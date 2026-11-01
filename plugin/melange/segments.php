<?php
// Markers of an episode (0.46.0): the player's "Skip intro" (intro, recap)
// and "Next episode" with a countdown (closing credits; dropped by the player
// on the last item of the playlist). Timecodes: GET /api/v1/segments of the
// AIOStreams server of the streams (Melange's fork; other servers have no
// such route: 404, no markers). aio_play (playback.php) asks for the episode
// that starts; a file of no known length is read first: the server's timecodes
// are of the file only with its duration, else 10-20 s late on some releases.
// Reads Aio::$settings; the time left of the operation: aio_op_total
// (playback.php). Functions are global; the require order is in main.php.
// Types in the PHPDoc: phpstan-types.neon of the repository.

// Seconds: the server of the streams has just answered, so the connection
// is quick; a reply from its cache takes a fraction of a second. A slower
// one leaves this episode without markers rather than delay the video.
define('AIO_SEG_CONNECT', 2);
define('AIO_SEG_TOTAL', 3);
// A reply this big is not a list of three segments.
define('AIO_SEG_MAX_BYTES', 65536);
// A marker ending this far past the end of the file is of another release
// (the same rule as the server's); a closer one is cut at the end.
define('AIO_SEG_SLACK', 20);
// A longer file is taken for an unknown length: the server refuses such a
// duration (400) and would give no markers at all.
define('AIO_SEG_DUR_MAX', 86400);
// The length of the file unknown: credits starting earlier, s, are garbage
// (an episode is 20 min or more, its credits near the end). A false one
// would switch to the next episode in the middle of this one.
define('AIO_SEG_OUTRO_MIN', 600);
// The head of the file for its length: seconds, as the markers, and at most
// this many bytes (mkvmerge puts Info within the first 7 KB).
define('AIO_MKV_CONNECT', 2);
define('AIO_MKV_TOTAL', 3);
define('AIO_MKV_MAX_BYTES', 65536);

// Manifest base $base -> the root of its AIOStreams server (what is before
// /stremio/), '' when the address has no /stremio/.
/**
 * @param string $base
 * @return string
 */
function aio_seg_root($base)
{
    return preg_match('~^(https?://[^/]+(?:/[^/]+)*?)/stremio/~', $base, $m) ? $m[1] : '';
}

// Body of /api/v1/segments, duration of the file $dur (s, 0: unknown) ->
// markers for PluginVodSeriesInfo::markers (recap, intro, closing credits),
// or null when the body is not the reply of the route. Whole seconds: from
// rounded up, to down (a button never covers the episode); 0 <= from < to; a
// type of no marker is skipped. Then the plausible ones only, the others to
// $drop ("outro 10-60 (before half)"): a type of more than one is dropped
// whole; intro and recap end by the half of a known length; the credits
// start after the end of the intro and the recap kept, from the half of a
// known length, from AIO_SEG_OUTRO_MIN s of an unknown one.
/**
 * @param string $body
 * @param float $dur
 * @param list<string> $drop
 * @return list<array<string, int|string>>|null
 */
function aio_seg_markers($body, $dur, &$drop)
{
    $drop = array();
    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j['success']) || $j['success'] !== true || !isset($j['data']['segments']) ||
        !is_array($j['data']['segments']))
        return null;
    $types = array('recap' => STREAM_MARKER_RECAP, 'intro' => STREAM_MARKER_INTRO,
        'outro' => STREAM_MARKER_CLOSING_CREDITS);
    $by = array();
    foreach ($j['data']['segments'] as $sg)
    {
        if (!is_array($sg) || !isset($sg['type'], $sg['start'], $sg['end']) || !is_string($sg['type']) ||
            !isset($types[$sg['type']]) || !(is_int($sg['start']) || is_float($sg['start'])) ||
            !(is_int($sg['end']) || is_float($sg['end'])) || $sg['start'] < 0 || $sg['start'] >= $sg['end'] ||
            $sg['end'] > 1e6)
            continue;
        $to = $sg['end'];
        if ($dur > 0)
        {
            if ($to > $dur + AIO_SEG_SLACK)
                continue;
            $to = min($to, $dur);
        }
        $from = (int) ceil($sg['start']);
        $to = (int) floor($to);
        if ($from < $to)
            $by[$sg['type']][] = array($from, $to);
    }
    $out = array();
    // The end of the intro and the recap kept.
    $after = 0;
    foreach ($types as $t => $type)
    {
        if (!isset($by[$t]))
            continue;
        list($from, $to) = $by[$t][0];
        $why = '';
        if (count($by[$t]) > 1)
            $why = count($by[$t]) . " of $t";
        else if ($t !== 'outro')
            $why = $dur > 0 && $to > $dur / 2 ? 'after half' : '';
        else if ($from < $after)
            $why = 'before the end of intro/recap';
        else if ($dur > 0)
            $why = $from < $dur / 2 ? 'before half' : '';
        else if ($from < AIO_SEG_OUTRO_MIN)
            $why = 'before ' . AIO_SEG_OUTRO_MIN . ' s';
        if ($why !== '')
        {
            foreach ($by[$t] as $m)
                $drop[] = "$t {$m[0]}-{$m[1]} ($why)";
            continue;
        }
        if ($t !== 'outro')
            $after = max($after, $to);
        $out[] = array(StreamMarker::type => $type, StreamMarker::from => $from, StreamMarker::to => $to);
    }
    return $out;
}

