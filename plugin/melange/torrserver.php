<?php
// "Watch via TorrServer" (0.31.0): the magnet of a row to TorrServer MatriX
// (POST /torrents add, save_to_db = ts_save), its file list awaited by a dialog
// with a timer, then vod_play of /stream/<name>?link=<hash>&index=<id>&play:
// the Dune player buffers a TorrServer stream itself. The episodes of the
// pack go to the playlist as such URLs, the player switching them alone, or
// lazy, each with its markers (ts_markers of the settings, aio_play).
// TorrServer drops a torrent nobody reads after its own timeout: no drop/rem.
// Reads Aio (state, settings), writes Aio::$tswait, Aio::$ts_pack. Functions are global; the
// require order is in main.php.

// Dialog ticks: every AIO_TS_TICK_MS a "get"; the file list awaited
// AIO_TS_WAIT s at most (Online movies: 60 ticks of 950 ms), and AIO_TS_TICKS
// ticks at most: the clock of the Dune may jump (NTP).
define('AIO_TS_TICK_MS', 1000);
define('AIO_TS_WAIT', 60);
define('AIO_TS_TICKS', 60);
// TorrServer MatriX answers /echo within a second ("Continue watching" asks
// before it goes there).
/**
 * @return bool
 */
function aio_ts_alive()
{
    $r = aio_ts_call(Aio::$settings['ts'] . '/echo', null, 1);
    $ok = $r['err'] === '' && $r['code'] === 200 && strpos($r['body'], 'MatriX.') === 0;
    aio_log('ts: ' . Aio::$settings['ts'] . ' echo: ' .
        ($ok ? 'MatriX' : ($r['err'] !== '' ? $r['err'] : "HTTP {$r['code']}")));
    return $ok;
}

// A TorrentStatus of TorrServer -> array or null. Sizes over 2 GB are quoted
// first: json_decode of PHP 5.3 on the 32-bit Dune clamps them.
/**
 * @param string $body
 * @return array<mixed>|null
 */
