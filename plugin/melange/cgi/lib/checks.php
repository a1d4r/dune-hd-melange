<?php
// The settings page (cgi/settings.php): "Проверить" - the manifest of
// AIOStreams, JacRed, the keys of Real-Debrid, TorBox, TMDB, TorrServer, Lampac,
// a qBittorrent server; the format hints of their fields. Nothing written.

function mel_cgi_fmt_manifest()
{
    return mel_cgi_l('Нужен адрес вида https://…/manifest.json — скопируйте ссылку на конфиг из AIOStreams',
        'An address like https://…/manifest.json is needed: copy the config link from AIOStreams');
}

function mel_cgi_fmt_jacred()
{
    return mel_cgi_l('нужен адрес вида http(s)://хост[:порт]; ключ — 8–200 символов без пробелов, & и #',
        'an address like http(s)://host[:port] is needed; the key - 8-200 characters without spaces, & and #');
}

function mel_cgi_fmt_lampac()
{
    return mel_cgi_l('Lampac: нужен адрес вида http://хост:порт или https://хост',
        'Lampac: use an address like http://host:port or https://host');
}

function mel_cgi_fmt_lp_token()
{
    return sprintf(mel_cgi_l('Токен провайдера: латинские буквы, цифры и _ + . - @ =, до %d символов',
        'Provider token: Latin letters, digits and _ + . - @ =, up to %d characters'), MEL_LP_TOKEN_MAX);
}

function mel_cgi_fmt_key($what)
{
    if ($what === 'rd')
        return mel_cgi_l('Ключ Real-Debrid: латинские буквы и цифры, 20–100 символов',
            'Real-Debrid key: Latin letters and digits, 20-100 characters');
    if ($what === 'tb')
        return mel_cgi_l('Ключ TorBox: латинские буквы, цифры и дефисы, 20–100 символов',
            'TorBox key: Latin letters, digits and hyphens, 20-100 characters');
    return mel_cgi_l('Ключ TMDB: ключ API (v3) — латинские буквы и цифры, 20–64 символа',
        'TMDB key: the API key (v3), Latin letters and digits, 20-64 characters');
}

// --- The check of the manifest.

// The manifest of a Stremio addon with "stream" among its resources.
function mel_cgi_is_manifest($body)
{
    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j['resources']) || !is_array($j['resources']))
        return false;
    foreach ($j['resources'] as $r)
    {
        if ($r === 'stream' || (is_array($r) && isset($r['name']) && $r['name'] === 'stream'))
            return true;
    }
    return false;
}

// GET manifest.json -> array('' when it is a Stremio manifest, else the
// reason for the page; its body).
// Redirects are followed here, not by curl: each address passes the rules of
// the typed one (mel_manifest_url), so a server cannot send this root
// request to http://127.0.0.1/cgi-bin/do?cmd=... of the Dune itself.
function mel_cgi_check($url)
{
    $end = time() + 10;
    for ($hops = 0; ; $hops++)
    {
        list($why, $r) = mel_cgi_get($url, max(1, $end - time()));
        if ($why !== null)
            return array($why, $r['body']);
        $next = mel_manifest_url(mel_cgi_location($url, $r['location']));
        if ($next === '')
        {
            mel_cgi_log('manifest check: redirect to an address of the wrong form');
            return array(mel_cgi_l('Сервер перенаправил на недопустимый адрес', 'The server redirected to an address that is not allowed'), '');
        }
        if ($hops === 3)
        {
            mel_cgi_log('manifest check: too many redirects');
            return array(mel_cgi_l('Слишком много перенаправлений', 'Too many redirects'), '');
        }
        $url = $next;
    }
}

// One GET of mel_cgi_check -> array('' (a manifest), the reason, or null for
// a redirect; the reply of mel_cgi_curl).
function mel_cgi_get($url, $timeout)
{
    $r = mel_cgi_curl($url, array(
        // As the plugin asks for streams.
        'headers' => array('Accept: application/json'),
        'connect' => min(5, $timeout),
        'total' => $timeout,
        'ua' => 'AIOStreams-DuneClient/' . MEL_VERSION,
        'gzip' => true));
    $code = $r['code'];
    if ($r['errno'] === -1)
    {
        mel_cgi_log('manifest check: curl options refused');
        return array(mel_cgi_l('Сервер не отвечает (curl не настроен)', 'The server does not answer (curl not set up)'), $r);
    }
    if ($r['errno'])
    {
        // The text of curl without the address.
        $host = (string) parse_url($url, PHP_URL_HOST);
        $err = preg_replace('/[0-9]{1,3}(\.[0-9]{1,3}){3}/', '…', $host !== '' ? str_replace($host, '…', $r['error']) :
            $r['error']);
        mel_cgi_log("manifest check: curl {$r['errno']}");
        return array(mel_cgi_l('Сервер не отвечает', 'The server does not answer') . " (curl {$r['errno']}: $err)", $r);
    }
    if (in_array($code, array(301, 302, 303, 307, 308), true) && $r['location'] !== '')
        return array(null, $r);
    if ($code !== 200)
    {
        mel_cgi_log("manifest check: HTTP $code");
        return array("HTTP $code", $r);
    }
    // Cut by mel_cgi_curl: over 256 KB.
    if ($r['cut'])
    {
        mel_cgi_log('manifest check: reply over 256 KB');
        return array(mel_cgi_l('Ответ сервера больше 256 КБ — это не манифест AIOStreams', 'The reply is over 256 KB: not an AIOStreams manifest'), $r);
    }
    if (!mel_cgi_is_manifest($r['body']))
    {
        mel_cgi_log('manifest check: not a Stremio manifest');
        return array(mel_cgi_l('Это не манифест Stremio', 'Not a Stremio manifest'), $r);
    }
    return array('', $r);
}

