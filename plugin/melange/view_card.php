<?php
// The own card of a movie as a view_gcomps screen "card:<type>:<id>", 1920x1080: the panel of the
// catalog grown into a card (R1 of design-own-card.md). The card background of the server
// (aio_catv_card) with the logo, or the dark one and the title as text; the lines of the panel of
// the catalog at its places (facts, genres and countries, the director, the description), the
// buttons in a row ("Watch", "Dune card") over a band of our own, under them the progress of the
// movie or why there is none; at the bottom the cast of the meta (card_meta.php) and at its right
// the hint "About the movie". DOWN scrolls the page under the logo: the cast up, "About the movie"
// (the tagline, the whole description, the details) under it, the whole screen darkened, the logo
// too; DOWN again further while there is more, UP back, at last to the button left. A series (H1):
// the buttons higher ("Continue SxEy" by the history), under them the tabs of the seasons and the
// row of the episodes of the season shown, their progress by the history; the cast goes DOWN with
// "About the series". The plugin draws and moves the focus itself with change_gcomps, as the list;
// the place (aio_oc_pos: the zone, the button, the stop, the season and the episode) travels in
// sel_state.
// ENTER on a tile does the network (aio_oc_enter); the screen is built from memory.
// Needs main.php, catalog.php, catalog_card.php, card_meta.php, history.php, playback.php, view_catalog.php,
// view_gcomps.php, gcomps.php; the seasons and the episodes of a series: view_card_series.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// Cards kept in memory by "<type>:<id>", AIO_CM_KEEP_EPS episodes in all at most; the oldest
// opened goes.
define('AIO_OC_KEEP', 8);
// The page: a clipping panel from the bottom of the logo (PANE_Y + TITLE_BOTTOM of the catalog,
// baked into card/v1) down to SHOW_BOTTOM, higher at a stop where it would cut a line; the page in
// it in the coordinates of the first screen.
// DOWN: the first stop OFF lower (the cast at DOWN_Y of the screen, "About" under it; without the
// cast further, "About" at DOWN_Y), the next ones STEP further at most, MIN_STEP at least, while
// the page goes on below the clip. "About" lies at OFF + ABOUT_Y of the page, under the clip of
// the first screen. The ink of a label starts INK under its top at least (30 and 24 px, measured
// on the device): an edge of the clip there cuts no line.
define('AIO_OC_VIEW_Y', 256);
define('AIO_OC_DOWN_Y', 290);
define('AIO_OC_STEP', 420);
define('AIO_OC_MIN_STEP', 40);
define('AIO_OC_SHOW_BOTTOM', 1020);
define('AIO_OC_INK', 10);
// The first screen (design-own-card.md, B): lines of the panel, the description in 30 px lines
// DESC_LH apart from under the director (or the genres) down to DESC_BOTTOM; buttons BTN_H high
// at BTN_Y, the text PAD from the edges (and the icon of "Watch" ICON_W more), GAP apart; CAP: the
// rounded ends (PNG), a rect between them; the progress or a line under them at LINE_Y; the bar
// BAR_H high BAR_DY under its top; the hint at the line of the cast, its right edge at HINT_R.
define('AIO_OC_FACTS_Y', 269);
define('AIO_OC_GENRES_Y', 315);
define('AIO_OC_DIRECTOR_Y', 361);
define('AIO_OC_DESC_LH', 42);
define('AIO_OC_DESC_BOTTOM', 560);
define('AIO_OC_BTN_Y', 584);
define('AIO_OC_BTN_H', 64);
define('AIO_OC_BTN_PAD', 32);
define('AIO_OC_ICON_W', 34);
define('AIO_OC_BTN_GAP', 20);
define('AIO_OC_CAP', 12);
define('AIO_OC_LINE_Y', 660);
define('AIO_OC_BAR_H', 6);
define('AIO_OC_BAR_DY', 9);
define('AIO_OC_HINT_R', 1845);
// The cast at CAST_Y of the first screen, CAST_MAX at most, photos 120x152 (TMDB
// w276_and_h350_face, a half) STEP apart, NAME_DY under their top the name in 2 lines and the role
// in 2, NAME_W wide: the bottom of its text 312 under CAST_Y, SHOW_BOTTOM at the first screen of a
// movie (design-own-card.md, "Итог 13.11.2026": R1 with the role in 2 lines, to be seen on the
// device; back: CAST_Y 724, LINE_Y 664, STEP 172, NAME_W 160, NAME_DY 158, CAST_MAX 10). DOWN:
// "About" at ABOUT_Y of the screen at the first stop with the cast, the description in DESC2_W,
// the pairs of the details at DET_X (the 8th column of the cast) in DET_W, to the right edge.
define('AIO_OC_CAST_Y', 708);
define('AIO_OC_OFF', AIO_OC_CAST_Y - AIO_OC_DOWN_Y);
define('AIO_OC_CAST_MAX', 9);
define('AIO_OC_PHOTO_W', 120);
define('AIO_OC_PHOTO_H', 152);
define('AIO_OC_PHOTO_STEP', 184);
define('AIO_OC_NAME_W', 168);
define('AIO_OC_NAME_DY', 154);
define('AIO_OC_ABOUT_Y', 616);
define('AIO_OC_DESC2_W', 960);
define('AIO_OC_DESC2_LINES', 100);
define('AIO_OC_DET_X', AIO_CATV_CARD_X + 7 * AIO_OC_PHOTO_STEP);
define('AIO_OC_DET_W', AIO_OC_HINT_R - AIO_OC_DET_X);
// A series (H1 of design-own-card.md): the buttons at BTN_Y_SER, the description down to
// DESC_BOTTOM_SER; the tabs of the seasons at TAB_Y, TAB_H high, the text TAB_PAD from their
// edges, TAB_GAP apart, the rounded ends TAB_CAP (PNG); more than TABS_MAX seasons: numbers after
// "Сезон". The count "Сезон 2 · 13 серий" in CNT_PX at the right edge of the line, the strip
// CNT_GAP before the widest; not all fitting: the strip scrolls by whole tabs, the chevrons
// CHEV_W x CHEV_H (PNG) CHEV_GAP off its ends, in slots CHEV_SLOT wide (T2 of design-own-card.md,
// "Итог 13.11.2026").
// The row of episodes at ROW_Y: stills EP_W x EP_H, EP_STEP apart from the x of the catalog,
// EP_VIS whole, the one before and after seen at the edges; the frame of the focus FRAME wide
// around the still; P+/P- PAGE stills.
define('AIO_OC_BTN_Y_SER', 512);
define('AIO_OC_DESC_BOTTOM_SER', 504);
define('AIO_OC_TAB_Y', 604);
define('AIO_OC_TAB_H', 52);
define('AIO_OC_TAB_PAD', 24);
define('AIO_OC_TAB_GAP', 8);
define('AIO_OC_TAB_CAP', 26);
define('AIO_OC_TABS_MAX', 8);
define('AIO_OC_CNT_GAP', 40);
define('AIO_OC_CNT_PX', 24);
define('AIO_OC_CHEV_W', 12);
define('AIO_OC_CHEV_H', 22);
define('AIO_OC_CHEV_GAP', 12);
// 4 more than the chevron and its gap: the left one 4 in from the x of the catalog, the right one
// 4 more before CNT_GAP.
define('AIO_OC_CHEV_SLOT', AIO_OC_CHEV_W + AIO_OC_CHEV_GAP + 4);
define('AIO_OC_ROW_Y', 680);
define('AIO_OC_EP_W', 384);
define('AIO_OC_EP_H', 216);
define('AIO_OC_EP_STEP', 408);
define('AIO_OC_EP_VIS', 4);
define('AIO_OC_ROW_H', 310);
define('AIO_OC_FRAME', 6);
define('AIO_OC_PAGE', 4);
// A season of more than WIN_MIN episodes has in the panel only the stills on screen and WIN more
// each side, added and dropped one by one as the row moves (as GCompList of the vendor). The probe
// (experiments/hrow_probe, 12.11.2026): a season of 13-16 stills shows its stubs 0.1 s after the
// key, one of 120 in 0.26 s (PHP 38 ms, 170 KB), its stills 0.56-0.9 s; so ~1.5 ms of the shell a
// still: 40 keep the change of a season near 0.15 s and the stills in 0.5 s, a window of 21 the
// same however long the season (One Piece: up to 197).
define('AIO_OC_WIN_MIN', 40);
define('AIO_OC_WIN', 8);
// change_gcomps steps of the row and the tabs (the probe: 5 ~320 ms; repeats of a held key come
// each 0.15-0.2 s and break it: to be tuned on the device).
define('AIO_OC_ROW_STEPS', 5);
// DOWN of a series: the first stop OFF_SER lower (the row above the clip, the cast at DOWN_Y); the
// hint under the row at HINT_Y_SER from the x of the catalog.
define('AIO_OC_OFF_SER', 740);
define('AIO_OC_HINT_Y_SER', 1030);
// Colours of the design: buttons, the bar, no photo, the darkening of DOWN, the frame of the focus,
// the tab of the season shown (white 16 %), the plate of the mark "watched".
define('AIO_OC_BTN_OFF', '#24FFFFFF');
define('AIO_OC_BAR_REST', '#FF404040');
define('AIO_OC_NO_PHOTO', '#FF262626');
define('AIO_OC_DIM', '#C0000000');
define('AIO_OC_BAND', '#D0000000');
define('AIO_OC_FOCUS', '#FF2850C0');
define('AIO_OC_TAB_ON', '#29FFFFFF');
define('AIO_OC_MARK_BG', '#B0000000');
// Our pictures: the ends of a button, off and focused, the icon of "Watch", the band over the
// background from y 470 (1920x160, then a rect), the ends of a tab of a season (shown, focused),
// the chevrons of the strip of tabs (grey, white with the focus on the tabs).
// card<N>: N goes up when they change, as cat<N>.
define('AIO_OC_IMG', 'plugin_file://img/card1_');
// Pictures of the shell: a still loading or not there (an episode not out yet), the mark "watched".
define('AIO_OC_ICONS', 'plugin_file://%shell_ext%/icons/');

