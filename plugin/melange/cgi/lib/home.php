<?php
// The settings page (cgi/settings.php): the main screen (#home) - its state
// and the lines of the screens by the values, the same rules as web/src/home.ts
// - and the frame of a screen.

// The texts of the values on the main screen (mel_cgi_rows), also the i18n
// of the script (mel_cgi_js: web/src/home.ts takes them by these keys).
function mel_cgi_home_texts()
{
    return array('watchOwn' => mel_cgi_l('Свой конфиг AIOStreams', 'Own AIOStreams config'),
        'needKey' => mel_cgi_l('нужен ключ', 'a key is needed'), 'needLink' => mel_cgi_l('нужна ссылка', 'a link is needed'),
        'needAddress' => mel_cgi_l('нужен адрес', 'an address is needed'),
        'viaTs' => mel_cgi_l('через TorrServer', 'via TorrServer'), 'tsDune' => mel_cgi_l('Приложение на Дюне', 'The app on the Dune'),
        'tsSpare' => mel_cgi_l('по выбору', 'by choice'), 'noServers' => mel_cgi_l('Не настроено', 'Not set up'),
        'ownJacred' => mel_cgi_l('Свой JacRed', 'Own JacRed'), 'ownAioServer' => mel_cgi_l('свой сервер', 'own server'),
        'logoOn' => mel_cgi_l('Логотип вместо названия', 'Logo in place of the title'),
        'logoOff' => mel_cgi_l('Название текстом', 'Title as text'),
        'ratingOn' => mel_cgi_l('рейтинг IMDb', 'IMDb rating'), 'ratingOff' => mel_cgi_l('без рейтинга', 'no rating'));
}

// The four cards of the answers of "Откуда брать фильмы" (the radio watch; Lampac - v2):
// on #src and on the main screen of the first run.
function mel_cgi_watch_cards()
{
    $t = mel_cgi_home_texts();
    return '<div class="choice">' . mel_cgi_card('wd', 'Debrid', mel_cgi_l('Real-Debrid или TorBox. Быстро, платная подписка.',
        'Real-Debrid or TorBox. Fast, a paid subscription.')) . mel_cgi_card('wp', mel_cgi_mode_p2p(),
        mel_cgi_l('Бесплатно, через TorrServer на Дюне.', 'Free, via TorrServer on the Dune.')) .
        mel_cgi_card('wo', $t['watchOwn'], mel_cgi_l('Если он у вас уже настроен.', 'If you already have one set up.')) .
        mel_cgi_card('wl', 'Lampac', mel_cgi_l('Если у вас свой сервер Lampac.', 'If you have your own Lampac server.')) . '</div>';
}

// The screens of the page (0.43.0): id => title; the main one is #home.
function mel_cgi_screens()
{
    return array('src' => mel_cgi_l('Откуда брать фильмы', 'Where to get films'),
        'voices' => 'JacRed', 'torr' => 'TorrServer',
        'dl' => mel_cgi_l('Скачивать на свой сервер', 'Download to your server'),
        'adv' => mel_cgi_l('Дополнительно', 'Advanced'),
        'rows' => mel_cgi_l('Ряды каталога', 'Catalog rows'),
        'look' => mel_cgi_l('Внешний вид', 'Appearance'),
        'hide' => mel_cgi_l('Фильтр каталога', 'Catalog filter'),
        'cfg' => 'AIOStreams', 'bak' => mel_cgi_l('Резервная копия', 'Backup'), 'log' => mel_cgi_l('Лог', 'Log'),
        'about' => mel_cgi_l('О Melange', 'About Melange'));
}

// The screen of the field name= $field of an error (mel_cgi_err): its scr
// (mel_cgi_fields), the servers #dl; '' - none (no field, an unknown one).
function mel_cgi_err_screen($field)
{
    if ($field === null)
        return '';
    if (preg_match('/^s[0-9]+_/', $field))
        return 'dl';
    foreach (mel_cgi_fields() as $f)
        if ($f['name'] === $field)
            return $f['scr'];
    return '';
}

// The state of the main screen by the values (no request): key - Debrid
// without a key, conf - Debrid or Торренты without a config on the chosen
// server in that mode, man - the own config without its link, lp - Lampac without its address (v2), ok.
function mel_cgi_state($v)
{
    $w = mel_cgi_watch($v);
    if ($w === 'lampac')
        return $v['lampac'] === '' ? 'lp' : 'ok';
    if ($w === 'own')
        return $v['manifest'] === '' ? 'man' : 'ok';
    if ($w === MEL_MODE_DEBRID && $v['rd'] . $v['tb'] === '')
        return 'key';
    return mel_cgi_aio_sel($v) ? 'ok' : 'conf';
}

