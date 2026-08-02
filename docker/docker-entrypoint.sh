#!/bin/sh
set -e

# remove quotes from variable if present
FOURGET_PROTO="${FOURGET_PROTO%\"}"
FOURGET_PROTO="${FOURGET_PROTO#\"}"

# make lowercase
FOURGET_PROTO=`echo $FOURGET_PROTO | awk '{print tolower($0)}'`

FOURGET_SRC='/var/www/html/4get'

mkdir -p /etc/apache2

if [ "$FOURGET_PROTO" = "https" ]; then
        echo "Using https configuration"
        cp -rf ${FOURGET_SRC}/docker/apache/https/httpd.conf /etc/apache2
        cp -rf ${FOURGET_SRC}/docker/apache/https/conf.d/* /etc/apache2/conf.d

else
        echo "Using http configuration"
        cp -rf ${FOURGET_SRC}/docker/apache/http/httpd.conf /etc/apache2
        cp -rf ${FOURGET_SRC}/docker/apache/http/conf.d/* /etc/apache2/conf.d
fi

php ./docker/gen_config.php

if [ "$@" = "start" ]; then
        echo "4get is running"
        exec httpd -DFOREGROUND
else 
        exec "$@"
fi

