<?php
// The side panel on the right of a GComps screen (UI kit, stage 6a): its keys, its state and the changes that open it,
// move its cursor, check its lines, move a line («Переместить»), scroll it, put a submenu in its place and close it.
// The view is the builders of kit.php (MelKit::panel and its lines); the open, the close and the stale screen are those
// of the overlay (kit_ovl.php), the panel is one of what it shows. Required by main.php after kit_ovl.php. PHP 5.3.6,
// firmware API only; types (MelKp, MelKpDesc, MelKpItem): phpstan-types.neon.
//
// The screen puts mel_ovl_slot() last in its comp_defs and opens a panel by mel_kp_open with the record of itself
// (MelOvlScreen, kit_ovl.php): the shade and the panel become the children of ovl, the map of the panel (UP, DOWN,
// LEFT, RIGHT, ENTER, RETURN to kp_key with the owner) takes the place of that of the screen. Its description
// (MelKpDesc), one of:
//   a menu   title, sub (optional), dim ('dim', the default, or 'dim_strong'), note (optional, under the lines), groups:
//            lists of items (MelKpItem), an item array('id', 'label', 'icon', 'detail', 'value', 'picked', 'way', and
//            'menu' - the description of a submenu, it takes the place of the panel - or 'act' - an action run after
//            the close: plugin_open_folder of a screen, a handle_user_input of the screen);
//   a check list  title, sub, dim, all (the label of the line «all», '' none), items: array('id', 'label', 'count',
//            'on'), move (the label of the hint «› Переместить»: RIGHT takes a line; '' none), done: array('label',
//            'count' - «Выбрано: {n} из {m}», 'act' - run after the close, its choice by mel_kp_done).
// An item whose id is not a string, or the id of one before, is left out (logged); an empty group too.
// The state, MelOvl::$st['kp'] (MelKp): stack, the panels, the first the top one, the last the one on screen (a
// submenu), each (MelKpFrame): desc, its description; its, its items by id; groups, the ids of a menu by group; cur,
// the cursor (a line of mel_kp_lines); top, the first line of the list shown; on, order: of a check list, id =>
// checked and the ids in their order; move: a line taken.

// The prefix of the ids of the panel (MelKit::panel): kp_dim, kp_pnl, kp_rows, …
define('MEL_KP', 'kp');

// The id of the group of the line of item $id: by the key of the item, not its place (a moved line keeps it).
/**
 * @param string $id
 * @return string
 */
function mel_kp_row_id($id)
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
    $out = array();
    if (!isset($f['desc']['items']))
    {
        $last = count($f['groups']) - 1;
        foreach ($f['groups'] as $g => $ids)
        {
            foreach ($ids as $j => $id)
                $out[] = array('item', $id, $g, $g === $last ? $j : -1);
        }
        return $out;
    }
    $g = 0;
    if (isset($f['desc']['all']) && $f['desc']['all'] !== '')
    {
        $out[] = array('all', '', 0, -1);
        $g = 1;
    }
    foreach ($f['order'] as $j => $id)
        $out[] = array('check', $id, $g, $j);
    $out[] = array('done', '', -1, -1);
    return $out;
}

// The id of item $it of a description: a string, else null.
/**
 * @param array<string, mixed> $it
 * @return string|null
 */
function mel_kp_id($it)
{
    return isset($it['id']) && is_string($it['id']) ? $it['id'] : null;
}

// A frame of description $d: its items by id; the cursor on its first picked item (a submenu: the current sort), else
// on the first line; of a check list the checks and the order of its items.
/**
 * @param MelKpDesc $d
 * @return MelKpFrame
 */
