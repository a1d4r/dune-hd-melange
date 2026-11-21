<?php
// The start of the files of the media library of the Dune (the own library, library.php): a copy of a movie or of an
// episode played by the path of its mount (mel_lib_play), the check of its copies, the lazy episodes of the disk
// (lib_next), "Continue" of play_action of wh_mode local (a copy to choose: the own card and the window W2 over it,
// mel_lib_win_card of deep_link.php).

// A lazy episode of a playlist of the disk (mel_lib_play), "lib_next:<H>:<Y>:<q>:<e>:<cid>" -> lib_next
// (mel_lazy_action): H the card of the history, Y the series of the index, the id of its copy (mel_lib_cid: of the one
// of mirrors chosen, not of their key - the next one of the same folder), '-' none on the disk.
/**
 * @param string $hid
 * @param string $y
 * @param int $q
 * @param int $e
 * @param string $cid
 * @return string
 */
function mel_lib_lazy_url($hid, $y, $q, $e, $cid)
{
    return "lib_next:$hid:$y:$q:$e:$cid";
}

// get_vod_stream_url of lazy episode $pb of the disk (mel_lib_lazy_url; mel_lazy_action) -> error_action:
// handle_user_input lib_next (mel_lib_next), its copy checked there. A bad one: the error.
/**
 * @param string $pb
 * @return MelAction
 */
function mel_lib_lazy($pb)
{
    if (!preg_match('/^lib_next:(' . MEL_DUNE_ID . '):(' . MEL_DUNE_ID . '):([1-9][0-9]{0,3}):([1-9][0-9]{0,4}):(' .
        MEL_LIB_CID . '|-)$/D', $pb, $m))
    {
        mel_log('lazy: bad playback_url ' . mel_cut(mel_mask($pb), 100));
        return mel_error('err_list_expired', '', true);
    }
    mel_log("lazy: S{$m[3]}E{$m[4]} of the disk {$m[5]} -> handle_user_input lib_next");
    return mel_input('lib_next', array('k' => $m[1], 'y' => $m[2], 's' => $m[3], 'e' => $m[4], 'c' => $m[5]));
}

// --- A copy of a movie of the media library of the Dune (library.php): its file played by the path of its mount.

// vod_play of the copy of title $mv the check found there ($go of mel_lib_start: copy, from at s - 0: the start, the
// player offers the place of the record itself; the i-th of n of its title, for the log; dur - the length of its MKV).
// No stream check, no TorrServer. Linked by the card $mv['dune_id'] (H, set by the caller: of the own card, else of the
// index): recent_uid "aio:H:q:e:lib-...", mdb_info, "Continue" back to play_action with wh_mode local; no H - unlinked.
// A movie ($ep null): one item, no markers. An episode ($ep: y - the series of the index, q, e - the episode, the season
// as asked, eps - the copies of the series "q:e" => copies): its markers by the length of its MKV (0 unknown; the file
// is not read here); linked, a playlist of its season and E1 of the next one as mel_play (by season_numbers; none, the
// episode past them, a season over MEL_EPISODES_MAX, H or Y not of MEL_DUNE_ID - alone), the others lazy
// (mel_lib_lazy_url; '-' none on the disk): the copy by mel_lib_ep_cid - F the pack of this copy (the choice just
// made), the record only of an episode not watched;
// the card saved for lib_next (mel_movie_save). The log has the key, no path, no name.
/**
 * @param MelMovie $mv
 * @param MelLibHere $go
 * @param string $lang
 * @param MelLibEp|null $ep
 * @return MelAction
 */
