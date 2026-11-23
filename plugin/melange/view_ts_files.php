<?php
// The screen of files of a pack of the section "На TorrServer" (two video files and more; its data: ts_list.php): a
// GComps screen of the UI kit, "tsf:<hash>" over the section (F1 + 3D of knowledge/melange/design-torrserver-section.md
// of the kitchen). On the left the poster (none: the plate of the section with the title), the size of the torrent, its
// video files, the day it was added, its name, and the folder, the file and the size of the line in focus. On the right
// the title, the lines of the groups of folders (MelKit::group_line; none: all in one folder) and the video files of the
// groups open, in the natural order of their paths, their names as they are, a check at those TorrServer marked viewed
// (/viewed, read with every view of the screen); under them «P+ P−» and "12 / 73". The plugin draws and moves the focus
// as the list of releases (view_gcomps.php): only the window of lines (mel_gc_win), the position in sel_state.
// ENTER on a group: open or close it (the screen replaced, "tsf:<hash>:<n>"); on a file: its start (mel_tsl_start with
// the record of the screen: the wait window and the error dialog over it), the playlist from it on. The screen opens on
// the file started last from it (memory), else on the one after the last marked viewed (mel_tsl_next_file), its group
// open. Needs ts_list.php, view_ts.php, view_ts_play.php, view_gcomps.php, kit.php, kit_ovl.php, kit_dlg.php,
// view_my_list.php (mel_mlv_key_acts), egg.php, main.php. PHP 5.3.6, firmware API only.

define('MEL_TSF_URL', 'tsf:');
// The list: its top, the lines on the screen (the 3D of the designer: 13 lines from y 130 to 962, the title above).
define('MEL_TSF_LY', 130);
define('MEL_TSF_VIS', 13);
// The texts beside the poster, the values of the fields of the line in focus, the name of the torrent and the fields.
define('MEL_TSF_SIDE_X', MEL_GC_CX + MEL_GC_PW + MEL_GC_SIDE_GAP);
define('MEL_TSF_VX', 232);
define('MEL_TSF_NAME_MID', 526);
define('MEL_TSF_FIELDS_MID', 641);
define('MEL_TSF_FIELD_LH', 32);
// A file in a group after its line; the room of the check when the pack has one; from the name to the size.
define('MEL_TSF_INDENT', 46);
define('MEL_TSF_CHECK_W', 44);
define('MEL_TSF_SIZE_GAP', 40);
// The label of a group at most, px.
define('MEL_TSF_GROUP_W', 700);

class MelTslf
{
    // The screen of files shown last, null none (memory of php_server): hash, e (its entry), rid (the owner of its keys
    // and its overlay), url (its address), v (the video files in order), runs, flat (mel_tsl_runs), open (the runs open),
    // lines (mel_tsl_lines), seen (the ids marked viewed), sel, top (of the lines), new (its next view takes sel and top,
    // not the sel_state of the shell).
    /** @var array<string, mixed>|null */
    public static $s = null;
    // The file started last from a screen of files, hash => file id.
    /** @var array<string, int> */
    public static $last = array();
    // The n of the address of a screen replaced ("tsf:<hash>:<n>"): a new address, nothing of the old one reused.
    /** @var int */
    public static $gen = 0;
}

// $s with its first letter small: the line «Добавлено …» of the info panel (mel_tsl_texts) on the side.
/**
 * @param string $s
 * @return string
 */
function mel_tsl_lc1($s)
{
    return mb_strtolower(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, mb_strlen($s, 'UTF-8'), 'UTF-8');
}

// The screen of files of torrent $hash, its files $files (kept: mel_tsl_files_keep) -> plugin_open_folder of "tsf:<hash>"
// (the wait screen leaves for it in its place, mel_ks_leave); it opens anew (the file of mel_tsl_next_file or started last).
/**
 * @param string $hash
 * @param list<MelTsFile> $files
 * @return MelAction
 */
function mel_tsl_files_open($hash, $files)
{
    mel_tsl_files_keep($hash, $files);
    MelTslf::$s = null;
    mel_log("ts section: files of $hash: the screen of files");
    return mel_catv_open_act(MEL_TSF_URL . $hash, mel_tsf_caption($hash));
}

// The caption of the screen of files of torrent $hash: the title of its entry, else the name of the section.
/**
 * @param string $hash
 * @return string
 */
