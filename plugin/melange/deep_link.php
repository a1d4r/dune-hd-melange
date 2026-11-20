<?php
// The public action "open" (public_actions of the manifest): a link
// plugin_launcher://{name=melange}{action_id=open}{to=<target>}{<field>=<value>}... opens a screen of Melange by ids.
// The shell gives the pairs to handle_user_input as they are (no URL decoding; no { or } in a value). The targets
// (mel_dl_targets): card - the own card, as ENTER on a tile (mel_oc_enter); streams - the list of streams of the card,
// as its "Watch" or ENTER on an episode (mel_oc_watch), nothing started, of source src if given (as "Смотреть из…");
// dune_card - the card of the Dune (file_movie); person - the page of a person (view_person.php); search - the results
// of a search of the catalog by the text, no dialog; tab - the catalog at a tab; row - "Show all" of a row of a tab by
// its number on the screen. A value off its pattern, a field the target does not take, one it lacks: a line of the
// log, no screen. Each call: a line of the log with the target and the ids.
// Needs main.php, catalog.php, catalog_card.php, card_meta.php, view_card.php, view_card_series.php, view_card_lib.php
// (mel_oc_lib_pick), view_catalog.php, view_person.php, continue.php (mel_cp_items), source_profiles.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// The targets: fn - the handler of the link; id - the fields of an id it takes, exactly one of them given (none: no
// id); need - the other fields it needs; may - those it takes if given; se - a series (type) needs s and e too (a
// movie takes none). Any other field of a link given: wrong. A new target: one more line here and its handler.
/**
 * @return array<string, array{fn: callable-string, id: list<string>, need: list<string>, may: list<string>, se: bool}>
 */
function mel_dl_targets()
{
    return array(
        'card' => array('fn' => 'mel_dl_card', 'id' => array('imdb', 'tmdb'), 'need' => array('type'), 'may' => array(), 'se' => false),
        'streams' => array('fn' => 'mel_dl_streams', 'id' => array('imdb', 'tmdb'), 'need' => array('type'),
            'may' => array('src'), 'se' => true),
        'dune_card' => array('fn' => 'mel_dl_dune_card', 'id' => array('imdb'), 'need' => array(), 'may' => array('type'), 'se' => false),
        'person' => array('fn' => 'mel_dl_person', 'id' => array('tmdb'), 'need' => array(), 'may' => array(), 'se' => false),
        'search' => array('fn' => 'mel_dl_search', 'id' => array(), 'need' => array('q'), 'may' => array(), 'se' => false),
        'tab' => array('fn' => 'mel_dl_tab', 'id' => array(), 'need' => array('tab'), 'may' => array(), 'se' => false),
        'row' => array('fn' => 'mel_dl_row', 'id' => array(), 'need' => array('tab', 'n'), 'may' => array(), 'se' => false),
        'mylist' => array('fn' => 'mel_dl_mylist', 'id' => array(), 'need' => array(), 'may' => array(), 'se' => false),
        'continue' => array('fn' => 'mel_dl_continue', 'id' => array(), 'need' => array(), 'may' => array(), 'se' => false));
}

// The fields of $in of a link (the others not read) -> array(why it is wrong: "<field>" (off its pattern), "<field>:
// none" (lacking), "<field>: not taken" (by the target), '' for none; the fields, '' for one not given). No value
// logged. q, the text of a search: 1-MEL_CAT_QUERY_MAX characters of UTF-8, no control ones, not blank. src, a source:
// the id or the name of a profile (mel_dl_src), of UTF-8, no control characters, room for spaces at the ends; empty -
// not given.
/**
 * @param stdClass $in
 * @return array{string, MelDlLink}
 */
function mel_dl_parse($in)
{
    $re = array('to' => '/^[a-z_]{1,20}$/D', 'type' => '/^(movie|series)$/D', 'imdb' => '/^tt[0-9]{1,10}$/D',
        'tmdb' => '/^[0-9]{1,9}$/D', 's' => '/^[0-9]{1,9}$/D', 'e' => '/^[0-9]{1,9}$/D', 'tab' => '/^(movie|series)$/D',
        'n' => '/^[1-9][0-9]{0,8}$/D',
        'q' => '/^[^\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]{1,' . MEL_CAT_QUERY_MAX . '}$/uD',
        'src' => '/^[^\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]{0,' . 2 * MEL_PROFILE_NAME_MAX . '}$/uD');
    $q = array_fill_keys(array_keys($re), '');
    foreach ($re as $k => $p)
    {
        if (!isset($in->$k))
            continue;
        $v = $in->$k;
        if (!is_string($v) || !preg_match($p, $v) || ($k === 'q' && mel_cat_query($v) === ''))
            return array($k, $q);
        $q[$k] = $v;
    }
    $all = mel_dl_targets();
    if (!isset($all[$q['to']]))
        return array($q['to'] === '' ? 'to: none' : 'to', $q);
    $t = $all[$q['to']];
    $need = array_merge($t['need'], $t['se'] && $q['type'] === 'series' ? array('s', 'e') : array());
    $take = array_merge(array('to'), $t['id'], $need, $t['may']);
    foreach ($q as $k => $v)
    {
        if ($v !== '' && !in_array($k, $take, true))
            return array("$k: not taken", $q);
    }
    $ids = 0;
    foreach ($t['id'] as $k)
        $ids += $q[$k] !== '' ? 1 : 0;
    if ($t['id'] && $ids !== 1)
        return array($ids ? 'id: ' . implode(' and ', $t['id']) : 'id: none', $q);
    foreach ($need as $k)
    {
        if ($q[$k] === '')
            return array("$k: none", $q);
    }
    return array('', $q);
}

