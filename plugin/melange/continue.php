<?php
// The row "Continue" (Продолжить) first on the screens of rows of the catalog and the tab "TV": the titles of the
// history of Melange begun and not watched, a series at its next episode, of the state of the history (progress.php)
// with no request; its tiles by movie_id, ENTER the own card (the pair of links.php, asked once when none).
// Needs progress.php, links.php, history.php, main.php (aio_tr, aio_log, aio_error, aio_data_dir, aio_file_sig,
// aio_write_file, AIO_SUP_ID), catalog.php (aio_cat_tile, aio_cat_row_state), catalog_card.php (aio_card_open),
// card_meta.php (aio_cm_id), view_catalog.php (aio_catv_*), view_card.php (aio_oc_enter).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// The rid of the row: its tiles are "continue:<movie_id>" (aio_catv_tile), its rows "items:continue", "title:continue".
define('AIO_CP_RID', 'continue');

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
// pair not of a series) by its latest record, of Melange, begun (aio_pg_begun) and not watched; a series by its latest
// episode record, of Melange, one of its episodes watched or begun (ep, or a mark of the own card after it), at the
// episode the own card offers (aio_wh_pick by the episodes of the pair out today, aio_pg_eps; none: the episode of the
// record), none when all of them out are watched. Marked in watched_movies.txt: none. -> type, end (of the latest
// record, the order of the row), w (the record: the title, the poster), s, e (-1 of a movie).
/**
 * @param string $mid
 * @param AioPgEntry $ent
 * @return array{type: string, end: int, w: stdClass, s: int, e: int}|null
 */
function aio_cp_item($mid, $ent)
{
    if ($ent['movie_mark'])
        return null;
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    $st = $ent['series'];
    if ($st === null && !($l && isset($l['type']) && $l['type'] === 'series'))
    {
        $w = $ent['w'];
        if (!aio_cp_ours($w) || $ent['seen'])
            return null;
        list(, , $pos, $len) = aio_wh_rec($w);
        return aio_pg_begun($pos, $len) ? array('type' => 'movie', 'end' => $ent['end'], 'w' => $w, 's' => -1, 'e' => -1) : null;
    }
    $w = $st ? $st[0] : null;
    if (!$w || !aio_cp_ours($w) || (!$ent['ep'] && !aio_wh_mark_last($st[2], aio_wh_end($w))))
        return null;
    list($s, $e) = aio_wh_rec($w);
    $p = aio_wh_pick($st, $mid, aio_pg_eps($l, $s, $e));
    if ($p['kind'] === 'first')
        return null;
    return array('type' => 'series', 'end' => $ent['end'], 'w' => $w, 's' => $p['s'], 'e' => $p['e']);
}

// The cards of the row of the types $types (movie, series), the latest records first, AIO_CATV_ROW_TILES at most:
// movie_id => aio_cp_item.
/**
 * @param list<string> $types
 * @return array<string, array{type: string, end: int, w: stdClass, s: int, e: int}>
 */
