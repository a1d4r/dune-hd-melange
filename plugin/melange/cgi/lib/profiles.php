<?php
// The settings page (cgi/settings.php): the sources (v2, spec source-profiles-v2 §3; in the code - profiles). The page is
// the editor of one of them, by its id: ?p=<id> (none or not there - the main one), ?p=new&from=<id> - a new one, the copy
// of that one (not there - of the main one; no profiles - the first one, of the settings). Its settings in the fields of
// the form (aio_cgi_editor_view), "Сохранить" writes it and the shared fields (aio_cgi_editor_save, in the save of the
// form), a=aio_sync&prof=<id> makes or changes its config. On #src: with two or more (or a new one) the block "Источники" -
// the list (by the script), the name; the four kinds; "Сделать основным", "Удалить" (aio_cgi_editor_foot). The actions of
// the script, POST ?t=<token>&a=<action>&ajax=1 (i: the id) -> JSON {ok, msg[, profiles]}:
//   profile_main (i) -> src_main, the main one (no request; one without its config too: "конфига нет" stays);
//   profile_del (i) -> gone (not the last one); its config into the cell of its server and mode when no other profile
//     has it (no config is lost); the main one gone - the first one left is.
// Each reads settings.json and writes it whole (aio_cgi_store). profiles: those of the file (none when it was not read).

// The texts of the sources in the i18n of the script (aio_cgi_js: web/src/profiles.ts takes them by these keys).
function aio_cgi_profile_texts()
{
    return array('profileMain' => aio_cgi_l('основной', 'main'),
        'profileNoConf' => aio_cgi_l('конфига нет', 'no config'),
        'profileNew' => aio_cgi_l('+ Добавить источник', '+ Add source'),
        'profilesFull' => aio_cgi_profiles_full(),
        'profileDeleteConfirm' => aio_cgi_l('Удалить источник «%s»? Конфиг на сервере останется.',
            'Delete the source "%s"? Its config on the server stays.'),
        'profileDeleteMain' => aio_cgi_l('Основным станет «%s».', '"%s" becomes the main source.'));
}

// The line of the sources full: instead of "+ Добавить источник", a save of a new one refused.
function aio_cgi_profiles_full()
{
    return sprintf(aio_cgi_l('Источников уже %d. Удалите лишний, чтобы добавить новый.',
        'There are %d sources already. Delete one to add another.'), AIO_PROFILES_MAX);
}

// The line of a save or an action refused by the source gone meanwhile (another tab deleted it).
function aio_cgi_profile_gone()
{
    return aio_cgi_l('Источник удалён в другой вкладке — обновите страницу', 'The source was deleted in another tab: reload the page');
}

// The kind of profile $p in the words of the page: Debrid, Торренты, Свой конфиг, Lampac.
function aio_cgi_profile_word($p)
{
    if (isset($p['lampac_url']))
        return 'Lampac';
    if (isset($p['url']))
        return aio_cgi_l('Свой конфиг', 'Own config');
    return isset($p['mode']) && $p['mode'] === AIO_MODE_P2P ? aio_cgi_mode_p2p() : 'Debrid';
}

// The name of a new profile of the kind of $p (aio_profile_of of the settings, a profile) among the profiles of the
// settings $s: the word of its kind, with a number from 2 while the name is taken (aio_profile_free_name).
function aio_cgi_profile_def($s, $p)
{
    return aio_profile_free_name($s, aio_cgi_profile_word($p ? $p : array('mode' => $s['aio_mode'])));
}

// The name of a copy of profile $p in the settings $s: "<its name> 2" (the first free one, aio_profile_free_name), its
// name cut so the number fits AIO_PROFILE_NAME_MAX.
function aio_cgi_profile_copy_name($s, $p)
{
    preg_match('/^.{0,' . (AIO_PROFILE_NAME_MAX - 2) . '}/su', $p['name'], $m);
    return aio_profile_free_name($s, aio_profile_name($m ? $m[0] : $p['name']));
}

