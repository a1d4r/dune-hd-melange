<?php
// The watch state of every card of the history of the Dune (one pass, memory of php_server) and
// the pairs card of the Dune (movie_id) -> IMDb / TMDB, for the badges of the tiles of the catalog and
// the tab "TV" and the line of the info panel. Read only of the history (history.php has the rules);
// the pairs in a file of data_dir. Needs history.php, main.php (aio_log, aio_write_file, aio_data_dir,
// aio_http_get), catalog_card.php (aio_card_token, aio_card_api), gcomps.php (aio_gc_sum).

// The cards the shell keeps (movies/storage_helper.php: /flashdata, /persistfs on old models): PHP of the
// Dune adds FS_PREFIX itself. Tests may define them first.
if (!defined('AIO_PG_SHELL_DIR'))
    define('AIO_PG_SHELL_DIR', '/flashdata/dunemdb_cache/movies');
if (!defined('AIO_PG_SHELL_DIR2'))
    define('AIO_PG_SHELL_DIR2', '/persistfs/dunemdb_cache/movies');
// A bigger card of the shell is not read (seen up to ~840 KB).
define('AIO_PG_SHELL_MAX_BYTES', 2000000);
// ms of an opening for the cards of the shell; one card is read even past it.
define('AIO_PG_SHELL_MS', 150);
// The pairs, in data_dir.
define('AIO_PG_LINKS_FILE', 'wh_links.json');
// A failed request is asked again after this many s; "no pair" stays.
define('AIO_PG_FAIL_S', 86400);
// A record that ended longer ago is not linked by a request.
define('AIO_PG_LINK_AGE_S', 31536000);
// Requests of an opening, the time of them all, s; the second one only with LINK_NEXT_S left.
define('AIO_PG_LINK_N', 2);
define('AIO_PG_LINK_S', 3.0);
define('AIO_PG_LINK_NEXT_S', 1.5);
define('AIO_PG_LINK_CONNECT', 2);
// The state is checked against the files once a second at most: r25 asks ~28 rows a screen.
define('AIO_PG_SIG_S', 1.0);
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
    // The pairs by movie_id (aio_pg_links; null: not read yet) and the reverse of them.
    /** @var array<string, AioPgLink>|null */
    public static $links = null;
    /** @var array<string, string> */
    public static $rev = array();
    // movie_ids whose card of the shell was looked at since the start of php_server.
    /** @var array<string, bool> */
    public static $shell_seen = array();
    // r25: the flagged rows of the screen opened last not asked yet; the lazy pairs after the last one.
    /** @var array<string, bool> */
    public static $pending = array();
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
    if ($mid === '' || !is_object($w))
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

// The marks of the own card in $set (aio_wh_marked_set) by the movie_id of their uids (aio_wh_marks).
/**
 * @param array<int|string, int> $set
 * @return array<string, array<string, int>>
 */
function aio_pg_own_marks($set)
{
    $out = array();
    $p = '/^' . AIO_SUP_ID . ':(' . AIO_DUNE_ID . '):/';
    foreach ($set as $uid => $ts)
    {
        if (preg_match($p, strval($uid), $m) && !isset($out[$m[1]]))
            $out[$m[1]] = aio_wh_marks($m[1], $set);
    }
    return $out;
}

// The state of every card of the history, read again when its files change (aio_wh_sig, watched_movies.txt
// too), the signature checked once in AIO_PG_SIG_S: movie_id => w (the latest record of any supplier or a
// local file, null: none), end (of it), seen (it watched: by position or its uid marked, aio_wh_mark_on),
// mv (the card marked in watched_movies.txt: watched at any time, as watch_history.php r24 and r25
// read it), st (a series: AioWhSeries of its episode records and the marks of the own card, as
// aio_wh_series; null: no episode record and no mark).
/**
 * @return array<string, AioPgEntry>
 */
