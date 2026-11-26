<?php
// The section "На TorrServer" on the screens (its data: ts_list.php). The way to it: an icon on the strip of the catalog
// after the heart and its item of POP UP (mel_catv_strip_icons; none with "Без торрентов", mel_tsl_on). The screen
// "cat_ts": on every firmware the list of the view "list" of "Фильмы" of the shell (movies/movie_list_screen.php of
// shell_ext, owner 24.11.2026): lines of torrents on the left, "tors:<hash>", the icon of the section and the title
// alone; the panel on the right: the poster (none - the picture of none of the shell), the title up to " [" or " (",
// the facts, the day it was added, the rest of the name. An empty list - the line of the hint (4A).
// The list is read by the input that opens the screen (the icon, the item, "Повторить"): an error there - the kit error
// screen over the screen it came from (mel_ks_rows_error); a build of the screen takes the list in memory within
// MEL_TSL_FRESH_S of its read, else reads it anew (an error then: a line of it). ENTER on a torrent: its start
// (view_ts_play.php) or the screen of its files (view_ts_files.php). POP UP on a torrent: «Удалить с TorrServer» alone
// (no IMDb, no TMDB: no item of a title), its confirmation, then "rem" (mel_tsl_rem_act); none on another line.
// Needs ts_list.php, ts_row.php (the copy of the row of the catalog), view_catalog.php, view_my_list.php
// (mel_mlv_is_screen), kit_scr.php, view_setup.php, view_card.php (mel_oc_day), view_gcomps.php (mel_gc_plural), main.php.
// PHP 5.3.6, firmware API only.

// The address of the screen; the rid of its lines of torrents ("tors:<hash>"); the prefix of its other lines: the hint
// of an empty list, a line of an error of a build of the screen ("tsl:err:<key>").
define('MEL_TSL_URL', 'cat_ts');
define('MEL_TSL_RID', 'tors');
define('MEL_TSL_OWN', 'tsl:');
define('MEL_TSL_HINT', MEL_TSL_OWN . 'hint');
define('MEL_TSL_ERR', MEL_TSL_OWN . 'err:');
// The icon of the strip, of its item of POP UP and of the lines of the screen: L1 of the designer, 64x64, the glyph
// 48x48 at 8, 8 as the icons of the shell (the N of tsl<N> goes up when it changes: the shell keeps a picture by its
// URL; made by experiments/ts_section_mock of the kitchen).
define('MEL_TSL_ICON', 'plugin_file://img/tsl1_logo.png');
// The background of the screen: the wallpaper of the shell, the picture it paints over the strip of the main screen; the
// same address as the default background of shell_ext (common/gui_helper.php), there with /config/bg.jpg.
define('MEL_TSL_BG', 'gui_skin://images/bg.jpg');
// A build of the screen takes the list in memory within this many s of its read.
define('MEL_TSL_FRESH_S', 10);
// show_dialog_delay of an open of the section (cat_tsl_open), ms: the read of the list takes up to MEL_TSL_LIST_S,
// «Подождите» after 1.5 s, not after MEL_DELAY_INPUT (10 s: a still screen till then).
define('MEL_TSL_DELAY', 1500);

// The state of the screen (the data: MelTsl of ts_list.php): when the list in memory was read (microtime; 0: none).
class MelTslView
{
    /** @var float */
    public static $read = 0.0;
}

// The open of the section from screen $from: cat_tsl_open with its own delay of «Подождите» (MEL_TSL_DELAY). The icon and
// its item of POP UP (their inputs cat_enter and cat_part wait the delay of the screen), "Повторить", the line of an error.
/**
 * @param string $from
 * @return MelAction
 */
function mel_tsl_open_act($from)
{
    return mel_input('cat_tsl_open', array('from' => $from), MEL_TSL_DELAY);
}

// cat_tsl_open (mel_tsl_open_act) on screen $from: the list read (mel_tsl_load); an error - the kit error screen over
// $from (mel_tsl_error); else the screen over $from, in place of a screen of Fav (as mel_mlv_open); on the screen itself
// (its line of an error) the screen built anew.
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

// The list read (mel_tsl_load), the time of it kept (MelTslView::$read); its first entries are the copy of the row of
// the catalog (mel_tsr_take: no second request).
/**
 * @return array{list<MelTsEntry>|null, string, string}
 */
function mel_tsl_read()
{
    $r = mel_tsl_load();
    if ($r[0] !== null)
        MelTslView::$read = microtime(true);
    mel_tsr_take($r);
    return $r;
}

