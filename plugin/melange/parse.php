<?php
// AIOStreams reply -> rows of the list: accessors of decoded JSON, a stream
// -> row (aio_row), its badges, audio and tracks. Pure functions: no firmware
// API, no log, no state.
// Types in the PHPDoc (AioRow and others): phpstan-types.neon of the repository.

// Characters of a joined list (trackers, languages) on a screen, before wrapping.
define('AIO_TEXT_MAX', 300);
// A bit rate above this (bit/s) is not believed.
define('AIO_RATE_MAX', 1e9);
// PCM is not in kingsizew/badges, its badge is our own: false drops it.
define('AIO_BADGE_PCM', true);
// streamData.sources (aio_addons): items looked at - and ids of each
// "cached" -, add-ons kept of them.
define('AIO_ADDONS_SCAN', 30);
define('AIO_ADDONS_MAX', 10);

/**
 * @param string $s
 * @param int $max
 * @return string
 */
function aio_cut($s, $max)
{
    return mb_strlen($s, 'UTF-8') > $max ? mb_substr($s, 0, $max, 'UTF-8') . '...' : $s;
}

// Characters of a line of a dialog 1100 px wide: the shell cuts a longer one
// in the middle (~65 fit, seen on the device 08.10.2026).
define('AIO_DIALOG_LINE', 60);

// Text (an error detail) -> at most 3 lines of a dialog, wrapped at spaces;
// a word longer than a line is cut hard, text past 3 lines ends in "...".
/**
 * @param mixed $s
 * @return list<string>
 */
function aio_dialog_lines($s)
{
    $max = AIO_DIALOG_LINE;
    $out = array();
    // Bad UTF-8 counts differently in mb_strrpos and mb_substr.
    $s = trim(preg_replace('/[ \t\r\n]+/', ' ', aio_utf8($s)));
    while ($s !== '')
    {
        $n = mb_strlen($s, 'UTF-8');
        if ($n <= $max || count($out) === 2)
        {
            $out[] = $n <= $max ? $s : aio_cut($s, $max - 3);
            break;
        }
        $sp = mb_strrpos(mb_substr($s, 0, $max + 1, 'UTF-8'), ' ', 0, 'UTF-8');
        $cut = $sp > 0 ? $sp : $max;
        $out[] = rtrim(mb_substr($s, 0, $cut, 'UTF-8'));
        $s = ltrim(mb_substr($s, $cut, $n, 'UTF-8'));
    }
    return $out;
}

// --- Small accessors for decoded JSON (arrays: json_decode(..., true)).

// json_decode of PHP 5.3 turns a lone surrogate ("\ud83d") into bytes that
// are not UTF-8, and json_encode then makes the whole string null.
/**
 * @param mixed $s
 * @return string
 */
function aio_utf8($s)
{
    return mb_convert_encoding(strval($s), 'UTF-8', 'UTF-8');
}

/**
 * @param mixed $a
 * @param int|string $k
 * @return string
 */
function aio_str($a, $k)
{
    return is_array($a) && isset($a[$k]) && is_scalar($a[$k]) && !is_bool($a[$k]) ? trim(aio_utf8($a[$k])) : '';
}

// http(s) picture with the shell's image cache flag (as vendor
// HD::enable_caching_for_image_url), or '' for no picture.
/**
 * @param string $url
 * @return string
 */
function aio_img($url)
{
    if (!preg_match('~^https?://[^\\s]+$~', $url))
        return '';
    if (strpos($url, 'dune_image_cache=1') !== false)
        return $url;
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'dune_image_cache=1';
}

// One line of plain text for the screens: no line breaks or tabs; '|' as '/'
// (it split item_detailed_info of the list screen, removed in 0.12.0).
/**
 * @param mixed $s
 * @return string
 */
function aio_clean($s)
{
    return trim(str_replace('|', '/', preg_replace('/[\\r\\n\\t]+/', ' ', strval($s))));
}

// A positive finite number from a JSON value (sizes come quoted), else 0.
/**
 * @param mixed $v
 * @return float
 */
function aio_num($v)
{
    $f = is_numeric($v) ? (float) $v : 0.0;
    return $f > 0 && $f < 1e18 ? $f : 0.0;
}

/**
 * @param mixed $a
 * @param int|string $k
 * @return array<mixed>
 */
function aio_arr($a, $k)
{
    return is_array($a) && isset($a[$k]) && is_array($a[$k]) ? $a[$k] : array();
}

/**
 * @param mixed $obj
 * @param string $k
 * @param int $def
 * @return int
 */
function aio_int($obj, $k, $def)
{
    return is_object($obj) && isset($obj->$k) && is_numeric($obj->$k) ? intval($obj->$k) : $def;
}