// The manifest just checked (mel_cgi_check returned '') -> "name vN" for the
// page: each at most 40 printable characters, '' when missing.
function mel_cgi_manifest_label($body)
{
    $j = json_decode($body, true);
    $out = array();
    foreach (array('name', 'version') as $k)
    {
        $v = isset($j[$k]) && is_string($j[$k]) ? trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $j[$k])) : '';
        preg_match('/^.{0,40}/su', $v, $m);
        $out[$k] = isset($m[0]) ? trim($m[0]) : '';
    }
    $v = preg_replace('/^[vV](?=[0-9])/', '', $out['version']);
    return trim($out['name'] . ($v !== '' ? " v$v" : ''));
}

// a=aio_check: the manifest of the posted field (not the stored one), nothing written.
function mel_cgi_aio_check_post()
{
    $url = mel_manifest_url(mel_cgi_param($_POST, 'manifest'));
    if ($url === '')
    {
        mel_cgi_log('manifest check: bad format');
        mel_cgi_json(200, array('ok' => false,
            'msg' => mel_cgi_fmt_manifest()));
    }
    list($why, $body) = mel_cgi_check($url);
    if ($why !== '')
        mel_cgi_json(200, array('ok' => false, 'msg' => $why));
    mel_cgi_log('manifest check: ok');
    $label = mel_cgi_manifest_label($body);
    mel_cgi_json(200, array('ok' => true, 'msg' => mel_cgi_l('OK: манифест', 'OK: manifest') . ($label !== '' ? " $label" : '') .
        mel_cgi_l(', есть ресурс stream', ', with the stream resource')));
}

// --- "Проверить" and "Сравнить все" of JacRed: one search per request of the script.

// The searches of the check: films and a series everybody has, as the
// plugin asks (jacred.php).
function mel_cgi_jr_queries()
{
    return array(
        array('title' => 'Матрица', 'title_original' => 'The Matrix', 'year' => '1999', 'is_serial' => '1'),
        array('title' => 'Во все тяжкие', 'title_original' => 'Breaking Bad', 'year' => '2008', 'is_serial' => '2',
            'season' => '1'),
        array('title' => 'Дюна: Часть вторая', 'title_original' => 'Dune: Part Two', 'year' => '2024', 'is_serial' => '1'));
}

