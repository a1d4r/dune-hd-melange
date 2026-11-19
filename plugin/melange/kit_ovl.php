<?php
// The overlay of a GComps screen (UI kit, stage 6): what is shown over the screen in the empty full-screen panel ovl,
// the last of its comp_defs (mel_ovl_slot) - the side panel (kit_panel.php), a dialog or a wait of our own, one at a
// time. Here what they share: the open, the close, the stale screen and the state. Required by main.php after kit.php.
// PHP 5.3.6, firmware API only; types (MelOvlScreen, MelOvlState): phpstan-types.neon.
//
// The screen gives the record of itself (MelOvlScreen) to the open and gets it back whole at the close:
//   owner  whose screen: the rid of a list of releases, the key of an own card (MelOc::$cards), the key of an online
//          list. Every key of the overlay carries it (o); a key of another owner, or with no state (a php_server
//          restart), is of a stale screen;
//   map    the action map of the screen, without RETURN (the shell leaves the screen by it) [device, gcomps_overlay_probe
//          P3]; the open puts the map of the overlay in its place (change_behaviour), the close puts it back;
//   timer  the timer of the screen (GuiTimerDef, null none): off while the overlay is open (a tick replacing the screen,
//          as card_load of the own card, would take the overlay away), back at the close;
//   sel    the sel_state of the screen, given to every change_gcomps of the overlay.
// The open: change_behaviour (the map of the overlay, no timer), then change_gcomps of the children of ovl. The close:
// change_behaviour (the map and the timer of the screen), then the children of ovl none - whatever is in it [device P4].

// The id of the slot of the overlay.
define('MEL_OVL', 'ovl');

class MelOvl
{
    // The overlay of the screen that opened the last one, null none: scr, the record of the screen; open, shown; the
    // state of what is shown (kp: the side panel) or what it left to its screen after the close (kp_done: the choice of
    // a check list). Kept after the close: a key that comes after it (sent before its map was applied) is of a live
    // screen. Memory of php_server only.
    /** @var MelOvlState|null */
    public static $st = null;
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

// change_behaviour: the action map $map, the timer $timer (null none), then $post.
/**
 * @param array<string, MelAction> $map
 * @param array<string, int>|null $timer
 * @param MelAction $post
 * @return MelAction
 */
function mel_ovl_behaviour($map, $timer, $post)
{
    return array(GuiAction::handler_string_id => CHANGE_BEHAVIOUR_ACTION_ID, GuiAction::data => array(
        ChangeBehaviourActionData::actions => $map,
        ChangeBehaviourActionData::timer => $timer,
        ChangeBehaviourActionData::post_action => $post));
}

// Open over the screen of record $scr (see above) the overlay of state $st (array('kp' => ...)), action map $map and
// components $defs (the children of ovl), then $post (a change_gcomps of them: the panel slides in). A record that is
// not one (owner '' or not a string, map not an array, a timer neither an array nor null, sel neither a string nor
// null): null, logged.
/**
 * @param array<string, mixed> $scr
 * @param array<string, mixed> $st
 * @param array<string, MelAction> $map
 * @param list<MelGcComp> $defs
 * @param MelAction|null $post
 * @return MelAction|null
 */
function mel_ovl_open($scr, $st, $map, $defs, $post = null)
{
    $owner = isset($scr['owner']) ? $scr['owner'] : null;
    $smap = isset($scr['map']) ? $scr['map'] : null;
    $timer = array_key_exists('timer', $scr) ? $scr['timer'] : false;
    $sel = array_key_exists('sel', $scr) ? $scr['sel'] : false;
    if (!is_string($owner) || $owner === '' || !is_array($smap) || !($timer === null || is_array($timer)) ||
        !($sel === null || is_string($sel)))
    {
        mel_log('kit overlay: no record of the screen, not opened');
        return null;
    }
    /** @var MelOvlScreen $rec */
    $rec = array('owner' => $owner, 'map' => $smap, 'timer' => $timer, 'sel' => $sel);
    MelOvl::$st = array_merge($st, array('scr' => $rec, 'open' => true));
    return mel_ovl_behaviour($map, null, mel_ovl_change(array(mel_gc_change(MEL_OVL, null, GCOMP_TRANSITION_NONE,
        $defs)), $sel, $post));
}

// The close of the open overlay, then $post: the record of its screen back (its map, its timer, its sel_state), the
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

// A key $in of an overlay of a stale screen - no state (a php_server restart) or one of another owner than its o:
// close_and_run leaves the screen, logged. Else null: the screen is live (its overlay open, or closed by the key before
// this one).
/**
 * @param stdClass $in
 * @return MelAction|null
 */
function mel_ovl_stale($in)
{
    $o = isset($in->o) && is_string($in->o) ? $in->o : '';
    $st = MelOvl::$st;
    if ($st && $o !== '' && $st['scr']['owner'] === $o)
        return null;
    mel_log('kit overlay: key of a stale screen, closed');
    return array(GuiAction::handler_string_id => CLOSE_AND_RUN_ACTION_ID,
        GuiAction::data => array(CloseAndRunActionData::post_action => null));
}
