<?php
// "Онлайн" on the Dune, movies (series: a later version): the client is lampac.php. Here the
// item "Онлайн" of "Откуда брать…", the window "Ищу онлайн…" of ticks (the search of Lampac,
// then the replies of its balancers, each call of php_server a few seconds), the online list
// on the screen of the releases (the frame of view_gcomps.php, rows "Phantom · Дублированный"
// and a card of their own), its MENU, ENTER (the start: online_play.php, as "Continue" of a
// record of it and play_action), the mark "online" of a title (data_dir/lampac_choice.json) and
// the windows: nothing online, no answer.
// The list lives in Aio::$state beside the releases: 'lp' (AioLpView), 'lp_on' - the screen
// shows it, 'lp_only' - the releases were never asked, 'lp_note' - the yellow line of the
// releases. The word Lampac only in the card of a row ("Через: Lampac из настроек").
// PHP 5.3.6, firmware API only. Types (AioLpView, AioLpRun, AioLpMark): phpstan-types.neon.

// The timer of the window between its ticks, ms: a tick is a request itself.
define('AIO_LPV_TICK_MS', 100);
// Seconds of the replies of the balancers in one tick (each call <= 3 s).
define('AIO_LPV_TICK_MAX', 3);
// Seconds between two searches of "Обновить": a poll of all balancers often gets the IP banned.
define('AIO_LPV_REFRESH', 30);
// Titles of lampac_choice.json at most, the newest kept.
define('AIO_LPV_MARKS_MAX', 500);
define('AIO_LPV_MARKS_FILE', 'lampac_choice.json');
// Width of the column of the balancers at most, px.
define('AIO_LPV_SRC_W', 320);

class AioLpv
{
    // The search of the window on screen (aio_lpv_search_open), null none.
    /** @var AioLpRun|null */
    public static $run = null;
    // The TMDB and KinoPoisk ids of the cards of play_action (movie_str: tmdb_id, kp_id) by
    // dune id or IMDb: their search needs no /externalids. Memory only.
    /** @var array<string, array{string, string}> */
    public static $ids = array();
}

// "Онлайн" for card $mv: a Lampac in the settings, a movie.
/**
 * @param AioMovie $mv
 * @return bool
 */
function aio_lpv_on($mv)
{
    return Aio::$settings['lp'] !== '' && !$mv['series'];
}

// --- The title.

// The key of a card: of the Dune, else IMDb.
/**
 * @param AioMovie $mv
 * @return string
 */
function aio_lpv_key($mv)
{
    return $mv['dune_id'] !== '' ? $mv['dune_id'] : $mv['imdb'];
}

// The parameters of Lampac of card $mv (aio_lp_title).
/**
 * @param AioMovie $mv
 * @return AioLpTitle
 */
function aio_lpv_title($mv)
{
    $k = aio_lpv_key($mv);
    $ids = isset(AioLpv::$ids[$k]) ? AioLpv::$ids[$k] : array('', '');
    return aio_lp_title(array('tmdb' => $ids[0], 'imdb' => $mv['imdb'], 'kp' => $ids[1], 'title' => $mv['title'],
        'original_title' => $mv['native'], 'year' => $mv['year'], 'serial' => $mv['series']));
}

// --- The mark "online" of a title (spec §8): data_dir/lampac_choice.json, key => mark.

/**
 * @return string
 */
function aio_lpv_marks_path()
{
    $dir = aio_data_dir();
    return $dir !== '' ? "$dir/" . AIO_LPV_MARKS_FILE : '';
}

// The marks of the file, the bad ones left out.
/**
 * @return array<string, AioLpMark>
 */
function aio_lpv_marks_read()
{
    $f = aio_lpv_marks_path();
    $j = $f !== '' && is_file($f) ? json_decode((string) file_get_contents($f, false, null, 0, 1048576), true) : null;
    $out = array();
    foreach (is_array($j) ? $j : array() as $k => $m)
    {
        if (is_string($k) && preg_match('/^(?:' . AIO_DUNE_ID . '|tt[0-9]{1,10})\z/', $k) && is_array($m) &&
            isset($m['online'], $m['path'], $m['voice'], $m['ts']) && is_bool($m['online']) && is_string($m['path']) &&
            is_string($m['voice']) && is_int($m['ts']))
            $out[$k] = array('online' => $m['online'], 'path' => $m['path'], 'voice' => $m['voice'], 'ts' => $m['ts']);
    }
    return $out;
}

// The mark of card $mv, null none.
/**
 * @param AioMovie $mv
 * @return AioLpMark|null
 */
function aio_lpv_mark($mv)
{
    $all = aio_lpv_marks_read();
    $k = aio_lpv_key($mv);
    return $k !== '' && isset($all[$k]) && $all[$k]['online'] ? $all[$k] : null;
}

// Card $mv marked "online" with balancer path $path and voice $voice of the last start (null: as
// it was), or the mark taken off ($on false). The newest AIO_LPV_MARKS_MAX kept.
/**
 * @param AioMovie $mv
 * @param bool $on
 * @param string|null $path
 * @param string|null $voice
 * @return void
 */
function aio_lpv_mark_set($mv, $on, $path = null, $voice = null)
{
    $f = aio_lpv_marks_path();
    $k = aio_lpv_key($mv);
    if ($f === '' || $k === '')
        return;
    $all = aio_lpv_marks_read();
    if (!$on && !isset($all[$k]))
        return;
    $old = isset($all[$k]) ? $all[$k] : array('online' => true, 'path' => '', 'voice' => '', 'ts' => 0);
    unset($all[$k]);
    if ($on)
        $all[$k] = array('online' => true, 'path' => $path !== null ? $path : $old['path'],
            'voice' => $voice !== null ? $voice : $old['voice'], 'ts' => time());
    uasort($all, function ($a, $b)
    {
        return $a['ts'] - $b['ts'];
    });
    $all = array_slice($all, -AIO_LPV_MARKS_MAX, AIO_LPV_MARKS_MAX, true);
    $ok = aio_write_file($f, json_encode($all));
    aio_log("lampac: mark $k " . ($on ? 'online' : 'off') . ($ok ? '' : ', not written'));
}

