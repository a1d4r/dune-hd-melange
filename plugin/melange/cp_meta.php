<?php
// The meta of the tiles of the row "Continue" (continue.php) and of "Мой список" (view_my_list.php): the tile of the
// meta of the catalog server (meta/<type>/<id>.json, as the own card asks it: the fields of the info panel of a tile of
// a row and its rating), kept in a file of data_dir. At the build of the rows (aio_cpm_build) a title with none kept
// (or one kept over AIO_CPM_STALE_S ago) gets the tile of a row in memory, else of a copy of a row on the disk, else
// (none kept) one batch of requests in AIO_CPM_BUILD_S; the rest asked lazily at the step of the pairs (links.php: r25
// the tick of the timer of the main screen, r24 the end of an opening), the pairs first, then one batch at once
// (curl_multi), else AIO_CPM_N requests of the step at most with theirs. The own card puts the tile of its meta there
// too (aio_cm_get), of a title of the history or of the list; a title into the list the tile of it with no request
// (aio_cpm_of_add: of the own card in memory, else the best one); the import of the Favorites of the Dune asks the meta
// of every title it adds at once (aio_cpm_fill).
// Needs main.php (aio_log, aio_data_dir, aio_file_sig, aio_write_file, aio_http_mark, AIO_HTTP_PAR), catalog.php
// (aio_cat_get, aio_cat_get_many, aio_cat_tile, aio_cat_cache_tiles),
// card_meta.php (aio_cm_same, aio_cm_mem_tile), continue.php (aio_cp_tile_map, aio_cp_map_tile, aio_cp_best_tile,
// aio_cp_items, aio_cp_ids), progress.php (aio_pg_index), links.php (aio_pg_tile_cards), my_list.php (aio_ml_list, aio_ml_in), view_catalog.php (aio_catv_is_down,
// AIO_CATV_ALL_COLS, AIO_CATV_ALL_ROWS).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// In data_dir: "<type>:<IMDb | tmdb:<id>>" => t (when written), tile (null: the request failed then).
define('AIO_CPM_FILE', 'cp_meta.json');
// Titles kept at most and the size of the file (a tile ~3-4.5 KB, the Russian escaped): "Мой список" holds ~570 at most
// (AIO_ML_COPY_MAX), the rows "Continue" 20 of a type. The titles neither in the list nor of the history (aio_cpm_kept;
// the own card of another card of the history, a title out of the list) AIO_CPM_OTHER_MAX at most, as the file was
// before the list had its meta here. Over them the oldest written go first of those others, then the oldest of
// the rest. The file is read once by php_server and written whole at a change only: 700 titles, 2 MB measured on a Mac
// (docker) - json_decode 17 ms, aio_cpm_read 70 ms (aio_cat_tile of each entry mostly), a write 16-18 ms; the Dune ~6
// times that: a read ~0.4-0.6 s once, a write ~0.1 s and the flash. A user with no list: AIO_CPM_OTHER_MAX and the
// titles of the history, as before.
define('AIO_CPM_MAX', 700);
define('AIO_CPM_MAX_BYTES', 3145728);
define('AIO_CPM_OTHER_MAX', 60);
// A failure of a title (big, 404, not its meta) is asked again after this many s. No connection, 429 or 5xx: the
// server, not the title - no request for AIO_CPM_PAUSE_S (memory of php_server), the title not kept. A timeout is of
// the title (a meta the server has not kept, a big series, in the ~1.5 s of a tick): the first one of it in a
// php_server not kept, asked again at the next step (the server has it by then, mostly), the second kept as its
// failure, no pause either (the own card asks AIO_CM_SERIES_BYTES in AIO_CM_SERIES_TOTAL s and keeps its tile here);
// of the server only right after a timeout of another title (within AIO_CPM_TIMED_OUT_S) with no reply of the server
// between (aio_cpm_judge): a pause.
define('AIO_CPM_FAIL_S', 86400);
define('AIO_CPM_PAUSE_S', 1800);
define('AIO_CPM_TIMED_OUT_S', 180);
// Requests of a step one by one at most (no curl_multi), those of the pairs in it counted; one starts with
// AIO_CPM_MIN_S left at least, its timeout what is left, to the ms (aio_http_get: CURLOPT_TIMEOUT_MS; a pair of ~0.6 s
// of a tick leaves ~0.9 s). With curl_multi: one batch of AIO_CPM_STEP_PAR titles at most a step, each its timeout the
// whole of what is left; of the import AIO_HTTP_PAR at once.
define('AIO_CPM_N', 2);
define('AIO_CPM_STEP_PAR', 4);
define('AIO_CPM_MIN_S', 0.5);
// A timeout of a request given less than this many s (the rest of a tick) is of that short rest, not of the server or
// the title (a meta the server has not kept takes 0.2-1.9 s): no pause, the title not marked or kept, asked at the
// next step; so is a timeout of no connection in it (curl 28: its connect is the rest too, aio_cat_req).
define('AIO_CPM_SHORT_S', 1.0);
// r25: the tick (aio_pg_tick, links.php) - the pair and the meta - within this many s from its start (a key pressed
// waits for it); the pair in it too, its timeout the whole s of it.
define('AIO_CPM_TICK_S', 1.5);
// The tiles of the screen "Продолжить" or "Мой список" asked after those of the rows, at most (the screen built again
// gives the next ones).
define('AIO_CPM_MORE', 40);
// The meta of the tiles of the rows "Continue" and "Мой список" no row has, asked at the build of a main screen of rows
// (aio_cpm_build), s at most from its start, the look at the rows in memory and on the disk in it: r25 its reply (the
// view with the first rows) waits for it, r24 it is in AIO_CATV_OPEN_S. AIO_CPM_SHORT_S at most (a test holds it): a
// timeout of the build is of its short rest (aio_cpm_judge), never a slow title or the server.
define('AIO_CPM_BUILD_S', 1.0);
// A title cut short at the build (a timeout of its short rest): not asked by the builds for this many s (AioCpm::$cut),
// the tick asks it (a meta that takes longer than the build has would else hold every opening for good).
define('AIO_CPM_BUILD_CUT_S', 180);
// A tile kept this many s ago or more: the build takes a newer one of a row in memory, or of a copy on the disk saved
// after it (a rating of a new title comes days after it, kpRating changes); none - kept as it is, no request.
define('AIO_CPM_STALE_S', 86400);
// A request of the meta of a title of the import of the Favorites of the Dune (aio_cpm_fill), s at most: a meta the
// server has not kept takes 0.2-1.9 s.
define('AIO_CPM_FILL_S', 5.0);
// Requests of the meta of an import at most in AIO_CPM_RATE_S s (the catalog server lets 180 a minute in, the rows of
// the catalog after the import need their share). Tests may define them first.
if (!defined('AIO_CPM_RATE_N'))
    define('AIO_CPM_RATE_N', 120);
