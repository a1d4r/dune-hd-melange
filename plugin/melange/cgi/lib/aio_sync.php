<?php
// The settings page (cgi/settings.php): the API of an AIOStreams server -
// /status ("Проверить" of another server) and the config of melange there
// (a=aio_sync, or within a save without the script).

// --- The config of AIOStreams (0.35.0): made and changed on the chosen
// server by "Сохранить" (a=aio_sync of the script, or within the save
// without JS). A config per server and mode (aio_mode), none is ever deleted.

// The API $what (user, status) of the server $base; with MEL_CGI_TEST_API
// (tests) <stub>/aio/<host[:port]> instead of $base.
function mel_cgi_aio_api($base, $what)
{
    if (defined('MEL_CGI_TEST_API'))
        $base = MEL_CGI_TEST_API . '/aio/' . preg_replace('~^https?://~', '', $base);
    return "$base/api/v1/$what";
}

// One request to the API $what (user by default, status) of $base:
// $method, $json (the body or null), $auth (array(uuid, password) or null)
// -> array(curl errno or 0, HTTP code, the reply decoded by mel_conf_decode
// (null: not JSON or over 1 MB)).
function mel_cgi_aio_req($base, $method, $json, $auth, $what = 'user')
{
    $url = mel_cgi_aio_api($base, $what);
    $r = mel_cgi_curl($what === 'user' ? mel_conf_user_url($url, $method) : $url, array(
        'method' => $method,
        'body' => $json,
        'headers' => mel_conf_api_headers($json, $auth),
        'connect' => 5,
        'total' => MEL_CONF_API_TIMEOUT,
        'max' => MEL_CONF_API_MAX,
        'ua' => 'AIOStreams-DuneClient/' . MEL_VERSION,
        'gzip' => true));
    if ($r['errno'])
        return array($r['errno'], 0, null);
    // Over 1 MB, cut: not a reply of AIOStreams.
    return array(0, $r['code'], $r['cut'] ? null : mel_conf_decode($r['body']));
}

// A refused or failed request -> array(the reason for the page, the error
// code of AIOStreams or ''). The message of the server is shown as it is
// (at most 300 characters, no control bytes); never logged. $own: another
// server (its certificate may be self-signed).
function mel_cgi_aio_why($r, $host, $own = false)
{
    list($e, $code, $j) = $r;
    // Not UTF-8 (a lone surrogate of JSON): no message, the HTTP code instead.
    list($ec, $msg) = mel_conf_err($j);
    // curl 60, 51: the certificate is not taken.
    if ($own && ($e === 60 || $e === 51))
        return array("$host: " . mel_cgi_l('сертификат сервера не принят (самоподписанный?) — попробуйте http://',
            'the server certificate is not accepted (self-signed?): try http://'), '');
    if ($e)
        return array("$host: " . mel_cgi_srv_net($e, 'AIOStreams'), '');
    if ($code === 429)
        return array(sprintf(mel_cgi_l('лимит запросов сервера %s, повторите через минуту',
            'server %s request limit reached, try again in a minute'), $host), $ec);
    if ($msg !== '')
    {
        preg_match('/^.{0,300}/su', $msg, $m);
        return array(sprintf(mel_cgi_l('%s ответил: %s', '%s answered: %s'), $host, $m[0]), $ec);
    }
    return array($code >= 200 && $code < 300 ? sprintf(mel_cgi_l('%s: неожиданный ответ сервера',
        '%s: unexpected reply from the server'), $host) : sprintf(mel_cgi_l('%s ответил HTTP %d', '%s answered HTTP %d'),
        $host, $code), $ec);
}