class AioOc
{
    // Cards by "<type>:<id>": tile, meta (or null), imdb, dune (the id of the card of the Dune or
    // of an old local one, '' for none), lang (of play_action), ui (of the texts), pos (the place,
    // aio_oc_pos), n (episodes of the meta); nav (what the keys need of aio_oc_layout) once shown.
    // Memory of php_server only: after a restart the card is made from the meta anew.
    /** @var array<string, AioOcCard> */
    public static $cards = array();
}

// ENTER on a tile of a movie or a series with IMDb or TMDB (aio_card_enter): the id of the card of the Dune
// (aio_card_id; none: an old local card m-melange-..., none is written now), the meta (aio_cm_get;
// a failure: the card of the tile), the IMDb of a tmdb: tile from it -> the card screen.
/**
 * @param AioCatTile $tile
 * @param string $lang
 * @return AioAction
 */
function aio_oc_enter($tile, $lang)
{
    $c = aio_card_id($tile);
    $g = aio_cm_get($tile);
    $key = $tile['type'] . ':' . aio_cm_id($tile);
    $card = aio_oc_make($tile, $g['meta'], $c['id'], $lang);
    aio_oc_keep($key, $card);
    aio_log("card: own $key: card " . ($card['dune'] !== '' ? $card['dune'] : 'none') . ', meta ' .
        ($g['err'] !== '' ? "failed ({$g['err']})" : 'ok') . ', imdb ' . ($card['imdb'] !== '' ? $card['imdb'] : 'none'));
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "card:$key",
            PluginOpenFolderActionData::caption => $tile['name']));
}

// A card of $tile: the IMDb of the tile or of the meta; no card of the Dune ($dune ''): the old
// local one if there is one.
/**
 * @param AioCatTile $tile
 * @param AioCardMeta|null $meta
 * @param string $dune
 * @param string $lang
 * @return AioOcCard
 */
function aio_oc_make($tile, $meta, $dune, $lang)
{
    $imdb = $tile['imdb_id'] !== '' ? $tile['imdb_id'] : ($meta ? $meta['imdb'] : '');
    $tile['imdb_id'] = $imdb;
    if ($dune === '')
        $dune = aio_card_local_find($tile);
    return array('tile' => $tile, 'meta' => $meta, 'imdb' => $imdb, 'dune' => $dune, 'lang' => $lang,
        'ui' => aio_catv_ui_lang(), 'pos' => array('z' => 'btn', 'b' => 0, 'p' => 0, 's' => 0, 'e' => 0),
        'n' => $meta ? aio_cm_eps_count($meta) : 0);
}

/**
 * @param string $key
 * @param AioOcCard $card
 * @return void
 */
function aio_oc_keep($key, $card)
{
    unset(AioOc::$cards[$key]);
    AioOc::$cards[$key] = $card;
    $sum = 0;
    foreach (AioOc::$cards as $c)
        $sum += $c['n'];
    // The card just kept stays, however many episodes it has.
    while (count(AioOc::$cards) > 1 && (count(AioOc::$cards) > AIO_OC_KEEP || $sum > AIO_CM_KEEP_EPS))
    {
        $c = array_shift(AioOc::$cards);
        $sum -= $c['n'];
    }
}

// The card of "<type>:<id>" from memory; none there (php_server restarted): made from its meta
// anew (its tile, no rating of Kinopoisk), kept. null: a bad key, no meta.
/**
 * @param string $key
 * @return AioOcCard|null
 */
function aio_oc_get($key)
{
    if (isset(AioOc::$cards[$key]))
        return AioOc::$cards[$key];
    if (!preg_match('/^(movie|series):(tt[0-9]{1,10}|tmdb:([0-9]{1,10}))$/D', $key, $m))
        return null;
    $t = aio_cat_tile(array('id' => $m[2], 'name' => 'x', 'type' => $m[1]), $m[1]);
    if (!$t)
        return null;
    $g = aio_cm_get($t);
    if (!$g['meta'] || !$g['meta']['tile'])
    {
        aio_log("card: own $key not in memory, no meta: outdated");
        return null;
    }
    $tile = $g['meta']['tile'];
    $tile['type'] = $m[1];
    $c = aio_card_id($tile);
    $card = aio_oc_make($tile, $g['meta'], $c['id'], aio_catv_lang());
    aio_oc_keep($key, $card);
    aio_log("card: own $key made anew from its meta");
    return $card;
}

// --- Texts.

// A country of the meta in the language of the screen; one not known in Russian as it is.
/**
 * @param string $c
 * @param string $lang
 * @return string
 */
function aio_oc_country($c, $lang)
{
    $n = aio_catv_country($c, $lang);
    return $n !== '' ? $n : $c;
}

