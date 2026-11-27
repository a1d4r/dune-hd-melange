<?php
// The overlay of a GComps screen (UI kit, stage 6): what is shown over the screen in the empty full-screen panel ovl,
// the last of its comp_defs (mel_ovl_slot) - the side panel (kit_panel.php), a dialog or a wait of our own, one at a
// time. Here what they share: the open, the close, the stale screen and the state. Required by main.php after kit.php.
// PHP 5.3.6, firmware API only; types (MelOvlScreen, MelOvlState): phpstan-types.neon.
//
// The screen gives the record of itself (MelOvlScreen) to the open and gets it back whole at the close:
//   owner  whose screen: the rid of a list of releases, the key of an own card (MelOc::$cards), the key of an online
//          list. Every key of the overlay carries it (o); a key of an owner with no state (a php_server restart, a
//          screen put anew, a record pushed out) is of a stale screen;
//   map    the action map of the screen whole, with the keys Fav (mel_mlv_key_acts), without RETURN (the shell leaves
//          the screen by it) [device, gcomps_overlay_probe P3]; the open puts the map of the overlay in its place
//          (change_behaviour), the close puts it back;
//   timer  the timer of the screen (GuiTimerDef, null none): off while the overlay is open (a tick replacing the screen,
//          as card_load of the own card, would take the overlay away), back at the close;
//   sel    the sel_state of the screen, given to every change_gcomps of the overlay.
// The open: change_behaviour (the map of the overlay, its timer: the ticks of a wait, else none), then change_gcomps of
// the children of ovl. The close: change_behaviour (the map and the timer of the screen), then the children of ovl
// none - whatever is in it [device P4].
//
// The state is by owner: the screens under the one on top keep their overlays (HOME, another screen with an overlay
// of its own, back). One state is in hand (MelOvl::$st: of the owner that opened or got a key last), those of the
// other owners wait in MelOvl::$rest; a key or a tick with o takes the state of its owner in hand (mel_ovl_stale).
// A record dies:
//   - its screen put anew (get_folder_view, a tick that replaces it) or finished: mel_ovl_drop;
//   - closed, when another owner comes in hand: a closed one is kept only for the keys right after the close; one
//     with what it left to its screen not taken yet - the choice of a check list (kp_done, mel_kp_done), the button
//     and the data of a wait (wt_done, mel_wt_done) - waits as the open ones do;
//   - waiting, its screen gone with no word to the plugin (the shell closed it, a list replaced by a new rid): pushed
//     out as the oldest when more than MEL_OVL_REST wait; a key of a live screen pushed out so is of a stale one;
//   - a php_server restart: all of them (memory only).
//
// The state in hand, the contract: the code of a screen calls only the open of an overlay (mel_kp_open,
// mel_dlg_open, mel_wait_open, mel_lib_win_open), mel_ovl_open_on, mel_ovl_drop, mel_kp_done and mel_wt_done - they find
// the state by the owner. mel_ovl_st, mel_ovl_set, mel_ovl_shown and mel_ovl_close work on the one in hand whoever its owner is:
// only for the files of the overlays, and only after mel_ovl_stale($in) or mel_ovl_open in the same request.
//
// The gate, one for every screen (mel_ovl_under, the first thing mel_handle_user_input does): under its open overlay
// a screen does none of the keys of its own map nor the tick of its timer (sent before the map of the overlay was
// applied) - the overlay was built for the screen as it is. By the record, no names: an input with the control of an
// action of the map of the record and the params of it that name the owner (rid, c, from). Not gated: the event of
// the player (GUI_EVENT_MENU_PLAYBACK_FINISH: no key), the colour keys of egg.php (no param names the screen), and
// what the map does not have - the keys of the overlay, the action of an item or of «Готово» (it comes after the
// close), the inputs of the player and of the dialogs of the shell over the screen (next, the ticks of a wait of the
// shell). A tick of a timer the record does not have: its screen (mel_oc_tick). An own input of the screen the map does
// not have (the action of an item, sent again) under its wait window: the dispatcher, by the lists of the own inputs
// of the screens (mel_scr_under, main.php).
// A tick that puts its screen anew checks mel_ovl_open_on itself, the gate or not: the timer of the shell may be one
// the record does not have (mel_oc_tick). The screens of kit_scr.php are no owners: the o of their keys (ks_key,
// ks_tick) is the id of the screen (MelKs), they have no overlay and their ticks are their own.