// The lines of the main screen: id of the screen => array(dot: ok, warn,
// '' - grey or none - no status, the value now). The source: of the source the page edits; two sources or more (v2) -
// the name of the main one "и ещё N" (mel_cgi_src_more). The server (#cfg, 0.44.0):
// its host (own one without a host: ownAioServer), ok - a config of the mode
// there. #adv: its value only with "Без торрентов" in effect, by the CSS of
// the radios.
function mel_cgi_rows($v)
{
    $t = mel_cgi_home_texts();
    $w = mel_cgi_watch($v);
    $st = mel_cgi_state($v);
    $dot = $st === 'ok' ? 'ok' : 'warn';
    if ($w === 'lampac')
        $src = 'Lampac · ' . ($v['lampac'] !== '' ? mel_cgi_host($v['lampac']) : $t['needAddress']);
    elseif ($w === 'own')
        $src = $t['watchOwn'] . ($v['manifest'] === '' ? ' · ' . $t['needLink'] : '');
    elseif ($w === MEL_MODE_P2P)
        $src = mel_cgi_mode_p2p() . ' · ' . $t['viaTs'];
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
    if ($w !== 'own' && $w !== 'lampac')
    {
        $tk = mel_cgi_tpl_keys($w);
        $all = $tk['tpls'] + mel_cgi_tpl_marks();
        $tpl = mel_cgi_tpl_sel($v, $w);
        $src .= isset($all[$tpl]) ? ' · ' . $all[$tpl]['short'] : '';
    }
    $jr = $v['jacred'] !== 'own' ? $v['jacred'] : mel_cgi_host($v['jo_url']);
    $names = array();
    foreach ($v['servers'] as $x)
    {
        $n = isset($x['name']) && is_string($x['name']) ? trim($x['name'], MEL_TRIM) : '';
        $u = isset($x['url']) && is_string($x['url']) ? trim($x['url'], MEL_TRIM) : '';
        if ($n . $u !== '')
            $names[] = $n !== '' ? $n : $u;
    }
    // Not mel_cgi_aio_base: a form back shows the host of the own address as typed, a wrong one too.
    $host = mel_cgi_host($v['aio_server'] === 'own' ? $v['aio_own'] : $v['aio_server']);
    // A form back: never the login and password typed in the address.
    list($ts) = mel_ts_userinfo($v['ts']);
    $more = mel_cgi_src_more($v);
    return array('src' => array($dot, $more !== '' ? $more : $src),
        'cfg' => array($w === 'own' || $w === 'lampac' ? '' : (mel_cgi_aio_sel($v) ? 'ok' : 'warn'),
            $host !== '' ? $host : $t['ownAioServer']),
        'voices' => array($jr === '' ? 'warn' : 'ok', $jr !== '' ? $jr : $t['ownJacred']),
        'torr' => array($w === MEL_MODE_P2P ? 'ok' : '', ($ts !== '' ? $ts : $t['tsDune']) .
            ($w === MEL_MODE_DEBRID ? ' · ' . $t['tsSpare'] : '')),
        'dl' => array($names ? 'ok' : '', $names ? implode(', ', $names) : $t['noServers']),
        'adv' => array('none', ''),
        'rows' => array('none', mel_cgi_l('Какие показывать и в каком порядке', 'Which to show and in what order')),
        // A form back with a wrong value: as on.
        'look' => array('none', ($v['cat_logo'] === mel_cgi_toggle_of('cat_logo', false) ? $t['logoOff'] : $t['logoOn']) . ' · ' .
            ($v['cat_rating'] === mel_cgi_toggle_of('cat_rating', false) ? $t['ratingOff'] : $t['ratingOn'])),
        // The age and the IMDb ratings (lib/hidden.php): their short names; a form back with a wrong value - all, any.
        'hide' => array('none', mel_cgi_hide_line($v['cat_age'], $v['cat_imdb_movie'], $v['cat_imdb_series'])),
        'bak' => array('none', mel_cgi_l('Настройки в файл и из файла', 'Settings to a file and back')),
        'log' => array('none', mel_cgi_l('С последнего включения Дюны', 'Since the Dune was switched on')),
        'about' => array('none', MEL_VERSION . ' · ' . mel_cgi_l('поддержать автора', 'support the author')));
}

