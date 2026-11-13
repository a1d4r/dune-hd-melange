<?php
// The rows of the own card (view_card.php) at the bottom of its first screen and in "down": the
// cast and the rows of posters (similar titles, the movies of a franchise, aio_cm_posters) - their
// items, their strips laid one under the other, drawn and changed as the focus moves; the stop of
// the same row on a screen laid out anew (aio_oc_stop_of); ENTER on a person or a poster; the timer
// asking the rows of posters (aio_oc_load).
// Needs view_card.php (the card, its layout and place), view_card_row.php (the strip, its cells),
// card_meta.php, catalog_card.php, view_person.php, gcomps.php.
// PHP 5.3.6, firmware API only. Types in the PHPDoc: phpstan-types.neon of the repository.

// --- The cast.

// The people of the row of card $c, CAST_MAX at most: the creators first (of a movie its
// directors, of a series its creators) with the role "Режиссёр" ("Создатель"), then the actors
// with the first not empty part of a role "A / B"; one who is both - once, among the creators
// (the same id of TMDB; one of them without it: the same name). -> array(them, how many of them
// are creators).
/**
 * @param AioOcCard $c
 * @return array{list<AioCardCredit>, int}
 */
function aio_oc_people($c)
{
    $m = $c['meta'];
    if (!$m)
        return array(array(), 0);
    $series = $c['tile']['type'] === 'series';
    $label = aio_tr($c['ui'], $series ? 'card_creator' : 'card_director');
    $out = array();
    $nc = 0;
    foreach (array($series ? $m['creators'] : $m['directors'], $m['cast']) as $k => $list)
    {
        foreach ($list as $p)
        {
            $p['role'] = $k === 0 ? $label : aio_oc_first_role($p['role']);
            $dup = false;
            foreach ($out as $q)
                $dup = $dup || ($p['tmdb'] !== '' && $q['tmdb'] !== '' ? $p['tmdb'] === $q['tmdb'] : $p['name'] === $q['name']);
            if (!$dup)
                $out[] = $p;
        }
        if ($k === 0)
            $nc = count($out);
    }
    return array(array_slice($out, 0, AIO_OC_CAST_MAX), min($nc, AIO_OC_CAST_MAX));
}

// Of a role "A / B / C" its first not empty part.
/**
 * @param string $role
 * @return string
 */
function aio_oc_first_role($role)
{
    foreach (preg_split('~(?:^|\s)/(?:\s|$)~', $role) as $r)
    {
        if (($r = aio_clean($r)) !== '')
            return $r;
    }
    return '';
}

// The strip of the cast of $n people, $nc of them creators (a line after them), its title at the
// top of "down" (aio_oc_stack lays it): photos PHOTO_STEP apart, the frame round the photo;
// CAST_VIS whole, the frame up to the last but one, then the row. Id "c".
/**
 * @param int $n
 * @param int $nc
 * @return AioOcStrip
 */
function aio_oc_cast_strip($n, $nc)
{
    return array('n' => $n, 'nc' => $nc, 'w' => AIO_OC_PHOTO_W, 'h' => AIO_OC_PHOTO_H, 'cw' => AIO_OC_NAME_W, 'ch' => AIO_OC_CAST_H,
        'step' => AIO_OC_PHOTO_STEP, 'div' => AIO_OC_CAST_DIV, 'vis' => AIO_OC_CAST_VIS, 'lead' => AIO_OC_CAST_VIS - 2,
        'x' => AIO_CATV_CARD_X, 'y' => AIO_OC_DOWN_Y + AIO_OC_ROW_HEAD, 'id' => 'c');
}

// The strips of the rows ($strips, in their order) laid in "down" one under the other from DOWN_Y,
// ROW_GAP apart, the title of each ROW_HEAD over its items. -> array(the rows by their stops (1..),
// the strips by their ids, the y of what goes under them).
/**
 * @param list<AioOcStrip> $strips
 * @return array{array<int, string>, array<string, AioOcStrip>, int}
 */
