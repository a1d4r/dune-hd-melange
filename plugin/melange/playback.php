<?php
// Playback: the stream check before the player, vod_play linked to the Dune
// card, the playlist of the season with lazy episodes, back from the player to
// the list, episodes by the left and right keys.
// Reads and writes Mel (state, next, playing, movies); reads Mel::$settings,
// Mel::$t0. Functions are global; the require order is in main.php.
// Types in the PHPDoc (MelState and others): phpstan-types.neon of the repository.

// A season with more episodes (by season_numbers) plays as one episode: a
// playlist item takes ~1.7 KB of memory (limit 128 MB).
define('MEL_EPISODES_MAX', 500);

// --- Playback.

// "S2 E4": the number of an episode for people (the log keeps "S02E04").
/**
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_se($s, $e)
{
    return "S$s E$e";
}

// "Title · S2 E4" for an episode, the title for a movie.
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_ep_name($mv, $s, $e)
{
    return $mv['title'] . ($e > 0 ? ' · ' . mel_ep_code($mv, $s, $e) : '');
}

// "S2 E4" of an episode; of an anime numbered on with its number of the meta (mel_cin_n): "S21 E32 (923)".
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_ep_code($mv, $s, $e)
{
    $n = mel_cin_n($mv['cin'], $s, $e);
    return mel_se($s, $e) . ($n > 0 ? " ($n)" : '');
}

// The name of a playlist item: "S2 E4 · Зелёный свет" in a playlist of several
// (INFO shows the title of vod_info above it), alone mel_ep_name and the title:
// "Во все тяжкие · S2 E4 · Зелёный свет"; the title of the episode only of a
// list of the own card (titles), none without it. A movie: its title.
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @param bool $list
 * @return string
 */
function mel_ser_name($mv, $s, $e, $list)
{
    $t = $e > 0 && isset($mv['titles']["$s:$e"]) ? ' · ' . $mv['titles']["$s:$e"] : '';
    return ($list && $e > 0 ? mel_ep_code($mv, $s, $e) : mel_ep_name($mv, $s, $e)) . $t;
}

// description of vod_info, for INFO of the player (as Online movies): a line
// of the release - video (resolution, picture), audio, size, voices, as the
// card of the list says them, empty parts left out. No annotation of the card.
/**
 * @param MelRow $r
 * @param string $lang
 * @return string
 */
function mel_info_desc($r, $lang)
{
    $parts = array(trim($r['resolution'] . ' ' . mel_badge_text($r['badges']['vis'])), $r['audio_full'],
        $r['size'] > 0 ? mel_size_str($r['size'], $lang) : '',
        isset($r['voices']) ? mel_voices_struct_text(mel_row_voices_struct($r), $lang, false) : '');
    return implode(' · ', array_filter(array_map('mel_clean', $parts), 'strlen'));
}

// An episode of the playlist without a URL: the player asks for it with
// get_vod_stream_url (mel_lazy_action). No recent_uid: its own vod_play has it.
// $list: in a playlist of several (mel_ser_name). $mode: 'debrid' or 'ts', the
// way the playlist was started, mel_next keeps it; then the id of the profile of
// releases of the start (v2, spec §1.7: the next episode is an operation of its
// own, it asks the same AIOStreams; none - no such suffix, the main one).
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @param string $hash
 * @param bool $list
 * @param string $mode
 * @return array<string, mixed>
 */
function mel_lazy_series($mv, $s, $e, $hash, $list, $mode)
{
    return array(
        PluginVodSeriesInfo::name => mel_ser_name($mv, $s, $e, $list),
        PluginVodSeriesInfo::playback_url => "aio_next:{$mv['dune_id']}:$s:$e:$hash:$mode" .
            (Mel::$settings['prof'] !== '' ? ':' . Mel::$settings['prof'] : ''),
        PluginVodSeriesInfo::playback_url_is_stream_url => false);
}

// A playlist item of URL $url, linked to the card by $hash ('' - unlinked);
// $list: in a playlist of several (mel_ser_name). Its recent_uid is read back
// by history.php: mel_wh_pick (the release), mel_wh_progress, mel_wh_series
// (the progress of the episodes on the own card).
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @param string $url
 * @param string $hash
 * @param bool $list
 * @return array<string, mixed>
 */
function mel_vod_item($mv, $s, $e, $url, $hash, $list)
{
    $ser = array(
        PluginVodSeriesInfo::name => mel_ser_name($mv, $s, $e, $list),
        PluginVodSeriesInfo::playback_url => $url,
        PluginVodSeriesInfo::playback_url_is_stream_url => true);
    if ($hash === '')
        return $ser;
    $ser[PluginVodSeriesInfo::recent_uid] = implode(':', array(MEL_SUP_ID, $mv['dune_id'], $s, $e, $hash));
    $mdb = array(
        FileMovieInfo::movie_id => $mv['dune_id'],
        FileMovieInfo::title => $mv['title'],
        FileMovieInfo::icon_url => $mv['poster']);
    // As ActionFactory::file_movie_info of Online movies. A firmware PHP API
    // without the ratings (r22): not sent.
    if (defined('FileMovieInfo::rate_imdb'))
        $mdb[FileMovieInfo::rate_imdb] = $mv['rate_imdb'] !== '' ? $mv['rate_imdb'] : null;
    if (defined('FileMovieInfo::rate_content'))
        $mdb[FileMovieInfo::rate_content] = null;
    if ($e > 0)
    {
        $mdb[FileMovieInfo::s] = $s;
        $mdb[FileMovieInfo::e] = $e;
    }
    $ser[PluginVodSeriesInfo::mdb_info] = $mdb;
    return $ser;
}

// What a playlist holds: season $s whole, the episodes of $others (season =>
// episode => URL), seasons ascending; after a season, E1 of the next one of
// season_numbers if it has none. -> list of array(season, episode).
/**
 * @param array<int, int> $seasons
 * @param int $s
 * @param array<int, array<int, string>> $others
 * @return list<array{int, int}>
 */
function mel_play_plan($seasons, $s, $others)
{
    $all = $others;
    $all[$s] = array_flip(range(1, $seasons[$s]));
    ksort($all);
    $plan = array();
    foreach ($all as $q => $eps)
    {
        foreach (array_keys($eps) as $i)
            $plan[] = array($q, $i);
        if (isset($seasons[$q + 1]) && !isset($all[$q + 1]))
            $plan[] = array($q + 1, 1);
    }
    return $plan;
}

// vod_play linked to the Dune card: recent_uid (prefix = our supplier id)
// gives [NN%] on the button, mdb_info the "Continue watching" row,
// recent_plugin/recent_action_params bring "Continue" back to play_action
// with wh_release and wh_mode in supplier_info. Without a dune id or infoHash: unlinked.
// A linked episode plays in a playlist of its whole season (by season_numbers),
// the others lazy, then E1 of the next season; P+/P- and the autoplay work.
// $ts (TorrServer, torrserver.php): array('url' => stream URL, 'eps' =>
// episode => URL of the other episodes of the season in the pack, 'others' =>
// season => episode => URL of the other seasons in it); with the markers of
// the settings off those go to the playlist as they are, on - lazy: a vod_play
// of its own gives each its markers (mel_next).
// $lazy: a lazy episode under the player (mel_next), a placeholder gets its
// dialog there. $pre: mel_precheck() of the row's URL already done. $from: rid
// of the screen under the player the caller notes (mel_playing; '' none: "Continue"),
// for the start by "Via <service>" of that dialog (mel_pre_failed).
// To the shell only as a reply of handle_user_input: on r24 mel_pf_check wraps it (main.php call_plugin).
/**
 * @param MelState $st
 * @param MelRow $r
 * @param int $pos
 * @param array{url: string, eps: array<int, string>, others: array<int, array<int, string>>}|null $ts
 * @param bool $lazy
 * @param MelPrecheck|null $pre
 * @param string $from
 * @return MelAction
 */