function aio_cp_items($types)
{
    $out = array();
    $ends = array();
    foreach (aio_pg_index() as $mid => $ent)
    {
        $mid = strval($mid);
        $it = aio_cp_item($mid, $ent);
        if ($it && in_array($it['type'], $types, true))
        {
            $out[$mid] = $it;
            $ends[$mid] = $it['end'];
        }
    }
    arsort($ends);
    $res = array();
    foreach (array_slice(array_keys($ends), 0, AIO_CATV_ROW_TILES) as $mid)
        $res[strval($mid)] = $out[$mid];
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
// each.
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

// The tile of card $mid of the history for the info panel and ENTER: a tile of a row in memory ($map, aio_cp_tile_map;
// null: made now) of its IMDb, else of its TMDB of its type, of the pair (it has a description), else one of the pair
// and the latest record (title, poster); no pair: of the record alone, id "dune:<movie_id>", no ids (ENTER asks the
// pair, aio_cp_enter). null: no record.
/**
 * @param string $mid
 * @param array<string, AioCatTile>|null $map
 * @return AioCatTile|null
 */
function aio_cp_tile($mid, $map = null)
{
    $idx = aio_pg_index();
    $ent = isset($idx[$mid]) ? $idx[$mid] : null;
    $w = $ent ? ($ent['series'] && $ent['series'][0] ? $ent['series'][0] : $ent['w']) : null;
    if (!$w)
        return null;
    $links = aio_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : array();
    $type = isset($l['type']) ? $l['type'] : ($ent['series'] ? 'series' : 'movie');
    $imdb = isset($l['imdb']) ? $l['imdb'] : '';
    $tmdb = $type === 'series' ? (isset($l['tmdbtv']) ? $l['tmdbtv'] : '') : (isset($l['tmdb']) ? $l['tmdb'] : '');
    if ($map === null)
        $map = aio_cp_tile_map();
    foreach (array('imdb' => $imdb, 'tmdb' => $tmdb) as $k => $v)
    {
        if ($v !== '' && isset($map["$type|$k:$v"]))
            return $map["$type|$k:$v"];
    }
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

// The row of the types $types ($tab: 'movie', 'series', '' both) for a screen of rows: its title and tiles (the title
// and the poster of the record, the plate of the rating of a tile of a row in memory, the badge), no "Show all", no
// flag; none when it is empty. $tiles: its tiles by "continue:<movie_id>" (the file of the tab "TV").
/**
 * @param string $tab
 * @param string $lang
 * @param array<string, AioCatTile>|null $tiles
 * @param-out array<string, AioCatTile> $tiles
 * @return list<array<string, mixed>>
 */
function aio_cp_row_defs($tab, $lang, &$tiles = null)
{
    $tiles = array();
    $items = array();
    $map = null;
    foreach (aio_cp_items($tab === '' ? array('movie', 'series') : array($tab)) as $mid => $it)
    {
        if ($map === null)
            $map = aio_cp_tile_map();
        $t = aio_cp_tile($mid, $map);
        if (!$t)
            continue;
        $id = AIO_CP_RID . ":$mid";
        $tiles[$id] = $t;
        $item = aio_catv_item(aio_catv_tile_id($id), isset($it['w']->title) ? aio_clean($it['w']->title) : $t['name'],
            aio_img(isset($it['w']->icon) && is_string($it['w']->icon) ? $it['w']->icon : ''));
        $stickers = aio_catv_rating_on() ? aio_catv_rate_stickers($t['imdbRating']) : array();
        $b = aio_cp_badge($mid, $it['type']);
        if ($b)
            $stickers = array_merge($stickers, aio_pg_stickers($b));
        if ($stickers)
            $item[PluginRegularItem::stickers] = $stickers;
        $items[] = $item;
    }
    if (!$items)
        return array();
    $row = array('key' => AIO_CP_RID, 'type' => $tab !== '' ? $tab : 'movie', 'id' => AIO_CP_RID,
        'name' => aio_tr($lang, 'cp_row_continue'), 'genre' => '', 'home' => false, 'group' => '');
    $defs = aio_catv_row_defs(aio_catv_list(aio_cat_row_state($row), array(), false), $lang, $items);
    $defs[0][PluginRow::id] = 'title:' . AIO_CP_RID;
    $defs[0][PluginRow::group_id] = 'row:' . AIO_CP_RID;
    $defs[1][PluginRow::id] = 'items:' . AIO_CP_RID;
    $defs[1][PluginRow::group_id] = 'row:' . AIO_CP_RID;
    $defs[1][PluginRow::header_id] = AIO_CATV_HEADER . AIO_CP_RID;
    aio_log(sprintf('continue: %s: %d tiles', $tab !== '' ? $tab : 'all', count($items)));
    return $defs;
}

// ENTER on the tile of card $mid of the row: the own card (aio_oc_enter, the card of the Dune known), "Своя карточка"
// off: the card of the Dune. No pair: one request of it now (aio_pg_link_now, kept for good); failed (no connection,
// no dune_auth) - the message cp_err_link; the card has no IMDb and no TMDB - err_no_imdb. No record of it: the screen
// is old.
/**
 * @param string $mid
 * @param string $lang
 * @return AioAction
 */
function aio_cp_enter($mid, $lang)
{
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
    if (!Aio::$settings['cat_card'])
        return aio_card_open($mid);
    return aio_oc_enter($t, $lang, $mid);
}