function mel_lib_play($mv, $go, $lang, $ep = null)
{
    $copy = $go['copy'];
    $pos = $go['at'];
    $linked = $mv['dune_id'] !== '';
    $q = $ep ? $ep['q'] : -1;
    $e = $ep ? $ep['e'] : -1;
    $seasons = $mv['seasons'];
    // H and Y go into lib_next: (mel_lib_lazy_url), H into a file name (mel_movie_save): of no form of an id - alone.
    $ids = $ep && preg_match('/^' . MEL_DUNE_ID . '$/D', $mv['dune_id']) &&
        preg_match('/^' . MEL_DUNE_ID . '$/D', $ep['y']);
    $season = $ids && $e > 0 && isset($seasons[$q]) && $e <= $seasons[$q] && $seasons[$q] <= MEL_EPISODES_MAX;
    $plan = $season ? mel_play_plan($seasons, $q, array()) : array();
    // The start one not in the plan (never by season_numbers): alone, no other one started in its place.
    if (!in_array(array($q, $e), $plan, true))
        list($season, $plan) = array(false, array(array($q, $e)));
    $list = count($plan) > 1;
    $ser = mel_vod_item($mv, $q, $e, $copy['url'], $linked ? $copy['key'] : '', $list);
    if ($ep)
    {
        $mk = mel_markers($mv['imdb'], $q, $e, $go['dur'], '', '');
        if ($mk)
            $ser[PluginVodSeriesInfo::markers] = $mk;
    }
    $y = $ep ? $ep['y'] : '';
    $eps = $ep ? $ep['eps'] : array();
    list($recs) = $list ? mel_lib_ep_ctx($mv['dune_id'], $y, true) : array(array());
    $ctx = array($recs, array(mel_lib_pack($copy)));
    $series = array();
    $ndx = 0;
    $whys = array();
    foreach ($plan as $k => $p)
    {
        list($pq, $pe) = $p;
        if ($pq === $q && $pe === $e)
        {
            $ndx = $k;
            $series[] = $ser;
            continue;
        }
        $se = mel_lib_se($pq, $pe);
        list($cid, $why) = mel_lib_ep_cid(isset($eps[$se]) ? $eps[$se] : array(), $ctx, $se, true);
        if ($cid === '')
            $cid = '-';
        $whys[$why] = (isset($whys[$why]) ? $whys[$why] : 0) + 1;
        $series[] = array(
            PluginVodSeriesInfo::name => mel_ser_name($mv, $pq, $pe, true),
            PluginVodSeriesInfo::playback_url => mel_lib_lazy_url($mv['dune_id'], $y, $pq, $pe, $cid),
            PluginVodSeriesInfo::playback_url_is_stream_url => false);
    }
    $p = strrpos($copy['path'], '/');
    $info = array(
        PluginVodInfo::id => 'aio.' . mel_lpv_key($mv),
        PluginVodInfo::name => $list ? $mv['title'] : mel_ser_name($mv, $q, $e, false),
        // The second line of INFO: the file, as a release by its name.
        PluginVodInfo::sub_name => mel_clean($p === false ? $copy['path'] : substr($copy['path'], $p + 1)),
        PluginVodInfo::poster_url => $mv['poster'],
        PluginVodInfo::series => $series,
        PluginVodInfo::initial_series_ndx => $ndx,
        PluginVodInfo::description => implode(' · ', array_filter(array($copy['res'],
            $copy['size'] > 0 ? mel_size_str($copy['size'], $lang) : ''), 'strlen')),
        PluginVodInfo::buffering_ms => 3000,
        PluginVodInfo::keep_playing_on_reenter => true,
        PluginVodInfo::skip_dummy_player_on_error => false);
    if ($pos > 0)
        $info[PluginVodInfo::initial_position_ms] = $pos * 1000;
    if ($linked)
    {
        $info[PluginVodInfo::recent_plugin] = 'shell_ext';
        $info[PluginVodInfo::recent_action_params] = mel_recent_params($mv['dune_id'], $copy['key'], 'local', '');
    }
    if ($season)
        mel_movie_save($mv, $lang);
    mel_pg_link_movie($mv);
    mel_log("play: local {$copy['key']}" . ($ep ? " S{$q}E{$e}" : '') . " pos=$pos linked=" . ($linked ? 1 : 0) .
        ($ep ? ', playlist ' . count($series) : '') . ', copy ' . ($go['i'] + 1) . " of {$go['n']}" .
        ($copy['res'] !== '' ? ", {$copy['res']}" : ''));
    if ($whys)
    {
        ksort($whys);
        $w = array();
        foreach ($whys as $why => $c)
            $w[] = "$why $c";
        mel_log("library: playlist S$q of the disk: " . implode(', ', $w));
    }
    return array(
        GuiAction::handler_string_id => PLUGIN_VOD_PLAY_ACTION_ID,
        GuiAction::data => array(PluginVodPlayActionData::vod_info => $info));
}

// The check of the copies of a movie or an episode before a start, the own card, play_action and the lazy episodes
// alike (the caller shows what it gives, a line or two): of its copies now $copies (mel_lib_copies, mel_lib_ep_copies;
// the best first), of $first - the key of a record (its mirrors in their order, the first there) or the id of the copy
// chosen ('' none; not among them - logged, as none) - from $first_pos s, if its file is there (mel_lib_here); else (or
// none such) by the copies left from $pos s - one: it, if there; several: their choice to the caller (pick), $auto - the
// first there of them in their order (no window: under the player). -> MelLibGo: copy, at (the place), i of n (for the
// log), dur (of an episode: the length of its MKV, read by the same check, 0 unknown; of a movie 0) - the file there;
// pick - the copies left, 2+; gone 'no' | 'slow' - none left. Of the mirrors of a key the copy of a pack of $f_packs
// first (mel_lib_ep_of: the rule of mel_lib_ep_cid; none - in their order). One check a copy at most; a copy that does
// not answer ('slow') takes the copies of all the shares of its server out unchecked (srv: the NAS sleeps or is off -
// one wait, not one a share; a mirror on another server is still checked), and no check starts that the operation
// (MEL_OP_BUDGET) has no time left for (of an episode: and its read).
/**
 * @param list<MelLibCopy> $copies
 * @param string $first
 * @param int $first_pos
 * @param int $pos
 * @param bool $auto
 * @param list<string> $f_packs
 * @return MelLibGo
 */
