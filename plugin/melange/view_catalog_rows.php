<?php
// The rows of a tab in two levels, as on the page: POP UP on the header of a row -> "Configure
// list…" -> a menu of "Row order" and the groups of the tab, each a dialog of the shell
// (edit_list_config) of its own. Its state is the file <FS_PREFIX>/config/lcfg_<config_id>.txt
// (list_utils.php): "+key"/"-key" against checked_ids, then "---" and an order. cat_rows of
// settings.json stays the choice, the page and the dialogs the same: the file is written from it
// before a dialog opens and read back when it closes; only the keys of that tab change.

// The groups of the menu: those of the page (GROUPS of web/src/rows.ts) in its order, the keys of
// their names; a group of aio_cat_group not here goes to "other".
/**
 * @return array<string, string>
 */
function aio_catv_lcfg_groups()
{
    return array('top' => 'cat_group_top', 'fresh' => 'cat_group_fresh', 'ru' => 'cat_group_ru',
        'ru-services' => 'cat_group_ru_services', 'genres' => 'cat_group_genres', 'theme' => 'cat_group_theme',
        'studios' => 'cat_group_studios', 'decades' => 'cat_group_decades', 'years' => 'cat_group_years',
        'streaming' => 'cat_group_streaming', 'world' => 'cat_group_world', 'runtime' => 'cat_group_runtime',
        'anime' => 'cat_group_anime', 'other' => 'cat_group_other');
}

// The group of the row $r in the menu: its own, if one of aio_catv_lcfg_groups, else "other".
/**
 * @param AioCatRow $r
 * @return string
 */
function aio_catv_lcfg_group($r)
{
    $groups = aio_catv_lcfg_groups();
    return isset($groups[$r['group']]) ? $r['group'] : 'other';
}

// The config_id of the dialog of the tab $tab: of its order ($g ''), else of its group $g;
// bin/uninstall.sh removes their files.
/**
 * @param string $tab
 * @param string $g
 * @return string
 */
function aio_catv_lcfg_id($tab, $g)
{
    return $g === '' ? "melange_rows_{$tab}_order" : "melange_rows_{$tab}_g_$g";
}

/**
 * @param string $tab
 * @param string $g
 * @return string
 */
function aio_catv_lcfg_path($tab, $g)
{
    return getenv('FS_PREFIX') . '/config/lcfg_' . aio_catv_lcfg_id($tab, $g) . '.txt';
}

// The chosen rows of the tab $tab of the manifest $rows (aio_cat_user_rows), in their order: the
// rows of "Row order".
/**
 * @param list<AioCatRow> $rows
 * @param string $tab
 * @return list<AioCatRow>
 */
function aio_catv_lcfg_chosen($rows, $tab)
{
    return aio_cat_user_rows($rows, Aio::$settings['cat_rows'], $tab);
}

// The rows of the group $g of the tab $tab of the manifest $rows, in the order of the manifest: the
// rows of the dialog of the group.
/**
 * @param list<AioCatRow> $rows
 * @param string $tab
 * @param string $g
 * @return list<AioCatRow>
 */
function aio_catv_lcfg_group_rows($rows, $tab, $g)
{
    $out = array();
    foreach ($rows as $r)
    {
        if (aio_cat_row_tab($r) === $tab && aio_catv_lcfg_group($r) === $g)
            $out[] = $r;
    }
    return $out;
}

// The keys of the rows $rows -> key => true.
/**
 * @param list<AioCatRow> $rows
 * @return array<string, true>
 */
function aio_catv_lcfg_keys($rows)
{
    $out = array();
    foreach ($rows as $r)
        $out[$r['key']] = true;
    return $out;
}

// The checked_ids of the dialog $g ('': "Row order") over its rows $base -> key => checked, every row
// of $base: the order all of them, a group its start rows (showInHome). What "Reset to defaults"
// gives back, and the state of a row with no line in the file (list_utils.php r25: a line only
// against checked_ids): aio_catv_rows_edit gives it to the shell, aio_catv_lcfg_read reads by it.
/**
 * @param list<AioCatRow> $base
 * @param string $g
 * @return array<string, bool>
 */
function aio_catv_lcfg_checked($base, $g)
{
    $out = array();
    foreach ($base as $r)
        $out[$r['key']] = $g === '' || $r['home'];
    return $out;
}

