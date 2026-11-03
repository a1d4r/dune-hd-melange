<?php
// Client of the catalog: rows of the shared AIOMetadata instance (Stremio addon
// protocol), its search, a cache on the device and the last good copy on a
// failure. Data only, no screens. Needs parse.php and main.php (aio_http_get,
// aio_log, aio_write_file, aio_data_dir), on the settings page cgi/lib/rows.php.
// Types in the PHPDoc (AioCatRow and others): phpstan-types.neon of the repository.

define('AIO_CAT_BASE', 'https://aiometadata.kompotik.com/stremio/');
// The shared config of the instance: public, as the apikey of the own JacRed, and
// read-only without its password. CI of this repository
// allows this one value.
define('AIO_CAT_UUID', 'e3778c33-0d0e-4d2e-a4db-a817b2d506f4');

// Timeouts of a request, s: a cold row of the server takes up to 4.5 s.
define('AIO_CAT_CONNECT', 5);
define('AIO_CAT_TOTAL', 15);
// A bigger reply (decoded) is cut off while it loads: 3 times the largest seen
// (344 KB). Over this many objects and lists it is not decoded: 1 MB of "{}," takes
// more than the 128 MB of PHP in json_decode, a Fatal; a real page of 50 tiles has
// ~1600 of them, its links aside.
define('AIO_CAT_MAX_BYTES', 1048576);
define('AIO_CAT_MAX_NODES', 40000);
// Tiles kept of a page, the rest only counts for skip: the server sends up to 50.
define('AIO_CAT_PAGE_TILES', 200);

// Listing of a row. skip of 1000 and more is a 404 of the server, so the row ends
// there. The state lives 5 h: the cursor of the server lives 6 h, and a lost one
// silently loses tiles.
define('AIO_CAT_SKIP_MAX', 1000);
define('AIO_CAT_STATE_TTL', 18000);

// Cache in data_dir/catalog: a failure right after a reboot has it too, tmp_dir
// (RAM) would not. Fresh for 1 h: the manifest and first pages are not asked
// again (the server keeps rows for a day). Kept for 30 days as the last good
// copy: an older row is of little use, and TMDB allows 6 months at most. 8 MB,
// 400 files: ~80 pages of 100 KB (json_encode makes Cyrillic 6 bytes a letter),
// the oldest go first.
// Raise the version when what a copy holds changes (the keys of a tile): older copies are dropped.
define('AIO_CAT_CACHE_V', 2);
define('AIO_CAT_FRESH', 3600);
define('AIO_CAT_KEEP', 2592000);
define('AIO_CAT_CACHE_BYTES', 8388608);
define('AIO_CAT_CACHE_FILES', 400);
define('AIO_CAT_MANIFEST_KEY', 'manifest');

define('AIO_CAT_NAME_MAX', 200);
define('AIO_CAT_DESC_MAX', 1200);
define('AIO_CAT_URL_MAX', 1000);
define('AIO_CAT_GENRES_MAX', 8);
define('AIO_CAT_CAST_MAX', 3);
define('AIO_CAT_QUERY_MAX', 100);

// --- Requests.

// The only place that adds the UUID to an address: no log or cache gets this URL.
/**
 * @param string $path
 * @return string
 */
function aio_cat_url($path)
{
    return AIO_CAT_BASE . AIO_CAT_UUID . '/' . $path;
}

// One GET -> array(err: '' or why it failed, list: the JSON list in $field).
// err: net (transport), timeout (curl 28), big (over the limit), rate (429), server (5xx),
// notfound (404), http (another status), bad (not the JSON expected). A failure
// is never an empty list. $what: for the log, no URL and no reply there. $limit: s
// the request may take at most, 0 for the usual timeouts.
/**
 * @param string $path
 * @param string $what
 * @param string $field
 * @param int $limit
 * @return array{err: string, list: array<mixed>}
 */