function mel_lib_start($copies, $first, $first_pos, $pos, $auto, $f_packs = array())
{
    $left = $copies;
    $want = mel_lib_ep_of($copies, $first, $f_packs);
    if ($first !== '')
        mel_log("library: start $first: " . (count($want) === 1 ? 'its copy' : ($want ? 'its ' . count($want) . ' copies' :
            'not among ' . count($copies) . ' copies')));
    $at = $first_pos;
    $slow = false;
    for (;;)
    {
        if (!$want)
        {
            if (!$left)
                return array('gone' => $slow ? 'slow' : 'no');
            if (count($left) > 1 && !$auto)
                return array('pick' => $left);
            $want = $left;
            $at = $pos;
        }
        $try = array_shift($want);
        // An episode: the markers by the length of its MKV (a movie has none).
        $mkv = $try['e'] > 0;
        $need = (int) (MEL_LIB_HERE_S + ($mkv ? ceil(MEL_LIB_MKV_MS / 1000) : 0));
        if (mel_op_total($need, $need, "library: here {$try['key']}") === 0)
            return array('gone' => 'slow');
        $dur = 0.0;
        $here = mel_lib_here($try, $mkv, $dur);
        if ($here === 'yes')
        {
            $i = 0;
            foreach ($copies as $n => $c)
            {
                if ($c['cid'] === $try['cid'])
                    $i = $n;
            }
            return array('copy' => $try, 'at' => $at, 'i' => $i, 'n' => count($copies), 'dur' => $dur);
        }
        $slow = $slow || $here === 'slow';
        $left = mel_lib_copies_but($left, $try, $here === 'slow');
        $want = mel_lib_copies_but($want, $try, $here === 'slow');
    }
}

// The copies $copies but copy $try, and, $slow (it did not answer), but all of its server (srv).
/**
 * @param list<MelLibCopy> $copies
 * @param MelLibCopy $try
 * @param bool $slow
 * @return list<MelLibCopy>
 */
function mel_lib_copies_but($copies, $try, $slow)
{
    $res = array();
    foreach ($copies as $c)
    {
        if ($c['cid'] !== $try['cid'] && !($slow && $c['srv'] === $try['srv']))
            $res[] = $c;
    }
    return $res;
}

// H, the card of the history of a movie with copies on the disk: its card of the Dune $dune_id, else its movie $y of the
// index (mel_lib_mid). The own card (mel_oc_lib) and "Continue" (mel_lib_resume) alike.
/**
 * @param string $dune_id
 * @param string $y
 * @return string
 */
function mel_lib_hid($dune_id, $y)
{
    return $dune_id !== '' ? $dune_id : $y;
}

// Where a copy of the movie of card of history $hid starts on the own card (C2-C4 of the spec), of its copies $copies
// now (mel_lib_copies) -> array($first, $first_pos, $pos) of mel_lib_start. $first: the copy of id $k chosen in the menu
// of the copies, '' ("Смотреть") - the key of the latest record of a copy of the card (mel_wh_lib_last; '' none). The
// place $pos: of the latest record of the movie of any supplier (mel_wh_movie_last; B1 of the owner,
// step 1: of the newer record), 0 watched. $first_pos: 0 when $first is the copy of our record and that record is the
// latest (the player offers its place itself), else $pos. Episode S$q E$e ($e >= 1, the season as asked; $copies of it):
// the same by the records of the episode - $k the choice of mel_lib_ep_pick or the id of a copy chosen, the record of a
// copy of it (mel_wh_lib_ep_last), the place of its latest record (mel_wh_ep_last, by the files $fix).
/**
 * @param string $hid
 * @param list<MelLibCopy> $copies
 * @param string $k
 * @param int $q
 * @param int $e
 * @param array<string, array{int, int}|string> $fix hash => array(s, e), or 'skip' (mel_lib_ep_hashes)
 * @return array{string, int, int}
 */
function mel_lib_place($hid, $copies, $k, $q = -1, $e = -1, $fix = array())
{
    $ep = $e >= 1;
    if (!$ep)
        $q = $e = -1;
    $rec = $ep ? mel_wh_lib_ep_last($hid, $q, $e) : mel_wh_lib_last($hid);
    $rk = $rec ? $rec['key'] : '';
    $any = $ep ? mel_wh_ep_last($hid, $q, $e, $fix) : mel_wh_movie_last($hid);
    $pos = $any && !mel_wh_full($any[0], $any[1]) ? intval($any[0]) : 0;
    $u = $any && $rk !== '' ? mel_wh_uid($any[2], $hid) : null;
    $own = $u !== null && $u[0] === $q && $u[1] === $e && $u[2] === $rk;
    $chosen = $k !== '' ? mel_lib_copies_of($copies, $k) : array();
    $of_rec = $k !== '' ? $chosen && $chosen[0]['key'] === $rk : $rk !== '';
    mel_log('library: place' . ($ep ? " S{$q}E{$e}" : '') . ': ' . ($rk !== '' ? "the record $rk" : 'no record') .
        ", at $pos s" . ($own ? ', the latest' : ''));
    return array($k !== '' ? $k : $rk, $of_rec && $own ? 0 : $pos, $pos);
}

