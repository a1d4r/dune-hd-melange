<?php
// Markers of an episode (0.46.0): the player's "Skip intro" (intro, recap)
// and "Next episode" with a countdown (closing credits; dropped by the player
// on the last item of the playlist). Timecodes: GET /api/v1/segments of the
// AIOStreams server of the streams (Melange's fork; other servers have no
// such route: 404, no markers). aio_play (playback.php) asks for the episode
// that starts.
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

// Markers of episode $s/$e of IMDb id $imdb, the file $dur s long (0:
// unknown, then not sent) from the server of the streams -> the list, array()
// on any failure (logged).
/**
 * @param string $imdb
 * @param int $s
 * @param int $e
 * @param float $dur
 * @return list<array<string, int|string>>
 */
function aio_markers($imdb, $s, $e, $dur)
{
    // A firmware PHP API without markers (older models): nothing to ask for.
    if (!class_exists('StreamMarker') || !defined('PluginVodSeriesInfo::markers'))
        return array();
    $root = aio_seg_root(Aio::$settings['base']);
    if ($root === '' || !preg_match('/^tt[0-9]{1,10}$/D', $imdb))
        return array();
    $ep = sprintf('S%02dE%02d', $s, $e);
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
