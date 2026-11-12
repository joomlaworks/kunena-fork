#!/bin/bash
# Usage: tests/tools/classload.sh <joomla-3.x-utd-checkout>
# Loads every Kunena class file (one PHP process per file) under Joomla's class loader and prints the PHP messages,
# exceptions and fatal errors raised by Kunena code. Nothing but the summary line means: all clear.
TOOLS=$(cd "$(dirname "$0")" && pwd); REPO=$(cd "$TOOLS/../.." && pwd); J3=${1:?usage: $0 <joomla-3.x checkout>}
[ -f "$J3/libraries/cms.php" ] || { echo "Not a Joomla 3 folder: $J3"; exit 1; }
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
php "$TOOLS/classmap.php" "$REPO" > "$TMP/map.inc"
php -r '$m = include $argv[1]; $f = array(); foreach ($m as $v) foreach ($v as $x) if (strpos($x, "/tests/") === false && basename($x) !== "attachment.php" && basename($x) !== "script.kunena.php") $f[$x] = 1; echo implode("\n", array_keys($f)), "\n";' "$TMP/map.inc" > "$TMP/files"
xargs -a "$TMP/files" -P8 -I{} php "$TOOLS/load.php" "$J3" "$TMP/map.inc" {} 2>/dev/null | sort -u | tee "$TMP/out"
echo "== $(wc -l < "$TMP/files") class files loaded, $(wc -l < "$TMP/out") messages"
[ ! -s "$TMP/out" ]
