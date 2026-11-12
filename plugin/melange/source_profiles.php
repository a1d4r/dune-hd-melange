<?php
// Source profiles (0.58.0): the sources the user saved on the page, switched by MENU of a list. Shared by the plugin
// (sources.php) and the settings page (cgi/lib/profiles.php); required by common.php (aio_settings_parse reads the
// profiles by aio_profiles_ok). Plain PHP 5.3, as common.php: no firmware API, no logging here (the callers log).
// Types in the PHPDoc (AioProfile and others): phpstan-types.neon of the repository.

define('AIO_PROFILES_MAX', 8);
// Characters of the name of a profile, at most.
define('AIO_PROFILE_NAME_MAX', 30);

// The JacRed of a profile as stored (0.58.0, the editor of the page): 'jacred' - a key of aio_jacred_builtin() or
// 'own' (only with a good own one), 'jacred_own_url', 'jacred_own_key' - aio_jacred_own() or both '' (kept when a
// built-in one is chosen, as of the settings); a wrong choice - AIO_JACRED_DEFAULT, as aio_settings_parse.
/**
 * @param array<mixed> $p
 * @return array{jacred: string, jacred_own_url: string, jacred_own_key: string}
 */
function aio_profile_jacred($p)
{
    $own = aio_jacred_own(array('url' => isset($p['jacred_own_url']) ? $p['jacred_own_url'] : '',
        'key' => isset($p['jacred_own_key']) ? $p['jacred_own_key'] : ''));
    $b = aio_jacred_builtin();
    $id = isset($p['jacred']) && is_string($p['jacred']) && (isset($b[$p['jacred']]) || ($p['jacred'] === 'own' && $own)) ?
        $p['jacred'] : AIO_JACRED_DEFAULT;
    return array('jacred' => $id, 'jacred_own_url' => $own ? $own['url'] : '', 'jacred_own_key' => $own ? $own['key'] : '');
}

// A profile as stored -> the profile, or null when it does not fit. A profile is a set of the settings of the source
// (0.58.0): a config of Melange array('name', 'base', 'mode', 'tpl', the JacRed of aio_profile_jacred, 'conf') - base
// of a server of the list (aio_aio_servers) or of an own one (aio_aio_own($base) === $base, as aio_aio_conf), mode of
// aio_aio_modes(), tpl - the template of a new config of its mode (aio_tpl_debrid, aio_tpl_p2p; a wrong one - the one
// of the settings $s of the mode), conf - its own config on that server (aio_aio_conf; null - not made yet); or an own
// config array('name', 'url', the JacRed) - url of aio_manifest_url. The name: 1-AIO_PROFILE_NAME_MAX characters of
// UTF-8, trimmed (of NBSP too: aio_profile_name), no control ones (C0, C1) nor invisible ones (U+00AD, U+200B-200F,
// U+2028-202E, U+2060-2069, U+3164, U+FEFF: a name that looks like another one). Other keys are dropped.
// One without jacred is of before the editor (array('name', 'base', 'mode') or array('name', 'url')): the JacRed of
// the settings $s; the template - of $s of the mode when it is of the source in use (source made, its server and mode:
// the profile stays "now"), else that of the config of its cell in $s (aio_confs[base][mode]) when it is one of its
// mode, else that of $s of the mode; the config of that cell (two profiles of before never had one cell: the target
// was unique).
/**
 * @param mixed $p
 * @param AioSettings $s
 * @return AioProfile|null
 */
