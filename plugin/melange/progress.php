<?php
// The watch state of every card of the history of the Dune (one pass, memory of php_server) for the badges of
// the tiles of the catalog and the tab "TV" and the line of the info panel. Read only of the history
// (history.php has the rules); the pairs card -> IMDb / TMDB of the tiles: links.php. Needs history.php,
// links.php (aio_pg_tile_cards), main.php (aio_log, aio_tr, AIO_SUP_ID, AIO_DUNE_ID), common.php (aio_fw_r), gcomps.php
// (aio_gc_sum, aio_gc_rect, aio_gc_at, aio_gc_image, aio_gc_geom, aio_gc_next_right), view_catalog.php
// (aio_catv_info_label, AIO_CATV_RATE_PX, AIO_CATV_RATE_PAD, AIO_CATV_TEXT_COLOR).

// The state is checked against the files once a second at most: r25 asks ~28 rows a screen.
define('AIO_PG_SIG_S', 1.0);
// Begun: this many s or % of it watched, as the row "Continue".
define('AIO_PG_BEGUN_S', 120);
define('AIO_PG_BEGUN_PCT', 2);
// The badges, in px of the poster 190x285 of a row (movies/movie_helper.php r25, add_movie_ritem;
// the plate of "S1 E3" as the rating, probe O1 on r25): the bar at the bottom, the line over it,
// the tick over the bar right.
define('AIO_PG_W', 190);
define('AIO_PG_H', 285);
define('AIO_PG_BAR_H', 8);
define('AIO_PG_BAR_ON', '#FFFFFFFF');
define('AIO_PG_BAR_OFF', '#FF404040');
define('AIO_PG_BAR_LINE', '#FF000000');
define('AIO_PG_TICK', 'plugin_file://%shell_ext%/icons/watched_poster.png');
// r24 and older have no watched_poster.png: the small tick of the same look.
define('AIO_PG_TICK_OLD', 'plugin_file://%shell_ext%/icons/watched_small.png');
define('AIO_PG_EP_DARK', '#B4000000');
define('AIO_PG_EP_H', 32);
define('AIO_PG_EP_PAD', 8);
// The line of the info panel (do_get_info_children of movie_helper.php r25): 40 high, the bar
// 324x6, the text 40 px right of it, the tick 20 px right of the text.
define('AIO_PG_INFO_H', 40);
define('AIO_PG_INFO_BAR_W', 324);
define('AIO_PG_INFO_BAR_H', 6);
define('AIO_PG_INFO_ON', '#FFE0E0FF');
define('AIO_PG_INFO_PX', 24);
define('AIO_PG_INFO_TICK', 'plugin_file://%shell_ext%/icons/watched_small.png');

