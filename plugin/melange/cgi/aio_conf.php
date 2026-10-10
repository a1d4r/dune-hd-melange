<?php
// The AIOStreams config of the settings page (0.35.0): the template
// (aio_template.json, next to this file) and the fields melange owns in a
// config. The rest of a config (manual edits of the user in the web
// settings of AIOStreams) is never touched. Configs are objects
// (json_decode without assoc): an empty {} stays {}, never [] (Zod of the
// server refuses [] for a record such as credentials). Plain PHP 5.3, no
// firmware API, no request.

// instanceId of the JacRed preset of the template.
define('AIO_CONF_PRESET', 'melange');
define('AIO_CONF_TEMPLATE', 'aio_template.json');

// PHP on Dune is 32-bit and json_decode of 5.3 clamps big integers to
// 2147483647: numbers of 10+ digits (sizes in bytes of the filters) pass
// as strings with this mark ("\u0001big:" in JSON) and come back as they were.
function aio_conf_big_in($m)
{
    return $m[0][0] === '"' ? $m[0] : '"\\u0001big:' . $m[0] . '"';
}

// PCRE of PHP 5.3 has no JIT: a string with ~20 000 escapes overflows the
// stack (SIGSEGV) at the default recursion limit. With this one preg gives
// up (null) long before: such a reply is "not JSON".
define('AIO_CONF_PCRE_DEPTH', 3000);

// JSON text -> objects and arrays (null when not JSON); big numbers marked.
function aio_conf_decode($s)
{
    $old = ini_get('pcre.recursion_limit');
    ini_set('pcre.recursion_limit', AIO_CONF_PCRE_DEPTH);
    $t = preg_replace_callback('/"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"|' .
        '(?<![0-9.eE+\-])-?[0-9]{10,}(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', 'aio_conf_big_in', (string) $s);
    ini_set('pcre.recursion_limit', $old);
    return is_string($t) ? json_decode($t) : null;
}

// A value of aio_conf_decode -> JSON text, the big numbers back as numbers.
function aio_conf_encode($v)
{
    return preg_replace('/"\\\\u0001big:(-?[0-9][0-9.eE+\-]*)"/', '$1', json_encode($v));
}

// The template $id (a key of aio_aio_tpls of common.php: the file with only
// its presets) as an object, null when unreadable or no such template.
function aio_conf_template($id = 'addons')
{
    $tpls = aio_aio_tpls();
    $j = aio_conf_decode(@file_get_contents(dirname(__FILE__) . '/' . AIO_CONF_TEMPLATE));
    if (!is_object($j) || !is_string($id) || !array_key_exists($id, $tpls))
        return null;
    if ($tpls[$id] !== null && isset($j->presets) && is_array($j->presets))
    {
        $keep = array();
        foreach ($j->presets as $x)
        {
            if (is_object($x) && isset($x->instanceId) && in_array($x->instanceId, $tpls[$id], true))
                $keep[] = $x;
        }
        $j->presets = $keep;
    }
    return $j;
}

// instanceIds of the presets of a config, sorted; null when not a list of
// presets each with a string instanceId.
function aio_conf_preset_ids($c)
{
    if (!is_object($c) || !isset($c->presets) || !is_array($c->presets))
        return null;
    $ids = array();
    foreach ($c->presets as $x)
    {
        if (!is_object($x) || !isset($x->instanceId) || !is_string($x->instanceId))
            return null;
        $ids[] = $x->instanceId;
    }
    sort($ids, SORT_STRING);
    return $ids;
}

// The template of a config as it is on the server (0.40.0; before
// aio_conf_patch, which brings back a removed JacRed preset): its set of
// presets is exactly that of a template -> its id, else 'custom'. Enabled
// or not, the order: not looked at.
function aio_conf_tpl_of($c)
{
    $ids = aio_conf_preset_ids($c);
    foreach (array_keys(aio_aio_tpls()) as $id)
    {
        if ($ids !== null && $ids === aio_conf_preset_ids(aio_conf_template($id)))
            return $id;
    }
    return 'custom';
}

// Torznab endpoint of a JacRed base (no trailing slash).
function aio_conf_torznab($base)
{
    return "$base/api/v2.0/indexers/all/results/torznab/api";
}

function aio_conf_copy($o)
{
    return json_decode(json_encode($o));
}

// $o->$k as an object, made one if it is not.
function aio_conf_obj($o, $k)
{
    if (!isset($o->$k) || !is_object($o->$k))
        $o->$k = new stdClass();
    return $o->$k;
}

// The item of the list $list with $field === $id, or null.
function aio_conf_find($list, $field, $id)
{
    foreach ($list as $x)
    {
        if (is_object($x) && isset($x->$field) && $x->$field === $id)
            return $x;
    }
    return null;
}

// A non-empty string at $o->$k.
function aio_conf_str($o, $k)
{
    return isset($o->$k) && is_string($o->$k) && $o->$k !== '';
}

