<?php
$homepage_started=hrtime(true);
include_once __DIR__ . "/lib/security_headers.php";
include "data/config.php";
include "lib/frontend.php";
require_once __DIR__ . "/lib/theme_picker.php";
securitysearch_theme_post();
$frontend = new frontend();

$homepage = $frontend->load(
	"home.html",
	[
		"server_short_description" => htmlspecialchars(config::SERVER_SHORT_DESCRIPTION)
	]
);
// Query-free origin processing time only; excludes DNS, TLS and reverse-proxy queues.
if(!headers_sent())header('Server-Timing: app;dur='.number_format((hrtime(true)-$homepage_started)/1000000,2,'.',''));
echo $homepage;
