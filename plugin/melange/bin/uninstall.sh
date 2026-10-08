#!/bin/sh
# global_actions/uninstall. The shell removes the plugin's own files, but not
# the supplier copy in /tmp: without this the menu item stays until reboot.
# A copy written by another plugin with the same id is left alone.

f=${MELANGE_TMP:-$FS_PREFIX/tmp}/movie_suppliers/aio
grep -q '"plugin":"melange"' "$f" 2>/dev/null && rm -f "$f"
exit 0
