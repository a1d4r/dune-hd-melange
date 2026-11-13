<?php
// Data of the own card asked only by an action, kept apart from the cache of titles of card_meta.php:
// a person (meta/person/tmdb:<id>.json), similar titles and the movies of a franchise (rows of the
// server; aio_cm_posters: both rows of an own card). No screens. Needs card_meta.php (its parsing
// and limits), catalog.php (aio_cat_get, aio_cat_tile) and main.php (aio_log).
// Types in the PHPDoc (AioCmPerson and others): phpstan-types.neon of the repository.

// Asked only by an action (a person, similar titles, a franchise): AIO_CM_MORE_KEEP of each kept
// for AIO_CM_TTL, not in the cache of titles. A person: up to 1 MB (Matthew Perry: 45 KB, 52 works),
// AIO_CM_WORKS_MAX works at most, a place of birth of AIO_CM_PLACE_MAX characters.
define('AIO_CM_MORE_KEEP', 3);
define('AIO_CM_WORKS_MAX', 100);
define('AIO_CM_PLACE_MAX', 200);
// An empty row (no similar, no franchise on the server) is asked again sooner, s.
define('AIO_CM_EMPTY_TTL', 300);
// Characters the font of the firmware has no glyph for (emoji): out of the BMP, the keycap U+20E3,
// the technical signs (U+2300-U+23FF: watches), the symbols and dingbats (U+2600-U+27BF), the
// arrows and shapes of U+2B00-U+2BFF (the star), the wavy dash U+3030, the selector of the emoji
// form, the joiner.
define('AIO_CM_EMOJI_RE', '/[\x{10000}-\x{10FFFF}\x{20E3}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{3030}\x{FE0F}\x{200D}]/u');
// The title of a section of a biography ("Главные награды: ..." of the server, its emoji out): of
// AIO_CM_HEAD_MAX characters and AIO_CM_HEAD_WORDS words at most, a capital first, no digits or marks.
define('AIO_CM_HEAD_MAX', 40);
define('AIO_CM_HEAD_WORDS', 4);
// The number of episodes of a work at most (One Piece: ~1150).
define('AIO_CM_WORK_EPS_MAX', 99999);
// The rows of posters of an own card (aio_cm_posters): POSTERS_TOTAL s for both at most, PARTS_MAX
// movies of a franchise at most.
define('AIO_CM_POSTERS_TOTAL', 3);
define('AIO_CM_PARTS_MAX', 40);

// What an action asked: memory of php_server only, gone with a restart.
class AioCmMore
{
    // kind => key (not a number: array_shift renumbers those) => when, for how long, what
    // (aio_cm_more_get).
    /** @var array<string, array<string, array{t: int, ttl: int, v: mixed}>> */
    public static $cache = array();
}

// What an action asked before (aio_cm_more_put): $kind of $key, kept less than its ttl ago; null
// for none.
/**
 * @param string $kind
 * @param string $key
 * @return mixed
 */
function aio_cm_more_get($kind, $key)
{
    if (!isset(AioCmMore::$cache[$kind][$key]))
        return null;
    $c = AioCmMore::$cache[$kind][$key];
    $now = time();
    // The clock going back is not trusted.
    if ($now >= $c['t'] && $now - $c['t'] < $c['ttl'])
        return $c['v'];
    unset(AioCmMore::$cache[$kind][$key]);
    return null;
}

// Keeps $v of $kind as $key for $ttl s: AIO_CM_MORE_KEEP of a kind at most, the oldest written
// goes first.
/**
 * @param string $kind
 * @param string $key
 * @param mixed $v
 * @param int $ttl
 * @return void
 */
