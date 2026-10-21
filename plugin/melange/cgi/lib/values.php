<?php
// The settings page (cgi/settings.php): the values of the form (from the
// settings: aio_cgi_values; posted: aio_cgi_posted), their format check
// (aio_cgi_check_values) and what the save does with them (aio_cgi_resets,
// aio_cgi_same_server), no request.

// Values of the form from the settings (aio_settings_read) or as posted
// (aio_cgi_posted), by the keys of aio_cgi_fields() - strings (aio_tpl,
// aio_tpl_p2p, aio_mode: the one chosen in a form back, '' from the
// settings; tpl_seen, tpl_p2p_seen only posted; ts_save: aio_cgi_ts_save_of()
// of the settings, as posted - none: '', settings.php puts the stored one);
// tpl_new, tpl_p2p_new -
// aio_tpl, aio_tpl_p2p of the settings (for a new config);
// mode_cur - aio_mode of the settings (one for all servers); servers -
// lists of strings by aio_cgi_server_fields(); confs - aio_confs of the
// settings (never posted).
function aio_cgi_values($s)
{
    $v = array();
    foreach (aio_cgi_fields() as $k => $f)
        if ($f['set'] !== '')
            $v[$k] = $s[$f['set']];
    $v['tpl_new'] = $v['aio_tpl'];
    $v['aio_tpl'] = '';
    $v['tpl_p2p_new'] = $v['aio_tpl_p2p'];
    $v['aio_tpl_p2p'] = '';
    $v['mode_cur'] = $v['aio_mode'];
    $v['aio_mode'] = '';
    $v['ts_save'] = aio_cgi_ts_save_of($s['ts_save']);
    $v['servers'] = $s['servers'];
    $v['confs'] = $s['aio_confs'];
    return $v;
}

// The AIOStreams server of the form: one of aio_aio_servers() or 'own';
// another one (a form of 0.35.1 with "manifest", a forged one) -> the
// first one, as aio_settings_read.
function aio_cgi_server_choice($as)
{
    $list = array_keys(aio_aio_servers());
    return in_array($as, array_merge($list, array('own')), true) ? $as : $list[0];
}

function aio_cgi_posted()
{
    $f = aio_cgi_fields();
    $v = array('servers' => array(), 'confs' => array());
    // The own JacRed as typed: aio_jacred_own trims it.
    foreach ($f as $k => $x)
        $v[$k] = $k === 'jo_url' || $k === 'jo_key' || $k === 'ts_pass' ? aio_cgi_param($_POST, $x['name']) :
            trim(aio_cgi_param($_POST, $x['name']), AIO_TRIM);
    // The answer of 0.43.0 (watch): the source and the mode by it; the own
    // config keeps the stored mode. None (a form of 0.42 or older) or another
    // one (an error, aio_cgi_check_values): source and aio_mode as posted.
    $w = array_search($v['watch'], aio_cgi_watches(), true);
    if ($w !== false)
    {
        $v['source'] = $w === 'wo' ? 'own' : 'made';
        $v['aio_mode'] = $w === 'wo' ? '' : $v['watch'];
    }
    // A form without the source: 0.35.1 chose the own config by aio_server
    // "manifest", 0.35.0 and older (no aio_own_url) by the manifest address
    // alone, as aio_settings_read.
    if ($v['source'] !== 'made' && $v['source'] !== 'own')
        $v['source'] = $v['aio_server'] === 'manifest' || ($v['manifest'] !== '' &&
            !array_key_exists($f['aio_own']['name'], $_POST)) ? 'own' : 'made';
    // Then the server as the save takes it: the page checks that card, the main screen shows it.
    $v['aio_server'] = aio_cgi_server_choice($v['aio_server']);
    for ($n = 1; $n <= AIO_SERVERS_MAX; $n++)
    {
        $s = array();
        foreach (array_keys(aio_cgi_server_fields()) as $k)
        {
            $x = aio_cgi_srv_field($n, $k);
            $s[$k] = aio_cgi_param($_POST, $x['name']);
        }
        $v['servers'][] = $s;
    }
    return $v;
}

// An error of the save: its text and the name= of the form field it is
// about (null: none; of a pair, the first one).
function aio_cgi_err($msg, $field = null)
{
    return array('msg' => $msg, 'field' => $field);
}

