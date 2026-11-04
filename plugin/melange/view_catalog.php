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
// The info panel, as the native one (movie_helper.php of shell_ext, the same info_r):
// a title of one line as large as fits PANE_W, then lines of 30 px (down to 15 px to
// fit TEXT_W), then the description. Names up to 197, descriptions up to 1096 characters
// were seen. DESC_CHARS: of the text of an item of the grid.
define('AIO_CATV_PANE_W', 950);
define('AIO_CATV_PANE_H', 560);
define('AIO_CATV_TEXT_W', 800);
define('AIO_CATV_TITLE_PX', 120);
define('AIO_CATV_TITLE_MIN_PX', 36);
define('AIO_CATV_LINE_PX', 30);
define('AIO_CATV_LINE_MIN_PX', 15);
define('AIO_CATV_TITLE_COLOR', '#FFFFFFE0');
define('AIO_CATV_TEXT_COLOR', '#FFAFAFA0');
define('AIO_CATV_CAST_COLOR', '#FFFFFF40');
// The English name under the title (info_ntitle of movie_helper.php): 36 px down to 20, 2 lines.
define('AIO_CATV_SUB_PX', 36);
define('AIO_CATV_SUB_MIN_PX', 20);
define('AIO_CATV_SUB_COLOR', '#FF808080');
define('AIO_CATV_DESC_CHARS', 360);
// The TMDB size of a background; w780 if the shell runs short of memory.
define('AIO_CATV_BG_SIZE', 'w1280');
define('AIO_CATV_ICONS', 'plugin_file://%shell_ext%/icons/');

