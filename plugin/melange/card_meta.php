<?php
// Data of the own card of a title: the meta of the catalog server (meta/<movie|series>/<id>.json),
// asked when the card opens, parsed to what the card shows and kept in the memory of php_server.
// Also asked only by an action, kept apart from it: a person (meta/person/tmdb:<id>.json), similar
// titles and the movies of a franchise (rows of the server). No screens. Needs catalog.php
// (aio_cat_get), catalog_card.php (aio_card_people, limits) and main.php (aio_log). Not through
// aio_catv_load_first: a 429 here must not stop the rows.
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
// Actors of the own card at most: the server gives up to 30 (aggregate_credits of a series, since
// 13.11.2026), the local card of the shell keeps AIO_CARD_ACTORS_MAX.
define('AIO_CM_CAST_MAX', 30);
// Asked only by an action (a person, similar titles, a franchise): AIO_CM_MORE_KEEP of each kept
// for AIO_CM_TTL, not in the cache of titles. A person: up to 1 MB (Matthew Perry: 45 KB, 52 works),
// AIO_CM_WORKS_MAX works at most, a place of birth of AIO_CM_PLACE_MAX characters.
define('AIO_CM_MORE_KEEP', 3);
define('AIO_CM_WORKS_MAX', 100);
define('AIO_CM_PLACE_MAX', 200);
// The cache of pictures of the catalog server, next to its addon path.
define('AIO_CM_IMG', preg_replace('~stremio/$~', '', AIO_CAT_BASE) . 'poster-cache/');

