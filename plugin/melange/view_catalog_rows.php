<?php
// The rows of a tab in two levels, as on the page: POP UP on the header of a row -> "Configure
// list…" -> a menu of "Group order" and the groups of the tab, each a dialog of the shell
// (edit_list_config) of its own. Its state is the file <FS_PREFIX>/config/lcfg_<config_id>.txt
// (list_utils.php): "+id"/"-id" against checked_ids, then "---" and an order. cat_rows of
// settings.json stays the choice, the page and the dialogs the same: the file is written from it
// before a dialog opens and read back when it closes; only the keys of that tab change. The rows
// of a group stay together (mel_cat_by_groups): "Group order" moves the groups and the rows of
// Melange (mel_cat_pseudo: "Продолжить", "Избранное", "На TorrServer"), the dialog of a group its rows.

// The groups of the menu: those of the page (mel_cat_groups) in its order, the keys of their names.
/**
 * @return array<string, string>
 */
function mel_catv_lcfg_groups()
{
    $out = array();
    foreach (mel_cat_groups() as $g)
        $out[$g] = 'cat_group_' . str_replace('-', '_', $g);
    return $out;
}

// The config_id of the dialog of the tab $tab: of its order of groups ($g ''), else of its group $g;
// bin/uninstall.sh removes their files.
/**
 * @param string $tab
 * @param string $g
 * @return string
 */
function mel_catv_lcfg_id($tab, $g)
{
    return $g === '' ? "melange_rows_{$tab}_order" : "melange_rows_{$tab}_g_$g";
}

/**
 * @param string $tab
 * @param string $g
 * @return string
 */
function mel_catv_lcfg_path($tab, $g)
{
    return getenv('FS_PREFIX') . '/config/lcfg_' . mel_catv_lcfg_id($tab, $g) . '.txt';
}

// The chosen rows of the tab $tab of the manifest $rows (mel_cat_user_rows), in their order, those
// of a group together.
/**
 * @param list<MelCatRow> $rows
 * @param string $tab
 * @return list<MelCatRow>
 */
function mel_catv_lcfg_chosen($rows, $tab)
{
    return mel_cat_user_rows($rows, Mel::$settings['cat_rows'], $tab);
}

// The rows of the group $g of the tab $tab of the manifest $rows, in the order of the manifest: the
// rows of the dialog of the group.
/**
 * @param list<MelCatRow> $rows
 * @param string $tab
 * @param string $g
 * @return list<MelCatRow>
 */
function mel_catv_lcfg_group_rows($rows, $tab, $g)
{
    $out = array();
    foreach ($rows as $r)
    {
        if (mel_cat_row_tab($r) === $tab && mel_cat_block($r) === $g)
            $out[] = $r;
    }
    return $out;
}

// The rows of Melange (mel_cat_pseudo) in "Group order" of a tab: all of them, always, but "На TorrServer" with "Без
// торрентов" on (mel_tsl_on; its key then stays as it was, mel_catv_lcfg_order_apply).
/**
 * @return array<string, array{on: bool, tr: string}>
 */
function mel_catv_lcfg_pseudo()
{
    $out = mel_cat_pseudo();
    if (!mel_tsl_on())
        unset($out['@torrserver']);
    return $out;
}

// The rows of Melange with a key of the tab $tab in cat_rows $chosen: name ('@continue') => shown (<tab>/@<name>) or not
// (:off); the first key of a name counts, as in mel_cat_tab_slots. A name with no key not there.
/**
 * @param mixed $chosen
 * @param string $tab
 * @return array<string, bool>
 */
function mel_catv_lcfg_keyed($chosen, $tab)
{
    $out = array();
    foreach (is_array($chosen) ? $chosen : array() as $k)
    {
        $pk = mel_cat_pseudo_key($k);
        if ($pk !== null && $pk['tab'] === $tab && !isset($out[$pk['name']]))
            $out[$pk['name']] = !$pk['off'];
    }
    return $out;
}

// The count of the keys of rows of the server in the keys of cat_rows $keys (not of a row of Melange).
/**
 * @param list<string> $keys
 * @return int
 */
function mel_catv_lcfg_rows_n($keys)
{
    $n = 0;
    foreach ($keys as $k)
        $n += mel_cat_pseudo_key($k) === null ? 1 : 0;
    return $n;
}

