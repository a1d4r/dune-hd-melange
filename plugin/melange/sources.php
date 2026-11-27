<?php
// "Источник" in MENU of a list (v2, spec source-profiles-v2 §2.2): the sources (profiles of settings.json) ready,
// in their order, by their id. A choice shows this title from that source on this screen only: nothing written, nothing
// remembered - the next opening goes by the history or the main one (§1.5); what is started from the screen takes its
// profile into the record (wh_prof). The screen keeps two lists (view_online.php): the releases of one profile of
// releases (rows, prof) and the online list of one profile of Lampac (lp, lp_prof); a choice of one of them already
// there - no request. The item of MENU: an item of the panel "Действия" of both lists (mel_src_item; mel_gc_actions of
// view_gcomps.php, mel_lpv_actions of view_online.php). The name of the source over the lists: mel_gc_tabs_from
// (view_gcomps.php).
// Reads and writes Mel (state, settings). Functions are global; the require order is in main.php.

// The id of the profile list $st shows: of Lampac on the online list, else of releases ('' none).
/**
 * @param MelState $st
 * @return string
 */
function mel_src_now($st)
{
    return !empty($st['lp_on']) ? $st['lp_prof'] : $st['prof'];
}

// The ready profiles of settings.json of the operation (Mel::$settings profiles) in their order; $lp_only - of Lampac
// only (a title of no IMDb and no TMDB has no releases, mel_rel_id; owner 19.11.2026).
/**
 * @param bool $lp_only
 * @return list<array{id: string, name: string, kind: string, ready: bool}>
 */
function mel_src_ready($lp_only = false)
{
    $r = array();
    foreach (Mel::$settings['profiles'] as $p)
    {
        if ($p['ready'] && (!$lp_only || $p['kind'] === 'lampac'))
            $r[] = $p;
    }
    return $r;
}

// "Источник" in MENU of a list: two ready profiles or more, of any kind ($lp_only: of Lampac).
/**
 * @param bool $lp_only
 * @return bool
 */
function mel_src_in_menu($lp_only = false)
{
    return count(mel_src_ready($lp_only)) >= 2;
}

// The submenu "Источник" of a side panel (mel_src_item), as mel_src_popup of a popup menu: the ready profiles in their
// order ($lp_only: those of Lampac, mel_src_ready), each by its name with the icon of its kind (mel_profile_kind), to
// $control of $params with p (its id) and n (its name for the dialog); the one of id $now picked ('' none); under the
// lines where the list is set. Fewer than two ready (mel_src_in_menu): null.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @param string $now
 * @param bool $lp_only
 * @return MelKpSubDesc|null
 */
function mel_src_panel($lang, $control, $params, $now = '', $lp_only = false)
{
    if (!mel_src_in_menu($lp_only))
        return null;
    $icons = array('debrid' => 'k_icon_zap', 'p2p' => 'k_icon_magnet', 'own' => 'k_icon_layers', 'lampac' => 'k_icon_tv');
    $items = array();
    foreach (mel_src_ready($lp_only) as $p)
        $items[] = array('id' => 'src_' . $p['id'], 'label' => $p['name'],
            'icon' => isset($icons[$p['kind']]) ? $icons[$p['kind']] : 'k_icon_layers', 'picked' => $p['id'] === $now,
            'act' => mel_input($control, array_merge($params, array('p' => $p['id'], 'n' => $p['name']))));
    return array('title' => mel_tr($lang, 'src_list'), 'groups' => array($items), 'note' => mel_tr($lang, 'src_note'));
}

// The item "Источник" of a side panel: its icon, on the right the name of the profile of id $now (cut to what the label
// leaves of the line, MelKit::panel_value), its submenu (mel_src_panel of the same arguments). Fewer than two ready:
// null.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @param string $now
 * @param bool $lp_only
 * @return MelKpItem|null
 */
function mel_src_item($lang, $control, $params, $now = '', $lp_only = false)
{
    $menu = mel_src_panel($lang, $control, $params, $now, $lp_only);
    if (!$menu)
        return null;
    $label = mel_tr($lang, 'src_list');
    return array('id' => 'src', 'label' => $label, 'icon' => 'k_icon_layers',
        'value' => MelKit::panel_value($label, mel_src_name($now)), 'menu' => $menu);
}

