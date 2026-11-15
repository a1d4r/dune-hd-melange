<?php
// The pairs card of the Dune (movie_id) -> IMDb / TMDB for the badges of the history (progress.php): kept in a
// file of data_dir; found by play_action, ENTER, the cards the shell keeps and, lazily, a request of the card
// to the dunemdb API: r24 in an opening of the catalog, r25 at the tick of the timer of its screen (aio_pg_tick).
// Needs progress.php (aio_pg_index), main.php (aio_log, aio_write_file, aio_data_dir, aio_http_get,
// AIO_DUNE_ID, AIO_HTTP_TOO_BIG), parse.php (aio_str), catalog.php (AIO_CAT_MAX_BYTES), catalog_card.php
// (aio_card_token, aio_card_api).

// The cards the shell keeps (movies/storage_helper.php: /flashdata, /persistfs on old models): PHP of the
// Dune adds FS_PREFIX itself. Tests may define them first.
if (!defined('AIO_PG_SHELL_DIR'))
    define('AIO_PG_SHELL_DIR', '/flashdata/dunemdb_cache/movies');
if (!defined('AIO_PG_SHELL_DIR2'))
    define('AIO_PG_SHELL_DIR2', '/persistfs/dunemdb_cache/movies');
// A bigger card of the shell is not read (seen up to ~840 KB).
define('AIO_PG_SHELL_MAX_BYTES', 2000000);
// ms of an opening for the cards of the shell; one card is read even past it.
define('AIO_PG_SHELL_MS', 150);
// The pairs, in data_dir.
define('AIO_PG_LINKS_FILE', 'wh_links.json');
// A failed request is asked again after this many s; "no pair" stays.
define('AIO_PG_FAIL_S', 86400);
// No connection, a timeout or 5xx: no request for this many s (memory of php_server).
define('AIO_PG_PAUSE_S', 1800);
// A record that ended longer ago is not linked by a request.
define('AIO_PG_LINK_AGE_S', 31536000);
// Requests of an opening, the time of them all, s; the second one only with LINK_NEXT_S left.
define('AIO_PG_LINK_N', 2);
define('AIO_PG_LINK_S', 3.0);
define('AIO_PG_LINK_NEXT_S', 1.5);
define('AIO_PG_LINK_CONNECT', 2);
// r25: the one tick of the timer of the main screen of rows (aio_pg_tick_set) this long after its opening: the
// rows of the window of the shell are asked by then, the step does not hold them.
define('AIO_PG_TICK_MS', 2500);
// r25: a tick this soon after a reply of get_folder_rows comes between the rows of a cold opening (the window of
// the shell still loading, longer than AIO_PG_TICK_MS): no step of that opening.
define('AIO_PG_ROWS_QUIET_S', 0.5);
// A reply of the API this small is decoded whole when its code is not before "result".
define('AIO_PG_HEAD_MAX', 65536);
// Episodes of a season the pairs and their parse take at most.
define('AIO_PG_EPS_MAX', 5000);

class AioPgLinks
{
    // The pairs by movie_id (aio_pg_links; null: not read yet) and the reverse of them: key => movie_ids.
    /** @var array<string, AioPgLink>|null */
    public static $links = null;
    /** @var array<string, list<string>> */
    public static $rev = array();
    // movie_ids whose card of the shell was looked at since the start of php_server.
    /** @var array<string, bool> */
    public static $shell_seen = array();
    // No request before this time (aio_pg_link_now: no connection, 5xx); 0: none.
    /** @var int */
    public static $pause = 0;
    // The last step found no dune_auth (logged once).
    /** @var bool */
    public static $no_auth = false;
    // r25: the main screen of rows whose timer is set for the lazy step (aio_pg_tick_set); '': none, or its tick came.
    /** @var string */
    public static $tick_url = '';
    // r25: the main screen of rows opened last ('': none since the start of php_server) and whether its tick made the
    // step; when get_folder_rows last answered (microtime; 0: never). For aio_pg_tick.
    /** @var string */
    public static $screen = '';
    /** @var bool */
    public static $ticked = false;
    /** @var float */
    public static $rows_t = 0.0;
}

