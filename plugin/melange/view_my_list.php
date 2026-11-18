<?php
// "Мой список" (0.62.0) on the screens of php_server: the title into the list or out of it, its messages, POP UP of a
// tile of the catalog and of the tab "TV", the one effect of a change (mel_ml_changed); the screens "Мой список" and
// "Продолжить" (Continue Watching), each as "Show all" of a row of the catalog (view_catalog.php: on r25 rows of
// MEL_CATV_ALL_COLS with the info panel, on r24 the grid): "Мой список" the entries of its file in their order,
// "Продолжить" all the cards of the row "Continue" (continue.php), not MEL_CATV_ROW_TILES; the meta of their tiles of
// no request at the opening (mel_cpm_local), then r25 the ticks of a timer of the screen (mel_mlv_tick), r24 the first
// tiles asked in the opening (mel_cpm_ask_due). The ways to them: the heart and the clock on the strip of the catalog
// and in its POP UP (mel_catv_strip_rows, mel_catv_menu) and the keys Fav 2 (key_favorites: "Мой список") and Fav 1
// (key_dune: "Продолжить") of the remote on every screen of Melange (mel_mlv_keys; the player has no folder view). An empty
// "Мой список": the hint tile, its POP UP the import of the Favorites of the Dune (mel_mlv_import). The file, the
// copy of the backup and the reading of the Favorites are of my_list.php (the settings page has it alone).
// Needs my_list.php, view_catalog.php (mel_catv_*), continue.php, cp_meta.php (MelCpm, mel_cpm_*), links.php (mel_pg_links,
// mel_pg_link_now, mel_pg_paused), progress.php, catalog.php (mel_cat_tile), catalog_card.php (mel_card_enter,
// mel_card_open, MEL_CARD_DELAY), view_card.php (mel_oc_enter), card_meta.php (mel_cm_id), tv_tab.php (mel_tv_flag,
// mel_tv_is_from), playback.php (mel_replace_act), main.php (mel_tr, mel_input, mel_error, mel_dialog).
// PHP 5.3.6, firmware API only.

// The rid of the tiles of "Мой список": "mylist:<the key of the entry>" (mel_catv_tile).
define('MEL_ML_RID', 'mylist');
// The screens of all the titles (the heart and the clock, Fav 2 and Fav 1); of one type (ENTER on the title of the row
// of a tab) the address and ":movie" or ":series".
define('MEL_MLV_LIST', 'cat_mylist');
define('MEL_MLV_CONT', 'cat_continue');
// The id of the hint tile of an empty "Мой список" (no tile of a title: mel_catv_tile has none of it).
define('MEL_MLV_HINT', 'hint:mylist');
// Its picture, T2 of knowledge/melange/design-my-list.md: <MEL_MLV_HINT_IMG>ru.png, en.png, 200x300 (the N of ml<N>
// goes up when they change: the shell keeps a picture by its URL; plugins/melange_mylist_img.py of the kitchen).
define('MEL_MLV_HINT_IMG', 'plugin_file://img/ml1_hint_');
// The events of the keys, literal: key_favorites is of r24 on (no GUI_EVENT_KEY_FAVORITES in r22; an unknown name the
// shell ignores). Proven on the device (r25, the probe fav_keys_probe): they reach handle_user_input on REGULAR and
// ROWS when the actions of the screen have them, and the shell opens neither its history nor its Favorites then.
define('MEL_MLV_KEY_LIST', 'key_favorites');
define('MEL_MLV_KEY_CONT', 'key_dune');
// The import, of two inputs: the item of POP UP the usual 60 s (mel_mlv_import_ask: the Favorites read, how many not in
// the list), then the import itself (mel_mlv_import), the shell waiting for it (its operation_timeout, mel_mlv_import_s)
// MEL_MLV_IMPORT_T0_S and MEL_MLV_IMPORT_PER_S a title of the Favorites not in the list (its pair and its meta: ~0.6
// and 0.2-1.9 s, MEL_PG_LINK_S and MEL_CPM_FILL_S at most), MEL_MLV_IMPORT_MAX_S at most (php_server answers nothing
// else meanwhile: more is a hang to the user; ~180 titles of requests, the cards of the shell and the pairs kept ask
// none); the import MEL_MLV_IMPORT_TAIL_S less (the files written and the answer after it). The ones not paired in it
// not added, counted in the message (a repeat goes on); the meta of one not asked in it lazily (mel_cpm_step).
define('MEL_MLV_IMPORT_T0_S', 60);
define('MEL_MLV_IMPORT_PER_S', 3);
define('MEL_MLV_IMPORT_MAX_S', 600);
define('MEL_MLV_IMPORT_TAIL_S', 15);
// r25: the meta of the tiles of the screen of no meta at its opening by the ticks of a timer of its own (mel_mlv_tick):
// the first one MEL_MLV_TICK_FIRST_MS after the view (no stubs, no get_folder_rows to wait for), the next ones (the
// view anew after one that got some, or change_behaviour) MEL_CPM_STEP_PAR requests in MEL_CPM_STEP_PAR *
// MEL_CPM_RATE_S / MEL_CPM_RATE_N s at most after the last one (mel_mlv_tick_ms: 4 in 2 s, 120 a minute, the share of
// the catalog server); MEL_MLV_TICK_MAX ticks of an opening at most (~1000 tiles of "Продолжить": 20 * 4-5 requests),
// the count anew at an opening, not at a view anew of its own tick (one within MEL_MLV_ANEW_S of it).
define('MEL_MLV_TICK_FIRST_MS', 750);
define('MEL_MLV_TICK_MAX', 20);
define('MEL_MLV_ANEW_S', 5.0);
// r24 (the grid, no timer: neither the timer of view_regular nor the grid after invalidate proven there): the opening
// asks the meta of the first MEL_MLV_OPEN_TILES of its tiles with none (the 5x2 in view at first), after the steps of no
// request, all in MEL_MLV_OPEN_S s of it; the next ones at the next opening.
define('MEL_MLV_OPEN_TILES', 10);
define('MEL_MLV_OPEN_S', 2.0);

