<?php
// "Источники…" of the online list of "Онлайн" (view_online.php): which sources of Lampac it shows and in which
// order - lampac_sources of settings.json, the dialog of the shell edit_list_config. Its MENU item, the search
// (aio_lpv_srcs_pick, aio_lpv_srcs_seen in aio_lpv_tick) and the dispatcher of lp_* are in view_online.php.
// PHP 5.3.6, firmware API only. Types (AioLpChoice, AioLpView): phpstan-types.neon.

// --- "Источники…" (MENU of the online list): which sources of Lampac the list shows and in which order, a dialog
// of the shell (edit_list_config), as "Настроить список…" of the rows of the catalog (view_catalog_rows.php). Its
// items: the sources Lampac has found on this Dune (lampac_sources of settings.json: a path, its name, on), one
// list and one order for all titles; a new one on, last. The key of a source is its path (aio_lp_path: "phantom"
// of /lite/phantom), as in the key of a row and the history ("Continue" asks that balancer alone, aio_lp_find:
// a source turned off still plays from there). Its state is the file <FS_PREFIX>/config/lcfg_melange_lp_sources.txt
// (bin/uninstall.sh removes it), written from the settings before the dialog opens, read back when it closes.

define('AIO_LPV_SRCS_ID', 'melange_lp_sources');

// The sources $sources of a search (AioLpSource) by choice $choice (lampac_sources): those turned off out, the
// others in its order, one not there (a new one, of no path) after them, in their order. -> array(the sources,
// the paths of those turned off).
/**
 * @param list<AioLpSource> $sources
 * @param list<AioLpChoice> $choice
 * @return array{list<AioLpSource>, list<string>}
 */
function aio_lpv_srcs_pick($sources, $choice)
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
// name of their label (aio_lp_src_name); a path not of aio_lp_src_path_ok none. A source there keeps the name of its
// first sight: another label of it changes nothing (settings.json is not written at every search).
/**
 * @param list<AioLpChoice> $choice
 * @param list<AioLpSource> $sources
 * @return list<AioLpChoice>
 */
function aio_lpv_srcs_new($choice, $sources)
{
    $at = array();
    foreach ($choice as $c)
        $at[$c['path']] = true;
    $out = array();
    foreach ($sources as $x)
    {
        $p = $x['path'];
        if (isset($at[$p]) || !aio_lp_src_path_ok($p))
            continue;
        $at[$p] = true;
        $name = aio_lp_src_name($x['label']);
        $out[] = array('path' => $p, 'name' => $name !== '' ? $name : aio_lp_src_name($p), 'on' => true);
    }
    return $out;
}

// The longest body of the settings of an import of the settings page (0.62.0: "Мой список" goes over it):
// AIO_CGI_BAK_SETTINGS_MAX of cgi/lib/backup.php (not loaded here).
define('AIO_LPV_SRCS_IMPORT_MAX', 32768);

// The export of settings $s (aio_settings_read) loads back on the settings page, as aio_cgi_import_fits of
// cgi/lib/backup.php has it: the body of its file (aio_cgi_export_json: web_token of 32 hex characters, any of the
// same length) not over AIO_LPV_SRCS_IMPORT_MAX. So settings.json stays within AIO_SETTINGS_MAX too.
/**
 * @param AioSettings $s
 * @return bool
 */
function aio_lpv_srcs_fits($s)
{
    $json = aio_settings_json($s + array('web_token' => str_repeat('0', 32)));
    return $json !== '' && strlen('settings=' . rawurlencode($json)) <= AIO_LPV_SRCS_IMPORT_MAX;
}

// lampac_sources $choice into Aio::$settings and settings.json - of the profile of Lampac of the operation (its id
// lp_prof; v2: each Lampac its own balancers), the file read again just before (a source of the file not in $choice
// kept, last), then the new ones $add (aio_lpv_srcs_new) after them, each while there is room (AIO_LP_SEEN_MAX) and
// the export of the settings with it loads back (aio_lpv_srcs_fits: the whole file, all the profiles); those not kept -
// one line. Written as the quick setup does (aio_settings_write); $add, none of it kept, or the profile gone (the
// page deleted it meanwhile): nothing written. -> the number of $add kept; -1: not written.
/**
 * @param list<AioLpChoice> $choice
 * @param list<AioLpChoice> $add
 * @return int
 */
