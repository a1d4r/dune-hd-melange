<?php
// A strip: a row of items under a frame of the focus, shared by the rows of the own card (the
// episodes of a series, the cast, similar titles, the movies of a franchise) and of the page of a
// person (its works). The frame goes in the first slots, then the strip moves under it; a line
// after the first nc items. A row draws its own cells and the colours of their labels; the strip
// moves them and the frame. Ids of a strip: its id (also its key in the place of a screen, MelOcPos
// r) and "<id>st" (the strip), "<id>fr" (the frame); the cells of a row of posters
// (mel_oc_poster_cell) "<id><i>" and their titles "<id>n<i>", so the rows of a screen do not
// collide; the stubs of a row asked (mel_oc_poster_stub) have none. The cast ("c") has its own:
// "p<i>", the names "pn<i>"; the episodes ("e") too: "c<i>", the titles "tt<i>".
// MelOcStrip: n items, nc before the line (0 none); the item w x h (the frame goes round it), the
// cell cw x ch (the strip ends cw after the x of the last); step apart, div more after the line;
// vis whole on the screen, lead slots the frame goes before the strip moves; x the left slot on
// the screen (the clip of the strip starts at 0), y the top of the item where the frame is drawn.
// The title of a row ROW_HEAD over the top of its items (mel_oc_row_title).
// Needs view_card.php (MEL_OC_FRAME, MEL_OC_FOCUS, MEL_OC_IMG, mel_oc_label), view_catalog.php
// (mel_catv_poster, mel_catv_rate_text) and gcomps.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

define('MEL_OC_ROW_HEAD', 58);
// A poster of a row (the works of a person, similar titles, the movies of a franchise),
// POSTER_W x POSTER_H, STEP apart, VIS of them whole (the next one at the right edge of the screen:
// 10 px of it cut), DIV more after the line; CAP_DY under it its title in 2 lines at most and the
// lines under it (mel_oc_poster_titles), 24 px. The owner, 13.11.2026: the posters of 190x285 were
// small, their titles cut. The lines under the items of a row (the titles and years of the posters,
// the names and roles of the cast): CAP_LH apart, the lines under a title CAP_GAP more apart from
// it; the bands of their ink (mel_oc_row_bands) are of the same.
define('MEL_OC_POSTER_W', 240);
define('MEL_OC_POSTER_H', 360);
define('MEL_OC_POSTER_STEP', 260);
define('MEL_OC_POSTER_VIS', 6);
define('MEL_OC_POSTER_DIV', 40);
define('MEL_OC_POSTER_CAP_DY', 12);
define('MEL_OC_CAP_LH', 26);
define('MEL_OC_CAP_GAP', 4);
// The IMDb rating in the top left corner of a poster of a row (mel_oc_rate_over): the plate of the
// catalog (MEL_CATV_RATE_*, of its poster 190x285) scaled to POSTER_W x POSTER_H, RATE_W x RATE_H at
// (RATE_PAD, RATE_PAD), img/card1_rate.png (70 % black, rounded); the number over it RATE_PX, centred
// by the shell in a label RATE_TH high in the middle of the plate, as the text of a button.
define('MEL_OC_RATE_PAD', 10);
define('MEL_OC_RATE_W', 66);
define('MEL_OC_RATE_H', 40);
define('MEL_OC_RATE_PX', 32);
define('MEL_OC_RATE_TH', 46);

// The initials shared by the cast and the page of a person without a photo.
/**
 * @param string $name
 * @return string
 */
function mel_oc_initials($name)
{
    $ini = '';
    foreach (array_slice(explode(' ', mel_clean($name)), 0, 2) as $w)
        $ini .= mb_substr($w, 0, 1, 'UTF-8');
    return $ini;
}

// The first item of a row of $n at the left slot with the one at $i in focus, $vis of them whole
// on the screen: the frame goes in the first $lead + 1 slots, then the row, the frame stays in slot
// $lead; at the end the frame again, the last at the right slot.
/**
 * @param int $i
 * @param int $n
 * @param int $vis
 * @param int $lead
 * @return int
 */
function mel_oc_off($i, $n, $vis, $lead)
{
    return max(0, min($i - $lead, $n - $vis));
}

