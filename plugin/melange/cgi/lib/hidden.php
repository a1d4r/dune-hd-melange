<?php
// The settings page (cgi/settings.php): the screen "Что не показывать" (#hide, 0.65.0) - the age of the catalog
// (cat_age, a radio of the form: mel_cgi_cat_ages) and "Скрытые", the titles hidden on the Dune (POP UP → «Скрыть»,
// data_dir/hidden_titles.json of hidden_titles.php), each with "Вернуть" (a=title_unhide, by the script only); the copy
// of the list in the backup (the field hidden_titles of the export, lib/backup.php). The file is written by the plugin
// too, with no lock (an action of the remote and one of the phone in the same instant may lose one): "Вернуть" reads
// it anew and takes one title out.

require_once dirname(dirname(dirname(__FILE__))) . '/hidden_titles.php';

// The field of the copy in the file of the export and its version: {"v": 1, "items": [[type, name, year, IMDb, TMDB,
// hidden]...]}, the newest first.
define('MEL_CGI_HT_FIELD', 'hidden_titles');
define('MEL_CGI_HT_COPY_V', 1);

// The answers of the age (radio cat_age): its value => array(name, hint, the value of the line on the main screen
// (data-short)); 'all' - cat_age null, else the age itself (mel_settings_parse: 16, 12, 6, 0).
function mel_cgi_cat_ages()
{
    return array(
        'all' => array(mel_cgi_l('Все', 'All'), mel_cgi_l('Возраст не проверяется.', 'The age is not checked.'),
            mel_cgi_l('Все возрасты', 'All ages')),
        '16' => array(mel_cgi_l('До 16+', 'Up to 16+'), mel_cgi_l('Скрыты 18+.', '18+ hidden.'), mel_cgi_l('До 16+', 'Up to 16+')),
        '12' => array(mel_cgi_l('До 12+', 'Up to 12+'), mel_cgi_l('Скрыты 16+ и 18+.', '16+ and 18+ hidden.'),
            mel_cgi_l('До 12+', 'Up to 12+')),
        '6' => array(mel_cgi_l('До 6+', 'Up to 6+'), mel_cgi_l('Скрыты 12+, 16+ и 18+.', '12+, 16+ and 18+ hidden.'),
            mel_cgi_l('До 6+', 'Up to 6+')),
        '0' => array(mel_cgi_l('Только 0+', '0+ only'), mel_cgi_l('Скрыто всё старше 0+.', 'All over 0+ hidden.'),
            mel_cgi_l('Только 0+', '0+ only')));
}

// The answer of mel_cgi_cat_ages() of cat_age of the settings (an int, null - all).
function mel_cgi_cat_age_of($a)
{
    return $a === null ? 'all' : strval($a);
}

// cat_age of the settings of answer $x of the form (mel_cgi_cat_ages; another one: null, the check refuses it).
function mel_cgi_cat_age_set($x)
{
    return $x !== 'all' && array_key_exists($x, mel_cgi_cat_ages()) ? intval($x) : null;
}

// Why the list of the file is not taken (mel_ht_read_file gave null, $why): the line of the page. A broken file
// (mel_ht_broken): php_server renames it to .bad at its next read and starts a new list (mel_ht_list); the page only
// reads it.
function mel_cgi_ht_locked($why)
{
    if (strpos($why, 'newer') !== false)
        return mel_cgi_l('Список скрытых записан более новой версией Melange: обновите плагин',
            'The hidden list is from a newer version of Melange: update the plugin');
    return mel_ht_broken($why) ? mel_cgi_l('Список скрытых повреждён: Дюна сохранит его копию и начнёт новый',
        'The hidden list is damaged: the Dune keeps a copy and starts a new one') :
        mel_cgi_l('Список скрытых на Дюне не читается', 'The hidden list on the Dune cannot be read');
}

// The list $list for the script: a title each - its key (k, posted back by "Вернуть"), its name, the line under it
// (Фильм or Сериал, the year).
function mel_cgi_ht_items($list)
{
    $out = array();
    foreach ($list as $e)
    {
        $kind = $e['type'] === 'series' ? mel_cgi_l('Сериал', 'Series') : mel_cgi_l('Фильм', 'Movie');
        $out[] = array('k' => $e['key'], 'name' => $e['name'], 'line' => $kind . ($e['year'] !== '' ? ', ' . $e['year'] : ''));
    }
    return $out;
}