// The countries of the card: of the meta, else the one of the tile.
/**
 * @param AioOcCard $c
 * @return list<string>
 */
function aio_oc_countries($c)
{
    $list = $c['meta'] ? $c['meta']['countries'] : array();
    if (!$list && $c['tile']['country'] !== '')
        $list = array($c['tile']['country']);
    $out = array();
    foreach ($list as $x)
        $out[] = aio_clean(aio_oc_country($x, $c['ui']));
    return $out;
}

// The facts of the panel of the catalog with the kind first: "Фильм · 2001 · 2 ч 32 мин". A series
// has it ("Сериал") in the parts already; its seasons of the meta with an episode out instead of
// the length of an episode: "Сериал · 2008 · 5 сезонов".
/**
 * @param AioOcCard $c
 * @return list<array{string, string, string}>
 */
function aio_oc_facts($c)
{
    $p = aio_catv_fact_parts($c['tile'], $c['ui']);
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
        array_unshift($p['rest'], aio_tr($c['ui'], 'card_kind_movie'));
    else if ($n > 0)
    {
        $len = aio_catv_runtime($c['tile']['runtime'], $c['ui']);
        $p['rest'] = array_values(array_diff($p['rest'], array($len)));
        $p['rest'][] = $n . ' ' . aio_gc_plural($c['ui'], $n, 'card_seasons');
    }
    return aio_catv_facts_of($p);
}

// "Y-m-d" -> "16 ноября 2001" ($year false: "16 ноября"; $short: "16 ноя 2001"); '' for none.
/**
 * @param string $d
 * @param string $lang
 * @param bool $short
 * @param bool $year
 * @return string
 */
function aio_oc_day($d, $lang, $short = false, $year = true)
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $d, $x))
        return '';
    $months = explode(',', aio_tr($lang, $short ? 'card_months_short' : 'card_months'));
    $mon = isset($months[intval($x[2]) - 1]) ? $months[intval($x[2]) - 1] : $x[2];
    return intval($x[3]) . " $mon" . ($year ? " $x[1]" : '');
}

// "Y-m-d" -> "16 ноября 2001"; a day after today (UTC): "Выходит 14.11.2026"; '' for none.
/**
 * @param string $d
 * @param string $lang
 * @return string
 */
function aio_oc_date($d, $lang)
{
    if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $d, $x) && $d > gmdate('Y-m-d'))
        return str_replace('{d}', "$x[3].$x[2].$x[1]", aio_tr($lang, 'card_coming'));
    return aio_oc_day($d, $lang);
}

// "Просмотрено 53 % · осталось 1 ч 12 мин" of a movie at $pos of $len s; '' when not begun or watched
// (aio_wh_full).
/**
 * @param float $pos
 * @param float $len
 * @param string $lang
 * @return string
 */
function aio_oc_progress_text($pos, $len, $lang)
{
    if ($pos <= 0 || aio_wh_full($pos, $len))
        return '';
    $left = aio_catv_runtime(intval(ceil(max(0, $len - $pos) / 60)) . 'min', $lang);
    return str_replace(array('{p}', '{t}'), array(strval(intval(round($pos * 100 / $len))), $left),
        aio_tr($lang, 'card_progress'));
}

// --- Layout.

// The buttons: "Watch" with an IMDb, "Dune card" with a card of the Dune; array(kind, text, icon).
// A series: "Continue SxEy" (a record in the history) or "Watch SxEy" of the episode to offer $ep;
// one not out yet - "SxEy · выйдет 16.11" (kind soon: ENTER to it in the row).
/**
 * @param AioOcCard $c
 * @param AioOcEp|null $ep
 * @return list<array{string, string, bool}>
 */
function aio_oc_buttons($c, $ep)
{
    $b = array();
    $ui = $c['ui'];
    if ($c['imdb'] !== '' && !$ep)
        $b[] = array('watch', aio_tr($ui, 'card_watch'), true);
    else if ($c['imdb'] !== '')
    {
        $name = 'S' . ($ep['s'] > 0 ? $ep['s'] : $ep['q']) . 'E' . $ep['e'];
        $x = aio_oc_ep($c, $ep['s'], $ep['e']);
        if ($x && $x['soon'])
        {
            $d = $x['date'] !== '' ? substr($x['date'], 8, 2) . '.' . substr($x['date'], 5, 2) : '';
            $b[] = array('soon', str_replace('{d}', $d, aio_tr($ui, $d !== '' ? 'card_btn_soon' : 'card_btn_not_out', $name)), false);
        }
        else
            $b[] = array('watch', aio_tr($ui, in_array($ep['kind'], array('record', 'next', 'same'), true) ? 'card_continue' : 'card_watch_ep',
                $name), true);
    }
    if ($c['dune'] !== '')
        $b[] = array('dune', aio_tr($ui, 'card_dune'), false);
    return $b;
}

/**
 * @param array{string, string, bool} $b
 * @return int
 */
function aio_oc_btn_w($b)
{
    return aio_gc_w($b[1], AIO_CATV_LINE_PX) + 2 * AIO_OC_BTN_PAD + ($b[2] ? AIO_OC_ICON_W : 0);
}

// The children of button $b of width $w: the ends of PNG, a rect between them, the icon and the text.
/**
 * @param array{string, string, bool} $b
 * @param int $w
 * @param bool $on
 * @return list<AioGcComp>
 */
