<?php
// The settings page (cgi/settings.php): the source profiles (0.58.0) - with "Профили источников" on the page is the
// editor of one profile (?p=<i>, p=new: a new one; none - the one in use, else a new one of the source in use): its
// settings of the source shown by the same fields as the source in use (aio_profile_view), "Сохранить" saves them to
// it (aio_cgi_editor_save, in the save of the form), a=aio_sync&prof=<name> makes or changes its config; its block on
// #src (aio_cgi_editor_block), the data and texts of the script; the actions of the script, POST
// ?t=<token>&a=<action>&ajax=1 (i: the index the page shows, was: its name there - another one in the file: nothing
// written) -> JSON {ok, msg[, profiles]}:
//   profile_use (i, was) -> profile i in use, as MENU of a list on the Dune does (no request: its config is there; the
//     source in use of no profile saved as a profile first, aio_profile_keep - the profiles full: refused);
//   profile_del (i, was) -> profile i gone (its config stays on the server, the source in use stays).
// Each one reads settings.json and writes it whole (aio_cgi_store). profiles: those of the file (none when it was not
// read).

// The texts of the profiles in the i18n of the script (aio_cgi_js: web/src/profiles.ts takes them by these keys).
function aio_cgi_profile_texts()
{
    return array('profileNow' => aio_cgi_l('сейчас', 'now'),
        'profileNoConf' => aio_cgi_l('конфига нет', 'no config'),
        'profileNew' => aio_cgi_l('+ Новый профиль', '+ New profile'),
        'profileDirty' => aio_cgi_l('Есть несохранённые изменения: сохраните их или обновите страницу.',
            'There are unsaved changes: save them or reload the page.'),
        'profilesFull' => aio_cgi_profiles_full(),
        'profileDeleteConfirm' => aio_cgi_l('Удалить профиль «%s»? Конфиг на сервере останется.',
            'Delete the profile "%s"? Its config on the server stays.'));
}

// The line of the profiles full: of "+ Новый профиль" and of a save of a new one refused.
function aio_cgi_profiles_full()
{
    return sprintf(aio_cgi_l('Профилей уже %d. Удалите лишний, чтобы добавить новый.',
        'There are %d profiles already. Delete one to add another.'), AIO_PROFILES_MAX);
}

// The line of "Сделать текущим" refused: the source in use of no profile is to be saved as one, the profiles full.
function aio_cgi_profiles_full_use()
{
    return sprintf(aio_cgi_l('Профилей уже %d: удалите лишний, чтобы переключиться',
        'There are %d profiles already: delete one to switch'), AIO_PROFILES_MAX);
}

// The line of a save refused by the profiles changed meanwhile (another tab).
function aio_cgi_profiles_changed()
{
    return aio_cgi_l('Профили изменились в другой вкладке — обновите страницу', 'The profiles changed in another tab: reload the page');
}

// The kind of profile $p in the words of the page: Debrid, Торренты, Свой конфиг.
function aio_cgi_profile_word($p)
{
    if (isset($p['url']))
        return aio_cgi_l('Свой конфиг', 'Own config');
    return $p['mode'] === AIO_MODE_P2P ? aio_cgi_mode_p2p() : 'Debrid';
}

// The words of the kinds of the profiles (aio_profile_keep): those of aio_cgi_profile_word.
function aio_cgi_profile_words()
{
    return array('debrid' => aio_cgi_profile_word(array('mode' => AIO_MODE_DEBRID)),
        'p2p' => aio_cgi_profile_word(array('mode' => AIO_MODE_P2P)), 'own' => aio_cgi_profile_word(array('url' => '')));
}

// The name of a new profile of the source of the settings $s (its profiles too): the word of its kind, with a number
// from 2 while the name is taken (aio_profile_free_name).
function aio_cgi_profile_def($s)
{
    $target = aio_profile_of($s);
    return aio_profile_free_name($s, aio_cgi_profile_word($target ? $target : array('mode' => $s['aio_mode'])));
}

// The line of profile $p under its name: its kind, host (the page is in the home network: hosts are shown) and JacRed.
function aio_cgi_profile_line($p)
{
    $h = aio_cgi_host(isset($p['url']) ? $p['url'] : $p['base']);
    $jr = $p['jacred'] === 'own' ? aio_cgi_host($p['jacred_own_url']) : $p['jacred'];
    return aio_cgi_profile_word($p) . ($h !== '' ? " · $h" : '') . ($jr !== '' ? " · $jr" : '');
}

