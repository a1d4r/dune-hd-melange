<?php
// Shared by the plugin (php_server) and the settings page (cgi/settings.php,
// php-cgi as root): the version, the files of data_dir, the rules for the
// addresses and the mask of the log (the HTTP clients: net.php). Plain
// PHP 5.3: no firmware API, no logging here.
// Types in the PHPDoc (MelSettings and others): phpstan-types.neon of the repository.

define('MEL_VERSION', '0.70.0');
// HTTPS works only with the firmware CA bundle (curl of the plugin and the page).
define('MEL_CA_BUNDLE', '/firmware/certs/ca-bundle.crt');
// In data_dir: written by the settings page and by the plugin (the quick setup, the rows of
// the catalog, the source profiles of MENU of a list), whole each time (mel_settings_json).
define('MEL_SETTINGS_FILE', 'settings.json');
// Its format: "v" of the file the page writes; a file of v 1 (0.33) is read
// by the migration (mel_settings_parse), a newer one is refused by the import.
define('MEL_SETTINGS_V', 2);
// In data_dir: the token of the settings page; written by the plugin, and by the
// page only on the import of a backup that holds another one (0.51.0).
define('MEL_TOKEN_FILE', 'web_token');
// In data_dir: the id of this Dune for Lampac (mel_lp_uid); not in settings.json: the export of the
// settings would take it to another Dune.
define('MEL_LP_UID_FILE', 'lampac_uid');
// In data_dir: the sort of the stream lists (mel_sort_read); written by the plugin only.
define('MEL_SORT_FILE', 'list_sort');
// In data_dir: the order of the balancers of the online list (mel_lpv_qsort); written by the plugin only.
define('MEL_LP_SORT_FILE', 'lampac_sort');
// In data_dir: the main Debrid service (mel_debrid_main_read, 0.56.0); written by the
// plugin (MENU of a list) and by the settings page, not in settings.json.
define('MEL_DEBRID_MAIN_FILE', 'debrid_main');
// In data_dir also aio_conf_mig: the schema of each config of Melange (MEL_CONF_MIG_FILE
// of cgi/aio_conf.php, 0.60.0); written by the plugin and by the settings page.
define('MEL_URL_MAX', 2000);
// A bigger settings.json is not read (a real one is below 5 KB).
define('MEL_SETTINGS_MAX', 65536);
// trim() without \0: a NUL byte at an edge is a format error, not stripped.
define('MEL_TRIM', " \t\r\n\x0B");
// Path segments of the manifest base this long or longer are hidden in the
// log wherever they are (UUID and config password of a public instance).
define('MEL_MASK_SEGMENT', 16);

// AIOStreams manifest address as typed or stored -> the URL to use, or ''
// when it does not fit: http(s)://host[:port]/path/manifest.json, printable
// ASCII without ? # quotes <>; stremio:// is https://, the scheme in any case.
/**
 * @param mixed $s
 * @return string
 */
function mel_manifest_url($s)
{
    if (!is_string($s))
        return '';
    $s = preg_replace('~^stremio://~i', 'https://', trim($s, MEL_TRIM));
    if (strlen($s) > MEL_URL_MAX || !preg_match('~^[\x21-\x7e]+\z~', $s) || preg_match('~[?#"\'`<>\\\\]~', $s))
        return '';
    // The scheme in any case ("HTTP://" of a redirect), stored in lower case.
    if (!preg_match('~^((?i)https?)(://[A-Za-z0-9.\-]+(?::[0-9]{1,5})?(?:/[^/]*)*/manifest\.json)\z~', $s, $m))
        return '';
    return strtolower($m[1]) . $m[2];
}

// The manifest address -> its base, with the trailing slash ('' for '').
/**
 * @param string $url
 * @return string
 */
function mel_manifest_base($url)
{
    return $url !== '' ? substr($url, 0, -strlen('manifest.json')) : '';
}

// JacRed address: "http://host:9117"
// or "http://host:9117/?apikey=KEY", a key of 8+ printable ASCII characters
// without & and #; a key with %XX in it is taken as already encoded. '' or
// anything else (control bytes, non-ASCII) -> null.
// -> array('base' => without a slash, 'host', 'key' => as written,
// 'key_url' => for the URL).
/**
 * @param mixed $s
 * @return MelJacred|null
 */
function mel_jacred_conf($s)
{
    $url = is_string($s) ? trim(preg_replace('/^\xEF\xBB\xBF/', '', $s), MEL_TRIM) : '';
    if (strlen($url) > MEL_URL_MAX ||
        !preg_match('~^(https?://([A-Za-z0-9.\-]+)(?::[0-9]{1,5})?)/?(?:\?apikey=([\x21\x22\x24\x25\x27-\x7e]*))?\z~', $url, $m))
        return null;
    $key = isset($m[3]) ? $m[3] : '';
    // A short key would be masked all over the log.
    if ($key !== '' && strlen($key) < 8)
        return null;
    return array('base' => $m[1], 'host' => $m[2], 'key' => $key,
        'key_url' => preg_match('/%[0-9A-Fa-f]{2}/', $key) ? $key : rawurlencode($key));
}

// Built-in JacRed $id of mel_jacred_builtin() -> mel_jacred_conf() +
// 'builtin' => true, null for another id.
/**
 * @param string $id
 * @return MelJacred|null
 */
function mel_jacred_builtin_conf($id)
{
    $b = mel_jacred_builtin();
    return isset($b[$id]) ? array('base' => $b[$id][0], 'host' => $id, 'key' => $b[$id][1],
        'key_url' => rawurlencode($b[$id][1]), 'builtin' => true) : null;
}

// "Download to server" (0.30.0): at most this many servers (qBittorrent WebAPI,
// Transmission RPC since 0.47.0).
define('MEL_SERVERS_MAX', 5);

// Debrid services a server may stand for (its "cache"): id of
// streamData.service of AIOStreams => name.
/**
 * @return array<string, string>
 */
function mel_server_caches()
{
    return array('torbox' => 'TorBox', 'realdebrid' => 'Real-Debrid');
}

// The services the main Debrid is one of (0.56.0; the setting of the page, MENU
// of a list, data_dir/debrid_main): id of streamData.service of AIOStreams => name.
/**
 * @return array<string, string>
 */
function mel_debrid_mains()
{
    return array('realdebrid' => 'Real-Debrid', 'torbox' => 'TorBox');
}

// The main Debrid service (0.56.0): a file cached on both plays from it.
// data_dir/debrid_main of directory $dir: an id of mel_debrid_mains(), else ''
// (none, unreadable, anything else): the copy first in the reply plays.
/**
 * @param string $dir
 * @return string
 */
function mel_debrid_main_read($dir)
{
    $f = "$dir/" . MEL_DEBRID_MAIN_FILE;
    $v = $dir !== '' && is_file($f) ? trim((string) @file_get_contents($f, false, null, 0, 16), MEL_TRIM) : '';
    return $v !== '' && array_key_exists($v, mel_debrid_mains()) ? $v : '';
}

// Clients of a server (0.47.0): its type => name for the settings page.
/**
 * @return array<string, string>
 */
function mel_server_types()
{
    return array('qbt' => 'qBittorrent / rdt-client', 'tr' => 'Transmission');
}

// A server as typed or stored: array of strings name, url, user, pass,
// category, tags, cache, type -> the same keys checked, or null when the address does not
// fit. url: http(s)://host[:port][/path], port 1-65535, stored without a
// trailing slash, scheme and host in lower case; name: up to 40 characters,
// the host[:port] when empty; user, category, tags: up
// to 100 printable characters, pass: up to 200; tags: comma-separated,
// stored without spaces around each tag, empty ones and repeats; cache: a key of
// mel_server_caches() or ''; type: a key of mel_server_types() (0.47.0), else
// 'qbt' (qBittorrent WebAPI: qBittorrent, rdt-client). No control bytes, UTF-8 only.
/**
 * @param mixed $a
 * @return MelServer|null
 */
