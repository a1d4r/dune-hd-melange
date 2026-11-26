<?php
// The meta of the tiles of the row "Continue" (continue.php) and of "Мой список" (view_my_list.php): the tile of the
// meta of the catalog server (meta/<type>/<id>.json, as the own card asks it: the fields of the info panel of a tile of
// a row and its rating), kept in a file of data_dir. At the build of the rows (mel_cpm_build) a title with none kept
// (or one kept over MEL_CPM_STALE_S ago) gets the tile of a row in memory, else of a copy of a row on the disk, else
// (none kept) one batch of requests in MEL_CPM_BUILD_S; the rest asked lazily at the step of the pairs (links.php: r25
// the tick of the timer of the main screen, r24 the end of an opening; the screens "Продолжить" and "Мой список" the
// same steps for all their tiles, view_my_list.php), the pairs first, then one batch at once
// (curl_multi), else MEL_CPM_N requests of the step at most with theirs. The own card puts the tile of its meta there
// too (mel_cm_get), of a title of the history or of the list; a title into the list the tile of it with no request
// (mel_cpm_of_add: of the own card in memory, else the best one); the import of the Favorites of the Dune asks the meta
// of every title it adds at once (mel_cpm_fill).
// Needs main.php (mel_log, mel_data_dir, mel_file_sig, mel_write_file, mel_http_mark, MEL_HTTP_PAR), catalog.php
// (mel_cat_get, mel_cat_get_many, mel_cat_tile, mel_cat_cache_tiles),
// card_meta.php (mel_cm_same, mel_cm_mem_tile), continue.php (mel_cp_tile_map, mel_cp_map_tile, mel_cp_best_tile,
// mel_cp_items, mel_cp_ids), progress.php (mel_pg_index), links.php (mel_pg_tile_cards), my_list.php (mel_ml_list, mel_ml_in), view_catalog.php (mel_catv_is_down,
// MEL_CATV_ALL_COLS, MEL_CATV_ALL_ROWS).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// In data_dir: "<type>:<IMDb | tmdb:<id>>" => t (when written), tile (null: the request failed then).
define('MEL_CPM_FILE', 'cp_meta.json');
// Titles kept at most and the size of the file (a tile ~3-4.5 KB, the Russian escaped): "Мой список" holds ~570 at most
// (MEL_ML_COPY_MAX), the rows "Continue" 20 of a type. The titles neither in the list nor of the history (mel_cpm_kept;
// the own card of another card of the history, a title out of the list) MEL_CPM_OTHER_MAX at most, as the file was
// before the list had its meta here. Over them the oldest written go first of those others, then the oldest of
// the rest. The file is read once by php_server and written whole at a change only: 700 titles, 2 MB measured on a Mac
// (docker) - json_decode 17 ms, mel_cpm_read 70 ms (mel_cat_tile of each entry mostly), a write 16-18 ms; the Dune ~6
// times that: a read ~0.4-0.6 s once, a write ~0.1 s and the flash. A user with no list: MEL_CPM_OTHER_MAX and the
// titles of the history, as before.
define('MEL_CPM_MAX', 700);
define('MEL_CPM_MAX_BYTES', 3145728);
define('MEL_CPM_OTHER_MAX', 60);
// The tiles of the screen "Продолжить" or "Мой список" (up to MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS) whose meta it
// keeps (mel_cpm_local) and asks (its ticks, the opening of r24): its first this many, kept over the other titles of
// the file (MelCpm::$hold). MEL_CPM_MAX less the titles of the rows of a build (mel_cpm_build_titles: 20 of "Continue"
// and 20 of the list of each type, 4 * MEL_CATV_ROW_TILES; a test holds it): neither pushes the other out, so a tick
// never asks again what a write pushed out (with no limit a screen of over MEL_CPM_MAX titles pushed its own first
// tiles out at each tick, for good). 620 tiles of ~4.5 KB are ~2.8 MB, within MEL_CPM_MAX_BYTES.
define('MEL_CPM_SCREEN_MAX', 620);
// A failure of a title (big, 404, not its meta) is asked again after this many s. No connection, 429 or 5xx: the
// server, not the title - no request for MEL_CPM_PAUSE_S (memory of php_server), the title not kept. A timeout is of
// the title (a meta the server has not kept, a big series, in the ~1.5 s of a tick): the first one of it in a
// php_server not kept, asked again at the next step (the server has it by then, mostly), the second kept as its
// failure, no pause either (the own card asks MEL_CM_SERIES_BYTES in MEL_CM_SERIES_TOTAL s and keeps its tile here);
// of the server only right after a timeout of another title (within MEL_CPM_TIMED_OUT_S) with no reply of the server
// between (mel_cpm_judge): a pause.
define('MEL_CPM_FAIL_S', 86400);
define('MEL_CPM_PAUSE_S', 1800);
define('MEL_CPM_TIMED_OUT_S', 180);
// Requests of a step one by one at most (no curl_multi), those of the pairs in it counted; one starts with
// MEL_CPM_MIN_S left at least, its timeout what is left, to the ms (mel_http_get: CURLOPT_TIMEOUT_MS; a pair of ~0.6 s
// of a tick leaves ~0.9 s). With curl_multi: one batch of MEL_CPM_STEP_PAR titles at most a step, each its timeout the
// whole of what is left; of the import MEL_HTTP_PAR at once.
define('MEL_CPM_N', 2);
define('MEL_CPM_STEP_PAR', 4);
define('MEL_CPM_MIN_S', 0.5);
// A timeout of a request given less than this many s (the rest of a tick) is of that short rest, not of the server or
// the title (a meta the server has not kept takes 0.2-1.9 s): no pause, the title not marked or kept, asked at the
// next step; so is a timeout of no connection in it (curl 28: its connect is the rest too, mel_cat_req).
define('MEL_CPM_SHORT_S', 1.0);
// r25: the tick (mel_pg_tick, links.php) - the pair and the meta - within this many s from its start (a key pressed
// waits for it); the pair in it too, its timeout the whole s of it.
define('MEL_CPM_TICK_S', 1.5);
// The tiles of the screen "Продолжить" or "Мой список" the ticks of a main screen ask after those of the rows, at most
// (the screen built again gives the next ones; its own ticks ask all of its tiles, view_my_list.php).
define('MEL_CPM_MORE', 40);
// The meta of the tiles of the rows "Continue" and "Мой список" no row has, asked at the build of a main screen of rows
// (mel_cpm_build), s at most from its start, the look at the rows in memory and on the disk in it: r25 its reply (the
// view with the first rows) waits for it, r24 it is in MEL_CATV_OPEN_S. MEL_CPM_SHORT_S at most (a test holds it): a
// timeout of the build is of its short rest (mel_cpm_judge), never a slow title or the server.
define('MEL_CPM_BUILD_S', 1.0);
// A title cut short at the build (a timeout of its short rest): not asked by the builds for this many s (MelCpm::$cut),
// the tick asks it (a meta that takes longer than the build has would else hold every opening for good).
define('MEL_CPM_BUILD_CUT_S', 180);
// A batch of a build of 2 requests or more none of which connected (a timeout of no connection each, of its short rest;
// the catalog server not reached): no build asks for this many s either (MelCpm::$off, no pause - a cold Wi-Fi after a
// sleep is that too; the tick asks with its own rules).
define('MEL_CPM_BUILD_OFF_S', 180);
// A tile kept this many s ago or more: the build takes a newer one of a row in memory, or of a copy on the disk saved
// after it (a rating of a new title comes days after it, kpRating changes); none - kept as it is, no request.
define('MEL_CPM_STALE_S', 86400);
// Such a tile with no newer one in memory or on the disk: not looked for again for this many s (MelCpm::$looked; each
// look is a glob and a stat of the copies).
define('MEL_CPM_LOOK_S', 3600);
// A request of the meta of a title of the import of the Favorites of the Dune (mel_cpm_fill), s at most: a meta the
// server has not kept takes 0.2-1.9 s.
define('MEL_CPM_FILL_S', 5.0);
// Requests of the meta of an import at most in MEL_CPM_RATE_S s (the catalog server lets 180 a minute in, the rows of
// the catalog after the import need their share). Tests may define them first.
if (!defined('MEL_CPM_RATE_N'))
    define('MEL_CPM_RATE_N', 120);
