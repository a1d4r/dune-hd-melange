<?php
// "Watch via TorrServer" (0.31.0): the magnet of a row to TorrServer MatriX
// (POST /torrents add, save_to_db false), its file list awaited by a dialog
// with a timer, then vod_play of /stream/<name>?link=<hash>&index=<id>&play:
// the Dune player buffers a TorrServer stream itself. The episodes of the
// pack go to the playlist as such URLs; the player switches them alone.
// TorrServer drops a torrent nobody reads after its own timeout: no drop/rem.
// Needs of main.php: Aio, aio_log, aio_tr, aio_dialog, aio_dialog_act,
// aio_ctl, aio_input, aio_close_and, aio_error; of parse.php: aio_str,
// aio_arr, aio_num, aio_cut, aio_dialog_lines, aio_magnet; of playback.php:
// aio_play, aio_playing, aio_ep_name, aio_ep_tag.

// Dialog ticks: every AIO_TS_TICK_MS a "get"; the file list awaited
// AIO_TS_WAIT s at most (Online movies: 60 ticks of 950 ms), and AIO_TS_TICKS
// ticks at most: the clock of the Dune may jump (NTP).
define('AIO_TS_TICK_MS', 1000);
define('AIO_TS_WAIT', 60);
define('AIO_TS_TICKS', 60);
// A bigger reply is not read (a pack of thousands of files is ~1 MB).
define('AIO_TS_MAX_BYTES', 4194304);

// Tests define their own aio_ts_call().
if (!function_exists('aio_ts_call'))
{
    // GET ($post null) or POST of JSON $post to TorrServer -> array(HTTP
    // code, body); $err is set on a transport error. http only, no redirects;
    // the errors of TorrServer have an empty body: the code tells.
    function aio_ts_call($url, $post, &$err, $total = 2)
    {
        $body = '';
        $over = false;
        $ch = curl_init($url);
        $opts = array(
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => $total,
            CURLOPT_USERAGENT => 'Melange-Dune/' . AIO_VERSION,
            CURLOPT_HTTPHEADER => array('Expect:', 'Content-Type: application/json'),
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$body, &$over)
            {
                if (strlen($body) + strlen($chunk) > AIO_TS_MAX_BYTES)
                {
                    $over = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            });
        if (!is_null($post))
        {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $post;
        }
        if (!curl_setopt_array($ch, $opts))
        {
            curl_close($ch);
            $err = 'curl options refused';
            return array(0, '');
        }
        $ok = curl_exec($ch);
        $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $err = $over ? 'reply over ' . AIO_TS_MAX_BYTES . ' bytes' :
            ($ok === false ? 'curl ' . curl_errno($ch) . ': ' . curl_error($ch) : '');
        curl_close($ch);
        return array($code, $err !== '' ? '' : $body);
    }
}

// TorrServer MatriX answers /echo within a second ("Continue watching" asks
// before it goes there).
function aio_ts_alive()
{
    $err = '';
    list($code, $body) = aio_ts_call(Aio::$settings['ts'] . '/echo', null, $err, 1);
    $ok = $err === '' && $code === 200 && strpos($body, 'MatriX.') === 0;
    aio_log('ts: ' . Aio::$settings['ts'] . ' echo: ' . ($ok ? 'MatriX' : ($err !== '' ? $err : "HTTP $code")));
    return $ok;
}