// The id of the slot of the overlay.
define('MEL_OVL', 'ovl');
// The overlays of the owners not in hand kept at most.
define('MEL_OVL_REST', 8);

class MelOvl
{
    // The overlay in hand - of the owner that opened one or got a key of one last, null none: scr, the record of the
    // screen; open, shown; the state of what is shown (kp: the side panel) or what it left to its screen after the
    // close (kp_done: the choice of a check list; wt_done: the button and the data of a wait). Kept after the close:
    // a key that comes after it (sent before its map was applied) is of a live screen. Memory of php_server only.
    /** @var MelOvlState|null */
    public static $st = null;
    // The overlays of the other owners that wait (the open ones, a closed one with kp_done or wt_done), by owner, the
    // oldest first; MEL_OVL_REST at most (see above).
    /** @var array<string, MelOvlState> */
    public static $rest = array();
}

// The overlay in hand (see above), null none.
/**
 * @return MelOvlState|null
 */
function mel_ovl_st()
{
    return MelOvl::$st;
}

// State $st, the one in hand changed, kept in its place.
/**
 * @param MelOvlState $st
 * @return void
 */
function mel_ovl_set($st)
{
    MelOvl::$st = $st;
}

// The overlay in hand is open and shows $kind (kp, dlg, wt, lw).
/**
 * @param string $kind
 * @return bool
 */
function mel_ovl_shown($kind)
{
    return MelOvl::$st && MelOvl::$st['open'] && isset(MelOvl::$st[$kind]);
}

// The overlay in hand out of it: an open one, and a closed one with what it left to its screen not taken yet (kp_done,
// wt_done), waits in MelOvl::$rest as the newest (the oldest over MEL_OVL_REST pushed out, logged); another closed one is gone.
/**
 * @return void
 */
function mel_ovl_park()
{
    $st = MelOvl::$st;
    MelOvl::$st = null;
    if (!$st || !($st['open'] || isset($st['kp_done']) || isset($st['wt_done'])))
        return;
    $owner = $st['scr']['owner'];
    unset(MelOvl::$rest[$owner]);
    MelOvl::$rest[$owner] = $st;
    $n = count(MelOvl::$rest) - MEL_OVL_REST;
    if ($n > 0)
    {
        MelOvl::$rest = array_slice(MelOvl::$rest, $n, null, true);
        mel_log("kit overlay: $n of the oldest waiting pushed out");
    }
}

// The overlay of owner $owner in hand: it is there, or it waited (the one in hand parked, mel_ovl_park). No state of
// this owner: false, nothing changed.
/**
 * @param string $owner
 * @return bool
 */
function mel_ovl_take($owner)
{
    if (MelOvl::$st && MelOvl::$st['scr']['owner'] === $owner)
        return true;
    if (!isset(MelOvl::$rest[$owner]))
        return false;
    $st = MelOvl::$rest[$owner];
    unset(MelOvl::$rest[$owner]);
    mel_ovl_park();
    MelOvl::$st = $st;
    return true;
}

// The last component of the comp_defs of a screen with an overlay: the empty full-screen panel it opens in.
/**
 * @return MelGcComp
 */
function mel_ovl_slot()
{
    return mel_gc_panel(MEL_OVL, mel_gc_at(1920, 1080, 0, 0), array());
}

// A key $control of the overlay of owner $owner with $params: handle_user_input with o.
/**
 * @param string $control
 * @param string $owner
 * @param array<string, string> $params
 * @return MelAction
 */
function mel_ovl_input($control, $owner, $params = array())
{
    return mel_input($control, array_merge(array('o' => $owner), $params));
}

// change_gcomps of changes $ch with the sel_state of the screen $sel, then $post.
/**
 * @param list<MelGcChange|array<string, mixed>> $ch
 * @param string|null $sel
 * @param MelAction|null $post
 * @return MelAction
 */
