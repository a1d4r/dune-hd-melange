<?php
// The settings page (cgi/settings.php): the API of an AIOStreams server -
// /status ("Проверить" of another server) and the config of melange there
// (a=aio_sync, or within a save without the script).

// --- The config of AIOStreams (0.35.0): made and changed on the chosen
// server by "Сохранить" (a=aio_sync of the script, or within the save
// without JS). One config per server, none is ever deleted.

// The reply of /api/v1/user: 1 MB at most; 20 s each (POST and PUT took
// about a second).
define('AIO_CGI_AIO_MAX', 1048576);
define('AIO_CGI_AIO_TIMEOUT', 20);

// The API $what (user, status) of the server $base; with AIO_CGI_TEST_API
// (tests) <stub>/aio/<host[:port]> instead of $base.
function aio_cgi_aio_api($base, $what)
{
    if (defined('AIO_CGI_TEST_API'))
        $base = AIO_CGI_TEST_API . '/aio/' . preg_replace('~^https?://~', '', $base);
    return "$base/api/v1/$what";
}

// One request to the API $what (user by default, status) of $base:
// $method, $json (the body or null), $auth (array(uuid, password) or null)
// -> array(curl errno or 0, HTTP code, the reply decoded by aio_conf_decode
// (null: not JSON or over 1 MB)).
function aio_cgi_aio_req($base, $method, $json, $auth, $what = 'user')
{
    global $AIO_CGI_BODY, $AIO_CGI_MAX;
    $AIO_CGI_BODY = '';
    $AIO_CGI_MAX = AIO_CGI_AIO_MAX;
    $h = array('Accept: application/json', 'Expect:');
    if ($json !== null)
        $h[] = 'Content-Type: application/json';
    if ($auth)
        $h[] = 'Authorization: Basic ' . base64_encode("$auth[0]:$auth[1]");
    $opt = array(
        CURLOPT_WRITEFUNCTION => 'aio_cgi_body',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => AIO_CGI_AIO_TIMEOUT,
        CURLOPT_CAINFO => AIO_CGI_CA,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'AIOStreams-DuneClient/' . AIO_VERSION,
        CURLOPT_HTTPHEADER => $h);
    if ($method !== 'GET')
    {
        $opt[CURLOPT_CUSTOMREQUEST] = $method;
        $opt[CURLOPT_POSTFIELDS] = $json;
    }
    // raw: the config itself, without the parent config the server merges
    // in (a PUT would bake it in); the web settings of AIOStreams ask so too.
    $ch = curl_init(aio_cgi_aio_api($base, $what) . ($what === 'user' && $method === 'GET' ? '?raw=true' : ''));
    $set = curl_setopt_array($ch, $opt);
    $ok = $set ? curl_exec($ch) : false;
    $code = $set ? intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)) : 0;
    $errno = $set ? curl_errno($ch) : -1;
    curl_close($ch);
    $AIO_CGI_MAX = AIO_CGI_MAX_BODY;
    // 23: over 1 MB, cut by aio_cgi_body - not a reply of AIOStreams.
    if ($ok === false && $errno !== 23)
        return array($errno, 0, null);
    return array(0, $code, $errno === 23 ? null : aio_conf_decode($AIO_CGI_BODY));
}

// A refused or failed request -> array(the reason for the page, the error
// code of AIOStreams or ''). The message of the server is shown as it is
// (at most 300 characters, no control bytes); never logged. $own: another
// server (its certificate may be self-signed).
function aio_cgi_aio_why($r, $host, $own = false)
{
    list($e, $code, $j) = $r;
    $ec = is_object($j) && isset($j->error) && is_object($j->error) && isset($j->error->code) &&
        is_string($j->error->code) && preg_match('/^[A-Z0-9_]{1,40}\z/', $j->error->code) ? $j->error->code : '';
    $msg = is_object($j) && isset($j->error) && is_object($j->error) && isset($j->error->message) &&
        is_string($j->error->message) ? trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $j->error->message)) : '';
    // Not UTF-8 (a lone surrogate of JSON): the HTTP code instead.
    if (!preg_match('//u', $msg))
        $msg = '';
    // curl 60, 51: the certificate is not taken.
    if ($own && ($e === 60 || $e === 51))
        return array("$host: " . aio_cgi_l('сертификат сервера не принят (самоподписанный?) — попробуйте http://',
            'the certificate of the server is not accepted (self-signed?): try http://'), '');
    if ($e)
        return array("$host: " . aio_cgi_srv_net($e, 'AIOStreams'), '');
    if ($code === 429)
        return array(sprintf(aio_cgi_l('лимит запросов сервера %s, повторите через минуту',
            'the request limit of the server %s, try again in a minute'), $host), $ec);
    if ($msg !== '')
    {
        preg_match('/^.{0,300}/su', $msg, $m);
        return array(sprintf(aio_cgi_l('%s ответил: %s', '%s answered: %s'), $host, $m[0]), $ec);
    }
    return array($code >= 200 && $code < 300 ? sprintf(aio_cgi_l('%s: неожиданный ответ сервера',
        '%s: an unexpected reply of the server'), $host) : sprintf(aio_cgi_l('%s ответил HTTP %d', '%s answered HTTP %d'),
        $host, $code), $ec);
}