// The profile the page is the editor of, by ?p= of the address $p and the settings $s (aio_settings_read): sel - its
// index, 'new' (a new one: the settings of the source in use, a config of its own made by the save), 'cur' (a new one
// of the source in use, with its config: none in use is a profile; a save without a name is the save of before), ''
// (no editor: the setting off); now - it is the one in use (the index; 'cur'); view - the settings the page shows
// (aio_profile_view: the source of the profile, the configs only its own; 'new': none; 'cur': $s); name - its name
// ('' of a new one). Another p (none, an index not there, 'new' with the profiles full): the one in use, else 'cur'.
function aio_cgi_editor($s, $p)
{
    if (!$s['src_profiles'])
        return array('sel' => '', 'now' => false, 'view' => $s, 'name' => '');
    $now = aio_profile_now($s);
    $k = preg_match('/^[0-9]{1,2}\z/', $p) ? intval($p) : -1;
    if ($k < 0 || !isset($s['profiles'][$k]))
        $k = $p === 'new' && count($s['profiles']) < AIO_PROFILES_MAX ? -1 : $now;
    if ($k >= 0)
        return array('sel' => strval($k), 'now' => $k === $now, 'view' => aio_profile_view($s, $s['profiles'][$k]),
            'name' => $s['profiles'][$k]['name']);
    if ($p !== 'new' || count($s['profiles']) >= AIO_PROFILES_MAX)
        return array('sel' => 'cur', 'now' => true, 'view' => $s, 'name' => '');
    $s['aio_confs'] = array();
    return array('sel' => 'new', 'now' => false, 'view' => $s, 'name' => '');
}

// The values of the form of the editor $ed (aio_cgi_editor) over $v (aio_cgi_values of its view), the settings $s:
// the fields pf_sel, pf_was, pf_now, pf_name, the placeholder of the name pf_def (aio_cgi_editor_def), prof - the data
// of the script (aio_cgi_profiles_data).
function aio_cgi_editor_values($v, $ed, $s)
{
    $v['pf_sel'] = $ed['sel'];
    $v['pf_was'] = $ed['name'];
    $v['pf_now'] = $ed['now'] ? '1' : '';
    $v['pf_name'] = $ed['name'];
    $v['pf_def'] = aio_cgi_editor_def($ed['sel'], $ed['view']);
    $v['prof'] = aio_cgi_profiles_data($s);
    return $v;
}

// The placeholder of "Название профиля" of the editor $sel (pf_sel) showing the settings $view: of a new one of the
// source in use (cur) - what a name does there (without one: no profile); of another one - the name a new one gets
// without one (aio_cgi_profile_def); '' - no editor.
function aio_cgi_editor_def($sel, $view)
{
    if ($sel === 'cur')
        return aio_cgi_l('Название, чтобы сохранить профилем', 'A name to save it as a profile');
    return $sel !== '' ? aio_cgi_profile_def($view) : '';
}

// The profiles of the settings $s for the script (#init.profiles, "profiles" of the answers): list - {name, line, now
// (the one in use: aio_profile_now), ready (aio_profile_ready; not: "конфига нет")} each in order; max -
// AIO_PROFILES_MAX.
function aio_cgi_profiles_data($s)
{
    $now = aio_profile_now($s);
    $list = array();
    foreach ($s['profiles'] as $i => $p)
        $list[] = array('name' => $p['name'], 'line' => aio_cgi_profile_line($p), 'now' => $i === $now,
            'ready' => (bool) aio_profile_ready($p));
    return array('list' => $list, 'max' => AIO_PROFILES_MAX);
}