// The card search $q of mel_cgi_jr_queries() in JacRed $conf (mel_jacred_conf)
// -> array(ok, the reason for the page or '', results, ms).
function mel_cgi_jr_check($conf, $q)
{
    $pq = '/api/v2.0/indexers/all/results?';
    foreach ($q as $k => $v)
        $pq .= "$k=" . rawurlencode($v) . '&';
    $pq = $conf['key_url'] !== '' ? $pq . 'apikey=' . $conf['key_url'] : rtrim($pq, '&');
    $url = $conf['base'] . $pq;
    $t = microtime(true);
    // Redirects as the plugin follows them (curl there), at most 3, 4 x 8 s in
    // all. Only the scheme, host, port and a path prefix may change: the same
    // search, never another request of this root CGI (IP Control of the Dune).
    for ($hops = 0; ; $hops++)
    {
        // The body as the plugin takes it (MEL_JR_MAX_BYTES).
        $r = mel_cgi_srv_req($url, null, '', null, 4000000);
        $e = $r['errno'];
        $code = $r['code'];
        if ($e || !in_array($code, array(301, 302, 303, 307, 308), true) || $r['location'] === '')
            break;
        $next = mel_cgi_location($url, $r['location']);
        $pre = substr($next, 0, -strlen($pq));
        if (substr($next, -strlen($pq)) !== $pq ||
            !preg_match('#^(?i)https?://[A-Za-z0-9.\-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9._~%!$&\'()*+,;=:@\-]+)*\z#', $pre))
        {
            mel_cgi_log('jacred check: redirect to an address of the wrong form');
            return array(false, mel_cgi_l('Сервер перенаправил на недопустимый адрес',
                'The server redirected to an address that is not allowed'), 0, 0);
        }
        if ($hops === 3)
        {
            mel_cgi_log('jacred check: too many redirects');
            return array(false, mel_cgi_l('Слишком много перенаправлений', 'Too many redirects'), 0, 0);
        }
        $url = $next;
    }
    $ms = (int) round((microtime(true) - $t) * 1000);
    $cut = $r['cut'];
    $j = !$e && $code === 200 && !$cut ? json_decode($r['body'], true) : null;
    $ok = is_array($j) && isset($j['Results']) && is_array($j['Results']);
    $n = $ok ? count($j['Results']) : 0;
    // Never the address or the key.
    mel_cgi_log('jacred check: ' . ($e ? "curl $e" : "HTTP $code") . ($cut ? ', over 4 MB' : '') .
        ($ok ? ", $n results" : '') . ", $ms ms");
    if ($e)
        return array(false, mel_cgi_srv_net($e, 'JacRed'), 0, $ms);
    if ($code === 401 || $code === 403)
        return array(false, mel_cgi_l('Ключ не принят', 'The key is not accepted') . ($conf['key'] === '' ?
            mel_cgi_l(' — этот JacRed требует ключ', ': this JacRed needs a key') :
            mel_cgi_l(' — проверьте ключ', ': check the key')) . " (HTTP $code)", 0, $ms);
    if ($code !== 200)
        return array(false, sprintf(mel_cgi_l('Ответил HTTP %d — это не JacRed?', 'It answered HTTP %d: not a JacRed?'),
            $code), 0, $ms);
    if ($cut)
        return array(false, mel_cgi_l('Ответ больше 4 МБ — это не JacRed?', 'The reply is over 4 MB: not a JacRed?'), 0, $ms);
    if (!$ok)
        return array(false, mel_cgi_l('Ответ не похож на JacRed (нет списка раздач)',
            'The reply does not look like JacRed (no list of releases)'), 0, $ms);
    return array(true, '', $n, $ms);
}

// a=jr_speed: search q (0-2) of mel_cgi_jr_queries() in the built-in JacRed b
// or in the posted own one (url, key; not the stored one), nothing written.
// -> JSON {ok, msg, n, ms}; of a wrong format also bad = "format": the script stops by it.
function mel_cgi_jr_speed_post()
{
    $q = mel_cgi_param($_POST, 'q');
    $qs = mel_cgi_jr_queries();
    $b = mel_cgi_param($_POST, 'b');
    if ($b !== '')
        $conf = mel_jacred_builtin_conf($b);
    else
    {
        $o = mel_jacred_own(array('url' => mel_cgi_param($_POST, 'url'), 'key' => mel_cgi_param($_POST, 'key')));
        $conf = $o ? mel_jacred_conf($o['url'] . ($o['key'] !== '' ? '/?apikey=' . $o['key'] : '')) : null;
    }
    if (!$conf || !preg_match('/^[0-2]\z/', $q))
    {
        mel_cgi_log('jacred check: bad format');
        mel_cgi_json(200, array('ok' => false, 'msg' => mel_cgi_fmt_jacred(), 'n' => 0, 'ms' => 0, 'bad' => 'format'));
    }
    $r = mel_cgi_jr_check($conf, $qs[intval($q)]);
    mel_cgi_json(200, array('ok' => $r[0], 'msg' => $r[1], 'n' => $r[2], 'ms' => $r[3]));
}

// --- The check of the keys of Real-Debrid, TorBox, TMDB: by the button and on saving.

// The API of the service $what (rd, tb, tmdb). Tests define MEL_CGI_TEST_API
// (a stub on 127.0.0.1) before this file: <stub>/<what> instead.
function mel_cgi_api_base($what)
{
    $api = array('rd' => 'https://api.real-debrid.com/rest/1.0', 'tb' => 'https://api.torbox.app/v1/api',
        'tmdb' => 'https://api.themoviedb.org/3');
    return defined('MEL_CGI_TEST_API') ? MEL_CGI_TEST_API . "/$what" : $api[$what];
}

// "2027-03-01T12:00:00.000Z" -> "01.03.2027", '' when it is not a date.
function mel_cgi_date($s)
{
    return is_string($s) && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})/', $s, $m) ? "$m[3].$m[2].$m[1]" : '';
}