function mel_play($st, $r, $pos, $ts = null, $lazy = false, $pre = null, $from = '')
{
    $mv = $st['movie'];
    $s = $st['s'];
    $e = $st['e'];
    // A file cached on several services: the copy is chosen now, the main
    // service may have changed since the list (the page), "Via <service>" may
    // have been chosen. With $pre the row is not cached: one copy.
    if (!$ts && !$pre)
        $r = mel_row_pick($r, mel_main_svc($mv, $r));
    $url = $ts ? $ts['url'] : $r['url'];
    $mode = $ts ? 'ts' : 'debrid';
    // A Debrid placeholder video (not cached, a limit), linked, would put
    // its progress on the card: it is not played at all. A redirect to the
    // Debrid CDN means the file is ready: linked whatever "cached" says.
    // Else linked only if cached; unknown status (null) is not. TorrServer
    // has no placeholder.
    $ready = false;
    if (!$ts && preg_match('~^https?://~i', $url))
    {
        if (!$pre)
            $pre = mel_precheck($url);
        if ($pre['class'] === 'placeholder')
            return mel_pre_failed($st, $r, $pos, $lazy, $pre, $from);
        if ($pre['class'] === 'stream')
        {
            $url = $pre['url'];
            $ready = true;
        }
    }
    $linked = $mv['dune_id'] !== '' && $r['hash'] !== '' && ($ts || $ready || $r['cached'] === true);
    $seasons = $mv['seasons'];
    $season = $linked && $e > 0 && isset($seasons[$s]) && $e <= $seasons[$s] && $seasons[$s] <= MEL_EPISODES_MAX &&
        preg_match('/^[0-9a-f]{40}$/D', $r['hash']);
    $others = $ts ? $ts['others'] : array();
    $plan = $season ? mel_play_plan($seasons, $s, $others) : array();
    if (count($plan) > MEL_EPISODES_MAX)
    {
        $others = array();
        $plan = mel_play_plan($seasons, $s, $others);
    }
    // A playlist of several: the title in vod_info, "S2 E4 · <episode>" in its items.
    $list = count($plan) > 1;
    $ser = mel_vod_item($mv, $s, $e, $url, $linked ? $r['hash'] : '', $list);
    if ($e > 0)
    {
        // A stream to read only when it is a file: the CDN of Debrid or TorrServer, its URL with &play (else no stream).
        $mk = mel_markers($mv['imdb'], $s, $e, mel_row_duration($r), $ts || $ready ? $url : '', $ts ? Mel::$settings['ts_auth'] : '');
        if ($mk)
            $ser[PluginVodSeriesInfo::markers] = $mk;
    }
    $series = array($ser);
    $ndx = 0;
    if ($season)
    {
        $urls = array();
        if ($ts && !Mel::$settings['markers'])
        {
            $urls = $others;
            $urls[$s] = $ts['eps'];
        }
        $series = array();
        foreach ($plan as $n => $p)
        {
            list($q, $i) = $p;
            if ($q === $s && $i === $e)
                $ndx = $n;
            $series[] = $q === $s && $i === $e ? $ser : (isset($urls[$q][$i]) ?
                mel_vod_item($mv, $q, $i, $urls[$q][$i], $r['hash'], $list) : mel_lazy_series($mv, $q, $i, $r['hash'], $list, $mode));
        }
        mel_movie_save($mv, $st['lang']);
    }

    $info = array(
        PluginVodInfo::id => 'aio.' . ($mv['dune_id'] !== '' ? $mv['dune_id'] : $mv['imdb']),
        PluginVodInfo::name => $list ? $mv['title'] : mel_ser_name($mv, $s, $e, false),
        // The second line of INFO: the release, as the card names it.
        PluginVodInfo::sub_name => mel_clean($r['label']),
        PluginVodInfo::poster_url => $mv['poster'],
        PluginVodInfo::series => $series,
        PluginVodInfo::initial_series_ndx => $ndx,
        PluginVodInfo::description => mel_info_desc($r, $st['lang']),
        // The fields Online movies always sends (lib/vod/movie.php get_vod_info).
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => true,
        PluginVodInfo::skip_dummy_player_on_error => false);
    // wh_pos is in seconds, as "pos" of the watch history (Online movies
    // multiplies it by 1000 the same way).
    if ($pos > 0)
        $info[PluginVodInfo::initial_position_ms] = $pos * 1000;
    // TorrServer: INFO opens the window of its numbers too. The actions and the
    // timer of the player belong to ts_info.php whole (its change_behaviour
    // replaces the whole map): no other actions here.
    $info += mel_tsi_vod($r, (bool) $ts, $st['lang']);
    if ($linked)
    {
        $info[PluginVodInfo::recent_plugin] = 'shell_ext';
        $info[PluginVodInfo::recent_action_params] = array(
            'handler_id' => 'entry',
            'control_id' => 'play_recent',
            'sup_id' => MEL_SUP_ID,
            'movie_id' => $mv['dune_id'],
            'sup_data' => 'auto',
            'wh_release' => $r['hash'],
            'wh_mode' => $mode,
            'wh_prof' => Mel::$settings['prof']);
    }
    // A file cached on several services: which copy plays.
    $copy = !$ts && isset($r['copies'], $r['copy']) ? sprintf(', copy %d of %d: %s', $r['copy'] + 1, count($r['copies']),
        mel_row_svc($r)) : '';
    mel_log('play: hash=' . ($r['hash'] !== '' ? $r['hash'] : '-') .
        " s=$s e=$e pos=$pos linked=" . ($linked ? 1 : 0) . ', playlist ' . count($series) . ($ts ? ', TorrServer' : '') . $copy);
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}

// Reply $a of handle_user_input as it goes to the shell: on r24 (MEL_FW_PF_CHECK_R) every
// plugin_vod_play in it (at the top, the post_action of close_dialog_and_run, a button of a dialog)
// runs after the built-in pf_check, as the card of the Dune does before a supplier (movie_screen.php
// of shell_ext r24): plugin_name ours, no error_action. Else $a as it is. Here and not in
// mel_play/mel_ct_clip: their callers look at the vod_play itself.
/**
 * @param mixed $a
 * @return mixed
 */
function mel_pf_check($a)
{
    return is_array($a) && mel_fw_r() === MEL_FW_PF_CHECK_R ? mel_pf_wrap($a) : $a;
}

/**
 * @param array<mixed> $a
 * @return array<mixed>
 */
