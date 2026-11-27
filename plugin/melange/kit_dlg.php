<?php
// The own dialog and the wait window over a GComps screen (UI kit, stage 6b-1): their keys, their state and the ticks
// of a wait. The view is the builders of kit.php (MelKit::dialog, MelKit::wait_box); the open, the close and the stale
// screen are those of the overlay (kit_ovl.php): a dialog and a wait are what it shows, one at a time. Required by
// main.php after kit_ovl.php. PHP 5.3.6, firmware API only; types (MelDlgDesc, MelDlgButton, MelWaitDesc, MelDlg,
// MelWait): phpstan-types.neon.
//
// The screen puts mel_ovl_slot() last in its comp_defs and opens over itself, with the record of itself (MelOvlScreen,
// kit_ovl.php), one of:
// - a dialog, mel_dlg_open($scr, $d) (mel_dlg_error: the simple one, a title, a reason and one button). Its description
//   (MelDlgDesc): icon (k_icon_*), tone ('', warn, err, ok: the colour of the icon), title, text, buttons (1 to 3, each
//   array('label', 'icon', 'act': run after the close, none: the close only)), focus (the button in focus first, default
//   0), cancel (run after the close by RETURN, none: the close only). LEFT and RIGHT move the focus among the buttons, to
//   the first and the last (no wrap); ENTER: the close, then the act of the button in focus; RETURN: the close, then
//   cancel;
// - a wait, mel_wait_open($scr, $w): the wait window (MelKit::wait_box: the time by the title, the facts, the stage
//   and its count over the bar, two facts under it) and the timer of its ticks. Every MEL_WAIT_TICK_MS the tick (wt_tick; its show_dialog_delay MEL_WAIT_DELAY is over any tick,
//   so the shell never shows its «Подключение…» over the window [device, gcomps_overlay_probe]) calls the function of the
//   screen tick($data, $sec): $data as the last tick left it, $sec since the open. It does a piece of the work and
//   replies (MelWaitTick), one of:
//     post    the work is done: the close, then post (the player, plugin_open_folder of a screen);
//     dialog  it failed: the dialog (MelDlgDesc) in the place of the window, the screen stays under the shade;
//     else    still at work: stage («Буфер»), count («28 из 32 МБ»), share (0..1; null: the empty bar), foot_l, foot_r
//             (the facts under the bar: «Скорость 17 Мбит/с, нужно ~69 Мбит/с», «Пиров 9 из 31, сидов 8»), foot_tone
//             (of foot_l: '', warn, err, ok) and data, each when given; late false: the late look not yet, whatever
//             the time (a second button that needs something found: it comes with the first tick past late['sec']
//             that does not say so); the window redraws only what changed (the children of its groups), then the
//             timer again.
//   Its description (MelWaitDesc): icon, tone, title, facts (the line under the title, of the whole wait: «Джентльмены
//   (2019) · 4K · Remux · 24.2 ГБ»; '' none), stage, count, share, foot_l, foot_r, foot_tone (as a reply), elapsed (the
//   time by the title, {sec} the seconds: «прошло {sec} с»; '' none), buttons (1 to 3, each array('id', 'label',
//   'icon', 'act', 'cancel': RETURN presses it)), focus, cancel (run after the close by RETURN with no cancel button,
//   none: the close only), tick (the name of the function), data, ms (the period of its ticks, default
//   MEL_WAIT_TICK_MS: a tick that is a request itself comes sooner; the time by the title and late are by the clock,
//   not by the ticks), late: at late['sec'] s (default MEL_WAIT_LATE),
//   once, its icon, tone, buttons («Показать найденное» before «Отмена») and focus (default: the button of the same id,
//   else the first), its text (only the wait screen of kit_scr.php shows it, in the place of the stage; the window has
//   no long text: owner 27.11.2026). The foot is there by the description, for the whole wait: foot true, or either
//   of its facts given at the open - a tick that empties both leaves its line empty, the window keeps its height. A
//   button (ENTER, or RETURN on cancel) closes the window, then runs its act (the screen stops its work there): a tick
//   that comes after the close (sent before it) gets no reply. The button and the data of the wait as its last tick
//   left it are kept for the screen (wt_done: mel_wt_done($owner), given once - «Показать найденное» shows what the
//   ticks found); not taken, the record waits as one with kp_done does (mel_ovl_park). Every key of a wait, of use or not, gives
//   its map and its timer again (a key may stop the timer of the shell; not checked on the device).
// The keys of both (mel_dlg_press) are those of the error and wait screen of kit_scr.php too.
// The errors and the messages with one button of our screens come here by the dispatcher, not by their code: the
// builder of such a window of the shell says the message it is (mel_error, mel_msg: Mel::$dlg, main.php), and the reply
// that is this window to an own input of a screen with an overlay opens mel_dlg_error over it (mel_dlg_of); what has no
// look of ours approved keeps the window of the shell (mel_msg with tone false, mel_dialog).
// The state, MelOvl::$st: dlg (MelDlg), wt (MelWait) while open; wt_done (MelWaitDone) after a wait closed by a
// button.

