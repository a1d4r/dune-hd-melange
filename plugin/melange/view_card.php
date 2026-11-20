<?php
// The own card of a movie as a view_gcomps screen "card:<type>:<id>", 1920x1080: the panel of the
// catalog grown into a card (R1 of design-own-card.md). The card background of the server
// (mel_catv_card) with the logo, or the dark one and the title as text; the lines of the panel of
// the catalog at its places (facts, genres and countries, the director, the description), the
// buttons in a row ("Watch", "Trailer", "My list") over a band of our own, over them the line of the history of the
// movie as the catalog has it, over that its copy on the disk or why there is no history; at the bottom the first of
// the rows (the movies of a long franchise, similar titles with the parts of a short one first, the cast of the meta,
// card_meta.php) and at its right the hint "Похожие, в ролях и о фильме". DOWN scrolls the page under the logo:
// the rows one by one under the logo, a stop each, then "About the movie" (the tagline, the whole
// description, the details) under them, the background gone, the screen black; DOWN again
// further while there is more, UP back, at last to the button left. A series (H3): the buttons
// higher ("Continue SxEy" by the history), under them the tabs of the seasons and the row of the
// episodes of the season shown, their progress by the history; the rows go DOWN with "About the
// series". At the stop of a row LEFT and RIGHT go along it (the creators first), over a row of posters
// the header of the poster in focus; ENTER on a person opens its page (view_person.php), on a poster
// the own card of its title.
// The plugin draws and moves the focus itself with change_gcomps, as the list; the place
// (mel_oc_pos: the zone, the button, the stop, the season and the episode, the item of each row)
// travels in sel_state.
// ENTER on a tile does the network (mel_oc_enter: the meta; the rows of posters on r24); the screen
// is built from memory; from r25 its timer asks the rows of posters (mel_oc_load).
// A title with copies on the disk has the slot of the overlay of the UI Kit last (kit_ovl.php: the window of the
// copies over the card), the record of its screen mel_oc_scr.
// Needs main.php, catalog.php, catalog_card.php, card_meta.php, card_more.php, history.php, playback.php, progress.php,
// continue.php, view_catalog.php, view_gcomps.php, gcomps.php, playback_lib.php, kit_ovl.php, view_my_list.php; the
// seasons and the episodes of a series: view_card_series.php; the strip of a row and the frame of the focus:
// view_card_row.php; the rows of the card (the cast, the posters), ENTER on their items and the timer of the posters:
// view_card_rows.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// Cards kept in memory by "<type>:<id>", MEL_CM_KEEP_EPS episodes in all at most; the oldest
// opened goes.
define('MEL_OC_KEEP', 8);
// The page: a clipping panel from the bottom of the logo (PANE_Y + TITLE_BOTTOM of the catalog,
// baked into card/v1) down to SHOW_BOTTOM, higher at a stop where it would cut a line; the page in
// it in the coordinates of the first screen.
// DOWN: the first stop OFF lower (the first row at DOWN_Y of the screen; without rows "About"
// there), a stop for each row (the next at DOWN_Y), then "About" (mel_oc_stops) and the next ones
// STEP further at most, MIN_STEP at least, while the page goes on below the clip. The rows lie in
// "down", a panel OFF lower in the page, from DOWN_Y one under the other, ROW_GAP apart, "About"
// under them (mel_oc_stack), ABOUT_Y at least: under the clip of the first screen but the first
// row of a movie. The ink of a label starts INK under its top at least (30 and 24 px, measured
// on the device): an edge of the clip there cuts no line.
define('MEL_OC_VIEW_Y', 256);
define('MEL_OC_DOWN_Y', 290);
// The stop of a row of posters (design-own-card-row-info.md, A): its title RI_Y, the top of the clip INK over it (the
// year of the row above goes under it); over the clip, on the black, the header of the poster in focus (mel_oc_ri): its
// title 48 px at RI_TITLE_Y, the line of facts of the catalog at RI_FACTS_Y, the description from RI_DESC_Y in
// RI_LINES lines DESC_LH apart at most, TITLE_W wide. Its room is the same with no description: the row does not move;
// the stubs "w" of a row asked keep it, empty.
define('MEL_OC_RI_Y', 362);
define('MEL_OC_RI_TITLE_Y', 36);
define('MEL_OC_RI_FACTS_Y', 114);
define('MEL_OC_RI_DESC_Y', 166);
define('MEL_OC_RI_LINES', 4);
define('MEL_OC_STEP', 420);
define('MEL_OC_MIN_STEP', 40);
define('MEL_OC_SHOW_BOTTOM', 1020);
define('MEL_OC_INK', 10);
// The first screen (design-own-card.md, B): lines of the panel, the description in 30 px lines
// DESC_LH apart from under the director (or the genres) down to DESC_BOTTOM; buttons BTN_H high
// at BTN_Y, the text PAD from the edges (and the icon of "Watch" ICON_W more), GAP apart; CAP: the
// rounded ends (PNG), a rect between them; the hint at the line of the cast, its right edge at HINT_R, its arrow
// HINT_ARROW_DX after the text.
// A movie, variant Б of the fifth pass (owner 20.11.2026, design-local-library.md, v10_B_*.png): under the director 4
// lines of the description down to the buttons; the line of the history of the catalog (mel_oc_hist: its place and
// look, mel_pg_info_line) takes the 4th, the line of the copy on the disk ("С диска", MEL_OC_LIB_Y of
// view_card_lib.php) or the line why at LINE_Y (no copy: "Только Lampac", no history, no IMDb) the 3rd - those over
// them go too: the description 3 lines with the history alone, 2 with either line, the slot of the history empty when
// there is none; the buttons and the rows do not move. DESC_BOTTOM: the bottom of the slot of the history - that of the
// line of the panel of the catalog (mel_catv_hist_y in a panel at MEL_CATV_PANE_Y, MEL_PG_INFO_H high), 589.
define('MEL_OC_FACTS_Y', 269);
define('MEL_OC_GENRES_Y', 315);
define('MEL_OC_DIRECTOR_Y', 361);
define('MEL_OC_DESC_LH', 42);
define('MEL_OC_DESC_BOTTOM', MEL_CATV_PANE_Y + mel_catv_hist_y() + MEL_PG_INFO_H);
define('MEL_OC_BTN_Y', 602);
define('MEL_OC_BTN_H', 64);
define('MEL_OC_BTN_PAD', 32);
define('MEL_OC_ICON_W', 34);
define('MEL_OC_BTN_GAP', 20);
define('MEL_OC_CAP', 12);
// The line why: its capitals on those of the 3rd line of the description (532), as the line "С диска".
define('MEL_OC_LINE_Y', 512);
// The height of a label of the card smaller than the lines of the panel (24 px: mel_oc_label), of the lines over the
// buttons; their text MEL_OC_LINE_PX. The bar of the progress of an episode on its still BAR_H high.
define('MEL_OC_LINE_H', 34);
define('MEL_OC_LINE_PX', MEL_K_PX_CAPTION);
define('MEL_OC_BAR_H', 6);
define('MEL_OC_HINT_R', 1845);
define('MEL_OC_HINT_ARROW_DX', 16);
// The icons of the button of the list (mel_oc_buttons, MEL_OC_IMG <icon>_icon.png): the title not in the list ("♡ В
// избранное": ENTER adds it, mel_oc_list), in it ("✓ В избранном": ENTER takes it out).
define('MEL_OC_ML_ADD', 'heart');
define('MEL_OC_ML_IN', 'tick');
// The arrow "more below" of the hints (card1_more_down.png, polish M1b): a grey open chevron down.
define('MEL_OC_MORE_W', 30);
define('MEL_OC_MORE_H', 14);
// The first row of a movie at CAST_Y of the first screen. The cast: CAST_MAX people at most (the
// creators first), photos 120x152 (TMDB w276_and_h350_face, a half) STEP apart, DIV more after the
// creators, NAME_DY under their top the name in 2 lines and the role in 2, NAME_W wide: the bottom
// of its text 312 under its title (CAST_H under the photos), SHOW_BOTTOM at the first screen of a
// movie (design-own-card.md, "Итог 13.11.2026": R1 with the role in 2 lines, to be seen on the
// device; back: CAST_Y 724, LINE_Y 664, STEP 172, NAME_W 160, NAME_DY 158). CAST_VIS whole on the
// screen, the row scrolls under the frame of the focus at its stop. "About": the description in
// DESC2_W, the pairs of the details at DET_X (the 8th column of the cast) in DET_W, to the right
// edge.
define('MEL_OC_CAST_Y', 708);
define('MEL_OC_OFF', MEL_OC_CAST_Y - MEL_OC_DOWN_Y);
define('MEL_OC_CAST_MAX', 30);
define('MEL_OC_CAST_VIS', 9);
define('MEL_OC_CAST_DIV', 32);
define('MEL_OC_CAST_H', 266);
define('MEL_OC_PHOTO_W', 120);
define('MEL_OC_PHOTO_H', 152);
define('MEL_OC_PHOTO_STEP', 184);
define('MEL_OC_NAME_W', 168);
define('MEL_OC_NAME_DY', 154);
define('MEL_OC_ROW_GAP', 14);
// "About" at ABOUT_Y of "down" at least: without rows it lies under the clip of the first screen
// of a movie too (OFF + ABOUT_Y > SHOW_BOTTOM); with the cast alone it is there anyway.
define('MEL_OC_ABOUT_Y', 616);
// The ids of the rows of a card (their strips, the keys of r of the place), in the order of r:
// the cast, the movies of a franchise, similar titles.
define('MEL_OC_ROW_IDS', 'c,f,s');
// The rows of posters (view_card_row.php; 2.0, stage 4 of design-own-card.md): similar titles and the
// movies of a franchise (mel_cm_posters); asked as the card opens after its meta on r24, from r25 by
// the timer of the screen (TICK_MS after it is shown), one a tick, grey stubs in their place till
// then (mel_oc_poster_rows: the row "w"); one of PARTS_IN
// at most goes first in "Похожие" after a line (variant C), a longer one is a row of its own over it. A cell: the poster, its title in
// 2 lines and the year under it (their boxes 462 under its top), TILE_CH high: at the stop of the
// next row (its title at DOWN_Y) the bottom of the year at VIEW_Y, none of it under the logo; the
// movie of the card darkened by THIS with "Этот фильм". The title of a row TITLE_W wide at most;
// of the first row of a movie up to HINT_GAP before the hint of the first screen at its right.
define('MEL_OC_PARTS_IN', 3);
define('MEL_OC_TICK_MS', 100);
define('MEL_OC_TILE_CH', 482);
define('MEL_OC_THIS', '#90000000');
define('MEL_OC_TITLE_W', 1180);
define('MEL_OC_HINT_GAP', 40);
define('MEL_OC_DESC2_W', 960);
define('MEL_OC_DESC2_LINES', 100);
define('MEL_OC_DET_X', MEL_CATV_CARD_X + 7 * MEL_OC_PHOTO_STEP);
define('MEL_OC_DET_W', MEL_OC_HINT_R - MEL_OC_DET_X);
// A series (H3 of design-own-card.md): the description DESC_GAP_SER under the genres (a movie: 14)
// in lines DESC_LH_SER apart down to DESC_BOTTOM_SER, 3 under the genres; the buttons at BTN_Y_SER,
// 35 px under its ink as at a movie; the tabs 28 under them, the row 24 under the tabs; under the row
// only the arrow of DOWN (design-own-card-polish.md, owner 13.11.2026: 3 lines; 14.11.2026: no text).
// The tabs of the seasons at TAB_Y, TAB_H high, the text TAB_PAD from their edges, TAB_GAP apart,
// the rounded ends TAB_CAP (PNG); more than TABS_MAX seasons: numbers after "Сезон". The count
// "Сезон 2 · 13 серий" in CNT_PX at the right edge of the line, the strip CNT_GAP before the widest;
// not all fitting: the strip scrolls by whole tabs, the chevrons CHEV_W x CHEV_H (PNG) CHEV_GAP off
// its ends, in slots CHEV_SLOT wide (T2 of design-own-card.md, "Итог 13.11.2026").
// The row of episodes at ROW_Y: stills EP_W x EP_H, EP_STEP apart from the x of the catalog,
// EP_VIS whole, the one before and after seen at the edges; the frame of the focus FRAME wide
// around the still; P+/P- PAGE stills.
// H3 of design-own-card.md (owner 15.11.2026): with the focus in the row of episodes the head under the logo is of
// the episode in focus (mel_oc_ep_head): its title 48 px at EP_TITLE_Y, "S1 E2 · <date> · <length>" at EP_FACTS_Y, its
// description from EP_DESC_Y in EP_LINES lines DESC_LH_SER apart; the buttons, the tabs and the row do not move.
define('MEL_OC_EP_TITLE_Y', 252);
define('MEL_OC_EP_FACTS_Y', 324);
define('MEL_OC_EP_DESC_Y', 372);
define('MEL_OC_EP_LINES', 3);
define('MEL_OC_BTN_Y_SER', 526);
define('MEL_OC_DESC_GAP_SER', 6);
define('MEL_OC_DESC_LH_SER', 40);
define('MEL_OC_DESC_BOTTOM_SER', 491);
// The head of an episode with a copy on the disk (spec §7.3, v8_S_head_A.png): its description in EP_LIB_LINES lines
// (not EP_LINES), the line of the copy the next line (its box at EP_LIB_Y, 46 high at MEL_CATV_LINE_PX), to the right
// edge of the text of the head (mel_oc_lib_line of view_card_lib.php).
define('MEL_OC_EP_LIB_LINES', 2);
define('MEL_OC_EP_LIB_Y', MEL_OC_EP_DESC_Y + MEL_OC_EP_LIB_LINES * MEL_OC_DESC_LH_SER);
define('MEL_OC_EP_LIB_R', MEL_CATV_CARD_X + MEL_CATV_TEXT_W);
define('MEL_OC_TAB_Y', 618);
define('MEL_OC_TAB_H', 52);
define('MEL_OC_TAB_PAD', 24);
define('MEL_OC_TAB_GAP', 8);
define('MEL_OC_TAB_CAP', 26);
define('MEL_OC_TABS_MAX', 8);
define('MEL_OC_CNT_GAP', 40);
define('MEL_OC_CNT_PX', 24);
define('MEL_OC_CHEV_W', 12);
define('MEL_OC_CHEV_H', 22);
define('MEL_OC_CHEV_GAP', 12);
// 4 more than the chevron and its gap: the left one 4 in from the x of the catalog, the right one
// 4 more before CNT_GAP.
define('MEL_OC_CHEV_SLOT', MEL_OC_CHEV_W + MEL_OC_CHEV_GAP + 4);
define('MEL_OC_ROW_Y', 694);
define('MEL_OC_EP_W', 384);
define('MEL_OC_EP_H', 216);
define('MEL_OC_EP_STEP', 408);
define('MEL_OC_EP_VIS', 4);
define('MEL_OC_ROW_H', 310);
define('MEL_OC_FRAME', 6);
define('MEL_OC_PAGE', 4);
// A season of more than WIN_MIN episodes has in the panel only the stills on screen and WIN more
// each side, added and dropped one by one as the row moves (as GCompList of the vendor). The probe
// (experiments/hrow_probe, 12.11.2026): a season of 13-16 stills shows its stubs 0.1 s after the
// key, one of 120 in 0.26 s (PHP 38 ms, 170 KB), its stills 0.56-0.9 s; so ~1.5 ms of the shell a
// still: 40 keep the change of a season near 0.15 s and the stills in 0.5 s, a window of 21 the
// same however long the season (One Piece: up to 197).
define('MEL_OC_WIN_MIN', 40);
define('MEL_OC_WIN', 8);
// change_gcomps steps of the row and the tabs (the probe: 5 ~320 ms; repeats of a held key come
// each 0.15-0.2 s and break it: to be tuned on the device).
define('MEL_OC_ROW_STEPS', 5);
// DOWN of a series: the first stop OFF_SER lower (the row above the clip, the cast at DOWN_Y); the
// arrow of DOWN under the row at (HINT_X_SER, HINT_Y_SER): the middle of the 4 whole stills, its ink
// ending at SHOW_BOTTOM.
define('MEL_OC_OFF_SER', 754);
define('MEL_OC_HINT_X_SER', MEL_CATV_CARD_X + intval((3 * MEL_OC_EP_STEP + MEL_OC_EP_W - MEL_OC_MORE_W) / 2));
define('MEL_OC_HINT_Y_SER', MEL_OC_SHOW_BOTTOM - MEL_OC_MORE_H);
// Colours of the design: buttons, the bar, no photo, the frame of the focus,
// the tab of the season shown (white 16 %), the plate of the mark "watched".
define('MEL_OC_BTN_OFF', '#24FFFFFF');
define('MEL_OC_BAR_REST', '#FF404040');
define('MEL_OC_NO_PHOTO', '#FF262626');
define('MEL_OC_FOCUS', '#FF2850C0');
define('MEL_OC_TAB_ON', '#29FFFFFF');
define('MEL_OC_MARK_BG', '#B0000000');
// Our pictures: the ends of a button, off and focused, the icon of "Watch", the ends of a tab of a
// season (shown, focused), the chevrons of the strip of tabs (grey, white with the focus on the
// tabs).
// card<N>: N goes up when they change, as cat<N>.
define('MEL_OC_IMG', 'plugin_file://img/card1_');
// Pictures of the shell: a still loading or not there (an episode not out yet), the mark "watched".
define('MEL_OC_ICONS', 'plugin_file://%shell_ext%/icons/');