// The title (the cause) and the text (what to do) of error $key of the list (mel_tsl_load) in language $lang (4B, 4C of
// the designer); of tsl_err_bad with its detail $detail (the code, "not a list") its line of the address after the text
// (mel_tsl_addr), none of another error (no answer: the error of curl).
/**
 * @param string $key
 * @param string $lang
 * @param string $detail
 * @return array{string, string}
 */
function mel_tsl_err_texts($key, $lang, $detail)
{
    $names = array('ts_err_down' => 'down', 'ts_err_auth' => 'auth', 'ts_err_auth_bad' => 'auth_bad', 'tsl_err_bad' => 'bad',
        'ts_err_off' => 'off');
    $n = isset($names[$key]) ? $names[$key] : 'bad';
    $text = mel_tr($lang, "tsl_{$n}_text");
    return array(mel_tr($lang, "tsl_{$n}_title"), $n === 'bad' && $detail !== '' ? "$text " . mel_tsl_addr($lang, $detail) : $text);
}

// The line of the address of TorrServer under an error of the list: «Адрес: <url>», of a reply not 200 (detail $detail
// "HTTP <code>") « · HTTP <code>» after it.
/**
 * @param string $lang
 * @param string $detail
 * @return string
 */
function mel_tsl_addr($lang, $detail)
{
    $addr = str_replace('{url}', Mel::$settings['ts'], mel_tr($lang, 'tsl_addr'));
    return strpos($detail, 'HTTP ') === 0 ? "$addr \xC2\xB7 $detail" : $addr;
}

// The kit error screen of error $key of the list (mel_tsl_load; $detail: its detail) over screen $from (4B, 4C of the
// designer): its title and text (mel_tsl_err_texts); no answer - «Повторить», «Настройки» and the address of TorrServer
// under them; not a list - «Повторить», «Настройки» and the address with the code (mel_tsl_addr); the password, «Без
// торрентов» - «Настройки». «Назад» after them (mel_ks_rows_error).
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
        'act' => mel_tsl_open_act($from));
    $setup = array('label' => mel_tr($lang, 'setup_menu'), 'icon' => 'k_icon_settings', 'act' => mel_setup_open_act($from));
    $own = array('ts_err_down' => array($retry, $setup), 'tsl_err_bad' => array($retry, $setup));
    $d = array('buttons' => isset($own[$key]) ? $own[$key] : array($setup));
    if ($key === 'ts_err_down' || $key === 'tsl_err_bad')
        $d['detail'] = mel_tsl_addr($lang, $key === 'tsl_err_bad' ? $detail : '');
    mel_log("ts section: error $key");
    list($title, $text) = mel_tsl_err_texts($key, $lang, '');
    return mel_ks_rows_error($lang, mel_tr($lang, 'tsl_title'), $title, $text, $d);
}

// The screen (get_folder_view of MEL_TSL_URL): the list in memory within MEL_TSL_FRESH_S of its read (a second build of
// one open too), else read anew; its lines in its order (mel_tsl_line), none - the hint, an error - a line of it (its
// title and text with the detail in the panel; ENTER: read anew). The list of mel_tsl_pane on every firmware.
/**
 * @return array<string, mixed>
 */
function mel_tsl_view()
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
    $items = mel_tsl_items($list, $key, $detail, $lang);
    mel_log(sprintf('ts section: screen: %d lines, %s, %.0f ms', $list === null ? 0 : count($list),
        $kept ? 'the list of the open' : 'the list read', (microtime(true) - $start) * 1000));
    return mel_tsl_pane($items);
}

// The lines of the screen of list $list (null: error $key with its detail $detail) in language $lang: its entries in
// its order (mel_tsl_line), none - the hint, an error - a line of it.
/**
 * @param list<MelTsEntry>|null $list
 * @param string $key
 * @param string $detail
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_tsl_items($list, $key, $detail, $lang)
{
    $none = MEL_CATV_ICONS . 'no_movie_poster.png';
    $items = array();
    if ($list === null)
    {
        list($title, $text) = mel_tsl_err_texts($key, $lang, $detail);
        $items[] = mel_tsl_item(MEL_TSL_ERR . $key, $title, $none, array($title, $text));
    }
    else if (!$list)
        $items[] = mel_tsl_item(MEL_TSL_HINT, mel_tr($lang, 'tsl_empty'), $none,
            array(mel_tr($lang, 'tsl_title'), mel_tr($lang, 'tsl_empty_text')));
    foreach ($list === null ? array() : $list as $e)
        $items[] = mel_tsl_line($e, $lang, $none);
    return $items;
}

// The line of entry $e in language $lang: its title alone (owner 24.11.2026); the panel: its texts (mel_tsl_texts: the
// title, the facts, "Добавлено <день>", the rest of the name last - a long one pushed the lines under it off the panel
// in 0.2.4) under its poster (mel_img: http(s); none - $none).
/**
 * @param MelTsEntry $e
 * @param string $lang
 * @param string $none
 * @return array<string, mixed>
 */