// The key $key of $what (rd, tb, tmdb; its format checked) -> array(level, the
// line for the page): 'ok' - a valid key (with premium); 'warn' - valid
// without premium, or not checked (no connection, an error of the service):
// saved anyway; 'err' - refused (401/403): nothing is saved. Neither the key
// nor the URL with it goes to the log.
function mel_cgi_key_check($what, $key)
{
    $names = array('rd' => 'Real-Debrid', 'tb' => 'TorBox', 'tmdb' => 'TMDB');
    $name = $names[$what];
    $auth = array('Accept: application/json', "Authorization: Bearer $key");
    if ($what === 'rd')
        $r = mel_cgi_srv_req(mel_cgi_api_base('rd') . '/user', null, '', $auth);
    elseif ($what === 'tb')
        $r = mel_cgi_srv_req(mel_cgi_api_base('tb') . '/user/me', null, '', $auth);
    else
        $r = mel_cgi_srv_req(mel_cgi_api_base('tmdb') . '/configuration?api_key=' . rawurlencode($key), null, '',
            array('Accept: application/json'));
    $e = $r['errno'];
    $code = $r['code'];
    $j = !$e && $code === 200 ? json_decode($r['body'], true) : null;
    $d = is_array($j) && isset($j['data']) && is_array($j['data']) ? $j['data'] : array();
    if ($e)
        $res = array('warn', sprintf(mel_cgi_l('%s: ключ не проверен — %s; сохранится как есть',
            '%s: the key was not checked - %s; it will be saved as is'), $name, mel_cgi_srv_net($e, $name)));
    // TorBox may answer a bad key with 200 and an error code of its own.
    elseif ($code === 401 || $code === 403 || ($what === 'tb' && is_array($j) && isset($j['success'], $j['error']) &&
        $j['success'] === false && in_array($j['error'], array('BAD_TOKEN', 'AUTH_ERROR', 'NO_AUTH'), true)))
        $res = array('err', $what === 'rd' && $code === 403 ?
            sprintf(mel_cgi_l('%s: ключ не принят или аккаунт заблокирован (HTTP %d)',
            '%s: the key is not accepted or the account is locked (HTTP %d)'), $name, $code) :
            sprintf(mel_cgi_l('%s: ключ не принят (HTTP %d)', '%s: the key is not accepted (HTTP %d)'), $name, $code));
    elseif ($code !== 200)
        $res = array('warn', sprintf(mel_cgi_l('%s: ключ не проверен — сервис ответил HTTP %d; сохранится как есть',
            '%s: the key was not checked - the service answered HTTP %d; it will be saved as is'), $name, $code));
    elseif (!is_array($j) || ($what === 'rd' && !isset($j['type'])) || ($what === 'tb' && !isset($d['plan'])))
        $res = array('warn', sprintf(mel_cgi_l('%s: ключ не проверен — неожиданный ответ сервиса; сохранится как есть',
            '%s: the key was not checked - unexpected reply from the service; it will be saved as is'), $name));
    elseif ($what === 'tmdb')
        $res = array('ok', mel_cgi_l('TMDB: ключ верный', 'TMDB: the key is valid'));
    else
    {
        $prem = $what === 'rd' ? $j['type'] === 'premium' : is_numeric($d['plan']) && $d['plan'] > 0;
        $date = mel_cgi_date($what === 'rd' ? (isset($j['expiration']) ? $j['expiration'] : '') :
            (isset($d['premium_expires_at']) ? $d['premium_expires_at'] : ''));
        $res = $prem ? array('ok', sprintf(mel_cgi_l('%s: ключ верный, премиум', '%s: the key is valid, premium'), $name) .
            ($date !== '' ? mel_cgi_l(' до ', ' until ') . $date : '')) :
            array('warn', sprintf(mel_cgi_l('%s: ключ верный, но премиума нет', '%s: the key is valid, but no premium'), $name));
    }
    mel_cgi_log("key check: $what: " . ($e ? "curl $e" : "HTTP $code") . ", $res[0]");
    return $res;
}

// a=key_check: the key of the posted field (what, key), nothing written. -> JSON {ok, level, msg}.
function mel_cgi_key_check_post()
{
    $what = mel_cgi_param($_POST, 'what');
    $in = trim(mel_cgi_param($_POST, 'key'), MEL_TRIM);
    if (!in_array($what, array('rd', 'tb', 'tmdb'), true))
        $res = array('err', mel_cgi_l('Неизвестный сервис', 'Unknown service'));
    elseif ($in === '')
        $res = array('err', mel_cgi_l('Ключ не указан', 'No key given'));
    elseif (mel_key_ok($what, $in) === '')
    {
        mel_cgi_log("key check: $what: bad format");
        $res = array('err', mel_cgi_fmt_key($what));
    }
    else
        $res = mel_cgi_key_check($what, $in);
    mel_cgi_json(200, array('ok' => $res[0] !== 'err', 'level' => $res[0], 'msg' => $res[1]));
}

// --- The check of TorrServer ("Проверить").

