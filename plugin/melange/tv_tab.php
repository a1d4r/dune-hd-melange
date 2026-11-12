<?php
// The tab "TV" of the main screen: Melange chosen as the TV application (Setup -> Applications ->
// TV -> Configure... -> Other application...), the tab shows its EPF, a ready view of rows in a
// file the shell reads without PHP (embeddable_plugin_folders of the manifest, mapping tv -> tv.json).
// Focus on a tile asks rows_info of Melange, ENTER opens a screen of Melange.
// The file: <tmp_dir>/epfs/tv.json, read first by the shell, and plugins_epfs/melange/tv.json next to
// plugins_data/melange, kept over a reboot, with tv.no_internet.json there. Written by the
// update_action of the manifest (aio_tv_update) and only by it, from the copies on the device: the
// rows of the tab "Movies", then those of "Series" (aio_catv_tab_copy_defs), no request.

define('AIO_TV_EPF', 'tv');
// "from" of the actions of the EPF: no screen of Melange is under them.
define('AIO_TV_FROM', 'tv:tv');

// The directories of the EPF from the paths the shell gives (FS_PREFIX in them): '' without one.
/**
 * @return array{tmp: string, flash: string}
 */
function aio_tv_dirs()
{
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
    $data = rtrim(aio_data_dir(), '/');
    return array('tmp' => $tmp === '' ? '' : "$tmp/epfs",
        'flash' => $data === '' ? '' : dirname(dirname($data)) . '/plugins_epfs/' . AIO_PLUGIN);
}

// Melange is the TV application: the line "shell_ext:tv = melange" of epf_mapping.txt, which the
// shell writes before it calls the update_action of the chosen plugin (device r25, tvtab_probe).
/**
 * @return bool
 */
function aio_tv_selected()
{
    $d = aio_tv_dirs();
    $f = $d['flash'] === '' ? '' : dirname($d['flash']) . '/epf_mapping.txt';
    $text = $f !== '' && is_file($f) ? strval(file_get_contents($f, false, null, 0, 65536)) : '';
    return preg_match('/^[ \t]*shell_ext:tv[ \t]*=[ \t]*' . AIO_PLUGIN . '[ \t]*\r?$/m', $text) === 1;
}

// ru or en of interface_language the shell sends with the update_action ("russian" as in
// settings.properties; the form of the value is not proven); none or "custom": of the settings.
/**
 * @param stdClass $in
 * @return string
 */
function aio_tv_lang($in)
{
    $l = aio_catv_in($in, 'interface_language');
    if ($l === '' || $l === 'custom')
        return aio_catv_lang();
    return $l === 'russian' || $l === 'ru' ? 'ru' : 'en';
}

// A row "Melange" of one tile that is no title: $id ("tv:open", "tv:setup"), its caption of $key.
/**
 * @param string $id
 * @param string $key
 * @param string $lang
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function aio_tv_note_defs($id, $key, $lang)
{
    $row = array('key' => 'tv/' . AIO_TV_EPF, 'type' => 'movie', 'id' => AIO_TV_EPF, 'name' => aio_tr($lang, 'plugin_caption'),
        'genre' => '', 'home' => false, 'group' => '');
    $item = aio_catv_item($id, aio_tr($lang, $key), AIO_CATV_ICONS . 'no_movie_poster.png');
    return aio_catv_row_defs(aio_catv_list(aio_cat_row_state($row), array(), false), $lang, array($item));
}

// The view of tv.json: the rows of both tabs of the catalog from the copies, as the screen of rows
// built now (aio_catv_logo_of: the mode of the tiles and the plates of the settings and the firmware).
// AIOStreams not set: one tile "Set up Melange" ("tv:setup"), as the catalog opens the settings;
// no row with a copy: one tile "Open Melange" ("tv:open"). The mode of the screen of rows before it
// is back after it: the grid of "Show all" asks for its next tiles without aio_catv_logo_of.
/**
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_tv_view($lang)
{
    $logo = AioCat::$logo;
    $rating = AioCat::$rating;
    aio_catv_logo_of(AIO_TV_FROM, true);
    if (Aio::$settings['base'] === '')
        $defs = aio_tv_note_defs('tv:setup', 'tv_setup', $lang);
    else
    {
        $movies = aio_catv_tab_copy_defs('movie', $lang);
        $series = aio_catv_tab_copy_defs('series', $lang);
        $defs = array_merge($movies['defs'], $series['defs']);
        if (!$defs)
            $defs = aio_tv_note_defs('tv:open', 'tv_open', $lang);
    }
    $view = aio_catv_rows_view($defs, null, AIO_TV_FROM);
    // No search key of the plugin on the main screen: POP UP has the search (aio_tv_menu).
    unset($view[PluginFolderView::data][PluginRowsFolderView::actions][GUI_EVENT_KEY_SEARCH]);
    AioCat::$logo = $logo;
    AioCat::$rating = $rating;
    return $view;
}

// $data to $path unless it is there already (the flash wears): 'same', 'new' or 'failed'.
// Atomic (aio_write_file); what an interrupted write left in the directory goes.
/**
 * @param string $path
 * @param string $data
 * @return string
 */
