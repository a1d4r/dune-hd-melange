<?php
// "Melange" item of the "Play..." menu of a Dune movie card:
// play_action -> streams of the owner's AIOStreams -> list -> Dune player,
// linked to the card so that Dune's own watch history works.
// PHP 5.3.6, firmware PHP API only (/firmware_ext/php/).
// Settings: data_dir/settings.json of the settings page (view_setup.php).

// MEL_VERSION and the rules for the addresses: shared with the settings page.
require_once dirname(__FILE__) . '/common.php';
// HTTP clients of TorrServer and Transmission: shared with the settings page.
require_once dirname(__FILE__) . '/net.php';
// The config of AIOStreams and its API /api/v1/user (the migrations before the
// streams, the quick setup): shared with the settings page; no firmware API.
require_once dirname(__FILE__) . '/cgi/aio_conf.php';
// Parsing of AIOStreams replies (no firmware API).
require_once dirname(__FILE__) . '/parse.php';
// Tracks of JacRed.
require_once dirname(__FILE__) . '/jacred.php';
// Dune's watch history.
require_once dirname(__FILE__) . '/history.php';
// The tokens of the UI kit (generated): colours, sizes, the font metric; before the screens and gcomps.php.
require_once dirname(__FILE__) . '/kit_tokens.php';
// The state of the history for the badges of the catalog; the pairs card -> IMDb / TMDB.
require_once dirname(__FILE__) . '/progress.php';
require_once dirname(__FILE__) . '/links.php';
require_once dirname(__FILE__) . '/links_bg.php';
require_once dirname(__FILE__) . '/library.php';
// Playback, the episodes around it and the lazy ones.
require_once dirname(__FILE__) . '/playback.php';
// The start of the files of the media library of the Dune (the own library).
require_once dirname(__FILE__) . '/playback_lib.php';
// Markers of an episode: "Skip intro", "Next episode".
require_once dirname(__FILE__) . '/segments.php';
// "Download to server": qBittorrent WebAPI.
require_once dirname(__FILE__) . '/server.php';
// "Откуда брать": the source profiles.
require_once dirname(__FILE__) . '/sources.php';
// "Watch via TorrServer".
require_once dirname(__FILE__) . '/torrserver.php';
// The client of Lampac ("Онлайн"): no firmware API.
require_once dirname(__FILE__) . '/lampac.php';
// "Онлайн" on the Dune: the online list.
require_once dirname(__FILE__) . '/view_online.php';
// "Онлайн": the search window "Ищу онлайн…", its result.
require_once dirname(__FILE__) . '/online_search.php';
// "Источники…" of the online list: which sources, their order.
require_once dirname(__FILE__) . '/online_sources.php';
// "Озвучка" of the online list: the dialog of the filter by voices.
require_once dirname(__FILE__) . '/online_voices.php';
// "Онлайн": its start, "Continue", play_action of a card with it.
require_once dirname(__FILE__) . '/online_play.php';
// TorrServer in the player: the window of its numbers with INFO.
require_once dirname(__FILE__) . '/ts_info.php';
// Rows of a list at most: our own cap, not only the AIOStreams config.
define('MEL_MAX_ROWS', 100);
// A bigger AIOStreams reply (decoded) is cut off while it loads: the tree of
// json_decode takes ~9.5 MB a MB of it, a 6 MB reply peaks at ~78 MB of the
// 128 MB limit (64-bit PHP, play_action -> get_folder_view).
define('MEL_MAX_BYTES', 6291456);
// $err of mel_http_get for a reply over its $max bytes.
define('MEL_HTTP_TOO_BIG', 'reply over the size limit');
define('MEL_PLUGIN', 'melange');
// Names: mel_ / MEL_ / Mel is the prefix of the code of Melange. An "aio" left is
// AIOStreams almost everywhere (aio_server, aio_mode, aio_tpl*, aio_confs,
// aio_own_url, aio_conf_mig*, a=aio_sync|aio_status|aio_check). The exceptions are
// legacy data kept by the users, so they stay "aio": this supplier id and the
// handler_id "aio", the uid "aio:..." of the watch history (the shell finds the
// supplier by the prefix of a uid), the scheme aio_next: and the fields aio_cin,
// aio_titles, aio_own_card, aio_rel_tmdb of movie_str.
define('MEL_SUP_ID', 'aio');
// Id of a Dune card in a regex: of the server (24 hex) or a local one of the shell
// (custom_mdb/user_movie, "m-..."). No ':' or '/': it goes into recent_uid,
// aio_next: and a file name.
define('MEL_DUNE_ID', '(?:[0-9a-f]{24}|m-[A-Za-z0-9_-]{1,64})');
// Runs of the numbering of Cinemeta of a list at most (mel_cin_valid): One Piece has under 40.
define('MEL_CIN_RUNS_MAX', 1000);
// show_dialog_delay of our handle_user_input actions, ms, as the play_action of
// Online movies.
define('MEL_DELAY_INPUT', 10000);

require_once dirname(__FILE__) . '/voices.php';
require_once dirname(__FILE__) . '/voices_parse.php';
require_once dirname(__FILE__) . '/view_gcomps.php';
// The components of the UI kit (row, stack, text, icon) over gcomps.php.
require_once dirname(__FILE__) . '/kit.php';
// The overlay of a GComps screen (the side panel, a dialog, a wait of our own): its open, close and state.
require_once dirname(__FILE__) . '/kit_ovl.php';
// The side panel on the right of a GComps screen: its keys, state and changes over the builders of kit.php.
require_once dirname(__FILE__) . '/kit_panel.php';
// The own dialog and the wait window over a GComps screen: their keys, state and the ticks of a wait.
require_once dirname(__FILE__) . '/kit_dlg.php';
// The screen of an error and the wait screen of our own (a GComps folder): its view, keys and ticks.
require_once dirname(__FILE__) . '/kit_scr.php';
require_once dirname(__FILE__) . '/view_info.php';
require_once dirname(__FILE__) . '/view_setup.php';
// The catalog: client, ENTER on a tile, its screens.
require_once dirname(__FILE__) . '/catalog.php';
require_once dirname(__FILE__) . '/catalog_card.php';
require_once dirname(__FILE__) . '/view_catalog.php';
require_once dirname(__FILE__) . '/view_catalog_rows.php';
require_once dirname(__FILE__) . '/continue.php';
require_once dirname(__FILE__) . '/cp_meta.php';
// "Мой список": its file; its actions on the screens.
require_once dirname(__FILE__) . '/my_list.php';
require_once dirname(__FILE__) . '/view_my_list.php';
// "На TorrServer": the list of TorrServer, its entries and files (no firmware API); its screen; a start from it; the
// screen of files of a pack.
require_once dirname(__FILE__) . '/ts_list.php';
require_once dirname(__FILE__) . '/view_ts.php';
require_once dirname(__FILE__) . '/view_ts_play.php';
require_once dirname(__FILE__) . '/view_ts_files.php';
// "Скрыть" and the age: the file of the hidden titles, the filter of the rows.
require_once dirname(__FILE__) . '/hidden_titles.php';
// The tab "TV" of the main screen.
require_once dirname(__FILE__) . '/tv_tab.php';
// The own card of a title: its meta, its screen; the page of a person.
require_once dirname(__FILE__) . '/card_meta.php';
require_once dirname(__FILE__) . '/card_more.php';
require_once dirname(__FILE__) . '/card_trailer.php';
require_once dirname(__FILE__) . '/view_card.php';
require_once dirname(__FILE__) . '/view_card_lib.php';
require_once dirname(__FILE__) . '/view_card_libwin.php';
require_once dirname(__FILE__) . '/view_card_series.php';
require_once dirname(__FILE__) . '/view_card_row.php';
require_once dirname(__FILE__) . '/view_card_rows.php';
require_once dirname(__FILE__) . '/view_person.php';
require_once dirname(__FILE__) . '/deep_link.php';
// The colour keys in a row.
require_once dirname(__FILE__) . '/egg.php';

class Mel
{
    // The last stream list. Memory of php_server only: stream URLs carry
    // tokens and never go to a file. After a server restart the list expires.
    /** @var MelState|null */
    public static $state = null;
    // Settings of the operation (mel_settings_load): 'base' - base URL of the
    // manifest (the own config, else the config made on the chosen server), '' when none; 'jrs' - mel_jacred_list(): the chosen JacRed,
    // then MEL_JACRED_DEFAULT (keys of mel_jacred_conf() + 'builtin'); 'jrm' -
    // mel_jacred_own_confs(): the own JacRed, chosen or not, for the mask of the log;
    // 'servers' - mel_server_conf() list of "Download to server", 'pw' - their
    // passwords and the keys of the services. Hidden in the log. 'ts' - the
    // TorrServer to use, http://host:port (mel_ts_base); 'ts_auth' - its
    // "login:password" for Basic auth ('' none; the password hidden in the log);
    // 'ts_save' - save_to_db of its add (ts_save of the settings). 'markers' -
    // the same of the settings: mel_markers, mel_play. 'markers_exact' - the same:
    // mel_markers. 'source_errors' - the same:
    // mel_errors_shown. 'no_color_keys' - the same: mel_gc_keys_in_menu. 'no_torrents' -
    // mel_no_torrents(): no P2P rows, nothing sent to TorrServer, an app or a server. 'aio' - the own
    // AIOStreams server ('' none), "<aio>" in the log. 'own' - the own config
    // is chosen (source own). 'hosts' - mel_settings_hosts(), hidden in the log.
    // 'sort', 'sort_rev' - the sort of a new list and its direction (mel_sort_read).
    // 'cat_rows' - the rows of the catalog chosen on the page, the argument of mel_cat_user_rows.
    // 'cat_logo' - the same of the settings, as stored. 'cat_rating' - the same: mel_catv_title_item. 'cat_age' - the
    // same: mel_ht_now.
    // 'debrid_main' - the main Debrid service (mel_debrid_main_read): of a file cached on
    // several, the copy of it plays (mel_list_state, mel_play); '' - the first in the reply.
    // 'profiles' - the source profiles (v2) in their order for MENU of a list: id, name, kind (mel_profile_kind), ready
    // (mel_profile_ready). 'prof', 'lp_prof' - the ids of the pair of profiles of the operation (mel_profile_pair: of
    // releases, of Lampac; '' none): 'base' and the rest are of the first, 'lp' and 'lp_src' of the second. 'main' - the
    // id of the main profile ('' none), 'ready' - a profile ready there. 'made' - the config of Melange in use: array('base', 'mode', 'conf' - MelConf),
    // null for none or the own one (the migrations of mel_mig_run). 'lp' - the Lampac of "Онлайн"
    // (mel_lp_base, view_online.php), '' off; its host "<lampac>" in the log ('hosts'). 'lp_src' - lampac_sources
    // of the settings: the sources of the online list found and the choice of them (mel_lpv_srcs_pick). 'lp_auth' - the
    // token, "as Lampa" and uid of that Lampac, MelLp::$auth with it (mel_op_apply).
    /** @var MelOpSettings */
    public static $settings = array('base' => '', 'jrs' => array(), 'jrm' => array(), 'servers' => array(),
        'pw' => array(), 'ts' => MEL_TS_DEFAULT, 'ts_auth' => '', 'ts_save' => false, 'markers' => true, 'markers_exact' => false, 'source_errors' => false, 'no_color_keys' => false, 'no_torrents' => false, 'aio' => '', 'own' => false,
        'hosts' => array(), 'sort' => 'aio', 'sort_rev' => false, 'cat_rows' => null, 'cat_logo' => true,
        'cat_rating' => false, 'cat_age' => null, 'debrid_main' => '', 'profiles' => array(), 'prof' => '', 'lp_prof' => '',
        'main' => '', 'ready' => false, 'made' => null, 'lp' => '', 'lp_src' => array(),
        'lp_auth' => array('token' => '', 'lampa' => false, 'uid' => ''));
    // settings.json as read at the start of the operation (mel_settings_load): another pair of its profiles takes its
    // settings without a second read (mel_op_use). null before the first one.
    /** @var MelSettings|null */
    public static $file = null;
    // Movies of the playlists with lazy episodes, by dune id: array('movie' =>
    // mel_movie, 'lang' => ...). Also in tmp_dir (mel_movie_save): a lazy
    // episode must work after a php_server restart. No stream URLs.
    /** @var array<string, MelLazyMovie> */
    public static $movies = array();
    // The list of an episode that is not in the release, until "Choose a
    // release" of its dialog.
    /** @var MelState|null */
    public static $next = null;
    // The reply of a lazy episode that was no URL for get_vod_stream_url or _info (mel_lazy_url): its playback_url,
    // the time, the action (held already), until its lazy_reply (mel_lazy_replied).
    /** @var MelLazyReply|null */
    public static $lazy_reply = null;
    // What plays: array('from' => rid of the list screen under the player
    // ('' if none), 'st' => list state of the episode playing now, 'sel' =>
    // row of its release). Lazy episodes move it on; back on that screen
    // mel_finish replaces it with the list of the episode played last.
    // It is also the season of the playlist playing for mel_lazy_url, so
    // every vod_play of mel_play marks it (mel_playing). Whether the start of that playlist resumed: Mel::$resumed.
    /** @var MelPlaying|null */
    public static $playing = null;
    // Why the start of the playlist built last by mel_play / mel_lpv_ep_play (Debrid, TorrServer, Lampac) resumes, ''
    // it does not (mel_resumed); only their lazy items (next, lp_next) read it: the shell starts the first item it
    // moves to next from that position too (Dune 25.11.2026), so that lazy episode goes the old way, a new vod_play
    // (mel_lazy_url). null while mel_lazy_url runs the handler of a lazy item: its vod_play is noted there, only
    // when it goes to the shell (mel_resumed_note). Not in Mel::$playing: Lampac has none, and mel_ts_play notes it
    // before its mel_play.
    /** @var string|null */
    public static $resumed = '';
    // The dialog of a refused start with "Via <the other one>" or "The same episode
    // from another release" (mel_pre_failed), until its button (mel_pre_via,
    // mel_pre_twin_go) or another start.
    /** @var MelAlt|null */
    public static $alt = null;
    // The service chosen by "Via <service>": the start and the next episodes of
    // this release (lazy, the playlist, the autoplay) take its copy
    // (mel_main_svc); a new start from a list or play_action - the main one again.
    /** @var MelVia|null */
    public static $via = null;
    // The list state of a TorrServer waiting dialog when it is not the list
    // on screen (a lazy episode, mel_next): its timer finds it by rid.
    /** @var MelState|null */
    public static $tswait = null;
    // The row and the map "episode -> file" of the last start through TorrServer:
    // the episodes of the map go without AIOStreams (mel_ts_pack_state). Memory of
    // php_server only: the magnet of a private tracker carries a passkey.
    /** @var MelTsPack|null */
    public static $ts_pack = null;
    // Start of the operation (microtime): set at the start of every
    // call_plugin and never reset (php_server keeps the last one). Its
    // readers - mel_precheck (time left to the stream check), mel_jacred
    // (cutoff), mel_relist_screen (log times), mel_lazy_hold (the soonest
    // vod_play of a lazy episode) - run only inside call_plugin;
    // tests set it themselves. 0: mel_precheck takes its full timeout,
    // mel_jacred skips the request, mel_lazy_hold does not wait.
    public static $t0 = 0;
    // Why settings.json was not read and the file then (mel_settings_read_logged):
    // a reason is logged once, not on every read.
    public static $settings_why_key = '';
    // The same of mig_lost of the settings: the line of the migration once per file.
    public static $settings_lost_key = '';
    // 'ino:uid' of web_token after mel_token_fix tried it: never again for that file.
    public static $token_tried = '';
    // The root of the server that answered 404 on /api/v1/segments (mel_markers):
    // no more reads or requests for it. Memory of php_server only: after an
    // update of the server the route is found without the user.
    public static $seg_no_route = '';
    // The zlib function of MelQrPng (in libphp5 of r24 and r25, not known for
    // older ones): mel_qr_file makes no picture without it. Tests name a missing one.
    public static $zlib = 'gzcompress';
}

