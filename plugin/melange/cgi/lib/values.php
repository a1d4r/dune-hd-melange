<?php
// The settings page (cgi/settings.php): the values of the form (from the
// settings: mel_cgi_values; posted: mel_cgi_posted), their format check
// (mel_cgi_check_values) and what the save does with them (mel_cgi_resets,
// mel_cgi_same_server), no request.

// Values of the form from the settings (mel_settings_read) or as posted
// (mel_cgi_posted), by the keys of mel_cgi_fields() - strings (aio_tpl,
// aio_tpl_p2p, aio_mode: the one chosen in a form back, '' from the
// settings; tpl_seen, tpl_p2p_seen only posted; ts_save: mel_cgi_ts_save_of()
// of the settings, markers: mel_cgi_markers_of() (of markers_exact too), those of mel_cgi_toggles(): mel_cgi_toggle_of(),
// cat_card: mel_cgi_cat_card_of(), as posted - none: '',
// settings.php puts the stored one; cat_card_was: cat_card of the settings, as posted ('' none);
// pf_sel, pf_from, pf_name - the editor of the sources, '' (mel_cgi_editor_values puts it), as posted;
// pf_def - the placeholder of the name, pf_list - the block of the sources shown (mel_cgi_editor_values; a save:
// settings.php); prof - the sources for the script (mel_cgi_profiles_data of the settings, of the file on a save);
// tpl_new, tpl_p2p_new -
// aio_tpl, aio_tpl_p2p of the settings (for a new config);
// mode_cur - aio_mode of the settings (one for all servers); servers -
// lists of strings by mel_cgi_server_fields(); confs - aio_confs of the
// settings (never posted).
function mel_cgi_values($s)
{
    $v = array();
    foreach (mel_cgi_fields() as $k => $f)
        if ($f['set'] !== '')
            $v[$k] = $s[$f['set']];
    $v['tpl_new'] = $v['aio_tpl'];
    $v['aio_tpl'] = '';
    $v['tpl_p2p_new'] = $v['aio_tpl_p2p'];
    $v['aio_tpl_p2p'] = '';
    $v['mode_cur'] = $v['aio_mode'];
    $v['aio_mode'] = '';
    $v['ts_save'] = mel_cgi_ts_save_of($s['ts_save']);
    $v['markers'] = mel_cgi_markers_of($s['markers'], $s['markers_exact']);
    foreach (mel_cgi_toggles() as $k => $x)
        $v[$k] = mel_cgi_toggle_of($k, $v[$k]);
    $v['cat_card'] = mel_cgi_cat_card_of($s['cat_card']);
    $v['cat_card_was'] = $v['cat_card'];
    // No editor of the sources (mel_cgi_editor_values puts one).
    foreach (array('pf_sel', 'pf_from', 'pf_name', 'pf_def') as $k)
        $v[$k] = '';
    $v['pf_list'] = false;
    $v['prof'] = mel_cgi_profiles_data($s);
    $v['cat_rows'] = mel_cgi_cat_rows_of($s['cat_rows']);
    $v['cat_rows_was'] = $v['cat_rows'];
    $v['servers'] = $s['servers'];
    $v['confs'] = $s['aio_confs'];
    // Not of settings.json: the form takes them from data_dir/debrid_main (settings.php).
    $v['debrid_main'] = '';
    $v['debrid_main_was'] = '';
    return $v;
}

// The AIOStreams server of the form: one of mel_aio_servers() or 'own';
// another one (a form of 0.35.1 with "manifest", a forged one) -> the
// first one, as mel_settings_read.
function mel_cgi_server_choice($as)
{
    $list = array_keys(mel_aio_servers());
    return in_array($as, array_merge($list, array('own')), true) ? $as : $list[0];
}

