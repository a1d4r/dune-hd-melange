<?php
// The pairs of the titles of the library (mel_pg_lib_queue) in a process of its own: php-cgi of the firmware, started by
// php_server with exec (nice 19, timeout KILL; stdin, stdout and stderr redirected, one '&': exec does not wait for it -
// device r25, 7-14 ms), runs bg/lib_map.php: it reads the cards of the shell of a job file of tmp_dir and writes the
// pairs to a result file there. php_server only writes the pairs (wh_links.json): it takes the result at the next
// opening of a screen of rows or a tick of the main one (mel_pg_lib_work). While it runs, the step in php_server reads
// no card; with no php-cgi, no exec, or after a pass that died, the step as before (mel_pg_lib_step).
// php_server: needs links.php, library.php (mel_lib_index via mel_pg_lib_queue), main.php (mel_log, mel_write_file).
// php-cgi (bg/lib_map.php): mel_pg_bg_run only, with parse.php (mel_str) and links.php (mel_pg_card_ids,
// mel_pg_parse_card); no DuneSystem, no hd_print there.

// The files of tmp_dir: the job (php_server: the queue, movie_id => type, and the folders of the cards), the progress
// (the pass: its pid, how far, at most every MEL_PG_BG_PROGRESS_S s), the result (the pass: the pairs, the titles of
// none), the output of php-cgi and of timeout.
define('MEL_PG_BG_JOB', 'lib_map_job.json');
define('MEL_PG_BG_PROG', 'lib_map_prog.json');
define('MEL_PG_BG_RES', 'lib_map_res.json');
define('MEL_PG_BG_OUT', 'lib_map.log');
// The output is removed at a start past this size.
define('MEL_PG_BG_OUT_MAX', 65536);
// The pass stops itself past this many s (its result with what it read; the rest at the next start), killed past
// MEL_PG_BG_KILL_S (timeout of the command): a pass of ~3000 cards takes ~30 s (device r25: 8-12 ms a card).
if (!defined('MEL_PG_BG_WORK_S'))
    define('MEL_PG_BG_WORK_S', 240);
define('MEL_PG_BG_KILL_S', 300);
define('MEL_PG_BG_PROGRESS_S', 1.0);
// A pass is running while its progress is younger than this many s or its pid is alive; with no progress, this long
// after its start. Past it, with no result: it died (killed by the timeout, or no start at all).
define('MEL_PG_BG_STALE_S', 10);
// A bigger result is not read.
define('MEL_PG_BG_RES_MAX', 8000000);
// Lines of mel_log of a pass (a preg error of the seasons, mel_pg_parse_seasons) in its result, at most.
define('MEL_PG_BG_LOG_N', 5);
// php-cgi of the firmware (as the CGI of the settings page, www/cgi-bin/settings), and the folder of nice and timeout
// (toybox; not in $FS_PREFIX/bin). Tests may define them first.
if (!defined('MEL_PG_BG_PHP'))
    define('MEL_PG_BG_PHP', getenv('FS_PREFIX') . '/firmware_ext/php/php-cgi');
if (!defined('MEL_PG_BG_BIN'))
    define('MEL_PG_BG_BIN', '/system/bin');
// The script of the pass and the folder of its php.ini (max_execution_time 0: php-cgi has 30 s by default).
define('MEL_PG_BG_DIR', dirname(__FILE__) . '/bg');
define('MEL_PG_BG_SCRIPT', 'lib_map.php');

class MelPgBg
{
    // Why the pass is not used in this php_server: null not known yet, '' it is (mel_pg_bg_why); 'died' after a pass
    // that died (mel_pg_bg_died).
    /** @var string|null */
    public static $why = null;
    // When this php_server started the pass (time()), 0 none.
    /** @var int */
    public static $start = 0;
}

// --- php_server.

// tmp_dir, '' none.
/**
 * @return string
 */
function mel_pg_bg_tmp()
{
    return isset(DuneSystem::$properties['tmp_dir_path']) ? rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
}

