<?php
// The section "На TorrServer" on the screens (its data: ts_list.php). The way to it: an icon on the strip of the catalog
// after the heart and its item of POP UP (mel_catv_strip_icons; none with "Без торрентов", mel_tsl_on). The screen
// "cat_ts" as "Избранное": on r25 rows of MEL_CATV_ALL_COLS with the info panel of the catalog, on r24 and r22 the grid
// of "Show all". A tile is a torrent, "tors:<hash>": its poster, none - the plate of the section with its title (2E of
// knowledge/melange/design-torrserver-section.md of the kitchen); an empty list - the hint tile (4A). The info panel of a
// torrent (2C): its title up to " [" or " (", the facts, the day it was added, the rest of its name smaller.
// The list is read by the input that opens the screen (the icon, the item, "Повторить"): an error there - the kit error
// screen over the screen it came from (mel_ks_rows_error); a build of the screen takes the list in memory within
// MEL_TSL_FRESH_S of its read, else reads it anew (an error then: a tile of it). ENTER on a torrent: a line of the log
// alone for now (the start: stage 3a). POP UP on a tile: none (no IMDb, no TMDB).
// Needs ts_list.php, view_catalog.php, view_my_list.php (mel_mlv_is_screen), kit.php (MelKit::wrap, w), kit_scr.php,
// view_setup.php, view_card.php (mel_oc_day), view_gcomps.php (mel_gc_plural), main.php. PHP 5.3.6, firmware API only.

// The address of the screen; the rid of its tiles of torrents ("tors:<hash>"); the prefix of its other tiles: the hint
// of an empty list, a tile of an error of a build of the screen ("tsl:err:<key>").
define('MEL_TSL_URL', 'cat_ts');
define('MEL_TSL_RID', 'tors');
define('MEL_TSL_OWN', 'tsl:');
define('MEL_TSL_HINT', MEL_TSL_OWN . 'hint');
define('MEL_TSL_ERR', MEL_TSL_OWN . 'err:');
// The icon of the strip, of its item of POP UP and of the plates: L1 of the designer, 64x64, the glyph 48x48 at 8, 8 as
// the icons of the shell (the N of tsl<N> goes up when it changes: the shell keeps a picture by its URL; made by
// experiments/ts_section_mock of the kitchen).
define('MEL_TSL_ICON', 'plugin_file://img/tsl1_logo.png');
// A build of the screen takes the list in memory within this many s of its read.
define('MEL_TSL_FRESH_S', 10);

// The state of the screen (the data: MelTsl of ts_list.php): when the list in memory was read (microtime; 0: none), the
// detail of the error of the last build (tsl_err_bad: the code, "not a list"; '' none).
class MelTslView
{
    /** @var float */
    public static $read = 0.0;
    /** @var string */
    public static $detail = '';
}

// The icon and the item of the section: always, but with "Без торрентов" on (owner 23.11.2026).
/**
 * @return bool
 */
function mel_tsl_on()
{
    return !Mel::$settings['no_torrents'];
}

// The icon, its item of POP UP, "Повторить" (cat_tsl_open) on screen $from: the list read (mel_tsl_load); an error - the
// kit error screen over $from (mel_tsl_error); else the screen over $from, in place of a screen of Fav (as
// mel_mlv_open); on the screen itself (its tile of an error) the screen built anew.
/**
 * @param string $from
 * @return MelAction
 */
function mel_tsl_open($from)
{
    list($list, $key, $detail) = mel_tsl_read();
    if ($list === null)
        return mel_tsl_error($key, $detail, $from);
    if ($from === MEL_TSL_URL)
    {
        mel_log('ts section: open: anew');
        return mel_catv_invalidate(MEL_TSL_URL);
    }
    $replace = mel_mlv_is_screen($from);
    mel_log('ts section: open' . ($replace ? ' in place' : ''));
    $caption = mel_tr(mel_catv_lang(), 'tsl_title');
    return $replace ? mel_replace_act(MEL_TSL_URL, $caption) : mel_catv_open_act(MEL_TSL_URL, $caption);
}