class MelOc
{
    // Cards by "<type>:<id>": tile, meta (or null), imdb, dune (the id of the card of the Dune or
    // of an old local one, '' for none), lang (of play_action), ui (of the texts), pos (the place,
    // mel_oc_pos), n (episodes of the meta), posters (the rows of posters, mel_cm_posters); nav (what
    // the keys need of mel_oc_layout) once shown.
    // Memory of php_server only: after a restart the card is made from the meta anew.
    /** @var array<string, MelOcCard> */
    public static $cards = array();
}

// ENTER on a tile of a movie or a series with IMDb or TMDB (mel_card_enter): the id of the card of the Dune
// (mel_card_id; none: an old local card m-melange-..., none is written now; $dune: known, of the row "Continue",
// no request of it, its rating of Kinopoisk not known: mel_oc_fill asks it), the meta (mel_cm_get; a failure: the
// card of the tile), the card of them (mel_oc_build) -> the card screen.
/**
 * @param MelCatTile $tile
 * @param string $lang
 * @param string $dune
 * @return MelAction
 */
function mel_oc_enter($tile, $lang, $dune = '')
{
    $c = $dune !== '' ? array('id' => $dune, 'none' => false, 'kp' => null) : mel_card_id($tile);
    $g = mel_cm_get($tile);
    $key = $tile['type'] . ':' . mel_cm_id($tile);
    $card = mel_oc_build($key, $tile, $g['meta'], $c, $lang);
    mel_log("card: own $key: card " . ($card['dune'] !== '' ? $card['dune'] : 'none') . ', meta ' .
        ($g['err'] !== '' ? "failed ({$g['err']})" : 'ok') . ', imdb ' . ($card['imdb'] !== '' ? $card['imdb'] : 'none'));
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "card:$key",
            PluginOpenFolderActionData::caption => $tile['name']));
}

// The tile of a card with what the header needs: a tile not of a row of the catalog (of the
// works of a person: id, name, poster, year) has no rating, genres, length or logo. What it lacks
// is taken of the tile of the card of this title in memory first (opened before, from a row of the
// catalog too: its rating of Kinopoisk and votes), then of the tile of the meta (since 13.11.2026 the
// server puts the fields of Kino API there too, kpRating, kpVotes, kpId, the age in
// app_extras.certifications.RU, mel_cat_tile; only if Kino API answered in time); still no rating:
// the fallback, that of Kinopoisk of the card of the Dune $c (mel_card_id), not known of its cache:
// asked now (mel_card_kp) with no card in memory. The ids stay those of the tile, so do its fields not empty.
/**
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @param array{id: string, none: bool, kp: string|null} $c
 * @return MelCatTile
 */
function mel_oc_fill($tile, $meta, $c)
{
    static $keys = array('year', 'imdbRating', 'description', 'genres', 'poster', 'background', 'country', 'runtime', 'age',
        'cast', 'englishTitle', 'kpRating', 'kpVotes', 'kpId', 'logo', 'logoLang');
    $key = $tile['type'] . ':' . mel_cm_id($tile);
    $was = isset(MelOc::$cards[$key]) ? MelOc::$cards[$key]['tile'] : null;
    foreach (array($was, $meta ? $meta['tile'] : null) as $m)
    {
        foreach ($m ? $keys : array() as $k)
        {
            if (($tile[$k] === '' || $tile[$k] === array() || $tile[$k] === 0) && $m[$k] !== '' && $m[$k] !== array())
                $tile[$k] = $m[$k];
        }
    }
    if ($tile['kpRating'] === '' && $c['id'] !== '')
    {
        if ($c['kp'] !== null)
            $tile['kpRating'] = $c['kp'];
        else if (!$was)
            $tile['kpRating'] = mel_card_kp($tile, $c['id']);
    }
    return $tile;
}

// A card of $tile: the IMDb of the tile or of the meta; no card of the Dune ($dune ''): the old
// local one if there is one; the rows of posters $posters (mel_cm_posters).
/**
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @param string $dune
 * @param string $lang
 * @param MelCmPosters $posters
 * @return MelOcCard
 */
function mel_oc_make($tile, $meta, $dune, $lang, $posters)
{
    $imdb = $tile['imdb_id'] !== '' ? $tile['imdb_id'] : ($meta ? $meta['imdb'] : '');
    $tile['imdb_id'] = $imdb;
    if ($dune === '')
        $dune = mel_card_local_find($tile);
    return array('tile' => $tile, 'meta' => $meta, 'imdb' => $imdb, 'dune' => $dune, 'lang' => $lang,
        'ui' => mel_catv_ui_lang(), 'pos' => array('z' => 'btn', 'b' => 0, 'p' => 0, 's' => 0, 'e' => 0, 'r' => array()),
        'n' => $meta ? mel_cm_eps_count($meta) : 0, 'posters' => $posters);
}

// The card $key of $tile with meta $meta (or null), the card of the Dune $c (mel_card_id), $lang
// of play_action: the tile filled (mel_oc_fill), the rows of posters asked (mel_cm_posters: all of them
// on r24, else those in memory, the rest by the timer of the screen), made (mel_oc_make) and kept
// (mel_oc_keep). A series with a card of the Dune: its episodes of the meta to the pair (mel_pg_link_meta).
/**
 * @param string $key
 * @param MelCatTile $tile
 * @param MelCardMeta|null $meta
 * @param array{id: string, none: bool, kp: string|null} $c
 * @param string $lang
 * @return MelOcCard
 */
function mel_oc_build($key, $tile, $meta, $c, $lang)
{
    $card = mel_oc_make(mel_oc_fill($tile, $meta, $c), $meta, $c['id'], $lang,
        mel_cm_posters($key, $tile, $meta, mel_fw_r() < MEL_FW_TIMER_R));
    mel_oc_keep($key, $card);
    if ($meta && $c['id'] !== '' && $tile['type'] === 'series')
        mel_pg_link_meta($c['id'], $meta);
    return $card;
}

/**
 * @param string $key
 * @param MelOcCard $card
 * @return void
 */
function mel_oc_keep($key, $card)
{
    unset(MelOc::$cards[$key]);
    MelOc::$cards[$key] = $card;
    $sum = 0;
    foreach (MelOc::$cards as $c)
        $sum += $c['n'];
    // The card just kept stays, however many episodes it has.
    while (count(MelOc::$cards) > 1 && (count(MelOc::$cards) > MEL_OC_KEEP || $sum > MEL_CM_KEEP_EPS))
    {
        $c = array_shift(MelOc::$cards);
        $sum -= $c['n'];
    }
}

// The card of "<type>:<id>" from memory; none there (php_server restarted): made from its meta
// anew (its tile, the rating of Kinopoisk of the card of the Dune), kept. null: a bad key, no meta.
/**
 * @param string $key
 * @return MelOcCard|null
 */
function mel_oc_get($key)
{
    if (isset(MelOc::$cards[$key]))
        return MelOc::$cards[$key];
    if (!preg_match('/^(movie|series):(tt[0-9]{1,10}|tmdb:([0-9]{1,10}))$/D', $key, $m))
        return null;
    $t = mel_oc_id_tile($m[1], $m[2]);
    $r = $t ? mel_oc_of_meta($key, $t) : null;
    if (!$r || !$r['card'])
    {
        mel_log("card: own $key not in memory, no meta: outdated");
        return null;
    }
    mel_log("card: own $key made anew from its meta");
    return $r['card'];
}

// The tile of id $id of the meta (tt..., tmdb:<n>, mel_cm_id) of type $type alone, no name; null for a wrong id.
/**
 * @param string $type
 * @param string $id
 * @return MelCatTile|null
 */
function mel_oc_id_tile($type, $id)
{
    $t = mel_cat_tile(array('id' => $id, 'name' => '-', 'type' => $type), $type);
    if ($t)
        $t['name'] = '';
    return $t;
}

// Card $key of tile $t made of its meta (mel_cm_get) with no tile of a row (a restart of php_server, a link,
// mel_dl_card_of): the tile of the meta, of the type of $t, the card of the Dune by it (mel_card_id), kept
// (mel_oc_build). -> card: null with no meta (or no tile in it); err: why ('' none, mel_cm_get, "tile").
/**
 * @param string $key
 * @param MelCatTile $t
 * @return array{card: MelOcCard|null, err: string}
 */
function mel_oc_of_meta($key, $t)
{
    $g = mel_cm_get($t);
    if (!$g['meta'] || !$g['meta']['tile'])
        return array('card' => null, 'err' => $g['err'] !== '' ? $g['err'] : 'tile');
    $tile = $g['meta']['tile'];
    $tile['type'] = $t['type'];
    return array('card' => mel_oc_build($key, $tile, $g['meta'], mel_card_id($tile), mel_catv_lang()), 'err' => '');
}

// --- Texts.

// A country of the meta in the language of the screen; one not known in Russian as it is.
/**
 * @param string $c
 * @param string $lang
 * @return string
 */
function mel_oc_country($c, $lang)
{
    $n = mel_catv_country($c, $lang);
    return $n !== '' ? $n : $c;
}

// The countries of the card: of the meta, else the one of the tile.
/**
 * @param MelOcCard $c
 * @return list<string>
 */
