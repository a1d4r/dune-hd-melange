<?php
// TorrServer in the player (0.48.0): over the INFO panel of the player
// (plugin_update_info_block, text_above: seen only while INFO is open) the
// speeds, peers and seeders, loaded of the torrent ("get"), redrawn every
// AIO_TSI_TICK_MS while TorrServer plays, no key. The timer of vod_info starts
// it (aio_tsi_vod), each tick re-arms it with change_behaviour (the timer of
// the player fires once). Its state is in the params of the timer action
// (hash, language, error and first-tick flags), never in Aio: the list may be
// gone under the player.
// Reads Aio::$settings. Functions are global; the require order is in main.php.
// Types in the PHPDoc (AioTsiParams): phpstan-types.neon of the repository.

define('AIO_TSI_TICK_MS', 2000);
// The tick while TorrServer gives no numbers: a "get" of 2 s is not repeated so often.
define('AIO_TSI_TICK_ERR_MS', 5000);
// operation_timeout of the tick, ms: the "get" takes 2 s at most.
define('AIO_TSI_TIMEOUT', 5000);

// The fields of vod_info for row $r ($ts: via TorrServer): the actions and the
// first tick in AIO_TSI_TICK_MS; none (array()) unless TorrServer.
/**
 * @param AioRow $r
 * @param bool $ts
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_tsi_vod($r, $ts, $lang)
{
    $p = aio_tsi_params($r, $ts, $lang);
    if (!$p)
        return array();
    return array(
        PluginVodInfo::actions => aio_tsi_acts($p),
        PluginVodInfo::timer => array(GuiTimerDef::delay_ms => AIO_TSI_TICK_MS));
}

// Params of the ticks of vod_play of row $r ($ts: via TorrServer): 'h' the
// infoHash, 'l' the language of the lines, 'e' "1" while an error is logged
// (only the first one of a row goes to the log), 'f' "1" on the first tick
// (logged). null: none (not TorrServer).
/**
 * @param AioRow $r
 * @param bool $ts
 * @param string $lang
 * @return AioTsiParams|null
 */
function aio_tsi_params($r, $ts, $lang)
{
    if (!$ts || !preg_match('/^[0-9a-f]{40}$/D', $r['hash']))
        return null;
    return array('h' => $r['hash'], 'l' => $lang === 'ru' ? 'ru' : 'en', 'e' => '0', 'f' => '1');
}

// The actions of the player: the timer -> the tick (handle_user_input of our
// plugin, no "Please wait" over the video: no show_dialog_delay).
/**
 * @param AioTsiParams $p
 * @return array<string, AioAction>
 */
function aio_tsi_acts($p)
{
    return array(GUI_EVENT_TIMER => array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(PluginHandleUserInputActionData::operation_timeout => AIO_TSI_TIMEOUT),
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => 'tsi_tick'), $p)));
}

// Params of the input -> as aio_tsi_params made them, or null.
/**
 * @param stdClass $in
 * @return AioTsiParams|null
 */
function aio_tsi_params_in($in)
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
function aio_tsi_count($j, $k)
{
    if (!isset($j[$k]))
        return '0';
    return is_int($j[$k]) && $j[$k] >= 0 ? strval($j[$k]) : "\xE2\x80\x94";
}

// A size of a TorrentStatus (quoted by aio_ts_decode when big): "—" when it
// is none, not a number, negative or too big for aio_num; 0 too unless $zero.
/**
 * @param array<mixed> $j
 * @param string $k
 * @param string $lang
 * @param bool $zero
 * @return string
 */
function aio_tsi_size($j, $k, $lang, $zero)
{
    $v = aio_str($j, $k);
    $f = aio_num($v);
    if ($f > 0)
        return aio_size_str($f, $lang);
    return $zero && is_numeric($v) && (float) $v == 0 ? aio_size_str(0.0, $lang) : "\xE2\x80\x94";
}

// Lines of TorrServer over the INFO panel: the speeds, the peers and seeders,
// the loaded size (the name of the release is sub_name of INFO); no numbers -
// one line why: slow (curl timeout), no answer, no such torrent (404), a reply
// not understood. $err: that for the log ('' none), without the address.
/**
 * @param string $hash
 * @param string $lang
 * @param string $err
 * @param-out string $err
 * @return list<string>
 */
function aio_tsi_lines($hash, $lang, &$err)
{
    list($rg, $j) = aio_ts_get($hash);
    if (!$j)
    {
        // curl_error names the host: its number only.
        $err = 'TorrServer ' . ($rg['err'] !== '' ? "curl {$rg['errno']}" : ($rg['code'] === 200 ? 'bad reply' : "HTTP {$rg['code']}"));
        if ($rg['err'] !== '')
            $key = $rg['errno'] === 28 ? 'tsi_slow' : 'tsi_down';
        else
            $key = $rg['code'] === 404 ? 'tsi_gone' : 'tsi_bad';
        return array(aio_tr($lang, $key));
    }
    $err = '';
    return array(
        str_replace(array('{down}', '{up}'), array(aio_speed_str(isset($j['download_speed']) ? $j['download_speed'] : null, $lang),
            aio_speed_str(isset($j['upload_speed']) ? $j['upload_speed'] : null, $lang)), aio_tr($lang, 'tsi_speed')),
        str_replace(array('{peers}', '{all}', '{seeds}'), array(aio_tsi_count($j, 'active_peers'),
            aio_tsi_count($j, 'total_peers'), aio_tsi_count($j, 'connected_seeders')), aio_tr($lang, 'tsi_peers')),
        str_replace(array('{got}', '{all}'), array(aio_tsi_size($j, 'loaded_size', $lang, true),
            aio_tsi_size($j, 'torrent_size', $lang, false)), aio_tr($lang, 'tsi_loaded')));
}

// The text over the INFO panel ('' none), then the actions $acts and the
// timer ($tick ms) armed again; no $acts: nothing armed.
/**
 * @param string $text
 * @param array<string, AioAction>|null $acts
 * @param int $tick
 * @return AioAction
 */
function aio_tsi_info_block($text, $acts, $tick)
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

// tsi_tick: the lines of TorrServer over INFO, the next tick armed (in
// AIO_TSI_TICK_ERR_MS after an error). Bad params: the text gone, nothing armed.
/**
 * @param stdClass $in
 * @return AioAction
 */
function aio_tsi_input($in)
{
    $p = aio_tsi_params_in($in);
    if (!$p)
    {
        aio_log('tsi: tick with bad params');
        return aio_tsi_info_block('', null, 0);
    }
    if ($p['f'] === '1')
        aio_log("tsi: first tick, TorrServer, hash={$p['h']}");
    $p['f'] = '0';
    $err = '';
    $lines = aio_tsi_lines($p['h'], $p['l'], $err);
    if ($err !== '' && $p['e'] === '0')
        aio_log("tsi: $err");
    $p['e'] = $err !== '' ? '1' : '0';
    return aio_tsi_info_block(implode("\n", $lines), aio_tsi_acts($p), $err !== '' ? AIO_TSI_TICK_ERR_MS : AIO_TSI_TICK_MS);
}
