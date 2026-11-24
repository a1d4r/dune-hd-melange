<?php
// "Онлайн" on the Dune, its start (the list: view_online.php): ENTER of a row ("call", the check
// of the link, vod_play; an episode in the playlist of its season, the others lazy: lampac_next:),
// "Continue" of a record of it, play_action of a card with "Онлайн" (an online record, the title of a profile
// of Lampac), the window "Раздач нет" with "Искать онлайн".
// PHP 5.3.6, firmware API only. Types (MelLpRow, MelMovie): phpstan-types.neon.

// "Continue": seconds of the whole way (the row of the shell waits ~20 s).
define('MEL_LPV_RESUME_MAX', 18);
// Seconds kept for the check of a link after "call".
define('MEL_LPV_CHECK_MIN', 4);

// The id of this Dune for Lampac of "Представляться приложением Lampa": 8 characters [a-z0-9], as
// the uid of the web Lampa, made once and kept in data_dir (MEL_LP_UID_FILE). '' when it cannot be
// kept: a new one each time is worse than none; that told once in the life of php_server.
/**
 * @return string
 */
function mel_lp_uid()
{
    static $told = false;
    $u = mel_lp_uid_read(mel_data_dir());
    $f = mel_data_dir() . '/' . MEL_LP_UID_FILE;
    // A link is never written over (mel_lp_uid_read: none).
    if ($u !== '' || mel_data_dir() === '' || is_link($f))
        return $u;
    for ($i = 0; $i < 8; $i++)
        $u .= substr('abcdefghijklmnopqrstuvwxyz0123456789', mt_rand(0, 35), 1);
    if (mel_write_file($f, $u))
        return $u;
    if (!$told)
        mel_log('lampac: ' . MEL_LP_UID_FILE . ' not written');
    $told = true;
    return '';
}

// The ids of movie_str of play_action $in kept for card $mv (MelLpv::$ids): of a series tmdbtv_id, else tmdb_id (the
// Dune gives a series tmdbtv_id, its tmdb_id and kp_id often empty), kp_id, original_language (of Melange only,
// mel_lpv_tile_ids: the movie_str of the Dune has none). An empty one keeps the one kept before (the native card gave a
// KP, the own card of the same dune id has none; the own card gave the language, the native card has none).
/**
 * @param stdClass $in
 * @param MelMovie $mv
 * @return void
 */
function mel_lpv_ids_keep($in, $mv)
{
    $m = isset($in->movie_str) && is_string($in->movie_str) ? json_decode($in->movie_str, true) : null;
    $tmdb = $mv['series'] ? mel_str($m, 'tmdbtv_id') : '';
    if ($tmdb === '')
        $tmdb = mel_str($m, 'tmdb_id');
    $kp = mel_str($m, 'kp_id');
    mel_lpv_ids_put(mel_lpv_key($mv), array('tmdb' => $tmdb, 'kp' => $kp,
        'original_language' => mel_str($m, 'original_language')));
}

// Ids $new kept for key $k of a card (mel_lpv_key) in MelLpv::$ids: an empty one keeps the one kept before; all empty -
// nothing kept. title (of Lampac, mel_lp_search_start) and title_of (the title of the card it replaced) only when one
// is kept, the two together: none - no keys. No key ('') - nothing kept: it would be of every card of no key.
/**
 * @param string $k
 * @param MelLpvIds $new
 * @return void
 */
function mel_lpv_ids_put($k, $new)
{
    if ($k === '')
        return;
    $was = isset(MelLpv::$ids[$k]) ? MelLpv::$ids[$k] : array('tmdb' => '', 'kp' => '', 'original_language' => '');
    $t = isset($new['title']) && $new['title'] !== '' ? $new : $was;
    $title = isset($t['title']) ? $t['title'] : '';
    if ($new['tmdb'] === '' && $new['kp'] === '' && $new['original_language'] === '' && $title === '')
        return;
    MelLpv::$ids[$k] = array('tmdb' => $new['tmdb'] !== '' ? $new['tmdb'] : $was['tmdb'],
        'kp' => $new['kp'] !== '' ? $new['kp'] : $was['kp'],
        'original_language' => $new['original_language'] !== '' ? $new['original_language'] : $was['original_language']);
    if ($title !== '')
    {
        MelLpv::$ids[$k]['title'] = $title;
        MelLpv::$ids[$k]['title_of'] = isset($t['title_of']) ? $t['title_of'] : '';
    }
}