// The titles of the library at an opening of a screen of rows (r24, r25) or at a tick of the main one (r25): the result
// of a pass taken (mel_pg_bg_take); a pass running - nothing; else, with a queue (mel_pg_lib_queue), a pass started
// for it (mel_pg_bg_start). With no pass (mel_pg_bg_why, a pass that died, its start failed): the step of $ms as before
// (mel_pg_lib_step). No queue: nothing looked at but the files of tmp_dir.
/**
 * @param float $ms
 * @return void
 */
function mel_pg_lib_work($ms)
{
    if (MelPgBg::$why === null || MelPgBg::$why === '')
    {
        $st = mel_pg_bg_state();
        if ($st === 'running')
            return;
        if ($st === 'done')
            mel_pg_bg_take();
        else if ($st === 'died')
            mel_pg_bg_died();
    }
    $q = mel_pg_lib_queue();
    if (!$q)
        return;
    if (mel_pg_bg_why() === '' && mel_pg_bg_start($q))
        return;
    mel_pg_lib_step($ms, $q);
}

// Can php_server start the pass: '' yes, else why not (no exec, no tmp_dir, no php-cgi, no script). Looked at once in
// php_server; a line of the log of why not.
/**
 * @return string
 */
function mel_pg_bg_why()
{
    if (MelPgBg::$why !== null)
        return MelPgBg::$why;
    $tmp = mel_pg_bg_tmp();
    $off = array_map('trim', explode(',', strval(ini_get('disable_functions'))));
    if (!function_exists('exec') || in_array('exec', $off, true))
        $why = 'no exec';
    else if ($tmp === '' || (!is_dir($tmp) && !mkdir($tmp, 0755, true)))
        $why = 'no tmp_dir';
    else if (!is_file(MEL_PG_BG_PHP))
        $why = 'no php-cgi';
    else if (!is_file(MEL_PG_BG_DIR . '/' . MEL_PG_BG_SCRIPT) || !is_file(MEL_PG_BG_DIR . '/php.ini'))
        $why = 'no script';
    else
        $why = '';
    MelPgBg::$why = $why;
    if ($why !== '')
        mel_log("library: bg map: off, $why; the step instead");
    return $why;
}

// The pass now: 'done' (its result there), 'running', 'died' (its progress old and its pid gone, or no progress
// MEL_PG_BG_STALE_S after its start), 'idle' (none).
/**
 * @return string
 */
function mel_pg_bg_state()
{
    $tmp = mel_pg_bg_tmp();
    if ($tmp === '')
        return 'idle';
    clearstatcache();
    if (is_file("$tmp/" . MEL_PG_BG_RES))
        return 'done';
    $prog = "$tmp/" . MEL_PG_BG_PROG;
    if (is_file($prog))
    {
        $age = time() - intval(filemtime($prog));
        if ($age >= 0 && $age < MEL_PG_BG_STALE_S)
            return 'running';
        $p = json_decode(strval(file_get_contents($prog)), true);
        return is_array($p) && isset($p['pid']) && is_int($p['pid']) && mel_pg_bg_alive($p['pid']) ? 'running' : 'died';
    }
    if (MelPgBg::$start === 0)
        return 'idle';
    $ago = time() - MelPgBg::$start;
    return $ago >= 0 && $ago < MEL_PG_BG_STALE_S ? 'running' : 'died';
}

// Is the pass of pid $pid alive: its /proc of the script (its own uid: hidepid=2 hides only the processes of others).
/**
 * @param int $pid
 * @return bool
 */
function mel_pg_bg_alive($pid)
{
    $f = "/proc/$pid/cmdline";
    return $pid > 0 && is_file($f) && strpos(strval(file_get_contents($f)), MEL_PG_BG_SCRIPT) !== false;
}

// The folders of the cards of the shell for the pass: with FS_PREFIX in front first (php-cgi may not add it as PHP of
// php_server does), then as they are (MEL_PG_SHELL_DIR, MEL_PG_SHELL_DIR2: the order of mel_pg_shell_path).
/**
 * @return list<string>
 */
function mel_pg_bg_dirs()
{
    $fs = strval(getenv('FS_PREFIX'));
    $out = array();
    foreach ($fs !== '' ? array(MEL_PG_SHELL_DIR, MEL_PG_SHELL_DIR2) : array() as $d)
        $out[] = $fs . $d;
    foreach (array(MEL_PG_SHELL_DIR, MEL_PG_SHELL_DIR2) as $d)
        $out[] = $d;
    return array_values(array_unique($out));
}

