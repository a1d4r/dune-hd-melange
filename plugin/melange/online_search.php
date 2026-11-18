<?php
// "Онлайн" on the Dune: the window "Ищу онлайн…" of ticks (the search of Lampac, then the replies of its
// balancers, each call of php_server a few seconds) and its result: the online list (view_online.php) or a window
// (nothing online, no answer). PHP 5.3.6, firmware API only. Types (AioLpRun, AioLpView): phpstan-types.neon.

// The timer of the window between its ticks, ms: a tick is a request itself.
define('AIO_LPV_TICK_MS', 100);

// --- The window "Ищу онлайн…": the search (events, /lifeevents), then the replies of the
// balancers, a tick each call of php_server (spec §4.3); the result - the online list, else
// a window. AioLpv::$run keeps it between the ticks; Cancel or RETURN drops the window.

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
// AioLpv::$redraw, aio_lpv_redraw_keys).
/**
 * @param AioState $st
 * @param string $how
 * @param array{back?: bool, path?: string, key?: string, fail?: string, more?: bool, redraw?: bool} $opt
 * @return AioAction
 */
function aio_lpv_search_open($st, $how, $opt = array())
{
    $sid = aio_rid();
    $more = !empty($opt['more']) && isset($st['lp']);
    $v = array('title' => aio_lpv_title($st['movie']), 'q' => '', 'sources' => array(),
        'hidden' => array('show' => 0, 'rch' => 0, 'bad' => 0), 'rows' => array(), 'why' => array(), 'next' => 0,
        'left' => 0, 't' => aio_lp_now(), 'note' => '');
    if ($more)
    {
        $v = $st['lp'];
        unset($v['gc'], $v['gc_new'], $v['gc_prog']);
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
    $run = array('sid' => $sid, 'st' => $st, 'how' => $how, 'back' => !empty($opt['back']),
        'path' => isset($opt['path']) ? $opt['path'] : '', 'key' => isset($opt['key']) ? $opt['key'] : '',
        'fail' => isset($opt['fail']) ? $opt['fail'] : '', 'phase' => $more ? 'replies' : 'ids', 'search' => null, 'v' => $v,
        'poll' => aio_lp_poll_start(count($v['rows'])), 'more' => $more ? count($v['rows']) : -1);
    AioLpv::$run = $run;
    AioLpv::$redraw = !empty($opt['redraw']) ? array('sid' => $sid, 'st' => $st) : null;
    aio_log("lampac: window $sid, " . aio_ep_tag($st) . ", $how, phase {$run['phase']}" .
        ($run['back'] ? ', back' : '') . ($run['path'] !== '' ? ", balancer {$run['path']}" : '') .
        (AioLpv::$redraw ? ', redraw' : ''));
    return aio_lpv_redraw_keys(aio_dialog_act(aio_tr($st['lang'], 'lp_search'), aio_lpv_search_defs($run), array(
        ShowDialogActionData::actions => array(GUI_EVENT_TIMER => aio_input('lp_tick', array('sid' => $sid), 30000)),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => AIO_LPV_TICK_MS))), $sid);
}

// Dialog $a of the window $sid (the search, no answer, nothing) of a search of "Источники…" (AioLpv::$redraw): RETURN
// to lp_cancel (aio_lpv_redraw), not closed by the shell; else $a as it is.
/**
 * @param AioAction $a
 * @param string $sid
 * @return AioAction
 */
function aio_lpv_redraw_keys($a, $sid)
{
    if (!AioLpv::$redraw || AioLpv::$redraw['sid'] !== $sid)
        return $a;
    // The keys of show_dialog and of change_behaviour: both 'actions'.
    $key = ShowDialogActionData::actions;
    if ($a[GuiAction::handler_string_id] === SHOW_DIALOG_ACTION_ID)
        $a[GuiAction::data][ShowDialogActionData::close_by_return] = false;
    $acts = isset($a[GuiAction::data][$key]) ? $a[GuiAction::data][$key] : array();
    $acts[GUI_EVENT_KEY_RETURN] = aio_input('lp_cancel', array('sid' => $sid));
    $a[GuiAction::data][$key] = $acts;
    return $a;
}

