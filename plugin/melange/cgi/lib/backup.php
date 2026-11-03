<?php
// The settings page (cgi/settings.php): the backup of the settings - the
// export of settings.json to a file (a=export) and the import of such a file
// (a=import: its text posted by the script of the page, never $_FILES).

// The longest body of the import (settings=<the text of the file, URL-encoded>):
// post_max_size of cgi/php.ini (32K), over it php-cgi drops the body. The
// page refuses a longer one before it goes (a file over it unread), the
// field is checked here too. A real settings.json is a few KB (a heavy one:
// 15 KB, a body of 20 KB).
define('AIO_CGI_IMPORT_MAX', 32768);

// The settings as aio_settings_read has them (checked fields only) in the form of settings.json
// v AIO_SETTINGS_V, with web_token: $t, the token of the page (0.51.0: the import
// gives the page this link again, after a reinstall too; the parse of settings
// drops the field, an older Melange imports the file without it), an attachment
// melange-settings-<UTC YYYYMMDD-HHMM>.json. None saved yet
// (the first run) or not read: a text error, no file.
function aio_cgi_export($dir, $t)
{
    $s = aio_settings_read($dir, $why);
    if ($why !== '')
    {
        aio_cgi_log("export: settings.json $why");
        aio_cgi_send(500, 'text/plain', aio_cgi_l('Настройки на Дюне не читаются, файл не сохранён.',
            'The settings on the Dune cannot be read, no file saved.') . "\n");
    }
    if (!$s['saved_once'])
    {
        aio_cgi_log('export: no settings yet');
        aio_cgi_send(404, 'text/plain', aio_cgi_l('Настроек ещё нет: сначала сохраните их.',
            'No settings yet: save them first.') . "\n");
    }
    $json = aio_settings_json($s + array('web_token' => $t));
    if ($json === '')
    {
        aio_cgi_log('export: json_encode failed');
        aio_cgi_send(500, 'text/plain', aio_cgi_l('Файл не сохранён: ошибка на Дюне.', 'No file saved: an error on the Dune.') . "\n");
    }
    date_default_timezone_set('UTC');
    aio_cgi_headers(200, 'application/json');
    header('Content-Disposition: attachment; filename="' . AIO_CGI_NAME . '-settings-' . gmdate('Ymd-Hi') . '.json"');
    header('X-Content-Type-Options: nosniff');
    echo $json;
    exit(0);
}

// The screen "Резервная копия" (#bak): "Сохранить в файл" - a link (works
// without the script), none on the first run (#bkn instead; the script shows
// it after the first save that stays on the page); "Загрузить из файла" - by
// the script only (#bki hidden till then), the file input never posted (no
// name) and only visually hidden (vh: a click() on a display:none one may be
// ignored by older mobile browsers), data-max - the longest body it posts.
function aio_cgi_backup($t, $v)
{
    $first = isset($v['first']) && $v['first'];
    return '<p class="sub">' . aio_cgi_l('Все настройки Melange в одном файле: перед переустановкой или для другой Дюны.',
        'All Melange settings in one file: before a reinstall or for another Dune.') . '</p>' .
        '<p class="hint wn">' . aio_cgi_l('В файле ключи Debrid и пароли — храните его у себя, никому не пересылайте.',
        'It holds Debrid keys and passwords: keep it to yourself, send it to no one.') . '</p>' .
        '<p class="hint" id="bkn"' . ($first ? '' : ' hidden') . '>' . aio_cgi_l('Настроек ещё нет: сначала сохраните их.',
        'No settings yet: save them first.') . '</p>' .
        '<a id="bkx" class="btn" href="settings?t=' . aio_cgi_h($t) . '&amp;a=export"' . ($first ? ' hidden' : '') . '>' .
        aio_cgi_l('Сохранить в файл', 'Save to a file') . '</a>' .
        '<div id="bki" hidden><button type="button" id="bkb" class="btn ghost">' . aio_cgi_l('Загрузить из файла', 'Load from a file') .
        '</button><input type="file" id="bkf" class="vh" tabindex="-1" aria-hidden="true" accept=".json,application/json" data-max="' .
        AIO_CGI_IMPORT_MAX . '">' .
        '<p class="hint c">' . aio_cgi_l('Заменит все настройки; конфиги AIOStreams на серверах не меняются.',
        'Replaces all settings; AIOStreams configs on the servers stay as they are.') . '</p></div>' .
        '<div id="bkr" class="res" aria-live="polite"></div>';
}

function aio_cgi_import_big()
{
    return aio_cgi_l('Файл слишком большой для файла настроек Melange', 'The file is too big for a Melange settings file');
}

