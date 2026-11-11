<?php
// "Melange" item of the "Play..." menu of a Dune movie card:
// play_action -> streams of the owner's AIOStreams -> list -> Dune player,
// linked to the card so that Dune's own watch history works.
// PHP 5.3.6, firmware PHP API only (/firmware_ext/php/).
// Settings: data_dir/settings.json of the settings page (view_setup.php).

// AIO_VERSION and the rules for the addresses: shared with the settings page.
require_once dirname(__FILE__) . '/common.php';
// HTTP clients of TorrServer and Transmission: shared with the settings page.
require_once dirname(__FILE__) . '/net.php';
// Parsing of AIOStreams replies (no firmware API).
require_once dirname(__FILE__) . '/parse.php';
// Tracks of JacRed.
require_once dirname(__FILE__) . '/jacred.php';
// Dune's watch history.
require_once dirname(__FILE__) . '/history.php';
// Playback, the episodes around it and the lazy ones.
require_once dirname(__FILE__) . '/playback.php';
// Markers of an episode: "Skip intro", "Next episode".
require_once dirname(__FILE__) . '/segments.php';
// "Download to server": qBittorrent WebAPI.
require_once dirname(__FILE__) . '/server.php';
// "Watch via TorrServer".
require_once dirname(__FILE__) . '/torrserver.php';
// TorrServer in the player: the window of its numbers with INFO.
require_once dirname(__FILE__) . '/ts_info.php';
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
// Id of a Dune card in a regex: of the server (24 hex) or a local one of the shell
// (custom_mdb/user_movie, "m-..."). No ':' or '/': it goes into recent_uid,
// aio_next: and a file name.
define('AIO_DUNE_ID', '(?:[0-9a-f]{24}|m-[A-Za-z0-9_-]{1,64})');
// show_dialog_delay of our handle_user_input actions, ms, as the play_action of
// Online movies.
define('AIO_DELAY_INPUT', 10000);

require_once dirname(__FILE__) . '/voices.php';
require_once dirname(__FILE__) . '/voices_parse.php';
require_once dirname(__FILE__) . '/view_gcomps.php';
require_once dirname(__FILE__) . '/view_info.php';
require_once dirname(__FILE__) . '/view_setup.php';
// The catalog: client, ENTER on a tile, its screens.
require_once dirname(__FILE__) . '/catalog.php';
require_once dirname(__FILE__) . '/catalog_card.php';
require_once dirname(__FILE__) . '/view_catalog.php';
require_once dirname(__FILE__) . '/view_catalog_rows.php';

