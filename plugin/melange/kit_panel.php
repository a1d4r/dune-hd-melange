<?php
// The side panel on the right of a GComps screen (UI kit, stage 6a): its keys, its state and the changes that open it,
// move its cursor, check its lines, move a line («Переместить»), scroll it, put a submenu in its place and close it.
// The view is the builders of kit.php (MelKit::panel and its lines); here only the mechanics, the same on every
// screen. Required by main.php after kit.php. PHP 5.3.6, firmware API only; types (MelKp, MelKpDesc): phpstan-types.neon.
//
// The screen puts the empty panel mel_kp_slot() last in its comp_defs and opens a panel by mel_kp_open: the shade and
// the panel become its children, and the action map of the screen gives way to that of the panel (change_behaviour:
// UP, DOWN, LEFT, RIGHT, ENTER, RETURN to kp_key). RETURN of an open panel comes to PHP; at the close the map of the
// screen comes back without RETURN, and the shell leaves the screen by RETURN itself [device, gcomps_overlay_probe P3].
// Its description (MelKpDesc), one of:
//   a menu   title, sub (optional), dim ('dim', the default, or 'dim_strong'), note (optional, under the lines), groups:
//            lists of items, an item array('id', 'label', 'icon', 'detail', 'value', 'picked', 'way', and 'menu' - the
//            description of a submenu, it takes the place of the panel - or 'act' - an action run after the close:
//            plugin_open_folder of a screen, a handle_user_input of the screen);
//   a check list  title, sub, dim, all (the label of the line «all», '' none), items: array('id', 'label', 'count',
//            'on'), move (the label of the hint «› Переместить»: RIGHT takes a line; '' none), done: array('label',
//            'count' - «Выбрано: {n} из {m}», 'act' - run after the close with the choice, see mel_kp_done).
// The state, Mel::$state['kp'] (MelKp) beside the cursor of the list: a new rid (mel_list_replace) has none, and the
// view of a screen draws no panel. map, sel: the map and the sel_state of the screen at the open; stack: the panels,
// the first the top one, the last the one on screen (a submenu): desc, its description; cur, the cursor (a line of
// mel_kp_lines); top, the first line of the list shown; on, order: of a check list, id => checked and the ids in
// their order; move: a line taken.

// The prefix of the ids of the panel (MelKit::panel): kp_dim, kp_pnl, kp_rows, …
define('MEL_KP', 'kp');

// The last component of the comp_defs of a screen with a panel: the empty full-screen panel the panel opens in.
/**
 * @return MelGcComp
 */
function mel_kp_slot()
{
    return mel_gc_panel(MEL_KP . '_ovl', mel_gc_at(1920, 1080, 0, 0), array());
}

// The id of the group of the line of item $id: by the key of the item, not its place (a moved line keeps it).
/**
 * @param string $id
 * @return string
 */
function mel_kp_rid($id)
{
    return MEL_KP . '_r' . substr(md5($id), 0, 10);
}

// The focusable lines of frame $f in their order, each array(kind, key, g, j): kind item (of a menu), all, check (a line
// of a check list), done; key the id of its item ('' for all and done); g its group in the view, j its place in the
// last group (the list; -1 out of it).
/**
 * @param MelKpFrame $f
 * @return list<array{string, string, int, int}>
 */
function mel_kp_lines($f)
{
    $d = $f['desc'];
    $out = array();
    if (!isset($d['items']))
    {
        $groups = isset($d['groups']) ? $d['groups'] : array();
        $last = count($groups) - 1;
        foreach ($groups as $g => $items)
        {
            foreach ($items as $j => $it)
                $out[] = array('item', (string) $it['id'], $g, $g === $last ? $j : -1);
        }
        return $out;
    }
    $g = 0;
    if (isset($d['all']) && $d['all'] !== '')
    {
        $out[] = array('all', '', 0, -1);
        $g = 1;
    }
    foreach ($f['order'] as $j => $id)
        $out[] = array('check', $id, $g, $j);
    $out[] = array('done', '', -1, -1);
    return $out;
}

// The item $key of a menu frame, null none.
/**
 * @param MelKpFrame $f
 * @param string $key
 * @return array<string, mixed>|null
 */