// x in the strip of item $i: step apart, div more after the first nc.
/**
 * @param MelOcStrip $s
 * @param int $i
 * @return int
 */
function mel_oc_strip_x($s, $i)
{
    return $i * $s['step'] + ($s['nc'] > 0 && $i >= $s['nc'] ? $s['div'] : 0);
}

// Strip $s with item $k in focus: array(it, clamped to the strip, the first item at the left slot,
// mel_oc_off).
/**
 * @param MelOcStrip $s
 * @param int $k
 * @return array{int, int}
 */
function mel_oc_strip_at($s, $k)
{
    $k = max(0, min($s['n'] - 1, $k));
    return array($k, mel_oc_off($k, $s['n'], $s['vis'], $s['lead']));
}

// The strip in its clip with item $off at the left slot.
/**
 * @param MelOcStrip $s
 * @param int $off
 * @return MelGcGeom
 */
function mel_oc_strip_geom($s, $off)
{
    return mel_gc_at(mel_oc_strip_x($s, $s['n'] - 1) + $s['cw'], $s['ch'], $s['x'] - mel_oc_strip_x($s, $off), 0);
}

// The frame round item $k with $off at the left slot, where the frame of the strip is drawn.
/**
 * @param MelOcStrip $s
 * @param int $k
 * @param int $off
 * @return MelGcGeom
 */
function mel_oc_strip_frame_geom($s, $k, $off)
{
    return mel_oc_frame_at($s['x'] + mel_oc_strip_x($s, $k) - mel_oc_strip_x($s, $off), $s['y'], $s['w'], $s['h']);
}

// The strip ("<id>st") of $cells with item $k in focus, the line after the first nc in the middle of
// their gap (none when all or none are before it).
/**
 * @param MelOcStrip $s
 * @param int $k
 * @param list<MelGcComp> $cells
 * @return MelGcComp
 */
function mel_oc_strip_panel($s, $k, $cells)
{
    list(, $off) = mel_oc_strip_at($s, $k);
    if ($s['nc'] > 0 && $s['nc'] < $s['n'])
        $cells[] = mel_gc_rect(mel_gc_at(2, $s['h'], mel_oc_strip_x($s, $s['nc'] - 1) + $s['w'] +
            intval(($s['step'] + $s['div'] - $s['w']) / 2) - 1, 0), MEL_GC_DIM);
    return mel_gc_panel($s['id'] . 'st', mel_oc_strip_geom($s, $off), $cells);
}

// The frame ("<id>fr") round item $k, empty but when the strip has the focus ($on).
/**
 * @param MelOcStrip $s
 * @param int $k
 * @param bool $on
 * @return MelGcComp
 */
function mel_oc_strip_frame($s, $k, $on)
{
    list($k, $off) = mel_oc_strip_at($s, $k);
    return mel_gc_panel($s['id'] . 'fr', mel_oc_strip_frame_geom($s, $k, $off), $on ? mel_oc_frame_rects($s['w'], $s['h']) : array());
}

// The changes of strip $s from item $ok (the focus on it: $was) to $k ($is): the strip moved, the
// frame moved (shown or hidden at once, mel_oc_frame_change).
/**
 * @param MelOcStrip $s
 * @param int $ok
 * @param int $k
 * @param bool $was
 * @param bool $is
 * @return list<MelGcChange>
 */
function mel_oc_strip_changes($s, $ok, $k, $was, $is)
{
    list($k, $off) = mel_oc_strip_at($s, $k);
    list($ok, $ooff) = mel_oc_strip_at($s, $ok);
    $ch = array();
    if ($off !== $ooff)
        $ch[] = mel_gc_change($s['id'] . 'st', mel_oc_strip_geom($s, $off), GCOMP_TRANSITION_DEFAULT);
    $slot = mel_oc_strip_x($s, $k) - mel_oc_strip_x($s, $off);
    return array_merge($ch, mel_oc_frame_change($s['id'] . 'fr', $slot !== mel_oc_strip_x($s, $ok) - mel_oc_strip_x($s, $ooff) ?
        mel_oc_strip_frame_geom($s, $k, $off) : null, true, $was, $is, $s['w'], $s['h']));
}

