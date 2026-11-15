<?php
// "Мой список" (My List, 0.61.0): the titles the user keeps in Melange, one JSON file of data_dir in the order of the
// row - the newest first, never reordered. In the memory of php_server, read anew when the file changes (aio_file_sig:
// the settings page writes it, by rename, on an import of a backup). The backup of the settings page
// (cgi/lib/backup.php) carries it in a short form (aio_ml_copy). The Favorites of the Dune are only read
// (aio_ml_fav_read), for an import by an action of the user. The functions of a file, an entry and the copy take what
// they need and log nothing: the settings page has them too (no main.php or parse.php there). Those of php_server
// need main.php (aio_data_dir, aio_file_sig, aio_write_file, aio_log).

define('AIO_ML_FILE', 'my_list.json');
// The version of the file: {"v": 1, "items": [<entry>...]}.
define('AIO_ML_V', 1);
// A bigger file is broken: the list of AIO_ML_COPY_MAX is ~0.6 KB a title in it (full addresses).
define('AIO_ML_FILE_MAX', 1048576);
// As AIO_CAT_NAME_MAX and AIO_CAT_URL_MAX of a tile (catalog.php).
define('AIO_ML_NAME_MAX', 200);
define('AIO_ML_URL_MAX', 1000);
// An id of a card of the Dune, as AIO_DUNE_ID (main.php): 24 hex, or a local card m-...
define('AIO_ML_DUNE_ID', '(?:[0-9a-f]{24}|m-[A-Za-z0-9_-]{1,64})');
// A rating of IMDb or Kinopoisk of a tile ("8.7", aio_cat_tile), its votes of Kinopoisk at most.
define('AIO_ML_RATE', '/^[0-9]{1,2}(\\.[0-9]{1,3})?$/D');
define('AIO_ML_VOTES_MAX', 999999999);
// The short form of the backup (aio_ml_copy), its field in the file of the export and its version.
define('AIO_ML_COPY_FIELD', 'my_list');
define('AIO_ML_COPY_V', 1);
// The most the list may take of the body of an import (cgi/lib/backup.php: settings=<the file, URL-encoded>, post_max_size
// 256K of cgi/php.ini): the rest, 32K, is of the settings. ~0.4 KB a title (a Russian name, the pictures of the catalog
// server): ~570 titles; a title over it is not added (aio_ml_add), so every export loads back.
define('AIO_ML_COPY_MAX', 229376);
// The Favorites of the Dune, as Favorites::reload of shell_ext r25 reads them (PHP of the Dune adds FS_PREFIX to /flashdata
// and /config itself). Tests may define them first.
if (!defined('AIO_ML_FAV_DIR'))
    define('AIO_ML_FAV_DIR', '/flashdata/main_screen_items');
if (!defined('AIO_ML_FAV_DIR2'))
    define('AIO_ML_FAV_DIR2', '/config/main_screen_items');
if (!defined('AIO_ML_FAV_LCFG'))
    define('AIO_ML_FAV_LCFG', '/config');
// Items of the Favorites read at most; a bigger file of one is skipped.
define('AIO_ML_FAV_MAX', 500);
define('AIO_ML_FAV_FILE_MAX', 262144);

class AioMl
{
    // The list in order (aio_ml_list), null: not read yet; the signature of the file it is of.
    /** @var list<AioMlEntry>|null */
    public static $list = null;
    /** @var string */
    public static $sig = '';
    // Why the file is not written over ('' - it may be): its version is newer, it is not readable.
    /** @var string */
    public static $lock = '';
}

// --- An entry.

// The key of title $t (a tile or an entry: the same fields), as that of the own card: "<type>:<id>", the id as of
// aio_cm_id (card_meta.php) - its IMDb, else tmdb:<id>; '' for none.
/**
 * @param array{type: string, imdb_id: string, _tmdbId: string} $t
 * @return string
 */
function aio_ml_key($t)
{
    if ($t['type'] !== 'movie' && $t['type'] !== 'series')
        return '';
    if ($t['imdb_id'] !== '')
        return $t['type'] . ':' . $t['imdb_id'];
    return $t['_tmdbId'] !== '' ? $t['type'] . ':tmdb:' . $t['_tmdbId'] : '';
}

