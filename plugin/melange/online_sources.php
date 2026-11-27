<?php
// "Балансеры" of the online list of "Онлайн" (view_online.php): which sources of Lampac it shows and in which
// order - lampac_sources of settings.json, the side panel of the kit (kit_panel.php) with checks and "Переместить", a
// submenu of "Действия". Its item of "Действия" and the dispatcher of lp_* are in view_online.php, the search
// (mel_lpv_srcs_pick in mel_lpv_tick, mel_lpv_srcs_seen in mel_lpv_run_seen of mel_lpv_run_end) in online_search.php.
// PHP 5.3.6, firmware API only. Types (MelLpChoice, MelLpView): phpstan-types.neon.

// --- "Балансеры" ("Действия" of the online list): which sources of Lampac the list shows and in which order. Its
// lines: the sources Lampac has found on this Dune (lampac_sources of settings.json: a path, its name, on), one
// list and one order for all titles; a new one on, last. The key of a source is its path (mel_lp_path: "phantom"
// of /lite/phantom), as in the key of a row and the history ("Continue" asks that balancer alone, mel_lp_find:
// a source turned off still plays from there).

// The sources $sources of a search (MelLpSource) by choice $choice (lampac_sources): those turned off out, the
// others in its order, one not there (a new one, of no path) after them, in their order. -> array(the sources,
// the paths of those turned off).
/**
 * @param list<MelLpSource> $sources
 * @param list<MelLpChoice> $choice
 * @return array{list<MelLpSource>, list<string>}
 */
function mel_lpv_srcs_pick($sources, $choice)
{
    $pos = array();
    foreach ($choice as $i => $c)
        $pos[$c['path']] = $c['on'] ? $i : -1;
    $keep = array();
    $off = array();
    foreach ($sources as $i => $x)
    {
        $p = $x['path'] !== '' && isset($pos[$x['path']]) ? $pos[$x['path']] : count($choice) + $i;
        if ($p < 0)
            $off[] = $x['path'];
        else
            $keep[] = array($p, $i);
    }
    sort($keep);
    $out = array();
    foreach ($keep as $k)
        $out[] = $sources[$k[1]];
    return array($out, array_values(array_unique($off)));
}

// The sources $sources of a search not in choice $choice, each once, in their order: entries of lampac_sources, on, the
// name of their label (mel_lp_src_name); a path not of mel_lp_src_path_ok none. A source there keeps the name of its
// first sight: another label of it changes nothing (settings.json is not written at every search).
/**
 * @param list<MelLpChoice> $choice
 * @param list<MelLpSource> $sources
 * @return list<MelLpChoice>
 */
function mel_lpv_srcs_new($choice, $sources)
{
    $at = array();
    foreach ($choice as $c)
        $at[$c['path']] = true;
    $out = array();
    foreach ($sources as $x)
    {
        $p = $x['path'];
        if (isset($at[$p]) || !mel_lp_src_path_ok($p))
            continue;
        $at[$p] = true;
        $name = mel_lp_src_name($x['label']);
        $out[] = array('path' => $p, 'name' => $name !== '' ? $name : mel_lp_src_name($p), 'on' => true);
    }
    return $out;
}

// The longest body of the settings of an import of the settings page (0.62.0: "Мой список" goes over it):
// MEL_CGI_BAK_SETTINGS_MAX of cgi/lib/backup.php (not loaded here).
define('MEL_LPV_SRCS_IMPORT_MAX', 32768);

// The export of settings $s (mel_settings_read) loads back on the settings page, as mel_cgi_import_fits of
// cgi/lib/backup.php has it: the body of its file (mel_cgi_export_json: web_token of 32 hex characters, any of the
// same length) not over MEL_LPV_SRCS_IMPORT_MAX. So settings.json stays within MEL_SETTINGS_MAX too.
/**
 * @param MelSettings $s
 * @return bool
 */
function mel_lpv_srcs_fits($s)
{
    $json = mel_settings_json($s + array('web_token' => str_repeat('0', 32)));
    return $json !== '' && strlen('settings=' . rawurlencode($json)) <= MEL_LPV_SRCS_IMPORT_MAX;
}

// lampac_sources $choice into Mel::$settings and settings.json - of the profile of Lampac of the operation (its id
// lp_prof; v2: each Lampac its own balancers), the file read again just before (a source of the file not in $choice
// kept, last), then the new ones $add (mel_lpv_srcs_new) after them, each while there is room (MEL_LP_SEEN_MAX) and
// the export of the settings with it loads back (mel_lpv_srcs_fits: the whole file, all the profiles); those not kept -
// one line. Written as the quick setup does (mel_settings_write); $add, none of it kept, or the profile gone (the
// page deleted it meanwhile): nothing written. -> the number of $add kept; -1: not written.
/**
 * @param list<MelLpChoice> $choice
 * @param list<MelLpChoice> $add
 * @return int
 */
