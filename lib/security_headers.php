<?php
/*
 * lib/security_headers.php
 *
 * Centralized HTTP security headers for Security Search.
 * Include this once at the top of every public PHP entry point,
 * BEFORE any output is emitted.
 *
 *   include_once __DIR__ . "/lib/security_headers.php";
 *
 * Edit policy in this single file rather than per-script.
 *
 * Notes on the CSP:
 *   - default-src 'none' is the most restrictive baseline.
 *   - Image enhancement permits same-origin scripts/connections. The opt-in Custom
 *     theme permits its local script, but still forbids network connections.
 *   - style-src 'self' 'unsafe-inline' — home.html ships an inline <style>.
 *     If/when that is moved into static/style.css, drop 'unsafe-inline'.
 *   - img-src includes data: for bundled image placeholders.
 *   - media-src 'self' for the home-page intro audio.
 *   - frame-ancestors 'none' is the modern equivalent of X-Frame-Options DENY.
 */

// Skip if headers were already sent (e.g. running via CLI).
if (headers_sent()) {
    return;
}

// HSTS — only meaningful over HTTPS, but harmless to send always.
// Two years + includeSubDomains + preload (eligible for HSTS preload list).
header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");

// Clickjacking & MIME sniffing
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Referrer leak prevention — privacy tool, send nothing.
header("Referrer-Policy: no-referrer");

// Disable powerful browser APIs we never use, plus opt out of
// Google's Topics / FLoC tracking and interest cohorts.
header(
    "Permissions-Policy: " .
    "accelerometer=(), ambient-light-sensor=(), autoplay=(self), " .
    "battery=(), camera=(), clipboard-read=(), clipboard-write=(), " .
    "display-capture=(), document-domain=(), encrypted-media=(), " .
    "fullscreen=(self), geolocation=(), gyroscope=(), " .
    "interest-cohort=(), browsing-topics=(), " .
    "magnetometer=(), microphone=(), midi=(), payment=(), " .
    "picture-in-picture=(), publickey-credentials-get=(), " .
    "screen-wake-lock=(), sync-xhr=(self), usb=(), " .
    "web-share=(), xr-spatial-tracking=()"
);

// Cross-origin isolation
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Resource-Policy: same-origin");
header("Cross-Origin-Embedder-Policy: unsafe-none"); // require-corp breaks 3rd-party img proxy
header("X-Permitted-Cross-Domain-Policies: none");

// Content Security Policy
$image_enhancement=defined('SECURITYSEARCH_IMAGE_ENHANCEMENT') && SECURITYSEARCH_IMAGE_ENHANCEMENT===true;
$local_picture=($_COOKIE['theme'] ?? null)==='Custom' && in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''),
    ['index.php','web.php','images.php','videos.php','news.php','music.php','settings.php','about.php','instances.php'],true);
header(
    "Content-Security-Policy: " .
    "default-src 'none'; " .
    (($image_enhancement || $local_picture) ? "script-src 'self'; " : "script-src 'none'; ") .
    "script-src-attr 'none'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "img-src 'self' data:; " .
    "media-src 'self'; " .
    "font-src 'self' data:; " .
    ($image_enhancement ? "connect-src 'self'; " : "connect-src 'none'; ") .
    "form-action 'self'; " .
    "frame-ancestors 'none'; " .
    "base-uri 'self'; " .
    "manifest-src 'self'; " .
    "object-src 'none'; " .
    "worker-src 'none'; " .
    "upgrade-insecure-requests"
);

// Hide PHP version
header_remove("X-Powered-By");
