<?php
// Details screen of one release (INFO on a list row): view_gcomps 1920x1080
// on the darkened fanart, as the list screen. Page 1, the passport: the name,
// then three columns - file and video, tracks, where from. The next pages,
// the raw data as compact YAML in three columns: the stream as AIOStreams
// sent it, then the row as Melange computed it (without the stream). P+/P-
// slide the pages with change_gcomps (as the list scrolls); the page travels
// in sel_state {"page":N}. URLs are cut to the host: they carry tokens. The
// page of the release on its tracker (row key 'release_url', streamData.infoUrl)
// is in the right column and, up to MEL_INF_QR_MAX bytes, a QR on page 2.
// Reads Mel::$state.

// YAML lines at most, both blocks (a huge reply stays bounded); the Melange
// block takes at most half.
define('MEL_INFO_LINES', 500);
define('MEL_INFO_DEPTH', 12);
define('MEL_INF_X', 75);
define('MEL_INF_W', 1770);      // to x 1845 = 1920 - 75
define('MEL_INF_CW', 563);      // three columns, 40 px apart
define('MEL_INF_CGAP', 40);
define('MEL_INF_Y', 116);       // the pages: under the title, down to y 962
define('MEL_INF_H', 846);
define('MEL_INF_HINT_Y', 990);  // the P+/P- hint and the counter, as the vendor hint row
define('MEL_INF_COLS_Y', 84);   // page 1: the columns at y 200, under a name of 2 lines
define('MEL_INF_HEAD_H', 40);   // page 1: column heads, 28 px
define('MEL_INF_LH', 32);       // page 1: labels 24 px, values 26 px
define('MEL_INF_GAP', 6);       // page 1: between fields
define('MEL_INF_SUBS_MIN', 3);  // page 1: subtitle lines kept when tracks do not fit
define('MEL_INF_TIND', 24);     // page 1: indent of the lines of a track after its first
define('MEL_INF_YLH', 30);      // YAML pages: 24 px lines
define('MEL_INF_YROWS', 28);    // YAML pages: lines of a column
define('MEL_INF_IND', 20);      // YAML pages: indent of a level
// The QR page: bytes of the address at most: qrcode/qrcode.php makes versions
// 1-10 only, 213 bytes at level M (QR_MAX_LENGTH); past them it fails with E_USER_ERROR.
define('MEL_INF_QR_MAX', 213);
define('MEL_INF_QR_SIDE', 520); // the QR page: side of the QR at most, px (a module of whole px)
define('MEL_INF_QR_GAP', 60);   // the QR page: from the QR to the text
define('MEL_INF_QR_LINES', 8);  // the QR page: lines of the address at most (30 px)

// open_folder of the details screen of row $n of list $st.
/**
 * @param MelState $st
 * @param int $n
 * @return MelAction
 */
function mel_info_open($st, $n)
{
    mel_log("info: row $n of " . count($st['rows']));
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => MEL_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "info:{$st['rid']}:$n",
            PluginOpenFolderActionData::caption =>
                mel_tr($st['lang'], 'info_title') . ' ' . ($n + 1) . ' / ' . count($st['rows'])));
}

// "https://host:port/…" of a URL (no path, query or user: the user part
// ends at the last "@" of the authority); other text as is.
/**
 * @param string $s
 * @return string
 */
function mel_info_url($s)
{
    if (!preg_match('~^\s*(https?://)([^/?#\s]*)~i', $s, $m))
        return $s;
    $at = strrpos($m[2], '@');
    return $m[1] . ($at === false ? $m[2] : substr($m[2], $at + 1)) . "/\xE2\x80\xA6";
}

/**
 * @param mixed $v
 * @return string
 */
function mel_info_scalar($v)
{
    if (is_null($v))
        return 'null';
    if (is_bool($v))
        return $v ? 'true' : 'false';
    // Sizes are floats: PHP on Dune is 32-bit.
    if (is_float($v) && $v == floor($v) && abs($v) < 1e15)
        return number_format($v, 0, '.', '');
    // As sent, unmasked: a local plugin may show its secrets on its screen.
    return mel_info_url(mel_utf8($v));
}

// One line of text as sent: line breaks and tabs as a space; "|" kept
// (mel_clean makes it "/" for the list screen).
/**
 * @param string $s
 * @return string
 */
function mel_info_clean($s)
{
    return trim(preg_replace('/[\\r\\n\\t]+/', ' ', strval($s)));
}

// mel_gc_cut of the pages of this screen: the text by mel_info_clean.
/**
 * @param MelGcGeom $geom
 * @param string $text
 * @param int $size
 * @param string $color
 * @return MelGcComp
 */
function mel_info_cut($geom, $text, $size, $color)
{
    $l = mel_gc_cut(null, $geom, '', $size, $color);
    $l[GComponentDef::specific_def][GCompTtfLabelDef::text] = mel_info_clean($text);
    return $l;
}

// --- Page 1: the passport.

// "1–7, 9, 11" of whole numbers; a run of three or more as a range.
/**
 * @param list<int> $a
 * @return string
 */
function mel_info_ranges($a)
{
    $a = array_values(array_unique($a));
    sort($a);
    $out = array();
    for ($i = 0; $i < count($a); $i = $j + 1)
    {
        for ($j = $i; $j + 1 < count($a) && $a[$j + 1] === $a[$j] + 1; $j++)
            ;
        if ($j - $i >= 2)
            $out[] = $a[$i] . "\xE2\x80\x93" . $a[$j];
        else
        {
            for ($k = $i; $k <= $j; $k++)
                $out[] = $a[$k];
        }
    }
    return implode(', ', $out);
}

// "S5 E3" of one episode (mel_se), else "S1–5 · E1–62"; '' without numbers.
/**
 * @param list<int> $seasons
 * @param list<int> $episodes
 * @return string
 */
function mel_info_eps($seasons, $episodes)
{
    if (count($seasons) === 1 && count($episodes) === 1)
        return mel_se($seasons[0], $episodes[0]);
    $p = array();
    if ($seasons)
        $p[] = 'S' . mel_info_ranges($seasons);
    if ($episodes)
        $p[] = 'E' . mel_info_ranges($episodes);
    return implode(' · ', $p);
}

// "3:00:23" of seconds.
/**
 * @param float $s
 * @return string
 */
function mel_info_duration($s)
{
    $s = intval(round($s));
    return $s >= 3600 ? sprintf('%d:%02d:%02d', $s / 3600, $s / 60 % 60, $s % 60) :
        sprintf('%d:%02d', $s / 60, $s % 60);
}

