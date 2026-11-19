<?php
// Dune's watch history (shell_ext recent/watch_history.php), read only: the
// episode of a series opened from its main card, the progress of the
// releases of a list. Functions are global; the require order is in main.php.

// Dune's watch history, as shell_ext reads it: PHP adds FS_PREFIX to /config
// itself. Tests may define it first.
if (!defined('MEL_WH_PATH'))
    define('MEL_WH_PATH', '/config/watch_history');
// A bigger history file is skipped: json_decode needs ~5.5x its size in memory.
define('MEL_WH_MAX_BYTES', 4000000);

// Our records of the history (mel_wh_ours_all) in the memory of php_server,
// until the history files change.
class MelWh
{
    /** @var string|null */
    public static $sig = null;
    /** @var array<string, float> */
    public static $all = array();
    // The latest of our records of each card (v2, spec §1.6: a movie without supplier_info opens by the profile of it):
    // dune id => array(the rest of its uid - infoHash or the key of an online record, wh_prof of its action_params ('' -
    // none, of before v2), the end of the record); one a card, not a release. $last_new: while the files are read.
    /** @var array<string, array{string, string, int}> */
    public static $last = array();
    /** @var array<string, array{string, string, int}> */
    public static $last_new = array();
    // The latest of our records of a copy of the media library (the rest of its uid a key lib-..., mel_lib_key) of each
    // card, by its end: dune id => MelLibRec. $lib_new: while the files are read.
    /** @var array<string, MelLibRec> */
    public static $lib = array();
    /** @var array<string, MelLibRec> */
    public static $lib_new = array();
}

// The card of a record that WatchHistory::do_update keeps: not deleted, wh_item_is_suitable of the
// shell -> its movie_id, '' for none or another record.
/**
 * @param mixed $w
 * @return string
 */
function mel_wh_kept($w)
{
    if (!is_object($w) || (isset($w->deleted) && $w->deleted == 1))
        return '';
    if (!isset($w->action_plugin) || !isset($w->action_params))
    {
        $url = isset($w->url) && is_string($w->url) ? $w->url : '';
        $p = strpos($url, '://');
        $scheme = $p ? substr($url, 0, $p) : '';
        if ($scheme === '' || $scheme === 'tmpfs' ||
            (isset($w->show_in_recent) ? !$w->show_in_recent : $scheme === 'file'))
            return '';
    }
    return isset($w->movie_id) && is_string($w->movie_id) ? $w->movie_id : '';
}

// A record of the card $dune_id that WatchHistory::do_update keeps (mel_wh_kept).
/**
 * @param mixed $w
 * @param string $dune_id
 * @return bool
 * @phpstan-assert-if-true stdClass $w
 */
function mel_wh_of($w, $dune_id)
{
    return $dune_id !== '' && mel_wh_kept($w) === $dune_id;
}

// An episode record of the series that WatchHistory::do_update keeps.
/**
 * @param mixed $w
 * @param string $dune_id
 * @return bool
 * @phpstan-assert-if-true stdClass $w
 */
