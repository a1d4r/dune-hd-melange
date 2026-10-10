<?php
// The settings page (cgi/settings.php): the files of the plugin on the Dune -
// the data dir and its token, the write of settings.json, the log download.

// <storage>/plugins_data/melange for <storage>/plugins/melange<$suffix>; '' else.
function aio_cgi_data_of($path, $suffix)
{
    if (!preg_match('#(^|/)\.\.?(/|\z)|//#', $path) &&
        preg_match('#^((?:/[^/]+)+)/plugins/' . AIO_CGI_NAME . preg_quote($suffix, '#') . '\z#', $path, $m))
        return $m[1] . '/plugins_data/' . AIO_CGI_NAME;
    return '';
}

// Where the data dir may be, in order. run_plugin_cgi (APK firmware) sets
// PLUGIN_NAME and PLUGIN_CGI_DIR = realpath(<tmp>/www/plugins/<name>)/cgi-bin;
// older firmware sets neither (its plugins take the name from $PWD). Then the
// folder of the plugin (three levels up from cgi/lib/store.php), then the
// paths of Screenshoter: /persistfs (Sigma), $FS_PREFIX/flashdata. None for
// another plugin name.
function aio_cgi_data_dirs()
{
    $name = (string) getenv('PLUGIN_NAME');
    if ($name !== '' && $name !== AIO_CGI_NAME)
        return array();
    $dirs = array(
        $name !== '' ? aio_cgi_data_of((string) getenv('PLUGIN_CGI_DIR'), '/www/cgi-bin') : '',
        aio_cgi_data_of(dirname(dirname(dirname(__FILE__))), ''),
        '/persistfs/plugins_data/' . AIO_CGI_NAME,
        getenv('FS_PREFIX') . '/flashdata/plugins_data/' . AIO_CGI_NAME);
    return array_values(array_unique(array_diff($dirs, array(''))));
}

// The data dir that holds the given token; '' if none. Nothing at any of
// the paths (a layout not known here): 500, whatever the token.
function aio_cgi_data_dir($t)
{
    $dirs = aio_cgi_data_dirs();
    $seen = false;
    foreach ($dirs as $d)
    {
        if (aio_cgi_token_ok($d, $t))
            return $d;
        $seen = $seen || file_exists($d) || is_link($d);
    }
    if ($dirs && !$seen)
    {
        aio_cgi_log('no data dir: ' . implode(', ', $dirs));
        aio_cgi_send(500, 'text/plain', aio_cgi_l(
            'Melange: на этой Дюне не найдена папка данных плагина. Сообщите автору модель Дюны и версию прошивки.',
            'Melange: the data folder of the plugin was not found on this Dune. Please tell the author the model of the Dune and the firmware version.') . "\n");
    }
    return '';
}

// The CGI never creates it: a root-owned dir would lock the plugin out.
function aio_cgi_dir_ok($dir)
{
    clearstatcache();
    return $dir !== '' && !is_link($dir) && is_dir($dir);
}

function aio_cgi_same($a, $b)
{
    if (strlen($a) !== strlen($b))
        return false;
    $d = 0;
    for ($i = 0; $i < strlen($a); $i++)
        $d |= ord($a[$i]) ^ ord($b[$i]);
    return $d === 0;
}

function aio_cgi_token_ok($dir, $given)
{
    if (!aio_token_ok($given) || !aio_cgi_dir_ok($dir))
        return false;
    $f = "$dir/" . AIO_TOKEN_FILE;
    if (is_link($f) || !is_file($f))
        return false;
    $t = trim((string) file_get_contents($f, false, null, 0, 64));
    return aio_token_ok($t) && aio_cgi_same($t, $given);
}

function aio_cgi_rand_hex($n)
{
    $b = function_exists('openssl_random_pseudo_bytes') ? (string) openssl_random_pseudo_bytes($n) : '';
    if (strlen($b) !== $n)
        $b = (string) file_get_contents('/dev/urandom', false, null, 0, $n);
    if (strlen($b) !== $n)
        $b = substr(md5(uniqid(mt_rand(), true) . mt_rand(), true), 0, $n);
    return bin2hex($b);
}

// --- Writing settings.json.

