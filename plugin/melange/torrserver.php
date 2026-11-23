<?php
// "Watch via TorrServer" (0.31.0): the magnet of a row to TorrServer MatriX
// (POST /torrents add, save_to_db = ts_save), its file list awaited by a dialog
// with a timer, then vod_play of /stream/<name>?link=<hash>&index=<id>&play:
// the Dune player buffers a TorrServer stream itself. The episodes of the
// pack go to the playlist as such URLs, the player switching them alone, or
// lazy, each with its markers (markers of the settings, mel_play).
// TorrServer drops a torrent nobody reads after its own timeout: no drop/rem.
// Reads Mel (state, settings), writes Mel::$tswait, Mel::$ts_pack. Functions are global; the
// require order is in main.php.

// Dialog ticks: every MEL_TS_TICK_MS a "get"; the file list awaited
// MEL_TS_WAIT s at most (Online movies: 60 ticks of 950 ms), and MEL_TS_TICKS
// ticks at most: the clock of the Dune may jump (NTP).
define('MEL_TS_TICK_MS', 1000);
define('MEL_TS_WAIT', 60);
define('MEL_TS_TICKS', 60);
// A start from 0: before the player the preload of its file (mel_ts_preload), the window till
// MEL_TS_BUF of it in the cache of TorrServer - the player of the Dune waits for as much itself, on a black
// screen. Or till MEL_TS_BUF_SHARE of a smaller preload_size; none after MEL_TS_PRE_TICKS ticks: the player.
// The request of the preload waits MEL_TS_PRE_S: it only has to reach TorrServer.
define('MEL_TS_BUF', 33554432);
define('MEL_TS_BUF_SHARE', 0.98);
define('MEL_TS_PRE_TICKS', 5);
define('MEL_TS_PRE_S', 0.3);
// TorrServer MatriX answers /echo within a second ("Continue watching" asks
// before it goes there).
/**
 * @return bool
 */
function mel_ts_alive()
{
    $r = mel_ts_call(Mel::$settings['ts'] . '/echo', null, 1);
    $ok = $r['err'] === '' && $r['code'] === 200 && strpos($r['body'], 'MatriX.') === 0;
    mel_log('ts: ' . Mel::$settings['ts'] . ' echo: ' .
        ($ok ? 'MatriX' : ($r['err'] !== '' ? $r['err'] : "HTTP {$r['code']}")));
    return $ok;
}

// A TorrentStatus of TorrServer -> array or null. Sizes over 2 GB are quoted
// first: json_decode of PHP 5.3 on the 32-bit Dune clamps them.
/**
 * @param string $body
 * @return array<mixed>|null
 */
function mel_ts_decode($body)
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
 * @return array{MelTsReply, array<mixed>|null}
 */
function mel_ts_get($hash)
{
    $rg = mel_ts_call(Mel::$settings['ts'] . '/torrents', (string) json_encode(array('action' => 'get', 'hash' => $hash)), 2,
        Mel::$settings['ts_auth']);
    return array($rg, $rg['err'] === '' && $rg['code'] === 200 ? mel_ts_decode($rg['body']) : null);
}

// file_stats of a TorrentStatus -> list of array('id' => int, 'path' =>
// string, 'size' => float), as TorrServer sorts them (ids from 1).
/**
 * @param array<mixed>|null $j
 * @return list<MelTsFile>
 */
function mel_ts_files($j)
{
    $out = array();
    foreach (mel_arr($j, 'file_stats') as $f)
    {
        $id = is_array($f) && isset($f['id']) && is_int($f['id']) ? $f['id'] : 0;
        $path = mel_str($f, 'path');
        if ($id >= 1 && $path !== '')
            $out[] = array('id' => $id, 'path' => $path, 'size' => mel_num(mel_str($f, 'length')));
    }
    return $out;
}

/**
 * @param string $path
 * @return string
 */
function mel_ts_basename($path)
{
    $p = strrpos($path, '/');
    return $p === false ? $path : substr($path, $p + 1);
}

/**
 * @param string $path
 * @return string
 */
function mel_ts_dirname($path)
{
    $p = strrpos($path, '/');
    return $p === false ? '' : substr($path, 0, $p);
}

// The number of an episode in the name of a file: up to 4 digits (One Piece - 1000), not a year 1900-2099, not
// a resolution ("1080p", "2160i").
define('MEL_TS_EP_N', '(?!(?:19|20)[0-9]{2}(?![0-9])|[0-9]{4}[pi])[0-9]{1,4}');

// Season and episode by the name of a file -> array(season or null, episode)
// or null: S01E02, s1.e2, s01ep02, S01E.02, 1x02; else E02 / EP02 / "Серия 2" /
// "2 серия" / " - 07" (season unknown); an episode up to 4 digits (MEL_TS_EP_N,
// 1x02: 3). Only to find a file in a known pack.
// The number leading the name is mel_ts_ep_lead, weaker (mel_ts_eps).
/**
 * @param string $path
 * @return array{int|null, int}|null
 */
function mel_ts_ep($path)
{
    $n = mel_ts_basename($path);
    $x = MEL_TS_EP_N;
    if (preg_match('/(?<![a-z0-9])s([0-9]{1,2})[ ._\-]?e(?:p(?:isode)?)?(' . $x . ')(?![0-9])/i', $n, $m) ||
        preg_match('/(?<![a-z0-9])s([0-9]{1,2})[ ._\-]?e(?:p(?:isode)?)?[ ._\-](?=[0-9]{2})(' . $x . ')(?![0-9a-z])/i', $n, $m) ||
        preg_match('/(?<![a-z0-9])([0-9]{1,2})x([0-9]{1,3})(?![0-9])/i', $n, $m))
        return array(intval($m[1]), intval($m[2]));
    // Not a CRC of a release: "[E1234ABC]" is no episode 1234 ("E05v2" is the episode 5).
    if (preg_match('/(?<![a-z0-9])ep?(?:isode)?[ ._\-]?(' . $x . ')(?:v[0-9])?(?![0-9a-z])/i', $n, $m) ||
        preg_match('/(?<![\pL0-9])серия[ ._\-]*(' . $x . ')(?![0-9])/iu', $n, $m) ||
        preg_match('/(?<![0-9])(' . $x . ')[ ._\-]*серия/iu', $n, $m) ||
        // Anime: "[Group] Title - 07 [1080p].mkv", "Title - 07v2.mkv".
        preg_match('/ - (' . $x . ')(?:v[0-9])?(?=[ ._\[(]|$)/i', $n, $m))
        return array(null, intval($m[1]));
    return null;
}

// The number leading the name: "01. Title.mkv", "01.mkv", "1000.mkv" (not
// "2024.", "1080p.") -> array(null, episode) or null.
/**
 * @param string $path
 * @return array{null, int}|null
 */