// Parsed metas by "<type>:<id>": memory of php_server only, gone with a restart.
class AioCm
{
    /** @var array<string, array{t: int, n: int, m: AioCardMeta}> n: episodes of m */
    public static $cache = array();
    // What an action asked (aio_cm_more_get): kind => key (not a number: array_shift renumbers
    // those) => when and what.
    /** @var array<string, array<string, array{t: int, v: mixed}>> */
    public static $more = array();
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
 * @return AioCardActor
 */
function aio_cm_person_of($p)
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
 * @return array{cast: list<AioCardActor>, directors: list<AioCardActor>, writers: list<AioCardActor>, creators: list<AioCardActor>}
 */
function aio_cm_people($m)
{
    $out = array('cast' => array(), 'directors' => array(), 'writers' => array(), 'creators' => array());
    foreach (aio_card_people($m, AIO_CM_CAST_MAX) as $p)
    {
        $type = intval($p['type']);
        $out[$type === 0 ? 'cast' : ($type === 1 ? 'directors' : 'writers')][] = aio_cm_person_of($p);
    }
    // As the cast of a meta: aio_card_people finds no creators itself.
    $c = array('app_extras' => array('cast' => aio_arr(aio_arr($m, 'app_extras'), 'creators')));
    foreach (aio_card_people($c, AIO_CARD_CREW_MAX) as $p)
        $out['creators'][] = array_merge(aio_cm_person_of($p), array('role' => ''));
    return $out;
}

// The names of people of the own card, for a line of text.
/**
 * @param list<AioCardActor> $list
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
// name. collection: the franchise of a movie (belongsToCollection: tmdb.collection.<n>, its name),
// id '' for none. A series also seasons and season_numbers (aio_cm_eps).
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
    $coll = preg_match('/^tmdb\.collection\.[1-9][0-9]{0,9}$/D', aio_str($coll, 'id')) ?
        array('id' => aio_str($coll, 'id'), 'name' => aio_cut(aio_one_line(aio_str($coll, 'name')), AIO_CAT_NAME_MAX)) :
        array('id' => '', 'name' => '');
    $eps = $type === 'series' ? aio_cm_eps(aio_arr($m, 'videos'), $now) : array('seasons' => array(), 'season_numbers' => array());
    return array('type' => $type, 'id' => $id, 'imdb' => $imdb, 'tile' => aio_cat_tile($m, $type),
        'original' => aio_cut(aio_one_line(aio_str($m, 'originalTitle')), AIO_CAT_NAME_MAX),
        'description' => aio_cut(aio_one_line(aio_str($m, 'description')), AIO_CM_DESC_MAX),
        'tagline' => aio_cut(aio_one_line(aio_str($m, 'tagline')), AIO_CM_TAGLINE_MAX), 'released' => $released,
        'studios' => aio_cm_names(aio_arr($m, 'studios'), AIO_CM_LIST_MAX),
        'networks' => aio_cm_names(aio_arr($m, 'networks'), AIO_CM_LIST_MAX),
        'status' => $status === 'Returning Series' ? 'on' : ($status === 'Ended' ? 'ended' : ''),
        'countries' => aio_cm_names($countries, AIO_CARD_COUNTRIES_MAX), 'cast' => $people['cast'],
        'directors' => $people['directors'], 'writers' => $people['writers'], 'creators' => $people['creators'],
        'collection' => $coll, 'seasons' => $eps['seasons'], 'season_numbers' => $eps['season_numbers']);
}

// The season the streams of season $s of meta $m are asked by: 1 for one numbered on from season 1
// (its first episode the last of the season before + 1, and so each season back to 1: One Piece S2
// is E62-E77 after E1-E61, asked as :1:62, the episode as the absolute number), else $s. The
// history of such an episode is written so too.
/**
 * @param AioCardMeta $m
 * @param int $s
 * @return int
 */
function aio_cm_req_s($m, $s)
{
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
    $eps = aio_cm_eps_count($m);
    aio_log(sprintf('card: meta %s: imdb %s, %d seasons, %d episodes, %d seasons out, %d actors, %d directors, %d writers, %d creators, collection %s',
        $key, $m['imdb'] !== '' ? $m['imdb'] : 'none', count($m['seasons']), $eps, count($m['season_numbers']),
        count($m['cast']), count($m['directors']), count($m['writers']), count($m['creators']),
        $m['collection']['id'] !== '' ? $m['collection']['id'] : 'none'));
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

// --- Asked only by an action: a person, similar titles, a franchise.

// What an action asked before (aio_cm_more_put): $kind of $key, less than AIO_CM_TTL ago; null
// for none.
/**
 * @param string $kind
 * @param string $key
 * @return mixed
 */
function aio_cm_more_get($kind, $key)
{
    if (!isset(AioCm::$more[$kind][$key]))
        return null;
    $c = AioCm::$more[$kind][$key];
    $now = time();
    // The clock going back is not trusted.
    if ($now >= $c['t'] && $now - $c['t'] < AIO_CM_TTL)
        return $c['v'];
    unset(AioCm::$more[$kind][$key]);
    return null;
}

// Keeps $v of $kind as $key: AIO_CM_MORE_KEEP of a kind at most, the oldest written goes first.
/**
 * @param string $kind
 * @param string $key
 * @param mixed $v
 * @return void
 */
function aio_cm_more_put($kind, $key, $v)
{
    unset(AioCm::$more[$kind][$key]);
    AioCm::$more[$kind][$key] = array('t' => time(), 'v' => $v);
    while (count(AioCm::$more[$kind]) > AIO_CM_MORE_KEEP)
        array_shift(AioCm::$more[$kind]);
}

// An error of aio_cat_get -> what the screen can do: later (no reply in time or not now: timeout,
// net, rate, server; ask again), none (there is none or it will not do: notfound, bad, big, http).
/**
 * @param string $err
 * @return string
 */
function aio_cm_err_kind($err)
{
    return in_array($err, array('timeout', 'net', 'rate', 'server'), true) ? 'later' : 'none';
}

// Tile $t is of the title asked as $id (its IMDb or tmdb:<n>).
/**
 * @param AioCatTile $t
 * @param string $id
 * @return bool
 */
function aio_cm_tile_is($t, $id)
{
    return $t['imdb_id'] === $id || ($t['_tmdbId'] !== '' && 'tmdb:' . $t['_tmdbId'] === $id);
}

// The meta of a person asked as tmdb:$tmdb -> what the person screen shows (AioCmPerson). photo:
// through the cache, as a poster. born, died: "Y-m-d" or ''. place: of birth. bio: the description,
// as that of a title; bio_en: it is in English (the server gives it when TMDB has none in Russian):
// it has Latin letters and no Cyrillic, whatever field the server marks it by. known_for: the
// profession (knownFor of the server: Actor, Director; '' for none). works: the credits with an id
// the own card opens (aio_cm_id), no repeats, in the order of the server, AIO_CM_WORKS_MAX at most:
// tile (aio_cat_tile), role (role of the server: the character or the job, in English) and types
// (personTypes: Actor, Director...), '' and none when the server gives none.
/**
 * @param array<mixed> $m
 * @param string $tmdb
 * @return AioCmPerson
 */
function aio_cm_person_parse($m, $tmdb)
{
    list(, $born) = aio_cm_time(aio_str($m, 'birthday'));
    list(, $died) = aio_cm_time(aio_str($m, 'deathday'));
    $bio = aio_cut(aio_one_line(aio_str($m, 'description')), AIO_CM_DESC_MAX);
    $works = array();
    $seen = array();
    foreach (array_slice(aio_arr($m, 'credits'), 0, AIO_CARD_SCAN_MAX) as $c)
    {
        if (count($works) >= AIO_CM_WORKS_MAX)
            break;
        $t = aio_cat_tile($c, '');
        if (!$t)
            continue;
        $key = $t['type'] . ':' . aio_cm_id($t);
        if (aio_cm_id($t) === '' || isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $works[] = array('tile' => $t, 'role' => aio_card_text(aio_str($c, 'role')),
            'types' => aio_cm_names(aio_arr($c, 'personTypes'), AIO_CM_LIST_MAX));
    }
    return array('id' => "tmdb:$tmdb", 'name' => aio_cut(aio_one_line(aio_str($m, 'name')), AIO_CAT_NAME_MAX),
        'photo' => aio_cm_img(aio_str($m, 'poster'), 'poster'), 'born' => $born, 'died' => $died,
        'place' => aio_cut(aio_one_line(aio_str($m, 'birthPlace')), AIO_CM_PLACE_MAX), 'bio' => $bio,
        'bio_en' => preg_match('/[A-Za-z]/', $bio) === 1 && preg_match('/[\x{0400}-\x{04FF}]/u', $bio) === 0,
        'known_for' => aio_card_text(aio_str($m, 'knownFor')), 'works' => $works);
}

// The person of TMDB id $tmdb (digits, tmdb of AioCardActor) for its screen, asked when ENTER opens
// it: from memory if asked less than AIO_CM_TTL ago, else in $limit s at most (up to AIO_CAT_TOTAL:
// aio_cat_get cuts more). -> err: '' or none (no such person: a wrong id, 404, a reply of another
// or no name; asking again will not help) or later (no reply in time, the net, 429, 5xx: ask again);
// person: the parsed one or null.
/**
 * @param string $tmdb
 * @param int $limit
 * @return array{err: string, person: AioCmPerson|null}
 */
function aio_cm_person($tmdb, $limit)
{
    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $tmdb))
        return array('err' => 'none', 'person' => null);
    $key = "tmdb:$tmdb";
    /** @var AioCmPerson|null $p */
    $p = aio_cm_more_get('person', $key);
    if ($p)
    {
        aio_log("card: person $key cached");
        return array('err' => '', 'person' => $p);
    }
    $g = aio_cat_get("meta/person/$key.json", "person $key", 'meta', $limit, true);
    if ($g['err'] !== '')
        return array('err' => aio_cm_err_kind($g['err']), 'person' => null);
    $p = aio_str($g['list'], 'id') === $key ? aio_cm_person_parse($g['list'], $tmdb) : null;
    unset($g);
    if (!$p || $p['name'] === '')
    {
        aio_log("card: person $key: another or no name");
        return array('err' => 'none', 'person' => null);
    }
    aio_log(sprintf('card: person %s: %d works, bio %s', $key, count($p['works']),
        $p['bio'] === '' ? 'none' : ($p['bio_en'] ? 'en' : 'ru')));
    aio_cm_more_put('person', $key, $p);
    return array('err' => '', 'person' => $p);
}

// Tiles of a row of the server asked by an action: from memory if asked less than AIO_CM_TTL ago,
// else in $limit s at most, parsed by $parse (aio_cat_tiles, aio_cm_parts_tiles). -> err: ''
// or as aio_cm_person (none, later); tiles: none on a failure, and none may be all there is.
/**
 * @param string $kind
 * @param string $key
 * @param string $path
 * @param int $limit
 * @param string $type
 * @param callable $parse
 * @return array{err: string, tiles: list<AioCatTile>}
 */
function aio_cm_more_row($kind, $key, $path, $limit, $type, $parse)
{
    /** @var list<AioCatTile>|null $tiles */
    $tiles = aio_cm_more_get($kind, $key);
    if ($tiles !== null)
    {
        aio_log("card: $kind $key cached");
        return array('err' => '', 'tiles' => $tiles);
    }
    $g = aio_cat_get($path, "$kind $key", 'metas', $limit);
    if ($g['err'] !== '')
        return array('err' => aio_cm_err_kind($g['err']), 'tiles' => array());
    /** @var list<AioCatTile> $tiles */
    $tiles = call_user_func($parse, $g['list'], $type);
    unset($g);
    aio_cm_more_put($kind, $key, $tiles);
    return array('err' => '', 'tiles' => $tiles);
}

// Items of a franchise (metas of its movies) -> tiles, as a row of the catalog, by the date out
// (released), ascending; one of no date at the end; the same date in the order of the server.
/**
 * @param array<mixed> $items
 * @param string $type
 * @return list<AioCatTile>
 */
function aio_cm_parts_tiles($items, $type)
{
    $by = array();
    foreach (array_slice($items, 0, AIO_CAT_PAGE_TILES) as $i => $m)
    {
        $t = aio_cat_tile($m, $type);
        list(, $date) = aio_cm_time(aio_str($m, 'released'));
        if ($t)
            $by[($date !== '' ? $date : '9999') . sprintf('.%04d', $i)] = $t;
    }
    ksort($by, SORT_STRING);
    return array_values($by);
}

// Similar titles of the title of $type (movie, series) asked as $id (its IMDb or tmdb:<n>): 20
// recommendations of TMDB (aiom.similar of the server, one page), asked by an action, in $limit s
// at most, tiles as a row of the catalog. -> as aio_cm_more_row; a wrong type or id: none.
/**
 * @param string $type
 * @param string $id
 * @param int $limit
 * @return array{err: string, tiles: list<AioCatTile>}
 */
function aio_cm_similar($type, $id, $limit)
{
    if (($type !== 'movie' && $type !== 'series') || !preg_match('/^(?:tt|tmdb:)[0-9]{1,10}$/D', $id))
        return array('err' => 'none', 'tiles' => array());
    return aio_cm_more_row('similar', "$type:$id", "catalog/$type/aiom.similar/similar=$id.json", $limit, $type,
        'aio_cat_tiles');
}

// The movies of franchise $coll (collection of AioCardMeta: tmdb.collection.<n>) by the date out,
// asked by an action, in $limit s at most. -> as aio_cm_more_row; current: the index of the movie
// asked as $id (its IMDb or tmdb:<n>) among the tiles, -1 for none. A wrong id: none.
/**
 * @param string $coll
 * @param string $id
 * @param int $limit
 * @return array{err: string, tiles: list<AioCatTile>, current: int}
 */
function aio_cm_parts($coll, $id, $limit)
{
    if (!preg_match('/^tmdb\.collection\.[1-9][0-9]{0,9}$/D', $coll))
        return array('err' => 'none', 'tiles' => array(), 'current' => -1);
    $r = aio_cm_more_row('parts', $coll, "catalog/movie/$coll.json", $limit, 'movie', 'aio_cm_parts_tiles');
    $cur = -1;
    foreach ($r['tiles'] as $i => $t)
    {
        if ($id !== '' && aio_cm_tile_is($t, $id))
        {
            $cur = $i;
            break;
        }
    }
    return array('err' => $r['err'], 'tiles' => $r['tiles'], 'current' => $cur);
}
