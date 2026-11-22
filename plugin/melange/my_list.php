<?php
// "Мой список" (My List, 0.62.0): the titles the user keeps in Melange, one JSON file of data_dir in the order of the
// row - the newest first, never reordered. In the memory of php_server, read anew when the file changes (mel_file_sig:
// the settings page writes it, by rename, on an import of a backup). The backup of the settings page
// (cgi/lib/backup.php) carries it in a short form (mel_ml_copy). The Favorites of the Dune are only read
// (mel_ml_fav_read), for an import by an action of the user. The functions of a file, an entry and the copy take what
// they need and log nothing: the settings page has them too (no main.php or parse.php there). Those of php_server
// need main.php (mel_data_dir, mel_file_sig, mel_write_file, mel_log). The actions of the screens: view_my_list.php.
// Names: "Мой список" of the code (mel_ml_*, mel_mlv_*, my_list.json, the keys ml_* of the translations) is "Избранное"
// / Favorites on the screen; mel_ml_fav_* and "the Favorites" in the comments of the reading of the shell are the
// Favorites of the Dune ("Избранное" of its main screen); cat_fav is of the keys Fav 1 / Fav 2 of the remote.

define('MEL_ML_FILE', 'my_list.json');
// The version of the file: {"v": 1, "items": [<entry>...]}.
define('MEL_ML_V', 1);
// A bigger file is broken: the list of MEL_ML_COPY_MAX is ~0.6 KB a title in it (full addresses).
define('MEL_ML_FILE_MAX', 1048576);
// As MEL_CAT_NAME_MAX and MEL_CAT_URL_MAX of a tile (catalog.php).
define('MEL_ML_NAME_MAX', 200);
define('MEL_ML_URL_MAX', 1000);
// An id of a card of the Dune, as MEL_DUNE_ID (main.php): 24 hex, or a local card m-...
define('MEL_ML_DUNE_ID', '(?:[0-9a-f]{24}|m-[A-Za-z0-9_-]{1,64})');
// A rating of IMDb or Kinopoisk of a tile ("8.7", mel_cat_tile), its votes of Kinopoisk at most.
define('MEL_ML_RATE', '/^[0-9]{1,2}(\\.[0-9]{1,3})?$/D');
define('MEL_ML_VOTES_MAX', 999999999);
// The short form of the backup (mel_ml_copy), its field in the file of the export and its version.
define('MEL_ML_COPY_FIELD', 'my_list');
define('MEL_ML_COPY_V', 1);
// The most the list may take of the body of an import (cgi/lib/backup.php: settings=<the file, URL-encoded>, post_max_size
// 1M of cgi/php.ini): of the rest, 32K is of the settings, 768K of the hidden titles. ~0.4 KB a title (a Russian name,
// the pictures of the catalog server): ~570 titles; a title over it is not added (mel_ml_add), so every export loads back.
define('MEL_ML_COPY_MAX', 229376);
// The Favorites of the Dune, as Favorites::reload of shell_ext r25 reads them (PHP of the Dune adds FS_PREFIX to /flashdata
// and /config itself). Tests may define them first.
if (!defined('MEL_ML_FAV_DIR'))
    define('MEL_ML_FAV_DIR', '/flashdata/main_screen_items');
if (!defined('MEL_ML_FAV_DIR2'))
    define('MEL_ML_FAV_DIR2', '/config/main_screen_items');
if (!defined('MEL_ML_FAV_LCFG'))
    define('MEL_ML_FAV_LCFG', '/config');
// Items of the Favorites read at most; a bigger file of one is skipped.
define('MEL_ML_FAV_MAX', 500);
define('MEL_ML_FAV_FILE_MAX', 262144);

class MelMl
{
    // The list in order (mel_ml_list), null: not read yet; the signature of the file it is of.
    /** @var list<MelMlEntry>|null */
    public static $list = null;
    /** @var string */
    public static $sig = '';
    // Why the file is not written over ('' - it may be): its version is newer, it is not readable.
    /** @var string */
    public static $lock = '';
}

