<?php
// "Озвучка" of the online list of "Онлайн" (view_online.php): the dialog of the shell (edit_list_config) of the filter
// by voices - the red key, "Озвучка: …" of MENU. The model of the filter (mel_lpv_voices, mel_lpv_shown,
// mel_lpv_flt_keep, mel_lpv_flt_name), its tab, its MENU item and the dispatcher of lp_* are in view_online.php.
// PHP 5.3.6, firmware API only. Types (MelLpView, MelState): phpstan-types.neon.

// The id of the dialog of the voices (edit_list_config): its file <FS_PREFIX>/config/lcfg_melange_lp_voices.txt
// (bin/uninstall.sh removes it).
define('MEL_LPV_VOICES_ID', 'melange_lp_voices');

/**
 * @return string
 */
function mel_lpv_flt_path()
{
    return getenv('FS_PREFIX') . '/config/lcfg_' . MEL_LPV_VOICES_ID . '.txt';
}

// The id of voice $k in the dialog of the voices: ASCII (a key has ":" and "|"), not a number.
/**
 * @param string $k
 * @return string
 */
function mel_lpv_flt_id($k)
{
    return 'v' . substr(md5($k), 0, 12);
}

// lp_flt_menu (the red key, MENU "Озвучка: …") of the online list $st: the dialog "Озвучка" of the shell (edit_list_config)
// with a check of each voice (mel_lpv_voices, "LostFilm · 8 балансеров"), those of the filter checked, the cursor on the
// first of them, else on the first. None checked: all (ALLOW_EMPTY; no checked_ids: "Сброс" unchecks all). Its order
// ("Переместить") is not read. Its file written first; not written: an error, no dialog. No close_item_params,
// dialog_params or params of items (as mel_lpv_srcs_edit). No voice: nothing; no such dialog of the firmware: an error.
/**
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_flt_edit($st)
{
    $lang = mel_gc_lang($st);
    $g = mel_lpv_voices($st['lp'], $lang);
    if (!$g)
    {
        mel_log('lampac: voices: none in the list, no dialog');
        return null;
    }
    if (!class_exists('EditListConfigActionData') || !defined('EDIT_LIST_CONFIG_ACTION_ID'))
        return mel_error('cat_rows_no_dialog');
    $ids = array();
    $on = array();
    $items = array();
    foreach ($g as $x)
    {
        $id = mel_lpv_flt_id($x['k']);
        $ids[] = $id;
        if (isset($st['lp']['flt'][$x['k']]))
            $on[$id] = true;
        $items[] = array(GuiItem::id => $id, GuiItem::caption => mel_catv_lcfg_caption(mel_lpv_flt_row($lang, $x['name'], $x['n'])));
    }
    if (!mel_write_file(mel_lpv_flt_path(), mel_catv_lcfg_text($ids, $on, $ids), 0644))
    {
        mel_log('lampac: voices: the file of the dialog not written');
        return mel_error('lp_flt_not_opened');
    }
    $sel = $on ? array_keys($on) : $ids;
    mel_log('lampac: voices: dialog, ' . count($ids) . ' voices, ' . count($on) . ' chosen');
    return array(GuiAction::handler_string_id => EDIT_LIST_CONFIG_ACTION_ID, GuiAction::data => array(
        EditListConfigActionData::config_id => MEL_LPV_VOICES_ID,
        EditListConfigActionData::title => mel_tr($lang, 'lp_flt_title'),
        EditListConfigActionData::all_items => $items,
        EditListConfigActionData::options => EDIT_LIST_CONFIG_OPT_ALLOW_EMPTY,
        EditListConfigActionData::sel_id => strval($sel[0]),
        EditListConfigActionData::post_action => mel_gc_act('lp_flt_apply', $st['rid'])));
}

// lp_flt_apply: post_action of the dialog of the voices of the online list $st, at every close (the shell has written its
// file by then; none: none checked). The voices checked in it (an id of no voice now goes) the filter, none or all of them
// no filter; the same as before: nothing; the file not a file or not read: an error, nothing changed. Else the list with
// it (mel_lpv_flt_set).
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_flt_apply($in, $st)
{
    if (!isset($in->list_config_id) || $in->list_config_id !== MEL_LPV_VOICES_ID)
        return null;
    if (!isset($in->list_config_changed) || !is_scalar($in->list_config_changed) || strval($in->list_config_changed) !== '1')
    {
        mel_log('lampac: voices: dialog closed, no change');
        return null;
    }
    list($text, $no) = mel_catv_lcfg_load(mel_lpv_flt_path());
    if ($no === 'bad' || $no === 'unread')
    {
        mel_log("lampac: voices: the file of the dialog $no, nothing changed");
        return mel_error('lp_flt_not_read');
    }
    $g = mel_lpv_voices($st['lp'], mel_gc_lang($st));
    $ids = array();
    foreach ($g as $x)
        $ids[] = mel_lpv_flt_id($x['k']);
    list($state) = mel_catv_lcfg_read($text, $ids, array_fill_keys($ids, false));
    $set = array();
    foreach ($g as $j => $x)
    {
        if ($state[$ids[$j]])
            $set[$x['k']] = $x['name'];
    }
    if (count($set) === count($g))
    {
        mel_log('lampac: voices: all checked, no filter');
        $set = array();
    }
    $a = array_keys($set);
    $b = isset($st['lp']['flt']) ? array_keys($st['lp']['flt']) : array();
    sort($a);
    sort($b);
    if ($a === $b)
    {
        mel_log('lampac: voices: the same');
        return null;
    }
    return mel_lpv_flt_set($st, $set);
}

// An item of the dialog of the voices: voice $name of $n balancers ("LostFilm · 8 источников").
/**
 * @param string $lang
 * @param string $name
 * @param int $n
 * @return string
 */
