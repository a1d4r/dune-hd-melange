<?php
// The copies on the disk in the own card: the line "С диска" of a copy of a movie under the buttons (R1, owner
// 20.11.2026) and the line of the copy of an episode in its head (B10) on the UI Kit - the widths by MelKit::w, the
// badges the plates b2 of the list of releases (MelKit::b2); the labels as the other lines of the card (mel_oc_label).
// And the copies of the card: found (mel_oc_lib), the current one (mel_oc_lib_cur, of an episode mel_oc_lib_ep_cur),
// "Копия на диске…" of MENU, the start (mel_oc_lib_start, mel_oc_lib_input).

// The line: its text, its right edge; the disk before it (MEL_OC_IMG disk.png, DISK_W square), a part of the label
// "С диска": DISK_GAP from the picture to the text (its strokes 3 px in from the edge: 11 px seen, as before the kit).
// LIB_GAP between the visible edges of the other parts of the line: the text (by its advance), the glyph of each plate
// b2 (MEL_K_PLATE_PAD in from the edges of the plate: the dark plate hardly shows on the card, the glyph is what is
// seen) and "·" of the tail. The rhythm m: well over the 10 px between the icon and the word inside a glyph ("DV
// Vision"), and the plates side by side, not over one another (LIB_GAP - 2 MEL_K_PLATE_PAD apart). The disk and the
// plates on the centre of the capitals of the text (mel_oc_label_cy).
define('MEL_OC_LIB_PX', MEL_OC_LINE_PX);
define('MEL_OC_LIB_R', 1845);
define('MEL_OC_LIB_GAP', MEL_K_GAP_M);
define('MEL_OC_DISK_W', 24);
define('MEL_OC_DISK_GAP', MEL_K_GAP_XS);
// The alpha of the badges of that line (T1, owner 18.11.2026): their white in the tone of the grey text by them.
define('MEL_OC_LIB_ALPHA', 175);
// The line without words (the head of an episode, B10 A, v8_S_head_A.png): the disk EP_DISK_W (ep_disk.png, the
// white of the mark of a still, dimmed as the badges by it: MEL_OC_LIB_ALPHA), the first plate DISK_GAP + EP_PLATE_GAP
// after it (spec §7.3: "8 + 4"; its glyph MEL_K_PLATE_PAD further), no plates - the text DISK_GAP after it.
define('MEL_OC_EP_DISK_W', 28);
define('MEL_OC_EP_PLATE_GAP', 4);
// The head of an episode with a copy (spec §7.3, v8_S_head_A.png): its description in EP_LIB_LINES lines (not
// MEL_OC_EP_LINES), the line of the copy the next line (its box at EP_LIB_Y, 46 high at MEL_CATV_LINE_PX), to the right
// edge of the text of the head.
define('MEL_OC_EP_LIB_LINES', 2);
define('MEL_OC_EP_LIB_Y', MEL_OC_EP_DESC_Y + MEL_OC_EP_LIB_LINES * MEL_OC_DESC_LH_SER);
define('MEL_OC_EP_LIB_R', MEL_CATV_CARD_X + MEL_CATV_TEXT_W);

// The data of copy $copy for its lines, apart from their layout (the line "С диска", the head of an episode, the
// window of the copies): quiet - the badges without the audio (the resolution, the source, the picture), ids - all of
// them, as the list of releases gives its tags (mel_lib_pf -> mel_badges with the path of the copy ->
// mel_gc_row_badges: the ids of the plates b2 of the UI Kit); ch - the channels as the card of a release has them ("7.1
// / 2.0", mel_audio_text), size - mel_size_str ('' none).
/**
 * @param MelLibCopy $copy
 * @param string $lang
 * @return MelLibParts
 */
function mel_lib_copy_parts($copy, $lang)
{
    $pf = mel_lib_pf($copy);
    $cols = mel_gc_row_badges(mel_badges($pf, null, $copy['path']));
    $quiet = array_values(array_filter(array($cols['res'], $cols['src'], $cols['vis']), 'strlen'));
    $ch = $pf['audioChannels'];
    rsort($ch);
    return array('quiet' => $quiet, 'ids' => $cols['audio'] !== '' ? array_merge($quiet, array($cols['audio'])) : $quiet,
        'ch' => implode(' / ', $ch), 'size' => $copy['size'] > 0 ? mel_size_str($copy['size'], $lang) : '');
}

