<?php
// GComps primitives of the view_gcomps screens (the list, the details): text
// widths by the firmware font, wrapping, the component builders, the key
// action. Required by view_gcomps.php; view_info.php gets it through the
// require order of main.php. Not self-contained: aio_gc_act puts the rid of
// the list and calls aio_input (main.php), aio_gc_ttf calls aio_clean
// (parse.php). PHP 5.3.6, firmware API only.

require_once dirname(__FILE__) . '/font_w.php';

define('AIO_GC_W_MARGIN', 1.03);   // text widths: the font's advances + 3 %

// --- Text: cleaning, width, wrapping.

// Advance of a glyph of OpenSans of the firmware, thousandths of the size
// (font_w.php); CJK and full-width forms (not
// in the font) as 1 em, other unknowns the average.
function aio_gc_glyph($c)
{
    // Kept, not copied on every glyph (684 entries); "=== null": is_null()
    // copies the array again (review 0.4.1).
    static $t = null;
    if ($t === null)
        $t = aio_font_w();
    if (isset($t[$c]))
        return $t[$c];
    return preg_match('/^[\\p{Han}\\p{Hangul}\\p{Hiragana}\\p{Katakana}\\x{FF00}-\\x{FFEF}]$/u', $c) ?
        1000 : AIO_FONT_AVG;
}

// Width of a text at $px: advances rounded to pixels glyph by glyph, as
// FreeType hints them (1.7 % from its layout on test lines), plus AIO_GC_W_MARGIN.
function aio_gc_w($s, $px)
{
    $w = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $c)
        $w += round(aio_gc_glyph($c) * $px / 1000);
    return intval(ceil($w * AIO_GC_W_MARGIN));
}

// The longest prefix of $s (in characters) not wider than $width.
function aio_gc_fit($s, $px, $width)
{
    $n = 0;
    $w = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $c)
    {
        $w += round(aio_gc_glyph($c) * $px / 1000);
        if ($w * AIO_GC_W_MARGIN > $width)
            break;
        $n++;
    }
    return $n;
}

// $s cut to $width with "…".
function aio_gc_cut_w($s, $px, $width)
{
    if (aio_gc_w($s, $px) <= $width)
        return $s;
    $ell = "\xE2\x80\xA6";
    return rtrim(mb_substr($s, 0, aio_gc_fit($s, $px, $width - aio_gc_w($ell, $px)), 'UTF-8')) . $ell;
}

// Lines of at most $width, broken after spaces and separators (a long word
// by characters); more than $max lines: the last one ends with "…".
function aio_gc_wrap($s, $px, $width, $max, $spaces = false)
{
    $lines = array();
    $cur = '';
    // Not after a dot before a digit: "5.1" stays whole. $spaces: only after
    // spaces ("Real-Debrid" whole).
    $re = $spaces ? '~(?<= )~u' : '~(?<=[ _\\-/,)\\]])|(?<=\\.)(?![0-9])~u';
    foreach (preg_split($re, $s, -1, PREG_SPLIT_NO_EMPTY) as $t)
    {
        if (aio_gc_w($cur . $t, $px) <= $width)
        {
            $cur .= $t;
            continue;
        }
        if (trim($cur) !== '')
            $lines[] = rtrim($cur);
        while (aio_gc_w($t, $px) > $width && count($lines) <= $max)
        {
            $n = max(1, aio_gc_fit($t, $px, $width));
            $lines[] = mb_substr($t, 0, $n, 'UTF-8');
            $t = mb_substr($t, $n, mb_strlen($t, 'UTF-8') - $n, 'UTF-8');
        }
        $cur = ltrim($t);
        // Enough to know it is cut: the rest is not wrapped.
        if (count($lines) > $max)
            break;
    }
    if (trim($cur) !== '' && count($lines) <= $max)
        $lines[] = rtrim($cur);
    if (count($lines) > $max)
    {
        $lines = array_slice($lines, 0, $max);
        $ell = "\xE2\x80\xA6";
        $l = $lines[$max - 1];
        $lines[$max - 1] = rtrim(mb_substr($l, 0, aio_gc_fit($l, $px, $width - aio_gc_w($ell, $px)),
            'UTF-8')) . $ell;
    }
    return $lines;
}

