<?php
// Screens of the catalog: the rows (media_url "catalog"), a row in full
// ("cat_grid:<rid>", of a search "cat_grid:<rid>:<id of the search>"; the rows of 9 on r25,
// a grid on r24), search and its results ("cat_search:<id>"). Data: catalog.php,
// ENTER on a tile: catalog_card.php. rid: a short hash of the key of a row; the id of a
// tile is "<rid>:<id of the title>", so it stays the same after a rebuild or a restart.
// Types in the PHPDoc (MelCatList and others): phpstan-types.neon of the repository.

// r24: seconds for rows at an opening, with the refresh of the manifest: what does not
// make it stays hidden until the next one. A cold row takes up to 4.5 s, 6 rows 2.5 s.
define('MEL_CATV_OPEN_S', 3.0);
// r24: s of it the meta of the rows "Continue" and "Мой список" (mel_cpm_build, before mel_catv_open) leaves to the rows
// at least: one row of the server (~0.4 s, 6 rows 2.5 s; mel_catv_open starts one with MEL_CATV_MIN_S left). The rows of
// memory and the copies take none of it: mel_catv_open counts its limit after them (the 35 rows of a tab of copies, the
// whole screen with them ~0.5 s on the Dune).
define('MEL_CATV_OPEN_ROW_S', 0.5);
// r25: one row of get_folder_rows, a key of the user waits for it; the stubs of a row.
define('MEL_CATV_ROW_S', 5.0);
define('MEL_CATV_STUBS', 9);
// r25: after a failure of the server this many s no row asks it (mel_catv_is_down).
define('MEL_CATV_DOWN_S', 30);
// The timeout of get_folder_view and of a portion of the grid is not known (10 s by
// the SDK): all of an opening, the first manifest in it, and a portion stay under it.
define('MEL_CATV_OPEN_MAX_S', 7.0);
define('MEL_CATV_GRID_S', 7.0);
// The first manifest, no copy: the screen waits for it.
define('MEL_CATV_MANIFEST_S', 5);
// The refresh of a copy (~0.3 s, 5 KB in gzip): r24 first in an opening, within MEL_CATV_OPEN_S;
// r25 at most this in get_folder_rows, keys and RETURN wait for php_server.
define('MEL_CATV_REFRESH_S', 2);
// Less than this left: no more requests.
define('MEL_CATV_MIN_S', 0.3);
define('MEL_CATV_ROW_TILES', 20);
define('MEL_CATV_GRID_PORTION', 50);
// r25 "Show all": rows of 9 ($ROWS_NUM_COLS of movie_list_screen.php), 112 of them hold the
// 1000 tiles of AIOMetadata; the shell can not add rows later.
define('MEL_CATV_ALL_COLS', 9);
define('MEL_CATV_ALL_ROWS', 112);
// The tiles a call of get_folder_rows loads past the row after the first one asked: the end on
// the border of a row closes the rows past it in the same reply.
define('MEL_CATV_ALL_AHEAD', 9);
// The timer of a view with stubs: the focus run past the end onto a closed row is lost, the tick
// once the end is known builds the view anew (catalog_probe y9, y9b).
define('MEL_CATV_ALL_TICK_MS', 1500);
// The searches kept in memory: Back to the results of an older one, its tiles and "Show all" work.
define('MEL_CATV_SEARCHES', 3);
// The screens of rows whose cat_logo of the opening is kept (mel_catv_logo_of).
define('MEL_CATV_SCREENS', 16);
// The main screens of rows the catalog keeps the addresses of (MelCat::$mains): of the two tabs and a new address.
define('MEL_CATV_MAINS', 4);
// A main screen built this many s at most after the player that invalidated it (MelCat::$inv_t) logs the time since; later
// it is not of that operation (the shell built it when the user came back to it).
define('MEL_CATV_INV_LOG_S', 10);
// The info panel, on the info_r of the native one (movie_helper.php of shell_ext), its colours
// and sizes of text: a title in TEXT_W, one line as large as fits from TITLE_PX down to
// TITLE_MIN_PX, else 2 lines of TITLE2_PX, its bottom at TITLE_BOTTOM (SUB_H higher over the
// English name); then lines of 30 px from LINES_Y (to fit TEXT_W the genres down to 26 px, the cast
// loses names), then the description. Names up to 197, descriptions up to 1096
// characters were seen. DESC_CHARS: of the text of an item of the grid.
define('MEL_CATV_PANE_W', 950);
define('MEL_CATV_PANE_H', 560);
define('MEL_CATV_TEXT_W', 800);
define('MEL_CATV_TITLE_PX', 88);
define('MEL_CATV_TITLE_MIN_PX', 60);
define('MEL_CATV_TITLE2_PX', 48);
define('MEL_CATV_TITLE_H', 120);
// TITLE_BOTTOM: PANE_Y + 216 = 256 is the bottom of the logo of a card: baked into card/v1 (MEL_CATV_CARD).
define('MEL_CATV_TITLE_BOTTOM', 216);
define('MEL_CATV_LINES_Y', 229);
define('MEL_CATV_LINE_PX', 30);
define('MEL_CATV_LINE_H', 46);
define('MEL_CATV_DATA_MIN_PX', 26);
// The description with the line of the history under it (mel_catv_info): this many lines under all the lines of the
// panel; the middle of the capitals of its line DESC_MID px under the top of the line (the mock of the designer).
define('MEL_CATV_DESC_HIST', 3);
define('MEL_CATV_DESC_MID', 32);
define('MEL_CATV_TITLE_COLOR', '#FFFFFFE0');
define('MEL_CATV_TEXT_COLOR', '#FFAFAFA0');
define('MEL_CATV_CAST_COLOR', '#FFFFFF40');
// The numbers of the ratings in the line of facts: white as the title, the rest of it grey.
define('MEL_CATV_RATE_COLOR', MEL_CATV_TITLE_COLOR);
// The line of facts (mel_catv_facts_labels): the ratings and the Russian age marks in outlined
// plates (img/cat1_kp.png ...), PLATE_H high, their tops PLATE_DY under the top of the line: centred
// on the capitals of 30 px (their middle 27 px under the top of the label, device 07.11.2026). The
// text over them by the shell: of a rating the name grey PLATE_PAD from the left, the number white
// as far from the right (both in one, centred, if they do not fit so by font_w.php), the age centred.
// Widths: "КП 8.9", "IMDb 9.5", "18+" at 30 px by font_w.php, PLATE_PAD a side (the age 10 a side).
// Gaps: between plates, before the rest of the line (grey text), before the age.
define('MEL_CATV_PLATE_H', 40);
define('MEL_CATV_PLATE_DY', 7);
define('MEL_CATV_PLATE_PAD', 12);
define('MEL_CATV_KP_W', 117);
define('MEL_CATV_IMDB_W', 153);
define('MEL_CATV_AGE_W', 73);
define('MEL_CATV_PLATE_GAP', 12);
define('MEL_CATV_REST_GAP', 18);
define('MEL_CATV_AGE_GAP', 16);
// The English name under the title (info_ntitle of movie_helper.php): 36 px down to 20, 2 lines.
define('MEL_CATV_SUB_PX', 36);
// SUB_H: the height of the English name under a title of text.
define('MEL_CATV_SUB_H', 46);
define('MEL_CATV_SUB_MIN_PX', 20);
define('MEL_CATV_SUB_COLOR', '#FF808080');
define('MEL_CATV_DESC_CHARS', 360);
// The TMDB size of a background; w780 if the shell runs short of memory.
define('MEL_CATV_BG_SIZE', 'w1280');
// The panel: x of info_r. The strip of the row titles (the main screen, the results of a search of
// two groups and more) moves it to the right by 30, to CARD_X, where the card background of the
// server has the logo (mel_catv_card); a screen without the strip (a search of one group, "Show all")
// with the card background: info_r at CARD_X itself.
// CARD_X (PANE_X + 30) is the left of the logo of a card, PANE_Y + TITLE_BOTTOM its bottom: baked into card/v1 (MEL_CATV_CARD).
define('MEL_CATV_PANE_X', 100);
define('MEL_CATV_CARD_X', 130);
define('MEL_CATV_PANE_Y', 40);
define('MEL_CATV_ICONS', 'plugin_file://%shell_ext%/icons/');
// The icons of the shell for the search and the setup: on the strip and in POP UP.
define('MEL_CATV_ICON_SEARCH', 'xxx4.png');
define('MEL_CATV_ICON_SETUP', 'xxx5.png');
// "Мой список" (view_my_list.php): the heart of the shell, on the strip left of the search and in POP UP.
define('MEL_CATV_ICON_MYLIST', 'xxx3.png');
// "Продолжить" (view_my_list.php): the clock of the shell (its history), left of the heart, as on the main
// screen of the Dune, and in POP UP.
define('MEL_CATV_ICON_CONT', 'xxx2.png');
// Our pictures of the panel, img/cat<N>_*: N goes up when they change, as the shell keeps a
// picture by its URL even across a reinstall (plugins/melange_catalog_img.py of the kitchen). The
// mask: of the shell, but darker to the right, so the text column (TEXT_W) stays on the dark
// whatever the background; card0.jpg: the dark background of vod_r of the whole screen without a
// card background (mel_catv_card). cat2_mask.png: baked into card/v1 (MEL_CATV_CARD).
define('MEL_CATV_IMG', 'plugin_file://img/cat2_');
// The IMDb rating in the top left corner of a poster of a row (mel_catv_rate_stickers): a plate
// RATE_W x RATE_H at (RATE_PAD, RATE_PAD) of the poster 190x285, 70 % black, the N of rate<N> goes
// up when it changes, as of cat<N>; the number over it, sticker_text_size RATE_PX of the template.
define('MEL_CATV_RATE_PLATE', 'plugin_file://img/rate1_plate.png');
define('MEL_CATV_RATE_PAD', 8);
define('MEL_CATV_RATE_W', 52);
define('MEL_CATV_RATE_H', 32);
define('MEL_CATV_RATE_PX', 25);
// The card backgrounds of the server: <base>/<CARD>... (mel_catv_card). Its layout - the logo up to
// 780x200 at CARD_X, its bottom at PANE_Y + TITLE_BOTTOM (256), the mask cat2_mask.png - is baked
// into card/v1 of the server (addon/lib/posterCache/card.ts of the fork of AIOMetadata): a change
// after a release of the plugin = v2 in the path and in card.ts.
define('MEL_CATV_CARD', 'poster-cache/card/v1/');
// The strip on top of the main screen, as the native top menu: the tabs "Movies" and "Series"
// (rows by their type), the icons of search and settings of the shell. The id of its row; the
// ids of its parts are "tab_<x>", no ':' (an id with one is a tile). A part in focus far right
// scrolls the whole strip: on the device a part at 1606-1670 did not (its frame on the part), one
// at 1640-1704 did. So the gear ends at 1660 and its frame, with the margins, at 1670.
define('MEL_CATV_STRIP', 'tabs');
// The header_id of a row of tiles: this and its rid (the shell names the headers of the rows without
// one by their number, "H0" is the strip). POP UP on a header brings it as selected_header_id
// (genre_rows_screen.php r25: header_id = the id of the list the dialog of the rows edits).
define('MEL_CATV_HEADER', 'h:');
// The header of the strip, as the shell names it: the strip has no header_id and is the first row
// with a title, so "H0" (device r25: POP UP on "Menu" brings it, the sel_state of a switch of the
// tab has it). A header_id of the strip would change both; the other rows all have MEL_CATV_HEADER.
define('MEL_CATV_STRIP_HEADER', 'H0');
// The tabs as the native menu: text in caps, the open one brighter (the colours of the title and
// the text of the panel, as the native ones), a blue line under it. No focused variant: it does
// nothing in a row of GComps, only the frame of the shell shows the focus.
define('MEL_CATV_TAB_PX', 38);
define('MEL_CATV_UNDERLINE', '#FF3870FF');

class MelCat
{
    // Listings by rid: what the info panel and ENTER read. Memory of php_server
    // only; after a restart a tile or "Show all" asked for brings the saved first page of its row
    // (mel_catv_list_restore).
    /** @var array<string, MelCatList> */
    public static $lists = array();
    // The last MEL_CATV_SEARCHES searches, the oldest first: by its id, the rids of its rows.
    /** @var array<string, list<string>> */
    public static $searches = array();
    // The searches of them opened as "Show all" of their one row (mel_catv_search_run): that
    // screen is their results.
    /** @var array<string, bool> */
    public static $at_once = array();
    // r25: when a row came empty, by rid. It is hidden at the openings of the next hour.
    /** @var array<string, int> */
    public static $empty = array();
    // r25: the manifest to refresh in get_folder_rows, once an opening.
    /** @var bool */
    public static $manifest_due = false;
    // When the manifest was last asked to refresh its copy, a failure too (mel_catv_manifest_tried); 0: not yet.
    /** @var int */
    public static $manifest_tried = 0;
    // r25: when a request of a row failed for the server (net, rate, server), 0: it did not.
    /** @var int */
    public static $down = 0;
    // When a row timed out at an opening of r24 (mel_catv_open) or at the timer of the tab "TV" (mel_tv_refresh, the
    // same php_server), by rid; within MEL_CAT_FRESH both ask it after the others, until it loads (mel_catv_slow_note).
    /** @var array<string, int> */
    public static $slow = array();
    // r25: the tiles of the flagged rows as this screen shows them, by rid and id: the shell
    // throws away a reply cancelled by ENTER, while the listing has already moved on. Also the
    // tiles of an old copy the grid of "Show all" began anew from (mel_catv_all_list): they
    // stay on the screen it was opened from.
    /** @var array<string, array<int|string, MelCatTile>> */
    public static $on_screen = array();
    // The language of the last screen of rows ('': not read yet), mel_catv_ui_lang.
    /** @var string */
    public static $lang = '';
    // "Show all": by rid, a call of get_folder_rows failed; no request until ENTER on its "did not load".
    /** @var array<string, bool> */
    public static $all_failed = array();
    // "Show all": by rid, the last view has stubs (and the timer).
    /** @var array<string, bool> */
    public static $all_stubs = array();
    // "Show all": the ticks of the timer of the last view with stubs and what the last one gave, for the log.
    /** @var int */
    public static $all_ticks = 0;
    /** @var string */
    public static $all_tick_last = '';
    // The query of the last search, cut as the search cuts it: the field of the next search
    // starts with it. Memory of php_server only, as the query is in no log and no file.
    /** @var string */
    public static $last_q = '';
    // cat_logo of the screen of rows built or asked for now (mel_catv_logo_of; null: none yet, the
    // settings): its vod_r and the ids of its tiles are by it.
    /** @var bool|null */
    public static $logo = null;
    // cat_logo of the last MEL_CATV_SCREENS screens of rows at their openings, by media_url.
    /** @var array<string, bool> */
    public static $logo_of = array();
    // The same of cat_rating (mel_catv_rating_on): a screen has the plates on all its tiles or on none.
    /** @var bool|null */
    public static $rating = null;
    /** @var array<string, bool> */
    public static $rating_of = array();
    // The main screens of rows opened last (their media_url, MEL_CATV_MAINS at most, the latest last): invalidated
    // back from the player and after a mark of the own card (mel_catv_mains_anew).
    /** @var list<string> */
    public static $mains = array();
    // r25: when the operation back from the player that invalidated them began (Mel::$t0, mel_catv_after_player): the
    // first of them built again within MEL_CATV_INV_LOG_S logs the time since (O2); 0: none.
    /** @var float */
    public static $inv_t = 0.0;
}

// ru or en, as the settings page reads it (cgi/lib/http.php).
/**
 * @return string
 */
function mel_catv_lang()
{
    $f = getenv('FS_PREFIX') . '/config/settings.properties';
    $text = is_file($f) ? strval(file_get_contents($f, false, null, 0, 65536)) : '';
    $l = preg_match('/^interface_language[ \t]*=[ \t]*(\w+)/m', $text, $m) ? $m[1] : '';
    if ($l === 'custom')
        $l = preg_match('/^noncustom_interface_language[ \t]*=[ \t]*(\w+)/m', $text, $m) ? $m[1] : '';
    return $l === 'russian' ? 'ru' : 'en';
}

// The language of the info panel and the grid: of the last screen of rows, read once
// after a restart of php_server (the grid or Back to the rows open no screen of rows).
/**
 * @return string
 */
function mel_catv_ui_lang()
{
    if (MelCat::$lang === '')
        MelCat::$lang = mel_catv_lang();
    return MelCat::$lang;
}

/**
 * @param stdClass $in
 * @param string $k
 * @return string
 */
function mel_catv_in($in, $k)
{
    return isset($in->$k) && is_string($in->$k) ? $in->$k : '';
}

/**
 * @param MelCatRow $row
 * @return string
 */
function mel_catv_rid($row)
{
    // A row of a search: a rid of its own for every search (sid), the key is one for all.
    return substr(md5($row['key'] . (isset($row['sid']) ? ':' . $row['sid'] : '')), 0, 8);
}

// A listing and all its tiles so far, by the id of a tile. stale: of a copy, a request failed.
/**
 * @param MelCatState $st
 * @param list<MelCatTile> $tiles
 * @param bool $stale
 * @return MelCatList
 */
function mel_catv_list($st, $tiles, $stale)
{
    $list = array('st' => $st, 'tiles' => array(), 'stale' => $stale);
    foreach ($tiles as $t)
        $list['tiles'][$t['id']] = $t;
    return $list;
}

// A listing from the saved first page, as if it was just got.
/**
 * @param MelCatRow $row
 * @param MelCatCached $c
 * @return MelCatList
 */
function mel_catv_list_copy($row, $c)
{
    $copy = mel_cat_state_of_copy($row, $c);
    return mel_catv_list($copy['st'], $copy['tiles'], false);
}

// The mode of the panel of an id of a tile or of "Show all" of a screen of rows: "c:" before it,
// cat_logo on (the card background, vod_r of the whole screen), "f:" off (the fanart on the right).
// The shell keeps the reply of rows_info by the id of the tile even across an exit and a new opening
// of the screen (device, r25): without the mode in the id a tile seen before a change of cat_logo
// would get the panel of the other vod_r. -> the mode, the id without it. No mode (an id of a
// screen before 0.55.0, the vod_r on the right): off.
/**
 * @param string $id
 * @return array{bool, string}
 */
function mel_catv_id_mode($id)
{
    if (strpos($id, 'c:') === 0 || strpos($id, 'f:') === 0)
        return array($id[0] === 'c', strval(substr($id, 2)));
    return array(false, $id);
}

