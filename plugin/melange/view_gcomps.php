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
// Reads Mel (state, settings), writes the cursor keys of Mel::$state;
// mel_gc_sort replaces it by the list sorted anew.
// PHP 5.3.6, firmware API only.
// Types in the PHPDoc (MelGcComp and others): phpstan-types.neon of the repository.

require_once dirname(__FILE__) . '/gcomps.php';

define('MEL_GC_CX', 75);       // left column: poster and card, as the movie card
define('MEL_GC_CW', 585);      // to x 660: the card keeps its width
define('MEL_GC_CY', 60);
define('MEL_GC_PW', 288);      // poster, as the movie card
define('MEL_GC_PH', 432);
define('MEL_GC_LX', 680);      // list: 20 px after the card, as 445 -> 465 of the movie card
define('MEL_GC_LW', 1165);     // right edge at 1845 = 1920 - 75
define('MEL_GC_LY', 194);      // under the sort tabs (12 px), no column heads
define('MEL_GC_ROW_H', 64);
define('MEL_GC_VIS', 12);      // visible rows: the list ends at y 962
// The hint row of the list: the keys at the vendor size, unscaled (scaled
// down they blur on the device): P+/P- 114x50, POP UP and INFO 52x50. The
// episode arrows (the vendor's bare chevron, nearly 50 high) at half size:
// the chevron as high as the glyph on the POP UP plate. Labels 30 px in a box
// of 40 centred on the keys, as on the device in 0.46.1; the gap 10 to a
// label: chosen (vendor text 36, gap 12).
define('MEL_GC_HINT_Y', 990);  // the vendor hint row 990..1040
define('MEL_GC_KEY_H', 50);
define('MEL_GC_KEY_W', 52);    // a vendor key of one button: the colour keys
define('MEL_GC_LABEL_H', 40);
define('MEL_GC_ARROW_W', 26);  // right_arrow.png 52x50 / 2
define('MEL_GC_ARROW_H', 25);
define('MEL_GC_HINT_PX', 30);
define('MEL_GC_KEY_GAP', 10);   // key -> its label
define('MEL_GC_HINT_GAP', 40);  // between hints (vendor HINTS_SMALL_HGAP)
// The sort tabs (mel_gc_sort_tabs): the colour keys and labels as the hints,
// in the row y 125..175 between the title (40..106) and the list; the line
// under the current tab 4 px, 3 px under the keys. The direction: a
// triangle of img/ (sort_down.png, sort_up.png: plugins/melange_img.sh),
// its room kept at every tab, so that the tabs never move.
define('MEL_GC_TABS_Y', 125);
define('MEL_GC_TAB_LINE', 4);
define('MEL_GC_TAB_LINE_Y', MEL_GC_KEY_H + 3);
define('MEL_GC_SORT_ARROW_W', 18);
define('MEL_GC_SORT_ARROW_H', 12);
define('MEL_GC_SORT_ARROW_GAP', 10);    // label -> its triangle
define('MEL_GC_PAD', 16);      // row padding left and right
define('MEL_GC_GAP', 16);      // between columns
// "rutracker +3" (142 px by the estimate) with a margin; less room: no tracker
// column (the card has them).
define('MEL_GC_TRACKER_MIN', 145);
define('MEL_GC_BADGE_H', 46);  // badge pictures with their plate
// Revision in the badge file names (BADGE_REV of their generator):
// the shell caches pictures by URL even across a reinstall.
define('MEL_GC_BADGE_REV', 2);
define('MEL_GC_BADGE_GAP', 10);    // between badges of one column
define('MEL_GC_VX', 156);      // card values after the labels ("Субтитры" 26 px)
define('MEL_GC_NAME_MAX', 400);    // characters of the name in the card, at most
define('MEL_GC_NAME_LINES', 2);    // the name block in the card: 2 lines of 26 px
define('MEL_GC_NAME_PX', 26);
define('MEL_GC_NAME_LH', 32);
define('MEL_GC_VOICES_LINES', 5);  // lines of the voices in the card, at most,
define('MEL_GC_VOICES_PX', 26);    // 26 px text in 32 px lines (as the name): more studios a line
define('MEL_GC_VOICES_LH', 32);
define('MEL_GC_CARD_BOTTOM', 1040);   // the card ends above this y on any data
define('MEL_GC_LANGS_MAX', 3);     // language flags of a row, the rest as "+N"
define('MEL_GC_STEPS', 5);     // change_gcomps animation steps (vendor default)

define('MEL_GC_TEXT', '#FFFFFFE0');
define('MEL_GC_TEXT2', '#FFAFAFA0');
define('MEL_GC_DIM', '#FF808080');
define('MEL_GC_SEL', '#D02850C0');
define('MEL_GC_GREEN', '#FF34C759');
define('MEL_GC_GREY', '#FF8E8E93');    // the "not cached" icon
define('MEL_GC_AMBER', '#FFFFB340');   // the number of source errors over the list
// Seeders of a P2P row in place of its "?" icon (mel_gc_seed_color). Bins as
// the ranked expressions of the P2P template (cgi/aio_template_p2p.json):
// 0-2, 3-9, 10-29, 30+, a bin starts at LOW, MID, HIGH; the template also
// ranks a high bit rate with few seeders lower, the colour does not.
define('MEL_GC_SEED_LOW', 3);
define('MEL_GC_SEED_MID', 10);
define('MEL_GC_SEED_HIGH', 30);
define('MEL_GC_SEED_MAX', 999);    // more: "999+"
define('MEL_GC_SEED_PX', 28);
define('MEL_GC_ICON', 38);    // the cache icon, square; the least width of its column
// Progress bar of a release watched before, as the vendor's.
define('MEL_GC_BAR_H', 4);
define('MEL_GC_BAR_MIN', 4);    // the white part at least, even for a second watched
define('MEL_GC_BAR_DONE', '#FFFFFFFF');
define('MEL_GC_BAR_REST', '#FF404040');

