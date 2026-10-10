<?php
// Playback: the stream check before the player, vod_play linked to the Dune
// card, the playlist of the season with lazy episodes, back from the player to
// the list, episodes by the left and right keys.
// Reads and writes Aio (state, next, playing, movies); reads Aio::$settings,
// Aio::$t0. Functions are global; the require order is in main.php.

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
// $lazy: a lazy episode under the player (aio_next), a placeholder gets its
// dialog there. $pre: aio_precheck() of the row's URL already done.
function aio_play($st, $r, $pos, $ts = null, $lazy = false, $pre = null)
{
    $mv = $st['movie'];
    $s = $st['s'];
    $e = $st['e'];
    $name = aio_ep_name($mv, $s, $e);
    $url = $ts ? $ts['url'] : $r['url'];
    // A Debrid placeholder video (not cached, a limit), linked, would put
    // its progress on the card: it is not played at all. A redirect to the
    // Debrid CDN means the file is ready: linked whatever "cached" says.
    // Else linked only if cached; unknown status (null) is not. TorrServer
    // has no placeholder.
    $ready = false;
    if (!$ts && preg_match('~^https?://~i', $url))
    {
        if (!$pre)
            $pre = aio_precheck($url);
        if ($pre['class'] === 'placeholder')
            return $lazy ? aio_next_failed($st['lang'], sprintf('S%02dE%02d', $s, $e), $pre['key'], $pre['detail']) :
                aio_error($pre['key'], $pre['detail']);
        if ($pre['class'] === 'stream')
        {
            $url = $pre['url'];
            $ready = true;
        }
    }
    $linked = $mv['dune_id'] !== '' && $r['hash'] !== '' && ($ts || $ready || $r['cached'] === true);
    $ser = aio_vod_item($mv, $s, $e, $url, $linked ? $r['hash'] : '');
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

// --- Stream check before the player: one GET of the row's URL, no
// redirects followed. Debrid add-ons redirect to the CDN of the service when
// the file is ready, else to a placeholder video of their own host:
// AIOStreams /static/<file>.mp4, Torrentio /videos/<file>.mp4, MediaFusion
// /static/exceptions/<file>.mp4, StremThru /v0/store/_/static/<file>.mp4.
// The add-ons of ElfHosted redirect to slate.elfhosted.com instead, the
// reason in its title and body.
// (Comet sends its placeholder as the body, 200: not told from a stream.)

define('AIO_PRE_CONNECT', 5);
define('AIO_PRE_TOTAL', 30);
// handle_user_input and play_action have 60 s: the check gets what is left
// of this many seconds of the operation, nothing below AIO_PRE_MIN.
define('AIO_OP_BUDGET', 55);
define('AIO_PRE_MIN', 3);
// A longer Location is not taken for a stream URL.
define('AIO_PRE_LOC_MAX', 4096);

// HTTP code $code and Location $loc of stream URL $url -> array('class' =>
// 'stream' (with 'url' => the Location), 'placeholder' (with 'file' => its
// name, or of aio_pre_slate()) or 'unclear' (with 'why')). Only an absolute
// http(s) Location of another host, not the slate, is a stream.
function aio_pre_class($url, $code, $loc)
{
    if ($code < 300 || $code > 399 || $loc === '')
        return array('class' => 'unclear', 'why' => "HTTP $code" . ($code >= 300 && $code <= 399 ? ', no Location' : ''));
    // The slate before the limits of a stream URL: a long one is still a placeholder.
    $p = preg_match('~^https?://~i', $loc) ? parse_url($loc) : false;
    if (is_array($p) && isset($p['host']) && strtolower($p['host']) === 'slate.elfhosted.com' &&
        !isset($p['user']) && !isset($p['pass']))
        return aio_pre_slate(isset($p['query']) ? $p['query'] : '');
    if (strlen($loc) > AIO_PRE_LOC_MAX || preg_match('/[^\x21-\x7e]/', $loc))
        return array('class' => 'unclear', 'why' => "HTTP $code, Location not a URL");
    $self = strtolower(strval(parse_url($url, PHP_URL_HOST)));
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $loc))
    {
        $p = parse_url($loc);
        if (!preg_match('~^https?://~i', $loc) || !is_array($p) || !isset($p['host']) || $p['host'] === '' ||
            isset($p['user']) || isset($p['pass']))
            return array('class' => 'unclear', 'why' => "HTTP $code, Location not http(s)");
        $host = strtolower($p['host']);
        if ($host !== $self)
            return array('class' => 'stream', 'url' => $loc, 'host' => $host);
        $path = isset($p['path']) ? $p['path'] : '';
    }
    else if (substr($loc, 0, 2) === '//')
        return array('class' => 'unclear', 'why' => "HTTP $code, Location not http(s)");
    else
        $path = preg_replace('/[?#].*$/s', '', $loc);
    if (preg_match('~(?:^|/)(?:static|videos)/(?:.*/)?([^/]+\.mp4)$~i', $path, $m))
        return array('class' => 'placeholder', 'file' => $m[1]);
    return array('class' => 'unclear', 'why' => "HTTP $code, same host, not a placeholder");
}

