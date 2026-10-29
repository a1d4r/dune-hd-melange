<?php
// TorrServer in the player (0.48.0): the green key opens a window at the top
// right, in the style of the INFO window of the player, with the numbers of
// the torrent ("get": the name, speeds, peers and seeders, loaded), redrawn
// every AIO_TSI_TICK_MS while it is open; green again closes it. update_osd
// draws it, change_behaviour arms the next tick (the timer of the player fires
// once). aio_tsi_vod gives vod_info its green. Its state is in the params of
// the actions (hash, the release, language, error flag), never in Aio: the
// list may be gone under the player.
// Reads Aio::$settings; text widths: aio_gc_* (gcomps.php). Functions are
// global; the require order is in main.php.
// Types in the PHPDoc (AioTsiParams): phpstan-types.neon of the repository.

define('AIO_TSI_TICK_MS', 1000);
// The tick while TorrServer gives no numbers: a "get" of 2 s is not repeated every second.
define('AIO_TSI_TICK_ERR_MS', 5000);
// operation_timeout of the actions, ms: the "get" takes 2 s at most.
define('AIO_TSI_TIMEOUT', 5000);
// The window on 1920x1080, its frame without the shadow: x 1150..1880, from
// y 80 as the INFO window of the player (its border is seen from x 1145, as
// that of the INFO window from 145). Its plate img/ts_info_bg.png is drawn at
// its own size, AIO_TSI_BG_* (the frame plus the shadow, the extents of the
// skin's osd_glass: 13 left, 9 top, 32 right, 29 bottom).
define('AIO_TSI_X', 1150);
define('AIO_TSI_Y', 80);
define('AIO_TSI_W', 730);
define('AIO_TSI_BG_X', 1137);
define('AIO_TSI_BG_Y', 71);
define('AIO_TSI_BG_W', 775);
define('AIO_TSI_BG_H', 233);
// Rows "Label: value" of the SMALL font in the grey of the INFO window, as
// there (r25 screenshots 29.10.2026): the first at AIO_TSI_TOP, AIO_TSI_LH
// apart, from AIO_TSI_PAD of the frame, as wide as it minus AIO_TSI_PAD on
// both sides. AIO_TSI_PX: SMALL in px for aio_gc_w (the widths of the
// screenshots: 29, not 30).
define('AIO_TSI_ROWS', 5);
define('AIO_TSI_TOP', 11);
define('AIO_TSI_LH', 34);
define('AIO_TSI_PAD', 20);
define('AIO_TSI_PX', 29);
define('AIO_TSI_COLOR', '15');

// The fields of vod_info for row $r ($ts: via TorrServer): green opens the
// window; none (array()) unless TorrServer. No timer until it opens.
/**
 * @param AioRow $r
 * @param bool $ts
 * @param string $lang
 * @return array<string, mixed>
 */
function aio_tsi_vod($r, $ts, $lang)
{
    $p = aio_tsi_params($r, $ts, $lang);
    return $p ? array(PluginVodInfo::actions => aio_tsi_acts($p, false)) : array();
}

// handle_user_input of our plugin from the player: no "Please wait" over the
// video (no show_dialog_delay).
/**
 * @param string $control
 * @param AioTsiParams $p
 * @return AioAction
 */
function aio_tsi_input_act($control, $p)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(PluginHandleUserInputActionData::operation_timeout => AIO_TSI_TIMEOUT),
        // No empty values: the shell may drop them (aio_tsi_params_in reads them as empty).
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => $control), array_diff($p, array(''))));
}

// The actions of the player: the window closed - green opens it; open - green
// closes it, the timer redraws it.
/**
 * @param AioTsiParams $p
 * @param bool $open
 * @return array<string, AioAction>
 */
function aio_tsi_acts($p, $open)
{
    if (!$open)
        return array(GUI_EVENT_KEY_B_GREEN => aio_tsi_input_act('tsi_open', $p));
    return array(GUI_EVENT_KEY_B_GREEN => aio_tsi_input_act('tsi_close', $p),
        GUI_EVENT_TIMER => aio_tsi_input_act('tsi_tick', $p));
}

// Width of a row of the window, px.
/**
 * @return int
 */
function aio_tsi_text_w()
{
    return AIO_TSI_W - 2 * AIO_TSI_PAD;
}

// A name in one line of the window: no control characters or line breaks
// (U+0085, U+2028, U+2029 too), cut with "…" to the row after the label
// "Release: " of $lang. A name it gave comes back as it is.
/**
 * @param string $s
 * @param string $lang
 * @return string
 */
function aio_tsi_name($s, $lang)
{
    return aio_gc_cut_w(trim(strval(preg_replace('/[\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]+/u', ' ', aio_utf8($s)))),
        AIO_TSI_PX, aio_tsi_text_w() - aio_gc_w(aio_tr($lang, 'tsi_l_name') . ': ', AIO_TSI_PX));
}

// Params of the window of vod_play of row $r ($ts: via TorrServer): 'h' the
// infoHash, 'n' the name of the release (when TorrServer names none), 'l' the
// language of its words, 'e' "1" while an error is logged (only the first one
// of a row goes to the log). null: no window (not TorrServer).
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
    $l = $lang === 'ru' ? 'ru' : 'en';
    return array('h' => $r['hash'], 'n' => aio_tsi_name($r['label'], $l), 'l' => $l, 'e' => '0');
}

// Params of the input -> as aio_tsi_params made them, or null.
/**
 * @param stdClass $in
 * @return AioTsiParams|null
 */
