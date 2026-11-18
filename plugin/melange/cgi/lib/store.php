<?php
// The settings page (cgi/settings.php): the files of the plugin on the Dune -
// the data dir and its token, the write of settings.json.

// <storage>/plugins_data/melange for <storage>/plugins/melange<$suffix>; '' else.
function mel_cgi_data_of($path, $suffix)
{
    if (!preg_match('#(^|/)\.\.?(/|\z)|//#', $path) &&
        preg_match('#^((?:/[^/]+)+)/plugins/' . MEL_CGI_NAME . preg_quote($suffix, '#') . '\z#', $path, $m))
        return $m[1] . '/plugins_data/' . MEL_CGI_NAME;
    return '';
}

// Where the data dir may be, in order. run_plugin_cgi (APK firmware) sets
// PLUGIN_NAME and PLUGIN_CGI_DIR = realpath(<tmp>/www/plugins/<name>)/cgi-bin;
// older firmware sets neither (its plugins take the name from $PWD). Then the
// folder of the plugin (three levels up from cgi/lib/store.php), then the
// paths of Screenshoter: /persistfs (Sigma), $FS_PREFIX/flashdata. None for
// another plugin name.
function mel_cgi_data_dirs()
{
    $name = (string) getenv('PLUGIN_NAME');
    if ($name !== '' && $name !== MEL_CGI_NAME)
        return array();
    $dirs = array(
        $name !== '' ? mel_cgi_data_of((string) getenv('PLUGIN_CGI_DIR'), '/www/cgi-bin') : '',
        mel_cgi_data_of(dirname(dirname(dirname(__FILE__))), ''),
        '/persistfs/plugins_data/' . MEL_CGI_NAME,
        getenv('FS_PREFIX') . '/flashdata/plugins_data/' . MEL_CGI_NAME);
    return array_values(array_unique(array_diff($dirs, array(''))));
}

// The data dir that holds the given token; '' if none. Nothing at any of
// the paths (a layout not known here): 500, whatever the token.
function mel_cgi_data_dir($t)
{
    $dirs = mel_cgi_data_dirs();
    $seen = false;
    foreach ($dirs as $d)
    {
        if (mel_cgi_token_ok($d, $t))
            return $d;
        $seen = $seen || file_exists($d) || is_link($d);
    }
    if ($dirs && !$seen)
    {
        mel_cgi_log('no data dir: ' . implode(', ', $dirs));
        mel_cgi_send(500, 'text/plain', mel_cgi_l(
            'Melange: на этой Дюне не найдена папка данных плагина. Сообщите автору модель Дюны и версию прошивки.',
            'Melange: the plugin data folder was not found on this Dune. Please tell the author your Dune model and firmware version.') . "\n");
    }
    return '';
}

// The CGI never creates it: a root-owned dir would lock the plugin out.
function mel_cgi_dir_ok($dir)
{
    clearstatcache();
    return $dir !== '' && !is_link($dir) && is_dir($dir);
}

function mel_cgi_same($a, $b)
{
    if (strlen($a) !== strlen($b))
        return false;
    $d = 0;
    for ($i = 0; $i < strlen($a); $i++)
        $d |= ord($a[$i]) ^ ord($b[$i]);
    return $d === 0;
}

function mel_cgi_token_ok($dir, $given)
{
    if (!mel_token_ok($given) || !mel_cgi_dir_ok($dir))
        return false;
    $f = "$dir/" . MEL_TOKEN_FILE;
    if (is_link($f) || !is_file($f))
        return false;
    $t = trim((string) file_get_contents($f, false, null, 0, 64));
    return mel_token_ok($t) && mel_cgi_same($t, $given);
}

function mel_cgi_rand_hex($n)
{
    $b = function_exists('openssl_random_pseudo_bytes') ? (string) openssl_random_pseudo_bytes($n) : '';
    if (strlen($b) !== $n)
        $b = (string) file_get_contents('/dev/urandom', false, null, 0, $n);
    if (strlen($b) !== $n)
        $b = substr(md5(uniqid(mt_rand(), true) . mt_rand(), true), 0, $n);
    return bin2hex($b);
}

// --- Writing settings.json and web_token.

// $data as data_dir/$name (settings.json; web_token: the token of a backup
// imported, lib/backup.php). Umask 077 (0600, as the plugin makes web_token;
// another mode of the old one: mel_token_fix), a fresh randomly
// named temp file opened with 'x', lchown/lchgrp to the owner of web_token
// (l*: never through a symlink), rename over (replaces a symlink itself).
// web_token is made by php_server, the reader of settings.json (anew at each
// QR, and when it cannot read settings.json or, with posix, web_token is of
// another uid: mel_token_fix): the owner of the dir may be another uid (a
// restored backup, hypothesis). fopen 'x' follows a *dangling* symlink and creates its
// target (PHP 5.3.6 and 5.6), so the temp path is lstat-checked before and
// after fopen, before any data goes in. -> '' or the reason (for the log).
function mel_cgi_write($dir, $data, $name = MEL_SETTINGS_FILE)
{
    $part = "$dir/$name." . mel_cgi_rand_hex(4) . '.part';
    clearstatcache();
    // lstat: a symlink put there after mel_cgi_token_ok gives no owner of its target.
    $ts = lstat("$dir/" . MEL_TOKEN_FILE);
    if ($ts === false || ($ts['mode'] & 0170000) !== 0100000)
        return 'no web_token to take the owner from';
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
            lchown($part, $ts['uid']);
        if (function_exists('lchgrp'))
            lchgrp($part, $ts['gid']);
        clearstatcache();
        $ps = lstat($part);
        // A file the plugin (the owner of web_token) cannot read is worse than none.
        if ($ps === false || $ps['uid'] !== $ts['uid'])
            $why = 'owner differs from the owner of web_token';
    }
    if ($why === '' && !rename($part, "$dir/$name"))
        $why = 'rename failed';
    if ($why !== '')
        unlink($part);
    return $why;
}

// settings.json of mel_settings_json (common.php: the main profile copied to the top), the keys of $data in the order of
// mel_settings_read (those it does not know after them) -> '' or the reason (for the log).
function mel_cgi_store($dir, $data)
{
    $json = mel_settings_json(array_merge(array_intersect_key(mel_settings_parse(array()), $data), $data));
    return $json !== '' ? mel_cgi_write($dir, $json) : 'json_encode failed';
}
