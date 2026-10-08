<?php
// Playback: vod_play linked to the Dune card, the playlist of the season with
// lazy episodes, back from the player to the list, episodes by the left and
// right keys. Needs of main.php: Aio (state), aio_log, aio_mask, aio_error,
// aio_tr, aio_input, aio_close_and, aio_dialog, aio_fetch, aio_list_state,
// aio_open_list, AIO_SUP_ID; of parse.php: aio_str, aio_arr, aio_cut,
// aio_dialog_lines, aio_magnet; of jacred.php: aio_jacred, aio_carry_tracks, AIO_JACRED;
// of view_gcomps.php: aio_gc_act, aio_gc_cursor, aio_gc_pos.

// A season with more episodes (by season_numbers) plays as one episode: a
// playlist item takes ~1.7 KB of memory (limit 128 MB).
define('AIO_EPISODES_MAX', 500);

// --- Playback.

// "Title S02E04" for an episode, the title for a movie.
function aio_ep_name($mv, $s, $e)
{
    return $mv['title'] . ($e > 0 ? sprintf(' S%02dE%02d', $s, $e) : '');
}

// An episode of the playlist without a URL: the player asks for it with
// get_vod_stream_url (aio_lazy_action). No recent_uid: its own vod_play has it.
function aio_lazy_series($mv, $s, $e, $hash)
{
    return array(
        PluginVodSeriesInfo::name => aio_ep_name($mv, $s, $e),
        PluginVodSeriesInfo::playback_url => "aio_next:{$mv['dune_id']}:$s:$e:$hash",
        PluginVodSeriesInfo::playback_url_is_stream_url => false);
}

// A playlist item of URL $url, linked to the card by $hash ('' - unlinked).
function aio_vod_item($mv, $s, $e, $url, $hash)
{
    $ser = array(
        PluginVodSeriesInfo::name => aio_ep_name($mv, $s, $e),
        PluginVodSeriesInfo::playback_url => $url,
        PluginVodSeriesInfo::playback_url_is_stream_url => true);
    if ($hash === '')
        return $ser;
    $ser[PluginVodSeriesInfo::recent_uid] = implode(':', array(AIO_SUP_ID, $mv['dune_id'], $s, $e, $hash));
    $mdb = array(
        FileMovieInfo::movie_id => $mv['dune_id'],
        FileMovieInfo::title => $mv['title'],
        FileMovieInfo::icon_url => $mv['poster'],
        // As ActionFactory::file_movie_info of Online movies.
        FileMovieInfo::rate_imdb => $mv['rate_imdb'] !== '' ? $mv['rate_imdb'] : null,
        FileMovieInfo::rate_content => null);
    if ($e > 0)
    {
        $mdb[FileMovieInfo::s] = $s;
        $mdb[FileMovieInfo::e] = $e;
    }
    $ser[PluginVodSeriesInfo::mdb_info] = $mdb;
    return $ser;
}

