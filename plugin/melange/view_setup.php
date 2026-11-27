<?php
// Settings screen (media_url "setup", controls) and "Set up from a phone":
// a QR with the address of the settings page (cgi/settings.php). The page
// writes data_dir/settings.json, the plugin reads it; the QR dialog polls the
// file by its timer. The one write of the plugin: "Quick setup: torrents"
// (0.51.0, mel_setup_quick), as the page saves "Торренты". The token of the
// page is permanent (a bookmark keeps working): data_dir/web_token, written
// here (new on "Change the link") and by the import of a backup on the page
// (its token back, 0.51.0); read anew at each QR, never kept in memory.
// Neither the token nor the page address goes to the log. Reads Mel::$settings.

// The config of AIOStreams the quick setup makes (mel_conf_create; its request:
// mel_user_req of main.php): shared with the settings page. Plain PHP, no firmware API.
require_once dirname(__FILE__) . '/cgi/aio_conf.php';

define('MEL_SETUP_TICK_MS', 1500);
// Seconds the QR dialog waits after a save for the config the page makes
// next (a=aio_sync: up to 2 requests of 20 s); a failed one writes nothing.
define('MEL_SETUP_WAIT_S', 45);
// play_action without an address: true - a dialog with "Set up"; false -
// the settings screen right away (if a dialog from play_action fails).
if (!defined('MEL_NO_ADDR_DIALOG'))
    define('MEL_NO_ADDR_DIALOG', true);
// Sources of random bytes for the token. Tests may define them first.
if (!defined('MEL_OPENSSL'))
    define('MEL_OPENSSL', true);
if (!defined('MEL_URANDOM'))
    define('MEL_URANDOM', '/dev/urandom');

class MelSetup
{
    // ino:mtime:size of settings.json when the QR dialog opened; null: no
    // dialog (or php_server restarted under it).
    public static $sig = null;
    // time() of a save after which the page makes the config; 0: not waiting.
    public static $wait = 0;
}

// The settings screen; $from: the screen of the catalog it is opened over,
// "setup::<from>", so a save reads that one again (mel_setup_refresh), and its
// caption is "Settings" (the path is "Melange > Settings", not "Melange > Melange").
function mel_setup_open_act($from = '')
{
    $cat = mel_catv_is_main($from);
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => $cat ? "setup::$from" : 'setup',
            PluginOpenFolderActionData::caption => $cat ? '%tr%setup_menu' : '%tr%plugin_caption'));
}

// The item "Настройки Melange" of a side panel "Действия" (kit_panel.php) in language $lang: the settings screen.
/**
 * @param string $lang
 * @return MelKpItem
 */
function mel_setup_item($lang)
{
    return array('id' => 'setup', 'label' => mel_tr($lang, 'setup_melange'), 'icon' => 'k_icon_settings',
        'act' => mel_setup_open_act());
}

// Params of the inputs of the settings screen $url, nothing kept in memory (a restart
// of php_server under the QR dialog): 'from' - the screen of the catalog to read again
// after a save; 'first' - the screen is that catalog itself (a first run).
function mel_setup_params($url)
{
    if (mel_catv_is_main($url))
        return array('from' => $url, 'first' => '1');
    $p = explode(':', $url, 3);
    return isset($p[2]) && mel_catv_is_main($p[2]) ? array('from' => $p[2]) : array();
}

// play_action without a config: none made on the chosen server, or no link
// to the own one.
function mel_no_address($lang)
{
    $key = Mel::$settings['own'] ? 'err_no_manifest_own' : 'err_no_manifest';
    mel_log("error: $key -> " . (MEL_NO_ADDR_DIALOG ? 'dialog' : 'settings screen'));
    if (!MEL_NO_ADDR_DIALOG)
        return mel_setup_open_act();
    // The quick setup from a card: its dialog says what next, in the language of the card.
    return mel_dialog(mel_tr($lang, $key), array(), array(
        mel_tr($lang, 'setup_quick') => mel_close_and(mel_input('setup_quick',
            array('card' => '1', 'lang' => $lang === 'ru' ? 'ru' : 'en'), 0)),
        mel_tr($lang, 'setup_open') => mel_close_and(mel_setup_open_act()),
        mel_tr($lang, 'setup_close') => mel_close_and(null)));
}