// The keys of the rows $rows -> key => true.
/**
 * @param list<MelCatRow> $rows
 * @return array<string, true>
 */
function mel_catv_lcfg_keys($rows)
{
    $out = array();
    foreach ($rows as $r)
        $out[$r['key']] = true;
    return $out;
}

// The dialog $g of the tab $tab of the manifest $rows ('': "Group order", else the group $g), its
// captions in the language $lang -> ids: its items, in the order of all_items (what "Reset to
// defaults" gives back); captions: id => caption; checked: its checked_ids, id => checked, every
// id; on: the ids chosen now (id => true); order: the ids in the order shown now, as the file after
// "---"; item: a row of the tab => its item (to select the row of the header); blocks: the slots
// of the tab (mel_cat_tab_slots) in their order, a group => the keys of its chosen rows
// (mel_cat_blocks), a row of Melange shown => array(<tab>/@<name>); keyed: mel_catv_lcfg_keyed.
// "Group order": the rows of Melange (mel_catv_lcfg_pseudo) in their canonical order, then the
// groups with chosen rows in the order of the page (mel_cat_groups): all_items. checked_ids: the
// groups and the rows of Melange on by default. Chosen: the groups, the rows of Melange shown. The
// order: theirs on the tab, a row of Melange hidden where it goes when checked (mel_cat_pseudo_at, as
// mel_cat_tab_slots: right after the nearest one before it in the canonical order, none - first). A group: its rows in the
// order of the manifest, checked_ids its start rows (showInHome); shown: its chosen rows in their
// order, then the others in the order of the manifest. checked_ids are also the state of an id with no line in the file
// (list_utils.php r25: a line only against checked_ids).
/**
 * @param list<MelCatRow> $rows
 * @param string $tab
 * @param string $g
 * @param string $lang
 * @return array{ids: list<string>, captions: array<string, string>, checked: array<string, bool>, on: array<string, true>, order: list<string>, item: array<string, string>, blocks: array<string, list<string>>, keyed: array<string, bool>}
 */
function mel_catv_lcfg_dialog($rows, $tab, $g, $lang)
{
    $blocks = array();
    foreach (mel_cat_tab_slots($rows, Mel::$settings['cat_rows'], $tab) as $s)
    {
        if (is_string($s))
            $blocks[$s] = array("$tab/$s");
        else
            $blocks[mel_cat_block($s)][] = $s['key'];
    }
    $names = mel_catv_lcfg_groups();
    $d = array('ids' => array(), 'captions' => array(), 'checked' => array(), 'on' => array(), 'order' => array(), 'item' => array(),
        'blocks' => $blocks, 'keyed' => mel_catv_lcfg_keyed(Mel::$settings['cat_rows'], $tab));
    if ($g === '')
    {
        $pseudo = mel_catv_lcfg_pseudo();
        foreach ($pseudo as $p => $x)
        {
            $d['ids'][] = $p;
            $d['captions'][$p] = mel_tr($lang, $x['tr']);
            $d['checked'][$p] = $x['on'];
            $d['item'][$p] = $p;
            if (isset($blocks[$p]))
                $d['on'][$p] = true;
        }
        foreach (mel_cat_groups() as $b)
        {
            if (!isset($blocks[$b]))
                continue;
            $d['ids'][] = $b;
            $d['captions'][$b] = mel_tr($lang, $names[$b]);
            $d['checked'][$b] = true;
            $d['on'][$b] = true;
        }
        foreach (array_keys($blocks) as $b)
        {
            if (isset($d['checked'][$b]))
                $d['order'][] = strval($b);
        }
        foreach (array_keys($pseudo) as $p)
        {
            if (!isset($d['on'][$p]))
                array_splice($d['order'], mel_cat_pseudo_at($d['order'], $p), 0, array($p));
        }
        foreach ($rows as $r)
        {
            if (mel_cat_row_tab($r) === $tab)
                $d['item'][$r['key']] = mel_cat_block($r);
        }
        return $d;
    }
    $d['order'] = isset($blocks[$g]) ? $blocks[$g] : array();
    foreach ($d['order'] as $k)
        $d['on'][$k] = true;
    foreach (mel_catv_lcfg_group_rows($rows, $tab, $g) as $r)
    {
        $d['ids'][] = $r['key'];
        $d['captions'][$r['key']] = $r['name'];
        $d['checked'][$r['key']] = $r['home'];
        $d['item'][$r['key']] = $r['key'];
        if (!isset($d['on'][$r['key']]))
            $d['order'][] = $r['key'];
    }
    return $d;
}