// A column of page 1 with its head at y $top: its x, the y of the next field,
// its bottom, the x of values after the widest of its $labels, components,
// no track left out yet.
/**
 * @param int $i
 * @param string $head
 * @param array<string> $labels
 * @param int $top
 * @return MelInfoCol
 */
function mel_info_col($i, $head, $labels, $top)
{
    $x = $i * (MEL_INF_CW + MEL_INF_CGAP);
    $lw = 0;
    foreach ($labels as $l)
        $lw = max($lw, mel_gc_w($l, 24));
    return array('x' => $x, 'y' => $top + MEL_INF_HEAD_H, 'end' => MEL_INF_H, 'lw' => min(240, $lw + 14),
        'd' => array(mel_info_cut(mel_gc_at(MEL_INF_CW, MEL_INF_HEAD_H, $x, $top),
            mel_gc_cut_w($head, 28, MEL_INF_CW), 28, MEL_GC_TEXT)), 'hid' => 0);
}

// Value lines a field of column $c has room for: $block - its label takes a
// line of its own.
/**
 * @param MelInfoCol $c
 * @param bool $block
 * @return int
 */
function mel_info_room($c, $block)
{
    return intval(($c['end'] - $c['y']) / MEL_INF_LH) - ($block ? 1 : 0);
}

// Value lines left in column $c for $fields block fields: their labels and
// the gaps between them taken.
/**
 * @param MelInfoCol $c
 * @param int $fields
 * @return int
 */
function mel_info_left($c, $fields)
{
    return intval(($c['end'] - $c['y'] - ($fields - 1) * MEL_INF_GAP) / MEL_INF_LH) - $fields;
}

// A field of column $c: the label (24 px, dimmed) at the left of the first
// value line or ($block) on a line above them; the value lines (26 px) cut to
// the width with "…", those of $cont (indexes) indented by MEL_INF_TIND. No
// lines: no field.
/**
 * @param MelInfoCol $c
 * @param string $label
 * @param list<string> $lines
 * @param bool $block
 * @param string $color
 * @param array<int, true> $cont
 * @return void
 */
function mel_info_put(&$c, $label, $lines, $block, $color = MEL_GC_TEXT2, $cont = array())
{
    if (!$lines)
        return;
    $vx = $block ? 0 : $c['lw'];
    if ($label !== '')
    {
        $lw = $block ? MEL_INF_CW : $c['lw'] - 10;
        $c['d'][] = mel_info_cut(mel_gc_at($lw, MEL_INF_LH, $c['x'], $c['y']), mel_gc_cut_w($label, 24, $lw), 24,
            MEL_GC_DIM);
        $c['y'] += $block ? MEL_INF_LH : 0;
    }
    foreach ($lines as $i => $l)
    {
        $x = $vx + (isset($cont[$i]) ? MEL_INF_TIND : 0);
        $c['d'][] = mel_info_cut(mel_gc_at(MEL_INF_CW - $x, MEL_INF_LH, $c['x'] + $x, $c['y']),
            mel_gc_cut_w($l, 26, MEL_INF_CW - $x), 26, $color);
        $c['y'] += MEL_INF_LH;
    }
    $c['y'] += MEL_INF_GAP;
}

// Tracks (lines each, mel_info_wrap_tracks) as a block field of column $c.
/**
 * @param MelInfoCol $c
 * @param string $label
 * @param list<list<string>> $tracks
 * @return void
 */
function mel_info_put_tracks(&$c, $label, $tracks)
{
    $lines = array();
    $cont = array();
    foreach ($tracks as $t)
    {
        foreach ($t as $i => $l)
        {
            if ($i > 0)
                $cont[count($lines)] = true;
            $lines[] = $l;
        }
    }
    mel_info_put($c, $label, $lines, true, MEL_GC_TEXT2, $cont);
}

// A text field of up to $max lines, wrapped by words or ($items) by " · ".
/**
 * @param MelInfoCol $c
 * @param string $label
 * @param string $v
 * @param int $max
 * @param bool $block
 * @param bool $items
 * @return void
 */
function mel_info_field(&$c, $label, $v, $max, $block = false, $items = false)
{
    $v = mel_info_clean($v);
    $max = min($max, mel_info_room($c, $block));
    if ($v === '' || $max < 1)
        return;
    $w = MEL_INF_CW - ($block ? 0 : $c['lw']);
    mel_info_put($c, $label, $items ? mel_gc_wrap_items($v, 26, $w, $max) : mel_gc_wrap($v, 26, $w, $max), $block);
}

// A subtitle codec of ffprobe in capitals, the long ones by their usual names ("SRT").
/**
 * @param string $c
 * @return string
 */
function mel_info_sub_codec($c)
{
    $names = array('subrip' => 'SRT', 'hdmv_pgs_subtitle' => 'PGS', 'dvd_subtitle' => 'VobSub', 'mov_text' => 'TX3G');
    $c = strtolower($c);
    return isset($names[$c]) ? $names[$c] : strtoupper($c);
}

// One line of a track: "• RU · TrueHD 5.1 · Дубляж / HotVoice 41", "•" for
// the default one (★ is in no font of the firmware, ● not in NotoSans Condensed).
// A title starting with the codec and channels ("DD 5.1 - Dub", "DD5.1"; the
// codec alone without channels) goes without them and the separators after;
// not when a word goes on ("DTS-HD", "DD+") or a hyphen is glued to the next
// one ("DD 5.1 -EX").
/**
 * @param string $lang
 * @param string $codec
 * @param string $ch
 * @param string $title
 * @param bool $default
 * @return string
 */
function mel_info_track($lang, $codec, $ch, $title, $default)
{
    $re = '~^' . preg_quote($codec, '~') . ($ch !== '' ? ' ?' . preg_quote($ch, '~') : '') .
        '(?![\pL\pN+\-])(?:[\s\x{2013}\x{2014}:,/|@\x{00B7}]|-(?![\pL\pN]))*+(?!-)~iu';
    $t = $codec === '' ? null : preg_replace($re, '', $title);
    if (is_string($t))
        $title = $t;
    $p = array_filter(array($lang, trim("$codec $ch"), $title), 'strlen');
    return ($default ? "\xE2\x80\xA2 " : '') . implode(' · ', $p);
}

// Tracks of a list of parsedFile, empty objects left out.
/**
 * @param array<mixed> $pf
 * @param string $k
 * @return list<non-empty-array<mixed>>
 */
function mel_info_raw_tracks($pf, $k)
{
    $out = array();
    foreach (mel_arr($pf, $k) as $t)
    {
        if (is_array($t) && $t)
            $out[] = $t;
    }
    return $out;
}