if (!defined('AIO_CPM_RATE_S'))
    define('AIO_CPM_RATE_S', 60.0);

class AioCpm
{
    // AIO_CPM_FILE as read ($sig: aio_file_sig of it then); null: not read.
    /** @var array<string, AioCpmEntry>|null */
    public static $cache = null;
    /** @var string */
    public static $sig = '';
    // The tiles of the row of the main screen built last (aio_catv_main_view, aio_cp_row_defs) with no tile of a row
    // in memory and none here, in its order: array(type, IMDb, TMDB); for the step. Not of the tab "TV".
    /** @var list<array{string, string, string}> */
    public static $miss = array();
    // The same of the row "Мой список" (our list, not the Favorites of the Dune) of that screen (aio_ml_row_defs): asked
    // after those of "Continue".
    /** @var list<array{string, string, string}> */
    public static $ml = array();
    // The same of the screen "Продолжить" or "Мой список" built last (view_my_list.php), its first AIO_CPM_MORE: asked
    // after those of the rows; a row built again keeps them.
    /** @var list<array{string, string, string}> */
    public static $more = array();
    // No request before this time; 0: none.
    /** @var int */
    public static $pause = 0;
    // The titles whose request timed out once since the start of php_server (the next one kept as a failure).
    /** @var array<string, bool> */
    public static $slow = array();
    // The title whose request timed out last, no reply of the server since (aio_cpm_judge, aio_cpm_of_card); '':
    // none; when (time()).
    /** @var string */
    public static $timed_out = '';
    /** @var int */
    public static $timed_t = 0;
    // The titles cut short at a build (aio_cpm_build): key => when (time()); the builds do not ask them for
    // AIO_CPM_BUILD_CUT_S. Not in the file.
    /** @var array<string, int> */
    public static $cut = array();
}

// The key of a title of type $type with IMDb $imdb, else TMDB $tmdb ('' none), as aio_cm_id; '' for none.
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @return string
 */
function aio_cpm_key($type, $imdb, $tmdb)
{
    if ($type !== 'movie' && $type !== 'series')
        return '';
    return $imdb !== '' ? "$type:$imdb" : ($tmdb !== '' ? "$type:tmdb:$tmdb" : '');
}

// The file: from memory, read again when it is another one. A broken file or entry is none; a tile parsed again
// (aio_cat_tile), of the type of its key.
/**
 * @return array<string, AioCpmEntry>
 */