// The command of the pass (exec of mel_pg_bg_start): nice 19 (php_server is at -10, its child too) and timeout KILL of
// the folder $bin, each only when there; the paths in the environment (escapeshellarg of PHP 5.3 in the locale C drops
// the bytes of a Cyrillic path); stdin, stdout and stderr redirected and one '&', no $! nor $(...): exec does not wait.
/**
 * @param string $bin
 * @return string
 */
function mel_pg_bg_cmd($bin = MEL_PG_BG_BIN)
{
    return 'X=Y ' . (is_file("$bin/nice") ? "$bin/nice -n 19 " : '') .
        (is_file("$bin/timeout") ? "$bin/timeout -s KILL " . MEL_PG_BG_KILL_S . ' ' : '') .
        '"$MEL_BG_PHP" -q -c "$MEL_BG_INI" "$MEL_BG_SCRIPT" >>"$MEL_BG_OUT" 2>&1 </dev/null &';
}

// The pass of the queue $q (mel_pg_lib_queue) started: the files of the last one out, the job written, exec of
// mel_pg_bg_cmd; a line of the log: the titles, the ms of the start, no nice or timeout. Not written, exec failed: a
// line, no pass in this php_server (MelPgBg::$why). -> started.
/**
 * @param array<string, string> $q
 * @return bool
 */
function mel_pg_bg_start($q)
{
    $t = microtime(true);
    $tmp = mel_pg_bg_tmp();
    foreach (array(MEL_PG_BG_RES, MEL_PG_BG_PROG) as $f)
    {
        if (is_file("$tmp/$f"))
            unlink("$tmp/$f");
    }
    $out = "$tmp/" . MEL_PG_BG_OUT;
    if (is_file($out) && filesize($out) > MEL_PG_BG_OUT_MAX)
        unlink($out);
    $job = json_encode(array('v' => 1, 'dirs' => mel_pg_bg_dirs(), 'q' => $q));
    if (!is_string($job) || !mel_write_file("$tmp/" . MEL_PG_BG_JOB, $job))
    {
        MelPgBg::$why = 'no job file';
        mel_log('library: bg map: off, the job not written; the step instead');
        return false;
    }
    $env = array('MEL_BG_TMP' => $tmp, 'MEL_BG_PHP' => MEL_PG_BG_PHP, 'MEL_BG_INI' => MEL_PG_BG_DIR,
        'MEL_BG_SCRIPT' => MEL_PG_BG_DIR . '/' . MEL_PG_BG_SCRIPT, 'MEL_BG_OUT' => $out);
    foreach ($env as $k => $v)
        putenv("$k=$v");
    $lines = array();
    $rc = -1;
    exec(mel_pg_bg_cmd(), $lines, $rc);
    foreach (array_keys($env) as $k)
        putenv($k);
    if ($rc !== 0)
    {
        MelPgBg::$why = 'exec failed';
        mel_log("library: bg map: off, exec rc $rc; the step instead");
        return false;
    }
    MelPgBg::$start = time();
    $no = array();
    foreach (array('nice', 'timeout') as $b)
    {
        if (!is_file(MEL_PG_BG_BIN . "/$b"))
            $no[] = "no $b";
    }
    mel_log(sprintf('library: bg map: start %d titles, %.0f ms%s', count($q), (microtime(true) - $t) * 1000,
        $no ? ', ' . implode(', ', $no) : ''));
    return true;
}

// The result of the pass taken, its files out: the pairs (mel_pg_link_put, as the step: one save, MelPgLinks::$rev
// once), the titles of none and of a pair not taken in MelPgLinks::$lib_seen; a line of the log: read, pairs, none, the
// ms of the pass (and where it stopped short), and its own lines. Not read (too big, not JSON, not of the pass): a line,
// nothing taken. -> the pairs taken.
/**
 * @return int
 */