// The list read (mel_tsl_load), the time of it kept (MelTslView::$read).
/**
 * @return array{list<MelTsEntry>|null, string, string}
 */
function mel_tsl_read()
{
    $r = mel_tsl_load();
    if ($r[0] !== null)
        MelTslView::$read = microtime(true);
    return $r;
}

// The title (the cause) and the text (what to do) of error $key of the list (mel_tsl_load) in language $lang (4B, 4C of
// the designer); the detail $detail of tsl_err_bad (the code, "not a list") after the text, none of another error (no
// answer: the error of curl).
/**
 * @param string $key
 * @param string $lang
 * @param string $detail
 * @return array{string, string}
 */
function mel_tsl_err_texts($key, $lang, $detail)
{
    $names = array('ts_err_down' => 'down', 'ts_err_auth' => 'auth', 'ts_err_auth_bad' => 'auth_bad', 'tsl_err_big' => 'big',
        'tsl_err_bad' => 'bad', 'ts_err_off' => 'off');
    $n = isset($names[$key]) ? $names[$key] : 'bad';
    $text = mel_tr($lang, "tsl_{$n}_text");
    return array(mel_tr($lang, "tsl_{$n}_title"), $n === 'bad' && $detail !== '' ? "$text $detail" : $text);
}

// The kit error screen of error $key of the list (mel_tsl_load; $detail: its detail) over screen $from (4B, 4C of the
// designer): its title and text (mel_tsl_err_texts); no answer - «Повторить», «Настройки» and the address
// of TorrServer under them; not a list - «Повторить», «Настройки» and the detail; over 4 MB - none of its own; the
// password, «Без торрентов» - «Настройки». «Назад» after them (mel_ks_rows_error).
/**
 * @param string $key
 * @param string $detail
 * @param string $from
 * @return MelAction
 */
function mel_tsl_error($key, $detail, $from)
{
    $lang = mel_catv_lang();
    $retry = array('label' => mel_tr($lang, 'ks_retry'), 'icon' => 'k_icon_rotate-ccw',
        'act' => mel_input('cat_tsl_open', array('from' => $from)));
    $setup = array('label' => mel_tr($lang, 'setup_menu'), 'icon' => 'k_icon_settings', 'act' => mel_setup_open_act($from));
    $own = array('ts_err_down' => array($retry, $setup), 'tsl_err_bad' => array($retry, $setup), 'tsl_err_big' => array());
    $d = array('buttons' => isset($own[$key]) ? $own[$key] : array($setup));
    if ($key === 'ts_err_down')
        $d['detail'] = str_replace('{url}', Mel::$settings['ts'], mel_tr($lang, 'tsl_addr'));
    else if ($key === 'tsl_err_bad')
        $d['detail'] = $detail;
    mel_log("ts section: error $key");
    list($title, $text) = mel_tsl_err_texts($key, $lang, '');
    return mel_ks_rows_error($lang, mel_tr($lang, 'tsl_title'), $title, $text, $d);
}

