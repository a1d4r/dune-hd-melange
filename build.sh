#!/bin/sh
# Build dist/dune_plugin_melange_<version>.zip from plugin/melange.
# With <check_update> in the manifest, also build the online update channel in
# dist/pages/melange/: the zip's files as .tar.gz (the updater takes tar.gz
# only) and update_info.xml (schema 2). dist/pages is the Pages site root.
# Usage: sh build.sh

set -eu

root=$(cd "$(dirname "$0")" && pwd)
name=melange
src="$root/plugin/$name"
manifest="$src/dune_plugin.xml"

shellcheck -s sh "$root/build.sh" "$src"/bin/*.sh
# CGI of the plugin: every file there runs as sh.
shellcheck -s sh "$src"/www/cgi-bin/*

if command -v php >/dev/null 2>&1; then
    find "$src" -name '*.php' | sort | while read -r f; do
        php -l "$f" >/dev/null || { php -l "$f"; exit 1; }
    done
else
    echo "php not in PATH: php -l skipped" >&2
fi

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$manifest")
out="$root/dist/dune_plugin_${name}_$version.zip"
mkdir -p "$root/dist"
rm -f "$out"
# Own settings and addresses never go into the zip; nor the source of the
# icon (the plugin uses logo.png) and the docs (*.md).
(cd "$src" && zip -q -X -r "$out" . -x '.DS_Store' -x '*/.DS_Store' -x '*_url.txt' -x 'settings.json' \
    -x '*/settings.json' -x 'icons/logo.svg' -x '*.md')
if unzip -Z1 "$out" | grep -E '(^|/)([^/]*_url\.txt|settings\.json)$'; then
    rm -f "$out"
    echo "secrets in the zip (*_url.txt, settings.json): removed" >&2
    exit 1
fi
unzip -l "$out"

# No leftovers of an earlier build, with or without <check_update> now.
pages="$root/dist/pages/$name"
rm -rf "$pages"
grep -q '<check_update>' "$manifest" || exit 0

# --- Online update channel (check_update schema 2).

fail() {
    echo "update channel: $*" >&2
    exit 1
}
# tag <name> <file>: text of the first <name>...</name> written on one line.
tag() { sed -n "s:.*<$1>\(.*\)</$1>.*:\1:p" "$2" | head -n 1; }
md5_of() {
    if command -v md5sum >/dev/null 2>&1; then
        md5sum <"$1" | cut -c 1-32
    else
        md5 -q "$1"
    fi
}

[ "$(tag name "$manifest")" = "$name" ] || fail "manifest <name> is not $name"
vi=$(tag version_index "$manifest")
case "$vi" in '' | 0* | *[!0-9]*) fail "bad version_index '$vi'" ;; esac
case "$version" in '' | *[!0-9A-Za-z.-]*) fail "bad version '$version'" ;; esac
channel=$(sed -n '/<check_update>/,/<\/check_update>/s:.*<url>\(.*\)</url>.*:\1:p' "$manifest")
# The site serves dist/pages as is, so the URL must end in <name>/update_info.xml.
case "$channel" in
    https://*/"$name"/update_info.xml) ;;
    *) fail "check_update url '$channel' is not https://.../$name/update_info.xml" ;;
esac
caption=$(sed -n 's/^plugin_caption = //p' "$src/translations/dune_language_russian.txt" |
    sed 's/&/\&amp;/g; s/</\&lt;/g; s/>/\&gt;/g')
[ -n "$caption" ] || fail "no plugin_caption in translations/dune_language_russian.txt"

archive="dune_plugin_${name}_$version.tar.gz"
tgz="$pages/$archive"
info="$pages/update_info.xml"
url="${channel%/update_info.xml}/$archive"
mkdir -p "$pages"

# The zip's files, directories included, as paths from the plugin root: the
# zip is built by exclusion, so take its own list.
files=$(mktemp)
trap 'rm -f "$files"' EXIT
unzip -Z1 "$out" | sed 's:/$::' | LC_ALL=C sort >"$files"
if tar --version 2>/dev/null | grep -q 'GNU tar'; then
    set -- --format=gnu --owner=0 --group=0 --numeric-owner
else
    # bsdtar (macOS): no AppleDouble ._* entries, xattrs, ACLs or file flags.
    set -- --format=gnutar --uid 0 --gid 0 --numeric-owner \
        --no-mac-metadata --no-xattrs --no-acls --no-fflags
fi
(cd "$src" && COPYFILE_DISABLE=1 tar "$@" --no-recursion -cf "${tgz%.gz}" -T "$files")
# -n: no file name or time in the gzip header.
gzip -n -9 "${tgz%.gz}"
md5=$(md5_of "$tgz")
size=$(cd "$src" && while IFS= read -r f; do [ -d "$f" ] || cat "$f"; done <"$files" | wc -c | tr -d ' ')

printf '%s\n' \
    '<dune_plugin_update_info>' \
    '  <schema>2</schema>' \
    "  <name>$name</name>" \
    '  <plugin_version_descriptor>' \
    "    <version_index>$vi</version_index>" \
    "    <version>$version</version>" \
    '    <beta>no</beta>' \
    '    <critical>no</critical>' \
    "    <url>$url</url>" \
    "    <md5>$md5</md5>" \
    "    <size>$size</size>" \
    "    <caption>$caption $version</caption>" \
    '  </plugin_version_descriptor>' \
    '</dune_plugin_update_info>' >"$info"

# A wrong archive or update_info means "update available" forever or
# "checksum error" on every Dune, so check what was written.
listing=$(tar -tzf "$tgz")
[ "$(printf '%s\n' "$listing" | LC_ALL=C sort)" = "$(unzip -Z1 "$out" | LC_ALL=C sort)" ] ||
    fail "$archive and the zip hold different files"
printf '%s\n' "$listing" | grep -qx 'dune_plugin.xml' || fail "no dune_plugin.xml in the root of $archive"
if printf '%s\n' "$listing" | grep -q '^\./\|^/\|\(^\|/\)\._\|\.DS_Store'; then
    fail "$archive has ./, /, ._* or .DS_Store entries"
fi
[ "$(tar -xzOf "$tgz" dune_plugin.xml | sed -n 's:.*<version_index>\(.*\)</version_index>.*:\1:p')" = "$vi" ] ||
    fail "version_index in $archive differs from plugin/$name"
[ "$(tag md5 "$info")" = "$(md5_of "$tgz")" ] || fail "md5 in update_info.xml is not the archive's"
[ "$(tag name "$info")" = "$name" ] || fail "name in update_info.xml is not $name"
[ "$(tag version_index "$info")" = "$vi" ] || fail "version_index in update_info.xml is not $vi"
[ "$(tag url "$info")" = "${channel%update_info.xml}$archive" ] || fail "url in update_info.xml is not next to it"
echo "update channel: dist/pages/$name/$archive ($size bytes unpacked), update_info.xml:"
cat "$info"
