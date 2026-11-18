<?php
// The page of a person as a view_gcomps screen "person:<tmdb>", 1920x1080 on the dark (2.0 of the
// own card, design-own-card.md, "Страница персоны"): ENTER on a person of the row "В ролях" opens it.
// The photo at the left (the address of the cell of the row: in the cache of the shell already),
// the name, the line of facts (the profession white, the dates and the place grey), 5 lines of the
// biography; under them the works (a strip of view_card_row.php, posters as in the catalog, under
// each its title and the role or the year). DOWN: the whole biography (when the 5 lines do not hold
// it) in paragraphs, the titles of its sections white, page by page; UP back. ENTER on a work: its own card (mel_card_enter); Back: the card the
// person came from, at the same person (its sel_state).
// The screen draws at once of what the row knew (the name, the photo, the profession) and, till the
// person comes, stubs; its timer asks the server (mel_cm_person: MelCmMore keeps 3), then the
// screen anew. No reply in time (later): asked again by the timer, MEL_PP_TRIES times in all, then
// ENTER asks again; none: "no data". The place of the focus: in MelPp and in sel_state.
// Needs view_card.php, view_card_row.php, card_more.php, view_catalog.php, gcomps.php, main.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// Persons kept in memory (what the row knew, the place, the tries), the oldest opened goes.
define('MEL_PP_KEEP', 16);
// Asks of the person a screen makes by its timer before ENTER is needed; the first at once (the
// screen is drawn by then), the next ones RETRY_MS later.
define('MEL_PP_TRIES', 2);
define('MEL_PP_TICK_MS', 100);
define('MEL_PP_RETRY_MS', 2000);
// The photo, the name (72 px), the facts, the biography (BIO_LINES of 30 px BIO_LH apart up to
// HINT_R); the works: the title at WORKS_Y, the posters of view_card_row.php under it, their cells
// CELL_H high (2 lines of the focused title and 2 of the role under a poster: their bottom at
// 1016); the hint of DOWN on the line of the title of the works.
define('MEL_PP_PHOTO_X', 130);
define('MEL_PP_PHOTO_Y', 60);
define('MEL_PP_PHOTO_W', 276);
define('MEL_PP_PHOTO_H', 350);
define('MEL_PP_TEXT_X', 454);
define('MEL_PP_NAME_Y', 66);
define('MEL_PP_NAME_PX', 72);
define('MEL_PP_NAME_H', 100);
define('MEL_PP_FACTS_Y', 160);
define('MEL_PP_BIO_Y', 222);
define('MEL_PP_BIO_LH', 42);
define('MEL_PP_BIO_LINES', 5);
define('MEL_PP_WORKS_Y', 470);
define('MEL_PP_CELL_H', 488);
// DOWN: the whole biography a page lower, its title at BIO2_Y, PAGE_LINES lines of it a stop from
// the x of the catalog, DESC2_W wide, PAR_GAP more between its paragraphs (half a line); the arrow
// of another page at HINT_Y, above MEL_OC_SHOW_BOTTOM (overscan) and under the text (its last line at
// 60 + 42 + 798 = 900 at most, MEL_CATV_LINE_H high).
define('MEL_PP_BIO2_Y', 60);
define('MEL_PP_PAGE_LINES', 20);
define('MEL_PP_PAR_GAP', 21);
define('MEL_PP_BIO2_MAX', 200);
define('MEL_PP_HINT_Y', 998);

class MelPp
{
    // tmdb => what the row knew (name, photo, job: the profession in the language of the screen),
    // lang (of play_action), ui, pos (the place, mel_pp_pos), err ('' none yet, later, none) and
    // tries of the timer. Memory of php_server only.
    /** @var array<string, MelPpEntry> */
    public static $people = array();
}

// ENTER on person $p of the row (an id of TMDB) with the profession $job the row shows: kept, its
// screen opened with the focus on the first work; no network here.
/**
 * @param MelCardCredit $p
 * @param string $job
 * @param string $lang
 * @param string $ui
 * @return MelAction
 */