// vod_play linked to the Dune card: recent_uid (prefix = our supplier id)
// gives [NN%] on the button, mdb_info the "Continue watching" row,
// recent_plugin/recent_action_params bring "Continue" back to play_action
// with wh_release in supplier_info. Without a dune id or infoHash: unlinked.
// A linked episode plays in a playlist of its whole season (by season_numbers),
// the others lazy, then E1 of the next season; P+/P- and the autoplay work.
// $ts (TorrServer, torrserver.php): array('url' => stream URL, 'eps' =>
// episode => URL of the other episodes of the season in the pack); those go
// to the playlist as they are, not lazy.
function aio_play($st, $r, $pos, $ts = null)
{
    $mv = $st['movie'];
    $s = $st['s'];
    $e = $st['e'];
    $name = aio_ep_name($mv, $s, $e);
    // A stream not in the debrid cache plays a 2-minute placeholder video:
    // linked, it would put the placeholder's progress on the card. Unknown
    // status (null) is not linked either. TorrServer has no placeholder.
    $linked = $mv['dune_id'] !== '' && $r['hash'] !== '' && ($ts || $r['cached'] === true);
    $ser = aio_vod_item($mv, $s, $e, $ts ? $ts['url'] : $r['url'], $linked ? $r['hash'] : '');
    $series = array($ser);
    $seasons = $mv['seasons'];
    if ($linked && $e > 0 && isset($seasons[$s]) && $e <= $seasons[$s] && $seasons[$s] <= AIO_EPISODES_MAX &&
        preg_match('/^[0-9a-f]{40}$/D', $r['hash']))
    {
        $series = array();
        for ($i = 1; $i <= $seasons[$s]; $i++)
            $series[] = $i === $e ? $ser : ($ts && isset($ts['eps'][$i]) ?
                aio_vod_item($mv, $s, $i, $ts['eps'][$i], $r['hash']) : aio_lazy_series($mv, $s, $i, $r['hash']));
        if (isset($seasons[$s + 1]))
            $series[] = aio_lazy_series($mv, $s + 1, 1, $r['hash']);
        aio_movie_save($mv, $st['lang']);
    }

    $info = array(
        PluginVodInfo::id => 'aio.' . ($mv['dune_id'] !== '' ? $mv['dune_id'] : $mv['imdb']),
        PluginVodInfo::name => $name,
        PluginVodInfo::poster_url => $mv['poster'],
        PluginVodInfo::series => $series,
        PluginVodInfo::initial_series_ndx => count($series) > 1 ? $e - 1 : 0,
        // The fields Online movies always sends (lib/vod/movie.php get_vod_info).
        PluginVodInfo::description => '',
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => true,
        PluginVodInfo::skip_dummy_player_on_error => false);
    // wh_pos is in seconds, as "pos" of the watch history (Online movies
    // multiplies it by 1000 the same way).
    if ($pos > 0)
        $info[PluginVodInfo::initial_position_ms] = $pos * 1000;
    if ($linked)
    {
        $info[PluginVodInfo::recent_plugin] = 'shell_ext';
        $info[PluginVodInfo::recent_action_params] = array(
            'handler_id' => 'entry',
            'control_id' => 'play_recent',
            'sup_id' => AIO_SUP_ID,
            'movie_id' => $mv['dune_id'],
            'sup_data' => 'auto',
            'wh_release' => $r['hash']);
    }
    aio_log('play: hash=' . ($r['hash'] !== '' ? $r['hash'] : '-') .
        " s=$s e=$e pos=$pos linked=" . ($linked ? 1 : 0) . ', playlist ' . count($series) . ($ts ? ', TorrServer' : ''));
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}

// --- Back from the player to the list screen.

// Playback of row $sel of list state $st; $from: rid of the screen under the player.
function aio_playing($from, $st, $sel)
{
    Aio::$playing = array('from' => $from, 'st' => $st, 'sel' => $sel);
}

// "S01E05", or "movie".
function aio_ep_tag($st)
{
    return $st['e'] > 0 ? sprintf('S%02dE%02d', $st['s'], $st['e']) : 'movie';
}

// menu_playback_finish of a live list screen. Only after a real stop, not a
// restart of the player (the flag, as Online movies has it on its screens).
function aio_finish_act($rid)
{
    $a = aio_gc_act('finish', $rid);
    $a[GuiAction::flags] = GUI_ACTION_FLAG_SKIP_ON_PLAY_RESTART;
    return $a;
}

