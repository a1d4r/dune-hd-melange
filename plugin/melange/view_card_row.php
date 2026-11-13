<?php
// A strip: a row of items under a frame of the focus, shared by the rows of the own card (the
// episodes of a series, the cast, similar titles, the movies of a franchise) and of the page of a
// person (its works). The frame goes in the first slots, then the strip moves under it; a line
// after the first nc items. A row draws its own cells and the colours of their labels; the strip
// moves them and the frame. Ids of a strip: its id (also its key in the place of a screen, AioOcPos
// r) and "<id>st" (the strip), "<id>fr" (the frame); the cells of a row of posters
// (aio_oc_poster_cell) "<id><i>" and their titles "<id>n<i>", so the rows of a screen do not
// collide. The cast ("c") has its own: "p<i>", the names "pn<i>"; the episodes ("e") too: "c<i>",
// the titles "tt<i>".
// AioOcStrip: n items, nc before the line (0 none); the item w x h (the frame goes round it), the
// cell cw x ch (the strip ends cw after the x of the last); step apart, div more after the line;
// vis whole on the screen, lead slots the frame goes before the strip moves; x the left slot on
// the screen (the clip of the strip starts at 0), y the top of the item where the frame is drawn.
// The title of a row ROW_HEAD over the top of its items (aio_oc_row_title).
// Needs view_card.php (AIO_OC_FRAME, AIO_OC_FOCUS, aio_oc_label), view_catalog.php (aio_catv_poster)
// and gcomps.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

define('AIO_OC_ROW_HEAD', 46);
// A poster of a row (the works of a person, similar titles, the movies of a franchise),
// POSTER_W x POSTER_H, STEP apart, VIS of them whole (the next one at the right edge of the screen:
// 10 px of it cut), DIV more after the line; CAP_DY under it its title in 2 lines at most and the
// lines under it (aio_oc_poster_titles), 24 px. The owner, 13.11.2026: the posters of 190x285 were
// small, their titles cut.
define('AIO_OC_POSTER_W', 240);
define('AIO_OC_POSTER_H', 360);
define('AIO_OC_POSTER_STEP', 260);
define('AIO_OC_POSTER_VIS', 6);
define('AIO_OC_POSTER_DIV', 40);
define('AIO_OC_POSTER_CAP_DY', 12);

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
function aio_oc_off($i, $n, $vis, $lead)
{
    return max(0, min($i - $lead, $n - $vis));
}

// x in the strip of item $i: step apart, div more after the first nc.
/**
 * @param AioOcStrip $s
 * @param int $i
 * @return int
 */
function aio_oc_strip_x($s, $i)
{
    return $i * $s['step'] + ($s['nc'] > 0 && $i >= $s['nc'] ? $s['div'] : 0);
}

// Strip $s with item $k in focus: array(it, clamped to the strip, the first item at the left slot,
// aio_oc_off).
/**
 * @param AioOcStrip $s
 * @param int $k
 * @return array{int, int}
 */
function aio_oc_strip_at($s, $k)
{
    $k = max(0, min($s['n'] - 1, $k));
    return array($k, aio_oc_off($k, $s['n'], $s['vis'], $s['lead']));
}

// The strip in its clip with item $off at the left slot.
/**
 * @param AioOcStrip $s
 * @param int $off
 * @return AioGcGeom
 */
function aio_oc_strip_geom($s, $off)
{
    return aio_gc_at(aio_oc_strip_x($s, $s['n'] - 1) + $s['cw'], $s['ch'], $s['x'] - aio_oc_strip_x($s, $off), 0);
}

// The frame round item $k with $off at the left slot, where the frame of the strip is drawn.
/**
 * @param AioOcStrip $s
 * @param int $k
 * @param int $off
 * @return AioGcGeom
 */
function aio_oc_strip_frame_geom($s, $k, $off)
{
    return aio_oc_frame_at($s['x'] + aio_oc_strip_x($s, $k) - aio_oc_strip_x($s, $off), $s['y'], $s['w'], $s['h']);
}

// The strip ("<id>st") of $cells with item $k in focus, the line after the first nc in the middle of
// their gap (none when all or none are before it).
/**
 * @param AioOcStrip $s
 * @param int $k
 * @param list<AioGcComp> $cells
 * @return AioGcComp
 */
