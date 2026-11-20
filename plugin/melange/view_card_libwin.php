<?php
// The window of the copies on the disk W2 over the own card (spec §8.1-§8.3): its open over the screen of the card
// (mel_lib_win_open, "· сейчас" mel_lib_win_cur; of "Continue" of the native over the own card it opens by
// mel_lib_win_card of deep_link.php), its components (mel_lib_win_defs), its keys (mel_lib_win_input), the paths of
// its lines (mel_lib_win_paths: P1 by mel_lib_tails, mel_lib_common_len; mel_lib_cut_mid). Split out of
// view_card_lib.php (the line "С диска" and the plates of a copy there).
// Needs view_card_lib.php (mel_lib_copy_parts, mel_lib_plate_ids, mel_oc_lib_plates, mel_oc_lib_cur, mel_oc_se,
// MEL_OC_LIB_GAP), view_card.php (MelOc, mel_oc_scr), view_card_series.php (mel_oc_ep, mel_oc_prog), kit.php,
// kit_ovl.php, gcomps.php, playback_lib.php (mel_lib_place, mel_lib_ep_cid, mel_lib_ep_ctx), continue.php (mel_cp_badge),
// view_catalog.php (mel_catv_ui_lang), main.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// The path of a line of the window of the copies (W2, spec §8.2): its text WIN_PATH_PX, its width WIN_PATH_W at most.
define('MEL_LIB_WIN_PATH_PX', MEL_K_PX_CAPTION);
define('MEL_LIB_WIN_PATH_W', 1000);

// The length of the common start of two paths, whole parts only (PathUtils::get_common_path_prefix_len of the shell).
/**
 * @param string $a
 * @param string $b
 * @return int
 */
function mel_lib_prefix_len($a, $b)
{
    $la = strlen($a);
    $lb = strlen($b);
    if ($la === 0 || $lb === 0 || $a[0] !== $b[0])
        return 0;
    $n = $a[0] === '/' ? 1 : 0;
    for (;;)
    {
        $na = strpos($a, '/', $n);
        $nb = strpos($b, '/', $n);
        if ($na === false || $na !== $nb || substr($a, $n, $na - $n) !== substr($b, $n, $nb - $n) ||
            $na + 1 >= $la || $na + 1 >= $lb)
            break;
        $n = $na + 1;
    }
    return $n;
}

// The length of the start of the paths the screen "Files" of the shell cuts off (PathUtils::get_common_file_prefix_len):
// their common start; of one path, all but its file and its folder.
/**
 * @param list<string> $paths
 * @return int
 */
function mel_lib_common_len($paths)
{
    if (!$paths)
        return 0;
    if (count($paths) === 1)
    {
        $p = $paths[0];
        $pos = strrpos($p, '/');
        if (!$pos)
            return 0;
        $pos = strrpos($p, '/', -(strlen($p) - $pos + 1));
        return $pos === false ? 0 : $pos + 1;
    }
    $n = mel_lib_prefix_len($paths[0], $paths[1]);
    for ($i = 2; $i < count($paths); $i++)
        $n = min($n, mel_lib_prefix_len($paths[0], $paths[$i]));
    return $n;
}

// The tails of the paths of copies $copies by their cid (P1, as the screen "Files" of the shell shows them): of a movie
// its path, of an episode ($series) the folder of its pack (dir) - the copies in one folder of the library without the
// start of the folders common to them all (mel_lib_common_len), in several "<caption of its folder>/<path>"; nothing
// left (an episode at the root of its folder of the library): the caption alone.
/**
 * @param list<MelLibCopy> $copies
 * @param bool $series
 * @return array<string, string>
 */
function mel_lib_tails($copies, $series)
{
    $folders = array();
    $paths = array();
    foreach ($copies as $c)
    {
        $folders[$c['folder']] = 1;
        $p = $series ? $c['dir'] : $c['path'];
        if (!in_array($p, $paths, true))
            $paths[] = $p;
    }
    $n = count($folders) > 1 ? -1 : mel_lib_common_len($paths);
    $out = array();
    foreach ($copies as $c)
    {
        $p = $series ? $c['dir'] : $c['path'];
        $t = $n >= 0 ? strval(substr($p, $n)) : ($p === '' ? '' : $c['caption'] . '/' . $p);
        $out[$c['cid']] = $t === '' ? $c['caption'] : $t;
    }
    return $out;
}

