<?php

chdir("../../");
header("Content-Type: application/json");

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
new bot_protection($null, $null, $null, "images", false);

try{
	$frontend = new frontend();
	[$scraper, $filters] = $frontend->getscraperfilters(
		"images",
		isset($_GET["scraper"]) ? $_GET["scraper"] : null
	);
	$get = $frontend->parsegetfilters($_GET, $filters);
	echo json_encode(
		$scraper->image($get),
		JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_IGNORE
	);
	
}catch(Throwable $e){
	http_response_code(503);
	header("Cache-Control: no-store");
	header("Retry-After: 30");
	
	echo json_encode(["status" => search_health::public_message($e)]);
}