// The caption of an item of a dialog from $s. The shell puts it into JSON as is ({"type":"text","text":"%s"}, r24, r25):
// a " or \ breaks it, the row empty. So " -> « and » in turn, \ -> /, control characters -> a space; not \" (a shell
// that escapes it itself would show \").
/**
 * @param string $s
 * @return string
 */
function mel_catv_lcfg_caption($s)
{
    $parts = explode('"', strtr(strval(preg_replace('/[\x00-\x1F\x7F]/', ' ', $s)), '\\', '/'));
    $out = array_shift($parts);
    foreach ($parts as $i => $p)
        $out .= ($i % 2 ? '»' : '«') . $p;
    return $out;
}

// The text of the file of a dialog over its items $ids, those chosen $on (id => true), the order
// shown $order: the state of every item, then the order.
/**
 * @param list<string> $ids
 * @param array<string, true> $on
 * @param list<string> $order
 * @return string
 */
function mel_catv_lcfg_text($ids, $on, $order)
{
    $state = '';
    foreach ($ids as $id)
        $state .= (isset($on[$id]) ? '+' : '-') . $id . "\n";
    return $state . "---\n" . ($order ? implode("\n", $order) . "\n" : '');
}

// The file $text of a dialog over its items $ids, its checked_ids $checked (mel_catv_lcfg_dialog)
// -> the state of every item: by its line (the last line of an item wins), an item with no line
// as $checked; the place of an item in the order after "---" (its first line); whether there is
// "---" (the shell writes it after "Move" only); the count of the lines "+" and "-" of items.
// Lines not of an item go.
/**
 * @param string $text
 * @param list<string> $ids
 * @param array<string, bool> $checked
 * @return array{array<string, bool>, array<string, int>, bool, int, int}
 */
function mel_catv_lcfg_read($text, $ids, $checked)
{
    $known = array_flip($ids);
    $state = $checked;
    $rank = array();
    $in_order = false;
    $plus = 0;
    $minus = 0;
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
        {
            $state[$k] = $line[0] === '+';
            $plus += $line[0] === '+' ? 1 : 0;
            $minus += $line[0] === '-' ? 1 : 0;
        }
    }
    return array($state, $rank, $in_order, $plus, $minus);
}

// All items after a dialog over $ids, its checked_ids $checked, the order shown when it opened
// $shown: in the order of the file after "---"; no "---" but lines "+"/"-" (checks, no "Move"), in
// the order of $shown; neither ("Reset to defaults", no file), in the order of $ids (all_items). An
// item with no place in that order after, in the order of $ids. -> array(ids, id => checked).
/**
 * @param string $text
 * @param list<string> $ids
 * @param array<string, bool> $checked
 * @param list<string> $shown
 * @return array{list<string>, array<string, bool>}
 */
function mel_catv_lcfg_sorted($text, $ids, $checked, $shown)
{
    list($state, $rank, $has_order, $plus, $minus) = mel_catv_lcfg_read($text, $ids, $checked);
    if (!$has_order && $plus + $minus > 0)
        $rank = array_flip($shown);
    $pos = array();
    $n = count($rank);
    foreach ($ids as $i => $id)
        $pos[$id] = isset($rank[$id]) ? $rank[$id] : $n + $i;
    asort($pos);
    $out = array();
    foreach (array_keys($pos) as $id)
        $out[] = strval($id);
    return array($out, $state);
}

// The items checked after a dialog (as mel_catv_lcfg_sorted), in that order -> ids; none checked:
// null.
/**
 * @param string $text
 * @param list<string> $ids
 * @param array<string, bool> $checked
 * @param list<string> $shown
 * @return list<string>|null
 */
function mel_catv_lcfg_order($text, $ids, $checked, $shown)
{
    list($all, $state) = mel_catv_lcfg_sorted($text, $ids, $checked, $shown);
    $out = array();
    foreach ($all as $id)
    {
        if ($state[$id])
            $out[] = $id;
    }
    return $out ? $out : null;
}

// The file of a dialog at $path after it closed -> array(its text, '' or why there is none: 'none' -
// no file, as an empty one (list_utils.php r25: no change against checked_ids); 'bad' - not a file
// or too big; 'unread' - not read, not as an empty one: that puts the dialog back to checked_ids).
/**
 * @param string $path
 * @return array{string, string}
 */