// handle_user_input of control open: the screen of the target of the link, null for a wrong link (logged). The
// line of the log: the target and its fields, the text of a search not (as the search logs none).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_dl_open($in)
{
    list($bad, $q) = mel_dl_parse($in);
    if ($bad !== '')
    {
        mel_log("open: bad $bad");
        return null;
    }
    $tag = array($q['to']);
    foreach (array('type' => '', 'imdb' => '', 'tmdb' => 'tmdb:', 'tab' => '', 'n' => '#') as $k => $pre)
    {
        if ($q[$k] !== '')
            $tag[] = $pre . $q[$k];
    }
    mel_log('open: ' . implode(' ', $tag) . ($q['s'] !== '' ? " S{$q['s']}E{$q['e']}" : ''));
    $all = mel_dl_targets();
    return call_user_func($all[$q['to']]['fn'], $q);
}

// The own card of link $q, kept, of the ids alone: made of the meta (mel_oc_of_meta: its tile, the card of the Dune
// by it), as ENTER on a tile makes it (mel_oc_enter). No meta: the card in memory if there is one (another screen of
// it may be under this one: not to lose its name), else the card of the ids, no name, as of a tile whose meta failed.
// -> array(the key "<type>:<id of the link>", the card).
/**
 * @param MelDlLink $q
 * @return array{string, MelOcCard}|null
 */
function mel_dl_card_of($q)
{
    $tile = mel_oc_id_tile($q['type'], $q['imdb'] !== '' ? $q['imdb'] : "tmdb:{$q['tmdb']}");
    if (!$tile)
        return null;
    $key = $q['type'] . ':' . mel_cm_id($tile);
    $r = mel_oc_of_meta($key, $tile);
    $card = $r['card'];
    $was = !$card && isset(MelOc::$cards[$key]);
    if ($was)
        $card = MelOc::$cards[$key];
    if (!$card)
        $card = mel_oc_build($key, $tile, null, mel_card_id($tile), mel_catv_lang());
    mel_log("open: own $key: card " . ($card['dune'] !== '' ? $card['dune'] : 'none') . ', meta ' .
        ($r['err'] !== '' ? "failed ({$r['err']})" . ($was ? ', the card in memory' : '') : 'ok') . ', imdb ' .
        ($card['imdb'] !== '' ? $card['imdb'] : 'none'));
    return array($key, $card);
}

// to=card: the own card screen "card:<key>" (its rows of posters asked by its timer, as after ENTER on a tile).
/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_card($q)
{
    $kc = mel_dl_card_of($q);
    if (!$kc)
        return null;
    list($key, $card) = $kc;
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "card:$key",
            PluginOpenFolderActionData::caption => $card['tile']['name'] !== '' ? $card['tile']['name'] : '%tr%plugin_caption'));
}

// The profile of src of a link, trimmed as the page trims a name (mel_profile_name): of the id if it is one
// (mel_profile_id_ok) of a profile there, else of the name: the same, else the same of no case and ё as е (two such:
// the first) -> array(its id, '' none; why none: 'no such source', 'not ready' (mel_profile_ready), '' for one).
/**
 * @param string $src
 * @return array{string, string}
 */
function mel_dl_src($src)
{
    $s = Mel::$file;
    if (!$s)
        return array('', 'no such source');
    $name = mel_profile_name($src);
    $k = mel_profile_id_ok($name) ? mel_profile_index($s, $name) : -1;
    if ($k < 0)
        $k = mel_profile_find($s, $name);
    $low = mb_strtolower(str_replace(array('ё', 'Ё'), 'е', $name), 'UTF-8');
    foreach ($k < 0 && $name !== '' ? $s['profiles'] : array() as $i => $p)
    {
        if (mb_strtolower(str_replace(array('ё', 'Ё'), 'е', $p['name']), 'UTF-8') === $low)
        {
            $k = $i;
            break;
        }
    }
    if ($k < 0)
        return array('', 'no such source');
    if (!mel_profile_ready($s['profiles'][$k]))
        return array('', 'not ready');
    return array($s['profiles'][$k]['id'], '');
}

