<?php
// The tab "TV" of the main screen: Melange chosen as the TV application (Setup -> Applications ->
// TV -> Configure... -> Other application...), the tab shows its EPF, a ready view of rows in a
// file the shell reads without PHP (embeddable_plugin_folders of the manifest, mapping tv -> tv.json).
// Focus on a tile asks rows_info of Melange, ENTER opens a screen of Melange.
// The file: <tmp_dir>/epfs/tv.json, read first by the shell, and $FS_PREFIX/flashdata/plugins_epfs/melange/tv.json,
// kept over a reboot, with tv.no_internet.json there. Written by the
// update_action of the manifest (aio_tv_update) and only by it, from the copies on the device: the
// rows of the tab "Movies", then those of "Series" (aio_catv_tab_copy_defs), no request.
// The same for movie.json and series.json, the rows of one tab each: the sub-items "Movies" and "Series"
// under "TV" (rows_menu_config.json of the flash directory, topmenu_enabled of the manifest); with them
// "TV" shows the first sub-item, tv.json is shown without them (the switch data_dir/tv_sub_off, a firmware
// older than AIO_TV_SUB_MIN_R or unknown).
// The shell later moves the file of tmp_dir to the flash (the same bytes there: it only removes it;
// code of the shell r25).
// Fresh: the shell calls it again on the flag <tmp_dir>/update_epfs_if_needed_flag (aio_tv_flag), set
// by the timer of the manifest (aio_tv_timer, every 10 min: the old copies asked anew) and by Melange
// when it saves a copy the file is built of (aio_tv_copy_saved) or reads a new settings.json.

define('AIO_TV_EPF', 'tv');
// "from" of the actions of each EPF: this and its id (aio_tv_is_from): no screen of Melange is under them.
define('AIO_TV_FROM_PREFIX', 'tv:');
// The sub-items under "TV" in the flash directory (aio_tv_menu_put), and the switch in data_dir that
// removes them and keeps them away: a way back if the shell fails on them (the owner makes it by hand).
define('AIO_TV_MENU_FILE', 'rows_menu_config.json');
define('AIO_TV_SUB_OFF', 'tv_sub_off');
// The sub-items work on r24 and r25 (seen on the device); older ones not tried, and a file the shell
// does not take crashed it at every start: there and on an unknown firmware they are off, as by the switch.
define('AIO_TV_SUB_MIN_R', 24);
// The timer: all its requests in this many s (aio_tv_refresh: each given the whole seconds left, curl takes
// whole ones, so none runs past it; none with less than 1 s left). Building and comparing the EPF come on
// top, only in a tick with a new copy or other settings. php_server runs one call at a time: the info
// panel and ENTER on "TV" wait for it.
define('AIO_TV_TIMER_S', 3.0);
// In data_dir: the tiles of tv.json by id without the mode (aio_tv_tile), written with it.
define('AIO_TV_TILES_FILE', 'tv_tiles.json');
// In data_dir: what the timer last built the EPF with (aio_tv_timer_state), kept over a restart of php_server.
define('AIO_TV_TIMER_FILE', 'tv_timer_state');

class AioTv
{
    // aio_tv_selected of the operation that started at Aio::$t0 ($selected_t0); null: not read yet.
    /** @var bool|null */
    public static $selected = null;
    /** @var float */
    public static $selected_t0 = -1.0;
    // The flag set in the operation that started at Aio::$t0: not again in it.
    /** @var float */
    public static $flagged_t0 = -1.0;
    // The timer asks its copies: no flag of each, it compares the file itself.
    /** @var bool */
    public static $quiet = false;
    // The copies the timer saved meanwhile (the manifest, first pages of rows of the file).
    /** @var int */
    public static $new_copies = 0;
    // aio_settings_sig at the last operation (aio_tv_settings_seen); null: none since a start of php_server.
    /** @var string|null */
    public static $sig = null;
    // The tiles of AIO_TV_TILES_FILE as read ($tiles_sig: 'ino:mtime:size' of the file then); null: not read.
    /** @var array<string, AioCatTile>|null */
    public static $tiles = null;
    /** @var string */
    public static $tiles_sig = '';
    // aio_tv_code_sig of this php_server; null: not taken yet.
    /** @var string|null */
    public static $code_sig = null;
}

