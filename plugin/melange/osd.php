<?php
// The green key in the player (0.48.0): a window over the video with the
// numbers of what plays - TorrServer: the torrent ("get": speeds, peers,
// loaded); Debrid: the release, the bit rate and buffering of the player
// (ext_command.state) -, redrawn every second while it is open. update_osd
// draws it, change_behaviour arms the next tick: the timer of the player
// fires once. Its state is in the params of the actions (mode, hash, the
// release), never in Aio: the list may be gone under the player. Reads
// Aio::$settings. Functions are global; the require order is in main.php.
// Types in the PHPDoc (AioOsdParams): phpstan-types.neon of the repository.

define('AIO_OSD_TICK_MS', 1000);
// operation_timeout of the actions, ms: the "get" takes 2 s at most.
define('AIO_OSD_TIMEOUT', 5000);
// The window at the top centre of 1920x1080: its plate (img/osd_bg.png, the
// same size) and up to AIO_OSD_LINES lines of the NORMAL font.
define('AIO_OSD_X', 360);
define('AIO_OSD_Y', 40);
define('AIO_OSD_W', 1200);
define('AIO_OSD_H', 250);
define('AIO_OSD_PAD', 40);
define('AIO_OSD_TOP', 25);
define('AIO_OSD_LH', 50);
define('AIO_OSD_LINES', 4);
// Characters of the name of the release: a text is not clipped by the plate.
define('AIO_OSD_NAME_MAX', 55);

// handle_user_input of our plugin from the player: no "Please wait" over the
// video (no show_dialog_delay).
/**
 * @param string $control
 * @param AioOsdParams $p
 * @return AioAction
 */
function aio_osd_input_act($control, $p)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(PluginHandleUserInputActionData::operation_timeout => AIO_OSD_TIMEOUT),
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => $control), $p));
}

// The actions of the player: the window closed - green opens it; open - green
// closes it, the timer redraws it.
/**
 * @param AioOsdParams $p
 * @param bool $open
 * @return array<string, AioAction>
 */
function aio_osd_acts($p, $open)
{
    if (!$open)
        return array(GUI_EVENT_KEY_B_GREEN => aio_osd_input_act('osd_open', $p));
    return array(GUI_EVENT_KEY_B_GREEN => aio_osd_input_act('osd_close', $p),
        GUI_EVENT_TIMER => aio_osd_input_act('osd_tick', $p));
}

// A name in one line of the window.
/**
 * @param string $s
 * @return string
 */
function aio_osd_name($s)
{
    return aio_cut(trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $s)), AIO_OSD_NAME_MAX);
}

// Params of the window of vod_play of row $r ($ts: via TorrServer): 'm' the
// mode, ts (TorrServer, by infoHash 'h') or deb (the URL of Debrid: name 'n'
// and size 'z' in bytes of the release); 'l' the language of its words, 'e'
// "1" while an error is logged (only the first one of a row goes to the log).
/**
 * @param AioRow $r
 * @param bool $ts
 * @param string $lang
 * @return AioOsdParams
 */
function aio_osd_params($r, $ts, $lang)
{
    $ts = $ts && preg_match('/^[0-9a-f]{40}$/D', $r['hash']);
    return array('m' => $ts ? 'ts' : 'deb', 'h' => $ts ? $r['hash'] : '',
        'n' => $ts ? '' : aio_osd_name($r['label']),
        'z' => $ts ? '0' : number_format($r['size'], 0, '.', ''), 'l' => $lang === 'ru' ? 'ru' : 'en', 'e' => '0');
}

// Params of the input -> as aio_osd_params made them, or null.
/**
 * @param stdClass $in
 * @return AioOsdParams|null
 */
