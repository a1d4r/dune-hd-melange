<?php
// The settings page (cgi/settings.php): the source profiles (0.58.0) - their block on #src
// (aio_cgi_profiles_block) and the texts of the script, their data for the script
// (#init.profiles, the answers below) and the actions of the script, POST
// ?t=<token>&a=<action>&ajax=1 -> JSON {ok, msg[, profiles][, part][, manifest]}:
//   profiles     -> the list, nothing written;
//   profile_add  (name) -> the stored source (never the form: the page saves it
//                first) as a new profile, its name or the word of its kind;
//                add and edit: refused when the file of the export would not load
//                back (aio_cgi_import_fits);
//   profile_edit (i, was, name[, url]) -> the name of profile i, the link of an own
//                config; the profile in use: the new link is manifest_url too;
//   profile_del  (i, was) -> profile i gone.
// i: the index the page shows, was: its name there - another one in the file
// (another tab): nothing written, the list of the file sent. Each one reads
// settings.json and writes it whole (aio_cgi_store); the save of the form keeps the
// profiles of the file. profiles: those of the file (none when it was not read),
// part: of the error - name, url (not a field of the form), manifest: true - the new link of the profile in use
// is manifest_url too.

// The texts of the profiles in the i18n of the script (aio_cgi_js: web/src/profiles.ts takes them by these keys).
function aio_cgi_profile_texts()
{
    return array('profileNow' => aio_cgi_l('сейчас', 'now'),
        'profileNoConf' => sprintf(aio_cgi_l('Конфига нет: выберите этот сервер и режим, затем нажмите «%s».',
            'No config: choose this server and mode, then press "%s".'), aio_cgi_saveb()),
        'profileName' => aio_cgi_l('Название', 'Name'), 'profileLink' => aio_cgi_l('Ссылка на конфиг', 'Config link'),
        'profileEdit' => aio_cgi_l('Изменить', 'Edit'), 'profileCancel' => aio_cgi_l('Отмена', 'Cancel'),
        'profileSave' => aio_cgi_l('Сохранить профиль', 'Save the profile'),
        'profileDelete' => aio_cgi_l('Удалить', 'Delete'),
        'profileDeleteConfirm' => aio_cgi_l('Удалить профиль «%s»?', 'Delete the profile "%s"?'));
}

// The line of the profiles full: under "Сохранить текущий как профиль" (#prx) and of a=profile_add refused.
function aio_cgi_profiles_full()
{
    return sprintf(aio_cgi_l('Профилей уже %d. Удалите лишний, чтобы добавить новый.',
        'There are %d profiles already. Delete one to add another.'), AIO_PROFILES_MAX);
}

// A text field of the profiles: not posted (no name=, so not a change for the bar of the save either), of the form
// #prf (aio_cgi_profiles_form), not the one it is in.
function aio_cgi_unposted($id, $label, $placeholder, $max)
{
    return '<label for="' . $id . '">' . $label . '</label><div class="f"><input id="' . $id . '" form="prf" type="text" value="" placeholder="' .
        aio_cgi_h($placeholder) . '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="' .
        $max . '"></div>';
}

// The form of the fields of the profiles (form="prf"), after the one of the page: Enter in them submits it, not the
// save (the script presses the button of the field instead). Its default button as the one of the page (.dflt: off
// the screen, not hidden - a browser may submit no form by a button not shown).
function aio_cgi_profiles_form()
{
    return '<form id="prf"><button type="submit" class="dflt" tabindex="-1" aria-hidden="true"></button></form>';
}

// The source profiles (0.58.0) at the bottom of #src: shown by the script (none without it) and by the CSS only
// with "Профили источников" on (class xs). The list (#prl) by the script from #init.profiles; under it the line of
// no profiles, "Название" and "Сохранить текущий как профиль" (a=profile_add), the lines why it is disabled: a
// change not saved yet, the profiles full, the source in use a profile already.
function aio_cgi_profiles_block()
{
    return '<div id="pr" class="xs" hidden><h3 class="gh">' . aio_cgi_l('Профили источников', 'Source profiles') .
        '</h3><div id="prl"></div><p class="hint" id="pre" hidden>' .
        aio_cgi_l('Профилей нет: сохраните хотя бы два, чтобы переключать их на Дюне.',
        'No profiles: save at least two to switch between them on the Dune.') . '</p>' .
        aio_cgi_unposted('prn', aio_cgi_l('Название', 'Name'), '', AIO_PROFILE_NAME_MAX) .
        '<button type="button" id="pra" class="btn ghost">' .
        aio_cgi_l('Сохранить текущий как профиль', 'Save the current source as a profile') . '</button>' .
        '<p class="hint" id="prd" hidden>' . aio_cgi_l('Сначала сохраните настройки, потом текущий источник как профиль.',
        'Save the settings first, then the current source as a profile.') . '</p>' .
        '<p class="hint" id="prx" hidden>' . aio_cgi_profiles_full() . '</p>' .
        '<p class="hint" id="prw" hidden>' . aio_cgi_l('Текущий источник уже сохранён профилем, он отмечен «сейчас».',
        'The current source is saved as a profile already: it is marked "now".') . '</p>' .
        '<div id="prr" class="res" aria-live="polite"></div></div>';
}

