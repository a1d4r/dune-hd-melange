<?php
// "Онлайн" on the Dune, movies and episodes: the client is lampac.php. Here (the window "Ищу онлайн…" of its search:
// online_search.php; a profile of Lampac of "Источник" of MENU: sources.php) the online list
// on the screen of the releases (the frame of view_gcomps.php, rows "Phantom · Дублированный"
// and a card of their own; of an episode a row a voice of its season), its MENU, ENTER (the start:
// online_play.php, as "Continue" of a record of it and play_action), the left and right keys and
// "Выбрать серию" of an episode, "Балансеры" (which balancers, their order: lampac_sources of the profile of
// Lampac; online_sources.php), the filter by voices (its panel "Озвучка": online_voices.php), the balancers by
// quality (the yellow key, mel_lpv_qsort) and the window of no such episode in the voice. The mark "online" of a title
// (data_dir/lampac_choice.json) is gone (v2): a record of the history leads to the online list.
// The list lives in Mel::$state beside the releases: 'lp' (MelLpView), 'lp_on' - the screen
// shows it, 'lp_only' - the releases were never asked, 'lp_note' - the yellow line of the
// releases; 'lp_prof' - the profile of Lampac of 'lp', 'prof' - of releases of 'rows' (v2, sources.php). The name of
// the profile of Lampac only in the card of a row ("Через: Lampac").
// PHP 5.3.6, firmware API only. Types (MelLpView, MelLpRun): phpstan-types.neon.

// Seconds between two searches of "Обновить": a poll of all balancers often gets the IP banned.
define('MEL_LPV_REFRESH', 30);
// Width of the column of the balancers at most, px.
define('MEL_LPV_SRC_W', 320);
// A row under the header of its balancer: its voice indented, px.
define('MEL_LPV_INDENT', 48);

class MelLpv
{
    // The search of the window on screen (mel_lpv_search_open), null none.
    /** @var MelLpRun|null */
    public static $run = null;
    // The TMDB and KinoPoisk ids and the original language of the cards of play_action (movie_str: tmdb_id or
    // tmdbtv_id, kp_id, original_language) by dune id or IMDb: their search needs no /externalids; the title of Lampac
    // of their search (fix_title, mel_lp_search_start), when it gave one; the KinoPoisk id of the catalog server when
    // Lampac gave none (mel_lpv_kp_catalog). Memory only.
    /** @var array<string, MelLpvIds> */
    public static $ids = array();
    // The titles ("movie:tt…", "series:tmdb:…") whose KinoPoisk id the catalog server did not give (mel_lpv_kp_catalog):
    // not asked again. Memory only.
    /** @var array<string, bool> */
    public static $kp_miss = array();
    // The season of the last start of an episode (online_play.php): the dune id of its card (a key of
    // a voice is the same for two series), its key (mel_lp_season_key), s, the row of the voice, the
    // season in it (MelLpSeason), the id of the profile of Lampac ('' none: one of another server does not fit) - the
    // next episodes of its playlist take their link from it (lampac_next:). One, memory only.
    /** @var array{k: string, key: string, s: int, prof: string, row: MelLpRow, sea: MelLpSeason}|null */
    public static $season = null;
    // The episode a lazy one of the playlist played last (mel_lpv_next): the rid of the online list
    // under the player, s, e; back from the player the list goes to it (mel_lpv_finish). null none.
    /** @var array{rid: string, s: int, e: int}|null */
    public static $played = null;
    // The search window of "Балансеры" on screen (mel_lpv_srcs_apply): its sid and the list of the new choice, put in
    // when the window closes with no list of its own (Cancel, RETURN, no answer, nothing: mel_lpv_redraw). null none.
    /** @var array{sid: string, st: MelState}|null */
    public static $redraw = null;
    // The balancers by quality (mel_lpv_qsort), null not read yet.
    /** @var bool|null */
    public static $qsort = null;
}

// "Онлайн" for card $mv, episode $e of season $s (-1: none): a Lampac in the settings; of a series
// an episode (its season asked).
/**
 * @param MelMovie $mv
 * @param int $s
 * @param int $e
 * @return bool
 */
function mel_lpv_on($mv, $s = -1, $e = -1)
{
    return Mel::$settings['lp'] !== '' && (!$mv['series'] || ($s >= 1 && $e >= 1));
}

// --- The title.

// The key of a card: of the Dune, else IMDb, else its TMDB (lpv_key of mel_movie: "tmdb:<id>", "tmdbtv:<id>"); '' none.
/**
 * @param MelMovie $mv
 * @return string
 */
function mel_lpv_key($mv)
{
    if ($mv['dune_id'] !== '')
        return $mv['dune_id'];
    return $mv['imdb'] !== '' ? $mv['imdb'] : (isset($mv['lpv_key']) ? $mv['lpv_key'] : '');
}

// The parameters of Lampac of card $mv (mel_lp_title): its title the one of Lampac kept (MelLpv::$ids) when that replaced
// this very title (the own card and the native one of the Dune share the key, not the title), else its own.
/**
 * @param MelMovie $mv
 * @return MelLpTitle
 */
function mel_lpv_title($mv)
{
    $k = mel_lpv_key($mv);
    $ids = isset(MelLpv::$ids[$k]) ? MelLpv::$ids[$k] : array();
    if (!isset($ids['title_of']) || $ids['title_of'] !== mel_lp_text($mv['title']))
        unset($ids['title']);
    unset($ids['title_of']);
    return mel_lp_title($ids + array('imdb' => $mv['imdb'], 'title' => $mv['title'], 'original_title' => $mv['native'],
        'year' => $mv['year'], 'serial' => $mv['series']));
}

// --- Words.

/**
 * @param string $lang
 * @param string $key
 * @param array<string, string|int> $sub
 * @return string
 */
function mel_lpv_tr($lang, $key, $sub = array())
{
    $s = mel_tr($lang, $key);
    foreach ($sub as $k => $v)
        $s = str_replace('{' . $k . '}', strval($v), $s);
    return $s;
}

// The words of reason $why (MelLpWhy codes, lampac.php), the address masked. The detail only where
// the text has {d} and it is short for people (http: the code, accsdb: the message of the
// provider); a curl error, an unknown type or method - only in the log (mel_lp_call, mel_lp_replies).
/**
 * @param string $lang
 * @param string $why
 * @param string $detail
 * @return string
 */
function mel_lpv_why($lang, $why, $detail)
{
    $key = "lp_why_$why";
    $s = mel_tr($lang, $key);
    if ($s === $key)
        $s = mel_tr($lang, 'lp_why_other');
    if ($detail === '')
        return str_replace(array(' ({d})', ': {d}', ' {d}'), '', $s);
    $detail = mel_mask(mel_lp_mask($detail, Mel::$settings['lp']));
    return str_replace('{d}', mel_cut(mel_one_line($detail), 80), $s);
}

// The yellow line of the releases of list $st (lp_note: "Онлайн не отвечает — показаны раздачи") in the row of the title
// (mel_gc_head, view_gcomps.php), '' none.
/**
 * @param MelState $st
 * @return string
 */
function mel_lpv_note($st)
{
    return !empty($st['lp_on']) || empty($st['lp_note']) ? '' : strval($st['lp_note']);
}

// --- The online list on screen.

// The plate b2 of the quality a balancer claims ("2160p", "1080p", "4K"): its id (k_glyph_<id>), '' none.
/**
 * @param string $q
 * @return string
 */
function mel_lpv_badge($q)
{
    // 8K (4320p): no plate.
    $p = mel_lp_q_num($q);
    $id = $p === 2160 ? '4k' : ($p > 0 ? "{$p}p" : '');
    $pics = mel_k_pic();
    return isset($pics["k_glyph_$id"]) ? $id : '';
}

// --- The filter by voices (the red key, "Озвучка" of "Действия"): the rows of the voices chosen of all balancers. Its state
// 'flt' of the view: the keys of the voices (mel_lpv_voice_key) => their names, in the order of mel_lpv_voices when
// chosen; none: all. It lives with the list of the title (between the episodes too), not past it.

// The key of voice $name for the filter: its studios (mel_track_voice, by mel_voices_same: "Кубик в кубе" and
// "Кубик в Кубе" one), else a dub ("Дубляж [1080, Rus]" and "Дублированный" one; other types by the name:
// "Профессиональный многоголосый" is no "Любительский многоголосый"), else the original, else the name itself; a
// Ukrainian one apart (not by its subtitles: "Оригинал (укр. субтитры)" is the original).
/**
 * @param string $name
 * @return string
 */
function mel_lpv_voice_key($name)
{
    static $memo = array();
    if (isset($memo[$name]))
        return $memo[$name];
    $t = mel_track_voice($name);
    $ua = preg_match('/укр|ukr|україн/iu', preg_replace('/\S*\s*(?:суб|sub)\S*/iu', ' ', $name)) ? ':ua' : '';
    if ($t && $t['studios'])
    {
        $ks = array();
        foreach ($t['studios'] as $x)
            $ks[] = mel_voices_same($x);
        sort($ks);
        $k = 's:' . implode('|', $ks) . $ua;
    }
    else if ($t && $t['types'] === array('dub'))
        $k = 't:dub' . $ua;
    else if ($t && $t['orig'])
        $k = 'o' . $ua;
    else
        $k = 'n:' . mel_voices_key($name);
    if (count($memo) >= 1000)
        $memo = array();
    return $memo[$name] = $k;
}

// The voices of row $r by their key (mel_lpv_voice_key) -> name: its voice, else its tracks (Collaps).
/**
 * @param MelLpRow $r
 * @return array<string, string>
 */
function mel_lpv_row_voices($r)
{
    $out = array();
    foreach ($r['voice'] !== '' ? array($r['voice']) : (isset($r['tracks']) ? $r['tracks'] : array()) as $name)
    {
        $k = mel_lpv_voice_key($name);
        if (!isset($out[$k]))
            $out[$k] = $name;
    }
    return $out;
}