// Language of AIOStreams (parsedFile.languages) -> short code: ISO 639-1
// where there is one, UA as on Russian trackers; '' for Unknown.
/**
 * @param string $name
 * @return string
 */
function aio_lang_code($name)
{
    static $codes = array('English' => 'EN', 'Japanese' => 'JA', 'Chinese' => 'ZH',
        'Russian' => 'RU', 'Arabic' => 'AR', 'Portuguese' => 'PT', 'Portuguese (Brazil)' => 'PT-BR',
        'Spanish' => 'ES', 'French' => 'FR', 'German' => 'DE', 'Italian' => 'IT', 'Korean' => 'KO',
        'Hindi' => 'HI', 'Bengali' => 'BN', 'Punjabi' => 'PA', 'Marathi' => 'MR', 'Gujarati' => 'GU',
        'Tamil' => 'TA', 'Telugu' => 'TE', 'Kannada' => 'KN', 'Malayalam' => 'ML', 'Thai' => 'TH',
        'Vietnamese' => 'VI', 'Indonesian' => 'ID', 'Turkish' => 'TR', 'Hebrew' => 'HE',
        'Persian' => 'FA', 'Ukrainian' => 'UA', 'Greek' => 'EL', 'Lithuanian' => 'LT',
        'Latvian' => 'LV', 'Estonian' => 'ET', 'Polish' => 'PL', 'Czech' => 'CS', 'Slovak' => 'SK',
        'Hungarian' => 'HU', 'Romanian' => 'RO', 'Bulgarian' => 'BG', 'Serbian' => 'SR',
        'Croatian' => 'HR', 'Slovenian' => 'SL', 'Dutch' => 'NL', 'Danish' => 'DA', 'Finnish' => 'FI',
        'Swedish' => 'SV', 'Norwegian' => 'NO', 'Malay' => 'MS', 'Latino' => 'LAT',
        'Dual Audio' => 'DUAL', 'Dubbed' => 'DUB', 'Multi' => 'MULTI', 'Original' => 'ORIG',
        'Unknown' => '');
    if (isset($codes[$name]))
        return $codes[$name];
    // A name AIOStreams may add later: its first letters.
    return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
}

// Audio badges: id => array(family, tier, audioTags it needs), the best of a
// family first (Smart Tier of Nuvio).
/**
 * @return array<string, array{string, int, list<string>}>
 */
function aio_audio_rules()
{
    return array(
        'dolby-atmos-truehd' => array('dolby', 1, array('Atmos', 'TrueHD')),
        'dolby-atmos-digital-plus' => array('dolby', 2, array('Atmos', 'DD+')),
        'dolby-atmos' => array('dolby', 2, array('Atmos')),
        'dolby-truehd' => array('dolby', 2, array('TrueHD')),
        'dolby-digital-plus' => array('dolby', 3, array('DD+')),
        'dolby-digital' => array('dolby', 4, array('DD')),
        'dts-x-hd-ma' => array('dts', 1, array('DTS:X', 'DTS-HD MA')),
        'dts-x-hd' => array('dts', 2, array('DTS:X', 'DTS-HD')),
        'dts-x' => array('dts', 2, array('DTS:X')),
        'dts-hd-ma' => array('dts', 2, array('DTS-HD MA')),
        'dts-hd' => array('dts', 3, array('DTS-HD')),
        'dts-es' => array('dts', 4, array('DTS-ES')),
        'dts' => array('dts', 4, array('DTS')),
        'flac' => array('lossless', 2, array('FLAC')),
        'pcm' => array('lossless', 2, array('PCM')),
        'opus' => array('other', 4, array('OPUS')),
        'aac' => array('other', 4, array('AAC')));
}

// parsedFile of AIOStreams -> badges of a release, for the screen. Ids are
// the file names of kingsizew/badges; the hierarchy is the Smart Tier of
// Nuvio, only AI stands apart. Tags come in
// any order: only presence counts. $res overrides parsedFile.resolution.
// -> array('res' => id|'', 'src' => id|'', 'vis' => id|'', 'ai' => bool,
//    'audio' => array() or array(the best audio id), 'tags' => IMAX, editions, flags)
/**
 * @param array<mixed> $pf
 * @param string|null $res
 * @return AioBadges
 */
