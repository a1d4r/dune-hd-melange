<?php
// The row "Continue" (Продолжить) first on the screens of rows of the catalog and the tab "TV": the titles of the
// history of Melange begun and not watched, a series at its next episode, of the state of the history (progress.php)
// with no request; its tiles by movie_id, ENTER the own card (the pair of links.php, asked once when none).
// The keys of the translations of it are "cp_..." (catalog progress); "cp" in the names of its code is "Continue".
// Needs progress.php, links.php, history.php, main.php (aio_tr, aio_log, aio_error, aio_data_dir, aio_file_sig,
// aio_write_file, AIO_SUP_ID), catalog.php (aio_cat_tile, aio_cat_row_state), catalog_card.php (aio_card_open,
// aio_card_id), card_meta.php (aio_cm_id, aio_cm_get), view_card_series.php (aio_cm_eps_before), view_catalog.php
// (aio_catv_*), view_card.php (aio_oc_enter), cp_meta.php (aio_cpm_tile, AioCpm), tv_tab.php (aio_tv_flag).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// The rid of the row: its tiles are "continue:<movie_id>" (aio_catv_tile), its rows "items:continue", "title:continue".
define('AIO_CP_RID', 'continue');
// In data_dir: the cards taken off the row ("Убрать из «Продолжить»"), movie_id => the end of the latest record then.
define('AIO_CP_HIDDEN_FILE', 'cp_hidden.json');
// Cards kept in it at most: the oldest ends go first.
define('AIO_CP_HIDDEN_MAX', 500);

class AioCp
{
    // AIO_CP_HIDDEN_FILE as read ($hidden_sig: aio_file_sig of it then); null: not read.
    /** @var array<string, int>|null */
    public static $hidden = null;
    /** @var string */
    public static $hidden_sig = '';
}

// --- What the row shows.

// The record $w is of a release of Melange (uid "aio:..."): a local file or another supplier opens no own card.
/**
 * @param stdClass|null $w
 * @return bool
 */
function aio_cp_ours($w)
{
    return $w !== null && isset($w->uid) && is_string($w->uid) && strpos($w->uid, AIO_SUP_ID . ':') === 0;
}

// The card $mid of the state $ent (aio_pg_index) in the row, null for none: a movie (no episode in the history, the
// pair not of a series) by its latest record, of Melange, begun (aio_pg_begun) and not watched, not marked in
// watched_movies.txt; a series by its latest episode record of Melange and its badge (aio_pg_badge: the episode the
// own card offers after the one watched, by the episodes of the pair out today; begun; watched with no list of them),
// none when all of them out are watched (all) or no episode is watched or begun. -> type, end (of the latest record, the
// order of the row), w (the record: the title, the poster), s, e (of the badge; -1 of a movie), b (the badge the row
// draws).
/**
 * @param string $mid
 * @param AioPgEntry $ent
 * @return array{type: string, end: int, w: stdClass, s: int, e: int, b: AioPgBadge|null}|null
 */
function aio_cp_item($mid, $ent)
{
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    $st = $ent['series'];
    if ($st === null && !($l && isset($l['type']) && $l['type'] === 'series'))
    {
        $w = $ent['w'];
        if ($ent['movie_mark'] || !aio_cp_ours($w) || $ent['seen'])
            return null;
        list(, , $pos, $len) = aio_wh_rec($w);
        return aio_pg_begun($pos, $len) ? array('type' => 'movie', 'end' => $ent['end'], 'w' => $w, 's' => -1, 'e' => -1,
            'b' => aio_pg_badge($ent, false, $mid)) : null;
    }
    $w = $st ? $st[0] : null;
    if (!$w || !aio_cp_ours($w))
        return null;
    $b = aio_pg_badge($ent, true, $mid);
    if (!$b || $b['all'] || $b['e'] < 0)
        return null;
    return array('type' => 'series', 'end' => $ent['end'], 'w' => $w, 's' => $b['s'], 'e' => $b['e'], 'b' => $b);
}

// The cards of the row of the types $types (movie, series), the latest records first, $max at most (the cards after them
// not looked at; the screen "Продолжить" of Fav 1: all of them, view_my_list.php), none taken off it with no record
// ending later (aio_cp_hidden): movie_id => aio_cp_item.
/**
 * @param list<string> $types
 * @param int $max
 * @return array<string, array{type: string, end: int, w: stdClass, s: int, e: int, b: AioPgBadge|null}>
 */
