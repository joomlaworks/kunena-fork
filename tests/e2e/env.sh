# Settings of the Kunena end-to-end test harness (sourced by the scripts). Override any of them with environment
# variables, e.g.: J3SRC=/path/to/joomla-3.x WORKDIR=/tmp/kunena-tests tests/e2e/matrix.sh
E2E=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
KUNENA_REPO=${KUNENA_REPO:-$(cd "$E2E/../.." && pwd)}
# Work folder: package ZIP, test sites, saved pages and logs (never inside the repository)
WORKDIR=${WORKDIR:-/tmp/kunena-tests}
DBTEST=$WORKDIR
SITES=$WORKDIR/sites
OUT=$WORKDIR/out
# Joomla 3.x UTD checkout used as the source of every test site (cloned on first use when missing)
J3SRC=${J3SRC:-$WORKDIR/joomla-3.x}
J3REPO=${J3REPO:-https://github.com/joomlaworks/joomla-3.x.git}
ADMIN_USER=admin
ADMIN_PASS=Admin12345678
# Database containers (see containers.sh)
PG_CONTAINER=kunena-test-pg;           PG_HOST=127.0.0.1:55432;      PG_USER=kunena; PG_PASS=kunena; PG_DB=kunena
MARIADB_CONTAINER=kunena-test-mariadb; MARIADB_HOST=127.0.0.1:53306; MARIADB_USER=kunena; MARIADB_PASS=kunena; MARIADB_DB=kunena
MYSQL_CONTAINER=kunena-test-mysql;     MYSQL_HOST=127.0.0.1:53307;   MYSQL_USER=kunena; MYSQL_PASS=kunena; MYSQL_DB=kunena
# Web server port per site
port_for() { case "$1" in sqlite) echo 8101;; pg) echo 8102;; pgpdo) echo 8104;; mariadb) echo 8103;; mysql) echo 8105;; *) echo 8199;; esac; }
mkdir -p "$SITES" "$OUT"