if (!defined('MEL_CPM_RATE_S'))
    define('MEL_CPM_RATE_S', 60.0);

class MelCpm
{
    // MEL_CPM_FILE as read ($sig: mel_file_sig of it then); null: not read.
    /** @var array<string, MelCpmEntry>|null */
    public static $cache = null;
    /** @var string */
    public static $sig = '';
    // The tiles of the row of the main screen built last (mel_catv_main_view, mel_cp_row_defs) with no tile of a row
    // in memory and none here, in its order: array(type, IMDb, TMDB); for the step; again after a tick of it paired
    // cards (mel_pg_tick). Not of the tab "TV".
    /** @var list<array{string, string, string}> */
    public static $miss = array();
    // The same of the row "Мой список" (our list, not the Favorites of the Dune) of that screen (mel_ml_row_defs): asked
    // after those of "Continue", before them when the row is above it (MelCat::$own).
    /** @var list<array{string, string, string}> */
    public static $ml = array();
    // The same of the row "На TorrServer" of that screen (mel_tsr_row_defs: its tiles known by the history).
    /** @var list<array{string, string, string}> */
    public static $ts = array();
    // The same of the screen "Продолжить" or "Мой список" built last (view_my_list.php), its first MEL_CPM_MORE: asked
    // after those of the rows; a row built again keeps them.
    /** @var list<array{string, string, string}> */
    public static $more = array();
    // No request before this time; 0: none.
    /** @var int */
    public static $pause = 0;
    // The titles whose request timed out once since the start of php_server (the next one kept as a failure).
    /** @var array<string, bool> */
    public static $slow = array();
    // The title whose request timed out last, no reply of the server since (mel_cpm_judge, mel_cpm_of_card); '':
    // none; when (time()).
    /** @var string */
    public static $timed_out = '';
    /** @var int */
    public static $timed_t = 0;
    // The titles cut short at a build (mel_cpm_build): key => when (time()); the builds do not ask them for
    // MEL_CPM_BUILD_CUT_S. Not in the file.
    /** @var array<string, int> */
    public static $cut = array();
    // When a batch of a build reached no server (MEL_CPM_BUILD_OFF_S); 0: none.
    /** @var int */
    public static $off = 0;
    // The stale tiles kept (MEL_CPM_STALE_S) with no newer one found: key => when looked (mel_cpm_local). Not in the
    // file.
    /** @var array<string, int> */
    public static $looked = array();
    // The keys of the titles of the screen "Продолжить" or "Мой список" viewed last, its first MEL_CPM_SCREEN_MAX
    // (mel_mlv_hold): pushed out of the file last of all (mel_cpm_json).
    /** @var array<string, bool> */
    public static $hold = array();
}

// The key of a title of type $type with IMDb $imdb, else TMDB $tmdb ('' none), as mel_cm_id; '' for none.
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @return string
 */
function mel_cpm_key($type, $imdb, $tmdb)
{
    if ($type !== 'movie' && $type !== 'series')
        return '';
    return $imdb !== '' ? "$type:$imdb" : ($tmdb !== '' ? "$type:tmdb:$tmdb" : '');
}

// The file: from memory, read again when it is another one. A broken file or entry is none; a tile parsed again
// (mel_cat_tile), of the type of its key.
/**
 * @return array<string, MelCpmEntry>
 */