function aio_oc_btn_items($b, $w, $on)
{
    $s = $on ? 'on_' : '';
    $d = array(
        aio_gc_image(aio_gc_at(AIO_OC_CAP, AIO_OC_BTN_H, 0, 0), AIO_OC_IMG . "pill_{$s}l.png"),
        aio_gc_rect(aio_gc_at($w - 2 * AIO_OC_CAP, AIO_OC_BTN_H, AIO_OC_CAP, 0), $on ? AIO_GC_SEL : AIO_OC_BTN_OFF),
        aio_gc_image(aio_gc_at(AIO_OC_CAP, AIO_OC_BTN_H, $w - AIO_OC_CAP, 0), AIO_OC_IMG . "pill_{$s}r.png"));
    $x = AIO_OC_BTN_PAD;
    if ($b[2])
    {
        $d[] = aio_gc_image(aio_gc_left_center(20, 24, $x), AIO_OC_IMG . "play_{$s}icon.png");
        $x += AIO_OC_ICON_W;
    }
    $d[] = aio_gc_cut(null, aio_gc_left_center($w - $x - AIO_OC_BTN_PAD, 44, $x), $b[1], AIO_CATV_LINE_PX,
        $on ? AIO_GC_TEXT : AIO_GC_TEXT2, array(GCompTtfLabelDef::halign => HALIGN_CENTER));
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
 * @return array<string, mixed>
 */
function aio_oc_label($x, $y, $w, $text, $px, $color, $spec = array())
{
    $l = aio_catv_info_label(aio_clean($text), $px, $color, $w, $px === AIO_CATV_LINE_PX ? AIO_CATV_LINE_H : 34, $y, $spec);
    $l[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::x] = $x;
    return $l;
}

// The first screen in the page: the lines of the panel (x of the catalog), the description to
// DESC_BOTTOM (a series: DESC_BOTTOM_SER), the buttons at the y of the layout (lit: $b).
/**
 * @param AioOcCard $c
 * @param AioOcLayout $l
 * @param int $b
 * @return list<array<string, mixed>>
 */
function aio_oc_head($c, $l, $b)
{
    $x0 = AIO_CATV_CARD_X;
    // The line of facts in a panel at the x of the catalog: its labels are at x of the panel.
    $d = array(aio_gc_panel('facts', aio_gc_at(AIO_CATV_PANE_W, AIO_OC_GENRES_Y, $x0, 0),
        aio_catv_facts_labels(aio_oc_facts($c), AIO_OC_FACTS_Y)));
    $y = AIO_OC_GENRES_Y;
    $genres = aio_catv_genre_line($c['tile'], $c['ui'], aio_oc_countries($c));
    if ($genres !== '')
    {
        $fit = aio_catv_fit($genres, AIO_CATV_LINE_PX, AIO_CATV_DATA_MIN_PX, AIO_CATV_TEXT_W);
        $d[] = aio_oc_label($x0, $y, AIO_CATV_TEXT_W, $fit[1], $fit[0], AIO_CATV_TEXT_COLOR);
        $y += AIO_CATV_LINE_H;
    }
    $dirs = $c['meta'] ? aio_cm_names_of($c['meta']['directors']) : array();
    if ($dirs)
    {
        $label = aio_tr($c['ui'], 'card_director');
        $lw = aio_gc_w($label, AIO_CATV_LINE_PX);
        $d[] = aio_oc_label($x0, $y, $lw, $label, AIO_CATV_LINE_PX, AIO_GC_DIM);
        $gap = aio_gc_w(' ', AIO_CATV_LINE_PX);
        $d[] = aio_oc_label($x0 + $lw + $gap, $y, AIO_CATV_TEXT_W - $lw - $gap,
            aio_gc_cut_w(aio_clean(implode(', ', $dirs)), AIO_CATV_LINE_PX, AIO_CATV_TEXT_W - $lw - $gap),
            AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
        $y += AIO_CATV_LINE_H;
    }
    $y += 14;
    $n = max(0, intval((($l['kind'] === 'series' ? AIO_OC_DESC_BOTTOM_SER : AIO_OC_DESC_BOTTOM) - $y) / AIO_OC_DESC_LH));
    $desc = $c['meta'] && $c['meta']['description'] !== '' ? $c['meta']['description'] : $c['tile']['description'];
    foreach (aio_gc_wrap(aio_clean($desc), AIO_CATV_LINE_PX, AIO_CATV_TEXT_W, $n, true) as $i => $line)
        $d[] = aio_oc_label($x0, $y + $i * AIO_OC_DESC_LH, AIO_CATV_TEXT_W, $line, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);

    $x = $x0;
    foreach ($l['btns'] as $i => $bt)
    {
        $w = aio_oc_btn_w($bt);
        $d[] = aio_gc_panel("b$i", aio_gc_at($w, AIO_OC_BTN_H, $x, $l['btn_y']), aio_oc_btn_items($bt, $w, $i === $b));
        $x += $w + AIO_OC_BTN_GAP;
    }
    return $d;
}

// Under the buttons ($w0: the width of the first): no IMDb - nothing plays (the meta failed: it
// did not load, else it has none); no card of the Dune - no history; else the progress $wh of the
// movie under "Watch", if begun, or "Watched" grey without the bar.
/**
 * @param AioOcCard $c
 * @param array{float, float}|null $wh
 * @param int $w0
 * @return list<array<string, mixed>>
 */
function aio_oc_under($c, $wh, $w0)
{
    $x0 = AIO_CATV_CARD_X;
    $d = array();
    $line = '';
    $color = AIO_CATV_TEXT_COLOR;
    if ($c['imdb'] === '')
        $line = aio_tr($c['ui'], $c['meta'] ? 'err_no_imdb' : 'cat_row_failed');
    else if ($c['dune'] === '')
        $line = aio_tr($c['ui'], 'card_no_history');
    else
    {
        $p = $wh ? aio_oc_progress_text($wh[0], $wh[1], $c['ui']) : '';
        if ($wh && aio_wh_full($wh[0], $wh[1]))
        {
            $line = aio_tr($c['ui'], 'card_watched');
            $color = AIO_GC_DIM;
        }
        else if ($p !== '')
        {
            $by = AIO_OC_LINE_Y + AIO_OC_BAR_DY;
            $fw = max(AIO_GC_BAR_MIN, intval(round($w0 * min(1, $wh[0] / $wh[1]))));
            $d[] = aio_gc_rect(aio_gc_at($w0, AIO_OC_BAR_H, $x0, $by), AIO_OC_BAR_REST);
            $d[] = aio_gc_rect(aio_gc_at($fw, AIO_OC_BAR_H, $x0, $by), AIO_GC_TEXT);
            $d[] = aio_oc_label($x0 + $w0 + AIO_OC_BTN_GAP, AIO_OC_LINE_Y, 1920 - $x0 - $w0 - AIO_OC_BTN_GAP - 75, $p, 24,
                AIO_CATV_TEXT_COLOR);
        }
    }
    if ($line !== '')
        $d[] = aio_oc_label($x0, AIO_OC_LINE_Y, AIO_CATV_TEXT_W, $line, 24, $color);
    return $d;
}

// A series without a card of the Dune or an IMDb: the line why, right of the buttons ($x: their
// end), as high as their middle.
/**
 * @param AioOcCard $c
 * @param int $x
 * @return list<array<string, mixed>>
 */
function aio_oc_under_ser($c, $x)
{
    if ($c['imdb'] !== '' && $c['dune'] !== '')
        return array();
    $line = aio_tr($c['ui'], $c['imdb'] === '' ? ($c['meta'] ? 'err_no_imdb' : 'cat_row_failed') : 'card_no_history');
    return array(aio_oc_label($x, AIO_OC_BTN_Y_SER + (AIO_OC_BTN_H - 34) / 2, AIO_OC_HINT_R - $x, $line, 24, AIO_CATV_TEXT_COLOR));
}

// The hint of what DOWN shows: $text and the arrow down at ($x, $y) of the screen.
/**
 * @param string $text
 * @param int $x
 * @param int $y
 * @return list<array<string, mixed>>
 */
function aio_oc_hint($text, $x, $y)
{
    $hw = aio_gc_w($text, AIO_CATV_LINE_PX);
    return array(aio_oc_label($x, $y, $hw, $text, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR),
        aio_gc_image(aio_gc_at(AIO_GC_SORT_ARROW_W, AIO_GC_SORT_ARROW_H, $x + $hw + 16, $y + 16),
            'plugin_file://img/sort_down.png'));
}

// The cast of the meta at $y, CAST_MAX at most: photos, names in 2 lines, the English roles grey
// in 2 (of a list "A / B" its first not empty); no photo: the initials. None: nothing.
/**
 * @param AioOcCard $c
 * @param int $y
 * @return list<array<string, mixed>>
 */
function aio_oc_cast($c, $y)
{
    $cast = $c['meta'] ? $c['meta']['cast'] : array();
    $d = array();
    if (!$cast)
        return $d;
    $x0 = AIO_CATV_CARD_X;
    $d[] = aio_oc_label($x0, $y, 800, aio_tr($c['ui'], 'card_cast'), AIO_CATV_LINE_PX, AIO_GC_TEXT);
    $py = $y + 46;
    foreach (array_slice($cast, 0, AIO_OC_CAST_MAX) as $i => $p)
    {
        $x = $x0 + $i * AIO_OC_PHOTO_STEP;
        $d[] = aio_gc_rect(aio_gc_at(AIO_OC_PHOTO_W, AIO_OC_PHOTO_H, $x, $py), AIO_OC_NO_PHOTO);
        if ($p['photo'] !== '')
            $d[] = aio_gc_image(aio_gc_at(AIO_OC_PHOTO_W, AIO_OC_PHOTO_H, $x, $py), $p['photo']);
        else
        {
            $ini = '';
            foreach (array_slice(explode(' ', $p['name']), 0, 2) as $w)
                $ini .= mb_substr($w, 0, 1, 'UTF-8');
            $d[] = aio_oc_label($x, $py + 40, AIO_OC_PHOTO_W, $ini, 48, AIO_GC_DIM,
                array(GCompTtfLabelDef::halign => HALIGN_CENTER));
        }
        $parts = explode(' ', $p['name'], 2);
        $role = '';
        foreach (preg_split('~(?:^|\s)/(?:\s|$)~', $p['role']) as $r)
        {
            if (($role = aio_clean($r)) !== '')
                break;
        }
        $lines = array(aio_gc_cut_w(aio_clean($parts[0]), 24, AIO_OC_NAME_W),
            isset($parts[1]) ? aio_gc_cut_w(aio_clean($parts[1]), 24, AIO_OC_NAME_W) : '');
        $lines = array_merge($lines, aio_gc_wrap($role, 24, AIO_OC_NAME_W, 2, true));
        foreach ($lines as $k => $s)
        {
            if ($s !== '')
                $d[] = aio_oc_label($x, $py + AIO_OC_NAME_DY + 26 * $k, AIO_OC_NAME_W, $s, 24, $k < 2 ? AIO_GC_TEXT : AIO_GC_DIM);
        }
    }
    return $d;
}

// What DOWN shows, in the coordinates of the screen at the first stop with the cast: "About the
// movie" (the series): the tagline, the whole description, at the right the details (a series:
// the first episode, the status, the networks). -> array(components, the bands of their ink,
// aio_oc_band); none: no stop.
/**
 * @param AioOcCard $c
 * @return array{list<array<string, mixed>>, list<array{int, int}>}
 */
function aio_oc_down($c)
{
    $m = $c['meta'];
    $lang = $c['ui'];
    $x0 = AIO_CATV_CARD_X;
    $d = array();
    $y = AIO_OC_ABOUT_Y;
    $desc = $m && $m['description'] !== '' ? $m['description'] : $c['tile']['description'];
    $rows = array();
    $name = $c['tile']['name'];
    $series = $c['tile']['type'] === 'series';
    if ($m && $m['original'] !== '' && $m['original'] !== $name)
        $rows[] = array('card_original', $m['original']);
    if ($m)
        $rows[] = array($series ? 'card_first_aired' : 'card_released', aio_oc_date($m['released'], $lang));
    if ($m && $series)
        $rows[] = array('card_status', $m['status'] !== '' ? aio_tr($lang, "card_status_{$m['status']}") : '');
    if ($m)
        $rows[] = array('card_director', implode(', ', aio_cm_names_of($m['directors'])));
    if ($m)
        $rows[] = array('card_writers', implode(', ', aio_cm_names_of($m['writers'])));
    if ($m && $series && $m['networks'])
        $rows[] = array('card_networks', implode(', ', $m['networks']));
    else if ($m)
        $rows[] = array('card_studios', implode(', ', $m['studios']));
    $rows[] = array('card_countries', implode(', ', aio_oc_countries($c)));
    $tagline = $m ? $m['tagline'] : '';
    $any = $desc !== '' || $tagline !== '';
    foreach ($rows as $r)
        $any = $any || $r[1] !== '';
    if (!$any)
        return array(array(), array());
    $d[] = aio_oc_label($x0, $y, 800, aio_tr($lang, $series ? 'card_about_series' : 'card_about_movie'), AIO_CATV_LINE_PX,
        AIO_GC_TEXT);
    $bands = array(aio_oc_band($y, AIO_CATV_LINE_PX));
    $ty = $y + 50;
    foreach (aio_gc_wrap(aio_clean($tagline), AIO_CATV_LINE_PX, AIO_OC_DESC2_W, 2, true) as $line)
    {
        $d[] = aio_oc_label($x0, $ty, AIO_OC_DESC2_W, $line, AIO_CATV_LINE_PX, AIO_GC_DIM);
        $bands[] = aio_oc_band($ty, AIO_CATV_LINE_PX);
        $ty += AIO_OC_DESC_LH;
    }
    if ($tagline !== '')
        $ty += 8;
    foreach (aio_gc_wrap(aio_clean($desc), AIO_CATV_LINE_PX, AIO_OC_DESC2_W, AIO_OC_DESC2_LINES, true) as $line)
    {
        $d[] = aio_oc_label($x0, $ty, AIO_OC_DESC2_W, $line, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
        $bands[] = aio_oc_band($ty, AIO_CATV_LINE_PX);
        $ty += AIO_OC_DESC_LH;
    }
    $ry = $y + 50;
    foreach ($rows as $r)
    {
        if ($r[1] === '')
            continue;
        $d[] = aio_oc_label(AIO_OC_DET_X, $ry, AIO_OC_DET_W, aio_tr($lang, $r[0]), 24, AIO_GC_DIM);
        $bands[] = aio_oc_band($ry, 24);
        $ry += 30;
        foreach (aio_gc_wrap(aio_clean($r[1]), AIO_CATV_LINE_PX, AIO_OC_DET_W, 2, true) as $line)
        {
            $d[] = aio_oc_label(AIO_OC_DET_X, $ry, AIO_OC_DET_W, $line, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
            $bands[] = aio_oc_band($ry, AIO_CATV_LINE_PX);
            $ry += 40;
        }
        $ry += 18;
    }
    return array($d, $bands);
}

// The band of the ink of a label of aio_oc_label at $y: from INK under its top to its bottom.
/**
 * @param int $y
 * @param int $px
 * @return array{int, int}
 */
function aio_oc_band($y, $px)
{
    return array($y + AIO_OC_INK, $y + ($px === AIO_CATV_LINE_PX ? AIO_CATV_LINE_H : 34));
}

// $y off the bands: up to the top of the one it cuts ($up) or down to its bottom, again while it
// cuts one (of the other column) -> an edge of the clip that cuts no line.
/**
 * @param list<array{int, int}> $bands
 * @param int $y
 * @param bool $up
 * @return int
 */
function aio_oc_clear($bands, $y, $up)
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

// The shifts of the page and the bottom of the clip on the screen at each. 0 the first screen;
// with something below ($bands of aio_oc_down), $s1, then STEP further at most while the page goes
// on below the clip, the last ($max) its end at the bottom of the clip, MIN_STEP after the one
// before at least. With the cast ($cast) the stop after $s1 puts "About" at the top of the clip:
// the cast goes whole. The top of the clip at a stop and its bottom cut no line (aio_oc_clear):
// a line cut at the top goes down whole, at the bottom it is not shown. $off: the shift of the
// stop of the coordinates of the bands (the layout).
/**
 * @param list<array{int, int}> $bands
 * @param int $s1
 * @param bool $cast
 * @param int $off
 * @return array{list<int>, list<int>}
 */
function aio_oc_stops($bands, $s1, $cast, $off)
{
    $stops = array(0);
    // The top of the clip at stop $s in the coordinates of the bands: $s + $top.
    $top = AIO_OC_VIEW_Y - $off;
    if ($bands)
    {
        $end = 0;
        foreach ($bands as $b)
            $end = max($end, $b[1]);
        $max = $off + $end - AIO_OC_SHOW_BOTTOM;
        $lo = $cast ? AIO_OC_ABOUT_Y - $top : 0;
        $stops[] = $s = $s1;
        while ($s < $max)
        {
            $n = $lo > $s ? $lo : max($s + AIO_OC_MIN_STEP, aio_oc_clear($bands, $s + AIO_OC_STEP + $top, true) - $top);
            if ($n > $max - AIO_OC_MIN_STEP)
                $n = max($lo, aio_oc_clear($bands, max($max, $s + AIO_OC_MIN_STEP) + $top, false) - $top);
            $stops[] = $s = $n;
        }
    }
    $clips = array();
    foreach ($stops as $s)
        $clips[] = aio_oc_clear($bands, AIO_OC_SHOW_BOTTOM - $off + $s, true) + $off - $s;
    return array($stops, $clips);
}

// The card laid out: its kind, buttons and their y, the stops of the page, the bottom of the clip
// at each and the height of the page, the shift of the coordinates of "down" (off), the zones of
// the focus in the order of UP and DOWN, what the first screen alone shows (what the keys need,
// AioOcNav), the components DOWN shows. The first stop: the cast at DOWN_Y; without it "About" at
// its place, the page further by ABOUT_Y - DOWN_Y. The first screen alone: the line under the
// buttons (a movie: its progress, aio_wh_movie, read here once a request; only with an IMDb and a
// card of the Dune, the only case it shows) - its foot would peep under the logo at DOWN - and the
// hint, if DOWN shows something. A series: the cast DOWN too, the history read once (aio_oc_pick):
// the episode to offer and the progress of the episodes; the tabs of its seasons and the row, or
// the line "did not load" without the meta.
/**
 * @param AioOcCard $c
 * @return AioOcLayout
 */
function aio_oc_layout($c)
{
    $series = $c['tile']['type'] === 'series';
    list($down, $bands) = aio_oc_down($c);
    $cast = $c['meta'] && $c['meta']['cast'];
    $off = $series ? AIO_OC_OFF_SER : AIO_OC_OFF;
    $ep = null;
    $prog = array();
    if ($series)
    {
        list($ep, $prog) = aio_oc_pick($c);
        if ($cast)
        {
            $down = array_merge(aio_oc_cast($c, AIO_OC_DOWN_Y), $down);
            $bands[] = aio_oc_band(AIO_OC_DOWN_Y, AIO_CATV_LINE_PX);
        }
    }
    $btns = aio_oc_buttons($c, $ep);
    $s1 = $cast ? $off : $off + AIO_OC_ABOUT_Y - AIO_OC_DOWN_Y;
    list($stops, $clips) = aio_oc_stops($bands, $s1, $cast, $off);
    $tabs = $series && $c['meta'] ? aio_oc_tabs_layout($c)
        : array('list' => array(), 'x' => 0, 'w' => 0, 'all' => 0, 'word' => 0, 'cx' => 0);
    $zones = array('btn');
    if ($series && (!$c['meta'] || $tabs['list']))
        $zones[] = 'pill';
    if ($tabs['list'])
        $zones[] = 'row';
    if (count($stops) > 1)
        $zones[] = 'down';
    if ($series)
    {
        $x = AIO_CATV_CARD_X;
        foreach ($btns as $b)
            $x += aio_oc_btn_w($b) + AIO_OC_BTN_GAP;
        $only = aio_oc_under_ser($c, $x + 12);
        if ($down)
            $only = array_merge($only, aio_oc_hint(aio_tr($c['ui'], $cast ? 'card_cast_about_series' : 'card_about_series'),
                AIO_CATV_CARD_X, AIO_OC_HINT_Y_SER));
    }
    else
    {
        $wh = $c['imdb'] !== '' && $c['dune'] !== '' ? aio_wh_movie($c['dune']) : null;
        $only = aio_oc_under($c, $wh, $btns ? aio_oc_btn_w($btns[0]) : 0);
        if ($down)
        {
            $hint = aio_tr($c['ui'], 'card_about_movie');
            $only = array_merge($only, aio_oc_hint($hint,
                AIO_OC_HINT_R - AIO_GC_SORT_ARROW_W - 16 - aio_gc_w($hint, AIO_CATV_LINE_PX), AIO_OC_CAST_Y));
        }
    }
    return array('kind' => $series ? 'series' : 'movie', 'btns' => $btns, 'btn_y' => $series ? AIO_OC_BTN_Y_SER : AIO_OC_BTN_Y,
        'stops' => $stops, 'clips' => $clips, 'h' => max(1080, $stops[count($stops) - 1] + AIO_OC_SHOW_BOTTOM), 'off' => $off,
        'zones' => $zones, 'tabs' => $tabs, 'ep' => $ep, 'prog' => $prog, 'first' => $only, 'down' => $down);
}

// The clip under the logo down to $bottom of the screen.
/**
 * @param int $bottom
 * @return AioGcGeom
 */
function aio_oc_view_geom($bottom)
{
    return aio_gc_at(1920, $bottom - AIO_OC_VIEW_Y, 0, AIO_OC_VIEW_Y);
}

// The page at shift $s inside the clip under the logo.
/**
 * @param int $s
 * @param int $h
 * @return AioGcGeom
 */
function aio_oc_page_geom($s, $h)
{
    return aio_gc_at(1920, $h, 0, -AIO_OC_VIEW_Y - $s);
}

// The darkening of zone $z: DOWN the whole screen, the logo baked into the background too.
/**
 * @param string $z
 * @return list<AioGcComp>
 */
function aio_oc_dim($z)
{
    return $z === 'down' ? array(aio_gc_rect(aio_gc_at(1920, 1080, 0, 0), AIO_OC_DIM)) : array();
}

// --- The place of the focus.

// A place on the card, AioOcPos, in AioOc::$cards and as sel_state alike: z the zone ("btn" the
// buttons, "pill" the tabs of the seasons, "row" the episodes, "down" the page below them), b the
// button (kept in every zone: UP comes back to it), p the stop of the page (0 but in "down"), s
// and e the season and the episode of the row of a series (0 for none, not in sel_state). A new
// zone or field is one more line in aio_oc_pos and aio_oc_next.
/**
 * @param AioOcPos $pos
 * @return string
 */
function aio_oc_state($pos)
{
    $j = array('z' => $pos['z'], 'b' => $pos['b'], 'p' => $pos['p']);
    if ($pos['s'] > 0)
        $j += array('s' => $pos['s'], 'e' => $pos['e']);
    return json_encode($j);
}

// The button lit at place $pos: the one of the focus in the zones of the buttons and of the page
// below (dark there), none in the zones of the series; -1 for none.
/**
 * @param AioOcPos $pos
 * @return int
 */
function aio_oc_btn_lit($pos)
{
    return $pos['z'] === 'btn' || $pos['z'] === 'down' ? $pos['b'] : -1;
}

// sel_state of the shell -> the place, each field checked alone: one missing or of a wrong type
// is that of the last place of the card, a number out of the card is clamped (one button less
// keeps the stop), a zone the card has not: the buttons. {"b", "p"} of before the zone: the zone of
// its stop. A season not of the card: that of the episode to offer, else the first; an episode
// not of the season: aio_oc_def_e.
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param mixed $raw
 * @return AioOcPos
 */
function aio_oc_pos($c, $l, $raw)
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
    $s = isset($j['s']) && is_int($j['s']) ? $j['s'] : $last['s'];
    $e = isset($j['e']) && is_int($j['e']) ? $j['e'] : $last['e'];
    if (!in_array($z, $l['zones'], true))
        $z = 'btn';
    if (!aio_oc_eps($c, $s))
    {
        $s = $l['ep'] && aio_oc_eps($c, $l['ep']['s']) ? $l['ep']['s'] : 0;
        if ($s === 0 && $c['meta'] && $c['meta']['seasons'])
            $s = key($c['meta']['seasons']);
        $e = 0;
    }
    if (aio_cm_ep_index(aio_oc_eps($c, $s), $e) < 0)
        $e = aio_oc_def_e($c, $l, $s);
    return array('z' => $z, 'b' => max(0, min(count($l['btns']) - 1, $b)),
        'p' => $z === 'down' ? max(1, min(count($l['stops']) - 1, $p)) : 0, 's' => $s, 'e' => $e);
}

// The place after key $d (left, right, up, down, pgup, pgdn) from $pos; $pos itself at an edge.
// Up and down go from zone to zone in the order of the layout (the buttons, the tabs, the row, the
// page below), in "down" from stop to stop first, entering it at the first stop. Left and right:
// along the buttons; along the tabs, the season of the tab shown in the row at once at its episode
// (aio_oc_def_e); along the row; P+ and P- (pgup, pgdn) PAGE stills along the row.
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param AioOcPos $pos
 * @param string $d
 * @return AioOcPos
 */
function aio_oc_next($c, $l, $pos, $d)
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
    $steps = array('left' => -1, 'right' => 1, 'pgup' => -AIO_OC_PAGE, 'pgdn' => AIO_OC_PAGE);
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
            $n['e'] = aio_oc_def_e($c, $l, $tab[0]);
        }
    }
    else if ($z === 'row')
    {
        $eps = aio_oc_eps($c, $pos['s']);
        if ($eps)
            $n['e'] = $eps[max(0, min(count($eps) - 1, aio_cm_ep_index($eps, $pos['e']) + $step))]['e'];
    }
    return $n;
}

