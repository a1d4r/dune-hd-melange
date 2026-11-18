<?php
// Client of the catalog: rows of the shared AIOMetadata instance (Stremio addon
// protocol), its search, a cache on the device and the last good copy on a
// failure. Data only, no screens. Needs parse.php and main.php (mel_http_get, mel_http_get_many,
// mel_log, mel_write_file, mel_data_dir), on the settings page cgi/lib/rows.php; a listing of php_server
// hidden_titles.php too (mel_ht_now, mel_ht_off: the filter of mel_cat_take).
// Types in the PHPDoc (MelCatRow and others): phpstan-types.neon of the repository.

define('MEL_CAT_BASE', 'https://aiometadata.kompotik.com/stremio/');
// The shared config of the instance: public, as the apikey of the own JacRed, and
// read-only without its password. CI of this repository
// allows this one value.
define('MEL_CAT_UUID', 'e3778c33-0d0e-4d2e-a4db-a817b2d506f4');

// Timeouts of a request, s: a cold row of the server takes up to 4.5 s.
define('MEL_CAT_CONNECT', 5);
define('MEL_CAT_TOTAL', 15);
// A bigger reply (decoded) is cut off while it loads: 3 times the largest seen
// (344 KB). Over this many objects and lists it is not decoded: 1 MB of "{}," takes
// more than the 128 MB of PHP in json_decode, a Fatal; a real page of 50 tiles has
// ~1600 of them, its links aside.
define('MEL_CAT_MAX_BYTES', 1048576);
define('MEL_CAT_MAX_NODES', 40000);
// Tiles kept of a page, the rest only counts for skip: the server sends up to 50.
define('MEL_CAT_PAGE_TILES', 200);

// Listing of a row. skip of 1000 and more is a 404 of the server, so the row ends
// there. The state lives 5 h: the cursor of the server lives 6 h, and a lost one
// silently loses tiles.
define('MEL_CAT_SKIP_MAX', 1000);
define('MEL_CAT_STATE_TTL', 18000);
// Pages past the first one call of mel_cat_more asks at most while the filter ("Скрыть", the age: mel_ht_off) takes
// all their new tiles; then the listing ends (end "filter"): a strict age would page a row of trends to the limit of
// skip.
define('MEL_CAT_FILTER_PAGES', 3);
// skip of a search goes by pages: the server asks its engine for page
// ceil(skip / size) + 1 and sends what of it passed the filters, so skip by the tiles
// got would ask for the same page again. 20 is the smallest page of the search engines
// of the server (TMDB, Kitsu 20; MAL, TVDB 25; Trakt 30): with a bigger one a page
// only comes twice (repeats are dropped), a step over the page would silently skip
// results. Not fitted to the engine on purpose.
define('MEL_CAT_SEARCH_PAGE', 20);
// A search gets this many tiles of a type, if there are: a full row of the screen
// (MEL_CATV_ROW_TILES of view_catalog.php). A short page is not the end, so one page is
// not enough.
define('MEL_CAT_SEARCH_TILES', 20);
// s from the start of a search after which no pages past the first are asked: a page
// takes 0.2-1.9 s, the first pages keep MEL_CAT_TOTAL each, so the worst case of a
// search stays that of its first pages alone.
define('MEL_CAT_SEARCH_WAIT', 10);

// Cache in data_dir/catalog: a failure right after a reboot has it too, tmp_dir
// (RAM) would not. Fresh for 1 h: first pages are not asked again (the server
// keeps rows for a day). Kept for 30 days as the last good
// copy: an older row is of little use, and TMDB allows 6 months at most. 8 MB,
// 400 files: ~80 pages of 100 KB (json_encode makes Cyrillic 6 bytes a letter),
// the oldest go first.
// Raise the version when mel_cat_tile makes a wrong tile of an older copy, not for a new key alone: older copies are dropped.
define('MEL_CAT_CACHE_V', 3);
define('MEL_CAT_FRESH', 3600);
// The copy of the manifest is not asked again for 10 min (mel_cat_manifest_recent): rows added or
// removed on the server show within it, an opening or a switch of the tab does not wait ~1 s for it.
define('MEL_CAT_MANIFEST_FRESH', 600);
define('MEL_CAT_KEEP', 2592000);
define('MEL_CAT_CACHE_BYTES', 8388608);
define('MEL_CAT_CACHE_FILES', 400);
define('MEL_CAT_MANIFEST_KEY', 'manifest');

define('MEL_CAT_NAME_MAX', 200);
define('MEL_CAT_DESC_MAX', 1200);
define('MEL_CAT_URL_MAX', 1000);
// A TMDB address in the URLs of the tiles (the logo, the fanart of the card backgrounds, mel_catv_card).
define('MEL_CAT_TMDB', 'https://image\.tmdb\.org/t/p/[a-z0-9_]+/');
define('MEL_CAT_GENRES_MAX', 8);
define('MEL_CAT_CAST_MAX', 3);
define('MEL_CAT_QUERY_MAX', 100);
// The rating of Kinopoisk of fewer votes is not shown (the owner's choice).
define('MEL_CAT_KP_VOTES_MIN', 1000);

class MelCatCopies
{
    // The index of the copies of the rows on the disk (mel_cat_cache_index): each file (mel_cat_cache_dir) as it was
    // when its keys were read ("mtime:size") and its mtime, its keys ("<type>|imdb:<IMDb>", "<type>|tmdb:<TMDB>"), and
    // the file of each key (of the newest file). Memory of php_server.
    /** @var array<string, string> */
    public static $sig = array();
    /** @var array<string, int> */
    public static $mtime = array();
    /** @var array<string, list<string>> */
    public static $keys = array();
    /** @var array<string, string> */
    public static $map = array();

    // The index forgotten (a new php_server).
    /**
     * @return void
     */
    public static function reset()
    {
        self::$sig = array();
        self::$mtime = array();
        self::$keys = array();
        self::$map = array();
    }
}

// --- Requests.

// The only place that adds the UUID to an address: no log or cache gets this URL.
/**
 * @param string $path
 * @return string
 */
function mel_cat_url($path)
{
    return MEL_CAT_BASE . MEL_CAT_UUID . '/' . $path;
}

// One GET -> array(err: '' or why it failed, list: the JSON list in $field, curl28: a timeout of curl, connected or not).
// err: net (transport; a timeout before a connection too: the server out of reach),
// timeout (curl 28 once connected: a slow reply), big (over the limit), rate (429), server (5xx),
// notfound (404), http (another status), bad (not the JSON expected). A failure
// is never an empty list. $what: for the log, no URL and no reply there; '' no line (the
// caller logs a sum of its requests). $limit: s
// the request may take at most (a fraction too: mel_http_get), 0 for the usual timeouts; over
// MEL_CAT_TOTAL (15 s) it is cut to that, silently. $one: $field holds one
// object (the meta of a title), not a list; null or a list there is bad. $max: bytes of the
// decoded reply at most (big over it).
/**
 * @param string $path
 * @param string $what
 * @param string $field
 * @param float|int $limit
 * @param bool $one
 * @param int $max
 * @return array{err: string, list: array<mixed>, curl28: bool} list: the object itself when $one
 */
