<?php
// "Онлайн" on the Dune: the window "Ищу в <its source>…" of ticks (the search of Lampac, then the replies of its
// balancers, each call of php_server a few seconds) and its result: the online list (view_online.php) or a window
// (nothing online, no answer). The search is one step a tick (mel_lpv_wait_tick) that replies as a tick of a wait window
// (MelWaitTick, kit_dlg.php): the fields of the window, or the end. Its hosts: the wait window of ours over the list
// the search is started from (mel_lpv_wait_kit), the dialog of the shell where there is no screen of ours under it -
// over a card, over the seasons of the shell (mel_lpv_search_shell, mel_lpv_tick). PHP 5.3.6, firmware API only. Types (MelLpRun, MelLpView, MelLpSearchView): phpstan-types.neon.

// The timer of the window between its ticks, ms: a tick is a request itself.
define('MEL_LPV_TICK_MS', 100);
// «Показать найденное» of the wait window of ours: from this second of the search on (the owner 27.11.2026), with a
// variant found.
define('MEL_LPV_FOUND_S', 15);

// --- The window "Ищу в <its source>…": the search (events, /lifeevents), then the replies of the
// balancers, a tick each call of php_server (spec §4.3); the result - the online list, else
// a window. MelLpv::$run keeps it between the ticks; Cancel or RETURN drops the window.

// A new window of the search of list $st (rows may be none; of an episode the rows of its season) as
// $how: 'open' - over a card (play_action), its screen opened; 'replace' - over a list screen,
// replaced; 'chosen' - over the episodes of the shell ("Выбрать серию"), those and the list replaced. $opt: back
// (nothing online: the releases with the
// yellow line), path (the path of a balancer: the cursor on it, the yellow line "<it> больше не
// отдаёт эту озвучку"), key (the key of a row, of an online record: the cursor on that row, shown
// though a filter hides it; none - on the first of the balancer of the key), fail (the row of the key found,
// its link not: the reason, the yellow line "<it> не ответил" of down, else "<it> не дал видео"),
// more (the replies of the view of $st from its next, no search; the cursor on the first new row), redraw (the screen
// under it shows a list before $st: $st put in when the window closes with no list -
// MelLpv::$redraw, mel_lpv_redraw_keys), scr (the record of the list on screen the search is started from, an own
// input of it: with 'replace' the wait window of ours over it, mel_lpv_wait_kit - its run the data of the window, not
// MelLpv::$run; none, or not opened: the dialog of the shell), retry and other (the first buttons of the dialogs of
// ours the search of the wait window of ours ends by with no list, each run after the close: «Повторить» of no answer
// - the input that started this search, again; «Другой источник» of nothing found - the submenu "Источник" over the
// list, mel_gc_src_menu_act; none: «Закрыть» alone. Not of the dialog of the shell: no list under it to act on).
/**
 * @param MelState $st
 * @param string $how
 * @param array{back?: bool, path?: string, key?: string, fail?: string, more?: bool, redraw?: bool, scr?: MelOvlScreen|null, retry?: MelAction|null, other?: MelAction|null} $opt
 * @return MelAction
 */
function mel_lpv_search_open($st, $how, $opt = array())
{
    $sid = mel_rid();
    $more = !empty($opt['more']) && isset($st['lp']);
    $v = array('title' => mel_lpv_title($st['movie']), 'q' => '', 'sources' => array(),
        'hidden' => array('show' => 0, 'bad' => 0), 'rows' => array(), 'why' => array(), 'next' => 0,
        'left' => 0, 't' => mel_lp_now(), 'note' => '');
    if ($more)
    {
        $v = $st['lp'];
        unset($v['gc'], $v['gc_new'], $v['gc_prog'], $v['gc_cols']);
    }
    else
    {
        // The filter and the folding of the list of the title before ("Обновить", another season).
        foreach (array('flt', 'open') as $k)
        {
            if (isset($st['lp'][$k]))
                $v[$k] = $st['lp'][$k];
        }
    }
    // The address of the Lampac of the list at the open (of its pair, mel_op_use; the pair of the operation as it was):
    // what mel_lpv_run_live holds the run to, of "Ещё N" too - it has no search of its own.
    list($rel, $lp) = array(Mel::$settings['prof'], Mel::$settings['lp_prof']);
    mel_op_use($st['prof'], $st['lp_prof']);
    $base = Mel::$settings['lp'];
    mel_op_use($rel, $lp);
    $run = array('sid' => $sid, 'st' => $st, 'base' => $base, 'how' => $how, 'back' => !empty($opt['back']),
        'path' => isset($opt['path']) ? $opt['path'] : '', 'key' => isset($opt['key']) ? $opt['key'] : '',
        'fail' => isset($opt['fail']) ? $opt['fail'] : '', 'phase' => $more ? 'replies' : 'ids', 'search' => null, 'v' => $v,
        'poll' => mel_lp_poll_start(count($v['rows'])), 'more' => $more ? count($v['rows']) : -1, 'retry' => null,
        'other' => null);
    // A window of before closed by RETURN of the shell (no lp_cancel): its end.
    mel_lpv_run_end();
    MelLpv::$redraw = !empty($opt['redraw']) ? array('sid' => $sid, 'st' => $st) : null;
    mel_log("lampac: window $sid, " . mel_ep_tag($st) . ", $how, phase {$run['phase']}" .
        ($run['back'] ? ', back' : '') . ($run['path'] !== '' ? ", balancer {$run['path']}" : '') .
        (MelLpv::$redraw ? ', redraw' : ''));
    $own = array_merge($run, array('retry' => isset($opt['retry']) ? $opt['retry'] : null,
        'other' => isset($opt['other']) ? $opt['other'] : null));
    $kit = $how === 'replace' && !empty($opt['scr']) ? mel_lpv_wait_kit($opt['scr'], $own) : null;
    if ($kit)
        return $kit;
    return mel_lpv_search_shell($run);
}

