<?php
// A start from the section "На TorrServer" (view_ts.php; its run and step: ts_list.php): "get" by the hash, never "add"
// ("add" would rewrite the title, the poster and the time of the entry and lift it up the list). file_stats at the
// first "get" - on, else the kit wait screen (W2 of the UI kit, owner 19.11: a wait from the rows is a screen of its
// own) with a "get" every MEL_TS_TICK_MS; from the start the preload of the file first (mel_ts_preload_file, as a start
// from the list of releases), from a position none. Then vod_play of our own (not mel_play: no card, no dune_id): the
// streams of TorrServer as ready URLs, a pack in the natural order of its paths (mel_tsl_playlist), the history by
// recent_uid "aiots:<hash>:<file id>" and recent_plugin ours with no mdb_info (with no movie_id it breaks the start,
// stage 0 of the spec), INFO the window of TorrServer, no markers. The record of "Последнее" brings us back by
// tsl_recent with wh_pos. The errors: the kit error screen, the texts of a start from the list of releases
// (mel_ts_fail). Two video files and more: the screen of files (stage 3b; for now a line of the log).
// Needs ts_list.php, view_ts.php, torrserver.php, ts_info.php, kit_scr.php, kit_dlg.php, main.php. PHP 5.3.6, firmware
// API only.

// The start of file $id of entry $e (0: by file_stats, one video file) from position $pos (s) from the rows of the
// section or "Последнее": the first "get", then vod_play, the screen of files, an error or the wait screen.
/**
 * @param MelTsEntry $e
 * @param int $id
 * @param int $pos
 * @return MelAction|null
 */
function mel_tsl_start($e, $id, $pos)
{
    $lang = mel_catv_lang();
    if (Mel::$settings['no_torrents'])
        return mel_tsl_fail_act($lang, 'ts_err_off', '');
    $n = $e['files'] === null ? '-' : strval(count($e['files']));
    mel_log("ts section: start {$e['hash']} file " . ($id > 0 ? $id : '?') . ", pos $pos, $n files known");
    list($what, $d, $why, $detail, $files, $j) = mel_tsl_advance(mel_tsl_run($e, $id, $pos, $lang), 0);
    if ($what === 'wait')
    {
        list($text, $live, $share) = mel_tsl_wait_texts($j, $d['b'] >= 0, $lang);
        return mel_ks_wait(array_merge(mel_tsl_look($lang), array('icon' => 'k_icon_monitor-play',
            'title' => mel_tr($lang, 'ts_wait'), 'text' => $text, 'live' => $live, 'share' => $share,
            'elapsed' => mel_tr($lang, 'ks_wait_elapsed'), 'tick' => 'mel_tsl_tick', 'data' => $d,
            'buttons' => array(array('id' => 'cancel', 'label' => mel_tr($lang, 'ts_cancel'), 'icon' => 'k_icon_x',
                'cancel' => true)))), mel_tr($lang, 'tsl_title'), MEL_TS_TICK_MS);
    }
    if ($what === 'play')
        return mel_tsl_play_act($d, $files, $why);
    if ($what === 'files')
        return mel_tsl_files_open($e['hash'], mel_tsl_files_known($e['hash'], $files));
    return mel_tsl_fail_act($lang, $why, $detail);
}

// The tick of a wait of a start (MelWaitDesc tick; the wait screen here, the wait window over the screen of files of
// stage 3b alike): run $d, $sec s since the start -> post vod_play; post the screen of files (by tsl_files: the wait
// closes first); dialog an error; else the run and its lines.
/**
 * @param mixed $d
 * @param int $sec
 * @return array<string, mixed>|null
 */
function mel_tsl_tick($d, $sec)
{
    if (!is_array($d) || !isset($d['e']['hash'], $d['f'], $d['pos'], $d['n'], $d['b'], $d['l']))
        return null;
    /** @var MelTslRun $d */
    list($what, $d, $why, $detail, $files, $j) = mel_tsl_advance($d, $sec);
    $hash = $d['e']['hash'];
    if ($what === 'play')
        return array('post' => mel_tsl_play_act($d, $files, $why));
    if ($what === 'files')
    {
        mel_tsl_files_known($hash, $files);
        return array('post' => mel_input('tsl_files', array('h' => $hash)));
    }
    if ($what === 'error')
        return array('dialog' => mel_tsl_fail_desc($d['l'], $why, $detail));
    list($text, $live, $share) = mel_tsl_wait_texts($j, $d['b'] >= 0, $d['l']);
    return array('data' => $d, 'text' => $text, 'live' => $live, 'share' => $share);
}