// Host of an address of the settings, '' for none.
function mel_setup_host($url)
{
    $h = $url !== '' ? parse_url($url, PHP_URL_HOST) : null;
    return is_string($h) ? $h : '';
}

// The chosen JacRed: a built-in id or the host of the own one ('' for none).
function mel_setup_jacred($s)
{
    $l = mel_jacred_list($s);
    return $l ? $l[0]['host'] : '';
}

function mel_setup_label($title, $caption)
{
    return mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => $caption), $title);
}

function mel_setup_button($name, $caption, $action)
{
    return mel_ctl($name, GUI_CONTROL_BUTTON, array(
        GuiButtonDef::caption => $caption, GuiButtonDef::width => 600, GuiButtonDef::push_action => $action));
}

function mel_setup_defs($p)
{
    $dir = mel_data_dir();
    $s = mel_settings_read_logged();
    $defs = array();
    $h = mel_setup_host(mel_settings_manifest($s));
    // "AIOStreams - own config" / "AIOStreams - Melange config": the host of
    // its server (another server too: on the TV, never in the log).
    $defs[] = mel_setup_label($s['source'] === 'own' ? '%tr%setup_aio_own' : '%tr%setup_aio_made',
        $h !== '' ? $h : '%tr%setup_not_set');
    $jr = mel_setup_jacred($s);
    $defs[] = mel_setup_label('JacRed', $jr !== '' ? $jr : '%tr%setup_not_set');
    if ($dir === '' || !is_dir($dir))
        $defs[] = mel_setup_label(null, '%tr%err_setup_dir');
    // No AIOStreams yet: the quick setup first; no link of the page to change.
    $none = mel_manifest_base(mel_settings_manifest($s)) === '';
    if ($none)
        $defs[] = mel_setup_button('quick', '%tr%setup_quick', mel_input('setup_quick', $p, 0));
    $defs[] = mel_setup_button('phone', '%tr%setup_phone', mel_input('setup_qr', $p));
    if (!$none)
        $defs[] = mel_setup_button('relink', '%tr%setup_relink', mel_input('setup_relink', $p));
    return $defs;
}

// $url: "setup", "setup:<n>", "setup:<n>:<catalog>", or the catalog itself without AIOStreams.
function mel_setup_folder_view($url)
{
    $s = mel_settings_read_logged();
    mel_log('setup: screen, AIOStreams ' . ($s['source'] === 'own' ? 'own config' : ($s['aio_server'] === 'own' ?
        'made on another server' : 'made on a public one')) . (mel_settings_manifest($s) !== '' ? ', set' : ', not set') .
        ', JacRed ' . ($s['jacred'] === 'own' ? 'own' : $s['jacred']));
    return array(
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::data => array(
            PluginControlsFolderView::defs => mel_setup_defs(mel_setup_params($url)),
            PluginControlsFolderView::initial_sel_ndx => -1));
}

// 32 hex of openssl or urandom; '' without them.
function mel_setup_rand_hex()
{
    $b = MEL_OPENSSL && function_exists('openssl_random_pseudo_bytes') ? (string) openssl_random_pseudo_bytes(16) : '';
    if (strlen($b) !== 16 && is_readable(MEL_URANDOM))
        $b = (string) file_get_contents(MEL_URANDOM, false, null, 0, 16);
    if (strlen($b) !== 16)
        return '';
    return bin2hex($b);
}

// The token of the page: the one in data_dir (mel_token_rewrite), a new one
// when there is none (or $renew). '' on failure.
function mel_setup_token($renew)
{
    if (mel_data_dir() === '')
        return '';
    $t = $renew ? '' : mel_token_rewrite();
    if ($t !== '')
        return $t;
    $t = mel_setup_rand_hex();
    if ($t === '')
    {
        // A token of md5(uniqid) could be guessed from the LAN.
        mel_log('setup: no source of random bytes, no link');
        return '';
    }
    mel_log('setup: ' . ($renew ? 'new link' : 'first link'));
    return mel_write_file(mel_data_dir() . '/' . MEL_TOKEN_FILE, $t) ? $t : '';
}

