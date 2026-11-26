#!/bin/sh
# global_actions/uninstall. The shell removes the plugin's own files, but not
# the supplier copies in /tmp (aicard, aio): without this the menu items stay until reboot.
# A copy written by another plugin with the same id is left alone.
# Local cards custom_mdb/user_movie/m-melange-* stay on purpose: the user's history (catalog_card.php).
# Also removes the shell's files of the dialogs of the catalog rows (config_id melange_rows_<tab>_order
# and melange_rows_<tab>_g_<g>, mel_catv_lcfg_id) and those of the sources of the online list and of its voices
# (melange_lp_sources, melange_lp_voices: dialogs of the versions before the side panel), which live outside the
# plugin's dirs, and the
# directory of its files of the tab "TV" (tv_tab.php), named by the plugin: the shell keeps it.

for id in aicard aio; do
    f=${MELANGE_TMP:-$FS_PREFIX/tmp}/movie_suppliers/$id
    grep -q '"plugin":"melange"' "$f" 2>/dev/null && rm -f "$f"
done
rm -f "$FS_PREFIX"/config/lcfg_melange_rows_*.txt
rm -f "$FS_PREFIX"/config/lcfg_melange_lp_sources.txt
rm -f "$FS_PREFIX"/config/lcfg_melange_lp_voices.txt
rm -rf "$FS_PREFIX/flashdata/plugins_epfs/melange"
exit 0
