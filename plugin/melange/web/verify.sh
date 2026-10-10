#!/bin/sh
# Type check, tests (test/, on the fixtures of PHP), rebuild ../cgi/settings.js and compare it with the git index.
# Run from web/ (bun run verify).

set -eu

want=$(sed -n 's/.*"packageManager": *"bun@\([^"]*\)".*/\1/p' package.json)
have=$(bun --version)
# Another minifier may emit another build: then the compare fails not because of src/.
[ "$have" = "$want" ] ||
    echo "warning: bun $have, package.json wants $want: settings.js may differ for that" >&2

bun run check
bun test
bun run build

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "no git: settings.js not compared" >&2
    exit 0
fi
git diff --quiet -- ../cgi/settings.js ||
    { echo "cgi/settings.js does not match web/src (rebuilt now): stage or commit it" >&2; exit 1; }
