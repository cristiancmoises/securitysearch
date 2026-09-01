<?php
include_once __DIR__ . "/lib/security_headers.php";
include "data/config.php";
include "lib/frontend.php";
$frontend = new frontend();

echo $frontend->load(
	"home.html",
	[
		"server_short_description" => htmlspecialchars(config::SERVER_SHORT_DESCRIPTION)
	]
);