// The directories of the EPF: tmp of tmp_dir_path ('' without one); flash of the fixed base of the shell
// (EPFS_BASE of its PHP, its loader of plugins), not of data_dir_path, which may be elsewhere (/persistfs):
// FS_PREFIX in front, as in bin/uninstall.sh.
/**
 * @return array{tmp: string, flash: string}
 */
function aio_tv_dirs()
{
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
    return array('tmp' => $tmp === '' ? '' : "$tmp/epfs", 'flash' => getenv('FS_PREFIX') . '/flashdata/plugins_epfs/' . AIO_PLUGIN);
}

// The EPF of the tab "TV" by id, each with the tabs of the catalog it shows: tv.json both (the item
// "TV" itself), movie.json and series.json one (its sub-items). Their tiles have the same ids.
/**
 * @return array<string, list<string>>
 */
function aio_tv_epfs()
{
    return array(AIO_TV_EPF => array('movie', 'series'), 'movie' => array('movie'), 'series' => array('series'));
}

// Melange is the TV application: the line "shell_ext:tv = melange" of epf_mapping.txt, which the
// shell writes before it calls the update_action of the chosen plugin (device r25, tvtab_probe).
// Read once an operation: each copy saved asks it (aio_tv_copy_saved).
/**
 * @return bool
 */
function aio_tv_selected()
{
    if (AioTv::$selected !== null && AioTv::$selected_t0 === Aio::$t0)
        return AioTv::$selected;
    $d = aio_tv_dirs();
    $f = dirname($d['flash']) . '/epf_mapping.txt';
    $text = is_file($f) ? strval(file_get_contents($f, false, null, 0, 65536)) : '';
    AioTv::$selected = preg_match('/^[ \t]*shell_ext:tv[ \t]*=[ \t]*' . AIO_PLUGIN . '[ \t]*\r?$/m', $text) === 1;
    AioTv::$selected_t0 = Aio::$t0;
    return AioTv::$selected;
}

// The flag for the shell to call the update_action again (aio_tv_update): <tmp_dir>/update_epfs_if_needed_flag
// with time() + 10, as the shell writes it for its own EPF (movie_helper.php; Global TV writes it empty).
// Only if Melange is the TV application, once an operation, never while the timer asks its copies.
// Never from aio_tv_update: the shell would call it again and again.
/**
 * @param string $why
 * @return void
 */
function aio_tv_flag($why)
{
    if (AioTv::$quiet || AioTv::$flagged_t0 === Aio::$t0 || !aio_tv_selected())
        return;
    $d = aio_tv_dirs();
    if ($d['tmp'] === '' || !is_dir(dirname($d['tmp'])))
        return;
    AioTv::$flagged_t0 = Aio::$t0;
    $ok = file_put_contents(dirname($d['tmp']) . '/update_epfs_if_needed_flag', strval(time() + 10)) !== false;
    aio_log("tv: flag: $why" . ($ok ? '' : ', not written'));
}

// The rows the files are built of: of the tabs of tv.json (aio_tv_epfs) in turn, as aio_catv_tab_copy_defs.
/**
 * @param list<AioCatRow> $rows
 * @return list<AioCatRow>
 */
function aio_tv_rows($rows)
{
    $epfs = aio_tv_epfs();
    $out = array();
    foreach ($epfs[AIO_TV_EPF] as $tab)
        $out = array_merge($out, aio_cat_user_rows($rows, Aio::$settings['cat_rows'], $tab));
    return $out;
}

// The sub-items off: the switch AIO_TV_SUB_OFF is in data_dir, or the firmware is older than
// AIO_TV_SUB_MIN_R or unknown.
/**
 * @return bool
 */