function mel_kp_item($f, $key)
{
    foreach (isset($f['desc']['groups']) ? $f['desc']['groups'] : array() as $items)
    {
        foreach ($items as $it)
        {
            if ((string) $it['id'] === $key)
                return $it;
        }
    }
    return null;
}

// A frame of description $d: the cursor on its first picked item (a submenu: the current sort), else on the first line;
// of a check list the checks and the order of its items.
/**
 * @param MelKpDesc $d
 * @return MelKpFrame
 */
function mel_kp_frame($d)
{
    $f = array('desc' => $d, 'cur' => 0, 'top' => 0, 'on' => array(), 'order' => array(), 'move' => false);
    if (isset($d['items']))
    {
        foreach ($d['items'] as $it)
        {
            $f['order'][] = (string) $it['id'];
            $f['on'][(string) $it['id']] = !empty($it['on']);
        }
        return $f;
    }
    foreach (mel_kp_lines($f) as $k => $l)
    {
        $it = mel_kp_item($f, $l[1]);
        if ($it && !empty($it['picked']))
        {
            $f['cur'] = $k;
            break;
        }
    }
    return mel_kp_window($f);
}

// Frame $f with its list scrolled so that the cursor, when on the list, is shown.
/**
 * @param MelKpFrame $f
 * @return MelKpFrame
 */
function mel_kp_window($f)
{
    $lines = mel_kp_lines($f);
    $j = isset($lines[$f['cur']]) ? $lines[$f['cur']][3] : -1;
    if ($j >= 0 && $j < $f['top'])
        $f['top'] = $j;
    elseif ($j >= $f['top'] + MEL_K_PANEL_VIS)
        $f['top'] = $j - MEL_K_PANEL_VIS + 1;
    return $f;
}

// The view of frame $f for MelKit::panel, the panel at $x.
/**
 * @param MelKpFrame $f
 * @param bool $back
 * @param int $x
 * @return array{id: string, title: string, back: bool, sub: string, dim: string, x: int, groups: list<list<MelKitPanelRow>>, top: int, hint: string, done: array{label: string, count: string, state: string}|null, note: string}
 */
function mel_kp_view($f, $back, $x)
{
    $d = $f['desc'];
    $v = array('id' => MEL_KP, 'title' => (string) $d['title'], 'back' => $back,
        'sub' => isset($d['sub']) ? (string) $d['sub'] : '', 'dim' => isset($d['dim']) ? (string) $d['dim'] : 'dim',
        'x' => $x, 'groups' => array(), 'top' => $f['top'], 'hint' => '', 'done' => null,
        'note' => isset($d['note']) ? (string) $d['note'] : '');
    $lines = mel_kp_lines($f);
    $cur = $f['cur'];
    foreach ($lines as $k => $l)
    {
        list($kind, $key, $g) = $l;
        $state = $k === $cur ? ($f['move'] ? 'move' : 'focus') : '';
        if ($kind === 'item')
        {
            $it = mel_kp_item($f, $key);
            $r = array('id' => mel_kp_rid($key), 'label' => (string) $it['label'], 'state' => $state);
            foreach (array('icon', 'detail', 'value', 'way') as $o)
            {
                if (isset($it[$o]))
                    $r[$o] = (string) $it[$o];
            }
            $r['picked'] = !empty($it['picked']);
            $r['chevron'] = isset($it['menu']);
            $v['groups'][$g][] = $r;
        }
        elseif ($kind === 'all')
            $v['groups'][$g][] = array('id' => MEL_KP . '_all', 'label' => (string) $d['all'], 'state' => $state,
                'check' => !in_array(false, $f['on'], true));
        elseif ($kind === 'check')
        {
            $it = mel_kp_check_item($d, $key);
            $v['groups'][$g][] = array('id' => mel_kp_rid($key), 'label' => (string) $it['label'], 'state' => $state,
                'check' => $f['on'][$key], 'count' => isset($it['count']) ? (string) $it['count'] : '');
        }
        else
        {
            $n = count(array_filter($f['on']));
            $v['done'] = array('label' => (string) $d['done']['label'], 'state' => $state,
                'count' => str_replace(array('{n}', '{m}'), array(strval($n), strval(count($f['on']))),
                    isset($d['done']['count']) ? (string) $d['done']['count'] : ''));
        }
    }
    // An empty group (no items): no list.
    $v['groups'] = array_values($v['groups']);
    if (isset($d['items']) && isset($d['move']) && $d['move'] !== '')
        $v['hint'] = (string) $d['move'];
    return $v;
}