// The value of the line of "Фильтр каталога" of the answers of the age $x (mel_cgi_cat_ages), of the IMDb ratings
// $movie, $series (mel_cgi_cat_imdbs; another one: as all, as any), as web/src/home.ts: "До 12+, IMDb: фильмы от 6.5,
// сериалы от 7", each part only when set; none set - that of all ages.
function mel_cgi_hide_line($x, $movie, $series)
{
    $a = mel_cgi_cat_ages();
    $out = $x !== 'all' && isset($a[$x]) ? array($a[$x]['short']) : array();
    $imdb = array();
    foreach (array(array(mel_cgi_cat_imdbs(false), $movie), array(mel_cgi_cat_imdbs(true), $series)) as $p)
    {
        if (isset($p[0][$p[1]]) && $p[0][$p[1]]['short'] !== '')
            $imdb[] = $p[0][$p[1]]['short'];
    }
    if ($imdb)
        $out[] = 'IMDb: ' . implode(', ', $imdb);
    return $out ? implode(', ', $out) : $a['all']['short'];
}

// The value of the line of the source on the main screen with two sources or more (v2, prof of the values $v:
// mel_cgi_profiles_data): "<the main one> и ещё N" (the script keeps it: data-fixed); '' - one or none.
function mel_cgi_src_more($v)
{
    $list = isset($v['prof']['list']) ? $v['prof']['list'] : array();
    foreach (count($list) >= 2 ? $list : array() as $p)
    {
        if ($p['main'])
            return sprintf(mel_cgi_l('%s и ещё %d', '%s and %d more'), $p['name'], count($list) - 1);
    }
    return '';
}

// The main screen (#home): the first run (no saved_at: $v['first']) - the
// question of #src by its cards and "Начать", the state hidden (the script
// shows it instead after the first save); else the state (#state, its
// data-st: mel_cgi_state, one <p data-st> shown); then the lines of the
// screens (#r<id>: the dot #d<id>, the value #v<id>; the line of #cfg, the
// server, right under that of #src - class xm: not for the own config).
function mel_cgi_home($v)
{
    $scr = mel_cgi_screens();
    $first = isset($v['first']) && $v['first'];
    $out = '<h1>Melange</h1><p class="sub">' . mel_cgi_l('Настройки на Дюне', 'Settings on the Dune') . '</p>';
    if ($first)
        $out .= '<div id="first"><h2>' . $scr['src'] . '</h2><p class="sub">' . mel_cgi_l('Можно поменять потом',
            'You can change it later') . '</p>' . mel_cgi_watch_cards() .
            '<a id="start" class="btn" href="#src">' . mel_cgi_l('Начать', 'Start') . '</a></div>';
    $st = mel_cgi_state($v);
    $txt = array('key' => mel_cgi_l('<b>Нужен ключ Debrid.</b> Введите его в «Откуда брать фильмы».',
        '<b>A Debrid key is needed.</b> Enter it in "Where to get films".'),
        'conf' => sprintf(mel_cgi_l('<b>Конфиг ещё не создан</b> — нажмите «%s».', '<b>No config yet</b>: press "%s".'),
        mel_cgi_saveb()),
        'man' => mel_cgi_l('<b>Нужна ссылка на свой конфиг.</b> Вставьте её в «Откуда брать фильмы».',
        '<b>Your config link is needed.</b> Paste it in "Where to get films".'),
        'lp' => mel_cgi_l('<b>Нужен адрес Lampac.</b> Впишите его в «Откуда брать фильмы».',
        '<b>The Lampac address is needed.</b> Enter it in "Where to get films".'),
        'ok' => mel_cgi_l('<b>Всё готово к просмотру.</b> На Дюне: фильм → Смотреть… → Melange.',
        '<b>All set to watch.</b> On the Dune: a film → Play... → Melange.'));
    $out .= '<div id="state" class="state ' . ($st === 'ok' ? 'ok' : 'warn') . '" data-st="' . $st . '"' .
        ($first ? ' hidden' : '') . '><span class="dot"></span><div>';
    foreach ($txt as $k => $x)
        $out .= '<p data-st="' . $k . '"' . ($k === $st ? '' : ' hidden') . '>' . $x . '</p>';
    $out .= '</div></div>';
    $rows = mel_cgi_rows($v);
    $groups = array(mel_cgi_l('Просмотр', 'Watching') => array('src', 'cfg', 'voices', 'torr'),
        mel_cgi_l('Каталог', 'Catalog') => array('rows', 'look', 'hide'),
        mel_cgi_l('Ещё', 'More') => array('dl', 'adv', 'bak', 'log', 'about'));
    foreach ($groups as $h => $ids)
    {
        $out .= '<h3 class="gh">' . $h . '</h3><div class="list">';
        foreach ($ids as $id)
        {
            $val = mel_cgi_h($rows[$id][1]);
            if ($id === 'adv')
                $val = '<span class="xn xt">' . mel_cgi_l('Торренты выключены', 'Torrents are off') . '</span>';
            $cls = array('cfg' => ' xm', 'voices' => ' xr');
            $out .= '<a class="row' . (isset($cls[$id]) ? $cls[$id] : '') . '" id="r' . $id . '" href="#' . $id . '"><span class="dot' .
                ($rows[$id][0] !== '' ? ' ' . $rows[$id][0] : '') . '" id="d' . $id . '"></span><span class="rt"><span class="t">' .
                $scr[$id] . '</span><span class="v" id="v' . $id . '"' . ($id === 'src' && mel_cgi_src_more($v) !== '' ?
                ' data-fixed="1"' : '') . '>' . $val .
                '</span></span><span class="chev">›</span></a>';
        }
        $out .= '</div>';
    }
    return $out;
}