// Title $a and title $b (entries or tiles) are one: of one type, the same IMDb or the same TMDB (one added of a tile of
// TMDB alone, its own card knows its IMDb of the meta).
/**
 * @param array{type: string, imdb_id: string, _tmdbId: string} $a
 * @param array{type: string, imdb_id: string, _tmdbId: string} $b
 * @return bool
 */
function aio_ml_same($a, $b)
{
    return $a['type'] === $b['type'] && (($a['imdb_id'] !== '' && $a['imdb_id'] === $b['imdb_id']) ||
        ($a['_tmdbId'] !== '' && $a['_tmdbId'] === $b['_tmdbId']));
}

// A string of one line, at most $max characters; '' for another value or broken UTF-8.
/**
 * @param mixed $v
 * @param int $max
 * @return string
 */
function aio_ml_text($v, $max)
{
    if (!is_string($v))
        return '';
    // PHP 5.3: broken UTF-8 -> null.
    $s = trim(strval(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $v)));
    return preg_match('/^.{0,' . $max . '}/su', $s, $m) ? rtrim($m[0]) : '';
}

// An address of a picture as a tile has it (aio_cat_img), '' for none.
/**
 * @param mixed $v
 * @return string
 */
function aio_ml_url($v)
{
    return is_string($v) && strlen($v) <= AIO_ML_URL_MAX && preg_match('~^https?://\S+$~D', $v) ? $v : '';
}

// An entry as stored, of the file, the copy or a tile -> its good fields with the key, null for one without a key or a
// name. A field that is not good is '' (added, kpVotes: 0); so is one an entry has not (the ratings of an entry of
// before them).
/**
 * @param mixed $e
 * @return AioMlEntry|null
 */
function aio_ml_clean($e)
{
    if (!is_array($e))
        return null;
    $s = array();
    foreach (array('type', 'year', 'imdb_id', '_tmdbId', 'kpId', 'imdbRating', 'kpRating', 'dune_id') as $k)
        $s[$k] = isset($e[$k]) && is_string($e[$k]) ? $e[$k] : '';
    $out = array('key' => '', 'type' => $s['type'] === 'movie' || $s['type'] === 'series' ? $s['type'] : '',
        'name' => aio_ml_text(isset($e['name']) ? $e['name'] : '', AIO_ML_NAME_MAX),
        'englishTitle' => aio_ml_text(isset($e['englishTitle']) ? $e['englishTitle'] : '', AIO_ML_NAME_MAX),
        'year' => preg_match('/^[0-9]{4}$/D', $s['year']) ? $s['year'] : '',
        'poster' => aio_ml_url(isset($e['poster']) ? $e['poster'] : ''),
        'background' => aio_ml_url(isset($e['background']) ? $e['background'] : ''),
        'imdb_id' => preg_match('/^tt[0-9]{1,10}$/D', $s['imdb_id']) ? $s['imdb_id'] : '',
        '_tmdbId' => preg_match('/^[0-9]{1,10}$/D', $s['_tmdbId']) ? $s['_tmdbId'] : '',
        'kpId' => preg_match('/^[1-9][0-9]{0,9}$/D', $s['kpId']) ? $s['kpId'] : '',
        'imdbRating' => preg_match(AIO_ML_RATE, $s['imdbRating']) ? $s['imdbRating'] : '',
        'kpRating' => preg_match(AIO_ML_RATE, $s['kpRating']) ? $s['kpRating'] : '',
        'kpVotes' => isset($e['kpVotes']) && is_int($e['kpVotes']) && $e['kpVotes'] >= 0 && $e['kpVotes'] <= AIO_ML_VOTES_MAX ?
            $e['kpVotes'] : 0,
        'dune_id' => preg_match('/^' . AIO_ML_DUNE_ID . '$/D', $s['dune_id']) ? $s['dune_id'] : '',
        'added' => isset($e['added']) && is_int($e['added']) && $e['added'] > 0 ? $e['added'] : 0);
    $out['key'] = aio_ml_key($out);
    return $out['key'] !== '' && $out['name'] !== '' ? $out : null;
}

// The entry of tile $t (aio_cat_tile), added at $now, $dune: the id of its card of the Dune ('' - none or not known);
// its ratings as the tile has them now (the sticker of a tile of the list, aio_ml_tile; '' none); null for a tile
// without IMDb or TMDB.
/**
 * @param AioCatTile $t
 * @param string $dune
 * @param int $now
 * @return AioMlEntry|null
 */