// The line of profile $p under its name: its kind, host (the page is in the home network: hosts are shown) and JacRed.
function aio_cgi_profile_line($p)
{
    if (isset($p['lampac_url']))
        return 'Lampac · ' . aio_cgi_host($p['lampac_url']);
    $h = aio_cgi_host(isset($p['url']) ? $p['url'] : $p['base']);
    $jr = $p['jacred'] === 'own' ? aio_cgi_host($p['jacred_own_url']) : $p['jacred'];
    return aio_cgi_profile_word($p) . ($h !== '' ? " · $h" : '') . ($jr !== '' ? " · $jr" : '');
}

// The configs of the cells of the settings $s no profile has (a config of before the profiles, spec v2 §4 p. 6): a
// profile of their server and mode takes one (aio_cgi_editor_save).
function aio_cgi_free_cells($s)
{
    $r = array();
    foreach ($s['aio_confs'] as $b => $cs)
    {
        foreach ($cs as $m => $c)
        {
            if (!aio_profile_held($s, $c))
                $r[$b][$m] = $c;
        }
    }
    return $r;
}

// The settings $s as the editor of profile $p shows them (aio_profile_view: its source, its config): the configs of the
// cells no profile has too (its save takes one of its server and mode); of Lampac - no config of its own; of one of
// releases - no Lampac (aio_lp_keys); of an own config or Lampac - the keys of Debrid of aio_profile_debrid (those of
// the top; Debrid chosen there: no key typed again, spec v2 §3.3), none such - those at the top (of a file of before v2:
// kept).
function aio_cgi_editor_view($s, $p)
{
    $v = aio_profile_view($s, $p);
    $own = isset($p['base']) ? $v['aio_confs'] : array();
    $v['aio_confs'] = aio_cgi_free_cells($s);
    foreach ($own as $b => $cs)
        $v['aio_confs'][$b] = $cs + (isset($v['aio_confs'][$b]) ? $v['aio_confs'][$b] : array());
    if (aio_profile_rel($p))
        $v = array_merge($v, aio_lp_keys());
    if (!isset($p['base']))
    {
        $k = aio_profile_debrid($s);
        $q = $k >= 0 ? $s['profiles'][$k] : $s;
        list($v['rd_key'], $v['tb_key']) = array($q['rd_key'], $q['tb_key']);
    }
    return $v;
}

// A new profile, the copy of the profile of index $f of the settings $s (spec v2 §3.3: no config of its own, the
// name "<name> 2", no id yet), else (-1) null.
function aio_cgi_profile_copy($s, $f)
{
    if ($f < 0)
        return null;
    $c = $s['profiles'][$f];
    if (isset($c['base']))
        $c['conf'] = null;
    list($c['id'], $c['name']) = array('', aio_cgi_profile_copy_name($s, $c));
    return $c;
}

// A new profile of the editor (of the page, of the save), the copy of the profile of index $f of the settings $s
// (aio_cgi_profile_copy; -1 none) -> array(the copy (null: none), the view: of the copy (aio_cgi_editor_view), else $s
// with the configs of the cells no profile has).
function aio_cgi_editor_new($s, $f)
{
    $c = aio_cgi_profile_copy($s, $f);
    if ($c)
        return array($c, aio_cgi_editor_view($s, $c));
    $s['aio_confs'] = aio_cgi_free_cells($s);
    return array(null, $s);
}

// The editor of the page by ?p= $p, from= $from of the address and the settings $s (aio_settings_read) -> array(sel - the
// id of the profile or 'new', from - the id of the profile a new one is the copy of ('' none), name ('' a new one of no
// copy: the word of its kind on the save), view - the settings the page shows (aio_cgi_editor_view; a new one of no
// copy: $s, the configs of the cells no profile has), lp - the profile is of Lampac). Not there (none, an id gone): the
// main one; p=new with the profiles full: the main one too.
function aio_cgi_editor($s, $p, $from)
{
    $k = $p !== 'new' ? aio_profile_index($s, $p) : -1;
    if ($k < 0 && ($p !== 'new' || count($s['profiles']) >= AIO_PROFILES_MAX))
        $k = aio_profile_main($s);
    if ($k >= 0)
    {
        $x = $s['profiles'][$k];
        return array('sel' => $x['id'], 'from' => '', 'name' => $x['name'], 'view' => aio_cgi_editor_view($s, $x),
            'lp' => !aio_profile_rel($x));
    }
    $f = aio_profile_index($s, $from);
    $f = $f >= 0 ? $f : aio_profile_main($s);
    list($c, $view) = aio_cgi_editor_new($s, $f);
    return array('sel' => 'new', 'from' => $c ? $s['profiles'][$f]['id'] : '', 'name' => $c ? $c['name'] : '',
        'view' => $view, 'lp' => $c && !aio_profile_rel($c));
}

