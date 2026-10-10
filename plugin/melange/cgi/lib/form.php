<?php
// The settings page (cgi/settings.php): the form - its HTML, the style and
// the script inline, the values posted and their format check.

function aio_cgi_h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// --- The page.

// The fields of the form, the one place of their names (the servers:
// aio_cgi_server_fields): key of the values (aio_cgi_values, aio_cgi_posted)
// => id, name= (also the field of an error of it, aio_cgi_err), key of
// settings.json ('' - none), maxlength (0 - none), type: sec - masked
// (aio_cgi_field), plain - text (aio_cgi_plain), select, radio (an id by
// option), hidden.
function aio_cgi_fields()
{
    return array(
        'source' => array('id' => '', 'name' => 'source', 'set' => 'source', 'max' => 0, 'type' => 'radio'),
        'manifest' => array('id' => 'm', 'name' => 'manifest', 'set' => 'manifest_url', 'max' => 2100, 'type' => 'sec'),
        'aio_server' => array('id' => 'as', 'name' => 'aio_server', 'set' => 'aio_server', 'max' => 0, 'type' => 'select'),
        'aio_own' => array('id' => 'ao', 'name' => 'aio_own_url', 'set' => 'aio_own_url', 'max' => 300, 'type' => 'plain'),
        'aio_tpl' => array('id' => 'at', 'name' => 'aio_tpl', 'set' => 'aio_tpl', 'max' => 0, 'type' => 'select'),
        'tpl_seen' => array('id' => 'ats', 'name' => 'aio_tpl_seen', 'set' => '', 'max' => 0, 'type' => 'hidden'),
        'rd' => array('id' => 'rd', 'name' => 'rd_key', 'set' => 'rd_key', 'max' => 100, 'type' => 'sec'),
        'tb' => array('id' => 'tb', 'name' => 'tb_key', 'set' => 'tb_key', 'max' => 100, 'type' => 'sec'),
        'tmdb' => array('id' => 'tm', 'name' => 'tmdb_key', 'set' => 'tmdb_key', 'max' => 100, 'type' => 'sec'),
        'jacred' => array('id' => '', 'name' => 'jacred', 'set' => 'jacred', 'max' => 0, 'type' => 'radio'),
        'jo_url' => array('id' => 'jou', 'name' => 'jacred_own_url', 'set' => 'jacred_own_url', 'max' => AIO_JACRED_URL_MAX,
            'type' => 'plain'),
        'jo_key' => array('id' => 'jok', 'name' => 'jacred_own_key', 'set' => 'jacred_own_key', 'max' => 200, 'type' => 'sec'),
        'ts' => array('id' => 'ts', 'name' => 'ts', 'set' => 'ts_url', 'max' => 300, 'type' => 'plain'));
}

// The fields of a server on the form (s<n>_<field>), as aio_server_conf()
// takes them: field => id suffix (s<n><id>), maxlength (the limit of
// aio_server_conf()), type as in aio_cgi_fields().
function aio_cgi_server_fields()
{
    return array(
        'name' => array('id' => 'n', 'max' => 40, 'type' => 'plain'),
        'url' => array('id' => 'u', 'max' => 300, 'type' => 'plain'),
        'user' => array('id' => 'l', 'max' => 100, 'type' => 'plain'),
        'pass' => array('id' => 'p', 'max' => 200, 'type' => 'sec'),
        'category' => array('id' => 'c', 'max' => 100, 'type' => 'plain'),
        'tags' => array('id' => 'g', 'max' => 100, 'type' => 'plain'),
        'cache' => array('id' => 'k', 'max' => 0, 'type' => 'select'));
}

// The field $k of the server block $n as a row of aio_cgi_fields().
function aio_cgi_srv_field($n, $k)
{
    $t = aio_cgi_server_fields();
    return array('id' => "s$n" . $t[$k]['id'], 'name' => "s{$n}_$k") + $t[$k];
}

// A text field of the row $f of aio_cgi_fields() (sec or plain); $more: the
// hint of sec, the placeholder of plain.
function aio_cgi_input($f, $label, $value, $more)
{
    return $f['type'] === 'sec' ? aio_cgi_field($f['id'], $f['name'], $label, $value, $more, $f['max']) :
        aio_cgi_plain($f['id'], $f['name'], $label, $value, $more, $f['max']);
}

// $name '' - read-only, not posted.
function aio_cgi_field($id, $name, $label, $value, $hint, $max = 2100)
{
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        // Not type=password: Chrome would offer to keep it in the Google password manager (the script makes it
        // one where CSS can't mask it: Firefox).
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" class="sec" value="' .
        aio_cgi_h($value) . '" ' .
        'autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="' . $max . '">' .
        '<button type="button" class="eye" data-for="' . $id . '" aria-label="' . aio_cgi_l('Показать', 'Show') . '">' .
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>' .
        '</button></div>' . ($hint !== '' ? '<p class="hint">' . $hint . '</p>' : '');
}

// A plain text field (no hiding); $max: the limit of aio_server_conf();
// $name '' - read-only, not posted.
function aio_cgi_plain($id, $name, $label, $value, $placeholder, $max = 300)
{
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" value="' .
        aio_cgi_h($value) . '" placeholder="' .
        aio_cgi_h($placeholder) . '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" ' .
        'maxlength="' . $max . '"></div>';
}

