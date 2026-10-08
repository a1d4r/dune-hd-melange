<?php
// Details screen of one release (INFO on a list row): view_gcomps 1920x1080
// on the darkened fanart, as the list screen. Page 1, the passport: the name,
// then three columns - file and video, tracks, where from. The next pages,
// the raw data as compact YAML in three columns: the stream as AIOStreams
// sent it, then the row as Melange computed it (without the stream). P+/P-
// slide the pages with change_gcomps (as the list scrolls); the page travels
// in sel_state {"page":N}. URLs are cut to the host: they carry tokens.

// YAML lines at most, both blocks (a huge reply stays bounded); the Melange
// block takes at most half.
define('AIO_INFO_LINES', 500);
define('AIO_INFO_DEPTH', 12);
define('AIO_INF_X', 75);
define('AIO_INF_W', 1770);      // to x 1845 = 1920 - 75
define('AIO_INF_CW', 563);      // three columns, 40 px apart
define('AIO_INF_CGAP', 40);
define('AIO_INF_Y', 116);       // the pages: under the title, down to y 962
define('AIO_INF_H', 846);
define('AIO_INF_COLS_Y', 84);   // page 1: the columns at y 200, under the name
define('AIO_INF_HEAD_H', 40);   // page 1: column heads, 28 px
define('AIO_INF_LH', 32);       // page 1: labels 24 px, values 26 px
define('AIO_INF_GAP', 6);       // page 1: between fields
define('AIO_INF_SUBS_MIN', 3);  // page 1: subtitle lines kept when tracks do not fit
define('AIO_INF_YLH', 30);      // YAML pages: 24 px lines
define('AIO_INF_YROWS', 28);    // YAML pages: lines of a column
define('AIO_INF_IND', 20);      // YAML pages: indent of a level

// open_folder of the details screen of row $n of list $st.
function aio_info_open($st, $n)
{
    aio_log("info: row $n of " . count($st['rows']));
    return array(
        GuiAction::handler_string_id => PLUGIN_OPEN_FOLDER_ACTION_ID,
        GuiAction::plugin_name => AIO_PLUGIN,
        GuiAction::data => array(
            PluginOpenFolderActionData::media_url => "info:{$st['rid']}:$n",
            PluginOpenFolderActionData::caption =>
                aio_tr($st['lang'], 'info_title') . ' ' . ($n + 1) . ' / ' . count($st['rows'])));
}

// "https://host:port/…" of a URL (no path, query or user: the user part
// ends at the last "@" of the authority); other text as is.
function aio_info_url($s)
{
    if (!preg_match('~^\s*(https?://)([^/?#\s]*)~i', $s, $m))
        return $s;
    $at = strrpos($m[2], '@');
    return $m[1] . ($at === false ? $m[2] : substr($m[2], $at + 1)) . "/\xE2\x80\xA6";
}

function aio_info_scalar($v)
{
    if (is_null($v))
        return 'null';
    if (is_bool($v))
        return $v ? 'true' : 'false';
    // Sizes are floats: PHP on Dune is 32-bit.
    if (is_float($v) && $v == floor($v) && abs($v) < 1e15)
        return number_format($v, 0, '.', '');
    // As sent, unmasked: a local plugin may show its secrets on its screen.
    return aio_info_url(aio_utf8($v));
}

// One line of text as sent: line breaks and tabs as a space; "|" kept
// (aio_clean makes it "/" for the list screen).
function aio_info_clean($s)
{
    return trim(preg_replace('/[\\r\\n\\t]+/', ' ', strval($s)));
}

// aio_gc_cut of the pages of this screen: the text by aio_info_clean.
function aio_info_cut($geom, $text, $size, $color)
{
    $l = aio_gc_cut(null, $geom, '', $size, $color);
    $l[GComponentDef::specific_def][GCompTtfLabelDef::text] = aio_info_clean($text);
    return $l;
}

// --- Page 1: the passport.

// "1–7, 9, 11" of whole numbers; a run of three or more as a range.
function aio_info_ranges($a)
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

// "S05E03" of one episode, else "S1–5 · E1–62"; '' without numbers.
function aio_info_eps($seasons, $episodes)
{
    if (count($seasons) === 1 && count($episodes) === 1)
        return sprintf('S%02dE%02d', $seasons[0], $episodes[0]);
    $p = array();
    if ($seasons)
        $p[] = 'S' . aio_info_ranges($seasons);
    if ($episodes)
        $p[] = 'E' . aio_info_ranges($episodes);
    return implode(' · ', $p);
}