function mel_tsf_caption($hash)
{
    return isset(MelTsl::$list[$hash]) ? MelTsl::$list[$hash]['title'] : mel_tr(mel_catv_lang(), 'tsl_title');
}

// A new state of the screen of files of torrent $hash (MelTslf::$s), the marks $seen: its entry (mel_tsl_find), its video
// files (mel_tsl_files_of), the line of the file it opens on - started last from it, else mel_tsl_next_file - its group
// open; null: the entry or its files not known (a restart of php_server), no video file.
/**
 * @param string $hash
 * @param array<int, bool> $seen
 * @return array<string, mixed>|null
 */
function mel_tsf_new($hash, $seen)
{
    list($e) = mel_tsl_find($hash);
    $files = mel_tsl_files_of($hash);
    if ($e === null || $files === null)
        return null;
    $v = mel_tsl_videos_of($files);
    if (!$v)
        return null;
    $at = -1;
    foreach (isset(MelTslf::$last[$hash]) ? $v : array() as $i => $f)
    {
        if ($f['id'] === MelTslf::$last[$hash])
            $at = $i;
    }
    if ($at < 0)
        $at = mel_tsl_next_file($v, $seen);
    list($runs, $flat) = mel_tsl_runs($v);
    $open = array(mel_tsl_run_of($runs, $at) => true);
    $lines = mel_tsl_lines($runs, $flat, $open);
    $sel = 0;
    foreach ($lines as $j => $l)
    {
        if ($l[0] === 'f' && $l[1] === $at)
            $sel = $j;
    }
    return array('hash' => $hash, 'e' => $e, 'rid' => mel_rid(), 'url' => '', 'v' => $v, 'runs' => $runs, 'flat' => $flat,
        'open' => $open, 'lines' => $lines, 'seen' => $seen, 'sel' => $sel, 'top' => mel_tsf_top($sel, count($lines)),
        'new' => false);
}

// The first line on the screen when line $sel of $n opens in focus: the top, or the focus on the third line when the list
// goes on above (3D).
/**
 * @param int $sel
 * @param int $n
 * @return int
 */
function mel_tsf_top($sel, $n)
{
    return $sel > MEL_TSF_VIS - 3 ? max(0, min($sel - 2, $n - MEL_TSF_VIS)) : 0;
}

// sel_state $raw of a list of $n lines -> array(sel, top), null: none or not of it.
/**
 * @param mixed $raw
 * @param int $n
 * @return array{int, int}|null
 */
function mel_tsf_parse($raw, $n)
{
    $j = is_string($raw) && $raw !== '' ? json_decode($raw) : null;
    if (!is_object($j) || !isset($j->sel, $j->top) || !is_int($j->sel) || !is_int($j->top))
        return null;
    if ($j->sel < 0 || $j->sel >= $n || $j->top < 0 || $j->top > max(0, $n - MEL_TSF_VIS) || $j->sel < $j->top ||
        $j->sel >= $j->top + MEL_TSF_VIS)
        return null;
    return array($j->sel, $j->top);
}

// get_folder_view of "tsf:<hash>[:<n>]": the marks read anew (mel_tsl_viewed); the state kept of this torrent (its
// position: of the shell's sel_state, of its own after a replace), else a new one (mel_tsf_new); none - «outdated». The
// time of the build in the log.
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_tsf_folder_view($media_url, $sel_state)
{
    $t0 = microtime(true);
    if (!preg_match('/^' . MEL_TSF_URL . '([0-9a-f]{40})(?::[0-9]{1,9})?$/D', $media_url, $m))
        return mel_lines_view(array(mel_expired_item()));
    $hash = $m[1];
    $seen = mel_tsl_viewed($hash);
    $s = MelTslf::$s;
    if ($s === null || $s['hash'] !== $hash)
        $s = mel_tsf_new($hash, $seen);
    else
    {
        $s['seen'] = $seen;
        $p = $s['new'] ? null : mel_tsf_parse($sel_state, count($s['lines']));
        if ($p)
            list($s['sel'], $s['top']) = $p;
    }
    if ($s === null)
    {
        mel_log("ts section: files screen of $hash: no entry or files in memory, outdated");
        return mel_lines_view(array(mel_expired_item()));
    }
    $s['new'] = false;
    $s['url'] = $media_url;
    MelTslf::$s = $s;
    $view = array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_gc_window_def(mel_tsf_window($s), ''),
            PluginGCompsFolderView::sel_state => mel_gc_state($s['sel'], $s['top']),
            PluginGCompsFolderView::actions => mel_tsf_acts($s),
            PluginGCompsFolderView::timer => null));
    mel_log(sprintf('ts section: files screen: %d videos, %d lines, %.0f ms', count($s['v']), count($s['lines']),
        (microtime(true) - $t0) * 1000));
    return $view;
}