// --- Log: /tmp/run/melange.log (stdout of php_server).

// The secrets of the settings and of stream URLs hidden (common.php).
function mel_mask($s)
{
    return mel_mask_secrets($s, Mel::$settings['base'], Mel::$settings['jrm'], Mel::$settings['pw'],
        Mel::$settings['aio'], Mel::$settings['hosts']);
}

function mel_log($msg)
{
    // gmdate: date() of PHP 5.3.6 on Dune is an hour ahead.
    hd_print('melange: ' . gmdate('H:i:s') . ' ' . mel_mask($msg));
}

// $stop: also stops the player (a lazy episode; proven on the device).
// $log false: an action shown later, if ever (not logged now).
function mel_error($key, $detail = '', $stop = false, $log = true)
{
    if ($log)
        mel_log("error: $key" . ($detail !== '' ? " ($detail)" : ''));
    // All fields, as ActionFactory::show_error of Online movies: the shell
    // fails on a missing field it expects (see the list items).
    return array(
        GuiAction::handler_string_id => PLUGIN_SHOW_ERROR_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::data => array(
            PluginShowErrorActionData::fatal => false,
            PluginShowErrorActionData::title => "%tr%$key",
            PluginShowErrorActionData::msg_lines =>
                $detail !== '' ? mel_dialog_lines($detail) : null,
            PluginShowErrorActionData::stop_playback => $stop,
            PluginShowErrorActionData::retry_delay_ms => 0),
        GuiAction::params => null);
}

// --- Input of play_action.

// Every key of params, values masked; movie_str only as a summary.
function mel_log_input($in)
{
    foreach (get_object_vars($in) as $k => $v)
    {
        if ($k === 'movie_str')
        {
            $m = is_string($v) ? json_decode($v, true) : null;
            $v = '';
            foreach (array('id', 'imdb_id', 'tmdb_id', 'tmdbtv_id', 'series_type', 'title', 'native_title', 'year') as $f)
                $v .= "$f=" . mel_str($m, $f) . ' ';
        }
        else if (!is_string($v))
            $v = json_encode($v);
        // Mask before cutting: a cut secret would not match the mask.
        mel_log("in: $k = " . mel_cut(mel_mask($v), 1000));
    }
}

/**
 * @return MelMovie|null
 */
function mel_movie($in)
{
    $m = isset($in->movie_str) && is_string($in->movie_str) ?
        json_decode($in->movie_str, true) : null;
    if (!is_array($m))
        return null;
    // Top-level series_type is series|single; inside movie_str it is "1" or "".
    $series = isset($in->series_type) && is_string($in->series_type) ?
        $in->series_type === 'series' : mel_str($m, 'series_type') !== '';
    $imdb = mel_str($m, 'imdb_id');
    $dune_id = mel_str($m, 'id');
    $title = mel_str($m, 'title');
    $mv = array(
        'series' => $series,
        'imdb' => preg_match('/^tt[0-9]{1,10}$/', $imdb) ? $imdb : '',
        'dune_id' => preg_match('/^' . MEL_DUNE_ID . '$/D', $dune_id) ? $dune_id : '',
        'title' => $title !== '' ? $title : mel_str($m, 'native_title'),
        'native' => mel_str($m, 'native_title'),
        'year' => mel_str($m, 'year'),
        'poster' => mel_str($m, 'poster_url'),
        'fanart' => mel_str($m, 'fanart_url'),
        'rate_imdb' => mel_str($m, 'rate_imdb'),
        'seasons' => mel_seasons($m));
    // The TMDB of the releases of a card of no IMDb (mel_rel_id): of a series its tmdbtv_id only (its tmdb_id may be of a
    // movie), of a movie its tmdb_id.
    $tmdb = mel_str($m, $series ? 'tmdbtv_id' : 'tmdb_id');
    if (preg_match('/^[0-9]{1,10}$/D', $tmdb))
        $mv['tmdb'] = $tmdb;
    // An IMDb the catalog matched (TMDB knows none; owner 21.11.2026): the releases by that TMDB (mel_rel_id). Only of a
    // list of Melange (aio_rel_tmdb: the own card, mel_oc_watch_in; a tile, mel_card_releases); a card of the Dune - by IMDb.
    if ($mv['imdb'] !== '' && isset($mv['tmdb']) && isset($m['aio_rel_tmdb']) && $m['aio_rel_tmdb'] === true)
    {
        $mv['rel_tmdb'] = true;
        mel_log("movie: {$mv['imdb']} matched by the catalog: the releases by tmdb:{$mv['tmdb']}");
    }
    // No card of the Dune and no IMDb: the key of the card (mel_lpv_key) of that TMDB, "tmdb:<id>" / "tmdbtv:<id>".
    if ($mv['imdb'] === '' && $mv['dune_id'] === '' && isset($mv['tmdb']))
        $mv['lpv_key'] = ($series ? 'tmdbtv:' : 'tmdb:') . $mv['tmdb'];
    // Of the own card, else of its runs file (a card of the shell: its numbering is of Cinemeta, mel_cin_load).
    $cin = mel_cin_valid(isset($m['aio_cin']) ? $m['aio_cin'] : null);
    if (!$cin && $series && $mv['imdb'] !== '')
        $cin = mel_cin_load($mv['imdb']);
    $mv['cin'] = $cin;
    // Of the own card only: the titles of its episodes (mel_ser_name).
    $titles = mel_titles_valid(isset($m['aio_titles']) ? $m['aio_titles'] : null);
    if ($titles)
        $mv['titles'] = $titles;
    // Of the own card (mel_oc_watch): its lists, the online one too, have no "Выбрать серию".
    if (isset($m['aio_own_card']) && $m['aio_own_card'] === true)
        $mv['own_card'] = true;
    return $mv;
}

// The runs file of the series of IMDb $imdb in data_dir (mel_cin_save), '' without data_dir.
/**
 * @param string $imdb
 * @return string
 */
function mel_cin_path($imdb)
{
    return mel_data_dir() !== '' && preg_match('/^tt[0-9]{1,10}$/D', $imdb) ? mel_data_dir() . "/cin_$imdb.json" : '';
}

// Runs $runs (mel_cin_valid) of the numbering of Cinemeta of an anime numbered on, IMDb $imdb, to its file in
// data_dir (~1 KB; written again only when they change): the ways in of the native card and "Continue" number the
// episodes of the list by them with no meta and no network (mel_movie: "S21E32 (923)", the file 923 of a pack).
// Logged when written.
/**
 * @param string $imdb
 * @param list<array{int, int, int}> $runs
 * @return void
 */
function mel_cin_save($imdb, $runs)
{
    $path = mel_cin_path($imdb);
    $data = json_encode($runs);
    if ($path === '' || !$runs || (is_file($path) && file_get_contents($path) === $data))
        return;
    mel_log("cinemeta: runs of $imdb " . (mel_write_file($path, $data) ? 'saved' : 'not saved'));
}

// The runs of the series of IMDb $imdb from its file (mel_cin_save), none for none or a bad one.
/**
 * @param string $imdb
 * @return list<array{int, int, int}>
 */
function mel_cin_load($imdb)
{
    $path = mel_cin_path($imdb);
    $size = $path !== '' && is_file($path) ? filesize($path) : 0;
    return $size > 0 && $size <= 65536 ? mel_cin_valid(json_decode(strval(file_get_contents($path)), true)) : array();
}

// The runs of the numbering of Cinemeta of a list from the own card (aio_cin of movie_str, mel_oc_watch;
// mel_cm_cin_runs) checked: each array(the first number of episodes, ascending from 1; a season of Cinemeta,
// 0 for numbers of no pair; its episode, 0 with season 0), MEL_CIN_RUNS_MAX at most; anything else: none.
// Season 0 is "no pair", not the specials: the server pairs S0 of Cinemeta, and the plugin keeps it out of the
// runs (an episode paired with a special is a number of no pair, mel_cm_eps).
/**
 * @param mixed $v
 * @return list<array{int, int, int}>
 */
function mel_cin_valid($v)
{
    if (!is_array($v) || count($v) > MEL_CIN_RUNS_MAX)
        return array();
    $out = array();
    $last = 0;
    foreach ($v as $r)
    {
        if (!is_array($r) || count($r) !== 3 || !isset($r[0], $r[1], $r[2]) || !is_int($r[0]) || !is_int($r[1]) ||
            !is_int($r[2]) || $r[0] <= $last || $r[1] < 0 || $r[1] > 9999 || $r[2] < 0 || $r[2] > 99999 ||
            ($r[1] > 0) !== ($r[2] > 0))
            return array();
        $out[] = array($r[0], $r[1], $r[2]);
        $last = $r[0];
    }
    return $out;
}

// The titles of the episodes of a list from the own card (aio_titles of movie_str, mel_oc_watch) checked:
// "<season>:<episode>" as the list asks them => one line, not empty, MEL_CM_EPS_MAX at most; anything else
// left out.
/**
 * @param mixed $v
 * @return array<string, string>
 */
function mel_titles_valid($v)
{
    $out = array();
    if (!is_array($v) || count($v) > MEL_CM_EPS_MAX)
        return $out;
    foreach ($v as $k => $t)
    {
        $t = is_string($t) ? mel_one_line($t) : '';
        if ($t !== '' && preg_match('/^[1-9][0-9]{0,3}:[1-9][0-9]{0,4}$/D', strval($k)))
            $out[strval($k)] = $t;
    }
    return $out;
}

// Number $n of an episode of a series numbered on (season 1 of its meta) -> array(season, episode) of Cinemeta by
// runs $runs (mel_cin_valid): by the run of $n; null for none (a number of no pair, no runs).
/**
 * @param list<array{int, int, int}> $runs
 * @param int $n
 * @return array{int, int}|null
 */
function mel_cin_pair($runs, $n)
{
    $run = null;
    foreach ($runs as $r)
    {
        if ($r[0] > $n)
            break;
        $run = $r;
    }
    return $run && $run[1] > 0 ? array($run[1], $run[2] + $n - $run[0]) : null;
}

// Episode $e of season $s of Cinemeta -> its number of the meta by runs $runs (mel_cin_valid), the back of
// mel_cin_pair; 0 for none. A run goes up to the next one, the last one on.
/**
 * @param list<array{int, int, int}> $runs
 * @param int $s
 * @param int $e
 * @return int
 */
function mel_cin_abs($runs, $s, $e)
{
    foreach ($runs as $i => $r)
    {
        $len = isset($runs[$i + 1]) ? $runs[$i + 1][0] - $r[0] : PHP_INT_MAX;
        if ($s > 0 && $r[1] === $s && $e >= $r[2] && $e - $r[2] < $len)
            return $r[0] + $e - $r[2];
    }
    return 0;
}

// The number of the meta of episode S$s E$e of an anime numbered on by the runs $runs of the numbering of
// Cinemeta (mel_cin_valid: cin of a list, of the own card or its runs file; cin of the meta): 923 for S21E32
// of One Piece; 0 for none, without runs, or the same as the episode (S1E5 is the 5th).
/**
 * @param list<array{int, int, int}> $runs
 * @param int $s
 * @param int $e
 * @return int
 */
function mel_cin_n($runs, $s, $e)
{
    $n = $e > 0 ? mel_cin_abs($runs, $s, $e) : 0;
    return $n > 0 && !($s === 1 && $n === $e) ? $n : 0;
}

// The id of the releases of card $mv: its IMDb, else "tmdb:<id>" of its TMDB (a series too: AIOStreams takes no
// "tmdbtv:"; owner 20.11.2026); '' none - no releases (Lampac only). An IMDb the catalog matched (rel_tmdb, mel_movie):
// its TMDB (owner 21.11.2026; AIOStreams knows that tt hours later only), the numbering of the episodes as it is.
/**
 * @param MelMovie $mv
 * @return string
 */
function mel_rel_id($mv)
{
    $tmdb = isset($mv['tmdb']) ? $mv['tmdb'] : '';
    if ($mv['imdb'] !== '' && ($tmdb === '' || empty($mv['rel_tmdb'])))
        return $mv['imdb'];
    return $tmdb !== '' ? "tmdb:$tmdb" : '';
}

