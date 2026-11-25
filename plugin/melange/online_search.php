<?php
// "Онлайн" on the Dune: the window "Ищу онлайн…" of ticks (the search of Lampac, then the replies of its
// balancers, each call of php_server a few seconds) and its result: the online list (view_online.php) or a window
// (nothing online, no answer). PHP 5.3.6, firmware API only. Types (MelLpRun, MelLpView): phpstan-types.neon.

// The timer of the window between its ticks, ms: a tick is a request itself.
define('MEL_LPV_TICK_MS', 100);

// --- The window "Ищу онлайн…": the search (events, /lifeevents), then the replies of the
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
// MelLpv::$redraw, mel_lpv_redraw_keys).
/**
 * @param MelState $st
 * @param string $how
 * @param array{back?: bool, path?: string, key?: string, fail?: string, more?: bool, redraw?: bool} $opt
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
        'poll' => mel_lp_poll_start(count($v['rows'])), 'more' => $more ? count($v['rows']) : -1);
    // A window of before closed by RETURN of the shell (no lp_cancel): its end.
    mel_lpv_run_end();
    MelLpv::$run = $run;
    MelLpv::$redraw = !empty($opt['redraw']) ? array('sid' => $sid, 'st' => $st) : null;
    mel_log("lampac: window $sid, " . mel_ep_tag($st) . ", $how, phase {$run['phase']}" .
        ($run['back'] ? ', back' : '') . ($run['path'] !== '' ? ", balancer {$run['path']}" : '') .
        (MelLpv::$redraw ? ', redraw' : ''));
    return mel_lpv_redraw_keys(mel_dialog_act(mel_tr($st['lang'], 'lp_search'), mel_lpv_search_defs($run), array(
        ShowDialogActionData::actions => array(GUI_EVENT_TIMER => mel_input('lp_tick', array('sid' => $sid), 30000)),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => MEL_LPV_TICK_MS))), $sid);
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

// lp_cancel of the window $in->sid: of a search of "Источники…" (MelLpv::$redraw) its list of the new choice put in,
// the screen replaced (else the rows of before stay there, of the sources just turned off too); else nothing.
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

// Controls of the window of run $run: the sources found, the balancers asked and the variants
// of them (both lines always: the window keeps its height), "Отмена".
/**
 * @param MelLpRun $run
 * @return list<array<string, mixed>>
 */
function mel_lpv_search_defs($run)
{
    $lang = $run['st']['lang'];
    $v = $run['v'];
    // Those of a refusal of RCH (rch true asked, mel_lp_source) not counted, as before.
    $rch = mel_lpv_rch_n($v);
    $n = count($v['sources']) - $rch;
    $l1 = mel_lpv_tr($lang, 'lp_search_found', array('n' => $n));
    $l2 = $run['phase'] === 'replies' ? mel_lpv_tr($lang, 'lp_search_asking', array('k' => max(0, $v['next'] - $rch), 'n' => $n,
        'r' => count($v['rows']))) : mel_tr($lang, 'lp_search_wait');
    return array(
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l1)),
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l2)),
        mel_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => mel_tr($lang, 'lp_cancel'),
            GuiButtonDef::width => 300, GuiButtonDef::push_action => mel_input('lp_cancel', array('sid' => $run['sid'])))));
}