function mel_server_conf($a)
{
    if (!is_array($a))
        return null;
    $v = array();
    foreach (array('name' => 40, 'url' => 300, 'user' => 100, 'pass' => 200, 'category' => 100, 'tags' => 100,
        'cache' => 20) as $k => $max)
    {
        $s = isset($a[$k]) && is_string($a[$k]) ? $a[$k] : '';
        // The password as typed: spaces at its edges may be part of it.
        if ($k !== 'pass')
            $s = trim($s, MEL_TRIM);
        if (!preg_match('/^[^\x00-\x1F\x7F]{0,' . $max . '}\z/u', $s))
            return null;
        $v[$k] = $s;
    }
    if (!preg_match('#^(https?)://([A-Za-z0-9.\-]+)(?::([0-9]{1,5}))?((?:/[A-Za-z0-9._~\-]+)*)/?\z#i', $v['url'], $m) ||
        ($m[3] !== '' && (intval($m[3]) < 1 || intval($m[3]) > 65535)))
        return null;
    $tags = array();
    foreach (explode(',', $v['tags']) as $t)
    {
        $t = trim($t, MEL_TRIM);
        if ($t !== '' && !in_array($t, $tags, true))
            $tags[] = $t;
    }
    $v['tags'] = implode(',', $tags);
    $host = strtolower($m[2]) . ($m[3] !== '' ? ":$m[3]" : '');
    $v['url'] = strtolower($m[1]) . "://$host$m[4]";
    if ($v['name'] === '')
        $v['name'] = $host;
    $caches = mel_server_caches();
    if (!isset($caches[$v['cache']]))
        $v['cache'] = '';
    // None (stored before 0.47.0) or unknown: qBittorrent, as it was.
    $types = mel_server_types();
    $v['type'] = isset($a['type']) && is_string($a['type']) && isset($types[$a['type']]) ? $a['type'] : 'qbt';
    return $v;
}

// "Watch via TorrServer" (0.31.0): no address set -> the TorrServe app of this Dune.
define('MEL_TS_DEFAULT', 'http://127.0.0.1:8090');

// TorrServer address as typed or stored: [http://]host[:port][/] -> 'http://host:port'
// (port 8090 when none, as Online movies), '' for empty, null when it does
// not fit (https, a path, a query, a user).
/**
 * @param mixed $s
 * @return string|null
 */
function mel_ts_addr($s)
{
    if (!is_string($s))
        return null;
    $s = trim($s, MEL_TRIM);
    if ($s === '')
        return '';
    if (strlen($s) > 300 || !preg_match('~^(?:(?i)http://)?([A-Za-z0-9.\-]+)(?::([0-9]{1,5}))?/?\z~', $s, $m))
        return null;
    $port = isset($m[2]) ? intval($m[2]) : 8090;
    return $port >= 1 && $port <= 65535 ? 'http://' . strtolower($m[1]) . ":$port" : null;
}

// The stored address ('' for none) -> the TorrServer to use.
/**
 * @param string $stored
 * @return string
 */
function mel_ts_base($stored)
{
    return $stored !== '' ? $stored : MEL_TS_DEFAULT;
}

// A stored address of Lampac ("Онлайн") longer than this is not one.
define('MEL_LP_URL_MAX', 300);
// A token of the provider of Lampac longer than this is not one (Lampac 1.90.1 sets no limit).
define('MEL_LP_TOKEN_MAX', 200);

// The token of the provider of Lampac (accsdb of Lampac 1.90.1: its query token=) as typed or stored:
// trimmed, 1 to MEL_LP_TOKEN_MAX characters of [A-Za-z0-9_+.\-@=] (those ValidateIdentity of Lampac
// keeps) -> the token, '' for empty (none), null when it does not fit.
/**
 * @param mixed $s
 * @return string|null
 */
function mel_lp_token($s)
{
    if (!is_string($s))
        return null;
    $s = trim($s, MEL_TRIM);
    if ($s === '')
        return '';
    return preg_match('/^[A-Za-z0-9_+.\-@=]{1,' . MEL_LP_TOKEN_MAX . '}\z/', $s) ? $s : null;
}

// The uid of this Dune for Lampac kept in data dir $dir (MEL_LP_UID_FILE): 8 of [a-z0-9]; '' when there is none, it
// is a link or not one. Only reads: the settings page makes none (it runs as root), mel_lp_uid of online_play.php makes
// it on the first request as Lampa, reading it by this one.
/**
 * @param string $dir
 * @return string
 */
function mel_lp_uid_read($dir)
{
    $f = "$dir/" . MEL_LP_UID_FILE;
    clearstatcache();
    if ($dir === '' || is_link($f) || !is_file($f))
        return '';
    $u = trim((string) @file_get_contents($f, false, null, 0, 16), MEL_TRIM);
    return preg_match('/^[a-z0-9]{8}\z/', $u) ? $u : '';
}

// The address of Lampac as typed or stored: [http(s)://]host[:port][/path][/]
// -> 'scheme://host[:port][/path]' (http when no scheme; scheme and host in
// lower case, no trailing '/'), '' for empty (off), null when it does not fit
// (another scheme, a user, a query, a fragment, a bad port).
/**
 * @param mixed $s
 * @return string|null
 */
function mel_lp_addr($s)
{
    if (!is_string($s))
        return null;
    $s = trim($s, MEL_TRIM);
    if ($s === '')
        return '';
    if (strlen($s) > MEL_LP_URL_MAX ||
        !preg_match('~^(?:(https?)://)?([A-Za-z0-9](?:[A-Za-z0-9.\-]*[A-Za-z0-9])?)(?::([0-9]{1,5}))?((?:/[A-Za-z0-9._\~\-]+)*)/?\z~i',
        $s, $m))
        return null;
    // Groups not matched at the end are not in $m (PHP 5.3).
    list(, $scheme, $host, $port, $path) = array_pad($m, 5, '');
    if ($port !== '' && (intval($port) < 1 || intval($port) > 65535))
        return null;
    return ($scheme !== '' ? strtolower($scheme) : 'http') . '://' . strtolower($host) .
        ($port !== '' ? ':' . intval($port) : '') . $path;
}

// Login of TorrServer (0.45.0, Basic auth of its --httpauth): trimmed, up to
// 100 characters, no control characters and no ':' (Basic sends
// "login:password") -> the login ('' for none) or null.
/**
 * @param mixed $s
 * @return string|null
 */
function mel_ts_login($s)
{
    if (!is_string($s))
        return null;
    $s = trim($s, MEL_TRIM);
    return preg_match('/^[^\x00-\x1F\x7F:]{0,100}\z/u', $s) ? $s : null;
}

// Password of TorrServer as typed (its spaces too): up to 200 characters, no
// control characters -> the password ('' for none) or null.
/**
 * @param mixed $s
 * @return string|null
 */
function mel_ts_pass($s)
{
    return is_string($s) && preg_match('/^[^\x00-\x1F\x7F]{0,200}\z/u', $s) ? $s : null;
}

// Basic auth of TorrServer by a login and password checked (mel_ts_login,
// mel_ts_pass): "login:password", '' for no login.
/**
 * @param string $user
 * @param string $pass
 * @return string
 */
function mel_ts_auth($user, $pass)
{
    return $user !== '' ? "$user:$pass" : '';
}

// An address typed as [http://]login:password@host[:port] -> array(the
// address without them, login, password) as typed (unchecked; no ':' - an
// empty password); no '@', no login before it (:p@h) or a '/', '?', '#' there
// (a path, not a login: http://u:p@host/@x) -> array($s, null, null), a bad
// address. The last '@' ends the password: a host has none.
/**
 * @param string $s
 * @return array{string, string|null, string|null}
 */
function mel_ts_userinfo($s)
{
    $at = strrpos($s, '@');
    $pre = preg_match('~^http://~i', $s) ? 7 : 0;
    if ($at === false || $at <= $pre)
        return array($s, null, null);
    $ui = substr($s, $pre, $at - $pre);
    $c = strpos($ui, ':');
    $u = $c === false ? $ui : substr($ui, 0, $c);
    if (strpbrk($ui, '/?#') !== false || trim($u, MEL_TRIM) === '')
        return array($s, null, null);
    return array(substr($s, 0, $pre) . (string) substr($s, $at + 1), $u, $c === false ? '' : (string) substr($ui, $c + 1));
}