function mel_oc_countries($c)
{
    $list = $c['meta'] ? $c['meta']['countries'] : array();
    if (!$list && $c['tile']['country'] !== '')
        $list = array($c['tile']['country']);
    $out = array();
    foreach ($list as $x)
        $out[] = mel_clean(mel_oc_country($x, $c['ui']));
    return $out;
}

// The facts of the panel of the catalog with the kind first: "Фильм · 2001 · 2 ч 32 мин". A series
// has it ("Сериал") in the parts already; its seasons of the meta with an episode out instead of
// the length of an episode: "Сериал · 2008 · 5 сезонов".
/**
 * @param MelOcCard $c
 * @return list<array{string, string, string}>
 */
function mel_oc_facts($c)
{
    $p = mel_catv_fact_parts($c['tile'], $c['ui']);
    $n = 0;
    foreach ($c['meta'] ? $c['meta']['seasons'] : array() as $s => $eps)
    {
        foreach ($eps as $ep)
        {
            if ($s > 0 && !$ep['soon'])
            {
                $n++;
                break;
            }
        }
    }
    if ($c['tile']['type'] !== 'series')
        array_unshift($p['rest'], mel_tr($c['ui'], 'card_kind_movie'));
    else if ($n > 0)
    {
        $len = mel_catv_runtime($c['tile']['runtime'], $c['ui']);
        $p['rest'] = array_values(array_diff($p['rest'], array($len)));
        $p['rest'][] = $n . ' ' . mel_gc_plural($c['ui'], $n, 'card_seasons');
    }
    return mel_catv_facts_of($p);
}

// "Y-m-d" -> "16 ноября 2001" ($year false: "16 ноября"; $short: "16 ноя 2001"); '' for none.
/**
 * @param string $d
 * @param string $lang
 * @param bool $short
 * @param bool $year
 * @return string
 */
function mel_oc_day($d, $lang, $short = false, $year = true)
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $d, $x))
        return '';
    $months = explode(',', mel_tr($lang, $short ? 'card_months_short' : 'card_months'));
    $mon = isset($months[intval($x[2]) - 1]) ? $months[intval($x[2]) - 1] : $x[2];
    return intval($x[3]) . " $mon" . ($year ? " $x[1]" : '');
}

// "Y-m-d" -> "16 ноября 2001"; a day after today (UTC): "Выходит 14.11.2026"; '' for none.
/**
 * @param string $d
 * @param string $lang
 * @return string
 */
function mel_oc_date($d, $lang)
{
    if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $d, $x) && $d > gmdate('Y-m-d'))
        return str_replace('{d}', "$x[3].$x[2].$x[1]", mel_tr($lang, 'card_coming'));
    return mel_oc_day($d, $lang);
}

// --- Layout.

// "Смотреть" of card $c: with an IMDb, else of its meta loaded (an IMDb may be in one not loaded: "Повторить" first),
// the releases by its TMDB (a card of no IMDb has one, mel_cm_id; mel_rel_id, owner 20.11.2026).
/**
 * @param MelOcCard $c
 * @return bool
 */
function mel_oc_can_watch($c)
{
    return $c['imdb'] !== '' || $c['meta'] !== null;
}

// The buttons: "Watch" with an IMDb or the meta (mel_oc_can_watch) or a copy on the disk of a movie ($lib), "Trailer" with one (card_trailer.php), "♡ В избранное" / "✓ В избранном" ("Мой список" of the code) (the
// list now, mel_ml_in); array(kind, text, its icon - the picture MEL_OC_IMG<icon>_icon.png, '' none, the least
// width: the button of the list as wide as the longer of its two texts, design-my-list.md, A: ENTER changes it in
// place, nothing moves; 0 for the others). "Dune card" is in MENU (mel_oc_dune_items), owner 15.11.2026.
// A series: "Continue SxEy" (a record in the history) or "Watch SxEy" of the episode to offer $ep;
// one not out yet - "SxEy · выйдет 16.11" (kind soon: ENTER to it in the row).
/**
 * @param MelOcCard $c
 * @param MelOcEp|null $ep
 * @param bool $lib
 * @return list<array{string, string, string, int}>
 */
function mel_oc_buttons($c, $ep, $lib = false)
{
    $b = array();
    $ui = $c['ui'];
    $can = mel_oc_can_watch($c) || ($lib && !$ep);
    if ($can && !$ep)
        $b[] = array('watch', mel_tr($ui, 'card_watch'), 'play', 0);
    else if ($can)
    {
        $name = mel_se($ep['s'] > 0 ? $ep['s'] : $ep['q'], $ep['e']);
        $x = mel_oc_ep($c, $ep['s'], $ep['e']);
        if ($x && $x['soon'])
        {
            $d = $x['date'] !== '' ? substr($x['date'], 8, 2) . '.' . substr($x['date'], 5, 2) : '';
            $b[] = array('soon', str_replace('{d}', $d, mel_tr($ui, $d !== '' ? 'card_btn_soon' : 'card_btn_not_out', $name)), '', 0);
        }
        else
            $b[] = array('watch', mel_tr($ui, in_array($ep['kind'], array('record', 'next', 'same'), true) ? 'card_continue' : 'card_watch_ep',
                $name), 'play', 0);
    }
    if (mel_ct_has($c))
        $b[] = array('trailer', mel_tr($ui, 'card_trailer'), '', 0);
    if (mel_ml_key($c['tile']) !== '')
    {
        $w = 0;
        foreach (array(array('ml_add_btn', MEL_OC_ML_ADD), array('ml_in', MEL_OC_ML_IN)) as $v)
            $w = max($w, mel_oc_btn_w(array('list', mel_tr($ui, $v[0]), $v[1], 0)));
        $in = mel_ml_in($c['tile'], $c['dune']) !== '';
        $b[] = array('list', mel_tr($ui, $in ? 'ml_in' : 'ml_add_btn'), $in ? MEL_OC_ML_IN : MEL_OC_ML_ADD, $w);
    }
    return $b;
}

// The width of button $b: its text, the edges and the icon, its least width at least.
/**
 * @param array{string, string, string, int} $b
 * @return int
 */
function mel_oc_btn_w($b)
{
    return max($b[3], mel_gc_w($b[1], MEL_CATV_LINE_PX) + 2 * MEL_OC_BTN_PAD + ($b[2] !== '' ? MEL_OC_ICON_W : 0));
}

// The children of button $b of width $w: the ends of PNG, a rect between them, the icon and the text in the middle as
// one (a button wider than them: the button of the list).
/**
 * @param array{string, string, string, int} $b
 * @param int $w
 * @param bool $on
 * @return list<MelGcComp>
 */
function mel_oc_btn_items($b, $w, $on)
{
    $s = $on ? 'on_' : '';
    $d = array(
        mel_gc_image(mel_gc_at(MEL_OC_CAP, MEL_OC_BTN_H, 0, 0), MEL_OC_IMG . "pill_{$s}l.png"),
        mel_gc_rect(mel_gc_at($w - 2 * MEL_OC_CAP, MEL_OC_BTN_H, MEL_OC_CAP, 0), $on ? MEL_GC_SEL : MEL_OC_BTN_OFF),
        mel_gc_image(mel_gc_at(MEL_OC_CAP, MEL_OC_BTN_H, $w - MEL_OC_CAP, 0), MEL_OC_IMG . "pill_{$s}r.png"));
    $tw = mel_gc_w($b[1], MEL_CATV_LINE_PX);
    $icon = $b[2] !== '' ? MEL_OC_ICON_W : 0;
    $x = max(MEL_OC_BTN_PAD, intval(($w - $icon - $tw) / 2));
    if ($icon)
    {
        $d[] = mel_gc_image(mel_gc_left_center(20, 24, $x), MEL_OC_IMG . "{$b[2]}_{$s}icon.png");
        $x += $icon;
    }
    $d[] = mel_gc_cut(null, mel_gc_left_center(min($tw, $w - $x - MEL_OC_BTN_PAD), 44, $x), $b[1], MEL_CATV_LINE_PX,
        $on ? MEL_GC_TEXT : MEL_GC_TEXT2, array(GCompTtfLabelDef::halign => HALIGN_CENTER));
    return $d;
}

// A label of the card at (x, y) of the page, as those of the panel of the catalog (30 px LINE_H high).
/**
 * @param int $x
 * @param int $y
 * @param int $w
 * @param string $text
 * @param int $px
 * @param string $color
 * @param array<string, mixed> $spec
 * @return MelGcComp
 */
function mel_oc_label($x, $y, $w, $text, $px, $color, $spec = array())
{
    $l = mel_catv_info_label(mel_clean($text), $px, $color, $w, mel_oc_label_h($px), $y, $spec);
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::x] = $x;
    return $l;
}

// The height of the box of a label of mel_oc_label of size $px: LINE_H of the panel at its size, else MEL_OC_LINE_H.
/**
 * @param int $px
 * @return int
 */
function mel_oc_label_h($px)
{
    return $px === MEL_CATV_LINE_PX ? MEL_CATV_LINE_H : MEL_OC_LINE_H;
}

// The centre of the capitals of a label of mel_oc_label of size $px at $y, px of the page: the shell sets a label with
// no flags as a line $px high in the middle of its box, the baseline at the bottom of that line (the line under the
// buttons on the device, 20.11.2026: 24 px in 34, the capitals 672-688 at 660); the capitals mel_k_cap() high.
/**
 * @param int $y
 * @param int $px
 * @return float
 */
function mel_oc_label_cy($y, $px)
{
    $caps = mel_k_cap();
    $cap = isset($caps[$px]) ? $caps[$px] : 0.7135 * $px;
    return $y + intval((mel_oc_label_h($px) - $px) / 2) + $px - $cap / 2;
}

// The first screen in the page at place $pos: the head in its panel "hd" (mel_oc_hd), the buttons at the y of the
// layout (lit: mel_oc_btn_lit).
/**
 * @param MelOcCard $c
 * @param MelOcLayout $l
 * @param MelOcPos $pos
 * @return list<array<string, mixed>>
 */
function mel_oc_head($c, $l, $pos)
{
    $b = mel_oc_btn_lit($pos);
    $d = array(mel_gc_panel('hd', mel_gc_at(1920, $l['btn_y'], 0, 0), mel_oc_hd($c, $l, $pos)));
    $x = MEL_CATV_CARD_X;
    foreach ($l['btns'] as $i => $bt)
    {
        $w = mel_oc_btn_w($bt);
        $d[] = mel_gc_panel("b$i", mel_gc_at($w, MEL_OC_BTN_H, $x, $l['btn_y']), mel_oc_btn_items($bt, $w, $i === $b));
        $x += $w + MEL_OC_BTN_GAP;
    }
    return $d;
}

// The head of the first screen at place $pos, the children of the panel "hd": of a series with the focus in the row of
// episodes the episode in focus (mel_oc_ep_head; with the line of its copy on the disk, mel_oc_lib_ep_cur), else the
// lines of the card (mel_oc_head_lines).
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $pos
 * @return list<array<string, mixed>>
 */
function mel_oc_hd($c, $l, $pos)
{
    $ep = mel_oc_ep_on($c, $pos);
    return $ep ? mel_oc_ep_head($c, $ep, mel_oc_lib_ep_cur($l, $ep['s'], $ep['e'])) : mel_oc_head_lines($c, $l);
}

// The lines of the panel (x of the catalog), the description to the top of the highest line over the buttons (a movie,
// over_y of mel_oc_first_movie: DESC_BOTTOM without one); a series: DESC_GAP_SER, DESC_LH_SER, DESC_BOTTOM_SER.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @return list<array<string, mixed>>
 */