// "3:00:23" of seconds.
function aio_info_duration($s)
{
    $s = intval(round($s));
    return $s >= 3600 ? sprintf('%d:%02d:%02d', $s / 3600, $s / 60 % 60, $s % 60) :
        sprintf('%d:%02d', $s / 60, $s % 60);
}

// A column of page 1: its x, the y of the next field, its bottom, the x of
// values after the widest of its $labels, components.
function aio_info_col($i, $head, $labels)
{
    $x = $i * (AIO_INF_CW + AIO_INF_CGAP);
    $lw = 0;
    foreach ($labels as $l)
        $lw = max($lw, aio_gc_w($l, 24));
    return array('x' => $x, 'y' => AIO_INF_COLS_Y + AIO_INF_HEAD_H, 'end' => AIO_INF_H, 'lw' => min(240, $lw + 14),
        'd' => array(aio_info_cut(aio_gc_at(AIO_INF_CW, AIO_INF_HEAD_H, $x, AIO_INF_COLS_Y),
            aio_gc_cut_w($head, 28, AIO_INF_CW), 28, AIO_GC_TEXT)));
}

// Value lines a field of column $c has room for: $block - its label takes a
// line of its own.
function aio_info_room($c, $block)
{
    return intval(($c['end'] - $c['y']) / AIO_INF_LH) - ($block ? 1 : 0);
}

// A field of column $c: the label (24 px, dimmed) at the left of the first
// value line or ($block) on a line above them; the value lines (26 px) cut to
// the width with "…". No lines: no field.
function aio_info_put(&$c, $label, $lines, $block, $color = AIO_GC_TEXT2)
{
    if (!$lines)
        return;
    $vx = $block ? 0 : $c['lw'];
    if ($label !== '')
    {
        $lw = $block ? AIO_INF_CW : $c['lw'] - 10;
        $c['d'][] = aio_info_cut(aio_gc_at($lw, AIO_INF_LH, $c['x'], $c['y']), aio_gc_cut_w($label, 24, $lw), 24,
            AIO_GC_DIM);
        $c['y'] += $block ? AIO_INF_LH : 0;
    }
    foreach ($lines as $l)
    {
        $c['d'][] = aio_info_cut(aio_gc_at(AIO_INF_CW - $vx, AIO_INF_LH, $c['x'] + $vx, $c['y']),
            aio_gc_cut_w($l, 26, AIO_INF_CW - $vx), 26, $color);
        $c['y'] += AIO_INF_LH;
    }
    $c['y'] += AIO_INF_GAP;
}

// A text field of up to $max lines, wrapped by words or ($items) by " · ".
function aio_info_field(&$c, $label, $v, $max, $block = false, $items = false)
{
    $v = aio_info_clean($v);
    $max = min($max, aio_info_room($c, $block));
    if ($v === '' || $max < 1)
        return;
    $w = AIO_INF_CW - ($block ? 0 : $c['lw']);
    aio_info_put($c, $label, $items ? aio_gc_wrap_items($v, 26, $w, $max) : aio_gc_wrap($v, 26, $w, $max), $block);
}

// A subtitle codec of ffprobe in capitals, the long ones by their usual names ("SRT").
function aio_info_sub_codec($c)
{
    $names = array('subrip' => 'SRT', 'hdmv_pgs_subtitle' => 'PGS', 'dvd_subtitle' => 'VobSub', 'mov_text' => 'TX3G');
    $c = strtolower($c);
    return isset($names[$c]) ? $names[$c] : strtoupper($c);
}

// One line of a track: "• RU · TrueHD 5.1 · Дубляж / HotVoice 41", "•" for
// the default one (★ is in no font of the firmware, ● not in NotoSans Condensed).
function aio_info_track($lang, $codec, $ch, $title, $default)
{
    $p = array_filter(array($lang, trim("$codec $ch"), $title), 'strlen');
    return ($default ? "\xE2\x80\xA2 " : '') . implode(' · ', $p);
}

// Tracks of a list of parsedFile, empty objects left out.
function aio_info_raw_tracks($pf, $k)
{
    $out = array();
    foreach (aio_arr($pf, $k) as $t)
    {
        if (is_array($t) && $t)
            $out[] = $t;
    }
    return $out;
}