// The line of copy $copy of a title of $n copies at $x, the label box at $y, $px (mel_oc_label), to the right edge $r
// at most. The movie under the buttons (the defaults), the same whatever the progress (R1, owner 20.11.2026): the disk
// (MEL_OC_DISK_W), $head "С диска" (null: lib_from_disk), the badges (mel_lib_copy_parts: the plates b2 as high as in
// the list of releases, MEL_OC_LIB_ALPHA), the channels and the size as text; $n 2 or more - and the tail "2 копии —
// MENU" (lib_copies, owner 20.11.2026). $head '': the head of an episode (B10 A): the disk EP_DISK_W, no words, no tail.
// Wider than $r: the tail of the copies out first, then the size, then the channels, then the audio (owner 19.11.2026);
// the resolution and the picture stay.
/**
 * @param string $lang
 * @param MelLibCopy $copy
 * @param int $x
 * @param int $n
 * @param int $y
 * @param int $px
 * @param int $r
 * @param string|null $head
 * @return list<array<string, mixed>>
 */
function mel_oc_lib_line($lang, $copy, $x, $n = 1, $y = MEL_OC_LINE_Y, $px = MEL_OC_LIB_PX, $r = MEL_OC_LIB_R, $head = null)
{
    $p = mel_lib_copy_parts($copy, $lang);
    $head = $head !== null ? $head : mel_tr($lang, 'lib_from_disk');
    $more = $n > 1 && $head !== '' ? str_replace('{n}', strval($n), mel_gc_plural($lang, $n, 'lib_copies')) : '';
    $cy = mel_oc_label_cy($y, $px);
    $dw = $head !== '' ? MEL_OC_DISK_W : MEL_OC_EP_DISK_W;
    $tx = $x + $dw + MEL_OC_DISK_GAP;
    $d = array();
    foreach (array(array($p['ch'], $p['size'], $more, $p['ids']), array($p['ch'], $p['size'], '', $p['ids']),
        array($p['ch'], '', '', $p['ids']), array('', '', '', $p['ids']), array('', '', '', $p['quiet'])) as $v)
    {
        list($d, $w) = mel_oc_lib_parts($head, $v[3], implode(' · ', array_filter(array($v[0], $v[1], $v[2]), 'strlen')),
            $tx, $y, $px, $cy);
        if ($tx + $w <= $r)
            break;
    }
    $disk = mel_gc_image(mel_gc_at($dw, $dw, $x, (int) round($cy - $dw / 2)), MEL_OC_IMG . ($head !== '' ? 'disk.png' :
        'ep_disk.png'));
    if ($head === '')
        $disk[GComponentDef::specific_def][GCompImageDef::alpha] = MEL_OC_LIB_ALPHA;
    return array_merge(array($disk), $d);
}

// The parts of the line of mel_oc_lib_line at $x, the labels at $y, $px, the plates centred at $cy (mel_oc_label_cy):
// the text $head, the badges $ids, " · " and the text $tail (after $head without badges), LIB_GAP apart -> array(the
// components, the width). $head '': the plates EP_PLATE_GAP from $x, the tail after them; no plates - the tail at $x,
// no " · " before it.
/**
 * @param string $head
 * @param list<string> $ids
 * @param string $tail
 * @param int $x
 * @param int $y
 * @param int $px
 * @param float $cy
 * @return array{list<array<string, mixed>>, int}
 */
