<?php
// A start from the section "На TorrServer" (view_ts.php; its run and step: ts_list.php): "get" by the hash, never "add"
// ("add" would rewrite the title, the poster and the time of the entry and lift it up the list). file_stats at the
// first "get" - on, else the waiting dialog of a start from the list of releases (torrserver.php, owner 23.11: the same
// window from the rows, the screen of files and "Последнее") with a "get" every MEL_TS_TICK_MS (tsl_tick); from the start
// the preload of the file first (mel_ts_preload_file), from a position none. Then vod_play of our own (not mel_play: no
// card, no dune_id): the streams of TorrServer as ready URLs, a pack in the natural order of its paths
// (mel_tsl_playlist), the history by recent_uid "aiots:<hash>:<file id>" and recent_plugin ours with no mdb_info (with
// no movie_id it breaks the start, stage 0 of the spec), INFO the window of TorrServer, no markers. The record of
// "Последнее" brings us back by tsl_recent with wh_pos. The errors: the dialog of a start from the list of releases
// (mel_ts_fail). Two video files and more: the screen of files (view_ts_files.php).
// Needs ts_list.php, view_ts.php, view_ts_files.php, torrserver.php, ts_info.php, main.php. PHP 5.3.6, firmware API
// only.

// The start of file $id of entry $e (0: by file_stats, one video file) from position $pos (s): the first "get", then
// vod_play, the screen of files, the error dialog or the waiting dialog over the screen it came from.
/**
 * @param MelTsEntry $e
 * @param int $id
 * @param int $pos
 * @return MelAction
 */
function mel_tsl_start($e, $id, $pos)
{
    $lang = mel_catv_lang();
    if (Mel::$settings['no_torrents'])
        return mel_torrents_off($lang);
    $known = mel_tsl_files_of($e['hash']);
    $n = $known === null ? '-' : strval(count($known));
    mel_log("ts section: start {$e['hash']} file " . ($id > 0 ? $id : '?') . ", pos $pos, $n files known");
    $t = time();
    list($what, $d, $why, $detail, $files, $j) = mel_tsl_advance(mel_tsl_run($e, $id, $pos, $lang), 0);
    if ($what !== 'wait')
        return mel_tsl_done($what, $d, $why, $detail, $files);
    mel_log('ts section: -> the waiting dialog');
    return mel_dialog_act(mel_tr($lang, 'ts_wait'), mel_tsl_wait_defs($d, 0, $j), array(
        ShowDialogActionData::actions => mel_tsl_wait_acts($d, $t),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => MEL_TS_TICK_MS)));
}

// The end of a step of run $d (mel_tsl_advance) but the wait: play - vod_play (mel_tsl_play_act); files - the screen of
// files; error - the dialog of it (mel_ts_fail, $why its key, $detail under it).
/**
 * @param string $what
 * @param MelTslRun $d
 * @param string $why
 * @param string $detail
 * @param list<MelTsFile> $files
 * @return MelAction
 */
function mel_tsl_done($what, $d, $why, $detail, $files)
{
    if ($what === 'play')
        return mel_tsl_play_act($d, $files, $why);
    if ($what === 'files')
        return mel_tsf_open($d['e']['hash'], $files);
    return mel_ts_fail($d['l'], $why, $detail);
}

// The controls of the waiting dialog of run $d, $sec s since its start, TorrentStatus $j: those of a start from the list
// of releases (mel_ts_wait_ctls), the title of the entry over them, no bit rate; «Отмена» closes it (ts_cancel).
/**
 * @param MelTslRun $d
 * @param int $sec
 * @param array<mixed>|null $j
 * @return list<array<string, mixed>>
 */
function mel_tsl_wait_defs($d, $sec, $j)
{
    return mel_ts_wait_ctls($d['l'], $d['e']['title'], 0.0, $sec, $j, $d['b'] >= 0, mel_input('ts_cancel'));
}

// The timer of the waiting dialog of run $d started at $t (time()): tsl_tick with the run in its params (the entry by its
// hash: in memory or bare, mel_tsl_tick).
/**
 * @param MelTslRun $d
 * @param int $t
 * @return array<string, MelAction>
 */