function mel_lpv_srcs_save($choice, $add = array())
{
    Mel::$settings['lp_src'] = $choice;
    $dir = mel_data_dir();
    $why = 'no data_dir';
    $s = $dir !== '' ? mel_settings_read($dir, $why) : null;
    if (!$s || $why !== '')
    {
        mel_log('lampac: sources: settings.json not read, not written');
        return -1;
    }
    $k = mel_profile_index($s, Mel::$settings['lp_prof']);
    if ($k < 0 || mel_profile_rel($s['profiles'][$k]))
    {
        mel_log('lampac: sources: the profile of Lampac gone, not written');
        return -1;
    }
    $have = array();
    foreach ($choice as $c)
        $have[$c['path']] = true;
    foreach ($s['profiles'][$k]['lampac_sources'] as $c)
    {
        if (!isset($have[$c['path']]) && count($choice) < MEL_LP_SEEN_MAX)
        {
            $choice[] = $c;
            $have[$c['path']] = true;
        }
    }
    $s['profiles'][$k]['lampac_sources'] = $choice;
    $full = 0;
    $big = 0;
    $n = 0;
    foreach ($add as $c)
    {
        if (isset($have[$c['path']]))
            continue;
        $t = $s;
        $t['profiles'][$k]['lampac_sources'][] = $c;
        if (count($s['profiles'][$k]['lampac_sources']) >= MEL_LP_SEEN_MAX)
            $full++;
        else if (!mel_lpv_srcs_fits($t))
            $big++;
        else
        {
            $s = $t;
            $have[$c['path']] = true;
            $n++;
        }
    }
    if ($full + $big > 0)
        mel_log('lampac: sources: ' . ($full + $big) . ' new not kept (' . MEL_LP_SEEN_MAX . " at most: $full, the export " .
            'of the settings over ' . MEL_LPV_SRCS_IMPORT_MAX . " bytes: $big)");
    Mel::$settings['lp_src'] = $s['profiles'][$k]['lampac_sources'];
    if ($add && $n === 0)
        return 0;
    if (!mel_settings_write($s))
    {
        mel_log('lampac: sources: settings.json not written');
        return -1;
    }
    return $n;
}

// The sources $sources of a search done: those new into lampac_sources (mel_lpv_srcs_new, mel_lpv_srcs_save).
/**
 * @param list<MelLpSource> $sources
 * @return void
 */
function mel_lpv_srcs_seen($sources)
{
    $new = mel_lpv_srcs_new(Mel::$settings['lp_src'], $sources);
    if (!$new)
        return;
    $n = mel_lpv_srcs_save(Mel::$settings['lp_src'], $new);
    if ($n > 0)
        mel_log("lampac: sources: $n new, " . count(Mel::$settings['lp_src']) . ' in all, saved');
}

// The id of the line of the source of path $p in the panel "Балансеры": with a prefix (a path alone may be a number,
// and a number is no string key of an array).
/**
 * @param string $p
 * @return string
 */
function mel_lpv_srcs_id($p)
{
    return 's|' . $p;
}

// The panel "Балансеры" of the online list $st, a check list: "Все балансеры" (checked when all are on, ENTER turns
// them all on), the sources of lampac_sources in their order by their names, those on checked, "Переместить", the
// cursor on the source of the row under the cursor of the list (its gc) when it is on, else on the first source;
// "Готово": lp_srcs_done (mel_lpv_srcs_apply). No choice saved: null.
/**
 * @param MelState $st
 * @return MelKpSubDesc|null
 */
function mel_lpv_srcs_panel($st)
{
    $lang = $st['lang'];
    list($sel) = mel_lpv_pos($st['lp'], null);
    list($i) = mel_lpv_mark(mel_lpv_lines($st['lp']), $sel);
    $at = isset($st['lp']['rows'][$i]) ? $st['lp']['rows'][$i]['path'] : null;
    $items = array();
    $cur = array();
    foreach (Mel::$settings['lp_src'] as $c)
    {
        $items[] = array('id' => mel_lpv_srcs_id($c['path']), 'label' => $c['name'], 'on' => $c['on']);
        if ($c['on'] && $c['path'] === $at)
            $cur = array('cur' => mel_lpv_srcs_id($c['path']));
    }
    if (!$items)
        return null;
    return array('title' => mel_tr($lang, 'lp_srcs_title'), 'dim' => 'dim_strong', 'all' => mel_tr($lang, 'lp_srcs_all'),
        'all_mode' => 'every', 'items' => $items, 'move' => mel_tr($lang, 'kp_move'),
        'done' => mel_lpv_done($lang, 'lp_srcs_done', $st['rid'])) + $cur + array('cur' => $items[0]['id']);
}

// View $v by choice $choice (lampac_sources after "Балансеры"), no request: its sources and rows of those turned
// off out, the others in the order of the choice (the rows of a source in their order), those asked before those
// not ("Ещё N"), the reasons of those off out. -> array(the view; the paths of its sources turned on that were off
// in its search: not asked, a search anew; a row of $v => its row now).
/**
 * @param MelLpView $v
 * @param list<MelLpChoice> $choice
 * @return array{MelLpView, list<string>, array<int, int>}
 */
