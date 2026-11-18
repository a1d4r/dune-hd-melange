<?php
// The client of Lampac ("Онлайн", a source of video besides the releases):
// its address in the settings, the parameters of a title and /externalids,
// the search of the sources (/lite/events, its life mode by /lifeevents) in
// steps of a window's ticks, the replies of the balancers -> rows, "call" of a
// row, the link -> what to play, the key of a row in the watch history. No
// firmware API, no screens: the plugin and the settings page call it. Needs
// common.php (aio_mask_host) and net.php (aio_lp_get, aio_lp_get_many, AIO_HTTP_PAR); aio_lp_check also
// aio_http_range of main.php. A log line only through aio_log, if there is one.
// PHP 5.3.6. Types in the PHPDoc (AioLpSearch and others): phpstan-types.neon.
//
// The reasons (AioLpWhy, why: a code, detail: its detail) go to "Source
// errors" by the screen of the plugin, its text by the code:
//   down - no answer (detail: the curl error); http - HTTP detail; forbidden -
//   403 of events ("check WAF in init.conf"); not_lampac - not JSON or not
//   its format (of events, a balancer, "call"); accsdb - the provider asks to log in (detail: its msg); rch -
//   "only through the Lampa app"; empty - nothing found ({}, no data);
//   type - unknown type (detail); method - unknown method (detail); headers -
//   "turn on streamproxy of the balancer in init.conf"; serial - a series asked
//   without a season; no_season - no such season at the balancer; no_episode -
//   no such episode in the voice (detail: the episodes there are);
//   no_episodes - the voice has no episode of the season; movie - a movie in
//   the list of a serial (aio_lp_replies of a season);
//   season_list - "call" of an episode gave a list, not its link; other_episode -
//   it gave another episode (aio_lp_call_ep); no_voice - the voice of a key of
//   the history is not in the season; bad_key - not a key of a balancer
//   (aio_lp_find); no_link - no link in the reply; long - "the link is too
//   long for Lampac, choose another source"; long_variant - the same inside a
//   playlist; not_hls - a playlist without #EXTM3U; html - a web page instead
//   of a video; blocked - the stub of Filmix "Заблокировано правообладателем!"
//   (aio_lp_blocked).

// Seconds of a connection to Lampac: a hung one is as one not answering.
define('AIO_LP_CONNECT', 2);
// Seconds of one call of a tick (events with life, /lifeevents, /externalids):
// the remote must not hang. /lifeevents waits up to 1.5 s for news itself.
define('AIO_LP_STEP', 3);
// Seconds of the whole search, events without life (the fallback, one call) too.
define('AIO_LP_SEARCH_MAX', 12);
// Seconds of the replies of the balancers: of a search those lost to the balancers of no answer, all of
// "Continue" (aio_lp_find); all of a search while they answer (aio_lp_poll: a server without a cache
// answers in 0.2-4 s each); each.
define('AIO_LP_REPLIES_MAX', 8);
define('AIO_LP_REPLIES_LONG', 24);
define('AIO_LP_REPLY_MAX', 3);
// Seconds of the replies of the balancers in one tick of a window (aio_lp_poll): no request started with less than
// AIO_LP_REPLY_MIN s of it left; a request started is not cut by it (up to AIO_LP_REPLY_MAX s), so a tick may go ~5 s.
define('AIO_LP_TICK_MAX', 3);
// A balancer is not asked with less time left than this (s).
define('AIO_LP_REPLY_MIN', 1);
// Seconds of "call" (Chromium balancers are slow), by default; the pause before its one repeat (us).
define('AIO_LP_CALL_MAX', 20);
define('AIO_LP_CALL_PAUSE', 1000000);
// Bytes of a reply of Lampac at most (VK Видео: 21 variants, ~150 KB).
define('AIO_LP_MAX_BYTES', 1048576);
// A longer link is refused by Kestrel of Lampac (414): not played.
define('AIO_LP_LINK_MAX', 8000);
// Mirrors of a link (" or ") kept at most; a longer value of a link of a reply is
// not parsed (long).
define('AIO_LP_MIRRORS_MAX', 5);
define('AIO_LP_LINKS_MAX', AIO_LP_MIRRORS_MAX * (AIO_LP_LINK_MAX + 4));
// The check of a link: seconds of all its mirrors and of each; bytes of the
// head of a playlist (Collaps: 14 audio groups of ~1.4 KB before the first
// variant).
define('AIO_LP_CHECK_MAX', 12);
define('AIO_LP_CHECK_EACH', 6);
define('AIO_LP_HLS_HEAD', 65536);
// Characters of a name of a source or a voice kept.
define('AIO_LP_NAME_MAX', 100);
// The characters of the path of a balancer (aio_lp_path_ok, the key of the history).
define('AIO_LP_PATH_CHARS', 'A-Za-z0-9_\-');
// Characters of the path of a balancer in the key of the history at most (aio_lp_key).
define('AIO_LP_KEY_PATH_MAX', 24);
// Sources of a search kept, at most.
define('AIO_LP_SOURCES_MAX', 60);
// Rows of a reply and of the online list, voices of a season, kept at most.
define('AIO_LP_ROWS_MAX', 100);
// Sources of lampac_sources at most (aio_lp_srcs_ok): those found on this Dune for all titles (Lampac has some
// tens of balancers), and characters of the name of one: the export of settings.json fits AIO_CGI_BAK_SETTINGS_MAX.
define('AIO_LP_SEEN_MAX', 60);
define('AIO_LP_SEEN_NAME_MAX', 40);
// Tracks of a balancer without voices (details of Collaps) at most.
define('AIO_LP_TRACKS_MAX', 30);
// Episodes of a season of a balancer kept, at most (a long anime has some hundreds).
define('AIO_LP_EPS_MAX', 2000);
// Characters of the episodes there are (aio_lp_ranges), its "…" included: "нет S12E10, есть " and
// they fit a label of a dialog (AIO_ERROR_LABEL of main.php, 48), with the name of a balancer a
// line of the window of the reasons (AIO_ROW_LINE, 68).
define('AIO_LP_RANGES_LEN', 30);
// The User-Agent of "Представляться приложением Lampa": the web Lampa sends that of its browser.
define('AIO_LP_UA',
    'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36');

// What the requests to the Lampac of the settings take (aio_lp_send): token - lampac_token ('' none);
// lampa - lampac_as_lampa; uid - the id of this Dune of it ('' none). aio_op_settings of main.php sets
// it, "Проверить" of the settings page (aio_cgi_lp_check_post) - from the form; unset, nothing is added.
class AioLp
{
    /** @var array{token: string, lampa: bool, uid: string} */
    public static $auth = array('token' => '', 'lampa' => false, 'uid' => '');
}

// The auth of AioLp::$auth of the token $token of the provider ('' none), "as Lampa" $lampa and the uid of this Dune
// $uid (only as Lampa): the one maker of it - the settings of an operation (aio_op_settings), "Проверить" of the page.
/**
 * @param string $token
 * @param bool $lampa
 * @param string $uid
 * @return array{token: string, lampa: bool, uid: string}
 */
function aio_lp_auth($token, $lampa, $uid)
{
    return array('token' => $token, 'lampa' => $lampa, 'uid' => $lampa ? $uid : '');
}

// Tests define their own aio_lp_now() and aio_lp_wait(): the clock of the
// budgets and the pause before a repeat.
if (!function_exists('aio_lp_now'))
{
    /**
     * @return float
     */
    function aio_lp_now()
    {
        return microtime(true);
    }
}
if (!function_exists('aio_lp_wait'))
{
    /**
     * @param int $us
     * @return void
     */
    function aio_lp_wait($us)
    {
        if ($us > 0)
            usleep($us);
    }
}

// --- The address.

// The Lampac of settings $s (lampac_url of settings.json): '' when off or not
// an address.
/**
 * @param array<mixed> $s
 * @return string
 */
function aio_lp_base($s)
{
    $a = aio_lp_addr(isset($s['lampac_url']) ? $s['lampac_url'] : '');
    return is_string($a) ? $a : '';
}

// The version of Lampac of its /version?type=build (without type it sends to
// another site): "1.90.1", '' when the reply is not that.
/**
 * @param string $body
 * @return string
 */
function aio_lp_version($body)
{
    $j = aio_lp_json($body);
    return is_array($j) && isset($j['version']) && is_string($j['version']) &&
        preg_match('/^[0-9]{1,4}(?:\.[0-9]{1,6}){1,3}\z/', $j['version']) ? $j['version'] : '';
}

// --- The requests.

// Link $url as it goes to Lampac $base (AioLp::$auth): to the host:port of $base token= and, as
// Lampa, uid= added when the link has none (Lampac 1.90.1 takes them only from the query and puts
// them in no link of a row, a season, a voice or "call"), the User-Agent of Lampa; to another host
// as it is. Not the link to play nor its check (aio_lp_check): those go as the player asks them. ->
// array(the link, the User-Agent: '' that of Melange).
/**
 * @param string $url
 * @param string $base
 * @return array{string, string}
 */
function aio_lp_send($url, $base)
{
    $a = AioLp::$auth;
    $h = aio_lp_hostport($url);
    if ($h === '' || $h !== aio_lp_hostport($base))
        return array($url, '');
    $q = array();
    if ($a['token'] !== '' && !preg_match('/[?&]token=/i', $url))
        $q['token'] = $a['token'];
    if ($a['lampa'] && $a['uid'] !== '' && !preg_match('/[?&]uid=/i', $url))
        $q['uid'] = $a['uid'];
    return array(aio_lp_url($url, aio_lp_query($q)), $a['lampa'] ? AIO_LP_UA : '');
}

// aio_lp_get of link $url of Lampac $base as aio_lp_send makes it.
/**
 * @param string $base
 * @param string $url
 * @param int $connect
 * @param int $total
 * @param int $max
 * @return AioLpReply
 */
function aio_lp_ask($base, $url, $connect, $total, $max)
{
    list($u, $ua) = aio_lp_send($url, $base);
    return aio_lp_get($u, $connect, $total, $max, $ua);
}

// aio_lp_get_many of links $urls (key => link) of Lampac $base as aio_lp_send makes them, each as aio_lp_ask. ->
// key => array(the reply, s it took); null: no curl_multi ($why: aio_net_multi).
/**
 * @param string $base
 * @param array<int, string> $urls
 * @param int $connect
 * @param int $total
 * @param int $max
 * @param string $why
 * @return array<int, array{AioLpReply, float}>|null
 */
function aio_lp_ask_many($base, $urls, $connect, $total, $max, &$why)
{
    $reqs = array();
    foreach ($urls as $k => $url)
    {
        list($u, $ua) = aio_lp_send($url, $base);
        $reqs[$k] = array($u, $connect, $total, $max, $ua);
    }
    return aio_lp_get_many($reqs, $why);
}

// --- The log.

// $s with the address $base of Lampac as "<lampac>" (of an address with a path
// its scheme://host[:port] and its host alone too: curl errors name the host), the
// links of its proxy and the tokens of the balancers hidden.
/**
 * @param string $s
 * @param string $base
 * @return string
 */
function aio_lp_mask($s, $base)
{
    if ($base !== '')
    {
        $s = aio_mask_host($s, $base, '<lampac>');
        $o = (string) preg_replace('~^([a-z]+://[^/]+).*\z~is', '$1', $base);
        if ($o !== $base)
            $s = aio_mask_host($s, $o, '<lampac>');
    }
    $s = preg_replace('~/proxy/[^\s"\'?&#]+~', '/proxy/***', $s);
    $s = preg_replace('~\b((?:token[a-z_]*|id_file|uid|account_email|memkey)=)[^\s"\'&#]+~i', '$1***', $s);
    // The token and the uid of the settings anywhere (the msg of accsdb may have them), of any length.
    foreach (array(AioLp::$auth['token'], AioLp::$auth['uid']) as $v)
    {
        if ($v !== '')
            $s = str_ireplace(array_unique(array($v, rawurlencode($v), urlencode($v))), '***', $s);
    }
    return $s;
}

// Detail $detail of reason $why for the log, as $fmt makes it ('' none): never the msg of accsdb
// (Lampac 1.90.1 puts the uid or the id of the account in it, Accsdb.cs): the window shows it.
/**
 * @param string $why
 * @param string $detail
 * @param string $fmt
 * @return string
 */
function aio_lp_log_detail($why, $detail, $fmt = '%s')
{
    return $detail === '' || $why === 'accsdb' ? '' : sprintf($fmt, $detail);
}

/**
 * @param string $msg
 * @param string $base
 * @return void
 */
function aio_lp_log($msg, $base)
{
    if (function_exists('aio_log'))
        aio_log('lampac: ' . aio_lp_mask($msg, $base));
}

// --- The title.

