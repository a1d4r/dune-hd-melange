<?php
// The screen of an error and the wait screen of our own (UI kit, stage 6b-2): a GComps folder of the whole screen,
// MelKit::error_screen on the card background of the title or on that of Melange. Where an error has no screen of ours
// under it (over the rows of the catalog, after the player, «no releases» in place of an empty list) and where a wait
// starts from the rows or the card of the Dune (owner 19.11; over a screen of ours: the window of kit_dlg.php).
// Required by main.php after kit_dlg.php. PHP 5.3.6, firmware API only; types (MelKsLook, MelKsScreen):
// phpstan-types.neon.
//
// A screen is opened by plugin_open_folder of "kscr:<id>" (mel_ks_error, mel_ks_wait), its description kept by id in
// MelKs::$scr (memory of php_server; the last MEL_KS_KEEP), so get_folder_view draws it again. The look of both
// (MelKsLook): bg, nl, logo, sub as MelKit::error_screen takes them (mel_ks_look_of: of a tile of the catalog).
// - An error, mel_ks_error($d): its description, those of the own dialog (MelDlgDesc, kit_dlg.php: icon, tone, title,
//   text, buttons 1 to 3 with act, focus, cancel: RETURN) and detail, the small line under the buttons. LEFT and RIGHT
//   move the focus (no wrap); ENTER leaves the screen for the act of the button in focus, RETURN for cancel (none:
//   back where it came from, the rows, the card, the list). The three cases: mel_ks_rows_error (over the rows),
//   mel_ks_player_error (pe_stop of the player with its code), mel_ks_no_releases (in place of an empty list).
// - A wait, mel_ks_wait($w): its description that of the wait window (MelWaitDesc, kit_dlg.php) and the same ticks
//   (mel_wait_new, mel_wait_step): its timer MEL_KS_TICK_MS, the first tick at once. A tick replies post: the screen
//   leaves for it (the list of releases, the error screen); dialog is not for a screen (logged, back); else its
//   map and timer again, then the blocks that changed. A button (ENTER, or RETURN on its cancel) leaves for its act.
// To leave (mel_ks_leave): plugin_open_folder becomes plugin_replace_path of the same screen (Back from it goes where
// the error or the wait came from, not to them), plugin_replace_path stays, anything else (none too) runs after
// close_and_run. The screen ends then: a key or a tick that comes after (sent before) gets no reply, its get_folder_view
// is «outdated». A key of an id not kept (a php_server restart): close_and_run, logged.

// The prefix of the ids of the components (MelKit::error_screen) and of the media_url of a screen.
define('MEL_KS', 'ks');
define('MEL_KS_URL', 'kscr:');
// The ticks of a wait screen, ms: the first at once, the next as soon as the work of one is done (lead 19.11).
define('MEL_KS_TICK_MS', 100);
// Screens kept by id: the one on screen and those under it a key or Back may come to.
define('MEL_KS_KEEP', 8);

class MelKs
{
    // The screens by id (MelKsScreen): look, detail, dlg (an error) or wt (a wait), end (null live, else the id of the
    // button that left it, 'done' of a wait done, '' RETURN with no button).
    /** @var array<string, MelKsScreen> */
    public static $scr = array();
}

// The look of a screen of description $d: bg ('' that of Melange), nl, logo, sub.
/**
 * @param array<string, mixed> $d
 * @return MelKsLook
 */
function mel_ks_look($d)
{
    return array('bg' => mel_dlg_str($d, 'bg'), 'nl' => isset($d['nl']) && is_string($d['nl']) ? $d['nl'] : null,
        'logo' => !empty($d['logo']), 'sub' => mel_dlg_str($d, 'sub'));
}

// The look of the title of tile $t (MelCatTile): its card background of the server with the logo, as the own card
// (mel_catv_card); none or no logo: the head of Melange with its name.
/**
 * @param MelCatTile $t
 * @return MelKsLook
 */
function mel_ks_look_of($t)
{
    $c = mel_catv_card($t, true);
    return array('bg' => $c['bg'], 'nl' => $c['nl'], 'logo' => $c['logo'], 'sub' => mel_clean($t['name']));
}

// Keep screen $s under a new id, the oldest beyond MEL_KS_KEEP gone -> plugin_open_folder of it, $caption (the path).
/**
 * @param MelKsScreen $s
 * @param string $caption
 * @return MelAction
 */
function mel_ks_put($s, $caption)
{
    // Not all digits: a key of digits would be an int, renumbered by array_shift.
    $id = 's' . mel_rid();
    MelKs::$scr[$id] = $s;
    while (count(MelKs::$scr) > MEL_KS_KEEP)
        array_shift(MelKs::$scr);
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => MEL_KS_URL . $id,
            PluginOpenFolderActionData::caption => $caption));
}

