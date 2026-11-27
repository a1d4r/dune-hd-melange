<?php
// "Download to server" in MENU of the list (0.30.0): the magnet of the row
// to a qBittorrent WebAPI server of the settings (qBittorrent, rdt-client):
// POST /api/v2/auth/login, then /api/v2/torrents/add (category, tags). Each push
// sends: a torrent the client has already is refused by qBittorrent (Fails. /
// 409), rdt-client returns the one it has (200). Transmission (0.47.0): JSON
// RPC torrent-add, HTTP Basic, its session id handshake (409). Synology
// Download Station (0.70.1): its Web API, a login and a logout each push.
// Reads Mel (state, settings). Functions are global; the require order is in
// main.php.

// Each request: connect, whole (rdt-client with TorBox may be slow to add).
define('MEL_SRV_CONNECT', 3);
define('MEL_SRV_TOTAL', 15);
// All the requests of a push to a Synology (seven at most) fit the 60 s of
// the operation: each gets what is left of this, MEL_SRV_TOTAL at most.
define('MEL_SYN_BUDGET', 45);

// Index of the server the cursor starts on: the one standing for the debrid
// service the row is cached in (a file cached on several: the copy to play
// first, mel_row_services), else the first with no service, else 0.
function mel_srv_default($servers, $row)
{
    $caches = mel_server_caches();
    if ($row['cached'] === true)
    {
        foreach (mel_row_services($row) as $svc)
        {
            foreach ($servers as $k => $s)
            {
                if ($svc !== '' && $s['cache'] !== '' && $caches[$s['cache']] === $svc)
                    return $k;
            }
        }
    }
    foreach ($servers as $k => $s)
    {
        if ($s['cache'] === '')
            return $k;
    }
    return 0;
}

// Row i of a live list with infoHash, from params rid and i; else null.
function mel_srv_row($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
        return null;
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
    return isset($st['rows'][$i]) && $st['rows'][$i]['hash'] !== '' ? $i : null;
}

// gc_srv (the MENU item) -> the popup menu of the servers.
function mel_srv_menu($in)
{
    $i = mel_srv_row($in);
    $servers = Mel::$settings['servers'];
    if (is_null($i) || !$servers)
    {
        mel_log('server: menu of no live row or no server');
        return mel_error('err_list_expired');
    }
    $st = Mel::$state;
    $row = $st['rows'][$i];
    $items = array();
    foreach ($servers as $k => $s)
        $items[] = array(
            GuiMenuItemDef::caption => $s['name'],
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/network.aai',
            GuiMenuItemDef::action => mel_gc_act('gc_push', $st['rid'], array('i' => strval($i), 'k' => strval($k))));
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => mel_srv_default($servers, $row)));
}

// Text of a reply for the dialog and the log: printable, short.
function mel_srv_text($body)
{
    return mel_cut(mel_one_line($body), 60);
}

