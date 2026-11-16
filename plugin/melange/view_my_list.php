<?php
// "Мой список" (0.61.0) on the screens of php_server: the title into the list or out of it, its messages, POP UP of a
// tile of the catalog and of the tab "TV", the one effect of a change (aio_ml_changed); the screens "Мой список" and
// "Продолжить" (Continue Watching), each as "Show all" of a row of the catalog (view_catalog.php: on r25 rows of
// AIO_CATV_ALL_COLS with the info panel, on r24 the grid), of memory and data_dir alone, no request: "Мой список" the
// entries of its file in their order, "Продолжить" all the cards of the row "Continue" (continue.php), not
// AIO_CATV_ROW_TILES. The ways to them: the heart on the strip of the catalog (aio_catv_strip_rows) and the keys Fav 2
// (key_favorites: "Мой список") and Fav 1 (key_dune: "Продолжить") of the remote on every screen of Melange
// (aio_mlv_keys; the player has no folder view). An empty "Мой список": the hint tile, its POP UP the import of the
// Favorites of the Dune (aio_mlv_import). The file, the copy of the backup and the reading of the Favorites are of
// my_list.php (the settings page has it alone).
// Needs my_list.php, view_catalog.php (aio_catv_*), continue.php, cp_meta.php (AioCpm::$more), links.php (aio_pg_links,
// aio_pg_link_now, aio_pg_paused), progress.php, catalog.php (aio_cat_tile), catalog_card.php (aio_card_enter,
// aio_card_open, AIO_CARD_DELAY), view_card.php (aio_oc_enter), card_meta.php (aio_cm_id), tv_tab.php (aio_tv_flag,
// aio_tv_is_from), playback.php (aio_replace_act), main.php (aio_tr, aio_input, aio_error, aio_dialog).
// PHP 5.3.6, firmware API only.

// The rid of the tiles of "Мой список": "mylist:<the key of the entry>" (aio_catv_tile).
define('AIO_ML_RID', 'mylist');
// The screens of all the titles (the heart, Fav 2 and Fav 1); of one type (ENTER on the title of the row of a tab) the
// address and ":movie" or ":series".
define('AIO_MLV_LIST', 'cat_mylist');
define('AIO_MLV_CONT', 'cat_continue');
// The id of the hint tile of an empty "Мой список" (no tile of a title: aio_catv_tile has none of it).
define('AIO_MLV_HINT', 'hint:mylist');
// Its picture, T2 of knowledge/melange/design-my-list.md: <AIO_MLV_HINT_IMG>ru.png, en.png, 200x300 (the N of ml<N>
// goes up when they change: the shell keeps a picture by its URL; plugins/melange_mylist_img.py of the kitchen).
define('AIO_MLV_HINT_IMG', 'plugin_file://img/ml1_hint_');
// The events of the keys, literal: key_favorites is of r24 on (no GUI_EVENT_KEY_FAVORITES in r22; an unknown name the
// shell ignores). Proven on the device (r25, the probe fav_keys_probe): they reach handle_user_input on REGULAR and
// ROWS when the actions of the screen have them, and the shell opens neither its history nor its Favorites then.
define('AIO_MLV_KEY_LIST', 'key_favorites');
define('AIO_MLV_KEY_CONT', 'key_dune');
// The import: requests of the pairs at most, s of them at most (handle_user_input: 60 s; one is AIO_PG_LINK_S at most);
// the ones left not added this time, counted in the message (a repeat goes on).
define('AIO_MLV_IMPORT_N', 10);
define('AIO_MLV_IMPORT_S', 30.0);

class AioMlv
{
    // The screens of these two opened since the start of php_server, by address: invalidated with the main screens of
    // the catalog (aio_catv_mains_urls).
    /** @var array<string, bool> */
    public static $opened = array();
}

// --- The title into the list or out of it.

// Title $t into the list ($on; aio_ml_add) or out of it (aio_ml_del of aio_ml_in), $dune: the id of its card of the
// Dune ('' - none or not known). -> '' or why not, as they give it.
/**
 * @param AioCatTile $t
 * @param string $dune
 * @param bool $on
 * @return string
 */