// The ids of tile $t for movie_str of a play_action of Melange itself (mel_card_releases, mel_oc_watch; read by
// mel_lpv_ids_keep): the TMDB id of the tile, else of the tile of its meta $meta (in hand, no request), as tmdbtv_id of a
// series, else tmdb_id; kp_id of Kinopoisk and original_language (mel_cat_orig_lang) the same way. None empty. Lampac puts
// the quality into the name of a balancer and show false only with a TMDB id, and its balancers of anime only with
// the language ja or zh: every way of Melange that makes movie_str for mel_play_action adds these. Its tmdb_id /
// tmdbtv_id is the id of the releases of a title of no IMDb too (mel_movie, mel_rel_id).
/**
 * @param MelCatTile $t
 * @param MelCardMeta|null $meta
 * @return array<string, string>
 */
function mel_lpv_tile_ids($t, $meta = null)
{
    $m = $meta && $meta['tile'] ? $meta['tile'] : null;
    $tmdb = mel_str($t, '_tmdbId');
    $kp = mel_str($t, 'kpId');
    $lang = mel_str($t, 'originalLanguage');
    if ($tmdb === '')
        $tmdb = mel_str($m, '_tmdbId');
    if ($kp === '')
        $kp = mel_str($m, 'kpId');
    if ($lang === '')
        $lang = mel_str($m, 'originalLanguage');
    $out = array();
    if ($tmdb !== '')
        $out[mel_str($t, 'type') === 'series' ? 'tmdbtv_id' : 'tmdb_id'] = $tmdb;
    if ($kp !== '')
        $out['kp_id'] = $kp;
    if ($lang !== '')
        $out['original_language'] = $lang;
    return $out;
}

// --- ENTER of a row of the online list (mel_lpv_pick, view_online.php): the link, the player.

// The link of row $r to play by deadline $deadline (mel_lp_now): "call" of a row of the method
// call (MEL_LPV_CHECK_MIN s kept for the check; of episode $e of season $s, $e > 0: the reply is
// that episode, mel_lp_call), then the check of the link (mel_lp_check: its mirrors, the length). A
// link that does not answer the check is played, as mel_precheck's. A row of a season (method
// season, mel_lp_season_rows) plays nothing: its episode first (mel_lp_ep_row).
// -> array(url, why: '' or a code of MelLpWhy, detail).
/**
 * @param string $base
 * @param MelLpRow $r
 * @param float $deadline
 * @param int $s
 * @param int $e
 * @return array{url: string, why: string, detail: string}
 */
function mel_lpv_link($base, $r, $deadline, $s = 0, $e = 0)
{
    if ($r['method'] === 'season')
    {
        mel_log("lampac: {$r['path']}: a row of a season, not of its episode");
        return array('url' => '', 'why' => 'method', 'detail' => 'season');
    }
    $url = $r['url'];
    if ($r['method'] === 'call')
    {
        $total = (int) min(MEL_LP_CALL_MAX, floor($deadline - mel_lp_now() - MEL_LPV_CHECK_MIN));
        if ($total < MEL_LP_REPLY_MIN)
        {
            mel_log('lampac: call: no time left');
            return array('url' => '', 'why' => 'down', 'detail' => '');
        }
        $c = mel_lp_call($base, $url, $total, MEL_LP_CALL_PAUSE, $s, $e);
        if ($c['err'] !== '')
            return array('url' => '', 'why' => $c['err'], 'detail' => $c['detail']);
        $url = $c['url'];
    }
    $total = (int) min(MEL_LP_CHECK_MAX, floor($deadline - mel_lp_now()));
    $k = mel_lp_check($base, $url, max(MEL_LP_REPLY_MIN, $total));
    if ($k['class'] === 'bad')
        return array('url' => '', 'why' => $k['why'], 'detail' => $k['detail']);
    return array('url' => $k['url'], 'why' => '', 'detail' => '');
}