// The copy of an episode to play, one rule for all the ways (the own card, the playlist, "Continue" of the native; owner
// 3: after a change of the copy the next episodes are of the new folder). Pure, no history, no index: of its copies now
// $copies_ep (the best first, mel_lib_ep_copies), the key $rec_key of our record of the episode that still holds ('' none,
// mel_lib_ep_ctx), the packs F $f_packs of the last choice of a folder (mirrors: several; none - no F), $auto - no window
// (the playlist, the line of the card). -> array(the key of the record or the id of a copy, '' - the window; why):
// record - the copy of the record; folder - of a pack F (the first of several); near - of the pack nearest to F
// (mel_lib_pack_near, the most of the packs F), near-best - the first of several as near; one - the only copy; auto-best -
// the first; window - none of these; none - no copy. No log: the caller logs a line an action.
/**
 * @param list<MelLibCopy> $copies_ep
 * @param string $rec_key
 * @param list<string> $f_packs
 * @param bool $auto
 * @return array{string, string}
 */
function mel_lib_ep_pick($copies_ep, $rec_key, $f_packs, $auto)
{
    if (!$copies_ep)
        return array('', 'none');
    $top = $copies_ep[0];
    if ($rec_key !== '' && mel_lib_copies_of($copies_ep, $rec_key))
        $why = 'record';
    else if ($f_packs)
    {
        $why = '';
        $at = -2;
        $ties = 0;
        foreach ($copies_ep as $c)
        {
            $p = mel_lib_pack($c);
            if (in_array($p, $f_packs, true))
            {
                list($top, $why) = array($c, 'folder');
                break;
            }
            $d = -1;
            foreach ($f_packs as $f)
                $d = max($d, mel_lib_pack_near($f, $p));
            if ($d > $at)
                list($top, $at, $ties) = array($c, $d, 1);
            else if ($d === $at)
                $ties++;
        }
        if ($why === '')
            $why = $ties > 1 ? 'near-best' : 'near';
    }
    else if (count($copies_ep) === 1)
        $why = 'one';
    else
        $why = $auto ? 'auto-best' : 'window';
    return array($why === 'record' ? $rec_key : ($why === 'window' ? '' : $top['cid']), $why);
}

// The copy OK plays of episode "q:e" $se, of its copies now $copies_ep (mel_lib_ep_copies), by what mel_lib_ep_ctx
// gives $ctx (array("q:e" => the key of our record, the packs F)) - one rule for the line of the card (lib_cur), the
// playlist and the start (owner 3, the review of 2в1б): mel_lib_ep_pick, and the key of a record, shared by mirrors -
// of them the copy of a pack F, else the first (mel_lib_ep_of). -> array(its id, '' - none, or the window of not
// $auto; why of mel_lib_ep_pick).
/**
 * @param list<MelLibCopy> $copies_ep
 * @param array{array<string, string>, list<string>} $ctx
 * @param string $se
 * @param bool $auto
 * @return array{string, string}
 */
function mel_lib_ep_cid($copies_ep, $ctx, $se, $auto)
{
    list($recs, $f) = $ctx;
    list($k, $why) = mel_lib_ep_pick($copies_ep, isset($recs[$se]) ? $recs[$se] : '', $f, $auto);
    $of = mel_lib_ep_of($copies_ep, $k, $f);
    return array($of ? $of[0]['cid'] : '', $why);
}

// The copies of copies $copies of $k (mel_lib_copies_of: the key of a record - its mirrors, or the id of one), of the
// mirrors the copies of a pack of $f_packs first, the rest after them, each in their order.
/**
 * @param list<MelLibCopy> $copies
 * @param string $k
 * @param list<string> $f_packs
 * @return list<MelLibCopy>
 */
function mel_lib_ep_of($copies, $k, $f_packs)
{
    $in = array();
    $out = array();
    foreach (mel_lib_copies_of($copies, $k) as $c)
    {
        if (in_array(mel_lib_pack($c), $f_packs, true))
            $in[] = $c;
        else
            $out[] = $c;
    }
    return array_merge($in, $out);
}

// What mel_lib_ep_pick goes by for the episodes of the series $y of the index, card of history $hid -> array("q:e" =>
// $rec_key, $f_packs), the history read once: of an episode (the season as asked) the key of our record of a copy of
// it (mel_wh_lib_eps) while that record is not watched or - not $unfinished - it is the last choice of a folder (its end
// not less than of the latest record of a copy of the card, mel_wh_lib_last); an episode not in the map goes of the
// pack F. F - the packs of the key of that latest record by the index (mel_lib_key_packs: its copy may be not mounted
// now, "the same folder" is there). The playlist ($unfinished: F the start copy) and "Continue" of the native (F the
// key of the record) take their own F.
/**
 * @param string $hid
 * @param string $y
 * @param bool $unfinished
 * @return array{array<string, string>, list<string>}
 */