class Aio
{
    // The last stream list. Memory of php_server only: stream URLs carry
    // tokens and never go to a file. After a server restart the list expires.
    /** @var AioState|null */
    public static $state = null;
    // Settings of the operation (aio_settings_load): 'base' - base URL of the
    // manifest (the own config, else the config made on the chosen server), '' when none; 'jrs' - aio_jacred_list(): the chosen JacRed,
    // then AIO_JACRED_DEFAULT (keys of aio_jacred_conf() + 'builtin'); 'jrm' -
    // aio_jacred_own_confs(): the own JacRed, chosen or not, for the mask of the log;
    // 'servers' - aio_server_conf() list of "Download to server", 'pw' - their
    // passwords and the keys of the services. Hidden in the log. 'ts' - the
    // TorrServer to use, http://host:port (aio_ts_base); 'ts_auth' - its
    // "login:password" for Basic auth ('' none; the password hidden in the log);
    // 'ts_save' - save_to_db of its add (ts_save of the settings). 'markers' -
    // the same of the settings: aio_markers, aio_play. 'source_errors' - the same:
    // aio_errors_shown. 'no_torrents' -
    // aio_no_torrents(): no P2P rows, nothing sent to TorrServer, an app or a server. 'aio' - the own
    // AIOStreams server ('' none), "<aio>" in the log. 'own' - the own config
    // is chosen (source own). 'hosts' - aio_settings_hosts(), hidden in the log.
    // 'sort', 'sort_rev' - the sort of a new list and its direction (aio_sort_read).
    // 'cat_rows' - the rows of the catalog chosen on the page, the argument of aio_cat_user_rows.
    // 'cat_logo' - the same of the settings, as stored. 'cat_rating' - the same: aio_catv_title_item.
    // 'debrid_main' - the main Debrid service (aio_debrid_main_read): of a file cached on
    // several, the copy of it plays (aio_list_state, aio_play); '' - the first in the reply.
    /** @var AioOpSettings */
    public static $settings = array('base' => '', 'jrs' => array(), 'jrm' => array(), 'servers' => array(),
        'pw' => array(), 'ts' => AIO_TS_DEFAULT, 'ts_auth' => '', 'ts_save' => false, 'markers' => true, 'source_errors' => false, 'no_torrents' => false, 'aio' => '', 'own' => false,
        'hosts' => array(), 'sort' => 'aio', 'sort_rev' => false, 'cat_rows' => null, 'cat_logo' => true,
        'cat_rating' => false, 'debrid_main' => '');
    // Movies of the playlists with lazy episodes, by dune id: array('movie' =>
    // aio_movie, 'lang' => ...). Also in tmp_dir (aio_movie_save): a lazy
    // episode must work after a php_server restart. No stream URLs.
    /** @var array<string, AioLazyMovie> */
    public static $movies = array();
    // The list of an episode that is not in the release, until "Choose a
    // release" of its dialog.
    /** @var AioState|null */
    public static $next = null;
    // What plays: array('from' => rid of the list screen under the player
    // ('' if none), 'st' => list state of the episode playing now, 'sel' =>
    // row of its release). Lazy episodes move it on; back on that screen
    // aio_finish replaces it with the list of the episode played last.
    /** @var AioPlaying|null */
    public static $playing = null;
    // The dialog "<service> could not play the release" with "Via <the other
    // one>" (aio_pre_failed), until its button (aio_pre_via) or another start.
    /** @var AioAlt|null */
    public static $alt = null;
    // The service chosen by "Via <service>": the start and the next episodes of
    // this release (lazy, the playlist, the autoplay) take its copy
    // (aio_main_svc); a new start from a list or play_action - the main one again.
    /** @var AioVia|null */
    public static $via = null;
    // The list state of a TorrServer waiting dialog when it is not the list
    // on screen (a lazy episode, aio_next): its timer finds it by rid.
    /** @var AioState|null */
    public static $tswait = null;
    // The row and the map "episode -> file" of the last start through TorrServer:
    // the episodes of the map go without AIOStreams (aio_ts_pack_state). Memory of
    // php_server only: the magnet of a private tracker carries a passkey.
    /** @var AioTsPack|null */
    public static $ts_pack = null;
    // Start of the operation (microtime): set at the start of every
    // call_plugin and never reset (php_server keeps the last one). Its
    // readers - aio_precheck (time left to the stream check), aio_jacred
    // (cutoff), aio_relist_screen (log times) - run only inside call_plugin;
    // tests set it themselves. 0: aio_precheck takes its full timeout,
    // aio_jacred skips the request.
    public static $t0 = 0;
    // Why settings.json was not read and the file then (aio_settings_read_logged):
    // a reason is logged once, not on every read.
    public static $settings_why_key = '';
    // 'ino:uid' of web_token after aio_token_fix tried it: never again for that file.
    public static $token_tried = '';
    // The root of the server that answered 404 on /api/v1/segments (aio_markers):
    // no more reads or requests for it. Memory of php_server only: after an
    // update of the server the route is found without the user.
    public static $seg_no_route = '';
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

/**
 * @return AioMovie|null
 */
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
    // The annotation of the card, for INFO in the player (cut: the card of
    // lazy episodes goes to tmp_dir); not a string -> none.
    $desc = isset($m['description']) && is_string($m['description']) ?
        aio_cut(trim(aio_utf8($m['description'])), AIO_INFO_DESC_MAX) : '';
    return array(
        'series' => $series,
        'imdb' => preg_match('/^tt[0-9]{1,10}$/', $imdb) ? $imdb : '',
        'dune_id' => preg_match('/^' . AIO_DUNE_ID . '$/D', $dune_id) ? $dune_id : '',
        'title' => $title !== '' ? $title : aio_str($m, 'native_title'),
        'native' => aio_str($m, 'native_title'),
        'year' => aio_str($m, 'year'),
        'poster' => aio_str($m, 'poster_url'),
        'fanart' => aio_str($m, 'fanart_url'),
        'rate_imdb' => aio_str($m, 'rate_imdb'),
        'desc' => $desc,
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
// supplier_info = {sup_data, wh_uid, wh_s, wh_e, wh_pos, wh_len (seconds), wh_*}.
// 'mode': wh_mode, 'debrid' or 'ts'; '' for a record before 0.49.0 or anything else.
// 'len': 0 without wh_len; 'next': of aio_wh_resume_ep. r22 (240619_0210) sends
// wh_len, but no wh_release or wh_mode: null there, the list.
/**
 * @param stdClass $in
 * @return AioResume|null
 */
function aio_resume($in)
{
    $si = isset($in->supplier_info) && is_string($in->supplier_info) ?
        json_decode($in->supplier_info) : null;
    if (!is_object($si) || !isset($si->wh_release) || !is_string($si->wh_release) ||
        !preg_match('/^[0-9a-f]{40}$/', $si->wh_release))
        return null;
    // r25 shell attaches the history to our own button "Melange NN%" in the
    // "Play..." dialog too: that one opens the list, as in r24.
    if (isset($in->selected_control_id) && $in->selected_control_id === 'play_online')
    {
        aio_log('resume: supplier_info of the play_online button ignored -> list');
        return null;
    }
    return array(
        'hash' => $si->wh_release,
        'mode' => isset($si->wh_mode) && ($si->wh_mode === 'debrid' || $si->wh_mode === 'ts') ? $si->wh_mode : '',
        's' => aio_int($si, 'wh_s', -1),
        'e' => aio_int($si, 'wh_e', -1),
        'pos' => max(0, aio_int($si, 'wh_pos', 0)),
        'len' => max(0, aio_int($si, 'wh_len', 0)),
        'uid' => isset($si->wh_uid) && is_string($si->wh_uid) ? $si->wh_uid : '',
        'next' => false);
}

// --- Settings.

// '' when the shell gave no data_dir_path (SDK: an STB without storage).
function aio_data_dir()
{
    return isset(DuneSystem::$properties['data_dir_path']) ? strval(DuneSystem::$properties['data_dir_path']) : '';
}

// 'ino:mtime:size' of settings.json, '' when there is none. The page renames
// a new file over it: a new inode even within the same second.
function aio_settings_sig()
{
    $f = aio_data_dir() . '/' . AIO_SETTINGS_FILE;
    clearstatcache();
    $s = aio_data_dir() !== '' && is_file($f) ? stat($f) : false;
    return $s === false ? '' : $s['ino'] . ':' . $s['mtime'] . ':' . $s['size'];
}

// aio_settings_read of data_dir; why settings.json was not read goes to the
// log when it or the file changes (the QR dialog reads it every 1.5 s).
/**
 * @return AioSettings
 */
function aio_settings_read_logged()
{
    $s = aio_settings_read(aio_data_dir(), $why);
    $key = $why !== '' ? $why . ' ' . aio_settings_sig() : '';
    if ($key !== Aio::$settings_why_key && $why !== '')
        aio_log("settings: settings.json $why");
    Aio::$settings_why_key = $key;
    aio_token_fix(strpos($why, 'not readable') === 0);
    return $s;
}

// The token in web_token, the file written anew with it: a new file is of
// php_server, and the page gives settings.json to the owner of web_token (a
// restored backup - chown -R, chmod -R 777 - may have given the old one to
// another uid). '' when there is none or it is not valid: nothing written.
// $ok: rewritten.
/**
 * @param bool|null $ok
 * @param-out bool $ok
 * @return string
 */
function aio_token_rewrite(&$ok = null)
{
    $ok = false;
    $f = aio_data_dir() . '/' . AIO_TOKEN_FILE;
    clearstatcache();
    if (aio_data_dir() === '' || is_link($f) || !is_file($f) || !is_readable($f))
        return '';
    $t = trim((string) file_get_contents($f, false, null, 0, 64));
    if (!aio_token_ok($t))
        return '';
    $ok = aio_write_file($f, $t);
    if (!$ok)
        aio_log('setup: web_token not rewritten');
    return $t;
}

// web_token of another uid (with posix) or settings.json not readable
// ($unread): web_token rewritten, so that the next save of the page - by a
// bookmark too, with no QR shown - gives settings.json to php_server. Once
// per file of web_token, done or not; a token it cannot read or not valid -
// one line; none, a symlink or not a file - nothing.
/**
 * @param bool $unread
 * @return void
 */
function aio_token_fix($unread)
{
    $f = aio_data_dir() . '/' . AIO_TOKEN_FILE;
    clearstatcache();
    if (aio_data_dir() === '' || is_link($f) || !is_file($f))
        return;
    $uid = fileowner($f);
    $sig = fileinode($f) . ':' . $uid;
    if ($sig === Aio::$token_tried || (!$unread && (!function_exists('posix_geteuid') || $uid === posix_geteuid())))
        return;
    $t = aio_token_rewrite($ok);
    clearstatcache();
    if ($t === '' && !is_link($f) && is_file($f))
        aio_log('setup: web_token ' . (is_readable($f) ? 'not valid' : 'not readable ' . aio_settings_owner($f)));
    Aio::$token_tried = is_file($f) ? fileinode($f) . ':' . fileowner($f) : $sig;
    if ($ok)
        aio_log('setup: web_token rewritten, owner uid ' . $uid . ' -> ' . fileowner($f));
}

// Writes $data to $path atomically, $mode (0600; 0644: a file the shell reads): a fresh file next
// to it, then rename. $path a folder: false, no rename (its warning).
function aio_write_file($path, $data, $mode = 0600)
{
    // A name, not a secret: fopen 'x' fails rather than reuse an existing file.
    $tmp = $path . '.' . substr(md5(uniqid(mt_rand(), true)), 0, 8) . '.tmp';
    $fp = is_dir(dirname($path)) && !is_dir($path) ? fopen($tmp, 'x') : false;
    if (!$fp)
        return false;
    $ok = chmod($tmp, $mode) && fwrite($fp, $data) === strlen($data);
    $ok = fclose($fp) && $ok && rename($tmp, $path);
    if (!$ok && is_file($tmp))
        unlink($tmp);
    return $ok;
}

// settings.json of the settings $s (aio_settings_read) as the page writes
// it (aio_settings_json: whole, saved_at now), by php_server: a fresh file of
// its uid, 0600, renamed over (aio_write_file). The page reads it as root and
// gives a file it writes to the owner of web_token, php_server too. -> success.
/**
 * @param AioSettings $s
 * @return bool
 */
function aio_settings_write($s)
{
    $json = aio_settings_json($s);
    return $json !== '' && aio_data_dir() !== '' && aio_write_file(aio_data_dir() . '/' . AIO_SETTINGS_FILE, $json);
}

// data_dir/settings.json, read at the start of every operation, before its
// first log line (the values are masked there). Not cached: the page writes
// it while php_server runs.
/**
 * @return void
 */
function aio_settings_load()
{
    $s = aio_settings_read_logged();
    list($sort, $rev) = aio_sort_read();
    Aio::$settings = array(
        // The stream URL is <base>/stream/...: the base keeps its trailing slash.
        'base' => aio_manifest_base(aio_settings_manifest($s)),
        'jrs' => aio_jacred_list($s),
        'jrm' => aio_jacred_own_confs($s),
        'servers' => $s['servers'],
        'pw' => aio_settings_secrets($s),
        'ts' => aio_ts_base($s['ts_url']),
        'ts_auth' => aio_ts_auth($s['ts_user'], $s['ts_pass']),
        'ts_save' => $s['ts_save'],
        'markers' => $s['markers'],
        'source_errors' => $s['source_errors'],
        'no_torrents' => aio_no_torrents($s),
        'aio' => $s['aio_own_url'],
        'own' => $s['source'] === 'own',
        'hosts' => aio_settings_hosts($s),
        'sort' => $sort,
        'sort_rev' => $rev,
        'cat_rows' => $s['cat_rows'],
        'cat_logo' => $s['cat_logo'],
        'cat_rating' => $s['cat_rating'],
        'debrid_main' => aio_debrid_main_read(aio_data_dir()));
}

// The sort of the lists, chosen by a colour key of the list (aio_gc_sort):
// data_dir/list_sort, not settings.json (the page writes that one), "<key>"
// or "<key>_rev" (reversed; 0.48.0 before the direction: the key only). None
// or not a key of aio_sort_keys: 'aio', the order of AIOStreams.
/**
 * @return array{string, bool} the key, reversed
 */
function aio_sort_read()
{
    $f = aio_data_dir() . '/' . AIO_SORT_FILE;
    $k = '';
    if (aio_data_dir() !== '' && is_file($f))
        $k = trim((string) @file_get_contents($f, false, null, 0, 16), AIO_TRIM);
    $rev = substr($k, -4) === '_rev';
    if ($rev)
        $k = substr($k, 0, -4);
    return in_array($k, aio_sort_keys(), true) ? array($k, $rev) : array('aio', false);
}

/**
 * @param string $k
 * @param bool $rev
 * @return bool
 */
function aio_sort_write($k, $rev)
{
    return aio_data_dir() !== '' && aio_write_file(aio_data_dir() . '/' . AIO_SORT_FILE, $rev ? "{$k}_rev" : $k);
}

// data_dir/debrid_main (aio_debrid_main_read) of service $svc, by MENU of a list
// (aio_gc_main); the page writes it too: the last write counts.
/**
 * @param string $svc
 * @return bool
 */
function aio_debrid_main_write($svc)
{
    return aio_data_dir() !== '' && aio_write_file(aio_data_dir() . '/' . AIO_DEBRID_MAIN_FILE, $svc);
}

// --- AIOStreams.

// Tests define their own aio_http_get().
if (!function_exists('aio_http_get'))
{
    // -> array(HTTP code, body); $err is set on a transport error, to
    // AIO_HTTP_TOO_BIG (body '') when the decoded body passes $max bytes.
    // $connected: a connection was made (a timeout before it is no slow reply).
    function aio_http_get($url, &$err, $connect = 10, $total = 30, $max = 0, &$connected = null)
    {
        $connected = false;
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
            CURLOPT_CAINFO => AIO_CA_BUNDLE,
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
        // 0 until the TCP connection is made (PHP 5.3 has no CURLINFO_PRIMARY_IP).
        $connected = curl_getinfo($ch, CURLINFO_CONNECT_TIME) > 0;
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
            CURLOPT_CAINFO => AIO_CA_BUNDLE,
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

// Tests define their own aio_http_json().
if (!function_exists('aio_http_json'))
{
    // POST of the JSON $json to an API of AIOStreams (the quick setup:
    // /api/v1/user) -> array(HTTP code, body). $err is set on a transport
    // error, to AIO_HTTP_TOO_BIG (body '') when the decoded body passes $max
    // bytes. As the page sends it (cgi/lib/aio_sync.php): no redirects, no
    // "Expect: 100-continue", the User-Agent of aio_fetch, gzip.
    function aio_http_json($url, $json, &$err, $connect, $total, $max)
    {
        $body = '';
        $over = false;
        $ch = curl_init($url);
        $set = curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => AIO_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
            CURLOPT_HTTPHEADER => array('Accept: application/json', 'Content-Type: application/json', 'Expect:'),
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$over, $max)
            {
                if (strlen($body) + strlen($chunk) > $max)
                {
                    $over = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }));
        if (!$set)
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = $over ? AIO_HTTP_TOO_BIG : ($ok === false ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '');
        curl_close($ch);
        return array($code, $err !== '' ? '' : $body);
    }
}

// Tests define their own aio_http_peek().
if (!function_exists('aio_http_peek'))
{
    // GET of a stream URL for its status and headers only -> array(HTTP
    // code, Location as sent or '', Content-Type or '', Content-Length or -1).
    // No redirects; the transfer stops at the end of the headers, the body (a
    // video) is never read. $err is set on a transport error.
    function aio_http_peek($url, &$err, $connect = 5, $total = 30)
    {
        $code = 0;
        $loc = '';
        $type = '';
        $len = -1;
        $done = false;
        $ch = curl_init($url);
        $set = curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => AIO_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // The User-Agent of aio_fetch: the add-ons see the same client.
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$code, &$loc, &$type, &$len, &$done)
            {
                // A status line starts a response (also after "100 Continue").
                if (preg_match('~^HTTP/\S+\s+([0-9]{3})~', $line, $m))
                {
                    $code = intval($m[1]);
                    $loc = '';
                    $type = '';
                    $len = -1;
                }
                else if ($loc === '' && preg_match('/^Location:[ \t]*(.*)$/is', $line, $m))
                    $loc = rtrim($m[1], " \t\r\n");
                else if ($type === '' && preg_match('/^Content-Type:[ \t]*(.*)$/is', $line, $m))
                    $type = rtrim($m[1], " \t\r\n");
                else if ($len < 0 && preg_match('/^Content-Length:[ \t]*([0-9]+)[ \t\r\n]*$/i', $line, $m))
                    $len = intval($m[1]);
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
            return array(0, '', '', -1);
        }
        $ok = curl_exec($ch);
        $err = $ok === false && !$done ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '';
        curl_close($ch);
        return $err !== '' ? array(0, '', '', -1) : array($code, $loc, $type, $len);
    }
}

// Tests define their own aio_http_range().
if (!function_exists('aio_http_range'))
{
    // GET of the first $max bytes of a stream URL -> array(HTTP code, the body,
    // its first $max bytes). A server without Range sends the whole file (200):
    // the transfer stops at $max all the same. $auth: "login:password" of Basic
    // auth ('' none), no redirects then: curl sends it to another port of the
    // host. $err is set on a transport error, the code and the bytes that came
    // are returned all the same.
    function aio_http_range($url, $max, &$err, $connect, $total, $auth = '')
    {
        $body = '';
        $full = false;
        $ch = curl_init($url);
        $opts = array(
            CURLOPT_RANGE => '0-' . ($max - 1),
            CURLOPT_FOLLOWLOCATION => $auth === '',
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => AIO_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // The User-Agent of aio_http_peek.
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$full, $max)
            {
                $body .= substr($chunk, 0, $max - strlen($body));
                if (strlen($body) < $max)
                    return strlen($chunk);
                // A short count aborts the transfer: curl 23, not an error here.
                $full = true;
                return 0;
            });
        if ($auth !== '')
        {
            $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $opts[CURLOPT_USERPWD] = $auth;
        }
        if (!curl_setopt_array($ch, $opts))
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = $ok === false && !$full ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '';
        curl_close($ch);
        return array($code, $body);
    }
}