function aio_ml_entry($t, $dune, $now)
{
    return aio_ml_clean(array('type' => $t['type'], 'name' => $t['name'], 'englishTitle' => $t['englishTitle'],
        'year' => $t['year'], 'poster' => $t['poster'], 'background' => $t['background'], 'imdb_id' => $t['imdb_id'],
        '_tmdbId' => $t['_tmdbId'], 'kpId' => $t['kpId'], 'imdbRating' => $t['imdbRating'], 'kpRating' => $t['kpRating'],
        'kpVotes' => $t['kpVotes'], 'dune_id' => $dune, 'added' => $now));
}

// The tile of entry $e for a row and the own card (aio_oc_enter): what the entry has (its ratings too: kpVotes kept, a
// copy of the tile parsed again by aio_cat_tile keeps kpRating), the rest empty (the own card takes it from its meta).
/**
 * @param AioMlEntry $e
 * @return AioCatTile
 */
function aio_ml_tile($e)
{
    return array('id' => $e['imdb_id'] !== '' ? $e['imdb_id'] : 'tmdb:' . $e['_tmdbId'], 'imdb_id' => $e['imdb_id'],
        '_tmdbId' => $e['_tmdbId'], 'type' => $e['type'], 'name' => $e['name'], 'year' => $e['year'], 'imdbRating' => $e['imdbRating'],
        'description' => '', 'genres' => array(), 'poster' => $e['poster'], 'background' => $e['background'], 'country' => '',
        'runtime' => '', 'age' => '', 'cast' => array(), 'englishTitle' => $e['englishTitle'], 'kpRating' => $e['kpRating'],
        'kpVotes' => $e['kpVotes'],
        'kpId' => $e['kpId'], 'logo' => '', 'logoLang' => '');
}

// Entries as stored -> the good ones (aio_ml_clean) in order, a key once (the first); $dropped: how many were not taken.
/**
 * @param array<mixed> $items
 * @param int $dropped
 * @return list<AioMlEntry>
 */
function aio_ml_items($items, &$dropped)
{
    $list = array();
    $seen = array();
    $dropped = 0;
    foreach ($items as $x)
    {
        $e = aio_ml_clean($x);
        if (!$e || isset($seen[$e['key']]))
        {
            $dropped++;
            continue;
        }
        $seen[$e['key']] = true;
        $list[] = $e;
    }
    return $list;
}

// --- The file.

// The text of the file of list $list; '' if json_encode failed.
/**
 * @param list<AioMlEntry> $list
 * @return string
 */
function aio_ml_json($list)
{
    $j = json_encode(array('v' => AIO_ML_V, 'items' => $list));
    return is_string($j) ? $j : '';
}

// The list of the file $path, null when it is not taken; $why ('' - read, or no file: an empty list): 'unreadable',
// 'v <n> newer' (of a later Melange) or, the file broken, 'too big', 'not JSON', 'not a list' (aio_ml_broken);
// $dropped: entries not taken (aio_ml_items).
/**
 * @param string $path
 * @param string $why
 * @param int $dropped
 * @return list<AioMlEntry>|null
 */
function aio_ml_read_file($path, &$why, &$dropped)
{
    $why = '';
    $dropped = 0;
    clearstatcache();
    if (!file_exists($path) && !is_link($path))
        return array();
    $size = is_file($path) ? filesize($path) : false;
    if ($size !== false && $size > AIO_ML_FILE_MAX)
    {
        $why = 'too big';
        return null;
    }
    $text = $size !== false && is_readable($path) ? @file_get_contents($path) : false;
    if (!is_string($text))
    {
        $why = 'unreadable';
        return null;
    }
    $j = json_decode($text, true);
    if (!is_array($j))
    {
        $why = 'not JSON';
        return null;
    }
    $v = isset($j['v']) ? $j['v'] : null;
    if (is_int($v) && $v > AIO_ML_V)
    {
        $why = "v $v newer";
        return null;
    }
    if ($v !== AIO_ML_V || !isset($j['items']) || !is_array($j['items']) || $j['items'] !== array_values($j['items']))
    {
        $why = 'not a list';
        return null;
    }
    return aio_ml_items($j['items'], $dropped);
}

// $why of aio_ml_read_file is of a broken file (moved aside, a new one may be written).
/**
 * @param string $why
 * @return bool
 */
