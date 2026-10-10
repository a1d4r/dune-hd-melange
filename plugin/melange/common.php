<?php
// Shared by the plugin (php_server) and the settings page (cgi/settings.php,
// php-cgi as root): the version, the files of data_dir, the rules for the
// addresses and the mask of the log. Plain PHP 5.3: no firmware API, no
// logging here.
// Types in the PHPDoc (AioSettings and others): phpstan-types.neon of the repository.

define('AIO_VERSION', '0.41.0');
// In data_dir: written by the settings page only; the plugin only reads it.
define('AIO_SETTINGS_FILE', 'settings.json');
// In data_dir: the token of the settings page; written by the plugin only.
define('AIO_TOKEN_FILE', 'web_token');
define('AIO_URL_MAX', 2000);
// A bigger settings.json is not read (a real one is below 5 KB).
define('AIO_SETTINGS_MAX', 65536);
// trim() without \0: a NUL byte at an edge is a format error, not stripped.
define('AIO_TRIM', " \t\r\n\x0B");
// Path segments of the manifest base this long or longer are hidden in the
// log wherever they are (UUID and config password of a public instance).
define('AIO_MASK_SEGMENT', 16);

// AIOStreams manifest address as typed or stored -> the URL to use, or ''
// when it does not fit: http(s)://host[:port]/path/manifest.json, printable
// ASCII without ? # quotes <>; stremio:// is https://, the scheme in any case.
/**
 * @param mixed $s
 * @return string
 */
function aio_manifest_url($s)
{
    if (!is_string($s))
        return '';
    $s = preg_replace('~^stremio://~i', 'https://', trim($s, AIO_TRIM));
    if (strlen($s) > AIO_URL_MAX || !preg_match('~^[\x21-\x7e]+\z~', $s) || preg_match('~[?#"\'`<>\\\\]~', $s))
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
function aio_manifest_base($url)
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
 * @return AioJacred|null
 */
function aio_jacred_conf($s)
{
    $url = is_string($s) ? trim(preg_replace('/^\xEF\xBB\xBF/', '', $s), AIO_TRIM) : '';
    if (strlen($url) > AIO_URL_MAX ||
        !preg_match('~^(https?://([A-Za-z0-9.\-]+)(?::[0-9]{1,5})?)/?(?:\?apikey=([\x21\x22\x24\x25\x27-\x7e]*))?\z~', $url, $m))
        return null;
    $key = isset($m[3]) ? $m[3] : '';
    // A short key would be masked all over the log.
    if ($key !== '' && strlen($key) < 8)
        return null;
    return array('base' => $m[1], 'host' => $m[2], 'key' => $key,
        'key_url' => preg_match('/%[0-9A-Fa-f]{2}/', $key) ? $key : rawurlencode($key));
}

// Built-in JacRed $id of aio_jacred_builtin() -> aio_jacred_conf() +
// 'builtin' => true, null for another id.
/**
 * @param string $id
 * @return AioJacred|null
 */
function aio_jacred_builtin_conf($id)
{
    $b = aio_jacred_builtin();
    return isset($b[$id]) ? array('base' => $b[$id][0], 'host' => $id, 'key' => $b[$id][1],
        'key_url' => rawurlencode($b[$id][1]), 'builtin' => true) : null;
}

// "Download to server" (0.30.0): at most this many qBittorrent WebAPI servers.
define('AIO_SERVERS_MAX', 5);

// Debrid services a server may stand for (its "cache"): id of
// streamData.service of AIOStreams => name.
/**
 * @return array<string, string>
 */
function aio_server_caches()
{
    return array('torbox' => 'TorBox', 'realdebrid' => 'Real-Debrid');
}

// A server as typed or stored: array of strings name, url, user, pass,
// category, tags, cache -> the same keys checked, or null when the address does not
// fit. url: http(s)://host[:port][/path], port 1-65535, stored without a
// trailing slash, scheme and host in lower case; name: up to 40 characters,
// the host[:port] when empty; user, category, tags: up
// to 100 printable characters, pass: up to 200; tags: comma-separated,
// stored without spaces around each tag, empty ones and repeats; cache: a key of
// aio_server_caches() or ''. No control bytes, UTF-8 only.
/**
 * @param mixed $a
 * @return AioServer|null
 */
function aio_server_conf($a)
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
            $s = trim($s, AIO_TRIM);
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
        $t = trim($t, AIO_TRIM);
        if ($t !== '' && !in_array($t, $tags, true))
            $tags[] = $t;
    }
    $v['tags'] = implode(',', $tags);
    $host = strtolower($m[2]) . ($m[3] !== '' ? ":$m[3]" : '');
    $v['url'] = strtolower($m[1]) . "://$host$m[4]";
    if ($v['name'] === '')
        $v['name'] = $host;
    $caches = aio_server_caches();
    if (!isset($caches[$v['cache']]))
        $v['cache'] = '';
    return $v;
}

