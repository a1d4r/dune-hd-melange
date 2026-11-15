<?php
// "Мой список" (0.61.0) on the screens of php_server: the title into the list or out of it, its messages, POP UP of a
// tile of the catalog and of the tab "TV". The file, the copy of the backup and the Favorites of the Dune are of
// my_list.php (the settings page has it alone).
// Needs my_list.php, view_catalog.php (aio_catv_*), continue.php (aio_cp_mid_of, aio_cp_card_known), tv_tab.php
// (aio_tv_is_from), main.php (aio_tr, aio_input, aio_error).
// PHP 5.3.6, firmware API only.

// Title $t into the list ($on; aio_ml_add) or out of it (aio_ml_del of aio_ml_in), $dune: the id of its card of the
// Dune ('' - none or not known). -> '' or why not, as they give it.
/**
 * @param AioCatTile $t
 * @param string $dune
 * @param bool $on
 * @return string
 */
function aio_ml_set($t, $dune, $on)
{
    if ($on)
        return aio_ml_add($t, $dune);
    $key = aio_ml_in($t, $dune);
    return $key !== '' ? aio_ml_del($key) : '';
}

// The message of why $why of aio_ml_set ('' none: null): no IMDb or TMDB, no name, the list full, its file of a later
// Melange or not readable, not written.
/**
 * @param string $why
 * @return AioAction|null
 */
function aio_ml_err($why)
{
    $keys = array('full' => 'ml_err_full', 'locked' => 'ml_err_locked', 'write' => 'ml_err_write', 'no id' => 'err_no_imdb',
        'no name' => 'ml_err_name');
    return $why === '' ? null : aio_error(isset($keys[$why]) ? $keys[$why] : 'ml_err_write', $why);
}

// The item of POP UP of tile $id of screen $from (aio_catv_tile_menu): "Добавить в «Мой список»" / "Убрать из «Моего
// списка»" by the list now (aio_ml_in by its card of the Dune known, aio_cp_card_known). None but on a tile of a title
// with IMDb or TMDB (aio_catv_tile).
/**
 * @param string $id
 * @param string $from
 * @param string $lang
 * @return list<array<string, mixed>>
 */
function aio_ml_menu_items($id, $from, $lang)
{
    $t = aio_catv_tile($id, aio_tv_is_from($from));
    if (!$t || aio_ml_key($t) === '')
        return array();
    $in = aio_ml_in($t, aio_cp_card_known($t, aio_cp_mid_of($id))) !== '';
    return array(array(
        GuiMenuItemDef::caption => aio_tr($lang, $in ? 'ml_del' : 'ml_add'),
        GuiMenuItemDef::icon_url => AIO_CATV_ICONS . ($in ? 'cancel_small.png' : 'favorites_small.png'),
        GuiMenuItemDef::action => aio_input('cat_ml', array('from' => $from, 'item' => $id, 'w' => $in ? 'del' : 'add'))));
}

// cat_ml of POP UP (aio_ml_menu_items; w add, del) of tile $in->item of screen $in->from: the title into the list or out
// of it (aio_ml_set; its card of the Dune known, aio_cp_card_known, no request), then the screen anew
// (aio_catv_tile_after: the line of the info panel); not written: the message (aio_ml_err), the list as it was.
/**
 * @param stdClass $in
 * @return AioAction|null
 */
function aio_ml_menu_act($in)
{
    $a = aio_catv_tile_act($in, array('add', 'del'));
    $t = $a['t'];
    if (!$t)
        return $a['err'];
    $why = aio_ml_set($t, aio_cp_card_known($t, aio_cp_mid_of($a['id'])), $a['w'] === 'add');
    return $why !== '' ? aio_ml_err($why) : aio_catv_tile_after($a['from'], "my list {$a['w']}");
}
