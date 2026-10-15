<?php
// The settings page (cgi/settings.php): the log download - the header (Melange,
// the servers, the device), melange.log and the tails of the shell logs, masked.

// The log download: at most the last 3 MB of melange.log and 1 MB of each
// shell log.
define('AIO_CGI_LOG_MAX', 3145728);
define('AIO_CGI_SHELL_LOG_MAX', 1048576);

// A file of the shell's run dir; with an empty FS_PREFIX (older models) /tmp/run.
function aio_cgi_run_path($file)
{
    return getenv('FS_PREFIX') . '/tmp/run/' . $file;
}

// stdout of php_server.
function aio_cgi_log_path()
{
    return aio_cgi_run_path(AIO_CGI_NAME . '.log');
}

// "Device: <product>, firmware <v>, app <v>, <platform>, FS_PREFIX set|empty"
// from versions.txt of the shell; no file -> "unknown", no field -> left out.
function aio_cgi_device()
{
    $f = aio_cgi_run_path('versions.txt');
    clearstatcache();
    // Not through a symlink: this runs as root.
    $fp = !is_link($f) && is_file($f) ? @fopen($f, 'rb') : false;
    $t = $fp ? (string) fread($fp, 4096) : '';
    if ($fp)
        fclose($fp);
    $v = array();
    foreach (array('product', 'firmware_version', 'app_version', 'platform_kind') as $k)
    {
        // Not our file: safe characters only, a sane length (substr of '' is false in PHP 5).
        $v[$k] = preg_match('~^' . $k . '=([^\n]*)~m', $t, $m) ?
            (string) substr(preg_replace('~[^A-Za-z0-9._+\-]~', '', $m[1]), 0, 48) : '';
    }
    return 'Device: ' . ($v['product'] !== '' ? $v['product'] : 'unknown') .
        ($v['firmware_version'] !== '' ? ', firmware ' . $v['firmware_version'] : '') .
        ($v['app_version'] !== '' ? ', app ' . $v['app_version'] : '') .
        ($v['platform_kind'] !== '' ? ', ' . $v['platform_kind'] : '') .
        ', FS_PREFIX ' . ((string) getenv('FS_PREFIX') !== '' ? 'set' : 'empty');
}

// A line of shell.log, after aio_mask_secrets, with the home network and the
// box hidden: the serial number of the Dune, MACs, IPv4 but 127.0.0.1, names
// of LAN hosts of the net scanner, a network URL down to scheme://host[:port]
// (user, path and query -> "/…"), values of auth/serial/token/password/key in
// k=v and k: v. The events (pid, time, module, errors) stay.
function aio_cgi_mask_shell($s)
{
    $s = preg_replace('~[0-9A-Fa-f]{4}(?:-[0-9A-Fa-f]{4}){5}-[0-9A-Fa-f]+~', '<serial>', $s);
    $s = preg_replace('~(?<![0-9A-Fa-f:\-])[0-9A-Fa-f]{2}([:\-])[0-9A-Fa-f]{2}(?:\1[0-9A-Fa-f]{2}){4}(?![0-9A-Fa-f:\-])~',
        '<mac>', $s);
    // Also JSON-escaped (":\/\/"); "<aio>" and the like of aio_mask_secrets as a host.
    $url = '~(?<![A-Za-z0-9+.\-])(https?|smb|nfs|s?ftps?|rtsps?|rtmp[a-z]*|rtp|udp|mms[a-z]*|(?:web)?davs?|wss?):(?:\\\\?/){2}';
    $s = preg_replace($url . '[^\s/\\\\?#@\'"]*@~i', '$1://', $s);
    $s = preg_replace($url . '([A-Za-z0-9.\-_\~%:\[\]<>]*)(?:\\\\?/|[?#])[^\s\'"]*~i', '$1://$2/…', $s);
    $s = preg_replace('~(?<![0-9.])(?!127\.0\.0\.1(?![0-9]|\.[0-9]))(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?![0-9]|\.[0-9])~',
        '<ip>', $s);
    $s = preg_replace('~(nmblookup got server \')[^\']*~', '$1<host>', $s);
    $s = preg_replace('~(net_scanner I: added \S+ - )[^\r\n]+~', '$1<host>', $s);
    $s = preg_replace('~(Nmap scan report for )(?!<ip>)[^\s(]+~', '$1<host>', $s);
    // The raw answer of nmblookup: "\tNAME <00> - B <ACTIVE>".
    $s = preg_replace('~^(\s+)\S[^<\r\n]*?(?=\s*<[0-9A-Fa-f]{2}> )~', '$1<host>', $s);
    return preg_replace('~(?<![A-Za-z0-9_\-])((?:[A-Za-z0-9_\-]*(?:auth|serial|token|secret|passw(?:or)?d|cookie|key)' .
        '[A-Za-z0-9_\-]*|pass|pwd)(?:=|: ?|":"))(["\']?)(?:Bearer |Basic )?[^\s&"\',;]+~i', '$1$2<…>', $s);
}

