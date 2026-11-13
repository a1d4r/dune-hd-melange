<?php
// The page of a person as a view_gcomps screen "person:<tmdb>", 1920x1080 on the dark (2.0 of the
// own card, design-own-card.md, "Страница персоны"): ENTER on a person of the row "В ролях" opens it.
// The photo at the left (the address of the cell of the row: in the cache of the shell already),
// the name, the line of facts (the profession white, the dates and the place grey), 5 lines of the
// biography; under them the works (a strip of view_card_row.php, posters as in the catalog, under
// each its title and the role or the year). DOWN: the whole biography (when the 5 lines do not hold
// it), page by page; UP back. ENTER on a work: its own card (aio_card_enter); Back: the card the
// person came from, at the same person (its sel_state).
// The screen draws at once of what the row knew (the name, the photo, the profession) and, till the
// person comes, stubs; its timer asks the server (aio_cm_person: AioCmMore keeps 3), then the
// screen anew. No reply in time (later): asked again by the timer, AIO_PP_TRIES times in all, then
// ENTER asks again; none: "no data". The place of the focus: in AioPp and in sel_state.
// Needs view_card.php, view_card_row.php, card_more.php, view_catalog.php, gcomps.php, main.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// Persons kept in memory (what the row knew, the place, the tries), the oldest opened goes.
define('AIO_PP_KEEP', 16);
// Asks of the person a screen makes by its timer before ENTER is needed; the first at once (the
// screen is drawn by then), the next ones RETRY_MS later.
define('AIO_PP_TRIES', 2);
define('AIO_PP_TICK_MS', 100);
define('AIO_PP_RETRY_MS', 2000);
// The photo, the name (72 px), the facts, the biography (BIO_LINES of 30 px BIO_LH apart up to
// HINT_R); the works: the title at WORKS_Y, the posters of view_card_row.php under it, their cells
// CELL_H high (2 lines of the title and 2 of the role under a poster: their bottom at 1004, over
// the hint of DOWN at HINT_Y).
define('AIO_PP_PHOTO_X', 130);
define('AIO_PP_PHOTO_Y', 60);
define('AIO_PP_PHOTO_W', 276);
define('AIO_PP_PHOTO_H', 350);
define('AIO_PP_TEXT_X', 454);
define('AIO_PP_NAME_Y', 66);
define('AIO_PP_NAME_PX', 72);
define('AIO_PP_NAME_H', 100);
define('AIO_PP_FACTS_Y', 160);
define('AIO_PP_BIO_Y', 222);
define('AIO_PP_BIO_LH', 42);
define('AIO_PP_BIO_LINES', 5);
define('AIO_PP_WORKS_Y', 470);
define('AIO_PP_CELL_H', 488);
// DOWN: the whole biography a page lower, its title at BIO2_Y, PAGE_LINES lines of it a stop from
// the x of the catalog to HINT_R; the hint of it at the foot of the first screen.
define('AIO_PP_BIO2_Y', 60);
define('AIO_PP_PAGE_LINES', 20);
define('AIO_PP_BIO2_MAX', 200);
define('AIO_PP_HINT_Y', 1030);

class AioPp
{
    // tmdb => what the row knew (name, photo, job: the profession in the language of the screen),
    // lang (of play_action), ui, pos (the place, aio_pp_pos), err ('' none yet, later, none) and
    // tries of the timer. Memory of php_server only.
    /** @var array<string, AioPpEntry> */
    public static $people = array();
}

// ENTER on person $p of the row (an id of TMDB) with the profession $job the row shows: kept, its
// screen opened with the focus on the first work; no network here.
/**
 * @param AioCardCredit $p
 * @param string $job
 * @param string $lang
 * @param string $ui
 * @return AioAction
 */
function aio_pp_open($p, $job, $lang, $ui)
{
    aio_pp_keep($p['tmdb'], array('name' => $p['name'], 'photo' => $p['photo'], 'job' => $job, 'lang' => $lang, 'ui' => $ui,
        'pos' => array('z' => 'w', 'p' => 0, 'k' => 0), 'err' => '', 'tries' => 0));
    aio_log("card: person tmdb:{$p['tmdb']} opened");
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "person:{$p['tmdb']}",
            PluginOpenFolderActionData::caption => $p['name']));
}