// The start of row $f of card $mv (MelLpFound: mel_lp_find, the season of a row of the online list;
// a movie: mel_lp_found of its row) of episode $e of season $s (-1 a movie) from $pos s, by deadline
// $deadline (mel_lp_now): its link (mel_lpv_link), the season of the episode kept (MelLpv::$season:
// the lazy episodes take their link from it), vod_play of the link. -> MelAction, or array(why,
// detail): no row ($f's reason), no link.
// vod_play is linked to the card when it has a dune id (spec §7): recent_uid aio:<dune id>:<s>:<e>:<key
// of the row>, as a release; recent_action_params wh_release = the key (the path of the balancer and the hex
// of the voice in it), wh_mode lampac ("Continue": mel_lpv_resume), wh_prof the id of the profile of
// Lampac of the start (v2). A linked episode plays in a playlist of the episodes $f['have'] of its voice in
// the season (mel_lp_episode, up to MEL_EPISODES_MAX), the others lazy -
// "lampac_next:<dune id>:<s>:<e>:<key>:<id of the profile>" (mel_lpv_lazy: the same voice, the key has it,
// the same Lampac); P+/P- and the autoplay go through them. On r24
// through pf_check (mel_pf_check of call_plugin).
/**
 * @param MelMovie $mv
 * @param string $lang
 * @param MelLpFound $f
 * @param int $s
 * @param int $e
 * @param int $pos
 * @param float $deadline
 * @return MelAction|array{string, string}
 */
function mel_lpv_ep_play($mv, $lang, $f, $s, $e, $pos, $deadline)
{
    $r = $f['row'];
    if ($r === null)
        return array($f['why'], $f['detail']);
    $l = mel_lpv_link(Mel::$settings['lp'], $r, $deadline, $e > 0 ? $s : 0, max(0, $e));
    if ($l['why'] !== '')
        return array($l['why'], $l['detail']);
    $url = $l['url'];
    if ($f['sea'])
        MelLpv::$season = array('k' => $mv['dune_id'], 'key' => $r['key'], 's' => $s, 'prof' => Mel::$settings['lp_prof'],
            'row' => $r, 'sea' => $f['sea']);
    $have = $f['have'];
    $path = $r['path'];
    $linked = $mv['dune_id'] !== '';
    $eps = $e > 0 && $linked && in_array($e, $have, true) && count($have) <= MEL_EPISODES_MAX ?
        array_values(array_unique($have)) : array($e);
    $list = count($eps) > 1;
    $series = array();
    $ndx = 0;
    // The profile of Lampac of this start goes with the lazy ones: they are operations of their own (spec v2 §1.7).
    $prof = Mel::$settings['lp_prof'] !== '' ? ':' . Mel::$settings['lp_prof'] : '';
    foreach ($eps as $n => $i)
    {
        if ($i === $e)
        {
            $ndx = $n;
            $series[] = mel_vod_item($mv, $s, $e, $url, $linked ? $r['key'] : '', $list);
        }
        else
            // From MEL_FW_NEXT_URL_R linked as the ready one: its URL may come in the reply of get_vod_stream_url
            // (mel_lazy_action).
            $series[] = array(
                PluginVodSeriesInfo::name => mel_ser_name($mv, $s, $i, true),
                PluginVodSeriesInfo::playback_url => "lampac_next:{$mv['dune_id']}:$s:$i:{$r['key']}$prof",
                PluginVodSeriesInfo::playback_url_is_stream_url => false) + mel_lazy_link($mv, $s, $i, $r['key']);
    }
    // The lazy ones find the card after a restart of php_server too.
    if ($list)
        mel_movie_save($mv, $lang);
    $info = array(
        PluginVodInfo::id => 'aio.' . mel_lpv_key($mv),
        PluginVodInfo::name => $list ? $mv['title'] : mel_ser_name($mv, $s, $e, false),
        PluginVodInfo::sub_name => $r['src'] . ($r['voice'] !== '' ? " \xC2\xB7 {$r['voice']}" : ''),
        PluginVodInfo::poster_url => $mv['poster'],
        PluginVodInfo::series => $series,
        PluginVodInfo::initial_series_ndx => $ndx,
        // No annotation of the card in INFO, as mel_play.
        PluginVodInfo::description => '',
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => true,
        PluginVodInfo::skip_dummy_player_on_error => false);
    if ($pos > 0)
        $info[PluginVodInfo::initial_position_ms] = $pos * 1000;
    if ($linked)
    {
        $info[PluginVodInfo::recent_plugin] = 'shell_ext';
        $info[PluginVodInfo::recent_action_params] = mel_recent_params($mv['dune_id'], $r['key'], 'lampac',
            Mel::$settings['lp_prof']);
    }
    mel_log("play: lampac {$r['key']}" . ($e > 0 ? sprintf(' S%02dE%02d', $s, $e) : '') . " pos=$pos linked=" .
        ($linked ? 1 : 0) . ($list ? ', ' . count($eps) . ' episodes' : ''));
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}

