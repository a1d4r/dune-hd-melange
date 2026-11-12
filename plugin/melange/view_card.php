<?php
// The own card of a movie as a view_gcomps screen "card:<type>:<id>", 1920x1080: the panel of the
// catalog grown into a card. The card background of the server (aio_catv_card) with the logo, or
// the dark one and the title as text; the lines of the panel of the catalog at its places (facts,
// genres and countries, the director, the description), the buttons in a row ("Watch", "Dune
// card") over a band of our own, under them the progress of the movie or why there is none; the
// hint "Cast and about" at the bottom. DOWN scrolls the page under the logo to the cast and the
// details of the meta (card_meta.php), the rest of the screen darkened; DOWN again further while
// there is more, UP back, at last to the button left. The plugin draws and moves the focus
// itself with change_gcomps, as the list; the place travels in sel_state {"b":button,"p":stop}.
// ENTER on a tile does the network (aio_oc_enter); the screen is built from memory.
// Needs main.php, catalog.php, catalog_card.php, card_meta.php, view_catalog.php, gcomps.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// Cards kept in memory by "<type>:<id>"; the oldest opened goes.
define('AIO_OC_KEEP', 8);
// The page: a clipping panel from the bottom of the logo (PANE_Y + TITLE_BOTTOM of the catalog,
// baked into card/v1) down; the page in it in the coordinates of the first screen. DOWN: the first
// stop OFF lower (the cast at 290 of the screen, the hint at 1030 above the clip), the next ones
// STEP further while the page goes on below SHOW_BOTTOM.
define('AIO_OC_VIEW_Y', 256);
define('AIO_OC_OFF', 830);
define('AIO_OC_STEP', 420);
define('AIO_OC_SHOW_BOTTOM', 1040);
// The first screen (design-own-card.md, B): lines of the panel, the description in 30 px lines
// DESC_LH apart from under the director (or the genres) down to DESC_BOTTOM; buttons BTN_H high
// at BTN_Y, the text PAD from the edges (and the icon of "Watch" ICON_W more), GAP apart; CAP: the
// rounded ends (PNG), a rect between them; the progress or a line under them at LINE_Y; the bar
// BAR_H high BAR_DY under its top; the hint at HINT_Y.
define('AIO_OC_FACTS_Y', 269);
define('AIO_OC_GENRES_Y', 315);
define('AIO_OC_DIRECTOR_Y', 361);
define('AIO_OC_DESC_LH', 42);
define('AIO_OC_DESC_BOTTOM', 560);
define('AIO_OC_BTN_Y', 596);
define('AIO_OC_BTN_H', 64);
define('AIO_OC_BTN_PAD', 32);
define('AIO_OC_ICON_W', 34);
define('AIO_OC_BTN_GAP', 20);
define('AIO_OC_CAP', 12);
define('AIO_OC_LINE_Y', 670);
define('AIO_OC_BAR_H', 6);
define('AIO_OC_BAR_DY', 9);
define('AIO_OC_HINT_Y', 1030);
// DOWN: the cast at CAST_Y of the screen, photos 120x152 (TMDB w276_and_h350_face, a half) STEP
// apart, the name in 2 lines and the role in NAME_W; "About" at ABOUT_Y (CAST_Y without the
// cast), the description in DESC2_W, the pairs of the details at DET_X in DET_W.
define('AIO_OC_CAST_Y', 290);
define('AIO_OC_PHOTO_W', 120);
define('AIO_OC_PHOTO_H', 152);
define('AIO_OC_PHOTO_STEP', 172);
define('AIO_OC_NAME_W', 160);
define('AIO_OC_ABOUT_Y', 590);
define('AIO_OC_DESC2_W', 960);
define('AIO_OC_DESC2_LINES', 100);
define('AIO_OC_DET_X', 1180);
define('AIO_OC_DET_W', 665);
// Colours of the design: buttons, the bar, no photo, the darkening of DOWN.
define('AIO_OC_BTN_OFF', '#24FFFFFF');
define('AIO_OC_BAR_REST', '#FF404040');
define('AIO_OC_NO_PHOTO', '#FF262626');
define('AIO_OC_DIM', '#C0000000');
define('AIO_OC_BAND', '#D0000000');
// Our pictures: the ends of a button, off and focused, the icon of "Watch", the band over the
// background from y 470 (1920x160, then a rect). card<N>: N goes up when they change, as cat<N>.
define('AIO_OC_IMG', 'plugin_file://img/card1_');

