<?php
// The section "На TorrServer": the torrents kept by TorrServer (POST /torrents list), as they are - no title of
// Melange, no card. Here the data only: the read of the list, its entries, their video files, the removal of one (rem),
// the uid of a start; the
// run of a start ("get", its step, its playlist); the screen of files of a pack (its groups of
// folders, its lines, the marks of TorrServer "viewed", the file it opens on); no firmware API. Reads Mel::$settings,
// writes MelTsl. Functions are global; the require order is in main.php.

// The lines of the section at most; the read of the list stops at as many entries kept. 1008 entries of 40 files hold
// ~36 MB in a 64-bit PHP 5.3, about half of it on the Dune (MEL_TSL_MEM lets them in), and their read and the build of
// the screen stay within MEL_TSL_LIST_S.
define('MEL_TSL_MAX', 1008);
// The total s of the request of the list (its connect 1 s, mel_ts_call: TorrServer down fails in 1 s); a list cut by it
// shows what came. The parse runs in the callback of curl (MelTslScan::feed), inside this time, and on the ARM of the Dune
// it is ~2x slower than on a PC: 10 s let a long list in whole.
define('MEL_TSL_LIST_S', 10);
// memory_get_usage (bytes) past which the read of the list stops on the entries it has (MelTsl::$mem_max). memory_limit
// of php_server is 128 MB, and a fatal error there takes the whole plugin down. 1008 entries of 40 files (6.5 KB of
// text each, a usual one) hold 36 MB in a 64-bit PHP 5.3, about half of it in the 32-bit one of the Dune: 48 MB lets
// them in, and the 80 MB over it cover a json_decode of one more entry (~11 times its text: a pack of 10 000 files,
// ~1.5 MB, ~17 MB for a moment), the screen of the tiles and the other screens of the plugin after it.
define('MEL_TSL_MEM', 50331648);
// The text of one entry at most (bytes): a longer one (a pack of 50 000 files and more, 6-10 MB) is skipped as not JSON,
// its text not kept - its json_decode would take ~11 times as much, over the memory_limit of php_server. 2 MB: a pack of
// ~13 000 files, ~23 MB for a moment, within the 80 MB over MEL_TSL_MEM.
define('MEL_TSL_ENTRY_MAX', 2097152);
// The total s of the request of the marks "viewed" of a torrent (the screen of files).
define('MEL_TSL_VIEWED_S', 2);
// The total s of the request "rem" (mel_tsl_rem): TorrServer answers it once the torrent is closed, up to 5 s for an active
// one (RemTorrent of its torr/apihelper.go), its cache off the disk and DLNA restarted; no answer by then - one "get".
define('MEL_TSL_REM_S', 8);
// The prefix of the uid of a start from the section: "aiots:<hash>:<file id>". Not "aio:": that is a release of
// Melange with a card (mel_cp_ours, mel_wh_ours_all, mel_wh_uid).
define('MEL_TSL_UID', 'aiots');

class MelTsl
{
    // The entries of the list last read, hash => entry in the order of the section; null: none read.
    /** @var array<string, MelTsEntry>|null */
    public static $list = null;
    // The files of file_stats of torrents whose entry knew none (a record of Lampa), hash => files: a read of the list
    // anew (mel_tsl_load) keeps them (mel_tsl_files_of).
    /** @var array<string, list<MelTsFile>> */
    public static $files = array();
    // The guard of memory of the read of the list (MEL_TSL_MEM; tests set less).
    /** @var int */
    public static $mem_max = MEL_TSL_MEM;
}

// The read of the reply of "list" as it comes, chunk by chunk (feed; mel_ts_call with a sink): TorrServer has no pages,
// its list is one JSON array, newest first (timestamp). A scanner keeps the depth of the brackets and whether it is in a
// string (a quote after an odd run of backslashes is in it: "data" is JSON in a string); each element of the array, once
// closed, is decoded alone (mel_ts_decode) and its text dropped: the whole reply is never kept; one over
// MEL_TSL_ENTRY_MAX is scanned to its end, its text not kept, and skipped (counted apart). An element is an object or an array;
// a scalar between them is skipped, not counted. No firmware API, no request.
class MelTslScan
{
    // The text not taken yet: from the start of the open element (or '' between them).
    /** @var string */
    public $buf = '';
    // Where the scan goes on in $buf; $from: the start of the open element there, -1 none.
    /** @var int */
    public $pos = 0;
    /** @var int */
    public $from = -1;
    // The open element is over MEL_TSL_ENTRY_MAX: its text dropped ($from -1), the scan goes on to its end.
    /** @var bool */
    public $long = false;
    // The brackets open: 1 - in the array, 2 and more - in an element.
    /** @var int */
    public $depth = 0;
    /** @var bool */
    public $str = false;
    // start - before "[", list - in the array, end - its "]" read; not - the reply is not an array.
    /** @var string */
    public $state = 'start';
    // Why the read stopped early: '' not, 'max' - MEL_TSL_MAX entries kept, 'memory' - over $mem (memory_get_usage).
    /** @var string */
    public $stop = '';
    /** @var int */
    public $mem;
    /** @var int */
    public $bytes = 0;
    // The elements read, of them not JSON, over MEL_TSL_ENTRY_MAX (not decoded).
    /** @var int */
    public $n = 0;
    /** @var int */
    public $bad = 0;
    /** @var int */
    public $big = 0;
    // ms of the scan and of the entries (in feed).
    /** @var float */
    public $ms = 0.0;
    // The entries kept: array(timestamp, the index in the reply, the entry); the hashes of them.
    /** @var list<array{int, int, MelTsEntry}> */
    public $out = array();
    /** @var array<string, bool> */
    public $seen = array();