// The magnet to server $srv -> array(status, key, detail): 'added'; 'failed'
// (an error key); 'maybe' (srv_err_timeout: it may be there).
function mel_qbt_add($srv, $magnet)
{
    $cookie = '';
    $err = '';
    // No user: a server without a login (qBittorrent "bypass for whitelisted subnets").
    if ($srv['user'] !== '')
    {
        $t = microtime(true);
        list($code, $body, $cookie) = mel_http_post($srv['url'] . '/api/v2/auth/login',
            array('username' => $srv['user'], 'password' => $srv['pass']), '', $err, MEL_SRV_CONNECT, MEL_SRV_TOTAL);
        mel_log(sprintf('server: login: %s, %s, %.2f s', $err !== '' ? $err : "HTTP $code",
            $cookie !== '' ? 'cookie' : 'no cookie', microtime(true) - $t));
        if ($err !== '')
            return array('failed', 'srv_err_net', $err);
        // qBittorrent bans the IP for a while after failed logins.
        if ($code === 403)
            return array('failed', 'srv_err_banned', 'HTTP 403');
        // Before 5.2 (and rdt-client): 200 "Ok." / "Fails."; 5.2: 204 with the
        // cookie QBT_SID_<port> / 401. The cookies of the reply go back as they are.
        if ($code === 401 || trim($body) === 'Fails.')
            return array('failed', 'srv_err_login', '');
        // A web page (a wrong port, another service) is no login.
        if (($code !== 200 && !($code >= 200 && $code < 300 && trim($body) === '')) || substr(trim($body), 0, 1) === '<')
            return array('failed', 'srv_err_reply', trim("HTTP $code " . mel_srv_text($body)));
    }
    $f = array('urls' => $magnet);
    if ($srv['category'] !== '')
        $f['category'] = $srv['category'];
    // qBittorrent creates missing tags; rdt-client may ignore them.
    if ($srv['tags'] !== '')
        $f['tags'] = $srv['tags'];
    $t = microtime(true);
    list($code, $body) = mel_http_post($srv['url'] . '/api/v2/torrents/add', $f, $cookie, $err, MEL_SRV_CONNECT,
        MEL_SRV_TOTAL);
    $text = mel_srv_text($body);
    mel_log(sprintf('server: add: %s, "%s", %.2f s', $err !== '' ? $err : "HTTP $code", $text,
        microtime(true) - $t));
    // rdt-client holds the reply while the torrent is queued: it may be there.
    if (strpos($err, 'curl 28:') === 0)
        return array('maybe', 'srv_err_timeout', $err);
    if ($err !== '')
        return array('failed', 'srv_err_net', $err);
    if ($code === 401 || $code === 403)
        return array('failed', 'srv_err_login', "HTTP $code");
    // qBittorrent 5.x: 409 when nothing was added (the torrent is there
    // already), 202 when the add goes on in the background.
    if ($code === 415 || $code === 409)
        return array('failed', 'srv_err_refused', trim("HTTP $code $text"));
    if ($code < 200 || $code > 299)
        return array('failed', 'srv_err_reply', trim("HTTP $code $text"));
    // qBittorrent before 5.2: "Ok." / "Fails."; 5.2: JSON of counts; rdt-client:
    // an empty 200, "Fails." (maybe as a JSON string) when it gave up.
    $b = trim(trim($body), '"');
    if ($b === 'Fails.')
        return array('failed', 'srv_err_refused', 'Fails.');
    // A web page: a wrong port or another service, not an add.
    if (substr($b, 0, 1) === '<')
        return array('failed', 'srv_err_reply', trim("HTTP $code $text"));
    $j = json_decode($body, true);
    $fc = is_array($j) && isset($j['failure_count']) && is_numeric($j['failure_count']) ? intval($j['failure_count']) : -1;
    if ($fc > 0)
        return array('failed', 'srv_err_refused', "failure_count $fc");
    // Any other 2xx is taken as added: a 2xx is not a refusal.
    if ($b !== '' && $b !== 'Ok.' && $fc < 0)
        mel_log("server: HTTP $code, reply not understood, taken as added");
    return array('added', '', '');
}

// The magnet to the Transmission server $srv -> array(status, key, detail)
// as mel_qbt_add; 'dup' (srv_dup): it has the torrent already. mel_trm_call
// does its session id handshake (409). Neither the password, the
// Authorization nor the session id is logged.
function mel_trm_add($srv, $magnet)
{
    $a = array('filename' => $magnet);
    if ($srv['category'] !== '')
        $a['download-dir'] = $srv['category'];
    // Transmission 3.x ignores labels without an error.
    if ($srv['tags'] !== '')
        $a['labels'] = explode(',', $srv['tags']);
    $t = microtime(true);
    $rep = mel_trm_call($srv, json_encode(array('method' => 'torrent-add', 'arguments' => $a)), MEL_SRV_CONNECT,
        MEL_SRV_TOTAL);
    $err = $rep['err'];
    $code = $rep['code'];
    $body = $rep['body'];
    mel_log(sprintf('server: rpc%s: %s, %.2f s', $rep['sid'] ? ' with session id' : '',
        $err !== '' ? $err : "HTTP $code", microtime(true) - $t));
    // Without a session id nothing is added: only the request with one may have been.
    if (strpos($err, 'curl 28:') === 0 && $rep['sid'])
        return array('maybe', 'srv_err_timeout', $err);
    if ($err !== '')
        return array('failed', 'srv_err_net', $err);
    $text = mel_srv_text($body);
    if ($code === 401)
        return array('failed', 'srv_err_login', '');
    // Not in its rpc-whitelist.
    if ($code === 403)
        return array('failed', 'srv_err_forbidden', 'HTTP 403');
    // The host name of the address is not in its rpc-host-whitelist.
    if ($code === 421)
        return array('failed', 'srv_err_hostname', 'HTTP 421');
    // Twice, or no id to retry with; its page quotes the id: not shown.
    if ($code === 409)
        return array('failed', 'srv_err_reply', 'HTTP 409');
    $j = $code === 200 ? json_decode($body, true) : null;
    if (!is_array($j) || !isset($j['result']) || !is_string($j['result']))
        return array('failed', 'srv_err_reply', trim("HTTP $code $text"));
    // An error is in "result", not in the HTTP code.
    if ($j['result'] !== 'success')
        return array('failed', 'srv_err_rejected', mel_srv_text($j['result']));
    $r = isset($j['arguments']) && is_array($j['arguments']) ? $j['arguments'] : array();
    if (isset($r['torrent-duplicate']))
        return array('dup', 'srv_dup', '');
    if (!isset($r['torrent-added']))
        mel_log('server: success without torrent-added, taken as added');
    return array('added', '', '');
}