function aio_ml_broken($why)
{
    return in_array($why, array('too big', 'not JSON', 'not a list'), true);
}

// --- The copy of the backup.

// List $list in the short form of the backup (the field AIO_ML_COPY_FIELD of the file of the export): {v, dirs: the
// parts of the addresses of the pictures up to their last "/", each once, items: a title each, [type, name,
// englishTitle, year, poster, background, imdb_id, _tmdbId, kpId, dune_id, added] and, of a title with a rating,
// [imdbRating, kpRating, kpVotes] after them (a Melange of before them reads the 11 alone), a picture as [<its part in
// dirs>, <the rest>] or ''}. No key (aio_ml_key makes it again) and no names of fields: 200 titles take ~80 KB of the
// body of the import, not ~180 KB of the form of the file.
/**
 * @param list<AioMlEntry> $list
 * @return array{v: int, dirs: list<string>, items: list<array<int, mixed>>}
 */
function aio_ml_copy($list)
{
    $dirs = array();
    $items = array();
    foreach ($list as $e)
    {
        $pics = array();
        foreach (array('poster', 'background') as $k)
        {
            $u = $e[$k];
            $p = strrpos($u, '/');
            if ($u === '' || $p === false)
            {
                $pics[] = '';
                continue;
            }
            $d = substr($u, 0, $p + 1);
            if (!isset($dirs[$d]))
                $dirs[$d] = count($dirs);
            $pics[] = array($dirs[$d], strval(substr($u, $p + 1)));
        }
        $x = array($e['type'], $e['name'], $e['englishTitle'], $e['year'], $pics[0], $pics[1], $e['imdb_id'],
            $e['_tmdbId'], $e['kpId'], $e['dune_id'], $e['added']);
        if ($e['imdbRating'] !== '' || $e['kpRating'] !== '')
            array_push($x, $e['imdbRating'], $e['kpRating'], $e['kpVotes']);
        $items[] = $x;
    }
    return array('v' => AIO_ML_COPY_V, 'dirs' => array_map('strval', array_keys($dirs)), 'items' => $items);
}

// The copy $c of a file of the export (aio_ml_copy, as json_decode gives it) -> its list (aio_ml_items: what is not
// good dropped, counted in $dropped), null for none: $why 'v <n> newer' (of a later Melange), 'not a list'.
/**
 * @param mixed $c
 * @param string $why
 * @param int $dropped
 * @return list<AioMlEntry>|null
 */
function aio_ml_from_copy($c, &$why, &$dropped)
{
    $why = '';
    $dropped = 0;
    $v = is_array($c) && isset($c['v']) ? $c['v'] : null;
    if (is_int($v) && $v > AIO_ML_COPY_V)
    {
        $why = "v $v newer";
        return null;
    }
    if ($v !== AIO_ML_COPY_V || !isset($c['dirs'], $c['items']) || !is_array($c['dirs']) || !is_array($c['items']))
    {
        $why = 'not a list';
        return null;
    }
    $dirs = $c['dirs'];
    $list = array();
    foreach ($c['items'] as $x)
    {
        // The ratings after them, if all three are there; fields after these (of a later form of v 1): not read.
        if (!is_array($x) || count($x) < 11 || array_keys(array_slice($x, 0, 11, true)) !== range(0, 10))
        {
            $list[] = null;
            continue;
        }
        $pics = array();
        foreach (array(4, 5) as $i)
        {
            $p = $x[$i];
            $pics[] = is_array($p) && count($p) === 2 && isset($p[0], $p[1]) && is_int($p[0]) && isset($dirs[$p[0]]) &&
                is_string($dirs[$p[0]]) && is_string($p[1]) ? $dirs[$p[0]] . $p[1] : '';
        }
        $list[] = array('type' => $x[0], 'name' => $x[1], 'englishTitle' => $x[2], 'year' => $x[3], 'poster' => $pics[0],
            'background' => $pics[1], 'imdb_id' => $x[6], '_tmdbId' => $x[7], 'kpId' => $x[8], 'dune_id' => $x[9],
            'added' => $x[10]);
        if (count($x) >= 14 && array_keys(array_slice($x, 11, 3, true)) === range(11, 13))
            $list[count($list) - 1] += array('imdbRating' => $x[11], 'kpRating' => $x[12], 'kpVotes' => $x[13]);
    }
    return aio_ml_items($list, $dropped);
}

