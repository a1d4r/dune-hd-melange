<?php
// The public action "open" (public_actions of the manifest): a link
// plugin_launcher://{name=melange}{action_id=open}{to=<target>}{<field>=<value>}... opens a screen of Melange by ids.
// The shell gives the pairs to handle_user_input as they are (no URL decoding; no { or } in a value). The targets
// (aio_dl_targets): card - the own card, as ENTER on a tile (aio_oc_enter); streams - the list of streams of the card,
// as its "Watch" or ENTER on an episode (aio_oc_watch), nothing started; dune_card - the card of the Dune (file_movie);
// person - the page of a person (view_person.php); search - the results of a search of the catalog by the text, no
// dialog; tab - the catalog at a tab; row - "Show all" of a row of a tab by its number on the screen. A value off its
// pattern, a field the target does not take, one it lacks: a line of the log, no screen. Each call: a line of the log
// with the target and the ids.
// Needs main.php, catalog.php, catalog_card.php, card_meta.php, view_card.php, view_card_series.php, view_catalog.php,
// view_person.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// The targets: fn - the handler of the link; id - the fields of an id it takes, exactly one of them given (none: no
// id); need - the other fields it needs; may - those it takes if given; se - a series (type) needs s and e too (a
// movie takes none). Any other field of a link given: wrong. A new target: one more line here and its handler.
/**
 * @return array<string, array{fn: callable-string, id: list<string>, need: list<string>, may: list<string>, se: bool}>
 */
function aio_dl_targets()
{
    return array(
        'card' => array('fn' => 'aio_dl_card', 'id' => array('imdb', 'tmdb'), 'need' => array('type'), 'may' => array(), 'se' => false),
        'streams' => array('fn' => 'aio_dl_streams', 'id' => array('imdb', 'tmdb'), 'need' => array('type'), 'may' => array(),
            'se' => true),
        'dune_card' => array('fn' => 'aio_dl_dune_card', 'id' => array('imdb'), 'need' => array(), 'may' => array('type'), 'se' => false),
        'person' => array('fn' => 'aio_dl_person', 'id' => array('tmdb'), 'need' => array(), 'may' => array(), 'se' => false),
        'search' => array('fn' => 'aio_dl_search', 'id' => array(), 'need' => array('q'), 'may' => array(), 'se' => false),
        'tab' => array('fn' => 'aio_dl_tab', 'id' => array(), 'need' => array('tab'), 'may' => array(), 'se' => false),
        'row' => array('fn' => 'aio_dl_row', 'id' => array(), 'need' => array('tab', 'n'), 'may' => array(), 'se' => false));
}

// The fields of $in of a link (the others not read) -> array(why it is wrong: "<field>" (off its pattern), "<field>:
// none" (lacking), "<field>: not taken" (by the target), '' for none; the fields, '' for one not given). No value
// logged. q, the text of a search: 1-AIO_CAT_QUERY_MAX characters of UTF-8, no control ones, not blank.
/**
 * @param stdClass $in
 * @return array{string, AioDlLink}
 */