// The values of the form of the editor $ed (aio_cgi_editor) over $v (aio_cgi_values of its view), the settings $s: the
// fields pf_sel, pf_from, pf_name, its placeholder pf_def (aio_cgi_editor_def), pf_list (the block "Источники" shown),
// watch "lampac" of a profile of Lampac, prof - the data of the script (aio_cgi_profiles_data).
function aio_cgi_editor_values($v, $ed, $s)
{
    $v['pf_sel'] = $ed['sel'];
    $v['pf_from'] = $ed['from'];
    $v['pf_name'] = $ed['name'];
    $v['pf_def'] = aio_cgi_editor_def($ed['view'], $ed['lp']);
    $v['pf_list'] = aio_cgi_editor_list($s, $ed['sel']);
    if ($ed['lp'])
        $v['watch'] = 'lampac';
    $v['prof'] = aio_cgi_profiles_data($s);
    return $v;
}

// The block "Источники" (the list, the name of the source) on the page of the editor $sel (pf_sel) of the settings $s:
// two profiles or more, or a new one beside one at least (spec v2 §3.1: one - no list, no name).
function aio_cgi_editor_list($s, $sel)
{
    $n = count($s['profiles']);
    return $n >= 2 || ($sel === 'new' && $n >= 1);
}

// The placeholder of "Название источника" of the editor showing the settings $view (of Lampac: $lp): the name a new one
// gets without one (aio_cgi_profile_def of its kind).
function aio_cgi_editor_def($view, $lp)
{
    return aio_cgi_profile_def($view, $lp ? array('lampac_url' => '') : aio_profile_of($view));
}

// The profiles of the settings $s for the script (#init.profiles, "profiles" of the answers): list - {id, name, line,
// main (aio_profile_main), ready (aio_profile_ready; not: "конфига нет")} each in order; max - AIO_PROFILES_MAX.
function aio_cgi_profiles_data($s)
{
    $m = aio_profile_main($s);
    $list = array();
    foreach ($s['profiles'] as $i => $p)
        $list[] = array('id' => $p['id'], 'name' => $p['name'], 'line' => aio_cgi_profile_line($p), 'main' => $i === $m,
            'ready' => (bool) aio_profile_ready($p));
    return array('list' => $list, 'max' => AIO_PROFILES_MAX);
}

// The block of the editor at the top of #src (#pe): with the list (pf_list of $v) the heading "Источники", the list (#pel,
// by the script: a link each to the page of it, "+ Добавить источник") and "Название источника" (prof_name: posted, a
// change for the bar; empty - the name stays, of a new one - the word of its kind); always the profile of the save - prof
// (pf_sel: its id, new), prof_from (pf_from: a new one is the copy of it).
function aio_cgi_editor_block($v)
{
    $f = aio_cgi_fields();
    $out = '<div id="pe">';
    if ($v['pf_list'])
        $out .= '<h3 class="gh">' . aio_cgi_l('Источники', 'Sources') . '</h3><div id="pel" class="list"></div>' .
            aio_cgi_input($f['pf_name'], aio_cgi_l('Название источника', 'Source name'), $v['pf_name'], $v['pf_def']);
    return $out . aio_cgi_hidden($f['pf_sel'], $v['pf_sel']) . aio_cgi_hidden($f['pf_from'], $v['pf_from']) . '</div>';
}

