<?php
// "Откуда брать…" in MENU of a list (v2, spec source-profiles-v2 §2.2): the sources (profiles of settings.json) ready,
// in their order, by their id. A choice shows this title from that source on this screen only: nothing written, nothing
// remembered - the next opening goes by the history or the main one (§1.5); what is started from the screen takes its
// profile into the record (wh_prof). The screen keeps two lists (view_online.php): the releases of one profile of
// releases (rows, prof) and the online list of one profile of Lampac (lp, lp_prof); a choice of one of them already
// there - no request. The item of MENU: mel_gc_src_item (view_gcomps.php), mel_lpv_menu (view_online.php). The name
// of the source over the lists: mel_src_tabs_right. The line of a copy of the media library in the menu of the copies:
// mel_lib_label.
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

// "Откуда брать…" in MENU of a list: two ready profiles or more, of any kind ($lp_only: of Lampac).
/**
 * @param bool $lp_only
 * @return bool
 */
function mel_src_in_menu($lp_only = false)
{
    return count(mel_src_ready($lp_only)) >= 2;
}

// gc_src_menu (MENU "Откуда брать…") of a live list $in->rid -> the popup menu of the ready profiles in their order, the
// one of the screen (mel_src_now) "<name> · сейчас" and selected (marked of the API draws nothing in a popup menu on
// r24); Lampac with its own icon.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_src_menu($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('source: menu of no live list');
        return mel_error('err_list_expired');
    }
    return mel_src_popup($st['lang'], 'gc_src', array('rid' => $st['rid']), mel_src_now($st), mel_rel_id($st['movie']) === '');
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

// --- "Смотреть из…" of POP UP of a tile of a title and of the own card: the list of the title at once from the source
// chosen (mel_play_action with its profile), not of the record or the main one; nothing remembered, as "Откуда брать…".

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

// The choice of "Смотреть из…" of input $in (p: the id of a profile, n: its name; of mel_src_popup) -> array(the id, ''
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

// --- The line of a copy of the media library of the Dune (mel_lib_copies, library.php) in the menu of the copies.

// Characters of the path in a line of the menu of the copies (mel_lib_label).
define('MEL_LIB_LABEL_MAX', 60);

// The length of the common start of two paths, whole parts only (PathUtils::get_common_path_prefix_len of the shell).
/**
 * @param string $a
 * @param string $b
 * @return int
 */
function mel_lib_prefix_len($a, $b)
{
    $la = strlen($a);
    $lb = strlen($b);
    if ($la === 0 || $lb === 0 || $a[0] !== $b[0])
        return 0;
    $n = $a[0] === '/' ? 1 : 0;
    for (;;)
    {
        $na = strpos($a, '/', $n);
        $nb = strpos($b, '/', $n);
        if ($na === false || $na !== $nb || substr($a, $n, $na - $n) !== substr($b, $n, $nb - $n) ||
            $na + 1 >= $la || $na + 1 >= $lb)
            break;
        $n = $na + 1;
    }
    return $n;
}

// The length of the start of the paths the screen "Files" of the shell cuts off (PathUtils::get_common_file_prefix_len):
// their common start; of one path, all but its file and its folder.
/**
 * @param list<string> $paths
 * @return int
 */
function mel_lib_common_len($paths)
{
    if (!$paths)
        return 0;
    if (count($paths) === 1)
    {
        $p = $paths[0];
        $pos = strrpos($p, '/');
        if (!$pos)
            return 0;
        $pos = strrpos($p, '/', -(strlen($p) - $pos + 1));
        return $pos === false ? 0 : $pos + 1;
    }
    $n = mel_lib_prefix_len($paths[0], $paths[1]);
    for ($i = 2; $i < count($paths); $i++)
        $n = min($n, mel_lib_prefix_len($paths[0], $paths[$i]));
    return $n;
}

// $s of over $max characters: its middle replaced by '…' (the start of the path and the end of the name stay).
/**
 * @param string $s
 * @param int $max
 * @return string
 */
function mel_lib_cut($s, $max)
{
    if (mb_strlen($s, 'UTF-8') <= $max)
        return $s;
    $tail = intval(ceil(($max - 1) / 2));
    return mb_substr($s, 0, $max - 1 - $tail, 'UTF-8') . '…' . mb_substr($s, -$tail, $tail, 'UTF-8');
}

// The line of the copy $copy (one of $copies) in the menu of the copies: "4K · 58.3 ГБ · <path>" - its resolution
// (res, none left out), the size (none left out), the path as the screen "Files" of the shell shows it: the copies in
// one folder of the library - the path without the start common to them all; in several - "<caption of its
// folder>/<path>"; over MEL_LIB_LABEL_MAX characters cut in the middle. Of the copies alone, nothing else read.
/**
 * @param MelLibCopy $copy
 * @param list<MelLibCopy> $copies
 * @param string $lang
 * @return string
 */