function aio_tv_sub_off()
{
    if (aio_fw_r() < AIO_TV_SUB_MIN_R)
        return true;
    clearstatcache();
    return aio_data_dir() !== '' && file_exists(aio_data_dir() . '/' . AIO_TV_SUB_OFF);
}

// aio_cat_cache_write saved the copy $key: the manifest or the first page of a row of the file - the flag.
// The flag of this operation set already, a copy of no first page (a page further on, a card): no manifest read.
// Saved by the timer (it asks only those): counted, no flag.
/**
 * @param string $key
 * @return void
 */
function aio_tv_copy_saved($key)
{
    $first = $key === AIO_CAT_MANIFEST_KEY || substr($key, -2) === '|0';
    if (AioTv::$quiet)
        AioTv::$new_copies += $first ? 1 : 0;
    if (AioTv::$quiet || AioTv::$flagged_t0 === Aio::$t0 || !$first || !aio_tv_selected())
        return;
    $hit = $key === AIO_CAT_MANIFEST_KEY;
    if (!$hit)
    {
        $copy = aio_cat_manifest_copy();
        foreach (aio_tv_rows($copy['rows']) as $row)
            $hit = $hit || aio_cat_page_key($row, 0) === $key;
    }
    if ($hit)
        aio_tv_flag($key === AIO_CAT_MANIFEST_KEY ? 'the manifest saved' : 'a row saved');
}

// At the start of an operation (settings.json read): a new one (the settings page or Melange wrote it) -
// the flag, its rows, plates or mode may be others. The settings page itself sets none: it runs as root,
// a flag of root in tmp_dir may be one php_server can not write again. Not in tv_update (it reads them
// anyway) nor in tv_timer (it compares the file); the first operation after a start of php_server only
// remembers it, the timer finds a change made before.
/**
 * @param stdClass $in
 * @return void
 */