// Values of the form -> array(settings.json v 2 without saved_at, errors for
// the page (aio_cgi_err), each with its field). Format only, no request.
function aio_cgi_check_values($v)
{
    $f = aio_cgi_fields();
    $errors = array();
    // Only the fields of the chosen source are checked; a wrong one of the
    // other (hidden) is not written: the stored value stays ($v['cur']).
    $made = $v['source'] === 'made';
    // No field (a form of 0.42 or older), or a wrong one of the own config: the stored one.
    $mode = in_array($v['aio_mode'], aio_aio_modes(), true) ? $v['aio_mode'] : $v['cur']['aio_mode'];
    if ($v['watch'] !== '' && !in_array($v['watch'], aio_cgi_watches(), true))
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Откуда брать фильмы: нет такого ответа', 'Where to get films: no such answer'),
            $f['watch']['name']);
    }
    elseif ($made && $v['aio_mode'] !== '' && $mode !== $v['aio_mode'])
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Режим: нет такого режима', 'Mode: no such mode'), $f['watch']['name']);
    }
    // The keys of settings.json: only by aio_cgi_fields().
    $set = array();
    foreach ($f as $k => $x)
        $set[$k] = $x['set'];
    $d = array('v' => 2, $set['source'] => $v['source'], $set['manifest'] => aio_manifest_url($v['manifest']));
    if ($v['manifest'] !== '' && $d[$set['manifest']] === '')
    {
        if ($made)
            $d[$set['manifest']] = $v['cur'][$set['manifest']];
        else
        {
            $errors[] = aio_cgi_err(aio_cgi_fmt_manifest(), $f['manifest']['name']);
        }
    }
    // The Debrid keys: checked below by the mode of the config after the
    // save; their errors go here, in the order of the page.
    $keys_at = count($errors);
    $d[$set['rd']] = '';
    $d[$set['tb']] = '';
    // Not one of the list (a form of 0.33): the default; the own one only with its address.
    $d[$set['jacred']] = $v['jacred'] === 'own' || array_key_exists($v['jacred'], aio_jacred_builtin()) ? $v['jacred'] :
        AIO_JACRED_DEFAULT;
    $own = aio_jacred_own(array('url' => $v['jo_url'], 'key' => $v['jo_key']));
    $d[$set['jo_url']] = $own ? $own['url'] : '';
    $d[$set['jo_key']] = $own ? $own['key'] : '';
    if (!$own && (trim($v['jo_url'] . $v['jo_key'], AIO_TRIM) !== '' || $d[$set['jacred']] === 'own'))
    {
        // The address alone right: the key is wrong.
        $errors[] = aio_cgi_err(aio_cgi_l('Свой JacRed', 'Own JacRed') . ': ' . aio_cgi_fmt_jacred(),
            $f[aio_jacred_own(array('url' => $v['jo_url'], 'key' => '')) ? 'jo_key' : 'jo_url']['name']);
    }
    $d[$set['aio_server']] = aio_cgi_server_choice($v['aio_server']);
    // The fields of both sources are kept; those of the chosen one must be there.
    if (!$made && $v['manifest'] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Укажите ссылку на конфиг — или выберите Debrid или «Торренты»',
            'Enter the config link, or choose Debrid or "Torrents"'), $f['manifest']['name']);
    }
    $d[$set['aio_own']] = aio_aio_own($v['aio_own']);
    // Hidden unless another server is chosen to make the config on.
    if ($v['aio_own'] !== '' && $d[$set['aio_own']] === '' && !($made && $d[$set['aio_server']] === 'own'))
        $d[$set['aio_own']] = $v['cur'][$set['aio_own']];
    elseif ($made && $d[$set['aio_server']] === 'own' && $d[$set['aio_own']] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Свой сервер AIOStreams: нужен адрес вида http(s)://хост[:порт] без пути',
            'Own AIOStreams server: an address like http(s)://host[:port] without a path is needed'), $f['aio_own']['name']);
    }
    $d[$set['aio_mode']] = $mode;
    // No field (a form of 0.39 or older), or a wrong one of the own config: the stored one.
    $d[$set['aio_tpl']] = aio_tpl_debrid($v['aio_tpl']) ? $v['aio_tpl'] : $v['cur'][$set['aio_tpl']];
    // That of P2P (0.44.0): only one chosen, another than the page showed
    // (tpl_p2p_seen); as shown, none (a form of 0.43 or older) or a wrong
    // one: the stored one. Else a save as shown (a config of P2P of 0.43:
    // p2p; any save of Debrid) would drop the default p2p_jacred for a new
    // config. Debrid as before (0.40.0): the one posted, shown or chosen
    // (its default addons is that of the configs of before).
    $ok = array('aio_tpl' => aio_tpl_debrid($v['aio_tpl']), 'aio_tpl_p2p' => aio_tpl_p2p($v['aio_tpl_p2p']));
    $d[$set['aio_tpl_p2p']] = $ok['aio_tpl_p2p'] && $v['aio_tpl_p2p'] !== $v['tpl_p2p_seen'] ? $v['aio_tpl_p2p'] :
        $v['cur'][$set['aio_tpl_p2p']];
    foreach ($ok as $k => $good)
    {
        if ($made && $v[$k] !== '' && !$good)
        {
            $errors[] = aio_cgi_err(aio_cgi_l('Шаблон конфига: нет такого шаблона', 'Config template: no such template'),
                $f[$k]['name']);
        }
    }
    // The page showed another one of the mode for the chosen server (none: a
    // form of 0.39 or older, of P2P 0.43 or older): another tab changed it
    // since. A page without the script showed the stored server only; the
    // script, for an own address other than the stored one, the one for a
    // new config (its conf()). Only the mode chosen: the other one hides its
    // template, no reset by it there.
    $tk = aio_cgi_tpl_keys($mode);
    $k = $tk['field'];
    $seen = $tk['seen'];
    $new = $v['cur'][$set[$k]];
    $shown = $d[$set['aio_server']] === 'own' && $d[$set['aio_own']] !== $v['cur'][$set['aio_own']] ? $new :
        aio_cgi_tpl_shown($mode, $d[$set['aio_server']], $d[$set['aio_own']], $v['confs'], $new);
    if ($v[$seen] !== '' && ($v['ajax'] || aio_cgi_same_server($d, $v['cur'])) && $v[$seen] !== $shown)
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Шаблон изменён в другой вкладке — обновите страницу',
            'The template was changed in another tab: reload the page'), $f[$k]['name']);
    }
    // The mode Debrid and no key to make the config there (none of Debrid there yet).
    $base = $d[$set['aio_server']] === 'own' ? $d[$set['aio_own']] : $d[$set['aio_server']];
    // The Debrid keys: only of the mode Debrid (else not sent; a wrong one: the stored one kept).
    $keys = array();
    foreach (array('rd', 'tb') as $what)
    {
        $k = $set[$what];
        $d[$k] = aio_key_ok($what, $v[$what]);
        if ($v[$what] !== '' && $d[$k] === '')
        {
            if (!$made || $mode !== AIO_MODE_DEBRID)
                $d[$k] = $v['cur'][$k];
            else
            {
                $keys[] = aio_cgi_err(aio_cgi_fmt_key($what), $f[$what]['name']);
            }
        }
    }
    array_splice($errors, $keys_at, 0, $keys);
    if ($made && $mode === AIO_MODE_DEBRID && $base !== '' && !isset($v['confs'][$base][AIO_MODE_DEBRID]) &&
        $d[$set['rd']] === '' && $d[$set['tb']] === '' && $v['rd'] . $v['tb'] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Для Debrid нужен ключ Real-Debrid или TorBox; без ключа выберите «Торренты»',
            'Debrid needs a Real-Debrid or TorBox key; without one, choose "Torrents"'), $f['rd']['name']);
    }
    // P2P: the own JacRed goes to Magnetio in one path segment "k=v|k=v".
    if ($made && $mode === AIO_MODE_P2P && $d[$set['jacred']] === 'own' && strpos($d[$set['jo_key']], '|') !== false)
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Свой JacRed: в режиме «Торренты» ключ — без символа |',
            'Own JacRed: with "Torrents", the key must not contain |'), $f['jo_key']['name']);
    }
    $d[$set['tmdb']] = aio_key_ok('tmdb', $v['tmdb']);
    // Hidden: the own config, or a server with TMDB of its own.
    if ($v['tmdb'] !== '' && $d[$set['tmdb']] === '' && (!$made || aio_tmdb_sent($base, 'k') === ''))
        $d[$set['tmdb']] = $v['cur'][$set['tmdb']];
    elseif ($v['tmdb'] !== '' && $d[$set['tmdb']] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_fmt_key('tmdb'), $f['tmdb']['name']);
    }
    // An empty block is none.
    $d['servers'] = array();
    foreach ($v['servers'] as $i => $s)
    {
        if (trim($s['name'] . $s['url'] . $s['user'] . $s['category'] . $s['tags'], AIO_TRIM) . $s['pass'] === '')
            continue;
        $c = aio_server_conf($s);
        if ($c)
            $d['servers'][] = $c;
        else
        {
            $x = aio_cgi_srv_field($i + 1, 'url');
            $errors[] = aio_cgi_err(aio_cgi_l('Сервер', 'Server') . ' ' . ($i + 1) . ': ' . aio_cgi_l('нужен адрес вида ' .
                'http://хост:порт; название — до 40 символов, логин, категория и теги — до 100, пароль — до 200, ' .
                'без управляющих символов', 'an address like http://host:port is needed; name up to 40 characters, ' .
                'login, category and tags up to 100, password up to 200, no control characters'), $x['name']);
        }
    }
    // One by one: list() of PHP 5 assigns from the right, the keys would go in reverse.
    $ts = aio_cgi_ts_parse($v['ts'], $v['ts_user'], $v['ts_pass']);
    $d[$set['ts']] = $ts[0];
    $d[$set['ts_user']] = $ts[1];
    $d[$set['ts_pass']] = $ts[2];
    $bad = $ts[3];
    if ($bad)
        $errors[] = aio_cgi_err($bad[1], $f[$bad[0]]['name']);
    // None posted: the stored one already (settings.php).
    $d[$set['ts_save']] = $v['ts_save'] === aio_cgi_ts_save_of(true);
    if (!array_key_exists($v['ts_save'], aio_cgi_ts_saves()))
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Что делать с раздачей: нет такого ответа', 'What to do with a release: no such answer'),
            $f['ts_save']['name']);
    }
    return array($d, $errors);
}

