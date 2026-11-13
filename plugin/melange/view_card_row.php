<?php
// A strip: a row of items under a frame of the focus, shared by the rows of the own card (the
// episodes of a series, the cast) and of the page of a person (its works). The frame goes in the
// first slots, then the strip moves under it; a line after the first nc items. A row draws its own
// cells and the colours of their labels; the strip moves them and the frame. Ids of a strip: its
// id (also its key in the place of a screen, AioOcPos r) and "st" (the strip), "fr" (the frame);
// the cells of a row start with it too, so the rows of a page do not collide.
// AioOcStrip: n items, nc before the line (0 none); the item w x h (the frame goes round it), the
// cell cw x ch (the strip ends cw after the x of the last); step apart, div more after the line;
// vis whole on the screen, lead slots the frame goes before the strip moves; x the left slot on
// the screen (the clip of the strip starts at 0), y the top of the item where the frame is drawn.
// Needs view_card.php (AIO_OC_FRAME, AIO_OC_FOCUS) and gcomps.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

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