// The error screen of description $d (see above) -> plugin_open_folder of it.
/**
 * @param array<string, mixed> $d
 * @param string $caption
 * @return MelAction
 */
function mel_ks_error($d, $caption = '')
{
    $bs = mel_dlg_buttons(isset($d['buttons']) ? $d['buttons'] : null, 'error screen');
    $dlg = array('icon' => mel_dlg_str($d, 'icon'), 'tone' => mel_dlg_str($d, 'tone'), 'title' => mel_dlg_str($d, 'title'),
        'text' => mel_dlg_str($d, 'text'), 'buttons' => $bs,
        'cur' => mel_dlg_focus(isset($d['focus']) ? $d['focus'] : 0, count($bs)),
        'cancel' => isset($d['cancel']) && is_array($d['cancel']) ? $d['cancel'] : null);
    mel_log('kit error screen: «' . $dlg['title'] . '», ' . count($bs) . ' buttons');
    return mel_ks_put(array('look' => mel_ks_look($d), 'detail' => mel_dlg_str($d, 'detail'), 'dlg' => $dlg, 'wt' => null,
        'end' => null), $caption !== '' ? $caption : $dlg['title']);
}

// The wait screen of description $w (see above) -> plugin_open_folder of it. No function tick: null, logged.
/**
 * @param array<string, mixed> $w
 * @param string $caption
 * @return MelAction|null
 */
function mel_ks_wait($w, $caption = '')
{
    $s = mel_wait_new($w, MEL_KS_TICK_MS);
    if ($s === null)
        return null;
    mel_log('kit wait screen: «' . $s['title'] . "», tick {$s['tick']}");
    return mel_ks_put(array('look' => mel_ks_look($w), 'detail' => '', 'dlg' => null, 'wt' => $s, 'end' => null),
        $caption !== '' ? $caption : $s['title']);
}

// --- The three cases of an error with no screen of ours under it. $lang: of the texts (mel_tr).

// Over the rows of the catalog, from their handle_user_input (in place of PLUGIN_SHOW_ERROR, owner 18.11): the
// background and the head of Melange with $sub («Каталог», «Поиск»), the icon triangle-alert in warn, title $title,
// text $text, the buttons of $d['buttons'] then «Назад»; $d may give icon and tone too («Ничего не найдено»: search-x
// in text 2). RETURN and «Назад»: back to the rows.
/**
 * @param string $lang
 * @param string $sub
 * @param string $title
 * @param string $text
 * @param array<string, mixed> $d
 * @return MelAction
 */
function mel_ks_rows_error($lang, $sub, $title, $text, $d = array())
{
    $bs = isset($d['buttons']) && is_array($d['buttons']) ? $d['buttons'] : array();
    $bs[] = array('label' => mel_tr($lang, 'ks_back'), 'icon' => 'k_icon_arrow-left');
    return mel_ks_error(array_merge(array('icon' => 'k_icon_triangle-alert', 'tone' => 'warn'), $d,
        array('bg' => '', 'sub' => $sub, 'title' => $title, 'text' => $text, 'buttons' => $bs)));
}

// After the player, from its event pe_stop with playback_error_code $code (plugin_open_folder there opens the screen
// at once, with no window of the shell [device, ui_kit_probe 6a]): the look $look of the title (mel_ks_look_of),
// circle-x in err, «Видео не открылось», «Другая раздача» ($other; null: back to the list), «Повторить» ($retry),
// the detail «Код ошибки плеера: N». RETURN: back to the list.
/**
 * @param string $lang
 * @param MelKsLook $look
 * @param int $code
 * @param MelAction|null $other
 * @param MelAction|null $retry
 * @return MelAction
 */
function mel_ks_player_error($lang, $look, $code, $other, $retry)
{
    return mel_ks_error(array_merge($look, array('icon' => 'k_icon_circle-x', 'tone' => 'err',
        'title' => mel_tr($lang, 'ks_player'), 'text' => mel_tr($lang, 'ks_player_text'),
        'detail' => str_replace('{code}', strval($code), mel_tr($lang, 'ks_player_code')),
        'buttons' => array(array('label' => mel_tr($lang, 'ks_other_release'), 'icon' => 'k_icon_list', 'act' => $other),
            array('label' => mel_tr($lang, 'ks_retry'), 'icon' => 'k_icon_rotate-ccw', 'act' => $retry)))));
}

