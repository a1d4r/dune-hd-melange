<?php
// The rows of a tab in the dialog of the shell (edit_list_config), as "Configure list" of the
// native Movies. Its state is the file <FS_PREFIX>/config/lcfg_<config_id>.txt (list_utils.php):
// "+key"/"-key" against checked_ids, then "---" and an order. cat_rows of settings.json stays the
// choice, the page and the dialog the same: the file is written from it before the dialog opens
// and read back when it closes; only the keys of that tab change.

// The groups of the dialog: those of the page (GROUPS of web/src/rows.ts) in its order, the keys of
// their names; a group of aio_cat_group not here goes to "other".
/**
 * @return array<string, string>
 */
function aio_catv_lcfg_groups()
{
    return array('top' => 'cat_group_top', 'ru' => 'cat_group_ru', 'ru-services' => 'cat_group_ru_services',
        'genres' => 'cat_group_genres', 'theme' => 'cat_group_theme', 'studios' => 'cat_group_studios',
        'decades' => 'cat_group_decades', 'years' => 'cat_group_years', 'streaming' => 'cat_group_streaming',
        'world' => 'cat_group_world', 'runtime' => 'cat_group_runtime', 'anime' => 'cat_group_anime', 'other' => 'cat_group_other');
}

// The group of the row $r in the dialog: its own, if one of aio_catv_lcfg_groups, else "other".
/**
 * @param AioCatRow $r
 * @return string
 */
function aio_catv_lcfg_group($r)
{
    $groups = aio_catv_lcfg_groups();
    return isset($groups[$r['group']]) ? $r['group'] : 'other';
}

// The config_id of the dialog of the tab $tab; bin/uninstall.sh removes their files.
/**
 * @param string $tab
 * @return string
 */
function aio_catv_lcfg_id($tab)
{
    return "melange_rows_$tab";
}

/**
 * @param string $tab
 * @return string
 */
function aio_catv_lcfg_path($tab)
{
    return getenv('FS_PREFIX') . '/config/lcfg_' . aio_catv_lcfg_id($tab) . '.txt';
}

// The rows of the tab $tab of the manifest $rows as the dialog lists them: the chosen ones
// (aio_cat_user_rows) in their order, then the rest in the order of the manifest -> the rows, on: key => chosen.
/**
 * @param list<AioCatRow> $rows
 * @param string $tab
 * @return array{list<AioCatRow>, array<string, bool>}
 */
function aio_catv_lcfg_base($rows, $tab)
{
    $base = array();
    $on = array();
    foreach (aio_cat_user_rows($rows, Aio::$settings['cat_rows'], $tab) as $r)
    {
        $base[] = $r;
        $on[$r['key']] = true;
    }
    foreach ($rows as $r)
    {
        if (aio_cat_row_tab($r) === $tab && !isset($on[$r['key']]))
        {
            $base[] = $r;
            $on[$r['key']] = false;
        }
    }
    return array($base, $on);
}

// The text of the file of the dialog: the state of every row, then the order of $base.
/**
 * @param list<AioCatRow> $base
 * @param array<string, bool> $on
 * @return string
 */
function aio_catv_lcfg_text($base, $on)
{
    $state = '';
    $order = '';
    foreach ($base as $r)
    {
        $state .= ($on[$r['key']] ? '+' : '-') . $r['key'] . "\n";
        $order .= $r['key'] . "\n";
    }
    return $state . "---\n" . $order;
}

// The rows chosen in the file $text of the dialog over $base (aio_catv_lcfg_base), in their new
// order; a row not in its lines: as checked_ids, a start row ("Reset to defaults"). The dialog moves
// a row only within its group, the order of the file may well be by groups: so of a group only its
// own order is taken, its rows go to the places its rows had in $base (a new row: at the end, as on
// the page). Lines not of a row of $base go. -> keys; none chosen: null.
/**
 * @param string $text
 * @param list<AioCatRow> $base
 * @return list<string>|null
 */
