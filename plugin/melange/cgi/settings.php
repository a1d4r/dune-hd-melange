<?php
// Settings page of melange (www/cgi-bin/settings -> php-cgi): root on the
// device, open to the whole LAN without a password. Without the token of
// <data_dir>/web_token every request gets the same 403, with no
// diagnostics and no outgoing request (no data dir at all: 500, a hint).
//   GET  ?t=<token> -> the form with the current values, in the language of
//                      the Dune (aio_cgi_lang);
//   POST ?t=<token> -> format (no request out) -> the keys given checked with
//                      their services (a refused one: nothing written) ->
//                      write settings.json v 2 (aio_confs kept) -> the
//                      config of AIOStreams as a=aio_sync -> 303 to GET
//                      ?t=<token>&saved=1 (a reload does not post again);
//                      errors -> the form back (only without JS);
//   POST ?t=<token>&ajax=1 -> the same save without the config, JSON {ok,
//                      next, aio[, notes] | errors: [{msg, field}]} to the script of the page
//                      (aio: create, update, reset (0.40.0: its button or
//                      another template) or '' - what a=aio_sync is to do):
//                      no page is ever an answer to a POST (a mobile browser
//                      would post it again on return);
//   POST ?t=<token>&a=aio_status&ajax=1 (url) -> GET /api/v1/status of that
//                      own AIOStreams server: version, TMDB of its own; JSON
//                      {ok, level, msg}, nothing written;
//   POST ?t=<token>&a=aio_sync&ajax=1 -> the config of AIOStreams on the
//                      chosen server (of the list or the own one, 0.35.1) by
//                      settings.json: the own config chosen -> nothing; the
//                      own server -> its TMDB by /status first; no config
//                      there -> no Debrid key: no
//                      request, else POST /api/v1/user of the template; a
//                      config -> GET ?raw=true, our fields, PUT (none if it has
//                      no Debrid at all); reset=1 -> PUT of the template with
//                      our fields, no GET. aio_confs written; JSON {ok, msg,
//                      skip, base, conf};
//   GET  ?t=<token>&a=log -> <FS_PREFIX>/tmp/run/melange.log masked, an attachment;
//   GET  ?t=<token>&a=ts_check&ts=<address> -> GET <TorrServer>/echo, JSON
//                      {ok, msg}, nothing written;
//   POST ?t=<token>&a=srv_check (url, user, pass, category, tags) -> login,
//                      app/version, categories, tags of that qBittorrent;
//                      JSON {ok, msg, lines}, nothing written;
//   POST ?t=<token>&a=aio_check&ajax=1 (manifest) -> GET of that manifest.json
//                      (redirects by the rules), JSON {ok, msg}, nothing written;
//   POST ?t=<token>&a=jr_speed&ajax=1 (b | url, key; q) -> search q of the
//                      check in that JacRed, JSON {ok, msg, n, ms}, nothing written;
//                      a wrong address, key, id or q: also bad = "format" (no request);
//   POST ?t=<token>&a=key_check&ajax=1 (what, key) -> the key checked with
//                      Real-Debrid, TorBox or TMDB, JSON {ok, level, msg}, nothing written.
// Input only goes through the rules of common.php; it never reaches exec,
// a path or eval. stderr (dune_apk_cgi_err.log) gets reasons, never values.
// PHP 5.3 syntax; php-cgi has no date.timezone: dates only in the log
// download, after date_default_timezone_set('UTC').

require dirname(dirname(__FILE__)) . '/common.php';
require dirname(__FILE__) . '/aio_conf.php';

define('AIO_CGI_NAME', 'melange');

// The parts of the page: functions and their globals, nothing runs.
require dirname(__FILE__) . '/lib/http.php';
require dirname(__FILE__) . '/lib/checks.php';
require dirname(__FILE__) . '/lib/aio_sync.php';
require dirname(__FILE__) . '/lib/store.php';
require dirname(__FILE__) . '/lib/form.php';

// --- The save of the form.