class AioOc
{
    // Cards by "<type>:<id>": tile, meta (or null), imdb, dune (the id of the card of the Dune or
    // of an old local one, '' for none), lang (of play_action), ui (of the texts), pos (b, p).
    // Memory of php_server only: after a restart the card is made from the meta anew.
    /** @var array<string, AioOcCard> */
    public static $cards = array();
}

// ENTER on a movie tile with IMDb or TMDB (aio_card_enter): the id of the card of the Dune
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
        $dune = aio_card_local($tile, false);
    return array('tile' => $tile, 'meta' => $meta, 'imdb' => $imdb, 'dune' => $dune, 'lang' => $lang,
        'ui' => aio_catv_ui_lang(), 'pos' => array(0, 0));
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
    while (count(AioOc::$cards) > AIO_OC_KEEP)
        array_shift(AioOc::$cards);
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
    if (!preg_match('/^(movie):(tt[0-9]{1,10}|tmdb:([0-9]{1,10}))$/D', $key, $m))
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

// The facts of the panel of the catalog (aio_catv_facts) with the kind first: "Фильм · 2001 · 2 ч 32 мин".
/**
 * @param AioOcCard $c
 * @return list<array{string, string, string}>
 */
function aio_oc_facts($c)
{
    $kind = aio_tr($c['ui'], 'card_kind_movie');
    $out = array();
    $done = false;
    foreach (aio_catv_facts($c['tile'], $c['ui']) as $f)
    {
        if ($f[0] === 'age' && !$done)
            $out[] = array('text', $kind, '');
        if ($f[0] === 'text' || ($f[0] === 'age' && !$done))
            $done = true;
        if ($f[0] === 'text')
            $f[1] = "$kind · {$f[1]}";
        $out[] = $f;
    }
    if (!$done)
        $out[] = array('text', $kind, '');
    return $out;
}

// The genres of the tile and the countries, as aio_catv_genre_line fits them: what does not fit
// at the least size goes, the countries but the first, then all of them, then the second genre.
/**
 * @param AioOcCard $c
 * @return string
 */
function aio_oc_genre_line($c)
{
    list($g) = aio_catv_genre_parts($c['tile'], $c['ui']);
    $cs = aio_oc_countries($c);
    $line = '';
    foreach (array(array($g, $cs), array($g, array_slice($cs, 0, 1)), array($g, array()), array(array_slice($g, 0, 1), array())) as $v)
    {
        $line = implode(' · ', array_filter(array(implode(', ', $v[0]), implode(', ', $v[1])), 'strlen'));
        if (aio_gc_w($line, AIO_CATV_DATA_MIN_PX) <= AIO_CATV_TEXT_W)
            break;
    }
    return $line;
}

// "Y-m-d" -> "16 ноября 2001"; a day after today (UTC): "Выходит 14.11.2026"; '' for none.
/**
 * @param string $d
 * @param string $lang
 * @return string
 */
function aio_oc_date($d, $lang)
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $d, $x))
        return '';
    if ($d > gmdate('Y-m-d'))
        return str_replace('{d}', "$x[3].$x[2].$x[1]", aio_tr($lang, 'card_coming'));
    $months = explode(',', aio_tr($lang, 'card_months'));
    $mon = isset($months[intval($x[2]) - 1]) ? $months[intval($x[2]) - 1] : $x[2];
    return intval($x[3]) . " $mon $x[1]";
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
/**
 * @param AioOcCard $c
 * @return list<array{string, string, bool}>
 */
