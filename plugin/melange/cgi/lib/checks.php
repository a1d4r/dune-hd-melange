<?php
// The settings page (cgi/settings.php): "Проверить" - the manifest of
// AIOStreams, JacRed, the keys of Real-Debrid, TorBox, TMDB, TorrServer, a
// qBittorrent server; the format hints of their fields. Nothing written.

function aio_cgi_fmt_manifest()
{
    return aio_cgi_l('Нужен адрес вида https://…/manifest.json — скопируйте ссылку на конфиг из AIOStreams',
        'An address like https://…/manifest.json is needed: copy the config link from AIOStreams');
}

function aio_cgi_fmt_jacred()
{
    return aio_cgi_l('нужен адрес вида http(s)://хост[:порт]; ключ — 8–200 символов без пробелов, & и #',
        'an address like http(s)://host[:port] is needed; the key - 8-200 characters without spaces, & and #');
}

function aio_cgi_fmt_key($what)
{
    if ($what === 'rd')
        return aio_cgi_l('Ключ Real-Debrid: латинские буквы и цифры, 20–100 символов',
            'Real-Debrid key: Latin letters and digits, 20-100 characters');
    if ($what === 'tb')
        return aio_cgi_l('Ключ TorBox: латинские буквы, цифры и дефисы, 20–100 символов',
            'TorBox key: Latin letters, digits and hyphens, 20-100 characters');
    return aio_cgi_l('Ключ TMDB: ключ API (v3) — латинские буквы и цифры, 20–64 символа',
        'TMDB key: the API key (v3), Latin letters and digits, 20-64 characters');
}

// --- The check of the manifest.

// The manifest of a Stremio addon with "stream" among its resources.
function aio_cgi_is_manifest($body)
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

// GET manifest.json -> '' when it is a Stremio manifest, else the reason for the page.
// Redirects are followed here, not by curl: each address passes the rules of
// the typed one (aio_manifest_url), so a server cannot send this root
// request to http://127.0.0.1/cgi-bin/do?cmd=... of the Dune itself.
function aio_cgi_check($url)
{
    global $AIO_CGI_LOCATION;
    $end = time() + 10;
    for ($hops = 0; ; $hops++)
    {
        $why = aio_cgi_get($url, max(1, $end - time()));
        if ($why !== null)
            return $why;
        $next = aio_manifest_url(aio_cgi_location($url, $AIO_CGI_LOCATION));
        if ($next === '')
        {
            aio_cgi_log('manifest check: redirect to an address of the wrong form');
            return aio_cgi_l('Сервер перенаправил на недопустимый адрес', 'The server redirected to an address that is not allowed');
        }
        if ($hops === 3)
        {
            aio_cgi_log('manifest check: too many redirects');
            return aio_cgi_l('Слишком много перенаправлений', 'Too many redirects');
        }
        $url = $next;
    }
}

