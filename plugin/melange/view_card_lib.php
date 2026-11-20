<?php
// The copies on the disk in the own card: the line "С диска" of a copy of a movie over the buttons (variant Б, owner
// 20.11.2026) and the line of the copy of an episode in its head (B10) on the UI Kit - the widths by MelKit::w, the
// badges the plates b2 of the list of releases (MelKit::b2); the labels as the other lines of the card (mel_oc_label).
// And the copies of the card: found (mel_oc_lib), the current one (mel_oc_lib_cur, of an episode mel_oc_lib_ep_cur),
// "Копия на диске…" of MENU, the start (mel_oc_lib_start, of an episode mel_oc_lib_start_ep; mel_oc_lib_input). The window of the copies W2 over the card:
// view_card_libwin.php.

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

// The line of copy $copy of a title of $n copies at $x. The movie ($ep false) over the buttons, the line of its history
// under it (variant Б, owner 20.11.2026), the same whatever the progress: its label box at MEL_OC_LIB_Y, LIB_PX, to
// the right edge LIB_R at most; the disk (MEL_OC_DISK_W), "С диска" (lib_from_disk), the badges (mel_lib_copy_parts:
// the plates b2 as high as in the list of releases, MEL_OC_LIB_ALPHA), the plate of the channels after them, the size as text; $n 2 or more - and the tail
// "2 копии — MENU" (lib_copies, owner 20.11.2026). The head of an episode ($ep true, B10 A): the box at MEL_OC_EP_LIB_Y,
// MEL_CATV_LINE_PX, to EP_LIB_R; the disk EP_DISK_W dimmed as the badges, no words, no tail.
// Wider than the edge: the parts out one by one in the order of $steps - the size, then of the tail of the copies and the
// size the one that fits (owner 20.11.2026: the tail stays longer than the size; решение лида 2г1г: the size in place of
// the tail before the channels), then neither, the plate of the channels, the audio (owner 19.11.2026); the resolution
// and the picture stay. The head of an episode has no tail: the size, the channels, the audio.
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
    $steps = array(array(), array('size' => false), array('more' => false, 'size' => true), array('size' => false),
        array('ch' => false), array('audio' => false));
    foreach ($steps as $st)
    {
        $on = array_merge($on, $st);
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
        $all[mel_oc_se($r['s'], $r['e'])] = 1;
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
    return mel_lib_ep_same(mel_lib_ep_copies($y), mel_lib_ep_hashes($y), mel_oc_lib_seasons($m), $m['cin']);
}

// The seasons of meta $m by the invariant of the numberings (mel_oc_lib_eps): season => the set of the numbers of its
// episodes (those not out and of no date too), only a season asked as it is (mel_cm_req_s = s).
/**
 * @param MelCardMeta $m
 * @return array<int, array<int, true>>
 */
function mel_oc_lib_seasons($m)
{
    $seasons = array();
    foreach ($m['seasons'] as $s => $eps)
    {
        if (!$eps || mel_cm_req_s($m, $s) !== $s)
            continue;
        foreach ($eps as $ep)
            $seasons[$s][$ep['e']] = true;
    }
    return $seasons;
}

// The uids of the native records of the files on the disk of card $c (C10, spec §7.5: the marks "watched" of the card
// on them too) - of every file of them in the index, mounted or not (mel_lib_native_uid: of the index alone, no share
// touched), each once: of a movie all its files; of a series the files of episodes $keys ("q:e" of the history,
// mel_cm_hkey) by the invariant of the numberings (mel_oc_lib_seasons, mel_lib_ep_same_one: "s:e" of the index is "q:e"
// then). Y of the screen shown (nav lib_y), else found anew (mel_oc_lib). No Y, a series of no meta: none.
/**
 * @param MelOcCard $c
 * @param list<string> $keys
 * @return list<string>
 */
function mel_oc_lib_native_uids($c, $keys = array())
{
    if (isset($c['nav']))
        $y = $c['nav']['lib_y'];
    else
    {
        $ol = mel_oc_lib($c);
        $y = $ol['lib_y'];
    }
    $m = $c['meta'];
    $series = $c['tile']['type'] === 'series';
    mel_lib_index();
    if ($y === '')
        return array();
    $files = array();
    if (!$series)
        $files = isset(MelLib::$by_mid[$y]) ? MelLib::$by_mid[$y] : array();
    else if ($m && isset(MelLib::$eps[$y]))
    {
        $want = array_flip($keys);
        $seasons = mel_oc_lib_seasons($m);
        foreach (MelLib::$eps[$y] as $r)
        {
            if (isset($want[mel_oc_se($r['s'], $r['e'])]) && mel_lib_ep_same_one($r['s'], $r['e'], $seasons, $m['cin']))
                $files[] = $r;
        }
    }
    $out = array();
    foreach ($files as $f)
    {
        $u = mel_lib_native_uid($f);
        if ($u !== '')
            $out[$u] = 1;
    }
    return array_keys($out);
}

