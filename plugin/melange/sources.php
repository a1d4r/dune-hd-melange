<?php
// "Откуда брать…" in MENU of a list (v2, spec source-profiles-v2 §2.2): the sources (profiles of settings.json) ready,
// in their order, by their id. A choice shows this title from that source on this screen only: nothing written, nothing
// remembered - the next opening goes by the history or the main one (§1.5); what is started from the screen takes its
// profile into the record (wh_prof). The screen keeps two lists (view_online.php): the releases of one profile of
// releases (rows, prof) and the online list of one profile of Lampac (lp, lp_prof); a choice of one of them already
// there - no request. The item of MENU: aio_gc_src_item (view_gcomps.php), aio_lpv_menu (view_online.php). The line
// "Откуда: <source>" over the lists: aio_src_tabs_right.
// Reads and writes Aio (state, settings). Functions are global; the require order is in main.php.

// The id of the profile list $st shows: of Lampac on the online list, else of releases ('' none).
/**
 * @param AioState $st
 * @return string
 */
function aio_src_now($st)
{
    return !empty($st['lp_on']) ? $st['lp_prof'] : $st['prof'];
}

// The ready profiles of settings.json of the operation (Aio::$settings profiles) in their order.
/**
 * @return list<array{id: string, name: string, kind: string, ready: bool}>
 */
function aio_src_ready()
{
    $r = array();
    foreach (Aio::$settings['profiles'] as $p)
    {
        if ($p['ready'])
            $r[] = $p;
    }
    return $r;
}

// "Откуда брать…" in MENU of a list: two ready profiles or more, of any kind.
/**
 * @return bool
 */
function aio_src_in_menu()
{
    return count(aio_src_ready()) >= 2;
}

// gc_src_menu (MENU "Откуда брать…") of a live list $in->rid -> the popup menu of the ready profiles in their order, the
// one of the screen (aio_src_now) "<name> · сейчас" and selected (marked of the API draws nothing in a popup menu on
// r24); Lampac with its own icon.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_gc_src_menu($in)
{
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('source: menu of no live list');
        return aio_error('err_list_expired');
    }
    return aio_src_popup($st['lang'], 'gc_src', array('rid' => $st['rid']), aio_src_now($st));
}

// The popup menu of the ready profiles in their order, Lampac with its own icon, each to $control of $params with p (its
// id) and n (its name for the dialog); the one of id $now "<name> · сейчас" and selected ('' none: the first selected).
// None ready: null.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @param string $now
 * @return AioAction|null
 */
function aio_src_popup($lang, $control, $params, $now = '')
{
    $items = array();
    $sel = 0;
    foreach (aio_src_ready() as $p)
    {
        if ($p['id'] === $now)
            $sel = count($items);
        $items[] = array(
            GuiMenuItemDef::caption => $p['id'] === $now ? str_replace('{name}', $p['name'], aio_tr($lang, 'src_now')) :
                $p['name'],
            GuiMenuItemDef::icon_url => $p['kind'] === 'lampac' ? 'gui_skin://small_icons/web_browser.aai' :
                'gui_skin://small_icons/sources.aai',
            GuiMenuItemDef::action => aio_input($control, array_merge($params, array('p' => $p['id'], 'n' => $p['name']))));
    }
    if (!$items)
    {
        aio_log('source: no profile ready, nothing');
        return null;
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => $sel));
}

// --- "Смотреть из…" of POP UP of a tile of a title and of the own card: the list of the title at once from the source
// chosen (aio_play_action with its profile), not of the record or the main one; nothing remembered, as "Откуда брать…".

// The item "Смотреть из…" to $control of $params (its menu of sources: aio_src_popup), with two ready profiles or more
// (aio_src_in_menu); none else.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @return list<array<string, mixed>>
 */