// array(lines, count) of the audio tracks of row $r (its own, at most 99, or
// JacRed's), the default marked by parsedFile.audioTracks (row tracks follow
// its order, empty objects too); the count of all of them.
function aio_info_audio($r, $pf)
{
    $raw = array_values(array_filter(aio_arr($pf, 'audioTracks'), 'is_array'));
    $out = array();
    foreach ($r['tracks'] as $i => $t)
    {
        $l = aio_info_track($t['lang'], $t['codec'], $t['ch'], $t['title'],
            isset($raw[$i]['default']) && $raw[$i]['default'] === true);
        if ($l !== '')
            $out[] = $l;
    }
    return array($out, max(count($out), count(aio_info_raw_tracks($pf, 'audioTracks'))));
}

// array(lines, count) of the subtitle tracks of parsedFile.subtitleTracks:
// 99 lines at most, the count of all.
function aio_info_subs($pf)
{
    $all = aio_info_raw_tracks($pf, 'subtitleTracks');
    $out = array();
    foreach (array_slice($all, 0, 99) as $t)
    {
        $l = aio_str($t, 'lang');
        $out[] = aio_info_track($l !== '' ? aio_lang_code($l) : '', aio_info_sub_codec(aio_str($t, 'codec')), '',
            aio_cut(aio_info_clean(aio_str($t, 'title')), 120), isset($t['default']) && $t['default'] === true);
    }
    return array($out, count($all));
}

// Lines of a list of $total items cut to $max: the last says "… ещё N".
function aio_info_more($lines, $max, $total, $lang)
{
    if (count($lines) <= $max && $total <= count($lines))
        return $lines;
    $keep = min(count($lines), max(1, $max) - 1);
    $more = array_slice($lines, 0, $keep);
    $more[] = str_replace('{n}', $total - $keep, aio_tr($lang, 'info_more'));
    return $more;
}

function aio_info_col_file($r, $raw, $sd, $pf, $tt)
{
    $lang = $tt['lang'];
    $l = array();
    foreach (array('size', 'rate', 'duration', 'picture', 'edition', 'container') as $k)
        $l[$k] = aio_tr($lang, "info_$k");
    $c = aio_info_col(0, aio_tr($lang, 'info_col_file'), array_merge($l, array($tt['video'], $tt['release'])));
    $size = $r['size'] > 0 ? aio_size_str($r['size'], $lang) : '';
    if ($r['pack'] > 0)
        $size .= ($size !== '' ? ' · ' : '') . $tt['pack'] . ' ' . aio_size_str($r['pack'], $lang);
    aio_info_field($c, $l['size'], $size, 2, false, true);
    if ($r['rate'] > 0)
    {
        $src = array('parsedFile.bitrate' => 'info_rate_file', 'size/duration' => 'info_rate_size',
            'description' => 'info_rate_desc');
        aio_info_field($c, $l['rate'], aio_gc_rate($r['rate'], $r['rate_approx'], $lang) .
            (isset($src[$r['rate_src']]) ? ' · ' . aio_tr($lang, $src[$r['rate_src']]) : ''), 2, false, true);
    }
    // streamData.duration in ms, parsedFile.duration in s.
    $dur = aio_num(aio_str($sd, 'duration')) / 1000;
    if ($dur < 1)
        $dur = aio_num(aio_str($pf, 'duration'));
    aio_info_field($c, $l['duration'], $dur >= 1 ? aio_info_duration($dur) : '', 1);
    $video = implode(' · ', array_filter(array($r['resolution'], $r['quality'], aio_str($pf, 'encode')), 'strlen'));
    aio_info_field($c, $tt['video'], $video !== '' ? $video : "\xE2\x80\x94", 2, false, true);
    $vis = array();
    foreach (aio_arr($pf, 'visualTags') as $t)
    {
        if (is_string($t) && trim($t) !== '')
            $vis[] = trim(aio_utf8($t));
    }
    if ($r['badges']['ai'] && !in_array('AI', $vis, true))
        $vis[] = 'AI';
    aio_info_field($c, $l['picture'], aio_cut(implode(' · ', $vis), AIO_TEXT_MAX), 2, false, true);
    $ed = array();
    foreach (aio_arr($pf, 'editions') as $t)
    {
        if (is_string($t) && trim($t) !== '')
            $ed[] = trim(aio_utf8($t));
    }
    aio_info_field($c, $l['edition'], aio_cut(implode(' · ', $ed), AIO_TEXT_MAX), 2, false, true);
    $cont = strtoupper(aio_str($pf, 'container'));
    if (isset($pf['hasChapters']) && is_bool($pf['hasChapters']))
        $cont .= ($cont !== '' ? ' · ' : '') .
            aio_tr($lang, $pf['hasChapters'] ? 'info_chapters' : 'info_no_chapters');
    aio_info_field($c, $l['container'], $cont, 1, false, true);
    aio_info_field($c, $tt['release'], $r['group'], 2);
    // As aio_row takes them: aio_row_voices drops 'names' of the row once parsed.
    $file = aio_str($sd, 'filename') !== '' ? aio_str($sd, 'filename') :
        aio_str(aio_arr($raw, 'behaviorHints'), 'filename');
    aio_info_field($c, aio_tr($lang, 'info_file'), aio_cut($file, AIO_TEXT_MAX), 3, true);
    aio_info_field($c, aio_tr($lang, 'info_folder'), aio_cut(aio_str($sd, 'folderName'), AIO_TEXT_MAX), 3, true);
    return $c['d'];
}

