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
//   a menu   title, sub (optional), dim ('dim', the default, or 'dim_strong'), note (optional, under the lines), cur
//            (optional: the id of the item the cursor opens on), groups:
//            lists of items (MelKpItem), an item array('id', 'label', 'icon', 'detail', 'value', 'picked', 'way', and
//            'menu' - the description of a submenu, it takes the place of the panel - or 'act' - an action run after
//            the close: plugin_open_folder of a screen, a handle_user_input of the screen);
//   a check list  title, sub, dim, cur (optional: the id of the item the cursor opens on), all (the label of the line
//            «all», '' none: checked when no item is - no choice is all of them; ENTER on it unchecks the items),
//            all_mode (optional, of a list with «all»: 'every' - the line «all» is checked when every item is, ENTER
//            on it checks them all),
//            items: array('id', 'label', 'count', 'on'), move (the label of the hint «› Переместить»: RIGHT takes a
//            line; '' none), done (none: no line «Готово»): array('label', 'count' - «Выбрано: {n} из {m}»,
//            'count_all' - optional, of a list with «all»: the count with no item checked, «Выбрано: все», 'act' -
//            run after the close, its choice by mel_kp_done).
//   a text   title, dim, text: a list of texts (the reasons of the errors), each wrapped in 2 lines, with no cursor
//            and no action: UP and DOWN turn its page (MelKit::panel_text: the texts that fit whole, the scroll bar
//            when more than a page), LEFT and RETURN as of a menu, ENTER and RIGHT nothing.
// A submenu (MelKpSubDesc) is a menu of items with no submenu of their own, a check list or a text. An item whose id is not a
// string, or the id of one before, is left out (logged); an empty group too; a menu that is not an array, none
// (logged); no title: '' (logged).
// A check list as a submenu: LEFT, RETURN and «Готово» with its choice as it was opened (the checks and the order) go
// back to the panel on the item that opened it (RETURN and «Готово» of a check list opened itself: the close alone,
// as edit_list_config of the shell left with no change); with another choice they close the whole panel as those of a
// check list opened itself do - the choice left to the screen (mel_kp_done), then the act of «Готово». So a check list
// as a submenu must have done with act: with none its changed choice closes the panel and nothing takes it.
// The state, MelOvl::$st['kp'] (MelKp): stack, the panels, the first the top one, the last the one on screen (a
// submenu), each (MelKpFrame): desc, its description; its, its items by id; groups, the ids of a menu by group; cur,
// the cursor (a line of mel_kp_lines); top, the first line of the list shown (of a text: the first text shown); on, order: of a check list, id =>
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

// The kind of frame $f, by its description: text (texts with no cursor: text), check (a check list: items), else menu.
/**
 * @param MelKpFrame $f
 * @return 'menu'|'check'|'text'
 */
function mel_kp_kind($f)
{
    if (isset($f['desc']['text']))
        return 'text';
    return isset($f['desc']['items']) ? 'check' : 'menu';
}

// The texts of frame $f of the kind text: the strings of its description.
/**
 * @param MelKpFrame $f
 * @return list<string>
 */
function mel_kp_texts($f)
{
    return array_values(array_filter(isset($f['desc']['text']) ? $f['desc']['text'] : array(), 'is_string'));
}

// The focusable lines of frame $f in their order, each array(kind, key, g, j): kind item (of a menu), all, check (a line
// of a check list), done; key the id of its item ('' for all and done); g its group in the view, j its place in the
// last group (the list; -1 out of it). A text has none.
/**
 * @param MelKpFrame $f
 * @return list<array{string, string, int, int}>
 */