// «No releases», in place of an empty list (or the post of a wait screen): the look $look of the title, search-x in
// text 2, «Раздач нет», text $text (what found none and what to do), buttons $buttons («Искать онлайн», «Откуда
// брать», …); none: «Назад» alone. RETURN: back where it came from.
/**
 * @param string $lang
 * @param MelKsLook $look
 * @param string $text
 * @param list<array<string, mixed>> $buttons
 * @return MelAction
 */
function mel_ks_no_releases($lang, $look, $text, $buttons = array())
{
    if (!$buttons)
        $buttons = array(array('label' => mel_tr($lang, 'ks_back'), 'icon' => 'k_icon_arrow-left'));
    return mel_ks_error(array_merge($look, array('icon' => 'k_icon_search-x', 'title' => mel_tr($lang, 'lp_no_releases'),
        'text' => $text, 'buttons' => $buttons)));
}

// --- The folder.

// The components of screen $s: MelKit::error_screen of its look and of its error or wait.
/**
 * @param MelKsScreen $s
 * @return list<MelGcComp>
 */
function mel_ks_defs($s)
{
    if ($s['wt'] !== null)
    {
        $w = $s['wt'];
        $v = array('icon' => $w['icon'], 'tone' => $w['tone'], 'title' => $w['title'], 'text' => $w['text'],
            'live' => mel_wait_live($w), 'share' => $w['share'], 'buttons' => mel_dlg_btn_views($w));
    }
    else
    {
        $e = $s['dlg'];
        $v = $e === null ? array() : array('icon' => $e['icon'], 'tone' => $e['tone'], 'title' => $e['title'],
            'text' => $e['text'], 'detail' => $s['detail'], 'buttons' => mel_dlg_btn_views($e));
    }
    $k = MelKit::error_screen(array_merge($s['look'], $v, array('id' => MEL_KS)));
    return $k['defs'];
}

// The action map of screen $id: LEFT, RIGHT, ENTER, RETURN (ks_key); of a wait its tick too (ks_tick,
// show_dialog_delay MEL_WAIT_DELAY over any tick, as the wait window).
/**
 * @param string $id
 * @param bool $wait
 * @return array<string, MelAction>
 */
function mel_ks_map($id, $wait)
{
    $map = mel_dlg_map('ks_key', $id);
    if ($wait)
        $map[GUI_EVENT_TIMER] = mel_input('ks_tick', array('o' => $id), MEL_WAIT_DELAY);
    return $map;
}

// get_folder_view of "kscr:<id>": the screen kept, its map, of a wait its timer (the first tick at once). Not kept or
// ended: «outdated».
/**
 * @param string $media_url
 * @return array<string, mixed>
 */
function mel_ks_folder_view($media_url)
{
    $id = strval(substr($media_url, strlen(MEL_KS_URL)));
    if (!isset(MelKs::$scr[$id]) || MelKs::$scr[$id]['end'] !== null)
    {
        mel_log("kit screen $id: not kept or ended, outdated");
        return mel_lines_view(array(mel_expired_item()));
    }
    $s = MelKs::$scr[$id];
    $wait = $s['wt'] !== null;
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => array(
                GCompWindowDef::background_color => 'rgb(0,0,0)',
                GCompWindowDef::background_url => null,
                GCompWindowDef::async_loading_background => true,
                GCompWindowDef::comp_defs => mel_ks_defs($s),
                GCompWindowDef::ui_state => null,
                GCompWindowDef::not_loaded_background_url => null,
                GCompWindowDef::playback_bg_alpha => -1,
                GCompWindowDef::background_fit_def => array(
                    ImageFitDef::base_halign_ratio => 0.5,
                    ImageFitDef::base_valign_ratio => 0.5),
                GCompWindowDef::small_state_text => null,
                GCompWindowDef::opaque_background => false),
            PluginGCompsFolderView::sel_state => null,
            PluginGCompsFolderView::actions => mel_ks_map($id, $wait),
            PluginGCompsFolderView::timer => $wait ? mel_wait_timer(MEL_KS_TICK_MS) : null));
}

// Leave screen $id for $act (see above), its end $end.
/**
 * @param string $id
 * @param string $end
 * @param MelAction|null $act
 * @return MelAction
 */
