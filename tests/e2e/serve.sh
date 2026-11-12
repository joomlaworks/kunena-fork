#!/bin/bash
# Usage: serve.sh <start|stop> <db>   (php -S on 127.0.0.1:<port_for db>, PHP errors logged to $SITES/<db>/php_errors.log)
. "$(dirname "$0")/env.sh"
ACT=$1; DB=$2; PORT=$(port_for $DB); SITE=$SITES/$DB; PHPBIN=${PHPBIN:-php}
pkill -f "php -S 127.0.0.1:$PORT" 2>/dev/null; pkill -f "S 127.0.0.1:$PORT " 2>/dev/null
[ "$ACT" = stop ] && exit 0
cd "$SITE" || exit 1
# FCGI_SHIM=1: a fastcgi_finish_request() like PHP-FPM's (fcgi-shim.php)
SHIM=""; [ -n "$FCGI_SHIM" ] && SHIM="-d auto_prepend_file=$E2E/fcgi-shim.php"
nohup $PHPBIN $SHIM -d error_reporting=-1 -d display_errors=0 -d log_errors=1 -d error_log="$SITE/php_errors.log" \
  -d memory_limit=512M -d max_execution_time=300 -d upload_max_filesize=64M -d post_max_size=64M \
  -S 127.0.0.1:$PORT -t "$SITE" > "$OUT/server-$DB.log" 2>&1 &
sleep 0.5; echo "serving $DB at http://127.0.0.1:$PORT/"
