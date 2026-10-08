#!/bin/sh
# Build dist/dune_plugin_melange_<version>.zip from plugin/melange.
# Usage: sh build.sh

set -eu

root=$(cd "$(dirname "$0")" && pwd)
src="$root/plugin/melange"

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

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$src/dune_plugin.xml")
out="$root/dist/dune_plugin_melange_$version.zip"
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