// --- Words.

/**
 * @param string $lang
 * @param string $key
 * @param array<string, string|int> $sub
 * @return string
 */
function aio_lpv_tr($lang, $key, $sub = array())
{
    $s = aio_tr($lang, $key);
    foreach ($sub as $k => $v)
        $s = str_replace('{' . $k . '}', strval($v), $s);
    return $s;
}

// The words of reason $why (AioLpWhy codes, lampac.php), the address masked. The detail only where
// the text has {d} and it is short for people (http: the code, accsdb: the message of the
// provider); a curl error, an unknown type or method - only in the log (aio_lp_call, aio_lp_replies).
/**
 * @param string $lang
 * @param string $why
 * @param string $detail
 * @return string
 */
function aio_lpv_why($lang, $why, $detail)
{
    $key = "lp_why_$why";
    $s = aio_tr($lang, $key);
    if ($s === $key)
        $s = aio_tr($lang, 'lp_why_other');
    if ($detail === '')
        return str_replace(array(' ({d})', ': {d}', ' {d}'), '', $s);
    $detail = aio_mask(aio_lp_mask($detail, Aio::$settings['lp']));
    return str_replace('{d}', aio_cut(aio_one_line($detail), 80), $s);
}

// --- "Онлайн" in "Откуда брать…" (the line "Откуда: …": aio_src_tabs_right, sources.php).

// "Онлайн" goes into "Откуда брать…" of list $st: the releases are there to go back to.
/**
 * @param AioState $st
 * @return bool
 */
function aio_lpv_in_menu($st)
{
    return aio_lpv_on($st['movie']) && Aio::$settings['base'] !== '';
}

// Items $items of the menu "Откуда брать…" of list $st (the profiles, $sel the one in use;
// none "· сейчас" in the online list) with "Онлайн" (aio_lpv_in_menu): no profile - "Раздачи"
// first; the profiles - a separator; then "Онлайн", in the online list "Онлайн · сейчас"
// selected. -> array(items, sel).
/**
 * @param AioState $st
 * @param list<array<string, mixed>> $items
 * @param int $sel
 * @return array{list<array<string, mixed>>, int}
 */