// The voices of the rows of view $v for the menu of the filter: k (the key), name (of the first row, a dub of no
// studio "Дубляж"), n (its balancers); by n, else in the order of Lampac.
/**
 * @param MelLpView $v
 * @param string $lang
 * @return list<array{k: string, name: string, n: int}>
 */
function mel_lpv_voices($v, $lang)
{
    $g = array();
    foreach ($v['rows'] as $r)
    {
        foreach (mel_lpv_row_voices($r) as $k => $name)
        {
            if (!isset($g[$k]))
                $g[$k] = array('k' => $k, 'name' => $k === 't:dub' ? mel_tr($lang, 'voices_dub') : $name, 'p' => array(), 'at' => count($g));
            $g[$k]['p'][$r['path']] = true;
        }
    }
    usort($g, function ($a, $b)
    {
        return count($a['p']) !== count($b['p']) ? count($b['p']) - count($a['p']) : $a['at'] - $b['at'];
    });
    $out = array();
    foreach ($g as $x)
        $out[] = array('k' => $x['k'], 'name' => $x['name'], 'n' => count($x['p']));
    return $out;
}

// The rows of view $v the screen shows: of a voice of its filter (a row of tracks: one of them), else all; by quality
// (mel_lpv_qsort, mel_lpv_by_quality) or as the rows are.
/**
 * @param MelLpView $v
 * @return list<int>
 */
function mel_lpv_shown($v)
{
    $out = array();
    foreach ($v['rows'] as $i => $r)
    {
        if (!isset($v['flt']) || array_intersect_key(mel_lpv_row_voices($r), $v['flt']))
            $out[] = $i;
    }
    return mel_lpv_qsort() ? mel_lpv_by_quality($v, $out) : $out;
}

// --- The order of the balancers (the yellow key, MENU "Сортировать по качеству" / "Порядок как в Балансерах"): as the rows
// are (the order of "Балансеры", mel_lpv_srcs_pick), or by quality. One for all the lists, kept in data_dir/lampac_sort
// ("quality"; none or else: as the rows are), not settings.json (the page writes that one); the rows stay as they are.

// The balancers by quality.
/**
 * @return bool
 */
function mel_lpv_qsort()
{
    if (MelLpv::$qsort === null)
    {
        $f = mel_data_dir() . '/' . MEL_LP_SORT_FILE;
        MelLpv::$qsort = mel_data_dir() !== '' && is_file($f) &&
            trim((string) @file_get_contents($f, false, null, 0, 16), MEL_TRIM) === 'quality';
    }
    return MelLpv::$qsort;
}

// The balancers by quality $on or not, for this run of the plugin; false - not kept in data_dir (the caller logs it).
/**
 * @param bool $on
 * @return bool
 */
function mel_lpv_qsort_set($on)
{
    MelLpv::$qsort = $on;
    return mel_data_dir() !== '' && mel_write_file(mel_data_dir() . '/' . MEL_LP_SORT_FILE, $on ? 'quality' : 'sources');
}

// Rows $ids of view $v by the quality of their balancers: the best of its rows there (mel_lp_q_num, what its line shows,
// mel_lpv_lines) first, of none ("1080" too) last, of one quality in the order of $ids; the rows of a balancer in their
// order, one after another.
/**
 * @param MelLpView $v
 * @param list<int> $ids
 * @return list<int>
 */
function mel_lpv_by_quality($v, $ids)
{
    $by = array();
    foreach ($ids as $i)
    {
        $p = $v['rows'][$i]['path'];
        if (!isset($by[$p]))
            $by[$p] = array(0, count($by), array());
        $by[$p][0] = min($by[$p][0], -mel_lp_q_num($v['rows'][$i]['quality']));
        $by[$p][2][] = $i;
    }
    // By the quality, then by the first place: the rows never compared.
    sort($by);
    $out = array();
    foreach ($by as $b)
        $out = array_merge($out, $b[2]);
    return $out;
}

// lp_qsort of the online list $st: the balancers by quality or as the rows are, for all the lists (mel_lpv_qsort_set); the
// cursor on the first line. Folded, unfolded and the filter as they were.
/**
 * @param MelState $st
 * @return MelAction
 */
function mel_lpv_qsort_flip($st)
{
    $on = !mel_lpv_qsort();
    $tag = $on ? 'by quality' : 'as the sources';
    if (!mel_lpv_qsort_set($on))
        mel_log("lampac: balancers $tag not saved, till the plugin restarts");
    $ns = $st;
    $ns['rid'] = mel_rid();
    // The cursor on the first line, as after "Обновить" (mel_lpv_result).
    $L = mel_lpv_lines($ns['lp']);
    $ns['lp']['gc'] = $L ? mel_lpv_put($ns['lp'], $L, $L[0]['i']) : array(0, 0);
    $ns['lp']['gc_new'] = true;
    mel_log("lampac: balancers $tag");
    return mel_list_replace($ns);
}

// The voice of row $r of view $v on screen: its voice; of tracks (Collaps) the one track, the one of the filter
// when one of them only is in it ("LostFilm — дорожкой в плеере"), else "Дорожками в плеере · 7"; none "—".
/**
 * @param MelLpView $v
 * @param MelLpRow $r
 * @param string $lang
 * @return string
 */
function mel_lpv_voice_text($v, $r, $lang)
{
    $tr = isset($r['tracks']) ? $r['tracks'] : array();
    if ($r['voice'] !== '' || count($tr) === 1)
        return $r['voice'] !== '' ? $r['voice'] : $tr[0];
    $vs = $tr && isset($v['flt']) ? array_intersect_key(mel_lpv_row_voices($r), $v['flt']) : array();
    if (count($vs) === 1)
        return mel_lpv_tr($lang, 'lp_track_one', array('name' => reset($vs)));
    return $tr ? mel_lpv_tr($lang, 'lp_tracks', array('n' => count($tr))) : "\xE2\x80\x94";
}

// View $v with the tracks of its rows (Collaps: tracks_by, mel_lp_season_rows) of episode $e, none of an episode
// not there.
/**
 * @param MelLpView $v
 * @param int $e
 * @return MelLpView
 */
function mel_lpv_ep_tracks($v, $e)
{
    foreach ($v['rows'] as $i => $r)
    {
        if (isset($r['tracks_by']))
            $v['rows'][$i]['tracks'] = isset($r['tracks_by'][$e]) ? $r['tracks_by'][$e] : array();
    }
    return $v;
}

// View $v of list $st with its filter when a row has a voice of it (those there shown); else none, the yellow line
// "В S1 E5 нет LostFilm — показаны все", of more voices "В S1 E5 нет выбранных озвучек — показаны все" (flt_gone: the
// line is of it).
/**
 * @param MelLpView $v
 * @param MelState $st
 * @return MelLpView
 */
function mel_lpv_flt_keep($v, $st)
{
    if (!isset($v['flt']) || mel_lpv_shown($v))
        return $v;
    if (count($v['flt']) === 1)
        $key = $st['e'] > 0 ? 'lp_flt_gone' : 'lp_flt_gone_movie';
    else
        $key = $st['e'] > 0 ? 'lp_flt_gone_many' : 'lp_flt_gone_many_movie';
    $v['note'] = mel_lpv_tr($st['lang'], $key, array('se' => "S{$st['s']} E{$st['e']}", 'voice' => reset($v['flt'])));
    mel_log('lampac: no voice of the filter in ' . mel_ep_tag($st) . ', all shown');
    unset($v['flt']);
    $v['flt_gone'] = true;
    return $v;
}

// View $v with its row $i on screen: the filter dropped when it hides that one (all shown, no yellow line of it).
/**
 * @param MelLpView $v
 * @param int $i
 * @return MelLpView
 */
function mel_lpv_flt_show($v, $i)
{
    if (!isset($v['flt']) || in_array($i, mel_lpv_shown($v), true))
        return $v;
    mel_log("lampac: row $i not of the filter " . implode(', ', array_keys($v['flt'])) . ', all shown');
    unset($v['flt'], $v['flt_gone']);
    return $v;
}

// The name of the filter of view $v: its first voice (cut to $w px of the hints; 0: whole), "+N" of the others
// ("LostFilm +2"); none "все".
/**
 * @param MelLpView $v
 * @param string $lang
 * @param int $w
 * @return string
 */
function mel_lpv_flt_name($v, $lang, $w = 0)
{
    if (!isset($v['flt']))
        return mel_tr($lang, 'lp_flt_any');
    $name = mel_lpv_flt_first($v);
    if ($w > 0)
        $name = MelKit::cut($name, MEL_K_PX_ROW, '', $w);
    return count($v['flt']) > 1 ? mel_lpv_tr($lang, 'lp_flt_more', array('name' => $name, 'n' => count($v['flt']) - 1)) : $name;
}

// The first voice of the filter of view $v (mel_lpv_flt_name: the name of the filter), '' none.
/**
 * @param MelLpView $v
 * @return string
 */
function mel_lpv_flt_first($v)
{
    return isset($v['flt']) ? strval(reset($v['flt'])) : '';
}

// Children of the row of the tabs of the online list $st, lines $L (MelKit::tabs from MEL_GC_LX on MEL_GC_TABS_MID,
// panel 'tabs'), in the order of the colour keys, each as its dot and its label, the one that is on on its plate: the
// filter, "Озвучка: все" or "Озвучка: LostFilm +2" (on), always with the chevron down - it opens a list (no voice in
// the list: none); the green key, "Свернуть все" (mel_lpv_fold_word), an action with no state (no balancer of 2 rows:
// none); the order, "Качество", on by quality (mel_lpv_qsort), no arrow (no row: none). A remote the firmware knows is
// without the colour keys: no dots, no green tab, of the order a state only - "По качеству" on by quality, else none
// (MENU has the items; mel_lpv_can). At the right the name of the source (mel_gc_tabs_from, no number of errors: MENU has them).
// No room: the green one in a short word ("Свернуть"), then the name of the filter shorter, 80 px at least; the order
// always, the name of the source shorter or none then.
/**
 * @param MelState $st
 * @param list<MelLpLine> $L
 * @return list<MelGcComp>
 */