function mel_cgi_posted()
{
    $f = mel_cgi_fields();
    $v = array('servers' => array(), 'confs' => array());
    // A raw one as typed (the own JacRed: mel_jacred_own trims it).
    foreach ($f as $k => $x)
        $v[$k] = isset($x['raw']) ? mel_cgi_param($_POST, $x['name']) : trim(mel_cgi_param($_POST, $x['name']), MEL_TRIM);
    // The answer of 0.43.0 (watch): the source and the mode by it; the own
    // config and Lampac (v2: its fields, mel_cgi_check_values) keep the stored mode. None (a form of 0.42 or older) or
    // another one (an error, mel_cgi_check_values): source and aio_mode as posted.
    $w = array_search($v['watch'], mel_cgi_watches(), true);
    if ($w !== false)
    {
        $v['source'] = $w === 'wo' ? 'own' : 'made';
        $v['aio_mode'] = $w === 'wo' || $w === 'wl' ? '' : $v['watch'];
    }
    // A form without the source: 0.35.1 chose the own config by aio_server
    // "manifest", 0.35.0 and older (no aio_own_url) by the manifest address
    // alone, as mel_settings_read.
    if ($v['source'] !== 'made' && $v['source'] !== 'own')
        $v['source'] = $v['aio_server'] === 'manifest' || ($v['manifest'] !== '' &&
            !array_key_exists($f['aio_own']['name'], $_POST)) ? 'own' : 'made';
    // Then the server as the save takes it: the page checks that card, the main screen shows it.
    $v['aio_server'] = mel_cgi_server_choice($v['aio_server']);
    for ($n = 1; $n <= MEL_SERVERS_MAX; $n++)
    {
        $s = array();
        foreach (array_keys(mel_cgi_server_fields()) as $k)
        {
            $x = mel_cgi_srv_field($n, $k);
            $s[$k] = mel_cgi_param($_POST, $x['name']);
        }
        $v['servers'][] = $s;
    }
    return $v;
}

// An error of the save: its text and the name= of the form field it is
// about (null: none; of a pair, the first one).
function mel_cgi_err($msg, $field = null)
{
    return array('msg' => $msg, 'field' => $field);
}

