<?php
// The colour keys red, green, yellow, blue in a row, MEL_EGG_WINDOW s at most from the first to the last, on a
// screen of Melange: a music video full screen, as the trailer of the own card (mel_ct_clip: no history, no
// marks). Its stream URL is asked of RuTube at that moment (signed for a day, its CDN node by the address that
// asks): nothing kept. Only another colour key and a pause break a run; other keys and inputs (arrows, ENTER,
// cat_info) do not. A failure (network, status, reply) shows nothing, one log line.
// The keys come as the input "egg" (mel_egg_acts in the actions of a screen) and, in a list, as its sort
// (gc_sort): the list sorts on every key, the sort before the run is put back when the video plays.
// Needs main.php (mel_input, mel_log, mel_http_get, mel_sort_set), catalog_card.php (MEL_CARD_DELAY),
// card_trailer.php (MEL_CT_CONNECT, MEL_CT_TOTAL, mel_ct_url_ok, mel_ct_clip), view_gcomps.php (mel_gc_sort_keys).

// From the first key to the last, s.
define('MEL_EGG_WINDOW', 3);
// The videos on RuTube, tried in turn.
define('MEL_EGG_IDS', 'c6cc4d620b1d4338901770a44b3e82f4 ac1caf5cbb710e9e7d9b7812762c9def');
define('MEL_EGG_API', 'https://rutube.ru/api/play/options/');
// The reply, bytes: ~140 KB (15.11.2026).
define('MEL_EGG_MAX', 524288);

class MelEgg
{
    // The run so far: 'n' its keys matched (0 none), 'at' the time of its first key (microtime), 'sort' the sort
    // of new lists (the settings) at that key: array(key, reversed); null - none yet.
    /** @var array{n: int, at: float, sort: array{string, bool}|null} */
    public static $run = array('n' => 0, 'at' => 0.0, 'sort' => null);
}

// The colour keys in the order of the run: name => event.
/**
 * @return array<string, string>
 */
function mel_egg_keys()
{
    return array('red' => GUI_EVENT_KEY_A_RED, 'green' => GUI_EVENT_KEY_B_GREEN, 'yellow' => GUI_EVENT_KEY_C_YELLOW,
        'blue' => GUI_EVENT_KEY_D_BLUE);
}

// The actions of the colour keys of a screen: the input "egg"; "Please wait" as for the trailer, the first
// three keys answer at once.
/**
 * @return array<string, MelAction>
 */
function mel_egg_acts()
{
    $a = array();
    foreach (mel_egg_keys() as $ck => $ev)
        $a[$ev] = mel_input('egg', array('ck' => $ck), MEL_CARD_DELAY);
    return $a;
}

// Colour key $ck (a name of mel_egg_keys) at $now (microtime) -> the keys of the run matched with it: 1 - red, a
// new run (also in the middle of one); 4 - the run is complete, the next starts anew; 0 - broken (another key,
// over MEL_EGG_WINDOW s from the first, the clock moved back).
/**
 * @param string $ck
 * @param float $now
 * @return int
 */
function mel_egg_press($ck, $now)
{
    $order = array_keys(mel_egg_keys());
    $r = MelEgg::$run;
    if ($ck === $order[0])
        $n = 1;
    else if (mel_egg_alive($r, $now) && $ck === $order[$r['n']])
        $n = $r['n'] + 1;
    else
        $n = 0;
    MelEgg::$run['n'] = $n < 4 ? $n : 0;
    if ($n === 1)
        MelEgg::$run['at'] = $now;
    return $n;
}

// Run $r (MelEgg::$run) goes on at $now (microtime): begun, MEL_EGG_WINDOW s at most from its first key, the clock
// not back.
/**
 * @param array{n: int, at: float, sort: array{string, bool}|null} $r
 * @param float $now
 * @return bool
 */
function mel_egg_alive($r, $now)
{
    return $r['n'] > 0 && $now >= $r['at'] && $now - $r['at'] <= MEL_EGG_WINDOW;
}

// The colour key of input $in of control $control: the name of mel_egg_keys, '' for none.
/**
 * @param stdClass $in
 * @param mixed $control
 * @return string
 */