// TorrServer of the form (0.45.0): its address, login and password as
// posted; a login and password in the address ([http://]login:password@host)
// go to their fields, before those -> array(address (aio_ts_addr), login,
// password, null) or array(null, '', '', array(the key of aio_cgi_fields()
// of the error: the address for those in it, its text)).
function aio_cgi_ts_parse($ts, $user, $pass)
{
    list($addr, $u, $p) = aio_ts_userinfo(trim($ts, AIO_TRIM));
    $in = !is_null($u);
    $a = aio_ts_addr($addr);
    $u = aio_ts_login($in ? $u : $user);
    $p = aio_ts_pass($in ? $p : $pass);
    $fu = $in ? 'ts' : 'ts_user';
    $fp = $in ? 'ts' : 'ts_pass';
    if (!is_string($a))
        return array(null, '', '', array('ts', aio_cgi_l('TorrServer: нужен адрес вида http://хост:порт или хост:порт (без https и пути)',
            'TorrServer: an address like http://host:port or host:port is needed (no https, no path)')));
    if (is_null($u))
        return array(null, '', '', array($fu, aio_cgi_l('TorrServer: логин — до 100 символов, без двоеточия и управляющих символов',
            'TorrServer: login up to 100 characters, no colon or control characters')));
    if (is_null($p))
        return array(null, '', '', array($fp, aio_cgi_l('TorrServer: пароль — до 200 символов, без управляющих символов',
            'TorrServer: password up to 200 characters, no control characters')));
    if ($u === '' && $p !== '')
        return array(null, '', '', array($fu, aio_cgi_l('TorrServer: к паролю нужен и логин', 'TorrServer: a password needs a login')));
    if ($u !== '' && $p === '')
        return array(null, '', '', array($fp, aio_cgi_l('TorrServer: к логину нужен и пароль', 'TorrServer: a login needs a password')));
    return array($a, $u, $p, null);
}

