<?php
// Stream list as a view_gcomps screen, 1920x1080, on the grid of the Dune
// movie card (poster 288x432 at 75,60, margins 75): darkened
// fanart; on the left the poster, beside it the size and the cache status,
// under it a card of the selected release (name, video, audio, voices,
// subtitles, languages, trackers); on the right the list: cache icon, badges
// of kingsizew/badges (resolution, source, picture, the best audio),
// size, language flags, trackers if room is left; under it the P+/P- hint
// and "1 / 60". The plugin draws and moves the selection itself with
// change_gcomps; the position travels in sel_state {"sel":N,"top":T}.
// Shapes as proven on the device (04.10.2026).
// Reads Aio (state, settings), writes the cursor keys of Aio::$state.
// PHP 5.3.6, firmware API only.

require_once dirname(__FILE__) . '/gcomps.php';

define('AIO_GC_CX', 75);       // left column: poster and card, as the movie card
define('AIO_GC_CW', 585);      // to x 660: the card keeps its width
define('AIO_GC_CY', 60);
define('AIO_GC_PW', 288);      // poster, as the movie card
define('AIO_GC_PH', 432);
define('AIO_GC_LX', 680);      // list: 20 px after the card, as 445 -> 465 of the movie card
define('AIO_GC_LW', 1165);     // right edge at 1845 = 1920 - 75
define('AIO_GC_LY', 130);      // 24 px under the title, no column heads
define('AIO_GC_ROW_H', 64);
define('AIO_GC_VIS', 13);      // visible rows: the list ends at y 962
define('AIO_GC_HINT_Y', 990);  // P+/P- key and "1 / 60", as the vendor hint row
define('AIO_GC_HINT_GAP', 80);  // between hints of that row (vendor HINTS_HGAP)
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
function aio_gc_text($lang)
{
    return array('lang' => $lang, 'page' => aio_tr($lang, 'gc_page'), 'pack' => aio_tr($lang, 'gc_pack'),
        'release' => aio_tr($lang, 'gc_release'), 'source' => aio_tr($lang, 'gc_source'),
        'cached' => aio_tr($lang, 'gc_cached'),
        'cached_in' => aio_tr($lang, 'gc_cached_in'),
        'download' => aio_tr($lang, 'gc_download'), 'unknown' => aio_tr($lang, 'gc_unknown'),
        'p2p' => aio_tr($lang, 'gc_peers'),
        'video' => aio_tr($lang, 'gc_video'), 'audio' => aio_tr($lang, 'gc_audio'),
        'voices' => aio_tr($lang, 'gc_voices'), 'subs' => aio_tr($lang, 'gc_subs'),
        'langs' => aio_tr($lang, 'gc_langs'), 'trackers' => aio_tr($lang, 'gc_trackers'),
        'addon' => aio_tr($lang, 'gc_addon'),
        'flip' => aio_tr($lang, 'gc_flip'));
}

// The cache status of row $r: "В кэше <service>", "Нужно скачать", "P2P" (no
// URL: TorrServer plays it), "неизвестно".
function aio_gc_status($r, $tt)
{
    if ($r['cached'] === true)
        return $r['service'] !== '' ? str_replace('{svc}', $r['service'], $tt['cached_in']) : $tt['cached'];
    if ($r['cached'] === false)
        return $tt['download'];
    return $r['url'] === '' ? $tt['p2p'] : $tt['unknown'];
}

// --- Position.

function aio_gc_state($sel, $top)
{
    return json_encode(array('sel' => $sel, 'top' => $top));
}

// sel_state -> array(sel, top), or null when it does not fit $n rows.
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
function aio_gc_cursor($sel, $n)
{
    return array($sel, max(0, min($sel - intval(AIO_GC_VIS / 2), $n - AIO_GC_VIS)));
}

// List state $st put in with row $sel selected (aio_finish, aio_relist): the
// cursor as aio_gc_cursor, and its first view ignores the shell's sel_state.
function aio_gc_put_cursor($st, $sel)
{
    $st['gc'] = aio_gc_cursor($sel, count($st['rows']));
    $st['gc_new'] = true;
    return $st;
}

// One key: up, down, pgup (P+), pgdn (P-). A page keeps the row on screen.
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
function aio_gc_icon($cached)
{
    return 'plugin_file://img/' . (is_null($cached) ? 'unknown.png' :
        ($cached ? 'cached.png' : 'download.png'));
}

// Languages of a row: those with a flag first, then the rest; the service
// codes only if there is no real language.
function aio_gc_tail_langs($langs)
{
    $flags = aio_gc_flags();
    $service = array('MULTI', 'DUAL', 'DUB', 'ORIG');
    $out = array('flag' => array(), 'text' => array(), 'service' => array());
    foreach ($langs as $c)
        $out[isset($flags[$c]) ? 'flag' : (in_array($c, $service, true) ? 'service' : 'text')][] = $c;
    return $out['flag'] || $out['text'] ? array_merge($out['flag'], $out['text']) : $out['service'];
}

