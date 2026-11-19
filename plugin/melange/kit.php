<?php
// The core of the UI kit: text, icon, row, stack over the primitives of
// gcomps.php, by the tokens of kit_tokens.php. A component takes its font size
// once: one call measures and draws, by the width model of the shell
// (kit_font.php: the sum of floor(advance * size / upem), no kerning). Every
// builder returns an item, its components and its visible edges; row and
// stack lay items out by those edges (the top of the capitals, the baseline,
// the strokes of an icon, the edge of a box), not by their boxes.
// Required by main.php after gcomps.php; calls mel_log (main.php) and
// mel_clean (parse.php). PHP 5.3.6, firmware API only.
// Types in the PHPDoc (MelKitItem): phpstan-types.neon of the repository.
//
// An item (MelKitItem), coordinates of the parent:
//   kind      text, icon, box, row, stack, or the component (plate,
//             button, chip, badges, key, bar);
//   defs      its components;
//   x0, x1    the visible left and right (a text: its advances by the model;
//             a component on a plate: the plate);
//   top, bottom  the visible top and bottom: of a text the top of its
//             capitals and its (last) baseline, of an icon its strokes;
//   mid       the line a row aligns it by: of a text the centre of its
//             capitals (first line), of an icon of its strokes, of a row its
//             line, of a stack that of its first block;
//   cy        the point it was asked to be centred at (a text: its box top +
//             h / 2, the centre of the capitals +-0.8 px [device]);
//   caps      it is or starts with a text (a row aligns to the first such);
//   ids       the ids of its components (only in a test, see $trace).
// A text also has w, px, face, lines, base (of its first line), step.

require_once dirname(__FILE__) . '/gcomps.php';

class MelKit
{
    // The trace of a test: null at work. A test sets array(): the builders
    // give their components ids (mk<n>) and row and stack add
    // array('kind', 'name', 'token', 'ids'): token, the gap (px, or a list
    // of one per pair); ids, a list of the ids of each element.
    /** @var list<array{kind: string, name: string, token: int|list<int>, ids: list<list<string>>}>|null */
    public static $trace = null;
    /** @var array{wide: string, faces: array<string, array{upem: int, avg: int, w: array<string, int>}>}|null */
    private static $font = null;
    /** @var int */
    private static $n = 0;
    /** @var array<string, bool> */
    private static $missed = array();

    // --- The tables of kit_tokens.php.

    // A row of mel_k_pic(); an unknown name: a line in the log (once) and an
    // empty picture 0 x 0.
    /**
     * @param string $name
     * @return array{url: string, w: int, h: int, mid: array{float, float}|null, ink: array{int, int, int, int}|null}
     */
    public static function pic($name)
    {
        $t = mel_k_pic();
        if (isset($t[$name]))
            return $t[$name];
        self::miss('picture', $name);
        return array('url' => '', 'w' => 0, 'h' => 0, 'mid' => null, 'ink' => null);
    }

    // A row of mel_k_corner(); an unknown component: a line in the log
    // (once), r 0 and no corners.
    /**
     * @param string $key
     * @return array{r: int, ring: int, color: string, tl: string, tr: string, bl: string, br: string}
     */
    public static function corner($key)
    {
        $t = mel_k_corner();
        if (isset($t[$key]))
            return $t[$key];
        self::miss('corner', $key);
        return array('r' => 0, 'ring' => 0, 'color' => MEL_K_C_NONE, 'tl' => '', 'tr' => '', 'bl' => '', 'br' => '');
    }

    /**
     * @param string $what
     * @param string $name
     * @return void
     */
    private static function miss($what, $name)
    {
        if (isset(self::$missed["$what $name"]))
            return;
        self::$missed["$what $name"] = true;
        mel_log("kit: no $what '$name'");
    }

    // --- Width: the model of the shell.

    /**
     * @param string $face
     * @return array{upem: int, avg: int, w: array<string, int>}
     */
    private static function face($face)
    {
        // The table (36 KB) is read on the first measure, not with the plugin.
        if (self::$font === null)
        {
            require_once dirname(__FILE__) . '/kit_font.php';
            self::$font = mel_kit_font();
        }
        $f = self::$font['faces'];
        return isset($f[$face]) ? $f[$face] : $f['regular'];
    }