function mel_tsl_wait_acts($d, $t)
{
    return array(GUI_EVENT_TIMER => mel_input('tsl_tick', array('h' => $d['e']['hash'], 'f' => strval($d['f']),
        'pos' => strval($d['pos']), 'n' => strval($d['n']), 'b' => $d['b'] >= 0 ? strval($d['b']) : '', 't' => strval($t))));
}

// tsl_tick, the timer of the waiting dialog of a start: the run of its params (the entry in memory, else bare - made
// anew of each "get"), one step of it (mel_tsl_advance) -> the wait: the timer again, the lines redrawn (reset_controls);
// else the dialog closed and the end of it (mel_tsl_done). Bad params: the dialog closed.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_tsl_tick($in)
{
    $p = array();
    foreach (array('h' => '/^[0-9a-f]{40}$/D', 'f' => '/^[0-9]{1,6}$/D', 'pos' => '/^[0-9]{1,7}$/D', 'n' => '/^[0-9]{1,4}$/D',
        'b' => '/^([0-9]{1,4})?$/D', 't' => '/^[0-9]{1,12}$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
        {
            mel_log("ts section: tick with a bad $k");
            return mel_close_and(null);
        }
        $p[$k] = $in->$k;
    }
    $h = $p['h'];
    $d = mel_tsl_run(isset(MelTsl::$list[$h]) ? MelTsl::$list[$h] : mel_tsl_bare($h), intval($p['f']), intval($p['pos']),
        mel_catv_lang());
    $d['n'] = intval($p['n']);
    $d['b'] = $p['b'] !== '' ? intval($p['b']) : -1;
    $sec = max(0, time() - intval($p['t']));
    list($what, $d, $why, $detail, $files, $j) = mel_tsl_advance($d, $sec);
    if ($what !== 'wait')
        return mel_close_and(mel_tsl_done($what, $d, $why, $detail, $files));
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => mel_tsl_wait_acts($d, intval($p['t'])),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => MEL_TS_TICK_MS),
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => mel_tsl_wait_defs($d, $sec, $j),
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null))));
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
        return mel_ts_fail($d['l'], 'ts_err_no_video');
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

// The entry of torrent $hash: in memory, else the list read once (a restart of php_server, an old screen) -> array(the
// entry or null: not in the list; the key of an error of the list, '' none; its detail).
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
// start of it (mel_tsl_start; no preload from a position). Not in the list (past MEL_TSL_MAX, an active torrent never
// saved) or the list not to read (not 200, not a list: tsl_err_bad): the start by its hash (mel_tsl_bare);
// TorrServer has it no more - "get" says it (404, tsl_err_gone). TorrServer down, its login, «Без торрентов»: the error
// dialog of a start (mel_ts_fail, mel_torrents_off).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_tsl_recent($in)
{
    $u = mel_tsl_uid_parse(isset($in->wh_uid) ? $in->wh_uid : null);
    if ($u === null && isset($in->h, $in->f) && is_string($in->h) && is_string($in->f))
        $u = mel_tsl_uid_parse(mel_tsl_uid($in->h, $in->f));
    if ($u === null)
    {
        mel_log('ts section: tsl_recent with a bad hash or file, none');
        return null;
    }
    list($h, $f) = $u;
    $p = isset($in->wh_pos) ? $in->wh_pos : null;
    $pos = (is_string($p) && preg_match('/^[0-9]{1,7}$/D', $p)) || (is_int($p) && $p >= 0) ? intval($p) : 0;
    mel_log("ts section: recent $h file $f, wh_pos $pos");
    list($e, $key, $detail) = mel_tsl_find($h);
    $bare = $key === 'tsl_err_bad';
    if ($key !== '' && !$bare)
        return $key === 'ts_err_off' ? mel_torrents_off(mel_catv_lang()) : mel_ts_fail(mel_catv_lang(), $key, $detail);
    if ($e === null)
    {
        mel_log("ts section: $h " . ($bare ? "of a list not read ($key)" : 'not in the list') . ', the start by its hash');
        $e = mel_tsl_bare($h);
    }
    return mel_tsl_start($e, $f, $pos);
}