function aio_pg_index()
{
    $now = microtime(true);
    if (AioPg::$sig_t > 0 && $now - AioPg::$sig_t >= 0 && $now - AioPg::$sig_t < AIO_PG_SIG_S)
        return AioPg::$idx;
    AioPg::$sig_t = $now;
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
        $idx[strval($mid)] = array('w' => $a['w'], 'end' => $a['end'], 'seen' => $a['seen'], 'mv' => false,
            'st' => $last ? array($last, $st, isset($own[$mid]) ? $own[$mid] : array()) : null);
    }
    // The marks of the own card with no record of their series, as aio_wh_series.
    foreach ($own as $mid => $marks)
    {
        if (!$marks)
            continue;
        if (!isset($idx[$mid]))
            $idx[$mid] = array('w' => null, 'end' => 0, 'seen' => false, 'mv' => false, 'st' => null);
        $st = $idx[$mid]['st'] ? $idx[$mid]['st'] : array(null, array(), $marks);
        foreach ($marks as $k => $ts)
        {
            if (!isset($st[1][$k]))
                $st[1][$k] = array(-1.0, 0.0, true);
        }
        $idx[$mid]['st'] = $st;
    }
    foreach (aio_wh_marked_set('watched_movies.txt') as $mid => $ts)
    {
        $mid = strval($mid);
        if (!isset($idx[$mid]))
            $idx[$mid] = array('w' => null, 'end' => 0, 'seen' => false, 'mv' => true, 'st' => null);
        else
            $idx[$mid]['mv'] = true;
    }
    AioPg::$sig = $sig;
    AioPg::$idx = $idx;
    aio_log(sprintf('progress: %d cards, %.0f ms', count($idx), (microtime(true) - $now) * 1000));
    return $idx;
}

// What the badges and the line of the panel show of the state $ent of a card, as add_movie_ritem of
// movie_helper.php r25 for a card not of "My collection": the card marked in watched_movies.txt - watched,
// no episode; a mark of the own card made after the latest episode record ends - its episode watched (the
// newest mark); else the latest record: watched (by itself, or its episode by the state of the series), or
// its progress (len > 0, pos >= 0). -> pos, len (s, 0: none), watched, s and e (-1: a movie); null: none.
/**
 * @param AioPgEntry $ent
 * @return AioPgBadge|null
 */
function aio_pg_badge($ent)
{
    if ($ent['mv'])
        return array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => -1, 'e' => -1);
    $st = $ent['st'];
    if ($st)
    {
        $end = $st[0] ? aio_wh_end($st[0]) : 0;
        $best = '';
        $bt = 0;
        foreach ($st[2] as $k => $ts)
        {
            if ($ts > $end && $ts >= $bt)
            {
                $best = strval($k);
                $bt = $ts;
            }
        }
        if ($best !== '')
        {
            list($s, $e) = explode(':', $best);
            return array('pos' => 0.0, 'len' => 0.0, 'watched' => true, 's' => intval($s), 'e' => intval($e));
        }
    }
    $w = $ent['w'];
    if (!$w)
        return null;
    list($s, $e, $pos, $len) = aio_wh_rec($w);
    $ep = isset($w->e) && is_numeric($w->e) && intval($w->e) >= 1;
    $watched = $ent['seen'] || ($ep && $st && isset($st[1]["$s:$e"]) && $st[1]["$s:$e"][2]);
    if (!$watched && !($len > 0 && $pos >= 0))
        return null;
    return array('pos' => max(0.0, $pos), 'len' => $len, 'watched' => $watched, 's' => $ep ? $s : -1, 'e' => $ep ? $e : -1);
}

// The badge of the card of tile $t of the catalog (aio_pg_tile_card), null for none.
/**
 * @param AioCatTile $t
 * @return AioPgBadge|null
 */
function aio_pg_tile_badge($t)
{
    $idx = aio_pg_index();
    if (!$idx)
        return null;
    $mid = aio_pg_tile_card($t);
    return $mid !== '' && isset($idx[$mid]) ? aio_pg_badge($idx[$mid]) : null;
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
// (full when watched) with the black line over it.
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
    $x1 = $b['watched'] ? AIO_PG_W : intval((AIO_PG_W * min($b['pos'], $b['len']) + $b['len'] / 2) / $b['len']);
    if ($x1 > 0)
        $out[] = array(PluginRegularSticker::r => aio_pg_r(0, $bar, $x1, AIO_PG_BAR_H), PluginRegularSticker::color => AIO_PG_BAR_ON);
    if ($x1 < AIO_PG_W)
        $out[] = array(PluginRegularSticker::r => aio_pg_r($x1, $bar, AIO_PG_W - $x1, AIO_PG_BAR_H),
            PluginRegularSticker::color => AIO_PG_BAR_OFF);
    $out[] = array(PluginRegularSticker::r => aio_pg_r(0, $bar - 1, AIO_PG_W, 1), PluginRegularSticker::color => AIO_PG_BAR_LINE);
    return $out;
}

// The text of the line of the panel of badge $b: "Просмотрено" or "Осталось H:MM" (no seconds), ", S1 E3" of
// an episode.
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
        $left = max(0, intval($b['len'] - min($b['pos'], $b['len'])));
        $s = sprintf(aio_tr($lang, 'cp_left'), sprintf('%d:%02d', intval($left / 3600), intval(($left % 3600) / 60)));
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

