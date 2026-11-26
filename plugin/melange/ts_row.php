<?php
// The row "На TorrServer" of the catalog, its data: a copy of the newest entries of the list of TorrServer in data_dir
// (MEL_TSR_FILE; the row is drawn of it at once, a read of the list refreshes it), only while the row is shown on a tab
// and not «Без торрентов» (mel_tsr_any): else no read, no write but of a removal (mel_tsr_drop: the copy on the disk
// edited with the row hidden too); the movie of a torrent by the history of Melange (its hash in the uid of a record
// "aio:"). No firmware API. Reads Mel::$settings, MelTsl, the history (history.php); writes
// MelTsr and the copy. Needs ts_list.php (MelTsl, mel_tsl_fetch, mel_tsl_video_count, mel_tsl_files_of, mel_tsl_on,
// mel_tsl_split, mel_tsl_name), catalog.php (mel_cat_tab_slots, mel_cat_slot_names), view_catalog.php
// (MEL_CATV_ROW_TILES), history.php (mel_wh_ours_all), tv_tab.php (mel_tv_flag), parse.php, main.php. Functions are global; the require order is
// in main.php.

// The copy of the row in data_dir: {"v": 1, "sig": mel_tsr_sig, "tiles": [{"e": the entry (mel_tsl_entry; its sizes as
// strings: json_decode of the 32-bit Dune clamps an integer over 2 GB), "nv": its video files (-1 not known), "fname":
// the name of the torrent by its files (mel_tsl_name)}, ...]}, the newest first. No time of its read in it: a restart of
// php_server reads the list anew at once (MelTsr::$read_t).
define('MEL_TSR_FILE', 'ts_row.json');
// The tiles of the row at most, as "Продолжить" and "Избранное".
define('MEL_TSR_MAX', MEL_CATV_ROW_TILES);
// The JSON of the files of an entry kept at most (bytes, ~300 files): those of a bigger pack are not kept (files null:
// known by "get" on OK), its nv and fname are.
define('MEL_TSR_FILES_MAX', 32768);
// The total s of the read of the row (its connect 1 s, mel_ts_call: TorrServer down fails in 1 s): a tick of the screen.
define('MEL_TSR_TICK_S', 1.0);
// The copy is fresh this many s after a read of the list (the row or the section).
define('MEL_TSR_FRESH_S', 60);

class MelTsr
{
    // The copy as read or written: hash => its tile in the order of the row; null: not read yet.
    /** @var array<string, MelTsrTile>|null */
    public static $tiles = null;
    // mel_tsr_sig of $tiles ('' none); mel_file_sig of MEL_TSR_FILE then; the JSON of the copy last read of the file or
    // put ('' none): mel_tsr_put writes only another one.
    /** @var string */
    public static $sig = '';
    /** @var string */
    public static $sig_file = '';
    /** @var string */
    public static $json = '';
    // When the list was read last (microtime; 0 never in this php_server), by mel_tsr_sig $read_sig.
    /** @var float */
    public static $read_t = 0.0;
    /** @var string */
    public static $read_sig = '';
    // The movies of the torrents by the history (mel_tsr_mid): hash => movie_id, of MelWh::$sig $mid_sig (null: none).
    /** @var array<string, string> */
    public static $mid = array();
    /** @var string|null */
    public static $mid_sig = null;
    // r24: the copy each main screen showed the row of when built (mel_tsr_seen_sig) by its media_url: after a removal
    // one of another copy is built again (mel_tsr_unseen), as r24 does not build the main screens under the section.
    /** @var array<string, string> */
    public static $seen = array();
}

// The address and the login of TorrServer of the settings as one hash: a copy of another one is none. The password is
// not in the file, its hash only.
/**
 * @return string
 */
function mel_tsr_sig()
{
    return md5(Mel::$settings['ts'] . '|' . Mel::$settings['ts_auth']);
}

// The row is shown on tab $tab ('movie', 'series'): its slot among the slots of the tab (mel_cat_tab_slots by cat_rows;
// its place needs no row of the server) and not «Без торрентов» (mel_tsl_on).
/**
 * @param string $tab
 * @return bool
 */