// The id of the streams of S$s E$e of $mv (mel_rel_id; a movie: the id alone): "<id>:<s>:<e>". -> array(id, for
// mel_fetch: array(its number of the meta, season, episode) for a list of an anime numbered on (mel_cin_n; logged),
// else null).
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @return array{string, array{int, int, int}|null}
 */
function mel_stream_ask($mv, $s, $e)
{
    $id = mel_rel_id($mv);
    if (!$mv['series'])
        return array($id, null);
    $n = mel_cin_n($mv['cin'], $s, $e);
    if ($n > 0)
        mel_log(sprintf('cinemeta: S%02dE%02d is the episode %d', $s, $e, $n));
    return array("$id:$s:$e", $n > 0 ? array($n, $s, $e) : null);
}

// Row $r of a reply of an episode of an anime numbered on ($cin of mel_stream_ask) has a wrong file of a pack:
// no season in its name, the episode of Cinemeta there and not its number ("032" of a pack "1-130" for S21E32,
// the 923rd episode of One Piece; AIOStreams counts a pack without a season so).
/**
 * @param MelRow $r
 * @param array{int, int, int}|null $cin
 * @return bool
 */
function mel_cin_wrong($r, $cin)
{
    return $cin !== null && $cin[2] !== $cin[0] && $r['pf_seasons'] === null &&
        in_array($cin[2], $r['pf_episodes'], true) && !in_array($cin[0], $r['pf_episodes'], true);
}

