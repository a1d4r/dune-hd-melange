<?php
// The media library of the Dune ("Фильмы" of the shell), read only: its
// folders (movie_index_config.xml), the network folders of their sources
// (network_folders.properties), the shares the shell has mounted
// (network_mount_list.xml), the storages of the box - USB, the disk of the bay
// (storage_list.xml) - and the index of the files of each folder
// (folder_files.<id>.json), as shell_ext/movies of r25 reads them
// (data_model.php, nf_common.php, gui_common.php). The copies of a movie (of an
// episode) are its files whose share is mounted now. Only mel_lib_here touches a
// share: a test -f of one file (and the read of its first 64 KB) in the
// background, from the operation of a start only.
// No login or password of a share is kept, returned or logged; the log has no
// path, server, share, folder caption or file name: folders by id, files by
// their key lib-... (mel_lib_key).
// Needs main.php (mel_log, mel_num, mel_str), links.php (mel_pg_links, mel_pg_rev, mel_pg_shell_pair, mel_pg_link_put,
// mel_pg_links_save, mel_pg_tile_cards), torrserver.php (mel_ts_dirname, mel_ts_ep, mel_ts_ep_lead), segments.php (mel_mkv_duration). The
// window of the copies: view_card_libwin.php (their paths mel_lib_tails); their start: mel_lib_start (playback_lib.php).

// The files of the shell. PHP on the Dune adds FS_PREFIX to /config,
// /flashdata and /tmp itself; a failed plain read is tried with FS_PREFIX.
// Tests define MEL_LIB_ROOT, the root of these paths, first.
if (!defined('MEL_LIB_ROOT'))
    define('MEL_LIB_ROOT', '');
define('MEL_LIB_CONFIG', '/config/movie_index_config.xml');
define('MEL_LIB_NF', '/config/network_folders.properties');
define('MEL_LIB_MOUNTS', '/tmp/run/network_mount_list.xml');
// The storages (USB, the disk of the bay) the storage service of the shell lists (StorageHelper::STORAGE_LIST_FILE), and
// the dir of a link /tmp/mnt/storage/<name> of each one mounted (update_local_folders_file.sh of the shell).
define('MEL_LIB_STORAGES', '/tmp/run/storage_list.xml');
define('MEL_LIB_STORAGE_LINKS', '/tmp/mnt/storage');
// The source of the internal memory and its root (GuiCommon::get_mount_path_by_source_id).
define('MEL_LIB_SDCARD_SID', 'sdcard://');
define('MEL_LIB_SDCARD', '/sdcard');
// A bigger file is not read: not a config of this box, or an index json_decode can not take in the memory of
// php_server (memory_limit 128M; an index of 16 MB is a Fatal there).
if (!defined('MEL_LIB_MAX_BYTES'))
    define('MEL_LIB_MAX_BYTES', 4194304);
// The bytes of the indexes of all the folders read at once (of folder_files, apart of folder_movies): a folder past
// them is skipped.
if (!defined('MEL_LIB_BUDGET_BYTES'))
    define('MEL_LIB_BUDGET_BYTES', 8388608);
// Cards of the shell read for a title not found by its ids (mel_lib_by_movies).
define('MEL_LIB_CANDIDATES', 3);
// The index looked at once in this many s for the badges "on the disk" of the tiles (mel_lib_tile_start): once a screen.
define('MEL_LIB_TILE_S', 1.0);
// Seconds mel_lib_here waits for its check of a file: a NAS asleep spins its disks up (8 s were short on the Dune, a disk
// of a NAS asleep 15 min: the check woke it, the next one answered in 65 ms; owner 21.11.2026: 25 s); a share still
// "mounted" of a NAS off answers only by the timeout of cifs (the check is in the background: php_server does not hang).
if (!defined('MEL_LIB_HERE_S'))
    define('MEL_LIB_HERE_S', 25);
// A step of the check before its test -f: tests define one first (a check that does not answer in time).
if (!defined('MEL_LIB_HERE_TEST'))
    define('MEL_LIB_HERE_TEST', '');
// Milliseconds mel_lib_here waits for the first bytes of a file there past its answer (the length of an MKV): in the
// budget of the operation; past them the length is unknown.
if (!defined('MEL_LIB_MKV_MS'))
    define('MEL_LIB_MKV_MS', 1000);
// The script of the check (sh -c, POSIX: bash, dash, mksh, busybox ash): 1 or 0 for a file at $MEL_P to $MEL_R, renamed
// there whole; of no file with $MEL_M set (the root of the mount of a storage), two more digits: test -d and test -r of
// it (builtins: no process more); with $MEL_B set, of a file there then its first 64 KB to $MEL_R.b (dd: head -c is not
// POSIX), renamed there whole too, an empty $MEL_R.b for a read failed (dd or mv); the paths only in the environment.
// Its lifetime bound inside, with no timeout(1) (that of busybox before 1.30 of old boxes takes "-t N", toybox "-s KILL
// N"; which one PATH finds is not known): a watcher in the background kills the script (kill -9 at MEL_LIB_HERE_S + 2
// s, by steps of sleep 1) - not a dd it waits for, a process of its own: that one an orphan then, done by itself, its
// $MEL_R.b.p removed by the next check. The script done, it kills the watcher (no kill of a pid reused later); the
// sleep 1 of the watcher of that moment is left, 1 s at most (a kill of a sleep of its own would miss the one forked
// but not started yet).
define('MEL_LIB_HERE_SH',
    '( for i in ' . implode(' ', range(1, MEL_LIB_HERE_S + 2)) . '; do sleep 1; done; kill -9 $$ ) & ' .
    'w=$!; ' . MEL_LIB_HERE_TEST . 'if test -f "$MEL_P"; then r=1; else r=0; if test -n "$MEL_M"; then ' .
    'if test -d "$MEL_M"; then r=${r}1; else r=${r}0; fi; ' .
    'if test -r "$MEL_M"; then r=${r}1; else r=${r}0; fi; fi; fi; ' .
    'echo $r >"$MEL_R.p" && mv -f "$MEL_R.p" "$MEL_R"; ' .
    'if test $r = 1 && test -n "$MEL_B"; then ' .
    '{ dd if="$MEL_P" of="$MEL_R.b.p" bs=4096 count=16 2>/dev/null && mv -f "$MEL_R.b.p" "$MEL_R.b"; } || ' .
    ': >"$MEL_R.b"; fi; kill $w');

// The library in the memory of php_server, until its files change (mel_lib_index).
class MelLib
{
    // stat of the configs and of the indexes of the folders last read; null: never read.
    /** @var string|null */
    public static $sig = null;
    /** @var array<int|string, MelLibFolder> */
    public static $folders = array();
    /** @var array<string, MelLibNf> */
    public static $nfs = array();
    // Movie files only (ass 0, s < 0, e < 0; of a title of bonus files only those, ass 2) by their movie_id.
    /** @var array<string, list<MelLibFile>> */
    public static $by_mid = array();
    // Episode files (ass 0, s >= 1, e >= 1) by their movie_id, their season and episode after the rules of the numbers
    // (mel_lib_eps_fix); a movie_id with none of them taken is not here. Only the fields of a file of a copy not got
    // from the others (mel_lib_ep_file): a library may have tens of thousands of them, all in the memory of php_server.
    /** @var array<string, list<MelLibEpRec>> */
    public static $eps = array();
    // The hashes of the episode files mel_lib_eps_fix dropped, by their movie_id.
    /** @var array<string, list<string>> */
    public static $eps_drop = array();
    // Files of the shell read so far (tests count the reads by it).
    /** @var int */
    public static $reads = 0;
    // The titles of the folders (folder_movies, mel_lib_movies): movie_id, the normalized names, the first and the
    // last year, the type (movie, series); null: not read. Read lazily, again when the stat of their files changes
    // ($movies_sig).
    /** @var list<array{string, list<string>, int, int, string}>|null */
    public static $movies = null;
    /** @var string */
    public static $movies_sig = '';
    // What mel_lib_by_movies found for a title (movie_id or ''), by all it goes by (mel_lib_movies_key); emptied whole
    // when the index or folder_movies is read again.
    /** @var array<string, string> */
    public static $found = array();
    // The titles with a file in a folder of a source Melange can play (mel_lib_source_known, by the config; mounted,
    // connected or not - mel_lib_source_mount): of the badge "on the disk" (mel_lib_tile_has) and of the queue of the
    // pairs (mel_pg_lib_queue). movie_id => its type: movie (movie files), series (episodes), '' (both).
    /** @var array<string, string> */
    public static $play = array();
    // When mel_lib_tile_start looked at the index last (microtime), 0: never.
    /** @var float */
    public static $tile_t = 0.0;
    // stat of storage_list.xml when the line of the local folders (mel_lib_local_line) was made last, and that line:
    // not in the signature of the index (the index does not depend on it), its line logged again when it changes.
    /** @var string|null */
    public static $sl = null;
    /** @var string */
    public static $local = '';
}

// --- Reads.

// Error handler of the reads: a PHP warning (it names the path) is not logged.
/**
 * @return bool
 */
function mel_lib_err_quiet()
{
    return true;
}

// The real paths of a shell path: plain (under MEL_LIB_ROOT), then with FS_PREFIX in front.
/**
 * @param string $p
 * @return list<string>
 */
function mel_lib_paths($p)
{
    $fp = getenv('FS_PREFIX');
    $res = array(MEL_LIB_ROOT . $p);
    if (is_string($fp) && $fp !== '')
        $res[] = $fp . $p;
    return $res;
}

// A shell file -> array(content or false, why not: 'no such file', 'too big',
// 'over budget' (bigger than $left bytes; -1: no budget), 'read failed'). Its
// size is known before the read. No PHP warning in the log, no path in the
// reason.
/**
 * @param string $p
 * @param int $left
 * @return array{string|false, string}
 */
function mel_lib_read($p, $left = -1)
{
    $why = 'no such file';
    foreach (mel_lib_paths($p) as $f)
    {
        if (!is_file($f))
            continue;
        $size = filesize($f);
        if ($size === false || $size > MEL_LIB_MAX_BYTES)
        {
            $why = 'too big';
            continue;
        }
        if ($left >= 0 && $size > $left)
        {
            $why = 'over budget';
            continue;
        }
        MelLib::$reads++;
        set_error_handler('mel_lib_err_quiet');
        $s = file_get_contents($f);
        restore_error_handler();
        if (is_string($s))
            return array($s, '');
        $why = 'read failed';
    }
    return array(false, $why);
}

// 'ino:mtime:size' of a shell file (the first real path there), '' for none.
/**
 * @param string $p
 * @return string
 */
function mel_lib_stat($p)
{
    foreach (mel_lib_paths($p) as $f)
    {
        $s = is_file($f) ? stat($f) : false;
        if ($s !== false)
            return $s['ino'] . ':' . $s['mtime'] . ':' . $s['size'];
    }
    return '';
}

// --- Configs.

// The attributes $keys of every element <$name> of an XML file of the shell ->
// list of name => value, only these (none for an empty $keys: the login and
// the password of a mount are never taken); false: not XML. SimpleXML, as the
// shell reads these files (data_model.php r25): the entities decoded, a '>'
// in a value is a part of it. No DTD: no entities of its own, nothing from
// the network.
/**
 * @param string $doc
 * @param string $name
 * @param list<string> $keys
 * @return list<array<string, string>>|false
 */
