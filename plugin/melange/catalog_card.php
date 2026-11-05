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

// A tile with IMDb and no card of the server: the local card of the shell made from
// it before, opened as it is (the user may have edited it in the shell), whatever the
// server said. None or a broken one, and $make (the server sure it has no card) for a
// movie: written from the tile, the fields do_parse_movie_json reads. -> its id, ''
// if none. The id is of Melange alone (m-melange-), no card of the user is touched.
// Uninstall leaves these files on purpose: they are the user's history, as the
// history of the shell is.
/**
 * @param AioCatTile $tile
 * @param bool $make
 * @return string
 */
function aio_card_local($tile, $make)
{
    if (!preg_match('/^tt[0-9]{1,10}$/D', $tile['imdb_id']))
        return '';
    $id = 'm-melange-' . $tile['imdb_id'];
    $f = AIO_USER_CARD_DIR . "/$id.json";
    $j = is_file($f) ? json_decode(strval(@file_get_contents($f)), true) : null;
    if (is_array($j) && isset($j['result']) && is_array($j['result']) && isset($j['result']['id']) &&
        $j['result']['id'] === $id)
    {
        aio_log("card: local $id kept");
        return $id;
    }
    if (!$make || $tile['type'] !== 'movie')
        return '';
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
    if ($tile['year'] !== '')
        $r['year'] = intval($tile['year']);
    if ($tile['imdbRating'] !== '')
        $r['rate_imdb'] = $tile['imdbRating'];
    // duration of the card in minutes.
    $min = aio_cat_runtime_min($tile['runtime']);
    if ($min > 0)
        $r['duration'] = $min;
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

// The action of ENTER on $tile (aio_cat_tile). $lang: ru or another, as of play_action.
/**
 * @param AioCatTile $tile
 * @param string $lang
 * @return AioAction
 */
function aio_card_enter($tile, $lang)
{
    $c = aio_card_id($tile);
    $id = $c['id'] !== '' ? $c['id'] : aio_card_local($tile, $c['none']);
    if ($id !== '')
        return aio_card_open($id);
    return $tile['imdb_id'] !== '' ? aio_card_releases($tile, $lang) : aio_error('err_card_no_source');
}