function mel_tsl_line($e, $lang, $none)
{
    $poster = mel_img($e['poster']);
    $t = mel_tsl_texts(mel_tsr_tile_of($e, mel_tsl_files_of($e['hash'])), $lang);
    return mel_tsl_item(MEL_TSL_RID . ':' . $e['hash'], $e['title'], $poster !== '' ? $poster : $none,
        array($t['head'], $t['facts'], $t['added'], $t['rest']));
}

// A line of the screen: id $id (the mode of the screen before it), caption $caption, the icon of the section; in the
// panel picture $poster and texts $texts (the first the title, the empty ones left out); nothing else in the line.
/**
 * @param string $id
 * @param string $caption
 * @param string $poster
 * @param list<string> $texts
 * @return array<string, mixed>
 */
function mel_tsl_item($id, $caption, $poster, $texts)
{
    return array(
        PluginRegularFolderItem::media_url => mel_catv_tile_id($id),
        PluginRegularFolderItem::caption => $caption,
        PluginRegularFolderItem::view_item_params => array(
            ViewItemParams::icon_path => MEL_TSL_ICON,
            ViewItemParams::item_detailed_icon_path => $poster,
            ViewItemParams::item_detailed_info => implode('|', array_map('mel_clean', array_filter($texts, 'strlen')))));
}

// The view of lines $items: the view "list" of "Фильмы" of the shell (movies/movie_list_screen.php of shell_ext: its
// get_view_params(1, 11, ..., true, true) over GuiHelper::get_common_regular_params; its lines of 48 px, the caption
// from the left, 1150 wide; the poster of the panel 310x465, movies/l.php), the texts of the panel normal (not large as
// the vendor, small was too small: owner 24.11.2026), broken in lines; the background MEL_TSL_BG over the one of the skin
// (optimize_full_screen_background off: on, the shell does not paint that one, SDK plugins.txt, and a skin with no
// images/bg.jpg would be black); ENTER and POP UP as the grid of "Show all" of r24 (cat_enter, cat_menu from the screen).
/**
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function mel_tsl_pane($items)
{
    $p = array('from' => MEL_TSL_URL);
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_REGULAR,
        PluginFolderView::data => array(
            PluginRegularFolderView::view_params => array(
                ViewParams::background_path => MEL_TSL_BG,
                ViewParams::background_order => 0,
                ViewParams::paint_path_box => true,
                ViewParams::paint_widget => true,
                ViewParams::paint_widget_background => true,
                ViewParams::paint_content_box_background => false,
                ViewParams::paint_path_box_background => false,
                ViewParams::paint_details_box_background => false,
                ViewParams::paint_help_line => true,
                ViewParams::optimize_full_screen_background => false,
                ViewParams::num_cols => 1,
                ViewParams::num_rows => 11,
                ViewParams::paint_details => true,
                ViewParams::scroll_animation_enabled => true,
                ViewParams::item_detailed_info_font_size => FONT_SIZE_NORMAL,
                ViewParams::item_detailed_info_auto_line_break => true,
                ViewParams::max_icon_width => 288,
                ViewParams::max_icon_height => 432,
                ViewParams::item_detailed_info_use_main_icon => false,
                ViewParams::content_box_x => 100,
                ViewParams::content_box_width => 1300,
                ViewParams::content_box_padding_right => 20,
                ViewParams::content_box_padding_left => 0),
            PluginRegularFolderView::base_view_item_params => array(
                ViewItemParams::item_padding_top => 0,
                ViewItemParams::item_padding_bottom => 0,
                ViewItemParams::icon_valign => VALIGN_CENTER,
                ViewItemParams::item_paint_caption => true,
                ViewItemParams::icon_width => 48,
                ViewItemParams::icon_height => 48,
                ViewItemParams::icon_scale_factor => 1.0,
                ViewItemParams::icon_sel_scale_factor => 1.0,
                ViewItemParams::item_detailed_icon_width => 310,
                ViewItemParams::item_detailed_icon_height => 465,
                ViewItemParams::icon_dx => 13,
                ViewItemParams::item_layout => HALIGN_LEFT,
                ViewItemParams::item_caption_width => 1150),
            PluginRegularFolderView::not_loaded_view_item_params => array(
                ViewItemParams::icon_path => MEL_TSL_ICON,
                ViewItemParams::item_detailed_icon_path => MEL_CATV_ICONS . 'loading_movie_poster.png'),
            PluginRegularFolderView::load_failed_view_item_params => array(
                ViewItemParams::icon_path => MEL_CATV_ICONS . 'no_movie_poster.png'),
            PluginRegularFolderView::async_icon_loading => true,
            PluginRegularFolderView::async_icon_loading_interrupt_enabled => true,
            PluginRegularFolderView::actions => array(
                GUI_EVENT_KEY_ENTER => mel_input('cat_enter', $p, MEL_CARD_DELAY),
                GUI_EVENT_KEY_POPUP_MENU => mel_input('cat_menu', $p)) + mel_egg_acts(),
            PluginRegularFolderView::initial_range => mel_tsl_range($items)));
}

// The range of all lines $items of the screen (its view, plugin_update_folder after "rem").
/**
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function mel_tsl_range($items)
{
    return array(
        PluginRegularFolderRange::total => count($items),
        PluginRegularFolderRange::more_items_available => false,
        PluginRegularFolderRange::from_ndx => 0,
        PluginRegularFolderRange::count => count($items),
        PluginRegularFolderRange::items => $items);
}

// The hash of line $id (of any mode) of a torrent, '' another.
/**
 * @param string $id
 * @return string
 */