function aio_cpm_read()
{
    $sig = aio_file_sig(AIO_CPM_FILE);
    if (AioCpm::$cache !== null && $sig === AioCpm::$sig)
        return AioCpm::$cache;
    $j = $sig !== '' ? json_decode(strval(file_get_contents(aio_data_dir() . '/' . AIO_CPM_FILE)), true) : null;
    $out = array();
    foreach (is_array($j) && isset($j['m']) && is_array($j['m']) ? $j['m'] : array() as $key => $e)
    {
        $key = strval($key);
        if (!preg_match('/^(movie|series):(tt[0-9]{1,10}|tmdb:[0-9]{1,10})$/D', $key, $k) || !is_array($e) ||
            !isset($e['t']) || !is_int($e['t']) || !array_key_exists('tile', $e))
            continue;
        $tile = is_array($e['tile']) ? aio_cat_tile($e['tile'], $k[1]) : null;
        if ($e['tile'] !== null && !$tile)
            continue;
        if ($tile)
            $tile['type'] = $k[1];
        $out[$key] = array('t' => $e['t'], 'tile' => $tile);
    }
    AioCpm::$cache = $out;
    AioCpm::$sig = $sig;
    return $out;
}

// Entry $key: tile $tile (null: a failure), now (aio_cpm_put_all). -> written (or the same).
/**
 * @param string $key
 * @param AioCatTile|null $tile
 * @return bool
 */
function aio_cpm_put($key, $tile)
{
    return aio_cpm_put_all(array($key => $tile));
}

// Entries $put (key => tile, null: a failure), now, at one write of the file whole (aio_write_file: a temporary file
// and rename), within the limits of aio_cpm_json; the memory is the file written then.
// The same tile again: nothing written, but of the keys of $renew (the build, a tile kept long ago: its time now). ->
// written (or nothing new).
/**
 * @param array<string, AioCatTile|null> $put
 * @param array<string, bool> $renew
 * @return bool
 */
function aio_cpm_put_all($put, $renew = array())
{
    if (aio_data_dir() === '')
        return false;
    $c = aio_cpm_read();
    $now = time();
    $n = 0;
    foreach ($put as $key => $tile)
    {
        $key = strval($key);
        if ($tile && isset($c[$key]) && $c[$key]['tile'] === $tile && !isset($renew[$key]))
            continue;
        // The order of the file is that of writing: the oldest first.
        unset($c[$key]);
        $c[$key] = array('t' => $now, 'tile' => $tile);
        $n++;
    }
    if (!$n)
        return true;
    $json = aio_cpm_json($c);
    $ok = $json !== '' && aio_write_file(aio_data_dir() . '/' . AIO_CPM_FILE, $json);
    AioCpm::$cache = $ok ? $c : null;
    AioCpm::$sig = $ok ? aio_file_sig(AIO_CPM_FILE) : '';
    return $ok;
}

// The text of the file of entries $c: of the titles neither in "Мой список" nor of the history (aio_cpm_kept) the
// AIO_CPM_OTHER_MAX written last at most; AIO_CPM_MAX entries and AIO_CPM_MAX_BYTES at most, over them out first the
// oldest written of those others, then the oldest of the rest; $c without them. '' if json_encode failed.
/**
 * @param array<string, AioCpmEntry> $c
 * @param-out array<string, AioCpmEntry> $c
 * @return string
 */
function aio_cpm_json(&$c)
{
    $json = json_encode(array('v' => 1, 'm' => (object) $c));
    if (!is_string($json))
        return '';
    // AIO_CPM_OTHER_MAX entries or fewer are within every limit, whoever they are: no aio_cpm_kept.
    if (count($c) <= AIO_CPM_OTHER_MAX && strlen($json) <= AIO_CPM_MAX_BYTES)
        return $json;
    $kept = aio_cpm_kept();
    $order = array();
    $others = 0;
    foreach (array(false, true) as $k)
    {
        foreach ($c as $key => $e)
        {
            if (isset($kept[$key]) === $k)
                $order[] = strval($key);
        }
        if (!$k)
            $others = count($order);
    }
    $i = 0;
    for (; $others > AIO_CPM_OTHER_MAX; $others--)
        unset($c[$order[$i++]]);
    if ($i)
    {
        $json = json_encode(array('v' => 1, 'm' => (object) $c));
        if (!is_string($json))
            return '';
    }
    if (count($c) <= AIO_CPM_MAX && strlen($json) <= AIO_CPM_MAX_BYTES)
        return $json;
    // The size of the text without an entry: its "key":{...} and a comma (the last one has none: the text encoded again
    // till it fits).
    $size = strlen($json);
    while ($i < count($order) && count($c) > 1)
    {
        if (count($c) <= AIO_CPM_MAX && $size <= AIO_CPM_MAX_BYTES)
        {
            $json = json_encode(array('v' => 1, 'm' => (object) $c));
            if (!is_string($json))
                return '';
            $size = strlen($json);
            if ($size <= AIO_CPM_MAX_BYTES)
                return $json;
        }
        $key = $order[$i++];
        $size -= strlen(strval(json_encode($key))) + strlen(strval(json_encode($c[$key]))) + 2;
        unset($c[$key]);
    }
    $json = json_encode(array('v' => 1, 'm' => (object) $c));
    return is_string($json) && strlen($json) <= AIO_CPM_MAX_BYTES ? $json : '';
}