// One GET of aio_cgi_check -> '' (a manifest), the reason, or null for a
// redirect (its Location in $AIO_CGI_LOCATION).
function aio_cgi_get($url, $timeout)
{
    global $AIO_CGI_BODY, $AIO_CGI_LOCATION;
    $AIO_CGI_BODY = '';
    $AIO_CGI_LOCATION = '';
    $http = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    $ch = curl_init($url);
    $set = curl_setopt_array($ch, array(
        CURLOPT_WRITEFUNCTION => 'aio_cgi_body',
        CURLOPT_HEADERFUNCTION => 'aio_cgi_head',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => $http,
        CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        // HTTPS works only with the firmware CA bundle.
        CURLOPT_CAINFO => AIO_CGI_CA,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        // As the plugin asks for streams.
        CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
        CURLOPT_HTTPHEADER => array('Accept: application/json')));
    if (!$set)
    {
        curl_close($ch);
        aio_cgi_log('manifest check: curl options refused');
        return aio_cgi_l('Сервер не отвечает (curl не настроен)', 'The server does not answer (curl not set up)');
    }
    $ok = curl_exec($ch);
    $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    curl_close($ch);
    // Aborted by aio_cgi_body: over 256 KB.
    $big = $errno === 23;
    if ($ok === false && !$big)
    {
        // The text of curl without the address.
        $host = (string) parse_url($url, PHP_URL_HOST);
        $err = preg_replace('/[0-9]{1,3}(\.[0-9]{1,3}){3}/', '…', $host !== '' ? str_replace($host, '…', $err) : $err);
        aio_cgi_log("manifest check: curl $errno");
        return aio_cgi_l('Сервер не отвечает', 'The server does not answer') . " (curl $errno: $err)";
    }
    if (in_array($code, array(301, 302, 303, 307, 308), true) && $AIO_CGI_LOCATION !== '')
        return null;
    if ($code !== 200)
    {
        aio_cgi_log("manifest check: HTTP $code");
        return "HTTP $code";
    }
    if ($big)
    {
        aio_cgi_log('manifest check: reply over 256 KB');
        return aio_cgi_l('Ответ сервера больше 256 КБ — это не манифест AIOStreams', 'The reply is over 256 KB: not an AIOStreams manifest');
    }
    if (!aio_cgi_is_manifest($AIO_CGI_BODY))
    {
        aio_cgi_log('manifest check: not a Stremio manifest');
        return aio_cgi_l('Это не манифест Stremio', 'Not a Stremio manifest');
    }
    return '';
}

// The manifest just checked (aio_cgi_check returned '') -> "name vN" for the
// page: each at most 40 printable characters, '' when missing.
function aio_cgi_manifest_label($body)
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
function aio_cgi_aio_check_post()
{
    global $AIO_CGI_BODY;
    $url = aio_manifest_url(aio_cgi_param($_POST, 'manifest'));
    if ($url === '')
    {
        aio_cgi_log('manifest check: bad format');
        aio_cgi_json(200, array('ok' => false,
            'msg' => aio_cgi_fmt_manifest()));
    }
    $why = aio_cgi_check($url);
    if ($why !== '')
        aio_cgi_json(200, array('ok' => false, 'msg' => $why));
    aio_cgi_log('manifest check: ok');
    $label = aio_cgi_manifest_label($AIO_CGI_BODY);
    aio_cgi_json(200, array('ok' => true, 'msg' => aio_cgi_l('OK: манифест', 'OK: manifest') . ($label !== '' ? " $label" : '') .
        aio_cgi_l(', есть ресурс stream', ', with the stream resource')));
}

// --- "Проверить" and "Сравнить все" of JacRed: one search per request of the script.

// The searches of the check: films and a series everybody has, as the
// plugin asks (jacred.php).
function aio_cgi_jr_queries()
{
    return array(
        array('title' => 'Матрица', 'title_original' => 'The Matrix', 'year' => '1999', 'is_serial' => '1'),
        array('title' => 'Во все тяжкие', 'title_original' => 'Breaking Bad', 'year' => '2008', 'is_serial' => '2',
            'season' => '1'),
        array('title' => 'Дюна: Часть вторая', 'title_original' => 'Dune: Part Two', 'year' => '2024', 'is_serial' => '1'));
}

