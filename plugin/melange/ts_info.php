<?php
// TorrServer in the player (0.48.0): INFO opens, next to the INFO window of
// the player and in its style, a window at the top right with the numbers of
// the torrent ("get": the name, speeds, peers and seeders, loaded), redrawn
// every AIO_TSI_TICK_MS while it is open. The key goes on to the player
// (run_default), so its INFO opens and closes as ever; the keys that close
// that INFO (aio_tsi_close_keys) close ours too, and so does a start of the
// playback anew (aio_tsi_end_state). update_osd draws it, change_behaviour
// arms the next tick (the timer of the player fires once).
// The map of the player (actions and timer of vod_info) belongs to this file
// whole: aio_tsi_vod gives vod_info its INFO, and each change_behaviour here
// replaces the whole map - an action of the player put anywhere else is lost
// at the first INFO. Its state is in the params of the actions (hash, the
// release, language, error flag; the key of a close), never in Aio: the list
// may be gone under the player.
// Reads Aio::$settings; text widths: aio_gc_* (gcomps.php). Functions are
// global; the require order is in main.php.
// Types in the PHPDoc (AioTsiParams): phpstan-types.neon of the repository.

define('AIO_TSI_TICK_MS', 1000);
// The first tick after INFO: the window opens at once, its numbers come with it.
define('AIO_TSI_TICK_FIRST_MS', 200);
// The tick while TorrServer gives no numbers: a "get" of 2 s is not repeated every second.
define('AIO_TSI_TICK_ERR_MS', 5000);
// operation_timeout of the actions, ms: the "get" takes 2 s at most.
define('AIO_TSI_TIMEOUT', 5000);
// The window on 1920x1080, its frame without the shadow: x 1300..1880, from
// y 80 as the INFO window of the player (its border is seen from x 1295, as
// that of the INFO window from 145). That window grows to the right with its
// longest row (to x 1235 with a long audio track, r25 29.10.2026) and is drawn
// over ours: ours is narrow, the name of the release wraps instead. Its plate
// img/ts_info_bg_<rows>.png is drawn at its own size, AIO_TSI_BG_* (the frame
// plus the shadow, the extents of the skin's osd_glass: 13 left, 9 top, 32
// right, 29 bottom), one per count of rows (aio_tsi_bg_h): image_height is not
// proven to stretch a PNG. The PNGs: plugins/melange_img.sh of the development
// repository, its numbers repeat these.
define('AIO_TSI_X', 1300);
define('AIO_TSI_Y', 80);
define('AIO_TSI_W', 580);
define('AIO_TSI_BG_X', 1287);
define('AIO_TSI_BG_Y', 71);
define('AIO_TSI_BG_W', 625);
// The height of the plate of AIO_TSI_ROWS rows; each row more, AIO_TSI_LH more.
define('AIO_TSI_BG_H', 233);
// Rows "Label: value" of the SMALL font in the grey of the INFO window, as
// there (r25 screenshots 29.10.2026): the first at AIO_TSI_TOP, AIO_TSI_LH
// apart, from AIO_TSI_PAD of the frame, as wide as it minus AIO_TSI_PAD on
// both sides, AIO_TSI_BOTTOM below the last. AIO_TSI_ROWS: the rows with the
// name in one (an error: one row on that plate); the name takes
// AIO_TSI_NAME_LINES at most, AIO_TSI_ROWS_MAX in all. AIO_TSI_PX: SMALL in px
// for aio_gc_w (the widths of the screenshots: 29, not 30).
define('AIO_TSI_ROWS', 5);
define('AIO_TSI_NAME_LINES', 3);
define('AIO_TSI_ROWS_MAX', AIO_TSI_ROWS - 1 + AIO_TSI_NAME_LINES);
define('AIO_TSI_TOP', 11);
define('AIO_TSI_BOTTOM', 14);
define('AIO_TSI_LH', 34);
define('AIO_TSI_PAD', 20);
define('AIO_TSI_PX', 29);
define('AIO_TSI_COLOR', '15');
// Characters of a name in the params at most: more than its rows hold.
define('AIO_TSI_NAME_MAX', 300);

// The fields of vod_info for row $r ($ts: via TorrServer): INFO opens the
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
// video (no show_dialog_delay). $key: 'k' of a close ('' none).
/**
 * @param string $control
 * @param AioTsiParams $p
 * @param string $key
 * @return AioAction
 */
function aio_tsi_input_act($control, $p, $key = '')
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(PluginHandleUserInputActionData::operation_timeout => AIO_TSI_TIMEOUT),
        // No empty values: the shell may drop them (aio_tsi_params_in reads them as empty).
        GuiAction::params => array_merge(array('handler_id' => 'aio', 'control_id' => $control), array_diff($p, array('')),
            $key !== '' ? array('k' => $key) : array()));
}

