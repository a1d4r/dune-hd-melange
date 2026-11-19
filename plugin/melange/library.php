<?php
// The media library of the Dune ("Фильмы" of the shell), read only: its
// folders (movie_index_config.xml), the network folders of their sources
// (network_folders.properties), the shares the shell has mounted
// (network_mount_list.xml) and the index of the files of each folder
// (folder_files.<id>.json), as shell_ext/movies of r25 reads them
// (data_model.php, nf_common.php, gui_common.php). The copies of a movie are
// its files whose share is mounted now. Only mel_lib_here touches a share: a
// test -f of one file in the background, from the operation of a start only.
// No login or password of a share is kept, returned or logged; the log has no
// path, server, share, folder caption or file name: folders by id, files by
// their key lib-... (mel_lib_key).
// Needs main.php (mel_log, mel_num, mel_str), links.php (mel_pg_links, mel_pg_rev, mel_pg_shell_pair, mel_pg_link_put,
// mel_pg_links_save). The menu of the copies: mel_lib_pick_menu, mel_lib_label (sources.php); their start:
// mel_lib_start (playback.php).

// The files of the shell. PHP on the Dune adds FS_PREFIX to /config,
// /flashdata and /tmp itself; a failed plain read is tried with FS_PREFIX.
// Tests define MEL_LIB_ROOT, the root of these paths, first.
if (!defined('MEL_LIB_ROOT'))
    define('MEL_LIB_ROOT', '');
define('MEL_LIB_CONFIG', '/config/movie_index_config.xml');
define('MEL_LIB_NF', '/config/network_folders.properties');
define('MEL_LIB_MOUNTS', '/tmp/run/network_mount_list.xml');
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
// Seconds mel_lib_here waits for its check of a file: a NAS asleep spins its disks up; a share still "mounted" of a NAS
// off answers only by the timeout of cifs (the check is in the background: php_server does not hang).
if (!defined('MEL_LIB_HERE_S'))
    define('MEL_LIB_HERE_S', 8);
// The script of the check (sh -c): 1 or 0 for a file at $MEL_P to $MEL_R, renamed there whole. The path only in the
// environment. Tests define their own first (a check that does not answer in time).
if (!defined('MEL_LIB_HERE_SH'))
    define('MEL_LIB_HERE_SH',
        'if test -f "$MEL_P"; then r=1; else r=0; fi; echo $r >"$MEL_R.p" && mv -f "$MEL_R.p" "$MEL_R"');

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
    // Movie files only (ass 0, s < 0, e < 0) by their movie_id.
    /** @var array<string, list<MelLibFile>> */
    public static $by_mid = array();
    // Files of the shell read so far (tests count the reads by it).
    /** @var int */
    public static $reads = 0;
    // The titles of the folders (folder_movies, mel_lib_movies): movie_id, the normalized names, the first and the
    // last year; null: not read. Read lazily, again when the stat of their files changes ($movies_sig).
    /** @var list<array{string, list<string>, int, int}>|null */
    public static $movies = null;
    /** @var string */
    public static $movies_sig = '';
    // What mel_lib_by_movies found for a title (movie_id or ''), by all it goes by (mel_lib_movies_key); emptied whole
    // when the index or folder_movies is read again.
    /** @var array<string, string> */
    public static $found = array();
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
    if (stripos($doc, '<!DOCTYPE') !== false)
        return false;
    $quiet = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($doc);
    libxml_clear_errors();
    libxml_use_internal_errors($quiet);
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