function mel_lib_ep_ctx($hid, $y, $unfinished = false)
{
    $last = mel_wh_lib_last($hid);
    $keys = array();
    foreach (mel_wh_lib_eps($hid) as $se => $rec)
    {
        if (!mel_wh_full($rec['pos'], $rec['len']) || (!$unfinished && $last && $rec['end'] >= $last['end']))
            $keys[$se] = $rec['key'];
    }
    return array($keys, $last ? mel_lib_key_packs($y, $last['key']) : array());
}

// Where episode S$q E$e of card of history $hid starts when no copy of it is chosen (OK on the own card, "Continue" of
// the native of a watched episode; C2, C7), of its copies now $copies (mel_lib_ep_copies, the best first): the copy by
// mel_lib_ep_pick (no $auto) of what mel_lib_ep_ctx gives $ctx, as mel_lib_ep_cid takes it (array("q:e" => the key of
// our record that still holds, the packs F); of C7 F the packs of the key of its record), the place by mel_lib_place of
// the native records by their files $fix (mel_lib_ep_hashes by the invariant, mel_lib_ep_same). -> array($first,
// $first_pos, $pos) of mel_lib_start (the mirrors of a key: the copy of F first there, mel_lib_ep_of - the caller gives
// it $ctx[1]); $first '' - the window (no copy to take: not the key of the record mel_lib_place would give), from $pos.
// The log: "library: episode S3E4: <why>" (no path).
/**
 * @param string $hid
 * @param list<MelLibCopy> $copies
 * @param int $q
 * @param int $e
 * @param array{array<string, string>, list<string>} $ctx
 * @param array<string, array{int, int}|string> $fix
 * @return array{string, int, int}
 */
function mel_lib_ep_place($hid, $copies, $q, $e, $ctx, $fix)
{
    list($recs, $f) = $ctx;
    $se = mel_lib_se($q, $e);
    list($first, $why) = mel_lib_ep_pick($copies, isset($recs[$se]) ? $recs[$se] : '', $f, false);
    mel_log("library: episode S{$q}E{$e}: $why");
    list($k, $first_pos, $pos) = mel_lib_place($hid, $copies, $first, $q, $e, $fix);
    return $first === '' ? array('', $pos, $pos) : array($k, $first_pos, $pos);
}

// "Continue" of the copy of key $key of movie $mv from $pos s (play_action of wh_mode local): the fields $m of its
// movie_str find the movie in the index (mel_lib_title, mel_lib_mid), the card of the record H is its card of the Dune,
// else of the index; its TMDB for the own card of the copies (tmdb, mel_lib_win_card). The copy of the key from $pos
// (mel_lib_resume_go).
/**
 * @param MelMovie $mv
 * @param array<mixed> $m
 * @param string $key
 * @param int $pos
 * @param string $lang
 * @return MelAction
 */
function mel_lib_resume($mv, $m, $key, $pos, $lang)
{
    $t = mel_lib_title($m, false);
    $y = mel_lib_mid($t);
    $copies = $y !== '' ? mel_lib_copies($y) : array();
    $mv['dune_id'] = mel_lib_hid($mv['dune_id'], $y);
    if ($t['tmdb'] !== '')
        $mv['tmdb'] = $t['tmdb'];
    mel_log("resume: local $key pos=$pos");
    return mel_lib_resume_go($mv, $copies, $key, $pos, $pos, $lang);
}

// The invariant of the three numberings, one place for the own card and "Continue" of the native (C7): an episode
// S$s E$e of the index is taken only when the meta of the title has it and asks its season as it is (mel_cm_req_s =
// s): then the season of the index is the season of the card, the season of the request and of the history (the
// key "s:e" of the stills, mel_cm_hkey, our uid "aio:H:s:e:...", the episodes of a playlist) - none of them counts
// the numbers of a copy anew. Pure, it knows no numbering: $seasons comes with the invariant in it - season => the
// set of the numbers of its episodes (number => true), only the seasons asked as they are (the own card: the episodes
// of its meta, the ones not out and of no date too, of the seasons with mel_cm_req_s = s, mel_oc_lib_eps - a season of
// E9-E16 has no E1-E8; C7 and lib_next: the counts of season_numbers, mel_lib_mv_eps - each season 1..N, no episode
// numbers there to look at), $cin - the runs of the numbering of Cinemeta (aio_cin, mel_cin_load). With $cin the
// seasons are of Cinemeta (by_cin: asked as they are), an episode there when a run of its season has it (mel_cin_abs);
// without, when the set of its season in $seasons has its number. So a season numbered on by TMDB (One Piece S2 is
// E62-E77, asked as season 1) is not there - its files give no badge; a season of the index the meta has not (the
// numbers of TMDB of a series with no pairs of Cinemeta: its season 3 is no season there) - none either. Of the copies
// $eps ("s:e" of the index => copies, mel_lib_ep_copies) and the hashes $hashes (mel_lib_ep_hashes) -> array(the
// copies of the episodes taken, the hashes of the files of them and of the files dropped - 'skip' as it is; a file of an
// episode not taken is not in it: a native record of it stays as it is, mel_wh_fix_se).
/**
 * @param array<string, list<MelLibCopy>> $eps
 * @param array<string, array{int, int}|string> $hashes
 * @param array<int, array<int, true>> $seasons
 * @param list<array{int, int, int}> $cin
 * @return array{array<string, list<MelLibCopy>>, array<string, array{int, int}|string>}
 */