function aio_cgi_radio($id, $name, $value, $label, $on, $attr = '')
{
    return '<label class="cb"><input type="radio" id="' . $id . '" name="' . $name . '" value="' . aio_cgi_h($value) . '"' .
        $attr . ($on ? ' checked' : '') . '> ' . $label . '</label>';
}

// A link of a hint, opened without the address of the page (it has the token).
function aio_cgi_link($url, $text)
{
    return '<a href="' . aio_cgi_h($url) . '" target="_blank" rel="noopener noreferrer">' . aio_cgi_h($text) . '</a>';
}

// The servers of "Download to server": AIO_SERVERS_MAX blocks s<n>_<field>,
// open when filled. "Дублировать" of a block is made by the script. $servers: lists of strings by aio_cgi_server_fields().
function aio_cgi_servers($servers)
{
    // One short line each: the rest is in the README.
    $out = '<h2 id="srvh">' . aio_cgi_l('Скачать на сервер (необязательно)', 'Download to server (optional)') . '</h2>' .
        '<p class="hint">' . aio_cgi_l('qBittorrent или rdt-client: адрес веб-интерфейса; логин пустой — без входа.',
        'qBittorrent or rdt-client: the web UI address; no login - none needed.') . '</p><p class="hint">' .
        aio_cgi_l('Не проверяйте с неверным паролем подряд — qBittorrent блокирует Дюну.',
        'Do not check a wrong password again and again: qBittorrent bans the Dune.') . '</p>';
    for ($n = 1; $n <= AIO_SERVERS_MAX; $n++)
    {
        $v = isset($servers[$n - 1]) ? $servers[$n - 1] : array();
        $f = array();
        foreach (array_keys(aio_cgi_server_fields()) as $k)
        {
            $v[$k] = isset($v[$k]) && is_string($v[$k]) ? $v[$k] : '';
            $f[$k] = aio_cgi_srv_field($n, $k);
        }
        $filled = $v['name'] . $v['url'] . $v['user'] . $v['pass'] . $v['category'] . $v['tags'] !== '';
        $out .= '<details class="srv"' . ($filled ? ' open' : '') . '><summary>' . aio_cgi_l('Сервер', 'Server') . " $n" .
            ($v['name'] !== '' ? ': ' . aio_cgi_h($v['name']) : '') . '</summary>' .
            aio_cgi_input($f['name'], aio_cgi_l('Название', 'Name'), $v['name'], 'qBittorrent') .
            aio_cgi_input($f['url'], aio_cgi_l('Адрес', 'Address'), $v['url'], 'http://192.168.1.10:8080') .
            aio_cgi_input($f['user'], aio_cgi_l('Логин', 'Login'), $v['user'], '') .
            aio_cgi_input($f['pass'], aio_cgi_l('Пароль', 'Password'), $v['pass'], '') .
            aio_cgi_input($f['category'], aio_cgi_l('Категория (необязательно)', 'Category (optional)'), $v['category'], '') .
            aio_cgi_input($f['tags'], aio_cgi_l('Теги через запятую (необязательно)', 'Tags, comma-separated (optional)'),
                $v['tags'], '') .
            '<label for="' . $f['cache']['id'] . '">' . aio_cgi_l('Для кэша', 'For the cache of') . '</label><select id="' .
            $f['cache']['id'] . '" name="' . $f['cache']['name'] . '">';
        foreach (array_merge(array('' => aio_cgi_l('Не из кэша', 'Not from a cache')), aio_server_caches()) as $id => $name)
            $out .= '<option value="' . $id . '"' . ($v['cache'] === $id ? ' selected' : '') . '>' . $name . '</option>';
        $out .= '</select><button type="button" class="chk srvchk">' . aio_cgi_l('Проверить', 'Check') .
            '</button><div class="srvr res" aria-live="polite"></div></details>';
    }
    // Shown by the script, which hides the empty blocks; without JS all five are open to fill.
    return $out . '<button type="button" id="addsrv" class="chk" hidden>' . aio_cgi_l('Добавить сервер', 'Add a server') .
        '</button>';
}