// The item $key of a check list.
/**
 * @param MelKpDesc $d
 * @param string $key
 * @return array<string, mixed>
 */
function mel_kp_check_item($d, $key)
{
    foreach ($d['items'] as $it)
    {
        if ((string) $it['id'] === $key)
            return $it;
    }
    return array('label' => '');
}

// The children of the panel kp_pnl of state $kp, its panel on screen (the last of the stack), the panel at $x.
/**
 * @param MelKp $kp
 * @param int|null $x
 * @return list<MelGcComp>
 */
function mel_kp_defs($kp, $x = null)
{
    $f = $kp['stack'][count($kp['stack']) - 1];
    $p = MelKit::panel(mel_kp_view($f, count($kp['stack']) > 1, $x === null ? 1920 - MEL_K_PANEL_W : $x));
    return $p['defs'];
}

// The components of $defs with an id, by id, into the children of panels.
/**
 * @param list<MelGcComp> $defs
 * @param array<string, MelGcComp> $out
 * @return array<string, MelGcComp>
 */
function mel_kp_index($defs, $out = array())
{
    foreach ($defs as $d)
    {
        if (isset($d[GComponentDef::id]))
            $out[(string) $d[GComponentDef::id]] = $d;
        if ($d[GComponentDef::kind] === GCOMPONENT_PANEL)
            $out = mel_kp_index($d[GComponentDef::specific_def][GCompPanelDef::children], $out);
    }
    return $out;
}

// The changes from panel $old to panel $new of the same lines, one a component: of a line or of «Готово» its children
// when they differ (the focus, a check, the count), of a line, of the list kp_rows and of the thumb their geometry when
// it differs (a line moved, the list scrolled).
/**
 * @param list<MelGcComp> $old
 * @param list<MelGcComp> $new
 * @return list<MelGcChange>
 */
function mel_kp_diff($old, $new)
{
    $a = mel_kp_index($old);
    $ch = array();
    foreach (mel_kp_index($new) as $id => $d)
    {
        if (!isset($a[$id]) || in_array($id, array(MEL_KP . '_dim', MEL_KP . '_pnl', MEL_KP . '_list'), true))
            continue;
        $o = $a[$id];
        $geom = $o[GComponentDef::geom_def] !== $d[GComponentDef::geom_def] ? $d[GComponentDef::geom_def] : null;
        $kids = null;
        if ($id !== MEL_KP . '_rows' && $d[GComponentDef::kind] === GCOMPONENT_PANEL &&
            $o[GComponentDef::specific_def] !== $d[GComponentDef::specific_def])
            $kids = $d[GComponentDef::specific_def][GCompPanelDef::children];
        if ($geom !== null || $kids !== null)
            $ch[] = mel_gc_change($id, $geom, $geom !== null ? GCOMP_TRANSITION_DEFAULT : GCOMP_TRANSITION_NONE, $kids);
    }
    return $ch;
}

// change_gcomps of changes $ch with the sel_state of the screen $sel, then $post.
/**
 * @param list<MelGcChange|array<string, mixed>> $ch
 * @param string|null $sel
 * @param MelAction|null $post
 * @return MelAction
 */
function mel_kp_change($ch, $sel, $post = null)
{
    $data = array(ChangeGCompsActionData::change_defs => $ch, ChangeGCompsActionData::num_steps => MEL_GC_STEPS);
    if ($sel !== null)
        $data[ChangeGCompsActionData::sel_state] = $sel;
    if ($post !== null)
        $data[ChangeGCompsActionData::post_action] = $post;
    return array(GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID, GuiAction::data => $data);
}

// change_behaviour: the action map $map, no timer, then $post.
/**
 * @param array<string, MelAction> $map
 * @param MelAction $post
 * @return MelAction
 */
function mel_kp_behaviour($map, $post)
{
    return array(GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID, GuiAction::data => array(
        ChangeBehaviourActionData::actions => $map,
        ChangeBehaviourActionData::timer => null,
        ChangeBehaviourActionData::post_action => $post));
}

// The action map of an open panel of the list $rid: its keys to kp_key, the others none (the list under the panel
// does not move).
/**
 * @param string $rid
 * @return array<string, MelAction>
 */
