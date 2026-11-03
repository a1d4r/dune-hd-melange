<?php
// The settings page (cgi/settings.php): the backup of the settings - the
// export of settings.json to a file (a=export) and the import of such a file
// (a=import: its text posted by the script of the page, never $_FILES).

// The longest text of a file the import takes (the page refuses a bigger file
// before reading it): the body of a POST is dropped over post_max_size (32K,
// cgi/php.ini) anyway; a settings.json is a few KB.
define('AIO_CGI_IMPORT_MAX', 30720);

// The settings as aio_settings_read has them (checked fields only, no token:
// web_token is a file of its own, of this Dune) in the form of settings.json
// v 2, an attachment melange-settings-<UTC YYYYMMDD-HHMM>.json. None saved yet
// (the first run) or not read: a text error, no file.
function aio_cgi_export($dir)
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
    $json = aio_cgi_settings_json($s);
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
// ignored by older mobile browsers), data-max - the longest file it reads.
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

// The import failed: JSON {ok: false, msg}, nothing written.
function aio_cgi_import_failed($code, $why, $msg)
{
    aio_cgi_log("import: $why, nothing written");
    aio_cgi_json($code, array('ok' => false, 'msg' => $msg));
}

// POST a=import&ajax=1 (settings: the text of the file) -> the settings of
// Melange in it (an object with v 1 or 2) through the rules of
// aio_settings_read (aio_settings_parse: a wrong field as not set, a v 1 file
// as v 2, other fields dropped), written whole as a save writes them, the
// configs of AIOStreams (aio_confs) of the file with them: the configs are
// on the servers already. No key checked, no request out. -> JSON {ok: true,
// next} (the page again, its values) | {ok: false, msg}.
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
    if (!isset($j['v']) || ($j['v'] !== 1 && $j['v'] !== 2))
        aio_cgi_import_failed(200, 'no v 1 or 2', $not);
    if (!aio_cgi_dir_ok($dir))
        aio_cgi_import_failed(500, 'no data dir', aio_cgi_l('Не загружено: нет папки плагина на Дюне',
            'Not loaded: the plugin data folder is missing on the Dune'));
    $s = aio_settings_parse($j);
    // The plugin reads no settings.json over AIO_SETTINGS_MAX.
    if (strlen(aio_cgi_settings_json($s)) > AIO_SETTINGS_MAX)
        aio_cgi_import_failed(200, 'settings over ' . AIO_SETTINGS_MAX . ' bytes', $big);
    $why = aio_cgi_store($dir, $s);
    if ($why !== '')
        aio_cgi_import_failed(500, $why, aio_cgi_l('Не загружено: ошибка записи на Дюне', 'Not loaded: write error on the Dune'));
    aio_cgi_log('import: ok');
    // Only the checked token, as after a save.
    aio_cgi_json(200, array('ok' => true, 'next' => 'settings?t=' . $t . '&imported=1'));
}