// The kind of profile $p in the words of the page: Debrid, Торренты, Свой конфиг.
function aio_cgi_profile_word($p)
{
    if (isset($p['url']))
        return aio_cgi_l('Свой конфиг', 'Own config');
    return $p['mode'] === AIO_MODE_P2P ? aio_cgi_mode_p2p() : 'Debrid';
}

// The name of a new profile of the stored source of $s: the word of its kind, with
// a number from 2 while the name is taken.
function aio_cgi_profile_def($s)
{
    $target = aio_profile_of($s);
    $w = aio_cgi_profile_word($target ? $target : array('mode' => $s['aio_mode']));
    $name = $w;
    for ($n = 2; aio_profile_find($s, $name) >= 0 && $n <= AIO_PROFILES_MAX + 1; $n++)
        $name = "$w $n";
    return $name;
}

// The line of profile $p under its name: its kind and host (the page is in the home
// network: hosts are shown).
function aio_cgi_profile_line($p)
{
    $h = aio_cgi_host(isset($p['url']) ? $p['url'] : $p['base']);
    return aio_cgi_profile_word($p) . ($h !== '' ? " · $h" : '');
}

// The profiles of the settings $s for the script: list - {name, line, now (the one in
// use: aio_profile_now), ready (aio_profile_ready; not - "Конфига нет"), url (an own
// config only)} each in order; def - the name of a new one (aio_cgi_profile_def), max -
// AIO_PROFILES_MAX.
function aio_cgi_profiles_data($s)
{
    $now = aio_profile_now($s);
    $list = array();
    foreach ($s['profiles'] as $i => $p)
    {
        $x = array('name' => $p['name'], 'line' => aio_cgi_profile_line($p), 'now' => $i === $now,
            'ready' => aio_profile_ready($s, $p));
        if (isset($p['url']))
            $x['url'] = $p['url'];
        $list[] = $x;
    }
    return array('list' => $list, 'def' => aio_cgi_profile_def($s), 'max' => AIO_PROFILES_MAX);
}

// The answer of an action: JSON {ok, msg, profiles (of $s; null - none), more}.
function aio_cgi_profile_answer($ok, $msg, $s, $more = array())
{
    aio_cgi_json(200, array_merge(array('ok' => $ok, 'msg' => $msg), $s !== null ?
        array('profiles' => aio_cgi_profiles_data($s)) : array(), $more));
}

// An action refused: nothing written, $why to the log.
function aio_cgi_profile_failed($a, $why, $msg, $s, $more = array())
{
    aio_cgi_log("$a: $why, nothing written");
    aio_cgi_profile_answer(false, $msg, $s, $more);
}

// The checks of the name and the target of profile $p (as typed) for the place $k of the
// profiles of $s (-1: a new one) -> the profile (aio_profile_ok) or the answer refused.
function aio_cgi_profile_checked($a, $s, $p, $k)
{
    $ok = aio_profile_ok($p);
    if (!$ok)
    {
        aio_cgi_profile_failed($a, 'bad name', sprintf(aio_cgi_l('Название: от 1 до %d символов, без управляющих и невидимых',
            'Name: 1 to %d characters, no control or invisible ones'), AIO_PROFILE_NAME_MAX), $s, array('part' => 'name'));
    }
    foreach ($s['profiles'] as $i => $x)
    {
        if ($i === $k)
            continue;
        if ($x['name'] === $ok['name'])
            aio_cgi_profile_failed($a, 'name taken', sprintf(aio_cgi_l('Профиль «%s» уже есть, выберите другое название',
                'A profile "%s" exists already, choose another name'), $ok['name']), $s, array('part' => 'name'));
        if (aio_profile_key($x) === aio_profile_key($ok))
        {
            aio_cgi_profile_failed($a, 'target taken', sprintf(aio_cgi_l('Этот источник уже сохранён профилем «%s»',
                'This source is saved already as the profile "%s"'), $x['name']), $s, isset($ok['url']) && $k >= 0 ?
                array('part' => 'url') : array());
        }
    }
    return $ok;
}