function aio_oc_strip_panel($s, $k, $cells)
{
    list(, $off) = aio_oc_strip_at($s, $k);
    if ($s['nc'] > 0 && $s['nc'] < $s['n'])
        $cells[] = aio_gc_rect(aio_gc_at(2, $s['h'], aio_oc_strip_x($s, $s['nc'] - 1) + $s['w'] +
            intval(($s['step'] + $s['div'] - $s['w']) / 2) - 1, 0), AIO_GC_DIM);
    return aio_gc_panel($s['id'] . 'st', aio_oc_strip_geom($s, $off), $cells);
}

// The frame ("<id>fr") round item $k, empty but when the strip has the focus ($on).
/**
 * @param AioOcStrip $s
 * @param int $k
 * @param bool $on
 * @return AioGcComp
 */
function aio_oc_strip_frame($s, $k, $on)
{
    list($k, $off) = aio_oc_strip_at($s, $k);
    return aio_gc_panel($s['id'] . 'fr', aio_oc_strip_frame_geom($s, $k, $off), $on ? aio_oc_frame_rects($s['w'], $s['h']) : array());
}

// The changes of strip $s from item $ok (the focus on it: $was) to $k ($is): the strip moved, the
// frame moved (shown or hidden at once, aio_oc_frame_change).
/**
 * @param AioOcStrip $s
 * @param int $ok
 * @param int $k
 * @param bool $was
 * @param bool $is
 * @return list<AioGcChange>
 */
function aio_oc_strip_changes($s, $ok, $k, $was, $is)
{
    list($k, $off) = aio_oc_strip_at($s, $k);
    list($ok, $ooff) = aio_oc_strip_at($s, $ok);
    $ch = array();
    if ($off !== $ooff)
        $ch[] = aio_gc_change($s['id'] . 'st', aio_oc_strip_geom($s, $off), GCOMP_TRANSITION_DEFAULT);
    $slot = aio_oc_strip_x($s, $k) - aio_oc_strip_x($s, $off);
    return array_merge($ch, aio_oc_frame_change($s['id'] . 'fr', $slot !== aio_oc_strip_x($s, $ok) - aio_oc_strip_x($s, $ooff) ?
        aio_oc_strip_frame_geom($s, $k, $off) : null, true, $was, $is, $s['w'], $s['h']));
}

// The frame of the focus around an item $w x $h at ($x, $y).
/**
 * @param int $x
 * @param int $y
 * @param int $w
 * @param int $h
 * @return AioGcGeom
 */
function aio_oc_frame_at($x, $y, $w, $h)
{
    $f = AIO_OC_FRAME;
    return aio_gc_at($w + 2 * $f, $h + 2 * $f, $x - $f, $y - $f);
}

// The frame of the focus around an item $w x $h: 4 rects FRAME wide.
/**
 * @param int $w
 * @param int $h
 * @return list<AioGcComp>
 */
function aio_oc_frame_rects($w, $h)
{
    $f = AIO_OC_FRAME;
    $w += 2 * $f;
    $h += 2 * $f;
    return array(aio_gc_rect(aio_gc_at($w, $f, 0, 0), AIO_OC_FOCUS), aio_gc_rect(aio_gc_at($w, $f, 0, $h - $f), AIO_OC_FOCUS),
        aio_gc_rect(aio_gc_at($f, $h - 2 * $f, 0, $f), AIO_OC_FOCUS), aio_gc_rect(aio_gc_at($f, $h - 2 * $f, $w - $f, $f), AIO_OC_FOCUS));
}

// The change of frame $id around an item $w x $h: shown or hidden ($was, $is) at once, at $geom
// (null: where it is); else moved to $geom, in motion if $smooth; none when nothing changes.
/**
 * @param string $id
 * @param AioGcGeom|null $geom
 * @param bool $smooth
 * @param bool $was
 * @param bool $is
 * @param int $w
 * @param int $h
 * @return list<AioGcChange>
 */