// The prefixes of the ids of the window (MelKit::dialog, MelKit::wait_box): dlg_win, dlg_b0, wt_stage, wt_bar, …
define('MEL_DLG', 'dlg');
define('MEL_WT', 'wt');
// The ticks of a wait, ms; show_dialog_delay of a tick, ms: over the longest tick (at 5000 «Подключение…» showed in
// ~5 s, at 60000 never [device, gcomps_overlay_probe]); the late wait, s (owner 19.11: «отвечает дольше обычного»).
define('MEL_WAIT_TICK_MS', 1000);
define('MEL_WAIT_DELAY', 60000);
define('MEL_WAIT_LATE', 25);

// The buttons of description $list ($what: for the log): those with a label, 3 at most, each with id ('b<n>' none),
// label, icon, act (null none), cancel.
/**
 * @param mixed $list
 * @param string $what
 * @return list<MelDlgButton>
 */
function mel_dlg_buttons($list, $what)
{
    $out = array();
    foreach (is_array($list) ? $list : array() as $i => $b)
    {
        if (!is_array($b) || !isset($b['label']) || !is_string($b['label']) || $b['label'] === '')
        {
            mel_log("kit $what: a button with no label, left out");
            continue;
        }
        if (count($out) === 3)
        {
            mel_log("kit $what: more than 3 buttons, the first 3");
            break;
        }
        $out[] = array('id' => isset($b['id']) && is_string($b['id']) ? $b['id'] : "b$i", 'label' => $b['label'],
            'icon' => isset($b['icon']) && is_string($b['icon']) ? $b['icon'] : '',
            'act' => isset($b['act']) && is_array($b['act']) ? $b['act'] : null, 'cancel' => !empty($b['cancel']));
    }
    return $out;
}

// Focus $f of a description within its $n buttons: an int in 0..n-1, else 0.
/**
 * @param mixed $f
 * @param int $n
 * @return int
 */
function mel_dlg_focus($f, $n)
{
    return is_int($f) && $f >= 0 && $f < $n ? $f : 0;
}

// String field $k of description $d: '' none.
/**
 * @param array<string, mixed> $d
 * @param string $k
 * @return string
 */
function mel_dlg_str($d, $k)
{
    return isset($d[$k]) && is_string($d[$k]) ? $d[$k] : '';
}

// Action field $k of description $d: an array, else null.
/**
 * @param array<string, mixed> $d
 * @param string $k
 * @return MelAction|null
 */
function mel_dlg_act($d, $k)
{
    return isset($d[$k]) && is_array($d[$k]) ? $d[$k] : null;
}

// The state of a new dialog of description $d (MelDlgDesc, see above; $what: for the log): its view, its buttons with
// the focus, its cancel. The overlay (mel_dlg_open) and the error screen of kit_scr.php.
/**
 * @param array<string, mixed> $d
 * @param string $what
 * @return MelDlg
 */
function mel_dlg_new($d, $what)
{
    $bs = mel_dlg_buttons(isset($d['buttons']) ? $d['buttons'] : null, $what);
    return array('icon' => mel_dlg_str($d, 'icon'), 'tone' => mel_dlg_str($d, 'tone'), 'title' => mel_dlg_str($d, 'title'),
        'text' => mel_dlg_str($d, 'text'), 'buttons' => $bs,
        'cur' => mel_dlg_focus(isset($d['focus']) ? $d['focus'] : 0, count($bs)), 'cancel' => mel_dlg_act($d, 'cancel'));
}