// The keys that close the INFO window of the player while it is open (r25,
// osd_probe 0.3.0; INFO first), those this firmware defines. The plugin does
// not see whether that window is open: these close ours while it is.
/**
 * @return list<string>
 */
function aio_tsi_close_keys()
{
    $keys = array();
    foreach (array('INFO', 'ENTER', 'LEFT', 'RIGHT', 'UP', 'DOWN', 'RETURN', 'POPUP_MENU', 'SUBTITLE', 'AUDIO', 'FWD',
        'REW', 'STOP') as $n)
    {
        if (defined("GUI_EVENT_KEY_$n"))
            $keys[] = strval(constant("GUI_EVENT_KEY_$n"));
    }
    return $keys;
}

// The actions of the player: the window closed - INFO opens it; open - each
// of aio_tsi_close_keys closes it (then goes to the player), the timer
// redraws it, a change of the playback state may close it.
/**
 * @param AioTsiParams $p
 * @param bool $open
 * @return array<string, AioAction>
 */
function aio_tsi_acts($p, $open)
{
    if (!$open)
        return array(GUI_EVENT_KEY_INFO => aio_tsi_input_act('tsi_open', $p));
    $a = array();
    foreach (aio_tsi_close_keys() as $key)
        $a[$key] = aio_tsi_input_act('tsi_close', $p, $key);
    $a[GUI_EVENT_TIMER] = aio_tsi_input_act('tsi_tick', $p);
    $a[GUI_EVENT_PLAYBACK_STATE_CHANGED] = aio_tsi_input_act('tsi_state', $p);
    return $a;
}

// The key for the player when closing key $key closes our window: RETURN as
// INFO. With the INFO window of the player open both close it; were the two
// windows out of step (that one gone without us), RETURN would stop the
// playback, INFO only opens that window.
/**
 * @param string $key
 * @return string
 */
function aio_tsi_default_key($key)
{
    return $key === GUI_EVENT_KEY_RETURN ? GUI_EVENT_KEY_INFO : $key;
}

// A state among the events of a playback_state_changed that closes the INFO
// window of the player ('' none): initializing (the start at the position of
// "Continue" after the buffering, r25 29.10.2026), deinitializing, stopped. Any
// event of them, not only the last: that window opens again only through us.
// A plain seek (seeking -> playing) keeps it (osd_probe 0.3.0).
/**
 * @param stdClass $in
 * @return string
 */
function aio_tsi_end_state($in)
{
    $j = isset($in->plugin_playback_events) && is_string($in->plugin_playback_events) ?
        json_decode($in->plugin_playback_events, true) : null;
    $evs = is_array($j) && isset($j['events']) && is_array($j['events']) ? $j['events'] : array();
    foreach ($evs as $e)
    {
        if (is_array($e) && isset($e['state']) && in_array($e['state'], array('initializing', 'deinitializing', 'stopped'), true))
            return $e['state'];
    }
    return '';
}

// Width of a row of the window, px.
/**
 * @return int
 */
function aio_tsi_text_w()
{
    return AIO_TSI_W - 2 * AIO_TSI_PAD;
}

// "Label: name" of the release in rows of the window (aio_gc_wrap: broken
// after spaces and . _ - / , ) ], a long word by characters); more than
// AIO_TSI_NAME_LINES: the last ends with "…", no separator before it
// ("HDR10+…", not "HDR10+.…").
/**
 * @param string $name
 * @param string $lang
 * @return list<string>
 */
function aio_tsi_name_rows($name, $lang)
{
    $rows = aio_gc_wrap(aio_tr($lang, 'tsi_l_name') . ': ' . $name, AIO_TSI_PX, aio_tsi_text_w(), AIO_TSI_NAME_LINES);
    $last = count($rows) - 1;
    if ($last === AIO_TSI_NAME_LINES - 1)
        $rows[$last] = strval(preg_replace('#[ ._/,-]+\x{2026}$#u', "\xE2\x80\xA6", $rows[$last]));
    return $rows;
}

// A name for the params of the window: one line (no control characters or
// line breaks, U+0085, U+2028, U+2029 too), AIO_TSI_NAME_MAX characters at
// most; aio_tsi_name_rows cuts it to its rows.
/**
 * @param string $s
 * @return string
 */