function mel_cpm_read()
{
    $sig = mel_file_sig(MEL_CPM_FILE);
    if (MelCpm::$cache !== null && $sig === MelCpm::$sig)
        return MelCpm::$cache;
    $j = $sig !== '' ? json_decode(strval(file_get_contents(mel_data_dir() . '/' . MEL_CPM_FILE)), true) : null;
    $out = array();
    foreach (is_array($j) && isset($j['m']) && is_array($j['m']) ? $j['m'] : array() as $key => $e)
    {
        $key = strval($key);
        if (!preg_match('/^(movie|series):(tt[0-9]{1,10}|tmdb:[0-9]{1,10})$/D', $key, $k) || !is_array($e) ||
            !isset($e['t']) || !is_int($e['t']) || !array_key_exists('tile', $e))
            continue;
        $tile = is_array($e['tile']) ? mel_cat_tile($e['tile'], $k[1]) : null;
        if ($e['tile'] !== null && !$tile)
            continue;
        if ($tile)
            $tile['type'] = $k[1];
        $out[$key] = array('t' => $e['t'], 'tile' => $tile);
    }
    MelCpm::$cache = $out;
    MelCpm::$sig = $sig;
    return $out;
}

// Entry $key: tile $tile (null: a failure), now (mel_cpm_put_all). -> written (or the same).
/**
 * @param string $key
 * @param MelCatTile|null $tile
 * @return bool
 */
function mel_cpm_put($key, $tile)
{
    return mel_cpm_put_all(array($key => $tile));
}

// Entries $put (key => tile, null: a failure), now, at one write of the file whole (mel_write_file: a temporary file
// and rename), within the limits of mel_cpm_json; the memory is the file written then.
// The same tile again: nothing written, but of the keys of $renew (the build, a tile kept long ago: its time now). ->
// written (or nothing new).
/**
 * @param array<string, MelCatTile|null> $put
 * @param array<string, bool> $renew
 * @return bool
 */
function mel_cpm_put_all($put, $renew = array())
{
    if (mel_data_dir() === '')
        return false;
    $c = mel_cpm_read();
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
    $json = mel_cpm_json($c);
    $ok = $json !== '' && mel_write_file(mel_data_dir() . '/' . MEL_CPM_FILE, $json);
    MelCpm::$cache = $ok ? $c : null;
    MelCpm::$sig = $ok ? mel_file_sig(MEL_CPM_FILE) : '';
    return $ok;
}

// The text of the file of entries $c: of the titles neither in "Мой список" nor of the history (mel_cpm_kept) nor of
// the screen viewed last (MelCpm::$hold) the MEL_CPM_OTHER_MAX written last at most; MEL_CPM_MAX entries and
// MEL_CPM_MAX_BYTES at most, over them out first the oldest written of those others, then the oldest of the rest but
// those of MelCpm::$hold, then the oldest of these; $c without them. '' if json_encode failed.
/**
 * @param array<string, MelCpmEntry> $c
 * @param-out array<string, MelCpmEntry> $c
 * @return string
 */
function mel_cpm_json(&$c)
{
    $json = json_encode(array('v' => 1, 'm' => (object) $c));
    if (!is_string($json))
        return '';
    // MEL_CPM_OTHER_MAX entries or fewer are within every limit, whoever they are: no mel_cpm_kept.
    if (count($c) <= MEL_CPM_OTHER_MAX && strlen($json) <= MEL_CPM_MAX_BYTES)
        return $json;
    $kept = mel_cpm_kept();
    $order = array();
    $others = 0;
    // 0: others, 1: kept, 2: held.
    foreach (array(0, 1, 2) as $k)
    {
        foreach ($c as $key => $e)
        {
            if ((isset(MelCpm::$hold[$key]) ? 2 : (isset($kept[$key]) ? 1 : 0)) === $k)
                $order[] = strval($key);
        }
        if (!$k)
            $others = count($order);
    }
    $i = 0;
    for (; $others > MEL_CPM_OTHER_MAX; $others--)
        unset($c[$order[$i++]]);
    if ($i)
    {
        $json = json_encode(array('v' => 1, 'm' => (object) $c));
        if (!is_string($json))
            return '';
    }
    if (count($c) <= MEL_CPM_MAX && strlen($json) <= MEL_CPM_MAX_BYTES)
        return $json;
    // The size of the text without an entry: its "key":{...} and a comma (the last one has none: the text encoded again
    // till it fits).
    $size = strlen($json);
    while ($i < count($order) && count($c) > 1)
    {
        if (count($c) <= MEL_CPM_MAX && $size <= MEL_CPM_MAX_BYTES)
        {
            $json = json_encode(array('v' => 1, 'm' => (object) $c));
            if (!is_string($json))
                return '';
            $size = strlen($json);
            if ($size <= MEL_CPM_MAX_BYTES)
                return $json;
        }
        $key = $order[$i++];
        $size -= strlen(strval(json_encode($key))) + strlen(strval(json_encode($c[$key]))) + 2;
        unset($c[$key]);
    }
    $json = json_encode(array('v' => 1, 'm' => (object) $c));
    return is_string($json) && strlen($json) <= MEL_CPM_MAX_BYTES ? $json : '';
}

// The keys of the titles not to push out of the file first: of "Мой список" and of the cards of the history (the row
// "Continue" and its screen, mel_cp_items), by IMDb and by TMDB.
/**
 * @return array<string, bool>
 */
function mel_cpm_kept()
{
    $ids = array();
    foreach (mel_ml_list() as $e)
        $ids[] = array($e['type'], $e['imdb_id'], $e['_tmdbId']);
    foreach (mel_cp_items(array('movie', 'series'), MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS) as $mid => $it)
        $ids[] = mel_cp_ids(strval($mid), $it['type'] === 'series');
    return mel_cpm_keys($ids);
}

// The keys of titles $ids (array(type, IMDb, TMDB) each), by IMDb and by TMDB: an entry is kept by either.
/**
 * @param list<array{string, string, string}> $ids
 * @return array<string, bool>
 */
function mel_cpm_keys($ids)
{
    $out = array();
    foreach ($ids as $m)
    {
        foreach (array(mel_cpm_key($m[0], $m[1], ''), mel_cpm_key($m[0], '', $m[2])) as $key)
        {
            if ($key !== '')
                $out[$key] = true;
        }
    }
    return $out;
}

