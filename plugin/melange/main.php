<?php
// "Melange" item of the "Play..." menu of a Dune movie card:
// play_action -> streams of the owner's AIOStreams -> list -> Dune player,
// linked to the card so that Dune's own watch history works.
// PHP 5.3.6, firmware PHP API only (/firmware_ext/php/).
// Settings: data_dir/settings.json of the settings page (view_setup.php).

// AIO_VERSION and the rules for the addresses: shared with the settings page.
require_once dirname(__FILE__) . '/common.php';
// Parsing of AIOStreams replies (no firmware API).
require_once dirname(__FILE__) . '/parse.php';
// Tracks of JacRed.
require_once dirname(__FILE__) . '/jacred.php';
// Dune's watch history.
require_once dirname(__FILE__) . '/history.php';
// Playback, the episodes around it and the lazy ones.
require_once dirname(__FILE__) . '/playback.php';
// "Download to server": qBittorrent WebAPI.
require_once dirname(__FILE__) . '/server.php';
// "Watch via TorrServer".
require_once dirname(__FILE__) . '/torrserver.php';
// Rows of a list at most: our own cap, not only the AIOStreams config.
define('AIO_MAX_ROWS', 100);
// A bigger AIOStreams reply (decoded) is cut off while it loads: the tree of
// json_decode takes ~9.5 MB a MB of it, a 6 MB reply peaks at ~78 MB of the
// 128 MB limit (64-bit PHP, play_action -> get_folder_view).
define('AIO_MAX_BYTES', 6291456);
// $err of aio_http_get for a reply over its $max bytes.
define('AIO_HTTP_TOO_BIG', 'reply over the size limit');
define('AIO_PLUGIN', 'melange');
// Supplier id kept from the plugin's old name aiostreams: Dune's watch history
// (recent_uid, sup_id of "Continue") refers to it.
define('AIO_SUP_ID', 'aio');
// show_dialog_delay of our handle_user_input actions, ms, as the play_action of
// Online movies.
define('AIO_DELAY_INPUT', 10000);

require_once dirname(__FILE__) . '/voices.php';
require_once dirname(__FILE__) . '/voices_parse.php';
require_once dirname(__FILE__) . '/view_gcomps.php';
require_once dirname(__FILE__) . '/view_info.php';
require_once dirname(__FILE__) . '/view_setup.php';

class Aio
{
    // The last stream list. Memory of php_server only: stream URLs carry
    // tokens and never go to a file. After a server restart the list expires.
    /** @var AioState|null */
    public static $state = null;
    // Settings of the operation (aio_settings_load): 'base' - base URL of the
    // manifest (the own config, else the config made on the chosen server), '' when none; 'jrs' - aio_jacred_list(): the chosen JacRed,
    // then jacred.stream (keys of aio_jacred_conf() + 'builtin'); 'jrm' -
    // aio_jacred_own_confs(): the own JacRed, chosen or not, for the mask of the log;
    // 'servers' - aio_server_conf() list of "Download to server", 'pw' - their
    // passwords and the keys of the services. Hidden in the log. 'ts' - the
    // TorrServer to use, http://host:port (aio_ts_base). 'aio' - the own
    // AIOStreams server ('' none), "<aio>" in the log. 'own' - the own config
    // is chosen (source own). 'hosts' - aio_settings_hosts(), hidden in the log.
    /** @var AioOpSettings */
    public static $settings = array('base' => '', 'jrs' => array(), 'jrm' => array(), 'servers' => array(),
        'pw' => array(), 'ts' => AIO_TS_DEFAULT, 'aio' => '', 'own' => false, 'hosts' => array());
    // Movies of the playlists with lazy episodes, by dune id: array('movie' =>
    // aio_movie, 'lang' => ...). Also in tmp_dir (aio_movie_save): a lazy
    // episode must work after a php_server restart. No stream URLs.
    public static $movies = array();
    // The list of an episode that is not in the release, until "Choose a
    // release" of its dialog.
    public static $next = null;
    // What plays: array('from' => rid of the list screen under the player
    // ('' if none), 'st' => list state of the episode playing now, 'sel' =>
    // row of its release). Lazy episodes move it on; back on that screen
    // aio_finish replaces it with the list of the episode played last.
    public static $playing = null;
    // The list state of a TorrServer waiting dialog when it is not the list
    // on screen (a lazy episode, aio_next): its timer finds it by rid.
    public static $tswait = null;
    // Start of the operation (microtime): set at the start of every
    // call_plugin and never reset (php_server keeps the last one). Its
    // readers - aio_precheck (time left to the stream check), aio_jacred
    // (cutoff), aio_relist_screen (log times) - run only inside call_plugin;
    // tests set it themselves. 0: aio_precheck takes its full timeout,
    // aio_jacred skips the request.
    public static $t0 = 0;
}