// Values of the form -> array(settings.json v 2 without saved_at, errors for
// the page (mel_cgi_err), each with its field). Format only, no request.
function mel_cgi_check_values($v)
{
    $f = mel_cgi_fields();
    $errors = array();
    // Only the fields of the chosen source are checked; a wrong one of the
    // other (hidden) is not written: the stored value stays ($v['cur']). Lampac (v2): its fields only, those of
    // AIOStreams and JacRed hidden.
    $lp = $v['watch'] === 'lampac';
    $made = $v['source'] === 'made' && !$lp;
    $ownc = $v['source'] === 'own' && !$lp;
    // No field (a form of 0.42 or older), or a wrong one of the own config: the stored one.
    $mode = in_array($v['aio_mode'], mel_aio_modes(), true) ? $v['aio_mode'] : $v['cur']['aio_mode'];
    if ($v['watch'] !== '' && !in_array($v['watch'], mel_cgi_watches(), true))
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Откуда брать фильмы: нет такого ответа', 'Where to get films: no such answer'),
            $f['watch']['name']);
    }
    elseif ($made && $v['aio_mode'] !== '' && $mode !== $v['aio_mode'])
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Режим: нет такого режима', 'Mode: no such mode'), $f['watch']['name']);
    }
    // The keys of settings.json: only by mel_cgi_fields().
    $set = array();
    foreach ($f as $k => $x)
        $set[$k] = $x['set'];
    $d = array('v' => MEL_SETTINGS_V, $set['source'] => $lp ? $v['cur']['source'] : $v['source'],
        $set['manifest'] => mel_manifest_url($v['manifest']));
    if ($v['manifest'] !== '' && $d[$set['manifest']] === '')
    {
        if (!$ownc)
            $d[$set['manifest']] = $v['cur'][$set['manifest']];
        else
        {
            $errors[] = mel_cgi_err(mel_cgi_fmt_manifest(), $f['manifest']['name']);
        }
    }
    // The Debrid keys: checked below by the mode of the config after the
    // save; their errors go here, in the order of the page.
    $keys_at = count($errors);
    $d[$set['rd']] = '';
    $d[$set['tb']] = '';
    // Not one of the list (a form of 0.33): the default; the own one only with its address.
    $d[$set['jacred']] = $v['jacred'] === 'own' || array_key_exists($v['jacred'], mel_jacred_builtin()) ? $v['jacred'] :
        MEL_JACRED_DEFAULT;
    $own = mel_jacred_own(array('url' => $v['jo_url'], 'key' => $v['jo_key']));
    $d[$set['jo_url']] = $own ? $own['url'] : '';
    $d[$set['jo_key']] = $own ? $own['key'] : '';
    if (!$own && $lp)
        list($d[$set['jacred']], $d[$set['jo_url']], $d[$set['jo_key']]) = array($v['cur'][$set['jacred']],
            $v['cur'][$set['jo_url']], $v['cur'][$set['jo_key']]);
    elseif (!$own && (trim($v['jo_url'] . $v['jo_key'], MEL_TRIM) !== '' || $d[$set['jacred']] === 'own'))
    {
        // The address alone right: the key is wrong.
        $errors[] = mel_cgi_err(mel_cgi_l('Свой JacRed', 'Own JacRed') . ': ' . mel_cgi_fmt_jacred(),
            $f[mel_jacred_own(array('url' => $v['jo_url'], 'key' => '')) ? 'jo_key' : 'jo_url']['name']);
    }
    $d[$set['aio_server']] = mel_cgi_server_choice($v['aio_server']);
    // The fields of both sources are kept; those of the chosen one must be there.
    if ($ownc && $v['manifest'] === '')
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Укажите ссылку на конфиг — или выберите Debrid или «Торренты»',
            'Enter the config link, or choose Debrid or "Torrents"'), $f['manifest']['name']);
    }
    $d[$set['aio_own']] = mel_aio_own($v['aio_own']);
    // Hidden unless another server is chosen to make the config on.
    if ($v['aio_own'] !== '' && $d[$set['aio_own']] === '' && !($made && $d[$set['aio_server']] === 'own'))
        $d[$set['aio_own']] = $v['cur'][$set['aio_own']];
    elseif ($made && $d[$set['aio_server']] === 'own' && $d[$set['aio_own']] === '')
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Свой сервер AIOStreams: нужен адрес вида http(s)://хост[:порт] без пути',
            'Own AIOStreams server: an address like http(s)://host[:port] without a path is needed'), $f['aio_own']['name']);
    }
    $d[$set['aio_mode']] = $mode;
    // No field (a form of 0.39 or older), or a wrong one of the own config: the stored one.
    $d[$set['aio_tpl']] = mel_tpl_debrid($v['aio_tpl']) ? $v['aio_tpl'] : $v['cur'][$set['aio_tpl']];
    // That of P2P (0.44.0): only one chosen, another than the page showed
    // (tpl_p2p_seen); as shown, none (a form of 0.43 or older) or a wrong
    // one: the stored one. Else a save as shown (a config of P2P of 0.43:
    // p2p; any save of Debrid) would drop the default p2p_jacred for a new
    // config. Debrid as before (0.40.0): the one posted, shown or chosen
    // (its default addons is that of the configs of before).
    $ok = array('aio_tpl' => mel_tpl_debrid($v['aio_tpl']), 'aio_tpl_p2p' => mel_tpl_p2p($v['aio_tpl_p2p']));
    $d[$set['aio_tpl_p2p']] = $ok['aio_tpl_p2p'] && $v['aio_tpl_p2p'] !== $v['tpl_p2p_seen'] ? $v['aio_tpl_p2p'] :
        $v['cur'][$set['aio_tpl_p2p']];
    foreach ($ok as $k => $good)
    {
        if ($made && $v[$k] !== '' && !$good)
        {
            $errors[] = mel_cgi_err(mel_cgi_l('Шаблон конфига: нет такого шаблона', 'Config template: no such template'),
                $f[$k]['name']);
        }
    }
    // The page showed another one of the mode for the chosen server (none: a
    // form of 0.39 or older, of P2P 0.43 or older): another tab changed it
    // since. A page without the script showed the stored server only; the
    // script, for an own address other than the stored one, the one for a
    // new config (its conf()). Only the mode chosen: the other one hides its
    // template, no reset by it there.
    $tk = mel_cgi_tpl_keys($mode);
    $k = $tk['field'];
    $seen = $tk['seen'];
    $new = $v['cur'][$set[$k]];
    $shown = $d[$set['aio_server']] === 'own' && $d[$set['aio_own']] !== $v['cur'][$set['aio_own']] ? $new :
        mel_cgi_tpl_shown($mode, mel_settings_base($d), $v['confs'], $new);
    if (!$lp && $v[$seen] !== '' && ($v['ajax'] || mel_cgi_same_server($d, $v['cur'])) && $v[$seen] !== $shown)
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Шаблон изменён в другой вкладке — обновите страницу',
            'The template was changed in another tab: reload the page'), $f[$k]['name']);
    }
    // The mode Debrid and no key to make the config there (none of Debrid there yet).
    $base = mel_settings_base($d);
    // The Debrid keys: only of the mode Debrid (else not sent; a wrong one: the stored one kept).
    $keys = array();
    foreach (array('rd', 'tb') as $what)
    {
        $k = $set[$what];
        $d[$k] = mel_key_ok($what, $v[$what]);
        if ($v[$what] !== '' && $d[$k] === '')
        {
            if (!$made || $mode !== MEL_MODE_DEBRID)
                $d[$k] = $v['cur'][$k];
            else
            {
                $keys[] = mel_cgi_err(mel_cgi_fmt_key($what), $f[$what]['name']);
            }
        }
    }
    array_splice($errors, $keys_at, 0, $keys);
    if ($made && $mode === MEL_MODE_DEBRID && $base !== '' && !isset($v['confs'][$base][MEL_MODE_DEBRID]) &&
        $d[$set['rd']] === '' && $d[$set['tb']] === '' && $v['rd'] . $v['tb'] === '')
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Для Debrid нужен ключ Real-Debrid или TorBox; без ключа выберите «Торренты»',
            'Debrid needs a Real-Debrid or TorBox key; without one, choose "Torrents"'), $f['rd']['name']);
    }
    // P2P: the own JacRed goes to Magnetio in one path segment "k=v|k=v".
    if ($made && $mode === MEL_MODE_P2P && $d[$set['jacred']] === 'own' && strpos($d[$set['jo_key']], '|') !== false)
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Свой JacRed: в режиме «Торренты» ключ — без символа |',
            'Own JacRed: with "Torrents", the key must not contain |'), $f['jo_key']['name']);
    }
    $d[$set['tmdb']] = mel_key_ok('tmdb', $v['tmdb']);
    // Hidden: the own config, or a server with TMDB of its own.
    if ($v['tmdb'] !== '' && $d[$set['tmdb']] === '' && (!$made || mel_tmdb_sent($base, 'k') === ''))
        $d[$set['tmdb']] = $v['cur'][$set['tmdb']];
    elseif ($v['tmdb'] !== '' && $d[$set['tmdb']] === '')
    {
        $errors[] = mel_cgi_err(mel_cgi_fmt_key('tmdb'), $f['tmdb']['name']);
    }
    // The settings on/off (no_torrents among them, before $off). None posted: the stored one already (settings.php).
    foreach (mel_cgi_toggles() as $k => $x)
    {
        $d[$set[$k]] = $v[$k] === $x[2];
        if (!array_key_exists($v[$k], $x[1]))
            $errors[] = mel_cgi_err(sprintf(mel_cgi_l('%s: нет такого ответа', '%s: no such answer'), $x[0]), $f[$k]['name']);
    }
    // In effect, TorrServer and the servers are hidden: a wrong value there is not
    // in the way and not written, the stored one stays.
    $off = mel_no_torrents($d);
    // An empty block is none.
    $d['servers'] = array();
    foreach ($v['servers'] as $i => $s)
    {
        if (trim($s['name'] . $s['url'] . $s['user'] . $s['category'] . $s['tags'], MEL_TRIM) . $s['pass'] === '')
            continue;
        $c = mel_server_conf($s);
        if ($c)
            $d['servers'][] = $c;
        elseif ($off)
        {
            $d['servers'] = $v['cur']['servers'];
            break;
        }
        else
        {
            $x = mel_cgi_srv_field($i + 1, 'url');
            $errors[] = mel_cgi_err(mel_cgi_l('Сервер', 'Server') . ' ' . ($i + 1) . ': ' . mel_cgi_l('нужен адрес вида ' .
                'http://хост:порт; название — до 40 символов, логин, категория и теги — до 100, пароль — до 200, ' .
                'без управляющих символов', 'an address like http://host:port is needed; name up to 40 characters, ' .
                'login, category and tags up to 100, password up to 200, no control characters'), $x['name']);
        }
    }
    // One by one: list() of PHP 5 assigns from the right, the keys would go in reverse.
    $ts = mel_cgi_ts_parse($v['ts'], $v['ts_user'], $v['ts_pass']);
    $d[$set['ts']] = $ts[0];
    $d[$set['ts_user']] = $ts[1];
    $d[$set['ts_pass']] = $ts[2];
    $bad = $ts[3];
    if ($bad && $off)
    {
        $d[$set['ts']] = $v['cur'][$set['ts']];
        $d[$set['ts_user']] = $v['cur'][$set['ts_user']];
        $d[$set['ts_pass']] = $v['cur'][$set['ts_pass']];
    }
    elseif ($bad)
        $errors[] = mel_cgi_err($bad[1], $f[$bad[0]]['name']);
    // None posted: the stored one already (settings.php).
    $d[$set['ts_save']] = $v['ts_save'] === mel_cgi_ts_save_of(true);
    if (!array_key_exists($v['ts_save'], mel_cgi_ts_saves()) && $off)
        $d[$set['ts_save']] = $v['cur'][$set['ts_save']];
    elseif (!array_key_exists($v['ts_save'], mel_cgi_ts_saves()))
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Что делать с раздачей: нет такого ответа', 'What to do with a release: no such answer'),
            $f['ts_save']['name']);
    }
    $d[$set['markers']] = $v['markers'] !== mel_cgi_markers_of(false, false);
    $d['markers_exact'] = $v['markers'] === mel_cgi_markers_of(true, true);
    if (!array_key_exists($v['markers'], mel_cgi_markers()))
    {
        $errors[] = mel_cgi_err(mel_cgi_l('Пропуск заставки и титров: нет такого ответа', 'Skip intro and credits: no such answer'),
            $f['markers']['name']);
    }
    // None chosen in the file and the answer the page showed for it posted (saved for another field): none still, the
    // default goes on by the firmware (on after an update to r24). Shown: as the page came (cat_card_was: a page of
    // r22 open over the update shows "off"); a form without it - by the firmware now.
    $was = $v['cat_card_was'] !== '' ? $v['cat_card_was'] : mel_cgi_cat_card_of(null);
    $d[$set['cat_card']] = $v['cur'][$set['cat_card']] === null && $v['cat_card'] === $was ? null :
        $v['cat_card'] === mel_cgi_cat_card_of(true);
    if (!array_key_exists($v['cat_card'], mel_cgi_cat_card()))
        $errors[] = mel_cgi_err(mel_cgi_l('Своя карточка: нет такого ответа', 'Own card: no such answer'), $f['cat_card']['name']);
    $d[$set['cat_rows']] = mel_cat_rows_ok(explode(' ', $v['cat_rows']));
    // The fields of Lampac: of the answer "Lampac" only (v2: a source of its own), the address needed; else hidden -
    // those of the view (of a source of releases: none).
    $a = mel_lp_addr($v['lampac']);
    $tk = mel_lp_token($v['lp_token']);
    $d[$set['lampac']] = $lp && is_string($a) ? $a : $v['cur'][$set['lampac']];
    $d[$set['lp_token']] = $lp && is_string($tk) ? $tk : $v['cur'][$set['lp_token']];
    if (!$lp)
        $d[$set['lp_lampa']] = $v['cur'][$set['lp_lampa']];
    if ($lp && !is_string($a))
        $errors[] = mel_cgi_err(mel_cgi_fmt_lampac(), $f['lampac']['name']);
    elseif ($lp && $a === '')
        $errors[] = mel_cgi_err(mel_cgi_l('Впишите адрес Lampac', 'Enter the Lampac address'), $f['lampac']['name']);
    if ($lp && !is_string($tk))
        $errors[] = mel_cgi_err(mel_cgi_fmt_lp_token(), $f['lp_token']['name']);
    return array($d, $errors);
}

