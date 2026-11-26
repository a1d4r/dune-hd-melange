<?php
// The row "На TorrServer" of the catalog on a main screen (its data: the copy of ts_row.php, no request here): its tiles
// in the order of the copy, "tors:<hash>" as the lines of the section (view_ts.php: ENTER and POP UP take them as
// theirs). A torrent known by the history of Melange (mel_tsr_mid) is the tile of its movie (mel_cp_tile: its poster,
// its rating, the badges of the disk and the history) and its panel is that of the catalog with one line of TorrServer
// over the line of the history (owner 26.11.2026, (в) of the designer); another one is its poster (none - the plate 2E),
// its panel 2C over the picture of TorrServer (B4 of the designer, owner 26.11.2026). ENTER on the title of the row: the
// section. Needs ts_row.php, view_ts.php, view_catalog.php, continue.php, progress.php, kit.php, main.php. PHP 5.3.6,
// firmware API only.

// The pictures of the panel of a tile not known (the N of tsr<N> goes up when one changes: the shell keeps a picture by
// its URL; made by experiments/ts-row-placeholder of the kitchen): cat_logo on - the whole screen, our mask in it; off -
// vod_r on the right, under our mask.
define('MEL_TSR_CARD', 'plugin_file://img/tsr1_card.jpg');
define('MEL_TSR_BG', 'plugin_file://img/tsr1_bg.jpg');
// The icon of the line of TorrServer of the panel of a known tile (L2 of the designer), its size and the gap after it.
define('MEL_TSR_LINE_ICON', 'plugin_file://img/tsr1_line.png');
define('MEL_TSR_LINE_ICON_WH', 32);
define('MEL_TSR_LINE_GAP', 14);

// The row of tab $tab ('movie', 'series') of main screen $from in language $lang: its title (tsl_title), its tiles of the
// copy (MEL_TSR_MAX at most), no "Show all" tile, no flag; ENTER on its title - the section over $from (mel_tsl_open_act).
// None: «Без торрентов» or no copy (an empty list, no answer ever, 401); the caller builds it for its slot alone (shown
// on the tab, mel_tsr_on). One map of the movies by the history for all of it (mel_tsr_mids). $tiles: the known
// ones by their ids with no mode; $miss: those of a record with no meta (array(type, IMDb, TMDB), in their order: the
// lazy meta, MelCpm::$ts).
/**
 * @param string $tab
 * @param string $lang
 * @param string $from
 * @param array<string, MelCatTile>|null $tiles
 * @param-out array<string, MelCatTile> $tiles
 * @param list<array{string, string, string}>|null $miss
 * @param-out list<array{string, string, string}> $miss
 * @return list<array<string, mixed>>
 */
function mel_tsr_row_defs($tab, $lang, $from, &$tiles = null, &$miss = null)
{
    $tiles = array();
    $miss = array();
    $items = array();
    // Its slot is shown (the caller): «Без торрентов» alone hides it here.
    if (!mel_tsl_on())
        return array();
    $copy = mel_tsr_tiles();
    $mids = $copy ? mel_tsr_mids() : array();
    $map = null;
    mel_lib_tile_start();
    foreach ($copy as $hash => $c)
    {
        $hash = strval($hash);
        $mid = isset($mids[$hash]) ? $mids[$hash] : '';
        if ($mid !== '' && $map === null)
            $map = mel_cp_tile_map();
        $t = $mid !== '' ? mel_cp_tile($mid, $map, $rec) : null;
        if (!$t)
        {
            $items[] = mel_tsr_item($c['e']);
            continue;
        }
        if ($rec && ($t['imdb_id'] !== '' || $t['_tmdbId'] !== ''))
            $miss[] = array($t['type'], $t['imdb_id'], $t['_tmdbId']);
        $items[] = mel_catv_title_item(MEL_TSL_RID, $t, $hash, $mid);
        $tiles[MEL_TSL_RID . ":$hash"] = $t;
    }
    if (!$items)
        return array();
    $name = mel_tr($lang, 'tsl_title');
    $row = array('key' => MEL_TSL_RID, 'type' => $tab !== '' ? $tab : 'movie', 'id' => MEL_TSL_RID, 'name' => $name,
        'genre' => '', 'home' => false, 'group' => '');
    $defs = mel_catv_row_defs(mel_catv_list(mel_cat_row_state($row), array(), false), $lang, $items, 0, MEL_TSL_RID);
    $defs[1][PluginRow::show_all_action] = mel_tsl_open_act($from);
    mel_log(sprintf('ts row: %s: %d tiles, %d known, %d with no meta', $tab, count($items), count($tiles), count($miss)));
    return $defs;
}

// The tile of entry $e not known: its title, its poster (mel_img: http(s)); none - on r25 the plate with the title (2E),
// on r24 the picture of none of the shell (stickers there not seen).
/**
 * @param MelTsEntry $e
 * @return array<string, mixed>
 */
function mel_tsr_item($e)
{
    $poster = mel_img($e['poster']);
    $item = mel_catv_item(mel_catv_tile_id(MEL_TSL_RID . ':' . $e['hash']), $e['title'], $poster);
    if ($poster === '' && mel_catv_loading_flag())
        $item[PluginRegularItem::stickers] = mel_tsr_plate($e['title']);
    return $item;
}