// The item of the sources of the panel "Действия" of the own card, to $control of $params with p (the id of a profile)
// and n (its name for the dialog): two ready profiles or more - "Смотреть из" with the submenu of them (mel_src_panel
// under this title; none picked: no list is open yet); one, of a title with a copy on the disk ($lib: "Смотреть" plays
// the file; owner 18.11.2026) - "Смотреть из «<name>»", its list at once; else none.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @param bool $lib
 * @return list<MelKpItem>
 */
function mel_src_watch_kp($lang, $control, $params, $lib)
{
    $ready = mel_src_ready();
    if ($lib && count($ready) === 1)
    {
        $p = $ready[0];
        return array(array('id' => 'src', 'label' => str_replace('{name}', $p['name'], mel_tr($lang, 'src_watch_one')),
            'icon' => 'k_icon_layers',
            'act' => mel_input($control, array_merge($params, array('p' => $p['id'], 'n' => $p['name'])))));
    }
    $menu = mel_src_panel($lang, $control, $params);
    if (!$menu)
        return array();
    $menu['title'] = mel_tr($lang, 'src_watch');
    return array(array('id' => 'src', 'label' => $menu['title'], 'icon' => 'k_icon_layers', 'menu' => $menu));
}

// The popup menu of the ready profiles in their order, Lampac with its own icon, each to $control of $params with p (its
// id) and n (its name for the dialog); the one of id $now "<name> · сейчас" and selected ('' none: the first selected);
// $lp_only - those of Lampac only (mel_src_ready). None ready: null.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @param string $now
 * @param bool $lp_only
 * @return MelAction|null
 */
function mel_src_popup($lang, $control, $params, $now = '', $lp_only = false)
{
    $items = array();
    $sel = 0;
    foreach (mel_src_ready($lp_only) as $p)
    {
        if ($p['id'] === $now)
            $sel = count($items);
        $items[] = array(
            GuiMenuItemDef::caption => $p['id'] === $now ? str_replace('{name}', $p['name'], mel_tr($lang, 'src_now')) :
                $p['name'],
            GuiMenuItemDef::icon_url => $p['kind'] === 'lampac' ? 'gui_skin://small_icons/web_browser.aai' :
                'gui_skin://small_icons/sources.aai',
            GuiMenuItemDef::action => mel_input($control, array_merge($params, array('p' => $p['id'], 'n' => $p['name']))));
    }
    if (!$items)
    {
        mel_log('source: no profile ready, nothing');
        return null;
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => $sel));
}

// --- "Смотреть из…" of POP UP of a tile of a title and of "Действия" of the own card (mel_src_watch_kp): the list of the title at once from the source
// chosen (mel_play_action with its profile), not of the record or the main one; nothing remembered, as "Источник" of a list.

// The item "Смотреть из…" to $control of $params (its menu of sources: mel_src_popup), with two ready profiles or more
// (mel_src_in_menu); none else.
/**
 * @param string $lang
 * @param string $control
 * @param array<string, string> $params
 * @return list<array<string, mixed>>
 */
function mel_src_watch_item($lang, $control, $params)
{
    if (!mel_src_in_menu())
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($lang, 'src_watch'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
        GuiMenuItemDef::action => mel_input($control, $params)));
}

// The choice of "Смотреть из…" of input $in (p: the id of a profile, n: its name; of mel_src_popup, mel_src_panel) -> array(the id, ''
// not an id: nothing; the dialog "Источник не сменился: <name>" (src_changed) of a profile gone or not ready since the
// menu (the page changed them), no request; null - there and ready).
/**
 * @param stdClass $in
 * @param string $lang
 * @return array{string, MelAction|null}
 */
function mel_src_watch_pick($in, $lang)
{
    $id = isset($in->p) && is_string($in->p) ? $in->p : '';
    if (!mel_profile_id_ok($id))
    {
        mel_log('source: watch from: no profile, nothing');
        return array('', null);
    }
    $s = Mel::$file;
    $k = $s ? mel_profile_index($s, $id) : -1;
    if ($k >= 0 && mel_profile_ready($s['profiles'][$k]))
        return array($id, null);
    mel_log('source: watch from: no such profile ready, nothing asked');
    $name = isset($in->n) && is_string($in->n) ? $in->n : '';
    return array($id, mel_gc_src_dialog(str_replace('{name}', $name, mel_tr($lang, 'src_failed')), $lang,
        array('error' => 'src_changed', 'detail' => '')));
}

