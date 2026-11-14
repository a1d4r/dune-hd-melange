<?php
// Data of the own card of a title: the meta of the catalog server (meta/<movie|series>/<id>.json),
// asked when the card opens, parsed to what the card shows and kept in the memory of php_server.
// What is asked only by an action (a person, similar titles, a franchise): card_more.php. No screens. Needs catalog.php
// (aio_cat_get), catalog_card.php (aio_card_people, limits) and main.php (aio_log, aio_cin_save, aio_cin_load).
// Not through aio_catv_load_first: a 429 here must not stop the rows.
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
// A meta of a series without the pairs of Cinemeta (imdbPairs there and not true: Cinemeta did not answer in 3 s
// or does not know the title) is kept for this long, s: once the server pairs it, the card and the history go by
// the pairs and not by TMDB or an old runs file (aio_cm_parse).
define('AIO_CM_TTL_NO_PAIRS', 60);
// Limits of the texts, characters; episodes of a title at most (One Piece: 1180).
define('AIO_CM_DESC_MAX', 3000);
define('AIO_CM_TAGLINE_MAX', 300);
define('AIO_CM_EP_TITLE_MAX', 100);
define('AIO_CM_EPS_MAX', 3000);
define('AIO_CM_LIST_MAX', 5);
// Actors of the own card at most: the server gives up to 30 (aggregate_credits of a series, since
// 13.11.2026), the local card of the shell keeps AIO_CARD_ACTORS_MAX.
define('AIO_CM_CAST_MAX', 30);
// The id of a franchise (belongsToCollection of the meta of a movie).
define('AIO_CM_COLL_RE', '/^tmdb\.collection\.[1-9][0-9]{0,9}$/D');
// The cache of pictures of the catalog server, next to its addon path.
define('AIO_CM_IMG', preg_replace('~stremio/$~', '', AIO_CAT_BASE) . 'poster-cache/');

// Parsed metas by "<type>:<id>": memory of php_server only, gone with a restart.
class AioCm
{
    /** @var array<string, array{t: int, ttl: int, n: int, m: AioCardMeta}> n: episodes of m, ttl: kept for, s */
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

// A person of aio_card_people -> of the own card: the photo through the cache, tmdb: the id of TMDB
// (digits, the person screen asks by it), '' for none (a name only: meta.writer, a tvdb id).
/**
 * @param array<string, mixed> $p
 * @return AioCardCredit
 */
function aio_cm_credit_of($p)
{
    return array('name' => strval($p['name']), 'role' => isset($p['role']) ? strval($p['role']) : '',
        'photo' => isset($p['imgPhoto']) ? aio_cm_img(strval($p['imgPhoto']), 'background') : '',
        'tmdb' => preg_match('/^melange-tmdb-([1-9][0-9]*)$/D', strval($p['id']), $x) ? $x[1] : '');
}

// People of the meta, as aio_card_people finds them: cast (AIO_CM_CAST_MAX at most), directors,
// writers; creators of a series (app_extras.creators, AIO_CARD_CREW_MAX at most): their character
// is the name, so no role (the screen says card_creator). Crew: no role either.
/**
 * @param array<mixed> $m
 * @return array{cast: list<AioCardCredit>, directors: list<AioCardCredit>, writers: list<AioCardCredit>, creators: list<AioCardCredit>}
 */
function aio_cm_people($m)
{
    $out = array('cast' => array(), 'directors' => array(), 'writers' => array(), 'creators' => array());
    foreach (aio_card_people($m, AIO_CM_CAST_MAX) as $p)
    {
        $type = intval($p['type']);
        $out[$type === 0 ? 'cast' : ($type === 1 ? 'directors' : 'writers')][] = aio_cm_credit_of($p);
    }
    // As the cast of a meta: aio_card_people finds no creators itself.
    $c = array('app_extras' => array('cast' => aio_arr(aio_arr($m, 'app_extras'), 'creators')));
    foreach (aio_card_people($c, AIO_CARD_CREW_MAX) as $p)
        $out['creators'][] = array_merge(aio_cm_credit_of($p), array('role' => ''));
    return $out;
}

// The names of people of the own card, for a line of text.
/**
 * @param list<AioCardCredit> $list
 * @return list<string>
 */
function aio_cm_names_of($list)
{
    $out = array();
    foreach ($list as $p)
        $out[] = $p['name'];
    return $out;
}

// videos of the meta of a series -> seasons: season => its episodes by number, ascending, season 0
// (specials) left out; season_numbers: season => the last episode out by $now (released, UTC), as
// aio_seasons of play_action: the episodes are 1..N there (the playlist, the next episode). So a
// season whose E1 is not out is left out, numbered on from the last season too (One Piece: S2 is
// E62-E77): no playlist and no next episode for it. An episode not out (later or no date) is there
// with soon and its date. Repeats of an episode and numbers not 1 and up are dropped; AIO_CM_EPS_MAX
// episodes at most, in the order of the server. pairs: of each of them (season => episode =>
// array(absoluteEpisode, imdbSeason, imdbEpisode)) its number by TMDB with no specials (0 for none) and its
// pair of Cinemeta the server gives (aio_cm_pairs); 0, 0 for none (no such fields) and for one of season 0
// (a special of Cinemeta: S0 is not shown).
/**
 * @param array<mixed> $videos
 * @param int $now
 * @return array{seasons: array<int, list<AioCardEp>>, season_numbers: array<int, int>, pairs: array<int, array<int, array{int, int, int}>>}
 */
function aio_cm_eps($videos, $now)
{
    $by = array();
    $pairs = array();
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
        $ps = aio_str($v, 'imdbSeason');
        $pe = aio_str($v, 'imdbEpisode');
        $abs = aio_str($v, 'absoluteEpisode');
        $ok = preg_match('/^[1-9][0-9]{0,3}$/D', $ps) && preg_match('/^[1-9][0-9]{0,4}$/D', $pe);
        $pairs[$s][$e] = array(preg_match('/^[1-9][0-9]{0,4}$/D', $abs) ? intval($abs) : 0, $ok ? intval($ps) : 0,
            $ok ? intval($pe) : 0);
        $n++;
    }
    $seasons = aio_cm_sorted($by);
    return array('seasons' => $seasons, 'season_numbers' => aio_cm_out($seasons), 'pairs' => $pairs);
}