function mel_pf_wrap($a)
{
    if (isset($a[GuiAction::handler_string_id]) && $a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
    {
        mel_log('play: pf_check (r24)');
        return array(
            GuiAction::handler_string_id => RUN_BUILTIN_ACTION_ID,
            GuiAction::data => array(
                RunBuiltinActionData::builtin_action_id => 'pf_check',
                RunBuiltinActionData::params => array('plugin_name' => MEL_PLUGIN),
                RunBuiltinActionData::post_action => $a,
                RunBuiltinActionData::error_action => null));
    }
    foreach ($a as $k => $v)
    {
        if (is_array($v))
            $a[$k] = mel_pf_wrap($v);
    }
    return $a;
}

// The service whose copy row $r of card $mv plays: of "Via <service>" of this
// release (Mel::$via) if the row has a copy of it, else the main Debrid of the settings.
/**
 * @param MelMovie $mv
 * @param MelRow $r
 * @return string
 */
function mel_main_svc($mv, $r)
{
    $v = Mel::$via;
    if ($v && $v['k'] === $mv['dune_id'] && $v['hash'] === $r['hash'])
    {
        foreach (isset($r['copies']) ? $r['copies'] : array() as $c)
        {
            if ($c['svc'] === $v['svc'])
                return $v['svc'];
        }
    }
    return Mel::$settings['debrid_main'];
}

// A placeholder of the stream check $pre (class placeholder) of row $r of list
// $st, started at $pos ($lazy: under the player; $from: mel_play). A row of one copy: the error
// as before 0.56.0 (mel_error, mel_next_failed). A file cached on several services
// (0.56.0): the service that refused names the dialog, its reason under it; a copy
// of a service not refused on this start yet (Mel::$alt: the refused ones) - the
// button "Via <it>" (mel_pre_via), the start kept in Mel::$alt till then. OK, under
// the player "Stop", closes it.
/**
 * @param MelState $st
 * @param MelRow $r
 * @param int $pos
 * @param bool $lazy
 * @param array{class: 'placeholder', key: string, detail: string, note: string} $pre
 * @param string $from
 * @return MelAction
 */
function mel_pre_failed($st, $r, $pos, $lazy, $pre, $from)
{
    $lang = $st['lang'];
    $ep = mel_se($st['s'], $st['e']);
    $key = $pre['key'];
    $detail = $pre['detail'];
    if (!isset($r['copies'], $r['copy']))
        return $lazy ? mel_next_failed($lang, $ep, $key, $detail) : mel_error($key, $detail);
    $i = -1;
    foreach ($st['rows'] as $n => $x)
    {
        if ($x['ord'] === $r['ord'])
            $i = $n;
    }
    $svc = mel_row_svc($r);
    $a = Mel::$alt;
    $tried = $a && $a['st']['rid'] === $st['rid'] && $a['i'] === $i ? $a['tried'] : array();
    if (!in_array($svc, $tried, true))
        $tried[] = $svc;
    $other = null;
    foreach ($r['copies'] as $c)
    {
        if (!$other && $c['svc'] !== '' && !in_array($c['svc'], $tried, true))
            $other = $c;
    }
    Mel::$alt = $other && $i >= 0 ? array('st' => $st, 'i' => $i, 'pos' => $pos, 'lazy' => $lazy, 'from' => $from,
        'tried' => $tried) : null;
    mel_log("error: $key" . ($detail !== '' ? " ($detail)" : '') . sprintf(', copy %d of %d: %s, row %d -> dialog, ',
        $r['copy'] + 1, count($r['copies']), $svc, $i) . (Mel::$alt ? "via {$other['svc']} offered" : 'no other copy left'));
    $name = str_replace('{svc}', $r['service'] !== '' ? $r['service'] : $svc, mel_tr($lang, 'pre_svc_failed'));
    $lines = array_merge(mel_dialog_lines(mel_tr($lang, $key)), mel_dialog_lines($detail));
    $buttons = array();
    if (Mel::$alt)
        $buttons[str_replace('{svc}', $other['service'] !== '' ? $other['service'] : $other['svc'], mel_tr($lang, 'pre_via'))] =
            mel_input('pre_via', array('rid' => $st['rid'], 'i' => strval($i), 'svc' => $other['svc']));
    if ($lazy)
    {
        $buttons[mel_tr($lang, 'next_stop')] = mel_input('next_stop');
        return mel_dialog(mel_tr($lang, 'next_failed', $ep), array_merge(array($name), $lines), $buttons);
    }
    $buttons['OK'] = mel_close_and(null);
    return mel_dialog($name, $lines, $buttons);
}

// "Via <service>" of the dialog of mel_pre_failed (rid, i: the row, svc: the
// service of its copy): the start again on that copy (Mel::$via: the next
// episodes of the release too), noted as the caller of that start would
// (mel_playing: 'from' of Mel::$alt). Not the start of Mel::$alt (another start
// since, a php_server restart), a service refused already: "the list expired".
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_pre_via($in)
{
    $a = Mel::$alt;
    $svc = isset($in->svc) && is_string($in->svc) ? $in->svc : '';
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,4}$/D', $in->i) ? intval($in->i) : -1;
    $row = $a && isset($in->rid) && $in->rid === $a['st']['rid'] && $i === $a['i'] ? $a['st']['rows'][$i] : null;
    $ok = false;
    foreach ($row && isset($row['copies']) && !in_array($svc, $a['tried'], true) ? $row['copies'] : array() as $c)
        $ok = $ok || $c['svc'] === $svc;
    if (!$a || !$row || !$ok)
    {
        mel_log('via: not the dialog of the last start, nothing');
        return mel_close_and(mel_error('err_list_expired'));
    }
    $st = $a['st'];
    // The list of the start, not always the one on screen (mel_op_of_screen): the pair of its profiles (spec v2 §1.9).
    mel_op_use($st['prof'], $st['lp_prof']);
    Mel::$via = array('k' => $st['movie']['dune_id'], 'hash' => $row['hash'], 'svc' => $svc);
    mel_log(sprintf('via: %s, row %d: %s refused, %s chosen', mel_ep_tag($st), $i, implode(', ', $a['tried']), $svc));
    $act = mel_play($st, $row, $a['pos'], null, $a['lazy'], null, $a['from']);
    if ($act[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
    {
        Mel::$alt = null;
        // The list on screen, if it is that one: its cursor now.
        mel_playing($a['from'], Mel::$state && Mel::$state['rid'] === $st['rid'] ? Mel::$state : $st, $i);
    }
    return mel_close_and($act);
}

// --- Time of the operation: handle_user_input and play_action have 60 s;
// the requests before the player (the stream check, the markers of
// segments.php) get what is left of this many seconds since Mel::$t0.
define('MEL_OP_BUDGET', 55);

// Seconds for a request of $what (a log prefix): what is left of the
// operation, at most $cap (all of it outside an operation), whole -> that, or
// 0 when it is under $min (logged: skipped).
/**
 * @param int $cap
 * @param int $min
 * @param string $what
 * @return int
 */
function mel_op_total($cap, $min, $what)
{
    $left = Mel::$t0 > 0 ? MEL_OP_BUDGET - (microtime(true) - Mel::$t0) : $cap;
    $total = (int) min($cap, floor($left));
    if ($total >= $min)
        return $total;
    mel_log(sprintf('%s: skipped, the operation took %.1f s', $what, MEL_OP_BUDGET - $left));
    return 0;
}

// --- Stream check before the player: one GET of the row's URL, no
// redirects followed. Debrid add-ons redirect to the CDN of the service when
// the file is ready, else to a placeholder video of their own host:
// AIOStreams /static/<file>.mp4, Torrentio /videos/<file>.mp4, MediaFusion
// /static/exceptions/<file>.mp4, StremThru /v0/store/_/static/<file>.mp4.
// The add-ons of ElfHosted redirect to slate.elfhosted.com instead, the
// reason in its title and body. Comet sends its placeholder as the body: 200,
// a video/* type (not an HLS playlist) and a Content-Length under 1 MB, no
// film is that small.

define('MEL_PRE_CONNECT', 5);
define('MEL_PRE_TOTAL', 30);
// The check gets what is left of MEL_OP_BUDGET, nothing below this.
define('MEL_PRE_MIN', 3);
// A longer Location is not taken for a stream URL.
define('MEL_PRE_LOC_MAX', 4096);
// A 200 video body shorter than this is a placeholder.
define('MEL_PRE_BODY_MAX', 1048576);

// HTTP code $code, Location $loc, Content-Type $type and Content-Length
// $len (-1: none) of stream URL $url -> array('class' => 'stream' (with
// 'url' => the Location), 'placeholder' (with 'key' and 'detail' of its
// message, 'note' for the log) or 'unclear' (with 'why')). Only an absolute
// http(s) Location of another host, not the slate, not an archive, is a stream.
/**
 * @param string $url
 * @param int $code
 * @param string $loc
 * @param string $type
 * @param int $len
 * @return MelPrecheck
 */
function mel_pre_class($url, $code, $loc, $type, $len)
{
    if ($code === 200 && $len > 0 && $len < MEL_PRE_BODY_MAX)
    {
        $t = strtolower(trim(preg_replace('/;.*$/s', '', $type)));
        // Not an HLS playlist: small, yet a stream. Comet: the reason is not in the headers.
        if (substr($t, 0, 6) === 'video/' && strpos($t, 'mpegurl') === false)
            return array('class' => 'placeholder', 'key' => 'err_pre_placeholder',
                'detail' => sprintf('HTTP %d, %d KB', $code, max(1, round($len / 1024))),
                'note' => mel_cut(preg_replace('/[^\x21-\x7e]+/', '', $t), 60) . " $len bytes");
    }
    if ($code < 300 || $code > 399 || $loc === '')
        return array('class' => 'unclear', 'why' => "HTTP $code" . ($code >= 300 && $code <= 399 ? ', no Location' : ''));
    // The slate before the limits of a stream URL: a long one is still a placeholder.
    $p = preg_match('~^https?://~i', $loc) ? parse_url($loc) : false;
    if (is_array($p) && isset($p['host']) && strtolower($p['host']) === 'slate.elfhosted.com' &&
        !isset($p['user']) && !isset($p['pass']))
        return mel_pre_slate(isset($p['query']) ? $p['query'] : '');
    if (strlen($loc) > MEL_PRE_LOC_MAX || preg_match('/[^\x21-\x7e]/', $loc))
        return array('class' => 'unclear', 'why' => "HTTP $code, Location not a URL");
    $self = strtolower(strval(parse_url($url, PHP_URL_HOST)));
    if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $loc))
    {
        $p = parse_url($loc);
        if (!preg_match('~^https?://~i', $loc) || !is_array($p) || !isset($p['host']) || $p['host'] === '' ||
            isset($p['user']) || isset($p['pass']))
            return array('class' => 'unclear', 'why' => "HTTP $code, Location not http(s)");
        $host = strtolower($p['host']);
        $path = isset($p['path']) ? $p['path'] : '';
        if ($host !== $self)
        {
            // Real-Debrid gives some releases as one archive, the player can't open it.
            if (preg_match('/\.(rar|zip)$/iD', urldecode($path), $m))
                return array('class' => 'placeholder', 'key' => 'err_pre_archive', 'detail' => '',
                    'note' => 'archive .' . strtolower($m[1]) . " -> $host");
            return array('class' => 'stream', 'url' => $loc, 'host' => $host);
        }
    }
    else if (substr($loc, 0, 2) === '//')
        return array('class' => 'unclear', 'why' => "HTTP $code, Location not http(s)");
    else
        $path = preg_replace('/[?#].*$/s', '', $loc);
    if (preg_match('~(?:^|/)(?:static|videos)/(?:.*/)?([^/]+\.mp4)$~i', $path, $m))
        return mel_pre_file($m[1]);
    return array('class' => 'unclear', 'why' => "HTTP $code, same host, not a placeholder");
}

