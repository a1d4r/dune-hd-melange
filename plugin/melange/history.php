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
// aio_wh_marked_set()). Watched: by position or its uid marked (aio_wh_mark_on the end of the record).
/**
 * @param mixed $w
 * @param array{string, array<int|string, int>} $arg
 * @param array<string, float> $acc
 * @return bool
 */
function aio_wh_ours_visit($w, $arg, &$acc)
{
    if (!aio_wh_ours($w, $arg[0]) || isset($acc[$w->uid]))
        return false;
    $pos = isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : -1;
    $len = isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0;
    $mark = isset($arg[1][$w->uid]) ? $arg[1][$w->uid] : 0;
    $acc[$w->uid] = aio_wh_full($pos, $len) || aio_wh_mark_on($mark, aio_wh_end($w)) ? 1.0 :
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

// uids marked as watched in watched_files.txt, as load_ts_by_id_from_wh_file (r25): uid => the
// time of the mark ("<ts>\t<uid>"; no tab: PHP_INT_MAX, the newest), a time of 0 or less skipped.
// Read anew at every call: update_watched_marks writes the file before its post_action.
/**
 * @return array<int|string, int>
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
        $ts = PHP_INT_MAX;
        $p = strpos($id, "\t");
        if ($p !== false)
        {
            $ts = intval(substr($id, 0, $p));
            $id = strval(substr($id, $p + 1));
        }
        if ($ts > 0 && $id !== '')
            $set[$id] = $ts;
    }
    return $set;
}

// The uid of the mark of episode $q:$e (the season as asked, aio_cm_req_s) of the series of card
// $dune_id that the own card sets (update_watched_marks): "aio:<dune id>:<s>:<e>:m". No record has
// it ("m" is no infoHash): it stays on every firmware until taken off.
/**
 * @param string $dune_id
 * @param int|string $q
 * @param int $e
 * @return string
 */
function aio_wh_mark_uid($dune_id, $q, $e)
{
    return implode(':', array(AIO_SUP_ID, $dune_id, $q, $e, 'm'));
}

// The marks of the episodes of the series of card $dune_id in $set (aio_wh_marked_set) by their
// uids aio_wh_mark_uid: "s:e" => the time of the mark.
/**
 * @param string $dune_id
 * @param array<int|string, int> $set
 * @return array<string, int>
 */
function aio_wh_marks($dune_id, $set)
{
    $out = array();
    if ($dune_id === '')
        return $out;
    $p = AIO_SUP_ID . ":$dune_id:";
    $n = strlen($p);
    foreach ($set as $uid => $ts)
    {
        $uid = strval($uid);
        if (strncmp($uid, $p, $n) === 0 && preg_match('/^([0-9]{1,5}):([0-9]{1,5}):m$/D', substr($uid, $n), $m))
            $out[intval($m[1]) . ':' . intval($m[2])] = $ts;
    }
    return $out;
}

// When record $w ends, as watch_history.php r25 counts it: start + watched; 0 without start.
/**
 * @param stdClass $w
 * @return int
 */
function aio_wh_end($w)
{
    if (!isset($w->start) || !is_numeric($w->start))
        return 0;
    return intval($w->start) + (isset($w->watched) && is_numeric($w->watched) ? max(0, intval($w->watched)) : 0);
}

// A mark of the uid of a record made at $ts (0: none) counts for the record that ends at $end, as
// the native: on r25 only if newer (get_file_mark_time), on older firmware at any time. Not by this
// rule: a mark of the own card (aio_wh_mark_uid: no record has it, no native meaning) counts only if
// newer than the record of its episode, on any firmware (aio_wh_series_visit); aio_wh_mark_next
// takes only the marks of the own card made after the latest record of the series ("> its end");
// the fallback of aio_wh_resume_ep counts the mark of the uid at any time without wh_len and none
// with it (the position only).
/**
 * @param int $ts
 * @param int $end
 * @return bool
 */
function aio_wh_mark_on($ts, $end)
{
    return $ts > 0 && ($ts > $end || aio_fw_r() < 25);
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

// The episode after the marks of the own card in state $st of a series (aio_wh_series) made after
// its latest episode record ends (none: any): the first one not watched (by the progress of $st)
// after the last of them by $eps (season => its episodes, ascending). -> array(s, e, a later one:
// false - all watched after it, S1E1, why); null: no such mark, the record decides.
/**
 * @param AioWhSeries $st
 * @param array<int, list<int>> $eps
 * @return array{int, int, bool, string}|null
 */
function aio_wh_mark_next($st, $eps)
{
    list($w, $prog, $marks) = $st;
    if (!$marks)
        return null;
    $end = $w ? aio_wh_end($w) : 0;
    $last = '';
    foreach ($eps as $s => $list)
    {
        foreach ($list as $e)
        {
            if (isset($marks["$s:$e"]) && $marks["$s:$e"] > $end)
                $last = "$s:$e";
        }
    }
    if ($last === '')
        return null;
    $after = false;
    foreach ($eps as $s => $list)
    {
        foreach ($list as $e)
        {
            if ($after && !(isset($prog["$s:$e"]) && $prog["$s:$e"][2]))
                return array($s, $e, true, "first not watched after the marked S" . str_replace(':', 'E', $last));
            if ("$s:$e" === $last)
                $after = true;
        }
    }
    return array(1, 1, false, 'watched after the marked S' . str_replace(':', 'E', $last));
}

// The episode to offer for state $st of the series of card $dune_id (aio_wh_series, aio_wh_native;
// no latest episode record: none), no log: the marks of the own card made after the record
// (aio_wh_mark_next) first; else the recorded one while not watched (by the progress of $st, as
// the row shows it), the next one by $eps (aio_wh_after) once it is, S1E1 with no record or after
// the last episode. hash: the release of the record if it is ours (uid "aio:<dune id>:<s>:<e>:<infoHash>"),
// also with another episode offered (its list opens at that release), else ''; kind: none (no
// record), record (not watched), next, same (watched, no data on its season), first (the last one
// watched); rec: the record for the log, why: the choice for the log.
/**
 * @param AioWhSeries $st
 * @param string $dune_id
 * @param array<int, list<int>> $eps
 * @return AioWhPick
 */
function aio_wh_pick($st, $dune_id, $eps)
{
    $w = $st[0];
    $mk = aio_wh_mark_next($st, $eps);
    if (!$w)
        return $mk ? array('s' => $mk[0], 'e' => $mk[1], 'hash' => '', 'kind' => $mk[2] ? 'next' : 'first', 'rec' => '',
            'why' => $mk[3]) : array('s' => 1, 'e' => 1, 'hash' => '', 'kind' => 'none', 'rec' => '', 'why' => 'no history record');
    list($s, $e, $pos, $len, $uid) = aio_wh_rec($w);
    $hash = preg_match('/^' . preg_quote(AIO_SUP_ID . ':' . $dune_id, '/') . ':-?[0-9]+:-?[0-9]+:([0-9a-f]{40})$/D',
        $uid, $m) ? $m[1] : '';
    $watched = isset($st[1]["$s:$e"]) && $st[1]["$s:$e"][2] ? (aio_wh_full($pos, $len) ? 'by position' : 'marked') : '';
    $rec = sprintf('record S%dE%d pos=%d/%d watched=%s', $s, $e, $pos, $len, $watched !== '' ? $watched : 'no');
    if ($mk)
        return array('s' => $mk[0], 'e' => $mk[1], 'hash' => $hash, 'kind' => $mk[2] ? 'next' : 'first', 'rec' => $rec,
            'why' => $mk[3]);
    if ($watched === '')
        return array('s' => $s, 'e' => $e, 'hash' => $hash, 'kind' => 'record', 'rec' => $rec, 'why' => 'not watched');
    list($ns, $ne, $next, $why) = aio_wh_after($eps, $s, $e);
    return array('s' => $ns, 'e' => $ne, 'hash' => $hash, 'kind' => $next ? 'next' : (isset($eps[$s]) ? 'first' : 'same'),
        'rec' => $rec, 'why' => $why);
}

// The state of the series of card $dune_id for the ways in of the native (aio_wh_episode,
// aio_wh_resume_ep) by $eps (season_numbers of dunemdb, aio_wh_lists), watched_files.txt read
// once: with marks of the own card all of the history (aio_wh_series); without them, or when the
// last of them by order is not in $eps (an anime numbered on by the own card: its 1:70 against 8
// episodes of S1 of dunemdb), the latest record alone (aio_wh_alone; with no marks only if $last,
// else none and the history not read): the record decides, as before the marks.
/**
 * @param string $dune_id
 * @param array<int, list<int>> $eps
 * @param bool $last
 * @return AioWhSeries
 */
function aio_wh_native($dune_id, $eps, $last)
{
    $set = $dune_id !== '' ? aio_wh_marked_set() : array();
    $marks = aio_wh_marks($dune_id, $set);
    $w = null;
    if ($marks)
    {
        $st = aio_wh_series($dune_id, $set);
        $ls = -1;
        $le = -1;
        foreach (array_keys($marks) as $k)
        {
            list($a, $b) = explode(':', strval($k));
            if (intval($a) > $ls || (intval($a) === $ls && intval($b) > $le))
            {
                $ls = intval($a);
                $le = intval($b);
            }
        }
        if (isset($eps[$ls]) && in_array($le, $eps[$ls], true))
            return $st;
        aio_log("history: the last mark S{$ls}E{$le} not in season_numbers, the record decides");
        $w = $st[0];
    }
    else if ($last && $dune_id !== '')
        $w = aio_wh_last($dune_id);
    return aio_wh_alone($w, $dune_id, $set);
}

// The state of the series of card $dune_id as aio_wh_series of its latest episode record $w alone
// (null: none): the progress of its episode by its position and the mark of its uid in $set
// (aio_wh_marked_set), no marks of the own card.
/**
 * @param stdClass|null $w
 * @param string $dune_id
 * @param array<int|string, int> $set
 * @return AioWhSeries
 */
function aio_wh_alone($w, $dune_id, $set)
{
    $acc = array();
    if ($w)
        aio_wh_series_visit($w, array($dune_id, $set, array()), $acc);
    unset($acc['last']);
    return array($w, $acc, array());
}

// Episode of a series opened from its main card (season = episode = -1): aio_wh_pick of the state
// of the series for the ways in of the native (aio_wh_native) by season_numbers, logged. -> array(s, e, hash)
/**
 * @param AioMovie $mv
 * @return array{int, int, string}
 */
function aio_wh_episode($mv)
{
    $eps = aio_wh_lists($mv['seasons']);
    $p = aio_wh_pick(aio_wh_native($mv['dune_id'], $eps, true), $mv['dune_id'], $eps);
    if ($p['rec'] !== '')
        aio_log("history: {$p['rec']}, " . count($mv['seasons']) . ' seasons in season_numbers');
    aio_log("episode: S{$p['s']}E{$p['e']}, {$p['why']}");
    return array($p['s'], $p['e'], $p['hash']);
}

// The state of the series of card $dune_id, read once a screen of its own card, by $set
// (aio_wh_marked_set): the progress of its episodes, the latest record of each S/E of any supplier
// or a local file, as the native "Seasons" show it -> array(the latest episode record of all
// (aio_wh_pick) or null, "s:e" => array(pos, len, watched), the marks of the own card
// (aio_wh_marks)). Watched: by position, its uid marked (aio_wh_mark_on the end of the record), or
// the mark of the own card of its S/E made after the record ends; an episode with such a mark and
// no record: array(-1, 0, true). Another reader of recent_uid besides aio_wh_progress.
/**
 * @param string $dune_id
 * @param array<int|string, int> $set
 * @return AioWhSeries
 */
function aio_wh_series($dune_id, $set)
{
    $marks = aio_wh_marks($dune_id, $set);
    $acc = aio_wh_find('history: series', 'aio_wh_series_visit', array($dune_id, $set, $marks));
    $last = isset($acc['last']) ? $acc['last'] : null;
    unset($acc['last']);
    foreach ($marks as $k => $ts)
    {
        if (!isset($acc[$k]))
            $acc[$k] = array(-1.0, 0.0, true);
    }
    return array($last, $acc, $marks);
}

/**
 * @param mixed $w
 * @param array{string, array<int|string, int>, array<string, int>} $arg
 * @param array<string, mixed> $acc
 * @return bool
 */
function aio_wh_series_visit($w, $arg, &$acc)
{
    if (!aio_wh_match($w, $arg[0]))
        return false;
    if (!isset($acc['last']))
        $acc['last'] = $w;
    // e is a number of 1 or more here (aio_wh_match): aio_wh_rec's E1 for no e is not reached.
    list($s, $e, $pos, $len, $uid) = aio_wh_rec($w);
    $k = "$s:$e";
    if (isset($acc[$k]))
        return false;
    $end = aio_wh_end($w);
    $mark = isset($arg[2][$k]) ? $arg[2][$k] : 0;
    $acc[$k] = array($pos, $len, aio_wh_full($pos, $len) || aio_wh_mark_on(isset($arg[1][$uid]) ? $arg[1][$uid] : 0, $end) ||
        ($mark > 0 && $mark > $end));
    return false;
}

// The uids of the records of our releases of the episodes $keys ("s:e" as asked) of the series of
// card $dune_id, one pass over the history: "s:e" => list of uids (the marks of update_watched_marks
// go on them too: the native "Seasons" show such an episode watched).
/**
 * @param string $dune_id
 * @param list<string> $keys
 * @return array<string, list<string>>
 */
function aio_wh_ours_eps($dune_id, $keys)
{
    $acc = aio_wh_find('history: marks', 'aio_wh_ours_eps_visit', array(AIO_SUP_ID . ":$dune_id:", array_flip($keys)));
    $out = array();
    foreach ($acc as $k => $uids)
    {
        foreach ($uids as $u => $t)
            $out[strval($k)][] = strval($u);
    }
    return $out;
}

/**
 * @param mixed $w
 * @param array{string, array<string, int>} $arg
 * @param array<string, array<string, true>> $acc
 * @return bool
 */
function aio_wh_ours_eps_visit($w, $arg, &$acc)
{
    if (aio_wh_ours($w, $arg[0]) && preg_match('/^([0-9]{1,5}:[0-9]{1,5}):([^:]+)$/D', substr($w->uid, strlen($arg[0])), $m) &&
        $m[2] !== 'm' && isset($arg[1][$m[1]]))
        $acc[$m[1]][$w->uid] = true;
    return false;
}

// "Continue watching" of a series ($r of aio_resume): the episode recorded,
// from its position; once it is watched the next one (aio_wh_next) from 0,
// 'next' set: its release must have it in its file. Watched: with wh_len by the
// position only (r25 counts a mark only if newer than the record,
// get_file_mark_time), without it by the mark only, at any time. Marks of the own card made after
// the latest record of the series (aio_wh_mark_next of aio_wh_native): the episode they give, as
// the own card offers it, from 0 the same way (from the position if it is the recorded one). One
// log line, no uid. -> array($r of the episode to play or null - watched, no later one: the list; s, e)
/**
 * @param AioMovie $mv
 * @param AioResume $r
 * @return array{AioResume|null, int, int}
 */
function aio_wh_resume_ep($mv, $r)
{
    $s = $r['s'];
    $e = $r['e'];
    $d = $mv['dune_id'];
    $eps = aio_wh_lists($mv['seasons']);
    $mk = aio_wh_mark_next(aio_wh_native($d, $eps, false), $eps);
    if ($mk)
    {
        $same = $mk[0] === $s && $mk[1] === $e;
        aio_log("resume: record S{$s}E{$e}, marked later -> S{$mk[0]}E{$mk[1]}, {$mk[3]}" .
            ($same ? ', from its position' : ($mk[2] ? ', from 0' : ', its list')));
        if ($same)
            return array($r, $s, $e);
        if (!$mk[2])
            return array(null, $mk[0], $mk[1]);
        $r['s'] = $mk[0];
        $r['e'] = $mk[1];
        $r['pos'] = 0;
        $r['next'] = true;
        return array($r, $mk[0], $mk[1]);
    }
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
