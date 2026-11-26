<?php
// ENTER on a tile of the catalog: the own card (view_card.php) of a tile with an id of the meta; of another one the
// native card of the Dune, its id found by movieBySigns, else a local card of the shell made before (up to 0.69.1, with
// the own card off or of r22; none is written now: the history of users is on them, mel_card_local_find), else the
// releases of Melange, no history kept; without an IMDb id either a message. Needs catalog.php (its cache) and main.php.
// dune_auth is only in the address of a request: no log, dialog or file gets that address.
// Types in the PHPDoc (MelCatTile, MelAction): phpstan-types.neon of the repository.

define('MEL_CARD_API', 'http://dunemdb.dune-hd.com:5535/');
// Timeouts of a request, s: from the Dune it takes 0.2 s, 0.5 s at most. Two
// requests stay under 6 s: after a transport failure the second is not asked.
define('MEL_CARD_CONNECT', 2);
define('MEL_CARD_TOTAL', 3);
// A card this many years off the tile is taken: 1 is the most a right card was seen
// off (Buratino 2025, its tile 2026), 6 and 12 were wrong ones (Mowgli 1967, Phantom Power 2026).
define('MEL_CARD_YEAR_DIFF', 1);
// A week: duplicates and suppliers of a title change slowly, and one more look costs 0.5 s.
define('MEL_CARD_TTL_ID', 604800);
// A day: new cards appear in days, none seen younger than a day (03.11.2026).
define('MEL_CARD_TTL_NONE', 86400);
// The meta of a movie asked of the catalog server by the own card (card_meta.php). 0.4 s seen (05.11.2026), "none" 1.8 s. ENTER waits for it;
// a failure leaves the card of the tile.
define('MEL_CARD_META_TOTAL', 4);
// show_dialog_delay of ENTER on a tile (it opens the own card), ms: the card waits for the network (the meta of a series
// takes over a second cold), "Please wait" is due soon.
define('MEL_CARD_DELAY', 1000);
// Limits of the people and the countries of the meta (card_meta.php): the server gives up to 10 actors. Of a list of
// the meta only the first MEL_CARD_SCAN_MAX items are looked at.
define('MEL_CARD_ACTORS_MAX', 10);
define('MEL_CARD_CREW_MAX', 5);
define('MEL_CARD_COUNTRIES_MAX', 10);
define('MEL_CARD_TEXT_MAX', 60);
define('MEL_CARD_SCAN_MAX', 200);
// Local cards of the shell (movies/custom_dunemdb.php): PHP of the Dune adds
// FS_PREFIX to /flashdata itself. Tests may define it first.
if (!defined('MEL_USER_CARD_DIR'))
    define('MEL_USER_CARD_DIR', '/flashdata/custom_mdb/user_movie');

// The content of dune_auth.txt: "dune_auth=<value>", '' if there is none.
/**
 * @return string
 */
function mel_card_token()
{
    $f = getenv('FS_PREFIX') . '/tmp/dune_auth.txt';
    return is_file($f) ? trim(strval(file_get_contents($f))) : '';
}

// As the shell: the prefix of the API from its file, if another plugin has set it.
/**
 * @return string
 */
function mel_card_api()
{
    $f = getenv('FS_PREFIX') . '/tmp/run/shell_ext_dunemdb_api_url.txt';
    $p = is_file($f) ? trim(strval(file_get_contents($f))) : '';
    if (!preg_match('~^https?://[^/?#\s]~', $p))
        return MEL_CARD_API;
    return substr($p, -1) === '/' ? $p : "$p/";
}

// One sign -> st: card, none (not found), refused (no dune_auth accepted), net
// (transport), bad (anything else); card: id, year (0 if none), type, sup (suppliers),
// kp (its rating of Kinopoisk as mel_cat_rate: rating.kp of the short answer, often none)
// or null. "Not found" and the refusal both come with code 0, only message tells.
// lang: no text of the card is used, and ru is what the choice was measured on.
/**
 * @param string $sign
 * @param string $token
 * @return array{st: string, card: array{id: string, year: int, type: string, sup: int, kp: string}|null}
 */