// TorrServer of the form (0.45.0): its address, login and password as
// posted; a login and password in the address ([http://]login:password@host)
// go to their fields, before those -> array(address (mel_ts_addr), login,
// password, null) or array(null, '', '', array(the key of mel_cgi_fields()
// of the error: the address for those in it, its text)).
function mel_cgi_ts_parse($ts, $user, $pass)
{
    list($addr, $u, $p) = mel_ts_userinfo(trim($ts, MEL_TRIM));
    $in = !is_null($u);
    $a = mel_ts_addr($addr);
    $u = mel_ts_login($in ? $u : $user);
    $p = mel_ts_pass($in ? $p : $pass);
    $fu = $in ? 'ts' : 'ts_user';
    $fp = $in ? 'ts' : 'ts_pass';
    if (!is_string($a))
        return array(null, '', '', array('ts', mel_cgi_l('TorrServer: нужен адрес вида http://хост:порт или хост:порт (без https и пути)',
            'TorrServer: an address like http://host:port or host:port is needed (no https, no path)')));
    if (is_null($u))
        return array(null, '', '', array($fu, mel_cgi_l('TorrServer: логин — до 100 символов, без двоеточия и управляющих символов',
            'TorrServer: login up to 100 characters, no colon or control characters')));
    if (is_null($p))
        return array(null, '', '', array($fp, mel_cgi_l('TorrServer: пароль — до 200 символов, без управляющих символов',
            'TorrServer: password up to 200 characters, no control characters')));
    if ($u === '' && $p !== '')
        return array(null, '', '', array($fu, mel_cgi_l('TorrServer: к паролю нужен и логин', 'TorrServer: a password needs a login')));
    if ($u !== '' && $p === '')
        return array(null, '', '', array($fp, mel_cgi_l('TorrServer: к логину нужен и пароль', 'TorrServer: a login needs a password')));
    return array($a, $u, $p, null);
}