// Back from the player to list screen $in->rid. If lazy episodes moved on
// (another episode of the same release played last), the screen is replaced
// by the list of that episode, cursor on the release: a new rid, so the old
// screen, if the shell keeps it, only says "outdated". Same episode: the
// shell keeps the screen and its cursor; only the progress bar of the
// release played may change (aio_gc_bar_update). No request: the rows are those
// of "next"; JacRed tracks of the old list go over by hash (one release).
function aio_finish($in)
{
    $st = Aio::$state;
    $p = Aio::$playing;
    $rid = isset($in->rid) && is_string($in->rid) ? $in->rid : '';
    if (!$st || $rid !== $st['rid'] || !$p || $p['from'] !== $rid)
    {
        aio_log('finish: not the screen of the playback, nothing to do');
        return null;
    }
    $ns = $p['st'];
    if ($ns['s'] === $st['s'] && $ns['e'] === $st['e'])
    {
        aio_log('finish: ' . aio_ep_tag($st) . ' as the list, screen kept');
        $pr = $ns['rows'];
        return aio_gc_bar_update($st, isset($pr[$p['sel']]) ? $pr[$p['sel']]['hash'] : '');
    }
    $sel = $p['sel'];
    $n = count($ns['rows']);
    $ns['gc'] = aio_gc_cursor($sel, $n);
    $ns['gc_new'] = true;
    $ns['rows'] = aio_carry_tracks($ns['rows'], $st['rows']);
    Aio::$state = $ns;
    // The playback goes on from the new screen: if the event came while the
    // player only switched episodes, the next ones still find their screen.
    aio_playing($ns['rid'], $ns, $sel);
    aio_log('finish: list ' . aio_ep_tag($st) . ', played ' . aio_ep_tag($ns) . " -> screen replaced, row $sel of $n");
    return aio_replace_act("streams:{$ns['rid']}", aio_ep_name($ns['movie'], $ns['s'], $ns['e']));
}

// The screen replaced by the one of $media_url: "Back" does not grow; a new
// media_url, so nothing cached of the old one is reused. $erase: screens
// taken off the top of the path first (3: the shell's episodes and seasons
// over the list, aio_chosen).
function aio_replace_act($media_url, $caption, $erase = 1)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_REPLACE_PATH_ACTION_ID,
        GuiAction::data => array(
            PluginReplacePathActionData::erase_count => $erase,
            PluginReplacePathActionData::elements => array(array(
                PluginPathElement::media_url => $media_url,
                PluginPathElement::caption => $caption,
                PluginPathElement::id => '')),
            // GComps selects by its sel_state ('gc'), not by sel_id.
            PluginReplacePathActionData::sel_id => null,
            PluginReplacePathActionData::post_action => null));
}

// --- Episodes by the left and right keys of the list screen (0.13.0).

// The episode after ($dir 1) or before ($dir -1) S$s E$e by season_numbers,
// as 0.9.0: the next one of the season, else E1 of the nearest later season;
// the one before, else the last of the nearest earlier season. No playlist
// here: no AIO_EPISODES_MAX. An episode past episodes_count (the card lags
// behind a running series) only goes back. -> array(s, e) or null.
function aio_flip_ep($seasons, $s, $e, $dir)
{
    $from = sprintf('S%02dE%02d', $s, $e);
    if (!isset($seasons[$s]))
    {
        aio_log("flip: $from, no data on season $s in season_numbers, nothing");
        return null;
    }
    if ($e > $seasons[$s] && $dir > 0)
    {
        aio_log("flip: $from, E$e beyond {$seasons[$s]} of season $s, nothing");
        return null;
    }
    if ($dir > 0 ? $e < $seasons[$s] : $e > 1)
    {
        aio_log("flip: $from -> " . sprintf('S%02dE%02d', $s, $e + $dir) . ', same season' .
            ($e > $seasons[$s] ? ", E$e beyond {$seasons[$s]} of season $s" : ''));
        return array($s, $e + $dir);
    }
    $near = null;
    foreach (array_keys($seasons) as $n)
    {
        if ($dir > 0 ? $n > $s && $near === null : $n < $s)
            $near = $n;
    }
    if ($near === null)
    {
        aio_log("flip: $from, " . ($dir > 0 ? 'last' : 'first') . ' episode of the series, nothing');
        return null;
    }
    $ne = $dir > 0 ? 1 : $seasons[$near];
    aio_log("flip: $from -> " . sprintf('S%02dE%02d', $near, $ne) . ', ' . ($dir > 0 ? 'next' : 'previous') . ' season');
    return array($near, $ne);
}