function mel_pg_bg_take()
{
    $tmp = mel_pg_bg_tmp();
    $f = "$tmp/" . MEL_PG_BG_RES;
    $size = is_file($f) ? filesize($f) : 0;
    $j = $size > 0 && $size <= MEL_PG_BG_RES_MAX ? json_decode(strval(file_get_contents($f)), true) : null;
    foreach (array(MEL_PG_BG_RES, MEL_PG_BG_PROG, MEL_PG_BG_JOB) as $x)
    {
        if (is_file("$tmp/$x"))
            unlink("$tmp/$x");
    }
    MelPgBg::$start = 0;
    if (!is_array($j) || !isset($j['v']) || $j['v'] !== 1 || !isset($j['pairs'], $j['none']) || !is_array($j['pairs']) ||
        !is_array($j['none']))
    {
        mel_log(sprintf('library: bg map: a result not read, %d B', $size));
        return 0;
    }
    if (isset($j['err']) && is_string($j['err']) && $j['err'] !== '')
    {
        MelPgBg::$why = 'failed';
        mel_log('library: bg map: off, ' . substr($j['err'], 0, 80) . '; the step instead');
        return 0;
    }
    $found = 0;
    $none = 0;
    $now = time();
    foreach ($j['pairs'] as $mid => $l)
    {
        $mid = strval($mid);
        if (mel_pg_link_put($mid, $l, false, false, false))
            $found++;
        else
        {
            MelPgLinks::$lib_seen[$mid] = $now;
            $none++;
        }
    }
    foreach ($j['none'] as $mid)
    {
        if (is_string($mid) && $mid !== '')
        {
            MelPgLinks::$lib_seen[$mid] = $now;
            $none++;
        }
    }
    if ($found)
    {
        mel_pg_rev_build();
        mel_pg_links_save();
    }
    $int = array();
    foreach (array('read', 'ms', 'done', 'total') as $k)
        $int[$k] = isset($j[$k]) && is_int($j[$k]) ? $j[$k] : 0;
    $stop = isset($j['stop']) && $j['stop'] === 'time' ?
        sprintf('; stopped at %d s, %d/%d', MEL_PG_BG_WORK_S, $int['done'], $int['total']) : '';
    mel_log(sprintf('progress: library cards (bg): %d read, %d pairs, %d none, %d ms%s', $int['read'], $found, $none,
        $int['ms'], $stop));
    foreach (isset($j['log']) && is_array($j['log']) ? array_slice($j['log'], 0, MEL_PG_BG_LOG_N) : array() as $line)
    {
        if (is_string($line))
            mel_log('library: bg map: ' . substr($line, 0, 200));
    }
    return $found;
}

// The pass died (mel_pg_bg_state): a line (how long it ran, how far, by its progress), its files out, no pass in this
// php_server (the step instead: not the same titles again and again).
/**
 * @return void
 */
function mel_pg_bg_died()
{
    $tmp = mel_pg_bg_tmp();
    $prog = "$tmp/" . MEL_PG_BG_PROG;
    $p = is_file($prog) ? json_decode(strval(file_get_contents($prog)), true) : null;
    foreach (array(MEL_PG_BG_PROG, MEL_PG_BG_JOB) as $x)
    {
        if (is_file("$tmp/$x"))
            unlink("$tmp/$x");
    }
    MelPgBg::$start = 0;
    MelPgBg::$why = 'died';
    $n = array();
    foreach (array('s', 'done', 'total') as $k)
        $n[$k] = is_array($p) && isset($p[$k]) && is_int($p[$k]) ? $p[$k] : 0;
    mel_log(is_array($p) ? sprintf('library: bg map: died after %d s, %d/%d (killed by the timeout?); the step instead',
        $n['s'], $n['done'], $n['total']) : 'library: bg map: died, no progress; the step instead');
}

// --- The pass (php-cgi, bg/lib_map.php).

// $data to $path: a temp file of 0600 next to it, then rename (atomic for php_server). -> written.
/**
 * @param string $path
 * @param string $data
 * @return bool
 */
function mel_pg_bg_put($path, $data)
{
    $part = $path . '.' . getmypid() . '.part';
    $ok = file_put_contents($part, $data) === strlen($data) && chmod($part, 0600) && rename($part, $path);
    if (!$ok && is_file($part))
        unlink($part);
    return $ok;
}