function mel_oc_head_lines($c, $l)
{
    $x0 = MEL_CATV_CARD_X;
    // The line of facts in a panel at the x of the catalog: its labels are at x of the panel.
    $d = array(mel_gc_panel('facts', mel_gc_at(MEL_CATV_PANE_W, MEL_OC_GENRES_Y, $x0, 0),
        mel_catv_facts_labels(mel_oc_facts($c), MEL_OC_FACTS_Y)));
    $y = MEL_OC_GENRES_Y;
    $genres = mel_catv_genre_line($c['tile'], $c['ui'], mel_oc_countries($c));
    if ($genres !== '')
    {
        foreach (mel_gc_wrap_items($genres, MEL_CATV_LINE_PX, MEL_CATV_TEXT_W, 1) as $line)
            $d[] = mel_oc_label($x0, $y, MEL_CATV_TEXT_W, $line, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
        $y += MEL_CATV_LINE_H;
    }
    $dirs = $c['meta'] ? mel_cm_names_of($c['meta']['directors']) : array();
    if ($dirs)
    {
        $label = mel_tr($c['ui'], 'card_director');
        $lw = intval(mel_gc_sum($label, MEL_CATV_LINE_PX));
        $d[] = mel_oc_label($x0, $y, $lw, $label, MEL_CATV_LINE_PX, MEL_GC_DIM);
        $gap = intval(mel_gc_sum(' ', MEL_CATV_LINE_PX));
        $d[] = mel_oc_label($x0 + $lw + $gap, $y, MEL_CATV_TEXT_W - $lw - $gap,
            mel_gc_cut_w(mel_clean(implode(', ', $dirs)), MEL_CATV_LINE_PX, MEL_CATV_TEXT_W - $lw - $gap),
            MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
        $y += MEL_CATV_LINE_H;
    }
    $ser = $l['kind'] === 'series';
    $y += $ser ? MEL_OC_DESC_GAP_SER : 14;
    $lh = $ser ? MEL_OC_DESC_LH_SER : MEL_OC_DESC_LH;
    $bottom = $ser ? MEL_OC_DESC_BOTTOM_SER : $l['over_y'];
    $n = max(0, intval(($bottom - $y) / $lh));
    $desc = $c['meta'] && $c['meta']['description'] !== '' ? $c['meta']['description'] : $c['tile']['description'];
    foreach (mel_gc_wrap(mel_clean($desc), MEL_CATV_LINE_PX, MEL_CATV_TEXT_W, $n, true) as $i => $line)
        $d[] = mel_oc_label($x0, $y + $i * $lh, MEL_CATV_TEXT_W, $line, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
    return $d;
}

// The line why of a movie without a copy on the disk, at LINE_Y over the buttons (owner 20.11.2026: from under them):
// no "Смотреть" (mel_oc_can_watch) - the meta failed; no card of the Dune - no history; else none ($hist: the line of
// the history is there, mel_oc_hist; no line of it).
/**
 * @param MelOcCard $c
 * @param bool $hist
 * @return list<array<string, mixed>>
 */
function mel_oc_why($c, $hist)
{
    $line = '';
    if (!mel_oc_can_watch($c))
        $line = mel_tr($c['ui'], 'cat_row_failed');
    else if ($c['dune'] === '')
        $line = mel_tr($c['ui'], 'card_no_history');
    if ($line === '')
        return array();
    return array(mel_oc_label(MEL_CATV_CARD_X, MEL_OC_LINE_Y, MEL_CATV_TEXT_W, $line, MEL_OC_LINE_PX, MEL_CATV_TEXT_COLOR));
}

// The line of the history of the movie of card of history $h over the buttons (variant Б, owner 20.11.2026): that of the
// panel of the catalog (mel_pg_info_line of its badge, mel_cp_badge: the bar, "Осталось 0:58"; watched - the bar full,
// "Просмотрено" and the tick) at its place on the screen (mel_catv_hist_y), in a panel where that of the catalog is
// (MEL_CATV_CARD_X, MEL_CATV_PANE_Y): it does not jump on the way catalog -> card. Not begun: none.
/**
 * @param string $lang
 * @param string $h
 * @return list<array<string, mixed>>
 */
function mel_oc_hist($lang, $h)
{
    $b = mel_cp_badge($h, 'movie');
    if (!$b)
        return array();
    return array(mel_gc_panel('hist', mel_gc_at(MEL_CATV_PANE_W, MEL_CATV_PANE_H, MEL_CATV_CARD_X, MEL_CATV_PANE_Y),
        mel_pg_info_line($b, mel_catv_hist_y(), $lang)));
}

// A series without a card of the Dune or "Смотреть" (mel_oc_can_watch: the meta failed): the line why, right of the
// buttons ($x: their end), as high as their middle.
/**
 * @param MelOcCard $c
 * @param int $x
 * @return list<array<string, mixed>>
 */
function mel_oc_under_ser($c, $x)
{
    $can = mel_oc_can_watch($c);
    if ($can && $c['dune'] !== '')
        return array();
    $line = mel_tr($c['ui'], $can ? 'card_no_history' : 'cat_row_failed');
    return array(mel_oc_label($x, MEL_OC_BTN_Y_SER + (MEL_OC_BTN_H - 34) / 2, MEL_OC_HINT_R - $x, $line, 24, MEL_CATV_TEXT_COLOR));
}

// The arrow "more below" at ($x, $y).
/**
 * @param int $x
 * @param int $y
 * @return MelGcComp
 */
function mel_oc_more($x, $y)
{
    return mel_gc_image(mel_gc_at(MEL_OC_MORE_W, MEL_OC_MORE_H, $x, $y), MEL_OC_IMG . 'more_down.png');
}

// The hint of what DOWN shows: $text and the arrow down (mel_oc_more) at ($x, $y) of the screen.
/**
 * @param string $text
 * @param int $x
 * @param int $y
 * @return list<MelGcComp>
 */
function mel_oc_hint($text, $x, $y)
{
    $hw = intval(mel_gc_sum($text, MEL_CATV_LINE_PX));
    return array(mel_oc_label($x, $y, $hw, $text, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR),
        mel_oc_more($x + $hw + MEL_OC_HINT_ARROW_DX, $y + 21));
}

// The hint $text of mel_oc_hint at $y, its arrow ending at MEL_OC_HINT_R -> array(its components, the x of the text).
/**
 * @param string $text
 * @param int $y
 * @return array{list<MelGcComp>, int}
 */
function mel_oc_hint_right($text, $y)
{
    $hx = MEL_OC_HINT_R - MEL_OC_MORE_W - MEL_OC_HINT_ARROW_DX - intval(mel_gc_sum($text, MEL_CATV_LINE_PX));
    return array(mel_oc_hint($text, $hx, $y), $hx);
}

// "About the movie" (the series) at $y of "down", under the rows: the tagline, the whole
// description, at the right the details (a series: the first episode, the status, the networks).
// -> array(components, the bands of their ink, mel_oc_band); none: no stop.
/**
 * @param MelOcCard $c
 * @param int $y
 * @return array{list<array<string, mixed>>, list<array{int, int}>}
 */
function mel_oc_down($c, $y)
{
    $m = $c['meta'];
    $lang = $c['ui'];
    $x0 = MEL_CATV_CARD_X;
    $d = array();
    $desc = $m && $m['description'] !== '' ? $m['description'] : $c['tile']['description'];
    $rows = array();
    $name = $c['tile']['name'];
    $series = $c['tile']['type'] === 'series';
    if ($m && $m['original'] !== '' && $m['original'] !== $name)
        $rows[] = array('card_original', $m['original']);
    if ($m)
        $rows[] = array($series ? 'card_first_aired' : 'card_released', mel_oc_date($m['released'], $lang));
    if ($m && $series)
        $rows[] = array('card_status', $m['status'] !== '' ? mel_tr($lang, "card_status_{$m['status']}") : '');
    if ($m)
        $rows[] = array('card_director', implode(', ', mel_cm_names_of($m['directors'])));
    if ($m)
        $rows[] = array('card_writers', implode(', ', mel_cm_names_of($m['writers'])));
    if ($m && $series && $m['networks'])
        $rows[] = array('card_networks', implode(', ', $m['networks']));
    else if ($m)
        $rows[] = array('card_studios', implode(', ', $m['studios']));
    $rows[] = array('card_countries', implode(', ', mel_oc_countries($c)));
    $tagline = $m ? $m['tagline'] : '';
    $any = $desc !== '' || $tagline !== '';
    foreach ($rows as $r)
        $any = $any || $r[1] !== '';
    if (!$any)
        return array(array(), array());
    $d[] = mel_oc_label($x0, $y, 800, mel_tr($lang, $series ? 'card_about_series' : 'card_about_movie'), MEL_CATV_LINE_PX,
        MEL_GC_TEXT);
    $bands = array(mel_oc_band($y, MEL_CATV_LINE_PX));
    $ty = $y + 50;
    foreach (mel_gc_wrap(mel_clean($tagline), MEL_CATV_LINE_PX, MEL_OC_DESC2_W, 2, true) as $line)
    {
        $d[] = mel_oc_label($x0, $ty, MEL_OC_DESC2_W, $line, MEL_CATV_LINE_PX, MEL_GC_DIM);
        $bands[] = mel_oc_band($ty, MEL_CATV_LINE_PX);
        $ty += MEL_OC_DESC_LH;
    }
    if ($tagline !== '')
        $ty += 8;
    foreach (mel_gc_wrap(mel_clean($desc), MEL_CATV_LINE_PX, MEL_OC_DESC2_W, MEL_OC_DESC2_LINES, true) as $line)
    {
        $d[] = mel_oc_label($x0, $ty, MEL_OC_DESC2_W, $line, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
        $bands[] = mel_oc_band($ty, MEL_CATV_LINE_PX);
        $ty += MEL_OC_DESC_LH;
    }
    $ry = $y + 50;
    foreach ($rows as $r)
    {
        if ($r[1] === '')
            continue;
        $d[] = mel_oc_label(MEL_OC_DET_X, $ry, MEL_OC_DET_W, mel_tr($lang, $r[0]), 24, MEL_GC_DIM);
        $bands[] = mel_oc_band($ry, 24);
        $ry += 30;
        foreach (mel_gc_wrap(mel_clean($r[1]), MEL_CATV_LINE_PX, MEL_OC_DET_W, 2, true) as $line)
        {
            $d[] = mel_oc_label(MEL_OC_DET_X, $ry, MEL_OC_DET_W, $line, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
            $bands[] = mel_oc_band($ry, MEL_CATV_LINE_PX);
            $ry += 40;
        }
        $ry += 18;
    }
    return array($d, $bands);
}

// The band of the ink of a label of mel_oc_label at $y: from INK under its top to its bottom.
/**
 * @param int $y
 * @param int $px
 * @return array{int, int}
 */
function mel_oc_band($y, $px)
{
    return array($y + MEL_OC_INK, $y + ($px === MEL_CATV_LINE_PX ? MEL_CATV_LINE_H : 34));
}

// $y off the bands: up to the top of the one it cuts ($up) or down to its bottom, again while it
// cuts one (of the other column) -> an edge of the clip that cuts no line.
/**
 * @param list<array{int, int}> $bands
 * @param int $y
 * @param bool $up
 * @return int
 */
function mel_oc_clear($bands, $y, $up)
{
    do
    {
        $moved = false;
        foreach ($bands as $b)
        {
            if ($b[0] < $y && $y < $b[1])
            {
                $y = $up ? $b[0] : $b[1];
                $moved = true;
            }
        }
    } while ($moved);
    return $y;
}

// The shifts of the page, the bottom of the clip on the screen and its top at each. 0 the first screen;
// a stop for each of $strips (mel_oc_stack), its title at DOWN_Y, the top of the clip VIEW_Y; a row of posters (the
// stubs too) at RI_Y, the top INK over it, the header of its poster in focus over the clip;
// with something below them ($bands of mel_oc_down, "About" at $about), the stop after the last
// row puts "About" at the top of the clip (the row goes whole), without rows "About" at DOWN_Y;
// then STEP further at most while the page goes on below the clip, the last ($max) its end at the
// bottom of the clip, MIN_STEP after the one before at least. The top of the clip at a stop and its
// bottom cut no line (mel_oc_clear; the lines of the rows too, mel_oc_row_bands): a line cut at the top goes
// down whole, at the bottom it is not shown. $off: the shift of the stop of the coordinates of the
// bands ("down").
/**
 * @param list<array{int, int}> $bands
 * @param array<string, MelOcStrip> $strips
 * @param int $about
 * @param int $off
 * @return array{list<int>, list<int>, list<int>}
 */
function mel_oc_stops($bands, $strips, $about, $off)
{
    $stops = array(0);
    $tops = array(MEL_OC_VIEW_Y);
    $rowbands = array();
    foreach ($strips as $id => $st)
    {
        $at = $id === 'c' ? MEL_OC_DOWN_Y : MEL_OC_RI_Y;
        $stops[] = $off + $st['y'] - MEL_OC_ROW_HEAD - $at;
        $tops[] = $id === 'c' ? MEL_OC_VIEW_Y : MEL_OC_RI_Y - MEL_OC_INK;
        $rowbands = array_merge($rowbands, mel_oc_row_bands($st));
    }
    // The top of the clip at stop $s in the coordinates of the bands: $s + $top.
    $top = MEL_OC_VIEW_Y - $off;
    if ($bands)
    {
        $end = 0;
        foreach ($bands as $b)
            $end = max($end, $b[1]);
        $max = $off + $end - MEL_OC_SHOW_BOTTOM;
        $lo = $strips ? $about - $top : 0;
        if (!$strips)
        {
            $stops[] = $off + $about - MEL_OC_DOWN_Y;
            $tops[] = MEL_OC_VIEW_Y;
        }
        $s = $stops[count($stops) - 1];
        while ($s < $max)
        {
            $n = $lo > $s ? $lo : max($s + MEL_OC_MIN_STEP, mel_oc_clear($bands, $s + MEL_OC_STEP + $top, true) - $top);
            if ($n > $max - MEL_OC_MIN_STEP)
                $n = max($lo, mel_oc_clear($bands, max($max, $s + MEL_OC_MIN_STEP) + $top, false) - $top);
            $stops[] = $s = $n;
            $tops[] = MEL_OC_VIEW_Y;
        }
    }
    $all = array_merge($bands, $rowbands);
    $clips = array();
    foreach ($stops as $s)
        $clips[] = mel_oc_clear($all, MEL_OC_SHOW_BOTTOM - $off + $s, true) + $off - $s;
    return array($stops, $clips, $tops);
}

// The card laid out: its kind, buttons and their y, the stops of the page, the bottom of the clip
// at each and its top (VIEW_Y; at the stop of a row of posters INK over its title, RI_Y) and the height of the page, the shift of the coordinates of "down" (off), the zones of
// the focus in the order of UP and DOWN, the rows by their stops and their strips (mel_oc_stack:
// the rows of posters of mel_oc_poster_rows, then the cast of mel_oc_people, "c"; a movie: the
// first of them under the buttons), the width of the title of the first row (tw: before the hint
// of a movie, TITLE_W at most), what the first screen alone shows (what the keys need,
// MelOcNav), the components of "About" under the rows (the rows over them by the place,
// mel_oc_window). The first screen alone: the lines over the buttons (a movie: the line of its history, read here
// once a request, and that of its copy or why, mel_oc_first_movie; over_y: the top of the highest of them, the
// description over it, mel_oc_head_lines) - their foot would peep under the logo at DOWN - and the hint of what DOWN
// shows ("Похожие, в ролях и о фильме"). A series: the history read once (mel_oc_pick): the episode to offer and the progress of the
// episodes; the tabs of its seasons and the row, or the line "did not load" without the meta.
/**
 * @param MelOcCard $c
 * @return MelOcLayout
 */
function mel_oc_layout($c)
{
    $series = $c['tile']['type'] === 'series';
    list($people, $nc) = mel_oc_people($c);
    $cast = count($people) > 0;
    $off = $series ? MEL_OC_OFF_SER : MEL_OC_OFF;
    $list = array();
    $posters = mel_oc_poster_rows($c);
    foreach ($posters as $id => $r)
        $list[] = mel_oc_poster_strip($id, $id === 'w' ? MEL_OC_POSTER_VIS : count($r['tiles']), $r['nc'], 0, MEL_OC_TILE_CH);
    if ($cast)
        $list[] = mel_oc_cast_strip(count($people), $nc);
    list($rows, $strips, $about) = mel_oc_stack($list);
    $about = max($about, MEL_OC_ABOUT_Y);
    list($down, $bands) = mel_oc_down($c, $about);
    $ep = null;
    $prog = array();
    $ol = mel_oc_lib($c);
    if ($series)
        list($ep, $prog) = mel_oc_pick($c, $ol['fix'], $ol['hid']);
    $btns = mel_oc_buttons($c, $ep, (bool) $ol['lib']);
    list($stops, $clips, $tops) = mel_oc_stops($bands, $strips, $about, $off);
    $tw = MEL_OC_TITLE_W;
    $tabs = $series && $c['meta'] ? mel_oc_tabs_layout($c, $ol['lib_eps'])
        : array('list' => array(), 'x' => 0, 'w' => 0, 'all' => 0, 'word' => 0, 'cx' => 0);
    $zones = array('btn');
    if ($series && (!$c['meta'] || $tabs['list']))
        $zones[] = 'pill';
    if ($tabs['list'])
        $zones[] = 'row';
    if (count($stops) > 1)
        $zones[] = 'down';
    $over_y = MEL_OC_DESC_BOTTOM;
    if ($series)
        $only = mel_oc_first_series($c, $btns, $down || $cast || $posters);
    else
    {
        list($only, $over_y) = mel_oc_first_movie($c, $ol['lib'], $ol['hid']);
        if ($down || $posters)
        {
            $below = array_slice($rows, 1);
            $more_cast = in_array('c', $below, true);
            $k = in_array('s', $below, true) || in_array('w', $below, true) ?
                ($more_cast ? 'card_sim_cast_about_movie' : 'card_sim_about_movie') :
                ($more_cast ? 'card_cast_about_movie' : 'card_about_movie');
            list($hint, $hx) = mel_oc_hint_right(mel_tr($c['ui'], $k), MEL_OC_CAST_Y);
            $only = array_merge($only, $hint);
            $tw = min($tw, $hx - MEL_OC_HINT_GAP - MEL_CATV_CARD_X);
        }
    }
    return array('kind' => $series ? 'series' : 'movie', 'btns' => $btns, 'btn_y' => $series ? MEL_OC_BTN_Y_SER : MEL_OC_BTN_Y,
        'stops' => $stops, 'clips' => $clips, 'tops' => $tops, 'h' => max(1080, $stops[count($stops) - 1] + MEL_OC_SHOW_BOTTOM), 'off' => $off,
        'zones' => $zones, 'tabs' => $tabs, 'ep' => $ep, 'prog' => $prog, 'rows' => $rows, 'strips' => $strips, 'tw' => $tw,
        'first' => $only, 'over_y' => $over_y, 'down' => $down, 'lib' => $ol['lib'], 'hid' => $ol['hid'],
        'lib_eps' => $ol['lib_eps'], 'lib_y' => $ol['lib_y'], 'lib_cur' => $ol['lib_cur']);
}

// The first screen of the series of card $c alone, of buttons $btns: the line why right of them (mel_oc_under_ser),
// the arrow of what DOWN shows when there is something below ($more).
/**
 * @param MelOcCard $c
 * @param list<array{string, string, string, int}> $btns
 * @param bool $more
 * @return list<array<string, mixed>>
 */
function mel_oc_first_series($c, $btns, $more)
{
    $x = MEL_CATV_CARD_X;
    foreach ($btns as $b)
        $x += mel_oc_btn_w($b) + MEL_OC_BTN_GAP;
    $only = mel_oc_under_ser($c, $x + 12);
    if ($more)
        $only[] = mel_oc_more(MEL_OC_HINT_X_SER, MEL_OC_HINT_Y_SER);
    return $only;
}

// The first screen of the movie of card $c alone, of copies on the disk $lib of card of history $hid (mel_oc_lib): the
// lines over the buttons (variant Б, owner 20.11.2026) - that of its history (mel_oc_hist) and that of its copy
// (mel_oc_lib_line) or why (mel_oc_why) -> array(the components, the top of the highest of them: the line of the copy
// (MEL_OC_LIB_Y) or why (MEL_OC_LINE_Y), else that of the history (its panel at MEL_CATV_PANE_Y, mel_catv_hist_y), else
// DESC_BOTTOM - the description over it, mel_oc_head_lines).
/**
 * @param MelOcCard $c
 * @param list<MelLibCopy> $lib
 * @param string $hid
 * @return array{list<array<string, mixed>>, int}
 */
function mel_oc_first_movie($c, $lib, $hid)
{
    // The history: by $hid with a copy (the file plays), else by the card of the Dune when "Watch" plays.
    $h = $lib ? $hid : ($c['dune'] !== '' && mel_oc_can_watch($c) ? $c['dune'] : '');
    $hist = $h !== '' ? mel_oc_hist($c['ui'], $h) : array();
    // A copy on the disk: the line of that of its latest record (mel_oc_lib_cur), else of the best (owner
    // 18-19.11.2026), with the count of two copies or more, in place of the lines of no IMDb, no Lampac or no card of
    // the Dune (the file plays).
    if ($lib)
    {
        $cur = mel_oc_lib_cur($lib, $hid);
        $line = mel_oc_lib_line($c['ui'], $cur ? $cur : $lib[0], MEL_CATV_CARD_X, count($lib));
    }
    else
        $line = mel_oc_why($c, (bool) $hist);
    $top = $line ? ($lib ? MEL_OC_LIB_Y : MEL_OC_LINE_Y) : ($hist ? MEL_CATV_PANE_Y + mel_catv_hist_y() : MEL_OC_DESC_BOTTOM);
    return array(array_merge($hist, $line), $top);
}

// The clip of the page at stop $p of layout $l, from its top (under the logo; at the stop of a row of posters under
// the header of the poster in focus) down to its bottom, and the page in it at the shift of the stop:
// array(the clip, the page).
/**
 * @param MelOcNav $l
 * @param int $p
 * @return array{MelGcGeom, MelGcGeom}
 */
function mel_oc_clip_geoms($l, $p)
{
    $top = $l['tops'][$p];
    return array(mel_gc_at(1920, $l['clips'][$p] - $top, 0, $top), mel_gc_at(1920, $l['h'], 0, -$top - $l['stops'][$p]));
}

// The background of card $c in zone $z, the children of the panel "bg" under all: the card
// background of the server with the logo baked in (mel_catv_card), card0 while it loads and when it
// fails; without the logo the title as in the panel of the catalog over it. DOWN none, the window
// black. A picture of the panel, not the background of the window: rows moving over a picture with
// any layer above it are jerky, over black smooth (hrow 0.5.0).
/**
 * @param MelOcCard $c
 * @param string $z
 * @return list<MelGcComp>
 */
function mel_oc_bg($c, $z)
{
    if ($z === 'down')
        return array();
    $card = mel_catv_card($c['tile'], true);
    $img = mel_gc_image(mel_gc_at(1920, 1080, 0, 0), $card['bg']);
    $img[GComponentDef::specific_def][GCompImageDef::not_loaded_url] = $card['nl'];
    $img[GComponentDef::specific_def][GCompImageDef::load_failed_url] = $card['nl'];
    if ($card['logo'])
        return array($img);
    return array($img, mel_gc_panel('title', mel_gc_at(MEL_CATV_PANE_W, MEL_OC_VIEW_Y, MEL_CATV_CARD_X, 0),
        array(mel_catv_title_label(mel_clean($c['tile']['name']), MEL_OC_VIEW_Y))));
}

// --- The place of the focus.

// A place on the card, MelOcPos, in MelOc::$cards and as sel_state alike: z the zone ("btn" the
// buttons, "pill" the tabs of the seasons, "row" the episodes, "down" the page below them), b the
// button (kept in every zone: UP comes back to it), p the stop of the page (0 but in "down"), s
// and e the season and the episode of the row of a series (0 for none, not in sel_state), r the
// item of each row by its id (MEL_OC_ROW_IDS; kept in every zone: a row comes back to it, LEFT and
// RIGHT at its stop; kept for a row the layout has not now too). A new zone or field is one more
// line in mel_oc_pos and mel_oc_next. In sel_state also rs: the ids of the rows of layout $l by their
// stops ("s,c"; mel_oc_stop_of; mel_oc_input: a screen of other rows is drawn anew).
/**
 * @param MelOcPos $pos
 * @param MelOcNav $l
 * @return string
 */
function mel_oc_state($pos, $l)
{
    $j = array('z' => $pos['z'], 'b' => $pos['b'], 'p' => $pos['p'], 'rs' => implode(',', $l['rows']));
    if ($pos['s'] > 0)
        $j += array('s' => $pos['s'], 'e' => $pos['e']);
    // An object even with no rows: {} as the card has them.
    $j['r'] = (object) $pos['r'];
    return json_encode($j);
}

// The button lit at place $pos: the one of the focus in the zones of the buttons and of the page
// below (dark there), none in the zones of the series; -1 for none.
/**
 * @param MelOcPos $pos
 * @return int
 */
function mel_oc_btn_lit($pos)
{
    return $pos['z'] === 'btn' || $pos['z'] === 'down' ? $pos['b'] : -1;
}

// sel_state of the shell -> the place, each field checked alone: one missing or of a wrong type
// is that of the last place of the card, a number out of the card is clamped (one button less
// keeps the stop), a zone the card has not: the buttons. {"b", "p"} of before the zone: the zone of
// its stop. A season not of the card: that of the episode to offer, else the first; an episode
// not of the season: mel_oc_def_e. r: each row of MEL_OC_ROW_IDS as a field (one not in r or of a
// wrong type: that of the last place, else 0), clamped to its strip if the layout has it, else
// kept (its row did not come now: its place for when it does), 0 at least; others dropped; in the
// order of MEL_OC_ROW_IDS. p of a sel_state with rs: of the same place now (mel_oc_stop_of). A
// sel_state of before r:
// its k the person of the cast (2.0, stage 2); of before k (0 was not in it then) that of the last
// place, 0 as the card was made anew after the restart of php_server.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param mixed $raw
 * @return MelOcPos
 */
function mel_oc_pos($c, $l, $raw)
{
    $j = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($j))
        $j = array();
    $last = $c['pos'];
    if (isset($j['z']) && is_string($j['z']))
        $z = $j['z'];
    else if (isset($j['p']) && is_int($j['p']))
        $z = $j['p'] > 0 ? 'down' : 'btn';
    else
        $z = $last['z'];
    $b = isset($j['b']) && is_int($j['b']) ? $j['b'] : $last['b'];
    $p = isset($j['p']) && is_int($j['p']) ? $j['p'] : $last['p'];
    if (isset($j['p'], $j['rs']) && is_int($j['p']) && is_string($j['rs']))
        $p = mel_oc_stop_of($j['rs'] !== '' ? explode(',', $j['rs']) : array(), $l['rows'], $p);
    $s = isset($j['s']) && is_int($j['s']) ? $j['s'] : $last['s'];
    $e = isset($j['e']) && is_int($j['e']) ? $j['e'] : $last['e'];
    $src = isset($j['r']) && is_array($j['r']) ? $j['r'] : (isset($j['k']) ? array('c' => $j['k']) : array());
    $r = array();
    foreach (explode(',', MEL_OC_ROW_IDS) as $id)
    {
        $k = isset($src[$id]) && is_int($src[$id]) ? $src[$id] : (isset($last['r'][$id]) ? $last['r'][$id] : null);
        if (isset($l['strips'][$id]))
            $r[$id] = max(0, min($l['strips'][$id]['n'] - 1, intval($k)));
        else if ($k !== null)
            $r[$id] = max(0, $k);
    }
    if (!in_array($z, $l['zones'], true))
        $z = 'btn';
    if (!mel_oc_eps($c, $s))
    {
        $s = $l['ep'] && mel_oc_eps($c, $l['ep']['s']) ? $l['ep']['s'] : 0;
        if ($s === 0 && $c['meta'] && $c['meta']['seasons'])
            $s = key($c['meta']['seasons']);
        $e = 0;
    }
    if (mel_cm_ep_index(mel_oc_eps($c, $s), $e) < 0)
        $e = mel_oc_def_e($c, $l, $s);
    return array('z' => $z, 'b' => max(0, min(count($l['btns']) - 1, $b)),
        'p' => $z === 'down' ? max(1, min(count($l['stops']) - 1, $p)) : 0, 's' => $s, 'e' => $e,
        'r' => $r);
}

// The place after key $d (left, right, up, down, pgup, pgdn) from $pos; $pos itself at an edge.
// Up and down go from zone to zone in the order of the layout (the buttons, the tabs, the row, the
// page below), in "down" from stop to stop first, entering it at the first stop. Left and right:
// along the buttons; along the tabs, the season of the tab shown in the row at once at its episode
// (mel_oc_def_e); along the row; P+ and P- (pgup, pgdn) PAGE stills along the row; at the stop of
// a row (mel_oc_strip_on) along it, but the stubs.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $pos
 * @param string $d
 * @return MelOcPos
 */
function mel_oc_next($c, $l, $pos, $d)
{
    $n = $pos;
    $z = $pos['z'];
    if ($d === 'up' || $d === 'down')
    {
        if ($z === 'down' && ($d === 'down' || $pos['p'] > 1))
        {
            $n['p'] = $d === 'down' ? min(count($l['stops']) - 1, $pos['p'] + 1) : $pos['p'] - 1;
            return $n;
        }
        $k = intval(array_search($z, $l['zones'], true)) + ($d === 'down' ? 1 : -1);
        if ($k < 0 || $k >= count($l['zones']))
            return $n;
        $n['z'] = $l['zones'][$k];
        $n['p'] = $n['z'] === 'down' ? 1 : 0;
        return $n;
    }
    $steps = array('left' => -1, 'right' => 1, 'pgup' => -MEL_OC_PAGE, 'pgdn' => MEL_OC_PAGE);
    if (!isset($steps[$d]))
        return $n;
    $step = $steps[$d];
    $arrow = $step === -1 || $step === 1;
    if ($z === 'btn' && $arrow)
        $n['b'] = max(0, min(count($l['btns']) - 1, $pos['b'] + $step));
    else if ($z === 'pill' && $arrow && $l['tabs']['list'])
    {
        $k = 0;
        foreach ($l['tabs']['list'] as $i => $tab)
        {
            if ($tab[0] === $pos['s'])
                $k = $i;
        }
        $tab = $l['tabs']['list'][max(0, min(count($l['tabs']['list']) - 1, $k + $step))];
        if ($tab[0] !== $pos['s'])
        {
            $n['s'] = $tab[0];
            $n['e'] = mel_oc_def_e($c, $l, $tab[0]);
        }
    }
    else if ($z === 'row')
    {
        $eps = mel_oc_eps($c, $pos['s']);
        if ($eps)
            $n['e'] = $eps[max(0, min(count($eps) - 1, mel_cm_ep_index($eps, $pos['e']) + $step))]['e'];
    }
    else if ($arrow && ($id = mel_oc_strip_on($l, $pos)) !== '' && $id !== 'w')
    {
        list($k) = mel_oc_strip_at($l['strips'][$id], mel_oc_strip_k($pos, $id) + $step);
        $n['r'][$id] = $k;
    }
    return $n;
}

// The changes of the headers that follow the focus from place $old to $new, in the same call as the row moves: the
// head of a series with the episode in focus (mel_oc_hd, H3), the header of the poster in focus over a row of posters
// (mel_oc_ri).
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $old
 * @param MelOcPos $new
 * @return list<MelGcChange>
 */
function mel_oc_head_changes($c, $l, $old, $new)
{
    $ch = array();
    if (mel_oc_ep_on($c, $new) !== mel_oc_ep_on($c, $old))
        $ch[] = mel_gc_change('hd', null, GCOMP_TRANSITION_NONE, mel_oc_hd($c, $l, $new));
    if (mel_oc_ri_of($l, $new) !== mel_oc_ri_of($l, $old))
        $ch[] = mel_gc_change('ri', null, GCOMP_TRANSITION_NONE, mel_oc_ri($c, $l, $new));
    return $ch;
}

// The change_defs from place $old to $new: the lit buttons redrawn, the tabs, the row and its frame
// (mel_oc_row_changes) or the line "did not load", the headers (mel_oc_head_changes), the rows (mel_oc_rows_changes), the page and the
// bottom of its clip moved, the background off or on, what the first screen alone shows off or on.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $old
 * @param MelOcPos $new
 * @return list<MelGcChange>
 */
function mel_oc_changes($c, $l, $old, $new)
{
    $ch = array();
    $ob = mel_oc_btn_lit($old);
    $nb = mel_oc_btn_lit($new);
    if ($nb !== $ob)
    {
        foreach (array($ob, $nb) as $i)
        {
            if ($i >= 0)
                $ch[] = mel_gc_change("b$i", null, GCOMP_TRANSITION_NONE,
                    mel_oc_btn_items($l['btns'][$i], mel_oc_btn_w($l['btns'][$i]), $i === $nb));
        }
    }
    if ($l['tabs']['list'])
        $ch = array_merge($ch, mel_oc_row_changes($c, $l, $old, $new));
    else if ($l['kind'] === 'series' && !$c['meta'] && ($old['z'] === 'pill') !== ($new['z'] === 'pill'))
    {
        $r = mel_oc_retry_panel($c, $new['z']);
        $ch[] = mel_gc_change('retry', null, GCOMP_TRANSITION_NONE, $r[GComponentDef::specific_def][GCompPanelDef::children]);
    }
    $ch = array_merge($ch, mel_oc_head_changes($c, $l, $old, $new), mel_oc_rows_changes($c, $l, $old, $new));
    if ($new['p'] !== $old['p'])
    {
        list($view, $page) = mel_oc_clip_geoms($l, $new['p']);
        $ch[] = mel_gc_change('view', $view, GCOMP_TRANSITION_DEFAULT);
        $ch[] = mel_gc_change('page', $page, GCOMP_TRANSITION_DEFAULT);
    }
    if (($new['z'] === 'down') !== ($old['z'] === 'down'))
    {
        $ch[] = mel_gc_change('bg', null, GCOMP_TRANSITION_NONE, mel_oc_bg($c, $new['z']));
        $ch[] = mel_gc_change('first', null, GCOMP_TRANSITION_NONE, $new['z'] === 'down' ? array() : $l['first']);
    }
    return $ch;
}

// --- The screen.

/**
 * @param MelOcCard $c
 * @param MelOcLayout $l
 * @param MelOcPos $pos
 * @return array<string, mixed>
 */
function mel_oc_window($c, $l, $pos)
{
    // No layer over the background (mel_oc_bg): card/v1 is near black below the buttons itself.
    $d = array();
    $d[] = mel_gc_panel('bg', mel_gc_at(1920, 1080, 0, 0), mel_oc_bg($c, $pos['z']));
    $series = $l['kind'] === 'series';
    $page = array_merge(mel_oc_head($c, $l, $pos), $series ? mel_oc_series($c, $l, $pos) : array());
    // The rows and "About", in a panel off lower: its own coordinates are those of the stop of the
    // first row.
    $down = array_merge(mel_oc_rows($c, $l, $pos), $l['down']);
    if ($down)
        $page[] = mel_gc_panel('down', mel_gc_at(1920, $l['h'] - $l['off'], 0, $l['off']), $down);
    list($view, $pg) = mel_oc_clip_geoms($l, $pos['p']);
    $d[] = mel_gc_panel('view', $view, array(mel_gc_panel('page', $pg, $page, 0)), 0);
    // What the first screen alone shows, over the clip: the arrow of a series is under its bottom.
    $d[] = mel_gc_panel('first', mel_gc_at(1920, 1080, 0, 0), $pos['z'] === 'down' ? array() : $l['first']);
    $d[] = mel_gc_panel('ri', mel_gc_at(1920, MEL_OC_RI_Y - MEL_OC_INK, 0, 0), mel_oc_ri($c, $l, $pos));
    // The slot of the overlay (the window of the copies) last; the others as before.
    if (mel_oc_slot($l))
        $d[] = mel_ovl_slot();
    return array(
        GCompWindowDef::background_color => 'rgb(0,0,0)',
        GCompWindowDef::background_url => null,
        GCompWindowDef::async_loading_background => true,
        GCompWindowDef::comp_defs => $d,
        GCompWindowDef::ui_state => null,
        GCompWindowDef::not_loaded_background_url => null,
        GCompWindowDef::playback_bg_alpha => -1,
        GCompWindowDef::background_fit_def => array(
            ImageFitDef::base_halign_ratio => 0.5,
            ImageFitDef::base_valign_ratio => 0.5),
        GCompWindowDef::small_state_text => null,
        GCompWindowDef::opaque_background => false);
}

// The items of the rows at place $pos for the log, those not the first: ", rows c 3, s 5"; '' none.
/**
 * @param MelOcPos $pos
 * @return string
 */
function mel_oc_rows_log($pos)
{
    $out = array();
    foreach ($pos['r'] as $id => $k)
    {
        if ($k > 0)
            $out[] = "$id $k";
    }
    return $out ? ', rows ' . implode(', ', $out) : '';
}

// get_folder_view of "card:<type>:<id>"; a card not in memory and not made anew: "outdated". Rows of
// posters still asked: the timer (mel_oc_load). The overlay of an old screen of the card dropped (mel_oc_ovl_drop): open,
// it would keep card_load of the new one from putting it.
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_oc_folder_view($media_url, $sel_state)
{
    $key = strval(substr($media_url, strlen('card:')));
    $c = mel_oc_get($key);
    if (!$c)
        return mel_lines_view(array(mel_expired_item()));
    mel_oc_ovl_drop($key);
    $l = mel_oc_layout($c);
    $pos = mel_oc_pos($c, $l, $sel_state);
    MelOc::$cards[$key]['pos'] = $pos;
    $nav = $l;
    unset($nav['down']);
    MelOc::$cards[$key]['nav'] = $nav;
    mel_log("card: own $key: screen, " . count($l['btns']) . ' buttons, ' . count($l['stops']) . " stops, at {$pos['z']} " .
        "{$pos['b']}, {$pos['p']}" . ($pos['s'] > 0 ? ", S{$pos['s']}E{$pos['e']}" : '') . mel_oc_rows_log($pos));
    list($acts, $timer) = mel_oc_acts($key, $c, $nav);
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_oc_window($c, $l, $pos),
            PluginGCompsFolderView::sel_state => mel_oc_state($pos, $l),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => $timer));
}

// The action map of the screen of card $c ($key) laid out as $l, and its timer (null none): the keys of the card, back
// from the player of a title with a copy on the disk (a movie, an episode), the timer (card_load, mel_oc_tick) of the
// rows of posters still asked or of the window of the copies to open (lw_auto). No RETURN: the shell leaves the screen
// by it.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @return array{array<string, MelAction>, array<string, int>|null}
 */
function mel_oc_acts($key, $c, $l)
{
    $k = array('c' => $key);
    $acts = array(
        GUI_EVENT_KEY_LEFT => mel_input('card_move', $k + array('d' => 'left')),
        GUI_EVENT_KEY_RIGHT => mel_input('card_move', $k + array('d' => 'right')),
        GUI_EVENT_KEY_UP => mel_input('card_move', $k + array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => mel_input('card_move', $k + array('d' => 'down')),
        GUI_EVENT_KEY_ENTER => mel_input('card_enter', $k),
        GUI_EVENT_KEY_POPUP_MENU => mel_input('card_menu', $k)) + mel_egg_acts();
    // A movie with a copy on the disk, a series with one of an episode (it plays from this screen; C8): back from the
    // player after STOP only, not between the episodes (as mel_finish_act). No copy: the screen as before 1г, the list
    // has its own.
    if (mel_oc_slot($l))
    {
        $acts[GUI_EVENT_MENU_PLAYBACK_FINISH] = mel_input('card_finish', $k);
        $acts[GUI_EVENT_MENU_PLAYBACK_FINISH][GuiAction::flags] = GUI_ACTION_FLAG_SKIP_ON_PLAY_RESTART;
    }
    if ($l['tabs']['list'])
        $acts += array(
            GUI_EVENT_KEY_P_PLUS => mel_input('card_move', $k + array('d' => 'pgup')),
            GUI_EVENT_KEY_P_MINUS => mel_input('card_move', $k + array('d' => 'pgdn')));
    $timer = null;
    // The rows of posters still asked, a window of the copies to open (lw_auto): the first tick (mel_oc_tick).
    if ($c['posters']['wait'] || isset($c['lw_auto']))
    {
        $acts[GUI_EVENT_TIMER] = mel_input('card_load', $k);
        $timer = array(GuiTimerDef::delay_ms => MEL_OC_TICK_MS);
    }
    return array($acts, $timer);
}

// The record of the screen of card $key under an overlay (MelOvlScreen, kit_ovl.php): owner the key, its action map
// as the shell has it (mel_oc_acts and the keys Fav 2 and Fav 1 mel_mlv_keys adds: the close puts the map back whole by
// change_behaviour; no RETURN), its timer (card_load of the rows of posters still asked, else null; off while the overlay
// is open: a tick puts the screen anew), its sel_state at the place of the focus. A card not in memory or not shown
// (no nav), or its screen with no slot of the overlay (mel_oc_slot): null.
/**
 * @param string $key
 * @return MelOvlScreen|null
 */
function mel_oc_scr($key)
{
    if (!isset(MelOc::$cards[$key]['nav']) || !mel_oc_slot(MelOc::$cards[$key]['nav']))
        return null;
    $c = MelOc::$cards[$key];
    $l = $c['nav'];
    list($acts, $timer) = mel_oc_acts($key, $c, $l);
    return array('owner' => $key, 'map' => $acts + mel_mlv_key_acts("card:$key"), 'timer' => $timer,
        'sel' => mel_oc_state($c['pos'], $l));
}

// The screen of the card of copies $l (its layout or nav) has the slot of the overlay (mel_oc_window), one opens over it
// (mel_oc_scr): a title with copies on the disk - of a movie or of the episodes of a series.
/**
 * @param array{lib: list<MelLibCopy>, lib_eps: array<string, list<MelLibCopy>>} $l
 * @return bool
 */
function mel_oc_slot($l)
{
    return $l['lib'] || $l['lib_eps'];
}

// The screen of card $key put anew (card_load with the overlay closed, card_finish, a new screen of the card: left by
// Home with the window open, then opened again): the overlay it opened (the window of the copies) is not that of the
// new one - its state gone (a key of it after this one is of a stale screen, mel_ovl_stale).
/**
 * @param string $key
 */
function mel_oc_ovl_drop($key)
{
    if (MelOvl::$st && MelOvl::$st['scr']['owner'] === $key)
        MelOvl::$st = null;
}

// card_load, the timer of the screen of card $key laid out as $l (mel_oc_acts), in this order:
//   - its window of the copies open (a tick sent before the overlay took the timer off): nothing - the screen put anew
//     would take the window away; the close gives the timer back, a tick then;
//   - a window to open (lw_auto, mel_lib_win_card: "Continue" of the native with a copy to choose): the flag off, the
//     window W2 of those copies over the screen, mode first (mel_lib_win_open) - before the rows of posters (mel_oc_load
//     puts the screen anew, its nav gone); only of the copies the screen shown has (mel_oc_lib_shown of nav), else
//     none, logged, and on to the posters;
//   - the rows of posters (mel_oc_load), the overlay of the screen before dropped (mel_oc_ovl_drop).
/**
 * @param string $key
 * @param MelOcNav $l
 * @return MelAction|null
 */
function mel_oc_tick($key, $l)
{
    if (MelOvl::$st && MelOvl::$st['open'] && MelOvl::$st['scr']['owner'] === $key)
    {
        mel_log("card: own $key: a tick under the overlay, none");
        return null;
    }
    mel_oc_ovl_drop($key);
    if (isset(MelOc::$cards[$key]['lw_auto']))
    {
        $w = MelOc::$cards[$key]['lw_auto'];
        unset(MelOc::$cards[$key]['lw_auto']);
        $shown = isset(MelOc::$cards[$key]['nav']) && mel_oc_lib_shown($l, $w['s'], $w['e']);
        $a = $shown ? mel_lib_win_open($key, $w['copies'], 'first', $w['s'], $w['e']) : null;
        if ($a)
            return $a;
        mel_log("card: own $key: the window of the copies of \"Continue\" not opened, the screen has none of them");
    }
    return mel_oc_load($key, MelOc::$cards[$key], $l);
}

// --- Keys.

// "Watch": the list of Melange as play_action makes it of the card of the tile, the id of the card
// of the Dune (or none: no history) in movie_str. native_title, as the card of the Dune gives it
// (JacRed asks by it): the original title of the meta, else the English one of the tile. The TMDB and Kinopoisk ids
// for the search of Lampac (mel_lpv_tile_ids). A series:
// episode $e of season $q as asked (mel_cm_req_s), season_numbers of the meta (the episodes out,
// mel_cm_eps) for the playlist of the season and the next episode, the runs of the numbering of
// Cinemeta of an anime numbered on (aio_cin: the numbers of its episodes, mel_cin_n), the titles of the episodes
// (aio_titles, mel_cm_titles), aio_own_card; $ep: the episode of the history (mel_oc_pick) when it is this one, null
// none - the list at its release, the online list at the row of its online record (mel_play_action). The list marked
// own (its online list too): its MENU has no "Choose episode" (choose_season_episode would give the seasons of the
// last native series, not of this card). $src: the profile of "Смотреть из…" (mel_play_action), '' none.
/**
 * @param MelOcCard $c
 * @param int $q
 * @param int $e
 * @param MelOcEp|null $ep
 * @param string $src
 * @return MelAction
 */
function mel_oc_watch($c, $q = -1, $e = -1, $ep = null, $src = '')
{
    return mel_play_action(mel_oc_watch_in($c, $q, $e), $ep, $src);
}

// The input of play_action of card $c (mel_oc_watch; of episode $e of season $q of a series): its movie_str, of the
// English title of the tile too (aio_en_title) - the names the media library is looked for by (mel_lib_title), the
// same of the card (mel_oc_lib) and of its start.
/**
 * @param MelOcCard $c
 * @param int $q
 * @param int $e
 * @return stdClass
 */
function mel_oc_watch_in($c, $q = -1, $e = -1)
{
    $t = $c['tile'];
    $native = $c['meta'] && $c['meta']['original'] !== '' ? $c['meta']['original'] : $t['englishTitle'];
    $mv = array('id' => $c['dune'], 'imdb_id' => $c['imdb'], 'title' => $t['name'],
        'native_title' => $native, 'year' => $t['year'], 'poster_url' => $t['poster'], 'fanart_url' => $t['background'],
        'rate_imdb' => $t['imdbRating'], 'description' => $t['description']) + mel_lpv_tile_ids($t, $c['meta']);
    $series = $t['type'] === 'series';
    if ($t['englishTitle'] !== '')
        $mv['aio_en_title'] = $t['englishTitle'];
    // The list, its online one too (the window of the search builds it later), marked own (mel_movie).
    $mv['aio_own_card'] = true;
    if ($series && $c['meta'])
    {
        $sn = array();
        foreach ($c['meta']['season_numbers'] as $s => $n)
            $sn[$s] = array('episodes_count' => $n);
        $mv['season_numbers'] = $sn;
        if ($c['meta']['cin'])
            $mv['aio_cin'] = $c['meta']['cin'];
        $titles = mel_cm_titles($c['meta']);
        if ($titles)
            $mv['aio_titles'] = $titles;
    }
    $in = new stdClass();
    $in->movie_str = json_encode($mv);
    $in->series_type = $series ? 'series' : 'single';
    if ($series)
    {
        $in->season = $q;
        $in->episode = $e;
    }
    $in->lang = $c['lang'];
    return $in;
}

// ENTER on episode $x of the meta of card $c (the row; the link of streams, mel_dl_streams), $ep the episode the card
// offers (mel_oc_pick; null none): its list (mel_oc_watch: the season asked by mel_cm_req_s, the cursor on the release
// or the online row of the history of $ep when it is this episode); one not out yet: the message (mel_oc_soon_msg).
// $src: the profile of "Смотреть из…", '' none.
/**
 * @param MelOcCard $c
 * @param MelCardEp $x
 * @param MelOcEp|null $ep
 * @param string $src
 * @return MelAction
 */
function mel_oc_ep_watch($c, $x, $ep, $src = '')
{
    if ($x['soon'])
        return mel_oc_soon_msg($x, $c['ui']);
    $q = $c['meta'] ? mel_cm_req_s($c['meta'], $x['s']) : $x['s'];
    return mel_oc_watch($c, $q, $x['e'], $ep && $ep['q'] === $q && $ep['e'] === $x['e'] ? $ep : null, $src);
}

// "Смотреть из…" (src_watch) of card $c from the source of profile $src: the list ENTER opens - on episode $x in focus
// in the row (mel_oc_ep_watch), else of "Смотреть" (a series: of the episode offered, $ep of mel_oc_pick; one not out
// yet - the message, as ENTER on it in the row).
/**
 * @param MelOcCard $c
 * @param MelOcEp|null $ep
 * @param MelCardEp|null $x
 * @param string $src
 * @return MelAction
 */
function mel_oc_watch_from($c, $ep, $x, $src)
{
    if (!$x && $ep)
        $x = mel_oc_ep($c, $ep['s'], $ep['e']);
    if ($x)
        return mel_oc_ep_watch($c, $x, $ep, $src);
    return $ep ? mel_oc_watch($c, $ep['q'], $ep['e'], $ep, $src) : mel_oc_watch($c, -1, -1, null, $src);
}

// "Смотреть из…" of tile $tile ($dune: its card of the Dune known, '' - asked as by ENTER, mel_card_id) from the source of
// profile $src: its own card in memory with its meta, else one made as by ENTER (mel_oc_enter) with no screen, not
// kept: no rows of posters, the tile filled by the meta, no rating asked (mel_oc_fill), a series with a card of the Dune
// - its episodes of the meta to the pair (mel_pg_link_meta, as mel_oc_build) -> mel_oc_watch_from (a series: the
// episode offered, as "▶ Продолжить" of its screen: by H and the files on the disk, mel_oc_lib_pick). No "Смотреть" (mel_oc_can_watch): the message of the card (mel_oc_why) - the meta did
// not load (a tile of TMDB alone).
/**
 * @param MelCatTile $tile
 * @param string $lang
 * @param string $dune
 * @param string $src
 * @return MelAction
 */
function mel_oc_tile_watch($tile, $lang, $dune, $src)
{
    $key = $tile['type'] . ':' . mel_cm_id($tile);
    // One in memory of no meta (it failed at ENTER) is made anew: the meta asked again.
    $kept = isset(MelOc::$cards[$key]) && MelOc::$cards[$key]['meta'];
    if ($kept)
        $c = MelOc::$cards[$key];
    else
    {
        if ($dune === '')
        {
            $d = mel_card_id($tile);
            $dune = $d['id'];
        }
        $g = mel_cm_get($tile);
        $c = mel_oc_make(mel_oc_fill($tile, $g['meta'], array('id' => '', 'none' => false, 'kp' => null)), $g['meta'], $dune,
            $lang, mel_cm_posters_new($tile, $g['meta']));
        if ($g['meta'] && $dune !== '' && $tile['type'] === 'series')
            mel_pg_link_meta($dune, $g['meta']);
    }
    mel_log("card: own $key: watch from, the card " . ($kept ? 'in memory' : 'made') . ', of the Dune ' .
        ($c['dune'] !== '' ? $c['dune'] : 'none'));
    if (!mel_oc_can_watch($c))
        return mel_error('card_meta_failed');
    $ep = null;
    if ($tile['type'] === 'series')
        list($ep) = mel_oc_lib_pick($c);
    return mel_oc_watch_from($c, $ep, null, $src);
}

// MENU of a movie of card $c ($key) with a card of the Dune: "Отметить просмотренным" / "Снять отметку" (mel_pg_mark_kind;
// none when watched by its position), card_mark_movie with w on / off (mel_oc_mark_movie).
/**
 * @param string $key
 * @param MelOcCard $c
 * @return list<array<string, mixed>>
 */
function mel_oc_movie_mark_items($key, $c)
{
    $w = $c['tile']['type'] === 'movie' && $c['dune'] !== '' ? mel_pg_mark_kind($c['dune'], 'movie') : '';
    if ($w === '')
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($c['ui'], $w === 'on' ? 'cp_mark_on' : 'card_mark_off'),
        GuiMenuItemDef::icon_url => MEL_OC_ICONS . 'watched_small.png',
        GuiMenuItemDef::action => mel_input('card_mark_movie', array('c' => $key, 'w' => $w))));
}

// card_mark_movie of MENU of a movie (mel_oc_movie_mark_items, $on: w on) of card $c ($key) -> update_watched_marks of
// its card of the Dune (mel_pg_mark_act: movie_ids; off also the uid of its latest record if marked,
// mel_wh_movie_off_ids; on and off the native uids of its files on the disk, mounted or not, C10:
// mel_oc_lib_native_uids), then mel_oc_mark_post. The log: the counts (", N native uids" with files only), no path.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param bool $on
 * @return MelAction|null
 */
function mel_oc_mark_movie($key, $c, $on)
{
    if ($c['tile']['type'] !== 'movie' || $c['dune'] === '')
        return null;
    $ids = $on ? array() : mel_wh_movie_off_ids($c['dune']);
    $nat = mel_oc_lib_native_uids($c);
    mel_log("card: own $key: mark " . ($on ? 'on' : 'off') . ' of the movie, ' . count($ids) . ' uids' .
        ($nat ? ', ' . count($nat) . ' native uids' : ''));
    return mel_pg_mark_act(array_merge($ids, $nat), array($c['dune']), $on, mel_oc_mark_post($key, $c));
}

// After a mark of MENU of card $c ($key), the post_action of update_watched_marks: the card anew in place (its line
// over the buttons, the marks of the episodes), the main screens of the catalog under it anew first
// (mel_catv_mains_anew: the row "Continue" and the badges), the tab "TV" built anew (mel_tv_flag).
/**
 * @param string $key
 * @param MelOcCard $c
 * @return MelAction|null
 */
function mel_oc_mark_post($key, $c)
{
    mel_tv_flag('a mark of the own card');
    return mel_catv_mains_anew(mel_replace_act("card:$key", $c['tile']['name']), 'after a mark of the own card');
}

// MENU: $items ("Смотреть из…", mel_oc_src_items; the marks of an episode, mel_oc_mark_items; of a movie,
// mel_oc_movie_mark_items; "Карточка Дюны", mel_oc_dune_items), then "Settings", as on the list.
/**
 * @param string $lang
 * @param list<array<string, mixed>> $items
 * @return MelAction
 */
function mel_oc_menu($lang, $items = array())
{
    $items[] = array(
        GuiMenuItemDef::caption => mel_tr($lang, 'setup_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/setup.aai',
        GuiMenuItemDef::action => mel_setup_open_act());
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => $items));
}