// season_numbers of movie_str ({"1": {"episodes_count": 10, ...}, ...}, the
// shell's movie_screen.php) -> array(season => episodes), ascending.
function mel_seasons($m)
{
    $res = array();
    foreach (mel_arr($m, 'season_numbers') as $n => $sn)
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
// supplier_info = {sup_data, wh_uid, wh_s, wh_e, wh_pos, wh_len (seconds), wh_*}; on r25
// the button "Melange NN%" (play_online) too: mel_play_action takes its profile only.
// 'mode': wh_mode, 'debrid' or 'ts'; '' for a record before 0.49.0 or anything else;
// 'lampac' of an online record (online_play.php): 'hash' its key as it is, of no format
// checked here (mel_lp_find of mel_lpv_resume does: bad_key, the search window); 'local' of
// a copy of the media library (mel_lib_play): 'hash' its key lib-<12 hex>, none else.
// 'len': 0 without wh_len; 'next': of mel_wh_resume_ep; 'prof': wh_prof, the id of the
// profile of the start (v2; '' - a record of before it, or not an id). r22 (240619_0210) sends
// wh_len, but no wh_release or wh_mode: null there, the list.
/**
 * @param stdClass $in
 * @return MelResume|null
 */
function mel_resume($in)
{
    $si = isset($in->supplier_info) && is_string($in->supplier_info) ?
        json_decode($in->supplier_info) : null;
    if (!is_object($si) || !isset($si->wh_release) || !is_string($si->wh_release))
        return null;
    $mode = isset($si->wh_mode) && in_array($si->wh_mode, array('debrid', 'ts', 'lampac', 'local'), true) ?
        strval($si->wh_mode) : '';
    $rel = $si->wh_release;
    if ($mode === 'local')
        $ok = mel_lib_key_ok($rel);
    else if ($mode === 'lampac')
        $ok = true;
    else
        $ok = preg_match('/^[0-9a-f]{40}$/', $rel) === 1;
    if (!$ok)
        return null;
    return array(
        'hash' => $rel,
        'mode' => $mode,
        's' => mel_int($si, 'wh_s', -1),
        'e' => mel_int($si, 'wh_e', -1),
        'pos' => max(0, mel_int($si, 'wh_pos', 0)),
        'len' => max(0, mel_int($si, 'wh_len', 0)),
        'uid' => isset($si->wh_uid) && is_string($si->wh_uid) ? $si->wh_uid : '',
        'prof' => isset($si->wh_prof) && mel_profile_id_ok($si->wh_prof) ? $si->wh_prof : '',
        'next' => false);
}

// recent_action_params of a vod_play linked to card $dune_id: "Continue watching" brings them back to play_action
// (shell_ext play_recent), its supplier_info read by mel_resume - wh_release $release (an infoHash, the key of an
// online row, the key of a copy on the disk), wh_mode $mode ('debrid', 'ts', 'lampac', 'local'), wh_prof $prof (the id
// of the profile of the start, '' none). The records of the history on the devices keep them: keys and values as
// they are (mel_play, mel_lpv_ep_play, mel_lib_play).
/**
 * @param string $dune_id
 * @param string $release
 * @param string $mode
 * @param string $prof
 * @return MelRecentParams
 */
function mel_recent_params($dune_id, $release, $mode, $prof)
{
    return array(
        'handler_id' => 'entry',
        'control_id' => 'play_recent',
        'sup_id' => MEL_SUP_ID,
        'movie_id' => $dune_id,
        'sup_data' => 'auto',
        'wh_release' => $release,
        'wh_mode' => $mode,
        'wh_prof' => $prof);
}

// --- Settings.

// '' when the shell gave no data_dir_path (SDK: an STB without storage).
function mel_data_dir()
{
    return isset(DuneSystem::$properties['data_dir_path']) ? strval(DuneSystem::$properties['data_dir_path']) : '';
}

// 'ino:mtime:size' of the file $name of data_dir, '' when there is none. A new file renamed
// over it has a new inode even within the same second.
/**
 * @param string $name
 * @return string
 */
function mel_file_sig($name)
{
    $f = mel_data_dir() . '/' . $name;
    clearstatcache();
    $s = mel_data_dir() !== '' && is_file($f) ? stat($f) : false;
    return $s === false ? '' : $s['ino'] . ':' . $s['mtime'] . ':' . $s['size'];
}

// mel_file_sig of settings.json: the page renames a new file over it.
function mel_settings_sig()
{
    return mel_file_sig(MEL_SETTINGS_FILE);
}

// mel_settings_read of data_dir; why settings.json was not read goes to the
// log when it or the file changes (the QR dialog reads it every 1.5 s).
/**
 * @return MelSettings
 */
function mel_settings_read_logged()
{
    $s = mel_settings_read(mel_data_dir(), $why);
    $key = $why !== '' ? $why . ' ' . mel_settings_sig() : '';
    if ($key !== Mel::$settings_why_key && $why !== '')
        mel_log("settings: settings.json $why");
    Mel::$settings_why_key = $key;
    // Once per file: the migration of a file of before v2 with the profiles full (spec v2 §4 p. 2).
    $lost = $s['mig_lost'] ? implode(', ', $s['mig_lost']) . ' ' . mel_settings_sig() : '';
    if ($lost !== '' && $lost !== Mel::$settings_lost_key)
        mel_log('settings: of before v2, ' . MEL_PROFILES_MAX . ' profiles already: no profile of ' . implode(', ', $s['mig_lost']));
    Mel::$settings_lost_key = $lost;
    mel_token_fix(strpos($why, 'not readable') === 0);
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
function mel_token_rewrite(&$ok = null)
{
    $ok = false;
    $f = mel_data_dir() . '/' . MEL_TOKEN_FILE;
    clearstatcache();
    if (mel_data_dir() === '' || is_link($f) || !is_file($f) || !is_readable($f))
        return '';
    $t = trim((string) file_get_contents($f, false, null, 0, 64));
    if (!mel_token_ok($t))
        return '';
    $ok = mel_write_file($f, $t);
    if (!$ok)
        mel_log('setup: web_token not rewritten');
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
function mel_token_fix($unread)
{
    $f = mel_data_dir() . '/' . MEL_TOKEN_FILE;
    clearstatcache();
    if (mel_data_dir() === '' || is_link($f) || !is_file($f))
        return;
    $uid = fileowner($f);
    $sig = fileinode($f) . ':' . $uid;
    if ($sig === Mel::$token_tried || (!$unread && (!function_exists('posix_geteuid') || $uid === posix_geteuid())))
        return;
    $t = mel_token_rewrite($ok);
    clearstatcache();
    if ($t === '' && !is_link($f) && is_file($f))
        mel_log('setup: web_token ' . (is_readable($f) ? 'not valid' : 'not readable ' . mel_settings_owner($f)));
    Mel::$token_tried = is_file($f) ? fileinode($f) . ':' . fileowner($f) : $sig;
    if ($ok)
        mel_log('setup: web_token rewritten, owner uid ' . $uid . ' -> ' . fileowner($f));
}

// Writes $data to $path atomically, $mode (0600; 0644: a file the shell reads): a fresh file next
// to it, then rename. $path a folder: false, no rename (its warning).
function mel_write_file($path, $data, $mode = 0600)
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

// Side in px of a QR picture made by MelQrPng (square, IHDR width), 0 if
// there is none or it is not ours.
/**
 * @param string $png
 * @return int
 */
function mel_qr_png_side($png)
{
    clearstatcache();
    $h = is_file($png) && !is_link($png) && is_readable($png) ? file_get_contents($png, false, null, 0, 24) : '';
    if (strlen($h) !== 24 || substr($h, 0, 16) !== "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR")
        return 0;
    $wh = unpack('Nw/Nh', substr($h, 16, 8));
    return $wh['w'] === $wh['h'] && $wh['w'] > 0 && $wh['w'] <= 2000 ? $wh['w'] : 0;
}

// The QR picture of $text (MelQrPng::make with $scale, $max_side, $mask) in
// tmp_dir, "<$group><$list><md5 of $text>.png": the shell caches pictures by
// path, so another text gets another name. Made once, then taken from there.
// The other <$group>*.png go, but those of the same $list ('' keeps none).
// The log, as "$tag: QR ...", has no $text. array(path, side px); null when
// it cannot be made or written.
/**
 * @param string $group
 * @param string $list
 * @param string $text
 * @param int $scale
 * @param int $max_side
 * @param int $mask
 * @param string $tag
 * @return array{string, int}|null
 */
function mel_qr_file($group, $list, $text, $scale, $max_side, $mask, $tag)
{
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
    if ($tmp === '' || !function_exists(Mel::$zlib) || (!is_dir($tmp) && !mkdir($tmp, 0755, true)))
        return null;
    $png = "$tmp/$group$list" . md5($text) . '.png';
    foreach ((array) glob("$tmp/$group*.png") as $old)
    {
        if (is_string($old) && $old !== $png && ($list === '' || strpos(basename($old), $group . $list) !== 0))
            unlink($old);
    }
    $side = mel_qr_png_side($png);
    if ($side > 0)
    {
        mel_log("$tag: QR from tmp_dir");
        return array($png, $side);
    }
    require_once dirname(__FILE__) . '/qrpng.php';
    $t = microtime(true);
    $qr = MelQrPng::make($text, $scale, 4, $max_side, $mask);
    if (!mel_write_file($png, $qr['png']))
    {
        mel_log("$tag: QR not written to tmp_dir");
        return null;
    }
    mel_log(sprintf('%s: QR v%d, %d px, %d ms', $tag, $qr['version'], $qr['size'], (microtime(true) - $t) * 1000));
    return array($png, $qr['size']);
}

// settings.json of the settings $s (mel_settings_read) as the page writes
// it (mel_settings_json: whole, saved_at now), by php_server: a fresh file of
// its uid, 0600, renamed over (mel_write_file). The page reads it as root and
// gives a file it writes to the owner of web_token, php_server too. -> success.
/**
 * @param MelSettings $s
 * @return bool
 */
function mel_settings_write($s)
{
    $json = mel_settings_json($s);
    if ($json === '' || mel_data_dir() === '' || !mel_write_file(mel_data_dir() . '/' . MEL_SETTINGS_FILE, $json))
        return false;
    // The file of the operation is this one now: another pair of its profiles (mel_op_use) takes it.
    Mel::$file = $s;
    return true;
}

// data_dir/settings.json, read at the start of every operation, before its
// first log line (the values are masked there). Not cached: the page writes
// it while php_server runs. Mel::$settings - of the pair of the main profile
// (mel_profile_pair); a list screen, a lazy episode take their own (mel_op_use).
/**
 * @return void
 */
function mel_settings_load()
{
    Mel::$file = mel_settings_read_logged();
    mel_op_apply(mel_op_settings(Mel::$file));
}

// Settings $o of an operation (mel_op_settings) in use: Mel::$settings and the auth of its Lampac (MelLp::$auth -
// lampac.php has no settings, its requests take it: mel_lp_send). The one writer of both: the load, another pair
// (mel_op_use), a rollback to the settings of before.
/**
 * @param MelOpSettings $o
 * @return void
 */
function mel_op_apply($o)
{
    Mel::$settings = $o;
    MelLp::$auth = $o['lp_auth'];
}

// The pair of profiles of ids $rel (of releases) and $lp (of Lampac) of the settings $s (spec v2 §1.9) -> their indexes
// in profiles, the one resolver of a pair: one not there, not of its kind or not ready ('' too) - that of the pair of the
// main one (mel_profile_pair; -1 none).
/**
 * @param MelSettings $s
 * @param string $rel
 * @param string $lp
 * @return array{int, int}
 */
function mel_op_pair($s, $rel, $lp)
{
    list($r0, $l0) = mel_profile_pair($s);
    $r = mel_profile_index($s, $rel);
    $l = mel_profile_index($s, $lp);
    if ($r < 0 || !mel_profile_rel($s['profiles'][$r]) || !mel_profile_ready($s['profiles'][$r]))
        $r = $r0;
    if ($l < 0 || mel_profile_rel($s['profiles'][$l]) || !mel_profile_ready($s['profiles'][$l]))
        $l = $l0;
    return array($r, $l);
}

// The settings of an operation (Mel::$settings, put in use by mel_op_apply) of settings $s (mel_settings_read) as if the
// source were the profiles of the pair of ids $rel (of releases), $lp (of Lampac), resolved by mel_op_pair ('' - of the
// pair of the main one; spec v2 §1.9). The source, its config and keys, "Без торрентов" of $rel (mel_profile_view: the
// configs only its own - a cell of another profile never goes in); the Lampac of $lp and its auth (lp_auth); the
// passwords, keys, tokens and hosts hidden in the log - of all profiles; the sort and the main Debrid read from their
// files.
/**
 * @param MelSettings $s
 * @param string $rel
 * @param string $lp
 * @return MelOpSettings
 */
function mel_op_settings($s, $rel = '', $lp = '')
{
    list($rel, $lp) = mel_op_pair($s, $rel, $lp);
    $v = $s;
    if ($rel >= 0)
        $v = mel_profile_view($v, $s['profiles'][$rel]);
    // No profile of releases: no config (the source at the top kept, of its words: "Впишите ссылку…" of an own one).
    else
        list($v['manifest_url'], $v['aio_confs'], $v['rd_key'], $v['tb_key']) = array('', array(), '', '');
    if ($lp >= 0)
        $v = mel_profile_view($v, $s['profiles'][$lp]);
    else
        $v = array_merge($v, mel_lp_keys());
    list($sort, $rev) = mel_sort_read();
    $conf = $v['source'] === 'own' ? null : mel_settings_conf($v);
    $lpb = mel_lp_base($v);
    $profiles = array();
    $ready = false;
    foreach ($s['profiles'] as $p)
    {
        $profiles[] = array('id' => $p['id'], 'name' => $p['name'], 'kind' => mel_profile_kind($p),
            'ready' => (bool) mel_profile_ready($p));
        $ready = $ready || mel_profile_ready($p);
    }
    $m = mel_profile_main($s);
    return array(
        // The stream URL is <base>/stream/...: the base keeps its trailing slash.
        'base' => mel_manifest_base(mel_settings_manifest($v)),
        'jrs' => mel_jacred_list($v),
        'jrm' => mel_jacred_own_confs($s),
        'servers' => $s['servers'],
        'pw' => mel_settings_secrets($s),
        'ts' => mel_ts_base($s['ts_url']),
        'ts_auth' => mel_ts_auth($s['ts_user'], $s['ts_pass']),
        'ts_save' => $s['ts_save'],
        'markers' => $s['markers'],
        'markers_exact' => $s['markers_exact'],
        'source_errors' => $s['source_errors'],
        'no_color_keys' => $s['no_color_keys'],
        'no_torrents' => mel_no_torrents($v),
        'aio' => $v['aio_own_url'],
        'own' => $v['source'] === 'own',
        'hosts' => mel_settings_hosts($s),
        'sort' => $sort,
        'sort_rev' => $rev,
        'cat_rows' => $s['cat_rows'],
        'cat_logo' => $s['cat_logo'],
        'cat_rating' => $s['cat_rating'],
        'cat_age' => $s['cat_age'],
        'debrid_main' => mel_debrid_main_read(mel_data_dir()),
        'profiles' => $profiles,
        'prof' => $rel >= 0 ? $s['profiles'][$rel]['id'] : '',
        'lp_prof' => $lp >= 0 ? $s['profiles'][$lp]['id'] : '',
        'main' => $m >= 0 ? $s['profiles'][$m]['id'] : '',
        'ready' => $ready,
        'made' => $conf ? array('base' => mel_settings_base($v), 'mode' => $v['aio_mode'], 'conf' => $conf) : null,
        'lp' => $lpb,
        'lp_src' => $v['lampac_sources'],
        // lampac.php has no settings: its requests to the Lampac of them take these (mel_op_apply, mel_lp_send).
        'lp_auth' => mel_lp_auth($v['lampac_token'], $v['lampac_as_lampa'],
            $v['lampac_as_lampa'] && $lpb !== '' ? mel_lp_uid() : ''));
}

// The settings of the operation (mel_op_apply) of the pair of profiles of ids $rel (of releases) and $lp (of Lampac) of
// Mel::$file (a list screen, a lazy episode, a record of the history), resolved by mel_op_pair. -> the pair as taken
// (ids, '' none).
/**
 * @param string $rel
 * @param string $lp
 * @return array{string, string}
 */
function mel_op_use($rel, $lp)
{
    $s = Mel::$file;
    if (!$s)
        return array(Mel::$settings['prof'], Mel::$settings['lp_prof']);
    list($r, $l) = mel_op_pair($s, $rel, $lp);
    $rid = $r >= 0 ? $s['profiles'][$r]['id'] : '';
    $lid = $l >= 0 ? $s['profiles'][$l]['id'] : '';
    if ($rid !== Mel::$settings['prof'] || $lid !== Mel::$settings['lp_prof'])
        mel_op_apply(mel_op_settings($s, $rid, $lid));
    return array(Mel::$settings['prof'], Mel::$settings['lp_prof']);
}

// The pair of profiles of the list screen of input $in (spec v2 §1.9): its media_url "streams:<rid>" or its rid that of
// Mel::$state - the profiles the list was made of (prof, lp_prof of the state), else the pair of the main one as loaded.
// The list on screen only: a handler of a list kept elsewhere (Mel::$tswait, Mel::$alt, MelLpv::$run) takes the pair
// of that list itself (mel_op_use).
/**
 * @param stdClass $in
 * @return void
 */
function mel_op_of_screen($in)
{
    $st = Mel::$state;
    if (!$st)
        return;
    $url = isset($in->media_url) && is_string($in->media_url) ? $in->media_url : '';
    if ($url === 'streams:' . $st['rid'] || (isset($in->rid) && $in->rid === $st['rid']))
        mel_op_use($st['prof'], $st['lp_prof']);
}

// The settings of the operation (mel_op_use) of the pair of the profile a title opens by (mel_profile_of_record of
// Mel::$file: the record of the history of kind $kind - 'lp', 'rel', '' none - and its profile $id), logged. -> it
// opens online first: that profile is Lampac, or no profile of releases is ready (spec v2 §2.1).
/**
 * @param string $id
 * @param string $kind
 * @return bool
 */
function mel_op_title($id, $kind)
{
    $s = Mel::$file;
    if (!$s)
        return Mel::$settings['prof'] === '' && Mel::$settings['lp_prof'] !== '';
    $t = mel_profile_of_record($s, $id, $kind);
    list($r, $l) = mel_profile_pair($s, $t);
    mel_op_use($r >= 0 ? $s['profiles'][$r]['id'] : '', $l >= 0 ? $s['profiles'][$l]['id'] : '');
    $on = $l >= 0 && ($r < 0 || ($t >= 0 && !mel_profile_rel($s['profiles'][$t])));
    $of = $kind === '' ? 'no record' : ($kind === 'lp' ? 'an online record' : 'a record of a release') . ($id === '' ?
        ' of no profile' : (mel_profile_index($s, $id) === $t ? ' of its profile' : ' of a profile gone or not ready'));
    mel_log("source: $of -> profile " . mel_profile_tag($t, $t >= 0 ? $s['profiles'][$t] : null) . ', pair ' .
        mel_profile_tag($r, $r >= 0 ? $s['profiles'][$r] : null) . ' + ' . mel_profile_tag($l, $l >= 0 ? $s['profiles'][$l] : null) .
        ($on ? ', online' : ''));
    return $on;
}

// The settings of the operation (mel_op_use) of profile $id chosen by "Смотреть из…" (mel_play_action; its caller
// checked it there and ready: mel_src_watch_pick, mel_dl_src (deep_link.php)): it and the other one of the pair of
// the main one, logged; nothing written. -> it is Lampac (the title opens online).
/**
 * @param string $id
 * @return bool
 */
function mel_op_src($id)
{
    $s = Mel::$file;
    $k = $s ? mel_profile_index($s, $id) : -1;
    $on = $k >= 0 && !mel_profile_rel($s['profiles'][$k]);
    $tags = array();
    foreach (array_merge(array($id), mel_op_use($on ? '' : $id, $on ? $id : '')) as $x)
    {
        $i = $s ? mel_profile_index($s, $x) : -1;
        $tags[] = mel_profile_tag($i, $i >= 0 ? $s['profiles'][$i] : null);
    }
    mel_log("source: chosen {$tags[0]}, pair {$tags[1]} + {$tags[2]}" . ($on ? ', online' : ''));
    return $on;
}

// The sort of the lists, chosen by a colour key of the list (mel_gc_sort):
// data_dir/list_sort, not settings.json (the page writes that one), "<key>"
// or "<key>_rev" (reversed; 0.48.0 before the direction: the key only). None
// or not a key of mel_sort_keys: 'aio', the order of AIOStreams.
/**
 * @return array{string, bool} the key, reversed
 */
function mel_sort_read()
{
    $f = mel_data_dir() . '/' . MEL_SORT_FILE;
    $k = '';
    if (mel_data_dir() !== '' && is_file($f))
        $k = trim((string) @file_get_contents($f, false, null, 0, 16), MEL_TRIM);
    $rev = substr($k, -4) === '_rev';
    if ($rev)
        $k = substr($k, 0, -4);
    return in_array($k, mel_sort_keys(), true) ? array($k, $rev) : array('aio', false);
}

/**
 * @param string $k
 * @param bool $rev
 * @return bool
 */
function mel_sort_write($k, $rev)
{
    return mel_data_dir() !== '' && mel_write_file(mel_data_dir() . '/' . MEL_SORT_FILE, $rev ? "{$k}_rev" : $k);
}

// The sort of new lists: key $k (mel_sort_keys), reversed $rev - kept (mel_sort_write) and in the settings of the
// operation, saved or not; false - not saved (the caller logs it).
/**
 * @param string $k
 * @param bool $rev
 * @return bool
 */
function mel_sort_set($k, $rev)
{
    Mel::$settings['sort'] = $k;
    Mel::$settings['sort_rev'] = $rev;
    return mel_sort_write($k, $rev);
}

// data_dir/debrid_main (mel_debrid_main_read) of service $svc, by MENU of a list
// (mel_gc_main); the page writes it too: the last write counts.
/**
 * @param string $svc
 * @return bool
 */
function mel_debrid_main_write($svc)
{
    return mel_data_dir() !== '' && mel_write_file(mel_data_dir() . '/' . MEL_DEBRID_MAIN_FILE, $svc);
}

// --- AIOStreams.

// The options of a GET of mel_http_get and mel_http_get_many with its body to $sink. $connect, $total: s; whole s as
// they are, a fraction (the lazy meta in what is left of a tick, cp_meta.php) in ms where curl has them (r25), with no
// signals: by the alarm of the resolver one under 1 s fails at once; else whole s, rounded up.
/**
 * @param float|int $connect
 * @param float|int $total
 * @param MelHttpSink $sink
 * @return array<int, mixed>
 */
function mel_http_opts($connect, $total, $sink)
{
    $ms = ($connect != floor($connect) || $total != floor($total)) && defined('CURLOPT_TIMEOUT_MS') &&
        defined('CURLOPT_CONNECTTIMEOUT_MS') && defined('CURLOPT_NOSIGNAL');
    $time = $ms ? array(CURLOPT_NOSIGNAL => true, CURLOPT_CONNECTTIMEOUT_MS => max(1, intval($connect * 1000)),
        CURLOPT_TIMEOUT_MS => max(1, intval($total * 1000))) :
        array(CURLOPT_CONNECTTIMEOUT => intval(ceil($connect)), CURLOPT_TIMEOUT => intval(ceil($total)));
    return $time + array(
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        // http(s) only, redirects too (as the settings page).
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        // HTTPS works only with the firmware CA bundle.
        CURLOPT_CAINFO => MEL_CA_BUNDLE,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        // A User-Agent starting with "AIOStreams" makes it add streamData.
        CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . MEL_VERSION,
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_WRITEFUNCTION => array($sink, 'write'));
}

// The reply of a GET done on handle $ch (mel_http_opts, its body in $sink), $fail: its curl error as
// "curl <errno>: <text>" ('' none) -> array(HTTP code, body, err, connected) as mel_http_get.
/**
 * @param resource $ch
 * @param string $fail
 * @param MelHttpSink $sink
 * @return array{int, string, string, bool}
 */
function mel_http_reply($ch, $fail, $sink)
{
    // 0 until the TCP connection is made (PHP 5.3 has no CURLINFO_PRIMARY_IP).
    return mel_http_reply_of(intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)), curl_getinfo($ch, CURLINFO_CONNECT_TIME) > 0,
        $fail, $sink);
}

// The reply of a GET done (HTTP $code, $connected, $fail as mel_http_reply) with its body in $sink -> as mel_http_reply.
/**
 * @param int $code
 * @param bool $connected
 * @param string $fail
 * @param MelHttpSink $sink
 * @return array{int, string, string, bool}
 */
function mel_http_reply_of($code, $connected, $fail, $sink)
{
    // Over $max with an error status: that HTTP error, not "too big".
    $err = $sink->over ? ($code === 200 ? MEL_HTTP_TOO_BIG : '') : $fail;
    return array($code, $err !== '' || $sink->over ? '' : $sink->body, $err, $connected);
}

// The path of the requests of a step for its line of the log: "par <n>" (mel_http_get_many, $par at most at once),
// "seq" (one by one: no curl_multi, or it failed), both, '' none.
/**
 * @param int $par
 * @param int $seq
 * @return string
 */
function mel_http_mark($par, $seq)
{
    return implode(', ', array_merge($par ? array("par $par") : array(), $seq ? array('seq') : array()));
}

// Tests define their own mel_http_get().
if (!function_exists('mel_http_get'))
{
    // -> array(HTTP code, body); $err is set on a transport error, to
    // MEL_HTTP_TOO_BIG (body '') when the decoded body passes $max bytes.
    // $connected: a connection was made (a timeout before it is no slow reply).
    // $connect, $total: s, as mel_http_opts.
    function mel_http_get($url, &$err, $connect = 10, $total = 30, $max = 0, &$connected = null)
    {
        $connected = false;
        $sink = new MelHttpSink($max);
        $ch = curl_init($url);
        $set = curl_setopt_array($ch, mel_http_opts($connect, $total, $sink));
        // PHP 5.3 stops at a refused option: no WRITEFUNCTION, the body would go to stdout.
        if (!$set)
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        list($code, $body, $err, $connected) = mel_http_reply($ch, $ok === false ? 'curl ' . curl_errno($ch) . ': ' .
            curl_error($ch) : '', $sink);
        curl_close($ch);
        return array($code, $body);
    }
}

// Tests define their own mel_http_get_many().
if (!function_exists('mel_http_get_many'))
{
    // The GETs $reqs (key => array(url, connect, total, max) as of mel_http_get) at once (mel_net_multi of net.php) ->
    // key => array(HTTP code, body, err, connected, s it took) as mel_http_get, in the order of $reqs; null: no
    // curl_multi (old firmware), or it failed now (add, exec; from then on none in this php_server, one line of the
    // log): the caller asks one by one (mel_http_get). The callers keep to MEL_HTTP_PAR.
    /**
     * @param array<array-key, array{string, float|int, float|int, int}> $reqs
     * @return array<array-key, array{int, string, string, bool, float}>|null
     */
    function mel_http_get_many($reqs)
    {
        // Not cut: a sign for the caller to keep to it.
        if (count($reqs) > MEL_HTTP_PAR && !MelHttp::$multi_off && function_exists('curl_multi_init'))
            mel_log(sprintf('http: %d requests at once, over MEL_HTTP_PAR (%d)', count($reqs), MEL_HTTP_PAR));
        $sinks = array();
        $q = array();
        foreach ($reqs as $k => $r)
        {
            $sinks[$k] = new MelHttpSink($r[3]);
            $q[$k] = array($r[0], mel_http_opts($r[1], $r[2], $sinks[$k]), $r[2]);
        }
        $why = '';
        $res = mel_net_multi($q, $why);
        if ($res === null)
        {
            if ($why !== '')
                mel_log("http: curl_multi failed ($why), one by one from now on");
            return null;
        }
        $out = array();
        foreach ($res as $k => $x)
        {
            $out[$k] = $x['errno'] === -1 ? array(0, '', $x['err'], false, 0.0) :
                mel_http_reply_of($x['code'], $x['connected'], $x['err'], $sinks[$k]) + array(4 => $x['t']);
        }
        return $out;
    }
}

// Tests define their own mel_http_post().
if (!function_exists('mel_http_post'))
{
    // POST of the form $fields with the cookies $cookie ('' for none) ->
    // array(HTTP code, body (its first 64 KB), cookies of the reply as
    // "name=value; ..."). $err is set on a transport error. No redirects, no
    // Referer or Origin (qBittorrent checks them against its host), no
    // "Expect: 100-continue" (a long magnet).
    function mel_http_post($url, $fields, $cookie, &$err, $connect = 3, $total = 15)
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
            CURLOPT_CAINFO => MEL_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Melange-Dune/' . MEL_VERSION,
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

// Tests define their own mel_http_json().
if (!function_exists('mel_http_json'))
{
    // $method (POST, PUT with the JSON $json; GET, $json null) of an API of
    // AIOStreams (/api/v1/user by mel_user_req) with Basic auth of $auth
    // (array(uuid, password), null none) -> array(HTTP code, body). $err is set
    // on a transport error, to MEL_HTTP_TOO_BIG (body '') when the decoded body
    // passes $max bytes. As the page sends it (cgi/lib/aio_sync.php): the
    // headers of mel_conf_api_headers, no redirects, the User-Agent of
    // mel_fetch, gzip.
    function mel_http_json($url, $json, &$err, $connect, $total, $max, $method = 'POST', $auth = null)
    {
        $body = '';
        $over = false;
        $h = mel_conf_api_headers($json, $auth);
        $opts = array(
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_CAINFO => MEL_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . MEL_VERSION,
            CURLOPT_HTTPHEADER => $h,
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
        if ($method === 'POST')
            $opts[CURLOPT_POST] = true;
        elseif ($method !== 'GET')
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($json !== null)
            $opts[CURLOPT_POSTFIELDS] = $json;
        $ch = curl_init($url);
        if (!curl_setopt_array($ch, $opts))
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = $over ? MEL_HTTP_TOO_BIG : ($ok === false ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '');
        curl_close($ch);
        return array($code, $err !== '' ? '' : $body);
    }
}

// A request to /api/v1/user of the server $base as the page sends it
// (mel_cgi_aio_req): $method (POST, PUT of $json; GET, $json null - its address
// by mel_conf_user_url) with Basic auth of $auth (array(uuid, password), null
// none) by mel_http_json, $connect and $total s -> array(curl errno (-1: none
// known), HTTP code, mel_conf_decode of the body; null: not JSON or over
// MEL_CONF_API_MAX). The request of mel_conf_create of the quick setup and
// of mel_conf_migrate (mel_mig_req).
/**
 * @param string $base
 * @param string $method
 * @param string|null $json
 * @param array{string, string}|null $auth
 * @param int $connect
 * @param int $total
 * @return array{int, int, mixed}
 */
function mel_user_req($base, $method, $json, $auth, $connect = 5, $total = MEL_CONF_API_TIMEOUT)
{
    list($code, $body) = mel_http_json(mel_conf_user_url("$base/api/v1/user", $method), $json, $err, $connect, $total,
        MEL_CONF_API_MAX, $method, $auth);
    if ($err !== '' && $err !== MEL_HTTP_TOO_BIG)
        return array(preg_match('/^curl ([0-9]+):/', $err, $m) ? intval($m[1]) : -1, 0, null);
    return array(0, $code, $body !== '' ? mel_conf_decode($body) : null);
}

// Tests define their own mel_http_peek().
if (!function_exists('mel_http_peek'))
{
    // GET of a stream URL for its status and headers only -> array(HTTP
    // code, Location as sent or '', Content-Type or '', Content-Length or -1).
    // No redirects; the transfer stops at the end of the headers, the body (a
    // video) is never read. $err is set on a transport error.
    function mel_http_peek($url, &$err, $connect = 5, $total = 30)
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
            CURLOPT_CAINFO => MEL_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // The User-Agent of mel_fetch: the add-ons see the same client.
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . MEL_VERSION,
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

// Tests define their own mel_http_range().
if (!function_exists('mel_http_range'))
{
    // GET of the first $max bytes of a stream URL -> array(HTTP code, the body,
    // its first $max bytes). A server without Range sends the whole file (200):
    // the transfer stops at $max all the same. $auth: "login:password" of Basic
    // auth ('' none), no redirects then: curl sends it to another port of the
    // host. $err is set on a transport error, the code and the bytes that came
    // are returned all the same.
    function mel_http_range($url, $max, &$err, $connect, $total, $auth = '')
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
            CURLOPT_CAINFO => MEL_CA_BUNDLE,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // The User-Agent of mel_http_peek.
            CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . MEL_VERSION,
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
define('MEL_ERRORS_MAX', 30);
// Reasons in a dialog of a lazy episode, at most (mel_errors_labels).
define('MEL_ERRORS_LABELS', 5);
// Characters of a reason in a label of a dialog of the usual width (mel_errors_labels), its own
// ellipsis included (a row of the window: MEL_ROW_LINE): the shell replaces the middle of a
// longer one with "...".
define('MEL_ERROR_LABEL', 48);

// No reasons (MelErrors): 'lines' - different reasons, up to MEL_ERRORS_MAX;
// 'more' - different ones past them.
/**
 * @return MelErrors
 */
function mel_no_errors()
{
    return array('lines' => array(), 'more' => 0);
}

// How many different reasons.
/**
 * @param MelErrors $errors
 * @return int
 */
function mel_errors_total($errors)
{
    return count($errors['lines']) + $errors['more'];
}

// The setting is on and the reply has reasons: they are shown.
/**
 * @param MelErrors $errors
 * @return bool
 */
function mel_errors_shown($errors)
{
    return Mel::$settings['source_errors'] && $errors['lines'];
}

// A reply of error streams only, with the setting on -> the window of its reasons
// instead of err_no_streams; else null. $res: the error of mel_fetch, or another
// one without 'errors'.
/**
 * @param string $lang
 * @param array{error: string, detail: string, errors?: MelErrors} $res
 * @return MelAction|null
 */
function mel_errors_only_dialog($lang, $res)
{
    return $res['error'] === 'err_no_streams' && isset($res['errors']) && mel_errors_shown($res['errors']) ?
        mel_errors_dialog(mel_tr($lang, 'errors_title'), $lang, $res['errors']) : null;
}

// The first $max reasons, then "and N more" of the others (lines of plain text).
/**
 * @param string $lang
 * @param MelErrors $errors
 * @param int $max
 * @return list<string>
 */
function mel_errors_rows($lang, $errors, $max)
{
    $rows = array_slice($errors['lines'], 0, $max);
    $rest = mel_errors_total($errors) - count($rows);
    if ($rest > 0)
        $rows[] = str_replace('{n}', strval($rest), mel_tr($lang, 'errors_more'));
    return $rows;
}

// $line, at most $max characters, its ellipsis included.
/**
 * @param string $line
 * @param int $max
 * @return string
 */
function mel_error_cut($line, $max)
{
    return mb_strlen($line, 'UTF-8') > $max ? rtrim(mb_substr($line, 0, $max - 1, 'UTF-8')) . '…' : $line;
}

// The reasons as labels of a dialog of a lazy episode, with the setting on: its
// buttons stay in view. Cut to the same text -> one.
/**
 * @param string $lang
 * @param MelErrors $errors
 * @return list<string>
 */
function mel_errors_labels($lang, $errors)
{
    if (!mel_errors_shown($errors))
        return array();
    $lines = array();
    foreach ($errors['lines'] as $line)
        $lines[] = mel_error_cut($line, MEL_ERROR_LABEL);
    $errors['lines'] = array_values(array_unique($lines));
    return mel_errors_rows($lang, $errors, MEL_ERRORS_LABELS);
}

// The reason of error stream $st (its streamData $sd), one line: "name: reason",
// no icon in the name, masked before the cut (a URL or a UUID across it stays
// hidden). No text at all is a reason too: "?".
/**
 * @param array<mixed> $st
 * @param array<mixed> $sd
 * @return string
 */
function mel_error_line($st, $sd)
{
    $e = mel_arr($sd, 'error');
    $name = mel_str($e, 'title');
    if ($name === '')
        $name = mel_str($st, 'name');
    $why = mel_str($e, 'description');
    if ($why === '')
        $why = mel_str($st, 'description');
    // Only the icon: "[RD]" and "[TB]" tell two add-ons apart.
    $name = trim(strval(preg_replace('/\\[\\s*[' . MEL_EMOJI . ']+\\s*\\]|[' . MEL_EMOJI . ']+/u', '', $name)));
    $line = $name !== '' && $why !== '' && $name !== $why ? "$name: $why" : ($name !== '' ? $name : $why);
    // Runs of control bytes and spaces, C1 controls too -> one space. Two passes:
    // a repeated group recurses in PCRE 8.11, a long run kills php_server.
    $line = preg_replace('/[\x00-\x20\x7F]+/', ' ', strval(preg_replace('/\xC2[\x80-\x9F]/', ' ', $line)));
    $line = trim(strval(mel_mask(strval($line))));
    // Not UTF-8 would break the JSON of the whole dialog.
    if ($line === '' || !preg_match('//u', $line))
        return '?';
    return mel_error_cut($line, MEL_ROW_LINE);
}

// Error streams of the reply (streamData.type "error": a failed add-on, a bad
// config): the text of the first 3 and the count in the log, masked before the
// cut. -> the reasons, no equal ones, in the order of the reply.
/**
 * @param array<mixed> $streams
 * @return MelErrors
 */
function mel_error_streams($streams)
{
    $n = 0;
    $errors = mel_no_errors();
    $seen = array();
    foreach ($streams as $st)
    {
        $sd = mel_arr($st, 'streamData');
        if (mel_str($sd, 'type') !== 'error')
            continue;
        $line = mel_error_line($st, $sd);
        if (!isset($seen[$line]))
        {
            $seen[$line] = true;
            if (count($errors['lines']) < MEL_ERRORS_MAX)
                $errors['lines'][] = $line;
            else
                $errors['more']++;
        }
        if (++$n > 3)
            continue;
        $e = mel_arr($sd, 'error');
        $parts = array();
        foreach (array(mel_str($st, 'name'), mel_str($st, 'description'), mel_str($e, 'title'),
            mel_str($e, 'description')) as $p)
        {
            $p = mel_cut(mel_mask(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $p)), 200);
            if ($p !== '' && !in_array($p, $parts, true))
                $parts[] = $p;
        }
        mel_log("error stream $n: " . implode(' | ', $parts));
    }
    if ($n > 0)
        mel_log("error streams: $n");
    return $errors;
}

// Seconds of a stream request, at most.
define('MEL_FETCH_TOTAL', 30);

// The migrations of a config before its streams (mel_mig_run): s to connect and in all, a request.
define('MEL_MIG_CONNECT', 3);
define('MEL_MIG_TOTAL', 6);
// A failed try (no connection, 429, 5xx...): the next not before this many s, at most this many a boot.
define('MEL_MIG_WAIT', 1800);
define('MEL_MIG_TRIES', 3);
// In tmp_dir (gone by a reboot or a reinstall): the failed tries, key (mel_conf_mig_key) =>
// array(tries, time of the last, 'stop': not until a reboot).
define('MEL_MIG_TRY_FILE', 'aio_conf_mig_try');

// The request of mel_conf_migrate: mel_user_req with the short timeouts of a card.
/**
 * @param string $base
 * @param string $method
 * @param string|null $json
 * @param array{string, string}|null $auth
 * @return array{int, int, mixed}
 */
function mel_mig_req($base, $method, $json, $auth)
{
    return mel_user_req($base, $method, $json, $auth, MEL_MIG_CONNECT, MEL_MIG_TOTAL);
}

// The config $uuid marked at MEL_CONF_SCHEMA in data_dir/aio_conf_mig (the settings $s:
// mel_conf_mig_json) -> written.
/**
 * @param string $uuid
 * @param MelSettings $s
 * @return bool
 */
function mel_mig_mark($uuid, $s)
{
    $dir = mel_data_dir();
    return $dir !== '' && mel_write_file("$dir/" . MEL_CONF_MIG_FILE,
        mel_conf_mig_json(mel_conf_mig_read($dir), $uuid, MEL_CONF_SCHEMA, $s));
}

// The migrations of the config of Melange in use (Mel::$settings['made'], 0.60.0) not
// yet at MEL_CONF_SCHEMA, before its streams are asked: mel_conf_migrate, then the mark;
// a failure is a line of the log only (the streams are asked as always), the next try
// by MEL_MIG_WAIT, MEL_MIG_TRIES. No data_dir or tmp_dir: nothing.
/**
 * @return void
 */
function mel_mig_run()
{
    $m = Mel::$settings['made'];
    $dir = mel_data_dir();
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
    if (!$m || $dir === '' || $tmp === '')
        return;
    $from = mel_conf_mig_of(mel_conf_mig_read($dir), $m['conf']['uuid']);
    if ($from >= MEL_CONF_SCHEMA)
        return;
    $k = mel_conf_mig_key($m['conf']['uuid']);
    $f = "$tmp/" . MEL_MIG_TRY_FILE;
    $tries = is_file($f) ? json_decode((string) file_get_contents($f, false, null, 0, 65536), true) : null;
    $tries = is_array($tries) ? $tries : array();
    $x = isset($tries[$k]) && is_array($tries[$k]) ? $tries[$k] + array(0, 0, false) : array(0, 0, false);
    // abs: the clock may be set after the try (the date of the firmware at a boot).
    if ($x[2] === true || $x[0] >= MEL_MIG_TRIES || ($x[0] > 0 && abs(time() - $x[1]) < MEL_MIG_WAIT))
        return;
    $t = microtime(true);
    list($class, $line) = mel_conf_migrate($m['base'], $m['mode'], $m['conf'], $from, 'mel_mig_req');
    if ($class === 'done')
    {
        // The settings as they are now: the marks of configs gone from them are dropped.
        $s = mel_settings_read($dir, $why);
        if ($why !== '' || !mel_mig_mark($m['conf']['uuid'], $s))
        {
            $class = 'retry';
            $line .= ', the mark not written';
        }
    }
    if ($class !== 'done')
    {
        $tries[$k] = array($x[0] + 1, time(), $class === 'stop');
        if (!is_dir($tmp))
            mkdir($tmp, 0755, true);
        mel_write_file($f, json_encode($tries));
        $line .= $class === 'stop' || $x[0] + 1 >= MEL_MIG_TRIES ? ', no more tries until a reboot' :
            ', next try in ' . (MEL_MIG_WAIT / 60) . ' min';
    }
    // The base whole: an own server is the address of AIOStreams there and masked as one (mel_mask).
    mel_log(sprintf('aio mig: %s %s: %d -> %d, %s, %.2f s', $m['base'], $m['mode'], $from,
        MEL_CONF_SCHEMA, $line, microtime(true) - $t));
}

// -> array('rows' => list: those of the main list, then those of the tier, MelRow tier) or
// array('error' => key, 'detail' => text) - err_no_streams: no row of either; 'errors':
// the reasons of the error streams of the reply (mel_error_streams), with the
// rows and with err_no_streams. Asked by the numbering of Cinemeta ($cin of
// mel_stream_ask): the rows of a wrong file of a pack (mel_cin_wrong) left out.
/**
 * @param string $base
 * @param string $id
 * @param bool $series
 * @param array{int, int, int}|null $cin
 * @return MelFetch
 */
function mel_fetch($base, $id, $series, $cin = null)
{
    mel_mig_run();
    $url = $base . 'stream/' . ($series ? 'series' : 'movie') . "/$id.json";
    $t = microtime(true);
    $err = '';
    list($code, $body) = mel_http_get($url, $err, 10, MEL_FETCH_TOTAL, MEL_MAX_BYTES);
    mel_log(sprintf('request %s: %s, %d bytes, %.2f s', $url,
        $err === MEL_HTTP_TOO_BIG ? 'reply over ' . MEL_MAX_BYTES . ' bytes' : ($err !== '' ? $err : "HTTP $code"),
        strlen($body), microtime(true) - $t));
    if ($err === MEL_HTTP_TOO_BIG)
        return array('error' => 'err_too_big', 'detail' => '');
    if ($err !== '')
        return array('error' => 'err_network', 'detail' => $err);
    if ($code !== 200)
        return array('error' => 'err_http', 'detail' => "HTTP $code");
    $j = mel_decode_streams($body);
    unset($body);
    if (!is_array($j) || !isset($j['streams']) || !is_array($j['streams']))
        return array('error' => 'err_bad_reply', 'detail' => '');
    // The first MEL_MAX_ROWS playable streams: those of the main list first, the places
    // left to the rows of the tier (mel_tier_why), each in the order of the reply (the
    // server puts the tier last; not relied on). No torrents: P2P rows hidden
    // here, the one way of every list into the state. A file cached on several
    // services: one row (mel_row_merge), before the cut - the places freed go
    // to the next streams; after it (MEL_MAX_ROWS rows of the main list; of a stream of
    // the tier: rows of both) a stream is made a row (mel_row) only to
    // be a copy of one there (mel_merge_find), the rest is not looked at. The voices
    // (mel_row_voices) of the rows kept only.
    $rows = array();
    $tiered = 0;
    $hidden = 0;
    $wrong = 0;
    $mx = mel_merge_start($series);
    foreach ($j['streams'] as $st)
    {
        // A row of the tier needs a place left (by the main rows before it), the main list a place of its own.
        $in_tier = is_array($st) && mel_tier_why(mel_arr($st, 'streamData')) !== null;
        // The rows of the main list so far: those of the tier made rows of it by a copy (mel_row_merge) count.
        $main_n = count($rows) - ($tiered - $mx['untier']);
        if (($in_tier ? count($rows) : $main_n) >= MEL_MAX_ROWS)
        {
            $k = is_array($st) ? mel_merge_key($st, $mx['series']) : null;
            $r = $k && mel_merge_find($mx, $k) ? mel_row($st, $series) : null;
            if ($r && !mel_cin_wrong($r, $cin))
                mel_row_merge($rows, $mx, $r);
            continue;
        }
        $r = is_array($st) ? mel_row($st, $series) : null;
        if ($r && mel_cin_wrong($r, $cin))
        {
            $wrong++;
            continue;
        }
        if ($r && Mel::$settings['no_torrents'] && mel_is_p2p($r))
        {
            $hidden++;
            continue;
        }
        if ($r && mel_row_merge($rows, $mx, $r))
            continue;
        if ($r)
        {
            $rows[] = $r;
            $tiered += $r['tier'] ? 1 : 0;
        }
    }
    // The main rows first, then those of the tier, MEL_MAX_ROWS in all (a copy of the main list after
    // its cut may have made a row of the tier one more of it: mel_row_merge).
    $main = array();
    $low = array();
    foreach ($rows as $r)
    {
        if ($r['tier'])
            $low[] = $r;
        else
            $main[] = $r;
    }
    $rows = array_slice(array_merge($main, $low), 0, MEL_MAX_ROWS);
    $tn = 0;
    foreach ($rows as $i => $r)
    {
        $r['ord'] = $i;
        $rows[$i] = mel_row_voices($r);
        $tn += $r['tier'] ? 1 : 0;
    }
    mel_log(sprintf('streams: %d, listed: %d%s%s%s, memory peak %.1f MB', count($j['streams']), count($rows),
        $tn ? ", unconfirmed: $tn" : '', $hidden ? ", P2P hidden: $hidden" : '', $wrong ? ", wrong files of packs: $wrong" : '',
        memory_get_peak_usage() / 1048576));
    if ($mx['by_hash'] + $mx['by_name'] > 0)
        mel_log(sprintf('merged copies of a file on another service: %d (by infoHash %d, by name and size %d)',
            $mx['by_hash'] + $mx['by_name'], $mx['by_hash'], $mx['by_name']));
    $errors = mel_error_streams($j['streams']);
    return $rows ? array('rows' => $rows, 'errors' => $errors) :
        array('error' => 'err_no_streams', 'detail' => '', 'errors' => $errors);
}

// --- The stream list screen.

// "51.6 ГБ" in the shell language ($lang of play_action: ru or another).
function mel_size_str($b, $lang)
{
    if ($b >= 1073741824.0)
        return number_format($b / 1073741824.0, 1, '.', '') . ' ' . mel_tr($lang, 'size_gb');
    return number_format($b / 1048576.0, 0, '.', '') . ' ' . mel_tr($lang, 'size_mb');
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
function mel_speed_str($bps, $lang, $zero = false)
{
    $b = is_int($bps) || is_float($bps) ? floatval($bps) : -1.0;
    // PHP 5.3: NAN > 50 is true.
    if (!is_finite($b) || $b < 0 || (!$zero && $b <= 50) || $b > 10737418240.0)
        return '—';
    return $b >= 1048576 ? number_format($b / 1048576, 1, '.', '') . ' ' . mel_tr($lang, 'speed_mbs') :
        number_format(ceil($b / 1024), 0, '.', '') . ' ' . mel_tr($lang, 'speed_kbs');
}

function mel_folder_view($media_url, $sel_state)
{
    // One timer lives, of a main screen or of a Fav screen: a view of another stops it (mel_mlv_view_asked: not a main
    // one). Within the window of the invalidate of a tick of a screen of Fav (mel_mlv_inv) a screen it invalidated stops
    // none: the main screen under it keeps its own, its ticks wait for those of that screen (mel_pg_tick).
    mel_pg_view_asked($media_url);
    mel_mlv_view_asked($media_url);
    // "setup:<n>": the same screen redrawn by mel_setup_refresh; a first run: the
    // settings in place of the catalog until a source is ready (v2: Lampac alone too).
    if ($media_url === 'setup' || strpos($media_url, 'setup:') === 0 ||
        (!Mel::$settings['ready'] && mel_catv_is_main($media_url)))
        return mel_setup_folder_view($media_url);
    if (strpos($media_url, 'info:') === 0)
        return mel_info_folder_view($media_url, $sel_state);
    if (mel_catv_is_screen($media_url))
        return mel_catv_folder_view($media_url, $sel_state);
    if (strpos($media_url, 'card:') === 0)
        return mel_oc_folder_view($media_url, $sel_state);
    if (strpos($media_url, 'person:') === 0)
        return mel_pp_folder_view($media_url, $sel_state);
    if (strpos($media_url, MEL_KS_URL) === 0)
        return mel_ks_folder_view($media_url);
    if (strpos($media_url, MEL_TSF_URL) === 0)
        return mel_tsf_folder_view($media_url, $sel_state);
    $st = Mel::$state;
    if ($st && $media_url === 'streams:' . $st['rid'])
        return !empty($st['lp_on']) && isset($st['lp']) ? mel_lpv_folder_view($st, $sel_state) : mel_gc_folder_view($st, $sel_state);
    mel_log('folder view: list outdated');
    return mel_lines_view(array(mel_expired_item()));
}

// A new id of a screen: a screen with an old one is stale.
function mel_rid()
{
    return substr(md5(uniqid('', true)), 0, 12);
}

// The original language of the title of card $mv as a code of mel_lang_code
// (mel_iso_lang): of movie_str of its play_action kept in MelLpv::$ids (Melange's
// own cards, mel_lpv_ids_keep); '' unknown (a native card of the Dune).
/**
 * @param MelMovie $mv
 * @return string
 */
function mel_list_orig($mv)
{
    $k = mel_lpv_key($mv);
    return isset(MelLpv::$ids[$k]) ? mel_iso_lang(MelLpv::$ids[$k]['original_language']) : '';
}

// ISO 639-1 or 639-2 code $l ("ja", "jpn") -> a code of mel_lang_code ("JA";
// Ukrainian UA as on Russian trackers); '' for none or an unknown 639-2 one.
/**
 * @param string $l
 * @return string
 */
function mel_iso_lang($l)
{
    $l = strtolower($l);
    if (preg_match('/^[a-z]{3}$/D', $l))
        return mel_jr_lang($l) !== $l ? mel_lang_code(mel_jr_lang($l)) : '';
    if (!preg_match('/^[a-z]{2}$/D', $l))
        return '';
    return $l === 'uk' ? 'UA' : strtoupper($l);
}

// State of a stream list (Mel::$state): its screen "streams:<rid>", the card,
// season and episode (-1 for a movie), rows in the order of 'sort' and
// 'sort_rev' (of the settings: mel_rows_sort; none from 0.58.0: a source profile
// with no streams, mel_gc_src), the shell language of play_action,
// 'errors': the reasons of the error streams of its reply (mel_error_streams).
// A row of no 'voice_langs' gets them (mel_row_langs, by mel_list_orig of the
// card): a list sorted anew keeps them.
// view_gcomps.php adds 'gc' - array(sel, top), the last cursor; 'gc_new' -
// the first view of a replaced screen ignores the shell's sel_state; 'gc_prog'
// - progress of the rows for their redraw; 'tier_open' - the group of the tier unfolded (mel_gc_lv), of
// this list only: a list of another title opens without it, a new list of this one (mel_relist,
// mel_finish, the sort, the main Debrid) takes it over (mel_gc_put_cursor). A screen replaced by a new list
// sets its cursor only by mel_gc_put_cursor, else 'gc_new' is lost. The one
// writer outside view_gcomps.php: mel_choose keeps 'gc' before it leaves.
// mel_ts_pack_state adds 'pack_only' and 'pack_map': the one row, never shown.
// view_online.php adds 'lp' (the online list), 'lp_on', 'lp_only', 'lp_note'.
/**
 * @param MelErrors $errors
 * @return MelState
 */
function mel_list_state($mv, $s, $e, $rows, $lang, $errors)
{
    $sort = Mel::$settings['sort'];
    $rev = Mel::$settings['sort_rev'];
    // A file cached on several services: the copy of the main one on top (its status, INFO).
    $orig = null;
    foreach ($rows as $i => $r)
    {
        $rows[$i] = mel_row_pick($r, Mel::$settings['debrid_main']);
        if (!isset($r['voice_langs']))
        {
            $orig = $orig === null ? mel_list_orig($mv) : $orig;
            $rows[$i]['voice_langs'] = mel_row_langs($rows[$i], $orig);
        }
    }
    return array('rid' => mel_rid(), 'movie' => $mv, 's' => $s, 'e' => $e, 'rows' => mel_rows_sort($rows, $sort, $rev),
        'lang' => $lang, 'sort' => $sort, 'sort_rev' => $rev, 'errors' => $errors, 'prof' => Mel::$settings['prof'],
        'lp_prof' => Mel::$settings['lp_prof']);
}

// --- play_action of the supplier.

// play_action $in; $hist: the episode of the history (mel_wh_pick) $in asks, null none - the own card (mel_oc_watch,
// MelOcEp of mel_oc_pick), the main card (mel_wh_episode here). With no "Continue" its list opens at its release
// (hash), or its online list at the row of its online record (key). $src: the id of the ready profile chosen by
// "Смотреть из…" (mel_oc_watch_from; checked by mel_src_watch_pick, mel_dl_src (deep_link.php)), its list at once
// (mel_op_src), nothing remembered; '' - by the record (mel_op_title).
// A movie_str made by Melange itself adds mel_lpv_tile_ids: Lampac puts the quality into the name of a balancer and show
// false only with a TMDB id. No IMDb and no TMDB (mel_rel_id): the search of Lampac always (its "Continue" too), its
// windows of nothing online and of no answer, never the releases; no Lampac - err_no_imdb_rel. "Continue" of a copy on
// the disk (wh_mode local) of a movie: the file (mel_lib_resume), of a series: the file of the episode
// (mel_lib_resume_ep), before all that - no IMDb, no profile, no network needed; an episode of none on the disk - the
// list of that episode. "Continue" of a series with episodes stopped in their credits not marked yet: their marks
// (mel_cw_take), then this play_action again (cw), before any of it.
/**
 * @param stdClass $in
 * @param MelWhPick|MelOcEp|null $hist
 * @param string $src
 * @return MelAction
 */
function mel_play_action($in, $hist = null, $src = '')
{
    Mel::$playing = null;
    Mel::$alt = null;
    Mel::$via = null;
    mel_log_input($in);

    $mv = mel_movie($in);
    if (!$mv)
        return mel_error('err_no_imdb');
    $lang = isset($in->lang) && is_string($in->lang) ? $in->lang : '';

    $si = mel_resume($in);
    $resume = $si;
    // The episode of "Continue" of a copy on the disk of a series, none of it there: its list (mel_lib_resume_ep).
    $lib_se = null;
    // r25 shell attaches the history to our own button "Melange NN%" in the
    // "Play..." dialog too: that one opens the list, as in r24.
    if ($si && isset($in->selected_control_id) && $in->selected_control_id === 'play_online')
    {
        mel_log('resume: supplier_info of the play_online button ignored -> list');
        $resume = null;
    }
    // This play_action again, of its fields read here: "Повторить" of the disk that did not answer (mel_lib_gone), and
    // after the marks of the episodes stopped in their credits (mel_cw_take; cw: they are made, never twice).
    $again = $resume ? array_filter(array_intersect_key(get_object_vars($in), array_flip(array('movie_str', 'series_type',
        'lang', 'supplier_info', 'selected_control_id'))), 'is_string') : array();
    $retry = $resume ? mel_input('play', $again) : null;
    // "Continue" of a series: those marks first, so the episode is chosen by them (mel_wh_resume_ep: the marks of the own
    // card), as if the own card was opened.
    if ($resume && $mv['series'] && !isset($in->cw))
    {
        $ids = mel_cw_take();
        if ($ids)
        {
            mel_log('resume: the episodes stopped in their credits marked first, then this play_action again');
            return mel_pg_mark_act($ids, array(), true, mel_input('play', $again + array('cw' => '1')));
        }
    }
    if ($resume && $resume['mode'] === 'local')
    {
        $m = (array) json_decode(strval($in->movie_str), true);
        if (!$mv['series'])
            return mel_lib_resume($mv, $m, $resume['hash'], $resume['pos'], $lang, $retry);
        $a = mel_lib_resume_ep($mv, $m, $resume, $lang, $lib_se, $retry);
        if ($a)
            return $a;
        // None of it on the disk: the list of the releases of that episode ($lib_se), no cursor; a record of no
        // episode - as of no record.
        if (!$lib_se)
            mel_log('resume: a record of a copy on the disk of a series ignored -> list');
        $si = $resume = null;
    }

    // No IMDb and no TMDB: no releases; only Lampac, by the title (owner 19.11.2026), no way to the releases.
    $no_id = mel_rel_id($mv) === '';
    if ($no_id && Mel::$settings['lp'] === '')
        return mel_error('err_no_imdb_rel');
    mel_pg_link_movie($mv);
    // The key of an online record ("Continue" of mode lampac), never a release of the list.
    $lpk = $resume && $resume['mode'] === 'lampac' ? $resume['hash'] : '';
    $s = -1;
    $e = -1;
    if ($lib_se)
        list($s, $e) = $lib_se;
    // A list opens with the cursor on this release (the one of the history).
    $hash = $resume && $lpk === '' ? $resume['hash'] : '';
    // The record is of a release (debrid, ts), even when mel_wh_resume_ep leaves no episode to play of it.
    $rel = $resume && $lpk === '';
    if ($mv['series'] && !$lib_se)
    {
        // Back from "Continue watching" the shell sends season = episode = -1.
        // $resume null after it: watched, no later episode - its list.
        if ($resume && $resume['s'] >= 1 && $resume['e'] >= 1)
            list($resume, $s, $e) = mel_wh_resume_ep($mv, $resume);
        else
        {
            $s = mel_int($in, 'season', -1);
            $e = mel_int($in, 'episode', -1);
        }
        // From the main card: season = episode = -1 and no supplier_info. Its release even after "Continue".
        if ($s < 1 || $e < 1)
        {
            $hist = mel_wh_episode($mv);
            $s = $hist['s'];
            $e = $hist['e'];
            $hash = $hist['hash'];
        }
    }
    if ($hist && $lpk === '' && !$rel)
    {
        $hash = $hist['hash'];
        $lpk = $hist['key'];
    }
    // The record the title goes on with (spec v2 §1.5): of supplier_info ("Continue"; the button "Melange NN%" of r25
    // too - its position is not taken, its profile is), else of the history of the card (the main card, the own card:
    // mel_wh_pick), else our latest record of the card (a movie, an episode asked by the shell: mel_wh_card_last; of a
    // movie its release or online row takes the cursor too). Its profile, else the rule of a record of none; no record
    // - the main one.
    $r = $hist && ($hist['hash'] !== '' || $hist['key'] !== '') ? $hist : null;
    $cur = !$mv['series'] && !$resume && $hash === '' && $lpk === '';
    // A record of a copy on the disk (mel_lib_play) latest: none (mel_wh_card_last) - the list by the main profile.
    if (!$r && $mv['dune_id'] !== '' && (!$si || $cur))
        $r = mel_wh_card_last($mv['dune_id']);
    if ($r && $cur)
        list($hash, $lpk) = array($r['hash'], $r['key']);
    // $si of mode local here: the button "Melange NN%" of r25 (play_online) of a record of a copy on the disk ($resume
    // null above, its branch skipped). Of kind '' - the main profile, as of no record: a copy on the disk is of no
    // profile of releases ('rel' would take the first of them over a main Lampac).
    $rec = $si ? array($si['prof'], $si['mode'] === 'lampac' ? 'lp' : ($si['mode'] === 'local' ? '' : 'rel')) :
        ($r ? array($r['prof'], $r['key'] !== '' ? 'lp' : 'rel') : array('', ''));
    $online = $src !== '' ? mel_op_src($src) : mel_op_title($rec[0], $rec[1]);
    // A source of releases chosen for a title of no IMDb and no TMDB: none.
    if ($no_id && $src !== '' && !$online)
        return mel_error('err_no_imdb_rel');
    $online = $online || $no_id;
    // "Онлайн" (online_play.php) of the episode chosen: its "Continue", the title of a profile of Lampac.
    $lp = mel_lpv_play_action($in, $mv, $lang, $s, $e, $resume, $lpk, $online, $src === '' && !$no_id);
    if ($lp)
        return $lp;
    if ($no_id)
        return mel_error('err_no_imdb_rel');
    // No "Онлайн" (no Lampac): an online record opens the releases of its episode, as the card.
    if ($lpk !== '')
        $resume = null;

    // No profile ready: Melange not set. No profile of releases (only Lampac, its "Онлайн" not for this one): the same.
    if (!Mel::$settings['ready'] || Mel::$settings['base'] === '')
        return mel_no_address($lang);

    list($id, $cin) = mel_stream_ask($mv, $s, $e);
    $res = mel_fetch(Mel::$settings['base'], $id, $mv['series'], $cin);
    $only = isset($res['error']) ? mel_errors_only_dialog($lang, $res) : null;
    if ($only)
    {
        mel_log('error: only error streams, their dialog');
        return $only;
    }
    // No releases at all: "Искать онлайн" (online_play.php).
    $none = isset($res['error']) ? mel_lpv_no_releases($mv, $s, $e, $lang, $res) : null;
    if ($none)
        return $none;
    if (isset($res['error']))
        return mel_error($res['error'], $res['detail']);

    $st = mel_list_state($mv, $s, $e, $res['rows'], $lang, $res['errors']);
    Mel::$state = $st;

    if ($resume)
    {
        // The way it was started goes on, never the other way (mode); no mode
        // (a record before 0.49.0): Debrid if it plays, else a P2P row.
        // Through Debrid: the row of the release with a URL, a cached one
        // first (mel_play checks it). One not cached only if the check finds
        // a stream: else maybe a placeholder, as before. Any episode in the
        // file: the reply is of the episode watched; the next one: only with it.
        $mode = $resume['mode'];
        $tag = "resume: release {$resume['hash']} mode=" . ($mode !== '' ? $mode : 'none');
        $pick = $resume['next'] ? mel_release_pick($st['rows'], $resume['hash'], $s, $e, mel_cin_n($mv['cin'], $s, $e)) :
            mel_release_pick($st['rows'], $resume['hash'], -1, -1);
        $deb = $mode !== 'ts' && $pick['deb'] >= 0 ? $st['rows'][$pick['deb']] : null;
        $pre = null;
        if ($deb && $deb['cached'] !== true)
        {
            $pre = mel_deb_precheck($deb);
            if ($pre['class'] !== 'stream')
            {
                mel_log("$tag: not cached, no stream from Debrid");
                $deb = null;
            }
        }
        if ($deb)
        {
            $a = mel_play($st, $deb, $resume['pos'], null, false, $pre);
            // Noted as a start of the list (no screen under the player): the season of the playlist for its lazy
            // episodes (mel_lazy_url).
            if ($a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
                mel_playing('', $st, $pick['deb']);
            return $a;
        }
        // Through TorrServer, only if it answers; else the list. Mode ts: a P2P
        // row first, else any of the release; no mode: a P2P row only; debrid:
        // none. No torrents: the list, not a word to TorrServer.
        $ts = $mode === 'ts' ? $pick['ts'] : ($mode === 'debrid' ? -1 : $pick['p2p']);
        $off = Mel::$settings['no_torrents'];
        if ($ts >= 0 && !$off && mel_ts_alive())
        {
            mel_log("$tag -> TorrServer");
            return mel_ts_open($st, $ts, $resume['pos'], '', false, true, $resume['next']);
        }
        mel_log("$tag: " . ($pick['rows'] === 0 ? 'not found' : ($off && $mode !== 'debrid' ? 'no torrents' :
            ($ts < 0 ? 'no way in this mode' : 'TorrServer does not answer'))) . ', showing the list');
    }
    return mel_open_list($hash !== '' ? mel_list_cursor($st, $hash) : $st);
}

// List $st with the cursor on release $hash (mel_release_row), logged.
/**
 * @param MelState $st
 * @param string $hash
 * @return MelState
 */
function mel_list_cursor($st, $hash)
{
    list($sel, $by) = mel_release_row($st['rows'], $hash, $st['s'], $st['e'], mel_cin_n($st['movie']['cin'], $st['s'], $st['e']));
    mel_log("list: cursor on row $sel of " . count($st['rows']) . ", $by");
    return mel_gc_put_cursor($st, $sel, null);
}

// Opens the list of $st.
function mel_open_list($st)
{
    Mel::$state = $st;
    if (MEL_JACRED)
    {
        $st['rows'] = mel_jacred($st['rows'], $st['movie'], $st['s'], $st['e']);
        Mel::$state = $st;
    }

    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "streams:{$st['rid']}",
            PluginOpenFolderActionData::caption => mel_ep_name($st['movie'], $st['s'], $st['e'])));
}

// --- Texts, actions and dialogs.

// Texts of translations/ for our own dialogs (%tr% there is not proven):
// ru or English.
function mel_tr($lang, $key, $ep = '')
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

// The text of the field "Сверка" of a row of the tier: its reason $why (mel_tier_why) for a
// series ($series) or a film, in the language $lang. The years when the server gave both
// (the years of a series as "2022–2024"); "date" and "dub" by the series too; a code Melange does not
// know: the general text.
/**
 * @param MelTierWhy $why
 * @param bool $series
 * @param string $lang
 * @return string
 */
function mel_tier_text($why, $series, $lang)
{
    $c = $why['code'];
    if ($c === 'year' && $why['ry'] !== null && $why['ty'] !== null)
        return str_replace(array('{r}', '{t}'), array(strval($why['ry']), $why['ty'] .
            ($why['te'] !== null && $why['te'] > $why['ty'] ? '–' . $why['te'] : '')),
            mel_tr($lang, $series ? 'tier_why_year_series' : 'tier_why_year_film'));
    if ($c === 'date' || $c === 'dub')
        return mel_tr($lang, "tier_why_$c" . ($series ? '_series' : ''));
    return mel_tr($lang, in_array($c, array('year', 'title'), true) ? "tier_why_$c" : 'tier_why_other');
}

// handle_user_input of our plugin, the shell waiting $timeout ms for it (60 s;
// more: the import of the Favorites of the Dune); "Please wait" after $delay
// ms (0: at once, a request to the internet for sure: the quick setup).
function mel_input($control, $params = array(), $delay = MEL_DELAY_INPUT, $timeout = 60000)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginHandleUserInputActionData::operation_timeout => $timeout,
            PluginHandleUserInputActionData::show_dialog_delay => $delay),
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => $control), $params));
}