// The paths of the lines of the window of copies $copies (of the episodes of a series: $series) by cid: their tails
// (mel_lib_tails) cut in the middle to the width of the window (mel_lib_cut_mid).
/**
 * @param list<MelLibCopy> $copies
 * @param bool $series
 * @return array<string, string>
 */
function mel_lib_win_paths($copies, $series)
{
    $out = array();
    foreach (mel_lib_tails($copies, $series) as $cid => $t)
        $out[$cid] = mel_lib_cut_mid($t, MEL_LIB_WIN_PATH_PX, MEL_LIB_WIN_PATH_W);
    return $out;
}

// Path $s at $px (the font of the kit, MelKit::w) wider than $w cut in the middle: its start up to the first "/" (its
// folder) kept, "…", then of its end as much as fits (mock_library_v4.py cut_mid); no "/" - "…" and the end. A start
// to the first "/" wider than half of $w (with "…"): no start kept by "/" - of the start as much as fits half of $w, "…",
// of the end the rest. Never wider than $w. Not wider: as it is.
/**
 * @param string $s
 * @param int $px
 * @param int $w
 * @return string
 */
function mel_lib_cut_mid($s, $px, $w)
{
    $s = preg_match('//u', $s) ? $s : mel_utf8($s);
    if (MelKit::w($s, $px) <= $w)
        return $s;
    $i = strpos($s, '/');
    $head = $i === false ? '' : substr($s, 0, $i + 1);
    // The width of a line is the sum of those of its glyphs (MelKit::w): the start grows from its first one, the end
    // from its last one.
    if (MelKit::w($head . '…', $px) * 2 > $w)
    {
        $half = intval(($w - MelKit::w('…', $px)) / 2);
        $head = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: array() as $ch)
        {
            $cw = MelKit::w($ch, $px);
            if ($cw > $half)
                break;
            $half -= $cw;
            $head .= $ch;
        }
    }
    $left = $w - MelKit::w($head . '…', $px);
    $chars = preg_split('//u', strval(substr($s, strlen($head))), -1, PREG_SPLIT_NO_EMPTY) ?: array();
    $tail = '';
    for ($k = count($chars) - 1; $k >= 0; $k--)
    {
        $cw = MelKit::w($chars[$k], $px);
        if ($cw > $left)
            break;
        $left -= $cw;
        $tail = $chars[$k] . $tail;
    }
    return $head . '…' . $tail;
}

// --- The window of the copies W2 (spec §8.1, §8.3; frames v8_W2_*, v9_W2_plate.png of the kitchen, geometry
// mock_library_v4.py window(), mode P1) over the own card, in the overlay of its screen (kit_ovl.php): the shade, the
// window WIN_W wide at WIN_X, its height by its lines (at most WIN_VIS shown), WIN_UP over the middle of the screen; the
// title (lib_win_title, MEL_K_PX_STATUS_TITLE, text 1) its baseline WIN_TITLE_BASE under the top; the lines WIN_ROW high
// from WIN_HEAD, each of a copy: its plates b2 (mel_lib_copy_parts, mel_lib_plate_ids: the plate of the channels after
// the audio; MEL_K_PLATE_GAP apart, not dimmed), "· сейчас" of the current one (lib_win_now) WIN_GAP after them and the
// size on the right, MEL_K_PX_ROW, text 2 (owner 20.11.2026: 30 px); under them the path P1 (mel_lib_win_paths,
// MEL_LIB_WIN_PATH_PX, text 3) its baseline WIN_PATH_BASE under the top of the line; the focus - the plate row_on
// (WIN_ON_X in from the sides, WIN_ROW - WIN_ON_GAP high), its texts on_accent; under the line of the current one the
// bar of its progress (WIN_BAR_X in, WIN_BAR_H high, WIN_BAR_Y under the top of the line); the hint under the lines
// (MEL_K_PX_CAPTION, text 3) its baseline WIN_FOOT_BASE under them, the window WIN_FOOT lower. All from WIN_PAD in.
define('MEL_LIB_WIN_X', 420);
define('MEL_LIB_WIN_W', 1080);
define('MEL_LIB_WIN_UP', 40);
define('MEL_LIB_WIN_PAD', 40);
define('MEL_LIB_WIN_HEAD', 88);
define('MEL_LIB_WIN_TITLE_BASE', 58);
define('MEL_LIB_WIN_ROW', 116);
define('MEL_LIB_WIN_VIS', 5);
define('MEL_LIB_WIN_LINE_Y', 41);
define('MEL_LIB_WIN_PATH_BASE', 88);
define('MEL_LIB_WIN_GAP', MEL_OC_LIB_GAP - MEL_K_PLATE_PAD);
define('MEL_LIB_WIN_ON_X', 16);
define('MEL_LIB_WIN_ON_GAP', 8);
define('MEL_LIB_WIN_BAR_X', 28);
define('MEL_LIB_WIN_BAR_Y', 102);
define('MEL_LIB_WIN_BAR_H', 4);
define('MEL_LIB_WIN_FOOT', 70);
define('MEL_LIB_WIN_FOOT_BASE', 44);
// The prefix of the ids of the window: lw_dim, lw_win, lw_list, lw_rows, lw_r<i> (a line).
define('MEL_LIB_WIN', 'lw');

