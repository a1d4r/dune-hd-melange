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

// A network URL of aio_cgi_mask_shell: scheme://host[:port], the user
// dropped, the path and query -> "/…"; a LAN name as host (no dot, or .lan,
// .local, .home, .localdomain, .internal, .home.arpa) -> "<host>". IPs and
// the tags of aio_mask_secrets ("<aio>", "<ts>"...) are left to their rules.
function aio_cgi_mask_url($m)
{
    $h = $m[2];
    if ($h !== '' && $h[0] !== '<' && $h[0] !== '[' && !preg_match('~^[0-9.]+$~', $h) && strtolower($h) !== 'localhost' &&
        (strpos($h, '.') === false || preg_match('~\.(?:lan|local|home|localdomain|internal|home\.arpa)\.?$~i', $h)))
        $h = '<host>';
    return $m[1] . '://' . $h . (isset($m[3]) ? $m[3] : '') . (isset($m[4]) && $m[4] !== '' ? '/…' : '');
}

// A line of shell.log, after aio_mask_secrets, with the home network and the
// box hidden: the serial number of the Dune, MACs, network URLs
// (aio_cgi_mask_url), IPv6 but ::1, IPv4 but 127.0.0.1, names of LAN hosts of
// the net scanner, values of auth/serial/token/password/key/cookie in k=v,
// k: v, "k": "v" (a quoted one whole, of a cookie or authorization the rest of
// the line). The events (pid, time, module, errors) stay. A regex that fails
// (pcre.backtrack_limit on a long token) -> the line hidden, not lost unseen.
function aio_cgi_mask_shell($s)
{
    $h = '[0-9A-Fa-f]{1,4}';
    $kv = '((?<![A-Za-z0-9_\-])(?:[A-Za-z0-9_\-]*(?:auth|serial|token|secret|passw(?:or)?d|cookie|key)[A-Za-z0-9_\-]*|pass|pwd)' .
        '"?(?:=|:[ \t]*))';
    $steps = array(
        array('~[0-9A-Fa-f]{4}(?:-[0-9A-Fa-f]{4}){5}-[0-9A-Fa-f]+~', '<serial>'),
        array('~(?<![0-9A-Fa-f:\-])[0-9A-Fa-f]{2}([:\-])[0-9A-Fa-f]{2}(?:\1[0-9A-Fa-f]{2}){4}(?![0-9A-Fa-f:\-])~', '<mac>'),
        // Also JSON-escaped (":\/\/").
        array('~(?<![A-Za-z0-9+.\-])(https?|smb|nfs|s?ftps?|rtsps?|rtmp[a-z]*|rtp|udp|mms[a-z]*|(?:web)?davs?|wss?)' .
            ':(?:\\\\?/){2}(?:[^\s/\\\\?#@\'"]*@)?(\[[0-9A-Fa-f:.]*\]|<[a-z]+>|[A-Za-z0-9.\-_\~%]*)(:[0-9]*)?' .
            '((?:\\\\?/|[?#])[^\s\'"]*)?~i', 'aio_cgi_mask_url'),
        // An address of aio_mask_secrets ("<aio>/path") as a URL; "<manifest>/" keeps its path (no secret left).
        array('~(<(?:aio|ts|server|jacred)>(?::[0-9]+)?)(?:\\\\?/|[?#])[^\s\'"]*~', '$1/…'),
        // Not a time ("16:04:43"): 8 groups or "::"; IPv4-mapped too.
        array("~(?<![0-9A-Za-z:.])(?!::1(?![0-9A-Za-z:]))(?:(?:$h:){7}$h|$h(?::$h)*::(?:$h(?::$h)*)?|::$h(?::$h)*)" .
            '(?::(?:[0-9]{1,3}\.){3}[0-9]{1,3})?(?![0-9A-Za-z:]|\.[0-9])~', '<ip>'),
        array('~(?<![0-9.])(?!127\.0\.0\.1(?![0-9]|\.[0-9]))(?:[0-9]{1,3}\.){3}[0-9]{1,3}(?![0-9]|\.[0-9])~', '<ip>'),
        array('~(nmblookup got server \')[^\']*~', '$1<host>'),
        array('~(net_scanner I: added \S+ - )[^\r\n]+~', '$1<host>'),
        array('~(Nmap scan report for )(?!<ip>)[^\s(]+~', '$1<host>'),
        // The raw answer of nmblookup: "\tNAME <00> - B <ACTIVE>".
        array('~^(\s+)\S[^<\r\n]*?(?=\s*<[0-9A-Fa-f]{2}> )~', '$1<host>'),
        array("~$kv\"[^\"\\r\\n]*\"?~i", '$1"<…>"'),
        array("~$kv'[^'\\r\\n]*'?~i", '$1\'<…>\''),
        array('~((?<![A-Za-z0-9_\-])(?:set-)?cookie|(?<![A-Za-z0-9_\-])(?:proxy-)?authorization)(:[ \t]*)(?!["\']<…>)[^\r\n]+~i',
            '$1$2<…>'),
        array("~$kv(?:Bearer |Basic )?[^\\s&\"',;]+~i", '$1<…>'),
    );
    foreach ($steps as $st)
    {
        $s = $st[1] === 'aio_cgi_mask_url' ? preg_replace_callback($st[0], $st[1], $s) : preg_replace($st[0], $st[1], $s);
        if (!is_string($s))
            return "(line hidden)\n";
    }
    return $s;
}