// What list $list adds to the body of an import (,"my_list":<the copy> in the file, URL-encoded as the page posts it:
// rawurlencode is never shorter than encodeURIComponent).
/**
 * @param list<AioMlEntry> $list
 * @return int
 */
function aio_ml_copy_size($list)
{
    return strlen(rawurlencode(',"' . AIO_ML_COPY_FIELD . '":' . json_encode(aio_ml_copy($list))));
}

// --- The list in php_server.

/**
 * @return string
 */
function aio_ml_path()
{
    return aio_data_dir() !== '' ? aio_data_dir() . '/' . AIO_ML_FILE : '';
}

// The list in order, the newest first. Read when the file is not that of the memory (aio_file_sig: a rename over it has
// a new inode). A broken file: renamed to my_list.json.bad (one before it replaced), the list empty, a new file written
// on a change; not renamed, a newer version or one not readable: the list empty, the file not written over
// (AioMl::$lock). One line of the log each time.
/**
 * @return list<AioMlEntry>
 */
function aio_ml_list()
{
    $sig = aio_file_sig(AIO_ML_FILE);
    if (AioMl::$list !== null && $sig === AioMl::$sig)
        return AioMl::$list;
    AioMl::$sig = $sig;
    AioMl::$lock = '';
    $path = aio_ml_path();
    $why = '';
    $dropped = 0;
    $list = $path !== '' ? aio_ml_read_file($path, $why, $dropped) : array();
    if ($list === null)
    {
        $list = array();
        $moved = aio_ml_broken($why) && @rename($path, "$path.bad");
        if ($moved)
            AioMl::$sig = aio_file_sig(AIO_ML_FILE);
        else
            AioMl::$lock = $why;
        aio_log('my list: ' . AIO_ML_FILE . " $why, " . ($moved ? 'moved to ' . AIO_ML_FILE . '.bad' : 'not written over') .
            ', the list empty');
    }
    elseif ($dropped)
        aio_log("my list: $dropped entries of " . AIO_ML_FILE . ' not good, dropped');
    AioMl::$list = $list;
    return $list;
}

// List $list written as the file, then in memory. -> '' or why not: 'locked' (AioMl::$lock), 'write'.
/**
 * @param list<AioMlEntry> $list
 * @return string
 */
function aio_ml_save($list)
{
    if (AioMl::$lock !== '')
    {
        aio_log('my list: not saved, ' . AIO_ML_FILE . ' ' . AioMl::$lock);
        return 'locked';
    }
    $path = aio_ml_path();
    $data = aio_ml_json($list);
    if ($path === '' || $data === '' || !aio_write_file($path, $data))
    {
        aio_log('my list: not saved, write failed');
        return 'write';
    }
    AioMl::$list = $list;
    AioMl::$sig = aio_file_sig(AIO_ML_FILE);
    return '';
}

/**
 * @param string $key
 * @return bool
 */
function aio_ml_has($key)
{
    foreach (aio_ml_list() as $e)
    {
        if ($e['key'] === $key)
            return true;
    }
    return false;
}

// Tile $t at the start of the list, $dune: the id of its card of the Dune ('' - none or not known). One in the list
// already (aio_ml_same) stays in its place; its dune_id not known, it is set to $dune. -> '' (added, or there), 'no id' (no IMDb or
// TMDB, no name), 'full' (the backup would not load back with it: AIO_ML_COPY_MAX), 'locked', 'write' (aio_ml_save).
/**
 * @param AioCatTile $t
 * @param string $dune
 * @return string
 */
function aio_ml_add($t, $dune = '')
{
    $list = aio_ml_list();
    $e = aio_ml_entry($t, $dune, time());
    if (!$e)
    {
        aio_log("my list: {$t['type']}:" . ($t['imdb_id'] !== '' ? $t['imdb_id'] : $t['_tmdbId']) . ' not added, no id or name');
        return 'no id';
    }
    foreach ($list as $i => $x)
    {
        if (!aio_ml_same($x, $e))
            continue;
        if ($x['dune_id'] !== '' || $e['dune_id'] === '')
            return '';
        $list[$i]['dune_id'] = $e['dune_id'];
        return aio_ml_save($list);
    }
    array_unshift($list, $e);
    if (aio_ml_copy_size($list) > AIO_ML_COPY_MAX)
    {
        aio_log("my list: {$e['key']} not added, the backup would be over " . AIO_ML_COPY_MAX . ' bytes');
        return 'full';
    }
    $why = aio_ml_save($list);
    if ($why === '')
        aio_log("my list: {$e['key']} added, " . count($list) . ' titles');
    return $why;
}

