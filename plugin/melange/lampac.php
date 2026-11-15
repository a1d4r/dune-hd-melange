<?php
// The client of Lampac ("Онлайн", a source of video besides the releases):
// its address in the settings, the parameters of a title and /externalids,
// the search of the sources (/lite/events, its life mode by /lifeevents) in
// steps of a window's ticks, the replies of the balancers -> rows, "call" of a
// row, the link -> what to play, the key of a row in the watch history. No
// firmware API, no screens: the plugin and the settings page call it. Needs
// common.php (aio_mask_host) and net.php (aio_lp_get); aio_lp_check also
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
//   "turn on streamproxy of the balancer in init.conf"; serial - a series, not
//   in this version; no_link - no link in the reply; long - "the link is too
//   long for Lampac, choose another source"; long_variant - the same inside a
//   playlist; not_hls - a playlist without #EXTM3U; html - a web page instead
//   of a video.

// A stored address longer than this is not one.
define('AIO_LP_URL_MAX', 300);
// Seconds of a connection to Lampac: a hung one is as one not answering.
define('AIO_LP_CONNECT', 2);
// Seconds of one call of a tick (events with life, /lifeevents, /externalids):
// the remote must not hang. /lifeevents waits up to 1.5 s for news itself.
define('AIO_LP_STEP', 3);
// Seconds of the whole search, events without life (the fallback, one call) too.
define('AIO_LP_SEARCH_MAX', 12);
// Seconds of the replies of the balancers, all and each.
define('AIO_LP_REPLIES_MAX', 8);
define('AIO_LP_REPLY_MAX', 3);
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
// Characters of a name of a source or a voice kept, of a balancer in the key.
define('AIO_LP_NAME_MAX', 100);
define('AIO_LP_BAL_MAX', 24);
// Sources of a search and rows of a reply kept, at most.
define('AIO_LP_SOURCES_MAX', 60);
define('AIO_LP_ROWS_MAX', 60);

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

// The address of Lampac as typed or stored: [http(s)://]host[:port][/path][/]
// -> 'scheme://host[:port][/path]' (http when no scheme; scheme and host in
// lower case, no trailing '/'), '' for empty (off), null when it does not fit
// (another scheme, a user, a query, a fragment, a bad port).
/**
 * @param mixed $s
 * @return string|null
 */