// --- Log: /tmp/run/melange.log (stdout of php_server).

// The secrets of the settings and of stream URLs hidden (common.php).
function aio_mask($s)
{
    return aio_mask_secrets($s, Aio::$settings['base'], Aio::$settings['jrm'], Aio::$settings['pw'],
        Aio::$settings['aio'], Aio::$settings['hosts']);
}

function aio_log($msg)
{
    // gmdate: date() of PHP 5.3.6 on Dune is an hour ahead.
    hd_print('melange: ' . gmdate('H:i:s') . ' ' . aio_mask($msg));
}

// $stop: also stops the player (a lazy episode; proven on the device).
// $log false: an action shown later, if ever (not logged now).
function aio_error($key, $detail = '', $stop = false, $log = true)
{
    if ($log)
        aio_log("error: $key" . ($detail !== '' ? " ($detail)" : ''));
    // All fields, as ActionFactory::show_error of Online movies: the shell
    // fails on a missing field it expects (see the list items).
    return array(
        GuiAction::handler_string_id => PLUGIN_SHOW_ERROR_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::data => array(
            PluginShowErrorActionData::fatal => false,
            PluginShowErrorActionData::title => "%tr%$key",
            PluginShowErrorActionData::msg_lines =>
                $detail !== '' ? aio_dialog_lines($detail) : null,
            PluginShowErrorActionData::stop_playback => $stop,
            PluginShowErrorActionData::retry_delay_ms => 0),
        GuiAction::params => null);
}

// --- Input of play_action.

// Every key of params, values masked; movie_str only as a summary.
function aio_log_input($in)
{
    foreach (get_object_vars($in) as $k => $v)
    {
        if ($k === 'movie_str')
        {
            $m = is_string($v) ? json_decode($v, true) : null;
            $v = '';
            foreach (array('id', 'imdb_id', 'series_type', 'title', 'native_title', 'year') as $f)
                $v .= "$f=" . aio_str($m, $f) . ' ';
        }
        else if (!is_string($v))
            $v = json_encode($v);
        // Mask before cutting: a cut secret would not match the mask.
        aio_log("in: $k = " . aio_cut(aio_mask($v), 1000));
    }
}

function aio_movie($in)
{
    $m = isset($in->movie_str) && is_string($in->movie_str) ?
        json_decode($in->movie_str, true) : null;
    if (!is_array($m))
        return null;
    // Top-level series_type is series|single; inside movie_str it is "1" or "".
    $series = isset($in->series_type) && is_string($in->series_type) ?
        $in->series_type === 'series' : aio_str($m, 'series_type') !== '';
    $imdb = aio_str($m, 'imdb_id');
    $dune_id = aio_str($m, 'id');
    $title = aio_str($m, 'title');
    return array(
        'series' => $series,
        'imdb' => preg_match('/^tt[0-9]{1,10}$/', $imdb) ? $imdb : '',
        'dune_id' => preg_match('/^[0-9a-f]{24}$/', $dune_id) ? $dune_id : '',
        'title' => $title !== '' ? $title : aio_str($m, 'native_title'),
        'native' => aio_str($m, 'native_title'),
        'year' => aio_str($m, 'year'),
        'poster' => aio_str($m, 'poster_url'),
        'fanart' => aio_str($m, 'fanart_url'),
        'rate_imdb' => aio_str($m, 'rate_imdb'),
        'seasons' => aio_seasons($m));
}

// season_numbers of movie_str ({"1": {"episodes_count": 10, ...}, ...}, the
// shell's movie_screen.php) -> array(season => episodes), ascending.
function aio_seasons($m)
{
    $res = array();
    foreach (aio_arr($m, 'season_numbers') as $n => $sn)
    {
        $c = is_array($sn) && isset($sn['episodes_count']) && is_numeric($sn['episodes_count']) ?
            intval($sn['episodes_count']) : 0;
        if (intval($n) >= 1 && $c >= 1)
            $res[intval($n)] = $c;
    }
    ksort($res);
    return $res;
}

