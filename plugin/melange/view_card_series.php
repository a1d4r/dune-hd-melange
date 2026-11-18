<?php
// The seasons and the episodes of a series on the own card (H3 of design-own-card.md): the episode
// to offer and the progress of the episodes by the history, the tabs of the seasons, the row of
// the stills and its window, their changes by the keys. Split out of view_card.php (its constants
// and the rest of the screen there).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// --- The episodes.

// The line under the title of an episode: not out - "выйдет 16 ноября 2026" (no date: "ещё не
// вышла"); begun - "осталось 29 мин из 48 мин"; else "6 апр 2009 · 48 мин" (what of it is known; not $short:
// "6 апреля 2009").
/**
 * @param MelCardEp $ep
 * @param array{float, float, bool}|null $pr
 * @param string $lang
 * @param bool $short
 * @return string
 */
function mel_oc_ep_line($ep, $pr, $lang, $short = true)
{
    if ($ep['soon'])
        return $ep['date'] !== '' ? str_replace('{d}', mel_oc_day($ep['date'], $lang), mel_tr($lang, 'card_ep_coming')) :
            mel_tr($lang, 'card_ep_not_out');
    if ($pr && $pr[0] > 0 && !$pr[2] && $pr[1] > 0)
        return str_replace(array('{l}', '{t}'), array(mel_catv_runtime(intval(ceil(max(0, $pr[1] - $pr[0]) / 60)) . 'min', $lang),
            mel_catv_runtime(intval(round($pr[1] / 60)) . 'min', $lang)), mel_tr($lang, 'card_ep_left'));
    return implode(' · ', array_filter(array(mel_oc_day($ep['date'], $lang, $short),
        $ep['min'] > 0 ? mel_catv_runtime($ep['min'] . 'min', $lang) : ''), 'strlen'));
}

// The episodes of season $s of the meta of card $c, none without.
/**
 * @param MelOcCard $c
 * @param int $s
 * @return list<MelCardEp>
 */
function mel_oc_eps($c, $s)
{
    return $c['meta'] && isset($c['meta']['seasons'][$s]) ? $c['meta']['seasons'][$s] : array();
}

// The progress of episode $ep of card $c in history $prog (mel_wh_series: by its key, mel_cm_hkey), null for none.
/**
 * @param MelOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param MelCardEp $ep
 * @return array{float, float, bool}|null
 */
function mel_oc_prog($c, $prog, $ep)
{
    $k = $c['meta'] ? mel_cm_hkey($c['meta'], $ep['s'], $ep['e']) : "{$ep['s']}:{$ep['e']}";
    return isset($prog[$k]) ? $prog[$k] : null;
}

// The episode to offer of a series card (mel_wh_pick of its history and its marks by the episodes
// of the meta, those not out too; without the meta as play_action without season_numbers): q, e - as
// asked, s - the season of the meta (0: none has it); hash, key, prof (the profile of the record, v2), kind of
// mel_wh_pick. -> array(it, the progress
// of its episodes, mel_wh_series).
/**
 * @param MelOcCard $c
 * @return array{MelOcEp, array<string, array{float, float, bool}>}
 */
function mel_oc_pick($c)
{
    $m = $c['meta'];
    $st = $c['dune'] !== '' ? mel_wh_series($c['dune'], mel_wh_marked_set()) : array(null, array(), array());
    $p = mel_wh_pick($st, $c['dune'], $m ? mel_cm_lists($m) : array());
    $s = $m ? mel_cm_season_of($m, $p['s'], $p['e']) : 0;
    mel_log('card: own series: ' . ($p['rec'] !== '' ? "{$p['rec']}, " : '') . "S{$p['s']}E{$p['e']}, {$p['why']}" .
        ($m && $s !== $p['s'] ? ", season $s of the meta" : ''));
    return array(array('q' => $p['s'], 'e' => $p['e'], 's' => $s, 'hash' => $p['hash'], 'key' => $p['key'],
        'prof' => $p['prof'], 'kind' => $p['kind']), $st[1]);
}

// The episode $e of season $s of card $c, null for none.
/**
 * @param MelOcCard $c
 * @param int $s
 * @param int $e
 * @return MelCardEp|null
 */
function mel_oc_ep($c, $s, $e)
{
    $eps = mel_oc_eps($c, $s);
    $i = mel_cm_ep_index($eps, $e);
    return $i >= 0 ? $eps[$i] : null;
}

// The episode of the head of a series at place $pos (H3): the one in focus in the row, kept in "down" below it (the
// head is above the clip there: DOWN and UP do not redraw it); null at the buttons and the tabs.
/**
 * @param MelOcCard $c
 * @param MelOcPos $pos
 * @return MelCardEp|null
 */