function aio_lp_addr($s)
{
    if (!is_string($s))
        return null;
    $s = trim($s, AIO_TRIM);
    if ($s === '')
        return '';
    if (strlen($s) > AIO_LP_URL_MAX ||
        !preg_match('~^(?:(https?)://)?([A-Za-z0-9](?:[A-Za-z0-9.\-]*[A-Za-z0-9])?)(?::([0-9]{1,5}))?((?:/[A-Za-z0-9._\~\-]+)*)/?\z~i',
        $s, $m))
        return null;
    // Groups not matched at the end are not in $m (PHP 5.3).
    list(, $scheme, $host, $port, $path) = array_pad($m, 5, '');
    if ($port !== '' && (intval($port) < 1 || intval($port) > 65535))
        return null;
    return ($scheme !== '' ? strtolower($scheme) : 'http') . '://' . strtolower($host) .
        ($port !== '' ? ':' . intval($port) : '') . $path;
}

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
    return preg_replace('~\b((?:token[a-z_]*|id_file|uid|account_email|memkey)=)[^\s"\'&#]+~i', '$1***', $s);
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
// known: Lampac 1.90.1 takes either) by /externalids of Lampac $base: without
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
    $ask = $p['id'] !== '' ? array('id' => $p['id']) : array('imdb_id' => $p['imdb_id']);
    $r = aio_lp_get("$base/externalids?" . aio_lp_query($ask + array('serial' => $p['serial'])),
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

// A balancer as a key: [a-z0-9_] of its name in lower case, cut; '' for none.
/**
 * @param mixed $s
 * @return string
 */
function aio_lp_bal($s)
{
    return is_string($s) ? (string) substr((string) preg_replace('/[^a-z0-9_]+/', '', strtolower($s)), 0, AIO_LP_BAL_MAX) : '';
}

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
    $bal = aio_lp_bal(isset($o['balanser']) ? $o['balanser'] : '');
    if ($bal === '')
        $bal = aio_lp_bal((string) preg_replace('~[?#].*\z~', '', basename($url)));
    $name = aio_lp_text(isset($o['name']) ? $o['name'] : '');
    $q = '';
    if (preg_match('/^(.*\S)\s+[~\-]\s+([0-9]{3,4}p|[0-9]K)\z/u', $name, $m))
        list($label, $q) = array($m[1], $m[2]);
    else
        $label = $name;
    return array('name' => $name !== '' ? $name : $bal, 'label' => $label !== '' ? $label : $bal, 'q' => $q,
        'url' => $url, 'bal' => $bal, 'index' => isset($o['index']) && is_int($o['index']) ? $o['index'] : 0,
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
    return preg_match('/^[A-Za-z0-9_\-]{1,' . AIO_LP_NAME_MAX . '}\z/', $b) ? $b : '';
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
    if ($base === '' || aio_lp_path("/lite/$path") !== $path || $path === '')
        return null;
    return array('name' => $path, 'label' => ucfirst($path), 'q' => '', 'url' => "$base/lite/$path", 'bal' => aio_lp_bal($path),
        'index' => 0, 'pos' => 0);
}

// Items $list of events, added to the sources $have of the search, $gone its
// items hidden (a key of the item => the reason of aio_lp_source): none
// twice, by link - a life list comes again whole. -> array(the sources by
// index, then by arrival; the items hidden).
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
        else if (!isset($by[$s['url']]) && !isset($gone[$s['url']]) && count($by) < AIO_LP_SOURCES_MAX)
            $by[$s['url']] = $s;
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
// AioLpSource: name, label, q, url, bal, index, pos), gone (items hidden:
// a key => show, rch or bad), hidden (their counts by reason), tasks and pending (of the last /lifeevents), done, capped (the
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
        'tasks' => 0, 'pending' => 0, 'done' => false, 'capped' => false, 'err' => '', 'detail' => '');
    aio_lp_log("search: {$p['id']}/{$p['imdb_id']}/{$p['kinopoisk_id']}, serial {$p['serial']}", $base);
    $r = aio_lp_get("$base/lite/events?" . aio_lp_query($p, array('life' => 'true')), AIO_LP_CONNECT, AIO_LP_STEP,
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
        $st['err'] !== '' ? ", {$st['err']}" . ($st['detail'] !== '' ? " ({$st['detail']})" : '') : '',
        aio_lp_now() - $st['t0']), $st['base']);
    return $st;
}

// One step of search $st (aio_lp_search_start), a tick of the window: life -
// one /lifeevents (up to AIO_LP_STEP s) with what came so far; plain - the one
// events without life (up to the time of the search left). Done at ready, at
// AIO_LP_SEARCH_MAX s from the start (capped), at a failure. -> the new state.
// A done search is returned as it is.
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
        $r = aio_lp_get("{$st['base']}/lite/events?{$st['q']}", AIO_LP_CONNECT, (int) ceil($left), AIO_LP_MAX_BYTES);
        $j = $r['err'] === '' && $r['code'] === 200 ? aio_lp_json($r['body']) : null;
        if ($j !== null && (isset($j[0]) || $j === array() || (isset($j['online']) && is_array($j['online']))))
            return aio_lp_search_end(aio_lp_search_add($st, isset($j['online']) ? $j['online'] : $j));
        list($st['err'], $st['detail']) = aio_lp_events_why($r, $j);
        return aio_lp_search_end($st);
    }
    $r = aio_lp_get("{$st['base']}/lifeevents?" . aio_lp_query(array('memkey' => $st['memkey'],
        'seen' => strval($st['seen']))), AIO_LP_CONNECT, (int) min(AIO_LP_STEP, ceil($left)), AIO_LP_MAX_BYTES);
    $j = $r['err'] === '' && $r['code'] === 200 ? aio_lp_json($r['body']) : null;
    if ($j === null || !isset($j['online']) || !is_array($j['online']))
    {
        list($st['err'], $st['detail']) = aio_lp_events_why($r, $j);
        return aio_lp_search_end($st);
    }
    // online is the whole list so far (Lampac 1.90.1); seen: how many came.
    $st = aio_lp_search_add($st, $j['online']);
    $st['seen'] = max($st['seen'], count($j['online']));
    $st['tasks'] = isset($j['tasks']) && is_int($j['tasks']) ? $j['tasks'] : 0;
    $st['pending'] = isset($j['pending']) && is_array($j['pending']) ? count($j['pending']) : 0;
    if (!empty($j['ready']))
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
    return array('src' => $src['label'], 'bal' => $src['bal'], 'why' => $why, 'detail' => $detail);
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