// Voices, then the audio and the subtitles, a line a track: what does not fit
// ends with "… ещё N", the subtitles keep AIO_INF_SUBS_MIN lines.
function aio_info_col_tracks($r, $pf, $tt)
{
    $lang = $tt['lang'];
    $c = aio_info_col(1, aio_tr($lang, 'info_col_tracks'), array());
    $dub = aio_voices_words($lang);
    $dub = $dub['dub'];
    $v = aio_info_clean(aio_row_voices_text($r, $lang, false));
    $max = min(4, aio_info_room($c, true));
    if ($v !== '' && $max > 0)
        aio_info_put($c, $tt['voices'], aio_gc_wrap_voices($v, 26, AIO_INF_CW, $max,
            $v === $dub || strpos($v, "$dub:") === 0 || strpos($v, "$dub \xC2\xB7") === 0), true);

    list($audio, $na) = aio_info_audio($r, $pf);
    $alabel = $tt['audio'] . ($audio ? " ($na)" : '');
    if (!$audio)
    {
        $audio = aio_gc_wrap_items($r['audio_full'] !== '' ? aio_info_clean($r['audio_full']) : "\xE2\x80\x94", 26,
            AIO_INF_CW, 2);
        $na = count($audio);
    }
    list($subs, $ns) = aio_info_subs($pf);
    $slabel = $tt['subs'] . ($subs || $r['subs'] ? ' (' . ($subs ? $ns : count($r['subs'])) . ')' : '');
    // Languages only (JacRed, parsedFile.subtitles): joined, at most AIO_INF_SUBS_MIN lines.
    $codes = !$subs && $r['subs'];
    if ($codes)
        $subs = aio_gc_wrap(implode(', ', $r['subs']), 26, AIO_INF_CW, AIO_INF_SUBS_MIN);
    // Value lines left for both: the two labels and a gap between.
    $room = intval(($c['end'] - $c['y'] - ($subs ? AIO_INF_GAP : 0)) / AIO_INF_LH) - ($subs ? 2 : 1);
    $sn = min(count($subs), max(AIO_INF_SUBS_MIN, $room - count($audio)));
    $an = max(1, $room - $sn);
    $sn = min($sn, $room - $an);
    aio_info_put($c, $alabel, aio_info_more($audio, $an, $na, $lang), true);
    if ($sn > 0)
        aio_info_put($c, $slabel, $codes ? aio_gc_wrap(implode(', ', $r['subs']), 26, AIO_INF_CW, $sn) :
            aio_info_more($subs, $sn, $ns, $lang), true);
    return $c['d'];
}