// The stickers of the plate 2E over the whole tile (MEL_PG_W x MEL_PG_H, the shell scales them in focus): the colour of
// the tiles with no picture (MEL_K_C_NO_PHOTO), the icon of the section of 40 px in the top left corner, $text in lines
// of MEL_CATV_RATE_PX (the size of the stickers of the template) 33 apart under it from the left, 6 at most, measured by
// the kit (MelKit::wrap: broken after spaces and separators, names of files too). The shell puts a text at the left of
// its r and a pixel over its middle (mel_catv_rate_stickers).
/**
 * @param string $text
 * @return list<array<string, mixed>>
 */
function mel_tsr_plate($text)
{
    // The glyph is 3/4 of the picture: r of 54 is a glyph of 40 at 14.
    $out = array(array(PluginRegularSticker::r => mel_pg_r(0, 0, MEL_PG_W, MEL_PG_H), PluginRegularSticker::color => MEL_K_C_NO_PHOTO),
        array(PluginRegularSticker::r => mel_pg_r(7, 7, 54, 54), PluginRegularSticker::icon_url => MEL_TSL_ICON));
    foreach (MelKit::wrap($text, MEL_CATV_RATE_PX, MEL_K_FACE_REGULAR, MEL_PG_W - 28, 6) as $j => $l)
        $out[] = array(PluginRegularSticker::r => mel_pg_r(14, 63 + 33 * $j, MEL_PG_W - 14, 33), PluginRegularSticker::text => $l);
    return $out;
}

// The panel of a tile not known, $tile of the copy (null: not in it - an empty panel, not the one of the tile before)
// in language $lang (2C) -> array(title, lines array(text, colour, least px), the description): the title, "73 файла ·
// 51.6 ГБ", "Добавлено 18 сентября", the rest of the name (mel_tsl_texts by the copy: no list read).
/**
 * @param MelTsrTile|null $tile
 * @param string $lang
 * @return array{string, list<array{string, string, int}>, string}
 */
function mel_tsr_info($tile, $lang)
{
    if ($tile === null)
        return array('', array(), '');
    $t = mel_tsl_texts($tile, $lang);
    return array($t['head'], array(array($t['facts'], MEL_CATV_TEXT_COLOR, MEL_CATV_DATA_MIN_PX),
        array($t['added'], MEL_CATV_TEXT_COLOR, MEL_CATV_DATA_MIN_PX)), $t['rest']);
}

// The text of the line of TorrServer of the panel of a known tile, $tile of the copy, in language $lang: «На
// TorrServer · 1 файл · 61.3 ГБ · добавлено 3 октября»; the video files from one (nv; not known - none), the size, the
// day; none of them - left out. No name of the torrent (owner 26.11.2026).
/**
 * @param MelTsrTile $tile
 * @param string $lang
 * @return string
 */
function mel_tsr_line_text($tile, $lang)
{
    $parts = array(mel_tr($lang, 'tsl_title'));
    if ($tile['nv'] > 0)
        $parts[] = $tile['nv'] . ' ' . mel_gc_plural($lang, $tile['nv'], 'tsl_files');
    if ($tile['e']['size'] > 0)
        $parts[] = mel_size_str($tile['e']['size'], $lang);
    $parts[] = mel_tsl_added($tile['e'], $lang, 'tsl_added_lc');
    return implode(' · ', array_filter($parts, 'strlen'));
}

// The line of TorrServer of the panel (mel_tsr_line_text) at $y of the panel, LINE_H high: the icon L2 on the left,
// centred in it, the text after it to the right edge of the panel (wider than MEL_CATV_TEXT_W of the other lines: a size
// and a day of a year fit so at 28-30 px) in MEL_CATV_LINE_PX, smaller down to MEL_CATV_DATA_MIN_PX to fit, cut with "…"
// then.
/**
 * @param string $text
 * @param int $y
 * @return list<array<string, mixed>>
 */
function mel_tsr_line($text, $y)
{
    $x = MEL_TSR_LINE_ICON_WH + MEL_TSR_LINE_GAP;
    $wh = MEL_TSR_LINE_ICON_WH;
    $w = MEL_CATV_PANE_W - $x;
    $fit = mel_catv_fit(mel_clean($text), MEL_CATV_LINE_PX, MEL_CATV_DATA_MIN_PX, $w);
    $l = mel_catv_info_label($fit[1], $fit[0], MEL_CATV_TEXT_COLOR, $w, MEL_CATV_LINE_H, $y);
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::x] = $x;
    return array(mel_gc_image(mel_gc_at($wh, $wh, 0, $y + intval((MEL_CATV_LINE_H - $wh) / 2)), MEL_TSR_LINE_ICON), $l);
}

// The background of the panel of a tile not known (the shape of mel_catv_card) in the mode $on (cat_logo): the picture
// of TorrServer, one for all of them, local (no second step as the focus comes from a fanart). On: of the whole screen,
// the mask in it; off: of vod_r on the right under our mask, as any fanart there. nl_bg_url: card0.jpg (the shell takes
// it after a failed bg_url only when it is another address).
/**
 * @param bool $on
 * @return array{bg: string, nl: string|null, mask: string|null, logo: bool}
 */
function mel_tsr_card($on)
{
    return array('bg' => $on ? MEL_TSR_CARD : MEL_TSR_BG, 'nl' => MEL_CATV_IMG . 'card0.jpg',
        'mask' => $on ? null : MEL_CATV_IMG . 'mask.png', 'logo' => false);
}
