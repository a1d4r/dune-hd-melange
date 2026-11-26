<?php
// The pairs card of the Dune (movie_id) -> IMDb / TMDB for the badges of the history (progress.php): kept in a
// file of data_dir; found by play_action, ENTER, the cards the shell keeps and, lazily, a request of the card
// to the dunemdb API: r24 in an opening of the catalog, r25 at the tick of the timer of its screen (mel_pg_tick); the
// lazy meta of the row "Continue" after them (cp_meta.php, mel_cpm_step). The titles of the media library get theirs
// by the cards of the shell too, at the same moments: by a pass in a process of its own (links_bg.php,
// mel_pg_lib_work), else step by step (mel_pg_lib_step): the badge "on the disk" of a tile.
// Needs library.php (mel_lib_index, MelLib), progress.php (mel_pg_index), main.php (mel_log, mel_write_file,
// mel_data_dir, mel_http_get, mel_http_get_many, MEL_DUNE_ID, MEL_HTTP_TOO_BIG), parse.php (mel_str), catalog.php
// (MEL_CAT_MAX_BYTES), catalog_card.php (mel_card_token, mel_card_api), view_catalog.php (mel_catv_is_down).
// Its top level is only define and classes: the pass in a process of its own (php-cgi, bg/lib_map.php) loads it for
// the functions of the cards (mel_pg_shell_text, mel_pg_lib_pair) with parse.php only (mel_str), no DuneSystem there.

// The cards the shell keeps (movies/storage_helper.php: /flashdata, /persistfs on old models): PHP of the
// Dune adds FS_PREFIX itself. Tests may define them first.
if (!defined('MEL_PG_SHELL_DIR'))
    define('MEL_PG_SHELL_DIR', '/flashdata/dunemdb_cache/movies');
if (!defined('MEL_PG_SHELL_DIR2'))
    define('MEL_PG_SHELL_DIR2', '/persistfs/dunemdb_cache/movies');
// A bigger card of the shell is not read (seen up to ~840 KB).
define('MEL_PG_SHELL_MAX_BYTES', 2000000);
// ms of an opening for the cards of the shell; one card is read even past it.
define('MEL_PG_SHELL_MS', 150);
// ms of an opening for the cards of the shell of the titles of the library (mel_pg_lib_step), its index read too; one
// card even past it. r25: s of a tick for them at most, of what the pairs and the meta left of MEL_CPM_TICK_S.
define('MEL_PG_LIB_MS', 200);
define('MEL_PG_LIB_TICK_S', 0.4);
// The ids of a card of the dunemdb API in its raw text: "sources" with "auxSources" after it, not of a person or a
// supplier ("type":N before them); either may be an empty [].
define('MEL_PG_SRC_RE', '/(?<!"type":\d,)(?<!"type":\d\d,)"sources":(\{[^{}]*+\}|\[\]),"auxSources":(\{[^{}]*+\}|\[\])/');
// The pairs, in data_dir.
define('MEL_PG_LINKS_FILE', 'wh_links.json');
// A failed request is asked again after this many s (no reply of the server: MEL_PG_PAUSE_S); "no pair" stays.
define('MEL_PG_FAIL_S', 86400);
// No connection, a timeout or 5xx: no request for this many s (memory of php_server).
define('MEL_PG_PAUSE_S', 1800);
// A record that ended longer ago is not linked by a request.
define('MEL_PG_LINK_AGE_S', 31536000);
// Requests of an opening, the time of them all, s; the second one only with LINK_NEXT_S left.
define('MEL_PG_LINK_N', 2);
define('MEL_PG_LINK_S', 3.0);
define('MEL_PG_LINK_NEXT_S', 1.5);
define('MEL_PG_LINK_CONNECT', 2);
// r25: the first tick of the timer of the main screen of rows (mel_pg_tick_set) this long after its opening: the
// rows of the window of the shell are asked by then, the step does not hold them.
define('MEL_PG_TICK_MS', 2500);
// r25: the timer again this long after a tick with more to ask (mel_pg_tick_again); ticks of an opening at most (the
// titles that fail are out of the queues, a slow one is asked again).
define('MEL_PG_TICK_AGAIN_MS', 3000);
define('MEL_PG_TICK_MAX', 10);
// r25: a tick this soon after a reply of get_folder_rows comes between the rows of a cold opening (the window of
// the shell still loading, longer than MEL_PG_TICK_MS): no step of that opening.
define('MEL_PG_ROWS_QUIET_S', 0.5);
// r25: a tick of the main screen this soon after the end of a tick of a screen of Fav (MelMlv::$tick_t; its timer may
// tick over the main one): no step, the timer again, not one of the ticks of the opening (MEL_PG_TICK_MAX).
define('MEL_PG_FAV_QUIET_S', 3.0);
// r25: a view of the main screen within this many s of a tick of it that got the meta of tiles (mel_pg_tick_anew: the
// shell builds it again at once) is of that tick, not an opening.
define('MEL_PG_ANEW_S', 5.0);
// A reply of the API this small is decoded whole when its code is not before "result".
define('MEL_PG_HEAD_MAX', 65536);
// Episodes of a season the pairs and their parse take at most.
define('MEL_PG_EPS_MAX', 5000);

class MelPgLinks
{
    // The pairs by movie_id (mel_pg_links; null: not read yet) and the reverse of them: key => movie_ids.
    /** @var array<string, MelPgLink>|null */
    public static $links = null;
    /** @var array<string, list<string>> */
    public static $rev = array();
    // movie_ids whose card of the shell was looked at since the start of php_server.
    /** @var array<string, bool> */
    public static $shell_seen = array();
    // movie_ids of the library whose card of the shell gave no pair (no card, no ids; mel_pg_lib_step) => when; looked
    // at again after MEL_PG_FAIL_S or when the index changes (MelLib::$sig, the one of them: $lib_sig).
    /** @var array<string, int> */
    public static $lib_seen = array();
    /** @var string|null */
    public static $lib_sig = null;
    // No request before this time (mel_pg_link_now: no connection, 5xx); 0: none.
    /** @var int */
    public static $pause = 0;
    // The last step found no dune_auth (logged once).
    /** @var bool */
    public static $no_auth = false;
    // r25: the main screen of rows whose timer is set for the lazy step (mel_pg_tick_set, mel_pg_tick_again); '': none,
    // or its tick came.
    /** @var string */
    public static $tick_url = '';
    // r25: the main screen of rows opened last ('': none since the start of php_server), the ticks of its opening and
    // why no more of them ('': not known yet); when get_folder_rows last answered (microtime; 0: never). For mel_pg_tick.
    /** @var string */
    public static $screen = '';
    /** @var int */
    public static $ticks = 0;
    /** @var string */
    public static $stop = '';
    // r25: the main screen the last tick built again ('': none; mel_pg_tick_anew) and when (microtime).
    /** @var string */
    public static $anew = '';
    /** @var float */
    public static $anew_t = 0.0;
    /** @var float */
    public static $rows_t = 0.0;
}

// --- When to link: an opening of the rows of the catalog.

// A screen of rows of the catalog opens: new cards of the history paired by the cards of the shell (before the
// badges of its tiles); the timer of the screen before it is no more for the step, nor its first row to refresh.
/**
 * @return void
 */
function mel_pg_screen_start()
{
    mel_pg_shell_step();
    mel_pg_lib_work(MEL_PG_LIB_MS);
    MelPgLinks::$tick_url = '';
    MelPgLinks::$screen = '';
    MelPgLinks::$ticks = 0;
    MelPgLinks::$stop = '';
    MelCat::$first_due = null;
    MelCat::$first_tries = 0;
}

// r25: a screen of Melange $url is asked of get_folder_view (mel_folder_view): another than the main screen of the
// timer - no more ticks of it (a tick under that screen would hold php_server), a line. Not a screen the tick of a
// screen of Fav invalidated, within the window of it (mel_mlv_inv: the shell builds them again, the main one under it
// with its timer; its ticks wait for those of that screen, MEL_PG_FAV_QUIET_S). Another screen than the one a tick built
// again (MelPgLinks::$anew): that one no more of it.
/**
 * @param string $url
 * @return void
 */
function mel_pg_view_asked($url)
{
    if ($url !== MelPgLinks::$anew)
        MelPgLinks::$anew = '';
    if (MelPgLinks::$tick_url === '' || $url === MelPgLinks::$tick_url || mel_mlv_inv($url) !== '')
        return;
    MelPgLinks::$tick_url = '';
    MelPgLinks::$stop = 'the screen left';
    mel_log(sprintf('link: tick %d/%d: no more, the screen left', MelPgLinks::$ticks, MEL_PG_TICK_MAX));
}

