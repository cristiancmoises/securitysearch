<?php
/*
 * lib/security_headers_minimal.php
 *
 * Minimal hardening headers for non-HTML endpoints:
 *   - image proxy (proxy.php)
 *   - favicon proxy (favicon.php)
 *   - captcha image (captcha.php)
 *   - XML feeds (opensearch.php, sitemap.php)
 *   - JSON APIs (ami4get.php, resolver.php, /api/*)
 *
 * These responses don't render HTML, so a heavy CSP is unnecessary —
 * but transport hardening, MIME sniffing protection, and PHP version
 * hiding still apply.
 */

if (headers_sent()) {
    return;
}

header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Cross-Origin-Resource-Policy: same-origin");
header("X-Permitted-Cross-Domain-Policies: none");

// Restrictive CSP for non-HTML — blocks everything since these
// responses are never interpreted as documents by the browser.
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

header_remove("X-Powered-By");
