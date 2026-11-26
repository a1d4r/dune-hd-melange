<?php
// The tab "TV" of the main screen: Melange chosen as the TV application (Setup -> Applications ->
// TV -> Configure... -> Other application...), the tab shows its EPF, a ready view of rows in a
// file the shell reads without PHP (embeddable_plugin_folders of the manifest, mapping tv -> tv.json).
// Focus on a tile asks rows_info of Melange, ENTER opens a screen of Melange.
// The file: <tmp_dir>/epfs/tv.json, read first by the shell, and $FS_PREFIX/flashdata/plugins_epfs/melange/tv.json,
// kept over a reboot, with tv.no_internet.json there. Written (mel_tv_write) by the update_action of the
// manifest (mel_tv_update) and by "Configure list…" from the EPF (mel_tv_rows_saved: it has the shell read them
// anew at once, no flag then), from the copies on the device: the rows of the tab "Movies", then those of "Series"
// (mel_catv_tab_copy_defs), no request.
// The same for movie.json and series.json, the rows of one tab each: the sub-items "Movies" and "Series"
// under "TV" (rows_menu_config.json of the flash directory, topmenu_enabled of the manifest); with them
// "TV" shows the first sub-item, tv.json is shown without them (the switch data_dir/tv_sub_off, a firmware
// older than MEL_TV_SUB_MIN_R or unknown).
// The shell later moves the file of tmp_dir to the flash (the same bytes there: it only removes it;
// code of the shell r25).
// Fresh: the shell calls it again on the flag <tmp_dir>/update_epfs_if_needed_flag (mel_tv_flag), set
// by the timer of the manifest (mel_tv_timer, every 10 min: the old copies asked anew, the history changed)
// and by Melange when it saves a copy the file is built of (mel_tv_copy_saved), reads a new settings.json or
// is back from the player (mel_finish), and by "Configure list…" when it has nothing for the shell to read anew.
// The rows "Continue" (continue.php) and "Мой список" of their types in each, where the tab has them (mel_cat_tab_slots;
// by default first); their badges and those of the tiles are of the history when the file is built. So the row "На
// TorrServer" (view_ts_row.php), of its copy on the disk (ts_row.php): the timer reads the list when the copy is older
// than MEL_TV_TSR_S, a copy written elsewhere sets the flag itself (mel_tv_tsr_saved).

define('MEL_TV_EPF', 'tv');
// "from" of the actions of each EPF: this and its id (mel_tv_is_from): no screen of Melange is under them.
define('MEL_TV_FROM_PREFIX', 'tv:');
// The sub-items under "TV" in the flash directory (mel_tv_menu_put), and the switch in data_dir that
// removes them and keeps them away: a way back if the shell fails on them (the owner makes it by hand).
define('MEL_TV_MENU_FILE', 'rows_menu_config.json');
define('MEL_TV_SUB_OFF', 'tv_sub_off');
// The sub-items work on r24 and r25 (seen on the device); older ones not tried, and a file the shell
// does not take crashed it at every start: there and on an unknown firmware they are off, as by the switch.
define('MEL_TV_SUB_MIN_R', 24);
// The timer: all its requests in this many s (mel_tv_refresh: each given the whole seconds left, curl takes
// whole ones, so none runs past it; none with less than 1 s left). Building and comparing the EPF come on
// top, only in a tick with a new copy or other settings. php_server runs one call at a time: the info
// panel and ENTER on "TV" wait for it.
define('MEL_TV_TIMER_S', 3.0);
// The timer reads the list of TorrServer for the row "На TorrServer" (mel_tv_tsr_read) when its copy is older than this:
// the timer comes every 10 min and the read is a part of a second past its start, so 600 would be missed by that part
// at the next timer and the list read every 20 min; 9 min reads it at each timer.
define('MEL_TV_TSR_S', 540);
// "Configure list…" on "TV" (mel_tv_rows_saved): the requests of the rows chosen anew with no copy in this many s, as
// the timer asks them (mel_tv_ask); the user waits after the dialog, the rows past it come with the timer.
define('MEL_TV_ROWS_S', 5.0);
// In data_dir: the tiles of tv.json by id without the mode (mel_tv_tile), written with it.
define('MEL_TV_TILES_FILE', 'tv_tiles.json');
// In data_dir: what the timer last built the EPF with (mel_tv_timer_state), kept over a restart of php_server.
define('MEL_TV_TIMER_FILE', 'tv_timer_state');

class MelTv
{
    // mel_tv_selected of the operation that started at Mel::$t0 ($selected_t0); null: not read yet.
    /** @var bool|null */
    public static $selected = null;
    /** @var float */
    public static $selected_t0 = -1.0;
    // The flag set in the operation that started at Mel::$t0: not again in it.
    /** @var float */
    public static $flagged_t0 = -1.0;
    // The timer asks its copies: no flag of each, it compares the file itself.
    /** @var bool */
    public static $quiet = false;
    // The copies the timer saved meanwhile (the manifest, first pages of rows of the file).
    /** @var int */
    public static $new_copies = 0;
    // mel_settings_sig at the last operation (mel_tv_settings_seen), or of the settings.json mel_tv_rows_saved wrote itself
    // (it has set the flag of it already: none again at the next operation); null: none since a start of php_server.
    /** @var string|null */
    public static $sig = null;
    // The tiles of MEL_TV_TILES_FILE as read ($tiles_sig: 'ino:mtime:size' of the file then); null: not read.
    /** @var array<string, MelCatTile>|null */
    public static $tiles = null;
    /** @var string */
    public static $tiles_sig = '';
    // mel_tv_code_sig of this php_server; null: not taken yet.
    /** @var string|null */
    public static $code_sig = null;
}

// The directories of the EPF: tmp of tmp_dir_path ('' without one); flash of the fixed base of the shell
// (EPFS_BASE of its PHP, its loader of plugins), not of data_dir_path, which may be elsewhere (/persistfs):
// FS_PREFIX in front, as in bin/uninstall.sh.
/**
 * @return array{tmp: string, flash: string}
 */