// Key $k of dialog or wait $v (MelDlg, MelWait; $wait: RETURN presses its cancel button) -> what follows, null none
// (LEFT on the first button, RIGHT on the last, ENTER with no button, another key): array('cur' => n), the focus on
// button n; array('button' => b), it ends by button b (ENTER on it, RETURN on the cancel button of a wait) or, b null,
// by RETURN (the host runs its cancel). The host (the overlay, the screen of kit_scr.php) does only what follows.
/**
 * @param MelDlg|MelWait $v
 * @param string $k
 * @param bool $wait
 * @return array{cur: int}|array{button: MelDlgButton|null}|null
 */
function mel_dlg_press($v, $k, $wait)
{
    if ($k === 'left' || $k === 'right')
    {
        $to = $v['cur'] + ($k === 'left' ? -1 : 1);
        return $to >= 0 && $to < count($v['buttons']) ? array('cur' => $to) : null;
    }
    if ($k === 'enter')
        return isset($v['buttons'][$v['cur']]) ? array('button' => $v['buttons'][$v['cur']]) : null;
    if ($k !== 'return')
        return null;
    foreach ($wait ? $v['buttons'] : array() as $b)
    {
        if ($b['cancel'])
            return array('button' => $b);
    }
    return array('button' => null);
}

// The view of $s (MelDlg or MelWait) for MelKit::dialog / wait_box: its buttons with the focus on cur.
/**
 * @param MelDlg|MelWait $s
 * @return list<array{label: string, icon: string, state: string}>
 */
function mel_dlg_btn_views($s)
{
    $out = array();
    foreach ($s['buttons'] as $i => $b)
        $out[] = array('label' => $b['label'], 'icon' => $b['icon'], 'state' => $i === $s['cur'] ? 'focus' : '');
    return $out;
}

// The components of open dialog $s: the shade dlg_dim and the window dlg_win.
/**
 * @param MelDlg $s
 * @return list<MelGcComp>
 */
function mel_dlg_defs($s)
{
    $k = MelKit::dialog(array('id' => MEL_DLG, 'icon' => $s['icon'], 'tone' => $s['tone'], 'title' => $s['title'],
        'text' => $s['text'], 'buttons' => mel_dlg_btn_views($s)));
    return $k['defs'];
}

// The time of wait $s: elapsed with its seconds.
/**
 * @param MelWait $s
 * @return string
 */
function mel_wait_time($s)
{
    return str_replace('{sec}', strval($s['sec']), $s['elapsed']);
}

// The components of open wait $s: the shade wt_dim and the window wt_win.
/**
 * @param MelWait $s
 * @return list<MelGcComp>
 */
function mel_wait_defs($s)
{
    $k = MelKit::wait_box(array('id' => MEL_WT, 'icon' => $s['icon'], 'tone' => $s['tone'], 'title' => $s['title'],
        'time' => mel_wait_time($s), 'facts' => $s['facts'], 'stage' => $s['stage'], 'count' => $s['count'],
        'share' => $s['share'], 'foot' => $s['foot'], 'foot_l' => $s['foot_l'], 'foot_r' => $s['foot_r'],
        'foot_tone' => $s['foot_tone'], 'buttons' => mel_dlg_btn_views($s)));
    return $k['defs'];
}

// The changes from the window $win (the id of a panel: of dialog / wait_box, the column of error_screen) of components
// $was to that of $now, only of what differs: a group of the window (a block, a button) by its children and, when it
// moved, its geometry; the window whole (its geometry and children) when its geometry or the list of its components
// changed (a line more, a button more), or a component of it with no id (its plates). No window in $now: none.
/**
 * @param list<MelGcComp> $was
 * @param list<MelGcComp> $now
 * @param string $win
 * @return list<MelGcChange>
 */