// Reasons of a reply kept, at most: the memory of the Dune; the others only counted.
define('AIO_ERRORS_MAX', 30);
// Reasons in a dialog of a lazy episode, at most (aio_errors_labels).
define('AIO_ERRORS_LABELS', 5);
// Characters of a reason, its own ellipsis included: the shell cuts a longer
// button of aio_errors_dialog at the right without one.
define('AIO_ERROR_LINE', 68);
// The same in a label of a dialog of the usual width (aio_errors_labels): the shell
// replaces the middle of a longer one with "...".
define('AIO_ERROR_LABEL', 48);

// No reasons (AioErrors): 'lines' - different reasons, up to AIO_ERRORS_MAX;
// 'more' - different ones past them.
/**
 * @return AioErrors
 */
function aio_no_errors()
{
    return array('lines' => array(), 'more' => 0);
}

// How many different reasons.
/**
 * @param AioErrors $errors
 * @return int
 */
function aio_errors_total($errors)
{
    return count($errors['lines']) + $errors['more'];
}

// The setting is on and the reply has reasons: they are shown.
/**
 * @param AioErrors $errors
 * @return bool
 */
function aio_errors_shown($errors)
{
    return Aio::$settings['source_errors'] && $errors['lines'];
}

// A reply of error streams only, with the setting on -> the window of its reasons
// instead of err_no_streams; else null. $res: the error of aio_fetch, or another
// one without 'errors'.
/**
 * @param string $lang
 * @param array{error: string, detail: string, errors?: AioErrors} $res
 * @return AioAction|null
 */