// --- When to link: an opening of the rows of the catalog.

// A screen of rows of the catalog opens: new cards of the history paired by the cards of the shell (before the
// badges of its tiles); the timer of the screen before it is no more for the step.
/**
 * @return void
 */
function aio_pg_screen_start()
{
    aio_pg_shell_step();
    AioPgLinks::$tick_url = '';
    AioPgLinks::$screen = '';
    AioPgLinks::$ticked = false;
}

// r25: get_folder_rows answers now (aio_pg_tick: rows still loading).
/**
 * @return void
 */
function aio_pg_rows_answered()
{
    AioPgLinks::$rows_t = microtime(true);
}

// r24: the screen is made, $left s of its opening left, $net: rows of it went to the server in it (old or no
// copies). The lazy pairs now, only with $net: an opening of rows of memory or the cache stays at once.
/**
 * @param float $left
 * @param bool $net
 * @return void
 */
function aio_pg_screen_built($left, $net)
{
    if ($net && $left >= 1)
        aio_pg_link_step(min($left, AIO_PG_LINK_S));
}

// r25: the main screen of rows $url is made: the step goes to the tick of its timer (AIO_PG_TICK_MS, once), after
// the rows of the window of the shell (get_folder_rows), as php_server answers one call at a time. A timer only
// with a card to ask (aio_pg_link_queue), no pause and dune_auth; a line of the log when a pause holds it, of no
// dune_auth once till it comes (AioPgLinks::$no_auth, as aio_pg_token). Back to the screen with no
// get_folder_view: no timer, no step. -> the timer is set.
/**
 * @param string $url
 * @return bool
 */
function aio_pg_tick_set($url)
{
    AioPgLinks::$screen = $url;
    if (!aio_pg_link_queue())
        return false;
    $pause = aio_pg_paused();
    if ($pause)
    {
        aio_log(sprintf('link: no timer: a pause of the requests, %d s left', $pause));
        return false;
    }
    $no_auth = aio_card_token() === '';
    if ($no_auth && !AioPgLinks::$no_auth)
        aio_log('link: no timer: no dune_auth');
    AioPgLinks::$no_auth = $no_auth;
    if ($no_auth)
        return false;
    AioPgLinks::$tick_url = $url;
    return true;
}

// r25: the tick of the timer of the main screen $url: the step (aio_pg_link_step) once, of one request (a key
// pressed now waits for it, ~0.5 s), its timeout as of a step. No step: within AIO_PG_ROWS_QUIET_S of a reply of
// get_folder_rows (the rows of a cold opening wait for php_server; the step of the opening is lost), a second tick
// of the screen, a tick of another or an old one, a tick with no screen since the start of php_server. A line of
// the log of each, in its own words.
/**
 * @param string $url
 * @return void
 */
function aio_pg_tick($url)
{
    if ($url === '' || AioPgLinks::$tick_url !== $url)
    {
        if (AioPgLinks::$screen === '')
            $why = 'no screen since the start of php_server';
        else if ($url !== AioPgLinks::$screen)
            $why = 'a tick of another or an old screen';
        else
            $why = AioPgLinks::$ticked ? 'the step was on this screen' : 'no timer on this screen';
        aio_log("link: tick: no step, $why");
        return;
    }
    AioPgLinks::$tick_url = '';
    $ago = microtime(true) - AioPgLinks::$rows_t;
    if (AioPgLinks::$rows_t > 0 && $ago >= 0 && $ago < AIO_PG_ROWS_QUIET_S)
    {
        aio_log('link: tick: no step, rows are loading');
        return;
    }
    AioPgLinks::$ticked = true;
    aio_pg_link_step(AIO_PG_LINK_S, true);
}

// --- The pairs.

