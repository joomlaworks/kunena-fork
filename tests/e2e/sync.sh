#!/bin/bash
# Usage: sync.sh <db>  — copy the repo's current Kunena files over an installed test site (dev loop; no installer run)
. "$(dirname "$0")/env.sh"; S=$SITES/$1; R=$KUNENA_REPO
[ -f "$S/configuration.php" ] || exit 1
rsync -a --exclude install.php "$R/components/com_kunena/" "$S/components/com_kunena/"
rsync -a --exclude install.php "$R/administrator/components/com_kunena/" "$S/administrator/components/com_kunena/"
rsync -a "$R/libraries/kunena/" "$S/libraries/kunena/"
rsync -a "$R/plugins/system/kunena/" "$S/plugins/system/kunena/"; rsync -a "$R/plugins/quickicon/kunena/" "$S/plugins/quickicon/kunena/"
[ -d "$S/plugins/console/kunena" ] && rsync -a "$R/plugins/console/kunena/" "$S/plugins/console/kunena/"
for x in content/kunenadiscuss search/kunena kunena/baldrick; do [ -d "$S/plugins/$x" ] && rsync -a "$R/plugins/$x/" "$S/plugins/$x/"; done
for m in "$R"/modules/mod_kunena*; do n=$(basename "$m"); [ -d "$S/modules/$n" ] && rsync -a "$m/" "$S/modules/$n/"; done
for p in "$R"/administrator/components/com_kunena/install/plugins/plg_kunena_*; do n=${p##*plg_kunena_}; [ -d "$S/plugins/kunena/$n" ] && rsync -a "$p/" "$S/plugins/kunena/$n/"; done
[ -d "$S/plugins/finder/kunena" ] && rsync -a "$R/administrator/components/com_kunena/install/plugins/plg_finder_kunena/" "$S/plugins/finder/kunena/"
rsync -a --exclude cache "$R/media/kunena/" "$S/media/kunena/"
echo synced $1
