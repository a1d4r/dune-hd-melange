<?php
// "Откуда брать" in MENU of the list (0.58.0): the source profiles the user saved on the
// settings page (settings.json, profiles). The menu of them, and the switch: the
// request to the source of the profile first, then settings.json read again and
// written (until stage 2 of v2: the main one, aio_settings_from_top), the screen replaced. The item of MENU: aio_gc_src_item, view_gcomps.php. The line
// "Откуда: <source>" over the lists: aio_src_tabs_right.
// Reads and writes Aio (state, settings). Functions are global; the require order is
// in main.php.

// gc_src_menu (MENU "Откуда брать…") of a live list -> the popup menu of the ready profiles in
// their order, the one in use "<name> · сейчас" and selected (marked of the API draws
// nothing in a popup menu on r24), else the first.
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
    $items = array();
    $sel = 0;
    foreach (aio_src_menu_profiles() as $p)
    {
        // The online list: no profile "сейчас" (view_online.php).
        $now = $p['now'] && empty($st['lp_on']);
        if ($now)
            $sel = count($items);
        $items[] = array(
            GuiMenuItemDef::caption => $now ? str_replace('{name}', $p['name'], aio_tr($st['lang'], 'src_now')) :
                $p['name'],
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_src', $st['rid'], array('n' => $p['name'])));
    }
    // "Онлайн" under a separator; no profile: "Раздачи" with it.
    if (aio_lpv_in_menu($st))
        list($items, $sel) = aio_lpv_src_items($st, $items, $sel);
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

// The profiles of releases of MENU "Откуда брать…" (v2: always, no setting): those ready (aio_profile_ready) in their
// order, now - the profile of releases of the operation (Aio::$settings prof).
/**
 * @return list<array{name: string, now: bool}>
 */
function aio_src_menu_profiles()
{
    $r = array();
    foreach (Aio::$settings['profiles'] as $p)
    {
        if ($p['ready'] && $p['kind'] !== 'lampac')
            $r[] = array('name' => $p['name'], 'now' => $p['id'] === Aio::$settings['prof']);
    }
    return $r;
}

// Profile $k of the settings $s (aio_profile_find; -1: none) can be switched to: there, of releases and ready.
/**
 * @param AioSettings $s
 * @param int $k
 * @return bool
 */
function aio_gc_src_usable($s, $k)
{
    return $k >= 0 && aio_profile_rel($s['profiles'][$k]) && aio_profile_ready($s['profiles'][$k]);
}

// After the request to the source of profile $p (aio_gc_src): settings.json read again (the
// page may have saved while the source answered), the profile applied, the main one until stage 2 of v2
// (aio_settings_from_top), written, Aio::$settings of it -> ''. Not written -> the key of the error, Aio::$settings not
// touched (the one rollback, to the settings before the request, is in aio_gc_src): the
// file not read (err_quick_read), not written (err_setup_write); the page changed meanwhile
// the profile (gone, another, not ready) or the source of the settings
// $s0 of the first read (aio_source_keys: src_changed).
/**
 * @param string $dir
 * @param AioSettings $s0
 * @param AioProfile $p
 * @param string $tag
 * @return string
 */
function aio_gc_src_commit($dir, $s0, $p, $tag)
{
    $s = aio_settings_read($dir, $why);
    $k = aio_profile_find($s, $p['name']);
    $err = array('', '');
    if ($why !== '')
        $err = array('err_quick_read', "settings.json $why");
    elseif (!aio_gc_src_usable($s, $k) || $s['profiles'][$k] !== $p)
        $err = array('src_changed', 'the profile changed meanwhile');
    else
    {
        foreach (aio_source_keys() as $f)
        {
            if ($s[$f] !== $s0[$f])
                $err = array('src_changed', 'the source changed meanwhile');
        }
    }
    if ($err[0] === '')
    {
        $s = aio_settings_from_top(aio_profile_apply($s, $p));
        if (aio_settings_write($s))
        {
            aio_op_apply(aio_op_settings($s));
            return '';
        }
        $err = array('err_setup_write', 'settings.json not written');
    }
    aio_log("$tag: {$err[1]}, the source and the screen kept");
    return $err[0];
}

// gc_src (n: the name of a profile, MENU "Откуда брать") of a live list: the list of its
// movie or episode from the source of the profile first (aio_relist with the settings of
// the profile, its JacRed too: the voices and tracks of JacRed are of the profile). It answered, with no streams too (an empty list), but not with error
// streams only (a link not taken): written (aio_gc_src_commit; the source in use kept by a
// profile first), the screen replaced, the cursor on the first row (another source sorts the releases
// its own way: the one under the cursor would be lower). An error, not written: its dialog - the source, the screen
// and the state as they were. The profile gone, not ready, the setting off, settings.json
// not read, the source to save with the profiles full: a dialog too, no request. The profile in use, a list not live: nothing.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_gc_src($in)
{
    $st = Aio::$state;
    $name = isset($in->n) && is_string($in->n) ? $in->n : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || $name === '')
    {
        aio_log('source: not a live list or no name, nothing');
        return null;
    }
    $title = str_replace('{name}', $name, aio_tr($st['lang'], 'src_failed'));
    $dir = aio_data_dir();
    $s = aio_settings_read($dir, $why);
    $k = aio_profile_find($s, $name);
    if ($why !== '' || !aio_gc_src_usable($s, $k))
    {
        // The menu was of the settings before: the page changed them since.
        aio_log('source: ' . ($why !== '' ? "settings.json $why" : 'no such profile ready') . ', ' .
            aio_ep_tag($st) . ', the source and the screen kept');
        return aio_gc_src_dialog($title, $st['lang'], array('error' => $why !== '' ? 'err_quick_read' : 'src_changed',
            'detail' => ''));
    }
    $now = aio_profile_index($s, Aio::$settings['prof']);
    $p = $s['profiles'][$k];
    $tag = 'source: profile ' . aio_profile_tag($now, $now < 0 ? null : $s['profiles'][$now]) . ' -> ' .
        aio_profile_tag($k, $p) . ', ' . aio_ep_tag($st);
    // The profile in use from the online list: its releases back; from the releases with the
    // yellow line: the line gone (view_online.php).
    if ($k === $now && (!empty($st['lp_on']) || !empty($st['lp_note'])))
        return aio_lpv_back($st);
    if ($k === $now)
    {
        aio_log("$tag: in use, nothing");
        return null;
    }
    // The online list: the cursor of the releases, not of its rows.
    list($cur) = aio_gc_pos($st, isset($in->parent_sel_state) && empty($st['lp_on']) ? $in->parent_sel_state : null);
    $old = Aio::$settings;
    // The request goes with the settings of the profile, the log masked by them too.
    aio_op_apply(aio_op_settings($s, $p['id'], Aio::$settings['lp_prof']));
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
            aio_log(sprintf('%s failed: %s, %.2f s, the source and the screen kept', $tag,
                $r['error'] === 'err_no_streams' ? 'error streams only' : $r['error'], microtime(true) - Aio::$t0));
            if ($r['error'] === 'err_no_streams')
                $r['error'] = 'err_sources_failed';
        }
    }
    if (!isset($r['error']))
    {
        $e = aio_gc_src_commit($dir, $s, $p, $tag);
        if ($e === '')
        {
            $ns = $r['st'];
            aio_log(sprintf('%s, screen replaced, %d -> %d rows, row %d -> %d (%s), %.2f s', $tag, count($st['rows']),
                count($ns['rows']), $cur, $r['sel'], $r['by'], microtime(true) - Aio::$t0));
            return aio_list_replace($ns);
        }
        $r = array('error' => $e, 'detail' => '');
    }
    aio_op_apply($old);
    return aio_gc_src_dialog($title, $st['lang'], $r);
}