function aio_cp_items($types, $max = AIO_CATV_ROW_TILES)
{
    $idx = aio_pg_index();
    $ends = array();
    foreach ($idx as $mid => $ent)
        $ends[strval($mid)] = $ent['end'];
    arsort($ends);
    $hidden = aio_cp_hidden();
    $res = array();
    foreach ($ends as $mid => $end)
    {
        $mid = strval($mid);
        $it = isset($hidden[$mid]) && $end <= $hidden[$mid] ? null : aio_cp_item($mid, $idx[$mid]);
        if ($it && in_array($it['type'], $types, true))
        {
            $res[$mid] = $it;
            if (count($res) >= $max)
                break;
        }
    }
    return $res;
}

// --- The tiles.

// The movie_id of an id of a tile of the row ("continue:<movie_id>", with the mode of the screen or without), '' for
// another.
/**
 * @param string $id
 * @return string
 */
function aio_cp_mid_of($id)
{
    list(, $id) = aio_catv_id_mode($id);
    $p = AIO_CP_RID . ':';
    $mid = strncmp($id, $p, strlen($p)) === 0 ? strval(substr($id, strlen($p))) : '';
    return preg_match('/^' . AIO_DUNE_ID . '$/D', $mid) ? $mid : '';
}

// The tiles of the rows in memory (AioCat::$lists) by "<type>|imdb:<IMDb>" and "<type>|tmdb:<TMDB>", the first one of
// each: for the tiles of the whole row (aio_cp_row_defs).
/**
 * @return array<string, AioCatTile>
 */
function aio_cp_tile_map()
{
    $out = array();
    foreach (AioCat::$lists as $list)
    {
        foreach ($list['tiles'] as $t)
        {
            foreach (array('imdb' => $t['imdb_id'], 'tmdb' => $t['_tmdbId']) as $k => $v)
            {
                if ($v !== '' && !isset($out["{$t['type']}|$k:$v"]))
                    $out["{$t['type']}|$k:$v"] = $t;
            }
        }
    }
    return $out;
}

// The first tile of the rows in memory of type $type and IMDb $imdb, else of TMDB $tmdb ('': none), as aio_cp_tile_map
// gives it, of one pass (a tile of the info panel or POP UP: no map of thousands of tiles); null for none.
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @return AioCatTile|null
 */
function aio_cp_tile_find($type, $imdb, $tmdb)
{
    $by_tmdb = null;
    foreach (AioCat::$lists as $list)
    {
        foreach ($list['tiles'] as $t)
        {
            if ($t['type'] !== $type)
                continue;
            if ($imdb !== '' && $t['imdb_id'] === $imdb)
                return $t;
            if (!$by_tmdb && $tmdb !== '' && $t['_tmdbId'] === $tmdb)
                $by_tmdb = $t;
        }
    }
    return $by_tmdb;
}

// The best tile of no request of a title of type $type with IMDb $imdb, else TMDB $tmdb ('': none): a tile of a row in
// memory ($map, aio_cp_tile_map; null: aio_cp_tile_find; it has a description), else the tile of its meta kept
// (aio_cpm_tile, cp_meta.php: asked lazily, or of the own card); null: neither (the caller has a tile of its own
// record: of the history, of "Мой список", of the Favorites of the Dune).
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param array<string, AioCatTile>|null $map
 * @return AioCatTile|null
 */
function aio_cp_best_tile($type, $imdb, $tmdb, $map = null)
{
    if ($map === null)
        $t = aio_cp_tile_find($type, $imdb, $tmdb);
    else if ($imdb !== '' && isset($map["$type|imdb:$imdb"]))
        $t = $map["$type|imdb:$imdb"];
    else
        $t = $tmdb !== '' && isset($map["$type|tmdb:$tmdb"]) ? $map["$type|tmdb:$tmdb"] : null;
    return $t ? $t : aio_cpm_tile($type, $imdb, $tmdb);
}

// The type, IMDb and TMDB ('' none) of card $mid of the history by its pair (links.php); no type in it: a series if
// $series (the card has episodes), else a movie.
/**
 * @param string $mid
 * @param bool $series
 * @return array{string, string, string}
 */