// $id of a tile or of "Show all" with the mode of the screen (mel_catv_id_mode).
/**
 * @param string $id
 * @return string
 */
function mel_catv_tile_id($id)
{
    return (mel_catv_logo_on() ? 'c:' : 'f:') . $id;
}

// The tile of an id of the screen, or null. Looked for in this order:
// 1. $tv (asked from the tab "TV"): the tiles of its file (mel_tv_tile); the listing in memory may have moved on since.
//    A tile of the row "Continue" ("continue:<movie_id>"): of the history and the pairs (mel_cp_tile), not of a listing;
//    of the screen "Мой список" ("mylist:<key>"): of its entry (mel_mlv_tile).
// 2. MelCat::$lists, "restored" ones too (mel_catv_list_restore); mel_catv_all_list begins the grid anew
//    from one older than MEL_CAT_FRESH, mel_catv_all_rows goes on from none older than MEL_CAT_STATE_TTL.
// 3. MelCat::$on_screen: r25, the flagged rows as the screen shows them; the old copy a grid began anew from.
// 4. No listing of its rid in memory (a restart of php_server, a file the shell shows without PHP): the copy
//    on the disk (mel_catv_list_restore). A listing in memory without the tile: null, the copy is not newer.
/**
 * @param string $id
 * @param bool $tv
 * @return MelCatTile|null
 */
function mel_catv_tile($id, $tv = false)
{
    list(, $id) = mel_catv_id_mode($id);
    $t = $tv ? mel_tv_tile($id) : null;
    if ($t)
        return $t;
    $p = explode(':', $id, 2);
    if (count($p) !== 2)
        return null;
    if ($p[0] === MEL_CP_RID)
        return mel_cp_mid_of($id) !== '' ? mel_cp_tile($p[1]) : null;
    if ($p[0] === MEL_ML_RID)
        return mel_mlv_tile($p[1]);
    if (isset(MelCat::$lists[$p[0]]['tiles'][$p[1]]))
        return MelCat::$lists[$p[0]]['tiles'][$p[1]];
    if (isset(MelCat::$on_screen[$p[0]][$p[1]]))
        return MelCat::$on_screen[$p[0]][$p[1]];
    $list = isset(MelCat::$lists[$p[0]]) ? null : mel_catv_list_restore($p[0]);
    return isset($list['tiles'][$p[1]]) ? $list['tiles'][$p[1]] : null;
}

// The listing of the row $rid of the saved manifest not in memory, from the saved first page of
// any age, no request; it goes to memory marked "restored" (mel_catv_all_list). null: no such row
// or no copy of it.
/**
 * @param string $rid
 * @return MelCatList|null
 */
function mel_catv_list_restore($rid)
{
    // A rid (mel_catv_rid), not "more", "stub" and the like: they read no manifest.
    if (!preg_match('/^[0-9a-f]{8}$/D', $rid))
        return null;
    $row = mel_catv_row_by_rid($rid);
    $c = $row ? mel_cat_page_cached($row, 0) : null;
    if (!$row || !$c)
        return null;
    $list = mel_catv_list_copy($row, $c);
    $list['restored'] = true;
    MelCat::$lists[$rid] = $list;
    return $list;
}

// --- Pictures: the size is in the address; a picture of another type or width is fine.

// Never empty. A path (of a picture of the Favorites of the Dune, mel_ml_url) as it is, as the shell has it in its row,
// if the file is there (PHP of the Dune adds FS_PREFIX itself, as mel_ml_fav_read): taken out of the Favorites of the
// Dune (its folder gone) or of a backup of another Dune - the usual picture of none.
/**
 * @param MelCatTile $t
 * @return string
 */
function mel_catv_poster($t)
{
    if (substr($t['poster'], 0, 1) === '/')
        return is_file($t['poster']) ? $t['poster'] : MEL_CATV_ICONS . 'no_movie_poster.png';
    $u = mel_img(str_replace('/poster-cache/poster/', '/poster-cache/w342/poster/', $t['poster']));
    return $u !== '' ? $u : MEL_CATV_ICONS . 'no_movie_poster.png';
}

/**
 * @param MelCatTile $t
 * @return string
 */
function mel_catv_background($t)
{
    $u = mel_img(strval(preg_replace('~/t/p/[a-z0-9_]+/~', '/t/p/' . MEL_CATV_BG_SIZE . '/', $t['background'])));
    return $u !== '' ? $u : MEL_CATV_ICONS . 'bg0.jpg';
}

// cat_logo ("Логотип фильма вместо названия" of the page) in effect on the screens of rows: on
// only where the shell sizes the background by vod_r (mel_fw_vod_r), as the card of the whole
// screen needs. Else, the setting kept as it is, the fanart on the right and the title as text;
// after an update to r25 the logo comes.
/**
 * @return bool
 */
function mel_catv_logo_effective()
{
    return Mel::$settings['cat_logo'] && mel_fw_vod_r();
}

// cat_logo (mel_catv_logo_effective) of the screen of rows built or asked for now.
/**
 * @return bool
 */
function mel_catv_logo_on()
{
    return MelCat::$logo !== null ? MelCat::$logo : mel_catv_logo_effective();
}

// cat_logo of the screen of rows $url: at its opening ($open, mel_catv_folder_view) of the settings,
// later (get_folder_rows: the rows of "Show all" as it scrolls) the same as then, so all its tiles
// are of the mode of its vod_r. A screen not among the last MEL_CATV_SCREENS (php_server restarted):
// the settings now. cat_rating the same way, so the rows of a screen are all with the rating or without.
/**
 * @param string $url
 * @param bool $open
 * @return void
 */
function mel_catv_logo_of($url, $open)
{
    if ($open || !isset(MelCat::$logo_of[$url]))
    {
        unset(MelCat::$logo_of[$url]);
        MelCat::$logo_of[$url] = mel_catv_logo_effective();
        if (count(MelCat::$logo_of) > MEL_CATV_SCREENS)
            array_shift(MelCat::$logo_of);
        unset(MelCat::$rating_of[$url]);
        MelCat::$rating_of[$url] = Mel::$settings['cat_rating'];
        if (count(MelCat::$rating_of) > MEL_CATV_SCREENS)
            array_shift(MelCat::$rating_of);
    }
    MelCat::$logo = MelCat::$logo_of[$url];
    MelCat::$rating = MelCat::$rating_of[$url];
}

// cat_rating ("Рейтинг IMDb на постерах" of the page) of the screen of rows built or asked for now.
/**
 * @return bool
 */
function mel_catv_rating_on()
{
    return MelCat::$rating !== null ? MelCat::$rating : Mel::$settings['cat_rating'];
}

// cat_logo on: vod_r of the whole screen (the card background is of it), else on the right as the
// native one. The panel at PANE_X, at CARD_X on a screen without the strip of the row titles ($titles).
/**
 * @param bool $titles
 * @return array{info_r: array{w: int, h: int, x: int, y: int}, vod_r: array{w: int, h: int, x: int, y: int}}
 */
function mel_catv_pane_r($titles)
{
    $on = mel_catv_logo_on();
    return array('info_r' => array('w' => MEL_CATV_PANE_W, 'h' => MEL_CATV_PANE_H,
            'x' => $on && !$titles ? MEL_CATV_CARD_X : MEL_CATV_PANE_X, 'y' => MEL_CATV_PANE_Y),
        'vod_r' => $on ? array('w' => 1920, 'h' => 1080, 'x' => 0, 'y' => 0) : array('w' => 1280, 'h' => 720, 'x' => 640, 'y' => 0));
}

// The background of the panel of tile $t (null: none, "Show all"), the one while it loads or if it
// fails (nl_bg_url), its mask, and whether it has the logo in place of the title, in the mode $on of
// the id (mel_catv_id_mode). cat_logo off: the fanart (mel_catv_background) on the right under our
// mask. On: the card background of the server, the fanart, our mask and the logo in one picture of the
// screen, so the panel comes with all of it at once (the shell switches the panel once the background
// has loaded, its pictures load after: experiments/bg_probe of the kitchen). Of the cache of
// backgrounds of the server only, <base>/poster-cache/background/<TMDB address>: the card is
// <base>/MEL_CATV_CARD[n/<logo>/]<fanart>, TMDB file names; n: no English name under the logo.
// The logo: the Russian one mel_cat_tile kept. No such fanart (none, another
// server, not of TMDB) or no tile: our dark card0.jpg, no mask; the same while a card loads.
/**
 * @param MelCatTile|null $t
 * @param bool $on
 * @return array{bg: string, nl: string|null, mask: string|null, logo: bool}
 */
function mel_catv_card($t, $on)
{
    if (!$on)
        return array('bg' => $t ? mel_catv_background($t) : MEL_CATV_ICONS . 'bg0.jpg', 'nl' => null, 'mask' => MEL_CATV_IMG . 'mask.png',
            'logo' => false);
    $none = MEL_CATV_IMG . 'card0.jpg';
    if (!$t || !preg_match('~^(https?://[^\s?#]+?)/poster-cache/background/' . MEL_CAT_TMDB . '([A-Za-z0-9]{10,40}\.jpg)$~D',
        $t['background'], $f))
        return array('bg' => $none, 'nl' => $none, 'mask' => null, 'logo' => false);
    $logo = $t['logo'] !== '' ? basename($t['logo']) : '';
    $path = $logo !== '' ? "n/$logo/$f[2]" : $f[2];
    return array('bg' => mel_img("$f[1]/" . MEL_CATV_CARD . $path), 'nl' => $none, 'mask' => null, 'logo' => $logo !== '');
}

// --- What to give at an opening.

// The refresh of the manifest was tried less than MEL_CAT_MANIFEST_FRESH ago: a failure keeps
// the window too, so a slow server does not hold every opening. The clock going back is not trusted.
/**
 * @return bool
 */
function mel_catv_manifest_tried()
{
    $since = time() - MelCat::$manifest_tried;
    return MelCat::$manifest_tried > 0 && $since >= 0 && $since < MEL_CAT_MANIFEST_FRESH;
}

// The refresh of a copy of the manifest in $limit s (mel_cat_manifest_fetch), its time remembered.
/**
 * @param int $limit
 * @return array{rows: list<MelCatRow>, err: string}
 */
function mel_catv_manifest_refresh($limit)
{
    MelCat::$manifest_tried = time();
    return mel_cat_manifest_fetch($limit);
}

// The server failed less than MEL_CATV_DOWN_S ago. The clock going back is not trusted.
/**
 * @return bool
 */
function mel_catv_is_down()
{
    $since = time() - MelCat::$down;
    return MelCat::$down > 0 && $since >= 0 && $since < MEL_CATV_DOWN_S;
}

// Row $rid timed out less than MEL_CAT_FRESH ago (MelCat::$slow): asked after the others.
/**
 * @param string $rid
 * @return bool
 */
function mel_catv_is_slow($rid)
{
    return isset(MelCat::$slow[$rid]) && mel_cat_fresh_since(MelCat::$slow[$rid]);
}

// The err of a request of the first page of row $rid (mel_cat_reply): a timeout - into MelCat::$slow, none - out of it.
/**
 * @param string $rid
 * @param string $err
 * @return void
 */
function mel_catv_slow_note($rid, $err)
{
    if ($err === 'timeout')
        MelCat::$slow[$rid] = time();
    else if ($err === '')
        unset(MelCat::$slow[$rid]);
}

// The first page of a row asked for anew: with tiles, into MelCat::$lists (of a copy
// after a failure: stale), else what is there stays; an empty row is remembered.
// MelCat::$down by its failure: net, rate, server, and a timeout unless $timeout_down is
// false (an opening: a slow row or the end of its limit, not the server down).
/**
 * @param MelCatRow $row
 * @param float $limit
 * @param bool $timeout_down
 * @return MelCatResult
 */
function mel_catv_load_first($row, $limit, $timeout_down = true)
{
    $rid = mel_catv_rid($row);
    $st = mel_cat_row_state($row);
    $res = mel_cat_more($st, $limit);
    if ($res['tiles'])
        MelCat::$lists[$rid] = mel_catv_list($st, $res['tiles'], $res['stale']);
    else if ($res['end'] && $res['err'] === '')
        MelCat::$empty[$rid] = time();
    $down = mel_cat_err_down($res['err']) || ($timeout_down && $res['err'] === 'timeout');
    MelCat::$down = $down ? time() : 0;
    return $res;
}

// The rows $rows for a new screen, into MelCat::$lists. At once: listings in memory
// still fresh, then the saved first pages of any age. In $limit s, one by one: the rows
// without a copy, then the old copies (the oldest first: rows past the limit of one opening
// are first at the next), then the rows that timed out at an opening before (MelCat::$slow):
// one slow row does not take the time of every opening. A timeout is not
// the server down; on a failure of the server (mel_catv_is_down) no more requests and the
// old copies not refreshed are marked stale. Those the limit left out are not. No tile: the
// row stays out of the lists. -> a row was asked of the server.
/**
 * @param list<MelCatRow> $rows
 * @param float $limit
 * @return bool
 */
function mel_catv_open($rows, $limit)
{
    $net = false;
    $none = array();
    $old = array();
    $slow = array();
    $old_t = array();
    foreach ($rows as $row)
    {
        $rid = mel_catv_rid($row);
        if (isset(MelCat::$lists[$rid]) && mel_cat_state_fresh(MelCat::$lists[$rid]['st']))
            continue;
        unset(MelCat::$lists[$rid]);
        $c = mel_cat_page_cached($row, 0);
        // Not "restored" (mel_catv_list_restore): this screen shows it.
        if ($c)
            MelCat::$lists[$rid] = mel_catv_list_copy($row, $c);
        if ($c && mel_cat_is_fresh($c))
            continue;
        if (mel_catv_is_slow($rid))
            $slow[$rid] = $row;
        else if (!$c)
            $none[$rid] = $row;
        else
        {
            $old[$rid] = $row;
            $old_t[] = $c['t'];
        }
    }
    $sorted = array();
    foreach (mel_cat_oldest_first(array_keys($old), $old_t) as $rid)
        $sorted[$rid] = $old[$rid];
    $old = $sorted;
    $start = microtime(true);
    $is_down = false;
    foreach ($none + $old + $slow as $rid => $row)
    {
        $left = $limit - (microtime(true) - $start);
        if ($left < MEL_CATV_MIN_S)
            break;
        $res = mel_catv_load_first($row, $left, false);
        $net = true;
        mel_catv_slow_note($rid, $res['err']);
        unset($old[$rid], $slow[$rid]);
        $is_down = mel_catv_is_down();
        if ($is_down)
            break;
    }
    if ($is_down)
    {
        foreach ($old + $slow as $rid => $row)
        {
            if (!isset(MelCat::$lists[$rid]))
                continue;
            $list = MelCat::$lists[$rid];
            $list['stale'] = true;
            MelCat::$lists[$rid] = $list;
        }
    }
    return $net;
}

// --- Building of a row, by its data.

// The only place that makes a tile of a row: without a string in icon_url the whole
// shell dies (gcomp_def.c:962 assertion "url" failed).
/**
 * @param string $id
 * @param string $caption
 * @param mixed $icon
 * @return array<string, string>
 */
function mel_catv_item($id, $caption, $icon)
{
    return array(
        PluginRegularItem::id => $id,
        PluginRegularItem::icon_url => is_string($icon) && $icon !== '' ? $icon : MEL_CATV_ICONS . 'no_movie_poster.png',
        PluginRegularItem::caption => $caption);
}

// The tile of a title in a row: its poster, the IMDb rating in the corner if it has one and
// cat_rating of the screen is on (mel_catv_rating_on), the badges of the history at the bottom
// (mel_pg_stickers; made now, never in the cache of the pages).
/**
 * @param string $rid
 * @param MelCatTile $t
 * @return array<string, mixed>
 */
function mel_catv_title_item($rid, $t)
{
    $item = mel_catv_item(mel_catv_tile_id("$rid:{$t['id']}"), $t['name'], mel_catv_poster($t));
    $stickers = mel_catv_rating_on() ? mel_catv_rate_stickers($t['imdbRating']) : array();
    $b = mel_pg_tile_badge($t);
    if ($b)
        $stickers = array_merge($stickers, mel_pg_stickers($b));
    if ($stickers)
        $item[PluginRegularItem::stickers] = $stickers;
    return $item;
}

// The number of an IMDb rating on a poster ("8.6", "10"), '' for none; also of the rows of the own
// card (mel_oc_rate_over).
/**
 * @param string $rating
 * @return string
 */
function mel_catv_rate_text($rating)
{
    // "0.04" is "0.0": none, as of kpRating; over 10 ("99.99" is "100.0", wider than the plate) none too.
    $s = sprintf('%.1F', (float) $rating);
    if ((float) $s <= 0 || (float) $s > 10)
        return '';
    return $s === '10.0' ? '10' : $s;
}

// The plate and the number of an IMDb rating (mel_catv_rate_text), none for none. In pixels of the
// poster 190x285, the shell scales them in focus. The shell puts the text at the left of its r and a
// pixel over the middle of it: the r of the text is a pixel lower than the plate, its x centres the
// number by font_w.php.
/**
 * @param string $rating
 * @return list<array<string, mixed>>
 */
function mel_catv_rate_stickers($rating)
{
    $s = mel_catv_rate_text($rating);
    if ($s === '')
        return array();
    $p = MEL_CATV_RATE_PAD;
    $x = $p + intval(round((MEL_CATV_RATE_W - mel_gc_sum($s, MEL_CATV_RATE_PX)) / 2));
    return array(
        array(PluginRegularSticker::r => array('w' => MEL_CATV_RATE_W, 'h' => MEL_CATV_RATE_H, 'x' => $p, 'y' => $p),
            PluginRegularSticker::icon_url => MEL_CATV_RATE_PLATE),
        array(PluginRegularSticker::r => array('w' => $p + MEL_CATV_RATE_W - $x, 'h' => MEL_CATV_RATE_H, 'x' => $x, 'y' => $p + 1),
            PluginRegularSticker::text => $s));
}

// The title over the row of group "row:$g".
/**
 * @param string $g
 * @param string $caption
 * @return array<string, mixed>
 */
function mel_catv_title_row($g, $caption)
{
    return array(
        PluginRow::type => PLUGIN_ROW_TYPE_TITLE,
        PluginRow::id => "title:$g",
        PluginRow::group_id => "row:$g",
        PluginRow::height => 55,
        PluginRow::inactive_height => 0,
        PluginRow::data => array(
            PluginTitleRow::caption => $caption,
            PluginTitleRow::color => '#FFFFFFE0',
            PluginTitleRow::font_size => 30,
            PluginTitleRow::left => 100,
            PluginTitleRow::dy => 9,
            PluginTitleRow::active_dy => 0,
            PluginTitleRow::width => 1920,
            PluginTitleRow::fade_enabled => true,
            PluginTitleRow::fade_color => '#FF606060',
            PluginTitleRow::lite_fade_color => '#FF808080'));
}