function mel_kp_lines($f)
{
    $out = array();
    $kind = mel_kp_kind($f);
    if ($kind === 'text')
        return $out;
    if ($kind === 'menu')
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
    if (isset($f['desc']['done']))
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

// The submenu of item $it of a menu «$title»: its description; none, or not an array (logged): null.
/**
 * @param array<string, mixed> $it
 * @param string $title
 * @return MelKpSubDesc|null
 */
function mel_kp_sub($it, $title)
{
    if (!isset($it['menu']))
        return null;
    if (!is_array($it['menu']))
    {
        mel_log("kit panel: «{$title}»: the menu of an item is not a description, none");
        return null;
    }
    /** @var MelKpSubDesc $m */
    $m = $it['menu'];
    return $m;
}

// A frame of description $d: its items by id (a menu not a description: none); the cursor on the item of its cur, else
// (a menu) on its first picked item (a submenu: the current sort), else on the first line, the list scrolled to it; of
// a check list the checks and the order of its items.
/**
 * @param MelKpDesc|MelKpSubDesc $d
 * @return MelKpFrame
 */
function mel_kp_frame($d)
{
    if (!isset($d['title']))
    {
        mel_log('kit panel: a panel with no title');
        $d['title'] = '';
    }
    $title = (string) $d['title'];
    $f = array('desc' => $d, 'its' => array(), 'groups' => array(), 'cur' => 0, 'top' => 0, 'on' => array(),
        'order' => array(), 'move' => false);
    $check = mel_kp_kind($f) === 'check';
    foreach ($check && isset($d['items']) ? array($d['items']) : (isset($d['groups']) ? $d['groups'] : array()) as $items)
    {
        $ids = array();
        foreach ($items as $it)
        {
            $id = mel_kp_id($it);
            if ($id === null || isset($f['its'][$id]))
            {
                mel_log("kit panel: «{$title}»: an item with no id of its own, left out");
                continue;
            }
            if (!$check)
            {
                $sub = mel_kp_sub($it, $title);
                unset($it['menu']);
                if ($sub !== null)
                    $it['menu'] = $sub;
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
    $at = isset($d['cur']) && isset($f['its'][$d['cur']]) ? $d['cur'] : null;
    foreach (mel_kp_lines($f) as $k => $l)
    {
        if ($l[0] !== 'all' && $l[0] !== 'done' &&
            ($at !== null ? $l[1] === $at : !$check && !empty($f['its'][$l[1]]['picked'])))
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

// Does the line «all» of check list $f mean every item checked (all_mode 'every'), not none of them.
/**
 * @param MelKpFrame $f
 * @return bool
 */
function mel_kp_all_every($f)
{
    return isset($f['desc']['all_mode']) && $f['desc']['all_mode'] === 'every';
}

// The view of line $l of frame $f in state $st ('', focus, move) for MelKit::panel_row, with the id of its group; $all:
// the check of the line «all» (no item checked; all_mode 'every': every one).
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
        return array('id' => MEL_KP . '_all', 'label' => isset($f['desc']['all']) ? (string) $f['desc']['all'] : '',
            'state' => $st, 'check' => $all);
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

// The view of the line «Готово» of check list $f in state $st for MelKit::panel_done: the count «Выбрано: n из m»; no
// item checked and a count_all: that one.
/**
 * @param MelKpFrame $f
 * @param string $st
 * @return array{label: string, count: string, state: string}
 */
function mel_kp_done_view($f, $st)
{
    $d = isset($f['desc']['done']) ? $f['desc']['done'] : array('label' => '');
    $n = count(array_filter($f['on']));
    if (!$n && isset($d['count_all']))
        return array('label' => (string) $d['label'], 'state' => $st, 'count' => (string) $d['count_all']);
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
    $done = null;
    $groups = array();
    $all = !in_array(!mel_kp_all_every($f), $f['on'], true);
    foreach (mel_kp_lines($f) as $k => $l)
    {
        if ($l[0] === 'done')
            $done = mel_kp_done_view($f, mel_kp_state($f, $k));
        else
            $groups[$l[2]][] = mel_kp_row($f, $l, mel_kp_state($f, $k), $all);
    }
    $v = array('id' => MEL_KP, 'title' => isset($d['title']) ? (string) $d['title'] : '', 'back' => $back,
        'sub' => isset($d['sub']) ? (string) $d['sub'] : '', 'dim' => mel_kp_dim($f), 'x' => $x,
        'groups' => array_values($groups), 'top' => $f['top'], 'done' => $done,
        'hint' => mel_kp_kind($f) === 'check' && isset($d['move']) ? (string) $d['move'] : '',
        'note' => isset($d['note']) ? (string) $d['note'] : '');
    if (mel_kp_kind($f) === 'text')
        $v['text'] = mel_kp_texts($f);
    return $v;
}

// The shade of frame $f: dim (the default) or dim_strong.
/**
 * @param MelKpFrame $f
 * @return string
 */
function mel_kp_dim($f)
{
    return isset($f['desc']['dim']) ? (string) $f['desc']['dim'] : 'dim';
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

// What a key may change of frame $f, by the id of its group: v, the view of a line (MelKitPanelRow) or d, of «Готово»
// (that of panel_done), the other null; j, its place in the list (-1 out of it). No component is built.
/**
 * @param MelKpFrame $f
 * @return array<string, array{v: MelKitPanelRow|null, d: array{label: string, count: string, state: string}|null, j: int}>
 */
function mel_kp_looks($f)
{
    $out = array();
    $all = !in_array(!mel_kp_all_every($f), $f['on'], true);
    foreach (mel_kp_lines($f) as $k => $l)
    {
        if ($l[0] === 'done')
            $out[MEL_KP . '_done'] = array('v' => null, 'd' => mel_kp_done_view($f, mel_kp_state($f, $k)), 'j' => -1);
        else
        {
            $r = mel_kp_row($f, $l, mel_kp_state($f, $k), $all);
            $out[isset($r['id']) ? $r['id'] : ''] = array('v' => $r, 'd' => null, 'j' => $l[3]);
        }
    }
    return $out;
}

// The changes of a key from frame $was to frame $f of the same panel, one a component, only of what differs: the
// children of a line or of «Готово» (the focus, a check, the count) - only those lines are built; the geometry of a
// line of the list (moved), of the list kp_rows and of the thumb (scrolled). Of a text: the children of kp_text (the
// texts of the page and the scroll bar) when the page is another.
/**
 * @param MelKpFrame $was
 * @param MelKpFrame $f
 * @return list<MelGcChange>
 */
function mel_kp_redraw($was, $f)
{
    if (mel_kp_kind($f) === 'text')
    {
        if ($f['top'] === $was['top'])
            return array();
        $t = MelKit::panel_text(MEL_KP, mel_kp_texts($f), $f['top']);
        return array(mel_gc_change(MEL_KP . '_text', null, GCOMP_TRANSITION_NONE,
            $t['it']['defs'][0][GComponentDef::specific_def][GCompPanelDef::children]));
    }
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
        $it = null;
        if ($x['d'] !== null && $x['d'] !== $a[$id]['d'])
            $it = MelKit::panel_done($id, $x['d']['label'], $x['d']['count'], $x['d']['state']);
        elseif ($x['v'] !== null && $x['v'] !== $a[$id]['v'])
            $it = MelKit::panel_row($id, $x['v']);
        if ($it !== null)
            $kids = $it['defs'][0][GComponentDef::specific_def][GCompPanelDef::children];
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
        mel_log('kit panel: open «' . (isset($d['title']) ? $d['title'] : '') . '»');
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

// The close of the panel by check list $f left with its choice changed (the checks or the order; the panel itself by
// «Готово» or RETURN, a submenu by LEFT too): its choice kept for its screen (mel_kp_done), then the action of
// «Готово».
/**
 * @param MelKpFrame $f
 * @return MelAction|null
 */
function mel_kp_apply($f)
{
    mel_log('kit panel: «' . (isset($f['desc']['title']) ? $f['desc']['title'] : '') . '»: done, ' .
        count(array_filter($f['on'])) . ' of ' . count($f['on']));
    return mel_ovl_close(isset($f['desc']['done']['act']) ? $f['desc']['done']['act'] : null,
        array('kp_done' => mel_kp_choice($f)));
}

// The choice of the check list that closed the panel last on the screen of owner $owner (MelKpChoice: on and order,
// ids of its items in the order of the lines), the panel itself or a submenu of it; the state of the owner in hand
// from now (it waited, if a key or a tick of another screen came since the close). Given once. None (another overlay
// of this screen since, no check list closed it): null.
/**
 * @param string $owner
 * @return MelKpChoice|null
 */
function mel_kp_done($owner)
{
    $st = mel_ovl_take($owner) ? mel_ovl_st() : null;
    if (!$st || !isset($st['kp_done']))
        return null;
    $c = $st['kp_done'];
    unset($st['kp_done']);
    mel_ovl_set($st);
    return $c;
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
    if (!mel_ovl_shown('kp'))
    {
        mel_log('kit panel: a key after the close, none');
        return null;
    }
    return mel_kp_key(isset($in->k) && is_string($in->k) ? $in->k : '');
}

// Key $k of the open panel (MelOvl::$st['kp']) -> the reply, null for none (an edge, a key of no use there; no panel
// open):
//   up, down    the cursor a line, to the first and the last (no wrap, as the list); the list scrolls with it; a line
//               taken moves with it among the lines of the list (the geometry of two lines); a text: its page before
//               and after (at the first and the last none);
//   enter       a menu item: its submenu in the place of the panel, else its action after the close; a check line:
//               its check (and «all» and the count); «all»: the check lines unchecked (it alone checked; all_mode
//               'every': all of them checked); «Готово»: as return;
//   right       an item with a submenu: as enter; a check line of a list with move: taken (⇅);
//   left        a submenu: as return;
//   return      a submenu: back to the panel on the same item; a menu or a check list itself: the close; a check
//               list with its choice changed (not as it opened), a submenu too: the close with the choice, then its
//               act;
//   a line taken: enter, right, left and return put it (and nothing else).
/**
 * @param string $k
 * @return MelAction|null
 */
function mel_kp_key($k)
{
    $st = mel_ovl_st();
    if (!$st || !isset($st['kp']))
        return null;
    $kp = $st['kp'];
    $n = count($kp['stack']) - 1;
    $f = $kp['stack'][$n];
    $was = $f;
    $lines = mel_kp_lines($f);
    // An empty menu: no line.
    $l = isset($lines[$f['cur']]) ? $lines[$f['cur']] : array('', '', -1, -1);
    $check = mel_kp_kind($f) === 'check';
    $back = $k === 'return' || $k === 'left' || ($k === 'enter' && $l[0] === 'done');
    if ($f['move'] && in_array($k, array('enter', 'right', 'left', 'return'), true))
        $f['move'] = false;
    elseif (mel_kp_kind($f) === 'text' && ($k === 'up' || $k === 'down'))
    {
        $t = MelKit::panel_text(MEL_KP, mel_kp_texts($f), $f['top']);
        if ($t[$k] === $t['top'])
            return null;
        $f['top'] = $t[$k];
    }
    elseif ($k === 'up' || $k === 'down')
    {
        $to = $f['cur'] + ($k === 'up' ? -1 : 1);
        if ($f['move'])
        {
            $j = $l[3] + ($k === 'up' ? -1 : 1);
            if ($j < 0 || $j >= count($f['order']))
                return null;
            $o = $f['order'];
            $o[$l[3]] = $o[$j];
            $o[$j] = $l[1];
            $f['order'] = array_values($o);
        }
        elseif ($to < 0 || $to >= count($lines))
            return null;
        $f['cur'] = $to;
        $f = mel_kp_window($f);
    }
    elseif ($check && $back && ($n > 0 || $k !== 'left') &&
        mel_kp_choice($f) !== mel_kp_choice(mel_kp_frame($f['desc'])))
        return mel_kp_apply($f);
    elseif ($back && $n > 0)
    {
        $stack = $kp['stack'];
        array_pop($stack);
        if (!$stack)
            return null;
        $kp['stack'] = $stack;
        return mel_kp_put($kp, $was);
    }
    elseif ($k === 'return' || ($check && $back && $k !== 'left'))
        return mel_ovl_close();
    elseif ($l[0] === 'item' && ($k === 'enter' || $k === 'right'))
    {
        $it = $f['its'][$l[1]];
        if (isset($it['menu']))
        {
            $kp['stack'][] = mel_kp_frame($it['menu']);
            return mel_kp_put($kp, $was);
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
    elseif ($k === 'enter' && $l[0] === 'all')
        $f['on'] = array_fill_keys(array_keys($f['on']), mel_kp_all_every($f));
    elseif ($k === 'enter' && $l[0] === 'check')
        $f['on'][$l[1]] = !$f['on'][$l[1]];
    elseif ($k === 'right' && $l[0] === 'check' && isset($f['desc']['move']) && $f['desc']['move'] !== '')
        $f['move'] = true;
    else
        return null;
    $kp['stack'][$n] = $f;
    $st['kp'] = $kp;
    mel_ovl_set($st);
    $ch = mel_kp_redraw($was, $f);
    return $ch ? mel_ovl_change($ch, $st['scr']['sel']) : null;
}

// State $kp (a submenu in or out) kept, its panel on screen in the place of that of frame $was: the children of kp_pnl
// and, when its shade is another (dim, dim_strong), of kp_dim - what a build of it whole gives.
/**
 * @param MelKp $kp
 * @param MelKpFrame $was
 * @return MelAction|null
 */
function mel_kp_put($kp, $was)
{
    $st = mel_ovl_st();
    if (!$st)
        return null;
    $st['kp'] = $kp;
    mel_ovl_set($st);
    $defs = mel_kp_index(mel_kp_defs($kp));
    $ch = array();
    if (mel_kp_dim($kp['stack'][count($kp['stack']) - 1]) !== mel_kp_dim($was))
        $ch[] = mel_gc_change(MEL_KP . '_dim', null, GCOMP_TRANSITION_NONE,
            $defs[MEL_KP . '_dim'][GComponentDef::specific_def][GCompPanelDef::children]);
    $ch[] = mel_gc_change(MEL_KP . '_pnl', null, GCOMP_TRANSITION_NONE,
        $defs[MEL_KP . '_pnl'][GComponentDef::specific_def][GCompPanelDef::children]);
    return mel_ovl_change($ch, $st['scr']['sel']);
}