// The dialog of a source that failed (mel_gc_src): $title, the error, its detail, OK. With the reasons of the error
// streams (the setting on; owner 27.11.2026): one - it is the text; two and more with $more (the action of
// "Ошибки аддонов": the panel of them over the list under the window, mel_gc_errs) - the error, OK and that button
// after it; with no $more (nowhere to open the panel) - a line each, a window of the shell (the dialog of ours has one
// text of 3 lines).
/**
 * @param string $title
 * @param string $lang
 * @param array{error: string, detail: string, errors?: MelErrors} $res
 * @param MelAction|null $more
 * @return MelAction
 */
function mel_gc_src_dialog($title, $lang, $res, $more = null)
{
    $why = isset($res['errors']) ? mel_errors_labels($lang, $res['errors']) : array();
    if (!$why)
        return mel_msg($title, array(mel_tr($lang, $res['error']), $res['detail']));
    if (mel_errors_total($res['errors']) === 1)
        return mel_msg($title, array($res['errors']['lines'][0]));
    if ($more)
        return mel_ask($title, array(mel_tr($lang, $res['error'])), 'k_icon_circle-x', 'err', array(mel_btn_ok(),
            mel_btn(mel_tr($lang, 'errors_menu'), 'k_icon_triangle-alert', $more)));
    return mel_dialog($title, array_merge(mel_dialog_lines(mel_tr($lang, $res['error'])), mel_dialog_lines($res['detail']),
        $why), array('OK' => mel_close_and(null)));
}

// gc_src_errs ("Ошибки аддонов" of the dialog of a source that failed, mel_gc_src_dialog) of a live list: the panel of the
// reasons of that reply (kept with the list, src_errors) over it - the one of "Ошибки аддонов" of "Действия", itself
// the panel. A list not live, no reasons kept: nothing; an overlay open over the list (a dialog, a panel, a wait - the
// button sends it after the close of its dialog alone): nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_errs($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !isset($st['src_errors']))
    {
        mel_log('source: errors: not a live list or no reasons kept, nothing');
        return null;
    }
    if (mel_ovl_open_on($st['rid']))
    {
        mel_log('source: errors: an overlay open over the list, nothing');
        return null;
    }
    list($scr) = mel_gc_scr_live($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    return mel_kp_open($scr, mel_errors_panel(mel_tr($st['lang'], 'errors_menu'), $st['lang'], $st['src_errors']));
}

// The action of gc_src_menu of live list $st, «Другой источник» of a window over it (mel_gc_src_menu); no submenu
// "Источник" of it (fewer than two profiles ready, mel_src_panel): null.
/**
 * @param MelState $st
 * @return MelAction|null
 */
function mel_gc_src_menu_act($st)
{
    return mel_src_in_menu(mel_rel_id($st['movie']) === '') ? mel_gc_act('gc_src_menu', $st['rid']) : null;
}

// gc_src_menu («Другой источник» of the window of a search with nothing found, mel_lpv_result) of a live list: the
// submenu "Источник" of its "Действия" (mel_src_panel, as mel_src_item of mel_gc_actions and mel_lpv_actions has it)
// over it, itself the panel. A list not live, no such submenu: nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_src_menu($in)
{
    $st = Mel::$state;
    $d = $st && isset($in->rid) && $in->rid === $st['rid'] ? mel_src_panel($st['lang'], 'gc_src', array('rid' => $st['rid']),
        mel_src_now($st), mel_rel_id($st['movie']) === '') : null;
    if (!$st || !$d)
    {
        mel_log('source: menu: not a live list or no choice of sources, nothing');
        return null;
    }
    list($scr) = mel_gc_scr_live($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null, true);
    return mel_kp_open($scr, $d);
}

