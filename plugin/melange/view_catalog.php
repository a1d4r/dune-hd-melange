<?php
// Screens of the catalog: the rows (media_url "catalog"), a row in full
// ("cat_grid:<rid>"), search and its results ("cat_search:<id>"). Data: catalog.php,
// ENTER on a tile: catalog_card.php. rid: a short hash of the key of a row; the id of a
// tile is "<rid>:<id of the title>", so it stays the same after a rebuild or a restart.
// Types in the PHPDoc (AioCatList and others): phpstan-types.neon of the repository.

// Seconds for rows at an opening, with the refresh of the manifest: what does not
// make it stays hidden until the next one. A cold row takes up to 4.5 s, 6 rows 2.5 s.
define('AIO_CATV_OPEN_S', 3.0);
// The first manifest and a portion of the grid. The timeout of these operations is
// not known (10 s by the SDK): they stay under it.
define('AIO_CATV_MANIFEST_S', 6);
define('AIO_CATV_GRID_S', 8.0);
// Less than this left: no more requests.
define('AIO_CATV_MIN_S', 0.3);
define('AIO_CATV_ROW_TILES', 20);
define('AIO_CATV_GRID_PORTION', 50);
// The info panel. The lines end well above the title of the row at y = 600; longer
// texts are cut here: names up to 197, descriptions up to 1096 characters were seen.
define('AIO_CATV_NAME_CHARS', 54);
define('AIO_CATV_META_CHARS', 55);
define('AIO_CATV_DESC_CHARS', 360);
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
    $st = aio_cat_row_state($row);
    $res = aio_cat_result();
    $st['t0'] = $c['t'];
    aio_cat_take($st, $res, $c['n'], $c['data']);
    return aio_catv_list($st, $res['tiles'], false);
}

// The tile of an id of the screen, memory only, or null.
/**
 * @param string $id
 * @return AioCatTile|null
 */
function aio_catv_tile($id)
{
    $p = explode(':', $id, 2);
    return count($p) === 2 && isset(AioCat::$lists[$p[0]]['tiles'][$p[1]]) ? AioCat::$lists[$p[0]]['tiles'][$p[1]] : null;
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
    $u = aio_img(strval(preg_replace('~/t/p/[a-z0-9_]+/~', '/t/p/w780/', $t['background'])));
    return $u !== '' ? $u : AIO_CATV_ICONS . 'bg0.jpg';
}

// --- What to give at an opening.

// The rows $rows for a new screen, into AioCat::$lists. At once: listings in memory
// still fresh, then the saved first pages of any age. In the time left, one by one:
// rows without a copy, then the old copies; on a failure of the server no more
// requests. No tile: the row stays out of the lists and is hidden.
/**
 * @param list<AioCatRow> $rows
 * @return void
 */
