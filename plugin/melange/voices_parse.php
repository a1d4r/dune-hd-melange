<?php
// Voices of a release for the "Voices" field:
// studios, types, original and subtitles from its names and audio tracks.
// Pure functions, no screens. Needs voices.php (the dictionary), of
// parse.php: aio_utf8, aio_cut, AIO_TEXT_MAX, and of main.php: aio_tr.

// Bytes of a release name read for its voices (real names are below 1 KB).
define('AIO_VOICES_BYTES', 4000);
// Audio tracks of a release read for its voices (real releases have up to ~30):
// every redraw of the card parses them.
define('AIO_VOICES_TRACKS', 32);

// A track title without what the codec and channels show, its parts joined
// by " | ": "Original | AC3 5.1 | 640 Kbps" -> "Original", "Dub \| DD 5.1 @
// 640 kbps - RHS" -> "Dub | RHS".
function aio_jr_title($t)
{
    $t = preg_replace('/(?<![\p{L}\p{N}])(?:(?:e-?ac-?3|ac-?3|ddp?\+?|dolby\s+digital(?:\s+plus)?|' .
        'dts(?:[:\-]x|-?hd)?(?:\s*(?:ma|master\s+audio|hra|es))?|truehd|atmos|aac|flac|opus|mp3|l?pcm)' .
        '(?:\s*[0-9]\.[0-9])?|[0-9]\.[0-9](?:\.[0-9])?|[0-9]?\s?ch|[0-9]+(?:[.,][0-9]+)?\s*[km]bps|' .
        '[0-9]+(?:[.,][0-9]+)?\s?khz|[0-9]+-?bit|joc|core|stereo|mono)(?![\p{L}\p{N}])/iu', '|', $t);
    // No separators inside brackets at their ends, then no empty brackets:
    // "(LostFilm, DTS 5.1)" -> "(LostFilm)", "Кубик в Кубе [ ]" -> "Кубик в Кубе".
    $t = preg_replace('/([(\[])[\s|@\\\\\-_,;:\/]+/u', '$1', $t);
    $t = preg_replace('/[\s|@\\\\\-_,;:\/]+([)\]])/u', '$1', $t);
    $t = str_replace(array('()', '[]'), '|', $t);
    $out = array();
    foreach (preg_split('/[|@\\\\]/', $t) as $p)
    {
        $p = preg_replace('/^[\s\-_,;:\/.\x{2013}\x{2014}]+|[\s\-_,;:\/.\x{2013}\x{2014}]+$/u', '', $p);
        if ($p !== '')
            $out[] = $p;
    }
    return implode(' | ', $out);
}

// A word of a name -> array(kind, id): 'type' (dub, mvo, dvo, avo), 'orig'
// (orig, jap), 'subs'; null if it tells nothing. $low: lower case, ё -> е.
// Tracker codes ("ДБ, 2 x ПМ, АП (Сербин), СТ") only as written: "по", "ст"
// are words.
function aio_voices_word($low, $word)
{
    static $words = array('dub' => 'type:dub', 'dubbed' => 'type:dub', 'mvo' => 'type:mvo',
        'dvo' => 'type:dvo', 'avo' => 'type:avo', 'авторский' => 'type:avo', 'original' => 'orig:orig',
        'orig' => 'orig:orig', 'jap' => 'orig:jap', 'jpn' => 'orig:jap', 'japanese' => 'orig:jap',
        'sub' => 'subs:1', 'subs' => 'subs:1', 'subbed' => 'subs:1', 'subtitles' => 'subs:1',
        'softsub' => 'subs:1', 'hardsub' => 'subs:1');
    static $codes = array('ДБ' => 'type:dub', 'ПМ' => 'type:mvo', 'ЛМ' => 'type:mvo', 'ПД' => 'type:dvo',
        'ЛД' => 'type:dvo', 'АП' => 'type:avo', 'ПО' => 'type:avo', 'ЛО' => 'type:avo', 'VO' => 'type:avo',
        'СТ' => 'subs:1');
    // By the stem, the capture group tells which.
    static $stems = array(1 => 'type:dub', 'type:mvo', 'type:dvo', 'type:avo', 'orig:orig', 'orig:jap', 'subs:1');
    if (isset($words[$low]))
        $c = $words[$low];
    else if (isset($codes[$word]))
        $c = $codes[$word];
    // A stem: only words of these first two letters go to the regex.
    else if (strpos(' ду мн ба дв од ор яп су ', ' ' . substr($low, 0, 4) . ' ') !== false &&
        preg_match('/^(?:(дубл(?:$|яж(?!н)|ир|ьов))|(многоголос|багатоголос)|(двухголос|двоголос)|(одноголос)|' .
        '(оригин|оригін)|(японск)|(субтитр))/u', $low, $m))
        $c = $stems[count($m) - 1];
    else
        return null;
    return explode(':', $c);
}