// Query of a slate.elfhosted.com Location -> a placeholder with 'slate' =>
// its title (printable, no leading "%": not a %tr% key of the shell, cut)
// and 'infringing' => title or body tell of a
// refusal as infringing / for legal reasons. Not parse_str: magic quotes.
function aio_pre_slate($query)
{
    $q = array('title' => '', 'body' => '');
    foreach (explode('&', $query) as $kv)
    {
        $kv = explode('=', $kv, 2);
        if (isset($kv[1]) && isset($q[$kv[0]]) && $q[$kv[0]] === '')
            $q[$kv[0]] = urldecode($kv[1]);
    }
    $why = $q['title'] . ' ' . $q['body'];
    $title = preg_replace('/[\x00-\x20\x7f]+/', ' ', $q['title']);
    if (!preg_match('//u', $title))
        $title = preg_replace('/[\x80-\xff]+/', '', $title);
    $title = rtrim(ltrim($title, '% '));
    return array('class' => 'placeholder', 'slate' => aio_cut($title, 100),
        'infringing' => stripos($why, 'infring') !== false || stripos($why, 'legal reason') !== false);
}

// The check of stream URL $url -> aio_pre_class() and, for a placeholder,
// 'key' and 'detail' of its message (by the file name).
function aio_precheck($url)
{
    $left = Aio::$t0 > 0 ? AIO_OP_BUDGET - (microtime(true) - Aio::$t0) : AIO_PRE_TOTAL;
    $total = (int) min(AIO_PRE_TOTAL, floor($left));
    if ($total < AIO_PRE_MIN)
    {
        aio_log(sprintf('precheck: skipped, the operation took %.1f s', AIO_OP_BUDGET - $left));
        return array('class' => 'unclear', 'why' => 'no time');
    }
    $t = microtime(true);
    $err = '';
    list($code, $loc) = aio_http_peek($url, $err, min(AIO_PRE_CONNECT, $total), $total);
    $t = microtime(true) - $t;
    $pre = $err !== '' ? array('class' => 'unclear', 'why' => $err) : aio_pre_class($url, $code, $loc);
    // Not the URLs: the row's and the CDN's carry keys and tokens.
    if ($pre['class'] === 'stream')
        aio_log(sprintf('precheck: stream, HTTP %d -> %s, %.2f s', $code, $pre['host'], $t));
    else if ($pre['class'] === 'placeholder' && isset($pre['slate']))
    {
        // Not its query: sig and ts.
        aio_log(sprintf('precheck: placeholder, HTTP %d, slate: %s, %.2f s', $code,
            $pre['slate'] !== '' ? $pre['slate'] : '-', $t));
        $pre['key'] = $pre['infringing'] ? 'err_pre_infringing' : 'err_pre_placeholder';
        $pre['detail'] = $pre['infringing'] ? '' : ($pre['slate'] !== '' ? $pre['slate'] : 'slate');
    }
    else if ($pre['class'] === 'placeholder')
    {
        aio_log(sprintf('precheck: placeholder, HTTP %d, %s, %.2f s', $code, aio_cut($pre['file'], 100), $t));
        $f = strtolower($pre['file']);
        // Infringing: MediaFusion content_infringing.mp4, Torrentio
        // failed_infringement_v3.mp4, StremThru 451.mp4, AIOStreams
        // unavailable_for_legal_reasons.mp4. MediaFusion: torrent_not_downloaded.mp4.
        if (strpos($f, 'infring') !== false || strpos($f, 'legal') !== false || $f === '451.mp4')
            $pre['key'] = 'err_pre_infringing';
        else if (strpos($f, 'downloading') !== false || strpos($f, 'not_downloaded') !== false)
            $pre['key'] = 'err_pre_downloading';
        else
            $pre['key'] = strpos($f, 'limit') !== false ? 'err_pre_limit' : 'err_pre_placeholder';
        $pre['detail'] = $pre['key'] === 'err_pre_placeholder' ? aio_cut($pre['file'], 100) : '';
    }
    else
        aio_log(sprintf('precheck: unclear (%s), %.2f s', $pre['why'], $t));
    return $pre;
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
    $ns = aio_gc_put_cursor($ns, $sel);
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
    $ep = sprintf('S%02dE%02d', $s, $e);
    return aio_relist_screen($st, $s, $e, $cur, 'flip: ' . aio_ep_tag($st) . " -> $ep", false,
        aio_tr($st['lang'], 'next_failed', $ep), 1);
}

