<?php
// The watch state of every card of the history of the Dune (one pass, memory of php_server) for the badges of
// the tiles of the catalog and the tab "TV" and the line of the info panel. Read only of the history
// (history.php has the rules); the pairs card -> IMDb / TMDB of the tiles: links.php. Needs history.php,
// links.php (mel_pg_tile_cards), main.php (mel_log, mel_tr, MEL_SUP_ID, MEL_DUNE_ID), common.php (mel_fw_r), gcomps.php
// (mel_gc_sum, mel_gc_w, mel_gc_cut_w, mel_gc_rect, mel_gc_at, mel_gc_image, mel_gc_geom, mel_gc_next_right), view_catalog.php
// (mel_catv_info_label, MEL_CATV_RATE_PX, MEL_CATV_TEXT_COLOR, MEL_CATV_TEXT_W).

// The state is checked against the files once a second at most: r25 asks ~28 rows a screen.
define('MEL_PG_SIG_S', 1.0);
// Begun: this many s or % of it watched, as the row "Continue".
define('MEL_PG_BEGUN_S', 120);
define('MEL_PG_BEGUN_PCT', 2);
// A record with no number of an episode of a video shorter than this many s (a trailer, a clip of 30 s under the id of
// the card) is not of the state (mel_pg_visit): no tile of "Continue", no badge, no line of the panel by it (decision of
// the owner 16.11.2026); of no length (0, none) it is; an episode of any length is (a short anime). The own card and the
// marks do not go by it.
define('MEL_PG_SHORT_S', 600);
// The badges, in px of the poster 190x285 of a row (movies/movie_helper.php r25, add_movie_ritem;
// the plate of "S1 E3" a tab of the bar, P-B of the designer, decision of the owner 15.11.2026): the bar at the
// bottom, the line over it, the tick over the bar right.
define('MEL_PG_W', 190);
define('MEL_PG_H', 285);
define('MEL_PG_BAR_H', 8);
define('MEL_PG_BAR_ON', '#FFFFFFFF');
define('MEL_PG_BAR_OFF', '#FF404040');
define('MEL_PG_BAR_LINE', '#FF000000');
define('MEL_PG_TICK', 'plugin_file://%shell_ext%/icons/watched_poster.png');
// r24 and older have no watched_poster.png: the small tick of the same look.
define('MEL_PG_TICK_OLD', 'plugin_file://%shell_ext%/icons/watched_small.png');
define('MEL_PG_EP_DARK', '#E6000000');
define('MEL_PG_EP_H', 32);
define('MEL_PG_EP_PAD', 8);
// The line of the info panel (do_get_info_children of movie_helper.php r25): 40 high, the bar
// 324x6, the text 40 px right of it, the tick 20 px right of the text; the bar gives way to the words
// down to BAR_MIN, then the words go down to MIN_PX. Its middle GAP px under the middle of the last
// line of the description (the native one on the frame of the owner, V2b of the designer).
define('MEL_PG_INFO_H', 40);
define('MEL_PG_INFO_BAR_W', 324);
define('MEL_PG_INFO_BAR_MIN', 160);
define('MEL_PG_INFO_BAR_H', 6);
define('MEL_PG_INFO_ON', '#FFE0E0FF');
define('MEL_PG_INFO_PX', 24);
define('MEL_PG_INFO_MIN_PX', 20);
define('MEL_PG_INFO_GAP', 47);
define('MEL_PG_INFO_TICK', 'plugin_file://%shell_ext%/icons/watched_small.png');
// The tick: 40 wide, 20 px right of the text.
define('MEL_PG_INFO_TICK_W', 60);

class MelPg
{
    // The state by movie_id (mel_pg_index) and the signature of the history files it is of.
    /** @var array<string, MelPgEntry> */
    public static $idx = array();
    /** @var string|null */
    public static $sig = null;
    // When the signature was last checked (microtime); 0: never, the state not built.
    /** @var float */
    public static $sig_t = 0.0;
    // What the last line of the log said of an empty state ('no folder', '0 cards'; '': cards), so once.
    /** @var string */
    public static $said = '';
}

// --- The state of the history.

// The record $w of the history the shell keeps for a card (mel_wh_kept) -> a copy of the fields the
// badges, the line of the panel and mel_wh_pick read.
/**
 * @param stdClass $w
 * @return stdClass
 */