// GET <base>/echo, then (0.45.0) POST <base>/settings {"action":"get"} with
// Basic auth by $auth (mel_ts_auth, '' none): it asks for one ->
// array(true, its version) or array(false, the reason for the page); /settings
// not 200 (nor 401) or no answer there -> array(true, that, 'warn').
function mel_cgi_ts_check($base, $auth = '')
{
    // mel_ts_call (net.php), 3 s at most a request; errno -1: its options refused.
    $r = mel_ts_call("$base/echo", null, 3);
    $err = $r['err'];
    $errno = $r['errno'];
    $code = $r['code'];
    if ($errno === -1)
    {
        mel_cgi_log('ts check: curl options refused');
        return array(false, mel_cgi_l('Не отвечает (curl не настроен)', 'No answer (curl not set up)'));
    }
    $ok = $err === '';
    $local = strpos($base, 'http://127.0.0.1:') === 0 || strpos($base, 'http://localhost:') === 0;
    mel_cgi_log('ts check: ' . ($ok === false ? "curl $errno" : "HTTP $code"));
    if ($ok === false && $errno === 7)
        return array(false, mel_cgi_l('Не отвечает: соединение отклонено', 'No answer: connection refused') .
            ($local ? mel_cgi_l(' — установите и запустите приложение TorrServe на Дюне',
            ': install and start the TorrServe app on the Dune') :
            mel_cgi_l(' — TorrServer по этому адресу не запущен', ': TorrServer is not running at this address')));
    if ($ok === false && $errno === 28)
        return array(false, mel_cgi_l('Нет ответа за 3 с', 'No answer within 3 s'));
    if ($ok === false && $errno !== 23)
        return array(false, mel_cgi_l('Не отвечает', 'No answer') . " (curl $errno)");
    if ($code !== 200)
        return array(false, sprintf(mel_cgi_l('Ответил HTTP %d — это не TorrServer?', 'It answered HTTP %d: not a TorrServer?'), $code));
    $v = trim(substr($r['body'], 0, 64));
    if (!preg_match('/^MatriX\.[0-9A-Za-z.\-]{1,20}\z/', $v))
        return array(false, mel_cgi_l('Ответил не TorrServer MatriX (старый 1.1?)', 'Not a TorrServer MatriX (an old 1.1?)'));
    // /echo is open always; /settings (as /torrents of the plugin) asks for the login when TorrServer has one.
    $r = mel_ts_call("$base/settings", '{"action":"get"}', 3, $auth);
    $st = $r['errno'] === -1 ? null : ($r['err'] === '' || $r['errno'] === 23 ? "HTTP {$r['code']}" :
        "curl {$r['errno']}");
    mel_cgi_log('ts check: settings ' . ($auth !== '' ? 'with' : 'without') . ' a login: ' . (is_null($st) ? 'curl options refused' : $st));
    if ($st === 'HTTP 401')
        return array(false, $auth === '' ? mel_cgi_l('TorrServer просит логин и пароль', 'TorrServer asks for a login and password') :
            mel_cgi_l('TorrServer не принял логин или пароль', 'TorrServer did not accept the login or password'));
    if ($st === 'HTTP 200')
        return array(true, sprintf(mel_cgi_l('TorrServer %s отвечает', 'TorrServer %s is responding'), $v));
    return array(true, sprintf(mel_cgi_l('TorrServer %s отвечает, но /settings: %s', 'TorrServer %s is responding, but /settings: %s'),
        $v, is_null($st) ? mel_cgi_l('curl не настроен', 'curl not set up') : $st), 'warn');
}

// a=ts_check by POST (0.45.0: the password is not in the URL): the
// TorrServer of the posted ts, ts_user, ts_pass (mel_cgi_ts_parse), nothing written.
function mel_cgi_ts_check_post()
{
    list($a, $u, $p, $bad) = mel_cgi_ts_parse(mel_cgi_param($_POST, 'ts'), mel_cgi_param($_POST, 'ts_user'),
        mel_cgi_param($_POST, 'ts_pass'));
    if ($bad)
        mel_cgi_log('ts check: bad format of ' . $bad[0]);
    $res = $bad ? array(false, $bad[1]) : mel_cgi_ts_check(mel_ts_base($a), mel_ts_auth($u, $p));
    mel_cgi_json(200, array('ok' => $res[0], 'msg' => $res[1]) + (isset($res[2]) ? array('level' => $res[2]) : array()));
}

// --- The check of Lampac ("Проверить" of "Адрес Lampac", #adv).

// The film of the check: one every Lampac with a balancer finds, its ids known (no /externalids).
function mel_cgi_lp_title()
{
    return mel_lp_title(array('tmdb' => '157336', 'imdb' => 'tt0816692', 'kp' => '258687', 'title' => 'Интерстеллар',
        'original_title' => 'Interstellar', 'year' => '2014', 'serial' => 0, 'original_language' => 'en'));
}

// Seconds of a step of the search of the check at least.
define('MEL_CGI_LP_TICK', 0.5);

// "Не отвечает" of a transport error: its curl errno (of /version; of the search, an error of lampac.php: 0).
function mel_cgi_lp_down($errno)
{
    return array(false, mel_cgi_l('Lampac не отвечает', 'Lampac is not responding') . ($errno > 0 ? " (curl $errno)" : ''));
}