// Text $key about a search of the profile of Lampac of id $id: its {name} the name of that source, as "Источник" and
// the line over the lists show it (mel_src_name; the owner 27.11.2026, (30): not «онлайн» - the releases are online
// too; mel_lpv_src_name), then $sub. $w (units of mel_gc_w at 1000; 0: any) - of a text of the window of the shell,
// which cuts a line wider than it (MEL_DIALOG_LINE_W: a title, in the middle; MEL_DIALOG_BTN_W: the label of a
// button, its end lost), not of the dialog of ours, which wraps its title: a wide name cut with "…" to what the other
// words leave of it, MEL_LPV_NAME_MIN_W at least - the words after the name stay. A name not wider than that is never
// cut: beside a long title of the movie the line is too wide by the title, cut by the shell as before.
define('MEL_LPV_NAME_MIN_W', 7250);
/**
 * @param string $lang
 * @param string $key
 * @param string $id
 * @param array<string, string|int> $sub
 * @param int $w
 * @return string
 */
function mel_lpv_src_tr($lang, $key, $id, $sub = array(), $w = 0)
{
    $name = mel_lpv_src_name($id);
    if ($w > 0)
        $name = mel_gc_cut_w($name, 1000, max(MEL_LPV_NAME_MIN_W,
            $w - mel_gc_w(mel_lpv_tr($lang, $key, array('name' => '') + $sub), 1000)));
    return mel_lpv_tr($lang, $key, array('name' => $name) + $sub);
}

// The search of run $run as the dialog of the shell "Ищу в <its source>…": the run kept between its ticks (MelLpv::$run), its
// two lines and "Отмена" (mel_lpv_search_defs), its timer to lp_tick (mel_lpv_tick).
/**
 * @param MelLpRun $run
 * @return MelAction
 */
function mel_lpv_search_shell($run)
{
    $sid = $run['sid'];
    MelLpv::$run = $run;
    return mel_lpv_redraw_keys(mel_dialog_act(mel_lpv_src_tr($run['st']['lang'], 'lp_search', $run['st']['lp_prof'], array(),
        MEL_DIALOG_LINE_W), mel_lpv_search_defs($run), array(
        ShowDialogActionData::actions => array(GUI_EVENT_TIMER => mel_input('lp_tick', array('sid' => $sid), 30000)),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => MEL_LPV_TICK_MS))), $sid);
}

// The search of run $run as the wait window of ours (mel_wait_open, kit_dlg.php; the owner 27.11.2026, frame
// P1_online25) over the screen of record $scr, the list it was started from: «Ищу в <its source>», «прошло N с» by the title,
// the fields of the search now (mel_lpv_search_view: the stage and the variants over the bar; no facts, no line under
// the bar), «Отмена» (RETURN too); from MEL_LPV_FOUND_S s on and with a variant found (late held by the step)
// «Показать найденное» after it, the focus still on «Отмена» (the owner 27.11.2026, (32)). Its tick is the step of
// the search (mel_lpv_wait_tick) every MEL_LPV_TICK_MS with the run its data; both buttons leave the run to the
// screen (lp_wt_done, mel_lpv_wait_done).
// Not opened (no record): null.
/**
 * @param MelOvlScreen $scr
 * @param MelLpRun $run
 * @return MelAction|null
 */