    /**
     * @param int $mem
     */
    public function __construct($mem)
    {
        $this->mem = $mem;
    }

    // A chunk of the reply -> go on reading (false: the read is over - the end of the array, not an array, a stop; spaces
    // only before "[" - on).
    /**
     * @param string $chunk
     * @return bool
     */
    public function feed($chunk)
    {
        $t = microtime(true);
        $this->bytes += strlen($chunk);
        if ($this->state === 'list' || $this->state === 'start')
        {
            $this->buf .= $chunk;
            $this->scan();
        }
        $this->ms += (microtime(true) - $t) * 1000;
        return ($this->state === 'list' || $this->state === 'start') && $this->stop === '';
    }

    // Why the read ended, after its last chunk: 'not' - not a list (no "[", not an array, every element not JSON, those
    // over MEL_TSL_ENTRY_MAX apart); 'max',
    // 'memory' - stopped there ($stop); 'open' - no end of the array (a reply cut); 'end' - its "]".
    /**
     * @return string
     */
    public function verdict()
    {
        if ($this->state === 'start' || $this->state === 'not' || ($this->bad > 0 && $this->bad === $this->n - $this->big))
            return 'not';
        if ($this->stop !== '')
            return $this->stop;
        return $this->state === 'list' ? 'open' : 'end';
    }

    /**
     * @return void
     */
    private function scan()
    {
        $b = $this->buf;
        $len = strlen($b);
        $i = $this->pos;
        while ($i < $len && $this->stop === '')
        {
            if ($this->str)
            {
                // The closing quote: the first not escaped - a backslash takes the byte after it, whatever it is. Not a
                // regex: PCRE 8.11 of the Dune recurses on its stack over a run of 40 000 backslashes and more and takes
                // php_server down.
                $i += strcspn($b, "\\\"", $i);
                if ($i >= $len)
                    break;
                if ($b[$i] === '\\')
                {
                    // Its byte in the next chunk: on from the backslash.
                    if ($i + 1 >= $len)
                        break;
                    $i += 2;
                    continue;
                }
                $i++;
                $this->str = false;
                continue;
            }
            if ($this->state === 'start')
            {
                $i += strspn($b, " \t\r\n", $i);
                if ($i >= $len)
                    break;
                if ($b[$i] !== '[')
                {
                    $this->state = 'not';
                    break;
                }
                $this->state = 'list';
                $this->depth = 1;
                $i++;
                continue;
            }
            $i += strcspn($b, '"{}[]', $i);
            if ($i >= $len)
                break;
            $c = $b[$i++];
            if ($c === '"')
                $this->str = true;
            else if ($c === '{' || $c === '[')
            {
                if ($this->depth === 1)
                    $this->from = $i - 1;
                $this->depth++;
            }
            else if (--$this->depth === 1)
            {
                // Over MEL_TSL_ENTRY_MAX (its text dropped, or whole in this chunk): not decoded, skipped.
                if ($this->long || $i - $this->from > MEL_TSL_ENTRY_MAX)
                {
                    $this->n++;
                    $this->big++;
                }
                else
                    $this->take(mel_ts_decode(substr($b, $this->from, $i - $this->from)));
                $this->from = -1;
                $this->long = false;
            }
            else if ($this->depth === 0)
            {
                $this->state = 'end';
                break;
            }
        }
        if ($this->from >= 0 && $len - $this->from > MEL_TSL_ENTRY_MAX)
        {
            $this->from = -1;
            $this->long = true;
        }
        // The open element kept, nothing before it (over MEL_TSL_ENTRY_MAX: nothing scanned); between elements nothing
        // scanned.
        $keep = $this->from >= 0 ? $this->from : $i;
        $this->buf = $keep > 0 ? (string) substr($b, $keep) : $b;
        $this->pos = $i - $keep;
        if ($this->from >= 0)
            $this->from = 0;
        // Past "]" nothing more to read: no stop.
        if ($this->stop === '' && $this->state !== 'end' && memory_get_usage() > $this->mem)
            $this->stop = 'memory';
    }