// A text of a title: one line, trimmed, cut.
/**
 * @param mixed $v
 * @return string
 */
function aio_lp_text($v)
{
    if (!is_string($v) && !is_int($v))
        return '';
    $v = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $v), AIO_TRIM);
    if (!preg_match('//u', $v))
        return '';
    return mb_strlen($v, 'UTF-8') > AIO_LP_NAME_MAX ? mb_substr($v, 0, AIO_LP_NAME_MAX, 'UTF-8') : $v;
}

// A title -> the parameters of Lampac, in its order. $t: tmdb (TMDB id),
// imdb (tt...), kp (KinoPoisk id), title, original_title, year, serial
// (bool or 0/1), original_language (2-3 letters); any may be missing, a bad
// one is left empty. source is always tmdb.
/**
 * @param array<mixed> $t
 * @return AioLpTitle
 */
function aio_lp_title($t)
{
    $g = function ($k) use ($t)
    {
        return isset($t[$k]) && (is_string($t[$k]) || is_int($t[$k])) ? trim((string) $t[$k]) : '';
    };
    $tmdb = $g('tmdb');
    $imdb = $g('imdb');
    $kp = $g('kp');
    $year = $g('year');
    $lang = strtolower($g('original_language'));
    $serial = isset($t['serial']) && ($t['serial'] === true || $t['serial'] === 1 || $t['serial'] === '1');
    return array('id' => preg_match('/^[1-9][0-9]{0,9}\z/', $tmdb) ? $tmdb : '',
        'imdb_id' => preg_match('/^tt[0-9]{1,10}\z/', $imdb) ? $imdb : '',
        'kinopoisk_id' => preg_match('/^[1-9][0-9]{0,9}\z/', $kp) ? $kp : '',
        'title' => aio_lp_text(isset($t['title']) ? $t['title'] : ''),
        'original_title' => aio_lp_text(isset($t['original_title']) ? $t['original_title'] : ''),
        'serial' => $serial ? '1' : '0',
        'year' => preg_match('/^[12][0-9]{3}\z/', $year) ? $year : '',
        'original_language' => preg_match('/^[a-z]{2,3}\z/', $lang) ? $lang : '',
        'source' => 'tmdb');
}

// The query of parameters $p (and $more after them): "k=v&...", empty ones too.
/**
 * @param array<string, string> $p
 * @param array<string, string> $more
 * @return string
 */
function aio_lp_query($p, $more = array())
{
    $q = array();
    foreach (array_merge($p, $more) as $k => $v)
        $q[] = rawurlencode($k) . '=' . rawurlencode($v);
    return implode('&', $q);
}

// $url with query $q added: after its own query by '&', else by '?'.
/**
 * @param string $url
 * @param string $q
 * @return string
 */
function aio_lp_url($url, $q)
{
    if ($q === '')
        return $url;
    return $url . (strpos($url, '?') === false ? '?' : (substr($url, -1) === '?' || substr($url, -1) === '&' ? '' : '&')) .
        $q;
}

// A decoded JSON object of a body, null when it is not one.
/**
 * @param string $body
 * @return array<mixed>|null
 */
function aio_lp_json($body)
{
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

// The ids the title lacks (no KinoPoisk or IMDb id, a TMDB or an IMDb one
// known: Lampac 1.90.1 takes either; both known - both, as Lampa) by /externalids of Lampac $base: without
// the KinoPoisk id the balancers find almost nothing. -> array(the parameters, '' or the reason of a failure: its
// ids stay as they were). An id it sends is taken only where there is none.
/**
 * @param string $base
 * @param AioLpTitle $p
 * @return array{AioLpTitle, string}
 */
function aio_lp_externalids($base, $p)
{
    if (($p['id'] === '' && $p['imdb_id'] === '') || ($p['kinopoisk_id'] !== '' && $p['imdb_id'] !== ''))
        return array($p, '');
    $t = aio_lp_now();
    $ask = array();
    foreach (array('id', 'imdb_id') as $k)
    {
        if ($p[$k] !== '')
            $ask[$k] = $p[$k];
    }
    $r = aio_lp_ask($base, "$base/externalids?" . aio_lp_query($ask + array('serial' => $p['serial'])),
        AIO_LP_CONNECT, AIO_LP_STEP, 65536);
    $j = $r['err'] === '' && $r['code'] === 200 ? aio_lp_json($r['body']) : null;
    if ($j === null)
    {
        $why = $r['err'] !== '' ? $r['err'] : ($r['code'] !== 200 ? "HTTP {$r['code']}" : 'not JSON');
        aio_lp_log(sprintf('externalids: %s, %.2f s', $why, aio_lp_now() - $t), $base);
        return array($p, $why);
    }
    $new = aio_lp_title(array('imdb' => isset($j['imdb_id']) ? $j['imdb_id'] : '',
        'kp' => isset($j['kinopoisk_id']) ? $j['kinopoisk_id'] : ''));
    foreach (array('imdb_id', 'kinopoisk_id') as $k)
    {
        if ($p[$k] === '')
            $p[$k] = $new[$k];
    }
    aio_lp_log(sprintf('externalids: imdb %s, kp %s, %.2f s', $p['imdb_id'] !== '' ? $p['imdb_id'] : '-',
        $p['kinopoisk_id'] !== '' ? $p['kinopoisk_id'] : '-', aio_lp_now() - $t), $base);
    return array($p, '');
}

// --- The search of the sources.

// One link: http(s), no spaces or control characters, else ''.
/**
 * @param mixed $u
 * @return string
 */
function aio_lp_link1($u)
{
    return is_string($u) && preg_match('~^https?://[^\x00-\x20\x7F]+\z~i', $u) ? $u : '';
}

// A link of a reply to play: its mirrors (aio_lp_links) joined by " or " again,
// '' for none or a value over AIO_LP_LINKS_MAX bytes.
/**
 * @param mixed $u
 * @return string
 */
function aio_lp_link_ok($u)
{
    return is_string($u) && strlen($u) <= AIO_LP_LINKS_MAX ? implode(' or ', aio_lp_links($u)) : '';
}

// A value of a link of a reply over AIO_LP_LINKS_MAX bytes: its length (the
// reason long), '' for another one.
/**
 * @param mixed $u
 * @return string
 */
function aio_lp_too_long($u)
{
    return is_string($u) && strlen($u) > AIO_LP_LINKS_MAX ? strval(strlen($u)) : '';
}

// "host:port" (lower case, the port of the scheme when none) of a link, '' for
// none (a user in it too).
/**
 * @param string $url
 * @return string
 */
function aio_lp_hostport($url)
{
    if (!preg_match('~^(https?)://([^/?#@:]+)(?::([0-9]{1,5}))?(?:[/?#]|\z)~i', $url, $m))
        return '';
    // Groups not matched at the end are not in $m (PHP 5.3).
    list(, $scheme, $host, $port) = array_pad($m, 4, '');
    return strtolower($host) . ':' . ($port !== '' ? intval($port) : (strtolower($scheme) === 'https' ? 443 : 80));
}

// All mirrors of link $url go to Lampac: its address $base (of the settings) or
// the host of source $src (events gives links of its own host). Only there the
// headers of a row are sent (its /proxy/ fetches with them).
/**
 * @param string $url
 * @param AioLpSource $src
 * @param string $base
 * @return bool
 */
function aio_lp_on_lampac($url, $src, $base)
{
    $own = array();
    foreach (array($base, $src['url']) as $b)
    {
        $h = aio_lp_hostport($b);
        if ($h !== '')
            $own[$h] = true;
    }
    $links = aio_lp_links($url);
    foreach ($links as $u)
    {
        if (!isset($own[aio_lp_hostport($u)]))
            return false;
    }
    return (bool) $links;
}

// Item $o of the list of events -> its source, or the reason it is hidden:
// 'show' (show false: the balancer found nothing), 'rch' (rch true: only the
// Lampa app plays it), 'bad' (no link). No show or rch at all: the source is
// tried. The name of the list ("Phantom ~ 2160p" in the life mode): label is
// it without the quality, q the quality.
/**
 * @param mixed $o
 * @param int $pos
 * @return AioLpSource|string
 */
function aio_lp_source($o, $pos)
{
    if (!is_array($o))
        return 'bad';
    if (isset($o['show']) && $o['show'] === false)
        return 'show';
    if (isset($o['rch']) && $o['rch'] === true)
        return 'rch';
    $url = isset($o['url']) && is_string($o['url']) && strlen($o['url']) <= AIO_LP_LINK_MAX ? aio_lp_link1($o['url']) : '';
    if ($url === '')
        return 'bad';
    $path = aio_lp_path($url);
    $name = aio_lp_text(isset($o['name']) ? $o['name'] : '');
    $q = '';
    if (preg_match('/^(.*\S)\s+[~\-]\s+([0-9]{3,4}p|[0-9]K)\z/u', $name, $m))
        list($label, $q) = array($m[1], $m[2]);
    else
        $label = $name;
    return array('name' => $name !== '' ? $name : $path, 'label' => $label !== '' ? $label : $path, 'q' => $q,
        'url' => $url, 'path' => $path, 'index' => isset($o['index']) && is_int($o['index']) ? $o['index'] : 0,
        'pos' => $pos);
}

// The path of a balancer in the link of its source ("phantom" of
// http://host/lite/phantom): what "Continue" asks again without events, '' for none.
/**
 * @param string $url
 * @return string
 */
function aio_lp_path($url)
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    $b = basename($path);
    return aio_lp_path_ok($b, AIO_LP_NAME_MAX) ? $b : '';
}

// $p is a path of a balancer of 1 to $max characters of AIO_LP_PATH_CHARS.
/**
 * @param string $p
 * @param int $max
 * @return bool
 */
function aio_lp_path_ok($p, $max)
{
    return preg_match('/^[' . AIO_LP_PATH_CHARS . ']{1,' . $max . '}\z/', $p) === 1;
}

// A source of balancer path $path (aio_lp_path) on Lampac $base, as events
// would give it (its label the path with a capital: events gives the name):
// "Continue" asks it alone. null for a bad path.
/**
 * @param string $base
 * @param string $path
 * @return AioLpSource|null
 */
function aio_lp_source_of($base, $path)
{
    if ($base === '' || !aio_lp_path_ok($path, AIO_LP_NAME_MAX))
        return null;
    return array('name' => $path, 'label' => ucfirst($path), 'q' => '', 'url' => "$base/lite/$path", 'path' => $path,
        'index' => 0, 'pos' => 0);
}

// The sources Lampac has found on this Dune, for all titles (lampac_sources of settings.json, the window
// "Источники…" of online_sources.php), as stored: in their order, each path (aio_lp_src_path_ok), name
// (aio_lp_src_name; '' - the path, cut so too) and on (not a bool - true); the first of a path kept, at most
// AIO_LP_SEEN_MAX; not a list - none.
/**
 * @param mixed $v
 * @return list<AioLpChoice>
 */
function aio_lp_srcs_ok($v)
{
    $out = array();
    foreach (is_array($v) ? $v : array() as $x)
    {
        $p = is_array($x) && isset($x['path']) && is_string($x['path']) ? $x['path'] : '';
        if (!aio_lp_src_path_ok($p) || isset($out[$p]) || count($out) >= AIO_LP_SEEN_MAX)
            continue;
        $name = aio_lp_src_name(isset($x['name']) ? $x['name'] : '');
        $out[$p] = array('path' => $p, 'name' => $name !== '' ? $name : aio_lp_src_name($p),
            'on' => !isset($x['on']) || $x['on'] !== false);
    }
    return array_values($out);
}

// $p is a path of a source of lampac_sources: of a balancer (aio_lp_path_ok) beginning with a letter or a digit
// (off, "--x" would be the line "---x" of the file of the window, read as its "---").
/**
 * @param string $p
 * @return bool
 */
function aio_lp_src_path_ok($p)
{
    return aio_lp_path_ok($p, AIO_LP_NAME_MAX) && preg_match('/^[A-Za-z0-9]/', $p) === 1;
}

// The name of a source of lampac_sources: aio_lp_text, at most AIO_LP_SEEN_NAME_MAX characters.
/**
 * @param mixed $v
 * @return string
 */
function aio_lp_src_name($v)
{
    $v = aio_lp_text($v);
    return mb_strlen($v, 'UTF-8') > AIO_LP_SEEN_NAME_MAX ? mb_substr($v, 0, AIO_LP_SEEN_NAME_MAX, 'UTF-8') : $v;
}

// Items $list of events, added to the sources $have of the search, $gone its
// items hidden (a key of the item => the reason of aio_lp_source): none
// twice, by link - a life list comes again whole. An item hidden by show false
// that comes with show true later is a source (a server may send all its items
// at every /lifeevents and turn show on as its balancers answer). ->
// array(the sources by index, then by arrival; the items hidden).
/**
 * @param mixed $list
 * @param list<AioLpSource> $have
 * @param array<string, string> $gone
 * @return array{list<AioLpSource>, array<string, string>}
 */
