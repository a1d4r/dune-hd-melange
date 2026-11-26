<?php
// Settings page of melange (www/cgi-bin/settings -> php-cgi): root on the
// device, open to the whole LAN without a password. Without the token of
// <data_dir>/web_token every request gets the same 403, with no
// diagnostics and no outgoing request (no data dir at all: 500, a hint).
//   GET  ?t=<token> -> the form with the current values, in the language of
//                      the Dune (mel_cgi_lang);
//   POST ?t=<token> -> format (no request out) -> the keys given checked with
//                      their services (a refused one: nothing written) ->
//                      write settings.json v 2 (aio_confs and the other sources kept;
//                      the source of the page - prof, lib/profiles.php - and the shared fields) -> the
//                      config of AIOStreams as a=aio_sync -> 303 to GET
//                      ?t=<token>&p=<id>&saved=1 (a reload does not post again);
//                      errors -> the form back (only without JS);
//   POST ?t=<token>&ajax=1 -> the same save without the config, JSON {ok,
//                      next, aio[, rows][, notes], prof, profiles | errors: [{msg, field}]}
//                      to the script of the page (prof: the source saved, {id, name, main};
//                      profiles: the list now)
//                      (aio: create, update, reset (0.40.0: its button or
//                      another template) or '' - what a=aio_sync is to do):
//                      no page is ever an answer to a POST (a mobile browser
//                      would post it again on return);
//   POST ?t=<token>&a=aio_status&ajax=1 (url) -> GET /api/v1/status of that
//                      own AIOStreams server: version, TMDB of its own; JSON
//                      {ok, level, msg}, nothing written;
//   POST ?t=<token>&a=aio_sync&ajax=1 (prof: the id of the source; none - the main one)
//                      -> the config of AIOStreams of that source: on its
//                      server (of the list or the own one, 0.35.1) in
//                      its mode (a config per mode): an own config or Lampac -> nothing; the
//                      own server -> its TMDB by /status first; no config
//                      there -> no Debrid key (mode Debrid): no
//                      request, else POST /api/v1/user of the template; a
//                      config -> GET ?raw=true, our fields, PUT (none if it has
//                      no Debrid at all, mode Debrid); reset=1 -> PUT of the template with
//                      our fields, no GET. aio_confs written; JSON {ok, msg,
//                      skip, base, mode, conf[, field], profiles} (field: as of a save error;
//                      profiles: those of the file after it);
//   GET  ?t=<token>&a=log -> <FS_PREFIX>/tmp/run/melange.log masked, an attachment;
//   GET  ?t=<token>&a=export -> settings.json as read, with web_token (0.51.0), an
//                      attachment (0.50.0); none saved yet: 404, a text;
//   POST ?t=<token>&a=import&ajax=1 (settings: the text of an exported file)
//                      -> its settings by the rules of mel_settings_read written
//                      whole, its aio_confs over those of this Dune; no key
//                      checked, no request out; its web_token (0.51.0)
//                      written over this one; JSON {ok, next | msg}
//                      (lib/backup.php); GET ?t=<token>&imported=1 - the form
//                      with "loaded from the file", imported=2 - and "the link
//                      not restored";
//   POST ?t=<token>&a=ts_check&ajax=1 (ts, ts_user, ts_pass) -> GET
//                      <TorrServer>/echo, then POST /settings with the login
//                      (0.45.0: is one asked for, taken), JSON {ok, msg[,
//                      level]}, nothing written;
//   POST ?t=<token>&a=lp_check&ajax=1 (lampac_url, lampac_token, lampac_as_lampa)
//                      -> /version?type=build, then the search of a film to its
//                      end (lampac.php) in that Lampac, with that token and as
//                      Lampa or not, JSON {ok, msg, level}, nothing written;
//   POST ?t=<token>&a=srv_check (type, url, user, pass, category, tags) ->
//                      login, app/version, categories, tags of that
//                      qBittorrent; type tr (0.47.0): session-get of that
//                      Transmission; JSON {ok, msg, lines}, nothing written;
//   POST ?t=<token>&a=aio_check&ajax=1 (manifest) -> GET of that manifest.json
//                      (redirects by the rules), JSON {ok, msg}, nothing written;
//   POST ?t=<token>&a=jr_speed&ajax=1 (b | url, key; q) -> search q of the
//                      check in that JacRed, JSON {ok, msg, n, ms}, nothing written;
//                      a wrong address, key, id or q: also bad = "format" (no request);
//   POST ?t=<token>&a=key_check&ajax=1 (what, key) -> the key checked with
//                      Real-Debrid, TorBox or TMDB, JSON {ok, level, msg}, nothing written;
//   POST ?t=<token>&a=cat_rows&ajax=1 -> the rows of the catalog (lib/rows.php),
//                      JSON {ok, stale, rows: [{key, name, group, home}]}, nothing written;
//   GET  ?t=<token>&p=<id>|new[&from=<id>] -> the page is the editor of that source (v2,
//                      lib/profiles.php; new: a new one, the copy of from; none: the main
//                      one): its settings in the fields, prof=<id>|new posted back, the
//                      save saves it, a=aio_sync&prof=<id> its config;
//   POST ?t=<token>&a=profile_main|profile_del&ajax=1 (i: the id) -> the main source,
//                      one deleted; JSON {ok, msg[, profiles]}, settings.json written whole;
//   POST ?t=<token>&a=title_unhide&ajax=1 (k: the key of a title) -> "Вернуть" of "Скрытые"
//                      (0.65.0, lib/hidden.php): that title out of data_dir/hidden_titles.json as it is
//                      now, JSON {ok, msg[, items]}.
// Input only goes through the rules of common.php; it never reaches exec,
// a path or eval. stderr (dune_apk_cgi_err.log) gets reasons, never values.
// PHP 5.3 syntax; php-cgi has no date.timezone: dates only in the log
// download, after date_default_timezone_set('UTC').