function aio_ts_decode($body)
{
    $body = preg_replace('/("(?:length|torrent_size|loaded_size|preloaded_bytes|preload_size|bytes_[a-z_]+)"\s*:\s*)' .
        '([0-9]{10,})/', '$1"$2"', $body);
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

// "get" of torrent $hash from the TorrServer of the settings, 2 s ->
// array(the reply, its TorrentStatus or null): the waiting dialog and the
// window of TorrServer in the player (ts_info.php).
/**
 * @param string $hash
 * @return array{AioTsReply, array<mixed>|null}
 */
function aio_ts_get($hash)
{
    $rg = aio_ts_call(Aio::$settings['ts'] . '/torrents', (string) json_encode(array('action' => 'get', 'hash' => $hash)), 2,
        Aio::$settings['ts_auth']);
    return array($rg, $rg['err'] === '' && $rg['code'] === 200 ? aio_ts_decode($rg['body']) : null);
}

// file_stats of a TorrentStatus -> list of array('id' => int, 'path' =>
// string, 'size' => float), as TorrServer sorts them (ids from 1).
/**
 * @param array<mixed>|null $j
 * @return list<AioTsFile>
 */
function aio_ts_files($j)
{
    $out = array();
    foreach (aio_arr($j, 'file_stats') as $f)
    {
        $id = is_array($f) && isset($f['id']) && is_int($f['id']) ? $f['id'] : 0;
        $path = aio_str($f, 'path');
        if ($id >= 1 && $path !== '')
            $out[] = array('id' => $id, 'path' => $path, 'size' => aio_num(aio_str($f, 'length')));
    }
    return $out;
}

/**
 * @param string $path
 * @return bool
 */
function aio_ts_video($path)
{
    return preg_match('/\.(mkv|mp4|m4v|avi|mov|wmv|ts|m2ts|mts|mpg|mpeg|vob|webm|flv|divx)$/iD', $path) === 1;
}

/**
 * @param string $path
 * @return string
 */
function aio_ts_basename($path)
{
    $p = strrpos($path, '/');
    return $p === false ? $path : substr($path, $p + 1);
}

/**
 * @param string $path
 * @return string
 */
function aio_ts_dirname($path)
{
    $p = strrpos($path, '/');
    return $p === false ? '' : substr($path, 0, $p);
}

// Season and episode by the name of a file -> array(season or null, episode)
// or null: S01E02, s1.e2, s01ep02, S01E.02, 1x02; else E02 / EP02 / "Серия 2" /
// "2 серия" / " - 07" (season unknown). Only to find a file in a known pack.
// The number leading the name is aio_ts_ep_lead, weaker (aio_ts_eps).
/**
 * @param string $path
 * @return array{int|null, int}|null
 */
function aio_ts_ep($path)
{
    $n = aio_ts_basename($path);
    if (preg_match('/(?<![a-z0-9])s([0-9]{1,2})[ ._\-]?e(?:p(?:isode)?)?([0-9]{1,3})(?![0-9])/i', $n, $m) ||
        preg_match('/(?<![a-z0-9])s([0-9]{1,2})[ ._\-]?e(?:p(?:isode)?)?[ ._\-]([0-9]{2,3})(?![0-9a-z])/i', $n, $m) ||
        preg_match('/(?<![a-z0-9])([0-9]{1,2})x([0-9]{1,3})(?![0-9])/i', $n, $m))
        return array(intval($m[1]), intval($m[2]));
    if (preg_match('/(?<![a-z0-9])ep?(?:isode)?[ ._\-]?([0-9]{1,3})(?![0-9])/i', $n, $m) ||
        preg_match('/(?<![\pL0-9])серия[ ._\-]*([0-9]{1,3})(?![0-9])/iu', $n, $m) ||
        preg_match('/(?<![0-9])([0-9]{1,3})[ ._\-]*серия/iu', $n, $m) ||
        // Anime: "[Group] Title - 07 [1080p].mkv", "Title - 07v2.mkv".
        preg_match('/ - ([0-9]{1,3})(?:v[0-9])?(?=[ ._\[(]|$)/i', $n, $m))
        return array(null, intval($m[1]));
    return null;
}

// The number leading the name: "01. Title.mkv", "01.mkv" (not "2024.",
// "1080p.") -> array(null, episode) or null.
/**
 * @param string $path
 * @return array{null, int}|null
 */
function aio_ts_ep_lead($path)
{
    return preg_match('/^([0-9]{1,3})(?=[ ._\-])/', aio_ts_basename($path), $m) ? array(null, intval($m[1])) : null;
}

// The weakest: in each folder, the number right after the start common to the
// names of its videos: "[Group] Title 01 [1080p].mkv". A folder gives none with
// a letter before the number ("SP01"), a repeated number or a date in a name; a
// file none with a separate number right after it ("5.1"). If any folder of
// videos has a season in the path, only such folders; else the rest, none if a
// number is in two of them.
// -> array(key of $files => array(null, episode)).
/**
 * @param list<AioTsFile> $files
 * @return array<int, array{null, int}>
 */
function aio_ts_eps_prefix($files)
{
    $dirs = array();
    foreach ($files as $k => $f)
    {
        if (aio_ts_video($f['path']))
            $dirs[aio_ts_dirname($f['path'])][$k] = aio_ts_basename($f['path']);
    }
    $season = array();
    $other = array();
    $used = array();
    $twice = false;
    $any_season = false;
    foreach ($dirs as $dir => $names)
    {
        $in_season = aio_ts_dir_season("$dir/", null);
        $any_season = $any_season || $in_season;
        if (count($names) < 2)
            continue;
        // The start common to all the names is that of the least and the greatest.
        $min = $max = reset($names);
        foreach ($names as $n)
        {
            if (strcmp($n, $min) < 0)
                $min = $n;
            if (strcmp($n, $max) > 0)
                $max = $n;
        }
        // Back over the digits: "1" and "12", "01" and "09" are whole numbers.
        $start = strlen(rtrim(substr($min, 0, strspn($min ^ $max, "\0")), '0123456789'));
        if ($start > 0 && preg_match('/[a-z\x80-\xff]/i', $min[$start - 1]))
            continue;
        $eps = array();
        $seen = array();
        foreach ($names as $k => $n)
        {
            if (preg_match('/(?<![0-9])(?:(?:19|20)[0-9]{2}[ ._\-][0-9]{1,2}[ ._\-][0-9]{1,2}|' .
                '[0-9]{1,2}[ ._\-][0-9]{1,2}[ ._\-](?:19|20)[0-9]{2})(?![0-9])/', $n))
                continue 2;
            if (preg_match('/^([0-9]{1,3})(?:v[0-9])?(?![0-9a-z])(?![ ._\-][0-9]{1,2}(?![0-9a-z]))/i', substr($n, $start), $m))
            {
                $e = intval($m[1]);
                if (isset($seen[$e]))
                    continue 2;
                $seen[$e] = true;
                $eps[$k] = array(null, $e);
            }
        }
        if ($in_season)
            $season += $eps;
        else
        {
            $other += $eps;
            $twice = $twice || array_intersect_key($seen, $used);
            $used += $seen;
        }
    }
    return $any_season ? $season : ($twice ? array() : $other);
}

// Episodes of the videos of $files by aio_ts_ep; only when it knows none of
// them, by aio_ts_ep_lead; only when that knows none, by aio_ts_eps_prefix.
// -> array(key of $files => its episode, by one of the last two).
/**
 * @param list<AioTsFile> $files
 * @return array{array<int, array{int|null, int}>, bool}
 */
function aio_ts_eps($files)
{
    $out = array();
    foreach ($files as $k => $f)
    {
        if (aio_ts_video($f['path']) && ($ep = aio_ts_ep($f['path'])))
            $out[$k] = $ep;
    }
    if ($out)
        return array($out, false);
    foreach ($files as $k => $f)
    {
        if (aio_ts_video($f['path']) && ($ep = aio_ts_ep_lead($f['path'])))
            $out[$k] = $ep;
    }
    return array($out ? $out : aio_ts_eps_prefix($files), true);
}

// What starts a mark of a season before its number: "S", "Season ", "Сезон ".
define('AIO_TS_SEASON_MARK', '(?:(?<![a-z0-9])(?:s|season[ ._\-]?)|сезон[ ._\-]*)');

// The marks of a season numbered by the regex $n: "S01", "Season 1", "Сезон 1", "1 сезон".
/**
 * @param string $n
 * @return string
 */
function aio_ts_season_re($n)
{
    return AIO_TS_SEASON_MARK . '0*' . $n . '(?![0-9])|(?<![0-9])0*' . $n . '[ ._\-]*сезон';
}

// A folder of the path names season $s ("Season 1", "S01", "1 сезон"); $s
// null: any season.
/**
 * @param string $path
 * @param int|null $s
 * @return bool
 */
function aio_ts_dir_season($path, $s)
{
    return preg_match('/' . aio_ts_season_re($s === null ? '[0-9]{1,3}' : strval($s)) . '/iu', aio_ts_dirname($path)) === 1;
}

// The one season the folder name $dir names: "S2", "Season 2", "2 сезон",
// "Show.S02.1080p" -> its number, else null: none ("Extras") or more than one
// ("сезон 1-8", "S01-S08", "S1-3", "Season 1 and 2"). Unlike aio_ts_dir_season:
// one folder, no ranges.
/**
 * @param string $dir
 * @return int|null
 */
function aio_ts_one_season($dir)
{
    $sep = '(?:[ ._]*[\-–—,+&][ ._]*|[ ._]+(?:and|и)[ ._]+)';
    if (preg_match('/' . AIO_TS_SEASON_MARK . '[0-9]{1,3}' . $sep . '(?:s|season[ ._\-]?)?[0-9]{1,3}(?![0-9a-zа-я])|[0-9]{1,3}' . $sep .
        '[0-9]{1,3}[ ._\-]*сезон/iu', $dir))
        return null;
    // The number before "Сезон 4" is of the title ("911 Сезон 4"), unless the one
    // after counts something else ("2 сезон 720p", "2 сезон 5.1", "2 сезон 10 серий").
    $dir = preg_replace('/(?<![0-9])[0-9]{1,3}(?=[ ._\-]*сезон[ ._\-]*[0-9]{1,3}(?![0-9\pL]|[.,][0-9](?![0-9])|[ ._\-]*(?:сез|сер|эп|част|бит|из(?!\pL)|bit|ep|part)))/iu', '', $dir);
    if (!preg_match_all('/' . aio_ts_season_re('([0-9]{1,3})') . '/iu', $dir, $m, PREG_SET_ORDER))
        return null;
    $found = array();
    // The group that matched is the last of the set.
    foreach ($m as $x)
        $found[intval(end($x))] = true;
    return count($found) === 1 ? key($found) : null;
}

// The season of file $path, episode $ep of aio_ts_eps: in its name, else in its last folder.
/**
 * @param string $path
 * @param array{int|null, int} $ep
 * @return int|null
 */
function aio_ts_file_season($path, $ep)
{
    return $ep[0] !== null ? $ep[0] : aio_ts_one_season(aio_ts_basename(aio_ts_dirname($path)));
}

// The folder above the seasons of file $path: copies of a pack ("1080p/S1",
// "720p/S1") differ by it. The folder of the file if that names no season.
/**
 * @param string $path
 * @return string
 */
function aio_ts_pack_root($path)
{
    $d = aio_ts_dirname($path);
    return aio_ts_one_season(aio_ts_basename($d)) !== null ? aio_ts_dirname($d) : $d;
}

// A folder of extras, not of episodes: "Season 2 Bonus", "Bonus/S2", "Доп. материалы".
// Of the last folder that names a season, only what follows the mark: before it
// is the title ("Секретные материалы. Сезон 2").
/**
 * @param string $dir
 * @return bool
 */
function aio_ts_extras($dir)
{
    $parts = explode('/', $dir);
    for ($i = count($parts) - 1; $i >= 0; $i--)
    {
        if (preg_match('/' . aio_ts_season_re('[0-9]{1,3}') . '/iu', $parts[$i], $m, PREG_OFFSET_CAPTURE))
        {
            $parts[$i] = strval(substr($parts[$i], $m[0][1] + strlen($m[0][0])));
            break;
        }
    }
    return preg_match('/(?<![\pL0-9])(?:(?:extras?|bonus(?:es)?|specials?|featurettes?|deleted|samples?|допы?|trailers?|interviews?|ncop|nced|creditless|behind|making)(?!\pL)|бонус|дополн|спец|материал|трейлер|интервью|ролик)/iu', implode('/', $parts)) === 1;
}

// The episodes of the other seasons of the pack, by the seasons of their files
// (aio_ts_file_season), $s the one played: season => episode => key of $files,
// both ascending. Folders of extras below $root: none. Of several files of an
// episode: under $root, in the folder of the season or $root itself, right
// beside the file played (a season folder of $root, "Bonus/S2" not), then with
// more files of the season in the folder. A folder whose numbers do not start
// at 1 numbers across seasons ("S2/11.mkv"): left out.
/**
 * @param list<AioTsFile> $files
 * @param array<int, array{int|null, int}> $all
 * @param string $root
 * @param int $s
 * @return array<int, array<int, int>>
 */
function aio_ts_pack_map($files, $all, $root, $s)
{
    $in = array();
    $count = array();
    $low = array();
    foreach ($all as $k => $ep)
    {
        $path = $files[$k]['path'];
        $q = aio_ts_file_season($path, $ep);
        $d = aio_ts_dirname($path);
        if ($q === null || $q === $s || aio_ts_extras($root !== '' && strpos("$d/", "$root/") === 0 ? substr($d, strlen($root)) : $d))
            continue;
        $in[$q][$k] = $ep[1];
        $count[$q][$d] = isset($count[$q][$d]) ? $count[$q][$d] + 1 : 1;
        if ($ep[0] === null && (!isset($low[$q]) || $ep[1] < $low[$q]))
            $low[$q] = $ep[1];
    }
    ksort($in);
    $out = array();
    foreach ($in as $q => $eps)
    {
        if (isset($low[$q]) && $low[$q] > 1)
            continue;
        $best = array();
        foreach ($eps as $k => $n)
        {
            $d = aio_ts_dirname($files[$k]['path']);
            $home = $d === $root || aio_ts_one_season(aio_ts_basename($d)) === $q;
            $rank = ($root === '' || strpos("$d/", "$root/") === 0 ? 2 : 0) + ($home ? 1 : 0) +
                ($home && ($d === $root || aio_ts_dirname($d) === $root) ? 1 : 0);
            if (!isset($best[$n]) || $rank > $best[$n][0] || ($rank === $best[$n][0] && $count[$q][$d] > $best[$n][1]))
                $best[$n] = array($rank, $count[$q][$d], $k);
        }
        ksort($best);
        foreach ($best as $n => $b)
            $out[$q][$n] = $b[2];
    }
    return $out;
}

// Files of episode $e of season $s among $files (video only): its season in
// the name, else an unknown season with the season in the folder, else any
// unknown season. By a number with no mark (the last two of aio_ts_eps): not in
// a folder of another season, and only from one folder; of several with the
// season in the path ("S1-3/Season 1"), the one whose last folder names it,
// none if the last folder of any names no season ("S01/Extras").
// -> keys of $files.
/**
 * @param list<AioTsFile> $files
 * @param int $s
 * @param int $e
 * @return list<int>
 */
function aio_ts_ep_files($files, $s, $e)
{
    list($all, $weak) = aio_ts_eps($files);
    $named = array();
    $dir = array();
    $any = array();
    $dirs = array();
    $season_dirs = array();
    foreach ($all as $k => $ep)
    {
        $path = $files[$k]['path'];
        if ($ep[1] !== $e || ($ep[0] !== null && $ep[0] !== $s))
            continue;
        if ($ep[0] !== null)
            $named[] = $k;
        else if (aio_ts_dir_season($path, $s))
        {
            $dir[] = $k;
            $season_dirs[aio_ts_dirname($path)] = true;
        }
        else if (!$weak)
            $any[] = $k;
        else if (!aio_ts_dir_season($path, null))
        {
            $any[] = $k;
            $dirs[aio_ts_dirname($path)] = true;
        }
    }
    if ($named)
        return $named;
    if ($dir)
    {
        if (!$weak || count($season_dirs) < 2)
            return $dir;
        $last = array();
        $last_dirs = array();
        foreach ($dir as $k)
        {
            $d = aio_ts_dirname($files[$k]['path']);
            if (!aio_ts_dir_season(aio_ts_basename($d) . '/', null))
                return array();
            if (aio_ts_dir_season(aio_ts_basename($d) . '/', $s))
            {
                $last[] = $k;
                $last_dirs[$d] = true;
            }
        }
        return count($last_dirs) === 1 ? $last : array();
    }
    return count($dirs) > 1 ? array() : $any;
}

// The file of row $r in $files -> array(key of $files or -1, why). The one
// AIOStreams chose: its name (behaviorHints.filename) and size (videoSize);
// else an episode by the name of the file; else, for a movie, the biggest video.
/**
 * @param list<AioTsFile> $files
 * @param AioRow $r
 * @param int $s
 * @param int $e
 * @return array{int, string}
 */
function aio_ts_pick($files, $r, $s, $e)
{
    $raw = $r['raw'];
    $sd = aio_arr($raw, 'streamData');
    $bh = aio_arr($raw, 'behaviorHints');
    $name = aio_str($bh, 'filename') !== '' ? aio_str($bh, 'filename') : aio_str($sd, 'filename');
    $size = aio_str($bh, 'videoSize') !== '' ? aio_num(aio_str($bh, 'videoSize')) : $r['size'];
    $by_name = array();
    foreach ($files as $k => $f)
    {
        if ($name !== '' && aio_ts_video($f['path']) && strcasecmp(aio_ts_basename($f['path']), $name) == 0)
            $by_name[] = $k;
    }
    // An episode: the file named by AIOStreams is left out for a file of this
    // episode with its season in the name, or in a folder when its own last
    // folder names another season and is not above that file. The rest stay:
    // absolute numbering ("- 13" for S02E01), a range of seasons in the root.
    $eps = $e > 0 ? aio_ts_ep_files($files, $s, $e) : array();
    $ep0 = $eps ? aio_ts_ep($files[$eps[0]]['path']) : null;
    if ($ep0 && $ep0[0] !== null)
        $by_name = array_values(array_intersect($by_name, $eps));
    else if ($eps && aio_ts_dir_season($files[$eps[0]]['path'], $s))
    {
        foreach ($by_name as $i => $k)
        {
            $d = aio_ts_basename(aio_ts_dirname($files[$k]['path'])) . '/';
            if (aio_ts_dir_season($d, null) && !aio_ts_dir_season($d, $s) &&
                strpos($files[$eps[0]]['path'], aio_ts_dirname($files[$k]['path']) . '/') !== 0)
                unset($by_name[$i]);
        }
        $by_name = array_values($by_name);
    }
    foreach ($by_name as $k)
    {
        if ($size > 0 && $files[$k]['size'] == $size)
            return array($k, 'name and size');
    }
    if (count($by_name) === 1)
        return array($by_name[0], 'name');
    if ($e > 0)
    {
        if ($by_name)
            return array($by_name[0], 'name and episode');
        return $eps ? array($eps[0], 'episode in the name') : array(-1, 'no file of the episode');
    }
    if ($by_name)
        return array($by_name[0], 'name, the first of ' . count($by_name));
    $big = -1;
    foreach ($files as $k => $f)
    {
        if (aio_ts_video($f['path']) && ($big < 0 || $f['size'] > $files[$big]['size']))
            $big = $k;
    }
    return $big >= 0 ? array($big, 'the biggest video') : array(-1, 'no video');
}

// The stream URL of file $f of torrent $hash for the player.
/**
 * @param string $base
 * @param string $hash
 * @param AioTsFile $f
 * @return string
 */
function aio_ts_url($base, $hash, $f)
{
    return "$base/stream/" . rawurlencode(aio_ts_basename($f['path'])) . "?link=$hash&index={$f['id']}&play";
}

// Episodes of season $s in the pack up to $max, besides $e (the file $chosen):
// episode => key of $files. $parsed: aio_ts_eps of $files (a pack may have
// thousands of files). Its season in the name first, then a file of the folder
// of $chosen; an unknown season counts only in that folder. By a number with no
// mark, none unless $chosen has number $e: AIOStreams may name another numbering.
/**
 * @param list<AioTsFile> $files
 * @param array{array<int, array{int|null, int}>, bool} $parsed
 * @param int $s
 * @param int $e
 * @param int $chosen
 * @param int $max
 * @return array<int, int>
 */
function aio_ts_episodes($files, $parsed, $s, $e, $chosen, $max)
{
    $dir = aio_ts_dirname($files[$chosen]['path']);
    $best = array();
    list($all, $weak) = $parsed;
    if ($weak && (!isset($all[$chosen]) || $all[$chosen][1] !== $e))
        return array();
    foreach ($all as $k => $ep)
    {
        if ($ep[1] < 1 || $ep[1] > $max || $ep[1] === $e || ($ep[0] !== null && $ep[0] !== $s))
            continue;
        $same = aio_ts_dirname($files[$k]['path']) === $dir;
        if ($ep[0] === null && !$same)
            continue;
        $rank = ($ep[0] !== null ? 2 : 0) + ($same ? 1 : 0);
        if (!isset($best[$ep[1]]) || $rank > $best[$ep[1]][0])
            $best[$ep[1]] = array($rank, $k);
    }
    ksort($best);
    $out = array();
    foreach ($best as $i => $b)
        $out[$i] = $b[1];
    return $out;
}

// The map of a start, file $k of row $r: season => episode => id of the file,
// the one source of the files of the playlist. Season $st['s']: aio_ts_episodes,
// the other seasons: aio_ts_pack_map, only if the file has the season asked for;
// no season known: the season alone. Kept (Aio::$ts_pack) only if the file has the
// number and that season, or no season at all: else a lazy episode asks AIOStreams.
/**
 * @param AioState $st
 * @param AioRow $r
 * @param list<AioTsFile> $files
 * @param int $k
 * @return array<int, array<int, int>>
 */
function aio_ts_map_start($st, $r, $files, $k)
{
    Aio::$ts_pack = null;
    $s = $st['s'];
    $e = $st['e'];
    if ($e <= 0)
        return array();
    $seasons = $st['movie']['seasons'];
    $parsed = aio_ts_eps($files);
    $all = $parsed[0];
    $map = array();
    foreach (aio_ts_episodes($files, $parsed, $s, $e, $k, isset($seasons[$s]) ? min($seasons[$s], AIO_EPISODES_MAX) : 0) as $n => $f)
        $map[$s][$n] = $files[$f]['id'];
    if (!isset($all[$k]) || $all[$k][1] !== $e)
        return $map;
    $fs = aio_ts_file_season($files[$k]['path'], $all[$k]);
    if ($fs !== null && $fs !== $s)
        return $map;
    $map[$s][$e] = $files[$k]['id'];
    ksort($map[$s]);
    if ($fs !== null)
    {
        foreach (aio_ts_pack_map($files, $all, aio_ts_pack_root($files[$k]['path']), $s) as $q => $m)
        {
            foreach ($m as $n => $f)
            {
                if (isset($seasons[$q]) && $n >= 1 && $n <= $seasons[$q])
                    $map[$q][$n] = $files[$f]['id'];
            }
        }
    }
    Aio::$ts_pack = array('key' => $st['movie']['dune_id'], 'hash' => $r['hash'], 'row' => aio_ts_pack_row($r), 'map' => $map);
    return $map;
}

// The URLs of $map, but for episode $e of season $s -> array(episode => URL of
// season $s, season => episode => URL of the others), for aio_play.
/**
 * @param string $base
 * @param string $hash
 * @param list<AioTsFile> $files
 * @param array<int, array<int, int>> $map
 * @param int $s
 * @param int $e
 * @return array{array<int, string>, array<int, array<int, string>>}
 */
function aio_ts_map_urls($base, $hash, $files, $map, $s, $e)
{
    $byid = array();
    foreach ($files as $f)
        $byid[$f['id']] = $f;
    $eps = array();
    $others = array();
    foreach ($map as $q => $m)
    {
        foreach ($m as $n => $id)
        {
            if (!isset($byid[$id]) || ($q === $s && $n === $e))
                continue;
            if ($q === $s)
                $eps[$n] = aio_ts_url($base, $hash, $byid[$id]);
            else
                $others[$q][$n] = aio_ts_url($base, $hash, $byid[$id]);
        }
    }
    return array($eps, $others);
}

// --- The chain.

// "Could not open via TorrServer": the reason, its detail, OK; over the
// stopped player of a lazy episode ($lazy) - "Stop", as aio_next_failed.
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @param bool $lazy
 * @return AioAction
 */
function aio_ts_fail($lang, $key, $detail = '', $lazy = false)
{
    aio_log("ts: -> dialog: $key" . ($detail !== '' ? " ($detail)" : ''));
    return aio_dialog(aio_tr($lang, 'ts_failed'), array_merge(aio_dialog_lines(aio_tr($lang, $key)),
        aio_dialog_lines($detail)), $lazy ? array(aio_tr($lang, 'next_stop') => aio_input('next_stop')) :
        array('OK' => aio_close_and(null)));
}

// "No torrents" and a row of a list opened before it was set: this dialog, nothing
// sent anywhere.
/**
 * @param string $lang
 * @return AioAction
 */
function aio_torrents_off($lang)
{
    aio_log('no torrents: -> dialog');
    return aio_dialog(aio_tr($lang, 'ts_err_off'), array(), array('OK' => aio_close_and(null)));
}

// 401 of TorrServer: no login set -> it asks for one, else it refused ours.
/**
 * @return string
 */
function aio_ts_auth_err()
{
    return Aio::$settings['ts_auth'] === '' ? 'ts_err_auth' : 'ts_err_auth_bad';
}

// Row $i of list $st through TorrServer from position $pos (s); $from: rid of
// the list screen under the player, '' for play_action; $lazy: a lazy
// episode of the player (aio_next): no file of it -> aio_next_missing.
// -> vod_play when the files are known at once, else the dialog that waits
// for them (its state in Aio::$tswait when $st is not the list on screen); an
// error dialog. $alive: aio_ts_alive() has just answered, no echo again.
/**
 * @param AioState $st
 * @param int $i
 * @param int $pos
 * @param string $from
 * @param bool $lazy
 * @param bool $alive
 * @return AioAction
 */
function aio_ts_open($st, $i, $pos, $from, $lazy = false, $alive = false)
{
    $r = $st['rows'][$i];
    $lang = $st['lang'];
    if (Aio::$settings['no_torrents'])
        return aio_torrents_off($lang);
    $base = Aio::$settings['ts'];
    $tag = aio_ep_tag($st) . ", row $i, hash {$r['hash']}";
    if (!$alive)
    {
        $t = microtime(true);
        $re = aio_ts_call("$base/echo", null, 2);
        $err = $re['err'];
        $code = $re['code'];
        $ver = $err === '' && $code === 200 ? aio_cut(trim($re['body']), 40) : '';
        aio_log(sprintf('ts: %s: %s echo: %s, %.2f s', $tag, $base, $err !== '' ? $err : "HTTP $code \"$ver\"",
            microtime(true) - $t));
        if ($err !== '' || $code !== 200)
            return aio_ts_fail($lang, 'ts_err_down', $err !== '' ? $err : "HTTP $code", $lazy);
        if (strpos($ver, 'MatriX.') !== 0)
            return aio_ts_fail($lang, 'ts_err_old', $ver, $lazy);
    }
    else
        aio_log("ts: $tag: $base answered just now");

    list($mg, $n, $src) = aio_magnet($r);
    $req = json_encode(array('action' => 'add', 'link' => $mg, 'title' => aio_ep_name($st['movie'], $st['s'], $st['e']),
        'poster' => $st['movie']['poster'], 'save_to_db' => Aio::$settings['ts_save']));
    $t = microtime(true);
    $ra = aio_ts_call("$base/torrents", $req, 5, Aio::$settings['ts_auth']);
    $err = $ra['err'];
    $code = $ra['code'];
    $j = $err === '' && $code === 200 ? aio_ts_decode($ra['body']) : null;
    $files = aio_ts_files($j);
    // The magnet is not logged: a private tracker carries a passkey.
    aio_log(sprintf('ts: add, save %d, %d trackers (%s): %s, %d files, %.2f s', Aio::$settings['ts_save'], $n, $src,
        $err !== '' ? $err : "HTTP $code", count($files), microtime(true) - $t));
    if ($err === '' && $code === 401)
        return aio_ts_fail($lang, aio_ts_auth_err(), '', $lazy);
    if ($err !== '' || $code !== 200 || !$j)
        return aio_ts_fail($lang, 'ts_err_add', $err !== '' ? $err : "HTTP $code", $lazy);
    if ($files)
        return aio_ts_play($st, $i, $files, $pos, $from, $lazy);
    Aio::$tswait = $st;
    $p = array('rid' => $st['rid'], 'i' => strval($i), 'pos' => strval($pos), 'f' => $from, 'l' => $lazy ? '1' : '0',
        't' => strval(time()), 'n' => '0');
    aio_log('ts: no files yet -> waiting dialog');
    return aio_dialog_act(aio_tr($lang, 'ts_wait'), aio_ts_wait_defs($st, $r, 0, $j, $lazy), array(
        ShowDialogActionData::actions => aio_ts_wait_acts($p),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => AIO_TS_TICK_MS)));
}