// The card search $q of aio_cgi_jr_queries() in JacRed $conf (aio_jacred_conf)
// -> array(ok, the reason for the page or '', results, ms).
function aio_cgi_jr_check($conf, $q)
{
    global $AIO_CGI_MAX, $AIO_CGI_LOCATION;
    $pq = '/api/v2.0/indexers/all/results?';
    foreach ($q as $k => $v)
        $pq .= "$k=" . rawurlencode($v) . '&';
    $pq = $conf['key_url'] !== '' ? $pq . 'apikey=' . $conf['key_url'] : rtrim($pq, '&');
    $url = $conf['base'] . $pq;
    // As the plugin takes it (AIO_JR_MAX_BYTES).
    $AIO_CGI_MAX = 4000000;
    $t = microtime(true);
    // Redirects as the plugin follows them (curl there), at most 3, 4 x 8 s in
    // all. Only the scheme, host, port and a path prefix may change: the same
    // search, never another request of this root CGI (IP Control of the Dune).
    for ($hops = 0; ; $hops++)
    {
        list($e, $code, $body, , $cut) = aio_cgi_srv_req($url, null, '');
        if ($e || !in_array($code, array(301, 302, 303, 307, 308), true) || $AIO_CGI_LOCATION === '')
            break;
        $next = aio_cgi_location($url, $AIO_CGI_LOCATION);
        $pre = substr($next, 0, -strlen($pq));
        if (substr($next, -strlen($pq)) !== $pq ||
            !preg_match('#^(?i)https?://[A-Za-z0-9.\-]+(?::[0-9]{1,5})?(?:/[A-Za-z0-9._~%!$&\'()*+,;=:@\-]+)*\z#', $pre))
        {
            aio_cgi_log('jacred check: redirect to an address of the wrong form');
            $AIO_CGI_MAX = AIO_CGI_MAX_BODY;
            return array(false, aio_cgi_l('Сервер перенаправил на недопустимый адрес',
                'The server redirected to an address that is not allowed'), 0, 0);
        }
        if ($hops === 3)
        {
            aio_cgi_log('jacred check: too many redirects');
            $AIO_CGI_MAX = AIO_CGI_MAX_BODY;
            return array(false, aio_cgi_l('Слишком много перенаправлений', 'Too many redirects'), 0, 0);
        }
        $url = $next;
    }
    $ms = (int) round((microtime(true) - $t) * 1000);
    $AIO_CGI_MAX = AIO_CGI_MAX_BODY;
    $j = !$e && $code === 200 && !$cut ? json_decode($body, true) : null;
    $ok = is_array($j) && isset($j['Results']) && is_array($j['Results']);
    $n = $ok ? count($j['Results']) : 0;
    // Never the address or the key.
    aio_cgi_log('jacred check: ' . ($e ? "curl $e" : "HTTP $code") . ($cut ? ', over 4 MB' : '') .
        ($ok ? ", $n results" : '') . ", $ms ms");
    if ($e)
        return array(false, aio_cgi_srv_net($e, 'JacRed'), 0, $ms);
    if ($code === 401 || $code === 403)
        return array(false, aio_cgi_l('Ключ не принят', 'The key is not accepted') . ($conf['key'] === '' ?
            aio_cgi_l(' — этот JacRed требует ключ', ': this JacRed needs a key') :
            aio_cgi_l(' — проверьте ключ', ': check the key')) . " (HTTP $code)", 0, $ms);
    if ($code !== 200)
        return array(false, sprintf(aio_cgi_l('Ответил HTTP %d — это не JacRed?', 'It answered HTTP %d: not a JacRed?'),
            $code), 0, $ms);
    if ($cut)
        return array(false, aio_cgi_l('Ответ больше 4 МБ — это не JacRed?', 'The reply is over 4 MB: not a JacRed?'), 0, $ms);
    if (!$ok)
        return array(false, aio_cgi_l('Ответ не похож на JacRed (нет списка раздач)',
            'The reply does not look like JacRed (no list of releases)'), 0, $ms);
    return array(true, '', $n, $ms);
}

