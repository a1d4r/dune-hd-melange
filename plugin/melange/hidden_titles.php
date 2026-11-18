<?php
// "Скрыть" (0.65.0): the titles the user hid from the rows of the catalog, one JSON file of data_dir, the newest first,
// only on this Dune; and the filter of the tiles of the rows by it and by the age of the settings (cat_age,
// mel_settings_parse). Hidden: none in the rows of the catalog, "Show all", the tab "TV", "Похожие" and "Части
// франшизы" of the own card, the works of a person; a search shows them (its rows have q). The age: there and in a
// search too. "Continue" and "Мой список" are not filtered (their tiles never go through mel_cat_take).
// The functions of the file and of an entry need my_list.php alone (mel_ml_key, mel_ml_same, mel_ml_text) and log
// nothing: the settings page has them too, with no main.php ("Скрытые", "Вернуть": mel_ht_read_file, mel_ht_without,
// mel_ht_json, then its own write of the file). Those of php_server need main.php (mel_data_dir, mel_file_sig,
// mel_write_file, mel_log, Mel::$settings) and catalog.php (mel_cat_age_of). POP UP, "Скрыть" and the listings in
// memory: view_catalog.php.
// PHP 5.3.6. Types in the PHPDoc: phpstan-types.neon of the repository.

require_once dirname(__FILE__) . '/my_list.php';

define('MEL_HT_FILE', 'hidden_titles.json');
// The version of the file: {"v": 1, "items": [<entry>...]}.
define('MEL_HT_V', 1);
// Titles kept at most: the oldest go first. ~150 bytes a title: 500 are ~75 KB.
define('MEL_HT_MAX', 500);
// A bigger file is broken.
define('MEL_HT_FILE_MAX', 262144);

class MelHt
{
    // The list in order (mel_ht_list), null: not read yet; the signature of the file it is of (mel_file_sig).
    /** @var list<MelHtEntry>|null */
    public static $list = null;
    /** @var string */
    public static $sig = '';
    // Why the file is not written over ('' - it may be): its version is newer, it is not readable.
    /** @var string */
    public static $lock = '';
    // The ids of the list: "<type>|<IMDb>" and "<type>|tmdb:<TMDB>" of each entry (mel_ht_ids).
    /** @var array<string, bool> */
    public static $ids = array();
}

// --- An entry and the file: no main.php.

// An entry as stored, or of a tile -> its good fields with its key (mel_ml_key), null for one without a key or a name.
// hidden: unix time, 0 for none or not good.
/**
 * @param mixed $e
 * @return MelHtEntry|null
 */
function mel_ht_clean($e)
{
    if (!is_array($e))
        return null;
    $s = array();
    foreach (array('type', 'imdb_id', '_tmdbId', 'year') as $k)
        $s[$k] = isset($e[$k]) && is_string($e[$k]) ? $e[$k] : '';
    $out = array('key' => '', 'type' => $s['type'] === 'movie' || $s['type'] === 'series' ? $s['type'] : '',
        'imdb_id' => preg_match('/^tt[0-9]{1,10}$/D', $s['imdb_id']) ? $s['imdb_id'] : '',
        '_tmdbId' => preg_match('/^[0-9]{1,10}$/D', $s['_tmdbId']) ? $s['_tmdbId'] : '',
        'name' => mel_ml_text(isset($e['name']) ? $e['name'] : '', MEL_ML_NAME_MAX),
        'year' => preg_match('/^[0-9]{4}$/D', $s['year']) ? $s['year'] : '',
        'hidden' => isset($e['hidden']) && is_int($e['hidden']) && $e['hidden'] > 0 ? $e['hidden'] : 0);
    $out['key'] = mel_ml_key($out);
    return $out['key'] !== '' && $out['name'] !== '' ? $out : null;
}

// The entry of tile $t (mel_cat_tile) hidden at $now; null for a tile without IMDb or TMDB (mal:...).
/**
 * @param MelCatTile $t
 * @param int $now
 * @return MelHtEntry|null
 */
function mel_ht_entry($t, $now)
{
    return mel_ht_clean(array('type' => $t['type'], 'imdb_id' => $t['imdb_id'], '_tmdbId' => $t['_tmdbId'],
        'name' => $t['name'], 'year' => $t['year'], 'hidden' => $now));
}

