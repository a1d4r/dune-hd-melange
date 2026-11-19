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

// The files of the shell. PHP on the Dune adds FS_PREFIX to /config,
// /flashdata and /tmp itself; a failed plain read is tried with FS_PREFIX.
// Tests define MEL_LIB_ROOT, the root of these paths, first.
if (!defined('MEL_LIB_ROOT'))
    define('MEL_LIB_ROOT', '');
define('MEL_LIB_CONFIG', '/config/movie_index_config.xml');
define('MEL_LIB_NF', '/config/network_folders.properties');
define('MEL_LIB_MOUNTS', '/tmp/run/network_mount_list.xml');
// A bigger file is not a config or an index of this box: not read.
if (!defined('MEL_LIB_MAX_BYTES'))
    define('MEL_LIB_MAX_BYTES', 16777216);

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
// 'read failed'). No PHP warning in the log, no path in the reason.
/**
 * @param string $p
 * @return array{string|false, string}
 */
function mel_lib_read($p)
{
    $why = 'no such file';
    foreach (mel_lib_paths($p) as $f)
    {
        if (!is_file($f))
            continue;
        if (filesize($f) > MEL_LIB_MAX_BYTES)
        {
            $why = 'too big';
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

// Attributes of every <$name ...> tag of a flat XML file of the shell -> list
// of name => value; only the attributes $keys when given (the login and the
// password of a mount are never kept). Not SimpleXML: the files are
// machine-written attribute lists.
/**
 * @param string $doc
 * @param string $name
 * @param list<string> $keys
 * @return list<array<string, string>>
 */
function mel_lib_xml_elements($doc, $name, $keys = array())
{
    $res = array();
    preg_match_all('~<' . $name . '\b([^>]*)>~', $doc, $tags);
    foreach ($tags[1] as $t)
    {
        preg_match_all('~([A-Za-z_][A-Za-z0-9_.-]*)\s*=\s*"([^"]*)"~', $t, $m, PREG_SET_ORDER);
        $a = array();
        foreach ($m as $x)
        {
            if (!$keys || in_array($x[1], $keys, true))
                $a[$x[1]] = html_entity_decode($x[2], ENT_QUOTES, 'UTF-8');
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
    $res = array();
    foreach (mel_lib_xml_elements($doc, 'folder', array('id', 'source_id', 'path', 'caption')) as $f)
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
    $res = array();
    $keys = array('path', 'type', 'server', 'server_ipaddr', 'dir', 'status');
    foreach (mel_lib_xml_elements($doc, 'mount', $keys) as $m)
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

// Where the shell keeps folder_files.<id>.json (StorageHelper: /flashdata,
// /persistfs on old models).
/**
 * @param int|string $folder_id
 * @return list<string>
 */
function mel_lib_index_paths($folder_id)
{
    return array("/flashdata/movie_index/folder_files.$folder_id.json",
        "/persistfs/movie_index/folder_files.$folder_id.json");
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

// The index of a folder -> list of MelLibFile (load_index_for_folder of the
// shell: a record without hash or id is skipped, the first of a path kept);
// $info: 'N records, M files' or why there are none.
/**
 * @param int|string $folder_id
 * @param string $info
 * @return list<MelLibFile>
 */
function mel_lib_folder_index($folder_id, &$info)
{
    $info = 'no index';
    foreach (mel_lib_index_paths($folder_id) as $p)
    {
        list($doc, $why) = mel_lib_read($p);
        if ($doc === false)
        {
            if ($why !== 'no such file')
                $info = $why;
            continue;
        }
        $list = json_decode(mel_lib_quote_size($doc), true);
        if (!is_array($list))
        {
            $info = 'not JSON';
            return array();
        }
        $files = array();
        $seen = array();
        foreach ($list as $f)
        {
            if (!is_array($f) || !isset($f['path'], $f['hash'], $f['id']) || !is_string($f['path']) ||
                !is_scalar($f['hash']) || !$f['hash'] || !is_scalar($f['id']) || isset($seen[$f['path']]))
                continue;
            $seen[$f['path']] = 1;
            $files[] = array(
                'folder' => strval($folder_id),
                'path' => $f['path'],
                'mid' => strval($f['id']),
                // Strings in the index.
                's' => isset($f['s']) && is_numeric($f['s']) ? intval($f['s']) : -1,
                'e' => isset($f['e']) && is_numeric($f['e']) ? intval($f['e']) : -1,
                'ass' => isset($f['ass_type']) && is_numeric($f['ass_type']) ? intval($f['ass_type']) : 0,
                'hash' => strval($f['hash']),
                'size' => mel_num(isset($f['size']) ? $f['size'] : 0),
                // The media tags of the indexer, as is: "MKV,4K,23.976,DV,HEVC,16:9,Atmos,7.1".
                'tags' => isset($f['tags']) && is_string($f['tags']) ? $f['tags'] : '');
        }
        $info = count($list) . ' records, ' . count($files) . ' files';
        return $files;
    }
    return array();
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

// The signature of the indexes of the folders $ids.
/**
 * @param list<int|string> $ids
 * @return string
 */
function mel_lib_sig_files($ids)
{
    $s = '';
    foreach ($ids as $id)
    {
        $one = '';
        foreach (mel_lib_index_paths($id) as $p)
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
// folder changes the config itself). No movie_index_config.xml: an empty
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
    foreach ($ids as $id)
    {
        $info = '';
        $files = mel_lib_folder_index($id, $info);
        if (!$files)
            mel_log("library: folder $id: $info");
        $n += count($files);
        foreach ($files as $f)
        {
            if ($f['ass'] === 0 && $f['s'] < 0 && $f['e'] < 0)
                $by_mid[$f['mid']][] = $f;
        }
    }
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

// The rank of the resolution of a copy, the higher the better: by the media
// tags (4K > 2K > 1080 > 720, the order of MediaTags::$TAGS_ORDER of the
// shell), by the name only when the file has no tags; 0 for another one, -1
// for none.
/**
 * @param string $tags
 * @param string $q
 * @return int
 */
function mel_lib_res_rank($tags, $q)
{
    $ranks = array('4K' => 4, '2K' => 3, '1080' => 2, '720' => 1);
    if (trim($tags) !== '')
    {
        foreach (explode(',', $tags) as $t)
        {
            $t = trim($t);
            if (isset($ranks[$t]))
                return $ranks[$t];
        }
        return -1;
    }
    $by_name = array('4K' => 4, '1440p' => 3, '1080p' => 2, '720p' => 1);
    return isset($by_name[$q]) ? $by_name[$q] : ($q !== '' ? 0 : -1);
}

// usort of the copies: the resolution down, then the size down, then the path.
/**
 * @param MelLibCopy $a
 * @param MelLibCopy $b
 * @return int
 */
function mel_lib_cmp_copies($a, $b)
{
    $ra = mel_lib_res_rank($a['tags'], $a['q']);
    $rb = mel_lib_res_rank($b['tags'], $b['q']);
    if ($ra !== $rb)
        return $rb - $ra;
    if ($a['size'] != $b['size'])
        return $a['size'] < $b['size'] ? 1 : -1;
    return strcmp($a['path'], $b['path']);
}

// The copies of the movie $mid of the index: its files whose source is
// mounted now, the best first (mel_lib_cmp_copies). The mount list is read
// on every call, and only when the movie has files in the index.
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
        $res[] = $f + array(
            'url' => $mnt[$id] . mel_lib_rel_path($folder['path'], $f['path']),
            'q' => mel_lib_quality($f['path']),
            'key' => mel_lib_key($f['hash']));
    }
    usort($res, 'mel_lib_cmp_copies');
    return $res;
}