function aio_badges($pf, $res = null)
{
    $set = array();
    foreach (array('visualTags', 'audioTags', 'editions') as $k)
    {
        $set[$k] = array();
        foreach (aio_arr($pf, $k) as $t)
        {
            if (is_string($t))
                $set[$k][$t] = true;
        }
    }
    $v = $set['visualTags'];
    $a = $set['audioTags'];

    $res = strtolower(is_null($res) ? aio_str($pf, 'resolution') : $res);
    $res = $res === '2160p' ? '4k' : ($res === '2k' ? '1440p' : $res);
    if (!in_array($res, array('4k', '1440p', '1080p', '720p', '576p', '480p', '360p', '240p', '144p'), true))
        $res = '';

    $srcs = array('BluRay REMUX' => 'remux', 'DVD REMUX' => 'remux', 'BluRay' => 'bluray',
        'WEB-DL' => 'web-dl', 'WEBRip' => 'webrip', 'HDRip' => 'hdrip', 'HC HD-Rip' => 'hc-hdrip',
        'DVDRip' => 'dvdrip', 'HDTV' => 'hdtv', 'CAM' => 'cam', 'TS' => 'ts', 'TC' => 'tc', 'SCR' => 'scr');
    $q = aio_str($pf, 'quality');
    $src = isset($srcs[$q]) ? $srcs[$q] : '';

    // Picture: one badge, DV with its HDR first, 10bit last; SDR has none.
    // HDR+DV, DV Only, HDR Only: service tags AIOStreams cuts before the reply.
    $dv = isset($v['DV']) || isset($v['HDR+DV']) || isset($v['DV Only']);
    $hdr = isset($v['HDR10+']) ? 'hdr10-plus' : (isset($v['HDR10']) ? 'hdr10' :
        (isset($v['HDR']) || isset($v['HDR+DV']) || isset($v['HDR Only']) ? 'hdr' : ''));
    if ($dv)
        $vis = 'dolby-vision' . ($hdr !== '' ? "-$hdr" : '');
    else if ($hdr !== '')
        $vis = $hdr;
    else
        $vis = isset($v['HLG']) ? 'hlg' : (isset($v['10bit']) ? '10bit' : '');

    // Audio: one badge, the lowest tier of all the rules that hold; on a tie
    // the first in the table (Dolby: Russian tracks are mostly Dolby).
    $audio = array();
    $tier = 99;
    foreach (aio_audio_rules() as $id => $r)
    {
        if ($r[1] < $tier && ($id !== 'pcm' || AIO_BADGE_PCM) &&
            count(array_intersect_key(array_flip($r[2]), $a)) === count($r[2]))
        {
            $audio = array($id);
            $tier = $r[1];
        }
    }

    $tags = array();
    if (isset($v['IMAX']) || isset($set['editions']['IMAX']))
        $tags[] = 'IMAX';
    if (isset($v['3D']))
        $tags[] = '3D';
    foreach (array_keys($set['editions']) as $e)
    {
        if ($e !== 'IMAX' && count($tags) < 8)
            $tags[] = aio_cut(aio_utf8($e), 40);
    }
    foreach (array('repack', 'proper', 'regraded', 'uncensored', 'unrated') as $f)
    {
        if (isset($pf[$f]) && $pf[$f] === true)
            $tags[] = strtoupper($f);
    }

    return array('res' => $res, 'src' => $src, 'vis' => $vis,
        'ai' => isset($v['AI']) || isset($v['Upscaled']) || !empty($pf['upscaled']),
        'audio' => $audio, 'tags' => $tags);
}

// Badge id of aio_badges as text, for the card: "DV · HDR10", "TrueHD Atmos".
/**
 * @param string $id
 * @return string
 */
function aio_badge_text($id)
{
    static $t = array('dolby-vision-hdr10-plus' => 'DV · HDR10+', 'dolby-vision-hdr10' => 'DV · HDR10',
        'dolby-vision-hdr' => 'DV · HDR', 'dolby-vision' => 'DV', 'hdr10-plus' => 'HDR10+', 'hdr10' => 'HDR10',
        'hdr' => 'HDR', 'hlg' => 'HLG', '10bit' => '10bit',
        'dolby-atmos-truehd' => 'TrueHD Atmos', 'dolby-atmos-digital-plus' => 'DD+ Atmos',
        'dolby-atmos' => 'Atmos', 'dolby-truehd' => 'TrueHD', 'dolby-digital-plus' => 'DD+',
        'dolby-digital' => 'DD', 'dts-x-hd-ma' => 'DTS:X MA', 'dts-x-hd' => 'DTS:X HD', 'dts-x' => 'DTS:X',
        'dts-hd-ma' => 'DTS-HD MA', 'dts-hd' => 'DTS-HD', 'dts-es' => 'DTS-ES', 'dts' => 'DTS',
        'flac' => 'FLAC', 'pcm' => 'PCM', 'opus' => 'OPUS', 'aac' => 'AAC');
    return isset($t[$id]) ? $t[$id] : '';
}