// The frame of the focus around an item $w x $h at ($x, $y).
/**
 * @param int $x
 * @param int $y
 * @param int $w
 * @param int $h
 * @return MelGcGeom
 */
function mel_oc_frame_at($x, $y, $w, $h)
{
    $f = MEL_OC_FRAME;
    return mel_gc_at($w + 2 * $f, $h + 2 * $f, $x - $f, $y - $f);
}

// The frame of the focus around an item $w x $h: 4 rects FRAME wide.
/**
 * @param int $w
 * @param int $h
 * @return list<MelGcComp>
 */
function mel_oc_frame_rects($w, $h)
{
    $f = MEL_OC_FRAME;
    $w += 2 * $f;
    $h += 2 * $f;
    return array(mel_gc_rect(mel_gc_at($w, $f, 0, 0), MEL_OC_FOCUS), mel_gc_rect(mel_gc_at($w, $f, 0, $h - $f), MEL_OC_FOCUS),
        mel_gc_rect(mel_gc_at($f, $h - 2 * $f, 0, $f), MEL_OC_FOCUS), mel_gc_rect(mel_gc_at($f, $h - 2 * $f, $w - $f, $f), MEL_OC_FOCUS));
}

// The change of frame $id around an item $w x $h: shown or hidden ($was, $is) at once, at $geom
// (null: where it is); else moved to $geom, in motion if $smooth; none when nothing changes.
/**
 * @param string $id
 * @param MelGcGeom|null $geom
 * @param bool $smooth
 * @param bool $was
 * @param bool $is
 * @param int $w
 * @param int $h
 * @return list<MelGcChange>
 */
function mel_oc_frame_change($id, $geom, $smooth, $was, $is, $w, $h)
{
    if ($was !== $is)
        return array(mel_gc_change($id, $geom, GCOMP_TRANSITION_NONE, $is ? mel_oc_frame_rects($w, $h) : array()));
    if ($geom)
        return array(mel_gc_change($id, $geom, $smooth ? GCOMP_TRANSITION_DEFAULT : GCOMP_TRANSITION_NONE));
    return array();
}

// --- Rows of posters.

// The title of a row at $y of its panel: $title white, then "· $rest" grey ('' none), cut to $w
// from the x of the catalog.
/**
 * @param int $y
 * @param string $title
 * @param string $rest
 * @param int $w
 * @return list<MelGcComp>
 */
function mel_oc_row_title($y, $title, $rest, $w)
{
    $tw = min($w, mel_gc_w($title, MEL_CATV_LINE_PX));
    $d = array(mel_oc_label(MEL_CATV_CARD_X, $y, $tw, mel_gc_cut_w($title, MEL_CATV_LINE_PX, $w), MEL_CATV_LINE_PX, MEL_GC_TEXT));
    $x = intval(mel_gc_sum($title . ' ', MEL_CATV_LINE_PX));
    if ($rest !== '' && $x < $w)
        $d[] = mel_oc_label(MEL_CATV_CARD_X + $x, $y, $w - $x, mel_gc_cut_w("· $rest", MEL_CATV_LINE_PX, $w - $x),
            MEL_CATV_LINE_PX, MEL_CATV_TEXT_COLOR);
    return $d;
}

// The strip of $n posters ($nc before the line) of a row "$id" with its title at $y of its panel,
// cells $ch high (the poster and the lines under it).
/**
 * @param string $id
 * @param int $n
 * @param int $nc
 * @param int $y
 * @param int $ch
 * @return MelOcStrip
 */
function mel_oc_poster_strip($id, $n, $nc, $y, $ch)
{
    return array('n' => $n, 'nc' => $nc, 'w' => MEL_OC_POSTER_W, 'h' => MEL_OC_POSTER_H, 'cw' => MEL_OC_POSTER_W, 'ch' => $ch,
        'step' => MEL_OC_POSTER_STEP, 'div' => MEL_OC_POSTER_DIV, 'vis' => MEL_OC_POSTER_VIS, 'lead' => MEL_OC_POSTER_VIS - 2,
        'x' => MEL_CATV_CARD_X, 'y' => $y + MEL_OC_ROW_HEAD, 'id' => $id);
}