// Like aio_gc_wrap, but by items of " · ": an item goes to the next line
// whole and no line ends with the separator; an item wider than a line is
// wrapped by itself.
function aio_gc_wrap_items($s, $px, $width, $max)
{
    $lines = array();
    $cur = '';
    foreach (explode(' · ', $s) as $item)
    {
        if (trim($item) === '')
            continue;
        $try = $cur === '' ? $item : "$cur · $item";
        if (aio_gc_w($try, $px) <= $width)
        {
            $cur = $try;
            continue;
        }
        if ($cur !== '')
            $lines[] = $cur;
        $sub = aio_gc_wrap($item, $px, $width, $max + 1);
        $cur = (string) array_pop($sub);
        $lines = array_merge($lines, $sub);
        if (count($lines) > $max)
            break;
    }
    if ($cur !== '' && count($lines) <= $max)
        $lines[] = $cur;
    if (count($lines) > $max)
    {
        $lines = array_slice($lines, 0, $max);
        $ell = "\xE2\x80\xA6";
        $l = $lines[$max - 1];
        $l = mb_substr($l, 0, aio_gc_fit($l, $px, $width - aio_gc_w($ell, $px)), 'UTF-8');
        $lines[$max - 1] = preg_replace('/[ \x{00B7}]+$/u', '', $l) . $ell;
    }
    return $lines;
}

// Voices "Дубляж: Red Head Sound · LostFilm, Кубик в Кубе · оригинал" in lines
// of at most $width: broken after " · " or ", " (the comma stays), never
// inside a name; a group that fits a line but not the rest of this one starts
// the next; $alone: the first group ("Дубляж: …") has lines of its own; more
// than $max lines: the last ends with "…".
function aio_gc_wrap_voices($s, $px, $width, $max, $alone = false)
{
    $lines = array();
    $cur = '';
    foreach (explode(' · ', $s) as $i => $item)
    {
        if ($i === 1 && $alone && $cur !== '')
        {
            $lines[] = $cur;
            $cur = '';
        }
        if ($cur !== '' && aio_gc_w("$cur · $item", $px) > $width && aio_gc_w($item, $px) <= $width)
        {
            $lines[] = $cur;
            $cur = '';
            if (count($lines) > $max)
                break;
        }
        $units = explode(', ', $item);
        foreach ($units as $j => $u)
        {
            $sep = $cur === '' ? '' : ($j > 0 ? ', ' : ' · ');
            // Room for the comma a break after it would leave.
            if (aio_gc_w($cur . $sep . $u . ($j < count($units) - 1 ? ',' : ''), $px) <= $width)
            {
                $cur .= $sep . $u;
                continue;
            }
            if ($cur !== '')
                $lines[] = $cur . ($j > 0 ? ',' : '');
            $cur = $u;
            if (count($lines) > $max)
                break 2;
        }
    }
    if ($cur !== '')
        $lines[] = $cur;
    if (count($lines) > $max)
    {
        $ell = "\xE2\x80\xA6";
        $l = $lines[$max - 1];
        $l = mb_substr($l, 0, aio_gc_fit($l, $px, $width - aio_gc_w($ell, $px)), 'UTF-8');
        $lines[$max - 1] = preg_replace('/[ ,\x{00B7}]+$/u', '', $l) . $ell;
        $lines = array_slice($lines, 0, $max);
    }
    // A name wider than a line alone.
    foreach ($lines as $i => $l)
        $lines[$i] = aio_gc_cut_w($l, $px, $width);
    return $lines;
}

// --- Builders: the shapes of vendor GCompGeom / GCompsFactory (non-zero align
// fields only), as proven on the device.

function aio_gc_align($x, $y, $ubw, $ubh, $ha, $va, $bha, $bva, $base_id)
{
    $a = array();
    foreach (array(GCompAlignDef::x => $x, GCompAlignDef::y => $y,
        GCompAlignDef::use_base_width => $ubw, GCompAlignDef::use_base_height => $ubh,
        GCompAlignDef::halign => $ha, GCompAlignDef::valign => $va,
        GCompAlignDef::base_halign => $bha, GCompAlignDef::base_valign => $bva,
        GCompAlignDef::base_id => $base_id) as $k => $v)
    {
        if ($v)
            $a[$k] = $v;
    }
    return $a;
}