// The change_defs from place $old to $new: the lit buttons redrawn, the tabs, the row and its frame
// (aio_oc_row_changes) or the line "did not load", the page and the bottom of its clip moved, the
// darkening on or off, what the first screen alone shows off or on.
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param AioOcPos $old
 * @param AioOcPos $new
 * @return list<AioGcChange>
 */
function aio_oc_changes($c, $l, $old, $new)
{
    $ch = array();
    $ob = aio_oc_btn_lit($old);
    $nb = aio_oc_btn_lit($new);
    if ($nb !== $ob)
    {
        foreach (array($ob, $nb) as $i)
        {
            if ($i >= 0)
                $ch[] = aio_gc_change("b$i", null, GCOMP_TRANSITION_NONE,
                    aio_oc_btn_items($l['btns'][$i], aio_oc_btn_w($l['btns'][$i]), $i === $nb));
        }
    }
    if ($l['tabs']['list'])
        $ch = array_merge($ch, aio_oc_row_changes($c, $l, $old, $new));
    else if ($l['kind'] === 'series' && !$c['meta'] && ($old['z'] === 'pill') !== ($new['z'] === 'pill'))
    {
        $r = aio_oc_retry_panel($c, $new['z']);
        $ch[] = aio_gc_change('retry', null, GCOMP_TRANSITION_NONE, $r[GComponentDef::specific_def][GCompPanelDef::children]);
    }
    if ($new['p'] !== $old['p'])
    {
        $ch[] = aio_gc_change('view', aio_oc_view_geom($l['clips'][$new['p']]), GCOMP_TRANSITION_DEFAULT);
        $ch[] = aio_gc_change('page', aio_oc_page_geom($l['stops'][$new['p']], $l['h']), GCOMP_TRANSITION_DEFAULT);
    }
    if (($new['z'] === 'down') !== ($old['z'] === 'down'))
    {
        $ch[] = aio_gc_change('dim', null, GCOMP_TRANSITION_NONE, aio_oc_dim($new['z']));
        $ch[] = aio_gc_change('first', null, GCOMP_TRANSITION_NONE, $new['z'] === 'down' ? array() : $l['first']);
    }
    return $ch;
}