// JacRed: one of the built-in ones or the own one (radio "jacred"); the
// fields of the own one are always there, used when it is chosen.
// "Проверить" asks the chosen one (the own one by the fields of the form),
// "Сравнить все" every built-in one and the own one if filled; one at a time.
function aio_cgi_jacred($v)
{
    // By the source: the script swaps the texts of data-made / data-own.
    $t = array('h' => array(aio_cgi_l('JacRed — поиск раздач и озвучки', 'JacRed - releases and voice-overs'),
        aio_cgi_l('JacRed — дорожки и озвучки', 'JacRed - audio tracks and voice-overs')),
        'p' => array(aio_cgi_l('По нему ищет раздачи созданный конфиг; озвучки и дорожки Melange берёт из него же',
        'The config made finds releases with it; Melange takes voice-overs and tracks from it too'),
        aio_cgi_l('Только озвучки и дорожки; раздачи ищет ваш конфиг',
        'Voice-overs and tracks only; your config finds the releases')));
    $f = aio_cgi_fields();
    $k = $v['source'] === 'own' ? 1 : 0;
    $out = '<h2 id="jrh" data-made="' . aio_cgi_h($t['h'][0]) . '" data-own="' . aio_cgi_h($t['h'][1]) . '">' . $t['h'][$k] .
        '</h2><p class="hint" id="jrp" data-made="' . aio_cgi_h($t['p'][0]) . '" data-own="' . aio_cgi_h($t['p'][1]) . '">' .
        $t['p'][$k] . '</p>' .
        // The created config on a server jacred.stream refuses (the script follows the choice).
        '<p class="hint" id="jr403"' . (!$k && in_array($v['aio_server'], aio_cgi_jr403(), true) ? '' : ' hidden') . '>' .
        aio_cgi_l('jacred.stream не пускает выбранный сервер AIOStreams — выберите другой JacRed',
        'jacred.stream refuses the chosen AIOStreams server: choose another JacRed') . '</p>' .
        // Not when jacred.stream itself is chosen (the script follows the choice).
        '<p class="hint" id="jrnote"' . ($v['jacred'] === AIO_JACRED_DEFAULT ? ' hidden' : '') . '>' .
        aio_cgi_l('Не ответил — Melange спросит jacred.stream.', 'No answer - Melange asks jacred.stream.') . '</p>';
    $i = 0;
    foreach (array_keys(aio_jacred_builtin()) as $id)
    {
        $i++;
        $out .= aio_cgi_radio("jrb$i", $f['jacred']['name'], $id, aio_cgi_h($id), $v['jacred'] === $id,
            ' class="jrb" data-id="' . aio_cgi_h($id) . '"');
    }
    return $out . aio_cgi_radio('jrown', $f['jacred']['name'], 'own', aio_cgi_l('Свой', 'Own'), $v['jacred'] === 'own') .
        aio_cgi_input($f['jo_url'], aio_cgi_l('Адрес своего JacRed', 'Address of the own JacRed'), $v['jo_url'],
            'http://192.168.1.10:9117') .
        // The config made on a public server: it can't reach a home address (the script follows the choice).
        '<p class="hint" id="jrlan"' . ($k || $v['aio_server'] === 'own' ? ' hidden' : '') . '>' .
        aio_cgi_l('Адрес в домашней сети публичному серверу недоступен: нужен адрес из интернета',
        'A home network address is out of reach of a public server: use a public one') .
        '</p>' .
        aio_cgi_input($f['jo_key'], aio_cgi_l('Ключ своего JacRed (необязательно)', 'Key of the own JacRed (optional)'),
            $v['jo_key'], '') .
        '<button type="button" id="jcb" class="chk">' . aio_cgi_l('Проверить', 'Check') . '</button><div id="jcr" class="res" aria-live="polite"></div>' .
        '<button type="button" id="jrsb" class="chk">' . aio_cgi_l('Сравнить все', 'Compare all') .
        '</button><p class="hint">' . aio_cgi_l('Три поиска в каждом JacRed, с паузой 2 с.',
        'Three searches in each JacRed, 2 s apart.') . '</p><div id="jrsr" class="res" aria-live="polite"></div>';
}

// AIOStreams servers where jacred.stream answers 403 to the server (0.35.0).
function aio_cgi_jr403()
{
    return array('https://aiostreams.fortheweak.cloud');
}

// The templates of the config (aio_aio_tpls of common.php, 0.40.0): id =>
// array(its name, its hint).
function aio_cgi_tpls()
{
    return array(
        'addons' => array(aio_cgi_l('JacRed + аддоны', 'JacRed + addons'), aio_cgi_l(
            'Torrentio, MediaFusion, Comet, StremThru Torz — больше раздач в кэше Debrid.',
            'Torrentio, MediaFusion, Comet, StremThru Torz: more releases cached in Debrid.')),
        'jacred' => array(aio_cgi_l('Только JacRed', 'JacRed only'), aio_cgi_l(
            'Русские трекеры, ответ быстрее; с одним RD раздач мало.',
            'Russian trackers, a faster reply; few releases with RD alone.')));
}

// The template the page shows for the server $server (aio_server; 'own' -
// at $own) with the configs $confs (aio_confs), 0.40.0: that of its config
// ('none' - not known yet), else $new (aio_tpl, for a new config).
function aio_cgi_tpl_shown($server, $own, $confs, $new)
{
    $b = $server === 'own' ? aio_aio_own($own) : $server;
    if ($b === '' || !isset($confs[$b]))
        return $new;
    return $confs[$b]['tpl'] !== null ? $confs[$b]['tpl'] : 'none';
}

// The server chosen in $d (settings.json) is the stored one of $cur: the
// one a page without the script showed.
function aio_cgi_same_server($d, $cur)
{
    return $d['aio_server'] === $cur['aio_server'] && ($d['aio_server'] !== 'own' ||
        $d['aio_own_url'] === $cur['aio_own_url']);
}

// The chosen server (of the list or the own one) has a config made by melange.
function aio_cgi_aio_has($v)
{
    $b = $v['aio_server'] === 'own' ? aio_aio_own($v['aio_own']) : $v['aio_server'];
    return $b !== '' && isset($v['confs'][$b]);
}