class AioPg
{
    // The state by movie_id (aio_pg_index) and the signature of the history files it is of.
    /** @var array<string, AioPgEntry> */
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

// The record $w of the history the shell keeps for a card (aio_wh_kept) -> a copy of the fields the
// badges, the line of the panel and aio_wh_pick read.
/**
 * @param stdClass $w
 * @return stdClass
 */
function aio_pg_slim($w)
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
// as aio_wh_series_visit makes it. $arg: array(aio_wh_marked_set(), the marks of the own card by movie_id).
/**
 * @param mixed $w
 * @param array{array<int|string, int>, array<string, array<string, int>>} $arg
 * @param array<string, array<string, mixed>> $acc
 * @return bool
 */
function aio_pg_visit($w, $arg, &$acc)
{
    $mid = aio_wh_kept($w);
    if ($mid === '' || !($w instanceof stdClass))
        return false;
    if (!isset($acc[$mid]))
    {
        list(, , $pos, $len, $uid) = aio_wh_rec($w);
        $end = aio_wh_end($w);
        $mark = isset($arg[0][$uid]) ? $arg[0][$uid] : 0;
        $acc[$mid] = array('w' => aio_pg_slim($w), 'end' => $end,
            'seen' => aio_wh_full($pos, $len) || aio_wh_mark_on($mark, $end), 'st' => array());
    }
    $marks = isset($arg[1][$mid]) ? $arg[1][$mid] : array();
    if (isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1)
    {
        $st = $acc[$mid]['st'];
        $had = isset($st['last']);
        aio_wh_series_visit($w, array($mid, $arg[0], $marks), $st);
        if (!$had && isset($st['last']))
            $st['last'] = aio_pg_slim($st['last']);
        $acc[$mid]['st'] = $st;
    }
    return false;
}

// The marks of the own card in $set (aio_wh_marked_set) by the movie_id of their uids, one pass: movie_id =>
// what aio_wh_marks gives of it.
/**
 * @param array<int|string, int> $set
 * @return array<string, array<string, int>>
 */
function aio_pg_own_marks($set)
{
    $out = array();
    $p = '/^' . AIO_SUP_ID . ':(' . AIO_DUNE_ID . '):(-?[0-9]{1,5}):(-?[0-9]{1,5}):m$/D';
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
function aio_pg_say($what, $line)
{
    if ($what === '' || AioPg::$said !== $what)
        aio_log($line);
    AioPg::$said = $what;
}

// The state of every card of the history, read again when its files change (aio_wh_sig, watched_movies.txt
// too), the signature checked once in AIO_PG_SIG_S: movie_id => w (the latest record of any supplier or a
// local file, null: none), end (of it), seen (it watched: by position or its uid marked, aio_wh_mark_on),
// movie_mark (the card marked in watched_movies.txt: watched at any time, as watch_history.php r24 and r25
// read it), series (AioWhSeries of its episode records and the marks of the own card, as aio_wh_series; null:
// no episode record and no mark).
/**
 * @return array<string, AioPgEntry>
 */
function aio_pg_index()
{
    $now = microtime(true);
    if (AioPg::$sig_t > 0 && $now - AioPg::$sig_t >= 0 && $now - AioPg::$sig_t < AIO_PG_SIG_S)
        return AioPg::$idx;
    AioPg::$sig_t = $now;
    if (!is_dir(AIO_WH_PATH) || !is_readable(AIO_WH_PATH))
    {
        AioPg::$sig = null;
        AioPg::$idx = array();
        aio_pg_say('no folder', 'progress: ' . (is_dir(AIO_WH_PATH) ? 'folder not readable' : 'no folder'));
        return AioPg::$idx;
    }
    $sig = aio_wh_sig();
    if ($sig !== null && $sig === AioPg::$sig)
        return AioPg::$idx;
    $set = aio_wh_marked_set();
    $own = aio_pg_own_marks($set);
    $acc = aio_wh_find('progress: index', 'aio_pg_visit', array($set, $own));
    $idx = array();
    foreach ($acc as $mid => $a)
    {
        $st = $a['st'];
        $last = isset($st['last']) && $st['last'] instanceof stdClass ? $st['last'] : null;
        unset($st['last']);
        $idx[strval($mid)] = array('w' => $a['w'], 'end' => $a['end'], 'seen' => $a['seen'], 'movie_mark' => false,
            'series' => $last ? array($last, $st, isset($own[$mid]) ? $own[$mid] : array()) : null);
    }
    // The marks of the own card with no record of their series, as aio_wh_series.
    foreach ($own as $mid => $marks)
    {
        if (!isset($idx[$mid]))
            $idx[$mid] = array('w' => null, 'end' => 0, 'seen' => false, 'movie_mark' => false, 'series' => null);
        $st = $idx[$mid]['series'] ? $idx[$mid]['series'] : array(null, array(), $marks);
        $st[1] = aio_wh_series_fill($st[1], $marks);
        $idx[$mid]['series'] = $st;
    }
    foreach (aio_wh_marked_set('watched_movies.txt') as $mid => $ts)
    {
        $mid = strval($mid);
        if (!isset($idx[$mid]))
            $idx[$mid] = array('w' => null, 'end' => 0, 'seen' => false, 'movie_mark' => true, 'series' => null);
        else
            $idx[$mid]['movie_mark'] = true;
    }
    AioPg::$sig = $sig;
    AioPg::$idx = $idx;
    aio_pg_say($idx ? '' : '0 cards', sprintf('progress: %d cards, %.0f ms', count($idx), (microtime(true) - $now) * 1000));
    return $idx;
}

// The state is old: the next aio_pg_index reads the history again (a mark just made).
/**
 * @return void
 */
function aio_pg_stale()
{
    AioPg::$sig = null;
    AioPg::$sig_t = 0.0;
}

// Begun by the row "Continue": AIO_PG_BEGUN_S or AIO_PG_BEGUN_PCT of $len watched.
/**
 * @param float $pos
 * @param float $len
 * @return bool
 */
function aio_pg_begun($pos, $len)
{
    return $len > 0 && ($pos >= AIO_PG_BEGUN_S || $pos * 100 >= AIO_PG_BEGUN_PCT * $len);
}

// What the badges and the line of the panel show of the state $ent of a card, as add_movie_ritem of
// movie_helper.php r25 for a card not of "My collection": the card marked in watched_movies.txt - watched,
// no episode; marks of the own card made after the latest episode record ends - the last of them by S/E
// watched (aio_wh_mark_last); else the latest record: watched (by itself, or its episode by the state of the
// series), or begun (aio_pg_begun). -> pos, len (s, 0: none), watched, s and e (-1: a movie); null: none.
/**
 * @param AioPgEntry $ent
 * @return AioPgBadge|null
 */
function aio_pg_badge($ent)
{
    if ($ent['movie_mark'])
        return array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => -1, 'e' => -1);
    $st = $ent['series'];
    $mk = $st ? aio_wh_mark_last($st[2], $st[0] ? aio_wh_end($st[0]) : 0) : null;
    if ($mk)
        return array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => $mk[0], 'e' => $mk[1]);
    $w = $ent['w'];
    if (!$w)
        return null;
    list($s, $e, $pos, $len) = aio_wh_rec($w);
    $ep = isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1;
    $watched = $ent['seen'] || ($ep && $st && isset($st[1]["$s:$e"]) && $st[1]["$s:$e"][2]);
    if (!$watched && !aio_pg_begun($pos, $len))
        return null;
    return array('pos' => max(0.0, $pos), 'len' => $len, 'watched' => $watched, 's' => $ep ? $s : -1, 'e' => $ep ? $e : -1);
}