function aio_lpv_srcs_save($choice, $add = array())
{
    Aio::$settings['lp_src'] = $choice;
    $dir = aio_data_dir();
    $why = 'no data_dir';
    $s = $dir !== '' ? aio_settings_read($dir, $why) : null;
    if (!$s || $why !== '')
    {
        aio_log('lampac: sources: settings.json not read, not written');
        return -1;
    }
    $k = aio_profile_index($s, Aio::$settings['lp_prof']);
    if ($k < 0 || aio_profile_rel($s['profiles'][$k]))
    {
        aio_log('lampac: sources: the profile of Lampac gone, not written');
        return -1;
    }
    $have = array();
    foreach ($choice as $c)
        $have[$c['path']] = true;
    foreach ($s['profiles'][$k]['lampac_sources'] as $c)
    {
        if (!isset($have[$c['path']]) && count($choice) < AIO_LP_SEEN_MAX)
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
        if (count($s['profiles'][$k]['lampac_sources']) >= AIO_LP_SEEN_MAX)
            $full++;
        else if (!aio_lpv_srcs_fits($t))
            $big++;
        else
        {
            $s = $t;
            $have[$c['path']] = true;
            $n++;
        }
    }
    if ($full + $big > 0)
        aio_log('lampac: sources: ' . ($full + $big) . ' new not kept (' . AIO_LP_SEEN_MAX . " at most: $full, the export " .
            'of the settings over ' . AIO_LPV_SRCS_IMPORT_MAX . " bytes: $big)");
    Aio::$settings['lp_src'] = $s['profiles'][$k]['lampac_sources'];
    if ($add && $n === 0)
        return 0;
    if (!aio_settings_write($s))
    {
        aio_log('lampac: sources: settings.json not written');
        return -1;
    }
    return $n;
}

// The sources $sources of a search done: those new into lampac_sources (aio_lpv_srcs_new, aio_lpv_srcs_save).
/**
 * @param list<AioLpSource> $sources
 * @return void
 */
function aio_lpv_srcs_seen($sources)
{
    $new = aio_lpv_srcs_new(Aio::$settings['lp_src'], $sources);
    if (!$new)
        return;
    $n = aio_lpv_srcs_save(Aio::$settings['lp_src'], $new);
    if ($n > 0)
        aio_log("lampac: sources: $n new, " . count(Aio::$settings['lp_src']) . ' in all, saved');
}

/**
 * @return string
 */
function aio_lpv_srcs_path()
{
    return getenv('FS_PREFIX') . '/config/lcfg_' . AIO_LPV_SRCS_ID . '.txt';
}

// lp_srcs ("Источники…") of the online list $st: the dialog of the sources of lampac_sources in their order, those
// on checked, "Сброс" all on (checked_ids), the source of the row under the cursor selected. No empty set (no
// options: a list of no source shows nothing). Its file written first; not written: an error, no dialog (its
// close would write the last state of the shell over the choice). No close_item_params, dialog_params or params
// of items: r22 has none (as aio_catv_rows_edit).
/**
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_srcs_edit($st)
{
    $choice = Aio::$settings['lp_src'];
    if (!$choice || !isset($st['lp']))
        return null;
    if (!class_exists('EditListConfigActionData') || !defined('EDIT_LIST_CONFIG_ACTION_ID'))
        return aio_error('cat_rows_no_dialog');
    $ids = array();
    $on = array();
    $items = array();
    foreach ($choice as $c)
    {
        $ids[] = $c['path'];
        if ($c['on'])
            $on[$c['path']] = true;
        $items[] = array(GuiItem::id => $c['path'], GuiItem::caption => aio_catv_lcfg_caption($c['name']));
    }
    if (!aio_write_file(aio_lpv_srcs_path(), aio_catv_lcfg_text($ids, $on, $ids), 0644))
    {
        aio_log('lampac: sources: the file of the dialog not written');
        return aio_error('lp_srcs_not_opened');
    }
    $data = array(
        EditListConfigActionData::config_id => AIO_LPV_SRCS_ID,
        EditListConfigActionData::title => aio_tr($st['lang'], 'lp_srcs_title'),
        EditListConfigActionData::all_items => $items,
        EditListConfigActionData::checked_ids => $ids,
        EditListConfigActionData::post_action => aio_gc_act('lp_srcs_apply', $st['rid']));
    list($sel) = aio_lpv_pos($st['lp'], null);
    list($i) = aio_lpv_mark(aio_lpv_lines($st['lp']), $sel);
    if (isset($st['lp']['rows'][$i]) && isset($on[$st['lp']['rows'][$i]['path']]))
        $data[EditListConfigActionData::sel_id] = $st['lp']['rows'][$i]['path'];
    aio_log('lampac: sources: dialog, ' . count($ids) . ' sources, ' . count($on) . ' on');
    return array(GuiAction::handler_string_id => EDIT_LIST_CONFIG_ACTION_ID, GuiAction::data => $data);
}

// View $v by choice $choice (lampac_sources after "Источники…"), no request: its sources and rows of those turned
// off out, the others in the order of the choice (the rows of a source in their order), those asked before those
// not ("Ещё N"), the reasons of those off out. -> array(the view; the paths of its sources turned on that were off
// in its search: not asked, a search anew; a row of $v => its row now).
/**
 * @param AioLpView $v
 * @param list<AioLpChoice> $choice
 * @return array{AioLpView, list<string>, array<int, int>}
 */