// The mount path of a network folder, '' when its share is not mounted
// (DataModel::get_network_mount_by_server_and_subdir, data_model.php r25):
// mounted, the same type, an SMB mount of a share (no '/' in its dir), the
// server by name or by server_ipaddr, the dir the same or a parent of it.
/**
 * @param MelLibNf $nf
 * @param list<MelLibMount> $mounts
 * @return string
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
        if ($mnt['dir'] === $nf['dir'])
            return $mnt['path'];
        $len = strlen($mnt['dir']);
        if (strncmp($nf['dir'], $mnt['dir'] . '/', $len + 1) === 0)
            return $mnt['path'] . substr($nf['dir'], $len);
    }
    return '';
}

// The mount path of the source of a folder of the library, '' when it is not
// mounted. Only network folders (network_folder_id://<id>,
// network_folder://<name>); other sources (USB, the internal memory) are not
// supported yet.
/**
 * @param MelLibFolder $folder
 * @param array<string, MelLibNf> $nfs
 * @param list<MelLibMount> $mounts
 * @return string
 */
function mel_lib_source_mount($folder, $nfs, $mounts)
{
    $sid = $folder['source_id'];
    $nf = null;
    if (strpos($sid, 'network_folder_id://') === 0)
    {
        $id = substr($sid, 20);
        $nf = isset($nfs[$id]) ? $nfs[$id] : null;
    }
    else if (strpos($sid, 'network_folder://') === 0)
    {
        $name = substr($sid, 17);
        foreach ($nfs as $x)
        {
            if ($x['name'] === $name)
                $nf = $x;
        }
    }
    return $nf ? mel_lib_mount_for_nf($nf, $mounts) : '';
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
// M files' or why there are none.
/**
 * @param int|string $folder_id
 * @param string $info
 * @param int $left
 * @return list<MelLibFile>
 */
function mel_lib_folder_index($folder_id, &$info, &$left)
{
    list($list, $info) = mel_lib_read_index($folder_id, 'files', $left);
    if ($list === null)
        return array();
    $files = array();
    $seen = array();
    foreach ($list as $f)
    {
        if (!is_array($f) || !isset($f['path'], $f['hash'], $f['id']) || !is_string($f['path']) ||
            !is_scalar($f['hash']) || !$f['hash'] || !is_scalar($f['id']) || isset($seen[$f['path']]))
            continue;
        $seen[$f['path']] = 1;
        // Strings in the index.
        $s = isset($f['s']) && is_numeric($f['s']) ? intval($f['s']) : -1;
        $e = isset($f['e']) && is_numeric($f['e']) ? intval($f['e']) : -1;
        $ass = isset($f['ass_type']) && is_numeric($f['ass_type']) ? intval($f['ass_type']) : 0;
        if ($ass !== 0 || $s >= 0 || $e >= 0)
            continue;
        // The media tags of the indexer, as is: "MKV,4K,23.976,DV,HEVC,16:9,Atmos,7.1".
        $tags = isset($f['tags']) && is_string($f['tags']) ? $f['tags'] : '';
        $files[] = array(
            'folder' => strval($folder_id),
            'path' => $f['path'],
            'mid' => strval($f['id']),
            'hash' => strval($f['hash']),
            'size' => mel_num(isset($f['size']) ? $f['size'] : 0),
            'tags' => $tags,
            'res' => mel_lib_res($tags, $f['path']));
    }
    $info = count($list) . ' records, ' . count($files) . ' files';
    unset($list, $seen);
    return $files;
}

// The signature of the configs: '' when there is no movie_index_config.xml.
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
// library, "library: none" in the log once.
/**
 * @return void
 */
function mel_lib_index()
{
    clearstatcache();
    $cfg = mel_lib_sig_cfg();
    if ($cfg === '')
    {
        if (MelLib::$sig !== '')
        {
            mel_log('library: none');
            MelLib::$sig = '';
            MelLib::$folders = array();
            MelLib::$nfs = array();
            MelLib::$by_mid = array();
            MelLib::$found = array();
        }
        return;
    }
    $sig = $cfg . mel_lib_sig_files(array_keys(MelLib::$folders));
    if ($sig === MelLib::$sig)
        return;
    $t = microtime(true);
    $err = '';
    $folders = mel_lib_folders($err);
    if ($err !== '')
        mel_log("library: config: $err");
    $nfs = mel_lib_network_folders($err);
    if ($err !== '')
        mel_log("library: network folders: $err");
    $ids = array_keys($folders);
    if ($ids !== array_keys(MelLib::$folders))
        $sig = $cfg . mel_lib_sig_files($ids);
    $by_mid = array();
    $n = 0;
    $skipped = 0;
    $left = MEL_LIB_BUDGET_BYTES;
    foreach ($ids as $id)
    {
        $info = '';
        $files = mel_lib_folder_index($id, $info, $left);
        if ($info === 'too big' || $info === 'over budget')
            $skipped++;
        else if (!$files)
            mel_log("library: folder $id: $info");
        $n += count($files);
        foreach ($files as $f)
            $by_mid[$f['mid']][] = $f;
        unset($files);
    }
    if ($skipped)
        mel_log("library: index budget: $skipped folders skipped");
    // Kept with folders skipped too: they are not read again on each card, only when their files change.
    MelLib::$sig = $sig;
    MelLib::$folders = $folders;
    MelLib::$nfs = $nfs;
    MelLib::$by_mid = $by_mid;
    MelLib::$found = array();
    mel_log('library: index read, ' . count($folders) . " folders, $n files, " .
        round((microtime(true) - $t) * 1000) . ' ms');
}

// --- Copies.

// 'lib-' + 12 hex of the hash of a file of the index: the key of a copy in
// the watch history (wh_release) and in the log. No ':' in it.
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

// The resolution of a file of the index, the one of its copy to show and to sort by (mel_lib_res_rank): by its media
// tags (mel_lib_res_words), by the name of its file (mel_lib_quality) only when it has no tags; '' for none.
/**
 * @param string $tags
 * @param string $path
 * @return string
 */
function mel_lib_res($tags, $path)
{
    if (trim($tags) === '')
        return mel_lib_quality($path);
    $words = mel_lib_res_words();
    foreach (explode(',', $tags) as $t)
    {
        $t = trim($t);
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

// The groups of the media tags of the indexer by MediaTags::$TAGS_ORDER of the shell (media_tags.php r25), the best
// first in each: the resolution, HDR, the audio, the channels; each tag (upper case) => its badge of the list of
// releases (img/nb2_<id>.png, MelGc::$badge_w), the channels none: they are text. 1080 and 720 are int keys in PHP.
/**
 * @return list<array<int|string, string>>
 */
function mel_lib_tag_groups()
{
    return array(
        array('4K' => '4k', '2K' => '1440p', '1080' => '1080p', '720' => '720p'),
        array('DV' => 'dolby-vision', 'HDR10+' => 'hdr10-plus', 'HDR10' => 'hdr10', 'HLG' => 'hlg'),
        array('ATMOS' => 'dolby-atmos', 'DTS:X' => 'dts-x', 'TRUEHD' => 'dolby-truehd', 'DTS-HD MA' => 'dts-hd-ma',
            'DTS-HD' => 'dts-hd', 'DD' => 'dolby-digital', 'DTS' => 'dts'),
        array('9.1' => '', '7.1' => '', '6.1' => '', '5.1' => ''));
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
    if (trim($c['tags']) === '')
    {
        $r = mel_lib_res_rank($c['res']);
        return $r > 0 ? $r * 1000 : $r;
    }
    $w = array();
    $mult = 1000;
    foreach (mel_lib_tag_groups() as $g)
    {
        $n = count($g);
        foreach (array_keys($g) as $k => $tag)
            $w[strval($tag)] = ($n - $k) * $mult;
        $mult = intval($mult / 10);
    }
    $sum = 0;
    foreach (explode(',', strtoupper($c['tags'])) as $t)
    {
        $t = trim($t);
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

// The badges of copy $c of the line "С диска" of the card: the best tag of each group (mel_lib_tag_groups) - res, hdr,
// audio as the ids of their badges, ch the channels as they are; src 'bluray' of a tag Blu-ray. No tags: res of the
// resolution by the name (res of the copy, lower case: an id of a badge if there is one). The case of a tag not
// minded; another tag (the container, the frame rate, the codec, the frame) - none.
/**
 * @param MelLibCopy $c
 * @return array{res: string, src: string, hdr: string, audio: string, ch: string}
 */
function mel_lib_badges($c)
{
    $b = array('res' => '', 'src' => '', 'hdr' => '', 'audio' => '', 'ch' => '');
    if (trim($c['tags']) === '')
    {
        $b['res'] = $c['res'] === '4K' ? '4k' : ($c['res'] === '2K' ? '1440p' : strtolower($c['res']));
        return $b;
    }
    $tags = array_map('trim', explode(',', strtoupper($c['tags'])));
    $b['src'] = in_array('BLU-RAY', $tags, true) ? 'bluray' : '';
    $groups = mel_lib_tag_groups();
    foreach (array('res', 'hdr', 'audio', 'ch') as $i => $k)
    {
        foreach ($groups[$i] as $tag => $id)
        {
            if (in_array(strval($tag), $tags, true))
            {
                $b[$k] = $k === 'ch' ? strval($tag) : $id;
                break;
            }
        }
    }
    return $b;
}

// The copy of key $key of copies $copies, null none.
/**
 * @param list<MelLibCopy> $copies
 * @param string $key
 * @return MelLibCopy|null
 */
function mel_lib_copy_by_key($copies, $key)
{
    foreach ($copies as $c)
    {
        if ($key !== '' && $c['key'] === $key)
            return $c;
    }
    return null;
}

// The copies of the movie $mid of the index: its files whose source is
// mounted now (with the caption of their folder, for mel_lib_label), each path to play once (one share in two network folders, by
// the name and by the IP of its server: the first), the best first, as the shell chooses
// (mel_lib_cmp_copies). The mount list is read on every call, and only when
// the movie has files in the index.
/**
 * @param string $mid
 * @return list<MelLibCopy>
 */
function mel_lib_copies($mid)
{
    mel_lib_index();
    if (empty(MelLib::$by_mid[$mid]))
        return array();
    $err = '';
    $mounts = mel_lib_mounts($err);
    if ($err !== '')
        mel_log("library: mounts: $err");
    $res = array();
    $mnt = array();
    $urls = array();
    foreach (MelLib::$by_mid[$mid] as $f)
    {
        $id = $f['folder'];
        if (!isset(MelLib::$folders[$id]))
            continue;
        $folder = MelLib::$folders[$id];
        if (!isset($mnt[$id]))
            $mnt[$id] = mel_lib_source_mount($folder, MelLib::$nfs, $mounts);
        if ($mnt[$id] === '')
            continue;
        $url = $mnt[$id] . mel_lib_rel_path($folder['path'], $f['path']);
        if (isset($urls[$url]))
            continue;
        $urls[$url] = 1;
        $res[] = $f + array('url' => $url, 'mnt' => $mnt[$id], 'key' => mel_lib_key($f['hash']),
            'caption' => $folder['caption']);
    }
    usort($res, 'mel_lib_cmp_copies');
    return $res;
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

// The movies of folder_movies.<id>.json of all the folders (load_folder_movies_file of the shell; a series out), each
// movie_id once: read when the stat of these files changed since the last read. Each file up to MEL_LIB_MAX_BYTES, all
// up to MEL_LIB_BUDGET_BYTES; a folder past them is skipped, their count in the log.
/**
 * @return list<array{string, list<string>, int, int}>
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
            if (!is_array($m) || !isset($m['id'], $m['title']) || !is_string($m['id']) || !is_string($m['title']) ||
                isset($seen[$m['id']]) || (isset($m['type']) && $m['type'] === 'series'))
                continue;
            $seen[$m['id']] = 1;
            $names = array();
            foreach (array($m['title'], isset($m['native_title']) ? $m['native_title'] : '') as $n)
            {
                $n = is_string($n) ? mel_lib_norm($n) : '';
                if ($n !== '' && !in_array($n, $names, true))
                    $names[] = $n;
            }
            list($y1, $y2) = mel_lib_years(isset($m['years']) ? $m['years'] : '');
            $res[] = array($m['id'], $names, $y1, $y2);
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

// The key of MelLib::$found of the title $t: all mel_lib_by_movies goes by (IMDb, TMDB, the year, mel_lib_names);
// '' when it does not look (no year, no names, no ids).
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
    return "{$t['imdb']}|{$t['tmdb']}|$year|" . implode('|', $names);
}

// The movie_id of the files of the title $t by its name and year (mel_lib_movies_key not ''): the movies $movies of
// folder_movies (mel_lib_movies) of the year +-1 with the same title or native_title (mel_lib_norm) and files in the
// index, the first MEL_LIB_CANDIDATES of them; the first whose pair (links.php) has the IMDb or the TMDB of $t. The pair
// of a candidate known is taken as is, else read from its card of the shell; each pair read is kept
// (mel_pg_link_put), the pairs written once: the next time the pairs find it and no card is read again. '' for none.
/**
 * @param MelLibTitle $t
 * @param list<array{string, list<string>, int, int}> $movies
 * @return string
 */
function mel_lib_by_movies($t, $movies)
{
    $year = intval($t['year']);
    $names = array_fill_keys(mel_lib_names($t), true);
    $cands = array();
    foreach ($movies as $m)
    {
        list($mid, $mnames, $y1, $y2) = $m;
        if ($year < $y1 - 1 || $year > $y2 + 1 || empty(MelLib::$by_mid[$mid]))
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
            ($t['tmdb'] !== '' && isset($pair['tmdb']) && $pair['tmdb'] === $t['tmdb']));
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

// The movie_id of a card of the pairs (links.php, mel_pg_rev) with the IMDb or the TMDB of the title $t and files in
// the index, '' for none. No read.
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
        $keys[] = 'tmdb:' . $t['tmdb'];
    $mids = array();
    foreach ($keys as $k)
    {
        foreach (mel_pg_rev($k) as $mid)
        {
            if (!empty(MelLib::$by_mid[$mid]) && !in_array($mid, $mids, true))
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

// The movie_id of the index for the title $t (a movie), '' for none (a series, no library, no files): its card of the
// Dune, else a card of the pairs (mel_lib_by_pairs), else mel_lib_by_movies. Only cards with files in the index count.
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
    if ($t['type'] !== 'movie' || !MelLib::$by_mid)
        return '';
    $by = 'dune';
    $y = $t['dune'] !== '' && !empty(MelLib::$by_mid[$t['dune']]) ? $t['dune'] : '';
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
// of MelLib::$found). dune: id; imdb: imdb_id; tmdb: tmdb_id of a movie; names: title, native_title and aio_en_title
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
    $tmdb = $series ? '' : mel_str($m, 'tmdb_id');
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
// MEL_LIB_HERE_SH in the background under timeout -s KILL, 2 s past the wait (stdin, stdout and stderr redirected, one
// '&': exec() does not wait for it), its answer polled every 50 ms in a file of tmp_dir, removed then (and those of
// checks answered too late). The path only in the environment of the check; the log: "library: here lib-... yes|no|slow in N ms". No
// tmp_dir: 'yes', not checked (the player tells).
/**
 * @param MelLibCopy $copy
 * @return string
 */
function mel_lib_here($copy)
{
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
    exec('MEL_P=' . escapeshellarg($copy['url']) . ' MEL_R=' . escapeshellarg($res) . ' timeout -s KILL ' .
        (MEL_LIB_HERE_S + 2) . ' sh -c ' . escapeshellarg(MEL_LIB_HERE_SH) . ' </dev/null >/dev/null 2>&1 &');
    $ans = 'slow';
    do
    {
        clearstatcache();
        if (is_file($res))
        {
            $ans = trim(strval(file_get_contents($res))) === '1' ? 'yes' : 'no';
            unlink($res);
            break;
        }
        usleep(50000);
    }
    while (microtime(true) - $t < MEL_LIB_HERE_S);
    mel_log("library: here {$copy['key']} $ans in " . round((microtime(true) - $t) * 1000) . ' ms');
    return $ans;
}
