<?php
// Data of the own card of a title: the meta of the catalog server (meta/<movie|series>/<id>.json),
// asked when the card opens, parsed to what the card shows and kept in the memory of php_server.
// No screens. Needs catalog.php (aio_cat_get), catalog_card.php (aio_card_people, limits) and
// main.php (aio_log). Not through aio_catv_load_first: a 429 here must not stop the rows.
// Types in the PHPDoc (AioCardMeta and others): phpstan-types.neon of the repository.

// A series: up to 4 MB (One Piece 1.1 MB, cold 9.5 s, 12.11.2026) in 10 s. A movie: the
// limit of the catalog and AIO_CARD_META_TOTAL.
define('AIO_CM_SERIES_BYTES', 4194304);
define('AIO_CM_SERIES_TOTAL', 10);
// Titles kept in memory, their episodes at most in all, and for how long, s; the oldest written
// goes first. A title of more episodes is not kept.
define('AIO_CM_KEEP', 8);
define('AIO_CM_KEEP_EPS', 3000);
define('AIO_CM_TTL', 3600);
// Limits of the texts, characters; episodes of a title at most (One Piece: 1180).
define('AIO_CM_DESC_MAX', 3000);
define('AIO_CM_TAGLINE_MAX', 300);
define('AIO_CM_EP_TITLE_MAX', 100);
define('AIO_CM_EPS_MAX', 3000);
define('AIO_CM_LIST_MAX', 5);
// The cache of pictures of the catalog server, next to its addon path.
define('AIO_CM_IMG', preg_replace('~stremio/$~', '', AIO_CAT_BASE) . 'poster-cache/');

// Parsed metas by "<type>:<id>": memory of php_server only, gone with a restart.
class AioCm
{
    /** @var array<string, array{t: int, n: int, m: AioCardMeta}> n: episodes of m */
    public static $cache = array();
}

// The id of the meta to ask for tile $t: its IMDb, else tmdb:<id>; '' for none (mal:..., no type).
/**
 * @param AioCatTile $t
 * @return string
 */
function aio_cm_id($t)
{
    if ($t['type'] !== 'movie' && $t['type'] !== 'series')
        return '';
    if ($t['imdb_id'] !== '')
        return $t['imdb_id'];
    return $t['_tmdbId'] !== '' ? 'tmdb:' . $t['_tmdbId'] : '';
}

// A picture of the meta -> the address the Dune loads, '' for none. TMDB (blocked for many,
// straight or already in the cache of the server) goes through the cache of the server in
// class $class: background keeps the size and shape (a still 500x281, a face 276x350), w342/poster
// fits a 2:3 poster. Another host stays as it is; a TMDB address of another form is dropped.
/**
 * @param string $url
 * @param string $class
 * @return string
 */
function aio_cm_img($url, $class)
{
    if (preg_match('~(' . AIO_CAT_TMDB . '[A-Za-z0-9]{10,40}\.(?:jpg|png))$~D', $url, $m))
        return aio_img(AIO_CM_IMG . "$class/$m[1]");
    $u = aio_cat_img(array('u' => $url), 'u');
    return $u !== '' && stripos($u, 'image.tmdb.org') === false ? aio_img($u) : '';
}

// An ISO time of the server ("2026-11-16T01:00:00.000Z", UTC) -> array(unix time, "Y-m-d");
// array(0, '') for none or a wrong date.
/**
 * @param string $s
 * @return array{int, string}
 */
function aio_cm_time($s)
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})(?:T([0-9]{2}):([0-9]{2})(?::([0-9]{2}))?)?/', $s, $x) ||
        !checkdate(intval($x[2]), intval($x[3]), intval($x[1])))
        return array(0, '');
    $t = gmmktime(isset($x[4]) ? intval($x[4]) : 0, isset($x[5]) ? intval($x[5]) : 0, isset($x[6]) ? intval($x[6]) : 0,
        intval($x[2]), intval($x[3]), intval($x[1]));
    return array(intval($t), "$x[1]-$x[2]-$x[3]");
}

// Short texts of a list of the meta (studios, networks, countries), no repeats.
/**
 * @param array<mixed> $list
 * @param int $max
 * @return list<string>
 */
function aio_cm_names($list, $max)
{
    $out = array();
    foreach (array_slice($list, 0, AIO_CARD_SCAN_MAX) as $v)
    {
        $v = aio_card_text($v);
        if ($v !== '' && !in_array($v, $out, true))
            $out[] = $v;
        if (count($out) >= $max)
            break;
    }
    return $out;
}