// lp_cancel of the window $in->sid: of a search of "Источники…" (AioLpv::$redraw) its list of the new choice put in,
// the screen replaced (else the rows of before stay there, of the sources just turned off too); else nothing.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_lpv_redraw($in)
{
    $r = AioLpv::$redraw;
    if (!$r || !isset($in->sid) || $in->sid !== $r['sid'])
        return null;
    AioLpv::$redraw = null;
    aio_log('lampac: window ' . $r['sid'] . ' closed with no list: the list of the new choice, ' . count($r['st']['lp']['rows']) .
        ' rows');
    return aio_list_replace($r['st']);
}

// Controls of the window of run $run: the sources found, the balancers asked and the variants
// of them (both lines always: the window keeps its height), "Отмена".
/**
 * @param AioLpRun $run
 * @return list<array<string, mixed>>
 */
function aio_lpv_search_defs($run)
{
    $lang = $run['st']['lang'];
    $v = $run['v'];
    $n = count($v['sources']);
    $l1 = aio_lpv_tr($lang, 'lp_search_found', array('n' => $n));
    $l2 = $run['phase'] === 'replies' ? aio_lpv_tr($lang, 'lp_search_asking', array('k' => $v['next'], 'n' => $n,
        'r' => count($v['rows']))) : aio_tr($lang, 'lp_search_wait');
    return array(
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l1)),
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l2)),
        aio_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => aio_tr($lang, 'lp_cancel'),
            GuiButtonDef::width => 300, GuiButtonDef::push_action => aio_input('lp_cancel', array('sid' => $run['sid'])))));
}

// lp_tick of the window $in->sid: one step of its run - /externalids, the start of the search
// (events), a /lifeevents (or the one events without life: up to AIO_LP_SEARCH_MAX s), the
// replies of a few balancers - then the window redrawn and its timer again, or closed with the
// result (aio_lpv_result).
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_lpv_tick($in)
{
    $run = AioLpv::$run;
    if (!$run || !isset($in->sid) || $in->sid !== $run['sid'])
    {
        aio_log('lampac: tick of a window gone');
        return aio_close_and(null);
    }
    // A tick is an operation of its own: the Lampac of the list of the run (its profile, spec v2 §2.2), not the main one.
    list(, $lp) = aio_op_use($run['st']['prof'], $run['st']['lp_prof']);
    $base = Aio::$settings['lp'];
    // That profile gone, not ready or of another address since (the page): no request - not to the address of before
    // with the auth of another Lampac, nor to another one with the balancers of this one.
    if ($lp !== $run['st']['lp_prof'] || ($run['search'] && $run['search']['base'] !== $base))
    {
        AioLpv::$run = null;
        aio_log("lampac: window {$run['sid']}: its profile of Lampac gone or changed on the page, closed with no request");
        return aio_close_and(aio_lpv_redraw_keys(aio_dialog(aio_tr($run['st']['lang'], 'src_changed'), array(),
            array(aio_tr($run['st']['lang'], 'lp_close') => aio_lpv_close_act($run['sid']))), $run['sid']));
    }
    if ($base === '')
    {
        AioLpv::$run = null;
        return aio_close_and(aio_lpv_down($run, 'down', 'no address'));
    }
    if ($run['phase'] === 'ids')
    {
        list($run['v']['title']) = aio_lp_externalids($base, $run['v']['title']);
        $run['phase'] = 'start';
    }
    else if ($run['phase'] === 'start')
    {
        $run['search'] = aio_lp_search_start($base, $run['v']['title']);
        $run['phase'] = 'search';
    }
    else if ($run['phase'] === 'search' && $run['search'])
        $run['search'] = aio_lp_search_step($run['search']);
    else if ($run['phase'] === 'replies')
    {
        list($run['poll'], $run['v'], $over) = aio_lp_poll($base, $run['poll'], $run['v'],
            $run['st']['movie']['series'] ? $run['st']['s'] : 0);
        if ($over)
            $run['phase'] = 'done';
    }
    $s = $run['search'];
    if ($run['phase'] === 'search' && $s)
    {
        // Those turned off in "Источники…" not asked, nor counted; the others in its order. Done: the new ones there.
        if ($s['done'])
            aio_lpv_srcs_seen($s['sources']);
        list($run['v']['sources'], $run['v']['off']) = aio_lpv_srcs_pick($s['sources'], Aio::$settings['lp_src']);
        $run['v']['hidden'] = $s['hidden'];
        if ($s['done'])
        {
            if ($run['v']['off'])
                aio_log('lampac: sources off: ' . implode(', ', $run['v']['off']));
            if (!$s['sources'] && $s['err'] !== '' && $s['err'] !== 'accsdb')
            {
                AioLpv::$run = null;
                return aio_close_and(aio_lpv_down($run, $s['err'], $s['detail']));
            }
            if ($s['err'] === 'accsdb')
                $run['v']['why'][] = array('src' => '', 'path' => '', 'why' => 'accsdb', 'detail' => $s['detail']);
            $run['v']['q'] = $s['q'];
            $run['v']['t'] = $s['t0'];
            $run['phase'] = $run['v']['sources'] ? 'replies' : 'done';
            $run['poll'] = aio_lp_poll_start(count($run['v']['rows']));
        }
    }
    AioLpv::$run = $run;
    if ($run['phase'] === 'done')
    {
        AioLpv::$run = null;
        return aio_close_and(aio_lpv_result($run));
    }
    return aio_lpv_redraw_keys(array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => array(GUI_EVENT_TIMER => aio_input('lp_tick', array('sid' => $run['sid']), 30000)),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => AIO_LPV_TICK_MS),
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => aio_lpv_search_defs($run),
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null)))), $run['sid']);
}