// Name $file of a placeholder video of the add-on -> its placeholder.
/**
 * @param string $file
 * @return array{class: 'placeholder', key: string, detail: string, note: string}
 */
function mel_pre_file($file)
{
    $f = strtolower($file);
    // MediaFusion: content_infringing.mp4, Torrentio: failed_infringement_v3.mp4,
    // StremThru: 451.mp4, AIOStreams: unavailable_for_legal_reasons.mp4.
    if (strpos($f, 'infring') !== false || strpos($f, 'legal') !== false || $f === '451.mp4')
        $key = 'err_pre_infringing';
    // AIOStreams: downloading.mp4, MediaFusion: torrent_not_downloaded.mp4.
    else if (strpos($f, 'downloading') !== false || strpos($f, 'not_downloaded') !== false)
        $key = 'err_pre_downloading';
    // Torrentio: failed_rar_v3.mp4, AIOStreams: archive_only.mp4 (TorBox keeps the release as one archive).
    else if (strpos($f, 'failed_rar') !== false || strpos($f, 'archive') === 0)
        $key = 'err_pre_archive';
    // AIOStreams: no_matching_file.mp4.
    else if (strpos($f, 'no_matching_file') !== false)
        $key = 'err_pre_no_file';
    // Torrentio: limits_exceeded_v2.mp4, StremThru: store_limit_exceeded.mp4,
    // AIOStreams: 429.mp4 (the limit of TorBox).
    else if (strpos($f, 'limit') !== false || $f === '429.mp4')
        $key = 'err_pre_limit';
    else
        $key = 'err_pre_placeholder';
    $name = mel_cut($file, 100);
    return array('class' => 'placeholder', 'key' => $key, 'detail' => $key === 'err_pre_placeholder' ? $name : '',
        'note' => $name);
}

// Query of a slate.elfhosted.com Location -> its placeholder: infringing when
// title or body tell of a refusal as infringing / for legal reasons, else by
// the title: a limit, downloading, else the general one with the title
// (printable, no leading "%": not a %tr% key of the shell, cut). Not its query
// in the note: sig and ts. Not parse_str: magic quotes.
/**
 * @param string $query
 * @return array{class: 'placeholder', key: string, detail: string, note: string}
 */
function mel_pre_slate($query)
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
    $title = mel_cut(rtrim(ltrim($title, '% ')), 100);
    // AIOStreams: "Debrid limit reached", "Proxy limit reached", "Too many
    // requests"; MediaFusion "Still downloading", AIOStreams "Still caching"
    // (not "download": AIOStreams "Download failed").
    if (stripos($why, 'infring') !== false || stripos($why, 'legal reason') !== false)
        $key = 'err_pre_infringing';
    else if (stripos($title, 'limit reached') !== false || stripos($title, 'too many requests') !== false)
        $key = 'err_pre_limit';
    else if (stripos($title, 'downloading') !== false || stripos($title, 'caching') !== false)
        $key = 'err_pre_downloading';
    else
        $key = 'err_pre_placeholder';
    return array('class' => 'placeholder', 'key' => $key,
        'detail' => $key === 'err_pre_placeholder' ? ($title !== '' ? $title : 'slate') : '',
        'note' => 'slate: ' . ($title !== '' ? $title : '-'));
}

// The check of stream URL $url -> mel_pre_class(), logged.
/**
 * @param string $url
 * @return MelPrecheck
 */
function mel_precheck($url)
{
    $total = mel_op_total(MEL_PRE_TOTAL, MEL_PRE_MIN, 'precheck');
    if ($total === 0)
        return array('class' => 'unclear', 'why' => 'no time');
    $t = microtime(true);
    $err = '';
    list($code, $loc, $type, $len) = mel_http_peek($url, $err, min(MEL_PRE_CONNECT, $total), $total);
    $t = microtime(true) - $t;
    $pre = $err !== '' ? array('class' => 'unclear', 'why' => $err) : mel_pre_class($url, $code, $loc, $type, $len);
    // Not the URLs: the row's and the CDN's carry keys and tokens.
    if ($pre['class'] === 'stream')
        mel_log(sprintf('precheck: stream, HTTP %d -> %s, %.2f s', $code, $pre['host'], $t));
    else if ($pre['class'] === 'placeholder')
        mel_log(sprintf('precheck: placeholder, HTTP %d, %s, %.2f s', $code, $pre['note'], $t));
    else
        mel_log(sprintf('precheck: unclear (%s), %.2f s', $pre['why'], $t));
    return $pre;
}

// --- Back from the player to the list screen.

// Playback of row $sel of list state $st; $from: rid of the screen under the player.
/**
 * @param string $from
 * @param MelState $st
 * @param int $sel
 * @return void
 */
function mel_playing($from, $st, $sel)
{
    Mel::$playing = array('from' => $from, 'st' => $st, 'sel' => $sel);
}

// "S01E05", or "movie".
/**
 * @param MelState $st
 * @return string
 */
function mel_ep_tag($st)
{
    return $st['e'] > 0 ? sprintf('S%02dE%02d', $st['s'], $st['e']) : 'movie';
}

// menu_playback_finish of a live list screen. Only after a real stop, not a
// restart of the player (the flag, as Online movies has it on its screens).
/**
 * @param string $rid
 * @return MelAction
 */
function mel_finish_act($rid)
{
    $a = mel_gc_act('finish', $rid);
    $a[GuiAction::flags] = GUI_ACTION_FLAG_SKIP_ON_PLAY_RESTART;
    return $a;
}

// Back from the player to list screen $in->rid. If lazy episodes moved on
// (another episode of the same release played last), the screen is replaced
// by the list of that episode, cursor on the release: a new rid, so the old
// screen, if the shell keeps it, only says "outdated". Same episode: the
// shell keeps the screen and its cursor; only the progress bar of the
// release played may change (mel_gc_bar_update). The rows are those of "next", no
// request; of a state of one row (mel_ts_pack_state), one request of that episode.
// JacRed tracks of the old list go over by hash (one release). The tab "TV" built anew (mel_tv_flag) and the main
// screens of the catalog invalidated (mel_catv_after_player): the row "Continue" and the badges are of the history.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_finish($in)
{
    mel_tv_flag('back from the player');
    return mel_catv_after_player(mel_finish_list($in));
}

// mel_finish of list $in->rid: the action of its screen.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_finish_list($in)
{
    $st = Mel::$state;
    // The online list (view_online.php): the bars of its rows.
    if ($st && !empty($st['lp_on']) && isset($st['lp'], $in->rid) && $in->rid === $st['rid'])
        return mel_lpv_finish($st);
    $p = Mel::$playing;
    $rid = isset($in->rid) && is_string($in->rid) ? $in->rid : '';
    if (!$st || $rid !== $st['rid'] || !$p || $p['from'] !== $rid)
    {
        mel_log('finish: not the screen of the playback, nothing to do');
        return null;
    }
    $ns = $p['st'];
    if ($ns['s'] === $st['s'] && $ns['e'] === $st['e'])
    {
        mel_log('finish: ' . mel_ep_tag($st) . ' as the list, screen kept');
        $pr = $ns['rows'];
        return mel_gc_bar_update($st, isset($pr[$p['sel']]) ? $pr[$p['sel']]['hash'] : '');
    }
    $sel = $p['sel'];
    if (isset($ns['pack_only']))
    {
        $hash = $ns['rows'][$sel]['hash'];
        $res = mel_next_fetch($ns['movie']['dune_id'], $ns['s'], $ns['e']);
        if (isset($res['error']))
        {
            mel_log('finish: list ' . mel_ep_tag($st) . ', played ' . mel_ep_tag($ns) . " -> no list: {$res['error']} {$res['detail']}, screen kept");
            return null;
        }
        $ns = $res['st'];
        $pick = mel_release_pick($ns['rows'], $hash, -1, -1);
        $sel = max($pick['ts'], 0);
    }
    $n = count($ns['rows']);
    $ns = mel_gc_put_cursor($ns, $sel);
    $ns['rows'] = mel_carry_tracks($ns['rows'], $st['rows']);
    // The playback goes on from the new screen: if the event came while the
    // player only switched episodes, the next ones still find their screen.
    mel_playing($ns['rid'], $ns, $sel);
    mel_log('finish: list ' . mel_ep_tag($st) . ', played ' . mel_ep_tag($ns) . " -> screen replaced, row $sel of $n");
    return mel_list_replace($ns);
}