// The keys of the titles not to push out of the file first: of "Мой список" and of the cards of the history (the row
// "Continue" and its screen, aio_cp_items), by IMDb and by TMDB.
/**
 * @return array<string, bool>
 */
function aio_cpm_kept()
{
    $ids = array();
    foreach (aio_ml_list() as $e)
        $ids[] = array($e['type'], $e['imdb_id'], $e['_tmdbId']);
    foreach (aio_cp_items(array('movie', 'series'), AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS) as $mid => $it)
        $ids[] = aio_cp_ids(strval($mid), $it['type'] === 'series');
    $out = array();
    foreach ($ids as $m)
    {
        foreach (array(aio_cpm_key($m[0], $m[1], ''), aio_cpm_key($m[0], '', $m[2])) as $key)
        {
            if ($key !== '')
                $out[$key] = true;
        }
    }
    return $out;
}

// The tile kept of a title of type $type with IMDb $imdb, else TMDB $tmdb, of the entries $c (null: aio_cpm_read); null
// for none (or a failure).
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param array<string, AioCpmEntry>|null $c
 * @return AioCatTile|null
 */
function aio_cpm_tile($type, $imdb, $tmdb, $c = null)
{
    if ($c === null)
        $c = aio_cpm_read();
    $key = aio_cpm_tile_key($type, $imdb, $tmdb, $c);
    return $key !== '' ? $c[$key]['tile'] : null;
}

// The key of the entry of entries $c with the tile kept of a title of type $type with IMDb $imdb, else TMDB $tmdb, as
// aio_cpm_tile finds it; '' for none.
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param array<string, AioCpmEntry> $c
 * @return string
 */
function aio_cpm_tile_key($type, $imdb, $tmdb, $c)
{
    foreach (array(aio_cpm_key($type, $imdb, ''), aio_cpm_key($type, '', $tmdb)) as $key)
    {
        if ($key !== '' && isset($c[$key]) && $c[$key]['tile'])
            return $key;
    }
    return '';
}

// The own card got the meta of tile $t (aio_cm_get, a reply of the server), its tile $tile: kept when the title is of
// the history (a pair of it, aio_pg_tile_cards) or of "Мой список".
/**
 * @param AioCatTile $t
 * @param AioCatTile|null $tile
 * @return void
 */
function aio_cpm_of_card($t, $tile)
{
    // A reply of the catalog server (the own card asks it): no timeout of the lazy meta right before the next one.
    AioCpm::$timed_out = '';
    $key = aio_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
    if (!$tile || $key === '' || (!aio_pg_tile_cards($t) && aio_ml_in($t) === ''))
        return;
    $tile['type'] = $t['type'];
    aio_cpm_put($key, $tile);
}

// The tile of title $t with no request: of its meta of the own card in memory (aio_cm_mem_tile), else the best one
// (aio_cp_best_tile: of the meta kept, else of a row in memory, $map: aio_cp_tile_map of the caller); null for none.
/**
 * @param AioCatTile $t
 * @param array<string, AioCatTile>|null $map
 * @return AioCatTile|null
 */
function aio_cpm_mem($t, $map = null)
{
    $m = aio_cm_mem_tile($t);
    return $m ? $m : aio_cp_best_tile($t['type'], $t['imdb_id'], $t['_tmdbId'], $map);
}

// Title $t into "Мой список" (aio_ml_set): the tile of it in memory (aio_cpm_mem: the own card, a row) kept, no request.
/**
 * @param AioCatTile $t
 * @return void
 */
function aio_cpm_of_add($t)
{
    $key = aio_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
    $m = $key !== '' ? aio_cpm_mem($t) : null;
    if ($m)
        aio_cpm_put($key, $m);
}

// s of the pause of the requests left; 0: none. A pause ending past AIO_CPM_PAUSE_S from now (the clock set back) is
// none.
/**
 * @return int
 * @phpstan-impure
 */
function aio_cpm_paused()
{
    $left = AioCpm::$pause - time();
    return $left > 0 && $left <= AIO_CPM_PAUSE_S ? $left : 0;
}

// The titles of AioCpm::$miss, AioCpm::$ml, then of AioCpm::$more, to ask (aio_cpm_due), in their order. -> keys =>
// array(type, IMDb, TMDB).
/**
 * @return array<string, array{string, string, string}>
 */