// Built-in JacRed of the settings page (0.34.0), in the order of the page:
// id (its host) => array(base, key). Public: neither is hidden in the log.
// jacred.stream answers 403 to a "curl/*" User-Agent and to a wrong key;
// jac.red answers 429 to a second request within ~0.5 s.
// The order is only that of the page: the default (and fallback) is MEL_JACRED_DEFAULT.
/**
 * @return array<string, array{string, string}>
 */
function mel_jacred_builtin()
{
    return array(
        // Melange's own backend (a VPS run by the author for Melange users).
        // If you found it, feel free to use it, but please don't abuse it.
        'jacred.kompotik.com' => array('https://jacred.kompotik.com', '5f72a2f4a5dc1bcc38ae00697f89c8c3'),
        'jacred.stream' => array('https://jacred.stream', 'pp'),
        'jr.maxvol.pro' => array('https://jr.maxvol.pro', ''),
        'jac.stull.xyz' => array('https://jac.stull.xyz', ''),
        'jac.red' => array('https://jac.red', ''));
}
// Chosen when the settings choose none (no file, 0.33 without one, a wrong
// choice); also the one asked when the chosen one does not answer.
define('MEL_JACRED_DEFAULT', 'jacred.kompotik.com');
define('MEL_JACRED_URL_MAX', 300);

// The own JacRed as typed or stored: array(url, key) -> the same keys
// checked, or null. url: http(s)://host[:port][/] of up to MEL_JACRED_URL_MAX
// characters, stored without the slash, scheme and host in lower case, port
// 1-65535; key: '' or 8-200 printable ASCII without space & # (as
// mel_jacred_conf; a shorter one would be masked all over the log).
/**
 * @param mixed $a
 * @return array{url: string, key: string}|null
 */
function mel_jacred_own($a)
{
    if (!is_array($a))
        return null;
    $url = isset($a['url']) && is_string($a['url']) ? trim($a['url'], MEL_TRIM) : '';
    $key = isset($a['key']) && is_string($a['key']) ? trim($a['key'], MEL_TRIM) : '';
    // 300 + "/?apikey=" + 200 stays within MEL_URL_MAX of mel_jacred_conf.
    if (strlen($url) > MEL_JACRED_URL_MAX || !preg_match('~^(https?)://([A-Za-z0-9.\-]+)(?::([0-9]{1,5}))?/?\z~i', $url, $m) ||
        (isset($m[3]) && (intval($m[3]) < 1 || intval($m[3]) > 65535)) ||
        ($key !== '' && !preg_match('~^[\x21\x22\x24\x25\x27-\x7e]{8,200}\z~', $key)))
        return null;
    return array('url' => strtolower("$m[1]://$m[2]") . (isset($m[3]) ? ":$m[3]" : ''), 'key' => $key);
}

// AIOStreams servers to choose from (0.34.0; the config is made on them from 0.35.0):
// base => it has a TMDB key of its own (else titles are not matched and
// series get fewer releases without a key of the user). The first one is the default.
/**
 * @return array<string, bool>
 */
function mel_aio_servers()
{
    return array(
        // Melange's own backend (a VPS run by the author for Melange users).
        // If you found it, feel free to use it, but please don't abuse it.
        'https://aiostreams.kompotik.com' => true,
        'https://aiostreams.12312023.xyz' => true,
        'https://aiostreamsfortheweebsstable.midnightignite.me' => false,
        'https://aio.atbphosting.com' => false,
        'https://aiostreams.stremio.ru' => false,
        'https://aiostreams-stable.forthewizards.uk' => false,
        'https://aiostreams.fortheweak.cloud' => false);
}
// The server of Melange in mel_aio_servers() (the badge "у Melange" of the page).
define('MEL_AIO_SERVER_MELANGE', 'https://aiostreams.kompotik.com');

// The template of the mode P2P "everywhere". The same value as the mode
// (MEL_MODE_P2P) by history (one template of P2P till 0.43): not the mode.
define('MEL_TPL_P2P', 'p2p');
// The template of the mode P2P "JacRed only" (0.44.0), its default.
define('MEL_TPL_P2P_JACRED', 'p2p_jacred');
// The value of the mode Debrid (aio_mode).
define('MEL_MODE_DEBRID', 'debrid');
// The value of the mode P2P (aio_mode).
define('MEL_MODE_P2P', 'p2p');

// Templates of the config melange makes (0.40.0): id => instanceIds of the
// presets of cgi/aio_template.json it has, in this order. The first one is
// the default of Debrid; the Debrid mode chooses one of them, the mode P2P
// (no Debrid service) one of mel_aio_tpls_p2p (Torrentio first, its rows win
// the dedup). All templates of a mode have the same JacRed preset and the
// same fields out of presets: mel_cgi_aio_sync patches a config of P2P with
// Magnetio by the template of the settings, whatever its own.
// Their JacRed: Torznab of AIOStreams "melange" (needs a Debrid service) or
// Magnetio "magnetio" (mel_conf_patch puts the chosen JacRed in either); P2P
// on the servers of mel_conf_p2p_torznab: Torznab in the place of Magnetio
// (so p2p_jacred there has the presets of jacred: mel_conf_tpl_of by the mode).
/**
 * @return array<string, list<string>>
 */
function mel_aio_tpls()
{
    return array('addons' => array('melange', 'comet', 'torz', 'mf', 'torrentio'), 'jacred' => array('melange'),
        MEL_TPL_P2P => array('torrentio', 'magnetio', 'torrentsdb', 'comet', 'torz'), MEL_TPL_P2P_JACRED => array('magnetio'));
}

// The templates of the mode P2P (aio_tpl_p2p of the settings), the rest of
// mel_aio_tpls are of Debrid. The first one is the default (as of Debrid);
// the order of the page: mel_cgi_tpls_p2p.
/**
 * @return list<string>
 */
function mel_aio_tpls_p2p()
{
    return array(MEL_TPL_P2P_JACRED, MEL_TPL_P2P);
}

// Modes of the config melange makes (aio_mode of the settings, the one in
// use, one for all servers; a server has a config of each at most): debrid
// - aio_tpl with the Debrid keys; p2p (MEL_MODE_P2P) - aio_tpl_p2p, the keys
// kept but never sent. The first one is the default.
/**
 * @return list<string>
 */
function mel_aio_modes()
{
    return array(MEL_MODE_DEBRID, MEL_MODE_P2P);
}

// $id is a template of the Debrid mode (aio_tpl).
/**
 * @param mixed $id
 * @return bool
 */
function mel_tpl_debrid($id)
{
    return is_string($id) && !mel_tpl_p2p($id) && array_key_exists($id, mel_aio_tpls());
}

// $id is a template of the mode P2P (aio_tpl_p2p).
/**
 * @param mixed $id
 * @return bool
 */
function mel_tpl_p2p($id)
{
    return in_array($id, mel_aio_tpls_p2p(), true);
}

// The template of a new config or a reset by the settings: the mode P2P ->
// aio_tpl_p2p, else aio_tpl.
/**
 * @param array{aio_mode: string, aio_tpl: string, aio_tpl_p2p: string} $s
 * @return string
 */
function mel_settings_tpl($s)
{
    return $s['aio_mode'] === MEL_MODE_P2P ? $s['aio_tpl_p2p'] : $s['aio_tpl'];
}

// The mode of a config of 0.42 or older (one per server; moved to the cell
// of its mode once, by mel_settings_read): by its template $tpl - one of
// P2P -> p2p, of the Debrid mode -> debrid; else ('custom', null) $mode, the one
// stored with it, none -> debrid (a config of P2P always had it stored).
/**
 * @param mixed $tpl
 * @param string|null $mode
 * @return string
 */