function aio_cat_get($path, $what, $field, $limit = 0)
{
    $t = microtime(true);
    $err = '';
    $total = $limit > 0 ? min($limit, AIO_CAT_TOTAL) : AIO_CAT_TOTAL;
    list($code, $body) = aio_http_get(aio_cat_url($path), $err, min(AIO_CAT_CONNECT, $total), $total, AIO_CAT_MAX_BYTES);
    $size = strlen($body);
    $status = $err !== '' ? $err : "HTTP $code";
    $why = '';
    $list = array();
    if ($err === AIO_HTTP_TOO_BIG)
        $why = 'big';
    else if (strpos($err, 'curl 28:') === 0)
        $why = 'timeout';
    else if ($err !== '')
        $why = 'net';
    else if ($code === 429)
        $why = 'rate';
    else if ($code === 404)
        $why = 'notfound';
    else if ($code >= 500)
        $why = 'server';
    else if ($code !== 200)
        $why = 'http';
    else
    {
        $j = substr_count($body, '{') + substr_count($body, '[') <= AIO_CAT_MAX_NODES ? json_decode($body, true) : null;
        // An array in PHP may be an object in JSON: only a list goes on.
        if (is_array($j) && isset($j[$field]) && is_array($j[$field]) && (!$j[$field] || isset($j[$field][0]) || array_key_exists(0, $j[$field])))
            $list = $j[$field];
        else
            $why = 'bad';
    }
    unset($body, $j);
    aio_log(sprintf('catalog: %s: %s, %d bytes, %d items, %.0f ms%s', $what, $status, $size, count($list),
        (microtime(true) - $t) * 1000, $why !== '' ? ", failed: $why" : ''));
    return array('err' => $why, 'list' => $list);
}

// --- Rows.

// The group of a row, by its id: the manifest has no such field. 'other' for the
// rest; a group new to the plugin comes as it is, its name on the screen is not here.
/**
 * @param string $id
 * @return string
 */
function aio_cat_group($id)
{
    if (preg_match('/^tmdb\.discover\.[^.]+\.([^.]+)\./', $id, $m))
        return $m[1];
    if (strpos($id, 'anilist.') === 0)
        return 'anime';
    return $id === 'tmdb.trending' || $id === 'tmdb.top_rated' || strpos($id, 'letterboxd.') === 0 ? 'top' : 'other';
}

// A row of the screen, or null when it can not be asked for. key: type and id
// (names repeat between the types, an id too), name for the screen, genre: the
// value to send, '' for none, home: a start row, group: aio_cat_group.
/**
 * @param mixed $type
 * @param mixed $id
 * @param string $name
 * @param string $genre
 * @param bool $home
 * @return AioCatRow|null
 */
function aio_cat_row($type, $id, $name, $genre, $home)
{
    static $types = array('movie' => true, 'series' => true, 'anime' => true);
    if (!is_string($type) || !isset($types[$type]) || !is_string($id) || !preg_match('/^[\x21-\x7e]{1,200}$/', $id))
        return null;
    return array('key' => "$type/$id", 'type' => $type, 'id' => $id,
        'name' => aio_cut(aio_one_line($name !== '' ? $name : $id), AIO_CAT_NAME_MAX), 'genre' => $genre,
        'home' => $home, 'group' => aio_cat_group($id));
}

// The only place that chooses rows from the catalogs of the manifest, in its order
// (the order of the screen): rows of a known type without a required extra but
// genre. That leaves out search, calendar-videos and aiom.similar by what they are,
// not by a list of ids. A required genre goes as its first option, an optional one
// not at all ("default" of the manifest is a trap: it narrows a row to one genre).
// A start row: showInHome strictly true (service rows have no such field).
/**
 * @param array<mixed> $catalogs
 * @return list<AioCatRow>
 */
function aio_cat_pick_rows($catalogs)
{
    $rows = array();
    foreach ($catalogs as $c)
    {
        if (!is_array($c))
            continue;
        $req = array();
        foreach (aio_arr($c, 'extraRequired') as $name)
            $req[aio_str(array($name), 0)] = true;
        $genre = '';
        foreach (aio_arr($c, 'extra') as $x)
        {
            $name = aio_str($x, 'name');
            if (is_array($x) && isset($x['isRequired']) && $x['isRequired'] === true)
                $req[$name] = true;
            if ($name === 'genre')
            {
                $opts = aio_arr($x, 'options');
                $genre = aio_str($opts, 0);
            }
        }
        if (array_diff(array_keys($req), array('genre')))
            continue;
        $row = aio_cat_row(isset($c['type']) ? $c['type'] : null, isset($c['id']) ? $c['id'] : null,
            aio_str($c, 'name'), isset($req['genre']) ? $genre : '', isset($c['showInHome']) && $c['showInHome'] === true);
        if ($row && !isset($rows[$row['key']]))
            $rows[$row['key']] = $row;
    }
    return array_values($rows);
}

