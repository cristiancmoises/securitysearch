#!/bin/sh
set -e

# remove quotes from variable if present
FOURGET_PROTO="${FOURGET_PROTO%\"}"
FOURGET_PROTO="${FOURGET_PROTO#\"}"

# make lowercase
FOURGET_PROTO=$(printf '%s\n' "$FOURGET_PROTO" | awk '{print tolower($0)}')

FOURGET_SRC='/var/www/html/4get'

mkdir -p /etc/apache2

if [ "$FOURGET_PROTO" = "https" ]; then
        echo "Using https configuration"
        cp -rf "$FOURGET_SRC/docker/apache/https/httpd.conf" /etc/apache2
        cp -rf "$FOURGET_SRC"/docker/apache/https/conf.d/* /etc/apache2/conf.d

else
        echo "Using http configuration"
        cp -rf "$FOURGET_SRC/docker/apache/http/httpd.conf" /etc/apache2
        cp -rf "$FOURGET_SRC"/docker/apache/http/conf.d/* /etc/apache2/conf.d
fi

php ./docker/gen_config.php

# Compile fixed public templates/CSS once, before Apache handles any requests.
# Explicit 0 opts out. Failure restores the original filesystem renderer.
if [ "${SECURITYSEARCH_RENDER_BUNDLE:-1}" != "0" ] && php ./lib/build_view_resources.php --build; then
        export SECURITYSEARCH_RENDER_BUNDLE=1
else
        export SECURITYSEARCH_RENDER_BUNDLE=0
        echo "Using dynamic UI resources; all page features remain enabled."
fi

if [ "$#" -eq 1 ] && [ "$1" = "start" ]; then
        echo "4get is running"
        exec httpd -DFOREGROUND
else
        exec "$@"
fi