// gc_flip (d = prev|next) of a live list of an episode: the list of the
// episode before or after it replaces the screen, as aio_finish does: "Back"
// goes to the card, the old rid is outdated. Cursor on the release of the
// current row (with the episode first), else on the first. An error: a dialog, the screen and the
// state stay as they were.
function aio_flip($in)
{
    $t0 = microtime(true);
    $st = Aio::$state;
    $d = isset($in->d) && is_string($in->d) ? $in->d : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || ($d !== 'prev' && $d !== 'next'))
    {
        aio_log('flip: not a live list or a bad key, nothing');
        return null;
    }
    if ($st['e'] < 1)
    {
        aio_log('flip: a movie, nothing');
        return null;
    }
    $to = aio_flip_ep($st['movie']['seasons'], $st['s'], $st['e'], $d === 'next' ? 1 : -1);
    if (!$to)
        return null;
    list($s, $e) = $to;
    list($cur) = aio_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    $hash = $st['rows'][$cur]['hash'];
    $mv = $st['movie'];
    $ep = sprintf('S%02dE%02d', $s, $e);
    $r = aio_relist($st, $s, $e, $hash, $t0);
    if (isset($r['error']))
    {
        aio_log(sprintf('flip: %s -> %s failed: %s, %.2f s, the screen kept', aio_ep_tag($st), $ep, $r['error'],
            microtime(true) - $t0));
        return aio_relist_dialog(aio_tr($st['lang'], 'next_failed', $ep), $st['lang'], $r);
    }
    $ns = $r['st'];
    Aio::$state = $ns;
    aio_log(sprintf('flip: %s -> %s, screen replaced, %d rows, row %d (%s), %.2f s', aio_ep_tag($st), $ep, count($ns['rows']),
        $r['sel'], $r['by'], microtime(true) - $t0));
    return aio_replace_act("streams:{$ns['rid']}", aio_ep_name($mv, $s, $e));
}

// gc_refresh (MENU "Refresh") of a live list, a movie or an episode: the same
// request anew, the screen replaced as by aio_flip, cursor on the same
// release, else the first row. An error: a dialog, the screen and the state
// stay as they were.
function aio_refresh($in)
{
    $t0 = microtime(true);
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('refresh: not a live list, nothing');
        return null;
    }
    list($cur) = aio_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    $r = aio_relist($st, $st['s'], $st['e'], $st['rows'][$cur]['hash'], $t0);
    if (isset($r['error']))
    {
        aio_log(sprintf('refresh: %s failed: %s, %.2f s, the screen kept', aio_ep_tag($st), $r['error'],
            microtime(true) - $t0));
        return aio_relist_dialog(aio_tr($st['lang'], 'refresh_failed'), $st['lang'], $r);
    }
    $ns = $r['st'];
    Aio::$state = $ns;
    aio_log(sprintf('refresh: %s, screen replaced, %d -> %d rows, row %d -> %d (%s), %.2f s', aio_ep_tag($st),
        count($st['rows']), count($ns['rows']), $cur, $r['sel'], $r['by'], microtime(true) - $t0));
    return aio_replace_act("streams:{$ns['rid']}", aio_ep_name($ns['movie'], $ns['s'], $ns['e']));
}

// --- "Choose episode" in MENU of an episode list (0.28.0): the shell's own
// seasons and episodes screens (choose_season_episode of shell_ext, as Online
// movies has it); ENTER on an episode there runs our result_action, gc_chosen,
// with chosen_s, chosen_e and se_chosen_manually added to its params.

// gc_choose of a live list of an episode -> the shell's seasons screen.
// No chosen_s/chosen_e (the shell would pick the episode itself), no
// season_filter (AIOStreams does not say which seasons have releases).
function aio_choose($in)
{
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('choose: not a live list');
        return aio_error('err_list_expired');
    }
    if ($st['e'] < 1)
    {
        aio_log('choose: a movie, nothing');
        return null;
    }
    // The cursor of the list for gc_chosen: its parent_sel_state is the shell's.
    Aio::$state['gc'] = aio_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    aio_log('choose: ' . aio_ep_tag($st) . " of list {$st['rid']}, row " . Aio::$state['gc'][0] .
        ' -> seasons screen of the shell');
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => 'shell_ext',
        GuiAction::data => null,
        GuiAction::params => array(
            'handler_id' => 'entry',
            'control_id' => 'choose_season_episode',
            'result_action' => json_encode(aio_gc_act('gc_chosen', $st['rid']))));
}