function mel_cat_get($path, $what, $field, $limit = 0, $one = false, $max = MEL_CAT_MAX_BYTES)
{
    return mel_cat_reply(mel_http_get_req(mel_cat_req($path, $limit, $max)), $what, $field, $one);
}

// One GET $q (array(URL, connect, total, max), as a request of mel_http_get_many) by mel_http_get -> array(HTTP code,
// body, err, connected, s it took), as a reply of mel_http_get_many. Here, not in main.php: the settings page
// (cgi/lib/rows.php) has its own mel_http_get and no main.php.
/**
 * @param array{string, float|int, float|int, int} $q
 * @return array{int, string, string, bool, float}
 */
function mel_http_get_req($q)
{
    $t = microtime(true);
    $err = '';
    $connected = true;
    list($code, $body) = mel_http_get($q[0], $err, $q[1], $q[2], $q[3], $connected);
    return array($code, $body, $err, (bool) $connected, microtime(true) - $t);
}

// The GETs of paths $paths (key => array(path, what)) at once (mel_http_get_many; the caller keeps to MEL_HTTP_PAR),
// each as mel_cat_get with the same $field, $limit, $one, $max and its line of the log -> key => array(err, list) in
// the order of $paths; null: no curl_multi (the caller asks one by one, mel_cat_get).
/**
 * @param array<array-key, array{string, string}> $paths
 * @param string $field
 * @param float|int $limit
 * @param bool $one
 * @param int $max
 * @return array<array-key, array{err: string, list: array<mixed>, curl28: bool}>|null
 */
function mel_cat_get_many($paths, $field, $limit = 0, $one = false, $max = MEL_CAT_MAX_BYTES)
{
    $reqs = array();
    foreach ($paths as $k => $p)
        $reqs[$k] = mel_cat_req($p[0], $limit, $max);
    $res = mel_http_get_many($reqs);
    if ($res === null)
        return null;
    $out = array();
    foreach ($paths as $k => $p)
    {
        $out[$k] = mel_cat_reply($res[$k], $p[1], $field, $one);
        // The body of each freed as it is parsed.
        unset($res[$k]);
    }
    return $out;
}

// The GET of $path (mel_cat_get) -> array(URL, connect, total, max) of mel_http_get.
/**
 * @param string $path
 * @param float|int $limit
 * @param int $max
 * @return array{string, float|int, float|int, int}
 */
function mel_cat_req($path, $limit, $max)
{
    $total = $limit > 0 ? min($limit, MEL_CAT_TOTAL) : MEL_CAT_TOTAL;
    return array(mel_cat_url($path), min(MEL_CAT_CONNECT, $total), $total, $max);
}

// The reply $r (array(HTTP code, body, err, connected, s it took) of mel_http_get) of a GET of mel_cat_get, its line
// of the log -> array(err, list, curl28) as mel_cat_get.
/**
 * @param array{int, string, string, bool, float} $r
 * @param string $what
 * @param string $field
 * @param bool $one
 * @return array{err: string, list: array<mixed>, curl28: bool}
 */
function mel_cat_reply($r, $what, $field, $one)
{
    list($code, $body, $err, $connected, $s) = $r;
    unset($r);
    $size = strlen($body);
    $status = $err !== '' ? $err : "HTTP $code";
    $why = '';
    $list = array();
    if ($err === MEL_HTTP_TOO_BIG)
        $why = 'big';
    else if (strpos($err, 'curl 28:') === 0 && $connected)
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
        $j = substr_count($body, '{') + substr_count($body, '[') <= MEL_CAT_MAX_NODES ? json_decode($body, true) : null;
        // An array in PHP may be an object in JSON: only a list goes on.
        // No ternary on the array: PHP 5.3 would copy it.
        $ok = is_array($j) && isset($j[$field]) && is_array($j[$field]);
        if ($ok)
        {
            $is_list = !$j[$field] || isset($j[$field][0]) || array_key_exists(0, $j[$field]);
            $ok = $one ? $j[$field] && !$is_list : $is_list;
        }
        if ($ok)
            $list = $j[$field];
        else
            $why = 'bad';
    }
    unset($body, $j);
    if ($what !== '')
        mel_log(sprintf('catalog: %s: %s, %d bytes, %d items, %.0f ms%s', $what, $status, $size, $one ? ($list ? 1 : 0) : count($list),
            $s * 1000, $why !== '' ? ", failed: $why" : ''));
    return array('err' => $why, 'list' => $list, 'curl28' => strpos($err, 'curl 28:') === 0);
}

// The err of mel_cat_reply is the server down: no request of the next row after it. A timeout is not: a slow row or the
// end of the limit (mel_catv_open, mel_tv_refresh).
/**
 * @param string $err
 * @return bool
 */
function mel_cat_err_down($err)
{
    return in_array($err, array('net', 'rate', 'server'), true);
}

// --- Rows.

// The group of a row, by its id: the manifest has no such field. 'other' for the
// rest; a group new to the plugin comes as it is, its name on the screen is not here.
/**
 * @param string $id
 * @return string
 */
function mel_cat_group($id)
{
    if (preg_match('/^tmdb\.discover\.[^.]+\.([^.]+)\./', $id, $m))
        return $m[1];
    if (strpos($id, 'anilist.') === 0)
        return 'anime';
    if (strpos($id, 'custom.fresh.') === 0)
        return 'fresh';
    return $id === 'tmdb.trending' || $id === 'tmdb.top' || $id === 'tmdb.top_rated' || strpos($id, 'letterboxd.') === 0 ? 'top' : 'other';
}

// The groups of the screens in their order: GROUPS of web/src/rows.ts, the menu of the rows
// (mel_catv_lcfg_groups). A group of mel_cat_group not here is "other" there.
/**
 * @return list<string>
 */
function mel_cat_groups()
{
    return array('top', 'fresh', 'ru', 'ru-services', 'genres', 'theme', 'studios', 'decades', 'years',
        'streaming', 'world', 'runtime', 'anime', 'other');
}

// The group of the row $r on the screens: its own (mel_cat_group), if one of mel_cat_groups, else "other".
/**
 * @param array{group: string} $r
 * @return string
 */
function mel_cat_block($r)
{
    return in_array($r['group'], mel_cat_groups(), true) ? $r['group'] : 'other';
}

// The rows $rows by groups (mel_cat_block): group => its rows, the groups in the order of their
// first row, the rows of each in their order. The same rule as blocksOf of web/src/rows.ts.
/**
 * @param list<MelCatRow> $rows
 * @return array<string, list<MelCatRow>>
 */
function mel_cat_blocks($rows)
{
    $out = array();
    foreach ($rows as $r)
        $out[mel_cat_block($r)][] = $r;
    return $out;
}

// The rows $rows with those of a group together (mel_cat_blocks, one after another).
/**
 * @param list<MelCatRow> $rows
 * @return list<MelCatRow>
 */
function mel_cat_by_groups($rows)
{
    $out = array();
    foreach (mel_cat_blocks($rows) as $b)
        $out = array_merge($out, $b);
    return $out;
}

