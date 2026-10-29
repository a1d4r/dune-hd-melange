<?php
// Stream list as a view_gcomps screen, 1920x1080, on the grid of the Dune
// movie card (poster 288x432 at 75,60, margins 75): darkened
// fanart; on the left the poster, beside it the size and the cache status,
// under it a card of the selected release (name, video, audio, voices,
// subtitles, languages, trackers); on the right the list: cache icon (the
// seeders of a P2P row), badges of kingsizew/badges (resolution, source,
// picture, the best audio), size, language flags, trackers if room is left;
// between the title and the list the colour keys of the sort as tabs (a
// remote with them), the current one bright and underlined, its direction a
// triangle; under the list one hint row: P+/P-, <- ->, POP UP "Actions", INFO
// "Details" and "1 / 60" at the right. The plugin draws and moves the
// selection itself with change_gcomps; the position travels in sel_state
// {"sel":N,"top":T}.
// Shapes as proven on the device (04.10.2026).
// Reads Aio (state, settings), writes the cursor keys of Aio::$state;
// aio_gc_sort replaces it by the list sorted anew.
// PHP 5.3.6, firmware API only.
// Types in the PHPDoc (AioGcComp and others): phpstan-types.neon of the repository.

require_once dirname(__FILE__) . '/gcomps.php';

define('AIO_GC_CX', 75);       // left column: poster and card, as the movie card
define('AIO_GC_CW', 585);      // to x 660: the card keeps its width
define('AIO_GC_CY', 60);
define('AIO_GC_PW', 288);      // poster, as the movie card
define('AIO_GC_PH', 432);
define('AIO_GC_LX', 680);      // list: 20 px after the card, as 445 -> 465 of the movie card
define('AIO_GC_LW', 1165);     // right edge at 1845 = 1920 - 75
define('AIO_GC_LY', 194);      // under the sort tabs (12 px), no column heads
define('AIO_GC_ROW_H', 64);
define('AIO_GC_VIS', 12);      // visible rows: the list ends at y 962
// The hint row of the list: the keys at the vendor size, unscaled (scaled
// down they blur on the device): P+/P- 114x50, POP UP and INFO 52x50. The
// episode arrows (the vendor's bare chevron, nearly 50 high) at half size:
// the chevron as high as the glyph on the POP UP plate. Labels 30 px in a box
// of 40 centred on the keys, as on the device in 0.46.1; the gap 10 to a
// label: chosen (vendor text 36, gap 12).
define('AIO_GC_HINT_Y', 990);  // the vendor hint row 990..1040
define('AIO_GC_KEY_H', 50);
define('AIO_GC_KEY_W', 52);    // a vendor key of one button: the colour keys
define('AIO_GC_LABEL_H', 40);
define('AIO_GC_ARROW_W', 26);  // right_arrow.png 52x50 / 2
define('AIO_GC_ARROW_H', 25);
define('AIO_GC_HINT_PX', 30);
define('AIO_GC_KEY_GAP', 10);   // key -> its label
define('AIO_GC_HINT_GAP', 40);  // between hints (vendor HINTS_SMALL_HGAP)
// The sort tabs (aio_gc_sort_tabs): the colour keys and labels as the hints,
// in the row y 125..175 between the title (40..106) and the list; the line
// under the current tab 4 px, 3 px under the keys. The direction: a
// triangle of img/ (sort_down.png, sort_up.png: plugins/melange_img.sh),
// its room kept at every tab, so that the tabs never move.
define('AIO_GC_TABS_Y', 125);
define('AIO_GC_TAB_LINE', 4);
define('AIO_GC_SORT_ARROW_W', 18);
define('AIO_GC_SORT_ARROW_H', 12);
define('AIO_GC_SORT_ARROW_GAP', 10);    // label -> its triangle
define('AIO_GC_PAD', 16);      // row padding left and right
define('AIO_GC_GAP', 16);      // between columns
// "rutracker +3" (142 px by the estimate) with a margin; less room: no tracker
// column (the card has them).
define('AIO_GC_TRACKER_MIN', 145);
define('AIO_GC_BADGE_H', 46);  // badge pictures with their plate
// Revision in the badge file names (BADGE_REV of their generator):
// the shell caches pictures by URL even across a reinstall.
define('AIO_GC_BADGE_REV', 2);
define('AIO_GC_BADGE_GAP', 10);    // between badges of one column
define('AIO_GC_VX', 156);      // card values after the labels ("Субтитры" 26 px)
define('AIO_GC_NAME_MAX', 400);    // characters of the name in the card, at most
define('AIO_GC_NAME_LINES', 2);    // the name block in the card: 2 lines of 26 px
define('AIO_GC_NAME_PX', 26);
define('AIO_GC_NAME_LH', 32);
define('AIO_GC_VOICES_LINES', 5);  // lines of the voices in the card, at most,
define('AIO_GC_VOICES_PX', 26);    // 26 px text in 32 px lines (as the name): more studios a line
define('AIO_GC_VOICES_LH', 32);
define('AIO_GC_CARD_BOTTOM', 1040);   // the card ends above this y on any data
define('AIO_GC_LANGS_MAX', 3);     // language flags of a row, the rest as "+N"
define('AIO_GC_STEPS', 5);     // change_gcomps animation steps (vendor default)

define('AIO_GC_TEXT', '#FFFFFFE0');
define('AIO_GC_TEXT2', '#FFAFAFA0');
define('AIO_GC_DIM', '#FF808080');
define('AIO_GC_SEL', '#D02850C0');
define('AIO_GC_GREEN', '#FF34C759');
define('AIO_GC_GREY', '#FF8E8E93');    // the "not cached" icon
// Seeders of a P2P row in place of its "?" icon (aio_gc_seed_color). Bins as
// the ranked expressions of the P2P template (cgi/aio_template_p2p.json):
// 0-2, 3-9, 10-29, 30+, a bin starts at LOW, MID, HIGH; the template also
// ranks a high bit rate with few seeders lower, the colour does not.
define('AIO_GC_SEED_LOW', 3);
define('AIO_GC_SEED_MID', 10);
define('AIO_GC_SEED_HIGH', 30);
define('AIO_GC_SEED_MAX', 999);    // more: "999+"
define('AIO_GC_SEED_PX', 28);
define('AIO_GC_ICON', 38);    // the cache icon, square; the least width of its column
// Progress bar of a release watched before, as the vendor's.
define('AIO_GC_BAR_H', 4);
define('AIO_GC_BAR_MIN', 4);    // the white part at least, even for a second watched
define('AIO_GC_BAR_DONE', '#FFFFFFFF');
define('AIO_GC_BAR_REST', '#FF404040');

class AioGc
{
    // Badge id of aio_badges -> width of img/nb<REV>_<id>.png, 46 px high with the
    // plate: the output of the badge generator (the tests compare it with
    // the PNGs).
    /** @var array<string, int> */
    public static $badge_w = array(
        '4k' => 58, '1440p' => 58, '1080p' => 79, '720p' => 62, '576p' => 90, '480p' => 92,
        '360p' => 89, '240p' => 91, '144p' => 86,
        'remux' => 151, 'bluray' => 151, 'web-dl' => 154, 'webrip' => 169, 'hdrip' => 133,
        'hc-hdrip' => 171, 'dvdrip' => 160, 'hdtv' => 140, 'cam' => 126, 'ts' => 97, 'tc' => 102,
        'scr' => 115,
        'dolby-vision-hdr10-plus' => 133, 'dolby-vision-hdr10' => 133, 'dolby-vision-hdr' => 133,
        'dolby-vision' => 88, 'hdr10-plus' => 157, 'hdr10' => 146, 'hdr' => 110, 'hlg' => 106,
        '10bit' => 123,
        'dolby-atmos-truehd' => 133, 'dolby-atmos-digital-plus' => 133, 'dolby-atmos' => 88,
        'dolby-truehd' => 88, 'dolby-digital-plus' => 88, 'dolby-digital' => 88,
        'dts-x-hd-ma' => 119, 'dts-x-hd' => 119, 'dts-x' => 134, 'dts-hd-ma' => 166, 'dts-hd' => 142,
        'dts-es' => 141, 'dts' => 106, 'flac' => 118, 'pcm' => 88, 'opus' => 123, 'aac' => 104);
}