// The state of the ticks of the screens of Fav.
class MelMlv
{
    // The screens of these two opened since the start of php_server, by address: invalidated with the main screens of
    // the catalog (mel_catv_mains_urls).
    /** @var array<string, bool> */
    public static $opened = array();
    // r25: the screen whose timer is set (mel_mlv_view, mel_mlv_tick; '': none, or its tick came), the ticks of its
    // opening, the tiles of it with no meta of its view last built (array(type, IMDb, TMDB), of its first
    // MEL_CPM_SCREEN_MAX: the queue of its ticks), the cards of them with no pair ("Продолжить": the pairs its ticks
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
    // The screen of that last tick ('': none; a tick of the main screen within MEL_PG_FAV_QUIET_S of it waits, mel_pg_tick).
    /** @var string */
    public static $ticked = '';
    // The screen a tick invalidated ('': none) and when (microtime): its next view is no opening.
    /** @var string */
    public static $anew = '';
    /** @var float */
    public static $anew_t = 0.0;
    // The screen of the last tick that invalidated ('': none; at MelMlv::$anew_t, kept after its view anew; an input of
    // the user or a view of a screen it did not invalidate ends it, mel_mlv_input, mel_mlv_view_asked) and the screens
    // it invalidated (that one, the main ones): their views within MEL_MLV_ANEW_S of it are of that invalidate
    // (mel_mlv_inv; the shell builds them again at once, the lowest first).
    /** @var string */
    public static $inv = '';
    /** @var list<string> */
    public static $inv_urls = array();
}

// --- The title into the list or out of it.

// Title $t into the list ($on; mel_ml_add: its tile in memory to the meta kept, mel_cpm_of_add) or out of it (mel_ml_del
// of mel_ml_in; its meta kept stays till pushed out), $dune: the id of its card of the Dune ('' - none or not known).
// -> '' or why not, as they give it.
/**
 * @param MelCatTile $t
 * @param string $dune
 * @param bool $on
 * @return string
 */
function mel_ml_set($t, $dune, $on)
{
    if ($on)
    {
        $why = mel_ml_add($t, $dune);
        if ($why === '')
            mel_cpm_of_add($t);
        return $why;
    }
    $key = mel_ml_in($t, $dune);
    return $key !== '' ? mel_ml_del($key) : '';
}

// The message of why $why of mel_ml_set ('' none: null): no IMDb or TMDB, no name, the list full, its file of a later
// Melange or not readable, not written.
/**
 * @param string $why
 * @return MelAction|null
 */
function mel_ml_err($why)
{
    $keys = array('full' => 'ml_err_full', 'locked' => 'ml_err_locked', 'write' => 'ml_err_write', 'no id' => 'err_no_imdb',
        'no name' => 'ml_err_name');
    return $why === '' ? null : mel_error(isset($keys[$why]) ? $keys[$why] : 'ml_err_write', $why);
}

// The item of POP UP of tile $id of screen $from (mel_catv_tile_menu): "Добавить в «Мой список»" / "Убрать из «Моего
// списка»" by the list now (mel_ml_in by its card of the Dune known, mel_cp_card_known). None but on a tile of a title
// with IMDb or TMDB (mel_catv_tile).
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_ml_menu_items($id, $from, $lang)
{
    $t = mel_catv_tile($id, mel_tv_is_from($from));
    if (!$t || mel_ml_key($t) === '')
        return array();
    $in = mel_ml_in($t, mel_cp_card_known($t, mel_cp_mid_of($id))) !== '';
    return array(array(
        GuiMenuItemDef::caption => mel_tr($lang, $in ? 'ml_del' : 'ml_add'),
        GuiMenuItemDef::icon_url => MEL_CATV_ICONS . ($in ? 'cancel_small.png' : 'favorites_small.png'),
        GuiMenuItemDef::action => mel_input('cat_ml', array('from' => $from, 'item' => $id, 'w' => $in ? 'del' : 'add'))));
}

// After a change of the list on screen $from ('': none to build again, the own card; mel_catv_screen_of), then $post:
// the screens that show the list built again by one invalidate (mel_catv_mains_anew) - on r25 the main screens of the
// catalog and these two opened (the shell keeps rows_info by the id of a tile, the line of the info panel " · В моём
// списке" would stay old after Back), and $from on r24 too; the tab "TV" built anew (mel_tv_flag). None of them: $post.
// The one effect of every change of the list (POP UP, the button of the own card, the import).
/**
 * @param string $from
 * @param MelAction|null $post
 * @param string $why
 * @return MelAction|null
 */
function mel_ml_changed($from, $post, $why)
{
    mel_tv_flag($why);
    return mel_catv_mains_anew($post, $why, mel_catv_screen_of($from));
}

// cat_ml of POP UP (mel_ml_menu_items; w add, del) of tile $in->item of screen $in->from: the title into the list or out
// of it (mel_ml_set; its card of the Dune known, mel_cp_card_known, no request), then the screens anew (mel_ml_changed:
// the line of the info panel); not written: the message (mel_ml_err), the list as it was.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_ml_menu_act($in)
{
    $a = mel_catv_tile_act($in, array('add', 'del'));
    $t = $a['t'];
    if (!$t)
        return $a['err'];
    $why = mel_ml_set($t, mel_cp_card_known($t, mel_cp_mid_of($a['id'])), $a['w'] === 'add');
    return $why !== '' ? mel_ml_err($why) : mel_ml_changed($a['from'], null, "my list {$a['w']}");
}

// --- The screens.

// Screen $url of these two -> array(MEL_MLV_LIST or MEL_MLV_CONT, its type: 'movie', 'series', '' - both); null for
// another.
/**
 * @param string $url
 * @return array{string, string}|null
 */
function mel_mlv_screen($url)
{
    if (!preg_match('/^(' . MEL_MLV_LIST . '|' . MEL_MLV_CONT . ')(?::(movie|series))?$/D', $url, $m))
        return null;
    return array($m[1], isset($m[2]) ? $m[2] : '');
}

/**
 * @param string $url
 * @return bool
 */
function mel_mlv_is_screen($url)
{
    return mel_mlv_screen($url) !== null;
}

// The types of type $type ('' both).
/**
 * @param string $type
 * @return list<string>
 */
function mel_mlv_types($type)
{
    return $type === '' ? array('movie', 'series') : array($type);
}

// $id (with the mode of the screen or without) is of the hint tile.
/**
 * @param string $id
 * @return bool
 */
function mel_mlv_is_hint($id)
{
    list(, $id) = mel_catv_id_mode($id);
    return $id === MEL_MLV_HINT;
}

// The entry of key $key of the list, null for none.
/**
 * @param string $key
 * @return MelMlEntry|null
 */
function mel_mlv_entry($key)
{
    foreach (mel_ml_list() as $e)
    {
        if ($e['key'] === $key)
            return $e;
    }
    return null;
}

// The tile of the entry of key $key for the info panel, ENTER and POP UP: as of the row "Continue" (mel_cp_best_tile: a
// the meta kept, else a tile of a row in memory; no request for an entry not of the history), else the tile of the
// entry (mel_ml_tile); null for no such entry.
/**
 * @param string $key
 * @return MelCatTile|null
 */