function mel_catv_lcfg_load($path)
{
    clearstatcache();
    if (!is_link($path) && !file_exists($path))
        return array('', 'none');
    if (is_link($path) || !is_file($path) || filesize($path) > 65536)
        return array('', 'bad');
    $text = @file_get_contents($path);
    return $text === false ? array('', 'unread') : array($text, '');
}

// The keys of the tab $tab after "Group order" (its dialog $d, mel_catv_lcfg_dialog): its items checked $got
// (mel_catv_lcfg_order) in their order, a group its rows of blocks in their order, a row of Melange <tab>/@<name>; a group
// unchecked goes with its rows; then <tab>/@<name>:off of the rows of Melange unchecked. A row of Melange not in the
// dialog (mel_catv_lcfg_pseudo) as its key was: shown - right after the nearest item before it on the tab still there,
// none - first; hidden - :off; no key - none.
/**
 * @param list<string> $got
 * @param array{ids: list<string>, blocks: array<string, list<string>>, keyed: array<string, bool>} $d
 * @param string $tab
 * @return list<string>
 */
function mel_catv_lcfg_order_apply($got, $d, $tab)
{
    $ids = array_flip($d['ids']);
    $was = array_keys($d['blocks']);
    foreach ($was as $i => $b)
    {
        if (isset($ids[$b]))
            continue;
        $at = 0;
        for ($j = $i - 1; $j >= 0; $j--)
        {
            $k = array_search($was[$j], $got, true);
            if ($k !== false)
            {
                $at = intval($k) + 1;
                break;
            }
        }
        array_splice($got, $at, 0, array(strval($b)));
    }
    $keys = array();
    foreach ($got as $b)
        $keys = array_merge($keys, $b[0] === '@' ? array("$tab/$b") : (isset($d['blocks'][$b]) ? $d['blocks'][$b] : array()));
    foreach ($d['ids'] as $id)
    {
        if ($id[0] === '@' && !in_array($id, $got, true))
            $keys[] = "$tab/$id:off";
    }
    foreach ($d['keyed'] as $p => $on)
    {
        if (!$on && !isset($ids[$p]))
            $keys[] = "$tab/$p:off";
    }
    return $keys;
}

// The keys of the tab $tab after the dialog of a group (its dialog $d, mel_catv_lcfg_dialog), its rows checked $block in
// their order (mel_catv_lcfg_group_apply over the keys of blocks): those of Melange as they were - a row shown only with
// its key in cat_rows (keyed; with none it comes back to the same place by itself), the keys :off last.
/**
 * @param array{ids: list<string>, blocks: array<string, list<string>>, keyed: array<string, bool>} $d
 * @param string $tab
 * @param list<string> $block
 * @return list<string>
 */
function mel_catv_lcfg_group_keys($d, $tab, $block)
{
    $now = array();
    foreach ($d['blocks'] as $b => $ks)
    {
        $b = strval($b);
        if ($b[0] !== '@' || !empty($d['keyed'][$b]))
            $now = array_merge($now, $ks);
    }
    $keys = mel_catv_lcfg_group_apply($now, $d['ids'], $block);
    foreach ($d['keyed'] as $p => $on)
    {
        if (!$on)
            $keys[] = "$tab/$p:off";
    }
    return $keys;
}

// The keys of the tab after the dialog of a group over its rows $ids: the chosen keys of the tab
// $chosen (in their order) with those of the group replaced by $block (its rows checked, in the
// order of the dialog) where the first of them was; none of the group chosen before: $block last.
// -> keys, maybe none.
/**
 * @param list<string> $chosen
 * @param list<string> $ids
 * @param list<string> $block
 * @return list<string>
 */
function mel_catv_lcfg_group_apply($chosen, $ids, $block)
{
    $of = array_flip($ids);
    $keys = array();
    $put = false;
    foreach ($chosen as $k)
    {
        if (!isset($of[$k]))
            $keys[] = $k;
        elseif (!$put)
            $keys = array_merge($keys, $block);
        $put = $put || isset($of[$k]);
    }
    return $put ? $keys : array_merge($keys, $block);
}

// cat_rows $chosen with the keys of the tab $tab replaced by $keys, movies first, then series,
// as the page keeps them (mel_cat_tabs).
/**
 * @param mixed $chosen
 * @param list<string> $keys
 * @param string $tab
 * @return list<string>|null
 */