function aio_cpm_queue()
{
    $c = aio_cpm_read();
    $now = time();
    $out = array();
    foreach (array_merge(AioCpm::$miss, AioCpm::$ml, AioCpm::$more) as $m)
    {
        $key = aio_cpm_key($m[0], $m[1], $m[2]);
        if ($key === '' || isset($out[$key]) || !aio_cpm_due(isset($c[$key]) ? $c[$key] : null, $now))
            continue;
        $out[$key] = $m;
    }
    return $out;
}

// A title of entry $e (null: none) is to be asked at time $now: none kept, no failure within AIO_CPM_FAIL_S (one of a
// time ahead of the clock is none).
/**
 * @param AioCpmEntry|null $e
 * @param int $now
 * @return bool
 */
function aio_cpm_due($e, $now)
{
    return !$e || (!$e['tile'] && ($now - $e['t'] < 0 || $now - $e['t'] >= AIO_CPM_FAIL_S));
}

// The titles of aio_cpm_queue a step asks for: those no row in memory has now (aio_cp_tile_map), pause or not. ->
// keys => type.
/**
 * @return array<string, string>
 */
function aio_cpm_todo()
{
    $q = aio_cpm_queue();
    $map = $q ? aio_cp_tile_map() : array();
    $todo = array();
    foreach ($q as $key => $m)
    {
        if (aio_cp_map_tile($map, $m[0], $m[1], $m[2]))
            continue;
        $todo[strval($key)] = $m[0];
    }
    return $todo;
}

// One request of the meta of title $key of type $type (aio_cat_get, its timeout $limit s, to the ms; its line of the log
// if $log), judged by aio_cpm_judge -> tile: of it (null: none); keep: kept as it is (the tile, or a failure of the
// title: big, 404, not its meta); stop: no more requests now (a pause, a timeout: it took the rest); cut: a timeout of
// a short rest (under AIO_CPM_SHORT_S).
/**
 * @param string $key
 * @param string $type
 * @param float $limit
 * @param bool $log
 * @return array{tile: AioCatTile|null, keep: bool, stop: bool, cut: bool}
 */
function aio_cpm_ask($key, $type, $limit, $log = true)
{
    $id = substr($key, strlen($type) + 1);
    $g = aio_cat_get("meta/$type/$id.json", $log ? "meta $key" : '', 'meta', $limit, true);
    $r = aio_cpm_judge(array($key => $g), array($key => $type), $limit);
    return $r[$key];
}

// The requests of the meta of titles $types (key => type) at once (aio_cat_get_many, each its timeout $limit s; its
// line of the log if $log), judged as one batch (aio_cpm_judge) -> key => as aio_cpm_ask, in the order of $types;
// null: no curl_multi (the caller asks one by one).
/**
 * @param array<string, string> $types
 * @param float $limit
 * @param bool $log
 * @return array<string, array{tile: AioCatTile|null, keep: bool, stop: bool, cut: bool}>|null
 */
function aio_cpm_ask_many($types, $limit, $log = true)
{
    $paths = array();
    foreach ($types as $key => $type)
        $paths[$key] = array("meta/$type/" . substr($key, strlen($type) + 1) . '.json', $log ? "meta $key" : '');
    $gets = aio_cat_get_many($paths, 'meta', $limit, true);
    return $gets === null ? null : aio_cpm_judge($gets, $types, $limit);
}

// The replies $gets (key => aio_cat_get of the meta of the title, type $types[key]) of requests of $limit s each, asked
// together (one, or a batch at once) -> key => tile, keep, stop, cut (as aio_cpm_ask). Of each title: no connection,
// 429 or 5xx - a pause (AIO_CPM_PAUSE_S), the title not kept; a timeout of under AIO_CPM_SHORT_S given (connected or
// not: curl 28) - cut, nothing kept, no pause, not counted; a timeout, the first of the title in this php_server - not
// kept, asked again; the second - kept
// failed, a pause or not, and of no pause (the title is slow: two such at the head of the queue would else hold it in
// pauses for good). A reply of the server (all but net and a timeout; any order in the batch) - no timeout before it
// counts (AioCpm::$timed_out ''). With none: the first timeouts of two titles or more (of the batch, and the one timed
// out last if within AIO_CPM_TIMED_OUT_S and not timed out again here) - the server, a pause, none of them kept.
/**
 * @param array<string, array{err: string, list: array<mixed>, curl28: bool}> $gets
 * @param array<string, string> $types
 * @param float $limit
 * @return array<string, array{tile: AioCatTile|null, keep: bool, stop: bool, cut: bool}>
 */