// All audio of a release in words for the card: codecs from the best down
// in the words of the badges ("TrueHD Atmos · DTS:X MA · DD+"), tags we do not
// know as they are, then every channel layout from the most ("7.1 / 5.1 / 2.0").
/**
 * @param array<mixed> $pf
 * @return string
 */
function aio_audio_text($pf)
{
    $left = array();
    foreach (aio_arr($pf, 'audioTags') as $t)
    {
        if (is_string($t) && trim($t) !== '' && $t !== 'Unknown')
            $left[trim(aio_utf8($t))] = true;
    }
    $codecs = array();
    $i = 0;
    foreach (aio_audio_rules() as $id => $r)
    {
        $i++;
        if (count(array_intersect_key(array_flip($r[2]), $left)) === count($r[2]))
        {
            // Tier first, the order of the table on a tie (ksort, not an unstable sort).
            $codecs[sprintf('%d%03d', $r[1], $i)] = aio_badge_text($id);
            $left = array_diff_key($left, array_flip($r[2]));
        }
    }
    ksort($codecs);
    $codecs = array_merge(array_values($codecs), array_keys($left));
    $ch = array();
    foreach (aio_arr($pf, 'audioChannels') as $c)
    {
        if (is_string($c) && preg_match('/^[0-9]\.[0-9]$/', $c))
            $ch[$c] = $c;
    }
    rsort($ch);
    if ($ch)
        $codecs[] = implode(' / ', $ch);
    return aio_cut(implode(' · ', $codecs), AIO_TEXT_MAX);
}

// A track title without the language the code already shows: "Russian Dub
// iTunes" -> "Dub iTunes", "Английский" -> "". Spaces collapsed.
/**
 * @param string $title
 * @param string $lang
 * @return string
 */
function aio_track_title($title, $lang)
{
    static $ru = array('Russian' => 'русский|русская|рус',
        'English' => 'английский|английская|англ',
        'Ukrainian' => 'украинский|украинская|український|українська|укр');
    $title = trim(preg_replace('/\s+/u', ' ', $title), ' /');
    // Full names and 3 letters (Rus, Eng, Ukr); never the 2-letter code:
    // "It Dub", "No Dub" are words.
    $names = array();
    if (preg_match('/^[A-Za-z]{3,}/', $lang, $m))
        $names = array(preg_quote($lang, '/'), substr($m[0], 0, 3));
    if (isset($ru[$lang]))
        $names[] = $ru[$lang];
    if (!$names)
        return $title;
    // A whole word: not "Russians", "Rus2", "Russian/English".
    return trim(preg_replace('/^(?:' . implode('|', $names) . ')(?![\p{L}\p{N}\/])[\s.,:;\-\x{2013}\x{2014}]*/iu',
        '', $title), ' /');
}

// Audio tracks of the media info (parsedFile.audioTracks, only with a probe),
// in file order: array('lang' => 'RU', 'codec' => 'TrueHD', 'ch' => '7.1',
// 'title' => 'Dub Jaskier'). At most 99 tracks.
/**
 * @param array<mixed> $pf
 * @return list<AioTrack>
 */
function aio_tracks($pf)
{
    $out = array();
    foreach (aio_arr($pf, 'audioTracks') as $t)
    {
        if (!is_array($t))
            continue;
        $lang = aio_str($t, 'lang');
        $codec = aio_str($t, 'tag') !== '' ? aio_str($t, 'tag') : strtoupper(aio_str($t, 'codec'));
        $ch = aio_str($t, 'channels');
        // "Dub_Bravo": one space; "|" and "/" -> " / ", they split studios
        // ("MVO | LostFilm | Кубик в Кубе"; after the language: "Russian/English" stays).
        $title = aio_track_title(aio_clean(str_replace('_', ' ', aio_str($t, 'title'))), $lang);
        $title = trim(preg_replace('~\s*/\s*~u', ' / ', $title), ' /');
        $code = $lang !== '' ? aio_lang_code($lang) : '';
        $out[] = array(
            'lang' => $code,
            'codec' => aio_cut($codec, 20),
            'ch' => preg_match('/^[0-9]\.[0-9]$/', $ch) ? $ch : '',
            'title' => aio_cut($title, 120));
        if (count($out) === 99)
            break;
    }
    return $out;
}

// Languages of a row: the unique codes of its audio tracks in track order when
// any track has one, else $langs (parsedFile.languages: JacRed's Torznab marks
// any Cyrillic title ru-RU and AIOStreams takes that over the title).
/**
 * @param list<AioTrack> $tracks
 * @param list<string> $langs
 * @return list<string>
 */