// Umask 077, a fresh randomly
// named temp file opened with 'x', lchown/lchgrp to the owner of the dir
// (l*: never through a symlink), rename over (replaces a symlink itself).
// fopen 'x' follows a *dangling* symlink and creates its target (PHP 5.3.6
// and 5.6), so the temp path is lstat-checked before and after fopen,
// before any data goes in. -> '' or the reason (for the log).
function aio_cgi_write($dir, $data)
{
    $part = "$dir/" . AIO_SETTINGS_FILE . '.' . aio_cgi_rand_hex(4) . '.part';
    clearstatcache();
    $ds = lstat($dir);
    umask(077);
    if (file_exists($part) || is_link($part))
        return 'temp name taken';
    $fp = fopen($part, 'x');
    if (!is_resource($fp))
        return 'fopen failed';
    clearstatcache();
    $ps = lstat($part);
    $fst = fstat($fp);
    if ($ps === false || is_link($part) || $ps['ino'] !== $fst['ino'])
    {
        fclose($fp);
        unlink($part);
        return 'temp path is not our file';
    }
    $n = fwrite($fp, $data);
    $why = fclose($fp) && $n === strlen($data) ? '' : 'write failed';
    if ($why === '')
    {
        // On the device root gives it to system; a CGI that is not root
        // (ATV, hypothesis) owns it already and may fail to chown.
        if (function_exists('lchown'))
            lchown($part, $ds['uid']);
        if (function_exists('lchgrp'))
            lchgrp($part, $ds['gid']);
        clearstatcache();
        $ps = lstat($part);
        // A file the plugin (the owner of the dir) cannot read is worse than none.
        if ($ps === false || $ps['uid'] !== $ds['uid'])
            $why = 'owner differs from the owner of the dir';
    }
    if ($why === '' && !rename($part, "$dir/" . AIO_SETTINGS_FILE))
        $why = 'rename failed';
    if ($why !== '')
        unlink($part);
    return $why;
}

// settings.json v 2 of $data (keys of aio_settings_read, its order) with
// saved_at now -> '' or the reason (for the log).
function aio_cgi_store($dir, $data)
{
    $data = array_merge(array('v' => 2), $data, array('saved_at' => time()));
    $json = json_encode($data);
    // PHP 5.3 turns a value that is not UTF-8 into null, not an error.
    return is_string($json) && json_decode($json, true) === $data ? aio_cgi_write($dir, $json) : 'json_encode failed';
}

// --- The log download.

// The log download: at most its last 3 MB.
define('AIO_CGI_LOG_MAX', 3145728);

// stdout of php_server; with an empty FS_PREFIX (older models) /tmp/run.
function aio_cgi_log_path()
{
    return getenv('FS_PREFIX') . '/tmp/run/' . AIO_CGI_NAME . '.log';
}

// A header, then melange.log (its last AIO_CGI_LOG_MAX bytes), every line
// through the mask again: PHP notices reach the log past aio_log. Streamed.
function aio_cgi_log_download($dir)
{
    set_time_limit(300);
    date_default_timezone_set('UTC');
    $s = aio_settings_read($dir);
    $base = aio_manifest_base(aio_settings_manifest($s));
    // The own JacRed, chosen or not: its key and address never in the log.
    $jrs = aio_jacred_own_confs($s);
    $pw = aio_settings_secrets($s);
    $hosts = aio_settings_hosts($s);
    aio_cgi_headers(200, 'text/plain');
    header('Content-Disposition: attachment; filename="' . AIO_CGI_NAME . '-' . gmdate('Ymd-Hi') . '.log"');
    header('X-Content-Type-Options: nosniff');
    $host = $base !== '' ? (string) parse_url($base, PHP_URL_HOST) : '';
    $own = $s['aio_own_url'] !== '' ? (string) parse_url($s['aio_own_url'], PHP_URL_HOST) : '';
    echo 'Melange ' . AIO_VERSION . ', ' . gmdate('Y-m-d H:i:s') . " UTC\n" .
        // Another server never by its address (a LAN or NAS one), an own
        // config on it neither.
        'AIOStreams: ' . ($host === '' ? 'not set' : ($s['source'] === 'made' && $s['aio_server'] === 'own' ? 'own' :
        ($own !== '' && strtolower($host) === $own ? '<aio>' : $host))) .
        ($s['source'] === 'own' ? ', own config' : ', Melange config') . "\n" .
        // The chosen one: a built-in id, the own one never by its address.
        'JacRed: ' . ($s['jacred'] === 'own' ? '<jacred>' : $s['jacred']) . "\n" .
        'Servers: ' . count($s['servers']) . "\n" .
        // The own one never by its address.
        'TorrServer: ' . (isset($hosts[$s['ts_url']]) ? '<ts>' : AIO_TS_DEFAULT) . "\n";
    $f = aio_cgi_log_path();
    clearstatcache();
    // Not through a symlink: this runs as root.
    $fp = !is_link($f) && is_file($f) ? fopen($f, 'rb') : false;
    if (!$fp)
    {
        echo "\n" . aio_cgi_l('Лог пуст: после перезагрузки Дюны он начинается заново',
            'The log is empty: it starts anew after a restart of the Dune') . "\n";
        exit(0);
    }
    $size = filesize($f);
    $cut = '';
    if ($size > AIO_CGI_LOG_MAX)
    {
        fseek($fp, -AIO_CGI_LOG_MAX, SEEK_END);
        // The first line is cut: a secret cut in it would pass the mask.
        $first = fgets($fp);
        $cut = $first === false || substr($first, -1) !== "\n" ? "(log cut: no line break in the last 3 MB)\n" :
            "(log cut: last 3 MB, the first partial line skipped)\n";
    }
    echo "melange.log: $size bytes\n$cut\n";
    $n = 0;
    while (($line = fgets($fp)) !== false)
    {
        echo aio_mask_secrets($line, $base, $jrs, $pw, $s['aio_own_url'], $hosts);
        if (++$n % 500 === 0)
            flush();
    }
    fclose($fp);
    exit(0);
}