function mel_pp_open($p, $job, $lang, $ui)
{
    mel_pp_keep($p['tmdb'], array('name' => $p['name'], 'photo' => $p['photo'], 'job' => $job, 'lang' => $lang, 'ui' => $ui,
        'pos' => array('z' => 'w', 'p' => 0, 'k' => 0), 'err' => '', 'tries' => 0));
    mel_log("card: person tmdb:{$p['tmdb']} opened");
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "person:{$p['tmdb']}",
            PluginOpenFolderActionData::caption => $p['name']));
}

/**
 * @param string $tmdb
 * @param MelPpEntry $e
 * @return void
 */
function mel_pp_keep($tmdb, $e)
{
    unset(MelPp::$people[$tmdb]);
    MelPp::$people[$tmdb] = $e;
    while (count(MelPp::$people) > MEL_PP_KEEP)
    {
        reset(MelPp::$people);
        unset(MelPp::$people[key(MelPp::$people)]);
    }
}

// Person $tmdb in memory changed to $e, in its place of the order.
/**
 * @param string $tmdb
 * @param MelPpEntry $e
 * @return void
 */
function mel_pp_put($tmdb, $e)
{
    MelPp::$people[$tmdb] = $e;
}

// The person of "person:<tmdb>" in memory; none (php_server restarted): known by the id alone.
/**
 * @param string $tmdb
 * @return MelPpEntry
 */
function mel_pp_entry($tmdb)
{
    if (isset(MelPp::$people[$tmdb]))
        return MelPp::$people[$tmdb];
    $ui = mel_catv_ui_lang();
    $e = array('name' => '', 'photo' => '', 'job' => '', 'lang' => mel_catv_lang(), 'ui' => $ui,
        'pos' => array('z' => 'w', 'p' => 0, 'k' => 0), 'err' => '', 'tries' => 0);
    mel_pp_keep($tmdb, $e);
    return $e;
}

// --- Texts.

// The profession of the server (knownFor) in the language of the screen; another as it is.
/**
 * @param string $kf
 * @param string $ui
 * @return string
 */
function mel_pp_job($kf, $ui)
{
    $k = 'person_job_' . strtolower($kf);
    return in_array($kf, array('Actor', 'Director', 'Writer', 'Producer', 'Creator'), true) ? mel_tr($ui, $k) : $kf;
}

// The full years from "Y-m-d" $born to $today ("Y-m-d"); -1 for a wrong date.
/**
 * @param string $born
 * @param string $today
 * @return int
 */
function mel_pp_age($born, $today)
{
    if (!preg_match('/^([0-9]{4})-([0-9]{2}-[0-9]{2})$/D', $born, $b) || !preg_match('/^([0-9]{4})-([0-9]{2}-[0-9]{2})$/D', $today, $t))
        return -1;
    $n = intval($t[1]) - intval($b[1]) - ($t[2] < $b[2] ? 1 : 0);
    return $n >= 0 ? $n : -1;
}

// A date of a person (mel_cm_person_date): "7 марта 1956", the day unknown "март 1956", the year
// alone "1956"; '' for none.
/**
 * @param string $d
 * @param string $ui
 * @return string
 */
function mel_pp_day($d, $ui)
{
    if (preg_match('/^([0-9]{4})-([0-9]{2})$/D', $d, $x))
    {
        $months = explode(',', mel_tr($ui, 'person_months'));
        return (isset($months[intval($x[2]) - 1]) ? $months[intval($x[2]) - 1] : $x[2]) . " $x[1]";
    }
    return preg_match('/^[0-9]{4}$/D', $d) ? $d : mel_oc_day($d, $ui);
}

// The grey part of the facts: "7 марта 1956 (70 лет) · <the place>"; died: "19 августа 1969 — 28
// октября 2023 · <the place>" (no age); a date in part (mel_pp_day): no age; died, born unknown:
// "… — 28 октября 2023" (a date alone reads as the birth); what is not known left out.
/**
 * @param MelCmPerson $p
 * @param string $ui
 * @return string
 */
function mel_pp_facts($p, $ui)
{
    $parts = array();
    $d = mel_pp_day($p['died'], $ui);
    if (($b = mel_pp_day($p['born'], $ui)) !== '')
    {
        $age = $p['died'] === '' ? mel_pp_age($p['born'], gmdate('Y-m-d')) : -1;
        $parts[] = $b . ($age >= 0 ? ' ' . str_replace(array('{n}', '{y}'), array(strval($age),
            mel_gc_plural($ui, $age, 'person_years')), mel_tr($ui, 'person_age')) : '');
    }
    else if ($d !== '')
        $parts[] = "\xE2\x80\xA6";
    if ($d !== '')
        $parts[] = $d;
    $dates = implode(' — ', $parts);
    return $p['place'] !== '' ? ($dates !== '' ? "$dates · " : '') . $p['place'] : $dates;
}

