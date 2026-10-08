<?php
// Audio tracks from ffprobe of the JacRed of the settings
// for rows without their own; tracks of an old list carried over by infoHash.
// Needs of main.php: aio_http_get, AIO_HTTP_TOO_BIG, aio_log, Aio::$settings;
// of parse.php: aio_str, aio_arr, aio_num, aio_cut, aio_lang_code, aio_tracks,
// AIO_TEXT_MAX; of voices_parse.php: aio_jr_title.

// Audio tracks from ffprobe of JacRed (its address in the settings) for
// streams without their own. false: no request, as 0.5.2.
// Tests may define it first.
if (!defined('AIO_JACRED'))
    define('AIO_JACRED', true);
// JacRed timeouts, s: connect, total of one request. No request once
// play_action has run AIO_JR_AFTER s (its limit is 60 s). The JacRed of the
// list are asked one by one until one answers, AIO_JR_BUDGET s for all of
// them: the first gets the whole AIO_JR_TIMEOUT, the next ones what is left
// (at least 1 s), so the worst wait is 2 s over that of a single JacRed.
define('AIO_JR_CONNECT', 2);
define('AIO_JR_TIMEOUT', 6);
define('AIO_JR_BUDGET', 8);
define('AIO_JR_AFTER', 20);
// A bigger JacRed reply is cut off while it loads (a large series without a season is ~1.3 MB).
define('AIO_JR_MAX_BYTES', 4000000);

// ISO 639-2 of ffprobe -> the language name of AIOStreams (as audioTracks
// have it); other codes as they are, '' for none.
function aio_jr_lang($code)
{
    static $names = array('rus' => 'Russian', 'eng' => 'English', 'ukr' => 'Ukrainian', 'jpn' => 'Japanese',
        'kor' => 'Korean', 'chi' => 'Chinese', 'zho' => 'Chinese', 'fre' => 'French', 'fra' => 'French',
        'ger' => 'German', 'deu' => 'German', 'ita' => 'Italian', 'spa' => 'Spanish', 'por' => 'Portuguese',
        'pol' => 'Polish', 'cze' => 'Czech', 'ces' => 'Czech', 'tur' => 'Turkish', 'ara' => 'Arabic',
        'hin' => 'Hindi', 'heb' => 'Hebrew', 'tha' => 'Thai', 'vie' => 'Vietnamese', 'swe' => 'Swedish',
        'nor' => 'Norwegian', 'dan' => 'Danish', 'fin' => 'Finnish', 'dut' => 'Dutch', 'nld' => 'Dutch',
        'hun' => 'Hungarian', 'rum' => 'Romanian', 'ron' => 'Romanian', 'bul' => 'Bulgarian',
        'srp' => 'Serbian', 'hrv' => 'Croatian', 'slv' => 'Slovenian', 'slo' => 'Slovak', 'slk' => 'Slovak',
        'gre' => 'Greek', 'ell' => 'Greek', 'lit' => 'Lithuanian', 'lav' => 'Latvian', 'est' => 'Estonian',
        'per' => 'Persian', 'fas' => 'Persian', 'ind' => 'Indonesian', 'may' => 'Malay', 'msa' => 'Malay');
    $code = strtolower($code);
    if (isset($names[$code]))
        return $names[$code];
    return preg_match('/^[a-z]{2,3}$/', $code) && $code !== 'und' ? $code : '';
}

