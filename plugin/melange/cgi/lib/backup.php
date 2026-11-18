<?php
// The settings page (cgi/settings.php): the backup of the settings - the
// export of settings.json to a file (a=export) and the import of such a file
// (a=import: its text posted by the script of the page, never $_FILES).
// "Мой список" (0.62.0) goes with them: the field my_list of the file
// (mel_ml_copy of my_list.php), written to its file of data_dir on an import;
// so do the hidden titles (0.65.0): the field hidden_titles (mel_cgi_ht_copy of
// lib/hidden.php), in the room the rest of the body leaves.

require_once dirname(dirname(dirname(__FILE__))) . '/my_list.php';

// What the settings take of the body of an import at most (settings=<the text
// of the file, URL-encoded>): a save of a source profile that would make it
// longer is refused (lib/profiles.php). A real settings.json is a few KB (a
// heavy one: 15 KB, a body of 20 KB).
define('MEL_CGI_BAK_SETTINGS_MAX', 32768);
// The longest body of the import: the settings and "Мой список"
// (MEL_ML_COPY_MAX), 256K - post_max_size of cgi/php.ini, over it php-cgi
// drops the body. The page refuses a longer one before it goes (a file over it
// unread), the field is checked here too. The list of 200 titles takes ~80 KB.
define('MEL_CGI_IMPORT_MAX', MEL_CGI_BAK_SETTINGS_MAX + MEL_ML_COPY_MAX);

// The settings as mel_settings_read has them (checked fields only) in the form of settings.json
// v MEL_SETTINGS_V, with web_token: $t, the token of the page (0.51.0: the import
// gives the page this link again, after a reinstall too; the parse of settings
// drops the field, an older Melange imports the file without it), an attachment
// melange-settings-<UTC YYYYMMDD-HHMM>.json. None saved yet
// (the first run) or not read: a text error, no file.
function mel_cgi_export($dir, $t)
{
    $s = mel_settings_read($dir, $why);
    if ($why !== '')
    {
        mel_cgi_log("export: settings.json $why");
        mel_cgi_send(500, 'text/plain', mel_cgi_l('Настройки на Дюне не читаются, файл не сохранён.',
            'The settings on the Dune cannot be read, no file saved.') . "\n");
    }
    if (!$s['saved_once'])
    {
        mel_cgi_log('export: no settings yet');
        mel_cgi_send(404, 'text/plain', mel_cgi_l('Настроек ещё нет: сначала сохраните их.',
            'No settings yet: save them first.') . "\n");
    }
    $json = mel_cgi_export_json($s, $t);
    if ($json === '')
    {
        mel_cgi_log('export: json_encode failed');
        mel_cgi_send(500, 'text/plain', mel_cgi_l('Файл не сохранён: ошибка на Дюне.', 'No file saved: an error on the Dune.') . "\n");
    }
    $json = mel_cgi_export_hidden($dir, mel_cgi_export_list($dir, $json));
    date_default_timezone_set('UTC');
    mel_cgi_headers(200, 'application/json');
    header('Content-Disposition: attachment; filename="' . MEL_CGI_NAME . '-settings-' . gmdate('Ymd-Hi') . '.json"');
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit(0);
}

// The text of the file of the export of the settings $s (mel_settings_read), with web_token $t ('': json_encode failed).
function mel_cgi_export_json($s, $t)
{
    return mel_settings_json($s + array('web_token' => $t));
}

// The text of the export $json (mel_cgi_export_json) with "Мой список" of $dir: the field my_list (mel_ml_copy) after
// the others. No file of the list: none (an import leaves the list of that Dune as it is); one not taken (broken, of a
// later Melange, not readable): none too, a line of the log.
function mel_cgi_export_list($dir, $json)
{
    $list = mel_ml_read_file("$dir/" . MEL_ML_FILE, $why, $dropped);
    if ($list === null)
        mel_cgi_log('export: ' . MEL_ML_FILE . " $why, not in the file");
    $c = $list !== null && file_exists("$dir/" . MEL_ML_FILE) ? mel_ml_copy_part($list) : '';
    if ($c === '')
        return $json;
    mel_cgi_log('export: my list, ' . count($list) . ' titles');
    // $json is an object: the field goes before its last "}".
    return substr($json, 0, -1) . $c . '}';
}