// What the page shows of a config: the link to its web settings, login,
// password, its template (aio_aio_conf: null - not known yet).
function aio_cgi_aio_show($c)
{
    return $c ? array('cfg' => aio_manifest_base($c['manifest']) . 'configure', 'login' => $c['uuid'],
        'pass' => $c['pass'], 'tpl' => $c['tpl']) : null;
}

// The configs of the AIOStreams choice for the script (#init.confs): value
// of an option => {cfg, login, pass, tpl} (tpl null: its template not known
// yet); that of "own" also with url, the address the config is of. A server
// without a config: none. An object even when empty.
function aio_cgi_aio_confs($v)
{
    $r = array();
    foreach (array_keys(aio_aio_servers()) as $url)
        if (isset($v['confs'][$url]))
            $r[$url] = aio_cgi_aio_show($v['confs'][$url]);
    $own = aio_aio_own($v['aio_own']);
    if ($own !== '' && isset($v['confs'][$own]))
        $r['own'] = aio_cgi_aio_show($v['confs'][$own]) + array('url' => $own);
    return (object) $r;
}

// The marks of the template choice only shown, never chosen (0.40.0): id
// (aio_cgi_tpl_shown) => array(its name, its hint).
function aio_cgi_tpl_marks()
{
    return array(
        'custom' => array(aio_cgi_l('Свой набор аддонов (изменён в AIOStreams)', 'Custom set of addons (changed in AIOStreams)'),
            aio_cgi_l('Выберите шаблон, чтобы заменить им конфиг.', 'Choose a template to replace the config with it.')),
        'none' => array(aio_cgi_l('Не определён — нажмите «Сохранить»', 'Unknown - press "Save"'),
            aio_cgi_l('Melange узнает шаблон конфига на сервере при сохранении.',
            'Melange learns the template of the config there on the save.')));
}

// "Шаблон конфига" (0.40.0) with the hint of the chosen one: that of the
// config of the chosen server, else the one for a new config (tpl_new),
// unless one is chosen in a form back. A mark (aio_cgi_tpl_marks) is
// disabled, so never posted; the one not shown is hidden (the one for a new
// config: #init.tplNew); aio_tpl_seen: the one the page shows (another one on
// "Сохранить" resets the config there; changed by another tab since: the
// save is refused).
function aio_cgi_tpl($v)
{
    $f = aio_cgi_fields();
    $tpls = aio_cgi_tpls();
    $all = $tpls + aio_cgi_tpl_marks();
    $shown = aio_cgi_tpl_shown($v['aio_server'], $v['aio_own'], $v['confs'], $v['tpl_new']);
    $sel = isset($tpls[$v['aio_tpl']]) ? $v['aio_tpl'] : (isset($all[$shown]) ? $shown : key($tpls));
    $out = '<label for="' . $f['aio_tpl']['id'] . '">' . aio_cgi_l('Шаблон конфига', 'Config template') . '</label><select id="' .
        $f['aio_tpl']['id'] . '" name="' . $f['aio_tpl']['name'] . '">';
    foreach ($all as $id => $x)
        $out .= '<option value="' . $id . '" data-hint="' . aio_cgi_h($x[1]) . '"' .
            (isset($tpls[$id]) ? '' : ' disabled' . ($id === $sel ? '' : ' hidden')) . ($id === $sel ? ' selected' : '') . '>' .
            aio_cgi_h($x[0]) . '</option>';
    return $out . '</select><input type="hidden" id="' . $f['tpl_seen']['id'] . '" name="' . $f['tpl_seen']['name'] .
        '" value="' . aio_cgi_h($shown) . '">' .
        '<p class="hint" id="ath">' . aio_cgi_h($all[$sel][1]) . '</p>';
}