// A TorrentStatus of TorrServer -> array or null. Sizes over 2 GB are quoted
// first: json_decode of PHP 5.3 on the 32-bit Dune clamps them.
function aio_ts_decode($body)
{
    $body = preg_replace('/("(?:length|torrent_size|loaded_size|preloaded_bytes|preload_size|bytes_[a-z_]+)"\s*:\s*)' .
        '([0-9]{10,})/', '$1"$2"', $body);
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

// file_stats of a TorrentStatus -> list of array('id' => int, 'path' =>
// string, 'size' => float), as TorrServer sorts them (ids from 1).
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

function aio_ts_video($path)
{
    return preg_match('/\.(mkv|mp4|m4v|avi|mov|wmv|ts|m2ts|mts|mpg|mpeg|vob|webm|flv|divx)$/iD', $path) === 1;
}

function aio_ts_basename($path)
{
    $p = strrpos($path, '/');
    return $p === false ? $path : substr($path, $p + 1);
}

function aio_ts_dirname($path)
{
    $p = strrpos($path, '/');
    return $p === false ? '' : substr($path, 0, $p);
}

// Season and episode by the name of a file -> array(season or null, episode)
// or null: S01E02, s1.e2, 1x02; else E02 / EP02 / "Серия 2" / "2 серия" /
// " - 07" (season unknown). Only to find a file in a known pack.
function aio_ts_ep($path)
{
    $n = aio_ts_basename($path);
    if (preg_match('/(?<![a-z0-9])s([0-9]{1,2})[ ._\-]?e([0-9]{1,3})(?![0-9])/i', $n, $m) ||
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

// A folder of the path names season $s ("Season 1", "S01", "1 сезон").
function aio_ts_dir_season($path, $s)
{
    return preg_match('/(?<![a-z0-9])(?:s|season[ ._\-]?)0*' . $s . '(?![0-9])|(?<![0-9])0*' . $s .
        '[ ._\-]*сезон|сезон[ ._\-]*0*' . $s . '(?![0-9])/iu', aio_ts_dirname($path)) === 1;
}

// Files of episode $e of season $s among $files (video only): its season in
// the name, else an unknown season with the season in the folder, else any
// unknown season. -> keys of $files.
function aio_ts_ep_files($files, $s, $e)
{
    $named = array();
    $dir = array();
    $any = array();
    foreach ($files as $k => $f)
    {
        $ep = aio_ts_video($f['path']) ? aio_ts_ep($f['path']) : null;
        if (!$ep || $ep[1] !== $e || ($ep[0] !== null && $ep[0] !== $s))
            continue;
        if ($ep[0] !== null)
            $named[] = $k;
        else if (aio_ts_dir_season($f['path'], $s))
            $dir[] = $k;
        else
            $any[] = $k;
    }
    return $named ? $named : ($dir ? $dir : $any);
}

// The file of row $r in $files -> array(key of $files or -1, why). The one
// AIOStreams chose: its name (behaviorHints.filename) and size (videoSize);
// else an episode by the name of the file; else, for a movie, the biggest video.
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
    // An episode: the file named by AIOStreams is left out only when the pack
    // has a file of this very episode, its season in the name (a reply of
    // another episode for this one); else it stays - absolute numbering of
    // anime ("- 13" or "S01E13" for S02E01, "- 01" is not S02E01).
    $eps = $e > 0 ? aio_ts_ep_files($files, $s, $e) : array();
    $ep0 = $eps ? aio_ts_ep($files[$eps[0]]['path']) : null;
    if ($ep0 && $ep0[0] !== null)
        $by_name = array_values(array_intersect($by_name, $eps));
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
function aio_ts_url($base, $hash, $f)
{
    return "$base/stream/" . rawurlencode(aio_ts_basename($f['path'])) . "?link=$hash&index={$f['id']}&play";
}

// Episodes of season $s in the pack up to $max, besides $e (the file $chosen):
// episode => URL. Each name parsed once (a pack may have thousands of files).
// Its season in the name first, then a file of the folder of $chosen; an
// unknown season counts only in that folder.
function aio_ts_episodes($base, $hash, $files, $s, $e, $chosen, $max)
{
    $dir = aio_ts_dirname($files[$chosen]['path']);
    $best = array();
    foreach ($files as $k => $f)
    {
        $ep = aio_ts_video($f['path']) ? aio_ts_ep($f['path']) : null;
        if (!$ep || $ep[1] < 1 || $ep[1] > $max || $ep[1] === $e || ($ep[0] !== null && $ep[0] !== $s))
            continue;
        $same = aio_ts_dirname($f['path']) === $dir;
        if ($ep[0] === null && !$same)
            continue;
        $rank = ($ep[0] !== null ? 2 : 0) + ($same ? 1 : 0);
        if (!isset($best[$ep[1]]) || $rank > $best[$ep[1]][0])
            $best[$ep[1]] = array($rank, $k);
    }
    ksort($best);
    $out = array();
    foreach ($best as $i => $b)
        $out[$i] = aio_ts_url($base, $hash, $files[$b[1]]);
    return $out;
}

// --- The chain.

// "Could not open via TorrServer": the reason, its detail, OK; over the
// stopped player of a lazy episode ($lazy) - "Stop", as aio_next_failed.
function aio_ts_fail($lang, $key, $detail = '', $lazy = false)
{
    aio_log("ts: -> dialog: $key" . ($detail !== '' ? " ($detail)" : ''));
    return aio_dialog(aio_tr($lang, 'ts_failed'), array_merge(aio_dialog_lines(aio_tr($lang, $key)),
        aio_dialog_lines($detail)), $lazy ? array(aio_tr($lang, 'next_stop') => aio_input('next_stop')) :
        array('OK' => aio_close_and(null)));
}

// Row $i of list $st through TorrServer from position $pos (s); $from: rid of
// the list screen under the player, '' for play_action; $lazy: a lazy
// episode of the player (aio_next): no file of it -> aio_next_missing.
// -> vod_play when the files are known at once, else the dialog that waits
// for them (its state in Aio::$tswait when $st is not the list on screen); an
// error dialog. $alive: aio_ts_alive() has just answered, no echo again.
function aio_ts_open($st, $i, $pos, $from, $lazy = false, $alive = false)
{
    $r = $st['rows'][$i];
    $lang = $st['lang'];
    $base = Aio::$settings['ts'];
    $tag = aio_ep_tag($st) . ", row $i, hash {$r['hash']}";
    $err = '';
    if (!$alive)
    {
        $t = microtime(true);
        list($code, $body) = aio_ts_call("$base/echo", null, $err, 2);
        $ver = $err === '' && $code === 200 ? aio_cut(trim($body), 40) : '';
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
        'poster' => $st['movie']['poster'], 'save_to_db' => false));
    $t = microtime(true);
    list($code, $body) = aio_ts_call("$base/torrents", $req, $err, 5);
    $j = $err === '' && $code === 200 ? aio_ts_decode($body) : null;
    $files = aio_ts_files($j);
    // The magnet is not logged: a private tracker carries a passkey.
    aio_log(sprintf('ts: add, %d trackers (%s): %s, %d files, %.2f s', $n, $src, $err !== '' ? $err : "HTTP $code",
        count($files), microtime(true) - $t));
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

function aio_ts_wait_acts($p)
{
    return array(GUI_EVENT_TIMER => aio_input('ts_tick', $p));
}

// Controls of the waiting dialog: the release, seconds and peers, "Cancel".
function aio_ts_wait_defs($st, $r, $sec, $j, $lazy = false)
{
    $lang = $st['lang'];
    $peers = is_array($j) && isset($j['active_peers']) && is_int($j['active_peers']) ? $j['active_peers'] : 0;
    $all = is_array($j) && isset($j['total_peers']) && is_int($j['total_peers']) ? $j['total_peers'] : 0;
    $defs = array();
    foreach (aio_dialog_lines($r['label']) as $l)
        $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l));
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
        str_replace(array('{sec}', '{peers}', '{all}'), array($sec, $peers, $all), aio_tr($lang, 'ts_wait_line'))));
    $defs[] = aio_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => aio_tr($lang, 'ts_cancel'),
        GuiButtonDef::width => 300, GuiButtonDef::push_action => aio_input($lazy ? 'next_stop' : 'ts_cancel')));
    return $defs;
}