function aio_errors_only_dialog($lang, $res)
{
    return $res['error'] === 'err_no_streams' && isset($res['errors']) && aio_errors_shown($res['errors']) ?
        aio_errors_dialog(aio_tr($lang, 'errors_title'), $lang, $res['errors']) : null;
}

// The first $max reasons, then "and N more" of the others (lines of plain text).
/**
 * @param string $lang
 * @param AioErrors $errors
 * @param int $max
 * @return list<string>
 */
function aio_errors_rows($lang, $errors, $max)
{
    $rows = array_slice($errors['lines'], 0, $max);
    $rest = aio_errors_total($errors) - count($rows);
    if ($rest > 0)
        $rows[] = str_replace('{n}', strval($rest), aio_tr($lang, 'errors_more'));
    return $rows;
}

// $line, at most $max characters, its ellipsis included.
/**
 * @param string $line
 * @param int $max
 * @return string
 */
function aio_error_cut($line, $max)
{
    return mb_strlen($line, 'UTF-8') > $max ? rtrim(mb_substr($line, 0, $max - 1, 'UTF-8')) . '…' : $line;
}

// The reasons as labels of a dialog of a lazy episode, with the setting on: its
// buttons stay in view. Cut to the same text -> one.
/**
 * @param string $lang
 * @param AioErrors $errors
 * @return list<string>
 */
