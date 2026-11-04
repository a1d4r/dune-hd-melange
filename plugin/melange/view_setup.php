<?php
// Settings screen (media_url "setup", controls) and "Set up from a phone":
// a QR with the address of the settings page (cgi/settings.php). The page
// writes data_dir/settings.json, the plugin reads it; the QR dialog polls the
// file by its timer. The one write of the plugin: "Quick setup: torrents"
// (0.51.0, aio_setup_quick), as the page saves "Торренты". The token of the
// page is permanent (a bookmark keeps working): data_dir/web_token, written
// here (new on "Change the link") and by the import of a backup on the page
// (its token back, 0.51.0); read anew at each QR, never kept in memory.
// Neither the token nor the page address goes to the log. Reads Aio::$settings.

// The config of AIOStreams the quick setup makes, its request (AIO_CONF_API_*):
// shared with the settings page. Plain PHP, no firmware API.
require_once dirname(__FILE__) . '/cgi/aio_conf.php';

define('AIO_SETUP_TICK_MS', 1500);
// Seconds the QR dialog waits after a save for the config the page makes
// next (a=aio_sync: up to 2 requests of 20 s); a failed one writes nothing.
define('AIO_SETUP_WAIT_S', 45);
// play_action without an address: true - a dialog with "Set up"; false -
// the settings screen right away (if a dialog from play_action fails).
if (!defined('AIO_NO_ADDR_DIALOG'))
    define('AIO_NO_ADDR_DIALOG', true);
// Sources of random bytes for the token. Tests may define them first.
if (!defined('AIO_OPENSSL'))
    define('AIO_OPENSSL', true);
if (!defined('AIO_URANDOM'))
    define('AIO_URANDOM', '/dev/urandom');

class AioSetup
{
    // ino:mtime:size of settings.json when the QR dialog opened; null: no
    // dialog (or php_server restarted under it).
    public static $sig = null;
    // time() of a save after which the page makes the config; 0: not waiting.
    public static $wait = 0;
}

// The settings screen; $from: the screen of the catalog it is opened over,
// "setup::<from>", so a save reads that one again (aio_setup_refresh), and its
// caption is "Settings" (the path is "Melange > Settings", not "Melange > Melange").
function aio_setup_open_act($from = '')
{
    $cat = aio_catv_is_main($from);
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => $cat ? "setup::$from" : 'setup',
            PluginOpenFolderActionData::caption => $cat ? '%tr%setup_menu' : '%tr%plugin_caption'));
}

// Params of the inputs of the settings screen $url, nothing kept in memory (a restart
// of php_server under the QR dialog): 'from' - the screen of the catalog to read again
// after a save; 'first' - the screen is that catalog itself (a first run).
function aio_setup_params($url)
{
    if (aio_catv_is_main($url))
        return array('from' => $url, 'first' => '1');
    $p = explode(':', $url, 3);
    return isset($p[2]) && aio_catv_is_main($p[2]) ? array('from' => $p[2]) : array();
}

// play_action without a config: none made on the chosen server, or no link
// to the own one.
function aio_no_address($lang)
{
    $key = Aio::$settings['own'] ? 'err_no_manifest_own' : 'err_no_manifest';
    aio_log("error: $key -> " . (AIO_NO_ADDR_DIALOG ? 'dialog' : 'settings screen'));
    if (!AIO_NO_ADDR_DIALOG)
        return aio_setup_open_act();
    // The quick setup from a card: its dialog says what next, in the language of the card.
    return aio_dialog(aio_tr($lang, $key), array(), array(
        aio_tr($lang, 'setup_quick') => aio_close_and(aio_input('setup_quick',
            array('card' => '1', 'lang' => $lang === 'ru' ? 'ru' : 'en'), 0)),
        aio_tr($lang, 'setup_open') => aio_close_and(aio_setup_open_act()),
        aio_tr($lang, 'setup_close') => aio_close_and(null)));
}

// Host of an address of the settings, '' for none.
function aio_setup_host($url)
{
    $h = $url !== '' ? parse_url($url, PHP_URL_HOST) : null;
    return is_string($h) ? $h : '';
}

// The chosen JacRed: a built-in id or the host of the own one ('' for none).
function aio_setup_jacred($s)
{
    $l = aio_jacred_list($s);
    return $l ? $l[0]['host'] : '';
}

