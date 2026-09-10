#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
printf '\n=== OFFLINE TEST: python3 scripts/release-audit.py --syntax-only ===\n'
python3 scripts/release-audit.py --syntax-only
printf '\n=== OFFLINE TEST: php tests/native-runtime.php ===\n'
php tests/native-runtime.php
printf '\n=== OFFLINE TEST: python3 tests/offline-boundary-regression.py ===\n'
python3 tests/offline-boundary-regression.py
printf '\n=== OFFLINE TEST: php tests/provider-dns-regression.php ===\n'
php tests/provider-dns-regression.php
printf '\n=== OFFLINE TEST: python3 tests/operator-themes-regression.py ===\n'
python3 tests/operator-themes-regression.py
printf '\n=== OFFLINE TEST: python3 tests/operator-archive-regression.py ===\n'
python3 tests/operator-archive-regression.py
printf '\n=== OFFLINE TEST: python3 tests/provider-http-harness-regression.py ===\n'
python3 tests/provider-http-harness-regression.py
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/regression.php ===\n'
php -d apc.enable_cli=1 tests/regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/services-regression.php ===\n'
php -d apc.enable_cli=1 tests/services-regression.php
printf '\n=== OFFLINE TEST: php tests/binternet-contract-regression.php ===\n'
php tests/binternet-contract-regression.php
printf '\n=== OFFLINE TEST: php tests/binternet-modern-regression.php ===\n'
php tests/binternet-modern-regression.php
printf '\n=== OFFLINE TEST: php tests/theme-picker-regression.php ===\n'
php tests/theme-picker-regression.php
printf '\n=== OFFLINE TEST: php -d disable_functions=curl_setopt,curl_exec,curl_share_init,curl_share_setopt tests/provider-http-regression.php ===\n'
php -d disable_functions=curl_setopt,curl_exec,curl_share_init,curl_share_setopt tests/provider-http-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/google-transport.php ===\n'
php -d apc.enable_cli=1 tests/google-transport.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/provider-api-regression.php ===\n'
php -d apc.enable_cli=1 tests/provider-api-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php ===\n'
php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php
printf '\n=== OFFLINE TEST: php tests/config-regression.php ===\n'
php tests/config-regression.php
printf '\n=== OFFLINE TEST: php -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_error tests/stream-regression.php ===\n'
php -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_error tests/stream-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/ui-regression.php ===\n'
php -d apc.enable_cli=1 tests/ui-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/reddit-regression.php ===\n'
php -d apc.enable_cli=1 tests/reddit-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/animation-regression.php ===\n'
php -d apc.enable_cli=1 tests/animation-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/search-execution-regression.php ===\n'
php -d apc.enable_cli=1 tests/search-execution-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_getinfo tests/brave-transport.php ===\n'
php -d apc.enable_cli=1 -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_getinfo tests/brave-transport.php
printf '\n=== OFFLINE TEST: node tests/infinite-regression.cjs ===\n'
node tests/infinite-regression.cjs
printf '\n=== OFFLINE TEST: node tests/motion-regression.cjs ===\n'
node tests/motion-regression.cjs
printf '\n=== OFFLINE TEST: python3 tests/publication-regression.py ===\n'
python3 tests/publication-regression.py
printf '\n=== OFFLINE TEST: python3 tests/publication-kit-regression.py ===\n'
python3 tests/publication-kit-regression.py
printf '\n=== OFFLINE TEST: python3 tests/push-remotes-regression.py ===\n'
python3 tests/push-remotes-regression.py
printf '\n=== OFFLINE TEST: python3 tests/deploy-regression.py ===\n'
python3 tests/deploy-regression.py
printf '\n=== OFFLINE TEST: python3 tests/deploy-gates-regression.py ===\n'
python3 tests/deploy-gates-regression.py
printf '\n=== OFFLINE TEST: python3 tests/theme-http-regression.py ===\n'
python3 tests/theme-http-regression.py
printf '\n=== OFFLINE TEST: python3 tests/http-regression.py ===\n'
python3 tests/http-regression.py
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/template-benchmark.php ===\n'
php -d apc.enable_cli=1 tests/template-benchmark.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/performance.php ===\n'
php -d apc.enable_cli=1 tests/performance.php

printf '\n=== OFFLINE TEST: php tests/experience-regression.php ===\n'
php tests/experience-regression.php
printf '\n=== OFFLINE TEST: php -d apc.enable_cli=1 tests/redlib-failover-regression.php ===\n'
php -d apc.enable_cli=1 tests/redlib-failover-regression.php
printf '\n=== OFFLINE TEST: python3 tests/publication-v0.9.21-regression.py ===\n'
python3 tests/publication-v0.9.21-regression.py
printf '\n=== OFFLINE TEST: python3 tests/rank-timer-regression.py ===\n'
python3 tests/rank-timer-regression.py
printf '\n=== OFFLINE TEST: node tests/local-picture-regression.cjs ===\n'
node tests/local-picture-regression.cjs
printf '\n=== OFFLINE TEST: python3 tests/experience-http-regression.py ===\n'
python3 tests/experience-http-regression.py
printf '\n=== OFFLINE TEST: python3 tests/publication-v0.9.22-regression.py ===\n'
python3 tests/publication-v0.9.22-regression.py