// IP of the Dune for the phone, from ifconfig: eth0, else wlan0, else the
// first other interface but loopback and VPNs (tun*, ppp*, wg*). busybox lists
// interfaces by name, so on Wi-Fi a VPN tun0 comes before wlan0. Else the
// source address of a UDP socket (connect sends nothing; may be a VPN's too).
function mel_setup_ip()
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
function mel_setup_url($ip, $tok)
{
    $port = getenv('HD_HTTP_LOCAL_PORT');
    $host = is_string($port) && $port !== '80' && ctype_digit($port) ? "$ip:$port" : $ip;
    return "http://$host/cgi-bin/plugins/" . MEL_PLUGIN . "/settings?t=$tok";
}

// $p: mel_setup_params of the screen, on to setup_refresh.
function mel_setup_timer_acts($p)
{
    return array(GUI_EVENT_TIMER => mel_input('setup_tick', $p));
}

function mel_setup_timer()
{
    return array(GuiTimerDef::delay_ms => MEL_SETUP_TICK_MS);
}

// The QR dialog: the page address as a QR and as text; its timer waits for
// a new settings.json. $renew: a new token first ("Change the link").
function mel_setup_qr($p, $renew = false)
{
    if (mel_data_dir() === '' || !is_dir(mel_data_dir()))
        return mel_error('err_setup_dir');
    $ip = mel_setup_ip();
    if ($ip === '')
        return mel_error('err_setup_ip');
    $tok = mel_setup_token($renew);
    if ($tok === '')
        return mel_error('err_setup_write');
    $url = mel_setup_url($ip, $tok);

    // One picture per address (token, IP, port), kept in tmp_dir: making it
    // takes ~0.6 s on the device; the old pictures go.
    $qr = mel_qr_file('qr_', '', $url, 8, 0, -1, 'setup');
    if (!$qr)
        return mel_error('err_setup_write');
    list($png, $side) = $qr;
    MelSetup::$sig = mel_settings_sig();
    MelSetup::$wait = 0;
    // The shell cuts a long label in the middle: the address in two lines.
    $q = strpos($url, '?');

    $defs = array(
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption =>
            '<icon width="' . $side . '" height="' . $side . '">' . $png . '</icon>')),
        mel_ctl('', GUI_CONTROL_VGAP, array(GuiVGapDef::vgap => $side)),
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => substr($url, 0, $q))),
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => substr($url, $q))),
        mel_ctl('', GUI_CONTROL_LABEL, array(GuiLabelDef::caption => '%tr%setup_bookmark')),
        mel_ctl('close', GUI_CONTROL_BUTTON, array(GuiButtonDef::caption => '%tr%setup_close',
            GuiButtonDef::width => 300, GuiButtonDef::push_action => mel_close_and(null))));
    $defs[0][GuiControlDef::params] = array('smart' => 1);
    return mel_dialog_act('%tr%setup_phone', $defs, array(
        ShowDialogActionData::preferred_width => 1500,
        ShowDialogActionData::actions => mel_setup_timer_acts($p),
        ShowDialogActionData::timer => mel_setup_timer()));
}

// After a save the page makes the config of the chosen server in a second
// write (cgi/settings.php mel_cgi_aio_sync): the main profile (v2) a config of
// Melange (Debrid, Торренты) with none of its own yet, P2P or a Debrid key of it.
function mel_setup_config_due()
{
    $s = mel_settings_read_logged();
    $m = mel_profile_main($s);
    $p = $m >= 0 ? $s['profiles'][$m] : null;
    return $p && isset($p['base']) && !$p['conf'] && ($p['mode'] === MEL_MODE_P2P || $p['rd_key'] !== '' || $p['tb_key'] !== '');
}

