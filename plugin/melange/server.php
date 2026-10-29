<?php
// "Download to server" in MENU of the list (0.30.0): the magnet of the row
// to a qBittorrent WebAPI server of the settings (qBittorrent, rdt-client):
// POST /api/v2/auth/login, then /api/v2/torrents/add (category, tags). Each push
// sends: a torrent the client has already is refused by qBittorrent (Fails. /
// 409), rdt-client returns the one it has (200). Transmission (0.47.0): JSON
// RPC torrent-add, HTTP Basic, its session id handshake (409).
// Reads Aio (state, settings). Functions are global; the require order is in
// main.php.

// Each request: connect, whole (rdt-client with TorBox may be slow to add).
define('AIO_SRV_CONNECT', 3);
define('AIO_SRV_TOTAL', 15);

// Index of the server the cursor starts on: the one standing for the debrid
// service the row is cached in, else the first with no service, else 0.
function aio_srv_default($servers, $row)
{
    $caches = aio_server_caches();
    if ($row['cached'] === true)
    {
        foreach ($servers as $k => $s)
        {
            if ($s['cache'] !== '' && $caches[$s['cache']] === $row['service'])
                return $k;
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
function aio_srv_row($in)
{
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
        return null;
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
    return isset($st['rows'][$i]) && $st['rows'][$i]['hash'] !== '' ? $i : null;
}

// gc_srv (the MENU item) -> the popup menu of the servers.
function aio_srv_menu($in)
{
    $i = aio_srv_row($in);
    $servers = Aio::$settings['servers'];
    if (is_null($i) || !$servers)
    {
        aio_log('server: menu of no live row or no server');
        return aio_error('err_list_expired');
    }
    $st = Aio::$state;
    $row = $st['rows'][$i];
    $items = array();
    foreach ($servers as $k => $s)
        $items[] = array(
            GuiMenuItemDef::caption => $s['name'],
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/network.aai',
            GuiMenuItemDef::action => aio_gc_act('gc_push', $st['rid'], array('i' => strval($i), 'k' => strval($k))));
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(
            ShowPopupMenuActionData::menu_items => $items,
            ShowPopupMenuActionData::selected_menu_item_index => aio_srv_default($servers, $row)));
}

// Text of a reply for the dialog and the log: printable, short.
function aio_srv_text($body)
{
    return aio_cut(aio_one_line($body), 60);
}

// The magnet to server $srv -> array(status, key, detail): 'added'; 'failed'
// (an error key); 'maybe' (srv_err_timeout: it may be there).
function aio_qbt_add($srv, $magnet)
{
    $cookie = '';
    $err = '';
    // No user: a server without a login (qBittorrent "bypass for whitelisted subnets").
    if ($srv['user'] !== '')
    {
        $t = microtime(true);
        list($code, $body, $cookie) = aio_http_post($srv['url'] . '/api/v2/auth/login',
            array('username' => $srv['user'], 'password' => $srv['pass']), '', $err, AIO_SRV_CONNECT, AIO_SRV_TOTAL);
        aio_log(sprintf('server: login: %s, %s, %.2f s', $err !== '' ? $err : "HTTP $code",
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
            return array('failed', 'srv_err_reply', trim("HTTP $code " . aio_srv_text($body)));
    }
    $f = array('urls' => $magnet);
    if ($srv['category'] !== '')
        $f['category'] = $srv['category'];
    // qBittorrent creates missing tags; rdt-client may ignore them.
    if ($srv['tags'] !== '')
        $f['tags'] = $srv['tags'];
    $t = microtime(true);
    list($code, $body) = aio_http_post($srv['url'] . '/api/v2/torrents/add', $f, $cookie, $err, AIO_SRV_CONNECT,
        AIO_SRV_TOTAL);
    $text = aio_srv_text($body);
    aio_log(sprintf('server: add: %s, "%s", %.2f s', $err !== '' ? $err : "HTTP $code", $text,
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
        aio_log("server: HTTP $code, reply not understood, taken as added");
    return array('added', '', '');
}

// The magnet to the Transmission server $srv -> array(status, key, detail)
// as aio_qbt_add; 'dup' (srv_dup): it has the torrent already. aio_trm_call
// does its session id handshake (409). Neither the password, the
// Authorization nor the session id is logged.
function aio_trm_add($srv, $magnet)
{
    $a = array('filename' => $magnet);
    if ($srv['category'] !== '')
        $a['download-dir'] = $srv['category'];
    // Transmission 3.x ignores labels without an error.
    if ($srv['tags'] !== '')
        $a['labels'] = explode(',', $srv['tags']);
    $t = microtime(true);
    $rep = aio_trm_call($srv, json_encode(array('method' => 'torrent-add', 'arguments' => $a)), AIO_SRV_CONNECT,
        AIO_SRV_TOTAL);
    $err = $rep['err'];
    $code = $rep['code'];
    $body = $rep['body'];
    aio_log(sprintf('server: rpc%s: %s, %.2f s', $rep['sid'] ? ' with session id' : '',
        $err !== '' ? $err : "HTTP $code", microtime(true) - $t));
    // Without a session id nothing is added: only the request with one may have been.
    if (strpos($err, 'curl 28:') === 0 && $rep['sid'])
        return array('maybe', 'srv_err_timeout', $err);
    if ($err !== '')
        return array('failed', 'srv_err_net', $err);
    $text = aio_srv_text($body);
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
        return array('failed', 'srv_err_rejected', aio_srv_text($j['result']));
    $r = isset($j['arguments']) && is_array($j['arguments']) ? $j['arguments'] : array();
    if (isset($r['torrent-duplicate']))
        return array('dup', 'srv_dup', '');
    if (!isset($r['torrent-added']))
        aio_log('server: success without torrent-added, taken as added');
    return array('added', '', '');
}

// gc_push of row i to server k -> a dialog: added or why not.
function aio_srv_push($in)
{
    $i = aio_srv_row($in);
    $servers = Aio::$settings['servers'];
    $k = isset($in->k) && is_string($in->k) && preg_match('/^[0-9]$/D', $in->k) ? intval($in->k) : -1;
    if (is_null($i) || !isset($servers[$k]))
    {
        aio_log('server: push of no live row or no such server');
        return aio_error('err_list_expired');
    }
    $st = Aio::$state;
    $row = $st['rows'][$i];
    $srv = $servers[$k];
    $lang = $st['lang'];
    // Not its address: the log is for others to read.
    $tag = aio_ep_tag($st) . ", row $i, hash {$row['hash']}, server $k" . ($srv['type'] === 'tr' ? ' (Transmission)' : '') .
        ($srv['category'] !== '' ? ", category {$srv['category']}" : '') .
        ($srv['tags'] !== '' ? ", tags {$srv['tags']}" : '');
    $ok = array('OK' => aio_close_and(null));
    list($mg, $n, $src) = aio_magnet($row);
    aio_log("server: $tag, $n trackers ($src)");
    list($status, $key, $detail) = $srv['type'] === 'tr' ? aio_trm_add($srv, $mg) : aio_qbt_add($srv, $mg);
    if ($status === 'dup')
    {
        aio_log('server: there already');
        return aio_dialog(str_replace('{srv}', $srv['name'], aio_tr($lang, $key)), array(), $ok);
    }
    if ($status !== 'added')
    {
        aio_log("server: not added: $key" . ($detail !== '' ? " ($detail)" : ''));
        return aio_dialog(str_replace('{srv}', $srv['name'], aio_tr($lang, $status === 'maybe' ? 'srv_maybe' : 'srv_failed')),
            array_merge(aio_dialog_lines(aio_tr($lang, $key)), aio_dialog_lines($detail)), $ok);
    }
    aio_log('server: added');
    return aio_dialog(str_replace('{srv}', $srv['name'], aio_tr($lang, 'srv_added')), array(), $ok);
}