// --- The line "Откуда: <source>" over the lists (the releases and the online list, view_online.php).

// Sources of list $st: the releases (the profiles ready, else the settings, if AIOStreams is set),
// and "Онлайн".
/**
 * @param AioState $st
 * @return int
 */
function aio_src_sources_n($st)
{
    return (Aio::$settings['base'] !== '' ? max(1, count(aio_src_menu_profiles())) : 0) +
        (aio_lpv_on($st['movie'], $st['s'], $st['e']) ? 1 : 0);
}

// The name of the source of list $st for "Откуда: …": "Онлайн", the profile in use, else "Раздачи".
/**
 * @param AioState $st
 * @return string
 */
function aio_src_from_name($st)
{
    if (!empty($st['lp_on']))
        return aio_tr($st['lang'], 'lp_online');
    foreach (aio_src_menu_profiles() as $p)
    {
        if ($p['now'])
            return $p['name'];
    }
    return aio_tr($st['lang'], 'lp_releases');
}

// Children of "Откуда: <source>" right-aligned to x $right of the row of the tabs of list $st,
// with two sources or more (spec §5.1), in $room px at most (the tabs left of it; less than the
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
    if (aio_src_sources_n($st) < 2)
        return array(array(), 0);
    $lang = aio_gc_lang($st);
    $h = AIO_GC_LABEL_H;
    $y += intval((AIO_GC_KEY_H - $h) / 2);
    $lead = aio_tr($lang, 'lp_from') . ' ';
    $lw = aio_gc_w($lead, AIO_GC_HINT_PX);
    if ($room - $lw < 80)
        return array(array(), 0);
    $name = aio_gc_cut_w(aio_src_from_name($st), AIO_GC_HINT_PX, min(300, $room - $lw));
    $nw = aio_gc_w($name, AIO_GC_HINT_PX);
    return array(array(
        aio_gc_cut(null, aio_gc_at($lw, $h, $right - $nw - $lw, $y), $lead, AIO_GC_HINT_PX, AIO_GC_DIM),
        aio_gc_cut(null, aio_gc_at($nw, $h, $right - $nw, $y), $name, AIO_GC_HINT_PX, AIO_GC_TEXT2)), $nw + $lw);
}