/**
 * @param string $tmdb
 * @param AioPpEntry $e
 * @return void
 */
function aio_pp_keep($tmdb, $e)
{
    unset(AioPp::$people[$tmdb]);
    AioPp::$people[$tmdb] = $e;
    while (count(AioPp::$people) > AIO_PP_KEEP)
        array_shift(AioPp::$people);
}

// Person $tmdb in memory changed to $e, in its place of the order.
/**
 * @param string $tmdb
 * @param AioPpEntry $e
 * @return void
 */
function aio_pp_put($tmdb, $e)
{
    AioPp::$people[$tmdb] = $e;
}

// The person of "person:<tmdb>" in memory; none (php_server restarted): known by the id alone.
/**
 * @param string $tmdb
 * @return AioPpEntry
 */
function aio_pp_entry($tmdb)
{
    if (isset(AioPp::$people[$tmdb]))
        return AioPp::$people[$tmdb];
    $ui = aio_catv_ui_lang();
    $e = array('name' => '', 'photo' => '', 'job' => '', 'lang' => aio_catv_lang(), 'ui' => $ui,
        'pos' => array('z' => 'w', 'p' => 0, 'k' => 0), 'err' => '', 'tries' => 0);
    aio_pp_keep($tmdb, $e);
    return $e;
}

// --- Texts.

// The profession of the server (knownFor) in the language of the screen; another as it is.
/**
 * @param string $kf
 * @param string $ui
 * @return string
 */
function aio_pp_job($kf, $ui)
{
    $k = 'person_job_' . strtolower($kf);
    return in_array($kf, array('Actor', 'Director', 'Writer', 'Producer', 'Creator'), true) ? aio_tr($ui, $k) : $kf;
}

// The full years from "Y-m-d" $born to $today ("Y-m-d"); -1 for a wrong date.
/**
 * @param string $born
 * @param string $today
 * @return int
 */
function aio_pp_age($born, $today)
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2}-[0-9]{2})$/D', $born, $b) || !preg_match('/^([0-9]{4})-([0-9]{2}-[0-9]{2})$/D', $today, $t))
        return -1;
    $n = intval($t[1]) - intval($b[1]) - ($t[2] < $b[2] ? 1 : 0);
    return $n >= 0 ? $n : -1;
}

// A date of a person (aio_cm_person_date): "7 марта 1956", the day unknown "март 1956", the year
// alone "1956"; '' for none.
/**
 * @param string $d
 * @param string $ui
 * @return string
 */
function aio_pp_day($d, $ui)
{
    if (preg_match('/^([0-9]{4})-([0-9]{2})$/D', $d, $x))
    {
        $months = explode(',', aio_tr($ui, 'person_months'));
        return (isset($months[intval($x[2]) - 1]) ? $months[intval($x[2]) - 1] : $x[2]) . " $x[1]";
    }
    return preg_match('/^[0-9]{4}$/D', $d) ? $d : aio_oc_day($d, $ui);
}

// The grey part of the facts: "7 марта 1956 (70 лет) — <the place>"; died: "19 августа 1969 — 28
// октября 2023 — <the place>" (no age); a date in part (aio_pp_day): no age; died, born unknown:
// "… — 28 октября 2023" (a date alone reads as the birth); what is not known left out with its dash.
/**
 * @param AioCmPerson $p
 * @param string $ui
 * @return string
 */
function aio_pp_facts($p, $ui)
{
    $parts = array();
    $d = aio_pp_day($p['died'], $ui);
    if (($b = aio_pp_day($p['born'], $ui)) !== '')
    {
        $age = $p['died'] === '' ? aio_pp_age($p['born'], gmdate('Y-m-d')) : -1;
        $parts[] = $b . ($age >= 0 ? ' ' . str_replace(array('{n}', '{y}'), array(strval($age),
            aio_gc_plural($ui, $age, 'person_years')), aio_tr($ui, 'person_age')) : '');
    }
    else if ($d !== '')
        $parts[] = "\xE2\x80\xA6";
    if ($d !== '')
        $parts[] = $d;
    if ($p['place'] !== '')
        $parts[] = $p['place'];
    return implode(' — ', $parts);
}