// array(lines, count) of the audio tracks of row $r (its own, at most 99, or
// JacRed's), the default marked by parsedFile.audioTracks (row tracks follow
// its order, empty objects too); the count of all of them.
/**
 * @param MelRow $r
 * @param array<mixed> $pf
 * @return array{list<string>, int}
 */
function mel_info_audio($r, $pf)
{
    $raw = array_values(array_filter(mel_arr($pf, 'audioTracks'), 'is_array'));
    $out = array();
    foreach ($r['tracks'] as $i => $t)
    {
        $l = mel_info_track($t['lang'], $t['codec'], $t['ch'], $t['title'],
            isset($raw[$i]['default']) && $raw[$i]['default'] === true);
        if ($l !== '')
            $out[] = $l;
    }
    return array($out, max(count($out), count(mel_info_raw_tracks($pf, 'audioTracks'))));
}

// Subtitles of row $r, null without any: the label with the count of all,
// the tracks of parsedFile.subtitleTracks (mel_info_wrap_tracks in the lines
// of a column), else the languages (JacRed, parsedFile.subtitles) joined by
// ", "; the lines the middle column gives them at most: all of the tracks,
// MEL_INF_SUBS_MIN of the languages.
/**
 * @param MelRow $r
 * @param array<mixed> $pf
 * @param MelGcText $tt
 * @return MelInfoSubs|null
 */
function mel_info_subs($r, $pf, $tt)
{
    $all = mel_info_raw_tracks($pf, 'subtitleTracks');
    // Lines of a column (under a name of 2 lines): a block of it has fewer,
    // so a track cut at them is whole nowhere. A track is a line at least:
    // past $max + 1 of them none is wrapped.
    $max = mel_info_room(mel_info_col(1, '', array(), MEL_INF_COLS_Y), false);
    $out = array();
    foreach (array_slice($all, 0, $max + 1) as $t)
    {
        $l = mel_str($t, 'lang');
        $out[] = mel_info_track($l !== '' ? mel_lang_code($l) : '', mel_info_sub_codec(mel_str($t, 'codec')), '',
            mel_info_clean(mel_str($t, 'title')), isset($t['default']) && $t['default'] === true);
    }
    if (!$out && !$r['subs'])
        return null;
    $n = $out ? count($all) : count($r['subs']);
    $st = mel_info_wrap_tracks($out, $max);
    $codes = $out ? '' : implode(', ', $r['subs']);
    return array('label' => "{$tt['subs']} ($n)", 'st' => $st, 'codes' => $codes, 'n' => $n,
        'lines' => $codes !== '' ? count(mel_gc_wrap($codes, 26, MEL_INF_CW, MEL_INF_SUBS_MIN)) : mel_info_nlines($st));
}

// Subtitles $s in $max lines (mel_info_more, the languages cut with "…"):
// array(tracks, how many of them are not shown whole).
/**
 * @param MelInfoSubs $s
 * @param int $max
 * @param string $lang
 * @return array{list<list<string>>, int}
 */
function mel_info_subs_fit($s, $max, $lang)
{
    if ($max < 1)
        return array(array(), $s['n']);
    if ($s['codes'] === '')
        return mel_info_more($s['st'], $max, $s['n'], $lang);
    $l = mel_gc_wrap($s['codes'], 26, MEL_INF_CW, $max);
    if (count(mel_gc_wrap($s['codes'], 26, MEL_INF_CW, $max + 1)) <= $max)
        return array(array_chunk($l, 1), 0);
    // Cut: the languages before the last comma shown are whole.
    return array(array_chunk($l, 1), $s['n'] - substr_count(implode(' ', $l), ','));
}

// Tracks $items in lines wrapped by words (mel_gc_wrap): the first of the
// column width, the rest indented by MEL_INF_TIND; more than $max lines: the
// last ends with "…". Past $max lines in all, the rest is left out.
/**
 * @param list<string> $items
 * @param int $max
 * @return list<list<string>>
 */
function mel_info_wrap_tracks($items, $max)
{
    $max = max(1, $max);
    $out = array();
    $n = 0;
    foreach ($items as $s)
    {
        if ($n > $max)
            break;
        $s = mel_info_clean($s);
        $l = mel_gc_wrap($s, 26, MEL_INF_CW, min(2, $max));
        if (count($l) > 1)
            $l = array_merge(array($l[0]), mel_gc_wrap(ltrim(mb_substr($s, mb_strlen($l[0], 'UTF-8'),
                mb_strlen($s, 'UTF-8'), 'UTF-8')), 26, MEL_INF_CW - MEL_INF_TIND, $max - 1));
        $out[] = $l = $l ? $l : array($s);
        $n += count($l);
    }
    return $out;
}

// Lines of tracks.
/**
 * @param list<list<string>> $tracks
 * @return int
 */
function mel_info_nlines($tracks)
{
    return count($tracks, COUNT_RECURSIVE) - count($tracks);
}

// Tracks of a list of $total in $max lines: whole tracks, then a line "… ещё
// N" of the rest; no track whole in the room: the first one cut with "…".
// array(lines, how many tracks are not shown whole).
/**
 * @param list<list<string>> $tracks
 * @param int $max
 * @param int $total
 * @param string $lang
 * @return array{list<list<string>>, int}
 */
function mel_info_more($tracks, $max, $total, $lang)
{
    if (mel_info_nlines($tracks) <= $max && $total <= count($tracks))
        return array($tracks, 0);
    $one = count($tracks) === 1 && $total <= 1;
    $left = $one ? $max : max(1, $max) - 1;
    $more = array();
    foreach ($tracks as $t)
    {
        if (count($t) > $left)
            break;
        $more[] = $t;
        $left -= count($t);
    }
    $hid = $total - count($more);
    if (!$more && $left > 0 && $tracks)
    {
        $t = array_slice($tracks[0], 0, $left - 1);
        $t[] = mel_gc_cut_w($tracks[0][$left - 1] . "\xE2\x80\xA6", 26, MEL_INF_CW - ($left > 1 ? MEL_INF_TIND : 0));
        $more[] = $t;
    }
    if (!$one)
        $more[] = array(str_replace('{n}', $total - count($more), mel_tr($lang, 'info_more')));
    return array($more, $hid);
}

/**
 * @param MelRow $r
 * @param array<mixed> $raw
 * @param array<mixed> $sd
 * @param array<mixed> $pf
 * @param MelGcText $tt
 * @param int $top
 * @return MelInfoCol
 */