function aio_info_col_source($r, $sd, $pf, $tt)
{
    $lang = $tt['lang'];
    $l = array();
    foreach (array('seeders', 'age', 'expr', 'eps_file', 'eps_pack', 'type') as $k)
        $l[$k] = aio_tr($lang, "info_$k");
    $c = aio_info_col(2, aio_tr($lang, 'info_col_source'),
        array_merge($l, array($tt['trackers'], $tt['addon'], $tt['source'])));
    // The cache status, as the card.
    $status = aio_gc_status($r, $tt);
    aio_info_put($c, '', aio_gc_wrap($status, 26, AIO_INF_CW, 2, true), true, aio_gc_status_color($r['cached']));
    if ($r['trackers'])
        aio_info_field($c, $tt['trackers'], aio_cut(implode(', ', $r['trackers']), AIO_TEXT_MAX), 2);
    else
        aio_info_field($c, $tt['addon'], $r['addon'], 2);
    $tor = aio_arr($sd, 'torrent');
    if (isset($tor['seeders']) && is_int($tor['seeders']) && $tor['seeders'] >= 0)
        aio_info_field($c, $l['seeders'], strval($tor['seeders']), 1);
    // streamData.age: hours (AIOStreams packages/core/src/debrid/utils.ts
    // "age in hours", formatters/base.ts formatHours: under a day in hours, else days).
    $age = aio_num(aio_str($sd, 'age'));
    if ($age > 0)
        aio_info_field($c, $l['age'], $age < 24 ? intval($age) . ' ' . aio_tr($lang, 'info_hours') :
            intval($age / 24) . ' ' . aio_tr($lang, 'info_days'), 1);
    aio_info_field($c, $l['expr'], $r['expr'], 2);
    aio_info_field($c, $tt['source'], $r['network'], 1);
    $eps = aio_info_eps(is_array($r['pf_seasons']) ? $r['pf_seasons'] : array(), $r['pf_episodes']);
    $et = aio_str($pf, 'episodeTitle');
    aio_info_field($c, $l['eps_file'], implode(' · ', array_filter(array($eps, $et), 'strlen')), 2);
    aio_info_field($c, $l['eps_pack'], aio_info_eps(aio_ints(aio_arr($pf, 'folderSeasons')),
        aio_ints(aio_arr($pf, 'folderEpisodes'))), 2);
    $type = array(aio_str($sd, 'type'));
    foreach (array('private', 'freeleech') as $k)
        $type[] = isset($tor[$k]) && $tor[$k] === true ? $k : '';
    aio_info_field($c, $l['type'], implode(' · ', array_filter($type, 'strlen')), 1);
    aio_info_field($c, aio_tr($lang, 'info_hash'), $r['hash'], 2, true);
    return $c['d'];
}

// Children of page 1: the name (2 lines) and the three columns.
function aio_info_passport($st, $n)
{
    $r = $st['rows'][$n];
    $tt = aio_gc_text(aio_gc_lang($st));
    $raw = isset($r['raw']) && is_array($r['raw']) ? $r['raw'] : array();
    $sd = aio_arr($raw, 'streamData');
    $pf = aio_arr($sd, 'parsedFile');
    $d = array();
    $name = aio_info_clean($r['label']);
    if (mb_strlen($name, 'UTF-8') > AIO_GC_NAME_MAX)
        $name = rtrim(mb_substr($name, 0, AIO_GC_NAME_MAX, 'UTF-8')) . "\xE2\x80\xA6";
    foreach (aio_gc_wrap($name, 26, AIO_INF_W, 2) as $i => $line)
        $d[] = aio_info_cut(aio_gc_at(AIO_INF_W, AIO_INF_LH, 0, $i * AIO_INF_LH), $line, 26, AIO_GC_TEXT);
    return array_merge($d, aio_info_col_file($r, $raw, $sd, $pf, $tt), aio_info_col_tracks($r, $pf, $tt),
        aio_info_col_source($r, $sd, $pf, $tt));
}

// --- YAML pages.

// A JSON list (keys 0..n-1) by its first and last keys: a list of 200 000
// is not walked; an object keyed so is shown as a list.
function aio_info_list($v)
{
    if (!$v)
        return true;
    reset($v);
    $first = key($v);
    end($v);
    return $first === 0 && key($v) === count($v) - 1;
}

// One line of text of a scalar: control characters as spaces.
function aio_info_line($s)
{
    return preg_replace('/[\\x00-\\x1F]+/', ' ', $s);
}

// $v in flow style: "a", "[a, b]", "{k: v}"; about $room characters at most
// (it goes down), the rest "…", as past AIO_INFO_DEPTH.
function aio_info_flow($v, &$room, $depth)
{
    if ($v === '')
        return '""';
    if (!is_array($v))
    {
        $s = aio_info_line(mb_substr(aio_info_scalar($v), 0, max(0, $room) + 1, 'UTF-8'));
        $room -= mb_strlen($s, 'UTF-8');
        return $s;
    }
    $list = aio_info_list($v);
    $parts = array();
    foreach ($v as $k => $x)
    {
        if ($room <= 0 || $depth >= AIO_INFO_DEPTH)
        {
            $parts[] = "\xE2\x80\xA6";
            break;
        }
        $k = $list ? '' : aio_info_line(aio_utf8($k)) . ': ';
        $room -= mb_strlen($k, 'UTF-8') + 2;
        $parts[] = $k . aio_info_flow($x, $room, $depth + 1);
    }
    return ($list ? '[' : '{') . implode(', ', $parts) . ($list ? ']' : '}');
}

