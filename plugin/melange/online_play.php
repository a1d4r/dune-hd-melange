<?php
// "Онлайн" on the Dune, its start (the list: view_online.php): ENTER of a row ("call", the check
// of the link, vod_play), "Continue" of a record of it, play_action of a card with "Онлайн" (the
// title marked "online", no AIOStreams), the window "Раздач нет" with "Искать онлайн".
// PHP 5.3.6, firmware API only. Types (AioLpRow, AioMovie): phpstan-types.neon.

// "Continue": seconds of the whole way (the row of the shell waits ~20 s), of the reply of
// the balancer (cold: not after events).
define('AIO_LPV_RESUME_MAX', 18);
define('AIO_LPV_RESUME_REPLY', 5);
// Seconds kept for the check of a link after "call".
define('AIO_LPV_CHECK_MIN', 4);

// The ids of movie_str of play_action $in kept for card $mv (AioLpv::$ids).
/**
 * @param stdClass $in
 * @param AioMovie $mv
 * @return void
 */
function aio_lpv_ids_keep($in, $mv)
{
    $m = isset($in->movie_str) && is_string($in->movie_str) ? json_decode($in->movie_str, true) : null;
    $tmdb = aio_str($m, 'tmdb_id');
    $kp = aio_str($m, 'kp_id');
    if ($tmdb !== '' || $kp !== '')
        AioLpv::$ids[aio_lpv_key($mv)] = array($tmdb, $kp);
}

// --- ENTER of a row of the online list (aio_lpv_pick, view_online.php): the link, the player.

// The link of row $r to play by deadline $deadline (aio_lp_now): "call" of a row of the method
// call (AIO_LPV_CHECK_MIN s kept for the check), then the check of the link (aio_lp_check: its
// mirrors, the length). A link that does not answer the check is played, as aio_precheck's.
// -> array(url, err: '' or a code of AioLpWhy, detail).
/**
 * @param string $base
 * @param AioLpRow $r
 * @param float $deadline
 * @return array{url: string, err: string, detail: string}
 */
function aio_lpv_link($base, $r, $deadline)
{
    $url = $r['url'];
    if ($r['method'] === 'call')
    {
        $total = (int) min(AIO_LP_CALL_MAX, floor($deadline - aio_lp_now() - AIO_LPV_CHECK_MIN));
        if ($total < AIO_LP_REPLY_MIN)
        {
            aio_log('lampac: call: no time left');
            return array('url' => '', 'err' => 'down', 'detail' => '');
        }
        $c = aio_lp_call($base, $url, $total);
        if ($c['err'] !== '')
            return array('url' => '', 'err' => $c['err'], 'detail' => $c['detail']);
        $url = $c['url'];
    }
    $total = (int) min(AIO_LP_CHECK_MAX, floor($deadline - aio_lp_now()));
    $k = aio_lp_check($base, $url, max(AIO_LP_REPLY_MIN, $total));
    if ($k['class'] === 'bad')
        return array('url' => '', 'err' => $k['why'], 'detail' => $k['detail']);
    return array('url' => $k['url'], 'err' => '', 'detail' => '');
}

// vod_play of link $url of row $r of card $mv from $pos s, linked to the
// card when it has a dune id (spec §7): recent_uid aio:<dune id>:-1:-1:<key of the row>, as a
// release of a movie; recent_action_params wh_release = the key (the path of the balancer in it),
// wh_mode lampac, wh_lv the voice ("Continue": aio_lpv_resume). The mark of a title marked
// "online" takes the row: the next list puts it first (only "Онлайн" and "Искать онлайн" mark a
// title, spec §8). On r24 through pf_check (aio_pf_check of call_plugin).
/**
 * @param AioMovie $mv
 * @param string $lang
 * @param AioLpRow $r
 * @param string $url
 * @param int $pos
 * @return AioAction
 */
function aio_lpv_vod($mv, $lang, $r, $url, $pos)
{
    $path = $r['path'];
    $linked = $mv['dune_id'] !== '';
    $ser = aio_vod_item($mv, -1, -1, $url, $linked ? $r['key'] : '', false);
    $info = array(
        PluginVodInfo::id => 'aio.' . aio_lpv_key($mv),
        PluginVodInfo::name => aio_ep_name($mv, -1, -1),
        PluginVodInfo::sub_name => $r['src'] . ($r['voice'] !== '' ? " \xC2\xB7 {$r['voice']}" : ''),
        PluginVodInfo::poster_url => $mv['poster'],
        PluginVodInfo::series => array($ser),
        PluginVodInfo::initial_series_ndx => 0,
        PluginVodInfo::description => $mv['desc'],
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => true,
        PluginVodInfo::skip_dummy_player_on_error => false);
    if ($pos > 0)
        $info[PluginVodInfo::initial_position_ms] = $pos * 1000;
    if ($linked)
    {
        $info[PluginVodInfo::recent_plugin] = 'shell_ext';
        $info[PluginVodInfo::recent_action_params] = array(
            'handler_id' => 'entry',
            'control_id' => 'play_recent',
            'sup_id' => AIO_SUP_ID,
            'movie_id' => $mv['dune_id'],
            'sup_data' => 'auto',
            'wh_release' => $r['key'],
            'wh_mode' => 'lampac',
            'wh_lv' => $r['voice']);
    }
    if ($path !== '' && aio_lpv_mark($mv))
        aio_lpv_mark_set($mv, true, $path, $r['voice']);
    aio_log("play: lampac {$r['key']} pos=$pos linked=" . ($linked ? 1 : 0));
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}

// --- play_action: "Continue", the title marked "online", no AIOStreams, no releases.