function mel_kp_map($rid)
{
    $map = array();
    foreach (array(GUI_EVENT_KEY_UP => 'up', GUI_EVENT_KEY_DOWN => 'down', GUI_EVENT_KEY_LEFT => 'left',
        GUI_EVENT_KEY_RIGHT => 'right', GUI_EVENT_KEY_ENTER => 'enter', GUI_EVENT_KEY_RETURN => 'return') as $ev => $k)
        $map[$ev] = mel_gc_act('kp_key', $rid, array('k' => $k));
    return $map;
}

// Open panel $d over the screen of the live list (Mel::$state) whose action map is $map (without RETURN) and sel_state
// $sel: the shade and the panel the children of kp_ovl, the panel off the screen on the right and then in its place
// (post_action: it slides in, if the shell animates it), the map of the panel. No live list: null, logged.
/**
 * @param MelKpDesc $d
 * @param array<string, MelAction> $map
 * @param string|null $sel
 * @return MelAction|null
 */
function mel_kp_open($d, $map, $sel = null)
{
    if (!Mel::$state)
    {
        mel_log('kit panel: no list, not opened');
        return null;
    }
    $kp = array('map' => $map, 'sel' => $sel, 'stack' => array(mel_kp_frame($d)));
    Mel::$state['kp'] = $kp;
    mel_log('kit panel: open «' . $d['title'] . '»');
    $slide = mel_kp_change(array(mel_gc_change(MEL_KP . '_pnl', mel_gc_at(MEL_K_PANEL_W, 1080, 1920 - MEL_K_PANEL_W, 0),
        GCOMP_TRANSITION_DEFAULT)), $sel);
    return mel_kp_behaviour(mel_kp_map(Mel::$state['rid']), mel_kp_change(array(
        mel_gc_change(MEL_KP . '_ovl', null, GCOMP_TRANSITION_NONE, mel_kp_defs($kp, 1920))), $sel, $slide));
}

// The close of the open panel, then $post: the shade and the panel removed, the map of the screen back (without
// RETURN), no state.
/**
 * @param MelAction|null $post
 * @return MelAction
 */
function mel_kp_close($post = null)
{
    $kp = Mel::$state['kp'];
    unset(Mel::$state['kp']);
    return mel_kp_behaviour($kp['map'], mel_kp_change(array(
        array(ChangeGCompDef::id => MEL_KP . '_dim', ChangeGCompDef::remove => true),
        array(ChangeGCompDef::id => MEL_KP . '_pnl', ChangeGCompDef::remove => true)), $kp['sel'], $post));
}

// The choice of a check list given to the action of its «Готово» (a handle_user_input: its params kp_on, kp_order),
// back by mel_kp_done.
/**
 * @param MelAction $act
 * @param MelKpFrame $f
 * @return MelAction
 */
function mel_kp_done_act($act, $f)
{
    if (!isset($act[GuiAction::params]) || !is_array($act[GuiAction::params]))
        return $act;
    $on = array();
    foreach ($f['order'] as $id)
    {
        if ($f['on'][$id])
            $on[] = $id;
    }
    $act[GuiAction::params]['kp_on'] = json_encode($on);
    $act[GuiAction::params]['kp_order'] = json_encode($f['order']);
    return $act;
}

// The choice of «Готово» of a check list in input $in (see mel_kp_done_act): array('on' => the ids checked, 'order' =>
// all the ids, both in their order); null none.
/**
 * @param stdClass $in
 * @return array{on: list<string>, order: list<string>}|null
 */
function mel_kp_done($in)
{
    $out = array();
    foreach (array('on' => 'kp_on', 'order' => 'kp_order') as $k => $p)
    {
        $v = isset($in->$p) && is_string($in->$p) ? json_decode($in->$p, true) : null;
        if (!is_array($v))
            return null;
        $out[$k] = array_values(array_map('strval', $v));
    }
    return $out;
}

// kp_key: a key of an open panel. Not the live list, or no panel open (a php_server restart): the screen is stale,
// close_and_run leaves it. Else see mel_kp_key.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_kp_input($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !isset($st['kp']))
    {
        mel_log('kit panel: key of a stale screen, closed');
        return array(GuiAction::handler_string_id => CLOSE_AND_RUN_ACTION_ID,
            GuiAction::data => array(CloseAndRunActionData::post_action => null));
    }
    return mel_kp_key(isset($in->k) && is_string($in->k) ? $in->k : '');
}