// The text of the file of a dialog over $base, the chosen rows $on (key => true): the state of every
// row, then the order of $base.
/**
 * @param list<AioCatRow> $base
 * @param array<string, true> $on
 * @return string
 */
function aio_catv_lcfg_text($base, $on)
{
    $state = '';
    $order = '';
    foreach ($base as $r)
    {
        $state .= (isset($on[$r['key']]) ? '+' : '-') . $r['key'] . "\n";
        $order .= $r['key'] . "\n";
    }
    return $state . "---\n" . $order;
}

// The file $text of a dialog over the rows $base, its checked_ids $checked (aio_catv_lcfg_checked)
// -> the state of every row of $base: by its line (the last line of a row wins), a row with no line
// as $checked; the place of a row in the order after "---" (its first line). Lines not of a row of
// $base go.
/**
 * @param string $text
 * @param list<AioCatRow> $base
 * @param array<string, bool> $checked
 * @return array{array<string, bool>, array<string, int>}
 */
function aio_catv_lcfg_read($text, $base, $checked)
{
    $known = aio_catv_lcfg_keys($base);
    $state = $checked;
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
    return array($state, $rank);
}

// The rows of the tab after the dialog "Row order" over $base (the chosen rows), its checked_ids
// $checked: in the order of the file, a row with no place there after, as in $base; a row unchecked
// goes. -> keys; none on: null.
/**
 * @param string $text
 * @param list<AioCatRow> $base
 * @param array<string, bool> $checked
 * @return list<string>|null
 */
function aio_catv_lcfg_order($text, $base, $checked)
{
    list($state, $rank) = aio_catv_lcfg_read($text, $base, $checked);
    $pos = array();
    $n = count($rank);
    foreach ($base as $i => $r)
        $pos[$r['key']] = isset($rank[$r['key']]) ? $rank[$r['key']] : $n + $i;
    asort($pos);
    $keys = array();
    foreach (array_keys($pos) as $k)
    {
        if ($state[$k])
            $keys[] = strval($k);
    }
    return $keys ? $keys : null;
}

// The rows of the tab after the dialog of a group over its rows $base, its checked_ids $checked, the
// chosen rows of the tab $chosen (keys, in their order): a row of the group unchecked goes, one
// checked and not chosen goes to the end, in the order of the manifest; the rest keep their places.
// -> keys, maybe none.
/**
 * @param string $text
 * @param list<AioCatRow> $base
 * @param array<string, bool> $checked
 * @param list<string> $chosen
 * @return list<string>
 */