function mel_dlg_diff($was, $now, $win)
{
    $a = null;
    $b = null;
    foreach ($was as $c)
    {
        if (isset($c[GComponentDef::id]) && $c[GComponentDef::id] === $win)
            $a = $c;
    }
    foreach ($now as $c)
    {
        if (isset($c[GComponentDef::id]) && $c[GComponentDef::id] === $win)
            $b = $c;
    }
    if ($b === null)
        return array();
    $ka = $a !== null ? $a[GComponentDef::specific_def][GCompPanelDef::children] : array();
    $kb = $b[GComponentDef::specific_def][GCompPanelDef::children];
    $whole = $a === null || $a[GComponentDef::geom_def] !== $b[GComponentDef::geom_def] || count($ka) !== count($kb);
    $ch = array();
    foreach ($kb as $i => $c)
    {
        if ($whole)
            break;
        $id = isset($c[GComponentDef::id]) ? $c[GComponentDef::id] : null;
        $was_id = isset($ka[$i][GComponentDef::id]) ? $ka[$i][GComponentDef::id] : null;
        if ($id !== $was_id || ($id === null && $c !== $ka[$i]))
            $whole = true;
        elseif ($id !== null && $c !== $ka[$i])
        {
            $g = $c[GComponentDef::geom_def];
            $kids = $c[GComponentDef::specific_def][GCompPanelDef::children];
            $ch[] = mel_gc_change((string) $id, $g !== $ka[$i][GComponentDef::geom_def] ? $g : null, GCOMP_TRANSITION_NONE,
                $kids !== $ka[$i][GComponentDef::specific_def][GCompPanelDef::children] ? $kids : null);
        }
    }
    if ($whole)
        return array(mel_gc_change($win, $b[GComponentDef::geom_def], GCOMP_TRANSITION_NONE, $kb));
    return $ch;
}

// The action map of an open dialog or wait ($kind dlg_key, wt_key) of the screen of owner $owner: LEFT, RIGHT, ENTER,
// RETURN; the others none (the screen under the window does not move).
/**
 * @param string $kind
 * @param string $owner
 * @return array<string, MelAction>
 */
function mel_dlg_map($kind, $owner)
{
    $map = array();
    foreach (array(GUI_EVENT_KEY_LEFT => 'left', GUI_EVENT_KEY_RIGHT => 'right', GUI_EVENT_KEY_ENTER => 'enter',
        GUI_EVENT_KEY_RETURN => 'return') as $ev => $k)
        $map[$ev] = mel_ovl_input($kind, $owner, array('k' => $k));
    return $map;
}

// Open dialog $d (MelDlgDesc, see above) over the screen of record $scr (MelOvlScreen, checked by mel_ovl_open): the
// shade and the window the children of ovl, its keys the map. Not a record: null, logged.
/**
 * @param array<string, mixed> $scr
 * @param array<string, mixed> $d
 * @return MelAction|null
 */
function mel_dlg_open($scr, $d)
{
    $s = mel_dlg_new($d, 'dialog');
    $owner = isset($scr['owner']) && is_string($scr['owner']) ? $scr['owner'] : '';
    $a = mel_ovl_open($scr, array('dlg' => $s), mel_dlg_map('dlg_key', $owner), mel_dlg_defs($s));
    // Not its title: it may name what is the user's own (a source, a server), and the log is for others to read.
    if ($a)
        mel_log('kit dialog: open, ' . count($s['buttons']) . ' buttons');
    return $a;
}

// The description (MelDlgDesc) of the simple dialog of an error on our screen: the title $title, the reason $text
// ('' none), one button $ok (k_icon_check); it and RETURN close it, then $act (null none). Tone $tone: err - the
// icon circle-x (r6 D3), warn - triangle-alert (r6 D2); null: err with a reason, warn without.
/**
 * @param string $ok
 * @param string $title
 * @param string $text
 * @param MelAction|null $act
 * @param string|null $tone
 * @return array<string, mixed>
 */
function mel_dlg_error_desc($ok, $title, $text = '', $act = null, $tone = null)
{
    if ($tone === null)
        $tone = $text !== '' ? 'err' : 'warn';
    return array('icon' => $tone === 'err' ? 'k_icon_circle-x' : 'k_icon_triangle-alert',
        'tone' => $tone, 'title' => $title, 'text' => $text,
        'buttons' => array(array('label' => $ok, 'icon' => 'k_icon_check', 'act' => $act)), 'cancel' => $act);
}

// The simple dialog of an error (mel_dlg_error_desc) open over the screen of record $scr (mel_error of a screen of
// ours).
/**
 * @param array<string, mixed> $scr
 * @param string $ok
 * @param string $title
 * @param string $text
 * @param MelAction|null $act
 * @param string|null $tone
 * @return MelAction|null
 */