// --- An entry.

// The key of title $t (a tile or an entry: the same fields), as that of the own card: "<type>:<id>", the id as of
// mel_cm_id (card_meta.php) - its IMDb, else tmdb:<id>; '' for none.
/**
 * @param array{type: string, imdb_id: string, _tmdbId: string} $t
 * @return string
 */
function mel_ml_key($t)
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
function mel_ml_same($a, $b)
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
function mel_ml_text($v, $max)
{
    if (!is_string($v))
        return '';
    // PHP 5.3: broken UTF-8 -> null.
    $s = trim(strval(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $v)));
    return preg_match('/^.{0,' . $max . '}/su', $s, $m) ? rtrim($m[0]) : '';
}

// An address of a picture as a tile has it (mel_cat_img: http or https), or the path of a picture of the Favorites of
// the Dune (of a root item, mel_ml_fav_read: as the shell shows it; its folder may have spaces, no ".."); '' for another.
/**
 * @param mixed $v
 * @return string
 */
function mel_ml_url($v)
{
    if (!is_string($v) || strlen($v) > MEL_ML_URL_MAX)
        return '';
    if (preg_match('~^https?://\S+$~D', $v))
        return $v;
    foreach (array(MEL_ML_FAV_DIR, MEL_ML_FAV_DIR2) as $dir)
    {
        if (strpos($v, "$dir/") === 0 && !preg_match('~[\x00-\x1f\x7f]|/\.\.(/|$)~', $v))
            return $v;
    }
    return '';
}

// An entry as stored, of the file, the copy or a tile -> its good fields with the key, null for one without a key or a
// name. A field that is not good is '' (added, kpVotes: 0); so is one an entry has not (the ratings of an entry of
// before them).
/**
 * @param mixed $e
 * @return MelMlEntry|null
 */
function mel_ml_clean($e)
{
    if (!is_array($e))
        return null;
    $s = array();
    foreach (array('type', 'year', 'imdb_id', '_tmdbId', 'kpId', 'imdbRating', 'kpRating', 'dune_id') as $k)
        $s[$k] = isset($e[$k]) && is_string($e[$k]) ? $e[$k] : '';
    $out = array('key' => '', 'type' => $s['type'] === 'movie' || $s['type'] === 'series' ? $s['type'] : '',
        'name' => mel_ml_text(isset($e['name']) ? $e['name'] : '', MEL_ML_NAME_MAX),
        'englishTitle' => mel_ml_text(isset($e['englishTitle']) ? $e['englishTitle'] : '', MEL_ML_NAME_MAX),
        'year' => preg_match('/^[0-9]{4}$/D', $s['year']) ? $s['year'] : '',
        'poster' => mel_ml_url(isset($e['poster']) ? $e['poster'] : ''),
        'background' => mel_ml_url(isset($e['background']) ? $e['background'] : ''),
        'imdb_id' => preg_match('/^tt[0-9]{1,10}$/D', $s['imdb_id']) ? $s['imdb_id'] : '',
        '_tmdbId' => preg_match('/^[0-9]{1,10}$/D', $s['_tmdbId']) ? $s['_tmdbId'] : '',
        'kpId' => preg_match('/^[1-9][0-9]{0,9}$/D', $s['kpId']) ? $s['kpId'] : '',
        'imdbRating' => preg_match(MEL_ML_RATE, $s['imdbRating']) ? $s['imdbRating'] : '',
        'kpRating' => preg_match(MEL_ML_RATE, $s['kpRating']) ? $s['kpRating'] : '',
        'kpVotes' => isset($e['kpVotes']) && is_int($e['kpVotes']) && $e['kpVotes'] >= 0 && $e['kpVotes'] <= MEL_ML_VOTES_MAX ?
            $e['kpVotes'] : 0,
        'dune_id' => preg_match('/^' . MEL_ML_DUNE_ID . '$/D', $s['dune_id']) ? $s['dune_id'] : '',
        'added' => isset($e['added']) && is_int($e['added']) && $e['added'] > 0 ? $e['added'] : 0);
    $out['key'] = mel_ml_key($out);
    return $out['key'] !== '' && $out['name'] !== '' ? $out : null;
}