function mel_ts_ep_lead($path)
{
    return preg_match('/^(' . MEL_TS_EP_N . ')(?=[ ._\-])/', mel_ts_basename($path), $m) ? array(null, intval($m[1])) : null;
}

// The weakest: in each folder, the number right after the start common to the
// names of its videos: "[Group] Title 01 [1080p].mkv". A folder gives none with
// a letter before the number ("SP01"), a repeated number or a date in a name; a
// file none with a separate number right after it ("5.1"). If any folder of
// videos has a season in the path, only such folders; else the rest, none if a
// number is in two of them.
// -> array(key of $files => array(null, episode)).
/**
 * @param list<MelTsFile> $files
 * @return array<int, array{null, int}>
 */
function mel_ts_eps_prefix($files)
{
    $dirs = array();
    foreach ($files as $k => $f)
    {
        if (mel_ts_video($f['path']))
            $dirs[mel_ts_dirname($f['path'])][$k] = mel_ts_basename($f['path']);
    }
    $season = array();
    $other = array();
    $used = array();
    $twice = false;
    $any_season = false;
    foreach ($dirs as $dir => $names)
    {
        $in_season = mel_ts_dir_season("$dir/", null);
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
            if (preg_match('/^(' . MEL_TS_EP_N . ')(?:v[0-9])?(?![0-9a-z])(?![ ._\-][0-9]{1,2}(?![0-9a-z]))/i', substr($n, $start), $m))
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

// Episodes of the videos of $files by mel_ts_ep; only when it knows none of
// them, by mel_ts_ep_lead; only when that knows none, by mel_ts_eps_prefix.
// -> array(key of $files => its episode, by one of the last two).
/**
 * @param list<MelTsFile> $files
 * @return array{array<int, array{int|null, int}>, bool}
 */
function mel_ts_eps($files)
{
    $out = array();
    foreach ($files as $k => $f)
    {
        if (mel_ts_video($f['path']) && ($ep = mel_ts_ep($f['path'])))
            $out[$k] = $ep;
    }
    if ($out)
        return array($out, false);
    foreach ($files as $k => $f)
    {
        if (mel_ts_video($f['path']) && ($ep = mel_ts_ep_lead($f['path'])))
            $out[$k] = $ep;
    }
    return array($out ? $out : mel_ts_eps_prefix($files), true);
}

// What starts a mark of a season before its number: "S", "Season ", "Сезон ".
define('MEL_TS_SEASON_MARK', '(?:(?<![a-z0-9])(?:s|season[ ._\-]?)|сезон[ ._\-]*)');

// The marks of a season numbered by the regex $n: "S01", "Season 1", "Сезон 1", "1 сезон".
/**
 * @param string $n
 * @return string
 */
function mel_ts_season_re($n)
{
    return MEL_TS_SEASON_MARK . '0*' . $n . '(?![0-9])|(?<![0-9])0*' . $n . '[ ._\-]*сезон';
}

// A folder of the path names season $s ("Season 1", "S01", "1 сезон"); $s
// null: any season.
/**
 * @param string $path
 * @param int|null $s
 * @return bool
 */
function mel_ts_dir_season($path, $s)
{
    return preg_match('/' . mel_ts_season_re($s === null ? '[0-9]{1,3}' : strval($s)) . '/iu', mel_ts_dirname($path)) === 1;
}

// The one season the folder name $dir names: "S2", "Season 2", "2 сезон",
// "Show.S02.1080p" -> its number, else null: none ("Extras"), more than one
// ("сезон 1-8", "S01-S08", "S1-3", "Season 1 and 2") or marks that differ, which
// one is the season is unclear ("911 Сезон 4", "2 сезон 3 озвучки"). Unlike
// mel_ts_dir_season: one folder, no ranges.
/**
 * @param string $dir
 * @return int|null
 */
function mel_ts_one_season($dir)
{
    $sep = '(?:[ ._]*[\-–—,+&][ ._]*|[ ._]+(?:and|и)[ ._]+)';
    if (preg_match('/' . MEL_TS_SEASON_MARK . '[0-9]{1,3}' . $sep . '(?:s|season[ ._\-]?)?[0-9]{1,3}(?![0-9a-zа-я])|[0-9]{1,3}' . $sep .
        '[0-9]{1,3}[ ._\-]*сезон/iu', $dir))
        return null;
    // Every mark, overlapping ones too ("911 Сезон 4": two), so a lookahead. A
    // number with a letter after is no mark ("сезон 720p"), but "S02E01" is.
    if (!preg_match_all('/(?=' . MEL_TS_SEASON_MARK . '0*([0-9]{1,3})(?![0-9]|(?!e[0-9])\pL)|(?<![0-9])([0-9]+)[ ._\-]*сезон(?!\pL))/iu', $dir, $m, PREG_SET_ORDER))
        return null;
    $found = array();
    // The group that matched is the last of the set.
    foreach ($m as $x)
        $found[intval(end($x))] = true;
    return count($found) === 1 ? key($found) : null;
}

// The season of file $path, episode $ep of mel_ts_eps: in its name, else in its last folder.
/**
 * @param string $path
 * @param array{int|null, int} $ep
 * @return int|null
 */
function mel_ts_file_season($path, $ep)
{
    return $ep[0] !== null ? $ep[0] : mel_ts_one_season(mel_ts_basename(mel_ts_dirname($path)));
}

// The episodes of the other seasons of the pack, by the seasons of their files
// (mel_ts_file_season), $s the one played: season => episode => key of $files,
// both ascending. Only from the folder of $played or a neighbour of it: the same
// parent and the same name but for its numbers after the mark of a season, if any
// ("S1" -> "S2", "Сезон 1 (2011)" -> "Сезон 2 (2012)", not "Show 2 S01" -> "Show 3 S02"),
// so no extras, other shows or other copies. A season takes one kind of folder: its
// own, else one that differs by the number of the season alone ("Show.S01.720p" ->
// "Show.S02.720p"), else other neighbours; of several files of an episode, the first.
// A folder whose numbers do not start at 1 numbers across seasons ("S2/11.mkv"): left out.
/**
 * @param list<MelTsFile> $files
 * @param array<int, array{int|null, int}> $all
 * @param string $played
 * @param int $s
 * @return array<int, array<int, int>>
 */
function mel_ts_pack_map($files, $all, $played, $s)
{
    $home = mel_ts_dirname($played);
    $name = mel_ts_basename($home);
    $title = preg_match('/' . mel_ts_season_re('[0-9]{1,3}') . '/iu', $name, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : 0;
    $like = '/^' . preg_quote(strval(substr($name, 0, $title)), '/') . preg_replace('/[0-9]+/', '[0-9]+', preg_quote(strval(substr($name, $title)), '/')) . '$/iu';
    preg_match_all('/[0-9]+/', $name, $nums);
    $out = array();
    $kinds = array();
    $low = array();
    foreach ($all as $k => $ep)
    {
        $path = $files[$k]['path'];
        $q = mel_ts_file_season($path, $ep);
        $d = mel_ts_dirname($path);
        $base = mel_ts_basename($d);
        if ($q === null || $q === $s || ($d !== $home && ($home === '' || mel_ts_dirname($d) !== mel_ts_dirname($home) || !preg_match($like, $base))))
            continue;
        preg_match_all('/[0-9]+/', $base, $m);
        $diff = array_diff_assoc($m[0], $nums[0]);
        $kind = $d === $home ? 2 : (count($diff) === 1 && intval(reset($diff)) === $q ? 1 : 0);
        if (isset($kinds[$q]) && $kind < $kinds[$q])
            continue;
        if (!isset($kinds[$q]) || $kind > $kinds[$q])
        {
            $kinds[$q] = $kind;
            $out[$q] = array();
            unset($low[$q]);
        }
        $n = $ep[1];
        if ($ep[0] === null && (!isset($low[$q]) || $n < $low[$q]))
            $low[$q] = $n;
        if (!isset($out[$q][$n]))
            $out[$q][$n] = $k;
    }
    foreach ($low as $q => $n)
    {
        if ($n > 1)
            unset($out[$q]);
    }
    ksort($out);
    foreach (array_keys($out) as $q)
        ksort($out[$q]);
    return $out;
}

// Files of episode $e of season $s among $files (video only): its season in
// the name, else an unknown season with the season in the folder, else any
// unknown season. By a number with no mark (the last two of mel_ts_eps): not in
// a folder of another season, and only from one folder; of several with the
// season in the path ("S1-3/Season 1"), the one whose last folder names it,
// none if the last folder of any names no season ("S01/Extras").
// -> keys of $files.
/**
 * @param list<MelTsFile> $files
 * @param int $s
 * @param int $e
 * @return list<int>
 */
function mel_ts_ep_files($files, $s, $e)
{
    list($all, $weak) = mel_ts_eps($files);
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
        else if (mel_ts_dir_season($path, $s))
        {
            $dir[] = $k;
            $season_dirs[mel_ts_dirname($path)] = true;
        }
        else if (!$weak)
            $any[] = $k;
        else if (!mel_ts_dir_season($path, null))
        {
            $any[] = $k;
            $dirs[mel_ts_dirname($path)] = true;
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
            $d = mel_ts_dirname($files[$k]['path']);
            if (!mel_ts_dir_season(mel_ts_basename($d) . '/', null))
                return array();
            if (mel_ts_dir_season(mel_ts_basename($d) . '/', $s))
            {
                $last[] = $k;
                $last_dirs[$d] = true;
            }
        }
        return count($last_dirs) === 1 ? $last : array();
    }
    return count($dirs) > 1 ? array() : $any;
}

// Files $keys of $files (mel_ts_ep_files) of season $s for sure: the season in the
// name, else as the one season of its own folder (mel_ts_one_season: "Season
// 21/032.mkv", not "S01-S21/032.mkv" nor "1-21 сезон/032.mkv").
/**
 * @param list<MelTsFile> $files
 * @param list<int> $keys
 * @param int $s
 * @return list<int>
 */
function mel_ts_ep_sure($files, $keys, $s)
{
    $out = array();
    foreach ($keys as $k)
    {
        $p = $files[$k]['path'];
        $ep = mel_ts_ep($p);
        if (($ep && $ep[0] !== null ? $ep[0] : mel_ts_one_season(mel_ts_basename(mel_ts_dirname($p)))) === $s)
            $out[] = $k;
    }
    return $out;
}

// The name of file $path, the one AIOStreams chose for S$s E$e of an anime
// numbered on (its number of the meta $n), names no other episode: no episode in
// it (mel_ts_ep, mel_ts_ep_lead), S$s E$e, or $n with no season or season 1. Not
// "One Piece - 032.mkv" for S21E32, the 923rd.
/**
 * @param string $path
 * @param int $s
 * @param int $e
 * @param int $n
 * @return bool
 */
function mel_ts_name_ok($path, $s, $e, $n)
{
    $ep = mel_ts_ep($path);
    if (!$ep)
        $ep = mel_ts_ep_lead($path);
    return !$ep || ($ep[0] === $s && $ep[1] === $e) || (($ep[0] === null || $ep[0] === 1) && $ep[1] === $n) ||
        ($ep[0] === null && $ep[1] === $e && mel_ts_one_season(mel_ts_basename(mel_ts_dirname($path))) === $s);
}

// The file of row $r in $files -> array(key of $files or -1, why). The one
// AIOStreams chose: its name (behaviorHints.filename) and size (videoSize);
// else an episode by the name of the file; else, for a movie, the biggest video,
// for an episode of a card of season 1 alone ($one_season), the only video if
// it has no mark. An episode of an anime numbered on, its number of the meta $n
// (mel_cin_n: 923 for S21E32 of One Piece): its season and episode only with the
// season in the name or as the one season of its own folder (mel_ts_ep_sure:
// "032" of a pack "1-130" or "1-21 сезон" is another episode, mel_cin_wrong),
// else by that number in season 1 (an old pack of the absolute numbering
// "1-990", "S01-S21/923.mkv"); the file named by AIOStreams only if its name has
// no other episode (mel_ts_name_ok).
/**
 * @param list<MelTsFile> $files
 * @param MelRow $r
 * @param int $s
 * @param int $e
 * @param bool $one_season
 * @param int $n
 * @return array{int, string}
 */
function mel_ts_pick($files, $r, $s, $e, $one_season = false, $n = 0)
{
    $raw = $r['raw'];
    $sd = mel_arr($raw, 'streamData');
    $bh = mel_arr($raw, 'behaviorHints');
    $name = mel_str($bh, 'filename') !== '' ? mel_str($bh, 'filename') : mel_str($sd, 'filename');
    $size = mel_str($bh, 'videoSize') !== '' ? mel_num(mel_str($bh, 'videoSize')) : $r['size'];
    $by_name = array();
    foreach ($files as $k => $f)
    {
        if ($name !== '' && mel_ts_video($f['path']) && strcasecmp(mel_ts_basename($f['path']), $name) == 0 &&
            ($n < 1 || mel_ts_name_ok($f['path'], $s, $e, $n)))
            $by_name[] = $k;
    }
    // An episode: the file named by AIOStreams is left out for a file of this
    // episode with its season in the name, or in a folder when its own last
    // folder names another season and is not above that file. The rest stay:
    // absolute numbering ("- 13" for S02E01), a range of seasons in the root.
    $eps = $e > 0 ? mel_ts_ep_files($files, $s, $e) : array();
    $q = $s;
    if ($n > 0 && $e > 0)
    {
        $eps = mel_ts_ep_sure($files, $eps, $s);
        if (!$eps)
            list($eps, $q) = array(mel_ts_ep_files($files, 1, $n), 1);
    }
    $ep0 = $eps ? mel_ts_ep($files[$eps[0]]['path']) : null;
    if ($ep0 && $ep0[0] !== null)
        $by_name = array_values(array_intersect($by_name, $eps));
    else if ($eps && mel_ts_dir_season($files[$eps[0]]['path'], $q))
    {
        foreach ($by_name as $i => $k)
        {
            $d = mel_ts_basename(mel_ts_dirname($files[$k]['path'])) . '/';
            if (mel_ts_dir_season($d, null) && !mel_ts_dir_season($d, $q) &&
                strpos($files[$eps[0]]['path'], mel_ts_dirname($files[$k]['path']) . '/') !== 0)
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
        if ($eps)
            return array($eps[0], 'episode in the name');
        // A movie in one file that IMDb lists as a miniseries ($one_season, the
        // card has season 1 alone): the only video of the release, with no mark
        // of a season or an episode in its path, for every episode.
        if ($one_season && $s === 1)
        {
            $n = 0;
            $only = -1;
            foreach ($files as $k => $f)
            {
                if (mel_ts_video($f['path']))
                {
                    $n++;
                    $only = $k;
                }
            }
            list($marked) = mel_ts_eps($files);
            // The path with "/": mel_ts_dir_season reads its folders, so the name too.
            if ($n === 1 && !isset($marked[$only]) && !mel_ts_dir_season($files[$only]['path'] . '/', null))
                return array($only, 'the only video');
        }
        return array(-1, 'no file of the episode');
    }
    if ($by_name)
        return array($by_name[0], 'name, the first of ' . count($by_name));
    $big = -1;
    foreach ($files as $k => $f)
    {
        if (mel_ts_video($f['path']) && ($big < 0 || $f['size'] > $files[$big]['size']))
            $big = $k;
    }
    return $big >= 0 ? array($big, 'the biggest video') : array(-1, 'no video');
}

// The stream URL of file $f of torrent $hash for the player ($what 'play'), or the one of its preload ('preload').
/**
 * @param string $base
 * @param string $hash
 * @param MelTsFile $f
 * @param string $what
 * @return string
 */
function mel_ts_url($base, $hash, $f, $what = 'play')
{
    return "$base/stream/" . rawurlencode(mel_ts_basename($f['path'])) . "?link=$hash&index={$f['id']}&$what";
}

// Episodes of season $s in the pack up to $max, besides $e (the file $chosen):
// episode => key of $files. $parsed: mel_ts_eps of $files (a pack may have
// thousands of files). Its season in the name first, then a file of the folder
// of $chosen; an unknown season counts only in that folder. By a number with no
// mark, none unless $chosen has number $e: AIOStreams may name another numbering.
/**
 * @param list<MelTsFile> $files
 * @param array{array<int, array{int|null, int}>, bool} $parsed
 * @param int $s
 * @param int $e
 * @param int $chosen
 * @param int $max
 * @return array<int, int>
 */
function mel_ts_episodes($files, $parsed, $s, $e, $chosen, $max)
{
    $dir = mel_ts_dirname($files[$chosen]['path']);
    $best = array();
    list($all, $weak) = $parsed;
    if ($weak && (!isset($all[$chosen]) || $all[$chosen][1] !== $e))
        return array();
    foreach ($all as $k => $ep)
    {
        if ($ep[1] < 1 || $ep[1] > $max || $ep[1] === $e || ($ep[0] !== null && $ep[0] !== $s))
            continue;
        $same = mel_ts_dirname($files[$k]['path']) === $dir;
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
// the one source of the files of the playlist. Season $st['s']: mel_ts_episodes,
// the other seasons: mel_ts_pack_map, only if the file has the season asked for;
// no season known: the season alone. Kept (Mel::$ts_pack) only if the file has the
// number and that season, or no season at all: else a lazy episode asks AIOStreams.
// The file of an anime numbered on by its number of the meta (mel_cin_n: "923"
// for S21E32): mel_ts_map_abs.
/**
 * @param MelState $st
 * @param MelRow $r
 * @param list<MelTsFile> $files
 * @param int $k
 * @return array<int, array<int, int>>
 */
function mel_ts_map_start($st, $r, $files, $k)
{
    Mel::$ts_pack = null;
    $s = $st['s'];
    $e = $st['e'];
    if ($e <= 0)
        return array();
    $seasons = $st['movie']['seasons'];
    $parsed = mel_ts_eps($files);
    $all = $parsed[0];
    $abs = mel_cin_n($st['movie']['cin'], $s, $e);
    if ($abs > 0 && isset($all[$k]) && $all[$k][1] === $abs && ($all[$k][0] === null || $all[$k][0] === 1))
        return mel_ts_map_abs($st, $r, $files, $all, $k);
    $map = array();
    foreach (mel_ts_episodes($files, $parsed, $s, $e, $k, isset($seasons[$s]) ? min($seasons[$s], MEL_EPISODES_MAX) : 0) as $n => $f)
        $map[$s][$n] = $files[$f]['id'];
    if (!isset($all[$k]) || $all[$k][1] !== $e)
        return $map;
    $fs = mel_ts_file_season($files[$k]['path'], $all[$k]);
    if ($fs !== null && $fs !== $s)
        return $map;
    $map[$s][$e] = $files[$k]['id'];
    ksort($map[$s]);
    if ($fs !== null)
    {
        foreach (mel_ts_pack_map($files, $all, $files[$k]['path'], $s) as $q => $m)
        {
            foreach ($m as $n => $f)
            {
                if (isset($seasons[$q]) && $n >= 1 && $n <= $seasons[$q])
                    $map[$q][$n] = $files[$f]['id'];
            }
        }
    }
    Mel::$ts_pack = array('key' => $st['movie']['dune_id'], 'hash' => $r['hash'], 'row' => mel_ts_pack_row($r), 'map' => $map);
    return $map;
}

// The map of a start of an anime numbered on, file $k of row $r with the number of the
// meta of the episode (the 923rd of One Piece for S21E32 in a pack "1-990"): the
// episodes of its season by their numbers (mel_cin_abs), the files of the folder of $k
// with such a number and no season or season 1 ($all: mel_ts_eps), the first of
// several; up to MEL_EPISODES_MAX. Kept (Mel::$ts_pack) for the lazy ones.
/**
 * @param MelState $st
 * @param MelRow $r
 * @param list<MelTsFile> $files
 * @param array<int, array{int|null, int}> $all
 * @param int $k
 * @return array<int, array<int, int>>
 */
function mel_ts_map_abs($st, $r, $files, $all, $k)
{
    $s = $st['s'];
    $mv = $st['movie'];
    $dir = mel_ts_dirname($files[$k]['path']);
    $by = array();
    foreach ($all as $j => $ep)
    {
        if (($ep[0] === null || $ep[0] === 1) && !isset($by[$ep[1]]) && mel_ts_dirname($files[$j]['path']) === $dir)
            $by[$ep[1]] = $j;
    }
    $max = isset($mv['seasons'][$s]) ? min($mv['seasons'][$s], MEL_EPISODES_MAX) : 0;
    $map = array($s => array());
    for ($i = 1; $i <= $max; $i++)
    {
        $a = mel_cin_abs($mv['cin'], $s, $i);
        if ($a > 0 && isset($by[$a]))
            $map[$s][$i] = $files[$by[$a]]['id'];
    }
    $map[$s][$st['e']] = $files[$k]['id'];
    ksort($map[$s]);
    Mel::$ts_pack = array('key' => $mv['dune_id'], 'hash' => $r['hash'], 'row' => mel_ts_pack_row($r), 'map' => $map);
    return $map;
}

// The URLs of $map, but for episode $e of season $s -> array(episode => URL of
// season $s, season => episode => URL of the others), for mel_play.
/**
 * @param string $base
 * @param string $hash
 * @param list<MelTsFile> $files
 * @param array<int, array<int, int>> $map
 * @param int $s
 * @param int $e
 * @return array{array<int, string>, array<int, array<int, string>>}
 */
function mel_ts_map_urls($base, $hash, $files, $map, $s, $e)
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
                $eps[$n] = mel_ts_url($base, $hash, $byid[$id]);
            else
                $others[$q][$n] = mel_ts_url($base, $hash, $byid[$id]);
        }
    }
    return array($eps, $others);
}

// --- The chain.

// "Could not open via TorrServer": the reason, its detail, OK; over the
// stopped player of a lazy episode ($lazy) - "Stop", as mel_next_failed.
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @param bool $lazy
 * @return MelAction
 */
function mel_ts_fail($lang, $key, $detail = '', $lazy = false)
{
    mel_log("ts: -> dialog: $key" . ($detail !== '' ? " ($detail)" : ''));
    return mel_dialog(mel_tr($lang, 'ts_failed'), array_merge(mel_dialog_lines(mel_tr($lang, $key)),
        mel_dialog_lines($detail)), $lazy ? array(mel_tr($lang, 'next_stop') => mel_input('next_stop')) :
        array('OK' => mel_close_and(null)));
}

// "No torrents" and a row of a list opened before it was set: this dialog, nothing
// sent anywhere.
/**
 * @param string $lang
 * @return MelAction
 */
function mel_torrents_off($lang)
{
    mel_log('no torrents: -> dialog');
    return mel_dialog(mel_tr($lang, 'ts_err_off'), array(), array('OK' => mel_close_and(null)));
}

// 401 of TorrServer: no login set -> it asks for one, else it refused ours.
/**
 * @return string
 */
function mel_ts_auth_err()
{
    return Mel::$settings['ts_auth'] === '' ? 'ts_err_auth' : 'ts_err_auth_bad';
}

// Row $i of list $st through TorrServer from position $pos (s); $from: rid of
// the list screen under the player, '' for play_action; $lazy: a lazy
// episode of the player (mel_next): no file of it -> mel_next_missing.
// -> vod_play when the files are known at once and $pos > 0, else the dialog that
// waits for them or, from 0, for the preload of the file (its state in
// Mel::$tswait when $st is not the list on screen); an error dialog. $alive:
// mel_ts_alive() has just answered, no echo again.
// $miss_list: "Continue" to the next episode, no file of it -> the list of $st.
/**
 * @param MelState $st
 * @param int $i
 * @param int $pos
 * @param string $from
 * @param bool $lazy
 * @param bool $alive
 * @param bool $miss_list
 * @return MelAction
 */
function mel_ts_open($st, $i, $pos, $from, $lazy = false, $alive = false, $miss_list = false)
{
    $r = $st['rows'][$i];
    $lang = $st['lang'];
    if (Mel::$settings['no_torrents'])
        return mel_torrents_off($lang);
    $base = Mel::$settings['ts'];
    $tag = mel_ep_tag($st) . ", row $i, hash {$r['hash']}";
    if (!$alive)
    {
        $t = microtime(true);
        $re = mel_ts_call("$base/echo", null, 2);
        $err = $re['err'];
        $code = $re['code'];
        $ver = $err === '' && $code === 200 ? mel_cut(trim($re['body']), 40) : '';
        mel_log(sprintf('ts: %s: %s echo: %s, %.2f s', $tag, $base, $err !== '' ? $err : "HTTP $code \"$ver\"",
            microtime(true) - $t));
        if ($err !== '' || $code !== 200)
            return mel_ts_fail($lang, 'ts_err_down', $err !== '' ? $err : "HTTP $code", $lazy);
        if (strpos($ver, 'MatriX.') !== 0)
            return mel_ts_fail($lang, 'ts_err_old', $ver, $lazy);
    }
    else
        mel_log("ts: $tag: $base answered just now");

    list($mg, $n, $src) = mel_magnet($r);
    // An address only: a path of a picture on the Dune (of its Favorites, mel_ml_url) means nothing to TorrServer.
    $poster = preg_match('~^https?://~', $st['movie']['poster']) ? $st['movie']['poster'] : '';
    $req = json_encode(array('action' => 'add', 'link' => $mg, 'title' => mel_ep_name($st['movie'], $st['s'], $st['e']),
        'poster' => $poster, 'save_to_db' => Mel::$settings['ts_save']));
    $t = microtime(true);
    $ra = mel_ts_call("$base/torrents", $req, 5, Mel::$settings['ts_auth']);
    $err = $ra['err'];
    $code = $ra['code'];
    $j = $err === '' && $code === 200 ? mel_ts_decode($ra['body']) : null;
    $files = mel_ts_files($j);
    // The magnet is not logged: a private tracker carries a passkey.
    mel_log(sprintf('ts: add, save %d, %d trackers (%s): %s, %d files, %.2f s', Mel::$settings['ts_save'], $n, $src,
        $err !== '' ? $err : "HTTP $code", count($files), microtime(true) - $t));
    if ($err === '' && $code === 401)
        return mel_ts_fail($lang, mel_ts_auth_err(), '', $lazy);
    if ($err !== '' || $code !== 200 || !$j)
        return mel_ts_fail($lang, 'ts_err_add', $err !== '' ? $err : "HTTP $code", $lazy);
    if ($files && ($pos > 0 || mel_ts_preload($st, $i, $files, $j) !== ''))
        return mel_ts_play($st, $i, $files, $pos, $from, $lazy, $miss_list);
    Mel::$tswait = $st;
    // l: 1 lazy, 2 $miss_list; b: the tick of the start of the preload, '' none yet.
    $p = array('rid' => $st['rid'], 'i' => strval($i), 'pos' => strval($pos), 'f' => $from,
        'l' => $lazy ? '1' : ($miss_list ? '2' : '0'),
        't' => strval(time()), 'n' => '0', 'b' => $files ? '0' : '');
    mel_log($files ? 'ts: -> the dialog of the preload' : 'ts: no files yet -> waiting dialog');
    return mel_ts_wait_open($lang, mel_ts_wait_defs($st, $r, 0, $j, $lazy, $p['b'] !== ''), mel_ts_wait_acts($p));
}

// The preload of the file of row $i of $st among $files (mel_ts_file), a start from 0, $j the
// TorrentStatus where the files came: a GET of its &preload with a timeout of MEL_TS_PRE_S, the reply not
// awaited - TorrServer holds the request till the end of the preload and goes on when the client leaves
// (device, knowledge melange-preload-buffer.md); its error ignored, the ticks see none (mel_ts_buf_done).
// No shell: the name of the file is in the URL. -> why the player at once, '' the preload asked (the window):
// no file (mel_ts_play tells why); MEL_TS_BUF in the cache already - the request all the same, no window
// (preloaded_bytes is the cache of the whole torrent: a lazy episode of the pack, a torrent alive - the head of
// its file may be cold, TorrServer loads it beside the player); a 200 at once with no preload_size in $j - the
// preload is off in TorrServer (PreloadCache 0 %: it returns at once).
/**
 * @param MelState $st
 * @param int $i
 * @param list<MelTsFile> $files
 * @param array<mixed>|null $j
 * @return string
 */
function mel_ts_preload($st, $i, $files, $j)
{
    list($k) = mel_ts_file($st, $i, $files);
    if ($k < 0)
        return 'no file';
    return mel_ts_preload_file($st['rows'][$i]['hash'], $files[$k], $j);
}

// The preload of file $f of torrent $hash, $j its TorrentStatus (see mel_ts_preload) -> why the player at once, ''
// the preload asked. The section "На TorrServer" (view_ts_play.php) too.
/**
 * @param string $hash
 * @param MelTsFile $f
 * @param array<mixed>|null $j
 * @return string
 */
function mel_ts_preload_file($hash, $f, $j)
{
    $t = microtime(true);
    $rp = mel_ts_call(mel_ts_url(Mel::$settings['ts'], $hash, $f, 'preload'), null, MEL_TS_PRE_S, Mel::$settings['ts_auth']);
    mel_log(sprintf('ts: preload of file %d: %s, %.2f s: %s', $f['id'], $rp['err'] !== '' ? $rp['err'] : "HTTP {$rp['code']}",
        microtime(true) - $t, mel_cut($f['path'], 200)));
    $got = mel_num(mel_str($j, 'preloaded_bytes'));
    if ($got >= MEL_TS_BUF)
    {
        mel_log(sprintf('ts: -> the player: the cache warm, preloaded %.0f', $got));
        return 'the cache warm';
    }
    if ($rp['err'] !== '' || $rp['code'] !== 200 || mel_num(mel_str($j, 'preload_size')) > 0)
        return '';
    mel_log('ts: -> the player: no preload in TorrServer');
    return 'no preload in TorrServer';
}

// TorrentStatus $j of a tick $ticks after the start of the preload -> why the player now, '' not yet:
// MEL_TS_BUF in the cache; MEL_TS_BUF_SHARE of its preload_size (a small cache or file); the preload over
// ("Torrent working" with a preload_size); no preload_size after MEL_TS_PRE_TICKS (an old TorrServer, the
// request lost).
/**
 * @param array<mixed>|null $j
 * @param int $ticks
 * @return string
 */
function mel_ts_buf_done($j, $ticks)
{
    $got = mel_num(mel_str($j, 'preloaded_bytes'));
    $size = mel_num(mel_str($j, 'preload_size'));
    if ($got >= MEL_TS_BUF)
        return 'the buffer';
    if ($size > 0 && $got >= MEL_TS_BUF_SHARE * $size)
        return '98 % of the preload';
    if ($size > 0 && strtolower(mel_str($j, 'stat_string')) === 'torrent working')
        return 'the preload over';
    return $size <= 0 && $ticks >= MEL_TS_PRE_TICKS ? 'no preload' : '';
}

// The waiting dialog of a start through TorrServer: «Открываю через TorrServer», controls $defs (mel_ts_wait_ctls), the
// actions $acts (its timer: the tick), a tick every MEL_TS_TICK_MS. A start from the list of releases and from the section
// alike.
/**
 * @param string $lang
 * @param list<array<string, mixed>> $defs
 * @param array<string, MelAction> $acts
 * @return MelAction
 */
function mel_ts_wait_open($lang, $defs, $acts)
{
    return mel_dialog_act(mel_tr($lang, 'ts_wait'), $defs, array(
        ShowDialogActionData::actions => $acts,
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => MEL_TS_TICK_MS)));
}

// The waiting dialog (mel_ts_wait_open) after a tick that waits on: its actions $acts again, the timer again, its controls
// redrawn as $defs (reset_controls, no focus).
/**
 * @param list<array<string, mixed>> $defs
 * @param array<string, MelAction> $acts
 * @return MelAction
 */
function mel_ts_wait_again($defs, $acts)
{
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => $acts,
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => MEL_TS_TICK_MS),
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => $defs,
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null))));
}