function mel_tsr_on($tab)
{
    return mel_tsl_on() &&
        in_array('@torrserver', mel_cat_slot_names(mel_cat_tab_slots(array(), Mel::$settings['cat_rows'], $tab)), true);
}

// The row is shown on a tab ("TV" shows those of the tabs): else no read of the list for it, no copy.
/**
 * @return bool
 */
function mel_tsr_any()
{
    return mel_tsr_on('movie') || mel_tsr_on('series');
}

// The copy is older than $s s: no read of the list in this php_server, one of another TorrServer or login, or one $s s
// ago and more.
/**
 * @param float $s
 * @return bool
 */
function mel_tsr_old($s)
{
    return MelTsr::$read_sig !== mel_tsr_sig() || MelTsr::$read_t <= 0 || microtime(true) - MelTsr::$read_t >= $s;
}

// The row is shown on tab $tab and its copy is old (mel_tsr_old of MEL_TSR_FRESH_S).
/**
 * @param string $tab
 * @return bool
 */
function mel_tsr_due($tab)
{
    return mel_tsr_on($tab) && mel_tsr_old(MEL_TSR_FRESH_S);
}

// The step of the row of a screen of tab $tab with $left s of its time left: a tick of the main screen (r25,
// mel_pg_tick) or the end of an opening that went to the network (r24, mel_pg_screen_built). The copy old there
// (mel_tsr_due): the list read (mel_tsr_read, MEL_TSR_TICK_S) when $left holds it, else not now (still due: the next tick
// reads it, mel_pg_tick_why keeps the timer). -> the copy new (written): the screen to build again; the same, not
// written, no answer, 401, the guard of memory - not.
/**
 * @param string $tab
 * @param float $left
 * @return bool
 */
function mel_tsr_step($tab, $left)
{
    if (!mel_tsr_due($tab))
        return false;
    if ($left < MEL_TSR_TICK_S)
    {
        mel_log(sprintf('ts row: %s: %.1f s left, no read now', $tab, max(0.0, $left)));
        return false;
    }
    list(, $new) = mel_tsr_read();
    return $new;
}

// The copy of the TorrServer of the settings: hash => its tile in order; from memory, the file read again when it is
// another one (a restart of php_server: once); none (no file, a broken one, another TorrServer or login) - empty.
/**
 * @return array<string, MelTsrTile>
 */
function mel_tsr_tiles()
{
    $sig = mel_file_sig(MEL_TSR_FILE);
    if (MelTsr::$tiles === null || $sig !== MelTsr::$sig_file)
    {
        $text = $sig !== '' ? strval(file_get_contents(mel_data_dir() . '/' . MEL_TSR_FILE)) : '';
        $j = $text !== '' ? json_decode($text, true) : null;
        $out = array();
        $ok = is_array($j) && isset($j['v'], $j['sig']) && $j['v'] === 1 && is_string($j['sig']);
        foreach ($ok ? mel_arr($j, 'tiles') : array() as $x)
        {
            $tile = mel_tsr_tile_parse($x);
            if ($tile !== null && !isset($out[$tile['e']['hash']]) && count($out) < MEL_TSR_MAX)
                $out[$tile['e']['hash']] = $tile;
        }
        MelTsr::$tiles = $out;
        MelTsr::$sig = $ok ? strval($j['sig']) : '';
        MelTsr::$sig_file = $sig;
        MelTsr::$json = $text;
    }
    return MelTsr::$sig === mel_tsr_sig() ? MelTsr::$tiles : array();
}

// What a screen showing the row now shows of it (mel_tsr_seen): the copy as last read or put (MelTsr::$json; the file
// read when another one, mel_tsr_tiles) and its TorrServer, «Без торрентов».
/**
 * @return string
 */
function mel_tsr_seen_sig()
{
    $on = mel_tsl_on();
    if ($on)
        mel_tsr_tiles();
    return md5(($on ? '1' : '0') . mel_tsr_sig() . MelTsr::$json);
}