// Lampac $base (mel_lp_addr, not '') -> array(ok, the line for the page, level): GET /version?type=build first (no
// answer: "Не отвечает" at once, no search), then the search of mel_cgi_lp_title to its end (MEL_LP_SEARCH_MAX s, as on
// the Dune), both by MelLp::$auth (mel_lp_ask: the token of the provider, as Lampa). Its sources (balancers that found
// the film) - ok, none - warn; no source and a reason - its line. Never the address nor the token in the log.
function mel_cgi_lp_check($base)
{
    $r = mel_lp_ask($base, "$base/version?type=build", MEL_LP_CONNECT, MEL_LP_STEP, 65536);
    $ver = $r['err'] === '' && $r['code'] === 200 ? mel_lp_version($r['body']) : '';
    mel_cgi_log('lampac check: version ' . ($r['err'] !== '' ? ($r['errno'] > 0 ? "curl {$r['errno']}" : 'too big') :
        "HTTP {$r['code']}") . ($ver !== '' ? ", $ver" : '') . (MelLp::$auth['token'] !== '' ? ', a token' : '') .
        (MelLp::$auth['lampa'] ? ', as Lampa' : ''));
    if ($r['errno'] === -1)
        return array(false, mel_cgi_l('Lampac не отвечает (curl не настроен)', 'Lampac is not responding (curl not set up)'));
    if ($r['err'] !== '' && $r['err'] !== MEL_LP_TOO_BIG)
        return mel_cgi_lp_down($r['errno']);
    $st = mel_lp_search_start($base, mel_cgi_lp_title());
    while (!$st['done'])
    {
        $t = mel_lp_now();
        $st = mel_lp_search_step($st);
        // A /lifeevents of ready:false at once: a step at most every MEL_CGI_LP_TICK s, not a busy loop of the Dune
        // and of requests to that Lampac (the window of the Dune ticks by its timer).
        $left = MEL_CGI_LP_TICK - (mel_lp_now() - $t);
        if (!$st['done'] && $left > 0)
            mel_lp_wait((int) ceil($left * 1000000));
    }
    $n = count($st['sources']);
    $why = $st['err'];
    mel_cgi_log("lampac check: $n sources" . ($why !== '' ? ", $why" : '') . ($st['capped'] ? ', out of time' : ''));
    if (!$n && $why === 'down')
        return mel_cgi_lp_down(0);
    if (!$n && $why === 'forbidden')
        return array(false, mel_cgi_l('Доступ закрыт (403) — проверьте WAF в init.conf',
            'Access denied (403): check WAF in init.conf'));
    if (!$n && $why === 'accsdb' && MelLp::$auth['token'] !== '')
        return array(false, mel_cgi_l('Токен не принят', 'Token not accepted'));
    if (!$n && $why === 'accsdb')
        return array(false, mel_cgi_l('Провайдер просит вход — впишите токен', 'The provider asks for a login: enter the token'));
    if (!$n && $why === 'http')
        return array(false, sprintf(mel_cgi_l('Ответил HTTP %s — это не Lampac?', 'It answered HTTP %s: not a Lampac?'),
            $st['detail']));
    if (!$n && $why !== '')
        return array(false, mel_cgi_l('Это не Lampac', 'Not a Lampac server'));
    $msg = sprintf(mel_cgi_l('Lampac%s отвечает, балансеров: %d', 'Lampac%s is responding, balancers: %d'),
        $ver !== '' ? " $ver" : '', $n);
    return $n ? array(true, $msg, 'ok') : array(true, $msg, 'warn');
}

// a=lp_check: the Lampac of the posted lampac_url with the posted token of its provider and "Представляться
// приложением Lampa" (as typed, not the stored ones; the uid of data dir $dir), nothing written. The token never in
// the log nor the answer. -> JSON {ok, msg, level}.
function mel_cgi_lp_check_post($dir)
{
    $f = mel_cgi_fields();
    $a = mel_lp_addr(mel_cgi_param($_POST, $f['lampac']['name']));
    $tk = mel_lp_token(mel_cgi_param($_POST, $f['lp_token']['name']));
    $lampa = mel_cgi_param($_POST, $f['lp_lampa']['name']) === mel_cgi_toggle_of('lp_lampa', true);
    if (!is_string($a) || !is_string($tk))
        mel_cgi_log('lampac check: bad format' . (is_string($a) ? ' of the token' : ''));
    if (is_string($a) && $a !== '' && is_string($tk))
        MelLp::$auth = mel_lp_auth($tk, $lampa, $lampa ? mel_lp_uid_read($dir) : '');
    $res = !is_string($a) ? array(false, mel_cgi_fmt_lampac(), 'err') : ($a === '' ? array(false,
        mel_cgi_l('Впишите адрес Lampac', 'Enter the Lampac address'), 'err') : (!is_string($tk) ? array(false,
        mel_cgi_fmt_lp_token(), 'err') : mel_cgi_lp_check($a)));
    mel_cgi_json(200, array('ok' => $res[0], 'msg' => $res[1], 'level' => isset($res[2]) ? $res[2] : 'err'));
}