/**
 * @param array<string, string> $p
 * @return array<string, MelAction>
 */
function mel_ts_wait_acts($p)
{
    return array(GUI_EVENT_TIMER => mel_input('ts_tick', $p));
}

// stat_string of TorrServer -> the key of its words, as TorrServe says it.
/**
 * @return array<string, string>
 */
function mel_ts_stats()
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
function mel_ts_int($j, $k)
{
    return is_array($j) && isset($j[$k]) && is_int($j[$k]) ? $j[$k] : 0;
}

// The target of a preload in bytes: MEL_TS_BUF, a smaller preload_size of TorrentStatus $j.
/**
 * @param array<mixed>|null $j
 * @return float
 */
function mel_ts_buf_need($j)
{
    $size = mel_num(mel_str($j, 'preload_size'));
    return $size > 0 && $size < MEL_TS_BUF ? $size : (float) MEL_TS_BUF;
}

// "42 Мбит/с" of the download speed of TorrServer (B/s) in the waiting dialog, as the bit rate next to it
// (mel_gc_rate); over 50 B/s never "0": 0.1 at least. No speed - as mel_speed_str: "—".
/**
 * @param mixed $bps
 * @param string $lang
 * @return string
 */
function mel_ts_speed($bps, $lang)
{
    $s = mel_speed_str($bps, $lang);
    return $s === "\xE2\x80\x94" ? $s : mel_gc_rate(max(8.0 * $bps, 1e5), false, $lang);
}