// The save resets the config on the server chosen in $d (the values checked)
// in the mode chosen to the template of that mode (0.40.0); $confs: the
// configs (aio_confs), $v: as posted (mel_cgi_posted with reset, ajax, cur).
// By its button or the script (it asks first and posts aio_reset); without
// the script, on the server the page showed (mel_cgi_same_server): another
// template of the mode (aio_tpl, 0.44.0 aio_tpl_p2p) than its tpl (also
// 'none' or 'custom').
function mel_cgi_resets($d, $confs, $v)
{
    $b = mel_settings_base($d);
    if ($b === '' || !isset($confs[$b][$d['aio_mode']]))
        return false;
    if ($v['reset'])
        return true;
    if ($v['ajax'] || !mel_cgi_same_server($d, $v['cur']))
        return false;
    $tk = mel_cgi_tpl_keys($d['aio_mode']);
    $k = $tk['field'];
    return $v[$k] !== '' && $v[$k] !== mel_cgi_tpl_shown($d['aio_mode'], $b, $confs, '');
}

// The server chosen in $d (settings.json) is the stored one of $cur: the
// one a page without the script showed.
function mel_cgi_same_server($d, $cur)
{
    return $d['aio_server'] === $cur['aio_server'] && ($d['aio_server'] !== 'own' ||
        $d['aio_own_url'] === $cur['aio_own_url']);
}