function aio_tsi_name($s)
{
    return rtrim(mb_substr(aio_one_line($s), 0, AIO_TSI_NAME_MAX, 'UTF-8'));
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
    return array('h' => $r['hash'], 'n' => aio_tsi_name($r['label']), 'l' => $l, 'e' => '0');
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
    // A name as aio_tsi_name leaves it: UTF-8, one line, AIO_TSI_NAME_MAX characters at most.
    if (!mb_check_encoding($p['n'], 'UTF-8') || aio_one_line($p['n']) !== $p['n'] ||
        mb_strlen($p['n'], 'UTF-8') > AIO_TSI_NAME_MAX)
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

// Rows "Label: value" of the window: the name (aio_tsi_name_rows), then the
// values $v - download, upload, peers of all and seeders, loaded of the size.
/**
 * @param string $lang
 * @param string $name
 * @param array{down: string, up: string, peers: string, all: string, seeds: string, got: string, size: string} $v
 * @return list<string>
 */
function aio_tsi_lines($lang, $name, $v)
{
    $l = array();
    foreach (array('down', 'up', 'peers', 'seeds', 'loaded') as $k)
        $l[$k] = aio_tr($lang, "tsi_l_$k") . ': ';
    return array_merge(aio_tsi_name_rows($name, $lang), array(
        $l['down'] . $v['down'],
        $l['up'] . $v['up'],
        $l['peers'] . $v['peers'] . ' / ' . $v['all'] . " \xC2\xB7 " . $l['seeds'] . $v['seeds'],
        $l['loaded'] . str_replace(array('{got}', '{all}'), array($v['got'], $v['size']), aio_tr($lang, 'tsi_loaded'))));
}

// The rows of the window before its first numbers: the name of the release, "—".
/**
 * @param string $name
 * @param string $lang
 * @return list<string>
 */
function aio_tsi_rows_blank($name, $lang)
{
    $d = "\xE2\x80\x94";
    return aio_tsi_lines($lang, $name, array('down' => $d, 'up' => $d, 'peers' => $d, 'all' => $d, 'seeds' => $d,
        'got' => $d, 'size' => $d));
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
    // Speeds: 0 is "0 KB/s" here (TorrServer gives 0 while the buffer ahead is full).
    return aio_tsi_lines($lang, $tn !== '' ? aio_tsi_name($tn) : $name, array(
        'down' => aio_speed_str(isset($j['download_speed']) ? $j['download_speed'] : null, $lang, true),
        'up' => aio_speed_str(isset($j['upload_speed']) ? $j['upload_speed'] : null, $lang, true),
        'peers' => aio_tsi_count($j, 'active_peers'),
        'all' => aio_tsi_count($j, 'total_peers'),
        'seeds' => aio_tsi_count($j, 'connected_seeders'),
        'got' => aio_tsi_size($j, 'loaded_size', $lang, true),
        'size' => aio_tsi_size($j, 'torrent_size', $lang, false)));
}

// The height of the plate of $n rows (AIO_TSI_ROWS at least), px.
/**
 * @param int $n
 * @return int
 */
function aio_tsi_bg_h($n)
{
    return AIO_TSI_BG_H + (max($n, AIO_TSI_ROWS) - AIO_TSI_ROWS) * AIO_TSI_LH;
}

// The plate of the window of $n rows (AIO_TSI_ROWS at least): a path of the
// plugin, as other plugins draw in the player (plugin_file:// there is not proven).
/**
 * @param int $n
 * @return string
 */
function aio_tsi_bg($n)
{
    $dir = isset(DuneSystem::$properties['install_dir_path']) ? strval(DuneSystem::$properties['install_dir_path']) :
        dirname(__FILE__);
    return rtrim($dir, '/') . '/img/ts_info_bg_' . max($n, AIO_TSI_ROWS) . '.png';
}

// Components of the window: the plate as high as the rows, then a text per
// row, each cut to the row.
/**
 * @param list<string> $rows
 * @return list<array<string, int|string|bool>>
 */
function aio_tsi_comps($rows)
{
    $rows = array_slice($rows, 0, AIO_TSI_ROWS_MAX);
    $c = array(array(
        PluginOsdComponent::image_url => aio_tsi_bg(count($rows)),
        PluginOsdComponent::x => AIO_TSI_BG_X,
        PluginOsdComponent::y => AIO_TSI_BG_Y,
        PluginOsdComponent::image_width => AIO_TSI_BG_W,
        PluginOsdComponent::image_height => aio_tsi_bg_h(count($rows))));
    foreach ($rows as $i => $row)
        $c[] = array(
            PluginOsdComponent::text => aio_gc_cut_w($row, AIO_TSI_PX, aio_tsi_text_w()),
            PluginOsdComponent::x => AIO_TSI_X + AIO_TSI_PAD,
            PluginOsdComponent::y => AIO_TSI_Y + AIO_TSI_TOP + $i * AIO_TSI_LH,
            PluginOsdComponent::text_font_size => PLUGIN_FONT_SMALL,
            PluginOsdComponent::text_color => AIO_TSI_COLOR,
            PluginOsdComponent::text_halo => false);
    return $c;
}

// update_osd of $comps, then $post.
/**
 * @param list<array<string, int|string|bool>> $comps
 * @param AioAction|null $post
 * @return AioAction
 */
function aio_tsi_osd($comps, $post)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_UPDATE_OSD_ACTION_ID,
        GuiAction::data => array(
            PluginUpdateOsdActionData::components => $comps,
            PluginUpdateOsdActionData::post_action => $post));
}