// Width of $s at $px before AIO_GC_W_MARGIN: aio_gc_w is ceil(this * margin).
function aio_info_sum($s, $px)
{
    $w = 0;
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $c)
        $w += round(aio_gc_glyph($c) * $px / 1000);
    return $w;
}

// $s in lines of at most $width (by aio_gc_w), broken as aio_gc_wrap does, a
// word wider than a line by characters; at most $max lines, the rest
// dropped. Linear: aio_gc_wrap measures the line again for every word.
function aio_info_wrap($s, $px, $width, $max)
{
    $lines = array();
    $cur = '';
    $cw = 0;
    foreach (preg_split('~(?<=[ _\\-/,)\\]])|(?<=\\.)(?![0-9])~u', $s, -1, PREG_SPLIT_NO_EMPTY) as $t)
    {
        $tw = aio_info_sum($t, $px);
        if (ceil(($cw + $tw) * AIO_GC_W_MARGIN) <= $width)
        {
            $cur .= $t;
            $cw += $tw;
            continue;
        }
        if (trim($cur) !== '')
            $lines[] = rtrim($cur);
        if (count($lines) >= $max)
            return $lines;
        $cur = ltrim($t);
        $cw = aio_info_sum($cur, $px);
        if (ceil($cw * AIO_GC_W_MARGIN) <= $width)
            continue;
        $cur = '';
        $cw = 0;
        foreach (preg_split('//u', ltrim($t), -1, PREG_SPLIT_NO_EMPTY) as $c)
        {
            $gw = round(aio_gc_glyph($c) * $px / 1000);
            if ($cur !== '' && ceil(($cw + $gw) * AIO_GC_W_MARGIN) > $width)
            {
                $lines[] = $cur;
                if (count($lines) >= $max)
                    return $lines;
                $cur = '';
                $cw = 0;
            }
            $cur .= $c;
            $cw += $gw;
        }
    }
    if (trim($cur) !== '')
        $lines[] = rtrim($cur);
    return $lines;
}