function mel_mlv_tile($key)
{
    $e = mel_mlv_entry($key);
    if (!$e)
        return null;
    $t = mel_ml_tile($e);
    $m = mel_cp_best_tile($t['type'], $t['imdb_id'], $t['_tmdbId']);
    return $m ? $m : $t;
}

// The tiles of "Мой список" of the types $types, $max at most: the items of a row (id "mylist:<key>", caption, the
// poster of the entry, stickers: the rating of the best tile, else of the entry, the badges of the history) and their
// tiles, the best (mel_cp_best_tile: of the meta kept, else of a row in memory) else of the entry; the newest first.
// $miss: those with no best tile, in their order (array(type, IMDb, TMDB): the lazy meta, MelCpm::$ml and $more).
/**
 * @param list<string> $types
 * @param int $max
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array{item: array<string, mixed>, tile: MelCatTile}>
 */
function mel_mlv_list_cells($types, $max, &$miss = null)
{
    $out = array();
    $miss = array();
    $map = null;
    foreach (mel_ml_list() as $e)
    {
        if (!in_array($e['type'], $types, true))
            continue;
        if (count($out) >= $max)
            break;
        if ($map === null)
            $map = mel_cp_tile_map();
        $t = mel_ml_tile($e);
        $m = mel_cp_best_tile($t['type'], $t['imdb_id'], $t['_tmdbId'], $map);
        if (!$m)
            $miss[] = array($t['type'], $t['imdb_id'], $t['_tmdbId']);
        else if ($m['imdbRating'] !== '')
            $t['imdbRating'] = $m['imdbRating'];
        // The id of the tile is the key of the entry: a movie and a series of one number of TMDB are two.
        $t['id'] = $e['key'];
        $item = mel_catv_title_item(MEL_ML_RID, $t);
        if ($m)
        {
            $t = $m;
            $t['id'] = $e['key'];
        }
        $out[] = array('item' => $item, 'tile' => $t);
    }
    return $out;
}

// The titles of the tiles of screen $sc (mel_mlv_screen) in their order, as mel_mlv_cells has them: array(type, IMDb,
// TMDB) of the entries of "Мой список", of the pairs of the cards of "Продолжить" (mel_cp_ids; none of a card with no
// pair: '' both). $mids: the movie_id of each, in that order ('' of an entry of the list).
/**
 * @param array{string, string} $sc
 * @param list<string>|null $mids
 * @param-out list<string> $mids
 * @return list<array{string, string, string}>
 */
