<?php
// Source profiles (0.58.0; v2 - spec source-profiles-v2): the sources of Melange, each with all its settings and an id,
// one of them the main one (src_main); in the interface - «источник». Shared by the plugin (sources.php, the operation:
// aio_op_settings of main.php) and the settings page (cgi/lib/profiles.php); required by common.php (aio_settings_parse
// reads the profiles by aio_profiles_ok, migrates a file of before v2: aio_settings_migrate; aio_settings_json copies
// the main one to the top: aio_settings_top). Plain PHP 5.3, as common.php: no firmware API, no logging here (the
// callers log).
// Types in the PHPDoc (AioProfile and others): phpstan-types.neon of the repository.

define('AIO_PROFILES_MAX', 8);
// Characters of the name of a profile, at most.
define('AIO_PROFILE_NAME_MAX', 30);

// --- Storage: the profiles of settings.json, their ids, names and configs.

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

// A profile as stored -> the profile, or null when it does not fit. A profile is a source of its own, all its settings
// in it (v2, 0.63.0; in the interface - «источник»), of one of four kinds told by its fields (no field of the kind:
// a version of before reads the file as it is):
// - Debrid or Торренты (aio_profile_kind debrid, p2p): a config of Melange array('id', 'name', 'base', 'mode', 'tpl',
//   the JacRed of aio_profile_jacred, 'conf', 'rd_key', 'tb_key') - base of a server of the list (aio_aio_servers) or
//   of an own one (aio_aio_own($base) === $base, as aio_aio_conf), mode of aio_aio_modes(), tpl - the template of a
//   new config of its mode (aio_tpl_debrid, aio_tpl_p2p; a wrong one - the one of the settings $s of the mode), conf -
//   its own config on that server (aio_aio_conf; null - not made yet), the keys of Real-Debrid and TorBox
//   (aio_key_ok, '' none; of Торренты only kept: none goes to its config);
// - an own config (own): array('id', 'name', 'url', the JacRed) - url of aio_manifest_url;
// - Lampac (lampac): array('id', 'name', 'lampac_url' - aio_lp_addr, not '' (not "url": a version of before would take
//   it for an own config), 'lampac_token' - aio_lp_token or '', 'lampac_as_lampa' - bool, 'lampac_sources' -
//   aio_lp_srcs_ok, written by the Dune ("Балансеры…")).
// id: 8 of [a-z0-9], as stored, '' without one (aio_profiles_ok gives it one). The name: 1-AIO_PROFILE_NAME_MAX
// characters of UTF-8, trimmed (of NBSP too: aio_profile_name), no control ones (C0, C1) nor invisible ones (U+00AD,
// U+200B-200F, U+2028-202E, U+2060-2069, U+3164, U+FEFF: a name that looks like another one). Other keys are dropped.
// One without jacred is of before the editor (array('name', 'base', 'mode') or array('name', 'url')): the JacRed of
// the settings $s; the template - of $s of the mode when it is of the source in use (source made, its server and mode:
// the profile stays "now"), else that of the config of its cell in $s (aio_confs[base][mode]) when it is one of its
// mode, else that of $s of the mode; the config of that cell (two profiles of before never had one cell: the target
// was unique). One of Debrid or Торренты without rd_key, tb_key (0.58.0-0.62.0: the keys were of all): those of $s.
/**
 * @param mixed $p
 * @param AioSettings $s
 * @return AioProfile|null
 */