// r25: get_folder_rows answers now (mel_pg_tick: rows still loading).
/**
 * @return void
 */
function mel_pg_rows_answered()
{
    MelPgLinks::$rows_t = microtime(true);
}

// r24: the screen of tab $tab is made, $left s of its opening left, $net: rows of it went to the server in it (old or
// no copies). The lazy pairs now, the tiles of its row "Continue" first (mel_cp_row_mids), then the lazy meta of that
// row (mel_cpm_step) in what is left of the time of the step, only with $net: an opening of rows of memory or the
// cache stays at once.
/**
 * @param float $left
 * @param bool $net
 * @param string $tab
 * @return void
 */
function mel_pg_screen_built($left, $net, $tab = '')
{
    if (!$net || $left < 1)
        return;
    $t = microtime(true);
    $limit = min($left, MEL_PG_LINK_S);
    $rounds = 0;
    $pairs = 0;
    mel_pg_link_step($limit, false, $rounds, $pairs, null, mel_cp_row_mids($tab));
    mel_cpm_step($limit - (microtime(true) - $t), MEL_CPM_N - $rounds);
}

// r25: the main screen of rows $url is made: the step goes to the ticks of its timer (MEL_PG_TICK_MS, then again while
// there is more to ask: mel_pg_tick_again), after the rows of the window of the shell (get_folder_rows), as php_server
// answers one call at a time. A timer with more to ask (mel_pg_tick_why); a line of the log when a pause or the
// catalog server down holds it, of no dune_auth once till it comes (MelPgLinks::$no_auth, as mel_pg_token). Back to
// the screen with no get_folder_view: no timer, no step. Built again by a tick of it ($anew, mel_pg_anew): the ticks of
// the opening go on - the timer under MEL_PG_TICK_MAX of them with more to ask, a line as of mel_pg_tick_again. -> the
// timer is set.
/**
 * @param string $url
 * @param bool $anew
 * @return bool
 */
function mel_pg_tick_set($url, $anew = false)
{
    MelPgLinks::$screen = $url;
    if ($anew)
    {
        $n = MelPgLinks::$ticks;
        $why = $n >= MEL_PG_TICK_MAX ? 'limit' : mel_pg_tick_why();
        MelPgLinks::$tick_url = $why === '' ? $url : '';
        if ($why !== '')
            MelPgLinks::$stop = $why;
        mel_log(sprintf('link: tick %d/%d: the screen anew, %s', $n, MEL_PG_TICK_MAX,
            $why === '' ? 'again in ' . MEL_PG_TICK_MS . ' ms' : "no more, $why"));
        return $why === '';
    }
    $why = mel_pg_tick_why();
    if ($why === 'pause')
        mel_log(sprintf('link: no timer: a pause of the requests, %d s left', max(mel_pg_paused(), mel_cpm_paused())));
    else if ($why === 'server down')
        mel_log('link: no timer: the catalog server down');
    else if ($why === 'no dune_auth' && !MelPgLinks::$no_auth)
        mel_log('link: no timer: no dune_auth');
    if ($why === 'no dune_auth')
        MelPgLinks::$no_auth = true;
    else if ($why === '' && mel_card_token() !== '')
        MelPgLinks::$no_auth = false;
    if ($why !== '')
        return false;
    MelPgLinks::$tick_url = $url;
    return true;
}

// r25: a tick of the timer of the main screen $url, MEL_CPM_TICK_S at most (a key pressed now waits for it): first the
// first row of an old copy (mel_pg_first_step, a request of 1 s at most; tiles other than on the screen: the screen built
// again, as of the meta below); then the step (mel_pg_link_step) once in what is left, of one batch at once, else of
// one request (~0.5 s; its timeout 1 s, whole s of curl); then the lazy meta of the row "Continue" (mel_cpm_step) in
// what is left of it: one batch at once, else MEL_CPM_N requests of the tick at most one by one; then the timer again
// or not (mel_pg_tick_again). No step: within MEL_PG_ROWS_QUIET_S of a reply of get_folder_rows (the rows of a cold
// opening wait for php_server; the timer again), within MEL_PG_FAV_QUIET_S of the end of a tick of a screen of Fav (the
// main one under it or just after Back: the timer again, not one of MEL_PG_TICK_MAX; no two timers asking at once), a
// tick after the last one of the screen, a tick of another or an old one, a tick with no screen since the start of
// php_server. A line of the log of each, in its own words. The tiles of the row "Continue" of the screen paired first (mel_cp_row_mids); the titles of the library
// last, in what is left (mel_pg_lib_work: the pass, else the step of MEL_PG_LIB_TICK_S at most). Cards paired: the tiles of that row with no
// meta again (MelCpm::$miss, mel_cp_row_miss; the row shown, MelCat::$own), theirs asked in the same tick. The meta of a tile of the screen got
// (MelCpm::$miss, MelCpm::$ml; not of MelCpm::$more, a screen of Fav): the screen built again (mel_pg_tick_anew), no
// timer again here. -> the timer again (mel_catv_input: change_behaviour).
/**
 * @param string $url
 * @return bool
 */
function mel_pg_tick($url)
{
    MelPgLinks::$anew = '';
    if ($url === '' || MelPgLinks::$tick_url !== $url)
    {
        if (MelPgLinks::$screen === '')
            $why = 'no screen since the start of php_server';
        else if ($url !== MelPgLinks::$screen)
            $why = 'a tick of another or an old screen';
        else
            $why = MelPgLinks::$stop !== '' ? 'no more ticks, ' . MelPgLinks::$stop : 'no timer on this screen';
        mel_log("link: tick: no step, $why");
        return false;
    }
    $ago = microtime(true) - MelMlv::$tick_t;
    if (MelMlv::$ticked !== '' && $ago >= 0 && $ago < MEL_PG_FAV_QUIET_S)
    {
        mel_log('link: tick: no step, after a tick of ' . MelMlv::$ticked);
        return mel_pg_tick_again($url);
    }
    MelPgLinks::$tick_url = '';
    MelPgLinks::$ticks++;
    $ago = microtime(true) - MelPgLinks::$rows_t;
    if (MelPgLinks::$rows_t > 0 && $ago >= 0 && $ago < MEL_PG_ROWS_QUIET_S)
        mel_log('link: tick: no step, rows are loading');
    else
    {
        $t = microtime(true);
        // A step of a request of up to 1 s (the first row) goes first; another of that weight would wait for the next
        // tick when this one asked ($first !== ''), or a key pressed now waits past MEL_CPM_TICK_S.
        $first = mel_pg_first_step();
        $rounds = 0;
        $pairs = 0;
        $tab = mel_catv_tab($url);
        mel_pg_link_step(min(MEL_PG_LINK_S, MEL_CPM_TICK_S - (microtime(true) - $t)), true, $rounds, $pairs, null,
            mel_cp_row_mids($tab));
        if ($pairs && in_array('@continue', MelCat::$own, true))
            MelCpm::$miss = mel_cp_row_miss($tab);
        $mine = mel_cpm_queue(array_merge(MelCpm::$miss, MelCpm::$ml));
        $got = mel_cpm_step(MEL_CPM_TICK_S - (microtime(true) - $t), MEL_CPM_N - $rounds, true);
        $left = min(MEL_PG_LIB_TICK_S, MEL_CPM_TICK_S - (microtime(true) - $t));
        if ($left > 0)
            mel_pg_lib_work($left * 1000);
        $c = $got ? mel_cpm_read() : array();
        $shown = 0;
        foreach ($got ? $mine : array() as $m)
            $shown += mel_cpm_tile($m[0], $m[1], $m[2], $c) ? 1 : 0;
        if ($shown || $first === 'changed')
            return mel_pg_tick_anew($url, $shown);
    }
    return mel_pg_tick_again($url);
}

// r25: the step of a tick for the first row of the main screen built of an old copy (MelCat::$first_due): its first page
// asked (mel_catv_load_first in MEL_CATV_FIRST_TICK_S; it saves the copy), the ids of the tiles of the row on the screen
// (MEL_CATV_ROW_TILES) before and after. The same: the memory fresh, the screen as it is. Others: the screen to build
// again (the caller), its first row of memory with no request; the tiles before for ENTER and the panel till then
// (MelCat::$on_screen). A failure: the copy stays, asked again at the next tick, MEL_CATV_FIRST_TRIES an opening at
// most. The server down: none asked, no more. A line of the log. -> '': none due, 'same', 'changed', 'failed', 'down'.
/**
 * @return string
 */