function aio_setup_label($title, $caption)
{
    return aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $caption), $title);
}

function aio_setup_button($name, $caption, $action)
{
    return aio_ctl($name, GUI_CONTROL_BUTTON, array(
        GuiButtonDef::caption => $caption, GuiButtonDef::width => 600, GuiButtonDef::push_action => $action));
}

function aio_setup_defs($p)
{
    $dir = aio_data_dir();
    $s = aio_settings_read_logged();
    $defs = array();
    $h = aio_setup_host(aio_settings_manifest($s));
    // "AIOStreams - own config" / "AIOStreams - Melange config": the host of
    // its server (another server too: on the TV, never in the log).
    $defs[] = aio_setup_label($s['source'] === 'own' ? '%tr%setup_aio_own' : '%tr%setup_aio_made',
        $h !== '' ? $h : '%tr%setup_not_set');
    $jr = aio_setup_jacred($s);
    $defs[] = aio_setup_label('JacRed', $jr !== '' ? $jr : '%tr%setup_not_set');
    if ($dir === '' || !is_dir($dir))
        $defs[] = aio_setup_label(null, '%tr%err_setup_dir');
    // No AIOStreams yet: the quick setup first; no link of the page to change.
    $none = aio_manifest_base(aio_settings_manifest($s)) === '';
    if ($none)
        $defs[] = aio_setup_button('quick', '%tr%setup_quick', aio_input('setup_quick', $p, 0));
    $defs[] = aio_setup_button('phone', '%tr%setup_phone', aio_input('setup_qr', $p));
    if (!$none)
        $defs[] = aio_setup_button('relink', '%tr%setup_relink', aio_input('setup_relink', $p));
    return $defs;
}

// $url: "setup", "setup:<n>", "setup:<n>:<catalog>", or the catalog itself without AIOStreams.
function aio_setup_folder_view($url)
{
    $s = aio_settings_read_logged();
    aio_log('setup: screen, AIOStreams ' . ($s['source'] === 'own' ? 'own config' : ($s['aio_server'] === 'own' ?
        'made on another server' : 'made on a public one')) . (aio_settings_manifest($s) !== '' ? ', set' : ', not set') .
        ', JacRed ' . ($s['jacred'] === 'own' ? 'own' : $s['jacred']));
    return array(
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::data => array(
            PluginControlsFolderView::defs => aio_setup_defs(aio_setup_params($url)),
            PluginControlsFolderView::initial_sel_ndx => -1));
}

// 32 hex of openssl or urandom; '' without them.
function aio_setup_rand_hex()
{
    $b = AIO_OPENSSL && function_exists('openssl_random_pseudo_bytes') ? (string) openssl_random_pseudo_bytes(16) : '';
    if (strlen($b) !== 16 && is_readable(AIO_URANDOM))
        $b = (string) file_get_contents(AIO_URANDOM, false, null, 0, 16);
    if (strlen($b) !== 16)
        return '';
    return bin2hex($b);
}

// The token of the page: the one in data_dir (aio_token_rewrite), a new one
// when there is none (or $renew). '' on failure.
function aio_setup_token($renew)
{
    if (aio_data_dir() === '')
        return '';
    $t = $renew ? '' : aio_token_rewrite();
    if ($t !== '')
        return $t;
    $t = aio_setup_rand_hex();
    if ($t === '')
    {
        // A token of md5(uniqid) could be guessed from the LAN.
        aio_log('setup: no source of random bytes, no link');
        return '';
    }
    aio_log('setup: ' . ($renew ? 'new link' : 'first link'));
    return aio_write_file(aio_data_dir() . '/' . AIO_TOKEN_FILE, $t) ? $t : '';
}