function mel_mode_of($tpl, $mode = null)
{
    if (mel_tpl_p2p($tpl))
        return MEL_MODE_P2P;
    if (mel_tpl_debrid($tpl))
        return MEL_MODE_DEBRID;
    return $mode !== null ? $mode : MEL_MODE_DEBRID;
}

// A config of AIOStreams made by the settings page (0.35.0), as stored in
// aio_confs of settings.json under its server $base (a key of
// mel_aio_servers()) and its mode (mel_aio_modes(): a server has a config
// of each mode at most): uuid, pass - the login and password of the config
// (24 hex, made by the page), manifest - its URL from the reply of the
// server (<base>/stremio/<uuid>/<encrypted password>/manifest.json), tmdb -
// the config had TMDB at its last change, set (0.35.1) - of rd, tb, tmdb
// the fields melange put in the config (null: not stored, a file of
// 0.35.0), tpl (0.40.0) - its template by its presets: a key of
// mel_aio_tpls(), 'custom' (changed in AIOStreams) or null (not known yet,
// a config of 0.39 or older). -> the same keys checked, or null.
/**
 * @param int|string $base
 * @param mixed $c
 * @return MelConf|null
 */
function mel_aio_conf($base, $c)
{
    $servers = mel_aio_servers();
    if ((!isset($servers[$base]) && mel_aio_own($base) !== $base) || !is_array($c) || !isset($c['uuid'], $c['pass'], $c['manifest']) ||
        !is_string($c['uuid']) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $c['uuid']) ||
        !is_string($c['pass']) || !preg_match('/^[0-9a-f]{24}\z/', $c['pass']) ||
        mel_manifest_url($c['manifest']) !== $c['manifest'] ||
        strpos($c['manifest'], "$base/stremio/{$c['uuid']}/") !== 0)
        return null;
    $tpl = isset($c['tpl']) && is_string($c['tpl']) && ($c['tpl'] === 'custom' ||
        array_key_exists($c['tpl'], mel_aio_tpls())) ? $c['tpl'] : null;
    return array('uuid' => $c['uuid'], 'pass' => $c['pass'], 'manifest' => $c['manifest'],
        'tmdb' => isset($c['tmdb']) && $c['tmdb'] === true, 'set' => isset($c['set']) && is_array($c['set']) ?
        array_values(array_intersect(array('rd', 'tb', 'tmdb'), $c['set'])) : null, 'tpl' => $tpl);
}

// The TMDB key melange puts in the config on the server $base: a server of
// the list with TMDB of its own hides the field on the page and never gets
// the key (a stale one would fail every change: 400); an own server always
// gets it.
/**
 * @param int|string $base
 * @param string $key
 * @return string
 */
function mel_tmdb_sent($base, $key)
{
    $servers = mel_aio_servers();
    return empty($servers[$base]) ? $key : '';
}

// The own AIOStreams server (0.35.1) as typed or stored: http(s)://host[:port][/]
// -> 'scheme://host[:port]' in lower case, port 1-65535; '' when it does not
// fit (a path, a query, a user). A LAN address is fine. The host: 2+
// characters, a letter or digit at both ends (the log masks it as a word).
/**
 * @param mixed $s
 * @return string
 */
function mel_aio_own($s)
{
    $s = is_string($s) ? trim($s, MEL_TRIM) : '';
    if (strlen($s) > 300 || !preg_match('~^(https?)://([A-Za-z0-9][A-Za-z0-9.\-]*[A-Za-z0-9])(?::([0-9]{1,5}))?/?\z~i', $s, $m) ||
        (isset($m[3]) && (intval($m[3]) < 1 || intval($m[3]) > 65535)))
        return '';
    return strtolower("$m[1]://$m[2]") . (isset($m[3]) ? ':' . intval($m[3]) : '');
}

// The server melange makes the config on (aio_server of the settings): a
// server of the list or another one -> its base, the key of its configs in
// aio_confs ('' for another one without an address).
/**
 * @param array{aio_server: string, aio_own_url: string} $s
 * @return string
 */
function mel_settings_base($s)
{
    return $s['aio_server'] === 'own' ? $s['aio_own_url'] : $s['aio_server'];
}

// The config made in use: that of the chosen server in the mode aio_mode, or null.
/**
 * @param MelSettings $s
 * @return MelConf|null
 */
function mel_settings_conf($s)
{
    $b = mel_settings_base($s);
    return $b !== '' && isset($s['aio_confs'][$b][$s['aio_mode']]) ? $s['aio_confs'][$b][$s['aio_mode']] : null;
}

// The manifest the plugin uses: the own config (source own), else the
// config made in use (mel_settings_conf; '' for none).
/**
 * @param MelSettings $s
 * @return string
 */
function mel_settings_manifest($s)
{
    if ($s['source'] === 'own')
        return $s['manifest_url'];
    $c = mel_settings_conf($s);
    return $c ? $c['manifest'] : '';
}

// Keys of the services as typed or stored -> the key, or '' when it does not
// fit. Real-Debrid: 52 upper-case letters and digits, TorBox: a UUID, TMDB
// v3: 32 hex - checked loosely, the service decides.
/**
 * @param string $what
 * @param mixed $s
 * @return string
 */
function mel_key_ok($what, $s)
{
    if (!is_string($s))
        return '';
    $s = trim($s, MEL_TRIM);
    $re = array('rd' => '~^[A-Za-z0-9]{20,100}\z~', 'tb' => '~^[A-Za-z0-9\-]{20,100}\z~',
        'tmdb' => '~^[A-Za-z0-9]{20,64}\z~');
    return isset($re[$what]) && preg_match($re[$what], $s) ? $s : '';
}

// jacred_url of 0.33 and older -> array(jacred, own url, own key): a
// built-in one if its host is one of them, else the own one (the key from
// ?apikey=); empty or wrong -> MEL_JACRED_DEFAULT.
/**
 * @param mixed $url
 * @return array{string, string, string}
 */
function mel_jacred_migrate($url)
{
    $c = mel_jacred_conf($url);
    $b = mel_jacred_builtin();
    if ($c && isset($b[strtolower($c['host'])]))
        return array(strtolower($c['host']), '', '');
    $own = $c ? mel_jacred_own(array('url' => $c['base'], 'key' => $c['key'])) : null;
    return $own ? array('own', $own['url'], $own['key']) : array(MEL_JACRED_DEFAULT, '', '');
}

// The own JacRed of the address $url and the key $key (as mel_jacred_own() stores them; $url '' - none)
// -> mel_jacred_conf() + 'builtin' => false, or null.
/**
 * @param string $url
 * @param string $key
 * @return MelJacred|null
 */
function mel_jacred_own_conf($url, $key)
{
    $c = $url !== '' ? mel_jacred_conf($url . ($key !== '' ? '/?apikey=' . $key : '')) : null;
    return $c ? $c + array('builtin' => false) : null;
}

// The own JacRed of the settings and of the source profiles (0.58.0), chosen or not, for the mask of the log:
// a list of mel_jacred_own_conf(), each once.
/**
 * @param MelSettings $s
 * @return list<MelJacred>
 */
function mel_jacred_own_confs($s)
{
    $list = array();
    $seen = array();
    foreach (array_merge(array($s), $s['profiles']) as $x)
    {
        $c = isset($x['jacred_own_url']) ? mel_jacred_own_conf($x['jacred_own_url'], $x['jacred_own_key']) : null;
        if ($c && !isset($seen[$c['base'] . ' ' . $c['key']]))
        {
            $seen[$c['base'] . ' ' . $c['key']] = true;
            $list[] = $c;
        }
    }
    return $list;
}

// The JacRed to ask, in this order: the chosen one, then MEL_JACRED_DEFAULT
// if that is another. -> list of mel_jacred_conf() + 'builtin'.
/**
 * @param MelSettings $s
 * @return list<MelJacred>
 */
function mel_jacred_list($s)
{
    $own = $s['jacred'] === 'own' ? mel_jacred_own_conf($s['jacred_own_url'], $s['jacred_own_key']) : null;
    $list = $own ? array($own) : array();
    foreach (array_unique(array($s['jacred'], MEL_JACRED_DEFAULT)) as $id)
    {
        $c = mel_jacred_builtin_conf($id);
        if ($c)
            $list[] = $c;
    }
    return $list;
}