function aio_tv_settings_seen($in)
{
    $sig = aio_settings_sig();
    $was = AioTv::$sig;
    AioTv::$sig = $sig;
    $c = isset($in->control_id) ? $in->control_id : '';
    if ($was !== null && $sig !== $was && $c !== 'tv_update' && $c !== 'tv_timer')
        aio_tv_flag('settings.json changed');
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

// The views of the EPF (aio_tv_epfs) by id: the rows of their tabs of the catalog from the copies, as
// a screen of rows opened now (the mode of the tiles and the plates of the settings and the firmware,
// as aio_catv_logo_of gives, but not kept among its screens: the EPF would push out a screen of Melange).
// Each tab built once. AIOStreams not set: one tile "Set up Melange" ("tv:setup"), as the catalog opens
// the settings; no row of its tabs with a copy: one tile "Open Melange" ("tv:open"). The mode of the screen
// of rows before it is back after it: the grid of "Show all" asks for its next tiles without aio_catv_logo_of.
// With the switch AIO_TV_SUB_OFF only tv.json: the sub-items are not shown, so their files are neither
// written (the flash wears) nor compared by the timer; those there already are kept.
/**
 * @param string $lang
 * @param array<string, AioCatTile>|null $tiles
 * @param-out array<string, AioCatTile> $tiles
 * @return array<string, array<string, mixed>>
 */
function aio_tv_views($lang, &$tiles = null)
{
    $logo = AioCat::$logo;
    $rating = AioCat::$rating;
    AioCat::$logo = aio_catv_logo_effective();
    AioCat::$rating = Aio::$settings['cat_rating'];
    $tiles = array();
    $tabs = array();
    $epfs = aio_tv_epfs();
    if (Aio::$settings['base'] !== '')
    {
        foreach ($epfs[AIO_TV_EPF] as $tab)
        {
            $tabs[$tab] = aio_catv_tab_copy_defs($tab, $lang);
            $lists = $tabs[$tab]['lists'];
            foreach ($tabs[$tab]['defs'] as $def)
            {
                foreach (isset($def[PluginRow::data][PluginRegularRow::items]) ? $def[PluginRow::data][PluginRegularRow::items] : array() as $it)
                {
                    list(, $id) = aio_catv_id_mode(strval($it[PluginRegularItem::id]));
                    $p = explode(':', $id, 2);
                    if (count($p) === 2 && isset($lists[$p[0]]['tiles'][$p[1]]))
                        $tiles[$id] = $lists[$p[0]]['tiles'][$p[1]];
                }
            }
        }
    }
    $views = array();
    $off = aio_tv_sub_off();
    foreach ($epfs as $epf => $of)
    {
        if ($off && $epf !== AIO_TV_EPF)
            continue;
        $defs = array();
        foreach ($of as $tab)
            $defs = isset($tabs[$tab]) ? array_merge($defs, $tabs[$tab]['defs']) : $defs;
        if (Aio::$settings['base'] === '')
            $defs = aio_tv_note_defs('tv:setup', 'tv_setup', $lang);
        else if (!$defs)
            $defs = aio_tv_note_defs('tv:open', 'tv_open', $lang);
        $view = aio_catv_rows_view($defs, null, AIO_TV_FROM_PREFIX . $epf);
        // No search key of the plugin on the main screen: POP UP has the search (aio_tv_menu).
        unset($view[PluginFolderView::data][PluginRowsFolderView::actions][GUI_EVENT_KEY_SEARCH]);
        $views[$epf] = $view;
    }
    AioCat::$logo = $logo;
    AioCat::$rating = $rating;
    return $views;
}

// The JSON of each EPF (aio_tv_views) by id; null when one can not be made. $tiles: their tiles.
/**
 * @param string $lang
 * @param array<string, AioCatTile>|null $tiles
 * @param-out array<string, AioCatTile> $tiles
 * @return array<string, string>|null
 */
function aio_tv_jsons($lang, &$tiles = null)
{
    $out = array();
    foreach (aio_tv_views($lang, $tiles) as $epf => $view)
    {
        $json = json_encode($view);
        if (!is_string($json))
            return null;
        $out[$epf] = $json;
    }
    return $out;
}

// The file $f holds $data already: not written again (the flash wears).
/**
 * @param string $f
 * @param string $data
 * @return bool
 */
function aio_tv_same($f, $data)
{
    clearstatcache();
    return is_file($f) && filesize($f) === strlen($data) && file_get_contents($f) === $data;
}

// The tiles of the EPF to AIO_TV_TILES_FILE (one map: movie.json and series.json hold tiles of tv.json),
// unless they are there already: 'same', 'new' or 'failed'.
// Not in plugins_epfs: the shell reads that, and it is no copy of the catalog (data_dir/catalog prunes).
// With the version of the copies: {"v": AIO_CAT_CACHE_V, "tiles": {"<rid>:<id>": tile}}.
/**
 * @param array<string, AioCatTile> $tiles
 * @return string
 */
function aio_tv_tiles_save($tiles)
{
    $f = aio_data_dir() . '/' . AIO_TV_TILES_FILE;
    $json = json_encode(array('v' => AIO_CAT_CACHE_V, 'tiles' => (object) $tiles));
    if (aio_data_dir() === '' || !is_string($json))
        return 'failed';
    if (aio_tv_same($f, $json))
        $res = 'same';
    else
        $res = aio_write_file($f, $json) ? 'new' : 'failed';
    AioTv::$tiles = $tiles;
    AioTv::$tiles_sig = aio_file_sig(AIO_TV_TILES_FILE);
    return $res;
}

// The tile $id (without the mode, "<rid>:<id>") of tv.json as written (aio_tv_tiles_save): the tab "TV"
// shows the file, while a screen of Melange may have got its rows anew since, without it. From memory,
// read again when the file is another one (a restart of php_server: from the disk). null: not in it.
// As a copy of the catalog (aio_cat_cache_read, aio_cat_page_cached): of another AIO_CAT_CACHE_V none,
// each tile made again by aio_cat_tile, so a key AioCatTile got since (an update of the zip) has its value.
/**
 * @param string $id
 * @return AioCatTile|null
 */
function aio_tv_tile($id)
{
    $sig = aio_file_sig(AIO_TV_TILES_FILE);
    if (AioTv::$tiles === null || $sig !== AioTv::$tiles_sig)
    {
        $j = $sig === '' ? null : json_decode(strval(file_get_contents(aio_data_dir() . '/' . AIO_TV_TILES_FILE)), true);
        AioTv::$tiles = array();
        if (is_array($j) && isset($j['v'], $j['tiles']) && $j['v'] === AIO_CAT_CACHE_V && is_array($j['tiles']))
        {
            foreach ($j['tiles'] as $k => $m)
            {
                $t = aio_cat_tile($m, aio_str($m, 'type'));
                if ($t)
                    AioTv::$tiles[strval($k)] = $t;
            }
        }
        AioTv::$tiles_sig = $sig;
    }
    return isset(AioTv::$tiles[$id]) ? AioTv::$tiles[$id] : null;
}

// folder_key of rows_info (the shell sends "<plugin>:<id of the EPF>") is of an EPF of the tab "TV",
// not of a screen of Melange ("melange:<media_url>", "melange:catalog"): so only their ids (aio_tv_epfs),
// no pattern, as aio_tv_is_from.
/**
 * @param string $key
 * @return bool
 */
function aio_tv_is_key($key)
{
    $epfs = aio_tv_epfs();
    $n = strlen(AIO_PLUGIN) + 1;
    return substr($key, 0, $n) === AIO_PLUGIN . ':' && isset($epfs[strval(substr($key, $n))]);
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
    if (aio_tv_same($path, $data))
        return 'same';
    if (!is_dir($dir) && !mkdir($dir, 0755, true))
        return 'failed';
    foreach (glob("$dir/*.tmp") ?: array() as $f)
        unlink($f);
    return aio_write_file($path, $data, 0644) ? 'new' : 'failed';
}

// The answer to the update_action and the timer: done.
/**
 * @return AioAction
 */
function aio_tv_status()
{
    return array(GuiAction::handler_string_id => STATUS_ACTION_ID, GuiAction::data => array(StatusActionData::status => 0));
}

// The JSON of rows_menu_config.json: the sub-items "Movies" and "Series" under "TV", the EPF of their tab
// each, the captions of the tabs of the catalog. Only items of type plugin with our name and a non-empty
// epf_id: an item of plugin_action_id alone next to one of epf_id crashed the shell r25 at every start
// (knowledge base rows-menu c024-c030). '' when it can not be made.
/**
 * @param string $lang
 * @return string
 */
function aio_tv_menu_json($lang)
{
    $items = array();
    foreach (array('movie' => 'cat_movies', 'series' => 'cat_series') as $epf => $key)
    {
        $items[] = array(
            'type' => 'plugin',
            'name' => AIO_PLUGIN,
            'epf_id' => $epf,
            // Without the no-break space at the end of cat_movies, cat_series (of the search, aio_catv_search_run).
            'caption' => trim(str_replace("\xc2\xa0", ' ', aio_tr($lang, $key))),
            'small_icon_url' => 'plugin_file://%' . AIO_PLUGIN . "%/img/tv_sub_$epf.png",
            'small_icon_width' => 100);
    }
    $json = json_encode(array('rows_menu_items' => $items));
    return is_string($json) ? $json : '';
}

// rows_menu_config.json of the flash directory $flash, after movie.json and series.json there ($epfs:
// written or the same): 'new', 'same', 'failed'; 'not written' when one of them failed. With the switch
// AIO_TV_SUB_OFF in data_dir never written, removed: 'off', 'off, removed', 'off, not removed'; 'off r22' and so on
// when the firmware turns them off (aio_tv_sub_off).
/**
 * @param string $flash
 * @param string $lang
 * @param bool $epfs
 * @return string
 */
function aio_tv_menu_put($flash, $lang, $epfs)
{
    $f = "$flash/" . AIO_TV_MENU_FILE;
    if (aio_tv_sub_off())
    {
        $off = aio_fw_r() < AIO_TV_SUB_MIN_R ? 'off r' . aio_fw_r() : 'off';
        return !is_file($f) ? $off : (@unlink($f) ? "$off, removed" : "$off, not removed");
    }
    if (!$epfs)
        return 'not written';
    $json = aio_tv_menu_json($lang);
    return $json === '' ? 'failed' : aio_tv_put($f, $json);
}

// interface_language as it came, to the log: its form is not proven.
/**
 * @param stdClass $in
 * @return string
 */
function aio_tv_lang_log($in)
{
    return ' interface_language=' . substr(strval(preg_replace('/[^A-Za-z_-]/', '', aio_catv_in($in, 'interface_language'))), 0, 20);
}

// update_action of embeddable_plugin_folders (at a start of the shell, a wake, the choice of the TV
// application): each EPF (aio_tv_views) to both directories, its .no_internet.json (the same rows: they
// need no network) to the flash one, then rows_menu_config.json there (aio_tv_menu_put), each only if
// it changed. Never the flag update_epfs_if_needed_flag here: the shell would call this again and again.
// The answer status. Not the TV application: nothing written.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_tv_update($in)
{
    $t0 = microtime(true);
    $status = aio_tv_status();
    $boot = isset($in->first_run_after_boot) && is_scalar($in->first_run_after_boot) ?
        substr(strval(preg_replace('/[^0-9A-Za-z]/', '', strval($in->first_run_after_boot))), 0, 8) : '';
    $what = 'tv: update' . ($boot !== '' ? " first_run_after_boot=$boot" : '') . aio_tv_lang_log($in);
    $d = aio_tv_dirs();
    if ($d['tmp'] === '')
    {
        aio_log("$what: no tmp_dir_path, nothing written");
        return $status;
    }
    if (!aio_tv_selected())
    {
        aio_log("$what: not the TV application, nothing written");
        return $status;
    }
    $lang = aio_tv_lang($in);
    // Not set back: the info panel of the tiles of the file (rows_info, aio_catv_ui_lang) speaks its language.
    AioCat::$lang = $lang;
    $jsons = aio_tv_jsons($lang, $tiles);
    if ($jsons === null)
    {
        aio_log("$what $lang: no JSON of the view, nothing written");
        return $status;
    }
    $out = array();
    $tmp_tv = 'failed';
    // The menu only after every sub-item written: none left out by the switch, none failed.
    $subs = count($jsons) === count(aio_tv_epfs());
    foreach ($jsons as $epf => $json)
    {
        $tmp = aio_tv_put("{$d['tmp']}/$epf.json", $json);
        $flash = aio_tv_put("{$d['flash']}/$epf.json", $json);
        $ni = aio_tv_put("{$d['flash']}/$epf.no_internet.json", $json);
        if ($epf === AIO_TV_EPF)
            $tmp_tv = $tmp;
        else
            $subs = $subs && $flash !== 'failed';
        $out[] = ($epf === AIO_TV_EPF ? '' : "$epf ") . "$tmp/$flash " . strlen($json) . " B, no_internet $ni";
    }
    $menu = aio_tv_menu_put($d['flash'], $lang, $subs);
    // The tiles of the files the shell reads first.
    $ts = $tmp_tv !== 'failed' ? aio_tv_tiles_save($tiles) : 'not written';
    aio_log(sprintf('%s %s: %s, menu %s, tiles %d %s, %.0f ms', $what, $lang, implode(', ', $out), $menu, count($tiles), $ts,
        (microtime(true) - $t0) * 1000));
    // The shell takes rows_menu_config.json by its mtime, as of Global TV: no menu read anew here.
    return $status;
}

// The file of the EPF $epf the shell shows holds $data: the one of tmp_dir while it is there, else the one
// of the flash (aio_tv_dirs). The shell moves the one of tmp_dir to the flash (removes it, the same bytes
// there) at any time: gone while read, the flash one is shown.
/**
 * @param string $epf
 * @param string $data
 * @return bool
 */
function aio_tv_shown_same($epf, $data)
{
    $d = aio_tv_dirs();
    $tmp = "{$d['tmp']}/$epf.json";
    if (aio_tv_same($tmp, $data))
        return true;
    clearstatcache();
    return !is_file($tmp) && aio_tv_same("{$d['flash']}/$epf.json", $data);
}

// The build of the code this php_server runs: the latest mtime of the *.php of the plugin and of the
// translations (the captions of the EPF, aio_tr), taken once (zip keeps the mtime of each file, a zip of
// the same AIO_VERSION has another one if any of them changed).
/**
 * @return string
 */
function aio_tv_code_sig()
{
    if (AioTv::$code_sig === null)
    {
        $t = 0;
        $dir = dirname(__FILE__);
        $files = array_merge(glob("$dir/*.php") ?: array(), glob("$dir/translations/dune_language_*.txt") ?: array());
        foreach ($files as $f)
            $t = max($t, intval(@filemtime($f)));
        AioTv::$code_sig = strval($t);
    }
    return AioTv::$code_sig;
}

// What the EPF are built with besides the copies: settings.json (aio_settings_sig), the language, the
// version and the build of the plugin (another one may build them otherwise).
/**
 * @param string $lang
 * @return string
 */
function aio_tv_timer_state($lang)
{
    return aio_settings_sig() . " $lang " . AIO_VERSION . ' ' . aio_tv_code_sig();
}

// The timer: the copy of the manifest older than AIO_CAT_FRESH asked anew (not AIO_CAT_MANIFEST_FRESH:
// the timer comes every 10 min), then the first pages
// of the rows of the file without a copy or with one older than AIO_CAT_FRESH, the oldest first, one by
// one in $limit s; after a failure of the server no more. Fresh copies are not asked: at most a request
// a row an hour, however often the shell calls. No flag of each copy (AioTv::$quiet), they are counted
// (AioTv::$new_copies). Each request gets the whole seconds left (curl takes whole ones: rounded up, the
// last could run past $limit), none begins with less than 1 s. -> for the log.
/**
 * @param float $limit
 * @return string
 */
function aio_tv_refresh($limit)
{
    $start = microtime(true);
    $down = array('net', 'timeout', 'rate', 'server');
    AioTv::$quiet = true;
    AioTv::$new_copies = 0;
    $copy = aio_cat_manifest_copy();
    $rows = $copy['rows'];
    $man = 'manifest fresh';
    $err = '';
    if (!$copy['rows'] || !aio_cat_is_fresh($copy))
    {
        $secs = intval(min(AIO_CATV_REFRESH_S, floor($limit)));
        $m = $secs >= 1 ? aio_cat_manifest_fetch($secs) : null;
        $rows = $m && $m['rows'] ? $m['rows'] : $rows;
        $err = $m ? $m['err'] : '';
        $man = $m ? 'manifest asked' . ($err !== '' ? " ($err)" : '') : 'manifest old, no time';
    }
    $tv_rows = aio_tv_rows($rows);
    $old = array();
    foreach ($tv_rows as $i => $row)
    {
        $c = aio_cat_page_cached($row, 0);
        if (!aio_cat_is_fresh($c))
            $old[$i] = $c ? $c['t'] : 0;
    }
    asort($old);
    $asked = 0;
    foreach (array_keys($old) as $i)
    {
        $secs = floor($limit - (microtime(true) - $start));
        if (in_array($err, $down, true) || $secs < 1)
            break;
        $st = aio_cat_row_state($tv_rows[$i]);
        $res = aio_cat_more($st, $secs);
        $err = $res['err'];
        $asked++;
    }
    AioTv::$quiet = false;
    return sprintf('%s, rows asked %d of %d old', $man, $asked, count($old));
}

// topmenu_timer_action of the manifest: the old copies asked anew (aio_tv_refresh, none without AIOStreams:
// the files have one tile then). Only when one came new or aio_tv_timer_state is another than the one of
// AIO_TV_TIMER_FILE (none there: another too) each EPF built and compared with the file the shell shows
// (aio_tv_shown_same; not the one of tmp_dir alone: the shell takes it away, the timer would find it
// changed each time): any one another - the flag, the shell calls the update_action, which writes them.
// Else nothing built: most ticks ask nothing, php_server is not held up for it. The state written only
// when another (the flash wears). Not the TV application: nothing asked, nothing written.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_tv_timer($in)
{
    $t0 = microtime(true);
    $what = 'tv: timer' . aio_tv_lang_log($in);
    $d = aio_tv_dirs();
    if ($d['tmp'] === '' || !aio_tv_selected())
    {
        aio_log("$what: " . ($d['tmp'] === '' ? 'no tmp_dir_path' : 'not the TV application') . ', nothing asked');
        return aio_tv_status();
    }
    // Before the requests: Aio::$settings are of the start of the operation, the page may save meanwhile.
    $lang = aio_tv_lang($in);
    $state = aio_tv_timer_state($lang);
    $asked = 'AIOStreams not set, nothing asked';
    $new = 0;
    if (Aio::$settings['base'] !== '')
    {
        $asked = aio_tv_refresh(AIO_TV_TIMER_S);
        $new = AioTv::$new_copies;
    }
    $sf = aio_data_dir() . '/' . AIO_TV_TIMER_FILE;
    clearstatcache();
    $was = aio_data_dir() !== '' && is_file($sf) ? strval(file_get_contents($sf, false, null, 0, 1024)) : '';
    $why = $new > 0 ? "new copies $new" : ($was === '' ? 'no state' : ($state !== $was ? 'state changed' : ''));
    if ($why === '')
    {
        aio_log(sprintf('%s %s: %s; nothing to do, %.0f ms', $what, $lang, $asked, (microtime(true) - $t0) * 1000));
        return aio_tv_status();
    }
    $jsons = aio_tv_jsons($lang);
    $changed = array();
    foreach ($jsons ?: array() as $epf => $json)
    {
        if (!aio_tv_shown_same($epf, $json))
            $changed[] = $epf;
    }
    if ($jsons !== null && $state !== $was && aio_data_dir() !== '')
        aio_write_file($sf, $state);
    aio_log(sprintf('%s %s: %s; %s, the file %s, %.0f ms', $what, $lang, $asked, $why,
        $changed ? 'changed (' . implode(', ', $changed) . ')' : 'the same', (microtime(true) - $t0) * 1000));
    if ($changed)
        aio_tv_flag('the timer');
    return aio_tv_status();
}

// --- The actions of the EPF: from "tv:<id of the EPF>", the tiles of the catalog as on its screens.

// $from of an action is an EPF of the tab "TV" ("tv:<id>" of aio_tv_epfs, no pattern, as aio_tv_is_key):
// no screen of Melange is under it, so none may be replaced (aio_replace_act); a screen of Melange opens
// over the main screen.
/**
 * @param string $from
 * @return bool
 */
function aio_tv_is_from($from)
{
    $epfs = aio_tv_epfs();
    $n = strlen(AIO_TV_FROM_PREFIX);
    return substr($from, 0, $n) === AIO_TV_FROM_PREFIX && isset($epfs[strval(substr($from, $n))]);
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
    // None in the file; on a screen of Melange they replace or redraw it, here there is none.
    if (strpos($id, 'retry:') === 0 || strpos($id, 'fail:') === 0)
        return null;
    $a = aio_catv_enter($in);
    // A tile of the file in no copy and not in memory: the file is old.
    if ($a === aio_error('cat_outdated', '', false, false))
        aio_tv_flag('a tile of the file is outdated');
    return $a;
}

// POP UP in the EPF, on any tile: the search (its results a screen of Melange), the catalog, the
// settings. The search key is not the plugin's there: the main screen has its own (aio_tv_views).
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
