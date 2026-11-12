<?php
// Dune's watch history (shell_ext recent/watch_history.php), read only: the
// episode of a series opened from its main card, the progress of the
// releases of a list. Functions are global; the require order is in main.php.

// Dune's watch history, as shell_ext reads it: PHP adds FS_PREFIX to /config
// itself. Tests may define it first.
if (!defined('AIO_WH_PATH'))
    define('AIO_WH_PATH', '/config/watch_history');
// A bigger history file is skipped: json_decode needs ~5.5x its size in memory.
define('AIO_WH_MAX_BYTES', 4000000);

// Our records of the history (aio_wh_ours_all) in the memory of php_server,
// until the history files change.
class AioWh
{
    /** @var string|null */
    public static $sig = null;
    /** @var array<string, float> */
    public static $all = array();
}

// A record of the card $dune_id that WatchHistory::do_update keeps: not deleted,
// wh_item_is_suitable of the shell.
/**
 * @param mixed $w
 * @param string $dune_id
 * @return bool
 * @phpstan-assert-if-true stdClass $w
 */
function aio_wh_of($w, $dune_id)
{
    if (!is_object($w) || (isset($w->deleted) && $w->deleted == 1))
        return false;
    if (!isset($w->action_plugin) || !isset($w->action_params))
    {
        $url = isset($w->url) && is_string($w->url) ? $w->url : '';
        $p = strpos($url, '://');
        $scheme = $p ? substr($url, 0, $p) : '';
        if ($scheme === '' || $scheme === 'tmpfs' ||
            (isset($w->show_in_recent) ? !$w->show_in_recent : $scheme === 'file'))
            return false;
    }
    return isset($w->movie_id) && is_string($w->movie_id) && $w->movie_id === $dune_id;
}

// An episode record of the series that WatchHistory::do_update keeps.
/**
 * @param mixed $w
 * @param string $dune_id
 * @return bool
 * @phpstan-assert-if-true stdClass $w
 */
function aio_wh_match($w, $dune_id)
{
    return aio_wh_of($w, $dune_id) && isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1;
}

// Records of the NNNNNN.json files, the latest first (files from the last
// one, records from the end), to $visit($w, $arg, &$acc) until it returns
// true. Only what it keeps in $acc stays in memory. One log line
// "<$tag>: N files, ..., count($acc) found". -> $acc
/**
 * @param string $tag
 * @param callable $visit
 * @param mixed $arg
 * @return array<mixed>
 */
function aio_wh_find($tag, $visit, $arg)
{
    $t = microtime(true);
    $dir = AIO_WH_PATH;
    if (!is_dir($dir) || !is_readable($dir))
    {
        aio_log("$tag: " . (is_dir($dir) ? 'folder not readable' : 'no folder'));
        return array();
    }
    $files = array();
    foreach (scandir($dir) as $f)
    {
        if (preg_match('/^[0-9]*\\.json$/', $f))
            $files[] = $f;
    }
    usort($files, 'strnatcmp');
    $read = 0;
    $records = 0;
    $found = array();
    $stop = false;
    for ($i = count($files) - 1; $i >= 0 && !$stop; $i--)
    {
        $path = "$dir/{$files[$i]}";
        $size = is_readable($path) ? filesize($path) : false;
        if ($size > AIO_WH_MAX_BYTES)
        {
            aio_log("$tag: {$files[$i]} is $size bytes, over " . AIO_WH_MAX_BYTES . ', skipped');
            continue;
        }
        $items = $size !== false ? json_decode(strval(file_get_contents($path))) : null;
        if (!is_array($items))
            continue;
        $read++;
        $records += count($items);
        for ($j = count($items) - 1; $j >= 0 && !$stop; $j--)
            $stop = $visit($items[$j], $arg, $found);
    }
    aio_log(sprintf('%s: %d files, %d read, %d records, %d found, %.0f ms', $tag, count($files), $read, $records,
        count($found), (microtime(true) - $t) * 1000));
    return $found;
}

// The latest episode record of the series in any NNNNNN.json, from any
// supplier or a local file, or null.
/**
 * @param string $dune_id
 * @return stdClass|null
 */
function aio_wh_last($dune_id)
{
    $found = aio_wh_find('history', 'aio_wh_last_visit', $dune_id);
    return $found ? $found[0] : null;
}

/**
 * @param mixed $w
 * @param string $dune_id
 * @param list<stdClass> $acc
 * @return bool
 */
function aio_wh_last_visit($w, $dune_id, &$acc)
{
    if (aio_wh_match($w, $dune_id))
        $acc[] = $w;
    return (bool)$acc;
}

// A record of a release of ours: not deleted, uid "<$prefix><infoHash>".
/**
 * @param mixed $w
 * @param string $prefix
 * @return bool
 * @phpstan-assert-if-true stdClass $w
 * @phpstan-assert-if-true string $w->uid
 */
function aio_wh_ours($w, $prefix)
{
    return is_object($w) && !(isset($w->deleted) && $w->deleted == 1) && isset($w->uid) && is_string($w->uid) &&
        strlen($w->uid) > strlen($prefix) && strpos($w->uid, $prefix) === 0;
}