function aio_cp_ids($mid, $series)
{
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : array();
    $type = isset($l['type']) ? $l['type'] : ($series ? 'series' : 'movie');
    $imdb = isset($l['imdb']) ? $l['imdb'] : '';
    $tmdb = $type === 'series' ? (isset($l['tmdbtv']) ? $l['tmdbtv'] : '') : (isset($l['tmdb']) ? $l['tmdb'] : '');
    return array($type, $imdb, $tmdb);
}

// The tile of card $mid of the history for the info panel and ENTER: the best tile of its pair (aio_cp_best_tile: of a
// row in memory, else of the meta kept), else one of the pair and the latest record (title, poster); no pair: of the
// record alone, id "dune:<movie_id>", no ids (ENTER asks the pair, aio_cp_enter). null: no record. $rec: the tile is of
// the pair and the record alone.
/**
 * @param string $mid
 * @param array<string, AioCatTile>|null $map
 * @param bool $rec
 * @param-out bool $rec
 * @return AioCatTile|null
 */
function aio_cp_tile($mid, $map = null, &$rec = false)
{
    $rec = false;
    $idx = aio_pg_index();
    $ent = isset($idx[$mid]) ? $idx[$mid] : null;
    $w = $ent ? ($ent['series'] && $ent['series'][0] ? $ent['series'][0] : $ent['w']) : null;
    if (!$w)
        return null;
    list($type, $imdb, $tmdb) = aio_cp_ids($mid, $ent['series'] ? true : false);
    $t = aio_cp_best_tile($type, $imdb, $tmdb, $map);
    if ($t)
        return $t;
    $rec = true;
    $id = $imdb !== '' ? $imdb : ($tmdb !== '' ? "tmdb:$tmdb" : "dune:$mid");
    return aio_cat_tile(array('id' => $id, 'name' => isset($w->title) ? $w->title : '-', 'type' => $type, 'imdb_id' => $imdb,
        '_tmdbId' => $tmdb, 'poster' => isset($w->icon) ? $w->icon : ''), $type);
}

// The badge of card $mid of the history (aio_pg_badge by the type of its tile), null for none.
/**
 * @param string $mid
 * @param string $type
 * @return AioPgBadge|null
 */
function aio_cp_badge($mid, $type)
{
    $idx = aio_pg_index();
    return isset($idx[$mid]) ? aio_pg_badge($idx[$mid], $type === 'series', $mid) : null;
}

// The cells of the row of the types $types, $max at most (aio_cp_items), by "continue:<movie_id>": the item (the title
// and the poster of the record, the plate of the rating of a tile of a row in memory or of the meta kept, the badge)
// and its tile; $miss: those of a pair with neither, in their order (array(type, IMDb, TMDB): the lazy meta,
// AioCpm::$miss and AioCpm::$more).
/**
 * @param list<string> $types
 * @param int $max
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return array<string, array{item: array<string, mixed>, tile: AioCatTile}>
 */
function aio_cp_tile_items($types, $max, &$miss = null)
{
    $out = array();
    $map = null;
    $miss = array();
    foreach (aio_cp_items($types, $max) as $mid => $it)
    {
        if ($map === null)
            $map = aio_cp_tile_map();
        $t = aio_cp_tile($mid, $map, $rec);
        if (!$t)
            continue;
        if ($rec && ($t['imdb_id'] !== '' || $t['_tmdbId'] !== ''))
            $miss[] = array($t['type'], $t['imdb_id'], $t['_tmdbId']);
        $id = AIO_CP_RID . ":$mid";
        $item = aio_catv_item(aio_catv_tile_id($id), isset($it['w']->title) ? aio_clean($it['w']->title) : $t['name'],
            aio_img(isset($it['w']->icon) && is_string($it['w']->icon) ? $it['w']->icon : ''));
        $stickers = aio_catv_rating_on() ? aio_catv_rate_stickers($t['imdbRating']) : array();
        if ($it['b'])
            $stickers = array_merge($stickers, aio_pg_stickers($it['b']));
        if ($stickers)
            $item[PluginRegularItem::stickers] = $stickers;
        $out[$id] = array('item' => $item, 'tile' => $t);
    }
    return $out;
}

// The row of the types $types ($tab: 'movie', 'series', '' both) for a screen of rows: its title and tiles
// (aio_cp_tile_items), no "Show all" tile (ENTER on its title: the screen of all of them of its type), no flag; none
// when it is empty. $tiles: its tiles by "continue:<movie_id>" (the file of the tab "TV"); $miss: those of a pair with
// neither, in its order (the lazy meta of a main screen, AioCpm::$miss: aio_catv_main_view puts them there, the tab
// "TV" not).
/**
 * @param string $tab
 * @param string $lang
 * @param array<string, AioCatTile>|null $tiles
 * @param-out array<string, AioCatTile> $tiles
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array<string, mixed>>
 */
