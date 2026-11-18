<?php
// The button "Трейлер" of the own card (stage 8 of 2.0): shown when the meta has trailers[]. ENTER asks
// the resolver of the catalog server (trailer/<movie|series>/<tt...|tmdb:N>) itself, without following
// its redirect, and plays the HLS of its Location full screen by vod_play: the player of the Dune
// waits for a slow reply on every Range request. 404, 503/504, a timeout, the network: the window
// "Трейлер не нашёлся". The trailer is not linked to the card: no recent_uid, recent_plugin, mdb_info
// (the history of the Dune skips such a vod_play), no markers, nothing of Mel::$state or
// Mel::$playing: no history, no marks, no "Continue". The card screen has no playback_finish: after
// the player the shell shows it as it was, the focus on the button.
// Needs main.php (mel_input, mel_error, mel_tr, mel_log, mel_http_peek), catalog.php (MEL_CAT_BASE),
// card_meta.php (mel_cm_id).
// Types in the PHPDoc (MelOcCard): phpstan-types.neon of the repository.

// The resolver, next to the addon path of the catalog server.
define('MEL_CT_BASE', preg_replace('~stremio/$~', '', MEL_CAT_BASE) . 'trailer/');
// The resolver: a cold title 1.5-3 s (13.11.2026), from its cache ms; s.
define('MEL_CT_CONNECT', 3);
define('MEL_CT_TOTAL', 5);
// A longer Location is not taken for a stream URL.
define('MEL_CT_LOC_MAX', 4096);

// The address of the trailer of card $c: its type and its IMDb (of the tile or the meta), else
// tmdb:N, the colon as it is; '' for none (another type, no id).
/**
 * @param MelOcCard $c
 * @return string
 */
function mel_ct_url($c)
{
    $id = mel_cm_id($c['tile']);
    if (!preg_match('/^(tt[0-9]{1,10}|tmdb:[0-9]{1,10})$/D', $id))
        return '';
    return MEL_CT_BASE . $c['tile']['type'] . '/' . $id;
}

// The button of card $c, if it has one: trailers[] in its meta and an address to ask.
/**
 * @param MelOcCard $c
 * @return bool
 */
function mel_ct_has($c)
{
    return $c['meta'] && $c['meta']['trailer'] && mel_ct_url($c) !== '';
}

// ENTER on the button of card $key: the request in an input of its own, "Please wait" of the shell
// due after MEL_CARD_DELAY as at ENTER on a tile (the ENTER of the card waits MEL_DELAY_INPUT).
/**
 * @param string $key
 * @return MelAction
 */
function mel_ct_enter($key)
{
    return mel_input('card_trailer', array('c' => $key), MEL_CARD_DELAY);
}

// The trailer of card $c: the resolver asked -> vod_play of its m3u8, else the window. One log line.
/**
 * @param MelOcCard $c
 * @return MelAction
 */
function mel_ct_play($c)
{
    $url = mel_ct_url($c);
    $tag = 'card: trailer ' . $c['tile']['type'] . ' ' . mel_cm_id($c['tile']);
    if (!mel_ct_has($c))
    {
        mel_log("$tag: fail, no trailer");
        return mel_ct_none($c);
    }
    $err = '';
    $t = microtime(true);
    list($code, $loc) = mel_http_peek($url, $err, MEL_CT_CONNECT, MEL_CT_TOTAL);
    $ms = intval(round((microtime(true) - $t) * 1000));
    if ($err !== '')
    {
        mel_log("$tag: fail $err, $ms ms");
        return mel_ct_none($c);
    }
    if ($code === 302 && mel_ct_url_ok($loc))
    {
        mel_log("$tag: 302 $ms ms");
        return mel_ct_vod($c, $loc);
    }
    mel_log($code === 302 ? "$tag: fail 302 without a URL, $ms ms" : "$tag: $code $ms ms");
    return mel_ct_none($c);
}

// $url fits as a stream URL of a reply: http(s), a host and a path, no space, control character or '|',
// at most MEL_CT_LOC_MAX bytes. Also egg.php.
/**
 * @param string $url
 * @return bool
 */
function mel_ct_url_ok($url)
{
    return strlen($url) <= MEL_CT_LOC_MAX && preg_match('~^https?://[\x21-\x2e\x30-\x7b\x7d\x7e]+/[\x21-\x7b\x7d\x7e]*$~iD', $url) === 1;
}

// "Трейлер не нашёлся", with OK.
/**
 * @param MelOcCard $c
 * @return MelAction
 */
function mel_ct_none($c)
{
    $a = mel_error('card_trailer_none', '', false, false);
    $a[GuiAction::data][PluginShowErrorActionData::title] = mel_tr($c['ui'], 'card_trailer_none');
    return $a;
}

// vod_play of the trailer of card $c at $url (mel_ct_clip).
/**
 * @param MelOcCard $c
 * @param string $url
 * @return MelAction
 */
function mel_ct_vod($c, $url)
{
    $t = $c['tile'];
    return mel_ct_clip('aio.trailer.' . mel_cm_id($t), $t['name'], mel_tr($c['ui'], 'card_trailer'), $t['poster'], $url);
}

// vod_play of $url full screen: vod id $id, the title $name, $sub under it, the poster $poster ('' none) and one
// stream URL from the start; no field that links it to a card (no history, marks, markers, actions). Also egg.php.
// To the shell only as a reply of handle_user_input: on r24 mel_pf_check wraps it (main.php call_plugin).
/**
 * @param string $id
 * @param string $name
 * @param string $sub
 * @param string $poster
 * @param string $url
 * @return MelAction
 */
function mel_ct_clip($id, $name, $sub, $poster, $url)
{
    $info = array(
        PluginVodInfo::id => $id,
        PluginVodInfo::name => $name,
        PluginVodInfo::sub_name => $sub,
        PluginVodInfo::poster_url => $poster,
        PluginVodInfo::series => array(array(
            PluginVodSeriesInfo::name => $name,
            PluginVodSeriesInfo::playback_url => $url,
            PluginVodSeriesInfo::playback_url_is_stream_url => true)),
        PluginVodInfo::initial_series_ndx => 0,
        PluginVodInfo::description => '',
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => false,
        PluginVodInfo::skip_dummy_player_on_error => false);
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}