// r24: main screen $url is built with the row (mel_catv_main_view): what of it it shows kept (MelTsr::$seen).
/**
 * @param string $url
 * @return void
 */
function mel_tsr_seen($url)
{
    MelTsr::$seen[$url] = mel_tsr_seen_sig();
}

// r24: of main screens $urls those built with the row of another copy than now (mel_tsr_seen), in their order.
/**
 * @param list<string> $urls
 * @return list<string>
 */
function mel_tsr_unseen($urls)
{
    $now = mel_tsr_seen_sig();
    $out = array();
    foreach ($urls as $url)
    {
        if (isset(MelTsr::$seen[$url]) && MelTsr::$seen[$url] !== $now)
            $out[] = $url;
    }
    return $out;
}

// The tiles the row shows, the one test of a copy to use (its slot on a tab aside: the caller's): none with the row
// shown on no tab or «Без торрентов» (mel_tsr_any), else the copy (mel_tsr_tiles; none - no row). The row on a main
// screen, the row of a deep link and a tile of the copy (mel_tsr_tile) alike; the raw copy is for mel_tsr_put and
// mel_tsr_drop alone.
/**
 * @return array<string, MelTsrTile>
 */
function mel_tsr_shown_tiles()
{
    return mel_tsr_any() ? mel_tsr_tiles() : array();
}

// The tile of the copy of torrent $hash, null none (or the row not shown: mel_tsr_shown_tiles).
/**
 * @param string $hash
 * @return MelTsrTile|null
 */
function mel_tsr_tile($hash)
{
    $c = mel_tsr_shown_tiles();
    return isset($c[$hash]) ? $c[$hash] : null;
}

// An element of "tiles" of the file -> its tile, null: not one (a hash not of 40 hex, a field of a wrong type).
/**
 * @param mixed $x
 * @return MelTsrTile|null
 */
function mel_tsr_tile_parse($x)
{
    $e = mel_arr($x, 'e');
    $hash = mel_str($e, 'hash');
    if (!preg_match('/^[0-9a-f]{40}$/D', $hash) || !isset($x['nv'], $e['added']) || !is_int($x['nv']) ||
        !is_int($e['added']) || !array_key_exists('files', $e) || !array_key_exists('lampa', $e) ||
        ($e['lampa'] !== null && !is_array($e['lampa'])) || ($e['files'] !== null && !is_array($e['files'])))
        return null;
    $files = null;
    if ($e['files'] !== null)
    {
        $files = array();
        foreach ($e['files'] as $f)
        {
            $id = is_array($f) && isset($f['id']) && is_int($f['id']) ? $f['id'] : 0;
            $path = mel_str($f, 'path');
            if ($id >= 1 && $path !== '')
                $files[] = array('id' => $id, 'path' => $path, 'size' => mel_num(mel_str($f, 'size')));
        }
    }
    return array('e' => array('hash' => $hash, 'title' => mel_str($e, 'title'), 'raw_title' => mel_str($e, 'raw_title'),
        'src' => mel_str($e, 'src'), 'poster' => mel_str($e, 'poster'), 'category' => mel_str($e, 'category'),
        'added' => $e['added'], 'size' => mel_num(mel_str($e, 'size')), 'files' => $files, 'lampa' => $e['lampa']),
        'nv' => $x['nv'], 'fname' => mel_str($x, 'fname'));
}

// The tiles of the copy of entries $list (of the list, in its order): the first MEL_TSR_MAX; their files - those of the
// entry, else of a file_stats kept (mel_tsl_files_of), their count of video files and the name by them taken of all of
// them, those over MEL_TSR_FILES_MAX not kept.
/**
 * @param list<MelTsEntry> $list
 * @return array<string, MelTsrTile>
 */