function mel_tv_dirs()
{
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
    return array('tmp' => $tmp === '' ? '' : "$tmp/epfs", 'flash' => getenv('FS_PREFIX') . '/flashdata/plugins_epfs/' . MEL_PLUGIN);
}

// The EPF of the tab "TV" by id, each with the tabs of the catalog it shows: tv.json both (the item
// "TV" itself), movie.json and series.json one (its sub-items). Their tiles have the same ids.
/**
 * @return array<string, list<string>>
 */
function mel_tv_epfs()
{
    return array(MEL_TV_EPF => array('movie', 'series'), 'movie' => array('movie'), 'series' => array('series'));
}

// Melange is the TV application: the line "shell_ext:tv = melange" of epf_mapping.txt, which the
// shell writes before it calls the update_action of the chosen plugin (device r25, tvtab_probe).
// Read once an operation: each copy saved asks it (mel_tv_copy_saved).
/**
 * @return bool
 */
function mel_tv_selected()
{
    if (MelTv::$selected !== null && MelTv::$selected_t0 === Mel::$t0)
        return MelTv::$selected;
    $d = mel_tv_dirs();
    $f = dirname($d['flash']) . '/epf_mapping.txt';
    $text = is_file($f) ? strval(file_get_contents($f, false, null, 0, 65536)) : '';
    MelTv::$selected = preg_match('/^[ \t]*shell_ext:tv[ \t]*=[ \t]*' . MEL_PLUGIN . '[ \t]*\r?$/m', $text) === 1;
    MelTv::$selected_t0 = Mel::$t0;
    return MelTv::$selected;
}

// The flag for the shell to call the update_action again (mel_tv_update): <tmp_dir>/update_epfs_if_needed_flag
// with time() + 10, as the shell writes it for its own EPF (movie_helper.php; Global TV writes it empty).
// Only if Melange is the TV application, once an operation, never while the timer asks its copies.
// Never from mel_tv_update: the shell would call it again and again.
/**
 * @param string $why
 * @return void
 */
function mel_tv_flag($why)
{
    if (MelTv::$quiet || MelTv::$flagged_t0 === Mel::$t0 || !mel_tv_selected())
        return;
    $d = mel_tv_dirs();
    if ($d['tmp'] === '' || !is_dir(dirname($d['tmp'])))
        return;
    MelTv::$flagged_t0 = Mel::$t0;
    $ok = file_put_contents(dirname($d['tmp']) . '/update_epfs_if_needed_flag', strval(time() + 10)) !== false;
    mel_log("tv: flag: $why" . ($ok ? '' : ', not written'));
}

// The rows the files are built of by cat_rows $chosen: of the tabs of tv.json (mel_tv_epfs) in turn, as
// mel_catv_tab_copy_defs.
/**
 * @param list<MelCatRow> $rows
 * @param mixed $chosen
 * @return list<MelCatRow>
 */
function mel_tv_rows($rows, $chosen)
{
    $epfs = mel_tv_epfs();
    $out = array();
    foreach ($epfs[MEL_TV_EPF] as $tab)
        $out = array_merge($out, mel_cat_user_rows($rows, $chosen, $tab));
    return $out;
}

// The sub-items off: the switch MEL_TV_SUB_OFF is in data_dir, or the firmware is older than
// MEL_TV_SUB_MIN_R or unknown.
/**
 * @return bool
 */
function mel_tv_sub_off()
{
    if (mel_fw_r() < MEL_TV_SUB_MIN_R)
        return true;
    clearstatcache();
    return mel_data_dir() !== '' && file_exists(mel_data_dir() . '/' . MEL_TV_SUB_OFF);
}

// mel_cat_cache_write saved the copy $key: the manifest or the first page of a row of the file - the flag.
// The flag of this operation set already, a copy of no first page (a page further on, a card): no manifest read.
// Saved by the timer (it asks only those): counted, no flag.
/**
 * @param string $key
 * @return void
 */
function mel_tv_copy_saved($key)
{
    $first = $key === MEL_CAT_MANIFEST_KEY || substr($key, -2) === '|0';
    if (MelTv::$quiet)
        MelTv::$new_copies += $first ? 1 : 0;
    if (MelTv::$quiet || MelTv::$flagged_t0 === Mel::$t0 || !$first || !mel_tv_selected())
        return;
    $hit = $key === MEL_CAT_MANIFEST_KEY;
    if (!$hit)
    {
        $copy = mel_cat_manifest_copy();
        foreach (mel_tv_rows($copy['rows'], Mel::$settings['cat_rows']) as $row)
            $hit = $hit || mel_cat_page_key($row, 0) === $key;
    }
    if ($hit)
        mel_tv_flag($key === MEL_CAT_MANIFEST_KEY ? 'the manifest saved' : 'a row saved');
}

// The copy of the row "На TorrServer" written anew (mel_tsr_put, the row shown): saved by the timer (mel_tv_tsr_read) -
// counted, no flag; else the flag, as mel_tv_copy_saved.
/**
 * @return void
 */