function mel_pg_first_step()
{
    $row = MelCat::$first_due;
    if ($row === null)
        return '';
    if (mel_catv_is_down())
    {
        MelCat::$first_due = null;
        mel_log('catalog: first row: tick: no request, the server down; the copy stays');
        return 'down';
    }
    $rid = mel_catv_rid($row);
    $was = isset(MelCat::$lists[$rid]) ? MelCat::$lists[$rid]['tiles'] : array();
    $t = microtime(true);
    $res = mel_catv_load_first($row, MEL_CATV_FIRST_TICK_S, false);
    $n = ++MelCat::$first_tries;
    $ms = (microtime(true) - $t) * 1000;
    if ($res['err'] !== '')
    {
        $again = $n < MEL_CATV_FIRST_TRIES && !mel_cat_err_down($res['err']);
        if (!$again)
            MelCat::$first_due = null;
        mel_log(sprintf('catalog: first row: tick %d/%d: failed, %s, %.0f ms; the copy stays%s', $n, MEL_CATV_FIRST_TRIES,
            $res['err'], $ms, $again ? ', again at the next tick' : ''));
        return 'failed';
    }
    MelCat::$first_due = null;
    $now = isset(MelCat::$lists[$rid]) ? MelCat::$lists[$rid]['tiles'] : array();
    $same = array_keys(array_slice($was, 0, MEL_CATV_ROW_TILES, true)) === array_keys(array_slice($now, 0, MEL_CATV_ROW_TILES, true));
    if (!$same)
        MelCat::$on_screen[$rid] = $was;
    mel_log(sprintf('catalog: first row: tick %d/%d: %s, %.0f ms', $n, MEL_CATV_FIRST_TRIES,
        $same ? 'same' : 'changed, the screen anew', $ms));
    return $same ? 'same' : 'changed';
}

// r25: a tick of the main screen $url got the meta of $got tiles: the screen built again with them (mel_catv_input:
// invalidate of it, the focus by sel_state - device r25), its view the one of this tick (mel_pg_anew; its timer
// mel_pg_tick_set); a line. -> false: no timer again here.
/**
 * @param string $url
 * @param int $got
 * @return bool
 */
function mel_pg_tick_anew($url, $got)
{
    MelPgLinks::$anew = $url;
    MelPgLinks::$anew_t = microtime(true);
    mel_log(sprintf('link: tick %d/%d: %d got, the screen anew', MelPgLinks::$ticks, MEL_PG_TICK_MAX, $got));
    return false;
}

// r25: main screen $url is the one the last tick built again (mel_pg_tick_anew) within MEL_PG_ANEW_S of it.
/**
 * @param string $url
 * @return bool
 */
function mel_pg_anew($url)
{
    $ago = microtime(true) - MelPgLinks::$anew_t;
    return $url !== '' && MelPgLinks::$anew === $url && $ago >= 0 && $ago < MEL_PG_ANEW_S;
}

// r25: main screen $url is being built: is it the one the last tick built again (mel_pg_anew)? That is no more after
// it, whatever the view gives (a message, no rows): the next view of it an opening.
/**
 * @param string $url
 * @return bool
 */
function mel_pg_anew_take($url)
{
    $anew = mel_pg_anew($url);
    MelPgLinks::$anew = '';
    return $anew;
}

// r25: after a tick of the main screen $url: the timer again (MEL_PG_TICK_AGAIN_MS) with more to ask
// (mel_pg_tick_why), under MEL_PG_TICK_MAX ticks of the opening; a line of the log: the tick of the opening, again or
// why no more (limit, pause, server down, no dune_auth, queue empty). -> the timer again.
/**
 * @param string $url
 * @return bool
 */
function mel_pg_tick_again($url)
{
    $n = MelPgLinks::$ticks;
    $why = $n >= MEL_PG_TICK_MAX ? 'limit' : mel_pg_tick_why();
    if ($why === '')
    {
        MelPgLinks::$tick_url = $url;
        mel_log(sprintf('link: tick %d/%d: again in %d ms', $n, MEL_PG_TICK_MAX, MEL_PG_TICK_AGAIN_MS));
        return true;
    }
    MelPgLinks::$stop = $why;
    mel_log(sprintf('link: tick %d/%d: no more, %s', $n, MEL_PG_TICK_MAX, $why));
    return false;
}

// r25: why a tick would ask nothing (mel_pg_tick_set, mel_pg_tick_again; of the screen "Продолжить" or "Мой список":
// its tiles $titles, its cards $mids) -> '': of the main screen its first row of an old copy to refresh
// (MelCat::$first_due) with the catalog server not down, or a meta of the row "Continue" to ask (mel_cpm_todo) with no
// pause of it and the catalog server not down, or a card to ask (mel_pg_link_queue) with no pause and dune_auth, or of the main
// screen a card of the shell of a title of the library to read (mel_pg_lib_queue); else 'pause'
// (of either queue), 'server down' (the catalog server, of the meta), 'no dune_auth', 'queue empty'. No arguments
// ($titles and $mids null): the main screen (mel_pg_tick_again), the only one whose ticks the library step keeps.
/**
 * @param list<array{string, string, string}>|null $titles
 * @param list<string>|null $mids
 * @return string
 */
function mel_pg_tick_why($titles = null, $mids = null)
{
    if ($titles === null && $mids === null && ((MelCat::$first_due !== null && !mel_catv_is_down()) || mel_pg_lib_queue()))
        return '';
    $cpm = mel_cpm_todo($titles) !== array();
    if ($cpm && !mel_cpm_paused() && !mel_catv_is_down())
        return '';
    $q = $mids !== array() && mel_pg_link_queue($mids) !== array();
    if ($q && !mel_pg_paused() && mel_card_token() !== '')
        return '';
    if (($q && mel_pg_paused()) || ($cpm && mel_cpm_paused()))
        return 'pause';
    if ($cpm)
        return 'server down';
    return $q ? 'no dune_auth' : 'queue empty';
}

// --- The pairs.

// The pairs movie_id => imdb, tmdb (of a movie), tmdbtv (of a series), type (movie, series, ''), seasons
// (season_numbers: season => episodes), soon, src (meta: the seasons are of the meta of the own card, mel_pg_link_meta),
// on (1: of the meta of an anime numbered on, mel_cm_numbered_on), sq (a series with no seasons: the time a request
// for them gave none, asked again after MEL_PG_FAIL_S, mel_pg_link_queue); or none (the time it was found there is no
// pair: for good) or fail (the time of a failed request: asked again after MEL_PG_FAIL_S; of no reply of the server,
// fail and sq set MEL_PG_FAIL_S - MEL_PG_PAUSE_S back: after MEL_PG_PAUSE_S, mel_pg_link_got). Read once from data_dir.
/**
 * @return array<string, MelPgLink>
 */
function mel_pg_links()
{
    if (MelPgLinks::$links !== null)
        return MelPgLinks::$links;
    $path = mel_data_dir() !== '' ? mel_data_dir() . '/' . MEL_PG_LINKS_FILE : '';
    $j = $path !== '' && is_file($path) ? json_decode(strval(file_get_contents($path)), true) : null;
    $links = array();
    foreach (is_array($j) && isset($j['links']) && is_array($j['links']) ? $j['links'] : array() as $mid => $l)
    {
        $l = mel_pg_link_clean($l);
        if ($l && preg_match('/^' . MEL_DUNE_ID . '$/D', strval($mid)))
            $links[strval($mid)] = $l;
    }
    MelPgLinks::$links = $links;
    mel_pg_rev_build();
    return $links;
}

// A pair as stored, of the file or of a source -> its fields that are good, null for none.
/**
 * @param mixed $l
 * @return MelPgLink|null
 */