// A row of the panel: its title and tiles, the first page and "Show all", or $items
// instead of them. $options: of the row of tiles. $rid: the rid of its ids ('': of the row, mel_catv_rid; a row of
// our own, "Continue": its own).
/**
 * @param MelCatList $list
 * @param string $lang
 * @param list<array<string, mixed>>|null $items
 * @param int $options
 * @param string $rid
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function mel_catv_row_defs($list, $lang, $items = null, $options = 0, $rid = '')
{
    $row = $list['st']['row'];
    if ($rid === '')
        $rid = mel_catv_rid($row);
    $more = '';
    // The no-break space at the end of the name of a search row (cat_movies) not before the mark.
    $title = $list['stale'] ? strval(preg_replace('/\x{00A0}$/u', '', $row['name'])) . ' (' . mel_tr($lang, 'cat_saved') . ')' : $row['name'];
    if ($items === null)
    {
        $items = array();
        foreach (array_slice($list['tiles'], 0, MEL_CATV_ROW_TILES) as $t)
            $items[] = mel_catv_title_item($rid, $t);
        // A search row: "Show all" past a row of its screen, so a long one is not scrolled through.
        if ($list['st']['end'] === '' || count($list['tiles']) > (isset($row['q']) ? MEL_CATV_ALL_COLS : MEL_CATV_ROW_TILES))
        {
            // Of a search: and its id, as in cat_grid:.
            $more = mel_catv_tile_id(isset($row['sid']) ? "more:$rid:{$row['sid']}" : "more:$rid");
            $items[] = mel_catv_item($more, mel_tr($lang, 'cat_more'), MEL_CATV_ICONS . 'more.png');
        }
    }
    $defs = array(
        mel_catv_title_row($rid, $title),
        array(
            PluginRow::type => PLUGIN_ROW_TYPE_REGULAR,
            PluginRow::id => "items:$rid",
            PluginRow::title => $title,
            PluginRow::header_id => MEL_CATV_HEADER . $rid,
            PluginRow::group_id => "row:$rid",
            PluginRow::height => 302,
            PluginRow::inactive_height => 300,
            PluginRow::data => array(
                PluginRegularRow::items => $items,
                PluginRegularRow::item_params_template_id => 'common',
                PluginRegularRow::width => 1920,
                PluginRegularRow::left_padding => 90,
                PluginRegularRow::inactive_left_padding => 120,
                PluginRegularRow::right_padding => 120,
                PluginRegularRow::fade_enabled => true,
                PluginRegularRow::fade_icon_mix_color => 0,
                PluginRegularRow::fade_icon_mix_alpha => 200,
                PluginRegularRow::lite_fade_icon_mix_alpha => 128,
                PluginRegularRow::fade_caption_color => '#FF808080')));
    if ($options)
        $defs[1][PluginRow::options] = $options;
    // ENTER on the title of the row in the panel of titles: "Show all", as genre_rows_screen.php.
    if ($more !== '')
        $defs[1][PluginRow::show_all_action] = mel_input('cat_all', array('cat_more' => $more));
    return $defs;
}

// --- r25: rows the shell asks for (get_folder_rows).

// The flag of a row to ask for; 0: r24, no such operation, the rows load at an opening.
/**
 * @return int
 */
function mel_catv_loading_flag()
{
    return defined('PLUGIN_OP_GET_FOLDER_ROWS') && defined('PLUGIN_ROW_OPT_LOADING') ? PLUGIN_ROW_OPT_LOADING : 0;
}

/**
 * @param string $rid
 * @return bool
 */
function mel_catv_is_empty($rid)
{
    return isset(MelCat::$empty[$rid]) && mel_cat_fresh_since(MelCat::$empty[$rid]);
}

// A tile of a message: "fail:<rid>" (cat_row_failed, ENTER tries again) or "empty:<rid>" (cat_row_empty).
/**
 * @param string $rid
 * @param string $note
 * @param string $lang
 * @return array<string, string>
 */
function mel_catv_note_item($rid, $note, $lang)
{
    return mel_catv_item(($note === 'cat_row_empty' ? 'empty:' : 'fail:') . $rid, mel_tr($lang, $note), MEL_CATV_ICONS . 'no_movie_poster.png');
}

// A row without tiles of titles. $note '': stubs for the shell to ask for. Else one tile
// with this message and no flag: a row hidden on the screen takes the focus away with it.
/**
 * @param MelCatRow $row
 * @param string $lang
 * @param string $note
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function mel_catv_row_note($row, $lang, $note)
{
    $rid = mel_catv_rid($row);
    $list = mel_catv_list(mel_cat_row_state($row), array(), false);
    if ($note !== '')
        return mel_catv_row_defs($list, $lang, array(mel_catv_note_item($rid, $note, $lang)));
    $items = array();
    for ($i = 0; $i < MEL_CATV_STUBS; $i++)
        $items[] = mel_catv_item("stub:$rid:$i", mel_tr($lang, 'cat_row_loading'), MEL_CATV_ICONS . 'loading_movie_poster.png');
    return mel_catv_row_defs($list, $lang, $items, mel_catv_loading_flag());
}

// The row $row now, never flagged (such a row is not asked for again): from memory or the
// cache when it can be, so a repeat of a call the shell cancelled (ENTER) is at once and
// the same; a new state every time, its skip never moves on. Else asked in $limit s; the
// server down: no request, what memory has is a saved copy. No tiles: one tile "empty" or
// "did not load". -> its title and its row.
/**
 * @param MelCatRow $row
 * @param string $lang
 * @param float $limit
 * @param bool $timeout_down
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function mel_catv_row_now($row, $lang, $limit, $timeout_down = true)
{
    $rid = mel_catv_rid($row);
    $ready = isset(MelCat::$lists[$rid]) && MelCat::$lists[$rid]['tiles'] && mel_cat_state_fresh(MelCat::$lists[$rid]['st']);
    if (!$ready && !mel_catv_is_empty($rid) && !mel_catv_is_down())
        mel_catv_load_first($row, $limit, $timeout_down);
    if (isset(MelCat::$lists[$rid]) && MelCat::$lists[$rid]['tiles'])
    {
        if (!mel_cat_state_fresh(MelCat::$lists[$rid]['st']))
            MelCat::$lists[$rid]['stale'] = true;
        return mel_catv_row_defs(MelCat::$lists[$rid], $lang);
    }
    return mel_catv_row_note($row, $lang, mel_catv_is_empty($rid) ? 'cat_row_empty' : 'cat_row_failed');
}

// The row $rid for the shell (mel_catv_row_now) -> the row and, only for a saved copy, its
// title: the shell replaces any row of a reply by its id, and that is the only way the
// title gets the mark.
/**
 * @param string $rid
 * @param string $lang
 * @return non-empty-list<array<string, mixed>>
 */
function mel_catv_row_answer($rid, $lang)
{
    $row = isset(MelCat::$lists[$rid]) ? MelCat::$lists[$rid]['st']['row'] : mel_catv_row_by_rid($rid);
    if (!$row)
    {
        mel_log('catalog: row: unknown id');
        $row = array('key' => '', 'type' => 'movie', 'id' => '', 'name' => mel_tr($lang, 'cat_title'), 'genre' => '', 'home' => false, 'group' => '');
        $defs = mel_catv_row_note($row, $lang, 'cat_row_failed');
        return array($defs[1]);
    }
    $defs = mel_catv_row_now($row, $lang, MEL_CATV_ROW_S);
    $saved = isset(MelCat::$lists[$rid]) && MelCat::$lists[$rid]['tiles'] && MelCat::$lists[$rid]['stale'];
    return $saved ? array($defs[1], $defs[0]) : array($defs[1]);
}

// get_folder_rows: "Show all": mel_catv_all_rows. Else one row a call (a saved copy: and
// its title), the shell asks for the rest next.
// Always a row with the id asked: on a reply without rows the shell asks again
// every ~1 ms. The refresh of the manifest (MelCat::$manifest_due) takes the time left of the call,
// MEL_CATV_REFRESH_S at most: an opening does not wait for it.
/**
 * @param stdClass $in
 * @return array{rows: list<array<string, mixed>>}
 */
function mel_catv_rows_op($in)
{
    $start = microtime(true);
    $ids = isset($in->row_ids) && is_array($in->row_ids) ? $in->row_ids : array();
    $url = mel_catv_in($in, 'media_url');
    mel_catv_logo_of($url, false);
    if (strpos($url, 'cat_grid:') === 0)
    {
        $all = mel_catv_all_rows($url, $ids);
        mel_pg_rows_answered();
        return array('rows' => $all);
    }
    $id = isset($ids[0]) && is_string($ids[0]) ? $ids[0] : 'items:';
    $rid = strpos($id, 'items:') === 0 ? strval(substr($id, strlen('items:'))) : '';
    $rows = mel_catv_row_answer($rid, mel_catv_lang());
    // The id and the header asked, of an unknown row too.
    $rows[0][PluginRow::id] = $id;
    $rows[0][PluginRow::header_id] = MEL_CATV_HEADER . $rid;
    $left = MEL_CATV_ROW_S - (microtime(true) - $start);
    if (MelCat::$manifest_due && $left >= 1 && !mel_catv_is_down())
    {
        MelCat::$manifest_due = false;
        mel_catv_manifest_refresh(min(intval($left), MEL_CATV_REFRESH_S));
    }
    mel_pg_rows_answered();
    return array('rows' => $rows);
}

/**
 * @param int $w
 * @param int $h
 * @param int $dx
 * @param int $iw
 * @param int $ih
 * @param int $idy
 * @param int $cdy
 * @param string $color
 * @return array<string, int|string>
 */
function mel_catv_item_vars($w, $h, $dx, $iw, $ih, $idy, $cdy, $color)
{
    return array(
        PluginRegularItemVariableParams::width => $w,
        PluginRegularItemVariableParams::height => $h,
        PluginRegularItemVariableParams::dx => $dx,
        PluginRegularItemVariableParams::icon_width => $iw,
        PluginRegularItemVariableParams::icon_height => $ih,
        PluginRegularItemVariableParams::icon_dy => $idy,
        PluginRegularItemVariableParams::caption_dy => $cdy,
        PluginRegularItemVariableParams::caption_color => $color,
        PluginRegularItemVariableParams::caption_font_size => 28);
}

// The template "common" of the tiles; $m: the margins of the selection, left, top, right, bottom.
/**
 * @param array{int, int, int, int} $m
 * @return array<string, mixed>
 */
function mel_catv_item_tpl($m)
{
    return array(
        PluginRegularItemParams::def => mel_catv_item_vars(200, 302, 0, 190, 285, 7, 10, '#FFAFAFA0'),
        PluginRegularItemParams::sel => mel_catv_item_vars(200, 302, 0, 200, 300, 0, 10, '#FFFFFFE0'),
        PluginRegularItemParams::inactive => mel_catv_item_vars(200, 300, 5, 190, 285, 0, 0, '#00000000'),
        PluginRegularItemParams::loading_url => MEL_CATV_ICONS . 'loading_movie_poster.png',
        PluginRegularItemParams::load_failed_url => MEL_CATV_ICONS . 'no_movie_poster.png',
        PluginRegularItemParams::caption_max_num_lines => 3,
        PluginRegularItemParams::caption_line_spacing => 3,
        PluginRegularItemParams::sticker_text_size => MEL_CATV_RATE_PX,
        PluginRegularItemParams::sel_margins => array(
            PluginMargins::left => $m[0], PluginMargins::top => $m[1],
            PluginMargins::right => $m[2], PluginMargins::bottom => $m[3]));
}

// The actions of the rows. $from: 'results:<id of the search>' on the results of a search, a
// new search replaces them; else the address of the screen, for its "did not load" tile.
/**
 * @param string $from
 * @return array<string, MelAction>
 */
function mel_catv_actions($from)
{
    $p = array('from' => $from);
    $info = mel_input('cat_info');
    // As the sample proven on the device: no timeout and no dialog on a focus move.
    $info[GuiAction::data] = null;
    return array(
        GUI_EVENT_KEY_ENTER => mel_input('cat_enter', $p, Mel::$settings['cat_card'] ? MEL_CARD_DELAY : MEL_DELAY_INPUT),
        GUI_EVENT_KEY_POPUP_MENU => mel_input('cat_menu', $p),
        GUI_EVENT_KEY_SEARCH => mel_input('cat_search', $p),
        GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE => $info) + mel_egg_acts();
}

// The focusable_def of a part of the strip. $m: sel_margins, left, top, right, bottom; the frame of
// the shell is a 5 px line across the edges of the part grown by them, with a glow of ~6 px.
/**
 * @param array{int, int, int, int} $m
 * @return array<string, mixed>
 */
function mel_catv_strip_focusable($m)
{
    return array(
        GCompFocusableDef::sel_margins => array(
            PluginMargins::left => $m[0], PluginMargins::top => $m[1], PluginMargins::right => $m[2], PluginMargins::bottom => $m[3]),
        GCompFocusableDef::background_url => null);
}

// The icons of the strip in its order, id => (key of the caption, PNG, alpha), for the strip and its
// items of POP UP (mel_catv_menu). Their glyphs 48x48 at 8, 8 of the PNG; grey #AAAAAA as the
// native ones: the magnifier is white at most (alpha 170), the clock #F8F8F8 (alpha 175), the gear
// and the heart #BBBBBB (alpha 232).
/**
 * @return array<string, array{0: string, 1: string, 2: int}>
 */
function mel_catv_strip_icons()
{
    return array(
        'tab_continue' => array('cp_row_continue', MEL_CATV_ICON_CONT, 175),
        'tab_mylist' => array('ml_title', MEL_CATV_ICON_MYLIST, 232),
        'tab_search' => array('cat_search', MEL_CATV_ICON_SEARCH, 170),
        'tab_setup' => array('setup_menu', MEL_CATV_ICON_SETUP, 232));
}

// The strip of the main screen of the tab $tab: its title row, a focusable row of GComps and a
// gap. With the panel of titles on, a row without a title before the rows with one hangs the
// screen, so the strip has a title and a group as a row of posters. Its parts: the tabs as text,
// the open one underlined, and the icons of the shell, all focusable; the margins of the frame
// leave ~15 px at the sides of a word or a glyph and 7-9 px above and below.
/**
 * @param string $tab
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_catv_strip_rows($tab, $lang)
{
    $defs = array();
    $items = array();
    // A label of 38 px is 38 high, the caps from 6 down to the baseline at 33: the baseline at
    // 83, ~150 of the screen as the native menu. "Series" 46 px right of the box of "Movies",
    // the words ~50 apart as in the native menu, in any language.
    foreach (array('movie' => 'cat_tab_movies', 'series' => 'cat_tab_series') as $type => $key)
    {
        $id = "tab_$type";
        $caption = mel_tr($lang, $key);
        $geom = $type === 'movie' ? mel_gc_at(-1, -1, 128, 50) : mel_gc_geom(-1, -1, mel_gc_next_right(46));
        $def = mel_gc_ttf($id, $geom, $caption, MEL_CATV_TAB_PX, $type === $tab ? MEL_CATV_TITLE_COLOR : MEL_CATV_TEXT_COLOR);
        $def[GComponentDef::focusable_def] = mel_catv_strip_focusable(array(16, 4, 16, 8));
        $defs[] = $def;
        $items[] = array(GCompItemDef::type => GCOMP_ITEM_REGULAR, GCompItemDef::id => $id,
            GCompItemDef::caption => $caption, GCompItemDef::icon_url => null);
    }
    // The line under the open tab: as wide as its word, 3 px under the baseline, inside the frame.
    $defs[] = mel_gc_rect(mel_gc_geom(0, 4, mel_gc_align(0, 36, true, false, 0, 0, 0, 0, "tab_$tab")), MEL_CATV_UNDERLINE);
    // The icons (mel_catv_strip_icons) 75 apart as the native ones, from the right: the gear
    // ends at 1660, its frame at 1670.
    $icons = mel_catv_strip_icons();
    $i = 0;
    $n = count($icons);
    foreach ($icons as $id => $t)
    {
        $defs[] = array(
            GComponentDef::id => $id,
            GComponentDef::geom_def => mel_gc_at(64, 64, 1596 - 75 * ($n - 1 - $i++), 34),
            GComponentDef::focusable_def => mel_catv_strip_focusable(array(10, 2, 10, 3)),
            GComponentDef::kind => GCOMPONENT_IMAGE,
            GComponentDef::specific_def => array(
                GCompImageDef::url => MEL_CATV_ICONS . $t[1],
                GCompImageDef::keep_aspect_ratio => true,
                GCompImageDef::upscale_enabled => false,
                GCompImageDef::alpha => $t[2]));
        $items[] = array(GCompItemDef::type => GCOMP_ITEM_REGULAR, GCompItemDef::id => $id,
            GCompItemDef::caption => mel_tr($lang, $t[0]), GCompItemDef::icon_url => MEL_CATV_ICONS . $t[1]);
    }
    $title = mel_tr($lang, 'cat_tab_menu');
    return array(
        mel_catv_title_row(MEL_CATV_STRIP, $title),
        array(
            PluginRow::type => PLUGIN_ROW_TYPE_GCOMPS,
            PluginRow::id => MEL_CATV_STRIP,
            PluginRow::title => $title,
            PluginRow::group_id => 'row:' . MEL_CATV_STRIP,
            PluginRow::ui_level => 0,
            PluginRow::height => 90,
            PluginRow::inactive_height => 90,
            PluginRow::focusable => true,
            PluginRow::data => array(
                PluginGCompsRow::defs => $defs,
                PluginGCompsRow::ui_state => array(
                    GCompUiStateDef::items => $items,
                    GCompUiStateDef::title => null,
                    GCompUiStateDef::bg_url => null,
                    GCompUiStateDef::poster_url => null,
                    GCompUiStateDef::view_params => null),
                PluginGCompsRow::width => 1920)),
        array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 20, PluginRow::inactive_height => 50));
}

// The focus on the part $id of the strip, as the shell takes it after a switch of the tab.
/**
 * @param string $id
 * @return string
 */
function mel_catv_strip_sel($id)
{
    return "1\n0\n" . MEL_CATV_STRIP_HEADER . "\n" . MEL_CATV_STRIP . "\n$id\npri:1\n$id\n1\n1\n";
}