function mel_lpv_wait_kit($scr, $run)
{
    $lang = $run['st']['lang'];
    $act = mel_gc_act('lp_wt_done', $scr['owner']);
    $cancel = array('id' => 'cancel', 'label' => mel_tr($lang, 'lp_cancel'), 'icon' => 'k_icon_x', 'cancel' => true,
        'act' => $act);
    $found = array('id' => 'found', 'label' => mel_tr($lang, 'lp_show_found'), 'icon' => 'k_icon_list', 'act' => $act);
    return mel_wait_open($scr, array_merge(mel_lpv_search_view($run), array('icon' => 'k_icon_globe',
        'title' => mel_lpv_src_tr($lang, 'lp_search_title', $run['st']['lp_prof']), 'elapsed' => mel_tr($lang, 'ks_wait_elapsed'),
        'tick' => 'mel_lpv_wait_tick', 'data' => $run, 'ms' => MEL_LPV_TICK_MS, 'buttons' => array($cancel),
        'late' => array('sec' => MEL_LPV_FOUND_S, 'buttons' => array($cancel, $found)))));
}

// lp_wt_done, a button of the wait window of ours over the list of rid $in->rid (it comes after the close): the run
// the window kept for the screen (mel_wt_done), over as a cancelled one (its sources kept, mel_lpv_run_over). «Отмена»
// (RETURN too): nothing more - of a search of "Источники…" its list of the new choice put in (mel_lpv_redraw), as
// lp_cancel of the dialog of the shell. «Показать найденное»: the result of the run as it is now (mel_lpv_result) -
// the list of what the balancers asked so far gave, the others its row "Ещё N"; its Lampac not the one it searched
// any more (mel_lpv_run_live): the message of it, as the step gives. No run kept (pressed twice, the state of the
// screen gone): nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_lpv_wait_done($in)
{
    $d = mel_wt_done(isset($in->rid) && is_string($in->rid) ? $in->rid : '');
    $run = $d && is_array($d['data']) && isset($d['data']['sid'], $d['data']['st'], $d['data']['v']) ? $d['data'] : null;
    if (!$d || !$run)
    {
        mel_log('lampac: a button of a wait with no run kept, nothing');
        return null;
    }
    /** @var MelLpRun $run */
    mel_lpv_run_over($run);
    if ($d['id'] !== 'found')
    {
        mel_log('lampac: the search cancelled');
        return mel_lpv_redraw((object) array('sid' => $run['sid']));
    }
    if (!mel_lpv_run_live($run))
    {
        mel_log("lampac: window {$run['sid']}: its profile of Lampac gone or changed on the page, what it found not shown");
        return mel_lpv_msg($run, mel_tr($run['st']['lang'], 'src_changed'));
    }
    mel_log("lampac: window {$run['sid']}: shown as found so far");
    return mel_lpv_result($run);
}

// Dialog $a of the window $sid (the search, no answer, nothing) of a search of "Источники…" (MelLpv::$redraw): RETURN
// to lp_cancel (mel_lpv_redraw), not closed by the shell; else $a as it is.
/**
 * @param MelAction $a
 * @param string $sid
 * @return MelAction
 */
function mel_lpv_redraw_keys($a, $sid)
{
    if (!MelLpv::$redraw || MelLpv::$redraw['sid'] !== $sid)
        return $a;
    // The keys of show_dialog and of change_behaviour: both 'actions'.
    $key = ShowDialogActionData::actions;
    if ($a[GuiAction::handler_string_id] === SHOW_DIALOG_ACTION_ID)
        $a[GuiAction::data][ShowDialogActionData::close_by_return] = false;
    $acts = isset($a[GuiAction::data][$key]) ? $a[GuiAction::data][$key] : array();
    $acts[GUI_EVENT_KEY_RETURN] = mel_input('lp_cancel', array('sid' => $sid));
    $a[GuiAction::data][$key] = $acts;
    return $a;
}

// lp_cancel of the window $in->sid (of the wait window and the dialog of ours in its place: lp_wt_done, lp_redraw):
// of a search of "Источники…" (MelLpv::$redraw) its list of the new choice put in, the screen replaced (else the rows of before stay there, of the sources just turned off too); else nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_lpv_redraw($in)
{
    $r = MelLpv::$redraw;
    if (!$r || !isset($in->sid) || $in->sid !== $r['sid'])
        return null;
    MelLpv::$redraw = null;
    mel_log('lampac: window ' . $r['sid'] . ' closed with no list: the list of the new choice, ' . count($r['st']['lp']['rows']) .
        ' rows');
    return mel_list_replace($r['st']);
}

// What the search of run $run shows now, the fields of a wait window (MelWaitTick, kit_dlg.php) - the window of ours
// takes them as they are, the dialog of the shell shows its two lines (shell_lines: mel_lpv_search_defs). The replies of
// the balancers: stage, those asked of those found («Опрошено: 6 из 7»); count, the variants so far («вариантов:
// 31»); share of the bar, the asked of the found; late, a variant is there (the second button of the window needs
// one: the owner 27.11.2026). Before them (the ids, the search): stage, «Ищу балансеры»; count, those found so far
// («найдено: 7»), none yet - no count (the owner 27.11.2026, (29)); the empty bar, late false; the lines of the shell
// as before - the balancers found, that they are answering.
/**
 * @param MelLpRun $run
 * @return MelLpSearchView
 */