function mel_mlv_titles($sc, &$mids = null)
{
    $max = MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS;
    $types = mel_mlv_types($sc[1]);
    $out = array();
    $mids = array();
    if ($sc[0] === MEL_MLV_LIST)
    {
        foreach (mel_ml_list() as $e)
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
    $idx = mel_pg_index();
    foreach (mel_cp_items($types, $max) as $mid => $it)
    {
        $out[] = mel_cp_ids(strval($mid), $idx[strval($mid)]['series'] ? true : false);
        $mids[] = strval($mid);
    }
    return $out;
}

// The titles of screen $sc whose meta it keeps and asks: its first MEL_CPM_SCREEN_MAX (mel_mlv_titles), their keys
// kept over the other ones of the file (MelCpm::$hold), the cards of them with no pair to ask (MelMlv::$cards).
/**
 * @param array{string, string} $sc
 * @return list<array{string, string, string}>
 */
function mel_mlv_hold($sc)
{
    $titles = array_slice(mel_mlv_titles($sc, $mids), 0, MEL_CPM_SCREEN_MAX);
    MelCpm::$hold = mel_cpm_keys($titles);
    MelMlv::$cards = array();
    foreach ($titles as $i => $m)
    {
        if ($m[1] === '' && $m[2] === '' && $mids[$i] !== '')
            MelMlv::$cards[] = $mids[$i];
    }
    return $titles;
}

// The tiles of screen $url (mel_mlv_screen): the items of a row and their tiles (for the grid of r24). "Мой список": its
// entries (mel_mlv_list_cells); "Продолжить": the cards of the row "Continue", the latest first; of its type, both on
// a screen of all. Its tiles with no meta of its first MEL_CPM_SCREEN_MAX (mel_mlv_hold, held anew): the queue of its
// ticks (MelMlv::$miss), the first of them to the lazy meta of a main screen after those of the rows (MelCpm::$more).
/**
 * @param string $url
 * @return list<array{item: array<string, mixed>, tile: MelCatTile}>
 */
function mel_mlv_cells($url)
{
    $sc = mel_mlv_screen($url);
    $max = MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS;
    if (!$sc)
        return array();
    mel_mlv_hold($sc);
    if ($sc[0] === MEL_MLV_LIST)
        $cells = mel_mlv_list_cells(mel_mlv_types($sc[1]), $max, $miss);
    else
        $cells = array_values(mel_cp_tile_items(mel_mlv_types($sc[1]), $max, $miss));
    MelMlv::$miss = array();
    foreach ($miss as $m)
    {
        if (isset(MelCpm::$hold[mel_cpm_key($m[0], $m[1], $m[2])]))
            MelMlv::$miss[] = $m;
    }
    MelCpm::$more = array_slice(MelMlv::$miss, 0, MEL_CPM_MORE);
    return $cells;
}

// The tile alone of an empty screen $url: the hint of "Мой список" (its picture by the language), "Пусто" of
// "Продолжить" (ENTER: nothing).
/**
 * @param string $url
 * @param string $lang
 * @return array<string, string>
 */
function mel_mlv_empty_item($url, $lang)
{
    $sc = mel_mlv_screen($url);
    if (!$sc || $sc[0] !== MEL_MLV_LIST)
        return mel_catv_note_item(MEL_CP_RID, 'cat_row_empty', $lang);
    return mel_catv_item(mel_catv_tile_id(MEL_MLV_HINT), mel_tr($lang, 'ml_title'), MEL_MLV_HINT_IMG . ($lang === 'ru' ? 'ru' : 'en') . '.png');
}

// The item of the grid of r24 of row item $item: as one of "Show all" (mel_catv_grid_item) of its tile $t, its id and
// poster; with no tile (the hint, "Пусто") its caption and the line of $info.
/**
 * @param array<string, mixed> $item
 * @param MelCatTile|null $t
 * @param string $info
 * @param string $lang
 * @return array<string, mixed>
 */
function mel_mlv_grid_item($item, $t, $info, $lang)
{
    $icon = $item[PluginRegularItem::icon_url];
    if ($t)
        $g = mel_catv_grid_item('', $t, $lang);
    else
        $g = array(PluginRegularFolderItem::caption => $item[PluginRegularItem::caption],
            PluginRegularFolderItem::view_item_params => array(ViewItemParams::item_detailed_info =>
                mel_clean($item[PluginRegularItem::caption]) . '|' . mel_clean($info)));
    $g[PluginRegularFolderItem::media_url] = $item[PluginRegularItem::id];
    $g[PluginRegularFolderItem::view_item_params][ViewItemParams::icon_path] = $icon;
    $g[PluginRegularFolderItem::view_item_params][ViewItemParams::item_detailed_icon_path] = $icon;
    return $g;
}

// The screen "Мой список" or "Продолжить" ($url), all its tiles at once (no stubs, no get_folder_rows): on r25 rows of
// MEL_CATV_ALL_COLS as "Show all" (mel_catv_all_pane: the info panel, ENTER, POP UP, the search), on r24 the grid of
// "Show all" with POP UP. Empty: one tile, mel_mlv_empty_item. Before its tiles the meta of them with no request, of
// its first MEL_CPM_SCREEN_MAX (mel_mlv_hold; mel_cpm_local: kept, of a row in memory, of a copy of a row on the disk;
// kept at one write, with those asked on r24); then r24 the first MEL_MLV_OPEN_TILES of them to ask asked in
// MEL_MLV_OPEN_S s of the opening (mel_cpm_ask_due, as a build), r25 the timer of the screen when there is more to ask
// (mel_mlv_tick_set: the count of the ticks anew at an opening, not at a view of its own tick). One line of the log.
/**
 * @param string $url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_mlv_view($url, $sel_state)
{
    $start = microtime(true);
    $lang = mel_catv_lang();
    MelCat::$lang = $lang;
    MelMlv::$opened[$url] = true;
    $sc = mel_mlv_screen($url);
    $list = $sc && $sc[0] === MEL_MLV_LIST;
    $rows_ok = mel_catv_all_ok();
    $ago = microtime(true) - MelMlv::$anew_t;
    $anew = MelMlv::$anew === $url && $ago >= 0 && $ago < MEL_MLV_ANEW_S;
    MelMlv::$anew = '';
    if (!$anew)
        MelMlv::$ticks = 0;
    $titles = $sc ? mel_mlv_hold($sc) : array();
    list($put, $renew, $due, , $mem, $disk) = mel_cpm_local($titles, mel_cpm_read(), time());
    $net = '';
    if (!$rows_ok)
    {
        list($got_put, $asked, $got, $mark) = mel_cpm_ask_due($due, $start, MEL_MLV_OPEN_S, MEL_MLV_OPEN_TILES);
        $put = array_merge($put, $got_put);
        $net = sprintf(', %d asked, %d got%s', $asked, $got, $mark);
    }
    if ($put)
        mel_cpm_put_all($put, $renew);
    $cells = mel_mlv_cells($url);
    $ms = $rows_ok ? mel_mlv_tick_set($url) : 0;
    $why = '';
    if ($rows_ok)
        $why = $ms ? ", timer in $ms ms" : ', no timer: ' . (MelMlv::$ticks >= MEL_MLV_TICK_MAX ? 'limit' : mel_mlv_why());
    mel_log(sprintf('%s: screen%s%s: %d tiles, %d of rows in memory, %d of rows on the disk%s, %d with no meta, %.0f ms%s',
        $list ? 'my list' : 'continue', $sc && $sc[1] !== '' ? " of {$sc[1]}" : '', $anew ? ' anew' : '', count($cells), $mem,
        $disk, $net, count(MelMlv::$miss), (microtime(true) - $start) * 1000, $why));
    $items = array();
    foreach ($cells as $c)
        $items[] = $c['item'];
    if (!$items)
        $items[] = mel_mlv_empty_item($url, $lang);
    if ($rows_ok)
    {
        $rows = array();
        foreach (array_chunk($items, MEL_CATV_ALL_COLS) as $k => $part)
            $rows[] = mel_catv_all_def("row$k", $part);
        return mel_catv_all_pane($rows, null, $sel_state, $url, $ms);
    }
    $grid = array();
    $info = $list ? mel_tr($lang, 'ml_hint') : mel_tr($lang, 'cat_row_empty');
    foreach ($items as $i => $item)
        $grid[] = mel_mlv_grid_item($item, isset($cells[$i]) ? $cells[$i]['tile'] : null, $info, $lang);
    $p = array('from' => $url);
    return mel_catv_grid_pane(array(
        PluginRegularFolderRange::total => count($grid),
        PluginRegularFolderRange::more_items_available => false,
        PluginRegularFolderRange::from_ndx => 0,
        PluginRegularFolderRange::count => count($grid),
        PluginRegularFolderRange::items => $grid),
        array(GUI_EVENT_KEY_ENTER => mel_input('cat_enter', $p, Mel::$settings['cat_card'] ? MEL_CARD_DELAY : MEL_DELAY_INPUT),
            GUI_EVENT_KEY_POPUP_MENU => mel_input('cat_menu', $p)) + mel_egg_acts());
}

// r25: why a tick of the screen of the view last built would ask nothing (mel_pg_tick_why of its tiles with no meta,
// MelMlv::$miss, and the cards of them with no pair, MelMlv::$cards: none on "Мой список") -> '' or why not.
/**
 * @return string
 */
function mel_mlv_why()
{
    return mel_pg_tick_why(MelMlv::$miss, MelMlv::$cards);
}

// r25: the screen of the last tick that invalidated (MelMlv::$inv) within MEL_MLV_ANEW_S of it when screen $url is one
// it invalidated (MelMlv::$inv_urls), else ''.
/**
 * @param string $url
 * @return string
 */
function mel_mlv_inv($url)
{
    $ago = microtime(true) - MelMlv::$anew_t;
    return MelMlv::$inv !== '' && $ago >= 0 && $ago < MEL_MLV_ANEW_S && in_array($url, MelMlv::$inv_urls, true) ?
        MelMlv::$inv : '';
}

// r25: main screen of rows $url built now is built again by the tick of a screen of Fav over it (mel_mlv_inv), whose
// timer lives (its view anew to come, or set again by it) -> that screen, '' none (an opening: as it is).
/**
 * @param string $url
 * @return string
 */
function mel_mlv_under($url)
{
    $inv = mel_mlv_inv($url);
    return $inv !== '' && (MelMlv::$anew === $inv || MelMlv::$tick_url === $inv) ? $inv : '';
}

// r25: input $control of the handler of Melange (mel_handle_user_input): one of the user (not a timer, *_tick, *_load,
// nor the info panel of the focus, cat_info, of a view built again too) ends the window of the invalidate of a tick
// (MelMlv::$inv): a screen asked after it is an opening (ENTER, the keys Fav, POP UP) as before.
/**
 * @param mixed $control
 * @return void
 */
function mel_mlv_input($control)
{
    if (MelMlv::$inv === '' || !is_string($control) || in_array($control, array('cat_info', 'tsi_state', 'tv_timer'), true) ||
        preg_match('/_(tick|load)$/D', $control))
        return;
    MelMlv::$inv = '';
}

// r25: ms till the next tick of a screen: MEL_MLV_TICK_FIRST_MS at least, MEL_CPM_STEP_PAR requests in their share of
// MEL_CPM_RATE_N in MEL_CPM_RATE_S after the last tick (MelMlv::$tick_t).
/**
 * @return int
 */
function mel_mlv_tick_ms()
{
    $gap = 1000.0 * MEL_CPM_STEP_PAR * MEL_CPM_RATE_S / MEL_CPM_RATE_N;
    $since = MelMlv::$tick_t > 0 ? (microtime(true) - MelMlv::$tick_t) * 1000 : $gap;
    return (int) max(MEL_MLV_TICK_FIRST_MS, ceil($gap - max(0.0, $since)));
}

// r25: the view of screen $url is made: its timer (MelMlv::$tick_url) when there is more to ask (mel_mlv_why) under
// MEL_MLV_TICK_MAX ticks of the opening. -> its ms (mel_mlv_tick_ms), 0: no timer.
/**
 * @param string $url
 * @return int
 */
function mel_mlv_tick_set($url)
{
    $ok = MelMlv::$ticks < MEL_MLV_TICK_MAX && mel_mlv_why() === '';
    MelMlv::$tick_url = $ok ? $url : '';
    return $ok ? mel_mlv_tick_ms() : 0;
}

// A screen of Melange $url is asked of get_folder_view (mel_folder_view): another than the one of the timer - no more
// ticks of it, nor a view anew of its tick. Not a main screen of rows built again (MelCat::$mains: invalidated with the
// screen by its tick, the shell may build it first; else the view anew of the screen was an opening, its count of the
// ticks 0 again and the limit gone). Within MEL_MLV_ANEW_S of the invalidate of a tick (mel_mlv_inv) a screen it
// invalidated is built again by the shell: as it is; another one is an opening (the user is on it: Back, then a link
// of the main screen, not an input of the handler) - the window ends, the timer of the screen of the tick too.
/**
 * @param string $url
 * @return void
 */
function mel_mlv_view_asked($url)
{
    $inv = mel_mlv_inv($url);
    if ($inv === '')
        MelMlv::$inv = '';
    if (in_array($url, MelCat::$mains, true) || ($inv !== '' && $inv !== $url))
        return;
    if (MelMlv::$anew !== '' && MelMlv::$anew !== $url)
        MelMlv::$anew = '';
    if (MelMlv::$tick_url === '' || MelMlv::$tick_url === $url)
        return;
    $sc = mel_mlv_screen(MelMlv::$tick_url);
    MelMlv::$tick_url = '';
    mel_log(sprintf('%s: screen: tick %d/%d: no more, the screen left', $sc && $sc[0] === MEL_MLV_CONT ? 'continue' : 'my list',
        MelMlv::$ticks, MEL_MLV_TICK_MAX));
}

// r25: a tick of the timer of screen $url (mel_mlv_tick_set), MEL_CPM_TICK_S at most, as mel_pg_tick: on "Продолжить"
// the pairs of its cards with none (mel_pg_link_step of MelMlv::$cards; one got: the tiles of no meta again), then the
// meta of its tiles with no meta (mel_cpm_step of MelMlv::$miss, its order: the first tiles first) in what is left.
// Some got: the view anew with the main screens of the catalog (mel_catv_mains_anew, the screen first, no post_action;
// the row "Continue" with the rating after Back; the view sets the timer again itself, the focus by sel_state; not the
// other screens of Fav opened; MelMlv::$inv, MelMlv::$inv_urls: the window of the invalidate). None
// got, more to ask: the timer again (mel_catv_all_again, mel_mlv_tick_ms); else nothing. Not the tick of the timer set
// (another screen, an old one): nothing. A line of the log of each.
/**
 * @param string $url
 * @return MelAction|null
 */
function mel_mlv_tick($url)
{
    $sc = mel_mlv_screen($url);
    $who = $sc !== null && $sc[0] === MEL_MLV_CONT ? 'continue' : 'my list';
    if (MelMlv::$tick_url !== $url)
    {
        mel_log("$who: screen: tick: no step, " . (MelMlv::$tick_url === '' ? 'no timer on it' : 'a tick of another screen'));
        return null;
    }
    MelMlv::$tick_url = '';
    $n = ++MelMlv::$ticks;
    $t = microtime(true);
    $rounds = 0;
    $pairs = 0;
    if (MelMlv::$cards)
        mel_pg_link_step(min(MEL_PG_LINK_S, MEL_CPM_TICK_S), true, $rounds, $pairs, MelMlv::$cards);
    // A card paired: its tile has ids now, its meta to ask (the tiles of no meta again, MelMlv::$miss).
    if ($pairs)
        mel_mlv_cells($url);
    $got = mel_cpm_step(MEL_CPM_TICK_S - (microtime(true) - $t), MEL_CPM_N - $rounds, true, MelMlv::$miss);
    MelMlv::$tick_t = microtime(true);
    MelMlv::$ticked = $url;
    $why = $n >= MEL_MLV_TICK_MAX ? 'limit' : mel_mlv_why();
    $left = count(mel_cpm_todo(MelMlv::$miss)) + (MelMlv::$cards ? count(mel_pg_link_queue(MelMlv::$cards)) : 0);
    $act = null;
    if ($pairs + $got > 0)
    {
        MelMlv::$anew = $url;
        MelMlv::$inv = $url;
        MelMlv::$anew_t = microtime(true);
        $act = mel_catv_mains_anew(null, "after a tick of $url", $url, false);
        $urls = $act ? $act[GuiAction::data][PluginInvalidateFoldersActionData::media_urls] : array();
        MelMlv::$inv_urls = $urls;
        $what = count($urls) > 1 ? 'invalidate with the main screens' : 'invalidate';
        $what .= $why !== '' ? ", no more: $why" : '';
    }
    else if ($why === '')
    {
        MelMlv::$tick_url = $url;
        $ms = mel_mlv_tick_ms();
        $act = mel_catv_all_again($url, $ms);
        $what = "again in $ms ms";
    }
    else
        $what = "no more: $why";
    mel_log(sprintf('%s: screen: tick %d/%d: %d pairs, %d got, %d left, %.0f ms, %s', $who, $n, MEL_MLV_TICK_MAX, $pairs, $got,
        $left, (microtime(true) - $t) * 1000, $what));
    return $act;
}

// The key of the entry of "Мой список" (mel_ml_key) of tile $id of a screen (its id of the row of the list,
// MEL_ML_RID:<key>, of any mode, mel_catv_id_mode); null for another tile.
/**
 * @param string $id
 * @return string|null
 */
function mel_mlv_key_of($id)
{
    list(, $x) = mel_catv_id_mode($id);
    $p = MEL_ML_RID . ':';
    return strncmp($x, $p, strlen($p)) === 0 ? strval(substr($x, strlen($p))) : null;
}

// ENTER on tile $id of "Мой список" (another tile: null): the own card ("Своя карточка" on) or the card of the Dune
// by the card of the Dune of the entry, no request of it; not known - as ENTER on a tile of a row (mel_card_enter). No
// such entry (taken out on another screen): the screen is old.
/**
 * @param string $id
 * @param string $lang
 * @return MelAction|null
 */
function mel_mlv_enter($id, $lang)
{
    $key = mel_mlv_key_of($id);
    if ($key === null)
        return null;
    $e = mel_mlv_entry($key);
    $t = $e ? mel_mlv_tile($key) : null;
    if (!$e || !$t)
    {
        mel_log('my list: ENTER: no such entry');
        return mel_error('cat_outdated');
    }
    if ($e['dune_id'] === '')
        return mel_card_enter($t, $lang);
    if (Mel::$settings['cat_card'] && mel_cm_id($t) !== '')
        return mel_oc_enter($t, $lang, $e['dune_id']);
    return mel_card_open($e['dune_id']);
}

// --- The row "Мой список" of the main screens.

// The row "Мой список" of the types of tab $tab ('movie', 'series'; '' both: tv.json of "TV") for a screen of rows,
// second after "Continue": its title and the first MEL_CATV_ROW_TILES titles of the list of them (mel_mlv_list_cells:
// the newest first, the entry and its best tile, no request), no "Show all" tile, no flag; ENTER on its title the screen of its type
// (mel_mlv_screen). None when the list has none of them (the empty screen with the hint is of the heart and Fav 2). $tiles:
// its tiles by "mylist:<key>" (the file of the tab "TV"); $miss: those with no meta (the lazy meta of a main screen,
// MelCpm::$ml: mel_catv_main_view puts them there).
/**
 * @param string $tab
 * @param string $lang
 * @param array<string, MelCatTile>|null $tiles
 * @param-out array<string, MelCatTile> $tiles
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array<string, mixed>>
 */
function mel_ml_row_defs($tab, $lang, &$tiles = null, &$miss = null)
{
    $tiles = array();
    $items = array();
    foreach (mel_mlv_list_cells(mel_mlv_types($tab), MEL_CATV_ROW_TILES, $miss) as $c)
    {
        $items[] = $c['item'];
        $tiles[MEL_ML_RID . ':' . $c['tile']['id']] = $c['tile'];
    }
    if (!$items)
        return array();
    $name = mel_tr($lang, 'ml_title');
    $row = array('key' => MEL_ML_RID, 'type' => $tab !== '' ? $tab : 'movie', 'id' => MEL_ML_RID, 'name' => $name, 'genre' => '',
        'home' => false, 'group' => '');
    $defs = mel_catv_row_defs(mel_catv_list(mel_cat_row_state($row), array(), false), $lang, $items, 0, MEL_ML_RID);
    $defs[1][PluginRow::show_all_action] = mel_catv_open_act(MEL_MLV_LIST . ($tab !== '' ? ":$tab" : ''), $name);
    mel_log(sprintf('my list: row %s: %d tiles', $tab !== '' ? $tab : 'all', count($items)));
    return $defs;
}

// --- The ways to the screens.

// The keys Fav 2 and Fav 1 of screen $from.
/**
 * @param string $from
 * @return array<string, MelAction>
 */
function mel_mlv_key_acts($from)
{
    return array(
        MEL_MLV_KEY_LIST => mel_input('cat_fav', array('from' => $from, 'w' => 'list')),
        MEL_MLV_KEY_CONT => mel_input('cat_fav', array('from' => $from, 'w' => 'continue')));
}

// Folder view $view of screen $media_url (mel_folder_view: every screen of Melange - the catalog, "Show all", a search,
// the own card, a person, the list of streams, the details, the settings, these two) with the keys Fav 2 and Fav 1 in
// its actions, the actions of the screen kept. A file of the tab "TV" is no such view: the keys stay with the shell
// there, as the colour keys (tv_tab.php). A new change_behaviour with actions on a screen of the catalog replaces its
// actions whole and loses the Fav keys: add mel_mlv_key_acts to them (as mel_catv_all_tick does).
/**
 * @param mixed $view
 * @param string $media_url
 * @return mixed
 */
function mel_mlv_keys($view, $media_url)
{
    if (!is_array($view) || !isset($view[PluginFolderView::data]) || !is_array($view[PluginFolderView::data]))
        return $view;
    // "actions" of every kind of view (PluginRowsFolderView, PluginRegularFolderView, PluginGCompsFolderView,
    // PluginControlsFolderView).
    $k = PluginRegularFolderView::actions;
    $acts = isset($view[PluginFolderView::data][$k]) && is_array($view[PluginFolderView::data][$k]) ? $view[PluginFolderView::data][$k] : array();
    $view[PluginFolderView::data][$k] = $acts + mel_mlv_key_acts($media_url);
    return $view;
}

// The screen of $w ('list': "Мой список", 'continue': "Продолжить"; another: null) of all the titles from screen $from:
// on it already - nothing; from one of these screens (of a type too) - in its place (Back goes where it went from that
// one: no stack of them); else over $from.
/**
 * @param string $w
 * @param string $from
 * @return MelAction|null
 */
function mel_mlv_open($w, $from)
{
    if ($w !== 'list' && $w !== 'continue')
        return null;
    $url = $w === 'list' ? MEL_MLV_LIST : MEL_MLV_CONT;
    if ($from === $url)
    {
        mel_log("my list: open $w: on it already");
        return null;
    }
    $replace = mel_mlv_is_screen($from);
    mel_log("my list: open $w" . ($replace ? ' in place' : ''));
    $caption = mel_tr(mel_catv_lang(), $w === 'list' ? 'ml_title' : 'cp_row_continue');
    return $replace ? mel_replace_act($url, $caption) : mel_catv_open_act($url, $caption);
}

// --- The import of the Favorites of the Dune.

// The items of POP UP of tile $id of screen $from (after those of a tile: mel_catv_tile_menu): on the screen
// "Мой список" (of a type too) and on a tile of the row "Мой список" "Добавить из «Избранного» Дюны" (its first input,
// mel_mlv_import_ask; the Favorites not read for the menu); on the screen "Продолжить" (of a type too; any tile, "Пусто"
// too) "Вернуть убранные (N)" when N of all types are off the row (mel_cp_hidden_n, mel_cp_unhide_act; the icon
// recent_small.png of the shell of r25, none in r22 and r24: return_small.png there); none on another.
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_mlv_menu_items($id, $from, $lang)
{
    $sc = mel_mlv_screen($from);
    if ($sc && $sc[0] === MEL_MLV_CONT)
    {
        $n = mel_cp_hidden_n();
        return $n ? array(array(
            GuiMenuItemDef::caption => sprintf(mel_tr($lang, 'cp_unhide'), $n),
            GuiMenuItemDef::icon_url => MEL_CATV_ICONS . (mel_fw_r() >= 25 ? 'recent_small.png' : 'return_small.png'),
            GuiMenuItemDef::action => mel_input('cat_unhide', array('from' => $from)))) : array();
    }
    if (!($sc && $sc[0] === MEL_MLV_LIST) && mel_mlv_key_of($id) === null)
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($lang, 'ml_import'),
        GuiMenuItemDef::icon_url => MEL_CATV_ICONS . 'favorites_small.png',
        GuiMenuItemDef::action => mel_input('cat_ml_import', array('from' => $from), MEL_CARD_DELAY)));
}

