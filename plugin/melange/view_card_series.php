<?php
// The seasons and the episodes of a series on the own card (H1 of design-own-card.md): the episode
// to offer and the progress of the episodes by the history, the tabs of the seasons, the row of
// the stills and its window, their changes by the keys. Split out of view_card.php (its constants
// and the rest of the screen there).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// --- The episodes.

// The line under the title of an episode: not out - "выйдет 16 ноября 2026" (no date: "ещё не
// вышла"); begun - "осталось 29 мин из 48 мин"; else "6 апр 2009 · 48 мин" (what of it is known).
/**
 * @param AioCardEp $ep
 * @param array{float, float, bool}|null $pr
 * @param string $lang
 * @return string
 */
function aio_oc_ep_line($ep, $pr, $lang)
{
    if ($ep['soon'])
        return $ep['date'] !== '' ? str_replace('{d}', aio_oc_day($ep['date'], $lang), aio_tr($lang, 'card_ep_coming')) :
            aio_tr($lang, 'card_ep_not_out');
    if ($pr && $pr[0] > 0 && !$pr[2] && $pr[1] > 0)
        return str_replace(array('{l}', '{t}'), array(aio_catv_runtime(intval(ceil(max(0, $pr[1] - $pr[0]) / 60)) . 'min', $lang),
            aio_catv_runtime(intval(round($pr[1] / 60)) . 'min', $lang)), aio_tr($lang, 'card_ep_left'));
    return implode(' · ', array_filter(array(aio_oc_day($ep['date'], $lang, true),
        $ep['min'] > 0 ? aio_catv_runtime($ep['min'] . 'min', $lang) : ''), 'strlen'));
}

// The episodes of season $s of the meta of card $c, none without.
/**
 * @param AioOcCard $c
 * @param int $s
 * @return list<AioCardEp>
 */
function aio_oc_eps($c, $s)
{
    return $c['meta'] && isset($c['meta']['seasons'][$s]) ? $c['meta']['seasons'][$s] : array();
}

// The progress of episode $ep of card $c in history $prog (aio_wh_series: by the season it is asked
// by, aio_cm_req_s), null for none.
/**
 * @param AioOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param AioCardEp $ep
 * @return array{float, float, bool}|null
 */
function aio_oc_prog($c, $prog, $ep)
{
    $k = ($c['meta'] ? aio_cm_req_s($c['meta'], $ep['s']) : $ep['s']) . ':' . $ep['e'];
    return isset($prog[$k]) ? $prog[$k] : null;
}

// The episode to offer of a series card (aio_wh_pick of its history and its marks by the episodes
// of the meta, those not out too; without the meta as play_action without season_numbers): q, e - as
// asked, s - the season of the meta (0: none has it); kind of aio_wh_pick. -> array(it, the progress
// of its episodes, aio_wh_series).
/**
 * @param AioOcCard $c
 * @return array{AioOcEp, array<string, array{float, float, bool}>}
 */
function aio_oc_pick($c)
{
    list($last, $prog, $marks) = $c['dune'] !== '' ? aio_wh_series($c['dune']) : array(null, array(), array());
    $m = $c['meta'];
    $p = aio_wh_pick($last, $c['dune'], $m ? aio_cm_lists($m) : array(), $marks, $prog);
    $s = $m ? aio_cm_season_of($m, $p['s'], $p['e']) : 0;
    aio_log('card: own series: ' . ($p['rec'] !== '' ? "{$p['rec']}, " : '') . "S{$p['s']}E{$p['e']}, {$p['why']}" .
        ($m && $s !== $p['s'] ? ", season $s of the meta" : ''));
    return array(array('q' => $p['s'], 'e' => $p['e'], 's' => $s, 'hash' => $p['hash'], 'kind' => $p['kind']), $prog);
}

// The episode $e of season $s of card $c, null for none.
/**
 * @param AioOcCard $c
 * @param int $s
 * @param int $e
 * @return AioCardEp|null
 */
function aio_oc_ep($c, $s, $e)
{
    $eps = aio_oc_eps($c, $s);
    $i = aio_cm_ep_index($eps, $e);
    return $i >= 0 ? $eps[$i] : null;
}

// --- The seasons and the episodes of a series.