// The lines under the title of work $w of a person of profession $kf (knownFor), 2 at most: its
// role; none, of brackets alone ("(voice)") or the profession itself (a director's "Director"):
// the year; a series: "· 62 серии" whole (the number with its word) at the end of the last line of
// the role, or "62 серии" on the second line when it does not fit there, the role then in one.
/**
 * @param AioCmWork $w
 * @param string $kf
 * @param string $ui
 * @return list<string>
 */
function aio_pp_work_lines($w, $kf, $ui)
{
    $s = aio_clean($w['role']);
    if (preg_replace('~\([^()]*\)|[\s,;/]~', '', $s) === '' || ($kf !== '' && strcasecmp($s, $kf) === 0))
        $s = $w['tile']['year'];
    if ($w['eps'] <= 0)
        return aio_gc_wrap($s, 24, AIO_OC_POSTER_W, 2, true);
    $eps = $w['eps'] . ' ' . aio_gc_plural($ui, $w['eps'], 'card_eps');
    if ($s === '')
        return array($eps);
    $all = aio_gc_wrap($s, 24, AIO_OC_POSTER_W, 3, true);
    $n = count($all);
    if ($n >= 1 && $n <= 2 && aio_gc_w($all[$n - 1] . " · $eps", 24) <= AIO_OC_POSTER_W)
        return array_merge(array_slice($all, 0, $n - 1), array($all[$n - 1] . " · $eps"));
    // "Walter White,…": no comma before the ellipsis.
    return array(strval(preg_replace('/[\s,;]+(\x{2026})$/u', '$1', aio_gc_cut_w($s, 24, AIO_OC_POSTER_W))), $eps);
}

// --- The layout.

// The strip of $n works ("w": "wst", "wfr", the cells "w<i>", their titles "wn<i>").
/**
 * @param int $n
 * @return AioOcStrip
 */
function aio_pp_strip($n)
{
    return aio_oc_poster_strip('w', $n, 0, AIO_PP_WORKS_Y, AIO_PP_CELL_H);
}

// The screen of person $tmdb ($e in memory, $p the person or null): its state (ok; wait - asked by
// the timer; failed - ENTER asks again; none), the works strip, the lines of the biography of the
// first screen and those of DOWN by stops (none: no stop).
/**
 * @param AioPpEntry $e
 * @param AioCmPerson|null $p
 * @return AioPpLayout
 */
function aio_pp_layout($e, $p)
{
    $st = $p ? 'ok' : ($e['err'] === 'none' ? 'none' : ($e['err'] === 'later' && $e['tries'] >= AIO_PP_TRIES ? 'failed' : 'wait'));
    $bio = $p ? $p['bio'] : '';
    $all = $bio !== '' ? aio_gc_wrap($bio, AIO_CATV_LINE_PX, AIO_OC_HINT_R - AIO_PP_TEXT_X, AIO_PP_BIO_LINES + 1, true) : array();
    $pages = array();
    if (count($all) > AIO_PP_BIO_LINES)
        $pages = array_chunk(aio_gc_wrap($bio, AIO_CATV_LINE_PX, AIO_OC_HINT_R - AIO_CATV_CARD_X, AIO_PP_BIO2_MAX, true),
            AIO_PP_PAGE_LINES);
    return array('state' => $st, 'strip' => aio_pp_strip($p ? count($p['works']) : 0),
        'bio' => aio_gc_wrap($bio, AIO_CATV_LINE_PX, AIO_OC_HINT_R - AIO_PP_TEXT_X, AIO_PP_BIO_LINES, true), 'pages' => $pages);
}

// --- The place of the focus.

// sel_state -> the place: z "w" the works, "bio" the whole biography at stop p (1..), k the work
// (kept in both). A field missing or of a wrong type: that of the last place; out of the screen:
// clamped (no works yet: kept), a stop the screen has not: the works.
/**
 * @param AioPpEntry $e
 * @param AioPpLayout $l
 * @param mixed $raw
 * @return AioPpPos
 */