function mel_dlg_error($scr, $ok, $title, $text = '', $act = null, $tone = null)
{
    return mel_dlg_open($scr, mel_dlg_error_desc($ok, $title, $text, $act, $tone));
}

// The timer of a wait and its map: the keys (wt_key) and the tick (wt_tick, show_dialog_delay MEL_WAIT_DELAY) of the
// screen of owner $owner.
/**
 * @param string $owner
 * @return array<string, MelAction>
 */
function mel_wait_map($owner)
{
    $map = mel_dlg_map('wt_key', $owner);
    $map[GUI_EVENT_TIMER] = mel_input('wt_tick', array('o' => $owner), MEL_WAIT_DELAY);
    return $map;
}

// The timer of the ticks of a wait, every $ms.
/**
 * @param int $ms
 * @return array<string, int>
 */
function mel_wait_timer($ms = MEL_WAIT_TICK_MS)
{
    return array(GuiTimerDef::delay_ms => $ms);
}

// The state of a new wait of description $w (MelWaitDesc, see above), its ticks every ms of it, with none every $ms
// (the overlay MEL_WAIT_TICK_MS; the wait screen of kit_scr.php MEL_KS_TICK_MS), started now. No function tick: null,
// logged.
/**
 * @param array<string, mixed> $w
 * @param int $ms
 * @return MelWait|null
 */
function mel_wait_new($w, $ms = MEL_WAIT_TICK_MS)
{
    $tick = mel_dlg_str($w, 'tick');
    if ($tick === '' || !function_exists($tick))
    {
        mel_log("kit wait: no function tick '$tick', not opened");
        return null;
    }
    $bs = mel_dlg_buttons(isset($w['buttons']) ? $w['buttons'] : null, 'wait');
    $late = isset($w['late']) && is_array($w['late']) ? $w['late'] : null;
    $foot = !empty($w['foot']) || mel_dlg_str($w, 'foot_l') !== '' || mel_dlg_str($w, 'foot_r') !== '';
    return array('icon' => mel_dlg_str($w, 'icon'), 'tone' => mel_dlg_str($w, 'tone'), 'title' => mel_dlg_str($w, 'title'),
        'facts' => mel_dlg_str($w, 'facts'), 'stage' => mel_dlg_str($w, 'stage'), 'count' => mel_dlg_str($w, 'count'),
        'share' => isset($w['share']) && (is_int($w['share']) || is_float($w['share'])) ? (float) $w['share'] : null,
        'foot' => $foot, 'foot_l' => mel_dlg_str($w, 'foot_l'), 'foot_r' => mel_dlg_str($w, 'foot_r'),
        'foot_tone' => mel_dlg_str($w, 'foot_tone'),
        'elapsed' => mel_dlg_str($w, 'elapsed'), 'buttons' => $bs,
        'cur' => mel_dlg_focus(isset($w['focus']) ? $w['focus'] : 0, count($bs)), 'cancel' => mel_dlg_act($w, 'cancel'),
        'tick' => $tick,
        'data' => isset($w['data']) ? $w['data'] : null, 't0' => time(), 'sec' => 0,
        'ms' => isset($w['ms']) && is_int($w['ms']) && $w['ms'] > 0 ? $w['ms'] : $ms, 'late' => $late,
        'late_at' => $late && isset($late['sec']) && is_int($late['sec']) ? $late['sec'] : MEL_WAIT_LATE,
        'late_text' => null);
}

// Open wait $w (MelWaitDesc, see above) over the screen of record $scr (MelOvlScreen): the shade and the window the
// children of ovl, the map of its keys and its tick, its timer. Not a record, or no function tick: null, logged.
/**
 * @param array<string, mixed> $scr
 * @param array<string, mixed> $w
 * @return MelAction|null
 */
function mel_wait_open($scr, $w)
{
    $s = mel_wait_new($w);
    if ($s === null)
        return null;
    $owner = isset($scr['owner']) && is_string($scr['owner']) ? $scr['owner'] : '';
    $a = mel_ovl_open($scr, array('wt' => $s), mel_wait_map($owner), mel_wait_defs($s), null, mel_wait_timer($s['ms']));
    if ($a)
        mel_log('kit wait: open «' . $s['title'] . "», tick {$s['tick']}");
    return $a;
}