// --- The screen.

/**
 * @param AioOcCard $c
 * @param AioOcLayout $l
 * @param AioOcPos $pos
 * @return array<string, mixed>
 */
function aio_oc_window($c, $l, $pos)
{
    $t = $c['tile'];
    $card = aio_catv_card($t, true);
    $d = array();
    // The band under the buttons: a gradient, then dark to the bottom.
    $d[] = aio_gc_image(aio_gc_at(1920, 160, 0, 470), AIO_OC_IMG . 'band.png');
    $d[] = aio_gc_rect(aio_gc_at(1920, 450, 0, 630), AIO_OC_BAND);
    $d[] = aio_gc_panel('dim', aio_gc_at(1920, 1080, 0, 0), aio_oc_dim($pos['z']));
    // Without the logo of the background: the title as in the panel of the catalog.
    if (!$card['logo'])
        $d[] = aio_gc_panel('title', aio_gc_at(AIO_CATV_PANE_W, AIO_OC_VIEW_Y, AIO_CATV_CARD_X, 0),
            array(aio_catv_title_label(aio_clean($t['name']), AIO_OC_VIEW_Y)));
    $page = array_merge(aio_oc_head($c, $l, aio_oc_btn_lit($pos)),
        $l['kind'] === 'series' ? aio_oc_series($c, $l, $pos) : aio_oc_cast($c, AIO_OC_CAST_Y));
    // What DOWN shows, in a panel off lower: its own coordinates are those of the first stop with the cast.
    if ($l['down'])
        $page[] = aio_gc_panel('down', aio_gc_at(1920, $l['h'] - $l['off'], 0, $l['off']), $l['down']);
    $d[] = aio_gc_panel('view', aio_oc_view_geom($l['clips'][$pos['p']]),
        array(aio_gc_panel('page', aio_oc_page_geom($l['stops'][$pos['p']], $l['h']), $page, 0)), 0);
    // What the first screen alone shows, over the clip: the hint of a series is under its bottom.
    $d[] = aio_gc_panel('first', aio_gc_at(1920, 1080, 0, 0), $pos['z'] === 'down' ? array() : $l['first']);
    return array(
        GCompWindowDef::background_color => 'rgb(0,0,0)',
        GCompWindowDef::background_url => $card['bg'],
        GCompWindowDef::async_loading_background => true,
        GCompWindowDef::comp_defs => $d,
        GCompWindowDef::ui_state => null,
        GCompWindowDef::not_loaded_background_url => $card['nl'],
        GCompWindowDef::playback_bg_alpha => -1,
        GCompWindowDef::background_fit_def => array(
            ImageFitDef::base_halign_ratio => 0.5,
            ImageFitDef::base_valign_ratio => 0.5),
        GCompWindowDef::small_state_text => null,
        GCompWindowDef::opaque_background => false);
}