// POST a=profiles | profile_add | profile_edit | profile_del (ajax=1): as above; $t: the token of the page (of the export).
function aio_cgi_profile_post($dir, $a, $t)
{
    $s = aio_settings_read(aio_cgi_dir_ok($dir) ? $dir : '', $why);
    if ($why !== '')
        aio_cgi_log("$a: settings.json $why");
    if ($a === 'profiles')
        aio_cgi_profile_answer(true, '', $why === '' ? $s : null);
    if ($why !== '' || !aio_cgi_dir_ok($dir))
    {
        aio_cgi_profile_failed($a, $why !== '' ? 'not read' : 'no data dir', aio_cgi_l('Не сохранено: настройки на Дюне не читаются',
            'Not saved: the settings on the Dune cannot be read'), null);
    }
    $name = aio_cgi_param($_POST, 'name');
    $more = array();
    if ($a === 'profile_add')
    {
        $target = aio_profile_of($s);
        if (!$target || !aio_profile_ready($s, $target))
            aio_cgi_profile_failed($a, 'no config', aio_cgi_l('Сначала сохраните: конфига ещё нет',
                'Save first: there is no config yet'), $s);
        if (count($s['profiles']) >= AIO_PROFILES_MAX)
            aio_cgi_profile_failed($a, 'full', aio_cgi_profiles_full(), $s);
        if (aio_profile_name($name) === '')
            $name = aio_cgi_profile_def($s);
        $p = aio_cgi_profile_checked($a, $s, array('name' => $name) + $target, -1);
        $s['profiles'][] = $p;
        $msg = sprintf(aio_cgi_l('Профиль «%s» сохранён', 'Profile "%s" saved'), $p['name']);
    }
    else
    {
        $i = aio_cgi_param($_POST, 'i');
        $k = preg_match('/^[0-9]{1,2}$/', $i) ? intval($i) : -1;
        if (!isset($s['profiles'][$k]) || $s['profiles'][$k]['name'] !== aio_cgi_param($_POST, 'was'))
            aio_cgi_profile_failed($a, 'no such profile', aio_cgi_l('Профили изменились в другой вкладке: список обновлён',
                'The profiles changed in another tab: the list is updated'), $s);
        $old = $s['profiles'][$k];
        if ($a === 'profile_del')
        {
            array_splice($s['profiles'], $k, 1);
            $msg = sprintf(aio_cgi_l('Профиль «%s» удалён', 'Profile "%s" deleted'), $old['name']);
        }
        else
        {
            $p = array('name' => $name) + $old;
            if (isset($old['url']))
            {
                $p['url'] = aio_manifest_url(aio_cgi_param($_POST, 'url'));
                if ($p['url'] === '')
                    aio_cgi_profile_failed($a, 'bad link', aio_cgi_fmt_manifest(), $s, array('part' => 'url'));
            }
            $p = aio_cgi_profile_checked($a, $s, $p, $k);
            if ($p === $old)
                aio_cgi_profile_answer(true, aio_cgi_l('Профиль не изменился', 'The profile is the same'), $s);
            // The one in use: its new link is the source now, as MENU of a list would set it.
            if (isset($p['url']) && $p['url'] !== $old['url'] && aio_profile_is($s, $old))
            {
                $s = aio_profile_apply($s, $p);
                $more['manifest'] = true;
            }
            $s['profiles'][$k] = $p;
            $msg = sprintf(aio_cgi_l('Профиль «%s» сохранён', 'Profile "%s" saved'), $p['name']);
        }
    }
    // A file of the export with the new profile or link loads back (a=import); a delete only makes it shorter.
    if ($a !== 'profile_del' && !aio_cgi_import_fits(aio_cgi_export_json($s, $t)))
        aio_cgi_profile_failed($a, 'the export over ' . AIO_CGI_IMPORT_MAX . ' bytes', aio_cgi_l('Не сохранено: с этим профилем резервная копия будет слишком большой',
            'Not saved: with this profile the backup file would be too big'), aio_settings_read($dir));
    // The plugin reads no settings.json over AIO_SETTINGS_MAX.
    if (strlen(aio_settings_json($s)) > AIO_SETTINGS_MAX)
        aio_cgi_profile_failed($a, 'settings over ' . AIO_SETTINGS_MAX . ' bytes', aio_cgi_l('Не сохранено: настройки слишком большие',
            'Not saved: the settings are too big'), aio_settings_read($dir));
    $why = aio_cgi_store($dir, $s);
    if ($why !== '')
        aio_cgi_profile_failed($a, $why, aio_cgi_l('Не сохранено: ошибка записи на Дюне', 'Not saved: write error on the Dune'),
            aio_settings_read($dir));
    aio_cgi_log("$a: ok" . (isset($more['manifest']) ? ', the link in use too' : ''));
    aio_cgi_profile_answer(true, $msg, $s, $more);
}
