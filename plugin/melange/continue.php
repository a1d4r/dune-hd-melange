<?php
// The row "Continue" (Продолжить) first on the screens of rows of the catalog and the tab "TV": the titles of the
// history of Melange begun and not watched, a series at its next episode, of the state of the history (progress.php)
// with no request; its tiles by movie_id, ENTER the own card (the pair of links.php, asked once when none).
// The keys of the translations of it are "cp_..." (catalog progress); "cp" in the names of its code is "Continue".
// Needs progress.php, links.php, history.php, main.php (mel_tr, mel_log, mel_error, mel_data_dir, mel_file_sig,
// mel_write_file, MEL_SUP_ID), catalog.php (mel_cat_tile, mel_cat_row_state), catalog_card.php (mel_card_open,
// mel_card_id, mel_card_local_imdb), card_meta.php (mel_cm_id, mel_cm_get), view_card_series.php (mel_cm_eps_before),
// view_catalog.php (mel_catv_*), view_card.php (mel_oc_enter), cp_meta.php (mel_cpm_tile, MelCpm), view_my_list.php
// (mel_ml_changed), library.php (mel_lib_bd_url, mel_lib_tile_start).
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// The rid of the row: its tiles are "continue:<movie_id>" (mel_catv_tile), its rows "items:continue", "title:continue".
define('MEL_CP_RID', 'continue');
// In data_dir: the cards taken off the row ("Убрать из «Продолжить»"), movie_id => the end of the latest record then.
define('MEL_CP_HIDDEN_FILE', 'cp_hidden.json');
// Cards kept in it at most: the oldest ends go first.
define('MEL_CP_HIDDEN_MAX', 500);

