<?php
// ENTER on a tile of the catalog: the native card of the Dune, its id found by
// movieBySigns; without one, for a tile with IMDb, a local card of the shell (made
// from a movie tile when the server is sure it has none); else the releases of
// Melange, no history kept; without an IMDb id either a message. Needs catalog.php
// (its cache) and main.php. dune_auth is only in the address of a request: no log,
// dialog or file gets that address.
// Types in the PHPDoc (AioCatTile, AioAction): phpstan-types.neon of the repository.

define('AIO_CARD_API', 'http://dunemdb.dune-hd.com:5535/');
// Timeouts of a request, s: from the Dune it takes 0.2 s, 0.5 s at most. Two
// requests stay under 6 s: after a transport failure the second is not asked.
define('AIO_CARD_CONNECT', 2);
define('AIO_CARD_TOTAL', 3);
// A card this many years off the tile is taken: 1 is the most a right card was seen
// off (Buratino 2025, its tile 2026), 6 and 12 were wrong ones (Mowgli 1967, Phantom Power 2026).
define('AIO_CARD_YEAR_DIFF', 1);
// A week: duplicates and suppliers of a title change slowly, and one more look costs 0.5 s.
define('AIO_CARD_TTL_ID', 604800);
// A day: new cards appear in days, none seen younger than a day (03.11.2026).
define('AIO_CARD_TTL_NONE', 86400);
// The meta of a movie asked of the catalog server: once when its local card is written, and
// by the own card (card_meta.php). 0.4 s seen (05.11.2026), "none" 1.8 s. ENTER waits for it;
// a failure leaves the card of the tile.
define('AIO_CARD_META_TOTAL', 4);
// show_dialog_delay of ENTER on a tile, ms: the card waits for the network (the meta of a series
// takes over a second cold), "Please wait" is due soon.
define('AIO_CARD_DELAY', 1000);
// Limits of the local card: the server gives up to 10 actors; genres as a tile
// (AIO_CAT_GENRES_MAX); ratings: ~45 countries seen, each a short text ("TE+7", "K-12").
// Of a list of the meta only the first AIO_CARD_SCAN_MAX items are looked at.
define('AIO_CARD_ACTORS_MAX', 10);
define('AIO_CARD_CREW_MAX', 5);
define('AIO_CARD_GENRES_MAX', 8);
define('AIO_CARD_COUNTRIES_MAX', 10);
define('AIO_CARD_RATINGS_MAX', 100);
define('AIO_CARD_RATING_LEN', 10);
define('AIO_CARD_TEXT_MAX', 60);
define('AIO_CARD_SCAN_MAX', 200);
// Local cards of the shell (movies/custom_dunemdb.php): PHP of the Dune adds
// FS_PREFIX to /flashdata itself. Tests may define it first.
if (!defined('AIO_USER_CARD_DIR'))
    define('AIO_USER_CARD_DIR', '/flashdata/custom_mdb/user_movie');

// The content of dune_auth.txt: "dune_auth=<value>", '' if there is none.
/**
 * @return string
 */
function aio_card_token()
{
    $f = getenv('FS_PREFIX') . '/tmp/dune_auth.txt';
    return is_file($f) ? trim(strval(file_get_contents($f))) : '';
}

// As the shell: the prefix of the API from its file, if another plugin has set it.
/**
 * @return string
 */
function aio_card_api()
{
    $f = getenv('FS_PREFIX') . '/tmp/run/shell_ext_dunemdb_api_url.txt';
    $p = is_file($f) ? trim(strval(file_get_contents($f))) : '';
    if (!preg_match('~^https?://[^/?#\s]~', $p))
        return AIO_CARD_API;
    return substr($p, -1) === '/' ? $p : "$p/";
}

// One sign -> st: card, none (not found), refused (no dune_auth accepted), net
// (transport), bad (anything else); card: id, year (0 if none), type, sup (suppliers)
// or null. "Not found" and the refusal both come with code 0, only message tells.
// lang: no text of the card is used, and ru is what the choice was measured on.
/**
 * @param string $sign
 * @param string $token
 * @return array{st: string, card: array{id: string, year: int, type: string, sup: int}|null}
 */
