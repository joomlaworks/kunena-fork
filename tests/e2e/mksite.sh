#!/bin/bash
# Usage: mksite.sh <sqlite|pg|pgpdo|mariadb|mysql>
# Builds a fresh Joomla 3.x UTD site for the given database under $SITES/<db>, and serves it with php -S.
set -e
. "$(dirname "$0")/env.sh"
DB=$1; [ -z "$DB" ] && { echo "usage: $0 <sqlite|pg|pgpdo|mariadb|mysql>"; exit 1; }
PORT=$(port_for $DB); SITE=$SITES/$DB
"$E2E/serve.sh" stop $DB || true
[ -d "$J3SRC" ] || git clone -q --depth 1 "$J3REPO" "$J3SRC"
rm -rf "$SITE"; mkdir -p "$SITES"; cp -a "$J3SRC" "$SITE"
# The reference copy may hold symlinks into the Kunena repo (dev setup): never install through them
find "$SITE" -type l -delete
rm -f "$SITE/configuration.php"; rm -rf "$SITE/database"
cd "$SITE"
php cli/joomla.php core:install --site-name="Kunena $DB" --admin-email=admin@example.com --admin-username=$ADMIN_USER --admin-password=$ADMIN_PASS -n -q
case $DB in
  pg|pgpdo)
    NAME=$PG_DB; [ $DB = pgpdo ] && NAME=${PG_DB}_pdo
    docker exec $PG_CONTAINER psql -q -U $PG_USER -d postgres -c "DROP DATABASE IF EXISTS $NAME" -c "CREATE DATABASE $NAME OWNER $PG_USER"
    DRV=postgresql; [ $DB = pgpdo ] && DRV=pgsql
    php cli/joomla.php database:convert --to=$DRV --host=$PG_HOST --user=$PG_USER --password=$PG_PASS --database=$NAME -n -q ;;
  mariadb|mysql)
    C=$MARIADB_CONTAINER; H=$MARIADB_HOST; CLI=mariadb
    [ $DB = mysql ] && { C=$MYSQL_CONTAINER; H=$MYSQL_HOST; CLI=mysql; }
    docker exec $C $CLI -uroot -pkunena -e "DROP DATABASE IF EXISTS kunena; CREATE DATABASE kunena CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON kunena.* TO 'kunena'@'%';"
    php cli/joomla.php database:convert --to=mysqli --host=$H --user=kunena --password=kunena --database=kunena -n -q ;;
esac
php cli/joomla.php config:set error_reporting=maximum -n -q || true
php cli/joomla.php config:set sef=0 -n -q || true
php cli/joomla.php config:set log_path="$SITE/administrator/logs" -n -q || true
# Never the system's sendmail: e-mails go to $OUT/<db>-mail.log (a misconfigured local MTA can block a request for ever)
mkdir -p "$OUT"
printf '#!/bin/sh\ncat >> "%s"\necho ----MAIL-END---- >> "%s"\n' "$OUT/$DB-mail.log" "$OUT/$DB-mail.log" > "$OUT/sendmail-$DB.sh"
chmod 755 "$OUT/sendmail-$DB.sh"
for setting in mailonline=1 mailer=sendmail "sendmail=$OUT/sendmail-$DB.sh"; do php cli/joomla.php config:set "$setting" -n -q || true; done
php cli/joomla.php site:info 2>&1 | grep -i -E 'database|driver' | head -5
# Log every failed query (errors Kunena may catch and hide) to administrator/logs/kunena-test-db-errors.php
for f in includes/framework.php administrator/includes/framework.php; do
  printf '\nJLog::addLogger(array("text_file" => "kunena-test-db-errors.php"), JLog::ALL, array("database-error"));\n' >> $f
done
# ... with the SQL of the failed query (test copy only)
for f in libraries/joomla/database/driver/{postgresql,pdo,mysqli,mysqlonsqlite}.php; do
  sed -i "s/JLog::add(JText::sprintf('JLIB_DATABASE_QUERY_FAILED', \$this->errorNum, \$this->errorMsg), JLog::ERROR, 'database-error');/JLog::add(JText::sprintf('JLIB_DATABASE_QUERY_FAILED', \$this->errorNum, \$this->errorMsg) . ' SQL: ' . str_replace(array(\"\\\\n\", \"\\\\t\"), ' ', isset(\$query) ? (string) \$query : (string) \$this->sql), JLog::ERROR, 'database-error');/" $f
done
# Marker: kexec.php (test helper) only works in sites built by this script
touch "$SITE/.kunena-test-site"
"$E2E/serve.sh" start $DB