function mel_kp_frame($d)
{
    $f = array('desc' => $d, 'its' => array(), 'groups' => array(), 'cur' => 0, 'top' => 0, 'on' => array(),
        'order' => array(), 'move' => false);
    $check = isset($d['items']);
    foreach ($check ? array($d['items']) : (isset($d['groups']) ? $d['groups'] : array()) as $items)
    {
        $ids = array();
        foreach ($items as $it)
        {
            $id = mel_kp_id($it);
            if ($id === null || isset($f['its'][$id]))
            {
                mel_log('kit panel: «' . $d['title'] . '»: an item with no id of its own, left out');
                continue;
            }
            $f['its'][$id] = $it;
            $ids[] = $id;
            if ($check)
                $f['on'][$id] = !empty($it['on']);
        }
        if ($check)
            $f['order'] = $ids;
        elseif ($ids)
            $f['groups'][] = $ids;
    }
    if ($check)
        return $f;
    foreach (mel_kp_lines($f) as $k => $l)
    {
        if (!empty($f['its'][$l[1]]['picked']))
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

// The view of line $l of frame $f in state $st ('', focus, move) for MelKit::panel_row, with the id of its group; $all:
// are all the lines checked (of the line «all»).
/**
 * @param MelKpFrame $f
 * @param array{string, string, int, int} $l
 * @param string $st
 * @param bool $all
 * @return MelKitPanelRow
 */
function mel_kp_row($f, $l, $st, $all)
{
    if ($l[0] === 'all')
        return array('id' => MEL_KP . '_all', 'label' => (string) $f['desc']['all'], 'state' => $st, 'check' => $all);
    $it = $f['its'][$l[1]];
    $r = array('id' => mel_kp_row_id($l[1]), 'label' => (string) $it['label'], 'state' => $st);
    if ($l[0] === 'check')
        return $r + array('check' => $f['on'][$l[1]], 'count' => isset($it['count']) ? (string) $it['count'] : '');
    foreach (array('icon', 'detail', 'value', 'way') as $o)
    {
        if (isset($it[$o]))
            $r[$o] = (string) $it[$o];
    }
    $r['picked'] = !empty($it['picked']);
    $r['chevron'] = isset($it['menu']);
    return $r;
}

// The view of the line «Готово» of check list $f in state $st for MelKit::panel_done: the count «Выбрано: n из m».
/**
 * @param MelKpFrame $f
 * @param string $st
 * @return array{label: string, count: string, state: string}
 */
function mel_kp_done_view($f, $st)
{
    $d = $f['desc']['done'];
    $n = count(array_filter($f['on']));
    return array('label' => (string) $d['label'], 'state' => $st, 'count' => str_replace(array('{n}', '{m}'),
        array(strval($n), strval(count($f['on']))), isset($d['count']) ? (string) $d['count'] : ''));
}

// The state of line $k of frame $f: focus or move on the cursor, else ''.
/**
 * @param MelKpFrame $f
 * @param int $k
 * @return string
 */
function mel_kp_state($f, $k)
{
    return $k === $f['cur'] ? ($f['move'] ? 'move' : 'focus') : '';
}

// The view of frame $f for MelKit::panel, the panel at $x.
/**
 * @param MelKpFrame $f
 * @param bool $back
 * @param int $x
 * @return MelKitPanel
 */
function mel_kp_view($f, $back, $x)
{
    $d = $f['desc'];
    $v = array('id' => MEL_KP, 'title' => (string) $d['title'], 'back' => $back,
        'sub' => isset($d['sub']) ? (string) $d['sub'] : '', 'dim' => isset($d['dim']) ? (string) $d['dim'] : 'dim',
        'x' => $x, 'groups' => array(), 'top' => $f['top'], 'hint' => '', 'done' => null,
        'note' => isset($d['note']) ? (string) $d['note'] : '');
    $all = !in_array(false, $f['on'], true);
    foreach (mel_kp_lines($f) as $k => $l)
    {
        if ($l[0] === 'done')
            $v['done'] = mel_kp_done_view($f, mel_kp_state($f, $k));
        else
            $v['groups'][$l[2]][] = mel_kp_row($f, $l, mel_kp_state($f, $k), $all);
    }
    if (isset($d['items']) && isset($d['move']) && $d['move'] !== '')
        $v['hint'] = (string) $d['move'];
    return $v;
}

// The components of state $kp, its panel on screen (the last of the stack) at $x: the shade kp_dim and the panel kp_pnl.
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

// What a key may change of frame $f, by the id of its group: v, the view of a line (MelKitPanelRow, of «Готово» that of
// panel_done); j, its place in the list (-1 out of it). No component is built.
/**
 * @param MelKpFrame $f
 * @return array<string, array{v: array<string, mixed>, j: int}>
 */
function mel_kp_looks($f)
{
    $out = array();
    $all = !in_array(false, $f['on'], true);
    foreach (mel_kp_lines($f) as $k => $l)
    {
        if ($l[0] === 'done')
            $out[MEL_KP . '_done'] = array('v' => mel_kp_done_view($f, mel_kp_state($f, $k)), 'j' => -1);
        else
        {
            $r = mel_kp_row($f, $l, mel_kp_state($f, $k), $all);
            $out[$r['id']] = array('v' => $r, 'j' => $l[3]);
        }
    }
    return $out;
}

// The changes of a key from frame $was to frame $f of the same panel, one a component, only of what differs: the
// children of a line or of «Готово» (the focus, a check, the count) - only those lines are built; the geometry of a
// line of the list (moved), of the list kp_rows and of the thumb (scrolled).
/**
 * @param MelKpFrame $was
 * @param MelKpFrame $f
 * @return list<MelGcChange>
 */
function mel_kp_redraw($was, $f)
{
    $a = mel_kp_looks($was);
    $ch = array();
    $n = 0;
    foreach (mel_kp_looks($f) as $id => $x)
    {
        if ($x['j'] >= 0)
            $n++;
        if (!isset($a[$id]))
            continue;
        $geom = $x['j'] >= 0 && $x['j'] !== $a[$id]['j'] ? mel_gc_at(MEL_K_PANEL_W, MEL_K_STEP_PANEL, 0,
            $x['j'] * MEL_K_STEP_PANEL) : null;
        $kids = null;
        if ($x['v'] !== $a[$id]['v'])
        {
            $v = $x['v'];
            $it = $id === MEL_KP . '_done' ? MelKit::panel_done($id, $v['label'], $v['count'], $v['state'])
                : MelKit::panel_row($id, $v);
            $kids = $it['defs'][0][GComponentDef::specific_def][GCompPanelDef::children];
        }
        if ($geom !== null || $kids !== null)
            $ch[] = mel_gc_change($id, $geom, $geom !== null ? GCOMP_TRANSITION_DEFAULT : GCOMP_TRANSITION_NONE, $kids);
    }
    if ($f['top'] !== $was['top'])
    {
        $s = MelKit::panel_scroll(MEL_KP, $n, $f['top']);
        $ch[] = mel_gc_change(MEL_KP . '_rows', $s['rows'], GCOMP_TRANSITION_DEFAULT);
        if ($s['thumb'] !== null)
            $ch[] = mel_gc_change(MEL_KP . '_thumb', $s['thumb'], GCOMP_TRANSITION_DEFAULT);
    }
    return $ch;
}

// The action map of an open panel of the screen of owner $owner: its keys to kp_key, the others none (the screen under
// the panel does not move).
/**
 * @param string $owner
 * @return array<string, MelAction>
 */
function mel_kp_map($owner)
{
    $map = array();
    foreach (array(GUI_EVENT_KEY_UP => 'up', GUI_EVENT_KEY_DOWN => 'down', GUI_EVENT_KEY_LEFT => 'left',
        GUI_EVENT_KEY_RIGHT => 'right', GUI_EVENT_KEY_ENTER => 'enter', GUI_EVENT_KEY_RETURN => 'return') as $ev => $k)
        $map[$ev] = mel_ovl_input('kp_key', $owner, array('k' => $k));
    return $map;
}

// Open panel $d over the screen of record $scr (MelOvlScreen, checked by mel_ovl_open): the overlay with the shade and
// the panel off the screen on the right, then the panel in its place (post_action: it slides in, if the shell animates
// it). Not a record: null, logged.
/**
 * @param array<string, mixed> $scr
 * @param MelKpDesc $d
 * @return MelAction|null
 */
function mel_kp_open($scr, $d)
{
    $kp = array('stack' => array(mel_kp_frame($d)));
    $owner = isset($scr['owner']) && is_string($scr['owner']) ? $scr['owner'] : '';
    $sel = isset($scr['sel']) && is_string($scr['sel']) ? $scr['sel'] : null;
    $slide = mel_ovl_change(array(mel_gc_change(MEL_KP . '_pnl', mel_gc_at(MEL_K_PANEL_W, 1080, 1920 - MEL_K_PANEL_W, 0),
        GCOMP_TRANSITION_DEFAULT)), $sel);
    $a = mel_ovl_open($scr, array('kp' => $kp), mel_kp_map($owner), mel_kp_defs($kp, 1920), $slide);
    if ($a)
        mel_log('kit panel: open «' . $d['title'] . '»');
    return $a;
}

// The choice of check list $f: on, the ids checked; order, all the ids; both in the order of the lines.
/**
 * @param MelKpFrame $f
 * @return MelKpChoice
 */
function mel_kp_choice($f)
{
    $on = array();
    foreach ($f['order'] as $id)
    {
        if ($f['on'][$id])
            $on[] = $id;
    }
    return array('on' => $on, 'order' => $f['order']);
}

// The close of check list $f by «Готово» or RETURN (both give the choice, as edit_list_config of the shell): the choice
// kept for its screen (mel_kp_done), then the action of «Готово».
/**
 * @param MelKpFrame $f
 * @return MelAction|null
 */
function mel_kp_apply($f)
{
    mel_log('kit panel: done, ' . count(array_filter($f['on'])) . ' of ' . count($f['on']));
    return mel_ovl_close(isset($f['desc']['done']['act']) ? $f['desc']['done']['act'] : null,
        array('kp_done' => mel_kp_choice($f)));
}

// The choice of the check list closed last on the screen of owner $owner (MelKpChoice: on and order, ids of its items
// in the order of the lines), for the action of its «Готово»; given once. None (another screen, another overlay since):
// null.
/**
 * @param string $owner
 * @return MelKpChoice|null
 */
function mel_kp_done($owner)
{
    $st = MelOvl::$st;
    if (!$st || $st['scr']['owner'] !== $owner || !isset($st['kp_done']))
        return null;
    unset(MelOvl::$st['kp_done']);
    return $st['kp_done'];
}

// kp_key: a key of an open panel. A stale screen: close_and_run (mel_ovl_stale). A live one with no panel (a key that
// came after the close, before its map was applied): none. Else see mel_kp_key.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_kp_input($in)
{
    $a = mel_ovl_stale($in);
    if ($a)
        return $a;
    if (!MelOvl::$st['open'] || !isset(MelOvl::$st['kp']))
    {
        mel_log('kit panel: a key after the close, none');
        return null;
    }
    return mel_kp_key(isset($in->k) && is_string($in->k) ? $in->k : '');
}

// Key $k of the open panel (MelOvl::$st['kp']) -> the reply, null for none (an edge, a key of no use there):
//   up, down    the cursor a line, to the first and the last (no wrap, as the list); the list scrolls with it; a line
//               taken moves with it among the lines of the list (the geometry of two lines);
//   enter       a menu item: its submenu in the place of the panel, else its action after the close; a check line or
//               «all»: its check (and «all» and the count); «Готово»: the close, then its action with the choice;
//   right       an item with a submenu: as enter; a check line of a list with move: taken (⇅);
//   left        a submenu: back, as return;
//   return      a menu: a submenu back to its panel on the same item, else the close; a check list: the close with the
//               choice, as «Готово»;
//   a line taken: enter, right, left and return put it (and nothing else).
/**
 * @param string $k
 * @return MelAction|null
 */
function mel_kp_key($k)
{
    $kp = MelOvl::$st['kp'];
    $n = count($kp['stack']) - 1;
    $f = $kp['stack'][$n];
    $was = $f;
    $lines = mel_kp_lines($f);
    // An empty menu: no line.
    $l = isset($lines[$f['cur']]) ? $lines[$f['cur']] : array('', '', -1, -1);
    $check = isset($f['desc']['items']);
    if ($f['move'] && in_array($k, array('enter', 'right', 'left', 'return'), true))
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
    elseif ($check && ($k === 'return' || ($k === 'left' && $n > 0)))
        return mel_kp_apply($f);
    elseif ($k === 'return' || $k === 'left')
    {
        if ($n === 0)
            return $k === 'return' ? mel_ovl_close() : null;
        array_pop($kp['stack']);
        MelOvl::$st['kp'] = $kp;
        return mel_kp_replace($kp);
    }
    elseif ($l[0] === 'item' && ($k === 'enter' || $k === 'right'))
    {
        $it = $f['its'][$l[1]];
        if (isset($it['menu']))
        {
            $kp['stack'][] = mel_kp_frame($it['menu']);
            MelOvl::$st['kp'] = $kp;
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
        return mel_ovl_close($it['act']);
    }
    elseif ($k === 'enter' && $l[0] === 'done')
        return mel_kp_apply($f);
    elseif ($k === 'enter' && $l[0] === 'all')
        $f['on'] = array_fill_keys(array_keys($f['on']), in_array(false, $f['on'], true));
    elseif ($k === 'enter' && $l[0] === 'check')
        $f['on'][$l[1]] = !$f['on'][$l[1]];
    elseif ($k === 'right' && $l[0] === 'check' && isset($f['desc']['move']) && $f['desc']['move'] !== '')
        $f['move'] = true;
    else
        return null;
    $kp['stack'][$n] = $f;
    MelOvl::$st['kp'] = $kp;
    $ch = mel_kp_redraw($was, $f);
    return $ch ? mel_ovl_change($ch, MelOvl::$st['scr']['sel']) : null;
}

// The panel on screen of state $kp (after a submenu in or out) in the place of the one before: the children of kp_pnl.
/**
 * @param MelKp $kp
 * @return MelAction
 */
function mel_kp_replace($kp)
{
    $defs = mel_kp_index(mel_kp_defs($kp));
    return mel_ovl_change(array(mel_gc_change(MEL_KP . '_pnl', null, GCOMP_TRANSITION_NONE,
        $defs[MEL_KP . '_pnl'][GComponentDef::specific_def][GCompPanelDef::children])), MelOvl::$st['scr']['sel']);
}