// Total s for the next request of a push to a Synology that ends at $end:
// what is left, 1 to $max.
function mel_syn_left($end, $max = MEL_SRV_TOTAL)
{
    return max(1, min($max, intval(ceil($end - microtime(true)))));
}

// The reply $r (mel_syn_req) of step $what, sent at $t, to the log: neither
// the password nor the sid is there.
function mel_syn_log($what, $r, $t)
{
    mel_log(sprintf('server: syn %s: %s, %.2f s', $what, $r['err'] !== '' ? $r['err'] :
        ($r['ok'] ? 'ok' : ($r['code'] ? "error {$r['code']}" : "HTTP {$r['http']}")), microtime(true) - $t));
}

// A reply (mel_syn_req) that is not ok as array('failed', key, detail): no
// connection; else key $key for an error code of the API; else not its reply.
function mel_syn_fail($r, $key)
{
    if ($r['err'] !== '')
        return array('failed', 'srv_err_net', $r['err']);
    if ($r['code'])
        return array('failed', $key, "error {$r['code']}");
    return array('failed', 'srv_err_reply', "HTTP {$r['http']}");
}

// The reply $r of a create (DS2 or v1) -> array(status, key, detail).
function mel_syn_created($r)
{
    if ($r['ok'])
        return array('added', '', '');
    // Not sent again: it may be there.
    if (strpos($r['err'], 'curl 28:') === 0)
        return array('maybe', 'srv_err_timeout', $r['err']);
    // The folder is under a shared folder and is not created.
    if ($r['code'] === 403)
        return array('failed', 'srv_err_syn_folder', '');
    if ($r['code'] === 406 || $r['code'] === 120)
        return array('failed', 'srv_err_syn_nodest', '');
    return mel_syn_fail($r, 'srv_err_rejected');
}

// The magnet (of the torrent $hash) to the Synology $srv in the session
// $sid -> array(status, key, detail). A task with the hash in the list ->
// 'dup': a second create answers "success" and leaves a task with an error
// there; no list -> no such check. The folder: the category, else the
// default one of Download Station, else nothing is sent. Create of
// DownloadStation2 (its values are JSON), of v1 when there is none or it
// does not know the method (102, 103, 104).
function mel_syn_send($srv, $apis, $sid, $magnet, $hash, $end)
{
    $t = microtime(true);
    $r = mel_syn_api($srv, $apis, 'SYNO.DownloadStation.Task', 1, 'list', array('additional' => 'detail'), $sid,
        MEL_SRV_CONNECT, mel_syn_left($end));
    mel_syn_log('list', $r, $t);
    $tasks = isset($r['data']['tasks']) && is_array($r['data']['tasks']) ? $r['data']['tasks'] : array();
    foreach ($tasks as $task)
    {
        if (isset($task['additional']['detail']['uri']) && is_string($task['additional']['detail']['uri']) &&
            stripos($task['additional']['detail']['uri'], "btih:$hash") !== false)
            return array('dup', 'srv_dup', '');
    }
    // Under a shared folder: no slash at its start.
    $dest = ltrim($srv['category'], '/');
    if ($dest === '')
    {
        $t = microtime(true);
        $r = mel_syn_api($srv, $apis, 'SYNO.DownloadStation.Info', 1, 'getconfig', array(), $sid, MEL_SRV_CONNECT,
            mel_syn_left($end));
        mel_syn_log('getconfig', $r, $t);
        if ($r['err'] !== '')
            return mel_syn_fail($r, '');
        if (isset($r['data']['default_destination']) && is_string($r['data']['default_destination']))
            $dest = ltrim($r['data']['default_destination'], '/');
        if ($dest === '')
            return array('failed', 'srv_err_syn_nodest', '');
    }
    $v1 = isset($apis['SYNO.DownloadStation.Task']);
    if (isset($apis['SYNO.DownloadStation2.Task']) && $apis['SYNO.DownloadStation2.Task']['max'] >= 2)
    {
        $t = microtime(true);
        $r = mel_syn_api($srv, $apis, 'SYNO.DownloadStation2.Task', 2, 'create', array('type' => '"url"',
            'url' => json_encode(array($magnet)), 'destination' => json_encode($dest), 'create_list' => 'false'), $sid,
            MEL_SRV_CONNECT, mel_syn_left($end));
        mel_syn_log('create', $r, $t);
        if (!$v1 || !in_array($r['code'], array(102, 103, 104), true))
            return mel_syn_created($r);
    }
    if (!$v1)
        return array('failed', 'srv_err_reply', 'no Download Station API');
    $t = microtime(true);
    $r = mel_syn_api($srv, $apis, 'SYNO.DownloadStation.Task', min(3, $apis['SYNO.DownloadStation.Task']['max']), 'create',
        array('uri' => $magnet, 'destination' => $dest), $sid, MEL_SRV_CONNECT, mel_syn_left($end));
    mel_syn_log('create v1', $r, $t);
    return mel_syn_created($r);
}