function aio_pp_pos($e, $l, $raw)
{
    $j = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($j))
        $j = array();
    $last = $e['pos'];
    $z = isset($j['z']) && is_string($j['z']) ? $j['z'] : $last['z'];
    $p = isset($j['p']) && is_int($j['p']) ? $j['p'] : $last['p'];
    $k = isset($j['k']) && is_int($j['k']) ? $j['k'] : $last['k'];
    if ($z !== 'bio' || !$l['pages'])
        $z = 'w';
    // No works yet (the person asked again): the work kept for when they come.
    if ($l['strip']['n'] > 0)
        list($k) = aio_oc_strip_at($l['strip'], $k);
    $k = max(0, $k);
    return array('z' => $z, 'p' => $z === 'bio' ? max(1, min(count($l['pages']), $p)) : 0, 'k' => $k);
}

/**
 * @param AioPpPos $pos
 * @return string
 */
function aio_pp_state($pos)
{
    return strval(json_encode(array('z' => $pos['z'], 'p' => $pos['p'], 'k' => $pos['k'])));
}

// --- The screen.

// The title of work $w and the lines under it (aio_pp_work_lines), the title white in focus.
/**
 * @param AioCmWork $w
 * @param string $kf
 * @param bool $on
 * @param string $ui
 * @return list<AioGcComp>
 */
function aio_pp_work_titles($w, $kf, $on, $ui)
{
    return aio_oc_poster_titles($w['tile']['name'], aio_pp_work_lines($w, $kf, $ui), $on ? AIO_GC_TEXT : AIO_GC_TEXT2);
}

// The page of a stop of DOWN: "Биография" and its lines of page $i.
/**
 * @param AioPpLayout $l
 * @param int $p
 * @param string $ui
 * @return list<AioGcComp>
 */
