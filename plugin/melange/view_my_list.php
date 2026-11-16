<?php
// "Мой список" (0.62.0) on the screens of php_server: the title into the list or out of it, its messages, POP UP of a
// tile of the catalog and of the tab "TV", the one effect of a change (aio_ml_changed); the screens "Мой список" and
// "Продолжить" (Continue Watching), each as "Show all" of a row of the catalog (view_catalog.php: on r25 rows of
// AIO_CATV_ALL_COLS with the info panel, on r24 the grid): "Мой список" the entries of its file in their order,
// "Продолжить" all the cards of the row "Continue" (continue.php), not AIO_CATV_ROW_TILES; the meta of their tiles of
// no request at the opening (aio_cpm_local), then r25 the ticks of a timer of the screen (aio_mlv_tick), r24 the first
// tiles asked in the opening (aio_cpm_ask_due). The ways to them: the heart and the clock on the strip of the catalog
// and in its POP UP (aio_catv_strip_rows, aio_catv_menu) and the keys Fav 2 (key_favorites: "Мой список") and Fav 1
// (key_dune: "Продолжить") of the remote on every screen of Melange (aio_mlv_keys; the player has no folder view). An empty
// "Мой список": the hint tile, its POP UP the import of the Favorites of the Dune (aio_mlv_import). The file, the
// copy of the backup and the reading of the Favorites are of my_list.php (the settings page has it alone).
// Needs my_list.php, view_catalog.php (aio_catv_*), continue.php, cp_meta.php (AioCpm, aio_cpm_*), links.php (aio_pg_links,
// aio_pg_link_now, aio_pg_paused), progress.php, catalog.php (aio_cat_tile), catalog_card.php (aio_card_enter,
// aio_card_open, AIO_CARD_DELAY), view_card.php (aio_oc_enter), card_meta.php (aio_cm_id), tv_tab.php (aio_tv_flag,
// aio_tv_is_from), playback.php (aio_replace_act), main.php (aio_tr, aio_input, aio_error, aio_dialog).
// PHP 5.3.6, firmware API only.

// The rid of the tiles of "Мой список": "mylist:<the key of the entry>" (aio_catv_tile).
define('AIO_ML_RID', 'mylist');
// The screens of all the titles (the heart and the clock, Fav 2 and Fav 1); of one type (ENTER on the title of the row
// of a tab) the address and ":movie" or ":series".
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
// The import, of two inputs: the item of POP UP the usual 60 s (aio_mlv_import_ask: the Favorites read, how many not in
// the list), then the import itself (aio_mlv_import), the shell waiting for it (its operation_timeout, aio_mlv_import_s)
// AIO_MLV_IMPORT_T0_S and AIO_MLV_IMPORT_PER_S a title of the Favorites not in the list (its pair and its meta: ~0.6
// and 0.2-1.9 s, AIO_PG_LINK_S and AIO_CPM_FILL_S at most), AIO_MLV_IMPORT_MAX_S at most (php_server answers nothing
// else meanwhile: more is a hang to the user; ~180 titles of requests, the cards of the shell and the pairs kept ask
// none); the import AIO_MLV_IMPORT_TAIL_S less (the files written and the answer after it). The ones not paired in it
// not added, counted in the message (a repeat goes on); the meta of one not asked in it lazily (aio_cpm_step).
define('AIO_MLV_IMPORT_T0_S', 60);
define('AIO_MLV_IMPORT_PER_S', 3);
define('AIO_MLV_IMPORT_MAX_S', 600);
define('AIO_MLV_IMPORT_TAIL_S', 15);
// r25: the meta of the tiles of the screen of no meta at its opening by the ticks of a timer of its own (aio_mlv_tick):
// the first one AIO_MLV_TICK_FIRST_MS after the view (no stubs, no get_folder_rows to wait for), the next ones (the
// view anew after one that got some, or change_behaviour) AIO_CPM_STEP_PAR requests in AIO_CPM_STEP_PAR *
// AIO_CPM_RATE_S / AIO_CPM_RATE_N s at most after the last one (aio_mlv_tick_ms: 4 in 2 s, 120 a minute, the share of
// the catalog server); AIO_MLV_TICK_MAX ticks of an opening at most (~1000 tiles of "Продолжить": 20 * 4-5 requests),
// the count anew at an opening, not at a view anew of its own tick (one within AIO_MLV_ANEW_S of it).
define('AIO_MLV_TICK_FIRST_MS', 750);
define('AIO_MLV_TICK_MAX', 20);
define('AIO_MLV_ANEW_S', 5.0);
// r24 (the grid, no timer: neither the timer of view_regular nor the grid after invalidate proven there): the opening
// asks the meta of the first AIO_MLV_OPEN_TILES of its tiles with none (the 5x2 in view at first), after the steps of no
// request, all in AIO_MLV_OPEN_S s of it; the next ones at the next opening.
define('AIO_MLV_OPEN_TILES', 10);
define('AIO_MLV_OPEN_S', 2.0);