// The first input of the import of screen $from: the Favorites of the Dune read (mel_ml_fav_read), the movies of no
// card of the Dune of the list counted, n. None: "В «Избранном» Дюны нет нового" at once. Else the import itself
// (mel_mlv_import) in an input of its own, the shell waiting mel_mlv_import_s(n) for it, the import that less
// MEL_MLV_IMPORT_TAIL_S (its param s, mel_mlv_import_budget). One line of the log.
/**
 * @param string $from
 * @return MelAction
 */
function mel_mlv_import_ask($from)
{
    $favs = mel_ml_fav_read();
    $n = mel_mlv_fav_new($favs);
    $s = mel_mlv_import_s($n);
    mel_log(sprintf('my list: import: %d in the Favorites, %d new%s', count($favs), $n, $n ? ", $s s" : ''));
    if (!$n)
        return mel_dialog(mel_tr(mel_catv_lang(), 'ml_import_none'), array(), array('OK' => mel_close_and(null)));
    return mel_input('cat_ml_import_go', array('from' => $from, 's' => strval($s - MEL_MLV_IMPORT_TAIL_S)), MEL_CARD_DELAY,
        $s * 1000);
}

// s the shell waits for an import of $n titles of the Favorites not in the list: MEL_MLV_IMPORT_T0_S and
// MEL_MLV_IMPORT_PER_S each, MEL_MLV_IMPORT_MAX_S at most.
/**
 * @param int $n
 * @return int
 */
