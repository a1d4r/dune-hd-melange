<?php
// The pass over the cards of the shell of the titles of the library, in a process of its own: php-cgi of the firmware
// (`php-cgi -q -c bg bg/lib_map.php`), started by php_server (mel_pg_bg_start, links_bg.php) with tmp_dir in
// MEL_BG_TMP. No DuneSystem, no hd_print here: only the functions of the cards of links.php (mel_pg_card_ids,
// mel_pg_parse_card) and mel_str of parse.php; the lines of mel_log go to the result, php_server logs them.

$GLOBALS['MEL_BG_LOG'] = array();

/**
 * @param string $msg
 * @return void
 */
function mel_log($msg)
{
    if (count($GLOBALS['MEL_BG_LOG']) < MEL_PG_BG_LOG_N)
        $GLOBALS['MEL_BG_LOG'][] = strval($msg);
}

$mel_dir = dirname(dirname(__FILE__));
require $mel_dir . '/parse.php';
require $mel_dir . '/links.php';
require $mel_dir . '/links_bg.php';
exit(mel_pg_bg_run(strval(getenv('MEL_BG_TMP'))) ? 0 : 1);