// The title of key $key out of the list. -> '' (taken out, or not there), 'locked', 'write' (aio_ml_save).
/**
 * @param string $key
 * @return string
 */
function aio_ml_del($key)
{
    $list = aio_ml_list();
    foreach ($list as $i => $e)
    {
        if ($e['key'] !== $key)
            continue;
        array_splice($list, $i, 1);
        $why = aio_ml_save($list);
        if ($why === '')
            aio_log("my list: $key removed, " . count($list) . ' titles');
        return $why;
    }
    return '';
}

// The entry with the card of the Dune $dune, null for none.
/**
 * @param string $dune
 * @return AioMlEntry|null
 */
function aio_ml_find_dune($dune)
{
    foreach ($dune !== '' ? aio_ml_list() : array() as $e)
    {
        if ($e['dune_id'] === $dune)
            return $e;
    }
    return null;
}

// The key of the entry of title $t in the list (aio_ml_same), else of the one of its card of the Dune $dune ('' not
// known); '' for none.
/**
 * @param array{type: string, imdb_id: string, _tmdbId: string} $t
 * @param string $dune
 * @return string
 */
function aio_ml_in($t, $dune = '')
{
    foreach (aio_ml_list() as $e)
    {
        if (aio_ml_same($e, $t))
            return $e['key'];
    }
    $e = aio_ml_find_dune($dune);
    return $e ? $e['key'] : '';
}

// --- The actions of the screens (php_server: also view_catalog.php, continue.php).

// Title $t into the list ($on; aio_ml_add) or out of it (aio_ml_del of aio_ml_in), $dune: the id of its card of the
// Dune ('' - none or not known). -> '' or why not, as they give it.
/**
 * @param AioCatTile $t
 * @param string $dune
 * @param bool $on
 * @return string
 */
function aio_ml_set($t, $dune, $on)
{
    if ($on)
        return aio_ml_add($t, $dune);
    $key = aio_ml_in($t, $dune);
    return $key !== '' ? aio_ml_del($key) : '';
}

// The message of why $why of aio_ml_set ('' none: null): the list full, its file of a later Melange or not readable,
// not written.
/**
 * @param string $why
 * @return AioAction|null
 */
function aio_ml_err($why)
{
    $keys = array('full' => 'ml_err_full', 'locked' => 'ml_err_locked', 'write' => 'ml_err_write', 'no id' => 'err_no_imdb');
    return $why === '' ? null : aio_error(isset($keys[$why]) ? $keys[$why] : 'ml_err_write', $why);
}

// The item of POP UP of tile $id of screen $from (aio_catv_tile_menu): "Добавить в «Мой список»" / "Убрать из «Моего
// списка»" by the list now (aio_ml_in; of the row "Continue" by its card of the Dune too). None but on a tile of a
// title with IMDb or TMDB (aio_catv_tile).
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_ml_menu_items($id, $from, $lang)
{
    $t = aio_catv_tile($id, aio_tv_is_from($from));
    if (!$t || aio_ml_key($t) === '')
        return array();
    $in = aio_ml_in($t, aio_cp_mid_of($id)) !== '';
    return array(array(
        GuiMenuItemDef::caption => aio_tr($lang, $in ? 'ml_del' : 'ml_add'),
        GuiMenuItemDef::icon_url => AIO_CATV_ICONS . ($in ? 'cancel_small.png' : 'favorites_small.png'),
        GuiMenuItemDef::action => aio_input('cat_ml', array('from' => $from, 'item' => $id, 'w' => $in ? 'del' : 'add'))));
}

