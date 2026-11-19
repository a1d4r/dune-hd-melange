<?php
// The media library of the Dune ("Фильмы" of the shell), read only: its
// folders (movie_index_config.xml), the network folders of their sources
// (network_folders.properties), the shares the shell has mounted
// (network_mount_list.xml) and the index of the files of each folder
// (folder_files.<id>.json), as shell_ext/movies of r25 reads them
// (data_model.php, nf_common.php, gui_common.php). The copies of a movie are
// its files whose share is mounted now. Nothing here touches a share.
// No login or password of a share is kept, returned or logged; the log has no
// path, server, share, folder caption or file name: folders by id, files by
// their key lib-... (mel_lib_key).
// Needs main.php (mel_log, mel_num, mel_size_str), links.php (mel_pg_links, MelPgLinks, mel_pg_shell_pair,
// mel_pg_link_put).

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
// Characters of the path in a line of the menu of the copies (mel_lib_label).
define('MEL_LIB_LABEL_MAX', 60);

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
    // What mel_lib_mid found for a title, until the index changes: its key => array(MelLib::$sig, movie_id or '', how).
    /** @var array<string, array{string, string, string}> */
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

// The rank of the resolution of a copy (mel_lib_res), the higher the better:
// 4K > 2K, 1440p > 1080p > 720p (the order of MediaTags::$TAGS_ORDER of the
// shell); 0 for another one, -1 for none.
/**
 * @param string $res
 * @return int
 */
function mel_lib_res_rank($res)
{
    $ranks = array('4K' => 4, '2K' => 3, '1440p' => 3, '1080p' => 2, '720p' => 1);
    return isset($ranks[$res]) ? $ranks[$res] : ($res !== '' ? 0 : -1);
}

// usort of the copies: the resolution down, then the size down, then the path.
/**
 * @param MelLibCopy $a
 * @param MelLibCopy $b
 * @return int
 */
function mel_lib_cmp_copies($a, $b)
{
    $ra = mel_lib_res_rank($a['res']);
    $rb = mel_lib_res_rank($b['res']);
    if ($ra !== $rb)
        return $rb - $ra;
    if ($a['size'] != $b['size'])
        return $a['size'] < $b['size'] ? 1 : -1;
    return strcmp($a['path'], $b['path']);
}

// The copies of the movie $mid of the index: its files whose source is
// mounted now, each path to play once (one share in two network folders, by
// the name and by the IP of its server: the first), the best first
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
        $res[] = $f + array('url' => $url, 'mnt' => $mnt[$id], 'key' => mel_lib_key($f['hash']));
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
    mel_log('library: folder_movies read, ' . count($res) . ' titles, ' . round((microtime(true) - $t) * 1000) . ' ms');
    return $res;
}

// The movie_id of the files of the title $t by its name and year: the movies of folder_movies of the year +-1 with
// the same title or native_title (mel_lib_norm) and files in the index, the first MEL_LIB_CANDIDATES of them; the
// first whose card of the shell has the IMDb or the TMDB of $t. Its pair is kept (mel_pg_link_put): the next time
// the pairs find it. '' for none.
/**
 * @param MelLibTitle $t
 * @return string
 */
function mel_lib_by_movies($t)
{
    $year = intval($t['year']);
    $names = array();
    foreach ($t['names'] as $n)
    {
        $n = mel_lib_norm($n);
        if ($n !== '')
            $names[$n] = true;
    }
    if ($year <= 0 || !$names || ($t['imdb'] === '' && $t['tmdb'] === ''))
        return '';
    $cands = array();
    foreach (mel_lib_movies() as $m)
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
    foreach ($cands as $mid)
    {
        $t0 = microtime(true);
        $pair = mel_pg_shell_pair($mid);
        $same = $pair && (($t['imdb'] !== '' && isset($pair['imdb']) && $pair['imdb'] === $t['imdb']) ||
            ($t['tmdb'] !== '' && isset($pair['tmdb']) && $pair['tmdb'] === $t['tmdb']));
        mel_log("library: card $mid: shell card " . ($pair ? 'read' : 'none') . ', ' .
            round((microtime(true) - $t0) * 1000) . ' ms' . ($pair ? ($same ? ', the same ids' : ', not the same ids') : ''));
        if ($same)
        {
            mel_pg_link_put($mid, $pair);
            return $mid;
        }
    }
    return '';
}

// The movie_id of the files of the title $t (a movie) -> array(movie_id or '', how: dune, pairs, folder_movies, -):
// its card of the Dune, else a card of the pairs (links.php) with its IMDb or TMDB, else mel_lib_by_movies. Only
// cards with files in the index count.
/**
 * @param MelLibTitle $t
 * @return array{string, string}
 */
function mel_lib_find($t)
{
    if ($t['dune'] !== '' && !empty(MelLib::$by_mid[$t['dune']]))
        return array($t['dune'], 'dune');
    $keys = array();
    if ($t['imdb'] !== '')
        $keys[] = 'imdb:' . $t['imdb'];
    if ($t['tmdb'] !== '')
        $keys[] = 'tmdb:' . $t['tmdb'];
    if (!$keys)
        return array('', '-');
    mel_pg_links();
    $mids = array();
    foreach ($keys as $k)
    {
        foreach (isset(MelPgLinks::$rev[$k]) ? MelPgLinks::$rev[$k] : array() as $mid)
        {
            if (!empty(MelLib::$by_mid[$mid]) && !in_array($mid, $mids, true))
                $mids[] = $mid;
        }
    }
    if ($mids)
    {
        // The card of the Dune with files is found above: of two cards here, neither is it.
        if (count($mids) > 1)
            mel_log('library: ' . count($mids) . ' cards by pairs, the first taken');
        return array($mids[0], 'pairs');
    }
    $y = mel_lib_by_movies($t);
    return array($y, $y !== '' ? 'folder_movies' : '-');
}