// Words of the list screen in language $lang of aio_gc_lang, by key.
/**
 * @param string $lang
 * @return AioGcText
 */
function aio_gc_text($lang)
{
    return array('lang' => $lang, 'page' => aio_tr($lang, 'gc_page'), 'pack' => aio_tr($lang, 'gc_pack'),
        'group' => aio_tr($lang, 'gc_group'), 'source' => aio_tr($lang, 'gc_source'),
        'cached' => aio_tr($lang, 'gc_cached'),
        'cached_in' => aio_tr($lang, 'gc_cached_in'),
        'download' => aio_tr($lang, 'gc_download'), 'unknown' => aio_tr($lang, 'gc_unknown'),
        'p2p' => aio_tr($lang, 'gc_peers'),
        'video' => aio_tr($lang, 'gc_video'), 'audio' => aio_tr($lang, 'gc_audio'),
        'voices' => aio_tr($lang, 'gc_voices'), 'subs' => aio_tr($lang, 'gc_subs'),
        'langs' => aio_tr($lang, 'gc_langs'), 'trackers' => aio_tr($lang, 'gc_trackers'),
        'addon' => aio_tr($lang, 'gc_addon'),
        'flip' => aio_tr($lang, 'gc_flip'),
        'actions' => aio_tr($lang, 'gc_actions'), 'details' => aio_tr($lang, 'gc_details'),
        'sort_aio' => aio_tr($lang, 'gc_sort_aio'), 'sort_seeds' => aio_tr($lang, 'gc_sort_seeds'),
        'sort_cached' => aio_tr($lang, 'gc_sort_cached'), 'sort_size' => aio_tr($lang, 'gc_sort_size'),
        'sort_date' => aio_tr($lang, 'gc_sort_date'));
}

// The cache status of row $r: "В кэше <service>", "Нужно скачать", "P2P" (no
// URL: TorrServer plays it), "неизвестно".
/**
 * @param AioRow $r
 * @param AioGcText $tt
 * @return string
 */
function aio_gc_status($r, $tt)
{
    if ($r['cached'] === true)
        return $r['service'] !== '' ? str_replace('{svc}', $r['service'], $tt['cached_in']) : $tt['cached'];
    if ($r['cached'] === false)
        return $tt['download'];
    return aio_is_p2p($r) ? $tt['p2p'] : $tt['unknown'];
}

// --- Position.

/**
 * @param int $sel
 * @param int $top
 * @return string
 */
function aio_gc_state($sel, $top)
{
    return json_encode(array('sel' => $sel, 'top' => $top));
}

// sel_state -> array(sel, top), or null when it does not fit $n rows.
/**
 * @param mixed $s
 * @param int $n
 * @return array{int, int}|null
 */
function aio_gc_parse($s, $n)
{
    $j = is_string($s) && $s !== '' ? json_decode($s) : null;
    if (!is_object($j) || !isset($j->sel, $j->top) || !is_int($j->sel) || !is_int($j->top))
        return null;
    if ($j->sel < 0 || $j->sel >= $n || $j->top < 0 || $j->top > max(0, $n - AIO_GC_VIS) ||
        $j->sel < $j->top || $j->sel >= $j->top + AIO_GC_VIS)
        return null;
    return array($j->sel, $j->top);
}

// From the shell's sel_state; else the last known position of this list.
/**
 * @param AioState $st
 * @param mixed $raw
 * @return array{int, int}
 */
function aio_gc_pos($st, $raw)
{
    $n = count($st['rows']);
    $p = aio_gc_parse($raw, $n);
    if (!$p && isset($st['gc']))
        $p = aio_gc_parse(aio_gc_state($st['gc'][0], $st['gc'][1]), $n);
    return $p ? $p : array(0, 0);
}

// Position of a list put in with row $sel of $n selected: the row in the
// middle of the screen, as far as the list allows. -> array(sel, top).
/**
 * @param int $sel
 * @param int $n
 * @return array{int, int}
 */
function aio_gc_cursor($sel, $n)
{
    return array($sel, max(0, min($sel - intval(AIO_GC_VIS / 2), $n - AIO_GC_VIS)));
}

// List state $st put in with row $sel selected (aio_finish, aio_relist): the
// cursor as aio_gc_cursor, and its first view ignores the shell's sel_state.
/**
 * @param AioState $st
 * @param int $sel
 * @return AioState
 */
function aio_gc_put_cursor($st, $sel)
{
    $st['gc'] = aio_gc_cursor($sel, count($st['rows']));
    $st['gc_new'] = true;
    return $st;
}

// One key: up, down, pgup (P+), pgdn (P-). A page keeps the row on screen.
/**
 * @param int $sel
 * @param int $top
 * @param int $n
 * @param 'up'|'down'|'pgup'|'pgdn' $key
 * @return array{int, int}
 */
function aio_gc_step($sel, $top, $n, $key)
{
    $d = array('up' => -1, 'down' => 1, 'pgup' => -AIO_GC_VIS, 'pgdn' => AIO_GC_VIS);
    $max_top = max(0, $n - AIO_GC_VIS);
    $nsel = max(0, min($n - 1, $sel + $d[$key]));
    $ntop = abs($d[$key]) > 1 ? max(0, min($max_top, $top + $d[$key])) : $top;
    if ($nsel < $ntop)
        $ntop = $nsel;
    else if ($nsel >= $ntop + AIO_GC_VIS)
        $ntop = $nsel - AIO_GC_VIS + 1;
    return array($nsel, $ntop);
}

// --- Row data.

// Language code of aio_lang_code -> flag of the base skin (gui_skin://flags/XX.png,
// checked against the r24 skin). Languages of several countries take the main
// one; codes without a flag (MULTI, LAT, Indian languages but Hindi) stay text.
/**
 * @return array<string, string>
 */
function aio_gc_flags()
{
    return array('RU' => 'RU', 'EN' => 'GB', 'UA' => 'UA', 'JA' => 'JP', 'ZH' => 'CN',
        'AR' => 'SA', 'PT' => 'PT', 'PT-BR' => 'BR', 'ES' => 'ES', 'FR' => 'FR', 'DE' => 'DE',
        'IT' => 'IT', 'KO' => 'KR', 'HI' => 'IN', 'BN' => 'BD', 'TH' => 'TH', 'VI' => 'VN',
        'ID' => 'ID', 'TR' => 'TR', 'HE' => 'IL', 'FA' => 'IR', 'EL' => 'GR', 'LT' => 'LT',
        'LV' => 'LV', 'ET' => 'EE', 'PL' => 'PL', 'CS' => 'CZ', 'SK' => 'SK', 'HU' => 'HU',
        'RO' => 'RO', 'BG' => 'BG', 'SR' => 'RS', 'HR' => 'HR', 'SL' => 'SI', 'NL' => 'NL',
        'DA' => 'DK', 'FI' => 'FI', 'SV' => 'SE', 'NO' => 'NO', 'MS' => 'MY');
}