    // Element $x of the array decoded (null: not JSON) -> its entry kept (mel_tsl_entry: a hash of 40 hex and the torrent
    // saved in the base (stat 5, TorrServer InDB) or with data - an active torrent never saved (stat 3, no data) is not;
    // a hash seen before is not), the read stopped at MEL_TSL_MAX kept or over $mem after an entry kept (one not kept
    // takes no memory: a list of active torrents to its "]").
    /**
     * @param array<mixed>|null $x
     * @return void
     */
    private function take($x)
    {
        $k = $this->n++;
        if ($x === null)
        {
            $this->bad++;
            return;
        }
        $e = mel_tsl_entry($x);
        if ($e === null || isset($this->seen[$e['hash']]))
            return;
        $this->seen[$e['hash']] = true;
        $this->out[] = array($e['added'], $k, $e);
        if (count($this->out) >= MEL_TSL_MAX)
            $this->stop = 'max';
        else if (memory_get_usage() > $this->mem)
            $this->stop = 'memory';
    }

    // The entries kept, newest first; a tie in the order of the reply.
    /**
     * @return list<MelTsEntry>
     */
    public function entries()
    {
        $out = $this->out;
        // usort of PHP 5.3 is not stable: the order of the reply breaks a tie.
        usort($out, 'mel_tsl_cmp');
        $list = array();
        foreach ($out as $o)
            $list[] = $o[2];
        return $list;
    }
}
// The list of the TorrServer of the settings -> array(entries in the order of the section or null on an error,
// the key of the error ('' none), its detail); kept in MelTsl when read, none kept on an error. Every open of the
// section reads it anew, as it comes (MelTslScan): up to MEL_TSL_MAX entries kept or MelTsl::$mem_max of memory, the
// newest (TorrServer sends them first); cut (its time out, a break) - the entries read, none - ts_err_down. Not an
// array, every element not JSON, cut with no entry, the guard of memory before any entry: tsl_err_bad; an element not
// JSON or over MEL_TSL_ENTRY_MAX is skipped.
/**
 * @return array{list<MelTsEntry>|null, string, string}
 */
function mel_tsl_load()
{
    MelTsl::$list = null;
    if (Mel::$settings['no_torrents'])
    {
        mel_log('ts section: list: no torrents');
        return array(null, 'ts_err_off', '');
    }
    $url = Mel::$settings['ts'] . '/torrents';
    $scan = new MelTslScan(MelTsl::$mem_max);
    $t = microtime(true);
    $r = mel_ts_call($url, (string) json_encode(array('action' => 'list')), MEL_TSL_LIST_S, Mel::$settings['ts_auth'],
        array($scan, 'feed'));
    $ms = (microtime(true) - $t) * 1000 - $scan->ms;
    $head = sprintf('ts section: list %s: ', $url);
    $cut = $r['err'] !== '';
    if ($cut && ($r['code'] !== 200 || !$scan->out))
    {
        mel_log($head . sprintf('%s, %d bytes, %.0f ms', $r['err'], $scan->bytes, $ms));
        return array(null, 'ts_err_down', $r['err']);
    }
    if ($r['code'] !== 200)
    {
        mel_log($head . sprintf('HTTP %d, %.0f ms', $r['code'], $ms));
        return $r['code'] === 401 ? array(null, mel_ts_auth_err(), '') : array(null, 'tsl_err_bad', "HTTP {$r['code']}");
    }
    $line = $head . sprintf('HTTP 200, %d bytes, %.0f ms, parse %.0f ms, ', $scan->bytes, $ms, $scan->ms);
    $v = $scan->verdict();
    if ($v === 'not' || ($v === 'open' && !$cut && !$scan->out))
    {
        mel_log($line . sprintf('%d entries, %d bad, not a list', $scan->n, $scan->bad));
        return array(null, 'tsl_err_bad', 'not a list');
    }
    $why = array('max' => 'stopped at ' . MEL_TSL_MAX, 'memory' => sprintf('stopped at memory %.1f MB',
        MelTsl::$mem_max / 1048576));
    // Stopped by the guard before any entry: nothing to show, an error - not an empty section.
    if ($v === 'memory' && !$scan->out)
    {
        mel_log($line . sprintf('%d entries, %d bad, %s, no entry', $scan->n, $scan->bad, $why['memory']));
        return array(null, 'tsl_err_bad', 'memory');
    }
    if (isset($why[$v]))
        $how = $why[$v];
    else
        $how = $cut ? "cut: {$r['err']}" : ($v === 'open' ? 'cut: no end of the list' : 'the end');
    $list = $scan->entries();
    mel_log($line . sprintf('%d entries, %d kept, ', $scan->n, count($list)) . ($scan->bad ? "{$scan->bad} bad, " : '') .
        ($scan->big ? "{$scan->big} too long, " : '') .
        sprintf('%s, peak %.1f MB', $how, memory_get_peak_usage() / 1048576));
    $keep = array();
    foreach ($list as $e)
        $keep[$e['hash']] = $e;
    MelTsl::$list = $keep;
    return array($list, '', '');
}