function aio_card_ask($sign, $token)
{
    $t = microtime(true);
    $err = '';
    list($code, $body) = aio_http_get(aio_card_api() . "movieBySigns/$sign?movieAnswerType=short&lang=ru&$token", $err,
        AIO_CARD_CONNECT, AIO_CARD_TOTAL, AIO_CAT_MAX_BYTES);
    $st = 'bad';
    $card = null;
    $why = "HTTP $code";
    if ($err !== '')
    {
        $st = 'net';
        // Only the number: the text of curl may hold the address.
        $why = preg_match('/^curl [0-9]+/', $err, $m) ? $m[0] : 'failed';
    }
    else if ($code === 200)
    {
        $j = json_decode($body, true);
        $r = is_array($j) && isset($j['result']) ? $j['result'] : null;
        $msg = strtolower(aio_str($j, 'message'));
        $id = aio_str($r, 'id');
        $why = '';
        if (intval(aio_str($j, 'code')) === 100 && preg_match('/^[0-9a-f]{24}$/D', $id))
        {
            $st = 'card';
            $card = array('id' => $id, 'year' => intval(aio_str($r, 'year')), 'type' => aio_str($r, 'type'),
                'sup' => isset($r['suppliers']) && is_array($r['suppliers']) ? count($r['suppliers']) : 0);
            $why = "$id year={$card['year']} type={$card['type']} sup={$card['sup']}";
        }
        else if ($msg === 'not found')
            $st = 'none';
        else if (strpos($msg, 'auth') !== false)
            $st = 'refused';
    }
    aio_log(sprintf('card: %s: %s %s, %.0f ms', $sign, $st, $why, (microtime(true) - $t) * 1000));
    return array('st' => $st, 'card' => $card);
}

// The type of the tile, not of the card: a card can have a wrong one.
/**
 * @param array{id: string, year: int, type: string, sup: int} $c
 * @param bool $series
 * @return array{int, int, int}
 */
function aio_card_rank($c, $series)
{
    return array($c['sup'], $c['year'] > 0 ? 1 : 0, ($c['type'] === 'series') === $series ? 1 : 0);
}

// Off the year of the tile by more than AIO_CARD_YEAR_DIFF: not this title. A card
// with year 0 stays (the only card of a title can be such), a tile without a year
// checks nothing. Then more suppliers, a non-zero year, the type of the tile; a
// tie goes to tmdb, the more reliable sign. -> the card or null.
/**
 * @param array{id: string, year: int, type: string, sup: int}|null $tmdb
 * @param array{id: string, year: int, type: string, sup: int}|null $imdb
 * @param AioCatTile $tile
 * @return array{id: string, year: int, type: string, sup: int}|null
 */
function aio_card_pick($tmdb, $imdb, $tile)
{
    $series = $tile['type'] === 'series';
    $best = null;
    foreach (array($tmdb, $imdb) as $c)
    {
        if (!$c)
            continue;
        if ($c['year'] > 0 && $tile['year'] !== '' && abs($c['year'] - intval($tile['year'])) > AIO_CARD_YEAR_DIFF)
            aio_log("card: {$c['id']} year {$c['year']}, tile {$tile['year']}: not taken");
        else if (!$best || aio_card_rank($c, $series) > aio_card_rank($best, $series))
            $best = $c;
    }
    return $best;
}

// The card of the tile: id, '' for none; none: the server answered every sign and
// no card fits (now or cached), not a failure, a refusal, no dune_auth or no sign.
// Only full answers go to the cache: a failure says nothing about the card, and a
// card without the answer of the other sign may not be the one to keep. With a
// refused or failed request the second is not asked. Without dune_auth nothing is
// asked: the shell can not open a card either.
/**
 * @param AioCatTile $tile
 * @return array{id: string, none: bool}
 */