function mel_tsl_hash_of($id)
{
    list(, $bare) = mel_catv_id_mode($id);
    return preg_match('/^' . MEL_TSL_RID . ':([0-9a-f]{40})$/D', $bare, $m) ? $m[1] : '';
}

// Line $id is of the screen: a torrent, the hint, an error.
/**
 * @param string $id
 * @return bool
 */
function mel_tsl_is_tile($id)
{
    list(, $bare) = mel_catv_id_mode($id);
    return strpos($bare, MEL_TSL_RID . ':') === 0 || strpos($bare, MEL_TSL_OWN) === 0;
}

// The texts of tile $tile of a torrent (mel_tsr_tile_of: its entry, video files, name by its files) in language $lang: head
// and rest - the title and the rest of it (mel_tsl_split); no rest (a short title of Melange) - the name of the torrent
// by its files (owner 24.11.2026); facts - "73 файла · 51.6 ГБ" (the video files if two and more; its size; '' none of
// them); added - "Добавлено 18 сентября" (mel_tsl_added; '' no date). The section and the row of the catalog alike.
/**
 * @param MelTsrTile $tile
 * @param string $lang
 * @return array{head: string, rest: string, facts: string, added: string}
 */
function mel_tsl_texts($tile, $lang)
{
    $e = $tile['e'];
    list($head, $rest) = mel_tsl_split($e['title']);
    $facts = array();
    if ($rest === '')
        $rest = $tile['fname'];
    $n = $tile['nv'];
    if ($n > 1)
        $facts[] = $n . ' ' . mel_gc_plural($lang, $n, 'tsl_files');
    if ($e['size'] > 0)
        $facts[] = mel_size_str($e['size'], $lang);
    return array('head' => $head, 'rest' => $rest, 'facts' => implode(' · ', $facts),
        'added' => mel_tsl_added($e, $lang, 'tsl_added'));
}

// The day entry $e was added in the words of key $key ("{d}": "18 сентября", the year if not this one, UTC): tsl_added
// of the panel, tsl_added_lc of the side of the screen of files; '' no date.
/**
 * @param MelTsEntry $e
 * @param string $lang
 * @param string $key
 * @return string
 */
function mel_tsl_added($e, $lang, $key)
{
    if ($e['added'] <= 0)
        return '';
    return str_replace('{d}', mel_oc_day(gmdate('Y-m-d', $e['added']), $lang, false, gmdate('Y', $e['added']) !== gmdate('Y')),
        mel_tr($lang, $key));
}