// chosen_s / chosen_e of the shell: an int or a string of digits, >= 1.
// -> the number or 0.
function aio_chosen_num($in, $k, $re)
{
    $v = isset($in->$k) ? $in->$k : null;
    if (is_int($v))
        $v = strval($v);
    return is_string($v) && preg_match($re, $v) ? intval($v) : 0;
}

// gc_chosen: the list of the episode chosen replaces the list and the two
// screens of the shell over it ("Back" goes to the card), as aio_flip: the
// request tt:s:e, tracks by hash, cursor on the release of the list. The same
// episode: just anew. An error: a dialog over the episodes screen, the list
// and the state as they were.
function aio_chosen($in)
{
    $t0 = microtime(true);
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('pick: not a live list');
        return aio_error('err_list_expired');
    }
    $s = aio_chosen_num($in, 'chosen_s', '/^[1-9][0-9]{0,3}$/D');
    $e = aio_chosen_num($in, 'chosen_e', '/^[1-9][0-9]{0,4}$/D');
    if ($st['e'] < 1 || $s < 1 || $e < 1)
    {
        aio_log('pick: ' . ($st['e'] < 1 ? 'a movie' : 'no episode chosen (' .
            aio_cut(json_encode(array(isset($in->chosen_s) ? $in->chosen_s : null,
            isset($in->chosen_e) ? $in->chosen_e : null)), 100) . ')') . ', nothing');
        return null;
    }
    $how = isset($in->se_chosen_manually) && is_scalar($in->se_chosen_manually) ?
        aio_cut(strval($in->se_chosen_manually), 10) : '-';
    list($cur) = aio_gc_pos($st, null);
    $ep = sprintf('S%02dE%02d', $s, $e);
    $r = aio_relist($st, $s, $e, $st['rows'][$cur]['hash'], $t0);
    if (isset($r['error']))
    {
        aio_log(sprintf('pick: %s -> %s (manually %s) failed: %s, %.2f s, the screen kept', aio_ep_tag($st), $ep, $how,
            $r['error'], microtime(true) - $t0));
        return aio_relist_dialog(aio_tr($st['lang'], 'next_failed', $ep), $st['lang'], $r);
    }
    $ns = $r['st'];
    Aio::$state = $ns;
    aio_log(sprintf('pick: %s -> %s (manually %s), screen replaced, %d rows, row %d (%s), %.2f s', aio_ep_tag($st), $ep, $how,
        count($ns['rows']), $r['sel'], $r['by'], microtime(true) - $t0));
    return aio_replace_act("streams:{$ns['rid']}", aio_ep_name($ns['movie'], $s, $e), 3);
}

// --- "Download in app" in MENU of the list (0.29.0): the magnet of the row
// to the system app chooser (torrent clients, remote clients, TorrServe).

// gc_dl of row i of a live list -> launch_ext_app of an intent: URI without a
// package (no -p: old firmware lacks it). The error action runs when no app
// takes magnet or the shell keeps focus for delay s (delay 0 raced the
// chooser and showed the error over it, device 08.10.2026).
function aio_download($in)
{
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('download: not a live list');
        return aio_error('err_list_expired');
    }
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
    if (!isset($st['rows'][$i]) || $st['rows'][$i]['hash'] === '')
    {
        aio_log('download: no row with infoHash, nothing');
        return null;
    }
    list($mg, $n, $src) = aio_magnet($st['rows'][$i]);
    // Trackers are not logged: a private one carries a passkey.
    aio_log('download: ' . aio_ep_tag($st) . ", row $i, hash {$st['rows'][$i]['hash']}, $n trackers ($src)" .
        ' -> app chooser');
    return array(
        GuiAction::handler_string_id => LAUNCH_EXT_APP_ACTION_ID,
        GuiAction::data => array(
            LaunchExtAppActionData::cmd => "am start 'intent:" . substr($mg, 7) .
                "#Intent;scheme=magnet;action=android.intent.action.VIEW;end'",
            LaunchExtAppActionData::ensure_playback_stopped => false,
            LaunchExtAppActionData::delay => 10,
            LaunchExtAppActionData::package => '',
            LaunchExtAppActionData::msg_toast => null,
            LaunchExtAppActionData::error_action => aio_error('dl_no_app', '', false, false)));
}