// Timer of the waiting dialog: "get" of the torrent; its files -> playback;
// still none -> the timer again, the text redrawn; gone or too long -> error.
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
    $err = '';
    list($code, $body) = aio_ts_call(Aio::$settings['ts'] . '/torrents',
        json_encode(array('action' => 'get', 'hash' => $r['hash'])), $err, 2);
    $j = $err === '' && $code === 200 ? aio_ts_decode($body) : null;
    $files = aio_ts_files($j);
    aio_log(sprintf('ts: tick %s, %d s: %s, stat %s, peers %s/%s, %d files', $p['n'], $sec,
        $err !== '' ? $err : "HTTP $code", is_array($j) && isset($j['stat']) ? json_encode($j['stat']) : '-',
        is_array($j) && isset($j['active_peers']) ? json_encode($j['active_peers']) : '-',
        is_array($j) && isset($j['total_peers']) ? json_encode($j['total_peers']) : '-', count($files)));
    if ($files)
        return aio_close_and(aio_ts_play($st, $i, $files, intval($p['pos']), $p['f'], $p['l'] === '1'));
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

// The file of the row among $files -> vod_play (the pack's episodes of the
// season as ready URLs), or the dialog "no video" ($lazy: "not in this
// release" of a lazy episode).
function aio_ts_play($st, $i, $files, $pos, $from, $lazy = false)
{
    $r = $st['rows'][$i];
    list($k, $by) = aio_ts_pick($files, $r, $st['s'], $st['e']);
    if ($k < 0)
    {
        aio_log("ts: {$by}, " . count($files) . ' files');
        if ($lazy)
            return aio_next_missing($st, $r['hash']);
        return aio_ts_fail($st['lang'], $st['e'] > 0 ? 'ts_err_no_ep' : 'ts_err_no_video');
    }
    $base = Aio::$settings['ts'];
    $seasons = $st['movie']['seasons'];
    $eps = $st['e'] > 0 ? aio_ts_episodes($base, $r['hash'], $files, $st['s'], $st['e'], $k,
        isset($seasons[$st['s']]) ? min($seasons[$st['s']], AIO_EPISODES_MAX) : 0) : array();
    aio_log("ts: file {$files[$k]['id']} of " . count($files) . " ($by): " . aio_cut($files[$k]['path'], 200) .
        ($st['e'] > 0 ? ', other episodes in the pack: ' . (count($eps) ? implode(' ', array_keys($eps)) : 'none') : ''));
    aio_playing($from, $st, $i);
    return aio_play($st, $r, $pos, array('url' => aio_ts_url($base, $r['hash'], $files[$k]), 'eps' => $eps));
}

// gc_ts (MENU "Watch via TorrServer") of row i of a live list.
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