function mel_pg_link_clean($l)
{
    if (!is_array($l))
        return null;
    if (isset($l['none']) && is_int($l['none']))
        return array('none' => $l['none']);
    if (isset($l['fail']) && is_int($l['fail']))
        return array('fail' => $l['fail']);
    $out = array();
    $imdb = mel_str($l, 'imdb');
    if (preg_match('/^tt[0-9]{1,10}$/D', $imdb))
        $out['imdb'] = $imdb;
    foreach (array('tmdb', 'tmdbtv') as $k)
    {
        $v = mel_str($l, $k);
        if (preg_match('/^[0-9]{1,10}$/D', $v))
            $out[$k] = $v;
    }
    if (!$out)
        return null;
    $type = mel_str($l, 'type');
    if ($type === 'movie' || $type === 'series')
        $out['type'] = $type;
    $seasons = array();
    foreach (isset($l['seasons']) && is_array($l['seasons']) ? $l['seasons'] : array() as $s => $n)
    {
        if (intval($s) >= 0 && is_int($n) && $n >= 1)
            $seasons[intval($s)] = $n;
    }
    if ($seasons)
    {
        ksort($seasons);
        $out['seasons'] = $seasons;
    }
    // The episodes not out by the source ("s:e" => the date, '' for none): only with the seasons.
    $soon = array();
    foreach ($seasons && isset($l['soon']) && is_array($l['soon']) ? $l['soon'] : array() as $k => $d)
    {
        if (preg_match('/^[0-9]{1,5}:[0-9]{1,5}$/D', strval($k)) && is_string($d) && ($d === '' ||
            preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $d)))
            $soon[strval($k)] = $d;
    }
    if ($soon)
        $out['soon'] = $soon;
    if ($seasons && mel_str($l, 'src') === 'meta')
    {
        $out['src'] = 'meta';
        if (isset($l['on']) && $l['on'] === 1)
            $out['on'] = 1;
    }
    if (!$seasons && isset($l['sq']) && is_int($l['sq']))
        $out['sq'] = $l['sq'];
    return $out;
}

// The pair $l is of a series (its type, or a TMDB of a series) with no seasons: the next episode and "all watched" of its
// badge want them (mel_pg_wanted).
/**
 * @param MelPgLink|null $l
 * @return bool
 */
function mel_pg_no_seasons($l)
{
    return $l && !isset($l['none']) && !isset($l['fail']) && !isset($l['seasons']) &&
        ((isset($l['type']) && $l['type'] === 'series') || isset($l['tmdbtv']));
}

// The episodes of the pair $l out by today (UTC; an episode of its soon with no date or a later one is not
// out), season => list ascending (mel_wh_pick), seasons of no episode out left out; none without seasons. The
// season $s of a record whose episode $e is past the episodes of the season (an anime numbered on: One Piece S2
// asked as S1 E62, mel_cm_req_s), or season 1 of a pair of on: the episodes of $s and the seasons after it
// numbered on in it, as the own card asks them; $e past them all (the pair is behind): none, the record as it is.
/**
 * @param MelPgLink|null $l
 * @param int $s
 * @param int $e
 * @return array<int, list<int>>
 */
function mel_pg_eps($l, $s = -1, $e = -1)
{
    if (!$l || !isset($l['seasons']))
        return array();
    $today = gmdate('Y-m-d');
    $soon = isset($l['soon']) ? $l['soon'] : array();
    $eps = array();
    foreach ($l['seasons'] as $n => $count)
    {
        $list = array();
        for ($x = 1; $x <= min($count, MEL_PG_EPS_MAX); $x++)
        {
            $d = isset($soon["$n:$x"]) ? $soon["$n:$x"] : null;
            if ($d === null || ($d !== '' && $d <= $today))
                $list[] = $x;
        }
        if ($list)
            $eps[$n] = $list;
    }
    if (!isset($eps[$s]) || ($e <= $eps[$s][count($eps[$s]) - 1] && !($s === 1 && isset($l['on']))))
        return $eps;
    $on = array();
    $off = 0;
    foreach ($l['seasons'] as $n => $count)
    {
        if ($n < $s)
            continue;
        foreach (isset($eps[$n]) ? $eps[$n] : array() as $x)
            $on[] = $off + $x;
        $off += $count;
    }
    if ($e > $off)
        return array();
    foreach (array_keys($eps) as $n)
    {
        if ($n > $s)
            unset($eps[$n]);
    }
    $eps[$s] = $on;
    return $eps;
}

// MelPgLinks::$rev of MelPgLinks::$links: "imdb:tt..", "tmdb:N", "tmdbtv:N" => the movie_ids with it (two cards of
// the Dune can have the same ids).
/**
 * @return void
 */
function mel_pg_rev_build()
{
    $rev = array();
    foreach (MelPgLinks::$links ? MelPgLinks::$links : array() as $mid => $l)
    {
        foreach (array('imdb', 'tmdb', 'tmdbtv') as $k)
        {
            if (isset($l[$k]))
                $rev["$k:{$l[$k]}"][] = strval($mid);
        }
    }
    MelPgLinks::$rev = $rev;
}

// The movie_ids whose pairs have the key $key ("imdb:tt..", "tmdb:N", "tmdbtv:N"; mel_pg_rev_build), the pairs read
// first.
/**
 * @param string $key
 * @return list<string>
 */
function mel_pg_rev($key)
{
    mel_pg_links();
    return isset(MelPgLinks::$rev[$key]) ? MelPgLinks::$rev[$key] : array();
}

// The pairs written whole (mel_write_file). -> written.
/**
 * @return bool
 */
function mel_pg_links_save()
{
    $path = mel_data_dir() !== '' ? mel_data_dir() . '/' . MEL_PG_LINKS_FILE : '';
    $data = json_encode(array('v' => 1, 'links' => mel_pg_links()));
    $ok = $path !== '' && is_string($data) && mel_write_file($path, $data);
    if (!$ok)
        mel_log('progress: pairs not saved');
    return $ok;
}

// The pair of movie_id $mid set to $l (mel_pg_link_clean): the fields of a pair it has not kept; a pair
// over none or fail; none or fail only over none or fail. Over a pair the seasons: of the meta (src) replace those of
// the pair whole, soon and on with them (the records of Melange are numbered by the own card: the Dune may have S1 of
// 20 where Cinemeta has S1 and S2 of 10); those of the card of the shell (numbered by the Dune) do not touch those of
// the meta but add the seasons after its last one with their soon (not to an anime numbered on: on; not when a season
// of the meta has fewer episodes by the Dune: numbered otherwise, Cinemeta S1 of 50 where the Dune has S1 and S2 of
// 25), those of $undated (season_numbers of play_action: no dates, the episodes not out counted) none (seen after the
// own card is opened); else the larger number of episodes of each. soon replaced only by a source of dates ($l has
// it, even empty: the card of the shell, the meta). A pair of before src (the seasons of the meta mixed in by the
// larger) is of the meta again at the next one. Written (mel_pg_links_save) only on a change and with $save; else
// the caller writes them once. MelPgLinks::$rev built anew only with $rev; else the caller builds it once
// (mel_pg_rev_build). No log line: the caller says what it found. -> it changed.
/**
 * @param string $mid
 * @param mixed $l
 * @param bool $save
 * @param bool $undated
 * @param bool $rev
 * @return bool
 */
function mel_pg_link_put($mid, $l, $save = true, $undated = false, $rev = true)
{
    $dated = is_array($l) && array_key_exists('soon', $l);
    $l = mel_pg_link_clean($l);
    if (!$l || !preg_match('/^' . MEL_DUNE_ID . '$/D', $mid))
        return false;
    $links = mel_pg_links();
    $old = isset($links[$mid]) ? $links[$mid] : null;
    $pair = !isset($l['none']) && !isset($l['fail']);
    $old_pair = $old && !isset($old['none']) && !isset($old['fail']);
    if ($old_pair && !$pair)
        return false;
    $new = $pair && $old_pair ? array_merge($old, array_filter($l)) : $l;
    if ($pair && $old_pair && isset($l['seasons']) && isset($old['src']) && !isset($l['src']))
    {
        foreach (array('seasons', 'soon', 'on') as $k)
        {
            unset($new[$k]);
            if (isset($old[$k]))
                $new[$k] = $old[$k];
        }
        // A season of the Dune after the last one of the meta (out after the own card was last opened): added with the
        // dates of its episodes not out; not to an anime numbered on (its seasons are not those of the Dune), nor when
        // a season of the meta has fewer episodes by the Dune (numbered otherwise), nor by a source of no dates.
        $add = !isset($old['on']) && !$undated;
        $last = 0;
        foreach (isset($old['seasons']) ? $old['seasons'] : array() as $n => $count)
        {
            $last = max($last, $n);
            if (!isset($l['seasons'][$n]) || $l['seasons'][$n] < $count)
                $add = false;
        }
        foreach ($add ? $l['seasons'] : array() as $n => $count)
        {
            if ($n > $last)
                $new['seasons'][$n] = $count;
        }
        foreach ($add && isset($l['soon']) ? $l['soon'] : array() as $k => $d)
        {
            if (intval($k) > $last)
                $new['soon'][$k] = $d;
        }
        $dated = false;
    }
    else if ($pair && $old_pair && isset($l['src']))
    {
        if (!isset($l['on']))
            unset($new['on']);
    }
    else if ($pair && $old_pair && isset($old['seasons'], $l['seasons']))
    {
        $seasons = $old['seasons'];
        foreach ($l['seasons'] as $n => $count)
            $seasons[$n] = max(isset($seasons[$n]) ? $seasons[$n] : 0, $count);
        ksort($seasons);
        $new['seasons'] = $seasons;
    }
    if ($pair && $old_pair && $dated && !isset($l['soon']))
        unset($new['soon']);
    if (isset($new['seasons']))
        unset($new['sq']);
    if ($new == $old)
        return false;
    // No copy of the pairs: the one of the static only.
    unset($links);
    MelPgLinks::$links[$mid] = $new;
    if ($rev)
        mel_pg_rev_build();
    if ($save)
        mel_pg_links_save();
    return true;
}