function mel_ovl_change($ch, $sel, $post = null)
{
    $data = array(ChangeGCompsActionData::change_defs => $ch, ChangeGCompsActionData::num_steps => MEL_GC_STEPS);
    if ($sel !== null)
        $data[ChangeGCompsActionData::sel_state] = $sel;
    if ($post !== null)
        $data[ChangeGCompsActionData::post_action] = $post;
    return array(GuiAction::handler_string_id => CHANGE_GCOMPS_ACTION_ID, GuiAction::data => $data);
}

// change_behaviour: the action map $map, the timer $timer (null none), then $post (null none).
/**
 * @param array<string, MelAction> $map
 * @param array<string, int>|null $timer
 * @param MelAction|null $post
 * @return MelAction
 */
function mel_ovl_behaviour($map, $timer, $post)
{
    return array(GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID, GuiAction::data => array(
        ChangeBehaviourActionData::actions => $map,
        ChangeBehaviourActionData::timer => $timer,
        ChangeBehaviourActionData::post_action => $post));
}

// Open over the screen of record $scr (see above), in hand from now, the overlay of state $st (array('kp' => ...)), action map $map,
// timer $timer (GuiTimerDef, null none; a wait: its ticks) and components $defs (the children of ovl), then $post (a
// change_gcomps of them: the panel slides in). A record that is not one (owner '' or not a string, map not an array, a
// timer neither an array nor null, sel neither a string nor null): null, logged.
/**
 * @param array<string, mixed> $scr
 * @param array<string, mixed> $st
 * @param array<string, MelAction> $map
 * @param list<MelGcComp> $defs
 * @param MelAction|null $post
 * @param array<string, int>|null $timer
 * @return MelAction|null
 */
function mel_ovl_open($scr, $st, $map, $defs, $post = null, $timer = null)
{
    $owner = isset($scr['owner']) ? $scr['owner'] : null;
    $smap = isset($scr['map']) ? $scr['map'] : null;
    $stimer = array_key_exists('timer', $scr) ? $scr['timer'] : false;
    $sel = array_key_exists('sel', $scr) ? $scr['sel'] : false;
    if (!is_string($owner) || $owner === '' || !is_array($smap) || !($stimer === null || is_array($stimer)) ||
        !($sel === null || is_string($sel)))
    {
        mel_log('kit overlay: no record of the screen, not opened');
        return null;
    }
    /** @var MelOvlScreen $rec */
    $rec = array('owner' => $owner, 'map' => $smap, 'timer' => $stimer, 'sel' => $sel);
    $named = false;
    foreach ($smap as $a)
        $named = $named || mel_ovl_names($a, $owner);
    // The gate (mel_ovl_under) knows the keys of the screen by the owner in their params.
    if (!$named)
        mel_log('kit overlay: no action of the map of the screen names its owner, its keys not gated');
    // The overlay of another owner waits; the one this owner had is replaced.
    if (MelOvl::$st && MelOvl::$st['scr']['owner'] !== $owner)
        mel_ovl_park();
    unset(MelOvl::$rest[$owner]);
    MelOvl::$st = array_merge($st, array('scr' => $rec, 'open' => true));
    return mel_ovl_behaviour($map, $timer, mel_ovl_change(array(mel_gc_change(MEL_OVL, null, GCOMP_TRANSITION_NONE,
        $defs)), $sel, $post));
}

// The close of the open overlay in hand, then $post: the record of its screen back (its map, its timer, its sel_state), the
// children of ovl none; of the state only $keep (array('kp_done' => ...), array('wt_done' => ...)) left. None open:
// $post.
/**
 * @param MelAction|null $post
 * @param array<string, mixed> $keep
 * @return MelAction|null
 */
function mel_ovl_close($post = null, $keep = array())
{
    $st = MelOvl::$st;
    if (!$st || !$st['open'])
        return $post;
    $scr = $st['scr'];
    MelOvl::$st = array_merge($keep, array('scr' => $scr, 'open' => false));
    return mel_ovl_behaviour($scr['map'], $scr['timer'], mel_ovl_change(array(mel_gc_change(MEL_OVL, null,
        GCOMP_TRANSITION_NONE, array())), $scr['sel'], $post));
}

