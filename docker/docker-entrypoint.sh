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

# Common fast-home response policy is identical for HTTP-behind-proxy and direct HTTPS.
cp "$FOURGET_SRC/docker/apache/fast-home.conf" /etc/apache2/conf.d/zz-securitysearch-fast-home.conf

php ./docker/gen_config.php

# Compile fixed public templates/CSS once, before Apache handles any requests.
# Explicit 0 opts out. Failure restores the original filesystem renderer.
if [ "${SECURITYSEARCH_RENDER_BUNDLE:-1}" != "0" ] && php ./lib/build_view_resources.php --build; then
        export SECURITYSEARCH_RENDER_BUNDLE=1
else
        export SECURITYSEARCH_RENDER_BUNDLE=0
        echo "Using dynamic UI resources; all page features remain enabled."
fi

# Anonymous query-free GET/HEAD / can bypass PHP entirely.  This is not a search
# cache: any cookie, query string or Authorization header remains on the dynamic path.
rm -f ./home-anonymous.generated.fast ./home-anonymous.generated.fast.gz
if [ "${SECURITYSEARCH_STATIC_HOME:-1}" != "0" ] && php ./lib/build_home_snapshot.php --build; then
        export SECURITYSEARCH_STATIC_HOME=1
else
        export SECURITYSEARCH_STATIC_HOME=0
        rm -f ./home-anonymous.generated.fast ./home-anonymous.generated.fast.gz
        echo "Using dynamic anonymous homepage; all page features remain enabled."
fi

# Prefer Apache event MPM + PHP-FPM for concurrency. The legacy mod_php/prefork
# runtime remains an explicit fallback mode, but we never silently downgrade a
# candidate: a broken FPM setup must fail readiness before production cutover.
SECURITYSEARCH_PHP_RUNTIME="${SECURITYSEARCH_PHP_RUNTIME:-fpm}"
case "$SECURITYSEARCH_PHP_RUNTIME" in
        fpm)
                test -f "$FOURGET_SRC/docker/php-fpm/php-fpm.conf"
                test -f "$FOURGET_SRC/docker/php-fpm/securitysearch.conf"
                test -f "$FOURGET_SRC/docker/apache/fpm.conf"
                rm -rf /etc/php84/php-fpm.d
                install -d -o root -g root -m 755 /etc/php84/php-fpm.d
                cp "$FOURGET_SRC/docker/php-fpm/php-fpm.conf" /etc/php84/php-fpm.conf
                cp "$FOURGET_SRC/docker/php-fpm/securitysearch.conf" /etc/php84/php-fpm.d/securitysearch.conf
                # mod_php requires prefork and cannot coexist with event MPM.
                rm -f /etc/apache2/conf.d/php84-module.conf
                cp "$FOURGET_SRC/docker/apache/fpm.conf" /etc/apache2/conf.d/20-securitysearch-fpm.conf
                sed -i 's#^LoadModule mpm_prefork_module modules/mod_mpm_prefork.so$#LoadModule mpm_event_module modules/mod_mpm_event.so#' /etc/apache2/httpd.conf
                grep -qx 'LoadModule mpm_event_module modules/mod_mpm_event.so' /etc/apache2/httpd.conf
                php-fpm84 -t -y /etc/php84/php-fpm.conf
                php-fpm84 -D -y /etc/php84/php-fpm.conf
                ready=0
                i=0
                while [ "$i" -lt 10 ]; do
                        if php -r '$s=@fsockopen("127.0.0.1",9000,$e,$m,0.2); if($s){fclose($s);exit(0);} exit(1);'; then
                                ready=1
                                break
                        fi
                        i=$((i + 1))
                        sleep 1
                done
                if [ "$ready" != "1" ]; then
                        echo "PHP-FPM did not become ready; refusing to start an unverified candidate." >&2
                        exit 1
                fi
                printf '%s\n' fpm > /run/securitysearch-php-runtime
                ;;
        prefork)
                test -f /etc/apache2/conf.d/php84-module.conf
                rm -f /etc/apache2/conf.d/20-securitysearch-fpm.conf
                printf '%s\n' prefork > /run/securitysearch-php-runtime
                ;;
        *)
                echo "SECURITYSEARCH_PHP_RUNTIME must be fpm or prefork." >&2
                exit 2
                ;;
esac

httpd -t

if [ "$#" -eq 1 ] && [ "$1" = "start" ]; then
        echo "SecuritySearch is running with PHP runtime: $SECURITYSEARCH_PHP_RUNTIME"
        exec httpd -DFOREGROUND
else
        exec "$@"
fi