// GET /api/v1/status of the server $base (no limit of /user there) ->
// array(ok, version or the reason (aio_cgi_aio_why), it has TMDB of its own).
function aio_cgi_aio_status($base)
{
    $r = aio_cgi_aio_req($base, 'GET', null, null, 'status');
    $d = aio_cgi_aio_ok($r) && isset($r[2]->data) && is_object($r[2]->data) ? $r[2]->data : null;
    $v = $d && isset($d->version) && is_string($d->version) && preg_match('/^[0-9A-Za-z.+\-]{1,40}\z/', $d->version) ?
        $d->version : '';
    $t = $d && isset($d->settings->metadata->tmdb) && is_object($d->settings->metadata->tmdb) ? $d->settings->metadata->tmdb :
        null;
    aio_cgi_log('aio status: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($v !== '' ? ', AIOStreams' : ''));
    if ($v === '')
    {
        if ($r[0] || !aio_cgi_aio_ok($r))
        {
            list($why) = aio_cgi_aio_why($r, (string) parse_url($base, PHP_URL_HOST), true);
            return array(false, $why, false);
        }
        return array(false, aio_cgi_l('Ответ не похож на AIOStreams', 'The reply does not look like AIOStreams'), false);
    }
    return array(true, $v, $t && ((isset($t->accessToken) && $t->accessToken === true) ||
        (isset($t->apiKey) && $t->apiKey === true)));
}

// a=aio_status: the own server of the posted field (url), nothing written. -> JSON {ok, level, msg}.
function aio_cgi_aio_status_post()
{
    $base = aio_aio_own(aio_cgi_param($_POST, 'url'));
    if ($base === '')
        aio_cgi_json(200, array('ok' => false, 'level' => 'err', 'msg' => aio_cgi_l(
            'Нужен адрес вида http(s)://хост[:порт] без пути', 'An address like http(s)://host[:port] without a path is needed')));
    $r = aio_cgi_aio_status($base);
    if (!$r[0])
        aio_cgi_json(200, array('ok' => false, 'level' => 'err', 'msg' => $r[1]));
    aio_cgi_json(200, array('ok' => true, 'level' => $r[2] ? 'ok' : 'warn', 'msg' => "AIOStreams $r[1] · " . ($r[2] ?
        aio_cgi_l('свой TMDB есть', 'TMDB of its own') : aio_cgi_l('своего TMDB нет: сверка названий — только с ключом TMDB',
        'no TMDB of its own: titles are matched only with a TMDB key'))));
}

// The reply of a request is a success of AIOStreams: 2xx, success true.
function aio_cgi_aio_ok($r)
{
    return !$r[0] && $r[1] >= 200 && $r[1] < 300 && is_object($r[2]) && isset($r[2]->success) && $r[2]->success === true;
}

// aio_confs[$base] of settings.json set to $c (null: forgotten), the rest as
// it is now in the file. -> '' or the reason (for the log).
function aio_cgi_aio_keep($dir, $base, $c)
{
    $s = aio_settings_read($dir);
    if ($c)
        $s['aio_confs'][$base] = $c;
    else
        unset($s['aio_confs'][$base]);
    return aio_cgi_store($dir, $s);
}

// The config of the chosen server (of the list or the own one) by
// settings.json: none -> POST of the template (aio_tpl) with our fields;
// there -> GET, its template by its presets stored (tpl), our fields, PUT;
// $reset (0.40.0) -> PUT of the template with our fields as for a new one,
// no GET (manual edits lost; the login, password and manifest stay). The
// own server: its TMDB by /status first.
// The own config chosen -> nothing (skip). No config and no Debrid key, or
// a reset without one -> no request.
// -> array(ok, the line for the page, skip, the choice (aio_server),
// aio_cgi_aio_show() of its config now[, the name= of the field a failure
// is about: no Debrid key, the own server not taken]).
function aio_cgi_aio_sync($dir, $reset = false)
{
    set_time_limit(120);
    $s = aio_settings_read($dir);
    $f = aio_cgi_fields();
    $choice = $s['aio_server'];
    $base = aio_settings_base($s);
    $host = (string) parse_url($base, PHP_URL_HOST);
    $conf = $base !== '' && isset($s['aio_confs'][$base]) ? $s['aio_confs'][$base] : null;
    $id = $s['aio_tpl'];
    // No config: made by the template.
    $reset = $reset && $conf;
    if ($s['source'] === 'own' || $base === '')
    {
        aio_cgi_log('aio sync: own config or no address, skipped');
        return array(true, '', true, $choice, aio_cgi_aio_show($conf));
    }
    $fail = $reset ? aio_cgi_l('Конфиг не сброшен: ', 'The config is not reset: ') : ($conf ?
        aio_cgi_l('Конфиг не обновлён: ', 'The config is not updated: ') : aio_cgi_l('Конфиг не создан: ', 'No config made: '));
    // The template is stored already: "Сохранить" again only updates.
    $again = $reset ? aio_cgi_l('; повторить — «Сбросить к шаблону»', '; to retry: "Reset to the template"') : '';
    // A config there may have a service of its own (set by hand): GET decides.
    if (!$conf && $s['rd_key'] === '' && $s['tb_key'] === '')
    {
        aio_cgi_log('aio sync: no Debrid key');
        return array(false, aio_cgi_l('Для конфига нужен ключ Debrid (Real-Debrid или TorBox)',
            'The config needs a Debrid key (Real-Debrid or TorBox)'), false, $choice, null, $f['rd']['name']);
    }
    // The template has no service of its own.
    if ($reset && $s['rd_key'] === '' && $s['tb_key'] === '')
    {
        aio_cgi_log("aio sync: reset ($id): no Debrid key, not changed");
        return array(false, $fail . aio_cgi_l('нужен ключ Debrid в Melange', 'a Debrid key in Melange is needed') . $again,
            false, $choice, aio_cgi_aio_show($conf), $f['rd']['name']);
    }
    $tpl = aio_conf_template($id);
    if (!$tpl)
    {
        aio_cgi_log('aio sync: no template');
        return array(false, $fail . aio_cgi_l('нет шаблона в плагине', 'no template in the plugin') . $again, false,
            $choice, aio_cgi_aio_show($conf));
    }
    $tmdbs = null;
    if ($choice === 'own')
    {
        list($ok, $why, $tmdbs) = aio_cgi_aio_status($base);
        if (!$ok)
            return array(false, $fail . $why . $again, false, $choice, aio_cgi_aio_show($conf), $f['aio_own']['name']);
    }
    if (!$conf)
    {
        $c = aio_conf_template($id);
        list($tmdb, $set) = aio_conf_patch($c, $tpl, aio_conf_params($s, null, $tmdbs));
        $pass = aio_cgi_rand_hex(12);
        $r = aio_cgi_aio_req($base, 'POST', aio_conf_encode((object) array('config' => $c, 'password' => $pass)), null);
        $d = aio_cgi_aio_ok($r) && isset($r[2]->data) && is_object($r[2]->data) ? $r[2]->data : null;
        $uuid = $d && isset($d->uuid) && is_string($d->uuid) ? $d->uuid : '';
        $enc = $d && isset($d->encryptedPassword) && is_string($d->encryptedPassword) &&
            preg_match('/^[A-Za-z0-9._~%=+\-]{1,1500}\z/', $d->encryptedPassword) ? $d->encryptedPassword : '';
        $new = $enc !== '' ? aio_aio_conf($base, array('uuid' => $uuid, 'pass' => $pass,
            'manifest' => "$base/stremio/$uuid/$enc/manifest.json", 'tmdb' => $tmdb, 'set' => $set, 'tpl' => $id)) : null;
        aio_cgi_log('aio sync: create: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($new ? ', made' : ''));
        if (!$new)
        {
            list($why, $ec) = aio_cgi_aio_why($r, $host, $choice === 'own');
            if ($ec !== '')
                aio_cgi_log("aio sync: $ec");
            return array(false, $fail . $why, false, $choice, null);
        }
        $w = aio_cgi_aio_keep($dir, $base, $new);
        if ($w !== '')
        {
            aio_cgi_log("aio sync: $w");
            return array(false, aio_cgi_l('Конфиг создан, но не записан на Дюне — ошибка записи',
                'The config is made but not written on the Dune: a write error'), false, $choice, null);
        }
        return array(true, sprintf(aio_cgi_l('Конфиг AIOStreams создан на %s', 'The AIOStreams config is made on %s'),
            $host), false, $choice, aio_cgi_aio_show($new));
    }
    $auth = array($conf['uuid'], $conf['pass']);
    if ($reset)
    {
        $u = aio_conf_template($id);
        list($tmdb, $set) = aio_conf_patch($u, $tpl, aio_conf_params($s, null, $tmdbs));
        $r = aio_cgi_aio_req($base, 'PUT', aio_conf_encode((object) array('config' => $u)), $auth);
        aio_cgi_log("aio sync: reset ($id): put: " . ($r[0] ? "curl $r[0]" : "HTTP $r[1]"));
    }
    else
    {
        $r = aio_cgi_aio_req($base, 'GET', null, $auth);
        $u = aio_cgi_aio_ok($r) && isset($r[2]->data) && is_object($r[2]->data) && isset($r[2]->data->userData) &&
            is_object($r[2]->data->userData) ? $r[2]->data->userData : null;
        aio_cgi_log('aio sync: get: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($u ? ', config' : ''));
    }
    if ($u && !$reset)
    {
        // As it is there, before our fields; stored even if the PUT fails.
        $t = aio_conf_tpl_of($u);
        aio_cgi_log("aio sync: template $t");
        if ($t !== $conf['tpl'])
        {
            $conf['tpl'] = $t;
            $w = aio_cgi_aio_keep($dir, $base, $conf);
            if ($w !== '')
                aio_cgi_log("aio sync: $w");
        }
        list($tmdb, $set) = aio_conf_patch($u, $tpl, aio_conf_params($s, $conf, $tmdbs));
        // No key of melange and none set by hand: PUT would get 400.
        if (!aio_conf_has_debrid($u))
        {
            aio_cgi_log('aio sync: no Debrid key, not changed');
            return array(false, aio_cgi_l('Конфиг не изменён: нужен хотя бы один ключ Debrid',
            'The config is not changed: at least one Debrid key is needed'), false, $choice, aio_cgi_aio_show($conf),
            $f['rd']['name']);
        }
        $r = aio_cgi_aio_req($base, 'PUT', aio_conf_encode((object) array('config' => $u)), $auth);
        aio_cgi_log('aio sync: put: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]"));
    }
    if (!$u || !aio_cgi_aio_ok($r))
    {
        list($why, $ec) = aio_cgi_aio_why($r, $host, $choice === 'own');
        if ($ec !== '')
            aio_cgi_log("aio sync: $ec");
        // The login and password are not taken any more: the config is
        // gone there (or the server lost its key). Forgotten here: the next
        // save makes a new one.
        if ($ec === 'USER_INVALID_DETAILS')
        {
            $w = aio_cgi_aio_keep($dir, $base, null);
            if ($w !== '')
                aio_cgi_log("aio sync: $w");
            return array(false, sprintf(aio_cgi_l('Сервер %s не узнал конфиг (удалён на сервере или сменён пароль?) — ' .
                'Melange его забыл; «Сохранить и создать конфиг» создаст новый',
                'The server %s does not know the config (deleted there or its password changed?): ' .
                'Melange forgot it; "Save and create the config" makes a new one'), $host), false, $choice,
                $w === '' ? null : aio_cgi_aio_show($conf));
        }
        return array(false, $fail . $why . $again, false, $choice, aio_cgi_aio_show($conf));
    }
    $conf['tmdb'] = $tmdb;
    $conf['set'] = $set;
    if ($reset)
        $conf['tpl'] = $id;
    $w = aio_cgi_aio_keep($dir, $base, $conf);
    if ($w !== '')
        aio_cgi_log("aio sync: $w");
    $tpls = aio_cgi_tpls();
    return array(true, $reset ? sprintf(aio_cgi_l('Конфиг AIOStreams на %s сброшен к шаблону «%s»',
        'The AIOStreams config on %s is reset to the template "%s"'), $host, $tpls[$id][0]) :
        sprintf(aio_cgi_l('Конфиг AIOStreams на %s обновлён', 'The AIOStreams config on %s is updated'), $host),
        false, $choice, aio_cgi_aio_show($conf));
}

// a=aio_sync (reset=1: to the template): JSON {ok, msg, skip, base, conf: {cfg, login, pass} | null[, field]}.
function aio_cgi_aio_sync_post($dir)
{
    if (!aio_cgi_dir_ok($dir))
        aio_cgi_json(200, array('ok' => false, 'msg' => aio_cgi_l('Нет папки плагина на Дюне',
            'No folder of the plugin on the Dune'), 'skip' => false, 'base' => '', 'conf' => null));
    $r = aio_cgi_aio_sync($dir, aio_cgi_param($_POST, 'reset') === '1');
    $j = array('ok' => $r[0], 'msg' => $r[1], 'skip' => $r[2], 'base' => $r[3], 'conf' => $r[4]);
    if (isset($r[5]))
        $j['field'] = $r[5];
    aio_cgi_json(200, $j);
}