// to=streams: the list of the card of the link, as "Watch" of the card opens it (a movie) or ENTER on episode e of
// season s of the meta (a series, mel_oc_ep_watch: one not out yet - its message; the episode offered - by H and the
// files on the disk, mel_oc_lib_pick, as the screen of the card); not in the meta, or no meta:
// asked as s, e. Only the list: play_action gets no selected_control_id, so no resume starts a player (mel_resume).
// src given: the list from that profile (mel_dl_src), as "Смотреть из…" of the card (mel_oc_watch_from): nothing
// remembered, the record and the main one not used; no such profile or not ready: null, logged, nothing asked.
/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_streams($q)
{
    $src = '';
    if ($q['src'] !== '')
    {
        list($src, $why) = mel_dl_src($q['src']);
        if ($why !== '')
        {
            mel_log("open: bad src: $why");
            return null;
        }
    }
    $kc = mel_dl_card_of($q);
    if (!$kc)
        return null;
    $c = $kc[1];
    if ($q['type'] !== 'series')
        return mel_oc_watch($c, -1, -1, null, $src);
    $s = intval($q['s']);
    $e = intval($q['e']);
    $x = mel_oc_ep($c, $s, $e);
    if (!$x)
        return mel_oc_watch($c, $s, $e, null, $src);
    list($ep) = mel_oc_lib_pick($c);
    return mel_oc_ep_watch($c, $x, $ep, $src);
}

// to=dune_card: the card of the Dune of the IMDb of the link (mel_card_id; the type, if given, picks of two cards),
// opened by file_movie as "Dune card" of the own card does (mel_card_open); none: null, logged. No local card made.
/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_dune_card($q)
{
    $tile = mel_oc_id_tile($q['type'], $q['imdb']);
    if (!$tile)
        return null;
    $c = mel_card_id($tile);
    if ($c['id'] === '')
    {
        mel_log("open: dune_card {$q['imdb']}: no card of the Dune" . ($c['none'] ? '' : ' (not asked or failed)'));
        return null;
    }
    return mel_card_open($c['id']);
}

// to=person: the page "person:<tmdb>" (view_person.php knows a person by the id alone; what the row knew of it,
// if it was opened before: mel_pp_entry).
/**
 * @param MelDlLink $q
 * @return MelAction
 */
function mel_dl_person($q)
{
    $p = mel_pp_entry($q['tmdb']);
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "person:{$q['tmdb']}",
            PluginOpenFolderActionData::caption => $p['name'] !== '' ? $p['name'] : '%tr%plugin_caption'));
}

// to=search: the results of the search of text q (mel_catv_search_run, as the dialog of the search of the main screen
// of the catalog runs it): two rows, or "Show all" of the one type found on r25; nothing found: its message.
/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_search($q)
{
    return mel_catv_search_run($q['q'], mel_catv_main_url('movie'));
}

// to=tab: the main screen of the catalog at tab $q['tab'], the focus on the tab (as a switch of the tab).
/**
 * @param MelDlLink $q
 * @return MelAction
 */
function mel_dl_tab($q)
{
    return mel_catv_open_act(mel_catv_main_url($q['tab']), '%tr%plugin_caption');
}

// to=mylist, to=continue: the screens "Мой список" and "Продолжить" (view_my_list.php), as Fav 2 and Fav 1 open them.
/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_mylist($q)
{
    return mel_mlv_open('list', '');
}

/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_continue($q)
{
    return mel_mlv_open('continue', '');
}

// to=row: "Show all" (mel_catv_all_open) of row n of tab $q['tab'], counted as the main screen shows them: the row
// "Continue" first when it has tiles of the tab (mel_cp_items), then "Мой список" when the list has titles of it - the
// screens of all of them of the tab (mel_mlv_open of ENTER on their title), then the rows of the user of the saved
// manifest (mel_cat_user_rows: those turned off not there) it shows (mel_catv_shown_rows: on r24
// those with tiles in memory, as the last opening of the tab got them). No saved manifest, no such row: null, logged.
/**
 * @param MelDlLink $q
 * @return MelAction|null
 */
function mel_dl_row($q)
{
    $copy = mel_cat_manifest_copy();
    if (!$copy['rows'])
    {
        mel_log('open: row: no saved manifest of the catalog');
        return null;
    }
    $n = intval($q['n']);
    // n is of the rows of the screen, not of the manifest: with "Continue" and "Мой список" on the tab the same row of
    // the catalog is one or two further, without them - back.
    $local = array();
    if (mel_cp_items(array($q['tab']), 1))
        $local[] = MEL_MLV_CONT;
    if (mel_mlv_list_cells(array($q['tab']), 1))
        $local[] = MEL_MLV_LIST;
    if (isset($local[$n - 1]))
    {
        $url = $local[$n - 1] . ':' . $q['tab'];
        mel_log("open: row {$q['tab']} #$n: $url");
        return mel_catv_open_act($url, mel_tr(mel_catv_lang(), $local[$n - 1] === MEL_MLV_LIST ? 'ml_title' : 'cp_row_continue'));
    }
    $cp = count($local);
    $rows = mel_catv_shown_rows(mel_cat_user_rows($copy['rows'], Mel::$settings['cat_rows'], $q['tab']), mel_catv_loading_flag());
    if (!isset($rows[$n - 1 - $cp]))
    {
        mel_log("open: row {$q['tab']} #$n: none, " . (count($rows) + $cp) . ' rows on the tab');
        return null;
    }
    $rid = mel_catv_rid($rows[$n - 1 - $cp]);
    mel_log("open: row {$q['tab']} #$n: $rid");
    return mel_catv_all_open("cat_grid:$rid");
}