function mel_lib_label($copy, $copies, $lang)
{
    $folders = array($copy['folder'] => 1);
    $paths = array($copy['path']);
    foreach ($copies as $c)
    {
        $folders[$c['folder']] = 1;
        if (!in_array($c['path'], $paths, true))
            $paths[] = $c['path'];
    }
    if (count($folders) > 1)
        $path = $copy['caption'] . '/' . $copy['path'];
    else
        $path = strval(substr($copy['path'], mel_lib_common_len($paths)));
    $parts = array();
    if ($copy['res'] !== '')
        $parts[] = $copy['res'];
    if ($copy['size'] > 0)
        $parts[] = mel_size_str($copy['size'], $lang);
    $parts[] = mel_lib_cut($path, MEL_LIB_LABEL_MAX);
    return implode(' · ', $parts);
}

// The dialog of a source that failed (mel_gc_src): $title, the error, its detail, the
// reasons of the error streams with the setting on, OK.
/**
 * @param string $title
 * @param string $lang
 * @param array{error: string, detail: string, errors?: MelErrors} $res
 * @return MelAction
 */
function mel_gc_src_dialog($title, $lang, $res)
{
    $lines = array_merge(mel_dialog_lines(mel_tr($lang, $res['error'])), mel_dialog_lines($res['detail']));
    if (isset($res['errors']))
        $lines = array_merge($lines, mel_errors_labels($lang, $res['errors']));
    return mel_dialog($title, $lines, array('OK' => mel_close_and(null)));
}

// gc_src (p: the id of a profile, n: its name for the dialog; MENU "Откуда брать…") of a live list: this title from
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
        return mel_src_lampac($st, $id, $tag);
    // No IMDb and no TMDB: no releases (the menu has none of them; one of an old menu - the message).
    if (mel_rel_id($st['movie']) === '')
    {
        mel_log("$tag: no IMDb and no TMDB, no releases");
        return mel_error('err_no_imdb_rel');
    }
    return mel_src_releases($st, $id, $tag, $title, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
}

// The releases of profile $id for list $st (mel_gc_src; $tag of the log, $title of the dialog of an error, $sel_state
// of the shell - the cursor of the releases). Its releases there (the screen shows its online list over them): back to
// them, no request, the cursor where it was (the same list, the same order). Else asked with its settings (mel_relist;
// the log masked by them too), the online list of the screen kept as its second one (of the same Lampac only), the
// screen replaced, the cursor on the first row (another source sorts the releases its own way: the one under the
// cursor would be lower); no streams - an empty list; an error - its dialog, the screen, the state and the settings
// of the operation kept (mel_op_apply).
/**
 * @param MelState $st
 * @param string $id
 * @param string $tag
 * @param string $title
 * @param mixed $sel_state
 * @return MelAction
 */
function mel_src_releases($st, $id, $tag, $title, $sel_state)
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
    list($cur) = mel_gc_pos($st, empty($st['lp_on']) ? $sel_state : null);
    $old = Mel::$settings;
    mel_op_use($id, $st['lp_prof']);
    $r = mel_relist($st, $st['s'], $st['e'], '');
    if (!isset($r['error']))
        $r['by'] = 'first row';
    else
    {
        $errors = isset($r['errors']) ? $r['errors'] : mel_no_errors();
        if ($r['error'] === 'err_no_streams' && !$errors['lines'])
            $r = array('st' => mel_gc_put_cursor(mel_list_state($st['movie'], $st['s'], $st['e'], array(), $st['lang'],
                $errors), 0), 'sel' => 0, 'by' => 'no streams');
        else
        {
            mel_log(sprintf('%s failed: %s, %.2f s, the screen kept', $tag,
                $r['error'] === 'err_no_streams' ? 'error streams only' : $r['error'], microtime(true) - Mel::$t0));
            if ($r['error'] === 'err_no_streams')
                $r['error'] = 'err_sources_failed';
            mel_op_apply($old);
            return mel_gc_src_dialog($title, $st['lang'], $r);
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
// than MEL_LPV_REFRESH s - back to it, no request; else the window "Ищу онлайн…" of it over the screen (the list of that
// Lampac in its place at the end; its windows of no answer, nothing online keep the screen and its profiles). No online
// list of this list (a series without its episode): nothing.
/**
 * @param MelState $st
 * @param string $id
 * @param string $tag
 * @return MelAction|null
 */
function mel_src_lampac($st, $id, $tag)
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
    return mel_lpv_search_open($ns, 'replace');
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

// Children of the name of the source of list $st ("NAS", no word before it) right-aligned to x $right of the row of the
// tabs, with two ready profiles or more (spec v2 §2.2), in $room px at most (the tabs left of it; less than a few letters:
// none). -> array(children, their width; 0 none).
/**
 * @param MelState $st
 * @param int $right
 * @param int $y
 * @param int $room
 * @return array{list<MelGcComp>, int}
 */
function mel_src_tabs_right($st, $right, $y, $room)
{
    $from = mel_src_in_menu() ? mel_src_name(mel_src_now($st)) : '';
    if ($from === '' || $room < 80)
        return array(array(), 0);
    $name = mel_gc_cut_w($from, MEL_GC_HINT_PX, min(300, $room));
    $nw = mel_gc_w($name, MEL_GC_HINT_PX);
    return array(array(mel_gc_cut(null, mel_gc_at($nw, MEL_GC_LABEL_H, $right - $nw, $y + intval((MEL_GC_KEY_H -
        MEL_GC_LABEL_H) / 2)), $name, MEL_GC_HINT_PX, MEL_GC_TEXT2)), $nw);
}
