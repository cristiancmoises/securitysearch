<?php
include_once __DIR__ . "/lib/security_headers.php";

/*
	Initialize request dependencies
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
   RESULTS — preserve the .image-wrapper[data-json] structure and
   class chain that the lightbox JavaScript depends on.
   ============================================================ */
foreach($results["image"] as $image){

	$host = parse_url($image["url"], PHP_URL_HOST) ?? "source";
	$original_url = $image["source"][0]["url"];
	$thumbnail_url = $image["source"][count($image["source"]) - 1]["url"];
	$original_animation_format = $frontend->animatedimageformat($original_url);
	$thumbnail_animation_format = $frontend->animatedimageformat($thumbnail_url);
	$provider_animation_format = strtolower((string)($image["motion_format"] ?? ""));
	$provider_animation_format =
		in_array($provider_animation_format, ["gif", "webp", "apng"], true) ?
		strtoupper($provider_animation_format) :
		null;
	$animation_format = $provider_animation_format ?? $original_animation_format ?? $thumbnail_animation_format;
	// A hint from either URL identifies the result as a motion candidate, but
	// always validate and play the provider's full-size original when available.
	// The thumbnail remains only the poster and a fallback for malformed results.
	$motion_url =
		is_string($original_url) && preg_match('#^https?://#i', $original_url) === 1 ?
		$original_url :
		$thumbnail_url;

	// Provider filters can identify animation even when a signed CDN URL has
	// no useful extension. Default searches still use conservative URL hints.
	if($animation_format === null){

		$selected_format = strtolower((string)($get["format"] ?? ""));
		$selected_type = strtolower((string)($get["type"] ?? $get["imagetype"] ?? ""));
		if(in_array($selected_format, ["gif", "webp", "apng"], true)){

			$animation_format = strtoupper($selected_format);
			$motion_url = $original_url;
		}elseif($selected_format === "6" || $selected_type === "gif" || strpos($selected_type, "animated") !== false){

			$animation_format = $selected_format === "6" || $selected_type === "gif" || strpos($selected_type, "gif") !== false ? "GIF" : "ANIMATED";
			$motion_url = $original_url;
		}
	}

	// Only remote HTTP(S) sources can use the validated animated proxy path.
	// In particular, do not let an active format filter turn a data URL into a
	// direct, unbounded browser payload.
	if(!is_string($motion_url) || preg_match('#^https?://#i', $motion_url) !== 1){

		$animation_format = null;
	}

	$thumbnail_src = $frontend->htmlimage($thumbnail_url, "thumb");
	if($animation_format !== null){

		$image_markup =
			'<img src="' . $thumbnail_src . '" data-motion-src="' . $frontend->htmlimage($motion_url, "animated") . '" data-poster-src="' . $thumbnail_src . '" alt="' . htmlspecialchars($image["title"]) . '" class="animated-preview" loading="lazy" decoding="async" fetchpriority="low">' .
			'<span class="motion-badge" aria-hidden="true">' . htmlspecialchars($animation_format) . '</span>';
	}else{

		$image_markup =
			'<img src="' . $thumbnail_src . '" alt="' . htmlspecialchars($image["title"]) . '" loading="lazy" decoding="async" fetchpriority="low">';
	}

	$payload["images"] .=
		'<div class="image-wrapper' . ($animation_format === null ? '' : ' animated-result') . '" title="' . htmlspecialchars($image["title"]) .'" data-json="' . htmlspecialchars(json_encode($image["source"])) . '">' .
			'<div class="image">' .
				'<a href="' . htmlspecialchars($original_url) . '" rel="noreferrer nofollow" class="thumb">' .
					$image_markup;

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