function mel_close_and($post)
{
    return array(
        GuiAction::handler_string_id => CLOSE_DIALOG_AND_RUN_ACTION_ID,
        GuiAction::data => array(CloseDialogAndRunActionData::post_action => $post));
}

// The dialog over the player closed, the player stopped (waited for), then $post (null: nothing): "Stop" and "Choose a
// release" of the dialogs of a lazy episode. A list over a player waiting for the URL of the episode takes back to that
// player on RETURN - so the stop first.
function mel_stop_and($post)
{
    return mel_close_and(array(
        GuiAction::handler_string_id => STOP_PLAYBACK_ACTION_ID,
        GuiAction::data => array(
            StopPlaybackActionData::wait_for_completion => true,
            StopPlaybackActionData::post_action => $post)));
}

// Dialog over the stopped player or a screen: text lines and buttons
// (caption => action, named btn0, btn1, ...).
function mel_dialog($title, $lines, $buttons)
{
    $defs = array();
    foreach ($lines as $l)
        $defs[] = mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l));
    $n = 0;
    foreach ($buttons as $cap => $act)
        $defs[] = mel_ctl('btn' . $n++, GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => $cap,
            GuiButtonDef::width => 500, GuiButtonDef::push_action => $act));
    return mel_dialog_act($title, $defs);
}