function mel_catv_rows_merge($chosen, $keys, $tab)
{
    $out = array();
    foreach (mel_cat_tabs() as $t)
    {
        if ($t === $tab)
        {
            $out = array_merge($out, $keys);
            continue;
        }
        foreach (is_array($chosen) ? $chosen : array() as $k)
        {
            if (is_string($k) && strpos($k, '/') !== false && mel_cat_row_tab(array('type' => strval(strstr($k, '/', true)))) === $t)
                $out[] = $k;
        }
    }
    return mel_cat_rows_ok($out);
}


// The tab of the rows of the dialogs of $from: of a main screen of the catalog (mel_catv_tab), of an EPF of the tab
// "TV" of one tab (mel_tv_tab_of: POP UP there, mel_tv_menu, gives them "tv:<tab>"); '' else.
/**
 * @param string $from
 * @return string
 */
function mel_catv_rows_tab($from)
{
    return mel_tv_is_from($from) ? mel_tv_tab_of($from) : mel_catv_tab($from);
}

// "Configure list…" and a separator for the POP UP of the main screen $from of the tab $tab, on the
// header $hid (selected_header_id, in the panel of the headers; genre_rows_screen.php r25): on any
// h:* header or MEL_CATV_STRIP_HEADER the menu of the rows of the tab (mel_catv_rows_menu), the row
// selected if it is in the copy, on "Продолжить", "Избранное", "На TorrServer" theirs; none if the copy has no row
// of the tab. Else (a tile) none.
/**
 * @param string $hid
 * @param string $tab
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_catv_rows_menu_items($hid, $tab, $from, $lang)
{
    $strip = $hid === MEL_CATV_STRIP_HEADER;
    $rid = mel_catv_header_rid($hid);
    if (!$strip && strpos($hid, MEL_CATV_HEADER) !== 0)
        return array();
    $copy = mel_cat_manifest_copy();
    $any = false;
    // "Продолжить", "Избранное", "На TorrServer": their items of "Group order".
    $by_rid = array(MEL_CP_RID => '@continue', MEL_ML_RID => '@mylist', MEL_TSL_RID => '@torrserver');
    $sel = isset($by_rid[$rid]) ? $by_rid[$rid] : null;
    foreach ($copy['rows'] as $r)
    {
        if (mel_cat_row_tab($r) !== $tab)
            continue;
        $any = true;
        if (mel_catv_rid($r) === $rid)
            $sel = $r['key'];
    }
    if (!$any)
        return array();
    return array(
        array(
            GuiMenuItemDef::caption => mel_tr($lang, 'cat_rows_edit'),
            GuiMenuItemDef::icon_url => MEL_CATV_ICONS . 'playlist_small.png',
            GuiMenuItemDef::action => mel_input('cat_rows_menu', array('from' => $from, 'sel' => strval($sel)))),
        array(GuiMenuItemDef::is_separator => true));
}

// "Configure list…" of the main screen $from, the row $sel ('': none) -> the second menu, as the
// MENU item "Download to server" (mel_srv_panel): "Group order" (with chosen rows of the tab), a
// separator, the groups of the tab in the order of the page with "N of M" (chosen, all of the tab);
// a group with no row of the tab not there. The cursor on the first item.
/**
 * @param string $from
 * @param string $sel
 * @return MelAction|null
 */
function mel_catv_rows_menu($from, $sel)
{
    $tab = mel_catv_rows_tab($from);
    if ($tab === '')
        return null;
    $copy = mel_cat_manifest_copy();
    $chosen = mel_catv_lcfg_chosen($copy['rows'], $tab);
    $on = mel_catv_lcfg_keys($chosen);
    $all = array();
    $n = array();
    foreach ($copy['rows'] as $r)
    {
        if (mel_cat_row_tab($r) !== $tab)
            continue;
        $g = mel_cat_block($r);
        $all[$g] = isset($all[$g]) ? $all[$g] + 1 : 1;
        $n[$g] = (isset($n[$g]) ? $n[$g] : 0) + (isset($on[$r['key']]) ? 1 : 0);
    }
    if (!$all)
        return null;
    $lang = mel_catv_lang();
    $items = array();
    if ($chosen)
    {
        $items[] = array(
            GuiMenuItemDef::caption => mel_tr($lang, 'cat_rows_group_order'),
            GuiMenuItemDef::action => mel_input('cat_rows_edit', array('from' => $from, 'sel' => $sel, 'g' => '')));
        $items[] = array(GuiMenuItemDef::is_separator => true);
    }
    foreach (mel_catv_lcfg_groups() as $g => $name)
    {
        if (!isset($all[$g]))
            continue;
        $of = str_replace(array('{n}', '{m}'), array(strval($n[$g]), strval($all[$g])), mel_tr($lang, 'cat_rows_of'));
        $items[] = array(
            GuiMenuItemDef::caption => mel_tr($lang, $name) . "  $of",
            GuiMenuItemDef::action => mel_input('cat_rows_edit', array('from' => $from, 'sel' => $sel, 'g' => $g)));
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => 0));
}