// The action map of screen $s: the moves, ENTER, the colour keys of every screen (egg.php); RETURN is the shell's.
/**
 * @param array<string, mixed> $s
 * @return array<string, MelAction>
 */
function mel_tsf_acts($s)
{
    $p = array('o' => $s['rid'], 'h' => $s['hash']);
    return array(
        GUI_EVENT_KEY_UP => mel_input('tsf_move', $p + array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => mel_input('tsf_move', $p + array('d' => 'down')),
        GUI_EVENT_KEY_P_PLUS => mel_input('tsf_move', $p + array('d' => 'pgup')),
        GUI_EVENT_KEY_P_MINUS => mel_input('tsf_move', $p + array('d' => 'pgdn')),
        GUI_EVENT_KEY_ENTER => mel_input('tsf_pick', $p)) + mel_egg_acts();
}

// The components of screen $s: the background, the side (mel_tsf_side), the card of the line in focus, the title, the window of lines in
// the clipping panel 'list', the scroll bar and the hint with more lines than the screen, the counter, the slot of the
// overlay last.
/**
 * @param array<string, mixed> $s
 * @return list<MelGcComp>
 */
function mel_tsf_window($s)
{
    $lang = mel_catv_lang();
    $n = count($s['lines']);
    $sel = $s['sel'];
    $top = $s['top'];
    // The background of a screen of Melange with no picture of its own (as the kit error screen): cat_card0 shaded.
    $bg = MelKit::pic('cat_card0');
    $d = array_merge(array(mel_gc_image(mel_gc_at(1920, 1080, 0, 0), $bg['url']),
        mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), MEL_K_C_SCRIM)), mel_tsf_side($s, $lang));
    $d[] = mel_gc_panel('card', mel_gc_at(1920, 1080, 0, 0), mel_tsf_card($s, $sel, $lang));
    list($head) = mel_tsl_split($s['e']['title']);
    $t = MelKit::text(MelKit::cut($head, MEL_K_PX_PANEL_TITLE, '', MEL_GC_LW - MEL_GC_PAD), MEL_K_PX_PANEL_TITLE,
        MEL_K_C_TEXT1, MEL_GC_LX + 4, MEL_GC_TITLE_MID);
    $d = array_merge($d, $t['defs']);
    $in = array(mel_gc_sel($sel));
    list($lo, $hi) = mel_gc_win($top, $n, MEL_TSF_VIS);
    for ($j = $lo; $j < $hi; $j++)
        $in[] = mel_tsf_line($s, $j, $j === $sel, $lang);
    $d[] = mel_gc_panel('list', mel_gc_at(MEL_GC_LW, MEL_TSF_VIS * MEL_GC_ROW_H, MEL_GC_LX, MEL_TSF_LY),
        array(mel_gc_panel('rows', mel_gc_rows_geom($top, $n), $in, 0)), 0);
    if ($n > MEL_TSF_VIS)
    {
        $d[] = mel_gc_rect(mel_gc_at(6, MEL_TSF_VIS * MEL_GC_ROW_H, MEL_GC_LX + MEL_GC_LW + 10, MEL_TSF_LY),
            MEL_K_C_SCROLL_TRACK);
        $d[] = mel_gc_rect(mel_tsf_thumb($top, $n), MEL_K_C_SCROLL_THUMB, 'thumb');
        $h = MelKit::hint(array(array('pp', mel_tr($lang, 'gc_page'))), MEL_GC_LX, MEL_GC_HINT_MID);
        $d = array_merge($d, $h['defs']);
    }
    $right = MEL_GC_LX + MEL_GC_LW - MEL_GC_PAD;
    $d[] = mel_gc_panel('counter', mel_gc_at(MEL_GC_COUNT_W, MEL_K_KEY_H, $right - MEL_GC_COUNT_W,
        MEL_GC_HINT_MID - intval(MEL_K_KEY_H / 2)), mel_tsf_count($s, $sel));
    $d[] = mel_ovl_slot();
    return $d;
}