function mel_lib_ep_same($eps, $hashes, $seasons, $cin)
{
    $ok = array();
    foreach ($eps as $k => $copies)
    {
        if (mel_lib_ep_same_one($copies[0]['s'], $copies[0]['e'], $seasons, $cin))
            $ok[$k] = $copies;
    }
    $fix = array();
    foreach ($hashes as $h => $se)
    {
        if (!is_array($se) || mel_lib_ep_same_one($se[0], $se[1], $seasons, $cin))
            $fix[$h] = $se;
    }
    return array($ok, $fix);
}

// Episode S$s E$e of the index by the invariant of mel_lib_ep_same.
/**
 * @param int $s
 * @param int $e
 * @param array<int, array<int, true>> $seasons
 * @param list<array{int, int, int}> $cin
 * @return bool
 */
function mel_lib_ep_same_one($s, $e, $seasons, $cin)
{
    if ($cin)
        return mel_cin_abs($cin, $s, $e) > 0;
    return isset($seasons[$s][$e]);
}

// The episodes of series $y of the index for card $mv by the invariant of the numberings (mel_lib_ep_same), of C7 and
// of lib_next (its card saved by mel_lib_play, of the own card too): the seasons of $mv['seasons'] - the counts of
// season_numbers, each season 1..N (no episode numbers there to look at) - and its runs of Cinemeta $mv['cin']. ->
// array(their copies "q:e" => copies, mel_lib_ep_copies; the hashes of the files, hash => array(q, e) | 'skip',
// mel_lib_ep_hashes).
/**
 * @param MelMovie $mv
 * @param string $y
 * @return array{array<string, list<MelLibCopy>>, array<string, array{int, int}|string>}
 */
function mel_lib_mv_eps($mv, $y)
{
    $seasons = array();
    foreach ($mv['seasons'] as $sn => $n)
        $seasons[$sn] = $n >= 1 ? array_fill_keys(range(1, $n), true) : array();
    return mel_lib_ep_same(mel_lib_ep_copies($y), mel_lib_ep_hashes($y), $seasons, $mv['cin']);
}

// "Continue" of an episode of a copy on the disk (C7: play_action of wh_mode local, the record $r of mel_resume) of
// series $mv, the fields $m of its movie_str. The key of the record: of its uid when that is of its episode
// "aio:H:<wh_s>:<wh_e>:lib-..." (a vod_play has one recent_action_params, of its start episode), else wh_release; a
// record of season 1 has no s (wh_s -1) - the season of that uid, else 1. The episode by mel_wh_resume_ep: not
// watched - its copy of the key from its place; watched - the next one, its copy and its place by mel_lib_ep_place (the
// record: ours of it not watched; F the packs of the key of the record; the native records by their files). -> its start
// (mel_lib_resume_go: several to choose - the own card and the window W2 over it); null - none of it on the disk (no copy, the series not in the index, no episode after a
// watched one): $se the episode for the list of its releases; null both - a record of no episode, as of none.
/**
 * @param MelMovie $mv
 * @param array<mixed> $m
 * @param MelResume $r
 * @param string $lang
 * @param array{int, int}|null $se
 * @param-out array{int, int}|null $se
 * @return MelAction|null
 */
function mel_lib_resume_ep($mv, $m, $r, $lang, &$se)
{
    $se = null;
    $e0 = $r['e'];
    if ($e0 < 1)
        return null;
    // The series in the index: H its card of the Dune, else Y; its TMDB for the own card of the copies (mel_lib_win_card).
    $t = mel_lib_title($m, true);
    $y = mel_lib_mid($t);
    $mv['dune_id'] = mel_lib_hid($mv['dune_id'], $y);
    if ($t['tmdb'] !== '')
        $mv['tmdb'] = $t['tmdb'];
    // The copies of its episodes and the hashes of its files by the invariant of the numberings (mel_lib_mv_eps); not
    // found: none.
    list($eps, $fix) = $y !== '' ? mel_lib_mv_eps($mv, $y) : array(array(), array());
    $hid = $mv['dune_id'];
    $u = mel_wh_uid($r['uid'], $hid);
    // A record of season 1 has no s (the player writes it of S2+ only; wh_s -1): the season of its uid, else 1.
    if ($r['s'] < 1)
        $r['s'] = $u && $u[1] === $e0 && $u[0] >= 1 ? $u[0] : 1;
    $s0 = $r['s'];
    $key = $u && $u[0] === $s0 && $u[1] === $e0 && mel_lib_key_ok($u[2]) ? $u[2] : $r['hash'];
    list($r, $q, $e) = mel_wh_resume_ep($mv, $r);
    // The key of the episode (not $se: the episode of the list).
    $qe = mel_lib_se($q, $e);
    $copies = isset($eps[$qe]) ? $eps[$qe] : array();
    $next = !$r || $r['next'];
    $tag = "resume: local series S{$s0}E{$e0} -> S{$q}E{$e} (" . ($next ? 'next' : 'same') . ')';
    if (!$r || !$copies)
    {
        mel_log("$tag, not on the disk -> its list");
        $se = array($q, $e);
        return null;
    }
    $ep = array('y' => $y, 'q' => $q, 'e' => $e, 'eps' => $eps);
    if (!$next)
    {
        mel_log("$tag, the key of the record $key");
        return mel_lib_resume_go($mv, $copies, $key, $r['pos'], $r['pos'], $lang, $ep);
    }
    mel_log($tag);
    // F the packs of the key of the record (not of the latest record of the card).
    list($recs) = mel_lib_ep_ctx($hid, $y, true);
    $ctx = array($recs, mel_lib_key_packs($y, $key));
    // The native records of the series by their files (mel_wh_fix_se). No copy to take (the window, $k ''): the own card
    // and the window W2 of them, none checked. Of the mirrors of a record the copy of F first (mel_lib_ep_cid).
    list($k, $first_pos, $pos) = mel_lib_ep_place($hid, $copies, $q, $e, $ctx, $fix);
    return mel_lib_resume_go($mv, $copies, $k, $first_pos, $pos, $lang, $ep, $ctx[1]);
}