// The block of the editor at the top of #src (only with the editor: pf_sel of $v not '', aio_cgi_editor; by the CSS
// only with "Профили источников" on, class xs): the list of the profiles (#pel, by the script: a link each, "+ Новый
// профиль"), the line why it is not followed (#ped: a change not saved), "Название профиля" (prof_name: posted, a
// change for the bar; of a new one of the source in use empty - no profile, the save of before), the line of the
// profile (#pen), "Сделать текущим" and "Удалить" (#peb, by the script: profile_use, profile_del), their results; the
// profile of the save - prof (pf_sel), prof_was (its name as the page came), prof_now (1: the page shows it in use).
function aio_cgi_editor_block($v)
{
    if ($v['pf_sel'] === '')
        return '';
    $f = aio_cgi_fields();
    if ($v['pf_sel'] === 'cur')
        $line = aio_cgi_l('Без названия — сохранить без профиля.', 'No name: saved without a profile.');
    // Of the kind chosen (by the CSS: xm - Debrid, Торренты; xo - the own config).
    elseif ($v['pf_sel'] === 'new')
        $line = '<span class="xm">' . aio_cgi_l('Новый профиль: на сервере создастся свой конфиг.',
            'A new profile: a config of its own is made on the server.') . '</span><span class="xo">' .
            aio_cgi_l('Новый профиль со своей ссылкой: конфиг на сервере не создаётся.',
            'A new profile with your own link: no config is made on the server.') . '</span>';
    else
        $line = $v['pf_now'] === '1' ? aio_cgi_l('Этот профиль сейчас на Дюне.', 'This profile is in use on the Dune.') :
            aio_cgi_l('Не текущий: источник сохранится в профиль, ключи и TorrServer — как обычно.',
            'Not in use: the source goes to the profile; keys and TorrServer save as usual.');
    $hidden = '';
    foreach (array('pf_sel', 'pf_was', 'pf_now') as $k)
        $hidden .= '<input type="hidden" id="' . $f[$k]['id'] . '" name="' . $f[$k]['name'] . '" value="' . aio_cgi_h($v[$k]) . '">';
    return '<div id="pe" class="xs"><div id="pel" class="list"></div><p class="hint wn" id="ped" hidden></p>' .
        aio_cgi_input($f['pf_name'], aio_cgi_l('Название профиля', 'Profile name'), $v['pf_name'], $v['pf_def']) .
        '<p class="hint" id="pen">' . $line . '</p><div class="btns" id="peb" hidden><button type="button" id="peu" ' .
        'class="btn ghost">' . aio_cgi_l('Сделать текущим', 'Make it current') . '</button><button type="button" id="pex" ' .
        'class="btn danger">' . aio_cgi_l('Удалить', 'Delete') . '</button></div><div id="per" class="res" aria-live="polite">' .
        '</div>' . $hidden . '</div>';
}

// The line of the profile on #cfg and #voices (their settings are of it): "Профиль «X»", "Новый профиль".
function aio_cgi_editor_line($v)
{
    if (!isset($v['pf_sel']) || $v['pf_sel'] === '' || $v['pf_sel'] === 'cur')
        return '';
    return '<p class="sub xs">' . ($v['pf_sel'] !== 'new' ? sprintf(aio_cgi_l('Профиль «%s»', 'Profile "%s"'),
        aio_cgi_h($v['pf_was'])) : aio_cgi_l('Новый профиль', 'New profile')) . '</p>';
}

// The editor of the save, by the posted $v (aio_cgi_posted: pf_sel, pf_was, pf_now, pf_name) and the stored settings
// $cur -> null (no editor: none posted; a new one of the source in use without a name - the save of before) or
// array(sel, old (the profile; null - a new one), name (as typed), now (it is in use in $cur: aio_profile_is; a new one
// of the source in use - none in use is a profile), view (the settings as of it: aio_cgi_editor), err (why
// refused: the profile gone or renamed meanwhile, the profiles full; '' - none)).
function aio_cgi_editor_posted($v, $cur)
{
    $sel = $v['pf_sel'];
    if ($sel === '' || ($sel === 'cur' && aio_profile_name($v['pf_name']) === ''))
        return null;
    $ed = array('sel' => $sel, 'old' => null, 'name' => $v['pf_name'], 'now' => false, 'view' => $cur, 'err' => '');
    if (preg_match('/^[0-9]{1,2}\z/', $sel))
    {
        $k = intval($sel);
        if (!isset($cur['profiles'][$k]) || $cur['profiles'][$k]['name'] !== $v['pf_was'])
            return array_merge($ed, array('err' => aio_cgi_profiles_changed()));
        $p = $cur['profiles'][$k];
        return array_merge($ed, array('old' => $p, 'now' => aio_profile_is($cur, $p), 'view' => aio_profile_view($cur, $p)));
    }
    if (count($cur['profiles']) >= AIO_PROFILES_MAX)
        return array_merge($ed, array('err' => aio_cgi_profiles_full()));
    if ($sel === 'cur')
        return array_merge($ed, array('now' => aio_profile_now($cur) < 0));
    $cur['aio_confs'] = array();
    return array_merge($ed, array('sel' => 'new', 'view' => $cur));
}