// Cache icon of a row: lightning, arrow down, "?".
/**
 * @param bool|null $cached
 * @return string
 */
function aio_gc_icon($cached)
{
    return 'plugin_file://img/' . (is_null($cached) ? 'unknown.png' :
        ($cached ? 'cached.png' : 'download.png'));
}

// Seeders the list shows in place of the icon: those of a P2P row (no URL;
// its cache status means nothing), else null.
/**
 * @param AioRow $r
 * @return int|null
 */
function aio_gc_seeds($r)
{
    return aio_is_p2p($r) ? $r['seeders'] : null;
}

/**
 * @param int $n
 * @return string
 */
function aio_gc_seeds_str($n)
{
    return $n > AIO_GC_SEED_MAX ? AIO_GC_SEED_MAX . '+' : strval($n);
}

// Colour of the seeders by bins; the row in focus brighter, as its texts.
/**
 * @param int $n
 * @param bool $on
 * @return string
 */
function aio_gc_seed_color($n, $on)
{
    if ($n < AIO_GC_SEED_LOW)
        return $on ? AIO_GC_TEXT2 : AIO_GC_DIM;
    if ($n < AIO_GC_SEED_MID)
        return $on ? AIO_GC_TEXT : AIO_GC_TEXT2;
    return $n < AIO_GC_SEED_HIGH ? AIO_GC_TEXT : AIO_GC_GREEN;
}

// Languages of a row: those with a flag first, then the rest; the service
// codes only if there is no real language.
/**
 * @param list<string> $langs
 * @return list<string>
 */
function aio_gc_tail_langs($langs)
{
    $flags = aio_gc_flags();
    $service = array('MULTI', 'DUAL', 'DUB', 'ORIG');
    $out = array('flag' => array(), 'text' => array(), 'service' => array());
    foreach ($langs as $c)
        $out[isset($flags[$c]) ? 'flag' : (in_array($c, $service, true) ? 'service' : 'text')][] = $c;
    return $out['flag'] || $out['text'] ? array_merge($out['flag'], $out['text']) : $out['service'];
}

/**
 * @param AioState $st
 * @return 'ru'|'en'
 */
function aio_gc_lang($st)
{
    return $st['lang'] === 'ru' ? 'ru' : 'en';
}

// array(title with year, "S05E03" of an episode, else ''). The number of
// releases is in the counter under the list.
/**
 * @param AioState $st
 * @return array{string, string}
 */
function aio_gc_title($st)
{
    $mv = $st['movie'];
    $title = $mv['title'] . (preg_match('/^[0-9]{4}$/', $mv['year']) ? " ({$mv['year']})" : '');
    return array($title, $st['e'] > 0 ? sprintf('S%02dE%02d', $st['s'], $st['e']) : '');
}

// The word <$key>_one/_few/_many for $n: English 1 / else, Russian by the
// last digit (1, 2-4, else; 11-14 as 5).
/**
 * @param string $lang
 * @param int $n
 * @param string $key
 * @return string
 */
function aio_gc_plural($lang, $n, $key)
{
    if ($lang === 'en')
        $form = $n === 1 ? 'one' : 'many';
    else
    {
        $m = $n % 100 >= 11 && $n % 100 <= 14 ? 5 : $n % 10;
        $form = $m === 1 ? 'one' : ($m >= 2 && $m <= 4 ? 'few' : 'many');
    }
    return aio_tr($lang, "{$key}_$form");
}

// Languages of a row: up to AIO_GC_LANGS_MAX flags of the skin, a code
// without a flag as text, the rest as "+N"; service codes (MULTI, DUAL, ...)
// only without a real language. Only what fits $width with the "+N" after
// it. -> array(flag|text, value, width).
/**
 * @param list<string> $langs
 * @param int $width
 * @return list<array{'flag'|'text', string, int}>
 */
function aio_gc_lang_items($langs, $width)
{
    $flags = aio_gc_flags();
    $tail = aio_gc_tail_langs($langs);
    $items = array();
    $x = 0;
    foreach (array_slice($tail, 0, AIO_GC_LANGS_MAX) as $i => $c)
    {
        $it = isset($flags[$c]) ? array('flag', $flags[$c], 36) : array('text', $c, aio_gc_w($c, 24));
        $rest = count($tail) - $i - 1;
        if ($x + $it[2] + ($rest > 0 ? 6 + aio_gc_w("+$rest", 24) : 0) > $width)
            break;
        $items[] = $it;
        $x += $it[2] + 6;
    }
    $rest = count($tail) - count($items);
    if ($rest > 0)
        $items[] = array('text', "+$rest", aio_gc_w("+$rest", 24));
    return $items;
}

/**
 * @param list<array{'flag'|'text', string, int}> $items
 * @return int
 */
function aio_gc_items_w($items)
{
    $w = 0;
    foreach ($items as $it)
        $w += $it[2];
    return $items ? $w + 6 * (count($items) - 1) : 0;
}

// "rutracker, bitru +2" if that fits $width, else "rutracker +3"
// with the name cut; the "+N" stays.
/**
 * @param AioRow $r
 * @param int $width
 * @return string
 */
function aio_gc_tracker($r, $width)
{
    $n = count($r['trackers']);
    if (!$n)
        return '';
    if ($n > 1)
    {
        $two = aio_clean($r['trackers'][0]) . ', ' . aio_clean($r['trackers'][1]) . ($n > 2 ? ' +' . ($n - 2) : '');
        if (aio_gc_w($two, 24) <= $width)
            return $two;
    }
    $more = $n > 1 ? ' +' . ($n - 1) : '';
    return aio_gc_cut_w(aio_clean($r['trackers'][0]), 24, $width - aio_gc_w($more, 24)) . $more;
}

// Badges of a row by column: resolution, source, picture, audio; AI only
// in the card. Only ids with a picture (aio_badges knows no others).
/**
 * @param AioBadges $b
 * @return array{res: list<string>, src: list<string>, vis: list<string>, audio: list<string>}
 */
function aio_gc_row_badges($b)
{
    $cols = array('res' => array($b['res']), 'src' => array($b['src']),
        'vis' => array($b['vis']), 'audio' => $b['audio']);
    foreach ($cols as $k => $ids)
        $cols[$k] = array_values(array_intersect($ids, array_keys(AioGc::$badge_w)));
    return $cols;
}

// Width of badges side by side, gaps between.
/**
 * @param list<string> $ids
 * @return int
 */
function aio_gc_plates_w($ids)
{
    $w = 0;
    foreach ($ids as $id)
        $w += AioGc::$badge_w[$id];
    return $ids ? $w + AIO_GC_BADGE_GAP * (count($ids) - 1) : 0;
}

// Column x and width for this list, each by its widest value; empty columns
// take no room; the icon column AIO_GC_ICON px, wider only for wider
// seeders. The badges and the size keep their width; the languages get what
// is left, the trackers (or the expression name, or the add-on) the rest if it
// is at least AIO_GC_TRACKER_MIN.
/**
 * @param list<AioRow> $rows
 * @param AioGcText $tt
 * @return AioGcCols
 */