function mel_oc_lib_parts($head, $ids, $tail, $x, $y, $px, $cy)
{
    if (!$ids && $tail !== '' && $head !== '')
    {
        $head .= " · $tail";
        $tail = '';
    }
    $d = array();
    $w = 0;
    $gap = MEL_OC_EP_PLATE_GAP;
    if ($head !== '')
    {
        $w = MelKit::w($head, $px);
        $d[] = mel_oc_label($x, $y, $w + $px, $head, $px, MEL_CATV_TEXT_COLOR);
        $gap = MEL_OC_LIB_GAP - MEL_K_PLATE_PAD;
    }
    if ($ids)
    {
        // The plates b2 side by side, their glyphs LIB_GAP apart, dimmed whole: the pictures by alpha, the rects of
        // the plates by the alpha of their colour.
        $items = array();
        foreach ($ids as $id)
            $items[] = MelKit::b2($id, 0, MEL_K_PLATE_H / 2);
        $row = MelKit::row($items, MEL_OC_LIB_GAP - 2 * MEL_K_PLATE_PAD, 0, MEL_K_PLATE_H / 2, 'lib');
        $pw = $row['x1'] - $row['x0'];
        $plates = $row['defs'];
        foreach ($plates as $i => $p)
        {
            if ($p[GComponentDef::kind] === GCOMPONENT_IMAGE)
                $plates[$i][GComponentDef::specific_def][GCompImageDef::alpha] = MEL_OC_LIB_ALPHA;
            else if ($p[GComponentDef::kind] === GCOMPONENT_RECT)
            {
                $c = (string) $p[GComponentDef::specific_def][GCompRectDef::color];
                $plates[$i][GComponentDef::specific_def][GCompRectDef::color] = sprintf('#%02X',
                    intval(hexdec(substr($c, 1, 2)) * MEL_OC_LIB_ALPHA / 255)) . substr($c, 3);
            }
        }
        // The middle of the plates at the pixel under $cy: the capitals of 4K and Vision sit 0.5 px under the middle
        // of their pictures, those of Atmos 1 px over it; so all of them are within 1 px of the capitals of the text.
        $d[] = mel_gc_panel('lib', mel_gc_at($pw, MEL_K_PLATE_H, $x + $w + $gap,
            (int) ceil($cy) - intval(MEL_K_PLATE_H / 2)), $plates);
        $w += $gap + $pw;
        $gap = MEL_OC_LIB_GAP - MEL_K_PLATE_PAD;
    }
    if ($tail !== '')
    {
        if ($w > 0)
            $tail = "· $tail";
        else
            $gap = 0;
        $tw = MelKit::w($tail, $px);
        $d[] = mel_oc_label($x + $w + $gap, $y, $tw + $px, $tail, $px, MEL_CATV_TEXT_COLOR);
        $w += $gap + $tw;
    }
    return array($d, $w);
}

// The copies on the disk of card $c (library.php): the title found in the media library by the movie_str of its
// start (mel_oc_watch_in, mel_lib_title: the same as play_action looks for it by) -> array(the copies of a movie on
// the shares mounted now (mel_lib_copies; no share touched), H: the card of its history - of the Dune, else of the
// index when there are copies, Y: the title in the index ('' none), the copies of the episodes of a series and the
// copy of each OK plays, the files of its episodes for the history; mel_oc_lib_series). A movie: no episodes.
/**
 * @param MelOcCard $c
 * @return array{list<MelLibCopy>, string, array<string, list<MelLibCopy>>, string, array<string, string>, array<string, array{int, int}|string>}
 */
function mel_oc_lib($c)
{
    if ($c['tile']['type'] === 'series')
        return mel_oc_lib_series($c);
    if ($c['tile']['type'] !== 'movie')
        return array(array(), $c['dune'], array(), '', array(), array());
    $in = mel_oc_watch_in($c);
    $y = mel_lib_mid(mel_lib_title((array) json_decode(strval($in->movie_str), true), false));
    if ($y === '')
        return array(array(), $c['dune'], array(), '', array(), array());
    $lib = mel_lib_copies($y);
    mel_log("card: own movie:" . mel_cm_id($c['tile']) . ": library $y, " . count($lib) . ' copies (' .
        count(MelLib::$by_mid[$y]) . ' in index)');
    return array($lib, $lib ? mel_lib_hid($c['dune'], $y) : $c['dune'], array(), $y, array(), array());
}