// The lines under the title of work $w of a person of profession $kf (knownFor), 2 at most: its
// role; none, of brackets alone ("(voice)") or the profession itself (a director's "Director"):
// the year; a series: "62 серии" whole (the number with its word) on a separate line, the role
// then cut to one.
/**
 * @param MelCmWork $w
 * @param string $kf
 * @param string $ui
 * @return list<string>
 */
function mel_pp_work_lines($w, $kf, $ui)
{
    $s = mel_clean($w['role']);
    if (preg_replace('~\([^()]*\)|[\s,;/]~', '', $s) === '' || ($kf !== '' && strcasecmp($s, $kf) === 0))
        $s = $w['tile']['year'];
    if ($w['eps'] <= 0)
        return mel_gc_wrap($s, 24, MEL_OC_POSTER_W, 2, true);
    $eps = $w['eps'] . ' ' . mel_gc_plural($ui, $w['eps'], 'card_eps');
    if ($s === '')
        return array($eps);
    return array(mel_gc_cut_w($s, 24, MEL_OC_POSTER_W), $eps);
}

// --- The layout.

// The strip of $n works ("w": "wst", "wfr", the cells "w<i>", their titles "wn<i>").
/**
 * @param int $n
 * @return MelOcStrip
 */
function mel_pp_strip($n)
{
    return mel_oc_poster_strip('w', $n, 0, MEL_PP_WORKS_Y, MEL_PP_CELL_H);
}

// The screen of person $tmdb ($e in memory, $p the person or null): its state (ok; wait - asked by
// the timer; failed - ENTER asks again; none), the works strip, the lines of the biography of the
// first screen and those of DOWN by stops (none: no stop).
/**
 * @param MelPpEntry $e
 * @param MelCmPerson|null $p
 * @return MelPpLayout
 */
function mel_pp_layout($e, $p)
{
    $st = $p ? 'ok' : ($e['err'] === 'none' ? 'none' : ($e['err'] === 'later' && $e['tries'] >= MEL_PP_TRIES ? 'failed' : 'wait'));
    $bio = $p ? $p['bio'] : '';
    $all = $bio !== '' ? mel_gc_wrap($bio, MEL_CATV_LINE_PX, MEL_OC_HINT_R - MEL_PP_TEXT_X, MEL_PP_BIO_LINES + 1, true) : array();
    $pages = $p && count($all) > MEL_PP_BIO_LINES ? mel_pp_bio_pages($p['pars']) : array();
    return array('state' => $st, 'strip' => mel_pp_strip($p ? count($p['works']) : 0),
        'bio' => mel_gc_wrap($bio, MEL_CATV_LINE_PX, MEL_OC_HINT_R - MEL_PP_TEXT_X, MEL_PP_BIO_LINES, true), 'pages' => $pages);
}

// The whole biography (paragraphs of mel_cm_bio_pars) by stops: array(the line, a title, its y in the
// page) each, MEL_PP_BIO2_MAX lines in all at most (the last with "…"). A title white on a line of its
// own, the text of its section under it; PAR_GAP before a paragraph but the first of a page; a title
// goes to the next page with the first line of its text.
/**
 * @param list<array{string, string}> $pars
 * @return list<list<array{string, bool, int}>>
 */
function mel_pp_bio_pages($pars)
{
    $w = MEL_OC_DESC2_W;
    $h = MEL_PP_PAGE_LINES * MEL_PP_BIO_LH;
    $left = MEL_PP_BIO2_MAX;
    $pages = array();
    $page = array();
    $y = 0;
    foreach ($pars as $par)
    {
        $lines = array();
        if ($par[0] !== '')
            $lines[] = array($par[0], true);
        foreach ($par[1] !== '' ? mel_gc_wrap($par[1], MEL_CATV_LINE_PX, $w, max(1, $left - count($lines)), true) : array() as $s)
            $lines[] = array($s, false);
        foreach ($lines as $i => $ln)
        {
            if ($left <= 0)
                break 2;
            $gap = $i === 0 && $page ? MEL_PP_PAR_GAP : 0;
            $need = $gap + ($ln[1] && isset($lines[$i + 1]) ? 2 : 1) * MEL_PP_BIO_LH;
            if ($page && $y + $need > $h)
            {
                $pages[] = $page;
                $page = array();
                $y = 0;
                $gap = 0;
            }
            $page[] = array($ln[0], $ln[1], $y + $gap);
            $y += $gap + MEL_PP_BIO_LH;
            $left--;
        }
    }
    if ($page)
        $pages[] = $page;
    return $pages;
}