// get_folder_view of "card:<type>:<id>"; a card not in memory and not made anew: "outdated".
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_oc_folder_view($media_url, $sel_state)
{
    $key = strval(substr($media_url, strlen('card:')));
    $c = aio_oc_get($key);
    if (!$c)
        return aio_lines_view(array(aio_expired_item()));
    $l = aio_oc_layout($c);
    $pos = aio_oc_pos($c, $l, $sel_state);
    AioOc::$cards[$key]['pos'] = $pos;
    $nav = $l;
    unset($nav['down']);
    AioOc::$cards[$key]['nav'] = $nav;
    aio_log("card: own $key: screen, " . count($l['btns']) . ' buttons, ' . count($l['stops']) . " stops, at {$pos['z']} " .
        "{$pos['b']}, {$pos['p']}" . ($pos['s'] > 0 ? ", S{$pos['s']}E{$pos['e']}" : ''));
    $k = array('c' => $key);
    $acts = array(
        GUI_EVENT_KEY_LEFT => aio_input('card_move', $k + array('d' => 'left')),
        GUI_EVENT_KEY_RIGHT => aio_input('card_move', $k + array('d' => 'right')),
        GUI_EVENT_KEY_UP => aio_input('card_move', $k + array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => aio_input('card_move', $k + array('d' => 'down')),
        GUI_EVENT_KEY_ENTER => aio_input('card_enter', $k),
        GUI_EVENT_KEY_POPUP_MENU => aio_input('card_menu', $k));
    if ($l['tabs']['list'])
        $acts += array(
            GUI_EVENT_KEY_P_PLUS => aio_input('card_move', $k + array('d' => 'pgup')),
            GUI_EVENT_KEY_P_MINUS => aio_input('card_move', $k + array('d' => 'pgdn')));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => aio_oc_window($c, $l, $pos),
            PluginGCompsFolderView::sel_state => aio_oc_state($pos),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => null));
}