// The copy "· сейчас" of the window of the copies $copies of card $key (spec §8.3): of a movie the copy of the latest
// record of a copy of the card (mel_oc_lib_cur); of episode $s, $e the copy OK plays (решение лида 5 (а): of the folder
// it plays from) - the rule of OK of its copies on the screen (mel_lib_ep_cid of mel_lib_ep_ctx, no $auto), not lib_cur
// (the best of $auto): OK of no copy to take gives the window (the series never watched from the disk, no record and
// no F to go by) - none then. Not among $copies, no card shown: '' (none; the cursor on the first line).
/**
 * @param string $key
 * @param list<MelLibCopy> $copies
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_lib_win_cur($key, $copies, $s = -1, $e = -1)
{
    if (!isset(MelOc::$cards[$key]['nav']))
        return '';
    $l = MelOc::$cards[$key]['nav'];
    if ($e < 1)
    {
        $cp = mel_oc_lib_cur($copies, $l['hid']);
        return $cp ? $cp['cid'] : '';
    }
    $se = mel_oc_se($s, $e);
    list($cid) = mel_lib_ep_cid(isset($l['lib_eps'][$se]) ? $l['lib_eps'][$se] : $copies,
        mel_lib_ep_ctx($l['hid'], $l['lib_y']), $se, false);
    foreach ($copies as $cp)
    {
        if ($cp['cid'] === $cid)
            return $cid;
    }
    return '';
}

// Open over the screen of card $key (its record mel_oc_scr: only so) the window of the copies $copies (2+, the best
// first) of its movie, of episode $s, $e of its series ($e >= 1), in mode $mode (first: the first "Смотреть"; menu: MENU
// "Копия на диске…"); the copy "· сейчас" by mel_lib_win_cur ($cur null; a cid or '' none - only the tests of the
// scroll give it): the cursor on it, else on the first line. A window to open by the first tick (lw_auto of "Continue",
// mel_lib_win_card) is off then: this one is it, it does not come again after its RETURN or the player.
// The bar of the line "сейчас" and the hint: of an episode the progress of its still (nav prog, mel_oc_prog), the hint
// lib_win_series; of a movie the progress of the line of its history (mel_cp_badge, as mel_oc_hist), the hint of mode
// first lib_win_first, of menu the place the start of a copy takes (mel_lib_place, as mel_oc_lib_start): "Продолжит с
// того же места — 0:47:28" (lib_win_place), none (not begun or watched) lib_win_first. No record of the screen: null.
/**
 * @param string $key
 * @param list<MelLibCopy> $copies
 * @param 'first'|'menu' $mode
 * @param int $s
 * @param int $e
 * @param string|null $cur
 * @return MelAction|null
 */