function aio_gc_cols($rows, $tt)
{
    $max = array('res' => 0, 'src' => 0, 'vis' => 0, 'audio' => 0, 'size' => 0);
    $langs = 0;
    $any_tracker = false;
    $icon = AIO_GC_ICON;
    foreach ($rows as $r)
    {
        $n = aio_gc_seeds($r);
        if (!is_null($n))
            $icon = max($icon, aio_gc_w(aio_gc_seeds_str($n), AIO_GC_SEED_PX));
        foreach (aio_gc_row_badges($r['badges']) as $k => $ids)
            $max[$k] = max($max[$k], aio_gc_plates_w($ids));
        $max['size'] = max($max['size'], $r['size'] > 0 ?
            min(180, aio_gc_w(aio_size_str($r['size'], $tt['lang']), 28)) : 0);
        $langs = max($langs, min(260, aio_gc_items_w(aio_gc_lang_items($r['langs'], PHP_INT_MAX))));
        $any_tracker = $any_tracker || $r['expr'] !== '' || $r['trackers'] || $r['addon'] !== '';
    }
    $cols = array('icon' => array(12, $icon));
    $x = 12 + $icon + 14;
    foreach ($max as $k => $w)
    {
        if ($w > 0)
        {
            $cols[$k] = array($x, $w);
            $x += $w + AIO_GC_GAP;
        }
    }
    $end = AIO_GC_LW - AIO_GC_PAD;
    // One flag at least, else no languages.
    if ($langs > 0 && $end - $x >= 36)
    {
        $cols['langs'] = array($x, min($langs, $end - $x));
        $x += $cols['langs'][1] + AIO_GC_GAP;
    }
    if ($any_tracker && $end - $x >= AIO_GC_TRACKER_MIN)
        $cols['tracker'] = array($x, $end - $x);
    return $cols;
}

// --- Screen parts.

/**
 * @param int $i
 * @return AioGcGeom
 */
function aio_gc_row_geom($i)
{
    return aio_gc_at(AIO_GC_LW, AIO_GC_ROW_H, 0, $i * AIO_GC_ROW_H);
}

/**
 * @param int $top
 * @param int $n
 * @return AioGcGeom
 */
function aio_gc_rows_geom($top, $n)
{
    return aio_gc_at(AIO_GC_LW, $n * AIO_GC_ROW_H, 0, -$top * AIO_GC_ROW_H);
}

// Scrollbar thumb: height by the visible share, y by the top row.
/**
 * @param int $top
 * @param int $n
 * @return AioGcGeom
 */
function aio_gc_thumb_geom($top, $n)
{
    $track = AIO_GC_VIS * AIO_GC_ROW_H;
    $h = max(40, intval($track * AIO_GC_VIS / $n));
    $y = intval(round(($track - $h) * $top / ($n - AIO_GC_VIS)));
    return aio_gc_at(6, $h, AIO_GC_LX + AIO_GC_LW + 10, AIO_GC_LY + $y);
}

// Badge pictures (the plate is in the PNG) from x, centred in the row.
/**
 * @param list<string> $ids
 * @param int $x
 * @return list<AioGcComp>
 */
function aio_gc_plates($ids, $x)
{
    $d = array();
    foreach ($ids as $id)
    {
        $d[] = aio_gc_image(aio_gc_left_center(AioGc::$badge_w[$id], AIO_GC_BADGE_H, $x),
            'plugin_file://img/nb' . AIO_GC_BADGE_REV . "_$id.png");
        $x += AioGc::$badge_w[$id] + AIO_GC_BADGE_GAP;
    }
    return $d;
}

/**
 * @param bool|null $cached
 * @return string
 */
function aio_gc_status_color($cached)
{
    return is_null($cached) ? AIO_GC_DIM : ($cached ? AIO_GC_GREEN : AIO_GC_GREY);
}

// The status of the card: that of aio_gc_status; a P2P row with seeders adds
// them ("P2P · 79 сидов") in the colour of the number in the list row in focus.
// -> array(text, colour).
/**
 * @param AioRow $r
 * @param AioGcText $tt
 * @return array{string, string}
 */
function aio_gc_card_status($r, $tt)
{
    $status = aio_gc_status($r, $tt);
    $n = is_null($r['cached']) ? aio_gc_seeds($r) : null;
    if (is_null($n))
        return array($status, aio_gc_status_color($r['cached']));
    return array("$status · $n " . aio_gc_plural($tt['lang'], $n, 'gc_seeds'), aio_gc_seed_color($n, true));
}

// Children of row panel "r$i". The row in focus is brighter, as in the
// shell's own lists: its texts TEXT, the add-on TEXT2; the others TEXT2, DIM.
// A release watched before ($share > 0): a progress bar at the row bottom.
/**
 * @param AioRow $r
 * @param AioGcText $tt
 * @param AioGcCols $c
 * @param bool $on
 * @param float|int $share
 * @return list<AioGcComp>
 */
function aio_gc_row_items($r, $tt, $c, $on, $share = 0)
{
    $hi = $on ? AIO_GC_TEXT : AIO_GC_TEXT2;
    $d = array();
    // The icon, or the seeders of a P2P row, centred in the icon column.
    $n = aio_gc_seeds($r);
    if (is_null($n))
        $d[] = aio_gc_image(aio_gc_left_center(AIO_GC_ICON, AIO_GC_ICON,
            $c['icon'][0] + intval(($c['icon'][1] - AIO_GC_ICON) / 2)),
            aio_gc_icon($r['cached']));
    else
        $d[] = aio_gc_cut(null, aio_gc_left_center($c['icon'][1], 44, $c['icon'][0]), aio_gc_seeds_str($n),
            AIO_GC_SEED_PX, aio_gc_seed_color($n, $on), array(GCompTtfLabelDef::halign => HALIGN_CENTER));
    foreach (aio_gc_row_badges($r['badges']) as $k => $ids)
    {
        if ($ids && isset($c[$k]))
            $d = array_merge($d, aio_gc_plates($ids, $c[$k][0]));
    }
    if ($r['size'] > 0 && isset($c['size']))
        $d[] = aio_gc_cut(null, aio_gc_left_center($c['size'][1], 44, $c['size'][0]),
            aio_size_str($r['size'], $tt['lang']), 28, $hi, array(GCompTtfLabelDef::halign => HALIGN_RIGHT));
    if (isset($c['langs']))
    {
        $x = $c['langs'][0];
        foreach (aio_gc_lang_items($r['langs'], $c['langs'][1]) as $it)
        {
            $d[] = $it[0] === 'flag' ?
                aio_gc_image(aio_gc_left_center(36, 36, $x), "gui_skin://flags/{$it[1]}.png") :
                aio_gc_cut(null, aio_gc_left_center($it[2], 40, $x), $it[1], 24, $hi);
            $x += $it[2] + 6;
        }
    }
    // The name of the AIOStreams expression in place of the trackers (the
    // card keeps them); no trackers (DMM Cast, some StremThru Torz): the
    // add-on, dimmed.
    if (($r['expr'] !== '' || $r['trackers'] || $r['addon'] !== '') && isset($c['tracker']))
    {
        $cw = $c['tracker'][1];
        if ($r['expr'] !== '')
            $txt = aio_gc_cut_w(aio_clean($r['expr']), 24, $cw);
        else if ($r['trackers'])
            $txt = aio_gc_tracker($r, $cw);
        else
            $txt = aio_gc_cut_w(aio_clean($r['addon']), 24, $cw);
        $d[] = aio_gc_cut(null, aio_gc_left_center($cw, 40, $c['tracker'][0]), $txt, 24,
            $r['expr'] !== '' || $r['trackers'] ? $hi : ($on ? AIO_GC_TEXT2 : AIO_GC_DIM));
    }
    if ($share > 0)
    {
        $w = AIO_GC_LW - 2 * AIO_GC_PAD;
        $fw = max(AIO_GC_BAR_MIN, intval(round($w * min(1, $share))));
        $y = AIO_GC_ROW_H - AIO_GC_BAR_H;
        $d[] = aio_gc_rect(aio_gc_at($fw, AIO_GC_BAR_H, AIO_GC_PAD, $y), AIO_GC_BAR_DONE);
        if ($fw < $w)
            $d[] = aio_gc_rect(aio_gc_at($w - $fw, AIO_GC_BAR_H, AIO_GC_PAD + $fw, $y), AIO_GC_BAR_REST);
    }
    return $d;
}

