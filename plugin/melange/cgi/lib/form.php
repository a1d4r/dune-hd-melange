<?php
// The settings page (cgi/settings.php): the form - its HTML, the style and
// the script inline (the values of the form, their check and what the save
// does with them: values.php).

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
// option), hidden; scr - its screen (aio_cgi_screens; of a radio, that of
// its cards), the link of an error of it without the script (aio_cgi_err_screen).
function aio_cgi_fields()
{
    return array(
        // The one choice of the page (debrid | p2p | own): source and aio_mode
        // from it (aio_cgi_posted); those two only of a form of 0.42 or older.
        'watch' => array('id' => '', 'name' => 'watch', 'set' => '', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'source' => array('id' => '', 'name' => 'source', 'set' => 'source', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'manifest' => array('id' => 'm', 'name' => 'manifest', 'set' => 'manifest_url', 'max' => 2100, 'type' => 'sec', 'scr' => 'src'),
        'aio_server' => array('id' => '', 'name' => 'aio_server', 'set' => 'aio_server', 'max' => 0, 'type' => 'radio', 'scr' => 'cfg'),
        'aio_own' => array('id' => 'ao', 'name' => 'aio_own_url', 'set' => 'aio_own_url', 'max' => 300, 'type' => 'plain', 'scr' => 'cfg'),
        'aio_mode' => array('id' => '', 'name' => 'aio_mode', 'set' => 'aio_mode', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'aio_tpl' => array('id' => 'at', 'name' => 'aio_tpl', 'set' => 'aio_tpl', 'max' => 0, 'type' => 'select', 'scr' => 'src'),
        'tpl_seen' => array('id' => 'ats', 'name' => 'aio_tpl_seen', 'set' => '', 'max' => 0, 'type' => 'hidden', 'scr' => 'src'),
        // The same two of the mode P2P (0.44.0).
        'aio_tpl_p2p' => array('id' => 'atp', 'name' => 'aio_tpl_p2p', 'set' => 'aio_tpl_p2p', 'max' => 0, 'type' => 'select',
            'scr' => 'src'),
        'tpl_p2p_seen' => array('id' => 'atps', 'name' => 'aio_tpl_p2p_seen', 'set' => '', 'max' => 0, 'type' => 'hidden',
            'scr' => 'src'),
        'rd' => array('id' => 'rd', 'name' => 'rd_key', 'set' => 'rd_key', 'max' => 100, 'type' => 'sec', 'scr' => 'src'),
        'tb' => array('id' => 'tb', 'name' => 'tb_key', 'set' => 'tb_key', 'max' => 100, 'type' => 'sec', 'scr' => 'src'),
        'tmdb' => array('id' => 'tm', 'name' => 'tmdb_key', 'set' => 'tmdb_key', 'max' => 100, 'type' => 'sec', 'scr' => 'cfg'),
        'jacred' => array('id' => '', 'name' => 'jacred', 'set' => 'jacred', 'max' => 0, 'type' => 'radio', 'scr' => 'voices'),
        'jo_url' => array('id' => 'jou', 'name' => 'jacred_own_url', 'set' => 'jacred_own_url', 'max' => AIO_JACRED_URL_MAX,
            'type' => 'plain', 'scr' => 'voices'),
        'jo_key' => array('id' => 'jok', 'name' => 'jacred_own_key', 'set' => 'jacred_own_key', 'max' => 200, 'type' => 'sec', 'scr' => 'voices'),
        'ts' => array('id' => 'ts', 'name' => 'ts', 'set' => 'ts_url', 'max' => 300, 'type' => 'plain', 'scr' => 'torr'));
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
// hint of sec, the placeholder of plain; $chk: aio_cgi_chk() or ''.
function aio_cgi_input($f, $label, $value, $more, $chk = '')
{
    return $f['type'] === 'sec' ? aio_cgi_field($f['id'], $f['name'], $label, $value, $more, $f['max'], $chk) :
        aio_cgi_plain($f['id'], $f['name'], $label, $value, $more, $f['max'], $chk);
}

// A small button in a field ("Проверить", "Копировать") and the box of its
// result right under the field: array(button, box).
function aio_cgi_chk($bid, $rid, $text = '')
{
    return array('<button type="button" id="' . $bid . '" class="chk in">' . ($text !== '' ? $text :
        aio_cgi_l('Проверить', 'Check')) . '</button>', '<div id="' . $rid . '" class="res" aria-live="polite"></div>');
}

// $name '' - read-only, not posted.
function aio_cgi_field($id, $name, $label, $value, $hint, $max = 2100, $chk = '')
{
    $chk = is_array($chk) ? $chk : array('', '');
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        // Not type=password: Chrome would offer to keep it in the Google password manager (the script makes it
        // one where CSS can't mask it: Firefox).
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" class="sec" value="' .
        aio_cgi_h($value) . '" ' .
        'autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="' . $max . '">' . $chk[0] .
        '<button type="button" class="eye" data-for="' . $id . '" aria-label="' . aio_cgi_l('Показать', 'Show') . '">' .
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>' .
        '</button></div>' . $chk[1] . ($hint !== '' ? '<p class="hint">' . $hint . '</p>' : '');
}

// A plain text field (no hiding); $max: the limit of aio_server_conf();
// $name '' - read-only, not posted.
function aio_cgi_plain($id, $name, $label, $value, $placeholder, $max = 300, $chk = '')
{
    $chk = is_array($chk) ? $chk : array('', '');
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" value="' .
        aio_cgi_h($value) . '" placeholder="' .
        aio_cgi_h($placeholder) . '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" ' .
        'maxlength="' . $max . '">' . $chk[0] . '</div>' . $chk[1];
}

// A radio hidden (a card of aio_cgi_card shows it): its label goes elsewhere;
// $cls: more classes.
function aio_cgi_vradio($id, $name, $value, $on, $cls = '', $attr = '')
{
    return '<input type="radio" class="vh' . ($cls !== '' ? " $cls" : '') . '" id="' . $id . '" name="' . $name . '" value="' .
        aio_cgi_h($value) . '"' . $attr . ($on ? ' checked' : '') . '>';
}

// The card of a choice: the label of the radio $for (aio_cgi_vradio), a title,
// a line under it ('' - none) and $more after them.
function aio_cgi_card($for, $title, $sub, $more = '')
{
    return '<label for="' . $for . '" class="opt"><span class="r"></span><span class="ot"><span class="t">' . $title .
        '</span>' . ($sub !== '' ? '<span class="v">' . $sub . '</span>' : '') . '</span>' . $more . '</label>';
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
    $out = '<p class="hint">' . aio_cgi_l('Адрес веб-интерфейса; логин пустой — без входа.',
        'The web UI address; leave the login empty if none is needed.') . '</p><p class="hint wn">' .
        aio_cgi_l('Не проверяйте с неверным паролем подряд — qBittorrent блокирует Дюну.',
        'Do not check with a wrong password repeatedly: qBittorrent bans the Dune.') . '</p>';
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

// The screen "Озвучки и дорожки" - JacRed: one of the built-in ones or the
// own one (radio "jacred"), a card each with a box for "Сравнить скорость"
// (span.ms data-jr: the id, "own"); the own one with its fields in
// #jrownd, open when chosen or filled; "Проверить" there asks the own one.
function aio_cgi_jacred($v)
{
    // By the source: the script swaps the texts of data-made / data-own.
    $t = array(aio_cgi_l('JacRed ищет русские раздачи и подсказывает озвучки.',
        'JacRed finds Russian releases and tells the voice-overs.'),
        aio_cgi_l('JacRed подсказывает озвучки и дорожки; раздачи ищет ваш конфиг.',
        'JacRed tells the voice-overs and tracks; your config finds the releases.'));
    $f = aio_cgi_fields();
    $k = $v['source'] === 'own' ? 1 : 0;
    // P2P on a server with Magnetio (not of aio_conf_p2p_torznab): Magnetio asks JacRed, not the server.
    $mg = aio_cgi_mode_sel($v) === AIO_MODE_P2P && !aio_conf_has_torznab($v['aio_server']);
    $jr403 = aio_cgi_jr403();
    $out = '<p class="sub" id="jrp" data-made="' . aio_cgi_h($t[0]) . '" data-own="' . aio_cgi_h($t[1]) . '">' . $t[$k] . '</p>' .
        // The created config on a server jacred.stream refuses, jacred.stream chosen (the script follows both);
        // not when Magnetio asks.
        '<p class="hint wn" id="jr403"' . (!$k && !$mg && isset($jr403[$v['aio_server']]) &&
        $v['jacred'] === $jr403[$v['aio_server']] ? '' : ' hidden') . '>' .
        aio_cgi_l('jacred.stream не пускает выбранный сервер AIOStreams — выберите другой JacRed',
        'jacred.stream refuses the chosen AIOStreams server: choose another JacRed') . '</p><div class="choice">';
    $i = 0;
    foreach (array_keys(aio_jacred_builtin()) as $id)
    {
        $i++;
        $out .= aio_cgi_vradio("jrb$i", $f['jacred']['name'], $id, $v['jacred'] === $id, 'jrb',
            ' data-id="' . aio_cgi_h($id) . '"') . aio_cgi_card("jrb$i", aio_cgi_h($id) .
            ($id === AIO_JACRED_DEFAULT ? '<span class="badge">' . aio_cgi_l('у Melange', 'of Melange') . '</span>' : ''), '',
            '<span class="ms" data-jr="' . aio_cgi_h($id) . '"></span>');
    }
    $own = $v['jacred'] === 'own' || trim($v['jo_url'] . $v['jo_key'], AIO_TRIM) !== '';
    // A hint under the list, not when the default itself is chosen (the script follows the choice).
    return $out . '</div><p class="hint" id="jrnote"' . ($v['jacred'] === AIO_JACRED_DEFAULT ? ' hidden' : '') . '>' .
        aio_cgi_l('Не ответил — Melange спросит ' . AIO_JACRED_DEFAULT . '.', 'No answer - Melange will ask ' . AIO_JACRED_DEFAULT . '.') .
        '</p><button type="button" id="jrsb" class="btn ghost">' . aio_cgi_l('Сравнить скорость', 'Compare speed') .
        '</button><p class="hint c">' . aio_cgi_l('Время ответа и число раздач на трёх фильмах',
        'Reply time and the number of releases on three films') . '</p><div id="jrsr" class="res" aria-live="polite"></div>' .
        '<details id="jrownd"' . ($own ? ' open' : '') . '><summary>' . aio_cgi_l('Свой JacRed…', 'Own JacRed…') . '</summary>' .
        '<div class="choice">' . aio_cgi_vradio('jrown', $f['jacred']['name'], 'own', $v['jacred'] === 'own') .
        aio_cgi_card('jrown', aio_cgi_l('Свой JacRed', 'Own JacRed'), '', '<span class="ms" data-jr="own"></span>') . '</div>' .
        aio_cgi_input($f['jo_url'], aio_cgi_l('Адрес', 'Address'), $v['jo_url'], 'http://192.168.1.10:9117',
            aio_cgi_chk('jcb', 'jcr')) .
        // The config made on a public server: it can't reach a home address (the script follows the choice);
        // nor Magnetio, whatever the server.
        '<p class="hint wn" id="jrlan"' . ($k || ($v['aio_server'] === 'own' && !$mg) ? ' hidden' : '') . '>' .
        aio_cgi_l('Адрес в домашней сети публичному серверу недоступен: нужен адрес из интернета',
        'A public server cannot reach a home network address: use an internet address') .
        '</p>' .
        aio_cgi_input($f['jo_key'], aio_cgi_l('Ключ, если нужен', 'Key, if needed'), $v['jo_key'], '') . '</details>';
}

// AIOStreams server => id of the JacRed that answers 403 to it (0.35.0);
// data-jr403 of the server's radio.
function aio_cgi_jr403()
{
    return array('https://aiostreams.fortheweak.cloud' => 'jacred.stream');
}

// The templates of the Debrid mode (aio_tpl_debrid of common.php, 0.40.0):
// id => array(its name, its hint, its short name for the main screen).
function aio_cgi_tpls()
{
    return array(
        'addons' => array(aio_cgi_l('Везде', 'Everywhere'), aio_cgi_l('JacRed, Torrentio, MediaFusion и другие — больше раздач в кэше Debrid.',
            'JacRed, Torrentio, MediaFusion and more: more releases cached in Debrid.'), aio_cgi_l('везде', 'everywhere')),
        'jacred' => array(aio_cgi_l('Только русские трекеры', 'Russian trackers only'), aio_cgi_l(
            'JacRed: быстрее; с одним Real-Debrid раздач мало.', 'JacRed: faster; few releases with Real-Debrid alone.'),
            aio_cgi_l('только русские', 'Russian only')));
}

// The templates of the mode P2P (aio_tpl_p2p of common.php, 0.44.0), as aio_cgi_tpls().
function aio_cgi_tpls_p2p()
{
    return array(
        AIO_TPL_P2P => array(aio_cgi_l('Везде', 'Everywhere'), aio_cgi_l(
            'Torrentio, JacRed, TorrentsDB, Comet и StremThru Torz — больше раздач.',
            'Torrentio, JacRed, TorrentsDB, Comet and StremThru Torz: more releases.'), aio_cgi_l('везде', 'everywhere')),
        AIO_TPL_P2P_JACRED => array(aio_cgi_l('Только русские трекеры', 'Russian trackers only'), aio_cgi_l(
            'JacRed: быстрее, раздачи русских трекеров.', 'JacRed: faster, releases of Russian trackers.'),
            aio_cgi_l('только русские', 'Russian only')));
}

// The template choice of the mode $m on the page (0.44.0: one per mode):
// field - the key of its select in aio_cgi_fields() and the values, seen -
// that of its aio_tpl_seen, new - the key of the values with the one for a
// new config, tpls - its templates as aio_cgi_tpls().
function aio_cgi_tpl_keys($m)
{
    return $m === AIO_MODE_P2P ? array('field' => 'aio_tpl_p2p', 'seen' => 'tpl_p2p_seen', 'new' => 'tpl_p2p_new',
        'tpls' => aio_cgi_tpls_p2p()) : array('field' => 'aio_tpl', 'seen' => 'tpl_seen', 'new' => 'tpl_new', 'tpls' => aio_cgi_tpls());
}

// The name of the template $id: of the Debrid mode or P2P.
function aio_cgi_tpl_name($id)
{
    $tpls = aio_cgi_tpls() + aio_cgi_tpls_p2p();
    return isset($tpls[$id]) ? $tpls[$id][0] : '';
}

// The mode P2P on the page.
function aio_cgi_mode_p2p()
{
    return aio_cgi_l('Торренты', 'Torrents');
}

// The template the page shows in the mode $m for the server $server
// (aio_server; 'own' - at $own) with the configs $confs (aio_confs), 0.40.0:
// that of its config of the mode ('none' - not known yet), else $new
// (aio_tpl or aio_tpl_p2p, for a new config).
function aio_cgi_tpl_shown($m, $server, $own, $confs, $new)
{
    $b = $server === 'own' ? aio_aio_own($own) : $server;
    if ($b === '' || !isset($confs[$b][$m]))
        return $new;
    return $confs[$b][$m]['tpl'] !== null ? $confs[$b][$m]['tpl'] : 'none';
}

// The mode the page shows: the one chosen in a form back, else aio_mode of
// the settings (one for all servers).
function aio_cgi_mode_sel($v)
{
    return in_array($v['aio_mode'], aio_aio_modes(), true) ? $v['aio_mode'] : $v['mode_cur'];
}

// The config made by melange on the chosen server (of the list or the own
// one) in the mode the page shows (aio_cgi_mode_sel), or null.
function aio_cgi_aio_sel($v)
{
    $b = $v['aio_server'] === 'own' ? aio_aio_own($v['aio_own']) : $v['aio_server'];
    $m = aio_cgi_mode_sel($v);
    return $b !== '' && isset($v['confs'][$b][$m]) ? $v['confs'][$b][$m] : null;
}

// What the page shows of a config: the link to its web settings, login,
// password and its template (aio_aio_conf: null - not known yet).
function aio_cgi_aio_show($c)
{
    return $c ? array('cfg' => aio_manifest_base($c['manifest']) . 'configure', 'login' => $c['uuid'],
        'pass' => $c['pass'], 'tpl' => $c['tpl']) : null;
}

// The configs of the AIOStreams choice for the script (#init.confs): mode
// (aio_aio_modes) => value of an option => {cfg, login, pass, tpl} (tpl
// null: its template not known yet); that of "own" also with url, the
// address the config is of. A server without a config in a mode: none
// there. Objects even when empty.
function aio_cgi_aio_confs($v)
{
    $r = array();
    $own = aio_aio_own($v['aio_own']);
    foreach (aio_aio_modes() as $m)
    {
        $c = array();
        foreach (array_keys(aio_aio_servers()) as $url)
            if (isset($v['confs'][$url][$m]))
                $c[$url] = aio_cgi_aio_show($v['confs'][$url][$m]);
        if ($own !== '' && isset($v['confs'][$own][$m]))
            $c['own'] = aio_cgi_aio_show($v['confs'][$own][$m]) + array('url' => $own);
        $r[$m] = (object) $c;
    }
    return (object) $r;
}

// The marks of the template choice only shown, never chosen (0.40.0): id
// (aio_cgi_tpl_shown) => array as aio_cgi_tpls().
function aio_cgi_tpl_marks()
{
    return array(
        'custom' => array(aio_cgi_l('Свой набор аддонов (изменён в AIOStreams)', 'Custom set of addons (changed in AIOStreams)'),
            aio_cgi_l('Выберите шаблон, чтобы заменить им конфиг.', 'Choose a template to replace the config with it.'),
            aio_cgi_l('свой набор', 'custom set')),
        'none' => array(sprintf(aio_cgi_l('Не определён — нажмите «%s»', 'Unknown - press "%s"'), aio_cgi_saveb()),
            aio_cgi_l('Melange узнает шаблон конфига на сервере при сохранении.',
            'Melange detects the config template on the server when you save.'), aio_cgi_l('шаблон не определён', 'template unknown')));
}

// The template chosen on the page in the mode $m (aio_cgi_tpl): one chosen
// in a form back, else the one shown (aio_cgi_tpl_shown), else the one for a new config.
function aio_cgi_tpl_sel($v, $m)
{
    $tk = aio_cgi_tpl_keys($m);
    $k = $tk['field'];
    $new = $v[$tk['new']];
    $shown = aio_cgi_tpl_shown($m, $v['aio_server'], $v['aio_own'], $v['confs'], $new);
    $all = $tk['tpls'] + aio_cgi_tpl_marks();
    return isset($tk['tpls'][$v[$k]]) ? $v[$k] : (isset($all[$shown]) ? $shown : $new);
}

// "Где искать раздачи" - the template of the mode $m (0.40.0 Debrid, 0.44.0
// P2P: one each) with the hint of the chosen one (#<id>h): that of the
// config of the mode of the chosen server (a template or a mark), else the
// one for a new config (tpl_new, tpl_p2p_new), unless one is chosen in a
// form back. A mark (aio_cgi_tpl_marks) is disabled, so never posted; the
// one not shown is hidden (the one for a new config: #init.tplNew);
// aio_tpl_seen, aio_tpl_p2p_seen: the one the page shows (another one on
// "Сохранить" resets the config there; changed by another tab since: the
// save is refused).
function aio_cgi_tpl($v, $m)
{
    $f = aio_cgi_fields();
    $tk = aio_cgi_tpl_keys($m);
    $k = $tk['field'];
    $seen = $tk['seen'];
    $tpls = $tk['tpls'];
    $all = $tpls + aio_cgi_tpl_marks();
    $shown = aio_cgi_tpl_shown($m, $v['aio_server'], $v['aio_own'], $v['confs'], $v[$tk['new']]);
    $sel = aio_cgi_tpl_sel($v, $m);
    $out = '<label for="' . $f[$k]['id'] . '">' . aio_cgi_l('Где искать раздачи', 'Where to look for releases') . '</label><select id="' .
        $f[$k]['id'] . '" name="' . $f[$k]['name'] . '">';
    foreach ($all as $id => $x)
        $out .= '<option value="' . $id . '" data-hint="' . aio_cgi_h($x[1]) . '" data-short="' . aio_cgi_h($x[2]) . '"' .
            (isset($tpls[$id]) ? '' : ' disabled' . ($id === $sel ? '' : ' hidden')) . ($id === $sel ? ' selected' : '') . '>' .
            aio_cgi_h($x[0]) . '</option>';
    return $out . '</select><input type="hidden" id="' . $f[$seen]['id'] . '" name="' . $f[$seen]['name'] .
        '" value="' . aio_cgi_h($shown) . '">' .
        '<p class="hint" id="' . $f[$k]['id'] . 'h">' . aio_cgi_h($all[$sel][1]) . '</p>';
}

// The answers of "Откуда брать фильмы" (radio watch): id of the radio => value.
function aio_cgi_watches()
{
    return array('wd' => AIO_MODE_DEBRID, 'wp' => AIO_MODE_P2P, 'wo' => 'own');
}

// The answer the page shows: the own config, else the mode (aio_cgi_mode_sel).
function aio_cgi_watch($v)
{
    return $v['source'] === 'own' ? 'own' : aio_cgi_mode_sel($v);
}

// The host of an address ('' - none).
function aio_cgi_host($url)
{
    $h = parse_url($url, PHP_URL_HOST);
    return is_string($h) ? $h : '';
}

// The screen "Откуда брать фильмы" (0.43.0): three cards of the radio watch
// (aio_cgi_form), under them the fields of the answer shown by the CSS
// (classes xd, xp, xo, xm - Debrid, Торренты, the own config, both made):
// Debrid - the keys (#dseg, #rdadd, #tbadd: by the script) and the template;
// Торренты - #tsp2p, where the script moves #tsbox of #torr (without it, a
// link there), and the template of P2P; the own config - its manifest
// address, only read. The server of both made: on #cfg (aio_cgi_cfg).
function aio_cgi_src($v)
{
    $f = aio_cgi_fields();
    return aio_cgi_watch_cards() .
        '<div id="mdeb" class="xd"><div id="dseg" class="seg" role="group" aria-label="' . aio_cgi_l('Сервис', 'Service') .
        '" hidden><button type="button" data-k="rd" aria-pressed="true">Real-Debrid</button>' .
        '<button type="button" data-k="tb" aria-pressed="false">TorBox</button></div><div id="rdbox">' .
        aio_cgi_input($f['rd'], aio_cgi_l('Ключ Real-Debrid', 'Real-Debrid key'), $v['rd'], aio_cgi_l('Где взять: ', 'Get it at ') .
            aio_cgi_link('https://real-debrid.com/apitoken', 'real-debrid.com/apitoken'), aio_cgi_chk('rdb', 'rdr')) .
        '</div><button type="button" id="tbadd" class="lnk" hidden>' . aio_cgi_l('+ Добавить TorBox', '+ Add TorBox') .
        '</button><div id="tbbox">' .
        aio_cgi_input($f['tb'], aio_cgi_l('Ключ TorBox', 'TorBox key'), $v['tb'], aio_cgi_l('Где взять: ', 'Get it at ') .
            aio_cgi_link('https://torbox.app/settings', 'torbox.app/settings'), aio_cgi_chk('tbb', 'tbr')) .
        '</div><button type="button" id="rdadd" class="lnk" hidden>' . aio_cgi_l('+ Добавить Real-Debrid', '+ Add Real-Debrid') .
        '</button>' . aio_cgi_tpl($v, AIO_MODE_DEBRID) . '</div>' .
        '<div id="mp2p" class="xp"><p class="hint">' . aio_cgi_l('Ключ не нужен: фильмы качаются с раздачи и играют через TorrServer.',
        'No key needed: films are fetched from the swarm and play via TorrServer.') . '</p><div id="tsp2p"></div>' .
        '<p class="hint" id="tslink"><a href="#torr">' . aio_cgi_l('Адрес TorrServer и проверка ›', 'TorrServer address and check ›') .
        '</a></p>' . aio_cgi_tpl($v, AIO_MODE_P2P) . '</div>' .
        '<div id="sown" class="xo">' . aio_cgi_input($f['manifest'], aio_cgi_l('Ссылка на конфиг', 'Config link'), $v['manifest'],
        aio_cgi_l('Где взять: Save & Install → Stremio → Direct Manifest URL.',
        'Get it at: Save & Install → Stremio → Direct Manifest URL.'), aio_cgi_chk('mb', 'mr')) .
        '<p class="hint">' . aio_cgi_l('Melange этот конфиг не меняет.', 'Melange does not change this config.') .
        '</p><p class="hint">' . aio_cgi_l('Торренты из него играют через ', 'Torrents of it play via ') .
        '<a href="#torr">TorrServer</a>.</p></div>';
}

// The screen "Сервер AIOStreams" (#cfg, 0.44.0; its fields: Debrid and
// Торренты, #smade of class xm): the server melange makes the config on - a
// card of the radio aio_server each (the host; "у Melange" of
// AIO_AIO_SERVER_MELANGE; "без TMDB" of one without TMDB of its own; data-tmdb,
// data-jr403, data-p2ptz for the script), another one in #asownd (its radio
// "own" and its address; open when chosen or filled); the TMDB key under a
// server without TMDB of its own (another one: always); then the config of
// the chosen server in the mode (#acfg) - its link, login and password,
// "Сбросить к шаблону" - or "none yet"; the script shows the one of the
// chosen server and mode from #init.confs (aio_cgi_aio_confs).
function aio_cgi_cfg($v)
{
    $f = aio_cgi_fields();
    $name = $f['aio_server']['name'];
    $jr403 = aio_cgi_jr403();
    $out = '<p class="hint xo" id="cfgown">' . aio_cgi_l('Свой конфиг Melange не меняет.', 'Melange does not change your own config.') .
        '</p><div id="smade" class="xm"><div class="choice">';
    $i = 0;
    foreach (aio_aio_servers() as $url => $tmdb)
    {
        $i++;
        $out .= aio_cgi_vradio("as$i", $name, $url, $v['aio_server'] === $url, '', ' data-tmdb="' . ($tmdb ? '1' : '0') . '"' .
            (isset($jr403[$url]) ? ' data-jr403="' . aio_cgi_h($jr403[$url]) . '"' : '') .
            (aio_conf_has_torznab($url) ? ' data-p2ptz="1"' : '')) .
            aio_cgi_card("as$i", aio_cgi_h(aio_cgi_host($url)) . ($url === AIO_AIO_SERVER_MELANGE ? '<span class="badge">' .
            aio_cgi_l('у Melange', 'of Melange') . '</span>' : ''), $tmdb ? '' : aio_cgi_l('без TMDB', 'no TMDB'));
    }
    $own = $v['aio_server'] === 'own';
    $sel = aio_cgi_aio_show(aio_cgi_aio_sel($v));
    return $out . '</div><details id="asownd"' . ($own || trim($v['aio_own'], AIO_TRIM) !== '' ? ' open' : '') . '><summary>' .
        aio_cgi_l('Другой сервер…', 'Another server…') . '</summary><div class="choice">' .
        aio_cgi_vradio('asown', $name, 'own', $own, '', ' data-tmdb="0"') .
        aio_cgi_card('asown', aio_cgi_l('Другой сервер', 'Another server'), '') . '</div>' .
        aio_cgi_input($f['aio_own'], aio_cgi_l('Адрес сервера AIOStreams', 'AIOStreams server address'), $v['aio_own'],
        'http://192.168.1.10:3000', aio_cgi_chk('aob', 'aor')) . '</details>' .
        '<div id="tmbox"><p class="hint" id="tmnote">' .
        aio_cgi_l('Без своего TMDB: без сверки названий, меньше раздач сериалов.',
        'The server has no TMDB of its own: titles are not matched, fewer series releases.') . '</p>' .
        aio_cgi_input($f['tmdb'], aio_cgi_l('Ключ TMDB (необязательно)', 'TMDB key (optional)'), $v['tmdb'],
        aio_cgi_link('https://www.themoviedb.org/settings/api', 'themoviedb.org') .
        aio_cgi_l(' → Settings → API, ключ v3', ' → Settings → API, the v3 key'), aio_cgi_chk('tmb', 'tmr')) . '</div>' .
        '<h3 class="gh">' . aio_cgi_l('Конфиг в AIOStreams', 'Config in AIOStreams') . '</h3><p class="sub">' .
        aio_cgi_l('Для опытных: аддоны и фильтры — в веб-настройках AIOStreams.',
        'For the experienced: addons and filters are in the AIOStreams web settings.') . '</p>' .
        '<div id="acfg"><p class="hint" id="anone"' . ($sel ? ' hidden' : '') . '>' .
        aio_cgi_l('Конфига на этом сервере ещё нет: создастся при сохранении.',
        'No config on this server yet: saving will create one.') . '</p>' .
        '<div id="ahave"' . ($sel ? '' : ' hidden') . '><p class="cfg"><a id="alink" class="btn ghost"' . ($sel ? ' href="' .
        aio_cgi_h($sel['cfg']) . '"' : '') . ' target="_blank" rel="noreferrer">' .
        aio_cgi_l('Открыть настройки AIOStreams', 'Open the AIOStreams settings') .
        '</a></p>' . aio_cgi_plain('al', '', aio_cgi_l('Логин', 'Login'), $sel ? $sel['login'] : '', '', 36) .
        aio_cgi_field('ap', '', aio_cgi_l('Пароль', 'Password'), $sel ? $sel['pass'] : '', aio_cgi_l(
        'Вход в настройки AIOStreams — по этому логину и паролю.', 'Log in to the AIOStreams settings with this login and password.'),
        24, aio_cgi_chk('apc', 'apr', aio_cgi_l('Копировать', 'Copy'))) .
        // A submit without the script: the save, then the reset (0.40.0).
        '<button type="submit" id="arb" class="btn danger" name="aio_reset" value="1">' .
        aio_cgi_resetb() . '</button><p class="hint">' .
        aio_cgi_l('Ваши правки в AIOStreams пропадут; логин, пароль и ссылка останутся.',
        'Your edits in AIOStreams are lost; the login, password and link stay.') . '</p></div></div></div>';
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
// the configs of the servers by mode (confs) and the template for a new
// config of each mode (tplNew); the script is cgi/settings.js, built from web/ (none readable:
// the page without it).
function aio_cgi_js($v)
{
    $again = aio_cgi_l('%s — нажмите «%s» ещё раз', '%s: press "%s" again');
    $t = array(
        'duplicate' => aio_cgi_l('Дублировать', 'Duplicate'),
        'serversFull' => aio_cgi_l('Все 5 блоков заняты', 'All 5 server slots are in use'),
        'copySuffix' => aio_cgi_l(' (копия)', ' (copy)'),
        'server' => aio_cgi_l('Сервер', 'Server'),
        'checking' => aio_cgi_l('Проверяю…', 'Checking…'), 'saving' => aio_cgi_l('Сохраняю…', 'Saving…'),
        'save' => aio_cgi_saveb(), 'saved' => aio_cgi_saved(), 'saveAndCreate' => aio_cgi_savec(),
        'savedOnDune' => aio_cgi_l('Настройки Melange сохранены на Дюне.', 'Melange settings are saved on the Dune.'),
        'creating' => aio_cgi_l('Создаю конфиг…', 'Creating the config…'),
        'updating' => aio_cgi_l('Обновляю конфиг…', 'Updating the config…'),
        'resetting' => aio_cgi_l('Сбрасываю конфиг…', 'Resetting the config…'),
        'notCreated' => sprintf($again, aio_cgi_not_done('create'), aio_cgi_saveb()),
        'notUpdated' => sprintf($again, aio_cgi_not_done('update'), aio_cgi_saveb()),
        'notReset' => sprintf($again, aio_cgi_not_done('reset'), aio_cgi_resetb()),
        'resetConfirm' => aio_cgi_l('Конфиг на %s будет заменён шаблоном «%s». Ваши правки в веб-настройках AIOStreams ' .
            'пропадут; логин, пароль и ссылка останутся. Продолжить?', 'The config on %s will be replaced with the ' .
            'template "%s". Your edits in the AIOStreams web settings will be lost; the login, password and link stay. ' .
            'Continue?'),
        'modeP2p' => aio_cgi_mode_p2p(),
        'passCopied' => aio_cgi_l('Пароль скопирован', 'Password copied'),
        'passNotCopied' => aio_cgi_l('Не скопировалось — пароль показан, скопируйте вручную',
            'Not copied: the password is shown, copy it by hand'),
        'noConnection' => aio_cgi_l('Нет связи с Дюной: проверьте, что телефон в той же сети, и нажмите ещё раз',
            'No connection to the Dune: check that the phone is on the same network and try again'),
        'timeout' => aio_cgi_l('Дюна не ответила за %d с — повторите', 'The Dune did not answer within %d s: try again'),
        'linkExpired' => aio_cgi_l('Ссылка устарела: откройте настройки заново по QR-коду на Дюне',
            'The link is out of date: open the settings again via the QR code on the Dune'),
        'badChars' => aio_cgi_l('В поле испорченный символ — сотрите его и введите заново',
            'A broken character in the field: delete it and type it again'),
        'httpError' => aio_cgi_l('Ошибка Дюны (HTTP %d), попробуйте ещё раз', 'Dune error (HTTP %d), try again'),
        'jacredFormat' => aio_cgi_fmt_jacred(),
        'releases' => aio_cgi_l('раздач', 'releases'), 'noAnswer' => aio_cgi_l('нет ответа', 'no answer'),
        'yes' => aio_cgi_l('да', 'yes'),
        'no' => aio_cgi_l('нет', 'no'), 'seconds' => aio_cgi_l('с', 's'), 'stopped' => aio_cgi_l('прервано', 'stopped'),
        // The count of the changes by "Сохранить": the forms of 1, 2-4 and 5 (English: 1 and the rest).
        'changeOne' => aio_cgi_l('%d изменение', '%d change'), 'changeFew' => aio_cgi_l('%d изменения', '%d changes'),
        'changeMany' => aio_cgi_l('%d изменений', '%d changes')) +
        // The lines of the main screen (aio_cgi_rows).
        aio_cgi_home_texts();
    $js = dirname(dirname(__FILE__)) . '/settings.js';
    $js = is_readable($js) ? file_get_contents($js) : false;
    if (!is_string($js))
        return '';
    $init = json_encode(array('i18n' => $t, 'jacredDefault' => AIO_JACRED_DEFAULT,
        'tplNew' => array(AIO_MODE_DEBRID => $v['tpl_new'], AIO_MODE_P2P => $v['tpl_p2p_new']), 'confs' => aio_cgi_aio_confs($v)));
    // JSON_HEX_TAG for "<" (PHP 5.3 has none): no "</script>" or "<!--" in the element; "<" is only in strings.
    return '<script type="application/json" id="init">' . str_replace('<', '\\u003c', $init) . '</script><script>' .
        $js . '</script>';
}

// The labels of the buttons, quoted in other texts as well.
function aio_cgi_saveb()
{
    return aio_cgi_l('Сохранить', 'Save');
}

// The main button while the chosen server has no config.
function aio_cgi_savec()
{
    return aio_cgi_l('Сохранить и создать конфиг', 'Save and create the config');
}

function aio_cgi_resetb()
{
    return aio_cgi_l('Сбросить к шаблону', 'Reset to the template');
}

// The sync with the AIOStreams server failed; $what: create, update or reset.
function aio_cgi_not_done($what)
{
    $t = array('create' => array('не создан', 'created'), 'update' => array('не обновлён', 'updated'),
        'reset' => array('не сброшен', 'reset'));
    return aio_cgi_l('Конфиг на сервере AIOStreams ' . $t[$what][0], 'The config on the AIOStreams server was not ' . $t[$what][1]);
}

function aio_cgi_saved()
{
    return aio_cgi_l('Сохранено. Дюна подхватит настройки сама.', 'Saved. The Dune picks up the settings automatically.');
}

// $v: aio_cgi_values() or aio_cgi_posted() (first: no saved_at yet, not
// saved_once of aio_settings_read); $errors: of aio_cgi_err().
// The page (0.43.0): the radios of watch, then the screens, #home last -
// the CSS shows the one of the address (#src...), else #home, and the
// fields of the answer chosen (without the script too); the bar of the
// save under them (#bar: the script shows it on a change, #dirty - their
// number, #srx - "Скрыть" of the results of a save). Errors without the script: in #msg, each with a link to the
// screen of its field, the field aria-invalid.
function aio_cgi_form($t, $v, $errors, $saved)
{
    $scr = aio_cgi_screens();
    $msg = '';
    foreach ($errors as $e)
    {
        $to = aio_cgi_err_screen($e['field']);
        $msg .= '<p class="err">' . aio_cgi_h($e['msg']) . ($to !== '' ? ' <a href="#' . $to . '">' . $scr[$to] . ' ›</a>' : '') .
            '</p>';
    }
    if ($saved)
        $msg .= '<p class="ok">' . aio_cgi_saved() . '</p>';
    $title = aio_cgi_l('Melange — настройки', 'Melange settings');
    $f = aio_cgi_fields();
    $w = aio_cgi_watch($v);
    $radios = '';
    foreach (aio_cgi_watches() as $id => $x)
        $radios .= aio_cgi_vradio($id, $f['watch']['name'], $x, $w === $x);
    $form = '<form method="post" action="settings?t=' . aio_cgi_h($t) . '" autocomplete="off">' .
        // Enter in a field without the script submits the first button: the save, not the reset.
        '<button type="submit" class="dflt" tabindex="-1" aria-hidden="true"></button>' . $radios .
        aio_cgi_screen('src', aio_cgi_src($v)) .
        aio_cgi_screen('cfg', aio_cgi_cfg($v)) .
        aio_cgi_screen('voices', aio_cgi_jacred($v)) .
        aio_cgi_screen('torr', '<p class="sub xd">' . aio_cgi_l('Запасной путь для раздач не из кэша Debrid.',
            'A fallback for releases not cached in Debrid.') . '</p><p class="sub xp">' .
            aio_cgi_l('С ответом «Торренты» без него не посмотреть.', 'With "Torrents" nothing plays without it.') . '</p>' .
            '<p class="sub xo" id="tsown">' . aio_cgi_l('Торренты из вашего конфига играют через TorrServer.',
            'Torrents of your config play via TorrServer.') . '</p>' .
            '<p class="hint" id="tsmoved" hidden><a href="#src">' . aio_cgi_l('Адрес — на экране «Откуда брать фильмы» ›',
            'The address is on the screen "Where to get films" ›') . '</a></p><div id="tsbox">' .
            aio_cgi_input($f['ts'], aio_cgi_l('Адрес TorrServer', 'TorrServer address'), $v['ts'],
                aio_cgi_l('Приложение на этой Дюне', 'The app on this Dune'), aio_cgi_chk('tsb', 'tsr')) .
            '<p class="hint">' . aio_cgi_l('Пусто — приложение TorrServe на Дюне; свой — http://хост:порт',
            'Empty - the TorrServe app on the Dune; your own - http://host:port') . '</p></div>') .
        aio_cgi_screen('dl', '<p class="sub">' . aio_cgi_l('На Дюне в списке раздач: Меню → «Скачать на сервер».',
            'On the Dune, in the list of releases: Menu → "Download to server".') . '</p>' . aio_cgi_servers($v['servers'])) .
        aio_cgi_screen('log', '<p class="sub">' . aio_cgi_l('С последнего включения Дюны; адреса и ключи скрыты — можно переслать разработчику.',
            'Since the Dune was last switched on; addresses and keys hidden: fine to send to the developer.') . '</p>' .
            '<a class="btn" href="settings?t=' . aio_cgi_h($t) . '&amp;a=log">' . aio_cgi_l('Скачать лог', 'Download the log') . '</a>') .
        '<section id="home" class="scr">' . aio_cgi_home($v) . '</section>' .
        '<div id="bar"><div id="sr" class="res" aria-live="polite"></div><button type="button" id="srx" class="lnk" hidden>' .
        aio_cgi_l('Скрыть', 'Hide') . '</button><div class="barrow"><span id="dirty"></span>' .
        '<button type="submit" id="save" class="save">' . (aio_cgi_state($v) === 'conf' ? aio_cgi_savec() :
        aio_cgi_saveb()) .
        '</button></div></div></form>';
    // The fields of the errors marked (a radio of watch or jacred: each one).
    foreach ($errors as $e)
        if ($e['field'] !== null)
            $form = str_replace(' name="' . $e['field'] . '"', ' name="' . $e['field'] . '" aria-invalid="true"', $form);
    return '<!DOCTYPE html><html lang="' . aio_cgi_l('ru', 'en') . '"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' .
        '<meta name="color-scheme" content="light dark">' .
        '<meta name="referrer" content="no-referrer"><title>' . $title . '</title>' . aio_cgi_css() . '</head><body><main>' .
        '<div id="msg" aria-live="polite">' . $msg . '</div>' . $form . '</main>' . aio_cgi_js($v) . '</body></html>';
}