function mel_oc_ep_on($c, $pos)
{
    return $pos['z'] === 'row' || $pos['z'] === 'down' ? mel_oc_ep($c, $pos['s'], $pos['e']) : null;
}

// The head of a series with the focus on episode $ep in the row (H3), under the logo: its title 48 px (none without),
// "S1 E2" (mel_se) white and "· 28 января 2008 · 49 мин" grey after it (not out yet: "· выйдет 16 ноября 2026", no
// description), its description in EP_LINES lines, "…" on the last; TEXT_W wide.
/**
 * @param MelOcCard $c
 * @param MelCardEp $ep
 * @return list<MelGcComp>
 */
function mel_oc_ep_head($c, $ep)
{
    $ui = $c['ui'];
    $x0 = MEL_CATV_CARD_X;
    $d = array();
    if ($ep['title'] !== '')
        $d[] = mel_gc_cut(null, mel_gc_at(MEL_CATV_TEXT_W, 66, $x0, MEL_OC_EP_TITLE_Y),
            mel_gc_cut_w(mel_clean($ep['title']), MEL_CATV_TITLE2_PX, MEL_CATV_TEXT_W), MEL_CATV_TITLE2_PX, MEL_CATV_TITLE_COLOR);
    $name = mel_se($ep['s'], $ep['e']);
    $when = mel_oc_ep_line($ep, null, $ui, false);
    $nw = intval(mel_gc_sum($name, MEL_CATV_LINE_PX));
    $d[] = mel_oc_label($x0, MEL_OC_EP_FACTS_Y, $nw, $name, MEL_CATV_LINE_PX, MEL_CATV_TITLE_COLOR);
    if ($when !== '')
    {
        $x = $x0 + $nw + intval(mel_gc_sum(' ', MEL_CATV_LINE_PX));
        $d[] = mel_oc_label($x, MEL_OC_EP_FACTS_Y, MEL_CATV_TEXT_W - $x + $x0,
            mel_gc_cut_w("· $when", MEL_CATV_LINE_PX, MEL_CATV_TEXT_W - $x + $x0), MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
    }
    foreach ($ep['soon'] ? array() : mel_gc_wrap(mel_clean($ep['desc']), MEL_CATV_LINE_PX, MEL_CATV_TEXT_W, MEL_OC_EP_LINES, true)
        as $i => $line)
        $d[] = mel_oc_label($x0, MEL_OC_EP_DESC_Y + $i * MEL_OC_DESC_LH_SER, MEL_CATV_TEXT_W, $line, MEL_CATV_LINE_PX,
            MEL_CATV_TEXT_COLOR);
    return $d;
}

// --- The seasons and the episodes of a series.

// The tabs of the seasons of the meta of card $c: list - array(season, text, x, width) in the
// strip, x - where it starts on the screen (at the x of the catalog, after "Сезон" when there are
// more than TABS_MAX: then the tabs are numbers; CHEV_SLOT further when not all fit, the slot of
// the left chevron), w - the width of its clip at most (all of it when all fit), all - the width
// of the strip, word - x of "Сезон", 0 for none, cx - x of the count: the widest of the seasons
// (mel_oc_count_text) ends at HINT_R, the strip CNT_GAP before it and
// CHEV_SLOT more for the right chevron when not all fit; one for the series, so nothing moves as
// the season changes.
/**
 * @param MelOcCard $c
 * @return MelOcTabs
 */
function mel_oc_tabs_layout($c)
{
    $ui = $c['ui'];
    $seasons = array_keys($c['meta']['seasons']);
    $x = MEL_CATV_CARD_X;
    $word = 0;
    $nums = count($seasons) > MEL_OC_TABS_MAX;
    if ($nums)
    {
        $word = $x;
        $x += mel_gc_w(mel_tr($ui, 'card_season'), MEL_CATV_LINE_PX) + 16;
    }
    $list = array();
    $tx = 0;
    $cw = 0;
    foreach ($seasons as $s)
    {
        $t = $nums ? strval($s) : str_replace('{n}', strval($s), mel_tr($ui, 'card_season_n'));
        $w = mel_gc_w($t, MEL_CATV_LINE_PX) + 2 * MEL_OC_TAB_PAD;
        $list[] = array($s, $t, $tx, $w);
        $tx += $w + MEL_OC_TAB_GAP;
        $cw = max($cw, mel_gc_w(mel_oc_count_text($c, $s), MEL_OC_CNT_PX));
    }
    $all = $list ? $tx - MEL_OC_TAB_GAP : 0;
    $cx = MEL_OC_HINT_R - $cw;
    $w = $cx - MEL_OC_CNT_GAP - $x;
    if ($all > $w)
    {
        $x += MEL_OC_CHEV_SLOT;
        $w -= 2 * MEL_OC_CHEV_SLOT;
    }
    return array('list' => $list, 'x' => $x, 'w' => min($all, $w), 'all' => $all, 'word' => $word, 'cx' => $cx);
}

// The shift of the strip of tabs $t with the tab of season $s: in the middle of its clip within
// the strip, then on to the start of a tab (the first at or after it): only whole tabs are seen
// (mel_oc_tabs_end); 0 when it all fits.
/**
 * @param MelOcTabs $t
 * @param int $s
 * @return int
 */
function mel_oc_tabs_shift($t, $s)
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
 * @param MelOcTabs $t
 * @param int $sh
 * @return int
 */
function mel_oc_tabs_end($t, $sh)
{
    $end = $sh;
    foreach ($t['list'] as $tab)
    {
        if ($tab[2] >= $sh && $tab[2] + $tab[3] <= $sh + $t['w'])
            $end = $tab[2] + $tab[3];
    }
    return $end;
}

// The clip of the strip of tabs $t at shift $sh (up to mel_oc_tabs_end) on the screen and the
// strip in it ('tabs' and 'tst').
/**
 * @param MelOcTabs $t
 * @param int $sh
 * @return array{MelGcGeom, MelGcGeom}
 */
function mel_oc_tabs_geoms($t, $sh)
{
    return array(mel_gc_at(mel_oc_tabs_end($t, $sh) - $sh, MEL_OC_TAB_H, $t['x'], MEL_OC_TAB_Y),
        mel_gc_at($t['all'], MEL_OC_TAB_H, -$sh, 0));
}

// The chevrons of the strip of tabs $t that does not fit, at shift $sh with its clip up to $end:
// the left one in its slot while there are seasons left of the clip, the right one CHEV_GAP after
// the clip while there are some right of it; grey, white with the focus on the tabs ($pill).
// -> id => array(geom, children), the children none when it is not seen.
/**
 * @param MelOcTabs $t
 * @param int $sh
 * @param int $end
 * @param bool $pill
 * @return array<string, array{MelGcGeom, list<MelGcComp>}>
 */
function mel_oc_chevs($t, $sh, $end, $pill)
{
    $y = MEL_OC_TAB_Y + intval((MEL_OC_TAB_H - MEL_OC_CHEV_H) / 2);
    $on = $pill ? 'on_' : '';
    $out = array();
    foreach (array('l' => array($t['x'] - MEL_OC_CHEV_GAP - MEL_OC_CHEV_W, $sh > 0),
        'r' => array($t['x'] + $end - $sh + MEL_OC_CHEV_GAP, $end < $t['all'])) as $k => $v)
        $out["ch$k"] = array(mel_gc_at(MEL_OC_CHEV_W, MEL_OC_CHEV_H, $v[0], $y), $v[1] ?
            array(mel_gc_image(mel_gc_at(MEL_OC_CHEV_W, MEL_OC_CHEV_H, 0, 0), MEL_OC_IMG . "chev_{$on}$k.png")) : array());
    return $out;
}

// The children of a tab (or of the line "did not load") of width $w: $state 0 - the text grey,
// 1 - the season shown (white 16 %), 2 - in focus.
/**
 * @param string $text
 * @param int $w
 * @param int $state
 * @return list<MelGcComp>
 */
function mel_oc_tab_items($text, $w, $state)
{
    $d = array();
    if ($state > 0)
    {
        $on = $state === 2 ? 'on_' : '';
        $d[] = mel_gc_image(mel_gc_at(MEL_OC_TAB_CAP, MEL_OC_TAB_H, 0, 0), MEL_OC_IMG . "tab_{$on}l.png");
        $d[] = mel_gc_rect(mel_gc_at($w - 2 * MEL_OC_TAB_CAP, MEL_OC_TAB_H, MEL_OC_TAB_CAP, 0), $state === 2 ? MEL_GC_SEL : MEL_OC_TAB_ON);
        $d[] = mel_gc_image(mel_gc_at(MEL_OC_TAB_CAP, MEL_OC_TAB_H, $w - MEL_OC_TAB_CAP, 0), MEL_OC_IMG . "tab_{$on}r.png");
    }
    $d[] = mel_gc_cut(null, mel_gc_left_center($w, 44, 0), $text, MEL_CATV_LINE_PX, $state > 0 ? MEL_GC_TEXT : MEL_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_CENTER));
    return $d;
}