// The screen of the rows $defs. The key set of RowsFactory::pane r25, nulls too;
// row_dup_map: a literal, the r24 API has no such constant. A main screen ($from): the strip
// first, above min_row_index_for_y2 (laid out from the top, the rows right under it while it
// has the focus; under it the rows as before, the focused one on screen_y2).
/**
 * @param list<array<string, mixed>> $defs
 * @param mixed $sel_state
 * @param string $from
 * @param bool $tick
 * @return array<string, mixed>
 */
function mel_catv_rows_view($defs, $sel_state, $from, $tick = false)
{
    $rows = array(array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 10, PluginRow::inactive_height => 10));
    $tab = mel_catv_tab($from);
    if ($tab !== '')
        $rows = array_merge($rows, mel_catv_strip_rows($tab, mel_catv_ui_lang()));
    // The first title of the rows.
    $y2_index = $tab !== '' ? count($rows) : 0;
    $groups = 0;
    foreach ($defs as $def)
    {
        $rows[] = $def;
        if ($def[PluginRow::type] === PLUGIN_ROW_TYPE_TITLE)
            $groups++;
    }
    $rows[] = array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 80, PluginRow::inactive_height => 80);
    // The strip of the row titles: on the main screen and in a search of two groups and more (device r25).
    $r = mel_catv_pane_r($tab !== '' || $groups > 1);
    $pane = array(
        PluginRowsPane::rows => $rows,
        'row_dup_map' => null,
        PluginRowsPane::bg => null,
        PluginRowsPane::initial_focus_row_id => null,
        PluginRowsPane::focus => array(
            GCompFocusDef::type => GCOMP_FOCUS_DEFAULT_CUT_IMAGE,
            GCompFocusDef::type2 => GCOMP_FOCUS_DEFAULT_RECT),
        PluginRowsPane::header_enabled => true,
        PluginRowsPane::single_list_navigation => false,
        PluginRowsPane::initial_focus_header => -1,
        PluginRowsPane::initial_focus_item_id => null,
        PluginRowsPane::horizontal_focus_freedom_factor => 0.89,
        PluginRowsPane::horizontal_focus_end_min_offset => 130,
        PluginRowsPane::vertical_focus_freedom_factor => 0.0,
        PluginRowsPane::vertical_focus_gravity => -0.5,
        PluginRowsPane::vertical_focus_end_min_offset => 50,
        PluginRowsPane::screen_r => array('w' => 1920, 'h' => 1080, 'x' => 0, 'y' => 0),
        PluginRowsPane::screen_y2 => 600,
        PluginRowsPane::min_row_index_for_y2 => $y2_index,
        PluginRowsPane::info_r => $r['info_r'],
        PluginRowsPane::vod_r => $r['vod_r'],
        PluginRowsPane::regular_item_params_templates => array('common' => mel_catv_item_tpl(array(3, 3, 3, 4))));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
        PluginFolderView::data => array(
            PluginRowsFolderView::pane => $pane,
            PluginRowsFolderView::sel_state => is_string($sel_state) && $sel_state !== '' ? $sel_state : null,
            PluginRowsFolderView::actions => mel_catv_actions($from) + ($tick ? array(GUI_EVENT_TIMER => mel_catv_tick($from)) : array()),
            PluginRowsFolderView::timer => $tick ? array(GuiTimerDef::delay_ms => MEL_PG_TICK_MS) : null));
}

// --- Screens.

// The tab of a screen of the rows (mel_catv_main_url): 'movie' of "catalog" (the entry),
// "catalog:<n>" and "cat_tab:movie"; the type of "cat_tab:<type>", "cat_tab:<type>:<n>". '': not
// such a screen. "catalog" and "cat_tab:movie" are the same screen, only the focus at the opening
// differs: on the panel, on the tab.
/**
 * @param string $media_url
 * @return string
 */
function mel_catv_tab($media_url)
{
    if ($media_url === 'catalog' || strpos($media_url, 'catalog:') === 0)
        return 'movie';
    return preg_match('/^cat_tab:(movie|series)(?::|$)/', $media_url, $m) ? $m[1] : '';
}

// The address of the main screen of the tab $tab. No $n: "cat_tab:<type>", a switch of the tab,
// the focus on it (mel_catv_main_view). $n, a new id of a screen (cat_retry, mel_setup_refresh):
// "catalog:<n>" of the movies, "cat_tab:series:<n>", the focus where the shell puts it.
/**
 * @param string $tab
 * @param string $n
 * @return string
 */
function mel_catv_main_url($tab, $n = '')
{
    if ($n === '')
        return "cat_tab:$tab";
    return $tab === 'series' ? "cat_tab:series:$n" : "catalog:$n";
}

/**
 * @param string $media_url
 * @return bool
 */
function mel_catv_is_main($media_url)
{
    return mel_catv_tab($media_url) !== '';
}

// The address of the screen of $from of the actions of a screen of rows (mel_catv_actions): of the results of a search
// "results:<id>" "cat_search:<id>", of an EPF of "TV" none (''), else $from itself.
/**
 * @param string $from
 * @return string
 */
function mel_catv_screen_of($from)
{
    if (strpos($from, 'results:') === 0)
        return 'cat_search:' . substr($from, strlen('results:'));
    return mel_tv_is_from($from) ? '' : $from;
}

/**
 * @param string $media_url
 * @return bool
 */
function mel_catv_is_screen($media_url)
{
    return mel_catv_is_main($media_url) || strpos($media_url, 'cat_grid:') === 0 || strpos($media_url, 'cat_search:') === 0 ||
        mel_mlv_is_screen($media_url);
}

/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_catv_folder_view($media_url, $sel_state)
{
    mel_catv_logo_of($media_url, true);
    if (strpos($media_url, 'cat_grid:') === 0)
        return mel_catv_all_ok() ? mel_catv_all_view($media_url, $sel_state) : mel_catv_grid_view($media_url);
    if (strpos($media_url, 'cat_search:') === 0)
        return mel_catv_results_view(substr($media_url, strlen('cat_search:')), $sel_state);
    if (mel_mlv_is_screen($media_url))
        return mel_mlv_view($media_url, $sel_state);
    return mel_catv_main_view($media_url, $sel_state);
}

// A message instead of an empty screen $from, and a way to try again.
/**
 * @param string $key
 * @param string $lang
 * @param string $from
 * @return array<string, mixed>
 */
function mel_catv_message_view($key, $lang, $from)
{
    mel_log("catalog: screen: $key");
    return array(
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::data => array(
            PluginControlsFolderView::defs => array(
                mel_setup_label(null, mel_tr($lang, $key)),
                mel_setup_button('retry', mel_tr($lang, 'cat_retry'), mel_input('cat_retry', array('from' => $from)))),
            PluginControlsFolderView::initial_sel_ndx => -1));
}

// The manifest: a copy within MEL_CAT_MANIFEST_FRESH as it is; no copy: one request that holds the screen.
// r25: the first row now (mel_catv_row_now), the others: a fresh copy ready, an old one
// flagged, none: stubs; get_folder_rows asks for the flagged ones. r24: the rows in MEL_CATV_OPEN_S.
// Only the rows of the tab of $media_url, in the order of the user.
// An older copy is refreshed, once in MEL_CAT_MANIFEST_FRESH even on a failure (mel_catv_manifest_tried): r24 first
// here (a failure: the copy); r25 in get_folder_rows, or here at the end with none flagged.
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_catv_main_view($media_url, $sel_state)
{
    $start = microtime(true);
    MelCat::$on_screen = array();
    MelCat::$mains = array_values(array_diff(MelCat::$mains, array($media_url)));
    MelCat::$mains[] = $media_url;
    MelCat::$mains = array_slice(MelCat::$mains, -MEL_CATV_MAINS);
    $lang = mel_catv_lang();
    MelCat::$lang = $lang;
    $tab = mel_catv_tab($media_url);
    // Built again by a tick of its own that got meta (mel_pg_anew_take): no opening - its ticks go on
    // (mel_pg_tick_set).
    $anew = mel_pg_anew_take($media_url);
    // A switch of the tab (mel_catv_strip_enter): the shell brings no sel_state, the focus goes
    // to the tab pressed. Else none of ours: at an opening the focus is on the panel.
    if ((!is_string($sel_state) || $sel_state === '') && $media_url === mel_catv_main_url($tab))
        $sel_state = mel_catv_strip_sel("tab_$tab");
    $loading = mel_catv_loading_flag();
    $copy = mel_cat_manifest_copy();
    $rows = $copy['rows'];
    // No request of the manifest due: a recent copy, a recent try, or asked in this opening. MEL_CATV_OPEN_S
    // counts from $rows_t0: after the first manifest (no copy), else from the start, the refresh within it.
    $manifest_done = mel_cat_manifest_recent($copy) || mel_catv_manifest_tried();
    $rows_t0 = $start;
    if (!$rows)
    {
        $new = mel_cat_manifest_fetch(MEL_CATV_MANIFEST_S);
        $rows = $new['rows'];
        $manifest_done = true;
        $rows_t0 = microtime(true);
        if (!$rows)
            return mel_catv_message_view('cat_unavailable', $lang, $media_url);
    }
    else if (!$loading && !$manifest_done && !mel_catv_is_down())
    {
        $new = mel_catv_manifest_refresh(MEL_CATV_REFRESH_S);
        $manifest_done = true;
        if ($new['rows'])
            $rows = $new['rows'];
    }
    $rows = mel_cat_user_rows($rows, Mel::$settings['cat_rows'], $tab);
    if (!$rows)
    {
        mel_log("catalog: screen: $tab: no rows");
        return mel_catv_rows_view(mel_catv_tab_empty($tab, $lang, false), $sel_state, $media_url);
    }
    $defs = array();
    $asked = false;
    // The rows shown: mel_catv_shown_rows, on r25 now, on r24 after the opening got their tiles.
    $shown = $loading ? mel_catv_shown_rows($rows, $loading) : $rows;
    // r25: all the rows of the tab came empty within the hour: the strip stays, not "did not load".
    if (!$shown)
    {
        mel_log("catalog: screen: $tab: all " . count($rows) . ' rows empty');
        return mel_catv_rows_view(mel_catv_tab_empty($tab, $lang, true), $sel_state, $media_url);
    }
    if (!$anew)
        mel_pg_screen_start();
    // The row "Continue" first, of the history, then "Мой список" of the tab: ready at once, the meta of their tiles
    // first (mel_cpm_build: kept, of the rows in memory or on the disk, else asked in MEL_CPM_BUILD_S; r24 in what is
    // left of MEL_CATV_OPEN_S but MEL_CATV_OPEN_ROW_S, under MEL_CPM_MIN_S none asked). Their tiles with no meta still
    // are for the lazy meta of this screen (mel_cpm_step), those of "Continue" first. Built again by the tick of a
    // screen of Fav over it (mel_mlv_under): none asked, the ticks of that screen ask; its own timer still (after Back the
    // lazy step goes on), its ticks wait for those of that screen (mel_pg_tick, MEL_PG_FAV_QUIET_S). Built again by its
    // own tick: none asked either, its next ticks ask.
    $under = mel_mlv_under($media_url);
    mel_cpm_build($tab, $under !== '' || $anew ? 0.0 : ($loading ? MEL_CPM_BUILD_S :
        max(0.0, min(MEL_CPM_BUILD_S, MEL_CATV_OPEN_S - (microtime(true) - $rows_t0) - MEL_CATV_OPEN_ROW_S))));
    $defs = array_merge(mel_cp_row_defs($tab, $lang, $cp_tiles, $miss), mel_ml_row_defs($tab, $lang, $ml_tiles, $ml_miss));
    MelCpm::$miss = $miss;
    MelCpm::$ml = $ml_miss;
    $now = microtime(true);
    $net = mel_catv_open($shown, $loading ? 0 : min(MEL_CATV_OPEN_S - ($now - $rows_t0), MEL_CATV_OPEN_MAX_S - ($now - $start)));
    if (!$loading)
        $shown = mel_catv_shown_rows($shown, $loading);
    foreach ($shown as $i => $row)
    {
        $rid = mel_catv_rid($row);
        // A flagged first row replaced under the focus on the headers covers the rows under it (device, 0.50.0). After
        // the row "Continue" the first row of the server is flagged as the others (the focus is not on it; O3).
        if ($loading && $i === 0 && count($defs) === 0)
        {
            $limit = max(MEL_CATV_MIN_S, min(MEL_CATV_ROW_S, MEL_CATV_OPEN_MAX_S - (microtime(true) - $start)));
            $defs = array_merge($defs, mel_catv_row_now($row, $lang, $limit, false));
        }
        else if (isset(MelCat::$lists[$rid]) && MelCat::$lists[$rid]['tiles'])
        {
            $flag = mel_cat_state_fresh(MelCat::$lists[$rid]['st']) ? 0 : $loading;
            if ($flag)
            {
                MelCat::$on_screen[$rid] = MelCat::$lists[$rid]['tiles'];
            }
            $defs = array_merge($defs, mel_catv_row_defs(MelCat::$lists[$rid], $lang, null, $flag));
            $asked = $asked || $flag;
        }
        else if ($loading)
        {
            $defs = array_merge($defs, mel_catv_row_note($row, $lang, ''));
            $asked = true;
        }
    }
    if ($loading)
    {
        MelCat::$manifest_due = $asked && !$manifest_done;
        $left = MEL_CATV_OPEN_S - (microtime(true) - $rows_t0);
        if (!$asked && !$manifest_done && $left >= 1 && !mel_catv_is_down())
            mel_catv_manifest_refresh(intval($left));
    }
    // The lazy pairs: r24 on the time left of the opening; r25 at the tick of the timer of the screen (mel_pg_tick_set).
    if (!$loading)
        mel_pg_screen_built(MEL_CATV_OPEN_S - (microtime(true) - $rows_t0), $net, $tab);
    mel_log(sprintf('catalog: screen: %s: %d rows of %d, %.0f ms%s', $tab, count($defs) / 2, count($rows), (microtime(true) - $start) * 1000,
        $under !== '' ? ", under $under: no requests of the meta" : ''));
    if (MelCat::$inv_t > 0)
    {
        $since = microtime(true) - MelCat::$inv_t;
        if ($since >= 0 && $since <= MEL_CATV_INV_LOG_S)
            mel_log(sprintf('catalog: screen: %s: anew after the player, %.0f ms since its finish', $media_url, $since * 1000));
        MelCat::$inv_t = 0.0;
    }
    if (!$defs)
        return mel_catv_message_view('cat_unavailable', $lang, $media_url);
    return mel_catv_rows_view($defs, $sel_state, $media_url, $loading && mel_pg_tick_set($media_url, $anew));
}

// The rows of the tab $rows (mel_cat_user_rows) the main screen shows (mel_catv_main_view; the link of a row,
// mel_dl_row): r25 ($loading) all but those come empty within the hour (mel_catv_is_empty), before the opening; r24
// those with tiles in memory, after the opening (mel_catv_open) got them. A new filter goes here, and to the rows of a
// tab of the copies alone as well (mel_catv_tab_copy_defs).
/**
 * @param list<MelCatRow> $rows
 * @param int $loading
 * @return list<MelCatRow>
 */
function mel_catv_shown_rows($rows, $loading)
{
    $out = array();
    foreach ($rows as $row)
    {
        $rid = mel_catv_rid($row);
        if ($loading ? !mel_catv_is_empty($rid) : isset(MelCat::$lists[$rid]) && MelCat::$lists[$rid]['tiles'])
            $out[] = $row;
    }
    return $out;
}

// The rows of the tab $tab from the copies alone, for a file the shell shows without PHP (the tab
// "TV"): the rows of the user (mel_cat_user_rows) of the saved manifest, each by its saved first page
// of any age; a row without one is left out. No request, no flag, no mark "saved", no strip; the tiles
// and the plates by the mode of the screen now (mel_catv_logo_of), the caller sets it. -> the defs of
// the rows (mel_catv_row_defs, for mel_catv_rows_view), their rids and their listings by rid.
/**
 * @param string $tab
 * @param string $lang
 * @return array{defs: list<array<string, mixed>>, rids: list<string>, lists: array<string, MelCatList>}
 */
function mel_catv_tab_copy_defs($tab, $lang)
{
    $copy = mel_cat_manifest_copy();
    $defs = array();
    $rids = array();
    $lists = array();
    foreach (mel_cat_user_rows($copy['rows'], Mel::$settings['cat_rows'], $tab) as $row)
    {
        $c = mel_cat_page_cached($row, 0);
        if (!$c)
            continue;
        $rid = mel_catv_rid($row);
        $lists[$rid] = mel_catv_list_copy($row, $c);
        $defs = array_merge($defs, mel_catv_row_defs($lists[$rid], $lang));
        $rids[] = $rid;
    }
    return array('defs' => $defs, 'rids' => $rids, 'lists' => $lists);
}

// The row of the tab $tab without a row to show (mel_catv_tab_empty; its header: mel_catv_menu).
/**
 * @param string $tab
 * @param string $lang
 * @return MelCatRow
 */
function mel_catv_tab_empty_row($tab, $lang)
{
    return array('key' => "tab/$tab", 'type' => $tab, 'id' => 'tab', 'name' => mel_tr($lang, $tab === 'movie' ? 'cat_movies' : 'cat_series'),
        'genre' => '', 'home' => false, 'group' => '');
}

// The tab $tab without a row to show: under the strip one tile that says why. $retry: its rows
// came empty (r25, hidden for an hour), "retry:<rid>", ENTER is "Retry"; else the manifest has
// none of its type to show (none chosen and no start row of it, mel_cat_user_rows), "empty:<rid>",
// ENTER: nothing.
/**
 * @param string $tab
 * @param string $lang
 * @param bool $retry
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function mel_catv_tab_empty($tab, $lang, $retry)
{
    $row = mel_catv_tab_empty_row($tab, $lang);
    $rid = mel_catv_rid($row);
    $list = mel_catv_list(mel_cat_row_state($row), array(), false);
    $item = $retry ? mel_catv_item("retry:$rid", mel_tr($lang, 'cat_tab_retry'), MEL_CATV_ICONS . 'no_movie_poster.png') :
        mel_catv_item("empty:$rid", mel_tr($lang, 'cat_tab_empty'), MEL_CATV_ICONS . 'no_movie_poster.png');
    return mel_catv_row_defs($list, $lang, array($item));
}

/**
 * @param string $id
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_catv_results_view($id, $sel_state)
{
    $lang = mel_catv_lang();
    MelCat::$lang = $lang;
    $defs = array();
    foreach (isset(MelCat::$searches[$id]) ? MelCat::$searches[$id] : array() as $rid)
    {
        if (isset(MelCat::$lists[$rid]))
            $defs = array_merge($defs, mel_catv_row_defs(MelCat::$lists[$rid], $lang));
    }
    if (!$defs)
    {
        mel_log('catalog: search screen outdated');
        return mel_lines_view(array(mel_expired_item()));
    }
    return mel_catv_rows_view($defs, $sel_state, "results:$id");
}

/**
 * @param string $media_url
 * @param string $caption
 * @return MelAction
 */
