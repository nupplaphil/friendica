#!/usr/bin/env bash

DATABASENAME=${MYSQL_DATABASE:friendica}
DATABASEUSER=${MYSQL_USERNAME:friendica}
DATABASEHOST=${MYSQL_HOST:localhost}
BASEDIR=$PWD

if [ -z "$PHP_EXE" ]; then
  PHP_EXE=php
fi
PHP=$(which "$PHP_EXE")
# Use the Friendica internal composer
COMPOSER="$BASEDIR/bin/composer.phar"

set -e

_XDEBUG_CONFIG=$XDEBUG_CONFIG
unset XDEBUG_CONFIG

if [ -x "$PHP" ]; then
  echo "Using PHP executable $PHP"
else
  echo "Could not find PHP executable $PHP_EXE" >&2
  exit 3
fi

echo "Installing depdendencies"
$PHP "$COMPOSER" install

PHPUNIT="$BASEDIR/vendor/phpunit/phpunit/phpunit"

if [ -x "$PHPUNIT" ]; then
  echo "Using PHPUnit executable $PHPUNIT"
else
  echo "Could not find PHPUnit executable after composer $PHPUNIT" >&2
  exit 3
fi

# Back up existing (dev) config if one exists and backup not already there
if [ -f config/local.config.php ] && [ ! -f config/local.config-autotest-backup.php ]; then
  mv config/local.config.php config/local.config-autotest-backup.php
fi

function cleanup_config {

    if [ -n "$DOCKER_CONTAINER_ID" ]; then
      echo "Kill the docker $DOCKER_CONTAINER_ID"
      docker stop "$DOCKER_CONTAINER_ID"
      docker rm -f "$DOCKER_CONTAINER_ID"
    fi

    cd "$BASEDIR"

    # Restore existing config
    if [ -f config/local.config-autotest-backup.php ]; then
      mv config/local.config-autotest-backup.php config/local.config.php
    fi
}

# restore config on exit
trap cleanup_config EXIT

function execute_tests {
    echo "Setup environment for MariaDB testing ..."
    # back to root folder
    cd "$BASEDIR"

    if [ -n "$USEDOCKER" ]; then
      echo "Fire up the mysql docker"
      DOCKER_CONTAINER_ID=$(docker run \
              -e MYSQL_ROOT_PASSWORD=friendica \
              -e MYSQL_USER="$DATABASEUSER" \
              -e MYSQL_PASSWORD=friendica \
              -e MYSQL_DATABASE="$DATABASENAME" \
              -d mysql)
      DATABASEHOST=$(docker inspect --format="{{.NetworkSettings.IPAddress}}" "$DOCKER_CONTAINER_ID")
    else
      if [ -z "$DRONE" ]; then  # no need to drop the DB when we are on CI
        if [ "mysql" != "$(mysql --version | grep -o mysql)" ]; then
          echo "Your mysql binary is not provided by mysql"
          echo "To use the docker container set the USEDOCKER environment variable"
          exit 3
        fi
        mysql -u "$DATABASEUSER" -pfriendica -e "DROP DATABASE IF EXISTS $DATABASENAME"
        echo "Initialize test data..."
        mysql -u "$DATABASEUSER" -pfriendica < friendica_test_data.sql
      else
        DATABASEHOST=db
      fi
    fi

    echo "Waiting for MySQL $DATABASEHOST initialization..."
    if ! bin/wait-for-connection $DATABASEHOST 3306 300; then
      echo "[ERROR] Waited 300 seconds, no response" >&2
      exit 1
    fi

    if [ -n "$USEDOCKER" ]; then
      echo "Initialize test data..."
      docker exec $DOCKER_CONTAINER_ID mysql -u "$DATABASEUSER" -pfriendica < friendica_test_data.sql
    fi

    #test execution
    echo "Testing..."
    cd tests
    rm -fr "coverage-html"
    mkdir "coverage-html"
    if [[ "$_XDEBUG_CONFIG" ]]; then
      export XDEBUG_CONFIG=$_XDEBUG_CONFIG
    fi

    COVER=''
    if [ -z "$NOCOVERAGE" ]; then
      COVER="--coverage-clover autotest-clover.xml --coverage-html coverage-html"
    else
      echo "No coverage"
    fi

    echo "${PHPUNIT[@]}" --configuration phpunit.xml $COVER --log-junit "autotest-results.xml" "$1" "$2"
    "${PHPUNIT[@]}" --configuration phpunit.xml $COVER --log-junit "autotest-results.xml" "$1" "$2"
    RESULT=$?

    if [ -n "$DOCKER_CONTAINER_ID" ]; then
      echo "Kill the docker $DOCKER_CONTAINER_ID"
      docker stop $DOCKER_CONTAINER_ID
      docker rm -f $DOCKER_CONTAINER_ID
      unset $DOCKER_CONTAINER_ID
    fi
}

#
# Start the test execution
#
if [ -n "$1" ] && [ ! -f "tests/$FILENAME" ] && [ "${FILENAME:0:2}" != "--" ]; then
  execute_tests "$FILENAME" "$2"
else
  execute_tests
fi