function mel_tsr_tiles_of($list)
{
    $out = array();
    foreach (array_slice($list, 0, MEL_TSR_MAX) as $e)
    {
        $h = $e['hash'];
        $files = $e['files'] !== null ? $e['files'] : mel_tsl_files_of($h);
        $tile = mel_tsr_tile_of($e, $files);
        $tile['e']['files'] = $files !== null &&
            strlen(strval(json_encode(mel_tsr_files_out($files)))) <= MEL_TSR_FILES_MAX ? $files : null;
        $out[$h] = $tile;
    }
    return $out;
}

// Entry $e with its files $files (null: not known) as a tile of the copy: its video files (mel_tsl_video_count; -1 not
// known) and the name of the torrent by them (mel_tsl_name). The texts of the panel of the section and of the row take
// it (mel_tsl_texts), so the two never differ.
/**
 * @param MelTsEntry $e
 * @param list<MelTsFile>|null $files
 * @return MelTsrTile
 */
function mel_tsr_tile_of($e, $files)
{
    list($head) = mel_tsl_split($e['title']);
    return array('e' => $e, 'nv' => $files === null ? -1 : mel_tsl_video_count($files), 'fname' => mel_tsl_name($files, $head));
}

// Files $files as the copy writes them: the sizes as strings.
/**
 * @param list<MelTsFile> $files
 * @return list<array{id: int, path: string, size: string}>
 */
function mel_tsr_files_out($files)
{
    $out = array();
    foreach ($files as $f)
        $out[] = array('id' => $f['id'], 'path' => $f['path'], 'size' => sprintf('%.0f', $f['size']));
    return $out;
}

// The copy is tiles $tiles (mel_tsr_tiles_of) of the TorrServer of the settings: in memory, and the file written when
// they are other than the copy last read or put (MelTsr::$json; the flash wears: most reads find the same list) ->
// 'same', 'new' (written), 'failed' (other, not written: a line of the log, memory as said; the same tiles next time
// are 'same', no write tried at every read). Not 'same' - the one place of the flag of the tab "TV", as a copy of the
// catalog (mel_tv_copy_saved): a read of its timer counted (MelTv::$quiet: it compares the files itself), else with the
// row shown the flag (mel_tv_flag); so a removal from the row, the section and "TV" alike, a tick, a read of the section.
/**
 * @param array<string, MelTsrTile> $tiles
 * @return string
 */
function mel_tsr_put($tiles)
{
    $out = array();
    foreach ($tiles as $tile)
    {
        $e = $tile['e'];
        $e['size'] = sprintf('%.0f', $e['size']);
        $e['files'] = $e['files'] === null ? null : mel_tsr_files_out($e['files']);
        $out[] = array('e' => $e, 'nv' => $tile['nv'], 'fname' => $tile['fname']);
    }
    $json = strval(json_encode(array('v' => 1, 'sig' => mel_tsr_sig(), 'tiles' => $out)));
    // The file changed by another one (removed): MelTsr::$json of it.
    mel_tsr_tiles();
    if ($json === MelTsr::$json)
        return 'same';
    $path = mel_data_dir() . '/' . MEL_TSR_FILE;
    $ok = mel_data_dir() !== '' && mel_write_file($path, $json);
    if (!$ok)
        mel_log('ts row: the copy not written');
    MelTsr::$tiles = $tiles;
    MelTsr::$sig = mel_tsr_sig();
    MelTsr::$sig_file = mel_file_sig(MEL_TSR_FILE);
    MelTsr::$json = $json;
    if (MelTv::$quiet)
        MelTv::$new_copies++;
    else if (mel_tsr_any())
        mel_tv_flag('the TorrServer copy');
    return $ok ? 'new' : 'failed';
}

// The copy by read $r of the list (mel_tsl_fetch: the entries or null, the key of the error, its detail): the entries
// read (none: an empty list); no answer (ts_err_down) - the copy stays (the row of it, owner 26.11.2026), and so when the
// guard of memory stopped the read short of MEL_TSR_MAX entries (detail 'memory': a big list of the section in memory,
// not the list of TorrServer); 401, a bad reply - empty (no row). The time of the read kept (mel_tsr_due). The row not
// shown (mel_tsr_any): nothing. -> the copy written anew (mel_tsr_put 'new'; not written - the screen stays).
/**
 * @param array{list<MelTsEntry>|null, string, string} $r
 * @return bool
 */