function mel_info_col_file($r, $raw, $sd, $pf, $tt, $top)
{
    $lang = $tt['lang'];
    $l = array();
    foreach (array('size', 'rate', 'duration', 'picture', 'edition', 'container') as $k)
        $l[$k] = mel_tr($lang, "info_$k");
    $c = mel_info_col(0, mel_tr($lang, 'info_col_file'), array_merge($l, array($tt['video'], $tt['group'])), $top);
    $size = $r['size'] > 0 ? mel_size_str($r['size'], $lang) : '';
    if ($r['pack'] > 0)
        $size .= ($size !== '' ? ' · ' : '') . $tt['pack'] . ' ' . mel_size_str($r['pack'], $lang);
    mel_info_field($c, $l['size'], $size, 2, false, true);
    if ($r['rate'] > 0)
    {
        $src = array('parsedFile.bitrate' => 'info_rate_file', 'size/duration' => 'info_rate_size',
            'description' => 'info_rate_desc');
        mel_info_field($c, $l['rate'], mel_gc_rate($r['rate'], $r['rate_approx'], $lang) .
            (isset($src[$r['rate_src']]) ? ' · ' . mel_tr($lang, $src[$r['rate_src']]) : ''), 2, false, true);
    }
    $dur = mel_row_duration($r);
    mel_info_field($c, $l['duration'], $dur >= 1 ? mel_info_duration($dur) : '', 1);
    $video = implode(' · ', array_filter(array($r['resolution'], $r['quality'], mel_str($pf, 'encode')), 'strlen'));
    mel_info_field($c, $tt['video'], $video !== '' ? $video : "\xE2\x80\x94", 2, false, true);
    $vis = array();
    foreach (mel_arr($pf, 'visualTags') as $t)
    {
        if (is_string($t) && trim($t) !== '')
            $vis[] = trim(mel_utf8($t));
    }
    if ($r['badges']['ai'] && !in_array('AI', $vis, true))
        $vis[] = 'AI';
    mel_info_field($c, $l['picture'], mel_cut(implode(' · ', $vis), MEL_TEXT_MAX), 2, false, true);
    $ed = array();
    foreach (mel_arr($pf, 'editions') as $t)
    {
        if (is_string($t) && trim($t) !== '')
            $ed[] = trim(mel_utf8($t));
    }
    mel_info_field($c, $l['edition'], mel_cut(implode(' · ', $ed), MEL_TEXT_MAX), 2, false, true);
    $cont = strtoupper(mel_str($pf, 'container'));
    if (isset($pf['hasChapters']) && is_bool($pf['hasChapters']))
        $cont .= ($cont !== '' ? ' · ' : '') .
            mel_tr($lang, $pf['hasChapters'] ? 'info_chapters' : 'info_no_chapters');
    mel_info_field($c, $l['container'], $cont, 1, false, true);
    mel_info_field($c, $tt['group'], $r['group'], 2);
    // As mel_row takes them: mel_row_voices drops 'names' of the row once parsed.
    $file = mel_str($sd, 'filename') !== '' ? mel_str($sd, 'filename') :
        mel_str(mel_arr($raw, 'behaviorHints'), 'filename');
    // Whole, the room of the column only.
    mel_info_field($c, mel_tr($lang, 'info_file'), $file, mel_info_room($c, true), true);
    mel_info_field($c, mel_tr($lang, 'info_folder'), mel_str($sd, 'folderName'), mel_info_room($c, true), true);
    return $c;
}

// What the middle column shows, for both of its layouts (mel_info_col_tracks):
// the column with its head, the voices (their first group alone on its
// lines: "Дубляж: …"; $vs: mel_row_voices_struct of $r), the label of the
// audio, its tracks wrapped in the room of the column and their count.
/**
 * @param MelRow $r
 * @param array<mixed> $pf
 * @param MelGcText $tt
 * @param int $top
 * @param MelVoicesStruct $vs
 * @return MelInfoMid
 */
function mel_info_mid($r, $pf, $tt, $top, $vs)
{
    $lang = $tt['lang'];
    $c = mel_info_col(1, mel_tr($lang, 'info_col_tracks'), array(), $top);
    $v = mel_info_clean(mel_voices_struct_text($vs, $lang, false, 0));
    list($audio, $na) = mel_info_audio($r, $pf);
    $alabel = $tt['audio'] . ($audio ? " ($na)" : '');
    if (!$audio)
    {
        $audio = mel_gc_wrap_items($r['audio_full'] !== '' ? mel_info_clean($r['audio_full']) : "\xE2\x80\x94", 26,
            MEL_INF_CW, 2);
        $na = count($audio);
    }
    return array('col' => $c, 'voices' => $v, 'alone' => mel_voices_dub_first($vs),
        'alabel' => $alabel, 'audio' => mel_info_wrap_tracks($audio, mel_info_room($c, true)), 'n_audio' => $na);
}

// The middle column of $m (mel_info_mid): voices, then the audio and the
// subtitles $s (null: none here), a track wrapped by words: what does not fit
// ends with "… ещё N", the voices leave the audio a line and the subtitles
// MEL_INF_SUBS_MIN lines; 'hid': how many tracks are not shown whole.
/**
 * @param MelInfoMid $m
 * @param MelGcText $tt
 * @param MelInfoSubs|null $s
 * @return MelInfoCol
 */
function mel_info_col_tracks($m, $tt, $s)
{
    $lang = $tt['lang'];
    $c = $m['col'];
    $v = $m['voices'];
    $at = $m['audio'];
    $sl = $s ? $s['lines'] : 0;
    // Voices: the room but a line of the audio and the least of the subtitles.
    $max = mel_info_left($c, $sl ? 3 : 2) - 1 - ($sl ? min(MEL_INF_SUBS_MIN, $sl) : 0);
    if ($v !== '' && $max > 0)
        mel_info_put($c, $tt['voices'], mel_gc_wrap_voices($v, 26, MEL_INF_CW, $max, $m['alone']), true);

    // Value lines left for both.
    $room = mel_info_left($c, $sl ? 2 : 1);
    $sn = min($sl, max(MEL_INF_SUBS_MIN, $room - mel_info_nlines($at)));
    list($at, $c['hid']) = mel_info_more($at, max(1, $room - $sn), $m['n_audio'], $lang);
    mel_info_put_tracks($c, $m['alabel'], $at);
    // The subtitles: the rest (with what whole audio tracks left).
    if ($s)
    {
        list($st, $hs) = mel_info_subs_fit($s, min($sl, $room - mel_info_nlines($at)), $lang);
        mel_info_put_tracks($c, $s['label'], $st);
        $c['hid'] += $hs;
    }
    return $c;
}