// The secrets of the settings besides the manifest and JacRed: server
// passwords, keys of Real-Debrid, TorBox, TMDB, the password of TorrServer, passwords of the configs
// and the long segments of their manifests, as of the manifest in use
// (mel_mask_secrets); so the long segments of the own config, and of the own
// configs and of the configs of the profiles, in use or not (0.58.0); the keys of
// Debrid of every profile and the token of every Lampac (as typed and URL-encoded
// both ways: + @ = change), not only of the one in use (v2).
/**
 * @param MelSettings $s
 * @return list<string>
 */
function mel_settings_secrets($s)
{
    $pw = mel_server_secrets($s['servers']);
    foreach (array('rd_key', 'tb_key', 'tmdb_key', 'ts_pass', 'lampac_token') as $k)
    {
        if ($s[$k] !== '')
            $pw[] = $s[$k];
    }
    $urls = $s['manifest_url'] !== '' ? array($s['manifest_url']) : array();
    foreach ($s['aio_confs'] as $cs)
    {
        foreach ($cs as $c)
        {
            $pw[] = $c['pass'];
            $urls[] = $c['manifest'];
        }
    }
    foreach ($s['profiles'] as $p)
    {
        if (isset($p['url']))
            $urls[] = $p['url'];
        elseif (isset($p['lampac_url']))
        {
            if ($p['lampac_token'] !== '')
                $pw = array_merge($pw, array_unique(array($p['lampac_token'], rawurlencode($p['lampac_token']),
                    urlencode($p['lampac_token']))));
        }
        else
        {
            foreach (array('rd_key', 'tb_key') as $k)
            {
                if ($p[$k] !== '')
                    $pw[] = $p[$k];
            }
            if ($p['conf'])
            {
                $pw[] = $p['conf']['pass'];
                $urls[] = $p['conf']['manifest'];
            }
        }
    }
    $pw = array_values(array_unique($pw));
    foreach (array_unique($urls) as $url)
    {
        foreach (explode('/', preg_replace('~^[^:]*://[^/]*~', '', mel_manifest_base($url))) as $seg)
        {
            if (strlen($seg) >= MEL_MASK_SEGMENT && !in_array($seg, $pw, true))
                $pw[] = $seg;
        }
    }
    return $pw;
}

// Addresses of the settings to hide in the log, scheme://host[:port] =>
// tag: the own TorrServer "<ts>" (not the default one on the Dune), the
// servers of "Download to server" "<server>", the own AIOStreams servers of the
// configs and of the profiles and the hosts of the own configs of the profiles,
// in use or not, "<aio>" (0.58.0; not the servers of the list: public), Lampac
// ("Онлайн") "<lampac>", its scheme://host[:port], of every profile of Lampac (v2).
/**
 * @param MelSettings $s
 * @return array<string, string>
 */
function mel_settings_hosts($s)
{
    $h = array();
    if ($s['ts_url'] !== '' && $s['ts_url'] !== MEL_TS_DEFAULT)
        $h[$s['ts_url']] = '<ts>';
    foreach ($s['servers'] as $srv)
    {
        $p = parse_url($srv['url']);
        $u = strtolower($p['scheme'] . '://' . $p['host']) . (isset($p['port']) ? ':' . $p['port'] : '');
        if (!isset($h[$u]))
            $h[$u] = '<server>';
    }
    $own = array_keys($s['aio_confs']);
    $lps = $s['lampac_url'] !== '' ? array($s['lampac_url']) : array();
    foreach ($s['profiles'] as $p)
    {
        if (isset($p['lampac_url']))
            $lps[] = $p['lampac_url'];
        // The address of the host of an own config: as mel_aio_own makes it.
        else
            $own[] = isset($p['url']) ? mel_aio_own(strval(preg_replace('~^([^:]*://[^/]*).*\z~s', '$1', $p['url']))) :
                $p['base'];
    }
    $servers = mel_aio_servers();
    foreach ($own as $u)
    {
        if ($u !== '' && !isset($servers[$u]) && !isset($h[$u]))
            $h[$u] = '<aio>';
    }
    foreach ($lps as $u)
        $h[strval(preg_replace('~^([^:]*://[^/]*).*\z~s', '$1', $u))] = '<lampac>';
    return $h;
}

// "Без торрентов" in effect: not with the mode "Торренты" of a made config,
// where the value is only kept. $s: of mel_settings_read or the values to save.
/**
 * @param array{no_torrents: bool, source: string, aio_mode: string} $s
 * @return bool
 */
function mel_no_torrents($s)
{
    return $s['no_torrents'] && !($s['source'] === 'made' && $s['aio_mode'] === MEL_MODE_P2P);
}

// --- Source profiles (0.58.0): source_profiles.php.
require_once dirname(__FILE__) . '/source_profiles.php';

// <data_dir>/settings.json -> array('source' => 'made' (melange makes the
// config on aio_server) or 'own' (the own config of manifest_url),
// 'manifest_url' => ..., 'rd_key', 'tb_key',
// 'jacred' => a key of mel_jacred_builtin() or 'own', 'jacred_own_url',
// 'jacred_own_key' => mel_jacred_own() or '' (kept when a built-in one is
// chosen), 'aio_server' => a key of mel_aio_servers() or 'own' (another
// server: aio_own_url), 'aio_own_url' => mel_aio_own(), 'aio_mode' => one of
// mel_aio_modes(), 'aio_tpl' => mel_tpl_debrid(), 'aio_tpl_p2p' =>
// mel_tpl_p2p() (0.44.0; none - the first of mel_aio_tpls_p2p, a config
// there keeps its template; all three one for all servers); everything kept
// whatever is chosen, 'tmdb_key',
// 'servers' => list of mel_server_conf(), 'ts_url' => mel_ts_addr() or '',
// 'ts_user', 'ts_pass' => mel_ts_login(), mel_ts_pass() (0.45.0; both set
// or both '', else both ''), 'ts_save' => bool, "Добавить и играть": the
// add of TorrServer keeps the release in its list (0.45.0; not a bool - false),
// 'markers' => bool, "Пропуск заставки и титров": the skip buttons in the player
// (segments.php); off: none, and the episodes of a pack in the playlist are
// ready URLs (0.49.1; not a bool - true),
// 'markers_exact' => bool, "Только точные" of it: only the markers the server
// matched to the length of the file (mel_seg_markers); nothing with markers
// off (not a bool - false),
// 'source_errors' => bool, "Ошибки источников": the reasons of the error streams
// on the list screen and in the dialogs (0.49.1; not a bool - false),
// 'no_color_keys' => bool, "Пульт без цветных кнопок": the sort and the details of a release
// in MENU of the list too (mel_gc_keys_in_menu; 0.61.0; not a bool - false),
// 'cat_rating' => bool, "Рейтинг IMDb на постерах": the IMDb rating in the corner of the
// posters of the rows of the catalog (mel_catv_title_item; not a bool - false),
// 'cat_logo' => bool, "Логотип фильма вместо названия": the card background of
// the server with the logo in the panel of the catalog (mel_catv_card; 0.55.0;
// not a bool - true),
// 'cat_age' => int|null, the age of "Фильтр каталога": tiles of the catalog of an older age (mel_cat_age_of) not
// shown (mel_ht_off; 0.65.0): 16, 12, 6, 0; null - all (another value - null),
// 'cat_imdb_movie', 'cat_imdb_series' => int|null, "Рейтинг IMDb: фильмы", "...: сериалы" of "Фильтр каталога": tiles of
// that type of a lower IMDb rating not shown (mel_ht_off; 0.70.0), in tenths: 50, 55 ... 80 (65 - from 6.5); null - any
// (another value - null),
// 'no_torrents' => bool, "Без торрентов" (0.49.0; not a bool - false; what it
// does: mel_no_torrents),
// 'aio_confs' => base of a server => mode => mel_aio_conf(); a config of
// 0.42 or older (one per server, base => config) is read into the cell of
// its mode (mel_mode_of), written back so by the next save),
// 'src_profiles' => bool, "Профили источников" of the page of before v2 (0.58.0; not a bool - false; the plugin
// does not read it: MENU of a list has the profiles always), 'profiles' => list of mel_profile_ok() (v2: a source
// each, all its settings in it and an id: Debrid, Торренты, an own config, Lampac; one of before the editor of the
// page takes the JacRed, the template and the config of the settings, of before v2 the keys of Debrid), at most
// MEL_PROFILES_MAX, unique names (the first one kept), 'src_main' => the id of the main profile (v2; of none - ''),
// 'lampac_url' => the address of Lampac, mel_lp_addr() or '' (off), 'lampac_token' => the token of its provider,
// mel_lp_token() or '' (none), 'lampac_as_lampa' => bool, "Представляться приложением Lampa" (not a bool - false),
// 'lampac_sources' => the balancers Lampac has found and the choice of them, mel_lp_srcs_ok(); the source, the
// keys of Debrid and lampac_* at the top are those of the main profile (of v2 a copy, mel_settings_top: a version of
// before reads them; a file of before v2 without src_main - the source in use of it and Lampac go into the profiles,
// mel_settings_migrate),
// each checked by the rules above; absent or wrong -> as not set ('', the
// default server, MEL_JACRED_DEFAULT; 'own' without a good address too; a
// wrong server is left out). A file of 0.33 (v 1, jacred_url) is read as v 2
// (mel_jacred_migrate); the plugin never writes it. Never through a symlink
// (the page runs as root). 'mig_lost' => what the migration of a file of before v2 could not make a profile of (the
// profiles full: 'the source in use', 'Lampac'; the reader logs it), never written. 'saved_once' => the file has saved_at (the page saved
// it, cgi/settings.php: the first run till then); never written back
// (mel_cgi_store drops it). $why: why a settings.json there was not read
// (mel_settings_unread, 'read failed', 'bad JSON', 'data dir not
// accessible'); '' when it was or there is none - the caller logs it.
/**
 * @param string $dir
 * @param string|null $why
 * @param-out string $why
 * @return MelSettings
 */