function aio_card_id($tile)
{
    $token = aio_card_token();
    if ($token === '')
    {
        aio_log('card: no dune_auth');
        return array('id' => '', 'none' => false);
    }
    $signs = array();
    // A TMDB id is of a movie or of a series: without the type it means nothing.
    if ($tile['_tmdbId'] !== '' && $tile['type'] !== '')
        $signs['tmdb'] = ($tile['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $tile['_tmdbId'];
    if ($tile['imdb_id'] !== '')
        $signs['imdb'] = 'imdb:' . $tile['imdb_id'];
    if (!$signs)
        return array('id' => '', 'none' => false);
    $key = "card|{$tile['type']}|{$tile['_tmdbId']}|{$tile['imdb_id']}|{$tile['year']}";
    $c = aio_cat_cache_read($key);
    if ($c)
    {
        $id = aio_str($c['data'], 'id');
        // A saved time ahead of the clock (a clock not set after a reboot) is not trusted.
        if (time() >= $c['t'] && time() - $c['t'] < ($id !== '' ? AIO_CARD_TTL_ID : AIO_CARD_TTL_NONE))
        {
            aio_log('card: cached ' . ($id !== '' ? $id : 'none'));
            return array('id' => $id, 'none' => $id === '');
        }
    }
    $cards = array('tmdb' => null, 'imdb' => null);
    $whole = true;
    foreach ($signs as $by => $sign)
    {
        $a = aio_card_ask($sign, $token);
        $cards[$by] = $a['card'];
        if ($a['st'] !== 'card' && $a['st'] !== 'none')
            $whole = false;
        if ($a['st'] === 'net' || $a['st'] === 'refused')
            break;
    }
    $card = aio_card_pick($cards['tmdb'], $cards['imdb'], $tile);
    $id = $card ? $card['id'] : '';
    if ($whole)
        aio_cat_cache_write($key, 1, array('id' => $id));
    return array('id' => $id, 'none' => $whole && $id === '');
}

// The handler of file_movie in r25 reads movie_id only: the card takes its type
// from the server.
/**
 * @param string $id
 * @return AioAction
 */
function aio_card_open($id)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => 'shell_ext',
        GuiAction::data => null,
        GuiAction::params => array('handler_id' => 'entry', 'control_id' => 'file_movie', 'movie_id' => $id));
}

// Text for the local card: one line, cut, no < and > (the shell puts names into its
// markup).
/**
 * @param mixed $s
 * @return string
 */
function aio_card_text($s)
{
    return trim(str_replace(array('<', '>'), '', aio_cut(aio_one_line(aio_str(array($s), 0)), AIO_CARD_TEXT_MAX)));
}

// A country of the server (an English name, ISO-3 as aio_cat_country) -> its code of the
// shell (known_country_codes.txt; the English names of its translations, those of TMDB),
// shown in the language of the Dune; another: "_" and the name, which the shell shows in
// lower case (parse_country_value); '' for none.
/**
 * @param mixed $c
 * @return string
 */