// Timer of the QR dialog: a new settings.json closes it and redraws the
// screen; a save before the config is made - at the write with the config or
// MEL_SETUP_WAIT_S after the last such save (the config failed).
function mel_setup_tick($p)
{
    $sig = mel_settings_sig();
    // The clock gone back: over too, never an endless wait.
    $late = MelSetup::$wait > 0 && (time() - MelSetup::$wait >= MEL_SETUP_WAIT_S || time() < MelSetup::$wait);
    if (MelSetup::$sig === null)
    {
        MelSetup::$sig = $sig;
        MelSetup::$wait = 0;
    }
    else if ($sig !== MelSetup::$sig && mel_setup_config_due())
    {
        MelSetup::$sig = $sig;
        MelSetup::$wait = time();
        mel_log('setup: settings.json changed, waiting for the config');
    }
    else if ($sig !== MelSetup::$sig || $late)
    {
        mel_log('setup: ' . ($sig !== MelSetup::$sig ? 'settings.json changed' : 'no config in ' . MEL_SETUP_WAIT_S . ' s') .
            ', dialog closed');
        MelSetup::$sig = null;
        MelSetup::$wait = 0;
        // reset_controls right in close_dialog_and_run is run by the shell but
        // does not redraw the screen (device, 0.14.0): our own input after
        // the dialog, which replaces the screen (mel_setup_refresh).
        return mel_close_and(mel_input('setup_refresh', $p));
    }
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => mel_setup_timer_acts($p),
            ChangeBehaviourActionData::timer => mel_setup_timer()));
}

// The settings screen replaced by a fresh one; $p of mel_setup_params. The catalog under
// it ($p['from']) is read again: its rows may have changed, and Back does not ask for it
// (the shell finds a screen by its exact media_url). A first run: the catalog itself, the
// settings until a source is ready (v2: Lampac too); none any more: the catalog under them shows the
// settings too, so both go for one such screen, no settings over settings.
function mel_setup_refresh($p)
{
    mel_log('setup: screen redrawn');
    $id = substr(mel_rid(), 0, 8);
    $from = isset($p['from']) && mel_catv_is_main($p['from']) ? $p['from'] : '';
    if ($from === '')
        return mel_replace_act("setup:$id", '%tr%plugin_caption');
    if (isset($p['first']) || !Mel::$settings['ready'])
        return mel_replace_act(mel_catv_main_url(mel_catv_tab($from), $id), '%tr%plugin_caption', isset($p['first']) ? 1 : 2);
    return array(
        GuiAction::handler_string_id => PLUGIN_INVALIDATE_FOLDERS_ACTION_ID,
        GuiAction::data => array(
            PluginInvalidateFoldersActionData::media_urls => array($from),
            PluginInvalidateFoldersActionData::post_action => mel_replace_act("setup:$id:$from", '%tr%setup_menu'),
            PluginInvalidateFoldersActionData::all_except => false));
}

// The quick setup failed by the reply $r of mel_conf_create (null: no
// template in the plugin) -> its error; the message of the server is shown,
// never logged.
/**
 * @param array{int, int, mixed}|null $r
 * @return array<string, mixed>
 */
function mel_quick_fail($r)
{
    if (!$r)
        return mel_error('err_quick_server', 'no template in the plugin');
    list($e, $code, $j) = $r;
    list($ec, $msg) = mel_conf_err($j);
    if ($ec !== '')
        mel_log("setup: quick: $ec");
    if ($e)
        return mel_error('err_quick_net', $e > 0 ? "curl $e" : '');
    if ($code === 429)
        return mel_error('err_quick_limit');
    if ($msg === '')
        return mel_error('err_quick_server', "HTTP $code");
    mel_log('error: err_quick_server (its message)');
    return mel_error('err_quick_server', $msg, false, false);
}

// The mode "Торренты" on the server of Melange, as the page saves it (the
// answer "Торренты" of "Где брать фильмы", the server of the list): the rest
// of $s as it is (aio_tpl_p2p, its default p2p_jacred; the Debrid keys,
// no_torrents - in this mode only kept).
/**
 * @param MelSettings $s
 * @return MelSettings
 */
function mel_quick_settings($s)
{
    $s['source'] = 'made';
    $s['aio_server'] = MEL_AIO_SERVER_MELANGE;
    $s['aio_mode'] = MEL_MODE_P2P;
    return $s;
}

// The profile of the quick setup in the settings $s (v2, spec §2.10): Торренты on the server of Melange - its index,
// -1 none.
/**
 * @param MelSettings $s
 * @return int
 */
function mel_quick_profile($s)
{
    foreach ($s['profiles'] as $i => $p)
    {
        if (isset($p['base']) && $p['base'] === MEL_AIO_SERVER_MELANGE && $p['mode'] === MEL_MODE_P2P)
            return $i;
    }
    return -1;
}