function mel_wh_match($w, $dune_id)
{
    return mel_wh_of($w, $dune_id) && isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1;
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
function mel_wh_find($tag, $visit, $arg)
{
    $t = microtime(true);
    $dir = MEL_WH_PATH;
    if (!is_dir($dir) || !is_readable($dir))
    {
        mel_log("$tag: " . (is_dir($dir) ? 'folder not readable' : 'no folder'));
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
        if ($size > MEL_WH_MAX_BYTES)
        {
            mel_log("$tag: {$files[$i]} is $size bytes, over " . MEL_WH_MAX_BYTES . ', skipped');
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
    mel_log(sprintf('%s: %d files, %d read, %d records, %d found, %.0f ms', $tag, count($files), $read, $records,
        count($found), (microtime(true) - $t) * 1000));
    return $found;
}

// The latest episode record of the series in any NNNNNN.json, from any
// supplier or a local file, or null.
/**
 * @param string $dune_id
 * @return stdClass|null
 */
function mel_wh_last($dune_id)
{
    $found = mel_wh_find('history', 'mel_wh_last_visit', $dune_id);
    return $found ? $found[0] : null;
}

/**
 * @param mixed $w
 * @param string $dune_id
 * @param list<stdClass> $acc
 * @return bool
 */
function mel_wh_last_visit($w, $dune_id, &$acc)
{
    if (mel_wh_match($w, $dune_id))
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
function mel_wh_ours($w, $prefix)
{
    return is_object($w) && !(isset($w->deleted) && $w->deleted == 1) && isset($w->uid) && is_string($w->uid) &&
        strlen($w->uid) > strlen($prefix) && strpos($w->uid, $prefix) === 0;
}

// The latest record of each release of ours -> its share in $acc (uid =>
// share, only a float each: there may be thousands). $arg: array(prefix,
// mel_wh_marked_set()). Watched: by position or its uid marked (mel_wh_mark_on the end of the record).
/**
 * @param mixed $w
 * @param array{string, array<int|string, int>} $arg
 * @param array<string, float> $acc
 * @return bool
 */
function mel_wh_ours_visit($w, $arg, &$acc)
{
    if (!mel_wh_ours($w, $arg[0]) || isset($acc[$w->uid]))
        return false;
    $pos = isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : -1;
    $len = isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0;
    $mark = isset($arg[1][$w->uid]) ? $arg[1][$w->uid] : 0;
    $end = mel_wh_end($w);
    $acc[$w->uid] = mel_wh_full($pos, $len) || mel_wh_mark_on($mark, $end) ? 1.0 :
        ($len > 0 && $pos > 0 ? min(1.0, $pos / $len) : 0.0);
    if (!preg_match('/^[^:]+:(' . MEL_DUNE_ID . '):-?[0-9]{1,5}:-?[0-9]{1,5}:([^:]+)\z/', $w->uid, $m))
        return false;
    if (!isset(MelWh::$last_new[$m[1]]) || MelWh::$last_new[$m[1]][2] < $end)
        MelWh::$last_new[$m[1]] = array($m[2], mel_wh_prof($w), $end);
    if (mel_lib_key_ok($m[2]) && (!isset(MelWh::$lib_new[$m[1]]) || MelWh::$lib_new[$m[1]]['end'] < $end))
        MelWh::$lib_new[$m[1]] = array('key' => $m[2], 'pos' => max(0.0, $pos), 'len' => $len, 'end' => $end);
    return false;
}

// wh_prof of the action_params of record $w (the id of the profile of its start, v2), '' none or not an id.
/**
 * @param stdClass $w
 * @return string
 */
function mel_wh_prof($w)
{
    $ap = isset($w->action_params) && is_object($w->action_params) ? $w->action_params : null;
    return $ap && isset($ap->wh_prof) && mel_profile_id_ok($ap->wh_prof) ? $ap->wh_prof : '';
}

// The latest of our records of card $dune_id (mel_wh_ours_all: read again only when the history files change), as
// mel_wh_pick has it: hash and key of the rest of its uid (mel_wh_tail), prof - the id of its profile ('' none); null none
// (or the rest of its uid neither: a record of a copy on the disk is none here, mel_wh_lib_last has it).
/**
 * @param string $dune_id
 * @return array{hash: string, key: string, prof: string}|null
 */
function mel_wh_card_last($dune_id)
{
    mel_wh_ours_all();
    if ($dune_id === '' || !isset(MelWh::$last[$dune_id]))
        return null;
    list($hash, $key) = mel_wh_tail(MelWh::$last[$dune_id][0]);
    return $hash !== '' || $key !== '' ? array('hash' => $hash, 'key' => $key, 'prof' => MelWh::$last[$dune_id][1]) : null;
}

// The rest $t of the uid of a record of ours ("aio:<dune id>:<s>:<e>:<rest>") -> array(hash: the infoHash of a release,
// key: the key of an online record, mel_lp_key_parse), '' each it is not.
/**
 * @param string $t
 * @return array{string, string}
 */
function mel_wh_tail($t)
{
    return array(preg_match('/^[0-9a-f]{40}$/D', $t) ? $t : '', mel_lp_key_parse($t) ? $t : '');
}

// The latest of our records of a copy on the disk of card $hid (MelWh::$lib), null none.
/**
 * @param string $hid
 * @return MelLibRec|null
 */
function mel_wh_lib_last($hid)
{
    mel_wh_ours_all();
    return $hid !== '' && isset(MelWh::$lib[$hid]) ? MelWh::$lib[$hid] : null;
}

// is_fully_watched of the shell with its default settings: 90 %, 30 s left.
/**
 * @param float|int $pos
 * @param float|int $len
 * @return bool
 */
function mel_wh_full($pos, $len)
{
    return $len > 0 && $pos >= 0 && $pos >= $len - 30 && round($pos * 100.0 / $len) >= 90;
}

// Names, sizes and times of the history files (NNNNNN.json, watched_files.txt and
// watched_movies.txt); null without the folder or while a file is younger than
// 2 s (written again within the same second it would keep its signature).
/**
 * @return string|null
 */
function mel_wh_sig()
{
    $dir = MEL_WH_PATH;
    if (!is_dir($dir) || !is_readable($dir))
        return null;
    clearstatcache();
    $now = time();
    $sig = '';
    foreach (scandir($dir) as $f)
    {
        if ($f !== 'watched_files.txt' && $f !== 'watched_movies.txt' && !preg_match('/^[0-9]*\\.json$/', $f))
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
function mel_wh_movie($dune_id)
{
    $r = mel_wh_movie_last($dune_id);
    return $r ? array($r[0], $r[1]) : null;
}

// mel_wh_movie with the uid of that record -> array(pos, len, uid), null for none.
/**
 * @param string $dune_id
 * @return array{float, float, string}|null
 */
function mel_wh_movie_last($dune_id)
{
    $found = mel_wh_find('history: movie', 'mel_wh_movie_visit', $dune_id);
    if (!$found)
        return null;
    $w = $found[0];
    $pos = isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : 0.0;
    $len = isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0.0;
    return $len > 0 ? array($pos, $len, isset($w->uid) && is_string($w->uid) ? $w->uid : '') : null;
}

/**
 * @param mixed $w
 * @param string $dune_id
 * @param list<stdClass> $acc
 * @return bool
 */
function mel_wh_movie_visit($w, $dune_id, &$acc)
{
    if (mel_wh_of($w, $dune_id) && !(isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1))
        $acc[] = $w;
    return (bool)$acc;
}

// Our records (uid "aio:..."): uid => share of its latest record, 0 without
// progress, 1 once watched (by position or marked). Read again only when the
// history files change.
/**
 * @return array<string, float>
 */
function mel_wh_ours_all()
{
    $t = microtime(true);
    $sig = mel_wh_sig();
    if ($sig !== null && $sig === MelWh::$sig)
    {
        mel_log(sprintf('progress: cache hit, %d releases, %.0f ms', count(MelWh::$all), (microtime(true) - $t) * 1000));
        return MelWh::$all;
    }
    MelWh::$last_new = array();
    MelWh::$lib_new = array();
    $all = mel_wh_find('progress', 'mel_wh_ours_visit', array(MEL_SUP_ID . ':', mel_wh_marked_set()));
    MelWh::$sig = $sig;
    MelWh::$all = $all;
    MelWh::$last = MelWh::$last_new;
    MelWh::$last_new = array();
    MelWh::$lib = MelWh::$lib_new;
    MelWh::$lib_new = array();
    return $all;
}

// Progress of the releases of a list watched before (the movie or the
// episode of $st): infoHash => share in (0, 1]. No dune id: none, the history
// is not read.
/**
 * @param MelState $st
 * @return MelProgress
 */
function mel_wh_progress($st)
{
    $mv = $st['movie'];
    if ($mv['dune_id'] === '')
        return array();
    $res = array();
    foreach (mel_wh_ours_all() as $uid => $share)
    {
        $u = $share > 0 ? mel_wh_uid(strval($uid), $mv['dune_id']) : null;
        if ($u && $u[0] === $st['s'] && $u[1] === $st['e'])
            $res[$u[2]] = $share;
    }
    return $res;
}

// uids marked as watched in watched_files.txt, as load_ts_by_id_from_wh_file (r25): uid => the
// time of the mark ("<ts>\t<uid>"; no tab: PHP_INT_MAX, the newest), a time of 0 or less skipped.
// Read anew at every call: update_watched_marks writes the file before its post_action. $name
// watched_movies.txt: the movie_ids of the cards marked so.
/**
 * @param string $name
 * @return array<int|string, int>
 */
function mel_wh_marked_set($name = 'watched_files.txt')
{
    $path = MEL_WH_PATH . "/$name";
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

// uid "aio:<dune id>:<s>:<e>:<rest>" of a record of our release (rest: its infoHash) or a mark of the own card
// (rest: m, mel_wh_mark_uid) of the series of card $dune_id -> array(s, e, rest), null for another.
/**
 * @param string $uid
 * @param string $dune_id
 * @return array{int, int, string}|null
 */
function mel_wh_uid($uid, $dune_id)
{
    $p = MEL_SUP_ID . ":$dune_id:";
    $n = strlen($p);
    return $dune_id !== '' && strncmp($uid, $p, $n) === 0 && preg_match('/^(-?[0-9]{1,5}):(-?[0-9]{1,5}):([^:]+)$/D',
        substr($uid, $n), $m) ? array(intval($m[1]), intval($m[2]), $m[3]) : null;
}

// The uid of the mark of episode $q:$e (the season as asked, mel_cm_req_s) of the series of card
// $dune_id that the own card sets (update_watched_marks): "aio:<dune id>:<s>:<e>:m". No record has
// it ("m" is no infoHash): it stays on every firmware until taken off.
/**
 * @param string $dune_id
 * @param int|string $q
 * @param int $e
 * @return string
 */
function mel_wh_mark_uid($dune_id, $q, $e)
{
    return implode(':', array(MEL_SUP_ID, $dune_id, $q, $e, 'm'));
}

// The marks of the episodes of the series of card $dune_id in $set (mel_wh_marked_set) by their
// uids mel_wh_mark_uid: "s:e" => the time of the mark.
/**
 * @param string $dune_id
 * @param array<int|string, int> $set
 * @return array<string, int>
 */
function mel_wh_marks($dune_id, $set)
{
    $out = array();
    foreach ($set as $uid => $ts)
    {
        $u = mel_wh_uid(strval($uid), $dune_id);
        if ($u && $u[2] === 'm' && $u[0] >= 0 && $u[1] >= 0)
            $out["{$u[0]}:{$u[1]}"] = $ts;
    }
    return $out;
}

// The last by order (season, then episode) of the marks of the own card $marks (mel_wh_marks) made after $end
// (the end of the latest record of the series; 0: any) -> array(s, e), null for none. Not the newest by time:
// "Mark previous" gives many episodes one time.
/**
 * @param array<string, int> $marks
 * @param int $end
 * @return array{int, int}|null
 */
function mel_wh_mark_last($marks, $end)
{
    $last = null;
    foreach ($marks as $k => $ts)
    {
        if ($ts <= $end)
            continue;
        $p = explode(':', strval($k));
        $s = intval($p[0]);
        $e = isset($p[1]) ? intval($p[1]) : 0;
        if (!$last || $s > $last[0] || ($s === $last[0] && $e > $last[1]))
            $last = array($s, $e);
    }
    return $last;
}

// When record $w ends, as watch_history.php r25 counts it: start + watched; 0 without start.
/**
 * @param stdClass $w
 * @return int
 */
function mel_wh_end($w)
{
    if (!isset($w->start) || !is_numeric($w->start))
        return 0;
    return intval($w->start) + (isset($w->watched) && is_numeric($w->watched) ? max(0, intval($w->watched)) : 0);
}

// A mark of the uid of a record made at $ts (0: none) counts for the record that ends at $end, as
// the native: on r25 only if newer (get_file_mark_time), on older firmware at any time. Not by this
// rule: a mark of the own card (mel_wh_mark_uid: no record has it, no native meaning) counts only if
// newer than the record of its episode, on any firmware (mel_wh_series_visit); mel_wh_mark_next
// takes only the marks of the own card made after the latest record of the series ("> its end");
// the fallback of mel_wh_resume_ep counts the mark of the uid at any time without wh_len and none
// with it (the position only).
/**
 * @param int $ts
 * @param int $end
 * @return bool
 */
function mel_wh_mark_on($ts, $end)
{
    return $ts > 0 && ($ts > $end || mel_fw_r() < 25);
}

// uid in watched_files.txt (marked as watched).
/**
 * @param string $uid
 * @return bool
 */
function mel_wh_marked($uid)
{
    $set = $uid !== '' ? mel_wh_marked_set() : array();
    return isset($set[$uid]);
}

// The episode after the watched S$s E$e by season_numbers, as 0.9.0: the next
// one of the season, else E1 of the nearest later season; the same one with
// no data on season $s, S1E1 after the last one. -> array(s, e, next: a later
// episode, why). Unlike mel_flip_ep (+1) on purpose: past episodes_count, the next season.
/**
 * @param array<int, int> $seasons
 * @param int $s
 * @param int $e
 * @return array{int, int, bool, string}
 */
function mel_wh_next($seasons, $s, $e)
{
    return mel_wh_after(mel_wh_lists($seasons), $s, $e);
}

// season_numbers (season => episodes) -> season => its episodes 1..N.
/**
 * @param array<int, int> $seasons
 * @return array<int, list<int>>
 */
function mel_wh_lists($seasons)
{
    $out = array();
    foreach ($seasons as $s => $n)
        $out[$s] = range(1, $n);
    return $out;
}

// The episode after the watched S$s E$e by $eps (season => its episodes, ascending): the next
// one of the season, else the first of the nearest later season; the same one with no data on
// season $s, S1E1 after the last one. -> as mel_wh_next.
/**
 * @param array<int, list<int>> $eps
 * @param int $s
 * @param int $e
 * @return array{int, int, bool, string}
 */
function mel_wh_after($eps, $s, $e)
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
// len (0 for none), uid (else url, else ''), the id of the profile of its start (mel_wh_prof, '' none)).
/**
 * @param stdClass $w
 * @return array{int, int, float, float, string, string}
 */
function mel_wh_rec($w)
{
    return array(isset($w->s) && is_numeric($w->s) && intval($w->s) >= 0 ? intval($w->s) : 1,
        isset($w->e) && is_numeric($w->e) ? intval($w->e) : 1,
        isset($w->pos) && is_numeric($w->pos) ? floatval($w->pos) : -1.0,
        isset($w->len) && is_numeric($w->len) ? floatval($w->len) : 0.0,
        isset($w->uid) && is_string($w->uid) ? $w->uid : (isset($w->url) && is_string($w->url) ? $w->url : ''),
        mel_wh_prof($w));
}

// The episode after the marks of the own card in state $st of a series (mel_wh_series) made after
// its latest episode record ends (none: any): the first one not watched (by the progress of $st)
// after the last of them by $eps (season => its episodes, ascending). -> array(s, e, a later one:
// false - all watched after it, S1E1, why); null: no such mark, the record decides.
/**
 * @param MelWhSeries $st
 * @param array<int, list<int>> $eps
 * @return array{int, int, bool, string}|null
 */
function mel_wh_mark_next($st, $eps)
{
    list($w, $prog, $marks) = $st;
    if (!$marks)
        return null;
    $end = $w ? mel_wh_end($w) : 0;
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

// The episode to offer for state $st of the series of card $dune_id (mel_wh_series, mel_wh_native;
// no latest episode record: none), no log: the marks of the own card made after the record
// (mel_wh_mark_next) first; else the recorded one while not watched (by the progress of $st, as
// the row shows it), the next one by $eps (mel_wh_after) once it is, S1E1 with no record or after
// the last episode. hash: the release of the record if it is ours (uid "aio:<dune id>:<s>:<e>:<infoHash>"),
// also with another episode offered (its list opens at that release), else ''; key: so the key of an online
// record of ours (uid "aio:<dune id>:<s>:<e>:lp-…", mel_lp_key_parse: its online list opens at the row of
// it), else ''; prof: the id of the profile of the record (wh_prof of its action_params, v2; '' none); kind: none (no
// record), record (not watched), next, same (watched, no data on its season), first (the last one
// watched); rec: the record for the log, why: the choice for the log.
/**
 * @param MelWhSeries $st
 * @param string $dune_id
 * @param array<int, list<int>> $eps
 * @return MelWhPick
 */
function mel_wh_pick($st, $dune_id, $eps)
{
    $w = $st[0];
    $mk = mel_wh_mark_next($st, $eps);
    if (!$w)
        return $mk ? array('s' => $mk[0], 'e' => $mk[1], 'hash' => '', 'key' => '', 'prof' => '',
            'kind' => $mk[2] ? 'next' : 'first', 'rec' => '', 'why' => $mk[3]) : array('s' => 1, 'e' => 1, 'hash' => '',
            'key' => '', 'prof' => '', 'kind' => 'none', 'rec' => '', 'why' => 'no history record');
    list($s, $e, $pos, $len, $uid, $prof) = mel_wh_rec($w);
    $u = mel_wh_uid($uid, $dune_id);
    list($hash, $key) = $u ? mel_wh_tail($u[2]) : array('', '');
    $watched = isset($st[1]["$s:$e"]) && $st[1]["$s:$e"][2] ? (mel_wh_full($pos, $len) ? 'by position' : 'marked') : '';
    $rec = sprintf('record S%dE%d pos=%d/%d watched=%s', $s, $e, $pos, $len, $watched !== '' ? $watched : 'no');
    if ($mk)
        return array('s' => $mk[0], 'e' => $mk[1], 'hash' => $hash, 'key' => $key, 'prof' => $prof,
            'kind' => $mk[2] ? 'next' : 'first', 'rec' => $rec, 'why' => $mk[3]);
    if ($watched === '')
        return array('s' => $s, 'e' => $e, 'hash' => $hash, 'key' => $key, 'prof' => $prof, 'kind' => 'record', 'rec' => $rec,
            'why' => 'not watched');
    list($ns, $ne, $next, $why) = mel_wh_after($eps, $s, $e);
    return array('s' => $ns, 'e' => $ne, 'hash' => $hash, 'key' => $key, 'prof' => $prof,
        'kind' => $next ? 'next' : (isset($eps[$s]) ? 'first' : 'same'), 'rec' => $rec, 'why' => $why);
}

// The state of the series of card $dune_id for the ways in of the native (mel_wh_episode,
// mel_wh_resume_ep) by $eps (season_numbers of dunemdb, mel_wh_lists), watched_files.txt read
// once: with marks of the own card all of the history (mel_wh_series); without them, or when the
// last of them by order is not in $eps (an anime numbered on by the own card with no pairs: its 1:70
// against 8 episodes of S1 of dunemdb), the latest record alone (mel_wh_alone; with no marks only if
// $last, else none and the history not read): the record decides, as before the marks.
/**
 * @param string $dune_id
 * @param array<int, list<int>> $eps
 * @param bool $last
 * @return MelWhSeries
 */
function mel_wh_native($dune_id, $eps, $last)
{
    $set = $dune_id !== '' ? mel_wh_marked_set() : array();
    $marks = mel_wh_marks($dune_id, $set);
    $w = null;
    $mk = mel_wh_mark_last($marks, 0);
    if ($mk)
    {
        $st = mel_wh_series($dune_id, $set);
        list($ls, $le) = $mk;
        if (isset($eps[$ls]) && in_array($le, $eps[$ls], true))
            return $st;
        mel_log("history: the last mark S{$ls}E{$le} not in season_numbers, the record decides");
        $w = $st[0];
    }
    else if ($last && $dune_id !== '')
        $w = mel_wh_last($dune_id);
    return mel_wh_alone($w, $dune_id, $set);
}

// The state of the series of card $dune_id as mel_wh_series of its latest episode record $w alone
// (null: none): the progress of its episode by its position and the mark of its uid in $set
// (mel_wh_marked_set), no marks of the own card.
/**
 * @param stdClass|null $w
 * @param string $dune_id
 * @param array<int|string, int> $set
 * @return MelWhSeries
 */
function mel_wh_alone($w, $dune_id, $set)
{
    $acc = array();
    if ($w)
        mel_wh_series_visit($w, array($dune_id, $set, array()), $acc);
    unset($acc['last']);
    return array($w, $acc, array());
}

// Episode of a series opened from its main card (season = episode = -1): mel_wh_pick of the state
// of the series for the ways in of the native (mel_wh_native) by season_numbers, logged.
/**
 * @param MelMovie $mv
 * @return MelWhPick
 */
function mel_wh_episode($mv)
{
    $eps = mel_wh_lists($mv['seasons']);
    $p = mel_wh_pick(mel_wh_native($mv['dune_id'], $eps, true), $mv['dune_id'], $eps);
    if ($p['rec'] !== '')
        mel_log("history: {$p['rec']}, " . count($mv['seasons']) . ' seasons in season_numbers');
    mel_log("episode: S{$p['s']}E{$p['e']}, {$p['why']}");
    return $p;
}

// The state of the series of card $dune_id, read once a screen of its own card, by $set
// (mel_wh_marked_set): the progress of its episodes, the latest record of each S/E of any supplier
// or a local file, as the native "Seasons" show it -> array(the latest episode record of all
// (mel_wh_pick) or null, "s:e" => array(pos, len, watched), the marks of the own card
// (mel_wh_marks)). Watched: by position, its uid marked (mel_wh_mark_on the end of the record), or
// the mark of the own card of its S/E made after the record ends; an episode with such a mark and
// no record: array(-1, 0, true). Another reader of recent_uid besides mel_wh_progress.
/**
 * @param string $dune_id
 * @param array<int|string, int> $set
 * @return MelWhSeries
 */
function mel_wh_series($dune_id, $set)
{
    $marks = mel_wh_marks($dune_id, $set);
    $acc = mel_wh_find('history: series', 'mel_wh_series_visit', array($dune_id, $set, $marks));
    $last = isset($acc['last']) ? $acc['last'] : null;
    unset($acc['last']);
    return array($last, mel_wh_series_fill($acc, $marks), $marks);
}

// The progress $prog of the episodes of a series (mel_wh_series) with the episodes of the marks of the own card
// $marks that have no record: array(-1, 0, true).
/**
 * @param array<string, array{float, float, bool}> $prog
 * @param array<string, int> $marks
 * @return array<string, array{float, float, bool}>
 */
function mel_wh_series_fill($prog, $marks)
{
    foreach ($marks as $k => $ts)
    {
        if (!isset($prog[$k]))
            $prog[$k] = array(-1.0, 0.0, true);
    }
    return $prog;
}

/**
 * @param mixed $w
 * @param array{string, array<int|string, int>, array<string, int>} $arg
 * @param array<string, mixed> $acc
 * @return bool
 */
function mel_wh_series_visit($w, $arg, &$acc)
{
    if (!mel_wh_match($w, $arg[0]))
        return false;
    if (!isset($acc['last']))
        $acc['last'] = $w;
    // e is a number of 1 or more here (mel_wh_match): mel_wh_rec's E1 for no e is not reached.
    list($s, $e, $pos, $len) = mel_wh_rec($w);
    $k = "$s:$e";
    if (isset($acc[$k]))
        return false;
    $acc[$k] = array($pos, $len, mel_wh_ep_watched($w, $arg[1], $arg[2]));
    return false;
}

// Episode record $w watched: by its position, its uid marked in $set (mel_wh_marked_set, mel_wh_mark_on) or
// its episode marked by the own card ($marks of mel_wh_marks) after the record ended.
/**
 * @param stdClass $w
 * @param array<int|string, int> $set
 * @param array<string, int> $marks
 * @return bool
 */
function mel_wh_ep_watched($w, $set, $marks)
{
    list($s, $e, $pos, $len, $uid) = mel_wh_rec($w);
    $end = mel_wh_end($w);
    $mark = isset($marks["$s:$e"]) ? $marks["$s:$e"] : 0;
    return mel_wh_full($pos, $len) || mel_wh_mark_on(isset($set[$uid]) ? $set[$uid] : 0, $end) || ($mark > 0 && $mark > $end);
}

// The uids of the records of our releases of the episodes $keys ("s:e" as asked) of the series of
// card $dune_id, one pass over the history: "s:e" => list of uids (the marks of update_watched_marks
// go on them too: the native "Seasons" show such an episode watched).
/**
 * @param string $dune_id
 * @param list<string> $keys
 * @return array<string, list<string>>
 */
function mel_wh_ours_eps($dune_id, $keys)
{
    $acc = mel_wh_find('history: marks', 'mel_wh_ours_eps_visit', array($dune_id, array_flip($keys)));
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
function mel_wh_ours_eps_visit($w, $arg, &$acc)
{
    $u = mel_wh_ours($w, MEL_SUP_ID . ':') ? mel_wh_uid($w->uid, $arg[0]) : null;
    $k = $u ? "{$u[0]}:{$u[1]}" : '';
    if ($u && $u[2] !== 'm' && isset($arg[1][$k]))
        $acc[$k][$w->uid] = true;
    return false;
}

// The uids update_watched_marks marks for episodes $keys ("q:e" as asked, mel_cm_hkey) of the series of card $dune_id:
// the mark of the own card of each (mel_wh_mark_uid), then the uids of the records of our releases of it
// (mel_wh_ours_eps), in the order of $keys.
/**
 * @param string $dune_id
 * @param list<string> $keys
 * @return list<string>
 */
function mel_wh_mark_ids($dune_id, $keys)
{
    $recs = mel_wh_ours_eps($dune_id, $keys);
    $ids = array();
    foreach ($keys as $k)
    {
        list($q, $n) = explode(':', $k);
        $ids[] = mel_wh_mark_uid($dune_id, $q, intval($n));
        foreach (isset($recs[$k]) ? $recs[$k] : array() as $u)
            $ids[] = $u;
    }
    return $ids;
}

// The uids of card $dune_id marked in watched_files.txt (mel_wh_marked_set): the marks of the own card and of the
// records of our releases ("aio:<dune id>:..."), as they are; no request, the history not read.
/**
 * @param string $dune_id
 * @return list<string>
 */
function mel_wh_marked_of($dune_id)
{
    $p = MEL_SUP_ID . ":$dune_id:";
    $out = array();
    foreach (mel_wh_marked_set() as $uid => $ts)
    {
        if ($dune_id !== '' && strncmp(strval($uid), $p, strlen($p)) === 0)
            $out[] = strval($uid);
    }
    return $out;
}

// "Continue watching" of a series ($r of mel_resume): the episode recorded,
// from its position; once it is watched the next one (mel_wh_next) from 0,
// 'next' set: its release must have it in its file. Watched: with wh_len by the
// position only (r25 counts a mark only if newer than the record,
// get_file_mark_time), without it by the mark only, at any time. Marks of the own card made after
// the latest record of the series (mel_wh_mark_next of mel_wh_native): the episode they give, as
// the own card offers it, from 0 the same way (from the position if it is the recorded one). One
// log line, no uid. -> array($r of the episode to play or null - watched, no later one: the list; s, e)
/**
 * @param MelMovie $mv
 * @param MelResume $r
 * @return array{MelResume|null, int, int}
 */
function mel_wh_resume_ep($mv, $r)
{
    $s = $r['s'];
    $e = $r['e'];
    $d = $mv['dune_id'];
    $eps = mel_wh_lists($mv['seasons']);
    $mk = mel_wh_mark_next(mel_wh_native($d, $eps, false), $eps);
    if ($mk)
    {
        $same = $mk[0] === $s && $mk[1] === $e;
        mel_log("resume: record S{$s}E{$e}, marked later -> S{$mk[0]}E{$mk[1]}, {$mk[3]}" .
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
    $watched = $r['len'] > 0 ? (mel_wh_full($r['pos'], $r['len']) ? 'by position' : '') :
        (mel_wh_marked($r['uid']) ? 'marked' : '');
    $tag = "resume: record S{$s}E{$e} pos={$r['pos']}/{$r['len']} watched=" . ($watched !== '' ? $watched : 'no');
    if ($watched === '')
    {
        mel_log("$tag -> S{$s}E{$e}");
        return array($r, $s, $e);
    }
    list($ns, $ne, $next, $why) = mel_wh_next($mv['seasons'], $s, $e);
    mel_log("$tag -> S{$ns}E{$ne}, $why" . ($next ? ', from 0' : ', its list'));
    if (!$next)
        return array(null, $ns, $ne);
    $r['s'] = $ns;
    $r['e'] = $ne;
    $r['pos'] = 0;
    $r['next'] = true;
    return array($r, $ns, $ne);
}
