<?php
// Stream list as a view_gcomps screen, 1920x1080, on the components of the UI kit (kit.php, r5 of the owner):
// darkened fanart; on the left the poster, beside it the size, the bit rate and the cache status, under it a card
// of the selected release (the name in up to 3 lines, video, audio, voices, subtitles, languages, trackers); on the
// right the list: the cache status (the seeders of a P2P row), the plates b2 (resolution, source, picture, sound:
// the senior part, mel_badge_senior; each part a column), size, language flags, trackers if room is left, a bar of
// a release watched before; between the title and the list the sort tabs of the colour keys (a
// remote with them), the name of the source at the right; under the list the hint line (P+ P-, <- ->, MENU
// "Actions", INFO "Details") and "1 / 60" at the right. The plugin draws and moves the selection (the blue plate
// row_on) itself with change_gcomps; the position travels in sel_state {"sel":N,"top":T}. The old frame of the list
// that the online list (view_online.php) still draws stays there until its stage.
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
// The window of rows on the screen (mel_gc_win) moves by this many rows: a move of the window adds and removes as many.
define('MEL_GC_WIN_STEP', 6);
// The centres of the capitals of the title, of the sort tabs and of the hint line (r5).
define('MEL_GC_TITLE_MID', 73);
define('MEL_GC_TABS_MID', 150);
define('MEL_GC_HINT_MID', 1012);
define('MEL_GC_COUNT_W', 300);   // the counter "5 / 60" at the right of the hint line
define('MEL_GC_PAD', 16);      // row padding left and right
define('MEL_GC_GAP', 16);      // between columns
// "rutracker +3" with a margin; less room: no tracker column (the card has them).
define('MEL_GC_TRACKER_MIN', 145);
define('MEL_GC_FLAG', 36);         // a flag of the skin, square
define('MEL_GC_FLAG_GAP', 6);
define('MEL_GC_SUBS_GAP', 12);    // the last flag or "?" -> the plate of the subtitles mark
define('MEL_GC_VX', 156);      // card values after the labels ("Субтитры")
define('MEL_GC_SIDE_GAP', 26);  // the poster -> the column beside it (r5; between m 24 and l 32 of the rhythm)
define('MEL_GC_NAME_MAX', 400);    // characters of the name in the card, at most
// The steps of the lines of the card (of the code, not 37 of the mocks: with it "Трекеры" went, the owner
// 18.11.2026): a value and the status 36, the name and the voices 32.
define('MEL_GC_LH', 36);
define('MEL_GC_NAME_LH', 32);
define('MEL_GC_VOICES_LH', 32);
define('MEL_GC_NAME_LINES', 3);    // the name in the card: up to 3 lines by fact
define('MEL_GC_VOICES_LINES', 5);  // lines of the voices in the card, at most
define('MEL_GC_CARD_BOTTOM', 1040);   // the card ends above this y on any data
define('MEL_GC_LANGS_MAX', 3);     // language flags of a row, the rest as "+N"
define('MEL_GC_STEPS', 5);     // change_gcomps animation steps (vendor default)

define('MEL_GC_TEXT', '#FFFFFFE0');
define('MEL_GC_TEXT2', '#FFAFAFA0');
define('MEL_GC_DIM', '#FF808080');
define('MEL_GC_SEL', '#D02850C0');
// Seeders of a P2P row in place of its "?" icon (mel_gc_seed_color). Bins as
// the ranked expressions of the P2P template (cgi/aio_template_p2p.json):
// 0-2, 3-9, 10-29, 30+, a bin starts at LOW, MID, HIGH; the template also
// ranks a high bit rate with few seeders lower, the colour does not.
define('MEL_GC_SEED_LOW', 3);
define('MEL_GC_SEED_MID', 10);
define('MEL_GC_SEED_HIGH', 30);
define('MEL_GC_SEED_MAX', 999);    // more: "999+"
// Progress bar of a release watched before, at the bottom of the plate of the focus.
define('MEL_GC_BAR_H', 4);
define('MEL_GC_BAR_Y', 58);
define('MEL_GC_BAR_MIN', 4);    // the white part at least, even for a second watched (the own card)

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
// line of their own - mel_gc_status_k), "Нужно скачать", "P2P" (no URL:
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

// Status $s of mel_gc_status in lines of at most $w at $px (MelKit::w): each of its lines wrapped after spaces
// only ("Real-Debrid" whole), no line ends with a comma; more than $max: the last ends with "…".
/**
 * @param string $s
 * @param int $px
 * @param int $w
 * @param int $max
 * @return list<string>
 */
