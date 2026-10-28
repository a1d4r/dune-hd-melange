<?php
// TorrServer in the player (0.48.0): over the INFO panel of the player
// (plugin_update_info_block, text_above: seen only while INFO is open) the
// speeds, peers and seeders, loaded of the torrent ("get"), redrawn every
// AIO_OSD_TICK_MS while TorrServer plays, no key. The timer of vod_info starts
// it, each tick re-arms it with change_behaviour (the timer of the player fires
// once). Its state is in the params of the timer action (hash, language, error
// and first-tick flags), never in Aio: the list may be gone under the player.
// Reads Aio::$settings. Functions are global; the require order is in main.php.
// Types in the PHPDoc (AioOsdParams): phpstan-types.neon of the repository.

define('AIO_OSD_TICK_MS', 2000);
// The tick while TorrServer gives no numbers: a "get" of 2 s is not repeated so often.
define('AIO_OSD_TICK_ERR_MS', 5000);
// operation_timeout of the tick, ms: the "get" takes 2 s at most.
define('AIO_OSD_TIMEOUT', 5000);

// Params of the ticks of vod_play of row $r ($ts: via TorrServer): 'h' the
// infoHash, 'l' the language of the lines, 'e' "1" while an error is logged
// (only the first one of a row goes to the log), 'f' "1" on the first tick
// (logged). null: none (not TorrServer).
/**
 * @param AioRow $r
 * @param bool $ts
 * @param string $lang
 * @return AioOsdParams|null
 */
function aio_osd_params($r, $ts, $lang)
{
    if (!$ts || !preg_match('/^[0-9a-f]{40}$/D', $r['hash']))
        return null;
    return array('h' => $r['hash'], 'l' => $lang === 'ru' ? 'ru' : 'en', 'e' => '0', 'f' => '1');
}

// The actions of the player: the timer -> the tick (handle_user_input of our
// plugin, no "Please wait" over the video: no show_dialog_delay).
/**
 * @param AioOsdParams $p
 * @return array<string, AioAction>
 */
function aio_osd_acts($p)
{
    return array(GUI_EVENT_TIMER => array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(PluginHandleUserInputActionData::operation_timeout => AIO_OSD_TIMEOUT),
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => 'osd_tick'), $p)));
}

// Params of the input -> as aio_osd_params made them, or null.
/**
 * @param stdClass $in
 * @return AioOsdParams|null
 */
function aio_osd_params_in($in)
{
    $p = array();
    foreach (array('h' => '/^[0-9a-f]{40}$/D', 'l' => '/^(ru|en)$/D', 'e' => '/^[01]$/D', 'f' => '/^[01]$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
            return null;
        $p[$k] = $in->$k;
    }
    return array('h' => $p['h'], 'l' => $p['l'], 'e' => $p['e'], 'f' => $p['f']);
}

// A count of a TorrentStatus: none -> 0, negative or not an int -> "—".
/**
 * @param array<mixed> $j
 * @param string $k
 * @return string
 */
function aio_osd_count($j, $k)
{
    if (!isset($j[$k]))
        return '0';
    return is_int($j[$k]) && $j[$k] >= 0 ? strval($j[$k]) : "\xE2\x80\x94";
}

// A size of a TorrentStatus (quoted by aio_ts_decode when big): "—" when it
// is none, not a number or negative; 0 too unless $zero.
/**
 * @param array<mixed> $j
 * @param string $k
 * @param string $lang
 * @param bool $zero
 * @return string
 */
function aio_osd_size($j, $k, $lang, $zero)
{
    $v = aio_str($j, $k);
    if (!is_numeric($v) || (float) $v < 0 || ((float) $v == 0 && !$zero))
        return "\xE2\x80\x94";
    return aio_size_str(aio_num($v), $lang);
}

// Lines of TorrServer over the INFO panel: the speeds, the peers and seeders,
// the loaded size (the name of the release is sub_name of INFO); no numbers -
// one line why: no answer, no such torrent (404), a reply not understood.
// $err: that for the log ('' none), without the address.
/**
 * @param string $hash
 * @param string $lang
 * @param string $err
 * @param-out string $err
 * @return list<string>
 */
function aio_osd_ts_lines($hash, $lang, &$err)
{
    list($rg, $j) = aio_ts_get($hash);
    if (!$j)
    {
        // curl_error names the host: its number only.
        $err = 'TorrServer ' . ($rg['err'] !== '' ? "curl {$rg['errno']}" : ($rg['code'] === 200 ? 'bad reply' : "HTTP {$rg['code']}"));
        return array(aio_tr($lang, $rg['err'] !== '' ? 'osd_ts_down' : ($rg['code'] === 404 ? 'osd_ts_gone' : 'osd_ts_bad')));
    }
    $err = '';
    return array(
        str_replace(array('{down}', '{up}'), array(aio_speed_str(isset($j['download_speed']) ? $j['download_speed'] : null, $lang),
            aio_speed_str(isset($j['upload_speed']) ? $j['upload_speed'] : null, $lang)), aio_tr($lang, 'osd_ts_speed')),
        str_replace(array('{peers}', '{all}', '{seeds}'), array(aio_osd_count($j, 'active_peers'),
            aio_osd_count($j, 'total_peers'), aio_osd_count($j, 'connected_seeders')), aio_tr($lang, 'osd_ts_peers')),
        str_replace(array('{got}', '{all}'), array(aio_osd_size($j, 'loaded_size', $lang, true),
            aio_osd_size($j, 'torrent_size', $lang, false)), aio_tr($lang, 'osd_ts_loaded')));
}

// The text over the INFO panel ('' none), then the actions $acts and the
// timer ($tick ms) armed again; no $acts: nothing armed.
/**
 * @param string $text
 * @param array<string, AioAction>|null $acts
 * @param int $tick
 * @return AioAction
 */
function aio_osd_update($text, $acts, $tick)
{
    // Grey, no halo, no offset: as the text of the panel itself (seen on r25).
    $d = array(
        PluginUpdateInfoBlockActionData::text_above => $text,
        PluginUpdateInfoBlockActionData::text_color => '15',
        PluginUpdateInfoBlockActionData::text_halo => false,
        PluginUpdateInfoBlockActionData::text_y_offset => 0);
    if (!is_null($acts))
        $d[PluginUpdateInfoBlockActionData::post_action] = array(
            GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
            GuiAction::data => array(
                ChangeBehaviourActionData::actions => $acts,
                ChangeBehaviourActionData::timer => array(GuiTimerDef::delay_ms => $tick),
                ChangeBehaviourActionData::post_action => null));
    return array(GuiAction::handler_string_id => PLUGIN_UPDATE_INFO_BLOCK_ACTION_ID, GuiAction::data => $d);
}

// osd_tick: the lines of TorrServer over INFO, the next tick armed (in
// AIO_OSD_TICK_ERR_MS after an error). Bad params: the text gone, nothing armed.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_osd_input($in)
{
    $p = aio_osd_params_in($in);
    if (!$p)
    {
        aio_log('osd: tick with bad params');
        return aio_osd_update('', null, 0);
    }
    if ($p['f'] === '1')
        aio_log("osd: first tick, TorrServer, hash={$p['h']}");
    $p['f'] = '0';
    $err = '';
    $lines = aio_osd_ts_lines($p['h'], $p['l'], $err);
    if ($err !== '' && $p['e'] === '0')
        aio_log("osd: $err");
    $p['e'] = $err !== '' ? '1' : '0';
    return aio_osd_update(implode("\n", $lines), aio_osd_acts($p), $err !== '' ? AIO_OSD_TICK_ERR_MS : AIO_OSD_TICK_MS);
}