// "rem" of torrent $hash on the TorrServer of the settings (POP UP of the section, owner 24.11.2026) -> array(the key of
// the error, '' removed; its detail; the index of its entry in MelTsl::$list before, -1 none there or an error): no
// answer - ts_err_down (the error of curl), 401 - the login (mel_ts_auth_err), another code - tsl_rem_bad ("HTTP
// <code>"). No answer in MEL_TSL_REM_S (curl 28): TorrServer answers once the cache is off its disk and DLNA restarted,
// it may take longer - one "get" of it, 404 (TorrServer has it no more): removed, else ts_err_down. Removed: out of
// MelTsl too (its entry, its files), the order of the rest kept; the history and the positions of Melange ("aiots:")
// stay.
/**
 * @param string $hash
 * @return array{string, string, int}
 */
function mel_tsl_rem($hash)
{
    $url = Mel::$settings['ts'] . '/torrents';
    $r = mel_ts_call($url, (string) json_encode(array('action' => 'rem', 'hash' => $hash)), MEL_TSL_REM_S,
        Mel::$settings['ts_auth']);
    $head = "ts section: rem $hash: ";
    if ($r['err'] !== '' && $r['errno'] === 28)
    {
        list($rg) = mel_ts_get($hash);
        $gone = $rg['err'] === '' && $rg['code'] === 404;
        mel_log($head . $r['err'] . ', get: ' . ($rg['err'] !== '' ? $rg['err'] : "HTTP {$rg['code']}") .
            ($gone ? ', removed' : ''));
        if (!$gone)
            return array('ts_err_down', $r['err'], -1);
    }
    else if ($r['err'] !== '')
    {
        mel_log($head . $r['err']);
        return array('ts_err_down', $r['err'], -1);
    }
    else
    {
        mel_log($head . "HTTP {$r['code']}");
        if ($r['code'] !== 200)
            return $r['code'] === 401 ? array(mel_ts_auth_err(), '', -1) : array('tsl_rem_bad', "HTTP {$r['code']}", -1);
    }
    $i = MelTsl::$list === null ? false : array_search($hash, array_keys(MelTsl::$list), true);
    if ($i !== false)
        unset(MelTsl::$list[$hash]);
    unset(MelTsl::$files[$hash]);
    return array('', '', $i === false ? -1 : $i);
}

/**
 * @param array{int, int, MelTsEntry} $a
 * @param array{int, int, MelTsEntry} $b
 * @return int
 */
function mel_tsl_cmp($a, $b)
{
    return $a[0] !== $b[0] ? ($a[0] > $b[0] ? -1 : 1) : $a[1] - $b[1];
}

// A TorrentStatus of the list -> its entry, null: not kept (MelTslScan). title: the title of TorrServer with
// no prefix of a client ("[NUM] ", "[KT] ", "[LAMPA] " - src num, kt, lampa; '' none), else the name of its
// biggest video file, else the start of its hash; raw_title: as TorrServer has it, one line with no control
// characters. data: the files of TorrServer ({"TorrServer":{"Files":[...]}}, files; ids as of file_stats), a card
// of Lampa ({"lampa":true,"movie":{...}}, lampa), else neither: files $stats (those of file_stats of a "get",
// mel_tsl_got), null - known only by "get".
/**
 * @param array<mixed> $x
 * @param list<MelTsFile>|null $stats
 * @return MelTsEntry|null
 */
function mel_tsl_entry($x, $stats = null)
{
    $hash = strtolower(mel_str($x, 'hash'));
    $data = mel_str($x, 'data');
    if (!preg_match('/^[0-9a-f]{40}$/D', $hash) || (mel_ts_int($x, 'stat') !== 5 && $data === ''))
        return null;
    $d = $data !== '' ? mel_ts_decode($data) : null;
    $files = null;
    $lampa = null;
    $own = mel_arr($d, 'TorrServer');
    if (isset($own['Files']) && is_array($own['Files']))
        $files = mel_ts_files(array('file_stats' => $own['Files']));
    else if (isset($d['lampa']) && $d['lampa'] === true && isset($d['movie']) && is_array($d['movie']))
        $lampa = $d['movie'];
    if ($files === null && $stats)
        $files = $stats;
    $raw = mel_tsl_trim(mel_clean(mel_one_line(mel_str($x, 'title'))));
    $src = '';
    $title = $raw;
    if (preg_match('/^\[(num|kt|lampa)\]/i', $raw, $m))
    {
        $src = strtolower($m[1]);
        $title = mel_tsl_trim(substr($raw, strlen($m[0])));
    }
    if ($title === '')
    {
        $big = null;
        foreach ($files === null ? array() : $files as $f)
        {
            if (mel_ts_video($f['path']) && ($big === null || $f['size'] > $big['size']))
                $big = $f;
        }
        $title = $big !== null ? mel_tsl_file_name($big) : substr($hash, 0, 8);
    }
    $stamp = isset($x['timestamp']) && is_numeric($x['timestamp']) ? intval($x['timestamp']) : 0;
    return array('hash' => $hash, 'title' => $title, 'raw_title' => $raw, 'src' => $src,
        'poster' => mel_str($x, 'poster'), 'category' => strtolower(mel_str($x, 'category')), 'added' => $stamp,
        'size' => mel_num(mel_str($x, 'torrent_size')), 'files' => $files, 'lampa' => $lampa);
}