// The latest record of each release of ours -> its share in $acc (uid =>
// share, only a float each: there may be thousands). $arg: array(prefix,
// aio_wh_marked_set()). Watched: the same rule as aio_wh_watched.
/**
 * @param mixed $w
 * @param array{string, array<int|string, true>} $arg
 * @param array<string, float> $acc
 * @return bool
 */
function aio_wh_ours_visit($w, $arg, &$acc)
{
    if (!aio_wh_ours($w, $arg[0]) || isset($acc[$w->uid]))
        return false;
    $pos = isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : -1;
    $len = isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0;
    $acc[$w->uid] = aio_wh_full($pos, $len) || isset($arg[1][$w->uid]) ? 1.0 :
        ($len > 0 && $pos > 0 ? min(1.0, $pos / $len) : 0.0);
    return false;
}

// is_fully_watched of the shell with its default settings: 90 %, 30 s left.
/**
 * @param float|int $pos
 * @param float|int $len
 * @return bool
 */
function aio_wh_full($pos, $len)
{
    return $len > 0 && $pos >= 0 && $pos >= $len - 30 && round($pos * 100.0 / $len) >= 90;
}

// Names, sizes and times of the history files (NNNNNN.json and
// watched_files.txt); null without the folder or while a file is younger than
// 2 s (written again within the same second it would keep its signature).
/**
 * @return string|null
 */
function aio_wh_sig()
{
    $dir = AIO_WH_PATH;
    if (!is_dir($dir) || !is_readable($dir))
        return null;
    clearstatcache();
    $now = time();
    $sig = '';
    foreach (scandir($dir) as $f)
    {
        if ($f !== 'watched_files.txt' && !preg_match('/^[0-9]*\\.json$/', $f))
            continue;
        $t = filemtime("$dir/$f");
        if ($t === false || $t >= $now - 1)
            return null;
        $sig .= "$f:" . filesize("$dir/$f") . ":$t\n";
    }
    return $sig;
}

// The progress of the movie of card $dune_id: its latest record of any supplier or a local
// file (no episode), as the native card shows it -> array(pos, len) in s, len > 0; null for none.
/**
 * @param string $dune_id
 * @return array{float, float}|null
 */
function aio_wh_movie($dune_id)
{
    $found = aio_wh_find('history: movie', 'aio_wh_movie_visit', $dune_id);
    if (!$found)
        return null;
    $w = $found[0];
    $pos = isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : 0.0;
    $len = isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0.0;
    return $len > 0 ? array($pos, $len) : null;
}

/**
 * @param mixed $w
 * @param string $dune_id
 * @param list<stdClass> $acc
 * @return bool
 */
function aio_wh_movie_visit($w, $dune_id, &$acc)
{
    if (aio_wh_of($w, $dune_id) && !(isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1))
        $acc[] = $w;
    return (bool)$acc;
}

// Our records (uid "aio:..."): uid => share of its latest record, 0 without
// progress, 1 once watched (by position or marked). Read again only when the
// history files change.
/**
 * @return array<string, float>
 */
function aio_wh_ours_all()
{
    $t = microtime(true);
    $sig = aio_wh_sig();
    if ($sig !== null && $sig === AioWh::$sig)
    {
        aio_log(sprintf('progress: cache hit, %d releases, %.0f ms', count(AioWh::$all), (microtime(true) - $t) * 1000));
        return AioWh::$all;
    }
    $all = aio_wh_find('progress', 'aio_wh_ours_visit', array(AIO_SUP_ID . ':', aio_wh_marked_set()));
    AioWh::$sig = $sig;
    AioWh::$all = $all;
    return $all;
}

// Progress of the releases of a list watched before (the movie or the
// episode of $st): infoHash => share in (0, 1]. No dune id: none, the history
// is not read.
/**
 * @param AioState $st
 * @return AioProgress
 */
function aio_wh_progress($st)
{
    $mv = $st['movie'];
    if ($mv['dune_id'] === '')
        return array();
    $prefix = implode(':', array(AIO_SUP_ID, $mv['dune_id'], $st['s'], $st['e'])) . ':';
    $n = strlen($prefix);
    $res = array();
    foreach (aio_wh_ours_all() as $uid => $share)
    {
        if ($share > 0 && strlen($uid) > $n && strncmp($uid, $prefix, $n) === 0)
            $res[substr($uid, $n)] = $share;
    }
    return $res;
}

// uids marked as watched in watched_files.txt, as load_idset_from_wh_file:
// uid => true.
/**
 * @return array<int|string, true>
 */
function aio_wh_marked_set()
{
    $path = AIO_WH_PATH . '/watched_files.txt';
    $set = array();
    if (!is_file($path) || !is_readable($path))
        return $set;
    foreach (explode("\n", strval(file_get_contents($path))) as $id)
    {
        if ($id === '' || $id[0] === '-')
            continue;
        $p = strpos($id, "\t");
        if ($p)
            $id = strval(substr($id, $p + 1));
        $set[$id] = true;
    }
    return $set;
}