function mel_lpv_search_view($run)
{
    $lang = $run['st']['lang'];
    $v = $run['v'];
    // Not counted, as when rch true hid them: those of a refusal of RCH, and those of rch true of events (mel_lp_source)
    // not asked yet (from next on) - asked and not refused, they are. A Lampac where they all refuse: N as before; a
    // server marking so balancers that answer as any: N grows as they answer, never down.
    $rch = mel_lpv_rch_n($v);
    $wait = 0;
    foreach ($v['sources'] as $i => $x)
    {
        if ($i >= $v['next'] && !empty($x['rch']))
            $wait++;
    }
    $n = max(0, count($v['sources']) - $rch - $wait);
    $found = mel_lpv_tr($lang, 'lp_search_found', array('n' => $n));
    if ($run['phase'] !== 'replies')
        return array('stage' => mel_tr($lang, 'lp_search_look'),
            'count' => $n > 0 ? mel_lpv_tr($lang, 'lp_search_got', array('n' => $n)) : '', 'share' => 0.0,
            'late' => false, 'shell_lines' => array($found, mel_tr($lang, 'lp_search_wait')));
    $k = max(0, $v['next'] - $rch);
    $stage = mel_lpv_tr($lang, 'lp_search_asked', array('k' => $k, 'n' => $n));
    $count = mel_lpv_tr($lang, 'lp_search_variants', array('r' => count($v['rows'])));
    return array('stage' => $stage, 'count' => $count, 'share' => $n > 0 ? min(1.0, $k / $n) : 0.0,
        'late' => count($v['rows']) > 0, 'shell_lines' => array($found, "$stage, $count"));
}

// Controls of the dialog of the shell of run $run, of the fields of its search (mel_lpv_search_view, shell_lines): the
// sources found, the balancers asked and the variants of them (both lines always: the window keeps its height),
// "Отмена".
/**
 * @param MelLpRun $run
 * @return list<array<string, mixed>>
 */
function mel_lpv_search_defs($run)
{
    $lang = $run['st']['lang'];
    $view = mel_lpv_search_view($run);
    return array(
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $view['shell_lines'][0])),
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $view['shell_lines'][1])),
        mel_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => mel_tr($lang, 'lp_cancel'),
            GuiButtonDef::width => 300, GuiButtonDef::push_action => mel_input('lp_cancel', array('sid' => $run['sid'])))));
}

// A message of the search of run $run, title $title, with "Закрыть": the window of the shell (of a search of
// "Источники…" its button and RETURN to lp_cancel: mel_lpv_close_act, mel_lpv_redraw_keys), said as a message
// (mel_msg_say) - the end of a wait of ours by it is the dialog of ours (mel_wait_end), which of such a search puts its
// list in when closed (lp_redraw: mel_lpv_redraw). The ends of a search with no list: mel_lpv_end_msg.
/**
 * @param MelLpRun $run
 * @param string $title
 * @return MelAction
 */
function mel_lpv_msg($run, $title)
{
    $ok = mel_tr($run['st']['lang'], 'lp_close');
    $redraw = MelLpv::$redraw && MelLpv::$redraw['sid'] === $run['sid'];
    return mel_msg_say(mel_lpv_redraw_keys(mel_dialog($title, array(), array($ok => mel_lpv_close_act($run['sid']))),
        $run['sid']), $title, '', $ok, null, $redraw ? mel_input('lp_redraw', array('sid' => $run['sid'])) : null);
}

// The search of run $run is over with no list: title $title (the dialog of ours has it whole; $shell - of the window
// of the shell, the name of the source in it cut for its one line, mel_lpv_src_tr), the paragraphs $parts of its text
// (the window of the shell: their first 3 lines), icon $icon in tone $tone, $first (mel_btn; null none) and «Закрыть»
// with a cross (the owner 27.11.2026, (31), frame sheet_q9 B). The window of the shell: «Закрыть» alone (of a search
// of "Источники…" it and RETURN to lp_cancel: mel_lpv_close_act, mel_lpv_redraw_keys); said as a window that asks
// (mel_ask_say) - the dialog of ours the wait of ours ends by (mel_wait_end): $first in focus, run after its close;
// «Закрыть» and RETURN of a search of "Источники…" put its list in (lp_redraw: mel_lpv_redraw).
/**
 * @param MelLpRun $run
 * @param string $title
 * @param string $shell
 * @param list<string> $parts
 * @param string $icon
 * @param string $tone
 * @param array{label: string, icon: string, act: MelAction|null}|null $first
 * @return MelAction
 */
