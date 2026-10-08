#!/bin/sh
# Movie supplier bin. Choosing the item runs play_action (main.php) instead,
# so this only answers if play_action was ignored: an error dialog.
# stdout: exactly one JSON object, exit code 0. Diagnostics go to stderr,
# which the shell appends to /tmp/run/aio__supplier.log.

cat >/dev/null
printf '%s bin called instead of play_action: %s\n' "$(date '+%F %T')" "$*" >&2
printf '{"error":{"message":"%%ext%%<key_global>melange_plugin_err_unsupported</key_global>"}}\n'