require dirname(dirname(__FILE__)) . '/common.php';
require dirname(dirname(__FILE__)) . '/net.php';
require dirname(dirname(__FILE__)) . '/lampac.php';
require dirname(__FILE__) . '/aio_conf.php';

define('MEL_CGI_NAME', 'melange');

// The parts of the page: functions and their globals, nothing runs.
require dirname(__FILE__) . '/lib/http.php';
require dirname(__FILE__) . '/lib/checks.php';
require dirname(__FILE__) . '/lib/aio_sync.php';
require dirname(__FILE__) . '/lib/store.php';
require dirname(__FILE__) . '/lib/log.php';
require dirname(__FILE__) . '/lib/form.php';
require dirname(__FILE__) . '/lib/values.php';
require dirname(__FILE__) . '/lib/home.php';
require dirname(__FILE__) . '/lib/backup.php';
require dirname(__FILE__) . '/lib/profiles.php';
require dirname(__FILE__) . '/lib/hidden.php';

// --- The save of the form.

// The save failed: JSON {ok: false, errors: [{msg, field}]} for the script of
// the page, else the form back.
function mel_cgi_failed($ajax, $code, $errors, $form)
{
    if ($ajax)
        mel_cgi_json($code, array('ok' => false, 'errors' => $errors));
    mel_cgi_send($code, $code === 200 ? 'text/html' : 'text/plain', $form);
}