// The magnet (of the torrent $hash) to the Synology Download Station $srv
// -> array(status, key, detail) as mel_trm_add. The paths and the versions
// of its APIs by mel_syn_query, a login, mel_syn_send, a logout: no session
// is kept, an error of the logout changes nothing.
function mel_syn_add($srv, $magnet, $hash)
{
    $end = microtime(true) + MEL_SYN_BUDGET;
    $t = microtime(true);
    list($r, $apis) = mel_syn_query($srv, MEL_SRV_CONNECT, mel_syn_left($end));
    mel_syn_log('query', $r, $t);
    if (!$r['ok'])
    {
        $f = mel_syn_fail($r, 'srv_err_reply');
        // A web page (a wrong port, another service): its start tells.
        if ($r['err'] === '' && !$r['code'])
            $f[2] = trim("{$f[2]} " . mel_srv_text($r['body']));
        return $f;
    }
    if (!isset($apis['SYNO.API.Auth']))
        return array('failed', 'srv_err_reply', 'no SYNO.API.Auth');
    $t = microtime(true);
    list($r, $sid) = mel_syn_login($srv, $apis, MEL_SRV_CONNECT, mel_syn_left($end));
    mel_syn_log('login', $r, $t);
    if ($r['code'] === 400 || $r['code'] === 401)
        return array('failed', 'srv_err_login', '');
    if ($r['code'] === 402)
        return array('failed', 'srv_err_syn_app', '');
    // The 2-step verification: of the user (403), required for all (406).
    if ($r['code'] === 403 || $r['code'] === 406)
        return array('failed', 'srv_err_syn_otp', '');
    // DSM blocks the address after wrong passwords, the right one too.
    if ($r['code'] === 407)
        return array('failed', 'srv_err_syn_blocked', '');
    if (!$r['ok'])
        return mel_syn_fail($r, 'srv_err_reply');
    if ($sid === '')
        return array('failed', 'srv_err_reply', 'no sid');
    $res = mel_syn_send($srv, $apis, $sid, $magnet, $hash, $end);
    $t = microtime(true);
    mel_syn_log('logout', mel_syn_logout($srv, $apis, $sid, MEL_SRV_CONNECT, mel_syn_left($end, 3)), $t);
    return $res;
}

// gc_push of row i to server k -> a dialog: added or why not.
function mel_srv_push($in)
{
    $i = mel_srv_row($in);
    $servers = Mel::$settings['servers'];
    $k = isset($in->k) && is_string($in->k) && preg_match('/^[0-9]$/D', $in->k) ? intval($in->k) : -1;
    if (is_null($i) || !isset($servers[$k]))
    {
        mel_log('server: push of no live row or no such server');
        return mel_error('err_list_expired');
    }
    $st = Mel::$state;
    if (Mel::$settings['no_torrents'])
        return mel_torrents_off($st['lang']);
    $row = $st['rows'][$i];
    $srv = $servers[$k];
    $lang = $st['lang'];
    // Not its address: the log is for others to read.
    $tag = mel_ep_tag($st) . ", row $i, hash {$row['hash']}, server $k" . ($srv['type'] === 'tr' ? ' (Transmission)' : ($srv['type'] === 'syn' ? ' (Synology)' : '')) .
        ($srv['category'] !== '' ? ", category {$srv['category']}" : '') .
        ($srv['tags'] !== '' ? ", tags {$srv['tags']}" : '');
    $ok = array('OK' => mel_close_and(null));
    list($mg, $n, $src) = mel_magnet($row);
    mel_log("server: $tag, $n trackers ($src)");
    if ($srv['type'] === 'tr')
        list($status, $key, $detail) = mel_trm_add($srv, $mg);
    else if ($srv['type'] === 'syn')
        list($status, $key, $detail) = mel_syn_add($srv, $mg, $row['hash']);
    else
        list($status, $key, $detail) = mel_qbt_add($srv, $mg);
    if ($status === 'dup')
    {
        mel_log('server: there already');
        return mel_dialog(str_replace('{srv}', $srv['name'], mel_tr($lang, $key)), array(), $ok);
    }
    if ($status !== 'added')
    {
        mel_log("server: not added: $key" . ($detail !== '' ? " ($detail)" : ''));
        return mel_dialog(str_replace('{srv}', $srv['name'], mel_tr($lang, $status === 'maybe' ? 'srv_maybe' : 'srv_failed')),
            array_merge(mel_dialog_lines(mel_tr($lang, $key)), mel_dialog_lines($detail)), $ok);
    }
    mel_log('server: added');
    return mel_dialog(str_replace('{srv}', $srv['name'], mel_tr($lang, 'srv_added')), array(), $ok);
}