// The pass over the job of tmp_dir $tmp (mel_pg_bg_start): for each title of its queue, in order, its card of the shell
// of the first of its folders that has it (no bigger than MEL_PG_SHELL_MAX_BYTES), its pair as the step reads it
// (mel_pg_card_ids, of a title of both types mel_pg_parse_card; the type of the index when the card has none), else
// none (no card, too big, no ids). The progress at its start and every MEL_PG_BG_PROGRESS_S s; past $limit s (one card
// at least) it stops, the result with what it read. The result: v, pairs (movie_id => pair), none (movie_ids), read,
// done, total, ms, stop ('time' or ''), log (lines of mel_log of the functions of links.php), err (a bad job). ->
// the result written.
/**
 * @param string $tmp
 * @param float $limit
 * @return bool
 */
function mel_pg_bg_run($tmp, $limit = MEL_PG_BG_WORK_S)
{
    if (function_exists('set_time_limit'))
        set_time_limit(0);
    $t0 = microtime(true);
    if ($tmp === '' || !is_dir($tmp))
        return false;
    $f = "$tmp/" . MEL_PG_BG_JOB;
    $job = is_file($f) ? json_decode(strval(file_get_contents($f)), true) : null;
    $res = array('v' => 1, 'pairs' => array(), 'none' => array(), 'read' => 0, 'done' => 0, 'total' => 0, 'ms' => 0,
        'stop' => '', 'log' => array(), 'err' => '');
    if (!is_array($job) || !isset($job['v'], $job['q'], $job['dirs']) || $job['v'] !== 1 || !is_array($job['q']) ||
        !is_array($job['dirs']))
    {
        $res['err'] = 'a bad job';
        return mel_pg_bg_put("$tmp/" . MEL_PG_BG_RES, strval(json_encode($res)));
    }
    $dirs = array();
    foreach ($job['dirs'] as $d)
    {
        if (is_string($d) && $d !== '' && is_dir($d))
            $dirs[] = $d;
    }
    $prog = "$tmp/" . MEL_PG_BG_PROG;
    $res['total'] = count($job['q']);
    $p = array('pid' => getmypid(), 's' => 0, 'done' => 0, 'total' => $res['total']);
    mel_pg_bg_put($prog, strval(json_encode($p)));
    $last = microtime(true);
    $pairs = array();
    foreach ($job['q'] as $mid => $type)
    {
        $mid = strval($mid);
        $now = microtime(true);
        if ($res['done'] && $now - $t0 >= $limit)
        {
            $res['stop'] = 'time';
            break;
        }
        if ($now - $last >= MEL_PG_BG_PROGRESS_S)
        {
            $last = $now;
            $p['s'] = intval($now - $t0);
            $p['done'] = $res['done'];
            mel_pg_bg_put($prog, strval(json_encode($p)));
        }
        $type = $type === 'movie' || $type === 'series' ? $type : '';
        $text = '';
        foreach (preg_match('/^[0-9a-f]{24}$/D', $mid) ? $dirs : array() as $d)
        {
            $path = "$d/$mid.json";
            if (!is_file($path))
                continue;
            $size = filesize($path);
            $text = $size > 0 && $size <= MEL_PG_SHELL_MAX_BYTES ? strval(file_get_contents($path)) : '';
            break;
        }
        $l = null;
        if ($text !== '')
        {
            $res['read']++;
            $l = $type !== '' ? mel_pg_card_ids($text) : mel_pg_parse_card($text);
        }
        unset($text);
        if ($l && !isset($l['type']) && $type !== '')
            $l['type'] = $type;
        if ($l)
            $pairs[$mid] = $l;
        else
            $res['none'][] = $mid;
        $res['done']++;
    }
    // An object even when empty: the movie_ids are the keys.
    $res['pairs'] = (object) $pairs;
    $res['ms'] = intval(round((microtime(true) - $t0) * 1000));
    $res['log'] = isset($GLOBALS['MEL_BG_LOG']) && is_array($GLOBALS['MEL_BG_LOG']) ? $GLOBALS['MEL_BG_LOG'] : array();
    $data = json_encode($res);
    return is_string($data) && mel_pg_bg_put("$tmp/" . MEL_PG_BG_RES, $data);
}