// "Continue" of an online record of play_action $in (supplier_info: wh_mode lampac, wh_release a
// key of aio_lp_key, the path of the balancer in it, wh_lv the voice) -> array(path, hex of the
// voice, voice, pos), null for another. The button "Melange NN%" of r25 (play_online): null, the
// list as aio_resume.
/**
 * @param stdClass $in
 * @return array{path: string, hex: string, voice: string, pos: int}|null
 */
function aio_lpv_resume_in($in)
{
    $si = isset($in->supplier_info) && is_string($in->supplier_info) ? json_decode($in->supplier_info) : null;
    if (!is_object($si) || !isset($si->wh_mode, $si->wh_release) || $si->wh_mode !== 'lampac')
        return null;
    $k = aio_lp_key_parse($si->wh_release);
    if (!$k || $k[0] === '' || (isset($in->selected_control_id) && $in->selected_control_id === 'play_online'))
        return null;
    return array('path' => $k[0], 'hex' => $k[1],
        'voice' => isset($si->wh_lv) && is_string($si->wh_lv) ? aio_lp_text($si->wh_lv) : '',
        'pos' => max(0, aio_int($si, 'wh_pos', 0)));
}

// "Continue" $r of card $mv (spec §7): the balancer alone (/lite/<path> on the Lampac of the
// settings, no events), its row of the voice, "call", the check, vod_play from the position;
// within AIO_LPV_RESUME_MAX s. Not so (no title, no voice, no link) -> the search window, the
// cursor on that balancer, the yellow line "<it> больше не отдаёт эту озвучку".
/**
 * @param AioMovie $mv
 * @param array{path: string, hex: string, voice: string, pos: int} $r
 * @param string $lang
 * @return AioAction
 */
function aio_lpv_resume($mv, $r, $lang)
{
    $base = Aio::$settings['lp'];
    $deadline = aio_lp_now() + AIO_LPV_RESUME_MAX;
    $src = aio_lp_source_of($base, $r['path']);
    $why = 'no source';
    if ($src)
    {
        list($t) = aio_lp_externalids($base, aio_lpv_title($mv));
        $rr = aio_lp_replies($base, array($src), aio_lp_query($t), 0,
            min(AIO_LPV_RESUME_REPLY, max(AIO_LP_REPLY_MIN, floor($deadline - aio_lp_now() - AIO_LPV_CHECK_MIN))));
        $row = null;
        foreach ($rr['rows'] as $x)
        {
            if (aio_lp_voice_is($x, $r['hex']))
            {
                $row = $x;
                break;
            }
        }
        $why = $row ? '' : ($rr['rows'] ? 'no voice' : 'no rows');
        if ($row)
        {
            $l = aio_lpv_link($base, $row, $deadline);
            if ($l['err'] === '')
            {
                aio_log("resume: lampac {$r['path']}, pos {$r['pos']}");
                return aio_lpv_vod($mv, $lang, $row, $l['url'], $r['pos']);
            }
            $why = "no link ({$l['err']})";
        }
    }
    aio_log("resume: lampac {$r['path']}: $why -> the search");
    $st = aio_list_state($mv, -1, -1, array(), $lang, aio_no_errors());
    $st['lp_only'] = true;
    return aio_lpv_search_open($st, 'open', array('path' => $r['path']));
}

// play_action $in of card $mv with "Онлайн" (aio_lpv_on): "Continue" of an online record; no
// AIOStreams - the search at once; a title marked "online" - the search, its releases if
// nothing comes, but "Continue" of a record of AIOStreams (aio_resume) goes on as before.
// null: the releases as before.
/**
 * @param stdClass $in
 * @param AioMovie $mv
 * @param string $lang
 * @return AioAction|null
 */
function aio_lpv_play_action($in, $mv, $lang)
{
    if (!aio_lpv_on($mv))
        return null;
    aio_lpv_ids_keep($in, $mv);
    $res = aio_lpv_resume_in($in);
    if ($res)
        return aio_lpv_resume($mv, $res, $lang);
    $aio = Aio::$settings['base'] !== '';
    $mark = $aio ? aio_lpv_mark($mv) : null;
    if ($aio && !$mark)
        return null;
    if ($aio && aio_resume($in))
    {
        aio_log('lampac: the title is marked online, "Continue" of a release -> the releases');
        return null;
    }
    aio_log('lampac: ' . ($aio ? 'the title is marked online' : 'no AIOStreams') . ' -> the search');
    $st = aio_list_state($mv, -1, -1, array(), $lang, aio_no_errors());
    $st['lp_only'] = true;
    return aio_lpv_search_open($st, 'open', array('mark' => $aio, 'back' => $aio));
}

// No releases at all for card $mv ($res of aio_fetch: err_no_streams, no reasons) and
// "Онлайн": the window "Раздач нет" with "Искать онлайн" (the empty list live: lp_find) and
// "Закрыть". null: the error as before.
/**
 * @param AioMovie $mv
 * @param int $s
 * @param int $e
 * @param string $lang
 * @param array{error: string, detail: string, errors?: AioErrors} $res
 * @return AioAction|null
 */
function aio_lpv_no_releases($mv, $s, $e, $lang, $res)
{
    if (!aio_lpv_on($mv) || $res['error'] !== 'err_no_streams' || (isset($res['errors']) && $res['errors']['lines']))
        return null;
    $st = aio_list_state($mv, $s, $e, array(), $lang, aio_no_errors());
    Aio::$state = $st;
    aio_log('lampac: no releases -> the window with the search');
    return aio_dialog(aio_tr($lang, 'lp_no_releases'), array(), array(
        aio_tr($lang, 'lp_find') => aio_close_and(aio_gc_act('lp_find', $st['rid'])),
        aio_tr($lang, 'lp_close') => aio_close_and(null)));
}