function aio_ml_set($t, $dune, $on)
{
    if ($on)
        return aio_ml_add($t, $dune);
    $key = aio_ml_in($t, $dune);
    return $key !== '' ? aio_ml_del($key) : '';
}

// The message of why $why of aio_ml_set ('' none: null): no IMDb or TMDB, no name, the list full, its file of a later
// Melange or not readable, not written.
/**
 * @param string $why
 * @return AioAction|null
 */
function aio_ml_err($why)
{
    $keys = array('full' => 'ml_err_full', 'locked' => 'ml_err_locked', 'write' => 'ml_err_write', 'no id' => 'err_no_imdb',
        'no name' => 'ml_err_name');
    return $why === '' ? null : aio_error(isset($keys[$why]) ? $keys[$why] : 'ml_err_write', $why);
}

// The item of POP UP of tile $id of screen $from (aio_catv_tile_menu): "Добавить в «Мой список»" / "Убрать из «Моего
// списка»" by the list now (aio_ml_in by its card of the Dune known, aio_cp_card_known). None but on a tile of a title
// with IMDb or TMDB (aio_catv_tile).
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_ml_menu_items($id, $from, $lang)
{
    $t = aio_catv_tile($id, aio_tv_is_from($from));
    if (!$t || aio_ml_key($t) === '')
        return array();
    $in = aio_ml_in($t, aio_cp_card_known($t, aio_cp_mid_of($id))) !== '';
    return array(array(
        GuiMenuItemDef::caption => aio_tr($lang, $in ? 'ml_del' : 'ml_add'),
        GuiMenuItemDef::icon_url => AIO_CATV_ICONS . ($in ? 'cancel_small.png' : 'favorites_small.png'),
        GuiMenuItemDef::action => aio_input('cat_ml', array('from' => $from, 'item' => $id, 'w' => $in ? 'del' : 'add'))));
}

// After a change of the list on screen $from ('': none to build again, the own card; aio_catv_screen_of), then $post:
// the screens that show the list built again by one invalidate (aio_catv_mains_anew) - on r25 the main screens of the
// catalog and these two opened (the shell keeps rows_info by the id of a tile, the line of the info panel " · В моём
// списке" would stay old after Back), and $from on r24 too; the tab "TV" built anew (aio_tv_flag). None of them: $post.
// The one effect of every change of the list (POP UP, the button of the own card, the import).
/**
 * @param string $from
 * @param AioAction|null $post
 * @param string $why
 * @return AioAction|null
 */
function aio_ml_changed($from, $post, $why)
{
    aio_tv_flag($why);
    return aio_catv_mains_anew($post, $why, aio_catv_screen_of($from));
}

// cat_ml of POP UP (aio_ml_menu_items; w add, del) of tile $in->item of screen $in->from: the title into the list or out
// of it (aio_ml_set; its card of the Dune known, aio_cp_card_known, no request), then the screens anew (aio_ml_changed:
// the line of the info panel); not written: the message (aio_ml_err), the list as it was.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_ml_menu_act($in)
{
    $a = aio_catv_tile_act($in, array('add', 'del'));
    $t = $a['t'];
    if (!$t)
        return $a['err'];
    $why = aio_ml_set($t, aio_cp_card_known($t, aio_cp_mid_of($a['id'])), $a['w'] === 'add');
    return $why !== '' ? aio_ml_err($why) : aio_ml_changed($a['from'], null, "my list {$a['w']}");
}

// --- The screens.

// Screen $url of these two -> array(AIO_MLV_LIST or AIO_MLV_CONT, its type: 'movie', 'series', '' - both); null for
// another.
/**
 * @param string $url
 * @return array{string, string}|null
 */
function aio_mlv_screen($url)
{
    if (!preg_match('/^(' . AIO_MLV_LIST . '|' . AIO_MLV_CONT . ')(?::(movie|series))?$/D', $url, $m))
        return null;
    return array($m[1], isset($m[2]) ? $m[2] : '');
}

/**
 * @param string $url
 * @return bool
 */