// Episodes by season and number (season => episode => it) -> seasons of the meta: ascending, a list each.
/**
 * @param array<int, array<int, AioCardEp>> $by
 * @return array<int, list<AioCardEp>>
 */
function aio_cm_sorted($by)
{
    ksort($by);
    $out = array();
    foreach ($by as $s => $eps)
    {
        ksort($eps);
        $out[$s] = array_values($eps);
    }
    return $out;
}

// season_numbers of seasons $seasons of the meta (aio_cm_eps): season => its last episode out, a season
// whose E1 is not out (or none) left out.
/**
 * @param array<int, list<AioCardEp>> $seasons
 * @return array<int, int>
 */
function aio_cm_out($seasons)
{
    $out = array();
    foreach ($seasons as $s => $eps)
    {
        if (!$eps || $eps[0]['e'] !== 1 || $eps[0]['soon'])
            continue;
        foreach ($eps as $ep)
        {
            if (!$ep['soon'])
                $out[$s] = $ep['e'];
        }
    }
    return $out;
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
// name. collection: the franchise of a movie (belongsToCollection: tmdb.collection.<n>, its name),
// id '' for none. A series also seasons and season_numbers (aio_cm_eps): by TMDB, or by the pairs of
// Cinemeta (by_cin: imdbPairs of the meta true, aio_cm_pairs; cin: the runs of an anime numbered on, none for
// any other). With no pairs (imdbPairs not true) an anime numbered on by TMDB goes by the runs file of its
// last opening with them (aio_cin_load: the pair of an episode by its number, aio_cin_pair), as with the pairs;
// with no file by TMDB. trailer: trailers[] not empty (the button "Трейлер", card_trailer.php).
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
    $coll = aio_arr($m, 'belongsToCollection');
    $coll = preg_match(AIO_CM_COLL_RE, aio_str($coll, 'id')) ?
        array('id' => aio_str($coll, 'id'), 'name' => aio_cut(aio_one_line(aio_str($coll, 'name')), AIO_CAT_NAME_MAX)) :
        array('id' => '', 'name' => '');
    $eps = $type === 'series' ? aio_cm_eps(aio_arr($m, 'videos'), $now) :
        array('seasons' => array(), 'season_numbers' => array(), 'pairs' => array());
    $out = array('type' => $type, 'id' => $id, 'imdb' => $imdb, 'tile' => aio_cat_tile($m, $type),
        'original' => aio_cut(aio_one_line(aio_str($m, 'originalTitle')), AIO_CAT_NAME_MAX),
        'description' => aio_cut(aio_one_line(aio_str($m, 'description')), AIO_CM_DESC_MAX),
        'tagline' => aio_cut(aio_one_line(aio_str($m, 'tagline')), AIO_CM_TAGLINE_MAX), 'released' => $released,
        'studios' => aio_cm_names(aio_arr($m, 'studios'), AIO_CM_LIST_MAX),
        'networks' => aio_cm_names(aio_arr($m, 'networks'), AIO_CM_LIST_MAX),
        'status' => $status === 'Returning Series' ? 'on' : ($status === 'Ended' ? 'ended' : ''),
        'countries' => aio_cm_names($countries, AIO_CARD_COUNTRIES_MAX), 'cast' => $people['cast'],
        'directors' => $people['directors'], 'writers' => $people['writers'], 'creators' => $people['creators'],
        'collection' => $coll, 'seasons' => $eps['seasons'], 'season_numbers' => $eps['season_numbers'],
        'trailer' => count(aio_arr($m, 'trailers')) > 0, 'by_cin' => false, 'cin' => array());
    if ($type !== 'series')
        return $out;
    if (isset($m['imdbPairs']) && $m['imdbPairs'] === true)
        return aio_cm_pairs($out, $eps['pairs'], 'pairs');
    $runs = $imdb !== '' && aio_cm_numbered_on($out) ? aio_cin_load($imdb) : array();
    return $runs ? aio_cm_pairs($out, aio_cm_run_pairs($out, $runs), 'runs file') : $out;
}