// The status of a wait of a start through TorrServer by TorrentStatus $j, in words: its status (none: the file list
// awaited), or with the preload ($buf: the key of its line, '' none) the MB of it of its target (mel_ts_buf_need). The
// waiting dialog of a start from the list of releases and from the section alike (mel_ts_wait_ctls).
/**
 * @param array<mixed>|null $j
 * @param string $buf
 * @param string $lang
 * @return string
 */
function mel_ts_wait_status($j, $buf, $lang)
{
    if ($buf !== '')
    {
        $need = mel_ts_buf_need($j);
        $got = min(mel_num(mel_str($j, 'preloaded_bytes')), $need);
        return str_replace(array('{got}', '{need}'), array(strval(intval(floor($got / 1048576))),
            strval(intval(ceil($need / 1048576)))), mel_tr($lang, $buf));
    }
    $stat = mel_one_line(mel_str($j, 'stat_string'));
    $keys = mel_ts_stats();
    $low = strtolower($stat);
    return $stat === '' ? mel_tr($lang, 'ts_wait_files') : (isset($keys[$low]) ? mel_tr($lang, $keys[$low]) :
        mel_cut($stat, 30));
}

// Controls of the waiting dialog (0.45.0) of row $r of $st (mel_ts_wait_ctls; $lazy: «Отмена» stops the player).
/**
 * @param MelState $st
 * @param MelRow $r
 * @param int $sec
 * @param array<mixed>|null $j
 * @param bool $lazy
 * @param bool $buf
 * @return list<array<string, mixed>>
 */