// Key $k of the open panel (Mel::$state['kp']) -> the reply, null for none (an edge, a key of no use there):
//   up, down    the cursor a line, to the first and the last (no wrap, as the list); the list scrolls with it; a line
//               taken moves with it among the lines of the list (the geometry of two lines);
//   enter       a menu item: its submenu in the place of the panel, else its action after the close; a check line or
//               «all»: its check (and «all» and the count); «Готово»: the close, then its action with the choice; a
//               line taken: put;
//   right       an item with a submenu: as enter; a check line of a list with move: taken (⇅); a line taken: put;
//   left        a submenu: back, as return;
//   return      a line taken: put; a submenu: back to its panel on the same item; else the close (a check list: the
//               checks not given).
/**
 * @param string $k
 * @return MelAction|null
 */
function mel_kp_key($k)
{
    $kp = Mel::$state['kp'];
    $n = count($kp['stack']) - 1;
    $f = $kp['stack'][$n];
    $lines = mel_kp_lines($f);
    $l = $lines[$f['cur']];
    $old = mel_kp_defs($kp);
    if ($f['move'] && ($k === 'enter' || $k === 'right' || $k === 'return'))
        $f['move'] = false;
    elseif ($k === 'up' || $k === 'down')
    {
        $to = $f['cur'] + ($k === 'up' ? -1 : 1);
        if ($f['move'])
        {
            $j = $l[3] + ($k === 'up' ? -1 : 1);
            if ($j < 0 || $j >= count($f['order']))
                return null;
            $f['order'][$l[3]] = $f['order'][$j];
            $f['order'][$j] = $l[1];
        }
        elseif ($to < 0 || $to >= count($lines))
            return null;
        $f['cur'] = $to;
        $f = mel_kp_window($f);
    }
    elseif ($k === 'return' || $k === 'left')
    {
        if ($n === 0)
            return $k === 'return' ? mel_kp_close() : null;
        array_pop($kp['stack']);
        Mel::$state['kp'] = $kp;
        return mel_kp_replace($kp);
    }
    elseif ($l[0] === 'item' && ($k === 'enter' || $k === 'right'))
    {
        $it = mel_kp_item($f, $l[1]);
        if (isset($it['menu']))
        {
            $kp['stack'][] = mel_kp_frame($it['menu']);
            Mel::$state['kp'] = $kp;
            return mel_kp_replace($kp);
        }
        if ($k === 'right')
            return null;
        if (!isset($it['act']))
        {
            mel_log("kit panel: item {$l[1]} has no action");
            return null;
        }
        mel_log("kit panel: item {$l[1]}, closed");
        return mel_kp_close($it['act']);
    }
    elseif ($k === 'enter' && $l[0] === 'done')
    {
        mel_log('kit panel: done, ' . count(array_filter($f['on'])) . ' of ' . count($f['on']));
        return mel_kp_close(mel_kp_done_act($f['desc']['done']['act'], $f));
    }
    elseif ($k === 'enter' && $l[0] === 'all')
        $f['on'] = array_fill_keys(array_keys($f['on']), in_array(false, $f['on'], true));
    elseif ($k === 'enter' && $l[0] === 'check')
        $f['on'][$l[1]] = !$f['on'][$l[1]];
    elseif ($k === 'right' && $l[0] === 'check' && isset($f['desc']['move']) && $f['desc']['move'] !== '')
        $f['move'] = true;
    else
        return null;
    $kp['stack'][$n] = $f;
    Mel::$state['kp'] = $kp;
    return mel_kp_change(mel_kp_diff($old, mel_kp_defs($kp)), $kp['sel']);
}

// The panel on screen of state $kp (after a submenu in or out) in the place of the one before: the children of kp_pnl.
/**
 * @param MelKp $kp
 * @return MelAction
 */
function mel_kp_replace($kp)
{
    $defs = mel_kp_defs($kp);
    return mel_kp_change(array(mel_gc_change(MEL_KP . '_pnl', null, GCOMP_TRANSITION_NONE,
        $defs[1][GComponentDef::specific_def][GCompPanelDef::children])), $kp['sel']);
}