// The reply ($code, $body) of the balancer of source $src -> array(kind: movie
// (rows), serial (a series: not in this version, a reason), similar (Lampac
// found several titles: skipped, counted), skip (nothing to play, reasons);
// rows; why). A row (AioLpRow): bal, src (its label), voice (translate,
// else voice_name, else ''), quality (maxquality, else the quality of the
// list), method (play: url plays; call: url gives the link, aio_lp_call),
// url (by "quality" when empty), stream (the link of "call" that plays at
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
    foreach ($data as $x)
    {
        if (!is_array($x))
            continue;
        $method = aio_lp_str($x, 'method');
        $qual = aio_lp_quals(isset($x['quality']) ? $x['quality'] : null);
        $raw = isset($x['url']) ? $x['url'] : null;
        $url = $method === 'call' ? aio_lp_link1($raw) : aio_lp_link_ok($raw);
        if ($url === '' && $qual)
            $url = $method === 'call' ? aio_lp_link1(reset($qual)) : reset($qual);
        if ($method !== 'play' && $method !== 'call')
            $why[] = aio_lp_why($src, 'method', aio_lp_text($method));
        else if ($url === '' && aio_lp_too_long($raw) !== '')
            $why[] = aio_lp_why($src, 'long', aio_lp_too_long($raw));
        else if ($url === '')
            $why[] = aio_lp_why($src, 'no_link');
        else if (!empty($x['headers']) && !aio_lp_on_lampac($url, $src, $base))
            $why[] = aio_lp_why($src, 'headers');
        else if (count($rows) < AIO_LP_ROWS_MAX)
        {
            $voice = aio_lp_text(aio_lp_str($x, 'translate'));
            if ($voice === '')
                $voice = aio_lp_text(aio_lp_str($x, 'voice_name'));
            $q = aio_lp_text(aio_lp_str($x, 'maxquality'));
            $rows[] = array('bal' => $src['bal'], 'src' => $src['label'], 'voice' => $voice,
                'quality' => $q !== '' ? $q : $src['q'], 'method' => $method, 'url' => $url,
                'stream' => aio_lp_link_ok(isset($x['stream']) ? $x['stream'] : null), 'qual' => $qual,
                'subs' => isset($x['subtitles']) && is_array($x['subtitles']) ? count($x['subtitles']) : 0,
                'key' => aio_lp_key($src['bal'], $voice));
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

// The replies of sources $sources (of a search) from the $from-th, in turn,
// for title $p (its query aio_lp_query): up to $total s in all (a tick of a
// window asks a part, from the next of the one before), AIO_LP_REPLY_MAX each (Lampac has them in its cache after events: 0.01-0.23
// s). -> array(rows (AioLpRow, by source), why (AioLpWhy), next: the index of
// the first source not asked, left: sources not asked (the screen says "N
// more"), similar, serial: sources that answered so, asked).
/**
 * @param string $base
 * @param list<AioLpSource> $sources
 * @param string $q
 * @param int $from
 * @param int|float $total
 * @return AioLpReplies
 */
function aio_lp_replies($base, $sources, $q, $from = 0, $total = AIO_LP_REPLIES_MAX)
{
    $t0 = aio_lp_now();
    $out = array('rows' => array(), 'why' => array(), 'next' => $from, 'left' => 0, 'similar' => 0, 'serial' => 0,
        'asked' => 0);
    for ($i = $from; $i < count($sources); $i++)
    {
        $left = $total - (aio_lp_now() - $t0);
        if ($left < AIO_LP_REPLY_MIN)
            break;
        $src = $sources[$i];
        $each = (int) min(AIO_LP_REPLY_MAX, floor($left));
        $r = aio_lp_get(aio_lp_url($src['url'], 'rjson=true&' . $q), min(AIO_LP_CONNECT, $each), $each,
            AIO_LP_MAX_BYTES);
        $out['asked']++;
        $out['next'] = $i + 1;
        if ($r['err'] !== '')
        {
            $out['why'][] = aio_lp_why($src, 'down', $r['err']);
            continue;
        }
        $p = aio_lp_reply($src, $r['code'], $r['body'], $base);
        if ($p['kind'] === 'similar')
            $out['similar']++;
        else if ($p['kind'] === 'serial')
            $out['serial']++;
        $out['rows'] = array_merge($out['rows'], $p['rows']);
        $out['why'] = array_merge($out['why'], $p['why']);
    }
    $out['left'] = count($sources) - $out['next'];
    $why = array();
    foreach ($out['why'] as $w)
        $why[] = "{$w['bal']} {$w['why']}" . ($w['detail'] !== '' ? " ({$w['detail']})" : '');
    aio_lp_log(sprintf('replies: %d rows of %d sources, %d not asked, similar: %d, serial: %d%s, %.2f s',
        count($out['rows']), $out['asked'], $out['left'], $out['similar'], $out['serial'],
        $why ? '; skipped: ' . implode(', ', array_slice($why, 0, 20)) : '', aio_lp_now() - $t0), $base);
    return $out;
}

// --- "call" and the link.

// "call" of row link $url (a row of method call; by ENTER only): its reply
// {"method":"play","url":...} -> array(url: the link ('' on a failure), qual:
// quality => link, err: '' or the reason (AioLpWhy codes: down, http, rch,
// accsdb, not_lampac, no_link, method), detail, tries). A failure, an empty
// link or HTTP 416 (Filmix answers it every other time) -> one more try after
// $pause us; not when the failure came from the cache of Lampac
// (X-Invoke-Cache: HIT: it keeps errors) nor for rch and accsdb. $total s for
// both tries.
/**
 * @param string $base
 * @param string $url
 * @param int $total
 * @param int $pause
 * @return AioLpLink
 */
function aio_lp_call($base, $url, $total = AIO_LP_CALL_MAX, $pause = AIO_LP_CALL_PAUSE)
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
        $r = aio_lp_get($url, min(AIO_LP_CONNECT, $left), $left, AIO_LP_MAX_BYTES);
        $j = $r['err'] === '' ? aio_lp_json($r['body']) : null;
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
        else if (aio_lp_str($j, 'method') !== '' && aio_lp_str($j, 'method') !== 'play')
            list($out['err'], $out['detail'], $again) = array('method', aio_lp_text(aio_lp_str($j, 'method')), false);
        else
        {
            $out['qual'] = aio_lp_quals(isset($j['quality']) ? $j['quality'] : null);
            $raw = isset($j['url']) ? $j['url'] : null;
            $out['url'] = aio_lp_link_ok($raw);
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
        $out['detail'] !== '' ? " ({$out['detail']})" : '', $out['tries'], aio_lp_now() - $t0), $base);
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
// "lp-<balancer>-<8 hex of the voice>", e.g. lp-phantom-5f2a91c0. No ':', never
// "m" nor a number; the same for the same balancer and voice (no link or token
// of Lampac in it: those expire).
/**
 * @param string $bal
 * @param string $voice
 * @return string
 */
function aio_lp_key($bal, $voice)
{
    $b = aio_lp_bal($bal);
    return 'lp-' . ($b !== '' ? $b : 'x') . '-' . substr(md5(trim($voice)), 0, 8);
}

// A key of aio_lp_key -> array(balancer, 8 hex), null for another one.
/**
 * @param mixed $key
 * @return array{string, string}|null
 */
function aio_lp_key_parse($key)
{
    if (!is_string($key) || !preg_match('/^lp-([a-z0-9_]{1,' . AIO_LP_BAL_MAX . '})-([0-9a-f]{8})\z/', $key, $m))
        return null;
    return array($m[1], $m[2]);
}

// Row $row is of voice hex $hex (of aio_lp_key_parse): "Continue" finds its voice again.
/**
 * @param AioLpRow $row
 * @param string $hex
 * @return bool
 */
function aio_lp_voice_is($row, $hex)
{
    return substr(md5(trim($row['voice'])), 0, 8) === $hex;
}