// The profile of the editor $ed (aio_cgi_editor_posted) by the values checked $data (aio_cgi_check_values with the
// view of it) and the settings stored $cur (read again just before the write; the profile found by its name - gone:
// refused); $t: the token (of the export) -> array(the settings to write, the profile, '') or array(null, null, why
// refused). In use (now of $ed): $data with the config of the profile in the cell (aio_profile_apply), the profile -
// the source of $data; else the settings of the source of $cur kept (aio_source_keys), the profile - the source of
// $data. Its config: the old one while the server and mode are those of it; else (a new one, another server or mode)
// the config of the cell of its server and mode when no profile has it (aio_profile_held) and it is not that of the
// source in use of no profile (unless this one is in use: a change of a profile not in use never changes the config
// the Dune plays) - a config of before the profiles is not lost; else none (made by a=aio_sync&prof). A new one
// without a name: the word of its kind (aio_cgi_profile_def). The name and the target (aio_profile_key) unique, the
// profiles at most AIO_PROFILES_MAX, the export with it loads back (aio_cgi_import_fits).
function aio_cgi_editor_save($ed, $data, $cur, $t)
{
    $k = $ed['old'] ? aio_profile_find($cur, $ed['old']['name']) : -1;
    if ($ed['old'] && $k < 0)
        return array(null, null, aio_cgi_profiles_changed());
    if (!$ed['old'] && count($cur['profiles']) >= AIO_PROFILES_MAX)
        return array(null, null, aio_cgi_profiles_full());
    $p = aio_profile_of($data);
    if ($p === null)
        return array(null, null, aio_cgi_profiles_changed());
    if (isset($p['base']))
    {
        $o = $ed['old'];
        $cell = $p['conf'];
        $use = $cur['source'] === 'made' ? aio_settings_conf($cur) : null;
        if ($o && isset($o['base']) && $o['base'] === $p['base'] && $o['mode'] === $p['mode'])
            $p['conf'] = $o['conf'];
        elseif (!$cell || aio_profile_held($cur, $cell) || (!$ed['now'] && $use && $use['uuid'] === $cell['uuid']))
            $p['conf'] = null;
    }
    // A new one without a name: the word of its kind (the hint of the field).
    $name = $ed['sel'] === 'new' && aio_profile_name($ed['name']) === '' ? aio_cgi_profile_def($data) : $ed['name'];
    $p = aio_profile_ok(array('name' => $name) + $p, $cur);
    if (!$p)
        return array(null, null, sprintf(aio_cgi_l('Название профиля: от 1 до %d символов, без управляющих и невидимых',
            'Profile name: 1 to %d characters, no control or invisible ones'), AIO_PROFILE_NAME_MAX));
    foreach ($cur['profiles'] as $i => $x)
    {
        if ($i === $k)
            continue;
        if ($x['name'] === $p['name'])
            return array(null, null, sprintf(aio_cgi_l('Профиль «%s» уже есть, выберите другое название',
                'A profile "%s" exists already, choose another name'), $p['name']));
        if (aio_profile_key($x) === aio_profile_key($p))
            return array(null, null, sprintf(aio_cgi_l('Такие настройки уже в профиле «%s»',
                'These settings are in the profile "%s" already'), $x['name']));
    }
    if ($ed['now'])
        $data = aio_profile_apply($data, $p);
    else
    {
        foreach (aio_source_keys() as $key)
            $data[$key] = $cur[$key];
    }
    if ($k >= 0)
        $data['profiles'][$k] = $p;
    else
        $data['profiles'][] = $p;
    if (!aio_cgi_import_fits(aio_cgi_export_json($data, $t)))
        return array(null, null, aio_cgi_l('Не сохранено: с этим профилем резервная копия будет слишком большой',
            'Not saved: with this profile the backup file would be too big'));
    return array($data, $p, '');
}