function mel_lib_xml_elements($doc, $name, $keys)
{
    $xml = mel_lib_xml_load($doc);
    if ($xml === false)
        return false;
    $els = $xml->xpath('//' . $name);
    $res = array();
    foreach (is_array($els) ? $els : array() as $el)
    {
        $a = array();
        foreach ($keys as $k)
        {
            if (isset($el[$k]))
                $a[$k] = strval($el[$k]);
        }
        $res[] = $a;
    }
    return $res;
}

// An XML file of the shell -> its root (SimpleXML), false: not XML or a DTD. The files of /tmp/run are writable by any
// process (-rw-rw-rw-), and a DTD in another encoding (UTF-16) passes the check of '<!DOCTYPE': so no external entity
// is loaded while it is parsed (no file, nothing from the network); the loader is set back after it
// (simplexml_load_file and DOMDocument::load elsewhere in php_server need it). No warning in the log.
/**
 * @param string $doc
 * @return SimpleXMLElement|false
 */
function mel_lib_xml_load($doc)
{
    if (stripos($doc, '<!DOCTYPE') !== false)
        return false;
    $quiet = libxml_use_internal_errors(true);
    $loader = libxml_disable_entity_loader(true);
    $xml = simplexml_load_string($doc);
    libxml_disable_entity_loader($loader);
    libxml_clear_errors();
    libxml_use_internal_errors($quiet);
    return $xml;
}

/**
 * @param array<string, string> $a
 * @param string $k
 * @return string
 */
function mel_lib_attr($a, $k)
{
    return isset($a[$k]) ? $a[$k] : '';
}

// movie_index_config.xml -> folder id => MelLibFolder; $err: why there are none.
/**
 * @param string $err
 * @return array<int|string, MelLibFolder>
 */
function mel_lib_folders(&$err)
{
    $err = '';
    list($doc, $why) = mel_lib_read(MEL_LIB_CONFIG);
    if ($doc === false)
    {
        $err = $why;
        return array();
    }
    if (strpos($doc, '<folders') === false)
    {
        $err = 'not the movie index config';
        return array();
    }
    $list = mel_lib_xml_elements($doc, 'folder', array('id', 'source_id', 'path', 'caption'));
    if ($list === false)
    {
        $err = 'not XML';
        return array();
    }
    $res = array();
    foreach ($list as $f)
    {
        $id = mel_lib_attr($f, 'id');
        if (!preg_match('/^[0-9]+$/D', $id))
            continue;
        $res[$id] = array(
            'id' => $id,
            'source_id' => mel_lib_attr($f, 'source_id'),
            'path' => mel_lib_attr($f, 'path'),
            'caption' => mel_lib_attr($f, 'caption'));
    }
    return $res;
}

// SMB names as the shell compares them: the server and the share in lower
// case, the rest of the dir as is (DataModel::smb_dir_to_lower).
/**
 * @param string $dir
 * @return string
 */
function mel_lib_smb_dir_lower($dir)
{
    $p = strpos($dir, '/');
    if (!$p)
        return mb_strtolower($dir, 'UTF-8');
    return mb_strtolower(substr($dir, 0, $p), 'UTF-8') . substr($dir, $p);
}

// network_folders.properties (do_reload_network_folders of the shell) -> nf
// id => MelLibNf. Only the keys id, name, type, server and directory are
// kept: the login and the password of a folder never get into an array.
/**
 * @param string $err
 * @return array<string, MelLibNf>
 */
function mel_lib_network_folders(&$err)
{
    $err = '';
    list($doc, $why) = mel_lib_read(MEL_LIB_NF);
    if ($doc === false)
    {
        $err = $why;
        return array();
    }
    $p = array();
    foreach (explode("\n", $doc) as $line)
    {
        if (preg_match('/^\s*((?:id|name|type|server|directory)\.[0-9]+) *= *(.*)$/', $line, $m))
            $p[$m[1]] = trim($m[2]);
    }
    $res = array();
    for ($n = 0; isset($p["id.$n"]); $n++)
    {
        $id = $p["id.$n"];
        $name = isset($p["name.$n"]) ? $p["name.$n"] : '';
        $type = isset($p["type.$n"]) ? intval($p["type.$n"]) : 0;
        $server = isset($p["server.$n"]) ? $p["server.$n"] : '';
        $dir = isset($p["directory.$n"]) ? $p["directory.$n"] : '';
        if ($id === '' || $name === '' || $server === '' || $dir === '' || ($type !== 0 && $type !== 1))
            continue;
        $type = $type === 0 ? 'smb' : 'nfs';
        if ($type === 'smb')
        {
            $server = strtolower($server);
            $dir = mel_lib_smb_dir_lower($dir);
        }
        $res[$id] = array('id' => $id, 'name' => $name, 'type' => $type, 'server' => $server, 'dir' => $dir);
    }
    return $res;
}

// network_mount_list.xml (reload_network_mounts of the shell) -> list of
// MelLibMount, the mounted and mounting ones, as the shell. The login and the
// password of a mount are not read.
/**
 * @param string $err
 * @return list<MelLibMount>
 */
function mel_lib_mounts(&$err)
{
    $err = '';
    list($doc, $why) = mel_lib_read(MEL_LIB_MOUNTS);
    if ($doc === false)
    {
        $err = $why;
        return array();
    }
    if (trim($doc) === '')
        return array();
    if (strpos($doc, '<network_mounts') === false)
    {
        $err = 'not the mount list';
        return array();
    }
    $list = mel_lib_xml_elements($doc, 'mount', array('path', 'type', 'server', 'server_ipaddr', 'dir', 'status'));
    if ($list === false)
    {
        $err = 'not XML';
        return array();
    }
    $res = array();
    foreach ($list as $m)
    {
        $type = mel_lib_attr($m, 'type');
        $status = mel_lib_attr($m, 'status');
        if (($type !== 'smb' && $type !== 'nfs') || ($status !== 'mounted' && $status !== 'mounting'))
            continue;
        $server = mel_lib_attr($m, 'server');
        $dir = mel_lib_attr($m, 'dir');
        if ($type === 'smb')
        {
            $server = strtolower($server);
            $dir = mel_lib_smb_dir_lower($dir);
        }
        $res[] = array(
            'path' => mel_lib_attr($m, 'path'),
            'type' => $type,
            'server' => $server,
            'server_ipaddr' => mel_lib_attr($m, 'server_ipaddr'),
            'dir' => $dir,
            'status' => $status);
    }
    return $res;
}

// The mount of a network folder (DataModel::get_network_mount_by_server_and_subdir, data_model.php r25): mounted, the
// same type, an SMB mount of a share (no '/' in its dir), the server by name or by server_ipaddr, the dir the same or a
// parent of it -> array(the path of the folder in it, the server of the mount: server_ipaddr, else its name in lower
// case - one NAS of several shares, mel_lib_start), array('', '') when its share is not mounted.
/**
 * @param MelLibNf $nf
 * @param list<MelLibMount> $mounts
 * @return array{string, string}
 */
function mel_lib_mount_for_nf($nf, $mounts)
{
    foreach ($mounts as $mnt)
    {
        if ($mnt['status'] !== 'mounted' || $mnt['type'] !== $nf['type'])
            continue;
        if ($nf['type'] === 'smb' && strpos($mnt['dir'], '/'))
            continue;
        if ($mnt['server'] !== $nf['server'] && $mnt['server_ipaddr'] !== $nf['server'])
            continue;
        $srv = $mnt['server_ipaddr'] !== '' ? $mnt['server_ipaddr'] : strtolower($mnt['server']);
        if ($mnt['dir'] === $nf['dir'])
            return array($mnt['path'], $srv);
        $len = strlen($mnt['dir']);
        if (strncmp($nf['dir'], $mnt['dir'] . '/', $len + 1) === 0)
            return array($mnt['path'] . substr($nf['dir'], $len), $srv);
    }
    return array('', '');
}

// storage_list.xml (written by the storage service of the shell; the fields of a storage its child elements, a field of
// no value left out) -> name => MelLibStorage of the storages the shell takes (DataModel::do_reload_storage_list,
// data_model.php r25): /*/storages/storage with a name, not is_persistfs_capable, the first of a name. Its path: '' of
// one not connected (mount_path not from '/', GuiCommon::source_is_connected), else secondary_mount_path when it is
// from '/' (/tmp/mnt/storage/<name>, bound to the name: mount_path of the main storage is /tmp/mnt/D, which may move to
// another one), else mount_path; with FS_PREFIX in front, as the shell (mel_lib_fs). An empty file: none. $err: why
// none ('' for an empty file); $diag, for the log, with no name, label, uuid or path: the count of the storages taken
// and the kind of each (" off": not connected); of none taken, the size of the file and the names of its tags and
// attributes down to the fields of a storage.
/**
 * @param string $err
 * @param string $diag
 * @return array<string, MelLibStorage>
 */
function mel_lib_storages(&$err, &$diag)
{
    $err = '';
    $diag = '';
    list($doc, $why) = mel_lib_read(MEL_LIB_STORAGES);
    if ($doc === false)
    {
        $err = $why;
        return array();
    }
    $xml = trim($doc) === '' ? null : mel_lib_xml_load($doc);
    if ($xml === false)
        $err = 'not XML';
    $els = $xml ? $xml->xpath('/*/storages/storage') : array();
    $res = array();
    $kinds = array();
    foreach (is_array($els) ? $els : array() as $el)
    {
        if (intval(strval($el->is_persistfs_capable)) === 1 || !isset($el->name))
            continue;
        $name = strval($el->name);
        if (isset($res[$name]))
            continue;
        $mp = strval($el->mount_path);
        $sp = strval($el->secondary_mount_path);
        $kind = strval(substr(strval(preg_replace('/[^a-z0-9_]/', '', strtolower(strval($el->kind)))), 0, 24));
        $kind = $kind === '' ? '?' : $kind;
        $path = substr($mp, 0, 1) !== '/' ? '' : mel_lib_fs(substr($sp, 0, 1) === '/' ? $sp : $mp);
        $res[$name] = array('path' => $path, 'kind' => $kind);
        $kinds[] = $path === '' ? "$kind off" : $kind;
    }
    $diag = count($res) . ($kinds ? ' (' . implode(', ', $kinds) . ')' : '');
    if (!$res)
    {
        $names = array();
        if ($xml)
            mel_lib_xml_names($xml, 3, $names);
        $diag .= ' (' . strlen($doc) . ' bytes' . ($names ? ', tags ' . implode(' ', $names) : '') . ')';
    }
    return $res;
}

// A path from '/' of the shell as the shell takes a mount path of a storage (HD::get_fs_prefix() . $mount_path,
// DataModel::do_reload_storage_list): FS_PREFIX in front (AOSP 11: $FS_PREFIX/tmp is /tmp; ATV has no /tmp of its
// own); as is with no FS_PREFIX (older Android).
/**
 * @param string $p
 * @return string
 */