function mel_mlv_import_s($n)
{
    return min(MEL_MLV_IMPORT_MAX_S, MEL_MLV_IMPORT_T0_S + MEL_MLV_IMPORT_PER_S * max(0, $n));
}

// The movies of Favorites $favs of the Dune (mel_ml_fav_read) of no card of the Dune of the list: how many.
/**
 * @param list<MelMlFav> $favs
 * @return int
 */
function mel_mlv_fav_new($favs)
{
    $there = array();
    foreach (mel_ml_list() as $e)
        $there[$e['dune_id']] = true;
    $n = 0;
    foreach ($favs as $f)
        $n += isset($there[$f['dune_id']]) ? 0 : 1;
    return $n;
}

// The s of the import of param $s (mel_mlv_import_ask): 1 to MEL_MLV_IMPORT_MAX_S less MEL_MLV_IMPORT_TAIL_S; another
// (none: an older item) MEL_MLV_IMPORT_T0_S less it.
/**
 * @param string $s
 * @return float
 */
function mel_mlv_import_budget($s)
{
    $max = MEL_MLV_IMPORT_MAX_S - MEL_MLV_IMPORT_TAIL_S;
    return preg_match('/^[1-9][0-9]{0,3}$/D', $s) ? (float) min($max, intval($s)) : (float) (MEL_MLV_IMPORT_T0_S - MEL_MLV_IMPORT_TAIL_S);
}