// The profile of the editor $ed of the save saved (aio_cgi_editor_save) with the values $data checked (the configs
// and the profiles - of $cur) and the settings stored $cur; $t: the token, $v: the values of the form -> array(the
// settings to write, the profile). Refused (the err of $ed too: gone, renamed, the profiles full) - the form back with
// why on the field of the name, nothing written (aio_cgi_failed). The save asks it before the keys are checked (no
// request for a save refused) and again just before the write.
function aio_cgi_editor_check($ed, $data, $cur, $t, $v)
{
    $data['aio_confs'] = $cur['aio_confs'];
    $data['profiles'] = $cur['profiles'];
    list($with, $p, $why) = $ed['err'] !== '' ? array(null, null, $ed['err']) : aio_cgi_editor_save($ed, $data, $cur, $t);
    if ($with === null)
    {
        aio_cgi_log('save: the profile refused, nothing written');
        $f = aio_cgi_fields();
        $e = array(aio_cgi_err($why, $f['pf_name']['name']));
        aio_cgi_failed($v['ajax'], 200, $e, aio_cgi_form($t, $v, $e, false));
    }
    return array($with, $p);
}

// The settings $s of a save without the editor ("Профили источников" off, or a new one of the source in use without
// a name): the config in use (aio_settings_conf) of a profile with other settings than those saved (aio_profile_same:
// another template or JacRed typed) leaves the cell - the profile keeps it, the save makes a new one (aio create): the
// config of a profile changes only with it.
function aio_cgi_profile_drift($s)
{
    $c = aio_settings_conf($s);
    foreach ($s['source'] === 'made' && $c ? $s['profiles'] : array() as $p)
    {
        if (!isset($p['url']) && $p['conf'] && $p['conf']['uuid'] === $c['uuid'] && !aio_profile_same($s, $p))
            return aio_settings_cell($s, aio_settings_base($s), $s['aio_mode'], null);
    }
    return $s;
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

// POST a=profile_use | profile_del (ajax=1): as above.
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
    $i = aio_cgi_param($_POST, 'i');
    $k = preg_match('/^[0-9]{1,2}\z/', $i) ? intval($i) : -1;
    if (!isset($s['profiles'][$k]) || $s['profiles'][$k]['name'] !== aio_cgi_param($_POST, 'was'))
        aio_cgi_profile_failed($a, 'no such profile', aio_cgi_profiles_changed(), $s);
    $p = $s['profiles'][$k];
    if ($a === 'profile_del')
    {
        array_splice($s['profiles'], $k, 1);
        $msg = sprintf(aio_cgi_l('Профиль «%s» удалён', 'Profile "%s" deleted'), $p['name']);
    }
    else
    {
        if (aio_profile_is($s, $p))
            aio_cgi_profile_answer(true, sprintf(aio_cgi_l('Профиль «%s» уже текущий', 'Profile "%s" is in use already'),
                $p['name']), $s);
        if (!aio_profile_ready($p))
            aio_cgi_profile_failed($a, 'no config', sprintf(aio_cgi_l('Конфига профиля ещё нет: нажмите «%s»',
                'The profile has no config yet: press "%s"'), aio_cgi_saveb()), $s);
        list($s, $kept, $full) = aio_profile_keep($s, aio_cgi_profile_words());
        if ($full !== '')
            aio_cgi_profile_failed($a, 'the profiles full, the previous source not saved', aio_cgi_profiles_full_use(), $s);
        if ($kept >= 0)
            aio_cgi_log("$a: saved previous as profile " . aio_profile_tag($kept, $s['profiles'][$kept]));
        $s = aio_profile_apply($s, $p);
        $msg = sprintf(aio_cgi_l('Профиль «%s» теперь текущий', 'Profile "%s" is in use now'), $p['name']);
    }
    $why = aio_cgi_store($dir, $s);
    if ($why !== '')
        aio_cgi_profile_failed($a, $why, aio_cgi_l('Не сохранено: ошибка записи на Дюне', 'Not saved: write error on the Dune'),
            aio_settings_read($dir));
    aio_cgi_log("$a: ok");
    aio_cgi_profile_answer(true, $msg, $s);
}