// The tabs of the seasons of the meta of card $c: list - array(season, text, x, width) in the
// strip, x - where it starts on the screen (at the x of the catalog, after "Сезон" when there are
// more than TABS_MAX: then the tabs are numbers; CHEV_SLOT further when not all fit, the slot of
// the left chevron), w - the width of its clip at most (all of it when all fit), all - the width
// of the strip, word - x of "Сезон", 0 for none, cx - x of the count: the widest of the seasons
// (aio_oc_count_text) ends at HINT_R, the strip CNT_GAP before it and
// CHEV_SLOT more for the right chevron when not all fit; one for the series, so nothing moves as
// the season changes.
/**
 * @param AioOcCard $c
 * @return AioOcTabs
 */
function aio_oc_tabs_layout($c)
{
    $ui = $c['ui'];
    $seasons = array_keys($c['meta']['seasons']);
    $x = AIO_CATV_CARD_X;
    $word = 0;
    $nums = count($seasons) > AIO_OC_TABS_MAX;
    if ($nums)
    {
        $word = $x;
        $x += aio_gc_w(aio_tr($ui, 'card_season'), AIO_CATV_LINE_PX) + 16;
    }
    $list = array();
    $tx = 0;
    $cw = 0;
    foreach ($seasons as $s)
    {
        $t = $nums ? strval($s) : str_replace('{n}', strval($s), aio_tr($ui, 'card_season_n'));
        $w = aio_gc_w($t, AIO_CATV_LINE_PX) + 2 * AIO_OC_TAB_PAD;
        $list[] = array($s, $t, $tx, $w);
        $tx += $w + AIO_OC_TAB_GAP;
        $cw = max($cw, aio_gc_w(aio_oc_count_text($c, $s), AIO_OC_CNT_PX));
    }
    $all = $list ? $tx - AIO_OC_TAB_GAP : 0;
    $cx = AIO_OC_HINT_R - $cw;
    $w = $cx - AIO_OC_CNT_GAP - $x;
    if ($all > $w)
    {
        $x += AIO_OC_CHEV_SLOT;
        $w -= 2 * AIO_OC_CHEV_SLOT;
    }
    return array('list' => $list, 'x' => $x, 'w' => min($all, $w), 'all' => $all, 'word' => $word, 'cx' => $cx);
}

// The shift of the strip of tabs $t with the tab of season $s: in the middle of its clip within
// the strip, then on to the start of a tab (the first at or after it): only whole tabs are seen
// (aio_oc_tabs_end); 0 when it all fits.
/**
 * @param AioOcTabs $t
 * @param int $s
 * @return int
 */
function aio_oc_tabs_shift($t, $s)
{
    if ($t['all'] <= $t['w'])
        return 0;
    $sh = 0;
    foreach ($t['list'] as $tab)
    {
        if ($tab[0] === $s)
            $sh = max(0, min($t['all'] - $t['w'], $tab[2] + intval($tab[3] / 2) - intval($t['w'] / 2)));
    }
    foreach ($t['list'] as $tab)
    {
        if ($tab[2] >= $sh)
            return $tab[2];
    }
    return $sh;
}

// The end in the strip of tabs $t of the last tab whole in its clip at shift $sh: the clip ends
// there, no half of a number is seen ("1" of "16" reads as another season).
/**
 * @param AioOcTabs $t
 * @param int $sh
 * @return int
 */
function aio_oc_tabs_end($t, $sh)
{
    $end = $sh;
    foreach ($t['list'] as $tab)
    {
        if ($tab[2] >= $sh && $tab[2] + $tab[3] <= $sh + $t['w'])
            $end = $tab[2] + $tab[3];
    }
    return $end;
}

// The clip of the strip of tabs $t at shift $sh (up to aio_oc_tabs_end) on the screen and the
// strip in it ('tabs' and 'tst').
/**
 * @param AioOcTabs $t
 * @param int $sh
 * @return array{AioGcGeom, AioGcGeom}
 */
function aio_oc_tabs_geoms($t, $sh)
{
    return array(aio_gc_at(aio_oc_tabs_end($t, $sh) - $sh, AIO_OC_TAB_H, $t['x'], AIO_OC_TAB_Y),
        aio_gc_at($t['all'], AIO_OC_TAB_H, -$sh, 0));
}

// The chevrons of the strip of tabs $t that does not fit, at shift $sh with its clip up to $end:
// the left one in its slot while there are seasons left of the clip, the right one CHEV_GAP after
// the clip while there are some right of it; grey, white with the focus on the tabs ($pill).
// -> id => array(geom, children), the children none when it is not seen.
/**
 * @param AioOcTabs $t
 * @param int $sh
 * @param int $end
 * @param bool $pill
 * @return array<string, array{AioGcGeom, list<AioGcComp>}>
 */
