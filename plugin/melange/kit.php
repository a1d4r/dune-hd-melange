<?php
// The core of the UI kit: text, icon, row, stack over the primitives of
// gcomps.php, by the tokens of kit_tokens.php. A component takes its font size
// once: one call measures and draws, by the width model of the shell
// (kit_font.php: the sum of floor(advance * size / upem), no kerning). Every
// builder returns an item, its components and its visible edges; row and
// stack lay items out by those edges (the top of the capitals, the baseline,
// the strokes of an icon, the edge of a box), not by their boxes.
// Required by main.php after gcomps.php; calls mel_log (main.php), mel_clean
// and mel_utf8 (parse.php), mel_gc_word_end (gcomps.php, bytes only: the
// width is the kit's own, never mel_gc_w and its kin). PHP 5.3.6, firmware
// API only.
// Types in the PHPDoc (MelKitItem): phpstan-types.neon of the repository.
//
// An item (MelKitItem), coordinates of the parent:
//   kind      text, icon, box, row, stack, group, or the component (plate,
//             button, chip, badges, badge_text, key, tab, tabs, bar, rating,
//             facts, sources);
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
//   ids       the ids of its components: those given as $o['id'] (at work
//             too); in a test also the mk<n> the others get for the trace.
// A text also has w, px, face, lines, base (of its first line), step.

require_once dirname(__FILE__) . '/gcomps.php';

class MelKit
{
    // The trace of a test: null at work. A test sets array() (reset): the
    // builders give their components without an id one (mk<n>) and row and
    // stack add array('kind', 'name', 'token', 'els'): token, the gap as
    // given (a name of the rhythm xs, s, m, l or px; or a list of one per
    // pair); els, of each element its ids and its edges after the layout
    // (x0, x1, top, bottom, mid), what check_align holds to the token.
    /** @var list<array{kind: string, name: string, token: int|string|list<int|string>, els: list<array{ids: list<string>, x0: int, x1: int, top: int, bottom: int, mid: int|float}>}>|null */
    public static $trace = null;
    /** @var array{wide: string, faces: array<string, array{upem: int, avg: int, w: array<string, int>}>}|null */
    private static $font = null;
    /** @var int */
    private static $n = 0;
    /** @var array<string, bool> */
    private static $missed = array();

    // A test trace afresh (a test, a sheet of the showcase): $trace array(),
    // the ids from mk1 again, whatever ran before.
    /**
     * @return void
     */
    public static function reset()
    {
        self::$n = 0;
        self::$trace = array();
    }

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
     * @return array{r: int, ring: int, color: string, tl: string, tr: string, bl: string, br: string, o: string}
     */
    public static function corner($key)
    {
        $t = mel_k_corner();
        if (isset($t[$key]))
            return $t[$key];
        self::miss('corner', $key);
        return array('r' => 0, 'ring' => 0, 'color' => MEL_K_C_NONE, 'tl' => '', 'tr' => '', 'bl' => '', 'br' => '',
            'o' => '');
    }

    /**
     * @param string $what
     * @param string $name
     * @return void
     */
    private static function miss($what, $name)
    {
        self::once("$what $name", "kit: no $what '$name'");
    }