function aio_mlv_is_screen($url)
{
    return aio_mlv_screen($url) !== null;
}

// The types of type $type ('' both).
/**
 * @param string $type
 * @return list<string>
 */
function aio_mlv_types($type)
{
    return $type === '' ? array('movie', 'series') : array($type);
}

// $id (with the mode of the screen or without) is of the hint tile.
/**
 * @param string $id
 * @return bool
 */
function aio_mlv_is_hint($id)
{
    list(, $id) = aio_catv_id_mode($id);
    return $id === AIO_MLV_HINT;
}

// The entry of key $key of the list, null for none.
/**
 * @param string $key
 * @return AioMlEntry|null
 */
function aio_mlv_entry($key)
{
    foreach (aio_ml_list() as $e)
    {
        if ($e['key'] === $key)
            return $e;
    }
    return null;
}

// The tile of the entry of key $key for the info panel, ENTER and POP UP: as of the row "Continue" (aio_cp_best_tile: a
// tile of a row in memory, else of the meta kept; no request for an entry not of the history), else the tile of the
// entry (aio_ml_tile); null for no such entry.
/**
 * @param string $key
 * @return AioCatTile|null
 */
function aio_mlv_tile($key)
{
    $e = aio_mlv_entry($key);
    if (!$e)
        return null;
    $t = aio_ml_tile($e);
    $m = aio_cp_best_tile($t['type'], $t['imdb_id'], $t['_tmdbId']);
    return $m ? $m : $t;
}

// The tiles of "Мой список" of the types $types, $max at most: the items of a row (id "mylist:<key>", caption, the
// poster of the entry, stickers: the rating of the entry, the badges of the history) and their tiles; the newest first.
/**
 * @param list<string> $types
 * @param int $max
 * @return list<array{item: array<string, mixed>, tile: AioCatTile}>
 */
function aio_mlv_list_cells($types, $max)
{
    $out = array();
    foreach (aio_ml_list() as $e)
    {
        if (!in_array($e['type'], $types, true))
            continue;
        if (count($out) >= $max)
            break;
        $t = aio_ml_tile($e);
        // The id of the tile is the key of the entry: a movie and a series of one number of TMDB are two.
        $t['id'] = $e['key'];
        $out[] = array('item' => aio_catv_title_item(AIO_ML_RID, $t), 'tile' => $t);
    }
    return $out;
}

// The tiles of screen $url (aio_mlv_screen): the items of a row and their tiles (for the grid of r24). "Мой список": its
// entries (aio_mlv_list_cells); "Продолжить": the cards of the row "Continue", the latest first; of its type, both on
// a screen of all; the first of its tiles with no meta to the lazy meta after those of the row (AioCpm::$more).
/**
 * @param string $url
 * @return list<array{item: array<string, mixed>, tile: AioCatTile}>
 */
function aio_mlv_cells($url)
{
    $sc = aio_mlv_screen($url);
    $max = AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS;
    if (!$sc)
        return array();
    if ($sc[0] === AIO_MLV_LIST)
        return aio_mlv_list_cells(aio_mlv_types($sc[1]), $max);
    $cells = aio_cp_tile_items(aio_mlv_types($sc[1]), $max, $miss);
    AioCpm::$more = array_slice($miss, 0, AIO_CPM_MORE);
    return array_values($cells);
}

// The tile alone of an empty screen $url: the hint of "Мой список" (its picture by the language), "Пусто" of
// "Продолжить" (ENTER: nothing).
/**
 * @param string $url
 * @param string $lang
 * @return array<string, string>
 */
function aio_mlv_empty_item($url, $lang)
{
    $sc = aio_mlv_screen($url);
    if (!$sc || $sc[0] !== AIO_MLV_LIST)
        return aio_catv_note_item(AIO_CP_RID, 'cat_row_empty', $lang);
    return aio_catv_item(aio_catv_tile_id(AIO_MLV_HINT), aio_tr($lang, 'ml_title'), AIO_MLV_HINT_IMG . ($lang === 'ru' ? 'ru' : 'en') . '.png');
}