// Entries as stored -> the good ones (mel_ht_clean) in order, a key once (the first), MEL_HT_MAX at most.
/**
 * @param array<mixed> $items
 * @return list<MelHtEntry>
 */
function mel_ht_items($items)
{
    $list = array();
    $seen = array();
    foreach ($items as $x)
    {
        $e = mel_ht_clean($x);
        if (!$e || isset($seen[$e['key']]) || count($list) >= MEL_HT_MAX)
            continue;
        $seen[$e['key']] = true;
        $list[] = $e;
    }
    return $list;
}

// The list of the file $path, null when it is not taken: $why 'unreadable' or 'v <n> newer' (of a later Melange). No
// file, a file too big, not JSON or not a list: an empty list ($why 'too big', 'not JSON', 'not a list'; a write puts a
// good one over it).
/**
 * @param string $path
 * @param string $why
 * @return list<MelHtEntry>|null
 */
function mel_ht_read_file($path, &$why)
{
    $why = '';
    clearstatcache();
    if (!file_exists($path) && !is_link($path))
        return array();
    $size = is_file($path) ? filesize($path) : false;
    if ($size !== false && $size > MEL_HT_FILE_MAX)
    {
        $why = 'too big';
        return array();
    }
    $text = $size !== false && is_readable($path) ? @file_get_contents($path) : false;
    if (!is_string($text))
    {
        $why = 'unreadable';
        return null;
    }
    $j = json_decode($text, true);
    $v = is_array($j) && isset($j['v']) ? $j['v'] : null;
    if (is_int($v) && $v > MEL_HT_V)
    {
        $why = "v $v newer";
        return null;
    }
    if (!is_array($j))
        $why = 'not JSON';
    elseif ($v !== MEL_HT_V || !isset($j['items']) || !is_array($j['items']) || $j['items'] !== array_values($j['items']))
        $why = 'not a list';
    return $why === '' ? mel_ht_items($j['items']) : array();
}

// The text of the file of list $list; '' if json_encode failed.
/**
 * @param list<MelHtEntry> $list
 * @return string
 */
function mel_ht_json($list)
{
    $j = json_encode(array('v' => MEL_HT_V, 'items' => $list));
    return is_string($j) ? $j : '';
}

// List $list with entry $e first, the entries of the same title (mel_ml_same: of its type, its IMDb or its TMDB) out,
// MEL_HT_MAX at most: the oldest (the last) go.
/**
 * @param list<MelHtEntry> $list
 * @param MelHtEntry $e
 * @return list<MelHtEntry>
 */
function mel_ht_with($list, $e)
{
    $out = array($e);
    foreach ($list as $x)
    {
        if (!mel_ml_same($x, $e))
            $out[] = $x;
    }
    return array_slice($out, 0, MEL_HT_MAX);
}

// List $list without the entry of key $key ("Вернуть" of the settings page; the same list for no such key).
/**
 * @param list<MelHtEntry> $list
 * @param string $key
 * @return list<MelHtEntry>
 */
function mel_ht_without($list, $key)
{
    $out = array();
    foreach ($list as $x)
    {
        if ($x['key'] !== $key)
            $out[] = $x;
    }
    return $out;
}

// The ids of list $list as mel_ht_off looks them up: "<type>|<IMDb>", "<type>|tmdb:<TMDB>".
/**
 * @param list<MelHtEntry> $list
 * @return array<string, bool>
 */
function mel_ht_ids($list)
{
    $ids = array();
    foreach ($list as $e)
    {
        if ($e['imdb_id'] !== '')
            $ids[$e['type'] . '|' . $e['imdb_id']] = true;
        if ($e['_tmdbId'] !== '')
            $ids[$e['type'] . '|tmdb:' . $e['_tmdbId']] = true;
    }
    return $ids;
}

// --- The list and the filter in php_server.

/**
 * @return string
 */
function mel_ht_path()
{
    return mel_data_dir() !== '' ? mel_data_dir() . '/' . MEL_HT_FILE : '';
}

// The list in memory as that of the file of signature $sig.
/**
 * @param list<MelHtEntry> $list
 * @param string $sig
 * @return void
 */
function mel_ht_keep_list($list, $sig)
{
    MelHt::$list = $list;
    MelHt::$sig = $sig;
    MelHt::$ids = mel_ht_ids($list);
}