function aio_catv_lcfg_parse($text, $base)
{
    $known = array();
    foreach ($base as $r)
        $known[$r['key']] = $r;
    $state = array();
    $rank = array();
    $in_order = false;
    foreach (explode("\n", $text) as $line)
    {
        $line = trim($line);
        if ($line === '')
            continue;
        if ($in_order)
        {
            if (isset($known[$line]) && !isset($rank[$line]))
                $rank[$line] = count($rank);
            continue;
        }
        if (strpos($line, '---') === 0)
        {
            $in_order = true;
            continue;
        }
        $k = strval(substr($line, 1));
        if (($line[0] === '+' || $line[0] === '-') && isset($known[$k]))
            $state[$k] = $line[0] === '+';
    }
    // The new order within each group; no place in the file: after, as in $base.
    $of = array();
    $n = count($rank);
    foreach ($base as $i => $r)
        $of[aio_catv_lcfg_group($r)][$r['key']] = isset($rank[$r['key']]) ? $rank[$r['key']] : $n + $i;
    foreach ($of as $g => $pos)
    {
        asort($pos);
        $of[$g] = array_keys($pos);
    }
    $keys = array();
    foreach ($base as $r)
    {
        $k = array_shift($of[aio_catv_lcfg_group($r)]);
        if (isset($state[$k]) ? $state[$k] : $known[$k]['home'])
            $keys[] = $k;
    }
    return $keys ? $keys : null;
}

// cat_rows $chosen with the keys of the tab $tab replaced by $keys, movies first, then series,
// as the page keeps them (aio_cat_tabs).
/**
 * @param mixed $chosen
 * @param list<string> $keys
 * @param string $tab
 * @return list<string>|null
 */
function aio_catv_rows_merge($chosen, $keys, $tab)
{
    $out = array();
    foreach (aio_cat_tabs() as $t)
    {
        if ($t === $tab)
        {
            $out = array_merge($out, $keys);
            continue;
        }
        foreach (is_array($chosen) ? $chosen : array() as $k)
        {
            if (is_string($k) && strpos($k, '/') !== false && aio_cat_row_tab(array('type' => strval(strstr($k, '/', true)))) === $t)
                $out[] = $k;
        }
    }
    return aio_cat_rows_ok($out);
}

// POP UP of the screen $from. On the header of a row of a main screen (selected_header_id, in the
// panel of the headers; genre_rows_screen.php r25): only "Configure list…", the dialog of the rows of
// its tab, that row selected; on the row "No rows" of the tab too, no row selected. Else (a tile,
// the strip, the results of a search, "Show all") nothing: the search and the settings are on the
// strip and the search key.
/**
 * @param stdClass $in
 * @param string $from
 * @return AioAction|null
 */
function aio_catv_menu($in, $from)
{
    $hid = aio_catv_in($in, 'selected_header_id');
    $tab = aio_catv_tab($from);
    aio_log("catalog: POP UP: header '" . aio_cut($hid, 100) . "'");
    if ($tab === '' || strpos($hid, AIO_CATV_HEADER) !== 0)
        return null;
    $rid = strval(substr($hid, strlen(AIO_CATV_HEADER)));
    $copy = aio_cat_manifest_copy();
    list($base) = aio_catv_lcfg_base($copy['rows'], $tab);
    $sel = null;
    foreach ($base as $r)
    {
        if (aio_catv_rid($r) === $rid)
            $sel = $r['key'];
    }
    $lang = aio_catv_lang();
    if (!$base || ($sel === null && $rid !== aio_catv_rid(aio_catv_tab_empty_row($tab, $lang))))
        return null;
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => array(array(
            GuiMenuItemDef::caption => aio_tr($lang, 'cat_rows_edit'),
            GuiMenuItemDef::action => aio_input('cat_rows_edit', array('from' => $from, 'sel' => strval($sel)))))));
}

// The dialog of the rows of the tab of the main screen $from, the row $sel ('': none) selected. Its
// file written from cat_rows first; not written: an error, no dialog (its close would write the last
// state of the shell over cat_rows of the tab). No close_item_params, dialog_params or params of
// items: r22 has none. No ALLOW_EMPTY: a tab is never empty, as on the page.
/**
 * @param string $from
 * @param string $sel
 * @return AioAction|null
 */