// ffprobe of one file -> array(audio tracks as aio_tracks() makes them,
// subtitle language codes). Video (covers among it) is not looked at.
function aio_jr_media($ff)
{
    static $codecs = array('ac3' => 'DD', 'eac3' => 'DD+', 'truehd' => 'TrueHD', 'dts' => 'DTS',
        'aac' => 'AAC', 'flac' => 'FLAC', 'opus' => 'OPUS', 'mp3' => 'MP3');
    static $chs = array(1 => '1.0', 2 => '2.0', 6 => '5.1', 8 => '7.1');
    $audio = array();
    $subs = array();
    foreach ($ff as $x)
    {
        $type = aio_str($x, 'codec_type');
        $tags = aio_arr($x, 'tags');
        $lang = aio_jr_lang(aio_str($tags, 'language'));
        if ($type === 'subtitle' && $lang !== '' && count($subs) < 30)
            $subs[aio_lang_code($lang)] = aio_lang_code($lang);
        if ($type !== 'audio')
            continue;
        $title = aio_str($tags, 'title');
        $codec = strtolower(aio_str($x, 'codec_name'));
        $tag = isset($codecs[$codec]) ? $codecs[$codec] : (strpos($codec, 'pcm_') === 0 ? 'PCM' : '');
        // Atmos and DTS-HD MA are not in ffprobe: Atmos only by the title, MA
        // by the bit rate (the DTS core is at most 1.5 Mbit/s) or the title.
        if (($tag === 'TrueHD' || $tag === 'DD+') && preg_match('/atmos(?!\p{L})|\bJOC\b/iu', $title))
            $tag .= ' Atmos';
        if ($tag === 'DTS' && preg_match('/DTS[:\-]X(?![\p{L}\p{N}])/i', $title))
            $tag = 'DTS:X';
        if ($tag === 'DTS' && (max(aio_num(aio_str($tags, 'BPS')), aio_num(aio_str($x, 'bit_rate'))) > 1.6e6 ||
            preg_match('/DTS-?HD\s*MA|Master\s*Audio/i', $title)))
            $tag = 'DTS-HD MA';
        $ch = intval(aio_str($x, 'channels'));
        $audio[] = array('lang' => $lang, 'tag' => $tag, 'codec' => $codec,
            'channels' => isset($chs[$ch]) ? $chs[$ch] : '', 'title' => aio_jr_title($title));
    }
    return array(aio_tracks(array('audioTracks' => $audio)), array_values(array_filter($subs, 'strlen')));
}

// Audio of the card from tracks of JacRed: codecs from the best down, then
// channel layouts from the most, as aio_audio_text ("DD+ · DD · 7.1 / 5.1").
function aio_jr_audio_text($tracks)
{
    static $rank = array('TrueHD Atmos' => 1, 'DTS:X' => 2, 'DTS-HD MA' => 3, 'TrueHD' => 4, 'DD+ Atmos' => 5,
        'DTS' => 6, 'DD+' => 7, 'DD' => 8);
    $codecs = array();
    $ch = array();
    foreach ($tracks as $i => $t)
    {
        // Rank first, then file order (ksort, not an unstable sort).
        if ($t['codec'] !== '' && !in_array($t['codec'], $codecs, true))
            $codecs[sprintf('%d%03d', isset($rank[$t['codec']]) ? $rank[$t['codec']] : 9, $i)] = $t['codec'];
        if ($t['ch'] !== '')
            $ch[$t['ch']] = $t['ch'];
    }
    ksort($codecs);
    rsort($ch);
    $codecs = array_values($codecs);
    if ($ch)
        $codecs[] = implode(' / ', $ch);
    return aio_cut(implode(' · ', $codecs), AIO_TEXT_MAX);
}

// One card search in JacRed $conf (aio_jacred_list) -> its Results with
// timing in ['dec'], or null when it did not answer with them.
function aio_jacred_ask($conf, $path, $connect, $total)
{
    $url = $conf[0] . ($conf[3] !== '' ? $path . 'apikey=' . $conf[3] : rtrim($path, '&'));
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get($url, $err, $connect, $total, AIO_JR_MAX_BYTES);
    // The key stays out of the log; the own JacRed is "<jacred>" there (aio_mask).
    aio_log(sprintf('jacred: request %s%s: %s, %d bytes, %.0f ms', $conf[0], rtrim($path, '&'),
        $err !== '' ? $err : "HTTP $code", strlen($body), (microtime(true) - $t) * 1000));
    if ($err === AIO_HTTP_TOO_BIG)
    {
        aio_log('jacred: reply over ' . AIO_JR_MAX_BYTES . ' bytes, not decoded');
        return null;
    }
    if ($err !== '' || $code !== 200)
        return null;
    $t = microtime(true);
    $j = json_decode($body, true);
    $dec = (microtime(true) - $t) * 1000;
    unset($body);
    if (!is_array($j) || !isset($j['Results']) || !is_array($j['Results']))
    {
        aio_log(sprintf('jacred: bad reply, json_decode %.0f ms', $dec));
        return null;
    }
    return array('Results' => $j['Results'], 'dec' => $dec);
}