// The screen replaced by the one of $media_url: "Back" does not grow; a new
// media_url, so nothing cached of the old one is reused. $erase: screens
// taken off the top of the path first (3: the shell's episodes and seasons
// over the list, mel_chosen).
/**
 * @param string $media_url
 * @param string $caption
 * @param int $erase
 * @return MelAction
 */
function mel_replace_act($media_url, $caption, $erase = 1)
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

// List $ns becomes the live one (Mel::$state), its screen "streams:<rid>"
// replaces the list screen ($erase: as mel_replace_act).
/**
 * @param MelState $ns
 * @param int $erase
 * @return MelAction
 */
function mel_list_replace($ns, $erase = 1)
{
    Mel::$state = $ns;
    return mel_replace_act("streams:{$ns['rid']}", mel_ep_name($ns['movie'], $ns['s'], $ns['e']), $erase);
}

// --- Episodes by the left and right keys of the list screen (0.13.0).

// The episode after ($dir 1) or before ($dir -1) S$s E$e by season_numbers,
// as 0.9.0: the next one of the season, else E1 of the nearest later season;
// the one before, else the last of the nearest earlier season. No playlist
// here: no MEL_EPISODES_MAX. An episode past episodes_count (the card lags
// behind a running series) only goes back. -> array(s, e) or null. Unlike
// mel_wh_next on purpose: nothing past episodes_count or after the last one.
/**
 * @param array<int, int> $seasons
 * @param int $s
 * @param int $e
 * @param int $dir
 * @return array{int, int}|null
 */
function mel_flip_ep($seasons, $s, $e, $dir)
{
    $from = sprintf('S%02dE%02d', $s, $e);
    if (!isset($seasons[$s]))
    {
        mel_log("flip: $from, no data on season $s in season_numbers, nothing");
        return null;
    }
    if ($e > $seasons[$s] && $dir > 0)
    {
        mel_log("flip: $from, E$e beyond {$seasons[$s]} of season $s, nothing");
        return null;
    }
    if ($dir > 0 ? $e < $seasons[$s] : $e > 1)
    {
        mel_log("flip: $from -> " . sprintf('S%02dE%02d', $s, $e + $dir) . ', same season' .
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
        mel_log("flip: $from, " . ($dir > 0 ? 'last' : 'first') . ' episode of the series, nothing');
        return null;
    }
    $ne = $dir > 0 ? 1 : $seasons[$near];
    mel_log("flip: $from -> " . sprintf('S%02dE%02d', $near, $ne) . ', ' . ($dir > 0 ? 'next' : 'previous') . ' season');
    return array($near, $ne);
}

// gc_flip (d = prev|next) of a live list of an episode: the list of the
// episode before or after it replaces the screen, as mel_finish does: "Back"
// goes to the card, the old rid is outdated. Cursor on the release of the
// current row (with the episode first), else on the first. An error: a dialog, the screen and the
// state stay as they were.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_flip($in)
{
    $st = Mel::$state;
    $d = isset($in->d) && is_string($in->d) ? $in->d : '';
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'] || ($d !== 'prev' && $d !== 'next'))
    {
        mel_log('flip: not a live list or a bad key, nothing');
        return null;
    }
    if ($st['e'] < 1)
    {
        mel_log('flip: a movie, nothing');
        return null;
    }
    $to = mel_flip_ep($st['movie']['seasons'], $st['s'], $st['e'], $d === 'next' ? 1 : -1);
    if (!$to)
        return null;
    list($s, $e) = $to;
    list($cur) = mel_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    $ep = sprintf('S%02dE%02d', $s, $e);
    return mel_relist_screen($st, $s, $e, $cur, 'flip: ' . mel_ep_tag($st) . " -> $ep", false,
        mel_tr($st['lang'], 'next_failed', mel_se($s, $e)), 1);
}

// gc_refresh (MENU "Refresh") of a live list, a movie or an episode: the same
// request anew, the screen replaced as by mel_flip, cursor on the same
// release, else the first row. An error: a dialog, the screen and the state
// stay as they were.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_refresh($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('refresh: not a live list, nothing');
        return null;
    }
    list($cur) = mel_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    return mel_relist_screen($st, $st['s'], $st['e'], $cur, 'refresh: ' . mel_ep_tag($st), true,
        mel_tr($st['lang'], 'refresh_failed'), 1);
}

// --- "Choose episode" in MENU of an episode list (0.28.0): the shell's own
// seasons and episodes screens (choose_season_episode of shell_ext, as Online
// movies has it); ENTER on an episode there runs our result_action, gc_chosen,
// with chosen_s, chosen_e and se_chosen_manually added to its params.

// gc_choose of a live list of an episode -> the shell's seasons screen.
// No chosen_s/chosen_e (the shell would pick the episode itself), no
// season_filter (AIOStreams does not say which seasons have releases).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_choose($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('choose: not a live list');
        return mel_error('err_list_expired');
    }
    if ($st['e'] < 1)
    {
        mel_log('choose: a movie, nothing');
        return null;
    }
    // The cursor of the list for gc_chosen: its parent_sel_state is the shell's.
    Mel::$state['gc'] = mel_gc_pos($st, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    mel_log('choose: ' . mel_ep_tag($st) . " of list {$st['rid']}, row " . Mel::$state['gc'][0] .
        ' -> seasons screen of the shell');
    return mel_choose_act($st['rid'], 'gc_chosen');
}

// The seasons screen of the shell over list $rid, ENTER on an episode there -> $control of it (gc_chosen;
// lp_chosen of the online list, view_online.php).
/**
 * @param string $rid
 * @param string $control
 * @return MelAction
 */
function mel_choose_act($rid, $control)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => 'shell_ext',
        GuiAction::data => null,
        GuiAction::params => array(
            'handler_id' => 'entry',
            'control_id' => 'choose_season_episode',
            'result_action' => json_encode(mel_gc_act($control, $rid))));
}

// chosen_s / chosen_e of the shell: an int or a string of digits, >= 1.
// -> the number or 0.
/**
 * @param stdClass $in
 * @param string $k
 * @param string $re
 * @return int
 */
function mel_chosen_num($in, $k, $re)
{
    $v = isset($in->$k) ? $in->$k : null;
    if (is_int($v))
        $v = strval($v);
    return is_string($v) && preg_match($re, $v) ? intval($v) : 0;
}

// gc_chosen: the list of the episode chosen replaces the list and the two
// screens of the shell over it ("Back" goes to the card), as mel_flip: the
// request tt:s:e, tracks by hash, cursor on the release of the list. The same
// episode: just anew. An error: a dialog over the episodes screen, the list
// and the state as they were.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_chosen($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('pick: not a live list');
        return mel_error('err_list_expired');
    }
    $s = mel_chosen_num($in, 'chosen_s', '/^[1-9][0-9]{0,3}$/D');
    $e = mel_chosen_num($in, 'chosen_e', '/^[1-9][0-9]{0,4}$/D');
    if ($st['e'] < 1 || $s < 1 || $e < 1)
    {
        mel_log('pick: ' . ($st['e'] < 1 ? 'a movie' : 'no episode chosen (' .
            mel_cut(json_encode(array(isset($in->chosen_s) ? $in->chosen_s : null,
            isset($in->chosen_e) ? $in->chosen_e : null)), 100) . ')') . ', nothing');
        return null;
    }
    $how = isset($in->se_chosen_manually) && is_scalar($in->se_chosen_manually) ?
        mel_cut(strval($in->se_chosen_manually), 10) : '-';
    list($cur) = mel_gc_pos($st, null);
    $ep = sprintf('S%02dE%02d', $s, $e);
    return mel_relist_screen($st, $s, $e, $cur, 'pick: ' . mel_ep_tag($st) . " -> $ep (manually $how)", false,
        mel_tr($st['lang'], 'next_failed', mel_se($s, $e)), 3);
}