function aio_lpv_src_items($st, $items, $sel)
{
    $lang = $st['lang'];
    $on = !empty($st['lp_on']);
    if (!$items)
        $items[] = array(
            GuiMenuItemDef::caption => $on ? aio_tr($lang, 'lp_releases') :
                aio_lpv_tr($lang, 'src_now', array('name' => aio_tr($lang, 'lp_releases'))),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
            GuiMenuItemDef::action => aio_gc_act('lp_back', $st['rid']));
    else
        $items[] = array(GuiMenuItemDef::is_separator => true);
    if ($on)
        $sel = count($items);
    $items[] = array(
        GuiMenuItemDef::caption => $on ? aio_lpv_tr($lang, 'src_now', array('name' => aio_tr($lang, 'lp_online'))) :
            aio_tr($lang, 'lp_online'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/web_browser.aai',
        GuiMenuItemDef::action => aio_gc_act('lp_go', $st['rid']));
    return array($items, $sel);
}

// Children of the yellow line of the releases of list $st (lp_note: "Онлайн не отвечает —
// показаны раздачи") right-aligned to x $right of the row of the title, 26 px: the title keeps
// half the row. -> array(children, their width; 0 none).
/**
 * @param AioState $st
 * @param int $right
 * @return array{list<AioGcComp>, int}
 */
function aio_lpv_note($st, $right)
{
    if (!empty($st['lp_on']) || empty($st['lp_note']))
        return array(array(), 0);
    $t = aio_gc_cut_w($st['lp_note'], 26, 560);
    $w = aio_gc_w($t, 26);
    return array(array(aio_gc_cut(null, aio_gc_at($w, 36, $right - $w, 58), $t, 26, AIO_GC_AMBER)), $w);
}

// --- The online list on screen.

// The badge of the quality a balancer claims ("2160p", "1080p", "4K"), '' none.
/**
 * @param string $q
 * @return string
 */
function aio_lpv_badge($q)
{
    if (preg_match('/^([0-9])K\z/i', $q, $m))
        return $m[1] === '4' ? '4k' : '';
    $p = preg_match('/^([0-9]{3,4})p\z/i', $q, $m) ? intval($m[1]) : 0;
    $id = $p >= 2160 ? '4k' : ($p > 0 ? "{$p}p" : '');
    return isset(AioGc::$badge_w[$id]) ? $id : '';
}

// Rows of view $v on screen: its rows, then "Ещё N источников" when sources are left.
/**
 * @param AioLpView $v
 * @return int
 */
function aio_lpv_n($v)
{
    return count($v['rows']) + ($v['left'] > 0 ? 1 : 0);
}

// Columns of rows $rows: the badge, the balancer (by its widest name). -> array(x of the
// balancer, its width, x of the voice).
/**
 * @param list<AioLpRow> $rows
 * @return array{int, int, int}
 */
function aio_lpv_cols($rows)
{
    $bw = 0;
    $sw = 0;
    foreach ($rows as $r)
    {
        $b = aio_lpv_badge($r['quality']);
        if ($b !== '')
            $bw = max($bw, AioGc::$badge_w[$b]);
        $sw = max($sw, min(AIO_LPV_SRC_W, aio_gc_w($r['src'], 30)));
    }
    $x = AIO_GC_PAD + ($bw > 0 ? $bw + 24 : 0);
    return array($x, $sw, $x + $sw + 40);
}

// Children of row panel "r$i" of view $v: the badge, the balancer (dimmed after a row of
// the same one, as groups read), the voice; one watched: the bar of the share. The row past
// the rows: "Ещё N источников — Обновить".
/**
 * @param AioLpView $v
 * @param int $i
 * @param array{int, int, int} $c
 * @param bool $on
 * @param string $lang
 * @return list<AioGcComp>
 */
function aio_lpv_row_items($v, $i, $c, $on, $lang)
{
    $hi = $on ? AIO_GC_TEXT : AIO_GC_TEXT2;
    $w = AIO_GC_LW - 2 * AIO_GC_PAD;
    if (!isset($v['rows'][$i]))
    {
        $t = aio_lpv_tr($lang, 'lp_more', array('n' => $v['left'], 'sources' => aio_gc_plural($lang, $v['left'], 'lp_sources')));
        return array(aio_gc_cut(null, aio_gc_left_center($w, 44, AIO_GC_PAD), $t, 30, $on ? AIO_GC_TEXT2 : AIO_GC_DIM));
    }
    $r = $v['rows'][$i];
    $d = array();
    $b = aio_lpv_badge($r['quality']);
    if ($b !== '')
        $d = aio_gc_plates(array($b), AIO_GC_PAD);
    $same = $i > 0 && $v['rows'][$i - 1]['path'] === $r['path'];
    $d[] = aio_gc_cut(null, aio_gc_left_center($c[1], 44, $c[0]), $r['src'], 30, $same && !$on ? AIO_GC_DIM : $hi);
    $d[] = aio_gc_cut(null, aio_gc_left_center(max(40, AIO_GC_LW - AIO_GC_PAD - $c[2]), 44, $c[2]),
        $r['voice'] !== '' ? $r['voice'] : "\xE2\x80\x94", 30, $hi);
    $prog = isset($v['gc_prog']) ? $v['gc_prog'] : array();
    return array_merge($d, aio_gc_bar(isset($prog[$r['key']]) ? $prog[$r['key']] : 0));
}

// Children of 'card' of row $sel of view $v: beside the poster the balancer and "до 2160p";
// under it the voice, the quality "(заявлено)", the subtitles that do not go to the player,
// "Через: Lampac из настроек". The row "Ещё N" and none: no card.
/**
 * @param AioState $st
 * @param AioLpView $v
 * @param int $sel
 * @return list<AioGcComp>
 */
function aio_lpv_card($st, $v, $sel)
{
    if (!isset($v['rows'][$sel]))
        return array();
    $r = $v['rows'][$sel];
    $lang = aio_gc_lang($st);
    $w = AIO_GC_CW;
    $sx = aio_img($st['movie']['poster']) !== '' ? AIO_GC_PW + 20 : 0;
    $sw = $w - $sx;
    $d = array(aio_gc_cut(null, aio_gc_at($sw, 60, $sx, 0), $r['src'], 48, AIO_GC_TEXT));
    if ($r['quality'] !== '')
        $d[] = aio_gc_cut(null, aio_gc_at($sw, 36, $sx, 64), aio_lpv_tr($lang, 'lp_side_q', array('q' => $r['quality'])),
            30, AIO_GC_TEXT2);
    $y = AIO_GC_PH + 20;
    $fields = array(array('lp_card_voice', $r['voice'] !== '' ? $r['voice'] : "\xE2\x80\x94"));
    if ($r['quality'] !== '')
        $fields[] = array('lp_card_quality', aio_lpv_tr($lang, 'lp_card_q', array('q' => $r['quality'])));
    if ($r['subs'] > 0)
        $fields[] = array('lp_card_subs', aio_tr($lang, 'lp_card_subs_v'));
    $fields[] = array('lp_card_via', aio_tr($lang, 'lp_card_via_v'));
    foreach ($fields as $f)
    {
        $d[] = aio_gc_cut(null, aio_gc_at(AIO_GC_VX - 6, 36, 0, $y), aio_tr($lang, $f[0]), 26, AIO_GC_DIM);
        foreach (aio_gc_wrap($f[1], 30, $w - AIO_GC_VX, 2) as $j => $line)
            $d[] = aio_gc_cut(null, aio_gc_at($w - AIO_GC_VX, 36, AIO_GC_VX, $y + 36 * $j), $line, 30, AIO_GC_TEXT2);
        $y += 36 * max(1, count(aio_gc_wrap($f[1], 30, $w - AIO_GC_VX, 2))) + 8;
    }
    return $d;
}

// The window of the online list of $st (its view 'lp'), row $sel selected, $top the first
// on screen: the frame of aio_gc_window (the background, the poster, the title, the hints,
// the counter), no tabs; on their row the yellow line of the view at the left and
// "Откуда: Онлайн" at the right.
/**
 * @param AioState $st
 * @param int $sel
 * @param int $top
 * @return array<string, mixed>
 */
function aio_lpv_window($st, $sel, $top)
{
    $v = $st['lp'];
    $mv = $st['movie'];
    $lang = aio_gc_lang($st);
    $n = aio_lpv_n($v);
    $c = aio_lpv_cols($v['rows']);
    $d = array(aio_gc_rect(aio_gc_at(1920, 1080, 0, 0), '#C0000000'));
    $poster = aio_img($mv['poster']);
    if ($poster !== '')
        $d[] = aio_gc_image(aio_gc_at(AIO_GC_PW, AIO_GC_PH, AIO_GC_CX, AIO_GC_CY), $poster, true);
    $d[] = aio_gc_panel('card', aio_gc_at(AIO_GC_CW, AIO_GC_CARD_BOTTOM - AIO_GC_CY, AIO_GC_CX, AIO_GC_CY),
        aio_lpv_card($st, $v, $sel));
    list($title) = aio_gc_title($st);
    $d[] = aio_gc_cut(null, aio_gc_at(AIO_GC_LW - 16, 66, AIO_GC_LX, 40), $title, 48, AIO_GC_TEXT);
    $in = array(aio_gc_rect(aio_gc_row_geom($sel), AIO_GC_SEL, 'sel'));
    for ($i = 0; $i < $n; $i++)
        $in[] = aio_gc_panel("r$i", aio_gc_row_geom($i), aio_lpv_row_items($v, $i, $c, $i === $sel, $lang));
    $d[] = aio_gc_panel('list', aio_gc_at(AIO_GC_LW, AIO_GC_VIS * AIO_GC_ROW_H, AIO_GC_LX, AIO_GC_LY),
        array(aio_gc_panel('rows', aio_gc_rows_geom($top, $n), $in, 0)), 0);
    $tt = aio_gc_text($lang);
    $hx = AIO_GC_LX;
    if ($n > AIO_GC_VIS)
    {
        $d[] = aio_gc_rect(aio_gc_at(6, AIO_GC_VIS * AIO_GC_ROW_H, AIO_GC_LX + AIO_GC_LW + 10, AIO_GC_LY), '#30FFFFFF');
        $d[] = aio_gc_rect(aio_gc_thumb_geom($top, $n), '#C0FFFFFF', 'thumb');
        $hx = aio_gc_hint($d, $hx, AIO_GC_HINT_Y, array('plugin_file://%shell_ext%/icons/p_plus_p_minus.png' => 114),
            $tt['page']);
    }
    aio_gc_hint($d, $hx, AIO_GC_HINT_Y, array('plugin_file://%shell_ext%/icons/popup_icon_only.png' => 52), $tt['actions']);
    $d[] = aio_gc_panel('counter', aio_gc_at(300, AIO_GC_KEY_H, AIO_GC_LX + AIO_GC_LW - 16 - 300, AIO_GC_HINT_Y),
        aio_gc_counter($sel, $n));
    // No tabs: the yellow line of the view at the left of their row, "Откуда: Онлайн" at the right.
    $right = AIO_GC_LX + AIO_GC_LW - 16;
    list($from, $fw) = aio_src_tabs_right($st, $right, AIO_GC_TABS_Y, 360);
    $d = array_merge($d, $from);
    if ($v['note'] !== '')
        $d[] = aio_gc_cut(null, aio_gc_at(AIO_GC_LW - 16 - ($fw > 0 ? $fw + 40 : 0), AIO_GC_LABEL_H, AIO_GC_LX,
            AIO_GC_TABS_Y + (AIO_GC_KEY_H - AIO_GC_LABEL_H) / 2), $v['note'], AIO_GC_HINT_PX, AIO_GC_AMBER);
    return aio_gc_window_def($d, $mv['fanart']);
}

// The cursor of view $v: of the shell's sel_state $raw, else its last one. -> array(sel, top).
/**
 * @param AioLpView $v
 * @param mixed $raw
 * @return array{int, int}
 */
function aio_lpv_pos($v, $raw)
{
    return aio_gc_pos_in($raw, aio_lpv_n($v), isset($v['gc']) ? $v['gc'] : null);
}

// get_folder_view of the online list of $st (lp_on).
/**
 * @param AioState $st
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_lpv_folder_view($st, $sel_state)
{
    // The first view of a view put in: its row, not a sel_state of the screen it replaced.
    list($sel, $top) = aio_lpv_pos($st['lp'], isset($st['lp']['gc_new']) ? null : $sel_state);
    unset(Aio::$state['lp']['gc_new']);
    Aio::$state['lp']['gc'] = array($sel, $top);
    $st['lp']['gc_prog'] = Aio::$state['lp']['gc_prog'] = aio_wh_progress($st);
    $rid = $st['rid'];
    $acts = array(
        GUI_EVENT_KEY_UP => aio_gc_act('lp_move', $rid, array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => aio_gc_act('lp_move', $rid, array('d' => 'down')),
        GUI_EVENT_KEY_P_PLUS => aio_gc_act('lp_move', $rid, array('d' => 'pgup')),
        GUI_EVENT_KEY_P_MINUS => aio_gc_act('lp_move', $rid, array('d' => 'pgdn')),
        GUI_EVENT_KEY_ENTER => aio_gc_act('lp_pick', $rid),
        GUI_EVENT_MENU_PLAYBACK_FINISH => aio_finish_act($rid),
        GUI_EVENT_KEY_POPUP_MENU => aio_gc_act('lp_menu', $rid));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => aio_lpv_window($st, $sel, $top),
            PluginGCompsFolderView::sel_state => aio_gc_state($sel, $top),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => null));
}

// lp_move of the online list: the cursor, the brightness of the rows, the card, the counter.
/**
 * @param stdClass $in
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_move($in, $st)
{
    $key = aio_gc_key($in);
    if ($key === null)
        return null;
    $v = $st['lp'];
    $n = aio_lpv_n($v);
    list($sel, $top) = aio_lpv_pos($v, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    list($nsel, $ntop) = aio_gc_step($sel, $top, $n, $key);
    Aio::$state['lp']['gc'] = array($nsel, $ntop);
    if ($nsel === $sel)
        return null;
    $ch = aio_gc_cursor_changes($top, $nsel, $ntop, $n);
    $lang = aio_gc_lang($st);
    $c = aio_lpv_cols($v['rows']);
    $ch[] = aio_gc_change("r$sel", null, GCOMP_TRANSITION_NONE, aio_lpv_row_items($v, $sel, $c, false, $lang));
    $ch[] = aio_gc_change("r$nsel", null, GCOMP_TRANSITION_NONE, aio_lpv_row_items($v, $nsel, $c, true, $lang));
    $ch[] = aio_gc_change('card', null, GCOMP_TRANSITION_NONE, aio_lpv_card($st, $v, $nsel));
    $ch[] = aio_gc_change('counter', null, GCOMP_TRANSITION_NONE, aio_gc_counter($nsel, $n));
    return aio_gc_changes_act($ch, $nsel, $ntop);
}

// Back from the player (aio_finish): the bars of the rows whose share changed, else nothing.
/**
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_finish($st)
{
    $v = $st['lp'];
    $old = isset($v['gc_prog']) ? $v['gc_prog'] : array();
    $v['gc_prog'] = Aio::$state['lp']['gc_prog'] = aio_wh_progress($st);
    list($sel, $top) = aio_lpv_pos($v, null);
    $lang = aio_gc_lang($st);
    $c = aio_lpv_cols($v['rows']);
    $ch = array();
    foreach ($v['rows'] as $i => $r)
    {
        $was = isset($old[$r['key']]) ? $old[$r['key']] : 0;
        $now = isset($v['gc_prog'][$r['key']]) ? $v['gc_prog'][$r['key']] : 0;
        if ($was != $now)
            $ch[] = aio_gc_change("r$i", null, GCOMP_TRANSITION_NONE, aio_lpv_row_items($v, $i, $c, $i === $sel, $lang));
    }
    aio_log('lampac: finish, ' . count($ch) . ' rows redrawn');
    if (!$ch)
        return null;
    return aio_gc_changes_act($ch, $sel, $top);
}

// --- MENU of the online list (A4): "Обновить", "Откуда брать…", "Ошибки источников", "Настройки".

/**
 * @param AioState $st
 * @return AioAction
 */
function aio_lpv_menu($st)
{
    $lang = $st['lang'];
    $rid = $st['rid'];
    $menu = array(array(
        GuiMenuItemDef::caption => aio_tr($lang, 'refresh_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/upgrade.aai',
        GuiMenuItemDef::action => aio_gc_act('lp_refresh', $rid)));
    if (aio_lpv_in_menu($st))
        $menu[] = array(
            GuiMenuItemDef::caption => aio_tr($lang, 'src_list'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_src_menu', $rid));
    $errors = aio_lpv_errors_of($st);
    if ($errors['lines'])
        $menu[] = array(
            GuiMenuItemDef::caption => aio_tr($lang, 'errors_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/info.aai',
            GuiMenuItemDef::action => aio_gc_act('lp_errors', $rid));
    $menu[] = array(
        GuiMenuItemDef::caption => aio_tr($lang, 'setup_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/setup.aai',
        GuiMenuItemDef::action => aio_setup_open_act());
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => $menu));
}

// "Ошибки источников" of the online list of $st: a line a reason of a source ("Filmix: не
// отвечает"), once each; those only through the Lampa app as one line of their number.
/**
 * @param AioState $st
 * @return AioErrors
 */
function aio_lpv_errors_of($st)
{
    $v = $st['lp'];
    $lang = aio_gc_lang($st);
    $lines = array();
    $rch = $v['hidden']['rch'];
    foreach ($v['why'] as $w)
    {
        if ($w['why'] === 'rch')
        {
            $rch++;
            continue;
        }
        $lines[] = ($w['src'] !== '' ? $w['src'] : $w['path']) . ': ' . aio_lpv_why($lang, $w['why'], $w['detail']);
    }
    $lines = array_values(array_unique($lines));
    if ($rch > 0)
        $lines[] = aio_lpv_tr($lang, 'lp_hidden_rch', array('n' => $rch, 'sources' => aio_gc_plural($lang, $rch, 'lp_sources')));
    return array('lines' => array_slice($lines, 0, AIO_ERRORS_MAX), 'more' => max(0, count($lines) - AIO_ERRORS_MAX));
}

// --- The window "Ищу онлайн…": the search (events, /lifeevents), then the replies of the
// balancers, a tick each call of php_server (spec §4.3); the result - the online list, else
// a window. AioLpv::$run keeps it between the ticks; Cancel or RETURN drops the window.

// A new window of the search of list $st (rows may be none) as $how: 'open' - over a card
// (play_action), its screen opened; 'replace' - over a list screen, replaced. $opt: mark (the
// title marked "online" when there is a list), back (nothing online: the releases with the
// yellow line), path (the path of a balancer: the cursor on it, the yellow line "<it> больше не
// отдаёт эту озвучку"), more (the replies of the view of $st from its next, no search; the
// cursor on the first new row).
/**
 * @param AioState $st
 * @param string $how
 * @param array{mark?: bool, back?: bool, path?: string, more?: bool} $opt
 * @return AioAction
 */
function aio_lpv_search_open($st, $how, $opt = array())
{
    $sid = aio_rid();
    $more = !empty($opt['more']) && isset($st['lp']);
    $v = array('title' => aio_lpv_title($st['movie']), 'q' => '', 'sources' => array(),
        'hidden' => array('show' => 0, 'rch' => 0, 'bad' => 0), 'rows' => array(), 'why' => array(), 'next' => 0,
        'left' => 0, 't' => aio_lp_now(), 'note' => '');
    if ($more)
    {
        $v = $st['lp'];
        unset($v['gc'], $v['gc_new'], $v['gc_prog']);
    }
    $run = array('sid' => $sid, 'st' => $st, 'how' => $how, 'mark' => !empty($opt['mark']), 'back' => !empty($opt['back']),
        'path' => isset($opt['path']) ? $opt['path'] : '', 'phase' => $more ? 'replies' : 'ids', 'search' => null,
        'v' => $v, 't1' => $more ? aio_lp_now() : 0.0, 'more' => $more ? count($v['rows']) : -1);
    AioLpv::$run = $run;
    aio_log("lampac: window $sid, " . aio_ep_tag($st) . ", $how, phase {$run['phase']}" . ($run['mark'] ? ', mark' : '') .
        ($run['back'] ? ', back' : '') . ($run['path'] !== '' ? ", balancer {$run['path']}" : ''));
    return aio_dialog_act(aio_tr($st['lang'], 'lp_search'), aio_lpv_search_defs($run), array(
        ShowDialogActionData::actions => array(GUI_EVENT_TIMER => aio_input('lp_tick', array('sid' => $sid), 30000)),
        ShowDialogActionData::timer => array(GuiTimerDef::delay_ms => AIO_LPV_TICK_MS)));
}

// Controls of the window of run $run: the sources found, the balancers asked and the variants
// of them (both lines always: the window keeps its height), "Отмена".
/**
 * @param AioLpRun $run
 * @return list<array<string, mixed>>
 */
function aio_lpv_search_defs($run)
{
    $lang = $run['st']['lang'];
    $v = $run['v'];
    $n = count($v['sources']);
    $l1 = aio_lpv_tr($lang, 'lp_search_found', array('n' => $n));
    $l2 = $run['phase'] === 'replies' ? aio_lpv_tr($lang, 'lp_search_asking', array('k' => $v['next'], 'n' => $n,
        'r' => count($v['rows']))) : aio_tr($lang, 'lp_search_wait');
    return array(
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l1)),
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $l2)),
        aio_ctl('cancel', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => aio_tr($lang, 'lp_cancel'),
            GuiButtonDef::width => 300, GuiButtonDef::push_action => aio_input('lp_cancel', array('sid' => $run['sid'])))));
}

