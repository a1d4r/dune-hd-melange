<?php
// The button "Трейлер" of the own card (stage 8 of 2.0): shown when the meta has trailers[]. ENTER asks
// the resolver of the catalog server (trailer/<movie|series>/<tt...|tmdb:N>) itself, without following
// its redirect, and plays the HLS of its Location full screen by vod_play: the player of the Dune
// waits for a slow reply on every Range request. 404, 503/504, a timeout, the network: the window
// "Трейлер не нашёлся". The trailer is not linked to the card: no recent_uid, recent_plugin, mdb_info
// (the history of the Dune skips such a vod_play), no markers, nothing of Aio::$state or
// Aio::$playing: no history, no marks, no "Continue". The card screen has no playback_finish: after
// the player the shell shows it as it was, the focus on the button.
// Needs main.php (aio_input, aio_error, aio_tr, aio_log, aio_http_peek), catalog.php (AIO_CAT_BASE),
// card_meta.php (aio_cm_id).
// Types in the PHPDoc (AioOcCard): phpstan-types.neon of the repository.

// The resolver, next to the addon path of the catalog server.
define('AIO_CT_BASE', preg_replace('~stremio/$~', '', AIO_CAT_BASE) . 'trailer/');
// The resolver: a cold title 1.5-3 s (13.11.2026), from its cache ms; s.
define('AIO_CT_CONNECT', 3);
define('AIO_CT_TOTAL', 5);
// A longer Location is not taken for a stream URL.
define('AIO_CT_LOC_MAX', 4096);

// The address of the trailer of card $c: its type and its IMDb (of the tile or the meta), else
// tmdb:N, the colon as it is; '' for none (another type, no id).
/**
 * @param AioOcCard $c
 * @return string
 */
function aio_ct_url($c)
{
    $id = aio_cm_id($c['tile']);
    if (!preg_match('/^(tt[0-9]{1,10}|tmdb:[0-9]{1,10})$/D', $id))
        return '';
    return AIO_CT_BASE . $c['tile']['type'] . '/' . $id;
}

// The button of card $c, if it has one: trailers[] in its meta and an address to ask.
/**
 * @param AioOcCard $c
 * @return bool
 */
function aio_ct_has($c)
{
    return $c['meta'] && $c['meta']['trailer'] && aio_ct_url($c) !== '';
}

// ENTER on the button of card $key: the request in an input of its own, "Please wait" of the shell
// due after AIO_CARD_DELAY as at ENTER on a tile (the ENTER of the card waits AIO_DELAY_INPUT).
/**
 * @param string $key
 * @return AioAction
 */
function aio_ct_enter($key)
{
    return aio_input('card_trailer', array('c' => $key), AIO_CARD_DELAY);
}

// The trailer of card $c: the resolver asked -> vod_play of its m3u8, else the window. One log line.
/**
 * @param AioOcCard $c
 * @return AioAction
 */
function aio_ct_play($c)
{
    $url = aio_ct_url($c);
    $tag = 'card: trailer ' . $c['tile']['type'] . ' ' . aio_cm_id($c['tile']);
    if (!aio_ct_has($c))
    {
        aio_log("$tag: fail, no trailer");
        return aio_ct_none($c);
    }
    $err = '';
    $t = microtime(true);
    list($code, $loc) = aio_http_peek($url, $err, AIO_CT_CONNECT, AIO_CT_TOTAL);
    $ms = intval(round((microtime(true) - $t) * 1000));
    if ($err !== '')
    {
        aio_log("$tag: fail $err, $ms ms");
        return aio_ct_none($c);
    }
    if ($code === 302 && strlen($loc) <= AIO_CT_LOC_MAX && preg_match('~^https?://[\x21-\x2e\x30-\x7b\x7d\x7e]+/[\x21-\x7b\x7d\x7e]*$~iD', $loc))
    {
        aio_log("$tag: 302 $ms ms");
        return aio_ct_vod($c, $loc);
    }
    aio_log($code === 302 ? "$tag: fail 302 without a URL, $ms ms" : "$tag: $code $ms ms");
    return aio_ct_none($c);
}

// "Трейлер не нашёлся", with OK.
/**
 * @param AioOcCard $c
 * @return AioAction
 */
function aio_ct_none($c)
{
    $a = aio_error('card_trailer_none', '', false, false);
    $a[GuiAction::data][PluginShowErrorActionData::title] = aio_tr($c['ui'], 'card_trailer_none');
    return $a;
}

// vod_play of $url full screen: the title, the poster and one stream URL from the start; no field that links it to
// the card (no history, marks, markers, actions).
/**
 * @param AioOcCard $c
 * @param string $url
 * @return AioAction
 */
function aio_ct_vod($c, $url)
{
    $t = $c['tile'];
    $info = array(
        PluginVodInfo::id => 'aio.trailer.' . aio_cm_id($t),
        PluginVodInfo::name => $t['name'],
        PluginVodInfo::sub_name => aio_tr($c['ui'], 'card_trailer'),
        PluginVodInfo::poster_url => $t['poster'],
        PluginVodInfo::series => array(array(
            PluginVodSeriesInfo::name => $t['name'],
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
