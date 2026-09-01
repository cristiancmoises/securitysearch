<?php
include_once __DIR__ . "/lib/security_headers.php";

/*
	Initialize random shit
*/
include "data/config.php";
include "lib/frontend.php";
$frontend = new frontend();

[$scraper, $filters] = $frontend->getscraperfilters("images");
$get = $frontend->parsegetfilters($_GET, $filters);

/*
	Captcha
*/
include "lib/bot_protection.php";
new bot_protection($frontend, $get, $filters, "images", true);

$payload = [
	"timetaken" => microtime(true),
	"images" => "",
	"nextpage" => "",
	"infinite_scroll" => ""
];

try{
	$results = $scraper->image($get);

}catch(Exception $error){

	$frontend->drawscrapererror($error->getMessage(), $get, "images", $payload["timetaken"]);
}


/* ============================================================
   EMPTY STATE
   ============================================================ */
if(count($results["image"]) === 0){

	$query = htmlspecialchars($get["s"] ?? "");

	$payload["images"] .=
		'<div class="images-empty">' .
			'<svg class="ie-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
				'<circle cx="11" cy="11" r="8"/>' .
				'<path d="m21 21-4.3-4.3"/>' .
				'<line x1="8" y1="11" x2="14" y2="11"/>' .
			'</svg>' .
			'<h2>No images found</h2>' .
			'<p>The scraper came back empty for <b>' . $query . '</b>.</p>' .
			'<ul class="ie-tips">' .
				'<li>Try a different image scraper in <a href="/settings">Settings</a></li>' .
				'<li>Use fewer or broader keywords</li>' .
				'<li>Check your NSFW filter — it may be hiding results</li>' .
				'<li>Some scrapers rate-limit briefly; wait a moment and retry</li>' .
			'</ul>' .
			'<div class="ie-actions">' .
				'<a href="/" class="ie-btn">← Home</a>' .
				'<a href="/settings" class="ie-btn primary">Open settings</a>' .
			'</div>' .
		'</div>';
}

/* ============================================================
   RESULTS — unchanged structure (4get lightbox JS depends on
   the .image-wrapper[data-json] attribute and class chain)
   ============================================================ */
foreach($results["image"] as $image){

	$host = parse_url($image["url"], PHP_URL_HOST) ?? "source";

	$payload["images"] .=
		'<div class="image-wrapper" title="' . htmlspecialchars($image["title"]) .'" data-json="' . htmlspecialchars(json_encode($image["source"])) . '">' .
			'<div class="image">' .
				'<a href="' . htmlspecialchars($image["source"][0]["url"]) . '" rel="noreferrer nofollow" class="thumb">' .
					'<img src="' . $frontend->htmlimage($image["source"][count($image["source"]) - 1]["url"], "thumb") . '" alt="thumbnail" loading="lazy" decoding="async">';

				if($image["source"][0]["width"] !== null){
					$payload["images"] .= '<div class="duration">' . $image["source"][0]["width"] . 'x' . $image["source"][0]["height"] . '</div>';
				}

			$payload["images"] .=
				'</a>' .
				'<a href="' . htmlspecialchars($image["url"]) . '" rel="noreferrer nofollow">' .
					'<div class="title">' . htmlspecialchars($host) . '</div>' .
					'<div class="description">' . $frontend->highlighttext($get["s"], $image["title"]) . '</div>' .
				'</a>' .
			'</div>' .
		'</div>';
}

/* ============================================================
   PAGINATION
   ============================================================ */
if($results["npt"] !== null){

	$payload["nextpage"] =
		'<a href="' . $frontend->htmlnextpage($get, $results["npt"], "images") . '" class="nextpage img">Next page &gt;</a>';

	if(($_COOKIE["image_infinite"] ?? "yes") !== "no"){
		$payload["infinite_scroll"] =
			'<script src="/static/images-infinite.js?v' . config::VERSION . '" defer></script>';
	}
}

echo $frontend->load("images.html", $payload);