// "Конфиг AIOStreams - откуда Melange берёт раздачи" (0.35.2): one of two.
// "Melange создаст конфиг": the server (of the list or another one), the
// template, the Debrid keys, the TMDB key under a server without TMDB of its
// own (another one: always), the config of the chosen server - its link,
// login and password, "Сбросить к шаблону", or "none yet". "У меня свой конфиг": its manifest address, only
// read. The script shows the fields of the chosen one (all without JS) and
// the config of the chosen server from #init.confs (aio_cgi_aio_confs).
function aio_cgi_aio($v)
{
    $chk = '<button type="button" id="%s" class="chk">' . aio_cgi_l('Проверить', 'Check') . '</button><div id="%s" class="res" aria-live="polite"></div>';
    $f = aio_cgi_fields();
    $own = aio_aio_own($v['aio_own']);
    $base = $v['aio_server'] === 'own' ? $own : $v['aio_server'];
    $sel = $base !== '' && isset($v['confs'][$base]) ? aio_cgi_aio_show($v['confs'][$base]) : null;
    $out = '<h2 id="aioh">' . aio_cgi_l('Конфиг AIOStreams — откуда Melange берёт раздачи',
        'AIOStreams config - where Melange gets releases') . '</h2>' .
        aio_cgi_radio('srcm', $f['source']['name'], 'made', aio_cgi_l('Melange создаст конфиг', 'Melange makes the config'),
        $v['source'] !== 'own') . '<div id="smade"><p class="hint">' .
        aio_cgi_l('Нужен ключ Real-Debrid или TorBox.', 'A Real-Debrid or TorBox key is needed.') . '</p><p class="hint">' .
        aio_cgi_l('Раздачи ищут JacRed (раздел ниже) и другие аддоны, смотрятся через Debrid.',
        'JacRed (below) and other addons find the releases, they play via Debrid.') . '</p>' .
        '<label for="' . $f['aio_server']['id'] . '">' . aio_cgi_l('Сервер AIOStreams', 'AIOStreams server') .
        '</label><select id="' . $f['aio_server']['id'] . '" name="' . $f['aio_server']['name'] . '">';
    foreach (aio_aio_servers() as $url => $tmdb)
        $out .= '<option value="' . aio_cgi_h($url) . '" data-tmdb="' . ($tmdb ? '1' : '0') . '"' .
            (in_array($url, aio_cgi_jr403(), true) ? ' data-jr403="1"' : '') .
            ($v['aio_server'] === $url ? ' selected' : '') . '>' .
            aio_cgi_h(substr($url, strlen('https://'))) . ($tmdb ? '' : aio_cgi_l(' — без TMDB', ' - no TMDB')) . '</option>';
    $out .= '<option value="own" data-tmdb="0"' .
        ($v['aio_server'] === 'own' ? ' selected' : '') . '>' . aio_cgi_l('Другой сервер…', 'Another server…') .
        '</option></select><div id="aobox">' . aio_cgi_input($f['aio_own'], aio_cgi_l('Адрес сервера AIOStreams',
        'AIOStreams server address'), $v['aio_own'], 'http://192.168.1.10:3000') .
        '<button type="button" id="aob" class="chk">' . aio_cgi_l('Проверить', 'Check') .
        '</button><div id="aor" class="res" aria-live="polite"></div></div>' .
        aio_cgi_tpl($v) .
        aio_cgi_input($f['rd'], aio_cgi_l('Ключ Real-Debrid (нужен хотя бы один)', 'Real-Debrid key (at least one is needed)'),
            $v['rd'], aio_cgi_l('Где взять: ', 'Get it at ') .
            aio_cgi_link('https://real-debrid.com/apitoken', 'real-debrid.com/apitoken')) .
        sprintf($chk, 'rdb', 'rdr') .
        aio_cgi_input($f['tb'], aio_cgi_l('Ключ TorBox (нужен хотя бы один)', 'TorBox key (at least one is needed)'),
            $v['tb'], aio_cgi_l('Где взять: ', 'Get it at ') .
            aio_cgi_link('https://torbox.app/settings', 'torbox.app/settings')) .
        sprintf($chk, 'tbb', 'tbr');
    $out .= '<div id="tmbox"><p class="hint" id="tmnote">' .
        aio_cgi_l('Без своего TMDB: без сверки названий, меньше раздач сериалов.',
        'No TMDB of its own: titles not matched, fewer series releases.') . '</p>' .
        aio_cgi_input($f['tmdb'], aio_cgi_l('Ключ TMDB (необязательно)', 'TMDB key (optional)'), $v['tmdb'],
        aio_cgi_link('https://www.themoviedb.org/settings/api', 'themoviedb.org') .
        aio_cgi_l(' → Settings → API, ключ v3', ' → Settings → API, the v3 key')) . sprintf($chk, 'tmb', 'tmr') . '</div>' .
        '<div id="acfg"><p class="hint" id="anone"' . ($sel ? ' hidden' : '') . '>' .
        aio_cgi_l('Конфига на этом сервере ещё нет: создастся при сохранении.',
        'No config on this server yet: saving makes one.') . '</p>' .
        '<div id="ahave"' . ($sel ? '' : ' hidden') . '><p class="cfg"><a id="alink"' . ($sel ? ' href="' .
        aio_cgi_h($sel['cfg']) . '"' : '') . ' target="_blank" rel="noreferrer">' .
        aio_cgi_l('Открыть настройки AIOStreams', 'Open the AIOStreams settings') .
        '</a></p>' . aio_cgi_plain('al', '', aio_cgi_l('Логин', 'Login'), $sel ? $sel['login'] : '', '', 36) .
        aio_cgi_field('ap', '', aio_cgi_l('Пароль', 'Password'), $sel ? $sel['pass'] : '', aio_cgi_l(
        'Вход в настройки AIOStreams — по этому логину и паролю.', 'Log in to the AIOStreams settings with this login and password.'),
        24) . '<button type="button" id="apc" class="chk">' . aio_cgi_l('Копировать', 'Copy') . '</button><div id="apr" class="res" aria-live="polite"></div>' .
        // A submit without the script: the save, then the reset (0.40.0).
        '<button type="submit" id="arb" class="chk" name="aio_reset" value="1">' .
        aio_cgi_l('Сбросить к шаблону', 'Reset to the template') . '</button></div></div></div>';
    return $out . aio_cgi_radio('srco', $f['source']['name'], 'own', aio_cgi_l('У меня свой конфиг AIOStreams',
        'I have my own AIOStreams config'), $v['source'] === 'own') . '<div id="sown">' .
        aio_cgi_input($f['manifest'], aio_cgi_l('Ссылка на конфиг', 'Config link'), $v['manifest'],
        aio_cgi_l('Где взять: Save & Install → Stremio → Direct Manifest URL.',
        'Where: Save & Install → Stremio → Direct Manifest URL.')) . sprintf($chk, 'mb', 'mr') .
        '<p class="hint">' . aio_cgi_l('Melange этот конфиг не меняет.', 'Melange does not change this config.') .
        '</p><p class="hint">' . aio_cgi_l('Без Debrid (раздачи P2P) нужен TorrServer — раздел ниже.',
        'Without Debrid (P2P releases) TorrServer is needed: see below.') . '</p></div>';
}