class AioCat
{
    // Listings by rid: what the info panel and ENTER read. Memory of php_server
    // only; after a restart the rows come from the cache, the panel stays empty.
    /** @var array<string, AioCatList> */
    public static $lists = array();
    // The screen of the last search: its id, the rids of its rows.
    /** @var array{id: string, rids: list<string>} */
    public static $search = array('id' => '', 'rids' => array());
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
    return substr(md5($row['key']), 0, 8);
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

// The tile of an id of the screen, memory only, or null.
/**
 * @param string $id
 * @return AioCatTile|null
 */
function aio_catv_tile($id)
{
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

// A row of the panel: its title and tiles, the first page and "Show all", or $items
// instead of them. $options: of the row of tiles.
/**
 * @param AioCatList $list
 * @param string $lang
 * @param list<array<string, string>>|null $items
 * @param int $options
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_catv_row_defs($list, $lang, $items = null, $options = 0)
{
    $row = $list['st']['row'];
    $rid = aio_catv_rid($row);
    // The no-break space at the end of the name of a search row (cat_movies) not before the mark.
    $title = $list['stale'] ? strval(preg_replace('/\x{00A0}$/u', '', $row['name'])) . ' (' . aio_tr($lang, 'cat_saved') . ')' : $row['name'];
    if ($items === null)
    {
        $items = array();
        foreach (array_slice($list['tiles'], 0, AIO_CATV_ROW_TILES) as $t)
            $items[] = aio_catv_item("$rid:{$t['id']}", $t['name'], aio_catv_poster($t));
        if ($list['st']['end'] === '' || count($list['tiles']) > AIO_CATV_ROW_TILES)
            $items[] = aio_catv_item("more:$rid", aio_tr($lang, 'cat_more'), AIO_CATV_ICONS . 'more.png');
    }
    $defs = array(
        array(
            PluginRow::type => PLUGIN_ROW_TYPE_TITLE,
            PluginRow::id => "title:$rid",
            PluginRow::group_id => "row:$rid",
            PluginRow::height => 55,
            PluginRow::inactive_height => 0,
            PluginRow::data => array(
                PluginTitleRow::caption => $title,
                PluginTitleRow::color => '#FFFFFFE0',
                PluginTitleRow::font_size => 30,
                PluginTitleRow::left => 100,
                PluginTitleRow::dy => 9,
                PluginTitleRow::active_dy => 0,
                PluginTitleRow::width => 1920,
                PluginTitleRow::fade_enabled => true,
                PluginTitleRow::fade_color => '#FF606060',
                PluginTitleRow::lite_fade_color => '#FF808080')),
        array(
            PluginRow::type => PLUGIN_ROW_TYPE_REGULAR,
            PluginRow::id => "items:$rid",
            PluginRow::title => $title,
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
        return aio_catv_row_defs($list, $lang, array(aio_catv_item($note === 'cat_row_empty' ? "empty:$rid" : "fail:$rid", aio_tr($lang, $note),
            AIO_CATV_ICONS . 'no_movie_poster.png')));
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
    if (strpos($url, 'cat_grid:') === 0)
        return array('rows' => aio_catv_all_rows($url, $ids));
    $id = isset($ids[0]) && is_string($ids[0]) ? $ids[0] : 'items:';
    $rows = aio_catv_row_answer(strpos($id, 'items:') === 0 ? strval(substr($id, strlen('items:'))) : '', aio_catv_lang());
    $rows[0][PluginRow::id] = $id;
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
        PluginRegularItemParams::sticker_text_size => 25,
        PluginRegularItemParams::sel_margins => array(
            PluginMargins::left => $m[0], PluginMargins::top => $m[1],
            PluginMargins::right => $m[2], PluginMargins::bottom => $m[3]));
}

// The actions of the rows. $from: 'results' on the results of a search, a new search
// replaces them; else the address of the screen, for its "did not load" tile.
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

// The screen of the rows $defs. The key set of RowsFactory::pane r25, nulls too;
// row_dup_map: a literal, the r24 API has no such constant.
/**
 * @param list<array<string, mixed>> $defs
 * @param mixed $sel_state
 * @param string $from
 * @return array<string, mixed>
 */
function aio_catv_rows_view($defs, $sel_state, $from)
{
    $rows = array(array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 10, PluginRow::inactive_height => 10));
    foreach ($defs as $def)
        $rows[] = $def;
    $rows[] = array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 80, PluginRow::inactive_height => 80);
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
        PluginRowsPane::min_row_index_for_y2 => 0,
        PluginRowsPane::info_r => array('w' => AIO_CATV_PANE_W, 'h' => AIO_CATV_PANE_H, 'x' => 100, 'y' => 40),
        PluginRowsPane::vod_r => array('w' => 1280, 'h' => 720, 'x' => 640, 'y' => 0),
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

// The screen of the rows: "catalog", "catalog:<n>" of cat_retry and aio_setup_refresh.
/**
 * @param string $media_url
 * @return bool
 */
function aio_catv_is_main($media_url)
{
    return $media_url === 'catalog' || strpos($media_url, 'catalog:') === 0;
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
    if (strpos($media_url, 'cat_grid:') === 0)
        return aio_catv_all_ok() ? aio_catv_all_view($media_url, $sel_state) : aio_catv_grid_view($media_url);
    if (strpos($media_url, 'cat_search:') === 0)
        return aio_catv_results_view(substr($media_url, strlen('cat_search:')), $sel_state);
    return aio_catv_main_view($media_url, $sel_state);
}

// A message instead of an empty screen, and a way to try again.
/**
 * @param string $key
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_catv_message_view($key, $lang)
{
    aio_log("catalog: screen: $key");
    return array(
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::data => array(
            PluginControlsFolderView::defs => array(
                aio_setup_label(null, aio_tr($lang, $key)),
                aio_setup_button('retry', aio_tr($lang, 'cat_retry'), aio_input('cat_retry'))),
            PluginControlsFolderView::initial_sel_ndx => -1));
}

// The manifest does not hold the screen: a copy of any age goes; no copy: one request.
// r25: the first row now (aio_catv_row_now), the others: a fresh copy ready, an old one
// flagged, none: stubs; get_folder_rows asks for the flagged ones and refreshes an old
// manifest in the time left (none flagged: here, as r24). r24: the rows and the manifest
// load here, in AIO_CATV_OPEN_S.
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
    $copy = aio_cat_manifest_copy();
    $rows = $copy['rows'];
    $fresh = aio_cat_is_fresh($copy);
    if (!$rows)
    {
        $new = aio_cat_manifest_fetch(AIO_CATV_MANIFEST_S);
        $rows = $new['rows'];
        $fresh = true;
        if (!$rows)
            return aio_catv_message_view('cat_unavailable', $lang);
    }
    $rows = aio_cat_user_rows($rows, Aio::$settings['cat_rows']);
    if (!$rows)
        return aio_catv_message_view('cat_no_rows', $lang);
    $loading = aio_catv_loading_flag();
    $defs = array();
    $asked = false;
    $shown = array();
    foreach ($rows as $row)
    {
        if (!$loading || !aio_catv_is_empty(aio_catv_rid($row)))
            $shown[] = $row;
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
    aio_log(sprintf('catalog: screen: %d rows of %d, %.0f ms', count($defs) / 2, count($rows), (microtime(true) - $start) * 1000));
    return $defs ? aio_catv_rows_view($defs, $sel_state, $media_url) : aio_catv_message_view('cat_unavailable', $lang);
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
    foreach (AioCat::$search['id'] === $id ? AioCat::$search['rids'] : array() as $rid)
    {
        if (isset(AioCat::$lists[$rid]))
            $defs = array_merge($defs, aio_catv_row_defs(AioCat::$lists[$rid], $lang));
    }
    if (!$defs)
    {
        aio_log('catalog: search screen outdated');
        return aio_lines_view(array(aio_expired_item()));
    }
    return aio_catv_rows_view($defs, $sel_state, 'results');
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
    if (!preg_match('/^(?:([0-9]+)h)?(?:([0-9]+)min)?$/', $r, $x))
        return '';
    $min = (isset($x[1]) ? intval($x[1]) * 60 : 0) + (isset($x[2]) ? intval($x[2]) : 0);
    $parts = array();
    if ($min >= 60)
        $parts[] = str_replace('{n}', strval(intval($min / 60)), aio_tr($lang, 'cat_len_h'));
    if ($min % 60 > 0)
        $parts[] = str_replace('{n}', strval($min % 60), aio_tr($lang, 'cat_len_m'));
    return implode(' ', $parts);
}

// The parts of the line of a tile as the native one: year, country, 2 genres, age,
// runtime, rating; words of the server only, the empty ones left out.
/**
 * @param AioCatTile $t
 * @param string $lang
 * @return list<string>
 */
function aio_catv_meta($t, $lang)
{
    $genres = array();
    foreach (array_slice($t['genres'], 0, 2) as $g)
        $genres[] = mb_strtoupper(mb_substr($g, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($g, 1, mb_strlen($g, 'UTF-8'), 'UTF-8');
    $rating = (float) $t['imdbRating'];
    $parts = array($t['year'], aio_catv_country($t['country'], $lang), implode(', ', $genres), $t['age'],
        aio_catv_runtime($t['runtime'], $lang), $rating > 0 ? sprintf('%.1F', $rating) : '');
    return array_values(array_filter($parts, 'strlen'));
}

/**
 * @param string $rid
 * @param AioCatTile $t
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_catv_grid_item($rid, $t, $lang)
{
    $icon = aio_catv_poster($t);
    $text = implode(' • ', array_filter(array(implode(' • ', aio_catv_meta($t, $lang)), aio_error_cut($t['description'], AIO_CATV_DESC_CHARS)), 'strlen'));
    return array(
        PluginRegularFolderItem::media_url => "$rid:{$t['id']}",
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

// The listing of a screen "Show all" ("cat_grid:<rid>", of a search "cat_grid:<rid>:<id of
// the search>"), or null. A search has one rid for every query, so a screen of an older one
// gets nothing. $anew: a row of the manifest not in memory gets a new listing, from the first
// page on; not for the rows of a screen already shown, the listing anew may differ.
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
        return $p[1] !== '' && $p[1] === AioCat::$search['id'] && in_array($rid, AioCat::$search['rids'], true) ? $list : null;
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
        $start = microtime(true);
        $out_of_time = false;
        while (count($list['tiles']) <= $from && $list['st']['end'] === '')
        {
            $left = AIO_CATV_GRID_S - (microtime(true) - $start);
            if ($left < AIO_CATV_MIN_S)
            {
                $out_of_time = true;
                break;
            }
            $res = aio_cat_more($list['st'], $left);
            aio_catv_add($list, $res);
            if ($res['err'] !== '')
                break;
        }
        AioCat::$lists[$rid] = $list;
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
 * @param list<array<string, string>> $items
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
        $items[] = aio_catv_item("$rid:{$t['id']}", $t['name'], aio_catv_poster($t));
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

// The screen of the rows $rows: the pane of get_rows_pane of movie_list_screen.php r25, the
// panel and the template of the catalog. $dup: row_dup_map, null without stubs.
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
        PluginRowsPane::info_r => array('w' => AIO_CATV_PANE_W, 'h' => AIO_CATV_PANE_H, 'x' => 100, 'y' => 40),
        PluginRowsPane::vod_r => array('w' => 1280, 'h' => 720, 'x' => 640, 'y' => 0),
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
            PluginRowsFolderView::actions => aio_catv_actions($url),
            PluginRowsFolderView::timer => null));
}

// The screen "Show all" (aio_catv_all_list). Its first row is never flagged (a flagged
// first row covered the rows under it, 0.50.0) and never changes: the end not known, it is
// filled to 9 tiles here, in AIO_CATV_GRID_S. The end known: its rows only, the last one
// short. Else the full rows in memory, then stubs up to AIO_CATV_ALL_ROWS. No tile at all:
// one tile "empty"; the first row not filled: its tiles and "did not load", ENTER on it opens
// the screen anew.
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
    while (count($list['tiles']) < AIO_CATV_ALL_COLS && $list['st']['end'] === '')
    {
        $left = AIO_CATV_GRID_S - (microtime(true) - $start);
        if ($left < AIO_CATV_MIN_S)
            break;
        $res = aio_cat_more($list['st'], $left);
        aio_catv_add($list, $res);
        AioCat::$lists[$rid] = $list;
        if ($res['err'] !== '')
            break;
    }
    $n = count($list['tiles']);
    // Past the last row the screen has, the listing ends for it.
    $end = $list['st']['end'] !== '' || $n >= AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS;
    $rows = array();
    $dup = null;
    if ($n === 0 || ($n < AIO_CATV_ALL_COLS && !$end))
    {
        $row = aio_catv_all_row($rid, 0, $list);
        $row[PluginRow::data][PluginRegularRow::items][] = aio_catv_item(($end ? 'empty:' : 'fail:') . $rid,
            aio_tr($lang, $end ? 'cat_row_empty' : 'cat_row_failed'), AIO_CATV_ICONS . 'no_movie_poster.png');
        $rows[] = $row;
    }
    else if ($end)
    {
        for ($k = 0; $k < min(AIO_CATV_ALL_ROWS, intval(ceil($n / AIO_CATV_ALL_COLS))); $k++)
            $rows[] = aio_catv_all_row($rid, $k, $list);
    }
    else
    {
        $full = intval($n / AIO_CATV_ALL_COLS);
        for ($k = 0; $k < AIO_CATV_ALL_ROWS; $k++)
            $rows[] = $k < $full ? aio_catv_all_row($rid, $k, $list) : aio_catv_all_stub($k);
        $items = array();
        for ($j = 0; $j < AIO_CATV_ALL_COLS; $j++)
            $items[] = aio_catv_item(".$j", aio_tr($lang, 'cat_row_loading'), AIO_CATV_ICONS . 'loading_movie_poster.png');
        // The shell puts the id of the dup row before the ids of its tiles: "row<k>.<j>".
        $dup = array('ld' => aio_catv_all_def('loading', $items, aio_catv_loading_flag()));
    }
    aio_log(sprintf('catalog: show all: %d tiles, %d rows%s, %.0f ms', $n, count($rows), $end ? ', the end' : '',
        (microtime(true) - $start) * 1000));
    return aio_catv_all_pane($rows, $dup, $sel_state, $url);
}

// get_folder_rows of "Show all", from memory: the rows asked that are full; once the end is
// known also the last short row and 0 tiles for every row past it, asked or not, in every
// reply (the shell throws away a reply it cancelled; it does not draw a row of 0 tiles).
// Nothing to give yet: the rows asked as stubs again. Never an empty reply: the shell would
// ask again at once. No listing (php_server restarted, an older search): 0 tiles from the
// first row asked on; an id not of a row of the screen: 0 tiles with that id.
/**
 * @param string $url
 * @param array<mixed> $ids
 * @return non-empty-list<array<string, mixed>>
 */
function aio_catv_all_rows($url, $ids)
{
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
        $n = count($list['tiles']);
        $end = $list['st']['end'] !== '' || $n >= AIO_CATV_ALL_COLS * AIO_CATV_ALL_ROWS;
        foreach ($asked as $k)
        {
            if ($end || AIO_CATV_ALL_COLS * ($k + 1) <= $n)
                $out[] = aio_catv_all_row($rid, $k, $list);
        }
        if (!$out)
        {
            foreach ($asked as $k)
                $out[] = aio_catv_all_stub($k);
        }
        if ($end)
        {
            for ($k = intval($n / AIO_CATV_ALL_COLS); $k < AIO_CATV_ALL_ROWS; $k++)
            {
                if (!in_array($k, $asked, true))
                    $out[] = aio_catv_all_row($rid, $k, $list);
            }
        }
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

// The panel of the native catalog, on its coordinates (movie_helper.php, the same info_r):
// the title, then lines 46 px high from y 199 in steps of 50, the description 7 px above
// where the next line would be, as many lines as the rest of the panel holds, as the
// native one counts them; no line for an empty field, the next ones go up. The English name of the
// tile under the title when it is not the title (native_title of dunemdb_cache.php: of a title not in
// English, as those of the catalog are in any interface): the title at y 20, the name at 144, the
// lines from 202 (20 + 120 + 7 + 55). Without it the title takes its place, the bottom at y 186.
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
    $rid = strpos($id, 'more:') === 0 ? strval(substr($id, strlen('more:'))) : '';
    if ($t)
    {
        $name = $t['name'];
        $sub = $t['englishTitle'] !== $name ? $t['englishTitle'] : '';
        $lines = array(array(implode(' | ', array_map('aio_clean', aio_catv_meta($t, aio_catv_ui_lang()))), AIO_CATV_TEXT_COLOR),
            array(aio_clean(implode(', ', $t['cast'])), AIO_CATV_CAST_COLOR));
        $desc = $t['description'];
    }
    else if (isset(AioCat::$lists[$rid]))
    {
        $name = AioCat::$lists[$rid]['st']['row']['name'];
        $sub = '';
        $lines = array(array(aio_clean(aio_tr(aio_catv_ui_lang(), 'cat_more')), AIO_CATV_TEXT_COLOR));
        $desc = '';
    }
    else
        return null;
    $labels = array();
    $name = aio_clean($name);
    // Cut here to 2 lines of the least size, "…" on the last one; the shell sizes it down to them.
    $sub = implode(' ', aio_gc_wrap(aio_clean($sub), AIO_CATV_SUB_MIN_PX, AIO_CATV_TEXT_W, 2, true));
    if ($name !== '')
    {
        $fit = aio_catv_fit($name, AIO_CATV_TITLE_PX, AIO_CATV_TITLE_MIN_PX, AIO_CATV_PANE_W);
        $labels[] = aio_catv_info_label($fit[1], $fit[0], AIO_CATV_TITLE_COLOR, AIO_CATV_PANE_W, 120, $sub !== '' ? 20 : 66,
            array(GCompTtfLabelDef::line_spacing => 0, GCompTtfLabelDef::valign => VALIGN_BOTTOM),
            GCOMP_OPT_TTF_LAYOUT_FIX | GCOMP_OPT_TTF_COMPACT_HEIGHT);
    }
    if ($sub !== '')
        $labels[] = aio_catv_info_label($sub, AIO_CATV_SUB_PX, AIO_CATV_SUB_COLOR, AIO_CATV_TEXT_W, 46, 144,
            array(GCompTtfLabelDef::max_num_lines => 2, GCompTtfLabelDef::min_font_size => AIO_CATV_SUB_MIN_PX,
                GCompTtfLabelDef::line_spacing => 0));
    $y = 202;
    foreach ($lines as $l)
    {
        if ($l[0] === '')
            continue;
        $fit = aio_catv_fit($l[0], AIO_CATV_LINE_PX, AIO_CATV_LINE_MIN_PX, AIO_CATV_TEXT_W);
        $labels[] = aio_catv_info_label($fit[1], $fit[0], $l[1], AIO_CATV_TEXT_W, 46, $y - 3);
        $y += 50;
    }
    // Lines of 30 px 5 px apart; cut here to them, "…" on the last one; the shell, with a
    // little more room, breaks them no sooner.
    $y -= 10;
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
        PluginRowsInfo::bg_url => $t ? aio_catv_background($t) : AIO_CATV_ICONS . 'bg0.jpg',
        PluginRowsInfo::nl_bg_url => null,
        PluginRowsInfo::mask_url => AIO_CATV_ICONS . 'mask.png',
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
    if (strpos($id, 'more:') === 0)
    {
        $rid = substr($id, strlen('more:'));
        $url = in_array($rid, AioCat::$search['rids'], true) ? "cat_grid:$rid:" . AioCat::$search['id'] : "cat_grid:$rid";
        return aio_catv_open_act($url, isset(AioCat::$lists[$rid]) ? AioCat::$lists[$rid]['st']['row']['name'] :
            aio_tr(aio_catv_lang(), 'cat_title'));
    }
    // A stub of "Show all": row<k>.<j>.
    if (strpos($id, 'stub:') === 0 || strpos($id, 'empty:') === 0 || preg_match('/^row[0-9]+\.[0-9]+$/D', $id))
        return null;
    // The opening tries again, and the rows after it too; the shell brings sel_state, the focus stays.
    if (strpos($id, 'fail:') === 0)
    {
        AioCat::$down = 0;
        return array(
            GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
            GuiAction::data => array(
                PluginInvalidateFoldersActionData::media_urls => array(aio_catv_in($in, 'from')),
                PluginInvalidateFoldersActionData::post_action => null,
                PluginInvalidateFoldersActionData::all_except => false));
    }
    $t = aio_catv_tile($id);
    if (!$t)
    {
        aio_log('catalog: ENTER: no such tile in memory');
        return aio_error('cat_outdated');
    }
    return aio_card_enter($t, aio_catv_lang());
}

// The dialog of a search; the field goes to cat_search_apply and on as cat_q. The
// query is in no action and no address: only in what the shell sends back.
/**
 * @param string $from
 * @return AioAction
 */
function aio_catv_search_ask($from)
{
    $p = array('from' => $from);
    return aio_dialog_act(aio_tr(aio_catv_lang(), 'cat_search'), array(
        aio_ctl('cat_q', GUI_CONTROL_TEXT_FIELD, array(
            GuiTextFieldDef::initial_value => '',
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
    if (aio_one_line($q) === '')
        return null;
    $lang = aio_catv_lang();
    // The no-break space at the end of cat_movies, cat_series is on purpose, in place of the number (the owner's choice); editors drop it as trailing whitespace: keep it.
    $found = aio_cat_search($q, array('movie' => aio_tr($lang, 'cat_movies'), 'series' => aio_tr($lang, 'cat_series')));
    $rids = array();
    $failed = false;
    foreach ($found as $f)
    {
        $res = $f['res'];
        $failed = $failed || $res['err'] !== '';
        if (!$res['tiles'])
            continue;
        $rid = aio_catv_rid($f['st']['row']);
        AioCat::$lists[$rid] = aio_catv_list($f['st'], $res['tiles'], $res['stale']);
        $rids[] = $rid;
    }
    if (!$rids)
        return aio_error($failed ? 'cat_search_failed' : 'cat_search_none');
    AioCat::$search = array('id' => substr(aio_rid(), 0, 8), 'rids' => $rids);
    $url = 'cat_search:' . AioCat::$search['id'];
    $caption = aio_tr($lang, 'cat_results');
    return $from === 'results' ? aio_replace_act($url, $caption) : aio_catv_open_act($url, $caption);
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
    if ($control === 'cat_retry')
    {
        AioCat::$empty = array();
        AioCat::$down = 0;
        return aio_replace_act('catalog:' . substr(aio_rid(), 0, 8), aio_tr(aio_catv_lang(), 'cat_title'));
    }
    if ($control === 'cat_search')
        return aio_catv_search_ask($from);
    if ($control === 'cat_search_apply')
        return aio_close_and(aio_input('cat_search_run', array('from' => $from)));
    if ($control === 'cat_search_run')
        return aio_catv_search_run(aio_catv_in($in, 'cat_q'), $from);
    if ($control === 'cat_menu')
    {
        $lang = aio_catv_lang();
        return array(
            GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
            GuiAction::data => array(ShowPopupMenuActionData::menu_items => array(
                array(
                    GuiMenuItemDef::caption => aio_tr($lang, 'cat_search'),
                    // No magnifier in the firmware small_icons: our own in their style
                    // (shell_ext puts plugin_file:// PNGs into menu items too).
                    GuiMenuItemDef::icon_url => 'plugin_file://img/menu_search.png',
                    GuiMenuItemDef::action => aio_input('cat_search', array('from' => $from))),
                array(
                    GuiMenuItemDef::caption => aio_tr($lang, 'setup_menu'),
                    GuiMenuItemDef::icon_url => 'gui_skin://small_icons/setup.aai',
                    GuiMenuItemDef::action => aio_setup_open_act($from)))));
    }
    return null;
}
