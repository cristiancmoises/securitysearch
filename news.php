<?php
include_once __DIR__ . "/lib/security_headers.php";
ob_start();
/*
	Initialize request dependencies
*/
include "data/config.php";

include "lib/frontend.php";
require_once __DIR__."/lib/search_guard.php";
$frontend = new frontend();

[$scraper, $filters] = $frontend->getscraperfilters("news");

$get = $frontend->parsegetfilters($_GET, $filters);

/*
	Captcha
*/
include "lib/bot_protection.php";
new bot_protection($frontend, $get, $filters, "news", true);

$payload = [
	"timetaken" => microtime(true),
	"class" => "",
	"right-left" => "",
	"right-right" => "",
	"left" => ""
];

try{
	$results = search_guard::run($scraper,"news",$get);
	
}catch(Exception $error){
	
	$frontend->drawscrapererror($error->getMessage(), $get, "news", $payload["timetaken"]);
}

/*
	Populate links
*/
if($get['scraper']==='reddit') {
    $origin=$results['_service'] ?? service_pool::primary();
    if(!in_array($origin,service_pool::origins(),true)) $origin=service_pool::primary();
    $payload['left']='<p class="news-context">'.($get['s']==='' ? 'Latest posts' : 'Related posts').' from r/news and r/worldnews · <a href="'.htmlspecialchars($origin,ENT_QUOTES).'/r/news+worldnews/new" rel="noreferrer noopener">Open Reddit</a><small>Source: '.htmlspecialchars(parse_url($origin,PHP_URL_HOST),ENT_QUOTES).(!empty($results['_cached']) ? ' · public feed cache (up to 60 seconds)' : '').'. '.(count(service_pool::origins())>1 ? 'A fallback Redlib instance may receive this query after a failure.' : 'External Redlib fallback is disabled.').'</small></p>';
}
if(count($results['news'])===0) {
    $payload['left'].='<div class="infobox"><h1>No news found</h1><p>Try fewer keywords, another period or another provider.</p></div>';
}

foreach($results["news"] as $news){
	
	$greentext = [];
	
	if($news["date"] !== null){
		
		$greentext[] = date("jS M y @ g:ia", $news["date"]);
	}
	
	if($news["author"] !== null){
		
		$greentext[] = $news["author"];
	}
	
	if(count($greentext) !== 0){
		
		$greentext = implode(" • ", $greentext);
	}else{
		
		$greentext = null;
	}
	
	$n = null;
	$payload["left"] .= $frontend->drawtextresult($news, $greentext, $n, $get["s"]);
}

if($results["npt"] !== null){
	
	$payload["left"] .=
		'<a href="' . htmlspecialchars($frontend->htmlnextpage($get, $results["npt"], "news"),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8') . '" class="nextpage">Next page &gt;</a>';
}

echo $frontend->load("search.html", $payload);