function mel_lpv_tabs($st, $L)
{
    $v = $st['lp'];
    $lang = mel_gc_lang($st);
    $keys = mel_gc_color_keys();
    $right = MEL_GC_LX + MEL_GC_LW - MEL_GC_PAD;
    // What the name of the source takes with its icon (mel_gc_tabs_from), the gap before it.
    $from = mel_src_in_menu() ? mel_src_name(mel_src_now($st)) : '';
    $room = $right - MEL_GC_LX - ($from === '' ? 0 :
        min(300, MEL_K_ICON_SORT + MEL_K_KEY_DOT_GAP + MelKit::w($from, MEL_K_PX_ROW)) + MEL_K_HINT_GAP);
    $flt = mel_lpv_can($st, $L, 'red', 'tab');
    $green = mel_lpv_can($st, $L, 'green', 'tab');
    $yellow = mel_lpv_can($st, $L, 'yellow', 'tab');
    $qs = mel_lpv_qsort();
    $short = false;
    $nw = 360;
    while (true)
    {
        $tabs = array();
        if ($flt)
            $tabs[] = array('key' => $keys ? 'red' : '', 'state' => isset($v['flt']) ? 'on' : '', 'icon' => 'k_icon_chevron-down',
                'label' => mel_lpv_tr($lang, 'lp_flt_tab', array('name' => mel_lpv_flt_name($v, $lang, $nw))));
        $fold = $green ? mel_lpv_fold_word($L, $lang, $short) : '';
        if ($fold !== '')
            $tabs[] = array('key' => 'green', 'label' => $fold, 'icon' => '');
        if ($yellow)
            $tabs[] = array('key' => $keys ? 'yellow' : '', 'state' => $qs ? 'on' : '', 'icon' => '',
                'label' => mel_tr($lang, $keys ? 'lp_qsort_key' : 'lp_qsort_tab'));
        $t = MelKit::tabs($tabs, MEL_GC_LX, MEL_GC_TABS_MID);
        $over = $t['x1'] - MEL_GC_LX - $room;
        if ($over <= 0)
            break;
        if ($fold !== '' && !$short)
        {
            $short = true;
            continue;
        }
        // From the name as wide as it is, not from 360.
        $nw = min($nw, MelKit::w(mel_lpv_flt_first($v), MEL_K_PX_ROW));
        if (!$flt || !isset($v['flt']) || $nw <= 80)
            break;
        $nw = max(80, $nw - $over);
    }
    $left = MEL_GC_LX;
    if ($tabs)
    {
        $left = $t['x1'] + MEL_K_HINT_GAP;
        $t = MelKit::group('tabs', $t);
    }
    return array_merge($t['defs'], mel_gc_tabs_from($st, $left, $right));
}

// Lines of the online list of view $v on screen (the rows of its filter, mel_lpv_shown): the rows of a balancer in a run of 2 and more
// after a line of it, shown under it when it is unfolded ('open' of the view: its path), else none; a row alone as one line; "Ещё N
// источников" last. A line: k (head: of a balancer unfolded; shut: folded; row: under a head; one: alone; more), i (the row; of a
// head and shut its first; of more the number of rows), n (of a head and shut its rows), q (the quality it shows: of a head and
// shut the best of its rows, of a row under it only another one, '' none).
/**
 * @param MelLpView $v
 * @return list<MelLpLine>
 */
function mel_lpv_lines($v)
{
    $ids = mel_lpv_shown($v);
    $out = array();
    $c = count($ids);
    for ($a = 0; $a < $c; $a = $b)
    {
        $b = $a + 1;
        while ($b < $c && $v['rows'][$ids[$b]]['path'] === $v['rows'][$ids[$a]]['path'])
            $b++;
        $best = '';
        for ($j = $a; $j < $b; $j++)
        {
            $q = $v['rows'][$ids[$j]]['quality'];
            if ($q !== '' && ($best === '' || mel_lp_q_num($q) > mel_lp_q_num($best)))
                $best = $q;
        }
        if ($b - $a > 1)
        {
            $open = isset($v['open'][$v['rows'][$ids[$a]]['path']]);
            $out[] = array('k' => $open ? 'head' : 'shut', 'i' => $ids[$a], 'n' => $b - $a, 'q' => $best);
            if (!$open)
                continue;
        }
        for ($j = $a; $j < $b; $j++)
        {
            $q = $v['rows'][$ids[$j]]['quality'];
            $same = $q === $best || (mel_lp_q_num($q) > 0 && mel_lp_q_num($q) === mel_lp_q_num($best));
            $out[] = array('k' => $b - $a > 1 ? 'row' : 'one', 'i' => $ids[$j], 'n' => 0, 'q' => $b - $a > 1 && $same ? '' : $q);
        }
    }
    if ($v['left'] > 0)
        $out[] = array('k' => 'more', 'i' => count($v['rows']), 'n' => 0, 'q' => '');
    return $out;
}

// The line of lines $L of view $v of row $i: its line (of a balancer folded: that one), else the line of the first row after
// it in the list (or "Ещё N"), else the last. Of the lines but the heads: A the one of the least row $i or after, P the one
// of the greatest row before $i of the path of row $i; A of row $i, else P of a balancer folded (its row then), else A; no
// A - the last. Not by the order of the lines: by quality (mel_lpv_qsort) a balancer later in the list comes first, and
// the runs of one path apart in the rows are one balancer.
/**
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @param int $i
 * @return int
 */
function mel_lpv_line_at($v, $L, $i)
{
    $path = isset($v['rows'][$i]) ? $v['rows'][$i]['path'] : null;
    $a = -1;
    $p = -1;
    foreach ($L as $j => $l)
    {
        if ($l['k'] === 'head')
            continue;
        if ($l['i'] >= $i && ($a < 0 || $l['i'] < $L[$a]['i']))
            $a = $j;
        if ($l['i'] < $i && $l['k'] !== 'more' && $v['rows'][$l['i']]['path'] === $path && ($p < 0 || $l['i'] > $L[$p]['i']))
            $p = $j;
    }
    if ($a >= 0 && $L[$a]['i'] === $i)
        return $a;
    if ($p >= 0 && $L[$p]['k'] === 'shut')
        return $p;
    return $a >= 0 ? $a : count($L) - 1;
}

// The row of line $j of lines $L, -1 for "Ещё N" and a line of a balancer.
/**
 * @param list<MelLpLine> $L
 * @param int $j
 * @return int
 */
function mel_lpv_row_of($L, $j)
{
    return isset($L[$j]) && ($L[$j]['k'] === 'row' || $L[$j]['k'] === 'one') ? $L[$j]['i'] : -1;
}

// Line $j of lines $L is of a balancer of 2 and more rows (head, shut).
/**
 * @param list<MelLpLine> $L
 * @param int $j
 * @return bool
 */
function mel_lpv_is_bal($L, $j)
{
    return isset($L[$j]) && ($L[$j]['k'] === 'head' || $L[$j]['k'] === 'shut');
}

// The line of the balancer of line $j of lines $L: of a row under a head that head, else $j.
/**
 * @param list<MelLpLine> $L
 * @param int $j
 * @return int
 */
function mel_lpv_bal_of($L, $j)
{
    if (isset($L[$j]) && $L[$j]['k'] === 'row')
    {
        while ($j > 0 && $L[$j]['k'] !== 'head')
            $j--;
    }
    return $j;
}

// The place of line $j of lines $L for the cursor in other lines of the list (mel_lpv_put_at): its row (of a line of a balancer
// its first, "Ещё N" the number of rows), whether the line is of a balancer.
/**
 * @param list<MelLpLine> $L
 * @param int $j
 * @return array{int, bool}
 */
function mel_lpv_mark($L, $j)
{
    return isset($L[$j]) ? array($L[$j]['i'], mel_lpv_is_bal($L, $j)) : array(0, false);
}

// The cursor of lines $L of view $v put on place $m (mel_lpv_mark): of a line of a balancer the line of the balancer of its
// row, else its row (mel_lpv_put).
/**
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @param array{int, bool} $m
 * @return array{int, int}
 */
function mel_lpv_put_at($v, $L, $m)
{
    $j = max(0, mel_lpv_line_at($v, $L, $m[0]));
    return mel_lpv_put_line($L, $m[1] ? mel_lpv_bal_of($L, $j) : $j);
}

// Cursor $sel, $top of lines $L as the list keeps it: the top no further than the list allows (a top of more lines: a
// balancer folded); on the first row under a head that head on screen too.
/**
 * @param list<MelLpLine> $L
 * @param int $sel
 * @param int $top
 * @return array{int, int}
 */
function mel_lpv_fix($L, $sel, $top)
{
    $top = max(min($top, count($L) - MEL_GC_VIS), $sel - MEL_GC_VIS + 1);
    if ($sel > 0 && isset($L[$sel - 1]) && $L[$sel - 1]['k'] === 'head')
        $top = min($top, $sel - 1);
    return array($sel, max(0, min($top, $sel)));
}

// One key of lines $L (mel_gc_step).
/**
 * @param list<MelLpLine> $L
 * @param int $sel
 * @param int $top
 * @param 'up'|'down'|'pgup'|'pgdn' $key
 * @return array{int, int}
 */
function mel_lpv_step($L, $sel, $top, $key)
{
    list($nsel, $ntop) = mel_gc_step($sel, $top, count($L), $key);
    return mel_lpv_fix($L, $nsel, $ntop);
}