function aio_track_langs($tracks, $langs)
{
    $out = array();
    foreach ($tracks as $t)
    {
        if ($t['lang'] !== '')
            $out[$t['lang']] = $t['lang'];
    }
    return $out ? array_values($out) : $langs;
}

// Name of a debrid service by streamData.service.id, as SERVICE_DETAILS of
// AIOStreams (packages/core/src/utils/constants.ts). An unknown id is shown
// as it is when short and plain; else '' (no name).
/**
 * @param string $id
 * @return string
 */
function aio_service_name($id)
{
    $names = array('realdebrid' => 'Real-Debrid', 'alldebrid' => 'AllDebrid', 'premiumize' => 'Premiumize',
        'debridlink' => 'Debrid-Link', 'torbox' => 'TorBox', 'stremio_nntp' => 'Stremio NNTP', 'nzbdav' => 'NzbDAV',
        'aiostreams' => 'AIOStreams', 'altmount' => 'AltMount', 'offcloud' => 'Offcloud', 'putio' => 'put.io',
        'easynews' => 'Easynews', 'easydebrid' => 'EasyDebrid', 'debrider' => 'Debrider', 'pikpak' => 'PikPak',
        'seedr' => 'Seedr', 'stremthru_newz' => 'StremThru Newz', 'torrin' => 'Torrin');
    if (isset($names[$id]))
        return $names[$id];
    return preg_match('/^[A-Za-z0-9._-]{1,20}\z/', $id) ? $id : '';
}

// One playable stream -> row; null for errors, statistics, no http(s) URL
// unless an infoHash (a P2P stream: played through TorrServer).
// The row, as Aio::$state['rows'] keeps it (main.php, playback.php, jacred.php,
// view_gcomps.php, view_info.php, voices_parse.php read it by these keys):
//   url          the stream URL; memory of php_server only, never logged nor saved;
//                '' for a P2P stream (then hash is set)
//   hash         infoHash, 40 lower-case hex digits (streamData.torrent, else
//                the stream's own), or ''
//   cached       service.cached: true, false, or null when unknown (no service)
//   service      service name for "Cached in ..."; '' when unknown (aio_service_name)
//   resolution   parsedFile.resolution, else from the name ("2160p"); may be ''
//   badges       aio_badges()
//   audio_full   audio of the card (aio_audio_text); JacRed fills it when ''
//   tracks       aio_tracks(); JacRed fills them when none (aio_fill_tracks)
//   subs         subtitle language codes; JacRed fills them when none
//   group        release, at most 60 characters: parsedFile.releaseGroup (the
//                releaser) and the first ranked expression of AIOStreams
//                (streamData.rankedStreamExpressionsMatched: names of the
//                matched ranked expressions),
//                "rg · ranked" when they differ, the ranked one when they are
//                the same but for case; one of them when the other is empty;
//                neither: "от X" / "by X" of the folder, then of the file
//                (aio_release_by), else streamData.streamExpressionMatched.name
//   expr         name of the AIOStreams expression the stream matched, at most
//                60 characters: the first ranked one, else streamExpressionMatched
//                .name; the list shows it in place of the trackers
//   network      parsedFile.network: the platform of a WEB-DL ("Netflix"), at most 60 characters
//   pack         a season pack: the folder size when it is bigger than the file, else 0.0
//   rate         bit rate, bit/s, 0.0 for none; rate_approx: the estimate of the
//                description (shown with "≈"); rate_src: its source, '' for none
//   quality      parsedFile.quality, capped: the screen wraps it (12 000 characters took seconds)
//   size         bytes, float: PHP on Dune is 32-bit, sizes over 2 GB do not fit an int
//   langs        language codes of the audio tracks, else of parsedFile.languages
//                (aio_track_langs); JacRed replaces them with its tracks
//   trackers     indexers
//   addon        streamData.addon: the name of the AIOStreams add-on ("DMM Cast"), at most 60
//                characters; shown where there are no trackers
//   addons       add-ons that found the same release (aio_addons, of streamData.sources):
//                list of array('addon' => name, 'cached' => bool); empty for fewer than 2
//   pf_seasons   seasons in the file, null when not parsed; pf_episodes: its
//                episodes (aio_has_ep)
//   names        array(folder, file); aio_row_voices replaces it by 'voices'
//                (aio_voices) and may shorten 'label'
//   label        name of the release: the file of a movie, the folder of a season pack
//   raw          the stream as it came, for the details screen (INFO); memory of
//                php_server only, as the URL: never logged nor saved
/**
 * @param array<mixed> $st
 * @param bool $series
 * @return AioRow|null
 */
