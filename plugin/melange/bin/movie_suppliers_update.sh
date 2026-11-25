#!/bin/sh
# Called by shell_ext async_worker after install and whenever
# "<version_index>:<ids>" changes: movie_suppliers_update.sh <id> <caption> [...].
# Arguments are ignored: the plugin registers its two suppliers, the ids of
# <movie_suppliers> of the manifest. The shell copies the written files to
# /tmp/movie_suppliers/ on every boot.
# Two items of "Play..." of the card of the Dune: aicard ("Melange") opens the
# own card of the title (control card), aio ("Melange - watch") the list
# (control play; its id is the prefix of the uids of the history: [NN%] on the
# button and "Continue" go by it). The shell sorts them by id (strnatcasecmp of
# cmp_suppliers): aicard above aio.
# Choosing an item runs play_action (main.php) instead of bin; bin only
# answers if the shell ever ignores play_action.
# Output is not logged anywhere; the messages are for running it by hand.

name=melange

# FS_PREFIX is unset on older Android models, where paths start at the root.
case "$FS_PREFIX" in
    /*[!A-Za-z0-9/._-]* | [!/]*)
        echo "$name: FS_PREFIX is relative or unsafe, nothing written"
        exit 1
        ;;
esac

mydir=$(cd "$(dirname "$0")" && pwd)
# The path goes into JSON and into a shell command line.
case "$mydir" in
    /*[!A-Za-z0-9/._-]* | [!/]* | '')
        echo "$name: unsafe plugin path, nothing written: $mydir"
        exit 1
        ;;
esac

dir="${MELANGE_FLASHDATA:-$FS_PREFIX/flashdata}/plugins_data/$name/movie_suppliers"

# write <supplier id> <caption key> <control_id>: one JSON line, atomically.
write() {
    tmp="$dir/.$1.tmp"
    if printf '{"plugin":"%s","caption":"%%tr%%%s","common_caption":"%%ext%%Melange","langs":"any","supported_video_types":"video","bin":"sh %s/supplier.sh","playback_type":"stream_special","play_action":{"handler_string_id":"plugin_handle_user_input","plugin_name":"%s","params":{"handler_id":"aio","control_id":"%s"}},"icon_url":"plugin_file://%%%s%%/icons/logo.png"}\n' \
        "$name" "$2" "$mydir" "$name" "$3" "$name" >"$tmp" &&
        mv -f "$tmp" "$dir/$1"; then
        echo "$name: supplier $1 written"
    else
        rm -f "$tmp"
        echo "$name: failed to write supplier $1"
        return 1
    fi
}

mkdir -p "$dir" || {
    echo "$name: failed to make $dir"
    exit 1
}
rc=0
write aicard supplier_card_caption card || rc=1
write aio supplier_caption play || rc=1
exit "$rc"