function aio_lpv_srcs_view($v, $choice)
{
    $asked = array();
    foreach (array_slice($v['sources'], 0, $v['next']) as $x)
        $asked[$x['url']] = true;
    list($srcs, $off) = aio_lpv_srcs_pick($v['sources'], $choice);
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

// lp_srcs_apply: post_action of the dialog of "Источники…" of the online list $st, at every close (the shell has
// written its file by then). Changed: the sources in the order of the file, each on or off, into lampac_sources;
// none on, the file not a file, the same: nothing written; not read: an error. Then the list at once: a source
// turned on that was off in its search - the search anew (it was not asked); else the list of what it has
// (aio_lpv_srcs_view), the cursor on its row if still shown, else the first.
/**
 * @param stdClass $in
 * @param AioState $st
 * @return AioAction|null
 */
function aio_lpv_srcs_apply($in, $st)
{
    $changed = isset($in->list_config_changed) && is_scalar($in->list_config_changed) ? strval($in->list_config_changed) : '';
    if (!isset($in->list_config_id) || $in->list_config_id !== AIO_LPV_SRCS_ID)
        return null;
    if ($changed !== '1')
    {
        aio_log('lampac: sources: dialog closed, no change');
        return null;
    }
    list($text, $no) = aio_catv_lcfg_load(aio_lpv_srcs_path());
    if ($no === 'bad' || $no === 'unread')
    {
        aio_log("lampac: sources: the file of the dialog $no, nothing changed");
        return $no === 'unread' ? aio_error('lp_srcs_not_saved') : null;
    }
    $was = Aio::$settings['lp_src'];
    $ids = array();
    $by = array();
    foreach ($was as $c)
    {
        $ids[] = $c['path'];
        $by[$c['path']] = $c;
    }
    list($order, $state) = aio_catv_lcfg_sorted($text, $ids, array_fill_keys($ids, true), $ids);
    if (!in_array(true, $state, true))
    {
        aio_log('lampac: sources: none on in the file, nothing changed');
        return null;
    }
    $choice = array();
    foreach ($order as $p)
        $choice[] = array('path' => $p, 'name' => $by[$p]['name'], 'on' => $state[$p]);
    if ($choice === $was)
    {
        aio_log('lampac: sources: the same');
        return null;
    }
    if (aio_lpv_srcs_save($choice) < 0)
        return aio_error('lp_srcs_not_saved');
    list($v, $back, $map) = aio_lpv_srcs_view($st['lp'], Aio::$settings['lp_src']);
    $n = count(array_filter($state));
    aio_log("lampac: sources: $n of " . count($choice) . ' on, saved; ' . ($back ? 'turned on, not asked: ' .
        implode(', ', $back) . ' -> the search anew' : count($v['rows']) . ' rows of ' . count($st['lp']['rows']) . ' kept'));
    list($sel) = aio_lpv_pos($st['lp'], null);
    $m = aio_lpv_mark(aio_lpv_lines($st['lp']), $sel);
    $ns = $st;
    $ns['rid'] = aio_rid();
    $ns['lp'] = aio_lpv_flt_keep($v, $st);
    // No row left (the sources of the rows off, those on gave none): the yellow line how to turn them on.
    if (!$ns['lp']['rows'] && !empty($ns['lp']['off']))
        $ns['lp']['note'] = aio_lpv_tr($st['lang'], 'lp_note_off', array('n' => count($ns['lp']['off'])));
    $ns['lp']['gc'] = aio_lpv_put_at(aio_lpv_lines($ns['lp']), isset($map[$m[0]]) ? array($map[$m[0]], $m[1]) : array(0, false));
    $ns['lp']['gc_new'] = true;
    // The search over the rows of before: closed with no list (Cancel, no answer), this list of the new choice instead.
    if ($back)
        return aio_lpv_search_open($ns, 'replace', array('redraw' => true));
    return aio_list_replace($ns);
}