function mel_lpv_end_msg($run, $title, $shell, $parts, $icon, $tone, $first)
{
    $sid = $run['sid'];
    $close = mel_tr($run['st']['lang'], 'lp_close');
    list($lines, $text) = mel_msg_parts($parts);
    $redraw = MelLpv::$redraw && MelLpv::$redraw['sid'] === $sid ? mel_input('lp_redraw', array('sid' => $sid)) : null;
    $buttons = array(mel_btn($close, 'k_icon_x', $redraw));
    if ($first)
        array_unshift($buttons, $first);
    return mel_ask_say(mel_lpv_redraw_keys(mel_dialog($shell, array_slice($lines, 0, 3),
        array($close => mel_lpv_close_act($sid))), $sid), $title, $text, $icon, $tone, $buttons, $redraw);
}

// One step of the search of run $run, the tick of a wait window (MelWaitTick, kit_dlg.php: the window of ours calls
// it by name with the run its data; the dialog of the shell by mel_lpv_tick), $sec s after its open: /externalids,
// the start of the search (events), a /lifeevents (or the one events without life: up to MEL_LP_SEARCH_MAX s), the
// replies of a few balancers. It goes on: the fields of the window (mel_lpv_search_view) and data, the run after the
// step. The end (mel_wait_end): post - the result (mel_lpv_result: the online list, the releases with the yellow
// line); or a message - nothing online, no answer (mel_lpv_down), the profile of Lampac gone - dialog, its
// description for the window of ours, and shell, its window of the shell.
/**
 * @param MelLpRun $run
 * @param int $sec
 * @return array{post: MelAction}|array{dialog: array<string, mixed>, shell: MelAction}|array{stage: string, count: string, share: float, late: bool, shell_lines: array{string, string}, data: MelLpRun}
 */
function mel_lpv_wait_tick($run, $sec)
{
    // A tick is an operation of its own: the Lampac of the list of the run (its profile, spec v2 §2.2), not the main one.
    // Not its Lampac any more: no request - not to the address of before with the auth of another Lampac, nor to
    // another one with the balancers of this one.
    if (!mel_lpv_run_live($run))
    {
        mel_log("lampac: window {$run['sid']}: its profile of Lampac gone or changed on the page, closed with no request");
        return mel_wait_end(mel_lpv_msg($run, mel_tr($run['st']['lang'], 'src_changed')));
    }
    $base = Mel::$settings['lp'];
    if ($base === '')
        return mel_wait_end(mel_lpv_down($run, 'down', 'no address'));
    if ($run['phase'] === 'ids')
    {
        list($run['v']['title'], $why) = mel_lp_externalids($base, $run['v']['title']);
        // No KinoPoisk id of Lampac: that of the catalog server, in a tick of its own (a tick is a request).
        $run['phase'] = mel_lpv_kp_ask($run['v']['title'], $why) ? 'kp' : 'start';
    }
    else if ($run['phase'] === 'kp')
    {
        $run['v']['title'] = mel_lpv_kp_catalog($run['st']['movie'], $run['v']['title']);
        $run['phase'] = 'start';
    }
    else if ($run['phase'] === 'start')
    {
        $run['search'] = mel_lp_search_start($base, $run['v']['title']);
        // The title of Lampac kept for "Continue" and the next episode of the card (mel_lpv_title): they ask the
        // balancer without events. With the title it replaced: the own card and the native one share the dune id,
        // not the title. Memory only.
        $k = mel_lpv_key($run['st']['movie']);
        if ($run['search']['title'] !== '' && $k !== '')
            mel_lpv_ids_put($k, array('tmdb' => '', 'kp' => '', 'original_language' => '',
                'title' => $run['search']['title'], 'title_of' => $run['v']['title']['title']));
        $run['phase'] = 'search';
    }
    else if ($run['phase'] === 'search' && $run['search'])
        $run['search'] = mel_lp_search_step($run['search']);
    else if ($run['phase'] === 'replies')
    {
        list($run['poll'], $run['v'], $over) = mel_lp_poll($base, $run['poll'], $run['v'],
            $run['st']['movie']['series'] ? $run['st']['s'] : 0);
        if ($over)
            $run['phase'] = 'done';
    }
    $s = $run['search'];
    if ($run['phase'] === 'search' && $s)
    {
        // Those turned off in "Источники…" not asked, nor counted; the others in its order, the new ones last (put there
        // at the end of the run, mel_lpv_run_seen).
        $ch = Mel::$settings['lp_src'];
        list($run['v']['sources'], $run['v']['off']) = mel_lpv_srcs_pick($s['sources'],
            array_merge($ch, mel_lpv_srcs_new($ch, $s['sources'])));
        $run['v']['hidden'] = $s['hidden'];
        if ($s['done'])
        {
            if ($run['v']['off'])
                mel_log('lampac: sources off: ' . implode(', ', $run['v']['off']));
            if (!$s['sources'] && $s['err'] !== '' && $s['err'] !== 'accsdb')
                return mel_wait_end(mel_lpv_down($run, $s['err'], $s['detail']));
            if ($s['err'] === 'accsdb')
                $run['v']['why'][] = array('src' => '', 'path' => '', 'why' => 'accsdb', 'detail' => $s['detail']);
            $run['v']['q'] = $s['q'];
            $run['v']['t'] = $s['t0'];
            $run['phase'] = $run['v']['sources'] ? 'replies' : 'done';
            $run['poll'] = mel_lp_poll_start(count($run['v']['rows']));
        }
    }
    if ($run['phase'] === 'done')
    {
        mel_lpv_run_over($run);
        return mel_wait_end(mel_lpv_result($run));
    }
    $view = mel_lpv_search_view($run);
    $view['data'] = $run;
    return $view;
}