// The item of the grid of r24 of row item $item: as one of "Show all" (aio_catv_grid_item) of its tile $t, its id and
// poster; with no tile (the hint, "Пусто") its caption and the line of $info.
/**
 * @param array<string, mixed> $item
 * @param AioCatTile|null $t
 * @param string $info
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_mlv_grid_item($item, $t, $info, $lang)
{
    $icon = $item[PluginRegularItem::icon_url];
    if ($t)
        $g = aio_catv_grid_item('', $t, $lang);
    else
        $g = array(PluginRegularFolderItem::caption => $item[PluginRegularItem::caption],
            PluginRegularFolderItem::view_item_params => array(ViewItemParams::item_detailed_info =>
                aio_clean($item[PluginRegularItem::caption]) . '|' . aio_clean($info)));
    $g[PluginRegularFolderItem::media_url] = $item[PluginRegularItem::id];
    $g[PluginRegularFolderItem::view_item_params][ViewItemParams::icon_path] = $icon;
    $g[PluginRegularFolderItem::view_item_params][ViewItemParams::item_detailed_icon_path] = $icon;
    return $g;
}

// The screen "Мой список" or "Продолжить" ($url), all its tiles at once (no stubs, no timer, no get_folder_rows): on r25
// rows of AIO_CATV_ALL_COLS as "Show all" (aio_catv_all_pane: the info panel, ENTER, POP UP, the search), on r24 the
// grid of "Show all" with POP UP. Empty: one tile, aio_mlv_empty_item.
/**
 * @param string $url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_mlv_view($url, $sel_state)
{
    $start = microtime(true);
    $lang = aio_catv_lang();
    AioCat::$lang = $lang;
    AioMlv::$opened[$url] = true;
    $cells = aio_mlv_cells($url);
    $sc = aio_mlv_screen($url);
    $list = $sc && $sc[0] === AIO_MLV_LIST;
    aio_log(sprintf('%s: screen%s: %d tiles, %.0f ms', $list ? 'my list' : 'continue', $sc && $sc[1] !== '' ? " of {$sc[1]}" : '',
        count($cells), (microtime(true) - $start) * 1000));
    $items = array();
    foreach ($cells as $c)
        $items[] = $c['item'];
    if (!$items)
        $items[] = aio_mlv_empty_item($url, $lang);
    if (aio_catv_all_ok())
    {
        $rows = array();
        foreach (array_chunk($items, AIO_CATV_ALL_COLS) as $k => $part)
            $rows[] = aio_catv_all_def("row$k", $part);
        return aio_catv_all_pane($rows, null, $sel_state, $url);
    }
    $grid = array();
    $info = $list ? aio_tr($lang, 'ml_hint') : aio_tr($lang, 'cat_row_empty');
    foreach ($items as $i => $item)
        $grid[] = aio_mlv_grid_item($item, isset($cells[$i]) ? $cells[$i]['tile'] : null, $info, $lang);
    $p = array('from' => $url);
    return aio_catv_grid_pane(array(
        PluginRegularFolderRange::total => count($grid),
        PluginRegularFolderRange::more_items_available => false,
        PluginRegularFolderRange::from_ndx => 0,
        PluginRegularFolderRange::count => count($grid),
        PluginRegularFolderRange::items => $grid),
        array(GUI_EVENT_KEY_ENTER => aio_input('cat_enter', $p, Aio::$settings['cat_card'] ? AIO_CARD_DELAY : AIO_DELAY_INPUT),
            GUI_EVENT_KEY_POPUP_MENU => aio_input('cat_menu', $p)) + aio_egg_acts());
}

// ENTER on tile $id of "Мой список" (another tile: null): the own card ("Своя карточка" on) or the card of the Dune
// by the card of the Dune of the entry, no request of it; not known - as ENTER on a tile of a row (aio_card_enter). No
// such entry (taken out on another screen): the screen is old.
/**
 * @param string $id
 * @param string $lang
 * @return AioAction|null
 */