// --- The lazy episodes of a playlist of a season (mel_lpv_ep_play).

// get_vod_stream_url of lazy episode $pb "lampac_next:<dune id>:<s>:<e>:<key>[:<id of the profile of Lampac>]"
// (mel_lazy_action of playback.php; no id: a playlist of before v2 - the main pair) -> error_action:
// handle_user_input lp_next (mel_lpv_next), as mel_next; the episode is that of $pb, whatever item of the playlist
// the player is on (plugin_vod_series_ndx). A bad one: the error. $here: the caller runs lp_next itself (mel_lazy_url).
/**
 * @param string $pb
 * @param bool $here
 * @return MelAction
 */
function mel_lpv_lazy($pb, $here = false)
{
    if (!preg_match('/^lampac_next:(' . MEL_DUNE_ID . '):([1-9][0-9]{0,3}):([1-9][0-9]{0,4}):([^:]+)(?::([a-z0-9]{8}))?\z/', $pb,
        $m) || mel_lp_key_parse($m[4]) === null)
    {
        mel_log('lazy: bad playback_url ' . mel_cut(mel_mask($pb), 100));
        return mel_error('err_list_expired', '', true);
    }
    $prof = isset($m[5]) ? $m[5] : '';
    mel_log("lazy: lampac S{$m[2]}E{$m[3]} {$m[4]}" . ($prof !== '' ? " profile $prof" : '') .
        ($here ? ' -> the URL here' : ' -> handle_user_input lp_next'));
    return mel_input('lp_next', array('k' => $m[1], 's' => $m[2], 'e' => $m[3], 'key' => $m[4], 'prof' => $prof));
}

