<?php
// Data of the own card asked only by an action, kept apart from the cache of titles of card_meta.php:
// a person (meta/person/tmdb:<id>.json), similar titles and the movies of a franchise (rows of the
// server; mel_cm_posters: both rows of an own card). No screens. Needs card_meta.php (its parsing
// and limits), catalog.php (mel_cat_get, mel_cat_tile), hidden_titles.php (mel_ht_keep, mel_ht_off: what the rows and the
// works show) and main.php (mel_log).
// Types in the PHPDoc (MelCmPerson and others): phpstan-types.neon of the repository.

// Asked only by an action (a person, similar titles, a franchise): MEL_CM_MORE_KEEP persons and
// MEL_CM_ROWS_KEEP rows of each kind (as MEL_OC_KEEP cards: the chain of "Похожие" and Back do not
// ask again for the rows of a card seen) kept for MEL_CM_TTL, not in the cache of titles. A person:
// up to 1 MB (Matthew Perry: 45 KB, 52 works), MEL_CM_WORKS_MAX works at most, a place of birth of
// MEL_CM_PLACE_MAX characters.
define('MEL_CM_MORE_KEEP', 3);
define('MEL_CM_ROWS_KEEP', 8);
define('MEL_CM_WORKS_MAX', 100);
define('MEL_CM_PLACE_MAX', 200);
// An empty row (no similar, no franchise on the server) or a failure (an error, 429) is asked again
// sooner, s; no reply in time, the net, 5xx (the server may build the reply meanwhile) LATER_TTL s
// later: with the server down an opening does not wait for it each time.
define('MEL_CM_EMPTY_TTL', 300);
define('MEL_CM_LATER_TTL', 30);
// Characters the font of the firmware has no glyph for (emoji): out of the BMP, the keycap U+20E3,
// the technical signs (U+2300-U+23FF: watches), the symbols and dingbats (U+2600-U+27BF), the
// arrows and shapes of U+2B00-U+2BFF (the star), the wavy dash U+3030, the selector of the emoji
// form, the joiner.
define('MEL_CM_EMOJI_RE', '/[\x{10000}-\x{10FFFF}\x{20E3}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{3030}\x{FE0F}\x{200D}]/u');
// The title of a section of a biography ("🏆Главные награды🏆: ..." of the server, its emoji out): of
// MEL_CM_HEAD_MAX characters and MEL_CM_HEAD_WORDS words at most, a capital first, no digits, marks,
// dashes or "…" (mel_cm_bio_pars).
define('MEL_CM_HEAD_MAX', 40);
define('MEL_CM_HEAD_WORDS', 4);
// The number of episodes of a work at most (One Piece: ~1150).
define('MEL_CM_WORK_EPS_MAX', 99999);
// The rows of posters of an own card (mel_cm_posters): asked as the card opens (r24), POSTERS_TOTAL s
// for both at most; by the timer of the card, one a tick, ROW_LIMIT s each; PARTS_MAX movies of a
// franchise at most.
define('MEL_CM_POSTERS_TOTAL', 3);
define('MEL_CM_ROW_LIMIT', 3);
define('MEL_CM_PARTS_MAX', 40);

// What an action asked: memory of php_server only, gone with a restart.
class MelCmMore
{
    // kind => key (not a number: array_shift renumbers those) => when, for how long, what
    // (mel_cm_more_get).
    /** @var array<string, array<string, array{t: int, ttl: int, v: mixed}>> */
    public static $cache = array();
}

// What an action asked before (mel_cm_more_put): $kind of $key, kept less than its ttl ago; null
// for none.
/**
 * @param string $kind
 * @param string $key
 * @return mixed
 */
function mel_cm_more_get($kind, $key)
{
    if (!isset(MelCmMore::$cache[$kind][$key]))
        return null;
    $c = MelCmMore::$cache[$kind][$key];
    $now = time();
    // The clock going back is not trusted.
    if ($now >= $c['t'] && $now - $c['t'] < $c['ttl'])
        return $c['v'];
    unset(MelCmMore::$cache[$kind][$key]);
    return null;
}