// $f (not through a symlink: this runs as root) at its last $max bytes from a
// whole line, "<name>: N bytes" and the cut printed -> the handle; false
// (nothing printed) when there is no file or it cannot be read.
function aio_cgi_log_open($f, $name, $max)
{
    clearstatcache();
    $fp = !is_link($f) && is_file($f) ? @fopen($f, 'rb') : false;
    if (!$fp)
        return false;
    $size = filesize($f);
    $cut = '';
    if ($size > $max)
    {
        fseek($fp, -$max, SEEK_END);
        // The first line is cut: a secret cut in it would pass the mask.
        $first = fgets($fp);
        $mb = round($max / 1048576) . ' MB';
        $cut = $first === false || substr($first, -1) !== "\n" ? "(log cut: no line break in the last $mb)\n" :
            "(log cut: last $mb, the first partial line skipped)\n";
    }
    echo "$name: $size bytes\n$cut\n";
    return $fp;
}

// The lines of $fp, each through the mask again ($m: the arguments of
// aio_mask_secrets; $shell: aio_cgi_mask_shell too), the last one ended. Streamed.
function aio_cgi_log_lines($fp, $m, $shell)
{
    $n = 0;
    $line = '';
    while (($line = fgets($fp)) !== false)
    {
        $out = aio_mask_secrets($line, $m['base'], $m['jrs'], $m['pw'], $m['aio'], $m['hosts']);
        echo $shell ? aio_cgi_mask_shell($out) : $out;
        if (substr($line, -1) !== "\n")
            echo "\n";
        if (++$n % 500 === 0)
            flush();
    }
    fclose($fp);
}

// A header (Melange, the servers, the device), then melange.log (its last
// AIO_CGI_LOG_MAX bytes), every line through the mask again: PHP notices
// reach the log past aio_log; then the tails of shell.log.old and shell.log
// of the shell (php_server closed, crashes), masked more.
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
    $m = array('base' => $base, 'jrs' => $jrs, 'pw' => $pw, 'aio' => $s['aio_own_url'], 'hosts' => $hosts);
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
        'TorrServer: ' . (isset($hosts[$s['ts_url']]) ? '<ts>' : AIO_TS_DEFAULT) . "\n" .
        aio_cgi_device() . "\n";
    $fp = aio_cgi_log_open(aio_cgi_log_path(), 'melange.log', AIO_CGI_LOG_MAX);
    if ($fp)
        aio_cgi_log_lines($fp, $m, false);
    else
        echo "\n" . aio_cgi_l('Лог пуст: после перезагрузки Дюны он начинается заново',
            'The log is empty: it starts anew after a restart of the Dune') . "\n";
    foreach (array('shell.log.old', 'shell.log') as $name)
    {
        echo "\n";
        $f = aio_cgi_run_path($name);
        $fp = aio_cgi_log_open($f, $name, AIO_CGI_SHELL_LOG_MAX);
        if ($fp)
            aio_cgi_log_lines($fp, $m, true);
        else
            echo $name . ': ' . (!is_link($f) && is_file($f) ? aio_cgi_l('не читается', 'cannot be read') :
                aio_cgi_l('нет файла', 'no file')) . "\n";
    }
    exit(0);
}