// lp_next (mel_lpv_lazy): episode $in->e of season $in->s in the voice of key $in->key of card $in->k
// (mel_movie_load) under the player -> vod_play of the playlist of the season with it a link
// (mel_lpv_ep_play). The link: of the season of the last start (MelLpv::$season) when it is of that key
// and season; none there, or it gives no link - mel_lp_find anew (the links of /proxy/ in a season
// expire; after a restart of php_server: /externalids too); "call" with its check of the episode, the
// check of the link. One deadline for all, of the operation. No such episode in the voice, a row that
// gives no link: the window "Серия … не загрузилась" with the reason and "Стоп", as mel_next; never
// another balancer or voice (spec §5.2: no families). The Lampac of the profile of the link ($in->prof; none there,
// none of the link - the main pair, mel_op_use).
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_lpv_next($in)
{
    if (isset($in->prof) && is_string($in->prof) && $in->prof !== '')
        mel_op_use(Mel::$settings['prof'], $in->prof);
    $ok = isset($in->k, $in->s, $in->e, $in->key) && is_string($in->k) && preg_match('/^' . MEL_DUNE_ID . '\z/', $in->k) &&
        is_string($in->s) && preg_match('/^[1-9][0-9]{0,3}\z/', $in->s) && is_string($in->e) &&
        preg_match('/^[1-9][0-9]{0,4}\z/', $in->e) && mel_lp_key_parse($in->key) !== null;
    $saved = $ok ? mel_movie_load($in->k) : null;
    $lang = $saved ? $saved['lang'] : (isset($in->lang) && is_string($in->lang) ? $in->lang : '');
    if (!$saved)
    {
        mel_log('lampac next: ' . ($ok ? 'no card' : 'bad params'));
        return mel_next_failed($lang, '', 'err_list_expired');
    }
    $mv = $saved['movie'];
    $s = intval($in->s);
    $e = intval($in->e);
    $key = strval($in->key);
    $ep = sprintf('S%02dE%02d', $s, $e);
    $base = Mel::$settings['lp'];
    $left = Mel::$t0 > 0 ? MEL_OP_BUDGET - (microtime(true) - Mel::$t0) : MEL_OP_BUDGET;
    $deadline = mel_lp_now() + $left;
    $c = MelLpv::$season;
    $f = mel_lp_not_found('down', 'no address');
    if ($base !== '' && $c && $c['k'] === $mv['dune_id'] && $c['key'] === $key && $c['s'] === $s &&
        $c['prof'] === Mel::$settings['lp_prof'])
    {
        $f = mel_lp_found($c['row'], $c['sea'], $s, $e);
        $a = mel_lpv_ep_play($mv, $lang, $f, $s, $e, 0, $deadline);
        if (!isset($a[0]))
            return mel_lpv_played($mv, $s, $e, $a);
        // Anew only for a link that expired (the check, http, no answer, an empty link of "call": its
        // token_movie of hours ago): the cache of Lampac keeps a reply of another episode, a list or
        // rch, a second "call" gives the same after its time.
        if ($f['row'] && !in_array($a[0], array('http', 'down', 'no_link', 'not_hls', 'html', 'long_variant'), true))
            return mel_lpv_next_failed($lang, $base, $f['row']['src'], $f['voice'], $s, $e, $a[0], $a[1], array());
        mel_log("lampac next: $ep " . ($f['row'] ? "of the season kept: {$a[0]}" : "not in the season kept ({$a[0]})") .
            ' -> the balancer anew');
    }
    else if ($base !== '')
        mel_log("lampac next: $ep, no season kept for $key -> the balancer");
    if ($base !== '')
    {
        list($t) = mel_lp_externalids($base, mel_lpv_title($mv));
        $f = mel_lp_find($base, $key, mel_lp_query($t), $s, $e,
            min(MEL_LP_REPLIES_MAX, max(0, $deadline - mel_lp_now() - MEL_LPV_CHECK_MIN)));
    }
    $a = mel_lpv_ep_play($mv, $lang, $f, $s, $e, 0, $deadline);
    if (!isset($a[0]))
        return mel_lpv_played($mv, $s, $e, $a);
    $k = mel_lp_key_parse($key);
    return mel_lpv_next_failed($lang, $base, $f['row'] ? $f['row']['src'] : ucfirst($k ? $k[0] : ''), $f['voice'], $s, $e,
        $a[0], $a[1], $f['have']);
}

// vod_play $a of a lazy episode $e of season $s of card $mv (mel_lpv_ep_play), noted for the online
// list on screen (MelLpv::$played: back from the player it goes to that episode).
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @param MelAction $a
 * @return MelAction
 */
function mel_lpv_played($mv, $s, $e, $a)
{
    $st = Mel::$state;
    MelLpv::$played = $st && !empty($st['lp_on']) && $st['movie']['dune_id'] === $mv['dune_id'] ?
        array('rid' => $st['rid'], 's' => $s, 'e' => $e) : null;
    return $a;
}