// The config has a service turned on with a credential (ours or set by hand
// in the web settings): without one the server refuses it (Torznab).
function aio_conf_has_debrid($c)
{
    foreach (isset($c->services) && is_array($c->services) ? $c->services : array() as $svc)
    {
        if (!is_object($svc) || !isset($svc->enabled) || $svc->enabled !== true || !isset($svc->credentials) ||
            !is_object($svc->credentials))
            continue;
        foreach (get_object_vars($svc->credentials) as $v)
        {
            if (is_string($v) && $v !== '')
                return true;
        }
    }
    return false;
}

// Our fields of the config $c (the template or userData of GET), changed in
// place; $tpl: the template (the preset back from it). $p: rd, tb - the
// keys ('' none); jr_url, jr_key - the Torznab endpoint and key ('' none) of
// the chosen JacRed; tmdb_key - the key to send ('' none); tmdb_server - the
// server has TMDB of its own; tmdb_prev - TMDB at the last change (null:
// a new config); set - of rd, tb, tmdb those melange put in the config. A
// key of melange is put (and marked); none -> the field is cleared only if
// melange put it, else left as the user set it in the web settings.
// -> array(whether the config has TMDB now, set now).
function aio_conf_patch($c, $tpl, $p)
{
    $set = array();
    if (!isset($c->services) || !is_array($c->services))
        $c->services = array();
    foreach (array('rd' => 'realdebrid', 'tb' => 'torbox') as $k => $id)
    {
        $key = $p[$k];
        $svc = aio_conf_find($c->services, 'id', $id);
        if ($key !== '')
        {
            if (!$svc)
                $c->services[] = $svc = (object) array('id' => $id);
            $svc->enabled = true;
            aio_conf_obj($svc, 'credentials')->apiKey = $key;
            $set[] = $k;
        }
        elseif ($svc && in_array($k, $p['set'], true))
        {
            $svc->enabled = false;
            $svc->credentials = new stdClass();
        }
    }
    if (!isset($c->presets) || !is_array($c->presets))
        $c->presets = array();
    $pr = aio_conf_find($c->presets, 'instanceId', AIO_CONF_PRESET);
    if (!$pr)
    {
        // Deleted in the web settings: back from the template, with its id
        // in the matching (the server dropped it from there).
        $c->presets[] = $pr = aio_conf_copy(aio_conf_find($tpl->presets, 'instanceId', AIO_CONF_PRESET));
        foreach (array('titleMatching', 'yearMatching') as $k)
        {
            $m = aio_conf_obj($c, $k);
            if (!isset($m->addons) || !is_array($m->addons))
                $m->addons = array();
            if (!in_array(AIO_CONF_PRESET, $m->addons, true))
                $m->addons[] = AIO_CONF_PRESET;
        }
    }
    $api = aio_conf_obj(aio_conf_obj($pr, 'options'), 'api');
    $api->url = $p['jr_url'];
    if ($p['jr_key'] !== '')
        $api->apiKey = $p['jr_key'];
    else
        unset($api->apiKey);
    if ($p['tmdb_key'] !== '')
    {
        $c->tmdbApiKey = $p['tmdb_key'];
        $set[] = 'tmdb';
    }
    elseif (in_array('tmdb', $p['set'], true))
        unset($c->tmdbApiKey);
    // As the server checks it: a key or a token in the config (ours or by
    // hand), or its own.
    $now = $p['tmdb_server'] || aio_conf_str($c, 'tmdbApiKey') || aio_conf_str($c, 'tmdbAccessToken');
    // Matching without TMDB: 400. Turned on once when TMDB comes.
    if (!$now || $p['tmdb_prev'] === false)
    {
        aio_conf_obj($c, 'titleMatching')->enabled = $now;
        aio_conf_obj($c, 'yearMatching')->enabled = $now;
    }
    return array($now, $set);
}

// The fields of aio_conf_patch from the settings $s (aio_settings_read of
// common.php) for the chosen server (of the list or the own one); $conf: its
// config of aio_confs, null for a new one; $tmdbs: the server has TMDB of
// its own (null: as the list says; /status of the own server). The key of
// TMDB as aio_tmdb_sent().
function aio_conf_params($s, $conf, $tmdbs = null)
{
    $servers = aio_aio_servers();
    $base = aio_settings_base($s);
    $jr = aio_jacred_list($s);
    return array('rd' => $s['rd_key'], 'tb' => $s['tb_key'], 'jr_url' => aio_conf_torznab($jr[0]['base']),
        'jr_key' => $jr[0]['key'], 'tmdb_key' => aio_tmdb_sent($base, $s['tmdb_key']),
        'tmdb_server' => $tmdbs !== null ? $tmdbs : !empty($servers[$base]), 'tmdb_prev' => $conf ? $conf['tmdb'] : null,
        'set' => $conf ? $conf['set'] : array());
}
