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

// Profile $k of the settings $s (aio_profile_find; -1: none) can be switched to: the setting
// on, the profile there and ready.
/**
 * @param AioSettings $s
 * @param int $k
 * @return bool
 */
function aio_gc_src_usable($s, $k)
{
    return $s['src_profiles'] && $k >= 0 && aio_profile_ready($s, $s['profiles'][$k]);
}

// After the request to the source of profile $p (aio_gc_src): settings.json read again (the
// page may have saved while the source answered), the profile applied and written,
// Aio::$settings of it -> ''. Not written -> the key of the error, Aio::$settings not
// touched (the one rollback, to the settings before the request, is in aio_gc_src): the
// file not read (err_quick_read), not written (err_setup_write); the page changed meanwhile
// the profile (gone, another, not ready, the setting off) or the source of the settings
// $s0 of the first read (src_changed).
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
        foreach (array('source', 'aio_server', 'aio_own_url', 'aio_mode', 'manifest_url') as $f)
        {
            if ($s[$f] !== $s0[$f])
                $err = array('src_changed', 'the source changed meanwhile');
        }
    }
    if ($err[0] === '')
    {
        $s = aio_profile_apply($s, $p);
        if (aio_settings_write($s))
        {
            Aio::$settings = aio_op_settings($s);
            return '';
        }
        $err = array('err_setup_write', 'settings.json not written');
    }
    aio_log("$tag: {$err[1]}, the source and the screen kept");
    return $err[0];
}

// gc_src (n: the name of a profile, MENU "Откуда брать") of a live list: the list of its
// movie or episode from the source of the profile first (aio_relist with the settings of
// the profile). It answered, with no streams too (an empty list), but not with error
// streams only (a link not taken): written (aio_gc_src_commit), the screen replaced, the
// cursor on the same release. An error, not written: its dialog - the source, the screen
// and the state as they were. The profile gone, not ready, the setting off, settings.json
// not read: a dialog too, no request. The profile in use, a list not live: nothing.
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
    if (isset($r['error']))
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
    Aio::$settings = $old;
    return aio_gc_src_dialog($title, $st['lang'], $r);
}
