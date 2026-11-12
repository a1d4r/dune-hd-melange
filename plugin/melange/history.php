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
    return aio_wh_after(aio_wh_lists($seasons), $s, $e);
}

// season_numbers (season => episodes) -> season => its episodes 1..N.
/**
 * @param array<int, int> $seasons
 * @return array<int, list<int>>
 */
function aio_wh_lists($seasons)
{
    $out = array();
    foreach ($seasons as $s => $n)
        $out[$s] = range(1, $n);
    return $out;
}

// The episode after the watched S$s E$e by $eps (season => its episodes, ascending): the next
// one of the season, else the first of the nearest later season; the same one with no data on
// season $s, S1E1 after the last one. -> as aio_wh_next.
/**
 * @param array<int, list<int>> $eps
 * @param int $s
 * @param int $e
 * @return array{int, int, bool, string}
 */
function aio_wh_after($eps, $s, $e)
{
    if (!isset($eps[$s]))
        return array($s, $e, false, "watched, but season $s is not in season_numbers");
    foreach ($eps[$s] as $n)
    {
        if ($n > $e)
            return array($s, $n, true, "next after the watched S{$s}E{$e}");
    }
    foreach ($eps as $n => $list)
    {
        if ($n > $s && $list)
            return array($n, $list[0], true, "next season after the watched last S{$s}E{$e}");
    }
    return array(1, 1, false, "the last episode S{$s}E{$e} is watched");
}

// Episode record $w -> array(s (1 for none or a negative one), e (1 for none), pos (-1 for none),
// len (0 for none), uid (else url, else '')).
/**
 * @param stdClass $w
 * @return array{int, int, float, float, string}
 */
function aio_wh_rec($w)
{
    return array(isset($w->s) && is_numeric($w->s) && intval($w->s) >= 0 ? intval($w->s) : 1,
        isset($w->e) && is_numeric($w->e) ? intval($w->e) : 1,
        isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : -1.0,
        isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0.0,
        isset($w->uid) && is_string($w->uid) ? $w->uid : (isset($w->url) && is_string($w->url) ? $w->url : ''));
}

// The episode to offer for the latest episode record $w of the series of card $dune_id
// (aio_wh_last; null: none), no log: the recorded one while not watched, the next one by $eps
// (aio_wh_after) once it is, S1E1 with no record or after the last episode. hash: the release
// of the record if it is ours (uid "aio:<dune id>:<s>:<e>:<infoHash>"), else ''; kind: none (no
// record), record (not watched), next, same (watched, no data on its season), first (the last
// one watched); rec: the record for the log, why: the choice for the log.
/**
 * @param stdClass|null $w
 * @param string $dune_id
 * @param array<int, list<int>> $eps
 * @return AioWhPick
 */
function aio_wh_pick($w, $dune_id, $eps)
{
    if (!$w)
        return array('s' => 1, 'e' => 1, 'hash' => '', 'kind' => 'none', 'rec' => '', 'why' => 'no history record');
    list($s, $e, $pos, $len, $uid) = aio_wh_rec($w);
    $hash = preg_match('/^' . preg_quote(AIO_SUP_ID . ':' . $dune_id, '/') . ':-?[0-9]+:-?[0-9]+:([0-9a-f]{40})$/D',
        $uid, $m) ? $m[1] : '';
    $watched = aio_wh_watched($pos, $len, $uid);
    $rec = sprintf('record S%dE%d pos=%d/%d watched=%s', $s, $e, $pos, $len, $watched !== '' ? $watched : 'no');
    if ($watched === '')
        return array('s' => $s, 'e' => $e, 'hash' => $hash, 'kind' => 'record', 'rec' => $rec, 'why' => 'not watched');
    list($ns, $ne, $next, $why) = aio_wh_after($eps, $s, $e);
    return array('s' => $ns, 'e' => $ne, 'hash' => $hash, 'kind' => $next ? 'next' : (isset($eps[$s]) ? 'first' : 'same'),
        'rec' => $rec, 'why' => $why);
}

// Episode of a series opened from its main card (season = episode = -1): aio_wh_pick of the
// last record in the history by season_numbers, logged. -> array(s, e, hash)
/**
 * @param AioMovie $mv
 * @return array{int, int, string}
 */
function aio_wh_episode($mv)
{
    $w = $mv['dune_id'] !== '' ? aio_wh_last($mv['dune_id']) : null;
    $p = aio_wh_pick($w, $mv['dune_id'], aio_wh_lists($mv['seasons']));
    if ($p['rec'] !== '')
        aio_log("history: {$p['rec']}, " . count($mv['seasons']) . ' seasons in season_numbers');
    aio_log("episode: S{$p['s']}E{$p['e']}, {$p['why']}");
    return array($p['s'], $p['e'], $p['hash']);
}

// The progress of the episodes of the series of card $dune_id, read once a screen of its own
// card: the latest record of each S/E of any supplier or a local file, as the native "Seasons"
// show it -> array(the latest episode record of all (aio_wh_pick) or null, "s:e" => array(pos,
// len, watched: aio_wh_watched)). Another reader of recent_uid besides aio_wh_progress.
/**
 * @param string $dune_id
 * @return array{stdClass|null, array<string, array{float, float, bool}>}
 */
function aio_wh_series($dune_id)
{
    $acc = aio_wh_find('history: series', 'aio_wh_series_visit', array($dune_id, aio_wh_marked_set()));
    $last = isset($acc['last']) ? $acc['last'] : null;
    unset($acc['last']);
    return array($last, $acc);
}

/**
 * @param mixed $w
 * @param array{string, array<int|string, true>} $arg
 * @param array<string, mixed> $acc
 * @return bool
 */
function aio_wh_series_visit($w, $arg, &$acc)
{
    if (!aio_wh_match($w, $arg[0]))
        return false;
    if (!isset($acc['last']))
        $acc['last'] = $w;
    list($s, $e, $pos, $len, $uid) = aio_wh_rec($w);
    $k = "$s:$e";
    if (isset($acc[$k]))
        return false;
    $acc[$k] = array($pos, $len, aio_wh_full($pos, $len) || isset($arg[1][$uid]));
    return false;
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