function aio_lp_sources($list, $have, $gone)
{
    $by = array();
    foreach ($have as $s)
        $by[$s['url']] = $s;
    foreach (is_array($list) ? array_values($list) : array() as $o)
    {
        $s = aio_lp_source($o, count($by) + count($gone));
        if (is_string($s))
        {
            $k = is_array($o) && isset($o['url']) && is_string($o['url']) ? $o['url'] : md5(serialize($o));
            if (!isset($by[$k]) && !isset($gone[$k]))
                $gone[$k] = $s;
        }
        else if (!isset($by[$s['url']]) && (!isset($gone[$s['url']]) || $gone[$s['url']] === 'show') &&
            count($by) < AIO_LP_SOURCES_MAX)
        {
            unset($gone[$s['url']]);
            $by[$s['url']] = $s;
        }
    }
    $out = array_values($by);
    usort($out, function ($a, $b)
    {
        return $a['index'] !== $b['index'] ? ($a['index'] < $b['index'] ? -1 : 1) : $a['pos'] - $b['pos'];
    });
    return array($out, $gone);
}

// The reason of a reply of events that is not its list: an error object of
// Lampac (accsdb, rch) or not JSON.
/**
 * @param AioLpReply $r
 * @param array<mixed>|null $j
 * @return array{string, string}
 */
function aio_lp_events_why($r, $j)
{
    if ($r['err'] !== '')
        return array('down', $r['err']);
    if ($r['code'] === 403)
        return array('forbidden', '');
    if (is_array($j) && !empty($j['accsdb']))
        return array('accsdb', aio_lp_text(isset($j['msg']) ? $j['msg'] : ''));
    if ($r['code'] !== 200)
        return array('http', strval($r['code']));
    return array('not_lampac', $j === null ? 'not JSON' : 'no list');
}

// A new search of title $p (aio_lp_title, its ids by aio_lp_externalids) in
// Lampac $base: events in the life mode (Lampac asks the balancers itself,
// its answers come by aio_lp_search_step). -> the state of the search
// (AioLpSearch), kept by the caller between the ticks and given to the next
// step: base, q (the query of the title), mode (life: /lifeevents next; plain:
// one events without life next, Lampac has no life mode; done), memkey, seen
// (items of /lifeevents had), t0 (its start, aio_lp_now), sources (by index;
// AioLpSource: name, label, q, url, path (aio_lp_path of url), index, pos), gone (items hidden:
// a key => show, rch or bad), hidden (their counts by reason), tasks and pending (of the last
// /lifeevents), shown (its items of show not false when all its tasks came and no pending was sent, -1 not so), fails (/lifeevents of no answer in a row), done, capped (the
// time of the search ran out), err and detail (a reason, '' none: the search
// failed only if no source came). One search at a time: two events at once
// make Lampac answer 500.
/**
 * @param string $base
 * @param AioLpTitle $p
 * @return AioLpSearch
 */
function aio_lp_search_start($base, $p)
{
    $st = array('base' => $base, 'q' => aio_lp_query($p), 'mode' => 'life', 'memkey' => '', 'seen' => 0,
        't0' => aio_lp_now(), 'sources' => array(), 'gone' => array(),
        'hidden' => array('show' => 0, 'rch' => 0, 'bad' => 0),
        'tasks' => 0, 'pending' => 0, 'shown' => -1, 'fails' => 0, 'done' => false, 'capped' => false, 'err' => '',
        'detail' => '');
    aio_lp_log("search: {$p['id']}/{$p['imdb_id']}/{$p['kinopoisk_id']}, serial {$p['serial']}", $base);
    $r = aio_lp_ask($base, "$base/lite/events?" . aio_lp_query($p, array('life' => 'true')), AIO_LP_CONNECT, AIO_LP_STEP,
        AIO_LP_MAX_BYTES);
    $j = $r['err'] === '' && $r['code'] === 200 ? aio_lp_json($r['body']) : null;
    if ($j !== null && isset($j['memkey']) && is_string($j['memkey']) && preg_match('/^[A-Za-z0-9_\-]{1,200}\z/', $j['memkey']))
    {
        $st['memkey'] = $j['memkey'];
        aio_lp_log(sprintf('events: life, %.2f s', aio_lp_now() - $st['t0']), $base);
        return $st;
    }
    // A list at once: a Lampac that does not know life answered without it.
    if ($j !== null && (isset($j[0]) || $j === array() || (isset($j['online']) && is_array($j['online']))))
        return aio_lp_search_end(aio_lp_search_add($st, isset($j['online']) ? $j['online'] : $j));
    // Errors and refusals: no point asking without life.
    if ($j === null || !empty($j['accsdb']))
    {
        list($st['err'], $st['detail']) = aio_lp_events_why($r, $j);
        return aio_lp_search_end($st);
    }
    $st['mode'] = 'plain';
    aio_lp_log(sprintf('events: no life, %.2f s', aio_lp_now() - $st['t0']), $base);
    return $st;
}

// Items $list of events added to the sources of search $st.
/**
 * @param AioLpSearch $st
 * @param mixed $list
 * @return AioLpSearch
 */
function aio_lp_search_add($st, $list)
{
    list($st['sources'], $st['gone']) = aio_lp_sources($list, $st['sources'], $st['gone']);
    $n = array_count_values($st['gone']);
    $st['hidden'] = array('show' => isset($n['show']) ? $n['show'] : 0, 'rch' => isset($n['rch']) ? $n['rch'] : 0,
        'bad' => isset($n['bad']) ? $n['bad'] : 0);
    return $st;
}

// Search $st done, logged: sources, the hidden, its time.
/**
 * @param AioLpSearch $st
 * @return AioLpSearch
 */
function aio_lp_search_end($st)
{
    $st['done'] = true;
    $st['mode'] = 'done';
    $h = $st['hidden'];
    aio_lp_log(sprintf('search: %d sources (hidden: show %d, rch %d, bad %d)%s%s, %.2f s', count($st['sources']),
        $h['show'], $h['rch'], $h['bad'], $st['capped'] ? ', out of time' : '',
        $st['err'] !== '' ? ", {$st['err']}" . aio_lp_log_detail($st['err'], $st['detail'], ' (%s)') : '',
        aio_lp_now() - $st['t0']), $st['base']);
    return $st;
}

// One step of search $st (aio_lp_search_start), a tick of the window: life -
// one /lifeevents (up to AIO_LP_STEP s) with what came so far; plain - the one
// events without life (up to the time of the search left). Done at ready, or
// when all its tasks came (online of tasks items: a server may not send ready) -
// with pending, none pending (Lampac); with no pending, the items of show true
// the same at two /lifeevents in a row (a server may send all its items at once
// and turn show on later; then a /lifeevents of no answer in time is the end too),
// at AIO_LP_SEARCH_MAX s from the start (capped), at a
// failure; a /lifeevents of no answer is asked once more at the next step (a
// slow server holds one over 3 s at times), a second one in a row is the
// failure. -> the new state. A done search is returned as it is.
/**
 * @param AioLpSearch $st
 * @return AioLpSearch
 */
function aio_lp_search_step($st)
{
    if ($st['done'])
        return $st;
    $left = AIO_LP_SEARCH_MAX - (aio_lp_now() - $st['t0']);
    if ($left < AIO_LP_REPLY_MIN)
    {
        $st['capped'] = true;
        return aio_lp_search_end($st);
    }
    if ($st['mode'] === 'plain')
    {
        $r = aio_lp_ask($st['base'], "{$st['base']}/lite/events?{$st['q']}", AIO_LP_CONNECT, (int) ceil($left),
            AIO_LP_MAX_BYTES);
        $j = $r['err'] === '' && $r['code'] === 200 ? aio_lp_json($r['body']) : null;
        if ($j !== null && (isset($j[0]) || $j === array() || (isset($j['online']) && is_array($j['online']))))
            return aio_lp_search_end(aio_lp_search_add($st, isset($j['online']) ? $j['online'] : $j));
        list($st['err'], $st['detail']) = aio_lp_events_why($r, $j);
        return aio_lp_search_end($st);
    }
    $r = aio_lp_ask($st['base'], "{$st['base']}/lifeevents?" . aio_lp_query(array('memkey' => $st['memkey'],
        'seen' => strval($st['seen']))), AIO_LP_CONNECT, (int) min(AIO_LP_STEP, ceil($left)), AIO_LP_MAX_BYTES);
    $j = $r['err'] === '' && $r['code'] === 200 ? aio_lp_json($r['body']) : null;
    if ($j === null || !isset($j['online']) || !is_array($j['online']))
    {
        // All came, no pending: a server holds /lifeevents till news (over the time of the call) - the end, no reason.
        if ($st['shown'] >= 0 && aio_lp_timed_out($r['err']))
        {
            aio_lp_log(sprintf('lifeevents: %s, all came, the end, %.2f s', $r['err'], aio_lp_now() - $st['t0']), $st['base']);
            return aio_lp_search_end($st);
        }
        if ($r['err'] !== '' && $st['fails'] < 1)
        {
            $st['fails']++;
            aio_lp_log(sprintf('lifeevents: %s, asked again, %.2f s', $r['err'], aio_lp_now() - $st['t0']), $st['base']);
            return $st;
        }
        list($st['err'], $st['detail']) = aio_lp_events_why($r, $j);
        return aio_lp_search_end($st);
    }
    $st['fails'] = 0;
    // online is the whole list so far (Lampac 1.90.1); seen: how many came.
    $st = aio_lp_search_add($st, $j['online']);
    $st['seen'] = max($st['seen'], count($j['online']));
    $st['tasks'] = isset($j['tasks']) && is_int($j['tasks']) ? $j['tasks'] : 0;
    $st['pending'] = isset($j['pending']) && is_array($j['pending']) ? count($j['pending']) : 0;
    $all = $st['tasks'] > 0 && count($j['online']) >= $st['tasks'];
    $on = 0;
    foreach ($j['online'] as $o)
    {
        if (!is_array($o) || !isset($o['show']) || $o['show'] !== false)
            $on++;
    }
    $was = $st['shown'];
    $hasp = isset($j['pending']) && is_array($j['pending']);
    $st['shown'] = $all && !$hasp ? $on : -1;
    if (!empty($j['ready']) || ($all && ($hasp ? $st['pending'] === 0 : $was === $on)))
        return aio_lp_search_end($st);
    if (AIO_LP_SEARCH_MAX - (aio_lp_now() - $st['t0']) < AIO_LP_REPLY_MIN)
    {
        $st['capped'] = true;
        return aio_lp_search_end($st);
    }
    return $st;
}

// --- The replies of the balancers.

// The text of a field of item $x, '' for none.
/**
 * @param array<mixed> $x
 * @param string $k
 * @return string
 */
function aio_lp_str($x, $k)
{
    return isset($x[$k]) && is_string($x[$k]) ? $x[$k] : '';
}

// A reason of source $src.
/**
 * @param AioLpSource $src
 * @param string $why
 * @param string $detail
 * @return AioLpWhy
 */
function aio_lp_why($src, $why, $detail = '')
{
    return array('src' => $src['label'], 'path' => $src['path'], 'why' => $why, 'detail' => $detail);
}

// The quality links of an item: "2160p" => link, in its order.
/**
 * @param mixed $q
 * @return array<string, string>
 */
function aio_lp_quals($q)
{
    $out = array();
    foreach (is_array($q) ? $q : array() as $k => $u)
    {
        $u = aio_lp_link_ok($u);
        if (is_string($k) && $u !== '' && count($out) < 10)
            $out[aio_lp_text($k)] = $u;
    }
    return $out;
}

// Link $url of an item by its quality links $qual (aio_lp_quals; a quality by aio_lp_q_num: "2160p HEVC"): the
// link of the best quality when $url is there under a quality known below it (Kinoc: url 1080p, also 2160p HEVC);
// else $url - not there, or under a quality not known ("auto": an adaptive one), as it is.
/**
 * @param string $url
 * @param array<string, string> $qual
 * @return string
 */
function aio_lp_qual_best($url, $qual)
{
    $best = '';
    $n = 0;
    $at = 0;
    foreach ($qual as $k => $u)
    {
        $q = aio_lp_q_num($k);
        if ($u === $url)
            $at = max($at, $q);
        if ($q > $n)
            list($n, $best) = array($q, $u);
    }
    return $at > 0 && $at < $n ? $best : $url;
}