function mel_pg_slim($w)
{
    $o = new stdClass();
    foreach (array('uid', 'url', 's', 'e', 'pos', 'len', 'start', 'watched', 'title', 'icon', 'series') as $k)
    {
        if (isset($w->$k) && is_scalar($w->$k))
            $o->$k = $w->$k;
    }
    return $o;
}

// The records of the history, the latest first, into $acc by movie_id: the latest record of any kind
// (w, with its end and whether it is watched by itself), the state of a series of its episode records
// as mel_wh_series_visit makes it, the latest episode record watched or begun (ep: the record, seen:
// watched, mel_wh_ep_watched; null: none). A record of no episode of a video under MEL_PG_SHORT_S is passed over, as if
// none.
// $arg: array(mel_wh_marked_set(), the marks of the own card by movie_id).
/**
 * @param mixed $w
 * @param array{array<int|string, int>, array<string, array<string, int>>} $arg
 * @param array<string, array<string, mixed>> $acc
 * @return bool
 */
function mel_pg_visit($w, $arg, &$acc)
{
    $mid = mel_wh_kept($w);
    if ($mid === '' || !($w instanceof stdClass))
        return false;
    list(, , , $len) = mel_wh_rec($w);
    $ep = isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1;
    if (!$ep && $len > 0 && $len < MEL_PG_SHORT_S)
        return false;
    if (!isset($acc[$mid]))
    {
        list(, , $pos, $len, $uid) = mel_wh_rec($w);
        $end = mel_wh_end($w);
        $mark = isset($arg[0][$uid]) ? $arg[0][$uid] : 0;
        $acc[$mid] = array('w' => mel_pg_slim($w), 'end' => $end,
            'seen' => mel_wh_full($pos, $len) || mel_wh_mark_on($mark, $end), 'st' => array(), 'ep' => null);
    }
    $marks = isset($arg[1][$mid]) ? $arg[1][$mid] : array();
    if ($ep)
    {
        $st = $acc[$mid]['st'];
        $had = isset($st['last']);
        mel_wh_series_visit($w, array($mid, $arg[0], $marks, array()), $st);
        if (!$had && isset($st['last']))
            $st['last'] = mel_pg_slim($st['last']);
        $acc[$mid]['st'] = $st;
        // An episode under the threshold, not watched, is passed over (15 s of the next one after a
        // watched one: the badge of the watched one).
        if ($acc[$mid]['ep'] === null)
        {
            list(, , $pos, $len) = mel_wh_rec($w);
            $seen = mel_wh_ep_watched($w, $arg[0], $marks);
            if ($seen || mel_pg_begun($pos, $len))
                $acc[$mid]['ep'] = array('w' => mel_pg_slim($w), 'seen' => $seen);
        }
    }
    return false;
}

// The marks of the own card in $set (mel_wh_marked_set) by the movie_id of their uids, one pass: movie_id =>
// what mel_wh_marks gives of it.
/**
 * @param array<int|string, int> $set
 * @return array<string, array<string, int>>
 */
function mel_pg_own_marks($set)
{
    $out = array();
    $p = '/^' . MEL_SUP_ID . ':(' . MEL_DUNE_ID . '):(-?[0-9]{1,5}):(-?[0-9]{1,5}):m$/D';
    foreach ($set as $uid => $ts)
    {
        if (preg_match($p, strval($uid), $m) && intval($m[2]) >= 0 && intval($m[3]) >= 0)
            $out[$m[1]][intval($m[2]) . ':' . intval($m[3])] = $ts;
    }
    return $out;
}

// A line of the log of an empty state $what ('no folder', '0 cards') only when it was not the last one said.
/**
 * @param string $what
 * @param string $line
 * @return void
 */
function mel_pg_say($what, $line)
{
    if ($what === '' || MelPg::$said !== $what)
        mel_log($line);
    MelPg::$said = $what;
}

// The state of every card of the history, read again when its files change (mel_wh_sig, watched_movies.txt
// too), the signature checked once in MEL_PG_SIG_S: movie_id => w (the latest record of any supplier or a
// local file, null: none), end (of it), seen (it watched: by position or its uid marked, mel_wh_mark_on),
// movie_mark (the card marked in watched_movies.txt: watched at any time, as watch_history.php r24 and r25
// read it), series (MelWhSeries of its episode records and the marks of the own card, as mel_wh_series; null:
// no episode record and no mark), ep (the latest episode record watched or begun and whether it is watched,
// mel_pg_visit; null: none).
/**
 * @return array<string, MelPgEntry>
 */