function aio_profile_ok($p, $s)
{
    if (!is_array($p) || !isset($p['name']) || !is_string($p['name']) || isset($p['url']) === isset($p['base']))
        return null;
    $name = aio_profile_name($p['name']);
    // Characters, not bytes; 0 of a name that is not UTF-8 (no mbstring here, as in common.php).
    $n = intval(preg_match_all('/./su', $name, $m));
    if ($n < 1 || $n > AIO_PROFILE_NAME_MAX ||
        preg_match('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F\xAD]|\xE2\x80[\x8B-\x8F\xA8-\xAE]|\xE2\x81[\xA0-\xA9]|\xE3\x85\xA4|\xEF\xBB\xBF/', $name))
        return null;
    $old = !array_key_exists('jacred', $p);
    $jr = aio_profile_jacred($old ? $s : $p);
    if (isset($p['url']))
    {
        $url = aio_manifest_url($p['url']);
        return $url !== '' ? array('name' => $name, 'url' => $url) + $jr : null;
    }
    $servers = aio_aio_servers();
    $b = is_string($p['base']) ? $p['base'] : '';
    if ((!isset($servers[$b]) && ($b === '' || aio_aio_own($b) !== $b)) || !isset($p['mode']) ||
        !in_array($p['mode'], aio_aio_modes(), true))
        return null;
    $mode = $p['mode'];
    $cell = isset($s['aio_confs'][$b][$mode]) ? $s['aio_confs'][$b][$mode] : null;
    $in_use = $s['source'] === 'made' && aio_settings_base($s) === $b && $s['aio_mode'] === $mode;
    $tpl = $old ? ($cell && !$in_use ? $cell['tpl'] : null) : (isset($p['tpl']) ? $p['tpl'] : null);
    if (!($mode === AIO_MODE_P2P ? aio_tpl_p2p($tpl) : aio_tpl_debrid($tpl)))
        $tpl = $mode === AIO_MODE_P2P ? $s['aio_tpl_p2p'] : $s['aio_tpl'];
    $conf = $old ? $cell : (isset($p['conf']) ? aio_aio_conf($b, $p['conf']) : null);
    return array('name' => $name, 'base' => $b, 'mode' => $mode, 'tpl' => (string) $tpl) + $jr + array('conf' => $conf);
}

// A name of a profile as typed, trimmed of AIO_TRIM and of NBSP (U+00A0) at both ends; one over
// 4 * AIO_PROFILE_NAME_MAX bytes as it is (aio_profile_ok refuses it). By a loop: a regex of the ends
// crashed PCRE on ~10K of spaces (a forged POST or import).
/**
 * @param string $name
 * @return string
 */
function aio_profile_name($name)
{
    if (strlen($name) > 4 * AIO_PROFILE_NAME_MAX)
        return $name;
    do
    {
        $was = $name;
        $name = trim($name, AIO_TRIM);
        if (substr($name, 0, 2) === "\xC2\xA0")
            $name = (string) substr($name, 2);
        if (substr($name, -2) === "\xC2\xA0")
            $name = (string) substr($name, 0, -2);
    } while ($name !== $was);
    return $name;
}

// profiles of settings.json -> those of aio_profile_ok (the settings $s: of aio_settings_parse, aio_confs read), in
// order, at most AIO_PROFILES_MAX; one of a name or a target (aio_profile_key) already there is dropped.
/**
 * @param array<mixed> $list
 * @param AioSettings $s
 * @return list<AioProfile>
 */
function aio_profiles_ok($list, $s)
{
    $r = array();
    $seen = array();
    foreach ($list as $p)
    {
        $p = aio_profile_ok($p, $s);
        if (!$p || count($r) === AIO_PROFILES_MAX)
            continue;
        $to = aio_profile_key($p);
        if (isset($seen['name ' . $p['name']]) || isset($seen[$to]))
            continue;
        $seen['name ' . $p['name']] = $seen[$to] = true;
        $r[] = $p;
    }
    return $r;
}

// The keys of the settings of the source (aio_profile_apply sets them; the template of a profile is one of the two):
// those MENU of a list and the page compare to see the source changed meanwhile.
/**
 * @return list<string>
 */
function aio_source_keys()
{
    return array('source', 'manifest_url', 'aio_server', 'aio_own_url', 'aio_mode', 'aio_tpl', 'aio_tpl_p2p', 'jacred',
        'jacred_own_url', 'jacred_own_key');
}

// aio_confs[$base][$mode] of the settings $s set to $c (null: emptied; the config of the other mode stays).
/**
 * @param AioSettings $s
 * @param string $base
 * @param string $mode
 * @param AioConf|null $c
 * @return AioSettings
 */
function aio_settings_cell($s, $base, $mode, $c)
{
    if ($c)
        $s['aio_confs'][$base][$mode] = $c;
    else
    {
        unset($s['aio_confs'][$base][$mode]);
        if (empty($s['aio_confs'][$base]))
            unset($s['aio_confs'][$base]);
    }
    return $s;
}

// The index of the profile of the settings $s with the config $c (its uuid), -1 for none.
/**
 * @param AioSettings $s
 * @param AioConf|null $c
 * @return int
 */
function aio_profile_holder($s, $c)
{
    foreach ($c ? $s['profiles'] : array() as $i => $p)
    {
        if (!isset($p['url']) && $p['conf'] && $p['conf']['uuid'] === $c['uuid'])
            return $i;
    }
    return -1;
}