function mel_lib_fs($p)
{
    $fp = getenv('FS_PREFIX');
    return is_string($fp) ? $fp . $p : $p;
}

// The names of the element $el, of its attributes (@name) and of those of its children down to $depth levels, each
// once, in their order, up to 40 -> $names. Names only, never a value.
/**
 * @param SimpleXMLElement $el
 * @param int $depth
 * @param list<string> $names
 * @return void
 */
function mel_lib_xml_names($el, $depth, &$names)
{
    $all = array($el->getName());
    foreach ($el->attributes() as $k => $v)
        $all[] = "@$k";
    foreach ($all as $n)
    {
        $n = strval(substr(strval(preg_replace('/[^A-Za-z0-9_.:@-]/', '', $n)), 0, 32));
        if ($n !== '' && count($names) < 40 && !in_array($n, $names, true))
            $names[] = $n;
    }
    if ($depth > 0)
    {
        foreach ($el->children() as $c)
            mel_lib_xml_names($c, $depth - 1, $names);
    }
}

// The name of the storage of the source of a folder of the library (storage_name://<name>; as is, the shell compares it
// so, DataModel::get_storage_by_name), null for another source; '' for none, '.', '..' or one with a '/' or a NUL.
/**
 * @param MelLibFolder $folder
 * @return string|null
 */
function mel_lib_storage_name($folder)
{
    $sid = $folder['source_id'];
    if (strpos($sid, 'storage_name://') !== 0)
        return null;
    $name = strval(substr($sid, 15));
    return preg_match('@[/\x00]@', $name) || $name === '.' || $name === '..' ? '' : $name;
}

// Where the storage $name is mounted: its path in storage_list.xml ($storages of mel_lib_storages; $how 'list'); not
// there at all, its link /tmp/mnt/storage/<name> when that is a dir ('symlink'; the format of the file is known by the
// code of r25 only, update_local_folders_file.sh of the shell takes the link) - with FS_PREFIX, as a path of the list
// (mel_lib_fs), the one looked at (under MEL_LIB_ROOT with no FS_PREFIX, as mel_lib_paths); else '' ('none': not
// connected - as a share not mounted). No dir of the storage is read.
/**
 * @param string $name
 * @param array<string, MelLibStorage> $storages
 * @param string $how
 * @return string
 */
function mel_lib_storage_path($name, $storages, &$how)
{
    $how = 'none';
    if ($name === '')
        return '';
    if (isset($storages[$name]))
    {
        if ($storages[$name]['path'] !== '')
            $how = 'list';
        return $storages[$name]['path'];
    }
    $link = MEL_LIB_STORAGE_LINKS . "/$name";
    $p = mel_lib_fs($link);
    if (!is_dir($p === $link ? MEL_LIB_ROOT . $p : $p))
        return '';
    $how = 'symlink';
    return $p;
}

// The mount of the source of a folder of the library: array(its path, its server), array('', '') when it is not
// mounted. A network folder (network_folder_id://<id>, network_folder://<name>) by its share (mel_lib_mount_for_nf); a
// storage (storage_name://<name>: USB, the disk of the bay) by $storages (mel_lib_storage_path); the internal memory
// (sdcard://) at /sdcard. The server of a storage, of sdcard://: its source_id (the disk that sleeps, apart from a NAS).
/**
 * @param MelLibFolder $folder
 * @param array<string, MelLibNf> $nfs
 * @param list<MelLibMount> $mounts
 * @param array<string, MelLibStorage> $storages
 * @return array{string, string}
 */
function mel_lib_source_mount($folder, $nfs, $mounts, $storages)
{
    $sid = $folder['source_id'];
    if ($sid === MEL_LIB_SDCARD_SID)
        return array(MEL_LIB_SDCARD, $sid);
    $name = mel_lib_storage_name($folder);
    if ($name !== null)
    {
        $how = '';
        $p = mel_lib_storage_path($name, $storages, $how);
        return $p === '' ? array('', '') : array($p, $sid);
    }
    $nf = mel_lib_source_nf($folder, $nfs);
    return $nf ? mel_lib_mount_for_nf($nf, $mounts) : array('', '');
}

// A source Melange can play the files of, by the config only (MelLib::$play): a network folder of the settings, a
// storage of a name, the internal memory. Its share or storage connected or not.
/**
 * @param MelLibFolder $folder
 * @param array<string, MelLibNf> $nfs
 * @return bool
 */
function mel_lib_source_known($folder, $nfs)
{
    $name = mel_lib_storage_name($folder);
    return $folder['source_id'] === MEL_LIB_SDCARD_SID || ($name !== null && $name !== '') ||
        mel_lib_source_nf($folder, $nfs) !== null;
}

// The line of the log of the folders of the library of a source not a network folder, '' for none: the storages of
// storage_list.xml (mel_lib_storages: their count, kinds, or why none), each such folder by its id with the scheme of
// its source and how its path was found - list, symlink, none (mel_lib_storage_path), fixed (sdcard://). No name,
// label or uuid of a storage, no path.
/**
 * @param array<int|string, MelLibFolder> $folders
 * @return string
 */
function mel_lib_local_line($folders)
{
    $parts = array();
    $storages = null;
    $list = '';
    foreach ($folders as $id => $f)
    {
        $sid = $f['source_id'];
        if (mel_lib_sid_nf($sid) !== null)
            continue;
        $name = mel_lib_storage_name($f);
        if ($name !== null && $storages === null)
        {
            $err = '';
            $d = '';
            $storages = mel_lib_storages($err, $d);
            $list = $err === '' ? " $d" : ": $err" . ($d !== '' ? ", $d" : '');
        }
        $how = 'none';
        if ($sid === MEL_LIB_SDCARD_SID)
            $how = 'fixed';
        else if ($name !== null)
            mel_lib_storage_path($name, $storages, $how);
        $scheme = preg_match('@^([a-z_]{1,24})://@', $sid, $m) ? $m[1] : 'other';
        $parts[] = "folder $id $scheme $how";
    }
    if (!$parts)
        return '';
    return 'library: local folders: ' . implode(', ', $parts) . ($storages === null ? '' : "; storages$list");
}

// The network folder of the source of a folder of the library (network_folder_id://<id>, network_folder://<name>),
// null for none or another source.
/**
 * @param MelLibFolder $folder
 * @param array<string, MelLibNf> $nfs
 * @return MelLibNf|null
 */
function mel_lib_source_nf($folder, $nfs)
{
    $ref = mel_lib_sid_nf($folder['source_id']);
    if ($ref === null)
        return null;
    $nf = null;
    if ($ref[0] === 'id')
        $nf = isset($nfs[$ref[1]]) ? $nfs[$ref[1]] : null;
    else
    {
        foreach ($nfs as $x)
        {
            if ($x['name'] === $ref[1])
                $nf = $x;
        }
    }
    return $nf;
}

// The network folder a source_id names: array('id', <id>) of network_folder_id://<id>, array('name', <name>) of
// network_folder://<name>; null for another source.
/**
 * @param string $sid
 * @return array{string, string}|null
 */
function mel_lib_sid_nf($sid)
{
    if (strpos($sid, 'network_folder_id://') === 0)
        return array('id', strval(substr($sid, 20)));
    if (strpos($sid, 'network_folder://') === 0)
        return array('name', strval(substr($sid, 17)));
    return null;
}

// UrlUtils::make_rel_path of the shell: the path of a file under the root of
// its source.
/**
 * @param string $folder_path
 * @param string $file_path
 * @return string
 */
function mel_lib_rel_path($folder_path, $file_path)
{
    $fp = $folder_path === '/' ? '' : $folder_path;
    return $file_path === '/' ? $fp : "$fp/$file_path";
}

// The URL the shell plays a file of folder $folder of the library by, $rel its path under the root of the source
// (mel_lib_rel_path), as GuiCommon::get_playback_url_by_source_id of r25: of sdcard://, of storage_name://<name> - the
// source and the path; of a network folder (network_folder_id://<id>, network_folder://<name>) -
// network_folder_id://<its id> and the path. null: another source, a storage of a name not taken (mel_lib_storage_name),
// a network folder not of the settings.
/**
 * @param MelLibFolder $folder
 * @param array<string, MelLibNf> $nfs
 * @param string $rel
 * @return string|null
 */
function mel_lib_shell_url($folder, $nfs, $rel)
{
    $sid = $folder['source_id'];
    $name = mel_lib_storage_name($folder);
    if ($sid === MEL_LIB_SDCARD_SID || ($name !== null && $name !== ''))
        return $sid . $rel;
    $nf = mel_lib_source_nf($folder, $nfs);
    return $nf ? "network_folder_id://{$nf['id']}$rel" : null;
}

// The main stream of a disc folder (a Blu-ray folder <name>/BDMV/...: ctype 3 of the index) by its record $f: its hrp,
// the path of that file in the folder ("BDMV/STREAM/00005.m2ts") - checked in place of the folder, played when the
// shell can not play the folder (mel_lib_shell_url; vod_play of the folder does not play, of the file it does, with no
// chapters: device r25 22.11.2026); '' for none, of a record of another ctype
// (the indexer writes hrp and ctype apart: an ISO file, a DVD folder) or a path not safe (absolute, a "." or ".." in it,
// a control character).
/**
 * @param array<mixed> $f
 * @return string
 */
function mel_lib_hrp($f)
{
    $h = isset($f['hrp'], $f['ctype']) && is_string($f['hrp']) && is_scalar($f['ctype']) &&
        strval($f['ctype']) === '3' ? $f['hrp'] : '';
    if ($h === '' || $h[0] === '/' || preg_match('~[\x00-\x1f\x7f]|(?:^|/)\.\.?(?:/|\z)~', $h))
        return '';
    return $h;
}

// --- Index.

// Where the shell keeps folder_files.<id>.json (folder_movies.<id>.json for
// $kind 'movies'; StorageHelper: /flashdata, /persistfs on old models).
/**
 * @param int|string $folder_id
 * @param string $kind
 * @return list<string>
 */
function mel_lib_index_paths($folder_id, $kind = 'files')
{
    return array("/flashdata/movie_index/folder_$kind.$folder_id.json",
        "/persistfs/movie_index/folder_$kind.$folder_id.json");
}

// The sizes of an index quoted before json_decode: PHP 5.3 on the 32-bit
// Dune saturates a bigger integer to 2147483647. The expression of
// mel_decode_streams (parse.php), for the key size only.
/**
 * @param string $doc
 * @return string
 */
function mel_lib_quote_size($doc)
{
    return (string) preg_replace('/("size"\\s*:\\s*)([0-9]{10,}(?:\\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/', '$1"$2"', $doc);
}

// The index of the folder $id (folder_$kind.<id>.json, the first place of it there) decoded, if it fits in $left bytes
// (less then by its size) -> array(the list or null, why none: 'no index', 'too big', 'over budget', 'read failed',
// 'not JSON'). The text is freed before the list is returned.
/**
 * @param int|string $id
 * @param string $kind
 * @param int $left
 * @return array{array<mixed>|null, string}
 */