// The tile kept of a title of type $type with IMDb $imdb, else TMDB $tmdb, of the entries $c (null: mel_cpm_read); null
// for none (or a failure).
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param array<string, MelCpmEntry>|null $c
 * @return MelCatTile|null
 */
function mel_cpm_tile($type, $imdb, $tmdb, $c = null)
{
    if ($c === null)
        $c = mel_cpm_read();
    $key = mel_cpm_tile_key($type, $imdb, $tmdb, $c);
    return $key !== '' ? $c[$key]['tile'] : null;
}

// The key of the entry of entries $c with the tile kept of a title of type $type with IMDb $imdb, else TMDB $tmdb, as
// mel_cpm_tile finds it; '' for none.
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param array<string, MelCpmEntry> $c
 * @return string
 */
function mel_cpm_tile_key($type, $imdb, $tmdb, $c)
{
    foreach (array(mel_cpm_key($type, $imdb, ''), mel_cpm_key($type, '', $tmdb)) as $key)
    {
        if ($key !== '' && isset($c[$key]) && $c[$key]['tile'])
            return $key;
    }
    return '';
}

// The own card got the meta of tile $t (mel_cm_get, a reply of the server), its tile $tile: kept when the title is of
// the history (a pair of it, mel_pg_tile_cards) or of "Мой список".
/**
 * @param MelCatTile $t
 * @param MelCatTile|null $tile
 * @return void
 */
function mel_cpm_of_card($t, $tile)
{
    // A reply of the catalog server (the own card asks it): no timeout of the lazy meta right before the next one.
    MelCpm::$timed_out = '';
    $key = mel_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
    if (!$tile || $key === '' || (!mel_pg_tile_cards($t) && mel_ml_in($t) === ''))
        return;
    $tile['type'] = $t['type'];
    mel_cpm_put($key, $tile);
}

// The tile of title $t with no request: of its meta of the own card in memory (mel_cm_mem_tile), else the best one
// (mel_cp_best_tile: of the meta kept, else of a row in memory, $map: mel_cp_tile_map of the caller); null for none.
/**
 * @param MelCatTile $t
 * @param array<string, MelCatTile>|null $map
 * @return MelCatTile|null
 */
function mel_cpm_mem($t, $map = null)
{
    $m = mel_cm_mem_tile($t);
    return $m ? $m : mel_cp_best_tile($t['type'], $t['imdb_id'], $t['_tmdbId'], $map);
}

// Title $t into "Мой список" (mel_ml_set): the tile of it in memory (mel_cpm_mem: the own card, a row) kept, no request.
/**
 * @param MelCatTile $t
 * @return void
 */
function mel_cpm_of_add($t)
{
    $key = mel_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
    $m = $key !== '' ? mel_cpm_mem($t) : null;
    if ($m)
        mel_cpm_put($key, $m);
}

// s of the pause of the requests left; 0: none. A pause ending past MEL_CPM_PAUSE_S from now (the clock set back) is
// none.
/**
 * @return int
 * @phpstan-impure
 */
function mel_cpm_paused()
{
    $left = MelCpm::$pause - time();
    return $left > 0 && $left <= MEL_CPM_PAUSE_S ? $left : 0;
}

// The tiles with no meta of the rows of Melange of the main screen (MelCpm::$miss, MelCpm::$ml, MelCpm::$ts), in the
// order of their rows (MelCat::$own). -> array(type, IMDb, TMDB).
/**
 * @return list<array{string, string, string}>
 */
function mel_cpm_rows()
{
    // The list of each row of Melange with one, by its name (mel_cat_pseudo).
    $of = array('@continue' => MelCpm::$miss, '@mylist' => MelCpm::$ml, '@torrserver' => MelCpm::$ts);
    $rows = array();
    foreach (MelCat::$own as $name)
        $rows = isset($of[$name]) ? array_merge($rows, $of[$name]) : $rows;
    return $rows;
}

// The titles of the rows (mel_cpm_rows), then of MelCpm::$more (of the ticks of a main screen), else of $titles (of
// the screen "Продолжить" or "Мой список", its tiles with no meta; of the rows alone, a tick), to ask (mel_cpm_due), in
// their order. -> keys => array(type, IMDb, TMDB).
/**
 * @param list<array{string, string, string}>|null $titles
 * @return array<string, array{string, string, string}>
 */
function mel_cpm_queue($titles = null)
{
    $c = mel_cpm_read();
    $now = time();
    $out = array();
    foreach ($titles !== null ? $titles : array_merge(mel_cpm_rows(), MelCpm::$more) as $m)
    {
        $key = mel_cpm_key($m[0], $m[1], $m[2]);
        if ($key === '' || isset($out[$key]) || !mel_cpm_due(isset($c[$key]) ? $c[$key] : null, $now))
            continue;
        $out[$key] = $m;
    }
    return $out;
}

// A title of entry $e (null: none) is to be asked at time $now: none kept, no failure within MEL_CPM_FAIL_S (one of a
// time ahead of the clock is none).
/**
 * @param MelCpmEntry|null $e
 * @param int $now
 * @return bool
 */
function mel_cpm_due($e, $now)
{
    return !$e || (!$e['tile'] && ($now - $e['t'] < 0 || $now - $e['t'] >= MEL_CPM_FAIL_S));
}

// The titles of mel_cpm_queue (of $titles: as it takes them) a step asks for: those no row in memory has now
// (mel_cp_tile_map), pause or not. -> keys => type.
/**
 * @param list<array{string, string, string}>|null $titles
 * @return array<string, string>
 */
function mel_cpm_todo($titles = null)
{
    $q = mel_cpm_queue($titles);
    $map = $q ? mel_cp_tile_map() : array();
    $todo = array();
    foreach ($q as $key => $m)
    {
        if (mel_cp_map_tile($map, $m[0], $m[1], $m[2]))
            continue;
        $todo[strval($key)] = $m[0];
    }
    return $todo;
}