// Line $text at indent $lvl to $out (array(level, text, colour)), wrapped to
// the column, the rest one level deeper. false once $max lines are reached.
function aio_info_yline(&$out, $lvl, $text, $max, $color = AIO_GC_TEXT2)
{
    $left = $max - count($out);
    if ($left <= 0)
        return false;
    // More could not be shown anyway.
    $text = mb_substr($text, 0, ($left + 1) * 100, 'UTF-8');
    foreach (aio_info_wrap($text, 24, AIO_INF_CW - ($lvl + 1) * AIO_INF_IND, $left + 1) as $i => $l)
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
function aio_info_yaml(&$out, $lvl, $head, $v, $max, $depth = 0)
{
    $nested = false;
    if (is_array($v) && $v && $depth < AIO_INFO_DEPTH)
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
    if ($nested || (is_array($v) && $v && $depth < AIO_INFO_DEPTH && !aio_info_list($v)))
    {
        if ($head !== '' && !aio_info_yline($out, $lvl, $head, $max))
            return false;
        $sub = $head !== '' ? $lvl + 1 : $lvl;
        $list = aio_info_list($v);
        foreach ($v as $k => $x)
        {
            if ($list)
            {
                $room = ($max - count($out) + 1) * 100;
                $ok = aio_info_yline($out, $sub, '- ' . aio_info_flow($x, $room, $depth + 1), $max);
            }
            else
                $ok = aio_info_yaml($out, $sub, aio_info_line(aio_utf8($k)) . ':', $x, $max, $depth + 1);
            if (!$ok)
                return false;
        }
        return true;
    }
    if (is_string($v) && preg_match('/[\\r\\n]/', trim($v)))
    {
        if (!aio_info_yline($out, $lvl, $head !== '' ? "$head |" : '|', $max))
            return false;
        foreach (preg_split('/\\r\\n|\\r|\\n/', aio_info_scalar($v)) as $l)
        {
            $l = trim(aio_info_line($l));
            if ($l !== '' && !aio_info_yline($out, $lvl + 1, $l, $max))
                return false;
        }
        return true;
    }
    $room = ($max - count($out) + 1) * 100;
    $text = is_array($v) && $v && $depth >= AIO_INFO_DEPTH ? "\xE2\x80\xA6" : aio_info_flow($v, $room, $depth);
    return aio_info_yline($out, $lvl, $head !== '' ? "$head $text" : $text, $max);
}

// YAML lines of row $n: array(AIOStreams lines, Melange lines), each block
// with its head; together at most AIO_INFO_LINES, the last of a cut block
// says so.
function aio_info_blocks($st, $n)
{
    $r = $st['rows'][$n];
    $lang = $st['lang'];
    $cut = array(0, aio_tr($lang, 'info_cut'), AIO_GC_DIM);
    $mel = array(array(0, aio_tr($lang, 'info_melange'), AIO_GC_TEXT));
    $max = intval(AIO_INFO_LINES / 2);
    $ok = true;
    foreach ($r as $k => $v)
    {
        if ($k === 'raw')
            continue;
        if (($k === 'size' || $k === 'pack') && $v > 0)
            $v = aio_size_str($v, $lang) . ' (' . aio_info_scalar($v) . ')';
        else if ($k === 'rate' && $v > 0)
            $v = aio_gc_rate($v, $r['rate_approx'], $lang) . ' (' . aio_info_scalar($v) . ')';
        $ok = aio_info_yaml($mel, 0, "$k:", $v, $max);
        // As the screens show the voices.
        if ($ok && $k === 'voices')
            $ok = aio_info_yaml($mel, 0, 'voices_text:', aio_row_voices_text($r, $lang), $max);
        if (!$ok)
        {
            $mel[$max - 1] = $cut;
            break;
        }
    }
    $max = AIO_INFO_LINES - count($mel);
    $aio = array(array(0, 'AIOStreams', AIO_GC_TEXT));
    if (!aio_info_yaml($aio, 0, '', isset($r['raw']) ? $r['raw'] : array(), $max))
        $aio[$max - 1] = $cut;
    return array($aio, $mel);
}

// Children of a YAML page: up to 3 columns of AIO_INF_YROWS lines.
function aio_info_ypage($lines)
{
    $d = array();
    foreach ($lines as $i => $l)
    {
        $x = intval($i / AIO_INF_YROWS) * (AIO_INF_CW + AIO_INF_CGAP) + $l[0] * AIO_INF_IND;
        $d[] = aio_info_cut(aio_gc_at(AIO_INF_CW - $l[0] * AIO_INF_IND, AIO_INF_YLH, $x,
            $i % AIO_INF_YROWS * AIO_INF_YLH), $l[1], 24, $l[2]);
    }
    return $d;
}

// Children of every page: the passport, then the AIOStreams pages, then the
// Melange ones.
function aio_info_pages($st, $n)
{
    $pages = array(aio_info_passport($st, $n));
    foreach (aio_info_blocks($st, $n) as $block)
    {
        foreach (array_chunk($block, 3 * AIO_INF_YROWS) as $lines)
            $pages[] = aio_info_ypage($lines);
    }
    return $pages;
}

// --- Screen.

function aio_info_pages_geom($page, $n)
{
    return aio_gc_at(AIO_INF_W, $n * AIO_INF_H, 0, -$page * AIO_INF_H);
}

// Children of 'icount' (under the pages, at the right): "1 / 4".
function aio_info_counter($page, $n)
{
    return array(aio_gc_cut(null, aio_gc_at(300, 50, 0, 0), ($page + 1) . " / $n", 36, AIO_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_RIGHT)));
}

// sel_state -> page, 0 when it does not fit $n pages.
function aio_info_page($s, $n)
{
    $j = is_string($s) && $s !== '' ? json_decode($s) : null;
    return is_object($j) && isset($j->page) && is_int($j->page) && $j->page >= 0 && $j->page < $n ? $j->page : 0;
}