function aio_catv_open($rows)
{
    $none = array();
    $old = array();
    foreach ($rows as $row)
    {
        $rid = aio_catv_rid($row);
        if (isset(AioCat::$lists[$rid]) && aio_cat_is_fresh(array('t' => AioCat::$lists[$rid]['st']['t0'])))
            continue;
        unset(AioCat::$lists[$rid]);
        $c = aio_cat_page_cached($row, 0);
        if (!$c)
            $none[$rid] = $row;
        else
        {
            AioCat::$lists[$rid] = aio_catv_list_copy($row, $c);
            if (!aio_cat_is_fresh($c))
                $old[$rid] = $row;
        }
    }
    $start = microtime(true);
    $failed = false;
    foreach ($none + $old as $rid => $row)
    {
        $left = AIO_CATV_OPEN_S - (microtime(true) - $start);
        if ($left < AIO_CATV_MIN_S)
            break;
        $st = aio_cat_row_state($row);
        $res = aio_cat_more($st, $left);
        if ($res['tiles'] && !$res['stale'])
        {
            AioCat::$lists[$rid] = aio_catv_list($st, $res['tiles'], false);
            unset($old[$rid]);
        }
        $failed = $failed || $res['err'] !== '';
        if (in_array($res['err'], array('net', 'rate', 'server'), true))
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

// A row of the panel: its title and tiles, the first page and "Show all".
/**
 * @param AioCatList $list
 * @param string $lang
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_catv_row_defs($list, $lang)
{
    $row = $list['st']['row'];
    $rid = aio_catv_rid($row);
    $title = $list['stale'] ? $row['name'] . ' (' . aio_tr($lang, 'cat_saved') . ')' : $row['name'];
    $items = array();
    foreach (array_slice($list['tiles'], 0, AIO_CATV_ROW_TILES) as $t)
        $items[] = aio_catv_item("$rid:{$t['id']}", $t['name'], aio_catv_poster($t));
    if ($list['st']['end'] === '' || count($list['tiles']) > AIO_CATV_ROW_TILES)
        $items[] = aio_catv_item("more:$rid", aio_tr($lang, 'cat_more'), AIO_CATV_ICONS . 'more.png');
    return array(
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

// The actions of the rows. $from: 'results' on the results of a search, a new search
// replaces them.
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
        GUI_EVENT_KEY_ENTER => aio_input('cat_enter'),
        GUI_EVENT_KEY_POPUP_MENU => aio_input('cat_menu', $p),
        GUI_EVENT_KEY_SEARCH => aio_input('cat_search', $p),
        GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE => $info);
}

// The screen of rows of the listings $lists, none of them without tiles. The key set
// of RowsFactory::pane r25, nulls too; row_dup_map: a literal, the r24 API has no such
// constant.
/**
 * @param list<AioCatList> $lists
 * @param mixed $sel_state
 * @param string $lang
 * @param string $from
 * @return array<string, mixed>
 */
function aio_catv_rows_view($lists, $sel_state, $lang, $from)
{
    $rows = array(array(PluginRow::type => PLUGIN_ROW_TYPE_VGAP, PluginRow::height => 10, PluginRow::inactive_height => 10));
    foreach ($lists as $list)
    {
        foreach (aio_catv_row_defs($list, $lang) as $def)
            $rows[] = $def;
    }
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
        PluginRowsPane::info_r => array('w' => 950, 'h' => 560, 'x' => 100, 'y' => 40),
        PluginRowsPane::vod_r => array('w' => 1280, 'h' => 720, 'x' => 640, 'y' => 0),
        PluginRowsPane::regular_item_params_templates => array('common' => array(
            PluginRegularItemParams::def => aio_catv_item_vars(200, 302, 0, 190, 285, 7, 10, '#FFAFAFA0'),
            PluginRegularItemParams::sel => aio_catv_item_vars(200, 302, 0, 200, 300, 0, 10, '#FFFFFFE0'),
            PluginRegularItemParams::inactive => aio_catv_item_vars(200, 300, 5, 190, 285, 0, 0, '#00000000'),
            PluginRegularItemParams::loading_url => AIO_CATV_ICONS . 'loading_movie_poster.png',
            PluginRegularItemParams::load_failed_url => AIO_CATV_ICONS . 'no_movie_poster.png',
            PluginRegularItemParams::caption_max_num_lines => 3,
            PluginRegularItemParams::caption_line_spacing => 3,
            PluginRegularItemParams::sticker_text_size => 25,
            PluginRegularItemParams::sel_margins => array(
                PluginMargins::left => 3, PluginMargins::top => 3,
                PluginMargins::right => 3, PluginMargins::bottom => 4))));
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

/**
 * @param string $media_url
 * @return bool
 */
function aio_catv_is_screen($media_url)
{
    return $media_url === 'catalog' || strpos($media_url, 'catalog:') === 0 || strpos($media_url, 'cat_grid:') === 0 ||
        strpos($media_url, 'cat_search:') === 0;
}

/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_folder_view($media_url, $sel_state)
{
    if (strpos($media_url, 'cat_grid:') === 0)
        return aio_catv_grid_view(substr($media_url, strlen('cat_grid:')));
    if (strpos($media_url, 'cat_search:') === 0)
        return aio_catv_results_view(substr($media_url, strlen('cat_search:')), $sel_state);
    return aio_catv_main_view($sel_state);
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

// The manifest does not hold the screen: a copy of any age goes, and the refresh waits
// for the time left after the rows, for the next opening. No copy: one request.
/**
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_main_view($sel_state)
{
    $lang = aio_catv_lang();
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
    $start = microtime(true);
    aio_catv_open($rows);
    $lists = array();
    foreach ($rows as $row)
    {
        $rid = aio_catv_rid($row);
        if (isset(AioCat::$lists[$rid]))
            $lists[] = AioCat::$lists[$rid];
    }
    $left = AIO_CATV_OPEN_S - (microtime(true) - $start);
    if (!$fresh && $left >= 1)
        aio_cat_manifest_fetch(intval($left));
    aio_log(sprintf('catalog: screen: %d rows of %d, %.0f ms', count($lists), count($rows), (microtime(true) - $start) * 1000));
    return $lists ? aio_catv_rows_view($lists, $sel_state, $lang, 'catalog') : aio_catv_message_view('cat_unavailable', $lang);
}

/**
 * @param string $id
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_catv_results_view($id, $sel_state)
{
    $lists = array();
    foreach (AioCat::$search['id'] === $id ? AioCat::$search['rids'] : array() as $rid)
    {
        if (isset(AioCat::$lists[$rid]))
            $lists[] = AioCat::$lists[$rid];
    }
    if (!$lists)
    {
        aio_log('catalog: search screen outdated');
        return aio_lines_view(array(aio_expired_item()));
    }
    return aio_catv_rows_view($lists, $sel_state, aio_catv_lang(), 'results');
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

// year, rating, genres of a tile: words of the server only, the panel reads no file.
/**
 * @param AioCatTile $t
 * @return string
 */
function aio_catv_meta($t)
{
    $parts = array();
    if ($t['year'] !== '')
        $parts[] = $t['year'];
    if ($t['imdbRating'] !== '')
        $parts[] = 'IMDb ' . $t['imdbRating'];
    if ($t['genres'])
        $parts[] = implode(', ', array_slice($t['genres'], 0, 3));
    return implode(' • ', $parts);
}

/**
 * @param string $rid
 * @param AioCatTile $t
 * @return array<string, mixed>
 */
function aio_catv_grid_item($rid, $t)
{
    $icon = aio_catv_poster($t);
    $text = implode(' • ', array_filter(array(aio_catv_meta($t), aio_error_cut($t['description'], AIO_CATV_DESC_CHARS)), 'strlen'));
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
// holds the old tiles, so they stay and are not new any more.
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
    {
        foreach ($list['tiles'] as $id => $t)
            $list['st']['seen'][strval($id)] = true;
    }
}

// The items of the grid from $from on. No listing in memory: a new one, from the first
// page to $from. A failure or no time: what there is; with nothing, the list ends for
// now, so the shell does not ask in a loop. ENTER on "Show all" goes on from there.
/**
 * @param string $rid
 * @param int $from
 * @return array<string, mixed>
 */
function aio_catv_range($rid, $from)
{
    if (!isset(AioCat::$lists[$rid]))
    {
        $row = aio_catv_row_by_rid($rid);
        if ($row)
            AioCat::$lists[$rid] = aio_catv_list(aio_cat_row_state($row), array(), false);
    }
    $items = array();
    $more = false;
    if (isset(AioCat::$lists[$rid]))
    {
        $list = AioCat::$lists[$rid];
        $start = microtime(true);
        while (count($list['tiles']) <= $from && $list['st']['end'] === '')
        {
            $left = AIO_CATV_GRID_S - (microtime(true) - $start);
            if ($left < AIO_CATV_MIN_S)
                break;
            $res = aio_cat_more($list['st'], $left);
            aio_catv_add($list, $res);
            if ($res['err'] !== '')
                break;
        }
        AioCat::$lists[$rid] = $list;
        foreach (array_slice($list['tiles'], $from, AIO_CATV_GRID_PORTION) as $t)
            $items[] = aio_catv_grid_item($rid, $t);
        $more = $items && ($list['st']['end'] === '' || count($list['tiles']) > $from + count($items));
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
    return strpos($media_url, 'cat_grid:') === 0 ? aio_catv_range(substr($media_url, strlen('cat_grid:')), $from) : null;
}

/**
 * @param string $rid
 * @return array<string, mixed>
 */
function aio_catv_grid_view($rid)
{
    $range = aio_catv_range($rid, 0);
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

// --- The info panel: on every focus move, from memory only.

/**
 * @param string $text
 * @param int $size
 * @param string $color
 * @param int $y
 * @param int $lines
 * @return array<string, mixed>
 */
function aio_catv_info_label($text, $size, $color, $y, $lines)
{
    $spec = array(
        GCompTtfLabelDef::text => $text,
        GCompTtfLabelDef::font_size => $size,
        GCompTtfLabelDef::text_color => $color);
    if ($lines > 1)
        $spec[GCompTtfLabelDef::max_num_lines] = $lines;
    return array(
        GComponentDef::geom_def => array(
            GCompGeometryDef::w => 800,
            GCompGeometryDef::h => -1,
            GCompGeometryDef::align_def => array(GCompAlignDef::x => 0, GCompAlignDef::y => $y)),
        GComponentDef::kind => GCOMPONENT_TTF_LABEL,
        GComponentDef::specific_def => $spec,
        GComponentDef::options => GCOMP_OPT_TTF_LAYOUT_FIX | GCOMP_OPT_TTF_COMPACT_HEIGHT);
}

// null without a tile in memory ("Show all", a restart of php_server).
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_catv_info($in)
{
    $id = aio_catv_in($in, 'item_id');
    $t = aio_catv_tile($id);
    if (!$t)
        return null;
    $labels = array(
        aio_catv_info_label(aio_clean(aio_error_cut($t['name'], AIO_CATV_NAME_CHARS)), 52, '#FFFFFFE0', 30, 2),
        aio_catv_info_label(aio_clean(aio_error_cut(aio_catv_meta($t), AIO_CATV_META_CHARS)), 32, '#FFAFAFA0', 170, 1),
        aio_catv_info_label(aio_clean(aio_error_cut($t['description'], AIO_CATV_DESC_CHARS)), 28, '#FF808080', 225, 7));
    $pane = array(
        GComponentDef::geom_def => array(
            GCompGeometryDef::w => 950,
            GCompGeometryDef::h => 560,
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
        PluginRowsInfo::bg_url => aio_catv_background($t),
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
 * @return AioAction
 */
function aio_catv_enter($in)
{
    $id = aio_catv_in($in, 'selected_item_id');
    if ($id === '')
        $id = aio_catv_in($in, 'selected_media_url');
    if (strpos($id, 'more:') === 0)
    {
        $rid = substr($id, strlen('more:'));
        return aio_catv_open_act("cat_grid:$rid", isset(AioCat::$lists[$rid]) ? AioCat::$lists[$rid]['st']['row']['name'] :
            aio_tr(aio_catv_lang(), 'cat_title'));
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
    $found = aio_cat_search($q);
    $rids = array();
    $failed = false;
    foreach (array('movie' => 'cat_movies', 'series' => 'cat_series') as $type => $key)
    {
        $res = $found[$type];
        $failed = $failed || $res['err'] !== '';
        $row = aio_cat_row($type, "melange.search.$type", aio_tr($lang, $key), '', false);
        if (!$row || !$res['tiles'])
            continue;
        $st = aio_cat_row_state($row);
        $st['end'] = 'search';
        $rid = aio_catv_rid($row);
        AioCat::$lists[$rid] = aio_catv_list($st, $res['tiles'], $res['stale']);
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
        return aio_replace_act('catalog:' . substr(aio_rid(), 0, 8), aio_tr(aio_catv_lang(), 'cat_title'));
    if ($control === 'cat_search')
        return aio_catv_search_ask($from);
    if ($control === 'cat_search_apply')
        return aio_close_and(aio_input('cat_search_run', array('from' => $from)));
    if ($control === 'cat_search_run')
        return aio_catv_search_run(aio_catv_in($in, 'cat_q'), $from);
    if ($control === 'cat_menu')
        return array(
            GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
            GuiAction::data => array(ShowPopupMenuActionData::menu_items => array(array(
                GuiMenuItemDef::caption => aio_tr(aio_catv_lang(), 'cat_search'),
                GuiMenuItemDef::icon_url => 'gui_skin://small_icons/info.aai',
                GuiMenuItemDef::action => aio_input('cat_search', array('from' => $from))))));
    return null;
}