// One request of the meta of title $key of type $type (mel_cat_get, its timeout $limit s, to the ms; its line of the log
// if $log), judged by mel_cpm_judge -> tile: of it (null: none); keep: kept as it is (the tile, or a failure of the
// title: big, 404, not its meta); stop: no more requests now (a pause, a timeout: it took the rest); cut: a timeout of
// a short rest (under MEL_CPM_SHORT_S); off: no connection (a timeout of curl before it).
/**
 * @param string $key
 * @param string $type
 * @param float $limit
 * @param bool $log
 * @return array{tile: MelCatTile|null, keep: bool, stop: bool, cut: bool, off: bool}
 */
function mel_cpm_ask($key, $type, $limit, $log = true)
{
    $id = substr($key, strlen($type) + 1);
    $g = mel_cat_get("meta/$type/$id.json", $log ? "meta $key" : '', 'meta', $limit, true);
    $r = mel_cpm_judge(array($key => $g), array($key => $type), $limit);
    return $r[$key];
}

// The requests of the meta of titles $types (key => type) at once (mel_cat_get_many, each its timeout $limit s; its
// line of the log if $log), judged as one batch (mel_cpm_judge) -> key => as mel_cpm_ask, in the order of $types;
// null: no curl_multi (the caller asks one by one).
/**
 * @param array<string, string> $types
 * @param float $limit
 * @param bool $log
 * @return array<string, array{tile: MelCatTile|null, keep: bool, stop: bool, cut: bool, off: bool}>|null
 */
function mel_cpm_ask_many($types, $limit, $log = true)
{
    $paths = array();
    foreach ($types as $key => $type)
        $paths[$key] = array("meta/$type/" . substr($key, strlen($type) + 1) . '.json', $log ? "meta $key" : '');
    $gets = mel_cat_get_many($paths, 'meta', $limit, true);
    return $gets === null ? null : mel_cpm_judge($gets, $types, $limit);
}

// The replies $gets (key => mel_cat_get of the meta of the title, type $types[key]) of requests of $limit s each, asked
// together (one, or a batch at once) -> key => tile, keep, stop, cut, off (as mel_cpm_ask). Of each title: no connection,
// 429 or 5xx - a pause (MEL_CPM_PAUSE_S), the title not kept; a timeout of under MEL_CPM_SHORT_S given (connected or
// not: curl 28) - cut, nothing kept, no pause, not counted; a timeout, the first of the title in this php_server - not
// kept, asked again; the second - kept
// failed, a pause or not, and of no pause (the title is slow: two such at the head of the queue would else hold it in
// pauses for good). A reply of the server (all but net and a timeout; any order in the batch) - no timeout before it
// counts (MelCpm::$timed_out ''), the server is reached (MelCpm::$off 0: a build asks again). With none: the first timeouts of two titles or more (of the batch, and the one timed
// out last if within MEL_CPM_TIMED_OUT_S and not timed out again here) - the server, a pause, none of them kept.
/**
 * @param array<string, array{err: string, list: array<mixed>, curl28: bool}> $gets
 * @param array<string, string> $types
 * @param float $limit
 * @return array<string, array{tile: MelCatTile|null, keep: bool, stop: bool, cut: bool, off: bool}>
 */
function mel_cpm_judge($gets, $types, $limit)
{
    $short = $limit < MEL_CPM_SHORT_S;
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
            if (isset(MelCpm::$slow[$key]))
                $again[$key] = true;
            else
                $first[$key] = true;
        }
    }
    $ago = time() - MelCpm::$timed_t;
    $who = $first;
    if (MelCpm::$timed_out !== '' && $ago >= 0 && $ago < MEL_CPM_TIMED_OUT_S && !isset($again[MelCpm::$timed_out]))
        $who[MelCpm::$timed_out] = true;
    $server = !$answered && count($who) >= 2;
    $out = array();
    foreach ($gets as $key => $g)
    {
        $key = strval($key);
        $type = $types[$key];
        $tile = $g['err'] === '' && mel_cm_same($g['list'], substr($key, strlen($type) + 1)) ? mel_cat_tile($g['list'], $type) :
            null;
        if ($tile)
            $tile['type'] = $type;
        $one = isset($first[$key]);
        // A timeout of no connection is "net" (mel_cat_reply): in a short rest its connect was that rest too.
        $cut = $short && ($g['err'] === 'timeout' || ($g['err'] === 'net' && $g['curl28']));
        $pause = ($one && $server) || (!$cut && in_array($g['err'], array('net', 'rate', 'server'), true));
        if ($one)
            MelCpm::$slow[$key] = true;
        if ($pause)
            MelCpm::$pause = time() + MEL_CPM_PAUSE_S;
        $out[$key] = array('tile' => $tile, 'keep' => isset($again[$key]) || (!$pause && !$cut && !$one),
            'stop' => $pause || $cut || $one || isset($again[$key]), 'cut' => $cut,
            'off' => $g['err'] === 'net' && $g['curl28']);
    }
    if ($answered)
    {
        MelCpm::$timed_out = '';
        MelCpm::$off = 0;
    }
    else if ($first)
    {
        end($first);
        MelCpm::$timed_out = strval(key($first));
        MelCpm::$timed_t = time();
    }
    return $out;
}

// The requests of the meta of titles $todo (key => type, in their order) in $limit s from $t0, with MEL_CPM_MIN_S left
// at least, none in a pause or with the catalog server down: with curl_multi one batch of the first $par (mel_cpm_ask_many, each its timeout the whole of what is left);
// else up to $n one by one (each with MEL_CPM_MIN_S left, its timeout the s left: mel_cpm_ask), a pause or a short rest
// - no more. -> array(key => tile of those to keep (null: a failure), asked, got, at once (0: one by one), keys cut short
// (a timeout of a short rest), of no connection).
/**
 * @param array<string, string> $todo
 * @param float $t0
 * @param float $limit
 * @param int $par
 * @param int $n
 * @return array{array<string, MelCatTile|null>, int, int, int, list<string>, int}
 */