// $f (not through a symlink: this runs as root) at its last $max bytes from a
// whole line, "<name>: N bytes" and the cut printed -> the handle; no file
// (a symlink, a dir) -> $none printed (default "<name>: нет файла"), not
// readable -> "<name>: не читается"; false for both.
function aio_cgi_log_open($f, $name, $max, $none = null)
{
    clearstatcache();
    if (is_link($f) || !is_file($f))
    {
        echo $none !== null ? $none : $name . ': ' . aio_cgi_l('нет файла', 'no file') . "\n";
        return false;
    }
    $fp = @fopen($f, 'rb');
    // The size of the file opened: the shell may rotate it in between.
    $st = $fp ? fstat($fp) : false;
    if (!$st)
    {
        if ($fp)
            fclose($fp);
        echo $name . ': ' . aio_cgi_l('не читается', 'cannot be read') . "\n";
        return false;
    }
    $size = $st['size'];
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
// aio_mask_secrets; $shell: aio_cgi_mask_shell too), the last one ended. A
// line the mask lost (a regex failed: '' for a line not empty) is said so.
// Streamed.
function aio_cgi_log_lines($fp, $m, $shell)
{
    $n = 0;
    while (($line = fgets($fp)) !== false)
    {
        $out = aio_mask_secrets($line, $m['base'], $m['jrs'], $m['pw'], $m['aio'], $m['hosts']);
        if (!is_string($out) || $out === '')
            $out = "(line hidden)\n";
        $out = $shell ? aio_cgi_mask_shell($out) : $out;
        echo substr($out, -1) === "\n" ? $out : "$out\n";
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
    $fp = aio_cgi_log_open(aio_cgi_log_path(), 'melange.log', AIO_CGI_LOG_MAX,
        "\n" . aio_cgi_l('Лог пуст: после перезагрузки Дюны он начинается заново',
        'The log is empty: it starts anew after a restart of the Dune') . "\n");
    if ($fp)
        aio_cgi_log_lines($fp, $m, false);
    foreach (array('shell.log.old', 'shell.log') as $name)
    {
        echo "\n";
        $fp = aio_cgi_log_open(aio_cgi_run_path($name), $name, AIO_CGI_SHELL_LOG_MAX);
        if ($fp)
            aio_cgi_log_lines($fp, $m, true);
    }
    exit(0);
}