function aio_catv_lcfg_group_apply($text, $base, $checked, $chosen)
{
    list($on) = aio_catv_lcfg_read($text, $base, $checked);
    $keys = array();
    foreach ($chosen as $k)
    {
        if (!isset($on[$k]) || $on[$k])
            $keys[] = $k;
    }
    foreach ($on as $k => $v)
    {
        if ($v && !in_array($k, $chosen, true))
            $keys[] = strval($k);
    }
    return $keys;
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


// "Configure list…" and a separator for the POP UP of the main screen $from of the tab $tab, on the
// header $hid (selected_header_id, in the panel of the headers; genre_rows_screen.php r25): the
// menu of the rows of its tab (aio_catv_rows_menu), that row selected; on the row "No rows" of the
// tab and on the header of the strip (AIO_CATV_STRIP_HEADER) too, no row selected. Else (a tile, a
// header of no row of the tab) none.
/**
 * @param string $hid
 * @param string $tab
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_catv_rows_menu_items($hid, $tab, $from, $lang)
{
    $strip = $hid === AIO_CATV_STRIP_HEADER;
    if (!$strip && strpos($hid, AIO_CATV_HEADER) !== 0)
        return array();
    $rid = $strip ? '' : strval(substr($hid, strlen(AIO_CATV_HEADER)));
    $copy = aio_cat_manifest_copy();
    $any = false;
    $sel = null;
    foreach ($copy['rows'] as $r)
    {
        if (aio_cat_row_tab($r) !== $tab)
            continue;
        $any = true;
        if (aio_catv_rid($r) === $rid)
            $sel = $r['key'];
    }
    if (!$any || ($sel === null && !$strip && $rid !== aio_catv_rid(aio_catv_tab_empty_row($tab, $lang))))
        return array();
    return array(
        array(
            GuiMenuItemDef::caption => aio_tr($lang, 'cat_rows_edit'),
            GuiMenuItemDef::action => aio_input('cat_rows_menu', array('from' => $from, 'sel' => strval($sel)))),
        array(GuiMenuItemDef::is_separator => true));
}

// "Configure list…" of the main screen $from, the row $sel ('': none) -> the second menu, as the
// MENU item "Download to server" (aio_srv_menu): "Row order" (with chosen rows of the tab), a
// separator, the groups of the tab in the order of the page with "N of M" (chosen, all of the tab);
// a group with no row of the tab not there. The cursor on the first item.
/**
 * @param string $from
 * @param string $sel
 * @return AioAction|null
 */
function aio_catv_rows_menu($from, $sel)
{
    $tab = aio_catv_tab($from);
    if ($tab === '')
        return null;
    $copy = aio_cat_manifest_copy();
    $chosen = aio_catv_lcfg_chosen($copy['rows'], $tab);
    $on = aio_catv_lcfg_keys($chosen);
    $all = array();
    $n = array();
    foreach ($copy['rows'] as $r)
    {
        if (aio_cat_row_tab($r) !== $tab)
            continue;
        $g = aio_catv_lcfg_group($r);
        $all[$g] = isset($all[$g]) ? $all[$g] + 1 : 1;
        $n[$g] = (isset($n[$g]) ? $n[$g] : 0) + (isset($on[$r['key']]) ? 1 : 0);
    }
    if (!$all)
        return null;
    $lang = aio_catv_lang();
    $items = array();
    if ($chosen)
    {
        $items[] = array(
            GuiMenuItemDef::caption => aio_tr($lang, 'cat_rows_order'),
            GuiMenuItemDef::action => aio_input('cat_rows_edit', array('from' => $from, 'sel' => $sel, 'g' => '')));
        $items[] = array(GuiMenuItemDef::is_separator => true);
    }
    foreach (aio_catv_lcfg_groups() as $g => $name)
    {
        if (!isset($all[$g]))
            continue;
        $of = str_replace(array('{n}', '{m}'), array(strval($n[$g]), strval($all[$g])), aio_tr($lang, 'cat_rows_of'));
        $items[] = array(
            GuiMenuItemDef::caption => aio_tr($lang, $name) . "  $of",
            GuiMenuItemDef::action => aio_input('cat_rows_edit', array('from' => $from, 'sel' => $sel, 'g' => $g)));
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => 0));
}

// The dialog $g of the tab of the main screen $from ('': "Row order", else of the group $g), the row
// $sel ('': none) selected if in it. "Row order": the chosen rows of the tab in their order, all
// checked, no options (no empty set, as on the page). A group: its rows of the tab in the order of
// the manifest, checked_ids its start rows (what "Reset to defaults" gives back), ALLOW_EMPTY. No
// groups. Its file written from cat_rows first; not written: an error, no dialog (its close would
// write the last state of the shell over cat_rows of the tab). No close_item_params, dialog_params
// or params of items: r22 has none.
/**
 * @param string $from
 * @param string $sel
 * @param string $g
 * @return AioAction|null
 */
function aio_catv_rows_edit($from, $sel, $g)
{
    $tab = aio_catv_tab($from);
    $names = aio_catv_lcfg_groups();
    if ($tab === '' || ($g !== '' && !isset($names[$g])))
        return null;
    if (!class_exists('EditListConfigActionData') || !defined('EDIT_LIST_CONFIG_ACTION_ID'))
        return aio_error('cat_rows_no_dialog');
    $copy = aio_cat_manifest_copy();
    $chosen = aio_catv_lcfg_chosen($copy['rows'], $tab);
    $base = $g === '' ? $chosen : aio_catv_lcfg_group_rows($copy['rows'], $tab, $g);
    if (!$base)
        return aio_error('cat_outdated');
    $on = aio_catv_lcfg_keys($chosen);
    $what = $g === '' ? 'order' : "group $g";
    if (!aio_write_file(aio_catv_lcfg_path($tab, $g), aio_catv_lcfg_text($base, $on), 0644))
    {
        aio_log("catalog: rows: $tab: $what: the file of the dialog not written");
        return aio_error('cat_rows_not_opened');
    }
    $lang = aio_catv_lang();
    $items = array();
    $n = 0;
    foreach ($base as $r)
    {
        $items[] = array(GuiItem::id => $r['key'], GuiItem::caption => $r['name']);
        $n += isset($on[$r['key']]) ? 1 : 0;
    }
    $all = aio_catv_lcfg_checked($base, $g);
    $checked = array_keys(array_filter($all));
    $data = array(
        EditListConfigActionData::config_id => aio_catv_lcfg_id($tab, $g),
        EditListConfigActionData::title => aio_tr($lang, $g === '' ? 'cat_rows_order' : $names[$g]),
        EditListConfigActionData::all_items => $items,
        EditListConfigActionData::post_action => aio_input('cat_rows_apply', array('from' => $from, 'g' => $g)));
    if ($checked)
        $data[EditListConfigActionData::checked_ids] = $checked;
    if ($g !== '')
        $data[EditListConfigActionData::options] = EDIT_LIST_CONFIG_OPT_ALLOW_EMPTY;
    if (isset($all[$sel]))
        $data[EditListConfigActionData::sel_id] = $sel;
    aio_log("catalog: rows: $tab: $what: dialog, " . count($items) . " rows, $n chosen");
    return array(GuiAction::handler_string_id => EDIT_LIST_CONFIG_ACTION_ID, GuiAction::data => $data);
}