// The window of lazy episode $e of season $s with no link (reason $why, $detail; no_episode and
// no_episodes of voice $voice of balancer $src: those $have) over the stopped player: "Серия … не
// загрузилась", the reason, "Стоп" (as mel_next_failed).
/**
 * @param string $lang
 * @param string $base
 * @param string $src
 * @param string $voice
 * @param int $s
 * @param int $e
 * @param string $why
 * @param string $detail
 * @param list<int> $have
 * @return MelAction
 */
function mel_lpv_next_failed($lang, $base, $src, $voice, $s, $e, $why, $detail, $have)
{
    mel_log(sprintf('lampac next: S%02dE%02d: no link (%s%s)', $s, $e, $why, mel_lp_mask(mel_lp_log_detail($why, $detail, ', %s'),
        $base)));
    $text = $why === 'no_episode' || $why === 'no_episodes' ?
        mel_lpv_no_ep_text($lang, $src, $voice, $s, $e, $why, $have) : "$src: " . mel_lpv_why($lang, $why, $detail);
    return mel_dialog(mel_tr($lang, 'next_failed', mel_se($s, $e)), mel_dialog_lines($text),
        array(mel_tr($lang, 'next_stop') => mel_input('next_stop')));
}

// --- play_action: "Continue", an online record, the title of a profile of Lampac, no releases.

// "Continue" $r of an online record (mel_resume, mode lampac: hash the key of the record as it is, pos) of card
// $mv (spec §7), of episode $e of season $s (-1 a movie; of a series the episode play_action chose: the next one
// after a watched one, from 0 - mel_wh_resume_ep): the balancer of the key alone (mel_lp_find: /lite/<path> on the
// Lampac of the settings, no events), the voice of the key, of a series its season and episode (the voice of
// another season found among its voice[] by the hex), "call", the check, vod_play from the position
// (mel_lpv_ep_play: an episode in the playlist of its season, the others lazy); within MEL_LPV_RESUME_MAX s. Not
// so (not a key, no title, no voice, no episode, no link) -> the search window of that episode, the cursor on the
// row of the key (else on that balancer), the yellow line "<it>: озвучки больше нет" (none when the search
// finds the row of the key); the row found but no link (fail: the reason), or no answer to the find (down) -
// "<it> не ответил" (down), else "<it> не дал видео". An episode of a
// series of no number (play_action asks none such: mel_lpv_on) - "<it>: видео не получить", "нет такой серии":
// a search of it would find no season, a list opened anew the same.
/**
 * @param MelMovie $mv
 * @param MelResume $r
 * @param string $lang
 * @param int $s
 * @param int $e
 * @return MelAction
 */
function mel_lpv_resume($mv, $r, $lang, $s = -1, $e = -1)
{
    $k = mel_lp_key_parse($r['hash']);
    $path = $k ? $k[0] : '';
    $ep = $s >= 0 ? sprintf(' S%02dE%02d', $s, $e) : '';
    // A list of before must not take the player back to its episode (as mel_lpv_pick).
    MelLpv::$played = null;
    if ($mv['series'] && ($s < 1 || $e < 1))
    {
        mel_log("resume: lampac $path$ep, no such episode");
        return mel_dialog(mel_lpv_tr($lang, 'lp_failed', array('src' => $path !== '' ? ucfirst($path) : 'Lampac')),
            mel_dialog_lines(mel_lpv_why($lang, 'no_episode', '')), array('OK' => mel_close_and(null)));
    }
    $base = Mel::$settings['lp'];
    $deadline = mel_lp_now() + MEL_LPV_RESUME_MAX;
    list($t) = mel_lp_externalids($base, mel_lpv_title($mv));
    $f = mel_lp_find($base, $r['hash'], mel_lp_query($t), $s, $e,
        min(MEL_LP_REPLIES_MAX, max(0, $deadline - mel_lp_now() - MEL_LPV_CHECK_MIN)));
    $a = mel_lpv_ep_play($mv, $lang, $f, $s, $e, $r['pos'], $deadline);
    if (!isset($a[0]))
    {
        mel_log("resume: lampac $path$ep, pos {$r['pos']}");
        return $a;
    }
    mel_log("resume: lampac $path$ep: " . ($f['row'] ? 'no link' : 'no row') . " ({$a[0]}) -> the search");
    $st = mel_list_state($mv, $s, $e, array(), $lang, mel_no_errors());
    $st['lp_only'] = true;
    // No answer of the balancer to the find itself (no row): as to "call" - "<it> не ответил".
    return mel_lpv_search_open($st, 'open', array('path' => $path, 'key' => $r['hash'],
        'fail' => $f['row'] || $a[0] === 'down' ? $a[0] : ''));
}