// The save resets the config on the server chosen in $d (the values checked)
// in the mode chosen to the template of that mode (0.40.0); $confs: the
// configs (aio_confs), $v: as posted (aio_cgi_posted with reset, ajax, cur).
// By its button or the script (it asks first and posts aio_reset); without
// the script, on the server the page showed (aio_cgi_same_server): another
// template of the mode (aio_tpl, 0.44.0 aio_tpl_p2p) than its tpl (also
// 'none' or 'custom').
function aio_cgi_resets($d, $confs, $v)
{
    $b = aio_settings_base($d);
    if ($b === '' || !isset($confs[$b][$d['aio_mode']]))
        return false;
    if ($v['reset'])
        return true;
    if ($v['ajax'] || !aio_cgi_same_server($d, $v['cur']))
        return false;
    $tk = aio_cgi_tpl_keys($d['aio_mode']);
    $k = $tk['field'];
    return $v[$k] !== '' && $v[$k] !== aio_cgi_tpl_shown($d['aio_mode'], $d['aio_server'], $d['aio_own_url'], $confs, '');
}

// The server chosen in $d (settings.json) is the stored one of $cur: the
// one a page without the script showed.
function aio_cgi_same_server($d, $cur)
{
    return $d['aio_server'] === $cur['aio_server'] && ($d['aio_server'] !== 'own' ||
        $d['aio_own_url'] === $cur['aio_own_url']);
}
