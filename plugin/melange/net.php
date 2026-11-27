<?php
// HTTP clients shared by the plugin (php_server) and the settings page
// (cgi/settings.php, php-cgi as root): TorrServer, the Transmission RPC,
// the Web API of Synology Download Station, Lampac, the loop of curl_multi (mel_net_multi: mel_http_get_many of
// main.php, mel_lp_get_many) and the body of a GET (MelHttpSink). Plain PHP
// 5.3: no firmware API, no logging here.
// Needs common.php (MEL_VERSION, MEL_CA_BUNDLE); tests define their own
// mel_ts_call(), mel_trm_call(), mel_syn_call(), mel_lp_get() and mel_lp_get_many().
// Types in the PHPDoc (MelServer): phpstan-types.neon of the repository.

// The Transmission RPC address of a stored server address: no path ->
// /transmission/rpc; .../transmission (its base) and the web UI path copied
// from a browser (.../transmission/web) -> .../transmission/rpc; any other
// path as typed (its own rpc-url, a reverse proxy).
/**
 * @param string $url
 * @return string
 */
function mel_trm_rpc($url)
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

// Tests define their own mel_trm_call().
if (!function_exists('mel_trm_call'))
{
    // POST of the JSON $json to the Transmission RPC of server $srv
    // (mel_server_conf; mel_trm_rpc of its url), HTTP Basic when it has a
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
     * @param MelServer $srv
     * @param string $json
     * @param int $connect
     * @param int $total
     * @return MelTrmReply
     */
    function mel_trm_call($srv, $json, $connect, $total)
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
                CURLOPT_CAINFO => MEL_CA_BUNDLE,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Melange-Dune/' . MEL_VERSION,
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
            $ch = curl_init(mel_trm_rpc($srv['url']));
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

// A bigger reply of Synology is cut there (the list of its tasks: ~1-2 KB a task).
define('MEL_SYN_MAX_BYTES', 1048576);

// Tests define their own mel_syn_call().
if (!function_exists('mel_syn_call'))
{
    // GET ($post null) or POST of the form $post (name => value) to the Web
    // API of a Synology -> array(code: HTTP code, body: its first
    // MEL_SYN_MAX_BYTES ('' on a transport error), err: the transport error
    // ('' none), errno: curl errno (0 none, -1 its options refused)). The
    // form by rawurlencode: its v1 API reads "+" as a plus. http and https,
    // no redirects, no "Expect: 100-continue". No log here: the form carries
    // the password or the sid. The plugin and the settings page.
    /**
     * @param string $url
     * @param array<string, string>|null $post
     * @param int $connect
     * @param int $total
     * @return MelSynCall
     */
    function mel_syn_call($url, $post, $connect, $total)
    {
        $body = '';
        $opts = array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => MEL_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Melange-Dune/' . MEL_VERSION,
            CURLOPT_HTTPHEADER => array('Expect:'),
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body)
            {
                if (strlen($body) < MEL_SYN_MAX_BYTES)
                    $body .= substr($chunk, 0, MEL_SYN_MAX_BYTES - strlen($body));
                return strlen($chunk);
            });
        if (!is_null($post))
        {
            $f = array();
            foreach ($post as $k => $v)
                $f[] = rawurlencode($k) . '=' . rawurlencode($v);
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = implode('&', $f);
        }
        $ch = curl_init($url);
        if (!curl_setopt_array($ch, $opts))
        {
            curl_close($ch);
            return array('code' => 0, 'body' => '', 'err' => 'curl options refused', 'errno' => -1);
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $errno = $ok === false ? curl_errno($ch) : 0;
        $err = $ok === false ? "curl $errno: " . curl_error($ch) : '';
        curl_close($ch);
        return array('code' => $code, 'body' => $err !== '' ? '' : $body, 'err' => $err, 'errno' => $errno);
    }
}

// A request of mel_syn_call read as the Web API answers (HTTP 200, JSON
// {"success":true,"data":...} or {"success":false,"error":{"code":N}}) ->
// array(ok: success, data: its data (array() none), api_err: its error code
// (0 none), err: the transport error ('' none), errno: of curl (0 none), http:
// HTTP code, body). Neither ok, api_err nor err: not a reply of the Web API (a
// wrong address).
/**
 * @param string $url
 * @param array<string, string>|null $post
 * @param int $connect
 * @param int $total
 * @return MelSynReply
 */