// The reply ($code, $body) of the balancer of source $src -> array(kind: movie
// (rows), serial (a series: not in this version, a reason), similar (Lampac
// found several titles: skipped, counted), skip (nothing to play, reasons);
// rows; why). A row (AioLpRow): path (aio_lp_path of the link of $src:
// "Continue" and the mark ask it again; the key has it), src (its label), voice
// (translate, else voice_name, else name not of a quality: some balancers keep the voice there; else ''), quality (maxquality, else the quality of the
// list), method (play: url plays; call: url gives the link, aio_lp_call),
// url (of play by "quality", aio_lp_qual_best; by "quality" when empty), stream (the link of "call" that plays at
// once, '' none), qual (quality => link), subs (subtitles of the item), key
// (aio_lp_key). url, stream and qual may be mirrors (" or "); the link of a
// row of call is one. An item with headers and a link not to Lampac
// (aio_lp_on_lampac: $base, the host of $src) is a reason, not a row: the
// player sends no headers.
/**
 * @param AioLpSource $src
 * @param int $code
 * @param string $body
 * @param string $base
 * @return AioLpParsed
 */
function aio_lp_reply($src, $code, $body, $base = '')
{
    $out = array('kind' => 'skip', 'rows' => array(), 'why' => array());
    $j = aio_lp_json($body);
    if (is_array($j) && !empty($j['rch']))
        $out['why'][] = aio_lp_why($src, 'rch');
    else if (is_array($j) && !empty($j['accsdb']))
        $out['why'][] = aio_lp_why($src, 'accsdb', aio_lp_text(isset($j['msg']) ? $j['msg'] : ''));
    else if ($code !== 200)
        $out['why'][] = aio_lp_why($src, 'http', strval($code));
    else if ($j === null)
        $out['why'][] = aio_lp_why($src, 'not_lampac');
    else if (!$j || !isset($j['type']) || !isset($j['data']) || !is_array($j['data']) || !$j['data'])
        $out['why'][] = aio_lp_why($src, 'empty');
    else if ($j['type'] === 'similar')
        $out['kind'] = 'similar';
    else if ($j['type'] === 'episode' || $j['type'] === 'season')
    {
        $out['kind'] = 'serial';
        $out['why'][] = aio_lp_why($src, 'serial');
    }
    else if ($j['type'] !== 'movie')
        $out['why'][] = aio_lp_why($src, 'type', aio_lp_text(is_string($j['type']) ? $j['type'] : gettype($j['type'])));
    else
        $out = aio_lp_movie($src, $j['data'], $base);
    return $out;
}

// Item $x of data[] of a reply (a movie or an episode) of source $src on Lampac $base: what it plays
// -> array(method: play or call, url ('' with a reason; of call one link, else mirrors), stream,
// qual, subs (its subtitles), why: '' or the reason it is not played (blocked, method, long, no_link,
// headers: aio_lp_reply), detail).
/**
 * @param AioLpSource $src
 * @param array<mixed> $x
 * @param string $base
 * @return array{method: string, url: string, stream: string, qual: array<string, string>, subs: int, why: string, detail: string}
 */
function aio_lp_item($src, $x, $base = '')
{
    $method = aio_lp_str($x, 'method');
    $qual = aio_lp_quals(isset($x['quality']) ? $x['quality'] : null);
    $raw = isset($x['url']) ? $x['url'] : null;
    $url = $method === 'call' ? aio_lp_link1($raw) : aio_lp_link_ok($raw);
    if ($method === 'play')
        $url = aio_lp_qual_best($url, $qual);
    if ($url === '' && $qual)
        $url = $method === 'call' ? aio_lp_link1(reset($qual)) : reset($qual);
    $why = array('', '');
    if (aio_lp_blocked($x))
        $why = array('blocked', '');
    else if ($method !== 'play' && $method !== 'call')
        $why = array('method', aio_lp_text($method));
    else if ($url === '' && aio_lp_too_long($raw) !== '')
        $why = array('long', aio_lp_too_long($raw));
    else if ($url === '')
        $why = array('no_link', '');
    else if (!empty($x['headers']) && !aio_lp_on_lampac($url, $src, $base))
        $why = array('headers', '');
    return array('method' => $method, 'url' => $why[0] === '' ? $url : '',
        'stream' => aio_lp_link_ok(isset($x['stream']) ? $x['stream'] : null), 'qual' => $qual,
        'subs' => isset($x['subtitles']) && is_array($x['subtitles']) ? count($x['subtitles']) : 0,
        'why' => $why[0], 'detail' => $why[1]);
}

// Item $x is the stub of Filmix "Заблокировано правообладателем!" (its translate, voice_name, or title "<the
// title> (Заблокировано правообладателем!)"; case, "!" and spaces aside): a video of the notice, no voice.
/**
 * @param array<mixed> $x
 * @return bool
 */
function aio_lp_blocked($x)
{
    foreach (array('translate', 'voice_name', 'title') as $k)
    {
        $t = preg_replace('/[\s!]+/u', '', mb_strtolower(aio_lp_str($x, $k), 'UTF-8'));
        if (is_string($t) && ($k === 'title' ? strpos($t, '(заблокированоправообладателем)') !== false :
            $t === 'заблокированоправообладателем'))
            return true;
    }
    return false;
}

// Items $data of a reply "movie" of source $src on Lampac $base -> aio_lp_reply.
/**
 * @param AioLpSource $src
 * @param array<mixed> $data
 * @param string $base
 * @return AioLpParsed
 */
function aio_lp_movie($src, $data, $base = '')
{
    $rows = array();
    $why = array();
    // The rows without a voice so far by their quality: their keys (aio_lp_key).
    $mute = array();
    // The voices and links of the rows so far: an item of the same twice is one row.
    $had = array();
    foreach ($data as $x)
    {
        if (!is_array($x))
            continue;
        $it = aio_lp_item($src, $x, $base);
        if ($it['why'] !== '')
            $why[] = aio_lp_why($src, $it['why'], $it['detail']);
        else if (count($rows) < AIO_LP_ROWS_MAX)
        {
            $voice = aio_lp_text(aio_lp_str($x, 'translate'));
            if ($voice === '')
                $voice = aio_lp_text(aio_lp_str($x, 'voice_name'));
            // A name of a quality ("1080p", "4K HDR") is none.
            $name = aio_lp_text(aio_lp_str($x, 'name'));
            if ($voice === '' && aio_lp_q_num($name) === 0)
                $voice = $name;
            if (isset($had[$voice . "\n" . $it['url']]))
                continue;
            $had[$voice . "\n" . $it['url']] = true;
            $q = aio_lp_text(aio_lp_str($x, 'maxquality'));
            $q = $q !== '' ? $q : $src['q'];
            $n = 0;
            if (trim($voice) === '')
            {
                $n = isset($mute[$q]) ? $mute[$q] : 0;
                $mute[$q] = $n + 1;
            }
            $rows[] = array('path' => $src['path'], 'src' => $src['label'],
                'voice' => $voice, 'quality' => $q, 'method' => $it['method'], 'url' => $it['url'],
                'stream' => $it['stream'], 'qual' => $it['qual'], 'subs' => $it['subs'],
                'key' => aio_lp_key($src['path'], $voice, $q, $n));
        }
    }
    // The same reason of several items of a source: once.
    $one = array();
    foreach ($why as $w)
        $one[$w['why'] . "\n" . $w['detail']] = $w;
    if (!$rows && !$one)
        $one[] = aio_lp_why($src, 'empty');
    return array('kind' => $rows ? 'movie' : 'skip', 'rows' => $rows, 'why' => array_values($one));
}

// The replies of sources $srcs (of a search: its index => the source) in their order, for title $q (its query
// aio_lp_query): asked while $total s have not gone (a tick of a window asks a part: aio_lp_poll), AIO_HTTP_PAR at once
// (aio_lp_ask_many; no curl_multi: one by one), each up to AIO_LP_REPLY_MAX s and up to $cap s from the start of the
// call ($cap below $total: $total; the replies of a search left, the tick may go over its part so: a server without a
// cache answers 0.2-4 s; Lampac has them in its cache after events: 0.01-0.23 s). $poll: the poll of a window this call
// is a tick of (aio_lp_poll; null none: no next call) - its held, the replies kept by the call before, by the index of
// the source, not asked again; its budget tells a next call. -> array(rows (AioLpRow, in the order of the sources,
// not of the replies), at (the index of the source of each row), why (AioLpWhy, at: the index of its source), done:
// the sources of $srcs gone through, in their order, left: those not (the screen says "N more"), similar, serial:
// sources that answered so, asked, lost: seconds of the requests of no answer (down; of a batch the time its slowest of
// no answer waited past the slowest that answered), again: the indexes of the sources of no answer in time
// (aio_lp_timed_out: asked once more, aio_lp_poll), held: when no time of the part was left for the season of a series
// and a next call of $poll will have time (aio_lp_poll_next), its reply and those of the sources after it in its batch
// (none: empty) - the next call takes them, its season first, none asked again).
// Season $s > 0 of a serial: a balancer of a series gives its season (aio_lp_serial: one more request, by the link of
// the season, one by one) and its rows a voice (aio_lp_season_rows), a movie the reason movie; $s 0: a series is the
// reason serial, as of a movie.
/**
 * @param string $base
 * @param array<int, AioLpSource> $srcs
 * @param string $q
 * @param int|float $total
 * @param int $s
 * @param int|float $cap
 * @param AioLpPoll|null $poll
 * @return AioLpReplies
 */
function aio_lp_replies($base, $srcs, $q, $total = AIO_LP_REPLIES_MAX, $s = 0, $cap = 0, $poll = null)
{
    $t0 = aio_lp_now();
    $held = $poll !== null ? $poll['held'] : array();
    $cap = max($total, $cap);
    $out = array('rows' => array(), 'at' => array(), 'why' => array(), 'done' => 0, 'left' => 0, 'similar' => 0,
        'serial' => 0, 'asked' => 0, 'lost' => 0.0, 'again' => array(), 'held' => array());
    $keys = array_keys($srcs);
    // curl_multi (none or it failed: one by one, "seq" in the log); the most at once, for the log.
    $multi = true;
    $par = 0;
    // No time for the season of a series: the rest of the call held ($keep: for the next call) or left.
    $hold = false;
    $keep = false;
    $k = 0;
    while (!$hold && $k < count($keys) && $total - (aio_lp_now() - $t0) >= AIO_LP_REPLY_MIN)
    {
        $t1 = aio_lp_now();
        $each = (int) min(AIO_LP_REPLY_MAX, floor($cap - ($t1 - $t0)));
        // The replies held by the call before first, alone (their seasons first), then AIO_HTTP_PAR at once.
        $batch = array();
        for ($j = $k; $j < count($keys) && isset($held[$keys[$j]]); $j++)
            $batch[] = $keys[$j];
        if (!$batch)
            $batch = array_slice($keys, $k, $multi ? AIO_HTTP_PAR : 1);
        $got = array();
        // The links of the batch; those to ask (not held).
        $links = array();
        $urls = array();
        foreach ($batch as $i)
        {
            $links[$i] = aio_lp_url($srcs[$i]['url'], 'rjson=true&' . $q);
            if (isset($held[$i]))
                $got[$i] = array($held[$i], 0.0);
            else
                $urls[$i] = $links[$i];
        }
        if (count($urls) > 1)
        {
            $why = '';
            $many = aio_lp_ask_many($base, $urls, min(AIO_LP_CONNECT, $each), $each, AIO_LP_MAX_BYTES, $why);
            if ($many === null)
            {
                if ($why !== '')
                    aio_lp_log("curl_multi failed ($why), one by one from now on", $base);
                $multi = false;
                continue;
            }
            $got += $many;
            $par = max($par, count($urls));
        }
        foreach (count($urls) === 1 ? $urls : array() as $i => $url)
        {
            $r = aio_lp_ask($base, $url, min(AIO_LP_CONNECT, $each), $each, AIO_LP_MAX_BYTES);
            $got[$i] = array($r, aio_lp_now() - $t1);
        }
        $out['asked'] += count($urls);
        // Lost to no answer: the wait past the slowest that answered (one by one: the time of the one of no answer).
        $down = 0.0;
        $ok = 0.0;
        foreach ($urls as $i => $url)
        {
            if ($got[$i][0]['err'] !== '')
                $down = max($down, $got[$i][1]);
            else
                $ok = max($ok, $got[$i][1]);
        }
        $out['lost'] += max(0.0, $down - $ok);
        foreach ($batch as $i)
        {
            $r = $got[$i][0];
            if ($hold)
            {
                if ($keep)
                    $out['held'][$i] = $r;
                continue;
            }
            $src = $srcs[$i];
            $url = $links[$i];
            if ($r['err'] !== '')
            {
                $out['done']++;
                $out['why'][] = aio_lp_why($src, 'down', $r['err']) + array('at' => $i);
                if (aio_lp_timed_out($r['err']))
                    $out['again'][] = $i;
                continue;
            }
            $p = aio_lp_reply($src, $r['code'], $r['body'], $base);
            if ($p['kind'] === 'movie' && $s > 0)
                $p = array('kind' => 'skip', 'rows' => array(), 'why' => array(aio_lp_why($src, 'movie')));
            if ($p['kind'] === 'serial' && $s > 0)
            {
                // No time of the part left for its season: when a next call of the poll has time, the reply kept for
                // it (and those after it in the batch), no season request on top; else, after a source of this call,
                // it and the rest left (one by one they would not have been asked: "N more"). The first in the call
                // is gone through all the same when it is a kept one or there is no next call (its season of what is
                // left: no time - down, again). A request is not cut by the part (up to AIO_LP_REPLY_MAX s of the
                // time left): a tick may go ~5 s.
                if (($out['done'] > 0 || !isset($held[$i])) && $total - (aio_lp_now() - $t0) < AIO_LP_REPLY_MIN)
                {
                    $keep = $poll !== null && aio_lp_poll_next($poll, $out['lost']);
                    $hold = $keep || $out['done'] > 0;
                    if ($keep)
                        $out['held'][$i] = $r;
                    if ($hold)
                        continue;
                }
                $t2 = aio_lp_now();
                $sea = aio_lp_serial($base, $src, (array) aio_lp_json($r['body']), $url, $s,
                    min(AIO_LP_REPLY_MAX, $cap - (aio_lp_now() - $t0)));
                $p['rows'] = $sea['why'] === '' ? aio_lp_season_rows($src, $sea) : array();
                $p['why'] = $sea['why'] === '' ? array() : array(aio_lp_why($src, $sea['why'], $sea['detail']));
                // The stub of Filmix among the voices of the season: the other voices rows, it the reason.
                if ($sea['why'] === '' && !empty($sea['blocked']))
                    $p['why'][] = aio_lp_why($src, 'blocked');
                if ($sea['why'] === 'down')
                    $out['lost'] += aio_lp_now() - $t2;
                if ($sea['why'] === 'down' && aio_lp_timed_out($sea['detail']))
                    $out['again'][] = $i;
            }
            $out['done']++;
            if ($p['kind'] === 'similar')
                $out['similar']++;
            else if ($p['kind'] === 'serial')
                $out['serial']++;
            $out['rows'] = array_merge($out['rows'], $p['rows']);
            foreach ($p['rows'] as $x)
                $out['at'][] = $i;
            foreach ($p['why'] as $w)
                $out['why'][] = $w + array('at' => $i);
        }
        $k += count($batch);
    }
    $out['left'] = count($srcs) - $out['done'];
    aio_lp_log(sprintf('replies: %d rows of %d sources, %d not asked, similar: %d, serial: %d%s%s%s, %.2f s',
        count($out['rows']), $out['asked'], $out['left'], $out['similar'], $out['serial'], $par > 1 ? ", par $par" : '',
        $multi ? '' : ', seq', $out['why'] ? '; skipped: ' . aio_lp_why_log($out['why'], $base) : '', aio_lp_now() - $t0),
        $base);
    return $out;
}