function aio_dl_parse($in)
{
    $re = array('to' => '/^[a-z_]{1,20}$/D', 'type' => '/^(movie|series)$/D', 'imdb' => '/^tt[0-9]{1,10}$/D',
        'tmdb' => '/^[0-9]{1,9}$/D', 's' => '/^[0-9]{1,9}$/D', 'e' => '/^[0-9]{1,9}$/D', 'tab' => '/^(movie|series)$/D',
        'n' => '/^[1-9][0-9]{0,8}$/D',
        'q' => '/^[^\x00-\x1F\x7F\x{0085}\x{2028}\x{2029}]{1,' . AIO_CAT_QUERY_MAX . '}$/uD');
    $q = array('to' => '', 'type' => '', 'imdb' => '', 'tmdb' => '', 's' => '', 'e' => '', 'tab' => '', 'n' => '', 'q' => '');
    foreach ($re as $k => $p)
    {
        if (!isset($in->$k))
            continue;
        $v = $in->$k;
        if (!is_string($v) || !preg_match($p, $v) || ($k === 'q' && aio_cat_query($v) === ''))
            return array($k, $q);
        $q[$k] = $v;
    }
    $all = aio_dl_targets();
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
 * @return AioAction|null
 */
function aio_dl_open($in)
{
    list($bad, $q) = aio_dl_parse($in);
    if ($bad !== '')
    {
        aio_log("open: bad $bad");
        return null;
    }
    $tag = array($q['to']);
    foreach (array('type' => '', 'imdb' => '', 'tmdb' => 'tmdb:', 'tab' => '', 'n' => '#') as $k => $pre)
    {
        if ($q[$k] !== '')
            $tag[] = $pre . $q[$k];
    }
    aio_log('open: ' . implode(' ', $tag) . ($q['s'] !== '' ? " S{$q['s']}E{$q['e']}" : ''));
    $all = aio_dl_targets();
    return call_user_func($all[$q['to']]['fn'], $q);
}

// The tile of the ids of link $q alone, no name (the id of the meta, aio_cm_id: the IMDb, else tmdb:<n>).
/**
 * @param AioDlLink $q
 * @return AioCatTile|null
 */
function aio_dl_tile($q)
{
    $t = aio_cat_tile(array('id' => $q['imdb'] !== '' ? $q['imdb'] : "tmdb:{$q['tmdb']}", 'name' => '-',
        'type' => $q['type']), $q['type']);
    if ($t)
        $t['name'] = '';
    return $t;
}

// The own card of link $q, kept, as ENTER on a tile makes it (aio_oc_enter) but of the ids alone: the meta first
// (aio_cm_get) and its tile in place of the tile of the ids (the name, the year, the pictures: a tile of a row has
// them), the type of the link; then the card of the Dune (aio_card_id, by the year of the meta too) -> array(the key
// "<type>:<id of the link>", the card). A failed meta: the card of the ids, no name, as one of a tile whose meta failed.
/**
 * @param AioDlLink $q
 * @return array{string, AioOcCard}|null
 */
function aio_dl_card_of($q)
{
    $tile = aio_dl_tile($q);
    if (!$tile)
        return null;
    $key = $q['type'] . ':' . aio_cm_id($tile);
    $g = aio_cm_get($tile);
    if ($g['meta'] && $g['meta']['tile'])
    {
        $tile = $g['meta']['tile'];
        $tile['type'] = $q['type'];
    }
    $card = aio_oc_build($key, $tile, $g['meta'], aio_card_id($tile), aio_catv_lang());
    aio_log("open: own $key: card " . ($card['dune'] !== '' ? $card['dune'] : 'none') . ', meta ' .
        ($g['err'] !== '' ? "failed ({$g['err']})" : 'ok') . ', imdb ' . ($card['imdb'] !== '' ? $card['imdb'] : 'none'));
    return array($key, $card);
}

// to=card: the own card screen "card:<key>" (its rows of posters asked by its timer, as after ENTER on a tile).
/**
 * @param AioDlLink $q
 * @return AioAction|null
 */
function aio_dl_card($q)
{
    $kc = aio_dl_card_of($q);
    if (!$kc)
        return null;
    list($key, $card) = $kc;
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "card:$key",
            PluginOpenFolderActionData::caption => $card['tile']['name'] !== '' ? $card['tile']['name'] : '%tr%plugin_caption'));
}

// to=streams: the list of the card of the link, as "Watch" of the card opens it (a movie) or ENTER on episode e of
// season s of the meta (a series: the season asked by aio_cm_req_s, the cursor on the release of the history when it
// is of this episode, aio_oc_pick); not in the meta, or no meta: asked as s, e. Only the list: play_action gets no
// selected_control_id, so no resume starts a player (aio_resume).
/**
 * @param AioDlLink $q
 * @return AioAction|null
 */