// IP of the Dune for the phone, from ifconfig: eth0, else wlan0, else the
// first other interface but loopback and VPNs (tun*, ppp*, wg*). busybox lists
// interfaces by name, so on Wi-Fi a VPN tun0 comes before wlan0. Else the
// source address of a UDP socket (connect sends nothing; may be a VPN's too).
function aio_setup_ip()
{
    $o = array();
    exec('ifconfig 2>/dev/null', $o);
    $ips = array();
    $if = '';
    foreach ($o as $line)
    {
        // A block starts with the interface name at the line start.
        if (preg_match('/^([^\s:]+)/', $line, $m))
            $if = $m[1];
        if ($if !== '' && !isset($ips[$if]) &&
            preg_match('/inet addr: ?([0-9]{1,3}(?:\.[0-9]{1,3}){3})/', $line, $m) && strpos($m[1], '127.') !== 0)
            $ips[$if] = $m[1];
    }
    foreach (array('eth0', 'wlan0') as $if)
    {
        if (isset($ips[$if]))
            return $ips[$if];
    }
    foreach ($ips as $if => $ip)
    {
        if (!preg_match('/^(tun|ppp|wg)/', $if))
            return $ip;
    }
    $ip = '';
    if (function_exists('socket_create'))
    {
        hd_silence_warnings();
        $s = socket_create(AF_INET, SOCK_DGRAM, 0);
        if (is_resource($s))
        {
            if (!socket_connect($s, '8.8.8.8', 53) || !socket_getsockname($s, $ip))
                $ip = '';
            socket_close($s);
        }
        hd_restore_warnings();
    }
    return preg_match('/^[0-9]{1,3}(\.[0-9]{1,3}){3}$/', (string) $ip) && $ip !== '0.0.0.0' ? $ip : '';
}

// The address of the settings page with the token; the port of httpd only
// if it is not 80 (ATV: 11080).
function aio_setup_url($ip, $tok)
{
    $port = getenv('HD_HTTP_LOCAL_PORT');
    $host = is_string($port) && $port !== '80' && ctype_digit($port) ? "$ip:$port" : $ip;
    return "http://$host/cgi-bin/plugins/" . AIO_PLUGIN . "/settings?t=$tok";
}

// $p: aio_setup_params of the screen, on to setup_refresh.
function aio_setup_timer_acts($p)
{
    return array(GUI_EVENT_TIMER => aio_input('setup_tick', $p));
}

function aio_setup_timer()
{
    return array(GuiTimerDef::delay_ms => AIO_SETUP_TICK_MS);
}

// Side in px of a QR picture made by AioQrPng (square, IHDR width), 0 if
// there is none or it is not ours.
function aio_setup_png_side($png)
{
    clearstatcache();
    $h = is_file($png) && !is_link($png) && is_readable($png) ? file_get_contents($png, false, null, 0, 24) : '';
    if (strlen($h) !== 24 || substr($h, 0, 16) !== "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR")
        return 0;
    $wh = unpack('Nw/Nh', substr($h, 16, 8));
    return $wh['w'] === $wh['h'] && $wh['w'] > 0 && $wh['w'] <= 2000 ? $wh['w'] : 0;
}

// The QR dialog: the page address as a QR and as text; its timer waits for
// a new settings.json. $renew: a new token first ("Change the link").
function aio_setup_qr($p, $renew = false)
{
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? strval(DuneSystem::$properties['tmp_dir_path']) : '';
    if (aio_data_dir() === '' || !is_dir(aio_data_dir()))
        return aio_error('err_setup_dir');
    $ip = aio_setup_ip();
    if ($ip === '')
        return aio_error('err_setup_ip');
    $tok = aio_setup_token($renew);
    if ($tok === '')
        return aio_error('err_setup_write');
    $url = aio_setup_url($ip, $tok);

    // One picture per address (token, IP, port), kept in tmp_dir: making it
    // takes ~0.6 s on the device. The shell caches pictures by path, so a
    // new address gets a new name; the old pictures go.
    $png = rtrim($tmp, '/') . '/qr_' . substr(md5($url), 0, 8) . '.png';
    if ($tmp === '' || (!is_dir($tmp) && !mkdir($tmp, 0755, true)))
        return aio_error('err_setup_write');
    foreach ((array) glob(rtrim($tmp, '/') . '/qr_*.png') as $old)
    {
        if (is_string($old) && $old !== $png)
            unlink($old);
    }
    $side = aio_setup_png_side($png);
    if ($side > 0)
        aio_log('setup: QR from tmp_dir');
    else
    {
        require_once dirname(__FILE__) . '/qrpng.php';
        $t = microtime(true);
        $qr = AioQrPng::make($url, 8, 4);
        if (!aio_write_file($png, $qr['png']))
            return aio_error('err_setup_write');
        $side = $qr['size'];
        aio_log(sprintf('setup: QR v%d, %d px, %d ms', $qr['version'], $side, (microtime(true) - $t) * 1000));
    }
    AioSetup::$sig = aio_settings_sig();
    AioSetup::$wait = 0;
    // The shell cuts a long label in the middle: the address in two lines.
    $q = strpos($url, '?');

    $defs = array(
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
            '<icon width="' . $side . '" height="' . $side . '">' . $png . '</icon>')),
        aio_ctl('', GUI_CONTROL_VGAP, array(GuiVGapDef::vgap => $side)),
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => substr($url, 0, $q))),
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => substr($url, $q))),
        aio_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => '%tr%setup_bookmark')),
        aio_ctl('close', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => '%tr%setup_close',
            GuiButtonDef::width => 300, GuiButtonDef::push_action => aio_close_and(null))));
    $defs[0][GuiControlDef::params] = array('smart' => 1);
    return aio_dialog_act('%tr%setup_phone', $defs, array(
        ShowDialogActionData::preferred_width => 1500,
        ShowDialogActionData::actions => aio_setup_timer_acts($p),
        ShowDialogActionData::timer => aio_setup_timer()));
}