function aio_cp_row_defs($tab, $lang, &$tiles = null, &$miss = null)
{
    $tiles = array();
    $items = array();
    $cells = aio_cp_tile_items($tab === '' ? array('movie', 'series') : array($tab), AIO_CATV_ROW_TILES, $miss);
    foreach ($cells as $id => $c)
    {
        $items[] = $c['item'];
        $tiles[$id] = $c['tile'];
    }
    if (!$items)
        return array();
    $row = array('key' => AIO_CP_RID, 'type' => $tab !== '' ? $tab : 'movie', 'id' => AIO_CP_RID,
        'name' => aio_tr($lang, 'cp_row_continue'), 'genre' => '', 'home' => false, 'group' => '');
    $defs = aio_catv_row_defs(aio_catv_list(aio_cat_row_state($row), array(), false), $lang, $items, 0, AIO_CP_RID);
    // ENTER on its title: all the cards of its type ("Продолжить" of view_my_list.php), as "Show all" of a row.
    $defs[1][PluginRow::show_all_action] = aio_catv_open_act(AIO_MLV_CONT . ($tab !== '' ? ":$tab" : ''), $row['name']);
    aio_log(sprintf('continue: %s: %d tiles', $tab !== '' ? $tab : 'all', count($items)));
    return $defs;
}

// ENTER on the tile of card $mid of the row: "Своя карточка" off - the card of the Dune at once (no pair needed); else
// the own card (aio_oc_enter, the card of the Dune known, its rating of Kinopoisk not: asked as of a card not in
// memory). No pair: one request of it now (aio_pg_link_now, kept for good); failed (no connection, no dune_auth) - the
// message cp_err_link; the card has no IMDb and no TMDB - err_no_imdb. No record of it: the screen is old.
/**
 * @param string $mid
 * @param string $lang
 * @return AioAction
 */
function aio_cp_enter($mid, $lang)
{
    if (!Aio::$settings['cat_card'])
        return aio_card_open($mid);
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    if (!$l || isset($l['fail']))
    {
        $r = aio_pg_link_now($mid, AIO_PG_LINK_S);
        aio_log("continue: ENTER: the pair asked: {$r['st']}");
        if ($r['st'] === 'fail')
            return aio_error('cp_err_link');
    }
    $t = aio_cp_tile($mid);
    if (!$t)
    {
        aio_log('continue: ENTER: no record of the card');
        return aio_error('cat_outdated');
    }
    if (aio_cm_id($t) === '')
        return aio_error('err_no_imdb');
    return aio_oc_enter($t, $lang, $mid);
}

// --- "Убрать из «Продолжить»": the history is not touched (the shell has no way to delete a record of it).

// The cards taken off the row: movie_id => the end of their latest record then (aio_cp_hide). From memory, read again
// when the file is another one.
/**
 * @return array<string, int>
 */
function aio_cp_hidden()
{
    $sig = aio_file_sig(AIO_CP_HIDDEN_FILE);
    if (AioCp::$hidden !== null && $sig === AioCp::$hidden_sig)
        return AioCp::$hidden;
    $j = $sig !== '' ? json_decode(strval(file_get_contents(aio_data_dir() . '/' . AIO_CP_HIDDEN_FILE)), true) : null;
    $out = array();
    foreach (is_array($j) && isset($j['hidden']) && is_array($j['hidden']) ? $j['hidden'] : array() as $mid => $end)
    {
        if (preg_match('/^' . AIO_DUNE_ID . '$/D', strval($mid)) && is_int($end))
            $out[strval($mid)] = $end;
    }
    AioCp::$hidden = $out;
    AioCp::$hidden_sig = $sig;
    return $out;
}

// Card $mid off the row till a record of it ends later than its latest one now (aio_pg_index), written whole
// (aio_write_file: a temporary file and rename), AIO_CP_HIDDEN_MAX at most. -> written.
/**
 * @param string $mid
 * @return bool
 */