// --- "Download in app" in MENU of the list (0.29.0): the magnet of the row
// to the system app chooser (torrent clients, remote clients, TorrServe).

// gc_dl of row i of a live list -> launch_ext_app of an intent: URI without a
// package (no -p: old firmware lacks it). The error action runs when no app
// takes magnet or the shell keeps focus for delay s (delay 0 raced the
// chooser and showed the error over it, device 08.10.2026).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_download($in)
{
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log('download: not a live list');
        return mel_error('err_list_expired');
    }
    if (Mel::$settings['no_torrents'])
        return mel_torrents_off($st['lang']);
    $i = isset($in->i) && is_string($in->i) && preg_match('/^[0-9]{1,3}$/D', $in->i) ? intval($in->i) : -1;
    if (!isset($st['rows'][$i]) || $st['rows'][$i]['hash'] === '')
    {
        mel_log('download: no row with infoHash, nothing');
        return null;
    }
    list($mg, $n, $src) = mel_magnet($st['rows'][$i]);
    // Trackers are not logged: a private one carries a passkey.
    mel_log('download: ' . mel_ep_tag($st) . ", row $i, hash {$st['rows'][$i]['hash']}, $n trackers ($src)" .
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
            LaunchExtAppActionData::error_action => mel_error('dl_no_app', '', false, false)));
}

// The list of S$s E$e (-1 -1: the movie) of the card of $st anew, the request
// of play_action. Tracks of the old list by hash first: JacRed then asks only
// for the rest. Cursor on release $hash (an episode: with it in its file
// first), else the first row. -> array('error' => key, 'detail' => text,
// maybe 'errors') of mel_fetch or array('st' => new state, 'sel' => row, 'by' =>
// why that row).
/**
 * @param MelState $st
 * @param int $s
 * @param int $e
 * @param string $hash
 * @return array{error: string, detail: string, errors?: MelErrors}|array{st: MelState, sel: int, by: string}
 */
function mel_relist($st, $s, $e, $hash)
{
    $mv = $st['movie'];
    // From the list screen the player may be near: no detour to the settings.
    if (Mel::$settings['base'] === '')
        $res = array('error' => Mel::$settings['own'] ? 'err_set_address_own' : 'err_set_address', 'detail' => '');
    else
    {
        list($id, $cin) = mel_stream_ask($mv, $s, $e);
        $res = mel_fetch(Mel::$settings['base'], $id, $mv['series'], $cin);
    }
    if (isset($res['error']))
        return $res;
    $rows = mel_carry_tracks($res['rows'], $st['rows']);
    if (MEL_JACRED)
        $rows = mel_jacred($rows, $mv, $s, $e);
    $ns = mel_list_state($mv, $s, $e, $rows, $st['lang'], $res['errors']);
    list($sel, $by) = mel_release_row($ns['rows'], $hash, $s, $e, mel_cin_n($mv['cin'], $s, $e));
    return array('st' => mel_gc_put_cursor($ns, $sel), 'sel' => $sel, 'by' => $by);
}

// The row of release $hash in $rows of S$s E$e: with the episode in its file
// first (mel_has_ep, by its number $n too), as mel_next (a release not in the cache has its hash in every reply,
// even a pack of another season), else any of it, else the first row. Found
// in the rows as the list has them: sorted. -> array(row, why that row)
/**
 * @param list<MelRow> $rows
 * @param string $hash
 * @param int $s
 * @param int $e
 * @param int $n
 * @return array{int, string}
 */