// --- The check of a server of "Download to server" ("Проверить" of a block).

// A server of mel_server_conf() -> array(ok, lines for the page): login,
// app/version, the category and the tags. Never the password or the cookie
// in the lines or the log.
function mel_cgi_qbt_check($srv)
{
    $api = $srv['url'] . '/api/v2';
    $cookie = '';
    $parts = array();
    if ($srv['user'] !== '')
    {
        $r = mel_cgi_srv_req("$api/auth/login", array('username' => $srv['user'], 'password' => $srv['pass']), '');
        $e = $r['errno'];
        $code = $r['code'];
        $body = $r['body'];
        $cookie = $r['cookies'];
        mel_cgi_log("server check: login: " . ($e ? "curl $e" : "HTTP $code") . ($cookie !== '' ? ', cookie' : ''));
        if ($e)
            return array(false, array(mel_cgi_srv_net($e)));
        if ($code === 403)
            return array(false, array(mel_cgi_l('qBittorrent временно заблокировал Дюну после неудачных входов — подождите ' .
                '(по умолчанию до часа)', 'qBittorrent has banned the Dune for a while after failed logins: wait ' .
                '(up to an hour by default)')));
        // Before 5.2 (and rdt-client): 200 "Ok." / "Fails."; 5.2: 204 with the
        // cookie QBT_SID_<port> / 401. Every cookie of the reply goes back.
        $empty = $code >= 200 && $code < 300 && trim($body) === '';
        if ($code === 401 || trim($body) === 'Fails.')
            return array(false, array(mel_cgi_l('Неверный логин или пароль', 'Wrong login or password')));
        if ($code !== 200 && !$empty)
            return array(false, array(sprintf(mel_cgi_l('Вход: ответ HTTP %d — это не qBittorrent?', 'Login: HTTP %d: not a qBittorrent?'), $code)));
        // Any other 200 (a web page on the wrong port) proves nothing: the API decides.
        if (trim($body) === 'Ok.' || $code === 204)
            $parts[] = mel_cgi_l('вход принят', 'logged in');
    }
    $r = mel_cgi_srv_req("$api/app/version", null, $cookie);
    $e = $r['errno'];
    $code = $r['code'];
    mel_cgi_log("server check: version: " . ($e ? "curl $e" : "HTTP $code"));
    $v = trim($r['body']);
    $v = !$e && $code === 200 && preg_match('/^[^\x00-\x1F\x7F<>]{1,40}\z/u', $v) ? $v : '';
    if ($srv['user'] === '')
    {
        // No login: the version is the only proof of a qBittorrent WebAPI.
        if ($e)
            return array(false, array(mel_cgi_srv_net($e)));
        if ($code === 401 || $code === 403)
            return array(false, array(mel_cgi_l('Сервер требует вход: укажите логин и пароль', 'The server requires a login: fill in the login and password')));
        if ($v === '')
            return array(false, array(mel_cgi_l('Ответ не похож на qBittorrent', 'The reply does not look like qBittorrent') . ($code !== 200 ? " (HTTP $code)" : '')));
        $parts[] = mel_cgi_l('без входа', 'no login');
    }
    // After a login: no cookie (403), a web page, a wrong port.
    elseif ($v === '')
        return array(false, array(sprintf(mel_cgi_l('Вход ответил, но API не отвечает (%s) — это точно qBittorrent или rdt-client?',
            'The login responded but the API does not (%s): is this really qBittorrent or rdt-client?'), $e ? "curl $e" : "HTTP $code")));
    $parts[] = mel_cgi_l('версия', 'version') . " $v";
    $more = array();
    if ($srv['category'] !== '')
    {
        $c = $srv['category'];
        $r = mel_cgi_srv_req("$api/torrents/categories", null, $cookie);
        $e = $r['errno'];
        $code = $r['code'];
        $j = !$e && $code === 200 ? json_decode($r['body'], true) : null;
        mel_cgi_log("server check: categories: " . ($e ? "curl $e" : "HTTP $code") . (is_array($j) ? ', JSON' : ''));
        if (!is_array($j))
            $parts[] = mel_cgi_l('категорию проверить не удалось', 'category not checked');
        elseif (array_key_exists($c, $j))
            $parts[] = sprintf(mel_cgi_l('категория %s есть', 'category %s exists'), $c);
        else
            // qBittorrent creates a missing category on add; rdt-client takes any.
            $more[] = sprintf(mel_cgi_l('Категории %s нет — qBittorrent создаст её при первой закачке (папка — по умолчанию)', 'No category %s: qBittorrent will create it on the first download (in the default folder)'), $c);
    }
    if ($srv['tags'] !== '')
    {
        $r = mel_cgi_srv_req("$api/torrents/tags", null, $cookie);
        $e = $r['errno'];
        $code = $r['code'];
        $j = !$e && $code === 200 ? json_decode($r['body'], true) : null;
        mel_cgi_log("server check: tags: " . ($e ? "curl $e" : "HTTP $code") . (is_array($j) ? ', JSON' : ''));
        if (!is_array($j))
            $parts[] = mel_cgi_l('теги проверить не удалось', 'tags not checked');
        else
        {
            foreach (explode(',', $srv['tags']) as $t)
            {
                if (in_array($t, $j, true))
                    $parts[] = sprintf(mel_cgi_l('тег %s есть', 'tag %s exists'), $t);
                else
                    $more[] = sprintf(mel_cgi_l('Тега %s нет — qBittorrent создаст его при первой закачке (rdt-client теги не хранит)', 'No tag %s: qBittorrent will create it on the first download (rdt-client does not store tags)'), $t);
            }
        }
    }
    return array(true, array_merge(array('OK: ' . implode(' · ', $parts)), $more));
}

