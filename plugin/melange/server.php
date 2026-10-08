<?php
// "Download to server" in MENU of the list (0.30.0): the magnet of the row
// to a qBittorrent WebAPI server of the settings (qBittorrent, rdt-client):
// POST /api/v2/auth/login, then /api/v2/torrents/add (category, tags). A hash goes to a
// server once: data_dir/servers_added.txt keeps "<hash> <server url>" lines.
// Needs of main.php: Aio, aio_log, aio_error, aio_tr, aio_dialog,
// aio_close_and, aio_data_dir, aio_http_post; of parse.php: aio_magnet,
// aio_cut; of playback.php: aio_ep_tag; of view_gcomps.php: aio_gc_act;
// of view_setup.php: aio_setup_write.

define('AIO_DONE_FILE', 'servers_added.txt');
// The last lines of the file kept.
define('AIO_DONE_MAX', 1000);
// Each request: connect, whole (rdt-client with TorBox may be slow to add).
define('AIO_SRV_CONNECT', 3);
define('AIO_SRV_TOTAL', 15);

// Lines of servers_added.txt, oldest first.
function aio_done_read()
{
    $f = aio_data_dir() . '/' . AIO_DONE_FILE;
    clearstatcache();
    if (aio_data_dir() === '' || !is_file($f))
        return array();
    $out = array();
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l)
    {
        if (preg_match('/^[0-9a-f]{40} \S+\z/', $l))
            $out[] = $l;
    }
    return $out;
}

function aio_done_has($hash, $srv)
{
    return in_array("$hash {$srv['url']}", aio_done_read(), true);
}

// Adds the pair, keeps the last AIO_DONE_MAX; false when not written.
function aio_done_add($hash, $srv)
{
    $lines = aio_done_read();
    $lines[] = "$hash {$srv['url']}";
    $lines = array_slice($lines, -AIO_DONE_MAX);
    return aio_data_dir() !== '' && aio_setup_write(aio_data_dir() . '/' . AIO_DONE_FILE, implode("\n", $lines) . "\n");
}

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

// gc_srv (the MENU item) -> the popup menu of the servers; a server that has
// the hash already says so in its caption (GuiMenuItemDef::marked shows
// nothing in show_popup_menu, with an icon or without; device 08.10.2026).
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
            GuiMenuItemDef::caption => $s['name'] .
                (aio_done_has($row['hash'], $s) ? ' — ' . aio_tr($st['lang'], 'srv_mark') : ''),
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
    return aio_cut(trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $body)), 60);
}

// The magnet to server $srv -> '' (added) or array(error key, detail).
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
            return array('srv_err_net', $err);
        // qBittorrent bans the IP for a while after failed logins.
        if ($code === 403)
            return array('srv_err_banned', 'HTTP 403');
        // Before 5.2 (and rdt-client): 200 "Ok." / "Fails."; 5.2: 204 with the
        // cookie QBT_SID_<port> / 401. The cookies of the reply go back as they are.
        if ($code === 401 || trim($body) === 'Fails.')
            return array('srv_err_login', '');
        // A web page (a wrong port, another service) is no login.
        if (($code !== 200 && !($code >= 200 && $code < 300 && trim($body) === '')) || substr(trim($body), 0, 1) === '<')
            return array('srv_err_reply', trim("HTTP $code " . aio_srv_text($body)));
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
        return array('srv_err_timeout', $err);
    if ($err !== '')
        return array('srv_err_net', $err);
    if ($code === 401 || $code === 403)
        return array('srv_err_login', "HTTP $code");
    // qBittorrent 5.x: 409 when nothing was added (the torrent is there
    // already), 202 when the add goes on in the background.
    if ($code === 415 || $code === 409)
        return array('srv_err_refused', trim("HTTP $code $text"));
    if ($code < 200 || $code > 299)
        return array('srv_err_reply', trim("HTTP $code $text"));
    // qBittorrent before 5.2: "Ok." / "Fails."; 5.2: JSON of counts; rdt-client:
    // an empty 200, "Fails." (maybe as a JSON string) when it gave up.
    $b = trim(trim($body), '"');
    if ($b === 'Fails.')
        return array('srv_err_refused', 'Fails.');
    // A web page: a wrong port or another service, not an add.
    if (substr($b, 0, 1) === '<')
        return array('srv_err_reply', trim("HTTP $code $text"));
    $j = json_decode($body, true);
    $fc = is_array($j) && isset($j['failure_count']) && is_numeric($j['failure_count']) ? intval($j['failure_count']) : -1;
    if ($fc > 0)
        return array('srv_err_refused', "failure_count $fc");
    // Any other 2xx is taken as added: sending again would only add it twice.
    if ($b !== '' && $b !== 'Ok.' && $fc < 0)
        aio_log("server: HTTP $code, reply not understood, taken as added");
    return '';
}

// gc_push of row i to server k -> a dialog: added, added before, or why not.
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
    $tag = aio_ep_tag($st) . ", row $i, hash {$row['hash']}, server $k" .
        ($srv['category'] !== '' ? ", category {$srv['category']}" : '') .
        ($srv['tags'] !== '' ? ", tags {$srv['tags']}" : '');
    $ok = array('OK' => aio_close_and(null));
    if (aio_done_has($row['hash'], $srv))
    {
        aio_log("server: $tag: added before");
        return aio_dialog(str_replace('{srv}', $srv['name'], aio_tr($lang, 'srv_again')), array(), $ok);
    }
    list($mg, $n, $src) = aio_magnet($row);
    aio_log("server: $tag, $n trackers ($src)");
    $res = aio_qbt_add($srv, $mg);
    if ($res !== '')
    {
        aio_log("server: not added: {$res[0]}" . ($res[1] !== '' ? " ({$res[1]})" : ''));
        return aio_dialog(str_replace('{srv}', $srv['name'], aio_tr($lang, $res[0] === 'srv_err_timeout' ? 'srv_maybe' :
            'srv_failed')),
            array_merge(aio_dialog_lines(aio_tr($lang, $res[0])), aio_dialog_lines($res[1])), $ok);
    }
    $kept = aio_done_add($row['hash'], $srv);
    aio_log('server: added' . ($kept ? '' : ', not kept in ' . AIO_DONE_FILE));
    return aio_dialog(str_replace('{srv}', $srv['name'], aio_tr($lang, 'srv_added')), array(), $ok);
}
