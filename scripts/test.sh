#!/bin/sh
# All commands below are mandatory. --keep-going collects every failure; it never
# changes a failing audit to success. Normal invocations retain fail-fast behavior.
set -eu
cd "$(dirname "$0")/.."
keep_going=0
case "${1-}" in
    '') [ "$#" -eq 0 ] || exit 2 ;;
    --keep-going) [ "$#" -eq 1 ] || exit 2; keep_going=1 ;;
    *) printf 'Usage: sh scripts/test.sh [--keep-going]\n' >&2; exit 2 ;;
esac
passed=0
failed=0
failures=''
run_test() {
    printf '\n=== OFFLINE TEST: %s ===\n' "$*"
    if "$@"; then
        passed=$((passed + 1))
    else
        code=$?
        failed=$((failed + 1))
        failures="${failures}
  FAIL (exit ${code}): $*"
        printf '\nFAILED (exit %s): %s\n' "$code" "$*" >&2
        if [ "$keep_going" -eq 0 ]; then return "$code"; fi
    fi
}
# BEGIN SUITES
run_test python3 scripts/release-audit.py --syntax-only
run_test python3 tests/audit-runner-regression.py
run_test php tests/native-runtime.php
run_test python3 tests/offline-boundary-regression.py
run_test php tests/provider-dns-regression.php
run_test python3 tests/operator-themes-regression.py
run_test python3 tests/operator-archive-regression.py
run_test python3 tests/provider-http-harness-regression.py
run_test php -d apc.enable_cli=1 tests/regression.php
run_test php -d apc.enable_cli=1 tests/services-regression.php
run_test php tests/binternet-contract-regression.php
run_test php tests/binternet-modern-regression.php
run_test php tests/theme-picker-regression.php
run_test php -d disable_functions=curl_setopt,curl_exec,curl_share_init,curl_share_setopt tests/provider-http-regression.php
run_test php -d apc.enable_cli=1 tests/google-transport.php
run_test php -d apc.enable_cli=1 tests/provider-api-regression.php
run_test php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php
run_test php tests/config-regression.php
run_test php -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_error tests/stream-regression.php
run_test php -d apc.enable_cli=1 tests/ui-regression.php
run_test php -d apc.enable_cli=1 tests/reddit-regression.php
run_test php -d apc.enable_cli=1 tests/animation-regression.php
run_test php -d apc.enable_cli=1 tests/search-execution-regression.php
run_test php -d apc.enable_cli=1 -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_getinfo tests/brave-transport.php
run_test node tests/infinite-regression.cjs
run_test node tests/motion-regression.cjs
run_test python3 tests/publication-regression.py
run_test python3 tests/publication-kit-regression.py
run_test python3 tests/push-remotes-regression.py
run_test python3 tests/deploy-import-regression.py
run_test python3 tests/deploy-regression.py
run_test python3 tests/deploy-gates-regression.py
run_test python3 tests/theme-http-regression.py
run_test python3 tests/http-regression.py
run_test php -d apc.enable_cli=1 tests/template-benchmark.php
run_test php -d apc.enable_cli=1 tests/performance.php
run_test php tests/experience-regression.php
run_test php -d apc.enable_cli=1 tests/redlib-failover-regression.php
run_test python3 tests/publication-v0.9.21-regression.py
run_test python3 tests/rank-timer-regression.py
run_test node tests/local-picture-regression.cjs
run_test python3 tests/experience-http-regression.py
run_test python3 tests/publication-v0.9.22-regression.py
run_test php tests/news-primary-regression.php
run_test python3 tests/news-deploy-regression.py
run_test python3 tests/picture-http-regression.py
run_test python3 tests/publication-v0.9.23-regression.py
run_test python3 tests/news-audit-contract-regression.py
run_test php tests/news-core-regression.php
run_test php tests/news-rss-regression.php
run_test php tests/news-http-policy-regression.php
run_test php tests/newswire-regression.php
run_test python3 tests/news-rss-http-regression.py
run_test python3 tests/news-rss-deploy-regression.py
run_test python3 tests/publication-v0.9.24-regression.py
run_test python3 tests/news-audit-runtime-regression.py
run_test python3 tests/redlib-attribution-regression.py
run_test php tests/search-health-regression.php
run_test php -d apc.enable_cli=0 -d disable_functions=curl_init,curl_reset,curl_setopt,curl_exec,curl_errno,curl_getinfo,curl_close tests/search-transport-regression.php
run_test python3 tests/home-performance-regression.py
run_test python3 tests/publication-v0.9.25-regression.py
run_test php tests/google-contract-regression.php
run_test php -d apc.enable_cli=0 -d disable_functions=curl_init,curl_reset,curl_setopt,curl_exec,curl_errno,curl_getinfo,curl_close tests/google-flow-regression.php
run_test php -d disable_functions=apcu_fetch,apcu_store,apcu_enabled,apcu_add,apcu_cas,apcu_delete tests/google-cache-regression.php
run_test python3 tests/frontend-onepass-regression.py
run_test python3 tests/google-live-gate-regression.py
run_test python3 tests/publication-v0.9.26-regression.py
run_test python3 tests/view-resources-regression.py
run_test python3 tests/delivery-profile-regression.py
run_test python3 tests/publication-v0.9.27-regression.py
run_test python3 tests/deploy-version-regression.py
# END SUITES
printf '\n=== OFFLINE AUDIT SUMMARY ===\n'
printf 'Commands passed: %s; failed: %s.\n' "$passed" "$failed"
if [ "$failed" -ne 0 ]; then
    printf '%s\n' "$failures" >&2
    printf 'OFFLINE AUDIT FAILED: deployment must not continue.\n' >&2
    exit 1
fi
printf 'Every required test command completed successfully.\n'