// The pairs movie_id => imdb, tmdb (of a movie), tmdbtv (of a series), type (movie, series, ''), seasons
// (season_numbers: season => episodes), soon, src (meta: the seasons are of the meta of the own card, aio_pg_link_meta),
// on (1: of the meta of an anime numbered on, aio_cm_numbered_on), sq (a series with no seasons: the time a request
// for them gave none, asked again after AIO_PG_FAIL_S, aio_pg_link_queue); or none (the time it was found there is no
// pair: for good) or fail (the time of a failed request: asked again after AIO_PG_FAIL_S). Read once from data_dir.
/**
 * @return array<string, AioPgLink>
 */
function aio_pg_links()
{
    if (AioPgLinks::$links !== null)
        return AioPgLinks::$links;
    $path = aio_data_dir() !== '' ? aio_data_dir() . '/' . AIO_PG_LINKS_FILE : '';
    $j = $path !== '' && is_file($path) ? json_decode(strval(file_get_contents($path)), true) : null;
    $links = array();
    foreach (is_array($j) && isset($j['links']) && is_array($j['links']) ? $j['links'] : array() as $mid => $l)
    {
        $l = aio_pg_link_clean($l);
        if ($l && preg_match('/^' . AIO_DUNE_ID . '$/D', strval($mid)))
            $links[strval($mid)] = $l;
    }
    AioPgLinks::$links = $links;
    aio_pg_rev_build();
    return $links;
}

// A pair as stored, of the file or of a source -> its fields that are good, null for none.
/**
 * @param mixed $l
 * @return AioPgLink|null
 */
