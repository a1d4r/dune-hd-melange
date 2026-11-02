<?php
// "Check release" (0.49.1): MENU of a row with infoHash. The release goes to
// TorrServer for AIO_TS_CHECK_SEC, never saved in its DB (add with save_to_db
// false writes nothing there; no title and poster, so a torrent already in memory
// keeps its own). A dialog shows the live peers and connected seeders of a "get" a
// second, ends with a verdict and stays until closed. The release is dropped at
// the verdict or the close, not "rem": it may lie in the DB of TorrServer; and not
// if another client had it in memory before the check ("keep"). A verdict stays on
// the row ('check' of AioRow): a sort keeps the rows, "Refresh" makes new ones.
// The state of a check is in the params of the actions of its dialog: rid, i: the
// row; h: its hash; keep: "1" no drop (the release of another client, or dropped
// already); ticks: ticks so far; peers: peers of the last reply; seeds: most seeders
// so far; errs: errors in a row.
// Reads Aio (state, settings), writes to Aio::$state only the verdict, on its row.
// Requests to TorrServer: torrserver.php. Functions are global; the require order
// is in main.php.

// A tick takes a second at least, the clock may jump (NTP): the seconds are ticks.
define('AIO_TS_CHECK_SEC', 15);
define('AIO_TS_CHECK_ERRS', 3);

// gc_check (MENU "Check release") of row i of a live list -> the dialog of the
// check, or the error dialog of its start. An error of "get" here: whose the
// release is stays unknown, so no check at all.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_ts_check_menu($in)
{
    $i = aio_ts_menu_row($in);
    if ($i < 0)
    {
        aio_log('ts check: menu of no live row with infoHash');
        return aio_error('err_list_expired');
    }
    /** @var AioState $st the row was found in it */
    $st = Aio::$state;
    $r = $st['rows'][$i];
    $lang = $st['lang'];
    if (Aio::$settings['no_torrents'])
        return aio_torrents_off($lang);
    $fail = aio_ts_echo_fail($lang, 'check, ' . aio_ep_tag($st) . ", row $i, hash {$r['hash']}");
    if ($fail)
        return $fail;
    list($rg, $g) = aio_ts_get($r['hash']);
    $err = $rg['err'];
    $code = $rg['code'];
    if ($err === '' && $code === 401)
        return aio_ts_fail($lang, aio_ts_auth_err());
    if ($err !== '' || ($code !== 404 && !is_array($g)))
        return aio_ts_fail($lang, 'ts_err_down', $err !== '' ? $err : "HTTP $code");
    // In memory: another client of this TorrServer may be playing it.
    $foreign = $code === 200 && !aio_ts_check_gone($g);
    aio_log("ts check: {$r['hash']}, HTTP $code, foreign " . intval($foreign));
    list($fail, $j) = aio_ts_add($lang, $r, array(), false);
    if ($fail)
        return $fail;
    $p = array('rid' => $st['rid'], 'i' => strval($i), 'h' => $r['hash'], 'keep' => $foreign ? '1' : '0', 'ticks' => '0',
        'peers' => strval(aio_ts_int($j, 'total_peers')), 'seeds' => strval(aio_ts_int($j, 'connected_seeders')), 'errs' => '0');
    // 1500: a line of 60 characters of the release fits it whole, at 1100 the
    // shell cuts its middle.
    return aio_dialog_act(aio_tr($lang, 'ts_check_title'), aio_ts_check_defs($st, $r, $p, ''), array(
        ShowDialogActionData::preferred_width => 1500,
        ShowDialogActionData::close_by_return => false,
        ShowDialogActionData::actions => aio_ts_check_acts($p, true),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => AIO_TS_TICK_MS)));
}

// A TorrentStatus not of a torrent in memory: 4 Closed, 5 InDB. A "get" of one in
// the DB also starts loading it in TorrServer.
/**
 * @param array<mixed>|null $j
 * @return bool
 */
function aio_ts_check_gone($j)
{
    return in_array(aio_ts_int($j, 'stat'), array(4, 5), true);
}

// The keys of the dialog: RETURN as "Close" (its close_by_return is off), and the tick.
/**
 * @param array<string, string> $p
 * @param bool $tick
 * @return array<string, AioAction>
 */
function aio_ts_check_acts($p, $tick)
{
    $a = array(GUI_EVENT_KEY_RETURN => aio_input('ts_check_close', $p));
    if ($tick)
        $a[GUI_EVENT_TIMER] = aio_input('ts_check_tick', $p);
    return $a;
}

// Controls of the dialog, the same number on every tick: the window keeps its
// height. $out: the key of the outcome, '' while it goes on.
/**
 * @param AioState $st
 * @param AioRow $r
 * @param array<string, string> $p
 * @param string $out
 * @return list<array<string, mixed>>
 */
function aio_ts_check_defs($st, $r, $p, $out)
{
    $lang = $st['lang'];
    $defs = array();
    foreach (aio_dialog_lines($r['label']) as $l)
        $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l));
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
        str_replace('{sec}', $p['ticks'], aio_tr($lang, $out === '' ? 'ts_check_line' : 'ts_check_done'))));
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
        str_replace(array('{peers}', '{seeds}'), array($p['peers'], $p['seeds']), aio_tr($lang, 'ts_check_peers'))));
    // A space: an empty label is not proven to keep its height.
    $defs[] = aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $out === '' ? ' ' : aio_tr($lang, $out)));
    $defs[] = aio_ctl('close', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => aio_tr($lang, 'ts_close'),
        GuiButtonDef::width => 300, GuiButtonDef::push_action => aio_input('ts_check_close', $p)));
    return $defs;
}

