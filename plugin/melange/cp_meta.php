<?php
// The meta of the tiles of the row "Continue" (continue.php) no row in memory has: the tile of the meta of the catalog
// server (meta/<type>/<id>.json, as the own card asks it: the fields of the info panel of a tile of a row and its
// rating), asked lazily at the step of the pairs (links.php: r25 the tick of the timer of the main screen, r24 the end
// of an opening), the pairs first, AIO_CPM_N requests of the step at most with theirs; kept in a file of data_dir. The
// own card puts the tile of its meta there too (aio_cm_get), of a title of the history.
// Needs main.php (aio_log, aio_data_dir, aio_file_sig, aio_write_file), catalog.php (aio_cat_get, aio_cat_tile),
// card_meta.php (aio_cm_same), continue.php (aio_cp_tile_map), links.php (aio_pg_tile_cards), view_catalog.php
// (aio_catv_is_down).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// In data_dir: "<type>:<IMDb | tmdb:<id>>" => t (when written), tile (null: the request failed then).
define('AIO_CPM_FILE', 'cp_meta.json');
// Titles kept at most and the size of the file (a tile ~2-4 KB, the Russian escaped): the oldest written go first. The
// row has 20 tiles of a type at most.
define('AIO_CPM_MAX', 60);
define('AIO_CPM_MAX_BYTES', 262144);
// A failure is asked again after this many s; no connection, a timeout, 429 or 5xx: no request for AIO_CPM_PAUSE_S
// (memory of php_server).
define('AIO_CPM_FAIL_S', 86400);
define('AIO_CPM_PAUSE_S', 1800);
// Requests of a step at most, those of the pairs in it counted; the second one only with AIO_CPM_NEXT_S left.
define('AIO_CPM_N', 2);
define('AIO_CPM_NEXT_S', 1.5);
// The tiles of the screen "Продолжить" asked after those of the row, at most: with the 20 of the row within AIO_CPM_MAX,
// so the step does not push out of the file what it asked for the row.
define('AIO_CPM_MORE', 40);

class AioCpm
{
    // AIO_CPM_FILE as read ($sig: aio_file_sig of it then); null: not read.
    /** @var array<string, AioCpmEntry>|null */
    public static $cache = null;
    /** @var string */
    public static $sig = '';
    // The tiles of the row built last (aio_cp_row_defs) with no tile of a row in memory and none here, in its order:
    // array(type, IMDb, TMDB); for the step.
    /** @var list<array{string, string, string}> */
    public static $miss = array();
    // The same of the screen "Продолжить" built last (view_my_list.php), its first AIO_CPM_MORE: asked after those of
    // the row; a row built again keeps them.
    /** @var list<array{string, string, string}> */
    public static $more = array();
    // No request before this time; 0: none.
    /** @var int */
    public static $pause = 0;
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

// Entry $key: tile $tile (null: a failure), now; the file written whole (aio_write_file: a temporary file and rename),
// AIO_CPM_MAX entries and AIO_CPM_MAX_BYTES at most, the oldest written go first. The same tile again: nothing written.
// -> written (or the same).
/**
 * @param string $key
 * @param AioCatTile|null $tile
 * @return bool
 */
function aio_cpm_put($key, $tile)
{
    if (aio_data_dir() === '')
        return false;
    $c = aio_cpm_read();
    if ($tile && isset($c[$key]) && $c[$key]['tile'] === $tile)
        return true;
    // The order of the file is that of writing: the oldest first.
    unset($c[$key]);
    $c[$key] = array('t' => time(), 'tile' => $tile);
    $c = array_slice($c, max(0, count($c) - AIO_CPM_MAX), null, true);
    $json = json_encode(array('v' => 1, 'm' => (object) $c));
    while (is_string($json) && strlen($json) > AIO_CPM_MAX_BYTES && count($c) > 1)
    {
        array_shift($c);
        $json = json_encode(array('v' => 1, 'm' => (object) $c));
    }
    $ok = is_string($json) && strlen($json) <= AIO_CPM_MAX_BYTES && aio_write_file(aio_data_dir() . '/' . AIO_CPM_FILE, $json);
    AioCpm::$cache = null;
    return $ok;
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
// it, aio_pg_tile_cards).
/**
 * @param AioCatTile $t
 * @param AioCatTile|null $tile
 * @return void
 */
function aio_cpm_of_card($t, $tile)
{
    $key = aio_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
    if (!$tile || $key === '' || !aio_pg_tile_cards($t))
        return;
    $tile['type'] = $t['type'];
    aio_cpm_put($key, $tile);
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

// The titles of AioCpm::$miss, then of AioCpm::$more, to ask, in their order: none kept, no failure within AIO_CPM_FAIL_S.
// -> keys => array(type, IMDb, TMDB).
/**
 * @return array<string, array{string, string, string}>
 */
function aio_cpm_queue()
{
    $c = aio_cpm_read();
    $now = time();
    $out = array();
    foreach (array_merge(AioCpm::$miss, AioCpm::$more) as $m)
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

// The lazy meta: the titles of aio_cpm_queue no row in memory has now (aio_cp_tile_map), up to $n requests in $limit s
// (the second one only with AIO_CPM_NEXT_S left), each kept (a failure too). No connection, a timeout, 429 or 5xx: a
// pause, no more of the step. One line of the log of a step that asked; of a $tick always. -> how many were asked.
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
    $q = $n > 0 && !aio_cpm_paused() && !aio_catv_is_down() ? aio_cpm_queue() : array();
    $map = $q ? aio_cp_tile_map() : array();
    foreach ($q as $key => $m)
    {
        list($type, $imdb, $tmdb) = $m;
        if (($imdb !== '' && isset($map["$type|imdb:$imdb"])) || ($tmdb !== '' && isset($map["$type|tmdb:$tmdb"])))
            continue;
        $left = $limit - (microtime(true) - $t);
        if ($asked >= $n || $left < ($asked ? AIO_CPM_NEXT_S : 1))
            break;
        $id = substr($key, strlen($type) + 1);
        $g = aio_cat_get("meta/$type/$id.json", "meta $key", 'meta', intval(floor($left)), true);
        $asked++;
        $tile = $g['err'] === '' && aio_cm_same($g['list'], $id) ? aio_cat_tile($g['list'], $type) : null;
        if ($tile)
            $tile['type'] = $type;
        aio_cpm_put($key, $tile);
        $got += $tile ? 1 : 0;
        if (in_array($g['err'], array('net', 'timeout', 'rate', 'server'), true))
        {
            AioCpm::$pause = time() + AIO_CPM_PAUSE_S;
            break;
        }
    }
    if ($asked || $tick)
        aio_log(sprintf('continue: meta: %s: %d asked, %d got, %.0f ms%s', $tick ? 'tick' : 'step', $asked, $got,
            (microtime(true) - $t) * 1000, AioCpm::$pause > 0 && aio_cpm_paused() ? ', pause' : ''));
    return $asked;
}