// "Watch via TorrServer" (0.31.0): no address set -> the TorrServe app of this Dune.
define('AIO_TS_DEFAULT', 'http://127.0.0.1:8090');

// TorrServer address as typed or stored: [http://]host[:port][/] -> 'http://host:port'
// (port 8090 when none, as Online movies), '' for empty, null when it does
// not fit (https, a path, a query, a user).
/**
 * @param mixed $s
 * @return string|null
 */
function aio_ts_addr($s)
{
    if (!is_string($s))
        return null;
    $s = trim($s, AIO_TRIM);
    if ($s === '')
        return '';
    if (strlen($s) > 300 || !preg_match('~^(?:(?i)http://)?([A-Za-z0-9.\-]+)(?::([0-9]{1,5}))?/?\z~', $s, $m))
        return null;
    $port = isset($m[2]) && $m[2] !== '' ? intval($m[2]) : 8090;
    return $port >= 1 && $port <= 65535 ? 'http://' . strtolower($m[1]) . ":$port" : null;
}

// The stored address ('' for none) -> the TorrServer to use.
/**
 * @param string $stored
 * @return string
 */
function aio_ts_base($stored)
{
    return $stored !== '' ? $stored : AIO_TS_DEFAULT;
}

// Built-in JacRed of the settings page (0.34.0), in the order of the page:
// id (its host) => array(base, key). Public: neither is hidden in the log.
// jacred.stream answers 403 to a "curl/*" User-Agent and to a wrong key;
// jac.red answers 429 to a second request within ~0.5 s.
/**
 * @return array<string, array{string, string}>
 */
function aio_jacred_builtin()
{
    return array(
        'jacred.stream' => array('https://jacred.stream', 'pp'),
        'jr.maxvol.pro' => array('https://jr.maxvol.pro', ''),
        'jac.stull.xyz' => array('https://jac.stull.xyz', ''),
        'jac.red' => array('https://jac.red', ''));
}
// Chosen when the settings choose none (no file, 0.33 without one, a wrong
// choice); also the one asked when the chosen one does not answer.
define('AIO_JACRED_DEFAULT', 'jacred.stream');
define('AIO_JACRED_URL_MAX', 300);

// The own JacRed as typed or stored: array(url, key) -> the same keys
// checked, or null. url: http(s)://host[:port][/] of up to AIO_JACRED_URL_MAX
// characters, stored without the slash, scheme and host in lower case, port
// 1-65535; key: '' or 8-200 printable ASCII without space & # (as
// aio_jacred_conf; a shorter one would be masked all over the log).
/**
 * @param mixed $a
 * @return array{url: string, key: string}|null
 */
function aio_jacred_own($a)
{
    if (!is_array($a))
        return null;
    $url = isset($a['url']) && is_string($a['url']) ? trim($a['url'], AIO_TRIM) : '';
    $key = isset($a['key']) && is_string($a['key']) ? trim($a['key'], AIO_TRIM) : '';
    // 300 + "/?apikey=" + 200 stays within AIO_URL_MAX of aio_jacred_conf.
    if (strlen($url) > AIO_JACRED_URL_MAX || !preg_match('~^(https?)://([A-Za-z0-9.\-]+)(?::([0-9]{1,5}))?/?\z~i', $url, $m) ||
        (isset($m[3]) && $m[3] !== '' && (intval($m[3]) < 1 || intval($m[3]) > 65535)) ||
        ($key !== '' && !preg_match('~^[\x21\x22\x24\x25\x27-\x7e]{8,200}\z~', $key)))
        return null;
    return array('url' => strtolower("$m[1]://$m[2]") . (isset($m[3]) && $m[3] !== '' ? ":$m[3]" : ''), 'key' => $key);
}