function mel_pg_index()
{
    $now = microtime(true);
    if (MelPg::$sig_t > 0 && $now - MelPg::$sig_t >= 0 && $now - MelPg::$sig_t < MEL_PG_SIG_S)
        return MelPg::$idx;
    MelPg::$sig_t = $now;
    if (!is_dir(MEL_WH_PATH) || !is_readable(MEL_WH_PATH))
    {
        MelPg::$sig = null;
        MelPg::$idx = array();
        mel_pg_say('no folder', 'progress: ' . (is_dir(MEL_WH_PATH) ? 'folder not readable' : 'no folder'));
        return MelPg::$idx;
    }
    $sig = mel_wh_sig();
    if ($sig !== null && $sig === MelPg::$sig)
        return MelPg::$idx;
    $set = mel_wh_marked_set();
    $own = mel_pg_own_marks($set);
    $acc = mel_wh_find('progress: index', 'mel_pg_visit', array($set, $own));
    $idx = array();
    foreach ($acc as $mid => $a)
    {
        $st = $a['st'];
        $last = isset($st['last']) && $st['last'] instanceof stdClass ? $st['last'] : null;
        unset($st['last']);
        $idx[strval($mid)] = array('w' => $a['w'], 'end' => $a['end'], 'seen' => $a['seen'], 'movie_mark' => false,
            'series' => $last ? array($last, $st, isset($own[$mid]) ? $own[$mid] : array()) : null, 'ep' => $a['ep']);
    }
    // The marks of the own card with no record of their series, as mel_wh_series.
    foreach ($own as $mid => $marks)
    {
        if (!isset($idx[$mid]))
            $idx[$mid] = array('w' => null, 'end' => 0, 'seen' => false, 'movie_mark' => false, 'series' => null, 'ep' => null);
        $st = $idx[$mid]['series'] ? $idx[$mid]['series'] : array(null, array(), $marks);
        $st[1] = mel_wh_series_fill($st[1], $marks);
        $idx[$mid]['series'] = $st;
    }
    foreach (mel_wh_marked_set('watched_movies.txt') as $mid => $ts)
    {
        $mid = strval($mid);
        if (!isset($idx[$mid]))
            $idx[$mid] = array('w' => null, 'end' => 0, 'seen' => false, 'movie_mark' => true, 'series' => null, 'ep' => null);
        else
            $idx[$mid]['movie_mark'] = true;
    }
    MelPg::$sig = $sig;
    MelPg::$idx = $idx;
    mel_pg_say($idx ? '' : '0 cards', sprintf('progress: %d cards, %.0f ms', count($idx), (microtime(true) - $now) * 1000));
    return $idx;
}

// The state is old: the next mel_pg_index reads the history again (a mark just made).
/**
 * @return void
 */
function mel_pg_stale()
{
    MelPg::$sig = null;
    MelPg::$sig_t = 0.0;
}

// --- Marks "watched" (update_watched_marks of the shell): the row "Continue", POP UP of a tile, MENU of the own card.

// What a mark of card $mid of the type $type would do, of the state of the history only (no request): 'on' (not
// watched), 'off' (watched by a mark: a movie marked in watched_movies.txt or by the mark of its record; a series
// watched whole by its badge, mel_pg_badge: all its episodes out, or marked in watched_movies.txt with no list of
// them, and marked: in watched_movies.txt or a uid of it in watched_files.txt, mel_wh_marked_of), '' (a movie or a
// series watched by its position, no mark: no mark takes it off, as an episode of the own card).
/**
 * @param string $mid
 * @param string $type
 * @return string
 */
function mel_pg_mark_kind($mid, $type)
{
    $idx = mel_pg_index();
    $ent = $mid !== '' && isset($idx[$mid]) ? $idx[$mid] : null;
    if (!$ent)
        return 'on';
    if ($type === 'series')
    {
        $b = mel_pg_badge($ent, true, $mid);
        if (!$b || !$b['all'])
            return 'on';
        return $ent['movie_mark'] || mel_wh_marked_of($mid) ? 'off' : '';
    }
    if ($ent['movie_mark'])
        return 'off';
    if (!$ent['seen'])
        return 'on';
    list(, , $pos, $len) = mel_wh_rec($ent['w'] ? $ent['w'] : new stdClass());
    return mel_wh_full($pos, $len) ? '' : 'off';
}