function mel_settings_read($dir, &$why = null)
{
    $why = '';
    $r = mel_settings_parse(array());
    $f = "$dir/" . MEL_SETTINGS_FILE;
    clearstatcache();
    if ($dir === '')
        return $r;
    if (!is_link($f) && !file_exists($f))
    {
        // None in a dir this process may enter: a new install, nothing to say.
        if (file_exists($dir) && (!is_dir($dir) || !is_executable($dir)))
            $why = 'data dir not accessible ' . mel_settings_owner($dir);
        return $r;
    }
    $why = mel_settings_unread($f);
    if ($why !== '')
        return $r;
    // @: quiet in the CGI; php_server's hd_error_handler ignores it and logs the warning.
    $raw = @file_get_contents($f, false, null, 0, MEL_SETTINGS_MAX);
    $j = json_decode((string) $raw, true);
    if (!is_array($j))
    {
        // is_readable is access(R_OK), SELinux included: open denied after it
        // passed - a race or the SELinux open permission (hypothesis).
        $why = $raw === false ? 'read failed ' . mel_settings_owner($f) : 'bad JSON';
        return $r;
    }
    return mel_settings_parse($j);
}

// The lowest IMDb ratings of "Фильтр каталога" (cat_imdb_movie, cat_imdb_series), in tenths: from 5 to 8 by 0.5.
/**
 * @return list<int>
 */
function mel_imdb_mins()
{
    return array(50, 55, 60, 65, 70, 75, 80);
}

// The settings of the decoded JSON of a settings.json (array() - the defaults)
// by the rules of mel_settings_read; also a file imported by the page
// (cgi/settings.php a=import).
/**
 * @param array<mixed> $j
 * @return MelSettings
 */