function aio_mlv_enter($id, $lang)
{
    list(, $id) = aio_catv_id_mode($id);
    $p = AIO_ML_RID . ':';
    if (strncmp($id, $p, strlen($p)) !== 0)
        return null;
    $key = strval(substr($id, strlen($p)));
    $e = aio_mlv_entry($key);
    $t = $e ? aio_mlv_tile($key) : null;
    if (!$e || !$t)
    {
        aio_log('my list: ENTER: no such entry');
        return aio_error('cat_outdated');
    }
    if ($e['dune_id'] === '')
        return aio_card_enter($t, $lang);
    if (Aio::$settings['cat_card'] && aio_cm_id($t) !== '')
        return aio_oc_enter($t, $lang, $e['dune_id']);
    return aio_card_open($e['dune_id']);
}

// --- The row "Мой список" of the main screens.

// The row "Мой список" of the types of tab $tab ('movie', 'series'; '' both: tv.json of "TV") for a screen of rows,
// second after "Continue": its title and the first AIO_CATV_ROW_TILES titles of the list of them (aio_mlv_list_cells:
// the newest first, the entry alone, no request), no "Show all" tile, no flag; ENTER on its title the screen of its type
// (aio_mlv_screen). None when the list has none of them (the empty screen with the hint is of the heart and Fav 2). $tiles:
// its tiles by "mylist:<key>" (the file of the tab "TV").
/**
 * @param string $tab
 * @param string $lang
 * @param array<string, AioCatTile>|null $tiles
 * @param-out array<string, AioCatTile> $tiles
 * @return list<array<string, mixed>>
 */
function aio_ml_row_defs($tab, $lang, &$tiles = null)
{
    $tiles = array();
    $items = array();
    foreach (aio_mlv_list_cells(aio_mlv_types($tab), AIO_CATV_ROW_TILES) as $c)
    {
        $items[] = $c['item'];
        $tiles[AIO_ML_RID . ':' . $c['tile']['id']] = $c['tile'];
    }
    if (!$items)
        return array();
    $name = aio_tr($lang, 'ml_title');
    $row = array('key' => AIO_ML_RID, 'type' => $tab !== '' ? $tab : 'movie', 'id' => AIO_ML_RID, 'name' => $name, 'genre' => '',
        'home' => false, 'group' => '');
    $defs = aio_catv_row_defs(aio_catv_list(aio_cat_row_state($row), array(), false), $lang, $items, 0, AIO_ML_RID);
    $defs[1][PluginRow::show_all_action] = aio_catv_open_act(AIO_MLV_LIST . ($tab !== '' ? ":$tab" : ''), $name);
    aio_log(sprintf('my list: row %s: %d tiles', $tab !== '' ? $tab : 'all', count($items)));
    return $defs;
}

// --- The ways to the screens.

// The keys Fav 2 and Fav 1 of screen $from.
/**
 * @param string $from
 * @return array<string, AioAction>
 */
function aio_mlv_key_acts($from)
{
    return array(
        AIO_MLV_KEY_LIST => aio_input('cat_fav', array('from' => $from, 'w' => 'list')),
        AIO_MLV_KEY_CONT => aio_input('cat_fav', array('from' => $from, 'w' => 'continue')));
}

// Folder view $view of screen $media_url (aio_folder_view: every screen of Melange - the catalog, "Show all", a search,
// the own card, a person, the list of streams, the details, the settings, these two) with the keys Fav 2 and Fav 1 in
// its actions, the actions of the screen kept. A file of the tab "TV" is no such view: the keys stay with the shell
// there, as the colour keys (tv_tab.php). A new change_behaviour with actions on a screen of the catalog replaces its
// actions whole and loses the Fav keys: add aio_mlv_key_acts to them (as aio_catv_all_tick does).
/**
 * @param mixed $view
 * @param string $media_url
 * @return mixed
 */
function aio_mlv_keys($view, $media_url)
{
    if (!is_array($view) || !isset($view[PluginFolderView::data]) || !is_array($view[PluginFolderView::data]))
        return $view;
    // "actions" of every kind of view (PluginRowsFolderView, PluginRegularFolderView, PluginGCompsFolderView,
    // PluginControlsFolderView).
    $k = PluginRegularFolderView::actions;
    $acts = isset($view[PluginFolderView::data][$k]) && is_array($view[PluginFolderView::data][$k]) ? $view[PluginFolderView::data][$k] : array();
    $view[PluginFolderView::data][$k] = $acts + aio_mlv_key_acts($media_url);
    return $view;
}