function aio_profile_ok($p, $s)
{
    if (!is_array($p) || !isset($p['name']) || !is_string($p['name']) ||
        (isset($p['url']) ? 1 : 0) + (isset($p['base']) ? 1 : 0) + (isset($p['lampac_url']) ? 1 : 0) !== 1)
        return null;
    $name = aio_profile_name($p['name']);
    // Characters, not bytes; 0 of a name that is not UTF-8 (no mbstring here, as in common.php).
    $n = intval(preg_match_all('/./su', $name, $m));
    if ($n < 1 || $n > AIO_PROFILE_NAME_MAX ||
        preg_match('/[\x00-\x1F\x7F]|\xC2[\x80-\x9F\xAD]|\xE2\x80[\x8B-\x8F\xA8-\xAE]|\xE2\x81[\xA0-\xA9]|\xE3\x85\xA4|\xEF\xBB\xBF/', $name))
        return null;
    $id = isset($p['id']) && aio_profile_id_ok($p['id']) ? $p['id'] : '';
    if (isset($p['lampac_url']))
    {
        $lp = aio_lp_fields($p);
        return $lp['lampac_url'] !== '' ? array('id' => $id, 'name' => $name) + $lp : null;
    }
    $old = !array_key_exists('jacred', $p);
    $jr = aio_profile_jacred($old ? $s : $p);
    if (isset($p['url']))
    {
        $url = aio_manifest_url($p['url']);
        return $url !== '' ? array('id' => $id, 'name' => $name, 'url' => $url) + $jr : null;
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
    $keys = array();
    foreach (array('rd_key' => 'rd', 'tb_key' => 'tb') as $k => $what)
        $keys[$k] = array_key_exists($k, $p) ? aio_key_ok($what, $p[$k]) : $s[$k];
    return array('id' => $id, 'name' => $name, 'base' => $b, 'mode' => $mode, 'tpl' => (string) $tpl) + $jr +
        array('conf' => $conf) + $keys;
}

// The fields of Lampac (of a profile of Lampac; at the top of settings.json - of before v2, the copy of the first one)
// with their defaults (none): the one list of them.
/**
 * @return array{lampac_url: string, lampac_token: string, lampac_as_lampa: bool, lampac_sources: list<AioLpChoice>}
 */
function aio_lp_keys()
{
    return array('lampac_url' => '', 'lampac_token' => '', 'lampac_as_lampa' => false, 'lampac_sources' => array());
}

// The fields of Lampac (aio_lp_keys) of $p as stored (a profile, settings.json): the address of aio_lp_addr, the
// token of aio_lp_token, "as Lampa" a bool, the balancers of aio_lp_srcs_ok; one missing or wrong - its default.
/**
 * @param array<mixed> $p
 * @return array{lampac_url: string, lampac_token: string, lampac_as_lampa: bool, lampac_sources: list<AioLpChoice>}
 */
function aio_lp_fields($p)
{
    $r = aio_lp_keys();
    $u = isset($p['lampac_url']) ? aio_lp_addr($p['lampac_url']) : null;
    $t = isset($p['lampac_token']) ? aio_lp_token($p['lampac_token']) : null;
    if (is_string($u))
        $r['lampac_url'] = $u;
    if (is_string($t))
        $r['lampac_token'] = $t;
    if (isset($p['lampac_as_lampa']) && is_bool($p['lampac_as_lampa']))
        $r['lampac_as_lampa'] = $p['lampac_as_lampa'];
    if (isset($p['lampac_sources']))
        $r['lampac_sources'] = aio_lp_srcs_ok($p['lampac_sources']);
    return $r;
}

// The kind of profile $p: 'debrid', 'p2p' (Торренты), 'own' (an own config), 'lampac'.
/**
 * @param array<string, mixed> $p
 * @return string
 */
function aio_profile_kind($p)
{
    if (isset($p['lampac_url']))
        return 'lampac';
    if (isset($p['url']))
        return 'own';
    return isset($p['mode']) && $p['mode'] === AIO_MODE_P2P ? 'p2p' : 'debrid';
}

// Profile $p gives releases (AIOStreams: Debrid, Торренты, an own config), not the online list of Lampac.
/**
 * @param array<string, mixed> $p
 * @return bool
 */
function aio_profile_rel($p)
{
    return !isset($p['lampac_url']);
}

// The id of a profile as stored: 8 of [a-z0-9].
/**
 * @param mixed $id
 * @return bool
 */
function aio_profile_id_ok($id)
{
    return is_string($id) && preg_match('/^[a-z0-9]{8}\z/', $id) === 1;
}

// The id of a profile of the name $name without one (0.58.0-0.62.0, a profile the migration makes): of the name, so
// the plugin and the page give it the same one till the file is written with it; taken in $taken (id => true) - of the
// name with a number from 2.
/**
 * @param string $name
 * @param array<string, bool> $taken
 * @return string
 */
function aio_profile_id_of($name, $taken)
{
    $id = substr(md5("p $name"), 0, 8);
    for ($n = 2; isset($taken[$id]); $n++)
        $id = substr(md5("p $name $n"), 0, 8);
    return $id;
}

// The id of a new profile named $name among those of the settings $s (the quick setup; the page, «+ Добавить
// источник»): of the name (aio_profile_id_of), taken - a random one.
/**
 * @param AioSettings $s
 * @param string $name
 * @return string
 */
function aio_profile_new_id($s, $name)
{
    $taken = array();
    foreach ($s['profiles'] as $p)
        $taken[$p['id']] = true;
    $id = substr(md5("p $name"), 0, 8);
    while (isset($taken[$id]))
        $id = substr(md5(uniqid('', true) . mt_rand()), 0, 8);
    return $id;
}

// Profiles $list (of aio_profile_ok) with an id each: one stored and not taken by one before it kept, else of the name
// (aio_profile_id_of).
/**
 * @param list<AioProfile> $list
 * @return list<AioProfile>
 */
function aio_profiles_ids($list)
{
    $taken = array();
    foreach ($list as $i => $p)
    {
        if ($p['id'] !== '' && !isset($taken[$p['id']]))
            $taken[$p['id']] = true;
        else
            $list[$i]['id'] = '';
    }
    foreach ($list as $i => $p)
    {
        if ($p['id'] === '')
        {
            $list[$i]['id'] = aio_profile_id_of($p['name'], $taken);
            $taken[$list[$i]['id']] = true;
        }
    }
    return $list;
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
// order, at most AIO_PROFILES_MAX, an id each (aio_profiles_ids); one of a name already there is dropped (by the name
// the user picks it in MENU). Two of the same settings are fine (v2: two Debrid of one server with other keys).
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
        if (!$p || count($r) === AIO_PROFILES_MAX || isset($seen[$p['name']]))
            continue;
        $seen[$p['name']] = true;
        $r[] = $p;
    }
    return aio_profiles_ids($r);
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
        if (!empty($p['conf']) && $p['conf']['uuid'] === $c['uuid'])
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

// Profile $p can be used: an own config, a config of Melange with its config made (the plugin makes none; the page
// does on "Save"), Lampac (its address is never '').
/**
 * @param AioProfile $p
 * @return bool
 */
function aio_profile_ready($p)
{
    return isset($p['base']) ? !empty($p['conf']) : true;
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

// The index of the profile of id $id in the settings $s, -1 for none.
/**
 * @param AioSettings $s
 * @param string $id
 * @return int
 */
function aio_profile_index($s, $id)
{
    foreach ($id !== '' ? $s['profiles'] : array() as $i => $p)
    {
        if ($p['id'] === $id)
            return $i;
    }
    return -1;
}

// The index of the main profile of the settings $s (src_main; written only by the page and the quick setup): that of
// the id, none there - the first; -1 - no profiles.
/**
 * @param AioSettings $s
 * @return int
 */
function aio_profile_main($s)
{
    $i = aio_profile_index($s, $s['src_main']);
    return $i >= 0 ? $i : ($s['profiles'] ? 0 : -1);
}

// The index of the first profile of the settings $s of releases ($rel true: aio_profile_rel) or of Lampac ($rel false),
// of those ready only ($ready: aio_profile_ready); -1 for none.
/**
 * @param AioSettings $s
 * @param bool $rel
 * @param bool $ready
 * @return int
 */
function aio_profile_first($s, $rel, $ready = true)
{
    foreach ($s['profiles'] as $i => $p)
    {
        if (aio_profile_rel($p) === $rel && (!$ready || aio_profile_ready($p)))
            return $i;
    }
    return -1;
}

// The index of the profile of releases of the settings $s by the main one: the main one if it is of releases (and ready,
// of $ready: aio_profile_ready), else the first of them (aio_profile_first, of $ready); -1 for none. The releases of the
// pair of a profile of Lampac (aio_profile_pair, aio_profile_of_record), the source at the top (aio_settings_top).
/**
 * @param AioSettings $s
 * @param bool $ready
 * @return int
 */
function aio_profile_rel_main($s, $ready)
{
    $m = aio_profile_main($s);
    return $m >= 0 && aio_profile_rel($s['profiles'][$m]) && (!$ready || aio_profile_ready($s['profiles'][$m])) ? $m :
        aio_profile_first($s, true, $ready);
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
    return ($i + 1) . ' (' . (isset($p['mode']) ? "made {$p['mode']}" : aio_profile_kind($p)) . ')';
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

// The word of the kind $kind of aio_profile_kind for the name of a new profile in the language of the Dune (the
// migration, the quick setup; src_kind_* of the translations, aio_cgi_profile_word of the page): the plugin and the
// page give a profile of the migration one name, so one id (aio_profile_id_of).
/**
 * @param string $kind
 * @return string
 */
function aio_profile_word($kind)
{
    $ru = aio_dune_lang() === 'ru';
    $w = array('debrid' => 'Debrid', 'p2p' => $ru ? 'Торренты' : 'Torrents', 'own' => $ru ? 'Свой конфиг' : 'Own config',
        'lampac' => 'Lampac');
    return isset($w[$kind]) ? $w[$kind] : 'Debrid';
}

// ru or en of interface_language of the Dune (settings.properties, as the firmware reads it: custom -
// noncustom_interface_language); no such file (not a Dune) - ru.
/**
 * @return string
 */
function aio_dune_lang()
{
    $f = getenv('FS_PREFIX') . '/config/settings.properties';
    if (!is_file($f))
        return 'ru';
    $text = strval(@file_get_contents($f, false, null, 0, 65536));
    $l = preg_match('/^interface_language[ \t]*=[ \t]*(\w+)/m', $text, $m) ? $m[1] : '';
    if ($l === 'custom')
        $l = preg_match('/^noncustom_interface_language[ \t]*=[ \t]*(\w+)/m', $text, $m) ? $m[1] : '';
    return $l === 'russian' ? 'ru' : 'en';
}

// Profile $p (of aio_profile_ok) added to the settings $s at the end; no id - one of its name (aio_profiles_ids). ->
// array(the settings, its index); the profiles full or $p null - $s and -1.
/**
 * @param AioSettings $s
 * @param AioProfile|null $p
 * @return array{0: AioSettings, 1: int}
 */
function aio_profile_add($s, $p)
{
    if (!$p || count($s['profiles']) >= AIO_PROFILES_MAX)
        return array($s, -1);
    $s['profiles'][] = $p;
    $s['profiles'] = aio_profiles_ids($s['profiles']);
    return array($s, count($s['profiles']) - 1);
}

// --- Before v2: the migration only (the old rule of the source in use, aio_profile_now).

// The target of profile $p of releases (or of aio_profile_of) as one string, its settings without the name, the keys
// and the config: the old rule of the source in use (aio_profile_same). The JacRed as it is used: the own address and
// key only of the own one chosen. Lampac: its address.
/**
 * @param array<string, mixed> $p
 * @return string
 */
function aio_profile_key($p)
{
    if (isset($p['lampac_url']))
        return "lampac {$p['lampac_url']}";
    $jr = $p['jacred'] === 'own' ? "own {$p['jacred_own_url']} {$p['jacred_own_key']}" : strval($p['jacred']);
    return isset($p['url']) ? "url {$p['url']} $jr" : "made {$p['base']} {$p['mode']} {$p['tpl']} $jr";
}

// The source at the top of settings $s is that of profile $p (what aio_profile_apply sets), its config aside.
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return bool
 */
function aio_profile_same($s, $p)
{
    $t = aio_profile_of($s);
    return $t !== null && aio_profile_rel($p) && aio_profile_key($t) === aio_profile_key($p);
}

// Profile $p is the source at the top of settings $s: its settings (aio_profile_same) and, of a config of Melange, its
// config in the cell (the settings of another profile of that server and mode typed on the page are not it) or none of
// its own yet (not made: a create failed - the source is still the profile). Lampac: never.
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return bool
 */
function aio_profile_is($s, $p)
{
    if (!aio_profile_same($s, $p))
        return false;
    if (!isset($p['base']))
        return true;
    $c = aio_settings_conf($s);
    return !$p['conf'] || ($c && $c['uuid'] === $p['conf']['uuid']);
}

// The index of the profile of the settings $s at the top (aio_profile_is), -1 for none.
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

// Profile $p of the source at the top, of a config of Melange without one of its own (a create of before failed), with
// the config $c of the cell when $free (no other profile has it) -> the profile; else as it is.
/**
 * @param AioProfile $p
 * @param AioConf|null $c
 * @param bool $free
 * @return AioProfile
 */
function aio_profile_conf_given($p, $c, $free)
{
    if (isset($p['base']) && !$p['conf'] && $c && $free)
        $p['conf'] = $c;
    return $p;
}

// The settings $s of a settings.json of before v2 (no src_main: 0.58.0-0.62.0, feat/lampac; aio_settings_parse; spec v2
// §4): the profiles have their ids and keys already (aio_profiles_ok). The source at the top of them, the one in use of
// before, is the main one: a profile of it by the old rule (aio_profile_now; without its config it gets that of the
// cell, if no other one has it), else - when it fits a profile (an own config with its link, a config of Melange with
// its config in the cell) - a new one at the end, named by its kind (aio_profile_word), the keys
// of the top; its config that of another profile (other settings typed on the page) - that one; the profiles full - the
// first. lampac_url set - a profile of Lampac "Lampac" at the end with the address, token, "as Lampa" and balancers of
// the top, the main one only of no other profile. The cells of aio_confs stay (a new profile of their server and mode
// takes one). Not made for the profiles full (AIO_PROFILES_MAX, the limit kept): mig_lost of the settings says which,
// the reader logs it. -> the settings, src_main the id of the main one ('' none).
/**
 * @param AioSettings $s
 * @return AioSettings
 */
function aio_settings_migrate($s)
{
    $main = -1;
    $now = aio_profile_now($s);
    $t = aio_profile_of($s);
    $c = $t && !isset($t['url']) ? $t['conf'] : null;
    $by = aio_profile_holder($s, $c);
    if ($now >= 0)
    {
        $main = $now;
        $s['profiles'][$now] = aio_profile_conf_given($s['profiles'][$now], $c, $by < 0);
    }
    elseif ($t && (isset($t['url']) || $c))
    {
        $main = $by;
        if ($main < 0)
            list($s, $main) = aio_profile_add($s, aio_profile_ok(array('name' =>
                aio_profile_free_name($s, aio_profile_word(aio_profile_kind($t)))) + $t, $s));
        if ($main < 0)
            list($s['mig_lost'][], $main) = array('the source in use', 0);
    }
    if ($s['lampac_url'] !== '')
    {
        list($s, $lp) = aio_profile_add($s, aio_profile_ok(array('name' => aio_profile_free_name($s, aio_profile_word('lampac'))) +
            array_intersect_key($s, aio_lp_keys()), $s));
        if ($lp < 0)
            $s['mig_lost'][] = 'Lampac';
        if ($main < 0 && $lp >= 0 && count($s['profiles']) === 1)
            $main = $lp;
    }
    $s['src_main'] = $main >= 0 ? $s['profiles'][$main]['id'] : '';
    return $s;
}

// --- The copy at the top: the main one there (spec v2 §4 p. 7), the source of the top as a profile.

// Settings $s with the source of profile $p of releases (aio_profile_rel) at the top (the copy of the main one in
// settings.json, aio_settings_top; the settings of an operation, aio_profile_view): a config of Melange - source made,
// the mode, the server (one of the list, else "own" with its address), the template of its mode, its config in the
// cell of the server and mode (none: the cell as it is - a config there no profile has is never lost); an own config -
// source own and its link, the server, the mode and the templates kept (only stored then); the JacRed of both. The
// keys of Debrid not: aio_profile_view, aio_settings_top.
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return AioSettings
 */
function aio_profile_apply($s, $p)
{
    if (isset($p['lampac_url']))
        return $s;
    list($s['jacred'], $s['jacred_own_url'], $s['jacred_own_key']) = array($p['jacred'], $p['jacred_own_url'], $p['jacred_own_key']);
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

// A profile of the source at the top of settings $s, without a name: of the own config (url) or of a config of Melange
// (base, mode, the template of the mode, conf - the config of its cell, null - none); with the JacRed of $s. null -
// none (the own config without its link, no server).
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

// The settings $s to write (aio_settings_json, the one writer of settings.json) with the settings of the main profile
// copied to the top (spec v2 §4 p. 7), so a version of before v2 over this file goes on with the main one: the source of
// it, else of the first profile of releases (aio_profile_apply: its config in the cell); the keys of Debrid of
// aio_profile_debrid; src_profiles on with two profiles of releases or more (its MENU of a list); lampac_* of the first
// profile of Lampac but its balancers (none: lampac_url ''); src_main the id of the main one ('' no profiles); an id
// each (aio_profiles_ids). Without profiles in $s: as it is.
/**
 * @param array<string, mixed> $s
 * @return array<string, mixed>
 */
function aio_settings_top($s)
{
    if (!isset($s['profiles']) || !is_array($s['profiles']))
        return $s;
    /** @var AioSettings $t */
    $t = $s;
    $t['profiles'] = aio_profiles_ids($t['profiles']);
    $m = aio_profile_main($t);
    $t['src_main'] = $m >= 0 ? $t['profiles'][$m]['id'] : '';
    if ($m < 0)
        return $t;
    $rel = aio_profile_rel_main($t, false);
    if ($rel >= 0)
        $t = aio_profile_apply($t, $t['profiles'][$rel]);
    $deb = aio_profile_debrid($t);
    $q = $deb >= 0 ? $t['profiles'][$deb] : null;
    if ($q && isset($q['base']))
        list($t['rd_key'], $t['tb_key']) = array($q['rd_key'], $q['tb_key']);
    $n = 0;
    foreach ($t['profiles'] as $p)
        $n += aio_profile_rel($p) ? 1 : 0;
    if ($n >= 2)
        $t['src_profiles'] = true;
    $lp = aio_profile_first($t, false, false);
    $t = array_merge($t, $lp >= 0 ? array_intersect_key($t['profiles'][$lp], aio_lp_keys()) : aio_lp_keys());
    // Not its balancers: feat/lampac of before was never released (no way back to it), the file has them once.
    $t['lampac_sources'] = array();
    return $t;
}

// The index of the profile of the settings $s whose keys of Debrid are those of the top (spec v2 §4 p. 7): the main one
// when it is of Debrid, else the first of Debrid; none - the main one of Торренты, else the first of them; -1 none.
/**
 * @param AioSettings $s
 * @return int
 */
function aio_profile_debrid($s)
{
    $m = aio_profile_main($s);
    $order = $m >= 0 ? array($m => $s['profiles'][$m]) + $s['profiles'] : $s['profiles'];
    foreach (array('debrid', 'p2p') as $kind)
    {
        foreach ($order as $i => $p)
        {
            if (isset($p['base']) && aio_profile_kind($p) === $kind)
                return $i;
        }
    }
    return -1;
}

// --- The pair of an operation (spec v2 §1.9): its profiles and its settings.

// The settings $s as if the source were profile $p, the configs only its own (the editor of the page; an operation,
// aio_op_settings of main.php): of one of releases its source (aio_profile_apply) and of Debrid and Торренты its keys
// (an own config: none, they are in the config); of Lampac its address, token, "as Lampa" and balancers.
/**
 * @param AioSettings $s
 * @param AioProfile $p
 * @return AioSettings
 */
function aio_profile_view($s, $p)
{
    if (isset($p['lampac_url']))
        return array_merge($s, array_intersect_key($p, aio_lp_keys()));
    $s = aio_profile_apply($s, $p);
    $s['aio_confs'] = array();
    list($s['rd_key'], $s['tb_key']) = array('', '');
    if (isset($p['base']))
    {
        if ($p['conf'])
            $s['aio_confs'] = array($p['base'] => array($p['mode'] => $p['conf']));
        list($s['rd_key'], $s['tb_key']) = array($p['rd_key'], $p['tb_key']);
    }
    return $s;
}

// The pair of profiles of an operation (spec v2 §1.9) of the profile $t of a title (an index of the settings $s; -1 -
// the main one) -> array(the index of the profile of releases, of Lampac; -1 none), both ready: $t of releases - it and
// the first Lampac (its "Искать онлайн"); $t of Lampac - it and the main one if it is of releases, else the first of
// them (its releases when nothing is online). $t not ready (the main one without its config) - the first ready one of
// its kind, else of the other one.
/**
 * @param AioSettings $s
 * @param int $t
 * @return array{int, int}
 */
function aio_profile_pair($s, $t = -1)
{
    $m = aio_profile_main($s);
    if ($t < 0)
        $t = $m;
    if ($t < 0)
        return array(-1, -1);
    $p = $s['profiles'][$t];
    $rel = aio_profile_rel($p);
    if (!aio_profile_ready($p))
        $t = aio_profile_first($s, $rel);
    if ($t < 0)
    {
        $rel = !$rel;
        $t = aio_profile_first($s, $rel);
    }
    if ($t < 0)
        return array(-1, -1);
    if ($rel)
        return array($t, aio_profile_first($s, false));
    return array(aio_profile_rel_main($s, true), $t);
}

// The index of the profile a title opens by (spec v2 §1.5) in the settings $s: of its record of the history of kind $kind
// ('lp' - an online one, 'rel' - of a release, '' - none: a new title) and the profile $id of it (wh_prof; '' - a record
// of before v2). The profile of the record there, ready and of its kind - it; else (none, deleted, not ready): an online
// record - the first Lampac, of a release - the main one if it is of releases, else the first of them; none of that
// kind, no record - the main one (-1 no profiles; aio_profile_pair takes a ready one then).
/**
 * @param AioSettings $s
 * @param string $id
 * @param string $kind
 * @return int
 */
function aio_profile_of_record($s, $id, $kind)
{
    $m = aio_profile_main($s);
    if ($kind !== 'lp' && $kind !== 'rel')
        return $m;
    $rel = $kind === 'rel';
    $i = aio_profile_index($s, $id);
    if ($i >= 0 && aio_profile_ready($s['profiles'][$i]) && aio_profile_rel($s['profiles'][$i]) === $rel)
        return $i;
    $i = $rel ? aio_profile_rel_main($s, true) : aio_profile_first($s, false);
    return $i >= 0 ? $i : $m;
}