function mel_lpv_srcs_view($v, $choice)
{
    $asked = array();
    foreach (array_slice($v['sources'], 0, $v['next']) as $x)
        $asked[$x['url']] = true;
    list($srcs, $off) = mel_lpv_srcs_pick($v['sources'], $choice);
    $now = array();
    foreach ($choice as $c)
        $now[$c['path']] = $c['on'];
    $back = array();
    foreach (isset($v['off']) ? $v['off'] : array() as $p)
    {
        if (isset($now[$p]) && $now[$p])
            $back[] = $p;
        else
            $off[] = $p;
    }
    $a = array();
    $u = array();
    $pos = array();
    foreach ($srcs as $i => $x)
    {
        $pos[$x['path']] = $i;
        if (isset($asked[$x['url']]))
            $a[] = $x;
        else
            $u[] = $x;
    }
    $v['sources'] = array_merge($a, $u);
    $v['next'] = count($a);
    $v['left'] = count($u);
    $v['off'] = array_values(array_unique($off));
    $keep = array();
    foreach ($v['rows'] as $i => $r)
    {
        if (isset($pos[$r['path']]))
            $keep[] = array($pos[$r['path']], $i);
    }
    sort($keep);
    $rows = array();
    $map = array();
    foreach ($keep as $k)
    {
        $map[$k[1]] = count($rows);
        $rows[] = $v['rows'][$k[1]];
    }
    $v['rows'] = $rows;
    $why = array();
    foreach ($v['why'] as $w)
    {
        if (!in_array($w['path'], $v['off'], true))
            $why[] = $w;
    }
    $v['why'] = $why;
    return array($v, $back, $map);
}

// lp_srcs_done: "Готово" of the panel "Балансеры" of the online list $st (left with another choice) with choice $c of
// it (mel_kp_done; none: nothing). The sources in the order of its lines, each on or off, into lampac_sources (one the
// panel did not have, found meanwhile: after them, as it is); none on, the same: nothing written; not written: an
// error. Then the list at once: a source turned on that was off in its search - the search anew (it was not asked);
// else the list of what it has (mel_lpv_srcs_view), the cursor on its row if still shown, else the first.
/**
 * @param MelState $st
 * @param MelKpChoice|null $c
 * @return MelAction|null
 */
function mel_lpv_srcs_apply($st, $c)
{
    if (!$c)
    {
        mel_log('lampac: sources: no choice of the panel, nothing');
        return null;
    }
    $was = Mel::$settings['lp_src'];
    $by = array();
    foreach ($was as $x)
        $by[mel_lpv_srcs_id($x['path'])] = $x;
    $on = array_flip($c['on']);
    $choice = array();
    foreach ($c['order'] as $id)
    {
        if (!isset($by[$id]))
            continue;
        $choice[] = array('path' => $by[$id]['path'], 'name' => $by[$id]['name'], 'on' => isset($on[$id]));
        unset($by[$id]);
    }
    foreach ($by as $x)
        $choice[] = $x;
    $state = array();
    foreach ($choice as $x)
        $state[$x['path']] = $x['on'];
    if (!in_array(true, $state, true))
    {
        mel_log('lampac: sources: none on, nothing changed');
        return null;
    }
    if ($choice === $was)
    {
        mel_log('lampac: sources: the same');
        return null;
    }
    if (mel_lpv_srcs_save($choice) < 0)
        return mel_error('lp_srcs_not_saved');
    list($v, $back, $map) = mel_lpv_srcs_view($st['lp'], Mel::$settings['lp_src']);
    $n = count(array_filter($state));
    mel_log("lampac: sources: $n of " . count($choice) . ' on, saved; ' . ($back ? 'turned on, not asked: ' .
        implode(', ', $back) . ' -> the search anew' : count($v['rows']) . ' rows of ' . count($st['lp']['rows']) . ' kept'));
    list($sel) = mel_lpv_pos($st['lp'], null);
    $m = mel_lpv_mark(mel_lpv_lines($st['lp']), $sel);
    $ns = $st;
    $ns['rid'] = mel_rid();
    $ns['lp'] = mel_lpv_flt_keep($v, $st);
    // No row left (the sources of the rows off, those on gave none): the yellow line how to turn them on.
    if (!$ns['lp']['rows'] && !empty($ns['lp']['off']))
        $ns['lp']['note'] = mel_lpv_tr($st['lang'], 'lp_note_off', array('n' => count($ns['lp']['off'])));
    $ns['lp']['gc'] = mel_lpv_put_at($ns['lp'], mel_lpv_lines($ns['lp']), isset($map[$m[0]]) ? array($map[$m[0]], $m[1]) : array(0, false));
    $ns['lp']['gc_new'] = true;
    // The search over the rows of before: closed with no list (Cancel, no answer), this list of the new choice instead.
    if ($back)
        return mel_lpv_search_open($ns, 'replace', array('redraw' => true));
    return mel_list_replace($ns);
}
