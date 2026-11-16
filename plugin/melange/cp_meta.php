<?php
// The meta of the tiles of the row "Continue" (continue.php) and of "Мой список" (view_my_list.php) no row in memory
// has: the tile of the meta of the catalog server (meta/<type>/<id>.json, as the own card asks it: the fields of the
// info panel of a tile of a row and its rating), asked lazily at the step of the pairs (links.php: r25 the tick of the
// timer of the main screen, r24 the end of an opening), the pairs first, AIO_CPM_N requests of the step at most with
// theirs; kept in a file of data_dir. The own card puts the tile of its meta there too (aio_cm_get), of a title of the
// history or of the list; a title into the list the tile of it in memory (aio_cpm_of_add); the import of the Favorites
// of the Dune asks the meta of every title it adds at once (aio_cpm_fill).
// Needs main.php (aio_log, aio_data_dir, aio_file_sig, aio_write_file), catalog.php (aio_cat_get, aio_cat_tile),
// card_meta.php (aio_cm_same, aio_cm_mem_tile), continue.php (aio_cp_tile_map, aio_cp_best_tile, aio_cp_items,
// aio_cp_ids), links.php (aio_pg_tile_cards), my_list.php (aio_ml_list, aio_ml_in), view_catalog.php (aio_catv_is_down,
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
// of the server only right after a timeout of another title with no reply between: a pause.
define('AIO_CPM_FAIL_S', 86400);
define('AIO_CPM_PAUSE_S', 1800);
// Requests of a step at most, those of the pairs in it counted; one starts with AIO_CPM_MIN_S left at least, its
// timeout what is left, to the ms (aio_http_get: CURLOPT_TIMEOUT_MS; a pair of ~0.6 s of a tick leaves ~0.9 s).
define('AIO_CPM_N', 2);
define('AIO_CPM_MIN_S', 0.5);
// A timeout of a request given less than this many s (the rest of a tick) is of that short rest, not of the server or
// the title (a meta the server has not kept takes 0.2-1.9 s): no pause, the title not marked or kept, asked at the
// next step.
define('AIO_CPM_SHORT_S', 1.0);
// r25: the tick (aio_pg_tick, links.php) - the pair and the meta - within this many s from its start, typically (a
// key pressed waits for it); a pair alone may take its AIO_PG_LINK_S.
define('AIO_CPM_TICK_S', 1.5);
// The tiles of the screen "Продолжить" or "Мой список" asked after those of the rows, at most (the screen built again
// gives the next ones).
define('AIO_CPM_MORE', 40);
// A request of the meta of a title of the import of the Favorites of the Dune (aio_cpm_fill), s at most: a meta the
// server has not kept takes 0.2-1.9 s.
define('AIO_CPM_FILL_S', 5.0);

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
    // The title whose request timed out last, no reply since (aio_cpm_ask); '': none.
    /** @var string */
    public static $timed_out = '';
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
// The same tile again: nothing written. -> written (or nothing new).
/**
 * @param array<string, AioCatTile|null> $put
 * @return bool
 */
function aio_cpm_put_all($put)
{
    if (aio_data_dir() === '')
        return false;
    $c = aio_cpm_read();
    $now = time();
    $n = 0;
    foreach ($put as $key => $tile)
    {
        $key = strval($key);
        if ($tile && isset($c[$key]) && $c[$key]['tile'] === $tile)
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

// The tile kept of a title of type $type with IMDb $imdb, else TMDB $tmdb; null for none (or a failure).
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @return AioCatTile|null
 */
function aio_cpm_tile($type, $imdb, $tmdb)
{
    $c = aio_cpm_read();
    foreach (array(aio_cpm_key($type, $imdb, ''), aio_cpm_key($type, '', $tmdb)) as $key)
    {
        if ($key !== '' && isset($c[$key]) && $c[$key]['tile'])
            return $c[$key]['tile'];
    }
    return null;
}

// The own card got the meta of tile $t (aio_cm_get), its tile $tile: kept when the title is of the history (a pair of
// it, aio_pg_tile_cards) or of "Мой список".
/**
 * @param AioCatTile $t
 * @param AioCatTile|null $tile
 * @return void
 */
function aio_cpm_of_card($t, $tile)
{
    $key = aio_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
    if (!$tile || $key === '' || (!aio_pg_tile_cards($t) && aio_ml_in($t) === ''))
        return;
    $tile['type'] = $t['type'];
    aio_cpm_put($key, $tile);
}

// The tile of title $t with no request: of its meta of the own card in memory (aio_cm_mem_tile), else the best one
// (aio_cp_best_tile: of a row in memory, $map: aio_cp_tile_map of the caller; else of the meta kept); null for none.
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
 */
function aio_cpm_paused()
{
    $left = AioCpm::$pause - time();
    return $left > 0 && $left <= AIO_CPM_PAUSE_S ? $left : 0;
}

// The titles of AioCpm::$miss, AioCpm::$ml, then of AioCpm::$more, to ask, in their order: none kept, no failure within
// AIO_CPM_FAIL_S. -> keys => array(type, IMDb, TMDB).
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
        $e = $key !== '' && isset($c[$key]) ? $c[$key] : null;
        if ($key === '' || isset($out[$key]) || ($e && ($e['tile'] || ($now - $e['t'] >= 0 && $now - $e['t'] < AIO_CPM_FAIL_S))))
            continue;
        $out[$key] = $m;
    }
    return $out;
}

// A step would ask: a title in the queue, no pause, the catalog server not down.
/**
 * @return bool
 */
function aio_cpm_wanted()
{
    return !aio_cpm_paused() && !aio_catv_is_down() && aio_cpm_queue() !== array();
}