function mel_lib_win_open($key, $copies, $mode, $s = -1, $e = -1, $cur = null)
{
    $scr = mel_oc_scr($key);
    if (!$scr || !$copies || !isset(MelOc::$cards[$key]['nav']))
    {
        mel_log("card: own $key: the window of the copies not opened (" . (!$scr ? 'no screen' : 'no copies') . ')');
        return null;
    }
    unset(MelOc::$cards[$key]['lw_auto']);
    if ($cur === null)
        $cur = mel_lib_win_cur($key, $copies, $s, $e);
    $c = MelOc::$cards[$key];
    $l = $c['nav'];
    $at = 0;
    foreach ($copies as $i => $cp)
    {
        if ($cp['cid'] === $cur)
            $at = $i;
    }
    $share = 0.0;
    $foot = mel_tr($c['ui'], 'lib_win_first');
    if ($e >= 1)
    {
        $x = mel_oc_ep($c, $s, $e);
        $pr = $x ? mel_oc_prog($c, $l['prog'], $x) : null;
        if ($pr && !$pr[2] && $pr[1] > 0)
            $share = min(1.0, $pr[0] / $pr[1]);
        $foot = mel_tr($c['ui'], 'lib_win_series');
    }
    else
    {
        $b = mel_cp_badge($l['hid'], 'movie');
        if ($b && !$b['watched'] && !$b['next'] && $b['e'] < 0 && $b['len'] > 0)
            $share = min(1.0, $b['pos'] / $b['len']);
        if ($mode === 'menu')
        {
            list(, , $pos) = mel_lib_place($l['hid'], $copies, '');
            if ($pos > 0)
                $foot = str_replace('{time}', sprintf('%d:%02d:%02d', $pos / 3600, $pos / 60 % 60, $pos % 60),
                    mel_tr($c['ui'], 'lib_win_place'));
        }
    }
    $lw = array('copies' => $copies, 'at' => $at, 'mode' => $mode, 's' => $e >= 1 ? $s : -1, 'e' => $e >= 1 ? $e : -1,
        'cur' => $cur, 'top' => max(0, $at - MEL_LIB_WIN_VIS + 1), 'share' => $cur !== '' ? $share : 0.0);
    mel_log("card: own $key: the window of " . count($copies) . " copies ($mode" . ($e >= 1 ? ", S{$s}E{$e}" : '') .
        ')' . ($cur !== '' ? ', the current one at ' . ($at + 1) : ''));
    return mel_ovl_open($scr, array('lw' => $lw), mel_lib_win_map($key), mel_lib_win_defs($lw, $foot, $c['ui']));
}

// The action map of the open window of the copies of the screen of owner $owner: UP, DOWN, ENTER, RETURN to lw_key,
// the other keys none (the card under it does not move).
/**
 * @param string $owner
 * @return array<string, MelAction>
 */
function mel_lib_win_map($owner)
{
    $map = array();
    foreach (array(GUI_EVENT_KEY_UP => 'up', GUI_EVENT_KEY_DOWN => 'down', GUI_EVENT_KEY_ENTER => 'enter',
        GUI_EVENT_KEY_RETURN => 'return') as $ev => $k)
        $map[$ev] = mel_ovl_input('lw_key', $owner, array('k' => $k));
    return $map;
}

// The components of window $lw with hint $foot in language $lang, the children of the slot of the overlay: the shade
// lw_dim and the window lw_win; its lines in the panel lw_rows (moved up by top lines) inside the clipping panel lw_list
// of WIN_VIS lines at most.
/**
 * @param MelLibWin $lw
 * @param string $foot
 * @param string $lang
 * @return list<MelGcComp>
 */
function mel_lib_win_defs($lw, $foot, $lang)
{
    $p = MEL_LIB_WIN;
    $n = count($lw['copies']);
    $vis = min($n, MEL_LIB_WIN_VIS);
    $W = MEL_LIB_WIN_W;
    $h = MEL_LIB_WIN_HEAD + $vis * MEL_LIB_WIN_ROW + MEL_LIB_WIN_FOOT;
    $paths = mel_lib_win_paths($lw['copies'], $lw['e'] >= 1);
    $rows = array();
    for ($i = 0; $i < $n; $i++)
        $rows[] = mel_lib_win_row($lw, $i, $paths, $lang);
    $caps = mel_k_cap();
    $bg = MelKit::plate('dialog', 0, 0, $W, $h);
    $line = MelKit::plate('dialog_line', 0, 0, $W, $h);
    $title = MelKit::text(mel_tr($lang, 'lib_win_title'), MEL_K_PX_STATUS_TITLE, MEL_K_C_TEXT1, MEL_LIB_WIN_PAD,
        MEL_LIB_WIN_TITLE_BASE - $caps[MEL_K_PX_STATUS_TITLE] / 2, array('w' => $W - 2 * MEL_LIB_WIN_PAD));
    $fy = MEL_LIB_WIN_HEAD + $vis * MEL_LIB_WIN_ROW + MEL_LIB_WIN_FOOT_BASE - $caps[MEL_K_PX_CAPTION] / 2;
    $hint = MelKit::text($foot, MEL_K_PX_CAPTION, MEL_K_C_TEXT3, MEL_LIB_WIN_PAD, $fy,
        array('w' => $W - 2 * MEL_LIB_WIN_PAD));
    // Options 0: the panel clips its children (the lines out of the window).
    $list = mel_gc_panel("{$p}_list", mel_gc_at($W, $vis * MEL_LIB_WIN_ROW, 0, MEL_LIB_WIN_HEAD), array(
        mel_gc_panel("{$p}_rows", mel_lib_win_scroll($n, $lw['top']), $rows)), 0);
    $y = intval((1080 - $h) / 2) - MEL_LIB_WIN_UP;
    $win = mel_gc_panel("{$p}_win", mel_gc_at($W, $h, MEL_LIB_WIN_X, $y), array_merge($bg['defs'], $line['defs'],
        $title['defs'], array($list), $hint['defs']));
    return array(mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), MEL_K_C_DIM_STRONG, "{$p}_dim"), $win);
}