// "Смотреть из…" of MENU of card $c ($key) at place $pos of layout $l (mel_src_watch_item: two ready profiles or more):
// card_src_menu of the card, of an episode in focus in the row its s and e; none without "Смотреть" (mel_oc_can_watch)
// and on a strip of posters or of the cast (the title there is another one). A movie with a copy on the disk, a series
// with one of an episode (C4: "Смотреть" may play a file): of one ready profile too, "Смотреть из «<name>»" to its list
// at once (mel_src_watch_items_lib).
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $pos
 * @return list<array<string, mixed>>
 */
function mel_oc_src_items($key, $c, $l, $pos)
{
    if (!mel_oc_can_watch($c) || mel_oc_strip_on($l, $pos) !== '')
        return array();
    $p = array('c' => $key);
    if ($pos['z'] === 'row')
        $p += array('s' => strval($pos['s']), 'e' => strval($pos['e']));
    if ($l['lib'] || $l['lib_eps'])
        return mel_src_watch_items_lib($c['ui'], 'card_src_menu', 'card_src', $p);
    return mel_src_watch_item($c['ui'], 'card_src_menu', $p);
}

// card_finish (GUI_EVENT_MENU_PLAYBACK_FINISH of the screen of card $c, $key; after STOP; the caller drops its overlay,
// mel_oc_ovl_drop): the card anew in place (its progress, its lines over the buttons; of a series the marks and the bars
// of the stills, the episode offered, the copy OK plays, lib_cur), the history stale first (mel_pg_stale: the shell may write the record after
// PLAYBACK_FINISH, within MEL_PG_SIG_S of the last read, and the line of the history would show the old one); after a copy on the disk (played) also the tab "TV" built anew (mel_tv_flag)
// and the main screens of the catalog anew first (mel_catv_after_player: the row "Continue" and the badges), as
// mel_finish of the list.
/**
 * @param string $key
 * @param MelOcCard $c
 * @return MelAction|null
 */