// A Transmission server of mel_server_conf() (0.47.0) -> array(ok, lines for
// the page): session-get by mel_trm_call (its session id handshake): login,
// version; labels of a server before 4.0 (rpc-version 17) are dropped by it.
// The folder is not checked. Never the password, the Authorization or the
// session id in the lines or the log.
function mel_cgi_trm_check($srv)
{
    $r = mel_trm_call($srv, json_encode(array('method' => 'session-get',
        'arguments' => array('fields' => array('version', 'rpc-version')))), 3, 8);
    $code = $r['code'];
    $e = $r['errno'];
    mel_cgi_log('server check: rpc' . ($r['sid'] ? ' with session id' : '') . ': ' . ($e ? "curl $e" : "HTTP $code"));
    if ($e)
        return array(false, array(mel_cgi_srv_net($e)));
    if ($code === 401)
        return array(false, array($srv['user'] === '' ? mel_cgi_l('Сервер требует вход: укажите логин и пароль',
            'The server requires a login: fill in the login and password') : mel_cgi_l('Неверный логин или пароль',
            'Wrong login or password')));
    if ($code === 403)
        return array(false, array(mel_cgi_l('Сервер не пускает Дюну: добавьте её адрес в белый список Transmission (rpc-whitelist)',
            'The server does not let the Dune in: add its address to the Transmission whitelist (rpc-whitelist)')));
    // The host name of the address is not in its rpc-host-whitelist.
    if ($code === 421)
        return array(false, array(mel_cgi_l('Transmission не принимает имя сервера в адресе: укажите IP или добавьте имя в ' .
            'rpc-host-whitelist', 'Transmission does not accept the server name of the address: use an IP or add the name to ' .
            'rpc-host-whitelist')));
    $j = $code === 200 ? json_decode($r['body'], true) : null;
    $a = is_array($j) && isset($j['result'], $j['arguments']) && $j['result'] === 'success' && is_array($j['arguments']) ?
        $j['arguments'] : array();
    // "4.1.3 (838877323f)" -> 4.1.3.
    if (!isset($a['version']) || !is_string($a['version']) || !preg_match('/^[0-9][0-9A-Za-z.+\-]{0,19}/', $a['version'], $m))
        return array(false, array(sprintf(mel_cgi_l('Ответ не похож на Transmission (HTTP %d) — проверьте адрес',
            'The reply does not look like Transmission (HTTP %d): check the address'), $code)));
    $more = array();
    if ($srv['tags'] !== '' && isset($a['rpc-version']) && is_int($a['rpc-version']) && $a['rpc-version'] < 17)
        $more[] = mel_cgi_l('Метки сохраняются с Transmission 4.0 — этот сервер их пропустит',
            'Labels are kept since Transmission 4.0: this server will skip them');
    return array(true, array_merge(array('OK: ' . ($srv['user'] !== '' ? mel_cgi_l('вход принят', 'logged in') :
        mel_cgi_l('без входа', 'no login')) . ' · ' . mel_cgi_l('версия', 'version') . " $m[0]"), $more));
}

// a=srv_check: the server of the posted block, nothing written.
function mel_cgi_srv_check_post()
{
    $v = array();
    foreach (array('type', 'url', 'user', 'pass', 'category', 'tags') as $k)
        $v[$k] = mel_cgi_param($_POST, $k);
    $srv = mel_server_conf($v);
    if ($srv)
        list($ok, $lines) = $srv['type'] === 'tr' ? mel_cgi_trm_check($srv) : mel_cgi_qbt_check($srv);
    else
    {
        mel_cgi_log('server check: bad format');
        list($ok, $lines) = array(false, array(mel_cgi_l('Нужен адрес вида http://хост:порт; логин, категория и теги — до 100 ' .
            'символов, пароль — до 200, без управляющих символов', 'An address like http://host:port is needed; login, ' .
            'category and tags up to 100 characters, password up to 200, no control characters')));
    }
    mel_cgi_json(200, array('ok' => $ok, 'msg' => implode("\n", $lines), 'lines' => $lines));
}