// --- Pairs.

// The pairs movie_id => imdb, tmdb (of a movie), tmdbtv (of a series), type (movie, series, ''), seasons
// (season_numbers: season => episodes); or none (the time it was found there is no pair: for good) or fail
// (the time of a failed request: asked again after AIO_PG_FAIL_S). Read once from data_dir.
/**
 * @return array<string, AioPgLink>
 */
function aio_pg_links()
{
    if (AioPg::$links !== null)
        return AioPg::$links;
    $path = aio_data_dir() !== '' ? aio_data_dir() . '/' . AIO_PG_LINKS_FILE : '';
    $j = $path !== '' && is_file($path) ? json_decode(strval(file_get_contents($path)), true) : null;
    $links = array();
    foreach (is_array($j) && isset($j['links']) && is_array($j['links']) ? $j['links'] : array() as $mid => $l)
    {
        $l = aio_pg_link_clean($l);
        if ($l && preg_match('/^' . AIO_DUNE_ID . '$/D', strval($mid)))
            $links[strval($mid)] = $l;
    }
    AioPg::$links = $links;
    aio_pg_rev_build();
    return $links;
}

// A pair as stored, of the file or of a source -> its fields that are good, null for none.
/**
 * @param mixed $l
 * @return AioPgLink|null
 */
function aio_pg_link_clean($l)
{
    if (!is_array($l))
        return null;
    if (isset($l['none']) && is_int($l['none']))
        return array('none' => $l['none']);
    if (isset($l['fail']) && is_int($l['fail']))
        return array('fail' => $l['fail']);
    $out = array();
    $imdb = aio_str($l, 'imdb');
    if (preg_match('/^tt[0-9]{1,10}$/D', $imdb))
        $out['imdb'] = $imdb;
    foreach (array('tmdb', 'tmdbtv') as $k)
    {
        $v = aio_str($l, $k);
        if (preg_match('/^[0-9]{1,10}$/D', $v))
            $out[$k] = $v;
    }
    if (!$out)
        return null;
    $type = aio_str($l, 'type');
    if ($type === 'movie' || $type === 'series')
        $out['type'] = $type;
    $seasons = array();
    foreach (isset($l['seasons']) && is_array($l['seasons']) ? $l['seasons'] : array() as $s => $n)
    {
        if (intval($s) >= 0 && is_int($n) && $n >= 1)
            $seasons[intval($s)] = $n;
    }
    if ($seasons)
    {
        ksort($seasons);
        $out['seasons'] = $seasons;
    }
    return $out;
}

// AioPg::$rev of AioPg::$links: "imdb:tt..", "tmdb:N", "tmdbtv:N" => movie_id.
/**
 * @return void
 */
function aio_pg_rev_build()
{
    $rev = array();
    foreach (AioPg::$links ? AioPg::$links : array() as $mid => $l)
    {
        foreach (array('imdb', 'tmdb', 'tmdbtv') as $k)
        {
            if (isset($l[$k]))
                $rev["$k:{$l[$k]}"] = strval($mid);
        }
    }
    AioPg::$rev = $rev;
}

// The pair of movie_id $mid set to $l (aio_pg_link_clean): the fields of a pair it has not kept; a pair
// over none or fail; none or fail only over none or fail. Written whole (aio_write_file) only on a change.
// -> it changed.
/**
 * @param string $mid
 * @param mixed $l
 * @return bool
 */
function aio_pg_link_put($mid, $l)
{
    $l = aio_pg_link_clean($l);
    if (!$l || !preg_match('/^' . AIO_DUNE_ID . '$/D', $mid))
        return false;
    $links = aio_pg_links();
    $old = isset($links[$mid]) ? $links[$mid] : null;
    $pair = !isset($l['none']) && !isset($l['fail']);
    $old_pair = $old && !isset($old['none']) && !isset($old['fail']);
    if ($old_pair && !$pair)
        return false;
    $new = $pair && $old_pair ? array_merge($old, array_filter($l)) : $l;
    if ($new == $old)
        return false;
    $links[$mid] = $new;
    AioPg::$links = $links;
    aio_pg_rev_build();
    $path = aio_data_dir() !== '' ? aio_data_dir() . '/' . AIO_PG_LINKS_FILE : '';
    $data = json_encode(array('v' => 1, 'links' => $links));
    $ok = $path !== '' && is_string($data) && aio_write_file($path, $data);
    aio_log("progress: pair of $mid " . ($pair ? 'ids' : (isset($l['none']) ? 'none' : 'fail')) . ($ok ? '' : ', not saved'));
    return true;
}