// lp_tick of the dialog of the shell $in->sid: one step of its run (mel_lpv_wait_tick), then its reply as the shell takes
// it - the end: the dialog closed, then the result or the message as its window of the shell; else the dialog redrawn
// of the run after the step and its timer again.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_lpv_tick($in)
{
    $run = MelLpv::$run;
    if (!$run || !isset($in->sid) || $in->sid !== $run['sid'])
    {
        mel_log('lampac: tick of a window gone');
        return mel_close_and(null);
    }
    MelLpv::$run = null;
    $r = mel_lpv_wait_tick($run, 0);
    if (isset($r['post']))
        return mel_close_and($r['post']);
    if (isset($r['dialog']))
        return mel_close_and($r['shell']);
    $run = $r['data'];
    MelLpv::$run = $run;
    return mel_lpv_redraw_keys(array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => array(GUI_EVENT_TIMER => mel_input('lp_tick', array('sid' => $run['sid']), 30000)),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => MEL_LPV_TICK_MS),
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => mel_lpv_search_defs($run),
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null)))), $run['sid']);
}

// The number of the sources of a refusal of RCH (the reason rch: only the Lampa app plays them) of the online list $v,
// each source once by its index (at): two sources of one path ("X ~ 2160p", "X ~ 1080p") two. The one count of them:
// "Найдено N", "Опрошено" (mel_lpv_search_defs), "Скрыто N" (mel_lpv_errors_of).
/**
 * @param MelLpView $v
 * @return int
 */
function mel_lpv_rch_n($v)
{
    $at = array();
    $n = 0;
    foreach ($v['why'] as $w)
    {
        if ($w['why'] !== 'rch')
            continue;
        if (!isset($w['at']))
            $n++;
        else
            $at[$w['at']] = true;
    }
    return $n + count($at);
}

// The end of the search of the dialog of the shell, MelLpv::$run: cancelled (lp_cancel) or left (a window closed by
// the shell, then a new search: mel_lpv_search_open) - its sources kept (mel_lpv_run_over), MelLpv::$run none.
/**
 * @return void
 */
function mel_lpv_run_end()
{
    $run = MelLpv::$run;
    MelLpv::$run = null;
    if ($run)
        mel_lpv_run_over($run);
}

// The search of run $run is over: done (mel_lpv_wait_tick), cancelled or left. In its replies or done: its sources into
// "Источники…" of its own profile of Lampac (mel_lpv_run_seen; the pair of the operation may be another one - that of
// the main profile, of a new title), that profile gone, not ready or of another address since (mel_lpv_run_live) -
// none kept: they are the balancers of another Lampac. The pair of the operation as it was.
/**
 * @param MelLpRun $run
 * @return void
 */
function mel_lpv_run_over($run)
{
    if ($run['phase'] !== 'replies' && $run['phase'] !== 'done')
        return;
    list($rel, $lp) = array(Mel::$settings['prof'], Mel::$settings['lp_prof']);
    if ($run['st']['lp_prof'] !== '' && mel_lpv_run_live($run))
        mel_lpv_run_seen($run);
    else
        mel_log("lampac: window {$run['sid']}: its profile of Lampac gone or changed on the page, its sources not kept");
    mel_op_use($rel, $lp);
}

// The Lampac of run $run is the one it searches still: its profile of Lampac there and ready, of the address the run
// was opened at (its 'base', mel_lpv_search_open; the page may change both meanwhile). The pair of the operation from now that of the run (mel_op_use:
// the Lampac of its list, spec v2 §2.2, not the main one), whatever the answer.
/**
 * @param MelLpRun $run
 * @return bool
 */
function mel_lpv_run_live($run)
{
    list(, $lp) = mel_op_use($run['st']['prof'], $run['st']['lp_prof']);
    return $lp === $run['st']['lp_prof'] && $run['base'] === Mel::$settings['lp'];
}