// The dialog $g of the tab of the main screen $from ('': "Group order", else of the group $g), as
// mel_catv_lcfg_dialog; selected: the row $sel ('': none) if in it, in "Group order" its group.
// "Group order" has no options (no empty set, as on the page), a group ALLOW_EMPTY. No groups of
// the shell. Its file written from cat_rows first (the order shown after "---"); not written: an
// error, no dialog (its close would write the last state of the shell over cat_rows of the tab). No
// close_item_params, dialog_params or params of items: r22 has none.
/**
 * @param string $from
 * @param string $sel
 * @param string $g
 * @return MelAction|null
 */
function mel_catv_rows_edit($from, $sel, $g)
{
    $tab = mel_catv_rows_tab($from);
    $names = mel_catv_lcfg_groups();
    if ($tab === '' || ($g !== '' && !isset($names[$g])))
        return null;
    if (!class_exists('EditListConfigActionData') || !defined('EDIT_LIST_CONFIG_ACTION_ID'))
        return mel_error('cat_rows_no_dialog');
    $copy = mel_cat_manifest_copy();
    $lang = mel_catv_lang();
    $d = mel_catv_lcfg_dialog($copy['rows'], $tab, $g, $lang);
    if (!$d['ids'])
        return mel_error('cat_outdated');
    $what = $g === '' ? 'order' : "group $g";
    // From "TV": its EPF in the log, the dialog opened over the main screen of the shell.
    $at = mel_tv_is_from($from) ? "$tab, $from" : $tab;
    if (!mel_write_file(mel_catv_lcfg_path($tab, $g), mel_catv_lcfg_text($d['ids'], $d['on'], $d['order']), 0644))
    {
        mel_log("catalog: rows: $at: $what: the file of the dialog not written");
        return mel_error('cat_rows_not_opened');
    }
    $items = array();
    foreach ($d['ids'] as $id)
        $items[] = array(GuiItem::id => $id, GuiItem::caption => mel_catv_lcfg_caption($d['captions'][$id]));
    $sel = isset($d['item'][$sel]) ? $d['item'][$sel] : '';
    $checked = array_keys(array_filter($d['checked']));
    $data = array(
        EditListConfigActionData::config_id => mel_catv_lcfg_id($tab, $g),
        EditListConfigActionData::title => mel_tr($lang, $g === '' ? 'cat_rows_group_order' : $names[$g]),
        EditListConfigActionData::all_items => $items,
        EditListConfigActionData::post_action => mel_input('cat_rows_apply', array('from' => $from, 'g' => $g)));
    if ($checked)
        $data[EditListConfigActionData::checked_ids] = $checked;
    if ($g !== '')
        $data[EditListConfigActionData::options] = EDIT_LIST_CONFIG_OPT_ALLOW_EMPTY;
    if (isset($d['checked'][$sel]))
        $data[EditListConfigActionData::sel_id] = $sel;
    $n_pseudo = $g === '' ? count(mel_catv_lcfg_pseudo()) : 0;
    mel_log("catalog: rows: $at: $what: dialog, " . ($g === '' ? (count($items) - $n_pseudo) . " groups, $n_pseudo rows of Melange" :
        count($items) . ' rows, ' . count($d['on']) . ' chosen'));
    return array(GuiAction::handler_string_id => EDIT_LIST_CONFIG_ACTION_ID, GuiAction::data => $data);
}