function aio_gc_lang($st)
{
    return $st['lang'] === 'ru' ? 'ru' : 'en';
}

// array(title with year, "S05E03 · 60 раздач").
function aio_gc_title($st, $n)
{
    $mv = $st['movie'];
    $title = $mv['title'] . (preg_match('/^[0-9]{4}$/', $mv['year']) ? " ({$mv['year']})" : '');
    $ep = $st['e'] > 0 ? sprintf('S%02dE%02d · ', $st['s'], $st['e']) : '';
    $lang = aio_gc_lang($st);
    if ($lang === 'en')
        $form = $n === 1 ? 'gc_streams_one' : 'gc_streams_many';
    else
    {
        $m = $n % 100 >= 11 && $n % 100 <= 14 ? 5 : $n % 10;
        $form = $m === 1 ? 'gc_streams_one' : ($m >= 2 && $m <= 4 ? 'gc_streams_few' : 'gc_streams_many');
    }
    return array($title, "$ep$n " . aio_tr($lang, $form));
}

// Languages of a row: up to AIO_GC_LANGS_MAX flags of the skin, a code
// without a flag as text, the rest as "+N"; service codes (MULTI, DUAL, ...)
// only without a real language. Only what fits $width with the "+N" after
// it. -> array(flag|text, value, width).
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

function aio_gc_items_w($items)
{
    $w = 0;
    foreach ($items as $it)
        $w += $it[2];
    return $items ? $w + 6 * (count($items) - 1) : 0;
}

// "rutracker, bitru +2" if that fits $width, else "rutracker +3"
// with the name cut; the "+N" stays.
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
function aio_gc_row_badges($b)
{
    $cols = array('res' => array($b['res']), 'src' => array($b['src']),
        'vis' => array($b['vis']), 'audio' => $b['audio']);
    foreach ($cols as $k => $ids)
        $cols[$k] = array_values(array_intersect($ids, array_keys(AioGc::$badge_w)));
    return $cols;
}

// Width of badges side by side, gaps between.
function aio_gc_plates_w($ids)
{
    $w = 0;
    foreach ($ids as $id)
        $w += AioGc::$badge_w[$id];
    return $ids ? $w + AIO_GC_BADGE_GAP * (count($ids) - 1) : 0;
}