    // The characters of $s (bytes if it is not valid UTF-8).
    /**
     * @param string $s
     * @return list<string>
     */
    private static function chars($s)
    {
        $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        return $c === false ? str_split($s) : $c;
    }

    // The advance of each character of $s at $px, px.
    /**
     * @param list<string> $chars
     * @param int $px
     * @param string $face
     * @return list<int>
     */
    private static function advances($chars, $px, $face)
    {
        $f = self::face($face);
        $out = array();
        foreach ($chars as $c)
        {
            if (isset($f['w'][$c]))
                $u = $f['w'][$c];
            else
                $u = preg_match(self::$font['wide'], $c) ? $f['upem'] : $f['avg'];
            $out[] = (int) floor($u * $px / $f['upem']);
        }
        return $out;
    }

    // Width of $s at $px in $face (MEL_K_FACE_*), px, as the shell sets it.
    /**
     * @param string $s
     * @param int $px
     * @param string $face
     * @return int
     */
    public static function w($s, $px, $face = '')
    {
        return array_sum(self::advances(self::chars($s), $px, $face));
    }

    // $s if it fits $w, else its longest start that fits with "…" (spaces and
    // , . ; : · before it dropped).
    /**
     * @param string $s
     * @param int $px
     * @param string $face
     * @param int $w
     * @return string
     */
    public static function cut($s, $px, $face, $w)
    {
        $c = self::chars($s);
        $a = self::advances($c, $px, $face);
        if (array_sum($a) <= $w)
            return $s;
        $ell = "\xE2\x80\xA6";
        $room = $w - self::w($ell, $px, $face);
        $n = 0;
        for ($sum = 0; $n < count($c) && $sum + $a[$n] <= $room; $n++)
            $sum += $a[$n];
        return preg_replace('/[\s.,;:\x{00B7}]+$/u', '', implode('', array_slice($c, 0, $n))) . $ell;
    }