// The screen (get_folder_view of MEL_TSL_URL): the list in memory within MEL_TSL_FRESH_S of its read (a second build of
// one open too), else read anew; its tiles in its order (mel_tsl_item), none - the hint, an error - a tile of it (its
// detail in its panel; ENTER: read anew). r25: rows of MEL_CATV_ALL_COLS as "Show all" (the info panel, ENTER, POP UP,
// the search); r24, r22: the grid of "Show all".
/**
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_tsl_view($sel_state)
{
    $start = microtime(true);
    $lang = mel_catv_lang();
    MelCat::$lang = $lang;
    $ago = microtime(true) - MelTslView::$read;
    $kept = MelTsl::$list !== null && $ago >= 0 && $ago < MEL_TSL_FRESH_S;
    $key = '';
    $detail = '';
    if ($kept)
        $list = array_values(MelTsl::$list);
    else
        list($list, $key, $detail) = mel_tsl_read();
    MelTslView::$detail = $detail;
    $none = MEL_CATV_ICONS . 'no_movie_poster.png';
    $rows_ok = mel_catv_all_ok();
    $items = array();
    $grid = array();
    if ($list === null)
    {
        $id = mel_catv_tile_id(MEL_TSL_ERR . $key);
        list($title, $text) = mel_tsl_err_texts($key, $lang, $detail);
        $items[] = mel_catv_item($id, $title, $none);
        $grid[] = mel_tsl_grid_item($id, $title, $none, $title, $text);
    }
    else if (!$list)
    {
        $id = mel_catv_tile_id(MEL_TSL_HINT);
        $title = mel_tr($lang, 'tsl_title');
        $item = mel_catv_item($id, $title, $none);
        $item[PluginRegularItem::stickers] = mel_tsl_plate(mel_tr($lang, 'tsl_empty'), true);
        $items[] = $item;
        $grid[] = mel_tsl_grid_item($id, $title, $none, $title, mel_tr($lang, 'tsl_empty_text'));
    }
    foreach ($list === null ? array() : $list as $e)
    {
        if ($rows_ok)
        {
            $items[] = mel_tsl_item($e);
            continue;
        }
        $poster = mel_img($e['poster']);
        list($head, $lines, $rest) = mel_tsl_texts($e, $lang);
        $grid[] = mel_tsl_grid_item(mel_catv_tile_id(MEL_TSL_RID . ':' . $e['hash']), $e['title'], $poster !== '' ? $poster : $none,
            $head, implode(' · ', array_filter(array($lines[0], $lines[1], $rest), 'strlen')));
    }
    mel_log(sprintf('ts section: screen: %d tiles, %s, %.0f ms', $list === null ? 0 : count($list),
        $kept ? 'the list of the open' : 'the list read', (microtime(true) - $start) * 1000));
    if ($rows_ok)
    {
        $rows = array();
        foreach (array_chunk($items, MEL_CATV_ALL_COLS) as $k => $part)
            $rows[] = mel_catv_all_def("row$k", $part);
        return mel_catv_all_pane($rows, null, $sel_state, MEL_TSL_URL);
    }
    $p = array('from' => MEL_TSL_URL);
    return mel_catv_grid_pane(array(
        PluginRegularFolderRange::total => count($grid),
        PluginRegularFolderRange::more_items_available => false,
        PluginRegularFolderRange::from_ndx => 0,
        PluginRegularFolderRange::count => count($grid),
        PluginRegularFolderRange::items => $grid),
        array(GUI_EVENT_KEY_ENTER => mel_input('cat_enter', $p, Mel::$settings['cat_card'] ? MEL_CARD_DELAY : MEL_DELAY_INPUT),
            GUI_EVENT_KEY_POPUP_MENU => mel_input('cat_menu', $p)) + mel_egg_acts());
}

// The tile of a row of entry $e: its title, its poster (mel_img: http(s)); none - the plate with the title (2E).
/**
 * @param MelTsEntry $e
 * @return array<string, mixed>
 */
function mel_tsl_item($e)
{
    $poster = mel_img($e['poster']);
    $item = mel_catv_item(mel_catv_tile_id(MEL_TSL_RID . ':' . $e['hash']), $e['title'], $poster);
    if ($poster === '')
        $item[PluginRegularItem::stickers] = mel_tsl_plate($e['title'], false);
    return $item;
}

// An item of the grid of r24 (as mel_mlv_grid_item): id $id, caption $caption, picture $icon, its details $title and
// $text.
/**
 * @param string $id
 * @param string $caption
 * @param string $icon
 * @param string $title
 * @param string $text
 * @return array<string, mixed>
 */