// --- The poll of the replies of a search (a window, tick by tick): its policy here, the window draws it.

// A new poll of the replies of a view (AioLpView) of $before rows so far ("Ещё N": the rows of before first) ->
// AioLpPoll: t1 (its start, aio_lp_now), lost (seconds of the requests of no answer), again (the indexes of the sources
// of no answer in time, asked once more at the end), held (replies kept for the next tick, aio_lp_replies), at (the
// index of the source of each row of the view, -1 a row of before).
/**
 * @param int $before
 * @return AioLpPoll
 */
function aio_lp_poll_start($before)
{
    return array('t1' => aio_lp_now(), 'lost' => 0.0, 'again' => array(), 'held' => array(),
        'at' => $before > 0 ? array_fill(0, $before, -1) : array());
}

// The seconds of the replies of poll $poll left: AIO_LP_REPLIES_LONG from its start while the balancers answer,
// AIO_LP_REPLIES_MAX lost to those of no answer.
/**
 * @param AioLpPoll $poll
 * @return float
 */
function aio_lp_poll_left($poll)
{
    return min(AIO_LP_REPLIES_LONG - (aio_lp_now() - $poll['t1']), AIO_LP_REPLIES_MAX - $poll['lost']);
}

// Poll $poll has a next tick when its tick now ends with $lost more seconds lost (aio_lp_poll_left).
/**
 * @param AioLpPoll $poll
 * @param float $lost
 * @return bool
 */
function aio_lp_poll_next($poll, $lost)
{
    $poll['lost'] += $lost;
    return aio_lp_poll_left($poll) >= AIO_LP_REPLY_MIN;
}

// One tick of poll $poll of the replies of view $v (its sources, q, rows, why, next) on Lampac $base, season $s (0: a
// movie): the next sources (aio_lp_replies) for up to AIO_LP_TICK_MAX s, a request up to the time left
// (aio_lp_poll_left), a season of no time left in the tick at the next one (held); all asked, those of no answer in time
// (again) once more, the same way. The rows in the order of the sources (aio_lp_rows_by_source), the reasons of no
// answer of the sources asked replaced (by the index of the source, not its path and label: two sources of one path,
// "X ~ 2160p" and "X ~ 1080p", have one label). -> array(the poll, the view, over: all asked or no time left).
/**
 * @param string $base
 * @param AioLpPoll $poll
 * @param AioLpView $v
 * @param int $s
 * @return array{AioLpPoll, AioLpView, bool}
 */
function aio_lp_poll($base, $poll, $v, $s)
{
    $left = aio_lp_poll_left($poll);
    $again = $v['next'] >= count($v['sources']);
    $ask = array();
    if ($left >= AIO_LP_REPLY_MIN)
    {
        foreach ($again ? $poll['again'] : range($v['next'], count($v['sources']) - 1) as $i)
            $ask[$i] = $v['sources'][$i];
    }
    if ($ask)
    {
        if ($again)
            aio_lp_log('asked again: ' . implode(', ', array_map(function ($x) { return $x['path']; }, $ask)), $base);
        $rr = aio_lp_replies($base, $ask, $v['q'], min(AIO_LP_TICK_MAX, $left), $s, $left, $poll);
        $gone = array_flip(array_slice(array_keys($ask), 0, $rr['done']));
        $why = array();
        foreach ($v['why'] as $w)
        {
            if ($w['why'] !== 'down' || !isset($w['at']) || !isset($gone[$w['at']]))
                $why[] = $w;
        }
        $v['why'] = array_merge($why, $rr['why']);
        list($rows, $at) = aio_lp_rows_by_source(array_merge($v['rows'], $rr['rows']), array_merge($poll['at'], $rr['at']));
        $v['rows'] = array_slice($rows, 0, AIO_LP_ROWS_MAX);
        $poll['at'] = array_slice($at, 0, AIO_LP_ROWS_MAX);
        $poll['lost'] += $rr['lost'];
        // Those of no answer when asked again: not a third time.
        if ($again)
            $poll['again'] = array_slice($poll['again'], $rr['done']);
        else
        {
            $v['next'] += $rr['done'];
            $poll['again'] = array_merge($poll['again'], $rr['again']);
        }
        $poll['held'] = $rr['held'];
    }
    return array($poll, $v, ($v['next'] >= count($v['sources']) && !$poll['again']) ||
        aio_lp_poll_left($poll) < AIO_LP_REPLY_MIN);
}

// Rows $rows in the order of their sources, $at the index of the source of each (-1: a row of before, first), those of
// one source in their order (two sources of one path, "Phantom ~ 2160p" and "~ 1080p", stay apart). -> array(the rows,
// their indexes).
/**
 * @param list<AioLpRow> $rows
 * @param list<int> $at
 * @return array{list<AioLpRow>, list<int>}
 */
function aio_lp_rows_by_source($rows, $at)
{
    $by = array();
    foreach ($rows as $i => $r)
        $by[] = array(isset($at[$i]) ? $at[$i] : PHP_INT_MAX, $i);
    sort($by);
    $out = array(array(), array());
    foreach ($by as $x)
    {
        $out[0][] = $rows[$x[1]];
        $out[1][] = $x[0];
    }
    return $out;
}

// Detail $detail of the reason down is no answer in time: the timeout of curl (28), or no time left for the
// request ("no time", aio_lp_season_get) - not a connection refused.
/**
 * @param string $detail
 * @return bool
 */
function aio_lp_timed_out($detail)
{
    return $detail === 'no time' || strpos($detail, 'curl 28:') === 0;
}

// Reasons $why (AioLpWhy) for the log, by code and detail in their order: "down (curl 28: ...):
// spectre, mirage; http (403): vkmovie". Each reason counted, none lost: a detail masked
// (aio_lp_mask of Lampac $base: a cut host is not known to it) and cut to 100 characters, 10 codes
// with details and 20 balancers in all at most, the rest as "+N" (the screen shows no detail: the
// log is where it is).
/**
 * @param list<AioLpWhy> $why
 * @param string $base
 * @return string
 */
function aio_lp_why_log($why, $base)
{
    $by = array();
    foreach ($why as $w)
    {
        $d = aio_lp_mask(aio_lp_log_detail($w['why'], $w['detail']), $base);
        if (mb_strlen($d, 'UTF-8') > 100)
            $d = mb_substr($d, 0, 100, 'UTF-8') . '...';
        $by[$w['why'] . ($d !== '' ? " ($d)" : '')][] = $w['path'] !== '' ? $w['path'] : $w['src'];
    }
    $out = array();
    $names = 0;
    $rest = 0;
    foreach ($by as $k => $bals)
    {
        if (count($out) >= 10)
        {
            $rest += count($bals);
            continue;
        }
        $n = max(0, min(count($bals), 20 - $names));
        $names += $n;
        $out[] = "$k: " . implode(', ', array_slice($bals, 0, $n)) . ($n < count($bals) ? ($n > 0 ? ' ' : '') . '+' .
            (count($bals) - $n) : '');
    }
    return implode('; ', $out) . ($rest > 0 ? "; +$rest more" : '');
}

// --- Serials (spec §5.2, §5.3): a season of a balancer, its voices and episodes.

// A number of a season or an episode of a reply: an int 0-99999 or its digits, -1 for another value.
/**
 * @param mixed $v
 * @return int
 */
function aio_lp_num($v)
{
    if (is_int($v))
        return $v >= 0 && $v <= 99999 ? $v : -1;
    return is_string($v) && preg_match('/^[0-9]{1,5}\z/', $v) ? intval($v) : -1;
}

// The link of season $s in reply $j of a balancer without s (type season: data[] of {method link,
// id, url, name "1 сезон"}): the item of id $s, else the first of a name "<s> ..."; '' for none.
// Lampac gives a season by this link only: AniDub has its page (u=) in it, Filmix its postid, the
// title query with &s=N alone gets 503 from AniDub (Lampac 1.90.1, 16.11.2026).
/**
 * @param array<mixed> $j
 * @param int $s
 * @return string
 */
function aio_lp_season_link($j, $s)
{
    $by_name = '';
    foreach (isset($j['data']) && is_array($j['data']) ? $j['data'] : array() as $x)
    {
        if (!is_array($x))
            continue;
        $u = strlen(aio_lp_str($x, 'url')) <= AIO_LP_LINK_MAX ? aio_lp_link1(aio_lp_str($x, 'url')) : '';
        if ($u === '')
            continue;
        if (aio_lp_num(isset($x['id']) ? $x['id'] : null) === $s)
            return $u;
        if ($by_name === '' && preg_match('/^\s*([0-9]{1,5})(?:\s|\z)/u', aio_lp_text(aio_lp_str($x, 'name')), $m) &&
            intval($m[1]) === $s)
            $by_name = $u;
    }
    return $by_name;
}

// Reply $j of a season (type episode) of source $src on Lampac $base, asked by link $url ->
// AioLpSeason: url ($url), voices (voice[]: name (aio_lp_text), url (its season), active (true
// strictly, of the first such alone); a voice without a name or a link, or of a name twice, left
// out; AIO_LP_ROWS_MAX at most; the stub of Filmix (aio_lp_blocked by its name) left out, blocked
// true), eps (data[]: AioLpEp - s and e (aio_lp_num; -1 s none), name,
// what aio_lp_item gives; an item of no number e or of s and e twice left out, AIO_LP_EPS_MAX at most, in the order of the reply), quality (maxquality
// of the reply, else the best of the quality of its episodes (Vibix), else the quality of the source), why and
// detail '' (aio_lp_season_reply sets them); no voice[]: tracks - by the number of an episode the names of its
// details, once each, AIO_LP_TRACKS_MAX at most (none: no key); no tracks either: voice - the translate of all its
// episodes when one (none: no key), the name of its one row (aio_lp_season_rows).
/**
 * @param AioLpSource $src
 * @param array<mixed> $j
 * @param string $url
 * @param string $base
 * @return AioLpSeason
 */