// The saved rows of any age within AIO_CAT_KEEP, no request -> rows, t: when saved
// (0: no copy).
/**
 * @return array{rows: list<AioCatRow>, t: int}
 */
function aio_cat_manifest_copy()
{
    $c = aio_cat_cache_read(AIO_CAT_MANIFEST_KEY);
    $rows = array();
    foreach ($c ? $c['data'] : array() as $r)
    {
        $row = aio_cat_row(isset($r['type']) ? $r['type'] : null, isset($r['id']) ? $r['id'] : null,
            aio_str($r, 'name'), aio_str($r, 'genre'), isset($r['home']) && $r['home'] === true);
        if ($row)
            $rows[] = $row;
    }
    return array('rows' => $rows, 't' => $c ? $c['t'] : 0);
}

// The rows from the server, saved as the copy -> rows, err ('' none; aio_cat_get).
// $limit: as of aio_cat_get.
/**
 * @param int $limit
 * @return array{rows: list<AioCatRow>, err: string}
 */
function aio_cat_manifest_fetch($limit = 0)
{
    $g = aio_cat_get('manifest.json', 'manifest', 'catalogs', $limit);
    $new = aio_cat_pick_rows($g['list']);
    if ($new)
    {
        aio_cat_cache_write(AIO_CAT_MANIFEST_KEY, count($new), $new);
        return array('rows' => $new, 'err' => '');
    }
    // A manifest without rows is broken too.
    return array('rows' => array(), 'err' => $g['err'] !== '' ? $g['err'] : 'bad');
}

// Rows of the catalog: from the cache for AIO_CAT_FRESH, else from the server; on
// a failure the last good copy with stale set, err tells why ('' none; aio_cat_get).
// No copy either: no rows and err.
/**
 * @return AioCatRows
 */
function aio_cat_manifest()
{
    $c = aio_cat_manifest_copy();
    if ($c['rows'] && aio_cat_is_fresh($c))
        return array('rows' => $c['rows'], 'stale' => false, 'err' => '');
    $new = aio_cat_manifest_fetch();
    if ($new['rows'])
        return array('rows' => $new['rows'], 'stale' => false, 'err' => '');
    return array('rows' => $c['rows'], 'stale' => (bool) $c['rows'], 'err' => $new['err']);
}

// The rows for the screen by the choice of the user (Aio::$settings['cat_rows']).
// Not a list: the start rows, in the order of the manifest. Else the chosen ones
// in the order of the user; a key not in the manifest goes, a new row does not
// come by itself. Nothing left: the start rows, never an empty screen.
/**
 * @param list<AioCatRow> $rows
 * @param mixed $chosen
 * @return list<AioCatRow>
 */
function aio_cat_user_rows($rows, $chosen)
{
    $out = array();
    if (is_array($chosen))
    {
        $by = array();
        foreach ($rows as $r)
            $by[$r['key']] = $r;
        foreach ($chosen as $k)
        {
            if (is_string($k) && isset($by[$k]))
            {
                $out[] = $by[$k];
                unset($by[$k]);
            }
        }
    }
    if ($out)
        return $out;
    foreach ($rows as $r)
    {
        if ($r['home'])
            $out[] = $r;
    }
    return $out;
}

// --- Tiles.

// http(s) address of a picture as the server gave it, else ''. The size in the
// address is up to the screen.
/**
 * @param mixed $m
 * @param string $k
 * @return string
 */
function aio_cat_img($m, $k)
{
    $u = aio_str($m, $k);
    return strlen($u) <= AIO_CAT_URL_MAX && preg_match('~^https?://\S+$~', $u) ? $u : '';
}

// The first country of the server ("Spain", "United States of America, Canada", ISO-3
// "jpn" of anime, any case) -> its English name; an unknown code: ''.
/**
 * @param string $s
 * @return string
 */