// Voices of one release name: array('types' => ids in the order of the name,
// 'studios' => names shown, 'orig' => '' | 'orig' | 'jap', 'subs' => bool,
// 'name' => the name without JacRed's tails, 'keys' => studio => its key).
// Studios: whole words of the dictionary (voices.php), the longest first, no
// overlaps, none before the first year (the title and the director of
// rutracker: "Белорусский вокзал (Андрей Смирнов) [1970, ...]").
function aio_voices_parse($s, $tails = true)
{
    // Bytes first: no regex on a huge string; aio_utf8 mends a cut letter.
    $s = trim(aio_utf8(substr(strval($s), 0, AIO_VOICES_BYTES)));
    // JacRed's " | [A B].rus" ("Кирдин | Stalk" is one name): its voices by
    // substring of this name and of the duplicates it joined.
    $tail = '';
    if ($tails && substr($s, -5) === '].rus' && ($b = strrpos($s, '[')) !== false &&
        substr($head = rtrim(substr($s, 0, $b)), -1) === '|')
    {
        $tail = substr($s, $b + 1, -5);
        $s = rtrim(substr($head, 0, -1));
    }
    $v = array('types' => array(), 'studios' => array(), 'orig' => '', 'subs' => false, 'name' => $s,
        'keys' => array(), 'stype' => array());
    // Real names are below 400 characters; a cut one keeps its end.
    $whole = mb_strlen($s, 'UTF-8') <= 1000;
    if (!$whole)
        $s = mb_substr($s, 0, 1000, 'UTF-8');
    // Names JacRed finds inside other words of a duplicate ("Штейн" of
    // "Бернштейн"): only from the voices of the name itself, not its join or tail.
    static $unsafe = array('Штейн' => true);

    list($word, $low, $join, $seps, $part, $dep, $lead, $pipe) = aio_voices_tokens($s);
    $n = count($word);
    $p = count($pipe);
    if ($n === 0)
        return $v;
    // The first year: before it studios and types are of the title and the
    // director; original and subtitles only in brackets ("[RUS(int), JAP+Sub]
    // [2013, ...]" of anime; not "Original Sin (2009)"), studios only in the
    // leading group ("[AniLibria] ...").
    $year = 0;
    while ($year < $n && !(strlen($word[$year]) === 4 && ctype_digit($word[$year]) &&
        ($word[$year][0] . $word[$year][1] === '19' || $word[$year][0] . $word[$year][1] === '20')))
        $year++;
    if ($year === $n)
        $year = 0;

    $cls = aio_voices_classes($word, $low, $join, $seps, $part);

    // Each part of the name between "|": 1 - voices (numbers too), 0 - only
    // numbers, -1 - something else.
    $ok = array();
    foreach ($cls as $i => $c)
    {
        $q = $part[$i];
        if (!isset($ok[$q]))
            $ok[$q] = 0;
        if (!$c)
            $ok[$q] = -1;
        else if ($c[0] !== 'n' && $ok[$q] === 0)
            $ok[$q] = 1;
    }

    // " | LostFilm | Кубик в Кубе", " | D, P" at the end: JacRed's join of
    // duplicates or the voices of rutor - off the name, if every part is voices.
    $from = 0;
    for ($q = $p; $q > 0 && (!isset($ok[$q]) || $ok[$q] >= 0); $q--)
    {
        if (isset($ok[$q]) && $ok[$q] === 1)
            $from = $q;
    }
    if ($whole && $from > 0)
        $v['name'] = rtrim(substr($s, 0, $pipe[$from]));
    // The words before it, for the rule of JacRed's voices below.
    $head = $from > 0 ? implode(' ', array_slice($low, 0, array_search($from, $part, true))) : '';

    // The type a studio is written with: "DVO (Кубик в Кубе)", "2x AVO (Сербин,
    // Дольский)", "MVO - LostFilm". A type holds for the brackets right after it
    // or the words after it up to ",", "+", "|", ";", "/".
    $stype = array();
    $pt = '';
    $pd = 0;
    $deeper = false;
    for ($i = 0; $i < $n; $i++)
    {
        if ($pt !== '' && $i > 0 && $dep[$i] <= $pd && ($deeper || strpbrk($seps[$i - 1], ',+|;/') !== false))
            $pt = '';
        $c = isset($cls[$i]) ? $cls[$i] : null;
        if ($c && ($c[0] === 'type' || ($c[0] === 'code' && $ok[$part[$i]] === 1)))
        {
            $pt = $c[1];
            $pd = $dep[$i];
            $deeper = false;
        }
        else if ($pt !== '')
        {
            $deeper = $deeper || $dep[$i] > $pd;
            if ($c && $c[0] === 'studio')
                $stype[$i] = $pt;
        }
    }

    $types = array();
    foreach ($cls as $i => $c)
    {
        if (!$c || ($c[0] === 'code' && $ok[$part[$i]] !== 1) || ($i < $year && $dep[$i] === 0) ||
            ($i < $year && $c[0] !== 'orig' && $c[0] !== 'subs' && !($c[0] === 'studio' && $lead[$i])))
            continue;
        if ($c[0] === 'studio' && $c[1] !== '' && count($v['studios']) < 20 && !isset($v['keys'][$c[1]]) &&
            ($from === 0 || $part[$i] < $from || (strpos($head, $c[2]) === false && !isset($unsafe[$c[1]]))))
        {
            $v['studios'][] = $c[1];
            $v['keys'][$c[1]] = $c[2];
            if (isset($stype[$i]))
                $v['stype'][$c[1]] = $stype[$i];
        }
        else if ($c[0] === 'type' || $c[0] === 'code')
            $types[$c[1]] = $c[1];
        else if ($c[0] === 'orig' && $v['orig'] !== 'jap')
            $v['orig'] = $c[1];
        else if ($c[0] === 'subs')
            $v['subs'] = true;
    }
    $v['types'] = array_values($types);
    if ($tails)
    {
        foreach (aio_voices_name_extra($s) as $x)
        {
            if (count($v['studios']) < 20 && !isset($v['keys'][$x[0]]))
            {
                $v['studios'][] = $x[0];
                $v['keys'][$x[0]] = aio_voices_key($x[0]);
                if ($x[1] !== '')
                    $v['stype'][$x[0]] = $x[1];
            }
        }
    }

    if ($tail !== '')
        $v = aio_voices_tail($v, implode(' ', $low), $tail, $unsafe);
    return $v;
}

// Tokens of a name -> array(word, low, join, seps, part, dep, lead, pipe), by
// word: as written; in lower case, ё -> е; the joint to the next ('' - a group
// ends: ",", "|", brackets; "+"; " "); the separator after it; the part of the
// name after the N-th "|"; the depth in brackets; inside the leading "[...]"
// (the voice studio of anime releases: "[AniLibria] Jujutsu Kaisen (2020)").
// pipe: where each "|" is, from 1. Lower case at once; word by word if that
// changed the words (a letter whose lower case is a letter and a mark).
function aio_voices_tokens($s)
{
    $re = '/([^\p{L}\p{N}]+)/u';
    $pc = preg_split($re, $s, -1, PREG_SPLIT_DELIM_CAPTURE);
    $lc = preg_split($re, mb_strtolower(str_replace(array('ё', 'Ё'), 'е', $s), 'UTF-8'), -1, PREG_SPLIT_DELIM_CAPTURE);
    $same = count($lc) === count($pc);
    $word = array();
    $low = array();
    $join = array();
    $seps = array();
    $part = array();
    $dep = array();
    $depth = 0;
    $lead_end = substr($s, 0, 1) === '[' ? strpos($s, ']') : false;
    $lead = array();
    $pipe = array();
    $n = 0;
    $p = 0;
    $pos = 0;
    foreach ($pc as $k => $x)
    {
        if ($k & 1)
        {
            for ($q = strpos($x, '|'); $q !== false; $q = strpos($x, '|', $q + 1))
                $pipe[++$p] = $pos + $q;
            if (strpbrk($x, '[]()') !== false)
                $depth = max(0, $depth + substr_count($x, '[') + substr_count($x, '(') - substr_count($x, ']') -
                    substr_count($x, ')'));
            if ($n > 0)
            {
                $seps[$n - 1] = $x;
                $join[$n - 1] = $x === '+' ? '+' : (strpbrk($x, '|/\\,;()[]{}@+') === false ? ' ' : '');
            }
        }
        else if ($x !== '')
        {
            $word[$n] = $x;
            $dep[$n] = $depth;
            $lead[$n] = $lead_end !== false && $pos < $lead_end;
            $low[$n] = $same ? $lc[$k] : mb_strtolower(str_replace(array('ё', 'Ё'), 'е', $x), 'UTF-8');
            $part[$n++] = $p;
        }
        $pos += strlen($x);
    }
    if ($n > 0)
        $join[$n - 1] = '';
    return array($word, $low, $join, $seps, $part, $dep, $lead, $pipe);
}