// lp_tick of the window $in->sid: one step of its run - /externalids, the start of the search
// (events), a /lifeevents (or the one events without life: up to AIO_LP_SEARCH_MAX s), the
// replies of a few balancers - then the window redrawn and its timer again, or closed with the
// result (aio_lpv_result).
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_lpv_tick($in)
{
    $run = AioLpv::$run;
    if (!$run || !isset($in->sid) || $in->sid !== $run['sid'])
    {
        aio_log('lampac: tick of a window gone');
        return aio_close_and(null);
    }
    $base = Aio::$settings['lp'];
    if ($base === '')
    {
        AioLpv::$run = null;
        return aio_close_and(aio_lpv_down($run, 'down', 'no address'));
    }
    if ($run['phase'] === 'ids')
    {
        list($run['v']['title']) = aio_lp_externalids($base, $run['v']['title']);
        $run['phase'] = 'start';
    }
    else if ($run['phase'] === 'start')
    {
        $run['search'] = aio_lp_search_start($base, $run['v']['title']);
        $run['phase'] = 'search';
    }
    else if ($run['phase'] === 'search' && $run['search'])
        $run['search'] = aio_lp_search_step($run['search']);
    else if ($run['phase'] === 'replies')
    {
        $v = $run['v'];
        $left = AIO_LP_REPLIES_MAX - (aio_lp_now() - $run['t1']);
        if ($left >= AIO_LP_REPLY_MIN && $v['next'] < count($v['sources']))
        {
            $rr = aio_lp_replies($base, $v['sources'], $v['q'], $v['next'], min(AIO_LPV_TICK_MAX, $left));
            $v['rows'] = array_slice(array_merge($v['rows'], $rr['rows']), 0, AIO_LP_ROWS_MAX);
            $v['why'] = array_merge($v['why'], $rr['why']);
            $v['next'] = $rr['next'];
            $run['v'] = $v;
        }
        if ($v['next'] >= count($v['sources']) || AIO_LP_REPLIES_MAX - (aio_lp_now() - $run['t1']) < AIO_LP_REPLY_MIN)
            $run['phase'] = 'done';
    }
    $s = $run['search'];
    if ($run['phase'] === 'search' && $s)
    {
        $run['v']['sources'] = $s['sources'];
        $run['v']['hidden'] = $s['hidden'];
        if ($s['done'])
        {
            if (!$s['sources'] && $s['err'] !== '' && $s['err'] !== 'accsdb')
            {
                AioLpv::$run = null;
                return aio_close_and(aio_lpv_down($run, $s['err'], $s['detail']));
            }
            if ($s['err'] === 'accsdb')
                $run['v']['why'][] = array('src' => '', 'path' => '', 'why' => 'accsdb', 'detail' => $s['detail']);
            $run['v']['q'] = $s['q'];
            $run['v']['t'] = $s['t0'];
            $run['phase'] = $s['sources'] ? 'replies' : 'done';
            $run['t1'] = aio_lp_now();
        }
    }
    AioLpv::$run = $run;
    if ($run['phase'] === 'done')
    {
        AioLpv::$run = null;
        return aio_close_and(aio_lpv_result($run));
    }
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => array(GUI_EVENT_TIMER => aio_input('lp_tick', array('sid' => $run['sid']), 30000)),
            ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => AIO_LPV_TICK_MS),
            ChangeBehaviourActionData::post_action => array(
                GuiAction::handler_string_id => RESET_CONTROLS_ACTION_ID,
                GuiAction::data => array(
                    ResetControlsActionData::defs => aio_lpv_search_defs($run),
                    ResetControlsActionData::initial_sel_ndx => -1,
                    ResetControlsActionData::post_action => null))));
}