function mel_tsr_take($r)
{
    if (!mel_tsr_any())
        return false;
    MelTsr::$read_t = microtime(true);
    MelTsr::$read_sig = mel_tsr_sig();
    if ($r[1] === 'ts_err_down')
    {
        mel_log('ts row: no answer, the copy kept');
        return false;
    }
    if ($r[2] === 'memory' && ($r[0] === null || count($r[0]) < MEL_TSR_MAX))
    {
        mel_log(sprintf('ts row: stopped at memory, %d entries, the copy kept', $r[0] === null ? 0 : count($r[0])));
        return false;
    }
    $tiles = mel_tsr_tiles_of($r[0] !== null ? $r[0] : array());
    $put = mel_tsr_put($tiles);
    mel_log(sprintf('ts row: the copy %d tiles%s, %s', count($tiles), $r[1] !== '' ? " ({$r[1]})" : '',
        $put === 'same' ? 'the same' : $put));
    return $put === 'new';
}

// The read of the row: "list" of the TorrServer of the settings up to MEL_TSR_MAX entries (the read stops there: the
// newest come first), MEL_TSR_TICK_S in all, to the copy (mel_tsr_take) -> array(the key of an error, '' none; the
// copy written anew). The row not shown (mel_tsr_any), «Без торрентов» too: no request ('ts_err_off').
/**
 * @return array{string, bool}
 */
function mel_tsr_read()
{
    if (!mel_tsr_any())
        return array('ts_err_off', false);
    $r = mel_tsl_fetch('ts row', MEL_TSR_MAX, MEL_TSR_TICK_S);
    return array($r[1], mel_tsr_take($r));
}

// Torrent $hash removed from TorrServer (mel_tsl_rem) or found gone (mel_tsl_enter, mel_tsl_rem_ask): out of the copy, the row hidden as well (a copy of it on
// the disk shown again when the row is), no read of the list. -> it was in the copy (a tile of it gone: the screens
// of the row to build anew, mel_tsl_anew).
/**
 * @param string $hash
 * @return bool
 */
function mel_tsr_drop($hash)
{
    $tiles = mel_tsr_tiles();
    if (!isset($tiles[$hash]))
        return false;
    unset($tiles[$hash]);
    mel_tsr_put($tiles);
    mel_log("ts row: $hash out of the copy");
    return true;
}

// The movies of the Dune (movie_id) of the torrents by the history of Melange: hash => movie_id, by the uid of a record
// of a release of it "aio:<movie_id>:<s>:<e>:<hash>" (mel_wh_ours_all, the latest first: of several movies the latest).
// A start from the section ("aiots:") gives none. No request; the map is made anew when the history changes
// (MelWh::$sig; none known - at every call: a row takes it once).
/**
 * @return array<string, string>
 */
function mel_tsr_mids()
{
    $all = mel_wh_ours_all();
    if (MelWh::$sig === null || MelWh::$sig !== MelTsr::$mid_sig)
    {
        $map = array();
        foreach ($all as $uid => $share)
        {
            if (preg_match('/^' . MEL_SUP_ID . ':(' . MEL_DUNE_ID . '):-?[0-9]{1,5}:-?[0-9]{1,5}:([0-9a-f]{40})\z/',
                strval($uid), $m) && !isset($map[$m[2]]))
                $map[$m[2]] = $m[1];
        }
        MelTsr::$mid = $map;
        MelTsr::$mid_sig = MelWh::$sig;
    }
    return MelTsr::$mid;
}

// The movie of the Dune of torrent $hash by the history (mel_tsr_mids), '' none.
/**
 * @param string $hash
 * @return string
 */
function mel_tsr_mid($hash)
{
    $mids = mel_tsr_mids();
    return isset($mids[$hash]) ? $mids[$hash] : '';
}
