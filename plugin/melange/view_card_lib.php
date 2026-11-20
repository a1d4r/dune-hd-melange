<?php
// The copies on the disk in the own card: the line "С диска" of a copy of a movie over the buttons (variant Б, owner
// 20.11.2026) and the line of the copy of an episode in its head (B10) on the UI Kit - the widths by MelKit::w, the
// badges the plates b2 of the list of releases (MelKit::b2); the labels as the other lines of the card (mel_oc_label).
// And the copies of the card: found (mel_oc_lib), the current one (mel_oc_lib_cur, of an episode mel_oc_lib_ep_cur),
// "Копия на диске…" of MENU, the start (mel_oc_lib_start, mel_oc_lib_input). The window of the copies W2 over the card
// (mel_lib_win_open, its keys mel_lib_win_input; the paths of its lines mel_lib_win_paths).

// The line of a movie: the y of its label box, its text, its right edge (that of the description, as the head of an
// episode: MEL_OC_EP_LIB_R); the disk before it (MEL_OC_IMG disk.png, DISK_W square), a part of the label
// "С диска": DISK_GAP from the picture to the text (its strokes 3 px in from the edge: 11 px seen, as before the kit).
// LIB_GAP between the visible edges of the other parts of the line: the text (by its advance), the glyph of each plate
// b2 (MEL_K_PLATE_PAD in from the edges of the plate: the dark plate hardly shows on the card, the glyph is what is
// seen) and "·" of the tail. The rhythm m: well over the 10 px between the icon and the word inside a glyph ("DV
// Vision"), and the plates side by side, not over one another (LIB_GAP - 2 MEL_K_PLATE_PAD apart). The disk and the
// plates on the centre of the capitals of the text (mel_oc_label_cy). The line in place of the 3rd line of the
// description under the director (variant Б, owner 20.11.2026): its capitals on those of that line (532).
define('MEL_OC_LIB_Y', 512);
define('MEL_OC_LIB_PX', MEL_OC_LINE_PX);
define('MEL_OC_LIB_R', MEL_CATV_CARD_X + MEL_CATV_TEXT_W);
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

// The data of copy $copy for its lines, apart from their layout (the line "С диска", the head of an episode, the
// window of the copies): quiet - the badges without the audio (the resolution, the source, the picture), ids - all of
// them, as the list of releases gives its tags (mel_lib_pf -> mel_badges with the path of the copy ->
// mel_gc_row_badges: the ids of the plates b2 of the UI Kit); ch - the id of the plate b2 of its channels, the best of
// them (owner 20.11.2026, spec §15: a plate in the own library, the list of releases writes them): ch-71 of 7.1 / 2.0
// (k_glyph_ch-71) - each layout of the tags of the indexer has its glyph in the kit (9.1 7.1 6.1 5.1 4.1 4.0 2.1 2.0
// 1.0); another one (5.0: not a tag of the indexer) none, not a plate of another layout and not text by the plates;
// size - mel_size_str ('' none).
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
    $ch = $ch ? 'ch-' . str_replace('.', '', $ch[0]) : '';
    $pics = mel_k_pic();
    return array('quiet' => $quiet, 'ids' => $cols['audio'] !== '' ? array_merge($quiet, array($cols['audio'])) : $quiet,
        'ch' => isset($pics["k_glyph_$ch"]) ? $ch : '', 'size' => $copy['size'] > 0 ? mel_size_str($copy['size'], $lang) : '');
}

// The path of a line of the window of the copies (W2, spec §8.2): its text WIN_PATH_PX, its width WIN_PATH_W at most.
define('MEL_LIB_WIN_PATH_PX', MEL_K_PX_CAPTION);
define('MEL_LIB_WIN_PATH_W', 1000);

// The paths of the lines of the window of copies $copies (of the episodes of a series: $series) by cid: their tails
// (mel_lib_tails) cut in the middle to the width of the window (mel_lib_cut_mid).
/**
 * @param list<MelLibCopy> $copies
 * @param bool $series
 * @return array<string, string>
 */
function mel_lib_win_paths($copies, $series)
{
    $out = array();
    foreach (mel_lib_tails($copies, $series) as $cid => $t)
        $out[$cid] = mel_lib_cut_mid($t, MEL_LIB_WIN_PATH_PX, MEL_LIB_WIN_PATH_W);
    return $out;
}