/**
 * @param int $i
 * @param AioRow $r
 * @param AioGcText $tt
 * @param AioGcCols $c
 * @param bool $on
 * @param float|int $share
 * @return AioGcComp
 */
function aio_gc_row($i, $r, $tt, $c, $on = false, $share = 0)
{
    return aio_gc_panel("r$i", aio_gc_row_geom($i), aio_gc_row_items($r, $tt, $c, $on, $share));
}

// Share of the release of a row watched before (aio_wh_progress), or 0.
/**
 * @param AioProgress $prog
 * @param AioRow $r
 * @return float|int
 */
function aio_gc_share($prog, $r)
{
    return $r['hash'] !== '' && isset($prog[$r['hash']]) ? $prog[$r['hash']] : 0;
}

// Children of 'card' (left column, at the poster's top left). Beside the
// poster: the size (48 px), the bit rate, the size of a season pack, the
// cache status (in the colour of the row's icon), the release group, the
// source (platform of a WEB-DL); at the poster's bottom the add-on when it
// fits. Under
// the poster: the name (a fixed block of 2 lines, 26 px), then by priority
// video, audio, voices (of the audio tracks and the name), subtitles, languages,
// trackers (without them the add-on, unless beside the poster) - what does not
// fit above AIO_GC_CARD_BOTTOM is cut or left out from the end.
/**
 * @param AioState $st
 * @param int $sel
 * @return list<AioGcComp>
 */
function aio_gc_card($st, $sel)
{
    $r = $st['rows'][$sel];
    $tt = aio_gc_text(aio_gc_lang($st));
    $w = AIO_GC_CW;
    $lh = 36;
    // Beside the poster; without a poster at the left edge.
    $sx = aio_img($st['movie']['poster']) !== '' ? AIO_GC_PW + 20 : 0;
    $sw = $w - $sx;
    $d = array();
    $y = 0;
    if ($r['size'] > 0)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($sw, 60, $sx, $y), aio_size_str($r['size'], $tt['lang']), 48, AIO_GC_TEXT);
        $y += 64;
    }
    if ($r['rate'] > 0)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $y), aio_gc_rate($r['rate'], $r['rate_approx'], $tt['lang']),
            30, AIO_GC_TEXT2);
        $y += $lh;
    }
    if ($r['pack'] > 0)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $y),
            $tt['pack'] . ' ' . aio_size_str($r['pack'], $tt['lang']), 30, AIO_GC_TEXT2);
        $y += $lh;
    }
    $y += 8;
    list($status, $color) = aio_gc_card_status($r, $tt);
    foreach (aio_gc_wrap($status, 30, $sw, 2, true) as $line)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $y), $line, 30, $color);
        $y += $lh;
    }
    if ($r['group'] !== '')
    {
        $y += 16;
        $d[] = aio_gc_cut(null, aio_gc_at($sw, 32, $sx, $y), $tt['group'], 26, AIO_GC_DIM);
        $y += 32;
        foreach (aio_gc_wrap(aio_clean($r['group']), 30, $sw, 2) as $line)
        {
            $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $y), $line, 30, AIO_GC_TEXT2);
            $y += $lh;
        }
    }
    // The platform of a WEB-DL: one line, so that it fits beside the poster
    // in the worst case (two lines of status and of release).
    if ($r['network'] !== '')
    {
        $y += 16;
        $d[] = aio_gc_cut(null, aio_gc_at($sw, 32, $sx, $y), $tt['source'], 26, AIO_GC_DIM);
        $y += 32;
        $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $y), aio_gc_cut_w(aio_clean($r['network']), 30, $sw), 30,
            AIO_GC_TEXT2);
        $y += $lh;
    }
    // The add-on at the poster's bottom (its value ends at AIO_GC_PH), when it
    // fits under the fields above; else under the poster without trackers.
    $addon = aio_clean($r['addon']);
    $ay = AIO_GC_PH - $lh - 32;
    $addon_side = $addon !== '' && $y + 16 <= $ay;
    if ($addon_side)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($sw, 32, $sx, $ay), $tt['addon'], 26, AIO_GC_DIM);
        $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $ay + 32), aio_gc_cut_w($addon, 30, $sw), 30, AIO_GC_TEXT2);
    }

    // Under the poster's place even without a poster: the fields do not move.
    $y = AIO_GC_PH + 20;
    $room = AIO_GC_CARD_BOTTOM - AIO_GC_CY - $y;

    // The name: always a block of 2 lines at 26 px, cut with "…", so that the
    // fields below stay in place from one release to the next.
    $name = aio_clean($r['label']);
    if (mb_strlen($name, 'UTF-8') > AIO_GC_NAME_MAX)
        $name = rtrim(mb_substr($name, 0, AIO_GC_NAME_MAX, 'UTF-8')) . "\xE2\x80\xA6";
    $ny = $y;
    foreach (aio_gc_wrap($name, AIO_GC_NAME_PX, $w, AIO_GC_NAME_LINES) as $line)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($w, AIO_GC_NAME_LH, 0, $ny), $line, AIO_GC_NAME_PX, AIO_GC_TEXT);
        $ny += AIO_GC_NAME_LH;
    }
    $y += AIO_GC_NAME_LINES * AIO_GC_NAME_LH + 14;
    $room -= AIO_GC_NAME_LINES * AIO_GC_NAME_LH + 14;

    // Fields by priority, each up to 2 lines while room is left, the voices
    // up to AIO_GC_VOICES_LINES.
    // "Дубляж: …" of the voices first, on lines of its own.
    $vs = aio_row_voices_struct($r);
    $b = $r['badges'];
    $video = array($r['resolution'], aio_badge_text($b['vis']), $b['ai'] ? 'AI' : '', $r['quality'],
        implode(' ', $b['tags']));
    foreach (array(
        array('video', implode(' · ', array_filter($video, 'strlen'))),
        array('audio', $r['audio_full']),
        // Subtitles have a field of their own.
        array('voices', aio_voices_struct_text($vs, $tt['lang'], false)),
        array('subs', aio_cut(implode(', ', $r['subs']), AIO_TEXT_MAX)),
        array('langs', aio_cut(implode(', ', $r['langs']), AIO_TEXT_MAX)),
        $r['trackers'] ? array('trackers', aio_cut(implode(', ', $r['trackers']), AIO_TEXT_MAX)) :
            array('addon', $addon_side ? '' : $r['addon'])) as $f)
    {
        $v = aio_clean($f[1]);
        $vlh = $f[0] === 'voices' ? AIO_GC_VOICES_LH : $lh;
        $px = $f[0] === 'voices' ? AIO_GC_VOICES_PX : 30;
        // The label (36 px) is the first line's height.
        $max = min($f[0] !== 'voices' ? 2 : AIO_GC_VOICES_LINES, intval(($room - 8 - $lh + $vlh) / $vlh));
        // Video and audio are always there (a dash); the others only with data.
        if ($max < 1 || ($v === '' && $f[0] !== 'video' && $f[0] !== 'audio'))
            continue;
        $v = $v !== '' ? $v : "\xE2\x80\x94";
        if ($f[0] === 'voices')
            $vl = aio_gc_wrap_voices($v, $px, $w - AIO_GC_VX, $max, aio_voices_dub_first($vs));
        else
            $vl = $f[0] === 'video' || $f[0] === 'audio' ?
                aio_gc_wrap_items($v, 30, $w - AIO_GC_VX, $max) : aio_gc_wrap($v, 30, $w - AIO_GC_VX, $max);
        $d[] = aio_gc_cut(null, aio_gc_at(AIO_GC_VX - 6, $lh, 0, $y), $tt[$f[0]], 26, AIO_GC_DIM);
        $y0 = $y;
        foreach ($vl as $j => $line)
        {
            // Smaller lines are centred in the first line of the label.
            $d[] = aio_gc_cut(null, aio_gc_at($w - AIO_GC_VX, $vlh, AIO_GC_VX, $y + ($j === 0 ? ($lh - $vlh) / 2 : 0)),
                $line, $px, AIO_GC_TEXT2);
            $y += $j === 0 ? $lh : $vlh;
        }
        $y += 8;
        $room -= $y - $y0;
    }
    return $d;
}