// The header of the EBML element at $p of $b, up to $end -> array(its ID as
// bytes with the length mark, its size or -1.0 unknown), $p moved to its data;
// null when broken or cut, $p as it was. The size is a float: 8 bytes do not
// fit the int of the 32-bit PHP of the Dune, a huge one just fails "past the
// buffer". Moves $p at least 2 bytes: a walk always ends.
/**
 * @param string $b
 * @param int $p
 * @param int $end
 * @return array{string, float}|null
 */
function aio_mkv_el($b, &$p, $end)
{
    if ($p >= $end)
        return null;
    $c = ord($b[$p]);
    $l = $c >= 0x80 ? 1 : ($c >= 0x40 ? 2 : ($c >= 0x20 ? 3 : ($c >= 0x10 ? 4 : 0)));
    if ($l === 0 || $p + $l >= $end)
        return null;
    $id = substr($b, $p, $l);
    $q = $p + $l;
    $c = ord($b[$q]);
    $l = 1;
    $bit = 0x80;
    while ($bit > 0 && !($c & $bit))
    {
        $l++;
        $bit >>= 1;
    }
    if ($bit === 0 || $q + $l > $end)
        return null;
    $size = (float) ($c & ($bit - 1));
    $all = ($c & ($bit - 1)) === $bit - 1;
    for ($i = 1; $i < $l; $i++)
    {
        $c = ord($b[$q + $i]);
        $size = $size * 256 + $c;
        $all = $all && $c === 255;
    }
    $p = $q + $l;
    return array($id, $all ? -1.0 : $size);
}

// Info of a Matroska file (its data) -> seconds, 0.0 without a finite
// Duration in 1..AIO_SEG_DUR_MAX.
/**
 * @param string $b
 * @return float
 */
function aio_mkv_info($b)
{
    $n = strlen($b);
    $p = 0;
    $scale = 1000000.0;
    $dur = 0.0;
    while (($el = aio_mkv_el($b, $p, $n)) !== null)
    {
        if ($el[1] < 0 || $p + $el[1] > $n)
            return 0.0;
        $size = (int) $el[1];
        if ($el[0] === "\x2A\xD7\xB1")
        {
            // TimestampScale, an unsigned of 1..8 bytes.
            if ($size < 1 || $size > 8)
                return 0.0;
            $scale = 0.0;
            for ($i = 0; $i < $size; $i++)
                $scale = $scale * 256 + ord($b[$p + $i]);
        }
        else if ($el[0] === "\x44\x89")
        {
            if ($size !== 4 && $size !== 8)
                return 0.0;
            // Big-endian; unpack of PHP 5.3 has the order of the machine only.
            $raw = substr($b, $p, $size);
            $u = unpack($size === 4 ? 'f' : 'd', pack('d', 1.0) === "\x3F\xF0\0\0\0\0\0\0" ? $raw : strrev($raw));
            if ($u)
                $dur = $u[1];
        }
        $p += $size;
    }
    $sec = $dur * $scale / 1e9;
    // is_finite first: PHP 5.3 may take a comparison with NaN for true.
    return is_finite($sec) && $sec >= 1 && $sec <= AIO_SEG_DUR_MAX ? $sec : 0.0;
}

// The first bytes $b of a file -> its length from the Info of a Matroska file,
// s; 0.0 when it is not there, $why says what is missing. A plain walk of the
// top level of the Segment up to Info: a Cluster, the end of the buffer or an
// element past it end it.
/**
 * @param string $b
 * @param string|null $why
 * @param-out string $why
 * @return float
 */
