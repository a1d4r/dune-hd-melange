<?php
// Settings screen (media_url "setup", controls) and "Set up from a phone":
// a QR with the address of the settings page (cgi/settings.php). The page
// writes data_dir/settings.json, the plugin only reads it; the QR dialog
// polls the file by its timer. The token of the page is permanent (a
// bookmark keeps working): data_dir/web_token, written here only, new on
// "Change the link". Neither the token nor the page address goes to the log.
// Reads Aio::$settings.

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

function aio_setup_open_act()
{
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => 'setup',
            PluginOpenFolderActionData::caption => '%tr%plugin_caption'));
}

// play_action without a config: none made on the chosen server, or no link
// to the own one.
function aio_no_address($lang)
{
    $key = Aio::$settings['own'] ? 'err_no_manifest_own' : 'err_no_manifest';
    aio_log("error: $key -> " . (AIO_NO_ADDR_DIALOG ? 'dialog' : 'settings screen'));
    if (!AIO_NO_ADDR_DIALOG)
        return aio_setup_open_act();
    return aio_dialog(aio_tr($lang, $key), array(), array(
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

function aio_setup_defs()
{
    $dir = aio_data_dir();
    $s = aio_settings_read($dir);
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
    $defs[] = aio_setup_button('phone', '%tr%setup_phone', aio_input('setup_qr'));
    $defs[] = aio_setup_button('relink', '%tr%setup_relink', aio_input('setup_relink'));
    return $defs;
}

function aio_setup_folder_view()
{
    $s = aio_settings_read(aio_data_dir());
    aio_log('setup: screen, AIOStreams ' . ($s['source'] === 'own' ? 'own config' : ($s['aio_server'] === 'own' ?
        'made on another server' : 'made on a public one')) . (aio_settings_manifest($s) !== '' ? ', set' : ', not set') .
        ', JacRed ' . ($s['jacred'] === 'own' ? 'own' : $s['jacred']));
    return array(
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::data => array(
            PluginControlsFolderView::defs => aio_setup_defs(),
            PluginControlsFolderView::initial_sel_ndx => -1));
}

// 'ino:mtime:size' of settings.json, '' when there is none. The page renames
// a new file over it: a new inode even within the same second.
function aio_setup_sig()
{
    $f = aio_data_dir() . '/' . AIO_SETTINGS_FILE;
    clearstatcache();
    $s = aio_data_dir() !== '' && is_file($f) ? stat($f) : false;
    return $s === false ? '' : $s['ino'] . ':' . $s['mtime'] . ':' . $s['size'];
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

// The token of the page: the one in data_dir, a new one when there is none
// (or $renew). '' on failure.
function aio_setup_token($renew)
{
    $f = aio_data_dir() . '/' . AIO_TOKEN_FILE;
    if (aio_data_dir() === '')
        return '';
    clearstatcache();
    if (!$renew && is_file($f) && is_readable($f))
    {
        $t = trim((string) file_get_contents($f, false, null, 0, 64));
        if (aio_token_ok($t))
            return $t;
    }
    $t = aio_setup_rand_hex();
    if ($t === '')
    {
        // A token of md5(uniqid) could be guessed from the LAN.
        aio_log('setup: no source of random bytes, no link');
        return '';
    }
    aio_log('setup: ' . ($renew ? 'new link' : 'first link'));
    return aio_write_file($f, $t) ? $t : '';
}

// IP of the Dune for the phone: the first non-loopback address of ifconfig,
// else the source address of a UDP socket (connect sends nothing).
function aio_setup_ip()
{
    $o = array();
    exec('ifconfig 2>/dev/null', $o);
    if (preg_match_all('/inet addr: ?([0-9]{1,3}(?:\.[0-9]{1,3}){3})/', implode("\n", $o), $m))
    {
        foreach ($m[1] as $ip)
        {
            if (strpos($ip, '127.') !== 0)
                return $ip;
        }
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

function aio_setup_timer_acts()
{
    return array(GUI_EVENT_TIMER => aio_input('setup_tick'));
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
// a new settings.json.
function aio_setup_qr()
{
    $tmp = isset(DuneSystem::$properties['tmp_dir_path']) ? strval(DuneSystem::$properties['tmp_dir_path']) : '';
    if (aio_data_dir() === '' || !is_dir(aio_data_dir()))
        return aio_error('err_setup_dir');
    $ip = aio_setup_ip();
    if ($ip === '')
        return aio_error('err_setup_ip');
    $tok = aio_setup_token(false);
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
    AioSetup::$sig = aio_setup_sig();
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
        ShowDialogActionData::actions => aio_setup_timer_acts(),
        ShowDialogActionData::timer => aio_setup_timer()));
}

// After a save the page makes the config of the chosen server in a second
// write (cgi/settings.php aio_cgi_aio_sync): Melange config, no config there
// yet, a Debrid key.
function aio_setup_config_due()
{
    $s = aio_settings_read(aio_data_dir());
    $b = aio_settings_base($s);
    return $s['source'] === 'made' && $b !== '' && !isset($s['aio_confs'][$b]) &&
        ($s['rd_key'] !== '' || $s['tb_key'] !== '');
}

// Timer of the QR dialog: a new settings.json closes it and redraws the
// screen; a save before the config is made - at the write with the config or
// AIO_SETUP_WAIT_S after the last such save (the config failed).
function aio_setup_tick()
{
    $sig = aio_setup_sig();
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
        return aio_close_and(aio_input('setup_refresh'));
    }
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => aio_setup_timer_acts(),
            ChangeBehaviourActionData::timer => aio_setup_timer()));
}

// The settings screen replaced by a fresh one.
function aio_setup_refresh()
{
    aio_log('setup: screen redrawn');
    return aio_replace_act('setup:' . substr(aio_rid(), 0, 8), '%tr%plugin_caption');
}

function aio_setup_relink()
{
    return aio_dialog('%tr%setup_relink', array('%tr%setup_relink_warn'), array(
        '%tr%setup_relink_ok' => aio_close_and(aio_input('setup_relink_do')),
        '%tr%setup_cancel' => aio_close_and(null)));
}

// handle_user_input of the settings: -> action or null.
function aio_setup_input($control)
{
    if ($control === 'setup')
    {
        // The "Settings" item of the popup menu of the Applications icon.
        aio_log('setup: from the popup menu');
        return aio_setup_open_act();
    }
    if ($control === 'setup_qr')
        return aio_setup_qr();
    if ($control === 'setup_tick')
        return aio_setup_tick();
    if ($control === 'setup_refresh')
        return aio_setup_refresh();
    if ($control === 'setup_relink')
        return aio_setup_relink();
    if ($control === 'setup_relink_do')
        return aio_setup_token(true) !== '' ? aio_setup_qr() : aio_error('err_setup_write');
    return null;
}
