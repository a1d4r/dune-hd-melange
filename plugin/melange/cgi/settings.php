<?php
// Settings page of melange (www/cgi-bin/settings -> php-cgi): root on the
// device, open to the whole LAN without a password. Without the token of
// <data_dir>/web_token every request gets the same 403, with no
// diagnostics and no outgoing request.
//   GET  ?t=<token> -> the form with the current values, in the language of
//                      the Dune (aio_cgi_lang);
//   POST ?t=<token> -> format (no request out) -> the keys given checked with
//                      their services (a refused one: nothing written) ->
//                      write settings.json v 2 (aio_confs kept) -> the
//                      config of AIOStreams as a=aio_sync -> 303 to GET
//                      ?t=<token>&saved=1 (a reload does not post again);
//                      errors -> the form back (only without JS);
//   POST ?t=<token>&ajax=1 -> the same save without the config, JSON {ok,
//                      next, aio[, notes] | errors} to the script of the page
//                      (aio: create, update or '' - what a=aio_sync is to do):
//                      no page is ever an answer to a POST (a mobile browser
//                      would post it again on return);
//   POST ?t=<token>&a=aio_status&ajax=1 (url) -> GET /api/v1/status of that
//                      own AIOStreams server: version, TMDB of its own; JSON
//                      {ok, level, msg}, nothing written;
//   POST ?t=<token>&a=aio_sync&ajax=1 -> the config of AIOStreams on the
//                      chosen server (of the list or the own one, 0.35.1) by
//                      settings.json: the own config chosen -> nothing; the
//                      own server -> its TMDB by /status first; no config
//                      there -> no Debrid key: no
//                      request, else POST /api/v1/user of the template; a
//                      config -> GET ?raw=true, our fields, PUT (none if it has
//                      no Debrid at all). aio_confs written; JSON {ok, msg,
//                      skip, base, conf};
//   GET  ?t=<token>&a=log -> <FS_PREFIX>/tmp/run/melange.log masked, an attachment;
//   GET  ?t=<token>&a=ts_check&ts=<address> -> GET <TorrServer>/echo, JSON
//                      {ok, msg}, nothing written;
//   POST ?t=<token>&a=srv_check (url, user, pass, category, tags) -> login,
//                      app/version, categories, tags of that qBittorrent;
//                      JSON {ok, msg, lines}, nothing written;
//   POST ?t=<token>&a=aio_check&ajax=1 (manifest) -> GET of that manifest.json
//                      (redirects by the rules), JSON {ok, msg}, nothing written;
//   POST ?t=<token>&a=jr_speed&ajax=1 (b | url, key; q) -> search q of the
//                      check in that JacRed, JSON {ok, msg, n, ms}, nothing written;
//   POST ?t=<token>&a=key_check&ajax=1 (what, key) -> the key checked with
//                      Real-Debrid, TorBox or TMDB, JSON {ok, level, msg}, nothing written.
// Input only goes through the rules of common.php; it never reaches exec,
// a path or eval. stderr (dune_apk_cgi_err.log) gets reasons, never values.
// PHP 5.3 syntax; php-cgi has no date.timezone: dates only in the log
// download, after date_default_timezone_set('UTC').

require dirname(dirname(__FILE__)) . '/common.php';
require dirname(__FILE__) . '/aio_conf.php';

define('AIO_CGI_NAME', 'melange');
define('AIO_CGI_MAX_BODY', 262144);
define('AIO_CGI_CA', '/firmware/certs/ca-bundle.crt');
// The log download: at most its last 3 MB.
define('AIO_CGI_LOG_MAX', 3145728);

function aio_cgi_h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// The language of the page: 'ru' or 'en' (aio_cgi_lang).
$AIO_CGI_LANG = 'ru';

// The text in the language of the page.
function aio_cgi_l($ru, $en)
{
    return $GLOBALS['AIO_CGI_LANG'] === 'en' ? $en : $ru;
}

// The language of the Dune (interface_language of settings.properties, read
// as the firmware does: dunelib/config_utils.php): russian -> ru, any other
// -> en; no such file -> the first language of the browser (ru* -> ru, other
// -> en); none -> ru.
function aio_cgi_lang()
{
    $f = getenv('FS_PREFIX') . '/config/settings.properties';
    $cfg = array();
    clearstatcache();
    if (is_file($f) && is_readable($f) && filesize($f) <= 65536)
    {
        foreach (explode("\n", (string) file_get_contents($f, false, null, 0, 65536)) as $line)
        {
            $p = strpos($line, '=');
            if ($p !== false && substr($line, 0, 1) !== '#')
                $cfg[trim(substr($line, 0, $p))] = trim(substr($line, $p + 1));
        }
    }
    $l = isset($cfg['interface_language']) ? $cfg['interface_language'] : '';
    if ($l === 'custom')
        $l = isset($cfg['noncustom_interface_language']) ? $cfg['noncustom_interface_language'] : '';
    if ($l !== '')
        return $l === 'russian' ? 'ru' : 'en';
    $al = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE']) ?
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
    if (preg_match('/^\s*([A-Za-z]{1,8})/', $al, $m))
        return strtolower($m[1]) === 'ru' ? 'ru' : 'en';
    return 'ru';
}

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

function aio_cgi_param($arr, $key)
{
    if (!isset($arr[$key]) || !is_string($arr[$key]))
        return '';
    return get_magic_quotes_gpc() ? stripslashes($arr[$key]) : $arr[$key];
}

// A reason for the CGI log; never a value of the form.
function aio_cgi_log($msg)
{
    file_put_contents('php://stderr', AIO_CGI_NAME . " settings: $msg\n");
}

function aio_cgi_headers($code, $type)
{
    $reasons = array(303 => 'See Other', 403 => 'Forbidden', 405 => 'Method Not Allowed',
        500 => 'Internal Server Error');
    // php-cgi turns it into a first-line "Status:", the only one busybox httpd reads.
    if (isset($reasons[$code]))
        header("HTTP/1.0 $code " . $reasons[$code]);
    header("Content-Type: $type; charset=utf-8");
    header('Cache-Control: no-store');
    // The token is in the URL.
    header('Referrer-Policy: no-referrer');
}

function aio_cgi_send($code, $type, $body)
{
    aio_cgi_headers($code, $type);
    echo $body;
    exit(0);
}

// JSON for the script of the page; never a value of the form (passwords).
function aio_cgi_json($code, $data)
{
    aio_cgi_send($code, 'application/json', json_encode($data));
}

function aio_cgi_forbidden()
{
    aio_cgi_send(403, 'text/plain', "403 Forbidden\n");
}

// run_plugin_cgi sets PLUGIN_CGI_DIR = realpath(<tmp>/www/plugins/<name>)/cgi-bin,
// that is <storage>/plugins/melange/www/cgi-bin; the shell keeps the data
// in <storage>/plugins_data/melange. Else the path of Screenshoter:
// /persistfs (Sigma) or $FS_PREFIX/flashdata. '' for another plugin name.
function aio_cgi_data_dir()
{
    if (getenv('PLUGIN_NAME') !== AIO_CGI_NAME)
        return '';
    $cgi = (string) getenv('PLUGIN_CGI_DIR');
    if (!preg_match('#(^|/)\.\.?(/|\z)|//#', $cgi) &&
        preg_match('#^((?:/[^/]+)+)/plugins/' . AIO_CGI_NAME . '/www/cgi-bin\z#', $cgi, $m))
        return $m[1] . '/plugins_data/' . AIO_CGI_NAME;
    $p = '/persistfs/plugins_data/' . AIO_CGI_NAME;
    return is_dir($p) ? $p : getenv('FS_PREFIX') . '/flashdata/plugins_data/' . AIO_CGI_NAME;
}

// The CGI never creates it: a root-owned dir would lock the plugin out.
function aio_cgi_dir_ok($dir)
{
    clearstatcache();
    return $dir !== '' && !is_link($dir) && is_dir($dir);
}

function aio_cgi_same($a, $b)
{
    if (strlen($a) !== strlen($b))
        return false;
    $d = 0;
    for ($i = 0; $i < strlen($a); $i++)
        $d |= ord($a[$i]) ^ ord($b[$i]);
    return $d === 0;
}

function aio_cgi_token_ok($dir, $given)
{
    if (!aio_token_ok($given) || !aio_cgi_dir_ok($dir))
        return false;
    $f = "$dir/" . AIO_TOKEN_FILE;
    if (is_link($f) || !is_file($f))
        return false;
    $t = trim((string) file_get_contents($f, false, null, 0, 64));
    return aio_token_ok($t) && aio_cgi_same($t, $given);
}

function aio_cgi_rand_hex($n)
{
    $b = function_exists('openssl_random_pseudo_bytes') ? (string) openssl_random_pseudo_bytes($n) : '';
    if (strlen($b) !== $n)
        $b = (string) file_get_contents('/dev/urandom', false, null, 0, $n);
    if (strlen($b) !== $n)
        $b = substr(md5(uniqid(mt_rand(), true) . mt_rand(), true), 0, $n);
    return bin2hex($b);
}

// --- The check of the manifest.

$AIO_CGI_BODY = '';
// The limit of aio_cgi_body; the JacRed check raises it.
$AIO_CGI_MAX = AIO_CGI_MAX_BODY;
function aio_cgi_body($ch, $data)
{
    global $AIO_CGI_BODY, $AIO_CGI_MAX;
    // A different length aborts the transfer (curl 23).
    if (strlen($AIO_CGI_BODY) + strlen($data) > $AIO_CGI_MAX)
        return 0;
    $AIO_CGI_BODY .= $data;
    return strlen($data);
}

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

$AIO_CGI_LOCATION = '';
function aio_cgi_head($ch, $line)
{
    global $AIO_CGI_LOCATION;
    if (preg_match('/^Location:\s*(.*?)\s*$/i', $line, $m))
        $AIO_CGI_LOCATION = $m[1];
    return strlen($line);
}