function aio_mkv_duration($b, &$why = null)
{
    $why = 'not MKV';
    $n = strlen($b);
    $p = 0;
    $el = aio_mkv_el($b, $p, $n);
    if ($el === null || $el[0] !== "\x1A\x45\xDF\xA3" || $el[1] < 0 || $p + $el[1] > $n)
        return 0.0;
    $p += (int) $el[1];
    $el = aio_mkv_el($b, $p, $n);
    if ($el === null || $el[0] !== "\x18\x53\x80\x67")
        return 0.0;
    $why = 'no Info';
    // Its size may be unknown, and it is usually past the buffer.
    $end = $el[1] < 0 ? $n : (int) min($n, $p + $el[1]);
    while (($el = aio_mkv_el($b, $p, $end)) !== null && $el[0] !== "\x1F\x43\xB6\x75" && $el[1] >= 0 && $p + $el[1] <= $end)
    {
        if ($el[0] === "\x15\x49\xA9\x66")
        {
            $why = 'no Duration';
            return aio_mkv_info((string) substr($b, $p, (int) $el[1]));
        }
        $p += (int) $el[1];
    }
    return 0.0;
}

// The length of the file at stream URL $url (Basic auth $auth, '' none) from
// its first bytes, $ep for the log -> s, 0.0 when it did not work: the video
// matters more than the length, no dialog. Never the URL in the log: its tokens.
/**
 * @param string $url
 * @param string $ep
 * @param string $auth
 * @return float
 */
function aio_mkv_read($url, $ep, $auth)
{
    $total = aio_op_total(AIO_MKV_TOTAL, 1, "mkv length: $ep");
    if ($total === 0)
        return 0.0;
    $t = microtime(true);
    $err = '';
    list($code, $b) = aio_http_range($url, AIO_MKV_MAX_BYTES, $err, min(AIO_MKV_CONNECT, $total), $total, $auth);
    $t = microtime(true) - $t;
    $why = $err !== '' ? aio_cut(aio_mask($err), 120) : "HTTP $code";
    $sec = $err === '' && ($code === 200 || $code === 206) ? aio_mkv_duration($b, $why) : 0.0;
    if ($sec > 0)
        aio_log(sprintf('mkv length: %s, %.1f s, %.2f s', $ep, $sec, $t));
    else
        aio_log(sprintf('mkv length: %s: none (%s), %.2f s', $ep, $why, $t));
    return $sec;
}

// Markers of episode $s/$e of IMDb id $imdb, the file $dur s long (0:
// unknown, then not sent) from the server of the streams -> the list, array()
// on any failure (logged), or with the markers of the settings off: no request
// at all. A length not in 1..AIO_SEG_DUR_MAX and a stream URL $stream ('': none
// to read; $auth: its Basic auth): the length comes from its head (aio_mkv_read).
/**
 * @param string $imdb
 * @param int $s
 * @param int $e
 * @param float $dur
 * @param string $stream
 * @param string $auth
 * @return list<array<string, int|string>>
 */
function aio_markers($imdb, $s, $e, $dur, $stream, $auth)
{
    $ep = sprintf('S%02dE%02d', $s, $e);
    if (!Aio::$settings['markers'])
    {
        aio_log("markers: $ep: off in the settings");
        return array();
    }
    // A firmware PHP API without markers (older models): nothing to ask for.
    if (!class_exists('StreamMarker') || !defined('PluginVodSeriesInfo::markers'))
        return array();
    $root = aio_seg_root(Aio::$settings['base']);
    if ($root === '' || !preg_match('/^tt[0-9]{1,10}$/D', $imdb))
        return array();
    if ($stream !== '' && !($dur >= 1 && $dur <= AIO_SEG_DUR_MAX))
        $dur = aio_mkv_read($stream, $ep, $auth);
    $total = aio_op_total(AIO_SEG_TOTAL, 1, "markers: $ep");
    if ($total === 0)
        return array();
    $sec = $dur >= 1 && $dur <= AIO_SEG_DUR_MAX ? (int) round($dur) : 0;
    if ($sec === 0)
        $dur = 0.0;
    $url = "$root/api/v1/segments?idType=imdbId&idValue=$imdb&season=$s&episode=$e" . ($sec > 0 ? "&duration=$sec" : '');
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get($url, $err, min(AIO_SEG_CONNECT, $total), $total, AIO_SEG_MAX_BYTES);
    $t = microtime(true) - $t;
    $drop = array();
    $mk = $err === '' && $code === 200 ? aio_seg_markers($body, $dur, $drop) : null;
    $what = array();
    foreach ($mk ? $mk : array() as $m)
        $what[] = $m[StreamMarker::type] . ' ' . $m[StreamMarker::from] . '-' . $m[StreamMarker::to];
    if ($drop)
        $what[] = 'dropped ' . implode(', ', $drop);
    aio_log(sprintf('markers: %s, duration %s: %s, %s, %.2f s', $ep, $sec > 0 ? "$sec s" : 'unknown',
        $err !== '' ? $err : "HTTP $code", $mk === null ? 'none' : ($what ? implode(', ', $what) : 'no segments'), $t));
    return $mk ? $mk : array();
}