function aio_pp_bio_page($l, $p, $ui)
{
    if (!isset($l['pages'][$p - 1]))
        return array();
    $d = array(aio_oc_label(AIO_CATV_CARD_X, AIO_PP_BIO2_Y, 800, aio_tr($ui, 'person_bio'), AIO_CATV_LINE_PX, AIO_GC_TEXT));
    foreach ($l['pages'][$p - 1] as $i => $s)
        $d[] = aio_oc_label(AIO_CATV_CARD_X, AIO_PP_BIO2_Y + 60 + $i * AIO_PP_BIO_LH, AIO_OC_HINT_R - AIO_CATV_CARD_X, $s,
            AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
    if (isset($l['pages'][$p]))
        $d[] = aio_gc_image(aio_gc_at(AIO_GC_SORT_ARROW_W, AIO_GC_SORT_ARROW_H, AIO_CATV_CARD_X, AIO_PP_HINT_Y + 16),
            'plugin_file://img/sort_down.png');
    return $d;
}

// The page of the first screen at $y: the facts, the biography or the state, the works.
/**
 * @param AioPpEntry $e
 * @param AioCmPerson|null $p
 * @param AioPpLayout $l
 * @param AioPpPos $pos
 * @return list<AioGcComp>
 */
function aio_pp_page($e, $p, $l, $pos)
{
    $ui = $e['ui'];
    $x = AIO_PP_TEXT_X;
    $w = AIO_OC_HINT_R - $x;
    $name = $p ? $p['name'] : $e['name'];
    $photo = $e['photo'] !== '' ? $e['photo'] : ($p ? $p['photo'] : '');
    $d = array(aio_gc_rect(aio_gc_at(AIO_PP_PHOTO_W, AIO_PP_PHOTO_H, AIO_PP_PHOTO_X, AIO_PP_PHOTO_Y), AIO_OC_NO_PHOTO));
    if ($photo !== '')
        $d[] = aio_gc_image(aio_gc_at(AIO_PP_PHOTO_W, AIO_PP_PHOTO_H, AIO_PP_PHOTO_X, AIO_PP_PHOTO_Y), $photo);
    $nl = aio_oc_label($x, AIO_PP_NAME_Y, $w, aio_gc_cut_w(aio_clean($name), AIO_PP_NAME_PX, $w), AIO_PP_NAME_PX, AIO_GC_TEXT);
    // The name of 72 px: its own height.
    $nl[GComponentDef::geom_def][GCompGeometryDef::h] = AIO_PP_NAME_H;
    $d[] = $nl;
    $kf = $p ? $p['known_for'] : '';
    $job = $kf !== '' ? aio_pp_job($kf, $ui) : $e['job'];
    $facts = $p ? aio_pp_facts($p, $ui) : '';
    $fx = $x;
    if ($job !== '')
    {
        $jw = min($w, aio_gc_w($job, AIO_CATV_LINE_PX));
        $d[] = aio_oc_label($x, AIO_PP_FACTS_Y, $jw, aio_gc_cut_w($job, AIO_CATV_LINE_PX, $w), AIO_CATV_LINE_PX, AIO_GC_TEXT);
        $fx += $jw + aio_gc_w(' ', AIO_CATV_LINE_PX);
        if ($facts !== '')
            $facts = "— $facts";
    }
    if ($facts !== '' && $fx < AIO_OC_HINT_R)
        $d[] = aio_oc_label($fx, AIO_PP_FACTS_Y, AIO_OC_HINT_R - $fx, aio_gc_cut_w($facts, AIO_CATV_LINE_PX, AIO_OC_HINT_R - $fx),
            AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
    $lines = $l['state'] === 'ok' ? $l['bio'] : array(aio_tr($ui, $l['state'] === 'wait' && $e['err'] === 'later' ? 'person_later' :
        "person_{$l['state']}"));
    foreach ($lines as $i => $s)
        $d[] = aio_oc_label($x, AIO_PP_BIO_Y + $i * AIO_PP_BIO_LH, $w, $s, AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
    // The works: the title (the count grey after it), the strip in a clip to the right edge of the
    // screen, the frame; stubs while the person is asked.
    $s = $l['strip'];
    $d = array_merge($d, aio_oc_row_title(AIO_PP_WORKS_Y, aio_tr($ui, 'person_works'), $p ? strval($s['n']) : '',
        AIO_OC_HINT_R - AIO_CATV_CARD_X));
    $cells = array();
    if ($p)
    {
        foreach ($p['works'] as $i => $wk)
            $cells[] = aio_oc_poster_cell($s, $i, $wk['tile'], aio_pp_work_titles($wk, $kf, $i === $pos['k'], $ui));
    }
    else if ($l['state'] === 'wait')
    {
        for ($i = 0; $i < AIO_OC_POSTER_VIS; $i++)
            $cells[] = aio_gc_rect(aio_gc_at(AIO_OC_POSTER_W, AIO_OC_POSTER_H, $i * AIO_OC_POSTER_STEP, 0), AIO_OC_NO_PHOTO);
        $s = array_merge($s, array('n' => AIO_OC_POSTER_VIS));
    }
    if ($cells)
        $d[] = aio_gc_panel('works', aio_gc_at(1920, AIO_PP_CELL_H, 0, $s['y']), array(aio_oc_strip_panel($s, $pos['k'], $cells)), 0);
    if ($p && $s['n'] > 0)
        $d[] = aio_oc_strip_frame($s, $pos['k'], true);
    // The hint of DOWN.
    if ($l['pages'])
        $d = array_merge($d, aio_oc_hint(aio_tr($ui, 'person_bio_more'), AIO_CATV_CARD_X, AIO_PP_HINT_Y));
    return $d;
}

// The page at zone $z: the first screen, or a screen up (the whole biography).
/**
 * @param string $z
 * @return AioGcGeom
 */
function aio_pp_page_geom($z)
{
    return aio_gc_at(1920, 2160, 0, $z === 'bio' ? -1080 : 0);
}

/**
 * @param AioPpEntry $e
 * @param AioCmPerson|null $p
 * @param AioPpLayout $l
 * @param AioPpPos $pos
 * @return array<string, mixed>
 */
function aio_pp_window($e, $p, $l, $pos)
{
    $page = aio_pp_page($e, $p, $l, $pos);
    $page[] = aio_gc_panel('bio', aio_gc_at(1920, 1080, 0, 1080), aio_pp_bio_page($l, $pos['p'], $e['ui']));
    return array(
        GCompWindowDef::background_color => 'rgb(0,0,0)',
        GCompWindowDef::background_url => null,
        GCompWindowDef::async_loading_background => true,
        GCompWindowDef::comp_defs => array(aio_gc_panel('view', aio_gc_at(1920, 1080, 0, 0),
            array(aio_gc_panel('page', aio_pp_page_geom($pos['z']), $page)), 0)),
        GCompWindowDef::ui_state => null,
        GCompWindowDef::not_loaded_background_url => null,
        GCompWindowDef::playback_bg_alpha => -1,
        GCompWindowDef::background_fit_def => array(
            ImageFitDef::base_halign_ratio => 0.5,
            ImageFitDef::base_valign_ratio => 0.5),
        GCompWindowDef::small_state_text => null,
        GCompWindowDef::opaque_background => false);
}

// The id of TMDB of "person:<tmdb>", '' for a wrong one.
/**
 * @param string $media_url
 * @return string
 */
function aio_pp_id($media_url)
{
    return preg_match('/^person:([1-9][0-9]{0,9})$/D', $media_url, $m) ? $m[1] : '';
}

// get_folder_view of "person:<tmdb>": the person from memory only (aio_cm_more_get); none there, as
// it is asked: the timer (aio_pp_load).
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function aio_pp_folder_view($media_url, $sel_state)
{
    $tmdb = aio_pp_id($media_url);
    if ($tmdb === '')
        return aio_lines_view(array(aio_expired_item()));
    $e = aio_pp_entry($tmdb);
    /** @var AioCmPerson|null $p */
    $p = aio_cm_more_get('person', "tmdb:$tmdb");
    $l = aio_pp_layout($e, $p);
    $pos = aio_pp_pos($e, $l, $sel_state);
    $e['pos'] = $pos;
    aio_pp_put($tmdb, $e);
    aio_log("card: person tmdb:$tmdb: screen, {$l['state']}, {$l['strip']['n']} works, at {$pos['z']} {$pos['p']}, work {$pos['k']}");
    $k = array('t' => $tmdb);
    $acts = array(
        GUI_EVENT_KEY_LEFT => aio_input('person_move', $k + array('d' => 'left')),
        GUI_EVENT_KEY_RIGHT => aio_input('person_move', $k + array('d' => 'right')),
        GUI_EVENT_KEY_UP => aio_input('person_move', $k + array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => aio_input('person_move', $k + array('d' => 'down')),
        GUI_EVENT_KEY_ENTER => aio_input('person_enter', $k),
        GUI_EVENT_KEY_POPUP_MENU => aio_input('person_menu', $k));
    $timer = null;
    if ($l['state'] === 'wait')
    {
        $acts[GUI_EVENT_TIMER] = aio_input('person_load', $k);
        $timer = array(GuiTimerDef::delay_ms => $e['tries'] > 0 ? AIO_PP_RETRY_MS : AIO_PP_TICK_MS);
    }
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => aio_pp_window($e, $p, $l, $pos),
            PluginGCompsFolderView::sel_state => aio_pp_state($pos),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => $timer));
}

// --- Keys.

// The place after key $d from $pos: LEFT and RIGHT along the works, DOWN to the whole biography
// and on by its stops, UP back; $pos itself at an edge.
/**
 * @param AioPpLayout $l
 * @param AioPpPos $pos
 * @param string $d
 * @return AioPpPos
 */
function aio_pp_next($l, $pos, $d)
{
    $n = $pos;
    if ($pos['z'] === 'w' && ($d === 'left' || $d === 'right'))
        list($n['k']) = aio_oc_strip_at($l['strip'], $pos['k'] + ($d === 'left' ? -1 : 1));
    else if ($d === 'down' && $l['pages'])
        $n = array_merge($pos, array('z' => 'bio', 'p' => min(count($l['pages']), $pos['p'] + 1)));
    else if ($d === 'up' && $pos['z'] === 'bio')
        $n = array_merge($pos, $pos['p'] > 1 ? array('p' => $pos['p'] - 1) : array('z' => 'w', 'p' => 0));
    return $n;
}

// The changes from $old to $new: along the works the strip, the frame, the titles of both; to the
// biography and back the page moved; its page of the stop.
/**
 * @param AioPpEntry $e
 * @param AioCmPerson $p
 * @param AioPpLayout $l
 * @param AioPpPos $old
 * @param AioPpPos $new
 * @return list<AioGcChange>
 */
function aio_pp_changes($e, $p, $l, $old, $new)
{
    $ch = array();
    if ($new['k'] !== $old['k'])
    {
        $ch = aio_oc_strip_changes($l['strip'], $old['k'], $new['k'], true, true);
        foreach (array($old['k'], $new['k']) as $i)
            $ch[] = aio_gc_change("wn$i", null, GCOMP_TRANSITION_NONE, aio_pp_work_titles($p['works'][$i], $p['known_for'],
                $i === $new['k'], $e['ui']));
    }
    if ($new['z'] !== $old['z'])
        $ch[] = aio_gc_change('page', aio_pp_page_geom($new['z']), GCOMP_TRANSITION_DEFAULT);
    if ($new['p'] !== $old['p'] && $new['p'] > 0)
        $ch[] = aio_gc_change('bio', null, GCOMP_TRANSITION_NONE, aio_pp_bio_page($l, $new['p'], $e['ui']));
    return $ch;
}

// person_move (d), person_enter, person_load (the timer), person_menu of person t. ENTER on a work:
// its own card; on "did not load": asked again (the screen anew, its timer). The timer: the
// person asked (aio_cm_person, AIO_CAT_TOTAL at most), the screen anew with it or its failure. Any
// key while the person is asked, or gone from AioCmMore with its screen open (AIO_CM_TTL past, Back
// deeper than it keeps, the shell did not ask the view again): as the timer, the place of the
// screen kept.
/**
 * @param stdClass $in
 * @param string $control
 * @return AioAction|null
 */
function aio_pp_input($in, $control)
{
    $tmdb = aio_pp_id('person:' . aio_catv_in($in, 't'));
    if ($control === 'person_menu')
        return aio_oc_menu(aio_catv_ui_lang());
    if ($tmdb === '')
        return null;
    $e = aio_pp_entry($tmdb);
    /** @var AioCmPerson|null $p */
    $p = aio_cm_more_get('person', "tmdb:$tmdb");
    $l = aio_pp_layout($e, $p);
    $pos = aio_pp_pos($e, $l, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    $e['pos'] = $pos;
    aio_pp_put($tmdb, $e);
    if ($control === 'person_load' || $l['state'] === 'wait')
    {
        if (!$p)
        {
            $r = aio_cm_person($tmdb, AIO_CAT_TOTAL);
            $e['tries'] = $r['err'] !== '' ? $e['tries'] + 1 : 0;
            $e['err'] = $r['err'];
            aio_pp_put($tmdb, $e);
            aio_log("card: person tmdb:$tmdb: " . ($r['err'] !== '' ? "{$r['err']}, try {$e['tries']}" : 'got'));
        }
        return aio_replace_act("person:$tmdb", $e['name']);
    }
    if ($control === 'person_enter')
    {
        if ($l['state'] === 'failed')
        {
            $e['err'] = '';
            $e['tries'] = 0;
            aio_pp_put($tmdb, $e);
            return aio_replace_act("person:$tmdb", $e['name']);
        }
        if (!$p || $pos['z'] !== 'w' || !isset($p['works'][$pos['k']]))
            return null;
        $t = $p['works'][$pos['k']]['tile'];
        aio_log("card: person tmdb:$tmdb: ENTER on work {$pos['k']}, {$t['type']}:" . aio_cm_id($t));
        return aio_card_enter($t, $e['lang']);
    }
    if (!$p || $control !== 'person_move')
        return null;
    $new = aio_pp_next($l, $pos, aio_catv_in($in, 'd'));
    if ($new === $pos)
        return null;
    $e['pos'] = $new;
    aio_pp_put($tmdb, $e);
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => aio_pp_changes($e, $p, $l, $pos, $new),
            ChangeGCompsActionData::num_steps => $new['z'] === $pos['z'] && $new['z'] === 'w' ? AIO_OC_ROW_STEPS : AIO_GC_STEPS,
            ChangeGCompsActionData::sel_state => aio_pp_state($new)));
}