// The geometry of the panel of the $n lines of the window, moved up by $top lines.
/**
 * @param int $n
 * @param int $top
 * @return MelGcGeom
 */
function mel_lib_win_scroll($n, $top)
{
    return mel_gc_at(MEL_LIB_WIN_W, $n * MEL_LIB_WIN_ROW, 0, -$top * MEL_LIB_WIN_ROW);
}

// Line $i of window $lw (the copy $lw['copies'][$i], its path of $paths by cid) in language $lang: the panel lw_r<i>
// WIN_ROW high at its place in lw_rows - one geometry in every state, a key changes it by its children.
/**
 * @param MelLibWin $lw
 * @param int $i
 * @param array<string, string> $paths
 * @param string $lang
 * @return MelGcComp
 */
function mel_lib_win_row($lw, $i, $paths, $lang)
{
    $cp = $lw['copies'][$i];
    $W = MEL_LIB_WIN_W;
    $on = $i === $lw['at'];
    $now = $cp['cid'] === $lw['cur'];
    $fg2 = $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT2;
    $d = array();
    if ($on)
    {
        $pl = MelKit::plate('row_on', MEL_LIB_WIN_ON_X, 0, $W - 2 * MEL_LIB_WIN_ON_X, MEL_LIB_WIN_ROW - MEL_LIB_WIN_ON_GAP);
        $d = $pl['defs'];
    }
    $parts = mel_lib_copy_parts($cp, $lang);
    $x = MEL_LIB_WIN_PAD;
    $ids = mel_lib_plate_ids($parts);
    $probe = MelKit::text('', MEL_K_PX_ROW, $fg2, 0, MEL_LIB_WIN_LINE_Y);
    if ($ids)
    {
        // The plates at the pixel under the centre of the capitals of the texts of the line (as mel_oc_lib_parts).
        list($plates, $pw) = mel_oc_lib_plates($ids, 255, MEL_K_PLATE_GAP);
        $d[] = mel_gc_panel(MEL_LIB_WIN . "_p$i", mel_gc_at($pw, MEL_K_PLATE_H, $x, (int) ceil($probe['mid']) -
            intval(MEL_K_PLATE_H / 2)), $plates);
        $x += $pw + MEL_LIB_WIN_GAP;
    }
    if ($now)
    {
        $t = MelKit::text(($ids ? "\xC2\xB7 " : '') . mel_tr($lang, 'lib_win_now'), MEL_K_PX_ROW, $fg2, $x,
            MEL_LIB_WIN_LINE_Y);
        $d = array_merge($d, $t['defs']);
    }
    if ($parts['size'] !== '')
    {
        $t = MelKit::text($parts['size'], MEL_K_PX_ROW, $fg2, 0, MEL_LIB_WIN_LINE_Y);
        $t = MelKit::move($t, $W - MEL_LIB_WIN_PAD - $t['x1'], 0);
        $d = array_merge($d, $t['defs']);
    }
    $caps = mel_k_cap();
    $t = MelKit::text(isset($paths[$cp['cid']]) ? $paths[$cp['cid']] : '', MEL_LIB_WIN_PATH_PX,
        $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT3, MEL_LIB_WIN_PAD, MEL_LIB_WIN_PATH_BASE - $caps[MEL_LIB_WIN_PATH_PX] / 2);
    $d = array_merge($d, $t['defs']);
    if ($now && $lw['share'] > 0)
    {
        $b = MelKit::bar('progress', MEL_LIB_WIN_BAR_X, MEL_LIB_WIN_BAR_Y, $W - 2 * MEL_LIB_WIN_BAR_X, MEL_LIB_WIN_BAR_H,
            $lw['share']);
        $d = array_merge($d, $b['defs']);
    }
    return mel_gc_panel(MEL_LIB_WIN . "_r$i", mel_gc_at($W, MEL_LIB_WIN_ROW, 0, $i * MEL_LIB_WIN_ROW), $d);
}

