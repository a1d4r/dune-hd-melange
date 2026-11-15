<?php
// HTTP clients shared by the plugin (php_server) and the settings page
// (cgi/settings.php, php-cgi as root): TorrServer, the Transmission RPC and
// Lampac. Plain PHP 5.3: no firmware API, no logging here. Needs common.php
// (AIO_VERSION, AIO_CA_BUNDLE); tests define their own aio_ts_call(),
// aio_trm_call() and aio_lp_get().
// Types in the PHPDoc (AioServer): phpstan-types.neon of the repository.

// The Transmission RPC address of a stored server address: no path ->
// /transmission/rpc; .../transmission (its base) and the web UI path copied
// from a browser (.../transmission/web) -> .../transmission/rpc; any other
// path as typed (its own rpc-url, a reverse proxy).
/**
 * @param string $url
 * @return string
 */
function aio_trm_rpc($url)
{
    $path = (string) preg_replace('~^[a-z]+://[^/]*~', '', $url);
    if ($path === '')
        return "$url/transmission/rpc";
    if (substr($path, -13) === '/transmission')
        return "$url/rpc";
    if (substr($path, -17) === '/transmission/web')
        return substr($url, 0, -4) . '/rpc';
    return $url;
}

// Tests define their own aio_trm_call().
if (!function_exists('aio_trm_call'))
{
    // POST of the JSON $json to the Transmission RPC of server $srv
    // (aio_server_conf; aio_trm_rpc of its url), HTTP Basic when it has a
    // login -> array(code: HTTP code, body: its first 64 KB ('' on a
    // transport error), err: the transport error ('' none), errno: curl errno
    // (0 none, -1 its options refused), sid: whether that request went with a
    // session id). Its CSRF: the first request goes without
    // X-Transmission-Session-Id, a 409 handing one -> once more with it; a
    // second 409 (or one without an id) is returned as is. http and https, no
    // redirects, no "Expect: 100-continue". No log here: never the password,
    // the Authorization or the session id there. The plugin and the check of
    // the settings page.
    /**
     * @param AioServer $srv
     * @param string $json
     * @param int $connect
     * @param int $total
     * @return AioTrmReply
     */
    function aio_trm_call($srv, $json, $connect, $total)
    {
        $sid = '';
        while (true)
        {
            $body = '';
            $got = '';
            $h = array('Expect:', 'Content-Type: application/json');
            if ($sid !== '')
                $h[] = "X-Transmission-Session-Id: $sid";
            $opts = array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => $connect,
                CURLOPT_TIMEOUT => $total,
                CURLOPT_CAINFO => AIO_CA_BUNDLE,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION,
                CURLOPT_HTTPHEADER => $h,
                CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$got)
                {
                    if (preg_match('/^X-Transmission-Session-Id:[ \t]*([\x21-\x7e]{1,200})[ \t\r\n]*$/i', $line, $m))
                        $got = $m[1];
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body)
                {
                    if (strlen($body) < 65536)
                        $body .= substr($chunk, 0, 65536 - strlen($body));
                    return strlen($chunk);
                });
            if ($srv['user'] !== '')
            {
                $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
                $opts[CURLOPT_USERPWD] = "{$srv['user']}:{$srv['pass']}";
            }
            $ch = curl_init(aio_trm_rpc($srv['url']));
            if (!curl_setopt_array($ch, $opts))
            {
                curl_close($ch);
                return array('code' => 0, 'body' => '', 'err' => 'curl options refused', 'errno' => -1,
                    'sid' => $sid !== '');
            }
            $ok = curl_exec($ch);
            $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
            $errno = $ok === false ? curl_errno($ch) : 0;
            $err = $ok === false ? "curl $errno: " . curl_error($ch) : '';
            curl_close($ch);
            if ($err !== '' || $code !== 409 || $got === '' || $sid !== '')
                return array('code' => $code, 'body' => $err !== '' ? '' : $body, 'err' => $err, 'errno' => $errno,
                    'sid' => $sid !== '');
            $sid = $got;
        }
    }
}