function aio_oc_chevs($t, $sh, $end, $pill)
{
    $y = AIO_OC_TAB_Y + intval((AIO_OC_TAB_H - AIO_OC_CHEV_H) / 2);
    $on = $pill ? 'on_' : '';
    $out = array();
    foreach (array('l' => array($t['x'] - AIO_OC_CHEV_GAP - AIO_OC_CHEV_W, $sh > 0),
        'r' => array($t['x'] + $end - $sh + AIO_OC_CHEV_GAP, $end < $t['all'])) as $k => $v)
        $out["ch$k"] = array(aio_gc_at(AIO_OC_CHEV_W, AIO_OC_CHEV_H, $v[0], $y), $v[1] ?
            array(aio_gc_image(aio_gc_at(AIO_OC_CHEV_W, AIO_OC_CHEV_H, 0, 0), AIO_OC_IMG . "chev_{$on}$k.png")) : array());
    return $out;
}

// The children of a tab (or of the line "did not load") of width $w: $state 0 - the text grey,
// 1 - the season shown (white 16 %), 2 - in focus.
/**
 * @param string $text
 * @param int $w
 * @param int $state
 * @return list<AioGcComp>
 */
function aio_oc_tab_items($text, $w, $state)
{
    $d = array();
    if ($state > 0)
    {
        $on = $state === 2 ? 'on_' : '';
        $d[] = aio_gc_image(aio_gc_at(AIO_OC_TAB_CAP, AIO_OC_TAB_H, 0, 0), AIO_OC_IMG . "tab_{$on}l.png");
        $d[] = aio_gc_rect(aio_gc_at($w - 2 * AIO_OC_TAB_CAP, AIO_OC_TAB_H, AIO_OC_TAB_CAP, 0), $state === 2 ? AIO_GC_SEL : AIO_OC_TAB_ON);
        $d[] = aio_gc_image(aio_gc_at(AIO_OC_TAB_CAP, AIO_OC_TAB_H, $w - AIO_OC_TAB_CAP, 0), AIO_OC_IMG . "tab_{$on}r.png");
    }
    $d[] = aio_gc_cut(null, aio_gc_left_center($w, 44, 0), $text, AIO_CATV_LINE_PX, $state > 0 ? AIO_GC_TEXT : AIO_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_CENTER));
    return $d;
}

// The state of the tab of season $s at place $pos (aio_oc_tab_items).
/**
 * @param AioOcPos $pos
 * @param int $s
 * @return int
 */
function aio_oc_tab_state($pos, $s)
{
    return $s !== $pos['s'] ? 0 : ($pos['z'] === 'pill' ? 2 : 1);
}

// The count of season $s: "Сезон 2 · 13 серий".
/**
 * @param AioOcCard $c
 * @param int $s
 * @return string
 */
function aio_oc_count_text($c, $s)
{
    $n = count(aio_oc_eps($c, $s));
    return str_replace(array('{s}', '{n}'), array(strval($s), $n . ' ' . aio_gc_plural($c['ui'], $n, 'card_eps')),
        aio_tr($c['ui'], 'card_season_count'));
}

// The count of season $s at the right of the line of the tabs $t, in its panel from cx to HINT_R.
/**
 * @param AioOcCard $c
 * @param int $s
 * @param AioOcTabs $t
 * @return list<AioGcComp>
 */