// A profile of the settings $s has the config $c (aio_profile_holder): the config may leave its cell, the profile keeps
// it.
/**
 * @param AioSettings $s
 * @param AioConf|null $c
 * @return bool
 */
function aio_profile_held($s, $c)
{
    return aio_profile_holder($s, $c) >= 0;
}

// Settings $s with profile $p in use (MENU of a list, "Сделать текущим" of the page): a config of Melange - source
// made, the mode, the server (one of the list, else "own" with its address), the template of its mode, its config in
// the cell of the server and mode (none: the cell as it is - a config there no profile has is never lost); an own
// config - source own and its link, the server, the mode and the templates kept (only stored then); the JacRed of
// both.
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return AioSettings
 */
function aio_profile_apply($s, $p)
{
    foreach (array('jacred', 'jacred_own_url', 'jacred_own_key') as $k)
        $s[$k] = $p[$k];
    if (isset($p['url']))
    {
        $s['source'] = 'own';
        $s['manifest_url'] = $p['url'];
        return $s;
    }
    $servers = aio_aio_servers();
    $s['source'] = 'made';
    $s['aio_mode'] = $p['mode'];
    $s[$p['mode'] === AIO_MODE_P2P ? 'aio_tpl_p2p' : 'aio_tpl'] = $p['tpl'];
    if (isset($servers[$p['base']]))
        $s['aio_server'] = $p['base'];
    else
        list($s['aio_server'], $s['aio_own_url']) = array('own', $p['base']);
    return $p['conf'] ? aio_settings_cell($s, $p['base'], $p['mode'], $p['conf']) : $s;
}

// The settings $s as the page shows profile $p (its editor): aio_profile_apply, the configs only that of the profile
// (another server or mode of it is another config of its own, made by a save).
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return AioSettings
 */
function aio_profile_view($s, $p)
{
    $s = aio_profile_apply($s, $p);
    $s['aio_confs'] = !isset($p['url']) && $p['conf'] ? array($p['base'] => array($p['mode'] => $p['conf'])) : array();
    return $s;
}

// The target of profile $p (or of aio_profile_of) as one string, its settings without the name and the config: two
// profiles of the same one are one source (aio_profiles_ok keeps the first, the page refuses the second). The JacRed
// as it is used: the own address and key only of the own one chosen.
/**
 * @param array<string, mixed> $p
 * @return string
 */
function aio_profile_key($p)
{
    $jr = $p['jacred'] === 'own' ? "own {$p['jacred_own_url']} {$p['jacred_own_key']}" : strval($p['jacred']);
    return isset($p['url']) ? "url {$p['url']} $jr" : "made {$p['base']} {$p['mode']} {$p['tpl']} $jr";
}

// A profile of the source of settings $s, without a name: of the own config (url) or of a config of Melange (base,
// mode, the template of the mode, conf - the config of its cell, null - none); with the JacRed of $s. null - none
// (the own config without its link, no server).
/**
 * @param AioSettings $s
 * @return array<string, mixed>|null
 */
function aio_profile_of($s)
{
    $jr = array('jacred' => $s['jacred'], 'jacred_own_url' => $s['jacred_own_url'], 'jacred_own_key' => $s['jacred_own_key']);
    if ($s['source'] === 'own')
        return $s['manifest_url'] !== '' ? array('url' => $s['manifest_url']) + $jr : null;
    $b = aio_settings_base($s);
    return $b !== '' ? array('base' => $b, 'mode' => $s['aio_mode'], 'tpl' => aio_settings_tpl($s)) + $jr +
        array('conf' => aio_settings_conf($s)) : null;
}

// Settings $s are those of profile $p (what aio_profile_apply sets), its config aside.
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return bool
 */
function aio_profile_same($s, $p)
{
    $t = aio_profile_of($s);
    return $t !== null && aio_profile_key($t) === aio_profile_key($p);
}

// Profile $p is the one in use in settings $s: its settings (aio_profile_same) and, of a config of Melange, its config
// in the cell (the settings of another profile of that server and mode typed on the page are not it) or none of its
// own yet (not made: a create failed - the source is still the profile; no other one has these settings).
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return bool
 */
function aio_profile_is($s, $p)
{
    if (!aio_profile_same($s, $p))
        return false;
    $c = isset($p['url']) ? null : aio_settings_conf($s);
    return isset($p['url']) || !$p['conf'] || ($c && $c['uuid'] === $p['conf']['uuid']);
}