/**
 * @param array<string, string> $p
 * @return array<string, AioAction>
 */
function aio_ts_wait_acts($p)
{
    return array(GUI_EVENT_TIMER => aio_input('ts_tick', $p));
}

// stat_string of TorrServer -> the key of its words, as TorrServe says it.
/**
 * @return array<string, string>
 */
function aio_ts_stats()
{
    return array('torrent added' => 'ts_st_added', 'torrent getting info' => 'ts_st_info', 'torrent preload' => 'ts_st_preload',
        'torrent working' => 'ts_st_working', 'torrent closed' => 'ts_st_closed', 'torrent in db' => 'ts_st_in_db');
}

// An int field of a TorrentStatus: none (omitempty) or not an int -> 0.
/**
 * @param array<mixed>|null $j
 * @param string $k
 * @return int
 */
function aio_ts_int($j, $k)
{
    return is_array($j) && isset($j[$k]) && is_int($j[$k]) ? $j[$k] : 0;
}

// Controls of the waiting dialog (0.45.0): the release; the status in words
// (none: "waiting for the file list"), seconds, peers, connected seeders;
// the download speed (aio_speed_str), always: the window keeps its height; "Cancel".
/**
 * @param AioState $st
 * @param AioRow $r
 * @param int $sec
 * @param array<mixed>|null $j
 * @param bool $lazy
 * @return list<array<string, mixed>>
 */