function aio_oc_count($c, $s, $t)
{
    return array(aio_gc_cut(null, aio_gc_left_center(AIO_OC_HINT_R - $t['cx'], 34, 0), aio_oc_count_text($c, $s),
        AIO_OC_CNT_PX, AIO_GC_DIM, array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// The stills in the panel of a season of $n at shift $off: all; a long one (more than WIN_MIN)
// those on screen and WIN each side. -> array(first, last).
/**
 * @param int $n
 * @param int $off
 * @return array{int, int}
 */
function aio_oc_win($n, $off)
{
    if ($n <= AIO_OC_WIN_MIN)
        return array(0, $n - 1);
    return array(max(0, $off - AIO_OC_WIN), min($n - 1, $off + AIO_OC_EP_VIS + AIO_OC_WIN));
}

// The row of a series at place $pos: array(the episodes of its season, the index of its episode
// (0 for none), the first still at the left slot (aio_oc_off: the frame goes in the first two slots,
// then the row, the frame stays in the second), the stills of the panel aio_oc_win).
/**
 * @param AioOcCard $c
 * @param AioOcPos $pos
 * @return array{list<AioCardEp>, int, int, int, int}
 */
function aio_oc_row($c, $pos)
{
    $eps = aio_oc_eps($c, $pos['s']);
    $n = count($eps);
    $i = max(0, aio_cm_ep_index($eps, $pos['e']));
    $off = aio_oc_off($i, $n, AIO_OC_EP_VIS, 1);
    list($lo, $hi) = aio_oc_win($n, $off);
    return array($eps, $i, $off, $lo, $hi);
}

// The panel of the stills of a season of $n with the one at $off at the left slot (x of the catalog).
/**
 * @param int $n
 * @param int $off
 * @return AioGcGeom
 */
function aio_oc_eps_geom($n, $off)
{
    return aio_gc_at($n * AIO_OC_EP_STEP, AIO_OC_ROW_H, AIO_CATV_CARD_X - $off * AIO_OC_EP_STEP, 0);
}

// The frame of the focus around slot $slot of the row, in the page.
/**
 * @param int $slot
 * @return AioGcGeom
 */
function aio_oc_frame_geom($slot)
{
    return aio_oc_frame_at(AIO_CATV_CARD_X + $slot * AIO_OC_EP_STEP, AIO_OC_ROW_Y, AIO_OC_EP_W, AIO_OC_EP_H);
}

// The frame of the focus, only in the zone of the row.
/**
 * @param string $z
 * @return list<AioGcComp>
 */
function aio_oc_frame($z)
{
    return $z === 'row' ? aio_oc_frame_rects(AIO_OC_EP_W, AIO_OC_EP_H) : array();
}

// The title and the line under it of episode $ep: "5. Бой товара" 30 px (in focus white, else grey,
// one not out yet dim) and aio_oc_ep_line 24 px dim.
/**
 * @param AioCardEp $ep
 * @param array{float, float, bool}|null $pr
 * @param bool $on
 * @param string $lang
 * @return list<AioGcComp>
 */
function aio_oc_ep_titles($ep, $pr, $on, $lang)
{
    $t = $ep['title'] !== '' ? "{$ep['e']}. {$ep['title']}" : strval($ep['e']);
    return array(
        aio_gc_cut(null, aio_gc_at(AIO_OC_EP_W, 40, 0, 0), aio_gc_cut_w(aio_clean($t), AIO_CATV_LINE_PX, AIO_OC_EP_W), AIO_CATV_LINE_PX,
            $ep['soon'] ? AIO_GC_DIM : ($on ? AIO_GC_TEXT : AIO_GC_TEXT2)),
        aio_gc_cut(null, aio_gc_at(AIO_OC_EP_W, 34, 0, 40), aio_oc_ep_line($ep, $pr, $lang), 24, AIO_GC_DIM));
}

// The still of episode $ep at index $i of its season: the frame of the server (loading: the
// picture of the shell; not out yet or none: the empty one of the shell), the bar of the progress
// at its foot if begun, the mark at the top right if watched; the titles under it ("tt<i>").
/**
 * @param AioOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param int $i
 * @param AioCardEp $ep
 * @param bool $on
 * @return AioGcComp
 */
function aio_oc_ep_card($c, $prog, $i, $ep, $on)
{
    $pr = aio_oc_prog($c, $prog, $ep);
    $img = aio_gc_image(aio_gc_at(AIO_OC_EP_W, AIO_OC_EP_H, 0, 0),
        $ep['soon'] || $ep['img'] === '' ? AIO_OC_ICONS . 'loading_episode_image.png' : $ep['img']);
    if (!$ep['soon'] && $ep['img'] !== '')
    {
        $img[GComponentDef::specific_def][GCompImageDef::not_loaded_url] = AIO_OC_ICONS . 'loading_episode_image.png';
        $img[GComponentDef::specific_def][GCompImageDef::load_failed_url] = AIO_OC_ICONS . 'no_episode_image.png';
    }
    $d = array(aio_gc_rect(aio_gc_at(AIO_OC_EP_W, AIO_OC_EP_H, 0, 0), AIO_OC_NO_PHOTO), $img);
    if ($pr && $pr[2])
    {
        $d[] = aio_gc_rect(aio_gc_at(44, 44, AIO_OC_EP_W - 52, 8), AIO_OC_MARK_BG);
        $d[] = aio_gc_image(aio_gc_at(40, 30, AIO_OC_EP_W - 50, 15), AIO_OC_ICONS . 'watched_small.png');
    }
    else if ($pr && $pr[0] > 0 && $pr[1] > 0)
    {
        $y = AIO_OC_EP_H - AIO_OC_BAR_H;
        $d[] = aio_gc_rect(aio_gc_at(AIO_OC_EP_W, AIO_OC_BAR_H, 0, $y), AIO_OC_BAR_REST);
        $d[] = aio_gc_rect(aio_gc_at(max(AIO_GC_BAR_MIN, intval(round(AIO_OC_EP_W * min(1, $pr[0] / $pr[1])))), AIO_OC_BAR_H, 0, $y),
            AIO_GC_TEXT);
    }
    $d[] = aio_gc_panel("tt$i", aio_gc_at(AIO_OC_EP_W, 74, 0, AIO_OC_EP_H + 12), aio_oc_ep_titles($ep, $pr, $on, $c['ui']));
    return aio_gc_panel("c$i", aio_gc_at(AIO_OC_EP_W, AIO_OC_ROW_H, $i * AIO_OC_EP_STEP, 0), $d);
}

// The stills $lo..$hi of season $s, the one at $focus lit (-1: none).
/**
 * @param AioOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param int $s
 * @param int $lo
 * @param int $hi
 * @param int $focus
 * @return list<AioGcComp>
 */
function aio_oc_ep_cards($c, $prog, $s, $lo, $hi, $focus)
{
    $eps = aio_oc_eps($c, $s);
    $d = array();
    for ($i = $lo; $i <= $hi; $i++)
        $d[] = aio_oc_ep_card($c, $prog, $i, $eps[$i], $i === $focus);
    return $d;
}

// The line "did not load" of a series without the meta, its text at the x of the catalog, as a
// tab in focus in zone $z of the tabs: ENTER asks again (aio_oc_retry).
/**
 * @param AioOcCard $c
 * @param string $z
 * @return AioGcComp
 */
function aio_oc_retry_panel($c, $z)
{
    $t = aio_tr($c['ui'], 'card_eps_failed');
    $w = aio_gc_w($t, AIO_CATV_LINE_PX) + 2 * AIO_OC_TAB_PAD;
    return aio_gc_panel('retry', aio_gc_at($w, AIO_OC_TAB_H, AIO_CATV_CARD_X - AIO_OC_TAB_PAD, AIO_OC_TAB_Y),
        aio_oc_tab_items($t, $w, $z === 'pill' ? 2 : 0));
}

// The tabs, the row and the frame of a series at place $pos, in the page; no meta: the line "did
// not load" (aio_oc_retry_panel).
/**
 * @param AioOcCard $c
 * @param AioOcLayout $l
 * @param AioOcPos $pos
 * @return list<AioGcComp>
 */
function aio_oc_series($c, $l, $pos)
{
    $ui = $c['ui'];
    if (!$c['meta'])
        return array(aio_oc_retry_panel($c, $pos['z']));
    $t = $l['tabs'];
    if (!$t['list'])
        return array();
    $d = array();
    if ($t['word'] > 0)
        $d[] = aio_gc_panel('tabh', aio_gc_at($t['x'] - $t['word'], AIO_OC_TAB_H, $t['word'], AIO_OC_TAB_Y),
            array(aio_gc_cut(null, aio_gc_left_center($t['x'] - $t['word'], 44, 0), aio_tr($ui, 'card_season'), AIO_CATV_LINE_PX,
                AIO_GC_TEXT2)));
    $tabs = array();
    foreach ($t['list'] as $k => $tab)
        $tabs[] = aio_gc_panel("t$k", aio_gc_at($tab[3], AIO_OC_TAB_H, $tab[2], 0), aio_oc_tab_items($tab[1], $tab[3],
            aio_oc_tab_state($pos, $tab[0])));
    $sh = aio_oc_tabs_shift($t, $pos['s']);
    $end = aio_oc_tabs_end($t, $sh);
    list($clip, $strip) = aio_oc_tabs_geoms($t, $sh);
    $d[] = aio_gc_panel('tabs', $clip, array(aio_gc_panel('tst', $strip, $tabs)), 0);
    if ($t['all'] > $t['w'])
    {
        foreach (aio_oc_chevs($t, $sh, $end, $pos['z'] === 'pill') as $id => $v)
            $d[] = aio_gc_panel($id, $v[0], $v[1]);
    }
    $d[] = aio_gc_panel('cnt', aio_gc_at(AIO_OC_HINT_R - $t['cx'], AIO_OC_TAB_H, $t['cx'], AIO_OC_TAB_Y),
        aio_oc_count($c, $pos['s'], $t));
    list($eps, $i, $off, $lo, $hi) = aio_oc_row($c, $pos);
    $n = count($eps);
    $d[] = aio_gc_panel('row', aio_gc_at(1920, AIO_OC_ROW_H, 0, AIO_OC_ROW_Y),
        array(aio_gc_panel('eps', aio_oc_eps_geom($n, $off), aio_oc_ep_cards($c, $l['prog'], $pos['s'], $lo, $hi,
            $pos['z'] === 'row' ? $i : -1))), 0);
    $d[] = aio_gc_panel('frame', aio_oc_frame_geom($i - $off), aio_oc_frame($pos['z']));
    return $d;
}

// --- The place of the focus.

// The episode of season $s the row puts the focus on: the one to offer if of this season, else
// the first not watched, else the first; 0 for no episodes.
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param int $s
 * @return int
 */
function aio_oc_def_e($c, $l, $s)
{
    $eps = aio_oc_eps($c, $s);
    if ($l['ep'] && $l['ep']['s'] === $s && aio_cm_ep_index($eps, $l['ep']['e']) >= 0)
        return $l['ep']['e'];
    foreach ($eps as $ep)
    {
        $pr = aio_oc_prog($c, $l['prog'], $ep);
        if (!$pr || !$pr[2])
            return $ep['e'];
    }
    return $eps ? $eps[0]['e'] : 0;
}

// The changes of the tabs and the row of a series from $old to $new. Another season: its tabs
// redrawn, the strip and its clip moved at once (a half tab would show in a motion), the count,
// all the stills of the panel at once; the chevrons as the strip and the zone have them. The same season,
// another episode: the panel moved (the stills it leaves in the first two slots: the frame moves),
// the titles lit and dimmed; a long season (aio_oc_win): the stills out of the window dropped, the
// new ones added after one kept (the places are of the panel, the order of its children does not
// matter) in the change that moves the panel; a window apart from the old one (ENTER on the
// button of an episode not out yet): all its stills at once, as another season. One change of a
// component a call (two of it jump with no motion, hrow_probe): the frame moved, shown in the zone
// of the row only, in one.
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param AioOcPos $old
 * @param AioOcPos $new
 * @return list<AioGcChange>
 */
function aio_oc_row_changes($c, $l, $old, $new)
{
    $ch = array();
    $t = $l['tabs'];
    foreach ($t['list'] as $k => $tab)
    {
        $st = aio_oc_tab_state($new, $tab[0]);
        if ($st !== aio_oc_tab_state($old, $tab[0]))
            $ch[] = aio_gc_change("t$k", null, GCOMP_TRANSITION_NONE, aio_oc_tab_items($tab[1], $tab[3], $st));
    }
    list($eps, $i, $off, $lo, $hi) = aio_oc_row($c, $new);
    list(, $oi, $ooff, $olo, $ohi) = aio_oc_row($c, $old);
    $n = count($eps);
    $lit = $new['z'] === 'row' ? $i : -1;
    $fg = null;
    $ft = GCOMP_TRANSITION_NONE;
    $sh = aio_oc_tabs_shift($t, $new['s']);
    $osh = aio_oc_tabs_shift($t, $old['s']);
    if ($sh !== $osh)
    {
        list($clip, $strip) = aio_oc_tabs_geoms($t, $sh);
        $ch[] = aio_gc_change('tabs', $clip, GCOMP_TRANSITION_NONE);
        $ch[] = aio_gc_change('tst', $strip, GCOMP_TRANSITION_NONE);
    }
    if ($t['all'] > $t['w'])
    {
        $och = aio_oc_chevs($t, $osh, aio_oc_tabs_end($t, $osh), $old['z'] === 'pill');
        foreach (aio_oc_chevs($t, $sh, aio_oc_tabs_end($t, $sh), $new['z'] === 'pill') as $id => $v)
        {
            if ($v !== $och[$id])
                $ch[] = aio_gc_change($id, $v[0], GCOMP_TRANSITION_NONE, $v[1]);
        }
    }
    if ($new['s'] !== $old['s'])
        $ch[] = aio_gc_change('cnt', null, GCOMP_TRANSITION_NONE, aio_oc_count($c, $new['s'], $t));
    if ($new['s'] !== $old['s'] || $lo > $ohi || $hi < $olo)
    {
        $ch[] = aio_gc_change('eps', aio_oc_eps_geom($n, $off), GCOMP_TRANSITION_NONE,
            aio_oc_ep_cards($c, $l['prog'], $new['s'], $lo, $hi, $lit));
        $fg = aio_oc_frame_geom($i - $off);
    }
    else
    {
        for ($k = $olo; $k <= $ohi; $k++)
        {
            if ($k < $lo || $k > $hi)
            {
                $x = aio_gc_change("c$k", null, GCOMP_TRANSITION_NONE);
                $x[ChangeGCompDef::remove] = true;
                $ch[] = $x;
            }
        }
        $add = array();
        for ($k = $lo; $k <= $hi; $k++)
        {
            if ($k < $olo || $k > $ohi)
                $add[] = aio_oc_ep_card($c, $l['prog'], $k, $eps[$k], $k === $lit);
        }
        if ($add || $off !== $ooff)
        {
            $x = aio_gc_change('eps', $off !== $ooff ? aio_oc_eps_geom($n, $off) : null, GCOMP_TRANSITION_DEFAULT, $add ? $add : null);
            if ($add)
            {
                $x[ChangeGCompDef::children_merge_mode] = true;
                $x[ChangeGCompDef::children_insert_after_id] = 'c' . min($hi, $ohi);
            }
            $ch[] = $x;
        }
        if ($i - $off !== $oi - $ooff)
        {
            $fg = aio_oc_frame_geom($i - $off);
            $ft = GCOMP_TRANSITION_DEFAULT;
        }
        $olit = $old['z'] === 'row' ? $oi : -1;
        // The titles of the stills kept in the panel; an added one is drawn lit or not already.
        foreach ($lit !== $olit ? array($olit, $lit) : array() as $k)
        {
            if ($k >= max($lo, $olo) && $k <= min($hi, $ohi))
                $ch[] = aio_gc_change("tt$k", null, GCOMP_TRANSITION_NONE,
                    aio_oc_ep_titles($eps[$k], aio_oc_prog($c, $l['prog'], $eps[$k]), $k === $lit, $c['ui']));
        }
    }
    // The frame shown or hidden jumps to its place.
    return array_merge($ch, aio_oc_frame_change('frame', $fg, $ft === GCOMP_TRANSITION_DEFAULT, $old['z'] === 'row', $new['z'] === 'row',
        AIO_OC_EP_W, AIO_OC_EP_H));
}

// --- Keys.

// ENTER on an episode not out yet: "Серия выйдет 16 ноября" (no date: "not out yet"), no streams asked.
/**
 * @param AioCardEp $ep
 * @param string $lang
 * @return AioAction
 */
function aio_oc_soon_msg($ep, $lang)
{
    $a = aio_error('card_ep_soon');
    $a[GuiAction::data][PluginShowErrorActionData::title] = $ep['date'] !== '' ?
        str_replace('{d}', aio_oc_day($ep['date'], $lang, false, false), aio_tr($lang, 'card_ep_soon')) : aio_tr($lang, 'card_ep_soon_nodate');
    return $a;
}

// --- Marks "watched" (update_watched_marks of the shell, uids aio_wh_mark_uid).

// The episodes out before episode $e of season $s of the meta of card $c, in its order (the
// seasons from 1, the episodes ascending), as asked (aio_cm_req_s): list of "q:e", $max at most (0:
// all).
/**
 * @param AioOcCard $c
 * @param int $s
 * @param int $e
 * @param int $max
 * @return list<string>
 */
function aio_oc_prev_eps($c, $s, $e, $max = 0)
{
    $out = array();
    if (!$c['meta'])
        return $out;
    foreach ($c['meta']['seasons'] as $sn => $eps)
    {
        if ($sn > $s)
            break;
        if ($sn < 1)
            continue;
        $q = aio_cm_req_s($c['meta'], $sn);
        foreach ($eps as $ep)
        {
            if ($sn === $s && $ep['e'] >= $e)
                break;
            if ($ep['soon'])
                continue;
            $out[] = "$q:{$ep['e']}";
            if ($max > 0 && count($out) >= $max)
                return $out;
        }
    }
    return $out;
}

// The items of MENU of an episode of series card $c ($key) with the focus at $pos in the row and
// the progress $prog of the screen, before "Settings": "Просмотрено" (not watched, out), "Не
// просмотрено" (watched by a mark: by the position no mark takes it off), "Отметить предыдущие"
// (an episode out before it). None elsewhere, without the meta or a card of the Dune (no history).
/**
 * @param string $key
 * @param AioOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param AioOcPos $pos
 * @return list<array<string, mixed>>
 */
function aio_oc_mark_items($key, $c, $prog, $pos)
{
    $x = $pos['z'] === 'row' && $c['dune'] !== '' && $c['meta'] ? aio_oc_ep($c, $pos['s'], $pos['e']) : null;
    if (!$x)
        return array();
    $pr = aio_oc_prog($c, $prog, $x);
    $w = '';
    if (!$pr || !$pr[2])
        $w = $x['soon'] ? '' : 'on';
    else if (!aio_wh_full($pr[0], $pr[1]))
        $w = 'off';
    $out = array();
    foreach (array($w, aio_oc_prev_eps($c, $pos['s'], $pos['e'], 1) ? 'prev' : '') as $k)
    {
        if ($k !== '')
            $out[] = array(
                GuiMenuItemDef::caption => aio_tr($c['ui'], "card_mark_$k"),
                GuiMenuItemDef::icon_url => AIO_OC_ICONS . 'watched_small.png',
                GuiMenuItemDef::action => aio_input('card_mark', array('c' => $key, 's' => strval($pos['s']),
                    'e' => strval($pos['e']), 'w' => $k)));
    }
    return $out;
}

// card_mark of MENU of an episode (aio_oc_mark_items: s, e of the meta, w: on, off, prev) of series
// card $c ($key) -> update_watched_marks: on/off the episode, prev the episodes out before it
// (aio_oc_prev_eps), the mark of the own card (aio_wh_mark_uid) of each and the uids of the records
// of our releases of it (aio_wh_ours_eps); then the card anew in place, the focus on the episode
// (the shell has written watched_files.txt by then). Nothing for a bad input.
/**
 * @param string $key
 * @param AioOcCard $c
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_oc_mark($key, $c, $in)
{
    $w = aio_catv_in($in, 'w');
    $s = aio_catv_in($in, 's');
    $e = aio_catv_in($in, 'e');
    if (!in_array($w, array('on', 'off', 'prev'), true) || !preg_match('/^[0-9]{1,5}$/D', $s) || !preg_match('/^[0-9]{1,5}$/D', $e) ||
        $c['dune'] === '' || !$c['meta'])
        return null;
    $s = intval($s);
    $e = intval($e);
    if (!aio_oc_ep($c, $s, $e))
        return null;
    $keys = $w === 'prev' ? aio_oc_prev_eps($c, $s, $e) : array(aio_cm_req_s($c['meta'], $s) . ":$e");
    if (!$keys)
        return null;
    $recs = aio_wh_ours_eps($c['dune'], $keys);
    $ids = array();
    $nr = 0;
    foreach ($keys as $k)
    {
        list($q, $n) = explode(':', $k);
        $ids[] = aio_wh_mark_uid($c['dune'], $q, intval($n));
        if (isset($recs[$k]))
        {
            $nr += count($recs[$k]);
            foreach ($recs[$k] as $u)
                $ids[] = $u;
        }
    }
    $pos = $c['pos'];
    AioOc::$cards[$key]['pos'] = array('z' => 'row', 'b' => $pos['b'], 'p' => 0, 's' => $s, 'e' => $e, 'k' => $pos['k']);
    aio_log("card: own $key: mark $w on S{$s}E{$e}: " . count($keys) . " episodes, $nr uids of records");
    return array(
        GuiAction::handler_string_id => UPDATE_WATCHED_MARKS_ACTION_ID,
        GuiAction::data => array(
            UpdateWatchedMarksActionData::item_ids => $ids,
            UpdateWatchedMarksActionData::movie_ids => array(),
            UpdateWatchedMarksActionData::watched => $w !== 'off',
            UpdateWatchedMarksActionData::post_action => aio_replace_act("card:$key", $c['tile']['name'])));
}

// ENTER on "did not load" of series card $c ($key): the meta asked again; got - the card anew with
// it, the screen replaced by it, the focus on the tabs; else the message.
/**
 * @param string $key
 * @param AioOcCard $c
 * @return AioAction
 */
function aio_oc_retry($key, $c)
{
    $g = aio_cm_get($c['tile']);
    if (!$g['meta'])
        return aio_error('cat_row_failed', $g['err']);
    $card = aio_oc_make($c['tile'], $g['meta'], $c['dune'], $c['lang']);
    $card['pos'] = array('z' => 'pill', 'b' => $c['pos']['b'], 'p' => 0, 's' => 0, 'e' => 0, 'k' => 0);
    aio_oc_keep($key, $card);
    aio_log("card: own $key: meta again, the screen anew");
    return aio_replace_act("card:$key", $c['tile']['name']);
}