function mel_card_ask($sign, $token)
{
    $t = microtime(true);
    $err = '';
    list($code, $body) = mel_http_get(mel_card_api() . "movieBySigns/$sign?movieAnswerType=short&lang=ru&$token", $err,
        MEL_CARD_CONNECT, MEL_CARD_TOTAL, MEL_CAT_MAX_BYTES);
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
        $msg = strtolower(mel_str($j, 'message'));
        $id = mel_str($r, 'id');
        $why = '';
        if (intval(mel_str($j, 'code')) === 100 && preg_match('/^[0-9a-f]{24}$/D', $id))
        {
            $st = 'card';
            $kp = mel_arr(mel_arr($r, 'rating'), 'kp');
            $card = array('id' => $id, 'year' => intval(mel_str($r, 'year')), 'type' => mel_str($r, 'type'),
                'sup' => isset($r['suppliers']) && is_array($r['suppliers']) ? count($r['suppliers']) : 0,
                'kp' => mel_cat_rate(mel_str($kp, 'rating')));
            $why = "$id year={$card['year']} type={$card['type']} sup={$card['sup']}";
        }
        else if ($msg === 'not found')
            $st = 'none';
        else if (strpos($msg, 'auth') !== false)
            $st = 'refused';
    }
    mel_log(sprintf('card: %s: %s %s, %.0f ms', $sign, $st, $why, (microtime(true) - $t) * 1000));
    return array('st' => $st, 'card' => $card);
}

// The type of the tile, not of the card: a card can have a wrong one.
/**
 * @param array{id: string, year: int, type: string, sup: int, kp: string} $c
 * @param bool $series
 * @return array{int, int, int}
 */
function mel_card_rank($c, $series)
{
    return array($c['sup'], $c['year'] > 0 ? 1 : 0, ($c['type'] === 'series') === $series ? 1 : 0);
}

// Off the year of the tile by more than MEL_CARD_YEAR_DIFF: not this title. A card
// with year 0 stays (the only card of a title can be such), a tile without a year
// checks nothing. Then more suppliers, a non-zero year, the type of the tile; a
// tie goes to tmdb, the more reliable sign. -> the card or null.
/**
 * @param array{id: string, year: int, type: string, sup: int, kp: string}|null $tmdb
 * @param array{id: string, year: int, type: string, sup: int, kp: string}|null $imdb
 * @param MelCatTile $tile
 * @return array{id: string, year: int, type: string, sup: int, kp: string}|null
 */
function mel_card_pick($tmdb, $imdb, $tile)
{
    $series = $tile['type'] === 'series';
    $best = null;
    foreach (array($tmdb, $imdb) as $c)
    {
        if (!$c)
            continue;
        if ($c['year'] > 0 && $tile['year'] !== '' && abs($c['year'] - intval($tile['year'])) > MEL_CARD_YEAR_DIFF)
            mel_log("card: {$c['id']} year {$c['year']}, tile {$tile['year']}: not taken");
        else if (!$best || mel_card_rank($c, $series) > mel_card_rank($best, $series))
            $best = $c;
    }
    return $best;
}

// The card of the tile: id, '' for none; none: the server answered every sign and
// no card fits (now or cached), not a failure, a refusal, no dune_auth or no sign.
// Only full answers go to the cache: a failure says nothing about the card, and a
// card without the answer of the other sign may not be the one to keep. With a
// refused or failed request the second is not asked. Without dune_auth nothing is
// asked: the shell can not open a card either. kp: the rating of Kinopoisk of the card
// ('' for none), kept with the id in the cache; null when not known (a cache of before it, no sign,
// no dune_auth). A card found goes to the pairs of the badges (mel_pg_link_tile).
/**
 * @param MelCatTile $tile
 * @return array{id: string, none: bool, kp: string|null}
 */