// Column x and width for this list, each by its widest value; empty columns
// take no room. The badges and the size keep their width; the languages get
// what is left, the trackers (or the expression name, or the add-on) the rest
// if it is at least AIO_GC_TRACKER_MIN.
function aio_gc_cols($rows, $tt)
{
    $max = array('res' => 0, 'src' => 0, 'vis' => 0, 'audio' => 0, 'size' => 0);
    $langs = 0;
    $any_tracker = false;
    foreach ($rows as $r)
    {
        foreach (aio_gc_row_badges($r['badges']) as $k => $ids)
            $max[$k] = max($max[$k], aio_gc_plates_w($ids));
        $max['size'] = max($max['size'], $r['size'] > 0 ?
            min(180, aio_gc_w(aio_size_str($r['size'], $tt['lang']), 28)) : 0);
        $langs = max($langs, min(260, aio_gc_items_w(aio_gc_lang_items($r['langs'], PHP_INT_MAX))));
        $any_tracker = $any_tracker || $r['expr'] !== '' || $r['trackers'] || $r['addon'] !== '';
    }
    $cols = array('icon' => array(12, 38));
    $x = 12 + 38 + 14;
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

function aio_gc_row_geom($i)
{
    return aio_gc_at(AIO_GC_LW, AIO_GC_ROW_H, 0, $i * AIO_GC_ROW_H);
}

function aio_gc_rows_geom($top, $n)
{
    return aio_gc_at(AIO_GC_LW, $n * AIO_GC_ROW_H, 0, -$top * AIO_GC_ROW_H);
}

// Scrollbar thumb: height by the visible share, y by the top row.
function aio_gc_thumb_geom($top, $n)
{
    $track = AIO_GC_VIS * AIO_GC_ROW_H;
    $h = max(40, intval($track * AIO_GC_VIS / $n));
    $y = intval(round(($track - $h) * $top / ($n - AIO_GC_VIS)));
    return aio_gc_at(6, $h, AIO_GC_LX + AIO_GC_LW + 10, AIO_GC_LY + $y);
}

// Badge pictures (the plate is in the PNG) from x, centred in the row.
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

function aio_gc_status_color($cached)
{
    return is_null($cached) ? AIO_GC_DIM : ($cached ? AIO_GC_GREEN : AIO_GC_GREY);
}

// Children of row panel "r$i". The row in focus is brighter, as in the
// shell's own lists: its texts TEXT, the add-on TEXT2; the others TEXT2, DIM.
// A release watched before ($share > 0): a progress bar at the row bottom.
function aio_gc_row_items($r, $tt, $c, $on, $share = 0)
{
    $hi = $on ? AIO_GC_TEXT : AIO_GC_TEXT2;
    $d = array();
    $d[] = aio_gc_image(aio_gc_left_center(38, 38, $c['icon'][0]), aio_gc_icon($r['cached']));
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

function aio_gc_row($i, $r, $tt, $c, $on = false, $share = 0)
{
    return aio_gc_panel("r$i", aio_gc_row_geom($i), aio_gc_row_items($r, $tt, $c, $on, $share));
}

// Share of the release of a row watched before (aio_wh_progress), or 0.
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
    $status = aio_gc_status($r, $tt);
    foreach (aio_gc_wrap($status, 30, $sw, 2, true) as $line)
    {
        $d[] = aio_gc_cut(null, aio_gc_at($sw, $lh, $sx, $y), $line, 30, aio_gc_status_color($r['cached']));
        $y += $lh;
    }
    if ($r['group'] !== '')
    {
        $y += 16;
        $d[] = aio_gc_cut(null, aio_gc_at($sw, 32, $sx, $y), $tt['release'], 26, AIO_GC_DIM);
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
    $dub = aio_voices_words($tt['lang']);
    $dub = $dub['dub'];
    $b = $r['badges'];
    $video = array($r['resolution'], aio_badge_text($b['vis']), $b['ai'] ? 'AI' : '', $r['quality'],
        implode(' ', $b['tags']));
    foreach (array(
        array('video', implode(' · ', array_filter($video, 'strlen'))),
        array('audio', $r['audio_full']),
        // Subtitles have a field of their own.
        array('voices', aio_row_voices_text($r, $tt['lang'], false)),
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
            $vl = aio_gc_wrap_voices($v, $px, $w - AIO_GC_VX, $max,
                $v === $dub || strpos($v, "$dub:") === 0 || strpos($v, "$dub \xC2\xB7") === 0);
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
function aio_gc_rate($bps, $approx, $lang)
{
    // By the rounded value: 9.96 -> "10", not "10.0".
    $m = $bps / 1e6;
    return ($approx ? "\xE2\x89\x88 " : '') . number_format($m, round($m, 1) < 10 ? 1 : 0, '.', '') . ' ' .
        aio_tr($lang, 'rate_mbps');
}

// Children of 'counter' (under the list, at the right): "5 / 60".
function aio_gc_counter($sel, $n)
{
    return array(aio_gc_cut(null, aio_gc_at(300, 50, 0, 0), ($sel + 1) . " / $n", 36, AIO_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

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

    // Title cut alone; the episode and the count have their own label.
    list($title, $count) = aio_gc_title($st, $n);
    $cw = min(440, aio_gc_w($count, 36));
    $d[] = aio_gc_cut(null, aio_gc_at(AIO_GC_LW - 16 - $cw - 30, 66, AIO_GC_LX, 40), $title, 48, AIO_GC_TEXT);
    $d[] = aio_gc_cut(null, aio_gc_at($cw, 50, AIO_GC_LX + AIO_GC_LW - 16 - $cw, 52), $count, 36, AIO_GC_TEXT2,
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
        // The vendor hint: the P+/P- key and its 36 px label (action_scroll of
        // shell_ext), as wide as the text: clear of the counter at the right.
        $d[] = aio_gc_image(aio_gc_at(114, 50, AIO_GC_LX, AIO_GC_HINT_Y),
            'plugin_file://%shell_ext%/icons/p_plus_p_minus.png');
        $w = aio_gc_w($tt['page'], 36);
        $d[] = aio_gc_cut(null, aio_gc_at($w, 50, AIO_GC_LX + 126, AIO_GC_HINT_Y), $tt['page'], 36, AIO_GC_TEXT2);
        $hx = AIO_GC_LX + 126 + $w + AIO_GC_HINT_GAP;
    }
    // An episode: the left and right keys go to the episode before and after.
    if ($st['e'] > 0)
    {
        $d[] = aio_gc_image(aio_gc_at(52, 50, $hx, AIO_GC_HINT_Y), 'plugin_file://img/left_arrow.png');
        $d[] = aio_gc_image(aio_gc_at(52, 50, $hx + 52, AIO_GC_HINT_Y),
            'plugin_file://%shell_ext%/icons/right_arrow.png');
        $d[] = aio_gc_cut(null, aio_gc_at(aio_gc_w($tt['flip'], 36), 50, $hx + 116, AIO_GC_HINT_Y), $tt['flip'], 36,
            AIO_GC_TEXT2);
    }
    $d[] = aio_gc_panel('counter', aio_gc_at(300, 50, AIO_GC_LX + AIO_GC_LW - 16 - 300, AIO_GC_HINT_Y),
        aio_gc_counter($sel, $n));

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

// gc_menu (MENU) of a live list -> the popup menu of the row under the cursor:
// "Refresh" (aio_refresh), an episode: "Choose episode" (aio_choose), a row
// with infoHash: "Download in app" (aio_download) and, with a server in the
// settings, "Download to server" (aio_srv_menu), "Settings". A screen of a
// lost list (replaced, php_server restarted): "Settings" only.
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
        if ($st['rows'][$sel]['url'] === '')
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