// gc_refresh (MENU "Refresh") of a live list, a movie or an episode: the same
// request anew, the screen replaced as by aio_flip, cursor on the same
// release, else the first row. An error: a dialog, the screen and the state
// stay as they were.
function aio_refresh($in)
{
    $st = Aio::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        aio_log('refresh: not a live list, nothing');
        return null;
    }
    list($cur) = aio_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    return aio_relist_screen($st, $st['s'], $st['e'], $cur, 'refresh: ' . aio_ep_tag($st), true,
        aio_tr($st['lang'], 'refresh_failed'), 1);
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
    return aio_relist_screen($st, $s, $e, $cur, 'pick: ' . aio_ep_tag($st) . " -> $ep (manually $how)", false,
        aio_tr($st['lang'], 'next_failed', $ep), 3);
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
function aio_relist($st, $s, $e, $hash)
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
        $rows = aio_jacred($rows, $mv, $s);

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
    $ns = aio_gc_put_cursor(aio_list_state($mv, $s, $e, $rows, $st['lang']), $sel);
    return array('st' => $ns, 'sel' => $sel, 'by' => $by);
}

// The dialog of a failed aio_relist: the error, its detail, OK.
function aio_relist_dialog($title, $lang, $res)
{
    return aio_dialog($title, array_merge(aio_dialog_lines(aio_tr($lang, $res['error'])),
        aio_dialog_lines($res['detail'])),
        array('OK' => aio_close_and(null)));
}