function aio_cat_country($s)
{
    static $iso = array('usa' => 'United States of America', 'gbr' => 'United Kingdom', 'fra' => 'France', 'deu' => 'Germany',
        'esp' => 'Spain', 'ita' => 'Italy', 'jpn' => 'Japan', 'kor' => 'South Korea', 'chn' => 'China', 'ind' => 'India',
        'can' => 'Canada', 'aus' => 'Australia', 'nzl' => 'New Zealand', 'rus' => 'Russia', 'bra' => 'Brazil', 'mex' => 'Mexico',
        'bel' => 'Belgium', 'nld' => 'Netherlands', 'swe' => 'Sweden', 'nor' => 'Norway', 'dnk' => 'Denmark', 'fin' => 'Finland',
        'irl' => 'Ireland', 'pol' => 'Poland', 'prt' => 'Portugal', 'aut' => 'Austria', 'che' => 'Switzerland', 'cze' => 'Czech Republic',
        'hun' => 'Hungary', 'grc' => 'Greece', 'tur' => 'Turkey', 'isr' => 'Israel', 'arg' => 'Argentina', 'hkg' => 'Hong Kong',
        'twn' => 'Taiwan', 'tha' => 'Thailand', 'idn' => 'Indonesia', 'phl' => 'Philippines', 'zaf' => 'South Africa',
        'isl' => 'Iceland', 'ukr' => 'Ukraine', 'kaz' => 'Kazakhstan');
    $first = explode(',', aio_one_line($s));
    $c = aio_cut(trim($first[0]), 60);
    if (!preg_match('/^[a-z]{3}$/i', $c))
        return $c;
    $k = strtolower($c);
    return isset($iso[$k]) ? $iso[$k] : '';
}

// A tile of the server -> only what is needed (links with the UUID, app_extras,
// logo and the rest are gone from here on), or null without an id or a name.
// All values strings, genres and cast lists. imdb_id and _tmdbId: also from the id itself,
// '' for none (mal:...). type: of the tile (rows of anime hold movie and series),
// else of the row, '' when neither says. year: 4 digits or ''. country: the English
// name of the first one (aio_cat_country), '' for none. runtime: "1h38min" or "30min" (of an
// episode for series). age: the Russian rating "16+" of app_extras.certifications.RU.
// cast: the first names of app_extras.cast. Run again on what the cache holds (age,
// cast: its own keys; a copy of before them: '' and none): the same result.
/**
 * @param mixed $m
 * @param string $row_type
 * @return AioCatTile|null
 */