function mel_tsl_grid_item($id, $caption, $icon, $title, $text)
{
    return array(
        PluginRegularFolderItem::media_url => $id,
        PluginRegularFolderItem::caption => $caption,
        PluginRegularFolderItem::view_item_params => array(
            ViewItemParams::icon_path => $icon,
            ViewItemParams::item_detailed_icon_path => $icon,
            ViewItemParams::item_detailed_info => mel_clean($title) . '|' . mel_clean($text)));
}

// The stickers of a plate over the whole tile (MEL_PG_W x MEL_PG_H, the shell scales them in focus): the colour of the
// tiles with no picture (MEL_K_C_NO_PHOTO), the icon of the section and $text in lines of MEL_CATV_RATE_PX (the size of
// the stickers of the template), 6 at most, measured by the kit (MelKit::wrap: broken after spaces and separators, names
// of files too). A torrent with no poster (2E): the icon of 40 px in the top left corner, the lines 33 apart under it
// from the left. The hint ($hint, 4A): the icon of 61 px in the middle at the top, the lines 30 apart under it, centred.
// The shell puts a text at the left of its r and a pixel over its middle (mel_catv_rate_stickers).
/**
 * @param string $text
 * @param bool $hint
 * @return list<array<string, mixed>>
 */
function mel_tsl_plate($text, $hint)
{
    $px = MEL_CATV_RATE_PX;
    // The glyph is 3/4 of the picture: r of 54 is a glyph of 40 at 14, of 81 one of 61.
    $out = array(array(PluginRegularSticker::r => mel_pg_r(0, 0, MEL_PG_W, MEL_PG_H), PluginRegularSticker::color => MEL_K_C_NO_PHOTO),
        array(PluginRegularSticker::r => $hint ? mel_pg_r(55, 18, 81, 81) : mel_pg_r(7, 7, 54, 54),
            PluginRegularSticker::icon_url => MEL_TSL_ICON));
    $lines = MelKit::wrap($text, $px, MEL_K_FACE_REGULAR, MEL_PG_W - ($hint ? 20 : 28), 6);
    foreach ($lines as $j => $l)
    {
        $x = $hint ? max(0, intval(round((MEL_PG_W - MelKit::w($l, $px, MEL_K_FACE_REGULAR)) / 2))) : 14;
        $out[] = array(PluginRegularSticker::r => mel_pg_r($x, ($hint ? 100 + 30 * $j : 63 + 33 * $j), MEL_PG_W - $x, 33),
            PluginRegularSticker::text => $l);
    }
    return $out;
}

// The hash of tile $id (of any mode) of a torrent, '' another.
/**
 * @param string $id
 * @return string
 */
function mel_tsl_hash_of($id)
{
    list(, $bare) = mel_catv_id_mode($id);
    return preg_match('/^' . MEL_TSL_RID . ':([0-9a-f]{40})$/D', $bare, $m) ? $m[1] : '';
}

// Tile $id is of the screen: a torrent, the hint, an error.
/**
 * @param string $id
 * @return bool
 */
function mel_tsl_is_tile($id)
{
    list(, $bare) = mel_catv_id_mode($id);
    return strpos($bare, MEL_TSL_RID . ':') === 0 || strpos($bare, MEL_TSL_OWN) === 0;
}

// Title $title as the panel shows it (I2): the head up to the first " [", " (", " / " before a year or "S<n>" (the tags
// of a group at the start, "[Group] ", go to the rest), the rest after it; none of them, or one in the first 3 bytes: the
// title whole and ''.
/**
 * @param string $title
 * @return array{string, string}
 */
function mel_tsl_split($title)
{
    $lead = preg_match('/^(?:\[[^\]]*\]\s*)+/', $title, $m) ? $m[0] : '';
    $body = (string) substr($title, strlen($lead));
    if (!preg_match('~ (?=\[)| (?=\()| / (?=(?:19|20)[0-9]{2}\b)| / (?=S[0-9])~', $body, $m, PREG_OFFSET_CAPTURE) || $m[0][1] < 3)
        return array($title, '');
    $rest = trim(ltrim(trim(substr($body, $m[0][1])), '/'));
    return array(trim(substr($body, 0, $m[0][1])), trim(trim($lead) . ' ' . $rest));
}