function mel_catv_open_act($media_url, $caption)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => $media_url,
            PluginOpenFolderActionData::caption => $caption));
}

// --- A row in full: tiles of the listing in memory, then the next pages.

// The English name of a country of a tile as the line shows it: in Russian only by the
// list, an unknown one goes; in English as it is, a few long names short.
/**
 * @param string $c
 * @param string $lang
 * @return string
 */
function mel_catv_country($c, $lang)
{
    static $en = array('united states of america' => 'USA', 'united states' => 'USA', 'united kingdom' => 'UK',
        'russian federation' => 'Russia', 'united arab emirates' => 'UAE');
    static $ru = array('united states of america' => 'США', 'united states' => 'США', 'united kingdom' => 'Великобритания',
        'uk' => 'Великобритания', 'great britain' => 'Великобритания', 'france' => 'Франция', 'germany' => 'Германия',
        'spain' => 'Испания', 'italy' => 'Италия', 'japan' => 'Япония', 'south korea' => 'Южная Корея', 'china' => 'Китай',
        'india' => 'Индия', 'canada' => 'Канада', 'australia' => 'Австралия', 'new zealand' => 'Новая Зеландия',
        'russia' => 'Россия', 'russian federation' => 'Россия', 'soviet union' => 'СССР', 'brazil' => 'Бразилия',
        'mexico' => 'Мексика', 'belgium' => 'Бельгия', 'netherlands' => 'Нидерланды', 'sweden' => 'Швеция',
        'norway' => 'Норвегия', 'denmark' => 'Дания', 'finland' => 'Финляндия', 'ireland' => 'Ирландия', 'poland' => 'Польша',
        'portugal' => 'Португалия', 'austria' => 'Австрия', 'switzerland' => 'Швейцария', 'czech republic' => 'Чехия',
        'czechia' => 'Чехия', 'hungary' => 'Венгрия', 'greece' => 'Греция', 'turkey' => 'Турция', 'israel' => 'Израиль',
        'argentina' => 'Аргентина', 'chile' => 'Чили', 'colombia' => 'Колумбия', 'hong kong' => 'Гонконг',
        'taiwan' => 'Тайвань', 'thailand' => 'Таиланд', 'indonesia' => 'Индонезия', 'philippines' => 'Филиппины',
        'south africa' => 'ЮАР', 'iceland' => 'Исландия', 'ukraine' => 'Украина', 'kazakhstan' => 'Казахстан',
        'belarus' => 'Беларусь', 'romania' => 'Румыния', 'bulgaria' => 'Болгария', 'serbia' => 'Сербия',
        'croatia' => 'Хорватия', 'estonia' => 'Эстония', 'latvia' => 'Латвия', 'lithuania' => 'Литва', 'georgia' => 'Грузия',
        'luxembourg' => 'Люксембург', 'iran' => 'Иран', 'egypt' => 'Египет', 'vietnam' => 'Вьетнам',
        'malaysia' => 'Малайзия', 'singapore' => 'Сингапур', 'united arab emirates' => 'ОАЭ', 'morocco' => 'Марокко',
        'peru' => 'Перу', 'cuba' => 'Куба');
    $k = strtolower($c);
    if ($lang === 'ru')
        return isset($ru[$k]) ? $ru[$k] : '';
    return isset($en[$k]) ? $en[$k] : $c;
}

// "1h38min" of a tile -> "1ч 38м" or "1h 38m", "30min" -> "30м", "120min" -> "2ч"; '' for none.
/**
 * @param string $r
 * @param string $lang
 * @return string
 */
function mel_catv_runtime($r, $lang)
{
    $min = mel_cat_runtime_min($r);
    $parts = array();
    if ($min >= 60)
        $parts[] = str_replace('{n}', strval(intval($min / 60)), mel_tr($lang, 'cat_len_h'));
    if ($min % 60 > 0)
        $parts[] = str_replace('{n}', strval($min % 60), mel_tr($lang, 'cat_len_m'));
    return implode(' ', $parts);
}

// The genres of a tile as the lines show them: the compound genres of TMDB series short (the
// server names them in Russian in any interface), with a capital, no repeat in another case,
// the first 2.
/**
 * @param MelCatTile $t
 * @return list<string>
 */
function mel_catv_genres($t)
{
    static $short = array('боевик и приключения' => 'боевик', 'нф и фэнтези' => 'фантастика', 'война и политика' => 'военный');
    $out = array();
    foreach ($t['genres'] as $g)
    {
        $k = mb_strtolower($g, 'UTF-8');
        if (isset($short[$k]))
            $g = $short[$k];
        $g = mb_strtoupper(mb_substr($g, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($g, 1, mb_strlen($g, 'UTF-8'), 'UTF-8');
        $k = mb_strtolower($g, 'UTF-8');
        if (!isset($out[$k]))
            $out[$k] = $g;
    }
    return array_slice(array_values($out), 0, 2);
}

// The parts of the facts of a tile, of the panel and the grid alike: the ratings as array(kind,
// name, value) ("kp", "КП", "7.7"; "imdb", "IMDb", "7.3"), KP first; the rest: "Сериал" for a series, year, runtime
// (of an episode for a series: no word for it); the age ('' for none). Words of the server only,
// the empty ones left out.
/**
 * @param MelCatTile $t
 * @param string $lang
 * @return array{rates: list<array{string, string, string}>, rest: list<string>, age: string}
 */
function mel_catv_fact_parts($t, $lang)
{
    $rates = array();
    if ($t['kpRating'] !== '')
        $rates[] = array('kp', mel_tr($lang, 'cat_rate_kp'), $t['kpRating']);
    $rating = (float) $t['imdbRating'];
    if ($rating > 0)
        $rates[] = array('imdb', mel_tr($lang, 'cat_rate_imdb'), sprintf('%.1F', $rating));
    $rest = array_filter(array($t['type'] === 'series' ? mel_tr($lang, 'cat_kind_series') : '', $t['year'],
        mel_catv_runtime($t['runtime'], $lang)), 'strlen');
    return array('rates' => $rates, 'rest' => array_values($rest), 'age' => $t['age']);
}

// The facts as text, a part a string: "КП 7.7", "IMDb 7.3", "2020", "1 ч 53 мин", "18+".
/**
 * @param array{rates: list<array{string, string, string}>, rest: list<string>, age: string} $parts
 * @return list<string>
 */
function mel_catv_fact_texts($parts)
{
    $out = array();
    foreach ($parts['rates'] as $r)
        $out[] = "{$r[1]} {$r[2]}";
    return array_values(array_filter(array_merge($out, $parts['rest'], array($parts['age'])), 'strlen'));
}

// The line of facts of the panel as its parts in order, array(kind, text, value): a plate of a
// rating ("kp" or "imdb", its name, its number), the rest as one text ("text": "Сериал · 2008 ·
// 56 мин", "·" between), the age in a plate ("age", "18+") if a Russian mark, else at the end of
// the text. No "·" next to a plate.
/**
 * @param MelCatTile $t
 * @param string $lang
 * @return list<array{string, string, string}>
 */
function mel_catv_facts($t, $lang)
{
    return mel_catv_facts_of(mel_catv_fact_parts($t, $lang));
}

// The line of facts of mel_catv_facts of the parts of mel_catv_fact_parts (the own card adds its kind to the rest).
/**
 * @param array{rates: list<array{string, string, string}>, rest: list<string>, age: string} $p
 * @return list<array{string, string, string}>
 */
function mel_catv_facts_of($p)
{
    static $ages = array('0+', '6+', '12+', '16+', '18+');
    $out = array();
    foreach ($p['rates'] as $r)
        $out[] = $r;
    $plate = in_array($p['age'], $ages, true);
    $rest = $p['rest'];
    if ($p['age'] !== '' && !$plate)
        $rest[] = $p['age'];
    if ($rest)
        $out[] = array('text', implode(' · ', $rest), '');
    if ($plate)
        $out[] = array('age', $p['age'], '');
    return $out;
}

// The genres and the country of a tile, of the panel and the grid alike: the genres of
// mel_catv_genres, a second one in Cyrillic small ("Комедия, криминал"; "Fantasy, Comedy" as it
// is), the country of the interface; cleaned.
/**
 * @param MelCatTile $t
 * @param string $lang
 * @return array{list<string>, string}
 */
function mel_catv_genre_parts($t, $lang)
{
    $g = array_map('mel_clean', mel_catv_genres($t));
    foreach ($g as $i => $x)
    {
        if ($i && preg_match('/^[\x{0400}-\x{04FF}]/u', $x))
            $g[$i] = mb_strtolower($x, 'UTF-8');
    }
    return array($g, mel_clean(mel_catv_country($t['country'], $lang)));
}

// The second line of data of the panel: the genres, then the country, fitted to TEXT_W. What
// does not fit at DATA_MIN_PX goes: the countries but the first, then all of them, then the
// second genre; mel_catv_fit cuts the rest. $countries: those of the own card (cleaned), else
// the one of the tile.
/**
 * @param MelCatTile $t
 * @param string $lang
 * @param list<string>|null $countries
 * @return string
 */
function mel_catv_genre_line($t, $lang, $countries = null)
{
    list($g, $c) = mel_catv_genre_parts($t, $lang);
    $cs = $countries !== null ? $countries : ($c !== '' ? array($c) : array());
    $line = '';
    foreach (array(array($g, $cs), array($g, array_slice($cs, 0, 1)), array($g, array()), array(array_slice($g, 0, 1), array())) as $v)
    {
        $line = implode(' · ', array_filter(array(implode(', ', $v[0]), implode(', ', $v[1])), 'strlen'));
        if (mel_gc_w($line, MEL_CATV_DATA_MIN_PX) <= MEL_CATV_TEXT_W)
            break;
    }
    return $line;
}

// The cast of the panel: the names as fit TEXT_W at 30 px, the last ones dropped whole, one at
// least; one too long is cut with "…".
/**
 * @param MelCatTile $t
 * @return string
 */
function mel_catv_cast_line($t)
{
    $names = array_map('mel_clean', $t['cast']);
    while (count($names) > 1 && mel_gc_w(implode(', ', $names), MEL_CATV_LINE_PX) > MEL_CATV_TEXT_W)
        array_pop($names);
    return mel_gc_cut_w(implode(', ', $names), MEL_CATV_LINE_PX, MEL_CATV_TEXT_W);
}

// An item of the grid: the lines of the panel in one, "·" between the parts.
/**
 * @param string $rid
 * @param MelCatTile $t
 * @param string $lang
 * @return array<string, mixed>
 */
function mel_catv_grid_item($rid, $t, $lang)
{
    $icon = mel_catv_poster($t);
    $g = mel_catv_genre_parts($t, $lang);
    $parts = array_merge(mel_catv_fact_texts(mel_catv_fact_parts($t, $lang)),
        array(implode(', ', $g[0]), $g[1], mel_error_cut($t['description'], MEL_CATV_DESC_CHARS)));
    $text = implode(' · ', array_filter($parts, 'strlen'));
    return array(
        PluginRegularFolderItem::media_url => mel_catv_tile_id("$rid:{$t['id']}"),
        PluginRegularFolderItem::caption => $t['name'],
        PluginRegularFolderItem::view_item_params => array(
            ViewItemParams::icon_path => $icon,
            ViewItemParams::item_detailed_icon_path => $icon,
            ViewItemParams::item_detailed_info => mel_clean($t['name']) . '|' . mel_clean($text)));
}

// The row of a rid from the saved manifest, or null.
/**
 * @param string $rid
 * @return MelCatRow|null
 */
function mel_catv_row_by_rid($rid)
{
    $copy = mel_cat_manifest_copy();
    foreach ($copy['rows'] as $row)
    {
        if (mel_catv_rid($row) === $rid)
            return $row;
    }
    return null;
}

// New tiles to the listing. The listing begun anew (mel_cat_more: reset): the shell
// holds the old tiles by their place, so they stay and are not new any more.
/**
 * @param MelCatList $list
 * @param MelCatResult $res
 * @param-out MelCatList $list
 * @return void
 */
function mel_catv_add(&$list, $res)
{
    foreach ($res['tiles'] as $t)
        $list['tiles'][$t['id']] = $t;
    if ($res['reset'])
        mel_cat_keep_seen($list['st'], $list['tiles']);
}

// The next pages of the listing $list of $rid, up to $want tiles or its end, within $budget s
// from $start; the listing goes to memory page by page, so a repeat of a call cancelled is at
// once. -> '', the error of a page, or 'out of time'.
/**
 * @param MelCatList $list
 * @param string $rid
 * @param int $want
 * @param float $budget
 * @param float $start
 * @param-out MelCatList $list
 * @return string
 */
function mel_catv_fill(&$list, $rid, $want, $budget, $start)
{
    while (count($list['tiles']) < $want && $list['st']['end'] === '')
    {
        $left = $budget - (microtime(true) - $start);
        if ($left < MEL_CATV_MIN_S)
            return 'out of time';
        $res = mel_cat_more($list['st'], $left);
        mel_catv_add($list, $res);
        MelCat::$lists[$rid] = $list;
        if ($res['err'] !== '')
            return $res['err'];
    }
    return '';
}

// The screen "Show all" of the address of a tile "more:<rid>[:<id of the search>]", or ''.
/**
 * @param string $id
 * @return string
 */
function mel_catv_more_url($id)
{
    list(, $id) = mel_catv_id_mode($id);
    return strpos($id, 'more:') === 0 ? 'cat_grid:' . substr($id, strlen('more:')) : '';
}

// The listing of a screen "Show all" ("cat_grid:<rid>", of a search "cat_grid:<rid>:<id of
// the search>"), or null: a search no more in memory (MEL_CATV_SEARCHES) gets nothing. $anew:
// a row of the manifest not in memory gets a new listing, from the first page on, and so does a
// listing restored from a copy older than MEL_CAT_FRESH (mel_catv_list_restore): a first page
// hours old would begin the grid, the titles gone from a row of trends since then missing in it;
// its tiles stay to the screen it came from (MelCat::$on_screen). Not $anew, for the rows of a
// screen already shown (the listing anew may differ): the listing in memory, else restored.
/**
 * @param string $url
 * @param bool $anew
 * @return MelCatList|null
 */
function mel_catv_all_list($url, $anew)
{
    $p = explode(':', strval(substr($url, strlen('cat_grid:'))));
    $rid = $p[0];
    $list = isset(MelCat::$lists[$rid]) ? MelCat::$lists[$rid] : null;
    if (count($p) === 2)
        return isset(MelCat::$searches[$p[1]]) && in_array($rid, MelCat::$searches[$p[1]], true) ? $list : null;
    if (count($p) > 2 || ($list && isset($list['st']['row']['q'])))
        return null;
    if (!$anew)
        return $list ? $list : mel_catv_list_restore($rid);
    if ($list && !empty($list['restored']) && !mel_cat_state_fresh($list['st']))
    {
        MelCat::$on_screen[$rid] = $list['tiles'];
        $list = null;
    }
    if ($list)
        return $list;
    $row = mel_catv_row_by_rid($rid);
    if (!$row)
        return null;
    MelCat::$lists[$rid] = mel_catv_list(mel_cat_row_state($row), array(), false);
    return MelCat::$lists[$rid];
}

// The items of the grid from $from on, by their place in all the tiles so far: the shell
// counts from_ndx itself and a reply may be shorter than it thinks. No listing in memory:
// a new one, from the first page to $from. A failure: what there is; with nothing, the
// list ends for now, so the shell does not ask in a loop. ENTER on "Show all" goes on from
// there. Out of time with nothing, only repeats so far: more to come; such a call took all
// of MEL_CATV_GRID_S, so it is no fast loop.
/**
 * @param string $url
 * @param int $from
 * @return array<string, mixed>
 */
function mel_catv_range($url, $from)
{
    $list = mel_catv_all_list($url, true);
    $items = array();
    $more = false;
    if ($list)
    {
        $rid = mel_catv_rid($list['st']['row']);
        $out_of_time = mel_catv_fill($list, $rid, $from + 1, MEL_CATV_GRID_S, microtime(true)) === 'out of time';
        $lang = mel_catv_ui_lang();
        foreach (array_slice($list['tiles'], $from, MEL_CATV_GRID_PORTION) as $t)
            $items[] = mel_catv_grid_item($rid, $t, $lang);
        $more = $items ? $list['st']['end'] === '' || count($list['tiles']) > $from + count($items) : $out_of_time;
    }
    return array(
        PluginRegularFolderRange::total => $from + count($items),
        PluginRegularFolderRange::more_items_available => $more,
        PluginRegularFolderRange::from_ndx => $from,
        PluginRegularFolderRange::count => count($items),
        PluginRegularFolderRange::items => $items);
}

// get_regular_folder_items: only the grid has more pages.
/**
 * @param string $media_url
 * @param int $from
 * @return array<string, mixed>|null
 */
function mel_catv_range_op($media_url, $from)
{
    return strpos($media_url, 'cat_grid:') === 0 ? mel_catv_range($media_url, $from) : null;
}

/**
 * @param string $url
 * @return array<string, mixed>
 */
function mel_catv_grid_view($url)
{
    $range = mel_catv_range($url, 0);
    if (!$range[PluginRegularFolderRange::items])
    {
        mel_log('catalog: grid: nothing to show');
        return mel_lines_view(array(mel_expired_item()));
    }
    return mel_catv_grid_pane($range, array(GUI_EVENT_KEY_ENTER => mel_input('cat_enter')) + mel_egg_acts());
}

// The grid of r24 of the items of $range (mel_catv_range), its actions $actions: "Show all", "Мой список" and
// "Продолжить" of Fav 1 (view_my_list.php).
/**
 * @param array<string, mixed> $range
 * @param array<string, MelAction> $actions
 * @return array<string, mixed>
 */
function mel_catv_grid_pane($range, $actions)
{
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_REGULAR,
        PluginFolderView::data => array(
            PluginRegularFolderView::view_params => array(
                ViewParams::num_cols => 5,
                ViewParams::num_rows => 2,
                ViewParams::paint_details => true,
                ViewParams::paint_item_info_in_details => true,
                ViewParams::item_detailed_info_auto_line_break => true,
                ViewParams::item_detailed_info_font_size => FONT_SIZE_SMALL,
                ViewParams::item_detailed_info_title_color => 14,
                ViewParams::item_detailed_info_text_color => 7,
                ViewParams::icon_selection_box_width => 241,
                ViewParams::icon_selection_box_height => 360,
                ViewParams::content_box_x => 100,
                ViewParams::content_box_width => 1300,
                ViewParams::content_box_padding_left => 0,
                ViewParams::content_box_padding_right => 20),
            PluginRegularFolderView::base_view_item_params => array(
                ViewItemParams::item_paint_caption => false,
                ViewItemParams::icon_valign => VALIGN_CENTER,
                ViewItemParams::icon_width => 214,
                ViewItemParams::icon_height => 321,
                ViewItemParams::icon_sel_scale_factor => 1.1,
                ViewItemParams::item_detailed_icon_width => 350,
                ViewItemParams::item_detailed_icon_height => 525),
            PluginRegularFolderView::not_loaded_view_item_params => array(
                ViewItemParams::icon_path => MEL_CATV_ICONS . 'loading_movie_poster.png'),
            PluginRegularFolderView::load_failed_view_item_params => array(
                ViewItemParams::icon_path => MEL_CATV_ICONS . 'no_movie_poster.png'),
            PluginRegularFolderView::async_icon_loading => true,
            PluginRegularFolderView::actions => $actions,
            PluginRegularFolderView::initial_range => $range));
}

// --- r25 "Show all": as the expanded row of the Dune (movies/movie_list_screen.php), rows
// "row<k>" of 9 with the info panel, all of them in the view; the shell asks for the tiles.

// The end of the listing for "Show all": known, or past the last row the screen has.
/**
 * @param MelCatList $list
 * @return bool
 */
function mel_catv_all_end($list)
{
    return $list['st']['end'] !== '' || count($list['tiles']) >= MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS;
}

// r24 has neither get_folder_rows nor dup rows: the grid there.
/**
 * @return bool
 */
function mel_catv_all_ok()
{
    return defined('PLUGIN_OP_GET_FOLDER_ROWS') && defined('PLUGIN_ROW_TYPE_DUP') && defined('PLUGIN_ROW_OPT_LOADING') &&
        defined('PLUGIN_ROW_OPT_OPTIMIZE_INVISIBLE');
}

// A row as add_row of movie_list_screen.php r25 makes it: no title, one group.
/**
 * @param string $id
 * @param list<array<string, mixed>> $items
 * @param int $options
 * @return array<string, mixed>
 */
function mel_catv_all_def($id, $items, $options = 0)
{
    return array(
        PluginRow::type => PLUGIN_ROW_TYPE_REGULAR,
        PluginRow::id => $id,
        PluginRow::data => array(
            PluginRegularRow::items => $items,
            PluginRegularRow::item_params_template_id => 'common',
            PluginRegularRow::width => 1920,
            PluginRegularRow::left_padding => 90,
            PluginRegularRow::inactive_left_padding => 120,
            PluginRegularRow::right_padding => 120,
            PluginRegularRow::hide_captions => false,
            PluginRegularRow::hide_icons => false),
        PluginRow::group_id => 'all',
        PluginRow::height => 302,
        PluginRow::inactive_height => -1,
        PluginRow::focusable => true,
        PluginRow::options => $options);
}

// Row $k: the tiles 9k..9k+8 of the listing, fewer at its end, none past it. The listing only
// grows at its end, without repeats, so a row once shown never changes. The ids of the
// tiles are those of the rows of the catalog: the panel and ENTER work the same.
/**
 * @param string $rid
 * @param int $k
 * @param MelCatList $list
 * @return array<string, mixed>
 */
function mel_catv_all_row($rid, $k, $list)
{
    $items = array();
    foreach (array_slice($list['tiles'], MEL_CATV_ALL_COLS * $k, MEL_CATV_ALL_COLS) as $t)
        $items[] = mel_catv_title_item($rid, $t);
    return mel_catv_all_def("row$k", $items);
}

// A row to ask for, a copy of "ld" of row_dup_map; the type a literal, the r24 API has no such constant.
/**
 * @param int $k
 * @return array<string, mixed>
 */
function mel_catv_all_stub($k)
{
    return array(
        PluginRow::type => 'row_dup',
        PluginRow::data => 'ld',
        PluginRow::id => "row$k",
        PluginRow::options => mel_catv_loading_flag() | (defined('PLUGIN_ROW_OPT_OPTIMIZE_INVISIBLE') ? PLUGIN_ROW_OPT_OPTIMIZE_INVISIBLE : 0));
}

// The tick of the timer of the screen $url: of "Show all" (mel_catv_all_tick), of the main screen (mel_pg_tick).
// No dialog, as rows_info.
/**
 * @param string $url
 * @return MelAction
 */
function mel_catv_tick($url)
{
    $tick = mel_input('cat_tick', array('from' => $url));
    $tick[GuiAction::data] = null;
    return $tick;
}

// r25: the timer of the main screen $url again (mel_pg_tick_again): change_behaviour, its actions whole as of
// mel_catv_rows_view with the tick and the Fav keys (mel_mlv_keys); the focus and the tiles stay.
/**
 * @param string $url
 * @return MelAction
 */
function mel_catv_tick_again($url)
{
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => mel_catv_actions($url) + array(GUI_EVENT_TIMER => mel_catv_tick($url)) +
                mel_mlv_key_acts($url),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => MEL_PG_TICK_AGAIN_MS)));
}