// The entry of tile $t (mel_cat_tile), added at $now, $dune: the id of its card of the Dune ('' - none or not known);
// its ratings as the tile has them now (the sticker of a tile of the list, mel_ml_tile; '' none); null for a tile
// without IMDb or TMDB.
/**
 * @param MelCatTile $t
 * @param string $dune
 * @param int $now
 * @return MelMlEntry|null
 */
function mel_ml_entry($t, $dune, $now)
{
    return mel_ml_clean(array('type' => $t['type'], 'name' => $t['name'], 'englishTitle' => $t['englishTitle'],
        'year' => $t['year'], 'poster' => $t['poster'], 'background' => $t['background'], 'imdb_id' => $t['imdb_id'],
        '_tmdbId' => $t['_tmdbId'], 'kpId' => $t['kpId'], 'imdbRating' => $t['imdbRating'], 'kpRating' => $t['kpRating'],
        'kpVotes' => $t['kpVotes'], 'dune_id' => $dune, 'added' => $now));
}

// The tile of entry $e for a row and the own card (mel_oc_enter): what the entry has (its ratings too, kpVotes with
// them), the rest empty (the own card takes it from its meta).
/**
 * @param MelMlEntry $e
 * @return MelCatTile
 */
function mel_ml_tile($e)
{
    return array('id' => $e['imdb_id'] !== '' ? $e['imdb_id'] : 'tmdb:' . $e['_tmdbId'], 'imdb_id' => $e['imdb_id'],
        '_tmdbId' => $e['_tmdbId'], 'type' => $e['type'], 'name' => $e['name'], 'year' => $e['year'], 'imdbRating' => $e['imdbRating'],
        'description' => '', 'genres' => array(), 'poster' => $e['poster'], 'background' => $e['background'], 'country' => '',
        'runtime' => '', 'age' => '', 'ageUs' => '', 'cast' => array(), 'englishTitle' => $e['englishTitle'], 'kpRating' => $e['kpRating'],
        'kpVotes' => $e['kpVotes'],
        'kpId' => $e['kpId'], 'logo' => '', 'logoLang' => '', 'originalLanguage' => '');
}

// Entries as stored -> the good ones (mel_ml_clean) in order, a key once (the first); $dropped: how many were not taken.
/**
 * @param array<mixed> $items
 * @param int $dropped
 * @return list<MelMlEntry>
 */