// lp_tick of the window $in->sid: one step of its run - /externalids, the start of the search
// (events), a /lifeevents (or the one events without life: up to MEL_LP_SEARCH_MAX s), the
// replies of a few balancers - then the window redrawn and its timer again, or closed with the
// result (mel_lpv_result).
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
    // A tick is an operation of its own: the Lampac of the list of the run (its profile, spec v2 §2.2), not the main one.
    list(, $lp) = mel_op_use($run['st']['prof'], $run['st']['lp_prof']);
    $base = Mel::$settings['lp'];
    // That profile gone, not ready or of another address since (the page): no request - not to the address of before
    // with the auth of another Lampac, nor to another one with the balancers of this one.
    if ($lp !== $run['st']['lp_prof'] || ($run['search'] && $run['search']['base'] !== $base))
    {
        MelLpv::$run = null;
        mel_log("lampac: window {$run['sid']}: its profile of Lampac gone or changed on the page, closed with no request");
        return mel_close_and(mel_lpv_redraw_keys(mel_dialog(mel_tr($run['st']['lang'], 'src_changed'), array(),
            array(mel_tr($run['st']['lang'], 'lp_close') => mel_lpv_close_act($run['sid']))), $run['sid']));
    }
    if ($base === '')
    {
        MelLpv::$run = null;
        return mel_close_and(mel_lpv_down($run, 'down', 'no address'));
    }
    if ($run['phase'] === 'ids')
    {
        list($run['v']['title']) = mel_lp_externalids($base, $run['v']['title']);
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
            {
                MelLpv::$run = null;
                return mel_close_and(mel_lpv_down($run, $s['err'], $s['detail']));
            }
            if ($s['err'] === 'accsdb')
                $run['v']['why'][] = array('src' => '', 'path' => '', 'why' => 'accsdb', 'detail' => $s['detail']);
            $run['v']['q'] = $s['q'];
            $run['v']['t'] = $s['t0'];
            $run['phase'] = $run['v']['sources'] ? 'replies' : 'done';
            $run['poll'] = mel_lp_poll_start(count($run['v']['rows']));
        }
    }
    MelLpv::$run = $run;
    if ($run['phase'] === 'done')
    {
        mel_lpv_run_end();
        return mel_close_and(mel_lpv_result($run));
    }
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

// The end of the search window MelLpv::$run: done (mel_lpv_tick), cancelled (lp_cancel) or left (a window closed by the
// shell, then a new search: mel_lpv_search_open). In its replies or done: its sources into "Источники…" of its own profile
// of Lampac (mel_lpv_run_seen; the pair of the operation may be another one - that of the main profile, of a new title),
// that profile gone or not ready - none kept. MelLpv::$run none, the pair of the operation as it was.
/**
 * @return void
 */
function mel_lpv_run_end()
{
    $run = MelLpv::$run;
    MelLpv::$run = null;
    if (!$run || ($run['phase'] !== 'replies' && $run['phase'] !== 'done'))
        return;
    list($rel, $lp) = array(Mel::$settings['prof'], Mel::$settings['lp_prof']);
    list(, $l) = mel_op_use($run['st']['prof'], $run['st']['lp_prof']);
    if ($l !== '' && $l === $run['st']['lp_prof'])
        mel_lpv_run_seen($run);
    else
        mel_log("lampac: window {$run['sid']}: its profile of Lampac gone, its sources not kept");
    mel_op_use($rel, $lp);
}

// The sources of run $run (mel_lpv_run_end, of the pair of the run) into "Источники…" (mel_lpv_srcs_seen): those of its
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
// those with the yellow line; else the window "Онлайн не отвечает: <its source>".
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
        return mel_lpv_releases($st, mel_tr($lang, 'lp_note_down'));
    // The source of Lampac of the screen by its name (spec v2: several of them; a wide one cut with "…" - the shell cuts a
    // title wider than the dialog in the middle), none ready - the words of before.
    $name = mel_src_name(Mel::$settings['lp_prof']);
    $title = $name !== '' ? mel_gc_cut_w(str_replace('{name}', $name, mel_tr($lang, 'lp_down_src')), 1000, MEL_DIALOG_LINE_W) :
        mel_tr($lang, 'lp_down');
    return mel_lpv_redraw_keys(mel_dialog($title, mel_dialog_lines(mel_lpv_why($lang, $why, $detail)),
        array(mel_tr($lang, 'lp_close') => mel_lpv_close_act($run['sid']))), $run['sid']);
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
// after "Ещё N" on the first new row), its screen opened or the list screen replaced; nothing at all - the window "Онлайн для
// «…» не нашлось" (a title with releases, back: those with the yellow line). All the sources found turned off in
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
            return mel_lpv_releases($st, mel_tr($lang, 'lp_note_empty'));
        $lines = array();
        foreach ($v['why'] as $w)
        {
            if ($w['why'] === 'accsdb' && $w['detail'] !== '')
                $lines = array_merge($lines, mel_dialog_lines($w['detail']));
        }
        return mel_lpv_redraw_keys(mel_dialog(mel_lpv_tr($lang, 'lp_none', array('title' => $mv['title'])),
            array_slice($lines, 0, 2), array(mel_tr($lang, 'lp_close') => mel_lpv_close_act($run['sid']))), $run['sid']);
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