// The series of card $c in the media library (mel_oc_lib): Y of the movie_str of its start; its episodes on the disk
// and the files of them (mel_oc_lib_eps); H by Y when there are copies, else the card of the Dune; of each episode the
// copy OK plays (mel_lib_ep_pick with $auto, as the line of the head and "· сейчас" of the window show it: of the
// history in memory, mel_lib_ep_ctx; the key of a record - its first mirror, as mel_lib_start takes it). The keys "s:e"
// of the copies are of the card, of the history and of the request at once (the invariant, mel_lib_ep_same).
// -> array(none, H, "s:e" => copies, Y, "s:e" => the id of a copy, hash => array(q, e) | 'skip').
/**
 * @param MelOcCard $c
 * @return array{list<MelLibCopy>, string, array<string, list<MelLibCopy>>, string, array<string, string>, array<string, array{int, int}|string>}
 */
function mel_oc_lib_series($c)
{
    $in = mel_oc_watch_in($c);
    $y = mel_lib_mid(mel_lib_title((array) json_decode(strval($in->movie_str), true), true));
    list($eps, $fix) = mel_oc_lib_eps($c, $y);
    $hid = $eps ? mel_lib_hid($c['dune'], $y) : $c['dune'];
    $cur = array();
    $seasons = array();
    list($recs, $f) = $eps ? mel_lib_ep_ctx($hid, $y) : array(array(), array());
    foreach ($eps as $se => $copies)
    {
        list($first) = mel_lib_ep_pick($copies, isset($recs[$se]) ? $recs[$se] : '', $f, true);
        $of = mel_lib_copies_of($copies, $first);
        $cur[$se] = $of ? $of[0]['cid'] : $copies[0]['cid'];
        $seasons[$copies[0]['s']] = 1;
    }
    $all = array();
    foreach ($y !== '' && isset(MelLib::$eps[$y]) ? MelLib::$eps[$y] : array() as $r)
        $all["{$r['s']}:{$r['e']}"] = 1;
    // No line with no series in the library at all (as a movie with none).
    if ($y !== '' || MelLib::$eps)
        mel_log('card: own series:' . mel_cm_id($c['tile']) . ': library ' . ($y !== '' ? $y : 'none') . ', ' .
            count($eps) . ' episodes on disk in ' . count($seasons) . ' seasons (' . count($all) . ' in index)');
    return array(array(), $hid, $eps, $y, $cur, $fix);
}

// The episodes of series $y of the index for card $c, by the invariant of the numberings (mel_lib_ep_same, of the meta
// of the card as its movie_str has it: season_numbers, cin; mel_oc_watch_in): array(their copies now "s:e" =>
// copies, mel_lib_ep_copies - the season of the index is that of the card; the files of them for the history,
// hash => array(q, e) - q the season of the history (mel_cm_req_s, the same by the invariant) | 'skip', for
// mel_wh_series and mel_lib_place). No meta or no Y: none.
/**
 * @param MelOcCard $c
 * @param string $y
 * @return array{array<string, list<MelLibCopy>>, array<string, array{int, int}|string>}
 */
function mel_oc_lib_eps($c, $y)
{
    $m = $c['meta'];
    if (!$m || $y === '')
        return array(array(), array());
    list($eps, $fix) = mel_lib_ep_same(mel_lib_ep_copies($y), mel_lib_ep_hashes($y), $m['season_numbers'], $m['cin']);
    foreach ($fix as $h => $se)
    {
        if (is_array($se))
            $fix[$h] = array(mel_cm_req_s($m, $se[0]), $se[1]);
    }
    return array($eps, $fix);
}

// The copy of copies $lib of the latest record of a copy of the card of history $hid (mel_wh_lib_last), there; null
// none (no record, or its copy not among them): no copy is the current one then.
/**
 * @param list<MelLibCopy> $lib
 * @param string $hid
 * @return MelLibCopy|null
 */
function mel_oc_lib_cur($lib, $hid)
{
    $rec = mel_wh_lib_last($hid);
    return $rec ? mel_lib_copy_by_key($lib, $rec['key']) : null;
}

// The copy of episode $e of season $s of the card of layout $l its line shows (the head, B10): the one OK plays
// (lib_cur), else the first of its copies; null - the episode has none.
/**
 * @param MelOcNav $l
 * @param int $s
 * @param int $e
 * @return MelLibCopy|null
 */