function aio_catv_rows_edit($from, $sel)
{
    $tab = aio_catv_tab($from);
    if ($tab === '')
        return null;
    if (!class_exists('EditListConfigActionData') || !defined('EDIT_LIST_CONFIG_ACTION_ID'))
        return aio_error('cat_rows_no_dialog');
    $copy = aio_cat_manifest_copy();
    list($base, $on) = aio_catv_lcfg_base($copy['rows'], $tab);
    if (!$base)
        return aio_error('cat_outdated');
    if (!aio_write_file(aio_catv_lcfg_path($tab), aio_catv_lcfg_text($base, $on), 0644))
    {
        aio_log("catalog: rows: $tab: the file of the dialog not written");
        return aio_error('cat_rows_not_opened');
    }
    $lang = aio_catv_lang();
    $names = aio_catv_lcfg_groups();
    $items = array();
    $checked = array();
    $used = array();
    foreach ($copy['rows'] as $r)
    {
        if (aio_cat_row_tab($r) !== $tab)
            continue;
        $g = aio_catv_lcfg_group($r);
        $used[$g] = true;
        $items[] = array(GuiItem::id => $r['key'], GuiItem::caption => $r['name'], GuiItem::group_id => $g);
        if ($r['home'])
            $checked[] = $r['key'];
    }
    $groups = array();
    foreach ($names as $g => $key)
    {
        if (isset($used[$g]))
            $groups[] = array(GuiItem::id => $g, GuiItem::caption => aio_tr($lang, $key));
    }
    $data = array(
        EditListConfigActionData::config_id => aio_catv_lcfg_id($tab),
        EditListConfigActionData::title => aio_tr($lang, "cat_rows_title_$tab"),
        EditListConfigActionData::all_items => $items,
        EditListConfigActionData::groups => $groups,
        EditListConfigActionData::post_action => aio_input('cat_rows_apply', array('from' => $from)));
    // checked_ids: what "Reset to defaults" gives back.
    if ($checked)
        $data[EditListConfigActionData::checked_ids] = $checked;
    if (isset($on[$sel]))
        $data[EditListConfigActionData::sel_id] = $sel;
    aio_log("catalog: rows: $tab: dialog, " . count($items) . ' rows, ' . count(array_filter($on)) . ' chosen');
    return array(GuiAction::handler_string_id => EDIT_LIST_CONFIG_ACTION_ID, GuiAction::data => $data);
}

// post_action of the dialog of the main screen $from, at every close; the shell has written its file
// by then. Changed: the rows chosen there go to cat_rows in place of those of the tab, settings.json
// read again and written as the quick setup does (aio_settings_write), the screen read again in place
// (invalidate: the shell keeps the focus by sel_state). Anything else: nothing written.
/**
 * @param stdClass $in
 * @param string $from
 * @return AioAction|null
 */
function aio_catv_rows_apply($in, $from)
{
    $tab = aio_catv_tab($from);
    $changed = isset($in->list_config_changed) && is_scalar($in->list_config_changed) ? strval($in->list_config_changed) : '';
    if ($tab === '' || aio_catv_in($in, 'list_config_id') !== aio_catv_lcfg_id($tab))
        return null;
    if ($changed !== '1')
    {
        aio_log("catalog: rows: $tab: dialog closed, no change");
        return null;
    }
    $path = aio_catv_lcfg_path($tab);
    clearstatcache();
    if (is_link($path) || !is_file($path) || filesize($path) > 65536)
    {
        aio_log("catalog: rows: $tab: no file of the dialog, nothing changed");
        return null;
    }
    $copy = aio_cat_manifest_copy();
    list($base) = aio_catv_lcfg_base($copy['rows'], $tab);
    $keys = aio_catv_lcfg_parse(strval(file_get_contents($path)), $base);
    if (!$keys)
    {
        aio_log("catalog: rows: $tab: none chosen in the file, nothing changed");
        return null;
    }
    $dir = aio_data_dir();
    $why = 'no data_dir';
    $s = $dir !== '' ? aio_settings_read($dir, $why) : null;
    if (!$s || $why !== '')
    {
        aio_log("catalog: rows: $tab: settings.json not read, not written");
        return aio_error('cat_rows_not_saved');
    }
    $rows = aio_catv_rows_merge($s['cat_rows'], $keys, $tab);
    if ($rows === $s['cat_rows'])
    {
        aio_log("catalog: rows: $tab: the same rows");
        return null;
    }
    $s['cat_rows'] = $rows;
    if (!aio_settings_write($s))
    {
        aio_log("catalog: rows: $tab: settings.json not written");
        return aio_error('cat_rows_not_saved');
    }
    aio_log("catalog: rows: $tab: " . count($keys) . ' chosen, saved');
    return aio_catv_invalidate($from);
}