// Profile $p can be used: an own config, or a config of Melange with its config made (the plugin makes none; the page
// does on "Save").
/**
 * @param AioProfile $p
 * @return bool
 */
function aio_profile_ready($p)
{
    return isset($p['url']) || $p['conf'];
}

// The index of the profile named $name in the settings $s, -1 for none.
/**
 * @param AioSettings $s
 * @param string $name
 * @return int
 */
function aio_profile_find($s, $name)
{
    foreach ($s['profiles'] as $i => $p)
    {
        if ($p['name'] === $name)
            return $i;
    }
    return -1;
}

// The index of the profile of the settings $s in use (aio_profile_is), -1 for none.
/**
 * @param AioSettings $s
 * @return int
 */
function aio_profile_now($s)
{
    foreach ($s['profiles'] as $i => $p)
    {
        if (aio_profile_is($s, $p))
            return $i;
    }
    return -1;
}

// A profile for the log (MENU of a list, the page): its number from 1 ("none" for -1) and kind, never its name or link
// (the user typed them).
/**
 * @param int $i
 * @param AioProfile|null $p
 * @return string
 */
function aio_profile_tag($i, $p)
{
    if ($i < 0 || !$p)
        return 'none';
    return ($i + 1) . ' (' . (isset($p['url']) ? 'own' : "made {$p['mode']}") . ')';
}

// The name of a new profile among those of the settings $s: the word $w of its kind, with a number from 2 while taken.
/**
 * @param AioSettings $s
 * @param string $w
 * @return string
 */
function aio_profile_free_name($s, $w)
{
    $name = $w;
    for ($n = 2; aio_profile_find($s, $name) >= 0 && $n <= AIO_PROFILES_MAX + 1; $n++)
        $name = "$w $n";
    return $name;
}

// Before a profile is applied to the settings $s (aio_profile_apply: MENU of a list, "Сделать текущим" of the page): the
// source in use kept by a profile, so MENU can bring it back. The source of a profile without its config (aio_profile_is:
// a create failed) and a config in the cell no profile has: the config given to that profile (a profile takes the config
// of no profile of its server and mode; else the next profile of them would take the cell). The source of no profile
// (aio_profile_now): saved as a profile at the end of them, its settings as they are (aio_profile_of), the name - the
// word of its kind in $words ('debrid', 'p2p', 'own': in the language of the caller) by aio_profile_free_name. Not saved
// (-> $s as it is): none to save (an own config without its link, a config of Melange without its config in the cell);
// a profile of these settings there (its target: aio_profiles_ok would drop the second); its config that of another
// profile (aio_profile_holder: other settings typed - a config of a profile changes only with it). -> array(the settings,
// the index of the profile that keeps the source now or -1, the profiles full: a source to save and no room - not to be
// applied, the index of the profile whose config is in the cell of a source not saved or -1, the index of the profile
// of these settings of a source not saved or -1).
/**
 * @param AioSettings $s
 * @param array{debrid: string, p2p: string, own: string} $words
 * @return array{0: AioSettings, 1: int, 2: bool, 3: int, 4: int}
 */
function aio_profile_keep($s, $words)
{
    $now = aio_profile_now($s);
    $t = aio_profile_of($s);
    $c = $t && !isset($t['url']) ? $t['conf'] : null;
    $by = aio_profile_holder($s, $c);
    if ($now >= 0)
    {
        $p = $s['profiles'][$now];
        if (isset($p['url']) || $p['conf'] || !$c || $by >= 0)
            return array($s, -1, false, -1, -1);
        $p['conf'] = $c;
        $s['profiles'][$now] = $p;
        return array($s, $now, false, -1, -1);
    }
    if (!$t || (!isset($t['url']) && !$c))
        return array($s, -1, false, -1, -1);
    if ($by >= 0)
        return array($s, -1, false, $by, -1);
    foreach ($s['profiles'] as $i => $x)
    {
        if (aio_profile_key($x) === aio_profile_key($t))
            return array($s, -1, false, -1, $i);
    }
    if (count($s['profiles']) >= AIO_PROFILES_MAX)
        return array($s, -1, true, -1, -1);
    $w = $words[isset($t['url']) ? 'own' : ($t['mode'] === AIO_MODE_P2P ? 'p2p' : 'debrid')];
    $np = aio_profile_ok(array('name' => aio_profile_free_name($s, $w)) + $t, $s);
    if (!$np)
        return array($s, -1, false, -1, -1);
    $s['profiles'][] = $np;
    return array($s, count($s['profiles']) - 1, false, -1, -1);
}