// #init.hidden of the page: {items (mel_cgi_ht_items), msg: '' or why there is no list (mel_cgi_ht_locked: of a later
// Melange, not readable, broken)}. No file: none hidden, no msg.
function mel_cgi_ht_data($dir)
{
    $why = '';
    $list = mel_cgi_dir_ok($dir) ? mel_ht_read_file("$dir/" . MEL_HT_FILE, $why) : array();
    if ($why !== '')
        mel_cgi_log('form: ' . MEL_HT_FILE . " $why");
    return array('items' => $list !== null ? mel_cgi_ht_items($list) : array(), 'msg' => $list !== null ? '' : mel_cgi_ht_locked($why));
}

// The screen "Что не показывать" (#hide): the age (a value of the form: '' or another one - none checked), where it and
// the hidden act; "Скрытые": the list made by the script (web/src/hidden.ts) from #init.hidden, its lines in #htr
// (without the script: why there is none).
function mel_cgi_hide_screen($v)
{
    $f = mel_cgi_fields();
    return '<p class="sub">' . mel_cgi_l('Действует в рядах каталога, «Показать все», «ТВ» и «Похожих». «Продолжить» и «Избранное» не меняются.',
        'Applies to the catalog rows, "Show all", "TV" and "Similar". "Continue Watching" and "Favorites" stay as they are.') . '</p>' .
        '<p class="lbl">' . mel_cgi_l('Возраст', 'Age rating') . '</p>' . mel_cgi_choice($f['cat_age'], mel_cgi_cat_ages(), $v['cat_age']) .
        '<p class="hint">' . mel_cgi_l('Возраст проверяется и в поиске. Тайтлы без возраста видны.',
        'Search results are filtered too. Titles with no age rating stay visible.') . '</p>' .
        '<p class="lbl">' . mel_cgi_l('Скрытые', 'Hidden') . '</p><div id="htl" class="list"></div>' .
        '<div id="htr" class="res" aria-live="polite"><p class="hint">' .
        mel_cgi_l('Список не открылся: нужен браузер с поддержкой скриптов.', 'The list did not open: a browser with scripts is needed.') .
        '</p></div><p class="hint">' . mel_cgi_l('Скрыть тайтл: на Дюне POP UP на постере → «Скрыть». В поиске он виден.',
        'To hide: POP UP on a poster on the Dune → "Hide". Search still shows it.') . '</p>';
}

// The answer of a=title_unhide: JSON {ok, msg[, items: the list of the file now (mel_cgi_ht_items)]}.
function mel_cgi_ht_answer($ok, $msg, $list)
{
    mel_cgi_json(200, array('ok' => $ok, 'msg' => $msg) + ($list !== null ? array('items' => mel_cgi_ht_items($list)) : array()));
}

// POST a=title_unhide&ajax=1 (k: the key of a title, mel_ml_key) -> the file read now (the Dune may have hidden
// more since the page came), that title out, written whole (mel_cgi_write: by rename, php_server reads it anew by
// mel_file_sig) -> {ok: true, msg, items}. No such title (out already): ok, nothing written. A file not taken (of a
// later Melange, not readable, broken: mel_cgi_ht_locked) or no data dir: refused, nothing written, no list; not
// written: refused, the list of the file.
function mel_cgi_ht_unhide_post($dir)
{
    if (!mel_cgi_dir_ok($dir))
    {
        mel_cgi_log('title_unhide: no data dir, nothing written');
        mel_cgi_ht_answer(false, mel_cgi_l('Не сохранено: нет папки плагина на Дюне',
            'Not saved: the plugin data folder is missing on the Dune'), null);
    }
    $list = mel_ht_read_file("$dir/" . MEL_HT_FILE, $why);
    if ($list === null)
    {
        mel_cgi_log('title_unhide: ' . MEL_HT_FILE . " $why, nothing written");
        mel_cgi_ht_answer(false, mel_cgi_ht_locked($why), null);
    }
    $k = mel_cgi_param($_POST, 'k');
    $out = mel_ht_without($list, $k);
    if (count($out) === count($list))
    {
        mel_cgi_log('title_unhide: no such title, nothing written');
        mel_cgi_ht_answer(true, '', $list);
    }
    $name = '';
    foreach ($list as $e)
        if ($e['key'] === $k)
            $name = $e['name'];
    $json = mel_ht_json($out);
    $why = $json !== '' ? mel_cgi_write($dir, $json, MEL_HT_FILE) : 'json_encode failed';
    if ($why !== '')
    {
        mel_cgi_log("title_unhide: $why, nothing written");
        mel_cgi_ht_answer(false, mel_cgi_l('Не сохранено: ошибка записи на Дюне', 'Not saved: write error on the Dune'), $list);
    }
    mel_cgi_log('title_unhide: ok, ' . count($out) . ' hidden');
    mel_cgi_ht_answer(true, sprintf(mel_cgi_l('«%s» снова в каталоге', '"%s" is back in the catalog'), $name), $out);
}

