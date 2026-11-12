#!/bin/bash
# Zips the repository the way GitHub's "Download ZIP" does (one top folder, export-ignore'd files left out)
# into $WORKDIR/kunena-main.zip
. "$(dirname "$0")/env.sh"
TMP=$(mktemp -d); mkdir "$TMP/kunena-main"
if git -C "$KUNENA_REPO" rev-parse --git-dir >/dev/null 2>&1; then
  # Exactly what GitHub exports: committed files of HEAD... plus uncommitted changes of tracked files
  (cd "$KUNENA_REPO" && git ls-files -co --exclude-standard -z | xargs -0 -I{} cp --parents -a {} "$TMP/kunena-main/" 2>/dev/null)
else
  cp -a "$KUNENA_REPO"/. "$TMP/kunena-main/"
fi
# .gitattributes export-ignore
(cd "$TMP/kunena-main" && rm -rf .git .claude tests docs .php-cs-fixer.dist.php .php-cs-fixer.cache .gitattributes .gitignore AGENTS.md JS-MIGRATION.md)
rm -f "$DBTEST/kunena-main.zip"; (cd "$TMP" && zip -qr "$DBTEST/kunena-main.zip" kunena-main)
rm -rf "$TMP"; ls -la "$DBTEST/kunena-main.zip"