// The list of S$s E$e (-1 -1: the movie) of the card of $st anew, the request
// of play_action. Tracks of the old list by hash first: JacRed then asks only
// for the rest. Cursor on release $hash (an episode: with it in its file
// first), else the first row. -> array('error' => key, 'detail' => text) of
// aio_fetch or array('st' => new state, 'sel' => row, 'by' => why that row).
function aio_relist($st, $s, $e, $hash, $t0)
{
    $mv = $st['movie'];
    // From the list screen the player may be near: no detour to the settings.
    $res = Aio::$settings['base'] === '' ?
        array('error' => Aio::$settings['own'] ? 'err_set_address_own' : 'err_set_address', 'detail' => '') :
        aio_fetch(Aio::$settings['base'], $mv['imdb'] . ($mv['series'] ? ":$s:$e" : ''), $mv['series']);
    if (isset($res['error']))
        return $res;
    $rows = aio_carry_tracks($res['rows'], $st['rows']);
    if (AIO_JACRED)
        $rows = aio_jacred($rows, $mv, $s, $t0);

    // The release with the episode in its file, as aio_next: a release not
    // in the cache has its hash in every reply, even a pack of another season.
    $sel = -1;
    $by = 'no such release';
    foreach ($rows as $i => $r)
    {
        if ($hash === '' || $r['hash'] !== $hash)
            continue;
        if ($e < 1 || aio_has_ep($r, $s, $e))
        {
            $sel = $i;
            $by = $e < 1 ? 'same release' : 'same release with the episode';
            break;
        }
        if ($sel < 0)
        {
            $sel = $i;
            $by = 'same release, episode not in its file';
        }
    }
    $sel = max(0, $sel);
    $ns = aio_list_state($mv, $s, $e, $rows, $st['lang']);
    $ns['gc'] = aio_gc_cursor($sel, count($rows));
    $ns['gc_new'] = true;
    return array('st' => $ns, 'sel' => $sel, 'by' => $by);
}

// The dialog of a failed aio_relist: the error, its detail, OK.
function aio_relist_dialog($title, $lang, $res)
{
    return aio_dialog($title, array_merge(aio_dialog_lines(aio_tr($lang, $res['error'])),
        aio_dialog_lines($res['detail'])),
        array('OK' => aio_close_and(null)));
}

// --- Lazy episodes of the playlist (proven on the device 05.10.2026): the
// player's get_vod_stream_url gets an action, not a URL. Only
// handle_user_input of our own plugin works there (a new vod_play or a
// dialog from it); show_dialog, stop_playback or "error" right in
// error_action hang the player.

function aio_movie_path($key)
{
    $dir = isset(DuneSystem::$properties['tmp_dir_path']) ? strval(DuneSystem::$properties['tmp_dir_path']) : '';
    return $dir !== '' ? rtrim($dir, '/') . "/movie_$key.json" : '';
}

// Card data of a playlist with lazy episodes: memory and tmp_dir (RAM,
// cleared by a reboot), so that a restarted php_server still finds it.
function aio_movie_save($mv, $lang)
{
    $key = $mv['dune_id'];
    $d = array('movie' => $mv, 'lang' => $lang);
    Aio::$movies[$key] = $d;
    $path = aio_movie_path($key);
    if ($path === '' || (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) ||
        file_put_contents($path, json_encode($d)) === false)
        aio_log('movie: not saved to tmp_dir');
}