// The cursor of lines $L of view $v put on row $i (mel_lpv_line_at) in the middle of the screen, as far as the
// list allows. -> array(sel, top).
/**
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @param int $i
 * @return array{int, int}
 */
function mel_lpv_put($v, $L, $i)
{
    return mel_lpv_put_line($L, max(0, mel_lpv_line_at($v, $L, $i)));
}

// The cursor of lines $L put on line $j in the middle of the screen, as far as the list allows. -> array(sel, top).
/**
 * @param list<MelLpLine> $L
 * @param int $j
 * @return array{int, int}
 */
function mel_lpv_put_line($L, $j)
{
    list($sel, $top) = mel_gc_cursor($j, count($L));
    return mel_lpv_fix($L, $sel, $top);
}

// The number of lines $L but "Ещё N" (of the counter).
/**
 * @param list<MelLpLine> $L
 * @return int
 */
function mel_lpv_count($L)
{
    $n = count($L);
    return $n > 0 && $L[$n - 1]['k'] === 'more' ? $n - 1 : $n;
}

// The counter of line $sel of lines $L (mel_gc_count): of the lines but "Ещё N" (on it: the last); no line: "0 / 0".
/**
 * @param list<MelLpLine> $L
 * @param int $sel
 * @return list<MelGcComp>
 */
function mel_lpv_counter($L, $sel)
{
    $n = mel_lpv_count($L);
    return mel_gc_count(min($sel, $n - 1), $n);
}

// A colour key of the online list $st, lines $L, is there at place $at, one for the three places. $key: 'red' the
// filter by voices (a list with voices), 'green' all folded or unfolded (a line of a balancer of 2 rows), 'yellow' the
// order by quality (a list with rows). $at: 'key' the key is bound (mel_lpv_screen); 'tab' its tab is shown
// (mel_lpv_tabs) - the green one with the colour keys only, the yellow one without them only on (by quality); 'menu'
// its item of the panel "Действия" (mel_lpv_actions) - always, with the colour keys too.
/**
 * @param MelState $st
 * @param list<MelLpLine> $L
 * @param string $key
 * @param string $at
 * @return bool
 */
function mel_lpv_can($st, $L, $key, $at)
{
    if ($key === 'red')
        $can = (bool) mel_lpv_voices($st['lp'], mel_gc_lang($st));
    else if ($key === 'green')
        $can = mel_lpv_fold_state($L) !== '';
    else
        $can = (bool) $st['lp']['rows'];
    if ($at === 'tab' && $key === 'green')
        return $can && mel_gc_color_keys();
    if ($at === 'tab' && $key === 'yellow')
        return $can && (mel_gc_color_keys() || mel_lpv_qsort());
    return $can;
}

// --- Folding (ENTER on the line of a balancer, the green key, MENU "Развернуть все" / "Свернуть все"): the rows of a
// balancer shown under its line or not. Its state 'open' of the view: the paths of the balancers unfolded; none: all folded,
// as a list opens. It lives with the list of the title (between the episodes, "Обновить", back from the player), as 'flt'.

// Of lines $L: '' no line of a balancer, 'open' one unfolded, else 'shut'.
/**
 * @param list<MelLpLine> $L
 * @return string
 */
function mel_lpv_fold_state($L)
{
    $out = '';
    foreach ($L as $l)
    {
        if ($l['k'] === 'head')
            return 'open';
        if ($l['k'] === 'shut')
            $out = 'shut';
    }
    return $out;
}

// The words of the green key of lines $L: "Свернуть все" one unfolded, "Развернуть все" ($short: "Свернуть", "Развернуть");
// '' no line of a balancer.
/**
 * @param list<MelLpLine> $L
 * @param string $lang
 * @param bool $short
 * @return string
 */
function mel_lpv_fold_word($L, $lang, $short = false)
{
    $f = mel_lpv_fold_state($L);
    if ($f === '')
        return '';
    if ($f === 'open')
        return mel_tr($lang, $short ? 'lp_fold' : 'lp_fold_all');
    return mel_tr($lang, $short ? 'lp_unfold' : 'lp_unfold_all');
}

// lp_pick on line $sel of a balancer of the online list $st ($top the first line on screen): folded - its rows shown under
// it, the cursor on the first of them; unfolded - folded, the cursor on its line. The screen replaced from the state, no
// request (as mel_lpv_flt_set).
/**
 * @param MelState $st
 * @param int $sel
 * @param int $top
 * @return MelAction
 */
function mel_lpv_fold($st, $sel, $top)
{
    $L = mel_lpv_lines($st['lp']);
    $i = $L[$sel]['i'];
    $path = $st['lp']['rows'][$i]['path'];
    $open = $L[$sel]['k'] === 'shut';
    $ns = $st;
    $ns['rid'] = mel_rid();
    if ($open)
        $ns['lp']['open'][$path] = true;
    else
        unset($ns['lp']['open'][$path]);
    $nl = mel_lpv_lines($ns['lp']);
    $j = mel_lpv_bal_of($nl, max(0, mel_lpv_line_at($ns['lp'], $nl, $i)));
    $ns['lp']['gc'] = mel_lpv_fix($nl, $open && isset($nl[$j + 1]) ? $j + 1 : $j, $top);
    $ns['lp']['gc_new'] = true;
    mel_log("lampac: balancer $path " . ($open ? 'unfolded' : 'folded') . ", {$L[$sel]['n']} rows");
    return mel_list_replace($ns);
}

// lp_fold (the green key, MENU "Развернуть все" / "Свернуть все") of the online list $st, the cursor on line $sel: one
// unfolded - all folded, else all unfolded; the cursor on the line of the balancer it was on (on a row alone: on it). No line of
// a balancer: nothing.
/**
 * @param MelState $st
 * @param int $sel
 * @return MelAction|null
 */
function mel_lpv_fold_all($st, $sel)
{
    $L = mel_lpv_lines($st['lp']);
    $f = mel_lpv_fold_state($L);
    if ($f === '')
    {
        mel_log('lampac: fold: no balancer of 2 rows, nothing');
        return null;
    }
    $m = mel_lpv_mark($L, mel_lpv_bal_of($L, $sel));
    $ns = $st;
    $ns['rid'] = mel_rid();
    unset($ns['lp']['open']);
    if ($f === 'shut')
        $ns['lp']['open'] = mel_lpv_open_all($st['lp']);
    $nl = mel_lpv_lines($ns['lp']);
    $ns['lp']['gc'] = mel_lpv_put_at($ns['lp'], $nl, $m);
    $ns['lp']['gc_new'] = true;
    mel_log('lampac: all balancers ' . ($f === 'shut' ? 'unfolded' : 'folded'));
    return mel_list_replace($ns);
}

// 'open' of view $v with all its balancers unfolded.
/**
 * @param MelLpView $v
 * @return array<string, bool>
 */
function mel_lpv_open_all($v)
{
    $out = array();
    foreach ($v['rows'] as $r)
        $out[$r['path']] = true;
    return $out;
}

// Columns of lines $L of view $v: the plate of the quality (b2, as wide as the widest of the lines), the balancer, the
// voice of a line alone (after the widest name of those). -> array(x of the balancer, x of the voice of a line alone,
// the width of the plates; 0 none).
/**
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @return array{int, int, int}
 */
function mel_lpv_cols($v, $L)
{
    $bw = 0;
    $sw = 0;
    foreach ($L as $l)
    {
        $b = mel_lpv_badge($l['q']);
        if ($b !== '')
            $bw = max($bw, MelKit::b2_w($b));
        if ($l['k'] === 'one')
            $sw = max($sw, min(MEL_LPV_SRC_W, MelKit::w($v['rows'][$l['i']]['src'], MEL_K_PX_ROW)));
    }
    $x = MEL_GC_PAD + ($bw > 0 ? $bw + MEL_K_GAP_M : 0);
    return array($x, $x + $sw + MEL_K_GAP_L, $bw);
}

// The columns of lines $L of view $v: those of its last full view (gc_cols, mel_lpv_folder_view), else counted
// (mel_lpv_cols) - they go over all the lines, a step does not count them again.
/**
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @return array{int, int, int}
 */
function mel_lpv_cols_st($v, $L)
{
    return isset($v['gc_cols']) ? $v['gc_cols'] : mel_lpv_cols($v, $L);
}

// Children of row panel "r$j" of view $v, line $j of its lines $L, $top the first line on screen, on the components
// of the kit as a row of the releases (mel_gc_row_items; $on: on the plate of the focus, its texts white): a rule over
// a block of an unfolded balancer and after it (none between the lines of the balancers folded and the rows alone);
// the quality of the line (mel_lpv_lines): its plate b2, else "до 4320p" at the right; a balancer - MelKit::group_line:
// its name, the number of its rows dimmed ("12 потоков"), the chevron: down folded, up unfolded; a row under it - the
// voice indented, on the first line of the screen the balancer dimmed before it; a row alone - the balancer and the
// voice; a row watched: the bar of the share. The line "Ещё N источников — Обновить", dimmed.
/**
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @param int $j
 * @param array{int, int, int} $c
 * @param bool $on
 * @param int $top
 * @param string $lang
 * @return list<MelGcComp>
 */