// The episode to offer of series card $c and the progress of its episodes (mel_oc_pick) by the history of H with the
// native records of the files of its episodes on the disk (mel_oc_lib: hid, fix), as the screen of the card offers it
// ("▶ Продолжить") - for a card with no layout at hand: "Смотреть из…" of a tile (mel_oc_tile_watch), the link of
// streams (mel_dl_streams). The map of the files is not kept (nav has none).
/**
 * @param MelOcCard $c
 * @return array{MelOcEp, array<string, array{float, float, bool}>}
 */
function mel_oc_lib_pick($c)
{
    $ol = mel_oc_lib($c);
    return mel_oc_pick($c, $ol['fix'], $ol['hid']);
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

// The key "s:e" of episode $e of season $s of the card in the copies of its episodes (nav lib_eps, lib_cur; the same of
// the index and of the history by the invariant, mel_lib_ep_same), one for all of the card.
/**
 * @param int $s
 * @param int $e
 * @return string
 */
function mel_oc_se($s, $e)
{
    return "$s:$e";
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
    $se = mel_oc_se($s, $e);
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
// card, mel_oc_se; the season of the copies of a key is its season) - the count of the season (mel_oc_count_text).
/**
 * @param array<string, list<MelLibCopy>> $lib_eps
 * @param int $s
 * @return int
 */
function mel_oc_lib_on_disk($lib_eps, $s)
{
    $n = 0;
    foreach ($lib_eps as $copies)
    {
        if ($copies && $copies[0]['s'] === $s)
            $n++;
    }
    return $n;
}

// "Копия на диске…" of MENU of card $c ($key) at place $pos of layout $l: a movie with two copies on the disk or more -
// card_lib_copies (the window W2 of them, mel_oc_lib_input); a series - of the episode in focus in the row, else of
// "Смотреть" (the episode offered, $l['ep']) with two copies or more, card_lib_copies with its s, e (the window W2 of
// it). None on a strip of posters or of the cast.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param MelOcPos $pos
 * @return list<array<string, mixed>>
 */
function mel_oc_lib_menu_items($key, $c, $l, $pos)
{
    if (mel_oc_strip_on($l, $pos) !== '')
        return array();
    $p = array('c' => $key);
    if ($l['lib_eps'])
    {
        list($s, $e) = $pos['z'] === 'row' ? array($pos['s'], $pos['e']) : ($l['ep'] ? array($l['ep']['s'], $l['ep']['e']) :
            array(0, 0));
        $se = mel_oc_se($s, $e);
        if (!isset($l['lib_eps'][$se]) || count($l['lib_eps'][$se]) < 2)
            return array();
        $p += array('s' => strval($s), 'e' => strval($e));
    }
    else if (count($l['lib']) < 2)
        return array();
    return array(array(
        GuiMenuItemDef::caption => mel_tr($c['ui'], 'lib_copy_menu'),
        GuiMenuItemDef::icon_url => 'gui_skin://small_icons/sources.aai',
        GuiMenuItemDef::action => mel_input('card_lib_copies', $p)));
}

// The start of a copy on the disk of the movie of card $c ($key) of layout $l (C2-C4, C11 of the spec): its copies read
// anew (a share may be gone since the screen; mel_lib_copies), the copy and the place by the history of the card
// (mel_lib_place). The copy of id $k chosen in the window of the copies ('' - "Смотреть": the copy of the record, of
// its mirrors the first there; none - the copies by mel_lib_start: one plays, several - the window W2 of them over the
// screen, mode first (mel_lib_win_open; none - the screen shown has no slot of the overlay: nothing, logged there), none
// there - mel_lib_gone).
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
    return isset($go['pick']) ? mel_lib_win_open($key, $go['pick'], 'first') : mel_lib_gone($go['gone']);
}

// The start of a copy on the disk of episode $e of season $s of the series of card $c ($key) of layout $l (C2, C4 of the
// spec, §7.5): the copies of its episodes and the files of them read anew (mel_oc_lib_eps: a share may be gone since the
// screen; the map of the files is not in nav), the season as asked is $s (the invariant: a key of lib_eps is of a season
// asked as it is). The copy of id $k chosen in the window of the copies (card_lib_copy) from the place of the episode
// (mel_lib_place; the choice goes into the history - the next episodes of its folder, owner 3); '' (OK on the still,
// "▶ Смотреть / Продолжить"): the copy and the place by the history of H (mel_lib_ep_ctx -> mel_lib_ep_place). Then
// mel_lib_start (of the mirrors of a record the copy of F first): the file there - vod_play of the playlist of its
// season (mel_lib_play), played (card_finish after the player); several left or none to take - the window of the copies
// of the episode over the screen (mel_lib_win_open, mode first; its "· сейчас" of lib_cur; none - the screen shown has no
// slot of the overlay: nothing, logged there); none there - mel_lib_gone of the episode. No episode on the disk now: as
// none there. No movie of the card: null.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param int $s
 * @param int $e
 * @param string $k
 * @return MelAction|null
 */
function mel_oc_lib_start_ep($key, $c, $l, $s, $e, $k = '')
{
    $y = $l['lib_y'];
    $mv = mel_movie(mel_oc_watch_in($c, $s, $e));
    if (!$mv || $y === '')
        return null;
    $hid = $l['hid'];
    $mv['dune_id'] = $hid;
    list($eps, $fix) = mel_oc_lib_eps($c, $y);
    $se = mel_oc_se($s, $e);
    $copies = isset($eps[$se]) ? $eps[$se] : array();
    mel_log("card: own $key: watch S{$s}E{$e} on the disk, " . count($copies) . ' copies' . ($k !== '' ? ", $k chosen" : ''));
    list($recs, $f) = mel_lib_ep_ctx($hid, $y);
    if ($k !== '')
        list($first, $first_pos, $pos) = mel_lib_place($hid, $copies, $k, $s, $e, $fix);
    else
        list($first, $first_pos, $pos) = mel_lib_ep_place($hid, $copies, $s, $e, isset($recs[$se]) ? $recs[$se] : '', $f,
            $fix);
    $go = mel_lib_start($copies, $first, $first_pos, $pos, false, $f);
    if (isset($go['copy']))
    {
        MelOc::$cards[$key]['played'] = true;
        return mel_lib_play($mv, $go, $c['ui'], array('y' => $y, 'q' => $s, 'e' => $e, 'eps' => $eps));
    }
    if (isset($go['pick']))
        return mel_lib_win_open($key, $go['pick'], 'first', $s, $e);
    return mel_lib_gone($go['gone'], $c['ui'], $s, $e);
}

// card_lib_copies (MENU "Копия на диске…") and card_lib_copy (k: the id of the copy of the window of the copies; from:
// first - of "Смотреть", menu - of MENU) of the movie of card $c ($key) of layout $l. MENU: the copies read anew, the
// window W2 of them over the screen, mode menu (mel_lib_win_open: "· сейчас" and the cursor on the copy of the latest
// record, mel_oc_lib_cur; no record - none, the cursor on the first); none left - lib_gone. A copy: mel_oc_lib_start
// (from the place of mel_lib_place). Only of a movie with copies on the screen shown (nav lib: its slot of the overlay).
// Of an episode (s, e; mel_oc_lib_input_ep): the window W2, its copy.
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
    if (mel_catv_in($in, 's') !== '' || mel_catv_in($in, 'e') !== '')
        return mel_oc_lib_input_ep($key, $c, $l, $in, $control);
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
    return mel_lib_win_open($key, $copies, 'menu');
}