// gc_src (p: the id of a profile, n: its name for the dialog; MENU "Источник") of a live list: this title from
// that profile on this screen only (spec v2 §2.2, nothing written). The one of the screen: nothing; of the releases
// with the yellow line - the line gone. A profile of releases: mel_src_releases; of Lampac: mel_src_lampac. The profile
// gone or not ready (the page changed them since the menu): the dialog "Источник не сменился: <name>" (src_changed), no
// request. A list not live, no id: nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_src($in)
{
    $st = Mel::$state;
    $id = isset($in->p) && is_string($in->p) ? $in->p : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !mel_profile_id_ok($id))
    {
        mel_log('source: not a live list or no profile, nothing');
        return null;
    }
    $name = isset($in->n) && is_string($in->n) ? $in->n : '';
    $title = str_replace('{name}', $name, mel_tr($st['lang'], 'src_failed'));
    $s = Mel::$file;
    $k = $s ? mel_profile_index($s, $id) : -1;
    $now = $s ? mel_profile_index($s, mel_src_now($st)) : -1;
    $tag = 'source: profile ' . mel_profile_tag($now, $s && $now >= 0 ? $s['profiles'][$now] : null) . ' -> ' .
        mel_profile_tag($k, $s && $k >= 0 ? $s['profiles'][$k] : null) . ', ' . mel_ep_tag($st);
    if (!$s || $k < 0 || !mel_profile_ready($s['profiles'][$k]))
    {
        mel_log("$tag: no such profile ready, the screen kept");
        return mel_gc_src_dialog($title, $st['lang'], array('error' => 'src_changed', 'detail' => ''));
    }
    if ($id === mel_src_now($st))
    {
        if (empty($st['lp_on']) && !empty($st['lp_note']))
        {
            $ns = $st;
            $ns['rid'] = mel_rid();
            unset($ns['lp_note']);
            mel_log("$tag: in use, the yellow line gone");
            return mel_list_replace($ns);
        }
        mel_log("$tag: in use, nothing");
        return null;
    }
    if (!mel_profile_rel($s['profiles'][$k]))
    {
        list($scr) = mel_gc_scr_live($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
        // "Повторить" of its window of no answer: this gc_src again.
        return mel_src_lampac($st, $id, $tag, $scr, mel_gc_act('gc_src', $st['rid'], array('p' => $id, 'n' => $name)));
    }
    // No IMDb and no TMDB: no releases (the menu has none of them; one of an old menu - the message).
    if (mel_rel_id($st['movie']) === '')
    {
        mel_log("$tag: no IMDb and no TMDB, no releases");
        return mel_error('err_no_imdb_rel');
    }
    // "Ошибки аддонов" of the dialog of its error: with the list itself under that dialog (the panel opens over it).
    return mel_src_releases($st, $id, $tag, $title, isset($in->parent_sel_state) ? $in->parent_sel_state : null,
        mel_scr_of($in, 'gc_src') ? mel_gc_act('gc_src_errs', $st['rid']) : null);
}

// The releases of profile $id for list $st (mel_gc_src; $tag of the log, $title of the dialog of an error, $sel_state
// of the shell - the cursor of the releases). Its releases there (the screen shows its online list over them): back to
// them, no request, the cursor where it was (the same list, the same order). Else asked with its settings (mel_relist;
// the log masked by them too), the online list of the screen kept as its second one (of the same Lampac only), the
// screen replaced, the cursor on the line a list opens on (another source sorts the releases its own way: the one under
// the cursor would be lower), the group of the tier folded or not as on the screen (rows of the tier only: unfolded);
// no streams - an empty list; an error - its dialog, the screen, the state and the settings
// of the operation kept (mel_op_apply), the reasons of its error streams, when shown (mel_errors_shown), with the list
// (src_errors: of this error alone, those of one before gone) for $errs - the action of "Ошибки аддонов" of that
// dialog (null: no such button; how many reasons make the button: mel_gc_src_dialog alone).
/**
 * @param MelState $st
 * @param string $id
 * @param string $tag
 * @param string $title
 * @param mixed $sel_state
 * @param MelAction|null $errs
 * @return MelAction
 */