function aio_ts_wait_defs($st, $r, $sec, $j, $lazy = false)
{
    $lang = $st['lang'];
    $stat = aio_one_line(aio_str($j, 'stat_string'));
    $keys = aio_ts_stats();
    $low = strtolower($stat);
    $status = $stat === '' ? aio_tr($lang, 'ts_wait_files') : (isset($keys[$low]) ? aio_tr($lang, $keys[$low]) : aio_cut($stat, 30));
    $defs = array();
    foreach (aio_dialog_lines($r['label']) as $l)
        $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l));
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
        str_replace(array('{status}', '{sec}'), array($status, $sec), aio_tr($lang, 'ts_wait_line'))));
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => str_replace(array('{peers}', '{all}', '{seeds}'),
        array(aio_ts_int($j, 'active_peers'), aio_ts_int($j, 'total_peers'), aio_ts_int($j, 'connected_seeders')),
        aio_tr($lang, 'ts_wait_peers'))));
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => str_replace('{speed}',
        aio_speed_str(is_array($j) && isset($j['download_speed']) ? $j['download_speed'] : null, $lang), aio_tr($lang, 'ts_wait_speed'))));
    $defs[] = aio_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => aio_tr($lang, 'ts_cancel'),
        GuiButtonDef::width => 300, GuiButtonDef::push_action => aio_input($lazy ? 'next_stop' : 'ts_cancel')));
    return $defs;
}