// "Continue watching" -> shell_ext play_recent -> our play_action with
// supplier_info = {sup_data, wh_uid, wh_s, wh_e, wh_pos (seconds), wh_*}.
function aio_resume($in)
{
    $si = isset($in->supplier_info) && is_string($in->supplier_info) ?
        json_decode($in->supplier_info) : null;
    if (!is_object($si) || !isset($si->wh_release) || !is_string($si->wh_release) ||
        !preg_match('/^[0-9a-f]{40}$/', $si->wh_release))
        return null;
    return array(
        'hash' => $si->wh_release,
        's' => aio_int($si, 'wh_s', -1),
        'e' => aio_int($si, 'wh_e', -1),
        'pos' => max(0, aio_int($si, 'wh_pos', 0)));
}

// --- Settings.

// '' when the shell gave no data_dir_path (SDK: an STB without storage).
function aio_data_dir()
{
    return isset(DuneSystem::$properties['data_dir_path']) ? strval(DuneSystem::$properties['data_dir_path']) : '';
}

// Writes $data to $path atomically, 0600: a fresh file next to it, then rename.
function aio_write_file($path, $data)
{
    // A name, not a secret: fopen 'x' fails rather than reuse an existing file.
    $tmp = $path . '.' . substr(md5(uniqid(mt_rand(), true)), 0, 8) . '.tmp';
    $fp = is_dir(dirname($path)) ? fopen($tmp, 'x') : false;
    if (!$fp)
        return false;
    $ok = chmod($tmp, 0600) && fwrite($fp, $data) === strlen($data);
    $ok = fclose($fp) && $ok && rename($tmp, $path);
    if (!$ok && is_file($tmp))
        unlink($tmp);
    return $ok;
}

// data_dir/settings.json, read at the start of every operation, before its
// first log line (the values are masked there). Not cached: the page writes
// it while php_server runs.
function aio_settings_load()
{
    $s = aio_settings_read(aio_data_dir());
    Aio::$settings = array(
        // The stream URL is <base>/stream/...: the base keeps its trailing slash.
        'base' => aio_manifest_base(aio_settings_manifest($s)),
        'jrs' => aio_jacred_list($s),
        'jrm' => aio_jacred_own_confs($s),
        'servers' => $s['servers'],
        'pw' => aio_settings_secrets($s),
        'ts' => aio_ts_base($s['ts_url']),
        'aio' => $s['aio_own_url'],
        'own' => $s['source'] === 'own',
        'hosts' => aio_settings_hosts($s));
}

// --- AIOStreams.

// Tests define their own aio_http_get().
if (!function_exists('aio_http_get'))
{
    // -> array(HTTP code, body); $err is set on a transport error, to
    // AIO_HTTP_TOO_BIG (body '') when the decoded body passes $max bytes.
    function aio_http_get($url, &$err, $connect = 10, $total = 30, $max = 0)
    {
        $body = '';
        $over = false;
        $ch = curl_init($url);
        $set = curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            // http(s) only, redirects too (as the settings page).
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            // HTTPS works only with the firmware CA bundle.
            CURLOPT_CAINFO => '/firmware/certs/ca-bundle.crt',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            // A User-Agent starting with "AIOStreams" makes it add streamData.
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
            CURLOPT_HTTPHEADER => array('Accept: application/json'),
            // Not RETURNTRANSFER: a reply over $max stops loading here, a
            // short count aborts the transfer.
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$over, $max)
            {
                if ($max > 0 && strlen($body) + strlen($chunk) > $max)
                {
                    $over = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }));
        // PHP 5.3 stops at a refused option: no WRITEFUNCTION, the body would go to stdout.
        if (!$set)
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        // Over $max with an error status: that HTTP error, not "too big".
        if ($over)
            $err = $code === 200 ? AIO_HTTP_TOO_BIG : '';
        else
            $err = $ok === false ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '';
        curl_close($ch);
        return array($code, $err !== '' || $over ? '' : $body);
    }
}

