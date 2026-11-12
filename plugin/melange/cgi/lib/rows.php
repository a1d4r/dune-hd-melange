<?php
// The settings page (cgi/settings.php): the rows of the catalog for its screen #rows.
// Runs the client of the plugin (catalog.php) with what it takes of main.php, which
// needs the firmware. Loaded only for this request (and for the start rows after a
// save, aio_cgi_cat_start). Only reads the cache: the
// manifest older than AIO_CAT_MANIFEST_FRESH is asked again and not kept (aio_cat_cache_dir).

define('AIO_CAT_READ_ONLY', true);
define('AIO_HTTP_TOO_BIG', 'reply over the size limit');
require dirname(dirname(dirname(__FILE__))) . '/parse.php';
require dirname(dirname(dirname(__FILE__))) . '/catalog.php';

$AIO_CGI_DATA = '';

function aio_data_dir()
{
    return $GLOBALS['AIO_CGI_DATA'];
}

function aio_log($msg)
{
    aio_cgi_log($msg);
}

// As aio_http_get of main.php ("curl <errno>:" as there, so catalog.php tells a timeout),
// for the log only the number of a curl error.
function aio_http_get($url, &$err, $connect, $total, $max, &$connected = null)
{
    if (defined('AIO_CGI_TEST_API'))
        $url = str_replace(AIO_CAT_BASE, AIO_CGI_TEST_API . '/cat/', $url);
    $r = aio_cgi_curl($url, array('connect' => $connect, 'total' => $total, 'max' => $max, 'gzip' => true));
    $connected = $r['connected'];
    $err = $r['errno'] !== 0 ? 'curl ' . $r['errno'] . ':' : ($r['cut'] && $r['code'] === 200 ? AIO_HTTP_TOO_BIG : '');
    return array($r['code'], $err !== '' || $r['cut'] ? '' : $r['body']);
}

// The keys of the start rows as the catalog shows them with nothing chosen (aio_cat_user_rows)
// and the page posts them: of movies, then of series; null: no copy (or no such row). No request.
/**
 * @param string $dir
 * @return list<string>|null
 */
function aio_cgi_cat_start($dir)
{
    $GLOBALS['AIO_CGI_DATA'] = $dir;
    $c = aio_cat_manifest_copy();
    $keys = array();
    foreach (aio_cat_tabs() as $tab)
    {
        foreach (aio_cat_user_rows($c['rows'], null, $tab) as $r)
            $keys[] = $r['key'];
    }
    return $keys ? $keys : null;
}

// JSON {ok: there is a list, stale: it is the last good copy, rows}: of a row only
// what the screen shows, tab: its part of the screen (aio_cat_row_tab). The UUID of the
// catalog never goes out, should the server put it in a name.
function aio_cgi_cat_rows_post($dir)
{
    $GLOBALS['AIO_CGI_DATA'] = $dir;
    $m = aio_cat_manifest();
    $rows = array();
    foreach ($m['rows'] as $r)
        $rows[] = array('key' => $r['key'], 'name' => $r['name'], 'group' => $r['group'], 'home' => $r['home'], 'tab' => aio_cat_row_tab($r));
    if (stripos(json_encode($rows), AIO_CAT_UUID) !== false)
    {
        aio_cgi_log('cat_rows: bad data');
        $rows = array();
    }
    aio_cgi_json(200, array('ok' => (bool) $rows, 'stale' => $rows && $m['stale'], 'rows' => $rows));
}