// The screen of owner $owner put anew (get_folder_view, a tick that replaces it): the overlay it opened is not that of
// the new one - its state gone (the close would put back the map of the old view; a key of it after this one is of a
// stale screen, mel_ovl_stale), in hand or waiting. The overlays of the other owners stay.
/**
 * @param string $owner
 * @return void
 */
function mel_ovl_drop($owner)
{
    if (MelOvl::$st && MelOvl::$st['scr']['owner'] === $owner)
        MelOvl::$st = null;
    unset(MelOvl::$rest[$owner]);
}

// The overlay of the screen of owner $owner is open, in hand or waiting; $kind: and of that kind (as mel_ovl_shown).
/**
 * @param string $owner
 * @param string|null $kind
 * @return bool
 */
function mel_ovl_open_on($owner, $kind = null)
{
    $st = isset(MelOvl::$rest[$owner]) ? MelOvl::$rest[$owner] : MelOvl::$st;
    return $st && $st['open'] && $st['scr']['owner'] === $owner && ($kind === null || isset($st[$kind]));
}

// The params of action $a of the map of a screen that name its owner $owner: the owner itself (rid, c) or the
// media_url of its screen, "<kind>:<owner>" (from of the keys Fav and search, mel_mlv_key_acts).
/**
 * @param mixed $a
 * @param string $owner
 * @return array<string, string>
 */
function mel_ovl_names($a, $owner)
{
    $p = is_array($a) && isset($a[GuiAction::params]) && is_array($a[GuiAction::params]) ? $a[GuiAction::params] : array();
    $out = array();
    $n = strlen($owner) + 1;
    foreach ($p as $k => $v)
    {
        if (is_string($v) && ($v === $owner || substr($v, -$n) === ":$owner"))
            $out[$k] = $v;
    }
    return $out;
}

// Input $in is the key of action $a of the map of the screen of owner $owner: its control, and the params of $a that
// name the owner (mel_ovl_names; one at least) the same in $in - the other params may differ (P+ of a map built
// without it).
/**
 * @param mixed $a
 * @param string $owner
 * @param stdClass $in
 * @return bool
 */
function mel_ovl_key_of($a, $owner, $in)
{
    $names = mel_ovl_names($a, $owner);
    if (!$names || !isset($a[GuiAction::params]['control_id'], $in->control_id) ||
        $a[GuiAction::params]['control_id'] !== $in->control_id)
        return false;
    foreach ($names as $k => $v)
    {
        if (!isset($in->$k) || $in->$k !== $v)
            return false;
    }
    return true;
}

// The gate (see above): input $in is a key of the map or the tick of the timer of a screen under its open overlay, in
// hand or waiting (mel_ovl_key_of; not the event of the player) - not done, logged.
/**
 * @param stdClass $in
 * @return bool
 */
function mel_ovl_under($in)
{
    $all = array_values(MelOvl::$rest);
    if (MelOvl::$st)
        $all[] = MelOvl::$st;
    foreach ($all as $st)
    {
        foreach ($st['open'] ? $st['scr']['map'] : array() as $ev => $a)
        {
            if ($ev === GUI_EVENT_MENU_PLAYBACK_FINISH || !mel_ovl_key_of($a, $st['scr']['owner'], $in))
                continue;
            mel_log("kit overlay: {$in->control_id} of the screen under its overlay, none");
            return true;
        }
    }
    return false;
}

// A key $in of an overlay of a stale screen - no o, or no state of its owner (a php_server restart, a screen put anew,
// a record pushed out): close_and_run leaves the screen, logged; the states of the other owners as they were. Else
// null: the screen is live (its overlay open, or closed by the key before this one) and its state is in hand
// (mel_ovl_take) - the overlay of another screen opened since waits.
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_ovl_stale($in)
{
    $o = isset($in->o) && is_string($in->o) ? $in->o : '';
    if ($o !== '' && mel_ovl_take($o))
        return null;
    mel_log('kit overlay: key of a stale screen, closed');
    return array(GuiAction::handler_string_id => CLOSE_AND_RUN_ACTION_ID,
        GuiAction::data => array(CloseAndRunActionData::post_action => null));
}