function aio_cp_hide($mid)
{
    $idx = aio_pg_index();
    if (!isset($idx[$mid]) || aio_data_dir() === '')
        return false;
    $hidden = aio_cp_hidden();
    unset($hidden[$mid]);
    $hidden[$mid] = $idx[$mid]['end'];
    if (count($hidden) > AIO_CP_HIDDEN_MAX)
    {
        asort($hidden);
        $hidden = array_slice($hidden, count($hidden) - AIO_CP_HIDDEN_MAX, null, true);
    }
    $json = json_encode(array('v' => 1, 'hidden' => (object) $hidden));
    $ok = is_string($json) && aio_write_file(aio_data_dir() . '/' . AIO_CP_HIDDEN_FILE, $json);
    AioCp::$hidden = null;
    return $ok;
}

// The cards taken off the row and kept off it now: of the history (aio_pg_index) with no record ending later, in the
// row else (aio_cp_item), both types. A card watched again or out of the history since is not counted. -> how many.
/**
 * @return int
 */
function aio_cp_hidden_n()
{
    $idx = aio_pg_index();
    $n = 0;
    foreach (aio_cp_hidden() as $mid => $end)
    {
        $mid = strval($mid);
        if (isset($idx[$mid]) && $idx[$mid]['end'] <= $end && aio_cp_item($mid, $idx[$mid]))
            $n++;
    }
    return $n;
}

// "Вернуть убранные": none taken off the row, the file written whole as aio_cp_hide does (every key, of a card of the
// Dune or "m-..."). -> written.
/**
 * @return bool
 */
function aio_cp_unhide()
{
    if (aio_data_dir() === '')
        return false;
    $json = json_encode(array('v' => 1, 'hidden' => (object) array()));
    $ok = is_string($json) && aio_write_file(aio_data_dir() . '/' . AIO_CP_HIDDEN_FILE, $json);
    AioCp::$hidden = null;
    return $ok;
}

// --- POP UP of a tile of the catalog and the tab "TV".

// The card of the Dune of tile $t known with no request: of its id of the row "Continue" ($mid; '' another tile), else by
// the pairs (aio_pg_tile_card); '' for none. The one rule of "the card of a title" of POP UP (the marks, "Мой список").
/**
 * @param AioCatTile $t
 * @param string $mid
 * @return string
 */
function aio_cp_card_known($t, $mid)
{
    return $mid !== '' ? $mid : aio_pg_tile_card($t, aio_pg_index());
}

// The card of the Dune of tile $t now: known (aio_cp_card_known: of its id of the row "Continue" $mid, by the pairs),
// else asked (aio_card_id, as ENTER: the pair kept). -> array(movie_id, '' none; why none: cp_err_mark - no answer,
// card_none_title - the Dune has no card of it).
/**
 * @param AioCatTile $t
 * @param string $mid
 * @return array{string, string}
 */
function aio_cp_card_of($t, $mid)
{
    $mid = aio_cp_card_known($t, $mid);
    if ($mid !== '')
        return array($mid, '');
    $c = aio_card_id($t);
    return array($c['id'], $c['id'] !== '' ? '' : ($c['none'] ? 'card_none_title' : 'cp_err_mark'));
}

// The items of POP UP of tile $id of screen $from (a main screen of the catalog, an EPF of "TV") of the row "Continue"
// and the marks (aio_catv_tile_menu puts them first): on the row "Continue" "Убрать из «Продолжить»", then "Отметить
// просмотренным" / "Снять отметку" (aio_pg_mark_kind; the card not known yet: "Отметить", asked at it). None but on a
// tile of a title (aio_catv_tile).
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_cp_menu_items($id, $from, $lang)
{
    $t = aio_catv_tile($id, aio_tv_is_from($from));
    if (!$t)
        return array();
    $mid = aio_cp_mid_of($id);
    $items = array();
    $p = array('from' => $from, 'item' => $id);
    if ($mid !== '')
        $items[] = array(GuiMenuItemDef::caption => aio_tr($lang, 'cp_hide'), GuiMenuItemDef::icon_url => AIO_CATV_ICONS . 'cancel_small.png',
            GuiMenuItemDef::action => aio_input('cat_hide', $p));
    $w = aio_pg_mark_kind(aio_cp_card_known($t, $mid), $t['type']);
    if ($w !== '')
        $items[] = array(GuiMenuItemDef::caption => aio_tr($lang, $w === 'on' ? 'cp_mark_on' : 'card_mark_off'),
            GuiMenuItemDef::icon_url => AIO_CATV_ICONS . 'watched_small.png',
            GuiMenuItemDef::action => aio_input('cat_mark', $p + array('w' => $w), AIO_CARD_DELAY));
    return $items;
}