function aio_osd_params_in($in)
{
    $p = array();
    foreach (array('m' => '/^(ts|deb)$/D', 'h' => '/^([0-9a-f]{40})?$/D', 'n' => '/^[^\x00-\x1F\x7F]*$/D',
        'z' => '/^[0-9]{1,15}$/D', 'l' => '/^(ru|en)$/D', 'e' => '/^[01]$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
            return null;
        $p[$k] = $in->$k;
    }
    if (($p['m'] === 'ts') !== ($p['h'] !== '') || !mb_check_encoding($p['n'], 'UTF-8') ||
        mb_strlen($p['n'], 'UTF-8') > AIO_OSD_NAME_MAX + 3)
        return null;
    return array('m' => $p['m'], 'h' => $p['h'], 'n' => $p['n'], 'z' => $p['z'], 'l' => $p['l'], 'e' => $p['e']);
}

// Lines of the window of TorrServer: the name of the torrent, the speeds, the
// peers and seeders, the loaded size; no reply - one line. $err: why there are
// no numbers ('' none), without the address.
/**
 * @param string $hash
 * @param string $lang
 * @param string $err
 * @param-out string $err
 * @return list<string>
 */
function aio_osd_ts_lines($hash, $lang, &$err)
{
    $rg = aio_ts_call(Aio::$settings['ts'] . '/torrents', (string) json_encode(array('action' => 'get', 'hash' => $hash)), 2,
        Aio::$settings['ts_auth']);
    $j = $rg['err'] === '' && $rg['code'] === 200 ? aio_ts_decode($rg['body']) : null;
    if (!$j)
    {
        // curl_error names the host: its number only.
        $err = 'TorrServer ' . ($rg['err'] !== '' ? "curl {$rg['errno']}" : ($rg['code'] === 200 ? 'bad reply' : "HTTP {$rg['code']}"));
        return array(aio_tr($lang, 'osd_ts_down'));
    }
    $err = '';
    $name = aio_str($j, 'name') !== '' ? aio_str($j, 'name') : aio_str($j, 'title');
    $all = aio_num(aio_str($j, 'torrent_size'));
    return array(
        aio_osd_name($name),
        str_replace(array('{down}', '{up}'), array(aio_speed_str(isset($j['download_speed']) ? $j['download_speed'] : null, $lang),
            aio_speed_str(isset($j['upload_speed']) ? $j['upload_speed'] : null, $lang)), aio_tr($lang, 'osd_ts_speed')),
        str_replace(array('{peers}', '{all}', '{seeds}'), array(strval(aio_ts_int($j, 'active_peers')),
            strval(aio_ts_int($j, 'total_peers')), strval(aio_ts_int($j, 'connected_seeders'))), aio_tr($lang, 'osd_ts_peers')),
        str_replace(array('{got}', '{all}'), array(aio_size_str(aio_num(aio_str($j, 'loaded_size')), $lang),
            $all > 0 ? aio_size_str($all, $lang) : "\xE2\x80\x94"), aio_tr($lang, 'osd_ts_loaded')));
}

// The player's file of its state: "key = value" lines; with an empty
// FS_PREFIX (older models) /tmp/run.
/**
 * @return string
 */
function aio_osd_state_path()
{
    return strval(getenv('FS_PREFIX')) . '/tmp/run/ext_command.state';
}

// Values of keys $keys in ext_command.state (others, playback_url with its
// token too, are never kept), null: no file.
/**
 * @param list<string> $keys
 * @return array<string, string>|null
 */
function aio_osd_state($keys)
{
    $f = aio_osd_state_path();
    // php_server lives on: its stat cache would keep the file of another playback.
    clearstatcache();
    $s = is_file($f) && is_readable($f) ? file_get_contents($f, false, null, 0, 65536) : false;
    if ($s === false)
        return null;
    $out = array();
    foreach (explode("\n", $s) as $line)
    {
        if (preg_match('/^\s*([a-z_]+)\s*=\s*(.*?)\s*$/D', $line, $m) && in_array($m[1], $keys, true))
            $out[$m[1]] = $m[2];
    }
    return $out;
}

// Lines of the window of Debrid: the name and size of the release, the bit
// rate of the player (bit/s: hypothesis), "Buffering..." while it buffers.
// $err: why there is no bit rate ('' none).
/**
 * @param AioOsdParams $p
 * @param string $err
 * @param-out string $err
 * @return list<string>
 */
function aio_osd_deb_lines($p, &$err)
{
    $lang = $p['l'];
    $st = aio_osd_state(array('playback_current_bitrate', 'playback_is_buffering'));
    $bps = is_array($st) && isset($st['playback_current_bitrate']) && preg_match('/^[0-9]{1,12}$/D', $st['playback_current_bitrate']) ?
        floatval($st['playback_current_bitrate']) : 0.0;
    $err = !is_array($st) ? 'ext_command.state: none' : (isset($st['playback_current_bitrate']) ? '' :
        'ext_command.state: no playback_current_bitrate');
    $size = floatval($p['z']);
    $lines = array();
    if ($p['n'] !== '')
        $lines[] = $p['n'];
    $lines[] = str_replace('{size}', $size > 0 ? aio_size_str($size, $lang) : "\xE2\x80\x94", aio_tr($lang, 'osd_deb_size'));
    $lines[] = str_replace('{rate}', $bps > 0 ? aio_gc_rate($bps, false, $lang) : "\xE2\x80\x94", aio_tr($lang, 'osd_deb_rate'));
    if (is_array($st) && isset($st['playback_is_buffering']) && $st['playback_is_buffering'] === '1')
        $lines[] = aio_tr($lang, 'osd_deb_buffering');
    return $lines;
}

// Components of the window: the plate, then the lines.
/**
 * @param list<string> $lines
 * @return list<array<string, int|string|bool>>
 */
function aio_osd_comps($lines)
{
    $c = array(array(
        PluginOsdComponent::image_url => 'plugin_file://%' . AIO_PLUGIN . '%/img/osd_bg.png',
        PluginOsdComponent::x => AIO_OSD_X,
        PluginOsdComponent::y => AIO_OSD_Y,
        PluginOsdComponent::image_width => AIO_OSD_W,
        PluginOsdComponent::image_height => AIO_OSD_H));
    foreach (array_slice($lines, 0, AIO_OSD_LINES) as $i => $l)
        $c[] = array(
            PluginOsdComponent::text => $l,
            PluginOsdComponent::x => AIO_OSD_X + AIO_OSD_PAD,
            PluginOsdComponent::y => AIO_OSD_Y + AIO_OSD_TOP + $i * AIO_OSD_LH,
            PluginOsdComponent::text_font_size => PLUGIN_FONT_NORMAL,
            PluginOsdComponent::text_color => '15',
            PluginOsdComponent::text_halo => false);
    return $c;
}

// update_osd of $comps, then the actions $acts and the timer ($tick ms, 0 none).
/**
 * @param list<array<string, int|string|bool>> $comps
 * @param array<string, AioAction> $acts
 * @param int $tick
 * @return AioAction
 */
function aio_osd_update($comps, $acts, $tick)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_UPDATE_OSD_ACTION_ID,
        GuiAction::data => array(
            PluginUpdateOsdActionData::components => $comps,
            PluginUpdateOsdActionData::post_action => array(
                GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
                GuiAction::data => array(
                    ChangeBehaviourActionData::actions => $acts,
                    ChangeBehaviourActionData::timer => $tick > 0 ? array(GuiTimerDef::delay_ms => $tick) : null,
                    ChangeBehaviourActionData::post_action => null))));
}