// cat_ml of POP UP (aio_ml_menu_items; w add, del) of tile $in->item of screen $in->from: the title into the list or out
// of it (aio_ml_set; its card of the Dune of the row "Continue" or of the pairs, no request), then the screen anew
// (aio_catv_tile_after: the line of the info panel); not written: the message (aio_ml_err), the list as it was.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_ml_menu_act($in)
{
    $id = aio_catv_in($in, 'item');
    $from = aio_catv_in($in, 'from');
    $w = aio_catv_in($in, 'w');
    $t = $w === 'add' || $w === 'del' ? aio_catv_tile($id, aio_tv_is_from($from)) : null;
    if (!$t)
        return $w === 'add' || $w === 'del' ? aio_error('cat_outdated') : null;
    $mid = aio_cp_mid_of($id);
    $why = aio_ml_set($t, $mid !== '' ? $mid : aio_pg_tile_card($t, aio_pg_index()), $w === 'add');
    return $why !== '' ? aio_ml_err($why) : aio_catv_tile_after($from, "my list $w");
}

// --- The Favorites of the Dune, read only.

// "key = value" lines of file $path (HD::parse_props of the shell: split at the first "=", not at the start of a
// line, both trimmed); null for no file or a bigger one.
/**
 * @param string $path
 * @return array<string, string>|null
 */
function aio_ml_props($path)
{
    $text = is_file($path) && filesize($path) <= AIO_ML_FAV_FILE_MAX ? @file_get_contents($path) : false;
    if (!is_string($text))
        return null;
    $props = array();
    foreach (explode("\n", $text) as $line)
    {
        $p = strpos($line, '=');
        if ($p)
            $props[trim(substr($line, 0, $p))] = trim(substr($line, $p + 1));
    }
    return $props;
}

// The order of a list config of the shell (lcfg_<id>.txt: the lines after "---"), empty for none.
/**
 * @param string $path
 * @return list<string>
 */
function aio_ml_lcfg_order($path)
{
    $text = is_file($path) && filesize($path) <= AIO_ML_FAV_FILE_MAX ? @file_get_contents($path) : false;
    $order = array();
    $on = false;
    foreach (is_string($text) ? explode("\n", $text) : array() as $line)
    {
        $line = trim($line);
        if ($on && $line !== '')
            $order[] = $line;
        elseif (strpos($line, '---') === 0)
            $on = true;
    }
    return $order;
}

// The fields of plugin_launcher://{name=shell_ext}{action_id=file_movie}{movie_id=...} (Favorites::parse_plugin_launcher_url
// of the shell), empty for another address.
/**
 * @param string $url
 * @return array<string, string>
 */
function aio_ml_launcher($url)
{
    $out = array();
    if (strncmp($url, 'plugin_launcher://', 18) !== 0)
        return $out;
    foreach (preg_split('/[{}]+/', substr($url, 18), -1, PREG_SPLIT_NO_EMPTY) as $w)
    {
        $p = strpos($w, '=');
        if ($p)
            $out[substr($w, 0, $p)] = strval(substr($w, $p + 1));
    }
    return $out;
}

// The movies of the folder @Movies of the Favorites ($dir; r25: <id>.json - id, title, series_type, poster_url, year -
// beside its poster <id>.jpg) in the order of $lcfg/lcfg_fav_movies.txt, the rest the oldest first (Favorites::
// load_folder_movies of the shell); a file of another id or not JSON skipped.
/**
 * @param string $dir
 * @param string $lcfg
 * @return list<AioMlFav>
 */
function aio_ml_fav_movies($dir, $lcfg)
{
    $names = is_dir($dir) ? @scandir($dir) : false;
    $found = array();
    $rest = array();
    foreach (is_array($names) ? $names : array() as $f)
    {
        if (!preg_match('/^(' . AIO_ML_DUNE_ID . ')\.json$/D', $f, $m) || count($found) >= AIO_ML_FAV_MAX)
            continue;
        $id = $m[1];
        $path = "$dir/$f";
        $j = is_file($path) && filesize($path) <= AIO_ML_FAV_FILE_MAX ? json_decode(strval(@file_get_contents($path)), true) : null;
        if (!is_array($j) || !isset($j['id']) || $j['id'] !== $id)
            continue;
        $year = isset($j['year']) && (is_int($j['year']) || is_string($j['year'])) ? strval($j['year']) : '';
        $found[$id] = array('dune_id' => $id, 'type' => !empty($j['series_type']) ? 'series' : 'movie',
            'name' => aio_ml_text(isset($j['title']) ? $j['title'] : '', AIO_ML_NAME_MAX),
            'year' => preg_match('/^[0-9]{4}/', $year, $y) ? $y[0] : '', 'poster' => aio_ml_url(isset($j['poster_url']) ? $j['poster_url'] : ''),
            'file' => is_file("$dir/$id.jpg") ? "$dir/$id.jpg" : '');
        $rest[$id] = sprintf('%012d', intval(filemtime($path))) . " $id";
    }
    $out = array();
    foreach (aio_ml_lcfg_order("$lcfg/lcfg_fav_movies.txt") as $id)
    {
        if (isset($found[$id], $rest[$id]))
        {
            $out[] = $found[$id];
            unset($rest[$id]);
        }
    }
    asort($rest, SORT_STRING);
    foreach ($rest as $id => $k)
        $out[] = $found[$id];
    return $out;
}