function aio_info_window($st, $n, $pages, $page)
{
    $mv = $st['movie'];
    $tt = aio_gc_text(aio_gc_lang($st));
    $d = array(aio_gc_rect(aio_gc_at(1920, 1080, 0, 0), '#C0000000'));
    // The title as the list's; the episode and the row at the right.
    list($title) = aio_gc_title($st, count($st['rows']));
    $count = ($st['e'] > 0 ? sprintf('S%02dE%02d · ', $st['s'], $st['e']) : '') .
        aio_tr($tt['lang'], 'info_stream') . ' ' . ($n + 1) . ' / ' . count($st['rows']);
    $cw = min(600, aio_gc_w($count, 36));
    $d[] = aio_gc_cut(null, aio_gc_at(AIO_INF_W - $cw - 30, 66, AIO_INF_X, 40), $title, 48, AIO_GC_TEXT);
    $d[] = aio_gc_cut(null, aio_gc_at($cw, 50, AIO_INF_X + AIO_INF_W - $cw, 52), $count, 36, AIO_GC_TEXT2,
        array(GCompTtfLabelDef::halign => HALIGN_RIGHT));
    // Viewport: clipping panel 'iview'; the inner panel 'ipages' moves a page.
    $in = array();
    foreach ($pages as $i => $p)
        $in[] = aio_gc_panel("ip$i", aio_gc_at(AIO_INF_W, AIO_INF_H, 0, $i * AIO_INF_H), $p);
    $d[] = aio_gc_panel('iview', aio_gc_at(AIO_INF_W, AIO_INF_H, AIO_INF_X, AIO_INF_Y),
        array(aio_gc_panel('ipages', aio_info_pages_geom($page, count($pages)), $in, 0)), 0);
    // The vendor hint of P+/P- and "Страницы", the counter at the right.
    $d[] = aio_gc_image(aio_gc_at(114, 50, AIO_INF_X, AIO_GC_HINT_Y),
        'plugin_file://%shell_ext%/icons/p_plus_p_minus.png');
    $hint = aio_tr($tt['lang'], 'info_pages');
    $d[] = aio_gc_cut(null, aio_gc_at(aio_gc_w($hint, 36), 50, AIO_INF_X + 126, AIO_GC_HINT_Y), $hint, 36,
        AIO_GC_TEXT2);
    $d[] = aio_gc_panel('icount', aio_gc_at(300, 50, AIO_INF_X + AIO_INF_W - 300, AIO_GC_HINT_Y),
        aio_info_counter($page, count($pages)));
    $fanart = aio_img($mv['fanart']);
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
function aio_info_folder_view($media_url, $sel_state = null)
{
    $st = Aio::$state;
    if (!$st || !preg_match('/^info:([0-9a-f]+):([0-9]+)$/D', $media_url, $m) || $m[1] !== $st['rid'] ||
        !isset($st['rows'][intval($m[2])]))
        return aio_lines_view(array(aio_expired_item()));
    $n = intval($m[2]);
    $pages = aio_info_pages($st, $n);
    $page = aio_info_page($sel_state, count($pages));
    $np = strval(count($pages));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => aio_info_window($st, $n, $pages, $page),
            PluginGCompsFolderView::sel_state => json_encode(array('page' => $page)),
            PluginGCompsFolderView::actions => array(
                GUI_EVENT_KEY_P_PLUS => aio_input('info_page', array('d' => 'prev', 'pages' => $np)),
                GUI_EVENT_KEY_P_MINUS => aio_input('info_page', array('d' => 'next', 'pages' => $np))),
            PluginGCompsFolderView::timer => null));
}

// P+/P- on the details screen -> change_gcomps of the page; null at an edge.
// Only the drawn screen moves: no list needed (its count of pages comes
// with the key).
function aio_info_input($in)
{
    $n = isset($in->pages) && is_string($in->pages) && preg_match('/^[1-9][0-9]{0,2}$/D', $in->pages) ?
        intval($in->pages) : 0;
    $d = isset($in->d) && is_string($in->d) ? $in->d : '';
    if ($n < 2 || ($d !== 'prev' && $d !== 'next'))
        return null;
    $page = aio_info_page(isset($in->parent_sel_state) ? $in->parent_sel_state : null, $n);
    $np = $page + ($d === 'next' ? 1 : -1);
    if ($np < 0 || $np >= $n)
        return null;
    return array(
        GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID,
        GuiAction::data => array(
            ChangeGCompsActionData::change_defs => array(
                aio_gc_change('ipages', aio_info_pages_geom($np, $n), GCOMP_TRANSITION_DEFAULT),
                aio_gc_change('icount', null, GCOMP_TRANSITION_NONE, aio_info_counter($np, $n))),
            ChangeGCompsActionData::num_steps => AIO_GC_STEPS,
            ChangeGCompsActionData::sel_state => json_encode(array('page' => $np))));
}

// The one row of a screen whose list is gone (replaced, php_server restarted):
// in both languages, the language of the list is gone too.
function aio_expired_item()
{
    return array(
        PluginRegularFolderItem::media_url => 'expired',
        PluginRegularFolderItem::caption => aio_tr('ru', 'list_outdated') . ' / ' . aio_tr('en', 'list_outdated'),
        PluginRegularFolderItem::view_item_params => array());
}

// A plain list of text lines, the view of the 0.1.1 list: the "outdated"
// screen. OK on a line does nothing: its action asks for a control no one
// handles.
function aio_lines_view($items)
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