// post_action of the dialog $g (as aio_catv_rows_edit) of the main screen $from, at every close;
// the shell has written its file by then; none: as an empty one (list_utils.php r25: no file, no
// change against checked_ids); there but not read: an error, nothing written. Changed: "Row order" gives the rows of the tab, a group changes its
// rows in them (aio_catv_lcfg_group_apply); no row left on the tab: an error, nothing written. The
// rows go to cat_rows in place of those of the tab, settings.json read again and written as the
// quick setup does (aio_settings_write), the screen read again in place (invalidate: the shell keeps
// the focus by sel_state). Anything else: nothing written. Back to the second menu: no.
/**
 * @param stdClass $in
 * @param string $from
 * @return AioAction|null
 */
function aio_catv_rows_apply($in, $from)
{
    $tab = aio_catv_tab($from);
    $g = aio_catv_in($in, 'g');
    $names = aio_catv_lcfg_groups();
    $changed = isset($in->list_config_changed) && is_scalar($in->list_config_changed) ? strval($in->list_config_changed) : '';
    if ($tab === '' || ($g !== '' && !isset($names[$g])) || aio_catv_in($in, 'list_config_id') !== aio_catv_lcfg_id($tab, $g))
        return null;
    $what = $g === '' ? 'order' : "group $g";
    if ($changed !== '1')
    {
        aio_log("catalog: rows: $tab: $what: dialog closed, no change");
        return null;
    }
    $path = aio_catv_lcfg_path($tab, $g);
    clearstatcache();
    $none = !is_link($path) && !file_exists($path);
    if (!$none && (is_link($path) || !is_file($path) || filesize($path) > 65536))
    {
        aio_log("catalog: rows: $tab: $what: the file of the dialog not a file or too big, nothing changed");
        return null;
    }
    if ($none)
        aio_log("catalog: rows: $tab: $what: no file of the dialog, the defaults");
    $text = $none ? '' : @file_get_contents($path);
    if ($text === false)
    {
        // Not as an empty one: that puts the rows of the dialog back to checked_ids.
        aio_log("catalog: rows: $tab: $what: the file of the dialog not read, nothing changed");
        return aio_error('cat_rows_not_saved');
    }
    $copy = aio_cat_manifest_copy();
    $chosen = aio_catv_lcfg_chosen($copy['rows'], $tab);
    $base = $g === '' ? $chosen : aio_catv_lcfg_group_rows($copy['rows'], $tab, $g);
    $checked = aio_catv_lcfg_checked($base, $g);
    if ($g === '')
    {
        $keys = aio_catv_lcfg_order($text, $base, $checked);
        if (!$keys)
        {
            aio_log("catalog: rows: $tab: $what: none chosen in the file, nothing changed");
            return null;
        }
    }
    else
    {
        $was = array();
        foreach ($chosen as $r)
            $was[] = $r['key'];
        $keys = aio_catv_lcfg_group_apply($text, $base, $checked, $was);
        if (!$keys)
        {
            aio_log("catalog: rows: $tab: $what: no row left on the tab, nothing changed");
            return aio_error('cat_rows_empty');
        }
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