function mel_settings_parse($j)
{
    $servers = array_keys(mel_aio_servers());
    $tpls = mel_aio_tpls();
    $tpls_p2p = mel_aio_tpls_p2p();
    $r = array('source' => 'made', 'manifest_url' => '', 'rd_key' => '', 'tb_key' => '', 'jacred' => MEL_JACRED_DEFAULT,
        'jacred_own_url' => '', 'jacred_own_key' => '', 'aio_server' => $servers[0], 'aio_own_url' => '',
        'aio_mode' => MEL_MODE_DEBRID, 'aio_tpl' => key($tpls), 'aio_tpl_p2p' => $tpls_p2p[0], 'tmdb_key' => '',
        'no_torrents' => false, 'servers' => array(), 'ts_url' => '', 'ts_user' => '', 'ts_pass' => '', 'ts_save' => false,
        'markers' => true, 'markers_exact' => false, 'source_errors' => false, 'no_color_keys' => false, 'cat_rating' => false, 'cat_logo' => true, 'cat_age' => null, 'cat_imdb_movie' => null,
        'cat_imdb_series' => null, 'cat_rows' => null) + mel_lp_keys() + array('aio_confs' => array(), 'src_profiles' => false,
        'profiles' => array(), 'src_main' => '', 'saved_once' => false, 'mig_lost' => array());
    $r['saved_once'] = isset($j['saved_at']);
    if (isset($j['manifest_url']))
        $r['manifest_url'] = mel_manifest_url($j['manifest_url']);
    foreach (array('rd_key' => 'rd', 'tb_key' => 'tb', 'tmdb_key' => 'tmdb') as $k => $what)
    {
        if (isset($j[$k]))
            $r[$k] = mel_key_ok($what, $j[$k]);
    }
    $as = isset($j['aio_server']) && is_string($j['aio_server']) ? $j['aio_server'] : '';
    if (in_array($as, array_merge($servers, array('own')), true))
        $r['aio_server'] = $as;
    if (isset($j['source']) && in_array($j['source'], array('made', 'own'), true))
        $r['source'] = $j['source'];
    // No source: 0.35.1 chose the own config by aio_server "manifest"; up to
    // 0.35.0 (no aio_own_url either) a manifest_url set was used whatever the server.
    elseif ($as === 'manifest' || ($r['manifest_url'] !== '' && !array_key_exists('aio_own_url', $j)))
        $r['source'] = 'own';
    if (isset($j['aio_own_url']))
        $r['aio_own_url'] = mel_aio_own($j['aio_own_url']);
    if (isset($j['aio_mode']) && in_array($j['aio_mode'], mel_aio_modes(), true))
        $r['aio_mode'] = $j['aio_mode'];
    if (isset($j['aio_tpl']) && mel_tpl_debrid($j['aio_tpl']))
        $r['aio_tpl'] = $j['aio_tpl'];
    if (isset($j['aio_tpl_p2p']) && mel_tpl_p2p($j['aio_tpl_p2p']))
        $r['aio_tpl_p2p'] = $j['aio_tpl_p2p'];
    if (!isset($j['v']) || $j['v'] !== MEL_SETTINGS_V)
        list($r['jacred'], $r['jacred_own_url'], $r['jacred_own_key']) =
            mel_jacred_migrate(isset($j['jacred_url']) ? $j['jacred_url'] : '');
    else
    {
        $own = mel_jacred_own(array('url' => isset($j['jacred_own_url']) ? $j['jacred_own_url'] : '',
            'key' => isset($j['jacred_own_key']) ? $j['jacred_own_key'] : ''));
        if ($own)
            list($r['jacred_own_url'], $r['jacred_own_key']) = array($own['url'], $own['key']);
        $b = mel_jacred_builtin();
        if (isset($j['jacred']) && is_string($j['jacred']) && (isset($b[$j['jacred']]) || ($j['jacred'] === 'own' && $own)))
            $r['jacred'] = $j['jacred'];
    }
    if (isset($j['servers']) && is_array($j['servers']))
    {
        foreach (array_values($j['servers']) as $s)
        {
            $s = mel_server_conf($s);
            if ($s && count($r['servers']) < MEL_SERVERS_MAX)
                $r['servers'][] = $s;
        }
    }
    if (isset($j['ts_url']) && is_string(mel_ts_addr($j['ts_url'])))
        $r['ts_url'] = mel_ts_addr($j['ts_url']);
    $tu = mel_ts_login(isset($j['ts_user']) ? $j['ts_user'] : '');
    $tp = mel_ts_pass(isset($j['ts_pass']) ? $j['ts_pass'] : '');
    if (is_string($tu) && is_string($tp) && ($tu === '') === ($tp === ''))
        list($r['ts_user'], $r['ts_pass']) = array($tu, $tp);
    if (isset($j['ts_save']) && is_bool($j['ts_save']))
        $r['ts_save'] = $j['ts_save'];
    if (isset($j['markers']) && is_bool($j['markers']))
        $r['markers'] = $j['markers'];
    if (isset($j['markers_exact']) && is_bool($j['markers_exact']))
        $r['markers_exact'] = $j['markers_exact'];
    if (isset($j['source_errors']) && is_bool($j['source_errors']))
        $r['source_errors'] = $j['source_errors'];
    if (isset($j['no_color_keys']) && is_bool($j['no_color_keys']))
        $r['no_color_keys'] = $j['no_color_keys'];
    if (isset($j['cat_rating']) && is_bool($j['cat_rating']))
        $r['cat_rating'] = $j['cat_rating'];
    if (isset($j['cat_logo']) && is_bool($j['cat_logo']))
        $r['cat_logo'] = $j['cat_logo'];
    if (isset($j['cat_age']) && in_array($j['cat_age'], array(16, 12, 6, 0), true))
        $r['cat_age'] = $j['cat_age'];
    foreach (array('cat_imdb_movie', 'cat_imdb_series') as $k)
    {
        if (isset($j[$k]) && in_array($j[$k], mel_imdb_mins(), true))
            $r[$k] = $j[$k];
    }
    if (isset($j['no_torrents']) && is_bool($j['no_torrents']))
        $r['no_torrents'] = $j['no_torrents'];
    if (isset($j['cat_rows']))
        $r['cat_rows'] = mel_cat_rows_ok($j['cat_rows']);
    if (isset($j['src_profiles']) && is_bool($j['src_profiles']))
        $r['src_profiles'] = $j['src_profiles'];
    $r = array_merge($r, mel_lp_fields($j));
    if (isset($j['aio_confs']) && is_array($j['aio_confs']))
    {
        foreach ($j['aio_confs'] as $b => $cs)
        {
            if (!is_array($cs))
                continue;
            // One config of 0.42 or older: in the cell of its mode.
            if (isset($cs['uuid']))
                $cs = array(mel_mode_of(isset($cs['tpl']) ? $cs['tpl'] : null, isset($cs['mode']) &&
                    in_array($cs['mode'], mel_aio_modes(), true) ? $cs['mode'] : null) => $cs);
            foreach (mel_aio_modes() as $m)
            {
                $c = isset($cs[$m]) ? mel_aio_conf($b, $cs[$m]) : null;
                // 0.35.0 stored no marks: the keys melange has now are taken as its own.
                if ($c && $c['set'] === null)
                    $c['set'] = array_keys(array_filter(array('rd' => $r['rd_key'] !== '', 'tb' => $r['tb_key'] !== '',
                        'tmdb' => mel_tmdb_sent($b, $r['tmdb_key']) !== '')));
                if ($c)
                    $r['aio_confs'][$b][$m] = $c;
            }
        }
    }
    // After aio_confs, the JacRed and the keys: a profile of before takes them (mel_profile_ok).
    if (isset($j['profiles']) && is_array($j['profiles']))
        $r['profiles'] = mel_profiles_ok($j['profiles'], $r);
    // No src_main: a file of before v2 (spec v2 §4).
    if (!array_key_exists('src_main', $j))
        $r = mel_settings_migrate($r);
    elseif (mel_profile_id_ok($j['src_main']) && mel_profile_index($r, $j['src_main']) >= 0)
        $r['src_main'] = $j['src_main'];
    // The main one by its id always: none or of no profile - the first one.
    $m = mel_profile_main($r);
    $r['src_main'] = $m >= 0 ? $r['profiles'][$m]['id'] : '';
    return $r;
}

// cat_rows of settings.json or the form -> the rows chosen: keys of rows (mel_cat_row
// of catalog.php), in order, unique; what can not be a key goes. null: not a list,
// or nothing left, as nothing chosen.
/**
 * @param mixed $v
 * @return list<string>|null
 */
function mel_cat_rows_ok($v)
{
    $keys = array();
    foreach (is_array($v) ? $v : array() as $k)
    {
        if (is_string($k) && preg_match('~^(?:movie|series|anime)/[\x21-\x7e]{1,200}\z~', $k))
            $keys[$k] = true;
    }
    return $keys ? array_keys($keys) : null;
}

// Why the settings.json at $f is not read, '' if it is: not readable - with
// its owner and mode and the uid of this process (the page writes it as
// root and gives it away).
/**
 * @param string $f
 * @return string
 */
function mel_settings_unread($f)
{
    if (is_link($f))
        return 'a symlink';
    if (!is_file($f))
        return 'not a file';
    if (!is_readable($f))
        return 'not readable ' . mel_settings_owner($f);
    if (filesize($f) > MEL_SETTINGS_MAX)
        return 'too big (' . filesize($f) . ' bytes)';
    return '';
}

// The JSON of settings.json v MEL_SETTINGS_V of $data (keys of
// mel_settings_read, its order; not saved_once: read only) with saved_at
// now, the main profile copied to the top (mel_settings_top); '' if json_encode
// failed. Written by the page (mel_cgi_store), its export (a=export) and the
// plugin (mel_settings_write: the quick setup, the rows, the balancers).
/**
 * @param array<string, mixed> $data
 * @return string
 */
function mel_settings_json($data)
{
    unset($data['saved_once'], $data['mig_lost']);
    $data = array_merge(array('v' => MEL_SETTINGS_V), mel_settings_top($data), array('saved_at' => time()));
    $json = json_encode($data);
    // PHP 5.3 turns a value that is not UTF-8 into null, not an error.
    return is_string($json) && json_decode($json, true) === $data ? $json : '';
}

// "(uid 1000, gid 1000, 0600; process uid 1000)" of an existing $f.
/**
 * @param string $f
 * @return string
 */
function mel_settings_owner($f)
{
    return sprintf('(uid %d, gid %d, %04o; process uid %s)', fileowner($f), filegroup($f), fileperms($f) & 07777,
        function_exists('posix_getuid') ? posix_getuid() : '?');
}

// Passwords of the servers to hide in the log (mel_mask_secrets).
/**
 * @param list<MelServer> $servers
 * @return list<string>
 */
function mel_server_secrets($servers)
{
    $pw = array();
    foreach ($servers as $s)
        $pw[] = $s['pass'];
    return $pw;
}

// 32 hex characters, as the plugin makes it.
/**
 * @param mixed $t
 * @return bool
 */
function mel_token_ok($t)
{
    return is_string($t) && preg_match('/^[0-9a-f]{32}\z/', $t) === 1;
}

// The address $aio (scheme://host[:port]) and its host in $s -> $tag.
/**
 * @param string $s
 * @param string $aio
 * @param string $tag
 * @return string
 */