// GET /api/v1/status of the server $base (no limit of /user there) ->
// array(ok, version or the reason (mel_cgi_aio_why), it has TMDB of its own,
// refused by its request limit (429)).
function mel_cgi_aio_status($base)
{
    $r = mel_cgi_aio_req($base, 'GET', null, null, 'status');
    $d = mel_conf_ok($r) && isset($r[2]->data) && is_object($r[2]->data) ? $r[2]->data : null;
    $v = $d && isset($d->version) && is_string($d->version) && preg_match('/^[0-9A-Za-z.+\-]{1,40}\z/', $d->version) ?
        $d->version : '';
    $t = $d && isset($d->settings->metadata->tmdb) && is_object($d->settings->metadata->tmdb) ? $d->settings->metadata->tmdb :
        null;
    mel_cgi_log('aio status: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($v !== '' ? ', AIOStreams' : ''));
    if ($v === '')
    {
        if ($r[0] || !mel_conf_ok($r))
        {
            list($why) = mel_cgi_aio_why($r, (string) parse_url($base, PHP_URL_HOST), true);
            return array(false, $why, false, !$r[0] && $r[1] === 429);
        }
        return array(false, mel_cgi_l('Ответ не похож на AIOStreams', 'The reply does not look like AIOStreams'), false,
            false);
    }
    return array(true, $v, $t && ((isset($t->accessToken) && $t->accessToken === true) ||
        (isset($t->apiKey) && $t->apiKey === true)), false);
}

// a=aio_status: the own server of the posted field (url), nothing written. -> JSON {ok, level, msg}.
function mel_cgi_aio_status_post()
{
    $base = mel_aio_own(mel_cgi_param($_POST, 'url'));
    if ($base === '')
        mel_cgi_json(200, array('ok' => false, 'level' => 'err', 'msg' => mel_cgi_l(
            'Нужен адрес вида http(s)://хост[:порт] без пути', 'An address like http(s)://host[:port] without a path is needed')));
    $r = mel_cgi_aio_status($base);
    if (!$r[0])
        mel_cgi_json(200, array('ok' => false, 'level' => 'err', 'msg' => $r[1]));
    mel_cgi_json(200, array('ok' => true, 'level' => $r[2] ? 'ok' : 'warn', 'msg' => "AIOStreams $r[1] · " . ($r[2] ?
        mel_cgi_l('свой TMDB есть', 'TMDB of its own') : mel_cgi_l('своего TMDB нет: сверка названий — только с ключом TMDB',
        'no TMDB of its own: titles are matched only with a TMDB key'))));
}

// The config $c of the server $base in the mode $mode (null: forgotten) written to settings.json, the rest as it is
// now in the file. It takes the place of the config X of the source $prof (its id; gone or of another server or mode
// now - nothing written) - wherever X is (its uuid): the cell, the profiles. None (just made): in the profile only
// (mel_settings_json puts the config of the main one in its cell). -> '' or the reason (for the log).
function mel_cgi_aio_keep($dir, $base, $mode, $c, $prof)
{
    $s = mel_settings_read($dir);
    $cell = isset($s['aio_confs'][$base][$mode]) ? $s['aio_confs'][$base][$mode] : null;
    $k = mel_profile_index($s, $prof);
    $p = $k >= 0 ? $s['profiles'][$k] : null;
    if (!$p || !isset($p['base']) || $p['base'] !== $base || $p['mode'] !== $mode)
        return 'the source changed meanwhile, not kept';
    $x = $p['conf'];
    if ($x && $cell && $cell['uuid'] === $x['uuid'])
        $s = mel_settings_cell($s, $base, $mode, $c);
    foreach ($s['profiles'] as $i => $q)
    {
        if ($x ? !empty($q['conf']) && $q['conf']['uuid'] === $x['uuid'] : $i === $k)
            $s['profiles'][$i]['conf'] = $c;
    }
    return mel_cgi_store($dir, $s);
}

// The config $uuid marked at MEL_CONF_SCHEMA in data_dir/aio_conf_mig (0.60.0): made or reset by the template now, or
// changed with the migrations (mel_conf_migrate_apply). A failure only logged: the plugin asks the server once more.
// settings.json not read: no mark (the defaults have no configs, the marks of the others would be dropped).
function mel_cgi_mig_mark($dir, $uuid)
{
    $s = mel_settings_read($dir, $why);
    if ($why !== '')
    {
        mel_cgi_log("aio sync: migration mark: settings.json $why, not written");
        return;
    }
    $w = mel_cgi_write($dir, mel_conf_mig_json(mel_conf_mig_read($dir), $uuid, MEL_CONF_SCHEMA, $s), MEL_CONF_MIG_FILE);
    if ($w !== '')
        mel_cgi_log("aio sync: migration mark: $w");
}

// The answer of a=aio_sync before its outcome (mel_cgi_aio_sync, the
// failures of mel_cgi_aio_sync_post): of the choice $base (aio_server) in
// the mode $mode, the keys in the order of the JSON.
function mel_cgi_aio_res($base, $mode)
{
    return array('ok' => false, 'msg' => '', 'skip' => false, 'base' => $base, 'mode' => $mode, 'conf' => null);
}

// The config of the source of id $prof (v2; '' - the main one) by the settings as of it (mel_profile_view: its
// server, mode, template, JacRed, keys of Debrid; that of the other mode is never touched), its own config: none
// -> POST of the template (mel_settings_tpl: aio_tpl, or aio_tpl_p2p with no
// Debrid key needed) with our fields;
// there -> GET, its template by its presets stored (tpl), the migrations
// after its mark (mel_conf_migrate_apply), our fields of the mode, PUT;
// $reset (0.40.0) -> PUT of the template with our fields as for a new one,
// no GET (manual edits lost; the login, password and manifest stay). The
// own server: its TMDB by /status first.
// A config made, reset or changed: marked at MEL_CONF_SCHEMA (mel_cgi_mig_mark).
// The own config chosen -> nothing (skip). Mode Debrid: no config and no
// Debrid key, or a reset without one -> no request.
// -> the JSON of a=aio_sync: array(ok, msg - the line for the page, skip,
// base - the choice (aio_server), mode, conf - mel_cgi_aio_show() of its
// config now[, field - the name= of the field a failure is about: no Debrid
// key, the own server not taken (not its 429: the address is right, wait)]).
function mel_cgi_aio_sync($dir, $reset = false, $prof = '')
{
    set_time_limit(120);
    $s = mel_settings_read($dir);
    $f = mel_cgi_fields();
    $k = $prof !== '' ? mel_profile_index($s, $prof) : mel_profile_main($s);
    if ($prof !== '' && $k < 0)
    {
        mel_cgi_log('aio sync: no such source');
        return array_merge(mel_cgi_aio_res('', ''), array('msg' => mel_cgi_profile_gone()));
    }
    // Lampac, no sources: nothing to make.
    if ($k < 0 || !mel_profile_rel($s['profiles'][$k]))
    {
        mel_cgi_log('aio sync: no config of the source, skipped');
        return array_merge(mel_cgi_aio_res('', ''), array('ok' => true, 'skip' => true));
    }
    $prof = $s['profiles'][$k]['id'];
    $s = mel_profile_view($s, $s['profiles'][$k]);
    $choice = $s['aio_server'];
    $base = mel_settings_base($s);
    $host = (string) parse_url($base, PHP_URL_HOST);
    $mode = $s['aio_mode'];
    // The answer; each return sets ok, msg, conf, field over it.
    $res = mel_cgi_aio_res($choice, $mode);
    $conf = mel_settings_conf($s);
    // No config: made by the template.
    $reset = $reset && $conf;
    $id = mel_settings_tpl($s);
    $debrid = $mode !== MEL_MODE_P2P;
    if ($s['source'] === 'own' || $base === '')
    {
        mel_cgi_log('aio sync: own config or no address, skipped');
        return array_merge($res, array('ok' => true, 'skip' => true, 'conf' => mel_cgi_aio_show($conf)));
    }
    $fail = mel_cgi_not_done($reset ? 'reset' : ($conf ? 'update' : 'create')) . ': ';
    // The template is stored already: "Сохранить" again only updates.
    $again = $reset ? sprintf(mel_cgi_l('; повторить — «%s»', '; to retry: "%s"'), mel_cgi_resetb()) : '';
    // A config there may have a service of its own (set by hand): GET decides.
    if ($debrid && !$conf && $s['rd_key'] === '' && $s['tb_key'] === '')
    {
        mel_cgi_log('aio sync: no Debrid key');
        return array_merge($res, array('msg' => $fail . mel_cgi_l('нужен ключ Debrid (Real-Debrid или TorBox)',
            'a Debrid key is needed (Real-Debrid or TorBox)'), 'field' => $f['rd']['name']));
    }
    // The template has no service of its own.
    if ($debrid && $reset && $s['rd_key'] === '' && $s['tb_key'] === '')
    {
        mel_cgi_log("aio sync: reset ($id): no Debrid key, not changed");
        return array_merge($res, array('msg' => $fail . mel_cgi_l('нужен ключ Debrid в Melange',
            'a Debrid key is needed in Melange') . $again, 'conf' => mel_cgi_aio_show($conf), 'field' => $f['rd']['name']));
    }
    $tpl = mel_conf_template($id, $base);
    if (!$tpl)
    {
        mel_cgi_log('aio sync: no template');
        return array_merge($res, array('msg' => $fail . mel_cgi_l('нет шаблона в плагине', 'no template in the plugin') .
            $again, 'conf' => mel_cgi_aio_show($conf)));
    }
    $tmdbs = null;
    if ($choice === 'own')
    {
        list($ok, $why, $tmdbs, $busy) = mel_cgi_aio_status($base);
        if ($busy)
            return array_merge($res, array('msg' => $fail . $why . $again, 'conf' => mel_cgi_aio_show($conf)));
        if (!$ok)
            return array_merge($res, array('msg' => $fail . $why . $again, 'conf' => mel_cgi_aio_show($conf),
                'field' => $f['aio_own']['name']));
    }
    if (!$conf)
    {
        // The template is there (checked above): a request always.
        list($new, $r) = mel_conf_create($s, $tmdbs, mel_cgi_rand_hex(12), 'mel_cgi_aio_req');
        mel_cgi_log('aio sync: create: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($new ? ', made' : ''));
        if (!$new)
        {
            list($why, $ec) = mel_cgi_aio_why($r, $host, $choice === 'own');
            if ($ec !== '')
                mel_cgi_log("aio sync: $ec");
            return array_merge($res, array('msg' => $fail . $why));
        }
        $w = mel_cgi_aio_keep($dir, $base, $mode, $new, $prof);
        if ($w !== '')
        {
            mel_cgi_log("aio sync: $w");
            return array_merge($res, array('msg' => mel_cgi_l('Конфиг создан, но не записан на Дюне — ошибка записи',
                'The config was created but not saved on the Dune: write error')));
        }
        mel_cgi_mig_mark($dir, $new['uuid']);
        return array_merge($res, array('ok' => true, 'msg' => sprintf(mel_cgi_l('Конфиг AIOStreams создан на %s',
            'The AIOStreams config was created on %s'), $host), 'conf' => mel_cgi_aio_show($new)));
    }
    $auth = array($conf['uuid'], $conf['pass']);
    if ($reset)
    {
        $u = mel_conf_template($id, $base);
        list($tmdb, $set) = mel_conf_patch($u, $tpl, mel_conf_params($s, null, $tmdbs));
        $r = mel_cgi_aio_req($base, 'PUT', mel_conf_encode((object) array('config' => $u)), $auth);
        mel_cgi_log("aio sync: reset ($id): put: " . ($r[0] ? "curl $r[0]" : "HTTP $r[1]"));
    }
    else
    {
        $r = mel_cgi_aio_req($base, 'GET', null, $auth);
        $u = mel_conf_user_data($r);
        mel_cgi_log('aio sync: get: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]") . ($u ? ', config' : ''));
    }
    if ($u && !$reset)
    {
        // The migrations not yet run on it (the plugin may not have asked for its streams since).
        $from = mel_conf_mig_of(mel_conf_mig_read($dir), $conf['uuid']);
        if ($from < MEL_CONF_SCHEMA)
        {
            $mig = mel_conf_migrate_apply($u, $from);
            mel_cgi_log("aio sync: migrations $from -> " . MEL_CONF_SCHEMA . ($mig ? ': ' . implode(', ', $mig) : ''));
        }
        // As it is there after the migrations (a step may change the presets), before our
        // fields; stored even if the PUT fails.
        // The mode is that of its cell: a template of the other one is custom here.
        $t = mel_conf_tpl_of($u, $mode);
        mel_cgi_log("aio sync: template $t ($mode)");
        if ($t !== $conf['tpl'])
        {
            $conf['tpl'] = $t;
            $w = mel_cgi_aio_keep($dir, $base, $mode, $conf, $prof);
            if ($w !== '')
                mel_cgi_log("aio sync: $w");
        }
        // P2P made with Magnetio before the server got Torznab (mel_conf_p2p_torznab):
        // Magnetio kept, Torznab only by a reset (either template of P2P: the same JacRed preset).
        $ids = mel_conf_preset_ids($u);
        $tpl_patch = !$debrid && $ids && in_array(MEL_CONF_MAGNETIO, $ids, true) ? mel_conf_template($id) : $tpl;
        list($tmdb, $set) = mel_conf_patch($u, $tpl_patch, mel_conf_params($s, $conf, $tmdbs));
        // No key of melange and none set by hand: PUT would get 400 (Torznab).
        if ($debrid && !mel_conf_has_debrid($u))
        {
            mel_cgi_log('aio sync: no Debrid key, not changed');
            return array_merge($res, array('msg' => $fail . mel_cgi_l('нужен хотя бы один ключ Debrid',
                'at least one Debrid key is needed'), 'conf' => mel_cgi_aio_show($conf), 'field' => $f['rd']['name']));
        }
        $r = mel_cgi_aio_req($base, 'PUT', mel_conf_encode((object) array('config' => $u)), $auth);
        mel_cgi_log('aio sync: put: ' . ($r[0] ? "curl $r[0]" : "HTTP $r[1]"));
    }
    if (!$u || !mel_conf_ok($r))
    {
        list($why, $ec) = mel_cgi_aio_why($r, $host, $choice === 'own');
        if ($ec !== '')
            mel_cgi_log("aio sync: $ec");
        // The login and password are not taken any more: the config is
        // gone there (or the server lost its key). Forgotten here: the next
        // save makes a new one.
        if ($ec === 'USER_INVALID_DETAILS')
        {
            $w = mel_cgi_aio_keep($dir, $base, $mode, null, $prof);
            if ($w !== '')
                mel_cgi_log("aio sync: $w");
            return array_merge($res, array('msg' => sprintf(mel_cgi_l(
                'Сервер %s не узнал конфиг (удалён на сервере или сменён пароль?) — Melange его забыл; «%s» создаст новый',
                'Server %s does not recognize the config (deleted there or password changed?): ' .
                'Melange has forgotten it; "%s" will create a new one'), $host, mel_cgi_savec()),
                'conf' => $w === '' ? null : mel_cgi_aio_show($conf)));
        }
        return array_merge($res, array('msg' => $fail . $why . $again, 'conf' => mel_cgi_aio_show($conf)));
    }
    $conf['tmdb'] = $tmdb;
    $conf['set'] = $set;
    if ($reset)
        $conf['tpl'] = $id;
    $w = mel_cgi_aio_keep($dir, $base, $mode, $conf, $prof);
    if ($w !== '')
        mel_cgi_log("aio sync: $w");
    // The template (a reset) or the migrations (above): at the schema now.
    mel_cgi_mig_mark($dir, $conf['uuid']);
    return array_merge($res, array('ok' => true, 'msg' => $reset ? sprintf(mel_cgi_l(
        'Конфиг AIOStreams на %s сброшен к шаблону «%s»', 'The AIOStreams config on %s was reset to the template "%s"'),
        $host, mel_cgi_tpl_name($id)) :
        sprintf(mel_cgi_l('Конфиг AIOStreams на %s обновлён', 'The AIOStreams config on %s was updated'), $host),
        'conf' => mel_cgi_aio_show($conf)));
}

// a=aio_sync (reset=1: to the template; prof: of that source, its id; none - the main one): JSON {ok, msg, skip, base,
// mode (aio_mode: the config is of it), conf: {cfg, login, pass, tpl} | null[, field], profiles} (profiles: those of the
// file after it - a config made or forgotten: ready; mel_cgi_profiles_data).
function mel_cgi_aio_sync_post($dir)
{
    if (!mel_cgi_dir_ok($dir))
        mel_cgi_json(200, array_merge(mel_cgi_aio_res('', ''), array('msg' => mel_cgi_l('Нет папки плагина на Дюне',
            'The plugin data folder is missing on the Dune'))));
    $r = mel_cgi_aio_sync($dir, mel_cgi_param($_POST, 'reset') === '1', mel_cgi_param($_POST, 'prof'));
    mel_cgi_json(200, $r + array('profiles' => mel_cgi_profiles_data(mel_settings_read($dir))));
}