function mel_lib_read_index($id, $kind, &$left)
{
    $why = 'no index';
    foreach (mel_lib_index_paths($id, $kind) as $p)
    {
        list($doc, $w) = mel_lib_read($p, $left);
        if ($doc === false)
        {
            // Too big or over the budget: the folder is skipped, not looked for in the next place.
            if ($w === 'too big' || $w === 'over budget')
                return array(null, $w);
            if ($w !== 'no such file')
                $why = $w;
            continue;
        }
        $left -= strlen($doc);
        if ($kind === 'files')
            $doc = mel_lib_quote_size($doc);
        $list = json_decode($doc, true);
        unset($doc);
        return is_array($list) ? array($list, '') : array(null, 'not JSON');
    }
    return array(null, $why);
}

// The movie files of the index of a folder (ass_type 0, no season, no
// episode) -> list of MelLibFile, $left: the bytes of the budget left
// (mel_lib_read_index). As load_index_for_folder of the shell: a record
// without hash or id is skipped, the first of a path kept. $info: 'N records,
// M files' (of movies) or why there are none. $ep_in: added to, by movie_id,
// its episode files (ass_type 0, season and episode 1 or more: f, MelLibEpIn,
// numbers as in the index, only the fields the rules of the numbers and the
// episodes kept need) and the count of its records neither a movie nor an
// episode (b: a bonus, a season 0, a season with no episode); of all the
// folders in one: the episode files are many, no copy of them. $bonus: added
// to, by movie_id, its bonus files of no season and episode (ass_type 2, s and
// e < 0) as movie files, counted in b too: mel_lib_index takes them for a
// title of no other record.
/**
 * @param int|string $folder_id
 * @param string $info
 * @param int $left
 * @param array<string, array{f: list<MelLibEpIn>, b: int}>|null $ep_in
 * @param-out array<string, array{f: list<MelLibEpIn>, b: int}> $ep_in
 * @param array<string, list<MelLibFile>>|null $bonus
 * @param-out array<string, list<MelLibFile>> $bonus
 * @return list<MelLibFile>
 */
function mel_lib_folder_index($folder_id, &$info, &$left, &$ep_in = null, &$bonus = null)
{
    if ($ep_in === null)
        $ep_in = array();
    if ($bonus === null)
        $bonus = array();
    list($list, $info) = mel_lib_read_index($folder_id, 'files', $left);
    if ($list === null)
        return array();
    // One string of the folder for all its records (not one each).
    $fid = strval($folder_id);
    $files = array();
    $seen = array();
    foreach ($list as $f)
    {
        // A hash of zeros: none, as the shell skips it (data_model.php r25).
        if (!is_array($f) || !isset($f['path'], $f['hash'], $f['id']) || !is_string($f['path']) ||
            !is_scalar($f['hash']) || preg_match('/^0*$/D', strval($f['hash'])) || !is_scalar($f['id']) ||
            isset($seen[$f['path']]))
            continue;
        $seen[$f['path']] = 1;
        // Strings in the index.
        $s = isset($f['s']) && is_numeric($f['s']) ? intval($f['s']) : -1;
        $e = isset($f['e']) && is_numeric($f['e']) ? intval($f['e']) : -1;
        $ass = isset($f['ass_type']) && is_numeric($f['ass_type']) ? intval($f['ass_type']) : 0;
        $mid = strval($f['id']);
        $ep = $ass === 0 && $s >= 1 && $e >= 1;
        if (!$ep && ($ass !== 0 || $s >= 0 || $e >= 0))
        {
            if (!isset($ep_in[$mid]))
                $ep_in[$mid] = array('f' => array(), 'b' => 0);
            $ep_in[$mid]['b']++;
            if ($ass !== 2 || $s >= 0 || $e >= 0)
                continue;
        }
        $tg = isset($f['tags']) && is_string($f['tags']) ? $f['tags'] : '';
        // The folder of the file in the folder of the library ('' at its root), for the path of a copy shown and the
        // packs of the episodes.
        $dir = mel_ts_dirname($f['path']);
        if ($ep)
        {
            if (!isset($ep_in[$mid]))
                $ep_in[$mid] = array('f' => array(), 'b' => 0);
            // The tags as the index has them (mel_lib_ep_file reads them for a copy); match_confirmed: only for the
            // rule of the confirmed (mel_lib_eps_fix), not in the file taken.
            $ep_in[$mid]['f'][] = array('folder' => $fid, 'path' => $f['path'], 'hash' => strval($f['hash']),
                'size' => mel_num(isset($f['size']) ? $f['size'] : 0), 'tg' => $tg, 's' => $s, 'e' => $e,
                'dir' => $dir, 'mc' => isset($f['match_confirmed']) && $f['match_confirmed'] === 'yes');
            continue;
        }
        // The media tags of the indexer ("MKV,4K,23.976,DV,HEVC,16:9,Atmos,7.1"), read once (mel_lib_tags).
        $tags = mel_lib_tags($tg);
        $file = array(
            'folder' => $fid,
            'path' => $f['path'],
            'mid' => $mid,
            'hash' => strval($f['hash']),
            'size' => mel_num(isset($f['size']) ? $f['size'] : 0),
            'tags' => $tags,
            'res' => mel_lib_res($tags, $f['path']),
            's' => -1,
            'e' => -1,
            'dir' => $dir);
        $hrp = mel_lib_hrp($f);
        if ($hrp !== '')
            $file['hrp'] = $hrp;
        if ($ass === 0)
            $files[] = $file;
        else
            $bonus[$mid][] = $file;
    }
    $info = count($list) . ' records, ' . count($files) . ' files';
    unset($list, $seen);
    return $files;
}

// The file of a copy (MelLibFile) of the episode file $f of the series $mid kept (MelLib::$eps): its tags read, its
// resolution by them, else by the name, its folder in the folder of the library.
/**
 * @param string $mid
 * @param MelLibEpRec $f
 * @return MelLibFile
 */
function mel_lib_ep_file($mid, $f)
{
    $tags = mel_lib_tags($f['tg']);
    return array('folder' => $f['folder'], 'path' => $f['path'], 'mid' => $mid, 'hash' => $f['hash'],
        'size' => $f['size'], 'tags' => $tags, 'res' => mel_lib_res($tags, $f['path']), 's' => $f['s'],
        'e' => $f['e'], 'dir' => mel_ts_dirname($f['path']));
}

// The rules of the numbers of the episode files of one movie_id (as the index has them, mel_lib_folder_index) ->
// files: the ones taken, their season and episode final (in their order, no mc, no dir: MelLibEpRec); drop: the hashes
// of the rest; the counts for the log. A pack: mel_lib_pack. By the name of the file the number (mel_ts_ep, else the
// number leading the name mel_ts_ep_lead) and the season if any:
// 1. no number in the name: taken as in the index;
// 2. the same as the index: taken as in the index - an anchor of its pack;
// 3. another one, no anchor in its pack (a pack of absolute numbers), or all such files of the pack of one season of
// the index other than the seasons of 1 and 2 there, with no season in the name and the number of the name past the
// episode of the index by one shift (a folder of two seasons numbered on: "- 05" is S2E1 past S1E1-4): taken as in
// the index;
// 4. another one, an anchor in its pack (an index error of the season): the season of the rest of its pack taken as in
// the index (1, 2), if one, the episode of the name; a season in the name other than that one, a number 0 of the name,
// or the number of another file of the pack (after the fixes) - dropped;
// 5. of a season and episode with a file of match_confirmed, the files without it dropped.
/**
 * @param list<MelLibEpIn> $files
 * @return array{files: list<MelLibEpRec>, drop: list<string>, eps: int, fixed: int, index: int, name: int, hidden: int}
 */
function mel_lib_eps_fix($files)
{
    $res = array('files' => array(), 'drop' => array(), 'eps' => 0, 'fixed' => 0, 'index' => 0, 'name' => 0,
        'hidden' => 0);
    // The number of the name of each file, the same as the index or not; the packs with an anchor (2).
    $num = array();
    $anchor = array();
    foreach ($files as $i => $f)
    {
        $n = mel_ts_ep($f['path']);
        if ($n === null)
            $n = mel_ts_ep_lead($f['path']);
        $same = $n !== null && $n[1] === $f['e'] && ($n[0] === null || $n[0] === $f['s']);
        $num[$i] = array($n, $same);
        if ($same)
            $anchor[mel_lib_pack($f)] = true;
    }
    // Index of $files => array(season, episode) taken; by pack: the seasons ($se_of) and the "s:e" ($se_seen) of the
    // files taken as in the index (1, 2), the files of another number with an anchor ($fix).
    $ok = array();
    $se_of = array();
    $se_seen = array();
    $fix = array();
    foreach ($files as $i => $f)
    {
        $pack = mel_lib_pack($f);
        list($n, $same) = $num[$i];
        // step 1, 2: no number in the name, the same number.
        if ($n === null || $same)
        {
            $ok[$i] = array($f['s'], $f['e']);
            $se_of[$pack][$f['s']] = true;
            $se_seen[$pack][mel_lib_se($f['s'], $f['e'])] = true;
        }
        // step 3, no anchor in the pack.
        else if (empty($anchor[$pack]))
        {
            $ok[$i] = array($f['s'], $f['e']);
            $res['index']++;
        }
        else
            $fix[$pack][$i] = $n;
    }
    // step 3, numbered on: by pack, all its files of another number of one season of the index not of its anchors, no
    // season in the name, one shift of the number over 0.
    foreach ($fix as $pack => $nums)
    {
        // array(season, shift) of the files so far; false: not numbered on.
        $on = null;
        foreach ($nums as $i => $n)
        {
            $f = $files[$i];
            $one = array($f['s'], $n[1] - $f['e']);
            if ($n[0] !== null || isset($se_of[$pack][$f['s']]) || $one[1] < 1 || ($on !== null && $one !== $on))
            {
                $on = false;
                break;
            }
            $on = $one;
        }
        if (!$on)
            continue;
        foreach ($nums as $i => $n)
        {
            $ok[$i] = array($files[$i]['s'], $files[$i]['e']);
            $res['index']++;
        }
        unset($fix[$pack]);
    }
    // step 4: the season of the pack, the number of the name; one file of each "s:e" in its pack.
    $cand = array();
    $count = array();
    foreach ($fix as $pack => $nums)
    {
        $ss = isset($se_of[$pack]) ? array_keys($se_of[$pack]) : array();
        foreach ($nums as $i => $n)
        {
            if (count($ss) !== 1 || $n[1] < 1 || ($n[0] !== null && $n[0] !== $ss[0]))
                continue;
            $k = mel_lib_se($ss[0], $n[1]);
            $cand[$i] = array($pack, $k, array($ss[0], $n[1]));
            $count[$pack][$k] = isset($count[$pack][$k]) ? $count[$pack][$k] + 1 : 1;
        }
    }
    foreach ($cand as $i => $c)
    {
        list($pack, $k, $se) = $c;
        if (isset($se_seen[$pack][$k]) || $count[$pack][$k] > 1)
            continue;
        $ok[$i] = $se;
        $res['fixed']++;
    }
    // step 5: the files not confirmed of an episode with a confirmed one.
    $conf = array();
    foreach ($ok as $i => $se)
    {
        if ($files[$i]['mc'])
            $conf[mel_lib_se($se[0], $se[1])] = true;
    }
    $eps = array();
    foreach ($files as $i => $f)
    {
        if (!isset($ok[$i]))
        {
            $res['drop'][] = $f['hash'];
            $res['name']++;
            continue;
        }
        $k = mel_lib_se($ok[$i][0], $ok[$i][1]);
        if (!$f['mc'] && isset($conf[$k]))
        {
            $res['drop'][] = $f['hash'];
            $res['hidden']++;
            continue;
        }
        $eps[$k] = true;
        $f['s'] = $ok[$i][0];
        $f['e'] = $ok[$i][1];
        unset($f['mc'], $f['dir']);
        $res['files'][] = $f;
    }
    $res['eps'] = count($eps);
    return $res;
}