// The start of "Continue" of a copy on the disk of movie $mv (H set; its TMDB, tmdb, for the own card), of its copies
// $copies, $ep null; of an episode ($ep: of mel_lib_ep_copies, $copies of it). By mel_lib_start of $first, $first_pos,
// $pos, the packs F $f_packs: the copy there - its vod_play; the copies left - the own card of the title and the window
// W2 of them over it (mel_lib_win_card; owner 1 (б)), no own card (no id of the title, or its card has none of them) -
// the first there of them in their order from $pos (as under the player); none - mel_lib_gone.
/**
 * @param MelMovie $mv
 * @param list<MelLibCopy> $copies
 * @param string $first
 * @param int $first_pos
 * @param int $pos
 * @param string $lang
 * @param MelLibEp|null $ep
 * @param list<string> $f_packs
 * @return MelAction
 */
function mel_lib_resume_go($mv, $copies, $first, $first_pos, $pos, $lang, $ep = null, $f_packs = array())
{
    $go = mel_lib_start($copies, $first, $first_pos, $pos, false, $f_packs);
    if (isset($go['pick']))
    {
        $a = mel_lib_win_card($mv, $go['pick'], $ep ? $ep['q'] : -1, $ep ? $ep['e'] : -1);
        if ($a)
            return $a;
        $go = mel_lib_start($go['pick'], '', $pos, $pos, true, $f_packs);
    }
    if (isset($go['copy']))
        return mel_lib_play($mv, $go, $lang, $ep);
    $why = isset($go['gone']) ? $go['gone'] : 'no';
    return $ep ? mel_lib_gone($why, $lang, $ep['q'], $ep['e']) : mel_lib_gone($why);
}

// What a start says when the file of a movie is not there and no other copy is (C5, owner 19.11.2026: a message with
// OK, the card stays): $why 'slow' - the disk did not answer in time, else the file is gone. Of episode S$q E$e ($e >=
// 1, "Continue" of the native): the same of the episode, in the words of the dialog of lib_next. All of it here:
// another look of it is one edit.
/**
 * @param string $why
 * @param string $lang
 * @param int $q
 * @param int $e
 * @return MelAction
 */
function mel_lib_gone($why, $lang = '', $q = -1, $e = -1)
{
    if ($e < 1)
        return mel_error($why === 'slow' ? 'lib_slow' : 'lib_gone');
    $k = $why === 'slow' ? 'lib_next_slow' : 'lib_next_no_disk';
    $a = mel_error($k);
    $a[GuiAction::data][PluginShowErrorActionData::title] = mel_tr($lang, $k, mel_se($q, $e));
    return $a;
}

// Seconds from the start of the operation (Mel::$t0) lib_next answers a new vod_play in, at the soonest: the native writes
// the record of the episode stopped ~0.9 s past its stop, and a vod_play before that takes it without recent_uid - the
// record is dropped (Dune 21.11.2026; next of the releases answers in 1.2-2 s, its record is there). Tests define it first.
if (!defined('MEL_LIB_NEXT_MIN'))
    define('MEL_LIB_NEXT_MIN', 1.5);