// Tests define their own aio_http_post().
if (!function_exists('aio_http_post'))
{
    // POST of the form $fields with the cookies $cookie ('' for none) ->
    // array(HTTP code, body (its first 64 KB), cookies of the reply as
    // "name=value; ..."). $err is set on a transport error. No redirects, no
    // Referer or Origin (qBittorrent checks them against its host), no
    // "Expect: 100-continue" (a long magnet).
    function aio_http_post($url, $fields, $cookie, &$err, $connect = 3, $total = 15)
    {
        $body = '';
        $cookies = array();
        $ch = curl_init($url);
        $set = curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields, '', '&'),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => '/firmware/certs/ca-bundle.crt',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION,
            CURLOPT_HTTPHEADER => $cookie !== '' ? array('Expect:', "Cookie: $cookie") : array('Expect:'),
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$cookies)
            {
                if (preg_match('/^Set-Cookie:\s*([A-Za-z0-9_.\-]+=[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)/i', $line, $m))
                    $cookies[] = $m[1];
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body)
            {
                if (strlen($body) < 65536)
                    $body .= substr($chunk, 0, 65536 - strlen($body));
                return strlen($chunk);
            }));
        if (!$set)
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '', '');
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = $ok === false ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '';
        curl_close($ch);
        return array($code, $err !== '' ? '' : $body, implode('; ', $cookies));
    }
}

// Tests define their own aio_http_peek().
if (!function_exists('aio_http_peek'))
{
    // GET of a stream URL for its status and Location only -> array(HTTP
    // code, Location as sent or ''). No redirects; the transfer stops at the
    // end of the headers, the body (a video) is never read. $err is set on a
    // transport error.
    function aio_http_peek($url, &$err, $connect = 5, $total = 30)
    {
        $code = 0;
        $loc = '';
        $done = false;
        $ch = curl_init($url);
        $set = curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => '/firmware/certs/ca-bundle.crt',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // The User-Agent of aio_fetch: the add-ons see the same client.
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$code, &$loc, &$done)
            {
                // A status line starts a response (also after "100 Continue").
                if (preg_match('~^HTTP/\S+\s+([0-9]{3})~', $line, $m))
                {
                    $code = intval($m[1]);
                    $loc = '';
                }
                else if ($loc === '' && preg_match('/^Location:[ \t]*(.*)$/is', $line, $m))
                    $loc = rtrim($m[1], " \t\r\n");
                else if (rtrim($line, "\r\n") === '' && $code >= 200)
                {
                    // End of the final headers: a short count aborts the transfer.
                    $done = true;
                    return 0;
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk)
            {
                return 0;
            }));
        if (!$set)
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        $err = $ok === false && !$done ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '';
        curl_close($ch);
        return $err !== '' ? array(0, '') : array($code, $loc);
    }
}

// Error streams of the reply (streamData.type "error": a failed add-on, a bad
// config): the count and the text of the first 3, masked before the cut.
function aio_log_error_streams($streams)
{
    $n = 0;
    foreach ($streams as $st)
    {
        $sd = aio_arr($st, 'streamData');
        if (aio_str($sd, 'type') !== 'error')
            continue;
        if (++$n > 3)
            continue;
        $e = aio_arr($sd, 'error');
        $parts = array();
        foreach (array(aio_str($st, 'name'), aio_str($st, 'description'), aio_str($e, 'title'),
            aio_str($e, 'description')) as $p)
        {
            $p = aio_cut(aio_mask(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $p)), 200);
            if ($p !== '' && !in_array($p, $parts, true))
                $parts[] = $p;
        }
        aio_log("error stream $n: " . implode(' | ', $parts));
    }
    if ($n > 0)
        aio_log("error streams: $n");
}

// -> array('rows' => list) or array('error' => key, 'detail' => text).
function aio_fetch($base, $id, $series)
{
    $url = $base . 'stream/' . ($series ? 'series' : 'movie') . "/$id.json";
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get($url, $err, 10, 30, AIO_MAX_BYTES);
    aio_log(sprintf('request %s: %s, %d bytes, %.2f s', $url,
        $err === AIO_HTTP_TOO_BIG ? 'reply over ' . AIO_MAX_BYTES . ' bytes' : ($err !== '' ? $err : "HTTP $code"),
        strlen($body), microtime(true) - $t));
    if ($err === AIO_HTTP_TOO_BIG)
        return array('error' => 'err_too_big', 'detail' => '');
    if ($err !== '')
        return array('error' => 'err_network', 'detail' => $err);
    if ($code !== 200)
        return array('error' => 'err_http', 'detail' => "HTTP $code");
    $j = aio_decode_streams($body);
    unset($body);
    if (!is_array($j) || !isset($j['streams']) || !is_array($j['streams']))
        return array('error' => 'err_bad_reply', 'detail' => '');
    // The first AIO_MAX_ROWS playable streams: the rest is not looked at.
    $rows = array();
    foreach ($j['streams'] as $st)
    {
        $r = is_array($st) ? aio_row($st, $series) : null;
        if ($r)
            $rows[] = aio_row_voices($r);
        if (count($rows) === AIO_MAX_ROWS)
            break;
    }
    aio_log(sprintf('streams: %d, listed: %d, memory peak %.1f MB', count($j['streams']), count($rows),
        memory_get_peak_usage() / 1048576));
    aio_log_error_streams($j['streams']);
    return $rows ? array('rows' => $rows) : array('error' => 'err_no_streams', 'detail' => '');
}