// The pair of card $mid of the Dune (links.php) with no request: kept, else of the card of the shell
// (mel_pg_shell_pair, kept). -> array(the pair or null, to ask: none kept, no "no pair" kept for good).
/**
 * @param string $mid
 * @return array{MelPgLink|null, bool}
 */
function mel_mlv_fav_known($mid)
{
    $links = mel_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    if ($l && !isset($l['none']) && !isset($l['fail']))
        return array($l, false);
    if ($l && isset($l['none']))
        return array(null, false);
    // The card of the shell first: no request, not counted.
    $l = isset(MelPgLinks::$shell_seen[$mid]) ? null : mel_pg_shell_pair($mid);
    if ($l)
    {
        mel_pg_link_put($mid, $l);
        return array($l, false);
    }
    return array(null, true);
}

// The pairs of cards $mids of the Dune asked (mel_mlv_fav_known: to ask) into $pairs (movie_id => the pair or null),
// in the $budget s from $t0, each MEL_PG_LINK_S at most: MEL_HTTP_PAR at once (mel_pg_link_many), else one by one (no
// curl_multi: mel_pg_link_now); none in a pause of the requests, none with under 1 s left (null). $asked: one more
// each asked. -> array(at most at once (0: none at once), one by one) for mel_http_mark.
/**
 * @param list<string> $mids
 * @param float $budget
 * @param float $t0
 * @param array<string, MelPgLink|null> $pairs
 * @param int $asked
 * @return array{int, int}
 */
