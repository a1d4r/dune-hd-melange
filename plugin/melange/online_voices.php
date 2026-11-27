<?php
// "Озвучка" of the online list of "Онлайн" (view_online.php): the side panel of the kit (kit_panel.php) with a check of
// each voice, the filter by voices - the red key and its tab (the panel itself), "Озвучка" of "Действия" (its submenu).
// The model of the filter (mel_lpv_voices, mel_lpv_shown, mel_lpv_flt_keep, mel_lpv_flt_name), its tab, its item of
// "Действия" and the dispatcher of lp_* are in view_online.php.
// PHP 5.3.6, firmware API only. Types (MelLpView, MelState): phpstan-types.neon.

// The id of the line of the voice of key $k in the panel "Озвучка": with a prefix (a key alone may be a number, and a
// number is no string key of an array).
/**
 * @param string $k
 * @return string
 */
function mel_lpv_flt_id($k)
{
    return 'v|' . $k;
}

// The panel "Озвучка" of the online list $st, a check list: "Все озвучки", a line a voice (mel_lpv_voices) with the
// number of its balancers on the right, those of the filter checked (no filter: none of them - "Все озвучки" alone is,
// the count "Выбрано: все"), the cursor on the first one checked (none: the first line, "Все озвучки"); "Готово":
// lp_flt_done (mel_lpv_flt_apply). No voice: null.
/**
 * @param MelState $st
 * @return MelKpSubDesc|null
 */
function mel_lpv_flt_panel($st)
{
    $lang = mel_gc_lang($st);
    $items = array();
    $cur = array();
    foreach (mel_lpv_voices($st['lp'], $lang) as $x)
    {
        $id = mel_lpv_flt_id($x['k']);
        $on = isset($st['lp']['flt'][$x['k']]);
        $items[] = array('id' => $id, 'label' => $x['name'], 'count' => strval($x['n']), 'on' => $on);
        if ($on && !$cur)
            $cur = array('cur' => $id);
    }
    if (!$items)
        return null;
    return array('title' => mel_tr($lang, 'lp_flt_title'), 'dim' => 'dim_strong', 'all' => mel_tr($lang, 'lp_flt_all'),
        'items' => $items, 'done' => array('count_all' => mel_tr($lang, 'kp_count_all')) +
            mel_lpv_done($lang, 'lp_flt_done', $st['rid'])) + $cur;
}

// lp_flt_menu (the red key) of the online list $st: the panel "Озвучка" over its screen (mel_lpv_flt_panel), the cursor
// of the list kept. No voice: nothing.
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_flt_open($in, $st)
{
    list($scr) = mel_gc_scr_live($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null, true);
    $d = mel_lpv_flt_panel($st);
    if (!$d)
    {
        mel_log('lampac: voices: none in the list, no panel');
        return null;
    }
    return mel_kp_open($scr, $d);
}

// lp_flt_done: "Готово" of the panel "Озвучка" of the online list $st (the panel itself or its submenu left with
// another choice) with choice $c of it (mel_kp_done; none: nothing). The voices checked (an id of no voice now goes) the
// filter, none or all of them no filter; the same as before: nothing. Else the list with it (mel_lpv_flt_set).
/**
 * @param MelState $st
 * @param MelKpChoice|null $c
 * @return MelAction|null
 */
function mel_lpv_flt_apply($st, $c)
{
    if (!$c)
    {
        mel_log('lampac: voices: no choice of the panel, nothing');
        return null;
    }
    $g = mel_lpv_voices($st['lp'], mel_gc_lang($st));
    $on = array_flip($c['on']);
    $set = array();
    foreach ($g as $x)
    {
        if (isset($on[mel_lpv_flt_id($x['k'])]))
            $set[$x['k']] = $x['name'];
    }
    if (count($set) === count($g))
    {
        mel_log('lampac: voices: all checked, no filter');
        $set = array();
    }
    $a = array_keys($set);
    $b = isset($st['lp']['flt']) ? array_keys($st['lp']['flt']) : array();
    sort($a);
    sort($b);
    if ($a === $b)
    {
        mel_log('lampac: voices: the same');
        return null;
    }
    return mel_lpv_flt_set($st, $set);
}

// The online list $st with filter $set (mel_lpv_voices key => name; none: all; some: all the balancers unfolded): the screen
// replaced (a new rid), the yellow line gone; the cursor on its row (on the line of a balancer: of it) when shown, else the
// first.
/**
 * @param MelState $st
 * @param array<string, string> $set
 * @return MelAction
 */
function mel_lpv_flt_set($st, $set)
{
    list($sel) = mel_lpv_pos($st['lp'], null);
    $m = mel_lpv_mark(mel_lpv_lines($st['lp']), $sel);
    $ns = $st;
    $ns['rid'] = mel_rid();
    unset($ns['lp']['flt']);
    // A filter: the rows of its voices shown, all unfolded.
    if ($set)
    {
        $ns['lp']['flt'] = $set;
        $ns['lp']['open'] = mel_lpv_open_all($ns['lp']);
    }
    $ns['lp']['note'] = '';
    unset($ns['lp']['flt_gone']);
    $L = mel_lpv_lines($ns['lp']);
    $j = -1;
    // On the line of a balancer: on its line (the line of its rows shown) when one of them is shown.
    if ($m[1])
    {
        $j = mel_lpv_bal_of($L, max(0, mel_lpv_line_at($ns['lp'], $L, $m[0])));
        $ok = mel_lpv_row_of($L, $j) >= 0 || mel_lpv_is_bal($L, $j);
        if (!$ok || $ns['lp']['rows'][$L[$j]['i']]['path'] !== $st['lp']['rows'][$m[0]]['path'])
            $j = -1;
    }
    else if (in_array($m[0], mel_lpv_shown($ns['lp']), true))
        $j = mel_lpv_line_at($ns['lp'], $L, $m[0]);
    $ns['lp']['gc'] = mel_lpv_put_line($L, max(0, $j));
    $ns['lp']['gc_new'] = true;
    mel_log('lampac: filter ' . ($set ? implode(', ', array_keys($set)) : 'off') . ', ' . count(mel_lpv_shown($ns['lp'])) .
        ' rows shown');
    return mel_list_replace($ns);
}