// The thumb of the scroll bar of a list of $n lines, $top first on the screen.
/**
 * @param int $top
 * @param int $n
 * @return MelGcGeom
 */
function mel_tsf_thumb($top, $n)
{
    $track = MEL_TSF_VIS * MEL_GC_ROW_H;
    $h = max(MEL_K_THUMB_MIN, intval($track * MEL_TSF_VIS / $n));
    $y = intval(round(($track - $h) * $top / ($n - MEL_TSF_VIS)));
    return mel_gc_at(6, $h, MEL_GC_LX + MEL_GC_LW + 10, MEL_TSF_LY + $y);
}

// The side of screen $s, of no line: the poster (none http(s): the plate, mel_tsf_plate), beside it the size of the
// torrent (none: of its files), its video files, the day it was added (UTC, as the section), under it its name.
/**
 * @param array<string, mixed> $s
 * @param string $lang
 * @return list<MelGcComp>
 */
function mel_tsf_side($s, $lang)
{
    $e = $s['e'];
    $poster = mel_img($e['poster']);
    $d = $poster !== '' ? array(mel_gc_image(mel_gc_at(MEL_GC_PW, MEL_GC_PH, MEL_GC_CX, MEL_GC_CY), $poster, true)) :
        mel_tsf_plate($e['title']);
    $size = $e['size'];
    if ($size <= 0)
    {
        $files = mel_tsl_files_of($s['hash']);
        foreach ($files === null ? $s['v'] : $files as $f)
            $size += $f['size'];
    }
    $n = count($s['v']);
    list(, $lines) = mel_tsl_texts($e, $lang);
    $w = MEL_GC_LX - MEL_K_GAP_XS - MEL_TSF_SIDE_X;
    $items = array(
        MelKit::text(MelKit::cut(mel_size_str($size, $lang), MEL_K_PX_PANEL_TITLE, '', $w), MEL_K_PX_PANEL_TITLE,
            MEL_K_C_TEXT1, MEL_TSF_SIDE_X, 77),
        MelKit::text($n . ' ' . mel_gc_plural($lang, $n, 'tsl_files'), MEL_K_PX_SECONDARY, MEL_K_C_TEXT2, MEL_TSF_SIDE_X, 118),
        MelKit::text(MelKit::wrap(mel_tsl_lc1($lines[1]), MEL_K_PX_SECONDARY, '', $w, 2), MEL_K_PX_SECONDARY, MEL_K_C_TEXT2,
            MEL_TSF_SIDE_X, 150, array('ls' => MEL_TSF_FIELD_LH - MEL_K_PX_SECONDARY)),
        MelKit::text(MelKit::wrap($e['title'], MEL_K_PX_SECONDARY, '', MEL_GC_CW, 3), MEL_K_PX_SECONDARY, MEL_K_C_TEXT2,
            MEL_GC_CX, MEL_TSF_NAME_MID, array('ls' => MEL_TSF_FIELD_LH - MEL_K_PX_SECONDARY)));
    foreach ($items as $it)
        $d = array_merge($d, $it['defs']);
    return $d;
}

// The plate in place of a poster (as a tile of the section with none, 2E): the colour of the tiles with no picture, the
// icon of the section in the top left corner, title $title under it.
/**
 * @param string $title
 * @return list<MelGcComp>
 */
function mel_tsf_plate($title)
{
    $d = array(mel_gc_rect(mel_gc_at(MEL_GC_PW, MEL_GC_PH, MEL_GC_CX, MEL_GC_CY), MEL_K_C_NO_PHOTO),
        mel_gc_image(mel_gc_at(82, 82, MEL_GC_CX + 11, MEL_GC_CY + 11), MEL_TSL_ICON));
    $lines = MelKit::wrap($title, MEL_K_PX_ROW, '', MEL_GC_PW - 40, 8);
    $t = MelKit::text($lines ? $lines : array(''), MEL_K_PX_ROW, MEL_K_C_TEXT1, MEL_GC_CX + 20, MEL_GC_CY + 125,
        array('ls' => 10));
    return array_merge($d, $t['defs']);
}