// uid in watched_files.txt (marked as watched).
/**
 * @param string $uid
 * @return bool
 */
function aio_wh_marked($uid)
{
    $set = $uid !== '' ? aio_wh_marked_set() : array();
    return isset($set[$uid]);
}

// How a record of S/E was watched: 'by position' (aio_wh_full), 'marked' (uid
// in watched_files.txt), '' not watched.
/**
 * @param float|int $pos
 * @param float|int $len
 * @param string $uid
 * @return string
 */
function aio_wh_watched($pos, $len, $uid)
{
    return aio_wh_full($pos, $len) ? 'by position' : (aio_wh_marked($uid) ? 'marked' : '');
}

// The episode after the watched S$s E$e by season_numbers, as 0.9.0: the next
// one of the season, else E1 of the nearest later season; the same one with
// no data on season $s, S1E1 after the last one. -> array(s, e, next: a later
// episode, why). Unlike aio_flip_ep (+1) on purpose: past episodes_count, the next season.
/**
 * @param array<int, int> $seasons
 * @param int $s
 * @param int $e
 * @return array{int, int, bool, string}
 */
function aio_wh_next($seasons, $s, $e)
{
    if (!isset($seasons[$s]))
        return array($s, $e, false, "watched, but season $s is not in season_numbers");
    if ($e < $seasons[$s])
        return array($s, $e + 1, true, "next after the watched S{$s}E{$e}");
    foreach (array_keys($seasons) as $n)
    {
        if ($n > $s)
            return array($n, 1, true, "next season after the watched last S{$s}E{$e}");
    }
    return array(1, 1, false, "the last episode S{$s}E{$e} is watched");
}

// Episode of a series opened from its main card (season = episode = -1):
// the last one in the history, the next one once it is watched, S1E1 with
// no record or after the last episode; the release of the record if it is
// ours (uid "aio:<dune id>:<s>:<e>:<infoHash>"), else ''. -> array(s, e, hash)
/**
 * @param AioMovie $mv
 * @return array{int, int, string}
 */
function aio_wh_episode($mv)
{
    $w = $mv['dune_id'] !== '' ? aio_wh_last($mv['dune_id']) : null;
    if (!$w)
    {
        aio_log('episode: S1E1, no history record');
        return array(1, 1, '');
    }
    $e = intval($w->e);
    $s = isset($w->s) && is_numeric($w->s) && intval($w->s) >= 0 ? intval($w->s) : 1;
    $pos = isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : -1;
    $len = isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0;
    $uid = isset($w->uid) && is_string($w->uid) ? $w->uid : (isset($w->url) && is_string($w->url) ? $w->url : '');
    $hash = preg_match('/^' . preg_quote(AIO_SUP_ID . ':' . $mv['dune_id'], '/') . ':-?[0-9]+:-?[0-9]+:([0-9a-f]{40})$/D',
        $uid, $m) ? $m[1] : '';
    $watched = aio_wh_watched($pos, $len, $uid);
    aio_log(sprintf('history: record S%dE%d pos=%d/%d watched=%s, %d seasons in season_numbers', $s, $e,
        $pos, $len, $watched !== '' ? $watched : 'no', count($mv['seasons'])));
    if ($watched === '')
    {
        aio_log("episode: S{$s}E{$e}, not watched");
        return array($s, $e, $hash);
    }
    list($ns, $ne, , $why) = aio_wh_next($mv['seasons'], $s, $e);
    aio_log("episode: S{$ns}E{$ne}, $why");
    return array($ns, $ne, $hash);
}

// "Continue watching" of a series ($r of aio_resume): the episode recorded,
// from its position; once it is watched the next one (aio_wh_next) from 0,
// 'next' set: its release must have it in its file. Watched: with wh_len by the
// position only (r25 counts a mark only if newer than the record,
// get_file_mark_time), without it by the mark only. One log line, no uid. -> array($r of the episode to play or
// null - watched, no later one: the list; s, e)
/**
 * @param AioMovie $mv
 * @param AioResume $r
 * @return array{AioResume|null, int, int}
 */
function aio_wh_resume_ep($mv, $r)
{
    $s = $r['s'];
    $e = $r['e'];
    $watched = $r['len'] > 0 ? (aio_wh_full($r['pos'], $r['len']) ? 'by position' : '') :
        (aio_wh_marked($r['uid']) ? 'marked' : '');
    $tag = "resume: record S{$s}E{$e} pos={$r['pos']}/{$r['len']} watched=" . ($watched !== '' ? $watched : 'no');
    if ($watched === '')
    {
        aio_log("$tag -> S{$s}E{$e}");
        return array($r, $s, $e);
    }
    list($ns, $ne, $next, $why) = aio_wh_next($mv['seasons'], $s, $e);
    aio_log("$tag -> S{$ns}E{$ne}, $why" . ($next ? ', from 0' : ', its list'));
    if (!$next)
        return array(null, $ns, $ne);
    $r['s'] = $ns;
    $r['e'] = $ne;
    $r['pos'] = 0;
    $r['next'] = true;
    return array($r, $ns, $ne);
}