// A row of the screen, or null when it can not be asked for. key: type and id
// (names repeat between the types, an id too; of a search: search/<type>,
// mel_cat_search_row), name for the screen (mel_cat_short_name), genre: the
// value to send, '' for none, home: a start row, group: mel_cat_group. $short: false for a
// name of the plugin (a search row), not one of the manifest.
/**
 * @param mixed $type
 * @param mixed $id
 * @param string $name
 * @param string $genre
 * @param bool $home
 * @param bool $short
 * @return MelCatRow|null
 */
function mel_cat_row($type, $id, $name, $genre, $home, $short = true)
{
    static $types = array('movie' => true, 'series' => true, 'anime' => true);
    if (!is_string($type) || !isset($types[$type]) || !is_string($id) || !preg_match('/^[\x21-\x7e]{1,200}$/', $id))
        return null;
    $row = array('key' => "$type/$id", 'type' => $type, 'id' => $id, 'name' => '', 'genre' => $genre,
        'home' => $home, 'group' => mel_cat_group($id));
    $name = mel_one_line($name !== '' ? $name : $id);
    $row['name'] = mel_cut($short ? mel_cat_short_name($name, mel_cat_row_tab($row)) : $name, MEL_CAT_NAME_MAX);
    return $row;
}

// The tab of the catalog of a row: movie on "Movies"; series and anime (the AniList rows,
// their tiles are series) on "Series". One rule for the screen and the settings page.
/**
 * @param array{type: string} $row
 * @return string
 */
function mel_cat_row_tab($row)
{
    return $row['type'] === 'movie' ? 'movie' : 'series';
}

// The tabs in their order: movies, then series. The settings page keeps the keys of
// cat_rows in this order too (commit() of web/src/rows.ts): the start rows compare by it.
/**
 * @return list<string>
 */
function mel_cat_tabs()
{
    return array('movie', 'series');
}

// The name of a row without the type its tab ($tab: mel_cat_row_tab) already says:
// "Боевик: фильмы" -> "Боевик", "Фильмы в тренде" -> "В тренде", "2025: фильмы" -> "2025 год".
// The first letter capital after a cut. Nothing left: the name as it is. A short name stays
// as it is (the copy of the manifest keeps them). The words are of the shared config in
// Russian: with another manifest the rule finds nothing and leaves the names.
/**
 * @param string $name
 * @param string $tab
 * @return string
 */