// Rows without audio tracks of their own get those of the same release
// (infoHash) from ffprobe of a JacRed of the settings, and its subtitle
// languages if they have none: one card search, after the AIOStreams reply,
// in the chosen JacRed, else jacred.stream (aio_jacred_list). Any failure leaves the rows
// as they are. $t0: start of play_action.
function aio_jacred($rows, $mv, $s, $t0)
{
    $need = array();
    foreach ($rows as $r)
    {
        if (!$r['tracks'] && $r['hash'] !== '')
            $need[$r['hash']] = true;
    }
    if (!$need)
        return $rows;
    if (microtime(true) - $t0 > AIO_JR_AFTER)
    {
        aio_log(sprintf('jacred: skipped, play_action took %.1f s', microtime(true) - $t0));
        return $rows;
    }
    $list = Aio::$settings['jrs'];
    if (!$list)
    {
        aio_log('jacred: no address in the settings, skipped');
        return $rows;
    }
    $q = array('title' => $mv['title'], 'title_original' => $mv['native'],
        'year' => preg_match('/^[0-9]{4}$/', $mv['year']) ? $mv['year'] : '', 'is_serial' => $mv['series'] ? '2' : '1',
        'season' => $mv['series'] ? strval($s) : '');
    $path = '/api/v2.0/indexers/all/results?';
    foreach ($q as $k => $v)
    {
        if ($v !== '')
            $path .= "$k=" . rawurlencode($v) . '&';
    }
    $end = microtime(true) + AIO_JR_BUDGET;
    $mem = memory_get_usage();
    $j = null;
    foreach ($list as $i => $conf)
    {
        $left = (int) floor($end - microtime(true));
        if ($left < 1)
        {
            aio_log('jacred: out of time, ' . (count($list) - $i) . ' not asked');
            break;
        }
        $j = aio_jacred_ask($conf, $path, min(AIO_JR_CONNECT, $left), min(AIO_JR_TIMEOUT, $left));
        if ($j)
            break;
    }
    if (!$j)
        return $rows;
    $dec = $j['dec'];
    $t = microtime(true);
    $n = count($j['Results']);
    $media = array();
    foreach ($j['Results'] as $x)
    {
        // rutracker gives the hash in upper case.
        if (is_array($x) && isset($x['MagnetUri']) && is_string($x['MagnetUri']) && isset($x['ffprobe']) && is_array($x['ffprobe']) &&
            preg_match('/btih:([0-9a-fA-F]{40})(?![0-9a-zA-Z])/', $x['MagnetUri'], $m) &&
            isset($need[$h = strtolower($m[1])]) && !isset($media[$h]))
        {
            $jm = aio_jr_media($x['ffprobe']);
            $media[$h] = array('tracks' => $jm[0], 'subs' => $jm[1]);
        }
    }
    $peak = memory_get_peak_usage();
    unset($j);
    $filled = aio_fill_tracks($rows, $media);
    $got = 0;
    foreach ($filled as $i => $r)
        $got += $r['tracks'] && !$rows[$i]['tracks'] ? 1 : 0;
    $rows = $filled;
    aio_log(sprintf('jacred: json_decode %.0f ms, %d results, %d of %d releases found, %d rows got tracks, ' .
        'match %.1f ms, memory %.1f MB before, peak %.1f MB', $dec, $n, count($media), count($need), $got,
        (microtime(true) - $t) * 1000, $mem / 1048576, $peak / 1048576));
    return $rows;
}

// Rows without tracks get those of an old row with the same hash (JacRed of
// the season; a release has the same tracks in every episode).
function aio_carry_tracks($rows, $old)
{
    $by = array();
    foreach ($old as $r)
    {
        if ($r['tracks'] && $r['hash'] !== '' && !isset($by[$r['hash']]))
            $by[$r['hash']] = $r;
    }
    return aio_fill_tracks($rows, $by);
}

// Rows without audio tracks get those of their release in $by_hash (infoHash
// => array('tracks' => ..., 'subs' => ...)), its subtitle languages if they
// have none and the audio of the card from the tracks if they have none.
function aio_fill_tracks($rows, $by_hash)
{
    foreach ($rows as $i => $r)
    {
        if ($r['tracks'] || !isset($by_hash[$r['hash']]))
            continue;
        $rows[$i]['tracks'] = $by_hash[$r['hash']]['tracks'];
        if ($r['audio_full'] === '')
            $rows[$i]['audio_full'] = aio_jr_audio_text($rows[$i]['tracks']);
        if (!$r['subs'])
            $rows[$i]['subs'] = $by_hash[$r['hash']]['subs'];
    }
    return $rows;
}