function aio_cm_more_put($kind, $key, $v, $ttl = AIO_CM_TTL)
{
    unset(AioCmMore::$cache[$kind][$key]);
    AioCmMore::$cache[$kind][$key] = array('t' => time(), 'ttl' => $ttl, 'v' => $v);
    while (count(AioCmMore::$cache[$kind]) > AIO_CM_MORE_KEEP)
        array_shift(AioCmMore::$cache[$kind]);
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

// A biography of the server -> one line (aio_cm_bio_line) and its paragraphs (pars, aio_cm_bio_pars). bio_en: it is in English (the server
// gives it when TMDB has none in Russian): descriptionLang "en", or Latin letters and no Cyrillic.
// The meta of a person asked as tmdb:$tmdb -> what the person screen shows (AioCmPerson). photo:
// through the cache, as a poster. born, died: "Y-m-d", "Y-m", "Y" (aio_cm_person_date) or ''. place: of birth. known_for: the
// profession (knownFor of the server: Actor, Director; '' for none). works: the credits with an id
// the own card opens (aio_cm_id), no repeats, in the order of the server, AIO_CM_WORKS_MAX at most:
// tile (aio_cat_tile), role (role of the server: up to two characters or jobs, the character first,
// "Walter White, Director", in English), types (personTypes: Actor, Director...), '' and none when
// the server gives none; eps: the episodes of a series (episodes), 0 for none.
/**
 * @param array<mixed> $m
 * @param string $tmdb
 * @return AioCmPerson
 */
function aio_cm_person_parse($m, $tmdb)
{
    $born = aio_cm_person_date(aio_str($m, 'birthday'));
    $died = aio_cm_person_date(aio_str($m, 'deathday'));
    $bio = aio_cut(aio_cm_bio_line(aio_str($m, 'description')), AIO_CM_DESC_MAX);
    $pars = aio_cm_bio_pars(aio_str($m, 'description'));
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
        $eps = isset($c['episodes']) && is_int($c['episodes']) ? $c['episodes'] : 0;
        $works[] = array('tile' => $t, 'role' => aio_card_text(aio_str($c, 'role')),
            'types' => aio_cm_names(aio_arr($c, 'personTypes'), AIO_CM_LIST_MAX),
            'eps' => $eps > 0 && $eps <= AIO_CM_WORK_EPS_MAX ? $eps : 0);
    }
    return array('id' => "tmdb:$tmdb", 'name' => aio_cut(aio_one_line(aio_str($m, 'name')), AIO_CAT_NAME_MAX),
        'photo' => aio_cm_img(aio_str($m, 'poster'), 'poster'), 'born' => $born, 'died' => $died,
        'place' => aio_cut(aio_one_line(aio_str($m, 'birthPlace')), AIO_CM_PLACE_MAX), 'bio' => $bio, 'pars' => $pars,
        'bio_en' => aio_str($m, 'descriptionLang') === 'en' ||
            (preg_match('/[A-Za-z]/', $bio) === 1 && preg_match('/[\x{0400}-\x{04FF}]/u', $bio) === 0),
        'known_for' => aio_card_text(aio_str($m, 'knownFor')), 'works' => $works);
}

// A date of a person of the server -> "Y-m-d" (aio_cm_time); the day unknown "Y-m", the month too "Y";
// '' for none or a wrong one.
/**
 * @param string $s
 * @return string
 */
function aio_cm_person_date($s)
{
    list(, $d) = aio_cm_time($s);
    if ($d !== '' || !preg_match('/^([0-9]{4})(?:-([0-9]{2}))?$/D', $s, $x) || intval($x[1]) < 1)
        return $d;
    if (!isset($x[2]))
        return $x[1];
    return intval($x[2]) >= 1 && intval($x[2]) <= 12 ? "$x[1]-$x[2]" : '';
}

// The paragraphs of a biography, each one line: the emoji out (AIO_CM_EMOJI_RE: the font has none),
// the empty ones gone.
/**
 * @param string $s
 * @return list<string>
 */
function aio_cm_bio_split($s)
{
    $out = array();
    foreach (preg_split('/[\r\n\x{0085}\x{2028}\x{2029}]+/u', aio_utf8($s)) as $par)
    {
        $par = trim(strval(preg_replace('/ {2,}/', ' ', aio_one_line(preg_replace(AIO_CM_EMOJI_RE, '', $par)))));
        if ($par !== '')
            $out[] = $par;
    }
    return $out;
}

// A biography in paragraphs -> one line for the screen (aio_cm_bio_split): the paragraphs joined, a
// dot after one that ends with no mark of its end (". ! ? … : ;", a closing quote or bracket after it).
/**
 * @param string $s
 * @return string
 */
function aio_cm_bio_line($s)
{
    $out = array();
    foreach (aio_cm_bio_split($s) as $par)
        $out[] = preg_match('/[.!?\x{2026}:;]["\x{00BB}\x{201D})\]]*$/u', $par) ? $par : "$par.";
    return implode(' ', $out);
}

// A biography in paragraphs (aio_cm_bio_split) -> array(the title of its section, its text) each,
// AIO_CM_DESC_MAX characters in all at most. A paragraph that starts with a title and ":" ("Главные
// награды: Эмми (2008) ...") is a section, the title without ":"; a paragraph of a title alone
// ("Главные награды:") is the title of the paragraph after it. Others: no title ('').
/**
 * @param string $s
 * @return list<array{string, string}>
 */