function mel_card_id($tile)
{
    $token = mel_card_token();
    if ($token === '')
    {
        mel_log('card: no dune_auth');
        return array('id' => '', 'none' => false, 'kp' => null);
    }
    $signs = array();
    // A TMDB id is of a movie or of a series: without the type it means nothing.
    if ($tile['_tmdbId'] !== '' && $tile['type'] !== '')
        $signs['tmdb'] = ($tile['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $tile['_tmdbId'];
    if ($tile['imdb_id'] !== '')
        $signs['imdb'] = 'imdb:' . $tile['imdb_id'];
    if (!$signs)
        return array('id' => '', 'none' => false, 'kp' => null);
    $key = "card|{$tile['type']}|{$tile['_tmdbId']}|{$tile['imdb_id']}|{$tile['year']}";
    $c = mel_cat_cache_read($key);
    if ($c)
    {
        $id = mel_str($c['data'], 'id');
        // A saved time ahead of the clock (a clock not set after a reboot) is not trusted.
        if (time() >= $c['t'] && time() - $c['t'] < ($id !== '' ? MEL_CARD_TTL_ID : MEL_CARD_TTL_NONE))
        {
            mel_log('card: cached ' . ($id !== '' ? $id : 'none'));
            mel_pg_link_tile($id, $tile);
            return array('id' => $id, 'none' => $id === '',
                'kp' => isset($c['data']['kp']) && is_string($c['data']['kp']) ? $c['data']['kp'] : null);
        }
    }
    $cards = array('tmdb' => null, 'imdb' => null);
    $whole = true;
    foreach ($signs as $by => $sign)
    {
        $a = mel_card_ask($sign, $token);
        $cards[$by] = $a['card'];
        if ($a['st'] !== 'card' && $a['st'] !== 'none')
            $whole = false;
        if ($a['st'] === 'net' || $a['st'] === 'refused')
            break;
    }
    $card = mel_card_pick($cards['tmdb'], $cards['imdb'], $tile);
    $id = $card ? $card['id'] : '';
    $kp = $card ? $card['kp'] : '';
    if ($whole)
        mel_cat_cache_write($key, 1, array('id' => $id, 'kp' => $kp));
    mel_pg_link_tile($id, $tile);
    return array('id' => $id, 'none' => $whole && $id === '', 'kp' => $kp);
}

// The answer of mel_card_id of card $id of the Dune known with no request (of the row "Continue", of movie_str of
// play_action, of a card open; '' - none): its rating of Kinopoisk not known (mel_oc_fill asks it of a card not in memory).
/**
 * @param string $id
 * @return array{id: string, none: bool, kp: string|null}
 */
function mel_card_id_known($id)
{
    return array('id' => $id, 'none' => false, 'kp' => null);
}

// The rating of Kinopoisk of card $id of the Dune of $tile, asked now by one sign (TMDB, else
// IMDb; mel_card_id got the id of its cache): '' for none, no answer or another card.
/**
 * @param MelCatTile $tile
 * @param string $id
 * @return string
 */
function mel_card_kp($tile, $id)
{
    $token = mel_card_token();
    if ($token === '' || $id === '')
        return '';
    if ($tile['_tmdbId'] !== '' && $tile['type'] !== '')
        $sign = ($tile['type'] === 'series' ? 'tmdbtv:' : 'tmdb:') . $tile['_tmdbId'];
    else if ($tile['imdb_id'] !== '')
        $sign = 'imdb:' . $tile['imdb_id'];
    else
        return '';
    $a = mel_card_ask($sign, $token);
    return $a['card'] && $a['card']['id'] === $id ? $a['card']['kp'] : '';
}

// The handler of file_movie in r25 reads movie_id only: the card takes its type
// from the server.
/**
 * @param string $id
 * @return MelAction
 */
function mel_card_open($id)
{
    return array(
        GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
        GuiAction::caption => null,
        GuiAction::plugin_name => 'shell_ext',
        GuiAction::data => null,
        GuiAction::params => array('handler_id' => 'entry', 'control_id' => 'file_movie', 'movie_id' => $id));
}

// Text of the meta for the own card: one line, cut, no < and >.
/**
 * @param mixed $s
 * @return string
 */
function mel_card_text($s)
{
    return trim(str_replace(array('<', '>'), '', mel_cut(mel_one_line(mel_str(array($s), 0)), MEL_CARD_TEXT_MAX)));
}

// People of the meta -> people of the card: actors of app_extras.cast (role: the
// character, imgPhoto), directors and writers of app_extras.directors, writers, then
// the names of director, writer not among them; $actors actors (the own card: more) and
// MEL_CARD_CREW_MAX of each crew at most, in the order of the server. type: 0 actor, 1
// director, 2 writer. id: melange-tmdb-<id> or melange-tvdb-<id>, else melange-n-<md5 of
// the name>: unique, no / or : (it names the file of the photo in the cache of the
// shell). A person on the server of the Dune has another id: ENTER on one says the server
// failed. card_meta.php gives creators here too, as app_extras.cast.
/**
 * @param array<mixed> $meta
 * @param int $actors
 * @return list<array<string, mixed>>
 */
function mel_card_people($meta, $actors = MEL_CARD_ACTORS_MAX)
{
    $extras = mel_arr($meta, 'app_extras');
    $n = MEL_CARD_SCAN_MAX;
    $src = array(0 => array_slice(mel_arr($extras, 'cast'), 0, $n),
        1 => array_merge(array_slice(mel_arr($extras, 'directors'), 0, $n), array_slice(mel_arr($meta, 'director'), 0, $n)),
        2 => array_merge(array_slice(mel_arr($extras, 'writers'), 0, $n), array_slice(mel_arr($meta, 'writer'), 0, $n)));
    $out = array();
    foreach ($src as $type => $list)
    {
        $names = array();
        $ids = array();
        foreach ($list as $p)
        {
            if (count($names) >= ($type === 0 ? $actors : MEL_CARD_CREW_MAX))
                break;
            $name = mel_card_text(is_array($p) ? mel_str($p, 'name') : $p);
            if ($name === '' || isset($names[$name]))
                continue;
            $id = 'melange-n-' . substr(md5($name), 0, 12);
            if (preg_match('/^[0-9]{1,10}$/D', mel_str($p, 'tmdbId')))
                $id = 'melange-tmdb-' . mel_str($p, 'tmdbId');
            else if (preg_match('/^[0-9]{1,10}$/D', mel_str($p, 'tvdbId')))
                $id = 'melange-tvdb-' . mel_str($p, 'tvdbId');
            if (isset($ids[$id]))
                continue;
            $names[$name] = true;
            $ids[$id] = true;
            $e = array('id' => $id, 'type' => $type, 'name' => $name);
            $role = $type === 0 ? mel_card_text(mel_str($p, 'character')) : '';
            if ($role !== '')
                $e['role'] = $role;
            $photo = mel_cat_img($p, 'photo');
            if ($photo !== '')
                $e['imgPhoto'] = $photo;
            $out[] = $e;
        }
    }
    return $out;
}

// The id of the local card of a tile: m-melange-<IMDb>, of Melange alone, no card of the user
// is touched; '' without a good IMDb.
/**
 * @param MelCatTile $tile
 * @return string
 */
function mel_card_local_id($tile)
{
    return preg_match('/^tt[0-9]{1,10}$/D', $tile['imdb_id']) ? 'm-melange-' . $tile['imdb_id'] : '';
}

// The IMDb of the local card $mid (mel_card_local_id: of a movie alone, no card of the Dune, no pair ever: the dunemdb
// API has no such card), '' for another card.
/**
 * @param string $mid
 * @return string
 */
function mel_card_local_imdb($mid)
{
    return preg_match('/^m-melange-(tt[0-9]{1,10})$/D', $mid, $m) ? $m[1] : '';
}

// A tile with IMDb and no card of the server: the local card of the shell made from it
// before, opened as it is (the user may have edited it in the shell), whatever the server
// said. -> its id, '' for none or a broken one.
/**
 * @param MelCatTile $tile
 * @return string
 */
function mel_card_local_find($tile)
{
    $id = mel_card_local_id($tile);
    if ($id === '')
        return '';
    $f = MEL_USER_CARD_DIR . "/$id.json";
    $j = is_file($f) ? json_decode(strval(@file_get_contents($f)), true) : null;
    if (is_array($j) && isset($j['result']) && is_array($j['result']) && isset($j['result']['id']) &&
        $j['result']['id'] === $id)
    {
        mel_log("card: local $id kept");
        return $id;
    }
    return '';
}

// The way of play_action of a tile of no type with IMDb (mel_card_enter: a movie or a series has its own card), the
// movie made from the tile (its releases by the IMDb, mel_rel_id): no id of a card, so no history (mel_play). The list
// opens after a dialog that says so; what mel_play_action says instead of a list goes as it is.
/**
 * @param MelCatTile $tile
 * @param string $lang
 * @return MelAction
 */
function mel_card_releases($tile, $lang)
{
    $in = new stdClass();
    $mv = array('imdb_id' => $tile['imdb_id'], 'title' => $tile['name'], 'year' => $tile['year'],
        'poster_url' => $tile['poster'], 'fanart_url' => $tile['background'], 'rate_imdb' => $tile['imdbRating'],
        'description' => $tile['description']) + mel_lpv_tile_ids($tile);
    $in->movie_str = json_encode($mv);
    $in->series_type = 'single';
    $in->lang = $lang;
    $act = mel_play_action($in);
    if ($act[GuiAction::handler_string_id] !== PLUGIN_OPEN_FOLDER_ACTION_ID)
        return $act;
    return mel_dialog(mel_tr($lang, 'card_none_title'), array(mel_tr($lang, 'card_no_history')),
        array(mel_tr($lang, 'card_show_list') => mel_close_and($act)));
}

// The action of ENTER on $tile (mel_cat_tile). $lang: ru or another, as of play_action. A movie or a series with an
// id of the meta (mel_cm_id: IMDb or TMDB): the own card of Melange (view_card.php), no local card written (0.70.0: on
// every firmware, no setting); else (a tile of no type, of no IMDb and no TMDB) the way of 0.58.0. The card of the Dune it
// opens gives no original language in its movie_str: that of the tile is kept for its search of Lampac.
/**
 * @param MelCatTile $tile
 * @param string $lang
 * @return MelAction
 */
function mel_card_enter($tile, $lang)
{
    if (mel_cm_id($tile) !== '')
        return mel_oc_enter($tile, $lang);
    $c = mel_card_id($tile);
    $id = $c['id'] !== '' ? $c['id'] : mel_card_local_find($tile);
    if ($id !== '')
    {
        mel_lpv_ids_put($id, array('tmdb' => '', 'kp' => '', 'original_language' => $tile['originalLanguage']));
        return mel_card_open($id);
    }
    // The releases by its IMDb; no IMDb (a tile of no type: its TMDB may be of either; of no TMDB either) - none.
    return $tile['imdb_id'] !== '' ? mel_card_releases($tile, $lang) : mel_error('err_card_no_source');
}