// The uids "Снять отметку" of the movie of card $mid takes off with its movie_ids: of its latest record (mel_pg_index)
// if marked.
/**
 * @param string $mid
 * @return list<string>
 */
function mel_wh_movie_off_ids($mid)
{
    $idx = mel_pg_index();
    $rec = isset($idx[$mid]['w']) ? mel_wh_rec($idx[$mid]['w']) : null;
    return $rec && $rec[4] !== '' && mel_wh_marked($rec[4]) ? array($rec[4]) : array();
}

// update_watched_marks of uids $ids and cards $movie_ids, watched $on, then $post; the badges read the history anew
// (mel_pg_stale: the shell writes the files before $post).
/**
 * @param list<string> $ids
 * @param list<string> $movie_ids
 * @param bool $on
 * @param MelAction|null $post
 * @return MelAction
 */
function mel_pg_mark_act($ids, $movie_ids, $on, $post)
{
    mel_pg_stale();
    return array(
        GuiAction::handler_string_id => UPDATE_WATCHED_MARKS_ACTION_ID,
        GuiAction::data => array(
            UpdateWatchedMarksActionData::item_ids => $ids,
            UpdateWatchedMarksActionData::movie_ids => $movie_ids,
            UpdateWatchedMarksActionData::watched => $on,
            UpdateWatchedMarksActionData::post_action => $post));
}

// Begun by the row "Continue": MEL_PG_BEGUN_S or MEL_PG_BEGUN_PCT of $len watched.
/**
 * @param float $pos
 * @param float $len
 * @return bool
 */
function mel_pg_begun($pos, $len)
{
    return $len > 0 && ($pos >= MEL_PG_BEGUN_S || $pos * 100 >= MEL_PG_BEGUN_PCT * $len);
}

// What the badges and the line of the panel show of the state $ent of a card, as add_movie_ritem of
// movie_helper.php r25 for a card not of "My collection": marks of the own card made after the latest episode
// record watched or begun ends - the last of them by S/E watched (mel_wh_mark_last); a tile of a series ($series,
// by the type of the tile, not of the card of the Dune: a film in two parts is a card "series") or the latest
// record an episode: the latest episode record watched or begun (ep), the episodes under the threshold passed over,
// for a tile of a series whatever the latest record (a file or a trailer with no S/E); none such: a tile of a
// series none, else the latest record with no S/E as of a movie, watched (by itself) or begun (mel_pg_begun). An
// episode watched, $mid (the card) given: the next one by the pair (mel_pg_badge_next). The card marked in
// watched_movies.txt: watched; a series (episodes in the history, or $series: the tile is one) - all of it, no episode,
// unless its episodes watched and the list of them (the pair) give the next one and it is over the mark
// (mel_pg_mark_over: a season out after the mark, "all out watched", decision of the owner; the mark is the way only
// with no list of them).
// -> pos, len (s, 0: none), watched (the movie or the episode), s and e (-1: none), all (a series watched whole: all its
// episodes out or marked whole), next (s, e: the episode after the watched one); null: none.
/**
 * @param MelPgEntry $ent
 * @param bool $series
 * @param string $mid
 * @return MelPgBadge|null
 */
function mel_pg_badge($ent, $series = false, $mid = '')
{
    $st = $ent['series'];
    $p = $ent['ep'];
    $w = $ent['w'];
    $ep = $w && isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1;
    $b = null;
    $mk = $st ? mel_wh_mark_last($st[2], $p ? mel_wh_end($p['w']) : 0) : null;
    if ($mk)
        $b = array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => $mk[0], 'e' => $mk[1], 'all' => false, 'next' => false);
    else if ($p && ($series || $ep))
    {
        list($s, $e, $pos, $len) = mel_wh_rec($p['w']);
        $b = array('pos' => max(0.0, $pos), 'len' => $len, 'watched' => $p['seen'], 's' => $s, 'e' => $e, 'all' => false, 'next' => false);
    }
    else if ($w && !$ep && !$series)
    {
        list(, , $pos, $len) = mel_wh_rec($w);
        if ($ent['seen'] || mel_pg_begun($pos, $len))
            $b = array('pos' => max(0.0, $pos), 'len' => $len, 'watched' => $ent['seen'], 's' => -1, 'e' => -1, 'all' => false, 'next' => false);
    }
    $nb = $b && $mid !== '' && $st && $b['e'] >= 0 && $b['watched'] ? mel_pg_badge_next($b, $st, $mid) : $b;
    if ($ent['movie_mark'] && !($nb && $nb['next'] && mel_pg_mark_over($nb, $st, $mid)))
        return array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => -1, 'e' => -1, 'all' => $series || $st || $ep,
            'next' => false);
    return $nb;
}