// a=jr_speed: search q (0-2) of aio_cgi_jr_queries() in the built-in JacRed b
// or in the posted own one (url, key; not the stored one), nothing written.
// -> JSON {ok, msg, n, ms}; of a wrong format also bad = "format": the script stops by it.
function aio_cgi_jr_speed_post()
{
    $q = aio_cgi_param($_POST, 'q');
    $qs = aio_cgi_jr_queries();
    $b = aio_cgi_param($_POST, 'b');
    if ($b !== '')
        $conf = aio_jacred_builtin_conf($b);
    else
    {
        $o = aio_jacred_own(array('url' => aio_cgi_param($_POST, 'url'), 'key' => aio_cgi_param($_POST, 'key')));
        $conf = $o ? aio_jacred_conf($o['url'] . ($o['key'] !== '' ? '/?apikey=' . $o['key'] : '')) : null;
    }
    if (!$conf || !preg_match('/^[0-2]\z/', $q))
    {
        aio_cgi_log('jacred check: bad format');
        aio_cgi_json(200, array('ok' => false, 'msg' => aio_cgi_fmt_jacred(), 'n' => 0, 'ms' => 0, 'bad' => 'format'));
    }
    $r = aio_cgi_jr_check($conf, $qs[intval($q)]);
    aio_cgi_json(200, array('ok' => $r[0], 'msg' => $r[1], 'n' => $r[2], 'ms' => $r[3]));
}

// --- The check of the keys of Real-Debrid, TorBox, TMDB: by the button and on saving.

// The API of the service $what (rd, tb, tmdb). Tests define AIO_CGI_TEST_API
// (a stub on 127.0.0.1) before this file: <stub>/<what> instead.
function aio_cgi_api_base($what)
{
    $api = array('rd' => 'https://api.real-debrid.com/rest/1.0', 'tb' => 'https://api.torbox.app/v1/api',
        'tmdb' => 'https://api.themoviedb.org/3');
    return defined('AIO_CGI_TEST_API') ? AIO_CGI_TEST_API . "/$what" : $api[$what];
}

// "2027-03-01T12:00:00.000Z" -> "01.03.2027", '' when it is not a date.
function aio_cgi_date($s)
{
    return is_string($s) && preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})/', $s, $m) ? "$m[3].$m[2].$m[1]" : '';
}

// The key $key of $what (rd, tb, tmdb; its format checked) -> array(level, the
// line for the page): 'ok' - a valid key (with premium); 'warn' - valid
// without premium, or not checked (no connection, an error of the service):
// saved anyway; 'err' - refused (401/403): nothing is saved. Neither the key
// nor the URL with it goes to the log.
function aio_cgi_key_check($what, $key)
{
    $names = array('rd' => 'Real-Debrid', 'tb' => 'TorBox', 'tmdb' => 'TMDB');
    $name = $names[$what];
    $auth = array('Accept: application/json', "Authorization: Bearer $key");
    if ($what === 'rd')
        $r = aio_cgi_srv_req(aio_cgi_api_base('rd') . '/user', null, '', $auth);
    elseif ($what === 'tb')
        $r = aio_cgi_srv_req(aio_cgi_api_base('tb') . '/user/me', null, '', $auth);
    else
        $r = aio_cgi_srv_req(aio_cgi_api_base('tmdb') . '/configuration?api_key=' . rawurlencode($key), null, '',
            array('Accept: application/json'));
    list($e, $code, $body) = $r;
    $j = !$e && $code === 200 ? json_decode($body, true) : null;
    $d = is_array($j) && isset($j['data']) && is_array($j['data']) ? $j['data'] : array();
    if ($e)
        $res = array('warn', sprintf(aio_cgi_l('%s: ключ не проверен — %s; сохранится как есть',
            '%s: the key is not checked - %s; it is saved as it is'), $name, aio_cgi_srv_net($e, $name)));
    // TorBox may answer a bad key with 200 and an error code of its own.
    elseif ($code === 401 || $code === 403 || ($what === 'tb' && is_array($j) && isset($j['success'], $j['error']) &&
        $j['success'] === false && in_array($j['error'], array('BAD_TOKEN', 'AUTH_ERROR', 'NO_AUTH'), true)))
        $res = array('err', $what === 'rd' && $code === 403 ?
            sprintf(aio_cgi_l('%s: ключ не принят или аккаунт заблокирован (HTTP %d)',
            '%s: the key is not accepted or the account is locked (HTTP %d)'), $name, $code) :
            sprintf(aio_cgi_l('%s: ключ не принят (HTTP %d)', '%s: the key is not accepted (HTTP %d)'), $name, $code));
    elseif ($code !== 200)
        $res = array('warn', sprintf(aio_cgi_l('%s: ключ не проверен — сервис ответил HTTP %d; сохранится как есть',
            '%s: the key is not checked - the service answered HTTP %d; it is saved as it is'), $name, $code));
    elseif (!is_array($j) || ($what === 'rd' && !isset($j['type'])) || ($what === 'tb' && !isset($d['plan'])))
        $res = array('warn', sprintf(aio_cgi_l('%s: ключ не проверен — неожиданный ответ сервиса; сохранится как есть',
            '%s: the key is not checked - an unexpected reply of the service; it is saved as it is'), $name));
    elseif ($what === 'tmdb')
        $res = array('ok', aio_cgi_l('TMDB: ключ верный', 'TMDB: the key is valid'));
    else
    {
        $prem = $what === 'rd' ? $j['type'] === 'premium' : is_numeric($d['plan']) && $d['plan'] > 0;
        $date = aio_cgi_date($what === 'rd' ? (isset($j['expiration']) ? $j['expiration'] : '') :
            (isset($d['premium_expires_at']) ? $d['premium_expires_at'] : ''));
        $res = $prem ? array('ok', sprintf(aio_cgi_l('%s: ключ верный, премиум', '%s: the key is valid, premium'), $name) .
            ($date !== '' ? aio_cgi_l(' до ', ' until ') . $date : '')) :
            array('warn', sprintf(aio_cgi_l('%s: ключ верный, но премиума нет', '%s: the key is valid, but no premium'), $name));
    }
    aio_cgi_log("key check: $what: " . ($e ? "curl $e" : "HTTP $code") . ", $res[0]");
    return $res;
}