// The screen of $w ('list': "Мой список", 'continue': "Продолжить"; another: null) of all the titles from screen $from:
// on it already - nothing; from one of these screens (of a type too) - in its place (Back goes where it went from that
// one: no stack of them); else over $from.
/**
 * @param string $w
 * @param string $from
 * @return AioAction|null
 */
function aio_mlv_open($w, $from)
{
    if ($w !== 'list' && $w !== 'continue')
        return null;
    $url = $w === 'list' ? AIO_MLV_LIST : AIO_MLV_CONT;
    if ($from === $url)
    {
        aio_log("my list: open $w: on it already");
        return null;
    }
    $replace = aio_mlv_is_screen($from);
    aio_log("my list: open $w" . ($replace ? ' in place' : ''));
    $caption = aio_tr(aio_catv_lang(), $w === 'list' ? 'ml_title' : 'cp_row_continue');
    return $replace ? aio_replace_act($url, $caption) : aio_catv_open_act($url, $caption);
}

// --- The import of the Favorites of the Dune.

// The items of POP UP of tile $id of screen $from (after those of a tile: aio_catv_tile_menu): on the screen
// "Мой список" (of a type too) and on a tile of the row "Мой список" "Добавить из «Избранного» Дюны"; none on another.
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_mlv_menu_items($id, $from, $lang)
{
    $sc = aio_mlv_screen($from);
    list(, $x) = aio_catv_id_mode($id);
    if (!($sc && $sc[0] === AIO_MLV_LIST) && strpos($x, AIO_ML_RID . ':') !== 0)
        return array();
    return array(array(
        GuiMenuItemDef::caption => aio_tr($lang, 'ml_import'),
        GuiMenuItemDef::icon_url => AIO_CATV_ICONS . 'favorites_small.png',
        GuiMenuItemDef::action => aio_input('cat_ml_import', array('from' => $from), AIO_CARD_DELAY)));
}

// The pair of card $mid of the Dune (links.php) now: kept, else of the card of the shell (aio_pg_shell_pair, kept), else
// asked (aio_pg_link_now, in $left s, AIO_PG_LINK_S at most; none in a pause of the requests, none past
// AIO_MLV_IMPORT_N of them). null: none ("no pair" kept for good, a failure, no time or requests left); $asked: one
// more when asked.
/**
 * @param string $mid
 * @param float $left
 * @param int $asked
 * @return AioPgLink|null
 */
function aio_mlv_fav_pair($mid, $left, &$asked)
{
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    if ($l && !isset($l['none']) && !isset($l['fail']))
        return $l;
    if ($l && isset($l['none']))
        return null;
    // The card of the shell first: no request, not counted.
    $l = isset(AioPgLinks::$shell_seen[$mid]) ? null : aio_pg_shell_pair($mid);
    if ($l)
    {
        aio_pg_link_put($mid, $l);
        return $l;
    }
    if ($left < 1 || $asked >= AIO_MLV_IMPORT_N || aio_pg_paused())
        return null;
    $asked++;
    $r = aio_pg_link_now($mid, min(AIO_PG_LINK_S, $left));
    return $r['link'];
}

// The tile of movie $f of the Favorites (aio_ml_fav_read) by its pair $l: the best tile of its IMDb or TMDB
// (aio_cp_best_tile: of a row in memory, else of the meta kept - the pictures and the ratings of the catalog, not the
// poster of dunemdb with auth=), else of the Favorites (its name, year, poster - its address, else its picture on the
// Dune: a root item has no address; a name of an item of the root "Name (2008)": the year of it). The type of the pair,
// else of the Favorites, else by its TMDB. null: no IMDb and no TMDB.
/**
 * @param AioMlFav $f
 * @param AioPgLink $l
 * @return AioCatTile|null
 */
