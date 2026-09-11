<?php
$homepage_started=hrtime(true);
include_once __DIR__ . "/lib/security_headers.php";
include "data/config.php";
require_once __DIR__."/lib/page_renderer.php";
require_once __DIR__ . "/lib/theme_picker.php";
securitysearch_theme_post();
$frontend = new page_renderer();

$homepage = $frontend->load(
	"home.html",
	[
		"server_short_description" => htmlspecialchars(config::SERVER_SHORT_DESCRIPTION)
	]
);
// Query-free origin processing time only; excludes DNS, TLS and reverse-proxy queues.
if(!headers_sent())header('Server-Timing: app;dur='.number_format((hrtime(true)-$homepage_started)/1000000,2,'.',''));
if(!headers_sent())header('X-SecuritySearch-Render: '.view_resources::mode());
echo $homepage;