// The pairs of Cinemeta of the episodes of meta $m of an anime numbered on by TMDB (its episodes by their numbers
// of the meta, One Piece S21E923) by runs $runs (aio_cin_load), as aio_cm_eps gives them: season => episode =>
// array(its number, season, episode of Cinemeta by its run, aio_cin_pair; 0, 0 for none: a number of no pair or
// before the first run). A number after the last of the runs goes on in it (aio_cm_cin_runs).
/**
 * @param AioCardMeta $m
 * @param list<array{int, int, int}> $runs
 * @return array<int, array<int, array{int, int, int}>>
 */
function aio_cm_run_pairs($m, $runs)
{
    $out = array();
    foreach ($m['seasons'] as $s => $eps)
    {
        foreach ($eps as $ep)
        {
            $p = aio_cin_pair($runs, $ep['e']);
            $out[$s][$ep['e']] = $p ? array($ep['e'], $p[0], $p[1]) : array($ep['e'], 0, 0);
        }
    }
    return $out;
}

// Meta $m of a series by TMDB numbered by the pairs of Cinemeta $pairs of its episodes (aio_cm_eps: the server
// paired them, imdbPairs), as the native card of the Dune has it and AIOStreams asks: the seasons of Cinemeta, s
// and e of an episode its pair; an episode of no pair left out (One Piece E590, a special of Cinemeta; a new
// one Cinemeta has not yet), season_numbers anew (aio_cm_out), by_cin set. An anime numbered on by TMDB
// (aio_cm_numbered_on: One Piece S21E923) also cin: the runs of its numbers by the pairs (aio_cm_cin_runs; 923
// of S21E32, absoluteEpisode). One line of the log, of $what: where the pairs are from.
/**
 * @param AioCardMeta $m
 * @param array<int, array<int, array{int, int, int}>> $pairs
 * @param string $what
 * @return AioCardMeta
 */