// Add-ons of one release (row key 'addons'): "JacRed · MediaFusion (кэш)",
// "(кэш)" after those that said themselves it is cached on the row's service
// (no ⚡: not in the Dune font).
/**
 * @param list<MelAddon> $addons
 * @param string $lang
 * @return string
 */
function mel_info_addons($addons, $lang)
{
    $v = array();
    foreach ($addons as $a)
        $v[] = mel_clean($a['addon']) . ($a['cached'] ? ' ' . mel_tr($lang, 'info_src_cached') : '');
    return implode(' · ', $v);
}

// The right column; the release page (release_url) in $pmax lines at most, fewer
// when the hash would not fit whole then.
/**
 * @param MelRow $r
 * @param array<mixed> $sd
 * @param array<mixed> $pf
 * @param MelGcText $tt
 * @param int $top
 * @param int $pmax
 * @return MelInfoCol
 */
function mel_info_col_source($r, $sd, $pf, $tt, $top, $pmax = 2)
{
    $lang = $tt['lang'];
    $l = array();
    foreach (array('seeders', 'age', 'expr', 'eps_file', 'eps_pack', 'type', 'addons', 'release_url') as $k)
        $l[$k] = mel_tr($lang, "info_$k");
    $c = mel_info_col(2, mel_tr($lang, 'info_col_source'),
        array_merge($l, array($tt['trackers'], $tt['addon'], $tt['source'])), $top);
    // The cache status, as the card but without the seeders (own field below).
    $status = mel_gc_status($r, $tt);
    mel_info_put($c, '', mel_gc_status_lines($status, 26, MEL_INF_CW, 2), true, mel_gc_status_color($r['cached']));
    if ($r['trackers'])
        mel_info_field($c, $tt['trackers'], mel_cut(implode(', ', $r['trackers']), MEL_TEXT_MAX), 2);
    // Without the scheme: the QR page has it whole.
    $rel = $pmax > 0 ? preg_replace('~^https?://~i', '', $r['release_url']) : '';
    mel_info_field($c, $l['release_url'], $rel, $pmax);
    // A line an add-on at most (no more than MEL_ADDONS_MAX), so none is cut;
    // the room of the column still limits it.
    if (!empty($r['addons']))
        mel_info_field($c, $l['addons'], mel_info_addons($r['addons'], $lang), count($r['addons']), false, true);
    else
        mel_info_field($c, $tt['addon'], $r['addon'], 2);
    if (!is_null($r['seeders']))
        mel_info_field($c, $l['seeders'], strval($r['seeders']), 1);
    // streamData.age: hours (AIOStreams packages/core/src/debrid/utils.ts
    // "age in hours", formatters/base.ts formatHours: under a day in hours, else days).
    $age = mel_num(mel_str($sd, 'age'));
    if ($age > 0)
        mel_info_field($c, $l['age'], $age < 24 ? intval($age) . ' ' . mel_tr($lang, 'info_hours') :
            intval($age / 24) . ' ' . mel_tr($lang, 'info_days'), 1);
    mel_info_field($c, $l['expr'], $r['expr'], 2);
    mel_info_field($c, $tt['source'], $r['network'], 1);
    $eps = mel_info_eps(is_array($r['pf_seasons']) ? $r['pf_seasons'] : array(), $r['pf_episodes']);
    $et = mel_str($pf, 'episodeTitle');
    mel_info_field($c, $l['eps_file'], implode(' · ', array_filter(array($eps, $et), 'strlen')), 2);
    mel_info_field($c, $l['eps_pack'], mel_info_eps(mel_ints(mel_arr($pf, 'folderSeasons')),
        mel_ints(mel_arr($pf, 'folderEpisodes'))), 2);
    $tor = mel_arr($sd, 'torrent');
    $type = array(mel_str($sd, 'type'));
    foreach (array('private', 'freeleech') as $k)
        $type[] = isset($tor[$k]) && $tor[$k] === true ? $k : '';
    mel_info_field($c, $l['type'], implode(' · ', array_filter($type, 'strlen')), 1);
    if ($rel !== '' && mel_info_room($c, true) < count(mel_gc_wrap($r['hash'], 26, MEL_INF_CW, 2)))
        return mel_info_col_source($r, $sd, $pf, $tt, $top, $pmax - 1);
    mel_info_field($c, mel_tr($lang, 'info_hash'), $r['hash'], 2, true);
    return $c;
}

// Children of page 1: the name (4 lines at most) and the three columns under it.
// Subtitles that the middle column does not show whole go to the bottom of
// the right one when the tracks left out of both are then fewer and a
// subtitle (a language) at least is whole there.
/**
 * @param MelState $st
 * @param int $n
 * @param MelVoicesStruct $vs
 * @return list<MelGcComp>
 */
function mel_info_passport($st, $n, $vs)
{
    $r = $st['rows'][$n];
    $tt = mel_gc_text(mel_gc_lang($st));
    $raw = $r['raw'];
    $sd = mel_arr($raw, 'streamData');
    $pf = mel_arr($sd, 'parsedFile');
    $d = array();
    $name = mel_gc_wrap(mel_info_clean($r['label']), 26, MEL_INF_W, 4);
    foreach ($name as $i => $line)
        $d[] = mel_info_cut(mel_gc_at(MEL_INF_W, MEL_INF_LH, 0, $i * MEL_INF_LH), $line, 26, MEL_GC_TEXT);
    // A name of 3-4 lines moves the columns down.
    $top = MEL_INF_COLS_Y + max(0, count($name) - 2) * MEL_INF_LH;
    $s = mel_info_subs($r, $pf, $tt);
    $m = mel_info_mid($r, $pf, $tt, $top, $vs);
    $mid = mel_info_col_tracks($m, $tt, $s);
    $src = mel_info_col_source($r, $sd, $pf, $tt, $top);
    if ($s && $mid['hid'] > 0)
    {
        $mb = mel_info_col_tracks($m, $tt, null);
        list($sub, $hs) = mel_info_subs_fit($s, mel_info_left($src, 1), $tt['lang']);
        if ($hs < $s['n'] && $mb['hid'] + $hs < $mid['hid'])
        {
            $mid = $mb;
            mel_info_put_tracks($src, $s['label'], $sub);
        }
    }
    $file = mel_info_col_file($r, $raw, $sd, $pf, $tt, $top);
    return array_merge($d, $file['d'], $mid['d'], $src['d']);
}

// --- The QR page: the page of the release on its tracker.