// Path $s at $px (the font of the kit, MelKit::w) wider than $w cut in the middle: its start up to the first "/" (its
// folder) kept, "…", then of its end as much as fits (mock_library_v4.py cut_mid); no "/" - "…" and the end. A start
// to the first "/" wider than half of $w (with "…"): no start kept by "/" - of the start as much as fits half of $w, "…",
// of the end the rest. Never wider than $w. Not wider: as it is.
/**
 * @param string $s
 * @param int $px
 * @param int $w
 * @return string
 */
function mel_lib_cut_mid($s, $px, $w)
{
    $s = preg_match('//u', $s) ? $s : mel_utf8($s);
    if (MelKit::w($s, $px) <= $w)
        return $s;
    $i = strpos($s, '/');
    $head = $i === false ? '' : substr($s, 0, $i + 1);
    // The width of a line is the sum of those of its glyphs (MelKit::w): the start grows from its first one, the end
    // from its last one.
    if (MelKit::w($head . '…', $px) * 2 > $w)
    {
        $half = intval(($w - MelKit::w('…', $px)) / 2);
        $head = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: array() as $ch)
        {
            $cw = MelKit::w($ch, $px);
            if ($cw > $half)
                break;
            $half -= $cw;
            $head .= $ch;
        }
    }
    $left = $w - MelKit::w($head . '…', $px);
    $chars = preg_split('//u', strval(substr($s, strlen($head))), -1, PREG_SPLIT_NO_EMPTY) ?: array();
    $tail = '';
    for ($k = count($chars) - 1; $k >= 0; $k--)
    {
        $cw = MelKit::w($chars[$k], $px);
        if ($cw > $left)
            break;
        $left -= $cw;
        $tail = $chars[$k] . $tail;
    }
    return $head . '…' . $tail;
}

// The line of copy $copy of a title of $n copies at $x. The movie ($ep false) over the buttons, the line of its history
// under it (variant Б, owner 20.11.2026), the same whatever the progress: its label box at MEL_OC_LIB_Y, LIB_PX, to
// the right edge LIB_R at most; the disk (MEL_OC_DISK_W), "С диска" (lib_from_disk), the badges (mel_lib_copy_parts:
// the plates b2 as high as in the list of releases, MEL_OC_LIB_ALPHA), the plate of the channels after them, the size as text; $n 2 or more - and the tail
// "2 копии — MENU" (lib_copies, owner 20.11.2026). The head of an episode ($ep true, B10 A): the box at MEL_OC_EP_LIB_Y,
// MEL_CATV_LINE_PX, to EP_LIB_R; the disk EP_DISK_W dimmed as the badges, no words, no tail.
// Wider than the edge: the parts out one by one in the order of $drop - the size, the tail of the copies (owner
// 20.11.2026: the tail stays longer than the size), the plate of the channels, the audio (owner 19.11.2026); the
// resolution and the picture stay. The head of an episode has no tail: the size, the channels, the audio.
/**
 * @param string $lang
 * @param MelLibCopy $copy
 * @param int $x
 * @param int $n
 * @param bool $ep
 * @return list<array<string, mixed>>
 */
function mel_oc_lib_line($lang, $copy, $x, $n, $ep = false)
{
    $p = mel_lib_copy_parts($copy, $lang);
    if ($ep)
        list($y, $px, $r, $dw, $head, $more) = array(MEL_OC_EP_LIB_Y, MEL_CATV_LINE_PX, MEL_OC_EP_LIB_R, MEL_OC_EP_DISK_W,
            '', '');
    else
        list($y, $px, $r, $dw, $head, $more) = array(MEL_OC_LIB_Y, MEL_OC_LIB_PX, MEL_OC_LIB_R, MEL_OC_DISK_W,
            mel_tr($lang, 'lib_from_disk'), $n > 1 ? str_replace('{n}', strval($n), mel_gc_plural($lang, $n, 'lib_copies')) : '');
    $cy = mel_oc_label_cy($y, $px);
    $tx = $x + $dw + MEL_OC_DISK_GAP;
    $on = array('more' => $more !== '', 'size' => true, 'ch' => true, 'audio' => true);
    $d = array();
    foreach (array('', 'size', 'more', 'ch', 'audio') as $drop)
    {
        if ($drop !== '')
            $on[$drop] = false;
        list($d, $w) = mel_oc_lib_parts($head, $p, $on, $more, $tx, $y, $px);
        if ($tx + $w <= $r)
            break;
    }
    $disk = mel_gc_image(mel_gc_at($dw, $dw, $x, (int) round($cy - $dw / 2)), MEL_OC_IMG . ($ep ? 'ep_disk.png' :
        'disk.png'));
    if ($ep)
        $disk[GComponentDef::specific_def][GCompImageDef::alpha] = MEL_OC_LIB_ALPHA;
    return array_merge(array($disk), $d);
}

