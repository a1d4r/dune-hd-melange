<?php
// Screens of the catalog: the rows (media_url "catalog"), a row in full
// ("cat_grid:<rid>", of a search "cat_grid:<rid>:<id of the search>"; the rows of 9 on r25,
// a grid on r24), search and its results ("cat_search:<id>"). Data: catalog.php,
// ENTER on a tile: catalog_card.php. rid: a short hash of the key of a row; the id of a
// tile is "<rid>:<id of the title>", so it stays the same after a rebuild or a restart.
// Types in the PHPDoc (AioCatList and others): phpstan-types.neon of the repository.

// r24: seconds for rows at an opening, with the refresh of the manifest: what does not
// make it stays hidden until the next one. A cold row takes up to 4.5 s, 6 rows 2.5 s.
define('AIO_CATV_OPEN_S', 3.0);
// r25: one row of get_folder_rows, a key of the user waits for it; the stubs of a row.
define('AIO_CATV_ROW_S', 5.0);
define('AIO_CATV_STUBS', 9);
// r25: after a failure of the server this many s no row asks it (aio_catv_is_down).
define('AIO_CATV_DOWN_S', 30);
// The timeout of get_folder_view and of a portion of the grid is not known (10 s by
// the SDK): all of an opening, the first manifest in it, and a portion stay under it.
define('AIO_CATV_OPEN_MAX_S', 7.0);
define('AIO_CATV_MANIFEST_S', 5);
define('AIO_CATV_GRID_S', 7.0);
// Less than this left: no more requests.
define('AIO_CATV_MIN_S', 0.3);
define('AIO_CATV_ROW_TILES', 20);
define('AIO_CATV_GRID_PORTION', 50);
// r25 "Show all": rows of 9 ($ROWS_NUM_COLS of movie_list_screen.php), 112 of them hold the
// 1000 tiles of AIOMetadata; the shell can not add rows later.
define('AIO_CATV_ALL_COLS', 9);
define('AIO_CATV_ALL_ROWS', 112);
// The tiles a call of get_folder_rows loads past the row after the first one asked: the end on
// the border of a row closes the rows past it in the same reply.
define('AIO_CATV_ALL_AHEAD', 9);
// The timer of a view with stubs: the focus run past the end onto a closed row is lost, the tick
// once the end is known builds the view anew (catalog_probe y9, y9b).
define('AIO_CATV_ALL_TICK_MS', 1500);
// The searches kept in memory: Back to the results of an older one, its tiles and "Show all" work.
define('AIO_CATV_SEARCHES', 3);
// The screens of rows whose cat_logo of the opening is kept (aio_catv_logo_of).
define('AIO_CATV_SCREENS', 16);
// The info panel, on the info_r of the native one (movie_helper.php of shell_ext), its colours
// and sizes of text: a title in TEXT_W, one line as large as fits from TITLE_PX down to
// TITLE_MIN_PX, else 2 lines of TITLE2_PX, its bottom at TITLE_BOTTOM (SUB_H higher over the
// English name); then lines of 30 px from LINES_Y (to fit TEXT_W the genres down to 26 px, the cast
// loses names), then the description. Names up to 197, descriptions up to 1096
// characters were seen. DESC_CHARS: of the text of an item of the grid.
define('AIO_CATV_PANE_W', 950);
define('AIO_CATV_PANE_H', 560);
define('AIO_CATV_TEXT_W', 800);
define('AIO_CATV_TITLE_PX', 88);
define('AIO_CATV_TITLE_MIN_PX', 60);
define('AIO_CATV_TITLE2_PX', 48);
define('AIO_CATV_TITLE_H', 120);
// TITLE_BOTTOM: PANE_Y + 216 = 256 is the bottom of the logo of a card: baked into card/v1 (AIO_CATV_CARD).
define('AIO_CATV_TITLE_BOTTOM', 216);
define('AIO_CATV_LINES_Y', 229);
define('AIO_CATV_LINE_PX', 30);
define('AIO_CATV_LINE_H', 46);
define('AIO_CATV_DATA_MIN_PX', 26);
define('AIO_CATV_TITLE_COLOR', '#FFFFFFE0');
define('AIO_CATV_TEXT_COLOR', '#FFAFAFA0');
define('AIO_CATV_CAST_COLOR', '#FFFFFF40');
// The numbers of the ratings in the line of facts: white as the title, the rest of it grey.
define('AIO_CATV_RATE_COLOR', AIO_CATV_TITLE_COLOR);
// The line of facts (aio_catv_facts_labels): the ratings and the Russian age marks in outlined
// plates (img/cat1_kp.png ...), PLATE_H high, their tops PLATE_DY under the top of the line: centred
// on the capitals of 30 px (their middle 27 px under the top of the label, device 07.11.2026). The
// text over them by the shell: of a rating the name grey PLATE_PAD from the left, the number white
// as far from the right (both in one, centred, if they do not fit so by font_w.php), the age centred.
// Widths: "КП 8.9", "IMDb 9.5", "18+" at 30 px by font_w.php, PLATE_PAD a side (the age 10 a side).
// Gaps: between plates, before the rest of the line (grey text), before the age.
define('AIO_CATV_PLATE_H', 40);
define('AIO_CATV_PLATE_DY', 7);
define('AIO_CATV_PLATE_PAD', 12);
define('AIO_CATV_KP_W', 117);
define('AIO_CATV_IMDB_W', 153);
define('AIO_CATV_AGE_W', 73);
define('AIO_CATV_PLATE_GAP', 12);
define('AIO_CATV_REST_GAP', 18);
define('AIO_CATV_AGE_GAP', 16);
// The English name under the title (info_ntitle of movie_helper.php): 36 px down to 20, 2 lines.
define('AIO_CATV_SUB_PX', 36);
// SUB_H: the height of the English name under a title of text.
define('AIO_CATV_SUB_H', 46);
define('AIO_CATV_SUB_MIN_PX', 20);
define('AIO_CATV_SUB_COLOR', '#FF808080');
define('AIO_CATV_DESC_CHARS', 360);
// The TMDB size of a background; w780 if the shell runs short of memory.
define('AIO_CATV_BG_SIZE', 'w1280');
// The panel: x of info_r. The strip of the row titles (the main screen, the results of a search of
// two groups and more) moves it to the right by 30, to CARD_X, where the card background of the
// server has the logo (aio_catv_card); a screen without the strip (a search of one group, "Show all")
// with the card background: info_r at CARD_X itself.
// CARD_X (PANE_X + 30) is the left of the logo of a card, PANE_Y + TITLE_BOTTOM its bottom: baked into card/v1 (AIO_CATV_CARD).
define('AIO_CATV_PANE_X', 100);
define('AIO_CATV_CARD_X', 130);
define('AIO_CATV_PANE_Y', 40);
define('AIO_CATV_ICONS', 'plugin_file://%shell_ext%/icons/');
// The icons of the shell for the search and the setup: on the strip and in POP UP.
define('AIO_CATV_ICON_SEARCH', 'xxx4.png');
define('AIO_CATV_ICON_SETUP', 'xxx5.png');
// Our pictures of the panel, img/cat<N>_*: N goes up when they change, as the shell keeps a
// picture by its URL even across a reinstall (plugins/melange_catalog_img.py of the kitchen). The
// mask: of the shell, but darker to the right, so the text column (TEXT_W) stays on the dark
// whatever the background; card0.jpg: the dark background of vod_r of the whole screen without a
// card background (aio_catv_card). cat2_mask.png: baked into card/v1 (AIO_CATV_CARD).
define('AIO_CATV_IMG', 'plugin_file://img/cat2_');
// The IMDb rating in the top left corner of a poster of a row (aio_catv_rate_stickers): a plate
// RATE_W x RATE_H at (RATE_PAD, RATE_PAD) of the poster 190x285, 70 % black, the N of rate<N> goes
// up when it changes, as of cat<N>; the number over it, sticker_text_size RATE_PX of the template.
define('AIO_CATV_RATE_PLATE', 'plugin_file://img/rate1_plate.png');
define('AIO_CATV_RATE_PAD', 8);
define('AIO_CATV_RATE_W', 52);
define('AIO_CATV_RATE_H', 32);
define('AIO_CATV_RATE_PX', 25);
// The card backgrounds of the server: <base>/<CARD>... (aio_catv_card). Its layout - the logo up to
// 780x200 at CARD_X, its bottom at PANE_Y + TITLE_BOTTOM (256), the mask cat2_mask.png - is baked
// into card/v1 of the server (addon/lib/posterCache/card.ts of the fork of AIOMetadata): a change
// after a release of the plugin = v2 in the path and in card.ts.
define('AIO_CATV_CARD', 'poster-cache/card/v1/');
// The strip on top of the main screen, as the native top menu: the tabs "Movies" and "Series"
// (rows by their type), the icons of search and settings of the shell. The id of its row; the
// ids of its parts are "tab_<x>", no ':' (an id with one is a tile). A part in focus far right
// scrolls the whole strip: on the device a part at 1606-1670 did not (its frame on the part), one
// at 1640-1704 did. So the gear ends at 1660 and its frame, with the margins, at 1670.
define('AIO_CATV_STRIP', 'tabs');
// The header_id of a row of tiles: this and its rid (the shell names the headers of the rows without
// one by their number, "H0" is the strip). POP UP on a header brings it as selected_header_id
// (genre_rows_screen.php r25: header_id = the id of the list the dialog of the rows edits).
define('AIO_CATV_HEADER', 'h:');
// The header of the strip, as the shell names it: the strip has no header_id and is the first row
// with a title, so "H0" (device r25: POP UP on "Menu" brings it, the sel_state of a switch of the
// tab has it). A header_id of the strip would change both; the other rows all have AIO_CATV_HEADER.
define('AIO_CATV_STRIP_HEADER', 'H0');
// The tabs as the native menu: text in caps, the open one brighter (the colours of the title and
// the text of the panel, as the native ones), a blue line under it. No focused variant: it does
// nothing in a row of GComps, only the frame of the shell shows the focus.
define('AIO_CATV_TAB_PX', 38);
define('AIO_CATV_UNDERLINE', '#FF3870FF');

class AioCat
{
    // Listings by rid: what the info panel and ENTER read. Memory of php_server
    // only; after a restart the rows come from the cache, the panel stays empty.
    /** @var array<string, AioCatList> */
    public static $lists = array();
    // The last AIO_CATV_SEARCHES searches, the oldest first: by its id, the rids of its rows.
    /** @var array<string, list<string>> */
    public static $searches = array();
    // r25: when a row came empty, by rid. It is hidden at the openings of the next hour.
    /** @var array<string, int> */
    public static $empty = array();
    // r25: the copy of the manifest is old, get_folder_rows refreshes it.
    /** @var bool */
    public static $manifest_old = false;
    // r25: when a request of a row failed for the server (net, rate, server), 0: it did not.
    /** @var int */
    public static $down = 0;
    // r25: the tiles of the flagged rows as this screen shows them, by rid and id: the shell
    // throws away a reply cancelled by ENTER, while the listing has already moved on.
    /** @var array<string, array<int|string, AioCatTile>> */
    public static $on_screen = array();
    // The language of the last screen of rows ('': not read yet), aio_catv_ui_lang.
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
    // cat_logo of the screen of rows built or asked for now (aio_catv_logo_of; null: none yet, the
    // settings): its vod_r and the ids of its tiles are by it.
    /** @var bool|null */
    public static $logo = null;
    // cat_logo of the last AIO_CATV_SCREENS screens of rows at their openings, by media_url.
    /** @var array<string, bool> */
    public static $logo_of = array();
    // The same of cat_rating (aio_catv_rating_on): a screen has the plates on all its tiles or on none.
    /** @var bool|null */
    public static $rating = null;
    /** @var array<string, bool> */
    public static $rating_of = array();
}