function mel_mlv_fav_ask($mids, $budget, $t0, &$pairs, &$asked)
{
    $par = 0;
    $seq = 0;
    $multi = true;
    $i = 0;
    while ($i < count($mids))
    {
        $left = $budget - (microtime(true) - $t0);
        if ($left < 1 || mel_pg_paused())
            break;
        $sent = 0;
        $rs = $multi ? mel_pg_link_many(array_slice($mids, $i, MEL_HTTP_PAR), min(MEL_PG_LINK_S, $left), $sent) : null;
        if ($rs === null)
        {
            $multi = false;
            $rs = array($mids[$i] => mel_pg_link_now($mids[$i], min(MEL_PG_LINK_S, $left)));
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

// The tile of movie $f of the Favorites (mel_ml_fav_read) by its pair $l: the best tile of its IMDb or TMDB
// (mel_cp_best_tile, $map: mel_cp_tile_map of the caller; of the meta kept, else of a row in memory - the pictures and
// the ratings of the catalog, not the poster of dunemdb with auth=), else of the Favorites (its name, year, poster -
// its address, else its picture on the Dune: a root item has no address; a name of an item of the root "Name (2008)":
// the year of it). The type of the pair, else of the Favorites, else by its TMDB. null: no IMDb and no TMDB.
/**
 * @param MelMlFav $f
 * @param MelPgLink $l
 * @param array<string, MelCatTile>|null $map
 * @return MelCatTile|null
 */
function mel_mlv_fav_tile($f, $l, $map = null)
{
    $type = isset($l['type']) && ($l['type'] === 'movie' || $l['type'] === 'series') ? $l['type'] :
        ($f['type'] !== '' ? $f['type'] : (isset($l['tmdbtv']) ? 'series' : 'movie'));
    $imdb = isset($l['imdb']) ? $l['imdb'] : '';
    $k = $type === 'series' ? 'tmdbtv' : 'tmdb';
    $tmdb = isset($l[$k]) ? $l[$k] : '';
    if ($imdb === '' && $tmdb === '')
        return null;
    $m = mel_cp_best_tile($type, $imdb, $tmdb, $map);
    if ($m)
        return $m;
    $name = $f['name'];
    $year = $f['year'];
    if ($year === '' && preg_match('/^(.+) \(([0-9]{4})\)$/su', $name, $x))
    {
        $name = $x[1];
        $year = $x[2];
    }
    $t = mel_cat_tile(array('id' => $imdb !== '' ? $imdb : "tmdb:$tmdb", 'name' => $name, 'type' => $type, 'imdb_id' => $imdb,
        '_tmdbId' => $tmdb, 'year' => $year, 'poster' => $f['poster']), $type);
    // A path is no address for mel_cat_tile.
    if ($t && $t['poster'] === '')
        $t['poster'] = $f['file'];
    return $t;
}

// The import of screen $from (the second input of "Добавить из «Избранного» Дюны", mel_mlv_import_ask): the movies
// and series of the shell in the Favorites of the Dune (mel_ml_fav_read, read only) not in the list (by their card of
// the Dune and by IMDb or TMDB) to the end of it in their order (mel_ml_append), nothing taken out; IMDb and TMDB by
// the pairs first, then the card of the shell (mel_mlv_fav_known), then the requests of the rest (mel_mlv_fav_ask: at
// once by MEL_HTTP_PAR, else one by one), all in $budget s (mel_mlv_import_budget). One without them: not added, counted (a repeat of the import goes on with them). Then the
// meta of the ones added in what is left of it, at once (mel_cpm_fill: the tile of a row in memory, else a request;
// not asked, no answer: lazily). Then the screens of the list anew (mel_ml_changed: the list, not the hint) and the
// message: "Добавлено из «Избранного»: N" (with the ones not added; the list full), "В «Избранном» Дюны нет нового",
// or why the file was not written (mel_ml_err). One line of the log (the meta in it, no line of each request of it;
// the path of the requests, mel_http_mark; ", pause": the lazy meta in a pause after it, mel_cpm_paused), no address.
/**
 * @param string $from
 * @param float|null $budget
 * @return MelAction|null
 */
function mel_mlv_import($from, $budget = null)
{
    $t0 = microtime(true);
    if ($budget === null)
        $budget = mel_mlv_import_budget('');
    $lang = mel_catv_lang();
    $favs = mel_ml_fav_read();
    $map = mel_cp_tile_map();
    $add = array();
    $there = 0;
    $skip = 0;
    $asked = 0;
    // The pairs first: kept or of the cards of the shell, then the rest asked (mel_mlv_fav_ask).
    $pairs = array();
    $ask = array();
    foreach ($favs as $f)
    {
        $mid = $f['dune_id'];
        if (array_key_exists($mid, $pairs) || mel_ml_find_dune($mid))
            continue;
        list($pairs[$mid], $q) = mel_mlv_fav_known($mid);
        if ($q)
            $ask[] = $mid;
    }
    list($par, $seq) = mel_mlv_fav_ask($ask, $budget, $t0, $pairs, $asked);
    foreach ($favs as $f)
    {
        if (mel_ml_find_dune($f['dune_id']))
        {
            $there++;
            continue;
        }
        $l = $pairs[$f['dune_id']];
        $t = $l ? mel_mlv_fav_tile($f, $l, $map) : null;
        if ($t && mel_ml_in($t) !== '')
            $there++;
        else if ($t)
            $add[] = array($t, $f['dune_id']);
        else
            $skip++;
    }
    list($why, $n) = mel_ml_append($add);
    $tiles = array();
    foreach ($n ? $add : array() as $a)
    {
        // The list full: the ones added only.
        if ($why === '' || mel_ml_in($a[0]) !== '')
            $tiles[] = $a[0];
    }
    list($metas, $got, $mpar, $mseq) = mel_cpm_fill($tiles, $budget - (microtime(true) - $t0), $map);
    $mark = mel_http_mark(max($par, $mpar), $seq + $mseq);
    mel_log(sprintf('my list: import: %d in the Favorites, %d there, %d asked, %d added, %d not%s, meta %d asked, %d got, %.0f ms%s%s',
        count($favs), $there, $asked, $n, $skip, $why !== '' ? ", $why" : '', $metas, $got, (microtime(true) - $t0) * 1000,
        $mark !== '' ? ", $mark" : '', mel_cpm_paused() ? ', pause' : ''));
    if ($n === 0 && $why !== '' && $why !== 'full')
        return mel_ml_err($why);
    $lines = array();
    if ($skip)
        $lines = array_merge($lines, mel_dialog_lines(sprintf(mel_tr($lang, 'ml_import_skip'), $skip)));
    if ($why === 'full')
        $lines = array_merge($lines, mel_dialog_lines(mel_tr($lang, 'ml_err_full')));
    $title = $n === 0 && !$lines ? mel_tr($lang, 'ml_import_none') : sprintf(mel_tr($lang, 'ml_import_n'), $n);
    $msg = mel_dialog($title, $lines, array('OK' => mel_close_and(null)));
    return $n === 0 ? $msg : mel_ml_changed($from, $msg, 'my list import');
}