function aio_oc_frame_change($id, $geom, $smooth, $was, $is, $w, $h)
{
    if ($was !== $is)
        return array(aio_gc_change($id, $geom, GCOMP_TRANSITION_NONE, $is ? aio_oc_frame_rects($w, $h) : array()));
    if ($geom)
        return array(aio_gc_change($id, $geom, $smooth ? GCOMP_TRANSITION_DEFAULT : GCOMP_TRANSITION_NONE));
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
 * @return list<AioGcComp>
 */
function aio_oc_row_title($y, $title, $rest, $w)
{
    $tw = min($w, aio_gc_w($title, AIO_CATV_LINE_PX));
    $d = array(aio_oc_label(AIO_CATV_CARD_X, $y, $tw, aio_gc_cut_w($title, AIO_CATV_LINE_PX, $w), AIO_CATV_LINE_PX, AIO_GC_TEXT));
    $x = aio_gc_w($title . ' ', AIO_CATV_LINE_PX);
    if ($rest !== '' && $x < $w)
        $d[] = aio_oc_label(AIO_CATV_CARD_X + $x, $y, $w - $x, aio_gc_cut_w("· $rest", AIO_CATV_LINE_PX, $w - $x),
            AIO_CATV_LINE_PX, AIO_CATV_TEXT_COLOR);
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
 * @return AioOcStrip
 */
function aio_oc_poster_strip($id, $n, $nc, $y, $ch)
{
    return array('n' => $n, 'nc' => $nc, 'w' => AIO_OC_POSTER_W, 'h' => AIO_OC_POSTER_H, 'cw' => AIO_OC_POSTER_W, 'ch' => $ch,
        'step' => AIO_OC_POSTER_STEP, 'div' => AIO_OC_POSTER_DIV, 'vis' => AIO_OC_POSTER_VIS, 'lead' => AIO_OC_POSTER_VIS - 2,
        'x' => AIO_CATV_CARD_X, 'y' => $y + AIO_OC_ROW_HEAD, 'id' => $id);
}

// The title of a poster ($name in $color, 2 lines at most, "…" at the end of the second) and the
// lines under it, grey, 4 px more apart from it: their tops 0, 26 and 56, 82.
/**
 * @param string $name
 * @param list<string> $lines
 * @param string $color
 * @return list<AioGcComp>
 */
function aio_oc_poster_titles($name, $lines, $color)
{
    $d = array();
    $y = 0;
    foreach (aio_gc_wrap(aio_clean($name), 24, AIO_OC_POSTER_W, 2, true) as $s)
    {
        $d[] = aio_oc_label(0, $y, AIO_OC_POSTER_W, $s, 24, $color);
        $y += 26;
    }
    $y += 4;
    foreach ($lines as $s)
    {
        $d[] = aio_oc_label(0, $y, AIO_OC_POSTER_W, $s, 24, AIO_GC_DIM);
        $y += 26;
    }
    return $d;
}

// The poster of tile $t at $i of strip $s ("<id><i>") as in the catalog (aio_catv_poster), $over
// over it, its titles under it ("<id>n<i>", aio_oc_poster_titles).
/**
 * @param AioOcStrip $s
 * @param int $i
 * @param AioCatTile $t
 * @param list<AioGcComp> $titles
 * @param list<AioGcComp> $over
 * @return AioGcComp
 */
function aio_oc_poster_cell($s, $i, $t, $titles, $over = array())
{
    $d = array_merge(array(aio_gc_rect(aio_gc_at(AIO_OC_POSTER_W, AIO_OC_POSTER_H, 0, 0), AIO_OC_NO_PHOTO),
        aio_gc_image(aio_gc_at(AIO_OC_POSTER_W, AIO_OC_POSTER_H, 0, 0), aio_catv_poster($t), true)), $over);
    $d[] = aio_gc_panel("{$s['id']}n$i", aio_gc_at(AIO_OC_POSTER_W, $s['ch'] - AIO_OC_POSTER_H - AIO_OC_POSTER_CAP_DY, 0,
        AIO_OC_POSTER_H + AIO_OC_POSTER_CAP_DY), $titles);
    return aio_gc_panel("{$s['id']}$i", aio_gc_at(AIO_OC_POSTER_W, $s['ch'], aio_oc_strip_x($s, $i), 0), $d);
}