// show_dialog of controls $defs; $more: fields of the dialog over the usual
// ones (width, timer).
function mel_dialog_act($title, $defs, $more = array())
{
    return array(
        GuiAction::handler_string_id => SHOW_DIALOG_ACTION_ID,
        GuiAction::data => array_merge(array(
            ShowDialogActionData::title => $title,
            ShowDialogActionData::defs => $defs,
            ShowDialogActionData::close_by_return => true,
            ShowDialogActionData::preferred_width => 1100), $more));
}

// The window of the reasons: up to MEL_ERRORS_MAX + 1 rows do not fit a dialog of
// labels (they get no focus, no key scrolls them), so left-aligned buttons of plain
// look, as "C" of knowledge/platform/dialog-scroll.md: ENTER on a row, OK and
// RETURN close it. A caption of smart_label is markup: without <text> the row is
// empty, a bare "<" empties it too.
/**
 * @param string $title
 * @param string $lang
 * @param MelErrors $errors
 * @return MelAction
 */
function mel_errors_dialog($title, $lang, $errors)
{
    $close = mel_close_and(null);
    $defs = array();
    foreach (mel_errors_rows($lang, $errors, MEL_ERRORS_MAX) as $i => $row)
        $defs[] = mel_row_button("row$i", $row, $close);
    $defs[] = mel_ctl('ok', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => 'OK',
        GuiButtonDef::width => 500, GuiButtonDef::push_action => $close));
    return mel_rows_dialog($title, $defs);
}