// AIOStreams servers to choose from (0.34.0; the config is made on them from 0.35.0):
// base => it has a TMDB key of its own (else titles are not matched and
// series get fewer releases without a key of the user). The first one is the default.
/**
 * @return array<string, bool>
 */
function aio_aio_servers()
{
    return array(
        'https://aiostreams.12312023.xyz' => true,
        'https://aiostreamsfortheweebsstable.midnightignite.me' => false,
        'https://aio.atbphosting.com' => false,
        'https://aiostreams.stremio.ru' => false,
        'https://aiostreams-stable.forthewizards.uk' => false,
        'https://aiostreams.fortheweak.cloud' => false);
}

// Templates of the config melange makes (0.40.0): id => instanceIds of the
// presets of cgi/aio_template.json it keeps (null: all). The first one is
// the default.
/**
 * @return array<string, list<string>|null>
 */
function aio_aio_tpls()
{
    return array('addons' => null, 'jacred' => array('melange'));
}

// A config of AIOStreams made by the settings page (0.35.0), as stored in
// aio_confs of settings.json under its server $base (a key of
// aio_aio_servers()): uuid, pass - the login and password of the config
// (24 hex, made by the page), manifest - its URL from the reply of the
// server (<base>/stremio/<uuid>/<encrypted password>/manifest.json), tmdb -
// the config had TMDB at its last change, set (0.35.1) - of rd, tb, tmdb
// the fields melange put in the config (null: not stored, a file of
// 0.35.0), tpl (0.40.0) - its template by its presets: a key of
// aio_aio_tpls(), 'custom' (changed in AIOStreams) or null (not known yet,
// a config of 0.39 or older). -> the same keys checked, or null.
/**
 * @param int|string $base
 * @param mixed $c
 * @return AioConf|null
 */