function mel_syn_req($url, $post, $connect, $total)
{
    $r = mel_syn_call($url, $post, $connect, $total);
    $out = array('ok' => false, 'data' => array(), 'api_err' => 0, 'err' => $r['err'], 'errno' => $r['errno'], 'http' => $r['code'],
        'body' => $r['body']);
    $j = $r['err'] === '' && $r['code'] === 200 ? json_decode($r['body'], true) : null;
    if (!is_array($j) || !isset($j['success']) || !is_bool($j['success']))
        return $out;
    $out['ok'] = $j['success'];
    if ($j['success'] && isset($j['data']) && is_array($j['data']))
        $out['data'] = $j['data'];
    if (!$j['success'] && isset($j['error']) && is_array($j['error']) && isset($j['error']['code']) && is_int($j['error']['code']))
        $out['api_err'] = $j['error']['code'];
    return $out;
}

// The reply $r (mel_syn_req, mel_syn_api) in a word, for the log and the
// detail of an error: ok | the transport error (curl N: ...) | error N (of the
// API) | HTTP N (not a reply of the Web API) | no api (mel_syn_api sent
// nothing).
/**
 * @param MelSynReply $r
 * @return string
 */
function mel_syn_text($r)
{
    if ($r['err'] !== '')
        return $r['err'];
    if ($r['ok'])
        return 'ok';
    if ($r['api_err'])
        return "error {$r['api_err']}";
    return $r['http'] ? "HTTP {$r['http']}" : 'no api';
}

// The APIs of the Synology $srv (mel_server_conf; its url is the base,
// /webapi/... is added here) by SYNO.API.Info, no login -> array(the reply
// of mel_syn_req, name => array(path under /webapi/, max: its last version))
// of SYNO.API.Auth, SYNO.DownloadStation.Task (v1), SYNO.DownloadStation2.Task
// and SYNO.DownloadStation.Info: those it has.
/**
 * @param MelServer $srv
 * @param int $connect
 * @param int $total
 * @return array{0: MelSynReply, 1: MelSynApis}
 */
function mel_syn_query($srv, $connect, $total)
{
    $r = mel_syn_req($srv['url'] . '/webapi/query.cgi?api=SYNO.API.Info&version=1&method=query&query=' .
        'SYNO.API.Auth,SYNO.DownloadStation.Task,SYNO.DownloadStation2.Task,SYNO.DownloadStation.Info', null, $connect, $total);
    $apis = array();
    foreach ($r['data'] as $k => $a)
    {
        if (is_string($k) && is_array($a) && isset($a['path']) && is_string($a['path']) && isset($a['maxVersion']) &&
            is_int($a['maxVersion']) && preg_match('~^[A-Za-z0-9_.\-]+(?:/[A-Za-z0-9_.\-]+)*\z~', $a['path']) &&
            strpos($a['path'], '..') === false)
            $apis[$k] = array('path' => $a['path'], 'max' => $a['maxVersion']);
    }
    return array($r, $apis);
}

// Method $method of the API $api (version $version, the fields $args) of
// the Synology $srv at its path of $apis (mel_syn_query), with the sid of a
// login ('' none) -> the reply of mel_syn_req; an API not in $apis: no
// request, a reply with neither ok, api_err nor err, http 0 ("no api" of
// mel_syn_text).
/**
 * @param MelServer $srv
 * @param MelSynApis $apis
 * @param string $api
 * @param int $version
 * @param string $method
 * @param array<string, string> $args
 * @param string $sid
 * @param int $connect
 * @param int $total
 * @return MelSynReply
 */
function mel_syn_api($srv, $apis, $api, $version, $method, $args, $sid, $connect, $total)
{
    if (!isset($apis[$api]))
        return array('ok' => false, 'data' => array(), 'api_err' => 0, 'err' => '', 'errno' => 0, 'http' => 0, 'body' => '');
    $f = array('api' => $api, 'version' => strval($version), 'method' => $method) + $args;
    if ($sid !== '')
        $f['_sid'] = $sid;
    return mel_syn_req("{$srv['url']}/webapi/{$apis[$api]['path']}", $f, $connect, $total);
}

// The version of SYNO.API.Auth to log in and out with (as Sonarr).
/**
 * @param MelSynApis $apis
 * @return int
 */
function mel_syn_auth_version($apis)
{
    return isset($apis['SYNO.API.Auth']) && $apis['SYNO.API.Auth']['max'] >= 6 ? 6 : 2;
}