function mel_cat_short_name($name, $tab)
{
    $w = $tab === 'movie' ? 'фильмы' : 'сериалы';
    $s = strval(preg_replace("/\\s*:\\s*$w\\z/iu", '', $name));
    $rest = trim(strval(preg_replace("/^$w\\s+/iu", '', $s)));
    if ($rest === '' || $rest === $name)
        return $name;
    if (preg_match('/^[0-9]{4}\z/', $rest))
        return "$rest год";
    return mb_strtoupper(mb_substr($rest, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($rest, 1, mb_strlen($rest, 'UTF-8'), 'UTF-8');
}

// The only place that chooses rows from the catalogs of the manifest, in its order
// (the order of the screen): rows of a known type without a required extra but
// genre. That leaves out search, calendar-videos and aiom.similar by what they are,
// not by a list of ids. A required genre goes as its first option, an optional one
// not at all ("default" of the manifest is a trap: it narrows a row to one genre).
// A start row: showInHome strictly true (service rows have no such field).
/**
 * @param array<mixed> $catalogs
 * @return list<MelCatRow>
 */
function mel_cat_pick_rows($catalogs)
{
    $rows = array();
    foreach ($catalogs as $c)
    {
        if (!is_array($c))
            continue;
        $req = array();
        foreach (mel_arr($c, 'extraRequired') as $name)
            $req[mel_str(array($name), 0)] = true;
        $genre = '';
        foreach (mel_arr($c, 'extra') as $x)
        {
            $name = mel_str($x, 'name');
            if (is_array($x) && isset($x['isRequired']) && $x['isRequired'] === true)
                $req[$name] = true;
            if ($name === 'genre')
            {
                $opts = mel_arr($x, 'options');
                $genre = mel_str($opts, 0);
            }
        }
        if (array_diff(array_keys($req), array('genre')))
            continue;
        $row = mel_cat_row(isset($c['type']) ? $c['type'] : null, isset($c['id']) ? $c['id'] : null,
            mel_str($c, 'name'), isset($req['genre']) ? $genre : '', isset($c['showInHome']) && $c['showInHome'] === true);
        if ($row && !isset($rows[$row['key']]))
            $rows[$row['key']] = $row;
    }
    return array_values($rows);
}

// The saved rows of any age within MEL_CAT_KEEP, no request -> rows, t: when saved
// (0: no copy).
/**
 * @return array{rows: list<MelCatRow>, t: int}
 */
function mel_cat_manifest_copy()
{
    $c = mel_cat_cache_read(MEL_CAT_MANIFEST_KEY);
    $rows = array();
    foreach ($c ? $c['data'] : array() as $r)
    {
        $row = mel_cat_row(isset($r['type']) ? $r['type'] : null, isset($r['id']) ? $r['id'] : null,
            mel_str($r, 'name'), mel_str($r, 'genre'), isset($r['home']) && $r['home'] === true);
        if ($row)
            $rows[] = $row;
    }
    return array('rows' => $rows, 't' => $c ? $c['t'] : 0);
}

// The rows from the server, saved as the copy -> rows, err ('' none; mel_cat_get).
// $limit: as of mel_cat_get.
/**
 * @param int $limit
 * @return array{rows: list<MelCatRow>, err: string}
 */
function mel_cat_manifest_fetch($limit = 0)
{
    $g = mel_cat_get('manifest.json', 'manifest', 'catalogs', $limit);
    $new = mel_cat_pick_rows($g['list']);
    if ($new)
    {
        mel_cat_cache_write(MEL_CAT_MANIFEST_KEY, count($new), $new);
        return array('rows' => $new, 'err' => '');
    }
    // A manifest without rows is broken too.
    return array('rows' => array(), 'err' => $g['err'] !== '' ? $g['err'] : 'bad');
}

// The copy $c of mel_cat_manifest_copy has rows saved less than MEL_CAT_MANIFEST_FRESH ago:
// no request for the manifest. A time ahead of the clock is not recent (mel_cat_fresh_since).
/**
 * @param array{rows: list<MelCatRow>, t: int} $c
 * @return bool
 */
function mel_cat_manifest_recent($c)
{
    return $c['rows'] && time() >= $c['t'] && time() - $c['t'] < MEL_CAT_MANIFEST_FRESH;
}

// Rows of the catalog: from the cache for MEL_CAT_MANIFEST_FRESH, else from the server; on
// a failure the last good copy with stale set, err tells why ('' none; mel_cat_get).
// No copy either: no rows and err.
/**
 * @return MelCatRows
 */
function mel_cat_manifest()
{
    $c = mel_cat_manifest_copy();
    if (mel_cat_manifest_recent($c))
        return array('rows' => $c['rows'], 'stale' => false, 'err' => '');
    $new = mel_cat_manifest_fetch();
    if ($new['rows'])
        return array('rows' => $new['rows'], 'stale' => false, 'err' => '');
    return array('rows' => $c['rows'], 'stale' => (bool) $c['rows'], 'err' => $new['err']);
}

// The rows of the tab $tab (mel_cat_row_tab) by the choice of the user (Mel::$settings['cat_rows']):
// the chosen ones of the tab in the order of the user; a key not in the manifest goes, a new row
// does not come by itself. None of the tab (not a list, nothing chosen, a choice of before 0.54.0
// with only movies): the start rows of the tab, in the order of the manifest, never an empty tab.
// Either way the rows of a group together (mel_cat_by_groups): a choice made before may mix them.
/**
 * @param list<MelCatRow> $rows
 * @param mixed $chosen
 * @param string $tab
 * @return list<MelCatRow>
 */
function mel_cat_user_rows($rows, $chosen, $tab)
{
    $by = array();
    foreach ($rows as $r)
    {
        if (mel_cat_row_tab($r) === $tab)
            $by[$r['key']] = $r;
    }
    $out = array();
    foreach (is_array($chosen) ? $chosen : array() as $k)
    {
        if (is_string($k) && isset($by[$k]))
        {
            $out[] = $by[$k];
            unset($by[$k]);
        }
    }
    if ($out)
        return mel_cat_by_groups($out);
    foreach ($by as $r)
    {
        if ($r['home'])
            $out[] = $r;
    }
    return mel_cat_by_groups($out);
}

// --- Tiles.

// http(s) address of a picture as the server gave it, else ''. The size in the
// address is up to the screen.
/**
 * @param mixed $m
 * @param string $k
 * @return string
 */
function mel_cat_img($m, $k)
{
    $u = mel_str($m, $k);
    return strlen($u) <= MEL_CAT_URL_MAX && preg_match('~^https?://\S+$~', $u) ? $u : '';
}

// The first country of the server ("Spain", "United States of America, Canada", ISO-3
// "jpn" of anime, any case) -> its English name; an unknown code: ''.
/**
 * @param string $s
 * @return string
 */
function mel_cat_country($s)
{
    static $iso = array('usa' => 'United States of America', 'gbr' => 'United Kingdom', 'fra' => 'France', 'deu' => 'Germany',
        'esp' => 'Spain', 'ita' => 'Italy', 'jpn' => 'Japan', 'kor' => 'South Korea', 'chn' => 'China', 'ind' => 'India',
        'can' => 'Canada', 'aus' => 'Australia', 'nzl' => 'New Zealand', 'rus' => 'Russia', 'bra' => 'Brazil', 'mex' => 'Mexico',
        'bel' => 'Belgium', 'nld' => 'Netherlands', 'swe' => 'Sweden', 'nor' => 'Norway', 'dnk' => 'Denmark', 'fin' => 'Finland',
        'irl' => 'Ireland', 'pol' => 'Poland', 'prt' => 'Portugal', 'aut' => 'Austria', 'che' => 'Switzerland', 'cze' => 'Czech Republic',
        'hun' => 'Hungary', 'grc' => 'Greece', 'tur' => 'Turkey', 'isr' => 'Israel', 'arg' => 'Argentina', 'hkg' => 'Hong Kong',
        'twn' => 'Taiwan', 'tha' => 'Thailand', 'idn' => 'Indonesia', 'phl' => 'Philippines', 'zaf' => 'South Africa',
        'isl' => 'Iceland', 'ukr' => 'Ukraine', 'kaz' => 'Kazakhstan');
    $first = explode(',', mel_one_line($s));
    $c = mel_cut(trim($first[0]), 60);
    if (!preg_match('/^[a-z]{3}$/i', $c))
        return $c;
    $k = strtolower($c);
    return isset($iso[$k]) ? $iso[$k] : '';
}

// runtime of a tile ("1h38min", "30min", "2h") in minutes, 0 for none.
/**
 * @param string $r
 * @return int
 */
function mel_cat_runtime_min($r)
{
    if (!preg_match('/^(?:([0-9]+)h)?(?:([0-9]+)min)?$/', $r, $x))
        return 0;
    return (isset($x[1]) ? intval($x[1]) * 60 : 0) + (isset($x[2]) ? intval($x[2]) : 0);
}

// A rating of Kinopoisk ("7.93") of $votes votes -> "7.9" as shown; '' for none, 0, over 10 or
// under MEL_CAT_KP_VOTES_MIN votes.
/**
 * @param string $kp
 * @param int $votes
 * @return string
 */
function mel_cat_kp($kp, $votes)
{
    $kp = preg_match('/^[0-9]{1,2}(\.[0-9]{1,3})?$/D', $kp) && (float) $kp <= 10 && $votes >= MEL_CAT_KP_VOTES_MIN ?
        sprintf('%.1F', (float) $kp) : '';
    // "0.04" is "0.0": none.
    return (float) $kp > 0 ? $kp : '';
}

// A tile of the server -> only what is needed (links with the UUID, app_extras,
// a logo not in Russian and the rest are gone from here on), or null without an id or a name.
// All values strings, genres and cast lists. imdb_id and _tmdbId: also from the id itself,
// '' for none (mal:...). type: of the tile (rows of anime hold movie and series),
// else of the row, '' when neither says. year: 4 digits or ''. country: the English
// name of the first one (mel_cat_country), '' for none. runtime: "1h38min" or "30min" (of an
// episode for series). age: the Russian rating "16+" of app_extras.certifications.RU. ageUs: the rating of the US
// of app_extras.certifications.US on the Russian scale (mel_cat_us_age), for the filter of the age (mel_cat_age_of).
// cast: the first names of app_extras.cast. englishTitle: the English name of the title
// (a field of our patch of the server; none: ''), as name. kpRating, kpVotes, kpId: of our
// patch too: the rating "7.9" ('' for none, 0, over 10 or under MEL_CAT_KP_VOTES_MIN votes),
// its votes (0 for none), the id of Kinopoisk (digits, '' for none). kpVotes is kept for the
// threshold when a copy of the cache is parsed again (mel_cat_tiles): without it the rating goes.
// logo, logoLang: of our patch too, the logo of the title in place of its name (the card background of
// the server, mel_catv_card, sizes it): kept only of "ru", its address https, the plain characters of
// a path, ending in a file of TMDB, PNG or SVG (the server takes an SVG as its PNG of TMDB); else none
// ('', ''): another language, null, not of TMDB. originalLanguage: mel_cat_orig_lang.
// Run again on what the cache holds (age, cast: its own keys; a copy of before them: '' and
// none): the same result.
/**
 * @param mixed $m
 * @param string $row_type
 * @return MelCatTile|null
 */
function mel_cat_tile($m, $row_type)
{
    $id = mel_str($m, 'id');
    $name = mel_cut(mel_one_line(mel_str($m, 'name')), MEL_CAT_NAME_MAX);
    if ($name === '' || !preg_match('/^[\x21-\x7e]{1,100}$/', $id))
        return null;
    $imdb = mel_str($m, 'imdb_id');
    if (!preg_match('/^tt[0-9]{1,10}$/', $imdb))
        $imdb = preg_match('/^tt[0-9]{1,10}$/', $id) ? $id : '';
    $tmdb = mel_str($m, '_tmdbId');
    if (!preg_match('/^[0-9]{1,10}$/', $tmdb))
        $tmdb = preg_match('/^tmdb:([0-9]{1,10})$/', $id, $x) ? $x[1] : '';
    $type = mel_str($m, 'type');
    if ($type !== 'movie' && $type !== 'series')
        $type = $row_type === 'movie' || $row_type === 'series' ? $row_type : '';
    $year = '';
    if (preg_match('/^[0-9]{4}/', mel_str($m, 'year'), $x) || preg_match('/^[0-9]{4}/', mel_str($m, 'releaseInfo'), $x))
        $year = $x[0];
    $rating = mel_str($m, 'imdbRating');
    $votes = mel_str($m, 'kpVotes');
    $votes = preg_match('/^[0-9]{1,9}$/D', $votes) ? intval($votes) : 0;
    $kp = mel_cat_kp(mel_str($m, 'kpRating'), $votes);
    $kp_id = mel_str($m, 'kpId');
    $genres = array();
    foreach (mel_arr($m, 'genres') as $g)
    {
        $g = mel_cut(mel_one_line(mel_str(array($g), 0)), 40);
        if ($g !== '' && count($genres) < MEL_CAT_GENRES_MAX)
            $genres[] = $g;
    }
    $country = mel_str($m, 'country');
    $country = mel_cat_country($country !== '' ? $country : mel_str(mel_arr($m, 'countries'), 0));
    $runtime = mel_str($m, 'runtime');
    $extras = mel_arr($m, 'app_extras');
    $age = mel_str(mel_arr($extras, 'certifications'), 'RU');
    $us = mel_cat_us_age(mel_str(mel_arr($extras, 'certifications'), 'US'));
    if (!isset($extras['certifications']))
    {
        $age = mel_str($m, 'age');
        $us = mel_str($m, 'ageUs');
    }
    $logo = mel_str($m, 'logo');
    $ru = mel_str($m, 'logoLang') === 'ru' && strlen($logo) <= MEL_CAT_URL_MAX &&
        preg_match('~^https://[A-Za-z0-9.-]+(:[0-9]{1,5})?/[A-Za-z0-9._/%:+-]*$~D', $logo) &&
        preg_match('~(^|/)' . MEL_CAT_TMDB . '[A-Za-z0-9]{10,40}\.(png|svg)$~D', $logo);
    $cast = array();
    foreach (isset($extras['cast']) ? mel_arr($extras, 'cast') : mel_arr($m, 'cast') as $c)
    {
        $c = mel_cut(mel_one_line(is_array($c) ? mel_str($c, 'name') : mel_str(array($c), 0)), 40);
        if ($c !== '' && count($cast) < MEL_CAT_CAST_MAX)
            $cast[] = $c;
    }
    // The first keys in this order: the index of the copies on the disk reads them so (mel_cat_cache_keys).
    return array('id' => $id, 'imdb_id' => $imdb, '_tmdbId' => $tmdb, 'type' => $type, 'name' => $name, 'year' => $year,
        'imdbRating' => preg_match('/^[0-9]{1,2}(\.[0-9]+)?$/', $rating) ? $rating : '',
        'description' => mel_cut(mel_one_line(mel_str($m, 'description')), MEL_CAT_DESC_MAX), 'genres' => $genres,
        'poster' => mel_cat_img($m, 'poster'), 'background' => mel_cat_img($m, 'background'), 'country' => $country,
        'runtime' => preg_match('/^(?=[0-9])([0-9]{1,2}h)?([0-9]{1,3}min)?$/', $runtime) ? $runtime : '',
        'age' => preg_match('/^[0-9]{1,2}\+$/', $age) ? $age : '', 'ageUs' => preg_match('/^[0-9]{1,2}\+$/D', $us) ? $us : '',
        'cast' => $cast,
        'englishTitle' => mel_cut(mel_one_line(mel_str($m, 'englishTitle')), MEL_CAT_NAME_MAX),
        'kpRating' => $kp, 'kpVotes' => $votes, 'kpId' => preg_match('/^[1-9][0-9]{0,9}$/D', $kp_id) ? $kp_id : '',
        'logo' => $ru ? $logo : '', 'logoLang' => $ru ? 'ru' : '', 'originalLanguage' => mel_cat_orig_lang($m));
}

// A rating of the US (G, PG-13, TV-MA...) on the Russian scale, by its stricter side: G, TV-Y, TV-G 0+; TV-Y7, PG,
// TV-PG 6+; PG-13 12+; TV-14 16+; R, NC-17, TV-MA 18+. Another (NR, an empty one): '' - no age.
/**
 * @param string $us
 * @return string
 */
function mel_cat_us_age($us)
{
    static $ages = array('G' => '0+', 'TV-Y' => '0+', 'TV-G' => '0+', 'TV-Y7' => '6+', 'PG' => '6+', 'TV-PG' => '6+',
        'PG-13' => '12+', 'TV-14' => '16+', 'R' => '18+', 'NC-17' => '18+', 'TV-MA' => '18+');
    $us = strtoupper(trim($us));
    return isset($ages[$us]) ? $ages[$us] : '';
}

// The age of tile $t for the filter: its Russian one, else that of the US (ageUs); -1 for none.
/**
 * @param MelCatTile $t
 * @return int
 */
function mel_cat_age_of($t)
{
    $a = $t['age'] !== '' ? $t['age'] : $t['ageUs'];
    return $a !== '' ? intval($a) : -1;
}

// The original language of the title of tile $m (not the language of the interface): two letters as TMDB gives it
// ("ja"), '' none. _providerArt.originalLanguage of AIOMetadata, else originalLanguage (a copy of the cache,
// mel_cat_tile), else _originalLanguage (rows of anime: ISO 639-2, "jpn"). Of the three letters only the languages
// Lampac 1.90.1 turns its balancers of anime on by: ja, zh all of them (OnlineApi.cs isanime); ko, th, vi, tl Kodik and
// AniDub alone (their ModInit.cs).
/**
 * @param mixed $m
 * @return string
 */
function mel_cat_orig_lang($m)
{
    $l = strtolower(mel_str(mel_arr($m, '_providerArt'), 'originalLanguage'));
    if ($l === '')
        $l = strtolower(mel_str($m, 'originalLanguage'));
    if ($l === '')
        $l = strtolower(mel_str($m, '_originalLanguage'));
    static $three = array('jpn' => 'ja', 'chi' => 'zh', 'zho' => 'zh', 'kor' => 'ko', 'tha' => 'th', 'vie' => 'vi', 'tgl' => 'tl');
    if (isset($three[$l]))
        $l = $three[$l];
    return preg_match('/^[a-z]{2}$/D', $l) ? $l : '';
}

// Items of a page -> the tiles among them; what is not a tile is dropped.
/**
 * @param array<mixed> $items
 * @param string $row_type
 * @return list<MelCatTile>
 */
function mel_cat_tiles($items, $row_type)
{
    $tiles = array();
    foreach (array_slice($items, 0, MEL_CAT_PAGE_TILES) as $m)
    {
        $t = mel_cat_tile($m, $row_type);
        if ($t)
            $tiles[] = $t;
    }
    return $tiles;
}

// --- Listing of a row.

// The address of a page of a row: the query of a search, its genre if any, skip if not 0;
// values encoded.
/**
 * @param MelCatRow $row
 * @param int $skip
 * @return string
 */
function mel_cat_page_path($row, $skip)
{
    $extra = array();
    if (isset($row['q']))
        $extra[] = 'search=' . rawurlencode($row['q']);
    if ($row['genre'] !== '')
        $extra[] = 'genre=' . rawurlencode($row['genre']);
    if ($skip > 0)
        $extra[] = sprintf('skip=%d', $skip);
    return 'catalog/' . rawurlencode($row['type']) . '/' . rawurlencode($row['id']) .
        ($extra ? '/' . implode('&', $extra) : '') . '.json';
}

// The state of a new listing of row $row (mel_cat_manifest), the argument of
// mel_cat_more. The caller keeps it where the tiles are and changes it only by the
// functions here: t0: when its first page was got (0: none yet), skip: all tiles got,
// repeats too (of a search: MEL_CAT_SEARCH_PAGE a page), end: why it ended ('' not yet;
// empty, skip, filter: MEL_CAT_FILTER_PAGES), seen: ids (those the filter took too).
/**
 * @param MelCatRow $row
 * @return MelCatState
 */
function mel_cat_row_state($row)
{
    return array('row' => $row, 't0' => 0, 'skip' => 0, 'end' => '', 'seen' => array());
}

// A listing from the saved first page of a row (mel_cat_page_cached), as if just got.
/**
 * @param MelCatRow $row
 * @param MelCatCached $c
 * @return array{st: MelCatState, tiles: list<MelCatTile>}
 */
function mel_cat_state_of_copy($row, $c)
{
    $st = mel_cat_row_state($row);
    $res = mel_cat_result();
    $st['t0'] = $c['t'];
    mel_cat_take($st, $res, $c['n'], $c['data']);
    return array('st' => $st, 'tiles' => $res['tiles']);
}

// $tiles the caller keeps after a reset: they do not come as new again.
/**
 * @param MelCatState $st
 * @param array<int|string, MelCatTile> $tiles
 * @param-out MelCatState $st
 * @return void
 */
function mel_cat_keep_seen(&$st, $tiles)
{
    foreach ($tiles as $t)
        $st['seen'][$t['id']] = true;
}

/**
 * @return MelCatResult
 */
function mel_cat_result()
{
    return array('tiles' => array(), 'reset' => false, 'end' => false, 'stale' => false, 'err' => '');
}

// A page taken into the listing: new tiles to $res, skip counts all $n got (of a
// search: MEL_CAT_SEARCH_PAGE a page), end set at its limits. A new tile the filter does not
// show (mel_ht_off: hidden, but not in a search - a row with q; the age, in a search too) is seen,
// not taken. -> the number of new tiles.
/**
 * @param MelCatState $st
 * @param MelCatResult $res
 * @param int $n
 * @param list<MelCatTile> $tiles
 * @param-out MelCatState $st
 * @param-out MelCatResult $res
 * @return int
 */
function mel_cat_take(&$st, &$res, $n, $tiles)
{
    $new = 0;
    $f = mel_ht_now();
    $search = isset($st['row']['q']);
    foreach ($tiles as $t)
    {
        if (isset($st['seen'][$t['id']]))
            continue;
        $st['seen'][$t['id']] = true;
        if (mel_ht_off($f, $t, $search))
            continue;
        $res['tiles'][] = $t;
        $new++;
    }
    $st['skip'] += isset($st['row']['q']) ? MEL_CAT_SEARCH_PAGE : $n;
    if ($st['skip'] >= MEL_CAT_SKIP_MAX)
        $st['end'] = 'skip';
    $res['end'] = $st['end'] !== '';
    return $new;
}

// The cached copy of a page -> array(t, n, tiles) or null.
/**
 * @param MelCatRow $row
 * @param int $skip
 * @return MelCatCached|null
 */
function mel_cat_page_cached($row, $skip)
{
    $c = mel_cat_cache_read(mel_cat_page_key($row, $skip));
    if (!$c)
        return null;
    $c['data'] = mel_cat_tiles($c['data'], $row['type']);
    return $c['data'] ? $c : null;
}

/**
 * @param MelCatRow $row
 * @param int $skip
 * @return string
 */
function mel_cat_page_key($row, $skip)
{
    return isset($row['q']) ? "search|{$row['type']}|{$row['q']}|$skip" : "{$row['key']}|{$row['genre']}|$skip";
}

// The next tiles of a row: at least one new unless it failed, ended or ran out of
// $limit (then call again, the state goes on). Moves $st on.
// -> tiles: the new ones (no repeats of this listing); reset: the listing began
// anew after 5 h, the tiles replace those of the caller; end: nothing more to ask
// for; err: why it failed ('' none; mel_cat_get, or empty: a first page came empty
// though the row had tiles); stale: tiles from the last good copy. On a failure the
// state stays for a retry, unless the copy moved it on: a failure is never the end of
// a row. A short page or a page of repeats is not the end either.
// $limit: s for this call, no next request after it, requests cut to what is left of it.
// $pages: pages past the first of this call whose new tiles the filter took all (mel_cat_take) asked at most, then
// the end (filter; the tab "TV" asks only first pages for their copies: 0).
/**
 * @param MelCatState $st
 * @param float $limit
 * @param int $pages
 * @param-out MelCatState $st
 * @return MelCatResult
 */
function mel_cat_more(&$st, $limit, $pages = MEL_CAT_FILTER_PAGES)
{
    $row = $st['row'];
    $res = mel_cat_result();
    $start = microtime(true);
    $cut = 0;
    while (true)
    {
        $now = time();
        // The clock going back is not trusted either.
        $expired = $st['t0'] > 0 && ($now < $st['t0'] || $now - $st['t0'] >= MEL_CAT_STATE_TTL);
        if ($st['end'] !== '' && !$expired)
        {
            $res['end'] = true;
            return $res;
        }
        $skip = $expired ? 0 : $st['skip'];
        $first = $skip === 0 ? mel_cat_page_cached($row, 0) : null;
        if ($first && mel_cat_is_fresh($first))
            $page = array('err' => '', 'n' => $first['n'], 't' => $first['t'], 'tiles' => $first['data']);
        else
        {
            // curl takes whole seconds.
            $left = max(1, intval(ceil($limit - (microtime(true) - $start))));
            $g = mel_cat_get(mel_cat_page_path($row, $skip), "{$row['key']} skip=$skip", 'metas', $left);
            $tiles = mel_cat_tiles($g['list'], $row['type']);
            $page = array('err' => $g['err'], 'n' => count($g['list']), 't' => time(), 'tiles' => $tiles);
            // Empty though the row had tiles: more likely a trouble of the server.
            if ($page['err'] === '' && $page['n'] === 0 && $first)
                $page['err'] = 'empty';
            // Of a search the first page only: its pages further on would push the
            // copies of the first pages of rows out of MEL_CAT_CACHE_FILES.
            if ($tiles && ($skip === 0 || !isset($row['q'])))
                mel_cat_cache_write(mel_cat_page_key($row, $skip), $page['n'], $tiles);
        }
        if ($page['err'] !== '')
        {
            // The tiles of the caller stay: a copy of the position it is at, not of the first page.
            $res['err'] = $page['err'];
            $c = mel_cat_page_cached($row, $st['skip']);
            if (!$c)
                return $res;
            $res['stale'] = true;
            if ($st['skip'] === 0)
                $st['t0'] = $c['t'];
            mel_cat_take($st, $res, $c['n'], $c['data']);
            return $res;
        }
        if ($expired)
        {
            $st = mel_cat_row_state($row);
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
        $seen = count($st['seen']);
        // No new tiles and not the end: the next portion at once.
        if (mel_cat_take($st, $res, $page['n'], $page['tiles']) > 0 || $st['end'] !== '' ||
            microtime(true) - $start >= $limit)
            return $res;
        // The filter took all the new ones, $pages times past the first: the end for this filter.
        if (count($st['seen']) > $seen && ++$cut > $pages)
        {
            $st['end'] = 'filter';
            $res['end'] = true;
            return $res;
        }
    }
}

// --- Search.

// The row of a search by $q of $type, a listing of mel_cat_more as of other rows: its
// first page is cached, a failure gives the last good copy. Its key is not that of the
// catalog of the server (id: for its address). The query stays out of the key, so out
// of the log (mel_cat_more), and out of the file names (mel_cat_cache_path).
/**
 * @param string $type
 * @param string $q
 * @param string $name
 * @return MelCatRow|null
 */
function mel_cat_search_row($type, $q, $name)
{
    $row = mel_cat_row($type, "search.$type", $name, '', false, false);
    if ($row)
    {
        $row['key'] = "search/$type";
        $row['q'] = $q;
    }
    return $row;
}

// A query as the search sends it: one line, at most MEL_CAT_QUERY_MAX characters.
/**
 * @param string $q
 * @return string
 */
function mel_cat_query($q)
{
    return mb_substr(mel_one_line($q), 0, MEL_CAT_QUERY_MAX, 'UTF-8');
}

// Search by $q in movies and series: by type, its listing after the pages got and the
// result of them (mel_cat_more): the first page, then pages further on until
// MEL_CAT_SEARCH_TILES tiles or the end, while $wait s from the start of the search
// last. None past a failure or the copy (stale) of the first page; a failure further
// on: err, the tiles before it stay, not the end. $names: by type, the name of its
// row. Search of anime does not find Russian names, so it is not asked. An empty
// query: nothing asked, nothing found.
/**
 * @param string $q
 * @param array{movie: string, series: string} $names
 * @param float $wait
 * @return array<string, array{st: MelCatState, res: MelCatResult}>
 */
function mel_cat_search($q, $names, $wait = MEL_CAT_SEARCH_WAIT)
{
    $q = mel_cat_query($q);
    $start = microtime(true);
    $found = array();
    foreach ($q !== '' ? $names : array() as $type => $name)
    {
        $row = mel_cat_search_row($type, $q, $name);
        if (!$row)
            continue;
        $st = mel_cat_row_state($row);
        $res = mel_cat_more($st, MEL_CAT_TOTAL);
        while (!$res['stale'] && $res['err'] === '' && $st['end'] === '' && count($res['tiles']) < MEL_CAT_SEARCH_TILES)
        {
            // curl takes whole seconds: less than 1 s left would be a request cut to 1 s, likely a timeout.
            $left = $wait - (microtime(true) - $start);
            if ($left < 1)
                break;
            $more = mel_cat_more($st, $left);
            // The listing began anew (the clock jumped): its tiles are all there is.
            if ($more['reset'])
                $res['tiles'] = $more['tiles'];
            else
                $res['tiles'] = array_merge($res['tiles'], $more['tiles']);
            $res['end'] = $more['end'];
            $res['err'] = $more['err'];
        }
        $found[$type] = array('st' => $st, 'res' => $res);
    }
    return $found;
}

// --- Cache: <data_dir>/catalog/<md5 of the key>.json, no secrets in the names. Only
// trimmed data goes in, and the UUID never does (mel_cat_cache_write).

// '' when there is no data_dir or the folder can not be made: all works, uncached.
/**
 * @return string
 */
function mel_cat_cache_dir()
{
    static $pruned = '';
    $data = mel_data_dir();
    if ($data === '' || !is_dir($data))
        return '';
    $dir = "$data/catalog";
    // The settings page runs as root and only reads: a folder or a file of its own
    // would lock out php_server.
    if (defined('MEL_CAT_READ_ONLY'))
        return !is_link($dir) && is_dir($dir) ? $dir : '';
    if (!is_dir($dir) && !mkdir($dir, 0700))
        return '';
    // Once a run: old files go even if nothing is ever written again.
    if ($pruned !== $dir)
    {
        $pruned = $dir;
        mel_cat_cache_prune($dir);
    }
    return $dir;
}

/**
 * @param string $key
 * @return string
 */
function mel_cat_cache_path($key)
{
    $dir = mel_cat_cache_dir();
    return $dir === '' ? '' : "$dir/" . ($key === MEL_CAT_MANIFEST_KEY ? $key : md5($key)) . '.json';
}

// -> array(t: when saved, n: items of the reply, data) or null; a broken or expired file
// is removed, unless MEL_CAT_READ_ONLY.
/**
 * @param string $key
 * @return array{t: int, n: int, data: array<mixed>}|null
 */
function mel_cat_cache_read($key)
{
    $f = mel_cat_cache_path($key);
    if ($f === '' || !is_file($f))
        return null;
    $c = json_decode(strval(file_get_contents($f)), true);
    if (mel_cat_cache_good($c))
        return array('t' => $c['t'], 'n' => $c['n'], 'data' => $c['data']);
    if (!defined('MEL_CAT_READ_ONLY'))
        unlink($f);
    return null;
}

// A copy $c as decoded is good: of this version, its fields, saved less than MEL_CAT_KEEP ago.
/**
 * @param mixed $c
 * @return bool
 * @phpstan-assert-if-true array{v: int, t: int, n: int, data: array<mixed>} $c
 */
function mel_cat_cache_good($c)
{
    return is_array($c) && isset($c['v'], $c['t'], $c['n'], $c['data']) && $c['v'] === MEL_CAT_CACHE_V && is_int($c['t']) &&
        is_int($c['n']) && $c['n'] >= 1 && is_array($c['data']) && time() - $c['t'] < MEL_CAT_KEEP;
}

// Saved less than MEL_CAT_FRESH ago. A saved time ahead of the clock (a clock not
// set after a reboot) is not fresh, but still a last good copy.
/**
 * @param int $t
 * @return bool
 */
function mel_cat_fresh_since($t)
{
    return time() >= $t && time() - $t < MEL_CAT_FRESH;
}

/**
 * @param array{t: int}|null $c
 * @return bool
 */
function mel_cat_is_fresh($c)
{
    return $c !== null && mel_cat_fresh_since($c['t']);
}

// $keys by $times (the time of the copy of each, in the same order), the oldest first, of the same time in the order of
// $keys (asort is not stable).
/**
 * @template T of int|string
 * @param list<T> $keys
 * @param list<int> $times
 * @return list<T>
 */
function mel_cat_oldest_first($keys, $times)
{
    $pos = array_keys($keys);
    array_multisort($times, SORT_ASC, $pos, SORT_ASC, $keys);
    return $keys;
}

// The first page of the listing was got less than MEL_CAT_FRESH ago.
/**
 * @param MelCatState $st
 * @return bool
 */
function mel_cat_state_fresh($st)
{
    return mel_cat_fresh_since($st['t0']);
}

/**
 * @param string $key
 * @param int $n
 * @param array<mixed> $data
 * @return void
 */
function mel_cat_cache_write($key, $n, $data)
{
    $f = mel_cat_cache_path($key);
    if ($f === '' || defined('MEL_CAT_READ_ONLY'))
        return;
    $json = json_encode(array('v' => MEL_CAT_CACHE_V, 't' => time(), 'n' => $n, 'data' => $data));
    // The trimmed fields do not hold it; should the server put it there, no copy on the disk.
    if (!is_string($json) || stripos($json, MEL_CAT_UUID) !== false)
    {
        mel_log('catalog: not cached: bad data');
        return;
    }
    if (!mel_write_file($f, $json))
    {
        mel_log('catalog: not cached: write failed');
        return;
    }
    // The tab "TV" of the main screen may be built of it (tv_tab.php; not loaded by the settings page).
    if (function_exists('mel_tv_copy_saved'))
        mel_tv_copy_saved($key);
    mel_cat_cache_prune(dirname($f));
}

// Removes what is left of an interrupted write and files over MEL_CAT_KEEP, then the
// oldest while over the limits. The manifest goes only by age: rows need it.
/**
 * @param string $dir
 * @return void
 */
function mel_cat_cache_prune($dir)
{
    clearstatcache();
    $list = glob("$dir/*");
    $times = array();
    $sizes = array();
    foreach ($list ? $list : array() as $f)
    {
        $m = intval(filemtime($f));
        if (substr($f, -5) !== '.json' || time() - $m >= MEL_CAT_KEEP)
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
        if ($total <= MEL_CAT_CACHE_BYTES && $count <= MEL_CAT_CACHE_FILES)
            break;
        if (basename($f) === MEL_CAT_MANIFEST_KEY . '.json')
            continue;
        unlink($f);
        $total -= $sizes[$f];
        $count--;
    }
}

// --- The tiles of the copies on the disk by IMDb or TMDB (the meta of the rows "Continue" and "Мой список",
// mel_cpm_build of cp_meta.php), no request.

// The keys of the tiles of the text $text of a copy (mel_cat_cache_write: {"v":..,"t":..,"n":..,"data":[tiles of
// mel_cat_tile]}) of a movie or a series, "<type>|imdb:<IMDb>" and "<type>|tmdb:<TMDB>" (as mel_cp_tile_map): by the
// fields of a tile in the order mel_cat_tile makes them, no json_decode; none of another version, of the manifest, of a
// card (catalog_card.php). The id a run of no quote or backslash at a time: PCRE of PHP 5.3.6 recurses on each repeat of
// a group, one a character would crash it on a long id.
/**
 * @param string $text
 * @return list<string>
 */
function mel_cat_cache_keys($text)
{
    $head = '{"v":' . MEL_CAT_CACHE_V . ',';
    if (strncmp($text, $head, strlen($head)) !== 0 || !preg_match_all('/\{"id":"(?:[^"\\\\]++|\\\\.)*+","imdb_id":"(tt[0-9]{1,10}|)",' .
        '"_tmdbId":"([0-9]{0,10})","type":"(movie|series)"/', $text, $m, PREG_SET_ORDER))
        return array();
    $out = array();
    foreach ($m as $one)
    {
        if ($one[1] !== '')
            $out[] = "$one[3]|imdb:$one[1]";
        if ($one[2] !== '')
            $out[] = "$one[3]|tmdb:$one[2]";
    }
    return $out;
}

// The index of the copies on the disk: key ("<type>|imdb:<IMDb>", "<type>|tmdb:<TMDB>") => the file of the newest copy
// with it. The keys of a file are read once while it is the same (its mtime and size; mel_cat_cache_keys: no
// json_decode), the files looked at each call (a stat each): the copies of the owner, 240 files of 8 MB, ~3000 tiles -
// all read ~15 ms on a Mac (docker), the Dune ~6 times that, once a php_server; then a stat of each.
/**
 * @return array<string, string>
 */
function mel_cat_cache_index()
{
    $dir = mel_cat_cache_dir();
    $list = $dir !== '' ? glob("$dir/*.json") : array();
    clearstatcache();
    $sigs = array();
    $times = array();
    foreach ($list ? $list : array() as $f)
    {
        $s = @stat($f);
        if (!$s)
            continue;
        $sigs[$f] = $s['mtime'] . ':' . $s['size'];
        $times[$f] = $s['mtime'];
    }
    if ($sigs === MelCatCopies::$sig)
        return MelCatCopies::$map;
    $keys = array();
    foreach ($sigs as $f => $sig)
    {
        $keys[$f] = isset(MelCatCopies::$sig[$f]) && MelCatCopies::$sig[$f] === $sig ? MelCatCopies::$keys[$f] :
            mel_cat_cache_keys(strval(@file_get_contents($f)));
    }
    // The newest copy of a title last: its key is of it.
    asort($times);
    $map = array();
    foreach ($times as $f => $t)
    {
        foreach ($keys[$f] as $k)
            $map[$k] = $f;
    }
    MelCatCopies::$sig = $sigs;
    MelCatCopies::$mtime = $times;
    MelCatCopies::$keys = $keys;
    MelCatCopies::$map = $map;
    return $map;
}

// The tiles of titles $want (key => array(type, IMDb, TMDB, since)) of the copies on the disk (mel_cat_cache_index: by
// IMDb, else by TMDB; the newest copy with it, saved after since by its mtime - 0: any), no request: only the files with
// them decoded, each once (a copy mel_cat_cache_read would not take: none of it), its tile parsed again (mel_cat_tile),
// of the type asked. -> key => tile, of those found.
/**
 * @param array<string, array{string, string, string, int}> $want
 * @return array<string, MelCatTile>
 */
function mel_cat_cache_tiles($want)
{
    $idx = mel_cat_cache_index();
    $files = array();
    foreach ($want as $key => $m)
    {
        list($type, $imdb, $tmdb, $since) = $m;
        foreach (array('imdb' => $imdb, 'tmdb' => $tmdb) as $by => $v)
        {
            if ($v === '' || !isset($idx["$type|$by:$v"]))
                continue;
            $f = $idx["$type|$by:$v"];
            if (MelCatCopies::$mtime[$f] > $since)
                $files[$f][strval($key)] = array($type, $by === 'imdb' ? 'imdb_id' : '_tmdbId', $v);
            break;
        }
    }
    $out = array();
    foreach ($files as $f => $of)
    {
        $j = json_decode(strval(@file_get_contents($f)), true);
        foreach (mel_cat_cache_good($j) ? $j['data'] : array() as $m)
        {
            foreach ($of as $key => $w)
            {
                if (!is_array($m) || mel_str($m, 'type') !== $w[0] || mel_str($m, $w[1]) !== $w[2])
                    continue;
                $t = mel_cat_tile($m, $w[0]);
                if ($t)
                {
                    $t['type'] = $w[0];
                    $out[$key] = $t;
                }
                unset($of[$key]);
            }
        }
    }
    return $out;
}