// Timer of the waiting dialog: "get" of the torrent; its files -> playback;
// still none -> the timer again, the text redrawn; gone or too long -> error.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_ts_tick($in)
{
    $p = array();
    foreach (array('rid' => '/^[0-9a-f]{12}$/D', 'i' => '/^[0-9]{1,3}$/D', 'pos' => '/^[0-9]{1,7}$/D',
        'f' => '/^([0-9a-f]{12})?$/D', 'l' => '/^[01]$/D', 't' => '/^[0-9]{1,12}$/D', 'n' => '/^[0-9]{1,4}$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
        {
            aio_log("ts: tick with a bad $k");
            return aio_close_and(aio_error('err_list_expired'));
        }
        $p[$k] = $in->$k;
    }
    $i = intval($p['i']);
    // The list on screen, or the one of a lazy episode (aio_next).
    $st = Aio::$state && Aio::$state['rid'] === $p['rid'] ? Aio::$state : Aio::$tswait;
    if (!$st || $p['rid'] !== $st['rid'] || !isset($st['rows'][$i]) || $st['rows'][$i]['hash'] === '')
    {
        aio_log('ts: tick of a list gone');
        return aio_close_and(aio_error('err_list_expired'));
    }
    $r = $st['rows'][$i];
    $lang = $st['lang'];
    $sec = max(0, time() - intval($p['t']));
    $p['n'] = strval(intval($p['n']) + 1);
    list($rg, $j) = aio_ts_get($r['hash']);
    $err = $rg['err'];
    $code = $rg['code'];
    $files = aio_ts_files($j);
    aio_log(sprintf('ts: tick %s, %d s: %s, stat %s, peers %s/%s, seeders %s, %d files', $p['n'], $sec,
        $err !== '' ? $err : "HTTP $code", is_array($j) && isset($j['stat']) ? json_encode($j['stat']) : '-',
        is_array($j) && isset($j['active_peers']) ? json_encode($j['active_peers']) : '-',
        is_array($j) && isset($j['total_peers']) ? json_encode($j['total_peers']) : '-',
        is_array($j) && isset($j['connected_seeders']) ? json_encode($j['connected_seeders']) : '-', count($files)));
    if ($files)
        return aio_close_and(aio_ts_play($st, $i, $files, intval($p['pos']), $p['f'], $p['l'] === '1'));
    if ($err === '' && $code === 401)
        return aio_close_and(aio_ts_fail($lang, aio_ts_auth_err(), '', $p['l'] === '1'));
    // 404: TorrServer has it neither in memory nor in its DB (closed).
    if ($err === '' && $code === 404)
        return aio_close_and(aio_ts_fail($lang, 'ts_err_gone', '', $p['l'] === '1'));
    if ($sec >= AIO_TS_WAIT || intval($p['n']) >= AIO_TS_TICKS)
        return aio_close_and(aio_ts_fail($lang, 'ts_err_timeout', $err !== '' ? $err : '', $p['l'] === '1'));
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => aio_ts_wait_acts($p),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => AIO_TS_TICK_MS),
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => aio_ts_wait_defs($st, $r, $sec, $j, $p['l'] === '1'),
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null))));
}