function mel_ts_wait_defs($st, $r, $sec, $j, $lazy = false, $buf = false)
{
    return mel_ts_wait_ctls($st['lang'], $r['label'], $r['rate'], $sec, $j, $buf, mel_input($lazy ? 'next_stop' : 'ts_cancel'));
}

// Controls of the waiting dialog: the label $label of what opens; the status in words (none: "waiting for the file
// list"; $buf, the preload: the MB of its target), seconds $sec, peers, connected seeders of TorrentStatus $j; the
// download speed (mel_ts_speed), always: the window keeps its height; with $buf the bit rate $rate, if known (> 0);
// "Cancel" with action $cancel. The start from the list of releases and from the section "На TorrServer" alike.
/**
 * @param string $lang
 * @param string $label
 * @param float $rate
 * @param int $sec
 * @param array<mixed>|null $j
 * @param bool $buf
 * @param MelAction $cancel
 * @return list<array<string, mixed>>
 */
function mel_ts_wait_ctls($lang, $label, $rate, $sec, $j, $buf, $cancel)
{
    $status = mel_ts_wait_status($j, $buf ? 'ts_wait_buf' : '', $lang);
    $defs = array();
    foreach (mel_dialog_lines($label) as $l)
        $defs[] = mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l));
    $defs[] = mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
        str_replace(array('{status}', '{sec}'), array($status, $sec), mel_tr($lang, 'ts_wait_line'))));
    $defs[] = mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => str_replace(array('{peers}', '{all}', '{seeds}'),
        array(mel_ts_int($j, 'active_peers'), mel_ts_int($j, 'total_peers'), mel_ts_int($j, 'connected_seeders')),
        mel_tr($lang, 'ts_wait_peers'))));
    $need = $buf && $rate > 0;
    $defs[] = mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => str_replace(array('{speed}', '{rate}'),
        array(mel_ts_speed(is_array($j) && isset($j['download_speed']) ? $j['download_speed'] : null, $lang),
        $need ? mel_gc_rate($rate, false, $lang) : ''), mel_tr($lang, $need ? 'ts_wait_speed_need' : 'ts_wait_speed'))));
    $defs[] = mel_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => mel_tr($lang, 'ts_cancel'),
        GuiButtonDef::width => 300, GuiButtonDef::push_action => $cancel));
    return $defs;
}