function aio_oc_stack($strips)
{
    $y = AIO_OC_DOWN_Y;
    $rows = array();
    $out = array();
    foreach ($strips as $s)
    {
        $s['y'] = $y + AIO_OC_ROW_HEAD;
        $rows[count($rows) + 1] = $s['id'];
        $out[$s['id']] = $s;
        $y = $s['y'] + $s['ch'] + AIO_OC_ROW_GAP;
    }
    return array($rows, $out, $y);
}

// The strip in focus at place $pos: the one of its stop of the page (rows of the layout), '' for none.
/**
 * @param AioOcNav $l
 * @param AioOcPos $pos
 * @return string
 */
function aio_oc_strip_on($l, $pos)
{
    return $pos['z'] === 'down' && isset($l['rows'][$pos['p']]) ? $l['rows'][$pos['p']] : '';
}

// The item of strip $id at place $pos (its memory, aio_oc_pos), 0 for none.
/**
 * @param AioOcPos $pos
 * @param string $id
 * @return int
 */
function aio_oc_strip_k($pos, $id)
{
    return isset($pos['r'][$id]) ? $pos['r'][$id] : 0;
}

// The colour of a name: white; the row in focus, grey but the one of the focus.
/**
 * @param bool $on
 * @param bool $lit
 * @return string
 */
function aio_oc_name_color($on, $lit)
{
    return !$on || $lit ? AIO_GC_TEXT : AIO_GC_TEXT2;
}

// The name of person $p in 2 lines, in its panel "pn<i>".
/**
 * @param AioCardCredit $p
 * @param string $color
 * @return list<array<string, mixed>>
 */
function aio_oc_cast_names($p, $color)
{
    $parts = explode(' ', $p['name'], 2);
    $d = array();
    foreach (array($parts[0], isset($parts[1]) ? $parts[1] : '') as $k => $s)
    {
        if (($s = aio_gc_cut_w(aio_clean($s), 24, AIO_OC_NAME_W)) !== '')
            $d[] = aio_oc_label(0, AIO_OC_CAP_LH * $k, AIO_OC_NAME_W, $s, 24, $color);
    }
    return $d;
}

// Person $p at $i in the strip ("p<i>"): the photo (none: the initials), the name, the role grey in
// 2 lines.
/**
 * @param int $i
 * @param int $x
 * @param AioCardCredit $p
 * @param string $color
 * @return AioGcComp
 */
function aio_oc_cast_cell($i, $x, $p, $color)
{
    $d = array(aio_gc_rect(aio_gc_at(AIO_OC_PHOTO_W, AIO_OC_PHOTO_H, 0, 0), AIO_OC_NO_PHOTO));
    if ($p['photo'] !== '')
        $d[] = aio_gc_image(aio_gc_at(AIO_OC_PHOTO_W, AIO_OC_PHOTO_H, 0, 0), $p['photo']);
    else
    {
        $ini = '';
        foreach (array_slice(explode(' ', $p['name']), 0, 2) as $w)
            $ini .= mb_substr($w, 0, 1, 'UTF-8');
        $d[] = aio_oc_label(0, 40, AIO_OC_PHOTO_W, $ini, 48, AIO_GC_DIM, array(GCompTtfLabelDef::halign => HALIGN_CENTER));
    }
    $d[] = aio_gc_panel("pn$i", aio_gc_at(AIO_OC_NAME_W, 60, 0, AIO_OC_NAME_DY), aio_oc_cast_names($p, $color));
    foreach (aio_gc_wrap($p['role'], 24, AIO_OC_NAME_W, 2, true) as $k => $s)
        $d[] = aio_oc_label(0, AIO_OC_NAME_DY + (2 + $k) * AIO_OC_CAP_LH, AIO_OC_NAME_W, $s, 24, AIO_GC_DIM);
    return aio_gc_panel("p$i", aio_gc_at(AIO_OC_NAME_W, AIO_OC_CAST_H, $x, 0), $d);
}

// The hint at the right of the title of the row ("chint"), the row in focus ($on) on person $p
// with an id of TMDB: ENTER opens the page of the person.
/**
 * @param AioCardCredit|null $p
 * @param bool $on
 * @param string $lang
 * @return list<AioGcComp>
 */