// The pair of the card of play_action: movie_str of the Dune (aio_movie) has its id, IMDb, the type
// and season_numbers.
/**
 * @param AioMovie $mv
 * @return void
 */
function aio_pg_link_movie($mv)
{
    if ($mv['dune_id'] !== '' && $mv['imdb'] !== '')
        aio_pg_link_put($mv['dune_id'], array('imdb' => $mv['imdb'], 'type' => $mv['series'] ? 'series' : 'movie',
            'seasons' => $mv['seasons']));
}

// The pair of the card $id found for tile $t (aio_card_id): its IMDb, its TMDB by its type.
/**
 * @param string $id
 * @param AioCatTile $t
 * @return void
 */
function aio_pg_link_tile($id, $t)
{
    $l = array('imdb' => $t['imdb_id'], 'type' => $t['type']);
    if ($t['_tmdbId'] !== '' && ($t['type'] === 'movie' || $t['type'] === 'series'))
        $l[$t['type'] === 'series' ? 'tmdbtv' : 'tmdb'] = $t['_tmdbId'];
    if ($id !== '')
        aio_pg_link_put($id, $l);
}

// The movie_id of the card of tile $t by the pairs (IMDb, else TMDB of its type), '' for none.
/**
 * @param AioCatTile $t
 * @return string
 */
