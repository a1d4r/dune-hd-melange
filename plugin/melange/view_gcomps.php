<?php
// Stream list as a view_gcomps screen, 1920x1080, on the components of the UI kit (kit.php, r5 of the owner):
// darkened fanart; on the left the poster, beside it the size, the bit rate and the cache status, under it a card
// of the selected release (the name in up to 3 lines, video, audio, voices, subtitles, languages, trackers); on the
// right the list: the cache status (the seeders of a P2P row), the plates b2 (resolution, source, picture, sound:
// the senior part, mel_badge_senior; each part a column), size, language flags, trackers if room is left, a bar of
// a release watched before; between the title and the list the sort tabs of the colour keys (a
// remote with them), the name of the source at the right; under the list the hint line (P+ P-, <- ->, MENU
// "Actions", INFO "Details") and "1 / 60" at the right. The plugin draws and moves the selection (the blue plate
// row_on) itself with change_gcomps; the position travels in sel_state {"sel":N,"top":T}, N and T of the lines of
// the screen (mel_gc_lv): the rows of the tier "Не подтверждены" (first in the rows) under a line of their group
// at the top, shown when it is unfolded; every action takes its row of the rows by mel_gc_row_of.
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
// The window of rows on the screen (mel_gc_rows_span) moves by this many rows: a move of the window adds and removes as many.
define('MEL_GC_WIN_STEP', 6);
// The centres of the capitals of the title, of the sort tabs and of the hint line (r5).
define('MEL_GC_TITLE_MID', 73);
define('MEL_GC_TABS_MID', 150);
define('MEL_GC_HINT_MID', 1012);
define('MEL_GC_COUNT_W', 300);   // the counter "5 / 60" at the right of the hint line
define('MEL_GC_PAD', 16);      // row padding left and right
define('MEL_GC_GAP', 16);      // between columns
define('MEL_GC_ICON_GAP', 14);  // the icon column -> the first column of the plates and the caption of the group
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
define('MEL_GC_LANGS_MAX', 2);     // language flags of a row, the rest as "+N" (the owner 20.11.2026)
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
        'sort_date' => mel_tr($lang, 'gc_sort_date'), 'errors' => mel_tr($lang, 'gc_errors'),
        'tier' => mel_tr($lang, 'gc_tier'), 'tier_card' => mel_tr($lang, 'gc_tier_card'),
        'tier_show' => mel_tr($lang, 'gc_tier_show'), 'tier_hide' => mel_tr($lang, 'gc_tier_hide'),
        'check' => mel_tr($lang, 'gc_tier_check'));
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

// sel_state -> array(sel, top), or null when it does not fit $n rows, $vis on the screen.
/**
 * @param mixed $s
 * @param int $n
 * @param int $vis
 * @return array{int, int}|null
 */