// The settings of the file of the export $json (mel_cgi_export_json, no list) load back: the body the page posts of
// them (importBody of web/src/api.ts, by encodeURIComponent - rawurlencode is never shorter) is not over
// MEL_CGI_BAK_SETTINGS_MAX, the rest of MEL_CGI_IMPORT_MAX is of the list.
function mel_cgi_import_fits($json)
{
    return strlen('settings=' . rawurlencode($json)) <= MEL_CGI_BAK_SETTINGS_MAX;
}

// The screen "Резервная копия" (#bak): "Сохранить в файл" - a link (works
// without the script), none on the first run (#bkn instead; the script shows
// it after the first save that stays on the page); "Загрузить из файла" - by
// the script only (#bki hidden till then), the file input never posted (no
// name) and only visually hidden (vh: a click() on a display:none one may be
// ignored by older mobile browsers), data-max - the longest body it posts.
function mel_cgi_backup($t, $v)
{
    $first = isset($v['first']) && $v['first'];
    return '<p class="sub">' . mel_cgi_l('Все настройки Melange в одном файле: перед переустановкой или для другой Дюны.',
        'All Melange settings in one file: before a reinstall or for another Dune.') . '</p>' .
        '<p class="hint">' . mel_cgi_l('Копия сохраняет и «Избранное», и скрытые тайтлы.',
        'The backup includes Favorites and hidden titles too.') . '</p>' .
        '<p class="hint wn">' . mel_cgi_l('В файле ключи Debrid, токены, пароли и ссылка на страницу — храните его у себя.',
        'It holds Debrid keys, tokens, passwords and the page link: keep it to yourself.') . '</p>' .
        '<p class="hint" id="bkn"' . ($first ? '' : ' hidden') . '>' . mel_cgi_l('Настроек ещё нет: сначала сохраните их.',
        'No settings yet: save them first.') . '</p>' .
        '<a id="bkx" class="btn" href="settings?t=' . mel_cgi_h($t) . '&amp;a=export"' . ($first ? ' hidden' : '') . '>' .
        mel_cgi_l('Сохранить в файл', 'Save to a file') . '</a>' .
        '<div id="bki" hidden><button type="button" id="bkb" class="btn ghost">' . mel_cgi_l('Загрузить из файла', 'Load from a file') .
        '</button><input type="file" id="bkf" class="vh" tabindex="-1" aria-hidden="true" accept=".json,application/json" data-max="' .
        MEL_CGI_IMPORT_MAX . '">' .
        '<p class="hint c">' . mel_cgi_l('Заменит все настройки; конфиги AIOStreams на серверах не меняются.',
        'Replaces all settings; AIOStreams configs on the servers stay as they are.') . '</p></div>' .
        '<div id="bkr" class="res" aria-live="polite"></div>';
}

function mel_cgi_import_big()
{
    return mel_cgi_l('Файл слишком большой для файла настроек Melange', 'The file is too big for a Melange settings file');
}

// aio_confs (base => mode => config) of this Dune with those of a file over
// them: the file wins for a base and mode it has, the rest stays.
function mel_cgi_confs_merge($cur, $file)
{
    foreach ($file as $b => $cs)
        foreach ($cs as $m => $c)
            $cur[$b][$m] = $c;
    return $cur;
}

// The import failed: JSON {ok: false, msg}, nothing written.
function mel_cgi_import_failed($code, $why, $msg)
{
    mel_cgi_log("import: $why, nothing written");
    mel_cgi_json($code, array('ok' => false, 'msg' => $msg));
}

// The file $name of $dir written over by $data on an import ($what: of the log; '' - json_encode failed): -> its text
// before, null - none (for mel_cgi_import_back). Not readable or not written: the files of $back put back
// (mel_cgi_import_back), the import refused with $msg.
function mel_cgi_import_put($dir, $name, $data, $what, $msg, $back = array())
{
    $path = "$dir/$name";
    clearstatcache();
    $old = is_file($path) && !is_link($path) ? @file_get_contents($path) : (file_exists($path) || is_link($path) ? false : null);
    $why = $old === false ? 'the file of this Dune not readable' : ($data !== '' ? mel_cgi_write($dir, $data, $name) :
        'json_encode failed');
    if ($why !== '')
    {
        mel_cgi_import_back($dir, $back);
        mel_cgi_import_failed(500, "$what: $why", $msg);
    }
    return $old;
}