function aio_oc_buttons($c)
{
    $b = array();
    if ($c['imdb'] !== '')
        $b[] = array('watch', aio_tr($c['ui'], 'card_watch'), true);
    if ($c['dune'] !== '')
        $b[] = array('dune', aio_tr($c['ui'], 'card_dune'), false);
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
// DESC_BOTTOM, the buttons (focus on $b), the progress or a line, the hint.
/**
 * @param AioOcCard $c
 * @param list<array{string, string, bool}> $btns
 * @param int $b
 * @param bool $down
 * @return list<array<string, mixed>>
 */
function aio_oc_head($c, $btns, $b, $down)
{
    $x0 = AIO_CATV_CARD_X;
    // The line of facts in a panel at the x of the catalog: its labels are at x of the panel.
    $d = array(aio_gc_panel('facts', aio_gc_at(AIO_CATV_PANE_W, AIO_OC_GENRES_Y, $x0, 0),
        aio_catv_facts_labels(aio_oc_facts($c), AIO_OC_FACTS_Y)));
    $y = AIO_OC_GENRES_Y;
    $genres = aio_oc_genre_line($c);
    if ($genres !== '')
    {
        $fit = aio_catv_fit($genres, AIO_CATV_LINE_PX, AIO_CATV_DATA_MIN_PX, AIO_CATV_TEXT_W);
        $d[] = aio_oc_label($x0, $y, AIO_CATV_TEXT_W, $fit[1], $fit[0], AIO_CATV_TEXT_COLOR);
        $y += AIO_CATV_LINE_H;
    }
    $dirs = $c['meta'] ? $c['meta']['directors'] : array();
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
    $n = max(0, intval((AIO_OC_DESC_BOTTOM - $y) / AIO_OC_DESC_LH));
    $desc = $c['meta'] && $c['meta']['description'] !== '' ? $c['meta']['description'] : $c['tile']['description'];
    foreach (aio_gc_wrap(aio_clean($desc), AIO_CATV_LINE_PX, AIO_CATV_TEXT_W, $n, true) as $i => $line)
        $d[] = aio_oc_label($x0, $y + $i * AIO_OC_DESC_LH, AIO_CATV_TEXT_W, $line, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);

    $x = $x0;
    $w0 = 0;
    foreach ($btns as $i => $bt)
    {
        $w = aio_oc_btn_w($bt);
        $d[] = aio_gc_panel("b$i", aio_gc_at($w, AIO_OC_BTN_H, $x, AIO_OC_BTN_Y), aio_oc_btn_items($bt, $w, $i === $b));
        if ($i === 0)
            $w0 = $w;
        $x += $w + AIO_OC_BTN_GAP;
    }
    // Under the buttons: no IMDb - nothing plays; no card of the Dune - no history; else the
    // progress of the movie under "Watch", if begun.
    $line = '';
    if ($c['imdb'] === '')
        $line = aio_tr($c['ui'], 'err_no_imdb');
    else if ($c['dune'] === '')
        $line = aio_tr($c['ui'], 'card_no_history');
    else
    {
        $wh = aio_wh_movie($c['dune']);
        $p = $wh ? aio_oc_progress_text($wh[0], $wh[1], $c['ui']) : '';
        if ($p !== '')
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
        $d[] = aio_oc_label($x0, AIO_OC_LINE_Y, AIO_CATV_TEXT_W, $line, 24, AIO_CATV_TEXT_COLOR);
    if ($down)
    {
        $hint = aio_tr($c['ui'], 'card_down_movie');
        $hw = aio_gc_w($hint, AIO_CATV_LINE_PX);
        $d[] = aio_oc_label($x0, AIO_OC_HINT_Y, $hw, $hint, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
        $d[] = aio_gc_image(aio_gc_at(AIO_GC_SORT_ARROW_W, AIO_GC_SORT_ARROW_H, $x0 + $hw + 16, AIO_OC_HINT_Y + 16),
            'plugin_file://img/sort_down.png');
    }
    return $d;
}

// What DOWN shows, in the coordinates of the screen at the first stop: the cast (photos, names
// in 2 lines, the English roles grey; no photo: the initials), "About the movie": the tagline,
// the whole description, at the right the details. -> array(components, their bottom); none: no stop.
/**
 * @param AioOcCard $c
 * @return array{list<array<string, mixed>>, int}
 */
function aio_oc_down($c)
{
    $m = $c['meta'];
    $lang = $c['ui'];
    $x0 = AIO_CATV_CARD_X;
    $d = array();
    $y = AIO_OC_CAST_Y;
    $cast = $m ? $m['cast'] : array();
    if ($cast)
    {
        $d[] = aio_oc_label($x0, $y, 800, aio_tr($lang, 'card_cast'), AIO_CATV_LINE_PX, AIO_GC_TEXT);
        $py = $y + 44;
        foreach ($cast as $i => $p)
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
            foreach (array($parts[0], isset($parts[1]) ? $parts[1] : '', $p['role']) as $k => $s)
            {
                if ($s !== '')
                    $d[] = aio_oc_label($x, $py + 160 + 26 * $k, AIO_OC_NAME_W,
                        aio_gc_cut_w(aio_clean($s), 24, AIO_OC_NAME_W), 24, $k < 2 ? AIO_GC_TEXT : AIO_GC_DIM);
            }
        }
        $y = AIO_OC_ABOUT_Y;
    }
    $desc = $m && $m['description'] !== '' ? $m['description'] : $c['tile']['description'];
    $rows = array();
    $name = $c['tile']['name'];
    if ($m && $m['original'] !== '' && $m['original'] !== $name)
        $rows[] = array('card_original', $m['original']);
    if ($m)
        $rows[] = array('card_released', aio_oc_date($m['released'], $lang));
    if ($m)
        $rows[] = array('card_director', implode(', ', $m['directors']));
    if ($m)
        $rows[] = array('card_writers', implode(', ', $m['writers']));
    if ($m)
        $rows[] = array('card_studios', implode(', ', $m['studios']));
    $rows[] = array('card_countries', implode(', ', aio_oc_countries($c)));
    $tagline = $m ? $m['tagline'] : '';
    $bottom = $y;
    $any = $desc !== '' || $tagline !== '' || $d;
    foreach ($rows as $r)
        $any = $any || $r[1] !== '';
    if (!$any)
        return array(array(), 0);
    $d[] = aio_oc_label($x0, $y, 800, aio_tr($lang, 'card_about_movie'), AIO_CATV_LINE_PX, AIO_GC_TEXT);
    $ty = $y + 50;
    foreach (aio_gc_wrap(aio_clean($tagline), AIO_CATV_LINE_PX, AIO_OC_DESC2_W, 2, true) as $line)
    {
        $d[] = aio_oc_label($x0, $ty, AIO_OC_DESC2_W, $line, AIO_CATV_LINE_PX, AIO_GC_DIM);
        $ty += AIO_OC_DESC_LH;
    }
    if ($tagline !== '')
        $ty += 8;
    foreach (aio_gc_wrap(aio_clean($desc), AIO_CATV_LINE_PX, AIO_OC_DESC2_W, AIO_OC_DESC2_LINES, true) as $line)
    {
        $d[] = aio_oc_label($x0, $ty, AIO_OC_DESC2_W, $line, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
        $ty += AIO_OC_DESC_LH;
    }
    $bottom = max($bottom + 50, $ty);
    $ry = $y + 50;
    foreach ($rows as $r)
    {
        if ($r[1] === '')
            continue;
        $d[] = aio_oc_label(AIO_OC_DET_X, $ry, AIO_OC_DET_W, aio_tr($lang, $r[0]), 24, AIO_GC_DIM);
        $ry += 30;
        foreach (aio_gc_wrap(aio_clean($r[1]), AIO_CATV_LINE_PX, AIO_OC_DET_W, 2, true) as $line)
        {
            $d[] = aio_oc_label(AIO_OC_DET_X, $ry, AIO_OC_DET_W, $line, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
            $ry += 40;
        }
        $ry += 18;
    }
    return array($d, max($bottom, $ry));
}

// The shifts of the page: 0 the first screen; with something below, OFF, then STEP further while
// the page goes on below SHOW_BOTTOM, the last to its end.
/**
 * @param int $bottom
 * @return list<int>
 */
function aio_oc_stops($bottom)
{
    if ($bottom <= 0)
        return array(0);
    $stops = array(0, AIO_OC_OFF);
    $max = AIO_OC_OFF + max(0, $bottom - AIO_OC_SHOW_BOTTOM);
    for ($s = AIO_OC_OFF; $s < $max; )
    {
        $s = min($s + AIO_OC_STEP, $max);
        $stops[] = $s;
    }
    return $stops;
}

// The card laid out: its buttons, the components DOWN shows and the stops of the page.
/**
 * @param AioOcCard $c
 * @return array{btns: list<array{string, string, bool}>, down: list<array<string, mixed>>, bottom: int, stops: list<int>}
 */
function aio_oc_layout($c)
{
    list($down, $bottom) = aio_oc_down($c);
    return array('btns' => aio_oc_buttons($c), 'down' => $down, 'bottom' => $bottom, 'stops' => aio_oc_stops($bottom));
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

// The darkening of a stop below the first: all but the logo (to x 930, its bottom at 270).
/**
 * @param int $p
 * @return list<AioGcComp>
 */
function aio_oc_dim($p)
{
    if ($p === 0)
        return array();
    return array(aio_gc_rect(aio_gc_at(1920, 810, 0, 270), AIO_OC_DIM), aio_gc_rect(aio_gc_at(990, 270, 930, 0), AIO_OC_DIM));
}

// --- The screen.

/**
 * @param int $b
 * @param int $p
 * @return string
 */
function aio_oc_state($b, $p)
{
    return json_encode(array('b' => $b, 'p' => $p));
}

// sel_state of the shell -> array(button, stop); else the last place of the card; else the first button.
/**
 * @param AioOcCard $c
 * @param array{btns: list<array{string, string, bool}>, down: list<array<string, mixed>>, bottom: int, stops: list<int>} $l
 * @param mixed $raw
 * @return array{int, int}
 */
function aio_oc_pos($c, $l, $raw)
{
    foreach (array(is_string($raw) ? json_decode($raw) : null, (object) array('b' => $c['pos'][0], 'p' => $c['pos'][1])) as $j)
    {
        if (is_object($j) && isset($j->b, $j->p) && is_int($j->b) && is_int($j->p) && $j->b >= 0 &&
            $j->b < max(1, count($l['btns'])) && $j->p >= 0 && $j->p < count($l['stops']))
            return array($j->b, $j->p);
    }
    return array(0, 0);
}

/**
 * @param AioOcCard $c
 * @param array{btns: list<array{string, string, bool}>, down: list<array<string, mixed>>, bottom: int, stops: list<int>} $l
 * @param int $b
 * @param int $p
 * @return array<string, mixed>
 */
function aio_oc_window($c, $l, $b, $p)
{
    $t = $c['tile'];
    $card = aio_catv_card($t, true);
    $d = array();
    // The band under the buttons: a gradient, then dark to the bottom.
    $d[] = aio_gc_image(aio_gc_at(1920, 160, 0, 470), AIO_OC_IMG . 'band.png');
    $d[] = aio_gc_rect(aio_gc_at(1920, 450, 0, 630), AIO_OC_BAND);
    $d[] = aio_gc_panel('dim', aio_gc_at(1920, 1080, 0, 0), aio_oc_dim($p));
    // Without the logo of the background: the title as in the panel of the catalog.
    if (!$card['logo'])
        $d[] = aio_gc_panel('title', aio_gc_at(AIO_CATV_PANE_W, AIO_OC_VIEW_Y, AIO_CATV_CARD_X, 0),
            array(aio_catv_title_label(aio_clean($t['name']), AIO_OC_VIEW_Y)));
    $page = aio_oc_head($c, $l['btns'], $b, (bool) $l['down']);
    foreach ($l['down'] as $x)
    {
        $x[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::y] =
            (isset($x[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::y]) ?
            $x[GComponentDef::geom_def][GCompGeometryDef::align_def][GCompAlignDef::y] : 0) + AIO_OC_OFF;
        $page[] = $x;
    }
    $h = max(1080, $l['bottom'] + AIO_OC_OFF + 40);
    $d[] = aio_gc_panel('view', aio_gc_at(1920, 1080 - AIO_OC_VIEW_Y, 0, AIO_OC_VIEW_Y),
        array(aio_gc_panel('page', aio_oc_page_geom($l['stops'][$p], $h), $page, 0)), 0);
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
    list($b, $p) = aio_oc_pos($c, $l, $sel_state);
    AioOc::$cards[$key]['pos'] = array($b, $p);
    aio_log("card: own $key: screen, " . count($l['btns']) . ' buttons, ' . count($l['stops']) . " stops, at $b, $p");
    $k = array('c' => $key);
    $acts = array(
        GUI_EVENT_KEY_LEFT => aio_input('card_move', $k + array('d' => 'left')),
        GUI_EVENT_KEY_RIGHT => aio_input('card_move', $k + array('d' => 'right')),
        GUI_EVENT_KEY_UP => aio_input('card_move', $k + array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => aio_input('card_move', $k + array('d' => 'down')),
        GUI_EVENT_KEY_ENTER => aio_input('card_enter', $k),
        GUI_EVENT_KEY_POPUP_MENU => aio_input('card_menu', $k));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => aio_oc_window($c, $l, $b, $p),
            PluginGCompsFolderView::sel_state => aio_oc_state($b, $p),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => null));
}

// --- Keys.

// "Watch": the list of Melange as play_action makes it of the card of the tile, the id of the card
// of the Dune (or none: no history) in movie_str.
/**
 * @param AioOcCard $c
 * @return AioAction
 */
function aio_oc_watch($c)
{
    $t = $c['tile'];
    $in = new stdClass();
    $in->movie_str = json_encode(array('id' => $c['dune'], 'imdb_id' => $c['imdb'], 'title' => $t['name'], 'year' => $t['year'],
        'poster_url' => $t['poster'], 'fanart_url' => $t['background'], 'rate_imdb' => $t['imdbRating'],
        'description' => $t['description']));
    $in->series_type = 'single';
    $in->lang = $c['lang'];
    return aio_play_action($in);
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

// card_move (d: left, right, up, down), card_enter, card_menu of the card c. Left and right move
// along the buttons, down and up from stop to stop of the page (up from the first one below:
// back to the button left); change_gcomps, null at an edge. ENTER on a button: "Watch" - the list,
// "Dune card" - the native card (file_movie); below the buttons nothing.
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
    $l = aio_oc_layout($c);
    list($b, $p) = aio_oc_pos($c, $l, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    AioOc::$cards[$key]['pos'] = array($b, $p);
    if ($control === 'card_enter')
    {
        if ($p !== 0 || !isset($l['btns'][$b]))
            return null;
        aio_log("card: own $key: ENTER on {$l['btns'][$b][0]}");
        return $l['btns'][$b][0] === 'watch' ? aio_oc_watch($c) : aio_card_open($c['dune']);
    }
    $d = aio_catv_in($in, 'd');
    $nb = $b;
    $np = $p;
    if ($p === 0 && ($d === 'left' || $d === 'right'))
        $nb = max(0, min(count($l['btns']) - 1, $b + ($d === 'left' ? -1 : 1)));
    else if ($d === 'down')
        $np = min(count($l['stops']) - 1, $p + 1);
    else if ($d === 'up')
        $np = max(0, $p - 1);
    if ($nb === $b && $np === $p)
        return null;
    AioOc::$cards[$key]['pos'] = array($nb, $np);
    $ch = array();
    if ($nb !== $b)
    {
        foreach (array($b => false, $nb => true) as $i => $on)
            $ch[] = aio_gc_change("b$i", null, GCOMP_TRANSITION_NONE,
                aio_oc_btn_items($l['btns'][$i], aio_oc_btn_w($l['btns'][$i]), $on));
    }
    if ($np !== $p)
    {
        $ch[] = aio_gc_change('page', aio_oc_page_geom($l['stops'][$np], max(1080, $l['bottom'] + AIO_OC_OFF + 40)),
            GCOMP_TRANSITION_DEFAULT);
        if ($np === 0 || $p === 0)
            $ch[] = aio_gc_change('dim', null, GCOMP_TRANSITION_NONE, aio_oc_dim($np));
    }
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => $ch,
            ChangeGCompsActionData::num_steps => AIO_GC_STEPS,
            ChangeGCompsActionData::sel_state => aio_oc_state($nb, $np)));
}