// The look of the screens of a start: the background and the head of Melange, «На TorrServer» under it.
/**
 * @param string $lang
 * @return array<string, mixed>
 */
function mel_tsl_look($lang)
{
    return array('bg' => '', 'sub' => mel_tr($lang, 'tsl_title'));
}

// The error of a start (MelDlgDesc): the title and the text of a start from the list of releases (mel_ts_fail; an error
// of the list, tsl_err_big and tsl_err_bad: its text, mel_tsl_err_texts), $detail under them, «Назад».
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @return array<string, mixed>
 */
function mel_tsl_fail_desc($lang, $key, $detail)
{
    if ($key === 'tsl_err_big' || $key === 'tsl_err_bad')
        list(, $text) = mel_tsl_err_texts($key, $lang, '');
    else
        $text = mel_tr($lang, $key);
    return array('icon' => 'k_icon_triangle-alert', 'tone' => 'warn', 'title' => mel_tr($lang, 'ts_failed'), 'text' => $text,
        'detail' => $detail, 'buttons' => array(array('label' => mel_tr($lang, 'ks_back'), 'icon' => 'k_icon_arrow-left')));
}

// The kit error screen of a start (mel_tsl_fail_desc). RETURN and «Назад»: back where it came from.
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @return MelAction
 */
function mel_tsl_fail_act($lang, $key, $detail)
{
    mel_log("ts section: error screen $key");
    return mel_ks_error(array_merge(mel_tsl_look($lang), mel_tsl_fail_desc($lang, $key, $detail)), mel_tr($lang, 'tsl_title'));
}

// vod_play of file f of run $d among $files (mel_tsl_playlist): a movie one item, a pack its video files (a window of
// MEL_EPISODES_MAX), the history of each by its uid, recent_action_params to tsl_recent, from the position of the run;
// $why: the reason of the start, for the log. f not a video file there: the error.
/**
 * @param MelTslRun $d
 * @param list<MelTsFile> $files
 * @param string $why
 * @return MelAction
 */
function mel_tsl_play_act($d, $files, $why)
{
    $e = $d['e'];
    $pl = mel_tsl_playlist($e, $files, $d['f'], Mel::$settings['ts']);
    if ($pl === null)
        return mel_tsl_fail_act($d['l'], 'ts_err_no_video', '');
    list($items, $ndx, $all) = $pl;
    $series = array();
    foreach ($items as $it)
        $series[] = array(
            PluginVodSeriesInfo::name => $it['name'],
            PluginVodSeriesInfo::playback_url => $it['url'],
            PluginVodSeriesInfo::playback_url_is_stream_url => true,
            PluginVodSeriesInfo::recent_uid => $it['uid']);
    $info = array(
        PluginVodInfo::id => MEL_TSL_UID . ':' . $e['hash'],
        PluginVodInfo::name => $e['title'],
        PluginVodInfo::poster_url => preg_match('~^https?://~i', $e['poster']) ? $e['poster'] : '',
        PluginVodInfo::series => $series,
        PluginVodInfo::initial_series_ndx => $ndx,
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => true,
        PluginVodInfo::skip_dummy_player_on_error => false,
        PluginVodInfo::recent_plugin => MEL_PLUGIN,
        PluginVodInfo::recent_action_params => array('handler_id' => 'aio', 'control_id' => 'tsl_recent', 'h' => $e['hash'],
            'f' => strval($d['f'])));
    if ($d['pos'] > 0)
        $info[PluginVodInfo::initial_position_ms] = $d['pos'] * 1000;
    // INFO: the window of TorrServer; the actions of the player are its own whole (ts_info.php).
    $info += mel_tsi_vod_of($e['hash'], $e['title'], $d['l']);
    mel_log(sprintf('ts section: play %s file %d (%s), playlist %d of %d video files, item %d, pos %d: %s', $e['hash'], $d['f'],
        $why, count($series), $all, $ndx, $d['pos'], mel_cut($items[$ndx]['path'], 200)));
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}

