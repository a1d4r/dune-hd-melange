<?php
// The settings page (cgi/settings.php): the request and the reply - the
// parameters, the language, the log line, the headers and the body of a
// reply; curl - the body, headers, redirects and cookies of a request.

define('AIO_CGI_MAX_BODY', 262144);
define('AIO_CGI_CA', '/firmware/certs/ca-bundle.crt');

// The language of the page: 'ru' or 'en', by aio_cgi_lang() on the first
// text (null: not yet).
$AIO_CGI_LANG = null;

// The text in the language of the page.
function aio_cgi_l($ru, $en)
{
    if ($GLOBALS['AIO_CGI_LANG'] === null)
        $GLOBALS['AIO_CGI_LANG'] = aio_cgi_lang();
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

// post_max_size of php.ini in bytes ("8K").
function aio_cgi_post_max()
{
    $v = trim((string) ini_get('post_max_size'));
    $n = intval($v);
    $u = strtoupper(substr($v, -1));
    return $u === 'K' ? $n * 1024 : ($u === 'M' ? $n * 1048576 : ($u === 'G' ? $n * 1073741824 : $n));
}