function aio_pg_tile_card($t)
{
    aio_pg_links();
    $keys = array('imdb:' . $t['imdb_id']);
    if ($t['_tmdbId'] !== '')
        $keys[] = ($t['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $t['_tmdbId'];
    foreach ($keys as $k)
    {
        if (isset(AioPg::$rev[$k]))
            return AioPg::$rev[$k];
    }
    return '';
}

// The ids of a card of the dunemdb API in its raw text (a reply of movie/<id>, a card of the shell): the
// last "sources" not of a person ("type":N, before it) with "auxSources" after it, as the shell reads
// them (dunemdb_cache.php: sources first, then auxSources), the type by the first "type" of a title. No
// json_decode of the ~400 KB. -> the pair (aio_pg_link_clean), null for none.
/**
 * @param string $text
 * @return AioPgLink|null
 */
function aio_pg_parse_card($text)
{
    if (!preg_match_all('/(?<!"type":\d,)(?<!"type":\d\d,)"sources":(\{[^{}]*+\}),"auxSources":(\{[^{}]*+\})/', $text, $m,
        PREG_SET_ORDER))
        return null;
    $last = $m[count($m) - 1];
    $ids = array('imdb' => 'IMDB', 'tmdb' => 'TheMovieDB', 'tmdbtv' => 'TheMovieDBTV');
    $l = array();
    foreach ($ids as $k => $src)
    {
        foreach (array($last[1], $last[2]) as $obj)
        {
            if (!isset($l[$k]) && preg_match('/"' . $src . '":"([^"]*)"/', $obj, $v))
                $l[$k] = $v[1];
        }
    }
    if (preg_match('/"type":"(single|series)"/', $text, $t))
        $l['type'] = $t[1] === 'series' ? 'series' : 'movie';
    return aio_pg_link_clean($l);
}

// The path of the card of the shell of movie_id $mid, '' for none.
/**
 * @param string $mid
 * @return string
 */
function aio_pg_shell_path($mid)
{
    foreach (array(AIO_PG_SHELL_DIR, AIO_PG_SHELL_DIR2) as $dir)
    {
        if (is_file("$dir/$mid.json"))
            return "$dir/$mid.json";
    }
    return '';
}

// The pair of $mid from the card of the shell, null for none (no file, too big, no ids). Not asked again
// for it while php_server runs.
/**
 * @param string $mid
 * @return AioPgLink|null
 */
function aio_pg_shell_pair($mid)
{
    AioPg::$shell_seen[$mid] = true;
    $path = preg_match('/^[0-9a-f]{24}$/D', $mid) ? aio_pg_shell_path($mid) : '';
    $size = $path !== '' ? filesize($path) : 0;
    if ($size <= 0 || $size > AIO_PG_SHELL_MAX_BYTES)
        return null;
    return aio_pg_parse_card(strval(file_get_contents($path)));
}

// The cards of the history with no pair (a failed request too), their records ending the latest first ->
// movie_id => end.
/**
 * @return array<string, int>
 */
function aio_pg_unlinked()
{
    $links = aio_pg_links();
    $out = array();
    foreach (aio_pg_index() as $mid => $ent)
    {
        if (!isset($links[$mid]) || isset($links[$mid]['fail']))
            $out[strval($mid)] = $ent['end'];
    }
    arsort($out);
    return $out;
}

// The cards of the history with no pair from the cards of the shell, the latest first, in AIO_PG_SHELL_MS
// (one card at least), each once while php_server runs. A line of the log only when one was read.
/**
 * @return void
 */
function aio_pg_shell_step()
{
    $t = microtime(true);
    $read = 0;
    $found = 0;
    foreach (aio_pg_unlinked() as $mid => $end)
    {
        if (isset(AioPg::$shell_seen[$mid]))
            continue;
        if ($read && (microtime(true) - $t) * 1000 >= AIO_PG_SHELL_MS)
            break;
        $read++;
        $l = aio_pg_shell_pair($mid);
        if ($l && aio_pg_link_put($mid, $l))
            $found++;
    }
    if ($read)
        aio_log(sprintf('progress: shell cards: %d looked at, %d pairs, %.0f ms', $read, $found, (microtime(true) - $t) * 1000));
}

// The pair of $mid now: the card of the shell, else movie/<id> of the dunemdb API with dune_auth, as the shell
// opens its card (dunemdb_api.php api_movie), in $total s. Kept: a pair; none (the server has no such card or
// no ids in it); fail (no dune_auth, transport, another answer: asked again after AIO_PG_FAIL_S). One log line
// with the size and the time, no token. -> st: pair, none, fail; link: the pair or null.
/**
 * @param string $mid
 * @param float $total
 * @return array{st: string, link: AioPgLink|null}
 */
function aio_pg_link_now($mid, $total = AIO_PG_LINK_S)
{
    $l = aio_pg_shell_pair($mid);
    if ($l)
    {
        aio_pg_link_put($mid, $l);
        return array('st' => 'pair', 'link' => $l);
    }
    if (!preg_match('/^[0-9a-f]{24}$/D', $mid))
        return array('st' => 'none', 'link' => null);
    $token = aio_card_token();
    if ($token === '')
    {
        aio_log("link: movie/$mid: no dune_auth");
        return array('st' => 'fail', 'link' => null);
    }
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get(aio_card_api() . "movie/$mid?movieAnswerType=full_with_recommendations&lang=ru&$token",
        $err, AIO_PG_LINK_CONNECT, max(1, intval(ceil($total))), AIO_CAT_MAX_BYTES);
    $st = 'fail';
    if ($err === '' && $code === 200 && preg_match('/"code":\s*([0-9]+)/', $body, $m))
    {
        $l = intval($m[1]) === 100 ? aio_pg_parse_card($body) : null;
        $st = $l ? 'pair' : (intval($m[1]) === 100 || stripos($body, 'not found') !== false ? 'none' : 'fail');
    }
    // Only the number of curl: its text may hold the address.
    $why = $err !== '' ? (preg_match('/^curl [0-9]+/', $err, $e) ? $e[0] : ($err === AIO_HTTP_TOO_BIG ? 'too big' : 'failed')) :
        "HTTP $code";
    aio_log(sprintf('link: movie/%s %d B, %.0f ms, %s, %s', $mid, strlen($body), (microtime(true) - $t) * 1000, $why, $st));
    aio_pg_link_put($mid, $st === 'pair' ? $l : array($st => time()));
    return array('st' => $st, 'link' => $l);
}

// The lazy pairs of an opening: the cards of the history with no pair, the latest first, records that
// ended within AIO_PG_LINK_AGE_S, a failure older than AIO_PG_FAIL_S; up to AIO_PG_LINK_N requests in
// $limit s (the second one only with AIO_PG_LINK_NEXT_S left). -> how many were asked.
/**
 * @param float $limit
 * @return int
 */
function aio_pg_link_step($limit)
{
    $t = microtime(true);
    $now = time();
    $links = aio_pg_links();
    $n = 0;
    foreach (aio_pg_unlinked() as $mid => $end)
    {
        $left = $limit - (microtime(true) - $t);
        if ($n >= AIO_PG_LINK_N || $left < ($n ? AIO_PG_LINK_NEXT_S : 1))
            break;
        if ($end <= 0 || $now - $end > AIO_PG_LINK_AGE_S || !preg_match('/^[0-9a-f]{24}$/D', $mid))
            continue;
        if (isset($links[$mid]['fail']) && $now - $links[$mid]['fail'] >= 0 && $now - $links[$mid]['fail'] < AIO_PG_FAIL_S)
            continue;
        aio_pg_link_now($mid, $left);
        $n++;
    }
    return $n;
}