// The end of #src: with the list (pf_list) "Сделать основным" and "Удалить" of a saved one (#peb, by the script:
// profile_main, profile_del; not "Сделать основным" of the main one) and their results (#per); one source alone - the box
// of "+ Добавить источник" under its fields (#pea, by the script).
function aio_cgi_editor_foot($v)
{
    if (!$v['pf_list'])
        return '<div id="pea" class="list"></div>';
    return '<div class="btns" id="peb" hidden><button type="button" id="peu" class="btn ghost">' .
        aio_cgi_l('Сделать основным', 'Set as main') . '</button><button type="button" id="pex" class="btn danger">' .
        aio_cgi_l('Удалить', 'Delete') . '</button></div><div id="per" class="res" aria-live="polite"></div>';
}

// The line of the source on #cfg and #voices (their settings are of it), with the list: "Источник «X»", "Новый источник".
function aio_cgi_editor_line($v)
{
    if (empty($v['pf_list']))
        return '';
    return '<p class="sub">' . ($v['pf_sel'] !== 'new' ? sprintf(aio_cgi_l('Источник «%s»', 'Source "%s"'),
        aio_cgi_h($v['pf_name'])) : aio_cgi_l('Новый источник', 'New source')) . '</p>';
}

// The editor of the save, by the posted $v (aio_cgi_posted: pf_sel, pf_from, pf_name) and the stored settings $cur ->
// array(old - the profile (null: a new one), from - the profile a new one is the copy of (null none), name - as typed
// (null: no field posted - the name stays), view (the settings as of it: aio_cgi_editor_view), err (why refused: the
// profile gone meanwhile, the profiles full; '' none)). prof none (a form of before v2) - the main one; no profiles -
// a new one.
function aio_cgi_editor_posted($v, $cur)
{
    $f = aio_cgi_fields();
    $sel = $v['pf_sel'];
    $ed = array('old' => null, 'from' => null, 'name' => isset($_POST[$f['pf_name']['name']]) ? $v['pf_name'] : null,
        'view' => $cur, 'err' => '');
    if ($sel === 'new' || ($sel === '' && !$cur['profiles']))
    {
        if (count($cur['profiles']) >= AIO_PROFILES_MAX)
            return array_merge($ed, array('err' => aio_cgi_profiles_full()));
        $k = aio_profile_index($cur, $v['pf_from']);
        list(, $view) = aio_cgi_editor_new($cur, $k);
        return array_merge($ed, array('from' => $k >= 0 ? $cur['profiles'][$k] : null, 'view' => $view));
    }
    $k = $sel === '' ? aio_profile_main($cur) : aio_profile_index($cur, $sel);
    if ($k < 0)
        return array_merge($ed, array('err' => aio_cgi_profile_gone()));
    $p = $cur['profiles'][$k];
    return array_merge($ed, array('old' => $p, 'view' => aio_cgi_editor_view($cur, $p)));
}

// The profile of the values $data checked (aio_cgi_check_values with the view of it): of Lampac ($lp: "Lampac" chosen) -
// its address, token, "as Lampa" and the balancers of $was (its record in the file: the Dune writes them; of a new one
// the copy, else none); else the source of $data (aio_profile_of) and its keys of Debrid; null - none (the own config
// without its link, no server).
function aio_cgi_profile_of_data($data, $lp, $was)
{
    if ($lp)
        return array_merge(aio_lp_keys(), array_intersect_key($data, aio_lp_keys()), array('lampac_sources' =>
            $was && isset($was['lampac_sources']) ? $was['lampac_sources'] : array()));
    $p = aio_profile_of($data);
    return $p ? $p + array('rd_key' => $data['rd_key'], 'tb_key' => $data['tb_key']) : null;
}

