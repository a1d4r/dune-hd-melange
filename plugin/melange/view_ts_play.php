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
// (mel_ts_fail). Two video files and more: the screen of files (view_ts_files.php); a start from it: the wait window and
// the error dialog over it.
// Needs ts_list.php, view_ts.php, view_ts_files.php, torrserver.php, ts_info.php, kit_scr.php, kit_dlg.php, main.php. PHP
// 5.3.6, firmware API only.

// The start of file $id of entry $e (0: by file_stats, one video file) from position $pos (s): the first "get", then
// vod_play, the screen of files, an error or the wait. From the rows of the section or "Последнее" ($scr null): the kit
// wait screen, the kit error screen. From the screen of files, the record of it $scr (MelOvlScreen): the wait window and
// the error dialog over it.
/**
 * @param MelTsEntry $e
 * @param int $id
 * @param int $pos
 * @param MelOvlScreen|null $scr
 * @return MelAction|null
 */
function mel_tsl_start($e, $id, $pos, $scr = null)
{
    $lang = mel_catv_lang();
    if (Mel::$settings['no_torrents'])
        return mel_tsl_fail($lang, 'ts_err_off', '', $scr);
    $known = mel_tsl_files_of($e['hash']);
    $n = $known === null ? '-' : strval(count($known));
    mel_log("ts section: start {$e['hash']} file " . ($id > 0 ? $id : '?') . ", pos $pos, $n files known" .
        ($scr !== null ? ', from the screen of files' : ''));
    list($what, $d, $why, $detail, $files, $j) = mel_tsl_advance(mel_tsl_run($e, $id, $pos, $lang), 0);
    if ($what === 'wait')
    {
        $w = mel_tsl_wait_desc($d, $j, $lang);
        $a = $scr !== null ? mel_wait_open($scr, $w) : null;
        return $a ? $a : mel_ks_wait(array_merge(mel_tsl_look($lang), $w), mel_tr($lang, 'tsl_title'), MEL_TS_TICK_MS);
    }
    if ($what === 'play')
        return mel_tsl_play_act($d, $files, $why);
    if ($what === 'files')
        return mel_tsf_open($e['hash'], $files);
    return mel_tsl_fail($lang, $why, $detail, $scr);
}

// The wait of run $d (MelWaitDesc, the look none: the wait screen adds it), its first lines of TorrentStatus $j
// (mel_tsl_wait_texts), its tick mel_tsl_tick, «Отмена».
/**
 * @param MelTslRun $d
 * @param array<mixed>|null $j
 * @param string $lang
 * @return array<string, mixed>
 */
function mel_tsl_wait_desc($d, $j, $lang)
{
    list($text, $live, $share) = mel_tsl_wait_texts($j, $d['b'] >= 0, $lang);
    return array('icon' => 'k_icon_monitor-play', 'title' => mel_tr($lang, 'ts_wait'), 'text' => $text, 'live' => $live,
        'share' => $share, 'elapsed' => mel_tr($lang, 'ks_wait_elapsed'), 'tick' => 'mel_tsl_tick', 'data' => $d,
        'buttons' => array(array('id' => 'cancel', 'label' => mel_tr($lang, 'ts_cancel'), 'icon' => 'k_icon_x',
            'cancel' => true)));
}

// The error $key of a start ($detail: its detail): over the screen of files of record $scr the dialog (mel_tsl_fail_desc),
// with none (or not a record) the kit error screen (mel_tsl_fail_act).
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @param MelOvlScreen|null $scr
 * @return MelAction
 */
function mel_tsl_fail($lang, $key, $detail, $scr)
{
    $a = $scr !== null ? mel_dlg_open($scr, mel_tsl_fail_desc($lang, $key, $detail)) : null;
    if ($a)
    {
        mel_log("ts section: error dialog $key");
        return $a;
    }
    return mel_tsl_fail_act($lang, $key, $detail);
}

// The tick of a wait of a start (MelWaitDesc tick; the wait screen, the wait window over the screen of files alike): run
// $d, $sec s since the start -> post vod_play; post the screen of files (the wait screen leaves for it in its place,
// mel_ks_leave); dialog an error; else the run and its lines.
/**
 * @param mixed $d
 * @param int $sec
 * @return array<string, mixed>|null
 */
function mel_tsl_tick($d, $sec)
{
    if (!is_array($d) || !isset($d['e']['hash'], $d['f'], $d['pos'], $d['n'], $d['b'], $d['l'], $d['g']))
        return null;
    /** @var MelTslRun $d */
    list($what, $d, $why, $detail, $files, $j) = mel_tsl_advance($d, $sec);
    $hash = $d['e']['hash'];
    if ($what === 'play')
        return array('post' => mel_tsl_play_act($d, $files, $why));
    if ($what === 'files')
        return array('post' => mel_tsf_open($hash, $files));
    if ($what === 'error')
        return array('dialog' => mel_tsl_fail_desc($d['l'], $why, $detail));
    list($text, $live, $share) = mel_tsl_wait_texts($j, $d['b'] >= 0, $d['l']);
    return array('data' => $d, 'text' => $text, 'live' => $live, 'share' => $share);
}

// The lines of the wait of a start (W2 of the UI kit) by TorrentStatus $j: the text - the status of TorrServer in words
// (mel_ts_wait_status), with the preload ($buf) «Предзагрузка: N из M МБ»; the live line after «прошло N с» - the peers
// and the seeders, with the preload the speed and the seeders; the share of the bar - of the preload, null none.
/**
 * @param array<mixed>|null $j
 * @param bool $buf
 * @param string $lang
 * @return array{string, string, float|null}
 */
function mel_tsl_wait_texts($j, $buf, $lang)
{
    list($text, $share) = mel_ts_wait_status($j, $buf ? 'tsl_wait_buf' : '', $lang);
    $seeds = strval(mel_ts_int($j, 'connected_seeders'));
    if (!$buf)
        return array($text, str_replace(array('{peers}', '{all}', '{seeds}'), array(strval(mel_ts_int($j, 'active_peers')),
            strval(mel_ts_int($j, 'total_peers')), $seeds), mel_tr($lang, 'tsl_wait_peers')), null);
    $speed = str_replace('{speed}', mel_ts_speed(is_array($j) && isset($j['download_speed']) ? $j['download_speed'] : null,
        $lang), mel_tr($lang, 'tsl_wait_speed'));
    return array($text, $speed . " \xC2\xB7 " . str_replace('{seeds}', $seeds, mel_tr($lang, 'tsl_wait_seeds')), $share);
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
// of the list tsl_err_bad: its text, mel_tsl_err_texts), $detail under them, «Назад».
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @return array<string, mixed>
 */
function mel_tsl_fail_desc($lang, $key, $detail)
{
    if ($key === 'tsl_err_bad')
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
// TorrServer has it no more - "get" says it (404, tsl_err_gone). TorrServer down, its login: the error screen.
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
        return mel_tsl_fail_act(mel_catv_lang(), $key, $detail);
    if ($e === null)
    {
        mel_log("ts section: $h " . ($bare ? "of a list not read ($key)" : 'not in the list') . ', the start by its hash');
        $e = mel_tsl_bare($h);
    }
    return mel_tsl_start($e, $f, $pos);
}