// Format first (no request out); then each key given is checked with its
// service: a refused one -> nothing written; not checked or no premium ->
// written, said in "notes" of the JSON (lost without JS: 303 to the page).
function mel_cgi_save($dir, $t)
{
    // In the query: a body over post_max_size is dropped.
    $ajax = mel_cgi_param($_GET, 'ajax') === '1';
    // php-cgi drops a body over post_max_size: $_POST is empty.
    $len = isset($_SERVER['CONTENT_LENGTH']) ? intval($_SERVER['CONTENT_LENGTH']) : 0;
    if (!$_POST && $len > mel_cgi_post_max())
    {
        mel_cgi_log('save: body over post_max_size');
        $e = array(mel_cgi_err(mel_cgi_l('Слишком длинный ввод', 'The input is too long')));
        mel_cgi_failed($ajax, 200, $e, mel_cgi_form($t, mel_cgi_values(mel_settings_read('')), $e, false));
    }
    $v = mel_cgi_posted();
    $f = mel_cgi_fields();
    // The configs are never in the form: kept as they are in the file.
    $cur = mel_settings_read(mel_cgi_dir_ok($dir) ? $dir : '', $why);
    if ($why !== '')
        mel_cgi_log("save: settings.json $why");
    // The editor of a source (lib/profiles.php): the fields of the source are of it, checked by its settings.
    $ed = mel_cgi_editor_posted($v, $cur);
    $view = $ed['view'];
    $v['prof'] = mel_cgi_profiles_data($cur);
    $v['pf_def'] = mel_cgi_editor_def($view, $v['watch'] === 'lampac');
    $v['pf_list'] = mel_cgi_editor_list($cur, $ed['old'] ? $ed['old']['id'] : 'new');
    $v['confs'] = $view['aio_confs'];
    $v['cur'] = $view;
    $v['tpl_new'] = $view['aio_tpl'];
    $v['tpl_p2p_new'] = $view['aio_tpl_p2p'];
    $v['mode_cur'] = $view['aio_mode'];
    // None posted (a form of 0.44 or older): the stored one, saved and checked on a page back.
    if ($v['ts_save'] === '')
        $v['ts_save'] = mel_cgi_ts_save_of($cur['ts_save']);
    if ($v['markers'] === '')
        $v['markers'] = mel_cgi_markers_of($cur['markers'], $cur['markers_exact']);
    foreach (mel_cgi_toggles() as $k => $x)
    {
        if ($v[$k] === '')
            $v[$k] = mel_cgi_toggle_of($k, $view[$f[$k]['set']]);
    }
    if ($v['cat_age'] === '')
        $v['cat_age'] = mel_cgi_cat_age_of($cur['cat_age']);
    // "Скрытые" of a form back (lib/hidden.php).
    $v['hidden'] = mel_cgi_ht_data(mel_cgi_dir_ok($dir) ? $dir : '');
    // '' is a choice here (none made), only no field at all means an older form.
    if (!isset($_POST['cat_rows']))
        $v['cat_rows'] = mel_cgi_cat_rows_of($cur['cat_rows']);
    $v['ajax'] = $ajax;
    $v['reset'] = mel_cgi_param($_POST, 'aio_reset') === '1';
    $v['first'] = !$cur['saved_once'];
    list($data, $errors) = mel_cgi_check_values($v);
    if ($errors)
    {
        $bad = array();
        foreach ($errors as $e)
            $bad[] = $e['field'];
        mel_cgi_log('save: bad format of ' . implode(', ', array_unique($bad)));
        mel_cgi_failed($ajax, 200, $errors, mel_cgi_form($t, $v, $errors, false));
    }
    // The source of the editor checked before the keys (no request for a save refused): again just before the write.
    mel_cgi_editor_check($ed, $data, $cur, $t, $v);
    if (!mel_cgi_dir_ok($dir))
    {
        mel_cgi_log('save: no data dir');
        mel_cgi_failed($ajax, 500, array(mel_cgi_err(mel_cgi_l('Не сохранено: нет папки плагина на Дюне',
            'Not saved: the plugin data folder is missing on the Dune'))), "500 No data dir, nothing written\n");
    }
    $notes = array();
    foreach (array('rd', 'tb', 'tmdb') as $what)
    {
        $k = $f[$what]['set'];
        // A server with a TMDB key of its own: the field is hidden, the key kept unchecked.
        // Only the keys of the chosen source: the own config and Lampac need none, nor a config of P2P a Debrid one.
        if ($data[$k] === '' || $data['source'] === 'own' || $v['watch'] === 'lampac' || ($what === 'tmdb' ?
            mel_tmdb_sent(mel_settings_base($data), $data[$k]) === '' : $data['aio_mode'] === MEL_MODE_P2P))
            continue;
        $r = mel_cgi_key_check($what, $data[$k]);
        if ($r[0] === 'err')
            $errors[] = mel_cgi_err($r[1], $f[$what]['name']);
        else
            $notes[] = array('level' => $r[0], 'msg' => $r[1]);
    }
    if ($errors)
    {
        mel_cgi_log('save: a key refused, nothing written');
        mel_cgi_failed($ajax, 200, $errors, mel_cgi_form($t, $v, $errors, false));
    }
    // Read again just before the write: a=aio_sync of another tab may have
    // written them while the keys were checked.
    $cur = mel_settings_read($dir);
    $data['aio_confs'] = $cur['aio_confs'];
    // The balancers of Lampac: of each source of it, from the file (the Dune writes them); none at the top (v2).
    $data['lampac_sources'] = array();
    // "Профили источников" of before v2 (no field now): as stored; mel_settings_json sets it for a version of before.
    $data['src_profiles'] = $cur['src_profiles'];
    // The rows not changed on the page (posted as it came, cat_rows_was): the stored ones stay, the
    // dialog of the rows on the Dune may have changed them since. No cat_rows_was: a form of before.
    if (isset($_POST[$f['cat_rows_was']['name']]) && $v['cat_rows'] === $v['cat_rows_was'])
        $data[$f['cat_rows']['set']] = $cur['cat_rows'];
    // The sources of the file (the Dune writes the balancers of Lampac), the one of the editor saved over them; the main
    // one as stored (a=profile_main, a=profile_del change it; the first source saved is it).
    list($data, $prof) = mel_cgi_editor_check($ed, $data, $cur, $t, $v);
    $pk = mel_profile_index($data, $prof['id']);
    mel_cgi_log('save: source ' . mel_profile_tag($pk, $prof) . ($prof['id'] === $data['src_main'] ? ', the main one' : '') .
        ($ed['old'] ? '' : ', new'));
    $why = mel_cgi_store($dir, $data);
    if ($why !== '')
    {
        mel_cgi_log("save: $why");
        mel_cgi_failed($ajax, 500, array(mel_cgi_err(mel_cgi_l('Не сохранено: ошибка записи на Дюне',
            'Not saved: write error on the Dune'))), "500 Settings not saved\n");
    }
    mel_cgi_log('save: ok');
    // "Основной Debrid": its own file, written only when another one than the page showed is posted.
    $dm = $v['debrid_main'];
    if ($dm !== $v['debrid_main_was'] && array_key_exists($dm, mel_debrid_mains()))
    {
        $why = mel_cgi_write($dir, $dm, MEL_DEBRID_MAIN_FILE);
        mel_cgi_log('save: debrid_main ' . ($why !== '' ? $why : 'written'));
    }
    // The config there (of the mode chosen) is reset to the template (0.40.0): mel_cgi_resets. That of the source of
    // the editor: its settings, its config; Lampac - none.
    $src = mel_profile_view($data, $prof);
    $lp = !mel_profile_rel($prof);
    $has = (bool) mel_settings_conf($src);
    $reset = !$lp && mel_cgi_resets($src, $src['aio_confs'], $v);
    // Other rows of the catalog, or their order: the catalog on the Dune takes them only
    // when it opens next, the page says so (rows=1, "rows" of the JSON).
    $rows = $data[$f['cat_rows']['set']] !== $cur['cat_rows'];
    // Other keys of the same screen (the start rows for nothing chosen, the rows of Melange on their places by
    // default, keys gone from the list): no change. Without a copy of the manifest the screen is not known: a change.
    if ($rows)
    {
        require dirname(__FILE__) . '/lib/rows.php';
        $rows = !mel_cgi_cat_same($dir, $cur['cat_rows'], $data[$f['cat_rows']['set']]);
    }
    // The page of the source saved (a new one has its id now).
    $next = 'settings?t=' . $t . '&p=' . $prof['id'] . '&saved=1' . ($rows ? '&rows=1' : '');
    // Only the checked token, as in the redirect below. aio: what a=aio_sync
    // is to do next ('' - nothing: the own address, Lampac). prof: the source saved - its id (a=aio_sync&prof), name, the
    // main one; profiles: the list now.
    if ($ajax)
        mel_cgi_json(200, array_merge(array('ok' => true, 'next' => $next,
            'aio' => $lp || $src['source'] === 'own' ? '' : ($reset ? 'reset' : ($has ? 'update' : 'create'))),
            $rows ? array('rows' => true) : array(), $notes ? array('notes' => $notes) : array(),
            array('prof' => array('id' => $prof['id'], 'name' => $prof['name'], 'main' => $prof['id'] === $data['src_main']),
            'profiles' => mel_cgi_profiles_data($data))));
    // Without the script: the config right here; its result is seen on the page (made or not).
    if (!$lp)
        mel_cgi_aio_sync($dir, $reset, $prof['id']);
    // Post/Redirect/Get. Relative to this page; only the checked token, no
    // value of the request (and not the Host header).
    header('HTTP/1.0 303 See Other');
    header('Location: ' . $next);
    mel_cgi_send(303, 'text/plain', "303 See Other\n");
}