function aio_pg_link_clean($l)
{
    if (!is_array($l))
        return null;
    if (isset($l['none']) && is_int($l['none']))
        return array('none' => $l['none']);
    if (isset($l['fail']) && is_int($l['fail']))
        return array('fail' => $l['fail']);
    $out = array();
    $imdb = aio_str($l, 'imdb');
    if (preg_match('/^tt[0-9]{1,10}$/D', $imdb))
        $out['imdb'] = $imdb;
    foreach (array('tmdb', 'tmdbtv') as $k)
    {
        $v = aio_str($l, $k);
        if (preg_match('/^[0-9]{1,10}$/D', $v))
            $out[$k] = $v;
    }
    if (!$out)
        return null;
    $type = aio_str($l, 'type');
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
    if ($seasons && aio_str($l, 'src') === 'meta')
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
// badge want them (aio_pg_wanted).
/**
 * @param AioPgLink|null $l
 * @return bool
 */
function aio_pg_no_seasons($l)
{
    return $l && !isset($l['none']) && !isset($l['fail']) && !isset($l['seasons']) &&
        ((isset($l['type']) && $l['type'] === 'series') || isset($l['tmdbtv']));
}

// The episodes of the pair $l out by today (UTC; an episode of its soon with no date or a later one is not
// out), season => list ascending (aio_wh_pick), seasons of no episode out left out; none without seasons. The
// season $s of a record whose episode $e is past the episodes of the season (an anime numbered on: One Piece S2
// asked as S1 E62, aio_cm_req_s), or season 1 of a pair of on: the episodes of $s and the seasons after it
// numbered on in it, as the own card asks them; $e past them all (the pair is behind): none, the record as it is.
/**
 * @param AioPgLink|null $l
 * @param int $s
 * @param int $e
 * @return array<int, list<int>>
 */
function aio_pg_eps($l, $s = -1, $e = -1)
{
    if (!$l || !isset($l['seasons']))
        return array();
    $today = gmdate('Y-m-d');
    $soon = isset($l['soon']) ? $l['soon'] : array();
    $eps = array();
    foreach ($l['seasons'] as $n => $count)
    {
        $list = array();
        for ($x = 1; $x <= min($count, AIO_PG_EPS_MAX); $x++)
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

// AioPgLinks::$rev of AioPgLinks::$links: "imdb:tt..", "tmdb:N", "tmdbtv:N" => the movie_ids with it (two cards of
// the Dune can have the same ids).
/**
 * @return void
 */
function aio_pg_rev_build()
{
    $rev = array();
    foreach (AioPgLinks::$links ? AioPgLinks::$links : array() as $mid => $l)
    {
        foreach (array('imdb', 'tmdb', 'tmdbtv') as $k)
        {
            if (isset($l[$k]))
                $rev["$k:{$l[$k]}"][] = strval($mid);
        }
    }
    AioPgLinks::$rev = $rev;
}

// The pairs written whole (aio_write_file). -> written.
/**
 * @return bool
 */
function aio_pg_links_save()
{
    $path = aio_data_dir() !== '' ? aio_data_dir() . '/' . AIO_PG_LINKS_FILE : '';
    $data = json_encode(array('v' => 1, 'links' => aio_pg_links()));
    $ok = $path !== '' && is_string($data) && aio_write_file($path, $data);
    if (!$ok)
        aio_log('progress: pairs not saved');
    return $ok;
}

// The pair of movie_id $mid set to $l (aio_pg_link_clean): the fields of a pair it has not kept; a pair
// over none or fail; none or fail only over none or fail. Over a pair the seasons: of the meta (src) replace those of
// the pair whole, soon and on with them (the records of Melange are numbered by the own card: the Dune may have S1 of
// 20 where Cinemeta has S1 and S2 of 10); those of the card of the shell (numbered by the Dune) do not touch those of
// the meta but add the seasons after its last one with their soon (not to an anime numbered on: on; not when a season
// of the meta has fewer episodes by the Dune: numbered otherwise, Cinemeta S1 of 50 where the Dune has S1 and S2 of
// 25), those of $undated (season_numbers of play_action: no dates, the episodes not out counted) none (seen after the
// own card is opened); else the larger number of episodes of each. soon replaced only by a source of dates ($l has
// it, even empty: the card of the shell, the meta). A pair of before src (the seasons of the meta mixed in by the
// larger) is of the meta again at the next one. Written (aio_pg_links_save) only on a change and with $save; else
// the caller writes them once. No log line: the caller says what it found. -> it changed.
/**
 * @param string $mid
 * @param mixed $l
 * @param bool $save
 * @param bool $undated
 * @return bool
 */
function aio_pg_link_put($mid, $l, $save = true, $undated = false)
{
    $dated = is_array($l) && array_key_exists('soon', $l);
    $l = aio_pg_link_clean($l);
    if (!$l || !preg_match('/^' . AIO_DUNE_ID . '$/D', $mid))
        return false;
    $links = aio_pg_links();
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
    $links[$mid] = $new;
    AioPgLinks::$links = $links;
    aio_pg_rev_build();
    if ($save)
        aio_pg_links_save();
    return true;
}

// The pair of the card of play_action: movie_str of the Dune (aio_movie) has its id, IMDb, the type
// and season_numbers (no dates: not added to a pair of the meta, aio_pg_link_put).
/**
 * @param AioMovie $mv
 * @return void
 */
function aio_pg_link_movie($mv)
{
    if ($mv['dune_id'] !== '' && $mv['imdb'] !== '' && aio_pg_link_put($mv['dune_id'], array('imdb' => $mv['imdb'],
        'type' => $mv['series'] ? 'series' : 'movie', 'seasons' => $mv['seasons']), true, true))
        aio_log('progress: a pair of play_action');
}

// The episodes of the series of card $id of the own card by its meta $m to the pair (src meta: over the seasons of the
// Dune, aio_pg_link_put): its episodes now in the row "Continue" and the badges. Each season its number of episodes,
// "s:e" of those not out => the date ('' none). An anime numbered on whole (aio_cm_numbered_on: One Piece S2 E62-E77,
// asked as S1 E62): the episodes of each season by their place in it, on (aio_pg_eps numbers them on in season 1, as
// the own card asks them). None: a season asked by another number but not all of them, a season numbered not 1 and up
// (numbered on: not on from the season before).
/**
 * @param string $id
 * @param AioCardMeta $m
 * @return void
 */
function aio_pg_link_meta($id, $m)
{
    $on = aio_cm_numbered_on($m);
    $seasons = array();
    $soon = array();
    $first = 1;
    foreach ($m['seasons'] as $s => $eps)
    {
        if ($s < 1 || !$eps)
            continue;
        if (!$on && aio_cm_req_s($m, $s) !== $s)
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
    if ($seasons && $m['imdb'] !== '' && aio_pg_link_put($id, $l))
        aio_log('progress: the episodes of the meta' . ($on ? ', numbered on' : ''));
}

// The pair of the card $id found for tile $t (aio_card_id): its IMDb, its TMDB by its type.
/**
 * @param string $id
 * @param AioCatTile $t
 * @return void
 */
function aio_pg_link_tile($id, $t)
{
    $l = array('imdb' => $t['imdb_id'], 'type' => $t['type']);
    if ($t['_tmdbId'] !== '' && ($t['type'] === 'movie' || $t['type'] === 'series'))
        $l[$t['type'] === 'series' ? 'tmdbtv' : 'tmdb'] = $t['_tmdbId'];
    if ($id !== '' && aio_pg_link_put($id, $l))
        aio_log('progress: a pair of ENTER');
}

// The movie_ids of the card of tile $t by the pairs: of its IMDb, then of its TMDB of its type. A tile of no
// type (a row of anime) has no TMDB key: the numbers of movies and series of TMDB are not the same ids.
/**
 * @param AioCatTile $t
 * @return list<string>
 */
function aio_pg_tile_cards($t)
{
    aio_pg_links();
    $keys = array('imdb:' . $t['imdb_id']);
    if ($t['_tmdbId'] !== '' && ($t['type'] === 'movie' || $t['type'] === 'series'))
        $keys[] = ($t['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $t['_tmdbId'];
    $out = array();
    foreach ($keys as $k)
    {
        foreach (isset(AioPgLinks::$rev[$k]) ? AioPgLinks::$rev[$k] : array() as $mid)
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
function aio_pg_out_of_lists($text, $offs, $first = false)
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
// found (with no ids in them: the card has none). -> the pair (aio_pg_link_clean), null for none.
/**
 * @param string $text
 * @param bool $found
 * @return AioPgLink|null
 */
function aio_pg_parse_card($text, &$found = false)
{
    $found = false;
    $obj = '(\{[^{}]*+\}|\[\])';
    if (!preg_match_all('/(?<!"type":\d,)(?<!"type":\d\d,)"sources":' . $obj . ',"auxSources":' . $obj . '/', $text, $m,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
        return null;
    $offs = array();
    foreach ($m as $one)
        $offs[] = intval($one[0][1]);
    $top = aio_pg_out_of_lists($text, $offs);
    $last = null;
    foreach ($m as $one)
    {
        if ($top[intval($one[0][1])])
            $last = $one;
    }
    if (!$last)
        return null;
    $found = true;
    $ids = array('imdb' => 'IMDB', 'tmdb' => 'TheMovieDB', 'tmdbtv' => 'TheMovieDBTV');
    $l = array();
    foreach ($ids as $k => $src)
    {
        foreach (array($last[1][0], $last[2][0]) as $obj)
        {
            if (!isset($l[$k]) && preg_match('/"' . $src . '":"([^"]*)"/', $obj, $v))
                $l[$k] = $v[1];
        }
    }
    if (preg_match_all('/"type":"(single|series)"/', $text, $t, PREG_SET_ORDER | PREG_OFFSET_CAPTURE))
    {
        $offs = array();
        foreach ($t as $one)
            $offs[] = intval($one[0][1]);
        $top = aio_pg_out_of_lists($text, $offs, true);
        foreach ($t as $one)
        {
            if (!empty($top[intval($one[0][1])]))
            {
                $l['type'] = $one[1][0] === 'series' ? 'series' : 'movie';
                break;
            }
        }
    }
    return aio_pg_link_clean(array_merge($l, aio_pg_parse_seasons($text)));
}

// A string of the raw text of a card ($m[0], $m[1] ':' of a key) -> it, a key or a date "YYYY-MM-DD"; else "".
/**
 * @param array<int, string> $m
 * @return string
 */
function aio_pg_strip_str($m)
{
    return $m[1] === ':' || preg_match('/^"[0-9]{4}-[0-9]{2}-[0-9]{2}"$/D', $m[0]) ? $m[0] : '""';
}

// The seasons of a card of the dunemdb API in its raw text (aio_pg_parse_card): the first "seasons" of episodes in no
// list, its strings but the keys and the dates cut out (the titles and descriptions are most of it, may hold
// brackets) and only that decoded -> seasons (season => its last episode by number, season 0 left out, as
// season_numbers of the shell) and soon (aio_pg_link_clean: "s:e" => premiered, '' for none, of those not out
// today, UTC); none for none.
/**
 * @param string $text
 * @return array{seasons?: array<int, int>, soon?: array<string, string>}
 */
function aio_pg_parse_seasons($text)
{
    if (!preg_match_all('/"seasons":\[\{"number":/', $text, $m, PREG_OFFSET_CAPTURE))
        return array();
    $offs = array();
    foreach ($m[0] as $one)
        $offs[] = intval($one[1]);
    $at = -1;
    foreach (aio_pg_out_of_lists($text, $offs, true) as $o => $top)
    {
        if ($top)
            $at = $o;
    }
    if ($at < 0)
        return array();
    $tail = preg_replace_callback('/"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"(:?)/', 'aio_pg_strip_str',
        substr($text, $at + strlen('"seasons":')));
    if (!is_string($tail) || !preg_match('/\[(?:[^\[\]]++|(?R))*+\]/A', $tail, $arr))
    {
        // pcre.backtrack_limit of PHP 5.3.6 is 100000: a card of 5 seasons of 300 episodes is past it.
        if (preg_last_error() !== PREG_NO_ERROR)
            aio_log(sprintf('link: the seasons of a card not read, preg error %d, %d B', preg_last_error(), strlen($text)));
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
            if ($e < 1 || $e > AIO_PG_EPS_MAX)
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
function aio_pg_api_head($body)
{
    $p = strpos($body, '"result":');
    $j = json_decode($p === false ? $body : rtrim(substr($body, 0, $p), " \t\r\n,") . '}', true);
    if ((!is_array($j) || !isset($j['code'])) && strlen($body) <= AIO_PG_HEAD_MAX)
        $j = json_decode($body, true);
    $code = is_array($j) && isset($j['code']) && is_numeric($j['code']) ? intval($j['code']) : -1;
    return array($code, strtolower(aio_str($j, 'message')));
}

// --- The cards of the shell.

// The path of the card of the shell of movie_id $mid, '' for none.
/**
 * @param string $mid
 * @return string
 */
function aio_pg_shell_path($mid)
{
    foreach (array(AIO_PG_SHELL_DIR, AIO_PG_SHELL_DIR2) as $dir)
    {
        if (is_file("$dir/$mid.json"))
            return "$dir/$mid.json";
    }
    return '';
}

// The pair of $mid from the card of the shell, null for none (no file, too big, no ids). Not asked again
// for it while php_server runs.
/**
 * @param string $mid
 * @return AioPgLink|null
 */
function aio_pg_shell_pair($mid)
{
    AioPgLinks::$shell_seen[$mid] = true;
    $path = preg_match('/^[0-9a-f]{24}$/D', $mid) ? aio_pg_shell_path($mid) : '';
    $size = $path !== '' ? filesize($path) : 0;
    if ($size <= 0 || $size > AIO_PG_SHELL_MAX_BYTES)
        return null;
    return aio_pg_parse_card(strval(file_get_contents($path)));
}

// The cards of the history a card of the dunemdb API is wanted for, their records ending the latest first: with no
// pair (a failed request too), or a pair of a series with no seasons (aio_pg_no_seasons: of ENTER, of a version before
// the seasons) with an episode record. -> movie_id => end.
/**
 * @return array<string, int>
 */
function aio_pg_wanted()
{
    $links = aio_pg_links();
    $out = array();
    foreach (aio_pg_index() as $mid => $ent)
    {
        $l = isset($links[$mid]) ? $links[$mid] : null;
        if (!$l || isset($l['fail']) || ($ent['series'] && $ent['series'][0] && aio_pg_no_seasons($l)))
            $out[strval($mid)] = $ent['end'];
    }
    arsort($out);
    return $out;
}

// The cards of aio_pg_wanted from the cards of the shell, the latest first, in AIO_PG_SHELL_MS (one card at least),
// each once while php_server runs; the pairs written once. A line of the log only when one was read.
/**
 * @return void
 */
function aio_pg_shell_step()
{
    $t = microtime(true);
    $read = 0;
    $found = 0;
    foreach (aio_pg_wanted() as $mid => $end)
    {
        if (isset(AioPgLinks::$shell_seen[$mid]))
            continue;
        if ($read && (microtime(true) - $t) * 1000 >= AIO_PG_SHELL_MS)
            break;
        $read++;
        $l = aio_pg_shell_pair($mid);
        if ($l && aio_pg_link_put($mid, $l, false))
            $found++;
    }
    if ($found)
        aio_pg_links_save();
    if ($read)
        aio_log(sprintf('progress: shell cards: %d looked at, %d pairs, %.0f ms', $read, $found, (microtime(true) - $t) * 1000));
}

// --- The request.

// dune_auth for a request, '' for none: a line of the log only when it went.
/**
 * @return string
 */
function aio_pg_token()
{
    $token = aio_card_token();
    if ($token === '' && !AioPgLinks::$no_auth)
        aio_log('link: no dune_auth');
    AioPgLinks::$no_auth = $token === '';
    return $token;
}

// The pair of $mid now: the card of the shell (not looked at yet), else movie/<id> of the dunemdb API with
// dune_auth, as the shell opens its card (dunemdb_api.php api_movie), in $total s (whole s; under 1 none). A pair of
// a series with no seasons (aio_pg_no_seasons): the card for them, of the shell only with them; none in the reply -
// sq, the time (the pair is not touched by none or fail).
// Kept: a pair; none (code 100 with no ids in the sources of the card, or the message "not found", as
// aio_card_ask); fail (no dune_auth, transport, "sources" not found by the parse, another answer: asked again
// after AIO_PG_FAIL_S). No connection, a timeout or 5xx: no request in
// AIO_PG_PAUSE_S (aio_pg_link_step). One log line with the size and the time, no token. -> st: pair, none,
// fail; link: the pair or null.
/**
 * @param string $mid
 * @param float $total
 * @return array{st: string, link: AioPgLink|null}
 */
function aio_pg_link_now($mid, $total = AIO_PG_LINK_S)
{
    $links = aio_pg_links();
    $need = aio_pg_no_seasons(isset($links[$mid]) ? $links[$mid] : null);
    $l = isset(AioPgLinks::$shell_seen[$mid]) ? null : aio_pg_shell_pair($mid);
    if ($l)
    {
        aio_pg_link_put($mid, $l);
        if (!$need || isset($l['seasons']))
            return array('st' => 'pair', 'link' => $l);
    }
    if (!preg_match('/^[0-9a-f]{24}$/D', $mid))
        return array('st' => 'none', 'link' => null);
    // The request in what is left, whole seconds of curl: under 1 s none.
    if ($total < 1)
        return array('st' => 'fail', 'link' => null);
    $token = aio_pg_token();
    if ($token === '')
        return array('st' => 'fail', 'link' => null);
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get(aio_card_api() . "movie/$mid?movieAnswerType=full_with_recommendations&lang=ru&$token",
        $err, min(AIO_PG_LINK_CONNECT, intval(floor($total))), intval(floor($total)), AIO_CAT_MAX_BYTES);
    $st = 'fail';
    if ($err === '' && $code === 200)
    {
        list($api, $msg) = aio_pg_api_head($body);
        $found = false;
        $l = $api === 100 ? aio_pg_parse_card($body, $found) : null;
        $st = $l ? 'pair' : ($api === 100 || $msg === 'not found' ? 'none' : 'fail');
        // "sources" the parse did not find (a format not known): not "no pair" for good.
        if ($st === 'none' && $api === 100 && !$found && strpos($body, '"sources":') !== false)
            $st = 'fail';
    }
    $down = ($err !== '' && $err !== AIO_HTTP_TOO_BIG) || ($err === '' && $code >= 500 && $code < 600);
    if ($down)
        AioPgLinks::$pause = time() + AIO_PG_PAUSE_S;
    // Only the number of curl: its text may hold the address.
    $why = $err !== '' ? (preg_match('/^curl [0-9]+/', $err, $e) ? $e[0] : ($err === AIO_HTTP_TOO_BIG ? 'too big' : 'failed')) :
        "HTTP $code";
    $got = $st === 'pair' && isset($l['seasons']);
    aio_log(sprintf('link: movie/%s %d B, %.0f ms, %s, %s%s%s', $mid, strlen($body), (microtime(true) - $t) * 1000, $why, $st,
        $need ? ($got ? ', seasons' : ', no seasons') : '', $down ? ', pause' : ''));
    aio_pg_link_put($mid, $st === 'pair' ? $l : array($st => time()), !$need || $got);
    if ($need && !$got)
    {
        $links = aio_pg_links();
        $links[$mid]['sq'] = time();
        AioPgLinks::$links = $links;
        aio_pg_links_save();
    }
    return array('st' => $st, 'link' => $l);
}

// s of the pause of the requests left (aio_pg_link_now: no connection, 5xx); 0: none. A pause ending past
// AIO_PG_PAUSE_S from now (the clock set back) is none.
/**
 * @return int
 */
function aio_pg_paused()
{
    $left = AioPgLinks::$pause - time();
    return $left > 0 && $left <= AIO_PG_PAUSE_S ? $left : 0;
}

// The cards a step asks for (aio_pg_link_step): of aio_pg_wanted, the latest first, records that ended within
// AIO_PG_LINK_AGE_S, a failure older than AIO_PG_FAIL_S (sq of a series with no seasons too), ids of the Dune.
// -> movie_ids.
/**
 * @return list<string>
 */
function aio_pg_link_queue()
{
    $now = time();
    $links = aio_pg_links();
    $out = array();
    foreach (aio_pg_wanted() as $mid => $end)
    {
        $mid = strval($mid);
        if ($end <= 0 || $now - $end > AIO_PG_LINK_AGE_S || !preg_match('/^[0-9a-f]{24}$/D', $mid))
            continue;
        $t = isset($links[$mid]['fail']) ? $links[$mid]['fail'] : (isset($links[$mid]['sq']) ? $links[$mid]['sq'] : null);
        if ($t !== null && $now - $t >= 0 && $now - $t < AIO_PG_FAIL_S)
            continue;
        $out[] = $mid;
    }
    return $out;
}

// The lazy pairs: the cards of aio_pg_link_queue, up to AIO_PG_LINK_N requests in $limit s (the second one only
// with AIO_PG_LINK_NEXT_S left; a $tick, aio_pg_tick: one). None in a pause (aio_pg_paused) or with no dune_auth.
// One line of the log of a step that asked; of a $tick always. -> how many were asked.
/**
 * @param float $limit
 * @param bool $tick
 * @return int
 */
function aio_pg_link_step($limit, $tick = false)
{
    $t = microtime(true);
    $n = 0;
    $got = 0;
    foreach (aio_pg_paused() ? array() : aio_pg_link_queue() as $mid)
    {
        $left = $limit - (microtime(true) - $t);
        if ($n >= ($tick ? 1 : AIO_PG_LINK_N) || $left < ($n ? AIO_PG_LINK_NEXT_S : 1))
            break;
        if ($n === 0 && aio_pg_token() === '')
            break;
        $pause = AioPgLinks::$pause;
        $r = aio_pg_link_now($mid, $left);
        $n++;
        $got += $r['st'] === 'pair' ? 1 : 0;
        if (AioPgLinks::$pause !== $pause)
            break;
    }
    if ($n || $tick)
        aio_log(sprintf('link: %s: %d asked, %d pairs, %.0f ms', $tick ? 'tick' : 'step', $n, $got, (microtime(true) - $t) * 1000));
    return $n;
}