// The movie_id of the index for the title $t, '' for none (a series, no library, no files): mel_lib_find, kept in
// MelLib::$found until the index changes (an opening of the same card again reads nothing). $by: how it was found
// (dune, pairs, folder_movies; '-' for none).
/**
 * @param MelLibTitle $t
 * @param string $by
 * @return string
 */
function mel_lib_mid($t, &$by = '')
{
    $by = '-';
    mel_lib_index();
    if ($t['type'] !== 'movie' || !MelLib::$by_mid)
        return '';
    $sig = strval(MelLib::$sig);
    $key = "{$t['type']}|{$t['imdb']}|{$t['tmdb']}|{$t['dune']}";
    if (!isset(MelLib::$found[$key]) || MelLib::$found[$key][0] !== $sig)
    {
        list($y, $how) = mel_lib_find($t);
        MelLib::$found[$key] = array($sig, $y, $how);
    }
    $by = MelLib::$found[$key][2];
    return MelLib::$found[$key][1];
}

// --- The line of a copy in the menu of the copies.

// The resolution of a file of the index, the one of its copy to show and to sort by (mel_lib_res_rank): by its media
// tags (4K, 2K, 1080p, 720p), by the name of its file (mel_lib_quality) only when it has no tags; '' for none.
/**
 * @param string $tags
 * @param string $path
 * @return string
 */
function mel_lib_res($tags, $path)
{
    if (trim($tags) === '')
        return mel_lib_quality($path);
    $words = array('4K' => '4K', '2K' => '2K', '1080' => '1080p', '720' => '720p');
    foreach (explode(',', $tags) as $t)
    {
        $t = trim($t);
        if (isset($words[$t]))
            return $words[$t];
    }
    return '';
}

// The length of the common start of two paths, whole parts only (PathUtils::get_common_path_prefix_len of the shell).
/**
 * @param string $a
 * @param string $b
 * @return int
 */
function mel_lib_prefix_len($a, $b)
{
    $la = strlen($a);
    $lb = strlen($b);
    if ($la === 0 || $lb === 0 || $a[0] !== $b[0])
        return 0;
    $n = $a[0] === '/' ? 1 : 0;
    for (;;)
    {
        $na = strpos($a, '/', $n);
        $nb = strpos($b, '/', $n);
        if ($na === false || $na !== $nb || substr($a, $n, $na - $n) !== substr($b, $n, $nb - $n) ||
            $na + 1 >= $la || $na + 1 >= $lb)
            break;
        $n = $na + 1;
    }
    return $n;
}

// The length of the start of the paths the screen "Files" of the shell cuts off (PathUtils::get_common_file_prefix_len):
// their common start; of one path, all but its file and its folder.
/**
 * @param list<string> $paths
 * @return int
 */
function mel_lib_common_len($paths)
{
    if (!$paths)
        return 0;
    if (count($paths) === 1)
    {
        $p = $paths[0];
        $pos = strrpos($p, '/');
        if (!$pos)
            return 0;
        $pos = strrpos($p, '/', -(strlen($p) - $pos + 1));
        return $pos === false ? 0 : $pos + 1;
    }
    $n = mel_lib_prefix_len($paths[0], $paths[1]);
    for ($i = 2; $i < count($paths); $i++)
        $n = min($n, mel_lib_prefix_len($paths[0], $paths[$i]));
    return $n;
}

// $s of over $max characters: its middle replaced by '…' (the start of the path and the end of the name stay).
/**
 * @param string $s
 * @param int $max
 * @return string
 */
function mel_lib_cut($s, $max)
{
    if (mb_strlen($s, 'UTF-8') <= $max)
        return $s;
    $tail = intval(ceil(($max - 1) / 2));
    return mb_substr($s, 0, $max - 1 - $tail, 'UTF-8') . '…' . mb_substr($s, -$tail, $tail, 'UTF-8');
}

// The line of the copy $copy (one of $copies) in the menu of the copies: "4K · 58.3 ГБ · <path>" - the resolution
// (mel_lib_res, none left out), the size (none left out), the path as the screen "Files" of the shell shows it: the
// copies in one folder of the library - the path without the start common to them all; in several - "<caption of
// the folder>/<path>"; over MEL_LIB_LABEL_MAX characters cut in the middle.
/**
 * @param MelLibCopy $copy
 * @param list<MelLibCopy> $copies
 * @param string $lang
 * @return string
 */
function mel_lib_label($copy, $copies, $lang)
{
    $folders = array($copy['folder'] => 1);
    $paths = array($copy['path']);
    foreach ($copies as $c)
    {
        $folders[$c['folder']] = 1;
        if (!in_array($c['path'], $paths, true))
            $paths[] = $c['path'];
    }
    if (count($folders) > 1)
    {
        $id = $copy['folder'];
        $path = (isset(MelLib::$folders[$id]) ? MelLib::$folders[$id]['caption'] : '') . '/' . $copy['path'];
    }
    else
        $path = strval(substr($copy['path'], mel_lib_common_len($paths)));
    $parts = array();
    if ($copy['res'] !== '')
        $parts[] = $copy['res'];
    if ($copy['size'] > 0)
        $parts[] = mel_size_str($copy['size'], $lang);
    $parts[] = mel_lib_cut($path, MEL_LIB_LABEL_MAX);
    return implode(' · ', $parts);
}