function aio_lp_season($src, $j, $url, $base = '')
{
    $q = aio_lp_text(aio_lp_str($j, 'maxquality'));
    $out = array('url' => $url, 'voices' => array(), 'eps' => array(), 'quality' => $q !== '' ? $q : $src['q'],
        'why' => '', 'detail' => '');
    $names = array();
    $active = false;
    foreach (isset($j['voice']) && is_array($j['voice']) ? $j['voice'] : array() as $v)
    {
        if (!is_array($v) || count($out['voices']) >= AIO_LP_ROWS_MAX)
            continue;
        $name = aio_lp_text(aio_lp_str($v, 'name'));
        if (aio_lp_blocked(array('voice_name' => $name)))
        {
            $out['blocked'] = true;
            continue;
        }
        $u = strlen(aio_lp_str($v, 'url')) <= AIO_LP_LINK_MAX ? aio_lp_link1(aio_lp_str($v, 'url')) : '';
        if ($name === '' || $u === '' || isset($names[$name]))
            continue;
        $names[$name] = true;
        $on = !$active && isset($v['active']) && $v['active'] === true;
        $active = $active || $on;
        $out['voices'][] = array('name' => $name, 'url' => $u, 'active' => $on);
    }
    $seen = array();
    foreach (isset($j['data']) && is_array($j['data']) ? $j['data'] : array() as $x)
    {
        if (count($out['eps']) >= AIO_LP_EPS_MAX)
            break;
        $e = is_array($x) ? aio_lp_num(isset($x['e']) ? $x['e'] : null) : -1;
        $es = is_array($x) ? aio_lp_num(isset($x['s']) ? $x['s'] : null) : -1;
        // e 0: a special or a trailer, no episode of the card.
        if ($e < 1 || isset($seen["$es:$e"]))
            continue;
        $seen["$es:$e"] = true;
        $out['eps'][] = array('s' => $es, 'e' => $e,
            'name' => aio_lp_text(aio_lp_str($x, 'name'))) + aio_lp_item($src, $x, $base);
    }
    if ($q === '')
    {
        foreach ($out['eps'] as $x)
        {
            foreach (array_keys($x['qual']) as $k)
            {
                if (aio_lp_q_num($k) > aio_lp_q_num($q))
                    $q = $k;
            }
        }
        if ($q !== '')
            $out['quality'] = $q;
    }
    // No voice[] (Collaps): the voices are the tracks of one HLS, named by details of each episode.
    if (!$out['voices'])
    {
        $by = array();
        foreach (isset($j['data']) && is_array($j['data']) ? $j['data'] : array() as $x)
        {
            $e = is_array($x) ? aio_lp_num(isset($x['e']) ? $x['e'] : null) : -1;
            if ($e < 1 || isset($by[$e]) || count($by) >= AIO_LP_EPS_MAX)
                continue;
            $tracks = array();
            foreach (explode(',', aio_lp_str($x, 'details')) as $t)
            {
                $t = aio_lp_text($t);
                if ($t !== '' && count($tracks) < AIO_LP_TRACKS_MAX)
                    $tracks[$t] = true;
            }
            if ($tracks)
                $by[$e] = array_map('strval', array_keys($tracks));
        }
        if ($by)
            $out['tracks'] = $by;
        $one = array();
        foreach ($by ? array() : (isset($j['data']) && is_array($j['data']) ? $j['data'] : array()) as $x)
        {
            if (is_array($x) && aio_lp_num(isset($x['e']) ? $x['e'] : null) >= 1)
                $one[aio_lp_text(aio_lp_str($x, 'translate'))] = true;
        }
        if (count($one) === 1 && key($one) !== '')
            $out['voice'] = strval(key($one));
    }
    return $out;
}

// The number of quality $q ("1080p", "1080p60", "2K", "4K"; by its first word: "2160p HEVC", "4K HDR"): its lines,
// 0 for none. The one rule of a quality of a string: links, the quality of a season, a name as a voice, the badges.
/**
 * @param string $q
 * @return int
 */
function aio_lp_q_num($q)
{
    $w = explode(' ', trim($q));
    $q = $w[0];
    $k = array('2' => 1440, '4' => 2160, '8' => 4320);
    if (preg_match('/^([0-9])K\z/i', $q, $m))
        return isset($k[$m[1]]) ? $k[$m[1]] : 0;
    return preg_match('/^([0-9]{3,4})p(?:[0-9]{2,3})?\z/i', $q, $m) ? intval($m[1]) : 0;
}

// The reply ($code, $body) of season link $url of source $src -> AioLpSeason, why '' or the reason
// (of aio_lp_reply: rch, accsdb, http, not_lampac, empty; type - not an episode list (detail: its
// kind); no_season - a list of seasons again, or episodes of another season $s when $s > 0).
/**
 * @param AioLpSource $src
 * @param int $code
 * @param string $body
 * @param string $url
 * @param string $base
 * @param int $s
 * @return AioLpSeason
 */
function aio_lp_season_reply($src, $code, $body, $url, $base = '', $s = 0)
{
    $p = aio_lp_reply($src, $code, $body, $base);
    $j = aio_lp_json($body);
    if ($p['kind'] !== 'serial' || !is_array($j))
    {
        list($why, $detail) = aio_lp_parsed_why($p);
        return aio_lp_season_no($src, $url, $why, $detail);
    }
    return aio_lp_season_of($src, $j, $url, $base, $s);
}

// A season of source $src (link $url) that is none: no voice nor episode, reason $why, $detail.
/**
 * @param AioLpSource $src
 * @param string $url
 * @param string $why
 * @param string $detail
 * @return AioLpSeason
 */
function aio_lp_season_no($src, $url, $why, $detail)
{
    return array('url' => $url, 'voices' => array(), 'eps' => array(), 'quality' => $src['q'], 'why' => $why,
        'detail' => $detail);
}

// The reason of reply $p (aio_lp_reply) of a serial that is not one: its first reason, else type
// (detail: its kind). -> array(why, detail).
/**
 * @param AioLpParsed $p
 * @return array{string, string}
 */
function aio_lp_parsed_why($p)
{
    return $p['kind'] === 'skip' && $p['why'] ? array($p['why'][0]['why'], $p['why'][0]['detail']) : array('type', $p['kind']);
}

// Reply $j of source $src, a list of seasons or an episode list (aio_lp_season), with why: no_season
// (a list of seasons; episodes all of another season than $s > 0, detail: "s <theirs>"), blocked (its
// voices all the stub of Filmix, aio_lp_blocked), empty (no episode nor voice).
/**
 * @param AioLpSource $src
 * @param array<mixed> $j
 * @param string $url
 * @param string $base
 * @param int $s
 * @return AioLpSeason
 */
function aio_lp_season_of($src, $j, $url, $base, $s)
{
    if (!isset($j['type']) || $j['type'] !== 'episode')
        return aio_lp_season_no($src, $url, 'no_season', strval($s));
    $out = aio_lp_season($src, $j, $url, $base);
    $other = 0;
    foreach ($out['eps'] as $x)
        $other += $s > 0 && $x['s'] >= 0 && $x['s'] !== $s ? 1 : 0;
    if (!$out['voices'] && !empty($out['blocked']))
        $out = aio_lp_season_no($src, $url, 'blocked', '');
    else if (!$out['eps'] && !$out['voices'])
        $out['why'] = 'empty';
    else if ($out['eps'] && $other === count($out['eps']))
        $out = aio_lp_season_no($src, $url, 'no_season', "s {$out['eps'][0]['s']}");
    return $out;
}

// Season link $url of source $src (aio_lp_season_link, a link of voice[]) on Lampac $base, asked
// in up to $total s (AIO_LP_REPLY_MAX at most) -> aio_lp_season_reply; down when no answer.
/**
 * @param string $base
 * @param AioLpSource $src
 * @param string $url
 * @param int|float $total
 * @param int $s
 * @return AioLpSeason
 */
function aio_lp_season_get($base, $src, $url, $total = AIO_LP_REPLY_MAX, $s = 0)
{
    $t0 = aio_lp_now();
    $each = (int) min(AIO_LP_REPLY_MAX, floor($total));
    if (!preg_match('/[?&]rjson=/i', $url))
        $url = aio_lp_url($url, 'rjson=true');
    if ($each < AIO_LP_REPLY_MIN)
        $out = aio_lp_season_no($src, $url, 'down', 'no time');
    else
    {
        $r = aio_lp_ask($base, $url, min(AIO_LP_CONNECT, $each), $each, AIO_LP_MAX_BYTES);
        $out = $r['err'] !== '' ? aio_lp_season_no($src, $url, 'down', $r['err']) :
            aio_lp_season_reply($src, $r['code'], $r['body'], $url, $base, $s);
    }
    aio_lp_log(sprintf('season: %s, %d voices, %d episodes%s, %.2f s', $src['path'], count($out['voices']),
        count($out['eps']), $out['why'] !== '' ? ", {$out['why']}" . aio_lp_log_detail($out['why'], $out['detail'], ' (%s)') :
        '', aio_lp_now() - $t0), $base);
    return $out;
}

// Season $s of source $src from reply $j of its balancer without s (asked by link $url): of a
// list of seasons (type season) - its link (aio_lp_season_link) asked in up to $total s, no
// such season - no_season; maxquality of the list (Filmix) the quality of the source for it (aio_lp_season);
// episodes at once (type episode) - those, if of season $s. -> AioLpSeason.
/**
 * @param string $base
 * @param AioLpSource $src
 * @param array<mixed> $j
 * @param string $url
 * @param int $s
 * @param int|float $total
 * @return AioLpSeason
 */
function aio_lp_serial($base, $src, $j, $url, $s, $total = AIO_LP_REPLY_MAX)
{
    if (isset($j['type']) && $j['type'] === 'episode')
        return aio_lp_season_of($src, $j, $url, $base, $s);
    $link = isset($j['type']) && $j['type'] === 'season' ? aio_lp_season_link($j, $s) : '';
    if ($link === '')
    {
        aio_lp_log("season: {$src['path']}, no season $s", $base);
        return aio_lp_season_no($src, $url, 'no_season', strval($s));
    }
    $q = aio_lp_text(aio_lp_str($j, 'maxquality'));
    if ($q !== '')
        $src['q'] = $q;
    return aio_lp_season_get($base, $src, $link, $total, $s);
}

// The rows of the online list of season $sea of source $src (spec §5.2): one a voice of voice[],
// the active first, then in the order of Lampac; a balancer without voices (Collaps, AniDub) - one,
// voice '' (the voice of the season, aio_lp_season), the tracks of the episodes with it (tracks_by; tracks of one episode: aio_lpv_ep_tracks). A row (AioLpRow) of method "season": url is the link of the season in that voice (of
// the active one, the first alone, $sea['url'], the link asked), no stream, qual or subs, quality of the season, key
// aio_lp_season_key (one for each episode of the voice). Its episode by ENTER: the season of url
// (aio_lp_season_get), aio_lp_episode, aio_lp_ep_row.
/**
 * @param AioLpSource $src
 * @param AioLpSeason $sea
 * @return list<AioLpRow>
 */
function aio_lp_season_rows($src, $sea)
{
    $voices = $sea['voices'] ? $sea['voices'] : array(array('name' => isset($sea['voice']) ? $sea['voice'] : '',
        'url' => $sea['url'], 'active' => true));
    $first = array();
    $rest = array();
    foreach ($voices as $v)
    {
        $on = $v['active'] === true && !$first;
        $row = array('path' => $src['path'], 'src' => $src['label'], 'voice' => $v['name'], 'quality' => $sea['quality'],
            'method' => 'season', 'url' => $on ? $sea['url'] : $v['url'], 'stream' => '', 'qual' => array(),
            'subs' => 0, 'key' => aio_lp_season_key($src['path'], $v['name']));
        if (!$sea['voices'] && !empty($sea['tracks']))
            $row['tracks_by'] = $sea['tracks'];
        if ($on)
            $first[] = $row;
        else
            $rest[] = $row;
    }
    return array_merge($first, $rest);
}

// Episode $e of season $s (0: any) in season $sea of a voice (aio_lp_season_reply) -> array(ep: the
// episode (AioLpEp) or null; why: '' or no_episode (none of that number: detail "<e>, have <its
// numbers>"), no_episodes (no episode of the season at all: detail "<e>"), or the reason of the episode (aio_lp_item: method, long, no_link, headers); detail;
// have: the numbers of the episodes of the season there are, in order, for the screen "нет S1E9,
// есть 1–7" (aio_lp_ranges)). An episode of another season in the reply is not one of $s.
/**
 * @param AioLpSeason $sea
 * @param int $s
 * @param int $e
 * @return array{ep: AioLpEp|null, why: string, detail: string, have: list<int>}
 */