function aio_dl_streams($q)
{
    $kc = aio_dl_card_of($q);
    if (!$kc)
        return null;
    $c = $kc[1];
    if ($q['type'] !== 'series')
        return aio_oc_watch($c);
    $s = intval($q['s']);
    $e = intval($q['e']);
    $x = aio_oc_ep($c, $s, $e);
    if (!$x || !$c['meta'])
        return aio_oc_watch($c, $s, $e);
    $qs = aio_cm_req_s($c['meta'], $x['s']);
    list($ep) = aio_oc_pick($c);
    return aio_oc_watch($c, $qs, $x['e'], $ep['q'] === $qs && $ep['e'] === $x['e'] ? $ep['hash'] : '');
}

// to=dune_card: the card of the Dune of the IMDb of the link (aio_card_id; the type, if given, picks of two cards),
// opened by file_movie as "Dune card" of the own card does (aio_card_open); none: null, logged. No local card made.
/**
 * @param AioDlLink $q
 * @return AioAction|null
 */
function aio_dl_dune_card($q)
{
    $tile = aio_dl_tile($q);
    if (!$tile)
        return null;
    $c = aio_card_id($tile);
    if ($c['id'] === '')
    {
        aio_log("open: dune_card {$q['imdb']}: no card of the Dune" . ($c['none'] ? '' : ' (not asked or failed)'));
        return null;
    }
    return aio_card_open($c['id']);
}

// to=person: the page "person:<tmdb>" (view_person.php knows a person by the id alone; what the row knew of it,
// if it was opened before: aio_pp_entry).
/**
 * @param AioDlLink $q
 * @return AioAction
 */
function aio_dl_person($q)
{
    $p = aio_pp_entry($q['tmdb']);
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "person:{$q['tmdb']}",
            PluginOpenFolderActionData::caption => $p['name'] !== '' ? $p['name'] : '%tr%plugin_caption'));
}

// to=search: the results of the search of text q (aio_catv_search_run, as the dialog of the search of the main screen
// of the catalog runs it): two rows, or "Show all" of the one type found on r25; nothing found: its message.
/**
 * @param AioDlLink $q
 * @return AioAction|null
 */
function aio_dl_search($q)
{
    return aio_catv_search_run($q['q'], aio_catv_main_url('movie'));
}

// to=tab: the main screen of the catalog at tab $q['tab'], the focus on the tab (as a switch of the tab).
/**
 * @param AioDlLink $q
 * @return AioAction
 */
function aio_dl_tab($q)
{
    return aio_catv_open_act(aio_catv_main_url($q['tab']), '%tr%plugin_caption');
}

// to=row: "Show all" (aio_catv_all_open) of row n of tab $q['tab'], counted as the main screen shows them: the rows of
// the user of the saved manifest (aio_cat_user_rows: those turned off not there), on r25 without those come empty
// within the hour (aio_catv_main_view hides them). No saved manifest, no such row: null, logged.
/**
 * @param AioDlLink $q
 * @return AioAction|null
 */
function aio_dl_row($q)
{
    $copy = aio_cat_manifest_copy();
    if (!$copy['rows'])
    {
        aio_log('open: row: no saved manifest of the catalog');
        return null;
    }
    $n = intval($q['n']);
    $i = 0;
    $loading = aio_catv_loading_flag();
    foreach (aio_cat_user_rows($copy['rows'], Aio::$settings['cat_rows'], $q['tab']) as $row)
    {
        $rid = aio_catv_rid($row);
        if ($loading && aio_catv_is_empty($rid))
            continue;
        if (++$i === $n)
        {
            aio_log("open: row {$q['tab']} #$n: $rid");
            return aio_catv_all_open("cat_grid:$rid");
        }
    }
    aio_log("open: row {$q['tab']} #$n: none, $i rows on the tab");
    return null;
}