// "Убрать из «Продолжить»" of tile $in->item of screen $in->from: off the row (aio_cp_hide), the screen anew.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_cp_hide_act($in)
{
    $mid = aio_cp_mid_of(aio_catv_in($in, 'item'));
    if ($mid === '')
        return null;
    $ok = aio_cp_hide($mid);
    aio_log("continue: $mid off the row" . ($ok ? '' : ', not written'));
    return aio_catv_tile_after(aio_catv_in($in, 'from'), 'off the row "Continue"');
}

// "Вернуть убранные (N)" of POP UP of the screen "Продолжить" $from (aio_mlv_menu_items): all back to the row
// (aio_cp_unhide), no message, as "Убрать"; the tab "TV" built anew (aio_tv_flag) and the screens of the row by one
// invalidate (aio_catv_mains_anew: on r25 the main screens of the catalog under it too), $from on r24 too.
/**
 * @param string $from
 * @return AioAction|null
 */
function aio_cp_unhide_act($from)
{
    $n = aio_cp_hidden_n();
    $ok = aio_cp_unhide();
    aio_log("continue: unhide: $n" . ($ok ? '' : ', not written'));
    $why = 'back to the row "Continue"';
    aio_tv_flag($why);
    return aio_catv_mains_anew(null, $why, aio_catv_screen_of($from));
}

// "Отметить просмотренным" / "Снять отметку" ($in->w on, off) of tile $in->item of screen $in->from: the card of the
// Dune (aio_cp_card_of: a request as ENTER without a pair), then update_watched_marks (aio_pg_mark_act): a movie -
// movie_ids, off also the uid of its latest record if marked (aio_wh_movie_off_ids); a series on - the whole of it (the
// owner): movie_ids and the marks of the own card of every episode out (the meta asked now, aio_cm_get, and
// aio_cm_eps_before; its episodes to the pair, aio_pg_link_meta: the dates of those not out) with the uids of the records of our releases of them (aio_wh_mark_ids), as "Отметить предыдущие"
// of the own card; a tile of the row with no pair ("dune:<movie_id>") gets it first (aio_pg_link_now, as ENTER); a
// series off - movie_ids and every uid of the card marked (aio_wh_marked_of: no request, no meta). post_action:
// aio_catv_tile_after. No card, no pair, no meta: the message cp_err_mark (the reason in the log).
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_cp_mark($in)
{
    $a = aio_catv_tile_act($in, array('on', 'off'));
    $t = $a['t'];
    if (!$t)
        return $a['err'];
    $from = $a['from'];
    $w = $a['w'];
    $cp = aio_cp_mid_of($a['id']);
    list($mid, $err) = aio_cp_card_of($t, $cp);
    if ($mid === '')
        return aio_error($err);
    $ids = array();
    if ($t['type'] === 'series' && $w === 'off')
        $ids = aio_wh_marked_of($mid);
    else if ($t['type'] === 'series')
    {
        $links = aio_pg_links();
        if ($cp !== '' && (!isset($links[$mid]) || isset($links[$mid]['fail'])))
        {
            $r = aio_pg_link_now($mid, AIO_PG_LINK_S);
            aio_log("continue: mark: the pair asked: {$r['st']}");
            $t = $r['st'] === 'fail' ? null : aio_cp_tile($mid);
            if (!$t)
                return aio_error('cp_err_mark');
        }
        if (aio_cm_id($t) === '')
            return aio_error('err_no_imdb');
        $g = aio_cm_get($t);
        if (!$g['meta'])
        {
            aio_log("continue: mark: no meta ({$g['err']})");
            return aio_error('cp_err_mark');
        }
        aio_pg_link_meta($mid, $g['meta']);
        $ids = aio_wh_mark_ids($mid, aio_cm_eps_before($g['meta'], PHP_INT_MAX, 0));
    }
    else if ($w === 'off')
        $ids = aio_wh_movie_off_ids($mid);
    aio_log("continue: mark $w of $mid ({$t['type']}): " . count($ids) . ' uids');
    return aio_pg_mark_act($ids, array($mid), $w === 'on', aio_catv_tile_after($from, "a mark $w"));
}