// Lampac of run $run did not answer ($why, $detail: AioLpWhy): a marked title with releases -
// those with the yellow line; else the window "Онлайн не отвечает".
/**
 * @param AioLpRun $run
 * @param string $why
 * @param string $detail
 * @return AioAction
 */
function aio_lpv_down($run, $why, $detail)
{
    $st = $run['st'];
    $lang = $st['lang'];
    aio_log("lampac: window {$run['sid']}: no answer ($why" . ($detail !== '' ? ', ' . aio_lp_mask($detail, Aio::$settings['lp']) : '') .
        ')' . ($run['back'] ? ' -> the releases' : ''));
    if ($run['back'])
        return aio_lpv_releases($st, aio_tr($lang, 'lp_note_down'));
    return aio_dialog(aio_tr($lang, 'lp_down'), aio_dialog_lines(aio_lpv_why($lang, $why, $detail)),
        array(aio_tr($lang, 'lp_close') => aio_close_and(null)));
}

// The end of run $run: the online list (of a new search the remembered row of the title first,
// then the order of Lampac; the cursor on the balancer of the run, after "Ещё N" on the first new
// row), its screen opened or the list screen replaced; nothing at all - the window "Онлайн для
// «…» не нашлось" (a marked title with releases: those with the yellow line).
/**
 * @param AioLpRun $run
 * @return AioAction
 */