function aio_lp_episode($sea, $s, $e)
{
    $have = array();
    $hit = null;
    foreach ($sea['eps'] as $x)
    {
        if ($s > 0 && $x['s'] >= 0 && $x['s'] !== $s)
            continue;
        $have[] = $x['e'];
        if ($hit === null && $x['e'] === $e)
            $hit = $x;
    }
    sort($have);
    if (!$have)
        return array('ep' => null, 'why' => 'no_episodes', 'detail' => strval($e), 'have' => $have);
    if ($hit === null)
        return array('ep' => null, 'why' => 'no_episode', 'detail' => "$e, have " . aio_lp_ranges($have), 'have' => $have);
    if ($hit['why'] !== '')
        return array('ep' => null, 'why' => $hit['why'], 'detail' => $hit['detail'], 'have' => $have);
    return array('ep' => $hit, 'why' => '', 'detail' => '', 'have' => $have);
}

// Numbers $n (in order) as ranges: "1–7, 9, 11–12" (an en dash); '' for none; up to $len
// characters: the ranges that fit, then "…" (", …" after them).
/**
 * @param list<int> $n
 * @param int $len
 * @return string
 */
function aio_lp_ranges($n, $len = AIO_LP_RANGES_LEN)
{
    $out = '';
    $i = 0;
    $c = count($n);
    while ($i < $c)
    {
        $j = $i;
        while ($j + 1 < $c && $n[$j + 1] <= $n[$j] + 1)
            $j++;
        $one = ($out !== '' ? ', ' : '') . ($n[$i] === $n[$j] ? strval($n[$i]) : "{$n[$i]}\xE2\x80\x93{$n[$j]}");
        if (mb_strlen($out . $one . ($j + 1 < $c ? ', …' : ''), 'UTF-8') > $len)
            return $out . ($out !== '' ? ', …' : '…');
        $out .= $one;
        $i = $j + 1;
    }
    return $out;
}

// Row $row of a voice of a season (aio_lp_season_rows) as the row of its episode $ep: what it plays
// (method, url, stream, qual, subs of the episode), the rest and the key of the row: aio_lpv_link
// and the history take it as a row of a movie.
/**
 * @param AioLpRow $row
 * @param AioLpEp $ep
 * @return AioLpRow
 */
function aio_lp_ep_row($row, $ep)
{
    $row['method'] = $ep['method'];
    $row['url'] = $ep['url'];
    $row['stream'] = $ep['stream'];
    $row['qual'] = $ep['qual'];
    $row['subs'] = $ep['subs'];
    return $row;
}

// The row of episode $e of season $s of history key $key (aio_lp_season_key: the path of the
// balancer and the hex of the voice) for title query $q (aio_lp_query) on Lampac $base, without
// events: "Continue" and the next episode (lampac_next:). $s -1: a movie (a key of aio_lp_key), the
// row of the reply of the hex of the key (aio_lp_voice_is; none: no_voice). The balancer of the path
// alone (aio_lp_source_of): its reply, its season (aio_lp_serial), the voice of the key among
// voice[] of the season (aio_lp_season_rows, by key; no voices: the key of md5("")), the season in
// that voice when it is not the active one, the episode (aio_lp_episode). Up to $total s, up to 3
// requests. -> array(row: the row of the episode (aio_lp_ep_row: method play or call, the key
// $key) or null; why: '' or bad_key (not a key of a balancer, s 0), no_voice (the voice of
// the key is not in the season), a reason of the reply, the season or the episode (down, http,
// type, no_season, no_episode...); detail; have: the episodes of the voice (aio_lp_episode); voice:
// its name, '' none; sea: the season of the voice (the next episodes take it), null without one).
/**
 * @param string $base
 * @param string $key
 * @param string $q
 * @param int $s
 * @param int $e
 * @param int|float $total
 * @return AioLpFound
 */
function aio_lp_find($base, $key, $q, $s, $e, $total = AIO_LP_REPLIES_MAX)
{
    $t0 = aio_lp_now();
    $out = aio_lp_not_found('', '');
    $k = aio_lp_key_parse($key);
    $src = $k !== null && $k[0] !== '' ? aio_lp_source_of($base, $k[0]) : null;
    $each = (int) min(AIO_LP_REPLY_MAX, floor($total));
    if ($src === null || ($s >= 0 && ($s < 1 || $e < 0)))
        $out['why'] = 'bad_key';
    else if ($each < AIO_LP_REPLY_MIN)
        list($out['why'], $out['detail']) = array('down', 'no time');
    else
    {
        $url = aio_lp_url($src['url'], 'rjson=true&' . $q);
        $r = aio_lp_ask($base, $url, min(AIO_LP_CONNECT, $each), $each, AIO_LP_MAX_BYTES);
        $p = $r['err'] === '' ? aio_lp_reply($src, $r['code'], $r['body'], $base) : null;
        if ($p === null)
            list($out['why'], $out['detail']) = array('down', $r['err']);
        else if ($s < 0 && $p['kind'] === 'movie')
        {
            $row = null;
            foreach ($p['rows'] as $x)
            {
                if ($row === null && aio_lp_voice_is($x, $k[1]))
                    $row = $x;
            }
            $out = aio_lp_found($row, null, $s, $e);
        }
        else if ($p['kind'] !== 'serial' || $s < 0)
            list($out['why'], $out['detail']) = aio_lp_parsed_why($p);
        else
        {
            $sea = aio_lp_serial($base, $src, (array) aio_lp_json($r['body']), $url, $s, $total - (aio_lp_now() - $t0));
            $out = aio_lp_voice_ep($base, $src, $key, $sea, $s, $e, $total - (aio_lp_now() - $t0));
        }
    }
    aio_lp_log(sprintf('find: %s S%dE%d: %s%s, %.2f s', $src ? $src['path'] : '-', $s, $e,
        $out['why'] !== '' ? $out['why'] : 'row', aio_lp_log_detail($out['why'], $out['detail'], ' (%s)'),
        aio_lp_now() - $t0), $base);
    return $out;
}

// Episode $e of season $s (aio_lp_serial, aio_lp_season_get of source $src) in the voice of key $key
// (aio_lp_voice_season), in up to $total s -> AioLpFound (aio_lp_find; ENTER of a row of a season).
/**
 * @param string $base
 * @param AioLpSource $src
 * @param string $key
 * @param AioLpSeason $sea
 * @param int $s
 * @param int $e
 * @param int|float $total
 * @return AioLpFound
 */
function aio_lp_voice_ep($base, $src, $key, $sea, $s, $e, $total)
{
    list($row, $sea) = aio_lp_voice_season($base, $src, $key, $sea, $s, $total);
    return aio_lp_found($row, $sea, $s, $e);
}

// Episode $e of season $s in season $sea of the voice of row $row (aio_lp_voice_season: a reason of
// the season, or no row - the reason) -> AioLpFound: the row of the episode (aio_lp_ep_row), its
// reason (aio_lp_episode), the episodes of the voice, the voice, the season. $sea null: $row is
// played as it is, no season (null: no_voice).
/**
 * @param AioLpRow|null $row
 * @param AioLpSeason|null $sea
 * @param int $s
 * @param int $e
 * @return AioLpFound
 */
function aio_lp_found($row, $sea, $s, $e)
{
    if ($sea === null)
        return $row ? array('row' => $row, 'why' => '', 'detail' => '', 'have' => array(), 'voice' => $row['voice'],
            'sea' => null) : aio_lp_not_found('no_voice', '');
    if ($row === null || $sea['why'] !== '')
        return aio_lp_not_found($sea['why'], $sea['detail'], $row ? $row['voice'] : '');
    $f = aio_lp_episode($sea, $s, $e);
    return array('row' => $f['ep'] ? aio_lp_ep_row($row, $f['ep']) : null, 'why' => $f['why'], 'detail' => $f['detail'],
        'have' => $f['have'], 'voice' => $row['voice'], 'sea' => $sea);
}

// No row (AioLpFound) for reason $why, $detail, of voice $voice ('' none known).
/**
 * @param string $why
 * @param string $detail
 * @param string $voice
 * @return AioLpFound
 */
function aio_lp_not_found($why, $detail, $voice = '')
{
    return array('row' => null, 'why' => $why, 'detail' => $detail, 'have' => array(), 'voice' => $voice, 'sea' => null);
}

// Season $sea of source $src (s $s) in the voice of key $key (aio_lp_season_key): its row of
// aio_lp_season_rows and the season of that voice - $sea when its episodes are of it (the active
// voice of the reply; no voices: the one row), else the link of the voice asked in up to $total s.
// The active voice of a reply may be another than that of its link: Lampac makes another one active
// after its cache of 10 min. -> array(the row or null, the season; why no_voice: not in voice[]
// (detail: the number of voices) or another voice active by its link (detail: "active <name>")).
/**
 * @param string $base
 * @param AioLpSource $src
 * @param string $key
 * @param AioLpSeason $sea
 * @param int $s
 * @param int|float $total
 * @return array{AioLpRow|null, AioLpSeason}
 */
function aio_lp_voice_season($base, $src, $key, $sea, $s, $total)
{
    $t0 = aio_lp_now();
    $row = null;
    foreach ($sea['why'] === '' ? aio_lp_season_rows($src, $sea) : array() as $x)
    {
        if ($x['key'] === $key)
        {
            $row = $x;
            break;
        }
    }
    if ($sea['why'] === '' && $row === null)
        return array(null, aio_lp_season_no($src, $sea['url'], 'no_voice', count($sea['voices']) . ' voices'));
    if ($row === null)
        return array(null, $sea);
    $other = aio_lp_voice_other($sea, $row);
    if ($row['url'] !== $sea['url'])
    {
        $sea = aio_lp_season_get($base, $src, $row['url'], $total - (aio_lp_now() - $t0), $s);
        $other = $sea['why'] === '' ? aio_lp_voice_other($sea, $row) : '';
    }
    if ($other !== '')
    {
        aio_lp_log("season: {$src['path']}, the voice of the link not active, $other is", $base);
        $sea = aio_lp_season_no($src, $sea['url'], 'no_voice', "active $other");
    }
    return array($row, $sea);
}

// The name of the voice active in season $sea when it is another than that of row $row ($sea has the
// episodes of that one), '' when it is that of the row or none is active.
/**
 * @param AioLpSeason $sea
 * @param AioLpRow $row
 * @return string
 */
function aio_lp_voice_other($sea, $row)
{
    foreach ($sea['voices'] as $v)
    {
        if ($v['active'] && $v['name'] !== $row['voice'])
            return $v['name'];
    }
    return '';
}

// --- "call" and the link.

// Reply $j of "call" of episode $e of season $s (spec §5.3 p. 4: Phantom in the player of Lampa
// played E1 for E2, VeoVeo always S1E1) -> array(why: '' or season_list (a list of episodes, not
// one link: data[] of more than one item, playlist[], type episode or season) or other_episode (e
// or s of the reply, or "(N серия)" at the end of its title, of another episode; detail: which),
// detail). Lampac 1.90.1 sends none of these in a reply of "call" (Phantom, HDVB, VideoHUB,
// AniDub, 16.11.2026): only a plain case is caught, the episode in the stream is not seen.
/**
 * @param array<mixed> $j
 * @param int $s
 * @param int $e
 * @return array{string, string}
 */
function aio_lp_call_ep($j, $s, $e)
{
    $type = aio_lp_str($j, 'type');
    if ((isset($j['data']) && is_array($j['data']) && count($j['data']) > 1) ||
        (isset($j['playlist']) && is_array($j['playlist'])) || $type === 'episode' || $type === 'season')
        return array('season_list', $type !== '' ? $type : (isset($j['playlist']) ? 'playlist' : 'data'));
    $je = aio_lp_num(isset($j['e']) ? $j['e'] : null);
    $js = aio_lp_num(isset($j['s']) ? $j['s'] : null);
    if ($je >= 0 && $je !== $e)
        return array('other_episode', "e $je");
    if ($s > 0 && $js > 0 && $js !== $s)
        return array('other_episode', "s $js");
    if (preg_match('/\(([0-9]{1,5}) серия\)\s*\z/u', aio_lp_text(aio_lp_str($j, 'title')), $m) && intval($m[1]) !== $e)
        return array('other_episode', "title {$m[1]}");
    return array('', '');
}

// "call" of row link $url (a row of method call; by ENTER only): its reply
// {"method":"play","url":...} -> array(url: the link (by quality, aio_lp_qual_best; '' on a failure), qual:
// quality => link, err: '' or the reason (AioLpWhy codes: down, http, rch,
// accsdb, not_lampac, no_link, method; of an episode $e > 0 of season $s also
// season_list and other_episode, aio_lp_call_ep), detail, tries). A failure, an empty
// link or HTTP 416 (Filmix answers it every other time) -> one more try after
// $pause us; not when the failure came from the cache of Lampac
// (X-Invoke-Cache: HIT: it keeps errors) nor for rch, accsdb and another
// episode. $total s for both tries.
/**
 * @param string $base
 * @param string $url
 * @param int $total
 * @param int $pause
 * @param int $s
 * @param int $e
 * @return AioLpLink
 */