// The title of a poster ($name in $color): one line, two in focus ($on), the last with "…" if
// cut. The lines under it grey, CAP_LH apart, CAP_GAP more apart from the title.
/**
 * @param string $name
 * @param list<string> $lines
 * @param string $color
 * @param bool $on
 * @return list<MelGcComp>
 */
function mel_oc_poster_titles($name, $lines, $color, $on)
{
    $d = array();
    $y = 0;
    foreach (mel_gc_wrap(mel_clean($name), 24, MEL_OC_POSTER_W, $on ? 2 : 1, true) as $s)
    {
        $d[] = mel_oc_label(0, $y, MEL_OC_POSTER_W, $s, 24, $color);
        $y += MEL_OC_CAP_LH;
    }
    $y += MEL_OC_CAP_GAP;
    foreach ($lines as $s)
    {
        $d[] = mel_oc_label(0, $y, MEL_OC_POSTER_W, $s, 24, MEL_GC_DIM);
        $y += MEL_OC_CAP_LH;
    }
    return $d;
}

// The shell's loading poster at $i of strip $s, while its row is asked (a card, a person).
/**
 * @param MelOcStrip $s
 * @param int $i
 * @return MelGcComp
 */
function mel_oc_poster_stub($s, $i)
{
    return mel_gc_image(mel_gc_at(MEL_OC_POSTER_W, MEL_OC_POSTER_H, mel_oc_strip_x($s, $i), 0),
        'plugin_file://%shell_ext%/icons/loading_movie_poster.png');
}

// The IMDb rating of a tile over its poster (mel_catv_rate_text), as on the posters of the catalog:
// the plate and the number, 2 components; none without a rating or with cat_rating of the settings
// off ("Рейтинг IMDb на постерах").
/**
 * @param string $rating
 * @return list<MelGcComp>
 */
function mel_oc_rate_over($rating)
{
    $s = Mel::$settings['cat_rating'] ? mel_catv_rate_text($rating) : '';
    if ($s === '')
        return array();
    $p = MEL_OC_RATE_PAD;
    return array(mel_gc_image(mel_gc_at(MEL_OC_RATE_W, MEL_OC_RATE_H, $p, $p), MEL_OC_IMG . 'rate.png'),
        mel_gc_ttf(null, mel_gc_at(MEL_OC_RATE_W, MEL_OC_RATE_TH, $p, $p + intval((MEL_OC_RATE_H - MEL_OC_RATE_TH) / 2)), $s,
            MEL_OC_RATE_PX, MEL_GC_TEXT, array(GCompTtfLabelDef::halign => HALIGN_CENTER)));
}

// The poster of tile $t at $i of strip $s ("<id><i>") as in the catalog (mel_catv_poster), its IMDb
// rating (mel_oc_rate_over) and $over over it, its titles under it ("<id>n<i>", mel_oc_poster_titles).
/**
 * @param MelOcStrip $s
 * @param int $i
 * @param MelCatTile $t
 * @param list<MelGcComp> $titles
 * @param list<MelGcComp> $over
 * @return MelGcComp
 */
function mel_oc_poster_cell($s, $i, $t, $titles, $over = array())
{
    $d = array_merge(array(mel_gc_rect(mel_gc_at(MEL_OC_POSTER_W, MEL_OC_POSTER_H, 0, 0), MEL_OC_NO_PHOTO),
        mel_gc_image(mel_gc_at(MEL_OC_POSTER_W, MEL_OC_POSTER_H, 0, 0), mel_catv_poster($t), true)),
        mel_oc_rate_over($t['imdbRating']), $over);
    $d[] = mel_gc_panel("{$s['id']}n$i", mel_gc_at(MEL_OC_POSTER_W, $s['ch'] - MEL_OC_POSTER_H - MEL_OC_POSTER_CAP_DY, 0,
        MEL_OC_POSTER_H + MEL_OC_POSTER_CAP_DY), $titles);
    return mel_gc_panel("{$s['id']}$i", mel_gc_at(MEL_OC_POSTER_W, $s['ch'], mel_oc_strip_x($s, $i), 0), $d);
}