function mel_ks_leave($id, $end, $act)
{
    MelKs::$scr[$id]['end'] = $end;
    $h = is_array($act) && isset($act[GuiAction::handler_string_id]) ? $act[GuiAction::handler_string_id] : '';
    if ($h === PLUGIN_OPEN_FOLDER_ACTION_ID && isset($act[GuiAction::data][PluginOpenFolderActionData::media_url]))
    {
        $d = $act[GuiAction::data];
        return mel_replace_act((string) $d[PluginOpenFolderActionData::media_url],
            isset($d[PluginOpenFolderActionData::caption]) ? (string) $d[PluginOpenFolderActionData::caption] : '');
    }
    if ($h === PLUGIN_REPLACE_PATH_ACTION_ID)
        return $act;
    return array(GuiAction::handler_string_id => CLOSE_AND_RUN_ACTION_ID,
        GuiAction::data => array(CloseAndRunActionData::post_action => $act));
}

// The reply of a wait screen $id that goes on: its map and its timer again, then $post (null none).
/**
 * @param string $id
 * @param MelAction|null $post
 * @return MelAction
 */
function mel_ks_again($id, $post)
{
    return mel_ovl_behaviour(mel_ks_map($id, true), mel_wait_timer(MelKs::$scr[$id]['wt']['ms']), $post);
}

// A tick of wait screen $id: mel_wait_step, then its reply (see above).
/**
 * @param string $id
 * @return MelAction
 */
function mel_ks_tick($id)
{
    $s = MelKs::$scr[$id];
    /** @var MelWait $w */
    $w = $s['wt'];
    list($now, $r) = mel_wait_step($w);
    if (isset($r['post']))
    {
        mel_log("kit wait screen: done, {$now['sec']} s");
        return mel_ks_leave($id, 'done', $r['post']);
    }
    if (isset($r['dialog']))
    {
        mel_log("kit wait screen: a dialog of the tick is not for a screen, back, {$now['sec']} s");
        return mel_ks_leave($id, '', null);
    }
    MelKs::$scr[$id]['wt'] = $now;
    $ch = mel_dlg_diff(mel_ks_defs($s), mel_ks_defs(MelKs::$scr[$id]));
    return mel_ks_again($id, $ch ? mel_ovl_change($ch, null) : null);
}

// Key $k of screen $id -> the reply, null none: left, right the focus a button, at the first and the last none; enter
// leaves for the act of the button in focus; return for the cancel of an error, of a wait for its button cancel (as
// enter on it; none: back). A key of a wait goes on with its map and timer.
/**
 * @param string $id
 * @param string $k
 * @return MelAction|null
 */
function mel_ks_key($id, $k)
{
    $s = MelKs::$scr[$id];
    $kind = $s['wt'] !== null ? 'wt' : 'dlg';
    /** @var MelDlg|MelWait $v */
    $v = $s[$kind];
    $n = count($v['buttons']);
    if ($k === 'left' || $k === 'right')
    {
        $to = $v['cur'] + ($k === 'left' ? -1 : 1);
        if ($to < 0 || $to >= $n)
            return null;
        MelKs::$scr[$id][$kind]['cur'] = $to;
        $ch = mel_ovl_change(mel_dlg_diff(mel_ks_defs($s), mel_ks_defs(MelKs::$scr[$id])), null);
        return $kind === 'wt' ? mel_ks_again($id, $ch) : $ch;
    }
    $b = null;
    if ($k === 'enter' && $n > 0)
        $b = $v['buttons'][$v['cur']];
    elseif ($k === 'return' && $kind === 'wt')
    {
        foreach ($v['buttons'] as $c)
        {
            if ($c['cancel'] && $b === null)
                $b = $c;
        }
    }
    elseif ($k !== 'return')
        return null;
    mel_log("kit screen $id: $k" . ($b !== null ? " on {$b['id']}" : '') . ', left');
    $cancel = $kind === 'dlg' && isset($v['cancel']) ? $v['cancel'] : null;
    return mel_ks_leave($id, $b !== null ? $b['id'] : '', $b !== null ? $b['act'] : $cancel);
}

// ks_key, ks_tick: a key or a tick of screen o. Not kept (a php_server restart): close_and_run, logged; ended (a key
// or a tick sent before it left): none.
/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_ks_input($in, $control)
{
    $id = isset($in->o) && is_string($in->o) ? $in->o : '';
    if (!isset(MelKs::$scr[$id]))
    {
        mel_log("kit screen $id: $control of a screen not kept, closed");
        return array(GuiAction::handler_string_id => CLOSE_AND_RUN_ACTION_ID,
            GuiAction::data => array(CloseAndRunActionData::post_action => null));
    }
    if (MelKs::$scr[$id]['end'] !== null)
    {
        mel_log("kit screen $id: $control after it left, none");
        return null;
    }
    if ($control === 'ks_tick')
        return MelKs::$scr[$id]['wt'] !== null ? mel_ks_tick($id) : null;
    return mel_ks_key($id, isset($in->k) && is_string($in->k) ? $in->k : '');
}