function mel_lpv_flt_row($lang, $name, $n)
{
    return mel_lpv_tr($lang, 'lp_flt_item', array('name' => $name, 'n' => $n,
        'sources' => mel_gc_plural($lang, $n, 'lp_sources')));
}

// The online list $st with filter $set (mel_lpv_voices key => name; none: all; some: all the balancers unfolded): the screen
// replaced (a new rid), the yellow line gone; the cursor on its row (on the line of a balancer: of it) when shown, else the
// first.
/**
 * @param MelState $st
 * @param array<string, string> $set
 * @return MelAction
 */
function mel_lpv_flt_set($st, $set)
{
    list($sel) = mel_lpv_pos($st['lp'], null);
    $m = mel_lpv_mark(mel_lpv_lines($st['lp']), $sel);
    $ns = $st;
    $ns['rid'] = mel_rid();
    unset($ns['lp']['flt']);
    // A filter: the rows of its voices shown, all unfolded.
    if ($set)
    {
        $ns['lp']['flt'] = $set;
        $ns['lp']['open'] = mel_lpv_open_all($ns['lp']);
    }
    $ns['lp']['note'] = '';
    unset($ns['lp']['flt_gone']);
    $L = mel_lpv_lines($ns['lp']);
    $j = -1;
    // On the line of a balancer: on its line (the line of its rows shown) when one of them is shown.
    if ($m[1])
    {
        $j = mel_lpv_bal_of($L, max(0, mel_lpv_line_at($ns['lp'], $L, $m[0])));
        $ok = mel_lpv_row_of($L, $j) >= 0 || mel_lpv_is_bal($L, $j);
        if (!$ok || $ns['lp']['rows'][$L[$j]['i']]['path'] !== $st['lp']['rows'][$m[0]]['path'])
            $j = -1;
    }
    else if (in_array($m[0], mel_lpv_shown($ns['lp']), true))
        $j = mel_lpv_line_at($ns['lp'], $L, $m[0]);
    $ns['lp']['gc'] = mel_lpv_put_line($L, max(0, $j));
    $ns['lp']['gc_new'] = true;
    mel_log('lampac: filter ' . ($set ? implode(', ', array_keys($set)) : 'off') . ', ' . count(mel_lpv_shown($ns['lp'])) .
        ' rows shown');
    return mel_list_replace($ns);
}