// a=key_check: the key of the posted field (what, key), nothing written. -> JSON {ok, level, msg}.
function aio_cgi_key_check_post()
{
    $what = aio_cgi_param($_POST, 'what');
    $in = trim(aio_cgi_param($_POST, 'key'), AIO_TRIM);
    if (!in_array($what, array('rd', 'tb', 'tmdb'), true))
        $res = array('err', aio_cgi_l('Неизвестный сервис', 'Unknown service'));
    elseif ($in === '')
        $res = array('err', aio_cgi_l('Ключ не указан', 'No key given'));
    elseif (aio_key_ok($what, $in) === '')
    {
        aio_cgi_log("key check: $what: bad format");
        $res = array('err', aio_cgi_fmt_key($what));
    }
    else
        $res = aio_cgi_key_check($what, $in);
    aio_cgi_json(200, array('ok' => $res[0] !== 'err', 'level' => $res[0], 'msg' => $res[1]));
}

// --- The check of TorrServer ("Проверить").

// GET <base>/echo -> array(true, its version) or array(false, the reason for
// the page). No redirects, http only, 3 s at most.
function aio_cgi_ts_check($base)
{
    global $AIO_CGI_BODY;
    $AIO_CGI_BODY = '';
    $ch = curl_init("$base/echo");
    $set = curl_setopt_array($ch, array(
        CURLOPT_WRITEFUNCTION => 'aio_cgi_body',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION));
    if (!$set)
    {
        curl_close($ch);
        aio_cgi_log('ts check: curl options refused');
        return array(false, aio_cgi_l('Не отвечает (curl не настроен)', 'No answer (curl not set up)'));
    }
    $ok = curl_exec($ch);
    $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $errno = curl_errno($ch);
    curl_close($ch);
    $local = strpos($base, 'http://127.0.0.1:') === 0 || strpos($base, 'http://localhost:') === 0;
    aio_cgi_log('ts check: ' . ($ok === false ? "curl $errno" : "HTTP $code"));
    if ($ok === false && $errno === 7)
        return array(false, aio_cgi_l('Не отвечает: соединение отклонено', 'No answer: connection refused') .
            ($local ? aio_cgi_l(' — установите и запустите приложение TorrServe на Дюне',
            ': install and start the TorrServe app on the Dune') :
            aio_cgi_l(' — TorrServer по этому адресу не запущен', ': no TorrServer runs at this address')));
    if ($ok === false && $errno === 28)
        return array(false, aio_cgi_l('Нет ответа за 3 с', 'No answer within 3 s'));
    if ($ok === false && $errno !== 23)
        return array(false, aio_cgi_l('Не отвечает', 'No answer') . " (curl $errno)");
    if ($code !== 200)
        return array(false, sprintf(aio_cgi_l('Ответил HTTP %d — это не TorrServer?', 'It answered HTTP %d: not a TorrServer?'), $code));
    $v = trim(substr($AIO_CGI_BODY, 0, 64));
    if (preg_match('/^MatriX\.[0-9A-Za-z.\-]{1,20}\z/', $v))
        return array(true, sprintf(aio_cgi_l('TorrServer %s отвечает', 'TorrServer %s answers'), $v));
    return array(false, aio_cgi_l('Ответил не TorrServer MatriX (старый 1.1?)', 'Not a TorrServer MatriX (an old 1.1?)'));
}