function aio_card_country($c)
{
    static $iso = array(
        'afghanistan' => 'af', 'albania' => 'al', 'algeria' => 'dz', 'andorra' => 'ad', 'angola' => 'ao',
        'argentina' => 'ar', 'armenia' => 'am', 'australia' => 'au', 'austria' => 'at', 'azerbaijan' => 'az',
        'bahamas' => 'bs', 'bahrain' => 'bh', 'bangladesh' => 'bd', 'barbados' => 'bb',
        'belarus' => 'by', 'belgium' => 'be', 'belize' => 'bz', 'benin' => 'bj', 'bhutan' => 'bt', 'bolivia' => 'bo',
        'bosnia and herzegovina' => 'ba', 'botswana' => 'bw', 'brazil' => 'br', 'brunei' => 'bn',
        'brunei darussalam' => 'bn', 'bulgaria' => 'bg', 'burkina faso' => 'bf', 'burundi' => 'bi', 'cambodia' => 'kh',
        'cameroon' => 'cm', 'canada' => 'ca', 'central african republic' => 'cf', 'chad' => 'td', 'chile' => 'cl',
        'china' => 'cn', 'colombia' => 'co', 'congo' => 'cg', 'cook islands' => 'ck', 'costa rica' => 'cr',
        'cote d\'ivoire' => 'ci', 'croatia' => 'hr', 'cuba' => 'cu', 'cyprus' => 'cy', 'czech republic' => 'cz',
        'czechia' => 'cz', 'côte d\'ivoire' => 'ci', 'democratic republic of the congo' => 'cd', 'denmark' => 'dk',
        'dominica' => 'dm', 'dominican republic' => 'do', 'dprk' => 'kp', 'ecuador' => 'ec', 'egypt' => 'eg',
        'el salvador' => 'sv', 'england' => 'gb', 'equatorial guinea' => 'gq', 'eritrea' => 'er', 'estonia' => 'ee',
        'ethiopia' => 'et', 'fiji' => 'fj', 'finland' => 'fi', 'france' => 'fr', 'gabon' => 'ga', 'gambia' => 'gm',
        'georgia' => 'ge', 'germany' => 'de', 'ghana' => 'gh', 'great britain' => 'gb', 'greece' => 'gr',
        'guadeloupe' => 'gp', 'guam' => 'gu', 'guatemala' => 'gt', 'guinea' => 'gn', 'guyana' => 'gy', 'haiti' => 'ht',
        'holy see' => 'va', 'honduras' => 'hn', 'hong kong' => 'hk', 'hungary' => 'hu', 'iceland' => 'is', 'india' => 'in',
        'indonesia' => 'id', 'iran' => 'ir', 'iraq' => 'iq', 'ireland' => 'ie', 'israel' => 'il', 'italy' => 'it',
        'ivory coast' => 'ci', 'jamaica' => 'jm', 'japan' => 'jp', 'jordan' => 'jo', 'kazakhstan' => 'kz', 'kenya' => 'ke',
        'korea' => 'kr', 'kuwait' => 'kw', 'kyrgyzstan' => 'kg', 'lao people\'s democratic republic' => 'la', 'laos' => 'la',
        'latvia' => 'lv', 'lebanon' => 'lb', 'liberia' => 'lr', 'libya' => 'ly', 'libyan arab jamahiriya' => 'ly',
        'liechtenstein' => 'li', 'lithuania' => 'lt', 'luxembourg' => 'lu', 'macedonia' => 'mk', 'madagascar' => 'mg',
        'malawi' => 'mw', 'malaysia' => 'my', 'maldives' => 'mv', 'mali' => 'ml', 'malta' => 'mt', 'mauritania' => 'mr',
        'mexico' => 'mx', 'moldova' => 'md', 'monaco' => 'mc', 'mongolia' => 'mn', 'montenegro' => 'me', 'morocco' => 'ma',
        'mozambique' => 'mz', 'myanmar' => 'mm', 'namibia' => 'na', 'nepal' => 'np', 'netherlands' => 'nl',
        'new zealand' => 'nz', 'nicaragua' => 'ni', 'niger' => 'ne', 'nigeria' => 'ng', 'north korea' => 'kp',
        'north macedonia' => 'mk', 'norway' => 'no', 'oman' => 'om', 'pakistan' => 'pk', 'panama' => 'pa',
        'papua new guinea' => 'pg', 'paraguay' => 'py', 'peru' => 'pe', 'philippines' => 'ph', 'poland' => 'pl',
        'portugal' => 'pt', 'puerto rico' => 'pr', 'qatar' => 'qa', 'republic of korea' => 'kr', 'romania' => 'ro',
        'russia' => 'ru', 'russian federation' => 'ru', 'rwanda' => 'rw', 'san marino' => 'sm', 'saudi arabia' => 'sa',
        'senegal' => 'sn', 'serbia' => 'rs', 'sierra leone' => 'sl', 'singapore' => 'sg', 'slovakia' => 'sk',
        'slovenia' => 'si', 'solomon islands' => 'sb', 'somalia' => 'so', 'south africa' => 'za', 'south korea' => 'kr',
        'soviet union' => 'su', 'spain' => 'es', 'sri lanka' => 'lk', 'sudan' => 'sd', 'suriname' => 'sr', 'sweden' => 'se',
        'switzerland' => 'ch', 'syria' => 'sy', 'syrian arab republic' => 'sy', 'taiwan' => 'tw', 'tajikistan' => 'tj',
        'tanzania' => 'tz', 'thailand' => 'th', 'togo' => 'tg', 'trinidad and tobago' => 'tt', 'tunisia' => 'tn',
        'turkey' => 'tr', 'turkmenistan' => 'tm', 'türkiye' => 'tr', 'uganda' => 'ug', 'uk' => 'gb',
        'ukraine' => 'ua', 'united arab emirates' => 'ae', 'united kingdom' => 'gb', 'united states' => 'us',
        'united states of america' => 'us', 'uruguay' => 'uy', 'usa' => 'us', 'ussr' => 'su', 'uzbekistan' => 'uz',
        'vatican' => 'va', 'venezuela' => 've', 'viet nam' => 'vn', 'vietnam' => 'vn', 'western sahara' => 'eh',
        'yemen' => 'ye', 'zambia' => 'zm', 'zimbabwe' => 'zw');
    $c = aio_cat_country(aio_card_text($c));
    $k = strtolower($c);
    return $c === '' ? '' : (isset($iso[$k]) ? $iso[$k] : '_' . $c);
}