// The signature of the configs: '' when there is no movie_index_config.xml. Not storage_list.xml: the index does not
// depend on it (MelLib::$play by the config; the copies read it each time), and its service may write it often.
/**
 * @return string
 */
function mel_lib_sig_cfg()
{
    $c = mel_lib_stat(MEL_LIB_CONFIG);
    return $c === '' ? '' : $c . '|' . mel_lib_stat(MEL_LIB_NF);
}

// The signature of the indexes of the folders $ids (of their folder_movies for $kind 'movies').
/**
 * @param list<int|string> $ids
 * @param string $kind
 * @return string
 */
function mel_lib_sig_files($ids, $kind = 'files')
{
    $s = '';
    foreach ($ids as $id)
    {
        $one = '';
        foreach (mel_lib_index_paths($id, $kind) as $p)
        {
            $one = mel_lib_stat($p);
            if ($one !== '')
                break;
        }
        $s .= "|$id:$one";
    }
    return $s;
}

// The library in MelLib, read again only when the signature changed: stat of
// the two configs and of the indexes of the folders of the last read (a new
// folder changes the config itself). The indexes one by one, each up to
// MEL_LIB_MAX_BYTES, all up to MEL_LIB_BUDGET_BYTES; a folder past them is
// skipped, their count in the log. No movie_index_config.xml: an empty
// library, "library: none" in the log once. The line of the local folders at
// each read, and again, with no read, when storage_list.xml changes it (a
// storage put in or out).
/**
 * @return void
 */
function mel_lib_index()
{
    clearstatcache();
    $cfg = mel_lib_sig_cfg();
    $sl = mel_lib_stat(MEL_LIB_STORAGES);
    if ($cfg === '')
    {
        if (MelLib::$sig !== '')
        {
            mel_log('library: none');
            MelLib::$sig = '';
            MelLib::$folders = array();
            MelLib::$nfs = array();
            MelLib::$by_mid = array();
            MelLib::$eps = array();
            MelLib::$eps_drop = array();
            MelLib::$play = array();
            MelLib::$found = array();
        }
        return;
    }
    $sig = $cfg . mel_lib_sig_files(array_keys(MelLib::$folders));
    if ($sig === MelLib::$sig)
    {
        if ($sl !== MelLib::$sl)
        {
            MelLib::$sl = $sl;
            $local = mel_lib_local_line(MelLib::$folders);
            if ($local !== MelLib::$local && $local !== '')
                mel_log($local);
            MelLib::$local = $local;
        }
        return;
    }
    $t = microtime(true);
    $err = '';
    $folders = mel_lib_folders($err);
    if ($err !== '')
        mel_log("library: config: $err");
    $nfs = mel_lib_network_folders($err);
    if ($err !== '')
        mel_log("library: network folders: $err");
    $local = mel_lib_local_line($folders);
    if ($local !== '')
        mel_log($local);
    MelLib::$sl = $sl;
    MelLib::$local = $local;
    $ids = array_keys($folders);
    if ($ids !== array_keys(MelLib::$folders))
        $sig = $cfg . mel_lib_sig_files($ids);
    $by_mid = array();
    $ep_in = array();
    $bonus = array();
    $n = 0;
    // The episode files of the folders read so far.
    $ne = 0;
    $skipped = 0;
    $left = MEL_LIB_BUDGET_BYTES;
    foreach ($ids as $id)
    {
        $info = '';
        $files = mel_lib_folder_index($id, $info, $left, $ep_in, $bonus);
        $ne0 = $ne;
        $ne = 0;
        foreach ($ep_in as $x)
            $ne += count($x['f']);
        if ($info === 'too big' || $info === 'over budget')
            $skipped++;
        else if (!$files && $ne === $ne0)
            mel_log("library: folder $id: $info");
        $n += count($files);
        foreach ($files as $f)
            $by_mid[$f['mid']][] = $f;
        unset($files);
    }
    if ($skipped)
        mel_log("library: index budget: $skipped folders skipped");
    // A title of bonus files only in all the folders (no movie file, no episode, no other record): they are its copies
    // - full movies the hash base of dunemdb marked so, played by the shell as any. Beside a movie file or of a series
    // a bonus stays one.
    $nb = 0;
    foreach ($bonus as $mid => $list)
    {
        if (isset($by_mid[$mid]) || $ep_in[$mid]['f'] || $ep_in[$mid]['b'] !== count($list))
            continue;
        $by_mid[$mid] = $list;
        $nb++;
    }
    unset($bonus);
    if ($nb)
        mel_log("library: $nb titles of bonus files only, their copies kept");
    // The rules on the episodes of all the folders of a movie_id at once: the confirmed file of an episode may be in
    // another folder (a pack is of one folder). The files as the index has them dropped at once, one movie_id after
    // another (not all of them kept with all those taken).
    $eps = array();
    $drop = array();
    foreach (array_keys($ep_in) as $mid)
    {
        $nf = count($ep_in[$mid]['f']);
        $b = $ep_in[$mid]['b'];
        $r = $nf ? mel_lib_eps_fix($ep_in[$mid]['f']) : null;
        unset($ep_in[$mid]);
        if (!$r)
            continue;
        if ($r['files'])
            $eps[$mid] = $r['files'];
        if ($r['drop'])
            $drop[$mid] = $r['drop'];
        mel_log("library: series $mid: $nf files, {$r['eps']} episodes, " .
            "{$r['fixed']} fixed by name, {$r['index']} by index (no anchors), {$r['name']} dropped (name), " .
            "{$r['hidden']} unconfirmed hidden, $b skipped (bonus/no number)");
        unset($r);
    }
    // Kept with folders skipped too: they are not read again on each card, only when their files change.
    MelLib::$sig = $sig;
    MelLib::$folders = $folders;
    MelLib::$nfs = $nfs;
    MelLib::$by_mid = $by_mid;
    MelLib::$eps = $eps;
    MelLib::$eps_drop = $drop;
    MelLib::$play = mel_lib_play_map($folders, $nfs, $by_mid, $eps);
    MelLib::$found = array();
    mel_log('library: index read, ' . count($folders) . " folders, $n files, $ne episode files, " .
        round((microtime(true) - $t) * 1000) . ' ms');
}

// The titles of $by_mid and $eps with a file in a folder of a source Melange can play (mel_lib_source_known;
// MelLib::$play) -> movie_id => movie, series, '' (both). By the config only: no share or storage looked at.
/**
 * @param array<int|string, MelLibFolder> $folders
 * @param array<string, MelLibNf> $nfs
 * @param array<string, list<MelLibFile>> $by_mid
 * @param array<string, list<MelLibEpRec>> $eps
 * @return array<string, string>
 */
function mel_lib_play_map($folders, $nfs, $by_mid, $eps)
{
    $net = array();
    foreach ($folders as $id => $f)
    {
        if (mel_lib_source_known($f, $nfs))
            $net[strval($id)] = true;
    }
    $out = array();
    if (!$net)
        return $out;
    foreach (array('movie' => $by_mid, 'series' => $eps) as $type => $map)
    {
        foreach ($map as $mid => $files)
        {
            foreach ($files as $f)
            {
                if (isset($net[$f['folder']]))
                {
                    $mid = strval($mid);
                    $out[$mid] = isset($out[$mid]) ? '' : $type;
                    break;
                }
            }
        }
    }
    return $out;
}

// --- Copies.

// 'lib-' + 12 hex of the hash of a file of the index: the key of a copy in
// the watch history (wh_release) and in the log. No ':' in it. The hash is of
// the content: the mirrors of one file on two shares have one key (one record).
/**
 * @param string $hash
 * @return string
 */
function mel_lib_key($hash)
{
    return 'lib-' . substr(md5($hash), 0, 12);
}

// $k is a key of a copy (mel_lib_key): of the uid of a record, of wh_release of "Continue".
/**
 * @param mixed $k
 * @return bool
 */
function mel_lib_key_ok($k)
{
    return is_string($k) && preg_match('/^lib-[0-9a-f]{12}$/D', $k) === 1;
}

// The id of a copy (mel_lib_cid) in a regex: of the window of the copies (mel_lib_cid_ok) and of lib_next: (mel_lib_lazy).
define('MEL_LIB_CID', 'cp-[0-9a-f]{12}');

// 'cp-' + 12 hex of the path to play of a copy (MEL_LIB_CID): its id in the window of the copies and the choice of it
// (one of mirrors of one key), no path in the params of an action.
/**
 * @param string $url
 * @return string
 */
function mel_lib_cid($url)
{
    return 'cp-' . substr(md5($url), 0, 12);
}

// $k is the id of a copy (mel_lib_cid): of the window of the copies.
/**
 * @param mixed $k
 * @return bool
 */
function mel_lib_cid_ok($k)
{
    return is_string($k) && preg_match('/^' . MEL_LIB_CID . '$/D', $k) === 1;
}

// The resolution in the name of a file (the last part of its path): '4K',
// '1080p', '720p', ... or ''. The expression of mel_row (parse.php); 2160p and
// 4k are 4K.
/**
 * @param string $path
 * @return string
 */
function mel_lib_quality($path)
{
    $p = strrpos($path, '/');
    $name = $p === false ? $path : substr($path, $p + 1);
    if (!preg_match('/\\b([0-9]{3,4}p|4k)\\b/i', $name, $m))
        return '';
    $q = strtolower($m[1]);
    return $q === '2160p' || $q === '4k' ? '4K' : $q;
}

// The resolutions of a copy, the word to show and to sort by => array(its media tag of the indexer, '' for none: by
// the name of the file only (mel_lib_quality); its rank, the higher the better: the order of MediaTags::$TAGS_ORDER of
// the shell, 4K > 2K, 1440p > 1080p > 720p).
/**
 * @return array<string, array{string, int}>
 */
function mel_lib_res_words()
{
    return array('4K' => array('4K', 4), '2K' => array('2K', 3), '1440p' => array('', 3), '1080p' => array('1080', 2),
        '720p' => array('720', 1));
}

// The media tags $s of a file of the index ("MKV,4K,23.976,DV,Atmos,7.1") -> each in upper case once, in their order,
// none empty: the case of a tag not minded anywhere after.
/**
 * @param string $s
 * @return list<string>
 */
function mel_lib_tags($s)
{
    $tags = array();
    foreach (explode(',', strtoupper($s)) as $t)
    {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $tags, true))
            $tags[] = $t;
    }
    return $tags;
}

// The resolution of a file of the index, the one of its copy to show and to sort by (mel_lib_res_rank): by its media
// tags (mel_lib_res_words), by the name of its file (mel_lib_quality) only when it has no tags; '' for none.
/**
 * @param list<string> $tags
 * @param string $path
 * @return string
 */