// After a save the page makes the config of the chosen server in a second
// write (cgi/settings.php aio_cgi_aio_sync): Melange config, none there yet
// in the mode chosen, P2P or a Debrid key.
function aio_setup_config_due()
{
    $s = aio_settings_read_logged();
    return $s['source'] === 'made' && aio_settings_base($s) !== '' && !aio_settings_conf($s) &&
        ($s['aio_mode'] === AIO_MODE_P2P || $s['rd_key'] !== '' || $s['tb_key'] !== '');
}

// Timer of the QR dialog: a new settings.json closes it and redraws the
// screen; a save before the config is made - at the write with the config or
// AIO_SETUP_WAIT_S after the last such save (the config failed).
function aio_setup_tick($p)
{
    $sig = aio_settings_sig();
    // The clock gone back: over too, never an endless wait.
    $late = AioSetup::$wait > 0 && (time() - AioSetup::$wait >= AIO_SETUP_WAIT_S || time() < AioSetup::$wait);
    if (AioSetup::$sig === null)
    {
        AioSetup::$sig = $sig;
        AioSetup::$wait = 0;
    }
    else if ($sig !== AioSetup::$sig && aio_setup_config_due())
    {
        AioSetup::$sig = $sig;
        AioSetup::$wait = time();
        aio_log('setup: settings.json changed, waiting for the config');
    }
    else if ($sig !== AioSetup::$sig || $late)
    {
        aio_log('setup: ' . ($sig !== AioSetup::$sig ? 'settings.json changed' : 'no config in ' . AIO_SETUP_WAIT_S . ' s') .
            ', dialog closed');
        AioSetup::$sig = null;
        AioSetup::$wait = 0;
        // reset_controls right in close_dialog_and_run is run by the shell but
        // does not redraw the screen (device, 0.14.0): our own input after
        // the dialog, which replaces the screen (aio_setup_refresh).
        return aio_close_and(aio_input('setup_refresh', $p));
    }
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => aio_setup_timer_acts($p),
            ChangeBehaviourActionData::timer => aio_setup_timer()));
}

// The settings screen replaced by a fresh one; $p of aio_setup_params. The catalog under
// it ($p['from']) is read again: its rows may have changed, and Back does not ask for it
// (the shell finds a screen by its exact media_url). A first run: the catalog itself, the
// settings until AIOStreams is set; AIOStreams gone: the catalog under them shows the
// settings too, so both go for one such screen, no settings over settings.
function aio_setup_refresh($p)
{
    aio_log('setup: screen redrawn');
    $id = substr(aio_rid(), 0, 8);
    $from = isset($p['from']) && aio_catv_is_main($p['from']) ? $p['from'] : '';
    if ($from === '')
        return aio_replace_act("setup:$id", '%tr%plugin_caption');
    if (isset($p['first']) || Aio::$settings['base'] === '')
        return aio_replace_act("catalog:$id", '%tr%plugin_caption', isset($p['first']) ? 1 : 2);
    return array(
        GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
        GuiAction::data => array(
            PluginInvalidateFoldersActionData::media_urls => array($from),
            PluginInvalidateFoldersActionData::post_action => aio_replace_act("setup:$id:$from", '%tr%setup_menu'),
            PluginInvalidateFoldersActionData::all_except => false));
}