function aio_cm_bio_pars($s)
{
    $out = array();
    $head = '';
    $left = AIO_CM_DESC_MAX;
    foreach (aio_cm_bio_split($s) as $par)
    {
        if ($left <= 0)
            break;
        $text = $par;
        if (preg_match('/^(\p{Lu}[^.!?:;,()\[\]\x{00AB}\x{00BB}"0-9]{0,' . (AIO_CM_HEAD_MAX - 1) . '}):\s*(.*)$/Du', $par, $x) &&
            count(explode(' ', trim($x[1]))) <= AIO_CM_HEAD_WORDS)
        {
            if ($head !== '')
                $out[] = array($head, '');
            $head = trim($x[1]);
            $text = $x[2];
        }
        if ($text === '')
            continue;
        $text = aio_cut($text, $left);
        $left -= mb_strlen($text, 'UTF-8');
        $out[] = array($head, $text);
        $head = '';
    }
    if ($head !== '')
        $out[] = array($head, '');
    return $out;
}

// The person of TMDB id $tmdb (digits, tmdb of AioCardCredit) for its screen, asked when ENTER opens
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

// Tiles of a row of the server asked by an action: from memory if asked less than AIO_CM_TTL ago
// (an empty row: AIO_CM_EMPTY_TTL), else in $limit s at most, parsed by $parse (aio_cat_tiles, aio_cm_parts_tiles). -> err: ''
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
    aio_cm_more_put($kind, $key, $tiles, $tiles ? AIO_CM_TTL : AIO_CM_EMPTY_TTL);
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
    if (!preg_match(AIO_CM_COLL_RE, $coll))
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

// The rows of posters of the own card $key of $tile with meta $meta, asked after the meta,
// POSTERS_TOTAL s for both at most (no meta: none, the server is not there): the movies of the
// franchise of a movie (aio_cm_parts) first, PARTS_MAX at most, none of fewer than 2 (the movie
// alone); then the similar titles (aio_cm_similar) if there is time left, without the movies of the
// franchise and the title itself. A failure, no reply in time: no row. -> sim, parts, cur (the index
// of the movie of the card among the parts, -1 for none), coll (the name of the franchise without
// "(Коллекция)").
/**
 * @param string $key
 * @param AioCatTile $tile
 * @param AioCardMeta|null $meta
 * @return AioCmPosters
 */
function aio_cm_posters($key, $tile, $meta)
{
    $out = array('sim' => array(), 'parts' => array(), 'cur' => -1, 'coll' => '');
    $id = aio_cm_id($tile);
    if (!$meta || $id === '')
        return $out;
    $t = microtime(true);
    $coll = $tile['type'] === 'movie' ? $meta['collection'] : array('id' => '', 'name' => '');
    $pe = 'none';
    if ($coll['id'] !== '')
    {
        $p = aio_cm_parts($coll['id'], $id, AIO_CM_POSTERS_TOTAL);
        $pe = $p['err'] !== '' ? $p['err'] : 'ok';
        if (count($p['tiles']) >= 2)
            $out = array_merge($out, array('parts' => array_slice($p['tiles'], 0, AIO_CM_PARTS_MAX),
                'cur' => $p['current'] < AIO_CM_PARTS_MAX ? $p['current'] : -1,
                'coll' => trim(strval(preg_replace('/\s*\((?:Коллекция|Collection)\)$/u', '', $coll['name'])))));
    }
    // Whole seconds (aio_cat_get): the time left rounded, half a second more at most.
    $left = intval(round(AIO_CM_POSTERS_TOTAL - (microtime(true) - $t)));
    $se = 'skipped';
    if ($left >= 1)
    {
        $g = aio_cm_similar($tile['type'], $id, $left);
        $se = $g['err'] !== '' ? $g['err'] : 'ok';
        $seen = array();
        foreach ($out['parts'] as $x)
            $seen[$x['type'] . ':' . aio_cm_id($x)] = true;
        foreach ($g['tiles'] as $x)
        {
            if (!isset($seen[$x['type'] . ':' . aio_cm_id($x)]) && !aio_cm_tile_is($x, $id) &&
                ($meta['imdb'] === '' || $x['imdb_id'] !== $meta['imdb']))
                $out['sim'][] = $x;
        }
    }
    aio_log(sprintf('card: own %s: similar %d (%s), franchise %d (%s), %.0f ms', $key, count($out['sim']), $se,
        count($out['parts']), $pe, (microtime(true) - $t) * 1000));
    return $out;
}