// Login of $srv to the session DownloadStation -> array(the reply of
// mel_syn_api, the sid ('' none)). Its error codes: mel_syn_login_why. No
// session is kept: mel_syn_logout after the work.
/**
 * @param MelServer $srv
 * @param MelSynApis $apis
 * @param int $connect
 * @param int $total
 * @return array{0: MelSynReply, 1: string}
 */
function mel_syn_login($srv, $apis, $connect, $total)
{
    $r = mel_syn_api($srv, $apis, 'SYNO.API.Auth', mel_syn_auth_version($apis), 'login', array('account' => $srv['user'],
        'passwd' => $srv['pass'], 'session' => 'DownloadStation', 'format' => 'sid'), '', $connect, $total);
    $sid = $r['ok'] && isset($r['data']['sid']) && is_string($r['data']['sid']) ? $r['data']['sid'] : '';
    return array($r, $sid);
}

// Why the login of the reply $r (mel_syn_login) was refused, by its error
// code: 'login' (400 a wrong login or password, 401 the account is off),
// 'app' (402 no right to Download Station), 'otp' (the 2-step verification:
// 403 of the user, 406 required for all), 'blocked' (407: DSM blocks the
// address after wrong passwords, the right one too), '' (none of these).
/**
 * @param MelSynReply $r
 * @return string
 */
function mel_syn_login_why($r)
{
    $why = array(400 => 'login', 401 => 'login', 402 => 'app', 403 => 'otp', 406 => 'otp', 407 => 'blocked');
    return isset($why[$r['api_err']]) ? $why[$r['api_err']] : '';
}

// The end of the session $sid of mel_syn_login.
/**
 * @param MelServer $srv
 * @param MelSynApis $apis
 * @param string $sid
 * @param int $connect
 * @param int $total
 * @return MelSynReply
 */
function mel_syn_logout($srv, $apis, $sid, $connect, $total)
{
    return mel_syn_api($srv, $apis, 'SYNO.API.Auth', mel_syn_auth_version($apis), 'logout', array('session' => 'DownloadStation'),
        $sid, $connect, $total);
}

// A bigger reply is not read (a pack of thousands of files is ~1 MB).
define('MEL_TS_MAX_BYTES', 4194304);
// err of mel_ts_call for a reply over MEL_TS_MAX_BYTES.
define('MEL_TS_TOO_BIG', 'reply over ' . MEL_TS_MAX_BYTES . ' bytes');

