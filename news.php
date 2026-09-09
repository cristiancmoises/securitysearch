<?php
include_once __DIR__ . "/lib/security_headers.php";
ob_start();
/*
	Initialize request dependencies
*/
include "data/config.php";

include "lib/frontend.php";
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
	$results = $scraper->news($get);
	
}catch(Exception $error){
	
	$frontend->drawscrapererror($error->getMessage(), $get, "news", $payload["timetaken"]);
}

/*
	Populate links
*/
if($get['scraper']==='reddit') {
    $payload['left']='<p class="news-context">'.($get['s']==='' ? 'Latest posts' : 'Related posts').' from r/news and r/worldnews · <a href="https://libre.securityops.co/r/news+worldnews/new" rel="noreferrer noopener">Open Reddit</a></p>';
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