// The pair of the card of play_action: movie_str of the Dune (mel_movie) has its id, IMDb, the type
// and season_numbers (no dates: not added to a pair of the meta, mel_pg_link_put).
/**
 * @param MelMovie $mv
 * @return void
 */
function mel_pg_link_movie($mv)
{
    if ($mv['dune_id'] !== '' && $mv['imdb'] !== '' && mel_pg_link_put($mv['dune_id'], array('imdb' => $mv['imdb'],
        'type' => $mv['series'] ? 'series' : 'movie', 'seasons' => $mv['seasons']), true, true))
        mel_log('progress: a pair of play_action');
}

// The episodes of the series of card $id of the own card by its meta $m to the pair (src meta: over the seasons of the
// Dune, mel_pg_link_put): its episodes now in the row "Continue" and the badges. Each season its number of episodes,
// "s:e" of those not out => the date ('' none). An anime numbered on whole (mel_cm_numbered_on: One Piece S2 E62-E77,
// asked as S1 E62): the episodes of each season by their place in it, on (mel_pg_eps numbers them on in season 1, as
// the own card asks them). None: a season asked by another number but not all of them, a season numbered not 1 and up
// (numbered on: not on from the season before).
/**
 * @param string $id
 * @param MelCardMeta $m
 * @return void
 */
function mel_pg_link_meta($id, $m)
{
    $on = mel_cm_numbered_on($m);
    $seasons = array();
    $soon = array();
    $first = 1;
    foreach ($m['seasons'] as $s => $eps)
    {
        if ($s < 1 || !$eps)
            continue;
        if (!$on && mel_cm_req_s($m, $s) !== $s)
            return;
        $list = array();
        foreach ($eps as $ep)
            $list[] = $ep['e'];
        $from = $on ? $first : 1;
        if ($list !== range($from, $from + count($list) - 1))
            return;
        $first = $from + count($list);
        $seasons[$s] = count($list);
        foreach ($eps as $i => $ep)
        {
            if ($ep['soon'])
                $soon["$s:" . ($i + 1)] = $ep['date'];
        }
    }
    $l = array('imdb' => $m['imdb'], 'type' => 'series', 'seasons' => $seasons, 'soon' => $soon, 'src' => 'meta');
    if ($on)
        $l['on'] = 1;
    // No IMDb (a series of Lampac only): the pair by its TMDB.
    if ($m['imdb'] === '' && preg_match('/^tmdb:([0-9]{1,10})$/D', $m['id'], $x))
        $l['tmdbtv'] = $x[1];
    if ($seasons && mel_pg_link_put($id, $l))
        mel_log('progress: the episodes of the meta' . ($on ? ', numbered on' : ''));
}

// The pair of the card $id found for tile $t (mel_card_id): its IMDb, its TMDB by its type.
/**
 * @param string $id
 * @param MelCatTile $t
 * @return void
 */
function mel_pg_link_tile($id, $t)
{
    $l = array('imdb' => $t['imdb_id'], 'type' => $t['type']);
    if ($t['_tmdbId'] !== '' && ($t['type'] === 'movie' || $t['type'] === 'series'))
        $l[$t['type'] === 'series' ? 'tmdbtv' : 'tmdb'] = $t['_tmdbId'];
    if ($id !== '' && mel_pg_link_put($id, $l))
        mel_log('progress: a pair of ENTER');
}

// The keys of the pairs (MelPgLinks::$rev) of tile $t: of its IMDb, then of its TMDB of its type (none of a tile of no
// type, as mel_pg_tile_cards).
/**
 * @param MelCatTile $t
 * @return list<string>
 */