// The state of the ticks of a screen of Fav: a new field of it goes to aio_mlv_state too.
class AioMlv
{
    // The screens of these two opened since the start of php_server, by address: invalidated with the main screens of
    // the catalog (aio_catv_mains_urls).
    /** @var array<string, bool> */
    public static $opened = array();
    // r25: the screen whose timer is set (aio_mlv_view, aio_mlv_tick; '': none, or its tick came), the ticks of its
    // opening, the tiles of it with no meta of its view last built (array(type, IMDb, TMDB), of its first
    // AIO_CPM_SCREEN_MAX: the queue of its ticks), the cards of them with no pair ("Продолжить": the pairs its ticks
    // ask), when its last tick ended (microtime; 0: none).
    /** @var string */
    public static $tick_url = '';
    /** @var int */
    public static $ticks = 0;
    /** @var list<array{string, string, string}> */
    public static $miss = array();
    /** @var list<string> */
    public static $cards = array();
    /** @var float */
    public static $tick_t = 0.0;
    // The screen of that last tick ('': none; a tick of the main screen within AIO_PG_FAV_QUIET_S of it waits, aio_pg_tick).
    /** @var string */
    public static $ticked = '';
    // The screen a tick invalidated ('': none) and when (microtime): its next view is no opening.
    /** @var string */
    public static $anew = '';
    /** @var float */
    public static $anew_t = 0.0;
    // The screen of the last tick that invalidated ('': none; at AioMlv::$anew_t, kept after its view anew; an input of
    // the user ends it, aio_mlv_input): the views of the other screens within AIO_MLV_ANEW_S of it are of that
    // invalidate (aio_mlv_inv).
    /** @var string */
    public static $inv = '';
}

// --- The title into the list or out of it.

// Title $t into the list ($on; aio_ml_add: its tile in memory to the meta kept, aio_cpm_of_add) or out of it (aio_ml_del
// of aio_ml_in; its meta kept stays till pushed out), $dune: the id of its card of the Dune ('' - none or not known).
// -> '' or why not, as they give it.
/**
 * @param AioCatTile $t
 * @param string $dune
 * @param bool $on
 * @return string
 */