function mel_tv_tsr_saved()
{
    if (MelTv::$quiet)
        MelTv::$new_copies++;
    mel_tv_flag('the TorrServer copy');
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
function mel_tv_settings_seen($in)
{
    $sig = mel_settings_sig();
    $was = MelTv::$sig;
    MelTv::$sig = $sig;
    $c = isset($in->control_id) ? $in->control_id : '';
    if ($was !== null && $sig !== $was && $c !== 'tv_update' && $c !== 'tv_timer')
        mel_tv_flag('settings.json changed');
}

// ru or en of interface_language the shell sends with the update_action ("russian" as in
// settings.properties; the form of the value is not proven); none or "custom": of the settings.
/**
 * @param stdClass $in
 * @return string
 */
function mel_tv_lang($in)
{
    $l = mel_catv_in($in, 'interface_language');
    if ($l === '' || $l === 'custom')
        return mel_catv_lang();
    return $l === 'russian' || $l === 'ru' ? 'ru' : 'en';
}

// A row "Melange" of one tile that is no title: $id ("tv:open", "tv:setup"), its caption of $key.
/**
 * @param string $id
 * @param string $key
 * @param string $lang
 * @return array{array<string, mixed>, array<string, mixed>}
 */
function mel_tv_note_defs($id, $key, $lang)
{
    $row = array('key' => 'tv/' . MEL_TV_EPF, 'type' => 'movie', 'id' => MEL_TV_EPF, 'name' => mel_tr($lang, 'plugin_caption'),
        'genre' => '', 'home' => false, 'group' => '');
    $item = mel_catv_item($id, mel_tr($lang, $key), MEL_CATV_ICONS . 'no_movie_poster.png');
    return mel_catv_row_defs(mel_catv_list(mel_cat_row_state($row), array(), false), $lang, array($item));
}

// The views of the EPF (mel_tv_epfs) by id: the rows of their tabs of the catalog from the copies in the order of the
// tab (mel_catv_tab_copy_defs), the rows "Continue", "Мой список" and "На TorrServer" (mel_cp_row_defs, mel_ml_row_defs,
// mel_tsr_row_defs: of both types on tv.json, there once, at its place on the first tab that shows it) where the tab
// has them, as
// a screen of rows opened now (the mode of the tiles and the plates of the settings and the firmware,
// as mel_catv_logo_of gives, but not kept among its screens: the EPF would push out a screen of Melange).
// Each tab built once. No source ready (Lampac too): one tile "Set up Melange" ("tv:setup"), as the catalog opens
// the settings; no row of its tabs with a copy: one tile "Open Melange" ("tv:open"). The mode of the screen
// of rows before it is back after it: the grid of "Show all" asks for its next tiles without mel_catv_logo_of.
// With the switch MEL_TV_SUB_OFF only tv.json: the sub-items are not shown, so their files are neither
// written (the flash wears) nor compared by the timer; those there already are kept.
/**
 * @param string $lang
 * @param array<string, MelCatTile>|null $tiles
 * @param-out array<string, MelCatTile> $tiles
 * @return array<string, array<string, mixed>>
 */
function mel_tv_views($lang, &$tiles = null)
{
    $logo = MelCat::$logo;
    $rating = MelCat::$rating;
    MelCat::$logo = mel_catv_logo_effective();
    MelCat::$rating = Mel::$settings['cat_rating'];
    $tiles = array();
    $tabs = array();
    $epfs = mel_tv_epfs();
    if (Mel::$settings['ready'])
    {
        foreach ($epfs[MEL_TV_EPF] as $tab)
        {
            $tabs[$tab] = mel_catv_tab_copy_defs($tab, $lang);
            $lists = $tabs[$tab]['lists'];
            foreach ($tabs[$tab]['slots'] as $slot)
            {
                foreach (is_string($slot) ? array() : $slot as $def)
                {
                    foreach (isset($def[PluginRow::data][PluginRegularRow::items]) ? $def[PluginRow::data][PluginRegularRow::items] : array() as $it)
                    {
                        list(, $id) = mel_catv_id_mode(strval($it[PluginRegularItem::id]));
                        $p = explode(':', $id, 2);
                        if (count($p) === 2 && isset($lists[$p[0]]['tiles'][$p[1]]))
                            $tiles[$id] = $lists[$p[0]]['tiles'][$p[1]];
                    }
                }
            }
        }
    }
    $views = array();
    $off = mel_tv_sub_off();
    // The meta of the tiles of the rows "Continue" and "Мой список" shown on a tab, of no request (mel_cpm_build): kept,
    // of the rows in memory or on the disk.
    if (Mel::$settings['ready'])
        mel_cpm_build('', 0.0, mel_tv_own_names($tabs, $epfs[MEL_TV_EPF]));
    foreach ($epfs as $epf => $of)
    {
        if ($off && $epf !== MEL_TV_EPF)
            continue;
        $cp_tiles = array();
        $ml_tiles = array();
        $one = count($of) === 1 ? $of[0] : '';
        // The rows of the tabs by their slots; a row of Melange of both types (tv.json) once, at its place on the first
        // tab that shows it.
        $names = mel_tv_own_names($tabs, $of);
        $own = array();
        if (in_array('@continue', $names, true))
            $own['@continue'] = mel_cp_row_defs($one, $lang, $cp_tiles);
        if (in_array('@mylist', $names, true))
            $own['@mylist'] = mel_ml_row_defs($one, $lang, $ml_tiles);
        // Its known tiles by the history alone (mel_catv_info): none to the tiles of the files.
        if (in_array('@torrserver', $names, true))
            $own['@torrserver'] = mel_tsr_row_defs($one, $lang, MEL_TV_FROM_PREFIX . $epf);
        $tiles = array_merge($tiles, $cp_tiles, $ml_tiles);
        $defs = array();
        foreach ($of as $tab)
        {
            foreach (isset($tabs[$tab]) ? $tabs[$tab]['slots'] : array() as $slot)
            {
                if (!is_string($slot))
                    $defs = array_merge($defs, $slot);
                else if (isset($own[$slot]))
                {
                    $defs = array_merge($defs, $own[$slot]);
                    unset($own[$slot]);
                }
            }
        }
        if (!Mel::$settings['ready'])
            $defs = mel_tv_note_defs('tv:setup', 'tv_setup', $lang);
        else if (!$defs)
            $defs = mel_tv_note_defs('tv:open', 'tv_open', $lang);
        $view = mel_catv_rows_view($defs, null, MEL_TV_FROM_PREFIX . $epf);
        // No search key of the plugin on the main screen: POP UP has the search (mel_tv_menu). Nor the colour
        // keys: they stay with the main screen.
        unset($view[PluginFolderView::data][PluginRowsFolderView::actions][GUI_EVENT_KEY_SEARCH]);
        foreach (mel_egg_keys() as $ev)
            unset($view[PluginFolderView::data][PluginRowsFolderView::actions][$ev]);
        $views[$epf] = $view;
    }
    MelCat::$logo = $logo;
    MelCat::$rating = $rating;
    return $views;
}

// The names of the rows of Melange (mel_cat_pseudo: '@continue') shown on the tabs $of, by the slots of the tabs $tabs
// (mel_catv_tab_copy_defs by tab), each once.
/**
 * @param array<string, array{slots: list<string|list<array<string, mixed>>>}> $tabs
 * @param list<string> $of
 * @return list<string>
 */
function mel_tv_own_names($tabs, $of)
{
    $out = array();
    foreach ($of as $tab)
        $out = array_merge($out, isset($tabs[$tab]) ? mel_cat_slot_names($tabs[$tab]['slots']) : array());
    return array_values(array_unique($out));
}

// The JSON of each EPF (mel_tv_views) by id; null when one can not be made. $tiles: their tiles.
/**
 * @param string $lang
 * @param array<string, MelCatTile>|null $tiles
 * @param-out array<string, MelCatTile> $tiles
 * @return array<string, string>|null
 */
function mel_tv_jsons($lang, &$tiles = null)
{
    $out = array();
    foreach (mel_tv_views($lang, $tiles) as $epf => $view)
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
function mel_tv_same($f, $data)
{
    clearstatcache();
    return is_file($f) && filesize($f) === strlen($data) && file_get_contents($f) === $data;
}

// The tiles of the EPF to MEL_TV_TILES_FILE (one map: movie.json and series.json hold tiles of tv.json),
// unless they are there already: 'same', 'new' or 'failed'.
// Not in plugins_epfs: the shell reads that, and it is no copy of the catalog (data_dir/catalog prunes).
// With the version of the copies: {"v": MEL_CAT_CACHE_V, "tiles": {"<rid>:<id>": tile}}.
/**
 * @param array<string, MelCatTile> $tiles
 * @return string
 */
function mel_tv_tiles_save($tiles)
{
    $f = mel_data_dir() . '/' . MEL_TV_TILES_FILE;
    $json = json_encode(array('v' => MEL_CAT_CACHE_V, 'tiles' => (object) $tiles));
    if (mel_data_dir() === '' || !is_string($json))
        return 'failed';
    if (mel_tv_same($f, $json))
        $res = 'same';
    else
        $res = mel_write_file($f, $json) ? 'new' : 'failed';
    MelTv::$tiles = $tiles;
    MelTv::$tiles_sig = mel_file_sig(MEL_TV_TILES_FILE);
    return $res;
}

// The tile $id (without the mode, "<rid>:<id>") of tv.json as written (mel_tv_tiles_save): the tab "TV"
// shows the file, while a screen of Melange may have got its rows anew since, without it. From memory,
// read again when the file is another one (a restart of php_server: from the disk). null: not in it.
// As a copy of the catalog (mel_cat_cache_read, mel_cat_page_cached): of a version not read (mel_cat_v_ok) none,
// each tile made again by mel_cat_tile, so a key MelCatTile got since (an update of the zip) has its value.
/**
 * @param string $id
 * @return MelCatTile|null
 */
function mel_tv_tile($id)
{
    $sig = mel_file_sig(MEL_TV_TILES_FILE);
    if (MelTv::$tiles === null || $sig !== MelTv::$tiles_sig)
    {
        $j = $sig === '' ? null : json_decode(strval(file_get_contents(mel_data_dir() . '/' . MEL_TV_TILES_FILE)), true);
        MelTv::$tiles = array();
        if (is_array($j) && isset($j['v'], $j['tiles']) && mel_cat_v_ok($j['v']) && is_array($j['tiles']))
        {
            foreach ($j['tiles'] as $k => $m)
            {
                $t = mel_cat_tile($m, mel_str($m, 'type'));
                if ($t)
                    MelTv::$tiles[strval($k)] = $t;
            }
        }
        MelTv::$tiles_sig = $sig;
    }
    return isset(MelTv::$tiles[$id]) ? MelTv::$tiles[$id] : null;
}

// folder_key of rows_info (the shell sends "<plugin>:<id of the EPF>") is of an EPF of the tab "TV",
// not of a screen of Melange ("melange:<media_url>", "melange:catalog"): so only their ids (mel_tv_epfs),
// no pattern, as mel_tv_is_from.
/**
 * @param string $key
 * @return bool
 */
function mel_tv_is_key($key)
{
    $epfs = mel_tv_epfs();
    $n = strlen(MEL_PLUGIN) + 1;
    return substr($key, 0, $n) === MEL_PLUGIN . ':' && isset($epfs[strval(substr($key, $n))]);
}

// $data to $path unless it is there already (the flash wears): 'same', 'new' or 'failed'.
// Atomic (mel_write_file); what an interrupted write left in the directory goes.
/**
 * @param string $path
 * @param string $data
 * @return string
 */
function mel_tv_put($path, $data)
{
    $dir = dirname($path);
    if (mel_tv_same($path, $data))
        return 'same';
    if (!is_dir($dir) && !mkdir($dir, 0755, true))
        return 'failed';
    foreach (glob("$dir/*.tmp") ?: array() as $f)
        unlink($f);
    return mel_write_file($path, $data, 0644) ? 'new' : 'failed';
}

// The answer to the update_action and the timer: done.
/**
 * @return MelAction
 */
function mel_tv_status()
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
function mel_tv_menu_json($lang)
{
    $items = array();
    foreach (array('movie' => 'cat_movies', 'series' => 'cat_series') as $epf => $key)
    {
        $items[] = array(
            'type' => 'plugin',
            'name' => MEL_PLUGIN,
            'epf_id' => $epf,
            // Without the no-break space at the end of cat_movies, cat_series (of the search, mel_catv_search_run).
            'caption' => trim(str_replace("\xc2\xa0", ' ', mel_tr($lang, $key))),
            'small_icon_url' => 'plugin_file://%' . MEL_PLUGIN . "%/img/tv_sub_$epf.png",
            'small_icon_width' => 100);
    }
    $json = json_encode(array('rows_menu_items' => $items));
    return is_string($json) ? $json : '';
}

// rows_menu_config.json of the flash directory $flash, after movie.json and series.json there ($epfs:
// written or the same): 'new', 'same', 'failed'; 'not written' when one of them failed. With the switch
// MEL_TV_SUB_OFF in data_dir never written, removed: 'off', 'off, removed', 'off, not removed'; 'off r22' and so on
// when the firmware turns them off (mel_tv_sub_off).
/**
 * @param string $flash
 * @param string $lang
 * @param bool $epfs
 * @return string
 */
function mel_tv_menu_put($flash, $lang, $epfs)
{
    $f = "$flash/" . MEL_TV_MENU_FILE;
    if (mel_tv_sub_off())
    {
        $off = mel_fw_r() < MEL_TV_SUB_MIN_R ? 'off r' . mel_fw_r() : 'off';
        return !is_file($f) ? $off : (@unlink($f) ? "$off, removed" : "$off, not removed");
    }
    if (!$epfs)
        return 'not written';
    $json = mel_tv_menu_json($lang);
    return $json === '' ? 'failed' : mel_tv_put($f, $json);
}

// interface_language as it came, to the log: its form is not proven.
/**
 * @param stdClass $in
 * @return string
 */
function mel_tv_lang_log($in)
{
    return ' interface_language=' . substr(strval(preg_replace('/[^A-Za-z_-]/', '', mel_catv_in($in, 'interface_language'))), 0, 20);
}

// update_action of embeddable_plugin_folders (at a start of the shell, a wake, the choice of the TV
// application): each EPF (mel_tv_views) to both directories, its .no_internet.json (the same rows: they
// need no network) to the flash one, then rows_menu_config.json there (mel_tv_menu_put), each only if
// it changed. Never the flag update_epfs_if_needed_flag here: the shell would call this again and again.
// The answer status. Not the TV application: nothing written.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_tv_update($in)
{
    $t0 = microtime(true);
    $status = mel_tv_status();
    $boot = isset($in->first_run_after_boot) && is_scalar($in->first_run_after_boot) ?
        substr(strval(preg_replace('/[^0-9A-Za-z]/', '', strval($in->first_run_after_boot))), 0, 8) : '';
    $what = 'tv: update' . ($boot !== '' ? " first_run_after_boot=$boot" : '') . mel_tv_lang_log($in);
    $lang = mel_tv_lang($in);
    // Not set back: the info panel of the tiles of the file (rows_info, mel_catv_ui_lang) speaks its language.
    MelCat::$lang = $lang;
    mel_log(sprintf('%s%s, %.0f ms', $what, mel_tv_write($lang), (microtime(true) - $t0) * 1000));
    // The shell takes rows_menu_config.json by its mtime, as of Global TV: no menu read anew here.
    return $status;
}

// Each EPF (mel_tv_views) of $lang to both directories of mel_tv_dirs (tmp_dir one there), its .no_internet.json (the
// same rows: they need no network) to the flash one, then rows_menu_config.json there (mel_tv_menu_put) and the tiles
// (mel_tv_tiles_save), each only if it changed: a file written anew has a newer mtime, the shell takes no other one
// ("already up-to-date"). No tmp_dir or not the TV application: nothing written. -> for the log after the name of the
// caller: " <lang>: <what written>", or ": <why nothing written>" (" <lang>: …" when no JSON of a view). $new: the ids
// of the EPF with other bytes now.
/**
 * @param string $lang
 * @param list<string>|null $new
 * @param-out list<string> $new
 * @return string
 */
function mel_tv_write($lang, &$new = null)
{
    $new = array();
    $d = mel_tv_dirs();
    if ($d['tmp'] === '')
        return ': no tmp_dir_path, nothing written';
    if (!mel_tv_selected())
        return ': not the TV application, nothing written';
    $jsons = mel_tv_jsons($lang, $tiles);
    if ($jsons === null)
        return " $lang: no JSON of the view, nothing written";
    $out = array();
    $tmp_tv = 'failed';
    // The menu only after every sub-item written: none left out by the switch, none failed.
    $subs = count($jsons) === count(mel_tv_epfs());
    foreach ($jsons as $epf => $json)
    {
        $tmp = mel_tv_put("{$d['tmp']}/$epf.json", $json);
        $flash = mel_tv_put("{$d['flash']}/$epf.json", $json);
        $ni = mel_tv_put("{$d['flash']}/$epf.no_internet.json", $json);
        if ($epf === MEL_TV_EPF)
            $tmp_tv = $tmp;
        else
            $subs = $subs && $flash !== 'failed';
        // Not the bytes of the flash already: the shell stores tmp_dir there and removes it, the one of tmp_dir alone is
        // written again with nothing new to show.
        if (($tmp === 'new' || $flash === 'new') && $flash !== 'same')
            $new[] = $epf;
        $out[] = ($epf === MEL_TV_EPF ? '' : "$epf ") . "$tmp/$flash " . strlen($json) . " B, no_internet $ni";
    }
    $menu = mel_tv_menu_put($d['flash'], $lang, $subs);
    // The tiles of the files the shell reads first.
    $ts = $tmp_tv !== 'failed' ? mel_tv_tiles_save($tiles) : 'not written';
    return sprintf(' %s: %s, menu %s, tiles %d %s', $lang, implode(', ', $out), $menu, count($tiles), $ts);
}

// The file of the EPF $epf the shell shows holds $data: the one of tmp_dir while it is there, else the one
// of the flash (mel_tv_dirs). The shell moves the one of tmp_dir to the flash (removes it, the same bytes
// there) at any time: gone while read, the flash one is shown.
/**
 * @param string $epf
 * @param string $data
 * @return bool
 */
function mel_tv_shown_same($epf, $data)
{
    $d = mel_tv_dirs();
    $tmp = "{$d['tmp']}/$epf.json";
    if (mel_tv_same($tmp, $data))
        return true;
    clearstatcache();
    return !is_file($tmp) && mel_tv_same("{$d['flash']}/$epf.json", $data);
}

// The build of the code this php_server runs: the latest mtime of the *.php of the plugin and of the
// translations (the captions of the EPF, mel_tr), taken once (zip keeps the mtime of each file, a zip of
// the same MEL_VERSION has another one if any of them changed).
/**
 * @return string
 */
function mel_tv_code_sig()
{
    if (MelTv::$code_sig === null)
    {
        $t = 0;
        $dir = dirname(__FILE__);
        $files = array_merge(glob("$dir/*.php") ?: array(), glob("$dir/translations/dune_language_*.txt") ?: array());
        foreach ($files as $f)
            $t = max($t, intval(@filemtime($f)));
        MelTv::$code_sig = strval($t);
    }
    return MelTv::$code_sig;
}

// What the EPF are built with besides the copies: settings.json (mel_settings_sig), the filter of the tiles
// (mel_ht_filter_sig: the file of the hidden titles - the settings page takes one back), the language, the version and
// the build of the plugin (another one may build them otherwise), the history (mel_wh_sig: the row "Continue" and the
// badges; a file of it written within 2 s: none, the next tick builds them again). Not the copy of the row "На
// TorrServer": each write of it is counted or sets the flag itself (mel_tv_tsr_saved).
/**
 * @param string $lang
 * @return string
 */
function mel_tv_timer_state($lang)
{
    return mel_settings_sig() . ' ' . mel_ht_filter_sig() . " $lang " . MEL_VERSION . ' ' . mel_tv_code_sig() . ' ' .
        md5(strval(mel_wh_sig()));
}

// The timer: first the list of TorrServer for the row "На TorrServer" (mel_tv_tsr_read, MEL_TSR_TICK_S of $limit), then
// the copy of the manifest older than MEL_CAT_FRESH asked anew (not MEL_CAT_MANIFEST_FRESH:
// the timer comes every 10 min), then the first pages
// of the rows of the file without a copy or with one older than MEL_CAT_FRESH, one by one in $limit s: those
// without a copy first, then the oldest copy (of the same time in the order of the file), each part a movie and a
// series in turn (mel_tv_in_turn); then the rows that timed out before (MelCat::$slow, as mel_catv_open: one slow
// row does not take the budget of every tick). A timeout is not the server down; after a failure of the server
// (mel_cat_err_down) no more. Fresh copies are not asked: at most a request a row an hour, however often the shell
// calls. No flag of each copy (MelTv::$quiet), they are counted (MelTv::$new_copies). Each request gets the whole
// seconds left (curl takes whole ones: rounded up, the last could run past $limit), none begins with less than 1 s.
// -> for the log.
/**
 * @param float $limit
 * @return string
 */
function mel_tv_refresh($limit)
{
    $start = microtime(true);
    MelTv::$quiet = true;
    MelTv::$new_copies = 0;
    $ts = mel_tv_tsr_read();
    $copy = mel_cat_manifest_copy();
    $rows = $copy['rows'];
    $man = 'manifest fresh';
    $err = '';
    if (!$copy['rows'] || !mel_cat_is_fresh($copy))
    {
        $secs = intval(min(MEL_CATV_REFRESH_S, floor($limit - (microtime(true) - $start))));
        $m = $secs >= 1 ? mel_cat_manifest_fetch($secs) : null;
        $rows = $m && $m['rows'] ? $m['rows'] : $rows;
        $err = $m ? $m['err'] : '';
        $man = $m ? 'manifest asked' . ($err !== '' ? " ($err)" : '') : 'manifest old, no time';
    }
    $tv_rows = mel_tv_rows($rows, Mel::$settings['cat_rows']);
    $none = array();
    $old = array();
    $old_t = array();
    $slow = array();
    foreach ($tv_rows as $i => $row)
    {
        $c = mel_cat_page_cached($row, 0);
        if (mel_cat_is_fresh($c))
            continue;
        if (mel_catv_is_slow(mel_catv_rid($row)))
            $slow[] = $i;
        else if (!$c)
            $none[] = $i;
        else
        {
            $old[] = $i;
            $old_t[] = $c['t'];
        }
    }
    // Copies of an older version are all old at once, a few rows a tick: the series would wait for all the movies.
    $queue = array_merge(mel_tv_in_turn($tv_rows, $none), mel_tv_in_turn($tv_rows, mel_cat_oldest_first($old, $old_t)),
        $slow);
    $ask = array();
    foreach ($queue as $i)
        $ask[] = $tv_rows[$i];
    $asked = mel_tv_ask($ask, $start, $limit, $err);
    MelTv::$quiet = false;
    return sprintf('%s%s, rows asked %d of %d old', $ts, $man, $asked, count($queue));
}

// The timer (mel_tv_refresh, its copies counted): the row "На TorrServer" shown on a tab ("TV" shows the rows of the
// tabs, mel_tsr_any) of a copy older than MEL_TV_TSR_S (mel_tsr_old) - the list read (mel_tsr_read); a new copy counted
// (mel_tv_tsr_saved). -> for the log: '' none read, else "TorrServer <the same, new, the key of the error>, ".
/**
 * @return string
 */
function mel_tv_tsr_read()
{
    if (!mel_tsr_any() || !mel_tsr_old(MEL_TV_TSR_S))
        return '';
    list($err, $new) = mel_tsr_read();
    return 'TorrServer ' . ($err !== '' ? $err : ($new ? 'new' : 'the same')) . ', ';
}

// The first pages of the rows $rows one by one, in their order, in $limit s since $start: each request the whole
// seconds left, none begun with less than 1 s, none after a failure of the server (mel_cat_err_down; $err: of a
// request before). -> how many asked.
/**
 * @param list<MelCatRow> $rows
 * @param float $start
 * @param float $limit
 * @param string $err
 * @return int
 */
function mel_tv_ask($rows, $start, $limit, $err = '')
{
    $asked = 0;
    foreach ($rows as $row)
    {
        $secs = floor($limit - (microtime(true) - $start));
        if (mel_cat_err_down($err) || $secs < 1)
            break;
        $st = mel_cat_row_state($row);
        // The first page alone, whatever the filter takes of it: its copy is all the files need.
        $res = mel_cat_more($st, $secs, 0);
        $err = $res['err'];
        mel_catv_slow_note(mel_catv_rid($row), $err);
        $asked++;
    }
    return $asked;
}

// $list (indexes of $tv_rows in the order to ask) a movie and a series in turn: by the place of each in the part of
// $list of its tab, of the same place in the order of $list.
/**
 * @param list<MelCatRow> $tv_rows
 * @param list<int> $list
 * @return list<int>
 */
function mel_tv_in_turn($tv_rows, $list)
{
    $n = array_fill_keys(mel_cat_tabs(), 0);
    $place = array();
    foreach ($list as $i)
        $place[] = $n[mel_cat_row_tab($tv_rows[$i])]++;
    return mel_cat_oldest_first($list, $place);
}

// topmenu_timer_action of the manifest: the old copies asked anew (mel_tv_refresh, none without AIOStreams:
// the files have one tile then). Only when one came new or mel_tv_timer_state is another than the one of
// MEL_TV_TIMER_FILE (none there: another too) each EPF built and compared with the file the shell shows
// (mel_tv_shown_same; not the one of tmp_dir alone: the shell takes it away, the timer would find it
// changed each time): any one another - the flag, the shell calls the update_action, which writes them.
// Else nothing built: most ticks ask nothing, php_server is not held up for it. The state written only
// when another (the flash wears). Not the TV application: nothing asked, nothing written.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_tv_timer($in)
{
    $t0 = microtime(true);
    $what = 'tv: timer' . mel_tv_lang_log($in);
    $d = mel_tv_dirs();
    if ($d['tmp'] === '' || !mel_tv_selected())
    {
        mel_log("$what: " . ($d['tmp'] === '' ? 'no tmp_dir_path' : 'not the TV application') . ', nothing asked');
        return mel_tv_status();
    }
    // Before the requests: Mel::$settings are of the start of the operation, the page may save meanwhile.
    $lang = mel_tv_lang($in);
    $state = mel_tv_timer_state($lang);
    $asked = 'AIOStreams not set, nothing asked';
    $new = 0;
    if (Mel::$settings['ready'])
    {
        $asked = mel_tv_refresh(MEL_TV_TIMER_S);
        $new = MelTv::$new_copies;
    }
    $sf = mel_data_dir() . '/' . MEL_TV_TIMER_FILE;
    clearstatcache();
    $was = mel_data_dir() !== '' && is_file($sf) ? strval(file_get_contents($sf, false, null, 0, 1024)) : '';
    $why = $new > 0 ? "new copies $new" : ($was === '' ? 'no state' : ($state !== $was ? 'state changed' : ''));
    if ($why === '')
    {
        mel_log(sprintf('%s %s: %s; nothing to do, %.0f ms', $what, $lang, $asked, (microtime(true) - $t0) * 1000));
        return mel_tv_status();
    }
    $jsons = mel_tv_jsons($lang);
    $changed = array();
    foreach ($jsons ?: array() as $epf => $json)
    {
        if (!mel_tv_shown_same($epf, $json))
            $changed[] = $epf;
    }
    if ($jsons !== null && $state !== $was && mel_data_dir() !== '')
        mel_write_file($sf, $state);
    mel_log(sprintf('%s %s: %s; %s, the file %s, %.0f ms', $what, $lang, $asked, $why,
        $changed ? 'changed (' . implode(', ', $changed) . ')' : 'the same', (microtime(true) - $t0) * 1000));
    if ($changed)
        mel_tv_flag('the timer');
    return mel_tv_status();
}

// --- The actions of the EPF: from "tv:<id of the EPF>", the tiles of the catalog as on its screens.

// $from of an action is an EPF of the tab "TV" ("tv:<id>" of mel_tv_epfs, no pattern, as mel_tv_is_key):
// no screen of Melange is under it, so none may be replaced (mel_replace_act); a screen of Melange opens
// over the main screen.
/**
 * @param string $from
 * @return bool
 */
function mel_tv_is_from($from)
{
    $epfs = mel_tv_epfs();
    $n = strlen(MEL_TV_FROM_PREFIX);
    return substr($from, 0, $n) === MEL_TV_FROM_PREFIX && isset($epfs[strval(substr($from, $n))]);
}

// The catalog of Melange, its entry "catalog", over the main screen.
/**
 * @return MelAction
 */
function mel_tv_catalog_act()
{
    return mel_catv_open_act('catalog', '%tr%plugin_caption');
}

// ENTER in the EPF: "Open Melange" - the catalog, "Set up Melange" - the settings; any other tile as on
// a screen of rows (mel_catv_enter: a title - its card, "Show all" - that screen of Melange).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_tv_enter($in)
{
    $id = mel_catv_in($in, 'selected_item_id');
    if ($id === 'tv:open')
        return mel_tv_catalog_act();
    if ($id === 'tv:setup')
        return mel_setup_open_act();
    // None in the file; on a screen of Melange they replace or redraw it, here there is none.
    if (strpos($id, 'retry:') === 0 || strpos($id, 'fail:') === 0)
        return null;
    $a = mel_catv_enter($in);
    // A tile of the file in no copy and not in memory: the file is old.
    if ($a === mel_error('cat_outdated', '', false, false))
        mel_tv_flag('a tile of the file is outdated');
    return $a;
}

// The tab of the EPF $from of one tab ("tv:movie", "tv:series"); '' of tv.json (both) and of any other $from.
/**
 * @param string $from
 * @return string
 */
function mel_tv_tab_of($from)
{
    $epfs = mel_tv_epfs();
    $id = strval(substr($from, strlen(MEL_TV_FROM_PREFIX)));
    return mel_tv_is_from($from) && count($epfs[$id]) === 1 ? $epfs[$id][0] : '';
}

// The tab of the rows "Configure list…" edits from the EPF $from, on the header $hid: of movie.json, series.json theirs;
// of tv.json (both) the tab of the row of the header in the copy of the manifest, else (the rows "Continue" and "Мой
// список" of both, a note) the first one, the movies.
/**
 * @param string $from
 * @param string $hid
 * @return string
 */
function mel_tv_rows_tab($from, $hid)
{
    $tab = mel_tv_tab_of($from);
    if ($tab !== '')
        return $tab;
    $rid = mel_catv_header_rid($hid);
    $row = $rid !== '' ? mel_catv_row_by_rid($rid) : null;
    $epfs = mel_tv_epfs();
    return $row ? mel_cat_row_tab($row) : $epfs[MEL_TV_EPF][0];
}

// "Configure list…" from the EPF saved (mel_catv_rows_apply: cat_rows $was -> $now, the copy of the manifest $rows): the
// rows of the files chosen now and not before with no copy of their first page asked at once (mel_tv_ask in
// MEL_TV_ROWS_S; none without AIOStreams, as the timer); then the files written here (mel_tv_write, in the language of
// the settings of the shell) and those written anew invalidated by their ids: the shell reads them again at once, with
// the focus in the rows too (as DUNE-HD.TV from POP UP of its EPF: update_tv_epf, invalidate_folders of its id; device
// r25). No flag then: the update_action it calls writes tmp_dir again, and the shell postpones the same view until the
// focus leaves the rows (code r25, knowledge epf-pending-apply). Nothing written anew (nothing changed, not written,
// not the TV application): null, as no screen of Melange is under the EPF, and the flag (mel_tv_flag sets none when
// not the TV application). No flag of each copy (MelTv::$quiet) nor of the new settings.json.
/**
 * @param list<MelCatRow> $rows
 * @param mixed $was
 * @param mixed $now
 * @return MelAction|null
 */
function mel_tv_rows_saved($rows, $was, $now)
{
    $start = microtime(true);
    $before = mel_catv_lcfg_keys(mel_tv_rows($rows, $was));
    $none = array();
    foreach (mel_tv_rows($rows, $now) as $row)
    {
        if (!isset($before[$row['key']]) && !mel_cat_page_cached($row, 0))
            $none[] = $row;
    }
    $asked = 0;
    if ($none && Mel::$settings['ready'])
    {
        MelTv::$quiet = true;
        $asked = mel_tv_ask($none, $start, MEL_TV_ROWS_S);
        MelTv::$quiet = false;
    }
    $res = mel_tv_write(mel_catv_lang(), $new);
    $a = $new ? mel_catv_invalidate($new[0]) : null;
    if ($a)
        $a[GuiAction::data][PluginInvalidateFoldersActionData::media_urls] = $new;
    mel_log(sprintf('tv: rows: %d chosen anew with no copy, %d asked; written%s; %s, %.0f ms', count($none), $asked, $res,
        $new ? 'invalidate ' . implode(', ', $new) : 'no invalidate', (microtime(true) - $start) * 1000));
    if (!$a)
        mel_tv_flag('the rows chosen');
    // settings.json written here seen: no flag of it again at the next operation (mel_tv_settings_seen).
    MelTv::$sig = mel_settings_sig();
    return $a;
}

// POP UP in the EPF, as on a main screen of the catalog (mel_catv_menu): on a tile of a title its items first
// (mel_catv_tile_menu), on the header of a row "Configure list…" (mel_catv_rows_menu_items) of its tab
// (mel_tv_rows_tab), its dialogs from "tv:<tab>"; then the parts of the strip (mel_catv_part_items; each a screen of
// Melange over the main screen, mel_catv_strip_enter) with "Open Melange" (the catalog) before the settings. The search
// key is not the plugin's there: the main screen has its own (mel_tv_views).
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_tv_menu($in)
{
    $from = mel_catv_in($in, 'from');
    $hid = mel_catv_in($in, 'selected_header_id');
    $lang = mel_catv_lang();
    mel_log("tv: POP UP on '" . mel_cut(mel_catv_in($in, 'selected_item_id'), 100) . "', header '" . mel_cut($hid, 100) . "'");
    // On a tile (no header in focus), as mel_catv_menu.
    $items = $hid === '' ? mel_catv_tile_menu(mel_catv_in($in, 'selected_item_id'), $from, $lang) : array();
    $tab = mel_tv_rows_tab($from, $hid);
    $screen = mel_catv_rows_menu_items($hid, $tab, MEL_TV_FROM_PREFIX . $tab, $lang);
    $parts = mel_catv_part_items($from, $lang);
    $setup = $parts['tab_setup'];
    unset($parts['tab_setup']);
    $parts['tv_open'] = array(
        GuiMenuItemDef::caption => mel_tr($lang, 'tv_open'),
        GuiMenuItemDef::icon_url => MEL_CATV_ICONS . 'movies_small.png',
        GuiMenuItemDef::action => mel_tv_catalog_act());
    $parts['tab_setup'] = $setup;
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => mel_catv_menu_join($items, array_merge($screen, array_values($parts)))));
}