// A bigger reply is not read (a pack of thousands of files is ~1 MB).
define('AIO_TS_MAX_BYTES', 4194304);

// Tests define their own aio_ts_call().
if (!function_exists('aio_ts_call'))
{
    // GET ($post null) or POST of JSON $post to TorrServer -> array(code:
    // HTTP code, body ('' on a transport error), err: the transport error (''
    // none), errno: curl errno, 0 none, -1 its options refused). http only,
    // no redirects; the errors of TorrServer have an empty body: the code
    // tells. $auth: "login:password" for Basic auth (0.45.0), '' none. The
    // plugin and the check of the settings page.
    /**
     * @param string $url
     * @param string|null $post
     * @param int $total
     * @param string $auth
     * @return AioTsReply
     */
    function aio_ts_call($url, $post, $total = 2, $auth = '')
    {
        $body = '';
        $over = false;
        $ch = curl_init($url);
        $opts = array(
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION,
            CURLOPT_HTTPHEADER => array('Expect:', 'Content-Type: application/json'),
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$over)
            {
                if (strlen($body) + strlen($chunk) > AIO_TS_MAX_BYTES)
                {
                    $over = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            });
        if (!is_null($post))
        {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $post;
        }
        if ($auth !== '')
        {
            $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $opts[CURLOPT_USERPWD] = $auth;
        }
        if (!curl_setopt_array($ch, $opts))
        {
            curl_close($ch);
            return array('code' => 0, 'body' => '', 'err' => 'curl options refused', 'errno' => -1);
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $errno = curl_errno($ch);
        $err = $over ? 'reply over ' . AIO_TS_MAX_BYTES . ' bytes' :
            ($ok === false ? "curl $errno: " . curl_error($ch) : '');
        curl_close($ch);
        return array('code' => $code, 'body' => $err !== '' ? '' : $body, 'err' => $err, 'errno' => $errno);
    }
}

// err of aio_lp_get for a reply over its $max bytes.
define('AIO_LP_TOO_BIG', 'reply over the size limit');

// Tests define their own aio_lp_get().
if (!function_exists('aio_lp_get'))
{
    // GET of an API of Lampac (lampac.php) -> array(code: HTTP code, body:
    // the body ('' on a transport error or over $max bytes), err: the
    // transport error ('' none; AIO_LP_TOO_BIG over $max), errno: curl errno
    // (0 none, -1 its options refused), hit: the reply came with
    // "X-Invoke-Cache: HIT" (Lampac keeps errors too: no point asking again)).
    // http and https, no redirects (an address of the page: its /version
    // without type sends to another site), gzip. No log here: the address
    // and the tokens of a link never go there. The plugin and the check of
    // the settings page.
    /**
     * @param string $url
     * @param int $connect
     * @param int $total
     * @param int $max
     * @return AioLpReply
     */
    function aio_lp_get($url, $connect, $total, $max)
    {
        $body = '';
        $over = false;
        $hit = false;
        $ch = curl_init($url);
        $opts = array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => AIO_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$hit)
            {
                // A status line starts a response (also after "100 Continue").
                if (preg_match('~^HTTP/\S+\s+[0-9]{3}~', $line))
                    $hit = false;
                else if (preg_match('/^X-Invoke-Cache:[ \t]*HIT[ \t\r\n]*$/i', $line))
                    $hit = true;
                return strlen($line);
            },
            // Not RETURNTRANSFER (curl_multi_getcontent of r25): a short count aborts the transfer.
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$over, $max)
            {
                if (strlen($body) + strlen($chunk) > $max)
                {
                    $over = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            });
        if (!curl_setopt_array($ch, $opts))
        {
            curl_close($ch);
            return array('code' => 0, 'body' => '', 'err' => 'curl options refused', 'errno' => -1, 'hit' => false);
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $errno = $over ? 0 : curl_errno($ch);
        $err = $over ? AIO_LP_TOO_BIG : ($ok === false ? "curl $errno: " . curl_error($ch) : '');
        curl_close($ch);
        return array('code' => $code, 'body' => $err !== '' ? '' : $body, 'err' => $err, 'errno' => $errno,
            'hit' => $hit);
    }
}