// Characters of a row of a window of rows (mel_rows_dialog: the reasons, the voices of the online list), its own
// ellipsis included: the shell cuts a longer button at the right without one. A button MEL_ROW_WIDTH wide shows ~75
// of a voice (19 px a character, experiments/long_menu_probe F); 68 leaves room for wide capitals, the end of a row
// (" · сейчас") stays.
define('MEL_ROW_LINE', 68);
// The width of a row of a window of rows, px.
define('MEL_ROW_WIDTH', 1450);

// A window of rows $defs (mel_row_button; a button OK after them, maybe) with title $title: 1500 px wide, scrolled
// (max_height, P+/P- by 11 rows), the cursor on row $sel (null: the shell's, the first).
/**
 * @param string $title
 * @param list<array<string, mixed>> $defs
 * @param int|null $sel
 * @return MelAction
 */
function mel_rows_dialog($title, $defs, $sel = null)
{
    $more = array(ShowDialogActionData::preferred_width => 1500, ShowDialogActionData::max_height => 875);
    if ($sel !== null)
        $more[ShowDialogActionData::initial_sel_ndx] = $sel;
    $more[ShowDialogActionData::params] = array('page_size' => 11);
    return mel_dialog_act($title, $defs, $more);
}

// A row of a window of rows (mel_rows_dialog): a button MEL_ROW_WIDTH wide of plain look, its text left-aligned,
// $text escaped for its markup.
/**
 * @param string $name
 * @param string $text
 * @param MelAction $act
 * @return array<string, mixed>
 */
