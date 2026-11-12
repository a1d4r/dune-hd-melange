<?php
// "Откуда брать" in MENU of the list (0.58.0): the source profiles the user saved on the
// settings page (settings.json, src_profiles). The menu of them, and the switch: the
// request to the source of the profile first, then settings.json read again and
// written, the screen replaced. The item of MENU: aio_gc_src_item, view_gcomps.php.
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
    foreach (Aio::$settings['profiles'] as $p)
    {
        if ($p['now'])
            $sel = count($items);
        $items[] = array(
            GuiMenuItemDef::caption => $p['now'] ? str_replace('{name}', $p['name'], aio_tr($st['lang'], 'src_now')) :
                $p['name'],
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_src', $st['rid'], array('n' => $p['name'])));
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

// A profile for the log: its number from 1 ("none" for -1) and kind, never its name
// or link (the user typed them).
/**
 * @param int $i
 * @param AioProfile|null $p
 * @return string
 */
function aio_gc_src_tag($i, $p)
{
    if ($i < 0 || !$p)
        return 'none';
    return ($i + 1) . ' (' . (isset($p['url']) ? 'own' : "made {$p['mode']}") . ')';
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

// gc_src (n: the name of a profile, MENU "Откуда брать") of a live list: the list of its
// movie or episode from the source of the profile first (aio_relist with the settings of
// the profile). It answered, with no streams too (an empty list), but not with error
// streams only (a link not taken): settings.json read again (the page may have saved
// meanwhile), the profile applied and written, the screen replaced, the cursor on the
// same release. An error: its dialog; not written: a dialog too - the source, the screen
// and the state as they were. The profile in use, not ready, gone, a list not live:
// nothing.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_gc_src($in)
{
    $st = Aio::$state;
    $name = isset($in->n) && is_string($in->n) ? $in->n : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('source: not a live list, nothing');
        return null;
    }
    $dir = aio_data_dir();
    $s = aio_settings_read($dir, $why);
    $k = aio_profile_find($s, $name);
    if ($why !== '' || !$s['src_profiles'] || $k < 0 || !aio_profile_ready($s, $s['profiles'][$k]))
    {
        aio_log('source: ' . ($why !== '' ? "settings.json $why" : 'no such profile ready') . ', nothing');
        return null;
    }
    $now = aio_profile_now($s);
    $p = $s['profiles'][$k];
    $tag = 'source: profile ' . aio_gc_src_tag($now, $now < 0 ? null : $s['profiles'][$now]) . ' -> ' .
        aio_gc_src_tag($k, $p) . ', ' . aio_ep_tag($st);
    if ($k === $now)
    {
        aio_log("$tag: in use, nothing");
        return null;
    }
    list($cur) = aio_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    $old = Aio::$settings;
    // The request goes with the settings of the profile, the log masked by them too.
    Aio::$settings = aio_op_settings(aio_profile_apply($s, $p));
    $r = aio_relist($st, $st['s'], $st['e'], isset($st['rows'][$cur]) ? $st['rows'][$cur]['hash'] : '');
    $title = str_replace('{name}', $p['name'], aio_tr($st['lang'], 'src_failed'));
    if (isset($r['error']))
    {
        $errors = isset($r['errors']) ? $r['errors'] : aio_no_errors();
        if ($r['error'] !== 'err_no_streams' || $errors['lines'])
        {
            Aio::$settings = $old;
            aio_log(sprintf('%s failed: %s, %.2f s, the source and the screen kept', $tag,
                $r['error'] === 'err_no_streams' ? 'error streams only' : $r['error'], microtime(true) - Aio::$t0));
            if ($r['error'] === 'err_no_streams')
                $r['error'] = 'err_sources_failed';
            return aio_gc_src_dialog($title, $st['lang'], $r);
        }
        $r = array('st' => aio_gc_put_cursor(aio_list_state($st['movie'], $st['s'], $st['e'], array(), $st['lang'],
            $errors), 0), 'sel' => 0, 'by' => 'no streams');
    }
    // Read again: the page may have saved while the source answered; the profile there as it
    // was, else nothing changes.
    $s = aio_settings_read($dir, $why);
    $k2 = aio_profile_find($s, $name);
    if ($why !== '' || !$s['src_profiles'] || $k2 < 0 || $s['profiles'][$k2] !== $p)
    {
        Aio::$settings = $old;
        aio_log("$tag: " . ($why !== '' ? "settings.json $why" : 'the profile changed meanwhile') .
            ', the source and the screen kept');
        return aio_gc_src_dialog($title, $st['lang'], array('error' => $why !== '' ? 'err_quick_read' : 'src_changed',
            'detail' => ''));
    }
    $s = aio_profile_apply($s, $p);
    if (!aio_settings_write($s))
    {
        Aio::$settings = $old;
        aio_log("$tag: settings.json not written, the source and the screen kept");
        return aio_gc_src_dialog($title, $st['lang'], array('error' => 'err_setup_write', 'detail' => ''));
    }
    Aio::$settings = aio_op_settings($s);
    $ns = $r['st'];
    aio_log(sprintf('%s, screen replaced, %d -> %d rows, row %d -> %d (%s), %.2f s', $tag, count($st['rows']),
        count($ns['rows']), $cur, $r['sel'], $r['by'], microtime(true) - Aio::$t0));
    return aio_list_replace($ns);
}