// lw_key: a key of the open window of the copies. A stale screen: close_and_run (mel_ovl_stale). A live one with no
// window (a key that came after the close, before its map was applied): none. Else mel_lib_win_key.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_lib_win_input($in)
{
    $a = mel_ovl_stale($in);
    if ($a)
        return $a;
    if (!MelOvl::$st || !MelOvl::$st['open'] || !isset(MelOvl::$st['lw']))
    {
        mel_log('card: the window of the copies: a key after the close, none');
        return null;
    }
    return mel_lib_win_key(isset($in->k) && is_string($in->k) ? $in->k : '');
}

// Key $k of the open window of the copies (MelOvl::$st['lw']) -> the reply, null for none:
//   up, down  the cursor a line, to the first and the last (no wrap); the lines scroll with it, WIN_VIS shown: the two
//             lines redrawn by their children, lw_rows moved when it scrolls;
//   enter     the close, then card_lib_copy of the card (k: the id of the copy, from: the mode; of an episode s, e) -
//             the card plays it (mel_oc_lib_input);
//   return    the close, nothing played.
/**
 * @param string $k
 * @return MelAction|null
 */
function mel_lib_win_key($k)
{
    $st = MelOvl::$st;
    if (!$st || !isset($st['lw']))
        return null;
    $lw = $st['lw'];
    $owner = $st['scr']['owner'];
    if ($k === 'return')
    {
        mel_log("card: own $owner: the window of the copies closed, nothing played");
        return mel_ovl_close();
    }
    if ($k === 'enter')
    {
        $cp = $lw['copies'][$lw['at']];
        $p = array('c' => $owner, 'k' => $cp['cid'], 'from' => $lw['mode']);
        if ($lw['e'] >= 1)
            $p += array('s' => strval($lw['s']), 'e' => strval($lw['e']));
        mel_log("card: own $owner: the copy at " . ($lw['at'] + 1) . ' of the window of the copies');
        return mel_ovl_close(mel_input('card_lib_copy', $p));
    }
    if ($k !== 'up' && $k !== 'down')
        return null;
    $was = $lw;
    $to = $lw['at'] + ($k === 'up' ? -1 : 1);
    if ($to < 0 || $to >= count($lw['copies']))
        return null;
    $lw['at'] = $to;
    if ($to < $lw['top'])
        $lw['top'] = $to;
    elseif ($to >= $lw['top'] + MEL_LIB_WIN_VIS)
        $lw['top'] = $to - MEL_LIB_WIN_VIS + 1;
    $st['lw'] = $lw;
    MelOvl::$st = $st;
    $lang = isset(MelOc::$cards[$owner]) ? MelOc::$cards[$owner]['ui'] : mel_catv_ui_lang();
    $paths = mel_lib_win_paths($lw['copies'], $lw['e'] >= 1);
    $ch = array();
    foreach (array($was['at'], $to) as $i)
    {
        $r = mel_lib_win_row($lw, $i, $paths, $lang);
        $ch[] = mel_gc_change(MEL_LIB_WIN . "_r$i", null, GCOMP_TRANSITION_NONE,
            $r[GComponentDef::specific_def][GCompPanelDef::children]);
    }
    if ($lw['top'] !== $was['top'])
        $ch[] = mel_gc_change(MEL_LIB_WIN . '_rows', mel_lib_win_scroll(count($lw['copies']), $lw['top']),
            GCOMP_TRANSITION_DEFAULT);
    return mel_ovl_change($ch, $st['scr']['sel']);
}