function mel_oc_finish($key, $c)
{
    $played = !empty($c['played']);
    unset(MelOc::$cards[$key]['played']);
    mel_log("card: own $key: back from the player" . ($played ? ', a copy on the disk' : ''));
    mel_pg_stale();
    $a = mel_replace_act("card:$key", $c['tile']['name']);
    if (!$played)
        return $a;
    mel_tv_flag('back from the player');
    return mel_catv_after_player($a);
}

// The params of card_src_menu of input $in for its menu of sources: c, s and e of an episode when there.
/**
 * @param stdClass $in
 * @return array<string, string>
 */
function mel_oc_src_params($in)
{
    $p = array('c' => mel_catv_in($in, 'c'));
    if (mel_catv_in($in, 's') !== '')
        $p += array('s' => mel_catv_in($in, 's'), 'e' => mel_catv_in($in, 'e'));
    return $p;
}

// card_src (p, n of the profile of the menu of sources; s, e of the episode in focus) of card $c of layout $l: the list
// from that profile (mel_oc_watch_from; the episode of s, e, else that of "Смотреть"); the profile gone or not ready:
// its dialog (mel_src_watch_pick), no request.
/**
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_oc_src($c, $l, $in)
{
    list($src, $err) = mel_src_watch_pick($in, $c['ui']);
    if ($src === '' || $err)
        return $err;
    $s = mel_catv_in($in, 's');
    $x = $s !== '' ? mel_oc_ep($c, intval($s), intval(mel_catv_in($in, 'e'))) : null;
    return mel_oc_watch_from($c, $l['ep'], $x, $src);
}

// "Карточка Дюны" of MENU of card $c with a card of the Dune (or an old local one): the native card (file_movie); none
// without one.
/**
 * @param MelOcCard $c
 * @return list<array<string, mixed>>
 */