// The request of aio_conf_create for the quick setup, as the page sends it
// (aio_cgi_aio_req): POST of $json to /api/v1/user of the server $base by
// aio_http_json -> array(curl errno (-1: none known), HTTP code,
// aio_conf_decode of the body; null: not JSON or over AIO_CONF_API_MAX). Only
// a POST: $method and $auth are those of aio_conf_create (POST, none).
/**
 * @param string $base
 * @param string $method
 * @param string $json
 * @param mixed $auth
 * @return array{int, int, mixed}
 */
function aio_quick_req($base, $method, $json, $auth)
{
    list($code, $body) = aio_http_json("$base/api/v1/user", $json, $err, 5, AIO_CONF_API_TIMEOUT, AIO_CONF_API_MAX);
    if ($err !== '' && $err !== AIO_HTTP_TOO_BIG)
        return array(preg_match('/^curl ([0-9]+):/', $err, $m) ? intval($m[1]) : -1, 0, null);
    return array(0, $code, $body !== '' ? aio_conf_decode($body) : null);
}

// The quick setup failed by the reply $r of aio_conf_create (null: no
// template in the plugin) -> its error; the message of the server is shown,
// never logged.
/**
 * @param array{int, int, mixed}|null $r
 * @return array<string, mixed>
 */
function aio_quick_fail($r)
{
    if (!$r)
        return aio_error('err_quick_server', 'no template in the plugin');
    list($e, $code, $j) = $r;
    list($ec, $msg) = aio_conf_err($j);
    if ($ec !== '')
        aio_log("setup: quick: $ec");
    if ($e)
        return aio_error('err_quick_net', $e > 0 ? "curl $e" : '');
    if ($code === 429)
        return aio_error('err_quick_limit');
    if ($msg === '')
        return aio_error('err_quick_server', "HTTP $code");
    aio_log('error: err_quick_server (its message)');
    return aio_error('err_quick_server', $msg, false, false);
}

// The mode "Торренты" on the server of Melange, as the page saves it (the
// answer "Торренты" of "Где брать фильмы", the server of the list): the rest
// of $s as it is (aio_tpl_p2p, its default p2p_jacred; the Debrid keys,
// no_torrents - in this mode only kept).
/**
 * @param AioSettings $s
 * @return AioSettings
 */
function aio_quick_settings($s)
{
    $s['source'] = 'made';
    $s['aio_server'] = AIO_AIO_SERVER_MELANGE;
    $s['aio_mode'] = AIO_MODE_P2P;
    return $s;
}

// "Quick setup: torrents" (0.51.0) of the settings screen ($p of
// aio_setup_params) and of the window "No config" of play_action ($p['card'],
// $p['lang']: the language of the card). TorrServer is asked first
// (aio_ts_alive): none is no stop, the catalog and search work without it -
// said after. The settings of aio_quick_settings; the config there in that
// mode made by aio_conf_create (one there already: no request). settings.json
// is written only after it, read again just before (as the page); a failure,
// a file not read too, writes nothing. Then aio_quick_done.
/**
 * @param array<string, string> $p
 * @return array<string, mixed>
 */