// ENTER on line $id of screen $from (mel_tsl_is_tile: the section, a tile of the row of the catalog, the tab "TV"), the one
// place a torrent branches: its entry (mel_tsl_find: in memory, of the copy of the row, else the list read once; an error
// of it - the error screen of the list over $from; none there, removed elsewhere - out of the copy, the screens anew
// (mel_tsl_anew: its tile off the row, not read again at every OK on it), then the window tsl_err_gone); its video
// files known (mel_tsl_files_of: of its data or of a file_stats before): one - the start of it, two and more - the screen
// of files (view_ts_files.php), none - the error; not known (a record of Lampa, other data) - the start by file_stats
// (view_ts_play.php). The hint - nothing; an error - the list read anew (mel_tsl_open_act).
/**
 * @param string $id
 * @param string $from
 * @return MelAction|null
 */
function mel_tsl_enter($id, $from)
{
    list(, $bare) = mel_catv_id_mode($id);
    if (strpos($bare, MEL_TSL_ERR) === 0)
        return mel_tsl_open_act($from);
    $hash = mel_tsl_hash_of($id);
    if ($hash === '')
        return null;
    list($e, $key, $detail) = mel_tsl_find($hash);
    if ($key !== '')
        return mel_tsl_error($key, $detail, $from);
    if ($e === null)
    {
        mel_log("ts section: enter $hash: not in the list, the screens anew");
        return mel_tsl_anew($from, mel_tsr_drop($hash), mel_ts_fail(mel_catv_lang(), 'tsl_err_gone'), 'after a torrent gone');
    }
    $files = mel_tsl_files_of($hash);
    $v = $files === null ? null : mel_tsl_videos_of($files);
    $kind = mel_tsl_kind($v);
    mel_log("ts section: enter $hash kind $kind");
    if ($kind === 'none')
        return mel_ts_fail(mel_catv_lang(), 'ts_err_no_video');
    if ($kind === 'pack' && $files !== null)
        return mel_tsf_open($hash, $files);
    return mel_tsl_start($e, $kind === 'movie' && $v ? $v[0]['id'] : 0, 0);
}

// --- «Удалить с TorrServer» (owner 24.11.2026): POP UP of a torrent line of the section and of a tile of the row of the
// catalog (owner 26.11.2026), a confirmation, "rem". Not on the screen of files of a pack; the history and the positions
// of Melange ("aiots:") stay.

// The items of POP UP of line $id of screen $from (mel_catv_tile_menu) in language $lang: a torrent - «Удалить с
// TorrServer» (cat_tsl_rem_ask: the entry may need the list read, MEL_TSL_DELAY); the hint, an error - none.
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_tsl_menu_items($id, $from, $lang)
{
    $hash = mel_tsl_hash_of($id);
    if ($hash === '')
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($lang, 'tsl_rem'),
        GuiMenuItemDef::icon_url => MEL_CATV_ICONS . 'cancel_small.png',
        GuiMenuItemDef::action => mel_input('cat_tsl_rem_ask', array('from' => $from, 'h' => $hash), MEL_TSL_DELAY)));
}

// cat_tsl_rem_ask of torrent $hash of screen $from: its entry (mel_tsl_find: of the copy of the row too, no list read;
// an error of the list - its error screen over $from; none there, removed elsewhere - out of the copy, the screens anew
// (mel_tsl_anew), then the window «Этой раздачи уже нет на TorrServer»), then the dialog «Удалить «<the title of the
// panel>» с TorrServer?»: «Удалить» - cat_tsl_rem, «Отмена» - nothing. Not a hash: nothing.
/**
 * @param string $hash
 * @param string $from
 * @return MelAction|null
 */
function mel_tsl_rem_ask($hash, $from)
{
    if (!preg_match('/^[0-9a-f]{40}$/D', $hash))
        return null;
    $lang = mel_catv_lang();
    list($e, $key, $detail) = mel_tsl_find($hash);
    if ($key !== '')
        return mel_tsl_error($key, $detail, $from);
    if ($e === null)
    {
        mel_log("ts section: rem $hash: not in the list, -> dialog: tsl_err_gone, the screen anew");
        return mel_tsl_anew($from, mel_tsr_drop($hash), mel_dialog(mel_tr($lang, 'tsl_err_gone'), array(), array('OK' => mel_close_and(null))),
            'after a torrent gone');
    }
    list($head) = mel_tsl_split($e['title']);
    mel_log("ts section: rem $hash: ask");
    $go = mel_input('cat_tsl_rem', array('from' => $from, 'h' => $hash), MEL_TSL_DELAY);
    return mel_dialog(str_replace('{t}', $head, mel_tr($lang, 'tsl_rem_ask')), array(), array(
        mel_tr($lang, 'tsl_rem_yes') => mel_close_and($go),
        mel_tr($lang, 'ts_cancel') => mel_close_and(null)));
}