function mel_oc_dune_items($c)
{
    if ($c['dune'] === '')
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($c['ui'], 'card_dune'),
        GuiMenuItemDef::icon_url => MEL_OC_ICONS . 'movies_small.png',
        GuiMenuItemDef::action => mel_card_open($c['dune'])));
}

// ENTER on the button of the list (mel_oc_buttons, kind list) of card $c ($key) at place $pos of layout $l: the title
// into the list or out of it by the button as it is drawn ("♡": in; the list changed on another screen since - what
// the button says, not the reverse of the file), mel_ml_set (the card of the Dune of the card); the button redrawn in
// place with the focus on it, its width the same (the layout shown, nav, gets the new button), after the screens of the
// list anew (mel_ml_changed: the line of the info panel of the tiles under the card); not written: the message
// (mel_ml_err), the button as it was.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $pos
 * @return MelAction|null
 */
function mel_oc_list($key, $c, $l, $pos)
{
    $on = isset($l['btns'][$pos['b']]) && $l['btns'][$pos['b']][2] === MEL_OC_ML_ADD;
    $why = mel_ml_set($c['tile'], $c['dune'], $on);
    if ($why !== '')
        return mel_ml_err($why);
    $i = $pos['b'];
    $btns = mel_oc_buttons($c, $l['ep'], (bool) $l['lib']);
    if (!isset($btns[$i]) || $btns[$i][0] !== 'list')
        return mel_replace_act("card:$key", $c['tile']['name']);
    if (isset(MelOc::$cards[$key]['nav']))
        MelOc::$cards[$key]['nav']['btns'][$i] = $btns[$i];
    return mel_ml_changed('', array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => array(mel_gc_change("b$i", null, GCOMP_TRANSITION_NONE,
                mel_oc_btn_items($btns[$i], mel_oc_btn_w($btns[$i]), true))),
            ChangeGCompsActionData::num_steps => MEL_GC_STEPS,
            ChangeGCompsActionData::sel_state => mel_oc_state($pos, $l))), 'my list of the own card');
}