// The badge $b of a watched episode of the series of card $mid of state $st (MelWhSeries) by the episodes of its pair
// out today (mel_pg_eps): the episode the own card offers after it (mel_wh_pick, as the row "Continue" and the button
// "Продолжить S1 E2"), next, not begun; none of them left - the series watched (all, as a movie); no episodes in the
// pair, the season of the record not in them: $b as it is.
/**
 * @param MelPgBadge $b
 * @param MelWhSeries $st
 * @param string $mid
 * @return MelPgBadge
 */
function mel_pg_badge_next($b, $st, $mid)
{
    $links = mel_pg_links();
    $eps = mel_pg_eps(isset($links[$mid]) ? $links[$mid] : null, $b['s'], $b['e']);
    if (!$eps)
        return $b;
    $p = mel_wh_pick($st, $mid, $eps);
    if ($p['kind'] === 'first')
        return array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => -1, 'e' => -1, 'all' => true, 'next' => false);
    if ($p['kind'] !== 'next' && $p['kind'] !== 'record')
        return $b;
    return array('pos' => 0.0, 'len' => 0.0, 'watched' => false, 's' => $p['s'], 'e' => $p['e'], 'all' => false, 'next' => true);
}

// The next episode of badge $nb (mel_pg_badge_next) of the series of card $mid of state $st takes the place of the mark of
// the card in watched_movies.txt: not if it is watched by $st (its record or a mark of the own card: S1 E1 watched again,
// S1 E2 marked - the series stays watched), nor if it is S<n> E1 of a season with no episode in $st by a pair with no
// dates (no src, no soon: season_numbers of play_action count the episodes not out yet - a season announced; better a
// new season missed than the tick lost for good, as "Отметить" marks only those of the meta out).
/**
 * @param MelPgBadge $nb
 * @param MelWhSeries $st
 * @param string $mid
 * @return bool
 */
function mel_pg_mark_over($nb, $st, $mid)
{
    $s = $nb['s'];
    if (isset($st[1]["$s:{$nb['e']}"]) && $st[1]["$s:{$nb['e']}"][2])
        return false;
    if ($nb['e'] !== 1)
        return true;
    $links = mel_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : array();
    if (isset($l['src']) || !empty($l['soon']))
        return true;
    foreach (array_keys($st[1]) as $k)
    {
        if (intval($k) === $s)
            return true;
    }
    return false;
}

// The badge of the card of tile $t of the catalog: of the first of its cards (mel_pg_tile_cards) in the
// history, null for none.
/**
 * @param MelCatTile $t
 * @return MelPgBadge|null
 */
function mel_pg_tile_badge($t)
{
    $idx = mel_pg_index();
    if (!$idx)
        return null;
    $mid = mel_pg_tile_card($t, $idx);
    return $mid !== '' && isset($idx[$mid]) ? mel_pg_badge($idx[$mid], $t['type'] === 'series', $mid) : null;
}

// The card of tile $t: the first of its cards by the pairs (mel_pg_tile_cards) in the state $idx (mel_pg_index),
// else the first of them, '' for none.
/**
 * @param MelCatTile $t
 * @param array<string, MelPgEntry> $idx
 * @return string
 */
function mel_pg_tile_card($t, $idx)
{
    $cards = mel_pg_tile_cards($t);
    foreach ($cards as $mid)
    {
        if (isset($idx[$mid]))
            return $mid;
    }
    return $cards ? $cards[0] : '';
}

// --- Badges.

/**
 * @param int $x
 * @param int $y
 * @param int $w
 * @param int $h
 * @return array{w: int, h: int, x: int, y: int}
 */
function mel_pg_r($x, $y, $w, $h)
{
    return array('w' => $w, 'h' => $h, 'x' => $x, 'y' => $y);
}