// aio_confs (base => mode => config) of this Dune with those of a file over
// them: the file wins for a base and mode it has, the rest stays.
function aio_cgi_confs_merge($cur, $file)
{
    foreach ($file as $b => $cs)
        foreach ($cs as $m => $c)
            $cur[$b][$m] = $c;
    return $cur;
}

// The import failed: JSON {ok: false, msg}, nothing written.
function aio_cgi_import_failed($code, $why, $msg)
{
    aio_cgi_log("import: $why, nothing written");
    aio_cgi_json($code, array('ok' => false, 'msg' => $msg));
}

// POST a=import&ajax=1 (settings: the text of the file) -> the settings of
// Melange in it (an object with v 1..AIO_SETTINGS_V; a newer v: its own
// message) through the rules of aio_settings_read (aio_settings_parse: a
// wrong field as not set, a v 1 file as v 2, other fields dropped), written
// whole as a save writes them. The configs of AIOStreams (aio_confs): those
// of this Dune, those of the file over them (aio_cgi_confs_merge): the
// configs are on the servers already, an older file unlinks none. No key
// checked, no request out. Then web_token of the file (aio_token_ok, another
// one): written over that of this Dune, its owner and mode kept
// (aio_cgi_write), after settings.json - a failure in between leaves the
// link as it was, the settings imported. -> JSON {ok: true, next} (the page
// again, its values; by the token of the file if written, imported=2 if it
// was not) | {ok: false, msg}.
function aio_cgi_import_post($dir, $t)
{
    $len = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
    $big = aio_cgi_import_big();
    // php-cgi drops a body over post_max_size: $_POST is empty.
    if (!$_POST && $len > aio_cgi_post_max())
        aio_cgi_import_failed(200, 'body over post_max_size', $big);
    $raw = aio_cgi_param($_POST, 'settings');
    if (strlen($raw) > AIO_CGI_IMPORT_MAX)
        aio_cgi_import_failed(200, 'over ' . AIO_CGI_IMPORT_MAX . ' bytes', $big);
    $not = aio_cgi_l('Это не файл настроек Melange', 'This is not a Melange settings file');
    if (trim($raw, AIO_TRIM) === '')
        aio_cgi_import_failed(200, 'empty', $not);
    // PHP 5.3: not UTF-8 -> null, as not JSON.
    $j = json_decode($raw, true);
    if (!is_array($j))
        aio_cgi_import_failed(200, 'not JSON', $not);
    $v = isset($j['v']) ? $j['v'] : null;
    if (is_int($v) && $v > AIO_SETTINGS_V)
        aio_cgi_import_failed(200, "v $v newer", aio_cgi_l('Файл от более новой версии Melange — обновите плагин',
            'The file is from a newer version of Melange: update the plugin'));
    if (!is_int($v) || $v < 1)
        aio_cgi_import_failed(200, 'no v', $not);
    if (!aio_cgi_dir_ok($dir))
        aio_cgi_import_failed(500, 'no data dir', aio_cgi_l('Не загружено: нет папки плагина на Дюне',
            'Not loaded: the plugin data folder is missing on the Dune'));
    $s = aio_settings_parse($j);
    $cur = aio_settings_read($dir, $why);
    if ($why !== '')
        aio_cgi_log("import: settings.json $why");
    $s['aio_confs'] = aio_cgi_confs_merge($cur['aio_confs'], $s['aio_confs']);
    // The plugin reads no settings.json over AIO_SETTINGS_MAX.
    if (strlen(aio_settings_json($s)) > AIO_SETTINGS_MAX)
        aio_cgi_import_failed(200, 'settings over ' . AIO_SETTINGS_MAX . ' bytes', $big);
    $why = aio_cgi_store($dir, $s);
    if ($why !== '')
        aio_cgi_import_failed(500, $why, aio_cgi_l('Не загружено: ошибка записи на Дюне', 'Not loaded: write error on the Dune'));
    aio_cgi_log('import: ok');
    $ft = isset($j['web_token']) ? $j['web_token'] : null;
    $done = 1;
    if (aio_token_ok($ft) && !aio_cgi_same($ft, $t))
    {
        $why = aio_cgi_write($dir, $ft, AIO_TOKEN_FILE);
        aio_cgi_log('import: web_token of the file ' . ($why === '' ? 'written' : "not written: $why"));
        if ($why === '')
            $t = $ft;
        else
            $done = 2;
    }
    // Only a checked token, as after a save.
    aio_cgi_json(200, array('ok' => true, 'next' => 'settings?t=' . $t . '&imported=' . $done));
}