// Genres of the server -> those of the card: the first letter capital (not in the list
// of genres of the shell: shown as they are), no repeats.
/**
 * @param array<mixed> $list
 * @return list<string>
 */
function aio_card_genres($list)
{
    $out = array();
    foreach (array_slice($list, 0, AIO_CARD_SCAN_MAX) as $g)
    {
        $g = aio_card_text($g);
        $g = mb_strtoupper(mb_substr($g, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($g, 1, mb_strlen($g, 'UTF-8'), 'UTF-8');
        if ($g !== '' && !in_array($g, $out, true))
            $out[] = $g;
        if (count($out) >= AIO_CARD_GENRES_MAX)
            break;
    }
    return $out;
}

// Countries of the server -> codes of the card (aio_card_country), no repeats.
/**
 * @param array<mixed> $list
 * @return list<string>
 */
function aio_card_countries($list)
{
    $out = array();
    foreach (array_slice($list, 0, AIO_CARD_SCAN_MAX) as $c)
    {
        $c = aio_card_country($c);
        if ($c !== '' && !in_array($c, $out, true))
            $out[] = $c;
        if (count($out) >= AIO_CARD_COUNTRIES_MAX)
            break;
    }
    return $out;
}

// People of the meta -> people of the card: actors of app_extras.cast (role: the
// character, imgPhoto), directors and writers of app_extras.directors, writers, then
// the names of director, writer not among them; AIO_CARD_ACTORS_MAX actors and
// AIO_CARD_CREW_MAX of each crew at most, in the order of the server. type: 0 actor, 1
// director, 2 writer. id: melange-tmdb-<id> or melange-tvdb-<id>, else melange-n-<md5 of
// the name>: unique, no / or : (it names the file of the photo in the cache of the
// shell). A person on the server of the Dune has another id: ENTER on one says the server
// failed.
/**
 * @param array<mixed> $meta
 * @return list<array<string, mixed>>
 */
function aio_card_people($meta)
{
    $extras = aio_arr($meta, 'app_extras');
    $n = AIO_CARD_SCAN_MAX;
    $src = array(0 => array_slice(aio_arr($extras, 'cast'), 0, $n),
        1 => array_merge(array_slice(aio_arr($extras, 'directors'), 0, $n), array_slice(aio_arr($meta, 'director'), 0, $n)),
        2 => array_merge(array_slice(aio_arr($extras, 'writers'), 0, $n), array_slice(aio_arr($meta, 'writer'), 0, $n)));
    $out = array();
    foreach ($src as $type => $list)
    {
        $names = array();
        $ids = array();
        foreach ($list as $p)
        {
            if (count($names) >= ($type === 0 ? AIO_CARD_ACTORS_MAX : AIO_CARD_CREW_MAX))
                break;
            $name = aio_card_text(is_array($p) ? aio_str($p, 'name') : $p);
            if ($name === '' || isset($names[$name]))
                continue;
            $id = 'melange-n-' . substr(md5($name), 0, 12);
            if (preg_match('/^[0-9]{1,10}$/D', aio_str($p, 'tmdbId')))
                $id = 'melange-tmdb-' . aio_str($p, 'tmdbId');
            else if (preg_match('/^[0-9]{1,10}$/D', aio_str($p, 'tvdbId')))
                $id = 'melange-tvdb-' . aio_str($p, 'tvdbId');
            if (isset($ids[$id]))
                continue;
            $names[$name] = true;
            $ids[$id] = true;
            $e = array('id' => $id, 'type' => $type, 'name' => $name);
            $role = $type === 0 ? aio_card_text(aio_str($p, 'character')) : '';
            if ($role !== '')
                $e['role'] = $role;
            $photo = aio_cat_img($p, 'photo');
            if ($photo !== '')
                $e['imgPhoto'] = $photo;
            $out[] = $e;
        }
    }
    return $out;
}

// app_extras.certifications of the meta -> contentRatings of the card: the rating of
// each country ("RU": "18+", "US": "R"), the shell takes that of the country of the
// Dune or works one out of the rest.
/**
 * @param array<mixed> $meta
 * @return array<string, string>
 */
function aio_card_ratings($meta)
{
    $out = array();
    foreach (array_slice(aio_arr(aio_arr($meta, 'app_extras'), 'certifications'), 0, AIO_CARD_SCAN_MAX, true) as $k => $v)
    {
        $v = aio_card_text($v);
        if (preg_match('/^[A-Z]{2}$/D', strval($k)) && $v !== '' && mb_strlen($v, 'UTF-8') <= AIO_CARD_RATING_LEN)
            $out[strval($k)] = $v;
        if (count($out) >= AIO_CARD_RATINGS_MAX)
            break;
    }
    return $out;
}

// The meta of the movie of $imdb on the catalog server, null on any failure (logged by
// aio_cat_get) or when it is of another title.
/**
 * @param string $imdb
 * @return array<mixed>|null
 */
function aio_card_meta($imdb)
{
    $g = aio_cat_get("meta/movie/$imdb.json", "meta $imdb", 'meta', AIO_CARD_META_TOTAL, true);
    $m = $g['list'];
    if ($g['err'] !== '' || (aio_str($m, 'imdb_id') !== $imdb && aio_str($m, 'id') !== $imdb))
    {
        if ($g['err'] === '')
            aio_log("card: meta $imdb of another title");
        return null;
    }
    return $m;
}

// The id of the local card of a tile: m-melange-<IMDb>, of Melange alone, no card of the user
// is touched; '' without a good IMDb.
/**
 * @param AioCatTile $tile
 * @return string
 */
function aio_card_local_id($tile)
{
    return preg_match('/^tt[0-9]{1,10}$/D', $tile['imdb_id']) ? 'm-melange-' . $tile['imdb_id'] : '';
}

// A tile with IMDb and no card of the server: the local card of the shell made from it
// before, opened as it is (the user may have edited it in the shell), whatever the server
// said. -> its id, '' for none or a broken one.
/**
 * @param AioCatTile $tile
 * @return string
 */
function aio_card_local_find($tile)
{
    $id = aio_card_local_id($tile);
    if ($id === '')
        return '';
    $f = AIO_USER_CARD_DIR . "/$id.json";
    $j = is_file($f) ? json_decode(strval(@file_get_contents($f)), true) : null;
    if (is_array($j) && isset($j['result']) && is_array($j['result']) && isset($j['result']['id']) &&
        $j['result']['id'] === $id)
    {
        aio_log("card: local $id kept");
        return $id;
    }
    return '';
}

// The way of 0.57.2 (goes with it): the local card of aio_card_local_find; none and $make (the
// server sure it has no card) for a movie: written from the tile, the fields do_parse_movie_json
// reads; genres, countries, the age and people from the meta of the catalog server
// (aio_card_meta), from the tile when it fails (no people then); Kinopoisk from the tile. Empty
// fields are left out. -> its id, '' if none. Uninstall leaves these files on purpose: they are
// the user's history, as the history of the shell is.
/**
 * @param AioCatTile $tile
 * @param bool $make
 * @return string
 */
function aio_card_local($tile, $make)
{
    $id = aio_card_local_find($tile);
    if ($id !== '' || !$make || $tile['type'] !== 'movie')
        return $id;
    // IMDb checked again where the file is written: a bad one makes no name.
    $id = aio_card_local_id($tile);
    if ($id === '')
        return '';
    $f = AIO_USER_CARD_DIR . "/$id.json";
    $now = time();
    $r = array('id' => $id, 'type' => 'movie', 'customType' => 'user',
        'title' => array('ru' => $tile['name'], 'en' => $tile['englishTitle'] !== '' ? $tile['englishTitle'] : $tile['name']),
        'description' => array('ru' => $tile['description'], 'en' => $tile['description']),
        'imgPoster' => $tile['poster'], 'imgThumbnail' => $tile['poster'],
        'imgBackdrops' => $tile['background'] !== '' ? array($tile['background']) : array(),
        'sources' => array('IMDB' => $tile['imdb_id']),
        'cdesc' => array('src' => 'manual', 'ctime' => $now, 'mtime' => $now));
    if ($tile['_tmdbId'] !== '')
        $r['sources']['TheMovieDB'] = $tile['_tmdbId'];
    // Kinopoisk: of the tile alone, the meta has none; the shell does not show the
    // rating, it goes to suppliers in movie_str, as the id.
    if ($tile['kpId'] !== '')
        $r['sources']['Kinopoisk'] = $tile['kpId'];
    if ($tile['year'] !== '')
        $r['year'] = intval($tile['year']);
    if ($tile['imdbRating'] !== '')
        $r['rate_imdb'] = $tile['imdbRating'];
    if ($tile['kpRating'] !== '')
        $r['rating'] = array('kp' => array('rating' => $tile['kpRating']));
    // duration of the card in minutes.
    $min = aio_cat_runtime_min($tile['runtime']);
    if ($min > 0)
        $r['duration'] = $min;
    // No folder: the card is not written, nothing to ask for.
    $meta = is_dir(AIO_USER_CARD_DIR) ? aio_card_meta($tile['imdb_id']) : null;
    $genres = aio_card_genres($meta ? aio_arr($meta, 'genres') : $tile['genres']);
    if (!$genres && $meta)
        $genres = aio_card_genres($tile['genres']);
    $countries = array();
    if ($meta)
    {
        $list = aio_arr($meta, 'countries');
        if (!$list)
        {
            // The rest of a long country in the last item is dropped.
            $list = explode(',', aio_str($meta, 'country'), AIO_CARD_COUNTRIES_MAX + 1);
            array_splice($list, AIO_CARD_COUNTRIES_MAX);
        }
        $countries = aio_card_countries($list);
    }
    if (!$countries)
        $countries = aio_card_countries(array($tile['country']));
    $ratings = $meta ? aio_card_ratings($meta) : array();
    $age = isset($ratings['RU']) && preg_match('/^[0-9]{1,2}\+$/D', $ratings['RU']) ? $ratings['RU'] : $tile['age'];
    $people = $meta ? aio_card_people($meta) : array();
    foreach (array('genres' => $genres, 'countries' => $countries, 'rate_content' => $age, 'contentRatings' => $ratings,
        'people' => $people) as $k => $v)
    {
        if ($v)
            $r[$k] = $v;
    }
    $data = json_encode(array('result' => $r));
    // Whole or nothing: a temp file in the folder, then rename. Its name starts with a
    // dot: the shell skips such files in the folder (fname_to_id).
    $tmp = AIO_USER_CARD_DIR . "/.$id.json." . getmypid();
    $ok = is_string($data) && is_dir(AIO_USER_CARD_DIR) && @file_put_contents($tmp, $data) === strlen($data) &&
        @rename($tmp, $f);
    if (!$ok && file_exists($tmp))
        @unlink($tmp);
    aio_log("card: local $id " . ($ok ? 'written' : 'not written'));
    return $ok ? $id : '';
}

// The way of play_action, the movie made from the tile: no id of a card, so no
// history (aio_play) and a series starts at S1E1. The list opens after a dialog that
// says so; what aio_play_action says instead of a list goes as it is.
/**
 * @param AioCatTile $tile
 * @param string $lang
 * @return AioAction
 */
function aio_card_releases($tile, $lang)
{
    $in = new stdClass();
    $in->movie_str = json_encode(array('imdb_id' => $tile['imdb_id'], 'title' => $tile['name'], 'year' => $tile['year'],
        'poster_url' => $tile['poster'], 'fanart_url' => $tile['background'], 'rate_imdb' => $tile['imdbRating'],
        'description' => $tile['description']));
    $in->series_type = $tile['type'] === 'series' ? 'series' : 'single';
    $in->lang = $lang;
    $act = aio_play_action($in);
    if ($act[GuiAction::handler_string_id] !== PLUGIN_OPEN_FOLDER_ACTION_ID)
        return $act;
    return aio_dialog(aio_tr($lang, 'card_none_title'), array(aio_tr($lang, 'card_no_history')),
        array(aio_tr($lang, 'card_show_list') => aio_close_and($act)));
}

// The action of ENTER on $tile (aio_cat_tile). $lang: ru or another, as of play_action. A movie
// or a series with an id of the meta (aio_cm_id: IMDb or TMDB): the own card of Melange
// (view_card.php), no local card written; else as before.
/**
 * @param AioCatTile $tile
 * @param string $lang
 * @return AioAction
 */
function aio_card_enter($tile, $lang)
{
    if (aio_cm_id($tile) !== '')
        return aio_oc_enter($tile, $lang);
    $c = aio_card_id($tile);
    $id = $c['id'] !== '' ? $c['id'] : aio_card_local($tile, $c['none']);
    if ($id !== '')
        return aio_card_open($id);
    return $tile['imdb_id'] !== '' ? aio_card_releases($tile, $lang) : aio_error('err_card_no_source');
}