// The stickers of badge $b on a poster of a row, after the rating: "S1 E3" of an episode on a plate as wide as the text,
// a tab of the bar (P-B): from the left edge, its bottom on the line of the bar, on the bottom edge with no bar; the tick
// of a watched movie or a series watched whole, the bar (full when watched) with the black line over it. A watched
// episode (no list of the episodes to tell the next one) and the next one have no bar and no tick: "S1 E10" stays true
// when a new season comes out (decisions of the owner).
/**
 * @param MelPgBadge $b
 * @return list<array<string, mixed>>
 */
function mel_pg_stickers($b)
{
    $out = array();
    $bar = MEL_PG_H - MEL_PG_BAR_H;
    $ep = $b['e'] >= 0 && !$b['all'];
    if ($ep)
    {
        $text = "S{$b['s']} E{$b['e']}";
        $tw = intval(mel_gc_sum($text, MEL_CATV_RATE_PX));
        $w = $tw + 2 * MEL_PG_EP_PAD;
        $y = ($b['watched'] || $b['next'] ? MEL_PG_H : $bar - 1) - MEL_PG_EP_H;
        $out[] = array(PluginRegularSticker::r => mel_pg_r(0, $y, $w, MEL_PG_EP_H), PluginRegularSticker::color => MEL_PG_EP_DARK);
        // The shell puts the text at the left of its r and a pixel over the middle (mel_catv_rate_stickers).
        $out[] = array(PluginRegularSticker::r => mel_pg_r(MEL_PG_EP_PAD, $y + 1, $w - MEL_PG_EP_PAD, MEL_PG_EP_H),
            PluginRegularSticker::text => $text);
    }
    if ($b['all'] || ($b['watched'] && !$ep))
    {
        $r = mel_fw_r();
        $out[] = array(PluginRegularSticker::r => mel_pg_r(MEL_PG_W - 53, $bar - 36, 41, 31),
            PluginRegularSticker::icon_url => $r > 0 && $r < 25 ? MEL_PG_TICK_OLD : MEL_PG_TICK);
    }
    if ($ep && ($b['watched'] || $b['next']))
        return $out;
    $x1 = $b['watched'] || $b['all'] ? MEL_PG_W : intval((MEL_PG_W * min($b['pos'], $b['len']) + $b['len'] / 2) / $b['len']);
    if ($x1 > 0)
        $out[] = array(PluginRegularSticker::r => mel_pg_r(0, $bar, $x1, MEL_PG_BAR_H), PluginRegularSticker::color => MEL_PG_BAR_ON);
    if ($x1 < MEL_PG_W)
        $out[] = array(PluginRegularSticker::r => mel_pg_r($x1, $bar, MEL_PG_W - $x1, MEL_PG_BAR_H),
            PluginRegularSticker::color => MEL_PG_BAR_OFF);
    $out[] = array(PluginRegularSticker::r => mel_pg_r(0, $bar - 1, MEL_PG_W, 1), PluginRegularSticker::color => MEL_PG_BAR_LINE);
    return $out;
}

// The text of the line of the panel of badge $b: "Просмотрено" (a movie, a series watched whole), "S1 E10
// просмотрена" (an episode), "Следующая: S1 E2" (the next one), or "Осталось H:MM" (the minutes up) with ", S1 E3" of
// an episode.
/**
 * @param MelPgBadge $b
 * @param string $lang
 * @return string
 */
function mel_pg_info_text($b, $lang)
{
    $ep = "S{$b['s']} E{$b['e']}";
    if ($b['all'] || ($b['watched'] && $b['e'] < 0))
        return mel_tr($lang, 'cp_watched');
    if ($b['next'])
        return sprintf(mel_tr($lang, 'cp_next'), $ep);
    if ($b['watched'])
        return sprintf(mel_tr($lang, 'cp_ep_watched'), $ep);
    // Minutes up: 45 s left is "0:01".
    $min = intval(ceil(max(0.0, $b['len'] - min($b['pos'], $b['len'])) / 60));
    $s = sprintf(mel_tr($lang, 'cp_left'), sprintf('%d:%02d', intval($min / 60), $min % 60));
    // A no-break space after the comma, as the native line.
    return $b['e'] >= 0 ? "$s,\xC2\xA0$ep" : $s;
}