// Location of a redirect -> the address it points to (relative to $base).
function aio_cgi_location($base, $loc)
{
    if (preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*:~', $loc))
        return $loc;
    preg_match('~^([^:]+:)//([^/]*)~', $base, $m);
    if (substr($loc, 0, 2) === '//')
        return $m[1] . $loc;
    if (substr($loc, 0, 1) === '/')
        return $m[0] . $loc;
    return substr($base, 0, strrpos($base, '/') + 1) . $loc;
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
    $pq = $conf[3] !== '' ? $pq . 'apikey=' . $conf[3] : rtrim($pq, '&');
    $url = $conf[0] . $pq;
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
        return array(false, aio_cgi_l('Ключ не принят', 'The key is not accepted') . ($conf[2] === '' ?
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
// -> JSON {ok, msg, n, ms}.
function aio_cgi_jr_speed_post()
{
    $q = aio_cgi_param($_POST, 'q');
    $qs = aio_cgi_jr_queries();
    $b = aio_cgi_param($_POST, 'b');
    $bi = aio_jacred_builtin();
    $conf = null;
    if ($b !== '' && isset($bi[$b]))
        $conf = array($bi[$b][0], $b, $bi[$b][1], rawurlencode($bi[$b][1]));
    elseif ($b === '')
    {
        $o = aio_jacred_own(array('url' => aio_cgi_param($_POST, 'url'), 'key' => aio_cgi_param($_POST, 'key')));
        $conf = $o ? aio_jacred_conf($o['url'] . ($o['key'] !== '' ? '/?apikey=' . $o['key'] : '')) : null;
    }
    if (!$conf || !preg_match('/^[0-2]\z/', $q))
    {
        aio_cgi_log('jacred check: bad format');
        aio_cgi_json(200, array('ok' => false, 'msg' => aio_cgi_fmt_jacred(), 'n' => 0, 'ms' => 0));
    }
    $r = aio_cgi_jr_check($conf, $qs[intval($q)]);
    aio_cgi_json(200, array('ok' => $r[0], 'msg' => $r[1], 'n' => $r[2], 'ms' => $r[3]));
}

// --- The check of the keys of Real-Debrid, TorBox, TMDB: by the button and on saving.

define('AIO_CGI_RD_API', 'https://api.real-debrid.com/rest/1.0');
define('AIO_CGI_TB_API', 'https://api.torbox.app/v1/api');
define('AIO_CGI_TMDB_API', 'https://api.themoviedb.org/3');

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
        $r = aio_cgi_srv_req(AIO_CGI_RD_API . '/user', null, '', $auth);
    elseif ($what === 'tb')
        $r = aio_cgi_srv_req(AIO_CGI_TB_API . '/user/me', null, '', $auth);
    else
        $r = aio_cgi_srv_req(AIO_CGI_TMDB_API . '/configuration?api_key=' . rawurlencode($key), null, '',
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

$AIO_CGI_COOKIES = array();
function aio_cgi_srv_head($ch, $line)
{
    global $AIO_CGI_COOKIES;
    if (preg_match('/^Set-Cookie:\s*([A-Za-z0-9_.\-]+=[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)/i', $line, $m))
        $AIO_CGI_COOKIES[] = $m[1];
    return aio_cgi_head($ch, $line);
}

// One request of the check: POST of $fields (null: GET) with the cookies
// $cookie (or the request headers $headers instead) -> array(curl errno or 0, HTTP code, body (256 KB at most),
// cookies of the reply, the body cut at the limit). No redirects, 3 s to
// connect, 8 s in all.
function aio_cgi_srv_req($url, $fields, $cookie, $headers = null)
{
    global $AIO_CGI_BODY, $AIO_CGI_COOKIES, $AIO_CGI_LOCATION;
    $AIO_CGI_BODY = '';
    $AIO_CGI_COOKIES = array();
    $AIO_CGI_LOCATION = '';
    $opt = array(
        CURLOPT_WRITEFUNCTION => 'aio_cgi_body',
        CURLOPT_HEADERFUNCTION => 'aio_cgi_srv_head',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CAINFO => AIO_CGI_CA,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // As aio_http_post of the plugin: no Referer or Origin, no Expect.
        CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION,
        CURLOPT_HTTPHEADER => is_array($headers) ? $headers :
            ($cookie !== '' ? array('Expect:', "Cookie: $cookie") : array('Expect:')));
    if (is_array($fields))
    {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = http_build_query($fields, '', '&');
    }
    $ch = curl_init($url);
    if (!curl_setopt_array($ch, $opt))
    {
        curl_close($ch);
        return array(-1, 0, '', '', false);
    }
    $ok = curl_exec($ch);
    $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $errno = curl_errno($ch);
    curl_close($ch);
    // 23: cut by aio_cgi_body, the start is there.
    $cut = $ok === false && $errno === 23;
    $errno = $ok === false && !$cut ? $errno : 0;
    return array($errno, $code, $AIO_CGI_BODY, implode('; ', $AIO_CGI_COOKIES), $cut);
}

// $what: the service at the address for "connection refused", '' for a client.
function aio_cgi_srv_net($errno, $what = '')
{
    if ($errno === 7)
        return sprintf(aio_cgi_l('Нет связи: соединение отклонено — %s по этому адресу не запущен?',
            'No connection: refused - is %s running at this address?'), $what !== '' ? $what : aio_cgi_l('клиент', 'the client'));
    if ($errno === 28)
        return aio_cgi_l('Нет связи: сервер не ответил вовремя', 'No connection: the server did not answer in time');
    if ($errno === 6)
        return aio_cgi_l('Нет связи: имя сервера не найдено', 'No connection: the server name was not found');
    return $errno === -1 ? aio_cgi_l('Нет связи (curl не настроен)', 'No connection (curl not set up)') :
        aio_cgi_l('Нет связи', 'No connection') . " (curl $errno)";
}

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

// --- Writing settings.json.

// Umask 077, a fresh randomly
// named temp file opened with 'x', lchown/lchgrp to the owner of the dir
// (l*: never through a symlink), rename over (replaces a symlink itself).
// fopen 'x' follows a *dangling* symlink and creates its target (PHP 5.3.6
// and 5.6), so the temp path is lstat-checked before and after fopen,
// before any data goes in. -> '' or the reason (for the log).
function aio_cgi_write($dir, $data)
{
    $part = "$dir/" . AIO_SETTINGS_FILE . '.' . aio_cgi_rand_hex(4) . '.part';
    clearstatcache();
    $ds = lstat($dir);
    umask(077);
    if (file_exists($part) || is_link($part))
        return 'temp name taken';
    $fp = fopen($part, 'x');
    if (!is_resource($fp))
        return 'fopen failed';
    clearstatcache();
    $ps = lstat($part);
    $fst = fstat($fp);
    if ($ps === false || is_link($part) || $ps['ino'] !== $fst['ino'])
    {
        fclose($fp);
        unlink($part);
        return 'temp path is not our file';
    }
    $n = fwrite($fp, $data);
    $why = fclose($fp) && $n === strlen($data) ? '' : 'write failed';
    if ($why === '')
    {
        // On the device root gives it to system; a CGI that is not root
        // (ATV, hypothesis) owns it already and may fail to chown.
        if (function_exists('lchown'))
            lchown($part, $ds['uid']);
        if (function_exists('lchgrp'))
            lchgrp($part, $ds['gid']);
        clearstatcache();
        $ps = lstat($part);
        // A file the plugin (the owner of the dir) cannot read is worse than none.
        if ($ps === false || $ps['uid'] !== $ds['uid'])
            $why = 'owner differs from the owner of the dir';
    }
    if ($why === '' && !rename($part, "$dir/" . AIO_SETTINGS_FILE))
        $why = 'rename failed';
    if ($why !== '')
        unlink($part);
    return $why;
}

// settings.json v 2 of $data (keys of aio_settings_read, its order) with
// saved_at now -> '' or the reason (for the log).
function aio_cgi_store($dir, $data)
{
    $data = array_merge(array('v' => 2), $data, array('saved_at' => time()));
    $json = json_encode($data);
    // PHP 5.3 turns a value that is not UTF-8 into null, not an error.
    return is_string($json) && json_decode($json, true) === $data ? aio_cgi_write($dir, $json) : 'json_encode failed';
}

// --- The config of AIOStreams (0.35.0): made and changed on the chosen
// server by "Сохранить" (a=aio_sync of the script, or within the save
// without JS). One config per server, none is ever deleted.

// The reply of /api/v1/user: 1 MB at most; 20 s each (POST and PUT took
// about a second).
define('AIO_CGI_AIO_MAX', 1048576);
define('AIO_CGI_AIO_TIMEOUT', 20);

// The API $what (user, status) of the server $base (tests point it to a stub).
function aio_cgi_aio_api($base, $what)
{
    return "$base/api/v1/$what";
}

// One request to the API $what (user by default, status) of $base:
// $method, $json (the body or null), $auth (array(uuid, password) or null)
// -> array(curl errno or 0, HTTP code, the reply decoded by aio_conf_decode
// (null: not JSON or over 1 MB)).
function aio_cgi_aio_req($base, $method, $json, $auth, $what = 'user')
{
    global $AIO_CGI_BODY, $AIO_CGI_MAX;
    $AIO_CGI_BODY = '';
    $AIO_CGI_MAX = AIO_CGI_AIO_MAX;
    $h = array('Accept: application/json', 'Expect:');
    if ($json !== null)
        $h[] = 'Content-Type: application/json';
    if ($auth)
        $h[] = 'Authorization: Basic ' . base64_encode("$auth[0]:$auth[1]");
    $opt = array(
        CURLOPT_WRITEFUNCTION => 'aio_cgi_body',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => AIO_CGI_AIO_TIMEOUT,
        CURLOPT_CAINFO => AIO_CGI_CA,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
        CURLOPT_HTTPHEADER => $h);
    if ($method !== 'GET')
    {
        $opt[CURLOPT_CUSTOMREQUEST] = $method;
        $opt[CURLOPT_POSTFIELDS] = $json;
    }
    // raw: the config itself, without the parent config the server merges
    // in (a PUT would bake it in); the web settings of AIOStreams ask so too.
    $ch = curl_init(aio_cgi_aio_api($base, $what) . ($what === 'user' && $method === 'GET' ? '?raw=true' : ''));
    $set = curl_setopt_array($ch, $opt);
    $ok = $set ? curl_exec($ch) : false;
    $code = $set ? intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)) : 0;
    $errno = $set ? curl_errno($ch) : -1;
    curl_close($ch);
    $AIO_CGI_MAX = AIO_CGI_MAX_BODY;
    // 23: over 1 MB, cut by aio_cgi_body - not a reply of AIOStreams.
    if ($ok === false && $errno !== 23)
        return array($errno, 0, null);
    return array(0, $code, $errno === 23 ? null : aio_conf_decode($AIO_CGI_BODY));
}

// A refused or failed request -> array(the reason for the page, the error
// code of AIOStreams or ''). The message of the server is shown as it is
// (at most 300 characters, no control bytes); never logged. $own: another
// server (its certificate may be self-signed).
function aio_cgi_aio_why($r, $host, $own = false)
{
    list($e, $code, $j) = $r;
    $ec = is_object($j) && isset($j->error) && is_object($j->error) && isset($j->error->code) &&
        is_string($j->error->code) && preg_match('/^[A-Z0-9_]{1,40}\z/', $j->error->code) ? $j->error->code : '';
    $msg = is_object($j) && isset($j->error) && is_object($j->error) && isset($j->error->message) &&
        is_string($j->error->message) ? trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $j->error->message)) : '';
    // Not UTF-8 (a lone surrogate of JSON): the HTTP code instead.
    if (!preg_match('//u', $msg))
        $msg = '';
    // curl 60, 51: the certificate is not taken.
    if ($own && ($e === 60 || $e === 51))
        return array("$host: " . aio_cgi_l('сертификат сервера не принят (самоподписанный?) — попробуйте http://',
            'the certificate of the server is not accepted (self-signed?): try http://'), '');
    if ($e)
        return array("$host: " . aio_cgi_srv_net($e, 'AIOStreams'), '');
    if ($code === 429)
        return array(sprintf(aio_cgi_l('лимит запросов сервера %s, повторите через минуту',
            'the request limit of the server %s, try again in a minute'), $host), $ec);
    if ($msg !== '')
    {
        preg_match('/^.{0,300}/su', $msg, $m);
        return array(sprintf(aio_cgi_l('%s ответил: %s', '%s answered: %s'), $host, $m[0]), $ec);
    }
    return array($code >= 200 && $code < 300 ? sprintf(aio_cgi_l('%s: неожиданный ответ сервера',
        '%s: an unexpected reply of the server'), $host) : sprintf(aio_cgi_l('%s ответил HTTP %d', '%s answered HTTP %d'),
        $host, $code), $ec);
}

// GET /api/v1/status of the server $base (no limit of /user there) ->
// array(ok, version or the reason (aio_cgi_aio_why), it has TMDB of its own).
function aio_cgi_aio_status($base)
{
    $r = aio_cgi_aio_req($base, 'GET', null, null, 'status');
    $d = aio_cgi_aio_ok($r) && isset($r[2]->data) && is_object($r[2]->data) ? $r[2]->data : null;
    $v = $d && isset($d->version) && is_string($d->version) && preg_match('/^[0-9A-Za-z.+\-]{1,40}\z/', $d->version) ?
        $d->version : '';
    $t = $d && isset($d->settings->metadata->tmdb) && is_object($d->settings->metadata->tmdb) ? $d->settings->metadata->tmdb :
        null;
    aio_cgi_log('aio status: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($v !== '' ? ', AIOStreams' : ''));
    if ($v === '')
    {
        if ($r[0] || !aio_cgi_aio_ok($r))
        {
            list($why) = aio_cgi_aio_why($r, (string) parse_url($base, PHP_URL_HOST), true);
            return array(false, $why, false);
        }
        return array(false, aio_cgi_l('Ответ не похож на AIOStreams', 'The reply does not look like AIOStreams'), false);
    }
    return array(true, $v, $t && ((isset($t->accessToken) && $t->accessToken === true) ||
        (isset($t->apiKey) && $t->apiKey === true)));
}

// a=aio_status: the own server of the posted field (url), nothing written. -> JSON {ok, level, msg}.
function aio_cgi_aio_status_post()
{
    $base = aio_aio_own(aio_cgi_param($_POST, 'url'));
    if ($base === '')
        aio_cgi_json(200, array('ok' => false, 'level' => 'err', 'msg' => aio_cgi_l(
            'Нужен адрес вида http(s)://хост[:порт] без пути', 'An address like http(s)://host[:port] without a path is needed')));
    $r = aio_cgi_aio_status($base);
    if (!$r[0])
        aio_cgi_json(200, array('ok' => false, 'level' => 'err', 'msg' => $r[1]));
    aio_cgi_json(200, array('ok' => true, 'level' => $r[2] ? 'ok' : 'warn', 'msg' => "AIOStreams $r[1] · " . ($r[2] ?
        aio_cgi_l('свой TMDB есть', 'TMDB of its own') : aio_cgi_l('своего TMDB нет: сверка названий — только с ключом TMDB',
        'no TMDB of its own: titles are matched only with a TMDB key'))));
}

// The reply of a request is a success of AIOStreams: 2xx, success true.
function aio_cgi_aio_ok($r)
{
    return !$r[0] && $r[1] >= 200 && $r[1] < 300 && is_object($r[2]) && isset($r[2]->success) && $r[2]->success === true;
}

// What the page shows of a config: the link to its web settings, login, password.
function aio_cgi_aio_show($c)
{
    return $c ? array('cfg' => aio_manifest_base($c['manifest']) . 'configure', 'login' => $c['uuid'],
        'pass' => $c['pass']) : null;
}

// aio_confs[$base] of settings.json set to $c (null: forgotten), the rest as
// it is now in the file. -> '' or the reason (for the log).
function aio_cgi_aio_keep($dir, $base, $c)
{
    $s = aio_settings_read($dir);
    if ($c)
        $s['aio_confs'][$base] = $c;
    else
        unset($s['aio_confs'][$base]);
    return aio_cgi_store($dir, $s);
}

// The config of the chosen server (of the list or the own one) by
// settings.json: none -> POST of the template with our fields; there -> GET,
// our fields, PUT. The own server: its TMDB by /status first. The own config
// chosen -> nothing (skip). No config and no Debrid key -> no request.
// -> array(ok, the line for the page, skip, the choice (aio_server),
// aio_cgi_aio_show() of its config now).
function aio_cgi_aio_sync($dir)
{
    set_time_limit(120);
    $s = aio_settings_read($dir);
    $choice = $s['aio_server'];
    $base = aio_settings_base($s);
    $host = (string) parse_url($base, PHP_URL_HOST);
    $conf = $base !== '' && isset($s['aio_confs'][$base]) ? $s['aio_confs'][$base] : null;
    if ($s['source'] === 'own' || $base === '')
    {
        aio_cgi_log('aio sync: own config or no address, skipped');
        return array(true, '', true, $choice, aio_cgi_aio_show($conf));
    }
    $fail = $conf ? aio_cgi_l('Конфиг не обновлён: ', 'The config is not updated: ') :
        aio_cgi_l('Конфиг не создан: ', 'No config made: ');
    // A config there may have a service of its own (set by hand): GET decides.
    if (!$conf && $s['rd_key'] === '' && $s['tb_key'] === '')
    {
        aio_cgi_log('aio sync: no Debrid key');
        return array(false, aio_cgi_l('Для конфига нужен ключ Debrid (Real-Debrid или TorBox)',
            'The config needs a Debrid key (Real-Debrid or TorBox)'), false, $choice, null);
    }
    $tpl = aio_conf_template();
    if (!$tpl)
    {
        aio_cgi_log('aio sync: no template');
        return array(false, $fail . aio_cgi_l('нет шаблона в плагине', 'no template in the plugin'), false, $choice,
            aio_cgi_aio_show($conf));
    }
    $tmdbs = null;
    if ($choice === 'own')
    {
        list($ok, $why, $tmdbs) = aio_cgi_aio_status($base);
        if (!$ok)
            return array(false, $fail . $why, false, $choice, aio_cgi_aio_show($conf));
    }
    if (!$conf)
    {
        $c = aio_conf_template();
        list($tmdb, $set) = aio_conf_patch($c, $tpl, aio_conf_params($s, null, $tmdbs));
        $pass = aio_cgi_rand_hex(12);
        $r = aio_cgi_aio_req($base, 'POST', aio_conf_encode((object) array('config' => $c, 'password' => $pass)), null);
        $d = aio_cgi_aio_ok($r) && isset($r[2]->data) && is_object($r[2]->data) ? $r[2]->data : null;
        $uuid = $d && isset($d->uuid) && is_string($d->uuid) ? $d->uuid : '';
        $enc = $d && isset($d->encryptedPassword) && is_string($d->encryptedPassword) &&
            preg_match('/^[A-Za-z0-9._~%=+\-]{1,1500}\z/', $d->encryptedPassword) ? $d->encryptedPassword : '';
        $new = $enc !== '' ? aio_aio_conf($base, array('uuid' => $uuid, 'pass' => $pass,
            'manifest' => "$base/stremio/$uuid/$enc/manifest.json", 'tmdb' => $tmdb, 'set' => $set)) : null;
        aio_cgi_log('aio sync: create: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($new ? ', made' : ''));
        if (!$new)
        {
            list($why, $ec) = aio_cgi_aio_why($r, $host, $choice === 'own');
            if ($ec !== '')
                aio_cgi_log("aio sync: $ec");
            return array(false, $fail . $why, false, $choice, null);
        }
        $w = aio_cgi_aio_keep($dir, $base, $new);
        if ($w !== '')
        {
            aio_cgi_log("aio sync: $w");
            return array(false, aio_cgi_l('Конфиг создан, но не записан на Дюне — ошибка записи',
                'The config is made but not written on the Dune: a write error'), false, $choice, null);
        }
        return array(true, sprintf(aio_cgi_l('Конфиг AIOStreams создан на %s', 'The AIOStreams config is made on %s'),
            $host), false, $choice, aio_cgi_aio_show($new));
    }
    $auth = array($conf['uuid'], $conf['pass']);
    $r = aio_cgi_aio_req($base, 'GET', null, $auth);
    $u = aio_cgi_aio_ok($r) && isset($r[2]->data) && is_object($r[2]->data) && isset($r[2]->data->userData) &&
        is_object($r[2]->data->userData) ? $r[2]->data->userData : null;
    aio_cgi_log('aio sync: get: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($u ? ', config' : ''));
    if ($u)
    {
        list($tmdb, $set) = aio_conf_patch($u, $tpl, aio_conf_params($s, $conf, $tmdbs));
        // No key of melange and none set by hand: PUT would get 400.
        if (!aio_conf_has_debrid($u))
        {
            aio_cgi_log('aio sync: no Debrid key, not changed');
            return array(false, aio_cgi_l('Конфиг не изменён: нужен хотя бы один ключ Debrid',
            'The config is not changed: at least one Debrid key is needed'), false, $choice, aio_cgi_aio_show($conf));
        }
        $r = aio_cgi_aio_req($base, 'PUT', aio_conf_encode((object) array('config' => $u)), $auth);
        aio_cgi_log('aio sync: put: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]"));
    }
    if (!$u || !aio_cgi_aio_ok($r))
    {
        list($why, $ec) = aio_cgi_aio_why($r, $host, $choice === 'own');
        if ($ec !== '')
            aio_cgi_log("aio sync: $ec");
        // The login and password are not taken any more: the config is
        // gone there (or the server lost its key). Forgotten here: the next
        // save makes a new one.
        if ($ec === 'USER_INVALID_DETAILS')
        {
            $w = aio_cgi_aio_keep($dir, $base, null);
            if ($w !== '')
                aio_cgi_log("aio sync: $w");
            return array(false, sprintf(aio_cgi_l('Сервер %s не узнал конфиг (удалён на сервере или сменён пароль?) — ' .
                'Melange его забыл; «Сохранить и создать конфиг» создаст новый',
                'The server %s does not know the config (deleted there or its password changed?): ' .
                'Melange forgot it; "Save and create the config" makes a new one'), $host), false, $choice,
                $w === '' ? null : aio_cgi_aio_show($conf));
        }
        return array(false, $fail . $why, false, $choice, aio_cgi_aio_show($conf));
    }
    $conf['tmdb'] = $tmdb;
    $conf['set'] = $set;
    $w = aio_cgi_aio_keep($dir, $base, $conf);
    if ($w !== '')
        aio_cgi_log("aio sync: $w");
    return array(true, sprintf(aio_cgi_l('Конфиг AIOStreams на %s обновлён', 'The AIOStreams config on %s is updated'), $host),
        false, $choice, aio_cgi_aio_show($conf));
}

// a=aio_sync: JSON {ok, msg, skip, base, conf: {cfg, login, pass} | null}.
function aio_cgi_aio_sync_post($dir)
{
    if (!aio_cgi_dir_ok($dir))
        aio_cgi_json(200, array('ok' => false, 'msg' => aio_cgi_l('Нет папки плагина на Дюне',
            'No folder of the plugin on the Dune'), 'skip' => false, 'base' => '', 'conf' => null));
    $r = aio_cgi_aio_sync($dir);
    aio_cgi_json(200, array('ok' => $r[0], 'msg' => $r[1], 'skip' => $r[2], 'base' => $r[3], 'conf' => $r[4]));
}

// --- The page.

// $name '' - read-only, not posted.
function aio_cgi_field($id, $name, $label, $value, $hint, $max = 2100)
{
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        // Not type=password: Chrome would offer to keep it in the Google password manager.
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" class="sec" value="' .
        aio_cgi_h($value) . '" ' .
        'autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="' . $max . '">' .
        '<button type="button" class="eye" data-for="' . $id . '" aria-label="' . aio_cgi_l('Показать', 'Show') . '">' .
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>' .
        '</button></div>' . ($hint !== '' ? '<p class="hint">' . $hint . '</p>' : '');
}

// The fields of a server on the form (s<n>_<field>), as aio_server_conf() takes them.
function aio_cgi_server_fields()
{
    return array('name', 'url', 'user', 'pass', 'category', 'tags', 'cache');
}

// A plain text field (no hiding); $max: the limit of aio_server_conf();
// $name '' - read-only, not posted.
function aio_cgi_plain($id, $name, $label, $value, $placeholder, $max = 300)
{
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" value="' .
        aio_cgi_h($value) . '" placeholder="' .
        aio_cgi_h($placeholder) . '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" ' .
        'maxlength="' . $max . '"></div>';
}

function aio_cgi_radio($id, $name, $value, $label, $on, $attr = '')
{
    return '<label class="cb"><input type="radio" id="' . $id . '" name="' . $name . '" value="' . aio_cgi_h($value) . '"' .
        $attr . ($on ? ' checked' : '') . '> ' . $label . '</label>';
}

// A link of a hint, opened without the address of the page (it has the token).
function aio_cgi_link($url, $text)
{
    return '<a href="' . aio_cgi_h($url) . '" target="_blank" rel="noopener noreferrer">' . aio_cgi_h($text) . '</a>';
}

// The servers of "Download to server": AIO_SERVERS_MAX blocks s<n>_<field>,
// open when filled. "Дублировать" of a block is made by the script. $servers: lists of strings by aio_cgi_server_fields().
function aio_cgi_servers($servers)
{
    // One short line each: the rest is in the README.
    $out = '<h2>' . aio_cgi_l('Скачать на сервер (необязательно)', 'Download to server (optional)') . '</h2>' .
        '<p class="hint">' . aio_cgi_l('qBittorrent или rdt-client: адрес веб-интерфейса; логин пустой — без входа.',
        'qBittorrent or rdt-client: the web UI address; no login - none needed.') . '</p><p class="hint">' .
        aio_cgi_l('Не проверяйте с неверным паролем подряд — qBittorrent блокирует Дюну.',
        'Do not check a wrong password again and again: qBittorrent bans the Dune.') . '</p>';
    for ($n = 1; $n <= AIO_SERVERS_MAX; $n++)
    {
        $v = isset($servers[$n - 1]) ? $servers[$n - 1] : array();
        foreach (aio_cgi_server_fields() as $k)
            $v[$k] = isset($v[$k]) && is_string($v[$k]) ? $v[$k] : '';
        $filled = $v['name'] . $v['url'] . $v['user'] . $v['pass'] . $v['category'] . $v['tags'] !== '';
        $out .= '<details class="srv"' . ($filled ? ' open' : '') . '><summary>' . aio_cgi_l('Сервер', 'Server') . " $n" .
            ($v['name'] !== '' ? ': ' . aio_cgi_h($v['name']) : '') . '</summary>' .
            aio_cgi_plain("s{$n}n", "s{$n}_name", aio_cgi_l('Название', 'Name'), $v['name'], 'qBittorrent', 40) .
            aio_cgi_plain("s{$n}u", "s{$n}_url", aio_cgi_l('Адрес', 'Address'), $v['url'], 'http://192.168.1.10:8080', 300) .
            aio_cgi_plain("s{$n}l", "s{$n}_user", aio_cgi_l('Логин', 'Login'), $v['user'], '', 100) .
            aio_cgi_field("s{$n}p", "s{$n}_pass", aio_cgi_l('Пароль', 'Password'), $v['pass'], '', 200) .
            aio_cgi_plain("s{$n}c", "s{$n}_category", aio_cgi_l('Категория (необязательно)', 'Category (optional)'),
                $v['category'], '', 100) .
            aio_cgi_plain("s{$n}g", "s{$n}_tags", aio_cgi_l('Теги через запятую (необязательно)',
                'Tags, comma-separated (optional)'), $v['tags'], '', 100) .
            '<label for="s' . $n . 'k">' . aio_cgi_l('Для кэша', 'For the cache of') . '</label><select id="s' . $n .
            'k" name="s' . $n . '_cache">';
        foreach (array_merge(array('' => aio_cgi_l('Не из кэша', 'Not from a cache')), aio_server_caches()) as $id => $name)
            $out .= '<option value="' . $id . '"' . ($v['cache'] === $id ? ' selected' : '') . '>' . $name . '</option>';
        $out .= '</select><button type="button" class="chk srvchk">' . aio_cgi_l('Проверить', 'Check') .
            '</button><div class="srvr"></div></details>';
    }
    // Shown by the script, which hides the empty blocks; without JS all five are open to fill.
    return $out . '<button type="button" id="addsrv" class="chk" hidden>' . aio_cgi_l('Добавить сервер', 'Add a server') .
        '</button>';
}

// JacRed: one of the built-in ones or the own one (radio "jacred"); the
// fields of the own one are always there, used when it is chosen.
// "Проверить" asks the chosen one (the own one by the fields of the form),
// "Сравнить все" every built-in one and the own one if filled; one at a time.
function aio_cgi_jacred($v)
{
    // By the source: the script swaps the texts of data-made / data-own.
    $t = array('h' => array(aio_cgi_l('JacRed — поиск раздач и озвучки', 'JacRed - releases and voice-overs'),
        aio_cgi_l('JacRed — дорожки и озвучки', 'JacRed - audio tracks and voice-overs')),
        'p' => array(aio_cgi_l('По нему ищет раздачи созданный конфиг; озвучки и дорожки Melange берёт из него же',
        'The config made finds releases with it; Melange takes voice-overs and tracks from it too'),
        aio_cgi_l('Только озвучки и дорожки; раздачи ищет ваш конфиг',
        'Voice-overs and tracks only; your config finds the releases')));
    $k = $v['source'] === 'own' ? 1 : 0;
    $out = '<h2 id="jrh" data-made="' . aio_cgi_h($t['h'][0]) . '" data-own="' . aio_cgi_h($t['h'][1]) . '">' . $t['h'][$k] .
        '</h2><p class="hint" id="jrp" data-made="' . aio_cgi_h($t['p'][0]) . '" data-own="' . aio_cgi_h($t['p'][1]) . '">' .
        $t['p'][$k] . '</p>' .
        // The created config on a server jacred.stream refuses (the script follows the choice).
        '<p class="hint" id="jr403"' . (!$k && in_array($v['aio_server'], aio_cgi_jr403(), true) ? '' : ' hidden') . '>' .
        aio_cgi_l('jacred.stream не пускает выбранный сервер AIOStreams — выберите другой JacRed',
        'jacred.stream refuses the chosen AIOStreams server: choose another JacRed') . '</p>' .
        // Not when jacred.stream itself is chosen (the script follows the choice).
        '<p class="hint" id="jrnote"' . ($v['jacred'] === AIO_JACRED_DEFAULT ? ' hidden' : '') . '>' .
        aio_cgi_l('Не ответил — Melange спросит jacred.stream.', 'No answer - Melange asks jacred.stream.') . '</p>';
    $i = 0;
    foreach (array_keys(aio_jacred_builtin()) as $id)
    {
        $i++;
        $out .= aio_cgi_radio("jrb$i", 'jacred', $id, aio_cgi_h($id), $v['jacred'] === $id,
            ' class="jrb" data-id="' . aio_cgi_h($id) . '"');
    }
    return $out . aio_cgi_radio('jrown', 'jacred', 'own', aio_cgi_l('Свой', 'Own'), $v['jacred'] === 'own') .
        aio_cgi_plain('jou', 'jacred_own_url', aio_cgi_l('Адрес своего JacRed', 'Address of the own JacRed'),
            $v['jo']['url'], 'http://192.168.1.10:9117', AIO_JACRED_URL_MAX) .
        // The config made on a public server: it can't reach a home address (the script follows the choice).
        '<p class="hint" id="jrlan"' . ($k || $v['aio_server'] === 'own' ? ' hidden' : '') . '>' .
        aio_cgi_l('Адрес в домашней сети публичному серверу недоступен: нужен адрес из интернета',
        'A home network address is out of reach of a public server: use a public one') .
        '</p>' .
        aio_cgi_field('jok', 'jacred_own_key', aio_cgi_l('Ключ своего JacRed (необязательно)', 'Key of the own JacRed (optional)'),
            $v['jo']['key'], '', 200) .
        '<button type="button" id="jcb" class="chk">' . aio_cgi_l('Проверить', 'Check') . '</button><div id="jcr"></div>' .
        '<button type="button" id="jrsb" class="chk">' . aio_cgi_l('Сравнить все', 'Compare all') .
        '</button><p class="hint">' . aio_cgi_l('Три поиска в каждом JacRed, с паузой 2 с.',
        'Three searches in each JacRed, 2 s apart.') . '</p><div id="jrsr"></div>';
}

// AIOStreams servers where jacred.stream answers 403 to the server (0.35.0).
function aio_cgi_jr403()
{
    return array('https://aiostreams.fortheweak.cloud');
}

// The chosen server (of the list or the own one) has a config made by melange.
function aio_cgi_aio_has($v)
{
    $b = $v['aio_server'] === 'own' ? aio_aio_own($v['aio_own']) : $v['aio_server'];
    return $b !== '' && isset($v['confs'][$b]);
}

// data- of an option of the AIOStreams choice for its config (none: '').
function aio_cgi_aio_data($c)
{
    return $c ? ' data-cfg="' . aio_cgi_h($c['cfg']) . '" data-login="' . aio_cgi_h($c['login']) . '" data-pass="' .
        aio_cgi_h($c['pass']) . '"' : '';
}

// "Конфиг AIOStreams - откуда Melange берёт раздачи" (0.35.2): one of two.
// "Melange создаст конфиг": the server (of the list or another one), the
// Debrid keys, the TMDB key under a server without TMDB of its own (another
// one: always), the config of the chosen server - its link, login and
// password, or "none yet". "У меня свой конфиг": its manifest address, only
// read. The script shows the fields of the chosen one (all without JS) and
// the config of the chosen server from data- of its option.
function aio_cgi_aio($v)
{
    $chk = '<button type="button" id="%s" class="chk">' . aio_cgi_l('Проверить', 'Check') . '</button><div id="%s"></div>';
    $own = aio_aio_own($v['aio_own']);
    $base = $v['aio_server'] === 'own' ? $own : $v['aio_server'];
    $sel = $base !== '' && isset($v['confs'][$base]) ? aio_cgi_aio_show($v['confs'][$base]) : null;
    $out = '<h2>' . aio_cgi_l('Конфиг AIOStreams — откуда Melange берёт раздачи',
        'AIOStreams config - where Melange gets releases') . '</h2>' .
        aio_cgi_radio('srcm', 'source', 'made', aio_cgi_l('Melange создаст конфиг', 'Melange makes the config'),
        $v['source'] !== 'own') . '<div id="smade"><p class="hint">' .
        aio_cgi_l('Нужен ключ Real-Debrid или TorBox.', 'A Real-Debrid or TorBox key is needed.') . '</p><p class="hint">' .
        aio_cgi_l('Раздачи ищет JacRed (раздел ниже), смотрятся через Debrid.',
        'JacRed (below) finds the releases, they play via Debrid.') . '</p>' .
        '<label for="as">' . aio_cgi_l('Сервер AIOStreams', 'AIOStreams server') . '</label><select id="as" name="aio_server">';
    foreach (aio_aio_servers() as $url => $tmdb)
    {
        $c = isset($v['confs'][$url]) ? aio_cgi_aio_show($v['confs'][$url]) : null;
        $out .= '<option value="' . aio_cgi_h($url) . '" data-tmdb="' . ($tmdb ? '1' : '0') . '"' .
            (in_array($url, aio_cgi_jr403(), true) ? ' data-jr403="1"' : '') . aio_cgi_aio_data($c) .
            ($v['aio_server'] === $url ? ' selected' : '') . '>' .
            aio_cgi_h(substr($url, strlen('https://'))) . ($tmdb ? '' : aio_cgi_l(' — без TMDB', ' - no TMDB')) . '</option>';
    }
    $c = $own !== '' && isset($v['confs'][$own]) ? aio_cgi_aio_show($v['confs'][$own]) : null;
    $out .= '<option value="own" data-tmdb="0" data-url="' . aio_cgi_h($own) . '"' . aio_cgi_aio_data($c) .
        ($v['aio_server'] === 'own' ? ' selected' : '') . '>' . aio_cgi_l('Другой сервер…', 'Another server…') .
        '</option></select><div id="aobox">' . aio_cgi_plain('ao', 'aio_own_url', aio_cgi_l('Адрес сервера AIOStreams',
        'AIOStreams server address'), $v['aio_own'], 'http://192.168.1.10:3000', 300) . sprintf($chk, 'aob', 'aor') . '</div>' .
        aio_cgi_field('rd', 'rd_key', aio_cgi_l('Ключ Real-Debrid (нужен хотя бы один)', 'Real-Debrid key (at least one is needed)'),
            $v['rd'], aio_cgi_l('Где взять: ', 'Get it at ') .
            aio_cgi_link('https://real-debrid.com/apitoken', 'real-debrid.com/apitoken'), 100) .
        sprintf($chk, 'rdb', 'rdr') .
        aio_cgi_field('tb', 'tb_key', aio_cgi_l('Ключ TorBox (нужен хотя бы один)', 'TorBox key (at least one is needed)'),
            $v['tb'], aio_cgi_l('Где взять: ', 'Get it at ') .
            aio_cgi_link('https://torbox.app/settings', 'torbox.app/settings'), 100) .
        sprintf($chk, 'tbb', 'tbr');
    $out .= '<div id="tmbox"><p class="hint" id="tmnote">' .
        aio_cgi_l('Без своего TMDB: без сверки названий, меньше раздач сериалов.',
        'No TMDB of its own: titles not matched, fewer series releases.') . '</p>' .
        aio_cgi_field('tm', 'tmdb_key', aio_cgi_l('Ключ TMDB (необязательно)', 'TMDB key (optional)'), $v['tmdb'],
        aio_cgi_link('https://www.themoviedb.org/settings/api', 'themoviedb.org') .
        aio_cgi_l(' → Settings → API, ключ v3', ' → Settings → API, the v3 key'), 100) . sprintf($chk, 'tmb', 'tmr') . '</div>' .
        '<div id="acfg"><p class="hint" id="anone"' . ($sel ? ' hidden' : '') . '>' .
        aio_cgi_l('Конфига на этом сервере ещё нет: создастся при сохранении.',
        'No config on this server yet: saving makes one.') . '</p>' .
        '<div id="ahave"' . ($sel ? '' : ' hidden') . '><p class="cfg"><a id="alink"' . ($sel ? ' href="' .
        aio_cgi_h($sel['cfg']) . '"' : '') . ' target="_blank" rel="noreferrer">' .
        aio_cgi_l('Открыть настройки AIOStreams', 'Open the AIOStreams settings') .
        '</a></p>' . aio_cgi_plain('al', '', aio_cgi_l('Логин', 'Login'), $sel ? $sel['login'] : '', '', 36) .
        aio_cgi_field('ap', '', aio_cgi_l('Пароль', 'Password'), $sel ? $sel['pass'] : '', aio_cgi_l(
        'Вход в настройки AIOStreams — по этому логину и паролю.', 'Log in to the AIOStreams settings with this login and password.'),
        24) . '<button type="button" id="apc" class="chk">' . aio_cgi_l('Копировать', 'Copy') . '</button><div id="apr"></div>' .
        '</div></div></div>';
    return $out . aio_cgi_radio('srco', 'source', 'own', aio_cgi_l('У меня свой конфиг AIOStreams',
        'I have my own AIOStreams config'), $v['source'] === 'own') . '<div id="sown">' .
        aio_cgi_field('m', 'manifest', aio_cgi_l('Ссылка на конфиг', 'Config link'), $v['manifest'],
        aio_cgi_l('Где взять: Save & Install → Stremio → Direct Manifest URL.',
        'Where: Save & Install → Stremio → Direct Manifest URL.')) . sprintf($chk, 'mb', 'mr') .
        '<p class="hint">' . aio_cgi_l('Melange этот конфиг не меняет.', 'Melange does not change this config.') .
        '</p><p class="hint">' . aio_cgi_l('Без Debrid (раздачи P2P) нужен TorrServer — раздел ниже.',
        'Without Debrid (P2P releases) TorrServer is needed: see below.') . '</p></div>';
}

// "Проверить" and "Сохранить" without leaving the page (XHR, ES5 for old
// WebViews): the page stays the answer to a GET. No XHR or JSON -> the
// form posts as usual. Messages go in by textContent; the texts are in T.
function aio_cgi_js()
{
    $t = array(
        'dup' => aio_cgi_l('Дублировать', 'Duplicate'), 'copy' => aio_cgi_l(' (копия)', ' (copy)'),
        'srv' => aio_cgi_l('Сервер', 'Server'),
        'checking' => aio_cgi_l('Проверяю…', 'Checking…'), 'saving' => aio_cgi_l('Сохраняю…', 'Saving…'),
        'save' => aio_cgi_l('Сохранить', 'Save'), 'saved' => aio_cgi_saved(), 'savec' => aio_cgi_savec(),
        'creating' => aio_cgi_l('Создаю конфиг…', 'Creating the config…'),
        'updating' => aio_cgi_l('Обновляю конфиг…', 'Updating the config…'),
        'copied' => aio_cgi_l('Пароль скопирован', 'The password is copied'),
        'nocopy' => aio_cgi_l('Не скопировалось — пароль показан, скопируйте вручную',
            'Not copied: the password is shown, copy it by hand'),
        'net0' => aio_cgi_l('Нет связи с Дюной: проверьте, что телефон в той же сети, и нажмите ещё раз',
            'No connection to the Dune: check that the phone is on the same network and try again'),
        'net403' => aio_cgi_l('Ссылка устарела: откройте настройки заново по QR-коду на Дюне',
            'The link is out of date: open the settings again by the QR code on the Dune'),
        'neterr' => aio_cgi_l('Ошибка Дюны (HTTP %d), попробуйте ещё раз', 'Error of the Dune (HTTP %d), try again'),
        'fmtj' => aio_cgi_fmt_jacred(), 'jo' => aio_cgi_l('Свой JacRed', 'Own JacRed'),
        'name' => 'JacRed', 'avail' => aio_cgi_l('доступен', 'available'),
        'time' => aio_cgi_l('время', 'time'), 'count' => aio_cgi_l('раздач', 'releases'), 'yes' => aio_cgi_l('да', 'yes'),
        'no' => aio_cgi_l('нет', 'no'), 'sec' => aio_cgi_l('с', 's'));
    return 'var T=' . json_encode($t) . ";\n" . <<<'JS'
(function(){
var f=document.forms[0];
if(!f)return;
var adv=document.getElementById("adv");
function blank(d){
var a=d.querySelectorAll("input[type=text]");
for(var i=0;i<a.length;i++)if(a[i].value!=="")return false;
return true;
}
function group(sel,addId,onReveal){
var ds=f.querySelectorAll(sel),ab=document.getElementById(addId);
function next(){
for(var i=0;i<ds.length;i++)if(ds[i].style.display==="none")return ds[i];
return null;
}
function reveal(d){
d.style.display="";
if(d.tagName==="DETAILS")d.open=true;
ab.hidden=!next();
onReveal();
}
for(var i=0;i<ds.length;i++)if(blank(ds[i]))ds[i].style.display="none";
ab.hidden=!next();
ab.addEventListener("click",function(){
var d=next();
if(!d)return;
reveal(d);
var a=d.querySelector("input[type=text]");
if(a)a.focus();
});
return {ds:ds,next:next,reveal:reveal};
}
function dupState(){
var a=f.querySelectorAll(".srvdup");
for(var i=0;i<a.length;i++)a[i].disabled=!sg.next();
}
var sg=group("details.srv","addsrv",dupState);
function fields(d){
var o={},a=d.querySelectorAll("input,select"),m;
for(var i=0;i<a.length;i++){
m=/^s([1-9])_([a-z]+)$/.exec(a[i].name);
if(m){o[m[2]]=a[i];o.n=m[1];}
}
return o;
}
function dup(d){
var t=sg.next();
if(!t||blank(d))return;
var s=fields(d),o=fields(t),k,sx=T.copy,c;
for(k in s)if(k!=="n"&&o[k])o[k].value=s[k].value;
c=(s.name.value!==""?s.name.value:T.srv+" "+s.n).slice(0,40-sx.length);
if(/[\ud800-\udbff]$/.test(c))c=c.slice(0,-1);
o.name.value=c+sx;
t.querySelector(".srvr").textContent="";
sg.reveal(t);
if(t.scrollIntoView)t.scrollIntoView();
o.name.focus();
}
for(var i=0;i<sg.ds.length;i++)(function(d){
var b=document.createElement("button");
b.type="button";
b.className="chk srvdup";
b.textContent=T.dup;
d.insertBefore(b,d.querySelector(".srvr"));
b.addEventListener("click",function(){dup(d);});
})(sg.ds[i]);
dupState();
var as=document.getElementById("as"),tmbox=document.getElementById("tmbox"),ao=document.getElementById("ao");
var sv=f.querySelector(".save"),ap=document.getElementById("ap"),apr=document.getElementById("apr");
var rdi=document.getElementById("rd"),tbi=document.getElementById("tb"),srcs=f.querySelectorAll("input[name=source]");
function ownUrl(){return ao.value.replace(/^\s+|\s+$/g,"").replace(/\/$/,"").replace(/:0+(\d)/g,":$1").toLowerCase();}
function swap(id,own){var e=document.getElementById(id);e.textContent=e.getAttribute(own?"data-own":"data-made");}
function aState(){
var o=as.options[as.selectedIndex],m=f.querySelector("input[name=source]:checked"),own=!!m&&m.value==="own";
var other=!!o&&o.value==="own",c=o?o.getAttribute("data-cfg"):null;
if(other&&ownUrl()!==o.getAttribute("data-url"))c=null;
document.getElementById("smade").style.display=own?"none":"";
document.getElementById("sown").style.display=own?"":"none";
document.getElementById("aobox").style.display=other?"":"none";
tmbox.style.display=!o||o.getAttribute("data-tmdb")==="1"?"none":"";
document.getElementById("tmnote").style.display=other?"none":"";
document.getElementById("anone").hidden=!!c;
document.getElementById("ahave").hidden=!c;
if(c&&ap.value!==o.getAttribute("data-pass")){
document.getElementById("alink").href=c;
document.getElementById("al").value=o.getAttribute("data-login");
ap.value=o.getAttribute("data-pass");
apr.textContent="";
}
swap("jrh",own);
swap("jrp",own);
document.getElementById("jr403").hidden=own||!o||o.getAttribute("data-jr403")!=="1";
document.getElementById("tsown").hidden=!own;
document.getElementById("jrlan").hidden=own||other;
var key=(rdi.value+tbi.value).replace(/\s+/g,"")!=="";
if(!sv.disabled)sv.textContent=!own&&!c&&key?T.savec:T.save;
}
as.addEventListener("change",aState);
ao.addEventListener("input",aState);
rdi.addEventListener("input",aState);
tbi.addEventListener("input",aState);
for(var i=0;i<srcs.length;i++)srcs[i].addEventListener("change",aState);
aState();
document.getElementById("apc").addEventListener("click",function(){
function done(ok){
if(!ok){ap.classList.remove("sec");document.querySelector(".eye[data-for=ap]").classList.add("on");}
show(apr,ok?"ok":"warn",[ok?T.copied:T.nocopy]);
}
// Not from ap: its masked text would copy as dots.
function old(){
var t=document.createElement("textarea"),ok=false;
t.value=ap.value;
t.setAttribute("readonly","");
t.style.position="fixed";t.style.left="-9999px";t.style.top="0";
document.body.appendChild(t);
t.focus();
t.select();
try{t.setSelectionRange(0,t.value.length);ok=document.execCommand("copy");}catch(e){}
document.body.removeChild(t);
done(ok);
}
if(navigator.clipboard&&window.isSecureContext)navigator.clipboard.writeText(ap.value).then(function(){done(true);},old);
else old();
});
var jn=document.getElementById("jrnote"),jrr=f.querySelectorAll("input[name=jacred]");
function jnState(){
var c=f.querySelector("input[name=jacred]:checked");
jn.hidden=!c||c.value==="jacred.stream";
}
for(var i=0;i<jrr.length;i++)jrr[i].addEventListener("change",jnState);
jnState();
if(!window.XMLHttpRequest||!window.JSON)return;
var base=f.getAttribute("action");
function req(method,url,body,done,to){
var x=new XMLHttpRequest();
x.open(method,url,true);
x.timeout=to||40000;
if(body!==null)x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");
x.onreadystatechange=function(){
if(x.readyState!==4)return;
var j=null;
try{j=JSON.parse(x.responseText);}catch(e){}
done(x.status,j&&typeof j==="object"?j:null);
};
x.send(body);
}
function why(s){
if(s===0)return T.net0;
if(s===403)return T.net403;
return T.neterr.replace("%d",s);
}
function show(el,cls,lines){
el.textContent="";
for(var i=0;i<lines.length;i++){
var p=document.createElement("p");
p.className=cls;
p.textContent=String(lines[i]);
el.appendChild(p);
}
}
var tb=document.getElementById("tsb"),tr=document.getElementById("tsr"),ti=document.getElementById("ts");
tb.addEventListener("click",function(){
if(tb.disabled)return;
tb.disabled=true;
show(tr,"hint",[T.checking]);
req("GET",base+"&a=ts_check&ts="+encodeURIComponent(ti.value),null,function(s,j){
tb.disabled=false;
if(j&&typeof j.ok==="boolean")show(tr,j.ok?"ok":"err",[j.msg]);
else show(tr,"err",[why(s)]);
});
});
function one(bid,rid,url,body){
var b=document.getElementById(bid),r=document.getElementById(rid);
b.addEventListener("click",function(){
if(b.disabled)return;
b.disabled=true;
show(r,"hint",[T.checking]);
req("POST",base+url,body(),function(s,j){
b.disabled=false;
if(j&&typeof j.ok==="boolean")show(r,j.level==="warn"?"warn":(j.ok?"ok":"err"),[j.msg]);
else show(r,"err",[why(s)]);
});
});
}
function val(id){return encodeURIComponent(document.getElementById(id).value);}
one("mb","mr","&a=aio_check&ajax=1",function(){return "manifest="+val("m");});
one("aob","aor","&a=aio_status&ajax=1",function(){return "url="+val("ao");});
one("rdb","rdr","&a=key_check&ajax=1",function(){return "what=rd&key="+val("rd");});
one("tbb","tbr","&a=key_check&ajax=1",function(){return "what=tb&key="+val("tb");});
one("tmb","tmr","&a=key_check&ajax=1",function(){return "what=tmdb&key="+val("tm");});
var cb=f.querySelectorAll(".srvchk");
for(var c=0;c<cb.length;c++)(function(b){
var d=b.parentNode,r=d.querySelector(".srvr");
b.addEventListener("click",function(){
if(b.disabled)return;
var p=[],a=d.querySelectorAll("input"),m;
for(var i=0;i<a.length;i++){
m=/^s[1-9]_(url|user|pass|category|tags)$/.exec(a[i].name);
if(m)p.push(m[1]+"="+encodeURIComponent(a[i].value));
}
b.disabled=true;
show(r,"hint",[T.checking]);
req("POST",base+"&a=srv_check&ajax=1",p.join("&"),function(s,j){
b.disabled=false;
if(j&&typeof j.ok==="boolean")show(r,j.ok?"ok":"err",j.lines&&j.lines.length?j.lines:[j.msg]);
else show(r,"err",[why(s)]);
});
});
})(cb[c]);
var jcb=document.getElementById("jcb"),jcr=document.getElementById("jcr"),jb=document.getElementById("jrsb"),jr=document.getElementById("jrsr");
function busy(on){jcb.disabled=on;jb.disabled=on;}
function sum(res){
var ok=0,ms=0,ns=[],last="";
for(var i=0;i<res.length;i++){
if(res[i].ok){ok++;ms+=res[i].ms;ns.push(String(res[i].n));}
else{ns.push("—");last=String(res[i].msg);}
}
return {ok:ok,avail:ok?T.yes+(ok<res.length?" ("+ok+"/"+res.length+")":""):T.no+(last?": "+last:""),
time:ok?(ms/ok/1000).toFixed(1)+" "+T.sec:"—",count:ns.join(" / ")};
}
function own(){
var u=document.getElementById("jou"),k=document.getElementById("jok");
if(u.value.replace(/\s+/g,"")==="")return null;
var h=/^\s*https?:\/\/([^\/:?#\s]+)/i.exec(u.value);
return {name:h?h[1]:u.value,body:"b=&url="+encodeURIComponent(u.value)+"&key="+encodeURIComponent(k.value)};
}
function speed(list,each,done){
function run(n,q,res){
if(n>=list.length){done();return;}
req("POST",base+"&a=jr_speed&ajax=1",list[n].body+"&q="+q,function(s,j){
res.push(j&&typeof j.ok==="boolean"?j:{ok:false,msg:why(s),n:0,ms:0});
if(each(n,res)===false){done();return;}
if(q<2)setTimeout(function(){run(n,q+1,res);},2000);
else run(n+1,0,[]);
});
}
run(0,0,[]);
}
jcb.addEventListener("click",function(){
if(jcb.disabled)return;
var c=f.querySelector("input[name=jacred]:checked"),it=c&&c.value!=="own"?{name:c.value,body:"b="+encodeURIComponent(c.value)}:own();
if(!it){show(jcr,"err",[T.jo+": "+T.fmtj]);return;}
busy(true);
show(jcr,"hint",[T.checking]);
speed([it],function(n,res){
var last=res[res.length-1];
if(!last.ok&&last.msg===T.fmtj){show(jcr,"err",[T.jo+": "+T.fmtj]);return false;}
if(res.length<3){show(jcr,"hint",[T.checking+" "+res.length+"/3"]);return;}
var x=sum(res);
show(jcr,x.ok?"ok":"err",[it.name+" · "+x.avail+" · "+x.time+" · "+T.count+": "+x.count]);
},function(){busy(false);});
});
jb.addEventListener("click",function(){
if(jb.disabled)return;
var list=[],a=f.querySelectorAll("input.jrb"),i,c,o=own();
for(i=0;i<a.length;i++)list.push({name:a[i].getAttribute("data-id"),body:"b="+encodeURIComponent(a[i].getAttribute("data-id"))});
if(o)list.push(o);
busy(true);
jr.textContent="";
var t=document.createElement("table"),hr=t.insertRow(-1),hs=[T.name,T.avail,T.time,T.count],rows=[];
for(i=0;i<hs.length;i++){var th=document.createElement("th");th.textContent=hs[i];hr.appendChild(th);}
for(i=0;i<list.length;i++){
var r=t.insertRow(-1);
for(c=0;c<4;c++)r.insertCell(-1);
r.cells[0].textContent=list[i].name;
r.cells[1].textContent=T.checking;
rows.push(r);
}
jr.appendChild(t);
speed(list,function(n,res){
if(res.length<3){rows[n].cells[1].textContent=T.checking+" "+res.length+"/3";return;}
var x=sum(res);
rows[n].cells[1].textContent=x.avail;
rows[n].cells[2].textContent=x.time;
rows[n].cells[3].textContent=x.count;
},function(){busy(false);});
});
var sb=sv,sr=document.getElementById("sr");
function note(cls,text){
var n=document.createElement("p");
n.className=cls;
n.textContent=String(text);
sr.appendChild(n);
return n;
}
// The config of the chosen server after the save: its result replaces the "creating" line.
function sync(how){
sb.disabled=true;
sb.textContent=how==="create"?T.creating:T.updating;
var w=note("hint",sb.textContent);
req("POST",base+"&a=aio_sync&ajax=1","",function(s,k){
sb.disabled=false;
if(k&&typeof k.ok==="boolean"){
for(var i=0;i<as.options.length;i++){
var o=as.options[i];
if(o.value!==k.base)continue;
if(k.base==="own")o.setAttribute("data-url",ownUrl());
if(k.conf){o.setAttribute("data-cfg",k.conf.cfg);o.setAttribute("data-login",k.conf.login);o.setAttribute("data-pass",k.conf.pass);}
else{o.removeAttribute("data-cfg");o.removeAttribute("data-login");o.removeAttribute("data-pass");}
}
if(k.skip)sr.removeChild(w);
else{w.className=k.ok?"ok":"err";w.textContent=String(k.msg);}
}
else{w.className="err";w.textContent=why(s);}
aState();
},60000);
}
f.addEventListener("submit",function(e){
if(sb.disabled){e.preventDefault();return;}
var p=[],el=f.elements;
for(var i=0;i<el.length;i++){
if(!el[i].name||el[i].disabled||el[i].tagName==="BUTTON")continue;
if((el[i].type==="checkbox"||el[i].type==="radio")&&!el[i].checked)continue;
p.push(encodeURIComponent(el[i].name)+"="+encodeURIComponent(el[i].value));
}
e.preventDefault();
sb.disabled=true;
sb.textContent=T.saving;
document.getElementById("msg").textContent="";
sr.textContent="";
req("POST",base+"&ajax=1",p.join("&"),function(s,j){
sb.disabled=false;
aState();
if(j&&j.ok===true&&typeof j.next==="string"){
var nn=j.notes&&j.notes.length?j.notes:[];
if(!nn.length&&!j.aio){location.replace(j.next);return;}
show(sr,"ok",[T.saved]);
for(var k=0;k<nn.length;k++)note(nn[k].level==="ok"?"ok":"warn",nn[k].msg);
if(j.aio==="create"||j.aio==="update")sync(j.aio);
return;
}
var e=j&&j.errors&&j.errors.length?j.errors:[why(s)];
show(sr,"err",e);
for(var k=0;k<e.length;k++){
var m=new RegExp("^"+T.srv+" ([1-9]):").exec(String(e[k]));
if(m&&sg.ds[m[1]-1]){adv.open=true;sg.reveal(sg.ds[m[1]-1]);}
}
});
});
})();
JS;
}

// Values of the form from the settings (aio_settings_read) or as posted
// (aio_cgi_posted): manifest, rd, tb, tmdb, aio_server, ts, jacred (the
// chosen one) - strings; jo - array(url, key) of the own JacRed; servers -
// lists of strings by aio_cgi_server_fields(); confs - aio_confs of the
// settings (never posted).
function aio_cgi_values($s)
{
    $v = array('source' => $s['source'], 'manifest' => $s['manifest_url'], 'rd' => $s['rd_key'], 'tb' => $s['tb_key'], 'tmdb' => $s['tmdb_key'],
        'aio_server' => $s['aio_server'], 'aio_own' => $s['aio_own_url'], 'ts' => $s['ts_url'], 'servers' => $s['servers'],
        'jacred' => $s['jacred'], 'jo' => array('url' => $s['jacred_own_url'], 'key' => $s['jacred_own_key']),
        'confs' => $s['aio_confs']);
    return $v;
}

function aio_cgi_posted()
{
    $v = array('servers' => array(), 'jo' => array('url' => aio_cgi_param($_POST, 'jacred_own_url'),
        'key' => aio_cgi_param($_POST, 'jacred_own_key')), 'confs' => array());
    foreach (array('manifest' => 'manifest', 'rd' => 'rd_key', 'tb' => 'tb_key', 'tmdb' => 'tmdb_key',
        'aio_server' => 'aio_server', 'aio_own' => 'aio_own_url', 'ts' => 'ts', 'jacred' => 'jacred',
        'source' => 'source') as $k => $name)
        $v[$k] = trim(aio_cgi_param($_POST, $name), AIO_TRIM);
    // A form without the source: 0.35.1 chose the own config by aio_server
    // "manifest", 0.35.0 and older (no aio_own_url) by the manifest address
    // alone, as aio_settings_read.
    if ($v['source'] !== 'made' && $v['source'] !== 'own')
        $v['source'] = $v['aio_server'] === 'manifest' || ($v['manifest'] !== '' &&
            !array_key_exists('aio_own_url', $_POST)) ? 'own' : 'made';
    for ($n = 1; $n <= AIO_SERVERS_MAX; $n++)
    {
        $s = array();
        foreach (aio_cgi_server_fields() as $k)
            $s[$k] = aio_cgi_param($_POST, "s{$n}_$k");
        $v['servers'][] = $s;
    }
    return $v;
}

// Values of the form -> array(settings.json v 2 without saved_at, errors for
// the page, names of the bad fields for the log). Format only, no request.
function aio_cgi_check_values($v)
{
    $errors = array();
    $bad = array();
    // Only the fields of the chosen source are checked; a wrong one of the
    // other (hidden) is not written: the stored value stays ($v['cur']).
    $made = $v['source'] === 'made';
    $d = array('v' => 2, 'source' => $v['source'], 'manifest_url' => aio_manifest_url($v['manifest']));
    if ($v['manifest'] !== '' && $d['manifest_url'] === '')
    {
        if ($made)
            $d['manifest_url'] = $v['cur']['manifest_url'];
        else
        {
            $errors[] = aio_cgi_fmt_manifest();
            $bad[] = 'manifest';
        }
    }
    foreach (array('rd' => 'rd_key', 'tb' => 'tb_key') as $what => $k)
    {
        $d[$k] = aio_key_ok($what, $v[$what]);
        if ($v[$what] !== '' && $d[$k] === '')
        {
            if (!$made)
                $d[$k] = $v['cur'][$k];
            else
            {
                $errors[] = aio_cgi_fmt_key($what);
                $bad[] = $k;
            }
        }
    }
    // Not one of the list (a form of 0.33): the default; the own one only with its address.
    $d['jacred'] = $v['jacred'] === 'own' || array_key_exists($v['jacred'], aio_jacred_builtin()) ? $v['jacred'] :
        AIO_JACRED_DEFAULT;
    $own = aio_jacred_own($v['jo']);
    $d['jacred_own_url'] = $own ? $own['url'] : '';
    $d['jacred_own_key'] = $own ? $own['key'] : '';
    if (!$own && (trim($v['jo']['url'] . $v['jo']['key'], AIO_TRIM) !== '' || $d['jacred'] === 'own'))
    {
        $errors[] = aio_cgi_l('Свой JacRed', 'Own JacRed') . ': ' . aio_cgi_fmt_jacred();
        $bad[] = 'jacred';
    }
    // Not one of the choices (a form of 0.35.1 or older): the first server, as aio_settings_read.
    $as = array_keys(aio_aio_servers());
    $d['aio_server'] = in_array($v['aio_server'], array_merge($as, array('own')), true) ? $v['aio_server'] : $as[0];
    // The fields of both sources are kept; those of the chosen one must be there.
    if (!$made && $v['manifest'] === '')
    {
        $errors[] = aio_cgi_l('Укажите ссылку на конфиг — или выберите «Melange создаст конфиг»',
            'Give the config link, or choose "Melange makes the config"');
        $bad[] = 'manifest';
    }
    $d['aio_own_url'] = aio_aio_own($v['aio_own']);
    // Hidden unless another server is chosen to make the config on.
    if ($v['aio_own'] !== '' && $d['aio_own_url'] === '' && !($made && $d['aio_server'] === 'own'))
        $d['aio_own_url'] = $v['cur']['aio_own_url'];
    elseif ($made && $d['aio_server'] === 'own' && $d['aio_own_url'] === '')
    {
        $errors[] = aio_cgi_l('Другой сервер AIOStreams: нужен адрес вида http(s)://хост[:порт] без пути',
            'Another AIOStreams server: an address like http(s)://host[:port] without a path is needed');
        $bad[] = 'aio_own_url';
    }
    // No config there yet and no key to make it.
    $base = $d['aio_server'] === 'own' ? $d['aio_own_url'] : $d['aio_server'];
    if ($made && $base !== '' && !isset($v['confs'][$base]) && $d['rd_key'] === '' && $d['tb_key'] === '' &&
        $v['rd'] . $v['tb'] === '')
    {
        $errors[] = aio_cgi_l('Для конфига нужен ключ Real-Debrid или TorBox — или выберите «У меня свой конфиг»',
            'The config needs a Real-Debrid or TorBox key, or choose "I have my own AIOStreams config"');
        $bad[] = 'debrid';
    }
    $d['tmdb_key'] = aio_key_ok('tmdb', $v['tmdb']);
    // Hidden: the own config, or a server with TMDB of its own.
    if ($v['tmdb'] !== '' && $d['tmdb_key'] === '' && (!$made || aio_tmdb_sent($base, 'k') === ''))
        $d['tmdb_key'] = $v['cur']['tmdb_key'];
    elseif ($v['tmdb'] !== '' && $d['tmdb_key'] === '')
    {
        $errors[] = aio_cgi_fmt_key('tmdb');
        $bad[] = 'tmdb_key';
    }
    // An empty block is none.
    $d['servers'] = array();
    foreach ($v['servers'] as $i => $s)
    {
        if (trim($s['name'] . $s['url'] . $s['user'] . $s['category'] . $s['tags'], AIO_TRIM) . $s['pass'] === '')
            continue;
        $c = aio_server_conf($s);
        if ($c)
            $d['servers'][] = $c;
        else
        {
            $errors[] = aio_cgi_l('Сервер', 'Server') . ' ' . ($i + 1) . ': ' . aio_cgi_l('нужен адрес вида ' .
                'http://хост:порт; название — до 40 символов, логин, категория и теги — до 100, пароль — до 200, ' .
                'без управляющих символов', 'an address like http://host:port is needed; name up to 40 characters, ' .
                'login, category and tags up to 100, password up to 200, no control characters');
            $bad[] = 'server ' . ($i + 1);
        }
    }
    $d['ts_url'] = aio_ts_addr($v['ts']);
    if (!is_string($d['ts_url']))
    {
        $errors[] = aio_cgi_l('TorrServer: нужен адрес вида http://хост:порт или хост:порт (без https и пути)',
            'TorrServer: an address like http://host:port or host:port is needed (no https, no path)');
        $bad[] = 'ts';
    }
    return array($d, $errors, $bad);
}

// The main button while the chosen server has no config.
function aio_cgi_savec()
{
    return aio_cgi_l('Сохранить и создать конфиг', 'Save and create the config');
}

function aio_cgi_saved()
{
    return aio_cgi_l('Сохранено. Дюна подхватит настройки сама.', 'Saved. The Dune picks the settings up by itself.');
}

// $v: aio_cgi_values() or aio_cgi_posted().
function aio_cgi_form($t, $v, $errors, $saved)
{
    $msg = '';
    foreach ($errors as $e)
        $msg .= '<p class="err">' . aio_cgi_h($e) . '</p>';
    if ($saved)
        $msg .= '<p class="ok">' . aio_cgi_saved() . '</p>';
    $chk = '<button type="button" id="%s" class="chk">' . aio_cgi_l('Проверить', 'Check') . '</button><div id="%s"></div>';
    $title = aio_cgi_l('Melange — настройки', 'Melange - settings');
    $body = '<h1>' . $title . '</h1><div id="msg">' . $msg . '</div>' .
        '<form method="post" action="settings?t=' . aio_cgi_h($t) . '" autocomplete="off">' .
        aio_cgi_aio($v) .
        aio_cgi_jacred($v) .
        '<h2>' . aio_cgi_l('Смотреть через TorrServer', 'Watch via TorrServer') . '</h2>' .
        aio_cgi_plain('ts', 'ts', aio_cgi_l('Адрес TorrServer (необязательно)', 'TorrServer address (optional)'),
            $v['ts'], '127.0.0.1:8090', 300) .
        '<p class="hint">' . aio_cgi_l('Пусто — приложение TorrServe на Дюне; свой — http://хост:порт',
            'Empty - the TorrServe app on the Dune; your own - http://host:port') . '</p>' .
        '<p class="hint" id="tsown"' . ($v['source'] === 'own' ? '' : ' hidden') . '>' .
        aio_cgi_l('Раздачи P2P из вашего конфига играют через TorrServer',
        'P2P releases of your config play via TorrServer') . '</p>' .
        sprintf($chk, 'tsb', 'tsr') .
        '<details id="adv"><summary>' . aio_cgi_l('Дополнительно', 'Advanced') . '</summary>' .
        aio_cgi_servers($v['servers']) . '</details>' .
        '<div id="sr"></div><button type="submit" class="save">' . ($v['source'] !== 'own' && !aio_cgi_aio_has($v) &&
        $v['rd'] . $v['tb'] !== '' ? aio_cgi_savec() : aio_cgi_l('Сохранить', 'Save')) . '</button></form>' .
        '<p class="log"><a href="settings?t=' . aio_cgi_h($t) . '&amp;a=log">' . aio_cgi_l('Скачать лог', 'Download the log') .
        '</a></p><p class="hint">' . aio_cgi_l('С последнего включения Дюны; адреса и ключи скрыты.',
        'Since the Dune was last switched on; addresses and keys hidden.') . '</p>';
    return '<!DOCTYPE html><html lang="' . $GLOBALS['AIO_CGI_LANG'] . '"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<meta name="referrer" content="no-referrer"><title>' . $title . '</title><style>' .
        'body{font:16px/1.4 -apple-system,Roboto,sans-serif;margin:0;padding:16px;background:#111;color:#eee}' .
        'main{max-width:520px;margin:0 auto}h1{font-size:20px;margin:0 0 16px}' .
        'label{display:block;margin:18px 0 6px;color:#ccc}.f{display:flex;gap:8px}' .
        'input{box-sizing:border-box;flex:1;min-width:0;padding:12px;font-size:16px;border:1px solid #444;' .
        'border-radius:8px;background:#1c1c1c;color:#fff}' .
        'label.cb{margin:10px 0 0;color:#eee}label.cb input{width:20px;height:20px;margin:0 8px 0 0;vertical-align:middle}' .
        '.eye{width:48px;border:1px solid #444;border-radius:8px;background:#1c1c1c;color:#bbb}' .
        '.sec{-webkit-text-security:disc}.eye.on{color:#2d7ff9}.hint{margin:6px 0 0;font-size:13px;color:#999}' .
        '.hint a{color:#2d7ff9}' .
        '.save{margin-top:24px;width:100%;padding:14px;font-size:17px;border:0;border-radius:8px;' .
        'background:#2d7ff9;color:#fff}.err{background:#4a1515;padding:10px;border-radius:8px}' .
        '.ok{background:#153d1f;padding:10px;border-radius:8px}.warn{background:#4a3a10;padding:10px;border-radius:8px}' .
        '.log{margin:28px 0 0}.log a{color:#2d7ff9}' .
        'h2{font-size:17px;margin:28px 0 0}details{margin:12px 0 0;padding:4px 12px 12px;border:1px solid #333;' .
        'border-radius:8px}summary{padding:8px 0;color:#ccc}#adv>summary{font-size:17px;color:#eee}' .
        'select{width:100%;padding:12px;font-size:16px;' .
        'border:1px solid #444;border-radius:8px;background:#1c1c1c;color:#fff}' .
        'table{width:100%;margin:10px 0 0;border-collapse:collapse;font-size:14px}' .
        'th,td{padding:6px 4px;border-bottom:1px solid #333;text-align:left;vertical-align:top}' .
        '#tsr p,#sr p,#mr p,#rdr p,#tbr p,#tmr p,#jcr p,#apr p,.srvr p{margin:10px 0 0}p.cfg{margin:14px 0 0}' .
        'p.cfg a{color:#2d7ff9}' .
        '.chk{margin-top:10px;padding:10px 18px;font-size:16px;border:1px solid #444;border-radius:8px;' .
        'background:#1c1c1c;color:#eee}.srvdup{margin-left:8px}.chk:disabled{opacity:.5}' .
        '</style></head><body><main>' . $body . '</main><script>' . aio_cgi_js() .
        'document.querySelectorAll(".eye").forEach(function(b){b.addEventListener("click",function(){' .
        'var i=document.getElementById(b.getAttribute("data-for"));' .
        'b.classList.toggle("on",!i.classList.toggle("sec"));});});' .
        '</script></body></html>';
}

// post_max_size of php.ini in bytes ("8K").
function aio_cgi_post_max()
{
    $v = trim((string) ini_get('post_max_size'));
    $n = intval($v);
    $u = strtoupper(substr($v, -1));
    return $u === 'K' ? $n * 1024 : ($u === 'M' ? $n * 1048576 : ($u === 'G' ? $n * 1073741824 : $n));
}

// The save failed: JSON for the script of the page, else the form back.
function aio_cgi_failed($ajax, $code, $errors, $form)
{
    if ($ajax)
        aio_cgi_json($code, array('ok' => false, 'errors' => $errors));
    aio_cgi_send($code, $code === 200 ? 'text/html' : 'text/plain', $form);
}

// Format first (no request out); then each key given is checked with its
// service: a refused one -> nothing written; not checked or no premium ->
// written, said in "notes" of the JSON (lost without JS: 303 to the page).
function aio_cgi_save($dir, $t)
{
    // In the query: a body over post_max_size is dropped.
    $ajax = aio_cgi_param($_GET, 'ajax') === '1';
    // php-cgi drops a body over post_max_size: $_POST is empty.
    $len = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
    if (!$_POST && $len > aio_cgi_post_max())
    {
        aio_cgi_log('save: body over post_max_size');
        $e = array(aio_cgi_l('Слишком длинный ввод', 'The input is too long'));
        aio_cgi_failed($ajax, 200, $e, aio_cgi_form($t, aio_cgi_values(aio_settings_read('')), $e, false));
    }
    $v = aio_cgi_posted();
    // The configs are never in the form: kept as they are in the file.
    $cur = aio_settings_read(aio_cgi_dir_ok($dir) ? $dir : '');
    $v['confs'] = $cur['aio_confs'];
    $v['cur'] = $cur;
    list($data, $errors, $bad) = aio_cgi_check_values($v);
    if ($errors)
    {
        aio_cgi_log('save: bad format of ' . implode(', ', $bad));
        aio_cgi_failed($ajax, 200, $errors, aio_cgi_form($t, $v, $errors, false));
    }
    if (!aio_cgi_dir_ok($dir))
    {
        aio_cgi_log('save: no data dir');
        aio_cgi_failed($ajax, 500, array(aio_cgi_l('Не сохранено: нет папки плагина на Дюне',
            'Not saved: no folder of the plugin on the Dune')), "500 No data dir, nothing written\n");
    }
    $notes = array();
    foreach (array('rd' => 'rd_key', 'tb' => 'tb_key', 'tmdb' => 'tmdb_key') as $what => $k)
    {
        // A server with a TMDB key of its own: the field is hidden, the key kept unchecked.
        // Only the keys of the chosen source: the own config needs none.
        if ($data[$k] === '' || $data['source'] === 'own' || ($what === 'tmdb' &&
            aio_tmdb_sent(aio_settings_base($data), $data[$k]) === ''))
            continue;
        $r = aio_cgi_key_check($what, $data[$k]);
        if ($r[0] === 'err')
            $errors[] = $r[1];
        else
            $notes[] = array('level' => $r[0], 'msg' => $r[1]);
    }
    if ($errors)
    {
        aio_cgi_log('save: a key refused, nothing written');
        aio_cgi_failed($ajax, 200, $errors, aio_cgi_form($t, $v, $errors, false));
    }
    // Read again just before the write: a=aio_sync of another tab may have
    // written them while the keys were checked.
    $cur = aio_settings_read($dir);
    $data['aio_confs'] = $cur['aio_confs'];
    $why = aio_cgi_store($dir, $data);
    if ($why !== '')
    {
        aio_cgi_log("save: $why");
        aio_cgi_failed($ajax, 500, array(aio_cgi_l('Не сохранено: ошибка записи на Дюне',
            'Not saved: a write error on the Dune')), "500 Settings not saved\n");
    }
    aio_cgi_log('save: ok');
    // Only the checked token, as in the redirect below. aio: what a=aio_sync
    // is to do next ('' - nothing, the own address).
    if ($ajax)
        aio_cgi_json(200, array_merge(array('ok' => true, 'next' => 'settings?t=' . $t . '&saved=1',
            'aio' => $data['source'] === 'own' ? '' : (isset($data['aio_confs'][aio_settings_base($data)]) ?
            'update' : 'create')),
            $notes ? array('notes' => $notes) : array()));
    // Without the script: the config right here; its result is seen on the page (made or not).
    aio_cgi_aio_sync($dir);
    // Post/Redirect/Get. Relative to this page; only the checked token, no
    // value of the request (and not the Host header).
    header('HTTP/1.0 303 See Other');
    header('Location: settings?t=' . $t . '&saved=1');
    aio_cgi_send(303, 'text/plain', "303 See Other\n");
}

// --- The log download.

// stdout of php_server; with an empty FS_PREFIX (older models) /tmp/run.
function aio_cgi_log_path()
{
    return getenv('FS_PREFIX') . '/tmp/run/' . AIO_CGI_NAME . '.log';
}

// A header, then melange.log (its last AIO_CGI_LOG_MAX bytes), every line
// through the mask again: PHP notices reach the log past aio_log. Streamed.
function aio_cgi_log_download($dir)
{
    set_time_limit(300);
    date_default_timezone_set('UTC');
    $s = aio_settings_read($dir);
    $base = aio_manifest_base(aio_settings_manifest($s));
    // The own JacRed, chosen or not: its key and address never in the log.
    $jrs = aio_jacred_own_confs($s);
    $pw = aio_settings_secrets($s);
    $hosts = aio_settings_hosts($s);
    aio_cgi_headers(200, 'text/plain');
    header('Content-Disposition: attachment; filename="' . AIO_CGI_NAME . '-' . gmdate('Ymd-Hi') . '.log"');
    header('X-Content-Type-Options: nosniff');
    $host = $base !== '' ? (string) parse_url($base, PHP_URL_HOST) : '';
    $own = $s['aio_own_url'] !== '' ? (string) parse_url($s['aio_own_url'], PHP_URL_HOST) : '';
    echo 'Melange ' . AIO_VERSION . ', ' . gmdate('Y-m-d H:i:s') . " UTC\n" .
        // Another server never by its address (a LAN or NAS one), an own
        // config on it neither.
        'AIOStreams: ' . ($host === '' ? 'not set' : ($s['source'] === 'made' && $s['aio_server'] === 'own' ? 'own' :
        ($own !== '' && strtolower($host) === $own ? '<aio>' : $host))) .
        ($s['source'] === 'own' ? ', own config' : ', Melange config') . "\n" .
        // The chosen one: a built-in id, the own one never by its address.
        'JacRed: ' . ($s['jacred'] === 'own' ? '<jacred>' : $s['jacred']) . "\n" .
        'Servers: ' . count($s['servers']) . "\n" .
        // The own one never by its address.
        'TorrServer: ' . (isset($hosts[$s['ts_url']]) ? '<ts>' : AIO_TS_DEFAULT) . "\n";
    $f = aio_cgi_log_path();
    clearstatcache();
    // Not through a symlink: this runs as root.
    $fp = !is_link($f) && is_file($f) ? fopen($f, 'rb') : false;
    if (!$fp)
    {
        echo "\n" . aio_cgi_l('Лог пуст: после перезагрузки Дюны он начинается заново',
            'The log is empty: it starts anew after a restart of the Dune') . "\n";
        exit(0);
    }
    $size = filesize($f);
    $cut = '';
    if ($size > AIO_CGI_LOG_MAX)
    {
        fseek($fp, -AIO_CGI_LOG_MAX, SEEK_END);
        // The first line is cut: a secret cut in it would pass the mask.
        $first = fgets($fp);
        $cut = $first === false || substr($first, -1) !== "\n" ? "(log cut: no line break in the last 3 MB)\n" :
            "(log cut: last 3 MB, the first partial line skipped)\n";
    }
    echo "melange.log: $size bytes\n$cut\n";
    $n = 0;
    while (($line = fgets($fp)) !== false)
    {
        echo aio_mask_secrets($line, $base, $jrs, $pw, $s['aio_own_url'], $hosts);
        if (++$n % 500 === 0)
            flush();
    }
    fclose($fp);
    exit(0);
}

$dir = aio_cgi_data_dir();
$t = aio_cgi_param($_GET, 't');
if (!aio_cgi_token_ok($dir, $t))
    aio_cgi_forbidden();
$AIO_CGI_LANG = aio_cgi_lang();
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
// "Проверить" of the page, by its script: by POST, the passwords, the keys
// and the manifest address (secrets too) are not in the URL.
if ($method === 'POST' && aio_cgi_param($_GET, 'ajax') === '1')
{
    $a = aio_cgi_param($_GET, 'a');
    if ($a === 'srv_check')
        aio_cgi_srv_check_post();
    if ($a === 'aio_check')
        aio_cgi_aio_check_post();
    if ($a === 'jr_speed')
        aio_cgi_jr_speed_post();
    if ($a === 'key_check')
        aio_cgi_key_check_post();
    if ($a === 'aio_sync')
        aio_cgi_aio_sync_post($dir);
    if ($a === 'aio_status')
        aio_cgi_aio_status_post();
}
// Any other POST with an action is never a save: "Проверить" of a tab opened
// before 0.31.1 posts the whole form with a=ts_check. 303 to the page, nothing written.
if ($method === 'POST' && aio_cgi_param($_POST, 'a') . aio_cgi_param($_GET, 'a') !== '')
{
    aio_cgi_log('post with an action: to the page');
    header('HTTP/1.0 303 See Other');
    header('Location: settings?t=' . $t);
    aio_cgi_send(303, 'text/plain', "303 See Other\n");
}
if ($method === 'POST')
    aio_cgi_save($dir, $t);
if ($method === 'GET' && aio_cgi_param($_GET, 'a') === 'log')
    aio_cgi_log_download($dir);
// "Проверить": the TorrServer of the field (or the default), nothing written.
if ($method === 'GET' && aio_cgi_param($_GET, 'a') === 'ts_check')
{
    $ts = aio_ts_addr(aio_cgi_param($_GET, 'ts'));
    $res = is_string($ts) ? aio_cgi_ts_check(aio_ts_base($ts)) :
        array(false, aio_cgi_l('Нужен адрес вида http://хост:порт или хост:порт', 'An address like http://host:port or host:port is needed'));
    aio_cgi_json(200, array('ok' => $res[0], 'msg' => $res[1]));
}
if ($method === 'GET')
    aio_cgi_send(200, 'text/html', aio_cgi_form($t, aio_cgi_values(aio_settings_read($dir)), array(),
        aio_cgi_param($_GET, 'saved') === '1'));
aio_cgi_send(405, 'text/plain', "405 Method Not Allowed\n");