// -> array('movie' => as aio_movie, 'lang' => ...) or null.
function aio_movie_load($key)
{
    if (isset(Aio::$movies[$key]))
        return Aio::$movies[$key];
    $path = aio_movie_path($key);
    $d = $path !== '' && is_file($path) ? json_decode(strval(file_get_contents($path)), true) : null;
    $m = is_array($d) && isset($d['movie']) && is_array($d['movie']) ? $d['movie'] : null;
    if (!$m || aio_str($m, 'dune_id') !== $key || !preg_match('/^tt[0-9]{1,10}$/', aio_str($m, 'imdb')))
        return null;
    $mv = array('series' => true, 'seasons' => array());
    foreach (array('imdb', 'dune_id', 'title', 'native', 'year', 'poster', 'fanart', 'rate_imdb') as $f)
        $mv[$f] = aio_str($m, $f);
    foreach (aio_arr($m, 'seasons') as $n => $c)
    {
        if (intval($n) >= 1 && is_int($c) && $c >= 1)
            $mv['seasons'][intval($n)] = $c;
    }
    ksort($mv['seasons']);
    $r = array('movie' => $mv, 'lang' => aio_str($d, 'lang'));
    Aio::$movies[$key] = $r;
    return $r;
}

// get_vod_stream_url of a lazy episode -> error_action.
function aio_lazy_action($pb)
{
    if (!is_string($pb) || !preg_match('/^aio_next:([0-9a-f]{24}):([1-9][0-9]{0,3}):([1-9][0-9]{0,4}):([0-9a-f]{40})$/D',
        $pb, $m))
    {
        aio_log('lazy: bad playback_url ' . (is_string($pb) ? aio_cut(aio_mask($pb), 100) : gettype($pb)));
        return aio_error('err_list_expired', '', true);
    }
    aio_log("lazy: S{$m[2]}E{$m[3]} hash={$m[4]} -> handle_user_input next");
    return aio_input('next', array('k' => $m[1], 's' => $m[2], 'e' => $m[3], 'h' => $m[4]));
}

// "Episode SxEy did not load": the error and "Stop" only.
function aio_next_failed($lang, $ep, $key, $detail = '')
{
    aio_log("next: -> dialog: $key" . ($detail !== '' ? " ($detail)" : ''));
    return aio_dialog(aio_tr($lang, 'next_failed', $ep),
        array_merge(aio_dialog_lines(aio_tr($lang, $key)), aio_dialog_lines($detail)),
        array(aio_tr($lang, 'next_stop') => aio_input('next_stop')));
}