// Keeps $v of $kind as $key for $ttl s: $keep of a kind at most, the oldest written goes first.
/**
 * @param string $kind
 * @param string $key
 * @param mixed $v
 * @param int $ttl
 * @param int $keep
 * @return void
 */
function mel_cm_more_put($kind, $key, $v, $ttl = MEL_CM_TTL, $keep = MEL_CM_MORE_KEEP)
{
    unset(MelCmMore::$cache[$kind][$key]);
    MelCmMore::$cache[$kind][$key] = array('t' => time(), 'ttl' => $ttl, 'v' => $v);
    while (count(MelCmMore::$cache[$kind]) > $keep)
        array_shift(MelCmMore::$cache[$kind]);
}

// An error of mel_cat_get -> what the screen can do: later (no reply in time or not now: timeout,
// net, rate, server; ask again), none (there is none or it will not do: notfound, bad, big, http).
/**
 * @param string $err
 * @return string
 */
function mel_cm_err_kind($err)
{
    return in_array($err, array('timeout', 'net', 'rate', 'server'), true) ? 'later' : 'none';
}

// Tile $t is of the title of $ids (its IMDb, tmdb:<n>; mel_cm_title_ids).
/**
 * @param MelCatTile $t
 * @param list<string> $ids
 * @return bool
 */
function mel_cm_tile_is($t, $ids)
{
    return ($t['imdb_id'] !== '' && in_array($t['imdb_id'], $ids, true)) ||
        ($t['_tmdbId'] !== '' && in_array('tmdb:' . $t['_tmdbId'], $ids, true));
}

// The ids of the title of $tile with meta $meta (or null): the IMDb and tmdb:<n> of the tile and of
// the tile of the meta (a card opened by its IMDb knows its TMDB of the meta), no empty, no repeats.
/**
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @return list<string>
 */
function mel_cm_title_ids($tile, $meta)
{
    $ids = array();
    foreach (array($tile, $meta && $meta['tile'] ? $meta['tile'] : null) as $t)
    {
        if ($t && $t['imdb_id'] !== '')
            $ids[] = $t['imdb_id'];
        if ($t && $t['_tmdbId'] !== '')
            $ids[] = 'tmdb:' . $t['_tmdbId'];
    }
    if ($meta && $meta['imdb'] !== '')
        $ids[] = $meta['imdb'];
    return array_values(array_unique($ids));
}

// A biography of the server -> one line (mel_cm_bio_line) and its paragraphs (pars, mel_cm_bio_pars). bio_en: it is in English (the server
// gives it when TMDB has none in Russian): descriptionLang "en", or Latin letters and no Cyrillic.
// The meta of a person asked as tmdb:$tmdb -> what the person screen shows (MelCmPerson). photo:
// through the cache, as a poster. born, died: "Y-m-d", "Y-m", "Y" (mel_cm_person_date) or ''. place: of birth. known_for: the
// profession (knownFor of the server: Actor, Director; '' for none). works: the credits with an id
// the own card opens (mel_cm_id), no repeats, in the order of the server, MEL_CM_WORKS_MAX at most:
// tile (mel_cat_tile), role (role of the server: up to two characters or jobs, the character first,
// "Walter White, Director", in English), types (personTypes: Actor, Director...), '' and none when
// the server gives none; eps: the episodes of a series (episodes), 0 for none.
/**
 * @param array<mixed> $m
 * @param string $tmdb
 * @return MelCmPerson
 */