function aio_errors_labels($lang, $errors)
{
    if (!aio_errors_shown($errors))
        return array();
    $lines = array();
    foreach ($errors['lines'] as $line)
        $lines[] = aio_error_cut($line, AIO_ERROR_LABEL);
    $errors['lines'] = array_values(array_unique($lines));
    return aio_errors_rows($lang, $errors, AIO_ERRORS_LABELS);
}

// The reason of error stream $st (its streamData $sd), one line: "name: reason",
// no icon in the name, masked before the cut (a URL or a UUID across it stays
// hidden). No text at all is a reason too: "?".
/**
 * @param array<mixed> $st
 * @param array<mixed> $sd
 * @return string
 */
function aio_error_line($st, $sd)
{
    $e = aio_arr($sd, 'error');
    $name = aio_str($e, 'title');
    if ($name === '')
        $name = aio_str($st, 'name');
    $why = aio_str($e, 'description');
    if ($why === '')
        $why = aio_str($st, 'description');
    // Only the icon: "[RD]" and "[TB]" tell two add-ons apart.
    $name = trim(strval(preg_replace('/\\[\\s*[' . AIO_EMOJI . ']+\\s*\\]|[' . AIO_EMOJI . ']+/u', '', $name)));
    $line = $name !== '' && $why !== '' && $name !== $why ? "$name: $why" : ($name !== '' ? $name : $why);
    // Runs of control bytes and spaces, C1 controls too -> one space. Two passes:
    // a repeated group recurses in PCRE 8.11, a long run kills php_server.
    $line = preg_replace('/[\x00-\x20\x7F]+/', ' ', strval(preg_replace('/\xC2[\x80-\x9F]/', ' ', $line)));
    $line = trim(strval(aio_mask(strval($line))));
    // Not UTF-8 would break the JSON of the whole dialog.
    if ($line === '' || !preg_match('//u', $line))
        return '?';
    return aio_error_cut($line, AIO_ERROR_LINE);
}