    // $msg in the log, once for $key.
    /**
     * @param string $key
     * @param string $msg
     * @return void
     */
    private static function once($key, $msg)
    {
        if (isset(self::$missed[$key]))
            return;
        self::$missed[$key] = true;
        mel_log($msg);
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

    // $s as valid UTF-8: invalid bytes dropped (mel_utf8). PHP 5.3.6 splits
    // an invalid string by //u into one piece, the whole of it.
    /**
     * @param string $s
     * @return string
     */
    private static function utf8($s)
    {
        return preg_match('//u', $s) ? $s : mel_utf8($s);
    }

    // The characters of $s, valid UTF-8 (see utf8).
    /**
     * @param string $s
     * @return list<string>
     */
    private static function chars($s)
    {
        $c = preg_split('//u', self::utf8($s), -1, PREG_SPLIT_NO_EMPTY);
        return $c === false ? array() : $c;
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

    // $s (valid UTF-8, see utf8) if it fits $w, else its longest start that
    // fits with "…" (spaces and , . ; : · before it dropped).
    /**
     * @param string $s
     * @param int $px
     * @param string $face
     * @param int $w
     * @return string
     */
    public static function cut($s, $px, $face, $w)
    {
        $s = self::utf8($s);
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

    // $s (valid UTF-8, its runs of spaces one) in lines of $w at most, broken
    // after spaces and after the separators of mel_gc_word_end (_ - / , ) ] .,
    // a dot not before a digit: "5.1" stays whole), so the name of a torrent
    // "Show.S01.2160p.WEB-DL" wraps at its dots and dashes; a word wider than
    // a line by characters. A line has no spaces at its ends. More than $n
    // lines: the last ends with "…".
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
        // Lines and what joins each to the next: ' ' after a space, '' after
        // a separator or inside a word.
        $lines = array();
        $join = array();
        if ($n < 1)
            return $lines;
        $s = trim(preg_replace('/\s+/u', ' ', self::utf8($s)));
        $len = strlen($s);
        // The line so far as is; cw its width without its trailing spaces, ct
        // the width of them.
        $cur = '';
        $cw = 0;
        $ct = 0;
        for ($p = 0, $e = 0; $p < $len; $p = $e)
        {
            // Past $n + 1 lines the rest is not read: the last is cut anyway.
            if (count($lines) > $n)
                break;
            $e = mel_gc_word_end($s, $p, ' _-/,)].', $len);
            $word = (string) substr($s, $p, $e - $p);
            if ($cur === '')
                $word = ltrim($word, ' ');
            $vis = rtrim($word, ' ');
            $vw = self::w($vis, $px, $face);
            $tw = self::w((string) substr($word, strlen($vis)), $px, $face);
            if ($cur !== '' && ($vis === '' || $cw + $ct + $vw <= $w))
            {
                $cur .= $word;
                if ($vis !== '')
                {
                    $cw += $ct + $vw;
                    $ct = 0;
                }
                $ct += $tw;
                continue;
            }
            if ($cur !== '')
            {
                $lines[] = rtrim($cur, ' ');
                $join[] = $ct > 0 ? ' ' : '';
                $cur = '';
            }
            while ($vw > $w && count($lines) <= $n)
            {
                $c = self::chars($vis);
                $a = self::advances($c, $px, $face);
                for ($k = 0, $sum = 0; $k < count($c) && $sum + $a[$k] <= $w; $k++)
                    $sum += $a[$k];
                $k = max(1, $k);
                $lines[] = implode('', array_slice($c, 0, $k));
                $join[] = '';
                $vis = implode('', array_slice($c, $k));
                $vw = self::w($vis, $px, $face);
            }
            $cur = $vis !== '' ? $vis . substr($word, strlen(rtrim($word, ' '))) : '';
            $cw = $vw;
            $ct = $vis !== '' ? $tw : 0;
        }
        if ($cur !== '')
        {
            $lines[] = rtrim($cur, ' ');
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

    // A text (valid UTF-8, see utf8) with the centre of its capitals at $y,
    // starting at $x. $o: face (MEL_K_FACE_*, default regular); w, the width
    // it may take (cut with "…"; with lines, wrapped); lines, the most lines
    // (default 1: a label each, the step $px + ls; without w logged, one
    // line); ls, line_spacing (default 0); id (of a line: id_<n> when
    // lines > 1).
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
        $s = mel_clean(self::utf8($s));
        if (isset($o['lines']) && !isset($o['w']))
            mel_log("kit: text '" . mb_substr($s, 0, 40, 'UTF-8') . "': lines without w, one line");
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
    // $o: id; box, its left and right those of its box, not of its strokes
    // (a button: the gap and the pad from the box of the icon, as in r5).
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
        $box = !empty($o['box']);
        return array('kind' => 'icon', 'defs' => array($img),
            'x0' => $box ? $ix : $ix + (int) round($ink[0] * $k),
            'x1' => $box ? $ix + $size : $ix + (int) round($ink[2] * $k),
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
            // At (0, 0) no align_def at all, as mel_gc_geom.
            if ($a)
                $g[GCompGeometryDef::align_def] = $a;
            else
                unset($g[GCompGeometryDef::align_def]);
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

    // The gap after element $i of the $kind $name, px: $gap, or its $i-th; a
    // name of the rhythm (xs, s, m, l) its MEL_K_GAP_*. No $i-th or an
    // unknown name: 0, logged.
    /**
     * @param int|string|list<int|string> $gap
     * @param int $i
     * @param string $kind
     * @param string $name
     * @return int
     */
    private static function gap($gap, $i, $kind, $name)
    {
        if (is_array($gap))
        {
            if (!isset($gap[$i]))
            {
                mel_log("kit: $kind '$name': no gap after element $i, " . count($gap) . ' given; 0');
                return 0;
            }
            $gap = $gap[$i];
        }
        if (!is_string($gap))
            return (int) $gap;
        $t = array('xs' => MEL_K_GAP_XS, 's' => MEL_K_GAP_S, 'm' => MEL_K_GAP_M, 'l' => MEL_K_GAP_L);
        if (isset($t[$gap]))
            return $t[$gap];
        self::miss('gap', $gap);
        return 0;
    }

    /**
     * @param string $kind
     * @param list<MelKitItem> $items
     * @param int|string|list<int|string> $gap
     * @param string $name
     * @return void
     */
    private static function trace($kind, $items, $gap, $name)
    {
        $els = array();
        foreach ($items as $it)
            $els[] = array('ids' => $it['ids'], 'x0' => $it['x0'], 'x1' => $it['x1'], 'top' => $it['top'],
                'bottom' => $it['bottom'], 'mid' => $it['mid']);
        self::$trace[] = array('kind' => $kind, 'name' => $name, 'token' => $gap, 'els' => $els);
    }

    // The items one after another, $gap (a token: px or a name of the rhythm
    // xs, s, m, l; or a list of one per pair)
    // between the visible right of one and the left of the next, their mid on
    // the centre of the capitals of the first text (else of the first item),
    // to a pixel. $x: the left of the first (default: where it is); $y: the
    // centre the first text is set at (default: where it is).
    /**
     * @param list<MelKitItem> $items
     * @param int|string|list<int|string> $gap
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
            $at = $items[$i]['x1'] + ($i + 1 < count($items) ? self::gap($gap, $i, 'row', $name) : 0);
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

    // The blocks from the top down, $gap (a token, by its name of the rhythm
    // xs, s, m, l for check_align, or px; or a list of one per pair) between
    // the visible bottom of one and the top of the next (a baseline to the
    // top of the capitals, the strokes of an icon, the edge of a box).
    // $y: the top of the first (default: where it is). Their x stay.
    /**
     * @param list<MelKitItem> $blocks
     * @param int|string|list<int|string> $gap
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
            $at = $blocks[$i]['bottom'] + ($i + 1 < count($blocks) ? self::gap($gap, $i, 'stack', $name) : 0);
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

    // $it as one panel $id (no clip) at its edges, its components the
    // children in the coordinates of the panel: a part of a screen whose
    // children change_gcomps replaces under the same id (the focus of a line,
    // of a button). Its edges and its line those of $it; ids: $id and those
    // of its components (for the trace).
    /**
     * @param string $id
     * @param MelKitItem $it
     * @return MelKitItem
     */
    public static function group($id, $it)
    {
        $in = self::move($it, -$it['x0'], -$it['top']);
        $it['defs'] = array(mel_gc_panel($id, mel_gc_at($it['x1'] - $it['x0'], $it['bottom'] - $it['top'], $it['x0'],
            $it['top']), $in['defs']));
        $it['ids'] = array_merge(array($id), $it['ids']);
        $it['kind'] = 'group';
        return $it;
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
    // to butt (a ring: only its band). Smaller than 2r: 2r. A circle (o of
    // the table): its one picture, always 2r x 2r (larger: logged once). Its
    // edges: the box.
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
        if ($c['o'] !== '')
        {
            $d = 2 * $r;
            if ($w > $d || $h > $d)
                self::once("circle $key", "kit: plate '$key' is a circle $d x $d, not {$w} x $h");
            $p = self::pic($c['o']);
            $out = self::box($p['url'] !== '' ? array(mel_gc_image(mel_gc_at($d, $d, $x, $y), $p['url'])) : array(),
                $x, $y, $d, $d);
            $out['kind'] = 'plate';
            return $out;
        }
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

    // $state of a component with states ('' and those of $known): as is, else
    // logged once and ''.
    /**
     * @param string $kind
     * @param string $state
     * @param list<string> $known
     * @return string
     */
    private static function state($kind, $state, $known)
    {
        if ($state === '' || in_array($state, $known, true))
            return $state;
        self::once("$kind state $state", "kit: $kind: no state '$state', none");
        return '';
    }

    // A pill button MEL_K_BTN_H high: the icon $icon (k_icon_*,
    // MEL_K_ICON_BUTTON; '' none) -MEL_K_ICON_GAP_BUTTON- the label
    // (MEL_K_PX_ROW) between the pads MEL_K_BTN_PAD, the pad and the gap
    // from the box of the icon, not its strokes (r5); the left of the plate at
    // $x, its centre and the capitals at $y. $state: '' on the surface (btn),
    // focus white on the accent (btn_on), off (disabled) text 3 on the
    // surface; one geometry for all three, so a screen changes the state by
    // the children of a panel of the same id (group). $o['w']: its least
    // width, the content centred in it (a button whose label changes keeps
    // its width). The label is never cut: wider than $o['w'], logged and set
    // whole.
    /**
     * @param string $label
     * @param string $icon
     * @param string $state
     * @param int $x
     * @param int|float $y
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function button($label, $icon, $state = '', $x = 0, $y = 0, $o = array())
    {
        // Rounded once here: text and row would each round a half pixel again.
        $y = (int) round($y);
        $state = self::state('button', $state, array('focus', 'off'));
        $fg = $state === 'off' ? MEL_K_C_TEXT3 : ($state === 'focus' ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT1);
        $c = self::text($label, MEL_K_PX_ROW, $fg, 0, $y);
        if ($icon !== '')
            $c = self::row(array(self::icon($icon, MEL_K_ICON_BUTTON, $fg, 0, 0, array('box' => true)), $c),
                MEL_K_ICON_GAP_BUTTON, 0, $y, 'button');
        $cw = $c['x1'] - $c['x0'];
        $w = $cw + 2 * MEL_K_BTN_PAD;
        $min = isset($o['w']) ? (int) $o['w'] : 0;
        if ($min && $w > $min)
            mel_log("kit: button '$label' is $w px wide, more than $min");
        $w = max($w, $min);
        $c = self::move($c, $x + intval(($w - $cw) / 2) - $c['x0'], 0);
        $p = self::plate($state === 'focus' ? 'btn_on' : 'btn', $x, $y - intval(MEL_K_BTN_H / 2), $w, MEL_K_BTN_H);
        return self::on('button', $p, $c);
    }

    // A chip MEL_K_CHIP_H high: $text (MEL_K_PX_CAPTION) in the colour of
    // $kind, ok («main») or warn («needs a key»), on its tint (chip_ok,
    // chip_warn), pads MEL_K_CHIP_PAD; the left at $x, the plate and the
    // capitals centred at $y. Another $kind: the text in text 2 with no plate
    // (its edges kept), logged once.
    /**
     * @param string $text
     * @param string $kind
     * @param int $x
     * @param int|float $y
     * @return MelKitItem
     */
    public static function chip($text, $kind, $x = 0, $y = 0)
    {
        $y = (int) round($y);
        $known = $kind === 'ok' || $kind === 'warn';
        if (!$known)
            self::once("chip $kind", "kit: chip: no kind '$kind', the text without a plate");
        $fg = $kind === 'warn' ? MEL_K_C_WARN : ($kind === 'ok' ? MEL_K_C_OK : MEL_K_C_TEXT2);
        $t = self::text($text, MEL_K_PX_CAPTION, $fg, $x + MEL_K_CHIP_PAD, $y);
        $top = $y - intval(MEL_K_CHIP_H / 2);
        $w = $t['x1'] - $t['x0'] + 2 * MEL_K_CHIP_PAD;
        $p = $known ? self::plate("chip_$kind", $x, $top, $w, MEL_K_CHIP_H) : self::box(array(), $x, $top, $w, MEL_K_CHIP_H);
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
        $y = (int) round($y);
        $items = array();
        foreach ($ids as $id)
        {
            $p = self::pic("k_badge_$id");
            if ($p['url'] !== '')
                $items[] = self::box(array(mel_gc_image(mel_gc_at($p['w'], $p['h'], 0, 0), $p['url'])), 0, 0, $p['w'], $p['h']);
        }
        $out = $items ? self::row($items, MEL_K_PLATE_GAP, $x, $y, $name) : self::box(array(), $x, $y, 0, 0);
        $out['kind'] = 'badges';
        return $out;
    }

    // A text plate b2 (IMAX, an edition: what no picture k_badge_* is) as
    // high as they, MEL_K_PLATE_H: $text (MEL_K_PX_BADGE, condensed, text 1)
    // between the pads MEL_K_PLATE_PAD on their plate (plate); the left at
    // $x, the plate and the capitals centred at $y. The height of capitals
    // of MEL_K_PX_BADGE is a guess (mel_k_cap()), not measured on the device.
    /**
     * @param string $text
     * @param int $x
     * @param int|float $y
     * @return MelKitItem
     */
    public static function badge_text($text, $x = 0, $y = 0)
    {
        $y = (int) round($y);
        $t = self::text($text, MEL_K_PX_BADGE, MEL_K_C_TEXT1, $x + MEL_K_PLATE_PAD, $y,
            array('face' => MEL_K_FACE_CONDENSED));
        $p = self::plate('plate', $x, $y - intval(MEL_K_PLATE_H / 2), $t['x1'] - $t['x0'] + 2 * MEL_K_PLATE_PAD,
            MEL_K_PLATE_H);
        return self::on('badge_text', $p, $t);
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
        $y = (int) round($y);
        $top = $y - intval(MEL_K_KEY_H / 2);
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
        if ($y !== null)
            $y = (int) round($y);
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

    // The sort tabs (the colour keys of the remote), MEL_K_TAB_GAP from plate
    // to plate; the left of the first plate at $x, the centre at $y. A tab of
    // $tabs: key, its colour key (red, green, yellow, blue; '' or another: no
    // dot, another logged), a dot MEL_K_KEY_DOT -MEL_K_KEY_DOT_GAP- label as
    // given (MEL_K_PX_ROW) -MEL_K_ICON_GAP_SORT- the arrow MEL_K_ICON_SORT
    // (down; up: up true) between the pads MEL_K_TAB_PAD; state: '' text 2,
    // no plate, no arrow; on (the sort of the list) text 1 on the plate sort;
    // focus white on the accent (tab_on). As r5 sort_bar: only the selected
    // has the arrow and its room, a tab without it ends MEL_K_ICON_GAP_SORT +
    // MEL_K_TAB_PAD after the label, so the tabs after the selected move by
    // MEL_K_ICON_SORT when the selection moves.
    /**
     * @param list<array{key: string, label: string, state?: string, up?: bool}> $tabs
     * @param int|null $x
     * @param int|float|null $y
     * @param string $name
     * @return MelKitItem
     */
    public static function tabs($tabs, $x = null, $y = null, $name = 'tabs')
    {
        if ($y !== null)
            $y = (int) round($y);
        $items = array();
        foreach ($tabs as $t)
            $items[] = self::tab($t);
        $out = $items ? self::row($items, MEL_K_TAB_GAP, $x, $y, $name)
            : self::box(array(), $x !== null ? $x : 0, $y !== null ? $y : 0, 0, 0);
        $out['kind'] = 'tabs';
        return $out;
    }

    // A tab of tabs at x 0, its plate centred at 0.
    /**
     * @param array{key: string, label: string, state?: string, up?: bool} $t
     * @return MelKitItem
     */
    private static function tab($t)
    {
        $state = self::state('tab', isset($t['state']) ? $t['state'] : '', array('on', 'focus'));
        $fg = $state === 'focus' ? MEL_K_C_ON_ACCENT : ($state === 'on' ? MEL_K_C_TEXT1 : MEL_K_C_TEXT2);
        $parts = array();
        $gaps = array();
        if ($t['key'] !== '' && in_array($t['key'], array('red', 'green', 'yellow', 'blue'), true))
        {
            $parts[] = self::plate("key_{$t['key']}", 0, -intval(MEL_K_KEY_DOT / 2), MEL_K_KEY_DOT, MEL_K_KEY_DOT);
            $gaps[] = MEL_K_KEY_DOT_GAP;
        }
        elseif ($t['key'] !== '')
            self::once("tab key {$t['key']}", "kit: tab: no colour key '{$t['key']}', no dot");
        $parts[] = self::text($t['label'], MEL_K_PX_ROW, $fg);
        $gaps[] = MEL_K_ICON_GAP_SORT;
        if ($state !== '')
            $parts[] = self::icon(empty($t['up']) ? 'k_icon_arrow-down' : 'k_icon_arrow-up', MEL_K_ICON_SORT, $fg, 0, 0,
                array('box' => true));
        $c = self::row($parts, $gaps, MEL_K_TAB_PAD, 0, 'tab');
        $top = -intval(MEL_K_TAB_H / 2);
        // No arrow: the gap before it stays (r5: label -56- the next dot).
        $w = $c['x1'] + ($state !== '' ? 0 : MEL_K_ICON_GAP_SORT) + MEL_K_TAB_PAD;
        $p = $state === '' ? self::box(array(), 0, $top, $w, MEL_K_TAB_H)
            : self::plate($state === 'focus' ? 'tab_on' : 'sort', 0, $top, $w, MEL_K_TAB_H);
        return self::on('tab', $p, $c);
    }

    // A bar of $role, $w x $h at ($x, $y); its edges the box:
    //   progress, info: done $share of the width (at least MEL_K_BAR_MIN when
    //     $share > 0) in MEL_K_C_BAR_DONE (info: MEL_K_C_BAR_INFO), the rest
    //     MEL_K_C_BAR_REST; $o['edge']: a line MEL_K_C_BAR_EDGE 1 px over it;
    //   scroll: the track MEL_K_C_SCROLL_TRACK and the thumb
    //     MEL_K_C_SCROLL_THUMB, $o['part'] of the height (default all, at
    //     least MEL_K_THUMB_MIN), at $share of the way down;
    //   ring: no bar but the outline of the focus (the plate ring) round the
    //     box, MEL_K_RING_W outside it; its edges the outline.
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

    // --- Blocks of a screen: the data ready (the screen takes them from the tile, the card, the settings), the
    // block lays them out by the rhythm.

    // The block of facts of the info panel and the own card (r5 facts), from the top down by stack: the plates of
    // the ratings (MEL_K_RATING_H) -s- the line of facts (MEL_K_PX_SECONDARY, text 2) -s- the line of the people
    // (its label text 3 -MEL_K_CAST_GAP- the names text 2) -m- the description (MEL_K_PX_DESCRIPTION, text 1).
    // No ratings and no age: no line of plates; no line of facts, of the people or no description: no such block,
    // the gaps of the others stay. The strings of the lists are taken after mel_clean, the empty ones dropped. $d:
    //   ratings   list of array(kind, name, value), the rates of mel_catv_fact_parts (not the output of
    //             mel_catv_facts: its entries text and age are skipped here): kind kp (MEL_K_RATING_KP_W wide), imdb
    //             (MEL_K_RATING_IMDB_W), another (by its text); the name text 2 MEL_K_RATING_PAD from the left,
    //             the value text 1 as far from the right, a plate wider when they do not fit so;
    //   age       the age mark ('' none), centred on a plate MEL_K_RATING_AGE_W, MEL_K_RATING_AGE_GAP after
    //             the ratings (MEL_K_RATING_GAP between them);
    //   facts     the parts of the line (year, length, …: list of strings), " · " between them, then the
    //   genres, countries  (lists, ", " between). Wider than $w: the countries but the first go, then all of
    //             them, then the genres but the first (mel_catv_genre_line), then the end is cut with "…";
    //   people_label, people  the label (the directors of a film «Режиссёр», the cast of a series «В ролях»; ''
    //             or none: the names alone, from the left) and the names: those after the first that do not fit
    //             $w go whole, the first is cut; no names: no line;
    //   desc      the description.
    // $w: the width of the lines of facts and people. $o: x, the left (default 0); y, the top of the block (default
    // 0); desc_w, the width of the description (default $w); desc_lines, its most lines (default 3; the info
    // panel passes 4 without people: owner), the last cut with "…"; desc_step, its step (default
    // MEL_K_STEP_INFO_DESC, the card MEL_K_STEP_CARD_DESC). The edges: the top of the plates (else of the
    // capitals of the first line), the baseline of the last line.
    /**
     * @param array{ratings?: list<array{string, string, string}>, age?: string, facts?: list<string>, genres?: list<string>, countries?: list<string>, people_label?: string, people?: list<string>, desc?: string} $d
     * @param int $w
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function facts($d, $w, $o = array())
    {
        $x = isset($o['x']) ? (int) $o['x'] : 0;
        $px = MEL_K_PX_SECONDARY;
        $blocks = array();
        $gaps = array();
        $plates = self::ratings(isset($d['ratings']) ? $d['ratings'] : array(), isset($d['age']) ? (string) $d['age'] : '',
            $x);
        if ($plates !== null)
            $blocks[] = $plates;
        $line = self::facts_line(self::strings($d, 'facts'), self::strings($d, 'genres'), self::strings($d, 'countries'),
            $w);
        if ($line !== '')
            $blocks[] = self::text($line, $px, MEL_K_C_TEXT2, $x, 0, array('w' => $w));
        $names = self::strings($d, 'people');
        if ($names)
        {
            $parts = array();
            $room = $w;
            $label = isset($d['people_label']) ? mel_clean(self::utf8((string) $d['people_label'])) : '';
            if ($label !== '')
            {
                $parts[] = self::text($label, $px, MEL_K_C_TEXT3, $x);
                $room -= $parts[0]['x1'] - $parts[0]['x0'] + MEL_K_CAST_GAP;
            }
            // The names that fit whole, one at least (the widths add up: no kerning).
            $sep = self::w(', ', $px);
            $sum = -$sep;
            $keep = 0;
            foreach ($names as $k => $nm)
            {
                $sum += $sep + self::w($nm, $px);
                if ($k && $sum > $room)
                    break;
                $keep = $k + 1;
            }
            $parts[] = self::text(implode(', ', array_slice($names, 0, $keep)), $px, MEL_K_C_TEXT2, 0, 0,
                array('w' => max(0, $room)));
            $blocks[] = self::row($parts, MEL_K_CAST_GAP, $x, null, 'people');
        }
        foreach ($blocks as $i => $b)
        {
            if ($i)
                $gaps[] = 's';
        }
        $n = isset($o['desc_lines']) ? (int) $o['desc_lines'] : 3;
        $desc = isset($d['desc']) ? trim((string) $d['desc']) : '';
        if ($desc !== '' && $n > 0)
        {
            $step = isset($o['desc_step']) ? (int) $o['desc_step'] : MEL_K_STEP_INFO_DESC;
            $blocks[] = self::text($desc, MEL_K_PX_DESCRIPTION, MEL_K_C_TEXT1, $x, 0, array('lines' => $n,
                'w' => isset($o['desc_w']) ? (int) $o['desc_w'] : $w, 'ls' => $step - MEL_K_PX_DESCRIPTION));
            if (count($blocks) > 1)
                $gaps[] = 'm';
        }
        $y = isset($o['y']) ? (int) $o['y'] : 0;
        $out = $blocks ? self::stack($blocks, $gaps, $y, 'facts') : self::box(array(), $x, $y, 0, 0);
        $out['kind'] = 'facts';
        return $out;
    }

    // The strings of the list $d[$key] after mel_clean, the empty ones dropped (see facts).
    /**
     * @param array<string, mixed> $d
     * @param string $key
     * @return list<string>
     */
    private static function strings($d, $key)
    {
        $out = array();
        foreach (isset($d[$key]) ? (array) $d[$key] : array() as $v)
        {
            $v = mel_clean(self::utf8((string) $v));
            if ($v !== '')
                $out[] = $v;
        }
        return $out;
    }

    // The line of plates of facts at $x (see facts); null without ratings and age.
    /**
     * @param list<array{string, string, string}> $ratings
     * @param string $age
     * @param int $x
     * @return MelKitItem|null
     */
    private static function ratings($ratings, $age, $x)
    {
        $min = array('kp' => MEL_K_RATING_KP_W, 'imdb' => MEL_K_RATING_IMDB_W);
        $items = array();
        $gaps = array();
        foreach ($ratings as $r)
        {
            // The entries text and age of mel_catv_facts are not ratings: «Сериал · 2008» is no plate.
            if ($r[0] === 'text' || $r[0] === 'age')
                continue;
            $items[] = self::rating($r[1], $r[2], isset($min[$r[0]]) ? $min[$r[0]] : 0);
            $gaps[] = MEL_K_RATING_GAP;
        }
        if ($age !== '')
        {
            if ($gaps)
                $gaps[count($gaps) - 1] = MEL_K_RATING_AGE_GAP;
            $items[] = self::rating($age, '', MEL_K_RATING_AGE_W);
        }
        if (!$items)
            return null;
        return self::row($items, $gaps, $x, null, 'ratings');
    }

    // A plate of a rating MEL_K_RATING_H high (the ring rating), at x 0 centred at 0, at least $min wide: $name
    // text 2 MEL_K_RATING_PAD from the left and $value text 1 as far from the right (MEL_K_PX_RATING); no
    // $value: $name centred (the age), MEL_K_RATING_AGE_PAD a side at least.
    /**
     * @param string $name
     * @param string $value
     * @param int $min
     * @return MelKitItem
     */
    private static function rating($name, $value, $min)
    {
        $px = MEL_K_PX_RATING;
        $n = self::text($name, $px, MEL_K_C_TEXT2);
        $c = array($n);
        if ($value !== '')
        {
            $v = self::text($value, $px, MEL_K_C_TEXT1);
            $w = max($min, self::w("$name $value", $px) + 2 * MEL_K_RATING_PAD);
            $c[] = self::move($v, $w - MEL_K_RATING_PAD - $v['x1'], 0);
            $c[0] = self::move($n, MEL_K_RATING_PAD, 0);
        }
        else
        {
            $w = max($min, $n['x1'] + 2 * MEL_K_RATING_AGE_PAD);
            $c[0] = self::move($n, intval(($w - $n['x1']) / 2), 0);
        }
        $t = self::join('rating', $c);
        $t['mid'] = $n['mid'];
        $t['caps'] = true;
        return self::on('rating', self::plate('rating', 0, -intval(MEL_K_RATING_H / 2), $w, MEL_K_RATING_H), $t);
    }

    // The line of facts of facts: $parts, the genres and the countries, " · " between, fitted to $w by what goes
    // (see facts); the last try as is (text cuts it).
    /**
     * @param list<string> $parts
     * @param list<string> $genres
     * @param list<string> $countries
     * @param int $w
     * @return string
     */
    private static function facts_line($parts, $genres, $countries, $w)
    {
        $line = '';
        foreach (array(array($genres, $countries), array($genres, array_slice($countries, 0, 1)), array($genres, array()),
            array(array_slice($genres, 0, 1), array())) as $v)
        {
            $line = implode(' · ', array_filter(array_merge($parts, array(implode(', ', $v[0]), implode(', ', $v[1]))),
                'strlen'));
            if (self::w($line, MEL_K_PX_SECONDARY) <= $w)
                break;
        }
        return $line;
    }

    // The list of sources of the settings on the TV (r5 sources_list), by stack: the caption $o['title']
    // (MEL_K_PX_CAPTION, text 3; '' or none: no caption) -s- the lines of $items one under another, MEL_K_STEP_SETUP
    // apart (a chip MEL_K_CHIP_H and the gap s), the last one $o['more'] («и ещё 2»; '' or none: none) with the
    // icon k_icon_ellipsis. Which sources, in which order (the main one first, up to three) and the words: the
    // screen's; the list draws them. A line of $items (its keys do not matter, the lines go in its order): icon,
    // the kind (k_icon_*, MEL_K_ICON_SETUP_SOURCE, text 2, its box) -MEL_K_ICON_GAP_SETUP_SOURCE- name
    // (MEL_K_PX_SECONDARY, text 1) and detail (« · » before it, text 2; optional: «Debrid · Real-Debrid», as the
    // lines of the page) -MEL_K_CHIP_BEFORE- the chips, array(text, kind) of chip, MEL_K_CHIP_GAP apart
    // (optional); all on the centre of the capitals of the name. $w: the width of a line (0: not cut), the detail
    // cut with "…" first, then the name with no detail. $o: x, the left of the caption and of the icons (default
    // 0); y, the top of the block (default 0); title, more. The edges: the top of the capitals of the caption
    // (else the visible top of the first line: of the main one its chip, as r5), the visible bottom of the last
    // line.
    /**
     * @param array<int, array{icon: string, name: string, detail?: string, chips?: list<array{string, string}>}> $items
     * @param int $w
     * @param array<string, mixed> $o
     * @return MelKitItem
     */
    public static function sources($items, $w, $o = array())
    {
        $x = isset($o['x']) ? (int) $o['x'] : 0;
        $w = (int) $w;
        $more = isset($o['more']) ? (string) $o['more'] : '';
        $px = MEL_K_PX_SECONDARY;
        $ic = MEL_K_ICON_SETUP_SOURCE + MEL_K_ICON_GAP_SETUP_SOURCE;
        $blocks = array();
        if (isset($o['title']) && $o['title'] !== '')
            $blocks[] = self::text((string) $o['title'], MEL_K_PX_CAPTION, MEL_K_C_TEXT3, $x);
        $lines = array();
        // The step goes by the place in the list, not by the key (holes after array_filter).
        foreach (array_values($items) as $i => $s)
        {
            $chips = array();
            $cw = 0;
            foreach (isset($s['chips']) ? $s['chips'] : array() as $c)
            {
                $chip = self::chip($c[0], $c[1]);
                $cw += ($chips ? MEL_K_CHIP_GAP : MEL_K_CHIP_BEFORE) + $chip['x1'] - $chip['x0'];
                $chips[] = $chip;
            }
            // The room of the name and the detail: the line but the icon and the chips.
            $room = $w - $ic - $cw;
            $name = self::text($s['name'], $px, MEL_K_C_TEXT1, 0, 0, $w > 0 ? array('w' => max(0, $room)) : array());
            $parts = array(self::icon($s['icon'], MEL_K_ICON_SETUP_SOURCE, MEL_K_C_TEXT2, 0, 0, array('box' => true)), $name);
            $gaps = array(MEL_K_ICON_GAP_SETUP_SOURCE);
            // « · » before the detail: a space as the gap (a label loses its spaces at the ends, mel_clean).
            $sp = self::w(' ', $px);
            $det = isset($s['detail']) && $s['detail'] !== '' ? "\xC2\xB7 " . $s['detail'] : '';
            if ($det !== '' && $w > 0)
                $det = $room > $name['x1'] + $sp ? self::cut($det, $px, MEL_K_FACE_REGULAR, $room - $name['x1'] - $sp) : '';
            // Nothing of the detail but "…": no detail.
            if ($det !== '' && $det !== "\xE2\x80\xA6")
            {
                $parts[] = self::text($det, $px, MEL_K_C_TEXT2);
                $gaps[] = $sp;
            }
            foreach ($chips as $k => $c)
            {
                $parts[] = $c;
                $gaps[] = $k ? MEL_K_CHIP_GAP : MEL_K_CHIP_BEFORE;
            }
            $lines[] = self::row($parts, $gaps, $x, $i * MEL_K_STEP_SETUP, "source $i");
        }
        if ($more !== '')
            $lines[] = self::row(array(self::icon('k_icon_ellipsis', MEL_K_ICON_SETUP_SOURCE, MEL_K_C_TEXT3, 0, 0,
                array('box' => true)), self::text($more, $px, MEL_K_C_TEXT2)), MEL_K_ICON_GAP_SETUP_SOURCE, $x,
                count($lines) * MEL_K_STEP_SETUP, 'more');
        if ($lines)
            $blocks[] = self::join('lines', $lines);
        $out = $blocks ? self::stack($blocks, 's', isset($o['y']) ? (int) $o['y'] : 0, 'sources')
            : self::box(array(), $x, isset($o['y']) ? (int) $o['y'] : 0, 0, 0);
        $out['kind'] = 'sources';
        return $out;
    }
    // --- The side panel on the right (stage 6a): the builders. The keys, the state of an open panel and its changes:
    // kit_panel.php. Coordinates of the panel (its left 0, the top of the screen 0).

    // A line of the panel MEL_K_STEP_PANEL high and MEL_K_PANEL_W wide as the group $id (a panel whose id goes by the
    // key of its content, not its place: «Переместить» moves it by its geometry), its top at $y. One geometry in every
    // state, so a screen changes the state by its children. $r:
    //   state     '' text 1; focus white on the plate panel_row_on (the whole line high, as r5; MEL_K_PANEL_INSET
    //             from both sides); move, a line taken by «Переместить»: focus and ⇅ on the right (k_icon_arrow-up-down
    //             MEL_K_ICON_PANEL_MOVE);
    //   label     MEL_K_PX_ROW, MEL_K_PANEL_INSET + MEL_K_PANEL_INNER from the left, after the icon or the square:
    //   icon      k_icon_* ('' or none: no icon), or check (true or false: k_icon_square-check, k_icon_square; an
    //             unchecked square text 3 off focus), MEL_K_ICON_PANEL by its box -MEL_K_ICON_GAP_PANEL- the label;
    //   detail    after the label « · detail» in text 2 («Debrid · Real-Debrid»), cut first;
    // on the right, as far from the right edge, one of: value (text 2) -xs- the chevron › MEL_K_ICON_PANEL_CHEVRON
    // when chevron (a submenu); count (MEL_K_PX_CAPTION, text 3: «6» balancers of a voice); picked, the chosen one
    // (k_icon_check MEL_K_ICON_PANEL), before it -s- the direction way (an icon, MEL_K_ICON_SORT). The label and its
    // detail are cut with "…" MEL_K_GAP_M before the right part. Edges: the box of the line.
    /**
     * @param string $id
     * @param MelKitPanelRow $r
     * @param int $y
     * @return MelKitItem
     */
    public static function panel_row($id, $r, $y = 0)
    {
        $state = self::state('panel row', isset($r['state']) ? (string) $r['state'] : '', array('focus', 'move'));
        $on = $state !== '';
        $fg = $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT1;
        $fg2 = $on ? MEL_K_C_ON_ACCENT2 : MEL_K_C_TEXT2;
        $fg3 = $on ? MEL_K_C_ON_ACCENT2 : MEL_K_C_TEXT3;
        $h = MEL_K_STEP_PANEL;
        $cy = $y + intval($h / 2);
        $cx0 = MEL_K_PANEL_INSET + MEL_K_PANEL_INNER;
        $cx1 = MEL_K_PANEL_W - $cx0;
        $parts = array(self::box(array(), 0, $y, MEL_K_PANEL_W, $h));
        if ($on)
            $parts[] = self::plate('panel_row_on', MEL_K_PANEL_INSET, $y, MEL_K_PANEL_W - 2 * MEL_K_PANEL_INSET, $h);
        // The right part, set from the right edge of the content.
        $right = array();
        $rgap = array();
        if ($state === 'move')
            $right[] = self::icon('k_icon_arrow-up-down', MEL_K_ICON_PANEL_MOVE, $fg2, 0, 0, array('box' => true));
        elseif (!empty($r['picked']))
        {
            if (isset($r['way']) && $r['way'] !== '')
            {
                $right[] = self::icon($r['way'], MEL_K_ICON_SORT, $fg2, 0, 0, array('box' => true));
                $rgap[] = MEL_K_GAP_S;
            }
            $right[] = self::icon('k_icon_check', MEL_K_ICON_PANEL, $fg, 0, 0, array('box' => true));
        }
        elseif (isset($r['count']) && $r['count'] !== '')
            $right[] = self::text($r['count'], MEL_K_PX_CAPTION, $fg3);
        else
        {
            if (isset($r['value']) && $r['value'] !== '')
            {
                $right[] = self::text($r['value'], MEL_K_PX_ROW, $fg2);
                $rgap[] = MEL_K_GAP_XS;
            }
            if (!empty($r['chevron']))
                $right[] = self::icon('k_icon_chevron-right', MEL_K_ICON_PANEL_CHEVRON, $fg2, 0, 0, array('box' => true));
            else
                $rgap = array();
        }
        $edge = $cx1;
        if ($right)
        {
            // Its width first: row sets it from its left, and the trace has it where it stands.
            $rw = array_sum($rgap);
            foreach ($right as $it)
                $rw += $it['x1'] - $it['x0'];
            $parts[] = self::row($right, $rgap, $cx1 - $rw, $cy, 'panel row right');
            $edge = $cx1 - $rw - MEL_K_GAP_M;
        }
        $left = array();
        if (isset($r['check']))
            $left[] = self::icon($r['check'] ? 'k_icon_square-check' : 'k_icon_square', MEL_K_ICON_PANEL,
                $r['check'] ? $fg : $fg3, 0, 0, array('box' => true));
        elseif (isset($r['icon']) && $r['icon'] !== '')
            $left[] = self::icon($r['icon'], MEL_K_ICON_PANEL, $fg, 0, 0, array('box' => true));
        $gaps = $left ? array(MEL_K_ICON_GAP_PANEL) : array();
        $lx = $cx0 + ($left ? MEL_K_ICON_PANEL + MEL_K_ICON_GAP_PANEL : 0);
        $room = max(0, $edge - $lx);
        $label = self::text($r['label'], MEL_K_PX_ROW, $fg, 0, 0, array('w' => $room));
        $left[] = $label;
        // « · detail» after the label: a space as the gap (a label loses its spaces at the ends, mel_clean).
        $sp = self::w(' ', MEL_K_PX_ROW);
        $rest = $room - ($label['x1'] - $label['x0']) - $sp;
        if (isset($r['detail']) && $r['detail'] !== '' && $rest > 0)
        {
            $det = self::cut("\xC2\xB7 " . $r['detail'], MEL_K_PX_ROW, MEL_K_FACE_REGULAR, $rest);
            if ($det !== "\xE2\x80\xA6")
            {
                $left[] = self::text($det, MEL_K_PX_ROW, $fg2);
                $gaps[] = $sp;
            }
        }
        $parts[] = self::row($left, $gaps, $cx0, $cy, 'panel row');
        $it = self::join('panel_row', $parts);
        $it['mid'] = $cy;
        $it['cy'] = $cy;
        return self::group($id, $it);
    }

    // The head of a panel: the title (MEL_K_PX_PANEL_TITLE, text 1) with the centre of its capitals at
    // MEL_K_PANEL_TITLE_Y + MEL_K_PANEL_TITLE_H / 2, from the left of the content, cut to its width; $back (a submenu
    // in the place of the panel): ‹ before it (k_icon_chevron-left MEL_K_ICON_PANEL_BACK, text 2), the content edge
    // at its strokes, -s- the title (r6 panel_title).
    /**
     * @param string $title
     * @param bool $back
     * @return MelKitItem
     */
    public static function panel_head($title, $back = false)
    {
        $cx0 = MEL_K_PANEL_INSET + MEL_K_PANEL_INNER;
        $w = MEL_K_PANEL_W - 2 * $cx0;
        $y = MEL_K_PANEL_TITLE_Y + MEL_K_PANEL_TITLE_H / 2;
        if (!$back)
            return self::text($title, MEL_K_PX_PANEL_TITLE, MEL_K_C_TEXT1, $cx0, $y, array('w' => $w));
        $b = self::icon('k_icon_chevron-left', MEL_K_ICON_PANEL_BACK, MEL_K_C_TEXT2);
        $t = self::text($title, MEL_K_PX_PANEL_TITLE, MEL_K_C_TEXT1, 0, 0,
            array('w' => max(0, $w - ($b['x1'] - $b['x0']) - MEL_K_GAP_S)));
        return self::row(array($b, $t), 's', $cx0, $y, 'panel head');
    }

    // The line «Готово» of a check list as the group $id: the button $label (k_icon_check; $state '' or focus, see
    // button) MEL_K_GAP_XS left of the content (r5), its centre at $cy; on the same line $count (MEL_K_PX_CAPTION, text
    // 3: «Выбрано: 2 из 9»; '' none) to the right edge of the content. Edges: those of the button and the count.
    /**
     * @param string $id
     * @param string $label
     * @param string $count
     * @param string $state
     * @param int $cy
     * @return MelKitItem
     */
    public static function panel_done($id, $label, $count, $state = '', $cy = 0)
    {
        $cx0 = MEL_K_PANEL_INSET + MEL_K_PANEL_INNER;
        $b = self::button($label, 'k_icon_check', $state, $cx0 - MEL_K_GAP_XS, $cy);
        $parts = array($b);
        if ($count !== '')
        {
            $c = self::text($count, MEL_K_PX_CAPTION, MEL_K_C_TEXT3, 0, $cy);
            $parts[] = self::move($c, MEL_K_PANEL_W - $cx0 - $c['x1'], 0);
        }
        $it = self::join('panel_done', $parts);
        $it['mid'] = $b['mid'];
        $it['cy'] = $b['cy'];
        $it['caps'] = true;
        return self::group($id, $it);
    }

    // The side panel V2 over a screen (r5 panel, r6 sub_panel) from the top down by stack: the head (panel_head)
    // [-s- sub] -l- (after sub -m-) the groups of lines, -s- a rule (MEL_K_C_LINE, 1 px) -s- between them; the last
    // group is the list: its lines in the panel {id}_rows inside the clipping panel {id}_list, MEL_K_PANEL_VIS of them
    // shown from line top, more: the scroll bar (bar scroll, MEL_K_PANEL_SCROLL_W, MEL_K_GAP_XS from the right edge of
    // the panel, its thumb {id}_thumb) on the right of the list; then -s- the hint «› hint» (the key of RIGHT: hint),
    // -m- the line done (panel_done, the group {id}_done) and -m- the note (MEL_K_PX_CAPTION, text 3, 2 lines a step
    // MEL_K_STEP_PANEL_NOTE). $v:
    //   id      the prefix of the ids (default kp): {id}_dim, the dim of the screen, and the panel {id}_pnl;
    //   title, back, sub  the title, ‹ before it (a submenu) and the subtitle (MEL_K_PX_SECONDARY, text 2; '' none);
    //   dim     dim (MEL_K_C_DIM: «Действия») or dim_strong (MEL_K_C_DIM_STRONG: «Озвучка», «Балансеры»);
    //   x       the left of the panel (default 1920 - MEL_K_PANEL_W; 1920: off the screen, to slide in);
    //   groups  lists of lines, a line array('id' => the id of its group, …$r of panel_row);
    //   top     the first line of the list shown (default 0; kept within the list);
    //   hint, note  '' or none: none; done: null or array('label', 'count', 'state') of panel_done.
    // Its edges: the screen; ids: those of its components with ids.
    /**
     * @param array{id?: string, title: string, back?: bool, sub?: string, dim?: string, x?: int, groups: list<list<MelKitPanelRow>>, top?: int, hint?: string, done?: array{label: string, count: string, state: string}|null, note?: string} $v
     * @return MelKitItem
     */
    public static function panel($v)
    {
        $p = isset($v['id']) ? (string) $v['id'] : 'kp';
        $W = MEL_K_PANEL_W;
        $cx0 = MEL_K_PANEL_INSET + MEL_K_PANEL_INNER;
        $blocks = array(self::panel_head($v['title'], !empty($v['back'])));
        $gaps = array();
        $sub = isset($v['sub']) ? (string) $v['sub'] : '';
        if ($sub !== '')
        {
            $blocks[] = self::text($sub, MEL_K_PX_SECONDARY, MEL_K_C_TEXT2, $cx0, 0, array('w' => $W - 2 * $cx0));
            $gaps[] = 's';
        }
        $groups = $v['groups'];
        $last = count($groups) - 1;
        foreach ($groups as $g => $lines)
        {
            if ($g)
            {
                $blocks[] = self::box(array(mel_gc_rect(mel_gc_at($W - 2 * $cx0, 1, $cx0, 0), MEL_K_C_LINE)), $cx0, 0,
                    $W - 2 * $cx0, 1);
                $gaps[] = 's';
                $gaps[] = 's';
            }
            else
                $gaps[] = $sub !== '' ? 'm' : 'l';
            $rows = array();
            foreach ($lines as $k => $r)
                $rows[] = self::panel_row(isset($r['id']) ? $r['id'] : '', $r, $k * MEL_K_STEP_PANEL);
            $blocks[] = $g < $last ? self::join('panel_group', $rows)
                : self::panel_list($p, $rows, isset($v['top']) ? (int) $v['top'] : 0);
        }
        if (isset($v['hint']) && $v['hint'] !== '')
        {
            $blocks[] = self::hint(array(array('k_icon_chevron-right', (string) $v['hint'])), $cx0, 0, 'panel hint');
            $gaps[] = 's';
        }
        if (!empty($v['done']))
        {
            $d = $v['done'];
            $blocks[] = self::panel_done("{$p}_done", $d['label'], $d['count'], $d['state']);
            $gaps[] = 'm';
        }
        if (isset($v['note']) && $v['note'] !== '')
        {
            $blocks[] = self::text((string) $v['note'], MEL_K_PX_CAPTION, MEL_K_C_TEXT3, $cx0, 0, array('w' => $W - 2 * $cx0,
                'lines' => 2, 'ls' => MEL_K_STEP_PANEL_NOTE - MEL_K_PX_CAPTION));
            $gaps[] = 'm';
        }
        // The first block stays: the head is set by its line.
        $body = self::stack($blocks, array_slice($gaps, 0, max(0, count($blocks) - 1)), null, 'panel');
        $x = isset($v['x']) ? (int) $v['x'] : 1920 - $W;
        $dim = isset($v['dim']) && $v['dim'] === 'dim_strong' ? MEL_K_C_DIM_STRONG : MEL_K_C_DIM;
        $pnl = mel_gc_panel("{$p}_pnl", mel_gc_at($W, 1080, $x, 0), array_merge(array(
            mel_gc_rect(mel_gc_at($W, 1080, 0, 0), MEL_K_C_PANEL), mel_gc_rect(mel_gc_at(1, 1080, 0, 0), MEL_K_C_LINE)),
            $body['defs']));
        $out = self::box(array(mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), $dim, "{$p}_dim"), $pnl), 0, 0, 1920, 1080);
        $out['kind'] = 'panel';
        return $out;
    }

    // The list of a panel: $rows (lines at y 0, 64, …) in the panel {$p}_rows moved up by $top lines inside the
    // clipping panel {$p}_list of MEL_K_PANEL_VIS lines at most, the scroll bar over its right when they are more.
    /**
     * @param string $p
     * @param list<MelKitItem> $rows
     * @param int $top
     * @return MelKitItem
     */
    private static function panel_list($p, $rows, $top)
    {
        $W = MEL_K_PANEL_W;
        $n = count($rows);
        $vis = min($n, MEL_K_PANEL_VIS);
        $top = max(0, min($top, $n - $vis));
        $h = $vis * MEL_K_STEP_PANEL;
        $defs = array();
        foreach ($rows as $r)
            $defs = array_merge($defs, $r['defs']);
        $in = array(mel_gc_panel("{$p}_rows", mel_gc_at($W, $n * MEL_K_STEP_PANEL, 0, -$top * MEL_K_STEP_PANEL), $defs));
        if ($n > $vis)
        {
            $bar = self::bar('scroll', $W - MEL_K_GAP_XS - MEL_K_PANEL_SCROLL_W, 0, MEL_K_PANEL_SCROLL_W, $h,
                $top / ($n - $vis), array('part' => $vis / $n, 'id' => "{$p}_thumb"));
            $in = array_merge($in, $bar['defs']);
        }
        // Options 0: the panel clips its children (the lines out of the window).
        $out = self::box(array(mel_gc_panel("{$p}_list", mel_gc_at($W, $h, 0, 0), $in, 0)), 0, 0, $W, $h);
        $out['kind'] = 'panel_list';
        return $out;
    }
}