function mel_oc_lib_ep_cur($l, $s, $e)
{
    $se = "$s:$e";
    if (empty($l['lib_eps'][$se]))
        return null;
    $copies = $l['lib_eps'][$se];
    foreach ($copies as $cp)
    {
        if (isset($l['lib_cur'][$se]) && $cp['cid'] === $l['lib_cur'][$se])
            return $cp;
    }
    return $copies[0];
}

// The episodes of season $s with copies on the disk of the copies of episodes $lib_eps (nav lib_eps: "s:e" of the
// card) - the count of the season (mel_oc_count_text).
/**
 * @param array<string, list<MelLibCopy>> $lib_eps
 * @param int $s
 * @return int
 */
function mel_oc_lib_on_disk($lib_eps, $s)
{
    $n = 0;
    foreach (array_keys($lib_eps) as $se)
    {
        if (strpos($se, "$s:") === 0)
            $n++;
    }
    return $n;
}

// "Копия на диске…" of MENU of a movie of card $c ($key) at place $pos of layout $l with two copies on the disk or more:
// card_lib_copies (the menu of the copies, mel_oc_lib_input); none on a strip of posters or of the cast.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $pos
 * @return list<array<string, mixed>>
 */
function mel_oc_lib_menu_items($key, $c, $l, $pos)
{
    if (count($l['lib']) < 2 || mel_oc_strip_on($l, $pos) !== '')
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($c['ui'], 'lib_copy_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
        GuiMenuItemDef::action => mel_input('card_lib_copies', array('c' => $key))));
}

// The start of a copy on the disk of the movie of card $c ($key) of layout $l (C2-C4 of the spec): its copies read
// anew (a share may be gone since the screen; mel_lib_copies), the copy and the place by the history of the card
// (mel_lib_place). The copy of id $k chosen in the menu of the copies ('' - "Смотреть": the copy of the record, of
// its mirrors the first there; none - the copies by mel_lib_start: one plays, several - their menu back to
// card_lib_copy, none there - mel_lib_gone).
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param string $k
 * @return MelAction|null
 */
function mel_oc_lib_start($key, $c, $l, $k = '')
{
    $mv = mel_movie(mel_oc_watch_in($c));
    if (!$mv || !$l['lib'])
        return null;
    $mv['dune_id'] = mel_lib_hid($mv['dune_id'], $l['hid']);
    $copies = mel_lib_copies($l['lib'][0]['mid']);
    mel_log("card: own $key: watch a copy on the disk");
    list($first, $first_pos, $pos) = mel_lib_place($l['hid'], $copies, $k);
    MelOc::$cards[$key]['played'] = true;
    $go = mel_lib_start($copies, $first, $first_pos, $pos, false);
    if (isset($go['copy']))
        return mel_lib_play($mv, $go, $c['ui']);
    return isset($go['pick']) ? mel_lib_pick_menu('card_lib_copy', array('c' => $key, 'from' => 'first'), $go['pick'], '',
        $c['ui']) : mel_lib_gone($go['gone']);
}

// card_lib_copies (MENU "Копия на диске…") and card_lib_copy (k: the id of the copy of the menu of the copies; from:
// first - of "Смотреть", menu - of MENU) of the movie of card $c ($key) of layout $l. The menu: the copies read anew, the
// one of the latest record (mel_oc_lib_cur) "· сейчас" and selected, no record - none of them, the first selected;
// none left - lib_gone. A copy: mel_oc_lib_start.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_oc_lib_input($key, $c, $l, $in, $control)
{
    if (!$l['lib'])
        return null;
    if ($control === 'card_lib_copy')
    {
        $k = mel_catv_in($in, 'k');
        if (!mel_lib_cid_ok($k))
            return null;
        mel_log("card: own $key: copy $k of the menu (" . mel_catv_in($in, 'from') . ')');
        return mel_oc_lib_start($key, $c, $l, $k);
    }
    $copies = mel_lib_copies($l['lib'][0]['mid']);
    if (!$copies)
        return mel_lib_gone('no');
    $cur = mel_oc_lib_cur($copies, $l['hid']);
    return mel_lib_pick_menu('card_lib_copy', array('c' => $key, 'from' => 'menu'), $copies, $cur ? $cur['cid'] : '',
        $c['ui']);
}