$t = mel_cgi_param($_GET, 't');
$dir = mel_cgi_data_dir($t);
if ($dir === '')
    mel_cgi_forbidden();
$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
// "Проверить" of the page, by its script: by POST, the passwords, the keys
// and the manifest address (secrets too) are not in the URL.
if ($method === 'POST' && mel_cgi_param($_GET, 'ajax') === '1')
{
    $a = mel_cgi_param($_GET, 'a');
    if ($a === 'srv_check')
        mel_cgi_srv_check_post();
    if ($a === 'ts_check')
        mel_cgi_ts_check_post();
    if ($a === 'lp_check')
        mel_cgi_lp_check_post($dir);
    if ($a === 'aio_check')
        mel_cgi_aio_check_post();
    if ($a === 'jr_speed')
        mel_cgi_jr_speed_post();
    if ($a === 'key_check')
        mel_cgi_key_check_post();
    if ($a === 'aio_sync')
        mel_cgi_aio_sync_post($dir);
    if ($a === 'aio_status')
        mel_cgi_aio_status_post();
    if ($a === 'import')
        mel_cgi_import_post($dir, $t);
    if ($a === 'cat_rows')
    {
        require dirname(__FILE__) . '/lib/rows.php';
        mel_cgi_cat_rows_post($dir);
    }
    if ($a === 'profile_main' || $a === 'profile_del')
        mel_cgi_profile_post($dir, $a);
    if ($a === 'title_unhide')
        mel_cgi_ht_unhide_post($dir);
}
// Any other POST with an action is never a save: "Проверить" of a tab opened
// before 0.31.1 posts the whole form with a=ts_check. 303 to the page, nothing written.
if ($method === 'POST' && mel_cgi_param($_POST, 'a') . mel_cgi_param($_GET, 'a') !== '')
{
    mel_cgi_log('post with an action: to the page');
    header('HTTP/1.0 303 See Other');
    header('Location: settings?t=' . $t);
    mel_cgi_send(303, 'text/plain', "303 See Other\n");
}
if ($method === 'POST')
    mel_cgi_save($dir, $t);