// osd_open, osd_tick (the window drawn anew, the next tick armed), osd_close
// (the window gone, green opens it again, no timer). Bad params: no window,
// nothing armed.
/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction
 */
function aio_osd_input($in, $control)
{
    $p = aio_osd_params_in($in);
    if (!$p)
    {
        aio_log("osd: $control with bad params");
        return array(GuiAction::handler_string_id => PLUGIN_UPDATE_OSD_ACTION_ID,
            GuiAction::data => array(PluginUpdateOsdActionData::components => array(),
                PluginUpdateOsdActionData::post_action => null));
    }
    if ($control === 'osd_close')
    {
        aio_log('osd: closed');
        $p['e'] = '0';
        return aio_osd_update(array(), aio_osd_acts($p, false), 0);
    }
    if ($control === 'osd_open')
    {
        aio_log('osd: open, ' . ($p['m'] === 'ts' ? "TorrServer, hash={$p['h']}" : 'Debrid'));
        $p['e'] = '0';
    }
    $err = '';
    $lines = $p['m'] === 'ts' ? aio_osd_ts_lines($p['h'], $p['l'], $err) : aio_osd_deb_lines($p, $err);
    if ($err !== '' && $p['e'] === '0')
        aio_log("osd: $err");
    $p['e'] = $err !== '' ? '1' : '0';
    return aio_osd_update(aio_osd_comps($lines), aio_osd_acts($p, true), AIO_OSD_TICK_MS);
}