// The movies of the Favorites of the Dune, read only, as Favorites::reload of shell_ext r25 has them: the items of the
// folders $dirs (a folder each with dune_folder.txt) in the order of $lcfg/lcfg_favorites.txt (their paths; the rest by
// name; a name in the second folder too: that one), a movie of the shell - media_url
// plugin_launcher://{name=shell_ext}{action_id=file_movie}{movie_id=<id>}{series=0|1} - each, the folder @Movies of
// r25 in its place: its movies (aio_ml_fav_movies). Persons, folders of the network, apps, other plugins: skipped; a
// movie twice: the first. -> {dune_id, type (movie, series; '' - not known: an item of before {series}), name, year
// ('' for an item), poster (an address; of @Movies with auth= of dunemdb: not for a log or a file), file (the picture
// on the Dune; '' none)}. No request, nothing written.
/**
 * @param list<string> $dirs
 * @param string $lcfg
 * @return list<AioMlFav>
 */
function aio_ml_fav_read($dirs = array(AIO_ML_FAV_DIR, AIO_ML_FAV_DIR2), $lcfg = AIO_ML_FAV_LCFG)
{
    $items = array();
    foreach ($dirs as $dir)
    {
        $names = is_dir($dir) ? @scandir($dir) : false;
        foreach (is_array($names) ? $names : array() as $name)
        {
            $props = $name !== '.' && $name !== '..' && count($items) < AIO_ML_FAV_MAX ? aio_ml_props("$dir/$name/dune_folder.txt") : null;
            if ($props !== null)
                $items[$name] = array('path' => "$dir/$name", 'props' => $props);
        }
    }
    $rank = array_flip(aio_ml_lcfg_order("$lcfg/lcfg_favorites.txt"));
    $sorted = array();
    foreach ($items as $name => $it)
    {
        $r = isset($rank[$it['path']]) ? $rank[$it['path']] : count($rank);
        $sorted[sprintf('%06d', $r) . "\0" . $name] = $it + array('name' => strval($name));
    }
    ksort($sorted, SORT_STRING);
    $out = array();
    $seen = array();
    foreach ($sorted as $it)
    {
        $p = $it['props'];
        $l = aio_ml_launcher(isset($p['media_url']) ? $p['media_url'] : '');
        if ($it['name'] === '@Movies')
            $got = aio_ml_fav_movies($it['path'], $lcfg);
        elseif (isset($l['name'], $l['action_id'], $l['movie_id']) && $l['name'] === 'shell_ext' && $l['action_id'] === 'file_movie' &&
            preg_match('/^' . AIO_ML_DUNE_ID . '$/D', $l['movie_id']))
        {
            $icon = isset($p['icon_path']) ? $p['icon_path'] : '';
            $file = $icon === '' || strpos($icon, '://') !== false ? '' : ($icon[0] === '/' ? $icon : "{$it['path']}/$icon");
            $series = isset($l['series']) ? $l['series'] : '';
            $name = aio_ml_text(isset($p['caption']) && $p['caption'] !== '' ? $p['caption'] : $it['name'], AIO_ML_NAME_MAX);
            $got = array(array('dune_id' => $l['movie_id'], 'type' => $series === '1' ? 'series' : ($series === '0' ? 'movie' : ''),
                'name' => $name, 'year' => '', 'poster' => aio_ml_url($icon), 'file' => $file !== '' && is_file($file) ? $file : ''));
        }
        else
            $got = array();
        foreach ($got as $m)
        {
            if (!isset($seen[$m['dune_id']]))
                $out[] = $m;
            $seen[$m['dune_id']] = true;
        }
    }
    return $out;
}