// The parts of the line of mel_oc_lib_line at $x, the labels at $y, $px, the plates centred on their capitals
// (mel_oc_label_cy), of the data $p of its copy those $on keeps (more, size, ch, audio): the text $head, the plates
// (mel_lib_plate_ids: the badges, the plate of the channels after them), " · " and the tail (the size, $more; after $head without badges),
// LIB_GAP apart -> array(the components, the width). $head '' (the head of an episode): the plates EP_PLATE_GAP from $x, the tail after them; no
// plates - the tail at $x, no " · " before it.
/**
 * @param string $head
 * @param MelLibParts $p
 * @param array{more: bool, size: bool, ch: bool, audio: bool} $on
 * @param string $more
 * @param int $x
 * @param int $y
 * @param int $px
 * @return array{list<array<string, mixed>>, int}
 */
function mel_oc_lib_parts($head, $p, $on, $more, $x, $y, $px)
{
    $cy = mel_oc_label_cy($y, $px);
    $ids = mel_lib_plate_ids($p, $on['audio'], $on['ch']);
    $tail = implode(' · ', array_filter(array($on['size'] ? $p['size'] : '', $on['more'] ? $more : ''), 'strlen'));
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
        // The plates b2 side by side, their glyphs LIB_GAP apart, dimmed whole.
        list($plates, $pw) = mel_oc_lib_plates($ids, MEL_OC_LIB_ALPHA, MEL_OC_LIB_GAP - 2 * MEL_K_PLATE_PAD);
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

// The ids of the plates b2 of the data $p of a copy (mel_lib_copy_parts) in their order, one for all its lines (the line
// "С диска", the head of an episode, the window of the copies): the badges (without the audio: not $audio), then the
// plate of the channels after the audio (spec §15; none: not $ch).
/**
 * @param MelLibParts $p
 * @param bool $audio
 * @param bool $ch
 * @return list<string>
 */
function mel_lib_plate_ids($p, $audio = true, $ch = true)
{
    $ids = $audio ? $p['ids'] : $p['quiet'];
    if ($ch && $p['ch'] !== '')
        $ids[] = $p['ch'];
    return $ids;
}

// The plates b2 of $ids side by side $gap apart (between the plates), their centre at MEL_K_PLATE_H / 2 and their left
// at 0 (the panel of the caller puts them), dimmed whole by $alpha (255: as they are; else the pictures by alpha, the
// rects of the plates by the alpha of their colour) -> array(the components, the width).
/**
 * @param list<string> $ids
 * @param int $alpha
 * @param int $gap
 * @return array{list<MelGcComp>, int}
 */
function mel_oc_lib_plates($ids, $alpha, $gap)
{
    $items = array();
    foreach ($ids as $id)
        $items[] = MelKit::b2($id, 0, MEL_K_PLATE_H / 2);
    $row = MelKit::row($items, $gap, 0, MEL_K_PLATE_H / 2, 'lib');
    $plates = $row['defs'];
    foreach ($alpha < 255 ? $plates : array() as $i => $def)
    {
        if ($def[GComponentDef::kind] === GCOMPONENT_IMAGE)
            $plates[$i][GComponentDef::specific_def][GCompImageDef::alpha] = $alpha;
        else if ($def[GComponentDef::kind] === GCOMPONENT_RECT)
        {
            $c = (string) $def[GComponentDef::specific_def][GCompRectDef::color];
            $plates[$i][GComponentDef::specific_def][GCompRectDef::color] = sprintf('#%02X',
                intval(hexdec(substr($c, 1, 2)) * $alpha / 255)) . substr($c, 3);
        }
    }
    /** @var list<MelGcComp> $plates */
    return array($plates, $row['x1'] - $row['x0']);
}

// The copies on the disk of card $c (library.php): the title found in the media library by the movie_str of its
// start (mel_oc_watch_in, mel_lib_title: the same as play_action looks for it by), Y found once for a movie and a
// series -> MelOcLib (the keys of nav, and fix): lib - the copies of a movie on the shares mounted now (mel_lib_copies;
// no share touched), hid - H, the card of its history: of the Dune, else of the index when there are copies, lib_y - Y,
// the title in the index ('' none); of a series lib_eps, lib_cur - the copies of its episodes and the copy of each OK
// plays, fix - the files of its episodes for the history (mel_oc_lib_series). A movie: no episodes; another type, no Y,
// a series with no series in the index (not looked for): none of it.
/**
 * @param MelOcCard $c
 * @return MelOcLib
 */
function mel_oc_lib($c)
{
    $res = array('lib' => array(), 'hid' => $c['dune'], 'lib_eps' => array(), 'lib_y' => '', 'lib_cur' => array(),
        'fix' => array());
    $series = $c['tile']['type'] === 'series';
    if (!$series && $c['tile']['type'] !== 'movie')
        return $res;
    // No series in the index at all: a series has nothing to look for (spec §10: no movie_str, no Y).
    if ($series)
    {
        mel_lib_index();
        if (!MelLib::$eps)
            return $res;
    }
    $in = mel_oc_watch_in($c);
    $res['lib_y'] = $y = mel_lib_mid(mel_lib_title((array) json_decode(strval($in->movie_str), true), $series));
    if ($series)
        return mel_oc_lib_series($c, $res);
    if ($y === '')
        return $res;
    $res['lib'] = mel_lib_copies($y);
    mel_log("card: own movie:" . mel_cm_id($c['tile']) . ": library $y, " . count($res['lib']) . ' copies (' .
        count(MelLib::$by_mid[$y]) . ' in index)');
    if ($res['lib'])
        $res['hid'] = mel_lib_hid($c['dune'], $y);
    return $res;
}

// The series of card $c in the media library (mel_oc_lib, $res of it with Y): its episodes on the disk and the files
// of them (mel_oc_lib_eps); H by Y when there are copies, else the card of the Dune; of each episode the copy OK plays
// (mel_lib_ep_cid with $auto, as the line of the head and "· сейчас" of the window show it: of the history in memory,
// mel_lib_ep_ctx; of the mirrors of a record the copy of F, as the playlist and the start take it). The keys "s:e" of
// the copies are of the card, of the history and of the request at once (the invariant, mel_lib_ep_same).
/**
 * @param MelOcCard $c
 * @param MelOcLib $res
 * @return MelOcLib
 */
function mel_oc_lib_series($c, $res)
{
    $y = $res['lib_y'];
    list($eps, $fix) = mel_oc_lib_eps($c, $y);
    $hid = $eps ? mel_lib_hid($c['dune'], $y) : $c['dune'];
    $cur = array();
    $seasons = array();
    $ctx = $eps ? mel_lib_ep_ctx($hid, $y) : array(array(), array());
    foreach ($eps as $se => $copies)
    {
        list($cur[$se]) = mel_lib_ep_cid($copies, $ctx, $se, true);
        $seasons[$copies[0]['s']] = 1;
    }
    $all = array();
    foreach ($y !== '' && isset(MelLib::$eps[$y]) ? MelLib::$eps[$y] : array() as $r)
        $all["{$r['s']}:{$r['e']}"] = 1;
    // No line with no series in the library at all (as a movie with none).
    if ($y !== '' || MelLib::$eps)
        mel_log('card: own series:' . mel_cm_id($c['tile']) . ': library ' . ($y !== '' ? $y : 'none') . ', ' .
            count($eps) . ' episodes on disk in ' . count($seasons) . ' seasons (' . count($all) . ' in index)');
    return array('hid' => $hid, 'lib_eps' => $eps, 'lib_cur' => $cur, 'fix' => $fix) + $res;
}

// The episodes of series $y of the index for card $c, by the invariant of the numberings (mel_lib_ep_same): the
// seasons of the meta of the card, season => the set of the numbers of its episodes (not 1..the last one: a season of
// E9-E16 has no E1-E8), of the episodes not out yet and of no date too (soon: a file of one on the disk has its mark on
// its still, though season_numbers leaves it out, mel_cm_out), and only a season the request asks as it is
// (mel_cm_req_s = s, checked here: a season numbered on by TMDB is none). -> array(their copies
// now "s:e" => copies, mel_lib_ep_copies - the season of the index is that of the card; the files of them for the
// history, hash => array(q, e) | 'skip', for mel_wh_series and mel_lib_place - q = s by the invariant, as they are). No
// meta or no Y: none.
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
    $seasons = array();
    foreach ($m['seasons'] as $s => $eps)
    {
        if (!$eps || mel_cm_req_s($m, $s) !== $s)
            continue;
        foreach ($eps as $ep)
            $seasons[$s][$ep['e']] = true;
    }
    return mel_lib_ep_same(mel_lib_ep_copies($y), mel_lib_ep_hashes($y), $seasons, $m['cin']);
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

// --- The window of the copies W2 (spec §8.1, §8.3; frames v8_W2_*, v9_W2_plate.png of the kitchen, geometry
// mock_library_v4.py window(), mode P1) over the own card, in the overlay of its screen (kit_ovl.php): the shade, the
// window WIN_W wide at WIN_X, its height by its lines (at most WIN_VIS shown), WIN_UP over the middle of the screen; the
// title (lib_win_title, MEL_K_PX_STATUS_TITLE, text 1) its baseline WIN_TITLE_BASE under the top; the lines WIN_ROW high
// from WIN_HEAD, each of a copy: its plates b2 (mel_lib_copy_parts, mel_lib_plate_ids: the plate of the channels after
// the audio; MEL_K_PLATE_GAP apart, not dimmed), "· сейчас" of the current one (lib_win_now) WIN_GAP after them and the
// size on the right, MEL_K_PX_ROW, text 2 (owner 20.11.2026: 30 px); under them the path P1 (mel_lib_win_paths,
// MEL_LIB_WIN_PATH_PX, text 3) its baseline WIN_PATH_BASE under the top of the line; the focus - the plate row_on
// (WIN_ON_X in from the sides, WIN_ROW - WIN_ON_GAP high), its texts on_accent; under the line of the current one the
// bar of its progress (WIN_BAR_X in, WIN_BAR_H high, WIN_BAR_Y under the top of the line); the hint under the lines
// (MEL_K_PX_CAPTION, text 3) its baseline WIN_FOOT_BASE under them, the window WIN_FOOT lower. All from WIN_PAD in.
define('MEL_LIB_WIN_X', 420);
define('MEL_LIB_WIN_W', 1080);
define('MEL_LIB_WIN_UP', 40);
define('MEL_LIB_WIN_PAD', 40);
define('MEL_LIB_WIN_HEAD', 88);
define('MEL_LIB_WIN_TITLE_BASE', 58);
define('MEL_LIB_WIN_ROW', 116);
define('MEL_LIB_WIN_VIS', 5);
define('MEL_LIB_WIN_LINE_Y', 41);
define('MEL_LIB_WIN_PATH_BASE', 88);
define('MEL_LIB_WIN_GAP', MEL_OC_LIB_GAP - MEL_K_PLATE_PAD);
define('MEL_LIB_WIN_ON_X', 16);
define('MEL_LIB_WIN_ON_GAP', 8);
define('MEL_LIB_WIN_BAR_X', 28);
define('MEL_LIB_WIN_BAR_Y', 102);
define('MEL_LIB_WIN_BAR_H', 4);
define('MEL_LIB_WIN_FOOT', 70);
define('MEL_LIB_WIN_FOOT_BASE', 44);
// The prefix of the ids of the window: lw_dim, lw_win, lw_list, lw_rows, lw_r<i> (a line).
define('MEL_LIB_WIN', 'lw');

// The copy "· сейчас" of the window of the copies $copies of card $key (spec §8.3): of a movie the copy of the latest
// record of a copy of the card (mel_oc_lib_cur); of episode $s, $e the copy OK plays (nav lib_cur, решение лида 5 (а):
// of the folder it plays from), unless the series was never watched from the disk (no record of a copy of the card) -
// none then. Not among $copies, no card shown: '' (none; the cursor on the first line).
/**
 * @param string $key
 * @param list<MelLibCopy> $copies
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_lib_win_cur($key, $copies, $s = -1, $e = -1)
{
    if (!isset(MelOc::$cards[$key]['nav']))
        return '';
    $l = MelOc::$cards[$key]['nav'];
    if ($e < 1)
    {
        $cp = mel_oc_lib_cur($copies, $l['hid']);
        return $cp ? $cp['cid'] : '';
    }
    $cid = isset($l['lib_cur']["$s:$e"]) && mel_wh_lib_last($l['hid']) ? $l['lib_cur']["$s:$e"] : '';
    foreach ($copies as $cp)
    {
        if ($cp['cid'] === $cid)
            return $cid;
    }
    return '';
}

// Open over the screen of card $key (its record mel_oc_scr: only so) the window of the copies $copies (2+, the best
// first) of its movie, of episode $s, $e of its series ($e >= 1), in mode $mode (first: the first "Смотреть"; menu: MENU
// "Копия на диске…"); $cur the copy "· сейчас" (mel_lib_win_cur; '' none): the cursor on it, else on the first line.
// The bar of the line "сейчас" and the hint: of an episode the progress of its still (nav prog, mel_oc_prog), the hint
// lib_win_series; of a movie the progress of the line of its history (mel_cp_badge, as mel_oc_hist), the hint of mode
// first lib_win_first, of menu the place the start of a copy takes (mel_lib_place, as mel_oc_lib_start): "Продолжит с
// того же места — 0:47:28" (lib_win_place), none (not begun or watched) lib_win_first. No record of the screen: null.
/**
 * @param string $key
 * @param list<MelLibCopy> $copies
 * @param string $cur
 * @param string $mode
 * @param int $s
 * @param int $e
 * @return MelAction|null
 */
function mel_lib_win_open($key, $copies, $cur, $mode, $s = -1, $e = -1)
{
    $scr = mel_oc_scr($key);
    if (!$scr || !$copies || !isset(MelOc::$cards[$key]['nav']))
    {
        mel_log("card: own $key: the window of the copies not opened (" . (!$scr ? 'no screen' : 'no copies') . ')');
        return null;
    }
    $c = MelOc::$cards[$key];
    $l = $c['nav'];
    $at = 0;
    foreach ($copies as $i => $cp)
    {
        if ($cp['cid'] === $cur)
            $at = $i;
    }
    $share = 0.0;
    $foot = mel_tr($c['ui'], 'lib_win_first');
    if ($e >= 1)
    {
        $x = mel_oc_ep($c, $s, $e);
        $pr = $x ? mel_oc_prog($c, $l['prog'], $x) : null;
        if ($pr && !$pr[2] && $pr[1] > 0)
            $share = min(1.0, $pr[0] / $pr[1]);
        $foot = mel_tr($c['ui'], 'lib_win_series');
    }
    else
    {
        $b = mel_cp_badge($l['hid'], 'movie');
        if ($b && !$b['watched'] && !$b['next'] && $b['e'] < 0 && $b['len'] > 0)
            $share = min(1.0, $b['pos'] / $b['len']);
        if ($mode === 'menu')
        {
            list(, , $pos) = mel_lib_place($l['hid'], $copies, '');
            if ($pos > 0)
                $foot = str_replace('{time}', sprintf('%d:%02d:%02d', $pos / 3600, $pos / 60 % 60, $pos % 60),
                    mel_tr($c['ui'], 'lib_win_place'));
        }
    }
    $lw = array('copies' => $copies, 'at' => $at, 'mode' => $mode, 's' => $e >= 1 ? $s : -1, 'e' => $e >= 1 ? $e : -1,
        'cur' => $cur, 'top' => max(0, $at - MEL_LIB_WIN_VIS + 1), 'share' => $cur !== '' ? $share : 0.0);
    mel_log("card: own $key: the window of " . count($copies) . " copies ($mode" . ($e >= 1 ? ", S{$s}E{$e}" : '') .
        ')' . ($cur !== '' ? ', the current one at ' . ($at + 1) : ''));
    return mel_ovl_open($scr, array('lw' => $lw), mel_lib_win_map($key), mel_lib_win_defs($lw, $foot, $c['ui']));
}

// The action map of the open window of the copies of the screen of owner $owner: UP, DOWN, ENTER, RETURN to lw_key,
// the other keys none (the card under it does not move).
/**
 * @param string $owner
 * @return array<string, MelAction>
 */
function mel_lib_win_map($owner)
{
    $map = array();
    foreach (array(GUI_EVENT_KEY_UP => 'up', GUI_EVENT_KEY_DOWN => 'down', GUI_EVENT_KEY_ENTER => 'enter',
        GUI_EVENT_KEY_RETURN => 'return') as $ev => $k)
        $map[$ev] = mel_ovl_input('lw_key', $owner, array('k' => $k));
    return $map;
}

// The components of window $lw with hint $foot in language $lang, the children of the slot of the overlay: the shade
// lw_dim and the window lw_win; its lines in the panel lw_rows (moved up by top lines) inside the clipping panel lw_list
// of WIN_VIS lines at most.
/**
 * @param MelLibWin $lw
 * @param string $foot
 * @param string $lang
 * @return list<MelGcComp>
 */
function mel_lib_win_defs($lw, $foot, $lang)
{
    $p = MEL_LIB_WIN;
    $n = count($lw['copies']);
    $vis = min($n, MEL_LIB_WIN_VIS);
    $W = MEL_LIB_WIN_W;
    $h = MEL_LIB_WIN_HEAD + $vis * MEL_LIB_WIN_ROW + MEL_LIB_WIN_FOOT;
    $paths = mel_lib_win_paths($lw['copies'], $lw['e'] >= 1);
    $rows = array();
    for ($i = 0; $i < $n; $i++)
        $rows[] = mel_lib_win_row($lw, $i, $paths, $lang);
    $caps = mel_k_cap();
    $bg = MelKit::plate('dialog', 0, 0, $W, $h);
    $line = MelKit::plate('dialog_line', 0, 0, $W, $h);
    $title = MelKit::text(mel_tr($lang, 'lib_win_title'), MEL_K_PX_STATUS_TITLE, MEL_K_C_TEXT1, MEL_LIB_WIN_PAD,
        MEL_LIB_WIN_TITLE_BASE - $caps[MEL_K_PX_STATUS_TITLE] / 2, array('w' => $W - 2 * MEL_LIB_WIN_PAD));
    $fy = MEL_LIB_WIN_HEAD + $vis * MEL_LIB_WIN_ROW + MEL_LIB_WIN_FOOT_BASE - $caps[MEL_K_PX_CAPTION] / 2;
    $hint = MelKit::text($foot, MEL_K_PX_CAPTION, MEL_K_C_TEXT3, MEL_LIB_WIN_PAD, $fy,
        array('w' => $W - 2 * MEL_LIB_WIN_PAD));
    // Options 0: the panel clips its children (the lines out of the window).
    $list = mel_gc_panel("{$p}_list", mel_gc_at($W, $vis * MEL_LIB_WIN_ROW, 0, MEL_LIB_WIN_HEAD), array(
        mel_gc_panel("{$p}_rows", mel_lib_win_scroll($n, $lw['top']), $rows)), 0);
    $y = intval((1080 - $h) / 2) - MEL_LIB_WIN_UP;
    $win = mel_gc_panel("{$p}_win", mel_gc_at($W, $h, MEL_LIB_WIN_X, $y), array_merge($bg['defs'], $line['defs'],
        $title['defs'], array($list), $hint['defs']));
    return array(mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), MEL_K_C_DIM_STRONG, "{$p}_dim"), $win);
}