    // $s in lines of $w at most, broken after spaces (a word wider than a line
    // by characters); more than $n lines: the last ends with "…".
    /**
     * @param string $s
     * @param int $px
     * @param string $face
     * @param int $w
     * @param int $n
     * @return list<string>
     */
    public static function wrap($s, $px, $face, $w, $n)
    {
        // Lines and what joins each to the next: ' ' between words, '' inside one.
        $lines = array();
        $join = array();
        if ($n < 1)
            return $lines;
        $sp = self::w(' ', $px, $face);
        $cur = '';
        $cw = 0;
        foreach (preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) as $word)
        {
            // Past $n + 1 lines the rest is not read: the last is cut anyway.
            if (count($lines) > $n)
                break;
            $ww = self::w($word, $px, $face);
            if ($cur !== '' && $cw + $sp + $ww <= $w)
            {
                $cur .= ' ' . $word;
                $cw += $sp + $ww;
                continue;
            }
            if ($cur !== '')
            {
                $lines[] = $cur;
                $join[] = ' ';
            }
            while ($ww > $w && count($lines) <= $n)
            {
                $c = self::chars($word);
                $a = self::advances($c, $px, $face);
                for ($k = 0, $sum = 0; $k < count($c) && $sum + $a[$k] <= $w; $k++)
                    $sum += $a[$k];
                $k = max(1, $k);
                $lines[] = implode('', array_slice($c, 0, $k));
                $join[] = '';
                $word = implode('', array_slice($c, $k));
                $ww = self::w($word, $px, $face);
            }
            $cur = $word;
            $cw = $ww;
        }
        if ($cur !== '')
        {
            $lines[] = $cur;
            $join[] = ' ';
        }
        if (count($lines) <= $n)
            return $lines;
        // The last line with the start of the next: cut, it ends with "…".
        $last = $lines[$n - 1] . $join[$n - 1] . $lines[$n];
        $lines = array_slice($lines, 0, $n - 1);
        $lines[] = self::cut($last, $px, $face, $w);
        return $lines;
    }

    // --- Components.

    // The id of a component: $o['id'] (with $sfx), else one of its own only
    // in a test.
    /**
     * @param array<string, mixed> $o
     * @param string $sfx
     * @return string|null
     */
    private static function id($o, $sfx = '')
    {
        if (isset($o['id']))
            return $o['id'] . $sfx;
        return self::$trace === null ? null : 'mk' . ++self::$n . $sfx;
    }

    // A text with the centre of its capitals at $y, starting at $x. $o: face
    // (MEL_K_FACE_*, default regular); w, the width it may take (cut with "…";
    // with lines, wrapped); lines, the most lines (default 1: a label each,
    // the step $px + ls); ls, line_spacing (default 0); id (of a line: id_<n>
    // when lines > 1).
    /**
     * @param string $s
     * @param int $px
     * @param string $color
     * @param int $x
     * @param int|float $y
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function text($s, $px, $color, $x = 0, $y = 0, $o = array())
    {
        $face = isset($o['face']) ? (string) $o['face'] : MEL_K_FACE_REGULAR;
        $n = isset($o['lines']) ? max(1, (int) $o['lines']) : 1;
        $ls = isset($o['ls']) ? (int) $o['ls'] : 0;
        $s = mel_clean($s);
        if (!isset($o['w']))
            $lines = array($s);
        elseif ($n > 1)
            $lines = self::wrap($s, $px, $face, (int) $o['w'], $n);
        else
            $lines = array(self::cut($s, $px, $face, (int) $o['w']));
        if (!$lines)
            $lines = array('');
        // The box: h - px even, so the capitals are centred in it to a pixel.
        $h = $px + 2 * (int) ceil($px / 4);
        $caps = mel_k_cap();
        $cap = isset($caps[$px]) ? $caps[$px] : 0.7135 * $px;
        $top = (int) round($y - $h / 2);
        // The baseline in a box of LAYOUT_FIX [device, the probe of the metric].
        $base = $top + intval(($h - $px) / 2) + (int) round(($px + $cap) / 2);
        $step = $px + $ls;
        $more = $face !== '' ? array(GCompTtfLabelDef::font_face => $face) : array();
        $defs = array();
        $ids = array();
        $w = 0;
        foreach ($lines as $i => $l)
        {
            $lw = self::w($l, $px, $face);
            $w = max($w, $lw);
            $id = self::id($o, count($lines) > 1 ? "_$i" : '');
            // Room past the width: the text is cut here, not by the shell.
            $defs[] = mel_gc_ttf($id, mel_gc_at($lw + $px, $h, $x, $top + $i * $step), $l, $px, $color, $more);
            if ($id !== null)
                $ids[] = $id;
        }
        return array('kind' => 'text', 'defs' => $defs, 'x0' => $x, 'x1' => $x + $w,
            'top' => $base - (int) round($cap), 'bottom' => $base + (count($lines) - 1) * $step,
            'mid' => $base - $cap / 2, 'cy' => $top + $h / 2, 'caps' => true, 'ids' => $ids,
            'w' => $w, 'px' => $px, 'face' => $face, 'lines' => $lines, 'base' => $base, 'step' => $step);
    }

    // A picture of the table (an icon k_icon_*, a key k_key_*) $size px wide,
    // the centre of its strokes (mid) at ($cx, $cy), in $color (mix_color).
    // Its edges: the box of its strokes (ink). An unknown name: no component.
    // $o: id.
    /**
     * @param string $name
     * @param int $size
     * @param string $color
     * @param int|float $cx
     * @param int|float $cy
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function icon($name, $size, $color, $cx = 0, $cy = 0, $o = array())
    {
        $p = self::pic($name);
        if ($p['url'] === '')
        {
            $x = (int) round($cx);
            $y = (int) round($cy);
            return array('kind' => 'icon', 'defs' => array(), 'x0' => $x, 'x1' => $x, 'top' => $y, 'bottom' => $y,
                'mid' => $y, 'cy' => $y, 'caps' => false, 'ids' => array());
        }
        $k = $size / $p['w'];
        $mid = $p['mid'] !== null ? $p['mid'] : array($p['w'] / 2, $p['h'] / 2);
        $ink = $p['ink'] !== null ? $p['ink'] : array(0, 0, $p['w'], $p['h']);
        $ix = (int) round($cx - $mid[0] * $k);
        $iy = (int) round($cy - $mid[1] * $k);
        $img = mel_gc_image(mel_gc_at($size, (int) round($p['h'] * $k), $ix, $iy), $p['url']);
        $img[GComponentDef::specific_def][GCompImageDef::mix_color] = $color;
        $img[GComponentDef::specific_def][GCompImageDef::mix_alpha] = 255;
        $ids = array();
        $id = self::id($o);
        if ($id !== null)
        {
            $img[GComponentDef::id] = $id;
            $ids[] = $id;
        }
        return array('kind' => 'icon', 'defs' => array($img),
            'x0' => $ix + (int) round($ink[0] * $k), 'x1' => $ix + (int) round($ink[2] * $k),
            'top' => $iy + (int) round($ink[1] * $k), 'bottom' => $iy + (int) round($ink[3] * $k),
            'mid' => $iy + $mid[1] * $k, 'cy' => $iy + $mid[1] * $k, 'caps' => false, 'ids' => $ids);
    }

    // Components of the caller as an item: their visible box ($x, $y, $w, $h
    // in the parent; mid its centre unless $mid). In a test the components
    // without an id get one.
    /**
     * @param list<MelGcComp> $defs
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @param int|float|null $mid
     * @return MelKitItem
     */
    public static function box($defs, $x, $y, $w, $h, $mid = null)
    {
        $ids = array();
        foreach ($defs as $i => $d)
        {
            if (!isset($d[GComponentDef::id]) && self::$trace !== null)
                $defs[$i][GComponentDef::id] = $d[GComponentDef::id] = self::id(array());
            if (isset($d[GComponentDef::id]))
                $ids[] = (string) $d[GComponentDef::id];
        }
        $m = $mid === null ? $y + $h / 2 : $mid;
        return array('kind' => 'box', 'defs' => $defs, 'x0' => $x, 'x1' => $x + $w, 'top' => $y, 'bottom' => $y + $h,
            'mid' => $m, 'cy' => $m, 'caps' => false, 'ids' => $ids);
    }

    // The item moved by ($dx, $dy): its components but those placed by a
    // sibling (base_id, base_to_prev), which move with it.
    /**
     * @param MelKitItem $it
     * @param int $dx
     * @param int $dy
     * @return MelKitItem
     */
    public static function move($it, $dx, $dy)
    {
        if (!$dx && !$dy)
            return $it;
        foreach ($it['defs'] as $i => $d)
        {
            $g = $d[GComponentDef::geom_def];
            $a = isset($g[GCompGeometryDef::align_def]) ? $g[GCompGeometryDef::align_def] : array();
            if (!empty($a[GCompAlignDef::base_id]) || !empty($a[GCompAlignDef::base_to_prev]))
                continue;
            foreach (array(GCompAlignDef::x => $dx, GCompAlignDef::y => $dy) as $k => $v)
            {
                $v += isset($a[$k]) ? (int) $a[$k] : 0;
                if ($v)
                    $a[$k] = $v;
                else
                    unset($a[$k]);
            }
            $g[GCompGeometryDef::align_def] = $a;
            $it['defs'][$i][GComponentDef::geom_def] = $g;
        }
        $it['x0'] += $dx;
        $it['x1'] += $dx;
        foreach (array('top', 'bottom', 'mid', 'cy', 'base') as $k)
        {
            if (isset($it[$k]))
                $it[$k] += $dy;
        }
        return $it;
    }

    // --- Layout.

    // The gap after element $i: $gap, or its $i-th.
    /**
     * @param int|list<int> $gap
     * @param int $i
     * @return int
     */
    private static function gap($gap, $i)
    {
        return is_array($gap) ? (int) $gap[$i] : (int) $gap;
    }

    /**
     * @param string $kind
     * @param list<MelKitItem> $items
     * @param int|list<int> $gap
     * @param string $name
     * @return void
     */
    private static function trace($kind, $items, $gap, $name)
    {
        $ids = array();
        foreach ($items as $it)
            $ids[] = $it['ids'];
        self::$trace[] = array('kind' => $kind, 'name' => $name, 'token' => $gap, 'ids' => $ids);
    }

    // The items one after another, $gap (a token, or a list of one per pair)
    // between the visible right of one and the left of the next, their mid on
    // the centre of the capitals of the first text (else of the first item),
    // to a pixel. $x: the left of the first (default: where it is); $y: the
    // centre the first text is set at (default: where it is).
    /**
     * @param list<MelKitItem> $items
     * @param int|list<int> $gap
     * @param int|null $x
     * @param int|float|null $y
     * @param string $name
     * @return MelKitItem
     */
    public static function row($items, $gap, $x = null, $y = null, $name = '')
    {
        $ref = 0;
        foreach ($items as $i => $it)
        {
            if ($it['caps'])
            {
                $ref = $i;
                break;
            }
        }
        if ($y !== null && $items)
            $items[$ref] = self::move($items[$ref], 0, (int) round($y - $items[$ref]['cy']));
        $line = $items ? $items[$ref]['mid'] : 0;
        $at = $x !== null ? $x : ($items ? $items[0]['x0'] : 0);
        foreach ($items as $i => $it)
        {
            $items[$i] = self::move($it, $at - $it['x0'], $i === $ref ? 0 : (int) round($line - $it['mid']));
            $at = $items[$i]['x1'] + ($i + 1 < count($items) ? self::gap($gap, $i) : 0);
        }
        $out = self::join('row', $items);
        $out['mid'] = $line;
        if ($items)
        {
            $out['cy'] = $items[$ref]['cy'];
            $out['caps'] = $items[$ref]['caps'];
        }
        if (self::$trace !== null)
            self::trace('row', $items, $gap, $name);
        return $out;
    }

    // The blocks from the top down, $gap (a token, or a list of one per pair)
    // between the visible bottom of one and the top of the next (a baseline
    // to the top of the capitals, the strokes of an icon, the edge of a box).
    // $y: the top of the first (default: where it is). Their x stay.
    /**
     * @param list<MelKitItem> $blocks
     * @param int|list<int> $gap
     * @param int|null $y
     * @param string $name
     * @return MelKitItem
     */
    public static function stack($blocks, $gap, $y = null, $name = '')
    {
        $at = $y;
        foreach ($blocks as $i => $b)
        {
            if ($at !== null)
                $blocks[$i] = self::move($b, 0, $at - $b['top']);
            $at = $blocks[$i]['bottom'] + ($i + 1 < count($blocks) ? self::gap($gap, $i) : 0);
        }
        $out = self::join('stack', $blocks);
        if ($blocks)
        {
            $out['mid'] = $blocks[0]['mid'];
            $out['cy'] = $blocks[0]['cy'];
            $out['caps'] = $blocks[0]['caps'];
        }
        if (self::$trace !== null)
            self::trace('stack', $blocks, $gap, $name);
        return $out;
    }

    // The items as one: their components in order, the box of their edges.
    /**
     * @param string $kind
     * @param list<MelKitItem> $items
     * @return MelKitItem
     */
    private static function join($kind, $items)
    {
        $out = array('kind' => $kind, 'defs' => array(), 'x0' => 0, 'x1' => 0, 'top' => 0, 'bottom' => 0,
            'mid' => 0, 'cy' => 0, 'caps' => false, 'ids' => array());
        foreach ($items as $i => $it)
        {
            $out['defs'] = array_merge($out['defs'], $it['defs']);
            $out['ids'] = array_merge($out['ids'], $it['ids']);
            foreach (array('x0' => 'min', 'x1' => 'max', 'top' => 'min', 'bottom' => 'max') as $k => $f)
                $out[$k] = $i ? $f($out[$k], $it[$k]) : $it[$k];
        }
        return $out;
    }

    // --- Components on a plate.

    // A plate (ring) of mel_k_corner() $key, $w x $h with its top left at
    // ($x, $y): its 4 corners r x r and rects of its colour between them, butt
    // to butt (a ring: only its band). Smaller than 2r: 2r. Its edges: the box.
    /**
     * @param string $key
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @return MelKitItem
     */
    public static function plate($key, $x, $y, $w, $h)
    {
        $c = self::corner($key);
        $r = $c['r'];
        $t = $c['ring'];
        $w = max($w, 2 * $r);
        $h = max($h, 2 * $r);
        $iw = $w - 2 * $r;
        $ih = $h - 2 * $r;
        // x, y, w, h: of a ring the 4 sides of its band, of a fill the middle
        // column and the two sides between the corners.
        if ($t > 0)
            $rects = array(array($x + $r, $y, $iw, $t), array($x + $r, $y + $h - $t, $iw, $t),
                array($x, $y + $r, $t, $ih), array($x + $w - $t, $y + $r, $t, $ih));
        else
            $rects = array(array($x + $r, $y, $iw, $h), array($x, $y + $r, $r, $ih), array($x + $w - $r, $y + $r, $r, $ih));
        $defs = array();
        foreach ($rects as $q)
        {
            if ($q[2] > 0 && $q[3] > 0)
                $defs[] = mel_gc_rect(mel_gc_at($q[2], $q[3], $q[0], $q[1]), $c['color']);
        }
        if ($r > 0)
        {
            $at = array('tl' => array($x, $y), 'tr' => array($x + $w - $r, $y), 'bl' => array($x, $y + $h - $r),
                'br' => array($x + $w - $r, $y + $h - $r));
            foreach ($at as $k => $q)
            {
                $p = self::pic($c[$k]);
                if ($p['url'] !== '')
                    $defs[] = mel_gc_image(mel_gc_at($r, $r, $q[0], $q[1]), $p['url']);
            }
        }
        $out = self::box($defs, $x, $y, $w, $h);
        $out['kind'] = 'plate';
        return $out;
    }

    // $content on $plate: the edges of the plate, the line of the content
    // (mid, caps); cy the centre of the plate.
    /**
     * @param string $kind
     * @param MelKitItem $plate
     * @param MelKitItem $content
     * @return MelKitItem
     */
    private static function on($kind, $plate, $content)
    {
        $out = self::join($kind, array($plate, $content));
        $out['mid'] = $content['mid'];
        $out['cy'] = ($plate['top'] + $plate['bottom']) / 2;
        $out['caps'] = $content['caps'];
        return $out;
    }

    // A pill button MEL_K_BTN_H high: the icon $icon (k_icon_*,
    // MEL_K_ICON_BUTTON; '' none) -MEL_K_ICON_GAP_BUTTON- the label
    // (MEL_K_PX_ROW) between the pads MEL_K_BTN_PAD; the left of the plate at
    // $x, its centre and the capitals at $y. In focus white on the accent
    // (btn_on), else on the surface (btn). The label is never cut: a button
    // wider than $o['w'] is logged and set whole. $o: w; off (disabled: text 3
    // on the surface, no focus).
    /**
     * @param string $label
     * @param string $icon
     * @param bool $focus
     * @param int $x
     * @param int|float $y
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function button($label, $icon, $focus, $x = 0, $y = 0, $o = array())
    {
        $off = !empty($o['off']);
        $on = $focus && !$off;
        $fg = $off ? MEL_K_C_TEXT3 : ($on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT1);
        $c = self::text($label, MEL_K_PX_ROW, $fg, $x + MEL_K_BTN_PAD, $y);
        if ($icon !== '')
            $c = self::row(array(self::icon($icon, MEL_K_ICON_BUTTON, $fg), $c), MEL_K_ICON_GAP_BUTTON,
                $x + MEL_K_BTN_PAD, $y, 'button');
        $w = $c['x1'] - $c['x0'] + 2 * MEL_K_BTN_PAD;
        if (isset($o['w']) && $w > $o['w'])
            mel_log("kit: button '$label' is $w px wide, more than {$o['w']}");
        $p = self::plate($on ? 'btn_on' : 'btn', $x, (int) round($y - MEL_K_BTN_H / 2), $w, MEL_K_BTN_H);
        return self::on('button', $p, $c);
    }

    // A chip MEL_K_CHIP_H high: $text (MEL_K_PX_CAPTION) in the colour of
    // $kind, ok («main») or warn («needs a key»), on its tint (chip_ok,
    // chip_warn), pads MEL_K_CHIP_PAD; the left at $x, the plate and the
    // capitals centred at $y.
    /**
     * @param string $text
     * @param string $kind
     * @param int $x
     * @param int|float $y
     * @return MelKitItem
     */
    public static function chip($text, $kind, $x = 0, $y = 0)
    {
        $fg = $kind === 'warn' ? MEL_K_C_WARN : ($kind === 'ok' ? MEL_K_C_OK : MEL_K_C_TEXT2);
        $t = self::text($text, MEL_K_PX_CAPTION, $fg, $x + MEL_K_CHIP_PAD, $y);
        $p = self::plate("chip_$kind", $x, (int) round($y - MEL_K_CHIP_H / 2), $t['x1'] - $t['x0'] + 2 * MEL_K_CHIP_PAD,
            MEL_K_CHIP_H);
        return self::on('chip', $p, $t);
    }

    // The plates b2 k_badge_<id> of $ids (which ids: the caller) in a row
    // MEL_K_PLATE_GAP apart, each as wide as its picture (mel_k_pic()); the
    // left at $x, their centre at $y. An unknown id: logged, left out.
    /**
     * @param list<string> $ids
     * @param int $x
     * @param int|float $y
     * @param string $name
     * @return MelKitItem
     */
    public static function badges($ids, $x = 0, $y = 0, $name = 'badges')
    {
        $items = array();
        foreach ($ids as $id)
        {
            $p = self::pic("k_badge_$id");
            if ($p['url'] !== '')
                $items[] = self::box(array(mel_gc_image(mel_gc_at($p['w'], $p['h'], 0, 0), $p['url'])), 0, 0, $p['w'], $p['h']);
        }
        $out = $items ? self::row($items, MEL_K_PLATE_GAP, $x, $y, $name) : self::box(array(), $x, (int) round($y), 0, 0);
        $out['kind'] = 'badges';
        return $out;
    }

    // A key of the remote on its plate (key) MEL_K_KEY_H high: 'pp' the two
    // runs «P+» -MEL_K_KEY_GAP- «P−» (MEL_K_PX_KEY) between the pads
    // MEL_K_KEY_PAD; else the picture $key (k_icon_menu MEL_K_ICON_MENU,
    // k_key_info MEL_K_ICON_INFO) by the centre of its strokes on a plate
    // MEL_K_KEY_W wide. The left at $x, the centre at $y.
    /**
     * @param string $key
     * @param int $x
     * @param int|float $y
     * @return MelKitItem
     */
    public static function key($key, $x = 0, $y = 0)
    {
        $top = (int) round($y - MEL_K_KEY_H / 2);
        if ($key === 'pp')
        {
            $c = self::row(array(self::text('P+', MEL_K_PX_KEY, MEL_K_C_TEXT1),
                self::text("P\xE2\x88\x92", MEL_K_PX_KEY, MEL_K_C_TEXT1)), MEL_K_KEY_GAP, $x + MEL_K_KEY_PAD, $y, 'key');
            $w = $c['x1'] - $c['x0'] + 2 * MEL_K_KEY_PAD;
        }
        else
        {
            $size = $key === 'k_key_info' ? MEL_K_ICON_INFO : MEL_K_ICON_MENU;
            $c = self::icon($key, $size, MEL_K_C_TEXT1, $x + MEL_K_KEY_W / 2, $top + MEL_K_KEY_H / 2);
            $w = MEL_K_KEY_W;
        }
        return self::on('key', self::plate('key', $x, $top, $w, MEL_K_KEY_H), $c);
    }

    // The hint line: for each array(key, label) of $items the key (see key)
    // -MEL_K_KEY_LABEL- its label (MEL_K_PX_HINT, text 2) -MEL_K_HINT_GAP-
    // the next; the left at $x, the line at $y.
    /**
     * @param list<array{string, string}> $items
     * @param int|null $x
     * @param int|float|null $y
     * @param string $name
     * @return MelKitItem
     */
    public static function hint($items, $x = null, $y = null, $name = 'hint')
    {
        $parts = array();
        $gaps = array();
        foreach ($items as $it)
        {
            $parts[] = self::key($it[0]);
            $parts[] = self::text($it[1], MEL_K_PX_HINT, MEL_K_C_TEXT2);
            $gaps[] = MEL_K_KEY_LABEL;
            $gaps[] = MEL_K_HINT_GAP;
        }
        return self::row($parts, $gaps, $x, $y, $name);
    }

    // A bar of $role, $w x $h at ($x, $y); its edges the box:
    //   progress, info: done $share of the width (at least MEL_K_BAR_MIN when
    //     $share > 0) in MEL_K_C_BAR_DONE (info: MEL_K_C_BAR_INFO), the rest
    //     MEL_K_C_BAR_REST; $o['edge']: a line MEL_K_C_BAR_EDGE 1 px over it;
    //   scroll, upright: the track MEL_K_C_SCROLL_TRACK and the thumb
    //     MEL_K_C_SCROLL_THUMB, $o['part'] of the height (default all, at
    //     least MEL_K_THUMB_MIN), at $share of the way down;
    //   ring: the focus ring (ring) round the box, MEL_K_RING_W outside it.
    // $o['id']: the id of the thumb or of the done part. Width or height 0, or
    // an unknown role (logged): nothing.
    /**
     * @param string $role
     * @param int $x
     * @param int $y
     * @param int $w
     * @param int $h
     * @param int|float $share
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function bar($role, $x, $y, $w, $h, $share = 0, $o = array())
    {
        $roles = array('progress' => MEL_K_C_BAR_DONE, 'info' => MEL_K_C_BAR_INFO, 'scroll' => '', 'ring' => '');
        if (!isset($roles[$role]))
            self::miss('bar', $role);
        if ($w <= 0 || $h <= 0 || !isset($roles[$role]))
            return self::box(array(), $x, $y, 0, 0);
        if ($role === 'ring')
        {
            $out = self::plate('ring', $x - MEL_K_RING_W, $y - MEL_K_RING_W, $w + 2 * MEL_K_RING_W, $h + 2 * MEL_K_RING_W);
            $out['kind'] = 'bar';
            return $out;
        }
        $id = isset($o['id']) ? (string) $o['id'] : null;
        $s = max(0, min(1, (float) $share));
        $d = array();
        $top = $y;
        if ($role === 'scroll')
        {
            $part = isset($o['part']) ? max(0, min(1, (float) $o['part'])) : 1;
            $th = min($h, max(MEL_K_THUMB_MIN, (int) round($h * $part)));
            $d[] = mel_gc_rect(mel_gc_at($w, $h, $x, $y), MEL_K_C_SCROLL_TRACK);
            $d[] = mel_gc_rect(mel_gc_at($w, $th, $x, $y + (int) round(($h - $th) * $s)), MEL_K_C_SCROLL_THUMB, $id);
        }
        else
        {
            $fw = $s > 0 ? min($w, max(MEL_K_BAR_MIN, (int) round($w * $s))) : 0;
            if ($fw < $w)
                $d[] = mel_gc_rect(mel_gc_at($w - $fw, $h, $x + $fw, $y), MEL_K_C_BAR_REST);
            if ($fw > 0)
                $d[] = mel_gc_rect(mel_gc_at($fw, $h, $x, $y), $roles[$role], $id);
            if (!empty($o['edge']))
            {
                $d[] = mel_gc_rect(mel_gc_at($w, 1, $x, $y - 1), MEL_K_C_BAR_EDGE);
                $top--;
            }
        }
        $out = self::box($d, $x, $top, $w, $y + $h - $top);
        $out['kind'] = 'bar';
        return $out;
    }
}