function aio_row($st, $series)
{
    $url = aio_str($st, 'url');
    $sd = aio_arr($st, 'streamData');
    $tor = aio_arr($sd, 'torrent');
    $hash = strtolower(aio_str($tor, 'infoHash') !== '' ? aio_str($tor, 'infoHash') : aio_str($st, 'infoHash'));
    if (!preg_match('/^[0-9a-f]{40}$/D', $hash))
        $hash = '';
    if (!preg_match('~^https?://~', $url))
    {
        // P2P (AIOStreams: no url, infoHash and fileIdx): TorrServer plays it.
        if ($url !== '' || $hash === '')
            return null;
    }
    $type = aio_str($sd, 'type');
    if ($type === 'error' || $type === 'statistic')
        return null;
    $pf = aio_arr($sd, 'parsedFile');
    $bh = aio_arr($st, 'behaviorHints');
    $svc = aio_arr($sd, 'service');
    $name = aio_str($st, 'name');   // "[TB⚡] JacRed 2160p"

    // Only a bool service.cached counts, not the emoji of the name; no
    // service (P2P, HTTP add-ons): unknown.
    $cached = isset($svc['cached']) && is_bool($svc['cached']) ? $svc['cached'] : null;
    $res = aio_str($pf, 'resolution');
    if ($res === '' && preg_match('/\\b([0-9]{3,4}p|4k)\\b/i', $name, $m))
        $res = $m[1];
    $size = aio_str($sd, 'size') !== '' ? aio_str($sd, 'size') : aio_str($bh, 'videoSize');
    $tracks = aio_tracks($pf);
    $langs = array();
    foreach (aio_arr($pf, 'languages') as $l)
    {
        $c = is_string($l) ? aio_lang_code($l) : '';
        if ($c !== '')
            $langs[$c] = $c;
    }
    // Subtitle languages: parsedFile.subtitles, else the languages of the tracks.
    $subs = array();
    $names = aio_arr($pf, 'subtitles');
    if (!$names)
    {
        foreach (aio_arr($pf, 'subtitleTracks') as $t)
            $names[] = aio_str($t, 'lang');
    }
    foreach ($names as $l)
    {
        $c = is_string($l) ? aio_lang_code($l) : '';
        if ($c !== '' && count($subs) < 30)
            $subs[$c] = $c;
    }
    $pack = aio_num(aio_str($sd, 'folderSize'));
    // Bit rate, bit/s: the probe's; else size / duration (ms, at least a
    // second); else the estimate in the description ("67 Mbps", not "7,4"),
    // shown with "≈". A value over AIO_RATE_MAX falls to the next source.
    $rate = aio_num(aio_str($pf, 'bitrate'));
    $approx = false;
    $rate_src = 'parsedFile.bitrate';
    if ($rate > AIO_RATE_MAX)
        $rate = 0.0;
    $dur = aio_num(aio_str($sd, 'duration'));
    if ($rate == 0 && $dur >= 1000 && aio_num($size) > 0 && aio_num($size) * 8 / ($dur / 1000) <= AIO_RATE_MAX)
    {
        $rate = aio_num($size) * 8 / ($dur / 1000);
        $rate_src = 'size/duration';
    }
    if ($rate == 0 && preg_match('/(?<![0-9.,])([0-9]+(?:\.[0-9]+)?)\s*Mbps/', aio_str($st, 'description'), $m) &&
        aio_num($m[1]) * 1e6 <= AIO_RATE_MAX)
    {
        $rate = aio_num($m[1]) * 1e6;
        $approx = true;
        $rate_src = 'description';
    }
    $trackers = array();
    foreach (explode(',', aio_str($sd, 'indexer')) as $tr)
    {
        if (trim($tr) !== '')
            $trackers[] = trim($tr);
    }
    // A movie file name tells the release best; a season pack - its folder.
    $file = aio_str($sd, 'filename') !== '' ? aio_str($sd, 'filename') : aio_str($bh, 'filename');
    $folder = aio_str($sd, 'folderName');
    $label = $series ? ($folder !== '' ? $folder : $file) : ($file !== '' ? $file : $folder);
    $group = aio_str($pf, 'releaseGroup');
    $ranked = aio_arr($sd, 'rankedStreamExpressionsMatched');
    $ranked = isset($ranked[0]) && is_string($ranked[0]) ? trim(aio_utf8($ranked[0])) : '';
    if ($ranked !== '')
        $group = $group === '' || strcasecmp($group, $ranked) == 0 ? $ranked : $group . ' · ' . $ranked;
    if ($group === '')
        $group = aio_release_by($folder);
    if ($group === '')
        $group = aio_release_by(preg_replace('/\\.[A-Za-z0-9]{2,4}$/', '', $file));
    $expr = aio_arr($sd, 'streamExpressionMatched');
    $expr = isset($expr['name']) && is_string($expr['name']) ? trim(aio_utf8($expr['name'])) : '';
    if ($group === '')
        $group = $expr;
    return array(
        'url' => $url,
        'hash' => $hash,
        'cached' => $cached,
        'service' => aio_service_name(aio_str($svc, 'id')),
        'resolution' => $res,
        'badges' => aio_badges($pf, $res),
        'audio_full' => aio_audio_text($pf),
        'tracks' => $tracks,
        'subs' => array_values($subs),
        'group' => aio_cut($group, 60),
        'expr' => aio_cut($ranked !== '' ? $ranked : $expr, 60),
        'network' => aio_cut(aio_str($pf, 'network'), 60),
        'pack' => $series && $pack > aio_num($size) * 1.05 ? $pack : 0.0,
        'rate' => $rate,
        'rate_approx' => $approx,
        'rate_src' => $rate > 0 ? $rate_src : '',
        'quality' => aio_cut(aio_str($pf, 'quality'), AIO_TEXT_MAX),
        'size' => aio_num($size),
        'langs' => aio_track_langs($tracks, array_values($langs)),
        'trackers' => $trackers,
        'addon' => aio_cut(aio_str($sd, 'addon'), 60),
        'addons' => aio_addons(aio_arr($sd, 'sources'), aio_str($svc, 'id'), aio_str($sd, 'addon')),
        'pf_seasons' => isset($pf['seasons']) && is_array($pf['seasons']) && $pf['seasons'] ?
            aio_ints($pf['seasons']) : null,
        'pf_episodes' => aio_ints(aio_arr($pf, 'episodes')),
        'names' => array($folder, $file),
        'label' => $label !== '' ? $label : aio_plain_name($name),
        'raw' => $st);
}