// The list of S$s E$e of the card of live list $st anew (aio_relist, cursor
// on the release of row $cur) replaces the screen and $erase screens over it
// (aio_replace_act); an error: dialog $title, the screen and the state kept.
// $tag starts the log lines; $log_old: they show the old rows and row too.
function aio_relist_screen($st, $s, $e, $cur, $tag, $log_old, $title, $erase)
{
    $r = aio_relist($st, $s, $e, $st['rows'][$cur]['hash']);
    if (isset($r['error']))
    {
        aio_log(sprintf('%s failed: %s, %.2f s, the screen kept', $tag, $r['error'], microtime(true) - Aio::$t0));
        return aio_relist_dialog($title, $st['lang'], $r);
    }
    $ns = $r['st'];
    Aio::$state = $ns;
    aio_log(sprintf('%s, screen replaced, %s rows, row %s (%s), %.2f s', $tag,
        ($log_old ? count($st['rows']) . ' -> ' : '') . count($ns['rows']), ($log_old ? "$cur -> " : '') . $r['sel'], $r['by'],
        microtime(true) - Aio::$t0));
    return aio_replace_act("streams:{$ns['rid']}", aio_ep_name($ns['movie'], $ns['s'], $ns['e']), $erase);
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

// The rows of release $hash in $rows (indexes, -1: none): 'deb' the row with a
// URL, a cached one first, with episode $s/$e in its file ($e < 1: any);
// 'p2p' the first P2P row (no URL); 'any' the first row; 'rows' rows of the
// release, 'cached' of them cached with a URL. The check of a row not cached
// (aio_deb_precheck) and TorrServer are the caller's (aio_play_action resume,
// aio_next).
function aio_release_pick($rows, $hash, $s, $e)
{
    $p = array('deb' => -1, 'p2p' => -1, 'any' => -1, 'rows' => 0, 'cached' => 0);
    foreach ($rows as $i => $r)
    {
        if ($r['hash'] !== $hash)
            continue;
        $p['rows']++;
        if ($p['any'] < 0)
            $p['any'] = $i;
        if ($r['url'] === '')
        {
            if ($p['p2p'] < 0)
                $p['p2p'] = $i;
            continue;
        }
        if ($r['cached'] === true)
            $p['cached']++;
        if (($e < 1 || aio_has_ep($r, $s, $e)) &&
            ($p['deb'] < 0 || ($r['cached'] === true && $rows[$p['deb']]['cached'] !== true)))
            $p['deb'] = $i;
    }
    return $p;
}

// aio_precheck() of the URL of Debrid row $r; a URL not http(s) is unclear.
function aio_deb_precheck($r)
{
    return preg_match('~^https?://~i', $r['url']) ? aio_precheck($r['url']) :
        array('class' => 'unclear', 'why' => 'not http(s)');
}

// A lazy episode: the same release (infoHash) with a URL and this episode in
// its file, a cached row first -> vod_play of it (aio_play checks it: a
// placeholder gets its dialog). A row not cached plays only if the check finds
// a stream (the CDN): else, as for no such row, the same release as a P2P
// stream (no URL) -> through TorrServer, the file of the episode in the pack
// (torrserver.php); else the dialog "not in this release" (a placeholder: its
// own). Not bingeGroup: one value for many releases, a silent change of the voice.
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
    $pick = aio_release_pick($st['rows'], $hash, $s, $e);
    $sel = $pick['deb'];
    $found = $sel >= 0 ? $st['rows'][$sel] : null;
    // Only a P2P row goes to TorrServer here, its own echo: a dialog under the player.
    $p2p = $pick['p2p'];
    aio_log("next: $ep: " . count($st['rows']) . " rows, {$pick['rows']} with the hash, {$pick['cached']} of them cached, " .
        ($found ? ($found['cached'] === true ? 'found' : 'found not cached') : "none with $ep") .
        ($p2p >= 0 ? ", P2P row $p2p" : ''));
    $from = Aio::$playing ? Aio::$playing['from'] : '';
    $pre = null;
    if ($found && $found['cached'] !== true)
    {
        $pre = aio_deb_precheck($found);
        // A placeholder without P2P: its dialog (aio_play), not "not in this release".
        if ($pre['class'] !== 'stream' && ($p2p >= 0 || $pre['class'] !== 'placeholder'))
        {
            aio_log("next: $ep: row $sel not cached, no stream from Debrid");
            $found = null;
        }
    }
    if ($found)
    {
        $a = aio_play($st, $found, 0, null, true, $pre);
        // A placeholder: the list under the player stays that of the episode played.
        if ($a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
            aio_playing($from, $st, $sel);
        return $a;
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
    return aio_close_and(aio_open_list($st));
}
