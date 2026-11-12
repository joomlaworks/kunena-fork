#!/bin/bash
# Usage: matrix.sh [db ...]   (default: sqlite pg mariadb)
# For each database: fresh Joomla site, Kunena package installed from a zip of the repo, every scenario, uninstall,
# reinstall over the kept tables (upgrade path), site check, then Kunena > Tools > Uninstall (drops the tables).
cd "$(dirname "$0")"; . ./env.sh; ./mkzip.sh >/dev/null
DBS=${@:-sqlite pg mariadb}; rc=0
for db in $DBS; do
  echo "################ $db"
  ./mksite.sh $db >/dev/null || { echo "site build failed"; rc=1; continue; }
  php run.php $db install,wizard,admin,config,site,post,guest,features,search,profile,recent,moderate,emptytrash,sampledata,extras,baldrick,adminpages,tools,uninstall 2>&1 | grep -v Deprecated | tee "$OUT"/$db-matrix1.txt | grep -Ev ' PASS|PASS  |^PHP ' ; r1=${PIPESTATUS[0]}
  echo "-- reinstall over existing tables"
  php run.php $db install,wizard,site,post,recent,purge 2>&1 | grep -v Deprecated | tee "$OUT"/$db-matrix2.txt | grep -Ev ' PASS|PASS  |^PHP ' ; r2=${PIPESTATUS[0]}
  [ $r1 -ne 0 -o $r2 -ne 0 ] && rc=1
done
exit $rc