// --- The stream list screen.

// "51.6 ГБ" in the shell language ($lang of play_action: ru or another).
function aio_size_str($b, $lang)
{
    if ($b >= 1073741824.0)
        return number_format($b / 1073741824.0, 1, '.', '') . ' ' . aio_tr($lang, 'size_gb');
    return number_format($b / 1048576.0, 0, '.', '') . ' ' . aio_tr($lang, 'size_mb');
}

function aio_folder_view($media_url, $sel_state)
{
    // "setup:<n>": the same screen redrawn by aio_setup_refresh.
    if ($media_url === 'setup' || strpos($media_url, 'setup:') === 0)
        return aio_setup_folder_view();
    if (strpos($media_url, 'info:') === 0)
        return aio_info_folder_view($media_url, $sel_state);
    $st = Aio::$state;
    if ($st && $media_url === 'streams:' . $st['rid'])
        return aio_gc_folder_view($st, $sel_state);
    aio_log('folder view: list outdated');
    return aio_lines_view(array(aio_expired_item()));
}

// A new id of a screen: a screen with an old one is stale.
function aio_rid()
{
    return substr(md5(uniqid('', true)), 0, 12);
}

// State of a stream list (Aio::$state): its screen "streams:<rid>", the card,
// season and episode (-1 for a movie), rows, the shell language of play_action.
// view_gcomps.php adds 'gc' - array(sel, top), the last cursor; 'gc_new' -
// the first view of a replaced screen ignores the shell's sel_state; 'gc_prog'
// - progress of the rows for their redraw. A screen replaced by a new list
// sets its cursor only by aio_gc_put_cursor, else 'gc_new' is lost. The one
// writer outside view_gcomps.php: aio_choose keeps 'gc' before it leaves.
function aio_list_state($mv, $s, $e, $rows, $lang)
{
    return array('rid' => aio_rid(), 'movie' => $mv, 's' => $s, 'e' => $e, 'rows' => $rows, 'lang' => $lang);
}

// --- play_action of the supplier.

function aio_play_action($in)
{
    Aio::$playing = null;
    aio_log_input($in);

    $mv = aio_movie($in);
    if (!$mv || $mv['imdb'] === '')
        return aio_error('err_no_imdb');

    $resume = aio_resume($in);
    $s = -1;
    $e = -1;
    if ($mv['series'])
    {
        // Back from "Continue watching" the shell sends season = episode = -1.
        if ($resume && $resume['s'] >= 1 && $resume['e'] >= 1)
        {
            $s = $resume['s'];
            $e = $resume['e'];
        }
        else
        {
            $s = aio_int($in, 'season', -1);
            $e = aio_int($in, 'episode', -1);
        }
        // From the main card: season = episode = -1 and no supplier_info.
        if ($s < 1 || $e < 1)
            list($s, $e) = aio_wh_episode($mv);
    }

    if (Aio::$settings['base'] === '')
        return aio_no_address(isset($in->lang) && is_string($in->lang) ? $in->lang : '');

    $res = aio_fetch(Aio::$settings['base'], $mv['imdb'] . ($mv['series'] ? ":$s:$e" : ''), $mv['series']);
    if (isset($res['error']))
        return aio_error($res['error'], $res['detail']);

    $st = aio_list_state($mv, $s, $e, $res['rows'], isset($in->lang) && is_string($in->lang) ? $in->lang : '');
    Aio::$state = $st;

    if ($resume)
    {
        // Through Debrid: the row of the release with a URL, a cached one
        // first (aio_play checks it). One not cached only if the check finds
        // a stream: else maybe a placeholder, as before. Any episode in the
        // file: the reply is of the episode watched.
        $pick = aio_release_pick($st['rows'], $resume['hash'], -1, -1);
        $deb = $pick['deb'] >= 0 ? $st['rows'][$pick['deb']] : null;
        $pre = null;
        if ($deb && $deb['cached'] !== true)
        {
            $pre = aio_deb_precheck($deb);
            if ($pre['class'] !== 'stream')
            {
                aio_log("resume: release {$resume['hash']} not cached, no stream from Debrid");
                $deb = null;
            }
        }
        if ($deb)
            return aio_play($st, $deb, $resume['pos'], null, false, $pre);
        // Not cached (watched via TorrServer, or gone from the cache) or P2P:
        // through TorrServer, only if it answers; else the list. A P2P row
        // first, else any of the release.
        $ts = $pick['p2p'] >= 0 ? $pick['p2p'] : $pick['any'];
        if ($ts >= 0 && aio_ts_alive())
        {
            aio_log("resume: release {$resume['hash']} not cached -> TorrServer");
            return aio_ts_open($st, $ts, $resume['pos'], '', false, true);
        }
        aio_log("resume: release {$resume['hash']} " . ($ts < 0 ? 'not found' : 'not cached, TorrServer does not answer') .
            ', showing the list');
    }
    return aio_open_list($st);
}