// One request of the meta of title $key of type $type (aio_cat_get, its timeout $limit s, to the ms; its line of the log
// if $log). -> tile: of it
// (null: none); keep: kept as it is (the tile, or a failure of the title: big, 404, not its meta); stop: no more
// requests now. No connection, 429 or 5xx: a pause (AIO_CPM_PAUSE_S), the title not kept. A timeout: the first of the
// title in this php_server not kept, no pause; the second kept failed, no pause; one right after a timeout of another
// title (AioCpm::$timed_out): a pause, not kept; each of them a stop (it took the rest). One of under AIO_CPM_SHORT_S
// given: nothing kept, no pause.
/**
 * @param string $key
 * @param string $type
 * @param float $limit
 * @param bool $log
 * @return array{tile: AioCatTile|null, keep: bool, stop: bool}
 */
function aio_cpm_ask($key, $type, $limit, $log = true)
{
    $id = substr($key, strlen($type) + 1);
    $g = aio_cat_get("meta/$type/$id.json", $log ? "meta $key" : '', 'meta', $limit, true);
    $tile = $g['err'] === '' && aio_cm_same($g['list'], $id) ? aio_cat_tile($g['list'], $type) : null;
    if ($tile)
        $tile['type'] = $type;
    // A timeout of a short rest: nothing (AIO_CPM_SHORT_S). Of a whole one, the first of the title: asked again at the
    // next step; the second: kept failed (the title is slow: one such title holds no row); right after a timeout of
    // another title: the server, a pause.
    $short = $g['err'] === 'timeout' && $limit < AIO_CPM_SHORT_S;
    $timeout = $g['err'] === 'timeout' && !$short;
    $again = $timeout && !isset(AioCpm::$slow[$key]);
    $pause = ($timeout && AioCpm::$timed_out !== '' && AioCpm::$timed_out !== $key) ||
        in_array($g['err'], array('net', 'rate', 'server'), true);
    if ($again)
        AioCpm::$slow[$key] = true;
    if ($pause)
        AioCpm::$pause = time() + AIO_CPM_PAUSE_S;
    if (!$short)
        AioCpm::$timed_out = $timeout ? $key : '';
    return array('tile' => $tile, 'keep' => !$pause && !$short && !$again, 'stop' => $pause || $short || $timeout);
}

// The lazy meta: the titles of aio_cpm_queue no row in memory has now (aio_cp_tile_map), up to $n requests in $limit s
// (each with AIO_CPM_MIN_S left, its timeout the s left: aio_cpm_ask), the ones kept at one write. A pause or a short
// rest: no more of the step. One line of the log of a step that asked; of a $tick always. -> how many were asked.
/**
 * @param float $limit
 * @param int $n
 * @param bool $tick
 * @return int
 */
function aio_cpm_step($limit, $n, $tick = false)
{
    $t = microtime(true);
    $asked = 0;
    $got = 0;
    $put = array();
    $q = $n > 0 && !aio_cpm_paused() && !aio_catv_is_down() ? aio_cpm_queue() : array();
    $map = $q ? aio_cp_tile_map() : array();
    foreach ($q as $key => $m)
    {
        list($type, $imdb, $tmdb) = $m;
        if (($imdb !== '' && isset($map["$type|imdb:$imdb"])) || ($tmdb !== '' && isset($map["$type|tmdb:$tmdb"])))
            continue;
        $left = $limit - (microtime(true) - $t);
        if ($asked >= $n || $left < AIO_CPM_MIN_S)
            break;
        $r = aio_cpm_ask($key, $type, $left);
        $asked++;
        if ($r['keep'])
            $put[$key] = $r['tile'];
        $got += $r['tile'] ? 1 : 0;
        if ($r['stop'])
            break;
    }
    if ($put)
        aio_cpm_put_all($put);
    if ($asked || $tick)
        aio_log(sprintf('continue: meta: %s: %d asked, %d got, %.0f ms%s', $tick ? 'tick' : 'step', $asked, $got,
            (microtime(true) - $t) * 1000, AioCpm::$pause > 0 && aio_cpm_paused() ? ', pause' : ''));
    return $asked;
}

// The meta of tiles $tiles kept now (the import of the Favorites of the Dune, view_my_list.php), for each with none
// kept: the tile of it in memory (aio_cpm_mem, $map: aio_cp_tile_map of the caller; no request), else asked
// (aio_cpm_ask, AIO_CPM_FILL_S at most; no line of the log each: the caller logs the sum) with AIO_CPM_MIN_S left of
// $limit s at least; no request in a pause (one it set too: the rest lazily, aio_cpm_step) or with the catalog server
// down. One write of the file. -> array(asked, got).
/**
 * @param list<AioCatTile> $tiles
 * @param float $limit
 * @param array<string, AioCatTile>|null $map
 * @return array{int, int}
 */
function aio_cpm_fill($tiles, $limit, $map = null)
{
    $t0 = microtime(true);
    $asked = 0;
    $got = 0;
    $put = array();
    foreach ($tiles as $t)
    {
        $key = aio_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
        if ($key === '' || array_key_exists($key, $put) || aio_cpm_tile($t['type'], $t['imdb_id'], $t['_tmdbId']))
            continue;
        $m = aio_cpm_mem($t, $map);
        if ($m)
        {
            $put[$key] = $m;
            continue;
        }
        $left = $limit - (microtime(true) - $t0);
        if ($left < AIO_CPM_MIN_S || aio_cpm_paused() || aio_catv_is_down())
            continue;
        $r = aio_cpm_ask($key, $t['type'], min(AIO_CPM_FILL_S, $left), false);
        $asked++;
        if ($r['keep'])
            $put[$key] = $r['tile'];
        $got += $r['tile'] ? 1 : 0;
    }
    if ($put)
        aio_cpm_put_all($put);
    return array($asked, $got);
}