function aio_lpv_result($run)
{
    $st = $run['st'];
    $mv = $st['movie'];
    $lang = $st['lang'];
    $v = $run['v'];
    $left = count($v['sources']) - $v['next'];
    $tag = "lampac: window {$run['sid']}: " . count($v['sources']) . ' sources, ' . count($v['rows']) . " rows, $left left";
    if (!$v['rows'] && $left <= 0)
    {
        aio_log("$tag -> " . ($run['back'] ? 'the releases' : 'nothing online'));
        if ($run['back'])
            return aio_lpv_releases($st, aio_tr($lang, 'lp_note_empty'));
        $lines = array();
        foreach ($v['why'] as $w)
        {
            if ($w['why'] === 'accsdb' && $w['detail'] !== '')
                $lines = array_merge($lines, aio_dialog_lines($w['detail']));
        }
        return aio_dialog(aio_lpv_tr($lang, 'lp_none', array('title' => $mv['title'])), array_slice($lines, 0, 2),
            array(aio_tr($lang, 'lp_close') => aio_close_and(null)));
    }
    $mark = $run['more'] < 0 ? aio_lpv_mark($mv) : null;
    $rows = $v['rows'];
    $first = -1;
    foreach ($rows as $i => $r)
    {
        if ($mark && $mark['path'] !== '' && $r['path'] === $mark['path'] && $r['voice'] === $mark['voice'])
        {
            $first = $i;
            break;
        }
    }
    if ($first > 0)
        $rows = array_merge(array($rows[$first]), array_slice($rows, 0, $first), array_slice($rows, $first + 1));
    $sel = max(0, $run['more']);
    $note = '';
    if ($run['path'] !== '')
    {
        $label = ucfirst($run['path']);
        foreach ($v['sources'] as $src)
        {
            if ($src['path'] === $run['path'])
                $label = $src['label'];
        }
        foreach ($rows as $i => $r)
        {
            if ($r['path'] === $run['path'])
            {
                $sel = $i;
                break;
            }
        }
        $note = aio_lpv_tr($lang, 'lp_note_gone', array('src' => $label));
    }
    $v['rows'] = $rows;
    $v['left'] = max(0, $left);
    $v['note'] = $note;
    $sel = min($sel, aio_lpv_n($v) - 1);
    $v['gc'] = aio_gc_cursor($sel, aio_lpv_n($v));
    $v['gc_new'] = true;
    $ns = $st;
    $ns['rid'] = aio_rid();
    $ns['lp'] = $v;
    $ns['lp_on'] = true;
    unset($ns['lp_note']);
    if ($run['mark'])
        aio_lpv_mark_set($mv, true);
    aio_log("$tag -> the list, row $sel" . ($first > 0 ? ", the remembered row $first first" : ''));
    if ($run['how'] === 'open')
        return aio_lpv_open($ns);
    return aio_list_replace($ns);
}