// Lampac of run $run did not answer ($why, $detail: AioLpWhy): a title with releases (back) -
// those with the yellow line; else the window "Онлайн не отвечает: <its source>".
/**
 * @param AioLpRun $run
 * @param string $why
 * @param string $detail
 * @return AioAction
 */
function aio_lpv_down($run, $why, $detail)
{
    $st = $run['st'];
    $lang = $st['lang'];
    aio_log("lampac: window {$run['sid']}: no answer ($why" . aio_lp_mask(aio_lp_log_detail($why, $detail, ', %s'), Aio::$settings['lp']) .
        ')' . ($run['back'] ? ' -> the releases' : ''));
    if ($run['back'])
        return aio_lpv_releases($st, aio_tr($lang, 'lp_note_down'));
    // The source of Lampac of the screen by its name (spec v2: several of them; a wide one cut with "…" - the shell cuts a
    // title wider than the dialog in the middle), none ready - the words of before.
    $name = aio_src_name(Aio::$settings['lp_prof']);
    $title = $name !== '' ? aio_gc_cut_w(str_replace('{name}', $name, aio_tr($lang, 'lp_down_src')), 1000, AIO_DIALOG_LINE_W) :
        aio_tr($lang, 'lp_down');
    return aio_lpv_redraw_keys(aio_dialog($title, aio_dialog_lines(aio_lpv_why($lang, $why, $detail)),
        array(aio_tr($lang, 'lp_close') => aio_lpv_close_act($run['sid']))), $run['sid']);
}

// The button "Закрыть" of a window of run $sid: of a search of "Источники…" lp_cancel (aio_lpv_redraw), else closed.
/**
 * @param string $sid
 * @return AioAction
 */
function aio_lpv_close_act($sid)
{
    return AioLpv::$redraw && AioLpv::$redraw['sid'] === $sid ? aio_input('lp_cancel', array('sid' => $sid)) : aio_close_and(null);
}

// The end of run $run: the online list (the rows in the order of "Источники…", aio_lpv_srcs_pick: the sources
// asked in it; the cursor of a new search on the row of the key of the run, else on its balancer,
// after "Ещё N" on the first new row), its screen opened or the list screen replaced; nothing at all - the window "Онлайн для
// «…» не нашлось" (a title with releases, back: those with the yellow line). All the sources found turned off in
// "Источники…" (not back): the list of no row all the same, with the yellow line of it - MENU there turns them on.
/**
 * @param AioLpRun $run
 * @return AioAction
 */