// The sources of run $run (mel_lpv_run_over, of the pair of the run) into "Источники…" (mel_lpv_srcs_seen): those of its
// search ("Ещё N": of its list) but, by path, those of a refusal of RCH (the reason rch) and those of rch true of events
// not asked yet (from next on: cancelled before them, they may refuse so too) - not there, as when rch true hid them.
/**
 * @param MelLpRun $run
 * @return void
 */
function mel_lpv_run_seen($run)
{
    $v = $run['v'];
    $skip = array();
    foreach ($v['why'] as $w)
    {
        if ($w['why'] === 'rch')
            $skip[$w['path']] = true;
    }
    foreach ($v['sources'] as $i => $x)
    {
        if ($i >= $v['next'] && !empty($x['rch']))
            $skip[$x['path']] = true;
    }
    $out = array();
    foreach ($run['search'] ? $run['search']['sources'] : $v['sources'] as $x)
    {
        if (!isset($skip[$x['path']]))
            $out[] = $x;
    }
    mel_lpv_srcs_seen($out);
}

// Lampac of run $run did not answer ($why, $detail: MelLpWhy): a title with releases (back) -
// those with the yellow line; else the window "<its source> не отвечает" (mel_lpv_end_msg, mel_lpv_src_tr; the
// owner 27.11.2026, (31)): the red circle, what to check (lp_down_check; a reason other than no answer - an error of
// HTTP, 403 - in its own words, mel_lpv_why), «Повторить» of the run (its 'retry'; none: no such button) and
// «Закрыть».
/**
 * @param MelLpRun $run
 * @param string $why
 * @param string $detail
 * @return MelAction
 */
function mel_lpv_down($run, $why, $detail)
{
    $st = $run['st'];
    $lang = $st['lang'];
    mel_log("lampac: window {$run['sid']}: no answer ($why" . mel_lp_mask(mel_lp_log_detail($why, $detail, ', %s'), Mel::$settings['lp']) .
        ')' . ($run['back'] ? ' -> the releases' : ''));
    if ($run['back'])
        return mel_lpv_releases($st, mel_lpv_src_tr($lang, 'lp_note_down', $st['lp_prof']));
    return mel_lpv_end_msg($run, mel_lpv_src_tr($lang, 'lp_down_src', $st['lp_prof']),
        mel_lpv_src_tr($lang, 'lp_down_src', $st['lp_prof'], array(), MEL_DIALOG_LINE_W),
        array($why === 'down' ? mel_tr($lang, 'lp_down_check') : mel_lpv_why($lang, $why, $detail)), 'k_icon_circle-x', 'err',
        $run['retry'] ? mel_btn(mel_tr($lang, 'cat_retry'), 'k_icon_refresh-cw', $run['retry']) : null);
}

// The button "Закрыть" of a window of run $sid: of a search of "Источники…" lp_cancel (mel_lpv_redraw), else closed.
/**
 * @param string $sid
 * @return MelAction
 */
function mel_lpv_close_act($sid)
{
    return MelLpv::$redraw && MelLpv::$redraw['sid'] === $sid ? mel_input('lp_cancel', array('sid' => $sid)) : mel_close_and(null);
}

// The end of run $run: the online list (the rows in the order of "Источники…", mel_lpv_srcs_pick: the sources
// asked in it; the cursor of a new search on the row of the key of the run, else on its balancer,
// after "Ещё N" on the first new row), its screen opened or the list screen replaced; nothing at all - the window "В
// <its source> для «…» ничего не нашлось" (mel_lpv_end_msg; the owner 27.11.2026, (31)): the grey mark of a search
// with nothing, «Опрошено балансеров: N, вариантов нет» (N as the search counted them, mel_lpv_search_view), then
// what Lampac said of its login (accsdb), «Другой источник» of the run (its 'other'; none: no such button) and
// «Закрыть» (a title with releases, back: those with the yellow line). All the sources found turned off in
// "Источники…" (not back): the list of no row all the same, with the yellow line of it - MENU there turns them on.
/**
 * @param MelLpRun $run
 * @return MelAction
 */