function aio_cat_tile($m, $row_type)
{
    $id = aio_str($m, 'id');
    $name = aio_cut(aio_one_line(aio_str($m, 'name')), AIO_CAT_NAME_MAX);
    if ($name === '' || !preg_match('/^[\x21-\x7e]{1,100}$/', $id))
        return null;
    $imdb = aio_str($m, 'imdb_id');
    if (!preg_match('/^tt[0-9]{1,10}$/', $imdb))
        $imdb = preg_match('/^tt[0-9]{1,10}$/', $id) ? $id : '';
    $tmdb = aio_str($m, '_tmdbId');
    if (!preg_match('/^[0-9]{1,10}$/', $tmdb))
        $tmdb = preg_match('/^tmdb:([0-9]{1,10})$/', $id, $x) ? $x[1] : '';
    $type = aio_str($m, 'type');
    if ($type !== 'movie' && $type !== 'series')
        $type = $row_type === 'movie' || $row_type === 'series' ? $row_type : '';
    $year = '';
    if (preg_match('/^[0-9]{4}/', aio_str($m, 'year'), $x) || preg_match('/^[0-9]{4}/', aio_str($m, 'releaseInfo'), $x))
        $year = $x[0];
    $rating = aio_str($m, 'imdbRating');
    $genres = array();
    foreach (aio_arr($m, 'genres') as $g)
    {
        $g = aio_cut(aio_one_line(aio_str(array($g), 0)), 40);
        if ($g !== '' && count($genres) < AIO_CAT_GENRES_MAX)
            $genres[] = $g;
    }
    $country = aio_str($m, 'country');
    $country = aio_cat_country($country !== '' ? $country : aio_str(aio_arr($m, 'countries'), 0));
    $runtime = aio_str($m, 'runtime');
    $extras = aio_arr($m, 'app_extras');
    $age = aio_str(aio_arr($extras, 'certifications'), 'RU');
    if (!isset($extras['certifications']))
        $age = aio_str($m, 'age');
    $cast = array();
    foreach (isset($extras['cast']) ? aio_arr($extras, 'cast') : aio_arr($m, 'cast') as $c)
    {
        $c = aio_cut(aio_one_line(is_array($c) ? aio_str($c, 'name') : aio_str(array($c), 0)), 40);
        if ($c !== '' && count($cast) < AIO_CAT_CAST_MAX)
            $cast[] = $c;
    }
    return array('id' => $id, 'imdb_id' => $imdb, '_tmdbId' => $tmdb, 'type' => $type, 'name' => $name, 'year' => $year,
        'imdbRating' => preg_match('/^[0-9]{1,2}(\.[0-9]+)?$/', $rating) ? $rating : '',
        'description' => aio_cut(aio_one_line(aio_str($m, 'description')), AIO_CAT_DESC_MAX), 'genres' => $genres,
        'poster' => aio_cat_img($m, 'poster'), 'background' => aio_cat_img($m, 'background'), 'country' => $country,
        'runtime' => preg_match('/^(?=[0-9])([0-9]{1,2}h)?([0-9]{1,3}min)?$/', $runtime) ? $runtime : '',
        'age' => preg_match('/^[0-9]{1,2}\+$/', $age) ? $age : '', 'cast' => $cast);
}

// Items of a page -> the tiles among them; what is not a tile is dropped.
/**
 * @param array<mixed> $items
 * @param string $row_type
 * @return list<AioCatTile>
 */
function aio_cat_tiles($items, $row_type)
{
    $tiles = array();
    foreach (array_slice($items, 0, AIO_CAT_PAGE_TILES) as $m)
    {
        $t = aio_cat_tile($m, $row_type);
        if ($t)
            $tiles[] = $t;
    }
    return $tiles;
}

// --- Listing of a row.

// The address of a page of a row: its genre if any, skip if not 0; values encoded.
/**
 * @param AioCatRow $row
 * @param int $skip
 * @return string
 */
function aio_cat_page_path($row, $skip)
{
    $extra = array();
    if ($row['genre'] !== '')
        $extra[] = 'genre=' . rawurlencode($row['genre']);
    if ($skip > 0)
        $extra[] = sprintf('skip=%d', $skip);
    return 'catalog/' . rawurlencode($row['type']) . '/' . rawurlencode($row['id']) .
        ($extra ? '/' . implode('&', $extra) : '') . '.json';
}

// The state of a new listing of row $row (aio_cat_manifest), the argument of
// aio_cat_more. The caller keeps it where the tiles are and changes it only by the
// functions here: t0: when its first page was got (0: none yet), skip: all tiles got,
// repeats too, end: why it ended ('' not yet; empty, skip, search), seen: ids.
/**
 * @param AioCatRow $row
 * @return AioCatState
 */
function aio_cat_row_state($row)
{
    return array('row' => $row, 't0' => 0, 'skip' => 0, 'end' => '', 'seen' => array());
}

// A listing with nothing more to ask for: the results of a search.
/**
 * @param AioCatRow $row
 * @return AioCatState
 */
function aio_cat_ended_state($row)
{
    $st = aio_cat_row_state($row);
    $st['end'] = 'search';
    return $st;
}

// A listing from the saved first page of a row (aio_cat_page_cached), as if just got.
/**
 * @param AioCatRow $row
 * @param AioCatCached $c
 * @return array{st: AioCatState, tiles: list<AioCatTile>}
 */
function aio_cat_state_of_copy($row, $c)
{
    $st = aio_cat_row_state($row);
    $res = aio_cat_result();
    $st['t0'] = $c['t'];
    aio_cat_take($st, $res, $c['n'], $c['data']);
    return array('st' => $st, 'tiles' => $res['tiles']);
}