// The actions $acts and the timer ($tick ms, 0 none).
/**
 * @param array<string, AioAction>|stdClass $acts
 * @param int $tick
 * @return AioAction
 */
function aio_tsi_behave($acts, $tick)
{
    return array(
        GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID,
        GuiAction::data => array(
            ChangeBehaviourActionData::actions => $acts,
            ChangeBehaviourActionData::timer => $tick > 0 ? array(GuiTimerDef::delay_ms => $tick) : null,
            ChangeBehaviourActionData::post_action => null));
}

// What the player does with key $key itself (its INFO window), then $post.
/**
 * @param string $key
 * @param AioAction|null $post
 * @return AioAction
 */
function aio_tsi_default($key, $post)
{
    return array(
        GuiAction::handler_string_id => RUN_DEFAULT_ACTION_ID,
        GuiAction::data => array(
            RunDefaultActionData::event => $key,
            RunDefaultActionData::post_action => $post));
}

// tsi_open (INFO to the player, then the window with the name of the release,
// its numbers with the first tick in AIO_TSI_TICK_FIRST_MS: no "get" before
// INFO), tsi_tick (the window drawn anew, the next tick armed: in
// AIO_TSI_TICK_ERR_MS after an error), tsi_close (the window gone, the key of
// 'k' to the player, INFO opens it again, no timer; as in osd_probe 0.2.1 on
// r25), tsi_state (the playback started anew: the window gone, nothing to the
// player; any other state - nothing). Bad params: no window, the key still to
// the player if known, the map dropped (the player is its own till the end).
/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction|null
 */
function aio_tsi_input($in, $control)
{
    $keys = aio_tsi_close_keys();
    // The key from the list, not from the params.
    $n = $control !== 'tsi_close' ? false : array_search(isset($in->k) && is_string($in->k) ? $in->k : '', $keys, true);
    $ck = $n !== false ? $keys[$n] : '';
    $key = $control === 'tsi_open' ? GUI_EVENT_KEY_INFO : ($ck !== '' ? aio_tsi_default_key($ck) : '');
    $p = aio_tsi_params_in($in);
    if (!$p || ($control === 'tsi_close' && $key === ''))
    {
        aio_log("tsi: $control with bad params");
        // An empty map as {}: the firmware reads actions as an object, [] may be refused.
        $drop = aio_tsi_behave(new stdClass(), 0);
        return aio_tsi_osd(array(), $key !== '' ? aio_tsi_default($key, $drop) : $drop);
    }
    if ($control === 'tsi_state')
    {
        $state = aio_tsi_end_state($in);
        if ($state === '')
            return null;
        aio_log("tsi: closed by playback $state");
        $p['e'] = '0';
        return aio_tsi_osd(array(), aio_tsi_behave(aio_tsi_acts($p, false), 0));
    }
    if ($control === 'tsi_close')
    {
        aio_log("tsi: closed, $ck");
        $p['e'] = '0';
        return aio_tsi_osd(array(), aio_tsi_default($key, aio_tsi_behave(aio_tsi_acts($p, false), 0)));
    }
    if ($control === 'tsi_open')
    {
        aio_log("tsi: open, TorrServer, hash={$p['h']}");
        $p['e'] = '0';
        return aio_tsi_default($key, aio_tsi_osd(aio_tsi_comps(aio_tsi_rows_blank($p['n'], $p['l'])),
            aio_tsi_behave(aio_tsi_acts($p, true), AIO_TSI_TICK_FIRST_MS)));
    }
    $err = '';
    $rows = aio_tsi_rows($p['h'], $p['n'], $p['l'], $err);
    if ($err !== '' && $p['e'] === '0')
        aio_log("tsi: $err");
    $p['e'] = $err !== '' ? '1' : '0';
    return aio_tsi_osd(aio_tsi_comps($rows),
        aio_tsi_behave(aio_tsi_acts($p, true), $err !== '' ? AIO_TSI_TICK_ERR_MS : AIO_TSI_TICK_MS));
}