// Opens the list of $st.
function aio_open_list($st)
{
    Aio::$state = $st;
    if (AIO_JACRED)
    {
        $st['rows'] = aio_jacred($st['rows'], $st['movie'], $st['s']);
        Aio::$state = $st;
    }

    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "streams:{$st['rid']}",
            PluginOpenFolderActionData::caption => aio_ep_name($st['movie'], $st['s'], $st['e'])));
}

// --- Texts, actions and dialogs.

// Texts of translations/ for our own dialogs (%tr% there is not proven):
// ru or English.
function aio_tr($lang, $key, $ep = '')
{
    static $t = array();
    $file = $lang === 'ru' ? 'russian' : 'english';
    if (!isset($t[$file]))
    {
        $t[$file] = array();
        $path = dirname(__FILE__) . "/translations/dune_language_$file.txt";
        foreach (is_file($path) ? file($path) : array() as $line)
        {
            if (preg_match('/^([a-z_]+) = (.*)$/', rtrim($line, "\r\n"), $m))
                $t[$file][$m[1]] = $m[2];
        }
    }
    $s = isset($t[$file][$key]) ? $t[$file][$key] : $key;
    return $ep !== '' ? str_replace('{ep}', $ep, $s) : trim(str_replace(array(' {ep}', '{ep}'), '', $s));
}

// handle_user_input of our plugin, 60 s; "Please wait" after AIO_DELAY_INPUT ms.
function aio_input($control, $params = array())
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginHandleUserInputActionData::operation_timeout => 60000,
            PluginHandleUserInputActionData::show_dialog_delay => AIO_DELAY_INPUT),
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => $control), $params));
}

function aio_close_and($post)
{
    return array(
        GuiAction::handler_string_id => CLOSE_DIALOG_AND_RUN_ACTION_ID,
        GuiAction::data => array(CloseDialogAndRunActionData::post_action => $post));
}

// Dialog over the stopped player or a screen: text lines and buttons
// (caption => action, named btn0, btn1, ...).
function aio_dialog($title, $lines, $buttons)
{
    $defs = array();
    foreach ($lines as $l)
        $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l));
    $n = 0;
    foreach ($buttons as $cap => $act)
        $defs[] = aio_ctl('btn' . $n++, GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => $cap,
            GuiButtonDef::width => 500, GuiButtonDef::push_action => $act));
    return aio_dialog_act($title, $defs);
}

// show_dialog of controls $defs; $more: fields of the dialog over the usual
// ones (width, timer).
function aio_dialog_act($title, $defs, $more = array())
{
    return array(
        GuiAction::handler_string_id => SHOW_DIALOG_ACTION_ID,
        GuiAction::data => array_merge(array(
            ShowDialogActionData::title => $title,
            ShowDialogActionData::defs => $defs,
            ShowDialogActionData::close_by_return => true,
            ShowDialogActionData::preferred_width => 1100), $more));
}