// $s with no spaces at its ends, no-break ones too.
/**
 * @param string $s
 * @return string
 */
function mel_tsl_trim($s)
{
    return strval(preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/uD', '', $s));
}

// The name of file $f with no folder and no extension: an item of the player, a title.
/**
 * @param MelTsFile $f
 * @return string
 */
function mel_tsl_file_name($f)
{
    $n = mel_ts_basename($f['path']);
    $p = strrpos($n, '.');
    return $p !== false && $p > 0 ? substr($n, 0, $p) : $n;
}

// The number of the video files of $files (no order: the line of the section needs the count alone).
/**
 * @param list<MelTsFile> $files
 * @return int
 */
function mel_tsl_video_count($files)
{
    $n = 0;
    foreach ($files as $f)
    {
        if (mel_ts_video($f['path']))
            $n++;
    }
    return $n;
}

// The video files of $files in the natural order of their paths ("Серия 2" before "Серия 10", the folders of the
// seasons in order): the order of a torrent may be any.
/**
 * @param list<MelTsFile> $files
 * @return list<MelTsFile>
 */
function mel_tsl_videos_of($files)
{
    $out = array();
    foreach ($files as $f)
    {
        if (mel_ts_video($f['path']))
            $out[] = $f;
    }
    usort($out, 'mel_tsl_path_cmp');
    return $out;
}

/**
 * @param MelTsFile $a
 * @param MelTsFile $b
 * @return int
 */
function mel_tsl_path_cmp($a, $b)
{
    $c = strnatcasecmp($a['path'], $b['path']);
    return $c !== 0 ? $c : $a['id'] - $b['id'];
}

// The files of torrent $hash: those of its entry in memory, else those of its file_stats kept (mel_tsl_files_keep); null
// none known. The screen of files and a start read them here.
/**
 * @param string $hash
 * @return list<MelTsFile>|null
 */
function mel_tsl_files_of($hash)
{
    if (isset(MelTsl::$list[$hash]) && MelTsl::$list[$hash]['files'] !== null)
        return MelTsl::$list[$hash]['files'];
    return isset(MelTsl::$files[$hash]) ? MelTsl::$files[$hash] : null;
}

// The files $files of file_stats of torrent $hash kept, if its entry knows none (not in memory too) -> $files.
/**
 * @param string $hash
 * @param list<MelTsFile> $files
 * @return list<MelTsFile>
 */
function mel_tsl_files_keep($hash, $files)
{
    if (!isset(MelTsl::$list[$hash]) || MelTsl::$list[$hash]['files'] === null)
        MelTsl::$files[$hash] = $files;
    return $files;
}

// What OK on a torrent with video files $videos (null: not known) starts: 'movie' - its one video file, 'pack' - the
// screen of its files (two or more: a movie with its sample too), 'none' - no video file, 'unknown' - "get" first.
/**
 * @param list<MelTsFile>|null $videos
 * @return string
 */
function mel_tsl_kind($videos)
{
    if ($videos === null)
        return 'unknown';
    $n = count($videos);
    return $n === 0 ? 'none' : ($n === 1 ? 'movie' : 'pack');
}

// The uid of the history of a start of file $id of torrent $hash from the section.
/**
 * @param string $hash
 * @param int|string $id
 * @return string
 */
function mel_tsl_uid($hash, $id)
{
    return MEL_TSL_UID . ":$hash:$id";
}

// The uid of the history $uid of a start from the section (mel_tsl_uid) -> array(the hash, the file id), null: not one.
/**
 * @param mixed $uid
 * @return array{string, int}|null
 */
function mel_tsl_uid_parse($uid)
{
    if (!is_string($uid) || !preg_match('/^' . MEL_TSL_UID . ':([0-9a-f]{40}):([1-9][0-9]{0,5})$/D', $uid, $m))
        return null;
    return array($m[1], intval($m[2]));
}

// The entry of torrent $hash known by its hash only (a record of "Последнее" not in the list: past MEL_TSL_MAX, an active
// torrent never saved): title '' - a run of it makes it anew of each reply of "get" (mel_tsl_got).
/**
 * @param string $hash
 * @return MelTsEntry
 */
function mel_tsl_bare($hash)
{
    return array('hash' => $hash, 'title' => '', 'raw_title' => '', 'src' => '', 'poster' => '', 'category' => '',
        'added' => 0, 'size' => 0.0, 'files' => null, 'lampa' => null);
}

// The entry of torrent $hash by its TorrentStatus $j of "get" (mel_tsl_bare): as one of the list (mel_tsl_entry: the
// title, the poster of TorrServer), whatever its stat; files those of file_stats when its data has none.
/**
 * @param string $hash
 * @param array<mixed> $j
 * @return MelTsEntry
 */
function mel_tsl_got($hash, $j)
{
    $files = mel_ts_files($j);
    $e = mel_tsl_entry(array_merge($j, array('hash' => $hash, 'stat' => 5)), $files ? $files : null);
    return $e !== null ? $e : mel_tsl_bare($hash);
}

// --- A start from the section (view_ts_play.php shows it): the run of a start, its step, its playlist.

// A new run of a start of file $id of entry $e (0: not known yet, by file_stats) from position $pos (s; 0: from the
// start, the preload first), the texts in $lang. n: the "get" done; b: the n of the start of the preload, -1 none; g:
// the entry known by its hash only (mel_tsl_bare), made anew of each reply of "get".
/**
 * @param MelTsEntry $e
 * @param int $id
 * @param int $pos
 * @param string $lang
 * @return MelTslRun
 */
function mel_tsl_run($e, $id, $pos, $lang)
{
    return array('e' => $e, 'f' => $id, 'pos' => $pos, 'n' => 0, 'b' => -1, 'l' => $lang, 'g' => $e['title'] === '');
}

// File $id among $files, null none.
/**
 * @param list<MelTsFile> $files
 * @param int $id
 * @return MelTsFile|null
 */
function mel_tsl_file_of($files, $id)
{
    foreach ($files as $f)
    {
        if ($f['id'] === $id)
            return $f;
    }
    return null;
}

// One step of run $d, $sec s since its start, after its "get" (n already counted): reply $rg, its TorrentStatus $j ->
// array(what now, the run after it, why (play: the reason; error: the key of its text), the detail of an error, the
// files of file_stats). What now: 'play' - file f of the run; 'preload' - ask the preload of file f, then play or
// wait (mel_tsl_advance); 'files' - two video files and more and no file chosen: the screen of files; 'error'; 'wait'.
// The errors as a start from the list of releases (mel_ts_tick): no answer to the first "get" (later ones wait on),
// 401, 404 (TorrServer has it no more), no file_stats in MEL_TS_WAIT s or MEL_TS_TICKS "get", no video file or not the
// file chosen. No firmware API, no request: the ticks of the waiting dialog run it (mel_tsl_tick).
/**
 * @param MelTslRun $d
 * @param int $sec
 * @param MelTsReply $rg
 * @param array<mixed>|null $j
 * @return array{string, MelTslRun, string, string, list<MelTsFile>}
 */
function mel_tsl_step($d, $sec, $rg, $j)
{
    $files = mel_ts_files($j);
    $out = $sec >= MEL_TS_WAIT || $d['n'] >= MEL_TS_TICKS;
    $err = $rg['err'];
    if ($files)
    {
        $v = mel_tsl_videos_of($files);
        if ($d['f'] === 0)
        {
            $kind = mel_tsl_kind($v);
            if ($kind !== 'movie')
                return array($kind === 'pack' ? 'files' : 'error', $d, $kind === 'pack' ? '' : 'ts_err_no_video', '', $files);
            $d['f'] = $v[0]['id'];
        }
        if (mel_tsl_file_of($v, $d['f']) === null)
            return array('error', $d, 'ts_err_no_video', '', $files);
        if ($d['pos'] > 0)
            return array('play', $d, 'a position', '', $files);
        if ($d['b'] < 0)
            return array($out ? 'play' : 'preload', $d, $out ? 'the time out' : '', '', $files);
        $why = $out ? 'the time out' : mel_ts_buf_done($j, $d['n'] - $d['b']);
        return array($why !== '' ? 'play' : 'wait', $d, $why, '', $files);
    }
    if ($err !== '' && $d['n'] <= 1)
        return array('error', $d, 'ts_err_down', $err, $files);
    if ($err === '' && $rg['code'] === 401)
        return array('error', $d, mel_ts_auth_err(), '', $files);
    if ($err === '' && $rg['code'] === 404)
        return array('error', $d, 'tsl_err_gone', '', $files);
    if ($out)
        return array('error', $d, 'ts_err_timeout', $err, $files);
    return array('wait', $d, '', '', $files);
}

// One "get" of run $d, $sec s since its start, and its step (mel_tsl_step); a preload asked there (mel_ts_preload_file)
// -> play at once or wait for it -> array(what now: play, files, error, wait; the run after it; why; the detail; the
// files of file_stats; the TorrentStatus). A request of 2 s at most, of the preload 0.3 s: a tick holds php_server no
// longer.
/**
 * @param MelTslRun $d
 * @param int $sec
 * @return array{string, MelTslRun, string, string, list<MelTsFile>, array<mixed>|null}
 */
function mel_tsl_advance($d, $sec)
{
    $d['n']++;
    $hash = $d['e']['hash'];
    list($rg, $j) = mel_ts_get($hash);
    if ($d['g'] && $rg['err'] === '' && $rg['code'] === 200 && is_array($j))
        $d['e'] = mel_tsl_got($hash, $j);
    list($what, $d, $why, $detail, $files) = mel_tsl_step($d, $sec, $rg, $j);
    mel_log(sprintf('ts section: %s get %d, %d s: %s, stat %s, peers %d/%d, seeders %d, %d files', $hash, $d['n'], $sec,
        $rg['err'] !== '' ? $rg['err'] : "HTTP {$rg['code']}", is_array($j) && isset($j['stat']) ? json_encode($j['stat']) : '-',
        mel_ts_int($j, 'active_peers'), mel_ts_int($j, 'total_peers'), mel_ts_int($j, 'connected_seeders'), count($files)) .
        ($d['b'] >= 0 ? sprintf(', preloaded %.0f of %.0f', mel_num(mel_str($j, 'preloaded_bytes')),
        mel_num(mel_str($j, 'preload_size'))) : ''));
    if ($what === 'preload')
    {
        $f = mel_tsl_file_of($files, $d['f']);
        $why = $f !== null ? mel_ts_preload_file($hash, $f, $j) : 'no file';
        if ($why !== '')
            $what = 'play';
        else
        {
            $d['b'] = $d['n'];
            $what = 'wait';
            mel_log("ts section: $hash preload of file {$d['f']}, the wait");
        }
    }
    if ($what === 'error')
        mel_log("ts section: $hash -> error $why" . ($detail !== '' ? " ($detail)" : ''));
    return array($what, $d, $why, $detail, $files, $j);
}

// The playlist of a start of file $id of entry $e among $files (those of file_stats, or of its data: the screen of
// files), the streams of TorrServer $base -> array(its items, the index of file $id in them, the video files of the
// torrent), null: $id is not a video file there. The items: the video files in the natural order of their paths
// (mel_tsl_videos_of); over MEL_EPISODES_MAX a window of as many - from file $id on, fewer after it: up to the last.
// An item: the file id, its name (no folder, no extension; of a torrent of one video file the title of the entry), the
// URL of its stream (mel_ts_url), the uid of its history (mel_tsl_uid), its path.
/**
 * @param MelTsEntry $e
 * @param list<MelTsFile> $files
 * @param int $id
 * @param string $base
 * @return array{list<array{id: int, name: string, url: string, uid: string, path: string}>, int, int}|null
 */
function mel_tsl_playlist($e, $files, $id, $base)
{
    $v = mel_tsl_videos_of($files);
    $k = -1;
    foreach ($v as $n => $f)
    {
        if ($f['id'] === $id)
            $k = $n;
    }
    if ($k < 0)
        return null;
    $all = count($v);
    $from = $all > MEL_EPISODES_MAX ? min($k, $all - MEL_EPISODES_MAX) : 0;
    $items = array();
    foreach (array_slice($v, $from, MEL_EPISODES_MAX) as $f)
        $items[] = array('id' => $f['id'], 'name' => $all === 1 ? $e['title'] : mel_tsl_file_name($f),
            'url' => mel_ts_url($base, $e['hash'], $f), 'uid' => mel_tsl_uid($e['hash'], $f['id']), 'path' => $f['path']);
    return array($items, $k - $from, $all);
}

// --- The screen of files of a pack (view_ts_files.php shows it).

// The groups of video files $v (in their order, mel_tsl_videos_of) by folder: the folder of a file with no folder all of
// them share ('' a file there), each run of files of one folder in a row a group -> array(the runs: dir, from (the index
// in $v of its first), n, size (bytes); flat: one folder for all - no lines of groups). Nested folders: dir the whole
// path ("Season 1/Extras"). The order of the paths first: a folder may come as two runs ("S1/a", "S1/b/c", "S1/d").
/**
 * @param list<MelTsFile> $v
 * @return array{list<MelTslRunDir>, bool}
 */
function mel_tsl_runs($v)
{
    $dirs = array();
    $root = null;
    foreach ($v as $f)
    {
        $p = strrpos($f['path'], '/');
        $d = $p === false ? '' : substr($f['path'], 0, $p);
        $dirs[] = $d;
        if ($root === null)
            $root = $d;
        // The longest folder all share: up to the folder over it while it is not one of $d.
        while ($root !== '' && $d !== $root && strpos($d, $root . '/') !== 0)
        {
            $q = strrpos($root, '/');
            $root = $q === false ? '' : substr($root, 0, $q);
        }
    }
    $runs = array();
    $cur = null;
    foreach ($v as $i => $f)
    {
        $d = $dirs[$i] === $root ? '' : ($root === '' ? $dirs[$i] : substr($dirs[$i], strlen((string) $root) + 1));
        if ($cur !== null && $cur['dir'] === $d)
        {
            $cur['n']++;
            $cur['size'] += $f['size'];
            continue;
        }
        if ($cur !== null)
            $runs[] = $cur;
        $cur = array('dir' => $d, 'from' => $i, 'n' => 1, 'size' => $f['size']);
    }
    if ($cur !== null)
        $runs[] = $cur;
    return array($runs, count($runs) < 2);
}

// The lines of the screen of files of runs $runs (mel_tsl_runs; $flat: no lines of groups): the line of each group, the
// lines of its files under it when it is open ($open: the index of a run => true) -> list of array('g', the index of the
// run) or array('f', the index of the file in the video files).
/**
 * @param list<MelTslRunDir> $runs
 * @param bool $flat
 * @param array<int, bool> $open
 * @return list<array{string, int}>
 */
function mel_tsl_lines($runs, $flat, $open)
{
    $out = array();
    foreach ($runs as $k => $r)
    {
        if (!$flat)
            $out[] = array('g', $k);
        if (!$flat && !isset($open[$k]))
            continue;
        for ($i = $r['from']; $i < $r['from'] + $r['n']; $i++)
            $out[] = array('f', $i);
    }
    return $out;
}

// The run of runs $runs (mel_tsl_runs) of the video file of index $i, 0 none.
/**
 * @param list<MelTslRunDir> $runs
 * @param int $i
 * @return int
 */
function mel_tsl_run_of($runs, $i)
{
    foreach ($runs as $k => $r)
    {
        if ($i >= $r['from'] && $i < $r['from'] + $r['n'])
            return $k;
    }
    return 0;
}

// The file the screen of files opens on, its index in video files $v ($seen: the ids marked viewed, mel_tsl_viewed): the
// one after the last marked; the last marked the last file, or none marked: the first not marked; all marked: the first.
/**
 * @param list<MelTsFile> $v
 * @param array<int, bool> $seen
 * @return int
 */
function mel_tsl_next_file($v, $seen)
{
    $last = -1;
    $first = -1;
    foreach ($v as $i => $f)
    {
        if (isset($seen[$f['id']]))
            $last = $i;
        else if ($first < 0)
            $first = $i;
    }
    if ($last >= 0 && $last < count($v) - 1)
        return $last + 1;
    return $first >= 0 ? $first : 0;
}

// The files of torrent $hash marked viewed by TorrServer (POST /viewed {"action":"list"}, the address and the login of the
// list, MEL_TSL_VIEWED_S s) -> their ids => true; an error, not a list, «Без торрентов» - none (a line of the log).
/**
 * @param string $hash
 * @return array<int, bool>
 */
function mel_tsl_viewed($hash)
{
    if (Mel::$settings['no_torrents'])
        return array();
    $t = microtime(true);
    $r = mel_ts_call(Mel::$settings['ts'] . '/viewed', (string) json_encode(array('action' => 'list', 'hash' => $hash)),
        MEL_TSL_VIEWED_S, Mel::$settings['ts_auth']);
    $head = sprintf('ts section: viewed %s: ', $hash);
    $ms = (microtime(true) - $t) * 1000;
    if ($r['err'] !== '' || $r['code'] !== 200)
    {
        mel_log($head . sprintf('%s, %.0f ms, no marks', $r['err'] !== '' ? $r['err'] : "HTTP {$r['code']}", $ms));
        return array();
    }
    $seen = mel_tsl_viewed_parse($r['body'], $hash);
    mel_log($head . ($seen === null ? 'not a list, no marks' : sprintf('%d marks, %.0f ms', count($seen), $ms)));
    return $seen === null ? array() : $seen;
}

// The body of a reply of /viewed list -> the file ids (file_index, from 1) of torrent $hash in it => true; JSON null
// (TorrServer: none marked) none; null: neither a JSON array nor null.
/**
 * @param string $body
 * @param string $hash
 * @return array<int, bool>|null
 */
function mel_tsl_viewed_parse($body, $hash)
{
    if (trim($body) === 'null')
        return array();
    $j = mel_ts_decode($body);
    if ($j === null || ($j !== array() && array_keys($j) !== range(0, count($j) - 1)))
        return null;
    $out = array();
    foreach ($j as $x)
    {
        if (is_array($x) && strtolower(mel_str($x, 'hash')) === $hash && isset($x['file_index']) && is_int($x['file_index']) &&
            $x['file_index'] > 0)
            $out[$x['file_index']] = true;
    }
    return $out;
}