class MelCp
{
    // MEL_CP_HIDDEN_FILE as read ($hidden_sig: mel_file_sig of it then); null: not read.
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
function mel_cp_ours($w)
{
    return $w !== null && isset($w->uid) && is_string($w->uid) && strpos($w->uid, MEL_SUP_ID . ':') === 0;
}

// The card $mid of the state $ent (mel_pg_index) in the row, null for none: a movie (no episode in the history, the
// pair not of a series) by its latest record, of Melange (or of the shell of a disc folder of the library), begun
// (mel_pg_begun) and not watched, not marked in watched_movies.txt; a series by its latest episode record of Melange and its badge (mel_pg_badge: the episode the
// own card offers after the one watched, by the episodes of the pair out today; begun; watched with no list of them),
// none when all of them out are watched (all) or no episode is watched or begun. -> type, end (of the latest record, the
// order of the row), w (the record: the title, the poster), s, e (of the badge; -1 of a movie), b (the badge the row
// draws).
/**
 * @param string $mid
 * @param MelPgEntry $ent
 * @return array{type: string, end: int, w: stdClass, s: int, e: int, b: MelPgBadge|null}|null
 */
function mel_cp_item($mid, $ent)
{
    $links = mel_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    $st = $ent['series'];
    if ($st === null && mel_cp_pair_type($l, false) !== 'series')
    {
        $w = $ent['w'];
        // Of Melange, or of the shell playing a disc folder of the library by its URL (mel_lib_bd_url).
        if ($ent['movie_mark'] || $ent['seen'] || !$w ||
            !(mel_cp_ours($w) || (isset($w->url) && is_string($w->url) && mel_lib_bd_url($mid, $w->url))))
            return null;
        list(, , $pos, $len) = mel_wh_rec($w);
        return mel_pg_begun($pos, $len) ? array('type' => 'movie', 'end' => $ent['end'], 'w' => $w, 's' => -1, 'e' => -1,
            'b' => mel_pg_badge($ent, false, $mid)) : null;
    }
    $w = $st ? $st[0] : null;
    if (!$w || !mel_cp_ours($w))
        return null;
    $b = mel_pg_badge($ent, true, $mid);
    if (!$b || $b['all'] || $b['e'] < 0)
        return null;
    return array('type' => 'series', 'end' => $ent['end'], 'w' => $w, 's' => $b['s'], 'e' => $b['e'], 'b' => $b);
}

// The cards of the row of the types $types (movie, series), the latest records first, $max at most (the cards after them
// not looked at; the screen "Продолжить" of Fav 1: all of them, view_my_list.php), none taken off it with no record
// ending later (mel_cp_hidden): movie_id => mel_cp_item.
/**
 * @param list<string> $types
 * @param int $max
 * @return array<string, array{type: string, end: int, w: stdClass, s: int, e: int, b: MelPgBadge|null}>
 */
function mel_cp_items($types, $max = MEL_CATV_ROW_TILES)
{
    $idx = mel_pg_index();
    $ends = array();
    foreach ($idx as $mid => $ent)
        $ends[strval($mid)] = $ent['end'];
    arsort($ends);
    $hidden = mel_cp_hidden();
    $res = array();
    foreach ($ends as $mid => $end)
    {
        $mid = strval($mid);
        $it = mel_cp_off($hidden, $mid, $end) ? null : mel_cp_item($mid, $idx[$mid]);
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
function mel_cp_mid_of($id)
{
    list(, $id) = mel_catv_id_mode($id);
    $p = MEL_CP_RID . ':';
    $mid = strncmp($id, $p, strlen($p)) === 0 ? strval(substr($id, strlen($p))) : '';
    return preg_match('/^' . MEL_DUNE_ID . '$/D', $mid) ? $mid : '';
}

// The tiles of the rows in memory (MelCat::$lists) by "<type>|imdb:<IMDb>" and "<type>|tmdb:<TMDB>", the first one of
// each: for the tiles of the whole row (mel_cp_row_defs).
/**
 * @return array<string, MelCatTile>
 */
function mel_cp_tile_map()
{
    $out = array();
    foreach (MelCat::$lists as $list)
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

// The first tile of the rows in memory of type $type and IMDb $imdb, else of TMDB $tmdb ('': none), as mel_cp_tile_map
// gives it, of one pass (a tile of the info panel or POP UP: no map of thousands of tiles); null for none.
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @return MelCatTile|null
 */
function mel_cp_tile_find($type, $imdb, $tmdb)
{
    $by_tmdb = null;
    foreach (MelCat::$lists as $list)
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

// The tile of map $map (mel_cp_tile_map) of a title of type $type with IMDb $imdb, else TMDB $tmdb ('': none); null for
// none.
/**
 * @param array<string, MelCatTile> $map
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @return MelCatTile|null
 */
function mel_cp_map_tile($map, $type, $imdb, $tmdb)
{
    if ($imdb !== '' && isset($map["$type|imdb:$imdb"]))
        return $map["$type|imdb:$imdb"];
    return $tmdb !== '' && isset($map["$type|tmdb:$tmdb"]) ? $map["$type|tmdb:$tmdb"] : null;
}

// The best tile of no request of a title of type $type with IMDb $imdb, else TMDB $tmdb ('': none): the tile of its
// meta kept (mel_cpm_tile, cp_meta.php: of a row in memory or on the disk when a row "Continue" or "Мой список" was
// built, mel_cpm_build; asked; of the own card), else a tile of a row in memory ($map, mel_cp_map_tile; null:
// mel_cp_tile_find); null: neither (the caller has a tile of its own record: of the history, of "Мой список", of the
// Favorites of the Dune).
/**
 * @param string $type
 * @param string $imdb
 * @param string $tmdb
 * @param array<string, MelCatTile>|null $map
 * @return MelCatTile|null
 */
function mel_cp_best_tile($type, $imdb, $tmdb, $map = null)
{
    $t = mel_cpm_tile($type, $imdb, $tmdb);
    if ($t)
        return $t;
    return $map === null ? mel_cp_tile_find($type, $imdb, $tmdb) : mel_cp_map_tile($map, $type, $imdb, $tmdb);
}

// The type of a card of the history by its pair $l (null: none): its type, else a series by a TMDB of a series alone
// (tmdbtv, no tmdb: the TMDB numbers of movies and series are not the same ids), else a series if $series (the card has
// episode records), else a movie.
/**
 * @param MelPgLink|array{}|null $l
 * @param bool $series
 * @return string
 */
function mel_cp_pair_type($l, $series)
{
    if (isset($l['type']))
        return $l['type'];
    if (isset($l['tmdbtv']) && !isset($l['tmdb']))
        return 'series';
    return $series ? 'series' : 'movie';
}

// The type, IMDb and TMDB ('' none) of card $mid of the history by its pair (links.php), its type by mel_cp_pair_type
// ($series: the card has episodes); a local card of Melange with no pair: a movie of the IMDb of its id
// (mel_card_local_imdb).
/**
 * @param string $mid
 * @param bool $series
 * @return array{string, string, string}
 */
function mel_cp_ids($mid, $series)
{
    $links = mel_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : array();
    $local = isset($l['imdb']) ? '' : mel_card_local_imdb($mid);
    if ($local !== '')
        return array('movie', $local, '');
    $type = mel_cp_pair_type($l, $series);
    $imdb = isset($l['imdb']) ? $l['imdb'] : '';
    $tmdb = $type === 'series' ? (isset($l['tmdbtv']) ? $l['tmdbtv'] : '') : (isset($l['tmdb']) ? $l['tmdb'] : '');
    return array($type, $imdb, $tmdb);
}

// The tile of card $mid of the history for the info panel and ENTER: the best tile of its pair (mel_cp_best_tile: of the
// meta kept, else of a row in memory), else one of the pair and the latest record (title, poster); no pair: of the
// record alone, id "dune:<movie_id>", no ids (ENTER asks the pair, mel_cp_enter). null: no record. $rec: the tile is of
// the pair and the record alone.
/**
 * @param string $mid
 * @param array<string, MelCatTile>|null $map
 * @param bool $rec
 * @param-out bool $rec
 * @return MelCatTile|null
 */
function mel_cp_tile($mid, $map = null, &$rec = false)
{
    $rec = false;
    $idx = mel_pg_index();
    $ent = isset($idx[$mid]) ? $idx[$mid] : null;
    $w = $ent ? ($ent['series'] && $ent['series'][0] ? $ent['series'][0] : $ent['w']) : null;
    if (!$w)
        return null;
    list($type, $imdb, $tmdb) = mel_cp_ids($mid, $ent['series'] ? true : false);
    $t = mel_cp_best_tile($type, $imdb, $tmdb, $map);
    if ($t)
        return $t;
    $rec = true;
    $id = $imdb !== '' ? $imdb : ($tmdb !== '' ? "tmdb:$tmdb" : "dune:$mid");
    return mel_cat_tile(array('id' => $id, 'name' => isset($w->title) ? $w->title : '-', 'type' => $type, 'imdb_id' => $imdb,
        '_tmdbId' => $tmdb, 'poster' => isset($w->icon) ? $w->icon : ''), $type);
}

// The badge of card $mid of the history (mel_pg_badge by the type of its tile), null for none.
/**
 * @param string $mid
 * @param string $type
 * @return MelPgBadge|null
 */
function mel_cp_badge($mid, $type)
{
    $idx = mel_pg_index();
    return isset($idx[$mid]) ? mel_pg_badge($idx[$mid], $type === 'series', $mid) : null;
}

// The cells of the row of the types $types, $max at most (mel_cp_items), by "continue:<movie_id>": the item (the title
// and the poster of the record, the plate of the rating of the meta kept or of a tile of a row in memory, the badges)
// and its tile; $miss: those of a pair with neither, in their order (array(type, IMDb, TMDB): the lazy meta,
// MelCpm::$miss and MelCpm::$more); $disk false: no badge "on the disk" (only $miss wanted).
/**
 * @param list<string> $types
 * @param int $max
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @param bool $disk
 * @return array<string, array{item: array<string, mixed>, tile: MelCatTile}>
 */
function mel_cp_tile_items($types, $max, &$miss = null, $disk = true)
{
    $out = array();
    $map = null;
    $miss = array();
    if ($disk)
        mel_lib_tile_start();
    foreach (mel_cp_items($types, $max) as $mid => $it)
    {
        if ($map === null)
            $map = mel_cp_tile_map();
        $t = mel_cp_tile($mid, $map, $rec);
        if (!$t)
            continue;
        if ($rec && ($t['imdb_id'] !== '' || $t['_tmdbId'] !== ''))
            $miss[] = array($t['type'], $t['imdb_id'], $t['_tmdbId']);
        $id = MEL_CP_RID . ":$mid";
        $item = mel_catv_item(mel_catv_tile_id($id), isset($it['w']->title) ? mel_clean($it['w']->title) : $t['name'],
            mel_img(isset($it['w']->icon) && is_string($it['w']->icon) ? $it['w']->icon : ''));
        $stickers = mel_catv_rating_on() ? mel_catv_rate_stickers($t['imdbRating']) : array();
        if ($disk)
            $stickers = array_merge($stickers, mel_catv_disk_stickers($t, strval($mid)));
        if ($it['b'])
            $stickers = array_merge($stickers, mel_pg_stickers($it['b']));
        if ($stickers)
            $item[PluginRegularItem::stickers] = $stickers;
        $out[$id] = array('item' => $item, 'tile' => $t);
    }
    return $out;
}

// The tiles of the row of tab $tab ('' both) of a pair with no meta, as mel_cp_row_defs gives them ($miss of
// mel_cp_tile_items, in its order): MelCpm::$miss again after a tick of the main screen paired cards (mel_pg_tick).
/**
 * @param string $tab
 * @return list<array{string, string, string}>
 */
function mel_cp_row_miss($tab)
{
    mel_cp_tile_items($tab === '' ? array('movie', 'series') : array($tab), MEL_CATV_ROW_TILES, $miss, false);
    return $miss;
}

// The movie_ids of the tiles of the row of tab $tab ('' both), in its order (mel_cp_items): their pairs asked before
// those of the other cards of the history (mel_pg_tick, mel_pg_screen_built).
/**
 * @param string $tab
 * @return list<string>
 */
function mel_cp_row_mids($tab)
{
    return array_map('strval', array_keys(mel_cp_items($tab === '' ? array('movie', 'series') : array($tab))));
}

// The row of the types $types ($tab: 'movie', 'series', '' both) for a screen of rows: its title and tiles
// (mel_cp_tile_items), no "Show all" tile (ENTER on its title: the screen of all of them of its type), no flag; none
// when it is empty. $tiles: its tiles by "continue:<movie_id>" (the file of the tab "TV"); $miss: those of a pair with
// neither, in its order (the lazy meta of a main screen, MelCpm::$miss: mel_catv_main_view puts them there, the tab
// "TV" not).
/**
 * @param string $tab
 * @param string $lang
 * @param array<string, MelCatTile>|null $tiles
 * @param-out array<string, MelCatTile> $tiles
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array<string, mixed>>
 */
function mel_cp_row_defs($tab, $lang, &$tiles = null, &$miss = null)
{
    $tiles = array();
    $items = array();
    $cells = mel_cp_tile_items($tab === '' ? array('movie', 'series') : array($tab), MEL_CATV_ROW_TILES, $miss);
    foreach ($cells as $id => $c)
    {
        $items[] = $c['item'];
        $tiles[$id] = $c['tile'];
    }
    if (!$items)
        return array();
    $row = array('key' => MEL_CP_RID, 'type' => $tab !== '' ? $tab : 'movie', 'id' => MEL_CP_RID,
        'name' => mel_tr($lang, 'cp_row_continue'), 'genre' => '', 'home' => false, 'group' => '');
    $defs = mel_catv_row_defs(mel_catv_list(mel_cat_row_state($row), array(), false), $lang, $items, 0, MEL_CP_RID);
    // ENTER on its title: all the cards of its type ("Продолжить" of view_my_list.php), as "Show all" of a row.
    $defs[1][PluginRow::show_all_action] = mel_catv_open_act(MEL_MLV_CONT . ($tab !== '' ? ":$tab" : ''), $row['name']);
    mel_log(sprintf('continue: %s: %d tiles', $tab !== '' ? $tab : 'all', count($items)));
    return $defs;
}

// ENTER on the tile of card $mid of the row: "Своя карточка" off - the card of the Dune at once (no pair needed); else
// the own card (mel_oc_enter, the card of the Dune known, its rating of Kinopoisk not: asked as of a card not in
// memory). No pair: one request of it now (mel_pg_link_now, kept for good); failed (no connection, no dune_auth) - the
// message cp_err_link; the card has no IMDb and no TMDB - err_no_imdb. No record of it: the screen is old.
/**
 * @param string $mid
 * @param string $lang
 * @return MelAction
 */
function mel_cp_enter($mid, $lang)
{
    if (!Mel::$settings['cat_card'])
        return mel_card_open($mid);
    $links = mel_pg_links();
    $l = isset($links[$mid]) ? $links[$mid] : null;
    if (!$l || isset($l['fail']))
    {
        $r = mel_pg_link_now($mid, MEL_PG_LINK_S);
        mel_log("continue: ENTER: the pair asked: {$r['st']}");
        if ($r['st'] === 'fail')
            return mel_error('cp_err_link');
    }
    $t = mel_cp_tile($mid);
    if (!$t)
    {
        mel_log('continue: ENTER: no record of the card');
        return mel_error('cat_outdated');
    }
    if (mel_cm_id($t) === '')
        return mel_error('err_no_imdb');
    return mel_oc_enter($t, $lang, $mid);
}

// --- "Убрать из «Продолжить»": the history is not touched (the shell has no way to delete a record of it).

// The cards taken off the row: movie_id => the end of their latest record then (mel_cp_hide). From memory, read again
// when the file is another one.
/**
 * @return array<string, int>
 */
function mel_cp_hidden()
{
    $sig = mel_file_sig(MEL_CP_HIDDEN_FILE);
    if (MelCp::$hidden !== null && $sig === MelCp::$hidden_sig)
        return MelCp::$hidden;
    $j = $sig !== '' ? json_decode(strval(file_get_contents(mel_data_dir() . '/' . MEL_CP_HIDDEN_FILE)), true) : null;
    $out = array();
    foreach (is_array($j) && isset($j['hidden']) && is_array($j['hidden']) ? $j['hidden'] : array() as $mid => $end)
    {
        if (preg_match('/^' . MEL_DUNE_ID . '$/D', strval($mid)) && is_int($end))
            $out[strval($mid)] = $end;
    }
    MelCp::$hidden = $out;
    MelCp::$hidden_sig = $sig;
    return $out;
}

// Card $mid off the row till a record of it ends later than its latest one now (mel_pg_index), written whole
// (mel_write_file: a temporary file and rename), MEL_CP_HIDDEN_MAX at most. -> written.
/**
 * @param string $mid
 * @return bool
 */
function mel_cp_hide($mid)
{
    $idx = mel_pg_index();
    if (!isset($idx[$mid]) || mel_data_dir() === '')
        return false;
    $hidden = mel_cp_hidden();
    unset($hidden[$mid]);
    $hidden[$mid] = $idx[$mid]['end'];
    if (count($hidden) > MEL_CP_HIDDEN_MAX)
    {
        asort($hidden);
        $hidden = array_slice($hidden, count($hidden) - MEL_CP_HIDDEN_MAX, null, true);
    }
    return mel_cp_hidden_put($hidden);
}

// The cards off the row $hidden (mel_cp_hidden) written whole (mel_write_file: a temporary file and rename), the memory
// read again. -> written.
/**
 * @param array<string, int> $hidden
 * @return bool
 */
function mel_cp_hidden_put($hidden)
{
    $json = json_encode(array('v' => 1, 'hidden' => (object) $hidden));
    $ok = is_string($json) && mel_write_file(mel_data_dir() . '/' . MEL_CP_HIDDEN_FILE, $json);
    MelCp::$hidden = null;
    return $ok;
}

// Card $mid of the latest record ending at $end is off the row now: taken off it ($hidden, mel_cp_hidden) with no
// record ending later since.
/**
 * @param array<string, int> $hidden
 * @param string $mid
 * @param int $end
 * @return bool
 */
function mel_cp_off($hidden, $mid, $end)
{
    return isset($hidden[$mid]) && $end <= $hidden[$mid];
}

// The cards taken off the row and kept off it now: of the history (mel_pg_index) with no record ending later, in the
// row else (mel_cp_item), both types. A card watched again or out of the history since is not counted. -> how many.
/**
 * @return int
 */
function mel_cp_hidden_n()
{
    $idx = mel_pg_index();
    $hidden = mel_cp_hidden();
    $n = 0;
    foreach ($hidden as $mid => $end)
    {
        $mid = strval($mid);
        if (isset($idx[$mid]) && mel_cp_off($hidden, $mid, $idx[$mid]['end']) && mel_cp_item($mid, $idx[$mid]))
            $n++;
    }
    return $n;
}

// "Вернуть убранные": none taken off the row (every key, of a card of the Dune or "m-..."), mel_cp_hidden_put. -> written.
/**
 * @return bool
 */
function mel_cp_unhide()
{
    return mel_data_dir() !== '' && mel_cp_hidden_put(array());
}

// --- POP UP of a tile of the catalog and the tab "TV".

// The card of the Dune of tile $t known with no request: of its id of the row "Continue" ($mid; '' another tile), else by
// the pairs (mel_pg_tile_card); '' for none. The card of a title of POP UP with no request: of the marks and "Мой
// список"; "Смотреть из…" (mel_catv_src) takes the card of the entry of "Мой список" first.
/**
 * @param MelCatTile $t
 * @param string $mid
 * @return string
 */
function mel_cp_card_known($t, $mid)
{
    return $mid !== '' ? $mid : mel_pg_tile_card($t, mel_pg_index());
}

// The card of the Dune of tile $t now: known (mel_cp_card_known: of its id of the row "Continue" $mid, by the pairs),
// else asked (mel_card_id, as ENTER: the pair kept). -> array(movie_id, '' none; why none: cp_err_mark - no answer,
// card_none_title - the Dune has no card of it).
/**
 * @param MelCatTile $t
 * @param string $mid
 * @return array{string, string}
 */
function mel_cp_card_of($t, $mid)
{
    $mid = mel_cp_card_known($t, $mid);
    if ($mid !== '')
        return array($mid, '');
    $c = mel_card_id($t);
    return array($c['id'], $c['id'] !== '' ? '' : ($c['none'] ? 'card_none_title' : 'cp_err_mark'));
}

// The items of POP UP of tile $id of screen $from (a main screen of the catalog, an EPF of "TV") of the row "Continue"
// and the marks (mel_catv_tile_menu puts them first): on the row "Continue" "Убрать из «Продолжить»", then "Отметить
// просмотренным" / "Снять отметку" (mel_pg_mark_kind; the card not known yet: "Отметить", asked at it). None but on a
// tile of a title (mel_catv_tile).
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function mel_cp_menu_items($id, $from, $lang)
{
    $t = mel_catv_tile($id, mel_tv_is_from($from));
    if (!$t)
        return array();
    $mid = mel_cp_mid_of($id);
    $items = array();
    $p = array('from' => $from, 'item' => $id);
    if ($mid !== '')
        $items[] = array(GuiMenuItemDef::caption => mel_tr($lang, 'cp_hide'), GuiMenuItemDef::icon_url => MEL_CATV_ICONS . 'cancel_small.png',
            GuiMenuItemDef::action => mel_input('cat_hide', $p));
    $w = mel_pg_mark_kind(mel_cp_card_known($t, $mid), $t['type']);
    if ($w !== '')
        $items[] = array(GuiMenuItemDef::caption => mel_tr($lang, $w === 'on' ? 'cp_mark_on' : 'card_mark_off'),
            GuiMenuItemDef::icon_url => MEL_CATV_ICONS . 'watched_small.png',
            GuiMenuItemDef::action => mel_input('cat_mark', $p + array('w' => $w), MEL_CARD_DELAY));
    return $items;
}

// "Убрать из «Продолжить»" of tile $in->item of screen $in->from: off the row (mel_cp_hide), the screens of the row anew
// (mel_ml_changed: on r25 the main ones and the screens of the lists opened too, the shell keeps rows_info by the id of a
// tile).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_cp_hide_act($in)
{
    $mid = mel_cp_mid_of(mel_catv_in($in, 'item'));
    if ($mid === '')
        return null;
    $ok = mel_cp_hide($mid);
    mel_log("continue: $mid off the row" . ($ok ? '' : ', not written'));
    return mel_ml_changed(mel_catv_in($in, 'from'), null, 'off the row "Continue"');
}

// "Вернуть убранные (N)" of POP UP of the screen "Продолжить" $from (mel_mlv_menu_items): all back to the row
// (mel_cp_unhide), no message, as "Убрать"; the screens of the row anew as after it (mel_ml_changed).
/**
 * @param string $from
 * @return MelAction|null
 */
function mel_cp_unhide_act($from)
{
    $n = mel_cp_hidden_n();
    $ok = mel_cp_unhide();
    mel_log("continue: unhide: $n" . ($ok ? '' : ', not written'));
    return mel_ml_changed($from, null, 'back to the row "Continue"');
}

// "Отметить просмотренным" / "Снять отметку" ($in->w on, off) of tile $in->item of screen $in->from: the card of the
// Dune (mel_cp_card_of: a request as ENTER without a pair), then update_watched_marks (mel_pg_mark_act): a movie -
// movie_ids, off also the uid of its latest record if marked (mel_wh_movie_off_ids); a series on - the whole of it (the
// owner): movie_ids and the marks of the own card of every episode out (the meta asked now, mel_cm_get, and
// mel_cm_eps_before; its episodes to the pair, mel_pg_link_meta: the dates of those not out) with the uids of the records of our releases of them (mel_wh_mark_ids), as "Отметить предыдущие"
// of the own card; a tile of the row with no pair ("dune:<movie_id>") gets it first (mel_pg_link_now, as ENTER); a
// series off - movie_ids and every uid of the card marked (mel_wh_marked_of: no request, no meta). post_action:
// mel_ml_changed. No card, no pair, no meta: the message cp_err_mark (the reason in the log).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_cp_mark($in)
{
    $a = mel_catv_tile_act($in, array('on', 'off'));
    $t = $a['t'];
    if (!$t)
        return $a['err'];
    $from = $a['from'];
    $w = $a['w'];
    $cp = mel_cp_mid_of($a['id']);
    list($mid, $err) = mel_cp_card_of($t, $cp);
    if ($mid === '')
        return mel_error($err);
    $ids = array();
    if ($t['type'] === 'series' && $w === 'off')
        $ids = mel_wh_marked_of($mid);
    else if ($t['type'] === 'series')
    {
        $links = mel_pg_links();
        if ($cp !== '' && (!isset($links[$mid]) || isset($links[$mid]['fail'])))
        {
            $r = mel_pg_link_now($mid, MEL_PG_LINK_S);
            mel_log("continue: mark: the pair asked: {$r['st']}");
            $t = $r['st'] === 'fail' ? null : mel_cp_tile($mid);
            if (!$t)
                return mel_error('cp_err_mark');
        }
        if (mel_cm_id($t) === '')
            return mel_error('err_no_imdb');
        $g = mel_cm_get($t);
        if (!$g['meta'])
        {
            mel_log("continue: mark: no meta ({$g['err']})");
            return mel_error('cp_err_mark');
        }
        mel_pg_link_meta($mid, $g['meta']);
        $ids = mel_wh_mark_ids($mid, mel_cm_eps_before($g['meta'], PHP_INT_MAX, 0));
    }
    else if ($w === 'off')
        $ids = mel_wh_movie_off_ids($mid);
    mel_log("continue: mark $w of $mid ({$t['type']}): " . count($ids) . ' uids');
    return mel_pg_mark_act($ids, array($mid), $w === 'on', mel_ml_changed($from, null, "a mark $w"));
}