function aio_lp_call($base, $url, $total = AIO_LP_CALL_MAX, $pause = AIO_LP_CALL_PAUSE, $s = 0, $e = 0)
{
    $t0 = aio_lp_now();
    if (!preg_match('/[?&]rjson=/i', $url))
        $url = aio_lp_url($url, 'rjson=true');
    $out = array('url' => '', 'qual' => array(), 'err' => '', 'detail' => '', 'tries' => 0);
    while (true)
    {
        $left = (int) floor($total - (aio_lp_now() - $t0));
        if ($left < AIO_LP_REPLY_MIN)
            break;
        $out['tries']++;
        $r = aio_lp_ask($base, $url, min(AIO_LP_CONNECT, $left), $left, AIO_LP_MAX_BYTES);
        $j = $r['err'] === '' ? aio_lp_json($r['body']) : null;
        $ep = $e > 0 && is_array($j) ? aio_lp_call_ep($j, $s, $e) : array('', '');
        $again = true;
        if ($r['err'] !== '')
            list($out['err'], $out['detail']) = array('down', $r['err']);
        else if (is_array($j) && !empty($j['rch']))
            list($out['err'], $out['detail'], $again) = array('rch', '', false);
        else if (is_array($j) && !empty($j['accsdb']))
            list($out['err'], $out['detail'], $again) = array('accsdb', aio_lp_text(isset($j['msg']) ? $j['msg'] : ''), false);
        else if ($r['code'] !== 200)
            list($out['err'], $out['detail']) = array('http', strval($r['code']));
        else if ($j === null)
            list($out['err'], $out['detail']) = array('not_lampac', 'not JSON');
        else if ($ep[0] !== '')
            list($out['err'], $out['detail'], $again) = array($ep[0], $ep[1], false);
        else if (aio_lp_str($j, 'method') !== '' && aio_lp_str($j, 'method') !== 'play')
            list($out['err'], $out['detail'], $again) = array('method', aio_lp_text(aio_lp_str($j, 'method')), false);
        else
        {
            $out['qual'] = aio_lp_quals(isset($j['quality']) ? $j['quality'] : null);
            $raw = isset($j['url']) ? $j['url'] : null;
            $out['url'] = aio_lp_qual_best(aio_lp_link_ok($raw), $out['qual']);
            if ($out['url'] === '' && $out['qual'])
                $out['url'] = reset($out['qual']);
            if ($out['url'] === '' && aio_lp_too_long($raw) !== '')
                list($out['err'], $out['detail'], $again) = array('long', aio_lp_too_long($raw), false);
            else
                list($out['err'], $out['detail']) = $out['url'] !== '' ? array('', '') : array('no_link', '');
        }
        if ($out['err'] === '' || !$again || $r['hit'] || $out['tries'] > 1)
            break;
        aio_lp_wait($pause);
    }
    if ($out['tries'] === 0)
        list($out['err'], $out['detail']) = array('down', 'no time');
    aio_lp_log(sprintf('call: %s%s, %d tries, %.2f s', $out['err'] !== '' ? $out['err'] : 'link',
        aio_lp_log_detail($out['err'], $out['detail'], ' (%s)'), $out['tries'], aio_lp_now() - $t0), $base);
    return $out;
}

// The mirrors of a link: its parts joined by " or ", in order, those that are
// links, the first AIO_LP_MIRRORS_MAX parts only.
/**
 * @param string $url
 * @return list<string>
 */
function aio_lp_links($url)
{
    $out = array();
    foreach (array_slice(explode(' or ', $url, AIO_LP_MIRRORS_MAX + 1), 0, AIO_LP_MIRRORS_MAX) as $u)
    {
        $u = aio_lp_link1(trim($u));
        if ($u !== '')
            $out[] = $u;
    }
    return $out;
}

// The first variant of master playlist $body ('' none in it) as the player
// asks it: relative to the playlist $url.
/**
 * @param string $url
 * @param string $body
 * @return string
 */
function aio_lp_variant($url, $body)
{
    $inf = false;
    foreach (preg_split('/\r?\n/', $body) as $line)
    {
        $line = trim($line);
        if (strpos($line, '#EXT-X-STREAM-INF') === 0)
            $inf = true;
        else if ($inf && $line !== '' && $line[0] !== '#')
        {
            if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $line))
                return $line;
            if ($line[0] === '/')
                return (string) preg_replace('~^([a-z]+://[^/]+).*\z~is', '$1', $url) . $line;
            return (string) preg_replace('~[^/?#]*(?:[?#].*)?\z~s', '', $url) . $line;
        }
    }
    return '';
}

// The verdict of a head of a link: what $url ($code, the first bytes $body)
// is -> array(class: stream, bad or unclear (the check tells nothing: no
// answer), why (AioLpWhy codes: long_variant, not_hls, html, http, down;
// '' for stream), detail, hls, no_seek: a file that answered 200 to a Range
// (rewinding may not work)).
/**
 * @param string $url
 * @param int $code
 * @param string $body
 * @param bool $hls
 * @return AioLpCheck
 */
function aio_lp_head_class($url, $code, $body, $hls)
{
    $out = array('class' => 'bad', 'url' => $url, 'why' => '', 'detail' => '', 'hls' => $hls, 'no_seek' => false);
    $b = ltrim(substr($body, 0, 3) === "\xEF\xBB\xBF" ? substr($body, 3) : $body);
    if ($code !== 200 && $code !== 206)
        list($out['why'], $out['detail']) = array('http', strval($code));
    else if ($hls && strpos($b, '#EXTM3U') !== 0)
        $out['why'] = $b !== '' && $b[0] === '<' ? 'html' : 'not_hls';
    else if (!$hls && $b !== '' && $b[0] === '<')
        $out['why'] = 'html';
    else if ($hls && strlen(aio_lp_variant($url, $body)) > AIO_LP_LINK_MAX)
        list($out['why'], $out['detail']) = array('long_variant', strval(strlen(aio_lp_variant($url, $body))));
    else
        list($out['class'], $out['no_seek']) = array('stream', !$hls && $code === 200);
    return $out;
}

// The check of link $url before the player (by ENTER only): its mirrors (" or
// ") in turn, the first that plays wins. A mirror over AIO_LP_LINK_MAX bytes
// is not asked (long). HLS (.m3u8 in the path, or a head of "#"): GET of its
// first AIO_LP_HLS_HEAD bytes, 200/206 and #EXTM3U, the first variant of a
// master playlist not longer either; a file: Range 0-0, 206, or 200 with
// no_seek. -> aio_lp_head_class of the mirror that plays; else of the first
// unclear one (no answer: the caller may play it, as aio_precheck), else of
// the first one. $total s for all: with no time left the rest is not tried.
/**
 * @param string $base
 * @param string $url
 * @param int $total
 * @return AioLpCheck
 */
function aio_lp_check($base, $url, $total = AIO_LP_CHECK_MAX)
{
    $t0 = aio_lp_now();
    $first = null;
    $unclear = null;
    $links = aio_lp_links($url);
    foreach ($links as $i => $u)
    {
        $left = (int) floor($total - (aio_lp_now() - $t0));
        $stop = false;
        if (strlen($u) > AIO_LP_LINK_MAX)
            $c = array('class' => 'bad', 'url' => $u, 'why' => 'long', 'detail' => strval(strlen($u)), 'hls' => false,
                'no_seek' => false);
        else if ($left < AIO_LP_REPLY_MIN)
        {
            $c = array('class' => 'unclear', 'url' => $u, 'why' => 'down', 'detail' => 'no time', 'hls' => false,
                'no_seek' => false);
            $stop = true;
        }
        else
        {
            $each = min(AIO_LP_CHECK_EACH, $left);
            $hls = (bool) preg_match('~\.m3u8?(?:[?#]|\z)~i', $u);
            $err = '';
            list($code, $body) = aio_http_range($u, $hls ? AIO_LP_HLS_HEAD : 1, $err, min(AIO_LP_CONNECT, $each), $each);
            // A file of "#": a playlist after all (a link without .m3u8, a redirect to one).
            if (!$hls && $err === '' && $body === '#')
            {
                $hls = true;
                list($code, $body) = aio_http_range($u, AIO_LP_HLS_HEAD, $err, min(AIO_LP_CONNECT, $each), $each);
            }
            if ($err !== '' && $code === 0)
                $c = array('class' => 'unclear', 'url' => $u, 'why' => 'down', 'detail' => $err, 'hls' => $hls,
                    'no_seek' => false);
            else
                $c = aio_lp_head_class($u, $code, $body, $hls);
        }
        aio_lp_log(sprintf('check %d/%d: %s%s%s, %d bytes', $i + 1, count($links), $c['class'],
            $c['why'] !== '' ? " {$c['why']}" : '', $c['detail'] !== '' ? " ({$c['detail']})" : '', strlen($u)), $base);
        if ($c['class'] === 'stream')
            return $c;
        if ($c['class'] === 'unclear' && $unclear === null)
            $unclear = $c;
        if ($first === null)
            $first = $c;
        if ($stop)
            break;
    }
    if ($unclear !== null)
        return $unclear;
    return $first !== null ? $first : array('class' => 'bad', 'url' => '', 'why' => 'no_link', 'detail' => '',
        'hls' => false, 'no_seek' => false);
}

// --- The watch history.

// The key of a row in the uid of the watch history ("aio:<dune_id>:<s>:<e>:<key>"):
// "lp-<path>-<8 hex of the voice>", e.g. lp-phantom-5f2a91c0, the path of the
// balancer as aio_lp_path ("Continue" and the mark ask it again; not the field
// "balanser" of events): no ':', may have '-', the hex is the last 8 after the
// last '-'. Never "m" nor a number; the same for the same balancer and voice (no
// link or token of Lampac in it: those expire). A bad path or one longer than
// AIO_LP_KEY_PATH_MAX: "lp--<hex>", no balancer to ask. No voice: the 8 hex of
// quality $q and $n, the number of the row among those of the balancer without a
// voice of that quality (three Phantom rows without one: three keys, the same at
// the next reply). A row of a movie only: an episode takes aio_lp_season_key.
/**
 * @param string $path
 * @param string $voice
 * @param string $q
 * @param int $n
 * @return string
 */
function aio_lp_key($path, $voice, $q = '', $n = 0)
{
    $p = aio_lp_path_ok($path, AIO_LP_KEY_PATH_MAX) ? $path : '';
    $v = trim($voice);
    return "lp-$p-" . substr(md5($v !== '' ? $v : "\n" . trim($q) . "\n$n"), 0, 8);
}

// The key of an episode of a serial (spec §7): as aio_lp_key, the 8 hex of $voice, the name of the
// voice of the season (aio_lp_text of the name of the item of voice[] the episodes of the reply are
// of, the active one), not "translate" of the episode; '' for a serial without voices: md5("").
// One key for each episode of that voice of the season (no $q, no number of a row): the next
// episode (lampac_next:) and "Continue" take the hex of the key (aio_lp_key_parse) and find the
// voice again among voice[] of the season by aio_lp_season_key of each name, e.g.
// aio:<dune_id>:1:3:lp-phantom-5f2a91c0; no link or t=<id> of the voice in the key. A voice of the
// same name in a movie: the same hex.
/**
 * @param string $path
 * @param string $voice
 * @return string
 */
function aio_lp_season_key($path, $voice)
{
    $p = aio_lp_path_ok($path, AIO_LP_KEY_PATH_MAX) ? $path : '';
    return "lp-$p-" . substr(md5(trim($voice)), 0, 8);
}

// A key of aio_lp_key or aio_lp_season_key -> array(path of the balancer, '' none; 8 hex), null for
// another one.
/**
 * @param mixed $key
 * @return array{string, string}|null
 */
function aio_lp_key_parse($key)
{
    if (!is_string($key) || !preg_match('/^lp-([' . AIO_LP_PATH_CHARS . ']{0,' . AIO_LP_KEY_PATH_MAX . '})-([0-9a-f]{8})\z/',
        $key, $m))
        return null;
    return array($m[1], $m[2]);
}

// Row $row is of voice hex $hex (of aio_lp_key_parse): "Continue" finds its voice (or its row
// without one) again.
/**
 * @param AioLpRow $row
 * @param string $hex
 * @return bool
 */
function aio_lp_voice_is($row, $hex)
{
    $k = aio_lp_key_parse($row['key']);
    return $k !== null && $k[1] === $hex;
}