// --- Keys.

// "Watch": the list of Melange as play_action makes it of the card of the tile, the id of the card
// of the Dune (or none: no history) in movie_str. native_title, as the card of the Dune gives it
// (JacRed asks by it): the original title of the meta, else the English one of the tile. A series:
// episode $e of season $q as asked (aio_cm_req_s), season_numbers of the meta (the episodes out,
// aio_cm_eps) for the playlist of the season and the next episode; the cursor of the list on
// release $hash of the history ('' none). The list marked own: its MENU has no "Choose episode"
// (choose_season_episode would give the seasons of the last native series, not of this card).
/**
 * @param AioOcCard $c
 * @param int $q
 * @param int $e
 * @param string $hash
 * @return AioAction
 */
function aio_oc_watch($c, $q = -1, $e = -1, $hash = '')
{
    $t = $c['tile'];
    $native = $c['meta'] && $c['meta']['original'] !== '' ? $c['meta']['original'] : $t['englishTitle'];
    $mv = array('id' => $c['dune'], 'imdb_id' => $c['imdb'], 'title' => $t['name'],
        'native_title' => $native, 'year' => $t['year'], 'poster_url' => $t['poster'], 'fanart_url' => $t['background'],
        'rate_imdb' => $t['imdbRating'], 'description' => $t['description']);
    $series = $t['type'] === 'series';
    if ($series && $c['meta'])
    {
        $sn = array();
        foreach ($c['meta']['season_numbers'] as $s => $n)
            $sn[$s] = array('episodes_count' => $n);
        $mv['season_numbers'] = $sn;
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
    $a = aio_play_action($in);
    if (!Aio::$state || $a[GuiAction::handler_string_id] !== PLUGIN_OPEN_FOLDER_ACTION_ID)
        return $a;
    Aio::$state['movie']['own_card'] = true;
    if ($hash !== '')
        Aio::$state = aio_list_cursor(Aio::$state, $hash);
    return $a;
}

// MENU: "Settings", as on the list.
/**
 * @param string $lang
 * @return AioAction
 */
function aio_oc_menu($lang)
{
    return array(
        GuiAction::handler_string_id => SHOW_POPUP_MENU_ACTION_ID,
        GuiAction::data => array(ShowPopupMenuActionData::menu_items => array(array(
            GuiMenuItemDef::caption => aio_tr($lang, 'setup_menu'),
            GuiMenuItemDef::icon_url => 'gui_skin://small_icons/setup.aai',
            GuiMenuItemDef::action => aio_setup_open_act()))));
}

// card_move (d: left, right, up, down, pgup, pgdn), card_enter, card_menu of the card c. The keys:
// the place of aio_oc_next by change_gcomps, null at an edge; the layout of the screen shown
// (nav), else laid out anew. ENTER on a button: "Watch" - the list of the episode offered, an
// episode not out yet - the focus to it in the row, "Dune card" - the native card (file_movie);
// on "did not load" - the meta again; on an episode - its list, one not out yet - the message;
// below them nothing.
/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction|null
 */
function aio_oc_input($in, $control)
{
    $key = aio_catv_in($in, 'c');
    $c = aio_oc_get($key);
    if ($control === 'card_menu')
        return aio_oc_menu($c ? $c['ui'] : aio_catv_ui_lang());
    if (!$c)
        return $control === 'card_enter' ? aio_error('cat_outdated') : null;
    $l = isset($c['nav']) ? $c['nav'] : aio_oc_layout($c);
    $pos = aio_oc_pos($c, $l, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    AioOc::$cards[$key]['pos'] = $pos;
    $new = $pos;
    if ($control === 'card_enter')
    {
        $b = $pos['b'];
        $ep = $l['ep'];
        if ($pos['z'] === 'pill' && !$c['meta'] && $l['kind'] === 'series')
            return aio_oc_retry($key, $c);
        if ($pos['z'] === 'row')
        {
            $x = aio_oc_ep($c, $pos['s'], $pos['e']);
            aio_log("card: own $key: ENTER on S{$pos['s']}E{$pos['e']}" . ($x && $x['soon'] ? ', not out yet' : ''));
            if (!$x || $x['soon'])
                return $x ? aio_oc_soon_msg($x, $c['ui']) : null;
            $q = aio_cm_req_s($c['meta'], $x['s']);
            return aio_oc_watch($c, $q, $x['e'], $ep && $ep['q'] === $q && $ep['e'] === $x['e'] ? $ep['hash'] : '');
        }
        if ($pos['z'] !== 'btn' || !isset($l['btns'][$b]))
            return null;
        $kind = $l['btns'][$b][0];
        aio_log("card: own $key: ENTER on $kind");
        if ($kind === 'dune')
            return aio_card_open($c['dune']);
        if ($kind === 'watch')
            return $ep ? aio_oc_watch($c, $ep['q'], $ep['e'], $ep['hash']) : aio_oc_watch($c);
        // Not out yet: the focus to it in the row.
        $new = array('z' => 'row', 'b' => $b, 'p' => 0, 's' => $ep['s'], 'e' => $ep['e']);
    }
    else
        $new = aio_oc_next($c, $l, $pos, aio_catv_in($in, 'd'));
    if ($new === $pos)
        return null;
    AioOc::$cards[$key]['pos'] = $new;
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => aio_oc_changes($c, $l, $pos, $new),
            ChangeGCompsActionData::num_steps => $new['z'] === $pos['z'] && ($new['z'] === 'row' || $new['z'] === 'pill') ?
                AIO_OC_ROW_STEPS : AIO_GC_STEPS,
            ChangeGCompsActionData::sel_state => aio_oc_state($new)));
}