// The view of "Show all" $url (or "Продолжить", "Мой список": mel_mlv_tick) again with its timer in $ms:
// change_behaviour, its actions whole (mel_catv_all_actions with the tick) and the Fav keys (mel_mlv_key_acts); the
// focus and the tiles stay.
/**
 * @param string $url
 * @param int $ms
 * @return MelAction
 */
function mel_catv_all_again($url, $ms)
{
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => mel_catv_all_actions($url, true) + mel_mlv_key_acts($url),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => $ms)));
}

// The actions of "Show all"; with a timer ($tick: of the stubs, or of "Продолжить", "Мой список"), its tick too.
/**
 * @param string $url
 * @param bool $tick
 * @return array<string, MelAction>
 */
function mel_catv_all_actions($url, $tick)
{
    $actions = mel_catv_actions($url);
    if ($tick)
        $actions[GUI_EVENT_TIMER] = mel_catv_tick($url);
    return $actions;
}

// The screen of the rows $rows: the pane of get_rows_pane of movie_list_screen.php r25, the
// panel and the template of the catalog. $dup: row_dup_map, null without stubs. $timer_ms: its timer in that many ms
// (of the stubs MEL_CATV_ALL_TICK_MS; "Продолжить", "Мой список": mel_mlv_view), 0 none.
/**
 * @param list<array<string, mixed>> $rows
 * @param array<string, array<string, mixed>>|null $dup
 * @param mixed $sel_state
 * @param string $url
 * @param int $timer_ms
 * @return array<string, mixed>
 */
function mel_catv_all_pane($rows, $dup, $sel_state, $url, $timer_ms = 0)
{
    array_unshift($rows, array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 20, PluginRow::id => 'top_vgap'));
    $r = mel_catv_pane_r(false);
    $pane = array(
        PluginRowsPane::rows => $rows,
        'row_dup_map' => $dup,
        PluginRowsPane::focus => array(
            GCompFocusDef::type => GCOMP_FOCUS_DEFAULT_CUT_IMAGE,
            GCompFocusDef::type2 => GCOMP_FOCUS_DEFAULT_RECT),
        PluginRowsPane::bg => null,
        PluginRowsPane::header_enabled => false,
        PluginRowsPane::single_list_navigation => true,
        PluginRowsPane::initial_focus_header => -1,
        PluginRowsPane::initial_focus_item_id => null,
        PluginRowsPane::initial_focus_row_id => null,
        PluginRowsPane::horizontal_focus_freedom_factor => 0.84,
        PluginRowsPane::horizontal_focus_end_min_offset => 0,
        PluginRowsPane::vertical_focus_freedom_factor => 0.0,
        PluginRowsPane::vertical_focus_gravity => -0.6,
        PluginRowsPane::vertical_focus_end_min_offset => 50,
        PluginRowsPane::screen_r => array('w' => 1920, 'h' => 1080, 'x' => 0, 'y' => 0),
        PluginRowsPane::screen_y2 => 600,
        PluginRowsPane::min_row_index_for_y2 => 0,
        PluginRowsPane::info_r => $r['info_r'],
        PluginRowsPane::vod_r => $r['vod_r'],
        PluginRowsPane::up_arrow_dy => 0,
        PluginRowsPane::regular_item_params_templates => array('common' => mel_catv_item_tpl(array(8, 4, 4, 6))));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
        PluginFolderView::data => array(
            PluginRowsFolderView::pane => $pane,
            PluginRowsFolderView::sel_state => is_string($sel_state) && $sel_state !== '' ? $sel_state : null,
            PluginRowsFolderView::actions => mel_catv_all_actions($url, $timer_ms > 0),
            PluginRowsFolderView::timer => $timer_ms > 0 ? array(GuiTimerDef::delay_ms => $timer_ms) : null));
}

// The screen "Show all" (mel_catv_all_list). Its first row is never flagged (a flagged
// first row covered the rows under it, 0.50.0) and never changes: the end not known, it is
// filled to 9 tiles here, in MEL_CATV_GRID_S. The end known: its rows only, the last one
// short. Else the full rows in memory, then stubs up to MEL_CATV_ALL_ROWS, and the timer. No
// tile at all: one tile "empty"; the first row not filled: its tiles and "did not load", ENTER
// on it opens the screen anew. After a failure of get_folder_rows (mel_catv_all_rows) the same
// from memory, its full rows and "did not load", no stubs, no timer: no request till ENTER on it.
/**
 * @param string $url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_catv_all_view($url, $sel_state)
{
    $start = microtime(true);
    $lang = mel_catv_lang();
    MelCat::$lang = $lang;
    $list = mel_catv_all_list($url, true);
    if (!$list)
    {
        mel_log('catalog: show all: nothing to show');
        return mel_lines_view(array(mel_expired_item()));
    }
    $rid = mel_catv_rid($list['st']['row']);
    $failed = isset(MelCat::$all_failed[$rid]);
    if (!$failed)
        mel_catv_fill($list, $rid, MEL_CATV_ALL_COLS, MEL_CATV_GRID_S, $start);
    $n = count($list['tiles']);
    $end = mel_catv_all_end($list);
    $rows = array();
    $dup = null;
    if ($n === 0 && $end)
    {
        $row = mel_catv_all_row($rid, 0, $list);
        $row[PluginRow::data][PluginRegularRow::items][] = mel_catv_note_item($rid, 'cat_row_empty', $lang);
        $rows[] = $row;
    }
    else if ($end)
    {
        for ($k = 0; $k < min(MEL_CATV_ALL_ROWS, intval(ceil($n / MEL_CATV_ALL_COLS))); $k++)
            $rows[] = mel_catv_all_row($rid, $k, $list);
    }
    else if ($failed || $n < MEL_CATV_ALL_COLS)
    {
        // "Did not load" after the last tile, a new row after a full one.
        $full = intval($n / MEL_CATV_ALL_COLS);
        for ($k = 0; $k <= $full; $k++)
            $rows[] = mel_catv_all_row($rid, $k, $list);
        $rows[$full][PluginRow::data][PluginRegularRow::items][] = mel_catv_note_item($rid, 'cat_row_failed', $lang);
    }
    else
    {
        $full = intval($n / MEL_CATV_ALL_COLS);
        for ($k = 0; $k < MEL_CATV_ALL_ROWS; $k++)
            $rows[] = $k < $full ? mel_catv_all_row($rid, $k, $list) : mel_catv_all_stub($k);
        // As add_loading_movie_ritem of movie_helper.php r25: no caption.
        $items = array();
        for ($j = 0; $j < MEL_CATV_ALL_COLS; $j++)
            $items[] = array(PluginRegularItem::id => ".$j", PluginRegularItem::icon_url => MEL_CATV_ICONS . 'loading_movie_poster_noalpha.png');
        // The shell puts the id of the dup row before the ids of its tiles: "row<k>.<j>".
        $dup = array('ld' => mel_catv_all_def('loading', $items, mel_catv_loading_flag()));
    }
    if ($dup)
    {
        MelCat::$all_stubs[$rid] = true;
        // The ticks of this screen from #1 in the log.
        MelCat::$all_ticks = 0;
        MelCat::$all_tick_last = '';
    }
    else
        unset(MelCat::$all_stubs[$rid]);
    mel_log(sprintf('catalog: show all: %d tiles, %d rows%s, %.0f ms', $n, count($rows), $end ? ', the end' : ($failed ? ', failed' : ''),
        (microtime(true) - $start) * 1000));
    return mel_catv_all_pane($rows, $dup, $sel_state, $url, $dup !== null ? MEL_CATV_ALL_TICK_MS : 0);
}

// get_folder_rows of "Show all": the rows asked that are full; once the end is known also the
// last short row and 0 tiles for every row past it, asked or not, in every reply (the shell
// throws away a reply it cancelled; it does not draw a row of 0 tiles). Before that the next
// pages, in MEL_CATV_ROW_S, up to the row after the first one asked and MEL_CATV_ALL_AHEAD tiles
// more, not to the last one: the opening asks row1..row11 at once, the rest of them the shell
// asks again by itself in a few ms, the screen below the first row is not empty for seconds. The
// listing goes to memory page by page, so a repeat of a cancelled call is at once. No row asked
// filled (a failure, out of time): "did not load" after the last tile and the rows past it closed
// as at the end, in every reply until the view anew; no request till then. Never an empty
// reply: the shell would ask again at once. php_server restarted: the listing goes on from the copy
// of the first page (mel_catv_all_list); no listing (no copy, a search out of memory): 0 tiles from
// the first row asked on; an id not of a row of the screen: 0 tiles with that id.
/**
 * @param string $url
 * @param array<mixed> $ids
 * @return non-empty-list<array<string, mixed>>
 */
function mel_catv_all_rows($url, $ids)
{
    $start = microtime(true);
    $list = mel_catv_all_list($url, false);
    // Restored from a copy older than MEL_CAT_STATE_TTL: mel_cat_more would begin it anew, its first page
    // after the tiles on the screen, the titles again. As with no listing.
    if ($list && !empty($list['restored']) && ($list['st']['t0'] > time() || time() - $list['st']['t0'] >= MEL_CAT_STATE_TTL))
        $list = null;
    $asked = array();
    $bad = array();
    foreach ($ids as $id)
    {
        if (!is_string($id))
            continue;
        if (preg_match('/^row([0-9]{1,3})$/D', $id, $m) && intval($m[1]) < MEL_CATV_ALL_ROWS)
            $asked[] = intval($m[1]);
        else
            $bad[] = mel_catv_all_def($id, array());
    }
    $out = array();
    if (!$list)
    {
        mel_log('catalog: show all: rows: no listing');
        for ($k = $asked ? min($asked) : MEL_CATV_ALL_ROWS; $k < MEL_CATV_ALL_ROWS; $k++)
            $out[] = mel_catv_all_def("row$k", array());
    }
    else
    {
        $rid = mel_catv_rid($list['st']['row']);
        $cap = MEL_CATV_ALL_COLS * MEL_CATV_ALL_ROWS;
        $want = $asked ? min($cap, MEL_CATV_ALL_COLS * (min($asked) + 2) + MEL_CATV_ALL_AHEAD) : 0;
        $was = count($list['tiles']);
        $failed = isset(MelCat::$all_failed[$rid]);
        $err = $failed ? '' : mel_catv_fill($list, $rid, $want, MEL_CATV_ROW_S, $start);
        $n = count($list['tiles']);
        $end = mel_catv_all_end($list);
        foreach ($asked as $k)
        {
            if ($end || MEL_CATV_ALL_COLS * ($k + 1) <= $n)
                $out[] = mel_catv_all_row($rid, $k, $list);
        }
        if (!$end && !$failed && $asked && !$out)
        {
            $failed = true;
            MelCat::$all_failed[$rid] = true;
            mel_log(sprintf('catalog: show all: rows: failed (%s), %d tiles', $err, $n));
        }
        if ($end || $failed)
        {
            $full = intval($n / MEL_CATV_ALL_COLS);
            for ($k = $full; $k < MEL_CATV_ALL_ROWS; $k++)
            {
                if (in_array($k, $asked, true) && $end)
                    continue;
                $row = mel_catv_all_row($rid, $k, $list);
                if ($failed && $k === $full)
                    $row[PluginRow::data][PluginRegularRow::items][] = mel_catv_note_item($rid, 'cat_row_failed', mel_catv_ui_lang());
                $out[] = $row;
            }
        }
        if ($n > $was)
            mel_log(sprintf('catalog: show all: rows: %d tiles, %d new%s, %.0f ms', $n, $n - $was, $end ? ', the end' : '',
                (microtime(true) - $start) * 1000));
    }
    $out = array_merge($out, $bad);
    // No id at all: a row the screen does not have, the shell drops it.
    return $out ? $out : array(mel_catv_all_def('none', array()));
}

// --- The info panel: on every focus move, from memory only.

// A label of the panel $w x $h at $y ($h -1: as high as its text). Options 0: as the
// native lines and description (GCompsFactory::label), else as its title (label_v2).
/**
 * @param string $text
 * @param int $size
 * @param string $color
 * @param int $w
 * @param int $h
 * @param int $y
 * @param array<string, mixed> $spec
 * @param int $options
 * @return array<string, mixed>
 */
function mel_catv_info_label($text, $size, $color, $w, $h, $y, $spec = array(), $options = 0)
{
    $spec[GCompTtfLabelDef::text] = $text;
    $spec[GCompTtfLabelDef::font_size] = $size;
    $spec[GCompTtfLabelDef::text_color] = $color;
    $def = array(
        GComponentDef::geom_def => array(
            GCompGeometryDef::w => $w,
            GCompGeometryDef::h => $h,
            GCompGeometryDef::align_def => array(GCompAlignDef::x => 0, GCompAlignDef::y => $y)),
        GComponentDef::kind => GCOMPONENT_TTF_LABEL,
        GComponentDef::specific_def => $spec);
    if ($options)
        $def[GComponentDef::options] = $options;
    return $def;
}

// The largest size from $max down to $min at which $text fits $width (font_w.php), and
// $text, cut with "…" at $min when it does not fit even so.
/**
 * @param string $text
 * @param int $max
 * @param int $min
 * @param int $width
 * @return array{int, string}
 */