// Children of the QR page of address $url of a row of list $rid: the QR at
// the left in the middle (its quiet zone white: light under the code on the
// dark screen), at its right the head, the address whole and how to use it;
// null without the picture. The picture: tmp_dir/iqr_<rid>_<md5>.png, mask 0,
// not the best of 8 (the screen waits for it: v10 ~0.5 s more); those of the
// other rows of the list stay (back to a row: not made again), of other lists go.
/**
 * @param string $url
 * @param string $rid
 * @param string $lang
 * @return list<MelGcComp>|null
 */
function mel_info_qr_page($url, $rid, $lang)
{
    $qr = mel_qr_file('iqr_', $rid . '_', $url, 1, MEL_INF_QR_SIDE, 0, 'info');
    if (!$qr)
        return null;
    list($png, $side) = $qr;
    $y = intval((MEL_INF_H - $side) / 2);
    // file:// and the absolute path, as the vendor's cached posters
    // (shell_ext movies/movie_helper.php complete_mc_url).
    $d = array(mel_gc_image(mel_gc_at($side, $side, 0, $y), "file://$png"));
    $x = $side + MEL_INF_QR_GAP;
    $w = MEL_INF_W - $x;
    $head = mel_tr($lang, 'info_qr_title');
    $d[] = mel_info_cut(mel_gc_at($w, 50, $x, $y), mel_gc_cut_w($head, 36, $w), 36, MEL_GC_TEXT);
    $y += 66;
    foreach (mel_gc_wrap($url, 30, $w, MEL_INF_QR_LINES) as $l)
    {
        $d[] = mel_info_cut(mel_gc_at($w, 40, $x, $y), $l, 30, MEL_GC_TEXT);
        $y += 40;
    }
    $hint = mel_tr($lang, 'info_qr_hint');
    $d[] = mel_info_cut(mel_gc_at($w, 40, $x, $y + 24), mel_gc_cut_w($hint, 30, $w), 30, MEL_GC_TEXT2);
    return $d;
}

// --- YAML pages.

// A JSON list (keys 0..n-1) by its first and last keys: a list of 200 000
// is not walked; an object keyed so is shown as a list.
/**
 * @param array<mixed> $v
 * @return bool
 */
function mel_info_list($v)
{
    if (!$v)
        return true;
    reset($v);
    $first = key($v);
    end($v);
    return $first === 0 && key($v) === count($v) - 1;
}

// One line of text of a scalar: control characters as spaces.
/**
 * @param string $s
 * @return string
 */
function mel_info_line($s)
{
    return preg_replace('/[\\x00-\\x1F]+/', ' ', $s);
}

// $v in flow style: "a", "[a, b]", "{k: v}"; about $room characters at most
// (it goes down), the rest "…", as past MEL_INFO_DEPTH.
/**
 * @param mixed $v
 * @param int $room
 * @param int $depth
 * @return string
 */
function mel_info_flow($v, &$room, $depth)
{
    if ($v === '')
        return '""';
    if (!is_array($v))
    {
        $s = mel_info_line(mb_substr(mel_info_scalar($v), 0, max(0, $room) + 1, 'UTF-8'));
        $room -= mb_strlen($s, 'UTF-8');
        return $s;
    }
    $list = mel_info_list($v);
    $parts = array();
    foreach ($v as $k => $x)
    {
        if ($room <= 0 || $depth >= MEL_INFO_DEPTH)
        {
            $parts[] = "\xE2\x80\xA6";
            break;
        }
        $k = $list ? '' : mel_info_line(mel_utf8($k)) . ': ';
        $room -= mb_strlen($k, 'UTF-8') + 2;
        $parts[] = $k . mel_info_flow($x, $room, $depth + 1);
    }
    return ($list ? '[' : '{') . implode(', ', $parts) . ($list ? ']' : '}');
}

// Line $text at indent $lvl to $out (array(level, text, colour)), wrapped to
// the column, the rest one level deeper. false once $max lines are reached.
/**
 * @param list<array{int, string, string}> $out
 * @param int $lvl
 * @param string $text
 * @param int $max
 * @param string $color
 * @return bool
 */
function mel_info_yline(&$out, $lvl, $text, $max, $color = MEL_GC_TEXT2)
{
    $left = $max - count($out);
    if ($left <= 0)
        return false;
    // More could not be shown anyway.
    $text = mb_substr($text, 0, ($left + 1) * 100, 'UTF-8');
    // $left + 1 lines: the last one (and its "…") only tells there is more.
    foreach (mel_gc_wrap($text, 24, MEL_INF_CW - ($lvl + 1) * MEL_INF_IND, $left + 1) as $i => $l)
    {
        if (count($out) >= $max)
            return false;
        $out[] = array($i ? $lvl + 1 : $lvl, $l, $color);
    }
    return true;
}

// Decoded JSON $v as YAML lines under $head ("key:", '' at the top): an
// object field by field a level deeper; a list of scalars in flow style; a
// list with objects or lists item by item "- {k: v}"; a string of several
// lines line by line a level deeper. false once $max lines are reached.
/**
 * @param list<array{int, string, string}> $out
 * @param int $lvl
 * @param string $head
 * @param mixed $v
 * @param int $max
 * @param int $depth
 * @return bool
 */
function mel_info_yaml(&$out, $lvl, $head, $v, $max, $depth = 0)
{
    $nested = false;
    if (is_array($v) && $v && $depth < MEL_INFO_DEPTH)
    {
        foreach ($v as $x)
        {
            if (is_array($x) && $x)
            {
                $nested = true;
                break;
            }
        }
    }
    if ($nested || (is_array($v) && $v && $depth < MEL_INFO_DEPTH && !mel_info_list($v)))
    {
        if ($head !== '' && !mel_info_yline($out, $lvl, $head, $max))
            return false;
        $sub = $head !== '' ? $lvl + 1 : $lvl;
        $list = mel_info_list($v);
        foreach ($v as $k => $x)
        {
            if ($list)
            {
                $room = ($max - count($out) + 1) * 100;
                $ok = mel_info_yline($out, $sub, '- ' . mel_info_flow($x, $room, $depth + 1), $max);
            }
            else
                $ok = mel_info_yaml($out, $sub, mel_info_line(mel_utf8($k)) . ':', $x, $max, $depth + 1);
            if (!$ok)
                return false;
        }
        return true;
    }
    if (is_string($v) && preg_match('/[\\r\\n]/', trim($v)))
    {
        if (!mel_info_yline($out, $lvl, $head !== '' ? "$head |" : '|', $max))
            return false;
        foreach (preg_split('/\\r\\n|\\r|\\n/', mel_info_scalar($v)) as $l)
        {
            $l = trim(mel_info_line($l));
            if ($l !== '' && !mel_info_yline($out, $lvl + 1, $l, $max))
                return false;
        }
        return true;
    }
    $room = ($max - count($out) + 1) * 100;
    $text = is_array($v) && $v && $depth >= MEL_INFO_DEPTH ? "\xE2\x80\xA6" : mel_info_flow($v, $room, $depth);
    return mel_info_yline($out, $lvl, $head !== '' ? "$head $text" : $text, $max);
}