// $tiles the caller keeps after a reset: they do not come as new again.
/**
 * @param AioCatState $st
 * @param array<int|string, AioCatTile> $tiles
 * @param-out AioCatState $st
 * @return void
 */
function aio_cat_keep_seen(&$st, $tiles)
{
    foreach ($tiles as $t)
        $st['seen'][$t['id']] = true;
}

/**
 * @return AioCatResult
 */
function aio_cat_result()
{
    return array('tiles' => array(), 'reset' => false, 'end' => false, 'stale' => false, 'err' => '');
}

// A page taken into the listing: new tiles to $res, skip counts all $n got, end set
// at its limits. -> the number of new tiles.
/**
 * @param AioCatState $st
 * @param AioCatResult $res
 * @param int $n
 * @param list<AioCatTile> $tiles
 * @param-out AioCatState $st
 * @param-out AioCatResult $res
 * @return int
 */
function aio_cat_take(&$st, &$res, $n, $tiles)
{
    $new = 0;
    foreach ($tiles as $t)
    {
        if (isset($st['seen'][$t['id']]))
            continue;
        $st['seen'][$t['id']] = true;
        $res['tiles'][] = $t;
        $new++;
    }
    $st['skip'] += $n;
    if ($st['skip'] >= AIO_CAT_SKIP_MAX)
        $st['end'] = 'skip';
    $res['end'] = $st['end'] !== '';
    return $new;
}

// The cached copy of a page -> array(t, n, tiles) or null.
/**
 * @param AioCatRow $row
 * @param int $skip
 * @return AioCatCached|null
 */
function aio_cat_page_cached($row, $skip)
{
    $c = aio_cat_cache_read(aio_cat_page_key($row, $skip));
    if (!$c)
        return null;
    $c['data'] = aio_cat_tiles($c['data'], $row['type']);
    return $c['data'] ? $c : null;
}

/**
 * @param AioCatRow $row
 * @param int $skip
 * @return string
 */
function aio_cat_page_key($row, $skip)
{
    return "{$row['key']}|{$row['genre']}|$skip";
}

// The next tiles of a row: at least one new unless it failed, ended or ran out of
// $limit (then call again, the state goes on). Moves $st on.
// -> tiles: the new ones (no repeats of this listing); reset: the listing began
// anew after 5 h, the tiles replace those of the caller; end: nothing more to ask
// for; err: why it failed ('' none; aio_cat_get, or empty: a first page came empty
// though the row had tiles); stale: tiles from the last good copy. On a failure the
// state stays for a retry, unless the copy moved it on: a failure is never the end of
// a row. A short page or a page of repeats is not the end either.
// $limit: s for this call, no next request after it, requests cut to what is left of it.
/**
 * @param AioCatState $st
 * @param float $limit
 * @param-out AioCatState $st
 * @return AioCatResult
 */
function aio_cat_more(&$st, $limit)
{
    $row = $st['row'];
    $res = aio_cat_result();
    $start = microtime(true);
    while (true)
    {
        $now = time();
        // The clock going back is not trusted either.
        $expired = $st['t0'] > 0 && ($now < $st['t0'] || $now - $st['t0'] >= AIO_CAT_STATE_TTL);
        if ($st['end'] !== '' && !$expired)
        {
            $res['end'] = true;
            return $res;
        }
        $skip = $expired ? 0 : $st['skip'];
        $first = $skip === 0 ? aio_cat_page_cached($row, 0) : null;
        if ($first && aio_cat_is_fresh($first))
            $page = array('err' => '', 'n' => $first['n'], 't' => $first['t'], 'tiles' => $first['data']);
        else
        {
            // curl takes whole seconds.
            $left = max(1, intval(ceil($limit - (microtime(true) - $start))));
            $g = aio_cat_get(aio_cat_page_path($row, $skip), "{$row['key']} skip=$skip", 'metas', $left);
            $tiles = aio_cat_tiles($g['list'], $row['type']);
            $page = array('err' => $g['err'], 'n' => count($g['list']), 't' => time(), 'tiles' => $tiles);
            // Empty though the row had tiles: more likely a trouble of the server.
            if ($page['err'] === '' && $page['n'] === 0 && $first)
                $page['err'] = 'empty';
            if ($tiles)
                aio_cat_cache_write(aio_cat_page_key($row, $skip), $page['n'], $tiles);
        }
        if ($page['err'] !== '')
        {
            // The tiles of the caller stay: a copy of the position it is at, not of the first page.
            $res['err'] = $page['err'];
            $c = aio_cat_page_cached($row, $st['skip']);
            if (!$c)
                return $res;
            $res['stale'] = true;
            if ($st['skip'] === 0)
                $st['t0'] = $c['t'];
            aio_cat_take($st, $res, $c['n'], $c['data']);
            return $res;
        }
        if ($expired)
        {
            $st = aio_cat_row_state($row);
            $res['reset'] = true;
        }
        if ($st['skip'] === 0)
            $st['t0'] = $page['t'];
        if ($page['n'] === 0)
        {
            $st['end'] = 'empty';
            $res['end'] = true;
            return $res;
        }
        // No new tiles and not the end: the next portion at once.
        if (aio_cat_take($st, $res, $page['n'], $page['tiles']) > 0 || $st['end'] !== '' ||
            microtime(true) - $start >= $limit)
            return $res;
    }
}