class MelGc
{
    // Badge id of mel_badges -> width of img/nb<REV>_<id>.png, 46 px high with the
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

// Words of the list screen in language $lang of mel_gc_lang, by key.
/**
 * @param string $lang
 * @return MelGcText
 */
function mel_gc_text($lang)
{
    return array('lang' => $lang, 'page' => mel_tr($lang, 'gc_page'), 'pack' => mel_tr($lang, 'gc_pack'),
        'group' => mel_tr($lang, 'gc_group'), 'source' => mel_tr($lang, 'gc_source'),
        'cached' => mel_tr($lang, 'gc_cached'),
        'cached_in' => mel_tr($lang, 'gc_cached_in'), 'cached_and' => mel_tr($lang, 'gc_cached_and'),
        'download' => mel_tr($lang, 'gc_download'), 'unknown' => mel_tr($lang, 'gc_unknown'),
        'p2p' => mel_tr($lang, 'gc_peers'),
        'video' => mel_tr($lang, 'gc_video'), 'audio' => mel_tr($lang, 'gc_audio'),
        'voices' => mel_tr($lang, 'gc_voices'), 'subs' => mel_tr($lang, 'gc_subs'),
        'langs' => mel_tr($lang, 'gc_langs'), 'trackers' => mel_tr($lang, 'gc_trackers'),
        'addon' => mel_tr($lang, 'gc_addon'),
        'flip' => mel_tr($lang, 'gc_flip'),
        'actions' => mel_tr($lang, 'gc_actions'), 'details' => mel_tr($lang, 'gc_details'),
        'sort_aio' => mel_tr($lang, 'gc_sort_aio'), 'sort_seeds' => mel_tr($lang, 'gc_sort_seeds'),
        'sort_cached' => mel_tr($lang, 'gc_sort_cached'), 'sort_size' => mel_tr($lang, 'gc_sort_size'),
        'sort_date' => mel_tr($lang, 'gc_sort_date'), 'errors' => mel_tr($lang, 'gc_errors'));
}

// The cache status of row $r: "В кэше <service>" (a file cached on several:
// "В кэше Real-Debrid\nи TorBox", the copy to play first, the others on a
// line of their own - mel_gc_status_lines), "Нужно скачать", "P2P" (no URL:
// TorrServer plays it), "неизвестно".
/**
 * @param MelRow $r
 * @param MelGcText $tt
 * @return string
 */
function mel_gc_status($r, $tt)
{
    if ($r['cached'] === true)
    {
        $svc = array_values(array_filter(mel_row_services($r), 'strlen'));
        if (!$svc)
            return $tt['cached'];
        $s = str_replace('{svc}', $svc[0], $tt['cached_in']);
        return count($svc) > 1 ? $s . "\n" . str_replace('{svc}', implode(', ', array_slice($svc, 1)), $tt['cached_and']) : $s;
    }
    if ($r['cached'] === false)
        return $tt['download'];
    return mel_is_p2p($r) ? $tt['p2p'] : $tt['unknown'];
}

// Status $s of mel_gc_status in lines of at most $width: each of its lines
// wrapped after spaces ("Real-Debrid" whole), no line ends with a comma;
// more than $max: the last ends with "…".
/**
 * @param string $s
 * @param int $px
 * @param int $width
 * @param int $max
 * @return list<string>
 */
function mel_gc_status_lines($s, $px, $width, $max)
{
    $lines = array();
    foreach (explode("\n", $s) as $part)
    {
        foreach (mel_gc_wrap($part, $px, $width, $max + 1, true) as $l)
            $lines[] = rtrim($l, ' ,');
    }
    if (count($lines) <= $max)
        return $lines;
    $lines = array_slice($lines, 0, $max);
    $ell = "\xE2\x80\xA6";
    $l = (string) array_pop($lines);
    $lines[] = rtrim(mb_substr($l, 0, mel_gc_fit($l, $px, $width - mel_gc_w($ell, $px)), 'UTF-8'), ' ,') . $ell;
    return $lines;
}

// --- Position.

/**
 * @param int $sel
 * @param int $top
 * @return string
 */
function mel_gc_state($sel, $top)
{
    return json_encode(array('sel' => $sel, 'top' => $top));
}

// sel_state -> array(sel, top), or null when it does not fit $n rows.
/**
 * @param mixed $s
 * @param int $n
 * @return array{int, int}|null
 */
function mel_gc_parse($s, $n)
{
    $j = is_string($s) && $s !== '' ? json_decode($s) : null;
    if (!is_object($j) || !isset($j->sel, $j->top) || !is_int($j->sel) || !is_int($j->top))
        return null;
    if ($j->sel < 0 || $j->sel >= $n || $j->top < 0 || $j->top > max(0, $n - MEL_GC_VIS) ||
        $j->sel < $j->top || $j->sel >= $j->top + MEL_GC_VIS)
        return null;
    return array($j->sel, $j->top);
}

// The cursor of a list of $n rows: of the shell's sel_state $raw, else its last one $gc (null
// none), else the first row (mel_gc_pos, mel_lpv_pos). -> array(sel, top).
/**
 * @param mixed $raw
 * @param int $n
 * @param array{int, int}|null $gc
 * @return array{int, int}
 */
function mel_gc_pos_in($raw, $n, $gc)
{
    $p = mel_gc_parse($raw, $n);
    if (!$p && $gc)
        $p = mel_gc_parse(mel_gc_state($gc[0], $gc[1]), $n);
    return $p ? $p : array(0, 0);
}

// From the shell's sel_state; else the last known position of this list.
/**
 * @param MelState $st
 * @param mixed $raw
 * @return array{int, int}
 */
function mel_gc_pos($st, $raw)
{
    return mel_gc_pos_in($raw, count($st['rows']), isset($st['gc']) ? $st['gc'] : null);
}

// Position of a list put in with row $sel of $n selected: the row in the
// middle of the screen, as far as the list allows. -> array(sel, top).
/**
 * @param int $sel
 * @param int $n
 * @return array{int, int}
 */
function mel_gc_cursor($sel, $n)
{
    return array($sel, max(0, min($sel - intval(MEL_GC_VIS / 2), $n - MEL_GC_VIS)));
}

// List state $st put in with row $sel selected (mel_finish, mel_relist): the
// cursor as mel_gc_cursor, and its first view ignores the shell's sel_state.
/**
 * @param MelState $st
 * @param int $sel
 * @return MelState
 */
function mel_gc_put_cursor($st, $sel)
{
    $st['gc'] = mel_gc_cursor($sel, count($st['rows']));
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
function mel_gc_step($sel, $top, $n, $key)
{
    $d = array('up' => -1, 'down' => 1, 'pgup' => -MEL_GC_VIS, 'pgdn' => MEL_GC_VIS);
    $max_top = max(0, $n - MEL_GC_VIS);
    $nsel = max(0, min($n - 1, $sel + $d[$key]));
    $ntop = abs($d[$key]) > 1 ? max(0, min($max_top, $top + $d[$key])) : $top;
    if ($nsel < $ntop)
        $ntop = $nsel;
    else if ($nsel >= $ntop + MEL_GC_VIS)
        $ntop = $nsel - MEL_GC_VIS + 1;
    return array($nsel, $ntop);
}

// The key of a move of input $in (its 'd' of mel_gc_act: up, down, pgup, pgdn), null another.
/**
 * @param stdClass $in
 * @return 'up'|'down'|'pgup'|'pgdn'|null
 */
function mel_gc_key($in)
{
    $key = isset($in->d) && is_string($in->d) ? $in->d : '';
    return in_array($key, array('up', 'down', 'pgup', 'pgdn'), true) ? $key : null;
}

// Changes of a list of $n rows whose cursor went to row $nsel, first row on screen from $top to
// $ntop: the highlight, the rows and the scroll thumb when the page moved.
/**
 * @param int $top
 * @param int $nsel
 * @param int $ntop
 * @param int $n
 * @return list<MelGcChange>
 */
function mel_gc_cursor_changes($top, $nsel, $ntop, $n)
{
    $ch = array(mel_gc_change('sel', mel_gc_row_geom($nsel), GCOMP_TRANSITION_DEFAULT));
    if ($ntop !== $top)
    {
        $ch[] = mel_gc_change('rows', mel_gc_rows_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT);
        $ch[] = mel_gc_change('thumb', mel_gc_thumb_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT);
    }
    return $ch;
}

// --- Row data.

// Language code of mel_lang_code -> flag of the base skin (gui_skin://flags/XX.png,
// checked against the r24 skin). Languages of several countries take the main
// one; codes without a flag (MULTI, LAT, Indian languages but Hindi) stay text.
/**
 * @return array<string, string>
 */
function mel_gc_flags()
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
function mel_gc_icon($cached)
{
    return 'plugin_file://img/' . (is_null($cached) ? 'unknown.png' :
        ($cached ? 'cached.png' : 'download.png'));
}

// Seeders the list shows in place of the icon: those of a P2P row (no URL;
// its cache status means nothing), else null.
/**
 * @param MelRow $r
 * @return int|null
 */
function mel_gc_seeds($r)
{
    return mel_is_p2p($r) ? $r['seeders'] : null;
}

/**
 * @param int $n
 * @return string
 */
function mel_gc_seeds_str($n)
{
    return $n > MEL_GC_SEED_MAX ? MEL_GC_SEED_MAX . '+' : strval($n);
}

// Colour of the seeders by bins; the row in focus brighter, as its texts.
/**
 * @param int $n
 * @param bool $on
 * @return string
 */
function mel_gc_seed_color($n, $on)
{
    if ($n < MEL_GC_SEED_LOW)
        return $on ? MEL_GC_TEXT2 : MEL_GC_DIM;
    if ($n < MEL_GC_SEED_MID)
        return $on ? MEL_GC_TEXT : MEL_GC_TEXT2;
    return $n < MEL_GC_SEED_HIGH ? MEL_GC_TEXT : MEL_GC_GREEN;
}

// Languages of a row: those with a flag first, then the rest; the service
// codes only if there is no real language.
/**
 * @param list<string> $langs
 * @return list<string>
 */
function mel_gc_tail_langs($langs)
{
    $flags = mel_gc_flags();
    $service = array('MULTI', 'DUAL', 'DUB', 'ORIG');
    $out = array('flag' => array(), 'text' => array(), 'service' => array());
    foreach ($langs as $c)
        $out[isset($flags[$c]) ? 'flag' : (in_array($c, $service, true) ? 'service' : 'text')][] = $c;
    return $out['flag'] || $out['text'] ? array_merge($out['flag'], $out['text']) : $out['service'];
}

/**
 * @param MelState $st
 * @return 'ru'|'en'
 */
function mel_gc_lang($st)
{
    return $st['lang'] === 'ru' ? 'ru' : 'en';
}

// array(title with year, "S5 E3" of an episode (mel_ep_code), else ''). The number of
// releases is in the counter under the list.
/**
 * @param MelState $st
 * @return array{string, string}
 */
function mel_gc_title($st)
{
    $mv = $st['movie'];
    $title = $mv['title'] . (preg_match('/^[0-9]{4}$/', $mv['year']) ? " ({$mv['year']})" : '');
    return array($title, $st['e'] > 0 ? mel_ep_code($mv, $st['s'], $st['e']) : '');
}

// Children of the row of the title of list $st (mel_gc_window, mel_lpv_window): the title (mel_gc_title) at the left, whole
// when it fits; "S5 E3" of an episode at the right; between them yellow line $note ('' none: the releases, mel_lpv_note;
// the online list, its note), 26 px, in the room the title leaves, 300 px at least (the title cut then).
/**
 * @param MelState $st
 * @param string $note
 * @return list<MelGcComp>
 */
function mel_gc_title_row($st, $note)
{
    list($title, $ep) = mel_gc_title($st);
    $right = MEL_GC_LX + MEL_GC_LW - 16;
    $ew = $ep === '' ? 0 : mel_gc_w($ep, 36);
    $nr = $right - ($ew > 0 ? $ew + 30 : 0);
    $nt = $note === '' ? '' : mel_gc_cut_w($note, 26, max(300, $nr - MEL_GC_LX - mel_gc_w($title, 48) - 30));
    $nw = $nt === '' ? 0 : mel_gc_w($nt, 26);
    $d = array(mel_gc_cut(null, mel_gc_at($nr - MEL_GC_LX - ($nw > 0 ? $nw + 30 : 0), 66, MEL_GC_LX, 40), $title, 48,
        MEL_GC_TEXT));
    if ($nt !== '')
        $d[] = mel_gc_cut(null, mel_gc_at($nw, 36, $nr - $nw, 58), $nt, 26, MEL_GC_AMBER);
    if ($ep !== '')
        $d[] = mel_gc_cut(null, mel_gc_at($ew, 50, $right - $ew, 52), $ep, 36, MEL_GC_TEXT2,
            array(GCompTtfLabelDef::halign => HALIGN_RIGHT));
    return $d;
}

// The word <$key>_one/_few/_many for $n: English 1 / else, Russian by the
// last digit (1, 2-4, else; 11-14 as 5).
/**
 * @param string $lang
 * @param int $n
 * @param string $key
 * @return string
 */
function mel_gc_plural($lang, $n, $key)
{
    if ($lang === 'en')
        $form = $n === 1 ? 'one' : 'many';
    else
    {
        $m = $n % 100 >= 11 && $n % 100 <= 14 ? 5 : $n % 10;
        $form = $m === 1 ? 'one' : ($m >= 2 && $m <= 4 ? 'few' : 'many');
    }
    return mel_tr($lang, "{$key}_$form");
}

// Languages of a row: up to MEL_GC_LANGS_MAX flags of the skin, a code
// without a flag as text, the rest as "+N"; service codes (MULTI, DUAL, ...)
// only without a real language. Only what fits $width with the "+N" after
// it. -> array(flag|text, value, width).
/**
 * @param list<string> $langs
 * @param int $width
 * @return list<array{'flag'|'text', string, int}>
 */
function mel_gc_lang_items($langs, $width)
{
    $flags = mel_gc_flags();
    $tail = mel_gc_tail_langs($langs);
    $items = array();
    $x = 0;
    foreach (array_slice($tail, 0, MEL_GC_LANGS_MAX) as $i => $c)
    {
        $it = isset($flags[$c]) ? array('flag', $flags[$c], 36) : array('text', $c, mel_gc_w($c, 24));
        $rest = count($tail) - $i - 1;
        if ($x + $it[2] + ($rest > 0 ? 6 + mel_gc_w("+$rest", 24) : 0) > $width)
            break;
        $items[] = $it;
        $x += $it[2] + 6;
    }
    $rest = count($tail) - count($items);
    if ($rest > 0)
        $items[] = array('text', "+$rest", mel_gc_w("+$rest", 24));
    return $items;
}

/**
 * @param list<array{'flag'|'text', string, int}> $items
 * @return int
 */
function mel_gc_items_w($items)
{
    $w = 0;
    foreach ($items as $it)
        $w += $it[2];
    return $items ? $w + 6 * (count($items) - 1) : 0;
}

// "rutracker, bitru +2" if that fits $width, else "rutracker +3"
// with the name cut; the "+N" stays.
/**
 * @param MelRow $r
 * @param int $width
 * @return string
 */
function mel_gc_tracker($r, $width)
{
    $n = count($r['trackers']);
    if (!$n)
        return '';
    if ($n > 1)
    {
        $two = mel_clean($r['trackers'][0]) . ', ' . mel_clean($r['trackers'][1]) . ($n > 2 ? ' +' . ($n - 2) : '');
        if (mel_gc_w($two, 24) <= $width)
            return $two;
    }
    $more = $n > 1 ? ' +' . ($n - 1) : '';
    return mel_gc_cut_w(mel_clean($r['trackers'][0]), 24, $width - mel_gc_w($more, 24)) . $more;
}

// Badges of a row by column: resolution, source, picture, audio; AI only
// in the card. Only ids with a picture (mel_badges knows no others).
/**
 * @param MelBadges $b
 * @return array{res: list<string>, src: list<string>, vis: list<string>, audio: list<string>}
 */
function mel_gc_row_badges($b)
{
    $cols = array('res' => array($b['res']), 'src' => array($b['src']),
        'vis' => array($b['vis']), 'audio' => $b['audio']);
    foreach ($cols as $k => $ids)
        $cols[$k] = array_values(array_intersect($ids, array_keys(MelGc::$badge_w)));
    return $cols;
}

// Width of badges side by side, gaps between.
/**
 * @param list<string> $ids
 * @return int
 */
function mel_gc_plates_w($ids)
{
    $w = 0;
    foreach ($ids as $id)
        $w += MelGc::$badge_w[$id];
    return $ids ? $w + MEL_GC_BADGE_GAP * (count($ids) - 1) : 0;
}

// Column x and width for this list, each by its widest value; empty columns
// take no room; the icon column MEL_GC_ICON px, wider only for wider
// seeders. The badges and the size keep their width; the languages get what
// is left, the trackers (or the expression name, or the add-on) the rest if it
// is at least MEL_GC_TRACKER_MIN.
/**
 * @param list<MelRow> $rows
 * @param MelGcText $tt
 * @return MelGcCols
 */
function mel_gc_cols($rows, $tt)
{
    $max = array('res' => 0, 'src' => 0, 'vis' => 0, 'audio' => 0, 'size' => 0);
    $langs = 0;
    $any_tracker = false;
    $icon = MEL_GC_ICON;
    foreach ($rows as $r)
    {
        $n = mel_gc_seeds($r);
        if (!is_null($n))
            $icon = max($icon, mel_gc_w(mel_gc_seeds_str($n), MEL_GC_SEED_PX));
        foreach (mel_gc_row_badges($r['badges']) as $k => $ids)
            $max[$k] = max($max[$k], mel_gc_plates_w($ids));
        $max['size'] = max($max['size'], $r['size'] > 0 ?
            min(180, mel_gc_w(mel_size_str($r['size'], $tt['lang']), 28)) : 0);
        $langs = max($langs, min(260, mel_gc_items_w(mel_gc_lang_items($r['langs'], PHP_INT_MAX))));
        $any_tracker = $any_tracker || $r['expr'] !== '' || $r['trackers'] || $r['addon'] !== '';
    }
    $cols = array('icon' => array(12, $icon));
    $x = 12 + $icon + 14;
    foreach ($max as $k => $w)
    {
        if ($w > 0)
        {
            $cols[$k] = array($x, $w);
            $x += $w + MEL_GC_GAP;
        }
    }
    $end = MEL_GC_LW - MEL_GC_PAD;
    // One flag at least, else no languages.
    if ($langs > 0 && $end - $x >= 36)
    {
        $cols['langs'] = array($x, min($langs, $end - $x));
        $x += $cols['langs'][1] + MEL_GC_GAP;
    }
    if ($any_tracker && $end - $x >= MEL_GC_TRACKER_MIN)
        $cols['tracker'] = array($x, $end - $x);
    return $cols;
}

// --- Screen parts.

/**
 * @param int $i
 * @return MelGcGeom
 */
function mel_gc_row_geom($i)
{
    return mel_gc_at(MEL_GC_LW, MEL_GC_ROW_H, 0, $i * MEL_GC_ROW_H);
}

/**
 * @param int $top
 * @param int $n
 * @return MelGcGeom
 */
function mel_gc_rows_geom($top, $n)
{
    return mel_gc_at(MEL_GC_LW, $n * MEL_GC_ROW_H, 0, -$top * MEL_GC_ROW_H);
}

// Scrollbar thumb: height by the visible share, y by the top row.
/**
 * @param int $top
 * @param int $n
 * @return MelGcGeom
 */
function mel_gc_thumb_geom($top, $n)
{
    $track = MEL_GC_VIS * MEL_GC_ROW_H;
    $h = max(40, intval($track * MEL_GC_VIS / $n));
    $y = intval(round(($track - $h) * $top / ($n - MEL_GC_VIS)));
    return mel_gc_at(6, $h, MEL_GC_LX + MEL_GC_LW + 10, MEL_GC_LY + $y);
}

// Badge pictures (the plate is in the PNG) from x, centred in the row.
/**
 * @param list<string> $ids
 * @param int $x
 * @return list<MelGcComp>
 */
function mel_gc_plates($ids, $x)
{
    $d = array();
    foreach ($ids as $id)
    {
        $d[] = mel_gc_image(mel_gc_left_center(MelGc::$badge_w[$id], MEL_GC_BADGE_H, $x),
            'plugin_file://img/nb' . MEL_GC_BADGE_REV . "_$id.png");
        $x += MelGc::$badge_w[$id] + MEL_GC_BADGE_GAP;
    }
    return $d;
}

/**
 * @param bool|null $cached
 * @return string
 */
function mel_gc_status_color($cached)
{
    return is_null($cached) ? MEL_GC_DIM : ($cached ? MEL_GC_GREEN : MEL_GC_GREY);
}

// The status of the card: that of mel_gc_status; a P2P row with seeders adds
// them ("P2P · 79 сидов") in the colour of the number in the list row in focus.
// -> array(text, colour).
/**
 * @param MelRow $r
 * @param MelGcText $tt
 * @return array{string, string}
 */
function mel_gc_card_status($r, $tt)
{
    $status = mel_gc_status($r, $tt);
    $n = is_null($r['cached']) ? mel_gc_seeds($r) : null;
    if (is_null($n))
        return array($status, mel_gc_status_color($r['cached']));
    return array("$status · $n " . mel_gc_plural($tt['lang'], $n, 'gc_seeds'), mel_gc_seed_color($n, true));
}

// The bar of share $share (0 none) watched under a row of a list (mel_gc_row_items,
// mel_lpv_row_items).
/**
 * @param float|int $share
 * @return list<MelGcComp>
 */
function mel_gc_bar($share)
{
    if ($share <= 0)
        return array();
    $w = MEL_GC_LW - 2 * MEL_GC_PAD;
    $fw = max(MEL_GC_BAR_MIN, intval(round($w * min(1, $share))));
    $y = MEL_GC_ROW_H - MEL_GC_BAR_H;
    $d = array(mel_gc_rect(mel_gc_at($fw, MEL_GC_BAR_H, MEL_GC_PAD, $y), MEL_GC_BAR_DONE));
    if ($fw < $w)
        $d[] = mel_gc_rect(mel_gc_at($w - $fw, MEL_GC_BAR_H, MEL_GC_PAD + $fw, $y), MEL_GC_BAR_REST);
    return $d;
}

// Children of row panel "r$i". The row in focus is brighter, as in the
// shell's own lists: its texts TEXT, the add-on TEXT2; the others TEXT2, DIM.
// A release watched before ($share > 0): a progress bar at the row bottom.
/**
 * @param MelRow $r
 * @param MelGcText $tt
 * @param MelGcCols $c
 * @param bool $on
 * @param float|int $share
 * @return list<MelGcComp>
 */
function mel_gc_row_items($r, $tt, $c, $on, $share = 0)
{
    $hi = $on ? MEL_GC_TEXT : MEL_GC_TEXT2;
    $d = array();
    // The icon, or the seeders of a P2P row, centred in the icon column.
    $n = mel_gc_seeds($r);
    if (is_null($n))
        $d[] = mel_gc_image(mel_gc_left_center(MEL_GC_ICON, MEL_GC_ICON,
            $c['icon'][0] + intval(($c['icon'][1] - MEL_GC_ICON) / 2)),
            mel_gc_icon($r['cached']));
    else
        $d[] = mel_gc_cut(null, mel_gc_left_center($c['icon'][1], 44, $c['icon'][0]), mel_gc_seeds_str($n),
            MEL_GC_SEED_PX, mel_gc_seed_color($n, $on), array(GCompTtfLabelDef::halign => HALIGN_CENTER));
    foreach (mel_gc_row_badges($r['badges']) as $k => $ids)
    {
        if ($ids && isset($c[$k]))
            $d = array_merge($d, mel_gc_plates($ids, $c[$k][0]));
    }
    if ($r['size'] > 0 && isset($c['size']))
        $d[] = mel_gc_cut(null, mel_gc_left_center($c['size'][1], 44, $c['size'][0]),
            mel_size_str($r['size'], $tt['lang']), 28, $hi, array(GCompTtfLabelDef::halign => HALIGN_RIGHT));
    if (isset($c['langs']))
    {
        $x = $c['langs'][0];
        foreach (mel_gc_lang_items($r['langs'], $c['langs'][1]) as $it)
        {
            $d[] = $it[0] === 'flag' ?
                mel_gc_image(mel_gc_left_center(36, 36, $x), "gui_skin://flags/{$it[1]}.png") :
                mel_gc_cut(null, mel_gc_left_center($it[2], 40, $x), $it[1], 24, $hi);
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
            $txt = mel_gc_cut_w(mel_clean($r['expr']), 24, $cw);
        else if ($r['trackers'])
            $txt = mel_gc_tracker($r, $cw);
        else
            $txt = mel_gc_cut_w(mel_clean($r['addon']), 24, $cw);
        $d[] = mel_gc_cut(null, mel_gc_left_center($cw, 40, $c['tracker'][0]), $txt, 24,
            $r['expr'] !== '' || $r['trackers'] ? $hi : ($on ? MEL_GC_TEXT2 : MEL_GC_DIM));
    }
    return array_merge($d, mel_gc_bar($share));
}

/**
 * @param int $i
 * @param MelRow $r
 * @param MelGcText $tt
 * @param MelGcCols $c
 * @param bool $on
 * @param float|int $share
 * @return MelGcComp
 */
function mel_gc_row($i, $r, $tt, $c, $on = false, $share = 0)
{
    return mel_gc_panel("r$i", mel_gc_row_geom($i), mel_gc_row_items($r, $tt, $c, $on, $share));
}

// Share of the release of a row watched before (mel_wh_progress), or 0.
/**
 * @param MelProgress $prog
 * @param MelRow $r
 * @return float|int
 */
function mel_gc_share($prog, $r)
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
// fit above MEL_GC_CARD_BOTTOM is cut or left out from the end.
/**
 * @param MelState $st
 * @param int $sel
 * @return list<MelGcComp>
 */
function mel_gc_card($st, $sel)
{
    // An empty list (mel_gc_src): no card.
    if (!isset($st['rows'][$sel]))
        return array();
    $r = $st['rows'][$sel];
    $tt = mel_gc_text(mel_gc_lang($st));
    $w = MEL_GC_CW;
    $lh = 36;
    // Beside the poster; without a poster at the left edge.
    $sx = mel_img($st['movie']['poster']) !== '' ? MEL_GC_PW + 20 : 0;
    $sw = $w - $sx;
    $d = array();
    $y = 0;
    if ($r['size'] > 0)
    {
        $d[] = mel_gc_cut(null, mel_gc_at($sw, 60, $sx, $y), mel_size_str($r['size'], $tt['lang']), 48, MEL_GC_TEXT);
        $y += 64;
    }
    if ($r['rate'] > 0)
    {
        $d[] = mel_gc_cut(null, mel_gc_at($sw, $lh, $sx, $y), mel_gc_rate($r['rate'], $r['rate_approx'], $tt['lang']),
            30, MEL_GC_TEXT2);
        $y += $lh;
    }
    if ($r['pack'] > 0)
    {
        $d[] = mel_gc_cut(null, mel_gc_at($sw, $lh, $sx, $y),
            $tt['pack'] . ' ' . mel_size_str($r['pack'], $tt['lang']), 30, MEL_GC_TEXT2);
        $y += $lh;
    }
    $y += 8;
    list($status, $color) = mel_gc_card_status($r, $tt);
    // Lines of the status and of the release group together, so that the
    // worst case below holds: a file cached on several takes up to 3 ("В кэше" /
    // "Real-Debrid" / "и TorBox"), then the release 1; else both 2.
    $budget = 4;
    $sl = mel_gc_status_lines($status, 30, $sw, 3);
    foreach ($sl as $line)
    {
        $d[] = mel_gc_cut(null, mel_gc_at($sw, $lh, $sx, $y), $line, 30, $color);
        $y += $lh;
    }
    if ($r['group'] !== '')
    {
        $y += 16;
        $d[] = mel_gc_cut(null, mel_gc_at($sw, 32, $sx, $y), $tt['group'], 26, MEL_GC_DIM);
        $y += 32;
        foreach (mel_gc_wrap(mel_clean($r['group']), 30, $sw, min(2, $budget - count($sl))) as $line)
        {
            $d[] = mel_gc_cut(null, mel_gc_at($sw, $lh, $sx, $y), $line, 30, MEL_GC_TEXT2);
            $y += $lh;
        }
    }
    // The platform of a WEB-DL: one line, so that it fits beside the poster
    // in the worst case (status and release in $budget lines).
    if ($r['network'] !== '')
    {
        $y += 16;
        $d[] = mel_gc_cut(null, mel_gc_at($sw, 32, $sx, $y), $tt['source'], 26, MEL_GC_DIM);
        $y += 32;
        $d[] = mel_gc_cut(null, mel_gc_at($sw, $lh, $sx, $y), mel_gc_cut_w(mel_clean($r['network']), 30, $sw), 30,
            MEL_GC_TEXT2);
        $y += $lh;
    }
    // The add-on at the poster's bottom (its value ends at MEL_GC_PH), when it
    // fits under the fields above; else under the poster without trackers.
    $addon = mel_clean($r['addon']);
    $ay = MEL_GC_PH - $lh - 32;
    $addon_side = $addon !== '' && $y + 16 <= $ay;
    if ($addon_side)
    {
        $d[] = mel_gc_cut(null, mel_gc_at($sw, 32, $sx, $ay), $tt['addon'], 26, MEL_GC_DIM);
        $d[] = mel_gc_cut(null, mel_gc_at($sw, $lh, $sx, $ay + 32), mel_gc_cut_w($addon, 30, $sw), 30, MEL_GC_TEXT2);
    }

    // Under the poster's place even without a poster: the fields do not move.
    $y = MEL_GC_PH + 20;
    $room = MEL_GC_CARD_BOTTOM - MEL_GC_CY - $y;

    // The name: always a block of 2 lines at 26 px, cut with "…", so that the
    // fields below stay in place from one release to the next.
    $name = mel_clean($r['label']);
    if (mb_strlen($name, 'UTF-8') > MEL_GC_NAME_MAX)
        $name = rtrim(mb_substr($name, 0, MEL_GC_NAME_MAX, 'UTF-8')) . "\xE2\x80\xA6";
    $ny = $y;
    foreach (mel_gc_wrap($name, MEL_GC_NAME_PX, $w, MEL_GC_NAME_LINES) as $line)
    {
        $d[] = mel_gc_cut(null, mel_gc_at($w, MEL_GC_NAME_LH, 0, $ny), $line, MEL_GC_NAME_PX, MEL_GC_TEXT);
        $ny += MEL_GC_NAME_LH;
    }
    $y += MEL_GC_NAME_LINES * MEL_GC_NAME_LH + 14;
    $room -= MEL_GC_NAME_LINES * MEL_GC_NAME_LH + 14;

    // Fields by priority, each up to 2 lines while room is left, the voices
    // up to MEL_GC_VOICES_LINES.
    // "Дубляж: …" of the voices first, on lines of its own.
    $vs = mel_row_voices_struct($r);
    $b = $r['badges'];
    $video = array($r['resolution'], mel_badge_text($b['vis']), $b['ai'] ? 'AI' : '', $r['quality'],
        implode(' ', $b['tags']));
    foreach (array(
        array('video', implode(' · ', array_filter($video, 'strlen'))),
        array('audio', $r['audio_full']),
        // Subtitles have a field of their own.
        array('voices', mel_voices_struct_text($vs, $tt['lang'], false)),
        array('subs', mel_cut(implode(', ', $r['subs']), MEL_TEXT_MAX)),
        array('langs', mel_cut(implode(', ', $r['langs']), MEL_TEXT_MAX)),
        $r['trackers'] ? array('trackers', mel_cut(implode(', ', $r['trackers']), MEL_TEXT_MAX)) :
            array('addon', $addon_side ? '' : $r['addon'])) as $f)
    {
        $v = mel_clean($f[1]);
        $vlh = $f[0] === 'voices' ? MEL_GC_VOICES_LH : $lh;
        $px = $f[0] === 'voices' ? MEL_GC_VOICES_PX : 30;
        // The label (36 px) is the first line's height.
        $max = min($f[0] !== 'voices' ? 2 : MEL_GC_VOICES_LINES, intval(($room - 8 - $lh + $vlh) / $vlh));
        // Video and audio are always there (a dash); the others only with data.
        if ($max < 1 || ($v === '' && $f[0] !== 'video' && $f[0] !== 'audio'))
            continue;
        $v = $v !== '' ? $v : "\xE2\x80\x94";
        if ($f[0] === 'voices')
            $vl = mel_gc_wrap_voices($v, $px, $w - MEL_GC_VX, $max, mel_voices_dub_first($vs));
        else
            $vl = $f[0] === 'video' || $f[0] === 'audio' ?
                mel_gc_wrap_items($v, 30, $w - MEL_GC_VX, $max) : mel_gc_wrap($v, 30, $w - MEL_GC_VX, $max);
        $d[] = mel_gc_cut(null, mel_gc_at(MEL_GC_VX - 6, $lh, 0, $y), $tt[$f[0]], 26, MEL_GC_DIM);
        $y0 = $y;
        foreach ($vl as $j => $line)
        {
            // Smaller lines are centred in the first line of the label.
            $d[] = mel_gc_cut(null, mel_gc_at($w - MEL_GC_VX, $vlh, MEL_GC_VX, $y + ($j === 0 ? ($lh - $vlh) / 2 : 0)),
                $line, $px, MEL_GC_TEXT2);
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
function mel_gc_rate($bps, $approx, $lang)
{
    // By the rounded value: 9.96 -> "10", not "10.0".
    $m = $bps / 1e6;
    return ($approx ? "\xE2\x89\x88 " : '') . number_format($m, round($m, 1) < 10 ? 1 : 0, '.', '') . ' ' .
        mel_tr($lang, 'rate_mbps');
}

// Children of 'counter' (the hint row at the right, 300 x MEL_GC_KEY_H):
// "5 / 60", placed as a hint label.
/**
 * @param int $sel
 * @param int $n
 * @return list<MelGcComp>
 */
function mel_gc_counter($sel, $n)
{
    return array(mel_gc_cut(null, mel_gc_at(300, MEL_GC_LABEL_H, 0, (MEL_GC_KEY_H - MEL_GC_LABEL_H) / 2),
        min($sel + 1, $n) . " / $n", MEL_GC_HINT_PX, MEL_GC_TEXT2, array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// A hint at x of row y: key pictures $keys (url => width, $h high) side by
// side, then label $text, both centred in the row of MEL_GC_KEY_H; returns
// the x of the next hint.
/**
 * @param list<MelGcComp> $d
 * @param int $x
 * @param int $y
 * @param array<string, int> $keys
 * @param string $text
 * @param int $h
 * @return int
 */
function mel_gc_hint(&$d, $x, $y, $keys, $text, $h = MEL_GC_KEY_H)
{
    foreach ($keys as $url => $w)
    {
        $d[] = mel_gc_image(mel_gc_at($w, $h, $x, $y + intval((MEL_GC_KEY_H - $h) / 2)), $url);
        $x += $w;
    }
    $x += MEL_GC_KEY_GAP;
    $w = mel_gc_w($text, MEL_GC_HINT_PX);
    $d[] = mel_gc_cut(null, mel_gc_at($w, MEL_GC_LABEL_H, $x, $y + (MEL_GC_KEY_H - MEL_GC_LABEL_H) / 2), $text,
        MEL_GC_HINT_PX, MEL_GC_TEXT2);
    return $x + $w + MEL_GC_HINT_GAP;
}

// The remote has the colour keys, as the vendor screens check it
// (dune_config.php of the firmware; an old one has none: the keys are there).
// Without them no sort tabs, the list where it is (their row empty).
/**
 * @return bool
 */
function mel_gc_color_keys()
{
    return !function_exists('dune_config_with_color_buttons') || dune_config_with_color_buttons();
}

// The colour keys of the sort in the order of the remote: key of
// mel_sort_keys => array(event, the vendor key picture).
/**
 * @return array<string, array{string, string}>
 */
function mel_gc_sort_keys()
{
    $u = 'plugin_file://%shell_ext%/icons/';
    return array('aio' => array(GUI_EVENT_KEY_A_RED, $u . 'red.png'),
        'seeds' => array(GUI_EVENT_KEY_B_GREEN, $u . 'green.png'),
        'size' => array(GUI_EVENT_KEY_C_YELLOW, $u . 'yellow.png'),
        'date' => array(GUI_EVENT_KEY_D_BLUE, $u . 'blue.png'));
}

// The key of mel_gc_text of the label of sort $k (a key of mel_sort_keys) of list $st: "sort_cached" of the
// green one in a Debrid list (mel_rows_debrid), else "sort_<k>".
/**
 * @param MelState $st
 * @param string $k
 * @return string
 */
function mel_gc_sort_word($st, $k)
{
    return $k === 'seeds' && mel_rows_debrid($st['rows']) ? 'sort_cached' : "sort_$k";
}

// Children of 'sort' (the tabs over the list): each colour key and its label,
// the green one "Cached" in a Debrid list (mel_rows_debrid); the sort of the
// list with a bright label, its direction (a triangle: down by default, up
// reversed) and a line under the key to the triangle; the others dimmed. $end: their width,
// the gap after the last one included.
/**
 * @param MelState $st
 * @param MelGcText $tt
 * @param int|null $end
 * @param-out int $end
 * @return list<MelGcComp>
 */
function mel_gc_sort_tabs($st, $tt, &$end = null)
{
    $d = array();
    $x = 0;
    foreach (mel_gc_sort_keys() as $k => $key)
    {
        $text = $tt[mel_gc_sort_word($st, $k)];
        $on = $k === $st['sort'];
        $x0 = $x;
        $d[] = mel_gc_image(mel_gc_at(MEL_GC_KEY_W, MEL_GC_KEY_H, $x, 0), $key[1]);
        $x += MEL_GC_KEY_W + MEL_GC_KEY_GAP;
        $w = mel_gc_w($text, MEL_GC_HINT_PX);
        $d[] = mel_gc_cut(null, mel_gc_at($w, MEL_GC_LABEL_H, $x, (MEL_GC_KEY_H - MEL_GC_LABEL_H) / 2), $text,
            MEL_GC_HINT_PX, $on ? MEL_GC_TEXT : MEL_GC_TEXT2);
        $x += $w + MEL_GC_SORT_ARROW_GAP;
        if ($on)
        {
            $d[] = mel_gc_image(mel_gc_at(MEL_GC_SORT_ARROW_W, MEL_GC_SORT_ARROW_H, $x,
                intval((MEL_GC_KEY_H - MEL_GC_SORT_ARROW_H) / 2)),
                'plugin_file://img/' . ($st['sort_rev'] ? 'sort_up.png' : 'sort_down.png'));
            $d[] = mel_gc_rect(mel_gc_at($x + MEL_GC_SORT_ARROW_W - $x0, MEL_GC_TAB_LINE, $x0, MEL_GC_TAB_LINE_Y),
                MEL_GC_TEXT);
        }
        $x += MEL_GC_SORT_ARROW_W + MEL_GC_HINT_GAP;
    }
    $end = $x;
    return $d;
}

/**
 * @param MelState $st
 * @param int $sel
 * @param int $top
 * @return array<string, mixed>
 */
function mel_gc_window($st, $sel, $top)
{
    $mv = $st['movie'];
    $rows = $st['rows'];
    $n = count($rows);
    $tt = mel_gc_text(mel_gc_lang($st));
    $cols = mel_gc_cols($rows, $tt);
    $d = array();
    $d[] = mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), '#C0000000');
    $poster = mel_img($mv['poster']);
    if ($poster !== '')
        $d[] = mel_gc_image(mel_gc_at(MEL_GC_PW, MEL_GC_PH, MEL_GC_CX, MEL_GC_CY), $poster, true);
    $d[] = mel_gc_panel('card', mel_gc_at(MEL_GC_CW, MEL_GC_CARD_BOTTOM - MEL_GC_CY, MEL_GC_CX, MEL_GC_CY),
        mel_gc_card($st, $sel));

    // The yellow line of "Онлайн" (view_online.php) in the row of the title.
    $d = array_merge($d, mel_gc_title_row($st, mel_lpv_note($st)));

    // Viewport: clipping panel 'list'; the inner panel 'rows' moves to scroll.
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $in = array(mel_gc_rect(mel_gc_row_geom($sel), MEL_GC_SEL, 'sel'));
    foreach ($rows as $i => $r)
        $in[] = mel_gc_row($i, $r, $tt, $cols, $i === $sel, mel_gc_share($prog, $r));
    // An empty list (the source of a profile has no streams, mel_gc_src): the line
    // of the error in place of the rows.
    if ($n === 0)
        $d[] = mel_gc_cut(null, mel_gc_at(MEL_GC_LW - 2 * MEL_GC_PAD, MEL_GC_ROW_H, MEL_GC_LX + MEL_GC_PAD, MEL_GC_LY),
            mel_tr($tt['lang'], 'err_no_streams'), 36, MEL_GC_TEXT2);
    else
        $d[] = mel_gc_panel('list', mel_gc_at(MEL_GC_LW, MEL_GC_VIS * MEL_GC_ROW_H, MEL_GC_LX, MEL_GC_LY),
            array(mel_gc_panel('rows', mel_gc_rows_geom($top, $n), $in, 0)), 0);
    $hx = MEL_GC_LX;
    if ($n > MEL_GC_VIS)
    {
        $d[] = mel_gc_rect(mel_gc_at(6, MEL_GC_VIS * MEL_GC_ROW_H, MEL_GC_LX + MEL_GC_LW + 10, MEL_GC_LY),
            '#30FFFFFF');
        $d[] = mel_gc_rect(mel_gc_thumb_geom($top, $n), '#C0FFFFFF', 'thumb');
        // The vendor hint: the P+/P- key and its label (action_scroll of
        // shell_ext), as wide as the text: clear of the counter at the right.
        $hx = mel_gc_hint($d, $hx, MEL_GC_HINT_Y, array('plugin_file://%shell_ext%/icons/p_plus_p_minus.png' => 114),
            $tt['page']);
    }
    // An episode: the left and right keys go to the episode before and after.
    if ($st['e'] > 0)
        $hx = mel_gc_hint($d, $hx, MEL_GC_HINT_Y, array('plugin_file://img/left_arrow.png' => MEL_GC_ARROW_W,
            'plugin_file://%shell_ext%/icons/right_arrow.png' => MEL_GC_ARROW_W), $tt['flip'], MEL_GC_ARROW_H);
    // The keys of mel_gc_folder_view: POP UP (the "≡" key of the remote, as
    // the vendor popup_icon_only.png) -> mel_gc_menu, INFO ("i") -> the details.
    $hx = mel_gc_hint($d, $hx, MEL_GC_HINT_Y,
        array('plugin_file://%shell_ext%/icons/popup_icon_only.png' => 52), $tt['actions']);
    mel_gc_hint($d, $hx, MEL_GC_HINT_Y, array('plugin_file://img/info_key.png' => 52), $tt['details']);
    $d[] = mel_gc_panel('counter', mel_gc_at(300, MEL_GC_KEY_H, MEL_GC_LX + MEL_GC_LW - 16 - 300, MEL_GC_HINT_Y),
        mel_gc_counter($sel, $n));
    $tabs_end = 0;
    if (mel_gc_color_keys())
        $d[] = mel_gc_panel('sort', mel_gc_at(MEL_GC_LW, MEL_GC_TAB_LINE_Y + MEL_GC_TAB_LINE, MEL_GC_LX, MEL_GC_TABS_Y),
            mel_gc_sort_tabs($st, $tt, $tabs_end));
    // Outside the panel: a remote without the colour keys has it too. Left of it the name of the source
    // in the room the tabs leave (sources.php).
    $right = MEL_GC_LX + MEL_GC_LW - 16;
    if (mel_errors_shown($st['errors']))
    {
        $text = str_replace('{n}', strval(mel_errors_total($st['errors'])), $tt['errors']);
        $w = mel_gc_w($text, MEL_GC_HINT_PX);
        $right -= $w + MEL_GC_HINT_GAP;
        $d[] = mel_gc_cut(null, mel_gc_at($w, MEL_GC_LABEL_H, MEL_GC_LX + MEL_GC_LW - 16 - $w,
            MEL_GC_TABS_Y + (MEL_GC_KEY_H - MEL_GC_LABEL_H) / 2), $text, MEL_GC_HINT_PX, MEL_GC_AMBER,
            array(GCompTtfLabelDef::halign => HALIGN_RIGHT));
    }
    list($from) = mel_src_tabs_right($st, $right, MEL_GC_TABS_Y, $right - MEL_GC_LX - $tabs_end);
    $d = array_merge($d, $from);

    return mel_gc_window_def($d, $mv['fanart']);
}

// The window of children $d over fanart $fanart of a card (mel_img; none: black) of a list
// screen (mel_gc_window, mel_lpv_window).
/**
 * @param list<MelGcComp> $d
 * @param string $fanart
 * @return array<string, mixed>
 */
function mel_gc_window_def($d, $fanart)
{
    $fanart = mel_img($fanart);
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

// change_gcomps of changes $ch of a list screen, its cursor then row $sel, $top first on screen.
/**
 * @param list<MelGcChange> $ch
 * @param int $sel
 * @param int $top
 * @return MelAction
 */
function mel_gc_changes_act($ch, $sel, $top)
{
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => $ch,
            ChangeGCompsActionData::num_steps => MEL_GC_STEPS,
            ChangeGCompsActionData::sel_state => mel_gc_state($sel, $top)));
}

// --- Entry points from main.php.

// Back from the player to the kept screen of list $st (mel_finish): the
// progress read anew (expected: the shell writes the history before this
// event - one shell log, unverified); the rows of release $hash (one hash
// may come in several rows: RD and TorBox) redrawn when its share changed,
// else null.
/**
 * @param MelState $st
 * @param string $hash
 * @return MelAction|null
 */
function mel_gc_bar_update($st, $hash)
{
    $old = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $prog = mel_wh_progress($st);
    Mel::$state['gc_prog'] = $prog;
    $rows = array();
    foreach ($st['rows'] as $k => $r)
    {
        if ($hash !== '' && $r['hash'] === $hash)
            $rows[] = $k;
    }
    if (!$rows)
    {
        mel_log('finish: progress: the release played has no infoHash or is not in the list');
        return null;
    }
    $was = isset($old[$hash]) ? $old[$hash] : 0;
    $now = isset($prog[$hash]) ? $prog[$hash] : 0;
    mel_log(sprintf('finish: progress %.1f%% -> %.1f%%, rows %s', $was * 100, $now * 100, implode(', ', $rows)));
    if ($was == $now)
        return null;
    list($sel, $top) = mel_gc_pos($st, null);
    $tt = mel_gc_text(mel_gc_lang($st));
    $cols = mel_gc_cols($st['rows'], $tt);
    $ch = array();
    foreach ($rows as $i)
        $ch[] = mel_gc_change("r$i", null, GCOMP_TRANSITION_NONE,
            mel_gc_row_items($st['rows'][$i], $tt, $cols, $i === $sel, $now));
    return mel_gc_changes_act($ch, $sel, $top);
}

// get_folder_view of a live list ($st matches the media_url).
/**
 * @param MelState $st
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_gc_folder_view($st, $sel_state)
{
    // First view of a list put in by mel_finish or mel_flip: its row, not a sel_state
    // the shell may carry over from the replaced screen.
    list($sel, $top) = mel_gc_pos($st, isset($st['gc_new']) ? null : $sel_state);
    unset(Mel::$state['gc_new']);
    Mel::$state['gc'] = array($sel, $top);
    // Progress read anew with every full screen (back from the player too);
    // kept for the rows that gc_move redraws.
    $st['gc_prog'] = Mel::$state['gc_prog'] = mel_wh_progress($st);
    $rid = $st['rid'];
    $acts = array(
        GUI_EVENT_KEY_UP => mel_gc_act('gc_move', $rid, array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => mel_gc_act('gc_move', $rid, array('d' => 'down')),
        GUI_EVENT_KEY_P_PLUS => mel_gc_act('gc_move', $rid, array('d' => 'pgup')),
        GUI_EVENT_KEY_P_MINUS => mel_gc_act('gc_move', $rid, array('d' => 'pgdn')),
        GUI_EVENT_KEY_ENTER => mel_gc_act('gc_pick', $rid),
        GUI_EVENT_KEY_INFO => mel_gc_act('gc_info', $rid),
        GUI_EVENT_MENU_PLAYBACK_FINISH => mel_finish_act($rid),
        // MENU: built for the row under the cursor (mel_gc_menu).
        GUI_EVENT_KEY_POPUP_MENU => mel_gc_act('gc_menu', $rid));
    // An episode: left and right flip episodes (mel_flip).
    if ($st['e'] > 0)
    {
        $acts[GUI_EVENT_KEY_LEFT] = mel_gc_act('gc_flip', $rid, array('d' => 'prev'));
        $acts[GUI_EVENT_KEY_RIGHT] = mel_gc_act('gc_flip', $rid, array('d' => 'next'));
    }
    // The colour keys: the sort (mel_gc_sort).
    foreach (mel_gc_sort_keys() as $k => $key)
        $acts[$key[0]] = mel_gc_act('gc_sort', $rid, array('k' => $k));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_gc_window($st, $sel, $top),
            PluginGCompsFolderView::sel_state => mel_gc_state($sel, $top),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => null));
}

// A sort for the log: "size", "size reversed".
/**
 * @param string $k
 * @param bool $rev
 * @return string
 */
function mel_sort_tag($k, $rev)
{
    return $k . ($rev ? ' reversed' : '');
}

// gc_sort (k: a key of mel_sort_keys) of a live list: its rows in that
// order, by default descending (mel_rows_sort); the key of the list's sort
// again: the other way round. The sort kept for new lists (mel_sort_set;
// not written: this list only); the screen replaced as by mel_refresh (a new
// rid), the cursor on the first row. A bad key, a list not live: nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_sort($in)
{
    $st = Mel::$state;
    $k = isset($in->k) && is_string($in->k) ? $in->k : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !in_array($k, mel_sort_keys(), true))
    {
        mel_log('sort: not a live list or a bad key, nothing');
        return null;
    }
    $rev = $k === $st['sort'] && !$st['sort_rev'];
    $tag = mel_sort_tag($k, $rev);
    // mel_list_state sorts by the settings: the new sort, saved or not.
    if (!mel_sort_set($k, $rev))
        mel_log("sort: $tag not saved, this list only");
    $ns = mel_list_state($st['movie'], $st['s'], $st['e'], $st['rows'], $st['lang'], $st['errors']);
    mel_log('sort: ' . mel_sort_tag($st['sort'], $st['sort_rev']) . " -> $tag, " . mel_ep_tag($st) . ', ' .
        count($st['rows']) . ' rows, screen replaced');
    return mel_list_replace(mel_gc_put_cursor($ns, 0));
}

// The sort and the details of a release in MENU of a list too (0.61.0): "Пульт без цветных кнопок" of the
// settings, or a remote the firmware knows is without the colour keys (no sort tabs then).
/**
 * @return bool
 */
function mel_gc_keys_in_menu()
{
    return Mel::$settings['no_color_keys'] || !mel_gc_color_keys();
}

// gc_sort_menu (MENU "Сортировка: <sort>", mel_gc_menu) of a live list -> the popup menu of the sorts in
// the order of the colour keys, each gc_sort as its key (the sort of the list again: the other way round);
// the sort of the list "<label> · сейчас" (reversed: "…, в обратном порядке") and selected.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_gc_sort_menu($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('sort: menu of no live list');
        return mel_error('err_list_expired');
    }
    $tt = mel_gc_text(mel_gc_lang($st));
    $items = array();
    $sel = 0;
    foreach (mel_sort_keys() as $k)
    {
        $text = $tt[mel_gc_sort_word($st, $k)];
        if ($k === $st['sort'])
        {
            $sel = count($items);
            $text = str_replace('{name}', $text, mel_tr($st['lang'], $st['sort_rev'] ? 'sort_now_rev' : 'sort_now'));
        }
        $items[] = array(
            GuiMenuItemDef::caption => $text,
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/playlist_file.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_sort', $st['rid'], array('k' => $k)));
    }
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => $sel));
}

// The main Debrid of list $st for its MENU (0.56.0): array(now, the other one)
// of mel_debrid_mains, by a row with copies of both; null - no such row,
// the choice changes nothing there. Now: of the settings, else the service first
// in the reply of such a row (it plays).
/**
 * @param MelState $st
 * @return array{string, string}|null
 */
function mel_gc_main_pair($st)
{
    $pair = array_keys(mel_debrid_mains());
    foreach ($st['rows'] as $r)
    {
        $svcs = array();
        foreach (isset($r['copies']) ? $r['copies'] : array() as $c)
        {
            if (in_array($c['svc'], $pair, true))
                $svcs[] = $c['svc'];
        }
        if (count(array_unique($svcs)) < 2)
            continue;
        $now = Mel::$settings['debrid_main'] !== '' ? Mel::$settings['debrid_main'] : $svcs[0];
        return array($now, $now === $pair[0] ? $pair[1] : $pair[0]);
    }
    return null;
}

// gc_main (k: an id of mel_debrid_mains, MENU "Main Debrid -> <k>") of a live
// list: k the main Debrid at once (mel_debrid_main_write), the copies of k on
// top, the screen replaced (a new rid), the cursor kept. Not written (the next
// operation reads the file again: the old one would play), a bad id, a list
// not live: nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_main($in)
{
    $st = Mel::$state;
    $k = isset($in->k) && is_string($in->k) ? $in->k : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || !array_key_exists($k, mel_debrid_mains()))
    {
        mel_log('debrid main: not a live list or a bad service, nothing');
        return null;
    }
    $was = Mel::$settings['debrid_main'] !== '' ? Mel::$settings['debrid_main'] : 'first in the reply';
    if (!mel_debrid_main_write($k))
    {
        mel_log("debrid main: $was -> $k not saved (a write error), nothing changed");
        return null;
    }
    Mel::$settings['debrid_main'] = $k;
    $ns = mel_list_state($st['movie'], $st['s'], $st['e'], $st['rows'], $st['lang'], $st['errors']);
    mel_log("debrid main: $was -> $k, " . mel_ep_tag($st) . ', screen replaced');
    return mel_list_replace(mel_gc_put_cursor($ns, isset($st['gc']) ? $st['gc'][0] : 0));
}

// --- The sources in MENU of a list (v2): the user saved them on the page (settings.json, profiles); a choice is of
// this screen only. Here the item only; its menu and the choice: sources.php.

// The MENU item "Откуда брать…" of live list $st (gc_src_menu: the menu of all ready profiles, any kind), or null: fewer
// than two ready (mel_src_in_menu).
/**
 * @param MelState $st
 * @return array<string, mixed>|null
 */
function mel_gc_src_item($st)
{
    if (!mel_src_in_menu())
        return null;
    return array(
        GuiMenuItemDef::caption => mel_tr($st['lang'], 'src_list'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
        GuiMenuItemDef::action => mel_gc_act('gc_src_menu', $st['rid']));
}

// gc_menu (MENU) of a live list -> the popup menu of the row under the cursor:
// with mel_gc_keys_in_menu "Release details" of the row (as INFO; none in an empty list) and "Sort: <sort>"
// (mel_gc_sort_menu) first; "Refresh" (mel_refresh), an episode: "Choose episode" (mel_choose), copies of
// Real-Debrid and TorBox in the list: "Main Debrid -> <the other one>" (mel_gc_main), the
// sources: "Get films from…" (mel_gc_src_item), a row
// with infoHash: "Download in app" (mel_download) and, with a server in the
// settings, "Download to server" (mel_srv_menu; these three not with "no torrents"), source errors of the
// reply if shown (mel_gc_errors), "Settings". A screen of a
// lost list (replaced, php_server restarted): "Settings" only.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_gc_menu($in)
{
    $st = Mel::$state;
    $setup = array(
        GuiMenuItemDef::caption => $st ? mel_tr($st['lang'], 'setup_menu') : '%tr%setup_menu',
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/setup.aai',
        GuiMenuItemDef::action => mel_setup_open_act());
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('menu: list outdated, settings only');
        return array(
            GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
            GuiAction::data => array(ShowPopupMenuActionData::menu_items => array($setup)));
    }
    list($sel, $top) = mel_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    Mel::$state['gc'] = array($sel, $top);
    $rid = $st['rid'];
    $menu = array();
    if (mel_gc_keys_in_menu())
    {
        if ($st['rows'])
            $menu[] = array(
                GuiMenuItemDef::caption => mel_tr($st['lang'], 'info_menu'),
                GuiMenuItemDef::icon_url => 'gui_skin://small_icons/info.aai',
                GuiMenuItemDef::action => mel_gc_act('gc_info', $rid, array('i' => strval($sel))));
        $tt = mel_gc_text(mel_gc_lang($st));
        $menu[] = array(
            GuiMenuItemDef::caption => str_replace('{name}', $tt[mel_gc_sort_word($st, $st['sort'])],
                mel_tr($st['lang'], 'sort_menu')),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/playlist_file.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_sort_menu', $rid));
    }
    $menu[] = array(
        GuiMenuItemDef::caption => mel_tr($st['lang'], 'refresh_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/upgrade.aai',
        GuiMenuItemDef::action => mel_gc_act('gc_refresh', $rid));
    // Without a card: the choice would end in "illegal state" of the shell.
    // From the own card (own_card): the seasons of the last native series would come; chosen there.
    if ($st['e'] > 0 && $st['movie']['dune_id'] !== '' && empty($st['movie']['own_card']))
        $menu[] = array(
            GuiMenuItemDef::caption => mel_tr($st['lang'], 'choose_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/playlist_file.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_choose', $rid));
    $main = mel_gc_main_pair($st);
    if ($main)
        $menu[] = array(
            GuiMenuItemDef::caption => str_replace('{svc}', mel_service_name($main[1]), mel_tr($st['lang'], 'main_menu')),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sync.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_main', $rid, array('k' => $main[1])));
    $src = mel_gc_src_item($st);
    if ($src)
        $menu[] = $src;
    $tor = isset($st['rows'][$sel]) && $st['rows'][$sel]['hash'] !== '' && !Mel::$settings['no_torrents'];
    if ($tor)
        $menu[] = array(
            GuiMenuItemDef::caption => mel_tr($st['lang'], 'ts_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/torrents.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_ts', $rid, array('i' => strval($sel))));
    if ($tor)
        $menu[] = array(
            GuiMenuItemDef::caption => mel_tr($st['lang'], 'dl_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/torrent_file.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_dl', $rid, array('i' => strval($sel))));
    if ($tor && Mel::$settings['servers'])
        $menu[] = array(
            GuiMenuItemDef::caption => mel_tr($st['lang'], 'srv_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/network_folder.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_srv', $rid, array('i' => strval($sel))));
    if (mel_errors_shown($st['errors']))
        $menu[] = array(
            GuiMenuItemDef::caption => mel_tr($st['lang'], 'errors_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/info.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_errors', $rid));
    $menu[] = $setup;
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => $menu));
}

// gc_errors (MENU "Source errors") of a live list -> the window of its reasons.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_gc_errors($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('source errors: not a live list');
        return mel_error('err_list_expired');
    }
    return mel_errors_dialog(mel_tr($st['lang'], 'errors_menu'), $st['lang'], $st['errors']);
}