function mel_catv_fit($text, $max, $min, $width)
{
    $w = mel_gc_w($text, 100);
    $px = max($min, min($max, $w > 0 ? intval($width * 100 / $w) + 1 : $max));
    while ($px > $min && mel_gc_w($text, $px) > $width)
        $px--;
    return array($px, mel_gc_cut_w($text, $px, $width));
}

// A label of the line of facts at $y: 30 px, LINE_H high as the other lines, $align its place (x, y
// set here when the align has no base).
/**
 * @param string $text
 * @param string $color
 * @param int $w
 * @param MelGcAlign $align
 * @param int $halign
 * @return array<string, mixed>
 */
function mel_catv_fact_label($text, $color, $w, $align, $halign = HALIGN_LEFT)
{
    $l = mel_catv_info_label(mel_clean($text), MEL_CATV_LINE_PX, $color, $w, MEL_CATV_LINE_H, 0,
        $halign === HALIGN_LEFT ? array() : array(GCompTtfLabelDef::halign => $halign));
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def] = $align;
    return $l;
}

// The line of facts of mel_catv_facts at $y, all of it 30 px: the plates at their places, as their
// widths are known; the text after them, cut with "…" to the room it has; the age plate after the
// text right of it by the shell (mel_gc_next_right: the text is as wide as the font of the user
// makes it), its label on it. The plates are pictures of their own size: the text in them, not
// the plate, follows the font.
/**
 * @param list<array{string, string, string}> $facts
 * @param int $y
 * @return list<array<string, mixed>>
 */
function mel_catv_facts_labels($facts, $y)
{
    $w = array('kp' => MEL_CATV_KP_W, 'imdb' => MEL_CATV_IMDB_W, 'age' => MEL_CATV_AGE_W);
    $last = end($facts);
    $age_w = $last && $last[0] === 'age' ? MEL_CATV_AGE_GAP + MEL_CATV_AGE_W : 0;
    $defs = array();
    $x = 0;
    $text = false;
    foreach ($facts as $f)
    {
        list($kind, $s, $value) = $f;
        if ($kind === 'text')
        {
            $x += $x ? MEL_CATV_REST_GAP : 0;
            $defs[] = mel_catv_fact_label(mel_gc_cut_w(mel_clean($s), MEL_CATV_LINE_PX, MEL_CATV_TEXT_W - $x - $age_w),
                MEL_CATV_TEXT_COLOR, -1, array(GCompAlignDef::x => $x, GCompAlignDef::y => $y));
            $text = true;
            continue;
        }
        $pw = $w[$kind];
        $plate = MEL_CATV_IMG . "$kind.png";
        if ($kind === 'age' && $text)
        {
            $defs[] = mel_gc_image(mel_gc_geom($pw, MEL_CATV_PLATE_H, mel_gc_next_right(MEL_CATV_AGE_GAP, MEL_CATV_PLATE_DY)), $plate);
            $defs[] = mel_catv_fact_label($s, MEL_CATV_TEXT_COLOR, $pw, array(GCompAlignDef::y => -MEL_CATV_PLATE_DY,
                GCompAlignDef::halign => HALIGN_LEFT, GCompAlignDef::valign => VALIGN_TOP, GCompAlignDef::base_halign => HALIGN_LEFT,
                GCompAlignDef::base_valign => VALIGN_TOP, GCompAlignDef::base_to_prev => true), HALIGN_CENTER);
            continue;
        }
        $x += $x ? ($kind === 'age' ? MEL_CATV_AGE_GAP : MEL_CATV_PLATE_GAP) : 0;
        $defs[] = mel_gc_image(mel_gc_at($pw, MEL_CATV_PLATE_H, $x, $y + MEL_CATV_PLATE_DY), $plate);
        $in = $pw - 2 * MEL_CATV_PLATE_PAD;
        if ($kind === 'age')
            $defs[] = mel_catv_fact_label($s, MEL_CATV_TEXT_COLOR, $pw, array(GCompAlignDef::x => $x, GCompAlignDef::y => $y), HALIGN_CENTER);
        else if (mel_gc_w("$s $value", MEL_CATV_LINE_PX) <= $in)
        {
            $at = array(GCompAlignDef::x => $x + MEL_CATV_PLATE_PAD, GCompAlignDef::y => $y);
            $defs[] = mel_catv_fact_label($s, MEL_CATV_TEXT_COLOR, $in, $at);
            $defs[] = mel_catv_fact_label($value, MEL_CATV_RATE_COLOR, $in, $at, HALIGN_RIGHT);
        }
        else
            $defs[] = mel_catv_fact_label(mel_gc_cut_w("$s $value", MEL_CATV_LINE_PX, $pw), MEL_CATV_RATE_COLOR, $pw,
                array(GCompAlignDef::x => $x, GCompAlignDef::y => $y), HALIGN_CENTER);
        $x += $pw;
    }
    return $defs;
}

// The title, its box TITLE_H high as the native one with the bottom at $bottom: a line as large as
// fits TEXT_W from TITLE_PX down to TITLE_MIN_PX; too long even so, 2 lines of TITLE2_PX, cut here
// to them (the native box takes 2 lines of 45 px at least).
/**
 * @param string $name
 * @param int $bottom
 * @return array<string, mixed>
 */
function mel_catv_title_label($name, $bottom)
{
    $spec = array(GCompTtfLabelDef::line_spacing => 0, GCompTtfLabelDef::valign => VALIGN_BOTTOM);
    if (mel_gc_w($name, MEL_CATV_TITLE_MIN_PX) <= MEL_CATV_TEXT_W)
        $fit = mel_catv_fit($name, MEL_CATV_TITLE_PX, MEL_CATV_TITLE_MIN_PX, MEL_CATV_TEXT_W);
    else
    {
        $fit = array(MEL_CATV_TITLE2_PX, implode(' ', mel_gc_wrap($name, MEL_CATV_TITLE2_PX, MEL_CATV_TEXT_W, 2, true)));
        $spec[GCompTtfLabelDef::max_num_lines] = 2;
    }
    return mel_catv_info_label($fit[1], $fit[0], MEL_CATV_TITLE_COLOR, MEL_CATV_TEXT_W, MEL_CATV_TITLE_H, $bottom - MEL_CATV_TITLE_H,
        $spec, GCOMP_OPT_TTF_LAYOUT_FIX | GCOMP_OPT_TTF_COMPACT_HEIGHT);
}

// The panel on the coordinates of the native one (movie_helper.php, the same info_r), 30 px lower,
// nearer the rows: the title (mel_catv_title_label), none when the background has the logo in its
// place (mel_catv_card); without the logo, the English name of the tile under the title when it is not
// the title (native_title of dunemdb_cache.php: of a title not in English, as those of the catalog are
// in any interface), the title higher by its height. Then the facts, the genres and the
// country, the cast: lines LINE_H high from MEL_CATV_LINES_Y; the description 13 px under the last one, as many lines
// as the rest of the panel holds, as the native one counts them; the line of the history of a card watched or begun
// (mel_pg_info_line; a title of "Мой список" with " · В моём списке" at its end, one not watched with these words
// alone, mel_pg_info_words) under it at one place, the description the lines over it. No line for an empty field, the
// next ones go up.
// "Show all": the name of the row and the caption of the tile. null without a tile (mel_catv_tile) or a row in memory or a copy
// (mel_catv_all_list: a stub, a message). No label with an empty text: whether the shell takes one is not known.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_catv_info($in)
{
    $id = mel_catv_in($in, 'item_id');
    $t = mel_catv_tile($id, mel_tv_is_key(mel_catv_in($in, 'folder_key')));
    $more_url = mel_catv_more_url($id);
    $more = $more_url !== '' ? mel_catv_all_list($more_url, false) : null;
    if ($t)
    {
        $name = $t['name'];
        $sub = $t['englishTitle'] !== $name ? $t['englishTitle'] : '';
        $lang = mel_catv_ui_lang();
        $facts = mel_catv_facts($t, $lang);
        $lines = array(array(mel_catv_genre_line($t, $lang), MEL_CATV_TEXT_COLOR, MEL_CATV_DATA_MIN_PX),
            array(mel_catv_cast_line($t), MEL_CATV_CAST_COLOR, MEL_CATV_LINE_PX));
        $desc = $t['description'];
    }
    else if ($more)
    {
        $name = $more['st']['row']['name'];
        $sub = '';
        $facts = array();
        $lines = array(array(mel_clean(mel_tr(mel_catv_ui_lang(), 'cat_more')), MEL_CATV_TEXT_COLOR, MEL_CATV_LINE_PX));
        $desc = '';
    }
    // The hint of an empty "Мой список": its name and what it is for (the tile has the short words).
    else if (mel_mlv_is_hint($id))
    {
        $name = mel_tr(mel_catv_ui_lang(), 'ml_title');
        $sub = '';
        $facts = array();
        $lines = array();
        $desc = mel_tr(mel_catv_ui_lang(), 'ml_hint');
    }
    else
        return null;
    $labels = array();
    $name = mel_clean($name);
    list($on) = mel_catv_id_mode($id);
    $card = mel_catv_card($t, $on);
    // None under the logo. Cut here to 2 lines of the least size, "…" on the last one; the shell sizes it down to them.
    $sub = $card['logo'] ? '' : implode(' ', mel_gc_wrap(mel_clean($sub), MEL_CATV_SUB_MIN_PX, MEL_CATV_TEXT_W, 2, true));
    $bottom = MEL_CATV_TITLE_BOTTOM - ($sub !== '' ? MEL_CATV_SUB_H : 0);
    if ($name !== '' && !$card['logo'])
        $labels[] = mel_catv_title_label($name, $bottom);
    if ($sub !== '')
        $labels[] = mel_catv_info_label($sub, MEL_CATV_SUB_PX, MEL_CATV_SUB_COLOR, MEL_CATV_TEXT_W, MEL_CATV_SUB_H, $bottom + 4,
            array(GCompTtfLabelDef::max_num_lines => 2, GCompTtfLabelDef::min_font_size => MEL_CATV_SUB_MIN_PX,
                GCompTtfLabelDef::line_spacing => 0));
    $y = MEL_CATV_LINES_Y;
    if ($facts)
    {
        $labels = array_merge($labels, mel_catv_facts_labels($facts, $y));
        $y += MEL_CATV_LINE_H;
    }
    foreach ($lines as $l)
    {
        if ($l[0] === '')
            continue;
        $fit = mel_catv_fit($l[0], MEL_CATV_LINE_PX, $l[2], MEL_CATV_TEXT_W);
        $labels[] = mel_catv_info_label($fit[1], $fit[0], $l[1], MEL_CATV_TEXT_W, MEL_CATV_LINE_H, $y);
        $y += MEL_CATV_LINE_H;
    }
    // Lines of 30 px 5 px apart, 13 px under the last line; cut here to them, "…" on the last one; the shell, with a
    // little more room, breaks them no sooner.
    $y += 13;
    $step = MEL_CATV_LINE_PX + 5;
    $n = intval((MEL_CATV_PANE_H - $y - MEL_CATV_LINE_PX) / $step);
    // The line of the history (mel_pg_info_line), V2b of the designer: under the description, as the native one, at one
    // place whatever the length of the description and the lines over it: its middle MEL_PG_INFO_GAP under the middle of
    // the last line of a description of MEL_CATV_DESC_HIST lines under all the lines of the panel (facts, genres, cast);
    // the description as many lines as are over it.
    // A title of "Мой список": " · В моём списке" after it; in the list with no history the words alone there.
    $mid = mel_cp_mid_of($id);
    $b = $t ? ($mid !== '' ? mel_cp_badge($mid, $t['type']) : mel_pg_tile_badge($t)) : null;
    $ml = $t && mel_ml_in($t, $mid) !== '' ? mel_tr(mel_catv_ui_lang(), 'ml_in') : '';
    $hist = array();
    if ($b || $ml !== '')
    {
        // The top of the last line of such a description.
        $last = MEL_CATV_LINES_Y + 3 * MEL_CATV_LINE_H + 13 + (MEL_CATV_DESC_HIST - 1) * $step;
        $n = min($n, intval(($last - $y) / $step) + 1);
        $hy = $last + MEL_CATV_DESC_MID + MEL_PG_INFO_GAP - intval(MEL_PG_INFO_H / 2);
        $hist = $b ? mel_pg_info_line($b, $hy, mel_catv_ui_lang(), $ml !== '' ? " · $ml" : '') : mel_pg_info_words($ml, $hy);
    }
    $desc = implode(' ', mel_gc_wrap(mel_clean($desc), MEL_CATV_LINE_PX, MEL_CATV_TEXT_W, $n, true));
    if ($desc !== '')
        $labels[] = mel_catv_info_label($desc, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR, MEL_CATV_TEXT_W, -1, $y,
            array(GCompTtfLabelDef::max_num_lines => $n, GCompTtfLabelDef::line_spacing => 5));
    $labels = array_merge($labels, $hist);
    $pane = array(
        GComponentDef::geom_def => array(
            GCompGeometryDef::w => MEL_CATV_PANE_W,
            GCompGeometryDef::h => MEL_CATV_PANE_H,
            GCompGeometryDef::align_def => array(GCompAlignDef::x => 0, GCompAlignDef::y => 0)),
        GComponentDef::kind => GCOMPONENT_PANEL,
        GComponentDef::id => 'info_pane',
        GComponentDef::options => GCOMP_OPT_PREPAINT,
        GComponentDef::specific_def => array(GCompPanelDef::children => $labels),
        GComponentDef::extra_variant_defs => array(array(
            GCompVariantDef::ref_id => 'info_inf_dimmed',
            GCompVariantDef::geom_def => null,
            GCompVariantDef::props => array('alpha' => 64))));
    $info = array(
        PluginRowsInfo::info_defs => array($pane),
        PluginRowsInfo::bg_url => $card['bg'],
        PluginRowsInfo::nl_bg_url => $card['nl'],
        PluginRowsInfo::mask_url => $card['mask'],
        PluginRowsInfo::bg_poster_mode => false,
        PluginRowsInfo::playback_urls => null,
        PluginRowsInfo::item_id => $id);
    if (mel_catv_in($in, 'folder_key') !== '')
        $info[PluginRowsInfo::folder_key] = mel_catv_in($in, 'folder_key');
    return array(
        GuiAction::handler_string_id => PLUGIN_UPDATE_ROWS_INFO_ACTION_ID,
        GuiAction::data => array(
            PluginUpdateRowsInfoActionData::info => $info,
            PluginUpdateRowsInfoActionData::post_action => null));
}

// --- Input.

/**
 * @param string $url
 * @return MelAction
 */
function mel_catv_invalidate($url)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
        GuiAction::data => array(
            PluginInvalidateFoldersActionData::media_urls => array($url),
            PluginInvalidateFoldersActionData::post_action => null,
            PluginInvalidateFoldersActionData::all_except => false));
}

// The screens of the history and the list opened, invalidated after the player, a mark, a change of the list: the main
// screens of rows (MelCat::$mains), then "Мой список" and "Продолжить" of Fav 1 (MelMlv::$opened).
/**
 * @return list<string>
 */
function mel_catv_mains_urls()
{
    return array_merge(MelCat::$mains, array_keys(MelMlv::$opened));
}

// The screens opened (mel_catv_mains_urls) invalidated first, then $post: the row "Continue" and the badges of
// the history anew when the shell is back on one (O2: whether a screen under the card is built again, the focus kept,
// is not proven); none opened: $post as it is. Only on r25: on r24 a screen of rows lower in the stack built again may
// go to the network for 3-7 s (not checked there). $also ('' none): a screen invalidated too, first of them, on r24 too
// (the screen of a change of "Мой список", mel_ml_changed; the screen of a tick, mel_mlv_tick). $why: for the log.
// $fav: the screens of Fav opened too; not by a tick (mel_mlv_tick: a screen of Fav lower in the stack built again would
// take the timer of the screen of the tick).
/**
 * @param MelAction|null $post
 * @param string $why
 * @param string $also
 * @param bool $fav
 * @return MelAction|null
 */
function mel_catv_mains_anew($post, $why, $also = '', $fav = true)
{
    $mains = $fav ? mel_catv_mains_urls() : MelCat::$mains;
    $urls = array_merge($also !== '' ? array($also) : array(), mel_fw_r() >= 25 ? $mains : array());
    $urls = array_values(array_unique($urls));
    if (!$urls)
    {
        if ($mains)
            mel_log("catalog: no invalidate $why: r" . mel_fw_r());
        return $post;
    }
    mel_log("catalog: invalidate $why: " . implode(', ', $urls));
    $a = mel_catv_invalidate($urls[0]);
    $a[GuiAction::data][PluginInvalidateFoldersActionData::media_urls] = $urls;
    $a[GuiAction::data][PluginInvalidateFoldersActionData::post_action] = $post;
    return $a;
}

// Back from the player (mel_finish), the action $post of the list of streams: mel_catv_mains_anew; invalidated, the time
// of the operation for the log of the screen built again (MelCat::$inv_t).
/**
 * @param MelAction|null $post
 * @return MelAction|null
 */
function mel_catv_after_player($post)
{
    $a = mel_catv_mains_anew($post, 'after the player');
    if ($a !== $post)
        MelCat::$inv_t = Mel::$t0 > 0 ? floatval(Mel::$t0) : microtime(true);
    return $a;
}

// The items of POP UP of tile $id of screen $from (a main screen of the catalog, "Show all", the results of a search, an
// EPF of "TV"): "Смотреть из…" of a title (mel_catv_src_items), those of the row "Continue" and the marks
// (mel_cp_menu_items), then that of "Мой список" (mel_ml_menu_items), then a separator and the import of the Favorites
// of the Dune (mel_mlv_menu_items: on the screen "Мой список" and its row); a separator after them is for the caller,
// when items of the screen follow. A tile mel_catv_tile has not (the hint of "Мой список", '' - none in focus) is for
// each part to tell.
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_catv_tile_menu($id, $from, $lang)
{
    return mel_catv_menu_join(array_merge(mel_catv_src_items($id, $from, $lang), mel_cp_menu_items($id, $from, $lang),
        mel_ml_menu_items($id, $from, $lang)), mel_mlv_menu_items($id, $from, $lang));
}

// "Смотреть из…" of tile $id of screen $from (mel_src_watch_item: two ready profiles or more): a movie or a series with
// an id of the meta (mel_cm_id, as of the own card), cat_src_menu of it; none on another.
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_catv_src_items($id, $from, $lang)
{
    $t = mel_catv_tile($id, mel_tv_is_from($from));
    if (!$t || mel_cm_id($t) === '')
        return array();
    return mel_src_watch_item($lang, 'cat_src_menu', array('from' => $from, 'item' => $id));
}