function mel_gc_parse($s, $n, $vis = MEL_GC_VIS)
{
    $j = is_string($s) && $s !== '' ? json_decode($s) : null;
    if (!is_object($j) || !isset($j->sel, $j->top) || !is_int($j->sel) || !is_int($j->top))
        return null;
    if ($j->sel < 0 || $j->sel >= $n || $j->top < 0 || $j->top > max(0, $n - $vis) ||
        $j->sel < $j->top || $j->sel >= $j->top + $vis)
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

// The cursor of list $st, of its lines (mel_gc_lv): from the shell's sel_state $raw; else the last known position of
// this list; else the line a list opens on (mel_gc_first). -> array(sel, top).
/**
 * @param MelState $st
 * @param mixed $raw
 * @return array{int, int}
 */
function mel_gc_pos($st, $raw)
{
    $lv = mel_gc_lv($st);
    $p = mel_gc_parse($raw, $lv['l']);
    if (!$p && isset($st['gc']))
        $p = mel_gc_parse(mel_gc_state($st['gc'][0], $st['gc'][1]), $lv['l']);
    return $p ? $p : mel_gc_cursor(mel_gc_first($lv), $lv['l']);
}

// The row of $st['rows'] under the cursor of list $st (mel_gc_pos of sel_state $raw), -1 on the line of the group.
/**
 * @param MelState $st
 * @param mixed $raw
 * @return int
 */
function mel_gc_row_now($st, $raw)
{
    list($sel) = mel_gc_pos($st, $raw);
    return mel_gc_row_of(mel_gc_lv($st), $sel);
}

// --- The tier "Не подтверждены": the lines of the screen over the rows.

// The lines of list $st: its rows of the tier come first (mel_rows_sort), so their number is enough; with one the first
// line is that of their group, its rows under it when it is unfolded, then the rows of the main list. Unfolded:
// 'tier_open' of the state (ENTER on the line of the group, mel_gc_put_cursor of a row of it); always without a row of
// the main list. -> array(t: rows of the tier, o: unfolded, n: rows, l: lines).
/**
 * @param MelState $st
 * @return MelGcLv
 */
function mel_gc_lv($st)
{
    $n = count($st['rows']);
    $t = 0;
    while ($t < $n && !empty($st['rows'][$t]['tier']))
        $t++;
    $o = $t > 0 && ($t === $n || !empty($st['tier_open']));
    return array('t' => $t, 'o' => $o, 'n' => $n, 'l' => $t === 0 ? $n : 1 + ($o ? $n : $n - $t));
}

// The row of $st['rows'] of line $j of lines $lv (mel_gc_lv), -1 the line of the group or none.
/**
 * @param MelGcLv $lv
 * @param int $j
 * @return int
 */
function mel_gc_row_of($lv, $j)
{
    if ($lv['t'] === 0)
        return $j >= 0 && $j < $lv['n'] ? $j : -1;
    if ($j <= 0 || $j >= $lv['l'])
        return -1;
    return $lv['o'] ? $j - 1 : $lv['t'] + $j - 1;
}

// The line of row $i of lines $lv; a row of the group folded: the line of the group.
/**
 * @param MelGcLv $lv
 * @param int $i
 * @return int
 */
function mel_gc_line_of($lv, $i)
{
    if ($lv['t'] === 0)
        return $i;
    if ($lv['o'])
        return $i + 1;
    return $i < $lv['t'] ? 0 : $i - $lv['t'] + 1;
}

// Whether line $j of lines $lv (mel_gc_lv) is that of the group of the tier.
/**
 * @param MelGcLv $lv
 * @param int $j
 * @return bool
 */
function mel_gc_on_group($lv, $j)
{
    return $lv['t'] > 0 && $j === 0;
}

// The line a list of lines $lv opens on: the first row of the main list (the group folded above it, or unfolded:
// after its rows); none: the first row of the tier (the group unfolded, the line under it); no tier: the first line.
/**
 * @param MelGcLv $lv
 * @return int
 */
function mel_gc_first($lv)
{
    return mel_gc_line_of($lv, $lv['t'] < $lv['n'] ? $lv['t'] : 0);
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

// List state $st put in with row $sel of $st['rows'] selected (mel_finish, mel_relist): a row of the tier unfolds
// its group ('tier_open'); -1 (or no such row): the line the list opens on (mel_gc_first). $was: the list it replaces
// (of the same title: another episode, the request anew, the sort), its group folded or not taken over first, before
// the lines are counted; null: none (a list opened anew, its own flag). The cursor on its line as mel_gc_cursor, and its first view ignores the shell's sel_state.
/**
 * @param MelState $st
 * @param int $sel
 * @param MelState|null $was
 * @return MelState
 */
function mel_gc_put_cursor($st, $sel, $was)
{
    if ($was !== null)
        $st['tier_open'] = !empty($was['tier_open']);
    $lv = mel_gc_lv($st);
    if ($sel >= 0 && $sel < $lv['t'] && !$lv['o'])
    {
        $st['tier_open'] = true;
        $lv = mel_gc_lv($st);
    }
    $st['gc'] = mel_gc_cursor($sel >= 0 && $sel < $lv['n'] ? mel_gc_line_of($lv, $sel) : mel_gc_first($lv), $lv['l']);
    $st['gc_new'] = true;
    return $st;
}

// One key: up, down, pgup (P+), pgdn (P-), $vis rows on the screen (the screen of files of a pack: its own). A page
// keeps the row on screen.
/**
 * @param int $sel
 * @param int $top
 * @param int $n
 * @param 'up'|'down'|'pgup'|'pgdn' $key
 * @param int $vis
 * @return array{int, int}
 */
function mel_gc_step($sel, $top, $n, $key, $vis = MEL_GC_VIS)
{
    $d = array('up' => -1, 'down' => 1, 'pgup' => -$vis, 'pgdn' => $vis);
    $max_top = max(0, $n - $vis);
    $nsel = max(0, min($n - 1, $sel + $d[$key]));
    $ntop = abs($d[$key]) > 1 ? max(0, min($max_top, $top + $d[$key])) : $top;
    if ($nsel < $ntop)
        $ntop = $nsel;
    else if ($nsel >= $ntop + $vis)
        $ntop = $nsel - $vis + 1;
    return array($nsel, $ntop);
}

// The rows of a list of $n rows on the screen with $top first visible, the span [first, past): array(first, past the
// last). A step costs the shell by all the components of the screen, so not all the rows: the visible ones and at
// least a page (MEL_GC_VIS)
// above and below them, so that the animated scroll of a page (P+, P-) or of a row never shows a row missing; the
// window moves by MEL_GC_WIN_STEP rows, 41 rows in all. Of the top only: a sel_state has always the same screen. $vis:
// the rows on the screen (the screen of files of a pack: its own).
/**
 * @param int $top
 * @param int $n
 * @param int $vis
 * @return array{int, int}
 */
function mel_gc_rows_span($top, $n, $vis = MEL_GC_VIS)
{
    $b = $top - $top % MEL_GC_WIN_STEP;
    return array(max(0, $b - $vis), min($n, $b + MEL_GC_WIN_STEP - 1 + 2 * $vis));
}

// The lines that come into the window of lines (mel_gc_rows_span) of a list of $n lines, $vis on the screen, when its top goes
// from $top to $ntop: their j in order; the caller builds them for mel_gc_win_changes.
/**
 * @param int $top
 * @param int $ntop
 * @param int $n
 * @param int $vis
 * @return list<int>
 */
function mel_gc_win_new($top, $ntop, $n, $vis = MEL_GC_VIS)
{
    list($olo, $ohi) = mel_gc_rows_span($top, $n, $vis);
    list($lo, $hi) = mel_gc_rows_span($ntop, $n, $vis);
    $out = array();
    for ($j = $lo; $j < $hi; $j++)
    {
        if ($j < $olo || $j >= $ohi)
            $out[] = $j;
    }
    return $out;
}

// The changes of a list of $n lines ($vis on the screen from y $ly) whose top went from $top to $ntop: the lines that
// leave the window (mel_gc_rows_span) removed, those that come in - $add, built of mel_gc_win_new - put in with the 'rows'
// change (after the line before them; above all, or a window apart from the old one - a jump of more than the window,
// none of its lines kept: after 'sel', over it), the thumb. Both cursor lines stay in a step of a page at most: the
// window holds a page above and below the screen.
/**
 * @param int $top
 * @param int $ntop
 * @param int $n
 * @param list<MelGcComp> $add
 * @param int $vis
 * @param int $ly
 * @return list<MelGcChange>
 */
function mel_gc_win_changes($top, $ntop, $n, $add, $vis = MEL_GC_VIS, $ly = MEL_GC_LY)
{
    list($olo, $ohi) = mel_gc_rows_span($top, $n, $vis);
    list($lo, $hi) = mel_gc_rows_span($ntop, $n, $vis);
    $ch = array();
    for ($j = $olo; $j < $ohi; $j++)
    {
        if ($j < $lo || $j >= $hi)
        {
            $x = mel_gc_change("r$j", null, GCOMP_TRANSITION_NONE);
            $x[ChangeGCompDef::remove] = true;
            $ch[] = $x;
        }
    }
    $x = mel_gc_change('rows', mel_gc_rows_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT, $add ? $add : null);
    if ($add)
    {
        $x[ChangeGCompDef::children_merge_mode] = true;
        $x[ChangeGCompDef::children_insert_after_id] = $lo < $olo || $lo >= $ohi ? 'sel' : 'r' . ($ohi - 1);
    }
    $ch[] = $x;
    $ch[] = mel_gc_change('thumb', mel_gc_thumb_geom($ntop, $n, $vis, $ly), GCOMP_TRANSITION_DEFAULT);
    return $ch;
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

// Changes of a list of $n lines ($vis on the screen from y $ly) whose cursor went to line $nsel, the first line on
// screen from $top to $ntop: the highlight; when the page moved - the panel 'rows' and the scroll thumb, and the window
// of lines the list holds (mel_gc_rows_span) follows the top (mel_gc_win_changes), as the episodes of the own card
// (view_card_series.php): $mk, j -> the panel "r$j" of line j out of focus.
/**
 * @param int $top
 * @param int $nsel
 * @param int $ntop
 * @param int $n
 * @param callable(int): MelGcComp $mk
 * @param int $vis
 * @param int $ly
 * @return list<MelGcChange>
 */
function mel_gc_cursor_changes($top, $nsel, $ntop, $n, $mk, $vis = MEL_GC_VIS, $ly = MEL_GC_LY)
{
    $ch = array(mel_gc_change('sel', mel_gc_row_geom($nsel), GCOMP_TRANSITION_DEFAULT));
    if ($ntop === $top)
        return $ch;
    $add = array();
    foreach (mel_gc_win_new($top, $ntop, $n, $vis) as $j)
        $add[] = call_user_func($mk, $j);
    return array_merge($ch, mel_gc_win_changes($top, $ntop, $n, $add, $vis, $ly));
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
// (mel_row_subs_mark, k_badge_subs-*) MEL_GC_SUBS_GAP after them, none when the language of the mark is among the
// confirmed ones (subtitles matter without the voice; the card and INFO keep them, mel_row_subs). Without a language confirmed or guessed the
// senior service code of 'langs' (mel_gc_service_word) as a small plate in place of "?" (k_lang_multi, ...; the
// owner 20.11.2026, variant B). With a "+N" (more than MEL_GC_LANGS_MAX, or folded by $width) no "?" (the owner
// 20.11.2026; the card keeps "RU?"). What does not fit $width: the flags go into the "+N" first, the plate stays.
// -> array(flag|text|q|subs, value (flag, text, picture, badge id), width).
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
    // $end after the languages without a "+N", $plus_end after a "+N" (no "?").
    $end = $plus_end = array();
    if ($q !== '')
    {
        $name = 'k_lang_' . $q;
        $p = MelKit::pic($name);
        $end[] = array('q', $name, $p['w']);
    }
    $m = mel_row_subs_mark($r);
    // No mark beside the confirmed voice of its language (the owner 27.11.2026); a guessed one ("?") keeps it.
    $m = in_array($m, $vl['ok'], true) ? '' : strtolower($m);
    // A plate b2 (k_badge_subs-*); a mark of no picture: none.
    $w = $m !== '' ? MelKit::badges_w(array("subs-$m")) : 0;
    if ($w > 0)
        $end[] = $plus_end[] = array('subs', "subs-$m", $w);
    $items = array();
    foreach (array_slice($tail, 0, MEL_GC_LANGS_MAX) as $i => $c)
    {
        $it = isset($flags[$c]) ? array('flag', $flags[$c], MEL_GC_FLAG) :
            array('text', $c, MelKit::w($c, MEL_K_PX_CAPTION));
        $rest = count($tail) - $i - 1;
        $try = array_merge($items, array($it), $rest > 0 ?
            array_merge(array(array('text', "+$rest", MelKit::w("+$rest", MEL_K_PX_CAPTION))), $plus_end) : $end);
        if (mel_gc_items_w($try) > $width)
            break;
        $items[] = $it;
    }
    $rest = count($tail) - count($items);
    if ($rest <= 0)
        return array_merge($items, $end);
    $items[] = array('text', "+$rest", MelKit::w("+$rest", MEL_K_PX_CAPTION));
    return array_merge($items, $plus_end);
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

// The facts of row $i of list $st in one line, for a window about it (the wait of a start through TorrServer, the
// owner 27.11.2026): the title with the year (mel_gc_title; no «S1 E1» of an episode), the words of the plates of the
// row (mel_gc_row_badges, mel_badge_word: resolution, source, picture, sound), its size - «Джентльмены (2019) · 4K ·
// Remux · Dolby Vision · Atmos · 24.2 ГБ»; what the row does not have is left out. Not cut here: the window does.
/**
 * @param MelState $st
 * @param int $i
 * @return string
 */
function mel_gc_facts($st, $i)
{
    $r = $st['rows'][$i];
    list($title) = mel_gc_title($st);
    $parts = array($title);
    foreach (mel_gc_row_badges($r['badges']) as $id)
        $parts[] = mel_badge_word($id);
    $parts[] = $r['size'] > 0 ? mel_size_str($r['size'], mel_gc_lang($st)) : '';
    return implode(" \xC2\xB7 ", array_values(array_filter($parts, 'strlen')));
}

// Column x and width for this list, each by its widest value; empty columns
// take no room; the icon column as wide as the plate of the cache, wider only
// for wider seeders. A column of the badges for each part (the plate of every
// row as wide as the column, the glyph in its middle: the owner 20.11.2026,
// variant C), the size keep their width; the languages
// get what is left but MEL_GC_TRACKER_MIN of the trackers (or the expression
// name, or the add-on): their flags fold into "+N" first, the plate of the
// subtitles stays (the owner 19.11.2026; with a "+N" no "?": mel_gc_lang_items);
// the trackers get the rest if it is at least MEL_GC_TRACKER_MIN.
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
    $x = 12 + $icon + MEL_GC_ICON_GAP;
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

// Scrollbar thumb: height by the visible share, y by the top row. $vis rows on the screen from y $ly (the screen of
// files of a pack: its own).
/**
 * @param int $top
 * @param int $n
 * @param int $vis
 * @param int $ly
 * @return MelGcGeom
 */
function mel_gc_thumb_geom($top, $n, $vis = MEL_GC_VIS, $ly = MEL_GC_LY)
{
    $track = $vis * MEL_GC_ROW_H;
    $h = max(MEL_K_THUMB_MIN, intval($track * $vis / $n));
    $y = intval(round(($track - $h) * $top / ($n - $vis)));
    return mel_gc_at(6, $h, MEL_GC_LX + MEL_GC_LW + 10, $ly + $y);
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
    $line = MelKit::line($it, $y, 'row');
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

// Children of the line of the group of lines $lv: MelKit::group_line from the left of the first column of the plates
// (mel_gc_cols: MEL_GC_ICON_GAP after the icon column), "Не подтверждены" and "4 раздачи", on the centre of the line.
/**
 * @param MelGcLv $lv
 * @param MelGcText $tt
 * @param MelGcCols $c
 * @param bool $on
 * @return list<MelGcComp>
 */
function mel_gc_group_items($lv, $tt, $c, $on)
{
    $g = MelKit::group_line($tt['tier'], mel_gc_tier_count($tt, $lv['t']), $lv['o'], $on,
        $c['icon'][0] + $c['icon'][1] + MEL_GC_ICON_GAP, MEL_GC_ROW_H / 2);
    return $g['defs'];
}

// Children of panel "r$j" of line $j of lines $lv of list $st: the group (mel_gc_group_items) or its row
// (mel_gc_row_items, the share of mel_gc_share of progress $prog).
/**
 * @param MelState $st
 * @param MelGcLv $lv
 * @param int $j
 * @param MelGcText $tt
 * @param MelGcCols $c
 * @param bool $on
 * @param MelProgress $prog
 * @return list<MelGcComp>
 */
function mel_gc_line_items($st, $lv, $j, $tt, $c, $on, $prog)
{
    if (mel_gc_on_group($lv, $j))
        return mel_gc_group_items($lv, $tt, $c, $on);
    $i = mel_gc_row_of($lv, $j);
    return mel_gc_row_items($st['rows'][$i], $tt, $c, $on, mel_gc_share($prog, $st['rows'][$i], $st));
}

// Panel "r$j" of line $j (mel_gc_line_items).
/**
 * @param MelState $st
 * @param MelGcLv $lv
 * @param int $j
 * @param MelGcText $tt
 * @param MelGcCols $c
 * @param bool $on
 * @param MelProgress $prog
 * @return MelGcComp
 */
function mel_gc_line_comp($st, $lv, $j, $tt, $c, $on, $prog)
{
    return mel_gc_panel("r$j", mel_gc_row_geom($j), mel_gc_line_items($st, $lv, $j, $tt, $c, $on, $prog));
}

// The rules of the group of lines $lv in panel 'rows' (MEL_K_LIST_RULE high, colour line, from MEL_GC_PAD to as far
// from the right): folded, under the line of the group; unfolded, none under it, after its last row when rows of the
// main list follow (the frames D1, E1a, C1, E2a of the owner). On the top edge of the line under it, above the plate
// of the focus of that line.
/**
 * @param MelGcLv $lv
 * @return list<MelGcComp>
 */
function mel_gc_rules($lv)
{
    // One rule at most: none without the tier or unfolded with no row of the main list.
    if ($lv['t'] === 0 || ($lv['o'] && $lv['t'] === $lv['n']))
        return array();
    $j = mel_gc_line_of($lv, $lv['t']);
    return array(mel_gc_rect(mel_gc_at(MEL_GC_LW - 2 * MEL_GC_PAD, MEL_K_LIST_RULE, MEL_GC_PAD, $j * MEL_GC_ROW_H),
        MEL_K_C_LINE, 'rule0'));
}

// Share of the release of row $r (mel_row_rel) of list $st watched before (mel_wh_progress), or 0 - of a row that
// does not take the bar of its release too (mel_row_takes_bar).
/**
 * @param MelProgress $prog
 * @param MelRow $r
 * @param MelState $st
 * @return float|int
 */
function mel_gc_share($prog, $r, $st)
{
    $rel = mel_row_rel($r);
    return $rel !== '' && isset($prog[$rel]) && mel_row_takes_bar($st, $r) ? $prog[$rel] : 0;
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
// in $color, step $lh) from x MEL_GC_VX, the first on the centre of the capitals of the label.
/**
 * @param string $label
 * @param list<string> $lines
 * @param int $lh
 * @param string $color
 * @return MelKitItem
 */
function mel_gc_field($label, $lines, $lh, $color = MEL_K_C_TEXT1)
{
    $vw = MEL_GC_CW - MEL_GC_VX;
    $l = MelKit::text($label, MEL_K_PX_CAPTION, MEL_K_C_TEXT3, 0, 0, array('w' => MEL_GC_VX - MEL_K_GAP_XS));
    $v = MelKit::text($lines, MEL_K_PX_SECONDARY, $color, 0, 0,
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

// The fields of the card under the name, from $top (the top of their capitals), in the order of priority: "Сверка"
// of a row of the tier ($check, mel_tier_text, in amber; '' none), video, audio, voices (of the audio tracks and the
// name), subtitles, languages, trackers (without them the add-on, unless $addon_side: beside the poster), each up to
// 2 lines (the voices MEL_GC_VOICES_LINES), MEL_K_GAP_M apart; the last baseline at $bottom at most. Video and audio
// are always there (a dash), the others only with data. What does not fit: the last field by priority goes whole
// (trackers or add-on, then languages), then the subtitles lose lines; "Сверка", video, audio and voices are never
// left out so (their lines only on a card that could not hold them at all). -> list of the blocks, not yet stacked.
/**
 * @param MelRow $r
 * @param MelGcText $tt
 * @param bool $addon_side
 * @param int $top
 * @param int $bottom
 * @param string $check
 * @return list<MelKitItem>
 */
function mel_gc_fields($r, $tt, $addon_side, $top, $bottom, $check = '')
{
    $vs = mel_row_voices_struct($r);
    $alone = mel_voices_dub_first($vs);
    $b = $r['badges'];
    $video = array($r['resolution'], mel_badge_text($b['vis']), $b['ai'] ? 'AI' : '', mel_row_quality_text($r),
        implode(' ', $b['tags']));
    $fields = array(
        array('check', $check),
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
        $ess = in_array($f[0], array('check', 'video', 'audio', 'voices'), true);
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
            $it = mel_gc_field($tt[$f[0]], mel_gc_field_lines($f[0], $v, $max, $alone), $lh,
                $f[0] === 'check' ? MEL_K_C_WARN : MEL_K_C_TEXT1);
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
    $check = !empty($r['tier']) && is_array($r['tier_why']) ?
        mel_tier_text($r['tier_why'], (bool) $st['movie']['series'], $tt['lang']) : '';
    $blocks = array_merge($blocks, mel_gc_fields($r, $tt, $addon_side, $top + $blocks[0]['bottom'] - $blocks[0]['top'] +
        MEL_K_GAP_M, MEL_GC_CARD_BOTTOM - MEL_GC_CY - MEL_K_GAP_XS, $check));
    $under = MelKit::stack($blocks, 'm', $top, 'card');
    return array_merge($d, $under['defs']);
}

// "4 раздачи", "13 releases": $n rows of the tier.
/**
 * @param MelGcText $tt
 * @param int $n
 * @return string
 */
function mel_gc_tier_count($tt, $n)
{
    return "$n " . mel_gc_plural($tt['lang'], $n, 'gc_rel');
}

// Children of 'card' on the line of the group of lines $lv (mel_gc_lv) of list $st, beside the poster as the card of a
// release (MEL_K_GAP_M apart): "4 раздачи" in the place of the size (MEL_K_PX_PANEL_TITLE, text 1), "не подтверждены"
// in that of the status (MEL_K_PX_ROW, text 2), "ENTER — показать" (unfolded: "ENTER — свернуть"; MEL_K_PX_SECONDARY,
// text 2) - none when ENTER does nothing there (no row of the main list: the group always unfolded). Nothing under the
// poster.
/**
 * @param MelState $st
 * @param MelGcLv $lv
 * @return list<MelGcComp>
 */
function mel_gc_group_card($st, $lv)
{
    $tt = mel_gc_text(mel_gc_lang($st));
    $sx = mel_img($st['movie']['poster']) !== '' ? MEL_GC_PW + MEL_GC_SIDE_GAP : 0;
    $o = array('w' => MEL_GC_CW - $sx);
    $b = array(MelKit::text(mel_gc_tier_count($tt, $lv['t']), MEL_K_PX_PANEL_TITLE, MEL_K_C_TEXT1, $sx, 0, $o),
        MelKit::text($tt['tier_card'], MEL_K_PX_ROW, MEL_K_C_TEXT2, $sx, 0, $o));
    if ($lv['t'] < $lv['n'])
        $b[] = MelKit::text($lv['o'] ? $tt['tier_hide'] : $tt['tier_show'], MEL_K_PX_SECONDARY, MEL_K_C_TEXT2, $sx, 0,
            $o);
    $s = MelKit::stack($b, 'm', 0, 'side');
    return $s['defs'];
}

// Children of 'card' of line $j of lines $lv of list $st: of the group mel_gc_group_card, of a row mel_gc_card.
/**
 * @param MelState $st
 * @param MelGcLv $lv
 * @param int $j
 * @return list<MelGcComp>
 */
function mel_gc_line_card($st, $lv, $j)
{
    return mel_gc_on_group($lv, $j) ? mel_gc_group_card($st, $lv) : mel_gc_card($st, mel_gc_row_of($lv, $j));
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

// Line $j of lines $lv -> array(its place from 1 among the releases on the screen, their number): the lines but that
// of the group (the group folded: those of the main list). The counter (mel_gc_count_of) and the caption of INFO
// (mel_info_open).
/**
 * @param MelGcLv $lv
 * @param int $j
 * @return array{0: int, 1: int}
 */
function mel_gc_place($lv, $j)
{
    $k = $lv['t'] > 0 ? 1 : 0;
    return array($j - $k + 1, $lv['l'] - $k);
}

// The counter on line $j of lines $lv (mel_gc_count of mel_gc_place); on the line of the group none.
/**
 * @param MelGcLv $lv
 * @param int $j
 * @return list<MelGcComp>
 */
function mel_gc_count_of($lv, $j)
{
    if (mel_gc_on_group($lv, $j))
        return array();
    list($p, $n) = mel_gc_place($lv, $j);
    return mel_gc_count($p - 1, $n);
}

// The keys of the screen of the list of rid $rid: the cursor, ENTER, INFO, MENU "Действия" (mel_gc_menu, built for the
// row under the cursor), the colour keys of the sort (mel_gc_sort); $flip, an episode: left and right flip the episodes
// (mel_flip). By the rid only: the screen of a list gone has them too (mel_gc_menu).
/**
 * @param string $rid
 * @param bool $flip
 * @return array<string, MelAction>
 */
function mel_gc_keys($rid, $flip)
{
    $acts = array(
        GUI_EVENT_KEY_UP => mel_gc_act('gc_move', $rid, array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => mel_gc_act('gc_move', $rid, array('d' => 'down')),
        GUI_EVENT_KEY_P_PLUS => mel_gc_act('gc_move', $rid, array('d' => 'pgup')),
        GUI_EVENT_KEY_P_MINUS => mel_gc_act('gc_move', $rid, array('d' => 'pgdn')),
        GUI_EVENT_KEY_ENTER => mel_gc_act('gc_pick', $rid),
        GUI_EVENT_KEY_INFO => mel_gc_act('gc_info', $rid),
        GUI_EVENT_MENU_PLAYBACK_FINISH => mel_finish_act($rid),
        GUI_EVENT_KEY_POPUP_MENU => mel_gc_act('gc_menu', $rid));
    if ($flip)
    {
        $acts[GUI_EVENT_KEY_LEFT] = mel_gc_act('gc_flip', $rid, array('d' => 'prev'));
        $acts[GUI_EVENT_KEY_RIGHT] = mel_gc_act('gc_flip', $rid, array('d' => 'next'));
    }
    foreach (mel_gc_sort_keys() as $k => $key)
        $acts[$key[0]] = mel_gc_act('gc_sort', $rid, array('k' => $k));
    return $acts;
}

// The keys of list $st (mel_gc_keys) and the items of its hint line (MelKit::hint) with the cursor on line $sel of its
// lines (mel_gc_lv), one for both: «P+ P−» "Прокрутка" with more lines than the screen; an episode: left and right,
// "Серии"; MENU "Действия"; INFO "Сведения" not on the line of the group, where it does nothing.
// -> array(actions, hint items).
/**
 * @param MelState $st
 * @param int $sel
 * @return array{array<string, MelAction>, list<array{string, string}>}
 */
function mel_gc_screen($st, $sel)
{
    $tt = mel_gc_text(mel_gc_lang($st));
    $lv = mel_gc_lv($st);
    $hint = $lv['l'] > MEL_GC_VIS ? array(array('pp', $tt['page'])) : array();
    if ($st['e'] > 0)
        $hint[] = array('lr', $tt['flip']);
    $hint[] = array('k_icon_menu', $tt['actions']);
    if (!mel_gc_on_group($lv, $sel))
        $hint[] = array('k_key_info', $tt['details']);
    return array(mel_gc_keys($st['rid'], $st['e'] > 0), $hint);
}

// The record of the screen of the list of rid $rid under an overlay (MelOvlScreen, kit_ovl.php), of its full view too
// (mel_gc_folder_view): owner the rid, its action map $acts (mel_gc_screen) with the keys Fav 2, Fav 1 and search
// mel_mlv_keys adds (the close puts the map back whole by change_behaviour; no RETURN), no timer, its sel_state $sel.
/**
 * @param string $rid
 * @param array<string, MelAction> $acts
 * @param string|null $sel
 * @return MelOvlScreen
 */
function mel_gc_scr($rid, $acts, $sel)
{
    return array('owner' => $rid, 'map' => $acts + mel_mlv_key_acts("streams:$rid"), 'timer' => null, 'sel' => $sel);
}

// The record (mel_gc_scr) of live list $st as its view has it - the online list (lp_on: mel_lpv_screen), else the list
// of releases (mel_gc_screen) - with the cursor of the shell's sel_state $raw, else its last one (mel_lpv_pos,
// mel_gc_pos). $keep: that cursor kept as the last one of the list (Mel::$state) - the menus: the actions of their
// items come with no sel_state of the shell. The one place the record of a live list is made for what opens over it
// (a panel, a dialog, a wait); its full view makes its own with the view. -> array(the record, sel, top).
/**
 * @param MelState $st
 * @param mixed $raw
 * @param bool $keep
 * @return array{MelOvlScreen, int, int}
 */
function mel_gc_scr_live($st, $raw, $keep = false)
{
    if (!empty($st['lp_on']) && isset($st['lp']))
    {
        list($sel, $top) = mel_lpv_pos($st['lp'], $raw);
        if ($keep)
            Mel::$state['lp']['gc'] = array($sel, $top);
        list($acts) = mel_lpv_screen($st, mel_lpv_lines($st['lp']));
    }
    else
    {
        list($sel, $top) = mel_gc_pos($st, $raw);
        if ($keep)
            Mel::$state['gc'] = array($sel, $top);
        list($acts) = mel_gc_screen($st, $sel);
    }
    return array(mel_gc_scr($st['rid'], $acts, mel_gc_state($sel, $top)), $sel, $top);
}

// The record (mel_gc_scr) of the screen of a list gone (replaced, php_server restarted) by its rid $rid: the keys every
// list has, without those of an episode (they do nothing there) - of the online list when control $control of the
// input is its own (lp_) -, the sel_state $raw the shell sent.
/**
 * @param string $rid
 * @param string $control
 * @param mixed $raw
 * @return MelOvlScreen
 */
function mel_gc_scr_gone($rid, $control, $raw)
{
    return mel_gc_scr($rid, strpos($control, 'lp_') === 0 ? mel_lpv_keys($rid) : mel_gc_keys($rid, false),
        is_string($raw) ? $raw : null);
}

// The controls of the own inputs of the screen of a list (mel_gc_scr_in): the keys of its map, the actions of the
// items of its panels and of «Готово» of their check lists, of a button of its wait window (lp_wt_done) - of the list
// of releases (gc_) and of the online list (lp_). Each is one the screen sends: a control here that no map, no panel and no chain of them has would be a name
// gone, its reply still thought shown over the list.
/**
 * @return list<string>
 */
function mel_gc_own()
{
    return array('gc_move', 'gc_pick', 'gc_info', 'gc_menu', 'gc_flip', 'gc_sort', 'gc_refresh', 'gc_main', 'gc_src',
        'gc_choose', 'gc_dl', 'gc_push', 'gc_ts', 'lp_move', 'lp_pick', 'lp_menu', 'lp_flt_menu', 'lp_flt_done', 'lp_fold',
        'lp_qsort', 'lp_flip', 'lp_choose', 'lp_refresh', 'lp_srcs_done', 'lp_wt_done');
}

// The record of the screen (mel_gc_scr) of the list of releases or the online list whose own input $in of control
// $control is: a key of its map or the action of an item of its panel (it comes after the close) - the reply to it is
// shown over the list itself (mel_scr_of, main.php). null: no rid, or an input not of the screen - of a window of the
// shell over it (its ticks and buttons: ts_tick, lp_tick, lp_cancel, lp_find, pre_via), of the player (finish, next,
// lp_next), of the screen of the seasons (gc_chosen, lp_chosen). A live list: mel_gc_scr_live, its cursor not kept
// (nothing follows a dialog but its close); a list gone: mel_gc_scr_gone.
/**
 * @param stdClass $in
 * @param string $control
 * @return MelOvlScreen|null
 */
function mel_gc_scr_in($in, $control)
{
    $rid = isset($in->rid) && is_string($in->rid) ? $in->rid : '';
    if ($rid === '' || !in_array($control, mel_gc_own(), true))
        return null;
    $raw = isset($in->parent_sel_state) ? $in->parent_sel_state : null;
    $st = Mel::$state;
    if (!$st || $st['rid'] !== $rid)
        return mel_gc_scr_gone($rid, $control, $raw);
    list($scr) = mel_gc_scr_live($st, $raw);
    return $scr;
}

// Panel 'hint' of the hint line of list $st (mel_gc_screen; from MEL_GC_LX on MEL_GC_HINT_MID) with the cursor on line
// $j: at 0, 0, its children in the coordinates of the screen; a step that comes to the group or leaves it replaces them
// (mel_gc_input).
/**
 * @param MelState $st
 * @param int $j
 * @return list<MelGcComp>
 */
function mel_gc_hint_defs($st, $j)
{
    list(, $items) = mel_gc_screen($st, $j);
    $h = MelKit::hint($items, MEL_GC_LX, MEL_GC_HINT_MID);
    return $h['defs'];
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
// amber (mel_errors_shown; the online list has none, its MENU has them). -> array(its children, the right edge of
// what is left of it: $right with no number).
/**
 * @param MelState $st
 * @param MelGcText $tt
 * @param int $right
 * @return array{list<MelGcComp>, int}
 */
function mel_gc_tabs_errors($st, $tt, $right)
{
    if (!mel_errors_shown($st['errors']))
        return array(array(), $right);
    $text = str_replace('{n}', strval(mel_errors_total($st['errors'])), $tt['errors']);
    $w = MelKit::w($text, MEL_K_PX_SECONDARY);
    $e = MelKit::text($text, MEL_K_PX_SECONDARY, MEL_K_C_WARN, $right - $w, MEL_GC_TABS_MID);
    return array($e['defs'], $right - $w - MEL_K_HINT_GAP);
}

// Right of the row of the tabs, right-aligned to $right on MEL_GC_TABS_MID (left of the number of the errors,
// mel_gc_tabs_errors): the name of the source of the list after its icon (two ready profiles or more, sources.php:
// mel_src_name), in what the tabs leave from $left (less than a few letters: no name).
/**
 * @param MelState $st
 * @param int $left
 * @param int $right
 * @return list<MelGcComp>
 */
function mel_gc_tabs_from($st, $left, $right)
{
    $from = mel_src_in_menu() ? mel_src_name(mel_src_now($st)) : '';
    $room = min(300, $right - $left);
    if ($from === '' || $room < 80)
        return array();
    $i = MelKit::icon('k_icon_layers', MEL_K_ICON_SORT, MEL_K_C_TEXT3, 0, 0, array('box' => true));
    $t = MelKit::text($from, MEL_K_PX_ROW, MEL_K_C_TEXT2, 0, 0,
        array('w' => $room - ($i['x1'] - $i['x0']) - MEL_K_KEY_DOT_GAP));
    $row = MelKit::row(array($i, $t), MEL_K_KEY_DOT_GAP, 0, MEL_GC_TABS_MID, 'from');
    $row = MelKit::move($row, $right - $row['x1'], 0);
    return $row['defs'];
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
    $lv = mel_gc_lv($st);
    $n = $lv['l'];
    $tt = mel_gc_text(mel_gc_lang($st));
    $cols = mel_gc_cols_st($st);
    $d = array();
    $d[] = mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), MEL_K_C_SCRIM);
    $poster = mel_img($mv['poster']);
    if ($poster !== '')
        $d[] = mel_gc_image(mel_gc_at(MEL_GC_PW, MEL_GC_PH, MEL_GC_CX, MEL_GC_CY), $poster, true);
    $d[] = mel_gc_panel('card', mel_gc_at(MEL_GC_CW, MEL_GC_CARD_BOTTOM - MEL_GC_CY, MEL_GC_CX, MEL_GC_CY),
        mel_gc_line_card($st, $lv, $sel));

    // The yellow line of "Онлайн" (view_online.php) in the row of the title.
    $d = array_merge($d, mel_gc_head($st, mel_lpv_note($st)));

    // Viewport: clipping panel 'list'; the inner panel 'rows' (as high as all the lines) moves to scroll, the lines of
    // the window (mel_gc_rows_span) in it at their places, the rules of the group after them.
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    $in = array(mel_gc_sel($sel));
    list($lo, $hi) = mel_gc_rows_span($top, $n);
    for ($j = $lo; $j < $hi; $j++)
        $in[] = mel_gc_line_comp($st, $lv, $j, $tt, $cols, $j === $sel, $prog);
    $in = array_merge($in, mel_gc_rules($lv));
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
    $d[] = mel_gc_panel('hint', mel_gc_at(1920, 1080, 0, 0), mel_gc_hint_defs($st, $sel));
    $right = MEL_GC_LX + MEL_GC_LW - MEL_GC_PAD;
    $d[] = mel_gc_panel('counter', mel_gc_at(MEL_GC_COUNT_W, MEL_K_KEY_H, $right - MEL_GC_COUNT_W,
        MEL_GC_HINT_MID - intval(MEL_K_KEY_H / 2)), mel_gc_count_of($lv, $sel));
    $left = MEL_GC_LX;
    if (mel_gc_color_keys())
    {
        $tabs = mel_gc_sort_tabs($st, $tt);
        $d = array_merge($d, $tabs['defs']);
        $left = $tabs['x1'] + MEL_K_HINT_GAP;
    }
    // Outside the tabs: a remote without the colour keys has them too.
    list($errs, $right) = mel_gc_tabs_errors($st, $tt, $right);
    $d = array_merge($d, $errs, mel_gc_tabs_from($st, $left, $right));
    // The slot of the overlay, the last: "Действия" (mel_gc_menu) opens in it.
    $d[] = mel_ovl_slot();

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
// event - one shell log, unverified); the rows of release $hash (mel_row_rel; one
// may come in several rows: RD and TorBox; mel_row_takes_bar) redrawn when its share changed,
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
        if ($hash !== '' && mel_row_rel($r) === $hash && mel_row_takes_bar($st, $r))
            $rows[] = $k;
    }
    if (!$rows)
    {
        mel_log('finish: progress: the release played has no key or is not in the list');
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
    // Only the rows on the screen (mel_gc_rows_span; not those of the group folded); the others get the share when the
    // window comes to them.
    $lv = mel_gc_lv($st);
    list($lo, $hi) = mel_gc_rows_span($top, $lv['l']);
    $ch = array();
    foreach ($rows as $i)
    {
        $j = mel_gc_line_of($lv, $i);
        if ($j >= $lo && $j < $hi && mel_gc_row_of($lv, $j) === $i)
            $ch[] = mel_gc_change("r$j", null, GCOMP_TRANSITION_NONE,
                mel_gc_row_items($st['rows'][$i], $tt, $cols, $j === $sel, $now));
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
    // A view anew under an open panel: its state is not that of this view (the close would put back the old map).
    mel_ovl_drop($st['rid']);
    list($acts) = mel_gc_screen($st, $sel);
    $scr = mel_gc_scr($st['rid'], $acts, mel_gc_state($sel, $top));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_gc_window($st, $sel, $top),
            PluginGCompsFolderView::sel_state => $scr['sel'],
            PluginGCompsFolderView::actions => $scr['map'],
            PluginGCompsFolderView::timer => $scr['timer']));
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
// rid), the group of the tier folded or not as it was, the cursor on the line a list opens on (mel_gc_first). A bad
// key, a list not live: nothing.
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
    return mel_list_replace(mel_gc_put_cursor($ns, -1, $st));
}

// The details of a release in MENU of a list too (0.61.0; of the online list its colour keys): "Пульт без цветных
// кнопок" of the settings, or a remote the firmware knows is without the colour keys (no sort tabs then). The sort is
// in MENU of the list of releases always.
/**
 * @return bool
 */
function mel_gc_keys_in_menu()
{
    return Mel::$settings['no_color_keys'] || !mel_gc_color_keys();
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

// gc_main (k: an id of mel_debrid_mains, a line of the menu "Main Debrid", mel_gc_main_menu) of a live
// list: k the main Debrid at once (mel_debrid_main_write), the copies of k on
// top, the screen replaced (a new rid), the cursor kept. Not written (the next
// operation reads the file again: the old one would play), a bad id, a list
// not live, k the main one of the list already (mel_gc_main_pair): nothing.
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
    // The main one of the list chosen in its menu again (mel_gc_main_menu).
    $pair = mel_gc_main_pair($st);
    if ($pair && $pair[0] === $k)
    {
        mel_log("debrid main: $k is the main one already, nothing");
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
    $cur = isset($st['gc']) ? mel_gc_row_of(mel_gc_lv($st), $st['gc'][0]) : -1;
    return mel_list_replace(mel_gc_put_cursor($ns, $cur, $st));
}

// --- "Действия" of the list: the side panel of the kit (kit_panel.php) over it, opened by MENU.

// "Выбрать серию" of MENU of list $st (the releases and the online list): an episode, with a card of the Dune (without
// one the choice would end in "illegal state" of the shell), not opened from the own card (the seasons of the last
// native series would come; chosen there).
/**
 * @param MelState $st
 * @return bool
 */
function mel_gc_can_choose($st)
{
    return $st['e'] > 0 && $st['movie']['dune_id'] !== '' && empty($st['movie']['own_card']);
}

// The submenu "Сортировка" of the panel of list $st: the sorts in the order of the colour keys, each gc_sort as its key
// (the sort of the list again: the other way round, said under the lines); the sort of the list picked, with its
// direction (down by default, up reversed, as its tab).
/**
 * @param MelState $st
 * @param MelGcText $tt
 * @return MelKpSubDesc
 */
function mel_gc_sort_menu($st, $tt)
{
    $items = array();
    foreach (mel_sort_keys() as $k)
    {
        $it = array('id' => "sort_$k", 'label' => $tt[mel_gc_sort_word($st, $k)], 'picked' => $k === $st['sort'],
            'act' => mel_gc_act('gc_sort', $st['rid'], array('k' => $k)));
        if ($k === $st['sort'])
            $it['way'] = $st['sort_rev'] ? 'k_icon_arrow-up' : 'k_icon_arrow-down';
        $items[] = $it;
    }
    return array('title' => mel_tr($st['lang'], 'sort_menu'), 'groups' => array($items),
        'note' => mel_tr($st['lang'], 'sort_note'));
}

// The submenu "Основной Debrid" of the panel of list $st with main Debrid $now (mel_gc_main_pair): the services of
// mel_debrid_mains, each gc_main with its id, the main one picked (chosen again: nothing, mel_gc_main).
/**
 * @param MelState $st
 * @param string $now
 * @return MelKpSubDesc
 */
function mel_gc_main_menu($st, $now)
{
    $items = array();
    foreach (array_keys(mel_debrid_mains()) as $k)
        $items[] = array('id' => "main_$k", 'label' => mel_service_name($k), 'picked' => $k === $now,
            'act' => mel_gc_act('gc_main', $st['rid'], array('k' => $k)));
    return array('title' => mel_tr($st['lang'], 'main_title'), 'groups' => array($items));
}

// The panel "Действия" of live list $st with the cursor on row $row of its rows (-1: the line of the group of the
// tier, no item of a release), three groups:
//   of the release under the cursor: with mel_gc_keys_in_menu "Сведения о раздаче" (as INFO); a row with infoHash, not
//     with "no torrents": "Смотреть через TorrServer" (mel_ts_menu), "Открыть в приложении" (mel_download) and, with a
//     server in the settings, "Скачать на сервер" (a submenu, mel_srv_panel); 'i' of those is the row;
//   of the list: "Сортировка" (always; a submenu, mel_gc_sort_menu) and "Источник" (two ready profiles, of a card of
//     no IMDb and no TMDB, mel_rel_id, those of Lampac; mel_src_item) with their value on the right, "Обновить
//     список" (mel_refresh), an episode: "Выбрать серию" (mel_choose), copies of Real-Debrid and TorBox in the list:
//     "Сменить основной Debrid" (a submenu, mel_gc_main_menu), source errors of the reply if shown (a submenu, mel_errors_panel);
//   "Настройки Melange".
/**
 * @param MelState $st
 * @param int $row
 * @return MelKpDesc
 */
function mel_gc_actions($st, $row)
{
    $lang = $st['lang'];
    $rid = $st['rid'];
    $tt = mel_gc_text(mel_gc_lang($st));
    $i = array('i' => strval($row));
    $one = array();
    if ($row >= 0 && mel_gc_keys_in_menu())
        $one[] = array('id' => 'info', 'label' => mel_tr($lang, 'info_menu'), 'icon' => 'k_icon_info',
            'act' => mel_gc_act('gc_info', $rid, $i));
    if (isset($st['rows'][$row]) && $st['rows'][$row]['hash'] !== '' && !Mel::$settings['no_torrents'])
    {
        $one[] = array('id' => 'ts', 'label' => mel_tr($lang, 'ts_menu'), 'icon' => 'k_icon_monitor-play',
            'act' => mel_gc_act('gc_ts', $rid, $i));
        $one[] = array('id' => 'app', 'label' => mel_tr($lang, 'dl_menu'), 'icon' => 'k_icon_external-link',
            'act' => mel_gc_act('gc_dl', $rid, $i));
        $srv = mel_srv_panel($st, $row);
        if ($srv)
            $one[] = array('id' => 'srv', 'label' => mel_tr($lang, 'srv_menu'), 'icon' => 'k_icon_hard-drive',
                'menu' => $srv);
    }
    $list = array(array('id' => 'sort', 'label' => mel_tr($lang, 'sort_menu'), 'icon' => 'k_icon_arrow-up-down',
        'value' => $tt[mel_gc_sort_word($st, $st['sort'])], 'menu' => mel_gc_sort_menu($st, $tt)));
    $src = mel_src_item($lang, 'gc_src', array('rid' => $rid), mel_src_now($st), mel_rel_id($st['movie']) === '');
    if ($src)
        $list[] = $src;
    $list[] = array('id' => 'refresh', 'label' => mel_tr($lang, 'refresh_list'), 'icon' => 'k_icon_refresh-cw',
        'act' => mel_gc_act('gc_refresh', $rid));
    if (mel_gc_can_choose($st))
        $list[] = array('id' => 'choose', 'label' => mel_tr($lang, 'choose_menu'), 'icon' => 'k_icon_list-video',
            'act' => mel_gc_act('gc_choose', $rid));
    $main = mel_gc_main_pair($st);
    if ($main)
        $list[] = array('id' => 'main', 'label' => mel_tr($lang, 'main_change_menu'), 'icon' => 'k_icon_zap',
            'menu' => mel_gc_main_menu($st, $main[0]));
    if (mel_errors_shown($st['errors']))
        $list[] = array('id' => 'errors', 'label' => mel_tr($lang, 'errors_menu'), 'icon' => 'k_icon_triangle-alert',
            'menu' => mel_errors_panel(mel_tr($lang, 'errors_menu'), $lang, $st['errors']));
    $groups = $one ? array($one, $list) : array($list);
    $groups[] = array(mel_setup_item($lang));
    return array('title' => $tt['actions'], 'groups' => $groups);
}

// gc_menu (MENU) of a list -> the panel "Действия" over its screen (mel_kp_open; mel_gc_actions), built for the row
// under the cursor, the cursor kept as the last one of the list (the actions of the items come with no sel_state of
// the shell). A screen of a lost list (replaced, php_server restarted): "Настройки Melange" only, in the language of
// the live list, else of the shell; the record of that screen by its rid (mel_gc_scr_gone). No rid: nothing.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_gc_menu($in)
{
    $st = Mel::$state;
    $raw = isset($in->parent_sel_state) ? $in->parent_sel_state : null;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('menu: list outdated, settings only');
        $lang = $st ? $st['lang'] : mel_catv_lang();
        $rid = isset($in->rid) && is_string($in->rid) ? $in->rid : '';
        $tt = mel_gc_text($lang === 'ru' ? 'ru' : 'en');
        return mel_kp_open(mel_gc_scr_gone($rid, 'gc_menu', $raw),
            array('title' => $tt['actions'], 'groups' => array(array(mel_setup_item($lang)))));
    }
    list($scr, $sel) = mel_gc_scr_live($st, $raw, true);
    // The items of a release take its row; on the line of the group (-1) none of them.
    return mel_kp_open($scr, mel_gc_actions($st, mel_gc_row_of(mel_gc_lv($st), $sel)));
}

// ENTER on the line of the group of the tier of list $st (lines $lv): folded - unfolded, the cursor on its first row;
// unfolded - folded, the cursor on its line. With no row of the main list it is always unfolded: nothing. The screen
// replaced from the state (a new rid), no request, as a balancer of the online list (mel_lpv_fold).
/**
 * @param MelState $st
 * @param MelGcLv $lv
 * @return MelAction|null
 */
function mel_gc_fold($st, $lv)
{
    if ($lv['t'] === $lv['n'])
        return null;
    $ns = $st;
    $ns['rid'] = mel_rid();
    $ns['tier_open'] = !$lv['o'];
    // Folded: the line of the group; unfolded: that of its first row.
    $ns['gc'] = array(mel_gc_line_of(mel_gc_lv($ns), 0), 0);
    $ns['gc_new'] = true;
    mel_log('tier: ' . ($lv['o'] ? 'folded' : 'unfolded') . ", {$lv['t']} rows, screen replaced");
    return mel_list_replace($ns);
}

// gc_move -> change_gcomps (null at an edge); gc_pick -> playback; gc_info ->
// the details screen (back on this one, mel_gc_pos finds the line in 'gc'); from MENU
// (mel_gc_menu) gc_info has the row in 'i', a row not in the list: outdated. All by the lines of the screen
// (mel_gc_lv): a release by its row of $st['rows']; on the line of the group ENTER folds or unfolds it (mel_gc_fold),
// INFO nothing. Under the open overlay of the list no key of its map comes here (the gate, kit_ovl.php).
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
        $lv = mel_gc_lv($st);
        list($sel, $top) = mel_gc_pos($st, $raw);
        if ($in->control_id === 'gc_info' && isset($in->i))
        {
            $i = is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
            if (!isset($st['rows'][$i]))
            {
                mel_log('info: menu of no live row');
                return mel_error('err_list_expired');
            }
            if ($i !== mel_gc_row_of($lv, $sel))
                list($sel, $top) = mel_gc_cursor(mel_gc_line_of($lv, $i), $lv['l']);
        }
        Mel::$state['gc'] = array($sel, $top);
        if (mel_gc_on_group($lv, $sel))
            return $in->control_id === 'gc_pick' ? mel_gc_fold($st, $lv) : null;
        $row = mel_gc_row_of($lv, $sel);
        if ($in->control_id === 'gc_info')
            return mel_info_open($st, $row);
        // A new start: the main Debrid again, not one of "Via <service>" before.
        Mel::$alt = null;
        Mel::$via = null;
        // A P2P stream (no URL, infoHash only): through TorrServer.
        if (mel_is_p2p($st['rows'][$row]))
        {
            list($scr) = mel_gc_scr_live(Mel::$state, $raw);
            return mel_ts_open(Mel::$state, $row, 0, $st['rid'], $scr);
        }
        $a = mel_play($st, $st['rows'][$row], 0, null, false, null, $st['rid']);
        if ($a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
            mel_playing($st['rid'], Mel::$state, $row);
        return $a;
    }

    $key = mel_gc_key($in);
    if (!$live || $key === null)
        return null;
    $lv = mel_gc_lv($st);
    $n = $lv['l'];
    list($sel, $top) = mel_gc_pos($st, $raw);
    list($nsel, $ntop) = mel_gc_step($sel, $top, $n, $key);
    Mel::$state['gc'] = array($nsel, $ntop);
    if ($nsel === $sel)
        return null;

    $tt = mel_gc_text(mel_gc_lang($st));
    $cols = mel_gc_cols_st($st);
    $prog = isset($st['gc_prog']) ? $st['gc_prog'] : array();
    // The window of lines follows the top (mel_gc_cursor_changes).
    $ch = mel_gc_cursor_changes($top, $nsel, $ntop, $n, function ($j) use ($st, $lv, $tt, $cols, $prog)
    {
        return mel_gc_line_comp($st, $lv, $j, $tt, $cols, false, $prog);
    });
    // The focus brightness moves with the line.
    $ch[] = mel_gc_change("r$sel", null, GCOMP_TRANSITION_NONE,
        mel_gc_line_items($st, $lv, $sel, $tt, $cols, false, $prog));
    $ch[] = mel_gc_change("r$nsel", null, GCOMP_TRANSITION_NONE,
        mel_gc_line_items($st, $lv, $nsel, $tt, $cols, true, $prog));
    $ch[] = mel_gc_change('card', null, GCOMP_TRANSITION_NONE, mel_gc_line_card($st, $lv, $nsel));
    $ch[] = mel_gc_change('counter', null, GCOMP_TRANSITION_NONE, mel_gc_count_of($lv, $nsel));
    // To the group or off it: "Сведения" goes or comes back.
    if (mel_gc_on_group($lv, $nsel) !== mel_gc_on_group($lv, $sel))
        $ch[] = mel_gc_change('hint', null, GCOMP_TRANSITION_NONE, mel_gc_hint_defs($st, $nsel));
    return mel_gc_changes_act($ch, $nsel, $ntop);
}