// YAML lines of row $n: array(AIOStreams lines, Melange lines), each block
// with its head; together at most MEL_INFO_LINES, the last of a cut block
// says so. $vs: mel_row_voices_struct of the row.
/**
 * @param MelState $st
 * @param int $n
 * @param MelVoicesStruct $vs
 * @return array{list<array{int, string, string}>, list<array{int, string, string}>}
 */
function mel_info_blocks($st, $n, $vs)
{
    $r = $st['rows'][$n];
    $lang = $st['lang'];
    $cut = array(0, mel_tr($lang, 'info_cut'), MEL_GC_DIM);
    $mel = array(array(0, mel_tr($lang, 'info_melange'), MEL_GC_TEXT));
    $max = intval(MEL_INFO_LINES / 2);
    $ok = true;
    foreach ($r as $k => $v)
    {
        if ($k === 'raw')
            continue;
        // The copies of a file cached on several services: their streams
        // whole are too long; that of the copy on top is the AIOStreams block.
        if ($k === 'copies')
        {
            foreach (array_keys($v) as $i)
                unset($v[$i]['raw']);
        }
        if (($k === 'size' || $k === 'pack') && $v > 0)
            $v = mel_size_str($v, $lang) . ' (' . mel_info_scalar($v) . ')';
        else if ($k === 'rate' && $v > 0)
            $v = mel_gc_rate($v, $r['rate_approx'], $lang) . ' (' . mel_info_scalar($v) . ')';
        $ok = mel_info_yaml($mel, 0, "$k:", $v, $max);
        // As the screens show the voices.
        if ($ok && $k === 'voices')
            $ok = mel_info_yaml($mel, 0, 'voices_text:', mel_voices_struct_text($vs, $lang), $max);
        if (!$ok)
        {
            $mel[$max - 1] = $cut;
            break;
        }
    }
    $max = MEL_INFO_LINES - count($mel);
    $aio = array(array(0, 'AIOStreams', MEL_GC_TEXT));
    if (!mel_info_yaml($aio, 0, '', $r['raw'], $max))
        $aio[$max - 1] = $cut;
    return array($aio, $mel);
}

// Children of a YAML page: up to 3 columns of MEL_INF_YROWS lines.
/**
 * @param list<array{int, string, string}> $lines
 * @return list<MelGcComp>
 */
function mel_info_ypage($lines)
{
    $d = array();
    foreach ($lines as $i => $l)
    {
        $x = intval($i / MEL_INF_YROWS) * (MEL_INF_CW + MEL_INF_CGAP) + $l[0] * MEL_INF_IND;
        $d[] = mel_info_cut(mel_gc_at(MEL_INF_CW - $l[0] * MEL_INF_IND, MEL_INF_YLH, $x,
            $i % MEL_INF_YROWS * MEL_INF_YLH), $l[1], 24, $l[2]);
    }
    return $d;
}

// Children of every page: the passport, the QR page, then the AIOStreams
// pages, then the Melange ones. The voices of the row are made once for both.
/**
 * @param MelState $st
 * @param int $n
 * @return list<list<MelGcComp>>
 */
function mel_info_pages($st, $n)
{
    $vs = mel_row_voices_struct($st['rows'][$n]);
    $pages = array(mel_info_passport($st, $n, $vs));
    $url = $st['rows'][$n]['release_url'];
    $qr = $url !== '' && strlen($url) <= MEL_INF_QR_MAX ? mel_info_qr_page($url, $st['rid'], mel_gc_lang($st)) : null;
    if ($qr)
        $pages[] = $qr;
    foreach (mel_info_blocks($st, $n, $vs) as $block)
    {
        foreach (array_chunk($block, 3 * MEL_INF_YROWS) as $lines)
            $pages[] = mel_info_ypage($lines);
    }
    return $pages;
}

// --- Screen.

/**
 * @param int $page
 * @param int $n
 * @return MelGcGeom
 */
function mel_info_pages_geom($page, $n)
{
    return mel_gc_at(MEL_INF_W, $n * MEL_INF_H, 0, -$page * MEL_INF_H);
}

// Children of 'icount' (under the pages, at the right): "1 / 4".
/**
 * @param int $page
 * @param int $n
 * @return list<MelGcComp>
 */