// The state of the tab of season $s at place $pos (mel_oc_tab_items).
/**
 * @param MelOcPos $pos
 * @param int $s
 * @return int
 */
function mel_oc_tab_state($pos, $s)
{
    return $s !== $pos['s'] ? 0 : ($pos['z'] === 'pill' ? 2 : 1);
}

// The count of season $s: "Сезон 2 · 13 серий".
/**
 * @param MelOcCard $c
 * @param int $s
 * @return string
 */
function mel_oc_count_text($c, $s)
{
    $n = count(mel_oc_eps($c, $s));
    return str_replace(array('{s}', '{n}'), array(strval($s), $n . ' ' . mel_gc_plural($c['ui'], $n, 'card_eps')),
        mel_tr($c['ui'], 'card_season_count'));
}

// The count of season $s at the right of the line of the tabs $t, in its panel from cx to HINT_R.
/**
 * @param MelOcCard $c
 * @param int $s
 * @param MelOcTabs $t
 * @return list<MelGcComp>
 */
function mel_oc_count($c, $s, $t)
{
    return array(mel_gc_cut(null, mel_gc_left_center(MEL_OC_HINT_R - $t['cx'], 34, 0), mel_oc_count_text($c, $s),
        MEL_OC_CNT_PX, MEL_GC_TEXT2, array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// The stills in the panel of a season of $n at shift $off: all; a long one (more than WIN_MIN)
// those on screen and WIN each side. -> array(first, last).
/**
 * @param int $n
 * @param int $off
 * @return array{int, int}
 */
function mel_oc_win($n, $off)
{
    if ($n <= MEL_OC_WIN_MIN)
        return array(0, $n - 1);
    return array(max(0, $off - MEL_OC_WIN), min($n - 1, $off + MEL_OC_EP_VIS + MEL_OC_WIN));
}

// The strip of the stills of a season of $n ("e": "est", "efr"; the stills "c<i>", their titles
// "tt<i>"): EP_STEP apart from the x of the catalog, EP_VIS whole, the frame in the first two
// slots, then the row, the frame stays in the second; the frame round the still, in the page.
/**
 * @param int $n
 * @return MelOcStrip
 */
function mel_oc_eps_strip($n)
{
    return array('n' => $n, 'nc' => 0, 'w' => MEL_OC_EP_W, 'h' => MEL_OC_EP_H, 'cw' => MEL_OC_EP_STEP, 'ch' => MEL_OC_ROW_H,
        'step' => MEL_OC_EP_STEP, 'div' => 0, 'vis' => MEL_OC_EP_VIS, 'lead' => 1, 'x' => MEL_CATV_CARD_X, 'y' => MEL_OC_ROW_Y,
        'id' => 'e');
}

// The row of a series at place $pos: array(the episodes of its season, the index of its episode
// (0 for none), the first still at the left slot (mel_oc_strip_at), the stills of the panel
// mel_oc_win, the strip).
/**
 * @param MelOcCard $c
 * @param MelOcPos $pos
 * @return array{list<MelCardEp>, int, int, int, int, MelOcStrip}
 */
function mel_oc_row($c, $pos)
{
    $eps = mel_oc_eps($c, $pos['s']);
    $st = mel_oc_eps_strip(count($eps));
    list($i, $off) = mel_oc_strip_at($st, mel_cm_ep_index($eps, $pos['e']));
    list($lo, $hi) = mel_oc_win(count($eps), $off);
    return array($eps, $i, $off, $lo, $hi, $st);
}

// The title and the line under it of episode $ep of card $c: "5. Бой товара", of an anime numbered on with its
// number of the meta (mel_cin_n) "32 · 923. Бой товара", 30 px (in focus white, else grey, one not out yet dim;
// a long title cut at the end with "…") and mel_oc_ep_line 24 px dim.
/**
 * @param MelOcCard $c
 * @param MelCardEp $ep
 * @param array{float, float, bool}|null $pr
 * @param bool $on
 * @return list<MelGcComp>
 */
function mel_oc_ep_titles($c, $ep, $pr, $on)
{
    $abs = $c['meta'] ? mel_cin_n($c['meta']['cin'], $ep['s'], $ep['e']) : 0;
    $n = $ep['e'] . ($abs > 0 ? " · $abs" : '');
    $t = $ep['title'] !== '' ? "$n. {$ep['title']}" : $n;
    return array(
        mel_gc_cut(null, mel_gc_at(MEL_OC_EP_W, 40, 0, 0), mel_gc_cut_w(mel_clean($t), MEL_CATV_LINE_PX, MEL_OC_EP_W), MEL_CATV_LINE_PX,
            $ep['soon'] ? MEL_GC_DIM : ($on ? MEL_GC_TEXT : MEL_GC_TEXT2)),
        mel_gc_cut(null, mel_gc_at(MEL_OC_EP_W, 34, 0, 40), mel_oc_ep_line($ep, $pr, $c['ui']), 24, MEL_GC_DIM));
}

// The still of episode $ep at index $i of its season: the frame of the server (loading: the
// picture of the shell; not out yet or none: the empty one of the shell), the bar of the progress
// at its foot if begun, the mark at the top right if watched; the titles under it ("tt<i>").
/**
 * @param MelOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param int $i
 * @param MelCardEp $ep
 * @param bool $on
 * @return MelGcComp
 */
function mel_oc_ep_card($c, $prog, $i, $ep, $on)
{
    $pr = mel_oc_prog($c, $prog, $ep);
    $img = mel_gc_image(mel_gc_at(MEL_OC_EP_W, MEL_OC_EP_H, 0, 0),
        $ep['soon'] || $ep['img'] === '' ? MEL_OC_ICONS . 'loading_episode_image.png' : $ep['img']);
    if (!$ep['soon'] && $ep['img'] !== '')
    {
        $img[GComponentDef::specific_def][GCompImageDef::not_loaded_url] = MEL_OC_ICONS . 'loading_episode_image.png';
        $img[GComponentDef::specific_def][GCompImageDef::load_failed_url] = MEL_OC_ICONS . 'no_episode_image.png';
    }
    $d = array(mel_gc_rect(mel_gc_at(MEL_OC_EP_W, MEL_OC_EP_H, 0, 0), MEL_OC_NO_PHOTO), $img);
    if ($pr && $pr[2])
    {
        $d[] = mel_gc_rect(mel_gc_at(44, 44, MEL_OC_EP_W - 52, 8), MEL_OC_MARK_BG);
        $d[] = mel_gc_image(mel_gc_at(40, 30, MEL_OC_EP_W - 50, 15), MEL_OC_ICONS . 'watched_small.png');
    }
    else if ($pr && $pr[0] > 0 && $pr[1] > 0)
    {
        $y = MEL_OC_EP_H - MEL_OC_BAR_H;
        $d[] = mel_gc_rect(mel_gc_at(MEL_OC_EP_W, MEL_OC_BAR_H, 0, $y), MEL_OC_BAR_REST);
        $d[] = mel_gc_rect(mel_gc_at(max(MEL_GC_BAR_MIN, intval(round(MEL_OC_EP_W * min(1, $pr[0] / $pr[1])))), MEL_OC_BAR_H, 0, $y),
            MEL_GC_TEXT);
    }
    $d[] = mel_gc_panel("tt$i", mel_gc_at(MEL_OC_EP_W, 74, 0, MEL_OC_EP_H + 12), mel_oc_ep_titles($c, $ep, $pr, $on));
    return mel_gc_panel("c$i", mel_gc_at(MEL_OC_EP_W, MEL_OC_ROW_H, $i * MEL_OC_EP_STEP, 0), $d);
}

// The stills $lo..$hi of season $s, the one at $focus lit (-1: none).
/**
 * @param MelOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param int $s
 * @param int $lo
 * @param int $hi
 * @param int $focus
 * @return list<MelGcComp>
 */
function mel_oc_ep_cards($c, $prog, $s, $lo, $hi, $focus)
{
    $eps = mel_oc_eps($c, $s);
    $d = array();
    for ($i = $lo; $i <= $hi; $i++)
        $d[] = mel_oc_ep_card($c, $prog, $i, $eps[$i], $i === $focus);
    return $d;
}

// The line "did not load" of a series without the meta, its text at the x of the catalog, as a
// tab in focus in zone $z of the tabs: ENTER asks again (mel_oc_retry).
/**
 * @param MelOcCard $c
 * @param string $z
 * @return MelGcComp
 */
function mel_oc_retry_panel($c, $z)
{
    $t = mel_tr($c['ui'], 'card_eps_failed');
    $w = mel_gc_w($t, MEL_CATV_LINE_PX) + 2 * MEL_OC_TAB_PAD;
    return mel_gc_panel('retry', mel_gc_at($w, MEL_OC_TAB_H, MEL_CATV_CARD_X - MEL_OC_TAB_PAD, MEL_OC_TAB_Y),
        mel_oc_tab_items($t, $w, $z === 'pill' ? 2 : 0));
}

// The tabs, the row and the frame of a series at place $pos, in the page; no meta: the line "did
// not load" (mel_oc_retry_panel).
/**
 * @param MelOcCard $c
 * @param MelOcLayout $l
 * @param MelOcPos $pos
 * @return list<MelGcComp>
 */
function mel_oc_series($c, $l, $pos)
{
    $ui = $c['ui'];
    if (!$c['meta'])
        return array(mel_oc_retry_panel($c, $pos['z']));
    $t = $l['tabs'];
    if (!$t['list'])
        return array();
    $d = array();
    if ($t['word'] > 0)
        $d[] = mel_gc_panel('tabh', mel_gc_at($t['x'] - $t['word'], MEL_OC_TAB_H, $t['word'], MEL_OC_TAB_Y),
            array(mel_gc_cut(null, mel_gc_left_center($t['x'] - $t['word'], 44, 0), mel_tr($ui, 'card_season'), MEL_CATV_LINE_PX,
                MEL_GC_TEXT2)));
    $tabs = array();
    foreach ($t['list'] as $k => $tab)
        $tabs[] = mel_gc_panel("t$k", mel_gc_at($tab[3], MEL_OC_TAB_H, $tab[2], 0), mel_oc_tab_items($tab[1], $tab[3],
            mel_oc_tab_state($pos, $tab[0])));
    $sh = mel_oc_tabs_shift($t, $pos['s']);
    $end = mel_oc_tabs_end($t, $sh);
    list($clip, $strip) = mel_oc_tabs_geoms($t, $sh);
    $d[] = mel_gc_panel('tabs', $clip, array(mel_gc_panel('tst', $strip, $tabs)), 0);
    if ($t['all'] > $t['w'])
    {
        foreach (mel_oc_chevs($t, $sh, $end, $pos['z'] === 'pill') as $id => $v)
            $d[] = mel_gc_panel($id, $v[0], $v[1]);
    }
    $d[] = mel_gc_panel('cnt', mel_gc_at(MEL_OC_HINT_R - $t['cx'], MEL_OC_TAB_H, $t['cx'], MEL_OC_TAB_Y),
        mel_oc_count($c, $pos['s'], $t));
    list(, $i, , $lo, $hi, $st) = mel_oc_row($c, $pos);
    $d[] = mel_gc_panel('row', mel_gc_at(1920, MEL_OC_ROW_H, 0, MEL_OC_ROW_Y),
        array(mel_oc_strip_panel($st, $i, mel_oc_ep_cards($c, $l['prog'], $pos['s'], $lo, $hi, $pos['z'] === 'row' ? $i : -1))), 0);
    $d[] = mel_oc_strip_frame($st, $i, $pos['z'] === 'row');
    return $d;
}

// --- The place of the focus.

// The episode of season $s the row puts the focus on: the one to offer if of this season, else
// the first not watched, else the first; 0 for no episodes.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param int $s
 * @return int
 */
function mel_oc_def_e($c, $l, $s)
{
    $eps = mel_oc_eps($c, $s);
    if ($l['ep'] && $l['ep']['s'] === $s && mel_cm_ep_index($eps, $l['ep']['e']) >= 0)
        return $l['ep']['e'];
    foreach ($eps as $ep)
    {
        $pr = mel_oc_prog($c, $l['prog'], $ep);
        if (!$pr || !$pr[2])
            return $ep['e'];
    }
    return $eps ? $eps[0]['e'] : 0;
}

// The changes of the tabs and the row of a series from $old to $new. Another season: its tabs
// redrawn, the strip and its clip moved at once (a half tab would show in a motion), the count,
// all the stills of the panel at once; the chevrons as the strip and the zone have them. The same season,
// another episode: the panel moved (the stills it leaves in the first two slots: the frame moves),
// the titles lit and dimmed; a long season (mel_oc_win): the stills out of the window dropped, the
// new ones added after one kept (the places are of the panel, the order of its children does not
// matter) in the change that moves the panel; a window apart from the old one (ENTER on the
// button of an episode not out yet): all its stills at once, as another season. One change of a
// component a call (two of it jump with no motion, hrow_probe): the frame moved, shown in the zone
// of the row only, in one.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $old
 * @param MelOcPos $new
 * @return list<MelGcChange>
 */
function mel_oc_row_changes($c, $l, $old, $new)
{
    $ch = array();
    $t = $l['tabs'];
    foreach ($t['list'] as $k => $tab)
    {
        $st = mel_oc_tab_state($new, $tab[0]);
        if ($st !== mel_oc_tab_state($old, $tab[0]))
            $ch[] = mel_gc_change("t$k", null, GCOMP_TRANSITION_NONE, mel_oc_tab_items($tab[1], $tab[3], $st));
    }
    list($eps, $i, $off, $lo, $hi, $st) = mel_oc_row($c, $new);
    list(, $oi, $ooff, $olo, $ohi) = mel_oc_row($c, $old);
    $lit = $new['z'] === 'row' ? $i : -1;
    $fg = null;
    $ft = GCOMP_TRANSITION_NONE;
    $sh = mel_oc_tabs_shift($t, $new['s']);
    $osh = mel_oc_tabs_shift($t, $old['s']);
    if ($sh !== $osh)
    {
        list($clip, $strip) = mel_oc_tabs_geoms($t, $sh);
        $ch[] = mel_gc_change('tabs', $clip, GCOMP_TRANSITION_NONE);
        $ch[] = mel_gc_change('tst', $strip, GCOMP_TRANSITION_NONE);
    }
    if ($t['all'] > $t['w'])
    {
        $och = mel_oc_chevs($t, $osh, mel_oc_tabs_end($t, $osh), $old['z'] === 'pill');
        foreach (mel_oc_chevs($t, $sh, mel_oc_tabs_end($t, $sh), $new['z'] === 'pill') as $id => $v)
        {
            if ($v !== $och[$id])
                $ch[] = mel_gc_change($id, $v[0], GCOMP_TRANSITION_NONE, $v[1]);
        }
    }
    if ($new['s'] !== $old['s'])
        $ch[] = mel_gc_change('cnt', null, GCOMP_TRANSITION_NONE, mel_oc_count($c, $new['s'], $t));
    if ($new['s'] !== $old['s'] || $lo > $ohi || $hi < $olo)
    {
        $ch[] = mel_gc_change('est', mel_oc_strip_geom($st, $off), GCOMP_TRANSITION_NONE,
            mel_oc_ep_cards($c, $l['prog'], $new['s'], $lo, $hi, $lit));
        $fg = mel_oc_strip_frame_geom($st, $i, $off);
    }
    else
    {
        for ($k = $olo; $k <= $ohi; $k++)
        {
            if ($k < $lo || $k > $hi)
            {
                $x = mel_gc_change("c$k", null, GCOMP_TRANSITION_NONE);
                $x[ChangeGCompDef::remove] = true;
                $ch[] = $x;
            }
        }
        $add = array();
        for ($k = $lo; $k <= $hi; $k++)
        {
            if ($k < $olo || $k > $ohi)
                $add[] = mel_oc_ep_card($c, $l['prog'], $k, $eps[$k], $k === $lit);
        }
        if ($add || $off !== $ooff)
        {
            $x = mel_gc_change('est', $off !== $ooff ? mel_oc_strip_geom($st, $off) : null, GCOMP_TRANSITION_DEFAULT, $add ? $add : null);
            if ($add)
            {
                $x[ChangeGCompDef::children_merge_mode] = true;
                $x[ChangeGCompDef::children_insert_after_id] = 'c' . min($hi, $ohi);
            }
            $ch[] = $x;
        }
        if ($i - $off !== $oi - $ooff)
        {
            $fg = mel_oc_strip_frame_geom($st, $i, $off);
            $ft = GCOMP_TRANSITION_DEFAULT;
        }
        $olit = $old['z'] === 'row' ? $oi : -1;
        // The titles of the stills kept in the panel; an added one is drawn lit or not already.
        foreach ($lit !== $olit ? array($olit, $lit) : array() as $k)
        {
            if ($k >= max($lo, $olo) && $k <= min($hi, $ohi))
                $ch[] = mel_gc_change("tt$k", null, GCOMP_TRANSITION_NONE,
                    mel_oc_ep_titles($c, $eps[$k], mel_oc_prog($c, $l['prog'], $eps[$k]), $k === $lit));
        }
    }
    // The frame shown or hidden jumps to its place.
    return array_merge($ch, mel_oc_frame_change('efr', $fg, $ft === GCOMP_TRANSITION_DEFAULT, $old['z'] === 'row', $new['z'] === 'row',
        MEL_OC_EP_W, MEL_OC_EP_H));
}

// --- Keys.

// ENTER on an episode not out yet: "Серия выйдет 16 ноября" (no date: "not out yet"), no streams asked.
/**
 * @param MelCardEp $ep
 * @param string $lang
 * @return MelAction
 */
function mel_oc_soon_msg($ep, $lang)
{
    $a = mel_error('card_ep_soon');
    $a[GuiAction::data][PluginShowErrorActionData::title] = $ep['date'] !== '' ?
        str_replace('{d}', mel_oc_day($ep['date'], $lang, false, false), mel_tr($lang, 'card_ep_soon')) : mel_tr($lang, 'card_ep_soon_nodate');
    return $a;
}

// --- Marks "watched" (update_watched_marks of the shell, uids mel_wh_mark_uid).

// The episodes out before episode $e of season $s of the meta of card $c, in its order (the
// seasons from 1, the episodes ascending), by their keys (mel_cm_hkey): list of "q:e", $max at most (0: all).
/**
 * @param MelOcCard $c
 * @param int $s
 * @param int $e
 * @param int $max
 * @return list<string>
 */
function mel_oc_prev_eps($c, $s, $e, $max = 0)
{
    return $c['meta'] ? mel_cm_eps_before($c['meta'], $s, $e, $max) : array();
}

// The episodes out before episode $e of season $s of meta $m (all of them: PHP_INT_MAX, 0), as mel_oc_prev_eps.
/**
 * @param MelCardMeta $m
 * @param int $s
 * @param int $e
 * @param int $max
 * @return list<string>
 */
function mel_cm_eps_before($m, $s, $e, $max = 0)
{
    $out = array();
    foreach ($m['seasons'] as $sn => $eps)
    {
        if ($sn > $s)
            break;
        if ($sn < 1)
            continue;
        foreach ($eps as $ep)
        {
            if ($sn === $s && $ep['e'] >= $e)
                break;
            if ($ep['soon'])
                continue;
            $out[] = mel_cm_hkey($m, $sn, $ep['e']);
            if ($max > 0 && count($out) >= $max)
                return $out;
        }
    }
    return $out;
}

// The items of MENU of an episode of series card $c ($key) with the focus at $pos in the row and
// the progress $prog of the screen, before "Settings": "Отметить просмотренной" (not watched, out),
// "Снять отметку" (watched by a mark: by the position no mark takes it off), "Отметить предыдущие"
// (an episode out before it). None elsewhere, without the meta or a card of the Dune (no history).
/**
 * @param string $key
 * @param MelOcCard $c
 * @param array<string, array{float, float, bool}> $prog
 * @param MelOcPos $pos
 * @return list<array<string, mixed>>
 */
function mel_oc_mark_items($key, $c, $prog, $pos)
{
    $x = $pos['z'] === 'row' && $c['dune'] !== '' && $c['meta'] ? mel_oc_ep($c, $pos['s'], $pos['e']) : null;
    if (!$x)
        return array();
    $pr = mel_oc_prog($c, $prog, $x);
    $w = '';
    if (!$pr || !$pr[2])
        $w = $x['soon'] ? '' : 'on';
    else if (!mel_wh_full($pr[0], $pr[1]))
        $w = 'off';
    $out = array();
    foreach (array($w, mel_oc_prev_eps($c, $pos['s'], $pos['e'], 1) ? 'prev' : '') as $k)
    {
        if ($k !== '')
            $out[] = array(
                GuiMenuItemDef::caption => mel_tr($c['ui'], "card_mark_$k"),
                GuiMenuItemDef::icon_url => MEL_OC_ICONS . 'watched_small.png',
                GuiMenuItemDef::action => mel_input('card_mark', array('c' => $key, 's' => strval($pos['s']),
                    'e' => strval($pos['e']), 'w' => $k)));
    }
    return $out;
}

// card_mark of MENU of an episode (mel_oc_mark_items: s, e of the meta, w: on, off, prev) of series
// card $c ($key) -> update_watched_marks (mel_pg_mark_act): on/off the episode, prev the episodes out before it
// (mel_oc_prev_eps), the mark of the own card of each and the uids of the records of our releases of it
// (mel_wh_mark_ids); then mel_oc_mark_post, the focus on the episode (the shell has written watched_files.txt by
// then). Nothing for a bad input.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_oc_mark($key, $c, $in)
{
    $w = mel_catv_in($in, 'w');
    $s = mel_catv_in($in, 's');
    $e = mel_catv_in($in, 'e');
    if (!in_array($w, array('on', 'off', 'prev'), true) || !preg_match('/^[0-9]{1,5}$/D', $s) || !preg_match('/^[0-9]{1,5}$/D', $e) ||
        $c['dune'] === '' || !$c['meta'])
        return null;
    $s = intval($s);
    $e = intval($e);
    if (!mel_oc_ep($c, $s, $e))
        return null;
    $keys = $w === 'prev' ? mel_oc_prev_eps($c, $s, $e) : array(mel_cm_hkey($c['meta'], $s, $e));
    if (!$keys)
        return null;
    $ids = mel_wh_mark_ids($c['dune'], $keys);
    MelOc::$cards[$key]['pos'] = array_merge($c['pos'], array('z' => 'row', 'p' => 0, 's' => $s, 'e' => $e));
    mel_log("card: own $key: mark $w on S{$s}E{$e}: " . count($keys) . ' episodes, ' . (count($ids) - count($keys)) .
        ' uids of records');
    return mel_pg_mark_act($ids, array(), $w !== 'off', mel_oc_mark_post($key, $c));
}

// ENTER on "did not load" of series card $c ($key): the meta asked again; got - the card anew with
// it (mel_oc_build: the tile filled of it, its rows of posters; the card of the Dune as it was, its
// rating of Kinopoisk not asked), the screen replaced by it, the focus on the tabs; else the message.
/**
 * @param string $key
 * @param MelOcCard $c
 * @return MelAction
 */
function mel_oc_retry($key, $c)
{
    $g = mel_cm_get($c['tile']);
    if (!$g['meta'])
        return mel_error('cat_row_failed', $g['err']);
    $card = mel_oc_build($key, $c['tile'], $g['meta'], array('id' => $c['dune'], 'none' => false, 'kp' => ''), $c['lang']);
    MelOc::$cards[$key]['pos'] = array_merge($card['pos'], array('z' => 'pill', 'b' => $c['pos']['b']));
    mel_log("card: own $key: meta again, the screen anew");
    return mel_replace_act("card:$key", $c['tile']['name']);
}