// post_action of the dialog $g (as mel_catv_rows_edit) of the main screen $from, at every close;
// the shell has written its file by then; none: as an empty one (list_utils.php r25: no file, no
// change against checked_ids); there but not read: an error, nothing written. Changed: "Group order"
// gives the groups and the rows of Melange of the tab in their order (mel_catv_lcfg_order_apply); a
// group gives its rows in its place, those of Melange as they were (mel_catv_lcfg_group_keys); no row
// of the server left on the tab (only those of Melange checked): an error, nothing written. The rows
// go to cat_rows in place of those of the tab, settings.json read again and written as the quick
// setup does (mel_settings_write), the screen read again in place (invalidate: the shell keeps the
// focus by sel_state). Anything else: nothing written. Back to the second menu: no.
/**
 * @param stdClass $in
 * @param string $from
 * @return MelAction|null
 */
function mel_catv_rows_apply($in, $from)
{
    $tab = mel_catv_rows_tab($from);
    $g = mel_catv_in($in, 'g');
    $names = mel_catv_lcfg_groups();
    $changed = isset($in->list_config_changed) && is_scalar($in->list_config_changed) ? strval($in->list_config_changed) : '';
    if ($tab === '' || ($g !== '' && !isset($names[$g])) || mel_catv_in($in, 'list_config_id') !== mel_catv_lcfg_id($tab, $g))
        return null;
    $what = $g === '' ? 'order' : "group $g";
    $at = mel_tv_is_from($from) ? "$tab, $from" : $tab;
    if ($changed !== '1')
    {
        mel_log("catalog: rows: $at: $what: dialog closed, no change");
        return null;
    }
    list($text, $no) = mel_catv_lcfg_load(mel_catv_lcfg_path($tab, $g));
    if ($no === 'bad')
    {
        mel_log("catalog: rows: $at: $what: the file of the dialog not a file or too big, nothing changed");
        return null;
    }
    if ($no === 'none')
        mel_log("catalog: rows: $at: $what: no file of the dialog, the defaults");
    if ($no === 'unread')
    {
        mel_log("catalog: rows: $at: $what: the file of the dialog not read, nothing changed");
        return mel_error('cat_rows_not_saved');
    }
    $copy = mel_cat_manifest_copy();
    $d = mel_catv_lcfg_dialog($copy['rows'], $tab, $g, mel_catv_lang());
    list(, , $has_order, $plus, $minus) = mel_catv_lcfg_read($text, $d['ids'], $d['checked']);
    mel_log("catalog: rows: $at: $what: the file: " . ($has_order ? 'an order' : 'no order') . ", $plus on, $minus off");
    $got = mel_catv_lcfg_order($text, $d['ids'], $d['checked'], $d['order']);
    if ($g === '')
    {
        if (!$got)
        {
            mel_log("catalog: rows: $at: $what: none chosen in the file, nothing changed");
            return null;
        }
        $keys = mel_catv_lcfg_order_apply($got, $d, $tab);
    }
    else
        $keys = mel_catv_lcfg_group_keys($d, $tab, $got ? $got : array());
    if (!mel_catv_lcfg_rows_n($keys))
    {
        mel_log("catalog: rows: $at: $what: no row left on the tab, nothing changed");
        return mel_error('cat_rows_empty');
    }
    $dir = mel_data_dir();
    $why = 'no data_dir';
    $s = $dir !== '' ? mel_settings_read($dir, $why) : null;
    if (!$s || $why !== '')
    {
        mel_log("catalog: rows: $at: settings.json not read, not written");
        return mel_error('cat_rows_not_saved');
    }
    $rows = mel_catv_rows_merge($s['cat_rows'], $keys, $tab);
    if ($rows === $s['cat_rows'])
    {
        mel_log("catalog: rows: $at: the same rows");
        return null;
    }
    $was = $s['cat_rows'];
    $s['cat_rows'] = $rows;
    if (!mel_settings_write($s))
    {
        mel_log("catalog: rows: $at: settings.json not written");
        return mel_error('cat_rows_not_saved');
    }
    // In memory too: the files of "TV" are built of it in this operation (mel_tv_rows_saved).
    Mel::$settings['cat_rows'] = $rows;
    mel_log("catalog: rows: $at: " . mel_catv_lcfg_rows_n($keys) . ' chosen, saved');
    // From "TV": no screen of Melange under the EPF to read again, the files built anew (mel_tv_rows_saved).
    return mel_tv_is_from($from) ? mel_tv_rows_saved($copy['rows'], $was, $rows) : mel_catv_invalidate($from);
}