function aio_cm_pairs($m, $pairs, $what)
{
    // Of the seasons of TMDB: before they and by_cin change (aio_cm_req_s with by_cin gives the season as it is).
    $on = aio_cm_numbered_on($m);
    $by = array();
    $abs = array();
    $map = array();
    $none = 0;
    foreach ($m['seasons'] as $s => $eps)
    {
        foreach ($eps as $ep)
        {
            list($n, $ps, $pe) = $pairs[$s][$ep['e']];
            if ($n > 0)
                $abs[$n] = $n;
            if ($ps === 0 || isset($by[$ps][$pe]))
            {
                $none++;
                continue;
            }
            $by[$ps][$pe] = array_merge($ep, array('s' => $ps, 'e' => $pe));
            if ($n > 0)
                $map[$n] = array($ps, $pe);
        }
    }
    ksort($abs);
    $runs = $map && $on ? aio_cm_cin_runs(array_values($abs), $map) : array();
    $m['seasons'] = aio_cm_sorted($by);
    $m['season_numbers'] = aio_cm_out($m['seasons']);
    $m['by_cin'] = true;
    $m['cin'] = $runs;
    aio_log(sprintf('card: %s %s: %d seasons, %d out, %d episodes of no pair left out, %d runs', $what, $m['id'],
        count($m['seasons']), count($m['season_numbers']), $none, count($runs)));
    return $m;
}

// The season the streams of season $s of meta $m are asked by: $s of the seasons of Cinemeta (by_cin); by TMDB
// 1 for one numbered on from season 1 (its first episode the last of the season before + 1, and so each season
// back to 1: One Piece S2 is E62-E77 after E1-E61, asked as :1:62, the episode as the absolute number), else
// $s. The history of such an episode is written so too.
/**
 * @param AioCardMeta $m
 * @param int $s
 * @return int
 */
function aio_cm_req_s($m, $s)
{
    if ($m['by_cin'])
        return $s;
    for ($t = $s; $t > 1; $t--)
    {
        if (!isset($m['seasons'][$t][0], $m['seasons'][$t - 1][0]))
            return $s;
        $prev = $m['seasons'][$t - 1];
        if ($m['seasons'][$t][0]['e'] !== $prev[count($prev) - 1]['e'] + 1)
            return $s;
    }
    return 1;
}

// The key of episode $e of season $s of meta $m in the history and the marks: "q:e", q the season it is asked
// by (aio_cm_req_s; by the seasons of Cinemeta s, by_cin of aio_cm_pairs).
/**
 * @param AioCardMeta $m
 * @param int $s
 * @param int $e
 * @return string
 */
function aio_cm_hkey($m, $s, $e)
{
    return aio_cm_req_s($m, $s) . ":$e";
}

// The episodes of meta $m, those not out too, by the season they are asked by (aio_cm_req_s):
// season => episode numbers, ascending (aio_wh_pick: the next one in this order).
/**
 * @param AioCardMeta $m
 * @return array<int, list<int>>
 */
function aio_cm_lists($m)
{
    $out = array();
    foreach ($m['seasons'] as $s => $eps)
    {
        $q = aio_cm_req_s($m, $s);
        foreach ($eps as $ep)
            $out[$q][] = $ep['e'];
    }
    foreach ($out as $q => $list)
    {
        sort($list);
        $out[$q] = array_values(array_unique($list));
    }
    ksort($out);
    return $out;
}

// Episode $e of season $q as asked (aio_cm_req_s) -> its season of meta $m: $q if it has it, else
// the one numbered on from the season before that has it; 0 for none.
/**
 * @param AioCardMeta $m
 * @param int $q
 * @param int $e
 * @return int
 */
function aio_cm_season_of($m, $q, $e)
{
    foreach ($m['seasons'] as $s => $eps)
    {
        if (($s === $q || ($q === 1 && aio_cm_req_s($m, $s) === 1)) && aio_cm_ep_index($eps, $e) >= 0)
            return $s;
    }
    return 0;
}

// The index of episode $e in $eps (a season of the meta), -1 for none.
/**
 * @param list<AioCardEp> $eps
 * @param int $e
 * @return int
 */
function aio_cm_ep_index($eps, $e)
{
    foreach ($eps as $i => $ep)
    {
        if ($ep['e'] === $e)
            return $i;
    }
    return -1;
}