function aio_cpm_judge($gets, $types, $limit)
{
    $short = $limit < AIO_CPM_SHORT_S;
    $answered = false;
    // The timeouts of the batch: the first of a title, again of a slow one.
    $first = array();
    $again = array();
    foreach ($gets as $key => $g)
    {
        $key = strval($key);
        if ($g['err'] !== 'timeout' && $g['err'] !== 'net')
            $answered = true;
        else if ($g['err'] === 'timeout' && !$short)
        {
            if (isset(AioCpm::$slow[$key]))
                $again[$key] = true;
            else
                $first[$key] = true;
        }
    }
    $ago = time() - AioCpm::$timed_t;
    $who = $first;
    if (AioCpm::$timed_out !== '' && $ago >= 0 && $ago < AIO_CPM_TIMED_OUT_S && !isset($again[AioCpm::$timed_out]))
        $who[AioCpm::$timed_out] = true;
    $server = !$answered && count($who) >= 2;
    $out = array();
    foreach ($gets as $key => $g)
    {
        $key = strval($key);
        $type = $types[$key];
        $tile = $g['err'] === '' && aio_cm_same($g['list'], substr($key, strlen($type) + 1)) ? aio_cat_tile($g['list'], $type) :
            null;
        if ($tile)
            $tile['type'] = $type;
        $one = isset($first[$key]);
        // A timeout of no connection is "net" (aio_cat_reply): in a short rest its connect was that rest too.
        $cut = $short && ($g['err'] === 'timeout' || ($g['err'] === 'net' && $g['curl28']));
        $pause = ($one && $server) || (!$cut && in_array($g['err'], array('net', 'rate', 'server'), true));
        if ($one)
            AioCpm::$slow[$key] = true;
        if ($pause)
            AioCpm::$pause = time() + AIO_CPM_PAUSE_S;
        $out[$key] = array('tile' => $tile, 'keep' => isset($again[$key]) || (!$pause && !$cut && !$one),
            'stop' => $pause || $cut || $one || isset($again[$key]), 'cut' => $cut);
    }
    if ($answered)
        AioCpm::$timed_out = '';
    else if ($first)
    {
        end($first);
        AioCpm::$timed_out = strval(key($first));
        AioCpm::$timed_t = time();
    }
    return $out;
}

// The requests of the meta of titles $todo (key => type, in their order) in $limit s from $t0, with AIO_CPM_MIN_S left
// at least: with curl_multi one batch of the first $par (aio_cpm_ask_many, each its timeout the whole of what is left);
// else up to $n one by one (each with AIO_CPM_MIN_S left, its timeout the s left: aio_cpm_ask), a pause or a short rest
// - no more. -> array(key => tile of those to keep (null: a failure), asked, got, at once (0: one by one), keys cut short
// (a timeout of a short rest)).
/**
 * @param array<string, string> $todo
 * @param float $t0
 * @param float $limit
 * @param int $par
 * @param int $n
 * @return array{array<string, AioCatTile|null>, int, int, int, list<string>}
 */
function aio_cpm_ask_some($todo, $t0, $limit, $par, $n)
{
    $left = $limit - (microtime(true) - $t0);
    $rs = $todo && $left >= AIO_CPM_MIN_S ? aio_cpm_ask_many(array_slice($todo, 0, $par, true), $left) : null;
    $at_once = $rs !== null ? count($rs) : 0;
    if ($rs === null)
    {
        $rs = array();
        foreach ($todo as $key => $type)
        {
            $key = strval($key);
            $left = $limit - (microtime(true) - $t0);
            if (count($rs) >= $n || $left < AIO_CPM_MIN_S)
                break;
            $rs[$key] = aio_cpm_ask($key, $type, $left);
            if ($rs[$key]['stop'])
                break;
        }
    }
    $put = array();
    $got = 0;
    $cut = array();
    foreach ($rs as $key => $r)
    {
        $key = strval($key);
        if ($r['keep'])
            $put[$key] = $r['tile'];
        $got += $r['tile'] ? 1 : 0;
        if ($r['cut'])
            $cut[] = $key;
    }
    return array($put, count($rs), $got, $at_once, $cut);
}

// The lazy meta: the titles of aio_cpm_todo, in $limit s (aio_cpm_ask_some: a batch of AIO_CPM_STEP_PAR at most, else
// $n one by one; of the batch only $n > 0 counts). The ones kept at one write. One line of the log of a step that
// asked (its path: aio_http_mark); of a $tick always. -> how many were asked.
/**
 * @param float $limit
 * @param int $n
 * @param bool $tick
 * @return int
 */
function aio_cpm_step($limit, $n, $tick = false)
{
    $t = microtime(true);
    $todo = $n > 0 && !aio_cpm_paused() && !aio_catv_is_down() ? aio_cpm_todo() : array();
    list($put, $asked, $got, $par) = aio_cpm_ask_some($todo, $t, $limit, AIO_CPM_STEP_PAR, $n);
    if ($put)
        aio_cpm_put_all($put);
    $mark = $asked ? aio_http_mark($par, $par ? 0 : $asked) : '';
    if ($asked || $tick)
        aio_log(sprintf('continue: meta: %s: %d asked, %d got, %.0f ms%s%s', $tick ? 'tick' : 'step', $asked, $got,
            (microtime(true) - $t) * 1000, $mark !== '' ? ", $mark" : '', AioCpm::$pause > 0 && aio_cpm_paused() ? ', pause' : ''));
    return $asked;
}