// The width of the text $text and the tail $tail of the line of the panel at $px, as the two labels they may be.
/**
 * @param string $text
 * @param string $tail
 * @param int $px
 * @return int
 */
function mel_pg_info_w($text, $tail, $px)
{
    return mel_gc_w($text, $px) + ($tail !== '' ? mel_gc_w($tail, $px) : 0);
}

// The line of the panel at $y, 40 high, of words $text alone (a title of "Мой список" with no history), as the text of
// mel_pg_info_line with no bar: at the left edge of the column, cut with "…" to it.
/**
 * @param string $text
 * @param int $y
 * @return list<array<string, mixed>>
 */
function mel_pg_info_words($text, $y)
{
    return array(mel_catv_info_label(mel_gc_cut_w($text, MEL_PG_INFO_PX, MEL_CATV_TEXT_W), MEL_PG_INFO_PX, MEL_CATV_TEXT_COLOR, -1,
        MEL_PG_INFO_H, $y - 3, array(GCompTtfLabelDef::valign => VALIGN_CENTER)));
}

// The line of the panel of badge $b at $y, 40 high: the bar (full when watched, none of it of the next episode), the
// text, the tick of a watched movie or a series watched whole (not of a watched episode, as on its tile: "S2 E7
// просмотрена ✓" reads as the series watched when its next episode is not known), then $tail (" · В моём списке", a
// title of "Мой список"; '' none). Too wide for the column (MEL_CATV_TEXT_W): the bar shorter, down to
// MEL_PG_INFO_BAR_MIN, then the words smaller, as the lines over it (mel_catv_fit), down to MEL_PG_INFO_MIN_PX, the tail
// cut with "…" to what is left.
/**
 * @param MelPgBadge $b
 * @param int $y
 * @param string $lang
 * @param string $tail
 * @return list<array<string, mixed>>
 */
function mel_pg_info_line($b, $y, $lang, $tail = '')
{
    $text = mel_pg_info_text($b, $lang);
    $tk = $b['all'] || ($b['watched'] && $b['e'] < 0) ? MEL_PG_INFO_TICK_W : 0;
    $px = MEL_PG_INFO_PX;
    $w = max(MEL_PG_INFO_BAR_MIN, min(MEL_PG_INFO_BAR_W, MEL_CATV_TEXT_W - 40 - $tk - mel_pg_info_w($text, $tail, $px)));
    $room = MEL_CATV_TEXT_W - $w - 40 - $tk;
    while ($px > MEL_PG_INFO_MIN_PX && mel_pg_info_w($text, $tail, $px) > $room)
        $px--;
    $text = mel_gc_cut_w($text, $px, $room);
    $tail = $tail !== '' ? mel_gc_cut_w($tail, $px, $room - mel_gc_w($text, $px)) : '';
    $x1 = $tk || $b['watched'] ? $w : ($b['next'] ? 0 : intval(($w * min($b['pos'], $b['len']) + $b['len'] / 2) / $b['len']));
    $by = $y + intval((MEL_PG_INFO_H - MEL_PG_INFO_BAR_H) / 2);
    $out = array();
    if ($x1 > 0)
        $out[] = mel_gc_rect(mel_gc_at($x1, MEL_PG_INFO_BAR_H, 0, $by), MEL_PG_INFO_ON);
    if ($x1 < $w)
        $out[] = mel_gc_rect(mel_gc_at($w - $x1, MEL_PG_INFO_BAR_H, $x1, $by), MEL_PG_BAR_OFF);
    $spec = array(GCompTtfLabelDef::valign => VALIGN_CENTER);
    $l = mel_catv_info_label($tk ? $text : $text . $tail, $px, MEL_CATV_TEXT_COLOR, -1, MEL_PG_INFO_H, $y - 3, $spec);
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::x] = $w + 40;
    $out[] = $l;
    if (!$tk)
        return $out;
    $out[] = mel_gc_image(mel_gc_geom(40, 30, mel_gc_next_right(20, 8)), MEL_PG_INFO_TICK);
    if ($tail !== '')
    {
        // Right of the tick, at the top of the text.
        $l = mel_catv_info_label($tail, $px, MEL_CATV_TEXT_COLOR, -1, MEL_PG_INFO_H, 0, $spec);
        $l[GComponentDef::geom_def][GCompGeometryDef::align_def] = mel_gc_next_right(0, -8);
        $out[] = $l;
    }
    return $out;
}
