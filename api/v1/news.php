<?php

chdir("../../");
header("Content-Type: application/json");
header("Cache-Control: private, no-store");
header("X-Content-Type-Options: nosniff");
header("X-Robots-Tag: noindex, nofollow");

include "data/config.php";
if(config::API_ENABLED === false){
	
	echo json_encode(["status" => "The server administrator disabled the API!"]);
	return;
}

include "lib/frontend.php";
require_once "lib/search_health.php";

/*
	Captcha
*/
include "lib/bot_protection.php";
$null = null;
new bot_protection($null, $null, $null, "news", false);

try{
	$frontend = new frontend();
	[$scraper, $filters] = $frontend->getscraperfilters(
		"news",
		isset($_GET["scraper"]) ? $_GET["scraper"] : null
	);
	$get = $frontend->parsegetfilters($_GET, $filters);
	echo json_encode(
		$scraper->news($get),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE
	);
	
}catch(Throwable $e){
	http_response_code(503);
	header("Cache-Control: no-store");
	header("Retry-After: 30");
	
	echo json_encode(["status" => search_health::public_message($e)]);
}