// List $ns the live one, its screen opened (over a card).
/**
 * @param AioState $ns
 * @return AioAction
 */
function aio_lpv_open($ns)
{
    Aio::$state = $ns;
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "streams:{$ns['rid']}",
            PluginOpenFolderActionData::caption => aio_ep_name($ns['movie'], $ns['s'], $ns['e'])));
}

// The releases of list $st asked now (its source of the settings), opened over the card with
// the yellow line $note; an error: its message.
/**
 * @param AioState $st
 * @param string $note
 * @return AioAction
 */
function aio_lpv_releases($st, $note)
{
    $r = aio_relist($st, $st['s'], $st['e'], '');
    if (isset($r['error']))
    {
        aio_log("lampac: the releases: {$r['error']}");
        return aio_error($r['error'], $r['detail']);
    }
    $ns = $r['st'];
    $ns['lp_note'] = $note;
    return aio_lpv_open($ns);
}

// --- MENU and the keys of the lists.

// lp_go ("Откуда брать… → Онлайн") of list $st: the search window, the title marked; the online
// list of this list from a search younger than AIO_LPV_REFRESH s: it again, no request; already
// the online list: nothing.
/**
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_go($st)
{
    if (!empty($st['lp_on']) || !aio_lpv_on($st['movie']))
        return null;
    if (isset($st['lp']) && aio_lp_now() - $st['lp']['t'] < AIO_LPV_REFRESH)
    {
        aio_lpv_mark_set($st['movie'], true);
        $ns = $st;
        $ns['rid'] = aio_rid();
        $ns['lp_on'] = true;
        aio_log('lampac: the online list again, ' . count($ns['lp']['rows']) . ' rows, no request');
        return aio_list_replace($ns);
    }
    return aio_lpv_search_open($st, 'replace', array('mark' => true));
}

// lp_back ("Откуда брать… → Раздачи", or the profile in use, of the online list $st): the mark
// taken off, the releases back - those asked before as they were, else asked now (an error:
// its dialog, the online list kept). Of the releases with the yellow line (a marked title,
// nothing online): the mark taken off, the releases stay, the line gone.
/**
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_back($st)
{
    if (empty($st['lp_on']) && !empty($st['lp_note']))
    {
        aio_lpv_mark_set($st['movie'], false);
        $ns = $st;
        $ns['rid'] = aio_rid();
        unset($ns['lp_note']);
        aio_log('lampac: the releases chosen over the yellow line, the mark off');
        return aio_list_replace($ns);
    }
    if (empty($st['lp_on']))
        return null;
    aio_lpv_mark_set($st['movie'], false);
    if (empty($st['lp_only']))
    {
        $ns = $st;
        $ns['rid'] = aio_rid();
        unset($ns['lp_on']);
        aio_log('lampac: back to the releases, ' . count($ns['rows']) . ' rows, no request');
        return aio_list_replace($ns);
    }
    $r = aio_relist($st, $st['s'], $st['e'], '');
    if (isset($r['error']))
    {
        aio_log("lampac: back to the releases: {$r['error']}, the online list kept");
        return aio_relist_dialog(aio_tr($st['lang'], 'lp_releases'), $st['lang'], $r);
    }
    $ns = $r['st'];
    $ns['lp'] = $st['lp'];
    aio_log('lampac: back to the releases, asked: ' . count($ns['rows']) . ' rows');
    return aio_list_replace($ns);
}

// lp_refresh ("Обновить") of the online list $st: a new search, not within AIO_LPV_REFRESH s of
// the last one (a message then).
/**
 * @param AioState $st
 * @return AioAction
 */