// The card of line $j of screen $s (panel 'card', in the coordinates of the screen): of a file «Папка» (its group; none in
// one folder), «Файл» (its name whole, up to 4 lines broken at its dots too), «Размер»; of a group «Папка», «Размер».
/**
 * @param array<string, mixed> $s
 * @param int $j
 * @param string $lang
 * @return list<MelGcComp>
 */
function mel_tsf_card($s, $j, $lang)
{
    if (!isset($s['lines'][$j]))
        return array();
    list($kind, $i) = $s['lines'][$j];
    $run = $s['runs'][$kind === 'g' ? $i : mel_tsl_run_of($s['runs'], $i)];
    $vw = MEL_GC_CX + MEL_GC_CW - MEL_TSF_VX;
    $fields = array();
    if (!$s['flat'])
        $fields[] = array('info_folder', MelKit::wrap($run['dir'] !== '' ? $run['dir'] : mel_tr($lang, 'tsl_files_root'),
            MEL_K_PX_SECONDARY, '', $vw, 2));
    if ($kind === 'f')
        $fields[] = array('info_file', MelKit::wrap(mel_ts_basename($s['v'][$i]['path']), MEL_K_PX_SECONDARY, '', $vw, 4));
    $fields[] = array('info_size', array(mel_size_str($kind === 'f' ? $s['v'][$i]['size'] : $run['size'], $lang)));
    $d = array();
    $y = MEL_TSF_FIELDS_MID;
    foreach ($fields as $f)
    {
        $l = MelKit::text(mel_tr($lang, $f[0]), MEL_K_PX_CAPTION, MEL_K_C_TEXT3, MEL_GC_CX + 2, $y,
            array('w' => MEL_TSF_VX - MEL_GC_CX - MEL_K_GAP_XS));
        $v = MelKit::text($f[1], MEL_K_PX_SECONDARY, MEL_K_C_TEXT1, MEL_TSF_VX, $y,
            array('w' => $vw, 'ls' => MEL_TSF_FIELD_LH - MEL_K_PX_SECONDARY));
        $d = array_merge($d, $l['defs'], $v['defs']);
        $y += MEL_TSF_FIELD_LH * count($f[1]) + 10;
    }
    return $d;
}

// The counter of line $j of screen $s: the place of its file among the video files ("12 / 73"); of a group none.
/**
 * @param array<string, mixed> $s
 * @param int $j
 * @return list<MelGcComp>
 */
function mel_tsf_count($s, $j)
{
    if (!isset($s['lines'][$j]) || $s['lines'][$j][0] !== 'f')
        return array();
    return mel_gc_count($s['lines'][$j][1], count($s['v']));
}

// Panel "r$j" of line $j of screen $s ($on: in focus).
/**
 * @param array<string, mixed> $s
 * @param int $j
 * @param bool $on
 * @param string $lang
 * @return MelGcComp
 */
function mel_tsf_line($s, $j, $on, $lang)
{
    return mel_gc_panel("r$j", mel_gc_row_geom($j), mel_tsf_line_items($s, $j, $on, $lang));
}

// The children of line $j of screen $s: a group - its folder, "10 файлов · 280.4 ГБ", the chevron (MelKit::group_line),
// open and not the first line a rule above it; a file - the check of a mark, its name as it is (cut with "…"), its size at
// the right, the last of a group open before a line of a group a rule under it. $on: white on the plate of the focus.
/**
 * @param array<string, mixed> $s
 * @param int $j
 * @param bool $on
 * @param string $lang
 * @return list<MelGcComp>
 */