// People of the meta, as aio_card_people finds them: cast (AIO_CARD_ACTORS_MAX at most, the photo
// through the cache), directors and writers by name. The creators of a series are not in 1.0.
/**
 * @param array<mixed> $m
 * @return array{cast: list<AioCardActor>, directors: list<string>, writers: list<string>}
 */
function aio_cm_people($m)
{
    $out = array('cast' => array(), 'directors' => array(), 'writers' => array());
    foreach (aio_card_people($m) as $p)
    {
        $type = intval($p['type']);
        if ($type === 0)
            $out['cast'][] = array('name' => strval($p['name']), 'role' => isset($p['role']) ? strval($p['role']) : '',
                'photo' => isset($p['imgPhoto']) ? aio_cm_img(strval($p['imgPhoto']), 'background') : '');
        else
            $out[$type === 1 ? 'directors' : 'writers'][] = strval($p['name']);
    }
    return $out;
}

// videos of the meta of a series -> seasons: season => its episodes by number, ascending, season 0
// (specials) left out; season_numbers: season => the last episode out by $now (released, UTC), as
// aio_seasons of play_action: the episodes are 1..N there (the playlist, the next episode). So a
// season whose E1 is not out is left out, numbered on from the last season too (One Piece: S2 is
// E62-E77): no playlist and no next episode for it. An episode not out (later or no date) is there
// with soon and its date. Repeats of an episode and numbers not 1 and up are dropped;
// AIO_CM_EPS_MAX episodes at most, in the order of the server.
/**
 * @param array<mixed> $videos
 * @param int $now
 * @return array{seasons: array<int, list<AioCardEp>>, season_numbers: array<int, int>}
 */
function aio_cm_eps($videos, $now)
{
    $by = array();
    $last = array();
    $n = 0;
    foreach ($videos as $v)
    {
        if ($n >= AIO_CM_EPS_MAX)
            break;
        $s = aio_str($v, 'season');
        $e = aio_str($v, 'episode');
        if (!preg_match('/^[0-9]{1,5}$/D', $s) || !preg_match('/^[0-9]{1,5}$/D', $e))
            continue;
        $s = intval($s);
        $e = intval($e);
        if ($s < 1 || $e < 1 || isset($by[$s][$e]))
            continue;
        list($t, $date) = aio_cm_time(aio_str($v, 'released'));
        $soon = $t === 0 || $t > $now;
        $by[$s][$e] = array('s' => $s, 'e' => $e,
            'title' => aio_cut(aio_one_line(aio_str($v, 'title')), AIO_CM_EP_TITLE_MAX), 'date' => $date,
            'img' => aio_cm_img(aio_str($v, 'thumbnail'), 'background'),
            'min' => aio_cat_runtime_min(aio_str($v, 'runtime')), 'soon' => $soon);
        $n++;
        if (!$soon && (!isset($last[$s]) || $e > $last[$s]))
            $last[$s] = $e;
    }
    ksort($by);
    ksort($last);
    $seasons = array();
    foreach ($by as $s => $eps)
    {
        ksort($eps);
        $seasons[$s] = array_values($eps);
        if (isset($last[$s]) && (!isset($eps[1]) || $eps[1]['soon']))
            unset($last[$s]);
    }
    return array('seasons' => $seasons, 'season_numbers' => $last);
}

// The meta $m of the title asked as $id is of it: IMDb in imdb_id or id, tmdb:<n> in _tmdbId or id.
/**
 * @param array<mixed> $m
 * @param string $id
 * @return bool
 */
function aio_cm_same($m, $id)
{
    if (aio_str($m, 'id') === $id)
        return true;
    return strpos($id, 'tmdb:') === 0 ? aio_str($m, '_tmdbId') === substr($id, 5) : aio_str($m, 'imdb_id') === $id;
}

// The meta $m of a title of $type asked as $id -> what the card shows (AioCardMeta). imdb: $id,
// or for tmdb:<n> the IMDb of the meta ('' for none). status: on (Returning Series), ended (Ended),
// '' for another. released: "Y-m-d" (UTC). original: originalTitle. tile: the meta as a tile of the
// catalog (aio_cat_tile), the card of a title no row keeps (php_server restarted); null without a
// name. A series also seasons and season_numbers (aio_cm_eps).
/**
 * @param array<mixed> $m
 * @param string $type
 * @param string $id
 * @param int $now
 * @return AioCardMeta
 */