// The texts of entry $e in language $lang (2C): the title (mel_tsl_split), its two lines - "Сериал · 73 файла · 51.6 ГБ"
// (the type of its category alone; the video files if two and more; its size; '' none of them) and "Добавлено 18 сентября"
// (the year if not this one, UTC; '' no date) - and the rest of the title.
/**
 * @param MelTsEntry $e
 * @param string $lang
 * @return array{string, array{string, string}, string}
 */
function mel_tsl_texts($e, $lang)
{
    list($head, $rest) = mel_tsl_split($e['title']);
    $kinds = array('movie' => 'card_kind_movie', 'tv' => 'cat_kind_series');
    $facts = array();
    if (isset($kinds[$e['category']]))
        $facts[] = mel_tr($lang, $kinds[$e['category']]);
    $v = mel_tsl_videos($e);
    if ($v !== null && count($v) > 1)
        $facts[] = count($v) . ' ' . mel_gc_plural($lang, count($v), 'tsl_files');
    if ($e['size'] > 0)
        $facts[] = mel_size_str($e['size'], $lang);
    $added = '';
    if ($e['added'] > 0)
        $added = str_replace('{d}', mel_oc_day(gmdate('Y-m-d', $e['added']), $lang, false, gmdate('Y', $e['added']) !== gmdate('Y')),
            mel_tr($lang, 'tsl_added'));
    return array($head, array(implode(' · ', $facts), $added), $rest);
}

// The info panel of tile $id of the screen (mel_catv_info) -> array(title, lines array(text, colour, least px), the
// description), null for another tile: a torrent (mel_tsl_texts; not in memory, a restart of php_server - an empty
// panel, not the one of the tile before; the list is not read here), the hint (the name of the section and what comes
// there), an error (its title and text, the detail of the last build).
/**
 * @param string $id
 * @return array{string, list<array{string, string, int}>, string}|null
 */
function mel_tsl_info($id)
{
    list(, $bare) = mel_catv_id_mode($id);
    $lang = mel_catv_ui_lang();
    if ($bare === MEL_TSL_HINT)
        return array(mel_tr($lang, 'tsl_title'), array(), mel_tr($lang, 'tsl_empty_text'));
    if (preg_match('/^' . MEL_TSL_ERR . '([a-z_]+)$/D', $bare, $m))
    {
        list($title, $text) = mel_tsl_err_texts($m[1], $lang, MelTslView::$detail);
        return array($title, array(), $text);
    }
    $hash = mel_tsl_hash_of($id);
    if ($hash === '')
        return null;
    if (!isset(MelTsl::$list[$hash]))
        return array('', array(), '');
    list($head, $lines, $rest) = mel_tsl_texts(MelTsl::$list[$hash], $lang);
    return array($head, array(array($lines[0], MEL_CATV_TEXT_COLOR, MEL_CATV_DATA_MIN_PX),
        array($lines[1], MEL_CATV_TEXT_COLOR, MEL_CATV_DATA_MIN_PX)), $rest);
}

// ENTER on tile $id of the screen (mel_tsl_is_tile): a torrent - a line of the log for now (the start: stage 3a; "-" of a
// torrent not in memory); the hint - nothing; an error - the list read anew (mel_tsl_open).
/**
 * @param string $id
 * @return MelAction|null
 */
function mel_tsl_enter($id)
{
    list(, $bare) = mel_catv_id_mode($id);
    if (strpos($bare, MEL_TSL_ERR) === 0)
        return mel_tsl_open(MEL_TSL_URL);
    $hash = mel_tsl_hash_of($id);
    if ($hash === '')
        return null;
    $kind = isset(MelTsl::$list[$hash]) ? mel_tsl_kind(mel_tsl_videos(MelTsl::$list[$hash])) : '-';
    mel_log("ts section: enter $hash kind $kind");
    return null;
}