// The params of an action of the dialog, each by its regex -> array or null.
/**
 * @param stdClass $in
 * @return array<string, string>|null
 */
function aio_ts_check_params($in)
{
    $p = array();
    foreach (array('rid' => '/^[0-9a-f]{12}$/D', 'i' => '/^[0-9]{1,3}$/D', 'h' => '/^[0-9a-f]{40}$/D', 'keep' => '/^[01]$/D',
        'ticks' => '/^[0-9]{1,4}$/D', 'peers' => '/^[0-9]{1,7}$/D', 'seeds' => '/^[0-9]{1,7}$/D', 'errs' => '/^[0-9]{1,2}$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
        {
            aio_log("ts check: a bad $k");
            return null;
        }
        $p[$k] = $in->$k;
    }
    return $p;
}

// The release closed in TorrServer, unless "keep". An error: in the log only.
/**
 * @param array<string, string> $p
 * @return void
 */
function aio_ts_check_drop($p)
{
    if ($p['keep'] === '1')
        return;
    $r = aio_ts_call(Aio::$settings['ts'] . '/torrents', (string) json_encode(array('action' => 'drop', 'hash' => $p['h'])), 2,
        Aio::$settings['ts_auth']);
    aio_log("ts check: drop {$p['h']}: " . ($r['err'] !== '' ? $r['err'] : "HTTP {$r['code']}"));
}

// Timer of the dialog: "get" of the release; the numbers redrawn and the timer
// again, or the outcome: redrawn, the release dropped, no timer.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_ts_check_tick($in)
{
    $p = aio_ts_check_params($in);
    if (!$p)
        return aio_close_and(aio_error('err_list_expired'));
    $st = Aio::$state;
    $i = intval($p['i']);
    if (!$st || $p['rid'] !== $st['rid'] || !isset($st['rows'][$i]) || $st['rows'][$i]['hash'] !== $p['h'])
    {
        aio_log('ts check: tick of a list gone');
        aio_ts_check_drop($p);
        return aio_close_and(aio_error('err_list_expired'));
    }
    $r = $st['rows'][$i];
    $p['ticks'] = strval(intval($p['ticks']) + 1);
    list($rg, $j) = aio_ts_get($p['h']);
    $code = $rg['code'];
    $ok = $rg['err'] === '' && is_array($j);
    $gone = $ok && aio_ts_check_gone($j);
    if ($ok && !$gone)
    {
        $p['peers'] = strval(aio_ts_int($j, 'total_peers'));
        $p['seeds'] = strval(max(intval($p['seeds']), aio_ts_int($j, 'connected_seeders')));
    }
    aio_log(sprintf('ts check: tick %s: %s, stat %s, peers %s, seeders %s', $p['ticks'], $rg['err'] !== '' ? $rg['err'] : "HTTP $code",
        is_array($j) && isset($j['stat']) ? json_encode($j['stat']) : '-', $p['peers'], $p['seeds']));
    if ($rg['err'] === '' && $code === 401)
        return aio_close_and(aio_ts_fail($st['lang'], aio_ts_auth_err()));
    $p['errs'] = $ok ? '0' : strval(intval($p['errs']) + 1);
    $out = '';
    if ($gone || ($rg['err'] === '' && $code === 404))
        $out = 'ts_check_gone';
    else if (intval($p['errs']) >= AIO_TS_CHECK_ERRS)
        $out = 'ts_check_no_reply';
    else if (intval($p['ticks']) >= AIO_TS_CHECK_SEC)
    {
        $out = intval($p['seeds']) >= 1 ? 'ts_check_alive' : (intval($p['peers']) >= 1 ? 'ts_check_no_seeds' : 'ts_check_dead');
        $st['rows'][$i]['check'] = array(intval($p['peers']), intval($p['seeds']));
        Aio::$state = $st;
    }
    if ($out !== '')
    {
        aio_log("ts check: {$p['h']}, foreign {$p['keep']}, peers {$p['peers']}, seeders {$p['seeds']}: $out");
        // 404: nothing there to drop.
        if ($code !== 404)
            aio_ts_check_drop($p);
        $p['keep'] = '1';
    }
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => aio_ts_check_acts($p, $out === ''),
            ChangeBehaviourActionData::timer => $out === '' ? array(GuiTimerDef::delay_ms => AIO_TS_TICK_MS) : null,
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => aio_ts_check_defs($st, $r, $p, $out),
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null))));
}

// "Close" and RETURN of the dialog: the release dropped, unless "keep"; with a verdict
// on the row, the card redrawn by our own input after the dialog: reset_controls
// right in close_dialog_and_run does not redraw a screen (aio_setup_tick).
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_ts_check_close($in)
{
    $p = aio_ts_check_params($in);
    if (!$p)
        return aio_close_and(null);
    aio_log("ts check: {$p['h']} closed at tick {$p['ticks']}");
    aio_ts_check_drop($p);
    $st = Aio::$state;
    $checked = $st && $st['rid'] === $p['rid'] && isset($st['rows'][intval($p['i'])]['check']);
    return aio_close_and($checked ? aio_input('ts_check_card', array('rid' => $p['rid'])) : null);
}

// After the dialog: the card of the row with its verdict, no request anywhere.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_ts_check_card($in)
{
    $st = Aio::$state;
    return $st && isset($in->rid) && $in->rid === $st['rid'] ? aio_gc_card_update($st) : null;
}