// The episodes of meta $m in all.
/**
 * @param AioCardMeta $m
 * @return int
 */
function aio_cm_eps_count($m)
{
    $n = 0;
    foreach ($m['seasons'] as $list)
        $n += count($list);
    return $n;
}

// --- The numbering of Cinemeta (the pairs of the server, aio_cm_pairs).

// Meta $m of a series by TMDB has seasons after the first, each numbered on from season 1 (aio_cm_req_s: One
// Piece S2 is E62-E77, S23 E1156-E1180): an anime whose numbers (absoluteEpisode) the list and the own card show.
/**
 * @param AioCardMeta $m
 * @return bool
 */
function aio_cm_numbered_on($m)
{
    if (count($m['seasons']) < 2)
        return false;
    foreach (array_keys($m['seasons']) as $s)
    {
        if (aio_cm_req_s($m, $s) !== 1)
            return false;
    }
    return true;
}

// The numbers $a of the episodes of a series (absoluteEpisode, ascending) with their pairs of Cinemeta $map
// (number => array(season, episode)) -> runs of them: list of array(the first number, the season of Cinemeta,
// its episode), the next numbers the next episodes of the season up to the next run; season 0: numbers of no
// pair. The numbers after the last pair go on in its run (Cinemeta has no episode of the meta yet). The runs of
// One Piece: one a season of Cinemeta and one a number of no pair, under 40.
/**
 * @param list<int> $a
 * @param array<int, array{int, int}> $map
 * @return list<array{int, int, int}>
 */
function aio_cm_cin_runs($a, $map)
{
    $out = array();
    $last = '';
    $end = $map ? max(array_keys($map)) : 0;
    foreach ($a as $n)
    {
        if ($end > 0 && $n > $end)
            break;
        $p = isset($map[$n]) ? $map[$n] : array(0, 0);
        $k = $p[0] > 0 ? $p[0] . ':' . ($p[1] - $n) : '0';
        if ($k !== $last)
            $out[] = $p[0] > 0 ? array($n, $p[0], $p[1]) : array($n, 0, 0);
        $last = $k;
    }
    return $out;
}

// The meta of tile $t for its card: from memory if parsed less than AIO_CM_TTL ago (a series without the pairs
// of Cinemeta: AIO_CM_TTL_NO_PAIRS), else asked.
// -> err: '' or why there is none: noid (nothing to ask by), the errors of aio_cat_get (net,
// timeout, big, rate, server, notfound, http, bad), other (the meta of another title); meta: the
// parsed one or null. Only a parsed meta is kept: a failure is asked again at the next opening.
// Kept: AIO_CM_KEEP titles and AIO_CM_KEEP_EPS episodes in all at most, the oldest go. The runs of an anime
// numbered on (cin, aio_cm_pairs) to their file too (aio_cin_save): the ways in of the native card number its
// episodes by them.
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
        if ($now >= $c['t'] && $now - $c['t'] < $c['ttl'])
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
    $no = $series && isset($g['list']['imdbPairs']) && $g['list']['imdbPairs'] !== true;
    $ttl = $no ? AIO_CM_TTL_NO_PAIRS : AIO_CM_TTL;
    unset($g);
    if ($m['cin'])
        aio_cin_save($m['imdb'], $m['cin']);
    $eps = aio_cm_eps_count($m);
    aio_log(sprintf('card: meta %s: imdb %s, %d seasons, %d episodes, %d seasons out, %d actors, %d directors, %d writers, %d creators, collection %s',
        $key, $m['imdb'] !== '' ? $m['imdb'] : 'none', count($m['seasons']), $eps, count($m['season_numbers']),
        count($m['cast']), count($m['directors']), count($m['writers']), count($m['creators']),
        $m['collection']['id'] !== '' ? $m['collection']['id'] : 'none'));
    if ($eps > AIO_CM_KEEP_EPS)
        return array('err' => '', 'meta' => $m);
    AioCm::$cache[$key] = array('t' => $now, 'ttl' => $ttl, 'n' => $eps, 'm' => $m);
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