function mel_row_button($name, $text, $act)
{
    $c = mel_ctl($name, GUI_CONTROL_BUTTON, array(
        GuiButtonDef::caption => '<text>' . str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $text) . '</text>',
        GuiButtonDef::width => MEL_ROW_WIDTH, GuiButtonDef::push_action => $act));
    $c[GuiControlDef::params] = array('button_caption_centered' => 'no', 'button_style' => 'smart_label', 'button_height' => 44);
    return $c;
}

function mel_ctl($name, $kind, $def, $title = null)
{
    return array(GuiControlDef::name => $name, GuiControlDef::title => $title,
        GuiControlDef::kind => $kind, GuiControlDef::specific_def => $def,
        GuiControlDef::params => null);
}

// --- Dispatcher.

function mel_handle_user_input($in)
{
    $handler = isset($in->handler_id) ? $in->handler_id : null;
    $control = isset($in->control_id) ? $in->control_id : null;
    if ($handler !== 'aio')
        return null;
    mel_mlv_input($control);
    // The colour keys of every screen, before the screen's own work (egg.php): a run of them plays the video and
    // puts back the sort of new lists from before the run; else (RuTube failed too) the key goes on, in a list it sorts.
    $egg = mel_egg_input($in, $control);
    if ($egg !== null)
        return $egg;
    if ($control === 'egg')
        return null;
    if ($control === 'play')
        return mel_play_action($in);
    // "Melange" of "Play..." of the card of the Dune (the supplier aicard): the own card (deep_link.php).
    if ($control === 'card')
        return mel_sup_card($in);
    if ($control === 'tv_update')
        return mel_tv_update($in);
    if ($control === 'tv_timer')
        return mel_tv_timer($in);
    if (is_string($control) && strpos($control, 'card_') === 0)
        return mel_oc_input($in, $control);
    if (is_string($control) && strpos($control, 'person_') === 0)
        return mel_pp_input($in, $control);
    if ($control === 'open')
        return mel_dl_open($in);
    if ($control === 'gc_move' || $control === 'gc_pick' || $control === 'gc_info')
        return mel_gc_input($in);
    if ($control === 'kp_key')
        return mel_kp_input($in);
    if ($control === 'lw_key')
        return mel_lib_win_input($in);
    if ($control === 'dlg_key' || $control === 'wt_key' || $control === 'wt_tick')
        return mel_dlg_input($in, $control);
    if ($control === 'ks_key' || $control === 'ks_tick')
        return mel_ks_input($in, $control);
    if ($control === 'info_page')
        return mel_info_input($in);
    if ($control === 'next')
        return mel_next($in);
    if ($control === 'next_pick')
        return mel_next_pick($in);
    if ($control === 'lazy_reply')
        return mel_lazy_replied($in);
    if ($control === 'lib_next')
        return mel_lib_next($in);
    if ($control === 'lib_next_pick')
        return mel_lib_next_pick($in);
    if ($control === 'pre_via')
        return mel_pre_via($in);
    if ($control === 'pre_twin')
        return mel_pre_twin_go($in);
    if ($control === 'finish')
        return mel_finish($in);
    if ($control === 'gc_flip')
        return mel_flip($in);
    if ($control === 'gc_refresh')
        return mel_refresh($in);
    if ($control === 'gc_sort')
        return mel_gc_sort($in);
    if ($control === 'gc_main')
        return mel_gc_main($in);
    if ($control === 'gc_sort_menu')
        return mel_gc_sort_menu($in);
    if (is_string($control) && strpos($control, 'lp_') === 0)
        return mel_lpv_input($in, $control);
    if ($control === 'gc_src_menu')
        return mel_gc_src_menu($in);
    if ($control === 'gc_src')
        return mel_gc_src($in);
    if ($control === 'gc_choose')
        return mel_choose($in);
    if ($control === 'gc_chosen')
        return mel_chosen($in);
    if ($control === 'gc_menu')
        return mel_gc_menu($in);
    if ($control === 'gc_errors')
        return mel_gc_errors($in);
    if ($control === 'gc_dl')
        return mel_download($in);
    if ($control === 'gc_srv')
        return mel_srv_menu($in);
    if ($control === 'gc_push')
        return mel_srv_push($in);
    if ($control === 'gc_ts')
        return mel_ts_menu($in);
    if ($control === 'ts_tick')
        return mel_ts_tick($in);
    if ($control === 'tsl_recent')
        return mel_tsl_recent($in);
    if ($control === 'tsl_tick')
        return mel_tsl_tick($in);
    if ($control === 'tsf_move' || $control === 'tsf_pick' || $control === 'tsf_finish')
        return mel_tsf_input($in, $control);
    if (in_array($control, array('tsi_open', 'tsi_tick', 'tsi_close', 'tsi_state'), true))
        return mel_tsi_input($in, $control);
    if ($control === 'ts_cancel')
    {
        mel_log('ts: waiting cancelled');
        return mel_close_and(null);
    }
    if (is_string($control) && strpos($control, 'setup') === 0)
        return mel_setup_input($in, $control);
    if (is_string($control) && strpos($control, 'cat_') === 0)
        return mel_catv_input($in, $control);
    if ($control === 'next_stop')
        return mel_stop_and(null);
    return null;
}

class MelFw extends DunePluginFw
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
            Mel::$t0 = microtime(true);
            // Every focus move on the rows: memory only.
            if (!isset($in->control_id) || $in->control_id !== 'cat_info')
            {
                mel_settings_load();
                mel_op_of_screen($in);
                mel_tv_settings_seen($in);
                mel_catv_filter_seen($in);
            }
            $op = $ctx->op_type_code;
            if ($op === PLUGIN_OP_GET_FOLDER_VIEW)
            {
                $type = PLUGIN_OUT_DATA_PLUGIN_FOLDER_VIEW;
                $url = isset($in->media_url) && is_string($in->media_url) ? $in->media_url : '';
                // Every screen of Melange: the keys Fav 2 and Fav 1 to "Мой список" and "Продолжить", the search key to
                // the search of Melange (view_my_list.php).
                $data = mel_mlv_keys(mel_folder_view($url, isset($in->sel_state) ? $in->sel_state : null), $url);
            }
            else if ($op === PLUGIN_OP_HANDLE_USER_INPUT)
            {
                $type = PLUGIN_OUT_DATA_GUI_ACTION;
                $data = mel_handle_user_input($in);
                // "next", "lp_next" and "lib_next", a lazy episode under the player (mel_lazy_action): its vod_play
                // not sooner than MEL_NEXT_MIN (mel_lazy_hold; "lazy_reply" is held already: its reply kept by
                // mel_lazy_url, anew by mel_lazy_replied); no pf_check of them - the player is on, pf_check passed with
                // its first start in this session, run_builtin from there not seen on the device.
                $c = isset($in->control_id) ? $in->control_id : '';
                if (in_array($c, array('next', 'lp_next', 'lib_next'), true))
                    $data = mel_lazy_hold($data);
                else if ($c !== 'lazy_reply')
                    $data = mel_pf_check($data);
            }
            else if ($op === PLUGIN_OP_GET_REGULAR_FOLDER_ITEMS)
            {
                $type = PLUGIN_OUT_DATA_PLUGIN_REGULAR_FOLDER_RANGE;
                $data = mel_catv_range_op(isset($in->media_url) && is_string($in->media_url) ? $in->media_url : '',
                    max(0, mel_int($in, 'from_ndx', 0)));
            }
            // r25 only; the type a literal, the r24 API has no such constant.
            else if (defined('PLUGIN_OP_GET_FOLDER_ROWS') && $op === PLUGIN_OP_GET_FOLDER_ROWS)
            {
                $type = 'plugin_folder_rows';
                $data = mel_catv_rows_op($in);
            }
            // Lazy episodes: media_url comes to get_vod_stream_url, the whole
            // input (playback_url) to get_vod_stream_info. The feature vod_get_stream_info of the manifest has the
            // shell ask get_vod_stream_info (r25 seen; r22 has neither): it carries the markers of the episode. From
            // MEL_FW_NEXT_URL_R the episode itself (mel_lazy_url): the URL for get_vod_stream_url, plugin_stream_info
            // {url, markers if any} for get_vod_stream_info - its literals, none in the r22 API; else error_action.
            else if ($op === PLUGIN_OP_GET_VOD_STREAM_URL || $op === 'get_vod_stream_info')
            {
                $a = $op === PLUGIN_OP_GET_VOD_STREAM_URL ? 'media_url' : 'playback_url';
                $b = $op === PLUGIN_OP_GET_VOD_STREAM_URL ? 'playback_url' : 'media_url';
                $r = mel_lazy_action(isset($in->$a) ? $in->$a : (isset($in->$b) ? $in->$b : null), $op);
                if (isset($r['url']) && $op === PLUGIN_OP_GET_VOD_STREAM_URL)
                {
                    $type = PLUGIN_OUT_DATA_URL;
                    $data = $r['url'];
                }
                else if (isset($r['url']))
                {
                    $type = 'plugin_stream_info';
                    $data = $r;
                }
                else
                    $error_action = $r;
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

DunePluginFw::$instance = new MelFw();
