<?php
include_once __DIR__ . "/lib/security_headers.php";
include "data/config.php";
include "lib/frontend.php";
require_once __DIR__ . "/lib/theme_picker.php";
securitysearch_theme_post();
$frontend = new frontend();

echo $frontend->load(
	"home.html",
	[
		"server_short_description" => htmlspecialchars(config::SERVER_SHORT_DESCRIPTION)
	]
);