function aio_tv_put($path, $data)
{
    $dir = dirname($path);
    clearstatcache();
    if (is_file($path) && filesize($path) === strlen($data) && file_get_contents($path) === $data)
        return 'same';
    if (!is_dir($dir) && !mkdir($dir, 0755, true))
        return 'failed';
    foreach (glob("$dir/*.tmp") ?: array() as $f)
        unlink($f);
    return aio_write_file($path, $data, 0644) ? 'new' : 'failed';
}

// update_action of embeddable_plugin_folders (at a start of the shell, a wake, the choice of the TV
// application): tv.json to both directories, tv.no_internet.json (the same rows: they need no
// network) to the flash one, each only if it changed. Never the flag update_epfs_if_needed_flag here:
// the shell would call this again and again. Not the TV application: nothing written.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_tv_update($in)
{
    $t0 = microtime(true);
    $status = array(
        GuiAction::handler_string_id => STATUS_ACTION_ID,
        GuiAction::data => array(StatusActionData::status => 0));
    $boot = isset($in->first_run_after_boot) && is_scalar($in->first_run_after_boot) ?
        substr(strval(preg_replace('/[^0-9A-Za-z]/', '', strval($in->first_run_after_boot))), 0, 8) : '';
    // interface_language as it came, to the log: its form is not proven.
    $what = 'tv: update' . ($boot !== '' ? " first_run_after_boot=$boot" : '') . ' interface_language=' .
        substr(strval(preg_replace('/[^A-Za-z_-]/', '', aio_catv_in($in, 'interface_language'))), 0, 20);
    $d = aio_tv_dirs();
    if ($d['tmp'] === '' || $d['flash'] === '')
    {
        aio_log("$what: no tmp_dir_path or data_dir_path, nothing written");
        return $status;
    }
    if (!aio_tv_selected())
    {
        aio_log("$what: not the TV application, nothing written");
        return $status;
    }
    $lang = aio_tv_lang($in);
    AioCat::$lang = $lang;
    $json = json_encode(aio_tv_view($lang));
    if (!is_string($json))
    {
        aio_log("$what $lang: no JSON of the view, nothing written");
        return $status;
    }
    $res = aio_tv_put("{$d['tmp']}/" . AIO_TV_EPF . '.json', $json) . '/' . aio_tv_put("{$d['flash']}/" . AIO_TV_EPF . '.json', $json);
    $ni = aio_tv_put("{$d['flash']}/" . AIO_TV_EPF . '.no_internet.json', $json);
    aio_log(sprintf('%s %s: %s %d B, no_internet %s, %.0f ms', $what, $lang, $res, strlen($json), $ni, (microtime(true) - $t0) * 1000));
    return $status;
}

// --- The actions of the EPF: from "tv:<id of the EPF>", the tiles of the catalog as on its screens.

// $from of an action is the EPF of the tab "TV": no screen of Melange is under it, so none may be
// replaced (aio_replace_act); a screen of Melange opens over the main screen.
/**
 * @param string $from
 * @return bool
 */
function aio_tv_is_from($from)
{
    return preg_match('/^tv:[a-z]+$/D', $from) === 1;
}

// The catalog of Melange, its entry "catalog", over the main screen.
/**
 * @return AioAction
 */
function aio_tv_catalog_act()
{
    return aio_catv_open_act('catalog', '%tr%plugin_caption');
}

// ENTER in the EPF: "Open Melange" - the catalog, "Set up Melange" - the settings; any other tile as on
// a screen of rows (aio_catv_enter: a title - its card, "Show all" - that screen of Melange).
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_tv_enter($in)
{
    $id = aio_catv_in($in, 'selected_item_id');
    if ($id === 'tv:open')
        return aio_tv_catalog_act();
    if ($id === 'tv:setup')
        return aio_setup_open_act();
    return aio_catv_enter($in);
}

// POP UP in the EPF, on any tile: the search (its results a screen of Melange), the catalog, the
// settings. The search key is not the plugin's there: the main screen has its own (aio_tv_view).
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_tv_menu($in)
{
    $from = aio_catv_in($in, 'from');
    $lang = aio_catv_lang();
    aio_log("tv: POP UP on '" . aio_cut(aio_catv_in($in, 'selected_item_id'), 100) . "'");
    $items = array();
    foreach (array(
        array('cat_search', AIO_CATV_ICON_SEARCH, aio_input('cat_search', array('from' => $from))),
        array('tv_open', 'movies_small.png', aio_tv_catalog_act()),
        array('setup_menu', AIO_CATV_ICON_SETUP, aio_setup_open_act())) as $m)
    {
        $items[] = array(
            GuiMenuItemDef::caption => aio_tr($lang, $m[0]),
            GuiMenuItemDef::icon_url => AIO_CATV_ICONS . $m[1],
            GuiMenuItemDef::action => $m[2]);
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => $items));
}