// cat_tsl_rem of torrent $hash of screen $from (the dialog closed): "rem" (mel_tsl_rem: out of the copy of the row too).
// An error: a window of why, the list and the row as they were. Removed from a tile of the row (a main screen, "TV"):
// the screens anew (mel_tsl_anew: its tile out). Removed in the section: the lines of the list in memory less it, in
// place (plugin_update_folder, as the shell does on its lists of files, movies/movie_files_screen.php), the cursor on the
// line after it (its index of mel_tsl_rem; the last one: the new last; none left: the hint) - not the list read anew: it
// comes by stream and may be long (up to MEL_TSL_LIST_S), and "rem" changed nothing else in it; the main screens under
// it anew (mel_tsl_anew). Not in the list in memory (a restart of php_server): the screen anew (invalidate: its view
// reads the list).
/**
 * @param string $hash
 * @param string $from
 * @return MelAction|null
 */
function mel_tsl_rem_act($hash, $from)
{
    if (!preg_match('/^[0-9a-f]{40}$/D', $hash))
        return null;
    $lang = mel_catv_lang();
    list($key, $detail, $i, $dropped) = mel_tsl_rem($hash);
    if ($key !== '')
        return mel_tsl_rem_fail($lang, $key, $detail);
    if ($from !== MEL_TSL_URL)
    {
        mel_log("ts row: rem $hash: the screens anew");
        return mel_tsl_anew($from, $dropped, null, 'after a removal');
    }
    if ($i < 0)
    {
        mel_log("ts section: rem $hash: not in memory, the screen anew");
        return mel_tsl_anew(MEL_TSL_URL, $dropped, null, 'after a removal');
    }
    $list = array_values(MelTsl::$list);
    $n = count($list);
    $sel = $n ? min($i, $n - 1) : 0;
    mel_log("ts section: rem $hash: $n lines left, the cursor on $sel");
    return mel_tsl_anew('', $dropped, array(
        GuiAction::handler_string_id => PLUGIN_UPDATE_FOLDER_ACTION_ID,
        GuiAction::data => array(
            PluginUpdateFolderActionData::range => mel_tsl_range(mel_tsl_items($list, '', '', $lang)),
            PluginUpdateFolderActionData::need_refresh => true,
            PluginUpdateFolderActionData::sel_ndx => $sel)), 'after a removal');
}

// The screens built anew after a torrent went off TorrServer (removed, or found gone) from screen $from, then $post: $from
// itself (mel_catv_screen_of: '' none, a screen updated by $post itself, "TV"); the row shown (mel_tsr_any) - on r25,
// its tile out of the copy ($dropped, mel_tsr_drop: one not in it changed no row), the main screens opened too
// (mel_catv_mains_anew: one under the section, the other tab); on r24 of them those built with another copy, whatever
// changed it (mel_tsr_unseen: the one under the section, a read of the section too). The shell keeps the focus by
// sel_state. None of them: $post.
/**
 * @param string $from
 * @param bool $dropped
 * @param MelAction|null $post
 * @param string $why
 * @return MelAction|null
 */
function mel_tsl_anew($from, $dropped, $post, $why)
{
    $also = mel_catv_screen_of($from);
    if (mel_tsr_any() && ($dropped || mel_fw_r() < 25))
        return mel_catv_mains_anew($post, $why, array_merge($also !== '' ? array($also) : array(),
            mel_fw_r() >= 25 ? array() : mel_tsr_unseen(MelCat::$mains)), false);
    if ($also === '')
        return $post;
    $a = mel_catv_invalidate($also);
    $a[GuiAction::data][PluginInvalidateFoldersActionData::post_action] = $post;
    return $a;
}

// The window of an error $key of "rem" (mel_tsl_rem; $detail its detail) in language $lang: «Раздача не удалена», why
// (the texts of the errors of a start: ts_err_down, the login; tsl_rem_bad), the address of TorrServer (with the code of
// a reply not 200, mel_tsl_addr) but under the login, OK.
/**
 * @param string $lang
 * @param string $key
 * @param string $detail
 * @return MelAction
 */
function mel_tsl_rem_fail($lang, $key, $detail)
{
    mel_log("ts section: rem: -> dialog: $key");
    $lines = mel_dialog_lines(mel_tr($lang, $key));
    if ($key === 'ts_err_down' || $key === 'tsl_rem_bad')
        $lines[] = mel_tsl_addr($lang, $detail);
    return mel_dialog(mel_tr($lang, 'tsl_rem_failed'), $lines, array('OK' => mel_close_and(null)));
}