// The style of the page: cgi/settings.css inline (none readable: the page without it).
function aio_cgi_css()
{
    $css = dirname(dirname(__FILE__)) . '/settings.css';
    $css = is_readable($css) ? file_get_contents($css) : false;
    return is_string($css) ? "<style>$css</style>" : '';
}

// "Проверить" and "Сохранить" without leaving the page (fetch, ES2017; an
// older browser fails to parse it and gets the page without the script):
// the page stays the answer to a GET. No fetch -> the form posts as usual.
// Messages go in by textContent; #init has the texts (i18n), the defaults,
// the configs of the servers (confs) and the template for a new config
// (tplNew); the script is cgi/settings.js, built from web/ (none readable:
// the page without it).
function aio_cgi_js($v)
{
    $t = array(
        'duplicate' => aio_cgi_l('Дублировать', 'Duplicate'),
        'serversFull' => aio_cgi_l('Все 5 блоков заняты', 'All 5 blocks are in use'),
        'copySuffix' => aio_cgi_l(' (копия)', ' (copy)'),
        'server' => aio_cgi_l('Сервер', 'Server'),
        'checking' => aio_cgi_l('Проверяю…', 'Checking…'), 'saving' => aio_cgi_l('Сохраняю…', 'Saving…'),
        'save' => aio_cgi_l('Сохранить', 'Save'), 'saved' => aio_cgi_saved(), 'saveAndCreate' => aio_cgi_savec(),
        'savedOnDune' => aio_cgi_l('Настройки Melange сохранены на Дюне.', 'Melange settings are saved on the Dune.'),
        'creating' => aio_cgi_l('Создаю конфиг…', 'Creating the config…'),
        'updating' => aio_cgi_l('Обновляю конфиг…', 'Updating the config…'),
        'resetting' => aio_cgi_l('Сбрасываю конфиг…', 'Resetting the config…'),
        'notCreated' => aio_cgi_l('Конфиг на сервере AIOStreams не создан — нажмите «Сохранить» ещё раз',
            'The config on the AIOStreams server is not created: press "Save" again'),
        'notUpdated' => aio_cgi_l('Конфиг на сервере AIOStreams не обновлён — нажмите «Сохранить» ещё раз',
            'The config on the AIOStreams server is not updated: press "Save" again'),
        'notReset' => aio_cgi_l('Конфиг на сервере AIOStreams не сброшен — нажмите «Сбросить к шаблону» ещё раз',
            'The config on the AIOStreams server is not reset: press "Reset to the template" again'),
        'resetConfirm' => aio_cgi_l('Конфиг на %s будет заменён шаблоном «%s». Ваши правки в веб-настройках AIOStreams ' .
            'пропадут; логин, пароль и ссылка останутся. Продолжить?', 'The config on %s will be replaced with the ' .
            'template "%s". Your edits in the AIOStreams web settings will be lost; the login, password and link stay. ' .
            'Go on?'),
        'passCopied' => aio_cgi_l('Пароль скопирован', 'The password is copied'),
        'passNotCopied' => aio_cgi_l('Не скопировалось — пароль показан, скопируйте вручную',
            'Not copied: the password is shown, copy it by hand'),
        'noConnection' => aio_cgi_l('Нет связи с Дюной: проверьте, что телефон в той же сети, и нажмите ещё раз',
            'No connection to the Dune: check that the phone is on the same network and try again'),
        'timeout' => aio_cgi_l('Дюна не ответила за %d с — повторите', 'The Dune did not answer within %d s: try again'),
        'linkExpired' => aio_cgi_l('Ссылка устарела: откройте настройки заново по QR-коду на Дюне',
            'The link is out of date: open the settings again by the QR code on the Dune'),
        'badChars' => aio_cgi_l('В поле испорченный символ — сотрите его и введите заново',
            'A broken character in the field: delete it and type it again'),
        'httpError' => aio_cgi_l('Ошибка Дюны (HTTP %d), попробуйте ещё раз', 'Error of the Dune (HTTP %d), try again'),
        'jacredFormat' => aio_cgi_fmt_jacred(), 'ownJacred' => aio_cgi_l('Свой JacRed', 'Own JacRed'),
        'colName' => 'JacRed', 'colAvailable' => aio_cgi_l('доступен', 'available'),
        'colTime' => aio_cgi_l('время', 'time'), 'releases' => aio_cgi_l('раздач', 'releases'),
        'choose' => aio_cgi_l('Выбрать', 'Choose'), 'yes' => aio_cgi_l('да', 'yes'),
        'no' => aio_cgi_l('нет', 'no'), 'seconds' => aio_cgi_l('с', 's'), 'stopped' => aio_cgi_l('прервано', 'stopped'));
    $js = dirname(dirname(__FILE__)) . '/settings.js';
    $js = is_readable($js) ? file_get_contents($js) : false;
    if (!is_string($js))
        return '';
    $init = json_encode(array('i18n' => $t, 'jacredDefault' => AIO_JACRED_DEFAULT, 'tplNew' => $v['tpl_new'],
        'confs' => aio_cgi_aio_confs($v)));
    // JSON_HEX_TAG for "<" (PHP 5.3 has none): no "</script>" or "<!--" in the element; "<" is only in strings.
    return '<script type="application/json" id="init">' . str_replace('<', '\\u003c', $init) . '</script><script>' .
        $js . '</script>';
}