function aio_aio_conf($base, $c)
{
    $servers = aio_aio_servers();
    if ((!isset($servers[$base]) && aio_aio_own($base) !== $base) || !is_array($c) || !isset($c['uuid'], $c['pass'], $c['manifest']) ||
        !is_string($c['uuid']) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $c['uuid']) ||
        !is_string($c['pass']) || !preg_match('/^[0-9a-f]{24}\z/', $c['pass']) ||
        aio_manifest_url($c['manifest']) !== $c['manifest'] ||
        strpos($c['manifest'], "$base/stremio/{$c['uuid']}/") !== 0)
        return null;
    return array('uuid' => $c['uuid'], 'pass' => $c['pass'], 'manifest' => $c['manifest'],
        'tmdb' => isset($c['tmdb']) && $c['tmdb'] === true, 'set' => isset($c['set']) && is_array($c['set']) ?
        array_values(array_intersect(array('rd', 'tb', 'tmdb'), $c['set'])) : null,
        'tpl' => isset($c['tpl']) && is_string($c['tpl']) && ($c['tpl'] === 'custom' ||
        array_key_exists($c['tpl'], aio_aio_tpls())) ? $c['tpl'] : null);
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
function aio_tmdb_sent($base, $key)
{
    $servers = aio_aio_servers();
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
function aio_aio_own($s)
{
    $s = is_string($s) ? trim($s, AIO_TRIM) : '';
    if (strlen($s) > 300 || !preg_match('~^(https?)://([A-Za-z0-9][A-Za-z0-9.\-]*[A-Za-z0-9])(?::([0-9]{1,5}))?/?\z~i', $s, $m) ||
        (isset($m[3]) && $m[3] !== '' && (intval($m[3]) < 1 || intval($m[3]) > 65535)))
        return '';
    return strtolower("$m[1]://$m[2]") . (isset($m[3]) && $m[3] !== '' ? ':' . intval($m[3]) : '');
}

// The server melange makes the config on (aio_server of the settings): a
// server of the list or another one -> its base, the key of its config in
// aio_confs ('' for another one without an address).
/**
 * @param AioSettings $s
 * @return string
 */
function aio_settings_base($s)
{
    return $s['aio_server'] === 'own' ? $s['aio_own_url'] : $s['aio_server'];
}

// The manifest the plugin uses: the own config (source own), else the
// config made on the chosen server ('' for none).
/**
 * @param AioSettings $s
 * @return string
 */
function aio_settings_manifest($s)
{
    if ($s['source'] === 'own')
        return $s['manifest_url'];
    $b = aio_settings_base($s);
    return $b !== '' && isset($s['aio_confs'][$b]) ? $s['aio_confs'][$b]['manifest'] : '';
}

// Keys of the services as typed or stored -> the key, or '' when it does not
// fit. Real-Debrid: 52 upper-case letters and digits, TorBox: a UUID, TMDB
// v3: 32 hex - checked loosely, the service decides.
/**
 * @param string $what
 * @param mixed $s
 * @return string
 */
function aio_key_ok($what, $s)
{
    if (!is_string($s))
        return '';
    $s = trim($s, AIO_TRIM);
    $re = array('rd' => '~^[A-Za-z0-9]{20,100}\z~', 'tb' => '~^[A-Za-z0-9\-]{20,100}\z~',
        'tmdb' => '~^[A-Za-z0-9]{20,64}\z~');
    return isset($re[$what]) && preg_match($re[$what], $s) ? $s : '';
}

// jacred_url of 0.33 and older -> array(jacred, own url, own key): a
// built-in one if its host is one of them, else the own one (the key from
// ?apikey=); empty or wrong -> AIO_JACRED_DEFAULT.
/**
 * @param mixed $url
 * @return array{string, string, string}
 */
function aio_jacred_migrate($url)
{
    $c = aio_jacred_conf($url);
    $b = aio_jacred_builtin();
    if ($c && isset($b[strtolower($c['host'])]))
        return array(strtolower($c['host']), '', '');
    $own = $c ? aio_jacred_own(array('url' => $c['base'], 'key' => $c['key'])) : null;
    return $own ? array('own', $own['url'], $own['key']) : array(AIO_JACRED_DEFAULT, '', '');
}

// The own JacRed of the settings, chosen or not, for the mask of the log:
// array() or array(aio_jacred_conf() + 'builtin' => false).
/**
 * @param AioSettings $s
 * @return list<AioJacred>
 */
function aio_jacred_own_confs($s)
{
    $c = $s['jacred_own_url'] !== '' ? aio_jacred_conf($s['jacred_own_url'] .
        ($s['jacred_own_key'] !== '' ? '/?apikey=' . $s['jacred_own_key'] : '')) : null;
    return $c ? array($c + array('builtin' => false)) : array();
}

// The JacRed to ask, in this order: the chosen one, then AIO_JACRED_DEFAULT
// if that is another. -> list of aio_jacred_conf() + 'builtin'.
/**
 * @param AioSettings $s
 * @return list<AioJacred>
 */
function aio_jacred_list($s)
{
    $list = $s['jacred'] === 'own' ? aio_jacred_own_confs($s) : array();
    foreach (array_unique(array($s['jacred'], AIO_JACRED_DEFAULT)) as $id)
    {
        $c = aio_jacred_builtin_conf($id);
        if ($c)
            $list[] = $c;
    }
    return $list;
}

// The secrets of the settings besides the manifest and JacRed: server
// passwords, keys of Real-Debrid, TorBox, TMDB, passwords of the configs
// and the long segments of their manifests, as of the manifest in use
// (aio_mask_secrets).
/**
 * @param AioSettings $s
 * @return list<string>
 */
function aio_settings_secrets($s)
{
    $pw = aio_server_secrets($s['servers']);
    foreach (array('rd_key', 'tb_key', 'tmdb_key') as $k)
    {
        if ($s[$k] !== '')
            $pw[] = $s[$k];
    }
    foreach ($s['aio_confs'] as $c)
    {
        $pw[] = $c['pass'];
        foreach (explode('/', preg_replace('~^[^:]*://[^/]*~', '', aio_manifest_base($c['manifest']))) as $seg)
        {
            if (strlen($seg) >= AIO_MASK_SEGMENT)
                $pw[] = $seg;
        }
    }
    return $pw;
}

// Addresses of the settings to hide in the log, scheme://host[:port] =>
// tag: the own TorrServer "<ts>" (not the default one on the Dune), the
// servers of "Download to server" "<server>".
/**
 * @param AioSettings $s
 * @return array<string, string>
 */
function aio_settings_hosts($s)
{
    $h = array();
    if ($s['ts_url'] !== '' && $s['ts_url'] !== AIO_TS_DEFAULT)
        $h[$s['ts_url']] = '<ts>';
    foreach ($s['servers'] as $srv)
    {
        $p = parse_url($srv['url']);
        $u = strtolower($p['scheme'] . '://' . $p['host']) . (isset($p['port']) ? ':' . $p['port'] : '');
        if (!isset($h[$u]))
            $h[$u] = '<server>';
    }
    return $h;
}

// <data_dir>/settings.json -> array('source' => 'made' (melange makes the
// config on aio_server) or 'own' (the own config of manifest_url),
// 'manifest_url' => ..., 'rd_key', 'tb_key',
// 'jacred' => a key of aio_jacred_builtin() or 'own', 'jacred_own_url',
// 'jacred_own_key' => aio_jacred_own() or '' (kept when a built-in one is
// chosen), 'aio_server' => a key of aio_aio_servers() or 'own' (another
// server: aio_own_url), 'aio_own_url' => aio_aio_own(), 'aio_tpl' => a key
// of aio_aio_tpls() (one for all servers); everything kept
// whatever is chosen, 'tmdb_key',
// 'servers' => list of aio_server_conf(), 'ts_url' => aio_ts_addr() or '',
// 'aio_confs' => base of a server => aio_aio_conf()),
// each checked by the rules above; absent or wrong -> as not set ('', the
// default server, AIO_JACRED_DEFAULT; 'own' without a good address too; a
// wrong server is left out). A file of 0.33 (v 1, jacred_url) is read as v 2
// (aio_jacred_migrate); the plugin never writes it. Never through a symlink
// (the page runs as root).
/**
 * @param string $dir
 * @return AioSettings
 */
function aio_settings_read($dir)
{
    $servers = array_keys(aio_aio_servers());
    $tpls = aio_aio_tpls();
    $r = array('source' => 'made', 'manifest_url' => '', 'rd_key' => '', 'tb_key' => '', 'jacred' => AIO_JACRED_DEFAULT,
        'jacred_own_url' => '', 'jacred_own_key' => '', 'aio_server' => $servers[0], 'aio_own_url' => '',
        'aio_tpl' => key($tpls), 'tmdb_key' => '', 'servers' => array(), 'ts_url' => '', 'aio_confs' => array());
    $f = "$dir/" . AIO_SETTINGS_FILE;
    clearstatcache();
    if ($dir === '' || is_link($f) || !is_file($f) || !is_readable($f) || filesize($f) > AIO_SETTINGS_MAX)
        return $r;
    $raw = file_get_contents($f, false, null, 0, AIO_SETTINGS_MAX);
    $j = json_decode((string) $raw, true);
    if (!is_array($j))
        return $r;
    if (isset($j['manifest_url']))
        $r['manifest_url'] = aio_manifest_url($j['manifest_url']);
    foreach (array('rd_key' => 'rd', 'tb_key' => 'tb', 'tmdb_key' => 'tmdb') as $k => $what)
    {
        if (isset($j[$k]))
            $r[$k] = aio_key_ok($what, $j[$k]);
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
        $r['aio_own_url'] = aio_aio_own($j['aio_own_url']);
    if (isset($j['aio_tpl']) && is_string($j['aio_tpl']) && array_key_exists($j['aio_tpl'], $tpls))
        $r['aio_tpl'] = $j['aio_tpl'];
    if (!isset($j['v']) || $j['v'] !== 2)
        list($r['jacred'], $r['jacred_own_url'], $r['jacred_own_key']) =
            aio_jacred_migrate(isset($j['jacred_url']) ? $j['jacred_url'] : '');
    else
    {
        $own = aio_jacred_own(array('url' => isset($j['jacred_own_url']) ? $j['jacred_own_url'] : '',
            'key' => isset($j['jacred_own_key']) ? $j['jacred_own_key'] : ''));
        if ($own)
            list($r['jacred_own_url'], $r['jacred_own_key']) = array($own['url'], $own['key']);
        $b = aio_jacred_builtin();
        if (isset($j['jacred']) && is_string($j['jacred']) && (isset($b[$j['jacred']]) || ($j['jacred'] === 'own' && $own)))
            $r['jacred'] = $j['jacred'];
    }
    if (isset($j['servers']) && is_array($j['servers']))
    {
        foreach (array_values($j['servers']) as $s)
        {
            $s = aio_server_conf($s);
            if ($s && count($r['servers']) < AIO_SERVERS_MAX)
                $r['servers'][] = $s;
        }
    }
    if (isset($j['ts_url']) && is_string(aio_ts_addr($j['ts_url'])))
        $r['ts_url'] = aio_ts_addr($j['ts_url']);
    if (isset($j['aio_confs']) && is_array($j['aio_confs']))
    {
        foreach ($j['aio_confs'] as $b => $c)
        {
            $c = aio_aio_conf($b, $c);
            // 0.35.0 stored no marks: the keys melange has now are taken as its own.
            if ($c && $c['set'] === null)
                $c['set'] = array_keys(array_filter(array('rd' => $r['rd_key'] !== '', 'tb' => $r['tb_key'] !== '',
                    'tmdb' => aio_tmdb_sent($b, $r['tmdb_key']) !== '')));
            if ($c)
                $r['aio_confs'][$b] = $c;
        }
    }
    return $r;
}

// Passwords of the servers to hide in the log (aio_mask_secrets).
/**
 * @param list<AioServer> $servers
 * @return list<string>
 */
function aio_server_secrets($servers)
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
function aio_token_ok($t)
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
function aio_mask_host($s, $aio, $tag)
{
    // Only as a whole host: not "aio" of "aio sync", not the start of
    // "aiostreams.example" (a host "aiostreams").
    $end = '(?![A-Za-z0-9\\-]|\\.[A-Za-z0-9])';
    $hp = preg_replace('~^[^:]*://~', '', $aio);
    $h = preg_replace('~:[0-9]+\z~', '', $hp);
    $hq = preg_quote($h, '~');
    // Its own port only as a whole ("nas:30000" keeps ":30000").
    $pp = $hp !== $h ? '(?:' . preg_quote(substr($hp, strlen($h)), '~') . '(?![0-9]))?' : '(?::[0-9]+)?';
    $s = preg_replace('~(?:' . preg_quote($aio, '~') . '|' . preg_quote(str_replace('/', '\\/', $aio), '~') . ')' . $end . '~',
        $tag, $s);
    // The host in a URL or after a user; else before a port; a dotted one
    // (an IP, a domain) also alone.
    $s = preg_replace('~(?<=//|\\\\/\\\\/|@)' . $hq . $pp . $end . '~', $tag, $s);
    $s = preg_replace('~(?<![A-Za-z0-9.\\-])' . $hq . (strpos($h, '.') !== false ? '' : '(?=:[0-9])') . $pp . $end . '~',
        $tag, $s);
    // Alone in curl errors: "resolve host: nas", "connect to nas port 3000",
    // "target host name 'nas'".
    return preg_replace('~(?:(?<=host: |host |host name \')' . $hq . '|(?<![A-Za-z0-9.\\-])' . $hq . '(?= port [0-9]))' . $end . '~',
        $tag, $s);
}

// A log line with the secrets hidden: the manifest base ($base, '' when not
// set) and each of its long path segments, the key of the own JacRed as
// written, URL-encoded (both ways) and JSON-escaped, and its address as
// "<jacred>" ($jrs: aio_jacred_own_confs() or confs of aio_jacred_conf();
// built-in ones are public and skipped),
// playback tokens, StremThru and Comet configs (base64 JSON), UUIDs, the
// secrets $pw of 4+ bytes (server passwords, keys of the services; a
// shorter one would garble the log and never goes there anyway).
// JSON-escaped slashes ("\/") are covered too. $aio: the own AIOStreams
// server (aio_aio_own(), '' for none; a LAN or NAS address) -> "<aio>";
// $hosts: more such addresses, as aio_settings_hosts().
/**
 * @param string $s
 * @param string $base
 * @param list<AioJacred> $jrs
 * @param list<string> $pw
 * @param string $aio
 * @param array<string, string> $hosts
 * @return string
 */
function aio_mask_secrets($s, $base, $jrs, $pw = array(), $aio = '', $hosts = array())
{
    if ($base !== '')
    {
        $s = str_replace(array($base, str_replace('/', '\\/', $base)), '<manifest>/', $s);
        foreach (explode('/', preg_replace('~^[^:]*://[^/]*~', '', $base)) as $seg)
        {
            if (strlen($seg) >= AIO_MASK_SEGMENT)
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
        $s = aio_mask_host($s, $aio, '<aio>');
    foreach ($hosts as $u => $tag)
        $s = aio_mask_host($s, $u, $tag);
    $s = preg_replace('~(\\\\?/(?:playback|torz)\\\\?/)[^/\\\\\\s"?#&]+~', '$1***', $s);
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