function aio_gc_geom($w, $h, $align)
{
    $g = array(GCompGeometryDef::w => $w, GCompGeometryDef::h => $h);
    if ($align)
        $g[GCompGeometryDef::align_def] = $align;
    return $g;
}

// Top-left corner at (x, y) of the parent.
function aio_gc_at($w, $h, $x, $y)
{
    return aio_gc_geom($w, $h, aio_gc_align($x, $y, false, false, 0, 0, 0, 0, null));
}

// Left edge at x, vertically centred in the parent.
function aio_gc_left_center($w, $h, $x)
{
    return aio_gc_geom($w, $h, aio_gc_align($x, 0, false, false,
        HALIGN_LEFT, VALIGN_CENTER, HALIGN_LEFT, VALIGN_CENTER, null));
}

// A text label; the text is cleaned of line breaks and '|' here.
function aio_gc_ttf($id, $geom, $text, $size, $color, $more = array())
{
    $spec = array(
        GCompTtfLabelDef::text => aio_clean($text),
        GCompTtfLabelDef::text_color => $color,
        GCompTtfLabelDef::font_size => $size);
    foreach ($more as $k => $v)
        $spec[$k] = $v;
    return array(
        GComponentDef::id => $id,
        GComponentDef::geom_def => $geom,
        GComponentDef::options => GCOMP_OPT_TTF_LAYOUT_FIX | GCOMP_OPT_TTF_COMPACT_HEIGHT,
        GComponentDef::kind => GCOMPONENT_TTF_LABEL,
        GComponentDef::specific_def => $spec);
}

// One line; the shell cuts it with "..." if our estimate was short.
function aio_gc_cut($id, $geom, $text, $size, $color, $more = array())
{
    $more[GCompTtfLabelDef::fit] = GCOMP_TEXT_FIT_APPEND_ELLIPSIS;
    return aio_gc_ttf($id, $geom, $text, $size, $color, $more);
}

function aio_gc_rect($geom, $color, $id = null)
{
    $r = array(
        GComponentDef::geom_def => $geom,
        GComponentDef::kind => GCOMPONENT_RECT,
        GComponentDef::specific_def => array(GCompRectDef::color => $color));
    if ($id !== null)
        $r[GComponentDef::id] = $id;
    return $r;
}

// A picture; a poster gets the shell's own "loading" and "no poster" pictures,
// as the vendor card (plugin_file://%<plugin>% works from our plugin, device 03.10.2026).
function aio_gc_image($geom, $url, $poster = false)
{
    return array(
        GComponentDef::geom_def => $geom,
        GComponentDef::margins_def => null,
        GComponentDef::kind => GCOMPONENT_IMAGE,
        GComponentDef::specific_def => array(
            GCompImageDef::url => $url,
            GCompImageDef::keep_aspect_ratio => true,
            GCompImageDef::upscale_enabled => true,
            GCompImageDef::not_loaded_url => $poster ?
                'plugin_file://%shell_ext%/icons/loading_movie_poster.png' : null,
            GCompImageDef::load_failed_url => $poster ?
                'plugin_file://%shell_ext%/icons/no_movie_poster.png' : null,
            GCompImageDef::low_quality_url => null,
            GCompImageDef::alpha => null,
            GCompImageDef::mix_alpha => null,
            GCompImageDef::mix_color => null));
}

// $options 0 = clipping panel, default NO_CLIP (vendor panel()).
function aio_gc_panel($id, $geom, $children, $options = GCOMP_OPT_NO_CLIP)
{
    $p = array(
        GComponentDef::id => $id,
        GComponentDef::geom_def => $geom,
        GComponentDef::kind => GCOMPONENT_PANEL,
        GComponentDef::specific_def => array(GCompPanelDef::children => $children));
    if ($options)
        $p[GComponentDef::options] = $options;
    return $p;
}

function aio_gc_change($id, $geom, $transition, $children = null)
{
    return array(
        ChangeGCompDef::id => $id,
        ChangeGCompDef::geom_def => $geom,
        ChangeGCompDef::props => null,
        ChangeGCompDef::view_position => null,
        ChangeGCompDef::selected => null,
        ChangeGCompDef::children => $children,
        ChangeGCompDef::transition => $transition);
}

// The rid of the list goes with every key: a screen of a replaced list is
// stale.
function aio_gc_act($control_id, $rid, $params = array())
{
    return aio_input($control_id, array_merge(array('rid' => $rid), $params));
}