// streamData.sources of a patched AIOStreams build (upstream has no such
// field; copies of one infoHash merged by its dedup): [{"addon":
// "MediaFusion", "cached": ["realdebrid"]}, ...]. $own: streamData.addon, the
// row's add-on, always first (added unmarked when the list lacks it). ->
// array(array('addon' => name, 'cached' => whether this add-on itself said
// the release is cached on service $svc, the service of the row)), in the
// order given, at most AIO_ADDONS_MAX; an add-on once (names compared whole,
// ignoring case); items that are not an object with a visible string name are
// left out. Fewer than 2 add-ons: array() - the screens show 'addon' as before.
/**
 * @param array<mixed> $src
 * @param string $svc
 * @param string $own
 * @return list<AioAddon>
 */
function aio_addons($src, $svc, $own)
{
    $out = array();
    $seen = array();
    $own_key = null;
    $own = aio_addon_name($own);
    if ($own !== '')
    {
        $out[] = array('addon' => aio_cut($own, 60), 'cached' => false);
        $own_key = mb_strtolower($own, 'UTF-8');
    }
    $n = 0;
    foreach ($src as $it)
    {
        if (++$n > AIO_ADDONS_SCAN || count($out) >= AIO_ADDONS_MAX)
            break;
        if (!is_array($it) || !isset($it['addon']) || !is_string($it['addon']))
            continue;
        $name = aio_addon_name($it['addon']);
        $key = mb_strtolower($name, 'UTF-8');
        if ($name === '' || isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $cached = false;
        if ($svc !== '' && isset($it['cached']) && is_array($it['cached']))
        {
            foreach (array_slice($it['cached'], 0, AIO_ADDONS_SCAN) as $id)
                $cached = $cached || (is_string($id) && $id === $svc);
        }
        if ($key === $own_key)
            $out[0]['cached'] = $cached;
        else
            $out[] = array('addon' => aio_cut($name, 60), 'cached' => $cached);
    }
    // The write to $out[0] above loses the shape for PHPStan.
    /** @var list<AioAddon> $out */
    return count($out) >= 2 ? $out : array();
}

// An add-on name without white space and invisible characters (NBSP, ZWSP,
// ZWNJ, ZWJ, word joiner, BOM) at its ends: '' for one with nothing visible.
/**
 * @param mixed $s
 * @return string
 */
function aio_addon_name($s)
{
    return strval(preg_replace('/^[\\s\\x{00A0}\\x{200B}-\\x{200D}\\x{2060}\\x{FEFF}]+|' .
        '[\\s\\x{00A0}\\x{200B}-\\x{200D}\\x{2060}\\x{FEFF}]+$/u', '', aio_utf8($s)));
}

// The releaser credited as "от X" / "by X" (the last credit: it follows the
// title), else ''. One token; dots inside stay, trailing ".-_" go. "by" needs
// a space or "_" after it: "Stand.by.Me" is a title, not a credit.
/**
 * @param string $s
 * @return string
 */
function aio_release_by($s)
{
    if (!preg_match_all('/(?:^|[\\s._\\-(\\[])(?:от|by)[\\s_]+([^\\s|\\[\\](),\\/]+)/iu', $s, $m))
        return '';
    return rtrim(end($m[1]), '.-_');
}

// Whole numbers of a JSON list.
/**
 * @param array<mixed> $a
 * @return list<int>
 */
function aio_ints($a)
{
    $res = array();
    foreach ($a as $v)
    {
        if (is_int($v) || (is_string($v) && preg_match('/^[0-9]{1,5}$/', $v)))
            $res[] = intval($v);
    }
    return $res;
}

// "[TB⚡] JacRed 2160p" -> "JacRed 2160p": no bracket tags, no emoji (the
// Dune font may lack them).
/**
 * @param string $name
 * @return string
 */
function aio_plain_name($name)
{
    $name = preg_replace('/\\[[^\\]]*\\]/', ' ', $name);
    $name = preg_replace('/[\\x{2190}-\\x{2BFF}\\x{1F000}-\\x{1FFFF}\\x{FE0F}\\x{200D}]/u', '', $name);
    return trim(preg_replace('/\\s+/u', ' ', strval($name)));
}

// PHP on Dune is 32-bit and json_decode of 5.3 clamps big integers to
// 2147483647 (no JSON_BIGINT_AS_STRING): sizes are quoted before decoding.
// The body is changed in place: one copy of a 10 MB reply less in memory.
/**
 * @param string $body
 * @param-out string|null $body
 * @return mixed
 */
function aio_decode_streams(&$body)
{
    // One bad byte fails json_decode of the whole list: invalid UTF-8 goes
    // first. mbstring of 5.3 lets lead bytes past U+10FFFF through, they go
    // to "?" here (json_decode rejects them).
    if (!preg_match('//u', $body))
        $body = aio_utf8(preg_replace('/[\xF5-\xFF]|\xF4[\x90-\xBF]/', '?', $body));
    // The whole number: "12345678901.5" quoted only up to the dot breaks the JSON.
    $body = preg_replace('/("(?:size|videoSize|folderSize|bitrate|duration)"\\s*:\\s*)' .
        '([0-9]{10,}(?:\\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/',
        '$1"$2"', $body);
    return json_decode($body, true);
}

// --- Magnet of a release ("Download in app", 0.29.0).

// Trackers of a magnet at most (a long intent URI, a long form field).
define('AIO_MAGNET_TR', 20);

// Trackers of a magnet when the stream names none: common public trackers.
/**
 * @return list<string>
 */
function aio_magnet_trackers()
{
    return array('udp://tracker.opentrackr.org:1337/announce', 'udp://open.stealth.si:80/announce',
        'udp://tracker.torrent.eu.org:451/announce', 'udp://exodus.desync.com:6969/announce');
}

// Row with infoHash -> array(magnet, number of trackers, 'sources' | 'public').
// dn: the folder of the torrent, else its single file; tr: the first
// AIO_MAGNET_TR "tracker:<url>" of sources (of the stream, of
// streamData.torrent), else the public ones. Every
// value is percent-encoded: no quote, space, "#" or ";" for the intent URI.
/**
 * @param AioRow $r
 * @return array{string, int, 'sources'|'public'}
 */
function aio_magnet($r)
{
    $raw = $r['raw'];
    $sd = aio_arr($raw, 'streamData');
    $file = aio_str($sd, 'filename') !== '' ? aio_str($sd, 'filename') :
        aio_str(aio_arr($raw, 'behaviorHints'), 'filename');
    $dn = aio_str($sd, 'folderName') !== '' ? aio_str($sd, 'folderName') : $file;
    $tr = array();
    foreach (array_merge(aio_arr($raw, 'sources'), aio_arr(aio_arr($sd, 'torrent'), 'sources')) as $s)
    {
        if (is_string($s) && preg_match('~^tracker:((?:udp|https?|wss?)://[^\\s]+)$~D', trim($s), $m))
            $tr[$m[1]] = true;
    }
    $src = $tr ? 'sources' : 'public';
    $tr = $tr ? array_slice(array_keys($tr), 0, AIO_MAGNET_TR) : aio_magnet_trackers();
    $mg = 'magnet:?xt=urn:btih:' . $r['hash'] . ($dn !== '' ? '&dn=' . rawurlencode($dn) : '');
    foreach ($tr as $t)
        $mg .= '&tr=' . rawurlencode($t);
    return array($mg, count($tr), $src);
}