// gc_move -> change_gcomps (null at an edge); gc_pick -> playback; gc_info ->
// the details screen (back on this one, mel_gc_pos finds the row in 'gc'); from MENU
// (mel_gc_menu) gc_info has the row in 'i', a row not in the list: outdated.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_input($in)
{
    $st = Mel::$state;
    $live = $st && isset($in->rid) && $in->rid === $st['rid'];
    $raw = isset($in->parent_sel_state) ? $in->parent_sel_state : null;
    if ($in->control_id === 'gc_pick' || $in->control_id === 'gc_info')
    {
        if (!$live)
            return mel_error('err_list_expired');
        // An empty list (mel_gc_src): no row to play or to show.
        if (!$st['rows'])
            return null;
        list($sel, $top) = mel_gc_pos($st, $raw);
        if ($in->control_id === 'gc_info' && isset($in->i))
        {
            $i = is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
            if (!isset($st['rows'][$i]))
            {
                mel_log('info: menu of no live row');
                return mel_error('err_list_expired');
            }
            if ($i !== $sel)
                list($sel, $top) = mel_gc_cursor($i, count($st['rows']));
        }
        Mel::$state['gc'] = array($sel, $top);
        if ($in->control_id === 'gc_info')
            return mel_info_open($st, $sel);
        // A new start: the main Debrid again, not one of "Via <service>" before.
        Mel::$alt = null;
        Mel::$via = null;
        // A P2P stream (no URL, infoHash only): through TorrServer.
        if (mel_is_p2p($st['rows'][$sel]))
            return mel_ts_open(Mel::$state, $sel, 0, $st['rid']);
        $a = mel_play($st, $st['rows'][$sel], 0, null, false, null, $st['rid']);
        if ($a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
            mel_playing($st['rid'], Mel::$state, $sel);
        return $a;
    }

    $key = mel_gc_key($in);
    if (!$live || $key === null)
        return null;
    $n = count($st['rows']);
    list($sel, $top) = mel_gc_pos($st, $raw);
    list($nsel, $ntop) = mel_gc_step($sel, $top, $n, $key);
    Mel::$state['gc'] = array($nsel, $ntop);
    if ($nsel === $sel)
        return null;

    $ch = mel_gc_cursor_changes($top, $nsel, $ntop, $n);
    // The focus brightness moves with the row.
    $tt = mel_gc_text(mel_gc_lang($st));
    $cols = mel_gc_cols($st['rows'], $tt);
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $ch[] = mel_gc_change("r$sel", null, GCOMP_TRANSITION_NONE,
        mel_gc_row_items($st['rows'][$sel], $tt, $cols, false, mel_gc_share($prog, $st['rows'][$sel])));
    $ch[] = mel_gc_change("r$nsel", null, GCOMP_TRANSITION_NONE,
        mel_gc_row_items($st['rows'][$nsel], $tt, $cols, true, mel_gc_share($prog, $st['rows'][$nsel])));
    $ch[] = mel_gc_change('card', null, GCOMP_TRANSITION_NONE, mel_gc_card($st, $nsel));
    $ch[] = mel_gc_change('counter', null, GCOMP_TRANSITION_NONE, mel_gc_counter($nsel, $n));
    return mel_gc_changes_act($ch, $nsel, $ntop);
}
