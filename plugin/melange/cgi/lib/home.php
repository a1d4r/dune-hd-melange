<?php
// The settings page (cgi/settings.php): the main screen (#home) - its state
// and the lines of the screens by the values, the same rules as web/src/home.ts
// - and the frame of a screen.

// The texts of the values on the main screen (aio_cgi_rows), also the i18n
// of the script (aio_cgi_js: web/src/home.ts takes them by these keys).
function aio_cgi_home_texts()
{
    return array('watchOwn' => aio_cgi_l('Свой конфиг AIOStreams', 'Own AIOStreams config'),
        'needKey' => aio_cgi_l('нужен ключ', 'a key is needed'), 'needLink' => aio_cgi_l('нужна ссылка', 'a link is needed'),
        'viaTs' => aio_cgi_l('через TorrServer', 'via TorrServer'), 'tsDune' => aio_cgi_l('Приложение на Дюне', 'The app on the Dune'),
        'tsSpare' => aio_cgi_l('запасной путь', 'fallback'), 'noServers' => aio_cgi_l('Не настроено', 'Not set up'),
        'ownJacred' => aio_cgi_l('Свой JacRed', 'Own JacRed'));
}

// The three cards of the answers of "Откуда брать фильмы" (the radio watch):
// on #src and on the main screen of the first run.
function aio_cgi_watch_cards()
{
    $t = aio_cgi_home_texts();
    return '<div class="choice">' . aio_cgi_card('wd', 'Debrid', aio_cgi_l('Real-Debrid или TorBox. Быстро, платная подписка.',
        'Real-Debrid or TorBox. Fast, a paid subscription.')) . aio_cgi_card('wp', aio_cgi_mode_p2p(),
        aio_cgi_l('Бесплатно, через TorrServer на Дюне.', 'Free, via TorrServer on the Dune.')) .
        aio_cgi_card('wo', $t['watchOwn'], aio_cgi_l('Если он у вас уже настроен.', 'If you already have one set up.')) . '</div>';
}

// The screens of the page (0.43.0): id => title; the main one is #home.
function aio_cgi_screens()
{
    return array('src' => aio_cgi_l('Откуда брать фильмы', 'Where to get films'),
        'voices' => aio_cgi_l('Озвучки и дорожки', 'Voice-overs and tracks'), 'torr' => 'TorrServer',
        'dl' => aio_cgi_l('Скачивать на свой сервер', 'Download to your server'),
        'cfg' => aio_cgi_l('Конфиг в AIOStreams', 'Config in AIOStreams'), 'log' => aio_cgi_l('Лог', 'Log'));
}

// The screen of the field name= $field of an error (aio_cgi_err): its scr
// (aio_cgi_fields), the servers #dl; '' - none (no field, an unknown one).
function aio_cgi_err_screen($field)
{
    if ($field === null)
        return '';
    if (preg_match('/^s[0-9]+_/', $field))
        return 'dl';
    foreach (aio_cgi_fields() as $f)
        if ($f['name'] === $field)
            return $f['scr'];
    return '';
}

// The state of the main screen by the values (no request): key - Debrid
// without a key, conf - Debrid or Торренты without a config on the chosen
// server in that mode, man - the own config without its link, ok.
function aio_cgi_state($v)
{
    $w = aio_cgi_watch($v);
    if ($w === 'own')
        return $v['manifest'] === '' ? 'man' : 'ok';
    if ($w === AIO_MODE_DEBRID && $v['rd'] . $v['tb'] === '')
        return 'key';
    return aio_cgi_aio_sel($v) ? 'ok' : 'conf';
}

// The lines of the main screen: id of the screen => array(dot: ok, warn or
// '' - grey, the value now).
function aio_cgi_rows($v)
{
    $t = aio_cgi_home_texts();
    $w = aio_cgi_watch($v);
    $st = aio_cgi_state($v);
    $dot = $st === 'ok' ? 'ok' : 'warn';
    if ($w === 'own')
        $src = $t['watchOwn'] . ($v['manifest'] === '' ? ' · ' . $t['needLink'] : '');
    elseif ($w === AIO_TPL_P2P)
        $src = aio_cgi_mode_p2p() . ' · ' . $t['viaTs'];
    else
    {
        $keys = array();
        if ($v['rd'] !== '')
            $keys[] = 'Real-Debrid';
        if ($v['tb'] !== '')
            $keys[] = 'TorBox';
        $src = 'Debrid · ' . ($keys ? implode(' + ', $keys) : $t['needKey']);
    }
    // The template of the mode, by its short name (0.44.0: P2P too).
    if ($w !== 'own')
    {
        list(, , , $tpls) = aio_cgi_tpl_keys($w);
        $all = $tpls + aio_cgi_tpl_marks();
        $tpl = aio_cgi_tpl_sel($v, $w);
        $src .= isset($all[$tpl]) ? ' · ' . $all[$tpl][2] : '';
    }
    $jr = $v['jacred'] !== 'own' ? $v['jacred'] : aio_cgi_host($v['jo_url']);
    $names = array();
    foreach ($v['servers'] as $x)
    {
        $n = isset($x['name']) && is_string($x['name']) ? trim($x['name'], AIO_TRIM) : '';
        $u = isset($x['url']) && is_string($x['url']) ? trim($x['url'], AIO_TRIM) : '';
        if ($n . $u !== '')
            $names[] = $n !== '' ? $n : $u;
    }
    return array('src' => array($dot, $src),
        'voices' => array($jr === '' ? 'warn' : 'ok', $jr !== '' ? $jr : $t['ownJacred']),
        'torr' => array($w === AIO_TPL_P2P ? 'ok' : '', ($v['ts'] !== '' ? $v['ts'] : $t['tsDune']) .
            ($w === AIO_MODE_DEBRID ? ' · ' . $t['tsSpare'] : '')),
        'dl' => array($names ? 'ok' : '', $names ? implode(', ', $names) : $t['noServers']),
        'cfg' => array('', aio_cgi_l('Для опытных: вход, сброс', 'For the experienced: login, reset')),
        'log' => array('', aio_cgi_l('С последнего включения Дюны', 'Since the Dune was switched on')));
}