// The settings $s with the config $conf of the quick setup in its profile (mel_quick_profile; v2, spec §2.10): one
// without a config gets it; none - a new one at the end of those of releases, named by its kind
// (mel_profile_word, mel_profile_free_name), of the settings of mel_quick_settings (the template of P2P, the JacRed,
// the keys of the top). It is the main one when there is none or the main one is not ready. -> array(the settings,
// '') or array(null, the key of the error: src_full - the profiles full).
/**
 * @param MelSettings $s
 * @param MelConf $conf
 * @return array{0: MelSettings|null, 1: string}
 */
function mel_quick_put($s, $conf)
{
    $k = mel_quick_profile($s);
    if ($k >= 0 && !$s['profiles'][$k]['conf'])
        $s['profiles'][$k]['conf'] = $conf;
    if ($k < 0)
    {
        $q = mel_quick_settings($s);
        $p = mel_profile_ok(array('name' => mel_profile_free_name($s, mel_profile_word('p2p')),
            'base' => MEL_AIO_SERVER_MELANGE, 'mode' => MEL_MODE_P2P, 'tpl' => $q['aio_tpl_p2p'], 'jacred' => $s['jacred'],
            'jacred_own_url' => $s['jacred_own_url'], 'jacred_own_key' => $s['jacred_own_key'], 'conf' => $conf), $s);
        if (!$p || count($s['profiles']) >= MEL_PROFILES_MAX)
            return array(null, 'src_full');
        $p['id'] = mel_profile_new_id($s, $p['name']);
        list($s, $k) = mel_profile_add($s, $p);
    }
    $m = mel_profile_main($s);
    if ($m < 0 || !mel_profile_ready($s['profiles'][$m]))
        $s['src_main'] = $s['profiles'][$k]['id'];
    return array($s, '');
}

// "Quick setup: torrents" (0.51.0) of the settings screen ($p of
// mel_setup_params) and of the window "No config" of play_action ($p['card'],
// $p['lang']: the language of the card). TorrServer is asked first
// (mel_ts_alive): none is no stop, the catalog and search work without it -
// said after. The config of its profile (mel_quick_profile; none - the one of
// the cell of the server of Melange in P2P no other profile has; none - made by
// mel_conf_create with the settings of mel_quick_settings). settings.json is
// written only after it, read again just before (as the page), the profile in
// it (mel_quick_put); a failure, a file not read too, writes nothing. Then
// mel_quick_done.
/**
 * @param array<string, string> $p
 * @return array<string, mixed>
 */
function mel_setup_quick($p)
{
    $dir = mel_data_dir();
    if ($dir === '' || !is_dir($dir))
        return mel_error('err_setup_dir');
    // A file there not read (mel_settings_load logged why): not overwritten with the defaults.
    $s = mel_settings_read($dir, $why);
    if ($why !== '')
        return mel_error('err_quick_read');
    $ts = mel_ts_alive();
    $k = mel_quick_profile($s);
    $conf = $k >= 0 ? $s['profiles'][$k]['conf'] : null;
    $cell = mel_settings_conf(mel_quick_settings($s));
    if (!$conf && $cell && !mel_profile_held($s, $cell))
        $conf = $cell;
    $made = !$conf;
    if ($conf)
        mel_log('setup: quick: a config of P2P there, no request');
    else
    {
        if ($k < 0 && count($s['profiles']) >= MEL_PROFILES_MAX)
        {
            mel_log('setup: quick: the profiles full, no request');
            return mel_error('src_full');
        }
        $pass = substr(mel_setup_rand_hex(), 0, 24);
        if ($pass === '')
        {
            mel_log('setup: quick: no source of random bytes');
            return mel_error('err_setup_write');
        }
        $t = microtime(true);
        list($conf, $r) = mel_conf_create(mel_quick_settings($s), null, $pass, 'mel_user_req');
        mel_log(sprintf('setup: quick: create: %s%s, %.2f s', !$r ? 'no template' : ($r[0] ? "curl $r[0]" : "HTTP $r[1]"),
            $conf ? ', made' : '', microtime(true) - $t));
        if (!$conf)
            return mel_quick_fail($r);
    }
    // Read again: the page may have saved while the server answered; a file
    // not read then is not overwritten with the defaults either.
    $s = mel_settings_read($dir, $why);
    if ($why !== '')
    {
        mel_log("setup: quick: settings.json $why, not written");
        return mel_error('err_quick_read');
    }
    list($s, $err) = mel_quick_put($s, $conf);
    if (!$s)
    {
        mel_log('setup: quick: the profiles full, not written');
        return mel_error($err);
    }
    if (!mel_settings_write($s))
    {
        mel_log('setup: quick: settings.json not written');
        return mel_error('err_quick_write');
    }
    // Made by the template now: no migration to run.
    if ($made && !mel_mig_mark($conf['uuid'], $s))
        mel_log('setup: quick: the migration mark not written');
    $k = mel_quick_profile($s);
    mel_log('setup: quick: saved' . ($ts ? '' : ', TorrServer does not answer') . ', profile ' .
        mel_profile_tag($k, $s['profiles'][$k]) . ($s['profiles'][$k]['id'] === $s['src_main'] ? ', the main one' : ''));
    // The screen of the catalog under it is read by this operation too.
    mel_settings_load();
    return mel_quick_done($p, $ts);
}

