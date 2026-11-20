<?php
// The line "С диска" of a copy on the disk of the own card of a movie under its buttons (R1, owner 20.11.2026; the
// constants MEL_OC_LIB_* and MEL_OC_DISK_* of view_card.php): on the UI Kit - the widths by MelKit::w, the badges the
// plates b2 of the list of releases (MelKit::b2); the labels as the other lines of the card (mel_oc_label).

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
    $tx = $x + MEL_OC_DISK_W + MEL_OC_DISK_GAP;
    $d = array();
    foreach (array(array($ch, $size, $ids), array($ch, '', $ids), array('', '', $ids), array('', '', $quiet)) as $v)
    {
        list($d, $w) = mel_oc_lib_parts($head, $v[2], implode(' · ', array_filter(array($v[0], $v[1]), 'strlen')), $tx);
        if ($tx + $w <= MEL_OC_LIB_R)
            break;
    }
    $disk = mel_gc_image(mel_gc_at(MEL_OC_DISK_W, MEL_OC_DISK_W, $x,
        MEL_OC_LINE_Y + intval((MEL_OC_LINE_H - MEL_OC_DISK_W) / 2)), MEL_OC_IMG . 'disk.png');
    return array_merge(array($disk), $d);
}

// The parts of the line of mel_oc_lib_line at $x: the text $head, the badges $ids, " · " and the text $tail (after
// $head without badges) -> array(the components, the width).
/**
 * @param string $head
 * @param list<string> $ids
 * @param string $tail
 * @param int $x
 * @return array{list<array<string, mixed>>, int}
 */
function mel_oc_lib_parts($head, $ids, $tail, $x)
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
        // The plates b2 side by side MEL_K_PLATE_GAP apart, dimmed whole: the pictures by alpha, the rects of the
        // plates by the alpha of their colour.
        $items = array();
        foreach ($ids as $id)
            $items[] = MelKit::b2($id, 0, MEL_K_PLATE_H / 2);
        $row = MelKit::row($items, MEL_K_PLATE_GAP, 0, MEL_K_PLATE_H / 2, 'lib');
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
        $d[] = mel_gc_panel('lib', mel_gc_at($pw, MEL_K_PLATE_H, $x + $w + MEL_K_PLATE_GAP,
            MEL_OC_LINE_Y + intval((MEL_OC_LINE_H - MEL_K_PLATE_H) / 2)), $plates);
        $w += MEL_K_PLATE_GAP + $pw;
    }
    if ($tail !== '')
    {
        $tail = "· $tail";
        $tw = MelKit::w($tail, MEL_OC_LIB_PX);
        $d[] = mel_oc_label($x + $w + MEL_K_PLATE_GAP, MEL_OC_LINE_Y, $tw + MEL_OC_LIB_PX, $tail, MEL_OC_LIB_PX,
            MEL_CATV_TEXT_COLOR);
        $w += MEL_K_PLATE_GAP + $tw;
    }
    return array($d, $w);
}