// Values of the form from the settings (aio_settings_read) or as posted
// (aio_cgi_posted), by the keys of aio_cgi_fields() - strings (aio_tpl: the
// one chosen in a form back, '' from the settings; tpl_seen only posted);
// tpl_new - aio_tpl of the settings (for a new config); servers - lists of
// strings by aio_cgi_server_fields(); confs - aio_confs of the settings
// (never posted).
function aio_cgi_values($s)
{
    $v = array();
    foreach (aio_cgi_fields() as $k => $f)
        if ($f['set'] !== '')
            $v[$k] = $s[$f['set']];
    $v['tpl_new'] = $v['aio_tpl'];
    $v['aio_tpl'] = '';
    $v['servers'] = $s['servers'];
    $v['confs'] = $s['aio_confs'];
    return $v;
}

function aio_cgi_posted()
{
    $f = aio_cgi_fields();
    $v = array('servers' => array(), 'confs' => array());
    // The own JacRed as typed: aio_jacred_own trims it.
    foreach ($f as $k => $x)
        $v[$k] = $k === 'jo_url' || $k === 'jo_key' ? aio_cgi_param($_POST, $x['name']) :
            trim(aio_cgi_param($_POST, $x['name']), AIO_TRIM);
    // A form without the source: 0.35.1 chose the own config by aio_server
    // "manifest", 0.35.0 and older (no aio_own_url) by the manifest address
    // alone, as aio_settings_read.
    if ($v['source'] !== 'made' && $v['source'] !== 'own')
        $v['source'] = $v['aio_server'] === 'manifest' || ($v['manifest'] !== '' &&
            !array_key_exists($f['aio_own']['name'], $_POST)) ? 'own' : 'made';
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
    foreach (array('rd', 'tb') as $what)
    {
        $k = $set[$what];
        $d[$k] = aio_key_ok($what, $v[$what]);
        if ($v[$what] !== '' && $d[$k] === '')
        {
            if (!$made)
                $d[$k] = $v['cur'][$k];
            else
            {
                $errors[] = aio_cgi_err(aio_cgi_fmt_key($what), $f[$what]['name']);
            }
        }
    }
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
    // Not one of the choices (a form of 0.35.1 or older): the first server, as aio_settings_read.
    $as = array_keys(aio_aio_servers());
    $d[$set['aio_server']] = in_array($v['aio_server'], array_merge($as, array('own')), true) ? $v['aio_server'] : $as[0];
    // The fields of both sources are kept; those of the chosen one must be there.
    if (!$made && $v['manifest'] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Укажите ссылку на конфиг — или выберите «Melange создаст конфиг»',
            'Give the config link, or choose "Melange makes the config"'), $f['manifest']['name']);
    }
    $d[$set['aio_own']] = aio_aio_own($v['aio_own']);
    // Hidden unless another server is chosen to make the config on.
    if ($v['aio_own'] !== '' && $d[$set['aio_own']] === '' && !($made && $d[$set['aio_server']] === 'own'))
        $d[$set['aio_own']] = $v['cur'][$set['aio_own']];
    elseif ($made && $d[$set['aio_server']] === 'own' && $d[$set['aio_own']] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Другой сервер AIOStreams: нужен адрес вида http(s)://хост[:порт] без пути',
            'Another AIOStreams server: an address like http(s)://host[:port] without a path is needed'), $f['aio_own']['name']);
    }
    // No field (a form of 0.39 or older), or a wrong one of the own config: the stored one.
    $d[$set['aio_tpl']] = array_key_exists($v['aio_tpl'], aio_aio_tpls()) ? $v['aio_tpl'] : $v['cur'][$set['aio_tpl']];
    if ($made && $v['aio_tpl'] !== '' && $d[$set['aio_tpl']] !== $v['aio_tpl'])
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Шаблон конфига: нет такого шаблона', 'Config template: no such template'),
            $f['aio_tpl']['name']);
    }
    // The page showed another one for the chosen server (none: a form of
    // 0.39 or older): another tab changed it since. A page without the
    // script showed the stored server only; the script, for an own address
    // other than the stored one, the one for a new config (its conf()).
    $shown = $d[$set['aio_server']] === 'own' && $d[$set['aio_own']] !== $v['cur'][$set['aio_own']] ? $v['cur'][$set['aio_tpl']] :
        aio_cgi_tpl_shown($d[$set['aio_server']], $d[$set['aio_own']], $v['confs'], $v['cur'][$set['aio_tpl']]);
    if ($v['tpl_seen'] !== '' && ($v['ajax'] || aio_cgi_same_server($d, $v['cur'])) && $v['tpl_seen'] !== $shown)
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Шаблон изменён в другой вкладке — обновите страницу',
            'The template was changed in another tab: reload the page'), $f['aio_tpl']['name']);
    }
    // No config there yet and no key to make it.
    $base = $d[$set['aio_server']] === 'own' ? $d[$set['aio_own']] : $d[$set['aio_server']];
    if ($made && $base !== '' && !isset($v['confs'][$base]) && $d[$set['rd']] === '' && $d[$set['tb']] === '' &&
        $v['rd'] . $v['tb'] === '')
    {
        $errors[] = aio_cgi_err(aio_cgi_l('Для конфига нужен ключ Real-Debrid или TorBox — или выберите «У меня свой конфиг»',
            'The config needs a Real-Debrid or TorBox key, or choose "I have my own AIOStreams config"'), $f['rd']['name']);
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
    $d[$set['ts']] = aio_ts_addr($v['ts']);
    if (!is_string($d[$set['ts']]))
    {
        $errors[] = aio_cgi_err(aio_cgi_l('TorrServer: нужен адрес вида http://хост:порт или хост:порт (без https и пути)',
            'TorrServer: an address like http://host:port or host:port is needed (no https, no path)'), $f['ts']['name']);
    }
    return array($d, $errors);
}