function mel_egg_color($in, $control)
{
    $colours = mel_egg_keys();
    if ($control === 'egg')
        return isset($in->ck) && is_string($in->ck) && isset($colours[$in->ck]) ? $in->ck : '';
    if ($control !== 'gc_sort' || !isset($in->k) || !is_string($in->k))
        return '';
    $keys = mel_gc_sort_keys();
    $ck = isset($keys[$in->k]) ? array_search($keys[$in->k][0], $colours, true) : false;
    return is_string($ck) ? $ck : '';
}

// A colour key of input $in of control $control goes to the run, at Mel::$t0; true - it is complete. A red key
// keeps the sort of new lists (the settings), a red key again in a run keeps the one before the first.
/**
 * @param stdClass $in
 * @param mixed $control
 * @return bool
 */
function mel_egg_key($in, $control)
{
    $ck = mel_egg_color($in, $control);
    if ($ck === '')
        return false;
    $r = MelEgg::$run;
    $now = floatval(Mel::$t0);
    $alive = mel_egg_alive($r, $now);
    $n = mel_egg_press($ck, $now);
    if ($n === 1 && !($alive && $r['sort'] !== null))
        MelEgg::$run['sort'] = array(Mel::$settings['sort'], Mel::$settings['sort_rev']);
    return $n === 4;
}

// Every input of the plugin (mel_handle_user_input): a run of colour keys complete with it -> the video
// (mel_egg_play), and the sort of new lists before the run put back (data_dir/list_sort, the settings of the
// operation; the list on screen stays as it is); else null, nothing changed: in a list the key sorts as ever.
/**
 * @param stdClass $in
 * @param mixed $control
 * @return MelAction|null
 */
function mel_egg_input($in, $control)
{
    if (!mel_egg_key($in, $control))
        return null;
    $sort = MelEgg::$run['sort'];
    MelEgg::$run['sort'] = null;
    $v = mel_egg_play();
    if ($v !== null && $sort !== null && $sort !== array(Mel::$settings['sort'], Mel::$settings['sort_rev']) &&
        !mel_sort_set($sort[0], $sort[1]))
        mel_log('egg: sort ' . mel_sort_tag($sort[0], $sort[1]) . ' not saved');
    return $v;
}

// The video: the stream URL of the first of MEL_EGG_IDS that gives one -> vod_play; else null. A transport error
// (no network, a timeout) ends the tries. One log line.
/**
 * @return MelAction|null
 */
function mel_egg_play()
{
    $fails = array();
    foreach (explode(' ', MEL_EGG_IDS) as $id)
    {
        $url = MEL_EGG_API . $id . '/?no_404=true&referer=https%3A%2F%2Frutube.ru';
        $err = '';
        $t = microtime(true);
        list($code, $body) = mel_http_get($url, $err, MEL_CT_CONNECT, MEL_CT_TOTAL, MEL_EGG_MAX);
        $ms = intval(round((microtime(true) - $t) * 1000));
        $j = $err === '' && $code === 200 ? json_decode($body, true) : null;
        $vb = is_array($j) && isset($j['video_balancer']) && is_array($j['video_balancer']) ? $j['video_balancer'] : array();
        $m3u8 = isset($vb['m3u8']) && is_string($vb['m3u8']) ? $vb['m3u8'] : '';
        if (strncmp($m3u8, 'https://', 8) === 0 && mel_ct_url_ok($m3u8))
        {
            mel_log('egg: ' . ($fails ? implode(', ', $fails) . ', ' : '') . "$id: 200 $ms ms");
            return mel_ct_clip('aio.egg', 'Never Gonna Give You Up', 'Rick Astley', '', $m3u8);
        }
        $fails[] = "$id: " . ($err !== '' ? $err : ($code !== 200 ? strval($code) : (is_array($j) ? 'no https m3u8' : 'not JSON'))) .
            " $ms ms";
        if ($err !== '')
            break;
    }
    mel_log('egg: fail, ' . implode(', ', $fails));
    return null;
}