// The row of a start through TorrServer for its lazy episodes: without the name,
// the size and the length of the file played, of another episode: aio_markers
// would drop the markers past its end.
/**
 * @param AioRow $r
 * @return AioRow
 */
function aio_ts_pack_row($r)
{
    $raw = $r['raw'];
    $bh = aio_arr($raw, 'behaviorHints');
    $sd = aio_arr($raw, 'streamData');
    $pf = aio_arr($sd, 'parsedFile');
    unset($bh['filename'], $bh['videoSize'], $sd['filename'], $sd['duration'], $pf['duration']);
    $sd['parsedFile'] = $pf;
    $raw['behaviorHints'] = $bh;
    $raw['streamData'] = $sd;
    $r['raw'] = $raw;
    return $r;
}

// The list state of a lazy episode from the row and the map of the last start, no
// AIOStreams: of that card and release, an episode of the map. Else null.
/**
 * @param string $key
 * @param int $s
 * @param int $e
 * @param string $hash
 * @return AioState|null
 */
function aio_ts_pack_state($key, $s, $e, $hash)
{
    $p = Aio::$ts_pack;
    $saved = $p && $p['key'] === $key && $p['hash'] === $hash && isset($p['map'][$s][$e]) ? aio_movie_load($key) : null;
    if (!$saved)
        return null;
    $st = aio_list_state($saved['movie'], $s, $e, array($p['row']), $saved['lang']);
    $st['pack_only'] = true;
    $st['pack_map'] = $p['map'];
    return $st;
}

