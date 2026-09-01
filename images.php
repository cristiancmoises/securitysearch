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

$result_images =
	is_array($results ?? null) && isset($results["image"]) && is_array($results["image"]) ?
	$results["image"] :
	[];


/* ============================================================
   EMPTY STATE
   ============================================================ */
$render_empty_state = static function($query){

	return
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
};

/* ============================================================
   RESULTS — preserve the .image-wrapper[data-json] structure and
   class chain that the lightbox JavaScript depends on.
   ============================================================ */
$is_remote_image_url = static function($url){
	if(!is_string($url) || $url === "" || strlen($url) > 8192){

		return false;
	}

	$parts = parse_url($url);
	return
		is_array($parts) &&
		isset($parts["scheme"], $parts["host"]) &&
		in_array(strtolower($parts["scheme"]), ["http", "https"], true) &&
		$parts["host"] !== "" &&
		!isset($parts["user"]) &&
		!isset($parts["pass"]);
};

$rendered_images = 0;
foreach($result_images as $image){

	if(!is_array($image) || !isset($image["source"]) || !is_array($image["source"])){

		continue;
	}

	$safe_sources = [];
	$seen_source_urls = [];
	foreach($image["source"] as $source){

		$url = is_array($source) ? ($source["url"] ?? null) : null;
		if(!$is_remote_image_url($url) || isset($seen_source_urls[$url])){

			continue;
		}
		$seen_source_urls[$url] = true;
		$width = isset($source["width"]) && is_numeric($source["width"]) && (int)$source["width"] > 0 ? (int)$source["width"] : null;
		$height = isset($source["height"]) && is_numeric($source["height"]) && (int)$source["height"] > 0 ? (int)$source["height"] : null;
		// The lightbox dimension contract is a complete pair. A partial pair can
		// otherwise turn into division by null while scaling the popup.
		if($width === null || $height === null){

			$width = null;
			$height = null;
		}
		$safe_sources[] = ["url" => $url, "width" => $width, "height" => $height];
	}
	if($safe_sources === []){

		continue;
	}
	$image["source"] = $safe_sources;
	$source_count = count($safe_sources);
	$original_url = $safe_sources[0]["url"];
	$thumbnail_url = $safe_sources[$source_count - 1]["url"];

	$result_url = $is_remote_image_url($image["url"] ?? null) ? $image["url"] : $original_url;
	$title = isset($image["title"]) && is_string($image["title"]) ? $image["title"] : "Image result";
	$parsed_host = parse_url($result_url, PHP_URL_HOST);
	$host = is_string($parsed_host) && $parsed_host !== "" ? $parsed_host : "source";
	$provider_motion_url = $image["motion_url"] ?? null;
	$original_animation_format = $frontend->animatedimageformat($original_url);
	$thumbnail_animation_format = $frontend->animatedimageformat($thumbnail_url);
	$provider_url_animation_format = $frontend->animatedimageformat($provider_motion_url);
	$provider_animation_format = strtolower((string)($image["motion_format"] ?? ""));
	$provider_animation_format =
		in_array($provider_animation_format, ["gif", "webp", "apng"], true) ?
		strtoupper($provider_animation_format) :
		null;
	$animation_format = $provider_animation_format ?? $provider_url_animation_format ?? $original_animation_format ?? $thumbnail_animation_format;
	// A hint from either URL identifies the result as a motion candidate, but
	// prefer a provider-owned animated resize when one exists. It is normally
	// much smaller and avoids origin hotlink failures; the original remains the
	// first motion fallback and the thumbnail remains the poster.
	$motion_url =
		$is_remote_image_url($provider_motion_url) ?
		$provider_motion_url :
		($is_remote_image_url($original_url) ?
		$original_url :
		$thumbnail_url);

	// Provider filters can identify animation even when a signed CDN URL has
	// no useful extension. Default searches still use conservative URL hints.
	if($animation_format === null){

		$selected_format = strtolower((string)($get["format"] ?? ""));
		$selected_type = strtolower((string)($get["type"] ?? $get["imagetype"] ?? ""));
		if(in_array($selected_format, ["gif", "webp", "apng"], true)){

			$animation_format = strtoupper($selected_format);
			$motion_url = $is_remote_image_url($provider_motion_url) ? $provider_motion_url : $original_url;
		}elseif($selected_format === "6" || $selected_type === "gif" || strpos($selected_type, "animated") !== false){

			$animation_format = $selected_format === "6" || $selected_type === "gif" || strpos($selected_type, "gif") !== false ? "GIF" : "ANIMATED";
			$motion_url = $is_remote_image_url($provider_motion_url) ? $provider_motion_url : $original_url;
		}
	}

	// Only remote HTTP(S) sources can use the validated animated proxy path.
	// In particular, do not let an active format filter turn a data URL into a
	// direct, unbounded browser payload.
	if(!$is_remote_image_url($motion_url)){

		$animation_format = null;
	}

	$thumbnail_src = $frontend->htmlimage($thumbnail_url, "thumb");
	$poster_fallback_urls = [];
	for($source_index = $source_count - 2; $source_index >= 0; $source_index--){

		$fallback_url = $safe_sources[$source_index]["url"] ?? null;
		if(
			$is_remote_image_url($fallback_url) &&
			$fallback_url !== $thumbnail_url &&
			!in_array($fallback_url, $poster_fallback_urls, true)
		){

			$poster_fallback_urls[] = $fallback_url;
		}
		if(count($poster_fallback_urls) === 2){

			break;
		}
	}
	$image_fallback_attributes = "";
	if(isset($poster_fallback_urls[0])){

		$image_fallback_attributes .= ' data-image-fallback-src="' . $frontend->htmlimage($poster_fallback_urls[0], "thumb") . '"';
	}
	if(isset($poster_fallback_urls[1])){

		$image_fallback_attributes .= ' data-image-fallback-secondary-src="' . $frontend->htmlimage($poster_fallback_urls[1], "thumb") . '"';
	}

	if($animation_format !== null){

		// A WebP MIME/extension says the container can animate, not that it does.
		// Validate it automatically once, but avoid spending the sole cache-busted
		// retry on the many static WebP results returned by image providers.
		$motion_retry = strtoupper($animation_format) === "WEBP" ? "no" : "yes";
		$motion_fallback_attribute = "";
		if($motion_url !== $original_url && $is_remote_image_url($original_url)){

			$motion_fallback_attribute = ' data-motion-fallback-src="' . $frontend->htmlimage($original_url, "animated") . '"';
		}
		$image_markup =
			'<img src="' . $thumbnail_src . '" data-motion-src="' . $frontend->htmlimage($motion_url, "animated") . '"' . $motion_fallback_attribute . ' data-motion-format="' . htmlspecialchars($animation_format) . '" data-motion-retry="' . $motion_retry . '" data-poster-src="' . $thumbnail_src . '"' . $image_fallback_attributes . ' alt="' . htmlspecialchars($title) . '" class="animated-preview" loading="lazy" decoding="async" fetchpriority="low">' .
			'<span class="motion-badge" aria-hidden="true">' . htmlspecialchars($animation_format) . '</span>';
	}else{

		$image_markup =
			'<img src="' . $thumbnail_src . '"' . $image_fallback_attributes . ' alt="' . htmlspecialchars($title) . '" loading="lazy" decoding="async" fetchpriority="low">';
	}

	$source_json = json_encode($safe_sources, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	if(!is_string($source_json)){

		$source_json = "[]";
	}
	$rendered_images++;
	$payload["images"] .=
		'<div class="image-wrapper' . ($animation_format === null ? '' : ' animated-result') . '" title="' . htmlspecialchars($title) .'" data-json="' . htmlspecialchars($source_json) . '">' .
			'<div class="image">' .
				'<a href="' . htmlspecialchars($original_url) . '" rel="noreferrer nofollow" class="thumb">' .
					$image_markup;

				if($safe_sources[0]["width"] !== null && $safe_sources[0]["height"] !== null){
					$payload["images"] .= '<div class="duration">' . $safe_sources[0]["width"] . 'x' . $safe_sources[0]["height"] . '</div>';
				}

		$payload["images"] .=
				'</a>' .
				'<a href="' . htmlspecialchars($result_url) . '" rel="noreferrer nofollow">' .
					'<div class="title">' . htmlspecialchars($host) . '</div>' .
					'<div class="description">' . $frontend->highlighttext($get["s"], $title) . '</div>' .
				'</a>' .
			'</div>' .
		'</div>';
}

if($rendered_images === 0){

	$payload["images"] = $render_empty_state(htmlspecialchars($get["s"] ?? ""));
}

/* ============================================================
   PAGINATION
   ============================================================ */
if(isset($results["npt"]) && is_string($results["npt"]) && $results["npt"] !== ""){

	$payload["nextpage"] =
		'<a href="' . $frontend->htmlnextpage($get, $results["npt"], "images") . '" class="nextpage img">Next page &gt;</a>';

	if(($_COOKIE["image_infinite"] ?? "yes") !== "no"){
		$payload["infinite_scroll"] =
			'<script src="/static/images-infinite.js?v' . config::VERSION . '" defer></script>';
	}
}

echo $frontend->load("images.html", $payload);