function mel_lib_res($tags, $path)
{
    if (!$tags)
        return mel_lib_quality($path);
    $words = mel_lib_res_words();
    foreach ($tags as $t)
    {
        foreach ($words as $res => $w)
        {
            if ($w[0] !== '' && $w[0] === $t)
                return $res;
        }
    }
    return '';
}

// The rank of the resolution of a copy (mel_lib_res, mel_lib_res_words), the higher the better; 0 for another one,
// -1 for none.
/**
 * @param string $res
 * @return int
 */
function mel_lib_res_rank($res)
{
    $words = mel_lib_res_words();
    return isset($words[$res]) ? $words[$res][1] : ($res !== '' ? 0 : -1);
}

// The groups of the media tags of the indexer by MediaTags::$TAGS_ORDER of the shell (media_tags.php r25), upper case,
// the best first in each: the resolution, HDR, the audio, the channels (only the order of the copies, mel_lib_order).
/**
 * @return list<list<string>>
 */
function mel_lib_tag_groups()
{
    return array(
        array('4K', '2K', '1080', '720'),
        array('DV', 'HDR10+', 'HDR10', 'HLG'),
        array('ATMOS', 'DTS:X', 'TRUEHD', 'DTS-HD MA', 'DTS-HD', 'DD', 'DTS'),
        array('9.1', '7.1', '6.1', '5.1'));
}

// The weight of a copy as MediaTags::get_order_by_tags of the shell weighs its tags: of each tag of a group its place
// from the end of the group (the best the most), times 1000 for the resolution, 100 HDR, 10 the audio, 1 the channels,
// all summed; no tags: its resolution by the name of the file (mel_lib_res_rank) in place of the tag of it.
/**
 * @param MelLibCopy $c
 * @return int
 */
function mel_lib_order($c)
{
    if (!$c['tags'])
    {
        $r = mel_lib_res_rank($c['res']);
        return $r > 0 ? $r * 1000 : $r;
    }
    $w = array();
    $mult = 1000;
    foreach (mel_lib_tag_groups() as $g)
    {
        $n = count($g);
        foreach ($g as $k => $tag)
            $w[$tag] = ($n - $k) * $mult;
        $mult = intval($mult / 10);
    }
    $sum = 0;
    foreach ($c['tags'] as $t)
    {
        if (isset($w[$t]))
            $sum += $w[$t];
    }
    return $sum;
}

// usort of the copies, the order of the shell (MediaTags::choose_tags_from_files, mel_lib_order): the weight of the
// tags down, then the size down, then the path.
/**
 * @param MelLibCopy $a
 * @param MelLibCopy $b
 * @return int
 */
function mel_lib_cmp_copies($a, $b)
{
    $oa = mel_lib_order($a);
    $ob = mel_lib_order($b);
    if ($oa !== $ob)
        return $ob - $oa;
    if ($a['size'] != $b['size'])
        return $a['size'] < $b['size'] ? 1 : -1;
    return strcmp($a['path'], $b['path']);
}

// The media tags of copy $c as a parsedFile of AIOStreams, for the badges of the list of releases (mel_badges,
// mel_gc_row_badges) and its channels: resolution - res of the copy (by the tags, else by the name); quality BluRay of
// a tag Blu-ray; visualTags and audioTags - its tags by the names mel_badges and mel_audio_rules know (DV, HDR10+,
// HDR, Atmos, TrueHD, DD, DTS:X, ...: the case of a tag not minded); audioChannels - its layouts "N.N". The others (the
// container, the frame rate, the codec, the frame, 3D) none.
/**
 * @param MelLibCopy $c
 * @return array{resolution: string, quality: string, visualTags: list<string>, audioTags: list<string>, audioChannels: list<string>}
 */
function mel_lib_pf($c)
{
    $names = array('vis' => array(), 'audio' => array());
    foreach (array('DV', 'HDR10+', 'HDR10', 'HDR', 'HLG', '10bit') as $t)
        $names['vis'][strtoupper($t)] = $t;
    foreach (mel_audio_rules() as $r)
    {
        foreach ($r[2] as $t)
            $names['audio'][strtoupper($t)] = $t;
    }
    $pf = array('resolution' => $c['res'], 'quality' => '', 'visualTags' => array(), 'audioTags' => array(),
        'audioChannels' => array());
    foreach ($c['tags'] as $t)
    {
        if ($t === 'BLU-RAY')
            $pf['quality'] = 'BluRay';
        else if (isset($names['vis'][$t]))
            $pf['visualTags'][] = $names['vis'][$t];
        else if (isset($names['audio'][$t]))
            $pf['audioTags'][] = $names['audio'][$t];
        else if (preg_match('/^[0-9]\.[0-9]$/D', $t))
            $pf['audioChannels'][] = $t;
    }
    return $pf;
}

// The first copy of key $key of copies $copies (of mirrors the best), null none.
/**
 * @param list<MelLibCopy> $copies
 * @param string $key
 * @return MelLibCopy|null
 */
function mel_lib_copy_by_key($copies, $key)
{
    $of = mel_lib_copies_of($copies, $key);
    return $of ? $of[0] : null;
}

// The copies of copies $copies of $k - the key of a record (mirrors: all of it, in their order) or the id of one
// (mel_lib_cid); none of ''.
/**
 * @param list<MelLibCopy> $copies
 * @param string $k
 * @return list<MelLibCopy>
 */
function mel_lib_copies_of($copies, $k)
{
    $res = array();
    foreach ($copies as $c)
    {
        if ($k !== '' && ($c['key'] === $k || $c['cid'] === $k))
            $res[] = $c;
    }
    return $res;
}

// The copies of the movie $mid of the index: its files whose source is mounted now (mel_lib_files_copies), the best
// first. The mount list is read on every call, and only when the movie has files in the index.
/**
 * @param string $mid
 * @return list<MelLibCopy>
 */
function mel_lib_copies($mid)
{
    mel_lib_index();
    return empty(MelLib::$by_mid[$mid]) ? array() : mel_lib_files_copies(MelLib::$by_mid[$mid]);
}

// The copies of the files $files of the index: the ones whose source is mounted now (with the caption of their folder,
// for mel_lib_tails), each path to play once (one share in two network folders, by the name and by the IP of its
// server: the first), the best first, as the shell chooses (mel_lib_cmp_copies). Reads the mount list, and the list of
// the storages for a file of a folder of one.
/**
 * @param list<MelLibFile> $files
 * @return list<MelLibCopy>
 */
function mel_lib_files_copies($files)
{
    $err = '';
    $mounts = mel_lib_mounts($err);
    if ($err !== '')
        mel_log("library: mounts: $err");
    $res = array();
    $mnt = array();
    $urls = array();
    $storages = null;
    foreach ($files as $f)
    {
        $id = $f['folder'];
        if (!isset(MelLib::$folders[$id]))
            continue;
        $folder = MelLib::$folders[$id];
        if ($storages === null && mel_lib_storage_name($folder) !== null)
        {
            $d = '';
            $storages = mel_lib_storages($err, $d);
            if ($err !== '')
                mel_log("library: storages: $err");
        }
        if (!isset($mnt[$id]))
            $mnt[$id] = mel_lib_source_mount($folder, MelLib::$nfs, $mounts, $storages === null ? array() : $storages);
        if ($mnt[$id][0] === '')
            continue;
        // A disc folder: its main stream (mel_lib_hrp) - checked (mel_lib_here), its MKV read; played by the shell by
        // the URL of the folder (shell_url, mel_lib_shell_url; none - its main stream, vod_play).
        $rel = mel_lib_rel_path($folder['path'], $f['path']);
        $url = $mnt[$id][0] . $rel . (isset($f['hrp']) ? '/' . $f['hrp'] : '');
        if (isset($urls[$url]))
            continue;
        $urls[$url] = 1;
        $c = $f + array('url' => $url, 'mnt' => $mnt[$id][0], 'srv' => $mnt[$id][1],
            'key' => mel_lib_key($f['hash']), 'cid' => mel_lib_cid($url), 'caption' => $folder['caption']);
        $su = isset($f['hrp']) ? mel_lib_shell_url($folder, MelLib::$nfs, $rel) : null;
        if ($su !== null)
            $c['shell_url'] = $su;
        $res[] = $c;
    }
    usort($res, 'mel_lib_cmp_copies');
    return $res;
}

// --- Episodes.

// The key "s:e" of episode S$s E$e of a series of the index in the copies of its episodes (mel_lib_ep_copies; the own
// card: nav lib_eps, lib_cur; the playlist and lib_next; the same of the card and of the history by the invariant,
// mel_lib_ep_same), one for all.
/**
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_lib_se($s, $e)
{
    return "$s:$e";
}

// The copies of the episodes of the series $mid of the index (MelLib::$eps: the season and the episode after the rules
// of the numbers) -> "s:e" => its copies (mel_lib_files_copies, the best first), the episodes in order. The mount list
// is read on every call, and only when the series has episodes in the index.
/**
 * @param string $mid
 * @return array<string, list<MelLibCopy>>
 */
function mel_lib_ep_copies($mid)
{
    mel_lib_index();
    if (empty(MelLib::$eps[$mid]))
        return array();
    $files = array();
    foreach (MelLib::$eps[$mid] as $f)
        $files[] = mel_lib_ep_file($mid, $f);
    $res = array();
    foreach (mel_lib_files_copies($files) as $c)
        $res[mel_lib_se($c['s'], $c['e'])][] = $c;
    uksort($res, 'strnatcmp');
    return $res;
}

// The hashes of the episode files of the series $mid of the index, mounted or not (the number of an episode of a record
// of the history by its file, video_id "hash:<hash>"): hash => array(season, episode) of the files taken, 'skip' of the
// files the rules of the numbers dropped (mel_lib_eps_fix: a number not sure, a copy not confirmed hidden) - a record
// of one is of no episode. A hash of a file taken and of one dropped (mirrors): the one taken. The hashes in upper case
// (as the shell writes video_id; mel_wh_fix_se).
/**
 * @param string $mid
 * @return array<string, array{int, int}|string>
 */
function mel_lib_ep_hashes($mid)
{
    mel_lib_index();
    $res = array();
    foreach (isset(MelLib::$eps[$mid]) ? MelLib::$eps[$mid] : array() as $f)
    {
        $h = strtoupper($f['hash']);
        if (!isset($res[$h]))
            $res[$h] = array($f['s'], $f['e']);
    }
    foreach (isset(MelLib::$eps_drop[$mid]) ? MelLib::$eps_drop[$mid] : array() as $h)
    {
        $h = strtoupper($h);
        if (!isset($res[$h]))
            $res[$h] = 'skip';
    }
    return $res;
}

// The pack of a file or a copy of the index: its folder of the library and the folder of the file in it,
// "<folder>|<dir>" (the folder an id: the first '|' ends it). A path: never in the log.
/**
 * @param array{folder: string, dir: string} $f
 * @return string
 */
function mel_lib_pack($f)
{
    return $f['folder'] . '|' . $f['dir'];
}