function mel_mask_host($s, $aio, $tag)
{
    // Only as a whole host: not "aio" of "aio sync", not the start of
    // "aiostreams.example" (a host "aiostreams"). In any case: a host of a link
    // is kept as typed (mel_manifest_url), its key in lower case (mel_aio_own).
    $end = '(?![A-Za-z0-9\\-]|\\.[A-Za-z0-9])';
    $hp = preg_replace('~^[^:]*://~', '', $aio);
    $h = preg_replace('~:[0-9]+\z~', '', $hp);
    $hq = preg_quote($h, '~');
    // Its own port only as a whole ("nas:30000" keeps ":30000").
    $pp = $hp !== $h ? '(?:' . preg_quote(substr($hp, strlen($h)), '~') . '(?![0-9]))?' : '(?::[0-9]+)?';
    $s = preg_replace('~(?:' . preg_quote($aio, '~') . '|' . preg_quote(str_replace('/', '\\/', $aio), '~') . ')' . $end . '~i',
        $tag, $s);
    // The host in a URL or after a user; else before a port; a dotted one
    // (an IP, a domain) also alone.
    $s = preg_replace('~(?<=//|\\\\/\\\\/|@)' . $hq . $pp . $end . '~i', $tag, $s);
    $s = preg_replace('~(?<![A-Za-z0-9.\\-])' . $hq . (strpos($h, '.') !== false ? '' : '(?=:[0-9])') . $pp . $end . '~i',
        $tag, $s);
    // Alone in curl errors: "resolve host: nas", "connect to nas port 3000",
    // "target host name 'nas'".
    return preg_replace('~(?:(?<=host: |host |host name \')' . $hq . '|(?<![A-Za-z0-9.\\-])' . $hq . '(?= port [0-9]))' . $end . '~i',
        $tag, $s);
}

// A log line with the secrets hidden: the manifest base ($base, '' when not
// set) and each of its long path segments, the key of the own JacRed as
// written, URL-encoded (both ways) and JSON-escaped, and its address as
// "<jacred>" ($jrs: mel_jacred_own_confs() or confs of mel_jacred_conf();
// built-in ones are public and skipped),
// playback tokens, StremThru and Comet configs (base64 JSON), UUIDs, the
// secrets $pw of 4+ bytes (server passwords, keys of the services; a
// shorter one would garble the log and never goes there anyway).
// JSON-escaped slashes ("\/") are covered too. $aio: the own AIOStreams
// server (mel_aio_own(), '' for none; a LAN or NAS address) -> "<aio>";
// $hosts: more such addresses, as mel_settings_hosts().
/**
 * @param string $s
 * @param string $base
 * @param list<MelJacred> $jrs
 * @param list<string> $pw
 * @param string $aio
 * @param array<string, string> $hosts
 * @return string
 */
function mel_mask_secrets($s, $base, $jrs, $pw = array(), $aio = '', $hosts = array())
{
    if ($base !== '')
    {
        $s = str_replace(array($base, str_replace('/', '\\/', $base)), '<manifest>/', $s);
        foreach (explode('/', preg_replace('~^[^:]*://[^/]*~', '', $base)) as $seg)
        {
            if (strlen($seg) >= MEL_MASK_SEGMENT)
                $s = str_replace($seg, '***', $s);
        }
    }
    foreach ($jrs as $jr)
    {
        if (!empty($jr['builtin']))
            continue;
        if ($jr['key'] !== '')
            $s = str_replace(array($jr['key'], rawurlencode($jr['key']), urlencode($jr['key']),
                substr(json_encode($jr['key']), 1, -1)), '***', $s);
        if ($jr['host'] !== '')
            $s = preg_replace('~(?<![A-Za-z0-9.\\-])' . preg_quote($jr['host'], '~') . '(?![A-Za-z0-9\\-])~', '<jacred>',
                str_replace($jr['base'], '<jacred>', $s));
    }
    if ($aio !== '')
        $s = mel_mask_host($s, $aio, '<aio>');
    foreach ($hosts as $u => $tag)
        $s = mel_mask_host($s, $u, $tag);
    $s = preg_replace('~(\\\\?/(?:playback|torz)\\\\?/)[^/\\\\\\s"?#&]+~', '$1***', $s);
    // A picture of dunemdb (the Favorites of the Dune, "Мой список"): auth=<the outside IP>-<a hash>.
    $s = preg_replace('~([?&]auth=)[^&\\s"\\\\]+~', '$1***', $s);
    // Base64 of JSON: it starts with the encoding of '{"'.
    $s = preg_replace('~ey[J][A-Za-z0-9_\\-+=%]+~', '***', $s);
    $s = preg_replace('~[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}~i',
        '***', $s);
    // Last: a password inside the manifest base must not break its mask.
    foreach ($pw as $p)
    {
        if (strlen($p) >= 4)
            $s = str_replace(array($p, rawurlencode($p), urlencode($p), substr(json_encode($p), 1, -1)), '***', $s);
    }
    return $s;
}

// --- The firmware of the box. Its version only for what the API has no sign of; else
// defined() / class_exists() of the API (PLUGIN_OP_GET_FOLDER_ROWS of MelFw, mel_catv_loading_flag,
// mel_catv_all_ok, mel_markers).

// From r25 the shell sizes the background of the rows by PluginRowsPane::vod_r; r24 puts it
// at 1280x720 at (640, 0) whatever vod_r is (the field is in the API of both).
define('MEL_FW_VOD_R_R', 25);
// The rows of posters of the own card come by the timer of its screen from r25 (seen on the device
// there; r24 by the code only): asked as the card opens before.
define('MEL_FW_TIMER_R', 25);
// The shell of r24 checks Dune Extra as a plugin starts its player and quits if the built-in pf_check
// has not run in its session yet (the card of the Dune runs it before a supplier): Melange runs it
// first there (mel_pf_check). r25 has no such check; r22 not looked at. Only this r (===), not a
// threshold as the other MEL_FW_*_R.
define('MEL_FW_PF_CHECK_R', 24);
// From r25 a lazy episode answers get_vod_stream_url (or _info) with its URL (mel_lazy_action): a new vod_play under the
// player of r25 drops the record of the episode that began after the end of the file of the one before. The shell
// of r25 waits for the URL over 65 s (seen on the device); r24 not seen (10 s by the SDK) and may not lose the
// record at all: r24, older and an unknown firmware answer with the vod_play as before.
define('MEL_FW_NEXT_URL_R', 25);

class MelFirmware
{
    // r of the firmware (mel_fw_r): null not read yet or unknown (read again next time).
    /** @var int|null */
    public static $r = null;
}

// versions.txt of the shell (<FS_PREFIX>/tmp/run; an empty FS_PREFIX on older
// models: /tmp/run), its first 4 KB, '' if none. Not through a symlink: the
// settings page reads it as root.
/**
 * @return string
 */
function mel_versions_text()
{
    $f = getenv('FS_PREFIX') . '/tmp/run/versions.txt';
    clearstatcache();
    $fp = !is_link($f) && is_file($f) ? @fopen($f, 'rb') : false;
    $t = $fp ? (string) fread($fp, 4096) : '';
    if ($fp)
        fclose($fp);
    return $t;
}

// Field $k of versions.txt $t, '' if none. Not our file: safe characters only,
// a sane length (substr of '' is false in PHP 5).
/**
 * @param string $t
 * @param string $k
 * @return string
 */
function mel_versions_field($t, $k)
{
    return preg_match('~^' . $k . '=([^\n]*)~m', $t, $m) ?
        (string) substr(strval(preg_replace('~[^A-Za-z0-9._+\-]~', '', $m[1])), 0, 48) : '';
}

// r of a firmware_version: 260827_0003_r24 -> 24, the same of 1.0.1-1000-260724_0207_r24
// (ATV); 0 of another form.
/**
 * @param string $v
 * @return int
 */
function mel_fw_r_of($v)
{
    return preg_match('~_r([0-9]{1,4})$~D', $v, $m) ? intval($m[1]) : 0;
}

// r of the firmware of the box, versions.txt read once per process; 0 unknown, not kept:
// the shell may write the file after the first call.
/**
 * @return int
 */
function mel_fw_r()
{
    if (MelFirmware::$r === null)
    {
        $r = mel_fw_r_of(mel_versions_field(mel_versions_text(), 'firmware_version'));
        if ($r === 0)
            return 0;
        MelFirmware::$r = $r;
    }
    return MelFirmware::$r;
}

// The shell of the box sizes the background of the rows by vod_r for sure (MEL_FW_VOD_R_R):
// false on r24, older or unknown.
/**
 * @return bool
 */
function mel_fw_vod_r()
{
    return mel_fw_r() >= MEL_FW_VOD_R_R;
}