// The file of the row among $files -> vod_play (the files of its map as ready
// URLs, aio_ts_map_start), or the dialog "no video" ($lazy: "not in this release"
// of a lazy episode). A state of aio_ts_pack_state: the file and the playlist of
// its map, the map stays; else the file by aio_ts_pick, a new map.
/**
 * @param AioState $st
 * @param int $i
 * @param list<AioTsFile> $files
 * @param int $pos
 * @param string $from
 * @param bool $lazy
 * @return AioAction
 */
function aio_ts_play($st, $i, $files, $pos, $from, $lazy = false)
{
    $r = $st['rows'][$i];
    $map = isset($st['pack_map']) ? $st['pack_map'] : null;
    if ($map !== null)
    {
        $k = -1;
        foreach ($files as $n => $f)
        {
            if ($f['id'] === $map[$st['s']][$st['e']])
                $k = $n;
        }
        $by = $k >= 0 ? 'the map' : 'no file of the map';
    }
    else
        list($k, $by) = aio_ts_pick($files, $r, $st['s'], $st['e']);
    if ($k < 0)
    {
        aio_log("ts: {$by}, " . count($files) . ' files');
        if ($lazy)
            return aio_next_missing($st, $r['hash']);
        return aio_ts_fail($st['lang'], $st['e'] > 0 ? 'ts_err_no_ep' : 'ts_err_no_video');
    }
    if ($map === null)
        $map = aio_ts_map_start($st, $r, $files, $k);
    $base = Aio::$settings['ts'];
    list($eps, $others) = aio_ts_map_urls($base, $r['hash'], $files, $map, $st['s'], $st['e']);
    aio_log("ts: file {$files[$k]['id']} of " . count($files) . " ($by): " . aio_cut($files[$k]['path'], 200) .
        ($st['e'] > 0 ? ', other episodes in the pack: ' . (count($eps) ? implode(' ', array_keys($eps)) : 'none') : ''));
    if ($others)
        aio_log('ts: other seasons in the pack: ' . implode(' ', array_keys($others)));
    aio_playing($from, $st, $i);
    return aio_play($st, $r, $pos, array('url' => aio_ts_url($base, $r['hash'], $files[$k]), 'eps' => $eps, 'others' => $others));
}

// gc_ts (MENU "Watch via TorrServer") of row i of a live list.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_ts_menu($in)
{
    $st = Aio::$state;
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !isset($st['rows'][$i]) || $st['rows'][$i]['hash'] === '')
    {
        aio_log('ts: menu of no live row with infoHash');
        return aio_error('err_list_expired');
    }
    return aio_ts_open($st, $i, 0, $st['rid']);
}