// The badge of the card of tile $t of the catalog: of the first of its cards (aio_pg_tile_cards) in the
// history, null for none.
/**
 * @param AioCatTile $t
 * @return AioPgBadge|null
 */
function aio_pg_tile_badge($t)
{
    $idx = aio_pg_index();
    if (!$idx)
        return null;
    $mid = aio_pg_tile_card($t, $idx);
    return $mid !== '' && isset($idx[$mid]) ? aio_pg_badge($idx[$mid]) : null;
}

// The card of tile $t: the first of its cards by the pairs (aio_pg_tile_cards) in the state $idx (aio_pg_index),
// else the first of them, '' for none.
/**
 * @param AioCatTile $t
 * @param array<string, AioPgEntry> $idx
 * @return string
 */
function aio_pg_tile_card($t, $idx)
{
    $cards = aio_pg_tile_cards($t);
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
function aio_pg_r($x, $y, $w, $h)
{
    return array('w' => $w, 'h' => $h, 'x' => $x, 'y' => $y);
}

// The stickers of badge $b on a poster of a row, after the rating: "S1 E3" of a series on a plate as wide
// as the text (at x of the rating, 3 px over the line of the bar), the tick of a watched one, the bar
// (full when watched) with the black line over it. A watched episode has no bar: "watched up to S1 E10"
// stays true when a new season comes out (decision of the owner).
/**
 * @param AioPgBadge $b
 * @return list<array<string, mixed>>
 */
function aio_pg_stickers($b)
{
    $out = array();
    $bar = AIO_PG_H - AIO_PG_BAR_H;
    if ($b['e'] >= 0)
    {
        $text = "S{$b['s']} E{$b['e']}";
        $tw = intval(aio_gc_sum($text, AIO_CATV_RATE_PX));
        $x = AIO_CATV_RATE_PAD;
        $w = $tw + 2 * AIO_PG_EP_PAD;
        $y = $bar - 1 - 3 - AIO_PG_EP_H;
        $out[] = array(PluginRegularSticker::r => aio_pg_r($x, $y, $w, AIO_PG_EP_H), PluginRegularSticker::color => AIO_PG_EP_DARK);
        // The shell puts the text at the left of its r and a pixel over the middle (aio_catv_rate_stickers).
        $out[] = array(PluginRegularSticker::r => aio_pg_r($x + AIO_PG_EP_PAD, $y + 1, $w - AIO_PG_EP_PAD, AIO_PG_EP_H),
            PluginRegularSticker::text => $text);
    }
    if ($b['watched'])
    {
        $r = aio_fw_r();
        $out[] = array(PluginRegularSticker::r => aio_pg_r(AIO_PG_W - 53, $bar - 36, 41, 31),
            PluginRegularSticker::icon_url => $r > 0 && $r < 25 ? AIO_PG_TICK_OLD : AIO_PG_TICK);
    }
    if ($b['watched'] && $b['e'] >= 0)
        return $out;
    $x1 = $b['watched'] ? AIO_PG_W : intval((AIO_PG_W * min($b['pos'], $b['len']) + $b['len'] / 2) / $b['len']);
    if ($x1 > 0)
        $out[] = array(PluginRegularSticker::r => aio_pg_r(0, $bar, $x1, AIO_PG_BAR_H), PluginRegularSticker::color => AIO_PG_BAR_ON);
    if ($x1 < AIO_PG_W)
        $out[] = array(PluginRegularSticker::r => aio_pg_r($x1, $bar, AIO_PG_W - $x1, AIO_PG_BAR_H),
            PluginRegularSticker::color => AIO_PG_BAR_OFF);
    $out[] = array(PluginRegularSticker::r => aio_pg_r(0, $bar - 1, AIO_PG_W, 1), PluginRegularSticker::color => AIO_PG_BAR_LINE);
    return $out;
}

// The text of the line of the panel of badge $b: "Просмотрено" or "Осталось H:MM" (the minutes up), ", S1 E3"
// of an episode.
/**
 * @param AioPgBadge $b
 * @param string $lang
 * @return string
 */
function aio_pg_info_text($b, $lang)
{
    if ($b['watched'])
        $s = aio_tr($lang, 'cp_watched');
    else
    {
        // Minutes up: 45 s left is "0:01".
        $min = intval(ceil(max(0.0, $b['len'] - min($b['pos'], $b['len'])) / 60));
        $s = sprintf(aio_tr($lang, 'cp_left'), sprintf('%d:%02d', intval($min / 60), $min % 60));
    }
    // A no-break space after the comma, as the native line.
    return $b['e'] >= 0 ? "$s,\xC2\xA0S{$b['s']} E{$b['e']}" : $s;
}

// The line of the panel of badge $b at $y, 40 high: the bar, the text, the tick of a watched one.
/**
 * @param AioPgBadge $b
 * @param int $y
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_pg_info_line($b, $y, $lang)
{
    $w = AIO_PG_INFO_BAR_W;
    $x1 = $b['watched'] ? $w : intval(($w * min($b['pos'], $b['len']) + $b['len'] / 2) / $b['len']);
    $by = $y + intval((AIO_PG_INFO_H - AIO_PG_INFO_BAR_H) / 2);
    $out = array();
    if ($x1 > 0)
        $out[] = aio_gc_rect(aio_gc_at($x1, AIO_PG_INFO_BAR_H, 0, $by), AIO_PG_INFO_ON);
    if ($x1 < $w)
        $out[] = aio_gc_rect(aio_gc_at($w - $x1, AIO_PG_INFO_BAR_H, $x1, $by), AIO_PG_BAR_OFF);
    $l = aio_catv_info_label(aio_pg_info_text($b, $lang), AIO_PG_INFO_PX, AIO_CATV_TEXT_COLOR, -1, AIO_PG_INFO_H, $y - 3,
        array(GCompTtfLabelDef::valign => VALIGN_CENTER));
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::x] = $w + 40;
    $out[] = $l;
    if ($b['watched'])
        $out[] = aio_gc_image(aio_gc_geom(40, 30, aio_gc_next_right(20, 8)), AIO_PG_INFO_TICK);
    return $out;
}