// ru or en, as the settings page reads it (cgi/lib/http.php).
/**
 * @return string
 */
function aio_catv_lang()
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
function aio_catv_ui_lang()
{
    if (AioCat::$lang === '')
        AioCat::$lang = aio_catv_lang();
    return AioCat::$lang;
}

/**
 * @param stdClass $in
 * @param string $k
 * @return string
 */
function aio_catv_in($in, $k)
{
    return isset($in->$k) && is_string($in->$k) ? $in->$k : '';
}

/**
 * @param AioCatRow $row
 * @return string
 */
function aio_catv_rid($row)
{
    // A row of a search: a rid of its own for every search (sid), the key is one for all.
    return substr(md5($row['key'] . (isset($row['sid']) ? ':' . $row['sid'] : '')), 0, 8);
}

// A listing and all its tiles so far, by the id of a tile. stale: of a copy, a request failed.
/**
 * @param AioCatState $st
 * @param list<AioCatTile> $tiles
 * @param bool $stale
 * @return AioCatList
 */
function aio_catv_list($st, $tiles, $stale)
{
    $list = array('st' => $st, 'tiles' => array(), 'stale' => $stale);
    foreach ($tiles as $t)
        $list['tiles'][$t['id']] = $t;
    return $list;
}

// A listing from the saved first page, as if it was just got.
/**
 * @param AioCatRow $row
 * @param AioCatCached $c
 * @return AioCatList
 */