function mel_cpm_ask_some($todo, $t0, $limit, $par, $n)
{
    if (mel_cpm_paused() || mel_catv_is_down())
        $todo = array();
    $left = $limit - (microtime(true) - $t0);
    $rs = $todo && $left >= MEL_CPM_MIN_S ? mel_cpm_ask_many(array_slice($todo, 0, $par, true), $left) : null;
    $at_once = $rs !== null ? count($rs) : 0;
    if ($rs === null)
    {
        $rs = array();
        foreach ($todo as $key => $type)
        {
            $key = strval($key);
            $left = $limit - (microtime(true) - $t0);
            if (count($rs) >= $n || $left < MEL_CPM_MIN_S)
                break;
            $rs[$key] = mel_cpm_ask($key, $type, $left);
            if ($rs[$key]['stop'])
                break;
        }
    }
    $put = array();
    $got = 0;
    $cut = array();
    $off = 0;
    foreach ($rs as $key => $r)
    {
        $key = strval($key);
        if ($r['keep'])
            $put[$key] = $r['tile'];
        $got += $r['tile'] ? 1 : 0;
        if ($r['cut'])
            $cut[] = $key;
        $off += $r['off'] ? 1 : 0;
    }
    return array($put, count($rs), $got, $at_once, $cut, $off);
}

// The lazy meta: the titles of mel_cpm_todo (of $titles: as it takes them), in $limit s (mel_cpm_ask_some: a batch of
// MEL_CPM_STEP_PAR at most, else $n one by one; of the batch only $n > 0 counts). The ones kept at one write. One line
// of the log of a step that asked (its path: mel_http_mark); of a $tick always. -> how many tiles it got.
/**
 * @param float $limit
 * @param int $n
 * @param bool $tick
 * @param list<array{string, string, string}>|null $titles
 * @return int
 */
function mel_cpm_step($limit, $n, $tick = false, $titles = null)
{
    $t = microtime(true);
    $todo = $n > 0 && !mel_cpm_paused() && !mel_catv_is_down() ? mel_cpm_todo($titles) : array();
    list($put, $asked, $got, $par) = mel_cpm_ask_some($todo, $t, $limit, MEL_CPM_STEP_PAR, $n);
    if ($put)
        mel_cpm_put_all($put);
    $mark = $asked ? mel_http_mark($par, $par ? 0 : $asked) : '';
    if ($asked || $tick)
        mel_log(sprintf('continue: meta: %s: %d asked, %d got, %.0f ms%s%s', $tick ? 'tick' : 'step', $asked, $got,
            (microtime(true) - $t) * 1000, $mark !== '' ? ", $mark" : '', MelCpm::$pause > 0 && mel_cpm_paused() ? ', pause' : ''));
    return $got;
}

// The meta of tiles $tiles kept now (the import of the Favorites of the Dune, view_my_list.php), for each with none
// kept: the tile of it in memory (mel_cpm_mem, $map: mel_cp_tile_map of the caller; no request), else asked
// (MEL_CPM_FILL_S at most; no line of the log each: the caller logs the sum) with MEL_CPM_MIN_S left of $limit s at
// least: MEL_HTTP_PAR at once (mel_cpm_ask_many), else one by one (no curl_multi: mel_cpm_ask), MEL_CPM_RATE_N in
// MEL_CPM_RATE_S s at most (a wait for that within $limit, else the rest lazily); no request in a pause
// (one it set too: the rest lazily, mel_cpm_step) or with the catalog server down. One write of the file. ->
// array(asked, got, at most at once (0: none at once), one by one), the last two for mel_http_mark.
/**
 * @param list<MelCatTile> $tiles
 * @param float $limit
 * @param array<string, MelCatTile>|null $map
 * @return array{int, int, int, int}
 */