function mel_cm_person_parse($m, $tmdb)
{
    $born = mel_cm_person_date(mel_str($m, 'birthday'));
    $died = mel_cm_person_date(mel_str($m, 'deathday'));
    $bio = mel_cut(mel_cm_bio_line(mel_str($m, 'description')), MEL_CM_DESC_MAX);
    $pars = mel_cm_bio_pars(mel_str($m, 'description'));
    $works = array();
    $seen = array();
    foreach (array_slice(mel_arr($m, 'credits'), 0, MEL_CARD_SCAN_MAX) as $c)
    {
        if (count($works) >= MEL_CM_WORKS_MAX)
            break;
        $t = mel_cat_tile($c, '');
        if (!$t)
            continue;
        $key = $t['type'] . ':' . mel_cm_id($t);
        if (mel_cm_id($t) === '' || isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $eps = isset($c['episodes']) && is_int($c['episodes']) ? $c['episodes'] : 0;
        $works[] = array('tile' => $t, 'role' => mel_card_text(mel_str($c, 'role')),
            'types' => mel_cm_names(mel_arr($c, 'personTypes'), MEL_CM_LIST_MAX),
            'eps' => $eps > 0 && $eps <= MEL_CM_WORK_EPS_MAX ? $eps : 0);
    }
    return array('id' => "tmdb:$tmdb", 'name' => mel_cut(mel_one_line(mel_str($m, 'name')), MEL_CAT_NAME_MAX),
        'photo' => mel_cm_img(mel_str($m, 'poster'), 'poster'), 'born' => $born, 'died' => $died,
        'place' => mel_cut(mel_one_line(mel_str($m, 'birthPlace')), MEL_CM_PLACE_MAX), 'bio' => $bio, 'pars' => $pars,
        'bio_en' => mel_str($m, 'descriptionLang') === 'en' ||
            (preg_match('/[A-Za-z]/', $bio) === 1 && preg_match('/[\x{0400}-\x{04FF}]/u', $bio) === 0),
        'known_for' => mel_card_text(mel_str($m, 'knownFor')), 'works' => $works);
}

// A date of a person of the server -> "Y-m-d" (mel_cm_time); the day unknown "Y-m", the month too "Y";
// '' for none or a wrong one.
/**
 * @param string $s
 * @return string
 */
function mel_cm_person_date($s)
{
    list(, $d) = mel_cm_time($s);
    if ($d !== '' || !preg_match('/^([0-9]{4})(?:-([0-9]{2}))?$/D', $s, $x) || intval($x[1]) < 1)
        return $d;
    if (!isset($x[2]))
        return $x[1];
    return intval($x[2]) >= 1 && intval($x[2]) <= 12 ? "$x[1]-$x[2]" : '';
}

// The paragraphs of a biography -> array(the paragraph as it came, it in one line with the emoji out
// (MEL_CM_EMOJI_RE: the font has none)) each, the empty ones gone.
/**
 * @param string $s
 * @return list<array{string, string}>
 */
function mel_cm_bio_split($s)
{
    $out = array();
    foreach (preg_split('/[\r\n\x{0085}\x{2028}\x{2029}]+/u', mel_utf8($s)) as $raw)
    {
        $par = trim(strval(preg_replace('/ {2,}/', ' ', mel_one_line(preg_replace(MEL_CM_EMOJI_RE, '', $raw)))));
        if ($par !== '')
            $out[] = array($raw, $par);
    }
    return $out;
}

// A biography in paragraphs -> one line for the screen (mel_cm_bio_split): the paragraphs joined, a
// dot after one that ends with no mark of its end (". ! ? … : ;", a closing quote or bracket after it).
/**
 * @param string $s
 * @return string
 */
function mel_cm_bio_line($s)
{
    $out = array();
    foreach (mel_cm_bio_split($s) as $x)
        $out[] = preg_match('/[.!?\x{2026}:;]["\x{00BB}\x{201D})\]]*$/u', $x[1]) ? $x[1] : "$x[1].";
    return implode(' ', $out);
}

// A biography in paragraphs (mel_cm_bio_split) -> array(the title of its section, its text) each,
// MEL_CM_DESC_MAX characters in all at most. A paragraph that starts with a title (MEL_CM_HEAD_MAX)
// with an emoji, then ":" (the server: "🏆Главные награды🏆: Эмми (2008) ...") is a section, the
// title without the emoji and ":"; a paragraph of a title alone ("Главные награды:") is the title of
// the paragraph after it. Others: no title (''): "Он часто говорил: ...", "Brad Pitt: born 1963".
/**
 * @param string $s
 * @return list<array{string, string}>
 */
function mel_cm_bio_pars($s)
{
    $out = array();
    $head = '';
    $left = MEL_CM_DESC_MAX;
    foreach (mel_cm_bio_split($s) as $y)
    {
        if ($left <= 0)
            break;
        list($raw, $par) = $y;
        $text = $par;
        if (preg_match('/^(\p{Lu}[^.!?:;,()\[\]\x{00AB}\x{00BB}"0-9\x{2010}-\x{2015}\x{2026}]{0,' . (MEL_CM_HEAD_MAX - 1) .
            '}):\s*(.*)$/Du', $par, $x) && count(explode(' ', trim($x[1]))) <= MEL_CM_HEAD_WORDS &&
            ($x[2] === '' || preg_match(MEL_CM_EMOJI_RE, strval(strstr($raw, ':', true)))))
        {
            if ($head !== '')
                $out[] = array($head, '');
            $head = trim($x[1]);
            $text = $x[2];
        }
        if ($text === '')
            continue;
        $text = mel_cut($text, $left);
        $left -= mb_strlen($text, 'UTF-8');
        $out[] = array($head, $text);
        $head = '';
    }
    if ($head !== '')
        $out[] = array($head, '');
    return $out;
}

// The person of TMDB id $tmdb (digits, tmdb of MelCardCredit) for its screen, asked when ENTER opens
// it: from memory if asked less than MEL_CM_TTL ago, else in $limit s at most (up to MEL_CAT_TOTAL:
// mel_cat_get cuts more). -> err: '' or none (no such person: a wrong id, 404, a reply of another
// or no name; asking again will not help) or later (no reply in time, the net, 429, 5xx: ask again);
// person: the parsed one or null, its works all of them (the screen shows them through mel_cm_person_shown).
/**
 * @param string $tmdb
 * @param int $limit
 * @return array{err: string, person: MelCmPerson|null}
 */
function mel_cm_person($tmdb, $limit)
{
    if (!preg_match('/^[1-9][0-9]{0,9}$/D', $tmdb))
        return array('err' => 'none', 'person' => null);
    $key = "tmdb:$tmdb";
    /** @var MelCmPerson|null $p */
    $p = mel_cm_more_get('person', $key);
    if ($p)
    {
        mel_log("card: person $key cached");
        return array('err' => '', 'person' => $p);
    }
    $g = mel_cat_get("meta/person/$key.json", "person $key", 'meta', $limit, true);
    if ($g['err'] !== '')
        return array('err' => mel_cm_err_kind($g['err']), 'person' => null);
    $p = mel_str($g['list'], 'id') === $key ? mel_cm_person_parse($g['list'], $tmdb) : null;
    unset($g);
    if (!$p || $p['name'] === '')
    {
        mel_log("card: person $key: another or no name");
        return array('err' => 'none', 'person' => null);
    }
    mel_log(sprintf('card: person %s: %d works, bio %s', $key, count($p['works']),
        $p['bio'] === '' ? 'none' : ($p['bio_en'] ? 'en' : 'ru')));
    mel_cm_more_put('person', $key, $p);
    return array('err' => '', 'person' => $p);
}

// Person $p (kept whole in memory) with the works the filter shows (mel_ht_off: hidden, the age and the IMDb rating of
// the settings), in their order: a title hidden or the filter changed since it was asked shows so at once. The screen of the person
// (mel_pp_person) draws them; its keys go by the works it drew.
/**
 * @param MelCmPerson $p
 * @return MelCmPerson
 */
function mel_cm_person_shown($p)
{
    $f = mel_ht_now();
    $works = array();
    foreach ($p['works'] as $w)
    {
        if (!mel_ht_off($f, $w['tile']))
            $works[] = $w;
    }
    $p['works'] = $works;
    return $p;
}

// Tiles of a row of the server asked by an action: from memory if asked less than MEL_CM_TTL ago
// (an empty row, a failure: MEL_CM_EMPTY_TTL; no reply in time, the net, 5xx: MEL_CM_LATER_TTL),
// MEL_CM_ROWS_KEEP of a kind, else in $limit s at most, parsed by $parse (mel_cat_tiles,
// mel_cm_parts_tiles), only those the own card opens (an id of mel_cm_id: not mal:...), no repeats; the filter
// ("Скрыть", the age) is of the callers, on the way out of memory, so a change of it shows at once.
// -> err: '' or as mel_cm_person (none, later); tiles: none on a failure, and none may be all there is.
/**
 * @param string $kind
 * @param string $key
 * @param string $path
 * @param int $limit
 * @param string $type
 * @param callable $parse
 * @return array{err: string, tiles: list<MelCatTile>}
 */
function mel_cm_more_row($kind, $key, $path, $limit, $type, $parse)
{
    /** @var array{err: string, tiles: list<MelCatTile>}|null $r */
    $r = mel_cm_more_get($kind, $key);
    if ($r !== null)
    {
        mel_log("card: $kind $key cached" . ($r['err'] !== '' ? " ({$r['err']})" : ''));
        return $r;
    }
    $g = mel_cat_get($path, "$kind $key", 'metas', $limit);
    $tiles = array();
    if ($g['err'] === '')
    {
        $seen = array();
        foreach (call_user_func($parse, $g['list'], $type) as $t)
        {
            $id = $t['type'] . ':' . mel_cm_id($t);
            if (mel_cm_id($t) !== '' && !isset($seen[$id]))
                $tiles[] = $t;
            $seen[$id] = true;
        }
    }
    $r = array('err' => $g['err'] !== '' ? mel_cm_err_kind($g['err']) : '', 'tiles' => $tiles);
    $ttl = in_array($g['err'], array('timeout', 'net', 'server'), true) ? MEL_CM_LATER_TTL : MEL_CM_EMPTY_TTL;
    unset($g);
    mel_cm_more_put($kind, $key, $r, $tiles ? MEL_CM_TTL : $ttl, MEL_CM_ROWS_KEEP);
    return $r;
}

// Items of a franchise (metas of its movies) -> tiles, as a row of the catalog, by the date out
// (released), ascending; one of no date at the end; the same date in the order of the server.
/**
 * @param array<mixed> $items
 * @param string $type
 * @return list<MelCatTile>
 */
function mel_cm_parts_tiles($items, $type)
{
    $by = array();
    foreach (array_slice($items, 0, MEL_CAT_PAGE_TILES) as $i => $m)
    {
        $t = mel_cat_tile($m, $type);
        list(, $date) = mel_cm_time(mel_str($m, 'released'));
        if ($t)
            $by[($date !== '' ? $date : '9999') . sprintf('.%04d', $i)] = $t;
    }
    ksort($by, SORT_STRING);
    return array_values($by);
}

// Similar titles of the title of $type (movie, series) asked as $id (its IMDb or tmdb:<n>): 20
// recommendations of TMDB (aiom.similar of the server, one page), asked by an action, in $limit s
// at most, tiles as a row of the catalog. -> as mel_cm_more_row, the tiles the filter shows (mel_ht_keep: hidden, the
// age); a wrong type or id: none.
/**
 * @param string $type
 * @param string $id
 * @param int $limit
 * @return array{err: string, tiles: list<MelCatTile>}
 */
function mel_cm_similar($type, $id, $limit)
{
    $key = mel_cm_similar_key($type, $id);
    if ($key === '')
        return array('err' => 'none', 'tiles' => array());
    $r = mel_cm_more_row('similar', $key, "catalog/$type/aiom.similar/similar=$id.json", $limit, $type, 'mel_cat_tiles');
    $r['tiles'] = mel_ht_keep($r['tiles']);
    return $r;
}

// The key of the similar titles of the title of $type asked as $id in memory (MelCmMore) as
// mel_cm_similar asks them: "<type>:<id>"; '' for a wrong type or id (none asked).
/**
 * @param string $type
 * @param string $id
 * @return string
 */
function mel_cm_similar_key($type, $id)
{
    return ($type === 'movie' || $type === 'series') && preg_match('/^(?:tt|tmdb:)[0-9]{1,10}$/D', $id) ? "$type:$id" : '';
}

// The movies of franchise $coll (collection of MelCardMeta: tmdb.collection.<n>) by the date out,
// asked by an action, in $limit s at most. -> as mel_cm_more_row, the tiles the filter shows (mel_ht_off: hidden, the
// age, the IMDb rating) and the movie of $ids (mel_cm_title_ids: its own card is open) whatever it; current: its index
// among them, -1 for none. A wrong id: none.
/**
 * @param string $coll
 * @param list<string> $ids
 * @param int $limit
 * @return array{err: string, tiles: list<MelCatTile>, current: int}
 */
function mel_cm_parts($coll, $ids, $limit)
{
    $key = mel_cm_parts_key($coll);
    if ($key === '')
        return array('err' => 'none', 'tiles' => array(), 'current' => -1);
    $r = mel_cm_more_row('parts', $key, "catalog/movie/$key.json", $limit, 'movie', 'mel_cm_parts_tiles');
    $f = mel_ht_now();
    $tiles = array();
    $cur = -1;
    foreach ($r['tiles'] as $t)
    {
        $is = mel_cm_tile_is($t, $ids);
        if ($is && $cur < 0)
            $cur = count($tiles);
        if ($is || !mel_ht_off($f, $t))
            $tiles[] = $t;
    }
    return array('err' => $r['err'], 'tiles' => $tiles, 'current' => $cur);
}

// The key of the movies of franchise $coll in memory (MelCmMore) as mel_cm_parts asks them: $coll;
// '' for a wrong id (none asked).
/**
 * @param string $coll
 * @return string
 */
function mel_cm_parts_key($coll)
{
    return preg_match(MEL_CM_COLL_RE, $coll) ? $coll : '';
}

// The rows of posters of the own card of $tile with meta $meta, none asked yet: wait - what to ask, in
// order: the movies of the franchise of a movie (parts; its id right, mel_cm_parts_key), then the similar
// titles (sim); nothing without the meta (the server is not there) or an id. st: how each went, for the
// log (none: not asked); ms: the time of the asks.
// The order parts, then sim matters: mel_oc_poster_rows knows no similar ones while some are asked.
/**
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @return MelCmPosters
 */
function mel_cm_posters_new($tile, $meta)
{
    $wait = array();
    if ($meta && mel_cm_id($tile) !== '')
    {
        if ($tile['type'] === 'movie' && mel_cm_parts_key($meta['collection']['id']) !== '')
            $wait[] = 'parts';
        $wait[] = 'sim';
    }
    return array('sim' => array(), 'parts' => array(), 'cur' => -1, 'coll' => '', 'wait' => $wait,
        'st' => array('parts' => 'none', 'sim' => 'none'), 'ms' => 0);
}

// The first of wait of rows $p of $tile with meta $meta is in memory (MelCmMore: asked before, a
// failure too; by the key mel_cm_parts and mel_cm_similar ask it by) or has a wrong id (nothing to
// wait for): asking it takes no time.
/**
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @param MelCmPosters $p
 * @return bool
 */
function mel_cm_posters_kept($tile, $meta, $p)
{
    if (!$p['wait'] || !$meta)
        return false;
    if ($p['wait'][0] === 'parts')
        list($kind, $key) = array('parts', mel_cm_parts_key($meta['collection']['id']));
    else
        list($kind, $key) = array('similar', mel_cm_similar_key($tile['type'], mel_cm_id($tile)));
    return $key === '' || mel_cm_more_get($kind, $key) !== null;
}

// The first of wait of rows $p of the own card $key ($tile, meta $meta) asked, in $limit s at most:
// the movies of the franchise (mel_cm_parts), PARTS_MAX at most, none of fewer than 2 (the movie
// alone); the similar titles (mel_cm_similar) without the movies of the franchise and the title itself
// (any of its ids, mel_cm_title_ids). A failure, no reply in time: no row. The last asked: the log of
// both, by the timer of the card ($timer) or not.
/**
 * @param string $key
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @param MelCmPosters $p
 * @param int $limit
 * @param bool $timer
 * @return MelCmPosters
 */
function mel_cm_posters_step($key, $tile, $meta, $p, $limit, $timer)
{
    if (!$p['wait'] || !$meta)
        return $p;
    $t = microtime(true);
    $ids = mel_cm_title_ids($tile, $meta);
    if (array_shift($p['wait']) === 'parts')
    {
        $coll = $meta['collection'];
        $r = mel_cm_parts($coll['id'], $ids, $limit);
        $p['st']['parts'] = $r['err'] !== '' ? $r['err'] : 'ok';
        if (count($r['tiles']) >= 2)
            $p = array_merge($p, array('parts' => array_slice($r['tiles'], 0, MEL_CM_PARTS_MAX),
                'cur' => $r['current'] < MEL_CM_PARTS_MAX ? $r['current'] : -1,
                'coll' => trim(strval(preg_replace('/\s*\((?:Коллекция|Collection)\)$/u', '', $coll['name'])))));
    }
    else
    {
        $g = mel_cm_similar($tile['type'], mel_cm_id($tile), $limit);
        $p['st']['sim'] = $g['err'] !== '' ? $g['err'] : 'ok';
        $seen = array();
        foreach ($p['parts'] as $x)
            $seen[$x['type'] . ':' . mel_cm_id($x)] = true;
        foreach ($g['tiles'] as $x)
        {
            if (!isset($seen[$x['type'] . ':' . mel_cm_id($x)]) && !mel_cm_tile_is($x, $ids))
                $p['sim'][] = $x;
        }
    }
    $p['ms'] += intval(round((microtime(true) - $t) * 1000));
    if (!$p['wait'])
        mel_cm_posters_log($key, $p, $timer);
    return $p;
}

/**
 * @param string $key
 * @param MelCmPosters $p
 * @param bool $timer
 * @return void
 */
function mel_cm_posters_log($key, $p, $timer)
{
    mel_log(sprintf('card: own %s: similar %d (%s), franchise %d (%s), %d ms%s', $key, count($p['sim']), $p['st']['sim'],
        count($p['parts']), $p['st']['parts'], $p['ms'], $timer ? ', timer' : ''));
}

// The rows of posters of the own card $key of $tile with meta $meta as it opens (mel_cm_posters_new):
// $sync (no timer of the card, r24) - all asked, POSTERS_TOTAL s for both at most, the similar titles
// not asked (skipped) with less than a second left; else those in memory only (mel_cm_posters_kept),
// the rest left in wait for the timer of the card (mel_cm_posters_step).
/**
 * @param string $key
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @param bool $sync
 * @return MelCmPosters
 */
function mel_cm_posters($key, $tile, $meta, $sync)
{
    $p = mel_cm_posters_new($tile, $meta);
    $t = microtime(true);
    while ($p['wait'] && ($sync || mel_cm_posters_kept($tile, $meta, $p)))
    {
        // Whole seconds (mel_cat_get): the time left rounded, half a second more at most.
        $left = $sync ? intval(round(MEL_CM_POSTERS_TOTAL - (microtime(true) - $t))) : MEL_CM_ROW_LIMIT;
        if ($left < 1)
        {
            foreach ($p['wait'] as $k)
                $p['st'][$k] = 'skipped';
            $p['wait'] = array();
            mel_cm_posters_log($key, $p, false);
        }
        else
            $p = mel_cm_posters_step($key, $tile, $meta, $p, $left, false);
    }
    return $p;
}