function mel_lpv_result($run)
{
    $st = $run['st'];
    $mv = $st['movie'];
    $lang = $st['lang'];
    $v = $run['v'];
    $left = count($v['sources']) - $v['next'];
    // A title with releases (back): those with the yellow line of nothing online, as before.
    $off = !$v['sources'] && !empty($v['off']) && !$run['back'] ? count($v['off']) : 0;
    $tag = "lampac: window {$run['sid']}: " . count($v['sources']) . ' sources, ' . count($v['rows']) . " rows, $left left" .
        ($off > 0 ? ", all $off found off" : '');
    if (!$v['rows'] && $left <= 0 && $off === 0)
    {
        mel_log("$tag -> " . ($run['back'] ? 'the releases' : 'nothing online'));
        if ($run['back'])
            return mel_lpv_releases($st, mel_lpv_src_tr($lang, 'lp_note_empty', $st['lp_prof']));
        $said = array();
        foreach ($v['why'] as $w)
        {
            if ($w['why'] === 'accsdb' && trim($w['detail']) !== '')
                $said[] = trim($w['detail']);
        }
        $sub = array('title' => $mv['title']);
        $asked = mel_lpv_tr($lang, 'lp_none_asked', array('n' => max(0, $v['next'] - mel_lpv_rch_n($v))));
        return mel_lpv_end_msg($run, mel_lpv_src_tr($lang, 'lp_none', $st['lp_prof'], $sub),
            mel_lpv_src_tr($lang, 'lp_none', $st['lp_prof'], $sub, MEL_DIALOG_LINE_W),
            array_merge(array($asked), array_values(array_unique($said))), 'k_icon_search-x', '',
            $run['other'] ? mel_btn(mel_tr($lang, 'lp_other_src'), 'k_icon_layers', $run['other']) : null);
    }
    MelLpv::$redraw = null;
    $rows = $v['rows'];
    $sel = $run['more'] >= 0 ? $run['more'] : 0;
    // The row of the key of a record, else the first of its balancer.
    $k = mel_lp_key_parse($run['key']);
    $path = $run['path'] !== '' ? $run['path'] : ($k ? $k[0] : '');
    $pin = -1;
    $hit = false;
    foreach ($path !== '' ? $rows : array() as $i => $r)
    {
        if ($r['key'] === $run['key'])
        {
            $pin = $i;
            $hit = true;
            break;
        }
        if ($pin < 0 && $r['path'] === $path)
            $pin = $i;
    }
    if ($pin >= 0)
        $sel = $pin;
    $note = '';
    // The row of the key found by the search (a balancer cold at "Continue"): never "gone", the voice is there.
    if ($run['path'] !== '' && !($hit && $run['fail'] === ''))
    {
        $label = ucfirst($run['path']);
        foreach ($v['sources'] as $src)
        {
            if ($src['path'] === $run['path'])
                $label = $src['label'];
        }
        $note = mel_lpv_tr($lang, $run['fail'] === '' ? 'lp_note_gone' : ($run['fail'] === 'down' ? 'lp_note_no_answer' :
            'lp_note_no_video'), array('src' => $label));
    }
    if ($off > 0)
        $note = mel_lpv_tr($lang, 'lp_note_off', array('n' => $off));
    $v['left'] = max(0, $left);
    $v['note'] = $note;
    unset($v['flt_gone']);
    $v = mel_lpv_flt_keep(mel_lpv_ep_tracks($v, $st['e']), $st);
    // A filter: all unfolded (as mel_lpv_flt_set), the balancers new to the list ("Ещё N", "Источники…") too.
    if (isset($v['flt']))
        $v['open'] = mel_lpv_open_all($v);
    if ($pin >= 0)
    {
        $v = mel_lpv_flt_show($v, $pin);
        // The balancer of the record unfolded, the cursor on its row.
        $v['open'][$rows[$pin]['path']] = true;
    }
    $L = mel_lpv_lines($v);
    // No row of a record nor "Ещё N": on the first line (by quality, mel_lpv_qsort, not the first row).
    if ($pin < 0 && $run['more'] < 0 && $L)
        $sel = $L[0]['i'];
    $v['gc'] = mel_lpv_put($v, $L, $sel);
    $v['gc_new'] = true;
    $ns = $st;
    $ns['rid'] = mel_rid();
    $ns['lp'] = $v;
    $ns['lp_on'] = true;
    unset($ns['lp_note']);
    mel_log("$tag -> the list, the cursor on row $sel of the list" .
        ($pin >= 0 ? ', ' . ($rows[$pin]['key'] === $run['key'] ? 'the row of the record' : 'the balancer of the record') : ''));
    if ($run['how'] === 'open')
        return mel_lpv_open($ns);
    return mel_list_replace($ns, $run['how'] === 'chosen' ? 3 : 1);
}

// List $ns the live one, its screen opened (over a card).
/**
 * @param MelState $ns
 * @return MelAction
 */
function mel_lpv_open($ns)
{
    Mel::$state = $ns;
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "streams:{$ns['rid']}",
            PluginOpenFolderActionData::caption => mel_ep_name($ns['movie'], $ns['s'], $ns['e'])));
}

// The releases of list $st asked now (its source of the settings), opened over the card with
// the yellow line $note; an error: its message.
/**
 * @param MelState $st
 * @param string $note
 * @return MelAction
 */
function mel_lpv_releases($st, $note)
{
    $r = mel_relist($st, $st['s'], $st['e'], '');
    if (isset($r['error']))
    {
        mel_log("lampac: the releases: {$r['error']}");
        return mel_error($r['error'], $r['detail']);
    }
    $ns = $r['st'];
    $ns['lp_note'] = $note;
    return mel_lpv_open($ns);
}