// The profile of the editor $ed (aio_cgi_editor_posted) by the values checked $data (aio_cgi_check_values with the view
// of it; $lp - "Lampac" chosen) and the settings stored $cur (read again just before the write; the profile found by its
// id - gone: refused); $t: the token (of the export) -> array(the settings to write, the profile, '') or array(null, null,
// why refused). Its record by its index (its id kept: a rename too; the main one stays it), a new one at the end with an
// id of its name (aio_profile_new_id), the first one the main one. Its config: the one of it while its server and mode
// are those of it; else the config of the cell of its server and mode when no profile has it (aio_profile_held: a config
// of before the profiles is not lost); else none (made by a=aio_sync&prof). Its config of before, of another server or
// mode now (or none: Lampac, an own config), into the cell of them when it is empty (no config is lost, as of
// profile_del; a cell with a config stays). The name: as typed; empty or none posted - the one of it, of a new one the
// word of its kind (aio_cgi_profile_def); none posted (one source alone) and the one of it the word of its kind before -
// the word of its kind now. Names unique, the profiles at most AIO_PROFILES_MAX, the export with it loads back
// (aio_cgi_import_fits).
function aio_cgi_editor_save($ed, $data, $cur, $t, $lp)
{
    $k = $ed['old'] ? aio_profile_index($cur, $ed['old']['id']) : -1;
    if ($ed['old'] && $k < 0)
        return array(null, null, aio_cgi_profile_gone());
    if (!$ed['old'] && count($cur['profiles']) >= AIO_PROFILES_MAX)
        return array(null, null, aio_cgi_profiles_full());
    $o = $k >= 0 ? $cur['profiles'][$k] : null;
    $fk = $ed['from'] ? aio_profile_index($cur, $ed['from']['id']) : -1;
    $p = aio_cgi_profile_of_data($data, $lp, $o ? $o : ($fk >= 0 ? $cur['profiles'][$fk] : null));
    if ($p === null)
        return array(null, null, aio_cgi_profile_gone());
    if (isset($p['base']))
    {
        $cell = isset($cur['aio_confs'][$p['base']][$p['mode']]) ? $cur['aio_confs'][$p['base']][$p['mode']] : null;
        if ($o && isset($o['base']) && $o['base'] === $p['base'] && $o['mode'] === $p['mode'])
            $p['conf'] = $o['conf'];
        else
            $p['conf'] = $cell && !aio_profile_held($cur, $cell) ? $cell : null;
    }
    $name = $ed['name'] !== null ? aio_profile_name($ed['name']) : '';
    if ($name === '')
        $name = $o ? $o['name'] : aio_cgi_profile_def($cur, $p);
    if ($ed['name'] === null && $o && $o['name'] === aio_cgi_profile_word($o) && aio_cgi_profile_word($p) !== $o['name'])
        $name = aio_cgi_profile_def($cur, $p);
    $p = aio_profile_ok(array('id' => $o ? $o['id'] : aio_profile_new_id($cur, $name), 'name' => $name) + $p, $cur);
    if (!$p)
        return array(null, null, sprintf(aio_cgi_l('Название источника: от 1 до %d символов, без управляющих и невидимых',
            'Source name: 1 to %d characters, no control or invisible ones'), AIO_PROFILE_NAME_MAX));
    foreach ($cur['profiles'] as $i => $x)
    {
        if ($i !== $k && $x['name'] === $p['name'])
            return array(null, null, sprintf(aio_cgi_l('Источник «%s» уже есть, выберите другое название',
                'A source "%s" exists already, choose another name'), $p['name']));
    }
    $data['profiles'] = $cur['profiles'];
    $data['src_main'] = $cur['src_main'];
    if ($k >= 0)
        $data['profiles'][$k] = $p;
    else
        list($data) = aio_profile_add($data, $p);
    $c = $o && isset($o['base']) ? $o['conf'] : null;
    if ($c && (!isset($p['base']) || $p['base'] !== $o['base'] || $p['mode'] !== $o['mode']) &&
        !isset($data['aio_confs'][$o['base']][$o['mode']]) && !aio_profile_held($data, $c))
        $data = aio_settings_cell($data, $o['base'], $o['mode'], $c);
    // The first one is the main one.
    if ($data['src_main'] === '')
        $data['src_main'] = $p['id'];
    if (!aio_cgi_import_fits(aio_cgi_export_json($data, $t)))
        return array(null, null, aio_cgi_l('Не сохранено: с этим источником резервная копия будет слишком большой',
            'Not saved: with this source the backup file would be too big'));
    return array($data, $p, '');
}

