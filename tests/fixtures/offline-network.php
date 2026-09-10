<?php
/** Test-only tripwires. No production runtime configuration uses this file.
 * Throw Error, not Exception: provider fallback must not swallow a fixture leak.
 * The PHP child disables these internal functions before declaring replacements.
 */
function securitysearch_test_network_denied(): never {
    error_log('OFFLINE_NETWORK_ATTEMPT: provider fixture escaped its transport boundary');
    throw new Error('OFFLINE_NETWORK_ATTEMPT');
}
foreach (['curl_exec','curl_multi_exec','dns_get_record','gethostbynamel','gethostbyname',
          'fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $target) {
    if (function_exists($target)) {
        throw new Error('Offline test isolation missing for '.$target);
    }
}
// Conditional declarations are lint-safe with native extensions enabled.
if (!function_exists('curl_exec')) {function curl_exec(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('curl_multi_exec')) {function curl_multi_exec(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('dns_get_record')) {function dns_get_record(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('gethostbynamel')) {function gethostbynamel(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('gethostbyname')) {function gethostbyname(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('fsockopen')) {function fsockopen(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('pfsockopen')) {function pfsockopen(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('stream_socket_client')) {function stream_socket_client(...$args) {securitysearch_test_network_denied();}}
if (!function_exists('socket_connect')) {function socket_connect(...$args) {securitysearch_test_network_denied();}}