// The save failed: JSON {ok: false, errors: [{msg, field}]} for the script of
// the page, else the form back.
function aio_cgi_failed($ajax, $code, $errors, $form)
{
    if ($ajax)
        aio_cgi_json($code, array('ok' => false, 'errors' => $errors));
    aio_cgi_send($code, $code === 200 ? 'text/html' : 'text/plain', $form);
}

// Format first (no request out); then each key given is checked with its
// service: a refused one -> nothing written; not checked or no premium ->
// written, said in "notes" of the JSON (lost without JS: 303 to the page).
function aio_cgi_save($dir, $t)
{
    // In the query: a body over post_max_size is dropped.
    $ajax = aio_cgi_param($_GET, 'ajax') === '1';
    // php-cgi drops a body over post_max_size: $_POST is empty.
    $len = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
    if (!$_POST && $len > aio_cgi_post_max())
    {
        aio_cgi_log('save: body over post_max_size');
        $e = array(aio_cgi_err(aio_cgi_l('Слишком длинный ввод', 'The input is too long')));
        aio_cgi_failed($ajax, 200, $e, aio_cgi_form($t, aio_cgi_values(aio_settings_read('')), $e, false));
    }
    $v = aio_cgi_posted();
    // The configs are never in the form: kept as they are in the file.
    $cur = aio_settings_read(aio_cgi_dir_ok($dir) ? $dir : '');
    $v['confs'] = $cur['aio_confs'];
    $v['cur'] = $cur;
    $v['tpl_new'] = $cur['aio_tpl'];
    $v['ajax'] = $ajax;
    list($data, $errors) = aio_cgi_check_values($v);
    if ($errors)
    {
        $bad = array();
        foreach ($errors as $e)
            $bad[] = $e['field'];
        aio_cgi_log('save: bad format of ' . implode(', ', array_unique($bad)));
        aio_cgi_failed($ajax, 200, $errors, aio_cgi_form($t, $v, $errors, false));
    }
    if (!aio_cgi_dir_ok($dir))
    {
        aio_cgi_log('save: no data dir');
        aio_cgi_failed($ajax, 500, array(aio_cgi_err(aio_cgi_l('Не сохранено: нет папки плагина на Дюне',
            'Not saved: no folder of the plugin on the Dune'))), "500 No data dir, nothing written\n");
    }
    $notes = array();
    $f = aio_cgi_fields();
    foreach (array('rd', 'tb', 'tmdb') as $what)
    {
        $k = $f[$what]['set'];
        // A server with a TMDB key of its own: the field is hidden, the key kept unchecked.
        // Only the keys of the chosen source: the own config needs none.
        if ($data[$k] === '' || $data['source'] === 'own' || ($what === 'tmdb' &&
            aio_tmdb_sent(aio_settings_base($data), $data[$k]) === ''))
            continue;
        $r = aio_cgi_key_check($what, $data[$k]);
        if ($r[0] === 'err')
            $errors[] = aio_cgi_err($r[1], $f[$what]['name']);
        else
            $notes[] = array('level' => $r[0], 'msg' => $r[1]);
    }
    if ($errors)
    {
        aio_cgi_log('save: a key refused, nothing written');
        aio_cgi_failed($ajax, 200, $errors, aio_cgi_form($t, $v, $errors, false));
    }
    // Read again just before the write: a=aio_sync of another tab may have
    // written them while the keys were checked.
    $cur = aio_settings_read($dir);
    $data['aio_confs'] = $cur['aio_confs'];
    $why = aio_cgi_store($dir, $data);
    if ($why !== '')
    {
        aio_cgi_log("save: $why");
        aio_cgi_failed($ajax, 500, array(aio_cgi_err(aio_cgi_l('Не сохранено: ошибка записи на Дюне',
            'Not saved: a write error on the Dune'))), "500 Settings not saved\n");
    }
    aio_cgi_log('save: ok');
    // The config there is reset to the template (0.40.0): by its button, or
    // a template chosen other than that of the config (tpl, also 'none' or
    // 'custom'). The script asks first and then posts aio_reset; without it
    // another template resets the config the page showed (the stored server).
    $has = isset($data['aio_confs'][aio_settings_base($data)]);
    $reset = $has && (aio_cgi_param($_POST, 'aio_reset') === '1' || (!$ajax && aio_cgi_same_server($data, $v['cur']) &&
        $v['aio_tpl'] !== '' && $v['aio_tpl'] !== aio_cgi_tpl_shown($data['aio_server'], $data['aio_own_url'],
        $data['aio_confs'], $data['aio_tpl'])));
    // Only the checked token, as in the redirect below. aio: what a=aio_sync
    // is to do next ('' - nothing, the own address).
    if ($ajax)
        aio_cgi_json(200, array_merge(array('ok' => true, 'next' => 'settings?t=' . $t . '&saved=1',
            'aio' => $data['source'] === 'own' ? '' : ($reset ? 'reset' : ($has ? 'update' : 'create'))),
            $notes ? array('notes' => $notes) : array()));
    // Without the script: the config right here; its result is seen on the page (made or not).
    aio_cgi_aio_sync($dir, $reset);
    // Post/Redirect/Get. Relative to this page; only the checked token, no
    // value of the request (and not the Host header).
    header('HTTP/1.0 303 See Other');
    header('Location: settings?t=' . $t . '&saved=1');
    aio_cgi_send(303, 'text/plain', "303 See Other\n");
}