// play_action $in of card $mv, of episode $e of season $s (-1 a movie; of a series the one play_action chose: of
// "Continue", of the shell, of the history) with "Онлайн" (mel_lpv_on: the Lampac of the pair of the operation,
// mel_op_title): "Continue" $r of an online record (mode lampac: mel_lpv_resume); the title of a profile of Lampac
// ($online: its record online, of none - the first Lampac; a new one of the main Lampac; spec v2 §2.1) - the search, the
// cursor on the row of the key $lpk of the online record (of "Continue" with no episode to play, of the latest record of
// the series: mel_wh_pick, of a movie: mel_wh_card_last), nothing online - the releases with the yellow line when a
// profile of releases is there and $back (the online list has a way back to the releases; false: a source chosen by
// "Смотреть из…" - the window "nothing online" of it). null: the releases (the title of a profile of releases). A source
// of releases chosen with none for the title: the window "Раздач нет" (mel_lpv_no_releases) still offers "Искать онлайн"
// of the main Lampac - so meant.
/**
 * @param stdClass $in
 * @param MelMovie $mv
 * @param string $lang
 * @param int $s
 * @param int $e
 * @param MelResume|null $r
 * @param string $lpk
 * @param bool $online
 * @param bool $back
 * @return MelAction|null
 */
function mel_lpv_play_action($in, $mv, $lang, $s, $e, $r, $lpk, $online, $back = true)
{
    // Of a series too: its online list from the releases takes them.
    mel_lpv_ids_keep($in, $mv);
    if (!mel_lpv_on($mv, $s, $e))
        return null;
    if ($r && $r['mode'] === 'lampac')
        return mel_lpv_resume($mv, $r, $lang, $s, $e);
    if (!$online)
        return null;
    $aio = Mel::$settings['prof'] !== '' && $back;
    mel_log('lampac: ' . ($lpk !== '' ? 'an online record' : ($r ? 'a record of a release, no profile of releases' :
        'the title of Lampac')) . ' -> the search');
    $st = mel_list_state($mv, $s, $e, array(), $lang, mel_no_errors());
    $st['lp_only'] = true;
    return mel_lpv_search_open($st, 'open', array('back' => $aio, 'key' => $lpk));
}

// No releases at all for card $mv ($res of mel_fetch: err_no_streams, no reasons) and
// "Онлайн": the window "Раздач нет" with "Искать онлайн" (the empty list live: lp_find) and
// "Закрыть". null: the error as before.
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @param string $lang
 * @param array{error: string, detail: string, errors?: MelErrors} $res
 * @return MelAction|null
 */
function mel_lpv_no_releases($mv, $s, $e, $lang, $res)
{
    if (!mel_lpv_on($mv, $s, $e) || $res['error'] !== 'err_no_streams' ||
        (isset($res['errors']) && $res['errors']['lines']))
        return null;
    $st = mel_list_state($mv, $s, $e, array(), $lang, mel_no_errors());
    Mel::$state = $st;
    mel_log('lampac: no releases -> the window with the search');
    return mel_dialog(mel_tr($lang, 'lp_no_releases'), array(), array(
        mel_tr($lang, 'lp_find') => mel_close_and(mel_gc_act('lp_find', $st['rid'])),
        mel_tr($lang, 'lp_close') => mel_close_and(null)));
}