function mel_tsf_line_items($s, $j, $on, $lang)
{
    list($kind, $i) = $s['lines'][$j];
    $mid = MEL_GC_ROW_H / 2;
    $rule_w = MEL_GC_LW - 2 * MEL_GC_PAD;
    if ($kind === 'g')
    {
        $r = $s['runs'][$i];
        $label = MelKit::cut($r['dir'] !== '' ? $r['dir'] : mel_tr($lang, 'tsl_files_root'), MEL_K_PX_ROW, '', MEL_TSF_GROUP_W);
        $count = $r['n'] . ' ' . mel_gc_plural($lang, $r['n'], 'tsl_files') . " \xC2\xB7 " . mel_size_str($r['size'], $lang);
        $g = MelKit::group_line($label, $count, isset($s['open'][$i]), $on, MEL_GC_PAD, $mid);
        $d = $g['defs'];
        if (isset($s['open'][$i]) && $j > 0)
            $d[] = mel_gc_rect(mel_gc_at($rule_w, MEL_K_LIST_RULE, MEL_GC_PAD, 0), MEL_K_C_LINE);
        return $d;
    }
    $f = $s['v'][$i];
    $c2 = $on ? MEL_K_C_ON_ACCENT2 : MEL_K_C_TEXT2;
    $size = mel_size_str($f['size'], $lang);
    $sx = MEL_GC_LW - MEL_GC_PAD - MelKit::w($size, MEL_K_PX_ROW);
    $x = MEL_GC_PAD + ($s['flat'] ? 0 : MEL_TSF_INDENT);
    $d = array();
    if (isset($s['seen'][$f['id']]))
    {
        $c = MelKit::icon('k_icon_check', MEL_K_ICON_BUTTON, $c2, $x + MEL_K_ICON_BUTTON / 2, $mid);
        $d = $c['defs'];
    }
    if (mel_tsf_marked($s))
        $x += MEL_TSF_CHECK_W;
    $name = MelKit::text(MelKit::cut(mel_ts_basename($f['path']), MEL_K_PX_ROW, '', $sx - MEL_TSF_SIZE_GAP - $x), MEL_K_PX_ROW,
        $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT1, $x, $mid);
    $sz = MelKit::text($size, MEL_K_PX_ROW, $c2, $sx, $mid);
    $d = array_merge($d, $name['defs'], $sz['defs']);
    $next = isset($s['lines'][$j + 1]) ? $s['lines'][$j + 1] : null;
    if (!$s['flat'] && $next !== null && $next[0] === 'g')
        $d[] = mel_gc_rect(mel_gc_at($rule_w, MEL_K_LIST_RULE, MEL_GC_PAD, MEL_GC_ROW_H - MEL_K_LIST_RULE), MEL_K_C_LINE);
    return $d;
}

// A video file of screen $s is marked viewed: the names of all move for the checks.
/**
 * @param array<string, mixed> $s
 * @return bool
 */
function mel_tsf_marked($s)
{
    foreach ($s['v'] as $f)
    {
        if (isset($s['seen'][$f['id']]))
            return true;
    }
    return false;
}

// The record of screen $s for its overlay (MelOvlScreen, kit_ovl.php): its rid, its map with the keys Fav, no timer, its
// sel_state.
/**
 * @param array<string, mixed> $s
 * @return array<string, mixed>
 */
function mel_tsf_scr($s)
{
    return array('owner' => $s['rid'], 'map' => mel_tsf_acts($s) + mel_mlv_key_acts($s['url']), 'timer' => null,
        'sel' => mel_gc_state($s['sel'], $s['top']));
}