// --- The backup.

// The copy of list $list in the file of the export: the fields of each entry in a row.
function mel_cgi_ht_copy($list)
{
    $items = array();
    foreach ($list as $e)
        $items[] = array($e['type'], $e['name'], $e['year'], $e['imdb_id'], $e['_tmdbId'], $e['hidden']);
    return array('v' => MEL_CGI_HT_COPY_V, 'items' => $items);
}

// The field of the copy of list $list, ',"hidden_titles":{…}', to go before the last "}" of the export ('': json_encode
// failed).
function mel_cgi_ht_copy_part($list)
{
    $c = json_encode(mel_cgi_ht_copy($list));
    return is_string($c) ? ',"' . MEL_CGI_HT_FIELD . '":' . $c : '';
}

// The copy $c of a file of the export (as json_decode gives it) -> its list (mel_ht_items: a title not good, one twice,
// over MEL_HT_MAX dropped, counted in $dropped), null for none: $why 'v <n> newer' (of a later Melange), 'not a list'.
function mel_cgi_ht_from_copy($c, &$why, &$dropped)
{
    $why = '';
    $dropped = 0;
    $v = is_array($c) && isset($c['v']) ? $c['v'] : null;
    if (is_int($v) && $v > MEL_CGI_HT_COPY_V)
    {
        $why = "v $v newer";
        return null;
    }
    if ($v !== MEL_CGI_HT_COPY_V || !isset($c['items']) || !is_array($c['items']) || $c['items'] !== array_values($c['items']))
    {
        $why = 'not a list';
        return null;
    }
    $rows = array();
    foreach ($c['items'] as $x)
    {
        // Fields after these (of a later form of v 1): not read.
        $rows[] = is_array($x) && count($x) >= 6 && array_keys(array_slice($x, 0, 6, true)) === range(0, 5) ? array('type' => $x[0],
            'name' => $x[1], 'year' => $x[2], 'imdb_id' => $x[3], '_tmdbId' => $x[4], 'hidden' => $x[5]) : null;
    }
    $list = mel_ht_items($rows);
    $dropped = count($rows) - count($list);
    return $list;
}

// The text of the export $json (with "Мой список": mel_cgi_export_list) with the hidden of $dir: the field
// hidden_titles after the others, the newest titles as many as keep the body of its import within MEL_CGI_IMPORT_MAX
// (the oldest left out, a line of the log; none fits: no field). No file: none (an import leaves the hidden of that
// Dune as they are); one not taken or broken: none too, a line of the log.
function mel_cgi_export_hidden($dir, $json)
{
    $path = "$dir/" . MEL_HT_FILE;
    $list = mel_ht_read_file($path, $why);
    if ($why !== '')
        mel_cgi_log('export: ' . MEL_HT_FILE . " $why, not in the file");
    clearstatcache();
    if ($list === null || $why !== '' || !file_exists($path))
        return $json;
    $room = MEL_CGI_IMPORT_MAX - strlen('settings=' . rawurlencode($json));
    // The most titles that fit: rawurlencode is never shorter than encodeURIComponent of the page.
    $lo = 0;
    $hi = count($list);
    while ($lo < $hi)
    {
        $m = intval(($lo + $hi + 1) / 2);
        if (strlen(rawurlencode(mel_cgi_ht_copy_part(array_slice($list, 0, $m)))) <= $room)
            $lo = $m;
        else
            $hi = $m - 1;
    }
    $c = $lo > 0 || !$list ? mel_cgi_ht_copy_part(array_slice($list, 0, $lo)) : '';
    if ($c === '' || strlen(rawurlencode($c)) > $room)
    {
        mel_cgi_log('export: hidden, ' . count($list) . ' titles, not in the file: no room');
        return $json;
    }
    mel_cgi_log('export: hidden, ' . $lo . ' titles' . ($lo < count($list) ? ', ' . (count($list) - $lo) .
        ' oldest left out: no room' : ''));
    return substr($json, 0, -1) . $c . '}';
}