function mel_info_counter($page, $n)
{
    return array(mel_gc_cut(null, mel_gc_at(300, 50, 0, 0), ($page + 1) . " / $n", 36, MEL_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// sel_state -> page, 0 when it does not fit $n pages.
/**
 * @param mixed $s
 * @param int $n
 * @return int
 */
function mel_info_page($s, $n)
{
    $j = is_string($s) && $s !== '' ? json_decode($s) : null;
    return is_object($j) && isset($j->page) && is_int($j->page) && $j->page >= 0 && $j->page < $n ? $j->page : 0;
}

/**
 * @param MelState $st
 * @param int $n
 * @param list<list<MelGcComp>> $pages
 * @param int $page
 * @return array<string, mixed>
 */
function mel_info_window($st, $n, $pages, $page)
{
    $mv = $st['movie'];
    $tt = mel_gc_text(mel_gc_lang($st));
    $d = array(mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), '#C0000000'));
    // The title as the list's; the episode and the row at the right.
    list($title) = mel_gc_title($st);
    $count = ($st['e'] > 0 ? mel_ep_code($mv, $st['s'], $st['e']) . ' · ' : '') .
        mel_tr($tt['lang'], 'info_stream') . ' ' . ($n + 1) . ' / ' . count($st['rows']);
    $cw = min(600, mel_gc_w($count, 36));
    $d[] = mel_gc_cut(null, mel_gc_at(MEL_INF_W - $cw - 30, 66, MEL_INF_X, 40), $title, 48, MEL_GC_TEXT);
    $d[] = mel_gc_cut(null, mel_gc_at($cw, 50, MEL_INF_X + MEL_INF_W - $cw, 52), $count, 36, MEL_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_RIGHT));
    // Viewport: clipping panel 'iview'; the inner panel 'ipages' moves a page.
    $in = array();
    foreach ($pages as $i => $p)
        $in[] = mel_gc_panel("ip$i", mel_gc_at(MEL_INF_W, MEL_INF_H, 0, $i * MEL_INF_H), $p);
    $d[] = mel_gc_panel('iview', mel_gc_at(MEL_INF_W, MEL_INF_H, MEL_INF_X, MEL_INF_Y),
        array(mel_gc_panel('ipages', mel_info_pages_geom($page, count($pages)), $in, 0)), 0);
    // The vendor hint of P+/P- and "Страницы", the counter at the right.
    $d[] = mel_gc_image(mel_gc_at(114, 50, MEL_INF_X, MEL_INF_HINT_Y),
        'plugin_file://%shell_ext%/icons/p_plus_p_minus.png');
    $hint = mel_tr($tt['lang'], 'info_pages');
    $d[] = mel_gc_cut(null, mel_gc_at(mel_gc_w($hint, 36), 50, MEL_INF_X + 126, MEL_INF_HINT_Y), $hint, 36,
        MEL_GC_TEXT2);
    $d[] = mel_gc_panel('icount', mel_gc_at(300, 50, MEL_INF_X + MEL_INF_W - 300, MEL_INF_HINT_Y),
        mel_info_counter($page, count($pages)));
    $fanart = mel_img($mv['fanart']);
    // Same key set as vendor GCompsFactory::get_window_def().
    return array(
        GCompWindowDef::background_color => 'rgb(0,0,0)',
        GCompWindowDef::background_url => $fanart !== '' ? $fanart : null,
        GCompWindowDef::async_loading_background => true,
        GCompWindowDef::comp_defs => $d,
        GCompWindowDef::ui_state => null,
        GCompWindowDef::not_loaded_background_url => null,
        GCompWindowDef::playback_bg_alpha => -1,
        GCompWindowDef::background_fit_def => array(
            ImageFitDef::base_halign_ratio => 0.62,
            ImageFitDef::base_valign_ratio => 0.5),
        GCompWindowDef::small_state_text => null,
        GCompWindowDef::opaque_background => false);
}

// get_folder_view of "info:<rid>:<row>"; a gone list: the "outdated" screen.
/**
 * @param string $media_url
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_info_folder_view($media_url, $sel_state = null)
{
    $st = Mel::$state;
    if (!$st || !preg_match('/^info:([0-9a-f]+):([0-9]+)$/D', $media_url, $m) || $m[1] !== $st['rid'] ||
        !isset($st['rows'][intval($m[2])]))
        return mel_lines_view(array(mel_expired_item()));
    $n = intval($m[2]);
    $pages = mel_info_pages($st, $n);
    $page = mel_info_page($sel_state, count($pages));
    $np = strval(count($pages));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_info_window($st, $n, $pages, $page),
            PluginGCompsFolderView::sel_state => json_encode(array('page' => $page)),
            PluginGCompsFolderView::actions => array(
                GUI_EVENT_KEY_P_PLUS => mel_input('info_page', array('d' => 'prev', 'pages' => $np)),
                GUI_EVENT_KEY_P_MINUS => mel_input('info_page', array('d' => 'next', 'pages' => $np))) + mel_egg_acts(),
            PluginGCompsFolderView::timer => null));
}

// P+/P- on the details screen -> change_gcomps of the page; null at an edge.
// Only the drawn screen moves: no list needed (its count of pages comes
// with the key).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_info_input($in)
{
    $n = isset($in->pages) && is_string($in->pages) && preg_match('/^[1-9][0-9]{0,2}$/D', $in->pages) ?
        intval($in->pages) : 0;
    $d = isset($in->d) && is_string($in->d) ? $in->d : '';
    if ($n < 2 || ($d !== 'prev' && $d !== 'next'))
        return null;
    $page = mel_info_page(isset($in->parent_sel_state) ? $in->parent_sel_state : null, $n);
    $np = $page + ($d === 'next' ? 1 : -1);
    if ($np < 0 || $np >= $n)
        return null;
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => array(
                mel_gc_change('ipages', mel_info_pages_geom($np, $n), GCOMP_TRANSITION_DEFAULT),
                mel_gc_change('icount', null, GCOMP_TRANSITION_NONE, mel_info_counter($np, $n))),
            ChangeGCompsActionData::num_steps => MEL_GC_STEPS,
            ChangeGCompsActionData::sel_state => json_encode(array('page' => $np))));
}

// The one row of a screen whose list is gone (replaced, php_server restarted):
// in both languages, the language of the list is gone too.
/**
 * @return array<string, mixed>
 */
function mel_expired_item()
{
    return array(
        PluginRegularFolderItem::media_url => 'expired',
        PluginRegularFolderItem::caption => mel_tr('ru', 'list_outdated') . ' / ' . mel_tr('en', 'list_outdated'),
        PluginRegularFolderItem::view_item_params => array());
}

// A plain list of text lines, the view of the 0.1.1 list: the "outdated"
// screen. OK on a line does nothing: its action asks for a control no one
// handles.
/**
 * @param list<array<string, mixed>> $items
 * @return array<string, mixed>
 */
function mel_lines_view($items)
{
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_REGULAR,
        PluginFolderView::data => array(
            PluginRegularFolderView::view_params => array(
                ViewParams::num_cols => 1,
                ViewParams::num_rows => 12,
                ViewParams::paint_details => false,
                ViewParams::paint_scrollbar => true,
                ViewParams::scroll_animation_enabled => true),
            PluginRegularFolderView::base_view_item_params => array(
                ViewItemParams::item_padding_top => 0,
                ViewItemParams::item_padding_bottom => 0,
                ViewItemParams::item_layout => HALIGN_LEFT,
                ViewItemParams::item_caption_width => 1650,
                ViewItemParams::item_caption_font_size => FONT_SIZE_SMALL),
            PluginRegularFolderView::not_loaded_view_item_params => array(),
            PluginRegularFolderView::async_icon_loading => false,
            PluginRegularFolderView::actions => array(
                GUI_EVENT_KEY_ENTER => array(
                    GuiAction::handler_string_id => PLUGIN_HANDLE_USER_INPUT_ACTION_ID,
                    GuiAction::data => null,
                    GuiAction::params => array('handler_id' => 'aio', 'control_id' => 'line_ok'))),
            PluginRegularFolderView::initial_range => array(
                PluginRegularFolderRange::total => count($items),
                PluginRegularFolderRange::more_items_available => false,
                PluginRegularFolderRange::from_ndx => 0,
                PluginRegularFolderRange::count => count($items),
                PluginRegularFolderRange::items => $items)));
}