// Timer of the waiting dialog: "get" of the torrent; its files -> playback, from 0 the preload of the
// file first (mel_ts_preload), then playback when it is done (mel_ts_buf_done) or the time is out;
// still none -> the timer again, the text redrawn; gone or too long with no files -> error.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_ts_tick($in)
{
    $p = array();
    foreach (array('rid' => '/^[0-9a-f]{12}$/D', 'i' => '/^[0-9]{1,3}$/D', 'pos' => '/^[0-9]{1,7}$/D',
        'f' => '/^([0-9a-f]{12})?$/D', 'l' => '/^[012]$/D', 't' => '/^[0-9]{1,12}$/D', 'n' => '/^[0-9]{1,4}$/D',
        'b' => '/^([0-9]{1,4})?$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
        {
            mel_log("ts: tick with a bad $k");
            return mel_close_and(mel_error('err_list_expired'));
        }
        $p[$k] = $in->$k;
    }
    $i = intval($p['i']);
    // The list on screen, or the one of a lazy episode (mel_next).
    $st = Mel::$state && Mel::$state['rid'] === $p['rid'] ? Mel::$state : Mel::$tswait;
    if (!$st || $p['rid'] !== $st['rid'] || !isset($st['rows'][$i]) || $st['rows'][$i]['hash'] === '')
    {
        mel_log('ts: tick of a list gone');
        return mel_close_and(mel_error('err_list_expired'));
    }
    // The list of the dialog, not always the one on screen (mel_op_of_screen): the pair of its profiles - the record, the
    // links of the next episodes and the markers of its source, not of the main one (spec v2 §1.9).
    mel_op_use($st['prof'], $st['lp_prof']);
    $r = $st['rows'][$i];
    $lang = $st['lang'];
    $sec = max(0, time() - intval($p['t']));
    $p['n'] = strval(intval($p['n']) + 1);
    list($rg, $j) = mel_ts_get($r['hash']);
    $err = $rg['err'];
    $code = $rg['code'];
    $files = mel_ts_files($j);
    mel_log(sprintf('ts: tick %s, %d s: %s, stat %s, peers %s/%s, seeders %s, %d files', $p['n'], $sec,
        $err !== '' ? $err : "HTTP $code", is_array($j) && isset($j['stat']) ? json_encode($j['stat']) : '-',
        is_array($j) && isset($j['active_peers']) ? json_encode($j['active_peers']) : '-',
        is_array($j) && isset($j['total_peers']) ? json_encode($j['total_peers']) : '-',
        is_array($j) && isset($j['connected_seeders']) ? json_encode($j['connected_seeders']) : '-', count($files)) .
        ($p['b'] !== '' ? sprintf(', preloaded %.0f of %.0f', mel_num(mel_str($j, 'preloaded_bytes')),
        mel_num(mel_str($j, 'preload_size'))) : ''));
    // The same rules of the wait (401, 404, the time out) for a start from the section: mel_tsl_step (ts_list.php).
    $out = $sec >= MEL_TS_WAIT || intval($p['n']) >= MEL_TS_TICKS;
    $play = '';
    if ($files && $p['b'] === '')
    {
        if (intval($p['pos']) > 0 || $out || mel_ts_preload($st, $i, $files, $j) !== '')
            $play = 'the files';
        else
            $p['b'] = $p['n'];
    }
    else if ($files)
        $play = $out ? 'the time out' : mel_ts_buf_done($j, intval($p['n']) - intval($p['b']));
    if ($play !== '')
    {
        if ($p['b'] !== '')
            mel_log("ts: -> the player: $play");
        return mel_close_and(mel_ts_play($st, $i, $files, intval($p['pos']), $p['f'], $p['l'] === '1', $p['l'] === '2'));
    }
    if ($err === '' && $code === 401)
        return mel_close_and(mel_ts_fail($lang, mel_ts_auth_err(), '', $p['l'] === '1'));
    // 404: TorrServer has it neither in memory nor in its DB (closed).
    if ($err === '' && $code === 404)
        return mel_close_and(mel_ts_fail($lang, 'ts_err_gone', '', $p['l'] === '1'));
    if ($out)
        return mel_close_and(mel_ts_fail($lang, 'ts_err_timeout', $err !== '' ? $err : '', $p['l'] === '1'));
    return mel_ts_wait_again(mel_ts_wait_defs($st, $r, $sec, $j, $p['l'] === '1', $p['b'] !== ''), mel_ts_wait_acts($p));
}

// The row of a start through TorrServer for its lazy episodes: without the name,
// the size and the length of the file played, of another episode: mel_markers
// would drop the markers past its end.
/**
 * @param MelRow $r
 * @return MelRow
 */
function mel_ts_pack_row($r)
{
    $raw = $r['raw'];
    $bh = mel_arr($raw, 'behaviorHints');
    $sd = mel_arr($raw, 'streamData');
    $pf = mel_arr($sd, 'parsedFile');
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
 * @return MelState|null
 */
function mel_ts_pack_state($key, $s, $e, $hash)
{
    $p = Mel::$ts_pack;
    $saved = $p && $p['key'] === $key && $p['hash'] === $hash && isset($p['map'][$s][$e]) ? mel_movie_load($key) : null;
    if (!$saved)
        return null;
    $st = mel_list_state($saved['movie'], $s, $e, array($p['row']), $saved['lang'], mel_no_errors());
    $st['pack_only'] = true;
    $st['pack_map'] = $p['map'];
    return $st;
}

// The file of row $i of $st among $files -> array(key of $files or -1, why, the map of a state of
// mel_ts_pack_state or null). That state: the file of its map; else the file by mel_ts_pick.
/**
 * @param MelState $st
 * @param int $i
 * @param list<MelTsFile> $files
 * @return array{int, string, array<int, array<int, int>>|null}
 */
function mel_ts_file($st, $i, $files)
{
    $map = isset($st['pack_map']) ? $st['pack_map'] : null;
    if ($map !== null)
    {
        $k = -1;
        foreach ($files as $n => $f)
        {
            if ($f['id'] === $map[$st['s']][$st['e']])
                $k = $n;
        }
        return array($k, $k >= 0 ? 'the map' : 'no file of the map', $map);
    }
    list($k, $by) = mel_ts_pick($files, $st['rows'][$i], $st['s'], $st['e'], count($st['movie']['seasons']) === 1,
        mel_cin_n($st['movie']['cin'], $st['s'], $st['e']));
    return array($k, $by, null);
}

// The file of the row among $files (mel_ts_file) -> vod_play (the files of its map as ready
// URLs, mel_ts_map_start), or the dialog "no video" ($lazy: "not in this release"
// of a lazy episode; $miss_list: the list, as mel_ts_open). A state of
// mel_ts_pack_state: the file and the playlist of its map, the map stays; else
// a new map.
/**
 * @param MelState $st
 * @param int $i
 * @param list<MelTsFile> $files
 * @param int $pos
 * @param string $from
 * @param bool $lazy
 * @param bool $miss_list
 * @return MelAction
 */
function mel_ts_play($st, $i, $files, $pos, $from, $lazy = false, $miss_list = false)
{
    $r = $st['rows'][$i];
    list($k, $by, $map) = mel_ts_file($st, $i, $files);
    if ($k < 0)
    {
        mel_log("ts: {$by}, " . count($files) . ' files');
        if ($lazy)
            return mel_next_missing($st, $r['hash'], mel_no_errors());
        if ($miss_list)
            return mel_open_list(mel_list_cursor($st, $r['hash']));
        return mel_ts_fail($st['lang'], $st['e'] > 0 ? 'ts_err_no_ep' : 'ts_err_no_video');
    }
    if ($map === null)
        $map = mel_ts_map_start($st, $r, $files, $k);
    $base = Mel::$settings['ts'];
    list($eps, $others) = mel_ts_map_urls($base, $r['hash'], $files, $map, $st['s'], $st['e']);
    mel_log("ts: file {$files[$k]['id']} of " . count($files) . " ($by): " . mel_cut($files[$k]['path'], 200) .
        ($st['e'] > 0 ? ', other episodes in the pack: ' . (count($eps) ? implode(' ', array_keys($eps)) : 'none') : ''));
    if ($others)
        mel_log('ts: other seasons in the pack: ' . implode(' ', array_keys($others)));
    mel_playing($from, $st, $i);
    return mel_play($st, $r, $pos, array('url' => mel_ts_url($base, $r['hash'], $files[$k]), 'eps' => $eps, 'others' => $others));
}

// gc_ts (MENU "Watch via TorrServer") of row i of a live list.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_ts_menu($in)
{
    $st = Mel::$state;
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !isset($st['rows'][$i]) || $st['rows'][$i]['hash'] === '')
    {
        mel_log('ts: menu of no live row with infoHash');
        return mel_error('err_list_expired');
    }
    return mel_ts_open($st, $i, 0, $st['rid']);
}