// tsf_move, tsf_pick of the screen of files: the position of the shell (parent_sel_state), else the one kept. A key of a
// screen not kept (a restart of php_server, another screen made since): the screen of files of its hash built anew in its
// place. A move: change_gcomps (none at an edge) - the plate of the focus, the window of lines that follows the top, the
// rows, the thumb, the two lines, the card, the counter. ENTER on a group: open or close it, the screen replaced; on a
// file: its start (mel_tsl_start with the record of the screen), the file kept as started last.
/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_tsf_input($in, $control)
{
    $s = MelTslf::$s;
    $o = isset($in->o) && is_string($in->o) ? $in->o : '';
    $h = isset($in->h) && is_string($in->h) ? $in->h : '';
    if ($s === null || $s['rid'] !== $o)
    {
        if (!preg_match('/^[0-9a-f]{40}$/D', $h))
            return null;
        mel_log("ts section: files screen of $h: $control of a screen not kept, built anew");
        return mel_replace_act(MEL_TSF_URL . $h . ':' . ++MelTslf::$gen, mel_tsf_caption($h));
    }
    $n = count($s['lines']);
    $p = mel_tsf_parse(isset($in->parent_sel_state) ? $in->parent_sel_state : null, $n);
    list($sel, $top) = $p ? $p : array($s['sel'], $s['top']);
    if ($control === 'tsf_pick')
        return mel_tsf_pick($s, $sel, $top);
    $key = mel_gc_key($in);
    if ($key === null)
        return null;
    list($nsel, $ntop) = mel_gc_step($sel, $top, $n, $key, MEL_TSF_VIS);
    MelTslf::$s['sel'] = $nsel;
    MelTslf::$s['top'] = $ntop;
    if ($nsel === $sel)
        return null;
    $lang = mel_catv_lang();
    $ch = array(mel_gc_change('sel', mel_gc_row_geom($nsel), GCOMP_TRANSITION_DEFAULT));
    if ($ntop !== $top)
    {
        // As mel_gc_input: the lines that leave the window removed, those that come in added to 'rows'.
        list($olo, $ohi) = mel_gc_win($top, $n, MEL_TSF_VIS);
        list($lo, $hi) = mel_gc_win($ntop, $n, MEL_TSF_VIS);
        $add = array();
        for ($j = min($olo, $lo); $j < max($ohi, $hi); $j++)
        {
            $was = $j >= $olo && $j < $ohi;
            $now = $j >= $lo && $j < $hi;
            if ($was && !$now)
            {
                $x = mel_gc_change("r$j", null, GCOMP_TRANSITION_NONE);
                $x[ChangeGCompDef::remove] = true;
                $ch[] = $x;
            }
            elseif ($now && !$was)
                $add[] = mel_tsf_line($s, $j, false, $lang);
        }
        $x = mel_gc_change('rows', mel_gc_rows_geom($ntop, $n), GCOMP_TRANSITION_DEFAULT, $add ? $add : null);
        if ($add)
        {
            $x[ChangeGCompDef::children_merge_mode] = true;
            $x[ChangeGCompDef::children_insert_after_id] = $lo < $olo ? 'sel' : 'r' . ($ohi - 1);
        }
        $ch[] = $x;
        $ch[] = mel_gc_change('thumb', mel_tsf_thumb($ntop, $n), GCOMP_TRANSITION_DEFAULT);
    }
    $ch[] = mel_gc_change("r$sel", null, GCOMP_TRANSITION_NONE, mel_tsf_line_items($s, $sel, false, $lang));
    $ch[] = mel_gc_change("r$nsel", null, GCOMP_TRANSITION_NONE, mel_tsf_line_items($s, $nsel, true, $lang));
    $ch[] = mel_gc_change('card', null, GCOMP_TRANSITION_NONE, mel_tsf_card($s, $nsel, $lang));
    $ch[] = mel_gc_change('counter', null, GCOMP_TRANSITION_NONE, mel_tsf_count($s, $nsel));
    return mel_gc_changes_act($ch, $nsel, $ntop);
}

// ENTER on line $sel of screen $s ($top first on the screen): a group open or closed - the lines anew, the focus on it,
// the screen replaced ("tsf:<hash>:<n>", its next view by the kept position); a file - its start (mel_tsl_start with the
// record of the screen: the wait window, the error dialog over it), kept as started last.
/**
 * @param array<string, mixed> $s
 * @param int $sel
 * @param int $top
 * @return MelAction|null
 */
function mel_tsf_pick($s, $sel, $top)
{
    if (!isset($s['lines'][$sel]))
        return null;
    list($kind, $i) = $s['lines'][$sel];
    $s['sel'] = $sel;
    $s['top'] = $top;
    if ($kind === 'f')
    {
        $f = $s['v'][$i];
        MelTslf::$last[$s['hash']] = $f['id'];
        MelTslf::$s = $s;
        mel_log("ts section: files screen of {$s['hash']}: file {$f['id']}, line $sel");
        return mel_tsl_start($s['e'], $f['id'], 0, mel_tsf_scr($s));
    }
    if (isset($s['open'][$i]))
        unset($s['open'][$i]);
    else
        $s['open'][$i] = true;
    $s['lines'] = mel_tsl_lines($s['runs'], $s['flat'], $s['open']);
    $n = count($s['lines']);
    $s['top'] = max(0, min($top, $n - MEL_TSF_VIS, $sel));
    if ($sel >= $s['top'] + MEL_TSF_VIS)
        $s['top'] = $sel - MEL_TSF_VIS + 1;
    $s['new'] = true;
    MelTslf::$s = $s;
    mel_log("ts section: files screen of {$s['hash']}: group $i " . (isset($s['open'][$i]) ? 'opened' : 'closed') .
        ", $n lines, the screen replaced");
    return mel_replace_act(MEL_TSF_URL . $s['hash'] . ':' . ++MelTslf::$gen, mel_tsf_caption($s['hash']));
}