// The main button while the chosen server has no config.
function aio_cgi_savec()
{
    return aio_cgi_l('Сохранить и создать конфиг', 'Save and create the config');
}

function aio_cgi_saved()
{
    return aio_cgi_l('Сохранено. Дюна подхватит настройки сама.', 'Saved. The Dune picks the settings up by itself.');
}

// $v: aio_cgi_values() or aio_cgi_posted(); $errors: of aio_cgi_err().
function aio_cgi_form($t, $v, $errors, $saved)
{
    $msg = '';
    foreach ($errors as $e)
        $msg .= '<p class="err">' . aio_cgi_h($e['msg']) . '</p>';
    if ($saved)
        $msg .= '<p class="ok">' . aio_cgi_saved() . '</p>';
    $chk = '<button type="button" id="%s" class="chk">' . aio_cgi_l('Проверить', 'Check') . '</button><div id="%s" class="res" aria-live="polite"></div>';
    $title = aio_cgi_l('Melange — настройки', 'Melange - settings');
    $f = aio_cgi_fields();
    // The sections; Advanced by the heading inside it: a browser opens the closed details on the way to it
    // (newer ones; the script opens it too).
    $toc = '<nav class="toc" aria-label="' . aio_cgi_l('Разделы', 'Sections') . '"><a href="#aioh">AIOStreams</a>' .
        '<a href="#jrh">JacRed</a><a href="#tsh">TorrServer</a><a href="#srvh" id="tocadv">' .
        aio_cgi_l('Дополнительно', 'Advanced') . '</a></nav>';
    $body = '<h1>' . $title . '</h1>' . $toc . '<div id="msg" aria-live="polite">' . $msg . '</div>' .
        '<form method="post" action="settings?t=' . aio_cgi_h($t) . '" autocomplete="off">' .
        // Enter in a field without the script submits the first button: the save, not the reset.
        '<button type="submit" class="dflt" tabindex="-1" aria-hidden="true"></button>' .
        aio_cgi_aio($v) .
        aio_cgi_jacred($v) .
        '<h2 id="tsh">' . aio_cgi_l('Смотреть через TorrServer', 'Watch via TorrServer') . '</h2>' .
        aio_cgi_input($f['ts'], aio_cgi_l('Адрес TorrServer (необязательно)', 'TorrServer address (optional)'),
            $v['ts'], '127.0.0.1:8090') .
        '<p class="hint">' . aio_cgi_l('Пусто — приложение TorrServe на Дюне; свой — http://хост:порт',
            'Empty - the TorrServe app on the Dune; your own - http://host:port') . '</p>' .
        '<p class="hint" id="tsown"' . ($v['source'] === 'own' ? '' : ' hidden') . '>' .
        aio_cgi_l('Раздачи P2P из вашего конфига играют через TorrServer',
        'P2P releases of your config play via TorrServer') . '</p>' .
        sprintf($chk, 'tsb', 'tsr') .
        '<details id="adv"><summary>' . aio_cgi_l('Дополнительно', 'Advanced') . '</summary>' .
        aio_cgi_servers($v['servers']) . '</details>' .
        '<div id="sr" class="res" aria-live="polite"></div><button type="submit" class="save">' . ($v['source'] !== 'own' && !aio_cgi_aio_has($v) &&
        $v['rd'] . $v['tb'] !== '' ? aio_cgi_savec() : aio_cgi_l('Сохранить', 'Save')) . '</button></form>' .
        '<p class="log"><a href="settings?t=' . aio_cgi_h($t) . '&amp;a=log">' . aio_cgi_l('Скачать лог', 'Download the log') .
        '</a></p><p class="hint">' . aio_cgi_l('С последнего включения Дюны; адреса и ключи скрыты.',
        'Since the Dune was last switched on; addresses and keys hidden.') . '</p>';
    return '<!DOCTYPE html><html lang="' . aio_cgi_l('ru', 'en') . '"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1">' .
        '<meta name="referrer" content="no-referrer"><title>' . $title . '</title>' . aio_cgi_css() . '</head><body><main>' .
        $body . '</main>' . aio_cgi_js($v) . '</body></html>';
}