// --- The check of a server of "Download to server" ("Проверить" of a block).

// A server of aio_server_conf() -> array(ok, lines for the page): login,
// app/version, the category and the tags. Never the password or the cookie
// in the lines or the log.
function aio_cgi_srv_check($srv)
{
    $api = $srv['url'] . '/api/v2';
    $cookie = '';
    $parts = array();
    if ($srv['user'] !== '')
    {
        list($e, $code, $body, $cookie) = aio_cgi_srv_req("$api/auth/login",
            array('username' => $srv['user'], 'password' => $srv['pass']), '');
        aio_cgi_log("server check: login: " . ($e ? "curl $e" : "HTTP $code") . ($cookie !== '' ? ', cookie' : ''));
        if ($e)
            return array(false, array(aio_cgi_srv_net($e)));
        if ($code === 403)
            return array(false, array(aio_cgi_l('qBittorrent временно заблокировал Дюну после неудачных входов — подождите ' .
                '(по умолчанию до часа)', 'qBittorrent has banned the Dune for a while after failed logins: wait ' .
                '(up to an hour by default)')));
        // Before 5.2 (and rdt-client): 200 "Ok." / "Fails."; 5.2: 204 with the
        // cookie QBT_SID_<port> / 401. Every cookie of the reply goes back.
        $empty = $code >= 200 && $code < 300 && trim($body) === '';
        if ($code === 401 || trim($body) === 'Fails.')
            return array(false, array(aio_cgi_l('Неверный логин или пароль', 'Wrong login or password')));
        if ($code !== 200 && !$empty)
            return array(false, array(sprintf(aio_cgi_l('Вход: ответ HTTP %d — это не qBittorrent?', 'Login: HTTP %d: not a qBittorrent?'), $code)));
        // Any other 200 (a web page on the wrong port) proves nothing: the API decides.
        if (trim($body) === 'Ok.' || $code === 204)
            $parts[] = aio_cgi_l('вход принят', 'logged in');
    }
    list($e, $code, $body) = aio_cgi_srv_req("$api/app/version", null, $cookie);
    aio_cgi_log("server check: version: " . ($e ? "curl $e" : "HTTP $code"));
    $v = trim($body);
    $v = !$e && $code === 200 && preg_match('/^[^\x00-\x1F\x7F<>]{1,40}\z/u', $v) ? $v : '';
    if ($srv['user'] === '')
    {
        // No login: the version is the only proof of a qBittorrent WebAPI.
        if ($e)
            return array(false, array(aio_cgi_srv_net($e)));
        if ($code === 401 || $code === 403)
            return array(false, array(aio_cgi_l('Сервер требует вход: укажите логин и пароль', 'The server wants a login: fill in the login and password')));
        if ($v === '')
            return array(false, array(aio_cgi_l('Ответ не похож на qBittorrent', 'The reply does not look like qBittorrent') . ($code !== 200 ? " (HTTP $code)" : '')));
        $parts[] = aio_cgi_l('без входа', 'no login');
    }
    // After a login: no cookie (403), a web page, a wrong port.
    elseif ($v === '')
        return array(false, array(sprintf(aio_cgi_l('Вход ответил, но API не отвечает (%s) — это точно qBittorrent или rdt-client?',
            'The login answered, the API does not (%s): is it qBittorrent or rdt-client?'), $e ? "curl $e" : "HTTP $code")));
    $parts[] = aio_cgi_l('версия', 'version') . " $v";
    $more = array();
    if ($srv['category'] !== '')
    {
        $c = $srv['category'];
        list($e, $code, $body) = aio_cgi_srv_req("$api/torrents/categories", null, $cookie);
        $j = !$e && $code === 200 ? json_decode($body, true) : null;
        aio_cgi_log("server check: categories: " . ($e ? "curl $e" : "HTTP $code") . (is_array($j) ? ', JSON' : ''));
        if (!is_array($j))
            $parts[] = aio_cgi_l('категорию проверить не удалось', 'the category not checked');
        elseif (array_key_exists($c, $j))
            $parts[] = sprintf(aio_cgi_l('категория %s есть', 'category %s is there'), $c);
        else
            // qBittorrent creates a missing category on add; rdt-client takes any.
            $more[] = sprintf(aio_cgi_l('Категории %s нет — qBittorrent создаст её при первой закачке (папка — по умолчанию)', 'No category %s: qBittorrent makes it on the first download (the default folder)'), $c);
    }
    if ($srv['tags'] !== '')
    {
        list($e, $code, $body) = aio_cgi_srv_req("$api/torrents/tags", null, $cookie);
        $j = !$e && $code === 200 ? json_decode($body, true) : null;
        aio_cgi_log("server check: tags: " . ($e ? "curl $e" : "HTTP $code") . (is_array($j) ? ', JSON' : ''));
        if (!is_array($j))
            $parts[] = aio_cgi_l('теги проверить не удалось', 'the tags not checked');
        else
        {
            foreach (explode(',', $srv['tags']) as $t)
            {
                if (in_array($t, $j, true))
                    $parts[] = sprintf(aio_cgi_l('тег %s есть', 'tag %s is there'), $t);
                else
                    $more[] = sprintf(aio_cgi_l('Тега %s нет — qBittorrent создаст его при первой закачке (rdt-client теги не хранит)', 'No tag %s: qBittorrent makes it on the first download (rdt-client keeps no tags)'), $t);
            }
        }
    }
    return array(true, array_merge(array('OK: ' . implode(' · ', $parts)), $more));
}

// a=srv_check: the server of the posted block, nothing written.
function aio_cgi_srv_check_post()
{
    $v = array();
    foreach (array('url', 'user', 'pass', 'category', 'tags') as $k)
        $v[$k] = aio_cgi_param($_POST, $k);
    $srv = aio_server_conf($v);
    if ($srv)
        list($ok, $lines) = aio_cgi_srv_check($srv);
    else
    {
        aio_cgi_log('server check: bad format');
        list($ok, $lines) = array(false, array(aio_cgi_l('Нужен адрес вида http://хост:порт; логин, категория и теги — до 100 ' .
            'символов, пароль — до 200, без управляющих символов', 'An address like http://host:port is needed; login, ' .
            'category and tags up to 100 characters, password up to 200, no control characters')));
    }
    aio_cgi_json(200, array('ok' => $ok, 'msg' => implode("\n", $lines), 'lines' => $lines));
}