function aio_setup_quick($p)
{
    $dir = aio_data_dir();
    if ($dir === '' || !is_dir($dir))
        return aio_error('err_setup_dir');
    // A file there not read (aio_settings_load logged why): not overwritten with the defaults.
    $s = aio_quick_settings(aio_settings_read($dir, $why));
    if ($why !== '')
        return aio_error('err_quick_read');
    $ts = aio_ts_alive();
    $conf = aio_settings_conf($s);
    if ($conf)
        aio_log('setup: quick: a config of P2P there, no request');
    else
    {
        $pass = substr(aio_setup_rand_hex(), 0, 24);
        if ($pass === '')
        {
            aio_log('setup: quick: no source of random bytes');
            return aio_error('err_setup_write');
        }
        $t = microtime(true);
        list($conf, $r) = aio_conf_create($s, null, $pass, 'aio_quick_req');
        aio_log(sprintf('setup: quick: create: %s%s, %.2f s', !$r ? 'no template' : ($r[0] ? "curl $r[0]" : "HTTP $r[1]"),
            $conf ? ', made' : '', microtime(true) - $t));
        if (!$conf)
            return aio_quick_fail($r);
    }
    // Read again: the page may have saved while the server answered; a file
    // not read then is not overwritten with the defaults either.
    $s = aio_quick_settings(aio_settings_read($dir, $why));
    if ($why !== '')
    {
        aio_log("setup: quick: settings.json $why, not written");
        return aio_error('err_quick_read');
    }
    $s['aio_confs'][AIO_AIO_SERVER_MELANGE][AIO_MODE_P2P] = $conf;
    if (!aio_settings_write($s))
    {
        aio_log('setup: quick: settings.json not written');
        return aio_error('err_quick_write');
    }
    aio_log('setup: quick: saved' . ($ts ? '' : ', TorrServer does not answer'));
    // The screen of the catalog under it is read by this operation too.
    aio_settings_load();
    return aio_quick_done($p, $ts);
}

// What the quick setup ($p of aio_setup_quick) shows when done; $ts:
// TorrServer answered. The window of a card: set up, play again (TorrServe
// first, if none). The settings screen: replaced at once (aio_setup_refresh);
// no TorrServer - a dialog first, in the language of the Dune.
/**
 * @param array<string, string> $p
 * @param bool $ts
 * @return array<string, mixed>
 */
function aio_quick_done($p, $ts)
{
    $card = isset($p['card']);
    $lang = $card && isset($p['lang']) ? $p['lang'] : aio_catv_lang();
    $lines = $ts ? array() : aio_dialog_lines(aio_tr($lang, 'quick_no_ts'));
    if ($card)
        return aio_dialog(aio_tr($lang, 'quick_done'), array_merge($lines, aio_dialog_lines(aio_tr($lang, 'quick_again'))),
            array('OK' => aio_close_and(null)));
    if ($ts)
        return aio_setup_refresh($p);
    // OK or Back: the screen replaced after the dialog (as the QR dialog does).
    $a = aio_dialog(aio_tr($lang, 'quick_done'), $lines, array('OK' => aio_close_and(aio_input('setup_refresh', $p))));
    $a[GuiAction::data][ShowDialogActionData::close_by_return] = false;
    $a[GuiAction::data][ShowDialogActionData::actions] = array(GUI_EVENT_KEY_RETURN => aio_input('setup_quick_ok', $p));
    return $a;
}

function aio_setup_relink($p)
{
    return aio_dialog('%tr%setup_relink', array('%tr%setup_relink_warn'), array(
        '%tr%setup_relink_ok' => aio_close_and(aio_input('setup_relink_do', $p)),
        '%tr%setup_cancel' => aio_close_and(null)));
}

// handle_user_input of the settings: -> action or null.
function aio_setup_input($in, $control)
{
    $p = array();
    foreach (array('from', 'first') as $k)
    {
        if (aio_catv_in($in, $k) !== '')
            $p[$k] = aio_catv_in($in, $k);
    }
    // The quick setup from the window of a card: that, and its language.
    if (aio_catv_in($in, 'card') === '1')
        $p['card'] = '1';
    if (in_array(aio_catv_in($in, 'lang'), array('ru', 'en'), true))
        $p['lang'] = aio_catv_in($in, 'lang');
    if ($control === 'setup')
    {
        // The "Settings" item of the popup menu of the Applications icon.
        aio_log('setup: from the popup menu');
        return aio_setup_open_act();
    }
    if ($control === 'setup_qr')
        return aio_setup_qr($p);
    if ($control === 'setup_tick')
        return aio_setup_tick($p);
    if ($control === 'setup_refresh')
        return aio_setup_refresh($p);
    if ($control === 'setup_relink')
        return aio_setup_relink($p);
    if ($control === 'setup_relink_do')
        return aio_setup_qr($p, true);
    if ($control === 'setup_quick')
        return aio_setup_quick($p);
    // Back in the dialog of the quick setup: closed, the screen replaced.
    if ($control === 'setup_quick_ok')
        return aio_close_and(aio_input('setup_refresh', $p));
    return null;
}