function mel_lpv_row_items($v, $L, $j, $c, $on, $top, $lang)
{
    $y = MEL_GC_ROW_H / 2;
    $w = MEL_GC_LW - 2 * MEL_GC_PAD;
    $fg = $on ? MEL_K_C_ON_ACCENT : MEL_K_C_TEXT1;
    $fg3 = $on ? MEL_K_C_ON_ACCENT2 : MEL_K_C_TEXT3;
    $l = $L[$j];
    if ($l['k'] === 'more')
    {
        $t = mel_lpv_tr($lang, 'lp_more', array('n' => $v['left'], 'sources' => mel_gc_plural($lang, $v['left'], 'lp_sources')));
        $t = MelKit::text($t, MEL_K_PX_ROW, $fg3, MEL_GC_PAD, $y, array('w' => $w));
        return $t['defs'];
    }
    $r = $v['rows'][$l['i']];
    $d = array();
    $flat = array('one' => true, 'shut' => true);
    if ($j > 0 && $l['k'] !== 'row' && !(isset($flat[$l['k']]) && isset($flat[$L[$j - 1]['k']])))
        $d[] = mel_gc_rect(mel_gc_at($w, MEL_K_LIST_RULE, MEL_GC_PAD, 0), MEL_K_C_LINE);
    $it = array();
    $b = mel_lpv_badge($l['q']);
    if ($b !== '')
        $it[] = MelKit::b2($b, MEL_GC_PAD, $y, $c[2]);
    // "до 2160p" at the right only of no plate (8K, a quality not known): the plate says it, the card too.
    $qx = MEL_GC_LW - MEL_GC_PAD;
    $qt = null;
    if ($l['q'] !== '' && $b === '')
    {
        $t = mel_lpv_tr($lang, 'lp_side_q', array('q' => $l['q']));
        $qx -= MelKit::w($t, MEL_K_PX_ROW);
        $qt = MelKit::text($t, MEL_K_PX_ROW, $fg, $qx, $y);
        $qx -= MEL_K_GAP_M;
    }
    if ($l['k'] === 'head' || $l['k'] === 'shut')
        $it[] = MelKit::group_line(MelKit::cut($r['src'], MEL_K_PX_ROW, '', MEL_LPV_SRC_W),
            $l['n'] . ' ' . mel_gc_plural($lang, $l['n'], 'lp_streams'), $l['k'] === 'head', $on, $c[0], $y);
    else
    {
        $vx = $l['k'] === 'one' ? $c[1] : $c[0] + MEL_LPV_INDENT;
        if ($l['k'] === 'one' || $j === $top)
            $it[] = MelKit::text($r['src'], MEL_K_PX_ROW, $l['k'] === 'one' ? $fg : $fg3, $c[0], $y, array('w' => MEL_LPV_SRC_W));
        if ($l['k'] === 'row' && $j === $top)
            $vx = max($vx, $c[0] + min(MEL_LPV_SRC_W, MelKit::w($r['src'], MEL_K_PX_ROW)) + MEL_K_GAP_L);
        $it[] = MelKit::text(mel_lpv_voice_text($v, $r, $lang), MEL_K_PX_ROW, $fg, $vx, $y, array('w' => max(40, $qx - $vx)));
    }
    if ($qt)
        $it[] = $qt;
    $line = MelKit::line($it, $y, 'row');
    $d = array_merge($d, $line['defs']);
    $share = ($l['k'] === 'row' || $l['k'] === 'one') && isset($v['gc_prog'][$r['key']]) ? $v['gc_prog'][$r['key']] : 0;
    if ($share > 0)
    {
        $bar = MelKit::bar('progress', MEL_GC_PAD, MEL_GC_BAR_Y, $w, MEL_GC_BAR_H, $share);
        $d = array_merge($d, $bar['defs']);
    }
    return $d;
}

// Children of 'card' of line $j of lines $L of view $v (mel_lpv_card).
/**
 * @param MelState $st
 * @param MelLpView $v
 * @param list<MelLpLine> $L
 * @param int $j
 * @return list<MelGcComp>
 */
function mel_lpv_card_of($st, $v, $L, $j)
{
    return mel_lpv_is_bal($L, $j) ? mel_lpv_card($st, $v, $L[$j]['i'], $L[$j]['q']) : mel_lpv_card($st, $v, mel_lpv_row_of($L, $j));
}

// Children of 'card' of row $i (of the list, not of the screen) of view $v, stacks of the kit as the card of a release
// (mel_gc_card): beside the poster the balancer (MEL_K_PX_PANEL_TITLE) and "до 2160p"; under it the fields (mel_gc_field):
// the voice, the quality ("до 2160p", else "источник не сообщает"), the subtitles that do not go to the player, "Через:
// Lampac из настроек". Of the line of a balancer (its first row $i; $q its best quality, '' none): no voice, no
// subtitles. The row "Ещё N" and none: no card.
/**
 * @param MelState $st
 * @param MelLpView $v
 * @param int $i
 * @param string|null $q
 * @return list<MelGcComp>
 */
function mel_lpv_card($st, $v, $i, $q = null)
{
    if (!isset($v['rows'][$i]))
        return array();
    $r = $v['rows'][$i];
    $bal = $q !== null;
    $q = $bal ? $q : $r['quality'];
    $lang = mel_gc_lang($st);
    $sx = mel_img($st['movie']['poster']) !== '' ? MEL_GC_PW + MEL_GC_SIDE_GAP : 0;
    $so = array('w' => MEL_GC_CW - $sx);
    $side = array(MelKit::text($r['src'], MEL_K_PX_PANEL_TITLE, MEL_K_C_TEXT1, $sx, 0, $so));
    if ($q !== '')
        $side[] = MelKit::text(mel_lpv_tr($lang, 'lp_side_q', array('q' => $q)), MEL_K_PX_ROW, MEL_K_C_TEXT2, $sx, 0, $so);
    $side = MelKit::stack($side, 'm', 0, 'side');
    $fields = $bal ? array() : array(array('lp_card_voice', mel_lpv_voice_text($v, $r, $lang)));
    $fields[] = array('lp_card_quality', $q !== '' ? mel_lpv_tr($lang, 'lp_side_q', array('q' => $q)) :
        mel_tr($lang, 'lp_card_q_none'));
    if ($r['subs'] > 0 && !$bal)
        $fields[] = array('lp_card_subs', mel_tr($lang, 'lp_card_subs_v'));
    // The profile of Lampac of the list (v2: one of several).
    $via = mel_src_name($st['lp_prof']);
    $fields[] = array('lp_card_via', $via !== '' ? $via : 'Lampac');
    $blocks = array();
    foreach ($fields as $f)
        $blocks[] = mel_gc_field(mel_tr($lang, $f[0]), MelKit::wrap($f[1], MEL_K_PX_SECONDARY, '', MEL_GC_CW - MEL_GC_VX, 2),
            MEL_GC_LH);
    // Under the poster's place even without a poster.
    $under = MelKit::stack($blocks, 'm', MEL_GC_PH + MEL_K_GAP_M, 'card');
    return array_merge($side['defs'], $under['defs']);
}

// The window of the online list of $st (its view 'lp'), line $sel selected, $top the first on screen (mel_lpv_lines),
// on the components of the kit as the list of releases (mel_gc_window): the background, the poster, the card, the
// title with the yellow line of the view (mel_gc_head), the lines of the window (mel_gc_rows_span) over the plate of
// the focus, the scroll bar, the hint line (mel_lpv_screen), the counter, the row of the tabs (mel_lpv_tabs).
/**
 * @param MelState $st
 * @param int $sel
 * @param int $top
 * @return array<string, mixed>
 */
function mel_lpv_window($st, $sel, $top)
{
    $v = $st['lp'];
    $mv = $st['movie'];
    $lang = mel_gc_lang($st);
    $L = mel_lpv_lines($v);
    $n = count($L);
    $c = mel_lpv_cols_st($v, $L);
    $d = array(mel_gc_rect(mel_gc_at(1920, 1080, 0, 0), MEL_K_C_SCRIM));
    $poster = mel_img($mv['poster']);
    if ($poster !== '')
        $d[] = mel_gc_image(mel_gc_at(MEL_GC_PW, MEL_GC_PH, MEL_GC_CX, MEL_GC_CY), $poster, true);
    $d[] = mel_gc_panel('card', mel_gc_at(MEL_GC_CW, MEL_GC_CARD_BOTTOM - MEL_GC_CY, MEL_GC_CX, MEL_GC_CY),
        mel_lpv_card_of($st, $v, $L, $sel));
    $d = array_merge($d, mel_gc_head($st, $v['note']));
    if ($n > 0)
    {
        $in = array(mel_gc_sel($sel));
        list($lo, $hi) = mel_gc_rows_span($top, $n);
        for ($i = $lo; $i < $hi; $i++)
            $in[] = mel_gc_panel("r$i", mel_gc_row_geom($i), mel_lpv_row_items($v, $L, $i, $c, $i === $sel, $top, $lang));
        $d[] = mel_gc_panel('list', mel_gc_at(MEL_GC_LW, MEL_GC_VIS * MEL_GC_ROW_H, MEL_GC_LX, MEL_GC_LY),
            array(mel_gc_panel('rows', mel_gc_rows_geom($top, $n), $in, 0)), 0);
    }
    if ($n > MEL_GC_VIS)
    {
        $d[] = mel_gc_rect(mel_gc_at(6, MEL_GC_VIS * MEL_GC_ROW_H, MEL_GC_LX + MEL_GC_LW + 10, MEL_GC_LY),
            MEL_K_C_SCROLL_TRACK);
        $d[] = mel_gc_rect(mel_gc_thumb_geom($top, $n), MEL_K_C_SCROLL_THUMB, 'thumb');
    }
    list(, $hint) = mel_lpv_screen($st, $L);
    $h = MelKit::hint($hint, MEL_GC_LX, MEL_GC_HINT_MID);
    $d = array_merge($d, $h['defs']);
    $d[] = mel_gc_panel('counter', mel_gc_at(MEL_GC_COUNT_W, MEL_K_KEY_H, MEL_GC_LX + MEL_GC_LW - MEL_GC_PAD - MEL_GC_COUNT_W,
        MEL_GC_HINT_MID - intval(MEL_K_KEY_H / 2)), mel_lpv_counter($L, $sel));
    $d = array_merge($d, mel_lpv_tabs($st, $L));
    // The slot of the overlay, the last: the panels of the list (mel_lpv_menu) open in it.
    $d[] = mel_ovl_slot();
    return mel_gc_window_def($d, $mv['fanart']);
}