function aio_catv_list_copy($row, $c)
{
    $copy = aio_cat_state_of_copy($row, $c);
    return aio_catv_list($copy['st'], $copy['tiles'], false);
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
function aio_catv_id_mode($id)
{
    if (strpos($id, 'c:') === 0 || strpos($id, 'f:') === 0)
        return array($id[0] === 'c', strval(substr($id, 2)));
    return array(false, $id);
}

// $id of a tile or of "Show all" with the mode of the screen (aio_catv_id_mode).
/**
 * @param string $id
 * @return string
 */
function aio_catv_tile_id($id)
{
    return (aio_catv_logo_on() ? 'c:' : 'f:') . $id;
}

// The tile of an id of the screen, memory only, or null.
/**
 * @param string $id
 * @return AioCatTile|null
 */
function aio_catv_tile($id)
{
    list(, $id) = aio_catv_id_mode($id);
    $p = explode(':', $id, 2);
    if (count($p) !== 2)
        return null;
    if (isset(AioCat::$lists[$p[0]]['tiles'][$p[1]]))
        return AioCat::$lists[$p[0]]['tiles'][$p[1]];
    return isset(AioCat::$on_screen[$p[0]][$p[1]]) ? AioCat::$on_screen[$p[0]][$p[1]] : null;
}

// --- Pictures: the size is in the address; a picture of another type or width is fine.

// Never empty.
/**
 * @param AioCatTile $t
 * @return string
 */
function aio_catv_poster($t)
{
    $u = aio_img(str_replace('/poster-cache/poster/', '/poster-cache/w342/poster/', $t['poster']));
    return $u !== '' ? $u : AIO_CATV_ICONS . 'no_movie_poster.png';
}

/**
 * @param AioCatTile $t
 * @return string
 */
function aio_catv_background($t)
{
    $u = aio_img(strval(preg_replace('~/t/p/[a-z0-9_]+/~', '/t/p/' . AIO_CATV_BG_SIZE . '/', $t['background'])));
    return $u !== '' ? $u : AIO_CATV_ICONS . 'bg0.jpg';
}

// cat_logo ("Логотип фильма вместо названия" of the page) of the screen of rows built or asked for now.
/**
 * @return bool
 */
function aio_catv_logo_on()
{
    return AioCat::$logo !== null ? AioCat::$logo : Aio::$settings['cat_logo'];
}

// cat_logo of the screen of rows $url: at its opening ($open, aio_catv_folder_view) of the settings,
// later (get_folder_rows: the rows of "Show all" as it scrolls) the same as then, so all its tiles
// are of the mode of its vod_r. A screen not among the last AIO_CATV_SCREENS (php_server restarted):
// the settings now. cat_rating the same way, so the rows of a screen are all with the rating or without.
/**
 * @param string $url
 * @param bool $open
 * @return void
 */
function aio_catv_logo_of($url, $open)
{
    if ($open || !isset(AioCat::$logo_of[$url]))
    {
        unset(AioCat::$logo_of[$url]);
        AioCat::$logo_of[$url] = Aio::$settings['cat_logo'];
        if (count(AioCat::$logo_of) > AIO_CATV_SCREENS)
            array_shift(AioCat::$logo_of);
        unset(AioCat::$rating_of[$url]);
        AioCat::$rating_of[$url] = Aio::$settings['cat_rating'];
        if (count(AioCat::$rating_of) > AIO_CATV_SCREENS)
            array_shift(AioCat::$rating_of);
    }
    AioCat::$logo = AioCat::$logo_of[$url];
    AioCat::$rating = AioCat::$rating_of[$url];
}

// cat_rating ("Рейтинг IMDb на постерах" of the page) of the screen of rows built or asked for now.
/**
 * @return bool
 */
function aio_catv_rating_on()
{
    return AioCat::$rating !== null ? AioCat::$rating : Aio::$settings['cat_rating'];
}

// cat_logo on: vod_r of the whole screen (the card background is of it), else on the right as the
// native one. The panel at PANE_X, at CARD_X on a screen without the strip of the row titles ($titles).
/**
 * @param bool $titles
 * @return array{info_r: array{w: int, h: int, x: int, y: int}, vod_r: array{w: int, h: int, x: int, y: int}}
 */
function aio_catv_pane_r($titles)
{
    $on = aio_catv_logo_on();
    return array('info_r' => array('w' => AIO_CATV_PANE_W, 'h' => AIO_CATV_PANE_H,
            'x' => $on && !$titles ? AIO_CATV_CARD_X : AIO_CATV_PANE_X, 'y' => AIO_CATV_PANE_Y),
        'vod_r' => $on ? array('w' => 1920, 'h' => 1080, 'x' => 0, 'y' => 0) : array('w' => 1280, 'h' => 720, 'x' => 640, 'y' => 0));
}

// The background of the panel of tile $t (null: none, "Show all"), the one while it loads or if it
// fails (nl_bg_url), its mask, and whether it has the logo in place of the title, in the mode $on of
// the id (aio_catv_id_mode). cat_logo off: the fanart (aio_catv_background) on the right under our
// mask. On: the card background of the server, the fanart, our mask and the logo in one picture of the
// screen, so the panel comes with all of it at once (the shell switches the panel once the background
// has loaded, its pictures load after: experiments/bg_probe of the kitchen). Of the cache of
// backgrounds of the server only, <base>/poster-cache/background/<TMDB address>: the card is
// <base>/AIO_CATV_CARD[n/<logo>/]<fanart>, TMDB file names; n: no English name under the logo.
// The logo: the Russian one aio_cat_tile kept. No such fanart (none, another
// server, not of TMDB) or no tile: our dark card0.jpg, no mask; the same while a card loads.
/**
 * @param AioCatTile|null $t
 * @param bool $on
 * @return array{bg: string, nl: string|null, mask: string|null, logo: bool}
 */
function aio_catv_card($t, $on)
{
    if (!$on)
        return array('bg' => $t ? aio_catv_background($t) : AIO_CATV_ICONS . 'bg0.jpg', 'nl' => null, 'mask' => AIO_CATV_IMG . 'mask.png',
            'logo' => false);
    $none = AIO_CATV_IMG . 'card0.jpg';
    if (!$t || !preg_match('~^(https?://[^\s?#]+?)/poster-cache/background/' . AIO_CAT_TMDB . '([A-Za-z0-9]{10,40}\.jpg)$~D',
        $t['background'], $f))
        return array('bg' => $none, 'nl' => $none, 'mask' => null, 'logo' => false);
    $logo = $t['logo'] !== '' ? basename($t['logo']) : '';
    $path = $logo !== '' ? "n/$logo/$f[2]" : $f[2];
    return array('bg' => aio_img("$f[1]/" . AIO_CATV_CARD . $path), 'nl' => $none, 'mask' => null, 'logo' => $logo !== '');
}

// --- What to give at an opening.

// The server failed less than AIO_CATV_DOWN_S ago. The clock going back is not trusted.
/**
 * @return bool
 */
function aio_catv_is_down()
{
    $since = time() - AioCat::$down;
    return AioCat::$down > 0 && $since >= 0 && $since < AIO_CATV_DOWN_S;
}

// The first page of a row asked for anew: with tiles, into AioCat::$lists (of a copy
// after a failure: stale), else what is there stays; an empty row is remembered.
// $timeout_down false: a timeout is a slow row, not the server down.
/**
 * @param AioCatRow $row
 * @param float $limit
 * @param bool $timeout_down
 * @return AioCatResult
 */
function aio_catv_load_first($row, $limit, $timeout_down = true)
{
    $rid = aio_catv_rid($row);
    $st = aio_cat_row_state($row);
    $res = aio_cat_more($st, $limit);
    if ($res['tiles'])
        AioCat::$lists[$rid] = aio_catv_list($st, $res['tiles'], $res['stale']);
    else if ($res['end'] && $res['err'] === '')
        AioCat::$empty[$rid] = time();
    $down = $timeout_down ? array('net', 'timeout', 'rate', 'server') : array('net', 'rate', 'server');
    AioCat::$down = in_array($res['err'], $down, true) ? time() : 0;
    return $res;
}

// The rows $rows for a new screen, into AioCat::$lists. At once: listings in memory
// still fresh, then the saved first pages of any age. In $limit s, one by one: the rows
// without a copy, then the old copies; on a failure of the server no more requests. No
// tile: the row stays out of the lists.
/**
 * @param list<AioCatRow> $rows
 * @param float $limit
 * @return void
 */
function aio_catv_open($rows, $limit)
{
    $none = array();
    $old = array();
    foreach ($rows as $row)
    {
        $rid = aio_catv_rid($row);
        if (isset(AioCat::$lists[$rid]) && aio_cat_state_fresh(AioCat::$lists[$rid]['st']))
            continue;
        unset(AioCat::$lists[$rid]);
        $c = aio_cat_page_cached($row, 0);
        if ($c)
            AioCat::$lists[$rid] = aio_catv_list_copy($row, $c);
        if (!$c)
            $none[$rid] = $row;
        else if (!aio_cat_is_fresh($c))
            $old[$rid] = $row;
    }
    $start = microtime(true);
    $failed = false;
    foreach ($none + $old as $rid => $row)
    {
        $left = $limit - (microtime(true) - $start);
        if ($left < AIO_CATV_MIN_S)
            break;
        $res = aio_catv_load_first($row, $left);
        if ($res['tiles'] && !$res['stale'])
            unset($old[$rid]);
        $failed = $failed || $res['err'] !== '';
        if (aio_catv_is_down())
            break;
    }
    if ($failed)
    {
        foreach ($old as $rid => $row)
        {
            $list = AioCat::$lists[$rid];
            $list['stale'] = true;
            AioCat::$lists[$rid] = $list;
        }
    }
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
function aio_catv_item($id, $caption, $icon)
{
    return array(
        PluginRegularItem::id => $id,
        PluginRegularItem::icon_url => is_string($icon) && $icon !== '' ? $icon : AIO_CATV_ICONS . 'no_movie_poster.png',
        PluginRegularItem::caption => $caption);
}

// The tile of a title in a row: its poster, the IMDb rating in the corner if it has one and
// cat_rating of the screen is on (aio_catv_rating_on).
/**
 * @param string $rid
 * @param AioCatTile $t
 * @return array<string, mixed>
 */
function aio_catv_title_item($rid, $t)
{
    $item = aio_catv_item(aio_catv_tile_id("$rid:{$t['id']}"), $t['name'], aio_catv_poster($t));
    $stickers = aio_catv_rating_on() ? aio_catv_rate_stickers($t['imdbRating']) : array();
    if ($stickers)
        $item[PluginRegularItem::stickers] = $stickers;
    return $item;
}

// The plate and the number of an IMDb rating ("8.6", "10"), none for none. In pixels of the poster
// 190x285, the shell scales them in focus. The shell puts the text at the left of its r and a pixel
// over the middle of it: the r of the text is a pixel lower than the plate, its x centres the
// number by font_w.php.
/**
 * @param string $rating
 * @return list<array<string, mixed>>
 */
function aio_catv_rate_stickers($rating)
{
    // "0.04" is "0.0": none, as of kpRating.
    $s = sprintf('%.1F', (float) $rating);
    if ((float) $s <= 0)
        return array();
    if ($s === '10.0')
        $s = '10';
    $p = AIO_CATV_RATE_PAD;
    $x = $p + intval(round((AIO_CATV_RATE_W - aio_gc_sum($s, AIO_CATV_RATE_PX)) / 2));
    return array(
        array(PluginRegularSticker::r => array('w' => AIO_CATV_RATE_W, 'h' => AIO_CATV_RATE_H, 'x' => $p, 'y' => $p),
            PluginRegularSticker::icon_url => AIO_CATV_RATE_PLATE),
        array(PluginRegularSticker::r => array('w' => $p + AIO_CATV_RATE_W - $x, 'h' => AIO_CATV_RATE_H, 'x' => $x, 'y' => $p + 1),
            PluginRegularSticker::text => $s));
}

// The title over the row of group "row:$g".
/**
 * @param string $g
 * @param string $caption
 * @return array<string, mixed>
 */
function aio_catv_title_row($g, $caption)
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
// instead of them. $options: of the row of tiles.
/**
 * @param AioCatList $list
 * @param string $lang
 * @param list<array<string, mixed>>|null $items
 * @param int $options
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_catv_row_defs($list, $lang, $items = null, $options = 0)
{
    $row = $list['st']['row'];
    $rid = aio_catv_rid($row);
    $more = '';
    // The no-break space at the end of the name of a search row (cat_movies) not before the mark.
    $title = $list['stale'] ? strval(preg_replace('/\x{00A0}$/u', '', $row['name'])) . ' (' . aio_tr($lang, 'cat_saved') . ')' : $row['name'];
    if ($items === null)
    {
        $items = array();
        foreach (array_slice($list['tiles'], 0, AIO_CATV_ROW_TILES) as $t)
            $items[] = aio_catv_title_item($rid, $t);
        if ($list['st']['end'] === '' || count($list['tiles']) > AIO_CATV_ROW_TILES)
        {
            // Of a search: and its id, as in cat_grid:.
            $more = aio_catv_tile_id(isset($row['sid']) ? "more:$rid:{$row['sid']}" : "more:$rid");
            $items[] = aio_catv_item($more, aio_tr($lang, 'cat_more'), AIO_CATV_ICONS . 'more.png');
        }
    }
    $defs = array(
        aio_catv_title_row($rid, $title),
        array(
            PluginRow::type => PLUGIN_ROW_TYPE_REGULAR,
            PluginRow::id => "items:$rid",
            PluginRow::title => $title,
            PluginRow::header_id => AIO_CATV_HEADER . $rid,
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
        $defs[1][PluginRow::show_all_action] = aio_input('cat_all', array('cat_more' => $more));
    return $defs;
}

// --- r25: rows the shell asks for (get_folder_rows).

// The flag of a row to ask for; 0: r24, no such operation, the rows load at an opening.
/**
 * @return int
 */
function aio_catv_loading_flag()
{
    return defined('PLUGIN_OP_GET_FOLDER_ROWS') && defined('PLUGIN_ROW_OPT_LOADING') ? PLUGIN_ROW_OPT_LOADING : 0;
}

/**
 * @param string $rid
 * @return bool
 */
function aio_catv_is_empty($rid)
{
    return isset(AioCat::$empty[$rid]) && aio_cat_fresh_since(AioCat::$empty[$rid]);
}

// A tile of a message: "fail:<rid>" (cat_row_failed, ENTER tries again) or "empty:<rid>" (cat_row_empty).
/**
 * @param string $rid
 * @param string $note
 * @param string $lang
 * @return array<string, string>
 */
function aio_catv_note_item($rid, $note, $lang)
{
    return aio_catv_item(($note === 'cat_row_empty' ? 'empty:' : 'fail:') . $rid, aio_tr($lang, $note), AIO_CATV_ICONS . 'no_movie_poster.png');
}

// A row without tiles of titles. $note '': stubs for the shell to ask for. Else one tile
// with this message and no flag: a row hidden on the screen takes the focus away with it.
/**
 * @param AioCatRow $row
 * @param string $lang
 * @param string $note
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_catv_row_note($row, $lang, $note)
{
    $rid = aio_catv_rid($row);
    $list = aio_catv_list(aio_cat_row_state($row), array(), false);
    if ($note !== '')
        return aio_catv_row_defs($list, $lang, array(aio_catv_note_item($rid, $note, $lang)));
    $items = array();
    for ($i = 0; $i < AIO_CATV_STUBS; $i++)
        $items[] = aio_catv_item("stub:$rid:$i", aio_tr($lang, 'cat_row_loading'), AIO_CATV_ICONS . 'loading_movie_poster.png');
    return aio_catv_row_defs($list, $lang, $items, aio_catv_loading_flag());
}

// The row $row now, never flagged (such a row is not asked for again): from memory or the
// cache when it can be, so a repeat of a call the shell cancelled (ENTER) is at once and
// the same; a new state every time, its skip never moves on. Else asked in $limit s; the
// server down: no request, what memory has is a saved copy. No tiles: one tile "empty" or
// "did not load". -> its title and its row.
/**
 * @param AioCatRow $row
 * @param string $lang
 * @param float $limit
 * @param bool $timeout_down
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_catv_row_now($row, $lang, $limit, $timeout_down = true)
{
    $rid = aio_catv_rid($row);
    $ready = isset(AioCat::$lists[$rid]) && AioCat::$lists[$rid]['tiles'] && aio_cat_state_fresh(AioCat::$lists[$rid]['st']);
    if (!$ready && !aio_catv_is_empty($rid) && !aio_catv_is_down())
        aio_catv_load_first($row, $limit, $timeout_down);
    if (isset(AioCat::$lists[$rid]) && AioCat::$lists[$rid]['tiles'])
    {
        if (!aio_cat_state_fresh(AioCat::$lists[$rid]['st']))
            AioCat::$lists[$rid]['stale'] = true;
        return aio_catv_row_defs(AioCat::$lists[$rid], $lang);
    }
    return aio_catv_row_note($row, $lang, aio_catv_is_empty($rid) ? 'cat_row_empty' : 'cat_row_failed');
}

// The row $rid for the shell (aio_catv_row_now) -> the row and, only for a saved copy, its
// title: the shell replaces any row of a reply by its id, and that is the only way the
// title gets the mark.
/**
 * @param string $rid
 * @param string $lang
 * @return non-empty-list<array<string, mixed>>
 */
function aio_catv_row_answer($rid, $lang)
{
    $row = isset(AioCat::$lists[$rid]) ? AioCat::$lists[$rid]['st']['row'] : aio_catv_row_by_rid($rid);
    if (!$row)
    {
        aio_log('catalog: row: unknown id');
        $row = array('key' => '', 'type' => 'movie', 'id' => '', 'name' => aio_tr($lang, 'cat_title'), 'genre' => '', 'home' => false, 'group' => '');
        $defs = aio_catv_row_note($row, $lang, 'cat_row_failed');
        return array($defs[1]);
    }
    $defs = aio_catv_row_now($row, $lang, AIO_CATV_ROW_S);
    $saved = isset(AioCat::$lists[$rid]) && AioCat::$lists[$rid]['tiles'] && AioCat::$lists[$rid]['stale'];
    return $saved ? array($defs[1], $defs[0]) : array($defs[1]);
}

// get_folder_rows: "Show all": aio_catv_all_rows. Else one row a call (a saved copy: and
// its title), the shell asks for the rest next. Always a row with the id asked: on a reply without rows the shell asks again
// every ~1 ms. The refresh of the manifest takes the time left of the call: an opening
// does not wait for it.
/**
 * @param stdClass $in
 * @return array{rows: list<array<string, mixed>>}
 */
function aio_catv_rows_op($in)
{
    $start = microtime(true);
    $ids = isset($in->row_ids) && is_array($in->row_ids) ? $in->row_ids : array();
    $url = aio_catv_in($in, 'media_url');
    aio_catv_logo_of($url, false);
    if (strpos($url, 'cat_grid:') === 0)
        return array('rows' => aio_catv_all_rows($url, $ids));
    $id = isset($ids[0]) && is_string($ids[0]) ? $ids[0] : 'items:';
    $rid = strpos($id, 'items:') === 0 ? strval(substr($id, strlen('items:'))) : '';
    $rows = aio_catv_row_answer($rid, aio_catv_lang());
    // The id and the header asked, of an unknown row too.
    $rows[0][PluginRow::id] = $id;
    $rows[0][PluginRow::header_id] = AIO_CATV_HEADER . $rid;
    $left = AIO_CATV_ROW_S - (microtime(true) - $start);
    if (AioCat::$manifest_old && $left >= 1 && !aio_catv_is_down())
    {
        AioCat::$manifest_old = false;
        aio_cat_manifest_fetch(intval($left));
    }
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
function aio_catv_item_vars($w, $h, $dx, $iw, $ih, $idy, $cdy, $color)
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
function aio_catv_item_tpl($m)
{
    return array(
        PluginRegularItemParams::def => aio_catv_item_vars(200, 302, 0, 190, 285, 7, 10, '#FFAFAFA0'),
        PluginRegularItemParams::sel => aio_catv_item_vars(200, 302, 0, 200, 300, 0, 10, '#FFFFFFE0'),
        PluginRegularItemParams::inactive => aio_catv_item_vars(200, 300, 5, 190, 285, 0, 0, '#00000000'),
        PluginRegularItemParams::loading_url => AIO_CATV_ICONS . 'loading_movie_poster.png',
        PluginRegularItemParams::load_failed_url => AIO_CATV_ICONS . 'no_movie_poster.png',
        PluginRegularItemParams::caption_max_num_lines => 3,
        PluginRegularItemParams::caption_line_spacing => 3,
        PluginRegularItemParams::sticker_text_size => AIO_CATV_RATE_PX,
        PluginRegularItemParams::sel_margins => array(
            PluginMargins::left => $m[0], PluginMargins::top => $m[1],
            PluginMargins::right => $m[2], PluginMargins::bottom => $m[3]));
}

// The actions of the rows. $from: 'results:<id of the search>' on the results of a search, a
// new search replaces them; else the address of the screen, for its "did not load" tile.
/**
 * @param string $from
 * @return array<string, AioAction>
 */
function aio_catv_actions($from)
{
    $p = array('from' => $from);
    $info = aio_input('cat_info');
    // As the sample proven on the device: no timeout and no dialog on a focus move.
    $info[GuiAction::data] = null;
    return array(
        GUI_EVENT_KEY_ENTER => aio_input('cat_enter', $p),
        GUI_EVENT_KEY_POPUP_MENU => aio_input('cat_menu', $p),
        GUI_EVENT_KEY_SEARCH => aio_input('cat_search', $p),
        GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE => $info);
}

// The focusable_def of a part of the strip. $m: sel_margins, left, top, right, bottom; the frame of
// the shell is a 5 px line across the edges of the part grown by them, with a glow of ~6 px.
/**
 * @param array{int, int, int, int} $m
 * @return array<string, mixed>
 */
function aio_catv_strip_focusable($m)
{
    return array(
        GCompFocusableDef::sel_margins => array(
            PluginMargins::left => $m[0], PluginMargins::top => $m[1], PluginMargins::right => $m[2], PluginMargins::bottom => $m[3]),
        GCompFocusableDef::background_url => null);
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
function aio_catv_strip_rows($tab, $lang)
{
    $defs = array();
    $items = array();
    // A label of 38 px is 38 high, the caps from 6 down to the baseline at 33: the baseline at
    // 83, ~150 of the screen as the native menu. "Series" 46 px right of the box of "Movies",
    // the words ~50 apart as in the native menu, in any language.
    foreach (array('movie' => 'cat_tab_movies', 'series' => 'cat_tab_series') as $type => $key)
    {
        $id = "tab_$type";
        $caption = aio_tr($lang, $key);
        $geom = $type === 'movie' ? aio_gc_at(-1, -1, 128, 50) : aio_gc_geom(-1, -1, aio_gc_next_right(46));
        $def = aio_gc_ttf($id, $geom, $caption, AIO_CATV_TAB_PX, $type === $tab ? AIO_CATV_TITLE_COLOR : AIO_CATV_TEXT_COLOR);
        $def[GComponentDef::focusable_def] = aio_catv_strip_focusable(array(16, 4, 16, 8));
        $defs[] = $def;
        $items[] = array(GCompItemDef::type => GCOMP_ITEM_REGULAR, GCompItemDef::id => $id,
            GCompItemDef::caption => $caption, GCompItemDef::icon_url => null);
    }
    // The line under the open tab: as wide as its word, 3 px under the baseline, inside the frame.
    $defs[] = aio_gc_rect(aio_gc_geom(0, 4, aio_gc_align(0, 36, true, false, 0, 0, 0, 0, "tab_$tab")), AIO_CATV_UNDERLINE);
    // The icons 75 apart as the native ones, their glyphs 48x48 at 8, 8 of the PNG; the gear
    // ends at 1660, its frame at 1670. Grey #AAAAAA as the native ones: the magnifier is white at
    // most (alpha 170), the gear #BBBBBB (alpha 232).
    foreach (array(array('search', 'cat_search', AIO_CATV_ICON_SEARCH, 1521, 170), array('setup', 'setup_menu', AIO_CATV_ICON_SETUP, 1596, 232)) as $t)
    {
        $id = "tab_{$t[0]}";
        $defs[] = array(
            GComponentDef::id => $id,
            GComponentDef::geom_def => aio_gc_at(64, 64, $t[3], 34),
            GComponentDef::focusable_def => aio_catv_strip_focusable(array(10, 2, 10, 3)),
            GComponentDef::kind => GCOMPONENT_IMAGE,
            GComponentDef::specific_def => array(
                GCompImageDef::url => AIO_CATV_ICONS . $t[2],
                GCompImageDef::keep_aspect_ratio => true,
                GCompImageDef::upscale_enabled => false,
                GCompImageDef::alpha => $t[4]));
        $items[] = array(GCompItemDef::type => GCOMP_ITEM_REGULAR, GCompItemDef::id => $id,
            GCompItemDef::caption => aio_tr($lang, $t[1]), GCompItemDef::icon_url => AIO_CATV_ICONS . $t[2]);
    }
    $title = aio_tr($lang, 'cat_tab_menu');
    return array(
        aio_catv_title_row(AIO_CATV_STRIP, $title),
        array(
            PluginRow::type => PLUGIN_ROW_TYPE_GCOMPS,
            PluginRow::id => AIO_CATV_STRIP,
            PluginRow::title => $title,
            PluginRow::group_id => 'row:' . AIO_CATV_STRIP,
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
function aio_catv_strip_sel($id)
{
    return "1\n0\n" . AIO_CATV_STRIP_HEADER . "\n" . AIO_CATV_STRIP . "\n$id\npri:1\n$id\n1\n1\n";
}

// The screen of the rows $defs. The key set of RowsFactory::pane r25, nulls too;
// row_dup_map: a literal, the r24 API has no such constant. A main screen ($from): the strip
// first, above min_row_index_for_y2 (laid out from the top, the rows right under it while it
// has the focus; under it the rows as before, the focused one on screen_y2).
/**
 * @param list<array<string, mixed>> $defs
 * @param mixed $sel_state
 * @param string $from
 * @return array<string, mixed>
 */
function aio_catv_rows_view($defs, $sel_state, $from)
{
    $rows = array(array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 10, PluginRow::inactive_height => 10));
    $tab = aio_catv_tab($from);
    if ($tab !== '')
        $rows = array_merge($rows, aio_catv_strip_rows($tab, aio_catv_ui_lang()));
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
    $r = aio_catv_pane_r($tab !== '' || $groups > 1);
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
        PluginRowsPane::regular_item_params_templates => array('common' => aio_catv_item_tpl(array(3, 3, 3, 4))));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
        PluginFolderView::data => array(
            PluginRowsFolderView::pane => $pane,
            PluginRowsFolderView::sel_state => is_string($sel_state) && $sel_state !== '' ? $sel_state : null,
            PluginRowsFolderView::actions => aio_catv_actions($from),
            PluginRowsFolderView::timer => null));
}

// --- Screens.

// The tab of a screen of the rows (aio_catv_main_url): 'movie' of "catalog" (the entry),
// "catalog:<n>" and "cat_tab:movie"; the type of "cat_tab:<type>", "cat_tab:<type>:<n>". '': not
// such a screen. "catalog" and "cat_tab:movie" are the same screen, only the focus at the opening
// differs: on the panel, on the tab.
/**
 * @param string $media_url
 * @return string
 */
function aio_catv_tab($media_url)
{
    if ($media_url === 'catalog' || strpos($media_url, 'catalog:') === 0)
        return 'movie';
    return preg_match('/^cat_tab:(movie|series)(?::|$)/', $media_url, $m) ? $m[1] : '';
}

// The address of the main screen of the tab $tab. No $n: "cat_tab:<type>", a switch of the tab,
// the focus on it (aio_catv_main_view). $n, a new id of a screen (cat_retry, aio_setup_refresh):
// "catalog:<n>" of the movies, "cat_tab:series:<n>", the focus where the shell puts it.
/**
 * @param string $tab
 * @param string $n
 * @return string
 */
function aio_catv_main_url($tab, $n = '')
{
    if ($n === '')
        return "cat_tab:$tab";
    return $tab === 'series' ? "cat_tab:series:$n" : "catalog:$n";
}

/**
 * @param string $media_url
 * @return bool
 */
function aio_catv_is_main($media_url)
{
    return aio_catv_tab($media_url) !== '';
}

/**
 * @param string $media_url
 * @return bool
 */
function aio_catv_is_screen($media_url)
{
    return aio_catv_is_main($media_url) || strpos($media_url, 'cat_grid:') === 0 || strpos($media_url, 'cat_search:') === 0;
}

/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_folder_view($media_url, $sel_state)
{
    aio_catv_logo_of($media_url, true);
    if (strpos($media_url, 'cat_grid:') === 0)
        return aio_catv_all_ok() ? aio_catv_all_view($media_url, $sel_state) : aio_catv_grid_view($media_url);
    if (strpos($media_url, 'cat_search:') === 0)
        return aio_catv_results_view(substr($media_url, strlen('cat_search:')), $sel_state);
    return aio_catv_main_view($media_url, $sel_state);
}

// A message instead of an empty screen $from, and a way to try again.
/**
 * @param string $key
 * @param string $lang
 * @param string $from
 * @return array<string, mixed>
 */
function aio_catv_message_view($key, $lang, $from)
{
    aio_log("catalog: screen: $key");
    return array(
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::data => array(
            PluginControlsFolderView::defs => array(
                aio_setup_label(null, aio_tr($lang, $key)),
                aio_setup_button('retry', aio_tr($lang, 'cat_retry'), aio_input('cat_retry', array('from' => $from)))),
            PluginControlsFolderView::initial_sel_ndx => -1));
}

// The manifest does not hold the screen: a copy of any age goes; no copy: one request.
// r25: the first row now (aio_catv_row_now), the others: a fresh copy ready, an old one
// flagged, none: stubs; get_folder_rows asks for the flagged ones and refreshes an old
// manifest in the time left (none flagged: here, as r24). r24: the rows and the manifest
// load here, in AIO_CATV_OPEN_S. Only the rows of the tab of $media_url, in the order of the user.
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_main_view($media_url, $sel_state)
{
    $start = microtime(true);
    AioCat::$on_screen = array();
    $lang = aio_catv_lang();
    AioCat::$lang = $lang;
    $tab = aio_catv_tab($media_url);
    // A switch of the tab (aio_catv_strip_enter): the shell brings no sel_state, the focus goes
    // to the tab pressed. Else none of ours: at an opening the focus is on the panel.
    if ((!is_string($sel_state) || $sel_state === '') && $media_url === aio_catv_main_url($tab))
        $sel_state = aio_catv_strip_sel("tab_$tab");
    $copy = aio_cat_manifest_copy();
    $rows = $copy['rows'];
    $fresh = aio_cat_is_fresh($copy);
    if (!$rows)
    {
        $new = aio_cat_manifest_fetch(AIO_CATV_MANIFEST_S);
        $rows = $new['rows'];
        $fresh = true;
        if (!$rows)
            return aio_catv_message_view('cat_unavailable', $lang, $media_url);
    }
    $rows = aio_cat_user_rows($rows, Aio::$settings['cat_rows'], $tab);
    if (!$rows)
    {
        aio_log("catalog: screen: $tab: no rows");
        return aio_catv_rows_view(aio_catv_tab_empty($tab, $lang, false), $sel_state, $media_url);
    }
    $loading = aio_catv_loading_flag();
    $defs = array();
    $asked = false;
    $shown = array();
    foreach ($rows as $row)
    {
        if (!$loading || !aio_catv_is_empty(aio_catv_rid($row)))
            $shown[] = $row;
    }
    // r25: all the rows of the tab came empty within the hour: the strip stays, not "did not load".
    if (!$shown)
    {
        aio_log("catalog: screen: $tab: all " . count($rows) . ' rows empty');
        return aio_catv_rows_view(aio_catv_tab_empty($tab, $lang, true), $sel_state, $media_url);
    }
    $left = AIO_CATV_OPEN_MAX_S - (microtime(true) - $start);
    aio_catv_open($shown, $loading ? 0 : min(AIO_CATV_OPEN_S, $left));
    foreach ($shown as $i => $row)
    {
        $rid = aio_catv_rid($row);
        // A flagged first row replaced under the focus on the headers covers the rows under it (device, 0.50.0).
        if ($loading && $i === 0)
        {
            $limit = max(AIO_CATV_MIN_S, min(AIO_CATV_ROW_S, AIO_CATV_OPEN_MAX_S - (microtime(true) - $start)));
            $defs = array_merge($defs, aio_catv_row_now($row, $lang, $limit, false));
        }
        else if (isset(AioCat::$lists[$rid]) && AioCat::$lists[$rid]['tiles'])
        {
            $flag = aio_cat_state_fresh(AioCat::$lists[$rid]['st']) ? 0 : $loading;
            if ($flag)
                AioCat::$on_screen[$rid] = AioCat::$lists[$rid]['tiles'];
            $defs = array_merge($defs, aio_catv_row_defs(AioCat::$lists[$rid], $lang, null, $flag));
            $asked = $asked || $flag;
        }
        else if ($loading)
        {
            $defs = array_merge($defs, aio_catv_row_note($row, $lang, ''));
            $asked = true;
        }
    }
    AioCat::$manifest_old = $asked && !$fresh;
    $left = AIO_CATV_OPEN_S - (microtime(true) - $start);
    if (!$asked && !$fresh && $left >= 1 && !aio_catv_is_down())
        aio_cat_manifest_fetch(intval($left));
    aio_log(sprintf('catalog: screen: %s: %d rows of %d, %.0f ms', $tab, count($defs) / 2, count($rows), (microtime(true) - $start) * 1000));
    return $defs ? aio_catv_rows_view($defs, $sel_state, $media_url) : aio_catv_message_view('cat_unavailable', $lang, $media_url);
}

// The row of the tab $tab without a row to show (aio_catv_tab_empty; its header: aio_catv_menu).
/**
 * @param string $tab
 * @param string $lang
 * @return AioCatRow
 */
function aio_catv_tab_empty_row($tab, $lang)
{
    return array('key' => "tab/$tab", 'type' => $tab, 'id' => 'tab', 'name' => aio_tr($lang, $tab === 'movie' ? 'cat_movies' : 'cat_series'),
        'genre' => '', 'home' => false, 'group' => '');
}

// The tab $tab without a row to show: under the strip one tile that says why. $retry: its rows
// came empty (r25, hidden for an hour), "retry:<rid>", ENTER is "Retry"; else the manifest has
// none of its type to show (none chosen and no start row of it, aio_cat_user_rows), "empty:<rid>",
// ENTER: nothing.
/**
 * @param string $tab
 * @param string $lang
 * @param bool $retry
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_catv_tab_empty($tab, $lang, $retry)
{
    $row = aio_catv_tab_empty_row($tab, $lang);
    $rid = aio_catv_rid($row);
    $list = aio_catv_list(aio_cat_row_state($row), array(), false);
    $item = $retry ? aio_catv_item("retry:$rid", aio_tr($lang, 'cat_tab_retry'), AIO_CATV_ICONS . 'no_movie_poster.png') :
        aio_catv_item("empty:$rid", aio_tr($lang, 'cat_tab_empty'), AIO_CATV_ICONS . 'no_movie_poster.png');
    return aio_catv_row_defs($list, $lang, array($item));
}

/**
 * @param string $id
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_results_view($id, $sel_state)
{
    $lang = aio_catv_lang();
    AioCat::$lang = $lang;
    $defs = array();
    foreach (isset(AioCat::$searches[$id]) ? AioCat::$searches[$id] : array() as $rid)
    {
        if (isset(AioCat::$lists[$rid]))
            $defs = array_merge($defs, aio_catv_row_defs(AioCat::$lists[$rid], $lang));
    }
    if (!$defs)
    {
        aio_log('catalog: search screen outdated');
        return aio_lines_view(array(aio_expired_item()));
    }
    return aio_catv_rows_view($defs, $sel_state, "results:$id");
}

/**
 * @param string $media_url
 * @param string $caption
 * @return AioAction
 */
function aio_catv_open_act($media_url, $caption)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
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
function aio_catv_country($c, $lang)
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
function aio_catv_runtime($r, $lang)
{
    $min = aio_cat_runtime_min($r);
    $parts = array();
    if ($min >= 60)
        $parts[] = str_replace('{n}', strval(intval($min / 60)), aio_tr($lang, 'cat_len_h'));
    if ($min % 60 > 0)
        $parts[] = str_replace('{n}', strval($min % 60), aio_tr($lang, 'cat_len_m'));
    return implode(' ', $parts);
}

// The genres of a tile as the lines show them: the compound genres of TMDB series short (the
// server names them in Russian in any interface), with a capital, no repeat in another case,
// the first 2.
/**
 * @param AioCatTile $t
 * @return list<string>
 */
function aio_catv_genres($t)
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
 * @param AioCatTile $t
 * @param string $lang
 * @return array{rates: list<array{string, string, string}>, rest: list<string>, age: string}
 */
function aio_catv_fact_parts($t, $lang)
{
    $rates = array();
    if ($t['kpRating'] !== '')
        $rates[] = array('kp', aio_tr($lang, 'cat_rate_kp'), $t['kpRating']);
    $rating = (float) $t['imdbRating'];
    if ($rating > 0)
        $rates[] = array('imdb', aio_tr($lang, 'cat_rate_imdb'), sprintf('%.1F', $rating));
    $rest = array_filter(array($t['type'] === 'series' ? aio_tr($lang, 'cat_kind_series') : '', $t['year'],
        aio_catv_runtime($t['runtime'], $lang)), 'strlen');
    return array('rates' => $rates, 'rest' => array_values($rest), 'age' => $t['age']);
}

// The facts as text, a part a string: "КП 7.7", "IMDb 7.3", "2020", "1 ч 53 мин", "18+".
/**
 * @param array{rates: list<array{string, string, string}>, rest: list<string>, age: string} $parts
 * @return list<string>
 */
function aio_catv_fact_texts($parts)
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
 * @param AioCatTile $t
 * @param string $lang
 * @return list<array{string, string, string}>
 */
function aio_catv_facts($t, $lang)
{
    static $ages = array('0+', '6+', '12+', '16+', '18+');
    $p = aio_catv_fact_parts($t, $lang);
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
// aio_catv_genres, a second one in Cyrillic small ("Комедия, криминал"; "Fantasy, Comedy" as it
// is), the country of the interface; cleaned.
/**
 * @param AioCatTile $t
 * @param string $lang
 * @return array{list<string>, string}
 */
function aio_catv_genre_parts($t, $lang)
{
    $g = array_map('aio_clean', aio_catv_genres($t));
    foreach ($g as $i => $x)
    {
        if ($i && preg_match('/^[\x{0400}-\x{04FF}]/u', $x))
            $g[$i] = mb_strtolower($x, 'UTF-8');
    }
    return array($g, aio_clean(aio_catv_country($t['country'], $lang)));
}

// The second line of data of the panel: the genres, then the country, fitted to TEXT_W. What
// does not fit at DATA_MIN_PX goes: the country first, then the second genre; aio_catv_fit
// cuts the rest.
/**
 * @param AioCatTile $t
 * @param string $lang
 * @return string
 */
function aio_catv_genre_line($t, $lang)
{
    list($g, $c) = aio_catv_genre_parts($t, $lang);
    $line = '';
    foreach (array(array($g, $c), array($g, ''), array(array_slice($g, 0, 1), '')) as $v)
    {
        $line = implode(' · ', array_filter(array(implode(', ', $v[0]), $v[1]), 'strlen'));
        if (aio_gc_w($line, AIO_CATV_DATA_MIN_PX) <= AIO_CATV_TEXT_W)
            break;
    }
    return $line;
}

// The cast of the panel: the names as fit TEXT_W at 30 px, the last ones dropped whole, one at
// least; one too long is cut with "…".
/**
 * @param AioCatTile $t
 * @return string
 */
function aio_catv_cast_line($t)
{
    $names = array_map('aio_clean', $t['cast']);
    while (count($names) > 1 && aio_gc_w(implode(', ', $names), AIO_CATV_LINE_PX) > AIO_CATV_TEXT_W)
        array_pop($names);
    return aio_gc_cut_w(implode(', ', $names), AIO_CATV_LINE_PX, AIO_CATV_TEXT_W);
}

// An item of the grid: the lines of the panel in one, "·" between the parts.
/**
 * @param string $rid
 * @param AioCatTile $t
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_catv_grid_item($rid, $t, $lang)
{
    $icon = aio_catv_poster($t);
    $g = aio_catv_genre_parts($t, $lang);
    $parts = array_merge(aio_catv_fact_texts(aio_catv_fact_parts($t, $lang)),
        array(implode(', ', $g[0]), $g[1], aio_error_cut($t['description'], AIO_CATV_DESC_CHARS)));
    $text = implode(' · ', array_filter($parts, 'strlen'));
    return array(
        PluginRegularFolderItem::media_url => aio_catv_tile_id("$rid:{$t['id']}"),
        PluginRegularFolderItem::caption => $t['name'],
        PluginRegularFolderItem::view_item_params => array(
            ViewItemParams::icon_path => $icon,
            ViewItemParams::item_detailed_icon_path => $icon,
            ViewItemParams::item_detailed_info => aio_clean($t['name']) . '|' . aio_clean($text)));
}

// The row of a rid from the saved manifest, or null.
/**
 * @param string $rid
 * @return AioCatRow|null
 */
function aio_catv_row_by_rid($rid)
{
    $copy = aio_cat_manifest_copy();
    foreach ($copy['rows'] as $row)
    {
        if (aio_catv_rid($row) === $rid)
            return $row;
    }
    return null;
}

// New tiles to the listing. The listing begun anew (aio_cat_more: reset): the shell
// holds the old tiles by their place, so they stay and are not new any more.
/**
 * @param AioCatList $list
 * @param AioCatResult $res
 * @param-out AioCatList $list
 * @return void
 */
function aio_catv_add(&$list, $res)
{
    foreach ($res['tiles'] as $t)
        $list['tiles'][$t['id']] = $t;
    if ($res['reset'])
        aio_cat_keep_seen($list['st'], $list['tiles']);
}

// The next pages of the listing $list of $rid, up to $want tiles or its end, within $budget s
// from $start; the listing goes to memory page by page, so a repeat of a call cancelled is at
// once. -> '', the error of a page, or 'out of time'.
/**
 * @param AioCatList $list
 * @param string $rid
 * @param int $want
 * @param float $budget
 * @param float $start
 * @param-out AioCatList $list
 * @return string
 */
function aio_catv_fill(&$list, $rid, $want, $budget, $start)
{
    while (count($list['tiles']) < $want && $list['st']['end'] === '')
    {
        $left = $budget - (microtime(true) - $start);
        if ($left < AIO_CATV_MIN_S)
            return 'out of time';
        $res = aio_cat_more($list['st'], $left);
        aio_catv_add($list, $res);
        AioCat::$lists[$rid] = $list;
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
function aio_catv_more_url($id)
{
    list(, $id) = aio_catv_id_mode($id);
    return strpos($id, 'more:') === 0 ? 'cat_grid:' . substr($id, strlen('more:')) : '';
}

// The listing of a screen "Show all" ("cat_grid:<rid>", of a search "cat_grid:<rid>:<id of
// the search>"), or null: a search no more in memory (AIO_CATV_SEARCHES) gets nothing. $anew:
// a row of the manifest not in memory gets a new listing, from the first page on; not for the
// rows of a screen already shown, the listing anew may differ.
/**
 * @param string $url
 * @param bool $anew
 * @return AioCatList|null
 */
function aio_catv_all_list($url, $anew)
{
    $p = explode(':', strval(substr($url, strlen('cat_grid:'))));
    $rid = $p[0];
    $list = isset(AioCat::$lists[$rid]) ? AioCat::$lists[$rid] : null;
    if (count($p) === 2)
        return isset(AioCat::$searches[$p[1]]) && in_array($rid, AioCat::$searches[$p[1]], true) ? $list : null;
    if (count($p) > 2 || ($list && isset($list['st']['row']['q'])))
        return null;
    if ($list || !$anew)
        return $list;
    $row = aio_catv_row_by_rid($rid);
    if (!$row)
        return null;
    AioCat::$lists[$rid] = aio_catv_list(aio_cat_row_state($row), array(), false);
    return AioCat::$lists[$rid];
}

// The items of the grid from $from on, by their place in all the tiles so far: the shell
// counts from_ndx itself and a reply may be shorter than it thinks. No listing in memory:
// a new one, from the first page to $from. A failure: what there is; with nothing, the
// list ends for now, so the shell does not ask in a loop. ENTER on "Show all" goes on from
// there. Out of time with nothing, only repeats so far: more to come; such a call took all
// of AIO_CATV_GRID_S, so it is no fast loop.
/**
 * @param string $url
 * @param int $from
 * @return array<string, mixed>
 */
function aio_catv_range($url, $from)
{
    $list = aio_catv_all_list($url, true);
    $items = array();
    $more = false;
    if ($list)
    {
        $rid = aio_catv_rid($list['st']['row']);
        $out_of_time = aio_catv_fill($list, $rid, $from + 1, AIO_CATV_GRID_S, microtime(true)) === 'out of time';
        $lang = aio_catv_ui_lang();
        foreach (array_slice($list['tiles'], $from, AIO_CATV_GRID_PORTION) as $t)
            $items[] = aio_catv_grid_item($rid, $t, $lang);
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
function aio_catv_range_op($media_url, $from)
{
    return strpos($media_url, 'cat_grid:') === 0 ? aio_catv_range($media_url, $from) : null;
}

/**
 * @param string $url
 * @return array<string, mixed>
 */
function aio_catv_grid_view($url)
{
    $range = aio_catv_range($url, 0);
    if (!$range[PluginRegularFolderRange::items])
    {
        aio_log('catalog: grid: nothing to show');
        return aio_lines_view(array(aio_expired_item()));
    }
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
                ViewItemParams::icon_path => AIO_CATV_ICONS . 'loading_movie_poster.png'),
            PluginRegularFolderView::load_failed_view_item_params => array(
                ViewItemParams::icon_path => AIO_CATV_ICONS . 'no_movie_poster.png'),
            PluginRegularFolderView::async_icon_loading => true,
            PluginRegularFolderView::actions => array(GUI_EVENT_KEY_ENTER => aio_input('cat_enter')),
            PluginRegularFolderView::initial_range => $range));
}

// --- r25 "Show all": as the expanded row of the Dune (movies/movie_list_screen.php), rows
// "row<k>" of 9 with the info panel, all of them in the view; the shell asks for the tiles.

// The end of the listing for "Show all": known, or past the last row the screen has.
/**
 * @param AioCatList $list
 * @return bool
 */
function aio_catv_all_end($list)
{
    return $list['st']['end'] !== '' || count($list['tiles']) >= AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS;
}

// r24 has neither get_folder_rows nor dup rows: the grid there.
/**
 * @return bool
 */
function aio_catv_all_ok()
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
function aio_catv_all_def($id, $items, $options = 0)
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
 * @param AioCatList $list
 * @return array<string, mixed>
 */
function aio_catv_all_row($rid, $k, $list)
{
    $items = array();
    foreach (array_slice($list['tiles'], AIO_CATV_ALL_COLS * $k, AIO_CATV_ALL_COLS) as $t)
        $items[] = aio_catv_title_item($rid, $t);
    return aio_catv_all_def("row$k", $items);
}

// A row to ask for, a copy of "ld" of row_dup_map; the type a literal, the r24 API has no such constant.
/**
 * @param int $k
 * @return array<string, mixed>
 */
function aio_catv_all_stub($k)
{
    return array(
        PluginRow::type => 'row_dup',
        PluginRow::data => 'ld',
        PluginRow::id => "row$k",
        PluginRow::options => aio_catv_loading_flag() | (defined('PLUGIN_ROW_OPT_OPTIMIZE_INVISIBLE') ? PLUGIN_ROW_OPT_OPTIMIZE_INVISIBLE : 0));
}

// The actions of "Show all"; with stubs, the tick of the timer too (no dialog, as rows_info).
/**
 * @param string $url
 * @param bool $stubs
 * @return array<string, AioAction>
 */
function aio_catv_all_actions($url, $stubs)
{
    $actions = aio_catv_actions($url);
    if ($stubs)
    {
        $tick = aio_input('cat_tick', array('from' => $url));
        $tick[GuiAction::data] = null;
        $actions[GUI_EVENT_TIMER] = $tick;
    }
    return $actions;
}

// The screen of the rows $rows: the pane of get_rows_pane of movie_list_screen.php r25, the
// panel and the template of the catalog. $dup: row_dup_map, null without stubs; with them the timer.
/**
 * @param list<array<string, mixed>> $rows
 * @param array<string, array<string, mixed>>|null $dup
 * @param mixed $sel_state
 * @param string $url
 * @return array<string, mixed>
 */
function aio_catv_all_pane($rows, $dup, $sel_state, $url)
{
    array_unshift($rows, array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 20, PluginRow::id => 'top_vgap'));
    $r = aio_catv_pane_r(false);
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
        PluginRowsPane::regular_item_params_templates => array('common' => aio_catv_item_tpl(array(8, 4, 4, 6))));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
        PluginFolderView::data => array(
            PluginRowsFolderView::pane => $pane,
            PluginRowsFolderView::sel_state => is_string($sel_state) && $sel_state !== '' ? $sel_state : null,
            PluginRowsFolderView::actions => aio_catv_all_actions($url, $dup !== null),
            PluginRowsFolderView::timer => $dup !== null ? array(GuiTimerDef::delay_ms => AIO_CATV_ALL_TICK_MS) : null));
}

// The screen "Show all" (aio_catv_all_list). Its first row is never flagged (a flagged
// first row covered the rows under it, 0.50.0) and never changes: the end not known, it is
// filled to 9 tiles here, in AIO_CATV_GRID_S. The end known: its rows only, the last one
// short. Else the full rows in memory, then stubs up to AIO_CATV_ALL_ROWS, and the timer. No
// tile at all: one tile "empty"; the first row not filled: its tiles and "did not load", ENTER
// on it opens the screen anew. After a failure of get_folder_rows (aio_catv_all_rows) the same
// from memory, its full rows and "did not load", no stubs, no timer: no request till ENTER on it.
/**
 * @param string $url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_all_view($url, $sel_state)
{
    $start = microtime(true);
    $lang = aio_catv_lang();
    AioCat::$lang = $lang;
    $list = aio_catv_all_list($url, true);
    if (!$list)
    {
        aio_log('catalog: show all: nothing to show');
        return aio_lines_view(array(aio_expired_item()));
    }
    $rid = aio_catv_rid($list['st']['row']);
    $failed = isset(AioCat::$all_failed[$rid]);
    if (!$failed)
        aio_catv_fill($list, $rid, AIO_CATV_ALL_COLS, AIO_CATV_GRID_S, $start);
    $n = count($list['tiles']);
    $end = aio_catv_all_end($list);
    $rows = array();
    $dup = null;
    if ($n === 0 && $end)
    {
        $row = aio_catv_all_row($rid, 0, $list);
        $row[PluginRow::data][PluginRegularRow::items][] = aio_catv_note_item($rid, 'cat_row_empty', $lang);
        $rows[] = $row;
    }
    else if ($end)
    {
        for ($k = 0; $k < min(AIO_CATV_ALL_ROWS, intval(ceil($n / AIO_CATV_ALL_COLS))); $k++)
            $rows[] = aio_catv_all_row($rid, $k, $list);
    }
    else if ($failed || $n < AIO_CATV_ALL_COLS)
    {
        // "Did not load" after the last tile, a new row after a full one.
        $full = intval($n / AIO_CATV_ALL_COLS);
        for ($k = 0; $k <= $full; $k++)
            $rows[] = aio_catv_all_row($rid, $k, $list);
        $rows[$full][PluginRow::data][PluginRegularRow::items][] = aio_catv_note_item($rid, 'cat_row_failed', $lang);
    }
    else
    {
        $full = intval($n / AIO_CATV_ALL_COLS);
        for ($k = 0; $k < AIO_CATV_ALL_ROWS; $k++)
            $rows[] = $k < $full ? aio_catv_all_row($rid, $k, $list) : aio_catv_all_stub($k);
        // As add_loading_movie_ritem of movie_helper.php r25: no caption.
        $items = array();
        for ($j = 0; $j < AIO_CATV_ALL_COLS; $j++)
            $items[] = array(PluginRegularItem::id => ".$j", PluginRegularItem::icon_url => AIO_CATV_ICONS . 'loading_movie_poster_noalpha.png');
        // The shell puts the id of the dup row before the ids of its tiles: "row<k>.<j>".
        $dup = array('ld' => aio_catv_all_def('loading', $items, aio_catv_loading_flag()));
    }
    if ($dup)
    {
        AioCat::$all_stubs[$rid] = true;
        // The ticks of this screen from #1 in the log.
        AioCat::$all_ticks = 0;
        AioCat::$all_tick_last = '';
    }
    else
        unset(AioCat::$all_stubs[$rid]);
    aio_log(sprintf('catalog: show all: %d tiles, %d rows%s, %.0f ms', $n, count($rows), $end ? ', the end' : ($failed ? ', failed' : ''),
        (microtime(true) - $start) * 1000));
    return aio_catv_all_pane($rows, $dup, $sel_state, $url);
}

// get_folder_rows of "Show all": the rows asked that are full; once the end is known also the
// last short row and 0 tiles for every row past it, asked or not, in every reply (the shell
// throws away a reply it cancelled; it does not draw a row of 0 tiles). Before that the next
// pages, in AIO_CATV_ROW_S, up to the row after the first one asked and AIO_CATV_ALL_AHEAD tiles
// more, not to the last one: the opening asks row1..row11 at once, the rest of them the shell
// asks again by itself in a few ms, the screen below the first row is not empty for seconds. The
// listing goes to memory page by page, so a repeat of a cancelled call is at once. No row asked
// filled (a failure, out of time): "did not load" after the last tile and the rows past it closed
// as at the end, in every reply until the view anew; no request till then. Never an empty
// reply: the shell would ask again at once. No listing (php_server restarted, a search out of
// memory): 0 tiles from the first row asked on; an id not of a row of the screen: 0 tiles with that id.
/**
 * @param string $url
 * @param array<mixed> $ids
 * @return non-empty-list<array<string, mixed>>
 */
function aio_catv_all_rows($url, $ids)
{
    $start = microtime(true);
    $list = aio_catv_all_list($url, false);
    $asked = array();
    $bad = array();
    foreach ($ids as $id)
    {
        if (!is_string($id))
            continue;
        if (preg_match('/^row([0-9]{1,3})$/D', $id, $m) && intval($m[1]) < AIO_CATV_ALL_ROWS)
            $asked[] = intval($m[1]);
        else
            $bad[] = aio_catv_all_def($id, array());
    }
    $out = array();
    if (!$list)
    {
        aio_log('catalog: show all: rows: no listing');
        for ($k = $asked ? min($asked) : AIO_CATV_ALL_ROWS; $k < AIO_CATV_ALL_ROWS; $k++)
            $out[] = aio_catv_all_def("row$k", array());
    }
    else
    {
        $rid = aio_catv_rid($list['st']['row']);
        $cap = AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS;
        $want = $asked ? min($cap, AIO_CATV_ALL_COLS * (min($asked) + 2) + AIO_CATV_ALL_AHEAD) : 0;
        $was = count($list['tiles']);
        $failed = isset(AioCat::$all_failed[$rid]);
        $err = $failed ? '' : aio_catv_fill($list, $rid, $want, AIO_CATV_ROW_S, $start);
        $n = count($list['tiles']);
        $end = aio_catv_all_end($list);
        foreach ($asked as $k)
        {
            if ($end || AIO_CATV_ALL_COLS * ($k + 1) <= $n)
                $out[] = aio_catv_all_row($rid, $k, $list);
        }
        if (!$end && !$failed && $asked && !$out)
        {
            $failed = true;
            AioCat::$all_failed[$rid] = true;
            aio_log(sprintf('catalog: show all: rows: failed (%s), %d tiles', $err, $n));
        }
        if ($end || $failed)
        {
            $full = intval($n / AIO_CATV_ALL_COLS);
            for ($k = $full; $k < AIO_CATV_ALL_ROWS; $k++)
            {
                if (in_array($k, $asked, true) && $end)
                    continue;
                $row = aio_catv_all_row($rid, $k, $list);
                if ($failed && $k === $full)
                    $row[PluginRow::data][PluginRegularRow::items][] = aio_catv_note_item($rid, 'cat_row_failed', aio_catv_ui_lang());
                $out[] = $row;
            }
        }
        if ($n > $was)
            aio_log(sprintf('catalog: show all: rows: %d tiles, %d new%s, %.0f ms', $n, $n - $was, $end ? ', the end' : '',
                (microtime(true) - $start) * 1000));
    }
    $out = array_merge($out, $bad);
    // No id at all: a row the screen does not have, the shell drops it.
    return $out ? $out : array(aio_catv_all_def('none', array()));
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
function aio_catv_info_label($text, $size, $color, $w, $h, $y, $spec = array(), $options = 0)
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
function aio_catv_fit($text, $max, $min, $width)
{
    $w = aio_gc_w($text, 100);
    $px = max($min, min($max, $w > 0 ? intval($width * 100 / $w) + 1 : $max));
    while ($px > $min && aio_gc_w($text, $px) > $width)
        $px--;
    return array($px, aio_gc_cut_w($text, $px, $width));
}

// A label of the line of facts at $y: 30 px, LINE_H high as the other lines, $align its place (x, y
// set here when the align has no base).
/**
 * @param string $text
 * @param string $color
 * @param int $w
 * @param AioGcAlign $align
 * @param int $halign
 * @return array<string, mixed>
 */
function aio_catv_fact_label($text, $color, $w, $align, $halign = HALIGN_LEFT)
{
    $l = aio_catv_info_label(aio_clean($text), AIO_CATV_LINE_PX, $color, $w, AIO_CATV_LINE_H, 0,
        $halign === HALIGN_LEFT ? array() : array(GCompTtfLabelDef::halign => $halign));
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def] = $align;
    return $l;
}

// The line of facts of aio_catv_facts at $y, all of it 30 px: the plates at their places, as their
// widths are known; the text after them, cut with "…" to the room it has; the age plate after the
// text right of it by the shell (aio_gc_next_right: the text is as wide as the font of the user
// makes it), its label on it. The plates are pictures of their own size: the text in them, not
// the plate, follows the font.
/**
 * @param list<array{string, string, string}> $facts
 * @param int $y
 * @return list<array<string, mixed>>
 */
function aio_catv_facts_labels($facts, $y)
{
    $w = array('kp' => AIO_CATV_KP_W, 'imdb' => AIO_CATV_IMDB_W, 'age' => AIO_CATV_AGE_W);
    $last = end($facts);
    $age_w = $last && $last[0] === 'age' ? AIO_CATV_AGE_GAP + AIO_CATV_AGE_W : 0;
    $defs = array();
    $x = 0;
    $text = false;
    foreach ($facts as $f)
    {
        list($kind, $s, $value) = $f;
        if ($kind === 'text')
        {
            $x += $x ? AIO_CATV_REST_GAP : 0;
            $defs[] = aio_catv_fact_label(aio_gc_cut_w(aio_clean($s), AIO_CATV_LINE_PX, AIO_CATV_TEXT_W - $x - $age_w),
                AIO_CATV_TEXT_COLOR, -1, array(GCompAlignDef::x => $x, GCompAlignDef::y => $y));
            $text = true;
            continue;
        }
        $pw = $w[$kind];
        $plate = AIO_CATV_IMG . "$kind.png";
        if ($kind === 'age' && $text)
        {
            $defs[] = aio_gc_image(aio_gc_geom($pw, AIO_CATV_PLATE_H, aio_gc_next_right(AIO_CATV_AGE_GAP, AIO_CATV_PLATE_DY)), $plate);
            $defs[] = aio_catv_fact_label($s, AIO_CATV_TEXT_COLOR, $pw, array(GCompAlignDef::y => -AIO_CATV_PLATE_DY,
                GCompAlignDef::halign => HALIGN_LEFT, GCompAlignDef::valign => VALIGN_TOP, GCompAlignDef::base_halign => HALIGN_LEFT,
                GCompAlignDef::base_valign => VALIGN_TOP, GCompAlignDef::base_to_prev => true), HALIGN_CENTER);
            continue;
        }
        $x += $x ? ($kind === 'age' ? AIO_CATV_AGE_GAP : AIO_CATV_PLATE_GAP) : 0;
        $defs[] = aio_gc_image(aio_gc_at($pw, AIO_CATV_PLATE_H, $x, $y + AIO_CATV_PLATE_DY), $plate);
        $in = $pw - 2 * AIO_CATV_PLATE_PAD;
        if ($kind === 'age')
            $defs[] = aio_catv_fact_label($s, AIO_CATV_TEXT_COLOR, $pw, array(GCompAlignDef::x => $x, GCompAlignDef::y => $y), HALIGN_CENTER);
        else if (aio_gc_w("$s $value", AIO_CATV_LINE_PX) <= $in)
        {
            $at = array(GCompAlignDef::x => $x + AIO_CATV_PLATE_PAD, GCompAlignDef::y => $y);
            $defs[] = aio_catv_fact_label($s, AIO_CATV_TEXT_COLOR, $in, $at);
            $defs[] = aio_catv_fact_label($value, AIO_CATV_RATE_COLOR, $in, $at, HALIGN_RIGHT);
        }
        else
            $defs[] = aio_catv_fact_label(aio_gc_cut_w("$s $value", AIO_CATV_LINE_PX, $pw), AIO_CATV_RATE_COLOR, $pw,
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
function aio_catv_title_label($name, $bottom)
{
    $spec = array(GCompTtfLabelDef::line_spacing => 0, GCompTtfLabelDef::valign => VALIGN_BOTTOM);
    if (aio_gc_w($name, AIO_CATV_TITLE_MIN_PX) <= AIO_CATV_TEXT_W)
        $fit = aio_catv_fit($name, AIO_CATV_TITLE_PX, AIO_CATV_TITLE_MIN_PX, AIO_CATV_TEXT_W);
    else
    {
        $fit = array(AIO_CATV_TITLE2_PX, implode(' ', aio_gc_wrap($name, AIO_CATV_TITLE2_PX, AIO_CATV_TEXT_W, 2, true)));
        $spec[GCompTtfLabelDef::max_num_lines] = 2;
    }
    return aio_catv_info_label($fit[1], $fit[0], AIO_CATV_TITLE_COLOR, AIO_CATV_TEXT_W, AIO_CATV_TITLE_H, $bottom - AIO_CATV_TITLE_H,
        $spec, GCOMP_OPT_TTF_LAYOUT_FIX | GCOMP_OPT_TTF_COMPACT_HEIGHT);
}

// The panel on the coordinates of the native one (movie_helper.php, the same info_r), 30 px lower,
// nearer the rows: the title (aio_catv_title_label), none when the background has the logo in its
// place (aio_catv_card); without the logo, the English name of the tile under the title when it is not
// the title (native_title of dunemdb_cache.php: of a title not in English, as those of the catalog are
// in any interface), the title higher by its height. Then the facts, the genres and the
// country, the cast: lines LINE_H high from AIO_CATV_LINES_Y; the description 13 px under the last
// one, as many lines as the rest of the panel holds, as the native one counts them. No line for an
// empty field, the next ones go up.
// "Show all": the name of the row and the caption of the tile. null without a tile or a row in memory (a stub, a message, a restart
// of php_server). No label with an empty text: whether the shell takes one is not known.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_catv_info($in)
{
    $id = aio_catv_in($in, 'item_id');
    $t = aio_catv_tile($id);
    $more_url = aio_catv_more_url($id);
    $more = $more_url !== '' ? aio_catv_all_list($more_url, false) : null;
    if ($t)
    {
        $name = $t['name'];
        $sub = $t['englishTitle'] !== $name ? $t['englishTitle'] : '';
        $lang = aio_catv_ui_lang();
        $facts = aio_catv_facts($t, $lang);
        $lines = array(array(aio_catv_genre_line($t, $lang), AIO_CATV_TEXT_COLOR, AIO_CATV_DATA_MIN_PX),
            array(aio_catv_cast_line($t), AIO_CATV_CAST_COLOR, AIO_CATV_LINE_PX));
        $desc = $t['description'];
    }
    else if ($more)
    {
        $name = $more['st']['row']['name'];
        $sub = '';
        $facts = array();
        $lines = array(array(aio_clean(aio_tr(aio_catv_ui_lang(), 'cat_more')), AIO_CATV_TEXT_COLOR, AIO_CATV_LINE_PX));
        $desc = '';
    }
    else
        return null;
    $labels = array();
    $name = aio_clean($name);
    list($on) = aio_catv_id_mode($id);
    $card = aio_catv_card($t, $on);
    // None under the logo. Cut here to 2 lines of the least size, "…" on the last one; the shell sizes it down to them.
    $sub = $card['logo'] ? '' : implode(' ', aio_gc_wrap(aio_clean($sub), AIO_CATV_SUB_MIN_PX, AIO_CATV_TEXT_W, 2, true));
    $bottom = AIO_CATV_TITLE_BOTTOM - ($sub !== '' ? AIO_CATV_SUB_H : 0);
    if ($name !== '' && !$card['logo'])
        $labels[] = aio_catv_title_label($name, $bottom);
    if ($sub !== '')
        $labels[] = aio_catv_info_label($sub, AIO_CATV_SUB_PX, AIO_CATV_SUB_COLOR, AIO_CATV_TEXT_W, AIO_CATV_SUB_H, $bottom + 4,
            array(GCompTtfLabelDef::max_num_lines => 2, GCompTtfLabelDef::min_font_size => AIO_CATV_SUB_MIN_PX,
                GCompTtfLabelDef::line_spacing => 0));
    $y = AIO_CATV_LINES_Y;
    if ($facts)
    {
        $labels = array_merge($labels, aio_catv_facts_labels($facts, $y));
        $y += AIO_CATV_LINE_H;
    }
    foreach ($lines as $l)
    {
        if ($l[0] === '')
            continue;
        $fit = aio_catv_fit($l[0], AIO_CATV_LINE_PX, $l[2], AIO_CATV_TEXT_W);
        $labels[] = aio_catv_info_label($fit[1], $fit[0], $l[1], AIO_CATV_TEXT_W, AIO_CATV_LINE_H, $y);
        $y += AIO_CATV_LINE_H;
    }
    // Lines of 30 px 5 px apart; cut here to them, "…" on the last one; the shell, with a
    // little more room, breaks them no sooner.
    $y += 13;
    $n = intval((AIO_CATV_PANE_H - $y - AIO_CATV_LINE_PX) / (AIO_CATV_LINE_PX + 5));
    $desc = implode(' ', aio_gc_wrap(aio_clean($desc), AIO_CATV_LINE_PX, AIO_CATV_TEXT_W, $n, true));
    if ($desc !== '')
        $labels[] = aio_catv_info_label($desc, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR, AIO_CATV_TEXT_W, -1, $y,
            array(GCompTtfLabelDef::max_num_lines => $n, GCompTtfLabelDef::line_spacing => 5));
    $pane = array(
        GComponentDef::geom_def => array(
            GCompGeometryDef::w => AIO_CATV_PANE_W,
            GCompGeometryDef::h => AIO_CATV_PANE_H,
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
    if (aio_catv_in($in, 'folder_key') !== '')
        $info[PluginRowsInfo::folder_key] = aio_catv_in($in, 'folder_key');
    return array(
        GuiAction::handler_string_id => PLUGIN_UPDATE_ROWS_INFO_ACTION_ID,
        GuiAction::data => array(
            PluginUpdateRowsInfoActionData::info => $info,
            PluginUpdateRowsInfoActionData::post_action => null));
}

// --- Input.

/**
 * @param string $url
 * @return AioAction
 */
function aio_catv_invalidate($url)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
        GuiAction::data => array(
            PluginInvalidateFoldersActionData::media_urls => array($url),
            PluginInvalidateFoldersActionData::post_action => null,
            PluginInvalidateFoldersActionData::all_except => false));
}

// The tick of the timer of "Show all" $url, memory only. The view of stubs and the end known or
// a failure (its tail closed, the focus on a stub lost): the view anew without stubs and timer;
// else the timer again (update_next_movie of main_entry_handler.php r25). No listing or stubs:
// no more ticks. The log: only a tick that gives other than the last one.
/**
 * @param string $url
 * @return AioAction|null
 */
function aio_catv_all_tick($url)
{
    $n = ++AioCat::$all_ticks;
    $list = aio_catv_all_list($url, false);
    $act = null;
    $rid = $list ? aio_catv_rid($list['st']['row']) : '';
    if (!$list || !isset(AioCat::$all_stubs[$rid]))
    {
        $what = 'null';
        $why = $list ? 'no stubs' : 'no listing';
    }
    else
    {
        $tiles = count($list['tiles']);
        $end = aio_catv_all_end($list);
        $failed = isset(AioCat::$all_failed[$rid]);
        $why = ($end ? 'the end, ' : ($failed ? 'failed, ' : '')) . "$tiles tiles";
        if ($end || $failed)
        {
            $what = 'invalidate';
            $act = aio_catv_invalidate($url);
        }
        else
        {
            $what = 'change_behaviour';
            $act = array(
                GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
                GuiAction::data => array(
                    ChangeBehaviourActionData::actions => aio_catv_all_actions($url, true),
                    ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => AIO_CATV_ALL_TICK_MS)));
        }
    }
    if ($what !== AioCat::$all_tick_last)
        aio_log("catalog: show all: tick #$n: $why, $what");
    AioCat::$all_tick_last = $what;
    return $act;
}

// Opens the screen "Show all" $url (aio_catv_more_url), named after its row.
/**
 * @param string $url
 * @return AioAction
 */
function aio_catv_all_open($url)
{
    $list = aio_catv_all_list($url, false);
    return aio_catv_open_act($url, $list ? $list['st']['row']['name'] : aio_tr(aio_catv_lang(), 'cat_title'));
}

// ENTER on the part $id of the strip of the main screen $from. A tab not active: the screen of its
// type in place of this one (erase_count 1: Back does not grow), the active one: nothing; $up (its
// item of POP UP, aio_catv_menu): the active one too, the focus up on it. The icons: the dialog of
// a search and the settings screen.
/**
 * @param string $id
 * @param string $from
 * @param bool $up
 * @return AioAction|null
 */
function aio_catv_strip_enter($id, $from, $up = false)
{
    if ($id === 'tab_search')
        return aio_catv_search_ask($from);
    if ($id === 'tab_setup')
        return aio_setup_open_act($from);
    $tab = strval(substr($id, strlen('tab_')));
    if (($tab !== 'movie' && $tab !== 'series') || !aio_catv_is_main($from) || (!$up && $tab === aio_catv_tab($from)))
        return null;
    return aio_replace_act(aio_catv_main_url($tab), '%tr%plugin_caption');
}

// POP UP of the screen $from. A main screen: the parts of the strip, as ENTER on them (cat_part), so
// the strip is at hand anywhere, with icons of the shell (the search and the setup as on the strip);
// on a header above them "Configure list…" and a separator (aio_catv_rows_menu_items). Else (the
// results of a search, "Show all") nothing.
/**
 * @param stdClass $in
 * @param string $from
 * @return AioAction|null
 */
function aio_catv_menu($in, $from)
{
    $hid = aio_catv_in($in, 'selected_header_id');
    $tab = aio_catv_tab($from);
    aio_log("catalog: POP UP: header '" . aio_cut($hid, 100) . "', row '" . aio_cut(aio_catv_in($in, 'selected_row_id'), 100) .
        "', item '" . aio_cut(aio_catv_in($in, 'selected_item_id'), 100) . "'");
    if ($tab === '')
        return null;
    $lang = aio_catv_lang();
    $items = aio_catv_rows_menu_items($hid, $tab, $from, $lang);
    $parts = array(
        'tab_movie' => array('cat_part_movies', 'movies_small.png'), 'tab_series' => array('cat_part_series', 'tv_small.png'),
        'tab_search' => array('cat_search', AIO_CATV_ICON_SEARCH), 'tab_setup' => array('setup_menu', AIO_CATV_ICON_SETUP));
    foreach ($parts as $id => $p)
    {
        $items[] = array(
            GuiMenuItemDef::caption => aio_tr($lang, $p[0]),
            GuiMenuItemDef::icon_url => AIO_CATV_ICONS . $p[1],
            GuiMenuItemDef::action => aio_input('cat_part', array('from' => $from, 'part' => $id)));
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => $items));
}

// ENTER on a tile: selected_item_id on the rows, selected_media_url on the grid.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_catv_enter($in)
{
    $id = aio_catv_in($in, 'selected_item_id');
    if ($id === '')
        $id = aio_catv_in($in, 'selected_media_url');
    if (aio_catv_in($in, 'selected_row_id') === AIO_CATV_STRIP || preg_match('/^tab_[a-z]+$/D', $id))
        return aio_catv_strip_enter($id, aio_catv_in($in, 'from'));
    // "more:<rid>", of a search "more:<rid>:<id of the search>": an older search opens outdated.
    $url = aio_catv_more_url($id);
    if ($url !== '')
        return aio_catv_all_open($url);
    if (strpos($id, 'retry:') === 0)
        return aio_catv_retry(aio_catv_in($in, 'from'));
    // A stub of "Show all": row<k>.<j>.
    if (strpos($id, 'stub:') === 0 || strpos($id, 'empty:') === 0 || preg_match('/^row[0-9]+\.[0-9]+$/D', $id))
        return null;
    // The opening tries again, and the rows after it too; the shell brings sel_state, the focus stays.
    // "Show all" goes on from the same place.
    if (strpos($id, 'fail:') === 0)
    {
        AioCat::$down = 0;
        unset(AioCat::$all_failed[strval(substr($id, strlen('fail:')))]);
        return aio_catv_invalidate(aio_catv_in($in, 'from'));
    }
    $t = aio_catv_tile($id);
    if (!$t)
    {
        aio_log('catalog: ENTER: no such tile in memory');
        return aio_error('cat_outdated');
    }
    return aio_card_enter($t, aio_catv_lang());
}

// "Retry" of the main screen $from: rows asked anew, the screen of its tab by a new address.
/**
 * @param string $from
 * @return AioAction
 */
function aio_catv_retry($from)
{
    AioCat::$empty = array();
    AioCat::$down = 0;
    $tab = aio_catv_tab($from);
    return aio_replace_act(aio_catv_main_url($tab !== '' ? $tab : 'movie', substr(aio_rid(), 0, 8)), aio_tr(aio_catv_lang(), 'cat_title'));
}

// The search $sid out of memory, its listings and the flags of their screens with it.
/**
 * @param string $sid
 * @return void
 */
function aio_catv_search_drop($sid)
{
    if (!isset(AioCat::$searches[$sid]))
        return;
    foreach (AioCat::$searches[$sid] as $r)
        unset(AioCat::$lists[$r], AioCat::$all_failed[$r], AioCat::$all_stubs[$r]);
    unset(AioCat::$searches[$sid]);
}

// The dialog of a search; the field goes to cat_search_apply and on as cat_q. The
// query is in no action to run and no address: only in what the shell sends back
// and in the field, which starts with the last one (AioCat::$last_q).
/**
 * @param string $from
 * @return AioAction
 */
function aio_catv_search_ask($from)
{
    $p = array('from' => $from);
    return aio_dialog_act(aio_tr(aio_catv_lang(), 'cat_search'), array(
        aio_ctl('cat_q', GUI_CONTROL_TEXT_FIELD, array(
            GuiTextFieldDef::initial_value => AioCat::$last_q,
            GuiTextFieldDef::numeric => 0,
            GuiTextFieldDef::password => 0,
            GuiTextFieldDef::has_osk => 1,
            GuiTextFieldDef::always_active => 1,
            GuiTextFieldDef::width => 1400,
            GuiTextFieldDef::apply_action => aio_input('cat_search_apply', $p),
            GuiTextFieldDef::confirm_action => null), ''),
        aio_ctl('', GUI_CONTROL_VGAP, array(GuiVGapDef::vgap => 500))),
        array(ShowDialogActionData::preferred_width => 0));
}

// $q: no log, no address of a screen. Results: two rows, the empty ones hidden.
/**
 * @param string $q
 * @param string $from
 * @return AioAction|null
 */
function aio_catv_search_run($q, $from)
{
    $q = aio_cat_query($q);
    if ($q === '')
        return null;
    AioCat::$last_q = $q;
    $lang = aio_catv_lang();
    // The no-break space at the end of cat_movies, cat_series is on purpose, in place of the number (the owner's choice); editors drop it as trailing whitespace: keep it.
    $found = aio_cat_search($q, array('movie' => aio_tr($lang, 'cat_movies'), 'series' => aio_tr($lang, 'cat_series')));
    $sid = substr(aio_rid(), 0, 8);
    $rids = array();
    $failed = false;
    foreach ($found as $f)
    {
        $res = $f['res'];
        $failed = $failed || $res['err'] !== '';
        if (!$res['tiles'])
            continue;
        $st = $f['st'];
        $st['row']['sid'] = $sid;
        $rid = aio_catv_rid($st['row']);
        AioCat::$lists[$rid] = aio_catv_list($st, $res['tiles'], $res['stale']);
        $rids[] = $rid;
    }
    if (!$rids)
        return aio_error($failed ? 'cat_search_failed' : 'cat_search_none');
    AioCat::$searches[$sid] = $rids;
    // From the results of a search: they are replaced, that search holds no place.
    $replace = strpos($from, 'results:') === 0;
    if ($replace)
        aio_catv_search_drop(strval(substr($from, strlen('results:'))));
    // Not array_shift: an id of digits only is a key of int, it would renumber it.
    while (count(AioCat::$searches) > AIO_CATV_SEARCHES)
    {
        reset(AioCat::$searches);
        aio_catv_search_drop(strval(key(AioCat::$searches)));
    }
    $url = "cat_search:$sid";
    $caption = aio_tr($lang, 'cat_results');
    return $replace ? aio_replace_act($url, $caption) : aio_catv_open_act($url, $caption);
}

/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction|null
 */
function aio_catv_input($in, $control)
{
    $from = aio_catv_in($in, 'from');
    if ($control === 'cat_info')
        return aio_catv_info($in);
    if ($control === 'cat_enter')
        return aio_catv_enter($in);
    // show_all_action of a row: cat_more is the id of its tile "Show all".
    if ($control === 'cat_all')
    {
        $url = aio_catv_more_url(aio_catv_in($in, 'cat_more'));
        return $url !== '' ? aio_catv_all_open($url) : null;
    }
    if ($control === 'cat_tick')
        return aio_catv_all_tick($from);
    if ($control === 'cat_retry')
        return aio_catv_retry($from);
    if ($control === 'cat_search')
        return aio_catv_search_ask($from);
    if ($control === 'cat_search_apply')
        return aio_close_and(aio_input('cat_search_run', array('from' => $from)));
    if ($control === 'cat_search_run')
        return aio_catv_search_run(aio_catv_in($in, 'cat_q'), $from);
    if ($control === 'cat_menu')
        return aio_catv_menu($in, $from);
    if ($control === 'cat_part')
        return aio_catv_strip_enter(aio_catv_in($in, 'part'), $from, true);
    if ($control === 'cat_rows_menu')
        return aio_catv_rows_menu($from, aio_catv_in($in, 'sel'));
    if ($control === 'cat_rows_edit')
        return aio_catv_rows_edit($from, aio_catv_in($in, 'sel'), aio_catv_in($in, 'g'));
    if ($control === 'cat_rows_apply')
        return aio_catv_rows_apply($in, $from);
    return null;
}
