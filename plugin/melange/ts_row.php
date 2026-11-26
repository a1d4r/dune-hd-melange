<?php
// The row "На TorrServer" of the catalog, its data: a copy of the newest entries of the list of TorrServer in data_dir
// (MEL_TSR_FILE; the row is drawn of it at once, a read of the list refreshes it), only while the row is shown on a tab
// and not «Без торрентов» (mel_tsr_any): else no read, no write; the movie of a torrent by the history of Melange (its
// hash in the uid of a record "aio:"). No firmware API. Reads Mel::$settings, MelTsl, the history (history.php); writes
// MelTsr and the copy. Functions are global; the require order is in main.php.

// The copy of the row in data_dir: {"v": 1, "sig": mel_tsr_sig, "entries": [{"e": the entry (mel_tsl_entry; its sizes as
// strings: json_decode of the 32-bit Dune clamps an integer over 2 GB), "nv": its video files (-1 not known), "fname":
// the name of the torrent by its files (mel_tsl_name)}, ...]}, the newest first. No time of its read in it: a restart of
// php_server reads the list anew at once (MelTsr::$read_t).
define('MEL_TSR_FILE', 'ts_row.json');
// The entries of the row at most: its tiles, as "Продолжить" and "Избранное".
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
    // The copy as read or written: hash => its row in the order of the row; null: not read yet.
    /** @var array<string, MelTsrRow>|null */
    public static $entries = null;
    // mel_tsr_sig of $entries ('' none); mel_file_sig of MEL_TSR_FILE then.
    /** @var string */
    public static $sig = '';
    /** @var string */
    public static $sig_file = '';
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

// The row is shown on tab $tab and its copy is old: no read of the list in this php_server, of another TorrServer or
// login, or MEL_TSR_FRESH_S ago and more.
/**
 * @param string $tab
 * @return bool
 */
function mel_tsr_due($tab)
{
    return mel_tsr_on($tab) && (MelTsr::$read_sig !== mel_tsr_sig() || MelTsr::$read_t <= 0 ||
        microtime(true) - MelTsr::$read_t >= MEL_TSR_FRESH_S);
}

// The copy of the TorrServer of the settings: hash => its row in order; from memory, the file read again when it is
// another one (a restart of php_server: once); none (no file, a broken one, another TorrServer or login) - empty.
/**
 * @return array<string, MelTsrRow>
 */
function mel_tsr_entries()
{
    $sig = mel_file_sig(MEL_TSR_FILE);
    if (MelTsr::$entries === null || $sig !== MelTsr::$sig_file)
    {
        $j = $sig !== '' ? json_decode(strval(file_get_contents(mel_data_dir() . '/' . MEL_TSR_FILE)), true) : null;
        $out = array();
        $ok = is_array($j) && isset($j['v'], $j['sig']) && $j['v'] === 1 && is_string($j['sig']);
        foreach ($ok ? mel_arr($j, 'entries') : array() as $x)
        {
            $row = mel_tsr_row_parse($x);
            if ($row !== null && !isset($out[$row['e']['hash']]) && count($out) < MEL_TSR_MAX)
                $out[$row['e']['hash']] = $row;
        }
        MelTsr::$entries = $out;
        MelTsr::$sig = $ok ? strval($j['sig']) : '';
        MelTsr::$sig_file = $sig;
    }
    return MelTsr::$sig === mel_tsr_sig() ? MelTsr::$entries : array();
}

// The row of the copy of torrent $hash, null none (or the row not shown: mel_tsr_any).
/**
 * @param string $hash
 * @return MelTsrRow|null
 */
function mel_tsr_entry($hash)
{
    if (!mel_tsr_any())
        return null;
    $c = mel_tsr_entries();
    return isset($c[$hash]) ? $c[$hash] : null;
}

// An element of "entries" of the file -> its row, null: not one (a hash not of 40 hex, a field of a wrong type).
/**
 * @param mixed $x
 * @return MelTsrRow|null
 */
function mel_tsr_row_parse($x)
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

// The rows of the copy of entries $list (of the list, in its order): the first MEL_TSR_MAX; their files - those of the
// entry, else of a file_stats kept (MelTsl::$files), their count of video files and the name by them taken of all of
// them, those over MEL_TSR_FILES_MAX not kept.
/**
 * @param list<MelTsEntry> $list
 * @return array<string, MelTsrRow>
 */