// Wait $s past its late time: the icon, the tone, the buttons and the focus of late, its text (of the wait screen).
/**
 * @param MelWait $s
 * @return MelWait
 */
function mel_wait_late($s)
{
    $l = $s['late'];
    $s['late'] = null;
    if ($l === null)
        return $s;
    mel_log("kit wait: late, {$s['sec']} s");
    if (isset($l['text']) && is_string($l['text']))
        $s['late_text'] = $l['text'];
    foreach (array('icon', 'tone') as $k)
    {
        if (isset($l[$k]) && is_string($l[$k]))
            $s[$k] = $l[$k];
    }
    if (isset($l['buttons']))
    {
        $id = isset($s['buttons'][$s['cur']]) ? $s['buttons'][$s['cur']]['id'] : '';
        $s['buttons'] = mel_dlg_buttons($l['buttons'], 'wait');
        $s['cur'] = 0;
        foreach ($s['buttons'] as $i => $b)
        {
            if ($b['id'] === $id)
                $s['cur'] = $i;
        }
    }
    if (isset($l['focus']))
        $s['cur'] = mel_dlg_focus($l['focus'], count($s['buttons']));
    return $s;
}

// One tick of wait $s: the function of the screen called with its data and the seconds since the open -> array(the
// wait after it, what it asks): array('post' => MelAction), done; array('dialog' => MelDlgDesc), failed (the wait as it
// was but its seconds); null, still at work: its data, stage, count, share and foot taken (see above), at late_at s
// the late ones (mel_wait_late) unless the reply holds them (late false). The seconds of the wait (its time, late) are counted after the function: it may take its
// time (a request, a sleep). The caller does the rest: the overlay (mel_wait_tick), the wait screen (kit_scr.php).
/**
 * @param MelWait $s
 * @return array{0: MelWait, 1: array{post?: MelAction, dialog?: array<string, mixed>}|null}
 */
function mel_wait_step($s)
{
    // Checked at the open (mel_wait_new): is_callable for the type.
    $f = $s['tick'];
    $r = is_callable($f) ? call_user_func($f, $s['data'], max(0, time() - $s['t0'])) : null;
    $s['sec'] = max(0, time() - $s['t0']);
    if (is_array($r) && isset($r['post']) && is_array($r['post']))
        return array($s, array('post' => $r['post']));
    if (is_array($r) && isset($r['dialog']) && is_array($r['dialog']))
        return array($s, array('dialog' => $r['dialog']));
    if (!is_array($r))
    {
        mel_log('kit wait: the tick gave no reply, the window as it was');
        $r = array();
    }
    if (array_key_exists('data', $r))
        $s['data'] = $r['data'];
    foreach (array('stage', 'count', 'foot_l', 'foot_r', 'foot_tone') as $k)
    {
        if (isset($r[$k]) && is_string($r[$k]))
            $s[$k] = $r[$k];
    }
    if (array_key_exists('share', $r))
        $s['share'] = is_int($r['share']) || is_float($r['share']) ? (float) $r['share'] : null;
    if ($s['late'] !== null && $s['sec'] >= $s['late_at'] && !(isset($r['late']) && $r['late'] === false))
        $s = mel_wait_late($s);
    return array($s, null);
}

// A tick of the open wait (MelOvl::$st['wt']): the function of the screen, then its reply (see above).
/**
 * @return MelAction|null
 */
function mel_wait_tick()
{
    $st = mel_ovl_st();
    if (!$st || !isset($st['wt']))
        return null;
    $was = $st['wt'];
    list($s, $r) = mel_wait_step($was);
    if (isset($r['post']))
    {
        mel_log("kit wait: done, {$s['sec']} s");
        return mel_ovl_close($r['post']);
    }
    if (isset($r['dialog']))
    {
        mel_log("kit wait: failed, {$s['sec']} s");
        $a = mel_dlg_open($st['scr'], $r['dialog']);
        return $a ? $a : mel_ovl_close();
    }
    $st['wt'] = $s;
    mel_ovl_set($st);
    return mel_wait_again(mel_dlg_diff(mel_wait_defs($was), mel_wait_defs($s), MEL_WT . '_win'));
}