// card_lib_copies and card_lib_copy of episode s, e of the series of card $c ($key) of layout $l (C4, spec §7.5). Only
// of an episode with copies on the screen shown (nav lib_eps: its slot of the overlay; not by the copies now). The
// window: the copies of the episode read anew (mel_oc_lib_eps), W2 of mode menu over the screen (mel_lib_win_open:
// "· сейчас" of lib_cur); none now - the message of the episode (mel_lib_gone). A copy (k, of the window): it plays from
// the place of the latest record of the episode of any supplier (mel_oc_lib_start_ep -> mel_lib_place; owner 3: the
// record of it makes its folder the one of the next episodes). A bad input, an episode with none: nothing.
/**
 * @param string $key
 * @param MelOcCard $c
 * @param MelOcNav $l
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_oc_lib_input_ep($key, $c, $l, $in, $control)
{
    $s = mel_catv_in($in, 's');
    $e = mel_catv_in($in, 'e');
    if (!preg_match('/^[0-9]{1,5}$/D', $s) || !preg_match('/^[0-9]{1,5}$/D', $e) ||
        empty($l['lib_eps'][mel_oc_se(intval($s), intval($e))]))
        return null;
    $s = intval($s);
    $e = intval($e);
    if ($control === 'card_lib_copy')
    {
        $k = mel_catv_in($in, 'k');
        if (!mel_lib_cid_ok($k))
            return null;
        mel_log("card: own $key: copy $k of S{$s}E{$e} of the window (" . mel_catv_in($in, 'from') . ')');
        return mel_oc_lib_start_ep($key, $c, $l, $s, $e, $k);
    }
    list($eps) = mel_oc_lib_eps($c, $l['lib_y']);
    $se = mel_oc_se($s, $e);
    if (empty($eps[$se]))
        return mel_lib_gone('no', $c['ui'], $s, $e);
    return mel_lib_win_open($key, $eps[$se], 'menu', $s, $e);
}