// lib_next: a lazy episode of a playlist of the disk (mel_lib_lazy_url, mel_lazy_action) - k the card of the history H
// (saved by mel_lib_play), y the series of the index, s, e the episode, c the id of its copy ('-' none on the disk).
// Its copies now, that one first (mel_lib_start; gone - the others in order, $auto: no window under the player) -> a
// new vod_play of it with its markers; none there or no copy - the dialog (mel_lib_next_missing). A bad input, the
// card gone - "the list expired".
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_lib_next($in)
{
    $ep = mel_lib_next_ep($in);
    // The language of the dialogs: of the card by a valid key, though the rest is bad (as mel_next).
    $saved = isset($in->k) && is_string($in->k) && preg_match('/^' . MEL_DUNE_ID . '$/D', $in->k) ?
        mel_movie_load($in->k) : null;
    $lang = $saved ? $saved['lang'] : '';
    $y = isset($in->y) && is_string($in->y) && preg_match('/^' . MEL_DUNE_ID . '$/D', $in->y) ? $in->y : '';
    $c = isset($in->c) && (mel_lib_cid_ok($in->c) || $in->c === '-') ? strval($in->c) : '';
    if (!$ep || !$saved || $y === '' || $c === '')
    {
        mel_log('lib_next: bad params or no card');
        return mel_next_failed($lang, '', 'err_list_expired');
    }
    list(, $q, $e) = $ep;
    $mv = $saved['movie'];
    mel_log("lib_next: S{$q}E{$e} $c");
    if ($c === '-')
        return mel_lib_next_missing($mv, $q, $e, 'none', $lang);
    // The episodes by the invariant of the numberings, as C7 (mel_lib_mv_eps): the playlist anew of them, not of the
    // index as it is - a season of the index not the season of the card gives no file to the episodes of the card.
    list($eps) = mel_lib_mv_eps($mv, $y);
    $se = mel_lib_se($q, $e);
    $go = mel_lib_start(isset($eps[$se]) ? $eps[$se] : array(), $c, 0, 0, true);
    if (isset($go['copy']))
    {
        $a = mel_lib_play($mv, $go, $lang, array('y' => $y, 'q' => $q, 'e' => $e, 'eps' => $eps));
        // Not sooner than MEL_LIB_NEXT_MIN from the start of the operation: the record of the episode stopped.
        $left = Mel::$t0 > 0 ? MEL_LIB_NEXT_MIN - (microtime(true) - Mel::$t0) : 0;
        if ($left > 0)
        {
            mel_log(sprintf('lib_next: waits %d ms for the record of the episode stopped', $left * 1000));
            usleep(intval($left * 1000000));
        }
        return $a;
    }
    return mel_lib_next_missing($mv, $q, $e, isset($go['gone']) ? $go['gone'] : 'no', $lang);
}

// Params k, s, e of lib_next and lib_next_pick -> array(the card of the history, season, episode) or null.
/**
 * @param stdClass $in
 * @return array{string, int, int}|null
 */
function mel_lib_next_ep($in)
{
    foreach (array('k' => '/^' . MEL_DUNE_ID . '$/D', 's' => '/^[1-9][0-9]{0,3}$/D', 'e' => '/^[1-9][0-9]{0,4}$/D')
        as $k => $re)
    {
        if (!isset($in->$k) || !is_string($in->$k) || !preg_match($re, $in->$k))
            return null;
    }
    return array(strval($in->k), intval($in->s), intval($in->e));
}

// The dialog over the stopped player when the next episode S$q E$e of the disk does not play ($why: none - no copy of
// it on the disk, no - its file not there now, slow - the disk did not answer; owner 3 (a)): "Choose a release" (the
// list of the episode, lib_next_pick) and "Stop", as "not in this release" (mel_next_missing). No IMDb nor TMDB
// (mel_rel_id): "Stop" only (no releases, mel_next_fetch). The title here: another one is one edit.
/**
 * @param MelMovie $mv
 * @param int $q
 * @param int $e
 * @param string $why
 * @param string $lang
 * @return MelAction
 */
function mel_lib_next_missing($mv, $q, $e, $why, $lang)
{
    mel_log(sprintf('lib_next: -> dialog: S%02dE%02d ', $q, $e) . ($why === 'slow' ? 'the disk did not answer' :
        ($why === 'no' ? 'the file not there' : 'not on the disk')) .
        (mel_rel_id($mv) === '' ? ', no IMDb, no TMDB' : ''));
    $buttons = array();
    if (mel_rel_id($mv) !== '')
        $buttons[mel_tr($lang, 'next_pick')] = mel_input('lib_next_pick', array('k' => $mv['dune_id'],
            's' => strval($q), 'e' => strval($e)));
    $buttons[mel_tr($lang, 'next_stop')] = mel_input('next_stop');
    return mel_dialog(mel_tr($lang, $why === 'slow' ? 'lib_next_slow' : 'lib_next_no_disk', mel_se($q, $e)), array(),
        $buttons);
}

// lib_next_pick ("Choose a release" of mel_lib_next_missing): the list of the episode of the main profile (a record of
// the disk has none), as mel_next_pick with no list kept.
/**
 * @param stdClass $in
 * @return MelAction
 */
function mel_lib_next_pick($in)
{
    $ep = mel_lib_next_ep($in);
    $res = $ep ? mel_next_fetch($ep[0], $ep[1], $ep[2]) : array('error' => 'err_list_expired', 'detail' => '');
    if (isset($res['error']))
        return mel_close_and(mel_error($res['error'], $res['detail']));
    mel_log(sprintf('lib_next: -> list of S%02dE%02d', $res['st']['s'], $res['st']['e']));
    return mel_stop_and(mel_open_list($res['st']));
}
