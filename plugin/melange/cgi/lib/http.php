<?php
// The settings page (cgi/settings.php): the request and the reply - the
// parameters, the language, the log line, the headers and the body of a
// reply; curl - the one request of the page (aio_cgi_curl), redirects.

define('AIO_CGI_MAX_BODY', 262144);

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

// The one curl request of the page, no redirects followed: $o - method
// ('GET' by default; POST, PUT), body (of a POST or PUT), headers (the request
// headers), connect, total (timeouts, s; 3 and 8 by default), max (the body
// read at most, AIO_CGI_MAX_BODY by default), ua (User-Agent,
// Melange-Dune/<version> by default), gzip (any Content-Encoding) ->
// array(errno: curl errno, 0 also when cut, -1 its options refused; code:
// HTTP code; body; cut: over max (curl 23), the start is there; location:
// Location of the reply; cookies: its Set-Cookie as "a=b; c=d"; error: the
// text of curl).
function aio_cgi_curl($url, $o)
{
    $o += array('method' => 'GET', 'body' => null, 'headers' => array(), 'connect' => 3, 'total' => 8,
        'max' => AIO_CGI_MAX_BODY, 'ua' => 'Melange-Dune/' . AIO_VERSION, 'gzip' => false);
    $max = $o['max'];
    $body = '';
    $loc = '';
    $cookies = array();
    $opt = array(
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$body, $max)
        {
            // A different length aborts the transfer (curl 23).
            if (strlen($body) + strlen($data) > $max)
                return 0;
            $body .= $data;
            return strlen($data);
        },
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$loc, &$cookies)
        {
            if (preg_match('/^Location:\s*(.*?)\s*$/i', $line, $m))
                $loc = $m[1];
            if (preg_match('/^Set-Cookie:\s*([A-Za-z0-9_.\-]+=[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)/i', $line, $m))
                $cookies[] = $m[1];
            return strlen($line);
        },
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => $o['connect'],
        CURLOPT_TIMEOUT => $o['total'],
        // HTTPS works only with the firmware CA bundle.
        CURLOPT_CAINFO => AIO_CA_BUNDLE,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => $o['ua'],
        CURLOPT_HTTPHEADER => $o['headers']);
    if ($o['gzip'])
        $opt[CURLOPT_ENCODING] = '';
    if ($o['method'] === 'POST')
        $opt[CURLOPT_POST] = true;
    elseif ($o['method'] !== 'GET')
        $opt[CURLOPT_CUSTOMREQUEST] = $o['method'];
    if ($o['method'] !== 'GET')
        $opt[CURLOPT_POSTFIELDS] = $o['body'];
    $ch = curl_init($url);
    if (!curl_setopt_array($ch, $opt))
    {
        curl_close($ch);
        return array('errno' => -1, 'code' => 0, 'body' => '', 'cut' => false, 'location' => '', 'cookies' => '',
            'error' => 'curl options refused');
    }
    $ok = curl_exec($ch);
    $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);
    $cut = $ok === false && $errno === 23;
    return array('errno' => $ok === false && !$cut ? $errno : 0, 'code' => $code, 'body' => $body, 'cut' => $cut,
        'location' => $loc, 'cookies' => implode('; ', $cookies), 'error' => $error);
}

// One request of the checks of a server, a key, JacRed: POST of $fields
// (null: GET) with the cookies $cookie (or the request headers $headers
// instead), the body up to $max -> the reply of aio_cgi_curl. 3 s to connect,
// 8 s in all.
function aio_cgi_srv_req($url, $fields, $cookie, $headers = null, $max = AIO_CGI_MAX_BODY)
{
    // As aio_http_post of the plugin: no Referer or Origin, no Expect.
    return aio_cgi_curl($url, array(
        'method' => is_array($fields) ? 'POST' : 'GET',
        'body' => is_array($fields) ? http_build_query($fields, '', '&') : null,
        'headers' => is_array($headers) ? $headers : ($cookie !== '' ? array('Expect:', "Cookie: $cookie") : array('Expect:')),
        'connect' => 3,
        'total' => 8,
        'max' => $max,
        'ua' => 'Melange-Dune/' . AIO_VERSION));
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