// --- Search.

// Search of one type: one page, cached as the first page of a row, a failure gives
// the last good copy. The query stays out of the log and the file names.
/**
 * @param string $type
 * @param string $q
 * @return AioCatResult
 */
function aio_cat_search_type($type, $q)
{
    $key = "search|$type|$q";
    $res = aio_cat_result();
    $res['end'] = true;
    $c = aio_cat_cache_read($key);
    $old = $c ? aio_cat_tiles($c['data'], $type) : array();
    if ($old && aio_cat_is_fresh($c))
    {
        $res['tiles'] = aio_cat_unique($old);
        return $res;
    }
    $g = aio_cat_get("catalog/$type/search.$type/search=" . rawurlencode($q) . '.json', "search $type", 'metas');
    if ($g['err'] !== '')
    {
        $res['err'] = $g['err'];
        // A failure is not the end: a retry may find it.
        $res['end'] = false;
        if ($old)
        {
            $res['tiles'] = aio_cat_unique($old);
            $res['stale'] = true;
        }
        return $res;
    }
    $tiles = aio_cat_tiles($g['list'], $type);
    if ($tiles)
        aio_cat_cache_write($key, count($g['list']), $tiles);
    $res['tiles'] = aio_cat_unique($tiles);
    return $res;
}

// Without repeats by id.
/**
 * @param list<AioCatTile> $tiles
 * @return list<AioCatTile>
 */
function aio_cat_unique($tiles)
{
    $by = array();
    foreach ($tiles as $t)
    {
        if (!isset($by[$t['id']]))
            $by[$t['id']] = $t;
    }
    return array_values($by);
}

// Search by $q in movies and series, array(movie => result, series => result),
// results as of aio_cat_more. Search of anime does not find Russian names, so
// it is not asked. An empty query: nothing asked, nothing found.
/**
 * @param string $q
 * @return array{movie: AioCatResult, series: AioCatResult}
 */
function aio_cat_search($q)
{
    $q = mb_substr(aio_one_line($q), 0, AIO_CAT_QUERY_MAX, 'UTF-8');
    if ($q === '')
    {
        $none = aio_cat_result();
        $none['end'] = true;
        return array('movie' => $none, 'series' => $none);
    }
    return array('movie' => aio_cat_search_type('movie', $q), 'series' => aio_cat_search_type('series', $q));
}

// --- Cache: <data_dir>/catalog/<md5 of the key>.json, no secrets in the names. Only
// trimmed data goes in, and the UUID never does (aio_cat_cache_write).

// '' when there is no data_dir or the folder can not be made: all works, uncached.
/**
 * @return string
 */
function aio_cat_cache_dir()
{
    static $pruned = '';
    $data = aio_data_dir();
    if ($data === '' || !is_dir($data))
        return '';
    $dir = "$data/catalog";
    // The settings page runs as root and only reads: a folder or a file of its own
    // would lock out php_server.
    if (defined('AIO_CAT_READ_ONLY'))
        return !is_link($dir) && is_dir($dir) ? $dir : '';
    if (!is_dir($dir) && !mkdir($dir, 0700))
        return '';
    // Once a run: old files go even if nothing is ever written again.
    if ($pruned !== $dir)
    {
        $pruned = $dir;
        aio_cat_cache_prune($dir);
    }
    return $dir;
}

