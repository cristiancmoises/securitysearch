<?php
include_once __DIR__ . "/lib/security_headers_minimal.php";

header("Content-Type: application/xml; charset=utf-8");
include "data/config.php";

// Match the public landing page canonical, never an untrusted Host header or
// the HTTP scheme of the private TLS-terminating proxy connection.
$domain = 'https://securityops.co';

// Use the most-recently-modified PHP file in the public root as the
// "site lastmod" — gives a real signal to crawlers without pinning
// a stale date forever.
$root = __DIR__;
$candidates = [
    $root . "/index.php",
    $root . "/about.php",
    $root . "/instances.php",
    $root . "/template/home.html",
    $root . "/template/about.html",
];
$site_mtime = 0;
foreach ($candidates as $c) {
    if (is_readable($c)) {
        $m = filemtime($c);
        if ($m && $m > $site_mtime) $site_mtime = $m;
    }
}
$site_lastmod = $site_mtime ? date('c', $site_mtime) : date('c');

// Per-page sitemap entries. Tuned for a metasearch front page:
// the index changes most often (banner rotation), the static
// info pages change rarely.
$urls = [
    ['/',          $site_lastmod, 'daily',   '1.0'],
    ['/about',     $site_lastmod, 'monthly', '0.7'],
    ['/instances', $site_lastmod, 'weekly',  '0.5'],
    ['/api.txt',   $site_lastmod, 'monthly', '0.3'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $lastmod, $changefreq, $priority]) {
    echo "  <url>\n";
    echo "    <loc>{$domain}{$path}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>{$changefreq}</changefreq>\n";
    echo "    <priority>{$priority}</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