function aio_oc_cast_hint($p, $on, $lang)
{
    if (!$on || !$p || $p['tmdb'] === '')
        return array();
    return array(aio_oc_label(0, 6, AIO_OC_HINT_R - AIO_CATV_CARD_X, aio_tr($lang, 'card_cast_hint'), 24, AIO_GC_DIM,
        array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// The rows of posters of card $c (aio_cm_posters) by their ids, in their order on the page: tiles,
// nc (before the line), cur (the index of the movie of the card, -1 for none), the title and what
// goes grey after it. A franchise of PARTS_IN at most with similar titles: first in "s" "Части
// франшизы и похожие" after a line; else its own row "f" ("Части франшизы · Дюна · 2 фильма")
// and "s" "Похожие" under it. Some still asked (wait): the stubs "w" ("Похожие"; "Части франшизы и
// похожие" while the franchise may go in it), under the long franchise if it came.
/**
 * @param AioOcCard $c
 * @return array<string, array{tiles: list<AioCatTile>, nc: int, cur: int, title: string, rest: string}>
 */
function aio_oc_poster_rows($c)
{
    $m = $c['posters'];
    $ui = $c['ui'];
    $np = count($m['parts']);
    $short = $np > 0 && $np <= AIO_OC_PARTS_IN;
    $rows = array();
    // The similar ones are asked last: none known while some are asked.
    if ($short && $m['sim'])
        return array('s' => array('tiles' => array_merge($m['parts'], $m['sim']), 'nc' => $np, 'cur' => $m['cur'],
            'title' => aio_tr($ui, 'card_parts_sim'), 'rest' => ''));
    if ($np > 0 && (!$short || !$m['wait']))
    {
        $n = $np . ' ' . aio_gc_plural($ui, $np, 'card_movies');
        $rows['f'] = array('tiles' => $m['parts'], 'nc' => 0, 'cur' => $m['cur'], 'title' => aio_tr($ui, 'card_parts'),
            'rest' => $m['coll'] !== '' ? "{$m['coll']} · $n" : $n);
    }
    if ($m['sim'])
        $rows['s'] = array('tiles' => $m['sim'], 'nc' => 0, 'cur' => -1, 'title' => aio_tr($ui, 'card_sim'), 'rest' => '');
    if ($m['wait'])
    {
        $sim = $m['wait'] === array('sim') && !$short;
        $rows['w'] = array('tiles' => array(), 'nc' => 0, 'cur' => -1, 'title' => aio_tr($ui, $sim ? 'card_sim' : 'card_parts_sim'),
            'rest' => '');
    }
    return $rows;
}

// The items of row $id of card $c: the people of the cast ("c", aio_oc_people), the tiles of a row
// of posters (aio_oc_poster_rows).
/**
 * @param AioOcCard $c
 * @param string $id
 * @return list<mixed>
 */
function aio_oc_row_items($c, $id)
{
    if ($id !== 'c')
    {
        $rows = aio_oc_poster_rows($c);
        return isset($rows[$id]) ? $rows[$id]['tiles'] : array();
    }
    list($people) = aio_oc_people($c);
    return $people;
}

// The colour of the name of item $i of row $s, item $k in focus, the row in focus ($on): the cast
// white, the row in focus grey but the one of the focus (aio_oc_name_color); a poster grey, white
// in focus; the stubs (no names) grey.
/**
 * @param AioOcStrip $s
 * @param int $i
 * @param int $k
 * @param bool $on
 * @return string
 */
function aio_oc_row_color($s, $i, $k, $on)
{
    if ($s['id'] !== 'c')
        return $on && $i === $k && $s['id'] !== 'w' ? AIO_GC_TEXT : AIO_GC_TEXT2;
    return aio_oc_name_color($on, $i === $k);
}

// The title of row $id of card $c and what goes grey after it ('' none): "В ролях", of a row of
// posters as aio_oc_poster_rows.
/**
 * @param AioOcCard $c
 * @param string $id
 * @return array{string, string}
 */
function aio_oc_row_head($c, $id)
{
    if ($id === 'c')
        return array(aio_tr($c['ui'], 'card_cast'), '');
    $rows = aio_oc_poster_rows($c);
    return isset($rows[$id]) ? array($rows[$id]['title'], $rows[$id]['rest']) : array('', '');
}

// The bands of the ink of the lines under the items of row $s (aio_oc_band): the names and the
// roles of the cast (aio_oc_cast_cell), 4 lines; the titles of the posters in 1 or 2 lines and the
// year under them (aio_oc_poster_titles).
/**
 * @param AioOcStrip $s
 * @return list<array{int, int}>
 */
function aio_oc_row_bands($s)
{
    $b = array();
    $y = $s['id'] === 'c' ? $s['y'] + AIO_OC_NAME_DY : $s['y'] + AIO_OC_POSTER_H + AIO_OC_POSTER_CAP_DY;
    $lh = AIO_OC_CAP_LH;
    $gap = AIO_OC_CAP_GAP;
    foreach ($s['id'] === 'c' ? array(0, $lh, 2 * $lh, 3 * $lh) : array(0, $lh, $lh + $gap, 2 * $lh + $gap) as $dy)
        $b[] = aio_oc_band($y + $dy, 24);
    return $b;
}

// The titles under tile $t of a row of posters in $color: its name, its year.
/**
 * @param AioCatTile $t
 * @param string $color
 * @return list<AioGcComp>
 */
function aio_oc_tile_titles($t, $color)
{
    return aio_oc_poster_titles($t['name'], $t['year'] !== '' ? array($t['year']) : array(), $color);
}

// Item $i of $items of row $s, its name in $color: a person (aio_oc_cast_cell); a poster
// (aio_oc_poster_cell), the movie of the card ($cur) darkened, "Этот фильм" over it; a stub of the
// row "w" (aio_oc_poster_stub).
/**
 * @param AioOcStrip $s
 * @param list<mixed> $items
 * @param int $i
 * @param string $color
 * @param int $cur
 * @param string $ui
 * @return AioGcComp
 */
function aio_oc_row_cell($s, $items, $i, $color, $cur, $ui)
{
    if ($s['id'] === 'c')
        return aio_oc_cast_cell($i, aio_oc_strip_x($s, $i), $items[$i], $color);
    if ($s['id'] === 'w')
        return aio_oc_poster_stub($s, $i);
    $over = array();
    if ($i === $cur)
        $over = array(aio_gc_rect(aio_gc_at(AIO_OC_POSTER_W, AIO_OC_POSTER_H, 0, 0), AIO_OC_THIS),
            aio_oc_label(0, intval((AIO_OC_POSTER_H - AIO_CATV_LINE_H) / 2), AIO_OC_POSTER_W, aio_tr($ui, 'card_this_movie'),
                AIO_CATV_LINE_PX, AIO_GC_TEXT, array(GCompTtfLabelDef::halign => HALIGN_CENTER)));
    return aio_oc_poster_cell($s, $i, $items[$i], aio_oc_tile_titles($items[$i], $color), $over);
}

// The change of the name of item $i of $items of row $s to $color.
/**
 * @param AioOcStrip $s
 * @param list<mixed> $items
 * @param int $i
 * @param string $color
 * @return AioGcChange
 */
function aio_oc_row_name($s, $items, $i, $color)
{
    if ($s['id'] !== 'c')
        return aio_gc_change("{$s['id']}n$i", null, GCOMP_TRANSITION_NONE, aio_oc_tile_titles($items[$i], $color));
    return aio_gc_change("pn$i", null, GCOMP_TRANSITION_NONE, aio_oc_cast_names($items[$i], $color));
}

// The hint at the right of the title of row $s ("<id>hint"), item $k of $items in focus, the row
// in focus ($on): of the cast aio_oc_cast_hint; none of a row of posters.
/**
 * @param AioOcCard $c
 * @param AioOcStrip $s
 * @param list<mixed> $items
 * @param int $k
 * @param bool $on
 * @return list<AioGcComp>
 */
function aio_oc_row_hint($c, $s, $items, $k, $on)
{
    return $s['id'] === 'c' ? aio_oc_cast_hint($items[$k], $on, $c['ui']) : array();
}

// The title of row $id of card $c with layout $l (aio_oc_row_title, at the top of its panel): TITLE_W
// at most; of the first row on the first screen ($first) tw of the layout, before the hint of what
// DOWN shows - it is not in "down".
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param string $id
 * @param bool $first
 * @return list<AioGcComp>
 */
function aio_oc_row_titles($c, $l, $id, $first)
{
    list($title, $rest) = aio_oc_row_head($c, $id);
    return aio_oc_row_title(0, $title, $rest, $first && $l['rows'][1] === $id ? $l['tw'] : AIO_OC_TITLE_W);
}

// The rows of the layout of card $c at place $pos, each at the y of its strip in "down": the title
// ("<id>head", aio_oc_row_titles) and the hint at its right (aio_oc_row_hint), the items
// (aio_oc_row_items) in a clip ("<id>cl") from the left edge of the screen to HINT_R (the one
// after the last whole peeks there; posters: to the right edge), the strip moved as
// aio_oc_strip_at; the frame of the focus (shown at the stop of the row).
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param AioOcPos $pos
 * @return list<array<string, mixed>>
 */
function aio_oc_rows($c, $l, $pos)
{
    $d = array();
    $posters = aio_oc_poster_rows($c);
    foreach ($l['strips'] as $id => $s)
    {
        $items = aio_oc_row_items($c, $id);
        $y = $s['y'] - AIO_OC_ROW_HEAD;
        $on = aio_oc_strip_on($l, $pos) === $id;
        list($k) = aio_oc_strip_at($s, aio_oc_strip_k($pos, $id));
        $cur = isset($posters[$id]) ? $posters[$id]['cur'] : -1;
        $cells = array();
        for ($i = 0; $i < $s['n']; $i++)
            $cells[] = aio_oc_row_cell($s, $items, $i, aio_oc_row_color($s, $i, $k, $on), $cur, $c['ui']);
        $d[] = aio_gc_panel("{$id}head", aio_gc_at(1920, AIO_CATV_LINE_H, 0, $y), aio_oc_row_titles($c, $l, $id, $pos['z'] !== 'down'));
        $d[] = aio_gc_panel("{$id}hint", aio_gc_at(AIO_OC_HINT_R - AIO_CATV_CARD_X, AIO_CATV_LINE_H, AIO_CATV_CARD_X, $y),
            aio_oc_row_hint($c, $s, $items, $k, $on));
        $d[] = aio_gc_panel("{$id}cl", aio_gc_at($id === 'c' ? AIO_OC_HINT_R : 1920, $s['ch'], 0, $s['y']),
            array(aio_oc_strip_panel($s, $k, $cells)), 0);
        $d[] = aio_oc_strip_frame($s, $k, $on);
    }
    return $d;
}

// The changes of the rows from place $old to $new: the strip and the frame (aio_oc_strip_changes),
// the names of the focus lit or dimmed (aio_oc_row_color: of the cast all of them as the row gets
// the focus or loses it), the hint; the titles anew as the first screen comes or goes (aio_oc_row_titles).
/**
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @param AioOcPos $old
 * @param AioOcPos $new
 * @return list<AioGcChange>
 */
function aio_oc_rows_changes($c, $l, $old, $new)
{
    $ch = array();
    foreach ($l['strips'] as $id => $s)
    {
        $items = aio_oc_row_items($c, $id);
        $on = aio_oc_strip_on($l, $new) === $id;
        $oon = aio_oc_strip_on($l, $old) === $id;
        list($k) = aio_oc_strip_at($s, aio_oc_strip_k($new, $id));
        list($ok) = aio_oc_strip_at($s, aio_oc_strip_k($old, $id));
        $ch = array_merge($ch, aio_oc_strip_changes($s, $ok, $k, $oon, $on));
        for ($i = 0; $i < $s['n']; $i++)
        {
            $color = aio_oc_row_color($s, $i, $k, $on);
            if ($color !== aio_oc_row_color($s, $i, $ok, $oon))
                $ch[] = aio_oc_row_name($s, $items, $i, $color);
        }
        $hint = aio_oc_row_hint($c, $s, $items, $k, $on);
        if ($hint !== aio_oc_row_hint($c, $s, $items, $ok, $oon))
            $ch[] = aio_gc_change("{$id}hint", null, GCOMP_TRANSITION_NONE, $hint);
        $first = $new['z'] !== 'down';
        if ($first !== ($old['z'] !== 'down'))
        {
            $head = aio_oc_row_titles($c, $l, $id, $first);
            if ($head !== aio_oc_row_titles($c, $l, $id, !$first))
                $ch[] = aio_gc_change("{$id}head", null, GCOMP_TRANSITION_NONE, $head);
        }
    }
    return $ch;
}

// --- The place of the focus.

// Stop $p of a screen laid out with rows $was (their ids by the stops, rs of aio_oc_state) -> the
// stop of the same place among $rows: a screen of the card shown before its rows of posters came
// (aio_oc_enter made it anew, Back came to the old screen, its timer got them) stays at its row, at
// its stop of "About" after the rows; the stubs (row "w"): the first row not among $was (came); a
// row gone, the stubs with none: $p.
/**
 * @param list<string> $was
 * @param array<int, string> $rows
 * @param int $p
 * @return int
 */
function aio_oc_stop_of($was, $rows, $p)
{
    if ($p < 1)
        return $p;
    if ($p > count($was))
        return $p - count($was) + count($rows);
    $came = $was[$p - 1] === 'w' ? array_diff($rows, $was) : array();
    if ($came)
        return intval(key($came));
    $q = array_search($was[$p - 1], $rows, true);
    return $q !== false ? intval($q) : $p;
}

// --- Keys.

// ENTER on person $k of the row of card $c ($key): with an id of TMDB its page (view_person.php)
// with the profession the row shows (a creator's, else "Актёр"); without, nothing.
/**
 * @param string $key
 * @param AioOcCard $c
 * @param int $k
 * @return AioAction|null
 */
function aio_oc_person($key, $c, $k)
{
    list($people, $nc) = aio_oc_people($c);
    $p = isset($people[$k]) ? $people[$k] : null;
    aio_log("card: own $key: ENTER on person $k" . ($p && $p['tmdb'] !== '' ? ", tmdb:{$p['tmdb']}" : ', no id of TMDB'));
    if (!$p || $p['tmdb'] === '')
        return null;
    $job = $k < $nc ? $p['role'] : aio_tr($c['ui'], 'person_job_actor');
    return aio_pp_open($p, $job, $c['lang'], $c['ui']);
}

// ENTER on poster $k of row $id of card $c ($key): the own card of its title (aio_card_enter, as
// a tile of the catalog); on the movie of the card ("Этот фильм") nothing. Back comes to this
// card at the same poster (its sel_state).
/**
 * @param string $key
 * @param AioOcCard $c
 * @param string $id
 * @param int $k
 * @return AioAction|null
 */
function aio_oc_tile_enter($key, $c, $id, $k)
{
    $rows = aio_oc_poster_rows($c);
    if (!isset($rows[$id]['tiles'][$k]))
        return null;
    $t = $rows[$id]['tiles'][$k];
    $this_one = $k === $rows[$id]['cur'];
    aio_log("card: own $key: ENTER on $id $k, {$t['type']}:" . aio_cm_id($t) . ($this_one ? ', this movie' : ''));
    return $this_one ? null : aio_card_enter($t, $c['lang']);
}

// The timer of card $c ($key) shown with layout $l: the next of its rows of posters asked
// (aio_cm_posters_step, ROW_LIMIT s), the screen anew with it at the same place (its sel_state none:
// the place of the card), at the same row (aio_oc_stop_of): from the stubs to the first row that
// came, with none to the row after them. Nothing to ask (a screen shown before the rows came to
// another of the same card): no request, the screen anew all the same, its stubs gone, no timer.
/**
 * @param string $key
 * @param AioOcCard $c
 * @param AioOcNav $l
 * @return AioAction
 */
function aio_oc_load($key, $c, $l)
{
    if (!$c['posters']['wait'])
        return aio_replace_act("card:$key", $c['tile']['name']);
    $c['posters'] = aio_cm_posters_step($key, $c['tile'], $c['meta'], $c['posters'], AIO_CM_ROW_LIMIT, true);
    if ($c['pos']['z'] === 'down')
    {
        $n = aio_oc_layout($c);
        $c['pos']['p'] = aio_oc_stop_of(array_values($l['rows']), $n['rows'], $c['pos']['p']);
    }
    unset($c['nav']);
    AioOc::$cards[$key] = $c;
    return aio_replace_act("card:$key", $c['tile']['name']);
}