// The main screen (#home): the first run (no saved_at: $v['first']) - the
// question of #src by its cards and "Начать", the state hidden (the script
// shows it instead after the first save); else the state (#state, its
// data-st: aio_cgi_state, one <p data-st> shown); then the lines of the
// screens (#r<id>: the dot #d<id>, the value #v<id>; the line of #cfg -
// class xm: not for the own config).
function aio_cgi_home($v)
{
    $scr = aio_cgi_screens();
    $first = isset($v['first']) && $v['first'];
    $out = '<h1>Melange</h1><p class="sub">' . aio_cgi_l('Настройки на Дюне', 'Settings on the Dune') . '</p>';
    if ($first)
        $out .= '<div id="first"><h2>' . $scr['src'] . '</h2><p class="sub">' . aio_cgi_l('Можно поменять потом',
            'You can change it later') . '</p>' . aio_cgi_watch_cards() .
            '<a id="start" class="btn" href="#src">' . aio_cgi_l('Начать', 'Start') . '</a></div>';
    $st = aio_cgi_state($v);
    $txt = array('key' => aio_cgi_l('<b>Нужен ключ Debrid.</b> Введите его в «Откуда брать фильмы».',
        '<b>A Debrid key is needed.</b> Enter it in "Where to get films".'),
        'conf' => sprintf(aio_cgi_l('<b>Конфиг ещё не создан</b> — нажмите «%s».', '<b>No config yet</b>: press "%s".'),
        aio_cgi_saveb()),
        'man' => aio_cgi_l('<b>Нужна ссылка на свой конфиг.</b> Вставьте её в «Откуда брать фильмы».',
        '<b>Your config link is needed.</b> Paste it in "Where to get films".'),
        'ok' => aio_cgi_l('<b>Всё готово к просмотру.</b> На Дюне: фильм → Смотреть… → Melange.',
        '<b>All set to watch.</b> On the Dune: a film → Play... → Melange.'));
    $out .= '<div id="state" class="state ' . ($st === 'ok' ? 'ok' : 'warn') . '" data-st="' . $st . '"' .
        ($first ? ' hidden' : '') . '><span class="dot"></span><div>';
    foreach ($txt as $k => $x)
        $out .= '<p data-st="' . $k . '"' . ($k === $st ? '' : ' hidden') . '>' . $x . '</p>';
    $out .= '</div></div>';
    $rows = aio_cgi_rows($v);
    $groups = array(aio_cgi_l('Просмотр', 'Watching') => array('src', 'voices', 'torr'),
        aio_cgi_l('Ещё', 'More') => array('dl', 'cfg', 'log'));
    foreach ($groups as $h => $ids)
    {
        $out .= '<h3 class="gh">' . $h . '</h3><div class="list">';
        foreach ($ids as $id)
            $out .= '<a class="row' . ($id === 'cfg' ? ' xm' : '') . '" id="r' . $id . '" href="#' . $id . '"><span class="dot' .
                ($rows[$id][0] !== '' ? ' ' . $rows[$id][0] : '') . '" id="d' . $id . '"></span><span class="rt"><span class="t">' .
                $scr[$id] . '</span><span class="v" id="v' . $id . '">' . aio_cgi_h($rows[$id][1]) .
                '</span></span><span class="chev">›</span></a>';
        $out .= '</div>';
    }
    return $out;
}

// A screen of the page: shown by :target (the CSS), back to #home.
function aio_cgi_screen($id, $body)
{
    $scr = aio_cgi_screens();
    return '<section id="' . $id . '" class="scr"><a class="back" href="#home">' . aio_cgi_l('‹ Настройки', '‹ Settings') .
        '</a><h2>' . $scr[$id] . '</h2>' . $body . '</section>';
}