function aio_mlv_fav_tile($f, $l)
{
    $type = isset($l['type']) && ($l['type'] === 'movie' || $l['type'] === 'series') ? $l['type'] :
        ($f['type'] !== '' ? $f['type'] : (isset($l['tmdbtv']) ? 'series' : 'movie'));
    $imdb = isset($l['imdb']) ? $l['imdb'] : '';
    $k = $type === 'series' ? 'tmdbtv' : 'tmdb';
    $tmdb = isset($l[$k]) ? $l[$k] : '';
    if ($imdb === '' && $tmdb === '')
        return null;
    $m = aio_cp_best_tile($type, $imdb, $tmdb);
    if ($m)
        return $m;
    $name = $f['name'];
    $year = $f['year'];
    if ($year === '' && preg_match('/^(.+) \(([0-9]{4})\)$/su', $name, $x))
    {
        $name = $x[1];
        $year = $x[2];
    }
    $t = aio_cat_tile(array('id' => $imdb !== '' ? $imdb : "tmdb:$tmdb", 'name' => $name, 'type' => $type, 'imdb_id' => $imdb,
        '_tmdbId' => $tmdb, 'year' => $year, 'poster' => $f['poster']), $type);
    // A path is no address for aio_cat_tile.
    if ($t && $t['poster'] === '')
        $t['poster'] = $f['file'];
    return $t;
}

// "Добавить из «Избранного» Дюны" of screen $from: the movies and series of the shell in the Favorites of the Dune
// (aio_ml_fav_read, read only) not in the list (by their card of the Dune and by IMDb or TMDB) to the end of it in
// their order (aio_ml_append), nothing taken out; IMDb and TMDB by the pairs first, then the card of the shell or a
// request (aio_mlv_fav_pair: AIO_MLV_IMPORT_N in AIO_MLV_IMPORT_S at most). One without them: not added, counted (a
// repeat of the import goes on with them). Then the screens of the list anew (aio_ml_changed: the list, not the hint)
// and the message: "Добавлено из «Избранного»: N" (with the ones not added; the list full), "В «Избранном» Дюны
// нет нового", or why the file was not written (aio_ml_err). One line of the log, no address.
/**
 * @param string $from
 * @param float $budget
 * @return AioAction|null
 */
function aio_mlv_import($from, $budget = AIO_MLV_IMPORT_S)
{
    $t0 = microtime(true);
    $lang = aio_catv_lang();
    $favs = aio_ml_fav_read();
    $add = array();
    $there = 0;
    $skip = 0;
    $asked = 0;
    foreach ($favs as $f)
    {
        if (aio_ml_find_dune($f['dune_id']))
        {
            $there++;
            continue;
        }
        $l = aio_mlv_fav_pair($f['dune_id'], $budget - (microtime(true) - $t0), $asked);
        $t = $l ? aio_mlv_fav_tile($f, $l) : null;
        if ($t && aio_ml_in($t) !== '')
            $there++;
        else if ($t)
            $add[] = array($t, $f['dune_id']);
        else
            $skip++;
    }
    list($why, $n) = aio_ml_append($add);
    aio_log(sprintf('my list: import: %d in the Favorites, %d there, %d asked, %d added, %d not%s, %.0f ms', count($favs), $there,
        $asked, $n, $skip, $why !== '' ? ", $why" : '', (microtime(true) - $t0) * 1000));
    if ($n === 0 && $why !== '' && $why !== 'full')
        return aio_ml_err($why);
    $lines = array();
    if ($skip)
        $lines = array_merge($lines, aio_dialog_lines(sprintf(aio_tr($lang, 'ml_import_skip'), $skip)));
    if ($why === 'full')
        $lines = array_merge($lines, aio_dialog_lines(aio_tr($lang, 'ml_err_full')));
    $title = $n === 0 && !$lines ? aio_tr($lang, 'ml_import_none') : sprintf(aio_tr($lang, 'ml_import_n'), $n);
    $msg = aio_dialog($title, $lines, array('OK' => aio_close_and(null)));
    return $n === 0 ? $msg : aio_ml_changed($from, $msg, 'my list import');
}
