<?php
// The section "На TorrServer": the torrents kept by TorrServer (POST /torrents list), as they are - no title of
// Melange, no card. Here the data only: the read of the list, its entries, their video files, the uid of a start;
// no firmware API. Reads Mel::$settings, writes MelTsl. Functions are global; the require order is in main.php.

// The tiles of the section at most: those of a "Show all" screen; the entries past them are logged, not shown.
define('MEL_TSL_MAX', MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS);
// The total s of the request of the list (its connect 1 s, mel_ts_call).
define('MEL_TSL_LIST_S', 3);
// The prefix of the uid of a start from the section: "aiots:<hash>:<file id>". Not "aio:": that is a release of
// Melange with a card (mel_cp_ours, mel_wh_ours_all, mel_wh_uid).
define('MEL_TSL_UID', 'aiots');

class MelTsl
{
    // The entries of the list last read, hash => entry in the order of the section; null: none read.
    /** @var array<string, MelTsEntry>|null */
    public static $list = null;
}

// The list of the TorrServer of the settings -> array(entries in the order of the section or null on an error,
// the key of the error ('' none), its detail); kept in MelTsl when read, none kept on an error. Every open of the
// section reads it anew.
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
    $t = microtime(true);
    $r = mel_ts_call($url, (string) json_encode(array('action' => 'list')), MEL_TSL_LIST_S, Mel::$settings['ts_auth']);
    $ms = (microtime(true) - $t) * 1000;
    $head = sprintf('ts section: list %s: ', $url);
    if ($r['err'] !== '')
    {
        mel_log($head . sprintf('%s, %.0f ms', $r['err'], $ms));
        $big = $r['err'] === MEL_TS_TOO_BIG;
        return array(null, $big ? 'tsl_err_big' : 'ts_err_down', $big ? '' : $r['err']);
    }
    if ($r['code'] !== 200)
    {
        mel_log($head . sprintf('HTTP %d, %.0f ms', $r['code'], $ms));
        return $r['code'] === 401 ? array(null, mel_ts_auth_err(), '') : array(null, 'tsl_err_bad', "HTTP {$r['code']}");
    }
    $t = microtime(true);
    $p = mel_tsl_parse($r['body']);
    $line = $head . sprintf('HTTP 200, %d bytes, %.0f ms, parse %.0f ms, ', strlen($r['body']), $ms,
        (microtime(true) - $t) * 1000);
    if ($p === null)
    {
        mel_log($line . 'not a list');
        return array(null, 'tsl_err_bad', 'not a list');
    }
    mel_log($line . sprintf('%d entries, %d kept', $p[1], count($p[0])) . ($p[2] ? ", {$p[2]} over " . MEL_TSL_MAX : ''));
    $keep = array();
    foreach ($p[0] as $e)
        $keep[$e['hash']] = $e;
    MelTsl::$list = $keep;
    return array($p[0], '', '');
}

// The body of a reply of "list" -> array(the entries kept, newest first, at most MEL_TSL_MAX; the entries of the
// reply; those cut by MEL_TSL_MAX), null: not a JSON array. Kept: a hash of 40 hex and the torrent saved in the
// base (stat 5, TorrServer InDB) or with data: an active torrent never saved (stat 3, no data) is not.
/**
 * @param string $body
 * @return array{list<MelTsEntry>, int, int}|null
 */
function mel_tsl_parse($body)
{
    $j = mel_ts_decode($body);
    if ($j === null || ($j !== array() && array_keys($j) !== range(0, count($j) - 1)))
        return null;
    $out = array();
    $seen = array();
    foreach ($j as $k => $x)
    {
        $e = is_array($x) ? mel_tsl_entry($x) : null;
        if ($e !== null && !isset($seen[$e['hash']]))
        {
            $seen[$e['hash']] = true;
            $out[] = array($e['added'], $k, $e);
        }
    }
    // usort of PHP 5.3 is not stable: the order of the reply breaks a tie.
    usort($out, 'mel_tsl_cmp');
    $list = array();
    foreach (array_slice($out, 0, MEL_TSL_MAX) as $o)
        $list[] = $o[2];
    return array($list, count($j), max(0, count($out) - MEL_TSL_MAX));
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

// A TorrentStatus of the list -> its entry, null: not kept (mel_tsl_parse). title: the title of TorrServer with
// no prefix of a client ("[NUM] ", "[KT] ", "[LAMPA] " - src num, kt, lampa; '' none), else the name of its
// biggest video file, else the start of its hash; raw_title: as TorrServer has it, one line with no control
// characters. data: the files of TorrServer ({"TorrServer":{"Files":[...]}}, files; ids as of file_stats), a card
// of Lampa ({"lampa":true,"movie":{...}}, lampa), else neither: files null - known only by "get".
/**
 * @param array<mixed> $x
 * @return MelTsEntry|null
 */
function mel_tsl_entry($x)
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

// The video files of entry $e (mel_tsl_videos_of), null: its files are not known (no list of them in data).
/**
 * @param MelTsEntry $e
 * @return list<MelTsFile>|null
 */
function mel_tsl_videos($e)
{
    return $e['files'] === null ? null : mel_tsl_videos_of($e['files']);
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
 * @param int $id
 * @return string
 */
function mel_tsl_uid($hash, $id)
{
    return MEL_TSL_UID . ":$hash:$id";
}
