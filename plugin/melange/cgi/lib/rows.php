<?php
// The settings page (cgi/settings.php): the rows of the catalog for its screen #rows.
// Runs the client of the plugin (catalog.php) with what it takes of main.php, which
// needs the firmware. Loaded only for this request (and for the start rows after a
// save, mel_cgi_cat_same). Only reads the cache: the
// manifest older than MEL_CAT_MANIFEST_FRESH is asked again and not kept (mel_cat_cache_dir).

define('MEL_CAT_READ_ONLY', true);
define('MEL_HTTP_TOO_BIG', 'reply over the size limit');
require dirname(dirname(dirname(__FILE__))) . '/parse.php';
require dirname(dirname(dirname(__FILE__))) . '/catalog.php';

$MEL_CGI_DATA = '';

function mel_data_dir()
{
    return $GLOBALS['MEL_CGI_DATA'];
}

function mel_log($msg)
{
    mel_cgi_log($msg);
}

// As mel_http_get of main.php ("curl <errno>:" as there, so catalog.php tells a timeout),
// for the log only the number of a curl error.
function mel_http_get($url, &$err, $connect, $total, $max, &$connected = null)
{
    if (defined('MEL_CGI_TEST_API'))
        $url = str_replace(MEL_CAT_BASE, MEL_CGI_TEST_API . '/cat/', $url);
    $r = mel_cgi_curl($url, array('connect' => $connect, 'total' => $total, 'max' => $max, 'gzip' => true));
    $connected = $r['connected'];
    $err = $r['errno'] !== 0 ? 'curl ' . $r['errno'] . ':' : ($r['cut'] && $r['code'] === 200 ? MEL_HTTP_TOO_BIG : '');
    return array($r['code'], $err !== '' || $r['cut'] ? '' : $r['body']);
}

// Whether the catalog shows the same rows in the same order for the choices $a and $b of cat_rows: the
// slots of each tab (mel_cat_tab_slots) by the copy of the manifest - the start rows for null, the rows of
// Melange without a key where they stand by default. False without a copy: not known. No request.
/**
 * @param string $dir
 * @param list<string>|null $a
 * @param list<string>|null $b
 * @return bool
 */
function mel_cgi_cat_same($dir, $a, $b)
{
    $GLOBALS['MEL_CGI_DATA'] = $dir;
    $c = mel_cat_manifest_copy();
    if (!$c['rows'])
        return false;
    foreach (mel_cat_tabs() as $tab)
    {
        if (mel_cat_tab_slots($c['rows'], $a, $tab) !== mel_cat_tab_slots($c['rows'], $b, $tab))
            return false;
    }
    return true;
}

// JSON {ok: there is a list, stale: it is the last good copy, rows}: of a row only
// what the screen shows, tab: its part of the screen (mel_cat_row_tab). The UUID of the
// catalog never goes out, should the server put it in a name.
function mel_cgi_cat_rows_post($dir)
{
    $GLOBALS['MEL_CGI_DATA'] = $dir;
    $m = mel_cat_manifest();
    $rows = array();
    foreach ($m['rows'] as $r)
        $rows[] = array('key' => $r['key'], 'name' => $r['name'], 'group' => $r['group'], 'home' => $r['home'], 'tab' => mel_cat_row_tab($r));
    if (stripos(json_encode($rows), MEL_CAT_UUID) !== false)
    {
        mel_cgi_log('cat_rows: bad data');
        $rows = array();
    }
    mel_cgi_json(200, array('ok' => (bool) $rows, 'stale' => $rows && $m['stale'], 'rows' => $rows));
}