function mel_gc_status_k($s, $px, $w, $max)
{
    $lines = array();
    foreach (explode("\n", $s) as $part)
    {
        $cur = '';
        foreach (explode(' ', $part) as $word)
        {
            if ($word === '')
                continue;
            if ($cur !== '' && MelKit::w("$cur $word", $px) > $w)
            {
                $lines[] = $cur;
                $cur = '';
            }
            $cur = $cur === '' ? $word : "$cur $word";
        }
        if ($cur !== '')
            $lines[] = $cur;
    }
    $out = array();
    foreach (array_slice($lines, 0, $max) as $i => $l)
    {
        $l = rtrim($l, ' ,');
        $out[] = $i === $max - 1 && count($lines) > $max ? MelKit::ell($l, $px, '', $w) : MelKit::cut($l, $px, '', $w);
    }
    return $out;
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

// The rows of a list of $n rows on the screen with $top first visible: array(first, past the last). A step costs the
// shell by all the components of the screen, so not all the rows: the visible ones and at least a page (MEL_GC_VIS)
// above and below them, so that the animated scroll of a page (P+, P-) or of a row never shows a row missing; the
// window moves by MEL_GC_WIN_STEP rows, 41 rows in all. Of the top only: a sel_state has always the same screen.
/**
 * @param int $top
 * @param int $n
 * @return array{int, int}
 */
function mel_gc_win($top, $n)
{
    $b = $top - $top % MEL_GC_WIN_STEP;
    return array(max(0, $b - MEL_GC_VIS), min($n, $b + MEL_GC_WIN_STEP - 1 + 2 * MEL_GC_VIS));
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

// The icon of the cache status of row $r (Lucide, k_icon_*) and its colour: in the cache a lightning (zap) in
// green, to download an arrow (arrow-down-to-line) in grey, else (P2P too) a question mark (circle-help), dim, as
// the icons of the list before the kit. -> array(name, colour).
/**
 * @param MelRow $r
 * @return array{string, string}
 */
function mel_gc_status_icon($r)
{
    if ($r['cached'] === true)
        return array('k_icon_zap', MEL_K_C_OK);
    if ($r['cached'] === false)
        return array('k_icon_arrow-down-to-line', MEL_K_C_UNCACHED);
    return array('k_icon_circle-help', MEL_K_C_TEXT3);
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
        return $on ? MEL_K_C_TEXT2 : MEL_K_C_TEXT3;
    if ($n < MEL_GC_SEED_MID)
        return $on ? MEL_K_C_TEXT1 : MEL_K_C_TEXT2;
    return $n < MEL_GC_SEED_HIGH ? MEL_K_C_TEXT1 : MEL_K_C_OK;
}

// Languages of a row (confirmed ones of mel_row_langs, no service codes):
// those with a flag first, then the rest.
/**
 * @param list<string> $langs
 * @return list<string>
 */
function mel_gc_tail_langs($langs)
{
    $flags = mel_gc_flags();
    $out = array('flag' => array(), 'text' => array());
    foreach ($langs as $c)
        $out[isset($flags[$c]) ? 'flag' : 'text'][] = $c;
    return array_merge($out['flag'], $out['text']);
}

// The service codes of $langs (mel_lang_service), in their order.
/**
 * @param list<string> $langs
 * @return list<string>
 */
function mel_gc_service_langs($langs)
{
    return array_values(array_intersect($langs, array_keys(mel_lang_service())));
}

// The senior service code of $langs (MULTI > DUAL > DUB > ORIG), '' none: the one word of the language column.
/**
 * @param list<string> $langs
 * @return string
 */
function mel_gc_service_word($langs)
{
    foreach (array('MULTI', 'DUAL', 'DUB', 'ORIG') as $c)
    {
        if (in_array($c, $langs, true))
            return $c;
    }
    return '';
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

// The row of the title of list $st: the title (mel_gc_title, MEL_K_PX_PAGE_TITLE) at the left, whole when it fits;
// "S5 E3" of an episode at the right; between them line $note in amber ('' none: mel_lpv_note), in the room the
// title leaves, 300 px at least (the title cut then). All by the centre of their capitals on MEL_GC_TITLE_MID.
/**
 * @param MelState $st
 * @param string $note
 * @return list<MelGcComp>
 */
function mel_gc_head($st, $note)
{
    list($title, $ep) = mel_gc_title($st);
    $right = MEL_GC_LX + MEL_GC_LW - MEL_GC_PAD;
    $y = MEL_GC_TITLE_MID;
    $d = array();
    $nr = $right;
    if ($ep !== '')
    {
        $ew = MelKit::w($ep, MEL_K_PX_STATUS_TITLE);
        $e = MelKit::text($ep, MEL_K_PX_STATUS_TITLE, MEL_K_C_TEXT2, $right - $ew, $y);
        $d = $e['defs'];
        $nr -= $ew + MEL_K_GAP_L;
    }
    $nw = 0;
    if ($note !== '')
    {
        $room = max(300, $nr - MEL_GC_LX - MelKit::w($title, MEL_K_PX_PAGE_TITLE) - MEL_K_GAP_L);
        $nt = MelKit::cut($note, MEL_K_PX_SECONDARY, '', $room);
        $nw = MelKit::w($nt, MEL_K_PX_SECONDARY);
        $n = MelKit::text($nt, MEL_K_PX_SECONDARY, MEL_K_C_WARN, $nr - $nw, $y);
        $d = array_merge($d, $n['defs']);
    }
    $t = MelKit::text($title, MEL_K_PX_PAGE_TITLE, MEL_K_C_TEXT1, MEL_GC_LX, $y,
        array('w' => $nr - MEL_GC_LX - ($nw > 0 ? $nw + MEL_K_GAP_L : 0)));
    return array_merge($t['defs'], $d);
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

// Languages of row $r (variant B, 19.11.2026): its confirmed ones (mel_row_voice_langs 'ok') as up to
// MEL_GC_LANGS_MAX flags of the skin, a code without a flag as text (MEL_K_PX_CAPTION), the rest as "+N"; one "?"
// (k_lang_q, in the slot of a flag) for all the guessed ones ('q'); the plate of the subtitles mark
// (mel_row_subs_mark, k_badge_subs-*) MEL_GC_SUBS_GAP after them. Without a language confirmed or guessed the
// senior service code of 'langs' (mel_gc_service_word) as a small plate in place of "?" (k_lang_multi, ...; the
// owner 20.11.2026, variant B). What does not fit $width: the flags go into the "+N" first, the "?" (or the word)
// and the plate stay. -> array(flag|text|q|subs, value (flag, text, picture, badge id), width).
/**
 * @param MelRow $r
 * @param int $width
 * @return list<array{'flag'|'text'|'q'|'subs', string, int}>
 */
function mel_gc_lang_items($r, $width)
{
    $flags = mel_gc_flags();
    $vl = mel_row_voice_langs($r);
    $tail = mel_gc_tail_langs($vl['ok']);
    $q = $vl['q'] ? 'q' : ($vl['ok'] ? '' : strtolower(mel_gc_service_word($r['langs'])));
    $end = array();
    if ($q !== '')
    {
        $p = MelKit::pic("k_lang_$q");
        $end[] = array('q', "k_lang_$q", $p['w']);
    }
    $m = strtolower(mel_row_subs_mark($r));
    // A plate b2 (k_badge_subs-*); a mark of no picture: none.
    $w = $m !== '' ? MelKit::badges_w(array("subs-$m")) : 0;
    if ($w > 0)
        $end[] = array('subs', "subs-$m", $w);
    $items = array();
    foreach (array_slice($tail, 0, MEL_GC_LANGS_MAX) as $i => $c)
    {
        $it = isset($flags[$c]) ? array('flag', $flags[$c], MEL_GC_FLAG) :
            array('text', $c, MelKit::w($c, MEL_K_PX_CAPTION));
        $rest = count($tail) - $i - 1;
        $try = array_merge($items, array($it), $rest > 0 ?
            array(array('text', "+$rest", MelKit::w("+$rest", MEL_K_PX_CAPTION))) : array(), $end);
        if (mel_gc_items_w($try) > $width)
            break;
        $items[] = $it;
    }
    $rest = count($tail) - count($items);
    if ($rest > 0)
        $items[] = array('text', "+$rest", MelKit::w("+$rest", MEL_K_PX_CAPTION));
    return array_merge($items, $end);
}

// The gap before language item $it of a row (mel_gc_lang_items): MEL_GC_SUBS_GAP before the plate of the
// subtitles, else MEL_GC_FLAG_GAP.
/**
 * @param array{'flag'|'text'|'q'|'subs', string, int} $it
 * @return int
 */
function mel_gc_item_gap($it)
{
    return $it[0] === 'subs' ? MEL_GC_SUBS_GAP : MEL_GC_FLAG_GAP;
}

/**
 * @param list<array{'flag'|'text'|'q'|'subs', string, int}> $items
 * @return int
 */
function mel_gc_items_w($items)
{
    $w = 0;
    foreach ($items as $i => $it)
        $w += ($i ? mel_gc_item_gap($it) : 0) + $it[2];
    return $w;
}

// "rutracker, bitru +2" if that fits $width at MEL_K_PX_SECONDARY, else
// "rutracker +3" with the name cut; the "+N" stays.
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
    $px = MEL_K_PX_SECONDARY;
    if ($n > 1)
    {
        $two = mel_clean($r['trackers'][0]) . ', ' . mel_clean($r['trackers'][1]) . ($n > 2 ? ' +' . ($n - 2) : '');
        if (MelKit::w($two, $px) <= $width)
            return $two;
    }
    $more = $n > 1 ? ' +' . ($n - 1) : '';
    return MelKit::cut(mel_clean($r['trackers'][0]), $px, '', $width - MelKit::w($more, $px)) . $more;
}

// Badges of a row by column (b2, the owner 18.11.2026): resolution, source, picture, sound, one each (mel_badges),
// its senior part (mel_badge_senior: «Vision» without HDR10, «Atmos», else the codec) with a glyph k_glyph_*
// (mel_k_pic(); no other: ''). AI, IMAX and the editions only in the card (the field "Видео").
/**
 * @param MelBadges $b
 * @return array{res: string, src: string, vis: string, audio: string}
 */
function mel_gc_row_badges($b)
{
    $pics = mel_k_pic();
    $cols = array('res' => $b['res'], 'src' => $b['src'], 'vis' => $b['vis'],
        'audio' => $b['audio'] ? $b['audio'][0] : '');
    foreach ($cols as $k => $id)
    {
        $id = mel_badge_senior($id);
        $cols[$k] = $id !== '' && isset($pics["k_glyph_$id"]) ? $id : '';
    }
    return $cols;
}

// Column x and width for this list, each by its widest value; empty columns
// take no room; the icon column as wide as the plate of the cache, wider only
// for wider seeders. A column of the badges for each part (the plate of every
// row as wide as the column, the glyph in its middle: the owner 20.11.2026,
// variant C), the size keep their width; the languages
// get what is left but MEL_GC_TRACKER_MIN of the trackers (or the expression
// name, or the add-on): their flags fold into "+N" first, "?" and the plate of
// the subtitles stay (the owner 19.11.2026); the trackers get the rest if it
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
    // The narrowest the languages go: all flags in "+N" (mel_gc_lang_items at no width).
    $lmin = 0;
    $any_tracker = false;
    $p = MelKit::pic('k_icon_cached');
    $icon = $p['w'];
    foreach ($rows as $r)
    {
        $n = mel_gc_seeds($r);
        if (!is_null($n))
            $icon = max($icon, MelKit::w(mel_gc_seeds_str($n), MEL_K_PX_SECONDARY));
        foreach (mel_gc_row_badges($r['badges']) as $k => $id)
            $max[$k] = max($max[$k], $id !== '' ? MelKit::b2_w($id) : 0);
        $max['size'] = max($max['size'], $r['size'] > 0 ?
            min(180, MelKit::w(mel_size_str($r['size'], $tt['lang']), MEL_K_PX_ROW)) : 0);
        $langs = max($langs, min(260, mel_gc_items_w(mel_gc_lang_items($r, PHP_INT_MAX))));
        $lmin = max($lmin, min(260, mel_gc_items_w(mel_gc_lang_items($r, 0))));
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
    if ($langs > 0 && $end - $x >= MEL_GC_FLAG)
    {
        $room = $end - $x - ($any_tracker ? MEL_GC_GAP + MEL_GC_TRACKER_MIN : 0);
        $cols['langs'] = array($x, min($langs, $end - $x, max($room, $lmin, MEL_GC_FLAG)));
        $x += $cols['langs'][1] + MEL_GC_GAP;
    }
    if ($any_tracker && $end - $x >= MEL_GC_TRACKER_MIN)
        $cols['tracker'] = array($x, $end - $x);
    return $cols;
}

// The columns of list $st: those of its last full view (gc_cols, mel_gc_folder_view), else counted (mel_gc_cols) -
// they go over all the rows, the most of a step of the cursor before.
/**
 * @param MelState $st
 * @return MelGcCols
 */
function mel_gc_cols_st($st)
{
    return isset($st['gc_cols']) ? $st['gc_cols'] : mel_gc_cols($st['rows'], mel_gc_text(mel_gc_lang($st)));
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
    $h = max(MEL_K_THUMB_MIN, intval($track * MEL_GC_VIS / $n));
    $y = intval(round(($track - $h) * $top / ($n - MEL_GC_VIS)));
    return mel_gc_at(6, $h, MEL_GC_LX + MEL_GC_LW + 10, MEL_GC_LY + $y);
}

// The colour of status $cached (of mel_gc_status): green in the cache, grey to download, dim unknown.
/**
 * @param bool|null $cached
 * @return string
 */
function mel_gc_status_color($cached)
{
    return is_null($cached) ? MEL_K_C_TEXT3 : ($cached ? MEL_K_C_OK : MEL_K_C_UNCACHED);
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

// Items $items set by their own x as one row of the kit (MelKit::row): the gaps between them as they are, their
// lines on the centre of the capitals of the first text at $y.
/**
 * @param list<MelKitItem> $items
 * @param int|float $y
 * @param string $name
 * @return MelKitItem
 */
function mel_gc_line($items, $y, $name)
{
    $gaps = array();
    for ($i = 1; $i < count($items); $i++)
        $gaps[] = $items[$i]['x0'] - $items[$i - 1]['x1'];
    return MelKit::row($items, $gaps, $items ? $items[0]['x0'] : 0, $y, $name);
}

// Children of row panel "r$i", on the centre of the row: the cache status (a lightning on a green disc, the
// arrow, "?"; the seeders of a P2P row centred in its column), the plates b2 each from the x of its
// column (mel_gc_cols), the size (right-aligned), the flags, the trackers
// (or the expression name; no trackers: the add-on dimmed). The row in focus on the blue plate (sel): its texts
// white (the seeders too: the colours of their bins only out of focus), the dim ones light; the disc of the
// lightning lighter. A release watched before ($share > 0): a bar
// at the bottom of the plate of the focus (none at 0).
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
    $y = MEL_GC_ROW_H / 2;
    $fg = $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT1;
    $fg2 = $on ? MEL_K_C_ON_ACCENT2 : MEL_K_C_TEXT2;
    $fg3 = $on ? MEL_K_C_ON_ACCENT2 : MEL_K_C_TEXT3;
    $it = array();
    // The status (or the seeders), centred in the icon column.
    $cx = $c['icon'][0] + intval($c['icon'][1] / 2);
    $n = mel_gc_seeds($r);
    if (!is_null($n))
    {
        $s = mel_gc_seeds_str($n);
        $it[] = MelKit::text($s, MEL_K_PX_SECONDARY, $on ? MEL_K_C_ON_ACCENT : mel_gc_seed_color($n, false),
            $cx - intval(MelKit::w($s, MEL_K_PX_SECONDARY) / 2), $y);
    }
    elseif ($r['cached'] === true)
    {
        // The lightning on its disc, one picture (k_icon_cached, -on in focus), as it is: no mix_color.
        $name = $on ? 'k_icon_cached-on' : 'k_icon_cached';
        $p = MelKit::pic($name);
        $px = $cx - intval($p['w'] / 2);
        $py = intval($y - $p['h'] / 2);
        $it[] = MelKit::box(array(mel_gc_image(mel_gc_at($p['w'], $p['h'], $px, $py), $p['url'])), $px, $py, $p['w'],
            $p['h']);
    }
    else
    {
        list($icon) = mel_gc_status_icon($r);
        $it[] = MelKit::icon($icon, MEL_K_ICON_LIST_DOWNLOAD, $fg3, $cx, $y);
    }
    $b = mel_gc_row_badges($r['badges']);
    foreach (array('res', 'src', 'vis', 'audio') as $k)
    {
        if ($b[$k] !== '' && isset($c[$k]))
            $it[] = MelKit::b2($b[$k], $c[$k][0], $y, $c[$k][1]);
    }
    if ($r['size'] > 0 && isset($c['size']))
    {
        $s = MelKit::cut(mel_size_str($r['size'], $tt['lang']), MEL_K_PX_ROW, '', $c['size'][1]);
        $it[] = MelKit::text($s, MEL_K_PX_ROW, $fg, $c['size'][0] + $c['size'][1] - MelKit::w($s, MEL_K_PX_ROW), $y);
    }
    if (isset($c['langs']))
    {
        $x = $c['langs'][0];
        foreach (mel_gc_lang_items($r, $c['langs'][1]) as $i => $l)
        {
            $x += $i ? mel_gc_item_gap($l) : 0;
            if ($l[0] === 'flag' || $l[0] === 'q')
            {
                // "?" (or the service word) in the slot of a flag, as a flag: its picture as wide as it is.
                $url = "gui_skin://flags/{$l[1]}.png";
                $fw = MEL_GC_FLAG;
                if ($l[0] === 'q')
                {
                    $p = MelKit::pic($l[1]);
                    $url = $p['url'];
                    $fw = $p['w'];
                }
                $fy = intval($y - MEL_GC_FLAG / 2);
                $it[] = MelKit::box(array(mel_gc_image(mel_gc_at($fw, MEL_GC_FLAG, $x, $fy), $url)), $x, $fy,
                    $fw, MEL_GC_FLAG);
            }
            elseif ($l[0] === 'subs')
                $it[] = MelKit::badges(array($l[1]), $x, $y, 'badges subs');
            else
                $it[] = MelKit::text($l[1], MEL_K_PX_CAPTION, $fg2, $x, $y);
            $x += $l[2];
        }
    }
    // The name of the AIOStreams expression in place of the trackers (the
    // card keeps them); no trackers (DMM Cast, some StremThru Torz): the
    // add-on, dimmed.
    if (($r['expr'] !== '' || $r['trackers'] || $r['addon'] !== '') && isset($c['tracker']))
    {
        $cw = $c['tracker'][1];
        $px = MEL_K_PX_SECONDARY;
        if ($r['expr'] !== '')
            $txt = MelKit::cut(mel_clean($r['expr']), $px, '', $cw);
        else if ($r['trackers'])
            $txt = mel_gc_tracker($r, $cw);
        else
            $txt = MelKit::cut(mel_clean($r['addon']), $px, '', $cw);
        $it[] = MelKit::text($txt, $px, $r['expr'] !== '' || $r['trackers'] ? $fg2 : $fg3, $c['tracker'][0], $y);
    }
    $line = mel_gc_line($it, $y, 'row');
    $d = $line['defs'];
    if ($share > 0)
    {
        $bar = MelKit::bar('progress', MEL_GC_PAD, MEL_GC_BAR_Y, MEL_GC_LW - 2 * MEL_GC_PAD, MEL_GC_BAR_H, $share);
        $d = array_merge($d, $bar['defs']);
    }
    return $d;
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

// The plate of the focus of the list (panel 'sel', moved by mel_gc_cursor_changes): row_on, MEL_K_ROW_ON_H high in
// the middle of row $sel.
/**
 * @param int $sel
 * @return MelGcComp
 */
function mel_gc_sel($sel)
{
    $p = MelKit::plate('row_on', 0, intval((MEL_GC_ROW_H - MEL_K_ROW_ON_H) / 2), MEL_GC_LW, MEL_K_ROW_ON_H);
    return mel_gc_panel('sel', mel_gc_row_geom($sel), $p['defs']);
}

// A field of the card: label $label (MEL_K_PX_CAPTION, text 3) and the lines $lines of its value (MEL_K_PX_SECONDARY,
// text 1, step $lh) from x MEL_GC_VX, the first on the centre of the capitals of the label.
/**
 * @param string $label
 * @param list<string> $lines
 * @param int $lh
 * @return MelKitItem
 */
function mel_gc_field($label, $lines, $lh)
{
    $vw = MEL_GC_CW - MEL_GC_VX;
    $l = MelKit::text($label, MEL_K_PX_CAPTION, MEL_K_C_TEXT3, 0, 0, array('w' => MEL_GC_VX - MEL_K_GAP_XS));
    $v = MelKit::text($lines, MEL_K_PX_SECONDARY, MEL_K_C_TEXT1, 0, 0,
        array('w' => $vw, 'ls' => $lh - MEL_K_PX_SECONDARY));
    // The rhythm of the card by the text of the row: the top of the capitals of its first (the label), the baseline
    // of its last.
    return MelKit::row(array($l, $v), MEL_GC_VX - $l['x1'], 0, null, 'field', true);
}

// The lines of field $k of the card with value $v, $n at most: video and audio by items of " · ", the voices by
// groups ($alone: "Дубляж: …" on lines of its own), the rest by words.
/**
 * @param string $k
 * @param string $v
 * @param int $n
 * @param bool $alone
 * @return list<string>
 */
function mel_gc_field_lines($k, $v, $n, $alone)
{
    $px = MEL_K_PX_SECONDARY;
    $w = MEL_GC_CW - MEL_GC_VX;
    if ($k === 'voices')
        return MelKit::wrap_groups($v, $px, '', $w, $n, $alone);
    if ($k === 'video' || $k === 'audio')
        return MelKit::wrap_items($v, $px, '', $w, $n);
    return MelKit::wrap($v, $px, '', $w, $n);
}

// The field "Языки" of the card of row $r (variant B): the confirmed languages (mel_row_voice_langs), then each
// guessed one with "?" ("JA, RU?"), then the service codes of 'langs' (MULTI, DUAL, ...) as before.
/**
 * @param MelRow $r
 * @return string
 */
function mel_gc_langs_text($r)
{
    $vl = mel_row_voice_langs($r);
    $out = $vl['ok'];
    foreach ($vl['q'] as $c)
        $out[] = "$c?";
    return implode(', ', array_merge($out, mel_gc_service_langs($r['langs'])));
}

// The visible bottom of blocks $blocks stacked from $top MEL_K_GAP_M apart.
/**
 * @param int $top
 * @param list<MelKitItem> $blocks
 * @return int
 */
function mel_gc_stack_end($top, $blocks)
{
    $end = $top - MEL_K_GAP_M;
    foreach ($blocks as $b)
        $end += MEL_K_GAP_M + $b['bottom'] - $b['top'];
    return $end;
}

// The fields of the card under the name, from $top (the top of their capitals), in the order of priority: video,
// audio, voices (of the audio tracks and the name), subtitles, languages, trackers (without them the add-on,
// unless $addon_side: beside the poster), each up to 2 lines (the voices MEL_GC_VOICES_LINES), MEL_K_GAP_M apart;
// the last baseline at $bottom at most. Video and audio are always there (a dash), the others only with data.
// What does not fit: the last field by priority goes whole (trackers or add-on, then languages), then the
// subtitles lose lines; video, audio and voices are never left out so (their lines only on a card that could not
// hold them at all). -> list of the blocks, not yet stacked.
/**
 * @param MelRow $r
 * @param MelGcText $tt
 * @param bool $addon_side
 * @param int $top
 * @param int $bottom
 * @return list<MelKitItem>
 */
function mel_gc_fields($r, $tt, $addon_side, $top, $bottom)
{
    $vs = mel_row_voices_struct($r);
    $alone = mel_voices_dub_first($vs);
    $b = $r['badges'];
    $video = array($r['resolution'], mel_badge_text($b['vis']), $b['ai'] ? 'AI' : '', mel_row_quality_text($r),
        implode(' ', $b['tags']));
    $fields = array(
        array('video', implode(' · ', array_filter($video, 'strlen'))),
        array('audio', $r['audio_full']),
        // Subtitles have a field of their own.
        array('voices', mel_voices_struct_text($vs, $tt['lang'], false)),
        array('subs', mel_cut(implode(', ', mel_row_subs($r)), MEL_TEXT_MAX)),
        array('langs', mel_cut(mel_gc_langs_text($r), MEL_TEXT_MAX)),
        $r['trackers'] ? array('trackers', mel_cut(implode(', ', $r['trackers']), MEL_TEXT_MAX)) :
            array('addon', $addon_side ? '' : $r['addon']));
    // The capitals of a line: its top to its baseline.
    $caps = mel_k_cap();
    $cap = (int) round($caps[MEL_K_PX_SECONDARY]);
    $out = array();
    $opt = array();
    $at = $top;
    foreach ($fields as $f)
    {
        $v = mel_clean($f[1]);
        $ess = in_array($f[0], array('video', 'audio', 'voices'), true);
        // Video and audio are always there (a dash); the others only with data.
        if ($v === '' && $f[0] !== 'video' && $f[0] !== 'audio')
            continue;
        $v = $v !== '' ? $v : "\xE2\x80\x94";
        $lh = $f[0] === 'voices' ? MEL_GC_VOICES_LH : MEL_GC_LH;
        $max = $f[0] === 'voices' ? MEL_GC_VOICES_LINES : 2;
        if ($ess)
        {
            $max = min($max, (int) floor(($bottom - $at - $cap) / $lh) + 1);
            if ($max < 1)
                continue;
            $it = mel_gc_field($tt[$f[0]], mel_gc_field_lines($f[0], $v, $max, $alone), $lh);
            $out[] = $it;
            $at += $it['bottom'] - $it['top'] + MEL_K_GAP_M;
            continue;
        }
        $opt[] = array($f[0], $v, $lh, $max);
    }
    // The optional fields while they fit: from the end, a field whole; the subtitles line by line. Each block built
    // once (by field and lines), not again on every try.
    $built = array();
    while ($opt)
    {
        $its = array();
        foreach ($opt as $o)
        {
            $key = "{$o[0]}:{$o[3]}";
            if (!isset($built[$key]))
                $built[$key] = mel_gc_field($tt[$o[0]], mel_gc_field_lines($o[0], $o[1], $o[3], $alone), $o[2]);
            $its[] = $built[$key];
        }
        if (mel_gc_stack_end($at, $its) <= $bottom)
            return array_merge($out, $its);
        $o = array_pop($opt);
        if ($o[0] === 'subs' && $o[3] > 1)
            $opt[] = array($o[0], $o[1], $o[2], $o[3] - 1);
    }
    return $out;
}

// Children of 'card' (left column, at the poster's top left), stacks of the kit. Beside the poster (MEL_K_GAP_M
// after it; without a poster from the left edge): the size (MEL_K_PX_PANEL_TITLE), the bit rate and the size of a
// season pack (MEL_K_PX_SECONDARY, text 2), the cache status with its icon (MEL_K_PX_ROW, in the colour of the
// status), the release group and the source (platform of a WEB-DL), labels MEL_K_PX_CAPTION in text 3; at the
// poster's bottom the add-on when it fits. Under the poster (from the same y without one): the name (up to
// MEL_GC_NAME_LINES lines by fact, step MEL_GC_NAME_LH), then the fields (mel_gc_fields) above MEL_GC_CARD_BOTTOM.
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
    $vls = MEL_GC_LH - MEL_K_PX_ROW;
    // Beside the poster; without a poster at the left edge.
    $sx = mel_img($st['movie']['poster']) !== '' ? MEL_GC_PW + MEL_GC_SIDE_GAP : 0;
    $sw = $w - $sx;
    $side = array();
    $gaps = array();
    if ($r['size'] > 0)
        $side[] = MelKit::text(mel_size_str($r['size'], $tt['lang']), MEL_K_PX_PANEL_TITLE, MEL_K_C_TEXT1, $sx, 0,
            array('w' => $sw));
    foreach (array($r['rate'] > 0 ? mel_gc_rate($r['rate'], $r['rate_approx'], $tt['lang']) : '',
        $r['pack'] > 0 ? $tt['pack'] . ' ' . mel_size_str($r['pack'], $tt['lang']) : '') as $s)
    {
        if ($s === '')
            continue;
        if ($side)
            $gaps[] = 's';
        $side[] = MelKit::text($s, MEL_K_PX_SECONDARY, MEL_K_C_TEXT2, $sx, 0, array('w' => $sw));
    }
    // The status (and the release group) in 4 lines together, so that the worst case holds beside the poster: a
    // file cached on several takes up to 3 ("В кэше" / "Real-Debrid" / "и TorBox"), then the release 1; else 2 each.
    // The icon (r5: the lightning) only of a known status, in the cache or to download; P2P and unknown none, as
    // before the kit.
    list($status, $color) = mel_gc_card_status($r, $tt);
    list($icon, $icolor) = mel_gc_status_icon($r);
    $i = is_null($r['cached']) ? null : MelKit::icon($icon, MEL_K_ICON_CACHED, $icolor, 0, 0, array('box' => true));
    $tw = $sw - ($i ? $i['x1'] - $i['x0'] + MEL_K_GAP_XS : 0);
    $sl = mel_gc_status_k($status, MEL_K_PX_ROW, $tw, 3);
    if ($side)
        $gaps[] = 'm';
    $t = MelKit::text($sl, MEL_K_PX_ROW, $color, $sx, 0, array('ls' => $vls));
    // The rhythm by the text, the icon on its line.
    $side[] = $i ? MelKit::row(array($i, $t), MEL_K_GAP_XS, $sx, null, 'status', true) : $t;
    if ($r['group'] !== '')
    {
        $side[] = MelKit::text($tt['group'], MEL_K_PX_CAPTION, MEL_K_C_TEXT3, $sx, 0);
        $side[] = MelKit::text(mel_clean($r['group']), MEL_K_PX_ROW, MEL_K_C_TEXT2, $sx, 0,
            array('w' => $sw, 'lines' => min(2, 4 - count($sl)), 'ls' => $vls));
        array_push($gaps, 'm', 's');
    }
    // The platform of a WEB-DL: one line, so that it fits beside the poster in the worst case.
    if ($r['network'] !== '')
    {
        $side[] = MelKit::text($tt['source'], MEL_K_PX_CAPTION, MEL_K_C_TEXT3, $sx, 0);
        $side[] = MelKit::text(mel_clean($r['network']), MEL_K_PX_ROW, MEL_K_C_TEXT2, $sx, 0, array('w' => $sw));
        array_push($gaps, 'm', 's');
    }
    $side = MelKit::stack($side, $gaps, 0, 'side');
    $d = $side['defs'];
    // The add-on at the poster's bottom (its baseline on it), when it fits under the fields above; else under the
    // poster without trackers.
    $addon = mel_clean($r['addon']);
    $addon_side = false;
    if ($addon !== '')
    {
        $a = MelKit::stack(array(MelKit::text($tt['addon'], MEL_K_PX_CAPTION, MEL_K_C_TEXT3, $sx, 0),
            MelKit::text($addon, MEL_K_PX_ROW, MEL_K_C_TEXT2, $sx, 0, array('w' => $sw))), 's', 0, 'addon');
        $a = MelKit::move($a, 0, MEL_GC_PH - $a['bottom']);
        $addon_side = $side['bottom'] + MEL_K_GAP_M <= $a['top'];
        if ($addon_side)
            $d = array_merge($d, $a['defs']);
    }

    // Under the poster's place even without a poster: the name and the fields below it.
    $name = mel_clean($r['label']);
    if (mb_strlen($name, 'UTF-8') > MEL_GC_NAME_MAX)
        $name = rtrim(mb_substr($name, 0, MEL_GC_NAME_MAX, 'UTF-8')) . "\xE2\x80\xA6";
    $blocks = array(MelKit::text($name, MEL_K_PX_SECONDARY, MEL_K_C_TEXT1, 0, 0,
        array('w' => $w, 'lines' => MEL_GC_NAME_LINES, 'ls' => MEL_GC_NAME_LH - MEL_K_PX_SECONDARY)));
    $top = MEL_GC_PH + MEL_K_GAP_M;
    $blocks = array_merge($blocks, mel_gc_fields($r, $tt, $addon_side, $top + $blocks[0]['bottom'] - $blocks[0]['top'] +
        MEL_K_GAP_M, MEL_GC_CARD_BOTTOM - MEL_GC_CY - MEL_K_GAP_XS));
    $under = MelKit::stack($blocks, 'm', $top, 'card');
    return array_merge($d, $under['defs']);
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

// Children of 'counter' (MEL_GC_COUNT_W x MEL_K_KEY_H at the right of the hint line): "5 / 60" right-aligned
// (MEL_K_PX_HINT, text 2), on the centre of the line.
/**
 * @param int $sel
 * @param int $n
 * @return list<MelGcComp>
 */
function mel_gc_count($sel, $n)
{
    $s = min($sel + 1, $n) . " / $n";
    $t = MelKit::text($s, MEL_K_PX_HINT, MEL_K_C_TEXT2, MEL_GC_COUNT_W - MelKit::w($s, MEL_K_PX_HINT),
        MEL_K_KEY_H / 2);
    return $t['defs'];
}

// The hint line of list $st of $n rows (MelKit::hint, from MEL_GC_LX on MEL_GC_HINT_MID): «P+ P−» "Прокрутка" (more
// rows than the screen), the chevrons "Серии" (an episode), MENU "Действия", INFO "Сведения".
/**
 * @param MelState $st
 * @param MelGcText $tt
 * @param int $n
 * @return MelKitItem
 */
function mel_gc_hints($st, $tt, $n)
{
    $items = array();
    if ($n > MEL_GC_VIS)
        $items[] = array('pp', $tt['page']);
    // An episode: the left and right keys go to the episode before and after.
    if ($st['e'] > 0)
        $items[] = array('lr', $tt['flip']);
    $items[] = array('k_icon_menu', $tt['actions']);
    $items[] = array('k_key_info', $tt['details']);
    return MelKit::hint($items, MEL_GC_LX, MEL_GC_HINT_MID);
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
// mel_sort_keys => array(event, the colour of the key: its dot on the tab).
/**
 * @return array<string, array{string, string}>
 */
function mel_gc_sort_keys()
{
    return array('aio' => array(GUI_EVENT_KEY_A_RED, 'red'),
        'seeds' => array(GUI_EVENT_KEY_B_GREEN, 'green'),
        'size' => array(GUI_EVENT_KEY_C_YELLOW, 'yellow'),
        'date' => array(GUI_EVENT_KEY_D_BLUE, 'blue'));
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

// The sort tabs over the list (MelKit::tabs, from MEL_GC_LX on MEL_GC_TABS_MID), panel 'sort': each colour key as
// its dot and its label, the green one "Cached" in a Debrid list (mel_rows_debrid); the sort of the list on its
// plate with its direction (an arrow: down by default, up reversed).
/**
 * @param MelState $st
 * @param MelGcText $tt
 * @return MelKitItem
 */
function mel_gc_sort_tabs($st, $tt)
{
    $tabs = array();
    foreach (mel_gc_sort_keys() as $k => $key)
        $tabs[] = array('key' => $key[1], 'label' => $tt[mel_gc_sort_word($st, $k)],
            'state' => $k === $st['sort'] ? 'on' : '', 'up' => $k === $st['sort'] && $st['sort_rev']);
    return MelKit::group('sort', MelKit::tabs($tabs, MEL_GC_LX, MEL_GC_TABS_MID));
}

// Right of the row of the tabs, right-aligned to $right on MEL_GC_TABS_MID: the number of the source errors in
// amber (mel_errors_shown), left of it the name of the source of the list after its icon (two ready profiles or
// more, sources.php: mel_src_name), in what the tabs leave from $left (less than a few letters: no name).
/**
 * @param MelState $st
 * @param MelGcText $tt
 * @param int $left
 * @param int $right
 * @return list<MelGcComp>
 */
function mel_gc_tabs_right($st, $tt, $left, $right)
{
    $d = array();
    $y = MEL_GC_TABS_MID;
    if (mel_errors_shown($st['errors']))
    {
        $text = str_replace('{n}', strval(mel_errors_total($st['errors'])), $tt['errors']);
        $w = MelKit::w($text, MEL_K_PX_SECONDARY);
        $e = MelKit::text($text, MEL_K_PX_SECONDARY, MEL_K_C_WARN, $right - $w, $y);
        $d = $e['defs'];
        $right -= $w + MEL_K_HINT_GAP;
    }
    $from = mel_src_in_menu() ? mel_src_name(mel_src_now($st)) : '';
    $room = min(300, $right - $left);
    if ($from === '' || $room < 80)
        return $d;
    $i = MelKit::icon('k_icon_layers', MEL_K_ICON_SORT, MEL_K_C_TEXT3, 0, 0, array('box' => true));
    $t = MelKit::text($from, MEL_K_PX_ROW, MEL_K_C_TEXT2, 0, 0,
        array('w' => $room - ($i['x1'] - $i['x0']) - MEL_K_KEY_DOT_GAP));
    $row = MelKit::row(array($i, $t), MEL_K_KEY_DOT_GAP, 0, $y, 'from');
    $row = MelKit::move($row, $right - $row['x1'], 0);
    return array_merge($d, $row['defs']);
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
    $cols = mel_gc_cols_st($st);
    $d = array();
    $d[] = mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), MEL_K_C_SCRIM);
    $poster = mel_img($mv['poster']);
    if ($poster !== '')
        $d[] = mel_gc_image(mel_gc_at(MEL_GC_PW, MEL_GC_PH, MEL_GC_CX, MEL_GC_CY), $poster, true);
    $d[] = mel_gc_panel('card', mel_gc_at(MEL_GC_CW, MEL_GC_CARD_BOTTOM - MEL_GC_CY, MEL_GC_CX, MEL_GC_CY),
        mel_gc_card($st, $sel));

    // The yellow line of "Онлайн" (view_online.php) in the row of the title.
    $d = array_merge($d, mel_gc_head($st, mel_lpv_note($st)));

    // Viewport: clipping panel 'list'; the inner panel 'rows' (as high as all the rows) moves to scroll, the rows of
    // the window (mel_gc_win) in it at their places.
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $in = array(mel_gc_sel($sel));
    list($lo, $hi) = mel_gc_win($top, $n);
    for ($i = $lo; $i < $hi; $i++)
        $in[] = mel_gc_row($i, $rows[$i], $tt, $cols, $i === $sel, mel_gc_share($prog, $rows[$i]));
    // An empty list (the source of a profile has no streams, mel_gc_src): the line
    // of the error in place of the rows.
    if ($n === 0)
    {
        $e = MelKit::text(mel_tr($tt['lang'], 'err_no_streams'), MEL_K_PX_STATUS_TITLE, MEL_K_C_TEXT2,
            MEL_GC_LX + MEL_GC_PAD, MEL_GC_LY + MEL_GC_ROW_H / 2, array('w' => MEL_GC_LW - 2 * MEL_GC_PAD));
        $d = array_merge($d, $e['defs']);
    }
    else
        $d[] = mel_gc_panel('list', mel_gc_at(MEL_GC_LW, MEL_GC_VIS * MEL_GC_ROW_H, MEL_GC_LX, MEL_GC_LY),
            array(mel_gc_panel('rows', mel_gc_rows_geom($top, $n), $in, 0)), 0);
    if ($n > MEL_GC_VIS)
    {
        $d[] = mel_gc_rect(mel_gc_at(6, MEL_GC_VIS * MEL_GC_ROW_H, MEL_GC_LX + MEL_GC_LW + 10, MEL_GC_LY),
            MEL_K_C_SCROLL_TRACK);
        $d[] = mel_gc_rect(mel_gc_thumb_geom($top, $n), MEL_K_C_SCROLL_THUMB, 'thumb');
    }
    $h = mel_gc_hints($st, $tt, $n);
    $d = array_merge($d, $h['defs']);
    $right = MEL_GC_LX + MEL_GC_LW - MEL_GC_PAD;
    $d[] = mel_gc_panel('counter', mel_gc_at(MEL_GC_COUNT_W, MEL_K_KEY_H, $right - MEL_GC_COUNT_W,
        MEL_GC_HINT_MID - intval(MEL_K_KEY_H / 2)), mel_gc_count($sel, $n));
    $left = MEL_GC_LX;
    if (mel_gc_color_keys())
    {
        $tabs = mel_gc_sort_tabs($st, $tt);
        $d = array_merge($d, $tabs['defs']);
        $left = $tabs['x1'] + MEL_K_HINT_GAP;
    }
    // Outside the tabs: a remote without the colour keys has them too.
    $d = array_merge($d, mel_gc_tabs_right($st, $tt, $left, $right));

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
    $cols = mel_gc_cols_st($st);
    // Only the rows on the screen (mel_gc_win); the others get the share when the window comes to them.
    list($lo, $hi) = mel_gc_win($top, count($st['rows']));
    $ch = array();
    foreach ($rows as $i)
    {
        if ($i >= $lo && $i < $hi)
            $ch[] = mel_gc_change("r$i", null, GCOMP_TRANSITION_NONE,
                mel_gc_row_items($st['rows'][$i], $tt, $cols, $i === $sel, $now));
    }
    return $ch ? mel_gc_changes_act($ch, $sel, $top) : null;
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
    // The columns too, counted once a full view (mel_gc_cols_st).
    $st['gc_cols'] = Mel::$state['gc_cols'] = mel_gc_cols($st['rows'], mel_gc_text(mel_gc_lang($st)));
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

// The MENU item "Откуда брать…" of live list $st (gc_src_menu: the menu of all ready profiles, any kind; of a card of no
// IMDb those of Lampac), or null: fewer than two ready (mel_src_in_menu).
/**
 * @param MelState $st
 * @return array<string, mixed>|null
 */
function mel_gc_src_item($st)
{
    if (!mel_src_in_menu($st['movie']['imdb'] === ''))
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

    $tt = mel_gc_text(mel_gc_lang($st));
    $cols = mel_gc_cols_st($st);
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    // As mel_gc_cursor_changes, and the window of rows follows the top (mel_gc_win): the rows that leave it removed,
    // those that come in (with the 'rows' change, after the row before them; above all: after 'sel', over it), as
    // the episodes of the own card (view_card_series.php). Both cursor rows stay: the window holds a page above and
    // below the screen.
    $ch = array(mel_gc_change('sel', mel_gc_row_geom($nsel), GCOMP_TRANSITION_DEFAULT));
    if ($ntop !== $top)
    {
        list($olo, $ohi) = mel_gc_win($top, $n);
        list($lo, $hi) = mel_gc_win($ntop, $n);
        $add = array();
        for ($i = min($olo, $lo); $i < max($ohi, $hi); $i++)
        {
            $was = $i >= $olo && $i < $ohi;
            $now = $i >= $lo && $i < $hi;
            if ($was && !$now)
            {
                $x = mel_gc_change("r$i", null, GCOMP_TRANSITION_NONE);
                $x[ChangeGCompDef::remove] = true;
                $ch[] = $x;
            }
            elseif ($now && !$was)
                $add[] = mel_gc_row($i, $st['rows'][$i], $tt, $cols, false, mel_gc_share($prog, $st['rows'][$i]));
        }
        $x = mel_gc_change('rows', mel_gc_rows_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT, $add ? $add : null);
        if ($add)
        {
            $x[ChangeGCompDef::children_merge_mode] = true;
            $x[ChangeGCompDef::children_insert_after_id] = $lo < $olo ? 'sel' : 'r' . ($ohi - 1);
        }
        $ch[] = $x;
        $ch[] = mel_gc_change('thumb', mel_gc_thumb_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT);
    }
    // The focus brightness moves with the row.
    $ch[] = mel_gc_change("r$sel", null, GCOMP_TRANSITION_NONE,
        mel_gc_row_items($st['rows'][$sel], $tt, $cols, false, mel_gc_share($prog, $st['rows'][$sel])));
    $ch[] = mel_gc_change("r$nsel", null, GCOMP_TRANSITION_NONE,
        mel_gc_row_items($st['rows'][$nsel], $tt, $cols, true, mel_gc_share($prog, $st['rows'][$nsel])));
    $ch[] = mel_gc_change('card', null, GCOMP_TRANSITION_NONE, mel_gc_card($st, $nsel));
    $ch[] = mel_gc_change('counter', null, GCOMP_TRANSITION_NONE, mel_gc_count($nsel, $n));
    return mel_gc_changes_act($ch, $nsel, $ntop);
}
