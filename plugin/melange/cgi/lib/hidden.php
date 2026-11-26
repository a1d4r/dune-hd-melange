<?php
// The settings page (cgi/settings.php): the screen "Что не показывать" (#hide, 0.65.0) - the age of the catalog
// (cat_age, a radio of the form: mel_cgi_cat_ages), the lowest IMDb ratings of movies and series (cat_imdb_movie,
// cat_imdb_series, 0.70.0: mel_cgi_cat_imdbs) and "Скрытые", the titles hidden on the Dune (POP UP → «Скрыть»,
// data_dir/hidden_titles.json of hidden_titles.php), each with "Вернуть" (a=title_unhide, by the script only); the copy
// of the list in the backup (the field hidden_titles of the export, lib/backup.php). The file is written by the plugin
// too, with no lock (an action of the remote and one of the phone in the same instant may lose one): "Вернуть" reads
// it anew and takes one title out.

require_once dirname(dirname(dirname(__FILE__))) . '/hidden_titles.php';

// The field of the copy in the file of the export and its version: {"v": 1, "items": ["<m|s>~<IMDb>~<TMDB>~<year>~<name>"
// ...]}, the newest first, no time of hiding (an import makes it 0). A string a title, not a row of fields: the page
// posts the file URL-encoded, where a quote or a comma takes 3 bytes and "~" one; a title of an empty name is ~35 bytes
// of the body so, ~90 as a row. A letter of a Cyrillic name: \uXXXX of json_encode of PHP 5.3, 8 bytes there.
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

// The answers of the lowest IMDb rating of movies ($series false: radio cat_imdb_movie) or series (cat_imdb_series):
// its value => array(name, hint, its part of the line on the main screen (data-short; 'all' - none: mel_cgi_hide_line));
// 'all' - null, else the rating in tenths (mel_imdb_mins: "65" - from 6.5).
function mel_cgi_cat_imdbs($series)
{
    $out = array('all' => array(mel_cgi_l('Любой', 'Any'), mel_cgi_l('Рейтинг не проверяется.', 'The rating is not checked.'), ''));
    foreach (mel_imdb_mins() as $n)
    {
        $r = intval($n / 10) . ($n % 10 ? '.' . $n % 10 : '');
        $out[strval($n)] = array(mel_cgi_l("От $r", "$r+"), '', $series ? mel_cgi_l("сериалы от $r", "series $r+") :
            mel_cgi_l("фильмы от $r", "movies $r+"));
    }
    return $out;
}

// The answer of mel_cgi_cat_imdbs() of cat_imdb_movie or cat_imdb_series of the settings (an int, null - any).
function mel_cgi_cat_imdb_of($a)
{
    return $a === null ? 'all' : strval($a);
}

// cat_imdb_movie or cat_imdb_series of the settings of answer $x of the form (mel_cgi_cat_imdbs; another one: null, the
// check refuses it).
function mel_cgi_cat_imdb_set($x)
{
    return $x !== 'all' && array_key_exists($x, mel_cgi_cat_imdbs(false)) ? intval($x) : null;
}