// Tests define their own mel_ts_call().
if (!function_exists('mel_ts_call'))
{
    // GET ($post null) or POST of JSON $post to TorrServer -> array(code:
    // HTTP code, body ('' on a transport error), err: the transport error (''
    // none), errno: curl errno, 0 none, -1 its options refused). http only,
    // no redirects; the errors of TorrServer have an empty body: the code
    // tells. $auth: "login:password" for Basic auth (0.45.0), '' none. The
    // plugin and the check of the settings page. $total (s): a fraction (the preload, MEL_TS_PRE_S) in ms
    // with no signals where curl has them, as mel_http_opts; else whole s, rounded up. $sink: each chunk of the body
    // goes to it as it comes, none kept (body ''), no MEL_TS_MAX_BYTES; it returns false - the read stops there, no
    // err (the list of the section, mel_tsl_load).
    /**
     * @param string $url
     * @param string|null $post
     * @param int|float $total
     * @param string $auth
     * @param callable|null $sink
     * @return MelTsReply
     */
    function mel_ts_call($url, $post, $total = 2, $auth = '', $sink = null)
    {
        $body = '';
        $over = false;
        $stop = false;
        $ch = curl_init($url);
        $ms = $total != floor($total) && defined('CURLOPT_TIMEOUT_MS') && defined('CURLOPT_CONNECTTIMEOUT_MS') &&
            defined('CURLOPT_NOSIGNAL');
        $time = $ms ? array(CURLOPT_NOSIGNAL => true, CURLOPT_CONNECTTIMEOUT_MS => max(1, intval(min(1, $total) * 1000)),
            CURLOPT_TIMEOUT_MS => max(1, intval($total * 1000))) :
            array(CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => intval(ceil($total)));
        $opts = $time + array(
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'Melange-Dune/' . MEL_VERSION,
            CURLOPT_HTTPHEADER => array('Expect:', 'Content-Type: application/json'),
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$over, &$stop, $sink)
            {
                if ($sink !== null)
                {
                    if (call_user_func($sink, $chunk) !== false)
                        return strlen($chunk);
                    $stop = true;
                    return 0;
                }
                if (strlen($body) + strlen($chunk) > MEL_TS_MAX_BYTES)
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
        $errno = $stop ? 0 : curl_errno($ch);
        $err = $over ? MEL_TS_TOO_BIG :
            ($ok === false && !$stop ? "curl $errno: " . curl_error($ch) : '');
        curl_close($ch);
        return array('code' => $code, 'body' => $err !== '' ? '' : $body, 'err' => $err, 'errno' => $errno);
    }
}

// TorrServe Silo at $base (the address of TorrServer)? A server written anew with another API (no /echo: 404 there,
// as of any program), not supported. Its open GET /api/system/ping answers 200 {"status":"ok"} (by the sources of
// Silo.3). One request by mel_ts_call, $total s as the /echo of the caller; asked only when /echo was answered, and
// not by a TorrServer MatriX: a working one and one that is down get no request more.
/**
 * @param string $base
 * @param int|float $total
 * @return bool
 */
function mel_ts_silo($base, $total)
{
    $r = mel_ts_call("$base/api/system/ping", null, $total);
    if ($r['err'] !== '' || $r['code'] !== 200)
        return false;
    $j = json_decode($r['body'], true);
    return is_array($j) && isset($j['status']) && $j['status'] === 'ok';
}

// Requests at once at most (mel_net_multi: mel_http_get_many of main.php, mel_lp_get_many): the catalog server allows
// 180 a minute, the dunemdb API as many; Lampac answers 5 balancers at once in the time of the slowest (a server without
// a cache: 0.2-0.7 s each).
define('MEL_HTTP_PAR', 5);

// curl_multi failed in this php_server (mel_net_multi): one by one since.
class MelHttp
{
    /** @var bool */
    public static $multi_off = false;
}

// The requests $reqs (key => array(URL, the options of curl (the body by WRITEFUNCTION), its total s)) at once by
// curl_multi -> key => array(errno: curl errno (0 none, -1 its options refused, 28 also no reply by the last guard),
// err: "curl <errno>: <text>" ('' none), code: HTTP code, connected: a connection was made, t: s it took), in the order
// of $reqs; null: no curl_multi (old firmware), or it failed now ($why: add, exec <rc>, no result; from then on none in
// this php_server, MelHttp::$multi_off; '' - none before) - the caller asks one by one and logs $why. The callers keep
// to MEL_HTTP_PAR. r25 (knowledge of its libphp5): no curl_multi_getcontent (it crashes PHP), the body by
// WRITEFUNCTION only, its callbacks get another handle as their first argument (never used); a new handle a request,
// none kept past the call (curl_close frees no handle that went through curl_multi there, the end of its last zval
// does).
/**
 * @param array<array-key, array{string, array<int, mixed>, float|int}> $reqs
 * @param string $why
 * @return array<array-key, array{errno: int, err: string, code: int, connected: bool, t: float}>|null
 */
function mel_net_multi($reqs, &$why)
{
    $why = '';
    if (MelHttp::$multi_off || !function_exists('curl_multi_init'))
        return null;
    $t0 = microtime(true);
    // curl_multi_init of PHP 5.3 has no failure of its own: a broken one fails at add or exec.
    $mh = curl_multi_init();
    $hs = array();
    $keys = array();
    $res = array();
    $wait = 0;
    foreach ($reqs as $k => $q)
    {
        $ch = curl_init($q[0]);
        // PHP 5.3 stops at a refused option: no WRITEFUNCTION, the body would go to stdout.
        if (!curl_setopt_array($ch, $q[1]))
        {
            curl_close($ch);
            $res[$k] = array('errno' => -1, 'err' => 'curl options refused', 'code' => 0, 'connected' => false, 't' => 0.0);
            continue;
        }
        if (curl_multi_add_handle($mh, $ch) !== 0)
        {
            curl_close($ch);
            foreach ($hs as $h)
            {
                curl_multi_remove_handle($mh, $h);
                curl_close($h);
            }
            curl_multi_close($mh);
            return mel_net_multi_off('add', $why);
        }
        $hs[$k] = $ch;
        $keys[(int) $ch] = $k;
        $wait = max($wait, $q[2]);
    }
    // A last guard past the timeouts of curl: the ones left then are timeouts of it.
    $end = $t0 + $wait + 2;
    $done = array();
    $bad = '';
    while (count($done) < count($hs))
    {
        do
            $rc = curl_multi_exec($mh, $active);
        while ($rc === CURLM_CALL_MULTI_PERFORM);
        if ($rc !== CURLM_OK)
        {
            $bad = "exec $rc";
            break;
        }
        // By $m['handle'] (right on r25), not by an argument of a callback.
        while (($m = curl_multi_info_read($mh)) !== false)
        {
            $id = (int) $m['handle'];
            if ($m['msg'] === CURLMSG_DONE && isset($keys[$id]) && !isset($done[$keys[$id]]))
                $done[$keys[$id]] = array(intval($m['result']), microtime(true) - $t0);
        }
        if (count($done) >= count($hs) || microtime(true) >= $end)
            break;
        if (!$active)
        {
            $bad = 'no result';
            break;
        }
        // Short: PHP 5.3.6 sleeps its whole timeout with no descriptor to wait for (the asynchronous resolver of a
        // host by its name).
        if (curl_multi_select($mh, 0.02) === -1)
            usleep(10000);
    }
    foreach ($bad === '' ? $hs : array() as $k => $h)
    {
        $no = isset($done[$k]) ? $done[$k][0] : 28;
        $res[$k] = array('errno' => $no, 'err' => !isset($done[$k]) ? 'curl 28: no reply in time' :
            ($no !== 0 ? "curl $no: " . curl_error($h) : ''), 'code' => intval(curl_getinfo($h, CURLINFO_HTTP_CODE)),
            // 0 until the TCP connection is made (PHP 5.3 has no CURLINFO_PRIMARY_IP).
            'connected' => curl_getinfo($h, CURLINFO_CONNECT_TIME) > 0,
            't' => isset($done[$k]) ? $done[$k][1] : microtime(true) - $t0);
    }
    foreach ($hs as $h)
    {
        curl_multi_remove_handle($mh, $h);
        curl_close($h);
    }
    curl_multi_close($mh);
    if ($bad !== '')
        return mel_net_multi_off($bad, $why);
    $out = array();
    foreach ($reqs as $k => $q)
        $out[$k] = $res[$k];
    return $out;
}

// curl_multi failed ($why: of the log): none from now on in this php_server. -> null.
/**
 * @param string $bad
 * @param string $why
 * @return null
 */
function mel_net_multi_off($bad, &$why)
{
    MelHttp::$multi_off = true;
    $why = $bad;
    return null;
}

// err of mel_lp_get for a reply over its $max bytes.
define('MEL_LP_TOO_BIG', 'reply over the size limit');

// The body of a GET (mel_http_get, mel_http_get_many of main.php; mel_lp_get, mel_lp_get_many): its WRITEFUNCTION,
// $max bytes at most (0: no limit); hit - the reply came with "X-Invoke-Cache: HIT", loc - its Location ('' none; its
// HEADERFUNCTION, of Lampac only: lampac.php follows its own redirects). Its own object a request: inside curl_multi the callback of r25 gets another handle as its first argument
// (multi.c of its libphp5 built for another php_curl), so the handle there is never used.
class MelHttpSink
{
    /** @var string */
    public $body = '';
    /** @var bool */
    public $over = false;
    /** @var bool */
    public $hit = false;
    /** @var string */
    public $loc = '';
    /** @var int */
    public $max = 0;

    /**
     * @param int $max
     */
    public function __construct($max)
    {
        $this->max = $max;
    }

    // Not RETURNTRANSFER (curl_multi_getcontent of r25): a reply over $max stops loading here, a short count aborts
    // the transfer.
    /**
     * @param mixed $ch
     * @param string $chunk
     * @return int
     */
    public function write($ch, $chunk)
    {
        if ($this->max > 0 && strlen($this->body) + strlen($chunk) > $this->max)
        {
            $this->over = true;
            return 0;
        }
        $this->body .= $chunk;
        return strlen($chunk);
    }

    /**
     * @param mixed $ch
     * @param string $line
     * @return int
     */
    public function head($ch, $line)
    {
        // A status line starts a response (also after "100 Continue").
        if (preg_match('~^HTTP/\S+\s+[0-9]{3}~', $line))
            list($this->hit, $this->loc) = array(false, '');
        else if (preg_match('/^X-Invoke-Cache:[ \t]*HIT[ \t\r\n]*$/i', $line))
            $this->hit = true;
        else if (preg_match('/^Location:[ \t]*(\S*)[ \t\r\n]*$/i', $line, $m))
            $this->loc = $m[1];
        return strlen($line);
    }
}

// The options of a GET of Lampac (mel_lp_get) with its body to $sink: http and https, no redirects (an address of the
// page: its /version without type sends to another site; those to Lampac itself mel_lp_ask of lampac.php follows), gzip. $ua: the User-Agent ('' that of Melange; mel_lp_send).
/**
 * @param int $connect
 * @param int $total
 * @param string $ua
 * @param MelHttpSink $sink
 * @return array<int, mixed>
 */
function mel_lp_opts($connect, $total, $ua, $sink)
{
    return array(
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => $connect,
        CURLOPT_TIMEOUT => $total,
        CURLOPT_CAINFO => MEL_CA_BUNDLE,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => $ua !== '' ? $ua : 'Melange-Dune/' . MEL_VERSION,
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_HEADERFUNCTION => array($sink, 'head'),
        CURLOPT_WRITEFUNCTION => array($sink, 'write'));
}

// The reply of a GET of Lampac done (HTTP $code, curl $errno, its error $fail: '' none) with its body in $sink ->
// MelLpReply (mel_lp_get).
/**
 * @param int $code
 * @param int $errno
 * @param string $fail
 * @param MelHttpSink $sink
 * @return MelLpReply
 */
function mel_lp_reply_of($code, $errno, $fail, $sink)
{
    $err = $sink->over ? MEL_LP_TOO_BIG : $fail;
    return array('code' => $code, 'body' => $err !== '' ? '' : $sink->body, 'err' => $err,
        'errno' => $sink->over ? 0 : $errno, 'hit' => $sink->hit, 'loc' => $sink->loc);
}

// Tests define their own mel_lp_get().
if (!function_exists('mel_lp_get'))
{
    // GET of an API of Lampac (lampac.php) -> array(code: HTTP code, body:
    // the body ('' on a transport error or over $max bytes), err: the
    // transport error ('' none; MEL_LP_TOO_BIG over $max), errno: curl errno
    // (0 none, -1 its options refused), hit: the reply came with
    // "X-Invoke-Cache: HIT" (Lampac keeps errors too: no point asking again), loc: its Location header ('' none)).
    // As mel_lp_opts. No log here: the address and the tokens of a link never
    // go there. The plugin and the check of the settings page. $ua: the
    // User-Agent ('' that of Melange; mel_lp_send).
    /**
     * @param string $url
     * @param int $connect
     * @param int $total
     * @param int $max
     * @param string $ua
     * @return MelLpReply
     */
    function mel_lp_get($url, $connect, $total, $max, $ua = '')
    {
        $sink = new MelHttpSink($max);
        $ch = curl_init($url);
        if (!curl_setopt_array($ch, mel_lp_opts($connect, $total, $ua, $sink)))
        {
            curl_close($ch);
            return array('code' => 0, 'body' => '', 'err' => 'curl options refused', 'errno' => -1, 'hit' => false,
                'loc' => '');
        }
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $r = mel_lp_reply_of(intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)), $errno,
            $ok === false ? "curl $errno: " . curl_error($ch) : '', $sink);
        curl_close($ch);
        return $r;
    }
}

// Tests define their own mel_lp_get_many().
if (!function_exists('mel_lp_get_many'))
{
    // The GETs $reqs (key => array(url, connect, total, max, ua) as of mel_lp_get) at once (mel_net_multi) -> key =>
    // array(the reply as of mel_lp_get, s it took), in the order of $reqs; null: no curl_multi ($why as mel_net_multi):
    // the caller asks one by one (mel_lp_get).
    /**
     * @param array<array-key, array{string, int, int, int, string}> $reqs
     * @param string $why
     * @return array<array-key, array{MelLpReply, float}>|null
     */
    function mel_lp_get_many($reqs, &$why)
    {
        $sinks = array();
        $q = array();
        foreach ($reqs as $k => $r)
        {
            $sinks[$k] = new MelHttpSink($r[3]);
            $q[$k] = array($r[0], mel_lp_opts($r[1], $r[2], $r[4], $sinks[$k]), $r[2]);
        }
        $res = mel_net_multi($q, $why);
        if ($res === null)
            return null;
        $out = array();
        foreach ($res as $k => $x)
            $out[$k] = array(mel_lp_reply_of($x['code'], $x['errno'], $x['err'], $sinks[$k]), $x['t']);
        return $out;
    }
}