// The geometry of the panel of the $n lines of the window, moved up by $top lines.
/**
 * @param int $n
 * @param int $top
 * @return MelGcGeom
 */
function mel_lib_win_scroll($n, $top)
{
    return mel_gc_at(MEL_LIB_WIN_W, $n * MEL_LIB_WIN_ROW, 0, -$top * MEL_LIB_WIN_ROW);
}

// Line $i of window $lw (the copy $lw['copies'][$i], its path of $paths by cid) in language $lang: the panel lw_r<i>
// WIN_ROW high at its place in lw_rows - one geometry in every state, a key changes it by its children.
/**
 * @param MelLibWin $lw
 * @param int $i
 * @param array<string, string> $paths
 * @param string $lang
 * @return MelGcComp
 */
function mel_lib_win_row($lw, $i, $paths, $lang)
{
    $cp = $lw['copies'][$i];
    $W = MEL_LIB_WIN_W;
    $on = $i === $lw['at'];
    $now = $cp['cid'] === $lw['cur'];
    $fg2 = $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT2;
    $d = array();
    if ($on)
    {
        $pl = MelKit::plate('row_on', MEL_LIB_WIN_ON_X, 0, $W - 2 * MEL_LIB_WIN_ON_X, MEL_LIB_WIN_ROW - MEL_LIB_WIN_ON_GAP);
        $d = $pl['defs'];
    }
    $parts = mel_lib_copy_parts($cp, $lang);
    $x = MEL_LIB_WIN_PAD;
    $ids = mel_lib_plate_ids($parts);
    $probe = MelKit::text('', MEL_K_PX_ROW, $fg2, 0, MEL_LIB_WIN_LINE_Y);
    if ($ids)
    {
        // The plates at the pixel under the centre of the capitals of the texts of the line (as mel_oc_lib_parts).
        list($plates, $pw) = mel_oc_lib_plates($ids, 255, MEL_K_PLATE_GAP);
        $d[] = mel_gc_panel(MEL_LIB_WIN . "_p$i", mel_gc_at($pw, MEL_K_PLATE_H, $x, (int) ceil($probe['mid']) -
            intval(MEL_K_PLATE_H / 2)), $plates);
        $x += $pw + MEL_LIB_WIN_GAP;
    }
    if ($now)
    {
        $t = MelKit::text(($ids ? "\xC2\xB7 " : '') . mel_tr($lang, 'lib_win_now'), MEL_K_PX_ROW, $fg2, $x,
            MEL_LIB_WIN_LINE_Y);
        $d = array_merge($d, $t['defs']);
    }
    if ($parts['size'] !== '')
    {
        $t = MelKit::text($parts['size'], MEL_K_PX_ROW, $fg2, 0, MEL_LIB_WIN_LINE_Y);
        $t = MelKit::move($t, $W - MEL_LIB_WIN_PAD - $t['x1'], 0);
        $d = array_merge($d, $t['defs']);
    }
    $caps = mel_k_cap();
    $t = MelKit::text(isset($paths[$cp['cid']]) ? $paths[$cp['cid']] : '', MEL_LIB_WIN_PATH_PX,
        $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT3, MEL_LIB_WIN_PAD, MEL_LIB_WIN_PATH_BASE - $caps[MEL_LIB_WIN_PATH_PX] / 2);
    $d = array_merge($d, $t['defs']);
    if ($now && $lw['share'] > 0)
    {
        $b = MelKit::bar('progress', MEL_LIB_WIN_BAR_X, MEL_LIB_WIN_BAR_Y, $W - 2 * MEL_LIB_WIN_BAR_X, MEL_LIB_WIN_BAR_H,
            $lw['share']);
        $d = array_merge($d, $b['defs']);
    }
    return mel_gc_panel(MEL_LIB_WIN . "_r$i", mel_gc_at($W, MEL_LIB_WIN_ROW, 0, $i * MEL_LIB_WIN_ROW), $d);
}