function mel_tsr_rows($list)
{
    $out = array();
    foreach (array_slice($list, 0, MEL_TSR_MAX) as $e)
    {
        $h = $e['hash'];
        $files = $e['files'] !== null ? $e['files'] : (isset(MelTsl::$files[$h]) ? MelTsl::$files[$h] : null);
        list($head) = mel_tsl_split($e['title']);
        $e['files'] = $files !== null && strlen(strval(json_encode(mel_tsr_files_out($files)))) <= MEL_TSR_FILES_MAX ?
            $files : null;
        $out[$h] = array('e' => $e, 'nv' => $files === null ? -1 : mel_tsl_video_count($files),
            'fname' => mel_tsl_name($files, $head));
    }
    return $out;
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

// The copy is rows $rows (mel_tsr_rows) of the TorrServer of the settings: in memory, and the file written unless it
// has them already (the flash wears: most reads find the same list) -> they are other than the file had (the row to
// draw anew). Not written: a line of the log, memory as said.
/**
 * @param array<string, MelTsrRow> $rows
 * @return bool
 */
function mel_tsr_put($rows)
{
    $out = array();
    foreach ($rows as $r)
    {
        $e = $r['e'];
        $e['size'] = sprintf('%.0f', $e['size']);
        $e['files'] = $e['files'] === null ? null : mel_tsr_files_out($e['files']);
        $out[] = array('e' => $e, 'nv' => $r['nv'], 'fname' => $r['fname']);
    }
    $json = json_encode(array('v' => 1, 'sig' => mel_tsr_sig(), 'entries' => $out));
    $path = mel_data_dir() . '/' . MEL_TSR_FILE;
    $old = mel_file_sig(MEL_TSR_FILE) !== '' ? strval(file_get_contents($path)) : '';
    $new = is_string($json) && $json !== $old;
    if ($new && (mel_data_dir() === '' || !mel_write_file($path, $json)))
        mel_log('ts row: the copy not written');
    MelTsr::$entries = $rows;
    MelTsr::$sig = mel_tsr_sig();
    MelTsr::$sig_file = mel_file_sig(MEL_TSR_FILE);
    return $new;
}

// The copy by read $r of the list (mel_tsl_fetch: the entries or null, the key of the error): the entries read (none:
// an empty list); no answer (ts_err_down) - the copy stays (the row of it, owner 26.11.2026); 401, a bad reply - empty
// (no row). The time of the read kept (mel_tsr_due). The row not shown (mel_tsr_any): nothing. -> the copy changed.
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
    $rows = mel_tsr_rows($r[0] !== null ? $r[0] : array());
    $new = mel_tsr_put($rows);
    mel_log(sprintf('ts row: the copy %d entries%s, %s', count($rows), $r[1] !== '' ? " ({$r[1]})" : '',
        $new ? 'new' : 'the same'));
    return $new;
}

// The read of the row: "list" of the TorrServer of the settings up to MEL_TSR_MAX entries (the read stops there: the
// newest come first), MEL_TSR_TICK_S in all, to the copy (mel_tsr_take) -> array(the key of an error, '' none; the
// copy changed). The row not shown (mel_tsr_any), «Без торрентов» too: no request ('ts_err_off').
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

// Torrent $hash removed from TorrServer (mel_tsl_rem): out of the copy too, no read of the list.
/**
 * @param string $hash
 * @return void
 */
function mel_tsr_drop($hash)
{
    if (mel_tsr_entry($hash) === null)
        return;
    $rows = mel_tsr_entries();
    unset($rows[$hash]);
    mel_tsr_put($rows);
    mel_log("ts row: $hash out of the copy");
}

// The movie of the Dune (movie_id) of torrent $hash by the history of Melange: the uid of a record of a release of it
// "aio:<movie_id>:<s>:<e>:<hash>" (mel_wh_ours_all, the latest first: of several movies the latest), '' none. A start
// from the section ("aiots:") gives none. No request; the map is made anew when the history changes (MelWh::$sig).
/**
 * @param string $hash
 * @return string
 */
function mel_tsr_mid($hash)
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
    return isset(MelTsr::$mid[$hash]) ? MelTsr::$mid[$hash] : '';
}
