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
// of its own, back). One state is in hand (MelOvl::$st: of the owner that opened or got a key last), the open ones of
// the other owners wait in MelOvl::$rest; a key or a tick with o takes the state of its owner in hand
// (mel_ovl_stale). The files of the overlays read and write the one in hand only by mel_ovl_st, mel_ovl_set and
// mel_ovl_shown. A record dies:
//   - its screen put anew (get_folder_view, a tick that replaces it) or finished: mel_ovl_drop;
//   - closed, when another owner comes in hand: a closed one is kept only for the keys right after the close and for
//     the choice of a check list (mel_kp_done), both before any key of another screen;
//   - open, its screen gone with no word to the plugin (the shell closed it, a list replaced by a new rid): pushed out
//     as the oldest when more than MEL_OVL_REST wait; a key of a live screen pushed out so is of a stale one;
//   - a php_server restart: all of them (memory only).
// The gate: under its open overlay (mel_ovl_open_on) a screen does none of its own keys that would change what the
// overlay was built for - the list of releases gc_move, gc_pick, gc_info (mel_gc_input), the online list all after
// its service ones (mel_lpv_input), the own card card_move, card_enter, card_menu and its tick (mel_oc_input,
// mel_oc_tick).

// The id of the slot of the overlay.
define('MEL_OVL', 'ovl');
// The open overlays of the owners not in hand kept at most.
define('MEL_OVL_REST', 8);

class MelOvl
{
    // The overlay in hand - of the owner that opened one or got a key of one last, null none: scr, the record of the
    // screen; open, shown; the state of what is shown (kp: the side panel) or what it left to its screen after the
    // close (kp_done: the choice of a check list). Kept after the close: a key that comes after it (sent before its
    // map was applied) is of a live screen. Memory of php_server only.
    /** @var MelOvlState|null */
    public static $st = null;
    // The open overlays of the other owners, by owner, the oldest first; MEL_OVL_REST at most (see above).
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

// The overlay in hand out of it: an open one waits in MelOvl::$rest as the newest (the oldest over MEL_OVL_REST
// pushed out, logged), a closed one is gone.
/**
 * @return void
 */
function mel_ovl_park()
{
    $st = MelOvl::$st;
    MelOvl::$st = null;
    if (!$st || !$st['open'])
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
    // The overlay of another owner waits; the one this owner had is replaced.
    if (MelOvl::$st && MelOvl::$st['scr']['owner'] !== $owner)
        mel_ovl_park();
    unset(MelOvl::$rest[$owner]);
    MelOvl::$st = array_merge($st, array('scr' => $rec, 'open' => true));
    return mel_ovl_behaviour($map, $timer, mel_ovl_change(array(mel_gc_change(MEL_OVL, null, GCOMP_TRANSITION_NONE,
        $defs)), $sel, $post));
}

// The close of the open overlay in hand, then $post: the record of its screen back (its map, its timer, its sel_state), the
// children of ovl none; of the state only $keep (array('kp_done' => ...)) left. None open: $post.
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

// The overlay of the screen of owner $owner is open, in hand or waiting: a key of the screen itself (sent before the map of the overlay was
// applied) and a tick of its timer are not done under it.
/**
 * @param string $owner
 * @return bool
 */
function mel_ovl_open_on($owner)
{
    return isset(MelOvl::$rest[$owner]) ||
        (MelOvl::$st && MelOvl::$st['open'] && MelOvl::$st['scr']['owner'] === $owner);
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