// What the quick setup ($p of mel_setup_quick) shows when done; $ts:
// TorrServer answered. The window of a card: set up, play again (TorrServe
// first, if none). The settings screen: replaced at once (mel_setup_refresh);
// no TorrServer - a dialog first, in the language of the Dune.
/**
 * @param array<string, string> $p
 * @param bool $ts
 * @return array<string, mixed>
 */
function mel_quick_done($p, $ts)
{
    $card = isset($p['card']);
    $lang = $card && isset($p['lang']) ? $p['lang'] : mel_catv_lang();
    $lines = $ts ? array() : mel_dialog_lines(mel_tr($lang, 'quick_no_ts'));
    if ($card)
        return mel_dialog(mel_tr($lang, 'quick_done'), array_merge($lines, mel_dialog_lines(mel_tr($lang, 'quick_again'))),
            array('OK' => mel_close_and(null)));
    if ($ts)
        return mel_setup_refresh($p);
    // OK or Back: the screen replaced after the dialog (as the QR dialog does).
    $a = mel_dialog(mel_tr($lang, 'quick_done'), $lines, array('OK' => mel_close_and(mel_input('setup_refresh', $p))));
    $a[GuiAction::data][ShowDialogActionData::close_by_return] = false;
    $a[GuiAction::data][ShowDialogActionData::actions] = array(GUI_EVENT_KEY_RETURN => mel_input('setup_quick_ok', $p));
    return $a;
}

function mel_setup_relink($p)
{
    return mel_dialog('%tr%setup_relink', array('%tr%setup_relink_warn'), array(
        '%tr%setup_relink_ok' => mel_close_and(mel_input('setup_relink_do', $p)),
        '%tr%setup_cancel' => mel_close_and(null)));
}

// handle_user_input of the settings: -> action or null.
function mel_setup_input($in, $control)
{
    $p = array();
    foreach (array('from', 'first') as $k)
    {
        if (mel_catv_in($in, $k) !== '')
            $p[$k] = mel_catv_in($in, $k);
    }
    // The quick setup from the window of a card: that, and its language.
    if (mel_catv_in($in, 'card') === '1')
        $p['card'] = '1';
    if (in_array(mel_catv_in($in, 'lang'), array('ru', 'en'), true))
        $p['lang'] = mel_catv_in($in, 'lang');
    if ($control === 'setup')
    {
        // The "Settings" item of the popup menu of the Applications icon.
        mel_log('setup: from the popup menu');
        return mel_setup_open_act();
    }
    if ($control === 'setup_qr')
        return mel_setup_qr($p);
    if ($control === 'setup_tick')
        return mel_setup_tick($p);
    if ($control === 'setup_refresh')
        return mel_setup_refresh($p);
    if ($control === 'setup_relink')
        return mel_setup_relink($p);
    if ($control === 'setup_relink_do')
        return mel_setup_qr($p, true);
    if ($control === 'setup_quick')
        return mel_setup_quick($p);
    // Back in the dialog of the quick setup: closed, the screen replaced.
    if ($control === 'setup_quick_ok')
        return mel_close_and(mel_input('setup_refresh', $p));
    return null;
}