// The reply of an open wait that goes on: its map and its timer again, then changes $ch (none: no change_gcomps). No
// wait open: null.
/**
 * @param list<MelGcChange> $ch
 * @return MelAction|null
 */
function mel_wait_again($ch)
{
    $st = mel_ovl_st();
    if (!$st || !isset($st['wt']))
        return null;
    return mel_ovl_behaviour(mel_wait_map($st['scr']['owner']), mel_wait_timer($st['wt']['ms']),
        $ch ? mel_ovl_change($ch, $st['scr']['sel']) : null);
}

// The components of the open dialog or wait $kind (dlg, wt); none open: none.
/**
 * @param string $kind
 * @return list<MelGcComp>
 */
function mel_dlg_now($kind)
{
    $st = mel_ovl_st();
    if ($kind === 'wt')
        return isset($st['wt']) ? mel_wait_defs($st['wt']) : array();
    return isset($st['dlg']) ? mel_dlg_defs($st['dlg']) : array();
}

// Key $k of open dialog or wait $kind (dlg, wt) -> the reply, null for none (mel_dlg_press): the focus on another
// button, the children of the two; the end by a button (ENTER, RETURN on the cancel button of a wait): the close, then
// its act - of a wait the button and its data kept for the screen (wt_done, mel_wt_done); by RETURN: the close, then
// cancel. A key of a wait that goes on (none of use too): its map and its timer
// again (mel_wait_again).
/**
 * @param string $kind
 * @param string $k
 * @return MelAction|null
 */
function mel_dlg_key($kind, $k)
{
    $st = mel_ovl_st();
    $wait = $kind === 'wt';
    if ($wait && isset($st['wt']))
        $s = $st['wt'];
    elseif (!$wait && isset($st['dlg']))
        $s = $st['dlg'];
    else
        return null;
    $r = mel_dlg_press($s, $k, $wait);
    if ($r === null)
        return $wait ? mel_wait_again(array()) : null;
    if (isset($r['cur']))
    {
        $was = mel_dlg_now($kind);
        if ($wait)
            $st['wt']['cur'] = $r['cur'];
        else
            $st['dlg']['cur'] = $r['cur'];
        mel_ovl_set($st);
        $ch = mel_dlg_diff($was, mel_dlg_now($kind), "{$kind}_win");
        return $wait ? mel_wait_again($ch) : mel_ovl_change($ch, $st['scr']['sel']);
    }
    $b = $r['button'];
    mel_log('kit ' . ($wait ? 'wait' : 'dialog') . ": $k" . ($b !== null ? " on {$b['id']}" : '') . ', closed');
    if ($b === null)
        return mel_ovl_close($s['cancel']);
    return mel_ovl_close($b['act'], $wait ? array('wt_done' => array('id' => $b['id'], 'data' => $st['wt']['data'])) :
        array());
}

// The button that closed the wait last on the screen of owner $owner and the data of the wait as its last tick left it
// (MelWaitDone: id, data); the state of the owner in hand from now (as mel_kp_done). Given once. None (another overlay
// of this screen since, the wait ended by its tick or by RETURN with no cancel button): null.
/**
 * @param string $owner
 * @return MelWaitDone|null
 */
function mel_wt_done($owner)
{
    $st = mel_ovl_take($owner) ? mel_ovl_st() : null;
    if (!$st || !isset($st['wt_done']))
        return null;
    $d = $st['wt_done'];
    unset($st['wt_done']);
    mel_ovl_set($st);
    return $d;
}

// dlg_key, wt_key, wt_tick: a key of an open dialog or wait, a tick of a wait. A stale screen: close_and_run
// (mel_ovl_stale). A live one with no such window (a key or a tick that came after the close, before its map was
// applied): none.
/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_dlg_input($in, $control)
{
    $a = mel_ovl_stale($in);
    if ($a)
        return $a;
    $kind = $control === 'dlg_key' ? 'dlg' : 'wt';
    if (!mel_ovl_shown($kind))
    {
        mel_log("kit $kind: $control after the close, none");
        return null;
    }
    if ($control === 'wt_tick')
        return mel_wait_tick();
    return mel_dlg_key($kind, isset($in->k) && is_string($in->k) ? $in->k : '');
}