// The list in order, the newest first. Read when the file is not that of the memory (mel_file_sig: the settings page
// renames a new one over it). A newer version or one not readable: none hidden, the file not written over
// (MelHt::$lock); one line of the log.
/**
 * @return list<MelHtEntry>
 */
function mel_ht_list()
{
    $sig = mel_file_sig(MEL_HT_FILE);
    if (MelHt::$list !== null && $sig === MelHt::$sig)
        return MelHt::$list;
    $why = '';
    $path = mel_ht_path();
    $list = $path !== '' ? mel_ht_read_file($path, $why) : array();
    MelHt::$lock = $list === null ? $why : '';
    if ($why !== '')
        mel_log('hidden: ' . MEL_HT_FILE . " $why" . ($list === null ? ', not written over, none hidden' : ', none hidden'));
    mel_ht_keep_list($list === null ? array() : $list, $sig);
    return MelHt::$list;
}

// "Скрыть" of tile $t: first in the list (an entry of the same title before replaced), written whole (mel_write_file: a
// temporary file and rename). -> '' or why not: 'no id' (no IMDb or TMDB), 'locked' (MelHt::$lock), 'write'.
/**
 * @param MelCatTile $t
 * @return string
 */
function mel_ht_hide($t)
{
    $e = mel_ht_entry($t, time());
    if (!$e)
        return 'no id';
    $list = mel_ht_with(mel_ht_list(), $e);
    if (MelHt::$lock !== '')
    {
        mel_log('hidden: not saved, ' . MEL_HT_FILE . ' ' . MelHt::$lock);
        return 'locked';
    }
    $path = mel_ht_path();
    $data = mel_ht_json($list);
    if ($path === '' || $data === '' || !mel_write_file($path, $data))
    {
        mel_log('hidden: not saved, write failed');
        return 'write';
    }
    mel_ht_keep_list($list, mel_file_sig(MEL_HT_FILE));
    mel_log("hidden: {$e['key']}, " . count($list) . ' hidden');
    return '';
}

// The filter now (the list read when the file changed, once for all the tiles of a page): ids - of the list
// (mel_ht_ids), age - the oldest age shown (cat_age of the settings: 0, 6, 12, 16), null: all.
/**
 * @return array{ids: array<string, bool>, age: int|null}
 */
function mel_ht_now()
{
    mel_ht_list();
    return array('ids' => MelHt::$ids, 'age' => Mel::$settings['cat_age']);
}

// Tile $t is not shown by filter $f (mel_ht_now): hidden (of its type, its IMDb or its TMDB; not in a search: $search),
// or its age (mel_cat_age_of) over the age of the filter; a tile of no age is shown.
/**
 * @param array{ids: array<string, bool>, age: int|null} $f
 * @param MelCatTile $t
 * @param bool $search
 * @return bool
 */
function mel_ht_off($f, $t, $search)
{
    if (!$search && (($t['imdb_id'] !== '' && isset($f['ids'][$t['type'] . '|' . $t['imdb_id']])) ||
        ($t['_tmdbId'] !== '' && isset($f['ids'][$t['type'] . '|tmdb:' . $t['_tmdbId']]))))
        return true;
    return $f['age'] !== null && mel_cat_age_of($t) > $f['age'];
}

// Tile $t is hidden (mel_ht_off of the list alone).
/**
 * @param MelCatTile $t
 * @return bool
 */
function mel_ht_has($t)
{
    mel_ht_list();
    return mel_ht_off(array('ids' => MelHt::$ids, 'age' => null), $t, false);
}

// Tiles $tiles the filter shows (mel_ht_off, not of a search), in their order: the rows of the own card.
/**
 * @param list<MelCatTile> $tiles
 * @return list<MelCatTile>
 */
function mel_ht_keep($tiles)
{
    $f = mel_ht_now();
    $out = array();
    foreach ($tiles as $t)
    {
        if (!mel_ht_off($f, $t, false))
            $out[] = $t;
    }
    return $out;
}

// What the filter is made of: the age of the settings and the file (mel_file_sig) - another one, the listings in memory
// begin anew (mel_catv_filter_seen).
/**
 * @return string
 */
function mel_ht_filter_sig()
{
    return json_encode(Mel::$settings['cat_age']) . ' ' . mel_file_sig(MEL_HT_FILE);
}