function mel_pg_tile_keys($t)
{
    $keys = array('imdb:' . $t['imdb_id']);
    if ($t['_tmdbId'] !== '' && ($t['type'] === 'movie' || $t['type'] === 'series'))
        $keys[] = ($t['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $t['_tmdbId'];
    return $keys;
}

// The movie_ids of the card of tile $t by the pairs: of its IMDb, then of its TMDB of its type. A tile of no
// type (a row of anime) has no TMDB key: the numbers of movies and series of TMDB are not the same ids.
/**
 * @param MelCatTile $t
 * @return list<string>
 */
function mel_pg_tile_cards($t)
{
    mel_pg_links();
    $out = array();
    foreach (mel_pg_tile_keys($t) as $k)
    {
        foreach (isset(MelPgLinks::$rev[$k]) ? MelPgLinks::$rev[$k] : array() as $mid)
            $out[] = $mid;
    }
    return array_values(array_unique($out));
}

// --- The cards of the dunemdb API.

// The offsets $offs (ascending, each at a key: out of any string) of $text that are in no list ("[...]": people,
// suppliers, recommendations): the brackets before them balanced, those of strings not counted (a title of
// a trailer "...HD] | ..."); with $first up to the first such one. -> offset => bool; false when a piece can not
// be read.
/**
 * @param string $text
 * @param list<int> $offs
 * @param bool $first
 * @return array<int, bool>
 */
function mel_pg_out_of_lists($text, $offs, $first = false)
{
    $out = array();
    $at = 0;
    $depth = 0;
    foreach ($offs as $o)
    {
        if ($o > $at && $depth !== null)
        {
            // A piece from a key to a key: its strings out, in step.
            $seg = preg_replace('/"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"/', '', substr($text, $at, $o - $at));
            $depth = is_string($seg) ? $depth + substr_count($seg, '[') - substr_count($seg, ']') : null;
        }
        $at = max($at, $o);
        $out[$o] = $depth === 0;
        if ($first && $out[$o])
            break;
    }
    return $out;
}

// The ids of a card of the dunemdb API in its raw text (a reply of movie/<id>, a card of the shell): the
// last "sources" with "auxSources" after it in no list (not of a person, a supplier, a recommendation), as
// the shell reads them (dunemdb_cache.php: sources first, then auxSources; either may be an empty []), the
// type by the first "type" of a title in no list. No json_decode of the ~400 KB. $found: such sources were
// found (with no ids in them: the card has none). -> the pair (mel_pg_link_clean), null for none.
/**
 * @param string $text
 * @param bool $found
 * @return MelPgLink|null
 */
function mel_pg_parse_card($text, &$found = false)
{
    $found = false;
    if (!preg_match_all(MEL_PG_SRC_RE, $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        return null;
    $offs = array();
    foreach ($m as $one)
        $offs[] = intval($one[0][1]);
    $top = mel_pg_out_of_lists($text, $offs);
    $last = null;
    foreach ($m as $one)
    {
        if ($top[intval($one[0][1])])
            $last = $one;
    }
    if (!$last)
        return null;
    $found = true;
    $l = mel_pg_src_ids($last[1][0], $last[2][0]);
    if (preg_match_all('/"type":"(single|series)"/', $text, $t, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
    {
        $offs = array();
        foreach ($t as $one)
            $offs[] = intval($one[0][1]);
        $top = mel_pg_out_of_lists($text, $offs, true);
        foreach ($t as $one)
        {
            if (!empty($top[intval($one[0][1])]))
            {
                $l['type'] = $one[1][0] === 'series' ? 'series' : 'movie';
                break;
            }
        }
    }
    return mel_pg_link_clean(array_merge($l, mel_pg_parse_seasons($text)));
}

// The ids of a block of MEL_PG_SRC_RE: its sources $src, its auxSources $aux, of sources first (as the shell) -> imdb,
// tmdb, tmdbtv found, as they are.
/**
 * @param string $src
 * @param string $aux
 * @return array<string, string>
 */
function mel_pg_src_ids($src, $aux)
{
    $ids = array('imdb' => 'IMDB', 'tmdb' => 'TheMovieDB', 'tmdbtv' => 'TheMovieDBTV');
    $l = array();
    foreach ($ids as $k => $name)
    {
        foreach (array($src, $aux) as $obj)
        {
            if (!isset($l[$k]) && preg_match('/"' . $name . '":"([^"]*)"/', $obj, $v))
                $l[$k] = $v[1];
        }
    }
    return $l;
}

// The ids of a card of the shell only (the pair of a title of the library: no type, no seasons): its one block of
// MEL_PG_SRC_RE - the one of the card in the cards of the shell, ~4 times cheaper than mel_pg_parse_card; more blocks
// (of a list too): mel_pg_parse_card, the one out of the lists. -> the pair (mel_pg_link_clean), null for none.
/**
 * @param string $text
 * @return MelPgLink|null
 */
function mel_pg_card_ids($text)
{
    $n = preg_match_all(MEL_PG_SRC_RE, $text, $m, PREG_SET_ORDER);
    if ($n > 1)
        return mel_pg_parse_card($text);
    return $n === 1 ? mel_pg_link_clean(mel_pg_src_ids($m[0][1], $m[0][2])) : null;
}

// A string of the raw text of a card ($m[0], $m[1] ':' of a key) -> it, a key or a date "YYYY-MM-DD"; else "".
/**
 * @param array<int, string> $m
 * @return string
 */
function mel_pg_strip_str($m)
{
    return $m[1] === ':' || preg_match('/^"[0-9]{4}-[0-9]{2}-[0-9]{2}"$/D', $m[0]) ? $m[0] : '""';
}

// The seasons of a card of the dunemdb API in its raw text (mel_pg_parse_card): the first "seasons" of episodes in no
// list, its strings but the keys and the dates cut out (the titles and descriptions are most of it, may hold
// brackets) and only that decoded -> seasons (season => its last episode by number, season 0 left out, as
// season_numbers of the shell) and soon (mel_pg_link_clean: "s:e" => premiered, '' for none, of those not out
// today, UTC); none for none.
/**
 * @param string $text
 * @return array{seasons?: array<int, int>, soon?: array<string, string>}
 */
function mel_pg_parse_seasons($text)
{
    if (!preg_match_all('/"seasons":\[\{"number":/', $text, $m, PREG_OFFSET_CAPTURE))
        return array();
    $offs = array();
    foreach ($m[0] as $one)
        $offs[] = intval($one[1]);
    $at = -1;
    foreach (mel_pg_out_of_lists($text, $offs, true) as $o => $top)
    {
        if ($top)
            $at = $o;
    }
    if ($at < 0)
        return array();
    $tail = preg_replace_callback('/"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"(:?)/', 'mel_pg_strip_str',
        substr($text, $at + strlen('"seasons":')));
    if (!is_string($tail) || !preg_match('/\[(?:[^\[\]]++|(?R))*+\]/A', $tail, $arr))
    {
        // pcre.backtrack_limit of PHP 5.3.6 is 100000: a card of 5 seasons of 300 episodes is past it.
        if (preg_last_error() !== PREG_NO_ERROR)
            mel_log(sprintf('link: the seasons of a card not read, preg error %d, %d B', preg_last_error(), strlen($text)));
        return array();
    }
    $list = json_decode($arr[0], true);
    $today = gmdate('Y-m-d');
    $seasons = array();
    $soon = array();
    foreach (is_array($list) ? $list : array() as $sn)
    {
        $n = isset($sn['number']) && is_int($sn['number']) ? $sn['number'] : 0;
        if ($n < 1 || !isset($sn['episodes']) || !is_array($sn['episodes']))
            continue;
        foreach ($sn['episodes'] as $ep)
        {
            $e = isset($ep['number']) && is_int($ep['number']) ? $ep['number'] : 0;
            if ($e < 1 || $e > MEL_PG_EPS_MAX)
                continue;
            $seasons[$n] = max(isset($seasons[$n]) ? $seasons[$n] : 0, $e);
            $d = isset($ep['premiered']) && is_string($ep['premiered']) ? $ep['premiered'] : '';
            if ($d === '' || $d > $today)
                $soon["$n:$e"] = $d;
        }
    }
    return $seasons ? array('seasons' => $seasons, 'soon' => $soon) : array();
}

// The code and the message (lower case) of a reply of the dunemdb API, of the fields before its "result"
// (the ~400 KB not decoded), of all of a small one -> array(code, -1 for none; message).
/**
 * @param string $body
 * @return array{int, string}
 */
function mel_pg_api_head($body)
{
    $p = strpos($body, '"result":');
    $j = json_decode($p === false ? $body : rtrim(substr($body, 0, $p), " \t\r\n,") . '}', true);
    if ((!is_array($j) || !isset($j['code'])) && strlen($body) <= MEL_PG_HEAD_MAX)
        $j = json_decode($body, true);
    $code = is_array($j) && isset($j['code']) && is_numeric($j['code']) ? intval($j['code']) : -1;
    return array($code, strtolower(mel_str($j, 'message')));
}

// --- The cards of the shell.

// The path of the card of the shell of movie_id $mid, '' for none.
/**
 * @param string $mid
 * @param list<string>|null $dirs
 * @return string
 */
function mel_pg_shell_path($mid, $dirs = null)
{
    foreach ($dirs !== null ? $dirs : array(MEL_PG_SHELL_DIR, MEL_PG_SHELL_DIR2) as $dir)
    {
        if (is_file("$dir/$mid.json"))
            return "$dir/$mid.json";
    }
    return '';
}

// The text of the card of the shell of movie_id $mid (an id of the dunemdb API), '' for none (no file, too big); of the
// first of the folders $dirs that has it (null: MEL_PG_SHELL_DIR, MEL_PG_SHELL_DIR2; the pass, mel_pg_bg_run, gives
// its own).
/**
 * @param string $mid
 * @param list<string>|null $dirs
 * @return string
 */
function mel_pg_shell_text($mid, $dirs = null)
{
    $path = preg_match('/^[0-9a-f]{24}$/D', $mid) ? mel_pg_shell_path($mid, $dirs) : '';
    $size = $path !== '' ? filesize($path) : 0;
    return $size > 0 && $size <= MEL_PG_SHELL_MAX_BYTES ? strval(file_get_contents($path)) : '';
}

// The pair of $mid from the card of the shell, null for none (no file, too big, no ids). Not asked again
// for it while php_server runs.
/**
 * @param string $mid
 * @return MelPgLink|null
 */
function mel_pg_shell_pair($mid)
{
    MelPgLinks::$shell_seen[$mid] = true;
    $text = mel_pg_shell_text($mid);
    return $text !== '' ? mel_pg_parse_card($text) : null;
}

// The pair of a title of the library of type $type of the index (movie, series, '' of both) by the text $text of its card
// of the shell, as the step and the pass read it: its ids (mel_pg_card_ids), of a title of both types the whole card
// (mel_pg_parse_card: its type and seasons); the type of the index when the card has none. -> the pair, null for none.
/**
 * @param string $text
 * @param string $type
 * @return MelPgLink|null
 */
function mel_pg_lib_pair($text, $type)
{
    $l = $type !== '' ? mel_pg_card_ids($text) : mel_pg_parse_card($text);
    if ($l && !isset($l['type']) && $type !== '')
        $l['type'] = $type;
    return $l;
}

// The cards of the history a card of the dunemdb API is wanted for, their records ending the latest first: with no
// pair (a failed request too), or a pair of a series with no seasons (mel_pg_no_seasons: of ENTER, of a version before
// the seasons) with an episode record. -> movie_id => end.
/**
 * @return array<string, int>
 */
function mel_pg_wanted()
{
    $links = mel_pg_links();
    $out = array();
    foreach (mel_pg_index() as $mid => $ent)
    {
        $l = isset($links[$mid]) ? $links[$mid] : null;
        if (!$l || isset($l['fail']) || ($ent['series'] && $ent['series'][0] && mel_pg_no_seasons($l)))
            $out[strval($mid)] = $ent['end'];
    }
    arsort($out);
    return $out;
}

// The cards of mel_pg_wanted from the cards of the shell, the latest first, in MEL_PG_SHELL_MS (one card at least),
// each once while php_server runs; the pairs written once. A line of the log only when one was read.
/**
 * @return void
 */
function mel_pg_shell_step()
{
    $t = microtime(true);
    $read = 0;
    $found = 0;
    foreach (mel_pg_wanted() as $mid => $end)
    {
        if (isset(MelPgLinks::$shell_seen[$mid]))
            continue;
        if ($read && (microtime(true) - $t) * 1000 >= MEL_PG_SHELL_MS)
            break;
        $read++;
        $l = mel_pg_shell_pair($mid);
        if ($l && mel_pg_link_put($mid, $l, false))
            $found++;
    }
    if ($found)
        mel_pg_links_save();
    if ($read)
        mel_log(sprintf('progress: shell cards: %d looked at, %d pairs, %.0f ms', $read, $found, (microtime(true) - $t) * 1000));
}

// The titles of the library Melange can play (MelLib::$play: of a network source; its index read again only when it
// changed) with no pair or a failed one, whose card of the shell gave none not within MEL_PG_FAIL_S nor since the index
// changed: no card read for a title of no badge. -> movie_id => its type by the index: movie (movie files), series
// (episodes), '' (both: the card tells, mel_pg_lib_step).
/**
 * @return array<string, string>
 */
function mel_pg_lib_queue()
{
    mel_lib_index();
    if (MelPgLinks::$lib_sig !== MelLib::$sig)
    {
        MelPgLinks::$lib_seen = array();
        MelPgLinks::$lib_sig = MelLib::$sig;
    }
    $links = mel_pg_links();
    $now = time();
    $out = array();
    foreach (MelLib::$play as $mid => $type)
    {
        $mid = strval($mid);
        $t = isset(MelPgLinks::$lib_seen[$mid]) ? MelPgLinks::$lib_seen[$mid] : null;
        if ((isset($links[$mid]) && !isset($links[$mid]['fail'])) ||
            ($t !== null && $now - $t >= 0 && $now - $t < MEL_PG_FAIL_S))
            continue;
        $out[$mid] = $type;
    }
    return $out;
}

// The pairs of the titles of the library (mel_pg_lib_queue) from their cards of the shell, by their ids (mel_pg_card_ids)
// and the type of the index (a series wants its seasons, mel_pg_wanted), of a title of both types the whole card
// (mel_pg_parse_card), in $ms (one card at least); the pairs and MelPgLinks::$rev once. A line of the log of a step
// that looked at a card: read, pairs, none (no card, too big, no ids: MelPgLinks::$lib_seen), ms. No library: nothing
// read. $q: the queue of the caller (mel_pg_lib_work), null: mel_pg_lib_queue.
/**
 * @param float $ms
 * @param array<string, string>|null $q
 * @return void
 */
function mel_pg_lib_step($ms, $q = null)
{
    $t = microtime(true);
    $read = 0;
    $found = 0;
    $none = 0;
    foreach ($q !== null ? $q : mel_pg_lib_queue() as $mid => $type)
    {
        $mid = strval($mid);
        if (($found || $none) && (microtime(true) - $t) * 1000 >= $ms)
            break;
        $text = mel_pg_shell_text($mid);
        $l = null;
        if ($text !== '')
        {
            $read++;
            $l = mel_pg_lib_pair($text, $type);
        }
        if ($l && mel_pg_link_put($mid, $l, false, false, false))
            $found++;
        else
        {
            MelPgLinks::$lib_seen[$mid] = time();
            $none++;
        }
    }
    if ($found)
    {
        mel_pg_rev_build();
        mel_pg_links_save();
    }
    if ($found || $none)
        mel_log(sprintf('progress: library cards: %d read, %d pairs, %d none, %.0f ms', $read, $found, $none,
            (microtime(true) - $t) * 1000));
}

// --- The request.

// dune_auth for a request, '' for none: a line of the log only when it went.
/**
 * @return string
 */
function mel_pg_token()
{
    $token = mel_card_token();
    if ($token === '' && !MelPgLinks::$no_auth)
        mel_log('link: no dune_auth');
    MelPgLinks::$no_auth = $token === '';
    return $token;
}

// The pair of $mid now: the card of the shell (not looked at yet), else movie/<id> of the dunemdb API with
// dune_auth, as the shell opens its card (dunemdb_api.php api_movie), in $total s (whole s; under 1 none). A pair of
// a series with no seasons (mel_pg_no_seasons): the card for them, of the shell only with them; none in the reply -
// sq, the time (the pair is not touched by none or fail); a new pair of a series with none in the reply - sq too.
// Kept: a pair; none (code 100 with no ids in the sources of the card, or the message "not found", as
// mel_card_ask); fail (no dune_auth, transport, "sources" not found by the parse, another answer: asked again
// after MEL_PG_FAIL_S). No connection, a timeout, 429 or 5xx: that card asked again after MEL_PG_PAUSE_S, no request in
// MEL_PG_PAUSE_S (mel_pg_link_step). One log line with the size and the time, no token. -> st: pair, none,
// fail; link: the pair or null.
/**
 * @param string $mid
 * @param float $total
 * @return array{st: string, link: MelPgLink|null}
 */
function mel_pg_link_now($mid, $total = MEL_PG_LINK_S)
{
    $p = mel_pg_link_pre($mid, $total);
    if ($p['done'])
        return $p['done'];
    return mel_pg_link_got($mid, $p['need'], mel_http_get_req($p['req']));
}

// The pairs of $mids now, as mel_pg_link_now each, their requests at once (mel_http_get_many; the caller keeps to
// MEL_HTTP_PAR); the pause (MEL_PG_PAUSE_S) only with no reply of the server in the batch (mel_pg_link_down of
// each) -> movie_id => array(st, link) in the order of $mids; null: no curl_multi (the caller asks one by one,
// mel_pg_link_now). $sent: the requests that went (not those of no request: mel_pg_link_pre).
/**
 * @param list<string> $mids
 * @param float $total
 * @param int $sent
 * @return array<string, array{st: string, link: MelPgLink|null}>|null
 */
function mel_pg_link_many($mids, $total = MEL_PG_LINK_S, &$sent = 0)
{
    $sent = 0;
    $pre = array();
    $reqs = array();
    foreach ($mids as $mid)
    {
        $pre[$mid] = mel_pg_link_pre($mid, $total);
        if (!$pre[$mid]['done'])
            $reqs[$mid] = $pre[$mid]['req'];
    }
    $res = $reqs ? mel_http_get_many($reqs) : array();
    if ($res === null)
        return null;
    $sent = count($reqs);
    // No connection, a timeout or 5xx: a pause only with no reply in the batch.
    $answered = false;
    foreach ($res as $r)
        $answered = $answered || !mel_pg_link_down($r[0], $r[2]);
    $out = array();
    foreach ($pre as $mid => $p)
    {
        $mid = strval($mid);
        $out[$mid] = $p['done'] ? $p['done'] : mel_pg_link_got($mid, $p['need'], $res[$mid], !$answered);
        // The body of each freed as it is parsed.
        unset($res[$mid]);
    }
    return $out;
}

// mel_pg_link_now of $mid before its request -> done: its result with no request (the card of the shell, not an id
// of the server, no time or no dune_auth), else null and req: array(URL, connect, total, max) of mel_http_get; need:
// a series with no seasons asks for them.
/**
 * @param string $mid
 * @param float $total
 * @return array{done: array{st: string, link: MelPgLink|null}|null, need: bool, req: array{string, int, int, int}}
 */
function mel_pg_link_pre($mid, $total)
{
    $links = mel_pg_links();
    $need = mel_pg_no_seasons(isset($links[$mid]) ? $links[$mid] : null);
    $out = array('done' => null, 'need' => $need, 'req' => array('', 0, 0, 0));
    $l = isset(MelPgLinks::$shell_seen[$mid]) ? null : mel_pg_shell_pair($mid);
    if ($l)
    {
        mel_pg_link_put($mid, $l);
        if (!$need || isset($l['seasons']))
            return array('done' => array('st' => 'pair', 'link' => $l)) + $out;
    }
    if (!preg_match('/^[0-9a-f]{24}$/D', $mid))
        return array('done' => array('st' => 'none', 'link' => null)) + $out;
    // The request in what is left, whole seconds of curl: under 1 s none.
    if ($total < 1)
        return array('done' => array('st' => 'fail', 'link' => null)) + $out;
    $token = mel_pg_token();
    if ($token === '')
        return array('done' => array('st' => 'fail', 'link' => null)) + $out;
    $out['req'] = array(mel_card_api() . "movie/$mid?movieAnswerType=full_with_recommendations&lang=ru&$token",
        min(MEL_PG_LINK_CONNECT, intval(floor($total))), intval(floor($total)), MEL_CAT_MAX_BYTES);
    return $out;
}

// The reply $r (array(HTTP code, body, err, connected, s it took) of mel_http_get) of the request of mel_pg_link_now
// of $mid ($need: of mel_pg_link_pre): parsed, kept, its pause (if $pause: mel_pg_link_down) and its line of the log
// -> array(st, link).
/**
 * @param string $mid
 * @param bool $need
 * @param array{int, string, string, bool, float} $r
 * @param bool $pause
 * @return array{st: string, link: MelPgLink|null}
 */
function mel_pg_link_got($mid, $need, $r, $pause = true)
{
    list($code, $body, $err, , $s) = $r;
    unset($r);
    $l = null;
    $st = 'fail';
    if ($err === '' && $code === 200)
    {
        list($api, $msg) = mel_pg_api_head($body);
        $found = false;
        $l = $api === 100 ? mel_pg_parse_card($body, $found) : null;
        $st = $l ? 'pair' : ($api === 100 || $msg === 'not found' ? 'none' : 'fail');
        // "sources" the parse did not find (a format not known): not "no pair" for good.
        if ($st === 'none' && $api === 100 && !$found && strpos($body, '"sources":') !== false)
            $st = 'fail';
    }
    // No reply of the server (a slow one too): the card asked again after MEL_PG_PAUSE_S, not MEL_PG_FAIL_S - its
    // time of fail (sq) set back so (mel_pg_link_queue).
    $net = mel_pg_link_down($code, $err);
    $at = $net ? time() - MEL_PG_FAIL_S + MEL_PG_PAUSE_S : time();
    $down = $pause && $net;
    if ($down)
        MelPgLinks::$pause = time() + MEL_PG_PAUSE_S;
    // Only the number of curl: its text may hold the address.
    $why = $err !== '' ? (preg_match('/^curl [0-9]+/', $err, $e) ? $e[0] : ($err === MEL_HTTP_TOO_BIG ? 'too big' : 'failed')) :
        "HTTP $code";
    $got = $st === 'pair' && isset($l['seasons']);
    // No seasons of a series: asked for them, or a new pair of a series with none (not asked again at the next tick).
    $sq = !$got && ($need || ($st === 'pair' && mel_pg_no_seasons($l)));
    mel_log(sprintf('link: movie/%s %d B, %.0f ms, %s, %s%s%s%s', $mid, strlen($body), $s * 1000, $why, $st,
        $sq ? ', no seasons' : ($got && $need ? ', seasons' : ''),
        $net ? sprintf(', retry %d min', MEL_PG_PAUSE_S / 60) : '', $down ? ', pause' : ''));
    mel_pg_link_put($mid, $st === 'pair' ? $l : array($st => $at), !$sq);
    if ($sq)
    {
        $links = mel_pg_links();
        $links[$mid]['sq'] = $at;
        MelPgLinks::$links = $links;
        mel_pg_links_save();
    }
    return array('st' => $st, 'link' => $l);
}

// A reply of HTTP code $code, transport error $err (mel_http_get) is of the API down: no connection, a timeout, 429
// (busy), 5xx.
/**
 * @param int $code
 * @param string $err
 * @return bool
 */
function mel_pg_link_down($code, $err)
{
    return ($err !== '' && $err !== MEL_HTTP_TOO_BIG) || ($err === '' && ($code === 429 || ($code >= 500 && $code < 600)));
}

// s of the pause of the requests left (mel_pg_link_now: no connection, 5xx); 0: none. A pause ending past
// MEL_PG_PAUSE_S from now (the clock set back) is none.
/**
 * @return int
 */
function mel_pg_paused()
{
    $left = MelPgLinks::$pause - time();
    return $left > 0 && $left <= MEL_PG_PAUSE_S ? $left : 0;
}

// The cards a step asks for (mel_pg_link_step): of mel_pg_wanted, the latest first, records that ended within
// MEL_PG_LINK_AGE_S, a failure older than MEL_PG_FAIL_S (sq of a series with no seasons too; set back for no reply of
// the server, mel_pg_link_got), ids of the Dune; of $mids alone (the cards of a screen; null: all); those of $first
// before the others, in its order (the tiles of the row "Continue" of the main screen, mel_cp_row_mids). -> movie_ids.
/**
 * @param list<string>|null $mids
 * @param list<string> $first
 * @return list<string>
 */
function mel_pg_link_queue($mids = null, $first = array())
{
    $now = time();
    $links = mel_pg_links();
    $only = $mids !== null ? array_flip($mids) : null;
    $out = array();
    foreach (mel_pg_wanted() as $mid => $end)
    {
        $mid = strval($mid);
        if ($end <= 0 || $now - $end > MEL_PG_LINK_AGE_S || !preg_match('/^[0-9a-f]{24}$/D', $mid) ||
            ($only !== null && !isset($only[$mid])))
            continue;
        $t = isset($links[$mid]['fail']) ? $links[$mid]['fail'] : (isset($links[$mid]['sq']) ? $links[$mid]['sq'] : null);
        if ($t !== null && $now - $t >= 0 && $now - $t < MEL_PG_FAIL_S)
            continue;
        $out[] = $mid;
    }
    if (!$first)
        return $out;
    $head = array_values(array_intersect($first, $out));
    return array_merge($head, array_values(array_diff($out, $head)));
}

// The lazy pairs: the cards of mel_pg_link_queue (of $mids, those of $first first: as it takes them) in $limit s:
// MEL_HTTP_PAR at once (mel_pg_link_many), each its timeout the whole of what is left; else (no curl_multi) up to
// MEL_PG_LINK_N requests one by one (the second one only with MEL_PG_LINK_NEXT_S left; a $tick, mel_pg_tick: one).
// None in a pause (mel_pg_paused) or with no dune_auth.
// One line of the log of a step that asked (its path: mel_http_mark); of a $tick always. -> how many were asked (only
// the tests read it); $rounds: the requests in time - a batch one, one by one each: the callers take it off MEL_CPM_N,
// the $n of mel_cpm_step; $pairs: the pairs got (the tick of the screen "Продолжить" builds it anew for them).
/**
 * @param float $limit
 * @param bool $tick
 * @param int $rounds
 * @param int $pairs
 * @param list<string>|null $mids
 * @param list<string> $first
 * @return int
 */
function mel_pg_link_step($limit, $tick = false, &$rounds = 0, &$pairs = 0, $mids = null, $first = array())
{
    $t = microtime(true);
    $n = 0;
    $got = 0;
    $rounds = 0;
    $q = mel_pg_paused() ? array() : mel_pg_link_queue($mids, $first);
    $par = 0;
    $many = $q && $limit >= 1 && mel_pg_token() !== '' ? mel_pg_link_many(array_slice($q, 0, MEL_HTTP_PAR), $limit, $par) :
        null;
    foreach ($many !== null ? $many : array() as $r)
    {
        $n++;
        $got += $r['st'] === 'pair' ? 1 : 0;
    }
    $rounds = $par ? 1 : 0;
    foreach ($many !== null ? array() : $q as $mid)
    {
        $left = $limit - (microtime(true) - $t);
        if ($n >= ($tick ? 1 : MEL_PG_LINK_N) || $left < ($n ? MEL_PG_LINK_NEXT_S : 1))
            break;
        if ($n === 0 && mel_pg_token() === '')
            break;
        $pause = MelPgLinks::$pause;
        $r = mel_pg_link_now($mid, $left);
        $n++;
        $rounds++;
        $got += $r['st'] === 'pair' ? 1 : 0;
        if (MelPgLinks::$pause !== $pause)
            break;
    }
    $mark = mel_http_mark($par, $many !== null ? 0 : $n);
    if ($n || $tick)
        mel_log(sprintf('link: %s: %d asked, %d pairs, %.0f ms%s', $tick ? 'tick' : 'step', $n, $got, (microtime(true) - $t) * 1000,
            $mark !== '' ? ", $mark" : ''));
    $pairs = $got;
    return $n;
}