// What each word of aio_voices_tokens() is: array(kind, id) - 'studio' (id:
// the name shown, then its key), 'type', 'code' (of rutor), 'orig', 'subs';
// 'in' - inside a studio; 'n' - a number or "x" ("2 x ПМ"); null - unknown.
// Studios: whole words of the dictionary (voices.php), the longest first.
function aio_voices_classes($word, $low, $join, $seps, $part)
{
    // Kept here: a static array returned by a function is copied on every call.
    static $dict = null;
    static $exact = null;
    // First words of the keys of several words.
    static $first = null;
    if ($first === null)
    {
        $dict = aio_voices_dict();
        $exact = aio_voices_exact();
        $first = array();
        foreach ($dict as $k => $unused)
        {
            $f = strtok($k, ' +');
            if ($f !== $k)
                $first[$f] = true;
        }
    }
    // Rutor codes: only in a part after "|" that is all voices ("| D, P, L2").
    static $rutor = null;
    if ($rutor === null)
        $rutor = aio_voices_codes();
    $n = count($word);
    $cls = array();
    for ($i = 0; $i < $n; )
    {
        $key = $low[$i];
        $hit = isset($dict[$key]) && (!isset($exact[$key]) || $exact[$key] === $word[$i]) ? $key : '';
        // "НТВ+" ("+" is no word): its key "нтв плюс".
        if (isset($seps[$i]) && $seps[$i][0] === '+' && isset($dict["$key плюс"]))
            $hit = "$key плюс";
        $len = 1;
        for ($j = $i + 1; isset($first[$low[$i]]) && $j < $n && $j - $i < AIO_VOICES_NGRAM && $join[$j - 1] !== ''; $j++)
        {
            $key .= $join[$j - 1] . $low[$j];
            if (isset($dict[$key]))
            {
                $hit = $key;
                $len = $j - $i + 1;
            }
        }
        if ($hit !== '')
        {
            $cls[$i] = array('studio', $dict[$hit], $hit);
            for ($j = $i + 1; $j < $i + $len; $j++)
                $cls[$j] = array('in', '');
            $i += $len;
            continue;
        }
        $c = aio_voices_word($low[$i], $word[$i]);
        if (!$c && isset($rutor[$word[$i]]))
        {
            // "Dune.Part.Two.2024.D.MVO.AVO.WEB-DL": right after the year, by a dot.
            if ($i > 0 && $seps[$i - 1] === '.' && preg_match('/^(?:19|20)[0-9]{2}$/', $word[$i - 1]))
                $c = array('type', $rutor[$word[$i]]);
            else if ($part[$i] > 0)
                $c = array('code', $rutor[$word[$i]]);
        }
        if (!$c && preg_match('/^(?:[0-9]+|x|х)$/u', $low[$i]))
            $c = array('n', '');
        $cls[$i] = $c;
        $i++;
    }
    return $cls;
}

// Voices of JacRed's tail " | [A B].rus" (the join of duplicates) added to
// $v of the name ($norm: its words in lower case): a studio whose key is in
// the name only inside a longer word (or before the year) is JacRed's
// substring ("Королёв" of "Королева", "Amedia" of "Novamedia"); one not in
// the name at all comes from a duplicate.
function aio_voices_tail($v, $norm, $tail, $unsafe)
{
    $t = aio_voices_parse($tail, false);
    foreach ($t['keys'] as $name => $key)
    {
        if (strpos($norm, $key) === false && !isset($v['keys'][$name]) && !isset($unsafe[$name]) &&
            count($v['studios']) < 20)
        {
            $v['studios'][] = $name;
            $v['keys'][$name] = $key;
        }
    }
    $v['types'] = array_merge($v['types'], array_diff($t['types'], $v['types']));
    return $v;
}

// Voices of a release from its names (folder, file): types and studios in
// order, without repeats; a studio inside a longer one found is dropped
// ("Сербин" with "Ю. Сербин").
function aio_voices($names)
{
    $v = array('types' => array(), 'studios' => array(), 'orig' => '', 'subs' => false, 'stype' => array());
    foreach ($names as $p)
    {
        $v['stype'] += isset($p['stype']) ? $p['stype'] : array();
        $v['types'] = array_merge($v['types'], array_diff($p['types'], $v['types']));
        $v['studios'] = array_merge($v['studios'], array_diff($p['studios'], $v['studios']));
        $v['orig'] = $v['orig'] === 'jap' || $p['orig'] === '' ? $v['orig'] : $p['orig'];
        $v['subs'] = $v['subs'] || $p['subs'];
    }
    $keep = array();
    foreach ($v['studios'] as $a)
    {
        $in = false;
        foreach ($v['studios'] as $b)
        {
            if ($a !== $b && preg_match('/(?<![\p{L}\p{N}])' . preg_quote($a, '/') . '(?![\p{L}\p{N}])/iu', $b))
                $in = true;
        }
        if (!$in)
            $keep[] = $a;
    }
    $v['studios'] = $keep;
    return $v;
}

// Words of the voices on the screens.
function aio_voices_words($lang)
{
    return array('dub' => aio_tr($lang, 'voices_dub'), 'mvo' => aio_tr($lang, 'voices_mvo'),
        'dvo' => aio_tr($lang, 'voices_dvo'), 'avo' => aio_tr($lang, 'voices_avo'),
        'orig' => aio_tr($lang, 'voices_orig'), 'subs' => aio_tr($lang, 'voices_subs'));
}

// Voices of a name with all their types: "Дубляж, ПМ · LostFilm, Кубик в Кубе ·
// оригинал JAP · субтитры"; '' if the name tells nothing. Not used by the
// plugin: for the tests and offline checks.
function aio_voices_text($v, $lang, $subs = true)
{
    $w = aio_voices_words($lang);
    $types = array();
    foreach ($v['types'] as $type)
        $types[] = $w[$type];
    $p = array(implode(', ', $types), implode(', ', $v['studios']));
    if ($v['orig'] !== '')
        $p[] = $w['orig'] . ($v['orig'] === 'jap' ? ' JAP' : '');
    if ($v['subs'] && $subs)
        $p[] = $w['subs'];
    return aio_cut(implode(' · ', array_filter($p, 'strlen')), AIO_TEXT_MAX);
}

// Lower case, ё -> е, words joined by a space: the key of voices.php.
function aio_voices_key($s)
{
    return trim(preg_replace('/[^\p{L}\p{N}+]+/u', ' ', mb_strtolower(str_replace(array('ё', 'Ё'), 'е', $s), 'UTF-8')));
}

// Key of one person or studio, for repeats: the key of its name in voices.php;
// a name not there whose last word is ("Андрей Дольский") - that word's.
function aio_voices_same($name)
{
    static $dict = null;
    if ($dict === null)
        $dict = aio_voices_dict();
    $k = aio_voices_key($name);
    $last = substr(strrchr(" $k", ' '), 1);
    if (isset($dict[$k]) && $dict[$k] !== '')
        return aio_voices_key($dict[$k]);
    return $last !== $k && isset($dict[$last]) && $dict[$last] !== '' ? aio_voices_key($dict[$last]) : $k;
}