if ($method === 'GET' && mel_cgi_param($_GET, 'a') === 'log')
    mel_cgi_log_download($dir);
if ($method === 'GET' && mel_cgi_param($_GET, 'a') === 'export')
    mel_cgi_export($dir, $t);
if ($method === 'GET')
{
    $s = mel_settings_read($dir, $why);
    if ($why !== '')
        mel_cgi_log("form: settings.json $why");
    if ($s['mig_lost'])
        mel_cgi_log('form: settings.json of before v2, ' . MEL_PROFILES_MAX . ' profiles already: no profile of ' .
            implode(', ', $s['mig_lost']));
    // The page of the source p (lib/profiles.php; p=new: a new one, the copy of from).
    $ed = mel_cgi_editor($s, mel_cgi_param($_GET, 'p'), mel_cgi_param($_GET, 'from'));
    $v = mel_cgi_editor_values(mel_cgi_values($ed['view']), $ed, $s);
    $v['debrid_main'] = mel_debrid_main_read($dir);
    $v['debrid_main_was'] = $v['debrid_main'];
    $v['first'] = !$s['saved_once'];
    $v['hidden'] = mel_cgi_ht_data($dir);
    $imp = mel_cgi_param($_GET, 'imported');
    mel_cgi_send(200, 'text/html', mel_cgi_form($t, $v, array(), mel_cgi_param($_GET, 'saved') === '1',
        in_array($imp, array('1', '2'), true) ? intval($imp) : 0, mel_cgi_param($_GET, 'rows') === '1'));
}
mel_cgi_send(405, 'text/plain', "405 Method Not Allowed\n");
