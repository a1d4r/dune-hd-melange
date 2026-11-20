<?php
// The copies on the disk in the own card of a movie: the line "С диска" of a copy under the buttons (R1, owner
// 20.11.2026) on the UI Kit - the widths by MelKit::w, the badges the plates b2 of the list of releases (MelKit::b2);
// the labels as the other lines of the card (mel_oc_label).

// The line: its text, its right edge; the disk before it (MEL_OC_IMG disk.png, DISK_W square, its strokes DISK_PAD in
// from the left and the right of the picture). LIB_GAP between the visible edges of the parts of the line: the strokes
// of the disk, the text (by its advance), the glyph of each plate b2 (MEL_K_PLATE_PAD in from the edges of the plate:
// the dark plate hardly shows on the card, the glyph is what is seen) and "·" of the tail. The rhythm m: well over
// the 10 px between the icon and the word inside a glyph ("DV Vision"), and the plates side by side, not over one
// another (LIB_GAP - 2 MEL_K_PLATE_PAD apart). The disk and the plates on the centre of the capitals of the text
// (mel_oc_label_cy).
define('MEL_OC_LIB_PX', MEL_OC_LINE_PX);
define('MEL_OC_LIB_R', 1845);
define('MEL_OC_LIB_GAP', MEL_K_GAP_M);
define('MEL_OC_DISK_W', 24);
define('MEL_OC_DISK_PAD', 3);
// The alpha of the badges of that line (T1, owner 18.11.2026): their white in the tone of the grey text by them.
define('MEL_OC_LIB_ALPHA', 175);

// The line of copy $copy at $x under the buttons, the same whatever the progress (R1, owner 20.11.2026): the disk
// (MEL_OC_DISK_W), "С диска" (lib_from_disk), the badges the list of releases gives its tags (mel_lib_pf ->
// mel_badges with the path of the copy -> mel_gc_row_badges: the resolution, the source, the picture, the audio, the
// plates b2 of the UI Kit, MelKit::b2, as high as there, MEL_OC_LIB_ALPHA), the channels as the card of a release has them ("7.1 / 2.0", mel_audio_text) and the size
// as text. Wider than to MEL_OC_LIB_R: the size out first, then the channels, then the audio (owner 19.11.2026); the
// resolution and the picture stay.
/**
 * @param string $lang
 * @param MelLibCopy $copy
 * @param int $x
 * @return list<array<string, mixed>>
 */
function mel_oc_lib_line($lang, $copy, $x)
{
    $pf = mel_lib_pf($copy);
    $cols = mel_gc_row_badges(mel_badges($pf, null, $copy['path']));
    $quiet = array_values(array_filter(array($cols['res'], $cols['src'], $cols['vis']), 'strlen'));
    $ids = $cols['audio'] !== '' ? array_merge($quiet, array($cols['audio'])) : $quiet;
    $ch = $pf['audioChannels'];
    rsort($ch);
    $ch = implode(' / ', $ch);
    $size = $copy['size'] > 0 ? mel_size_str($copy['size'], $lang) : '';
    $head = mel_tr($lang, 'lib_from_disk');
    $cy = mel_oc_label_cy(MEL_OC_LINE_Y, MEL_OC_LIB_PX);
    $tx = $x + MEL_OC_DISK_W - MEL_OC_DISK_PAD + MEL_OC_LIB_GAP;
    $d = array();
    foreach (array(array($ch, $size, $ids), array($ch, '', $ids), array('', '', $ids), array('', '', $quiet)) as $v)
    {
        list($d, $w) = mel_oc_lib_parts($head, $v[2], implode(' · ', array_filter(array($v[0], $v[1]), 'strlen')), $tx,
            $cy);
        if ($tx + $w <= MEL_OC_LIB_R)
            break;
    }
    $disk = mel_gc_image(mel_gc_at(MEL_OC_DISK_W, MEL_OC_DISK_W, $x, (int) round($cy - MEL_OC_DISK_W / 2)),
        MEL_OC_IMG . 'disk.png');
    return array_merge(array($disk), $d);
}

// The parts of the line of mel_oc_lib_line at $x, the plates centred at $cy (mel_oc_label_cy): the text $head, the
// badges $ids, " · " and the text $tail (after $head without badges), LIB_GAP apart -> array(the components, the
// width).
/**
 * @param string $head
 * @param list<string> $ids
 * @param string $tail
 * @param int $x
 * @param float $cy
 * @return array{list<array<string, mixed>>, int}
 */
function mel_oc_lib_parts($head, $ids, $tail, $x, $cy)
{
    if (!$ids && $tail !== '')
    {
        $head .= " · $tail";
        $tail = '';
    }
    $w = MelKit::w($head, MEL_OC_LIB_PX);
    $d = array(mel_oc_label($x, MEL_OC_LINE_Y, $w + MEL_OC_LIB_PX, $head, MEL_OC_LIB_PX, MEL_CATV_TEXT_COLOR));
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
        $d[] = mel_gc_panel('lib', mel_gc_at($pw, MEL_K_PLATE_H, $x + $w + MEL_OC_LIB_GAP - MEL_K_PLATE_PAD,
            (int) ceil($cy) - intval(MEL_K_PLATE_H / 2)), $plates);
        $w += MEL_OC_LIB_GAP - MEL_K_PLATE_PAD + $pw;
    }
    if ($tail !== '')
    {
        $tail = "· $tail";
        $tw = MelKit::w($tail, MEL_OC_LIB_PX);
        $gap = MEL_OC_LIB_GAP - MEL_K_PLATE_PAD;
        $d[] = mel_oc_label($x + $w + $gap, MEL_OC_LINE_Y, $tw + MEL_OC_LIB_PX, $tail, MEL_OC_LIB_PX, MEL_CATV_TEXT_COLOR);
        $w += $gap + $tw;
    }
    return array($d, $w);
}