// --- The place of the focus.

// sel_state -> the place: z "w" the works, "bio" the whole biography at stop p (1..), k the work
// (kept in both). A field missing or of a wrong type: that of the last place; out of the screen:
// clamped (no works yet: kept), a stop the screen has not: the works.
/**
 * @param MelPpEntry $e
 * @param MelPpLayout $l
 * @param mixed $raw
 * @return MelPpPos
 */
function mel_pp_pos($e, $l, $raw)
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
        list($k) = mel_oc_strip_at($l['strip'], $k);
    $k = max(0, $k);
    return array('z' => $z, 'p' => $z === 'bio' ? max(1, min(count($l['pages']), $p)) : 0, 'k' => $k);
}

/**
 * @param MelPpPos $pos
 * @return string
 */
function mel_pp_state($pos)
{
    return strval(json_encode(array('z' => $pos['z'], 'p' => $pos['p'], 'k' => $pos['k'])));
}

// --- The screen.

// The title of work $w and the lines under it (mel_pp_work_lines), the title white in focus.
/**
 * @param MelCmWork $w
 * @param string $kf
 * @param bool $on
 * @param string $ui
 * @return list<MelGcComp>
 */
function mel_pp_work_titles($w, $kf, $on, $ui)
{
    return mel_oc_poster_titles($w['tile']['name'], mel_pp_work_lines($w, $kf, $ui), $on ? MEL_GC_TEXT : MEL_GC_TEXT2, $on);
}

// The page of a stop of DOWN: "Биография · <name>" and its lines (the titles of sections white).
/**
 * @param MelPpLayout $l
 * @param int $p
 * @param string $ui
 * @param string $name
 * @return list<MelGcComp>
 */
function mel_pp_bio_page($l, $p, $ui, $name)
{
    if (!isset($l['pages'][$p - 1]))
        return array();
    $d = mel_oc_row_title(MEL_PP_BIO2_Y, mel_tr($ui, 'person_bio'), $name, MEL_OC_HINT_R - MEL_CATV_CARD_X);
    foreach ($l['pages'][$p - 1] as $s)
        $d[] = mel_oc_label(MEL_CATV_CARD_X, MEL_PP_BIO2_Y + MEL_PP_BIO_LH + $s[2], MEL_OC_DESC2_W, $s[0],
            MEL_CATV_LINE_PX, $s[1] ? MEL_GC_TEXT : MEL_CATV_TEXT_COLOR);
    if (isset($l['pages'][$p]))
        $d[] = mel_oc_more(MEL_CATV_CARD_X, MEL_PP_HINT_Y);
    return $d;
}

// The page of the first screen at $y: the facts, the biography or the state, the works.
/**
 * @param MelPpEntry $e
 * @param MelCmPerson|null $p
 * @param MelPpLayout $l
 * @param MelPpPos $pos
 * @return list<MelGcComp>
 */