$t = aio_cgi_param($_GET, 't');
$dir = aio_cgi_data_dir($t);
if ($dir === '')
    aio_cgi_forbidden();
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
// "Проверить" of the page, by its script: by POST, the passwords, the keys
// and the manifest address (secrets too) are not in the URL.
if ($method === 'POST' && aio_cgi_param($_GET, 'ajax') === '1')
{
    $a = aio_cgi_param($_GET, 'a');
    if ($a === 'srv_check')
        aio_cgi_srv_check_post();
    if ($a === 'aio_check')
        aio_cgi_aio_check_post();
    if ($a === 'jr_speed')
        aio_cgi_jr_speed_post();
    if ($a === 'key_check')
        aio_cgi_key_check_post();
    if ($a === 'aio_sync')
        aio_cgi_aio_sync_post($dir);
    if ($a === 'aio_status')
        aio_cgi_aio_status_post();
}
// Any other POST with an action is never a save: "Проверить" of a tab opened
// before 0.31.1 posts the whole form with a=ts_check. 303 to the page, nothing written.
if ($method === 'POST' && aio_cgi_param($_POST, 'a') . aio_cgi_param($_GET, 'a') !== '')
{
    aio_cgi_log('post with an action: to the page');
    header('HTTP/1.0 303 See Other');
    header('Location: settings?t=' . $t);
    aio_cgi_send(303, 'text/plain', "303 See Other\n");
}
if ($method === 'POST')
    aio_cgi_save($dir, $t);
if ($method === 'GET' && aio_cgi_param($_GET, 'a') === 'log')
    aio_cgi_log_download($dir);
// "Проверить": the TorrServer of the field (or the default), nothing written.
if ($method === 'GET' && aio_cgi_param($_GET, 'a') === 'ts_check')
{
    $ts = aio_ts_addr(aio_cgi_param($_GET, 'ts'));
    $res = is_string($ts) ? aio_cgi_ts_check(aio_ts_base($ts)) :
        array(false, aio_cgi_l('Нужен адрес вида http://хост:порт или хост:порт', 'An address like http://host:port or host:port is needed'));
    aio_cgi_json(200, array('ok' => $res[0], 'msg' => $res[1]));
}
if ($method === 'GET')
    aio_cgi_send(200, 'text/html', aio_cgi_form($t, aio_cgi_values(aio_settings_read($dir)), array(),
        aio_cgi_param($_GET, 'saved') === '1'));
aio_cgi_send(405, 'text/plain', "405 Method Not Allowed\n");