// The packs of the episode files of the series $mid of the index of the key $key (mel_lib_key; mirrors: a pack each),
// mounted or not: "the same folder" of the last choice of a copy, though its share is not mounted now.
/**
 * @param string $mid
 * @param string $key
 * @return list<string>
 */
function mel_lib_key_packs($mid, $key)
{
    mel_lib_index();
    $res = array();
    foreach (isset(MelLib::$eps[$mid]) ? MelLib::$eps[$mid] : array() as $f)
    {
        $p = mel_lib_pack(array('folder' => $f['folder'], 'dir' => mel_ts_dirname($f['path'])));
        if (mel_lib_key($f['hash']) === $key && !in_array($p, $res, true))
            $res[] = $p;
    }
    return $res;
}

// How near the pack $b is to the pack $a (mel_lib_pack): -1 of another folder of the library, else the count of the
// folders of their dirs the same from the root of the folder of the library (0: only the folder of the library) -
// "Show/Season 2" is nearer to "Show/Season 1" (1) than "Other/Season 2" (0).
/**
 * @param string $a
 * @param string $b
 * @return int
 */
function mel_lib_pack_near($a, $b)
{
    $pa = explode('|', $a, 2);
    $pb = explode('|', $b, 2);
    if (count($pa) < 2 || count($pb) < 2 || $pa[0] !== $pb[0])
        return -1;
    $da = $pa[1] === '' ? array() : explode('/', $pa[1]);
    $db = $pb[1] === '' ? array() : explode('/', $pb[1]);
    $n = 0;
    while ($n < count($da) && $n < count($db) && $da[$n] === $db[$n])
        $n++;
    return $n;
}

// The uid of the file of the copy $copy in the history of the shell (its own record of the file, its mark "watched"),
// '' for another source: as the shell makes it (DataModel::make_network_folder_uid_prefix and get_folder_uid_prefix,
// data_model.php r25; GuiCommon::get_qualified_uid_by_file, gui_common.php) - smb://<server>/<dir> of the network
// folder (the server and the share in lower case, mel_lib_network_folders), nfs://<server><dir> with no ":/", the
// slashes squeezed, none at the end; storage_name://<the name of the storage in lower case>; sdcard:// (then "/": three
// slashes); then "/", the path of the folder of the library with no slashes around and "/" (none of the root), the path
// of the file. Its server, share, storage and path: never in the log.
/**
 * @param array{folder: string, path: string} $copy
 * @return string
 */
function mel_lib_native_uid($copy)
{
    if (!isset(MelLib::$folders[$copy['folder']]))
        return '';
    $folder = MelLib::$folders[$copy['folder']];
    $name = mel_lib_storage_name($folder);
    if ($folder['source_id'] === MEL_LIB_SDCARD_SID)
        $p = MEL_LIB_SDCARD_SID;
    else if ($name !== null && $name !== '')
        $p = 'storage_name://' . mb_strtolower($name, 'UTF-8');
    else
    {
        $nf = mel_lib_source_nf($folder, MelLib::$nfs);
        if (!$nf)
            return '';
        $p = $nf['type'] === 'smb' ? "smb://{$nf['server']}/{$nf['dir']}" : strval(preg_replace(
            array('@^nfs://([^/]*):/@', '@^nfs://([^/]*)/:/@'), 'nfs://$1/', "nfs://{$nf['server']}{$nf['dir']}"));
        $p = substr($p, 0, 6) . rtrim(strval(preg_replace('@/+@', '/', substr($p, 6))), '/');
    }
    $path = trim($folder['path'], '/');
    return "$p/" . ($path === '' ? '' : "$path/") . $copy['path'];
}

// --- The movie_id of a title.

// A name as the titles are compared: lower case, ё as е, all but letters and digits a space, the spaces squeezed.
/**
 * @param string $s
 * @return string
 */
function mel_lib_norm($s)
{
    $s = preg_replace('/[^\p{L}\p{N}]+/u', ' ', str_replace('ё', 'е', mb_strtolower($s, 'UTF-8')));
    return is_string($s) ? trim($s) : '';
}

// The years of a title of folder_movies, "2019" or "2019-2021" (MovieHelper::parse_years of the shell) -> first, last.
/**
 * @param mixed $years
 * @return array{int, int}
 */
function mel_lib_years($years)
{
    $y = is_scalar($years) ? strval($years) : '';
    $p = strpos($y, '-');
    if (!$p)
        return array(intval($y), intval($y));
    return array(intval(substr($y, 0, $p)), intval(substr($y, $p + 1)));
}

// The titles of folder_movies.<id>.json of all the folders (load_folder_movies_file of the shell), movies and series,
// each movie_id once of a type: read when the stat of these files changed since the last read. Each file up to
// MEL_LIB_MAX_BYTES, all up to MEL_LIB_BUDGET_BYTES; a folder past them is skipped, their count in the log.
/**
 * @return list<array{string, list<string>, int, int, string}>
 */
function mel_lib_movies()
{
    $ids = array_keys(MelLib::$folders);
    $sig = mel_lib_sig_files($ids, 'movies');
    if (MelLib::$movies !== null && $sig === MelLib::$movies_sig)
        return MelLib::$movies;
    $t = microtime(true);
    $res = array();
    $seen = array();
    $skipped = 0;
    $left = MEL_LIB_BUDGET_BYTES;
    foreach ($ids as $id)
    {
        list($list, $why) = mel_lib_read_index($id, 'movies', $left);
        if ($why === 'too big' || $why === 'over budget')
            $skipped++;
        else if ($why !== '' && $why !== 'no index')
            mel_log("library: folder $id: folder_movies $why");
        foreach ($list !== null ? $list : array() as $m)
        {
            if (!is_array($m) || !isset($m['id'], $m['title']) || !is_string($m['id']) || !is_string($m['title']))
                continue;
            $type = isset($m['type']) && $m['type'] === 'series' ? 'series' : 'movie';
            if (isset($seen["$type:{$m['id']}"]))
                continue;
            $seen["$type:{$m['id']}"] = 1;
            $names = array();
            foreach (array($m['title'], isset($m['native_title']) ? $m['native_title'] : '') as $n)
            {
                $n = is_string($n) ? mel_lib_norm($n) : '';
                if ($n !== '' && !in_array($n, $names, true))
                    $names[] = $n;
            }
            list($y1, $y2) = mel_lib_years(isset($m['years']) ? $m['years'] : '');
            $res[] = array($m['id'], $names, $y1, $y2, $type);
        }
        unset($list);
    }
    if ($skipped)
        mel_log("library: folder_movies budget: $skipped folders skipped");
    MelLib::$movies = $res;
    MelLib::$movies_sig = $sig;
    MelLib::$found = array();
    mel_log('library: folder_movies read, ' . count($res) . ' titles, ' . round((microtime(true) - $t) * 1000) . ' ms');
    return $res;
}


// The names of the title $t as the titles are compared (mel_lib_norm), each once, sorted; none empty.
/**
 * @param MelLibTitle $t
 * @return list<string>
 */
function mel_lib_names($t)
{
    $names = array();
    foreach ($t['names'] as $n)
    {
        $n = mel_lib_norm($n);
        if ($n !== '')
            $names[$n] = true;
    }
    ksort($names);
    return array_map('strval', array_keys($names));
}

// The key of MelLib::$found of the title $t: all mel_lib_by_movies goes by (the type, IMDb, TMDB, the year,
// mel_lib_names); '' when it does not look (no year, no names, no ids).
/**
 * @param MelLibTitle $t
 * @return string
 */
function mel_lib_movies_key($t)
{
    $year = intval($t['year']);
    $names = mel_lib_names($t);
    if ($year <= 0 || !$names || ($t['imdb'] === '' && $t['tmdb'] === ''))
        return '';
    return "{$t['type']}|{$t['imdb']}|{$t['tmdb']}|$year|" . implode('|', $names);
}

// The movie_id of the files of the title $t by its name and year (mel_lib_movies_key not ''): the titles $movies of
// folder_movies (mel_lib_movies) of its type, of the year +-1, with the same title or native_title (mel_lib_norm) and
// files in the index (mel_lib_has), the first MEL_LIB_CANDIDATES of them; the first whose pair (links.php) has the IMDb
// or the TMDB of $t (of a series its tmdbtv). The pair of a candidate known is taken as is, else read from its card of
// the shell; each pair read is kept (mel_pg_link_put), the pairs written once: the next time the pairs find it and no
// card is read again. '' for none.
/**
 * @param MelLibTitle $t
 * @param list<array{string, list<string>, int, int, string}> $movies
 * @return string
 */
function mel_lib_by_movies($t, $movies)
{
    $year = intval($t['year']);
    $names = array_fill_keys(mel_lib_names($t), true);
    $cands = array();
    foreach ($movies as $m)
    {
        list($mid, $mnames, $y1, $y2, $type) = $m;
        if ($type !== $t['type'] || $year < $y1 - 1 || $year > $y2 + 1 || !mel_lib_has($mid, $type))
            continue;
        foreach ($mnames as $n)
        {
            if (isset($names[$n]))
            {
                $cands[] = $mid;
                break;
            }
        }
        if (count($cands) >= MEL_LIB_CANDIDATES)
            break;
    }
    $res = '';
    $put = false;
    $tk = $t['type'] === 'series' ? 'tmdbtv' : 'tmdb';
    foreach ($cands as $mid)
    {
        $links = mel_pg_links();
        $pair = isset($links[$mid]) && !isset($links[$mid]['none']) && !isset($links[$mid]['fail']) ? $links[$mid] : null;
        $known = $pair !== null;
        if (!$known)
        {
            $t0 = microtime(true);
            $pair = mel_pg_shell_pair($mid);
            if ($pair && mel_pg_link_put($mid, $pair, false))
                $put = true;
        }
        $same = $pair && (($t['imdb'] !== '' && isset($pair['imdb']) && $pair['imdb'] === $t['imdb']) ||
            ($t['tmdb'] !== '' && isset($pair[$tk]) && $pair[$tk] === $t['tmdb']));
        if (!$known)
            mel_log("library: card $mid: shell card " . ($pair ? 'read' : 'none') . ', ' .
                round((microtime(true) - $t0) * 1000) . ' ms' . ($pair ? ($same ? ', the same ids' : ', not the same ids') : ''));
        if ($same)
        {
            $res = $mid;
            break;
        }
    }
    if ($put)
        mel_pg_links_save();
    return $res;
}

// The files of the index of the titles of the type $type by movie_id: of a movie its movie files (MelLib::$by_mid), of
// a series its episodes (MelLib::$eps); none of another type.
/**
 * @param string $type
 * @return array<string, list<MelLibFile>>|array<string, list<MelLibEpRec>>
 */
function mel_lib_map($type)
{
    if ($type === 'series')
        return MelLib::$eps;
    return $type === 'movie' ? MelLib::$by_mid : array();
}

// The movie_id $mid has files of the type $type in the index (mel_lib_map).
/**
 * @param string $mid
 * @param string $type
 * @return bool
 */
function mel_lib_has($mid, $type)
{
    $map = mel_lib_map($type);
    return !empty($map[$mid]);
}