function mel_release_row($rows, $hash, $s, $e, $n = 0)
{
    $sel = -1;
    $by = 'no such release';
    foreach ($rows as $i => $r)
    {
        if ($hash === '' || $r['hash'] !== $hash)
            continue;
        if ($e < 1 || mel_has_ep($r, $s, $e, $n))
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
    return array(max(0, $sel), $by);
}

// The dialog of a failed mel_relist: the error, its detail, OK; a reply of error
// streams only, with the setting on: their window instead.
/**
 * @param string $title
 * @param string $lang
 * @param array{error: string, detail: string, errors?: MelErrors} $res
 * @return MelAction
 */
function mel_relist_dialog($title, $lang, $res)
{
    $only = mel_errors_only_dialog($lang, $res);
    if ($only)
        return $only;
    return mel_dialog($title, array_merge(mel_dialog_lines(mel_tr($lang, $res['error'])),
        mel_dialog_lines($res['detail'])),
        array('OK' => mel_close_and(null)));
}

// The list of S$s E$e of the card of live list $st anew (mel_relist, cursor
// on the release of row $cur) replaces the screen and $erase screens over it
// (mel_replace_act); an error: dialog $title, the screen and the state kept.
// $tag starts the log lines; $log_old: they show the old rows and row too.
/**
 * @param MelState $st
 * @param int $s
 * @param int $e
 * @param int $cur
 * @param string $tag
 * @param bool $log_old
 * @param string $title
 * @param int $erase
 * @return MelAction
 */
function mel_relist_screen($st, $s, $e, $cur, $tag, $log_old, $title, $erase)
{
    // An empty list (mel_gc_src): no release to keep.
    $r = mel_relist($st, $s, $e, isset($st['rows'][$cur]) ? $st['rows'][$cur]['hash'] : '');
    if (isset($r['error']))
    {
        mel_log(sprintf('%s failed: %s, %.2f s, the screen kept', $tag, $r['error'], microtime(true) - Mel::$t0));
        return mel_relist_dialog($title, $st['lang'], $r);
    }
    $ns = $r['st'];
    mel_log(sprintf('%s, screen replaced, %s rows, row %s (%s), %.2f s', $tag,
        ($log_old ? count($st['rows']) . ' -> ' : '') . count($ns['rows']), ($log_old ? "$cur -> " : '') . $r['sel'], $r['by'],
        microtime(true) - Mel::$t0));
    return mel_list_replace($ns, $erase);
}

// --- Lazy episodes of the playlist (proven on the device 05.10.2026): the
// player's get_vod_stream_url gets an action, not a URL. Only
// handle_user_input of our own plugin works there (a new vod_play or a
// dialog from it); show_dialog, stop_playback or "error" right in
// error_action hang the player.

/**
 * @param string $key
 * @return string
 */
function mel_movie_path($key)
{
    $dir = isset(DuneSystem::$properties['tmp_dir_path']) ? strval(DuneSystem::$properties['tmp_dir_path']) : '';
    return $dir !== '' ? rtrim($dir, '/') . "/movie_$key.json" : '';
}

// Card data of a playlist with lazy episodes: memory and tmp_dir (RAM,
// cleared by a reboot), so that a restarted php_server still finds it.
/**
 * @param MelMovie $mv
 * @param string $lang
 * @return void
 */
function mel_movie_save($mv, $lang)
{
    $key = $mv['dune_id'];
    $d = array('movie' => $mv, 'lang' => $lang);
    Mel::$movies[$key] = $d;
    $path = mel_movie_path($key);
    if ($path === '' || (!is_dir(dirname($path)) && !mkdir(dirname($path), 0755, true)) ||
        file_put_contents($path, json_encode($d)) === false)
        mel_log('movie: not saved to tmp_dir');
}

// -> array('movie' => as mel_movie, 'lang' => ...) or null.
/**
 * @param string $key
 * @return MelLazyMovie|null
 */
function mel_movie_load($key)
{
    if (isset(Mel::$movies[$key]))
        return Mel::$movies[$key];
    $path = mel_movie_path($key);
    $d = $path !== '' && is_file($path) ? json_decode(strval(file_get_contents($path)), true) : null;
    $m = is_array($d) && isset($d['movie']) && is_array($d['movie']) ? $d['movie'] : null;
    if (!$m || mel_str($m, 'dune_id') !== $key || !preg_match('/^tt[0-9]{1,10}$/', mel_str($m, 'imdb')))
        return null;
    $mv = array('series' => true, 'seasons' => array());
    foreach (array('imdb', 'dune_id', 'title', 'native', 'year', 'poster', 'fanart', 'rate_imdb') as $f)
        $mv[$f] = mel_str($m, $f);
    foreach (mel_arr($m, 'seasons') as $n => $c)
    {
        if (intval($n) >= 1 && is_int($c) && $c >= 1)
            $mv['seasons'][intval($n)] = $c;
    }
    ksort($mv['seasons']);
    if (isset($m['own_card']) && $m['own_card'] === true)
        $mv['own_card'] = true;
    $mv['cin'] = mel_cin_valid(isset($m['cin']) ? $m['cin'] : null);
    $titles = mel_titles_valid(isset($m['titles']) ? $m['titles'] : null);
    if ($titles)
        $mv['titles'] = $titles;
    $r = array('movie' => $mv, 'lang' => mel_str($d, 'lang'));
    Mel::$movies[$key] = $r;
    return $r;
}

// get_vod_stream_url of a lazy episode -> error_action.
/**
 * @param mixed $pb
 * @return MelAction
 */
function mel_lazy_action($pb)
{
    // An episode of "Онлайн" (online_play.php).
    if (is_string($pb) && strpos($pb, 'lampac_next:') === 0)
        return mel_lpv_lazy($pb);
    if (!is_string($pb) || !preg_match('/^aio_next:(' . MEL_DUNE_ID . '):([1-9][0-9]{0,3}):([1-9][0-9]{0,4}):([0-9a-f]{40}):(debrid|ts)' .
        '(?::([a-z0-9]{8}))?$/D', $pb, $m))
    {
        mel_log('lazy: bad playback_url ' . (is_string($pb) ? mel_cut(mel_mask($pb), 100) : gettype($pb)));
        return mel_error('err_list_expired', '', true);
    }
    $prof = isset($m[6]) ? $m[6] : '';
    mel_log("lazy: S{$m[2]}E{$m[3]} hash={$m[4]} mode={$m[5]}" . ($prof !== '' ? " profile $prof" : '') .
        ' -> handle_user_input next');
    return mel_input('next', array('k' => $m[1], 's' => $m[2], 'e' => $m[3], 'h' => $m[4], 'm' => $m[5], 'p' => $prof));
}

// "Episode SxEy did not load": the error, $labels (mel_errors_labels) and "Stop" only.
/**
 * @param string $lang
 * @param string $ep
 * @param string $key
 * @param string $detail
 * @param list<string> $labels
 * @return MelAction
 */
function mel_next_failed($lang, $ep, $key, $detail = '', $labels = array())
{
    mel_log("next: -> dialog: $key" . ($detail !== '' ? " ($detail)" : ''));
    return mel_dialog(mel_tr($lang, 'next_failed', $ep),
        array_merge(mel_dialog_lines(mel_tr($lang, $key)), mel_dialog_lines($detail), $labels),
        array(mel_tr($lang, 'next_stop') => mel_input('next_stop')));
}

// Param p of next/next_pick: the id of the profile of releases of the playlist (mel_lazy_series) - Mel::$settings of it
// (mel_op_use; none, not there or not ready: the main one as loaded).
/**
 * @param stdClass $in
 * @return void
 */
function mel_next_prof($in)
{
    if (isset($in->p) && is_string($in->p) && $in->p !== '')
        mel_op_use($in->p, Mel::$settings['lp_prof']);
}

// Params k, s, e, h of next/next_pick -> array(key, s, e, hash) or null.
/**
 * @param stdClass $in
 * @return array{string, int, int, string}|null
 */
function mel_next_params($in)
{
    $p = array();
    foreach (array('k' => '/^' . MEL_DUNE_ID . '$/D', 's' => '/^[1-9][0-9]{0,3}$/D', 'e' => '/^[1-9][0-9]{0,4}$/D',
        'h' => '/^[0-9a-f]{40}$/D') as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
            return null;
        $p[] = $k === 's' || $k === 'e' ? intval($in->$k) : $in->$k;
    }
    return $p;
}

// Streams of the episode -> array('st' => list state) or array('error' =>
// key, 'detail' => text); 'lang' and 'errors' (mel_fetch, else none) in both.
/**
 * @param string $key
 * @param int $s
 * @param int $e
 * @return array{error: string, detail: string, lang: string, errors: MelErrors}|array{lang: string, st: MelState, errors: MelErrors}
 */
function mel_next_fetch($key, $s, $e)
{
    $saved = mel_movie_load($key);
    if (!$saved)
        return array('error' => 'err_list_expired', 'detail' => '', 'lang' => '', 'errors' => mel_no_errors());
    $mv = $saved['movie'];
    // Under the player: only the error with the way to the settings.
    if (Mel::$settings['base'] === '')
        return array('error' => Mel::$settings['own'] ? 'err_set_address_own' : 'err_set_address', 'detail' => '',
            'lang' => $saved['lang'], 'errors' => mel_no_errors());
    list($id, $cin) = mel_stream_ask($mv, $s, $e);
    $res = mel_fetch(Mel::$settings['base'], $id, true, $cin);
    if (isset($res['error']))
        return array('error' => $res['error'], 'detail' => $res['detail'], 'lang' => $saved['lang'],
            'errors' => isset($res['errors']) ? $res['errors'] : mel_no_errors());
    return array('lang' => $saved['lang'], 'st' => mel_list_state($mv, $s, $e, $res['rows'], $saved['lang'], $res['errors']),
        'errors' => $res['errors']);
}

// The file of the row has the episode. Seasons may be absent (not parsed). An episode of an anime numbered on, its
// number of the meta $n (mel_cin_n; 923 for S21E32 of One Piece): its season in the name with its episode
// (a file with no season and number 32 is of another episode, mel_cin_wrong), or that number with no season or
// season 1 (a pack "1-990", "S01E923").
/**
 * @param MelRow $r
 * @param int $s
 * @param int $e
 * @param int $n
 * @return bool
 */
function mel_has_ep($r, $s, $e, $n = 0)
{
    if ($n > 0)
        return ($r['pf_seasons'] !== null && in_array($s, $r['pf_seasons'], true) && in_array($e, $r['pf_episodes'], true)) ||
            (in_array($n, $r['pf_episodes'], true) && ($r['pf_seasons'] === null || in_array(1, $r['pf_seasons'], true)));
    return in_array($e, $r['pf_episodes'], true) && ($r['pf_seasons'] === null || in_array($s, $r['pf_seasons'], true));
}

// The rows of release $hash in $rows (indexes, -1: none): 'deb' the row with a
// URL, a cached one first, with episode $s/$e in its file ($e < 1: any; mel_has_ep,
// by its number $n too);
// 'p2p' the first P2P row (no URL); 'ts' the row for TorrServer: the first P2P
// one, else the first; 'rows' rows of the release, 'cached' of them cached with
// a URL. First: in the order of
// AIOStreams (ord), not of the sorted list, so that a sort never changes the
// row (copies of a release from several add-ons). The check of a row not cached
// (mel_deb_precheck) and TorrServer are the caller's, by the mode (mel_play_action
// resume, mel_next).
/**
 * @param list<MelRow> $rows
 * @param string $hash
 * @param int $s
 * @param int $e
 * @param int $n
 * @return array{deb: int, p2p: int, ts: int, rows: int, cached: int}
 */
function mel_release_pick($rows, $hash, $s, $e, $n = 0)
{
    $p = array('deb' => -1, 'p2p' => -1, 'ts' => -1, 'rows' => 0, 'cached' => 0);
    $ord = $pos = array();
    foreach ($rows as $i => $r)
    {
        $ord[] = $r['ord'];
        $pos[] = $i;
    }
    array_multisort($ord, SORT_ASC, $pos, SORT_ASC);
    foreach ($pos as $i)
    {
        $r = $rows[$i];
        if ($r['hash'] !== $hash)
            continue;
        $p['rows']++;
        if ($p['ts'] < 0)
            $p['ts'] = $i;
        if (mel_is_p2p($r))
        {
            if ($p['p2p'] < 0)
                $p['ts'] = $p['p2p'] = $i;
            continue;
        }
        if ($r['cached'] === true)
            $p['cached']++;
        if (($e < 1 || mel_has_ep($r, $s, $e, $n)) &&
            ($p['deb'] < 0 || ($r['cached'] === true && $rows[$p['deb']]['cached'] !== true)))
            $p['deb'] = $i;
    }
    return $p;
}

// mel_precheck() of the URL of Debrid row $r; a URL not http(s) is unclear.
/**
 * @param MelRow $r
 * @return MelPrecheck
 */
function mel_deb_precheck($r)
{
    return preg_match('~^https?://~i', $r['url']) ? mel_precheck($r['url']) :
        array('class' => 'unclear', 'why' => 'not http(s)');
}

// One more request of mel_next leaves this many seconds at least: the stream
// check (MEL_PRE_MIN), or the echo and add of TorrServer (2 + 5 s).
define('MEL_NEXT_AFTER_FETCH', 7);

// The streams of a lazy episode and the release in them -> array(mel_next_fetch,
// mel_release_pick or null on an error, no such release). Add-ons failed in the
// reply and it has no such release: one more request at once (their outage may be
// over), if the operation has the time for it.
/**
 * @param string $key
 * @param int $s
 * @param int $e
 * @param string $hash
 * @param string $mode
 * @return array{0: array{error: string, detail: string, lang: string, errors: MelErrors}|array{lang: string, st: MelState, errors: MelErrors}, 1: array{deb: int, p2p: int, ts: int, rows: int, cached: int}|null, 2: bool}
 */
function mel_next_fetch_retry($key, $s, $e, $hash, $mode)
{
    $ep = sprintf('S%02dE%02d', $s, $e);
    $retry = MEL_FETCH_TOTAL + MEL_NEXT_AFTER_FETCH;
    for ($first = true; ; $first = false)
    {
        $res = mel_next_fetch($key, $s, $e);
        $errors = $res['errors'];
        $pick = isset($res['error']) ? null : mel_release_pick($res['st']['rows'], $hash, $s, $e,
            mel_cin_n($res['st']['movie']['cin'], $s, $e));
        $none = $pick ? $pick[$mode === 'ts' ? 'ts' : 'deb'] < 0 : $res['error'] === 'err_no_streams';
        if (!$first || !$none || !$errors['lines'] || !mel_op_total($retry, $retry, "next: $ep: retry"))
            return array($res, $pick, $none);
        mel_log("next: $ep: the release not found, " . mel_errors_total($errors) . ' reasons, one more request');
        // Not two replies in the memory.
        unset($res, $pick);
    }
}

// A lazy episode, the way its playlist was started (m), never the other way.
// debrid: the same release (infoHash) with a URL and this episode in its file,
// a cached row first -> vod_play of it (mel_play checks it: a placeholder gets
// its dialog). A row not cached plays only if the check finds a stream (the
// CDN); else the dialog "not in this release".
// ts: an episode of the map of the last start: its row and file, no request
// (mel_ts_pack_state); else as a request finds: a P2P row of the release, else any
// of it, even cached -> through TorrServer, the file of the episode by the reply
// (torrserver.php); no row or file: the same dialog. Not bingeGroup: one value for
// many releases, a silent change of the voice.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_next($in)
{
    // Language of the dialogs without a saved card: of the input, else of
    // the card by a valid key, else English (as mel_tr).
    $lang = isset($in->lang) && is_string($in->lang) ? $in->lang : '';
    if ($lang === '' && isset($in->k) && is_string($in->k) && preg_match('/^' . MEL_DUNE_ID . '$/D', $in->k))
    {
        $saved = mel_movie_load($in->k);
        $lang = $saved ? $saved['lang'] : '';
    }
    mel_next_prof($in);
    $p = mel_next_params($in);
    $mode = isset($in->m) ? $in->m : '';
    if (!$p || ($mode !== 'debrid' && $mode !== 'ts'))
    {
        mel_log('next: bad params');
        return mel_next_failed($lang, '', 'err_list_expired');
    }
    list($key, $s, $e, $hash) = $p;
    $ep = sprintf('S%02dE%02d', $s, $e);
    mel_log("next: $ep hash=$hash mode=$mode");
    // "No torrents" set during the playback. Before the request: P2P rows are
    // hidden from its reply, "not in this release" would hide the reason.
    if ($mode === 'ts' && Mel::$settings['no_torrents'])
        return mel_next_failed($lang, mel_se($s, $e), 'ts_err_off');
    $st = $mode === 'ts' ? mel_ts_pack_state($key, $s, $e, $hash) : null;
    if ($st)
    {
        mel_log("next: $ep: the map of the last start, no request");
        return mel_ts_open($st, 0, 0, Mel::$playing ? Mel::$playing['from'] : '', true);
    }
    list($res, $pick, $none) = mel_next_fetch_retry($key, $s, $e, $hash, $mode);
    $errors = $res['errors'];
    if (isset($res['error']))
    {
        $lang = $res['lang'] !== '' ? $res['lang'] : $lang;
        return mel_next_failed($lang, mel_se($s, $e), $res['error'] === 'err_no_streams' && $errors['lines'] ? 'err_sources_failed' : $res['error'],
            $res['detail'], mel_errors_labels($lang, $errors));
    }
    if (!$none)
        // Release found, no stream: not the sources' fault.
        $errors = mel_no_errors();
    $st = $res['st'];
    $sel = $pick['deb'];
    $found = $sel >= 0 ? $st['rows'][$sel] : null;
    $p2p = $pick['p2p'];
    mel_log("next: $ep: " . count($st['rows']) . " rows, {$pick['rows']} with the hash, {$pick['cached']} of them cached, " .
        ($found ? ($found['cached'] === true ? 'found' : 'found not cached') : "none with $ep") .
        ($p2p >= 0 ? ", P2P row $p2p" : ''));
    $from = Mel::$playing ? Mel::$playing['from'] : '';
    if ($mode === 'ts')
    {
        $ts = $pick['ts'];
        // Its own echo: a dialog under the player.
        return $ts >= 0 ? mel_ts_open($st, $ts, 0, $from, true) : mel_next_missing($st, $hash, $errors);
    }
    $pre = null;
    if ($found && $found['cached'] !== true)
    {
        $pre = mel_deb_precheck($found);
        // A placeholder: its dialog (mel_play), not "not in this release".
        if ($pre['class'] !== 'stream' && $pre['class'] !== 'placeholder')
        {
            mel_log("next: $ep: row $sel not cached, no stream from Debrid");
            $found = null;
        }
    }
    if ($found)
    {
        $a = mel_play($st, $found, 0, null, true, $pre, $from);
        // A placeholder: the list under the player stays that of the episode played.
        if ($a[GuiAction::handler_string_id] === PLUGIN_VOD_PLAY_ACTION_ID)
            mel_playing($from, $st, $sel);
        return $a;
    }
    return mel_next_missing($st, $hash, $errors);
}

// The dialog "S2 E3 is not in this release" over the stopped player of a lazy
// episode of list state $st: "Choose a release" (its list; a state of one row,
// mel_ts_pack_state, is no list: mel_next_pick asks for it) and "Stop".
// $errors: of the reply without the release (else none), the text says so.
/**
 * @param MelState $st
 * @param string $hash
 * @param MelErrors $errors
 * @return MelAction
 */
function mel_next_missing($st, $hash, $errors)
{
    Mel::$next = isset($st['pack_only']) ? null : $st;
    $ep = sprintf('S%02dE%02d', $st['s'], $st['e']);
    mel_log($errors['lines'] ? "next: -> dialog: $ep: " . mel_errors_total($errors) . ' reasons, the release not found' :
        "next: -> dialog: $ep is not in this release");
    $lang = $st['lang'];
    $se = mel_se($st['s'], $st['e']);
    $title = $errors['lines'] ? mel_tr($lang, 'next_sources_failed', $se) : mel_tr($lang, 'next_no_episode', $se);
    return mel_dialog($title, mel_errors_labels($lang, $errors), array(
        mel_tr($lang, 'next_pick') => mel_input('next_pick', array('k' => $st['movie']['dune_id'], 's' => strval($st['s']),
            'e' => strval($st['e']), 'h' => $hash, 'rid' => $st['rid'], 'p' => Mel::$settings['prof'])),
        mel_tr($lang, 'next_stop') => mel_input('next_stop')));
}

// "Choose a release": the list of the episode (fetched again after a
// php_server restart).
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_next_pick($in)
{
    $st = Mel::$next;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_next_prof($in);
        $p = mel_next_params($in);
        $res = $p ? mel_next_fetch($p[0], $p[1], $p[2]) : array('error' => 'err_list_expired', 'detail' => '');
        if (isset($res['error']))
            return mel_close_and(mel_error($res['error'], $res['detail']));
        $st = $res['st'];
    }
    Mel::$next = null;
    mel_log(sprintf('next: -> list of S%02dE%02d', $st['s'], $st['e']));
    return mel_close_and(mel_open_list($st));
}