function aio_cm_parse($m, $type, $id, $now)
{
    $imdb = $id;
    if (strpos($id, 'tmdb:') === 0)
    {
        $imdb = aio_str($m, 'imdb_id');
        if (!preg_match('/^tt[0-9]{1,10}$/D', $imdb))
            $imdb = preg_match('/^tt[0-9]{1,10}$/D', aio_str($m, 'id')) ? aio_str($m, 'id') : '';
    }
    $countries = aio_arr($m, 'countries');
    if (!$countries)
        $countries = explode(',', aio_str($m, 'country'));
    $status = aio_str($m, 'status');
    list(, $released) = aio_cm_time(aio_str($m, 'released'));
    $people = aio_cm_people($m);
    $eps = $type === 'series' ? aio_cm_eps(aio_arr($m, 'videos'), $now) : array('seasons' => array(), 'season_numbers' => array());
    return array('type' => $type, 'id' => $id, 'imdb' => $imdb, 'tile' => aio_cat_tile($m, $type),
        'original' => aio_cut(aio_one_line(aio_str($m, 'originalTitle')), AIO_CAT_NAME_MAX),
        'description' => aio_cut(aio_one_line(aio_str($m, 'description')), AIO_CM_DESC_MAX),
        'tagline' => aio_cut(aio_one_line(aio_str($m, 'tagline')), AIO_CM_TAGLINE_MAX), 'released' => $released,
        'studios' => aio_cm_names(aio_arr($m, 'studios'), AIO_CM_LIST_MAX),
        'networks' => aio_cm_names(aio_arr($m, 'networks'), AIO_CM_LIST_MAX),
        'status' => $status === 'Returning Series' ? 'on' : ($status === 'Ended' ? 'ended' : ''),
        'countries' => aio_cm_names($countries, AIO_CARD_COUNTRIES_MAX), 'cast' => $people['cast'],
        'directors' => $people['directors'], 'writers' => $people['writers'],
        'seasons' => $eps['seasons'], 'season_numbers' => $eps['season_numbers']);
}

// The meta of tile $t for its card: from memory if parsed less than AIO_CM_TTL ago, else asked.
// -> err: '' or why there is none: noid (nothing to ask by), the errors of aio_cat_get (net,
// timeout, big, rate, server, notfound, http, bad), other (the meta of another title); meta: the
// parsed one or null. Only a parsed meta is kept: a failure is asked again at the next opening.
// Kept: AIO_CM_KEEP titles and AIO_CM_KEEP_EPS episodes in all at most, the oldest go.
/**
 * @param AioCatTile $t
 * @return array{err: string, meta: AioCardMeta|null}
 */
function aio_cm_get($t)
{
    $id = aio_cm_id($t);
    if ($id === '')
        return array('err' => 'noid', 'meta' => null);
    $type = $t['type'];
    $key = "$type:$id";
    $now = time();
    if (isset(AioCm::$cache[$key]))
    {
        $c = AioCm::$cache[$key];
        // The clock going back is not trusted.
        if ($now >= $c['t'] && $now - $c['t'] < AIO_CM_TTL)
        {
            aio_log("card: meta $key cached");
            return array('err' => '', 'meta' => $c['m']);
        }
        unset(AioCm::$cache[$key]);
    }
    $series = $type === 'series';
    $g = aio_cat_get("meta/$type/$id.json", "meta $key", 'meta', $series ? AIO_CM_SERIES_TOTAL : AIO_CARD_META_TOTAL, true,
        $series ? AIO_CM_SERIES_BYTES : AIO_CAT_MAX_BYTES);
    if ($g['err'] !== '')
        return array('err' => $g['err'], 'meta' => null);
    if (!aio_cm_same($g['list'], $id))
    {
        aio_log("card: meta $key of another title");
        return array('err' => 'other', 'meta' => null);
    }
    $m = aio_cm_parse($g['list'], $type, $id, $now);
    unset($g);
    $eps = 0;
    foreach ($m['seasons'] as $list)
        $eps += count($list);
    aio_log(sprintf('card: meta %s: imdb %s, %d seasons, %d episodes, %d seasons out, %d actors, %d directors, %d writers',
        $key, $m['imdb'] !== '' ? $m['imdb'] : 'none', count($m['seasons']), $eps, count($m['season_numbers']),
        count($m['cast']), count($m['directors']), count($m['writers'])));
    if ($eps > AIO_CM_KEEP_EPS)
        return array('err' => '', 'meta' => $m);
    AioCm::$cache[$key] = array('t' => $now, 'n' => $eps, 'm' => $m);
    $sum = 0;
    foreach (AioCm::$cache as $c)
        $sum += $c['n'];
    while (count(AioCm::$cache) > AIO_CM_KEEP || $sum > AIO_CM_KEEP_EPS)
    {
        $c = array_shift(AioCm::$cache);
        $sum -= $c['n'];
    }
    return array('err' => '', 'meta' => $m);
}