// The index (mel_lib_index) and the pairs (mel_pg_links) ready for the badges of the tiles (mel_lib_tile_has): once a
// build of a screen or a row, the index looked at once in MEL_LIB_TILE_S.
/**
 * @return void
 */
function mel_lib_tile_start()
{
    $now = microtime(true);
    if (MelLib::$tile_t > 0 && $now - MelLib::$tile_t >= 0 && $now - MelLib::$tile_t < MEL_LIB_TILE_S)
        return;
    MelLib::$tile_t = $now;
    mel_lib_index();
    mel_pg_links();
}

// The title of tile $t (of a row, of the own card) can be played from the disk (MelLib::$play): its movie_id $mid of
// the history, else a card of its pairs (mel_pg_tile_keys); a movie by its movie files, a series by its episodes, a
// tile of no type by either. Only a look in memory (mel_lib_tile_start once before, else here): no file of the shell
// read, no line of the log of its own. No library: nothing more.
/**
 * @param MelCatTile $t
 * @param string $mid
 * @return bool
 */
function mel_lib_tile_has($t, $mid = '')
{
    if (!(MelLib::$tile_t > 0))
        mel_lib_tile_start();
    if (!MelLib::$play)
        return false;
    $any = $t['type'] !== 'movie' && $t['type'] !== 'series';
    $lists = $mid !== '' ? array(array($mid)) : array();
    foreach (mel_pg_tile_keys($t) as $k)
    {
        if (isset(MelPgLinks::$rev[$k]))
            $lists[] = MelPgLinks::$rev[$k];
    }
    foreach ($lists as $list)
    {
        foreach ($list as $m)
        {
            if (isset(MelLib::$play[$m]) && ($any || MelLib::$play[$m] === '' || MelLib::$play[$m] === $t['type']))
                return true;
        }
    }
    return false;
}

// The movie_id of a card of the pairs (links.php, mel_pg_rev) with the IMDb or the TMDB of the title $t (of a series
// its tmdbtv) and files in the index (mel_lib_has), '' for none. No read.
/**
 * @param MelLibTitle $t
 * @return string
 */
function mel_lib_by_pairs($t)
{
    $keys = array();
    if ($t['imdb'] !== '')
        $keys[] = 'imdb:' . $t['imdb'];
    if ($t['tmdb'] !== '')
        $keys[] = ($t['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $t['tmdb'];
    $mids = array();
    foreach ($keys as $k)
    {
        foreach (mel_pg_rev($k) as $mid)
        {
            if (mel_lib_has($mid, $t['type']) && !in_array($mid, $mids, true))
                $mids[] = $mid;
        }
    }
    if (!$mids)
        return '';
    // The card of the Dune with files is found before: of two cards here, neither is it.
    if (count($mids) > 1)
        mel_log('library: ' . count($mids) . ' cards by pairs, the first taken');
    return $mids[0];
}

// The movie_id of the index for the title $t (a movie or a series), '' for none (no library, no files): its card of the
// Dune, else a card of the pairs (mel_lib_by_pairs), else mel_lib_by_movies. Only cards with files of its type in the
// index count (mel_lib_has: movie files of a movie, episodes of a series).
// The first two read nothing and are looked at on each call; what mel_lib_by_movies found (and '') is kept in
// MelLib::$found until the index or folder_movies is read again: the same card opened again reads no card of the
// shell, and no line of the log. Else one line: "library: title -> <movie_id> by dune|pairs|folder_movies" or
// "library: title -> none".
/**
 * @param MelLibTitle $t
 * @return string
 */
function mel_lib_mid($t)
{
    mel_lib_index();
    if (!mel_lib_map($t['type']))
        return '';
    $by = 'dune';
    $y = $t['dune'] !== '' && mel_lib_has($t['dune'], $t['type']) ? $t['dune'] : '';
    if ($y === '')
    {
        $by = 'pairs';
        $y = mel_lib_by_pairs($t);
    }
    $key = $y === '' ? mel_lib_movies_key($t) : '';
    if ($key !== '')
    {
        // Read again (MelLib::$found emptied) when its files changed.
        $movies = mel_lib_movies();
        if (isset(MelLib::$found[$key]))
            return MelLib::$found[$key];
        $by = 'folder_movies';
        $y = mel_lib_by_movies($t, $movies);
        MelLib::$found[$key] = $y;
    }
    mel_log('library: title -> ' . ($y !== '' ? "$y by $by" : 'none'));
    return $y;
}

// The title of mel_lib_mid of the fields $m of a movie_str: of the shell (play_action) or of the own card (its
// movie_str, mel_oc_watch) - so the card and play_action of one title look for it alike (the same names, the same key
// of MelLib::$found). dune: id; imdb: imdb_id; tmdb: tmdb_id of a movie, of a series its TMDB of series tmdbtv_id only
// (as the shell and the own card give it; none - '', as mel_movie); names: title, native_title and aio_en_title
// (the English name the own card adds), in that order, each once, none empty. Ids not of their form: ''.
/**
 * @param array<mixed> $m
 * @param bool $series
 * @return MelLibTitle
 */
function mel_lib_title($m, $series)
{
    $names = array();
    foreach (array('title', 'native_title', 'aio_en_title') as $k)
    {
        $n = mel_str($m, $k);
        if ($n !== '' && !in_array($n, $names, true))
            $names[] = $n;
    }
    $dune = mel_str($m, 'id');
    $imdb = mel_str($m, 'imdb_id');
    // Of a series its tmdbtv_id only: its tmdb_id may be of a movie (mel_movie).
    $tmdb = mel_str($m, $series ? 'tmdbtv_id' : 'tmdb_id');
    return array(
        'dune' => preg_match('/^' . MEL_DUNE_ID . '$/D', $dune) ? $dune : '',
        'imdb' => preg_match('/^tt[0-9]{1,10}$/D', $imdb) ? $imdb : '',
        'tmdb' => preg_match('/^[0-9]{1,10}$/D', $tmdb) ? $tmdb : '',
        'type' => $series ? 'series' : 'movie',
        'year' => mel_str($m, 'year'),
        'names' => $names);
}

// --- The file of a copy there.

// Is the file of the copy $copy there now: 'yes', 'no' or 'slow' (no answer in MEL_LIB_HERE_S s). Only from the
// operation of a start (OK, play_action), never while a card is laid out: a share of a NAS off may stay "mounted", a
// stat on it hangs until the timeout of cifs, and the keys and the operation with it if php_server did it itself. So
// MEL_LIB_HERE_SH in the background, killed by its own watcher 2 s past the wait (stdin, stdout and stderr redirected,
// one '&': exec() does not wait for it), its answer polled every 50 ms in a file of tmp_dir, removed then (and those of
// checks answered too late). The paths only in the environment of the check (putenv, taken out after exec()): not in
// the command, where escapeshellarg of PHP 5.3 in the locale C drops the bytes of a Cyrillic path. The log: "library:
// here lib-... yes|no|slow in N ms"; a copy of a storage or of the internal memory not there: "...; mount dir yes|no,
// readable yes|no" of the root of its mount (a wrong path apart from no access), no path; of a disc folder (its main
// stream checked, mel_lib_hrp) "...; bd hrp", its first bytes not read. No tmp_dir: 'yes', not
// checked (the player tells). $mkv: of a file there, $dur - the length of its MKV (mel_mkv_duration) by its first 64
// KB, read by the same check past its answer (php_server reads no share), waited for MEL_LIB_MKV_MS more; 0 for none in
// time, not MKV, no length. The log: "library: mkv lib-... N s in N ms" or "library: mkv lib-... unknown (why) in N
// ms".
/**
 * @param MelLibCopy $copy
 * @param bool $mkv
 * @param float|null $dur
 * @param-out float $dur
 * @return string
 */
function mel_lib_here($copy, $mkv = false, &$dur = null)
{
    $dur = 0.0;
    // The main stream of a disc folder (mel_lib_hrp) is no MKV: its first bytes not read.
    if (isset($copy['hrp']))
        $mkv = false;
    $t = microtime(true);
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ?
        rtrim(strval(DuneSystem::$properties['tmp_dir_path']), '/') : '';
    if ($tmp === '' || (!is_dir($tmp) && !mkdir($tmp, 0755, true)))
    {
        mel_log("library: here {$copy['key']} not checked, no tmp_dir");
        return 'yes';
    }
    foreach ((array) glob("$tmp/lib_here_*") as $old)
    {
        if (is_string($old))
            unlink($old);
    }
    $res = "$tmp/lib_here_" . getmypid() . '_' . str_replace('.', '', uniqid('', true));
    $local = isset(MelLib::$folders[$copy['folder']]) &&
        mel_lib_sid_nf(MelLib::$folders[$copy['folder']]['source_id']) === null;
    putenv('MEL_P=' . $copy['url']);
    putenv('MEL_R=' . $res);
    if ($mkv)
        putenv('MEL_B=1');
    if ($local)
        putenv('MEL_M=' . $copy['mnt']);
    exec('sh -c ' . escapeshellarg(MEL_LIB_HERE_SH) . ' </dev/null >/dev/null 2>&1 &');
    putenv('MEL_P');
    putenv('MEL_R');
    if ($mkv)
        putenv('MEL_B');
    if ($local)
        putenv('MEL_M');
    $ans = 'slow';
    $mnt = '';
    do
    {
        clearstatcache();
        if (is_file($res))
        {
            $out = trim(strval(file_get_contents($res)));
            $ans = $out === '1' ? 'yes' : 'no';
            if (preg_match('/^0([01])([01])$/D', $out, $m))
                $mnt = '; mount dir ' . ($m[1] === '1' ? 'yes' : 'no') . ', readable ' . ($m[2] === '1' ? 'yes' : 'no');
            unlink($res);
            break;
        }
        usleep(50000);
    }
    while (microtime(true) - $t < MEL_LIB_HERE_S);
    mel_log("library: here {$copy['key']} $ans in " . round((microtime(true) - $t) * 1000) . " ms$mnt" .
        (isset($copy['hrp']) ? '; bd hrp' : ''));
    if ($mkv && $ans === 'yes')
        $dur = mel_lib_here_mkv($copy, "$res.b");
    return $ans;
}

// The length of the MKV of the copy $copy by its first bytes, the file $b of its check (mel_lib_here) waited for
// MEL_LIB_MKV_MS, removed then -> s, 0.0 for none: not in time (removed by the next check), not read (an empty $b: the
// read failed), not MKV, no length.
/**
 * @param MelLibCopy $copy
 * @param string $b
 * @return float
 */
function mel_lib_here_mkv($copy, $b)
{
    $t = microtime(true);
    $why = 'not in time';
    $dur = 0.0;
    do
    {
        clearstatcache();
        if (is_file($b))
        {
            $head = strval(file_get_contents($b));
            unlink($b);
            if ($head === '')
                $why = 'not read';
            else
                $dur = mel_mkv_duration($head, $why);
            break;
        }
        usleep(20000);
    }
    while ((microtime(true) - $t) * 1000 < MEL_LIB_MKV_MS);
    mel_log("library: mkv {$copy['key']} " . ($dur > 0 ? round($dur) . ' s' : "unknown ($why)") . ' in ' .
        round((microtime(true) - $t) * 1000) . ' ms');
    return $dur;
}