// Error streams of the reply (streamData.type "error": a failed add-on, a bad
// config): the text of the first 3 and the count in the log, masked before the
// cut. -> the reasons, no equal ones, in the order of the reply.
/**
 * @param array<mixed> $streams
 * @return AioErrors
 */
function aio_error_streams($streams)
{
    $n = 0;
    $errors = aio_no_errors();
    $seen = array();
    foreach ($streams as $st)
    {
        $sd = aio_arr($st, 'streamData');
        if (aio_str($sd, 'type') !== 'error')
            continue;
        $line = aio_error_line($st, $sd);
        if (!isset($seen[$line]))
        {
            $seen[$line] = true;
            if (count($errors['lines']) < AIO_ERRORS_MAX)
                $errors['lines'][] = $line;
            else
                $errors['more']++;
        }
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
    return $errors;
}

// Seconds of a stream request, at most.
define('AIO_FETCH_TOTAL', 30);

// -> array('rows' => list) or array('error' => key, 'detail' => text); 'errors':
// the reasons of the error streams of the reply (aio_error_streams), with the
// rows and with err_no_streams.
/**
 * @param string $base
 * @param string $id
 * @param bool $series
 * @return AioFetch
 */
function aio_fetch($base, $id, $series)
{
    $url = $base . 'stream/' . ($series ? 'series' : 'movie') . "/$id.json";
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get($url, $err, 10, AIO_FETCH_TOTAL, AIO_MAX_BYTES);
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
    // The first AIO_MAX_ROWS playable streams. No torrents: P2P rows hidden
    // here, the one way of every list into the state. A file cached on several
    // services: one row (aio_row_merge), before the cut - the places freed go
    // to the next streams; after it a stream is made a row (aio_row) only to
    // be a copy of one there (aio_merge_find), the rest is not looked at.
    $rows = array();
    $hidden = 0;
    $mx = aio_merge_start();
    foreach ($j['streams'] as $st)
    {
        if (count($rows) === AIO_MAX_ROWS)
        {
            $k = is_array($st) ? aio_merge_key($st) : null;
            $r = $k && aio_merge_find($mx, $k) ? aio_row($st, $series) : null;
            if ($r)
                aio_row_merge($rows, $mx, $r);
            continue;
        }
        $r = is_array($st) ? aio_row($st, $series) : null;
        if ($r && Aio::$settings['no_torrents'] && aio_is_p2p($r))
        {
            $hidden++;
            continue;
        }
        if ($r && aio_row_merge($rows, $mx, $r))
            continue;
        if ($r)
        {
            $r['ord'] = count($rows);
            $rows[] = aio_row_voices($r);
        }
    }
    aio_log(sprintf('streams: %d, listed: %d%s, memory peak %.1f MB', count($j['streams']), count($rows),
        $hidden ? ", P2P hidden: $hidden" : '', memory_get_peak_usage() / 1048576));
    if ($mx['by_hash'] + $mx['by_name'] > 0)
        aio_log(sprintf('merged copies of a file on another service: %d (by infoHash %d, by name and size %d)',
            $mx['by_hash'] + $mx['by_name'], $mx['by_hash'], $mx['by_name']));
    $errors = aio_error_streams($j['streams']);
    return $rows ? array('rows' => $rows, 'errors' => $errors) :
        array('error' => 'err_no_streams', 'detail' => '', 'errors' => $errors);
}

// --- The stream list screen.

// "51.6 ГБ" in the shell language ($lang of play_action: ru or another).
function aio_size_str($b, $lang)
{
    if ($b >= 1073741824.0)
        return number_format($b / 1073741824.0, 1, '.', '') . ' ' . aio_tr($lang, 'size_gb');
    return number_format($b / 1048576.0, 0, '.', '') . ' ' . aio_tr($lang, 'size_mb');
}

// "1.5 МБ/с" of the download speed of TorrServer (B/s): under 1 MB/s in KB/s,
// rounded up (over 50 B/s is never "0", as TorrServe); 50 B/s or less (unless
// $zero: from 0 on), negative, over 10 GB/s (nonsense), not a number (NAN,
// INF, a string) -> "—".
/**
 * @param mixed $bps
 * @param string $lang
 * @param bool $zero
 * @return string
 */
function aio_speed_str($bps, $lang, $zero = false)
{
    $b = is_int($bps) || is_float($bps) ? floatval($bps) : -1.0;
    // PHP 5.3: NAN > 50 is true.
    if (!is_finite($b) || $b < 0 || (!$zero && $b <= 50) || $b > 10737418240.0)
        return '—';
    return $b >= 1048576 ? number_format($b / 1048576, 1, '.', '') . ' ' . aio_tr($lang, 'speed_mbs') :
        number_format(ceil($b / 1024), 0, '.', '') . ' ' . aio_tr($lang, 'speed_kbs');
}

function aio_folder_view($media_url, $sel_state)
{
    // "setup:<n>": the same screen redrawn by aio_setup_refresh; a first run: the
    // settings in place of the catalog until AIOStreams is set.
    if ($media_url === 'setup' || strpos($media_url, 'setup:') === 0 ||
        (Aio::$settings['base'] === '' && aio_catv_is_main($media_url)))
        return aio_setup_folder_view($media_url);
    if (strpos($media_url, 'info:') === 0)
        return aio_info_folder_view($media_url, $sel_state);
    if (aio_catv_is_screen($media_url))
        return aio_catv_folder_view($media_url, $sel_state);
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
// season and episode (-1 for a movie), rows in the order of 'sort' and
// 'sort_rev' (of the settings: aio_rows_sort), the shell language of play_action,
// 'errors': the reasons of the error streams of its reply (aio_error_streams).
// view_gcomps.php adds 'gc' - array(sel, top), the last cursor; 'gc_new' -
// the first view of a replaced screen ignores the shell's sel_state; 'gc_prog'
// - progress of the rows for their redraw. A screen replaced by a new list
// sets its cursor only by aio_gc_put_cursor, else 'gc_new' is lost. The one
// writer outside view_gcomps.php: aio_choose keeps 'gc' before it leaves.
// aio_ts_pack_state adds 'pack_only' and 'pack_map': the one row, never shown.
/**
 * @param AioErrors $errors
 * @return AioState
 */
function aio_list_state($mv, $s, $e, $rows, $lang, $errors)
{
    $sort = Aio::$settings['sort'];
    $rev = Aio::$settings['sort_rev'];
    // A file cached on several services: the copy of the main one on top (its status, INFO).
    foreach ($rows as $i => $r)
        $rows[$i] = aio_row_pick($r, Aio::$settings['debrid_main']);
    return array('rid' => aio_rid(), 'movie' => $mv, 's' => $s, 'e' => $e, 'rows' => aio_rows_sort($rows, $sort, $rev),
        'lang' => $lang, 'sort' => $sort, 'sort_rev' => $rev, 'errors' => $errors);
}

// --- play_action of the supplier.

function aio_play_action($in)
{
    Aio::$playing = null;
    Aio::$alt = null;
    Aio::$via = null;
    aio_log_input($in);

    $mv = aio_movie($in);
    if (!$mv || $mv['imdb'] === '')
        return aio_error('err_no_imdb');

    $resume = aio_resume($in);
    $s = -1;
    $e = -1;
    // A list opens with the cursor on this release (the one of the history).
    $hash = $resume ? $resume['hash'] : '';
    if ($mv['series'])
    {
        // Back from "Continue watching" the shell sends season = episode = -1.
        // $resume null after it: watched, no later episode - its list.
        if ($resume && $resume['s'] >= 1 && $resume['e'] >= 1)
            list($resume, $s, $e) = aio_wh_resume_ep($mv, $resume);
        else
        {
            $s = aio_int($in, 'season', -1);
            $e = aio_int($in, 'episode', -1);
        }
        // From the main card: season = episode = -1 and no supplier_info.
        if ($s < 1 || $e < 1)
            list($s, $e, $hash) = aio_wh_episode($mv);
    }

    $lang = isset($in->lang) && is_string($in->lang) ? $in->lang : '';
    if (Aio::$settings['base'] === '')
        return aio_no_address($lang);

    $res = aio_fetch(Aio::$settings['base'], $mv['imdb'] . ($mv['series'] ? ":$s:$e" : ''), $mv['series']);
    $only = isset($res['error']) ? aio_errors_only_dialog($lang, $res) : null;
    if ($only)
    {
        aio_log('error: only error streams, their dialog');
        return $only;
    }
    if (isset($res['error']))
        return aio_error($res['error'], $res['detail']);

    $st = aio_list_state($mv, $s, $e, $res['rows'], $lang, $res['errors']);
    Aio::$state = $st;

    if ($resume)
    {
        // The way it was started goes on, never the other way (mode); no mode
        // (a record before 0.49.0): Debrid if it plays, else a P2P row.
        // Through Debrid: the row of the release with a URL, a cached one
        // first (aio_play checks it). One not cached only if the check finds
        // a stream: else maybe a placeholder, as before. Any episode in the
        // file: the reply is of the episode watched; the next one: only with it.
        $mode = $resume['mode'];
        $tag = "resume: release {$resume['hash']} mode=" . ($mode !== '' ? $mode : 'none');
        $pick = aio_release_pick($st['rows'], $resume['hash'], $resume['next'] ? $s : -1, $resume['next'] ? $e : -1);
        $deb = $mode !== 'ts' && $pick['deb'] >= 0 ? $st['rows'][$pick['deb']] : null;
        $pre = null;
        if ($deb && $deb['cached'] !== true)
        {
            $pre = aio_deb_precheck($deb);
            if ($pre['class'] !== 'stream')
            {
                aio_log("$tag: not cached, no stream from Debrid");
                $deb = null;
            }
        }
        if ($deb)
            return aio_play($st, $deb, $resume['pos'], null, false, $pre);
        // Through TorrServer, only if it answers; else the list. Mode ts: a P2P
        // row first, else any of the release; no mode: a P2P row only; debrid:
        // none. No torrents: the list, not a word to TorrServer.
        $ts = $mode === 'ts' ? $pick['ts'] : ($mode === 'debrid' ? -1 : $pick['p2p']);
        $off = Aio::$settings['no_torrents'];
        if ($ts >= 0 && !$off && aio_ts_alive())
        {
            aio_log("$tag -> TorrServer");
            return aio_ts_open($st, $ts, $resume['pos'], '', false, true, $resume['next']);
        }
        aio_log("$tag: " . ($pick['rows'] === 0 ? 'not found' : ($off && $mode !== 'debrid' ? 'no torrents' :
            ($ts < 0 ? 'no way in this mode' : 'TorrServer does not answer'))) . ', showing the list');
    }
    return aio_open_list($hash !== '' ? aio_list_cursor($st, $hash) : $st);
}

// List $st with the cursor on release $hash (aio_release_row), logged.
/**
 * @param AioState $st
 * @param string $hash
 * @return AioState
 */
function aio_list_cursor($st, $hash)
{
    list($sel, $by) = aio_release_row($st['rows'], $hash, $st['s'], $st['e']);
    aio_log("list: cursor on row $sel of " . count($st['rows']) . ", $by");
    return aio_gc_put_cursor($st, $sel);
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

// handle_user_input of our plugin, 60 s; "Please wait" after $delay ms (0: at
// once, a request to the internet for sure: the quick setup).
function aio_input($control, $params = array(), $delay = AIO_DELAY_INPUT)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginHandleUserInputActionData::operation_timeout => 60000,
            PluginHandleUserInputActionData::show_dialog_delay => $delay),
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

// The window of the reasons: up to AIO_ERRORS_MAX + 1 rows do not fit a dialog of
// labels (they get no focus, no key scrolls them), so left-aligned buttons of plain
// look, as "C" of knowledge/platform/dialog-scroll.md: ENTER on a row, OK and
// RETURN close it. A caption of smart_label is markup: without <text> the row is
// empty, a bare "<" empties it too.
/**
 * @param string $title
 * @param string $lang
 * @param AioErrors $errors
 * @return AioAction
 */
function aio_errors_dialog($title, $lang, $errors)
{
    $close = aio_close_and(null);
    $defs = array();
    foreach (aio_errors_rows($lang, $errors, AIO_ERRORS_MAX) as $i => $row)
    {
        $c = aio_ctl("row$i", GUI_CONTROL_BUTTON, array(
            GuiButtonDef::caption => '<text>' . str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $row) . '</text>',
            GuiButtonDef::width => 1450, GuiButtonDef::push_action => $close));
        $c[GuiControlDef::params] = array('button_caption_centered' => 'no', 'button_style' => 'smart_label', 'button_height' => 44);
        $defs[] = $c;
    }
    $defs[] = aio_ctl('ok', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => 'OK',
        GuiButtonDef::width => 500, GuiButtonDef::push_action => $close));
    return aio_dialog_act($title, $defs, array(ShowDialogActionData::preferred_width => 1500,
        ShowDialogActionData::max_height => 875, ShowDialogActionData::params => array('page_size' => 11)));
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
    if ($control === 'pre_via')
        return aio_pre_via($in);
    if ($control === 'finish')
        return aio_finish($in);
    if ($control === 'gc_flip')
        return aio_flip($in);
    if ($control === 'gc_refresh')
        return aio_refresh($in);
    if ($control === 'gc_sort')
        return aio_gc_sort($in);
    if ($control === 'gc_main')
        return aio_gc_main($in);
    if ($control === 'gc_choose')
        return aio_choose($in);
    if ($control === 'gc_chosen')
        return aio_chosen($in);
    if ($control === 'gc_menu')
        return aio_gc_menu($in);
    if ($control === 'gc_errors')
        return aio_gc_errors($in);
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
    if (in_array($control, array('tsi_open', 'tsi_tick', 'tsi_close', 'tsi_state'), true))
        return aio_tsi_input($in, $control);
    if ($control === 'ts_cancel')
    {
        aio_log('ts: waiting cancelled');
        return aio_close_and(null);
    }
    if (is_string($control) && strpos($control, 'setup') === 0)
        return aio_setup_input($in, $control);
    if (is_string($control) && strpos($control, 'cat_') === 0)
        return aio_catv_input($in, $control);
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
            // Every focus move on the rows: memory only.
            if (!isset($in->control_id) || $in->control_id !== 'cat_info')
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
            else if ($op === PLUGIN_OP_GET_REGULAR_FOLDER_ITEMS)
            {
                $type = PLUGIN_OUT_DATA_PLUGIN_REGULAR_FOLDER_RANGE;
                $data = aio_catv_range_op(isset($in->media_url) && is_string($in->media_url) ? $in->media_url : '',
                    max(0, aio_int($in, 'from_ndx', 0)));
            }
            // r25 only; the type a literal, the r24 API has no such constant.
            else if (defined('PLUGIN_OP_GET_FOLDER_ROWS') && $op === PLUGIN_OP_GET_FOLDER_ROWS)
            {
                $type = 'plugin_folder_rows';
                $data = aio_catv_rows_op($in);
            }
            // Lazy episodes: media_url comes to get_vod_stream_url, the whole
            // input (playback_url) to get_vod_stream_info.
            else if ($op === PLUGIN_OP_GET_VOD_STREAM_URL ||
                (defined('PLUGIN_OP_GET_VOD_STREAM_INFO') && $op === PLUGIN_OP_GET_VOD_STREAM_INFO))
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