// The cursor of view $v (lines of mel_lpv_lines): of the shell's sel_state $raw, else its last one
// (mel_lpv_fix). -> array(sel, top).
/**
 * @param MelLpView $v
 * @param mixed $raw
 * @return array{int, int}
 */
function mel_lpv_pos($v, $raw)
{
    $L = mel_lpv_lines($v);
    list($sel, $top) = mel_gc_pos_in($raw, count($L), isset($v['gc']) ? $v['gc'] : null);
    return mel_lpv_fix($L, $sel, $top);
}

// The cursor of the online list $st of the shell's sel_state of input $in (mel_lpv_pos), kept as the last one of the
// live list (Mel::$state): what is built over or in place of the list starts from it. -> array(sel, top).
/**
 * @param stdClass $in
 * @param MelState $st
 * @return array{int, int}
 */
function mel_lpv_pos_keep($in, $st)
{
    $pos = mel_lpv_pos($st['lp'], isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    Mel::$state['lp']['gc'] = $pos;
    return $pos;
}

// The keys of the screen of the online list of rid $rid every such list has: the cursor, ENTER, MENU, back from the
// player. By the rid only: the screen of a list gone has them too (mel_gc_scr_in).
/**
 * @param string $rid
 * @return array<string, MelAction>
 */
function mel_lpv_keys($rid)
{
    return array(
        GUI_EVENT_KEY_UP => mel_gc_act('lp_move', $rid, array('d' => 'up')),
        GUI_EVENT_KEY_DOWN => mel_gc_act('lp_move', $rid, array('d' => 'down')),
        GUI_EVENT_KEY_P_PLUS => mel_gc_act('lp_move', $rid, array('d' => 'pgup')),
        GUI_EVENT_KEY_P_MINUS => mel_gc_act('lp_move', $rid, array('d' => 'pgdn')),
        GUI_EVENT_KEY_ENTER => mel_gc_act('lp_pick', $rid),
        GUI_EVENT_MENU_PLAYBACK_FINISH => mel_finish_act($rid),
        GUI_EVENT_KEY_POPUP_MENU => mel_gc_act('lp_menu', $rid));
}

// The keys of the online list $st of lines $L and the items of its hint line (MelKit::hint), one for both: the cursor,
// ENTER, MENU "Действия"; the green key (mel_lpv_fold_all), of a list with a balancer of 2 rows; «P+ P−» "Прокрутка" with more lines than the screen; the
// yellow key, the order of the balancers, of a list with rows (its tab, mel_lpv_tabs); the red key, the filter by
// voices, of a list with voices (mel_lpv_can); an episode: left and right flip the episodes of the season
// (mel_lpv_flip), "Серии". -> array(actions, hint items).
/**
 * @param MelState $st
 * @param list<MelLpLine> $L
 * @return array{array<string, MelAction>, list<array{string, string}>}
 */
function mel_lpv_screen($st, $L)
{
    $rid = $st['rid'];
    $tt = mel_gc_text(mel_gc_lang($st));
    $acts = mel_lpv_keys($rid);
    if (mel_lpv_can($st, $L, 'green', 'key'))
        $acts[GUI_EVENT_KEY_B_GREEN] = mel_gc_act('lp_fold', $rid);
    $hint = count($L) > MEL_GC_VIS ? array(array('pp', $tt['page'])) : array();
    if (mel_lpv_can($st, $L, 'yellow', 'key'))
        $acts[GUI_EVENT_KEY_C_YELLOW] = mel_gc_act('lp_qsort', $rid);
    if (mel_lpv_can($st, $L, 'red', 'key'))
        $acts[GUI_EVENT_KEY_A_RED] = mel_gc_act('lp_flt_menu', $rid);
    if ($st['e'] > 0)
    {
        $acts[GUI_EVENT_KEY_LEFT] = mel_gc_act('lp_flip', $rid, array('d' => 'prev'));
        $acts[GUI_EVENT_KEY_RIGHT] = mel_gc_act('lp_flip', $rid, array('d' => 'next'));
        $hint[] = array('lr', $tt['flip']);
    }
    $hint[] = array('k_icon_menu', $tt['actions']);
    return array($acts, $hint);
}

// get_folder_view of the online list of $st (lp_on).
/**
 * @param MelState $st
 * @param mixed $sel_state
 * @return array<string, mixed>
 */
function mel_lpv_folder_view($st, $sel_state)
{
    // The first view of a view put in: its row, not a sel_state of the screen it replaced.
    list($sel, $top) = mel_lpv_pos($st['lp'], isset($st['lp']['gc_new']) ? null : $sel_state);
    unset(Mel::$state['lp']['gc_new']);
    Mel::$state['lp']['gc'] = array($sel, $top);
    $st['lp']['gc_prog'] = Mel::$state['lp']['gc_prog'] = mel_wh_progress($st);
    // The columns, counted once a full view (mel_lpv_cols_st).
    $L = mel_lpv_lines($st['lp']);
    $st['lp']['gc_cols'] = Mel::$state['lp']['gc_cols'] = mel_lpv_cols($st['lp'], $L);
    // A view anew under an open panel: its state is not that of this view (the close would put back the old map).
    mel_ovl_drop($st['rid']);
    list($acts) = mel_lpv_screen($st, $L);
    $scr = mel_gc_scr($st['rid'], $acts, mel_gc_state($sel, $top));
    return array(
        PluginFolderView::multiple_views_supported => false,
        PluginFolderView::archive => null,
        PluginFolderView::folder_type => null,
        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
        PluginFolderView::data => array(
            PluginGCompsFolderView::window_def => mel_lpv_window($st, $sel, $top),
            PluginGCompsFolderView::sel_state => $scr['sel'],
            PluginGCompsFolderView::actions => $scr['map'],
            PluginGCompsFolderView::timer => $scr['timer']));
}

// lp_move of the online list: the cursor (mel_lpv_step), the window of lines after the top (mel_gc_cursor_changes), the
// line the cursor left and the one it came to, the first line of the screen when it moved (mel_lpv_row_items), the card,
// the counter.
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_move($in, $st)
{
    $key = mel_gc_key($in);
    if ($key === null)
        return null;
    $v = $st['lp'];
    $L = mel_lpv_lines($v);
    $n = count($L);
    list($sel, $top) = mel_lpv_pos($v, isset($in->parent_sel_state) ? $in->parent_sel_state : null);
    list($nsel, $ntop) = mel_lpv_step($L, $sel, $top, $key);
    Mel::$state['lp']['gc'] = array($nsel, $ntop);
    if ($nsel === $sel && $ntop === $top)
        return null;
    $lang = mel_gc_lang($st);
    $c = mel_lpv_cols_st($v, $L);
    // A line that comes into the window: as the new top shows it.
    $ch = mel_gc_cursor_changes($top, $nsel, $ntop, $n, function ($j) use ($v, $L, $c, $ntop, $lang)
    {
        return mel_gc_panel("r$j", mel_gc_row_geom($j), mel_lpv_row_items($v, $L, $j, $c, false, $ntop, $lang));
    });
    foreach (array_unique(array($sel, $nsel, $top, $ntop)) as $j)
        $ch[] = mel_gc_change("r$j", null, GCOMP_TRANSITION_NONE, mel_lpv_row_items($v, $L, $j, $c, $j === $nsel, $ntop, $lang));
    $ch[] = mel_gc_change('card', null, GCOMP_TRANSITION_NONE, mel_lpv_card_of($st, $v, $L, $nsel));
    $ch[] = mel_gc_change('counter', null, GCOMP_TRANSITION_NONE, mel_lpv_counter($L, $nsel));
    return mel_gc_changes_act($ch, $nsel, $ntop);
}

// Back from the player (mel_finish): lazy episodes played another episode of the season of the list
// (MelLpv::$played) - the list of that one, its rows and cursor kept (as mel_finish of the releases);
// else the bars of the rows whose share changed, else nothing.
/**
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_finish($st)
{
    $p = MelLpv::$played;
    MelLpv::$played = null;
    if ($p && $p['rid'] === $st['rid'] && $p['s'] === $st['s'] && $p['e'] !== $st['e'])
    {
        $ns = mel_lpv_ep_state($st, $p['s'], $p['e']);
        mel_log('lampac: finish: list ' . mel_ep_tag($st) . ', played ' . mel_ep_tag($ns) . ' -> screen replaced');
        return mel_list_replace($ns);
    }
    $v = $st['lp'];
    $old = isset($v['gc_prog']) ? $v['gc_prog'] : array();
    $v['gc_prog'] = Mel::$state['lp']['gc_prog'] = mel_wh_progress($st);
    list($sel, $top) = mel_lpv_pos($v, null);
    $lang = mel_gc_lang($st);
    $L = mel_lpv_lines($v);
    $c = mel_lpv_cols_st($v, $L);
    // Only the lines on the screen (mel_gc_rows_span); the others get the share when the window comes to them.
    list($lo, $hi) = mel_gc_rows_span($top, count($L));
    $ch = array();
    for ($j = $lo; $j < $hi; $j++)
    {
        $i = mel_lpv_row_of($L, $j);
        if ($i < 0)
            continue;
        $key = $v['rows'][$i]['key'];
        $was = isset($old[$key]) ? $old[$key] : 0;
        $now = isset($v['gc_prog'][$key]) ? $v['gc_prog'][$key] : 0;
        if ($was != $now)
            $ch[] = mel_gc_change("r$j", null, GCOMP_TRANSITION_NONE, mel_lpv_row_items($v, $L, $j, $c, $j === $sel, $top, $lang));
    }
    mel_log('lampac: finish, ' . count($ch) . ' rows redrawn');
    if (!$ch)
        return null;
    return mel_gc_changes_act($ch, $sel, $top);
}

// --- "Действия" of the online list: the side panel of the kit (kit_panel.php) over it, opened by MENU.

// The panel "Действия" of the online list $st of lines $L, three groups:
//   of the view of the list (mel_lpv_can 'menu': with the colour keys too): "Озвучка" with the name of the filter on
//     the right (a list with voices; its check list, mel_lpv_flt_panel, a submenu; the red key opens it alone),
//     "Балансеры" (a choice of them saved, lp_src; a check list with "Переместить", mel_lpv_srcs_panel), "Развернуть все" /
//     "Свернуть все" (a balancer of 2 rows; as the green key), "Сортировать по качеству" / "Порядок как в Балансерах" (a
//     list with rows; as the yellow key);
//   of the list: "Обновить список" (mel_lpv_refresh), an episode: "Выбрать серию", "Источник" (two ready profiles, of a
//     card of no IMDb and no TMDB, mel_rel_id, those of Lampac; mel_src_item), "Ошибки балансеров" (reasons of the view; a submenu,
//     mel_errors_panel);
//   "Настройки Melange".
/**
 * @param MelState $st
 * @param list<MelLpLine> $L
 * @return MelKpDesc
 */
function mel_lpv_actions($st, $L)
{
    $lang = $st['lang'];
    $rid = $st['rid'];
    $tt = mel_gc_text(mel_gc_lang($st));
    $view = array();
    if (mel_lpv_can($st, $L, 'red', 'menu'))
    {
        $label = mel_tr($lang, 'lp_flt_title');
        $view[] = array('id' => 'flt', 'label' => $label, 'icon' => 'k_icon_audio-lines',
            'value' => MelKit::panel_value($label, mel_lpv_flt_name($st['lp'], $lang)),
            'menu' => mel_lpv_flt_panel($st));
    }
    $srcs = mel_lpv_srcs_panel($st);
    if ($srcs)
        $view[] = array('id' => 'srcs', 'label' => mel_tr($lang, 'lp_srcs_menu'), 'icon' => 'k_icon_list-checks',
            'menu' => $srcs);
    if (mel_lpv_can($st, $L, 'green', 'menu'))
        $view[] = array('id' => 'fold', 'label' => mel_lpv_fold_word($L, $lang),
            'icon' => mel_lpv_fold_state($L) === 'open' ? 'k_icon_chevrons-up' : 'k_icon_chevrons-down',
            'act' => mel_gc_act('lp_fold', $rid));
    if (mel_lpv_can($st, $L, 'yellow', 'menu'))
        $view[] = array('id' => 'qsort', 'label' => mel_tr($lang, mel_lpv_qsort() ? 'lp_qsort_off' : 'lp_qsort_on'),
            'icon' => 'k_icon_arrow-up-down', 'act' => mel_gc_act('lp_qsort', $rid));
    $list = array(array('id' => 'refresh', 'label' => mel_tr($lang, 'refresh_list'), 'icon' => 'k_icon_refresh-cw',
        'act' => mel_gc_act('lp_refresh', $rid)));
    if (mel_gc_can_choose($st))
        $list[] = array('id' => 'choose', 'label' => mel_tr($lang, 'choose_menu'), 'icon' => 'k_icon_list-video',
            'act' => mel_gc_act('lp_choose', $rid));
    $src = mel_src_item($lang, 'gc_src', array('rid' => $rid), mel_src_now($st), mel_rel_id($st['movie']) === '');
    if ($src)
        $list[] = $src;
    $errors = mel_lpv_errors_of($st);
    if ($errors['lines'])
        $list[] = array('id' => 'errors', 'label' => mel_tr($lang, 'lp_errors_menu'), 'icon' => 'k_icon_triangle-alert',
            'menu' => mel_errors_panel(mel_tr($lang, 'lp_errors_menu'), $lang, $errors));
    $groups = $view ? array($view, $list) : array($list);
    $groups[] = array(mel_setup_item($lang));
    return array('title' => $tt['actions'], 'groups' => $groups);
}

// «Готово» of a check list of the online list in language $lang (mel_lpv_flt_panel, mel_lpv_srcs_panel): its label,
// the count "Выбрано: {n} из {m}", its action - $control of the list of rid $rid, which takes the choice (mel_kp_done).
/**
 * @param string $lang
 * @param string $control
 * @param string $rid
 * @return array{label: string, count: string, act: MelAction}
 */
function mel_lpv_done($lang, $control, $rid)
{
    return array('label' => mel_tr($lang, 'kp_done'), 'count' => mel_tr($lang, 'kp_count'),
        'act' => mel_gc_act($control, $rid));
}

// lp_menu (MENU) of the online list $st -> the panel "Действия" over its screen (mel_kp_open; mel_lpv_actions), the
// cursor kept as the last one of the list (the actions of the items come with no sel_state of the shell).
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_menu($in, $st)
{
    list($sel, $top) = mel_lpv_pos_keep($in, $st);
    // The cursor of "Балансеры" (mel_lpv_srcs_panel) is by that of the list.
    $st['lp']['gc'] = array($sel, $top);
    $L = mel_lpv_lines($st['lp']);
    list($acts) = mel_lpv_screen($st, $L);
    return mel_kp_open(mel_gc_scr($st['rid'], $acts, mel_gc_state($sel, $top)), mel_lpv_actions($st, $L));
}

// "Ошибки балансеров" of the online list of $st: a line a reason of a balancer ("Filmix: не
// отвечает"), once each; those only through the Lampa app as one line of their number.
/**
 * @param MelState $st
 * @return MelErrors
 */
function mel_lpv_errors_of($st)
{
    $v = $st['lp'];
    $lang = mel_gc_lang($st);
    $lines = array();
    $rch = mel_lpv_rch_n($v);
    foreach ($v['why'] as $w)
    {
        if ($w['why'] === 'rch')
            continue;
        $lines[] = ($w['src'] !== '' ? $w['src'] : $w['path']) . ': ' . mel_lpv_why($lang, $w['why'], $w['detail']);
    }
    $lines = array_values(array_unique($lines));
    if ($rch > 0)
        $lines[] = mel_lpv_tr($lang, 'lp_hidden_rch', array('n' => $rch, 'sources' => mel_gc_plural($lang, $rch, 'lp_sources')));
    return array('lines' => array_slice($lines, 0, MEL_ERRORS_MAX), 'more' => max(0, count($lines) - MEL_ERRORS_MAX));
}

// --- MENU and the keys of the lists.

// lp_refresh ("Обновить") of the online list $st: a new search, not within MEL_LPV_REFRESH s of
// the last one (a message then).
/**
 * @param MelState $st
 * @return MelAction
 */
function mel_lpv_refresh($st)
{
    $wait = (int) ceil(MEL_LPV_REFRESH - (mel_lp_now() - $st['lp']['t']));
    if ($wait > 0)
    {
        mel_log("lampac: refresh in $wait s");
        return mel_dialog(mel_lpv_tr($st['lang'], 'lp_refresh_wait', array('n' => $wait)), array(),
            array('OK' => mel_close_and(null)));
    }
    return mel_lpv_search_open($st, 'replace');
}

// lp_pick (ENTER) of the online list $st: the line of a balancer - folded or unfolded (mel_lpv_fold); the row "Ещё N" - the
// replies of the sources left; a row - its link and the player; a row of a season (an episode): its episode first (mel_lpv_episode),
// none in the voice - a window, the list stays.
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_pick($in, $st)
{
    $v = $st['lp'];
    list($sel, $top) = mel_lpv_pos_keep($in, $st);
    $L = mel_lpv_lines($v);
    if (mel_lpv_is_bal($L, $sel))
        return mel_lpv_fold($st, $sel, $top);
    $i = mel_lpv_row_of($L, $sel);
    if ($i < 0)
        return $v['left'] > 0 ? mel_lpv_search_open($st, 'replace', array('more' => true)) : null;
    $r = $v['rows'][$i];
    $base = Mel::$settings['lp'];
    $left = Mel::$t0 > 0 ? MEL_OP_BUDGET - (microtime(true) - Mel::$t0) : MEL_OP_BUDGET;
    $deadline = mel_lp_now() + $left;
    mel_log("lampac: row $i of " . count($v['rows']) . ": {$r['path']}, {$r['method']}" . ($st['e'] > 0 ? ', ' . mel_ep_tag($st) : ''));
    $s = $st['e'] > 0 ? $st['s'] : 0;
    $e = $st['e'] > 0 ? $st['e'] : 0;
    $f = mel_lp_found($r, null, -1, -1);
    if ($r['method'] === 'season')
    {
        $f = mel_lpv_episode($base, $v, $r, $s, $e, $deadline);
        if ($f['why'] === 'no_episode' || $f['why'] === 'no_episodes')
        {
            mel_log("lampac: {$r['path']}: no " . mel_ep_tag($st) . " in the voice ({$f['detail']})");
            return mel_dialog(mel_lpv_tr($st['lang'], 'lp_no_ep_title', array('se' => "S{$s}E{$e}")),
                mel_dialog_lines(mel_lpv_no_ep_text($st['lang'], $r['src'], $r['voice'], $s, $e, $f['why'], $f['have'])),
                array('OK' => mel_close_and(null)));
        }
    }
    $a = mel_lpv_ep_play($st['movie'], $st['lang'], $f, $st['s'], $st['e'], 0, $deadline);
    if (isset($a[0]))
        return mel_lpv_failed($st['lang'], $r, $a[0], $a[1]);
    MelLpv::$played = null;
    return $a;
}

// Episode $e of season $s of row $r of a voice of a season (method season) of view $v: the season by
// the link of the row (spec §5.3: never a link of our own), in its voice (mel_lp_voice_ep: Lampac made
// another one active - the link of the voice among voice[]; none of the row - no_voice), the episode,
// before deadline $deadline (MEL_LPV_CHECK_MIN s kept for the check of the link) -> MelLpFound.
/**
 * @param string $base
 * @param MelLpView $v
 * @param MelLpRow $r
 * @param int $s
 * @param int $e
 * @param float $deadline
 * @return MelLpFound
 */
function mel_lpv_episode($base, $v, $r, $s, $e, $deadline)
{
    $src = mel_lp_source_of($base, $r['path']);
    foreach ($v['sources'] as $x)
    {
        if ($x['path'] === $r['path'])
            $src = $x;
    }
    if ($src === null || $s < 1 || $e < 1)
        return mel_lp_not_found('bad_key', '', $r['voice']);
    $total = min(2 * MEL_LP_REPLY_MAX, $deadline - mel_lp_now() - MEL_LPV_CHECK_MIN);
    $t0 = mel_lp_now();
    $sea = mel_lp_season_get($base, $src, $r['url'], $total, $s);
    return mel_lp_voice_ep($base, $src, $r['key'], $sea, $s, $e, $total - (mel_lp_now() - $t0));
}

// The words of no episode $e of season $s ($why no_episode: of those $have; no_episodes: none at all)
// in voice $voice of balancer $src ('': the balancer has no voices): "В озвучке LostFilm нет S1E9,
// есть 1–7", "В озвучке LostFilm нет серий", "У Collaps нет S1E9, есть 1–7".
/**
 * @param string $lang
 * @param string $src
 * @param string $voice
 * @param int $s
 * @param int $e
 * @param string $why
 * @param list<int> $have
 * @return string
 */
function mel_lpv_no_ep_text($lang, $src, $voice, $s, $e, $why, $have)
{
    $key = ($why === 'no_episode' ? 'lp_no_ep' : 'lp_no_eps') . ($voice !== '' ? '' : '_src');
    return mel_lpv_tr($lang, $key, array('voice' => mel_cut($voice, 40), 'src' => $src, 'se' => "S{$s}E{$e}",
        'have' => mel_lp_ranges($have)));
}

// The window of row $r that gave no link to play: its reason; the list stays.
/**
 * @param string $lang
 * @param MelLpRow $r
 * @param string $why
 * @param string $detail
 * @return MelAction
 */
function mel_lpv_failed($lang, $r, $why, $detail)
{
    mel_log("lampac: {$r['path']}: no link to play ($why)");
    return mel_dialog(mel_lpv_tr($lang, 'lp_failed', array('src' => $r['src'])), mel_dialog_lines(mel_lpv_why($lang, $why,
        $detail)), array('OK' => mel_close_and(null)));
}

// --- The episodes of the online list: the left and right keys, "Выбрать серию".

// List $st as of episode $e of season $s, its rows the same (of the season): a new rid, the cursor
// kept (on its row when the lines change: the tracks of Collaps of the episode, the filter gone), the folding too; the
// releases, of another episode, asked anew when chosen (lp_only).
/**
 * @param MelState $st
 * @param int $s
 * @param int $e
 * @return MelState
 */
function mel_lpv_ep_state($st, $s, $e)
{
    $ns = $st;
    $ns['rid'] = mel_rid();
    $ns['s'] = $s;
    $ns['e'] = $e;
    $ns['rows'] = array();
    $ns['errors'] = mel_no_errors();
    $ns['lp_only'] = true;
    // The same season: the tracks of the episode (Collaps), the filter of them (mel_lpv_flt_keep).
    if ($s === $st['s'])
    {
        // The yellow line of a filter gone of the episode before: not of this one.
        if (!empty($ns['lp']['flt_gone']))
            $ns['lp']['note'] = '';
        unset($ns['lp']['flt_gone']);
        $ns['lp'] = mel_lpv_flt_keep(mel_lpv_ep_tracks($ns['lp'], $e), $ns);
    }
    if (isset($st['lp']['gc']))
    {
        // Other lines: the cursor on its row (on the line of a balancer: of it).
        $L = mel_lpv_lines($st['lp']);
        $nl = mel_lpv_lines($ns['lp']);
        $ns['lp']['gc'] = $nl === $L ? $st['lp']['gc'] : mel_lpv_put_at($ns['lp'], $nl, mel_lpv_mark($L, $st['lp']['gc'][0]));
        $ns['lp']['gc_new'] = true;
    }
    return $ns;
}

// lp_flip (d prev|next) of the online list $st of an episode: the episode before or after it in its
// season (the rows are of the season: no request), the screen replaced (mel_flip of the releases). Past
// the first or the last episode of the season (season_numbers; none known: back only): nothing - the
// rows of another season are another search.
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_flip($in, $st)
{
    $d = isset($in->d) && is_string($in->d) ? $in->d : '';
    if ($st['e'] < 1 || ($d !== 'prev' && $d !== 'next'))
        return null;
    list($sel, $top) = mel_lpv_pos_keep($in, $st);
    $st['lp']['gc'] = array($sel, $top);
    $n = isset($st['movie']['seasons'][$st['s']]) ? $st['movie']['seasons'][$st['s']] : 0;
    $e = $st['e'] + ($d === 'next' ? 1 : -1);
    if ($e < 1 || ($d === 'next' && $e > $n))
    {
        mel_log('lampac: flip: ' . mel_ep_tag($st) . ', ' . ($e < 1 ? 'the first' : "the last ($n)") . ' of the season, nothing');
        return null;
    }
    $ns = mel_lpv_ep_state($st, $st['s'], $e);
    mel_log('lampac: flip: ' . mel_ep_tag($st) . ' -> ' . mel_ep_tag($ns) . ', the rows kept, row ' . $sel);
    return mel_list_replace($ns);
}

// lp_chosen of "Выбрать серию" (mel_choose_act) of the online list $st: an episode of its season -
// the list of it (as mel_lpv_flip); of another season - the search of that season ('chosen'); the
// screens of the shell taken off with the list.
/**
 * @param stdClass $in
 * @param MelState $st
 * @return MelAction|null
 */
function mel_lpv_chosen($in, $st)
{
    $s = mel_chosen_num($in, 'chosen_s', '/^[1-9][0-9]{0,3}$/D');
    $e = mel_chosen_num($in, 'chosen_e', '/^[1-9][0-9]{0,4}$/D');
    if ($st['e'] < 1 || $s < 1 || $e < 1)
    {
        mel_log('lampac: chosen: no episode, nothing');
        return null;
    }
    $ns = mel_lpv_ep_state($st, $s, $e);
    mel_log('lampac: chosen: ' . mel_ep_tag($st) . ' -> ' . mel_ep_tag($ns) . ($s === $st['s'] ? ', the rows kept' : ', the search'));
    if ($s === $st['s'])
        return mel_list_replace($ns, 3);
    unset($ns['lp']['gc'], $ns['lp']['gc_new']);
    return mel_lpv_search_open($ns, 'chosen');
}

// --- The dispatcher of lp_* (main.php).

/**
 * @param stdClass $in
 * @param string $control
 * @return MelAction|null
 */
function mel_lpv_input($in, $control)
{
    // «Готово» of a check list: its choice is taken first, whatever the list is by now (given once; left, it would
    // stay in the state of the overlay).
    $choice = null;
    if ($control === 'lp_flt_done' || $control === 'lp_srcs_done')
        $choice = mel_kp_done(isset($in->rid) && is_string($in->rid) ? $in->rid : '');
    if ($control === 'lp_tick')
        return mel_lpv_tick($in);
    // Under the player (mel_lpv_lazy): no list.
    if ($control === 'lp_next')
        return mel_lpv_next($in);
    if ($control === 'lp_cancel')
    {
        if (MelLpv::$run && isset($in->sid) && $in->sid === MelLpv::$run['sid'])
            mel_lpv_run_end();
        mel_log('lampac: the search cancelled');
        return mel_close_and(mel_lpv_redraw($in));
    }
    $st = Mel::$state;
    if (!$st || !isset($in->rid) || $in->rid !== $st['rid'])
    {
        mel_log("lampac: $control of no live list");
        return $control === 'lp_move' ? null : mel_error('err_list_expired');
    }
    if ($control === 'lp_find')
        return mel_lpv_on($st['movie'], $st['s'], $st['e']) ? mel_lpv_search_open($st, 'open') : null;
    if (empty($st['lp_on']) || !isset($st['lp']))
        return null;
    if ($control === 'lp_move')
        return mel_lpv_move($in, $st);
    if ($control === 'lp_pick')
        return mel_lpv_pick($in, $st);
    if ($control === 'lp_menu')
        return mel_lpv_menu($in, $st);
    if ($control === 'lp_flt_menu')
        return mel_lpv_flt_open($in, $st);
    if ($control === 'lp_flt_done')
        return mel_lpv_flt_apply($st, $choice);
    if ($control === 'lp_fold')
    {
        list($sel) = mel_lpv_pos_keep($in, $st);
        return mel_lpv_fold_all($st, $sel);
    }
    if ($control === 'lp_qsort')
        return $st['lp']['rows'] ? mel_lpv_qsort_flip($st) : null;
    if ($control === 'lp_flip')
        return mel_lpv_flip($in, $st);
    if ($control === 'lp_choose')
    {
        if ($st['e'] < 1)
            return null;
        mel_lpv_pos_keep($in, $st);
        mel_log('lampac: choose: ' . mel_ep_tag($st) . ' -> seasons screen of the shell');
        return mel_choose_act($st['rid'], 'lp_chosen');
    }
    if ($control === 'lp_chosen')
        return mel_lpv_chosen($in, $st);
    if ($control === 'lp_refresh')
        return mel_lpv_refresh($st);
    if ($control === 'lp_srcs_done')
        return mel_lpv_srcs_apply($st, $choice);
    return null;
}