function mel_src_releases($st, $id, $tag, $title, $sel_state, $errs = null)
{
    if ($st['prof'] === $id && empty($st['lp_only']))
    {
        $ns = $st;
        $ns['rid'] = mel_rid();
        unset($ns['lp_on']);
        // Their own cursor, not the sel_state of the online list.
        $ns['gc_new'] = true;
        mel_log("$tag: its releases again, " . count($ns['rows']) . ' rows, no request');
        return mel_list_replace($ns);
    }
    // The online list: the cursor of the releases, not of its rows.
    $cur = mel_gc_row_now($st, empty($st['lp_on']) ? $sel_state : null);
    $old = Mel::$settings;
    mel_op_use($id, $st['lp_prof']);
    $r = mel_relist($st, $st['s'], $st['e'], '');
    if (!isset($r['error']))
        $r['by'] = 'the line a list opens on';
    else
    {
        $errors = isset($r['errors']) ? $r['errors'] : mel_no_errors();
        if ($r['error'] === 'err_no_streams' && !$errors['lines'])
            $r = array('st' => mel_gc_put_cursor(mel_list_state($st['movie'], $st['s'], $st['e'], array(), $st['lang'],
                $errors), 0, null), 'sel' => 0, 'by' => 'no streams');
        else
        {
            mel_log(sprintf('%s failed: %s, %.2f s, the screen kept', $tag,
                $r['error'] === 'err_no_streams' ? 'error streams only' : $r['error'], microtime(true) - Mel::$t0));
            if ($r['error'] === 'err_no_streams')
                $r['error'] = 'err_sources_failed';
            mel_op_apply($old);
            if (Mel::$state)
            {
                unset(Mel::$state['src_errors']);
                if ($errs && mel_errors_shown($errors))
                    Mel::$state['src_errors'] = $errors;
            }
            return mel_gc_src_dialog($title, $st['lang'], $r, $errs);
        }
    }
    $ns = $r['st'];
    // The online list goes with its Lampac: kept only when the pair kept it (that profile gone - another one now).
    if (isset($st['lp']) && $ns['lp_prof'] === $st['lp_prof'])
        $ns['lp'] = $st['lp'];
    mel_log(sprintf('%s, screen replaced, %d -> %d rows, row %d -> %d (%s), %.2f s', $tag, count($st['rows']),
        count($ns['rows']), $cur, $r['sel'], $r['by'], microtime(true) - Mel::$t0));
    return mel_list_replace($ns);
}

// The online list of the profile of Lampac $id for list $st (mel_gc_src; $tag of the log): its online list there younger
// than MEL_LPV_REFRESH s - back to it, no request; else the window "Ищу в <its source>…" of it over the screen (of record $scr:
// the wait window of ours; the list of that Lampac in its place at the end; its windows of no answer, nothing online
// keep the screen and its profiles; of the window of ours with $retry, the action of its "Повторить", and "Другой
// источник" - mel_lpv_search_open). No online list of this list (a series without its episode): nothing.
/**
 * @param MelState $st
 * @param string $id
 * @param string $tag
 * @param MelOvlScreen|null $scr
 * @param MelAction|null $retry
 * @return MelAction|null
 */
function mel_src_lampac($st, $id, $tag, $scr = null, $retry = null)
{
    $old = Mel::$settings;
    mel_op_use($st['prof'], $id);
    if (!mel_lpv_on($st['movie'], $st['s'], $st['e']))
    {
        mel_op_apply($old);
        mel_log("$tag: no online list of this, nothing");
        return null;
    }
    if ($st['lp_prof'] === $id && isset($st['lp']) && mel_lp_now() - $st['lp']['t'] < MEL_LPV_REFRESH)
    {
        $ns = $st;
        $ns['rid'] = mel_rid();
        $ns['lp_on'] = true;
        // Its own cursor, not the sel_state of the releases it replaces.
        $ns['lp']['gc_new'] = true;
        mel_log("$tag: its online list again, " . count($ns['lp']['rows']) . ' rows, no request');
        return mel_list_replace($ns);
    }
    $ns = $st;
    $ns['lp_prof'] = $id;
    mel_log("$tag: the search");
    return mel_lpv_search_open($ns, 'replace', array('scr' => $scr, 'retry' => $retry, 'other' => mel_gc_src_menu_act($st)));
}

// --- The name of the source over the lists (the releases and the online list, view_online.php).

// The name of the ready profile of id $id of the operation (Mel::$settings profiles), '' none.
/**
 * @param string $id
 * @return string
 */
function mel_src_name($id)
{
    foreach (mel_src_ready() as $p)
    {
        if ($p['id'] === $id)
            return $p['name'];
    }
    return '';
}