function aio_lpv_result($run)
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
        aio_log("$tag -> " . ($run['back'] ? 'the releases' : 'nothing online'));
        if ($run['back'])
            return aio_lpv_releases($st, aio_tr($lang, 'lp_note_empty'));
        $lines = array();
        foreach ($v['why'] as $w)
        {
            if ($w['why'] === 'accsdb' && $w['detail'] !== '')
                $lines = array_merge($lines, aio_dialog_lines($w['detail']));
        }
        return aio_lpv_redraw_keys(aio_dialog(aio_lpv_tr($lang, 'lp_none', array('title' => $mv['title'])),
            array_slice($lines, 0, 2), array(aio_tr($lang, 'lp_close') => aio_lpv_close_act($run['sid']))), $run['sid']);
    }
    AioLpv::$redraw = null;
    $rows = $v['rows'];
    $sel = $run['more'] >= 0 ? $run['more'] : 0;
    // The row of the key of a record, else the first of its balancer.
    $k = aio_lp_key_parse($run['key']);
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
        $note = aio_lpv_tr($lang, $run['fail'] === '' ? 'lp_note_gone' : ($run['fail'] === 'down' ? 'lp_note_no_answer' :
            'lp_note_no_video'), array('src' => $label));
    }
    if ($off > 0)
        $note = aio_lpv_tr($lang, 'lp_note_off', array('n' => $off));
    $v['left'] = max(0, $left);
    $v['note'] = $note;
    unset($v['flt_gone']);
    $v = aio_lpv_flt_keep(aio_lpv_ep_tracks($v, $st['e']), $st);
    // A filter: all unfolded (as aio_lpv_flt_set), the balancers new to the list ("Ещё N", "Источники…") too.
    if (isset($v['flt']))
        $v['open'] = aio_lpv_open_all($v);
    if ($pin >= 0)
    {
        $v = aio_lpv_flt_show($v, $pin);
        // The balancer of the record unfolded, the cursor on its row.
        $v['open'][$rows[$pin]['path']] = true;
    }
    $L = aio_lpv_lines($v);
    // No row of a record nor "Ещё N": on the first line (by quality, aio_lpv_qsort, not the first row).
    if ($pin < 0 && $run['more'] < 0 && $L)
        $sel = $L[0]['i'];
    $v['gc'] = aio_lpv_put($L, $sel);
    $v['gc_new'] = true;
    $ns = $st;
    $ns['rid'] = aio_rid();
    $ns['lp'] = $v;
    $ns['lp_on'] = true;
    unset($ns['lp_note']);
    aio_log("$tag -> the list, the cursor on row $sel of the list" .
        ($pin >= 0 ? ', ' . ($rows[$pin]['key'] === $run['key'] ? 'the row of the record' : 'the balancer of the record') : ''));
    if ($run['how'] === 'open')
        return aio_lpv_open($ns);
    return aio_list_replace($ns, $run['how'] === 'chosen' ? 3 : 1);
}

// List $ns the live one, its screen opened (over a card).
/**
 * @param AioState $ns
 * @return AioAction
 */
function aio_lpv_open($ns)
{
    Aio::$state = $ns;
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "streams:{$ns['rid']}",
            PluginOpenFolderActionData::caption => aio_ep_name($ns['movie'], $ns['s'], $ns['e'])));
}

// The releases of list $st asked now (its source of the settings), opened over the card with
// the yellow line $note; an error: its message.
/**
 * @param AioState $st
 * @param string $note
 * @return AioAction
 */
function aio_lpv_releases($st, $note)
{
    $r = aio_relist($st, $st['s'], $st['e'], '');
    if (isset($r['error']))
    {
        aio_log("lampac: the releases: {$r['error']}");
        return aio_error($r['error'], $r['detail']);
    }
    $ns = $r['st'];
    $ns['lp_note'] = $note;
    return aio_lpv_open($ns);
}