function mel_ml_items($items, &$dropped)
{
    $list = array();
    $seen = array();
    $dropped = 0;
    foreach ($items as $x)
    {
        $e = mel_ml_clean($x);
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
 * @param list<MelMlEntry> $list
 * @return string
 */
function mel_ml_json($list)
{
    $j = json_encode(array('v' => MEL_ML_V, 'items' => $list));
    return is_string($j) ? $j : '';
}

// The list of the file $path, null when it is not taken; $why ('' - read, or no file: an empty list): 'unreadable',
// 'v <n> newer' (of a later Melange) or, the file broken, 'too big', 'not JSON', 'not a list' (mel_ml_broken);
// $dropped: entries not taken (mel_ml_items).
/**
 * @param string $path
 * @param string $why
 * @param int $dropped
 * @return list<MelMlEntry>|null
 */
function mel_ml_read_file($path, &$why, &$dropped)
{
    $why = '';
    $dropped = 0;
    clearstatcache();
    if (!file_exists($path) && !is_link($path))
        return array();
    $size = is_file($path) ? filesize($path) : false;
    if ($size !== false && $size > MEL_ML_FILE_MAX)
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
    if (is_int($v) && $v > MEL_ML_V)
    {
        $why = "v $v newer";
        return null;
    }
    if ($v !== MEL_ML_V || !isset($j['items']) || !is_array($j['items']) || $j['items'] !== array_values($j['items']))
    {
        $why = 'not a list';
        return null;
    }
    return mel_ml_items($j['items'], $dropped);
}

// $why of mel_ml_read_file is of a broken file (moved aside, a new one may be written).
/**
 * @param string $why
 * @return bool
 */
function mel_ml_broken($why)
{
    return in_array($why, array('too big', 'not JSON', 'not a list'), true);
}

// --- The copy of the backup.

// List $list in the short form of the backup (the field MEL_ML_COPY_FIELD of the file of the export): {v, dirs: the
// parts of the addresses of the pictures up to their last "/", each once, items: a title each, [type, name,
// englishTitle, year, poster, background, imdb_id, _tmdbId, kpId, dune_id, added] and, of a title with a rating,
// [imdbRating, kpRating, kpVotes] after them (a Melange of before them reads the 11 alone), a picture as [<its part in
// dirs>, <the rest>] or ''}. No key (mel_ml_key makes it again) and no names of fields: 200 titles take ~80 KB of the
// body of the import, not ~180 KB of the form of the file. A picture with auth= (of @Movies of the Favorites of the
// Dune, mel_ml_fav_read: the outside IP of this Dune) is '': the file of the export leaves the Dune. A path of a picture
// of the Favorites stays (no address in it; loaded back on this Dune after Melange is installed again, it shows).
/**
 * @param list<MelMlEntry> $list
 * @return array{v: int, dirs: list<string>, items: list<array<int, mixed>>}
 */
function mel_ml_copy($list)
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
            if ($u === '' || $p === false || preg_match('~[?&]auth=~', $u))
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
    return array('v' => MEL_ML_COPY_V, 'dirs' => array_map('strval', array_keys($dirs)), 'items' => $items);
}

// The copy $c of a file of the export (mel_ml_copy, as json_decode gives it) -> its list (mel_ml_items: what is not
// good dropped, counted in $dropped), null for none: $why 'v <n> newer' (of a later Melange), 'not a list'.
/**
 * @param mixed $c
 * @param string $why
 * @param int $dropped
 * @return list<MelMlEntry>|null
 */
function mel_ml_from_copy($c, &$why, &$dropped)
{
    $why = '';
    $dropped = 0;
    $v = is_array($c) && isset($c['v']) ? $c['v'] : null;
    if (is_int($v) && $v > MEL_ML_COPY_V)
    {
        $why = "v $v newer";
        return null;
    }
    if ($v !== MEL_ML_COPY_V || !isset($c['dirs'], $c['items']) || !is_array($c['dirs']) || !is_array($c['items']))
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
    return mel_ml_items($list, $dropped);
}

// List $list as it goes into the text of the file of the export, after its other fields: ',"my_list":<the copy>'
// (mel_ml_copy); '' if json_encode failed.
/**
 * @param list<MelMlEntry> $list
 * @return string
 */
function mel_ml_copy_part($list)
{
    $c = json_encode(mel_ml_copy($list));
    return is_string($c) ? ',"' . MEL_ML_COPY_FIELD . '":' . $c : '';
}

// What list $list adds to the body of an import (mel_ml_copy_part, URL-encoded as the page posts it: rawurlencode is
// never shorter than encodeURIComponent).
/**
 * @param list<MelMlEntry> $list
 * @return int
 */
function mel_ml_copy_size($list)
{
    return strlen(rawurlencode(mel_ml_copy_part($list)));
}

// --- The list in php_server.

/**
 * @return string
 */
function mel_ml_path()
{
    return mel_data_dir() !== '' ? mel_data_dir() . '/' . MEL_ML_FILE : '';
}

// The list in order, the newest first. Read when the file is not that of the memory (mel_file_sig: a rename over it has
// a new inode). A broken file: renamed to my_list.json.bad (one before it replaced), the list empty, a new file written
// on a change; not renamed, a newer version or one not readable: the list empty, the file not written over
// (MelMl::$lock). One line of the log each time.
/**
 * @return list<MelMlEntry>
 */
function mel_ml_list()
{
    $sig = mel_file_sig(MEL_ML_FILE);
    if (MelMl::$list !== null && $sig === MelMl::$sig)
        return MelMl::$list;
    MelMl::$sig = $sig;
    MelMl::$lock = '';
    $path = mel_ml_path();
    $why = '';
    $dropped = 0;
    $list = $path !== '' ? mel_ml_read_file($path, $why, $dropped) : array();
    if ($list === null)
    {
        $list = array();
        $moved = mel_ml_broken($why) && @rename($path, "$path.bad");
        if ($moved)
            MelMl::$sig = mel_file_sig(MEL_ML_FILE);
        else
            MelMl::$lock = $why;
        mel_log('my list: ' . MEL_ML_FILE . " $why, " . ($moved ? 'moved to ' . MEL_ML_FILE . '.bad' : 'not written over') .
            ', the list empty');
    }
    elseif ($dropped)
        mel_log("my list: $dropped entries of " . MEL_ML_FILE . ' not good, dropped');
    MelMl::$list = $list;
    return $list;
}

// List $list written as the file, then in memory. -> '' or why not: 'locked' (MelMl::$lock), 'write'.
/**
 * @param list<MelMlEntry> $list
 * @return string
 */
function mel_ml_save($list)
{
    if (MelMl::$lock !== '')
    {
        mel_log('my list: not saved, ' . MEL_ML_FILE . ' ' . MelMl::$lock);
        return 'locked';
    }
    $path = mel_ml_path();
    $data = mel_ml_json($list);
    if ($path === '' || $data === '' || !mel_write_file($path, $data))
    {
        mel_log('my list: not saved, write failed');
        return 'write';
    }
    MelMl::$list = $list;
    MelMl::$sig = mel_file_sig(MEL_ML_FILE);
    return '';
}

/**
 * @param string $key
 * @return bool
 */
function mel_ml_has($key)
{
    foreach (mel_ml_list() as $e)
    {
        if ($e['key'] === $key)
            return true;
    }
    return false;
}

// Tile $t at the start of the list, $dune: the id of its card of the Dune ('' - none or not known). One in the list
// already (mel_ml_in: of its IMDb or TMDB, or of the card $dune) stays in its place; its dune_id not known, it is set to
// $dune. -> '' (added, or there), 'no id' (no IMDb or TMDB), 'no name', 'full' (the backup would not load back with it, or
// with the dune_id: MEL_ML_COPY_MAX), 'locked', 'write' (mel_ml_save).
/**
 * @param MelCatTile $t
 * @param string $dune
 * @return string
 */
function mel_ml_add($t, $dune = '')
{
    $list = mel_ml_list();
    $e = mel_ml_entry($t, $dune, time());
    if (!$e)
    {
        $why = mel_ml_key($t) === '' ? 'no id' : 'no name';
        mel_log("my list: {$t['type']}:" . ($t['imdb_id'] !== '' ? $t['imdb_id'] : $t['_tmdbId']) . " not added, $why");
        return $why;
    }
    $key = mel_ml_in($e, $e['dune_id']);
    foreach ($key !== '' ? $list : array() as $i => $x)
    {
        if ($x['key'] !== $key)
            continue;
        if ($x['dune_id'] !== '' || $e['dune_id'] === '')
            return '';
        $x['dune_id'] = $e['dune_id'];
        $list[$i] = $x;
        if (mel_ml_copy_size($list) > MEL_ML_COPY_MAX)
        {
            mel_log("my list: the card of the Dune of $key not kept, the backup would be over " . MEL_ML_COPY_MAX . ' bytes');
            return 'full';
        }
        return mel_ml_save($list);
    }
    array_unshift($list, $e);
    if (mel_ml_copy_size($list) > MEL_ML_COPY_MAX)
    {
        mel_log("my list: {$e['key']} not added, the backup would be over " . MEL_ML_COPY_MAX . ' bytes');
        return 'full';
    }
    $why = mel_ml_save($list);
    if ($why === '')
        mel_log("my list: {$e['key']} added, " . count($list) . ' titles');
    return $why;
}

// Tiles $add (array(tile, its card of the Dune) each) at the end of the list in their order, at one write (the import of
// the Favorites of the Dune: the list as it was stays first). One in the list already (mel_ml_same) or of a card of the
// Dune in it, a repeat among them, one without IMDb or TMDB: not added. The backup would not load back with the next one
// (MEL_ML_COPY_MAX): those before it added, why 'full'. -> array(why: '', 'full', 'locked', 'write' - none added; how many
// were added).
/**
 * @param list<array{MelCatTile, string}> $add
 * @return array{string, int}
 */
function mel_ml_append($add)
{
    $list = mel_ml_list();
    $n = 0;
    $why = '';
    $now = time();
    foreach ($add as $a)
    {
        $e = mel_ml_entry($a[0], $a[1], $now);
        if (!$e)
            continue;
        foreach ($list as $x)
        {
            if (mel_ml_same($x, $e) || ($e['dune_id'] !== '' && $x['dune_id'] === $e['dune_id']))
                continue 2;
        }
        $list[] = $e;
        if (mel_ml_copy_size($list) > MEL_ML_COPY_MAX)
        {
            array_pop($list);
            mel_log("my list: {$e['key']} not added, the backup would be over " . MEL_ML_COPY_MAX . ' bytes');
            $why = 'full';
            break;
        }
        $n++;
    }
    if (!$n)
        return array($why, 0);
    $w = mel_ml_save($list);
    return $w !== '' ? array($w, 0) : array($why, $n);
}

// The title of key $key out of the list. -> '' (taken out, or not there), 'locked', 'write' (mel_ml_save).
/**
 * @param string $key
 * @return string
 */
function mel_ml_del($key)
{
    $list = mel_ml_list();
    foreach ($list as $i => $e)
    {
        if ($e['key'] !== $key)
            continue;
        array_splice($list, $i, 1);
        $why = mel_ml_save($list);
        if ($why === '')
            mel_log("my list: $key removed, " . count($list) . ' titles');
        return $why;
    }
    return '';
}

// The entry with the card of the Dune $dune, null for none.
/**
 * @param string $dune
 * @return MelMlEntry|null
 */
function mel_ml_find_dune($dune)
{
    foreach ($dune !== '' ? mel_ml_list() : array() as $e)
    {
        if ($e['dune_id'] === $dune)
            return $e;
    }
    return null;
}

// The key of the entry of title $t in the list (mel_ml_same), else of the one of its card of the Dune $dune ('' not
// known); '' for none.
/**
 * @param array{type: string, imdb_id: string, _tmdbId: string} $t
 * @param string $dune
 * @return string
 */
function mel_ml_in($t, $dune = '')
{
    foreach (mel_ml_list() as $e)
    {
        if (mel_ml_same($e, $t))
            return $e['key'];
    }
    $e = mel_ml_find_dune($dune);
    return $e ? $e['key'] : '';
}

// --- The Favorites of the Dune, read only.

// "key = value" lines of file $path (HD::parse_props of the shell: split at the first "=", not at the start of a
// line, both trimmed); null for no file or a bigger one.
/**
 * @param string $path
 * @return array<string, string>|null
 */
function mel_ml_props($path)
{
    $text = is_file($path) && filesize($path) <= MEL_ML_FAV_FILE_MAX ? @file_get_contents($path) : false;
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
function mel_ml_lcfg_order($path)
{
    $text = is_file($path) && filesize($path) <= MEL_ML_FAV_FILE_MAX ? @file_get_contents($path) : false;
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
function mel_ml_launcher($url)
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
 * @return list<MelMlFav>
 */
function mel_ml_fav_movies($dir, $lcfg)
{
    $names = is_dir($dir) ? @scandir($dir) : false;
    $found = array();
    $rest = array();
    foreach (is_array($names) ? $names : array() as $f)
    {
        if (!preg_match('/^(' . MEL_ML_DUNE_ID . ')\.json$/D', $f, $m) || count($found) >= MEL_ML_FAV_MAX)
            continue;
        $id = $m[1];
        $path = "$dir/$f";
        $j = is_file($path) && filesize($path) <= MEL_ML_FAV_FILE_MAX ? json_decode(strval(@file_get_contents($path)), true) : null;
        if (!is_array($j) || !isset($j['id']) || $j['id'] !== $id)
            continue;
        $year = isset($j['year']) && (is_int($j['year']) || is_string($j['year'])) ? strval($j['year']) : '';
        $found[$id] = array('dune_id' => $id, 'type' => !empty($j['series_type']) ? 'series' : 'movie',
            'name' => mel_ml_text(isset($j['title']) ? $j['title'] : '', MEL_ML_NAME_MAX),
            'year' => preg_match('/^[0-9]{4}/', $year, $y) ? $y[0] : '', 'poster' => mel_ml_url(isset($j['poster_url']) ? $j['poster_url'] : ''),
            'file' => is_file("$dir/$id.jpg") ? "$dir/$id.jpg" : '');
        $rest[$id] = sprintf('%012d', intval(filemtime($path))) . " $id";
    }
    $out = array();
    foreach (mel_ml_lcfg_order("$lcfg/lcfg_fav_movies.txt") as $id)
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
// r25 in its place: its movies (mel_ml_fav_movies). Persons, folders of the network, apps, other plugins: skipped; a
// movie twice: the first. -> {dune_id, type (movie, series; '' - not known: an item of before {series}), name, year
// ('' for an item), poster (an address; of @Movies with auth= of dunemdb - the outside IP of this Dune: kept in
// MEL_ML_FILE as it is, the data of this Dune; masked in the log, mel_mask; not in the copy of the backup,
// mel_ml_copy), file (the picture on the Dune; '' none)}. No request, nothing written.
/**
 * @param list<string> $dirs
 * @param string $lcfg
 * @return list<MelMlFav>
 */
function mel_ml_fav_read($dirs = array(MEL_ML_FAV_DIR, MEL_ML_FAV_DIR2), $lcfg = MEL_ML_FAV_LCFG)
{
    $items = array();
    foreach ($dirs as $dir)
    {
        $names = is_dir($dir) ? @scandir($dir) : false;
        foreach (is_array($names) ? $names : array() as $name)
        {
            $props = $name !== '.' && $name !== '..' && count($items) < MEL_ML_FAV_MAX ? mel_ml_props("$dir/$name/dune_folder.txt") : null;
            if ($props !== null)
                $items[$name] = array('path' => "$dir/$name", 'props' => $props);
        }
    }
    $rank = array_flip(mel_ml_lcfg_order("$lcfg/lcfg_favorites.txt"));
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
        $l = mel_ml_launcher(isset($p['media_url']) ? $p['media_url'] : '');
        if ($it['name'] === '@Movies')
            $got = mel_ml_fav_movies($it['path'], $lcfg);
        elseif (isset($l['name'], $l['action_id'], $l['movie_id']) && $l['name'] === 'shell_ext' && $l['action_id'] === 'file_movie' &&
            preg_match('/^' . MEL_ML_DUNE_ID . '$/D', $l['movie_id']))
        {
            $icon = isset($p['icon_path']) ? $p['icon_path'] : '';
            $file = $icon === '' || strpos($icon, '://') !== false ? '' : ($icon[0] === '/' ? $icon : "{$it['path']}/$icon");
            $series = isset($l['series']) ? $l['series'] : '';
            $name = mel_ml_text(isset($p['caption']) && $p['caption'] !== '' ? $p['caption'] : $it['name'], MEL_ML_NAME_MAX);
            $got = array(array('dune_id' => $l['movie_id'], 'type' => $series === '1' ? 'series' : ($series === '0' ? 'movie' : ''),
                'name' => $name, 'year' => '', 'poster' => mel_ml_url($icon), 'file' => $file !== '' && is_file($file) ? $file : ''));
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