function aio_src_watch_item($lang, $control, $params)
{
    if (!aio_src_in_menu())
        return array();
    return array(array(
        GuiMenuItemDef::caption => aio_tr($lang, 'src_watch'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
        GuiMenuItemDef::action => aio_input($control, $params)));
}

// The choice of "Смотреть из…" of input $in (p: the id of a profile, n: its name; of aio_src_popup) -> array(the id, ''
// not an id: nothing; the dialog "Источник не сменился: <name>" (src_changed) of a profile gone or not ready since the
// menu (the page changed them), no request; null - there and ready).
/**
 * @param stdClass $in
 * @param string $lang
 * @return array{string, AioAction|null}
 */
function aio_src_watch_pick($in, $lang)
{
    $id = isset($in->p) && is_string($in->p) ? $in->p : '';
    if (!aio_profile_id_ok($id))
    {
        aio_log('source: watch from: no profile, nothing');
        return array('', null);
    }
    $s = Aio::$file;
    $k = $s ? aio_profile_index($s, $id) : -1;
    if ($k >= 0 && aio_profile_ready($s['profiles'][$k]))
        return array($id, null);
    aio_log('source: watch from: no such profile ready, nothing asked');
    $name = isset($in->n) && is_string($in->n) ? $in->n : '';
    return array($id, aio_gc_src_dialog(str_replace('{name}', $name, aio_tr($lang, 'src_failed')), $lang,
        array('error' => 'src_changed', 'detail' => '')));
}

// The dialog of a source that failed (aio_gc_src): $title, the error, its detail, the
// reasons of the error streams with the setting on, OK.
/**
 * @param string $title
 * @param string $lang
 * @param array{error: string, detail: string, errors?: AioErrors} $res
 * @return AioAction
 */
function aio_gc_src_dialog($title, $lang, $res)
{
    $lines = array_merge(aio_dialog_lines(aio_tr($lang, $res['error'])), aio_dialog_lines($res['detail']));
    if (isset($res['errors']))
        $lines = array_merge($lines, aio_errors_labels($lang, $res['errors']));
    return aio_dialog($title, $lines, array('OK' => aio_close_and(null)));
}

// gc_src (p: the id of a profile, n: its name for the dialog; MENU "Откуда брать…") of a live list: this title from
// that profile on this screen only (spec v2 §2.2, nothing written). The one of the screen: nothing; of the releases
// with the yellow line - the line gone. A profile of releases: aio_src_releases; of Lampac: aio_src_lampac. The profile
// gone or not ready (the page changed them since the menu): the dialog "Источник не сменился: <name>" (src_changed), no
// request. A list not live, no id: nothing.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_gc_src($in)
{
    $st = Aio::$state;
    $id = isset($in->p) && is_string($in->p) ? $in->p : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !aio_profile_id_ok($id))
    {
        aio_log('source: not a live list or no profile, nothing');
        return null;
    }
    $name = isset($in->n) && is_string($in->n) ? $in->n : '';
    $title = str_replace('{name}', $name, aio_tr($st['lang'], 'src_failed'));
    $s = Aio::$file;
    $k = $s ? aio_profile_index($s, $id) : -1;
    $now = $s ? aio_profile_index($s, aio_src_now($st)) : -1;
    $tag = 'source: profile ' . aio_profile_tag($now, $s && $now >= 0 ? $s['profiles'][$now] : null) . ' -> ' .
        aio_profile_tag($k, $s && $k >= 0 ? $s['profiles'][$k] : null) . ', ' . aio_ep_tag($st);
    if (!$s || $k < 0 || !aio_profile_ready($s['profiles'][$k]))
    {
        aio_log("$tag: no such profile ready, the screen kept");
        return aio_gc_src_dialog($title, $st['lang'], array('error' => 'src_changed', 'detail' => ''));
    }
    if ($id === aio_src_now($st))
    {
        if (empty($st['lp_on']) && !empty($st['lp_note']))
        {
            $ns = $st;
            $ns['rid'] = aio_rid();
            unset($ns['lp_note']);
            aio_log("$tag: in use, the yellow line gone");
            return aio_list_replace($ns);
        }
        aio_log("$tag: in use, nothing");
        return null;
    }
    if (!aio_profile_rel($s['profiles'][$k]))
        return aio_src_lampac($st, $id, $tag);
    return aio_src_releases($st, $id, $tag, $title, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
}

// The releases of profile $id for list $st (aio_gc_src; $tag of the log, $title of the dialog of an error, $sel_state
// of the shell - the cursor of the releases). Its releases there (the screen shows its online list over them): back to
// them, no request, the cursor where it was (the same list, the same order). Else asked with its settings (aio_relist;
// the log masked by them too), the online list of the screen kept as its second one (of the same Lampac only), the
// screen replaced, the cursor on the first row (another source sorts the releases its own way: the one under the
// cursor would be lower); no streams - an empty list; an error - its dialog, the screen, the state and the settings
// of the operation kept (aio_op_apply).
/**
 * @param AioState $st
 * @param string $id
 * @param string $tag
 * @param string $title
 * @param mixed $sel_state
 * @return AioAction
 */
function aio_src_releases($st, $id, $tag, $title, $sel_state)
{
    if ($st['prof'] === $id && empty($st['lp_only']))
    {
        $ns = $st;
        $ns['rid'] = aio_rid();
        unset($ns['lp_on']);
        // Their own cursor, not the sel_state of the online list.
        $ns['gc_new'] = true;
        aio_log("$tag: its releases again, " . count($ns['rows']) . ' rows, no request');
        return aio_list_replace($ns);
    }
    // The online list: the cursor of the releases, not of its rows.
    list($cur) = aio_gc_pos($st, empty($st['lp_on']) ? $sel_state : null);
    $old = Aio::$settings;
    aio_op_use($id, $st['lp_prof']);
    $r = aio_relist($st, $st['s'], $st['e'], '');
    if (!isset($r['error']))
        $r['by'] = 'first row';
    else
    {
        $errors = isset($r['errors']) ? $r['errors'] : aio_no_errors();
        if ($r['error'] === 'err_no_streams' && !$errors['lines'])
            $r = array('st' => aio_gc_put_cursor(aio_list_state($st['movie'], $st['s'], $st['e'], array(), $st['lang'],
                $errors), 0), 'sel' => 0, 'by' => 'no streams');
        else
        {
            aio_log(sprintf('%s failed: %s, %.2f s, the screen kept', $tag,
                $r['error'] === 'err_no_streams' ? 'error streams only' : $r['error'], microtime(true) - Aio::$t0));
            if ($r['error'] === 'err_no_streams')
                $r['error'] = 'err_sources_failed';
            aio_op_apply($old);
            return aio_gc_src_dialog($title, $st['lang'], $r);
        }
    }
    $ns = $r['st'];
    // The online list goes with its Lampac: kept only when the pair kept it (that profile gone - another one now).
    if (isset($st['lp']) && $ns['lp_prof'] === $st['lp_prof'])
        $ns['lp'] = $st['lp'];
    aio_log(sprintf('%s, screen replaced, %d -> %d rows, row %d -> %d (%s), %.2f s', $tag, count($st['rows']),
        count($ns['rows']), $cur, $r['sel'], $r['by'], microtime(true) - Aio::$t0));
    return aio_list_replace($ns);
}

// The online list of the profile of Lampac $id for list $st (aio_gc_src; $tag of the log): its online list there younger
// than AIO_LPV_REFRESH s - back to it, no request; else the window "Ищу онлайн…" of it over the screen (the list of that
// Lampac in its place at the end; its windows of no answer, nothing online keep the screen and its profiles). No online
// list of this list (a series without its episode): nothing.
/**
 * @param AioState $st
 * @param string $id
 * @param string $tag
 * @return AioAction|null
 */
function aio_src_lampac($st, $id, $tag)
{
    $old = Aio::$settings;
    aio_op_use($st['prof'], $id);
    if (!aio_lpv_on($st['movie'], $st['s'], $st['e']))
    {
        aio_op_apply($old);
        aio_log("$tag: no online list of this, nothing");
        return null;
    }
    if ($st['lp_prof'] === $id && isset($st['lp']) && aio_lp_now() - $st['lp']['t'] < AIO_LPV_REFRESH)
    {
        $ns = $st;
        $ns['rid'] = aio_rid();
        $ns['lp_on'] = true;
        // Its own cursor, not the sel_state of the releases it replaces.
        $ns['lp']['gc_new'] = true;
        aio_log("$tag: its online list again, " . count($ns['lp']['rows']) . ' rows, no request');
        return aio_list_replace($ns);
    }
    $ns = $st;
    $ns['lp_prof'] = $id;
    aio_log("$tag: the search");
    return aio_lpv_search_open($ns, 'replace');
}

// --- The line "Откуда: <source>" over the lists (the releases and the online list, view_online.php).

// The name of the ready profile of id $id of the operation (Aio::$settings profiles), '' none.
/**
 * @param string $id
 * @return string
 */
function aio_src_name($id)
{
    foreach (aio_src_ready() as $p)
    {
        if ($p['id'] === $id)
            return $p['name'];
    }
    return '';
}

// Children of "Откуда: <source>" right-aligned to x $right of the row of the tabs of list $st,
// with two ready profiles or more (spec v2 §2.2), in $room px at most (the tabs left of it; less than the
// word and a few letters: none). -> array(children, their width; 0 none).
/**
 * @param AioState $st
 * @param int $right
 * @param int $y
 * @param int $room
 * @return array{list<AioGcComp>, int}
 */
function aio_src_tabs_right($st, $right, $y, $room)
{
    $from = aio_src_in_menu() ? aio_src_name(aio_src_now($st)) : '';
    if ($from === '')
        return array(array(), 0);
    $lang = aio_gc_lang($st);
    $h = AIO_GC_LABEL_H;
    $y += intval((AIO_GC_KEY_H - $h) / 2);
    $lead = aio_tr($lang, 'lp_from') . ' ';
    $lw = aio_gc_w($lead, AIO_GC_HINT_PX);
    if ($room - $lw < 80)
        return array(array(), 0);
    $name = aio_gc_cut_w($from, AIO_GC_HINT_PX, min(300, $room - $lw));
    $nw = aio_gc_w($name, AIO_GC_HINT_PX);
    return array(array(
        aio_gc_cut(null, aio_gc_at($lw, $h, $right - $nw - $lw, $y), $lead, AIO_GC_HINT_PX, AIO_GC_DIM),
        aio_gc_cut(null, aio_gc_at($nw, $h, $right - $nw, $y), $name, AIO_GC_HINT_PX, AIO_GC_TEXT2)), $nw + $lw);
}