// The meta of tiles $tiles kept now (the import of the Favorites of the Dune, view_my_list.php), for each with none
// kept: the tile of it in memory (aio_cpm_mem, $map: aio_cp_tile_map of the caller; no request), else asked
// (AIO_CPM_FILL_S at most; no line of the log each: the caller logs the sum) with AIO_CPM_MIN_S left of $limit s at
// least: AIO_HTTP_PAR at once (aio_cpm_ask_many), else one by one (no curl_multi: aio_cpm_ask), AIO_CPM_RATE_N in
// AIO_CPM_RATE_S s at most (a wait for that within $limit, else the rest lazily); no request in a pause
// (one it set too: the rest lazily, aio_cpm_step) or with the catalog server down. One write of the file. ->
// array(asked, got, at most at once (0: none at once), one by one), the last two for aio_http_mark.
/**
 * @param list<AioCatTile> $tiles
 * @param float $limit
 * @param array<string, AioCatTile>|null $map
 * @return array{int, int, int, int}
 */
function aio_cpm_fill($tiles, $limit, $map = null)
{
    $t0 = microtime(true);
    $asked = 0;
    $got = 0;
    $par = 0;
    $seq = 0;
    $put = array();
    $todo = array();
    foreach ($tiles as $t)
    {
        $key = aio_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
        if ($key === '' || array_key_exists($key, $put) || isset($todo[$key]) ||
            aio_cpm_tile($t['type'], $t['imdb_id'], $t['_tmdbId']))
            continue;
        $m = aio_cpm_mem($t, $map);
        if ($m)
            $put[$key] = $m;
        else
            $todo[$key] = $t['type'];
    }
    $multi = true;
    // When each request went (microtime), of the last AIO_CPM_RATE_S s.
    $sent = array();
    while ($todo)
    {
        $left = $limit - (microtime(true) - $t0);
        if ($left < AIO_CPM_MIN_S || aio_cpm_paused() || aio_catv_is_down())
            break;
        $batch = $multi ? array_slice($todo, 0, AIO_HTTP_PAR, true) : array_slice($todo, 0, 1, true);
        $now = microtime(true);
        while ($sent && $now - $sent[0] >= AIO_CPM_RATE_S)
            array_shift($sent);
        $over = count($sent) + count($batch) - AIO_CPM_RATE_N;
        if ($over > 0)
        {
            $wait = $sent[$over - 1] + AIO_CPM_RATE_S - $now;
            if ($wait > $left - AIO_CPM_MIN_S)
                break;
            usleep((int) ceil($wait * 1000000));
            continue;
        }
        $sent = array_merge($sent, array_fill(0, count($batch), $now));
        $rs = $multi ? aio_cpm_ask_many($batch, min(AIO_CPM_FILL_S, $left), false) : null;
        if ($rs === null)
        {
            $multi = false;
            $key = strval(key($batch));
            $rs = array($key => aio_cpm_ask($key, $batch[$key], min(AIO_CPM_FILL_S, $left), false));
            $seq++;
        }
        else
            $par = max($par, count($rs));
        foreach ($rs as $key => $r)
        {
            $key = strval($key);
            unset($todo[$key]);
            $asked++;
            if ($r['keep'])
                $put[$key] = $r['tile'];
            $got += $r['tile'] ? 1 : 0;
        }
    }
    if ($put)
        aio_cpm_put_all($put);
    return array($asked, $got, $par, $seq);
}

// --- The meta at the build of the rows "Continue" and "Мой список" (aio_catv_main_view, the tab "TV").

// The titles of the rows "Continue" and "Мой список" of each type, AIO_CATV_ROW_TILES of each (those of a row of both
// types, the tab "TV", are among them): of tab $tab first ('': the movies first), those of "Continue" before those of
// the list. -> array(type, IMDb, TMDB) each, in that order.
/**
 * @param string $tab
 * @return list<array{string, string, string}>
 */
function aio_cpm_build_titles($tab)
{
    $idx = aio_pg_index();
    $list = aio_ml_list();
    $out = array();
    foreach ($tab === 'series' ? array('series', 'movie') : array('movie', 'series') as $type)
    {
        // As aio_cp_tile asks its tile.
        foreach (aio_cp_items(array($type)) as $mid => $it)
            $out[] = aio_cp_ids(strval($mid), $idx[strval($mid)]['series'] ? true : false);
        $n = 0;
        foreach ($list as $e)
        {
            if ($e['type'] !== $type)
                continue;
            if ($n++ >= AIO_CATV_ROW_TILES)
                break;
            $out[] = array($e['type'], $e['imdb_id'], $e['_tmdbId']);
        }
    }
    return $out;
}