function aio_tsi_params_in($in)
{
    $p = array();
    // n may be absent (empty values are not sent): empty.
    foreach (array('h' => '/^[0-9a-f]{40}$/D', 'n' => '/^.*$/sD', 'l' => '/^(ru|en)$/D', 'e' => '/^[01]$/D') as $k => $re)
    {
        $v = !isset($in->$k) && $k === 'n' ? '' : (isset($in->$k) ? $in->$k : null);
        if (!is_string($v) || !preg_match($re, $v))
            return null;
        $p[$k] = $v;
    }
    // A name as aio_tsi_name leaves it: UTF-8, one line, cut to the row.
    if (!mb_check_encoding($p['n'], 'UTF-8') || aio_tsi_name($p['n'], $p['l']) !== $p['n'])
        return null;
    return array('h' => $p['h'], 'n' => $p['n'], 'l' => $p['l'], 'e' => $p['e']);
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

// Rows "Label: value" of the window of TorrServer: the name of the torrent
// (none: of the release, $name), download, upload, peers and seeders, loaded
// of the size. No numbers - one row why: slow (curl timeout), no answer, no
// such torrent (404), a reply not understood. $err: that for the log ('' none),
// without the address.
/**
 * @param string $hash
 * @param string $name
 * @param string $lang
 * @param string $err
 * @param-out string $err
 * @return list<string>
 */
function aio_tsi_rows($hash, $name, $lang, &$err)
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
    $tn = aio_str($j, 'name') !== '' ? aio_str($j, 'name') : aio_str($j, 'title');
    $l = array();
    foreach (array('name', 'down', 'up', 'peers', 'seeds', 'loaded') as $k)
        $l[$k] = aio_tr($lang, "tsi_l_$k") . ': ';
    // Speeds: 0 is "0 KB/s" here (TorrServer gives 0 while the buffer ahead is full).
    return array(
        $l['name'] . ($tn !== '' ? aio_tsi_name($tn, $lang) : $name),
        $l['down'] . aio_speed_str(isset($j['download_speed']) ? $j['download_speed'] : null, $lang, true),
        $l['up'] . aio_speed_str(isset($j['upload_speed']) ? $j['upload_speed'] : null, $lang, true),
        $l['peers'] . aio_tsi_count($j, 'active_peers') . ' / ' . aio_tsi_count($j, 'total_peers') . " \xC2\xB7 " .
            $l['seeds'] . aio_tsi_count($j, 'connected_seeders'),
        $l['loaded'] . str_replace(array('{got}', '{all}'),
            array(aio_tsi_size($j, 'loaded_size', $lang, true), aio_tsi_size($j, 'torrent_size', $lang, false)),
            aio_tr($lang, 'tsi_loaded')));
}

// The plate of the window: a path of the plugin, as other plugins draw in the
// player (plugin_file:// there is not proven).
/**
 * @return string
 */
function aio_tsi_bg()
{
    $dir = isset(DuneSystem::$properties['install_dir_path']) ? strval(DuneSystem::$properties['install_dir_path']) :
        dirname(__FILE__);
    return rtrim($dir, '/') . '/img/ts_info_bg.png';
}

// Components of the window: the plate, then a text per row, each cut to the row.
/**
 * @param list<string> $rows
 * @return list<array<string, int|string|bool>>
 */
function aio_tsi_comps($rows)
{
    $c = array(array(
        PluginOsdComponent::image_url => aio_tsi_bg(),
        PluginOsdComponent::x => AIO_TSI_BG_X,
        PluginOsdComponent::y => AIO_TSI_BG_Y,
        PluginOsdComponent::image_width => AIO_TSI_BG_W,
        PluginOsdComponent::image_height => AIO_TSI_BG_H));
    foreach (array_slice($rows, 0, AIO_TSI_ROWS) as $i => $row)
        $c[] = array(
            PluginOsdComponent::text => aio_gc_cut_w($row, AIO_TSI_PX, aio_tsi_text_w()),
            PluginOsdComponent::x => AIO_TSI_X + AIO_TSI_PAD,
            PluginOsdComponent::y => AIO_TSI_Y + AIO_TSI_TOP + $i * AIO_TSI_LH,
            PluginOsdComponent::text_font_size => PLUGIN_FONT_SMALL,
            PluginOsdComponent::text_color => AIO_TSI_COLOR,
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
function aio_tsi_update($comps, $acts, $tick)
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

// tsi_open, tsi_tick (the window drawn anew, the next tick armed: in
// AIO_TSI_TICK_ERR_MS after an error), tsi_close (the window gone, green
// opens it again, no timer). Bad params: no window, nothing armed.
/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction
 */
function aio_tsi_input($in, $control)
{
    $p = aio_tsi_params_in($in);
    if (!$p)
    {
        aio_log("tsi: $control with bad params");
        return array(GuiAction::handler_string_id => PLUGIN_UPDATE_OSD_ACTION_ID,
            GuiAction::data => array(PluginUpdateOsdActionData::components => array(),
                PluginUpdateOsdActionData::post_action => null));
    }
    if ($control === 'tsi_close')
    {
        aio_log('tsi: closed');
        $p['e'] = '0';
        return aio_tsi_update(array(), aio_tsi_acts($p, false), 0);
    }
    if ($control === 'tsi_open')
    {
        aio_log("tsi: open, TorrServer, hash={$p['h']}");
        $p['e'] = '0';
    }
    $err = '';
    $rows = aio_tsi_rows($p['h'], $p['n'], $p['l'], $err);
    if ($err !== '' && $p['e'] === '0')
        aio_log("tsi: $err");
    $p['e'] = $err !== '' ? '1' : '0';
    return aio_tsi_update(aio_tsi_comps($rows), aio_tsi_acts($p, true), $err !== '' ? AIO_TSI_TICK_ERR_MS : AIO_TSI_TICK_MS);
}