// The profile of the editor $ed of the save saved (aio_cgi_editor_save) with the values $data checked (the configs - of
// $cur) and the settings stored $cur; $t: the token, $v: the values of the form -> array(the settings to write, the
// profile). Refused (the err of $ed too: gone, the profiles full) - the form back with why on the field of the name,
// nothing written (aio_cgi_failed). The save asks it before the keys are checked (no request for a save refused) and
// again just before the write.
function aio_cgi_editor_check($ed, $data, $cur, $t, $v)
{
    $data['aio_confs'] = $cur['aio_confs'];
    list($with, $p, $why) = $ed['err'] !== '' ? array(null, null, $ed['err']) :
        aio_cgi_editor_save($ed, $data, $cur, $t, $v['watch'] === 'lampac');
    if ($with === null)
    {
        aio_cgi_log('save: the source refused, nothing written');
        $f = aio_cgi_fields();
        $e = array(aio_cgi_err($why, $f['pf_name']['name']));
        aio_cgi_failed($v['ajax'], 200, $e, aio_cgi_form($t, $v, $e, false));
    }
    return array($with, $p);
}

// The answer of an action: JSON {ok, msg, profiles (of $s; null - none)}.
function aio_cgi_profile_answer($ok, $msg, $s)
{
    aio_cgi_json(200, array_merge(array('ok' => $ok, 'msg' => $msg), $s !== null ?
        array('profiles' => aio_cgi_profiles_data($s)) : array()));
}

// An action refused: nothing written, $why to the log.
function aio_cgi_profile_failed($a, $why, $msg, $s)
{
    aio_cgi_log("$a: $why, nothing written");
    aio_cgi_profile_answer(false, $msg, $s);
}

// POST a=profile_main | profile_del (ajax=1): as above.
function aio_cgi_profile_post($dir, $a)
{
    $s = aio_settings_read(aio_cgi_dir_ok($dir) ? $dir : '', $why);
    if ($why !== '')
        aio_cgi_log("$a: settings.json $why");
    if ($why !== '' || !aio_cgi_dir_ok($dir))
    {
        aio_cgi_profile_failed($a, $why !== '' ? 'not read' : 'no data dir', aio_cgi_l('Не сохранено: настройки на Дюне не читаются',
            'Not saved: the settings on the Dune cannot be read'), null);
    }
    $k = aio_profile_index($s, aio_cgi_param($_POST, 'i'));
    if ($k < 0)
        aio_cgi_profile_failed($a, 'no such profile', aio_cgi_profile_gone(), $s);
    $p = $s['profiles'][$k];
    $main = aio_profile_main($s) === $k;
    if ($a === 'profile_del')
    {
        // No button of the last one.
        if (count($s['profiles']) === 1)
            aio_cgi_profile_failed($a, 'the last one', aio_cgi_l('Последний источник не удаляется',
                'The last source cannot be deleted'), $s);
        array_splice($s['profiles'], $k, 1);
        // Its config into the cell of its server and mode, empty or of a profile (no config is lost; spec v2 §3.3).
        $c = isset($p['base']) ? $p['conf'] : null;
        $cell = $c && isset($s['aio_confs'][$p['base']][$p['mode']]) ? $s['aio_confs'][$p['base']][$p['mode']] : null;
        if ($c && !aio_profile_held($s, $c) && (!$cell || aio_profile_held($s, $cell)))
            $s = aio_settings_cell($s, $p['base'], $p['mode'], $c);
        if ($main)
            $s['src_main'] = $s['profiles'][0]['id'];
        $msg = sprintf(aio_cgi_l('Источник «%s» удалён', 'Source "%s" deleted'), $p['name']);
    }
    else
    {
        $msg = sprintf(aio_cgi_l('Основной источник — «%s»', 'The main source is "%s"'), $p['name']);
        if ($main)
            aio_cgi_profile_answer(true, $msg, $s);
        $s['src_main'] = $p['id'];
    }
    $why = aio_cgi_store($dir, $s);
    if ($why !== '')
        aio_cgi_profile_failed($a, $why, aio_cgi_l('Не сохранено: ошибка записи на Дюне', 'Not saved: write error on the Dune'),
            aio_settings_read($dir));
    aio_cgi_log("$a: ok");
    aio_cgi_profile_answer(true, $msg, $s);
}