/**
 * @param string $key
 * @return string
 */
function aio_cat_cache_path($key)
{
    $dir = aio_cat_cache_dir();
    return $dir === '' ? '' : "$dir/" . ($key === AIO_CAT_MANIFEST_KEY ? $key : md5($key)) . '.json';
}

// -> array(t: when saved, n: items of the reply, data) or null; a broken or expired file
// is removed, unless AIO_CAT_READ_ONLY.
/**
 * @param string $key
 * @return array{t: int, n: int, data: array<mixed>}|null
 */
function aio_cat_cache_read($key)
{
    $f = aio_cat_cache_path($key);
    if ($f === '' || !is_file($f))
        return null;
    $c = json_decode(strval(file_get_contents($f)), true);
    if (is_array($c) && isset($c['v'], $c['t'], $c['n'], $c['data']) && $c['v'] === AIO_CAT_CACHE_V && is_int($c['t']) &&
        is_int($c['n']) && $c['n'] >= 1 && is_array($c['data']) && time() - $c['t'] < AIO_CAT_KEEP)
        return array('t' => $c['t'], 'n' => $c['n'], 'data' => $c['data']);
    if (!defined('AIO_CAT_READ_ONLY'))
        unlink($f);
    return null;
}

// Saved less than AIO_CAT_FRESH ago. A saved time ahead of the clock (a clock not
// set after a reboot) is not fresh, but still a last good copy.
/**
 * @param int $t
 * @return bool
 */
function aio_cat_fresh_since($t)
{
    return time() >= $t && time() - $t < AIO_CAT_FRESH;
}

/**
 * @param array{t: int}|null $c
 * @return bool
 */
function aio_cat_is_fresh($c)
{
    return $c !== null && aio_cat_fresh_since($c['t']);
}

// The first page of the listing was got less than AIO_CAT_FRESH ago.
/**
 * @param AioCatState $st
 * @return bool
 */
function aio_cat_state_fresh($st)
{
    return aio_cat_fresh_since($st['t0']);
}

/**
 * @param string $key
 * @param int $n
 * @param array<mixed> $data
 * @return void
 */
function aio_cat_cache_write($key, $n, $data)
{
    $f = aio_cat_cache_path($key);
    if ($f === '' || defined('AIO_CAT_READ_ONLY'))
        return;
    $json = json_encode(array('v' => AIO_CAT_CACHE_V, 't' => time(), 'n' => $n, 'data' => $data));
    // The trimmed fields do not hold it; should the server put it there, no copy on the disk.
    if (!is_string($json) || stripos($json, AIO_CAT_UUID) !== false)
    {
        aio_log('catalog: not cached: bad data');
        return;
    }
    if (!aio_write_file($f, $json))
    {
        aio_log('catalog: not cached: write failed');
        return;
    }
    aio_cat_cache_prune(dirname($f));
}

// Removes what is left of an interrupted write and files over AIO_CAT_KEEP, then the
// oldest while over the limits. The manifest goes only by age: rows need it.
/**
 * @param string $dir
 * @return void
 */
function aio_cat_cache_prune($dir)
{
    clearstatcache();
    $list = glob("$dir/*");
    $times = array();
    $sizes = array();
    foreach ($list ? $list : array() as $f)
    {
        $m = intval(filemtime($f));
        if (substr($f, -5) !== '.json' || time() - $m >= AIO_CAT_KEEP)
        {
            unlink($f);
            continue;
        }
        $times[$f] = $m;
        $sizes[$f] = intval(filesize($f));
    }
    asort($times);
    $total = array_sum($sizes);
    $count = count($times);
    foreach ($times as $f => $m)
    {
        if ($total <= AIO_CAT_CACHE_BYTES && $count <= AIO_CAT_CACHE_FILES)
            break;
        if (basename($f) === AIO_CAT_MANIFEST_KEY . '.json')
            continue;
        unlink($f);
        $total -= $sizes[$f];
        $count--;
    }
}