function aio_ctl($name, $kind, $def, $title = null)
{
    return array(GuiControlDef::name => $name, GuiControlDef::title => $title,
        GuiControlDef::kind => $kind, GuiControlDef::specific_def => $def,
        GuiControlDef::params => null);
}

// --- Dispatcher.

function aio_handle_user_input($in)
{
    $handler = isset($in->handler_id) ? $in->handler_id : null;
    $control = isset($in->control_id) ? $in->control_id : null;
    if ($handler !== 'aio')
        return null;
    if ($control === 'play')
        return aio_play_action($in);
    if ($control === 'gc_move' || $control === 'gc_pick' || $control === 'gc_info')
        return aio_gc_input($in);
    if ($control === 'info_page')
        return aio_info_input($in);
    if ($control === 'next')
        return aio_next($in);
    if ($control === 'next_pick')
        return aio_next_pick($in);
    if ($control === 'finish')
        return aio_finish($in);
    if ($control === 'gc_flip')
        return aio_flip($in);
    if ($control === 'gc_refresh')
        return aio_refresh($in);
    if ($control === 'gc_choose')
        return aio_choose($in);
    if ($control === 'gc_chosen')
        return aio_chosen($in);
    if ($control === 'gc_menu')
        return aio_gc_menu($in);
    if ($control === 'gc_dl')
        return aio_download($in);
    if ($control === 'gc_srv')
        return aio_srv_menu($in);
    if ($control === 'gc_push')
        return aio_srv_push($in);
    if ($control === 'gc_ts')
        return aio_ts_menu($in);
    if ($control === 'ts_tick')
        return aio_ts_tick($in);
    if ($control === 'ts_cancel')
    {
        aio_log('ts: waiting cancelled');
        return aio_close_and(null);
    }
    if (is_string($control) && strpos($control, 'setup') === 0)
        return aio_setup_input($control);
    if ($control === 'next_stop')
        return aio_close_and(array(
            GuiAction::handler_string_id => STOP_PLAYBACK_ACTION_ID,
            GuiAction::data => array(
                StopPlaybackActionData::wait_for_completion => true,
                StopPlaybackActionData::post_action => null)));
    return null;
}

class AioFw extends DunePluginFw
{
    public function call_plugin($call_ctx_json)
    {
        $ctx = json_decode($call_ctx_json);
        $type = null;
        $data = null;
        $error_action = null;
        $in = is_object($ctx) && isset($ctx->input_data) && is_object($ctx->input_data) ?
            $ctx->input_data : null;
        if ($in && isset($ctx->op_type_code))
        {
            Aio::$t0 = microtime(true);
            aio_settings_load();
            $op = $ctx->op_type_code;
            if ($op === PLUGIN_OP_GET_FOLDER_VIEW)
            {
                $type = PLUGIN_OUT_DATA_PLUGIN_FOLDER_VIEW;
                $data = aio_folder_view(isset($in->media_url) && is_string($in->media_url) ?
                    $in->media_url : '', isset($in->sel_state) ? $in->sel_state : null);
            }
            else if ($op === PLUGIN_OP_HANDLE_USER_INPUT)
            {
                $type = PLUGIN_OUT_DATA_GUI_ACTION;
                $data = aio_handle_user_input($in);
            }
            // Lazy episodes: media_url comes to get_vod_stream_url, the whole
            // input (playback_url) to get_vod_stream_info.
            else if ($op === PLUGIN_OP_GET_VOD_STREAM_URL || $op === PLUGIN_OP_GET_VOD_STREAM_INFO)
            {
                $a = $op === PLUGIN_OP_GET_VOD_STREAM_URL ? 'media_url' : 'playback_url';
                $b = $op === PLUGIN_OP_GET_VOD_STREAM_URL ? 'playback_url' : 'media_url';
                $error_action = aio_lazy_action(isset($in->$a) ? $in->$a : (isset($in->$b) ? $in->$b : null));
            }
        }

        $out = array(
            PluginOutputData::has_data => !is_null($data),
            PluginOutputData::plugin_cookies =>
                isset($ctx->plugin_cookies) ? $ctx->plugin_cookies : null,
            PluginOutputData::is_error => !is_null($error_action),
            PluginOutputData::error_action => $error_action);
        if (!is_null($data))
        {
            $out[PluginOutputData::data_type] = $type;
            $out[PluginOutputData::data] = $data;
        }
        return json_encode($out);
    }
}

DunePluginFw::$instance = new AioFw();
