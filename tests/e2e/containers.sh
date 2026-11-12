#!/bin/bash
# Usage: containers.sh up|down   Start (or remove) the PostgreSQL, MariaDB and MySQL containers used by the tests.
. "$(dirname "$0")/env.sh"
case "$1" in
  up)
    docker start $PG_CONTAINER 2>/dev/null || docker run -d --name $PG_CONTAINER -p ${PG_HOST}:5432 \
      -e POSTGRES_USER=$PG_USER -e POSTGRES_PASSWORD=$PG_PASS -e POSTGRES_DB=$PG_DB postgres:16
    docker start $MARIADB_CONTAINER 2>/dev/null || docker run -d --name $MARIADB_CONTAINER -p ${MARIADB_HOST}:3306 \
      -e MARIADB_ROOT_PASSWORD=kunena -e MARIADB_USER=$MARIADB_USER -e MARIADB_PASSWORD=$MARIADB_PASS -e MARIADB_DATABASE=$MARIADB_DB mariadb:11.4
    docker start $MYSQL_CONTAINER 2>/dev/null || docker run -d --name $MYSQL_CONTAINER -p ${MYSQL_HOST}:3306 \
      -e MYSQL_ROOT_PASSWORD=kunena -e MYSQL_USER=$MYSQL_USER -e MYSQL_PASSWORD=$MYSQL_PASS -e MYSQL_DATABASE=$MYSQL_DB mysql:8.4
    echo "waiting for the databases..."; sleep 15 ;;
  down)
    for db in sqlite pg pgpdo mariadb mysql; do "$E2E/serve.sh" stop $db; done
    docker rm -f $PG_CONTAINER $MARIADB_CONTAINER $MYSQL_CONTAINER ;;
  *) echo "usage: $0 up|down"; exit 1 ;;
esac