// cat_src of POP UP (p, n of the menu of sources of "Смотреть из…") of tile $in->item of screen $in->from: the list of
// the title from that profile at once (mel_oc_tile_watch; the card of the Dune of the entry of "Мой список", else known
// with no request - mel_cp_card_known, else asked as by ENTER); the profile gone or not ready - its dialog
// (mel_src_watch_pick), no request; no such tile now - cat_outdated.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_catv_src($in)
{
    $lang = mel_catv_lang();
    list($src, $err) = mel_src_watch_pick($in, $lang);
    if ($src === '' || $err)
        return $err;
    $id = mel_catv_in($in, 'item');
    $t = mel_catv_tile($id, mel_tv_is_from(mel_catv_in($in, 'from')));
    if (!$t || mel_cm_id($t) === '')
        return mel_error('cat_outdated');
    $k = mel_mlv_key_of($id);
    $e = $k !== null ? mel_mlv_entry($k) : null;
    $dune = $e && $e['dune_id'] !== '' ? $e['dune_id'] : mel_cp_card_known($t, mel_cp_mid_of($id));
    return mel_oc_tile_watch($t, $lang, $dune, $src);
}

// $a, then a separator and $b; no separator when one of them is empty.
/**
 * @param list<array<string, mixed>> $a
 * @param list<array<string, mixed>> $b
 * @return list<array<string, mixed>>
 */
function mel_catv_menu_join($a, $b)
{
    if ($a && $b)
        $a[] = array(GuiMenuItemDef::is_separator => true);
    return array_merge($a, $b);
}

// The input of an action of POP UP of a tile ($in: item, from, w of mel_catv_tile_menu): its tile (mel_catv_tile; of
// the file of "TV" there). -> id, from, w, t: the tile, null with err: cat_outdated (no such tile now), null (w not of
// $ws).
/**
 * @param stdClass $in
 * @param list<string> $ws
 * @return array{id: string, from: string, w: string, t: MelCatTile|null, err: MelAction|null}
 */
function mel_catv_tile_act($in, $ws)
{
    $id = mel_catv_in($in, 'item');
    $from = mel_catv_in($in, 'from');
    $w = mel_catv_in($in, 'w');
    $ok = in_array($w, $ws, true);
    $t = $ok ? mel_catv_tile($id, mel_tv_is_from($from)) : null;
    return array('id' => $id, 'from' => $from, 'w' => $w, 't' => $t, 'err' => $ok && !$t ? mel_error('cat_outdated') : null);
}

// The tick of the timer of "Show all" $url, memory only. The view of stubs and the end known or
// a failure (its tail closed, the focus on a stub lost): the view anew without stubs and timer;
// else the timer again (update_next_movie of main_entry_handler.php r25). No listing or stubs:
// no more ticks. The log: only a tick that gives other than the last one.
/**
 * @param string $url
 * @return MelAction|null
 */
function mel_catv_all_tick($url)
{
    $n = ++MelCat::$all_ticks;
    $list = mel_catv_all_list($url, false);
    $act = null;
    $rid = $list ? mel_catv_rid($list['st']['row']) : '';
    if (!$list || !isset(MelCat::$all_stubs[$rid]))
    {
        $what = 'null';
        $why = $list ? 'no stubs' : 'no listing';
    }
    else
    {
        $tiles = count($list['tiles']);
        $end = mel_catv_all_end($list);
        $failed = isset(MelCat::$all_failed[$rid]);
        $why = ($end ? 'the end, ' : ($failed ? 'failed, ' : '')) . "$tiles tiles";
        if ($end || $failed)
        {
            $what = 'invalidate';
            $act = mel_catv_invalidate($url);
        }
        else
        {
            $what = 'change_behaviour';
            $act = mel_catv_all_again($url, MEL_CATV_ALL_TICK_MS);
        }
    }
    if ($what !== MelCat::$all_tick_last)
        mel_log("catalog: show all: tick #$n: $why, $what");
    MelCat::$all_tick_last = $what;
    return $act;
}

// Opens the screen "Show all" $url (mel_catv_more_url), named after its row.
/**
 * @param string $url
 * @return MelAction
 */
function mel_catv_all_open($url)
{
    $list = mel_catv_all_list($url, false);
    return mel_catv_open_act($url, $list ? $list['st']['row']['name'] : mel_tr(mel_catv_lang(), 'cat_title'));
}

// ENTER on the part $id of the strip of the main screen $from. A tab not active: the screen of its
// type in place of this one (erase_count 1: Back does not grow), the active one: nothing; $up (its
// item of POP UP, mel_catv_menu): the active one too, the focus up on it. The icons: the dialog of
// a search, the settings screen and the screens "Продолжить" and "Мой список".
/**
 * @param string $id
 * @param string $from
 * @param bool $up
 * @return MelAction|null
 */
function mel_catv_strip_enter($id, $from, $up = false)
{
    if ($id === 'tab_search')
        return mel_catv_search_ask($from);
    if ($id === 'tab_setup')
        return mel_setup_open_act($from);
    if ($id === 'tab_continue')
        return mel_mlv_open('continue', $from);
    if ($id === 'tab_mylist')
        return mel_mlv_open('list', $from);
    $tab = strval(substr($id, strlen('tab_')));
    if (($tab !== 'movie' && $tab !== 'series') || !mel_catv_is_main($from) || (!$up && $tab === mel_catv_tab($from)))
        return null;
    return mel_replace_act(mel_catv_main_url($tab), '%tr%plugin_caption');
}

// POP UP of the screen $from. A main screen: the parts of the strip, as ENTER on them (cat_part), so
// the strip is at hand anywhere, with icons of the shell (the search and the setup as on the strip);
// on a header above them "Configure list…" and a separator (mel_catv_rows_menu_items), on a tile of a
// title its items and a separator (mel_catv_tile_menu). Else (the results of a search, "Show all") the items of a tile
// alone, no separator; none: nothing.
/**
 * @param stdClass $in
 * @param string $from
 * @return MelAction|null
 */
function mel_catv_menu($in, $from)
{
    $hid = mel_catv_in($in, 'selected_header_id');
    $tab = mel_catv_tab($from);
    mel_log("catalog: POP UP: header '" . mel_cut($hid, 100) . "', row '" . mel_cut(mel_catv_in($in, 'selected_row_id'), 100) .
        "', item '" . mel_cut(mel_catv_in($in, 'selected_item_id'), 100) . "'");
    $lang = mel_catv_lang();
    // On a tile (no header in focus): its items first (mel_catv_tile_menu), then those of the screen. The grid of r24
    // ("Мой список", "Продолжить") brings the tile as selected_media_url.
    $item = mel_catv_in($in, 'selected_item_id');
    if ($item === '')
        $item = mel_catv_in($in, 'selected_media_url');
    // A header in focus: no tile (on the screen "Мой список" the import still).
    $items = mel_catv_tile_menu($hid === '' ? $item : '', $from, $lang);
    if ($tab === '')
    {
        return $items ? array(
            GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
            GuiAction::data => array(ShowPopupMenuActionData::menu_items => $items)) : null;
    }
    // On a header "Configure list…" and its separator (mel_catv_rows_menu_items), then the parts of the strip.
    $screen = mel_catv_rows_menu_items($hid, $tab, $from, $lang);
    // The tabs with icons of their own, then the icons of the strip (mel_catv_strip_icons).
    $parts = array_merge(
        array('tab_movie' => array('cat_part_movies', 'movies_small.png'), 'tab_series' => array('cat_part_series', 'tv_small.png')),
        mel_catv_strip_icons());
    foreach ($parts as $id => $p)
    {
        $screen[] = array(
            GuiMenuItemDef::caption => mel_tr($lang, $p[0]),
            GuiMenuItemDef::icon_url => MEL_CATV_ICONS . $p[1],
            GuiMenuItemDef::action => mel_input('cat_part', array('from' => $from, 'part' => $id)));
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => mel_catv_menu_join($items, $screen)));
}

// ENTER on a tile: selected_item_id on the rows, selected_media_url on the grid.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_catv_enter($in)
{
    $id = mel_catv_in($in, 'selected_item_id');
    if ($id === '')
        $id = mel_catv_in($in, 'selected_media_url');
    if (mel_catv_in($in, 'selected_row_id') === MEL_CATV_STRIP || preg_match('/^tab_[a-z]+$/D', $id))
        return mel_catv_strip_enter($id, mel_catv_in($in, 'from'));
    // "more:<rid>", of a search "more:<rid>:<id of the search>": an older search opens outdated.
    $url = mel_catv_more_url($id);
    if ($url !== '')
        return mel_catv_all_open($url);
    if (strpos($id, 'retry:') === 0)
        return mel_catv_retry(mel_catv_in($in, 'from'));
    // A stub of "Show all": row<k>.<j>.
    if (strpos($id, 'stub:') === 0 || strpos($id, 'empty:') === 0 || mel_mlv_is_hint($id) || preg_match('/^row[0-9]+\.[0-9]+$/D', $id))
        return null;
    // The opening tries again, and the rows after it too; the shell brings sel_state, the focus stays.
    // "Show all" goes on from the same place.
    if (strpos($id, 'fail:') === 0)
    {
        // MelCat::$slow stays: it is the order of the requests alone, the slow row first again would take the opening.
        MelCat::$down = 0;
        unset(MelCat::$all_failed[strval(substr($id, strlen('fail:')))]);
        return mel_catv_invalidate(mel_catv_in($in, 'from'));
    }
    // A tile of the row "Continue": by its card of the Dune.
    $mid = mel_cp_mid_of($id);
    if ($mid !== '')
        return mel_cp_enter($mid, mel_catv_lang());
    // A tile of "Мой список": by its card of the Dune when the entry knows it.
    $ml = mel_mlv_enter($id, mel_catv_lang());
    if ($ml)
        return $ml;
    $t = mel_catv_tile($id, mel_tv_is_from(mel_catv_in($in, 'from')));
    if (!$t)
    {
        mel_log('catalog: ENTER: no such tile in memory');
        return mel_error('cat_outdated');
    }
    return mel_card_enter($t, mel_catv_lang());
}

// "Retry" of the main screen $from: rows asked anew, the screen of its tab by a new address.
/**
 * @param string $from
 * @return MelAction
 */
function mel_catv_retry($from)
{
    MelCat::$empty = array();
    // MelCat::$slow stays: the order of the requests alone (mel_catv_open).
    MelCat::$down = 0;
    $tab = mel_catv_tab($from);
    return mel_replace_act(mel_catv_main_url($tab !== '' ? $tab : 'movie', substr(mel_rid(), 0, 8)), mel_tr(mel_catv_lang(), 'cat_title'));
}

// The search $sid out of memory, its listings and the flags of their screens with it.
/**
 * @param string $sid
 * @return void
 */
function mel_catv_search_drop($sid)
{
    if (!isset(MelCat::$searches[$sid]))
        return;
    foreach (MelCat::$searches[$sid] as $r)
        unset(MelCat::$lists[$r], MelCat::$all_failed[$r], MelCat::$all_stubs[$r]);
    unset(MelCat::$searches[$sid], MelCat::$at_once[$sid]);
}

// The dialog of a search; the field goes to cat_search_apply and on as cat_q. The
// query is in no action to run and no address: only in what the shell sends back
// and in the field, which starts with the last one (MelCat::$last_q).
/**
 * @param string $from
 * @return MelAction
 */
function mel_catv_search_ask($from)
{
    $p = array('from' => $from);
    return mel_dialog_act(mel_tr(mel_catv_lang(), 'cat_search'), array(
        mel_ctl('cat_q', GUI_CONTROL_TEXT_FIELD, array(
            GuiTextFieldDef::initial_value => MelCat::$last_q,
            GuiTextFieldDef::numeric => 0,
            GuiTextFieldDef::password => 0,
            GuiTextFieldDef::has_osk => 1,
            GuiTextFieldDef::always_active => 1,
            GuiTextFieldDef::width => 1400,
            GuiTextFieldDef::apply_action => mel_input('cat_search_apply', $p),
            GuiTextFieldDef::confirm_action => null), ''),
        mel_ctl('', GUI_CONTROL_VGAP, array(GuiVGapDef::vgap => 500))),
        array(ShowDialogActionData::preferred_width => 0));
}

// $q: no log, no address of a screen. Results: two rows, the empty ones hidden; one row on
// r25: its "Show all".
/**
 * @param string $q
 * @param string $from
 * @return MelAction|null
 */
function mel_catv_search_run($q, $from)
{
    $q = mel_cat_query($q);
    if ($q === '')
        return null;
    MelCat::$last_q = $q;
    $lang = mel_catv_lang();
    // The no-break space at the end of cat_movies, cat_series is on purpose, in place of the number (the owner's choice); editors drop it as trailing whitespace: keep it.
    $found = mel_cat_search($q, array('movie' => mel_tr($lang, 'cat_movies'), 'series' => mel_tr($lang, 'cat_series')));
    $sid = substr(mel_rid(), 0, 8);
    $rids = array();
    $failed = false;
    $whole = false;
    foreach ($found as $f)
    {
        $res = $f['res'];
        $failed = $failed || $res['err'] !== '';
        if (!$res['tiles'])
            continue;
        $st = $f['st'];
        $st['row']['sid'] = $sid;
        $rid = mel_catv_rid($st['row']);
        MelCat::$lists[$rid] = mel_catv_list($st, $res['tiles'], $res['stale']);
        $rids[] = $rid;
        $whole = $res['err'] === '' && !$res['stale'];
    }
    if (!$rids)
        return mel_error($failed ? 'cat_search_failed' : 'cat_search_none');
    MelCat::$searches[$sid] = $rids;
    // From the results of a search, its "Show all" opened at once too: they are replaced, that
    // search holds no place. "Show all" of a search with the results under it: opened over it.
    $old = strpos($from, 'results:') === 0 ? strval(substr($from, strlen('results:'))) : '';
    if (preg_match('/^cat_grid:[^:]+:([^:]+)$/D', $from, $m) && isset(MelCat::$at_once[$m[1]]))
        $old = $m[1];
    $replace = $old !== '';
    if ($replace)
        mel_catv_search_drop($old);
    // Not array_shift: an id of digits only is a key of int, it would renumber it.
    while (count(MelCat::$searches) > MEL_CATV_SEARCHES)
    {
        reset(MelCat::$searches);
        mel_catv_search_drop(strval(key(MelCat::$searches)));
    }
    // One type found: its "Show all" at once, named after its row, as mel_catv_all_open. Only as
    // the expanded row of r25: the grid of r24 has neither the search key nor the info panel. Not
    // after a failure or of a saved copy: the results say so on the row and do not ask again.
    $one = count($rids) === 1 && $whole && mel_catv_all_ok();
    if ($one)
        MelCat::$at_once[$sid] = true;
    $url = $one ? "cat_grid:{$rids[0]}:$sid" : "cat_search:$sid";
    $caption = $one ? MelCat::$lists[$rids[0]]['st']['row']['name'] : mel_tr($lang, 'cat_results');
    return $replace ? mel_replace_act($url, $caption) : mel_catv_open_act($url, $caption);
}

/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_catv_input($in, $control)
{
    $from = mel_catv_in($in, 'from');
    if ($control === 'cat_info')
        return mel_catv_info($in);
    if ($control === 'cat_enter')
        return mel_tv_is_from($from) ? mel_tv_enter($in) : mel_catv_enter($in);
    // show_all_action of a row: cat_more is the id of its tile "Show all".
    if ($control === 'cat_all')
    {
        $url = mel_catv_more_url(mel_catv_in($in, 'cat_more'));
        return $url !== '' ? mel_catv_all_open($url) : null;
    }
    // The tick of the timer of the main screen: the lazy step; more to ask - the timer again (as mel_catv_all_tick); some
    // meta got - the screen built again with it (mel_pg_tick_anew), its view sets the timer; else nothing in the reply.
    if ($control === 'cat_tick' && mel_catv_is_main($from))
    {
        if (mel_pg_tick($from))
            return mel_catv_tick_again($from);
        return mel_pg_anew($from) ? mel_catv_invalidate($from) : null;
    }
    // The tick of the screen "Продолжить" or "Мой список": the lazy step of its tiles, the screen anew with what came.
    if ($control === 'cat_tick' && mel_mlv_is_screen($from))
        return mel_mlv_tick($from);
    if ($control === 'cat_tick')
        return mel_catv_all_tick($from);
    if ($control === 'cat_retry')
        return mel_tv_is_from($from) ? null : mel_catv_retry($from);
    if ($control === 'cat_search')
        return mel_catv_search_ask($from);
    if ($control === 'cat_search_apply')
        return mel_close_and(mel_input('cat_search_run', array('from' => $from)));
    if ($control === 'cat_search_run')
        return mel_catv_search_run(mel_catv_in($in, 'cat_q'), $from);
    if ($control === 'cat_menu')
        return mel_tv_is_from($from) ? mel_tv_menu($in) : mel_catv_menu($in, $from);
    if ($control === 'cat_src_menu')
        return mel_src_popup(mel_catv_lang(), 'cat_src', array('from' => $from, 'item' => mel_catv_in($in, 'item')));
    if ($control === 'cat_src')
        return mel_catv_src($in);
    if ($control === 'cat_part')
        return mel_catv_strip_enter(mel_catv_in($in, 'part'), $from, true);
    if ($control === 'cat_rows_menu')
        return mel_catv_rows_menu($from, mel_catv_in($in, 'sel'));
    if ($control === 'cat_rows_edit')
        return mel_catv_rows_edit($from, mel_catv_in($in, 'sel'), mel_catv_in($in, 'g'));
    if ($control === 'cat_rows_apply')
        return mel_catv_rows_apply($in, $from);
    if ($control === 'cat_hide')
        return mel_cp_hide_act($in);
    if ($control === 'cat_unhide')
        return mel_cp_unhide_act($from);
    if ($control === 'cat_mark')
        return mel_cp_mark($in);
    if ($control === 'cat_ml')
        return mel_ml_menu_act($in);
    if ($control === 'cat_fav')
        return mel_mlv_open(mel_catv_in($in, 'w'), $from);
    if ($control === 'cat_ml_import')
        return mel_mlv_import_ask($from);
    if ($control === 'cat_ml_import_go')
        return mel_mlv_import($from, mel_mlv_import_budget(mel_catv_in($in, 's')));
    return null;
}