// The voice of one audio track by its title ("Dub Bravo Records Georgia",
// "MVO | LostFilm | Кубик в Кубе", "AVO Ю.Сербин") and language code:
// array('types' => dub|mvo|dvo|avo in order, 'studios' => names, 'orig' =>
// bool); null for a commentary. Studios are the parts of the title without the
// types, the language, the codec, file names and separators: the names of
// voices.php found in a part by words, else the part as written - only for
// RU, UA and unknown tracks (a foreign one names its studios only by the
// dictionary: "Surround", "Director's Cut" are no studios).
function aio_track_voice($title, $lang = '')
{
    static $noise = null;
    static $own = null;
    static $dict = null;
    static $exact = null;
    // Keys without spaces: "PazlVoice" is "Pazl Voice".
    static $glued = array();
    if ($dict === null)
    {
        $noise = aio_voices_noise();
        $own = aio_voices_own();
        $dict = aio_voices_dict();
        $exact = aio_voices_exact();
        foreach ($dict as $k => $name)
            $glued[str_replace(' ', '', $k)] = $name;
    }
    // "й" and "ё" written with a combining mark ("Яроцкии\xCC\x86"); [b] tags,
    // also with the brackets lost ("bBravo Records GeorgiaB").
    $s = str_replace(array("\xD0\xB8\xCC\x86", "\xD0\x98\xCC\x86", "\xD0\xB5\xCC\x88", "\xD0\x95\xCC\x88"),
        array('й', 'Й', 'ё', 'Ё'), aio_voices_clean($title));
    $s = preg_replace('~\[/?[a-zA-Z]\]|(?<!\p{L})b(?=\p{Lu}\p{Ll})|(?<=\p{Ll})B(?!\p{L})~u', ' ', $s);
    // File names and track numbers: "X265-SpaceHD13.А.Гаврилов", "Rus.mka", "Track2", "18+".
    $s = preg_replace('/(?<![\p{L}\p{N}])(?:[xh]\.?26[45]|hevc|avc)(?:-[A-Za-z0-9]+)?|' .
        '\.(?:mka|mkv|mp4|m4a|ac3|eac3|dts|aac|flac|thd|wav)(?![\p{L}\p{N}])|' .
        '(?<![\p{L}\p{N}])(?:track|дорожка|audio|аудио)\s*#?\s*[0-9]+|[0-9]+\+(?![0-9])/iu', '|', $s);
    // A channel before its name: "т/к Україна", 'ТК "Україна"' ("Телеканал Че" stays).
    $s = preg_replace('~(?<![\p{L}\p{N}])(?:т\s*/\s*к|тк|телеканал|канал)\s*(?=["\x{00AB}]?\p{L}{3})~iu', '|', $s);
    $s = aio_jr_title($s);
    // Commentaries and audio description are no voices.
    if (preg_match('/comment|коммент|\bcomm\b|descripti|audiodescr|тифло/iu', $s))
        return null;
    $foreign = !in_array($lang, array('', 'RU', 'UA'), true);
    // The cast of a studio is no studio: "AniLibria (HectoR, Sharon)", "[AniLibria]
    // HectoR, Lupin", "Itashi & Kari [AniLibria]" (aio_voices_cast).
    $skip = array();
    if (preg_match('~^(.*?)[(\[]([^()\[\]]*)[)\]](.*)$~su', $s, $m))
    {
        $in = aio_voices_side($m[2]);
        $out = aio_voices_side($m[1] . ' | ' . $m[3]);
        $drop = '';
        // Outside the brackets only a list goes: "Студия Нота [РТР/СТС]" stays.
        if (aio_voices_cast($out, $in) && $out['n'] >= 2)
            $drop = $m[1] . '|' . $m[3];
        else if (aio_voices_cast($in, $out))
            $drop = $m[2];
        foreach (aio_voices_items($drop) as $x)
            $skip[] = ' ' . aio_voices_skip_key($x) . ' ';
    }
    $v = array('types' => array(), 'studios' => array(), 'orig' => false);
    $parts = preg_split('/([\p{L}\p{N}]+)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 1; $i < count($parts); $i += 2)
    {
        $low = mb_strtolower(str_replace(array('ё', 'Ё'), 'е', $parts[$i]), 'UTF-8');
        // "D" of rutor alone, not a letter of "C.D.V.".
        $d = $parts[$i] === 'D' && substr($parts[$i - 1], -1) !== '.' && substr($parts[$i + 1], 0, 1) !== '.';
        // "Dubbing (Red Head Sound)", but "Movie Dubbing" is a studio; "Origina" is cut.
        if ($d || ($low === 'dubbing' && $i === 1))
            $c = array('type', 'dub');
        else if (strpos($low, 'двуголос') === 0)
            $c = array('type', 'dvo');
        // Types in Latin letters: "Dubliazh", "mnogogolos".
        else if (preg_match('/^(?:(dubl[iy]?a(?:zh|j)(?![nh]))|(mnogogolos)|(dvu[hx]?golos)|(odnogolos))/', $low, $tm))
        {
            if ($tm[1] !== '')
                $c = array('type', 'dub');
            else if (isset($tm[2]) && $tm[2] !== '')
                $c = array('type', 'mvo');
            else if (isset($tm[3]) && $tm[3] !== '')
                $c = array('type', 'dvo');
            else
                $c = array('type', 'avo');
        }
        else if (strpos($low, 'origin') === 0)
            $c = array('orig', 'orig');
        else
            $c = aio_voices_word($low, $parts[$i]);
        if ($c && $c[0] === 'type' && !in_array($c[1], $v['types'], true))
            $v['types'][] = $c[1];
        else if ($c && $c[0] === 'orig')
            $v['orig'] = true;
        else if (!$c && (!isset($noise[$low]) || (isset($exact[$low]) && $exact[$low] === $parts[$i])))
            continue;
        $parts[$i] = '|';
    }
    $list = array();
    foreach (preg_split('~\s*[|,;/()\[\]{}"\x{00AB}\x{00BB}]+\s*~u', implode('', $parts)) as $p)
        $list = array_merge($list, aio_voices_names($p));
    foreach ($list as $p)
    {
        // "НТВ+": the "+" is part of the name.
        $plus = '';
        if (preg_match('/^[\s.\-_:\x{2013}\x{2014}]*(\p{L}.*?)\s*\+\s*$/u', $p, $pm) &&
            isset($dict[aio_voices_key($pm[1]) . ' плюс']))
            $plus = $dict[aio_voices_key($pm[1]) . ' плюс'];
        $p = preg_replace('/^[\s.\-_:+&\x{2013}\x{2014}]+|[\s\-_:+&\x{2013}\x{2014}]+$/u', '', $p);
        $p = aio_voices_unwrap($p);
        // The year of a translation: "AVO Goblin 2004".
        $p = trim(preg_replace('/(?<![\p{L}\p{N}])(?:19|20)[0-9]{2}(?![\p{L}\p{N}])/u', '', $p));
        if ($p === '' || count($v['studios']) >= 10)
            continue;
        if ($plus !== '')
        {
            if (!in_array($plus, $v['studios'], true))
                $v['studios'][] = $plus;
            continue;
        }
        $k = aio_voices_key($p);
        // A common word of voices.php ("Inter" of "International") is none.
        // A dropped name, or the part of one left by noise words.
        $sk = ' ' . aio_voices_skip_key($p) . ' ';
        foreach ($sk === '  ' ? array() : $skip as $x)
        {
            if (strpos($x, $sk) !== false)
                continue 2;
        }
        if (isset($dict[$k]) && $dict[$k] === '' && !isset($own[$k]))
            continue;
        $g = str_replace(' ', '', $k);
        $pv = isset($own[$k]) ? array('studios' => array($own[$k])) : aio_voices_parse($p, false);
        // An exact key only as written: "HDR" is no "HDr" of HDRezka.
        if (!$pv['studios'] && isset($glued[$g]) && $glued[$g] !== '' && (!isset($exact[$g]) || $exact[$g] === $p))
            $pv['studios'] = array($glued[$g]);
        if ($pv['studios'])
        {
            foreach ($pv['studios'] as $st)
            {
                if (!in_array($st, $v['studios'], true))
                    $v['studios'][] = $st;
            }
            continue;
        }
        // Not in the dictionary: a name as written, unless it is a file name
        // ("Брат.1997.HDTV.1080p", "4ol2bwkl", "128kbs"), two letters ("HE"),
        // letters of two scripts in a word (look-alike Latin "Пepeвog"), or of a
        // foreign track.
        if (!$foreign && aio_voices_as_written($p))
            $v['studios'][] = aio_cut(aio_voices_person($p), 40);
    }
    return $v;
}

// A side of brackets in a track title: array('studios' => of voices.php and
// common words of a track ("Україна"), 'n' => names, 'other' => whether one is
// not a studio).
function aio_voices_side($s)
{
    static $own = null;
    static $noise = null;
    if ($own === null)
    {
        $own = aio_voices_own();
        $noise = aio_voices_noise();
    }
    $v = array('studios' => array(), 'n' => 0, 'other' => false);
    foreach (aio_voices_items($s) as $x)
    {
        $x = aio_voices_unwrap($x);
        $k = aio_voices_key($x);
        $pv = isset($own[$k]) ? array('types' => array(), 'studios' => array($own[$k])) : aio_voices_parse($x, false);
        // Types ("MVO Russian"), noise words, a file part are no names.
        $named = false;
        foreach (explode(' ', $k) as $w)
            $named = $named || (!isset($noise[$w]) && !aio_voices_word($w, $w));
        if (!$pv['studios'] && (!$named || !aio_voices_as_written($x)))
            continue;
        $v['n']++;
        $v['studios'] = array_merge($v['studios'], $pv['studios']);
        $v['other'] = $v['other'] || !$pv['studios'];
    }
    return $v;
}

// Whether side $a of the brackets is the cast of studio side $b: no studio
// against some, or a list of names (one not a studio: JacRed knows a few nicks)
// against one studio.
function aio_voices_cast($a, $b)
{
    return ($b['studios'] && !$a['studios']) ||
        (count($b['studios']) === 1 && $b['n'] === 1 && $a['n'] >= 2 && $a['other']);
}

// Names of a list: "A & B, C и D".
function aio_voices_items($s)
{
    $out = array();
    foreach (preg_split('~\s*[|,;/]\s*~u', $s) as $x)
    {
        foreach (aio_voices_names($x) as $y)
        {
            if (preg_match('/[\p{L}\p{N}]/u', $y))
                $out[] = $y;
        }
    }
    return $out;
}

// Two names in one part of a title or a name list: by "&" and " + " unless the
// whole part is one name ("kubik&ko"); by "и", "с", "and" only if each side is
// a known name or a person ("Гланц и Королёва", not "Иванов и сыновья").
function aio_voices_names($p)
{
    static $dict = null;
    static $own = null;
    if ($dict === null)
    {
        $dict = aio_voices_dict();
        $own = aio_voices_own();
    }
    $k = aio_voices_key($p);
    if (isset($dict[$k]) || isset($own[$k]))
        return array($p);
    $out = array();
    $parts = array();
    foreach (preg_split('~\s*(?:&|\s\+\s)\s*~u', $p) as $x)
    {
        // "J&N union" is one name.
        if (preg_match('/\p{L}/u', $x) && !preg_match('/\p{L}.*\p{L}/su', $x))
            return array($p);
        if (trim($x) !== '')
            $parts[] = $x;
    }
    foreach ($parts as $x)
    {
        $y = preg_split('~\s+(?:и|с|and)\s+~u', trim($x));
        $known = count($y) > 1;
        foreach ($y as $z)
        {
            $kz = aio_voices_key($z);
            $known = $known && ((isset($dict[$kz]) && $dict[$kz] !== '') || isset($own[$kz]) ||
                preg_match('/^\p{Lu}\.\s*\p{Lu}\p{Ll}+$/u', trim($z)) || aio_voices_person($z) !== $z);
        }
        $out = array_merge($out, $known ? $y : array($x));
    }
    return $out;
}

// Key of a name to skip: one-letter words out ("Trina D", the "D" is a type).
function aio_voices_skip_key($s)
{
    return trim(preg_replace('/(?:^|\s)\p{L}(?=\s|$)/u', '', aio_voices_key($s)));
}

// First names of people of the voices, lower case, е for ё.
function aio_voices_first_names()
{
    static $first = array('александр' => 1, 'алексей' => 1, 'анастасия' => 1, 'андрей' => 1, 'анна' => 1,
        'антон' => 1, 'артем' => 1, 'борис' => 1, 'вадим' => 1, 'валерий' => 1, 'вартан' => 1, 'василий' => 1,
        'виктор' => 1, 'владимир' => 1, 'всеволод' => 1, 'вячеслав' => 1, 'геннадий' => 1, 'григорий' => 1,
        'денис' => 1, 'дмитрий' => 1, 'евгений' => 1, 'евгения' => 1, 'екатерина' => 1, 'елена' => 1, 'иван' => 1,
        'игорь' => 1, 'инна' => 1, 'ирина' => 1, 'кирилл' => 1, 'константин' => 1, 'леонид' => 1, 'максим' => 1,
        'маргарита' => 1, 'марина' => 1, 'мария' => 1, 'михаил' => 1, 'наталья' => 1, 'николай' => 1,
        'олег' => 1, 'ольга' => 1, 'павел' => 1, 'петр' => 1, 'роман' => 1, 'светлана' => 1, 'сергей' => 1,
        'татьяна' => 1, 'юлия' => 1, 'юрий' => 1, 'ярослав' => 1);
    return $first;
}

// "Андрей Федоров" -> "А. Федоров": a person whose surname voices.php knows,
// as the dictionary writes people. Else as it is.
function aio_voices_person($p)
{
    static $dict = null;
    static $own = null;
    static $first = null;
    if ($dict === null)
    {
        $dict = aio_voices_dict();
        $own = aio_voices_own();
        $first = aio_voices_first_names();
    }
    if (!preg_match('/^(\p{Lu})(\p{Ll}+)\s+(\p{Lu}\p{Ll}+(?:-\p{Lu}\p{Ll}+)?)$/u', trim($p), $m) ||
        !isset($first[mb_strtolower(str_replace('ё', 'е', $m[1] . $m[2]), 'UTF-8')]))
        return $p;
    $k = aio_voices_key($m[3]);
    return (isset($dict[$k]) && $dict[$k] !== '') || isset($own[$k]) ? "{$m[1]}. {$m[3]}" : $p;
}

// Not studios: languages, words about a translation, a source, a file or an
// edition, typos of them. Words of tracks and of the voice lists of names.
function aio_voices_noise()
{
    static $noise = array('russian' => 1, 'english' => 1, 'ukrainian' => 1, 'rus' => 1, 'eng' => 1, 'ukr' => 1,
        'русский' => 1, 'русская' => 1, 'рус' => 1, 'украинский' => 1, 'украинская' => 1, 'укр' => 1,
        'український' => 1, 'українська' => 1, 'английский' => 1, 'англ' => 1, 'закадровый' => 1,
        'закадровая' => 1, 'профессиональный' => 1, 'профессиональная' => 1, 'прфессиональный' => 1,
        'любительский' => 1, 'любительская' => 1, 'перевод' => 1, 'озвучка' => 1, 'озвучивание' => 1,
        'озвучание' => 1, 'лицензия' => 1, 'лицензионный' => 1, 'license' => 1, 'ліцензія' => 1, 'lic' => 1,
        'track' => 1, 'audio' => 1, 'аудио' => 1, 'дорожка' => 1, 'custom' => 1, 'compatibility' => 1,
        'студия' => 1, 'студія' => 1, 'ru' => 1, 'ua' => 1, 'en' => 1, 'hd' => 1, 'bd' => 1, 'cee' => 1, 'blu' => 1,
        'ray' => 1, 'bluray' => 1, 'web' => 1, 'dl' => 1, 'bdremux' => 1, 'remux' => 1, 'bdrip' => 1, 'webrip' => 1,
        'hdrip' => 1, 'источник' => 1, 'online' => 1, 'kb' => 1, 'кбит' => 1, 'кбитс' => 1, 'поздний' => 1,
        'ранний' => 1, 'old' => 1, 'без' => 1, 'цензуры' => 1, 'express' => 1, 'proper' => 1, 'prorer' => 1,
        'repack' => 1, 'uhd' => 1, 'eur' => 1, 'fixed' => 1, 'cin' => 1, 'aka' => 1, 'surround' => 1,
        'channels' => 1, 'channel' => 1, 'new' => 1, 'mov' => 1, 'ukranian' => 1,
        // Sources, encoders, trackers (release names checked 07.10.2026).
        'itunes' => 1, 'vhs' => 1, 'dvd' => 1, 'dvd9' => 1, 'dvd5' => 1, 'r5' => 1, 'censor' => 1, 'korsars' => 1,
        'rgb' => 1, 'miramax' => 1, 'rusatmos' => 1, 'atmos' => 1, 'atmosphere' => 1, 'dolby' => 1,
        'licence' => 1, 'stream' => 1, 'official' => 1, 'переиздание' => 1, 'lfe' => 1, 'abr' => 1, 'cbr' => 1,
        'qaac' => 1, 'mpeg' => 1, 'avg' => 1, 'fix' => 1, 'russia' => 1, 'ukraine' => 1, 'ver' => 1,
        'delay' => 1, 'kbps' => 1, 'kbs' => 1, 'line' => 1, 'hdr' => 1, 'bdrus' => 1, 'kyberpunk' => 1,
        // Codes of languages, audio formats.
        'fra' => 1, 'fre' => 1, 'spa' => 1, 'ger' => 1, 'deu' => 1, 'ita' => 1, 'jpn' => 1, 'kor' => 1, 'chi' => 1,
        'zho' => 1, 'por' => 1, 'pol' => 1, 'tur' => 1, 'heb' => 1, 'ara' => 1, 'hin' => 1, 'swe' => 1,
        'nor' => 1, 'dan' => 1, 'fin' => 1, 'dut' => 1, 'nld' => 1, 'hun' => 1, 'cze' => 1, 'ces' => 1,
        'gre' => 1, 'ell' => 1, 'kaz' => 1, 'uzb' => 1, 'tha' => 1, 'vie' => 1, 'dts' => 1, 'ac3' => 1,
        'eac3' => 1, 'aac' => 1, 'flac' => 1, 'truehd' => 1, 'dd' => 1, 'ddp' => 1, 'mono' => 1, 'stereo' => 1,
        'ch' => 1, 'khz' => 1, 'lpcm' => 1, 'pcm' => 1, 'mp3' => 1, 'opus' => 1,
        // Words of a translation, a type, an edition or no name: "полное
        // дублирование", "Професійний двоголосий", "Неизвестный", "Чистый звук",
        // "Перевод с английского", "MVO с цензурой".
        'полное' => 1, 'полный' => 1, 'полная' => 1, 'старый' => 1, 'кинотеатральный' => 1, 'чистый' => 1,
        'звук' => 1, 'вставки' => 1, 'вставок' => 1, 'вставками' => 1, 'английского' => 1, 'японского' => 1,
        'цензурой' => 1, 'професійний' => 1, 'професійна' => 1, 'закадровий' => 1, 'закадрова' => 1,
        'неизвестный' => 1, 'неизвестная' => 1, 'неизвестно' => 1, 'невідомий' => 1, 'для' => 1, 'заказу' => 1,
        // A first name left of "Дмитрий «Гоблин» Пучков".
        'дмитрий' => 1, 'петр' => 1);
    return $noise;
}

// Studios of an audio track that are common words in release names: TV
// channels (Інтер of International, a country, "Карусель"), studios named as
// words (Tycoon, Twister), people of a pair ("Гланц и Королёва"), "Гоблина".
function aio_voices_own()
{
    static $own = array('інтер' => 'Інтер', 'интер' => 'Інтер', 'україна' => 'Україна', 'украина' => 'Україна',
        'ukraina' => 'Україна', 'карусель' => 'Карусель', 'домашний' => 'Домашний', 'tycoon' => 'Tycoon',
        'twister' => 'Twister', 'octopus' => 'Octopus', 'мосфильм' => 'Мосфильм',
        'союзмультфильм' => 'Союзмультфильм', 'пятница' => 'Пятница', 'тв 3' => 'ТВ-3', 'тв3' => 'ТВ-3',
        'tv3' => 'ТВ-3', 'tb 3' => 'ТВ-3', 'tb3' => 'ТВ-3', 'че' => 'Че!', 'телеканал че' => 'Че!',
        'королева' => 'И. Королёва', 'инна королева' => 'И. Королёва', 'и королева' => 'И. Королёва',
        'i koroleva' => 'И. Королёва', 'koroleva' => 'И. Королёва', 'казакова' => 'Т. Казакова',
        'т казакова' => 'Т. Казакова', 't kazakova' => 'Т. Казакова', 'гоблина' => 'Д. Пучков');
    return $own;
}

// A part without the words around a name: a customer ("для ТВ3", "по заказу
// РТР"), "по переводу Гоблина", a channel ("т/к", "телеканал").
function aio_voices_unwrap($p)
{
    return preg_replace('~^(?:(?:для|по\s+заказу|по\s+переводу|т\s*/\s*к|тк|телеканал|канал)\s+)+|\s+по$~iu', '',
        $p);
}

// Text where names are shown as written, without HTML ("<b>LostFilm</b>",
// "&amp;") and pictographs ("MVO 🎬 Кубик"), as aio_plain_name.
function aio_voices_clean($s)
{
    $s = preg_replace('/&#?[A-Za-z0-9]+;/', ' ', html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
    $s = preg_replace('~</?(?:a|b|i|u|s|em|strong|span|font|br|p|div|small|big|sup|sub)(?![\p{L}\p{N}])[^<>]*>~iu',
        ' ', $s);
    // "№" stays: "Божья искра №1".
    return preg_replace('/(?:(?!\x{2116})[\p{So}\x{1F000}-\x{1FFFF}\x{FE0F}\x{200D}])+/u', ' ', $s);
}

// A part not in voices.php may be shown as written: three letters at least, no
// file name, year, resolution, bit rate, letters of two scripts in a word, no
// patronymic ("Юрий Владимирович" of "Сербин, Юрий Владимирович").
function aio_voices_as_written($p)
{
    static $first = null;
    if ($first === null)
        $first = aio_voices_first_names();
    if (preg_match('/^(?:(\p{Lu}\p{Ll}+)\s+)?\p{Lu}\p{Ll}+(?:ович|евич|ьич|овна|евна|ична)$/u', trim($p), $m) &&
        (!isset($m[1]) || $m[1] === '' || isset($first[mb_strtolower(str_replace('ё', 'е', $m[1]), 'UTF-8')])))
        return false;
    return preg_match('/\p{L}.*\p{L}.*\p{L}/su', $p) && !preg_match('/^\p{Ll}\.?\s/u', $p) &&
        !preg_match('/(?:19|20)[0-9]{2}|[0-9]{3,4}p|\..*\..*\.|[0-9]+\s*kb/i', $p) &&
        !preg_match('/^(?=.*[0-9][a-z])[a-z0-9]{8}$/', $p) &&
        !preg_match('/\p{Cyrillic}[A-Za-z]|[A-Za-z]\p{Cyrillic}/u', $p);
}

// Codes of rutor: type ids.
function aio_voices_codes()
{
    static $codes = array('D' => 'dub', 'P' => 'mvo', 'L' => 'mvo', 'P2' => 'dvo', 'L2' => 'dvo', 'P1' => 'avo',
        'L1' => 'avo', 'A' => 'avo');
    return $codes;
}

// Studios not in voices.php in the voice lists of a release name, after its
// first year: in brackets right after a type ("ЛД (AlisaDirilis)", "ПМ (Нота)",
// "MVO (TVShows, WinMedia)") and in the part after rutor's codes ("| L2 |
// AEROChannelEkat & Риша"). -> list of array(name, type id or '').
function aio_voices_name_extra($s)
{
    // Words of an edition or a release after rutor's codes ("| Open Matte").
    static $stop = array('open' => 1, 'matte' => 1, 'imax' => 1, 'cut' => 1, 'remastered' => 1, 'remaster' => 1,
        'version' => 1, 'extended' => 1, 'upscale' => 1, 'hdr10' => 1, 'sdr' => 1, 'vision' => 1, 'fps' => 1,
        'hybrid' => 1, 'theatrical' => 1, 'special' => 1, 'ultimate' => 1, 'collection' => 1, 'criterion' => 1,
        'director' => 1, 'directors' => 1, 'despecialized' => 1, 'uncut' => 1, 'unrated' => 1, 'edition' => 1,
        'anniversary' => 1, 'redux' => 1, 'restoration' => 1, 'restored' => 1, 'rip' => 1, 'от' => 1, 'by' => 1,
        'sub' => 1, 'subs' => 1, 'original' => 1, '4k' => 1, 'bit' => 1, 'версия' => 1, 'издание' => 1,
        'режиссерская' => 1, 'расширенная' => 1, 'театральная' => 1, 'fullscreen' => 1, 'full' => 1,
        'frame' => 1, 'transfer' => 1, 'сжатый' => 1, 'локализованный' => 1, 'видеоряд' => 1);
    // Kept here: a static array returned by a function is copied on every call.
    static $noise = null;
    static $own = null;
    static $dict = null;
    static $codes = null;
    if ($dict === null)
    {
        $noise = aio_voices_noise();
        $own = aio_voices_own();
        $dict = aio_voices_dict();
        $codes = aio_voices_codes();
    }
    $out = array();
    if (strpbrk($s, '(|') === false ||
        !preg_match('/(?<![0-9])(?:19|20)[0-9]{2}(?![0-9])/', $s, $m, PREG_OFFSET_CAPTURE))
        return $out;
    $s = aio_voices_clean(substr($s, $m[0][1] + 4));
    $lists = array();
    if (preg_match_all('/(?<![\p{L}\p{N}])(ДБ|ПМ|ЛМ|ПД|ЛД|АП|ЛО|ПО|Dub|DUB|MVO|DVO|AVO|VO)\s*\(([^()]{1,300})\)/u', $s,
        $mm, PREG_SET_ORDER))
    {
        foreach ($mm as $x)
        {
            $c = aio_voices_word(mb_strtolower($x[1], 'UTF-8'), $x[1]);
            $lists[] = array($x[2], $c && $c[0] === 'type' ? $c[1] : '');
        }
    }
    // Rutor's codes, Cyrillic "А", "Р" too: one code types the names after it.
    if (preg_match('/\|\s*((?:[DPLAАР][12]?\s*,\s*)*[DPLAАР][12]?)\s*\|([^|]*)/u', $s, $x))
    {
        $cs = preg_split('/\s*,\s*/', str_replace(array('А', 'Р'), array('A', 'P'), $x[1]));
        $lists[] = array($x[2], count($cs) === 1 && isset($codes[$cs[0]]) ? $codes[$cs[0]] : '');
    }
    foreach ($lists as $l)
    {
        $items = array();
        // "т/к Нота": the "/" is no list.
        foreach (preg_split('~\s*[|,;/]\s*~u', preg_replace('~(?<!\p{L})т\s*/\s*к\s+~iu', '', $l[0])) as $x)
        {
            // "Black & Chrome Edition" is an edition as a whole.
            foreach (explode(' ', aio_voices_key($x)) as $w)
            {
                if (isset($stop[$w]))
                    continue 2;
            }
            $items = array_merge($items, aio_voices_names($x));
        }
        foreach ($items as $p)
        {
            $p = aio_voices_unwrap(preg_replace('/^[\s"\'\x{00AB}\x{00BB}.\-]+|[\s"\'\x{00AB}\x{00BB}.\-]+$/u', '',
                $p));
            $k = aio_voices_key($p);
            // A common word of voices.php ("Ultradox", "Tycoon") is none.
            if ($k === '' || (isset($dict[$k]) && $dict[$k] === '' && !isset($own[$k])))
                continue;
            if (isset($own[$k]))
            {
                $out[] = array($own[$k], $l[1]);
                continue;
            }
            // A name of voices.php is the parse's already.
            if (isset($dict[$k]))
                continue;
            $pv = aio_voices_parse($p, false);
            if ($pv['studios'] || $pv['types'] || $pv['orig'] !== '' || $pv['subs'] || !aio_voices_as_written($p) ||
                mb_strlen($p, 'UTF-8') > 40)
                continue;
            foreach (explode(' ', $k) as $w)
            {
                if (isset($noise[$w]) || isset($stop[$w]) || preg_match('/^[0-9]+$/', $w))
                    continue 2;
            }
            $out[] = array(aio_voices_person($p), $l[1]);
        }
    }
    return $out;
}

// Voices of a row for the screens, by the audio tracks and the name:
// "Дубляж: Bravo Records, Red Head Sound · LostFilm, Ю. Сербин, Кубик в Кубе ·
// оригинал · субтитры" (aio_voices_struct_text). One person or studio is one
// entry. $subs false: no "субтитры" (GComps has a field of its own).
function aio_row_voices_text($r, $lang, $subs = true)
{
    return aio_voices_struct_text(aio_row_voices_struct($r), $lang, $subs);
}

// What aio_row_voices_text shows, in order: array('groups' => type => studios
// (dub, mvo, dvo, avo; only with a track of a type and a studio), 'types' =>
// types without studios, 'studios' => studios without a type, 'orig' => '' |
// 'orig' | 'jap', 'subs' => bool of the name).
function aio_row_voices_struct($r)
{
    $groups = array();
    $seen = array();
    $types = array();
    $orig = '';
    $nv = $r['voices'];
    // Types the name gives its studios: "DVO (Кубик в Кубе)".
    $nst = array();
    foreach (isset($nv['stype']) ? $nv['stype'] : array() as $st => $type)
        $nst[aio_voices_same($st)] = $type;
    foreach (array_slice($r['tracks'], 0, AIO_VOICES_TRACKS) as $t)
    {
        $v = aio_track_voice($t['title'], $t['lang']);
        if (!$v)
            continue;
        // A track without a type: the type the name gives one of its studios
        // ("Kubik3 / el brujo" of "DVO (Кубик в Кубе)" -> "ПД: Кубик в Кубе, el brujo").
        foreach ($v['types'] ? array() : $v['studios'] as $st)
        {
            if (!$v['types'] && isset($nst[aio_voices_same($st)]))
                $v['types'] = array($nst[aio_voices_same($st)]);
        }
        $foreign = $t['lang'] !== '' && $t['lang'] !== 'RU';
        // Original: by the title, or a foreign track (not UA) that names no studio.
        if ($v['orig'] || (!$v['studios'] && !in_array($t['lang'], array('', 'RU', 'UA'), true)))
        {
            $orig = $orig === 'jap' || $t['lang'] === 'JA' ? 'jap' : 'orig';
            continue;
        }
        // A UA track tells only its studios: its "MVO" is not ours.
        if ($foreign && !$v['studios'])
            continue;
        foreach ($v['types'] ? $v['types'] : array('') as $type)
        {
            $types[$type] = true;
            foreach ($v['studios'] as $st)
            {
                $k = aio_voices_same($st) . ($foreign ? ":{$t['lang']}" : '');
                if (!isset($groups[$type][$k]))
                    $groups[$type][$k] = $st . ($foreign ? " ({$t['lang']})" : '');
                $seen[$k] = true;
            }
        }
    }
    unset($types['']);
    // A studio of the name at the end of a longer word of another studio is
    // JacRed's substring ("Amedia" of "Novamedia", "AniMedia" of "Reanimedia");
    // "Че!" in "Печенька" is not.
    $all = array();
    foreach ($groups as $list)
    {
        foreach ($list as $st)
            $all[] = aio_voices_key($st);
    }
    foreach ($nv['studios'] as $st)
        $all[] = aio_voices_key($st);
    $keep = array();
    foreach ($nv['studios'] as $st)
    {
        $k = aio_voices_key($st);
        $in = false;
        foreach ($all as $o)
            $in = $in || ($o !== $k && preg_match('/\p{L}' . preg_quote($k, '/') . '(?![\p{L}\p{N}])/u', $o));
        if (!$in)
            $keep[] = $st;
    }
    $nv['studios'] = $keep;
    $orig = $orig !== '' ? $orig : $nv['orig'];
    // As aio_voices_text: the types and studios of the name, no groups.
    if (!$groups && !$types)
        return aio_voices_by_tracker(array('groups' => array(), 'types' => $nv['types'], 'studios' => $nv['studios'],
            'orig' => $orig, 'subs' => $nv['subs']), $r);
    // Without a type: no studio of a type, nor one of the name in the tracks.
    $bare = array();
    foreach (isset($groups['']) ? $groups[''] : array() as $k => $st)
    {
        $typed = false;
        foreach ($groups as $type => $list)
            $typed = $typed || ($type !== '' && isset($list[$k]));
        if (!$typed)
            $bare[$k] = $st;
    }
    foreach ($nv['studios'] as $st)
    {
        $k = aio_voices_same($st);
        if (isset($seen[$k]) || isset($bare[$k]))
            continue;
        if (isset($nst[$k]))
            $groups[$nst[$k]][$k] = $st;
        else
            $bare[$k] = $st;
        $seen[$k] = true;
    }
    $out = array();
    foreach (array('dub', 'mvo', 'dvo', 'avo') as $type)
    {
        if (!empty($groups[$type]))
            $out[$type] = array_values($groups[$type]);
    }
    // Types of the name and the tracks without a group of studios.
    $rest = array();
    foreach (array_merge($nv['types'], array_keys($types)) as $type)
    {
        if (!isset($out[$type]))
            $rest[$type] = $type;
    }
    return aio_voices_by_tracker(array('groups' => $out, 'types' => array_values($rest),
        'studios' => array_values($bare), 'orig' => $orig, 'subs' => $nv['subs']), $r);
}

// A release of a tracker of one voice studio, anywhere in streamData.indexer of
// AIOStreams ("kinozal, rudub"), whose names and tracks name no studio: that
// studio. BaibaKo's "(УКР.)" is its Ukrainian voice.
function aio_voices_by_tracker($v, $r)
{
    static $studio = array('lostfilm' => 'LostFilm', 'baibako' => 'BaibaKo', 'leproduction' => 'LE-Production',
        'anidub' => 'AniDUB', 'aniliberty' => 'AniLiberty', 'anistar' => 'AniStar', 'rudub' => 'RuDub');
    if ($v['groups'] || $v['studios'] || !isset($r['trackers']))
        return $v;
    foreach ($r['trackers'] as $t)
    {
        // "LE-Production" -> "leproduction".
        $t = preg_replace('/[^a-z]/', '', strtolower($t));
        if (isset($studio[$t]))
        {
            $ua = $t === 'baibako' && !empty($r['voices']['ua']);
            $v['studios'][] = $studio[$t] . ($ua ? ' (UA)' : '');
            break;
        }
    }
    return $v;
}

// aio_row_voices_struct() for the screens: of the types only the dub, "Дубляж:
// Red Head Sound · LostFilm, HDRezka, Ю. Сербин · оригинал JAP · субтитры"
// ("Дубляж" alone without its studios); the other studios in one list, groups
// in order, then those without a type, no repeats; bare types (ПМ, АП) not
// shown. '' if empty.
function aio_voices_struct_text($v, $lang, $subs = true)
{
    $w = aio_voices_words($lang);
    $p = array();
    // The struct has one name a studio already: repeats by the name shown.
    $seen = array();
    if (!empty($v['groups']['dub']))
    {
        $p[] = $w['dub'] . ': ' . implode(', ', $v['groups']['dub']);
        foreach ($v['groups']['dub'] as $st)
            $seen[$st] = true;
    }
    else if (in_array('dub', $v['types'], true))
        $p[] = $w['dub'];
    $rest = array();
    foreach ($v['groups'] as $list)
    {
        foreach ($list as $st)
            $rest[] = $st;
    }
    $list = array();
    foreach (array_merge($rest, $v['studios']) as $st)
    {
        if (!isset($seen[$st]))
            $list[] = $st;
        $seen[$st] = true;
    }
    $p[] = implode(', ', $list);
    if ($v['orig'] !== '')
        $p[] = $w['orig'] . ($v['orig'] === 'jap' ? ' JAP' : '');
    if ($v['subs'] && $subs)
        $p[] = $w['subs'];
    return aio_cut(implode(' · ', array_filter($p, 'strlen')), AIO_TEXT_MAX);
}

// Voices of the names of a row and the name shown without JacRed's tails;
// only for the rows listed (two parses a row).
function aio_row_voices($r)
{
    $p = array();
    foreach ($r['names'] as $nm)
    {
        if ($nm !== '' && !isset($p[$nm]))
            $p[$nm] = aio_voices_parse($nm);
    }
    $r['voices'] = aio_voices($p);
    // BaibaKo's Ukrainian voice: "(УКР.)" in the folder or the file name.
    if (preg_match('/\(УКР\.?\)/u', implode(' ', $r['names'])))
        $r['voices']['ua'] = true;
    if (isset($p[$r['label']]) && $p[$r['label']]['name'] !== '')
        $r['label'] = $p[$r['label']]['name'];
    unset($r['names']);
    return $r;
}

// One release for offline checks, not used
// by the plugin: its names (torrent name; folder and file) and audio tracks as
// aio_tracks() makes them ('lang' => 'RU', 'title' => ...), trackers (indexer)
// -> aio_row_voices_struct().
function aio_voices_release($names, $tracks, $trackers = array())
{
    return aio_row_voices_struct(aio_row_voices(array('names' => $names, 'label' => $names ? $names[0] : '',
        'tracks' => $tracks, 'trackers' => $trackers)));
}