// Params k, s, e, h of next/next_pick -> array(key, s, e, hash) or null.
function aio_next_params($in)
{
    $p = array();
    foreach (array('k' => '/^[0-9a-f]{24}$/D', 's' => '/^[1-9][0-9]{0,3}$/D', 'e' => '/^[1-9][0-9]{0,4}$/D',
        'h' => '/^[0-9a-f]{40}$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
            return null;
        $p[] = $k === 's' || $k === 'e' ? intval($in->$k) : $in->$k;
    }
    return $p;
}

// Streams of the episode -> array('st' => list state) or array('error' =>
// key, 'detail' => text); 'lang' in both.
function aio_next_fetch($key, $s, $e)
{
    $saved = aio_movie_load($key);
    if (!$saved)
        return array('error' => 'err_list_expired', 'detail' => '', 'lang' => '');
    $mv = $saved['movie'];
    // Under the player: only the error with the way to the settings.
    if (Aio::$settings['base'] === '')
        return array('error' => Aio::$settings['own'] ? 'err_set_address_own' : 'err_set_address', 'detail' => '',
            'lang' => $saved['lang']);
    $res = aio_fetch(Aio::$settings['base'], "{$mv['imdb']}:$s:$e", true);
    if (isset($res['error']))
        return array('error' => $res['error'], 'detail' => $res['detail'], 'lang' => $saved['lang']);
    return array('lang' => $saved['lang'], 'st' => aio_list_state($mv, $s, $e, $res['rows'], $saved['lang']));
}

// The file of the row has the episode. Seasons may be absent (not parsed).
function aio_has_ep($r, $s, $e)
{
    return in_array($e, $r['pf_episodes'], true) && ($r['pf_seasons'] === null || in_array($s, $r['pf_seasons'], true));
}

// A lazy episode: the same release (infoHash), cached, with this episode in
// its file -> vod_play of it; else the same release as a P2P stream (no URL)
// -> through TorrServer, the file of the episode in the pack (torrserver.php);
// else the dialog "not in this release". Not bingeGroup: one value for many
// releases, a silent change of the voice.
function aio_next($in)
{
    // Language of the dialogs without a saved card: of the input, else of
    // the card by a valid key, else English (as aio_tr).
    $lang = isset($in->lang) && is_string($in->lang) ? $in->lang : '';
    if ($lang === '' && isset($in->k) && is_string($in->k) && preg_match('/^[0-9a-f]{24}$/D', $in->k))
    {
        $saved = aio_movie_load($in->k);
        $lang = $saved ? $saved['lang'] : '';
    }
    $p = aio_next_params($in);
    if (!$p)
    {
        aio_log('next: bad params');
        return aio_next_failed($lang, '', 'err_list_expired');
    }
    list($key, $s, $e, $hash) = $p;
    $ep = sprintf('S%02dE%02d', $s, $e);
    aio_log("next: $ep hash=$hash");
    $res = aio_next_fetch($key, $s, $e);
    if (isset($res['error']))
        return aio_next_failed($res['lang'] !== '' ? $res['lang'] : $lang, $ep, $res['error'], $res['detail']);
    $st = $res['st'];
    $same = 0;
    $cached = 0;
    $found = null;
    $sel = -1;
    $p2p = -1;
    foreach ($st['rows'] as $i => $r)
    {
        if ($r['hash'] !== $hash)
            continue;
        $same++;
        if ($r['url'] === '' && $p2p < 0)
            $p2p = $i;
        if ($r['cached'] !== true)
            continue;
        $cached++;
        if (!$found && aio_has_ep($r, $s, $e))
        {
            $found = $r;
            $sel = $i;
        }
    }
    aio_log("next: $ep: " . count($st['rows']) . " rows, $same with the hash, $cached of them cached, " .
        ($found ? 'found' : "none with $ep") . ($p2p >= 0 ? ", P2P row $p2p" : ''));
    $from = Aio::$playing ? Aio::$playing['from'] : '';
    if ($found)
    {
        aio_playing($from, $st, $sel);
        return aio_play($st, $found, 0);
    }
    if ($p2p >= 0)
        return aio_ts_open($st, $p2p, 0, $from, true);
    return aio_next_missing($st, $hash);
}

// The dialog "S2E3 is not in this release" over the stopped player of a lazy
// episode of list state $st: "Choose a release" (its list) and "Stop".
function aio_next_missing($st, $hash)
{
    Aio::$next = $st;
    $ep = sprintf('S%02dE%02d', $st['s'], $st['e']);
    aio_log("next: -> dialog: $ep is not in this release");
    $lang = $st['lang'];
    return aio_dialog(aio_tr($lang, 'next_no_episode', $ep), array(), array(
        aio_tr($lang, 'next_pick') => aio_input('next_pick', array('k' => $st['movie']['dune_id'], 's' => strval($st['s']),
            'e' => strval($st['e']), 'h' => $hash, 'rid' => $st['rid'])),
        aio_tr($lang, 'next_stop') => aio_input('next_stop')));
}

// "Choose a release": the list of the episode (fetched again after a
// php_server restart).
function aio_next_pick($in)
{
    $t0 = microtime(true);
    $st = Aio::$next;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        $p = aio_next_params($in);
        $res = $p ? aio_next_fetch($p[0], $p[1], $p[2]) : array('error' => 'err_list_expired', 'detail' => '');
        if (isset($res['error']))
            return aio_close_and(aio_error($res['error'], $res['detail']));
        $st = $res['st'];
    }
    Aio::$next = null;
    aio_log(sprintf('next: -> list of S%02dE%02d', $st['s'], $st['e']));
    return aio_close_and(aio_open_list($st, $t0));
}