function aio_lpv_refresh($st)
{
    $wait = (int) ceil(AIO_LPV_REFRESH - (aio_lp_now() - $st['lp']['t']));
    if ($wait > 0)
    {
        aio_log("lampac: refresh in $wait s");
        return aio_dialog(aio_lpv_tr($st['lang'], 'lp_refresh_wait', array('n' => $wait)), array(),
            array('OK' => aio_close_and(null)));
    }
    return aio_lpv_search_open($st, 'replace');
}

// lp_pick (ENTER) of the online list $st: the row "Ещё N" - the replies of the sources left; a
// row - its link and the player.
/**
 * @param stdClass $in
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_pick($in, $st)
{
    $v = $st['lp'];
    list($sel, $top) = aio_lpv_pos($v, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    Aio::$state['lp']['gc'] = array($sel, $top);
    if (!isset($v['rows'][$sel]))
        return $v['left'] > 0 ? aio_lpv_search_open($st, 'replace', array('more' => true)) : null;
    $r = $v['rows'][$sel];
    $left = Aio::$t0 > 0 ? AIO_OP_BUDGET - (microtime(true) - Aio::$t0) : AIO_OP_BUDGET;
    aio_log("lampac: row $sel of " . count($v['rows']) . ": {$r['path']}, {$r['method']}");
    $l = aio_lpv_link(Aio::$settings['lp'], $r, aio_lp_now() + $left);
    if ($l['err'] !== '')
        return aio_lpv_failed($st['lang'], $r, $l['err'], $l['detail']);
    return aio_lpv_vod($st['movie'], $st['lang'], $r, $l['url'], 0);
}

// The window of row $r that gave no link to play: its reason; the list stays.
/**
 * @param string $lang
 * @param AioLpRow $r
 * @param string $why
 * @param string $detail
 * @return AioAction
 */
function aio_lpv_failed($lang, $r, $why, $detail)
{
    aio_log("lampac: {$r['path']}: no link to play ($why)");
    return aio_dialog(aio_lpv_tr($lang, 'lp_failed', array('src' => $r['src'])), aio_dialog_lines(aio_lpv_why($lang, $why,
        $detail)), array('OK' => aio_close_and(null)));
}

// --- The dispatcher of lp_* (main.php).

/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction|null
 */
function aio_lpv_input($in, $control)
{
    if ($control === 'lp_tick')
        return aio_lpv_tick($in);
    if ($control === 'lp_cancel')
    {
        if (AioLpv::$run && isset($in->sid) && $in->sid === AioLpv::$run['sid'])
            AioLpv::$run = null;
        aio_log('lampac: the search cancelled');
        return aio_close_and(null);
    }
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log("lampac: $control of no live list");
        return $control === 'lp_move' ? null : aio_error('err_list_expired');
    }
    if ($control === 'lp_find')
        return aio_lpv_on($st['movie']) ? aio_lpv_search_open($st, 'open', array('mark' => true)) : null;
    if ($control === 'lp_go')
        return aio_lpv_go($st);
    if ($control === 'lp_back')
        return aio_lpv_back($st);
    if (empty($st['lp_on']) || !isset($st['lp']))
        return null;
    if ($control === 'lp_move')
        return aio_lpv_move($in, $st);
    if ($control === 'lp_pick')
        return aio_lpv_pick($in, $st);
    if ($control === 'lp_menu')
    {
        list($sel, $top) = aio_lpv_pos($st['lp'], isset($in->parent_sel_state) ? $in->parent_sel_state : null);
        Aio::$state['lp']['gc'] = array($sel, $top);
        return aio_lpv_menu($st);
    }
    if ($control === 'lp_errors')
        return aio_errors_dialog(aio_tr($st['lang'], 'errors_menu'), $st['lang'], aio_lpv_errors_of($st));
    if ($control === 'lp_refresh')
        return aio_lpv_refresh($st);
    return null;
}