// The files written over by an import put back after a failure: array(name, the text before - null: none, false: not
// written over, what of the log) each; a line of the log each.
function mel_cgi_import_back($dir, $files)
{
    foreach ($files as $x)
    {
        list($name, $old, $what) = $x;
        if ($old === false)
            continue;
        $back = $old === null ? (@unlink("$dir/$name") ? '' : 'unlink failed') : mel_cgi_write($dir, $old, $name);
        mel_cgi_log("import: $what of this Dune " . ($back === '' ? 'put back' : "not put back: $back"));
    }
}

// POST a=import&ajax=1 (settings: the text of the file) -> the settings of
// Melange in it (an object with v 1..MEL_SETTINGS_V; a newer v: its own
// message) through the rules of mel_settings_read (mel_settings_parse: a
// wrong field as not set, a v 1 file as v 2, other fields dropped), written
// whole as a save writes them. "Мой список" of the field my_list
// (mel_ml_from_copy; a title not good dropped; a copy of a later Melange: the
// message of a newer file, another value: not a file of Melange) written over
// that of this Dune (mel_cgi_write, by rename: php_server reads it anew by
// mel_file_sig), before the settings: its failure leaves both as they were,
// a failure of the settings puts the list of this Dune back (none: the file
// removed); a list whose file php_server would not read (MEL_ML_FILE_MAX): the
// message, nothing written; no field (a file of before 0.62.0): the list of
// this Dune stays. The hidden titles of the field hidden_titles (mel_cgi_ht_from_copy) the same way, after "Мой
// список" (its failure puts that back too; a file over MEL_HT_FILE_MAX: the message, nothing written). The
// configs of AIOStreams (aio_confs): those of this Dune, those of the file
// over them (mel_cgi_confs_merge): the
// configs are on the servers already, an older file unlinks none. No key
// checked, no request out. Then web_token of the file (mel_token_ok, another
// one): written over that of this Dune, its owner kept, 0600
// (mel_cgi_write), after settings.json - a failure in between leaves the
// link as it was, the settings imported. -> JSON {ok: true, next} (the page
// again, its values; by the token of the file if written, imported=2 if it
// was not) | {ok: false, msg}.
function mel_cgi_import_post($dir, $t)
{
    $len = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
    $big = mel_cgi_import_big();
    // php-cgi drops a body over post_max_size: $_POST is empty.
    if (!$_POST && $len > mel_cgi_post_max())
        mel_cgi_import_failed(200, 'body over post_max_size', $big);
    $raw = mel_cgi_param($_POST, 'settings');
    if (strlen($raw) > MEL_CGI_IMPORT_MAX)
        mel_cgi_import_failed(200, 'over ' . MEL_CGI_IMPORT_MAX . ' bytes', $big);
    $not = mel_cgi_l('Это не файл настроек Melange', 'This is not a Melange settings file');
    if (trim($raw, MEL_TRIM) === '')
        mel_cgi_import_failed(200, 'empty', $not);
    // PHP 5.3: not UTF-8 -> null, as not JSON.
    $j = json_decode($raw, true);
    if (!is_array($j))
        mel_cgi_import_failed(200, 'not JSON', $not);
    $v = isset($j['v']) ? $j['v'] : null;
    if (is_int($v) && $v > MEL_SETTINGS_V)
        mel_cgi_import_failed(200, "v $v newer", mel_cgi_l('Файл от более новой версии Melange — обновите плагин',
            'The file is from a newer version of Melange: update the plugin'));
    if (!is_int($v) || $v < 1)
        mel_cgi_import_failed(200, 'no v', $not);
    $list = null;
    if (array_key_exists(MEL_ML_COPY_FIELD, $j))
    {
        $list = mel_ml_from_copy($j[MEL_ML_COPY_FIELD], $why, $dropped);
        if ($list === null && strpos($why, 'newer') !== false)
            mel_cgi_import_failed(200, "my list $why", mel_cgi_l('Файл от более новой версии Melange — обновите плагин',
                'The file is from a newer version of Melange: update the plugin'));
        if ($list === null)
            mel_cgi_import_failed(200, "my list $why", $not);
    }
    $hidden = null;
    if (array_key_exists(MEL_CGI_HT_FIELD, $j))
    {
        $hidden = mel_cgi_ht_from_copy($j[MEL_CGI_HT_FIELD], $why, $hdropped);
        if ($hidden === null && strpos($why, 'newer') !== false)
            mel_cgi_import_failed(200, "hidden $why", mel_cgi_l('Файл от более новой версии Melange — обновите плагин',
                'The file is from a newer version of Melange: update the plugin'));
        if ($hidden === null)
            mel_cgi_import_failed(200, "hidden $why", $not);
    }
    if (!mel_cgi_dir_ok($dir))
        mel_cgi_import_failed(500, 'no data dir', mel_cgi_l('Не загружено: нет папки плагина на Дюне',
            'Not loaded: the plugin data folder is missing on the Dune'));
    $s = mel_settings_parse($j);
    $cur = mel_settings_read($dir, $why);
    if ($why !== '')
        mel_cgi_log("import: settings.json $why");
    $s['aio_confs'] = mel_cgi_confs_merge($cur['aio_confs'], $s['aio_confs']);
    // The plugin reads no settings.json over MEL_SETTINGS_MAX.
    if (strlen(mel_settings_json($s)) > MEL_SETTINGS_MAX)
        mel_cgi_import_failed(200, 'settings over ' . MEL_SETTINGS_MAX . ' bytes', $big);
    $wfail = mel_cgi_l('Не загружено: ошибка записи на Дюне', 'Not loaded: write error on the Dune');
    $ml = $list !== null ? mel_ml_json($list) : '';
    if ($list !== null && strlen($ml) > MEL_ML_FILE_MAX)
        mel_cgi_import_failed(200, 'my list: the file over ' . MEL_ML_FILE_MAX . ' bytes', $big);
    $ht = $hidden !== null ? mel_ht_json($hidden) : '';
    if ($hidden !== null && strlen($ht) > MEL_HT_FILE_MAX)
        mel_cgi_import_failed(200, 'hidden: the file over ' . MEL_HT_FILE_MAX . ' bytes', $big);
    // The files of this Dune, for a failure after them: their text, null - no file (false: not written over).
    $old = false;
    if ($list !== null)
    {
        $old = mel_cgi_import_put($dir, MEL_ML_FILE, $ml, 'my list', $wfail);
        mel_cgi_log('import: my list, ' . count($list) . ' titles' . ($dropped ? ", $dropped not good dropped" : ''));
    }
    $hold = false;
    if ($hidden !== null)
    {
        $hold = mel_cgi_import_put($dir, MEL_HT_FILE, $ht, 'hidden', $wfail, array(array(MEL_ML_FILE, $old, 'my list')));
        mel_cgi_log('import: hidden, ' . count($hidden) . ' titles' . ($hdropped ? ", $hdropped not good dropped" : ''));
    }
    $why = mel_cgi_store($dir, $s);
    if ($why !== '')
    {
        mel_cgi_import_back($dir, array(array(MEL_ML_FILE, $old, 'my list'), array(MEL_HT_FILE, $hold, 'hidden')));
        mel_cgi_import_failed(500, $why, $wfail);
    }
    mel_cgi_log('import: ok');
    if ($s['mig_lost'])
        mel_cgi_log('import: the file of before v2, ' . MEL_PROFILES_MAX . ' profiles already: no profile of ' .
            implode(', ', $s['mig_lost']));
    $ft = isset($j['web_token']) ? $j['web_token'] : null;
    $done = 1;
    if (mel_token_ok($ft) && !mel_cgi_same($ft, $t))
    {
        $why = mel_cgi_write($dir, $ft, MEL_TOKEN_FILE);
        mel_cgi_log('import: web_token of the file ' . ($why === '' ? 'written' : "not written: $why"));
        if ($why === '')
            $t = $ft;
        else
            $done = 2;
    }
    // Only a checked token, as after a save.
    mel_cgi_json(200, array('ok' => true, 'next' => 'settings?t=' . $t . '&imported=' . $done));
}