// Why the list of the file is not taken (mel_ht_read_file gave null, $why): the line of the page. A broken file
// (mel_ht_broken): php_server renames it to .bad at its next read and starts a new list (mel_ht_list); the page only
// reads it.
function mel_cgi_ht_why_text($why)
{
    if (strpos($why, 'newer') !== false)
        return mel_cgi_l('Список скрытых записан более новой версией Melange: обновите плагин',
            'The hidden list is from a newer version of Melange: update the plugin');
    return mel_ht_broken($why) ? mel_cgi_l('Список скрытых повреждён: Дюна начнёт новый, старый файл останется рядом',
        'The hidden list is damaged: the Dune starts a new one and keeps the old file') :
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

// #init.hidden of the page: {items (mel_cgi_ht_items), msg: '' or why there is no list (mel_cgi_ht_why_text: of a later
// Melange, not readable, broken)}. No file: none hidden, no msg.
function mel_cgi_ht_data($dir)
{
    $why = '';
    $list = mel_cgi_dir_ok($dir) ? mel_ht_read_file("$dir/" . MEL_HT_FILE, $why) : array();
    if ($why !== '')
        mel_cgi_log('form: ' . MEL_HT_FILE . " $why");
    return array('items' => $list !== null ? mel_cgi_ht_items($list) : array(), 'msg' => $list !== null ? '' : mel_cgi_ht_why_text($why));
}

// The screen "Что не показывать" (#hide): the age and the IMDb ratings of movies and series (values of the form: '' or
// another one - none checked), where they and the hidden act; "Скрытые": the list made by the script
// (web/src/hidden.ts) from #init.hidden, its lines in #htr (without the script: why there is none).
function mel_cgi_hide_screen($v)
{
    $f = mel_cgi_fields();
    return '<p class="sub">' . mel_cgi_l('Действует в каталоге, «ТВ» и карточках. «Продолжить» и «Избранное» не меняются.',
        'Applies to the catalog, "TV" and cards. "Continue Watching" and Favorites stay.') . '</p>' .
        '<p class="lbl">' . mel_cgi_l('Возраст', 'Age rating') . '</p>' . mel_cgi_choice($f['cat_age'], mel_cgi_cat_ages(), $v['cat_age']) .
        '<p class="hint">' . mel_cgi_l('Возраст проверяется и в поиске. Тайтлы без возраста видны.',
        'Search results are filtered too. Titles with no age rating stay visible.') . '</p>' .
        '<p class="lbl">' . mel_cgi_l('Рейтинг IMDb: фильмы', 'IMDb rating: movies') . '</p>' .
        mel_cgi_choice($f['cat_imdb_movie'], mel_cgi_cat_imdbs(false), $v['cat_imdb_movie']) .
        '<p class="lbl">' . mel_cgi_l('Рейтинг IMDb: сериалы', 'IMDb rating: series') . '</p>' .
        mel_cgi_choice($f['cat_imdb_series'], mel_cgi_cat_imdbs(true), $v['cat_imdb_series']) .
        '<p class="hint">' . mel_cgi_l('Рейтинг проверяется и в поиске. Тайтлы без рейтинга IMDb видны.',
        'Search results are filtered too. Titles with no IMDb rating stay visible.') . '</p>' .
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
// later Melange, not readable, broken: mel_cgi_ht_why_text) or no data dir: refused, nothing written, no list; not
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
        mel_cgi_ht_answer(false, mel_cgi_ht_why_text($why), null);
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

// Name $name as the copy keeps it: up to $n letters, "…" after a cut one.
function mel_cgi_ht_cut($name, $n)
{
    return preg_match('/^(.{' . $n . '}).+/su', $name, $m) ? rtrim($m[1]) . '…' : $name;
}

// The copy of list $list in the file of the export, its names up to $n letters (mel_cgi_ht_cut).
function mel_cgi_ht_copy($list, $n)
{
    $items = array();
    foreach ($list as $e)
        $items[] = ($e['type'] === 'series' ? 's' : 'm') . '~' . $e['imdb_id'] . '~' . $e['_tmdbId'] . '~' . $e['year'] . '~' .
            mel_cgi_ht_cut($e['name'], $n);
    return array('v' => MEL_CGI_HT_COPY_V, 'items' => $items);
}

// The field of the copy of list $list (names up to $n letters), ',"hidden_titles":{…}', to go before the last "}" of the
// export ('': json_encode failed).
function mel_cgi_ht_copy_part($list, $n)
{
    $c = json_encode(mel_cgi_ht_copy($list, $n));
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
        // The name last: a "~" in it is of the name.
        $f = is_string($x) ? explode('~', $x, 5) : array();
        $rows[] = count($f) === 5 && ($f[0] === 'm' || $f[0] === 's') ? array('type' => $f[0] === 's' ? 'series' : 'movie',
            'imdb_id' => $f[1], '_tmdbId' => $f[2], 'year' => $f[3], 'name' => $f[4]) : null;
    }
    $list = mel_ht_items($rows);
    $dropped = count($rows) - count($list);
    return $list;
}