// The date of the build of the plugin: build.sh writes build_date.txt (YYYY-MM-DD) next to
// dune_plugin.xml, a checkout has none; as the page shows it, '' for none or another text.
function mel_cgi_build_date()
{
    $f = dirname(dirname(dirname(__FILE__))) . '/build_date.txt';
    $d = is_file($f) ? trim((string) @file_get_contents($f)) : '';
    return preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $d, $m) ? mel_cgi_l("$m[3].$m[2].$m[1]", $d) : '';
}

// A line of the blocks of mel_cgi_about: $val on the right; with $url a link in a new tab, without the
// address of the page (it has the token); $cls more classes of the line.
function mel_cgi_about_row($title, $val = '', $url = '', $cls = '')
{
    $c = $cls !== '' ? 'row ' . $cls : 'row';
    $in = '<span class="dot none"></span><span class="rt"><span class="t">' . mel_cgi_h($title) . '</span></span>' .
        ($val !== '' ? '<span class="rv">' . mel_cgi_h($val) . '</span>' : '');
    return $url === '' ? '<div class="' . $c . '">' . $in . '</div>' : '<a class="' . $c . '" href="' . mel_cgi_h($url) .
        '" target="_blank" rel="noopener noreferrer">' . $in . '<span class="chev">›</span></a>';
}

// The screen "О Melange" (0.61.0; its line on the main screen, in "Ещё": the version): the version, the date
// of the build (none: no line), firmware_version of versions.txt whole (260827_0003_r24: the builds of one r
// differ in a report of a problem), the host of the server of the own config (the line "Откуда брать фильмы" does
// not show it; no link: no line; xo: hidden as another answer is chosen), the channel; the donations and the
// referral links of Debrid. The other servers, JacRed and Debrid are on the main screen: not repeated.
function mel_cgi_about($v)
{
    $aio = mel_cgi_watch($v) === 'own' ? mel_cgi_host($v['manifest']) : '';
    $date = mel_cgi_build_date();
    $fw = (string) substr(mel_versions_field(mel_versions_text(), 'firmware_version'), 0, 40);
    return '<div class="list">' .
        mel_cgi_about_row(mel_cgi_l('Версия', 'Version'), MEL_VERSION) .
        ($date !== '' ? mel_cgi_about_row(mel_cgi_l('Сборка', 'Built'), $date) : '') .
        mel_cgi_about_row(mel_cgi_l('Прошивка Дюны', 'Dune firmware'), $fw !== '' ? $fw : mel_cgi_l('не определена', 'unknown')) .
        ($aio !== '' ? mel_cgi_about_row('AIOStreams', $aio, '', 'xo') : '') .
        mel_cgi_about_row(mel_cgi_l('Telegram-канал автора', 'The author\'s Telegram channel'), '', 'https://t.me/arrakis_vibecode') .
        '</div><h3 class="gh">' . mel_cgi_l('Поддержать автора', 'Support the author') . '</h3><div class="list">' .
        mel_cgi_about_row(mel_cgi_l('Т-Банк', 'T-Bank'), '', 'https://t.tb.ru/pm_short/1vlgJXv58ZY') .
        mel_cgi_about_row(mel_cgi_l('ЮMoney', 'YooMoney'), '',
            'https://yoomoney.ru/fundraise/1LATTUJ81C4.261103') .
        mel_cgi_about_row(mel_cgi_l('Подписка Real-Debrid', 'Real-Debrid subscription'), '', 'https://real-debrid.com/?id=20188550') .
        mel_cgi_about_row(mel_cgi_l('Подписка TorBox', 'TorBox subscription'), '',
            'https://torbox.app/subscription?referral=2339fa70-a03e-41f0-a414-9c96f0791bb1') .
        '</div>';
}

// A screen of the page: shown by :target (the CSS), back to #home.
function mel_cgi_screen($id, $body)
{
    $scr = mel_cgi_screens();
    return '<section id="' . $id . '" class="scr"><a class="back" href="#home">' . mel_cgi_l('‹ Настройки', '‹ Settings') .
        '</a><h2>' . $scr[$id] . '</h2>' . $body . '</section>';
}
