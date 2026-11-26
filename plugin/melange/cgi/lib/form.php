<?php
// The settings page (cgi/settings.php): the form - its HTML, the style and
// the script inline (the values of the form, their check and what the save
// does with them: values.php).

function mel_cgi_h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// --- The page.

// The fields of the form, the one place of their names (the servers:
// mel_cgi_server_fields): key of the values (mel_cgi_values, mel_cgi_posted)
// => id, name= (also the field of an error of it, mel_cgi_err), key of
// settings.json ('' - none), maxlength (0 - none), type: sec - masked
// (mel_cgi_field), plain - text (mel_cgi_plain), select, radio (an id by
// option; of the template choices aio_tpl, aio_tpl_p2p the id is the prefix
// of those of their cards and #<id>h of their marks), hidden; scr - its
// screen (mel_cgi_screens; of a radio, that of its cards), the link of an
// error of it without the script (mel_cgi_err_screen); raw - posted as
// typed, not trimmed (mel_cgi_posted).
function mel_cgi_fields()
{
    return array(
        // The one choice of the page (debrid | p2p | own): source and aio_mode
        // from it (mel_cgi_posted); those two only of a form of 0.42 or older.
        'watch' => array('id' => '', 'name' => 'watch', 'set' => '', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'source' => array('id' => '', 'name' => 'source', 'set' => 'source', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'manifest' => array('id' => 'm', 'name' => 'manifest', 'set' => 'manifest_url', 'max' => 2100, 'type' => 'sec', 'scr' => 'src'),
        'aio_server' => array('id' => '', 'name' => 'aio_server', 'set' => 'aio_server', 'max' => 0, 'type' => 'radio', 'scr' => 'cfg'),
        'aio_own' => array('id' => 'ao', 'name' => 'aio_own_url', 'set' => 'aio_own_url', 'max' => 300, 'type' => 'plain', 'scr' => 'cfg'),
        'aio_mode' => array('id' => '', 'name' => 'aio_mode', 'set' => 'aio_mode', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'aio_tpl' => array('id' => 'at', 'name' => 'aio_tpl', 'set' => 'aio_tpl', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'tpl_seen' => array('id' => 'ats', 'name' => 'aio_tpl_seen', 'set' => '', 'max' => 0, 'type' => 'hidden', 'scr' => 'src'),
        // The same two of the mode P2P (0.44.0).
        'aio_tpl_p2p' => array('id' => 'atp', 'name' => 'aio_tpl_p2p', 'set' => 'aio_tpl_p2p', 'max' => 0, 'type' => 'radio',
            'scr' => 'src'),
        'tpl_p2p_seen' => array('id' => 'atps', 'name' => 'aio_tpl_p2p_seen', 'set' => '', 'max' => 0, 'type' => 'hidden',
            'scr' => 'src'),
        'rd' => array('id' => 'rd', 'name' => 'rd_key', 'set' => 'rd_key', 'max' => 100, 'type' => 'sec', 'scr' => 'src'),
        'tb' => array('id' => 'tb', 'name' => 'tb_key', 'set' => 'tb_key', 'max' => 100, 'type' => 'sec', 'scr' => 'src'),
        // "Основной Debrid" (0.56.0): data_dir/debrid_main, not settings.json (mel_cgi_debrid_main).
        'debrid_main' => array('id' => 'dm', 'name' => 'debrid_main', 'set' => '', 'max' => 0, 'type' => 'radio', 'scr' => 'src'),
        'debrid_main_was' => array('id' => 'dmw', 'name' => 'debrid_main_was', 'set' => '', 'max' => 0, 'type' => 'hidden',
            'scr' => 'src'),
        'tmdb' => array('id' => 'tm', 'name' => 'tmdb_key', 'set' => 'tmdb_key', 'max' => 100, 'type' => 'sec', 'scr' => 'cfg'),
        'jacred' => array('id' => '', 'name' => 'jacred', 'set' => 'jacred', 'max' => 0, 'type' => 'radio', 'scr' => 'voices'),
        'jo_url' => array('id' => 'jou', 'name' => 'jacred_own_url', 'set' => 'jacred_own_url', 'max' => MEL_JACRED_URL_MAX,
            'type' => 'plain', 'scr' => 'voices', 'raw' => true),
        'jo_key' => array('id' => 'jok', 'name' => 'jacred_own_key', 'set' => 'jacred_own_key', 'max' => 200, 'type' => 'sec', 'scr' => 'voices',
            'raw' => true),
        'ts' => array('id' => 'ts', 'name' => 'ts', 'set' => 'ts_url', 'max' => 300, 'type' => 'plain', 'scr' => 'torr'),
        // Basic auth of TorrServer (0.45.0); also typed in the address as login:password@host.
        'ts_user' => array('id' => 'tsu', 'name' => 'ts_user', 'set' => 'ts_user', 'max' => 100, 'type' => 'plain', 'scr' => 'torr'),
        'ts_pass' => array('id' => 'tsp', 'name' => 'ts_pass', 'set' => 'ts_pass', 'max' => 200, 'type' => 'sec', 'scr' => 'torr',
            'raw' => true),
        // "Играть" or "Добавить и играть" (0.45.0): mel_cgi_ts_saves().
        'ts_save' => array('id' => 'tss', 'name' => 'ts_save', 'set' => 'ts_save', 'max' => 0, 'type' => 'radio', 'scr' => 'torr'),
        // "Пропуск заставки и титров" (0.49.1): mel_cgi_markers().
        'markers' => array('id' => 'mrk', 'name' => 'markers', 'set' => 'markers', 'max' => 0, 'type' => 'radio', 'scr' => 'adv'),
        // "Ошибки аддонов" (0.49.1): mel_cgi_toggles(), as the others on/off.
        'source_errors' => array('id' => 'ser', 'name' => 'source_errors', 'set' => 'source_errors', 'max' => 0, 'type' => 'radio', 'scr' => 'adv'),
        // "Пульт без цветных кнопок" (0.61.0).
        'no_color_keys' => array('id' => 'nck', 'name' => 'no_color_keys', 'set' => 'no_color_keys', 'max' => 0, 'type' => 'radio',
            'scr' => 'adv'),
        // "Без торрентов" (0.49.0): its radios at the top of the form (mel_cgi_form), for the CSS.
        'no_torrents' => array('id' => 'nt', 'name' => 'no_torrents', 'set' => 'no_torrents', 'max' => 0, 'type' => 'radio',
            'scr' => 'adv'),
        // "Адрес Lampac" (v2: a source of the kind "Lampac", lampac.php): mel_cgi_lampac_block on #src.
        'lampac' => array('id' => 'lp', 'name' => 'lampac_url', 'set' => 'lampac_url', 'max' => MEL_LP_URL_MAX, 'type' => 'plain',
            'scr' => 'src'),
        // Under it: "Токен провайдера" ('' none, mel_lp_token) and "Представляться приложением Lampa" (mel_cgi_toggles).
        'lp_token' => array('id' => 'lpt', 'name' => 'lampac_token', 'set' => 'lampac_token', 'max' => MEL_LP_TOKEN_MAX,
            'type' => 'sec', 'scr' => 'src'),
        'lp_lampa' => array('id' => 'lpa', 'name' => 'lampac_as_lampa', 'set' => 'lampac_as_lampa', 'max' => 0, 'type' => 'radio',
            'scr' => 'src'),
        // The editor of the sources (v2, lib/profiles.php): the source the page shows (its id, new), the one a new one is
        // the copy of (its id, ''); its name as typed.
        'pf_sel' => array('id' => 'pfs', 'name' => 'prof', 'set' => '', 'max' => 0, 'type' => 'hidden', 'scr' => 'src'),
        'pf_from' => array('id' => 'pff', 'name' => 'prof_from', 'set' => '', 'max' => 0, 'type' => 'hidden', 'scr' => 'src'),
        'pf_name' => array('id' => 'pfn', 'name' => 'prof_name', 'set' => '', 'max' => MEL_PROFILE_NAME_MAX, 'type' => 'plain',
            'scr' => 'src', 'raw' => true),
        // "Рейтинг IMDb на постерах".
        'cat_rating' => array('id' => 'rti', 'name' => 'cat_rating', 'set' => 'cat_rating', 'max' => 0, 'type' => 'radio', 'scr' => 'look'),
        // "Логотип фильма вместо названия" (0.55.0).
        'cat_logo' => array('id' => 'clg', 'name' => 'cat_logo', 'set' => 'cat_logo', 'max' => 0, 'type' => 'radio', 'scr' => 'look'),
        // "Возраст" of "Фильтр каталога" (0.65.0, lib/hidden.php; a list since 0.70.0): mel_cgi_cat_ages().
        'cat_age' => array('id' => 'cag', 'name' => 'cat_age', 'set' => 'cat_age', 'max' => 0, 'type' => 'select', 'scr' => 'hide'),
        // "Рейтинг IMDb: фильмы", "...: сериалы" of "Фильтр каталога" (0.70.0, lib/hidden.php): mel_cgi_cat_imdbs().
        'cat_imdb_movie' => array('id' => 'cim', 'name' => 'cat_imdb_movie', 'set' => 'cat_imdb_movie', 'max' => 0, 'type' => 'select',
            'scr' => 'hide'),
        'cat_imdb_series' => array('id' => 'cis', 'name' => 'cat_imdb_series', 'set' => 'cat_imdb_series', 'max' => 0,
            'type' => 'select', 'scr' => 'hide'),
        // The rows of the catalog chosen: mel_cgi_rows_screen.
        'cat_rows' => array('id' => 'crv', 'name' => 'cat_rows', 'set' => 'cat_rows', 'max' => 0, 'type' => 'plain', 'scr' => 'rows'),
        // cat_rows as the page came (as debrid_main_was): the save keeps the stored ones when it is posted back.
        'cat_rows_was' => array('id' => 'crw', 'name' => 'cat_rows_was', 'set' => '', 'max' => 0, 'type' => 'hidden', 'scr' => 'rows'));
}

// The fields of a server on the form (s<n>_<field>), as mel_server_conf()
// takes them: field => id suffix (s<n><id>), maxlength (the limit of
// mel_server_conf()), type as in mel_cgi_fields().
function mel_cgi_server_fields()
{
    return array(
        'type' => array('id' => 't', 'max' => 0, 'type' => 'select'),
        'name' => array('id' => 'n', 'max' => 40, 'type' => 'plain'),
        'url' => array('id' => 'u', 'max' => 300, 'type' => 'plain'),
        'user' => array('id' => 'l', 'max' => 100, 'type' => 'plain'),
        'pass' => array('id' => 'p', 'max' => 200, 'type' => 'sec'),
        'category' => array('id' => 'c', 'max' => 100, 'type' => 'plain'),
        'tags' => array('id' => 'g', 'max' => 100, 'type' => 'plain'),
        'cache' => array('id' => 'k', 'max' => 0, 'type' => 'select'));
}

// The field $k of the server block $n as a row of mel_cgi_fields().
function mel_cgi_srv_field($n, $k)
{
    $t = mel_cgi_server_fields();
    return array('id' => "s$n" . $t[$k]['id'], 'name' => "s{$n}_$k") + $t[$k];
}

// A text field of the row $f of mel_cgi_fields() (sec or plain); $more: the
// hint of sec, the placeholder of plain; $chk: mel_cgi_chk() or ''.
function mel_cgi_input($f, $label, $value, $more, $chk = '')
{
    return $f['type'] === 'sec' ? mel_cgi_field($f['id'], $f['name'], $label, $value, $more, $f['max'], $chk) :
        mel_cgi_plain($f['id'], $f['name'], $label, $value, $more, $f['max'], $chk);
}

// A hidden field of the row $f of mel_cgi_fields() with $value (the *_was ones: a value as the page came, posted back).
function mel_cgi_hidden($f, $value)
{
    return '<input type="hidden" id="' . $f['id'] . '" name="' . $f['name'] . '" value="' . mel_cgi_h($value) . '">';
}

// A small button in a field ("Проверить", "Копировать") and the box of its
// result right under the field: array(button, box).
function mel_cgi_chk($bid, $rid, $text = '')
{
    return array('<button type="button" id="' . $bid . '" class="chk in">' . ($text !== '' ? $text :
        mel_cgi_l('Проверить', 'Check')) . '</button>', '<div id="' . $rid . '" class="res" aria-live="polite"></div>');
}

// $name '' - read-only, not posted.
function mel_cgi_field($id, $name, $label, $value, $hint, $max = 2100, $chk = '')
{
    $chk = is_array($chk) ? $chk : array('', '');
    return '<label for="' . $id . '">' . $label . '</label><div class="f">' .
        // Not type=password: Chrome would offer to keep it in the Google password manager (the script makes it
        // one where CSS can't mask it: Firefox).
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" class="sec" value="' .
        mel_cgi_h($value) . '" ' .
        'autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="' . $max . '">' . $chk[0] .
        '<button type="button" class="eye" data-for="' . $id . '" aria-label="' . mel_cgi_l('Показать', 'Show') . '">' .
        '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">' .
        '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>' .
        '</button></div>' . $chk[1] . ($hint !== '' ? '<p class="hint">' . $hint . '</p>' : '');
}

// A plain text field (no hiding); $max: the limit of mel_server_conf();
// $name '' - read-only, not posted. $label_attrs, $input_attrs: more
// attributes of the label and of the input, escaped (' data-x="…"').
function mel_cgi_plain($id, $name, $label, $value, $placeholder, $max = 300, $chk = '', $label_attrs = '',
    $input_attrs = '')
{
    $chk = is_array($chk) ? $chk : array('', '');
    return '<label for="' . $id . '"' . $label_attrs . '>' . $label . '</label><div class="f">' .
        '<input id="' . $id . '" ' . ($name !== '' ? 'name="' . $name . '"' : 'readonly') . ' type="text" value="' .
        mel_cgi_h($value) . '"' . $input_attrs . ' placeholder="' .
        mel_cgi_h($placeholder) . '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" ' .
        'maxlength="' . $max . '">' . $chk[0] . '</div>' . $chk[1];
}

// A radio hidden (a card of mel_cgi_card shows it): its label goes elsewhere;
// $cls: more classes.
function mel_cgi_vradio($id, $name, $value, $on, $cls = '', $attr = '')
{
    return '<input type="radio" class="vh' . ($cls !== '' ? " $cls" : '') . '" id="' . $id . '" name="' . $name . '" value="' .
        mel_cgi_h($value) . '"' . $attr . ($on ? ' checked' : '') . '>';
}

// The card of a choice: the label of the radio $for (mel_cgi_vradio), a title,
// a line under it ('' - none) and $more after them.
function mel_cgi_card($for, $title, $sub, $more = '')
{
    return '<label for="' . $for . '" class="opt"><span class="r"></span><span class="ot"><span class="t">' . $title .
        '</span>' . ($sub !== '' ? '<span class="v">' . $sub . '</span>' : '') . '</span>' . $more . '</label>';
}

// A simple choice (div.choice): a card of a radio of the row $f of
// mel_cgi_fields() each (its id: that of the row and the number of the card),
// by $opts value => array(title, hint[, 'short' => data-short of the radio]); $sel:
// the value checked (a string: a key of digits is an int in PHP).
function mel_cgi_choice($f, $opts, $sel)
{
    $out = '<div class="choice">';
    $i = 0;
    foreach ($opts as $val => $x)
    {
        $i++;
        $out .= mel_cgi_vradio($f['id'] . $i, $f['name'], $val, strval($val) === $sel, '', isset($x['short']) ?
            ' data-short="' . mel_cgi_h($x['short']) . '"' : '') . mel_cgi_card($f['id'] . $i, mel_cgi_h($x[0]), mel_cgi_h($x[1]));
    }
    return $out . '</div>';
}

// A list (select) of the row $f of mel_cgi_fields() under its label $label, by $opts value => array(title[, 'short' =>
// data-short of the option]), as mel_cgi_choice; $sel: the value selected (a string, as of mel_cgi_choice: the age of
// mel_cgi_cat_ages is a key of digits; another one: none, the first one shown).
function mel_cgi_select($f, $label, $opts, $sel)
{
    $out = '<label for="' . $f['id'] . '">' . $label . '</label><select id="' . $f['id'] . '" name="' . $f['name'] . '">';
    foreach ($opts as $val => $x)
        $out .= '<option value="' . mel_cgi_h($val) . '"' . (isset($x['short']) ? ' data-short="' . mel_cgi_h($x['short']) . '"' : '') .
            (strval($val) === $sel ? ' selected' : '') . '>' . mel_cgi_h($x[0]) . '</option>';
    return $out . '</select>';
}

// The options of mel_cgi_select of the names $names (value => name), no data-short.
function mel_cgi_opts($names)
{
    $out = array();
    foreach ($names as $val => $name)
        $out[$val] = array($name);
    return $out;
}

// A link of a hint, opened without the address of the page (it has the token).
function mel_cgi_link($url, $text)
{
    return '<a href="' . mel_cgi_h($url) . '" target="_blank" rel="noopener noreferrer">' . mel_cgi_h($text) . '</a>';
}

// data-qbt / data-tr of $x = array(text for qBittorrent, for Transmission):
// the script swaps them by the client chosen (of a label: its text, of an
// input: its placeholder).
function mel_cgi_srv_alt($x)
{
    return ' data-qbt="' . mel_cgi_h($x[0]) . '" data-tr="' . mel_cgi_h($x[1]) . '"';
}

// A plain field of a server block whose label ($label; a string: the same for
// both) and placeholder ($ph) depend on the client: array(qBittorrent,
// Transmission), the one of $type shown.
function mel_cgi_srv_plain($f, $label, $value, $ph, $type)
{
    $i = $type === 'tr' ? 1 : 0;
    return mel_cgi_plain($f['id'], $f['name'], is_array($label) ? $label[$i] : $label, $value, $ph[$i], $f['max'], '',
        is_array($label) ? mel_cgi_srv_alt($label) : '', $ph[0] === $ph[1] ? '' : mel_cgi_srv_alt($ph));
}

// The servers of "Download to server": MEL_SERVERS_MAX blocks s<n>_<field>,
// open when filled. "Дублировать" of a block is made by the script. $servers: lists of strings by mel_cgi_server_fields().
function mel_cgi_servers($servers)
{
    // One short line each: the rest is in the README.
    $out = '<p class="hint">' . mel_cgi_l('Адрес веб-интерфейса; логин пустой — без входа.',
        'The web UI address; leave the login empty if none is needed.') . '</p><p class="hint wn">' .
        mel_cgi_l('Не проверяйте с неверным паролем подряд — qBittorrent блокирует Дюну.',
        'Do not check with a wrong password repeatedly: qBittorrent bans the Dune.') . '</p>';
    for ($n = 1; $n <= MEL_SERVERS_MAX; $n++)
    {
        $v = isset($servers[$n - 1]) ? $servers[$n - 1] : array();
        $f = array();
        foreach (array_keys(mel_cgi_server_fields()) as $k)
        {
            $v[$k] = isset($v[$k]) && is_string($v[$k]) ? $v[$k] : '';
            $f[$k] = mel_cgi_srv_field($n, $k);
        }
        $filled = $v['name'] . $v['url'] . $v['user'] . $v['pass'] . $v['category'] . $v['tags'] !== '';
        // Its client (select s<n>_type, 0.47.0); none or unknown: qBittorrent, as mel_server_conf.
        $types = mel_server_types();
        $type = isset($types[$v['type']]) ? $v['type'] : 'qbt';
        $out .= '<details class="srv"' . ($filled ? ' open' : '') . '><summary>' . mel_cgi_l('Сервер', 'Server') . " $n" .
            ($v['name'] !== '' ? ': ' . mel_cgi_h($v['name']) : '') . '</summary>' .
            mel_cgi_select($f['type'], mel_cgi_l('Клиент', 'Client'), mel_cgi_opts($types), $type) .
            mel_cgi_input($f['name'], mel_cgi_l('Название', 'Name'), $v['name'], 'qBittorrent') .
            mel_cgi_srv_plain($f['url'], mel_cgi_l('Адрес', 'Address'), $v['url'],
                array('http://192.168.1.10:8080', 'http://192.168.1.10:9091'), $type) .
            mel_cgi_input($f['user'], mel_cgi_l('Логин', 'Login'), $v['user'], '') .
            mel_cgi_input($f['pass'], mel_cgi_l('Пароль', 'Password'), $v['pass'], '') .
            mel_cgi_srv_plain($f['category'], array(mel_cgi_l('Категория (необязательно)', 'Category (optional)'),
                mel_cgi_l('Папка загрузки на сервере (необязательно)', 'Download folder on the server (optional)')),
                $v['category'], array('', ''), $type) .
            mel_cgi_srv_plain($f['tags'], array(mel_cgi_l('Теги через запятую (необязательно)', 'Tags, comma-separated (optional)'),
                mel_cgi_l('Метки через запятую (необязательно)', 'Labels, comma-separated (optional)')), $v['tags'], array('', ''),
                $type) .
            mel_cgi_select($f['cache'], mel_cgi_l('Для кэша', 'For the cache of'), mel_cgi_opts(array_merge(
                array('' => mel_cgi_l('Не из кэша', 'Not from a cache')), mel_server_caches())), $v['cache']) .
            '<button type="button" class="chk srvchk">' . mel_cgi_l('Проверить', 'Check') .
            '</button><div class="srvr res" aria-live="polite"></div></details>';
    }
    // Shown by the script, which hides the empty blocks; without JS all five are open to fill.
    return $out . '<button type="button" id="addsrv" class="chk" hidden>' . mel_cgi_l('Добавить сервер', 'Add a server') .
        '</button>';
}

// The answers of "Что делать с раздачей" of TorrServer (radio ts_save,
// 0.45.0): its value => array(name, hint); "add" - the add keeps the release
// in the list of TorrServer (ts_save of the settings), "play" - it forgets it.
function mel_cgi_ts_saves()
{
    return array(
        'play' => array(mel_cgi_l('Играть', 'Play'), mel_cgi_l('TorrServer забудет раздачу после просмотра.',
            'TorrServer forgets the release after watching.')),
        'add' => array(mel_cgi_l('Добавить и играть', 'Add & Play'), mel_cgi_l(
            'Раздача останется в списке TorrServer.',
            'The release stays in the TorrServer list.')));
}

// The answer of mel_cgi_ts_saves() of ts_save of the settings (a bool).
function mel_cgi_ts_save_of($on)
{
    return $on ? 'add' : 'play';
}

// "Что делать с раздачей" on #torr: a card of the radio ts_save each (a value
// of the form: '' or another one - none checked).
function mel_cgi_ts_save($v)
{
    $f = mel_cgi_fields();
    return '<p class="lbl">' . mel_cgi_l('Что делать с раздачей', 'What to do with a release') . '</p>' .
        mel_cgi_choice($f['ts_save'], mel_cgi_ts_saves(), $v['ts_save']);
}

// The answers of "Пропуск заставки и титров" on the screen #adv (radio markers,
// 0.49.1): its value => array(name, hint); "on" - markers of the settings,
// "exact" - also markers_exact.
function mel_cgi_markers()
{
    return array(
        'on' => array(mel_cgi_l('Включён', 'On'), mel_cgi_l(
            'Кнопки пропуска в плеере. Серии могут запускаться чуть медленнее.',
            'Skip buttons in the player. Episodes may start a little slower.')),
        'exact' => array(mel_cgi_l('Только точные', 'Exact only'), mel_cgi_l(
            'Только метки, сверенные с длиной файла. Кнопок будет меньше.',
            'Only marks matched to the file length. Fewer buttons.')),
        'off' => array(mel_cgi_l('Выключен', 'Off'), mel_cgi_l(
            'Выключите, если серии долго запускаются.',
            'Turn off if episodes take long to start.')));
}

// The answer of mel_cgi_markers() of markers and markers_exact of the settings (bools).
function mel_cgi_markers_of($on, $exact)
{
    return $on ? ($exact ? 'exact' : 'on') : 'off';
}

// "Пропуск заставки и титров" on #adv (a value of the form: '' or another one -
// none checked).
function mel_cgi_markers_block($v)
{
    $f = mel_cgi_fields();
    return '<p class="lbl">' . mel_cgi_l('Пропуск заставки и титров', 'Skip intro and credits') . '</p>' .
        mel_cgi_choice($f['markers'], mel_cgi_markers(), $v['markers']);
}

// --- The settings on/off.

// The settings on/off of the page (radios of two answers, by mel_cgi_choice): the key of mel_cgi_fields() =>
// array(its label, the answers - value => array(name, hint), the answer of true of the settings). Their value of the
// form (mel_cgi_toggle_of: mel_cgi_values; a form of before without one - the stored one, settings.php), its check
// (mel_cgi_check_values: "<label>: нет такого ответа"). Not here: ts_save (kept when torrents are off), markers (three
// answers).
function mel_cgi_toggles()
{
    return array(
        // "Запуск торрентов" (0.49.0): its radios at the top of the form, for the CSS (mel_cgi_form); its cards on #adv, not
        // on #torr (its line goes with "Без торрентов"), none with "Торренты" (class xt), the value kept.
        'no_torrents' => array(mel_cgi_l('Запуск торрентов', 'Running torrents'), array(
            'allow' => array(mel_cgi_l('Разрешить', 'Allow'), mel_cgi_l('Если конфиг отдаёт P2P, они показываются; TorrServer и скачивание доступны.',
                'P2P releases show if the config has them; TorrServer and downloads are available.')),
            'off' => array(mel_cgi_l('Без торрентов', 'No torrents'), mel_cgi_l(
                'Melange не запускает торренты: P2P скрыты, TorrServer и скачивание отключены.',
                'Melange does not start torrents: P2P hidden, TorrServer and downloads disabled.'))), 'off'),
        // 0.49.1.
        'source_errors' => array(mel_cgi_l('Ошибки аддонов', 'Addon errors'), array(
            'off' => array(mel_cgi_l('Выключено', 'Off'), mel_cgi_l(
                'Melange не показывает ошибки аддонов AIOStreams.',
                'Melange does not show AIOStreams addon errors.')),
            'on' => array(mel_cgi_l('Включено', 'On'), mel_cgi_l(
                'Над списком раздач — число ошибок, причины — в меню «Действия».',
                'The number of errors above the list of releases, the reasons in the "Actions" menu.'))), 'on'),
        // 0.61.0.
        'no_color_keys' => array(mel_cgi_l('Пульт без цветных кнопок', 'Remote without colour buttons'), array(
            'off' => array(mel_cgi_l('Выключено', 'Off'), mel_cgi_l(
                'Сортировка цветными кнопками, сведения кнопкой INFO.',
                'Sort with the colour buttons, details with the INFO button.')),
            'on' => array(mel_cgi_l('Включено', 'On'), mel_cgi_l(
                'Всё с цветных кнопок и INFO — в меню «Действия» раздач и онлайн-списка.',
                'Adds colour button and INFO items to "Actions" of the release and online lists.'))), 'on'),
        // On #look (0.55.0).
        'cat_logo' => array(mel_cgi_l('Логотип фильма вместо названия', 'Film logo in place of the title'), array(
            'on' => array(mel_cgi_l('Включён', 'On'), mel_cgi_l(
                'Русский логотип в карточке над рядами, как в онлайн-кинотеатрах.',
                'The Russian logo in the card above the rows, as in streaming services.')),
            'off' => array(mel_cgi_l('Выключен', 'Off'), mel_cgi_l(
                'Название текстом, памяти Дюны нужно вдвое меньше.',
                'The title as text; the Dune needs half the memory.'))), 'on'),
        'cat_rating' => array(mel_cgi_l('Рейтинг IMDb на постерах', 'IMDb rating on posters'), array(
            'off' => array(mel_cgi_l('Выключен', 'Off'), mel_cgi_l(
                'Постеры без цифр, рейтинги — в карточке над рядами.',
                'Posters without numbers, the ratings in the card above the rows.')),
            'on' => array(mel_cgi_l('Включён', 'On'), mel_cgi_l(
                'Цифра IMDb в углу постера: в рядах каталога и в карточке.',
                'The IMDb number in the corner of a poster: in the catalog rows and in the card.'))), 'on'),
        // Under "Токен провайдера" of Lampac on #src: the User-Agent of Lampa and the uid of the Dune (mel_lp_send of lampac.php).
        'lp_lampa' => array(mel_cgi_l('Представляться приложением Lampa', 'Present as the Lampa app'), array(
            'off' => array(mel_cgi_l('Выключено', 'Off'), mel_cgi_l(
                'Melange шлёт запросы под своим именем.',
                'Melange sends requests under its own name.')),
            'on' => array(mel_cgi_l('Включено', 'On'), mel_cgi_l(
                'Melange шлёт запросы как Lampa в браузере Android. Под вашу ответственность.',
                'Melange sends requests as Lampa in an Android browser. At your own risk.'))), 'on'));
}

// The answer of toggle $k (mel_cgi_toggles) of a bool of the settings.
function mel_cgi_toggle_of($k, $on)
{
    $t = mel_cgi_toggles();
    foreach (array_keys($t[$k][1]) as $x)
    {
        if (($x === $t[$k][2]) === (bool) $on)
            return $x;
    }
    return '';
}

// The label of toggle $k and the cards of its answers ($v: values of the form; '' or another one - none checked).
function mel_cgi_toggle_block($k, $v)
{
    $f = mel_cgi_fields();
    $t = mel_cgi_toggles();
    return '<p class="lbl">' . $t[$k][0] . '</p>' . mel_cgi_choice($f[$k], $t[$k][1], $v[$k]);
}

// The label of toggle $k and the cards of its radios, not the radios: those at the top of the form, for the CSS
// (mel_cgi_form: no_torrents); $cls: more classes of the label and the cards ('' none).
function mel_cgi_toggle_cards($k, $cls = '')
{
    $f = mel_cgi_fields();
    $t = mel_cgi_toggles();
    $c = $cls !== '' ? " $cls" : '';
    $out = '<p class="lbl' . $c . '">' . $t[$k][0] . '</p><div class="choice' . $c . '">';
    $i = 0;
    foreach ($t[$k][1] as $x)
        $out .= mel_cgi_card($f[$k]['id'] . ++$i, mel_cgi_h($x[0]), mel_cgi_h($x[1]));
    return $out . '</div>';
}

// The fields of a source of the kind "Lampac" on #src (v2; #slp of class xl - by the CSS only with that answer): its
// address with "Проверить" (a=lp_check, with the token and the option below as typed; needed: no "off"), the token of
// its provider (a secret, as the password of TorrServer), "Представляться приложением Lampa".
function mel_cgi_lampac_block($v)
{
    $f = mel_cgi_fields();
    return '<div id="slp" class="xl">' . mel_cgi_input($f['lampac'], mel_cgi_l('Адрес Lampac', 'Lampac address'), $v['lampac'],
        'http://lampac.example:9118', mel_cgi_chk('lpb', 'lpr')) .
        '<p class="hint">' . mel_cgi_l('Ваш сервер Lampac: http://хост:порт', 'Your Lampac server: http://host:port') .
        '</p>' . mel_cgi_input($f['lp_token'], mel_cgi_l('Токен провайдера', 'Provider token'), $v['lp_token'],
        mel_cgi_l('Пусто — без входа. Токен выдаёт провайдер Lampac.', 'Empty: no login. Your Lampac provider gives the token.')) .
        mel_cgi_toggle_block('lp_lampa', $v) . '</div>';
}

// The screen #look (a value of the form: '' or another one - none checked). Under the logo:
// on r24, older or an unknown firmware (mel_fw_vod_r) a warning that it needs r25, the catalog
// shows the title as text whatever is chosen; else when to turn it off. "Своя карточка" is gone (0.70.0: always on; a
// cat_card of an older settings.json is not read).
function mel_cgi_look_screen($v)
{
    return '<p class="sub">' . mel_cgi_l('Действует со следующего открытия каталога.', 'Takes effect the next time the catalog opens.') .
        '</p>' . mel_cgi_toggle_block('cat_logo', $v) .
        (mel_fw_vod_r() ? '<p class="hint">' . mel_cgi_l('Выключите, если каталог тормозит или карточки грузятся долго.',
        'Turn off if the catalog lags or the cards take long to load.') : '<p class="hint wn">' .
        mel_cgi_l('Логотип работает с прошивки r25. На этой Дюне — название текстом.',
        'The logo needs firmware r25 or newer; this Dune shows the title as text.')) . '</p>' .
        mel_cgi_toggle_block('cat_rating', $v);
}

// The value of the form of cat_rows of the settings (mel_cat_rows_ok): keys by spaces, none in a key.
function mel_cgi_cat_rows_of($keys)
{
    return is_array($keys) ? implode(' ', $keys) : '';
}

// The screen of the rows of the catalog (#rows): the list comes by the script
// (web/src/rows.ts), which keeps the choice in the field cat_rows, hidden but not of
// type hidden: the bar of the save counts it. No script or no list: it posts what is stored.
// cat_rows_was: the rows the page shows; the same posted back, the save keeps the stored ones
// (the dialog of the rows on the Dune may have changed them since).
// Two parts, as the tabs of the catalog: movies, series (with anime); the
// switch #crt shows one (by the script, as #dseg), the count on its button.
function mel_cgi_rows_screen($v)
{
    $f = mel_cgi_fields();
    return '<p class="sub">' . mel_cgi_l('Какие ряды показывать на вкладках каталога на Дюне и в каком порядке.',
        'Which rows the tabs of the catalog on the Dune show, and in what order.') . '</p>' .
        '<input type="text" id="' . $f['cat_rows']['id'] . '" name="' . $f['cat_rows']['name'] . '" value="' .
        mel_cgi_h($v['cat_rows']) . '" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" hidden>' .
        mel_cgi_hidden($f['cat_rows_was'], $v['cat_rows_was']) .
        '<div id="crs" class="res" aria-live="polite"><p class="hint">' .
        mel_cgi_l('Список рядов не открылся: нужен браузер с поддержкой скриптов.',
        'The list of rows did not open: a browser with scripts is needed.') . '</p></div>' .
        '<button type="button" id="crr" class="chk" hidden>' . mel_cgi_l('Повторить', 'Try again') . '</button>' .
        '<div id="crb" hidden><div id="crt" class="seg" role="group" aria-label="' . mel_cgi_l('Вкладка', 'Tab') .
        '"><button type="button" data-k="movie" aria-pressed="true">' . mel_cgi_l('Фильмы', 'Movies') . '<span></span></button>' .
        '<button type="button" data-k="series" aria-pressed="false">' . mel_cgi_l('Сериалы', 'Series') . '<span></span></button></div>' .
        mel_cgi_rows_part('crpm', 'crom', 'crgm', false) . mel_cgi_rows_part('crps', 'cros', 'crgs', true) . '</div>';
}

// A part of the screen of the rows: the rows shown in their order, all its rows by groups.
function mel_cgi_rows_part($id, $shown, $groups, $hidden)
{
    return '<div id="' . $id . '"' . ($hidden ? ' hidden' : '') . '><h3 class="gh">' . mel_cgi_l('Показывать', 'Shown') .
        '</h3><div id="' . $shown . '" class="list"></div><h3 class="gh">' . mel_cgi_l('Все ряды', 'All rows') .
        '</h3><div id="' . $groups . '"></div></div>';
}

// The screen "JacRed" (#voices): one of the built-in ones or the own one
// (radio "jacred"), a card each with a box for "Сравнить скорость" (span.ms
// data-jr: the id, "own"); the own one last, its fields under it
// (mel_cgi_ownf); "Проверить" there asks the own one. $bad: the fields of
// the errors of a save.
function mel_cgi_jacred($v, $bad = array())
{
    // By the source: the script swaps the texts of data-made / data-own.
    $t = array(mel_cgi_l('JacRed ищет русские раздачи и подсказывает озвучки.',
        'JacRed finds Russian releases and tells the voice-overs.'),
        mel_cgi_l('JacRed подсказывает озвучки и дорожки; раздачи ищет ваш конфиг.',
        'JacRed tells the voice-overs and tracks; your config finds the releases.'));
    $f = mel_cgi_fields();
    $k = $v['source'] === 'own' ? 1 : 0;
    // P2P on a server with Magnetio (not of mel_conf_p2p_torznab): Magnetio asks JacRed, not the server.
    $mg = mel_cgi_mode_sel($v) === MEL_MODE_P2P && !mel_conf_has_torznab($v['aio_server']);
    $jr403 = mel_cgi_jr403();
    $out = mel_cgi_editor_line($v) . '<p class="sub" id="jrp" data-made="' . mel_cgi_h($t[0]) . '" data-own="' . mel_cgi_h($t[1]) . '">' . $t[$k] . '</p>' .
        // The created config on a server jacred.stream refuses, jacred.stream chosen (the script follows both);
        // not when Magnetio asks.
        '<p class="hint wn" id="jr403"' . (!$k && !$mg && isset($jr403[$v['aio_server']]) &&
        $v['jacred'] === $jr403[$v['aio_server']] ? '' : ' hidden') . '>' .
        mel_cgi_l('jacred.stream не пускает выбранный сервер AIOStreams — выберите другой JacRed',
        'jacred.stream refuses the chosen AIOStreams server: choose another JacRed') . '</p><div class="choice">';
    $i = 0;
    foreach (array_keys(mel_jacred_builtin()) as $id)
    {
        $i++;
        $out .= mel_cgi_vradio("jrb$i", $f['jacred']['name'], $id, $v['jacred'] === $id, 'jrb',
            ' data-id="' . mel_cgi_h($id) . '"') . mel_cgi_card("jrb$i", mel_cgi_h($id) .
            ($id === MEL_JACRED_DEFAULT ? '<span class="badge">' . mel_cgi_l('у Melange', 'of Melange') . '</span>' : ''), '',
            '<span class="ms" data-jr="' . mel_cgi_h($id) . '"></span>');
    }
    $out .= mel_cgi_vradio('jrown', $f['jacred']['name'], 'own', $v['jacred'] === 'own', 'own') .
        mel_cgi_card('jrown', mel_cgi_l('Свой JacRed', 'Own JacRed'), '', '<span class="ms" data-jr="own"></span>') .
        mel_cgi_ownf(array($f['jo_url']['name'], $f['jo_key']['name']), $bad,
        mel_cgi_input($f['jo_url'], mel_cgi_l('Адрес', 'Address'), $v['jo_url'], 'http://192.168.1.10:9117',
            mel_cgi_chk('jcb', 'jcr')) .
        // The config made on a public server: it can't reach a home address (the script follows the choice);
        // nor Magnetio, whatever the server.
        '<p class="hint wn" id="jrlan"' . ($k || ($v['aio_server'] === 'own' && !$mg) ? ' hidden' : '') . '>' .
        mel_cgi_l('Адрес в домашней сети публичному серверу недоступен: нужен адрес из интернета',
        'A public server cannot reach a home network address: use an internet address') .
        '</p>' .
        mel_cgi_input($f['jo_key'], mel_cgi_l('Ключ, если нужен', 'Key, if needed'), $v['jo_key'], ''));
    // A hint under the list, not when the default itself is chosen (the script follows the choice).
    return $out . '</div><p class="hint" id="jrnote"' . ($v['jacred'] === MEL_JACRED_DEFAULT ? ' hidden' : '') . '>' .
        mel_cgi_l('Не ответил — раздач не будет; озвучки Melange спросит у ' . MEL_JACRED_DEFAULT . '.',
            'No answer - no releases; Melange asks ' . MEL_JACRED_DEFAULT . ' for voices.') .
        '</p><button type="button" id="jrsb" class="btn ghost">' . mel_cgi_l('Сравнить скорость', 'Compare speed') .
        '</button><p class="hint c">' . mel_cgi_l('Время ответа и число раздач на трёх фильмах',
        'Reply time and the number of releases on three films') . '</p><div id="jrsr" class="res" aria-live="polite"></div>';
}

// The fields $html of the own one of a choice (0.44.0), after its card (its
// radio of class own) in the list: the CSS shows them only while it is
// chosen, or (class open) while an error of a save is on one of $names (of
// $bad); without the CSS: always.
function mel_cgi_ownf($names, $bad, $html)
{
    return '<div class="ownf' . (array_intersect($names, $bad) ? ' open' : '') . '">' . $html . '</div>';
}

// AIOStreams server => id of the JacRed that answers 403 to it (0.35.0);
// data-jr403 of the server's radio.
function mel_cgi_jr403()
{
    return array('https://aiostreams.fortheweak.cloud' => 'jacred.stream');
}

// The templates of the Debrid mode (mel_tpl_debrid of common.php, 0.40.0):
// id => array(its name, its hint, 'short' => its short name for the main screen).
function mel_cgi_tpls()
{
    return array(
        'addons' => array(mel_cgi_l('Везде', 'Everywhere'), mel_cgi_l('JacRed, Torrentio, MediaFusion и другие — больше раздач в кэше Debrid.',
            'JacRed, Torrentio, MediaFusion and more: more releases cached in Debrid.'), 'short' => mel_cgi_l('везде', 'everywhere')),
        'jacred' => array(mel_cgi_l('Только русские трекеры', 'Russian trackers only'), mel_cgi_l(
            'JacRed: быстрее; с одним Real-Debrid раздач мало.', 'JacRed: faster; few releases with Real-Debrid alone.'),
            'short' => mel_cgi_l('только русские', 'Russian only')));
}

// The templates of the mode P2P (aio_tpl_p2p of common.php, 0.44.0), as mel_cgi_tpls().
function mel_cgi_tpls_p2p()
{
    return array(
        MEL_TPL_P2P => array(mel_cgi_l('Везде', 'Everywhere'), mel_cgi_l(
            'Torrentio, JacRed, TorrentsDB, Comet и StremThru Torz — больше раздач.',
            'Torrentio, JacRed, TorrentsDB, Comet and StremThru Torz: more releases.'), 'short' => mel_cgi_l('везде', 'everywhere')),
        MEL_TPL_P2P_JACRED => array(mel_cgi_l('Только русские трекеры', 'Russian trackers only'), mel_cgi_l(
            'JacRed: быстрее, раздачи русских трекеров.', 'JacRed: faster, releases of Russian trackers.'),
            'short' => mel_cgi_l('только русские', 'Russian only')));
}

// The template choice of the mode $m on the page (0.44.0: one per mode):
// field - the key of its radios in mel_cgi_fields() and the values, seen -
// that of its aio_tpl_seen, new - the key of the values with the one for a
// new config, tpls - its templates as mel_cgi_tpls().
function mel_cgi_tpl_keys($m)
{
    return $m === MEL_MODE_P2P ? array('field' => 'aio_tpl_p2p', 'seen' => 'tpl_p2p_seen', 'new' => 'tpl_p2p_new',
        'tpls' => mel_cgi_tpls_p2p()) : array('field' => 'aio_tpl', 'seen' => 'tpl_seen', 'new' => 'tpl_new', 'tpls' => mel_cgi_tpls());
}

// The name of the template $id: of the Debrid mode or P2P.
function mel_cgi_tpl_name($id)
{
    $tpls = mel_cgi_tpls() + mel_cgi_tpls_p2p();
    return isset($tpls[$id]) ? $tpls[$id][0] : '';
}

// The mode P2P on the page.
function mel_cgi_mode_p2p()
{
    return mel_cgi_l('Торренты', 'Torrents');
}

// The base of the AIOStreams server $server (aio_server; 'own' - at $own,
// mel_aio_own of it), the key of its configs in aio_confs ('' - none).
function mel_cgi_aio_base($server, $own)
{
    return $server === 'own' ? mel_aio_own($own) : $server;
}

// The template the page shows in the mode $m for the server of the base $b
// (mel_cgi_aio_base, mel_settings_base) with the configs $confs (aio_confs),
// 0.40.0: that of its config of the mode ('none' - not known yet), else $new
// (aio_tpl or aio_tpl_p2p, for a new config).
function mel_cgi_tpl_shown($m, $b, $confs, $new)
{
    if ($b === '' || !isset($confs[$b][$m]))
        return $new;
    return $confs[$b][$m]['tpl'] !== null ? $confs[$b][$m]['tpl'] : 'none';
}

// The mode the page shows: the one chosen in a form back, else aio_mode of
// the settings (one for all servers).
function mel_cgi_mode_sel($v)
{
    return in_array($v['aio_mode'], mel_aio_modes(), true) ? $v['aio_mode'] : $v['mode_cur'];
}

// The config made by melange on the chosen server (of the list or the own
// one) in the mode the page shows (mel_cgi_mode_sel), or null.
function mel_cgi_aio_sel($v)
{
    $b = mel_cgi_aio_base($v['aio_server'], $v['aio_own']);
    $m = mel_cgi_mode_sel($v);
    return $b !== '' && isset($v['confs'][$b][$m]) ? $v['confs'][$b][$m] : null;
}

// What the page shows of a config: the link to its web settings, login,
// password and its template (mel_aio_conf: null - not known yet).
function mel_cgi_aio_show($c)
{
    return $c ? array('cfg' => mel_manifest_base($c['manifest']) . 'configure', 'login' => $c['uuid'],
        'pass' => $c['pass'], 'tpl' => $c['tpl']) : null;
}

// The configs of the AIOStreams choice for the script (#init.confs): mode
// (mel_aio_modes) => value of an option => {cfg, login, pass, tpl} (tpl
// null: its template not known yet); that of "own" also with url, the
// address the config is of. A server without a config in a mode: none
// there. Objects even when empty.
function mel_cgi_aio_confs($v)
{
    $r = array();
    $own = mel_aio_own($v['aio_own']);
    foreach (mel_aio_modes() as $m)
    {
        $c = array();
        foreach (array_keys(mel_aio_servers()) as $url)
            if (isset($v['confs'][$url][$m]))
                $c[$url] = mel_cgi_aio_show($v['confs'][$url][$m]);
        if ($own !== '' && isset($v['confs'][$own][$m]))
            $c['own'] = mel_cgi_aio_show($v['confs'][$own][$m]) + array('url' => $own);
        $r[$m] = (object) $c;
    }
    return (object) $r;
}

// The marks of the template choice only shown, never chosen (0.40.0): id
// (mel_cgi_tpl_shown) => array as mel_cgi_tpls().
function mel_cgi_tpl_marks()
{
    return array(
        'custom' => array(mel_cgi_l('Свой набор аддонов (изменён в AIOStreams)', 'Custom set of addons (changed in AIOStreams)'),
            mel_cgi_l('Выберите шаблон, чтобы заменить им конфиг.', 'Choose a template to replace the config with it.'),
            'short' => mel_cgi_l('свой набор', 'custom set')),
        'none' => array(sprintf(mel_cgi_l('Не определён — нажмите «%s»', 'Unknown - press "%s"'), mel_cgi_saveb()),
            mel_cgi_l('Melange узнает шаблон конфига на сервере при сохранении.',
            'Melange detects the config template on the server when you save.'), 'short' => mel_cgi_l('шаблон не определён', 'template unknown')));
}

// The template chosen on the page in the mode $m (mel_cgi_tpl): one chosen
// in a form back, else the one shown (mel_cgi_tpl_shown), else the one for a new config.
function mel_cgi_tpl_sel($v, $m)
{
    $tk = mel_cgi_tpl_keys($m);
    $k = $tk['field'];
    $new = $v[$tk['new']];
    $shown = mel_cgi_tpl_shown($m, mel_cgi_aio_base($v['aio_server'], $v['aio_own']), $v['confs'], $new);
    $all = $tk['tpls'] + mel_cgi_tpl_marks();
    return isset($tk['tpls'][$v[$k]]) ? $v[$k] : (isset($all[$shown]) ? $shown : $new);
}

// "Где искать раздачи" - the template of the mode $m (0.40.0 Debrid, 0.44.0
// P2P: one each): a card of the radio aio_tpl / aio_tpl_p2p each (its name
// and hint; data-short: its name on the main screen), the chosen one: that of
// the config of the mode of the chosen server, else the one for a new config
// (tpl_new, tpl_p2p_new), unless one is chosen in a form back. A mark
// (mel_cgi_tpl_marks) is no card: none checked, so nothing posted, its name
// and hint above them (#<id>h, a line of each mark with its data-short, the
// one not shown hidden; the one for a new config: #init.tplNew); aio_tpl_seen,
// aio_tpl_p2p_seen: the one the page shows (another one on "Сохранить"
// resets the config there; changed by another tab since: the save is
// refused).
function mel_cgi_tpl($v, $m)
{
    $f = mel_cgi_fields();
    $tk = mel_cgi_tpl_keys($m);
    $k = $tk['field'];
    $id = $f[$k]['id'];
    $seen = $tk['seen'];
    $shown = mel_cgi_tpl_shown($m, mel_cgi_aio_base($v['aio_server'], $v['aio_own']), $v['confs'], $v[$tk['new']]);
    $sel = mel_cgi_tpl_sel($v, $m);
    $out = '<p class="lbl">' . mel_cgi_l('Где искать раздачи', 'Where to look for releases') . '</p><div id="' . $id . 'h">';
    foreach (mel_cgi_tpl_marks() as $mk => $x)
        $out .= '<p class="mark" data-mark="' . $mk . '" data-short="' . mel_cgi_h($x['short']) . '"' . ($mk === $sel ? '' : ' hidden') . '>' .
            '<span class="t">' . mel_cgi_h($x[0]) .
            '</span><span class="v">' . mel_cgi_h($x[1]) . '</span></p>';
    return $out . '</div>' . mel_cgi_choice($f[$k], $tk['tpls'], $sel) . mel_cgi_hidden($f[$seen], $shown);
}

// The answers of "Откуда брать фильмы" (radio watch): id of the radio => value; Lampac (v2) - a source of its own kind.
function mel_cgi_watches()
{
    return array('wd' => MEL_MODE_DEBRID, 'wp' => MEL_MODE_P2P, 'wo' => 'own', 'wl' => 'lampac');
}

// The answer the page shows: Lampac (watch of the values: a source of Lampac, mel_cgi_editor_values; a form back), the
// own config, else the mode (mel_cgi_mode_sel).
function mel_cgi_watch($v)
{
    if (isset($v['watch']) && $v['watch'] === 'lampac')
        return 'lampac';
    return $v['source'] === 'own' ? 'own' : mel_cgi_mode_sel($v);
}

// The host of an address ('' - none).
function mel_cgi_host($url)
{
    $h = parse_url($url, PHP_URL_HOST);
    return is_string($h) ? $h : '';
}

// The answers of "Основной Debrid" (radio debrid_main, 0.56.0): an id of
// mel_debrid_mains() => array(name, hint).
function mel_cgi_debrid_mains()
{
    $out = array();
    foreach (mel_debrid_mains() as $id => $name)
        $out[$id] = array($name, '');
    return $out;
}

// "Основной Debrid" under the keys of #mdeb (#dmain): shown with both keys
// saved (else hidden; the script shows it by the keys typed, debrid.ts). None
// checked: none chosen, the copy first in the reply plays. debrid_main_was:
// the one the page shows - the save writes data_dir/debrid_main only when
// another one is posted (MENU of a list on the Dune may have changed it since).
function mel_cgi_debrid_main($v)
{
    $f = mel_cgi_fields();
    return '<div id="dmain"' . ($v['rd'] !== '' && $v['tb'] !== '' ? '' : ' hidden') . '><p class="lbl">' .
        mel_cgi_l('Основной Debrid', 'Main Debrid') . '</p>' .
        mel_cgi_choice($f['debrid_main'], mel_cgi_debrid_mains(), $v['debrid_main']) . '<p class="hint">' .
        mel_cgi_l('Через какой сервис запускать раздачу, если она в кэше у обоих',
        'Which service plays a release cached on both') . '</p>' . mel_cgi_hidden($f['debrid_main_was'], $v['debrid_main_was']) .
        '</div>';
}

// The screen "Откуда брать фильмы" (0.43.0): the block of the sources at the top (mel_cgi_editor_block, v2), four cards
// of the radio watch (mel_cgi_form), under them the fields of the answer shown by the CSS
// (classes xd, xp, xo, xl, xm - Debrid, Торренты, the own config, Lampac, both made; xr - not Lampac; xt -
// not Торренты, of "Без торрентов" (#adv); xa - not with it, xn - only with it):
// Debrid - the keys (#dseg, #rdadd, #tbadd: by the script) and the template;
// Торренты - the template of P2P and a link to TorrServer (its field is on
// #torr only, 0.44.0); the own config - its manifest
// address, only read; Lampac - its address, token, "as Lampa" (mel_cgi_lampac_block). The server of both made: on #cfg
// (mel_cgi_cfg). Then "Сделать основным", "Удалить" or "+ Добавить источник" (mel_cgi_editor_foot).
function mel_cgi_src($v)
{
    $f = mel_cgi_fields();
    return mel_cgi_editor_block($v) . mel_cgi_watch_cards() .
        '<div id="mdeb" class="xd"><div id="dseg" class="seg" role="group" aria-label="' . mel_cgi_l('Сервис', 'Service') .
        '" hidden><button type="button" data-k="rd" aria-pressed="true">Real-Debrid</button>' .
        '<button type="button" data-k="tb" aria-pressed="false">TorBox</button></div><div id="rdbox">' .
        mel_cgi_input($f['rd'], mel_cgi_l('Ключ Real-Debrid', 'Real-Debrid key'), $v['rd'], mel_cgi_l('Где взять: ', 'Get it at ') .
            mel_cgi_link('https://real-debrid.com/apitoken', 'real-debrid.com/apitoken'), mel_cgi_chk('rdb', 'rdr')) .
        '</div><button type="button" id="tbadd" class="lnk" hidden>' . mel_cgi_l('+ Добавить TorBox', '+ Add TorBox') .
        '</button><div id="tbbox">' .
        mel_cgi_input($f['tb'], mel_cgi_l('Ключ TorBox', 'TorBox key'), $v['tb'], mel_cgi_l('Где взять: ', 'Get it at ') .
            mel_cgi_link('https://torbox.app/settings', 'torbox.app/settings'), mel_cgi_chk('tbb', 'tbr')) .
        '</div><button type="button" id="rdadd" class="lnk" hidden>' . mel_cgi_l('+ Добавить Real-Debrid', '+ Add Real-Debrid') .
        '</button>' . mel_cgi_debrid_main($v) . mel_cgi_tpl($v, MEL_MODE_DEBRID) . '</div>' .
        '<div id="mp2p" class="xp"><p class="hint">' . mel_cgi_l('Ключ не нужен: фильмы качаются с раздачи и играют через TorrServer.',
        'No key needed: films are fetched from the swarm and play via TorrServer.') . '</p>' . mel_cgi_tpl($v, MEL_MODE_P2P) .
        '<p class="hint" id="tslink"><a href="#torr">' . mel_cgi_l('Адрес TorrServer и проверка ›', 'TorrServer address and check ›') .
        '</a></p></div>' .
        '<div id="sown" class="xo">' . mel_cgi_input($f['manifest'], mel_cgi_l('Ссылка на конфиг', 'Config link'), $v['manifest'],
        mel_cgi_l('Где взять: Save & Install → Stremio → Direct Manifest URL.',
        'Get it at: Save & Install → Stremio → Direct Manifest URL.'), mel_cgi_chk('mb', 'mr')) .
        '<p class="hint">' . mel_cgi_l('Melange этот конфиг не меняет.', 'Melange does not change this config.') .
        '</p><p class="hint xa">' . mel_cgi_l('Торренты из него играют через ', 'Torrents of it play via ') .
        '<a href="#torr">TorrServer</a>.</p></div>' . mel_cgi_lampac_block($v) . mel_cgi_editor_foot($v);
}

// The screen "AIOStreams" (#cfg, 0.44.0; its fields: Debrid and
// Торренты, #smade of class xm): the server melange makes the config on - a
// card of the radio aio_server each (the host; "у Melange" of
// MEL_AIO_SERVER_MELANGE; "без TMDB" of one without TMDB of its own; data-tmdb,
// data-jr403, data-p2ptz for the script), the own one last (radio "own",
// its address under it: mel_cgi_ownf); the TMDB key under a
// server without TMDB of its own (another one: always); then the config of
// the chosen server in the mode (#acfg) - its link, login and password,
// "Сбросить к шаблону" - or "none yet"; the script shows the one of the
// chosen server and mode from #init.confs (mel_cgi_aio_confs). $bad: the
// fields of the errors of a save.
function mel_cgi_cfg($v, $bad = array())
{
    $f = mel_cgi_fields();
    $name = $f['aio_server']['name'];
    $jr403 = mel_cgi_jr403();
    $out = mel_cgi_editor_line($v) . '<p class="hint xo" id="cfgown">' . mel_cgi_l('Свой конфиг Melange не меняет.',
        'Melange does not change your own config.') .
        '</p><div id="smade" class="xm"><div class="choice">';
    $i = 0;
    foreach (mel_aio_servers() as $url => $tmdb)
    {
        $i++;
        $out .= mel_cgi_vradio("as$i", $name, $url, $v['aio_server'] === $url, '', ' data-tmdb="' . ($tmdb ? '1' : '0') . '"' .
            (isset($jr403[$url]) ? ' data-jr403="' . mel_cgi_h($jr403[$url]) . '"' : '') .
            (mel_conf_has_torznab($url) ? ' data-p2ptz="1"' : '')) .
            mel_cgi_card("as$i", mel_cgi_h(mel_cgi_host($url)) . ($url === MEL_AIO_SERVER_MELANGE ? '<span class="badge">' .
            mel_cgi_l('у Melange', 'of Melange') . '</span>' : ''), $tmdb ? '' : mel_cgi_l('без TMDB', 'no TMDB'));
    }
    $sel = mel_cgi_aio_show(mel_cgi_aio_sel($v));
    return $out . mel_cgi_vradio('asown', $name, 'own', $v['aio_server'] === 'own', 'own', ' data-tmdb="0"') .
        mel_cgi_card('asown', mel_cgi_l('Свой сервер', 'Own server'), '') .
        mel_cgi_ownf(array($f['aio_own']['name']), $bad, mel_cgi_input($f['aio_own'],
        mel_cgi_l('Адрес сервера AIOStreams', 'AIOStreams server address'), $v['aio_own'], 'http://192.168.1.10:3000',
        mel_cgi_chk('aob', 'aor'))) . '</div>' .
        '<div id="tmbox"><p class="hint" id="tmnote">' .
        mel_cgi_l('Без своего TMDB: без сверки названий, меньше раздач сериалов.',
        'The server has no TMDB of its own: titles are not matched, fewer series releases.') . '</p>' .
        mel_cgi_input($f['tmdb'], mel_cgi_l('Ключ TMDB (необязательно)', 'TMDB key (optional)'), $v['tmdb'],
        mel_cgi_link('https://www.themoviedb.org/settings/api', 'themoviedb.org') .
        mel_cgi_l(' → Settings → API, ключ v3', ' → Settings → API, the v3 key'), mel_cgi_chk('tmb', 'tmr')) . '</div>' .
        '<h3 class="gh">' . mel_cgi_l('Конфиг в AIOStreams', 'Config in AIOStreams') . '</h3><p class="sub">' .
        mel_cgi_l('Для опытных: аддоны и фильтры — в веб-настройках AIOStreams.',
        'For the experienced: addons and filters are in the AIOStreams web settings.') . '</p>' .
        '<div id="acfg"><p class="hint" id="anone"' . ($sel ? ' hidden' : '') . '>' .
        mel_cgi_l('Конфига на этом сервере ещё нет: создастся при сохранении.',
        'No config on this server yet: saving will create one.') . '</p>' .
        '<div id="ahave"' . ($sel ? '' : ' hidden') . '><p class="cfg"><a id="alink" class="btn ghost"' . ($sel ? ' href="' .
        mel_cgi_h($sel['cfg']) . '"' : '') . ' target="_blank" rel="noreferrer">' .
        mel_cgi_l('Открыть настройки AIOStreams', 'Open the AIOStreams settings') .
        '</a></p>' . mel_cgi_plain('al', '', mel_cgi_l('Логин', 'Login'), $sel ? $sel['login'] : '', '', 36) .
        mel_cgi_field('ap', '', mel_cgi_l('Пароль', 'Password'), $sel ? $sel['pass'] : '', mel_cgi_l(
        'Вход в настройки AIOStreams — по этому логину и паролю.', 'Log in to the AIOStreams settings with this login and password.'),
        24, mel_cgi_chk('apc', 'apr', mel_cgi_l('Копировать', 'Copy'))) .
        // A submit without the script: the save, then the reset (0.40.0).
        '<button type="submit" id="arb" class="btn danger" name="aio_reset" value="1">' .
        mel_cgi_resetb() . '</button><p class="hint">' .
        mel_cgi_l('Ваши правки в AIOStreams пропадут; логин, пароль и ссылка останутся.',
        'Your edits in AIOStreams are lost; the login, password and link stay.') . '</p></div></div></div>';
}

// The style of the page: cgi/settings.css inline (none readable: the page without it).
function mel_cgi_css()
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
// config of each mode (tplNew), the source profiles (profiles: mel_cgi_profiles_data), the hidden titles (hidden:
// mel_cgi_ht_data of $v['hidden'], settings.php puts it; none - an empty list); the script is
// cgi/settings.js, built from web/ (none readable:
// the page without it).
function mel_cgi_js($v)
{
    $again = mel_cgi_l('%s — нажмите «%s» ещё раз', '%s: press "%s" again');
    $t = array(
        'duplicate' => mel_cgi_l('Дублировать', 'Duplicate'),
        'serversFull' => mel_cgi_l('Все 5 блоков заняты', 'All 5 server slots are in use'),
        'copySuffix' => mel_cgi_l(' (копия)', ' (copy)'),
        'server' => mel_cgi_l('Сервер', 'Server'),
        'checking' => mel_cgi_l('Проверяю…', 'Checking…'), 'saving' => mel_cgi_l('Сохраняю…', 'Saving…'),
        'save' => mel_cgi_saveb(), 'saved' => mel_cgi_saved(), 'rowsLater' => mel_cgi_rows_later(),
        'saveAndCreate' => mel_cgi_savec(),
        'savedOnDune' => mel_cgi_l('Настройки Melange сохранены на Дюне.', 'Melange settings are saved on the Dune.'),
        'creating' => mel_cgi_l('Создаю конфиг…', 'Creating the config…'),
        'updating' => mel_cgi_l('Обновляю конфиг…', 'Updating the config…'),
        'resetting' => mel_cgi_l('Сбрасываю конфиг…', 'Resetting the config…'),
        'notCreated' => sprintf($again, mel_cgi_not_done('create'), mel_cgi_saveb()),
        'notUpdated' => sprintf($again, mel_cgi_not_done('update'), mel_cgi_saveb()),
        'notReset' => sprintf($again, mel_cgi_not_done('reset'), mel_cgi_resetb()),
        'resetConfirm' => mel_cgi_l('Конфиг на %s будет заменён шаблоном «%s». Ваши правки в веб-настройках AIOStreams ' .
            'пропадут; логин, пароль и ссылка останутся. Продолжить?', 'The config on %s will be replaced with the ' .
            'template "%s". Your edits in the AIOStreams web settings will be lost; the login, password and link stay. ' .
            'Continue?'),
        'modeP2p' => mel_cgi_mode_p2p(),
        'passCopied' => mel_cgi_l('Пароль скопирован', 'Password copied'),
        'passNotCopied' => mel_cgi_l('Не скопировалось — пароль показан, скопируйте вручную',
            'Not copied: the password is shown, copy it by hand'),
        'noConnection' => mel_cgi_l('Нет связи с Дюной: проверьте, что телефон в той же сети, и нажмите ещё раз',
            'No connection to the Dune: check that the phone is on the same network and try again'),
        'timeout' => mel_cgi_l('Дюна не ответила за %d с — повторите', 'The Dune did not answer within %d s: try again'),
        'linkExpired' => mel_cgi_l('Ссылка устарела: откройте настройки заново по QR-коду на Дюне',
            'The link is out of date: open the settings again via the QR code on the Dune'),
        'badChars' => mel_cgi_l('В поле испорченный символ — сотрите его и введите заново',
            'A broken character in the field: delete it and type it again'),
        'httpError' => mel_cgi_l('Ошибка Дюны (HTTP %d), попробуйте ещё раз', 'Dune error (HTTP %d), try again'),
        'jacredFormat' => mel_cgi_fmt_jacred(),
        'releases' => mel_cgi_l('раздач', 'releases'), 'noAnswer' => mel_cgi_l('нет ответа', 'no answer'),
        'yes' => mel_cgi_l('да', 'yes'),
        'no' => mel_cgi_l('нет', 'no'), 'seconds' => mel_cgi_l('с', 's'), 'stopped' => mel_cgi_l('прервано', 'stopped'),
        // The count of the changes by "Сохранить": the forms of 1, 2-4 and 5 (English: 1 and the rest).
        'changeOne' => mel_cgi_l('%d изменение', '%d change'), 'changeFew' => mel_cgi_l('%d изменения', '%d changes'),
        'changeMany' => mel_cgi_l('%d изменений', '%d changes'),
        // The screen of the rows of the catalog.
        'rowsLoading' => mel_cgi_l('Загружаю список рядов…', 'Loading the list of rows…'),
        'rowsNoList' => mel_cgi_l('Сервер каталога не ответил, списка рядов нет. Сохранённый выбор не изменился.',
            'The catalog server did not answer, there is no list of rows. The saved choice has not changed.'),
        'rowsStale' => mel_cgi_l('Сервер каталога не ответил: список рядов из последней копии на Дюне.',
            'The catalog server did not answer: the list of rows is the last copy on the Dune.'),
        'rowsLastMovie' => mel_cgi_l('Должен остаться хотя бы один ряд фильмов: сначала включите другой.',
            'At least one row of movies must stay: turn on another one first.'),
        'rowsLastSeries' => mel_cgi_l('Должен остаться хотя бы один ряд сериалов: сначала включите другой.',
            'At least one row of series must stay: turn on another one first.'),
        'rowsTop' => mel_cgi_l('В начало', 'To the top'), 'rowsUp' => mel_cgi_l('Выше', 'Up'),
        'rowsDown' => mel_cgi_l('Ниже', 'Down'), 'rowsBottom' => mel_cgi_l('В конец', 'To the bottom'),
        'rowsGroupAll' => mel_cgi_l('Все ряды группы', 'All rows of the group'),
        // The rows of Melange (mel_cat_pseudo): their names as on the Dune, their group.
        'rowContinue' => mel_cgi_l('Продолжить', 'Continue Watching'), 'rowMylist' => mel_cgi_l('Избранное', 'Favorites'),
        'rowTorrserver' => mel_cgi_l('На TorrServer', 'On TorrServer'), 'groupOwn' => mel_cgi_l('Ряды Melange', 'Melange rows'),
        'groupTop' => mel_cgi_l('Топы', 'Top'), 'groupFresh' => mel_cgi_l('Новинки с переводом', 'Fresh releases'),
        'groupRu' => mel_cgi_l('Российское', 'Russian'),
        'groupRuServices' => mel_cgi_l('Российские сервисы', 'Russian services'), 'groupGenres' => mel_cgi_l('Жанры', 'Genres'),
        'groupTheme' => mel_cgi_l('Темы', 'Themes'), 'groupStudios' => mel_cgi_l('Студии', 'Studios'),
        'groupDecades' => mel_cgi_l('Десятилетия', 'Decades'), 'groupYears' => mel_cgi_l('Годы', 'Years'),
        'groupStreaming' => mel_cgi_l('Стриминги', 'Streaming'), 'groupWorld' => mel_cgi_l('Страны', 'Countries'),
        'groupRuntime' => mel_cgi_l('Длительность', 'Runtime'), 'groupAnime' => mel_cgi_l('Аниме', 'Anime'),
        'groupOther' => mel_cgi_l('Другое', 'Other'),
        // The import of a file of the settings (lib/backup.php).
        'importConfirm' => mel_cgi_l('Текущие настройки будут заменены настройками из файла. Продолжить?',
            'The current settings will be replaced with those from the file. Continue?'),
        // The file holds another link of the page (web_token, 0.51.0).
        'importConfirmLink' => mel_cgi_l('Текущие настройки будут заменены настройками из файла, ссылка на эту страницу — ссылкой из файла: текущая перестанет работать. Продолжить?',
            'The current settings will be replaced with those from the file, the link of this page with the one from the file: the current link will stop working. Continue?'),
        'importing' => mel_cgi_l('Загружаю…', 'Loading…'),
        'importTooBig' => mel_cgi_import_big(),
        'importUnread' => mel_cgi_l('Файл не прочитался — выберите его ещё раз', 'The file could not be read: choose it again'),
        // "Скрытые" of "Фильтр каталога" (lib/hidden.php): the line of an empty list, the button of a title.
        'hiddenNone' => mel_cgi_l('Скрытых нет.', 'Nothing hidden.'), 'hiddenReturn' => mel_cgi_l('Вернуть', 'Unhide')) +
        // The lines of the main screen (mel_cgi_rows), the source profiles (lib/profiles.php).
        mel_cgi_home_texts() + mel_cgi_profile_texts();
    $js = dirname(dirname(__FILE__)) . '/settings.js';
    $js = is_readable($js) ? file_get_contents($js) : false;
    if (!is_string($js))
        return '';
    $init = json_encode(array('i18n' => $t, 'jacredDefault' => MEL_JACRED_DEFAULT,
        'tplNew' => array(MEL_MODE_DEBRID => $v['tpl_new'], MEL_MODE_P2P => $v['tpl_p2p_new']), 'confs' => mel_cgi_aio_confs($v),
        'profiles' => $v['prof'], 'hidden' => isset($v['hidden']) ? $v['hidden'] : mel_cgi_ht_data('')));
    // JSON_HEX_TAG for "<" (PHP 5.3 has none): no "</script>" or "<!--" in the element; "<" is only in strings.
    return '<script type="application/json" id="init">' . str_replace('<', '\\u003c', $init) . '</script><script>' .
        $js . '</script>';
}

// The labels of the buttons, quoted in other texts as well.
function mel_cgi_saveb()
{
    return mel_cgi_l('Сохранить', 'Save');
}

// The main button while the chosen server has no config.
function mel_cgi_savec()
{
    return mel_cgi_l('Сохранить и создать конфиг', 'Save and create the config');
}

function mel_cgi_resetb()
{
    return mel_cgi_l('Сбросить к шаблону', 'Reset to the template');
}

// The sync with the AIOStreams server failed; $what: create, update or reset.
function mel_cgi_not_done($what)
{
    $t = array('create' => array('не создан', 'created'), 'update' => array('не обновлён', 'updated'),
        'reset' => array('не сброшен', 'reset'));
    return mel_cgi_l('Конфиг на сервере AIOStreams ' . $t[$what][0], 'The config on the AIOStreams server was not ' . $t[$what][1]);
}

function mel_cgi_saved()
{
    return mel_cgi_l('Сохранено. Дюна подхватит настройки сама.', 'Saved. The Dune picks up the settings automatically.');
}

// After "Saved" when the rows of the catalog changed (?rows=1, "rows" of the JSON of a save).
function mel_cgi_rows_later()
{
    return mel_cgi_l('Ряды обновятся при следующем открытии каталога.', 'The rows change the next time the catalog opens.');
}

// $v: mel_cgi_values() or mel_cgi_posted() (first: no saved_at yet, not
// saved_once of mel_settings_read); $errors: of mel_cgi_err(); $imported:
// after the import of a file (a=import), its line in #msg (2: and that the
// link of the file was not written); $rows: with $saved,
// the rows of the catalog changed (mel_cgi_rows_later).
// The page (0.43.0): the radios of watch and no_torrents, then the screens, #home
// last - the CSS shows the one of the address (#src...), else #home, the fields
// of the answer chosen and, with "Без торрентов" in effect, #home without the
// lines of TorrServer and the servers (without the script too); the bar of the
// save under them (#bar: the script shows it on a change, #dirty - their
// number, #srx - "Скрыть" of the results of a save). The page is the editor of a source (v2, lib/profiles.php): its
// block on #src, its line on #cfg and #voices with two sources or more. Errors without the script: in #msg, each with a link to the screen of its field, the field
// aria-invalid.
function mel_cgi_form($t, $v, $errors, $saved, $imported = 0, $rows = false)
{
    $scr = mel_cgi_screens();
    $msg = '';
    $bad = array();
    foreach ($errors as $e)
    {
        $bad[] = $e['field'];
        $to = mel_cgi_err_screen($e['field']);
        $msg .= '<p class="err">' . mel_cgi_h($e['msg']) . ($to !== '' ? ' <a href="#' . $to . '">' . $scr[$to] . ' ›</a>' : '') .
            '</p>';
    }
    if ($saved)
        $msg .= '<p class="ok">' . mel_cgi_saved() . '</p>';
    if ($saved && $rows)
        $msg .= '<p class="ok">' . mel_cgi_rows_later() . '</p>';
    if ($imported)
        $msg .= '<p class="ok">' . mel_cgi_l('Настройки загружены из файла. Дюна подхватит их сама.',
            'Settings loaded from the file. The Dune picks them up automatically.') . '</p>';
    if ($imported === 2)
        $msg .= '<p class="warn">' . mel_cgi_l('Ссылку на страницу из файла вернуть не удалось — она осталась прежней.',
            'The link of the page from the file could not be restored: it stays as it was.') . '</p>';
    $title = mel_cgi_l('Melange — настройки', 'Melange settings');
    $f = mel_cgi_fields();
    $w = mel_cgi_watch($v);
    $radios = '';
    foreach (mel_cgi_watches() as $id => $x)
        $radios .= mel_cgi_vradio($id, $f['watch']['name'], $x, $w === $x);
    $tg = mel_cgi_toggles();
    $i = 0;
    foreach (array_keys($tg['no_torrents'][1]) as $x)
        $radios .= mel_cgi_vradio($f['no_torrents']['id'] . ++$i, $f['no_torrents']['name'], $x, $v['no_torrents'] === $x);
    $form = '<form method="post" action="settings?t=' . mel_cgi_h($t) . '" autocomplete="off">' .
        // Enter in a field without the script submits the first button: the save, not the reset.
        '<button type="submit" class="dflt" tabindex="-1" aria-hidden="true"></button>' . $radios .
        mel_cgi_screen('src', mel_cgi_src($v)) .
        mel_cgi_screen('cfg', mel_cgi_cfg($v, $bad)) .
        mel_cgi_screen('voices', mel_cgi_jacred($v, $bad)) .
        mel_cgi_screen('torr', '<p class="sub xd">' . mel_cgi_l('Для раздач не из кэша Debrid: MENU → «Смотреть через TorrServer».',
            'For releases not cached in Debrid: MENU → "Watch via TorrServer".') . '</p><p class="sub xp">' .
            mel_cgi_l('С ответом «Торренты» без него не посмотреть.', 'With "Torrents" nothing plays without it.') . '</p>' .
            '<p class="sub xo" id="tsown">' . mel_cgi_l('Торренты из вашего конфига играют через TorrServer.',
            'Torrents of your config play via TorrServer.') . '</p>' .
            mel_cgi_input($f['ts'], mel_cgi_l('Адрес TorrServer', 'TorrServer address'), $v['ts'],
                mel_cgi_l('Приложение на этой Дюне', 'The app on this Dune'), mel_cgi_chk('tsb', 'tsr')) .
            '<p class="hint">' . mel_cgi_l('Пусто — приложение TorrServe на Дюне; свой — http://хост:порт',
            'Empty - the TorrServe app on the Dune; your own - http://host:port') . '</p>' .
            mel_cgi_input($f['ts_user'], mel_cgi_l('Логин', 'Login'), $v['ts_user'], '') .
            mel_cgi_input($f['ts_pass'], mel_cgi_l('Пароль', 'Password'), $v['ts_pass'],
                mel_cgi_l('Если TorrServer просит вход. Можно и в адресе: логин:пароль@хост:порт',
                'If TorrServer asks for them. Also in the address: login:password@host:port')) . mel_cgi_ts_save($v)) .
        mel_cgi_screen('rows', mel_cgi_rows_screen($v)) .
        mel_cgi_screen('look', mel_cgi_look_screen($v)) .
        mel_cgi_screen('hide', mel_cgi_hide_screen($v)) .
        mel_cgi_screen('dl', '<p class="sub">' . mel_cgi_l('На Дюне в списке раздач: Меню → «Скачать на сервер».',
            'On the Dune, in the list of releases: Menu → "Download to server".') . '</p>' . mel_cgi_servers($v['servers'])) .
        mel_cgi_screen('adv', mel_cgi_toggle_cards('no_torrents', 'xt') . mel_cgi_markers_block($v) . mel_cgi_toggle_block('source_errors', $v) .
            mel_cgi_toggle_block('no_color_keys', $v)) .
        mel_cgi_screen('bak', mel_cgi_backup($t, $v)) .
        mel_cgi_screen('log', '<p class="sub">' . mel_cgi_l('С последнего включения Дюны; адреса и ключи скрыты — можно переслать разработчику.',
            'Since the Dune was last switched on; addresses and keys hidden: fine to send to the developer.') . '</p>' .
            '<a class="btn" href="settings?t=' . mel_cgi_h($t) . '&amp;a=log">' . mel_cgi_l('Скачать лог', 'Download the log') . '</a>') .
        mel_cgi_screen('about', mel_cgi_about($v)) .
        '<section id="home" class="scr">' . mel_cgi_home($v) . '</section>' .
        '<div id="bar"><div id="sr" class="res" aria-live="polite"></div><button type="button" id="srx" class="lnk" hidden>' .
        mel_cgi_l('Скрыть', 'Hide') . '</button><div class="barrow"><span id="dirty"></span>' .
        '<button type="submit" id="save" class="save">' . (mel_cgi_state($v) === 'conf' ? mel_cgi_savec() :
        mel_cgi_saveb()) .
        '</button></div></div></form>';
    // The fields of the errors marked (a radio of watch or jacred: each one).
    foreach ($errors as $e)
        if ($e['field'] !== null)
            $form = str_replace(' name="' . $e['field'] . '"', ' name="' . $e['field'] . '" aria-invalid="true"', $form);
    return '<!DOCTYPE html><html lang="' . mel_cgi_l('ru', 'en') . '"><head><meta charset="utf-8">' .
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' .
        '<meta name="color-scheme" content="light dark">' .
        '<meta name="referrer" content="no-referrer"><title>' . $title . '</title>' . mel_cgi_css() . '</head><body><main>' .
        '<div id="msg" aria-live="polite">' . $msg . '</div>' . $form . '</main>' . mel_cgi_js($v) . '</body></html>';
}