// Bit rate: "59 Мбит/с", "≈ 7.4 Mbps" (one decimal under 10).
/**
 * @param float $bps
 * @param bool $approx
 * @param string $lang
 * @return string
 */
function aio_gc_rate($bps, $approx, $lang)
{
    // By the rounded value: 9.96 -> "10", not "10.0".
    $m = $bps / 1e6;
    return ($approx ? "\xE2\x89\x88 " : '') . number_format($m, round($m, 1) < 10 ? 1 : 0, '.', '') . ' ' .
        aio_tr($lang, 'rate_mbps');
}

// Children of 'counter' (the hint row at the right, 300 x AIO_GC_KEY_H):
// "5 / 60", placed as a hint label.
/**
 * @param int $sel
 * @param int $n
 * @return list<AioGcComp>
 */
function aio_gc_counter($sel, $n)
{
    return array(aio_gc_cut(null, aio_gc_at(300, AIO_GC_LABEL_H, 0, (AIO_GC_KEY_H - AIO_GC_LABEL_H) / 2),
        ($sel + 1) . " / $n", AIO_GC_HINT_PX, AIO_GC_TEXT2, array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// A hint at x of row y: key pictures $keys (url => width, $h high) side by
// side, then label $text, both centred in the row of AIO_GC_KEY_H; returns
// the x of the next hint.
/**
 * @param list<AioGcComp> $d
 * @param int $x
 * @param int $y
 * @param array<string, int> $keys
 * @param string $text
 * @param int $h
 * @return int
 */
function aio_gc_hint(&$d, $x, $y, $keys, $text, $h = AIO_GC_KEY_H)
{
    foreach ($keys as $url => $w)
    {
        $d[] = aio_gc_image(aio_gc_at($w, $h, $x, $y + intval((AIO_GC_KEY_H - $h) / 2)), $url);
        $x += $w;
    }
    $x += AIO_GC_KEY_GAP;
    $w = aio_gc_w($text, AIO_GC_HINT_PX);
    $d[] = aio_gc_cut(null, aio_gc_at($w, AIO_GC_LABEL_H, $x, $y + (AIO_GC_KEY_H - AIO_GC_LABEL_H) / 2), $text,
        AIO_GC_HINT_PX, AIO_GC_TEXT2);
    return $x + $w + AIO_GC_HINT_GAP;
}

// The remote has the colour keys, as the vendor screens check it
// (dune_config.php of the firmware; an old one has none: the keys are there).
// Without them no sort tabs, the list where it is (their row empty).
/**
 * @return bool
 */
function aio_gc_color_keys()
{
    return !function_exists('dune_config_with_color_buttons') || dune_config_with_color_buttons();
}

// The colour keys of the sort in the order of the remote: key of
// aio_sort_keys => array(event, the vendor key picture).
/**
 * @return array<string, array{string, string}>
 */
function aio_gc_sort_keys()
{
    $u = 'plugin_file://%shell_ext%/icons/';
    return array('aio' => array(GUI_EVENT_KEY_A_RED, $u . 'red.png'),
        'seeds' => array(GUI_EVENT_KEY_B_GREEN, $u . 'green.png'),
        'size' => array(GUI_EVENT_KEY_C_YELLOW, $u . 'yellow.png'),
        'date' => array(GUI_EVENT_KEY_D_BLUE, $u . 'blue.png'));
}

// Children of 'sort' (the tabs over the list): each colour key and its label,
// the green one "Cached" in a Debrid list (aio_rows_debrid); the sort of the
// list with a bright label, its direction (a triangle: down by default, up
// reversed) and a line under the key to the triangle; the others dimmed.
/**
 * @param AioState $st
 * @param AioGcText $tt
 * @return list<AioGcComp>
 */
function aio_gc_sort_tabs($st, $tt)
{
    $d = array();
    $x = 0;
    foreach (aio_gc_sort_keys() as $k => $key)
    {
        $text = $tt[$k === 'seeds' && aio_rows_debrid($st['rows']) ? 'sort_cached' : "sort_$k"];
        $on = $k === $st['sort'];
        $x0 = $x;
        $d[] = aio_gc_image(aio_gc_at(AIO_GC_KEY_W, AIO_GC_KEY_H, $x, 0), $key[1]);
        $x += AIO_GC_KEY_W + AIO_GC_KEY_GAP;
        $w = aio_gc_w($text, AIO_GC_HINT_PX);
        $d[] = aio_gc_cut(null, aio_gc_at($w, AIO_GC_LABEL_H, $x, (AIO_GC_KEY_H - AIO_GC_LABEL_H) / 2), $text,
            AIO_GC_HINT_PX, $on ? AIO_GC_TEXT : AIO_GC_TEXT2);
        $x += $w + AIO_GC_SORT_ARROW_GAP;
        if ($on)
        {
            $d[] = aio_gc_image(aio_gc_at(AIO_GC_SORT_ARROW_W, AIO_GC_SORT_ARROW_H, $x,
                intval((AIO_GC_KEY_H - AIO_GC_SORT_ARROW_H) / 2)),
                'plugin_file://img/' . ($st['sort_rev'] ? 'sort_up.png' : 'sort_down.png'));
            $d[] = aio_gc_rect(aio_gc_at($x + AIO_GC_SORT_ARROW_W - $x0, AIO_GC_TAB_LINE, $x0, AIO_GC_KEY_H + 3),
                AIO_GC_TEXT);
        }
        $x += AIO_GC_SORT_ARROW_W + AIO_GC_HINT_GAP;
    }
    return $d;
}

/**
 * @param AioState $st
 * @param int $sel
 * @param int $top
 * @return array<string, mixed>
 */
function aio_gc_window($st, $sel, $top)
{
    $mv = $st['movie'];
    $rows = $st['rows'];
    $n = count($rows);
    $tt = aio_gc_text(aio_gc_lang($st));
    $cols = aio_gc_cols($rows, $tt);
    $d = array();
    $d[] = aio_gc_rect(aio_gc_at(1920, 1080, 0, 0), '#C0000000');
    $poster = aio_img($mv['poster']);
    if ($poster !== '')
        $d[] = aio_gc_image(aio_gc_at(AIO_GC_PW, AIO_GC_PH, AIO_GC_CX, AIO_GC_CY), $poster, true);
    $d[] = aio_gc_panel('card', aio_gc_at(AIO_GC_CW, AIO_GC_CARD_BOTTOM - AIO_GC_CY, AIO_GC_CX, AIO_GC_CY),
        aio_gc_card($st, $sel));

    // Title cut alone; an episode has its own label at the right.
    list($title, $ep) = aio_gc_title($st);
    $ew = $ep === '' ? 0 : aio_gc_w($ep, 36);
    $d[] = aio_gc_cut(null, aio_gc_at(AIO_GC_LW - 16 - ($ew > 0 ? $ew + 30 : 0), 66, AIO_GC_LX, 40), $title, 48,
        AIO_GC_TEXT);
    if ($ep !== '')
        $d[] = aio_gc_cut(null, aio_gc_at($ew, 50, AIO_GC_LX + AIO_GC_LW - 16 - $ew, 52), $ep, 36, AIO_GC_TEXT2,
            array(GCompTtfLabelDef::halign => HALIGN_RIGHT));

    // Viewport: clipping panel 'list'; the inner panel 'rows' moves to scroll.
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $in = array(aio_gc_rect(aio_gc_row_geom($sel), AIO_GC_SEL, 'sel'));
    foreach ($rows as $i => $r)
        $in[] = aio_gc_row($i, $r, $tt, $cols, $i === $sel, aio_gc_share($prog, $r));
    $d[] = aio_gc_panel('list', aio_gc_at(AIO_GC_LW, AIO_GC_VIS * AIO_GC_ROW_H, AIO_GC_LX, AIO_GC_LY),
        array(aio_gc_panel('rows', aio_gc_rows_geom($top, $n), $in, 0)), 0);
    $hx = AIO_GC_LX;
    if ($n > AIO_GC_VIS)
    {
        $d[] = aio_gc_rect(aio_gc_at(6, AIO_GC_VIS * AIO_GC_ROW_H, AIO_GC_LX + AIO_GC_LW + 10, AIO_GC_LY),
            '#30FFFFFF');
        $d[] = aio_gc_rect(aio_gc_thumb_geom($top, $n), '#C0FFFFFF', 'thumb');
        // The vendor hint: the P+/P- key and its label (action_scroll of
        // shell_ext), as wide as the text: clear of the counter at the right.
        $hx = aio_gc_hint($d, $hx, AIO_GC_HINT_Y, array('plugin_file://%shell_ext%/icons/p_plus_p_minus.png' => 114),
            $tt['page']);
    }
    // An episode: the left and right keys go to the episode before and after.
    if ($st['e'] > 0)
        $hx = aio_gc_hint($d, $hx, AIO_GC_HINT_Y, array('plugin_file://img/left_arrow.png' => AIO_GC_ARROW_W,
            'plugin_file://%shell_ext%/icons/right_arrow.png' => AIO_GC_ARROW_W), $tt['flip'], AIO_GC_ARROW_H);
    // The keys of aio_gc_folder_view: POP UP (the "≡" key of the remote, as
    // the vendor popup_icon_only.png) -> aio_gc_menu, INFO ("i") -> the details.
    $hx = aio_gc_hint($d, $hx, AIO_GC_HINT_Y,
        array('plugin_file://%shell_ext%/icons/popup_icon_only.png' => 52), $tt['actions']);
    aio_gc_hint($d, $hx, AIO_GC_HINT_Y, array('plugin_file://img/info_key.png' => 52), $tt['details']);
    $d[] = aio_gc_panel('counter', aio_gc_at(300, AIO_GC_KEY_H, AIO_GC_LX + AIO_GC_LW - 16 - 300, AIO_GC_HINT_Y),
        aio_gc_counter($sel, $n));
    if (aio_gc_color_keys())
        $d[] = aio_gc_panel('sort', aio_gc_at(AIO_GC_LW, AIO_GC_KEY_H + 3 + AIO_GC_TAB_LINE, AIO_GC_LX, AIO_GC_TABS_Y),
            aio_gc_sort_tabs($st, $tt));

    $fanart = aio_img($mv['fanart']);
    // Same key set as vendor GCompsFactory::get_window_def().
    return array(
        GCompWindowDef::background_color => 'rgb(0,0,0)',
        GCompWindowDef::background_url => $fanart !== '' ? $fanart : null,
        GCompWindowDef::async_loading_background => true,
        GCompWindowDef::comp_defs => $d,
        GCompWindowDef::ui_state => null,
        GCompWindowDef::not_loaded_background_url => null,
        GCompWindowDef::playback_bg_alpha => -1,
        GCompWindowDef::background_fit_def => array(
            ImageFitDef::base_halign_ratio => 0.62,
            ImageFitDef::base_valign_ratio => 0.5),
        GCompWindowDef::small_state_text => null,
        GCompWindowDef::opaque_background => false);
}

// --- Entry points from main.php.

// Back from the player to the kept screen of list $st (aio_finish): the
// progress read anew (expected: the shell writes the history before this
// event - one shell log, unverified); the rows of release $hash (one hash
// may come in several rows: RD and TorBox) redrawn when its share changed,
// else null.
/**
 * @param AioState $st
 * @param string $hash
 * @return AioAction|null
 */
function aio_gc_bar_update($st, $hash)
{
    $old = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $prog = aio_wh_progress($st);
    Aio::$state['gc_prog'] = $prog;
    $rows = array();
    foreach ($st['rows'] as $k => $r)
    {
        if ($hash !== '' && $r['hash'] === $hash)
            $rows[] = $k;
    }
    if (!$rows)
    {
        aio_log('finish: progress: the release played has no infoHash or is not in the list');
        return null;
    }
    $was = isset($old[$hash]) ? $old[$hash] : 0;
    $now = isset($prog[$hash]) ? $prog[$hash] : 0;
    aio_log(sprintf('finish: progress %.1f%% -> %.1f%%, rows %s', $was * 100, $now * 100, implode(', ', $rows)));
    if ($was == $now)
        return null;
    list($sel, $top) = aio_gc_pos($st, null);
    $tt = aio_gc_text(aio_gc_lang($st));
    $cols = aio_gc_cols($st['rows'], $tt);
    $ch = array();
    foreach ($rows as $i)
        $ch[] = aio_gc_change("r$i", null, GCOMP_TRANSITION_NONE,
            aio_gc_row_items($st['rows'][$i], $tt, $cols, $i === $sel, $now));
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => $ch,
            ChangeGCompsActionData::num_steps => AIO_GC_STEPS,
            ChangeGCompsActionData::sel_state => aio_gc_state($sel, $top)));
}

// get_folder_view of a live list ($st matches the media_url).
/**
 * @param AioState $st
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_gc_folder_view($st, $sel_state)
{
    // First view of a list put in by aio_finish or aio_flip: its row, not a sel_state
    // the shell may carry over from the replaced screen.
    list($sel, $top) = aio_gc_pos($st, isset($st['gc_new']) ? null : $sel_state);
    unset(Aio::$state['gc_new']);
    Aio::$state['gc'] = array($sel, $top);
    // Progress read anew with every full screen (back from the player too);
    // kept for the rows that gc_move redraws.
    $st['gc_prog'] = Aio::$state['gc_prog'] = aio_wh_progress($st);
    $rid = $st['rid'];
    $acts = array(
        GUI_EVENT_KEY_UP => aio_gc_act('gc_move', $rid, array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => aio_gc_act('gc_move', $rid, array('d' => 'down')),
        GUI_EVENT_KEY_P_PLUS => aio_gc_act('gc_move', $rid, array('d' => 'pgup')),
        GUI_EVENT_KEY_P_MINUS => aio_gc_act('gc_move', $rid, array('d' => 'pgdn')),
        GUI_EVENT_KEY_ENTER => aio_gc_act('gc_pick', $rid),
        GUI_EVENT_KEY_INFO => aio_gc_act('gc_info', $rid),
        GUI_EVENT_MENU_PLAYBACK_FINISH => aio_finish_act($rid),
        // MENU: built for the row under the cursor (aio_gc_menu).
        GUI_EVENT_KEY_POPUP_MENU => aio_gc_act('gc_menu', $rid));
    // An episode: left and right flip episodes (aio_flip).
    if ($st['e'] > 0)
    {
        $acts[GUI_EVENT_KEY_LEFT] = aio_gc_act('gc_flip', $rid, array('d' => 'prev'));
        $acts[GUI_EVENT_KEY_RIGHT] = aio_gc_act('gc_flip', $rid, array('d' => 'next'));
    }
    // The colour keys: the sort (aio_gc_sort).
    foreach (aio_gc_sort_keys() as $k => $key)
        $acts[$key[0]] = aio_gc_act('gc_sort', $rid, array('k' => $k));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => aio_gc_window($st, $sel, $top),
            PluginGCompsFolderView::sel_state => aio_gc_state($sel, $top),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => null));
}

// gc_sort (k: a key of aio_sort_keys) of a live list: its rows in that
// order, by default descending (aio_rows_sort); the key of the list's sort
// again: the other way round. The sort kept for new lists (aio_sort_write;
// not written: this list only); the screen replaced as by aio_refresh (a new
// rid), the cursor on the first row. A bad key, a list not live: nothing.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_gc_sort($in)
{
    $st = Aio::$state;
    $k = isset($in->k) && is_string($in->k) ? $in->k : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !in_array($k, aio_sort_keys(), true))
    {
        aio_log('sort: not a live list or a bad key, nothing');
        return null;
    }
    $rev = $k === $st['sort'] && !$st['sort_rev'];
    $tag = $k . ($rev ? ' reversed' : '');
    if (!aio_sort_write($k, $rev))
        aio_log("sort: $tag not saved, this list only");
    // aio_list_state sorts by the settings: the new sort, saved or not.
    Aio::$settings['sort'] = $k;
    Aio::$settings['sort_rev'] = $rev;
    $ns = aio_list_state($st['movie'], $st['s'], $st['e'], $st['rows'], $st['lang']);
    aio_log("sort: {$st['sort']}" . ($st['sort_rev'] ? ' reversed' : '') . " -> $tag, " . aio_ep_tag($st) . ', ' .
        count($st['rows']) . ' rows, screen replaced');
    return aio_list_replace(aio_gc_put_cursor($ns, 0));
}

// gc_menu (MENU) of a live list -> the popup menu of the row under the cursor:
// "Refresh" (aio_refresh), an episode: "Choose episode" (aio_choose), a row
// with infoHash: "Download in app" (aio_download) and, with a server in the
// settings, "Download to server" (aio_srv_menu), "Settings". A screen of a
// lost list (replaced, php_server restarted): "Settings" only.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_gc_menu($in)
{
    $st = Aio::$state;
    $setup = array(
        GuiMenuItemDef::caption => $st ? aio_tr($st['lang'], 'setup_menu') : '%tr%setup_menu',
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/setup.aai',
        GuiMenuItemDef::action => aio_setup_open_act());
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('menu: list outdated, settings only');
        return array(
            GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
            GuiAction::data => array(ShowPopupMenuActionData::menu_items => array($setup)));
    }
    list($sel, $top) = aio_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    Aio::$state['gc'] = array($sel, $top);
    $rid = $st['rid'];
    $menu = array(array(
        GuiMenuItemDef::caption => aio_tr($st['lang'], 'refresh_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/upgrade.aai',
        GuiMenuItemDef::action => aio_gc_act('gc_refresh', $rid)));
    if ($st['e'] > 0)
        $menu[] = array(
            GuiMenuItemDef::caption => aio_tr($st['lang'], 'choose_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/playlist_file.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_choose', $rid));
    if ($st['rows'][$sel]['hash'] !== '')
        $menu[] = array(
            GuiMenuItemDef::caption => aio_tr($st['lang'], 'ts_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/torrents.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_ts', $rid, array('i' => strval($sel))));
    if ($st['rows'][$sel]['hash'] !== '')
        $menu[] = array(
            GuiMenuItemDef::caption => aio_tr($st['lang'], 'dl_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/torrent_file.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_dl', $rid, array('i' => strval($sel))));
    if ($st['rows'][$sel]['hash'] !== '' && Aio::$settings['servers'])
        $menu[] = array(
            GuiMenuItemDef::caption => aio_tr($st['lang'], 'srv_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/network_folder.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_srv', $rid, array('i' => strval($sel))));
    $menu[] = $setup;
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => $menu));
}

// gc_move -> change_gcomps (null at an edge); gc_pick -> playback; gc_info ->
// the details screen (back on this one, aio_gc_pos finds the row in 'gc').
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_gc_input($in)
{
    $st = Aio::$state;
    $live = $st && isset($in->rid) && $in->rid === $st['rid'];
    $raw = isset($in->parent_sel_state) ? $in->parent_sel_state : null;
    if ($in->control_id === 'gc_pick' || $in->control_id === 'gc_info')
    {
        if (!$live)
            return aio_error('err_list_expired');
        list($sel, $top) = aio_gc_pos($st, $raw);
        Aio::$state['gc'] = array($sel, $top);
        if ($in->control_id === 'gc_info')
            return aio_info_open($st, $sel);
        // A P2P stream (no URL, infoHash only): through TorrServer.
        if (aio_is_p2p($st['rows'][$sel]))
            return aio_ts_open(Aio::$state, $sel, 0, $st['rid']);
        $a = aio_play($st, $st['rows'][$sel], 0);
        if ($a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
            aio_playing($st['rid'], Aio::$state, $sel);
        return $a;
    }

    $key = isset($in->d) && is_string($in->d) ? $in->d : '';
    if (!$live || !in_array($key, array('up', 'down', 'pgup', 'pgdn'), true))
        return null;
    $n = count($st['rows']);
    list($sel, $top) = aio_gc_pos($st, $raw);
    list($nsel, $ntop) = aio_gc_step($sel, $top, $n, $key);
    Aio::$state['gc'] = array($nsel, $ntop);
    if ($nsel === $sel)
        return null;

    $ch = array(aio_gc_change('sel', aio_gc_row_geom($nsel), GCOMP_TRANSITION_DEFAULT));
    if ($ntop !== $top)
    {
        $ch[] = aio_gc_change('rows', aio_gc_rows_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT);
        $ch[] = aio_gc_change('thumb', aio_gc_thumb_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT);
    }
    // The focus brightness moves with the row.
    $tt = aio_gc_text(aio_gc_lang($st));
    $cols = aio_gc_cols($st['rows'], $tt);
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $ch[] = aio_gc_change("r$sel", null, GCOMP_TRANSITION_NONE,
        aio_gc_row_items($st['rows'][$sel], $tt, $cols, false, aio_gc_share($prog, $st['rows'][$sel])));
    $ch[] = aio_gc_change("r$nsel", null, GCOMP_TRANSITION_NONE,
        aio_gc_row_items($st['rows'][$nsel], $tt, $cols, true, aio_gc_share($prog, $st['rows'][$nsel])));
    $ch[] = aio_gc_change('card', null, GCOMP_TRANSITION_NONE, aio_gc_card($st, $nsel));
    $ch[] = aio_gc_change('counter', null, GCOMP_TRANSITION_NONE, aio_gc_counter($nsel, $n));
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => $ch,
            ChangeGCompsActionData::num_steps => AIO_GC_STEPS,
            ChangeGCompsActionData::sel_state => aio_gc_state($nsel, $ntop)));
}