function aio_ml_set($t, $dune, $on)
{
    if ($on)
    {
        $why = aio_ml_add($t, $dune);
        if ($why === '')
            aio_cpm_of_add($t);
        return $why;
    }
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
// the meta kept, else a tile of a row in memory; no request for an entry not of the history), else the tile of the
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
// poster of the entry, stickers: the rating of the best tile, else of the entry, the badges of the history) and their
// tiles, the best (aio_cp_best_tile: of the meta kept, else of a row in memory) else of the entry; the newest first.
// $miss: those with no best tile, in their order (array(type, IMDb, TMDB): the lazy meta, AioCpm::$ml and $more).
/**
 * @param list<string> $types
 * @param int $max
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array{item: array<string, mixed>, tile: AioCatTile}>
 */
function aio_mlv_list_cells($types, $max, &$miss = null)
{
    $out = array();
    $miss = array();
    $map = null;
    foreach (aio_ml_list() as $e)
    {
        if (!in_array($e['type'], $types, true))
            continue;
        if (count($out) >= $max)
            break;
        if ($map === null)
            $map = aio_cp_tile_map();
        $t = aio_ml_tile($e);
        $m = aio_cp_best_tile($t['type'], $t['imdb_id'], $t['_tmdbId'], $map);
        if (!$m)
            $miss[] = array($t['type'], $t['imdb_id'], $t['_tmdbId']);
        else if ($m['imdbRating'] !== '')
            $t['imdbRating'] = $m['imdbRating'];
        // The id of the tile is the key of the entry: a movie and a series of one number of TMDB are two.
        $t['id'] = $e['key'];
        $item = aio_catv_title_item(AIO_ML_RID, $t);
        if ($m)
        {
            $t = $m;
            $t['id'] = $e['key'];
        }
        $out[] = array('item' => $item, 'tile' => $t);
    }
    return $out;
}

// The titles of the tiles of screen $sc (aio_mlv_screen) in their order, as aio_mlv_cells has them: array(type, IMDb,
// TMDB) of the entries of "Мой список", of the pairs of the cards of "Продолжить" (aio_cp_ids; none of a card with no
// pair: '' both). $mids: the movie_id of each, in that order ('' of an entry of the list).
/**
 * @param array{string, string} $sc
 * @param list<string>|null $mids
 * @param-out list<string> $mids
 * @return list<array{string, string, string}>
 */
function aio_mlv_titles($sc, &$mids = null)
{
    $max = AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS;
    $types = aio_mlv_types($sc[1]);
    $out = array();
    $mids = array();
    if ($sc[0] === AIO_MLV_LIST)
    {
        foreach (aio_ml_list() as $e)
        {
            if (count($out) >= $max)
                break;
            if (in_array($e['type'], $types, true))
            {
                $out[] = array($e['type'], $e['imdb_id'], $e['_tmdbId']);
                $mids[] = '';
            }
        }
        return $out;
    }
    $idx = aio_pg_index();
    foreach (aio_cp_items($types, $max) as $mid => $it)
    {
        $out[] = aio_cp_ids(strval($mid), $idx[strval($mid)]['series'] ? true : false);
        $mids[] = strval($mid);
    }
    return $out;
}

// The titles of screen $sc whose meta it keeps and asks: its first AIO_CPM_SCREEN_MAX (aio_mlv_titles), their keys
// kept over the other ones of the file (AioCpm::$hold), the cards of them with no pair to ask (AioMlv::$cards).
/**
 * @param array{string, string} $sc
 * @return list<array{string, string, string}>
 */
function aio_mlv_hold($sc)
{
    $titles = array_slice(aio_mlv_titles($sc, $mids), 0, AIO_CPM_SCREEN_MAX);
    AioCpm::$hold = aio_cpm_keys($titles);
    AioMlv::$cards = array();
    foreach ($titles as $i => $m)
    {
        if ($m[1] === '' && $m[2] === '' && $mids[$i] !== '')
            AioMlv::$cards[] = $mids[$i];
    }
    return $titles;
}

// The tiles of screen $url (aio_mlv_screen): the items of a row and their tiles (for the grid of r24). "Мой список": its
// entries (aio_mlv_list_cells); "Продолжить": the cards of the row "Continue", the latest first; of its type, both on
// a screen of all. Its tiles with no meta of its first AIO_CPM_SCREEN_MAX (aio_mlv_hold, held anew): the queue of its
// ticks (AioMlv::$miss), the first of them to the lazy meta of a main screen after those of the rows (AioCpm::$more).
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
    aio_mlv_hold($sc);
    if ($sc[0] === AIO_MLV_LIST)
        $cells = aio_mlv_list_cells(aio_mlv_types($sc[1]), $max, $miss);
    else
        $cells = array_values(aio_cp_tile_items(aio_mlv_types($sc[1]), $max, $miss));
    AioMlv::$miss = array();
    foreach ($miss as $m)
    {
        if (isset(AioCpm::$hold[aio_cpm_key($m[0], $m[1], $m[2])]))
            AioMlv::$miss[] = $m;
    }
    AioCpm::$more = array_slice(AioMlv::$miss, 0, AIO_CPM_MORE);
    return $cells;
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

// The screen "Мой список" or "Продолжить" ($url), all its tiles at once (no stubs, no get_folder_rows): on r25 rows of
// AIO_CATV_ALL_COLS as "Show all" (aio_catv_all_pane: the info panel, ENTER, POP UP, the search), on r24 the grid of
// "Show all" with POP UP. Empty: one tile, aio_mlv_empty_item. Before its tiles the meta of them with no request, of
// its first AIO_CPM_SCREEN_MAX (aio_mlv_hold; aio_cpm_local: kept, of a row in memory, of a copy of a row on the disk;
// kept at one write, with those asked on r24); then r24 the first AIO_MLV_OPEN_TILES of them to ask asked in
// AIO_MLV_OPEN_S s of the opening (aio_cpm_ask_due, as a build), r25 the timer of the screen when there is more to ask
// (aio_mlv_tick_set: the count of the ticks anew at an opening, not at a view of its own tick). One line of the log.
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
    $sc = aio_mlv_screen($url);
    $list = $sc && $sc[0] === AIO_MLV_LIST;
    $rows_ok = aio_catv_all_ok();
    // Another screen than the one of a tick, within AIO_MLV_ANEW_S of its invalidate (the shell builds the screens of the
    // stack again): the state of the timer of that screen kept as it is, no timer of this one.
    $inv = aio_mlv_inv();
    $keep = $inv !== '' && $inv !== $url ? aio_mlv_state() : null;
    $ago = microtime(true) - AioMlv::$anew_t;
    $anew = AioMlv::$anew === $url && $ago >= 0 && $ago < AIO_MLV_ANEW_S;
    AioMlv::$anew = '';
    if (!$anew)
        AioMlv::$ticks = 0;
    $titles = $sc ? aio_mlv_hold($sc) : array();
    list($put, $renew, $due, , $mem, $disk) = aio_cpm_local($titles, aio_cpm_read(), time());
    $net = '';
    if (!$rows_ok)
    {
        list($got_put, $asked, $got, $mark) = aio_cpm_ask_due($due, $start, AIO_MLV_OPEN_S, AIO_MLV_OPEN_TILES);
        $put = array_merge($put, $got_put);
        $net = sprintf(', %d asked, %d got%s', $asked, $got, $mark);
    }
    // Within the window of a tick: written with the titles of the screen of the tick held ($keep), not of this one - a
    // full file pushes out these first, not those.
    if ($put)
    {
        if ($keep !== null)
            AioCpm::$hold = $keep['hold'];
        aio_cpm_put_all($put, $renew);
    }
    $cells = aio_mlv_cells($url);
    $ms = $rows_ok && $keep === null ? aio_mlv_tick_set($url) : 0;
    $why = '';
    if ($rows_ok)
        $why = $ms ? ", timer in $ms ms" : ', no timer: ' . ($keep !== null ? "under $inv" :
            (AioMlv::$ticks >= AIO_MLV_TICK_MAX ? 'limit' : aio_mlv_why()));
    aio_log(sprintf('%s: screen%s%s: %d tiles, %d of rows in memory, %d of rows on the disk%s, %d with no meta, %.0f ms%s',
        $list ? 'my list' : 'continue', $sc && $sc[1] !== '' ? " of {$sc[1]}" : '', $anew ? ' anew' : '', count($cells), $mem,
        $disk, $net, count(AioMlv::$miss), (microtime(true) - $start) * 1000, $why));
    if ($keep !== null)
        aio_mlv_restore($keep);
    $items = array();
    foreach ($cells as $c)
        $items[] = $c['item'];
    if (!$items)
        $items[] = aio_mlv_empty_item($url, $lang);
    if ($rows_ok)
    {
        $rows = array();
        foreach (array_chunk($items, AIO_CATV_ALL_COLS) as $k => $part)
            $rows[] = aio_catv_all_def("row$k", $part);
        return aio_catv_all_pane($rows, null, $sel_state, $url, $ms);
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

// r25: why a tick of the screen of the view last built would ask nothing (aio_pg_tick_why of its tiles with no meta,
// AioMlv::$miss, and the cards of them with no pair, AioMlv::$cards: none on "Мой список") -> '' or why not.
/**
 * @return string
 */
function aio_mlv_why()
{
    return aio_pg_tick_why(AioMlv::$miss, AioMlv::$cards);
}

// r25: the screen of the last tick that invalidated (AioMlv::$inv) within AIO_MLV_ANEW_S of it, else ''.
/**
 * @return string
 */
function aio_mlv_inv()
{
    $ago = microtime(true) - AioMlv::$anew_t;
    return AioMlv::$inv !== '' && $ago >= 0 && $ago < AIO_MLV_ANEW_S ? AioMlv::$inv : '';
}

// r25: a main screen of rows built now is built again by the tick of a screen of Fav over it (aio_mlv_inv), whose
// timer lives (its view anew to come, or set again by it) -> that screen, '' none (an opening: as it is).
/**
 * @return string
 */
function aio_mlv_under()
{
    $inv = aio_mlv_inv();
    return $inv !== '' && (AioMlv::$anew === $inv || AioMlv::$tick_url === $inv) ? $inv : '';
}

// r25: input $control of the handler of Melange (aio_handle_user_input): one of the user (not a timer, *_tick, *_load,
// nor the info panel of the focus, cat_info, of a view built again too) ends the window of the invalidate of a tick
// (AioMlv::$inv): a screen asked after it is an opening (ENTER, the keys Fav, POP UP) as before.
/**
 * @param mixed $control
 * @return void
 */
function aio_mlv_input($control)
{
    if (AioMlv::$inv === '' || !is_string($control) || in_array($control, array('cat_info', 'tsi_state', 'tv_timer'), true) ||
        preg_match('/_(tick|load)$/D', $control))
        return;
    AioMlv::$inv = '';
}

// The state of the timer of a screen (aio_mlv_view of another one within the window of its invalidate keeps it): the
// timer, the ticks, the queues, its view anew, its titles held of the file of the meta, the lazy meta of a main screen.
/**
 * @return AioMlvState
 */
function aio_mlv_state()
{
    return array('tick_url' => AioMlv::$tick_url, 'ticks' => AioMlv::$ticks, 'miss' => AioMlv::$miss, 'cards' => AioMlv::$cards,
        'anew' => AioMlv::$anew, 'hold' => AioCpm::$hold, 'more' => AioCpm::$more);
}

// The state $s of aio_mlv_state back.
/**
 * @param AioMlvState $s
 * @return void
 */
function aio_mlv_restore($s)
{
    AioMlv::$tick_url = $s['tick_url'];
    AioMlv::$ticks = $s['ticks'];
    AioMlv::$miss = $s['miss'];
    AioMlv::$cards = $s['cards'];
    AioMlv::$anew = $s['anew'];
    AioCpm::$hold = $s['hold'];
    AioCpm::$more = $s['more'];
}

// r25: ms till the next tick of a screen: AIO_MLV_TICK_FIRST_MS at least, AIO_CPM_STEP_PAR requests in their share of
// AIO_CPM_RATE_N in AIO_CPM_RATE_S after the last tick (AioMlv::$tick_t).
/**
 * @return int
 */
function aio_mlv_tick_ms()
{
    $gap = 1000.0 * AIO_CPM_STEP_PAR * AIO_CPM_RATE_S / AIO_CPM_RATE_N;
    $since = AioMlv::$tick_t > 0 ? (microtime(true) - AioMlv::$tick_t) * 1000 : $gap;
    return (int) max(AIO_MLV_TICK_FIRST_MS, ceil($gap - max(0.0, $since)));
}

// r25: the view of screen $url is made: its timer (AioMlv::$tick_url) when there is more to ask (aio_mlv_why) under
// AIO_MLV_TICK_MAX ticks of the opening. -> its ms (aio_mlv_tick_ms), 0: no timer.
/**
 * @param string $url
 * @return int
 */
function aio_mlv_tick_set($url)
{
    $ok = AioMlv::$ticks < AIO_MLV_TICK_MAX && aio_mlv_why() === '';
    AioMlv::$tick_url = $ok ? $url : '';
    return $ok ? aio_mlv_tick_ms() : 0;
}

// A screen of Melange $url is asked of get_folder_view (aio_folder_view): another than the one of the timer - no more
// ticks of it, nor a view anew of its tick. Not a main screen of rows built again (AioCat::$mains: invalidated with the
// screen by its tick, the shell may build it first; else the view anew of the screen was an opening, its count of the
// ticks 0 again and the limit gone), nor another screen within AIO_MLV_ANEW_S of the invalidate of a tick (aio_mlv_inv:
// the shell may build the screens of the stack again, a screen of Fav lower in it would take the timer).
/**
 * @param string $url
 * @return void
 */
function aio_mlv_view_asked($url)
{
    $inv = aio_mlv_inv();
    if (in_array($url, AioCat::$mains, true) || ($inv !== '' && $inv !== $url))
        return;
    if (AioMlv::$anew !== '' && AioMlv::$anew !== $url)
        AioMlv::$anew = '';
    if (AioMlv::$tick_url === '' || AioMlv::$tick_url === $url)
        return;
    $sc = aio_mlv_screen(AioMlv::$tick_url);
    AioMlv::$tick_url = '';
    aio_log(sprintf('%s: screen: tick %d/%d: no more, the screen left', $sc && $sc[0] === AIO_MLV_CONT ? 'continue' : 'my list',
        AioMlv::$ticks, AIO_MLV_TICK_MAX));
}

// r25: a tick of the timer of screen $url (aio_mlv_tick_set), AIO_CPM_TICK_S at most, as aio_pg_tick: on "Продолжить"
// the pairs of its cards with none (aio_pg_link_step of AioMlv::$cards; one got: the tiles of no meta again), then the
// meta of its tiles with no meta (aio_cpm_step of AioMlv::$miss, its order: the first tiles first) in what is left.
// Some got: the view anew with the main screens of the catalog (aio_catv_mains_anew, the screen first, no post_action;
// the row "Continue" with the rating after Back; the view sets the timer again itself, the focus by sel_state; not the
// other screens of Fav opened, AioMlv::$inv: the window of the invalidate). None
// got, more to ask: the timer again (aio_catv_all_again, aio_mlv_tick_ms); else nothing. Not the tick of the timer set
// (another screen, an old one): nothing. A line of the log of each.
/**
 * @param string $url
 * @return AioAction|null
 */
function aio_mlv_tick($url)
{
    $sc = aio_mlv_screen($url);
    $who = $sc !== null && $sc[0] === AIO_MLV_CONT ? 'continue' : 'my list';
    if (AioMlv::$tick_url !== $url)
    {
        aio_log("$who: screen: tick: no step, " . (AioMlv::$tick_url === '' ? 'no timer on it' : 'a tick of another screen'));
        return null;
    }
    AioMlv::$tick_url = '';
    $n = ++AioMlv::$ticks;
    $t = microtime(true);
    $rounds = 0;
    $pairs = 0;
    if (AioMlv::$cards)
        aio_pg_link_step(min(AIO_PG_LINK_S, AIO_CPM_TICK_S), true, $rounds, $pairs, AioMlv::$cards);
    // A card paired: its tile has ids now, its meta to ask (the tiles of no meta again, AioMlv::$miss).
    if ($pairs)
        aio_mlv_cells($url);
    $got = aio_cpm_step(AIO_CPM_TICK_S - (microtime(true) - $t), AIO_CPM_N - $rounds, true, AioMlv::$miss);
    AioMlv::$tick_t = microtime(true);
    AioMlv::$ticked = $url;
    $why = $n >= AIO_MLV_TICK_MAX ? 'limit' : aio_mlv_why();
    $left = count(aio_cpm_todo(AioMlv::$miss)) + (AioMlv::$cards ? count(aio_pg_link_queue(AioMlv::$cards)) : 0);
    $act = null;
    if ($pairs + $got > 0)
    {
        AioMlv::$anew = $url;
        AioMlv::$inv = $url;
        AioMlv::$anew_t = microtime(true);
        $act = aio_catv_mains_anew(null, "after a tick of $url", $url, false);
        $urls = $act ? $act[GuiAction::data][PluginInvalidateFoldersActionData::media_urls] : array();
        $what = count($urls) > 1 ? 'invalidate with the main screens' : 'invalidate';
        $what .= $why !== '' ? ", no more: $why" : '';
    }
    else if ($why === '')
    {
        AioMlv::$tick_url = $url;
        $ms = aio_mlv_tick_ms();
        $act = aio_catv_all_again($url, $ms);
        $what = "again in $ms ms";
    }
    else
        $what = "no more: $why";
    aio_log(sprintf('%s: screen: tick %d/%d: %d pairs, %d got, %d left, %.0f ms, %s', $who, $n, AIO_MLV_TICK_MAX, $pairs, $got,
        $left, (microtime(true) - $t) * 1000, $what));
    return $act;
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
// the newest first, the entry and its best tile, no request), no "Show all" tile, no flag; ENTER on its title the screen of its type
// (aio_mlv_screen). None when the list has none of them (the empty screen with the hint is of the heart and Fav 2). $tiles:
// its tiles by "mylist:<key>" (the file of the tab "TV"); $miss: those with no meta (the lazy meta of a main screen,
// AioCpm::$ml: aio_catv_main_view puts them there).
/**
 * @param string $tab
 * @param string $lang
 * @param array<string, AioCatTile>|null $tiles
 * @param-out array<string, AioCatTile> $tiles
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array<string, mixed>>
 */
function aio_ml_row_defs($tab, $lang, &$tiles = null, &$miss = null)
{
    $tiles = array();
    $items = array();
    foreach (aio_mlv_list_cells(aio_mlv_types($tab), AIO_CATV_ROW_TILES, $miss) as $c)
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
// "Мой список" (of a type too) and on a tile of the row "Мой список" "Добавить из «Избранного» Дюны" (its first input,
// aio_mlv_import_ask; the Favorites not read for the menu); on the screen "Продолжить" (of a type too; any tile, "Пусто"
// too) "Вернуть убранные (N)" when N of all types are off the row (aio_cp_hidden_n, aio_cp_unhide_act; the icon
// recent_small.png of the shell of r25, none in r22 and r24: return_small.png there); none on another.
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_mlv_menu_items($id, $from, $lang)
{
    $sc = aio_mlv_screen($from);
    if ($sc && $sc[0] === AIO_MLV_CONT)
    {
        $n = aio_cp_hidden_n();
        return $n ? array(array(
            GuiMenuItemDef::caption => sprintf(aio_tr($lang, 'cp_unhide'), $n),
            GuiMenuItemDef::icon_url => AIO_CATV_ICONS . (aio_fw_r() >= 25 ? 'recent_small.png' : 'return_small.png'),
            GuiMenuItemDef::action => aio_input('cat_unhide', array('from' => $from)))) : array();
    }
    list(, $x) = aio_catv_id_mode($id);
    if (!($sc && $sc[0] === AIO_MLV_LIST) && strpos($x, AIO_ML_RID . ':') !== 0)
        return array();
    return array(array(
        GuiMenuItemDef::caption => aio_tr($lang, 'ml_import'),
        GuiMenuItemDef::icon_url => AIO_CATV_ICONS . 'favorites_small.png',
        GuiMenuItemDef::action => aio_input('cat_ml_import', array('from' => $from), AIO_CARD_DELAY)));
}

// The first input of the import of screen $from: the Favorites of the Dune read (aio_ml_fav_read), the movies of no
// card of the Dune of the list counted, n. None: "В «Избранном» Дюны нет нового" at once. Else the import itself
// (aio_mlv_import) in an input of its own, the shell waiting aio_mlv_import_s(n) for it, the import that less
// AIO_MLV_IMPORT_TAIL_S (its param s, aio_mlv_import_budget). One line of the log.
/**
 * @param string $from
 * @return AioAction
 */
function aio_mlv_import_ask($from)
{
    $favs = aio_ml_fav_read();
    $n = aio_mlv_fav_new($favs);
    $s = aio_mlv_import_s($n);
    aio_log(sprintf('my list: import: %d in the Favorites, %d new%s', count($favs), $n, $n ? ", $s s" : ''));
    if (!$n)
        return aio_dialog(aio_tr(aio_catv_lang(), 'ml_import_none'), array(), array('OK' => aio_close_and(null)));
    return aio_input('cat_ml_import_go', array('from' => $from, 's' => strval($s - AIO_MLV_IMPORT_TAIL_S)), AIO_CARD_DELAY,
        $s * 1000);
}

// s the shell waits for an import of $n titles of the Favorites not in the list: AIO_MLV_IMPORT_T0_S and
// AIO_MLV_IMPORT_PER_S each, AIO_MLV_IMPORT_MAX_S at most.
/**
 * @param int $n
 * @return int
 */
function aio_mlv_import_s($n)
{
    return min(AIO_MLV_IMPORT_MAX_S, AIO_MLV_IMPORT_T0_S + AIO_MLV_IMPORT_PER_S * max(0, $n));
}

// The movies of Favorites $favs of the Dune (aio_ml_fav_read) of no card of the Dune of the list: how many.
/**
 * @param list<AioMlFav> $favs
 * @return int
 */
function aio_mlv_fav_new($favs)
{
    $there = array();
    foreach (aio_ml_list() as $e)
        $there[$e['dune_id']] = true;
    $n = 0;
    foreach ($favs as $f)
        $n += isset($there[$f['dune_id']]) ? 0 : 1;
    return $n;
}

// The s of the import of param $s (aio_mlv_import_ask): 1 to AIO_MLV_IMPORT_MAX_S less AIO_MLV_IMPORT_TAIL_S; another
// (none: an older item) AIO_MLV_IMPORT_T0_S less it.
/**
 * @param string $s
 * @return float
 */
function aio_mlv_import_budget($s)
{
    $max = AIO_MLV_IMPORT_MAX_S - AIO_MLV_IMPORT_TAIL_S;
    return preg_match('/^[1-9][0-9]{0,3}$/D', $s) ? (float) min($max, intval($s)) : (float) (AIO_MLV_IMPORT_T0_S - AIO_MLV_IMPORT_TAIL_S);
}

// The pair of card $mid of the Dune (links.php) with no request: kept, else of the card of the shell
// (aio_pg_shell_pair, kept). -> array(the pair or null, to ask: none kept, no "no pair" kept for good).
/**
 * @param string $mid
 * @return array{AioPgLink|null, bool}
 */
function aio_mlv_fav_known($mid)
{
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    if ($l && !isset($l['none']) && !isset($l['fail']))
        return array($l, false);
    if ($l && isset($l['none']))
        return array(null, false);
    // The card of the shell first: no request, not counted.
    $l = isset(AioPgLinks::$shell_seen[$mid]) ? null : aio_pg_shell_pair($mid);
    if ($l)
    {
        aio_pg_link_put($mid, $l);
        return array($l, false);
    }
    return array(null, true);
}

// The pairs of cards $mids of the Dune asked (aio_mlv_fav_known: to ask) into $pairs (movie_id => the pair or null),
// in the $budget s from $t0, each AIO_PG_LINK_S at most: AIO_HTTP_PAR at once (aio_pg_link_many), else one by one (no
// curl_multi: aio_pg_link_now); none in a pause of the requests, none with under 1 s left (null). $asked: one more
// each asked. -> array(at most at once (0: none at once), one by one) for aio_http_mark.
/**
 * @param list<string> $mids
 * @param float $budget
 * @param float $t0
 * @param array<string, AioPgLink|null> $pairs
 * @param int $asked
 * @return array{int, int}
 */
function aio_mlv_fav_ask($mids, $budget, $t0, &$pairs, &$asked)
{
    $par = 0;
    $seq = 0;
    $multi = true;
    $i = 0;
    while ($i < count($mids))
    {
        $left = $budget - (microtime(true) - $t0);
        if ($left < 1 || aio_pg_paused())
            break;
        $sent = 0;
        $rs = $multi ? aio_pg_link_many(array_slice($mids, $i, AIO_HTTP_PAR), min(AIO_PG_LINK_S, $left), $sent) : null;
        if ($rs === null)
        {
            $multi = false;
            $rs = array($mids[$i] => aio_pg_link_now($mids[$i], min(AIO_PG_LINK_S, $left)));
            $seq++;
        }
        else
            $par = max($par, $sent);
        foreach ($rs as $mid => $r)
            $pairs[strval($mid)] = $r['link'];
        $asked += count($rs);
        $i += count($rs);
    }
    return array($par, $seq);
}

// The tile of movie $f of the Favorites (aio_ml_fav_read) by its pair $l: the best tile of its IMDb or TMDB
// (aio_cp_best_tile, $map: aio_cp_tile_map of the caller; of the meta kept, else of a row in memory - the pictures and
// the ratings of the catalog, not the poster of dunemdb with auth=), else of the Favorites (its name, year, poster -
// its address, else its picture on the Dune: a root item has no address; a name of an item of the root "Name (2008)":
// the year of it). The type of the pair, else of the Favorites, else by its TMDB. null: no IMDb and no TMDB.
/**
 * @param AioMlFav $f
 * @param AioPgLink $l
 * @param array<string, AioCatTile>|null $map
 * @return AioCatTile|null
 */
function aio_mlv_fav_tile($f, $l, $map = null)
{
    $type = isset($l['type']) && ($l['type'] === 'movie' || $l['type'] === 'series') ? $l['type'] :
        ($f['type'] !== '' ? $f['type'] : (isset($l['tmdbtv']) ? 'series' : 'movie'));
    $imdb = isset($l['imdb']) ? $l['imdb'] : '';
    $k = $type === 'series' ? 'tmdbtv' : 'tmdb';
    $tmdb = isset($l[$k]) ? $l[$k] : '';
    if ($imdb === '' && $tmdb === '')
        return null;
    $m = aio_cp_best_tile($type, $imdb, $tmdb, $map);
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

// The import of screen $from (the second input of "Добавить из «Избранного» Дюны", aio_mlv_import_ask): the movies
// and series of the shell in the Favorites of the Dune (aio_ml_fav_read, read only) not in the list (by their card of
// the Dune and by IMDb or TMDB) to the end of it in their order (aio_ml_append), nothing taken out; IMDb and TMDB by
// the pairs first, then the card of the shell (aio_mlv_fav_known), then the requests of the rest (aio_mlv_fav_ask: at
// once by AIO_HTTP_PAR, else one by one), all in $budget s (aio_mlv_import_budget). One without them: not added, counted (a repeat of the import goes on with them). Then the
// meta of the ones added in what is left of it, at once (aio_cpm_fill: the tile of a row in memory, else a request;
// not asked, no answer: lazily). Then the screens of the list anew (aio_ml_changed: the list, not the hint) and the
// message: "Добавлено из «Избранного»: N" (with the ones not added; the list full), "В «Избранном» Дюны нет нового",
// or why the file was not written (aio_ml_err). One line of the log (the meta in it, no line of each request of it;
// the path of the requests, aio_http_mark; ", pause": the lazy meta in a pause after it, aio_cpm_paused), no address.
/**
 * @param string $from
 * @param float|null $budget
 * @return AioAction|null
 */
function aio_mlv_import($from, $budget = null)
{
    $t0 = microtime(true);
    if ($budget === null)
        $budget = aio_mlv_import_budget('');
    $lang = aio_catv_lang();
    $favs = aio_ml_fav_read();
    $map = aio_cp_tile_map();
    $add = array();
    $there = 0;
    $skip = 0;
    $asked = 0;
    // The pairs first: kept or of the cards of the shell, then the rest asked (aio_mlv_fav_ask).
    $pairs = array();
    $ask = array();
    foreach ($favs as $f)
    {
        $mid = $f['dune_id'];
        if (array_key_exists($mid, $pairs) || aio_ml_find_dune($mid))
            continue;
        list($pairs[$mid], $q) = aio_mlv_fav_known($mid);
        if ($q)
            $ask[] = $mid;
    }
    list($par, $seq) = aio_mlv_fav_ask($ask, $budget, $t0, $pairs, $asked);
    foreach ($favs as $f)
    {
        if (aio_ml_find_dune($f['dune_id']))
        {
            $there++;
            continue;
        }
        $l = $pairs[$f['dune_id']];
        $t = $l ? aio_mlv_fav_tile($f, $l, $map) : null;
        if ($t && aio_ml_in($t) !== '')
            $there++;
        else if ($t)
            $add[] = array($t, $f['dune_id']);
        else
            $skip++;
    }
    list($why, $n) = aio_ml_append($add);
    $tiles = array();
    foreach ($n ? $add : array() as $a)
    {
        // The list full: the ones added only.
        if ($why === '' || aio_ml_in($a[0]) !== '')
            $tiles[] = $a[0];
    }
    list($metas, $got, $mpar, $mseq) = aio_cpm_fill($tiles, $budget - (microtime(true) - $t0), $map);
    $mark = aio_http_mark(max($par, $mpar), $seq + $mseq);
    aio_log(sprintf('my list: import: %d in the Favorites, %d there, %d asked, %d added, %d not%s, meta %d asked, %d got, %.0f ms%s%s',
        count($favs), $there, $asked, $n, $skip, $why !== '' ? ", $why" : '', $metas, $got, (microtime(true) - $t0) * 1000,
        $mark !== '' ? ", $mark" : '', aio_cpm_paused() ? ', pause' : ''));
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