// lw_key: a key of the open window of the copies. A stale screen: close_and_run (mel_ovl_stale). A live one with no
// window (a key that came after the close, before its map was applied): none. Else mel_lib_win_key.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_lib_win_input($in)
{
    $a = mel_ovl_stale($in);
    if ($a)
        return $a;
    if (!MelOvl::$st || !MelOvl::$st['open'] || !isset(MelOvl::$st['lw']))
    {
        mel_log('card: the window of the copies: a key after the close, none');
        return null;
    }
    return mel_lib_win_key(isset($in->k) && is_string($in->k) ? $in->k : '');
}

// Key $k of the open window of the copies (MelOvl::$st['lw']) -> the reply, null for none:
//   up, down  the cursor a line, to the first and the last (no wrap); the lines scroll with it, WIN_VIS shown: the two
//             lines redrawn by their children, lw_rows moved when it scrolls;
//   enter     the close, then card_lib_copy of the card (k: the id of the copy, from: the mode; of an episode s, e) -
//             the card plays it (mel_oc_lib_input);
//   return    the close, nothing played.
/**
 * @param string $k
 * @return MelAction|null
 */
function mel_lib_win_key($k)
{
    $st = MelOvl::$st;
    if (!$st || !isset($st['lw']))
        return null;
    $lw = $st['lw'];
    $owner = $st['scr']['owner'];
    if ($k === 'return')
    {
        mel_log("card: own $owner: the window of the copies closed, nothing played");
        return mel_ovl_close();
    }
    if ($k === 'enter')
    {
        $cp = $lw['copies'][$lw['at']];
        $p = array('c' => $owner, 'k' => $cp['cid'], 'from' => $lw['mode']);
        if ($lw['e'] >= 1)
            $p += array('s' => strval($lw['s']), 'e' => strval($lw['e']));
        mel_log("card: own $owner: the copy at " . ($lw['at'] + 1) . ' of the window of the copies');
        return mel_ovl_close(mel_input('card_lib_copy', $p));
    }
    if ($k !== 'up' && $k !== 'down')
        return null;
    $was = $lw;
    $to = $lw['at'] + ($k === 'up' ? -1 : 1);
    if ($to < 0 || $to >= count($lw['copies']))
        return null;
    $lw['at'] = $to;
    if ($to < $lw['top'])
        $lw['top'] = $to;
    elseif ($to >= $lw['top'] + MEL_LIB_WIN_VIS)
        $lw['top'] = $to - MEL_LIB_WIN_VIS + 1;
    $st['lw'] = $lw;
    MelOvl::$st = $st;
    $lang = isset(MelOc::$cards[$owner]) ? MelOc::$cards[$owner]['ui'] : mel_catv_ui_lang();
    $paths = mel_lib_win_paths($lw['copies'], $lw['e'] >= 1);
    $ch = array();
    foreach (array($was['at'], $to) as $i)
    {
        $r = mel_lib_win_row($lw, $i, $paths, $lang);
        $ch[] = mel_gc_change(MEL_LIB_WIN . "_r$i", null, GCOMP_TRANSITION_NONE,
            $r[GComponentDef::specific_def][GCompPanelDef::children]);
    }
    if ($lw['top'] !== $was['top'])
        $ch[] = mel_gc_change(MEL_LIB_WIN . '_rows', mel_lib_win_scroll(count($lw['copies']), $lw['top']),
            GCOMP_TRANSITION_DEFAULT);
    return mel_ovl_change($ch, $st['scr']['sel']);
}