// The files $files of file_stats of torrent $hash kept in its entry in memory, if it knew none (a record of Lampa,
// other data): the screen of files reads them there -> $files.
/**
 * @param string $hash
 * @param list<MelTsFile> $files
 * @return list<MelTsFile>
 */
function mel_tsl_files_known($hash, $files)
{
    if (isset(MelTsl::$list[$hash]) && MelTsl::$list[$hash]['files'] === null)
        MelTsl::$list[$hash]['files'] = $files;
    return $files;
}

// The screen of the files $files of torrent $hash (two video files and more): stage 3b (its open, MelAction). For now a
// line of the log.
/**
 * @param string $hash
 * @param list<MelTsFile> $files
 * @return null
 */
function mel_tsl_files_open($hash, $files)
{
    mel_log("ts section: files of $hash: " . count(mel_tsl_videos_of($files)) . ' video files, the screen of files (stage 3b)');
    return null;
}

// The entry of torrent $hash: in memory, else the list read once (a restart of php_server, an old screen) -> array(the
// entry or null: not on TorrServer; the key of an error of the list, '' none; its detail).
/**
 * @param string $hash
 * @return array{MelTsEntry|null, string, string}
 */
function mel_tsl_find($hash)
{
    if (isset(MelTsl::$list[$hash]))
        return array(MelTsl::$list[$hash], '', '');
    mel_log("ts section: $hash not in memory, the list read");
    list($list, $key, $detail) = mel_tsl_read();
    if ($list === null)
        return array(null, $key, $detail);
    return array(isset(MelTsl::$list[$hash]) ? MelTsl::$list[$hash] : null, '', '');
}

// tsl_recent: a record of the section chosen in "Последнее" of the Dune - the file of its wh_uid
// ("aiots:<hash>:<id>": the item played of a playlist), else h and f of recent_action_params, from wh_pos (s) - the
// start of it (mel_tsl_start; no preload from a position). tsl_files: the screen of files after a wait (h).
/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_tsl_input($in, $control)
{
    $uid = isset($in->wh_uid) && is_string($in->wh_uid) ? $in->wh_uid : '';
    $h = isset($in->h) && is_string($in->h) ? $in->h : '';
    $f = isset($in->f) && is_string($in->f) ? $in->f : '';
    if ($control === 'tsl_recent' && preg_match('/^' . MEL_TSL_UID . ':([0-9a-f]{40}):([0-9]{1,6})$/D', $uid, $m))
        list(, $h, $f) = $m;
    if (!preg_match('/^[0-9a-f]{40}$/D', $h) || ($control === 'tsl_recent' && (!preg_match('/^[0-9]{1,6}$/D', $f) || intval($f) < 1)))
    {
        mel_log("ts section: $control with a bad hash or file, none");
        return null;
    }
    if ($control === 'tsl_files')
    {
        if (!isset(MelTsl::$list[$h]) || MelTsl::$list[$h]['files'] === null)
        {
            mel_log("ts section: files of $h not in memory, none");
            return null;
        }
        return mel_tsl_files_open($h, MelTsl::$list[$h]['files']);
    }
    $p = isset($in->wh_pos) ? $in->wh_pos : null;
    $pos = (is_string($p) && preg_match('/^[0-9]{1,7}$/D', $p)) || (is_int($p) && $p >= 0) ? intval($p) : 0;
    mel_log("ts section: recent $h file $f, wh_pos $pos");
    $lang = mel_catv_lang();
    list($e, $key, $detail) = mel_tsl_find($h);
    if ($key !== '')
        return mel_tsl_fail_act($lang, $key, $detail);
    if ($e === null)
        return mel_tsl_fail_act($lang, 'tsl_err_gone', '');
    return mel_tsl_start($e, intval($f), $pos);
}