function mel_pp_page($e, $p, $l, $pos)
{
    $ui = $e['ui'];
    $x = MEL_PP_TEXT_X;
    $w = MEL_OC_HINT_R - $x;
    $name = $p ? $p['name'] : $e['name'];
    $photo = $e['photo'] !== '' ? $e['photo'] : ($p ? $p['photo'] : '');
    $d = array(mel_gc_rect(mel_gc_at(MEL_PP_PHOTO_W, MEL_PP_PHOTO_H, MEL_PP_PHOTO_X, MEL_PP_PHOTO_Y), MEL_OC_NO_PHOTO));
    if ($photo !== '')
        $d[] = mel_gc_image(mel_gc_at(MEL_PP_PHOTO_W, MEL_PP_PHOTO_H, MEL_PP_PHOTO_X, MEL_PP_PHOTO_Y), $photo);
    else
    {
        $ini = mel_oc_label(MEL_PP_PHOTO_X, MEL_PP_PHOTO_Y, MEL_PP_PHOTO_W, mel_oc_initials($name), 110, MEL_GC_DIM,
            array(GCompTtfLabelDef::halign => HALIGN_CENTER, GCompTtfLabelDef::valign => VALIGN_CENTER));
        $ini[GComponentDef::geom_def][GCompGeometryDef::h] = MEL_PP_PHOTO_H;
        $d[] = $ini;
    }
    $nl = mel_oc_label($x, MEL_PP_NAME_Y, $w, mel_gc_cut_w(mel_clean($name), MEL_PP_NAME_PX, $w), MEL_PP_NAME_PX, MEL_GC_TEXT);
    // The name of 72 px: its own height.
    $nl[GComponentDef::geom_def][GCompGeometryDef::h] = MEL_PP_NAME_H;
    $d[] = $nl;
    $kf = $p ? $p['known_for'] : '';
    $job = $kf !== '' ? mel_pp_job($kf, $ui) : $e['job'];
    $facts = $p ? mel_pp_facts($p, $ui) : '';
    $fx = $x;
    if ($job !== '')
    {
        $jw = min($w, intval(mel_gc_sum($job, MEL_CATV_LINE_PX)));
        $d[] = mel_oc_label($x, MEL_PP_FACTS_Y, $jw, mel_gc_cut_w($job, MEL_CATV_LINE_PX, $w), MEL_CATV_LINE_PX, MEL_GC_TEXT);
        $fx += $jw + intval(mel_gc_sum(' ', MEL_CATV_LINE_PX));
        if ($facts !== '')
            $facts = "· $facts";
    }
    if ($facts !== '' && $fx < MEL_OC_HINT_R)
        $d[] = mel_oc_label($fx, MEL_PP_FACTS_Y, MEL_OC_HINT_R - $fx, mel_gc_cut_w($facts, MEL_CATV_LINE_PX, MEL_OC_HINT_R - $fx),
            MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
    $lines = $l['state'] === 'ok' ? $l['bio'] : array(mel_tr($ui, $l['state'] === 'wait' && $e['err'] === 'later' ? 'person_later' :
        "person_{$l['state']}"));
    foreach ($lines as $i => $s)
        $d[] = mel_oc_label($x, MEL_PP_BIO_Y + $i * MEL_PP_BIO_LH, $w, $s, MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
    // The works: the title (the count grey after it), the strip in a clip to the right edge of the
    // screen, the frame; stubs while the person is asked.
    $s = $l['strip'];
    $tw = MEL_OC_HINT_R - MEL_CATV_CARD_X;
    if ($l['pages'])
    {
        list($hint, $hx) = mel_oc_hint_right(mel_tr($ui, 'person_bio_more'), MEL_PP_WORKS_Y);
        $d = array_merge($d, $hint);
        $tw = min($tw, $hx - MEL_OC_HINT_GAP - MEL_CATV_CARD_X);
    }
    $d = array_merge($d, mel_oc_row_title(MEL_PP_WORKS_Y, mel_tr($ui, 'person_works'), $p ? strval($s['n']) : '', $tw));
    $cells = array();
    if ($p)
    {
        foreach ($p['works'] as $i => $wk)
            $cells[] = mel_oc_poster_cell($s, $i, $wk['tile'], mel_pp_work_titles($wk, $kf, $i === $pos['k'], $ui));
    }
    else if ($l['state'] === 'wait')
    {
        for ($i = 0; $i < MEL_OC_POSTER_VIS; $i++)
            $cells[] = mel_oc_poster_stub($s, $i);
        $s = array_merge($s, array('n' => MEL_OC_POSTER_VIS));
    }
    if ($cells)
        $d[] = mel_gc_panel('works', mel_gc_at(1920, MEL_PP_CELL_H, 0, $s['y']), array(mel_oc_strip_panel($s, $pos['k'], $cells)), 0);
    if ($p && $s['n'] > 0)
        $d[] = mel_oc_strip_frame($s, $pos['k'], true);
    return $d;
}

// The page at zone $z: the first screen, or a screen up (the whole biography).
/**
 * @param string $z
 * @return MelGcGeom
 */
function mel_pp_page_geom($z)
{
    return mel_gc_at(1920, 2160, 0, $z === 'bio' ? -1080 : 0);
}

/**
 * @param MelPpEntry $e
 * @param MelCmPerson|null $p
 * @param MelPpLayout $l
 * @param MelPpPos $pos
 * @return array<string, mixed>
 */
function mel_pp_window($e, $p, $l, $pos)
{
    $page = mel_pp_page($e, $p, $l, $pos);
    $page[] = mel_gc_panel('bio', mel_gc_at(1920, 1080, 0, 1080), mel_pp_bio_page($l, $pos['p'], $e['ui'], $p ? $p['name'] : $e['name']));
    return array(
        GCompWindowDef::background_color => 'rgb(0,0,0)',
        GCompWindowDef::background_url => null,
        GCompWindowDef::async_loading_background => true,
        GCompWindowDef::comp_defs => array(mel_gc_panel('view', mel_gc_at(1920, 1080, 0, 0),
            array(mel_gc_panel('page', mel_pp_page_geom($pos['z']), $page)), 0)),
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
function mel_pp_id($media_url)
{
    return preg_match('/^person:([1-9][0-9]{0,9})$/D', $media_url, $m) ? $m[1] : '';
}

// get_folder_view of "person:<tmdb>": the person from memory only (mel_cm_more_get); none there, as
// it is asked: the timer (mel_pp_load).
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_pp_folder_view($media_url, $sel_state)
{
    $tmdb = mel_pp_id($media_url);
    if ($tmdb === '')
        return mel_lines_view(array(mel_expired_item()));
    $e = mel_pp_entry($tmdb);
    /** @var MelCmPerson|null $p */
    $p = mel_cm_more_get('person', "tmdb:$tmdb");
    $l = mel_pp_layout($e, $p);
    $pos = mel_pp_pos($e, $l, $sel_state);
    $e['pos'] = $pos;
    mel_pp_put($tmdb, $e);
    mel_log("card: person tmdb:$tmdb: screen, {$l['state']}, {$l['strip']['n']} works, at {$pos['z']} {$pos['p']}, work {$pos['k']}");
    $k = array('t' => $tmdb);
    $acts = array(
        GUI_EVENT_KEY_LEFT => mel_input('person_move', $k + array('d' => 'left')),
        GUI_EVENT_KEY_RIGHT => mel_input('person_move', $k + array('d' => 'right')),
        GUI_EVENT_KEY_UP => mel_input('person_move', $k + array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => mel_input('person_move', $k + array('d' => 'down')),
        GUI_EVENT_KEY_ENTER => mel_input('person_enter', $k),
        GUI_EVENT_KEY_POPUP_MENU => mel_input('person_menu', $k)) + mel_egg_acts();
    $timer = null;
    if ($l['state'] === 'wait')
    {
        $acts[GUI_EVENT_TIMER] = mel_input('person_load', $k);
        $timer = array(GuiTimerDef::delay_ms => $e['tries'] > 0 ? MEL_PP_RETRY_MS : MEL_PP_TICK_MS);
    }
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_pp_window($e, $p, $l, $pos),
            PluginGCompsFolderView::sel_state => mel_pp_state($pos),
            PluginGCompsFolderView::actions => $acts,
            PluginGCompsFolderView::timer => $timer));
}

// --- Keys.

// The place after key $d from $pos: LEFT and RIGHT along the works, DOWN to the whole biography
// and on by its stops, UP back; $pos itself at an edge.
/**
 * @param MelPpLayout $l
 * @param MelPpPos $pos
 * @param string $d
 * @return MelPpPos
 */
function mel_pp_next($l, $pos, $d)
{
    $n = $pos;
    if ($pos['z'] === 'w' && ($d === 'left' || $d === 'right'))
        list($n['k']) = mel_oc_strip_at($l['strip'], $pos['k'] + ($d === 'left' ? -1 : 1));
    else if ($d === 'down' && $l['pages'])
        $n = array_merge($pos, array('z' => 'bio', 'p' => min(count($l['pages']), $pos['p'] + 1)));
    else if ($d === 'up' && $pos['z'] === 'bio')
        $n = array_merge($pos, $pos['p'] > 1 ? array('p' => $pos['p'] - 1) : array('z' => 'w', 'p' => 0));
    return $n;
}

// The changes from $old to $new: along the works the strip, the frame, the titles of both; to the
// biography and back the page moved; its page of the stop.
/**
 * @param MelPpEntry $e
 * @param MelCmPerson $p
 * @param MelPpLayout $l
 * @param MelPpPos $old
 * @param MelPpPos $new
 * @return list<MelGcChange>
 */
function mel_pp_changes($e, $p, $l, $old, $new)
{
    $ch = array();
    if ($new['k'] !== $old['k'])
    {
        $ch = mel_oc_strip_changes($l['strip'], $old['k'], $new['k'], true, true);
        foreach (array($old['k'], $new['k']) as $i)
            $ch[] = mel_gc_change("wn$i", null, GCOMP_TRANSITION_NONE, mel_pp_work_titles($p['works'][$i], $p['known_for'],
                $i === $new['k'], $e['ui']));
    }
    if ($new['z'] !== $old['z'])
        $ch[] = mel_gc_change('page', mel_pp_page_geom($new['z']), GCOMP_TRANSITION_DEFAULT);
    if ($new['p'] !== $old['p'] && $new['p'] > 0)
        $ch[] = mel_gc_change('bio', null, GCOMP_TRANSITION_NONE, mel_pp_bio_page($l, $new['p'], $e['ui'], $p['name']));
    return $ch;
}

// person_move (d), person_enter, person_load (the timer), person_menu of person t. ENTER on a work:
// its own card; on "did not load": asked again (the screen anew, its timer). The timer: the
// person asked (mel_cm_person, MEL_CAT_TOTAL at most), the screen anew with it or its failure. Any
// key while the person is asked, or gone from MelCmMore with its screen open (MEL_CM_TTL past, Back
// deeper than it keeps, the shell did not ask the view again): as the timer, the place of the
// screen kept.
/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_pp_input($in, $control)
{
    $tmdb = mel_pp_id('person:' . mel_catv_in($in, 't'));
    if ($control === 'person_menu')
        return mel_oc_menu(mel_catv_ui_lang());
    if ($tmdb === '')
        return null;
    $e = mel_pp_entry($tmdb);
    /** @var MelCmPerson|null $p */
    $p = mel_cm_more_get('person', "tmdb:$tmdb");
    $l = mel_pp_layout($e, $p);
    $pos = mel_pp_pos($e, $l, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    $e['pos'] = $pos;
    mel_pp_put($tmdb, $e);
    if ($control === 'person_load' || $l['state'] === 'wait')
    {
        if (!$p)
        {
            $r = mel_cm_person($tmdb, MEL_CAT_TOTAL);
            $e['tries'] = $r['err'] !== '' ? $e['tries'] + 1 : 0;
            $e['err'] = $r['err'];
            mel_pp_put($tmdb, $e);
            mel_log("card: person tmdb:$tmdb: " . ($r['err'] !== '' ? "{$r['err']}, try {$e['tries']}" : 'got'));
        }
        return mel_replace_act("person:$tmdb", $e['name']);
    }
    if ($control === 'person_enter')
    {
        if ($l['state'] === 'failed')
        {
            $e['err'] = '';
            $e['tries'] = 0;
            mel_pp_put($tmdb, $e);
            return mel_replace_act("person:$tmdb", $e['name']);
        }
        if (!$p || $pos['z'] !== 'w' || !isset($p['works'][$pos['k']]))
            return null;
        $t = $p['works'][$pos['k']]['tile'];
        mel_log("card: person tmdb:$tmdb: ENTER on work {$pos['k']}, {$t['type']}:" . mel_cm_id($t));
        return mel_card_enter($t, $e['lang']);
    }
    if (!$p || $control !== 'person_move')
        return null;
    $new = mel_pp_next($l, $pos, mel_catv_in($in, 'd'));
    if ($new === $pos)
        return null;
    $e['pos'] = $new;
    mel_pp_put($tmdb, $e);
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => mel_pp_changes($e, $p, $l, $pos, $new),
            ChangeGCompsActionData::num_steps => $new['z'] === $pos['z'] && $new['z'] === 'w' ? MEL_OC_ROW_STEPS : MEL_GC_STEPS,
            ChangeGCompsActionData::sel_state => mel_pp_state($new)));
}