// The tiles of the rows "Continue" and "Мой список" (aio_cpm_build_titles of tab $tab) before they are built, so their
// tiles have the rating and the info panel at once: of each title with no tile kept, the tile of a row in memory
// (aio_cp_tile_map), else of a copy of a row on the disk (aio_cat_cache_tiles), kept; the titles of neither (to ask:
// aio_cpm_due, as aio_cpm_queue; none cut short at a build within AIO_CPM_BUILD_CUT_S) asked in $limit s from the start
// (0: none, the tab "TV" builds its file with no request): aio_cpm_ask_some, AIO_HTTP_PAR at once, else AIO_CPM_N one
// by one, judged as a step (aio_cpm_judge: the rest is under AIO_CPM_SHORT_S, so a timeout is of it - nothing kept,
// asked again at the step, AioCpm::$cut), none in a pause or with the catalog server down. A tile kept AIO_CPM_STALE_S
// ago or more (or ahead of the clock): the tile of a row in memory, else of a copy saved after it, no request; another
// tile kept, the same - its time now (no look again for AIO_CPM_STALE_S); neither - as it is (the own card renews it).
// One write of the file; the titles not got go to the step as before (AioCpm::$miss, AioCpm::$ml of the rows built).
// One line of the log ("of rows" counts the tiles renewed too).
/**
 * @param string $tab
 * @param float $limit
 * @return void
 */
function aio_cpm_build($tab, $limit)
{
    $t0 = microtime(true);
    $c = aio_cpm_read();
    $now = time();
    $kept = 0;
    $mem = 0;
    $seen = array();
    $put = array();
    $renew = array();
    $want = array();
    $map = null;
    foreach (aio_cpm_build_titles($tab) as $m)
    {
        list($type, $imdb, $tmdb) = $m;
        $key = aio_cpm_key($type, $imdb, $tmdb);
        $ek = $key !== '' ? aio_cpm_tile_key($type, $imdb, $tmdb, $c) : '';
        if ($key === '' || isset($seen[$key]) || isset($seen[$ek]))
            continue;
        $seen[$key] = true;
        $since = 0;
        if ($ek !== '')
        {
            $seen[$ek] = true;
            $since = $c[$ek]['t'];
            if ($now - $since >= 0 && $now - $since < AIO_CPM_STALE_S)
            {
                $kept++;
                continue;
            }
            // Renewed under the key it is kept by.
            $key = $ek;
            $renew[$key] = true;
        }
        if ($map === null)
            $map = aio_cp_tile_map();
        $t = aio_cp_map_tile($map, $type, $imdb, $tmdb);
        if ($t)
        {
            $t['type'] = $type;
            $put[$key] = $t;
            $mem++;
        }
        else
            $want[$key] = array($type, $imdb, $tmdb, $ek !== '' ? $since : 0);
    }
    $disk = $want ? aio_cat_cache_tiles($want) : array();
    $todo = array();
    $due = 0;
    foreach (AioCpm::$cut as $key => $when)
    {
        if ($now - $when < 0 || $now - $when >= AIO_CPM_BUILD_CUT_S)
            unset(AioCpm::$cut[$key]);
    }
    foreach ($want as $key => $m)
    {
        $key = strval($key);
        if (isset($disk[$key]))
            $put[$key] = $disk[$key];
        else if (isset($renew[$key]))
            $kept++;
        else if (aio_cpm_due(isset($c[$key]) ? $c[$key] : null, $now))
        {
            $due++;
            if (!isset(AioCpm::$cut[$key]))
                $todo[$key] = $m[0];
        }
    }
    if (aio_cpm_paused() || aio_catv_is_down())
        $todo = array();
    list($got_put, $asked, $got, $par, $cut) = aio_cpm_ask_some($todo, $t0, $limit, AIO_HTTP_PAR, AIO_CPM_N);
    foreach ($cut as $key)
        AioCpm::$cut[$key] = time();
    $put = array_merge($put, $got_put);
    if ($put)
        aio_cpm_put_all($put, $renew);
    $mark = $asked ? aio_http_mark($par, $par ? 0 : $asked) : '';
    aio_log(sprintf('continue: meta: build: %s: %d kept, %d of rows in memory, %d of rows on the disk, %d asked, %d got, ' .
        '%d left, %.0f ms%s%s', $tab !== '' ? $tab : 'all', $kept, $mem, count($disk), $asked, $got, $due - $got,
        (microtime(true) - $t0) * 1000, $mark !== '' ? ", $mark" : '', $asked && aio_cpm_paused() ? ', pause' : ''));
}