function mel_cpm_fill($tiles, $limit, $map = null)
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
        $key = mel_cpm_key($t['type'], $t['imdb_id'], $t['_tmdbId']);
        if ($key === '' || array_key_exists($key, $put) || isset($todo[$key]) ||
            mel_cpm_tile($t['type'], $t['imdb_id'], $t['_tmdbId']))
            continue;
        $m = mel_cpm_mem($t, $map);
        if ($m)
            $put[$key] = $m;
        else
            $todo[$key] = $t['type'];
    }
    $multi = true;
    // When each request went (microtime), of the last MEL_CPM_RATE_S s.
    $sent = array();
    while ($todo)
    {
        $left = $limit - (microtime(true) - $t0);
        if ($left < MEL_CPM_MIN_S || mel_cpm_paused() || mel_catv_is_down())
            break;
        $batch = $multi ? array_slice($todo, 0, MEL_HTTP_PAR, true) : array_slice($todo, 0, 1, true);
        $now = microtime(true);
        while ($sent && $now - $sent[0] >= MEL_CPM_RATE_S)
            array_shift($sent);
        $over = count($sent) + count($batch) - MEL_CPM_RATE_N;
        if ($over > 0)
        {
            $wait = $sent[$over - 1] + MEL_CPM_RATE_S - $now;
            if ($wait > $left - MEL_CPM_MIN_S)
                break;
            usleep((int) ceil($wait * 1000000));
            continue;
        }
        $sent = array_merge($sent, array_fill(0, count($batch), $now));
        $rs = $multi ? mel_cpm_ask_many($batch, min(MEL_CPM_FILL_S, $left), false) : null;
        if ($rs === null)
        {
            $multi = false;
            $key = strval(key($batch));
            $rs = array($key => mel_cpm_ask($key, $batch[$key], min(MEL_CPM_FILL_S, $left), false));
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
        mel_cpm_put_all($put);
    return array($asked, $got, $par, $seq);
}

// --- The meta at the build of the rows "Continue" and "Мой список" (mel_catv_main_view, the tab "TV").

// The titles of the rows "Continue" and "Мой список" of each type, MEL_CATV_ROW_TILES of each (those of a row of both
// types, the tab "TV", are among them): of tab $tab first ('': the movies first), those of "Continue" before those of
// the list; only of the rows shown, $names (of mel_cat_pseudo: '@continue', '@mylist'). -> array(type, IMDb, TMDB) each,
// in that order.
/**
 * @param string $tab
 * @param list<string> $names
 * @return list<array{string, string, string}>
 */
function mel_cpm_build_titles($tab, $names)
{
    $idx = mel_pg_index();
    $list = mel_ml_list();
    $out = array();
    foreach ($tab === 'series' ? array('series', 'movie') : array('movie', 'series') as $type)
    {
        // As mel_cp_tile asks its tile.
        foreach (in_array('@continue', $names, true) ? mel_cp_items(array($type)) : array() as $mid => $it)
            $out[] = mel_cp_ids(strval($mid), $idx[strval($mid)]['series'] ? true : false);
        $n = 0;
        foreach (in_array('@mylist', $names, true) ? $list : array() as $e)
        {
            if ($e['type'] !== $type)
                continue;
            if ($n++ >= MEL_CATV_ROW_TILES)
                break;
            $out[] = array($e['type'], $e['imdb_id'], $e['_tmdbId']);
        }
    }
    return $out;
}

// The titles $titles (array(type, IMDb, TMDB) each) with no request, by entries $c (mel_cpm_read) at time $now: of each
// with no tile kept, the tile of a row in memory (mel_cp_tile_map), else of a copy of a row on the disk
// (mel_cat_cache_tiles); a tile kept MEL_CPM_STALE_S ago or more (or ahead of the clock): the tile of a row in memory
// got from the server after it (mel_cpm_mem_times), else of a copy saved after it, the same tile too (its time now: no
// look again for MEL_CPM_STALE_S); neither - as it is, not looked for again for MEL_CPM_LOOK_S (MelCpm::$looked).
// Nothing written: the caller keeps put (mel_cpm_put_all with renew) at its one write. -> array(put: key => tile,
// renew: key => true, due: key => type of the titles to ask (mel_cpm_due, as mel_cpm_queue), kept (fresh, or stale and
// not found), of rows in memory, of rows on the disk (the renewed counted there)).
/**
 * @param list<array{string, string, string}> $titles
 * @param array<string, MelCpmEntry> $c
 * @param int $now
 * @return array{array<string, MelCatTile>, array<string, bool>, array<string, string>, int, int, int}
 */
function mel_cpm_local($titles, $c, $now)
{
    $kept = 0;
    $mem = 0;
    $seen = array();
    $put = array();
    $renew = array();
    $want = array();
    $map = null;
    $times = null;
    foreach ($titles as $m)
    {
        list($type, $imdb, $tmdb) = $m;
        $key = mel_cpm_key($type, $imdb, $tmdb);
        $ek = $key !== '' ? mel_cpm_tile_key($type, $imdb, $tmdb, $c) : '';
        if ($key === '' || isset($seen[$key]) || isset($seen[$ek]))
            continue;
        $seen[$key] = true;
        $since = 0;
        if ($ek !== '')
        {
            $seen[$ek] = true;
            $since = $c[$ek]['t'];
            if ($now - $since >= 0 && $now - $since < MEL_CPM_STALE_S)
            {
                $kept++;
                continue;
            }
            // Renewed under the key it is kept by.
            $key = $ek;
            $ago = isset(MelCpm::$looked[$key]) ? $now - MelCpm::$looked[$key] : -1;
            if ($ago >= 0 && $ago < MEL_CPM_LOOK_S)
            {
                $kept++;
                continue;
            }
            $renew[$key] = true;
            if ($times === null)
                $times = mel_cpm_mem_times();
            $t = mel_cpm_times_tile($times, $type, $imdb, $tmdb, $since);
        }
        else
        {
            if ($map === null)
                $map = mel_cp_tile_map();
            $t = mel_cp_map_tile($map, $type, $imdb, $tmdb);
        }
        if ($t)
        {
            $t['type'] = $type;
            $put[$key] = $t;
            $mem++;
        }
        else
            $want[$key] = array($type, $imdb, $tmdb, $ek !== '' ? $since : 0);
    }
    $disk = $want ? mel_cat_cache_tiles($want) : array();
    $due = array();
    foreach ($want as $key => $m)
    {
        $key = strval($key);
        if (isset($disk[$key]))
            $put[$key] = $disk[$key];
        else if (isset($renew[$key]))
        {
            $kept++;
            MelCpm::$looked[$key] = $now;
        }
        else if (mel_cpm_due(isset($c[$key]) ? $c[$key] : null, $now))
            $due[$key] = $m[0];
    }
    return array($put, $renew, $due, $kept, $mem, count($disk));
}

// The tiles of the rows in memory got from the server (no listing of a copy after a failure), by "<type>|imdb:<IMDb>"
// and "<type>|tmdb:<TMDB>": array(the tile, when its listing began - st t0) of the newest listing with it.
/**
 * @return array<string, array{MelCatTile, int}>
 */
function mel_cpm_mem_times()
{
    $out = array();
    foreach (MelCat::$lists as $list)
    {
        if ($list['stale'])
            continue;
        $t0 = $list['st']['t0'];
        foreach ($list['tiles'] as $t)
        {
            foreach (array('imdb' => $t['imdb_id'], 'tmdb' => $t['_tmdbId']) as $k => $v)
            {
                $kk = "{$t['type']}|$k:$v";
                if ($v !== '' && (!isset($out[$kk]) || $out[$kk][1] < $t0))
                    $out[$kk] = array($t, $t0);
            }
        }
    }
    return $out;
}

// The tile of $times (mel_cpm_mem_times) of a title of type $type with IMDb $imdb, else TMDB $tmdb, of a listing begun
// after $since; null for none.
/**
 * @param array<string, array{MelCatTile, int}> $times
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param int $since
 * @return MelCatTile|null
 */
function mel_cpm_times_tile($times, $type, $imdb, $tmdb, $since)
{
    foreach (array("$type|imdb:$imdb" => $imdb, "$type|tmdb:$tmdb" => $tmdb) as $k => $v)
    {
        if ($v !== '' && isset($times[$k]))
            return $times[$k][1] > $since ? $times[$k][0] : null;
    }
    return null;
}

// mel_cpm_ask_due asks the network at time $now: no batch of it reached no server within MEL_CPM_BUILD_OFF_S
// (MelCpm::$off, 0 again at a reply of the server: mel_cpm_judge; a clock set back: none).
/**
 * @param int $now
 * @return bool
 */
function mel_cpm_build_net($now)
{
    $ago = $now - MelCpm::$off;
    return MelCpm::$off === 0 || $ago < 0 || $ago >= MEL_CPM_BUILD_OFF_S;
}

// The batches of mel_cpm_ask_due of $asked requests, $off of them of no connection: 2 or more, none connected -
// MelCpm::$off now. -> it is so.
/**
 * @param int $asked
 * @param int $off
 * @return bool
 */
function mel_cpm_build_off($asked, $off)
{
    if ($asked < 2 || $off < $asked)
        return false;
    MelCpm::$off = time();
    return true;
}

// Titles $due (key => type) less those cut short at a build within MEL_CPM_BUILD_CUT_S of $now (MelCpm::$cut; the older
// ones out of it).
/**
 * @param array<string, string> $due
 * @param int $now
 * @return array<string, string>
 */
function mel_cpm_uncut($due, $now)
{
    foreach (MelCpm::$cut as $key => $when)
    {
        if ($now - $when < 0 || $now - $when >= MEL_CPM_BUILD_CUT_S)
            unset(MelCpm::$cut[$key]);
    }
    return array_diff_key($due, MelCpm::$cut);
}

// The titles $due (key => type, in their order: mel_cpm_local) asked as a build asks them (mel_cpm_build; the opening
// of the screen "Продолжить" or "Мой список" on r24, view_my_list.php), $max at most in $limit s from $t0: none cut
// short at a build within MEL_CPM_BUILD_CUT_S (mel_cpm_uncut), none within MEL_CPM_BUILD_OFF_S of a batch of it that
// reached no server (mel_cpm_build_net); batches of MEL_HTTP_PAR while there is time (mel_cpm_ask_some), else MEL_CPM_N
// one by one once, none in a pause or with the catalog server down; judged as a step (mel_cpm_judge: a timeout of a
// rest under MEL_CPM_SHORT_S is of it - nothing kept, asked again by the tick, MelCpm::$cut now); a batch of 2 or more
// none of which connected - MelCpm::$off now (mel_cpm_build_off). Nothing written. -> array(key => tile to keep (null:
// a failure), asked, got, the marks of the log: '' or ", <mel_http_mark>[, pause][, unreached]" - pause: a pause of the
// meta after asking; unreached: some were due and none asked for MelCpm::$off, or set now).
/**
 * @param array<string, string> $due
 * @param float $t0
 * @param float $limit
 * @param int $max
 * @return array{array<string, MelCatTile|null>, int, int, string}
 */
function mel_cpm_ask_due($due, $t0, $limit, $max)
{
    $now = time();
    $net = mel_cpm_build_net($now);
    $todo = $net ? array_slice(mel_cpm_uncut($due, $now), 0, $max, true) : array();
    $put = array();
    $asked = 0;
    $got = 0;
    $par = 0;
    $seq = 0;
    $off = 0;
    while ($todo)
    {
        list($p, $a, $g, $at_once, $cut, $o) = mel_cpm_ask_some($todo, $t0, $limit, MEL_HTTP_PAR, MEL_CPM_N);
        foreach ($cut as $key)
            MelCpm::$cut[$key] = time();
        $put = array_merge($put, $p);
        $asked += $a;
        $got += $g;
        $off += $o;
        $par = max($par, $at_once);
        $seq += $at_once ? 0 : $a;
        if (!$a || !$at_once)
            break;
        $todo = array_slice($todo, $a, null, true);
    }
    $marks = array();
    if ($asked)
        $marks[] = mel_http_mark($par, $seq);
    if ($asked && mel_cpm_paused())
        $marks[] = 'pause';
    if ((!$net && $due) || mel_cpm_build_off($asked, $off))
        $marks[] = 'unreached';
    return array($put, $asked, $got, $marks ? ', ' . implode(', ', $marks) : '');
}

// The tiles of the rows "Continue" and "Мой список" (mel_cpm_build_titles of tab $tab, of those shown: $names; none by
// default) before they are built, so their tiles have the rating and the info panel at once: the steps of no request
// first (mel_cpm_local: kept, of the rows in memory or on the disk; a stale tile renewed by them), then the titles to
// ask asked in $limit s from the start (0: none, the tab "TV" builds its file with no request): mel_cpm_ask_due, one
// batch of MEL_HTTP_PAR (or MEL_CPM_N one by one). One write of the file; the titles not got go to the step as before
// (MelCpm::$miss, MelCpm::$ml of the rows built). One line of the log ("of rows" counts the tiles renewed too).
/**
 * @param string $tab
 * @param float $limit
 * @param list<string> $names
 * @return void
 */
function mel_cpm_build($tab, $limit, $names = array())
{
    $t0 = microtime(true);
    $now = time();
    list($put, $renew, $due, $kept, $mem, $disk) = mel_cpm_local(mel_cpm_build_titles($tab, $names), mel_cpm_read(), $now);
    list($got_put, $asked, $got, $mark) = mel_cpm_ask_due($due, $t0, $limit, MEL_HTTP_PAR);
    $put = array_merge($put, $got_put);
    if ($put)
        mel_cpm_put_all($put, $renew);
    mel_log(sprintf('continue: meta: build: %s: %d kept, %d of rows in memory, %d of rows on the disk, %d asked, %d got, ' .
        '%d left, %.0f ms%s', $tab !== '' ? $tab : 'all', $kept, $mem, $disk, $asked, $got, count($due) - $got,
        (microtime(true) - $t0) * 1000, $mark));
}