// card_move (d: left, right, up, down, pgup, pgdn), card_enter, card_menu, card_mark (mel_oc_mark), card_mark_movie,
// card_load (the timer, mel_oc_tick), card_src_menu, card_src ("Смотреть из…", mel_oc_src), card_lib_copies,
// card_lib_copy (the copies on the disk, mel_oc_lib_input), card_finish (back from the player, mel_oc_finish) of the
// card c. MENU: "Смотреть из…" (mel_oc_src_items), "Копия на диске…" (mel_oc_lib_menu_items), the marks of the episode in focus in the
// row (of a movie: its mark, mel_oc_movie_mark_items), "Карточка Дюны" (mel_oc_dune_items), then "Settings". The keys: the place of mel_oc_next by change_gcomps, null at an edge; the layout of the screen shown
// (nav), else laid out anew. ENTER on a button: "Watch" - a movie with a copy on the disk: the file (mel_oc_lib_start),
// the episode offered with one: its file (mel_oc_lib_start_ep), else the list of the episode offered, an
// episode not out yet - the focus to it in the row, the button of the list - into it or out (mel_oc_list);
// on "did not load" - the meta again; on an episode - with a copy on the disk its file (mel_oc_lib_start_ep), else its
// list, one not out yet - the message; on
// a person of the cast - its page (mel_oc_person); on a poster - the own card of its title
// (mel_oc_tile_enter); below them nothing. A move or ENTER on a screen of rows other than those of
// the layout (rs of its sel_state; another screen of the card got the rows since): the screen anew
// at its place (mel_oc_pos, mel_oc_stop_of), the key not done (its frame and ENTER by the rows it
// shows); a sel_state of before rs: as it is.
/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_oc_input($in, $control)
{
    $key = mel_catv_in($in, 'c');
    $c = mel_oc_get($key);
    if ($control === 'card_menu' && !$c)
        return mel_oc_menu(mel_catv_ui_lang());
    if (!$c)
        return in_array($control, array('card_enter', 'card_trailer', 'card_src'), true) ? mel_error('cat_outdated') : null;
    if ($control === 'card_mark')
        return mel_oc_mark($key, $c, $in);
    if ($control === 'card_mark_movie')
    {
        $w = mel_catv_in($in, 'w');
        return $w === 'on' || $w === 'off' ? mel_oc_mark_movie($key, $c, $w === 'on') : null;
    }
    if ($control === 'card_trailer')
        return mel_ct_play($c);
    if ($control === 'card_finish')
    {
        mel_oc_ovl_drop($key);
        return mel_oc_finish($key, $c);
    }
    if ($control === 'card_src_menu')
        return mel_src_popup($c['ui'], 'card_src', mel_oc_src_params($in));
    $l = isset($c['nav']) ? $c['nav'] : mel_oc_layout($c);
    if ($control === 'card_src')
        return mel_oc_src($c, $l, $in);
    if ($control === 'card_lib_copies' || $control === 'card_lib_copy')
        return mel_oc_lib_input($key, $c, $l, $in, $control);
    $raw = isset($in->parent_sel_state) ? $in->parent_sel_state : null;
    $pos = mel_oc_pos($c, $l, $raw);
    MelOc::$cards[$key]['pos'] = $pos;
    if ($control === 'card_load')
        return mel_oc_tick($key, $l);
    if ($control === 'card_menu')
        return mel_oc_menu($c['ui'], array_merge(mel_oc_src_items($key, $c, $l, $pos), mel_oc_lib_menu_items($key, $c, $l, $pos),
            $l['kind'] === 'movie' ?
            mel_oc_movie_mark_items($key, $c) : mel_oc_mark_items($key, $c, $l['prog'], $pos), mel_oc_dune_items($c)));
    $j = is_string($raw) ? json_decode($raw, true) : null;
    $rs = implode(',', $l['rows']);
    if (is_array($j) && isset($j['rs']) && is_string($j['rs']) && $j['rs'] !== $rs)
    {
        mel_log("card: own $key: $control on a screen of rows \"{$j['rs']}\", now \"$rs\": the screen anew");
        return mel_replace_act("card:$key", $c['tile']['name']);
    }
    $new = $pos;
    if ($control === 'card_enter')
    {
        $b = $pos['b'];
        $ep = $l['ep'];
        if ($pos['z'] === 'pill' && !$c['meta'] && $l['kind'] === 'series')
            return mel_oc_retry($key, $c);
        $id = mel_oc_strip_on($l, $pos);
        if ($id === 'c')
            return mel_oc_person($key, $c, mel_oc_strip_k($pos, 'c'));
        if ($id !== '')
            return mel_oc_tile_enter($key, $c, $id, mel_oc_strip_k($pos, $id));
        if ($pos['z'] === 'row')
        {
            $x = mel_oc_ep($c, $pos['s'], $pos['e']);
            mel_log("card: own $key: ENTER on S{$pos['s']}E{$pos['e']}" . ($x && $x['soon'] ? ', not out yet' : ''));
            // With a copy on the disk (one not out yet too: its file is there) - the file (C2).
            if ($x && mel_oc_lib_shown($l, $x['s'], $x['e']))
                return mel_oc_lib_start_ep($key, $c, $l, $x['s'], $x['e']);
            return $x ? mel_oc_ep_watch($c, $x, $ep) : null;
        }
        if ($pos['z'] !== 'btn' || !isset($l['btns'][$b]))
            return null;
        $kind = $l['btns'][$b][0];
        mel_log("card: own $key: ENTER on $kind");
        if ($kind === 'list')
            return mel_oc_list($key, $c, $l, $pos);
        if ($kind === 'trailer')
            return mel_ct_enter($key);
        if ($kind === 'watch' && !$ep && mel_oc_lib_shown($l))
            return mel_oc_lib_start($key, $c, $l);
        // The episode offered with a copy on the disk (its season of the card $ep['s']): the file (C2).
        if ($kind === 'watch' && $ep && mel_oc_lib_shown($l, $ep['s'], $ep['e']))
            return mel_oc_lib_start_ep($key, $c, $l, $ep['s'], $ep['e']);
        if ($kind === 'watch')
            return $ep ? mel_oc_watch($c, $ep['q'], $ep['e'], $ep) : mel_oc_watch($c);
        // Not out yet: the focus to it in the row.
        if ($kind === 'soon')
            $new = array_merge($pos, array('z' => 'row', 'p' => 0, 's' => $ep['s'], 'e' => $ep['e']));
        else
            return null;
    }
    else
        $new = mel_oc_next($c, $l, $pos, mel_catv_in($in, 'd'));
    if ($new === $pos)
        return null;
    MelOc::$cards[$key]['pos'] = $new;
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => mel_oc_changes($c, $l, $pos, $new),
            ChangeGCompsActionData::num_steps => $new['z'] === $pos['z'] && ($new['z'] === 'row' || $new['z'] === 'pill' ||
                $new['r'] !== $pos['r']) ? MEL_OC_ROW_STEPS : MEL_GC_STEPS,
            ChangeGCompsActionData::sel_state => mel_oc_state($new, $l)));
}
