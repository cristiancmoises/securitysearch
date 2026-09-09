#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
php -d apc.enable_cli=1 tests/regression.php
php -d apc.enable_cli=1 tests/services-regression.php
php -d apc.enable_cli=1 tests/google-transport.php
php -d apc.enable_cli=1 tests/provider-api-regression.php
php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php
php tests/config-regression.php
php -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_error tests/stream-regression.php
php -d apc.enable_cli=1 tests/ui-regression.php
php -d apc.enable_cli=1 tests/reddit-regression.php
php -d apc.enable_cli=1 tests/animation-regression.php
php -d apc.enable_cli=1 tests/search-execution-regression.php
php -d apc.enable_cli=1 -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_getinfo tests/brave-transport.php
node tests/infinite-regression.cjs
node tests/motion-regression.cjs
python3 tests/publication-regression.py
python3 tests/publication-kit-regression.py
python3 tests/deploy-regression.py
python3 tests/http-regression.py
php -d apc.enable_cli=1 tests/template-benchmark.php
php -d apc.enable_cli=1 tests/performance.php
