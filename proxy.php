<?php
include_once __DIR__ . "/lib/security_headers_minimal.php";
include "data/config.php";
include "lib/curlproxy.php";
include "lib/animated_preview.php";
$proxy = new proxy();

if(!isset($_GET["i"])){
	
	header("X-Error: No URL(i) provided!");
	$proxy->do404();
	die();
}

try{
	
	// original size request, stream file to browser
	if(
		!isset($_GET["s"]) ||
		$_GET["s"] == "original"
	){
		
		$proxy->stream_linear_image($_GET["i"]);
		die();
	}

	// Preserve animation for lazy image-grid previews. This path stays behind
	// the privacy proxy and is capped more tightly than the full-size viewer.
	if($_GET["s"] == "animated"){

		if(!admit_animated_preview()){

			http_response_code(429);
			header("Retry-After: 60");
			header("Cache-Control: no-store");
			header("Pragma: no-cache");
			header("Expires: 0");
			header("Content-Length: 0");
			die();
		}
		$proxy = new proxy(false);

		$payload = $proxy->get(
			$_GET["i"],
			$proxy::req_image,
			true,
			null,
			0,
			20000000
		);

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->buffer($payload["body"]);
		$allowed_mimes = [
			"image/gif",
			"image/webp",
			"image/apng",
			"image/png"
		];
		if(!is_string($mime) || !in_array(strtolower($mime), $allowed_mimes, true)){

			throw new Exception("Animated preview returned an unsupported image format");
		}

		try{

			if($mime === "image/png" || $mime === "image/apng"){

				// Reject malformed or structurally expensive PNGs before invoking
				// ImageMagick's decoder on attacker-controlled chunk streams.
				$frame_count = animated_preview_apng_frame_count($payload["body"]);
				if($frame_count < 2){

					throw new Exception("Animated PNG failed structural validation");
				}
				$inspection = animated_preview_inspect_raster($payload["body"]);
			}else{

				$inspection = animated_preview_inspect_raster($payload["body"]);
				$frame_count = $inspection["frames"];
			}
			$width = $inspection["width"];
			$height = $inspection["height"];
		}catch(Throwable $error){

			throw new Exception("Animated preview could not be inspected");
		}

		if(
			$frame_count < 2 ||
			$frame_count > 1000 ||
			$width < 1 ||
			$height < 1 ||
			$width > 16384 ||
			$height > 16384 ||
			$width * $height > 40000000 ||
			$width * $height * $frame_count > 250000000
		){

			throw new Exception("Animated preview exceeds the validation limits");
		}

		$filetype = explode("/", strtolower($mime), 2)[1];
		$source = parse_url($_GET["i"]);
		$upstream_cache = strtolower((string)($payload["headers"]["cache-control"] ?? ""));
		$upstream_pragma = strtolower((string)($payload["headers"]["pragma"] ?? ""));
		$upstream_no_store = preg_match('/(?:^|,)\s*(?:no-store|no-cache)\b/', $upstream_cache) === 1 ||
			preg_match('/(?:^|,)\s*no-cache\b/', $upstream_pragma) === 1;
		$upstream_private = preg_match('/(?:^|,)\s*private\b/', $upstream_cache) === 1 ||
			isset($payload["headers"]["set-cookie"]);
		$public_source = is_array($source) &&
			!isset($source["user"]) &&
			!isset($source["pass"]) &&
			!isset($source["query"]);
		$public_cache = $public_source && !$upstream_private && !$upstream_no_store;
		if($upstream_no_store){

			$cache_ttl = 0;
			header("Cache-Control: no-store");
			header("Pragma: no-cache");
		}elseif($public_cache){

			$cache_ttl = 300;
			header("Cache-Control: public, max-age=300, s-maxage=300, stale-while-revalidate=30");
			header("Pragma: public");
		}else{

			$cache_ttl = 120;
			header("Cache-Control: private, max-age=120");
			header("Pragma: private");
		}
		header("Expires: " . ($cache_ttl === 0 ? "0" : gmdate("D, d M Y H:i:s", time() + $cache_ttl) . " GMT"));
		header_remove("Set-Cookie");
		$proxy->getfilenameheader($payload["headers"], $_GET["i"], $filetype);
		header("Content-Type: " . strtolower($mime));
		header("Content-Length: " . strlen($payload["body"]));
		echo $payload["body"];
		die();
	}
	
	// bing request, ask bing to resize and stream to browser
	$image = parse_url($_GET["i"]);
	
	if(
		isset($image["host"]) &&
		preg_match(
			'/^[A-z0-9.]*bing\.(net|com)$/i',
			$image["host"]
		)
	){
		
		if(!isset($image["path"])){
			
			header("X-Error: Missing bing image path");
			$proxy->do404();
			die();
		}
		
		//
		// get image ID
		// formations:
		// https://tse2.mm.bing.net/th/id/OIP.3yLBkUPn8EXA1wlhWP2BHwHaE3
		// https://tse2.mm.bing.net/th?id=OIP.3yLBkUPn8EXA1wlhWP2BHwHaE3
		//
		$id = null;
		if(isset($image["query"])){
			
			parse_str($image["query"], $str);
			
			if(isset($str["id"])){
				
				$id = $str["id"];
			}
		}
		
		if($id === null){
			
			$id = explode("/th/id/", $image["path"], 2);
			
			if(count($id) !== 2){
				
				// malformed
				return $url;
			}
			
			$id = $id[1];
		}
		
		if(is_array($id)){
			
			header("X-Error: Missing bing id parameter");
			$proxy->do404();
			die();
		}
			
		switch($_GET["s"]){
			
			case "portrait": $req = "&w=50&h=90&p=0&qlt=90"; break;
			case "landscape": $req = "&w=160&h=90&p=0&qlt=90"; break;
			case "square": $req = "&w=90&h=90&p=0&qlt=90"; break;
			case "thumb": $req = "&w=236&h=180&p=0&qlt=90"; break;
			case "cover": $req = "&w=207&h=270&p=0&qlt=90"; break;
		}
		
		$proxy->stream_linear_image("https://" . $image["host"] . "/th?id=" . rawurlencode($id) . $req, "https://www.bing.com");
		die();
	}
	
	// resize image ourselves
	$payload = $proxy->get($_GET["i"], $proxy::req_image, true);
	
	// get image format & set imagick
	$image = null;
	$format = $proxy->getimageformat($payload, $image);
	
	try{
		
		if($format !== false){
			$image->setFormat($format);
		}
		
		$image->readImageBlob($payload["body"]);
		
		$image_width = $image->getImageWidth();
		$image_height = $image->getImageHeight();
		
		switch($_GET["s"]){
			
			case "portrait":
				$width = 50;
				$height = 90;
				break;
			
			case "landscape":
				$width = 160;
				$height = 90;
				break;
			
			case "square":
				$width = 90;
				$height = 90;
				break;
			
			case "thumb":
				$width = 236;
				$height = 180;
				break;
			
			case "cover":
				$width = 207;
				$height = 270;
				break;
		}
		
		$ratio = $image_width / $image_height;
		
		if($image_width > $width){
			
			$image_width = $width;
			$image_height = round($image_width / $ratio);
		}
		if($image_height > $height){
			
			$ratio = $image_width / $image_height;
			$image_height = $height;
			$image_width = $image_height * $ratio;
		}
		
		$image->setImageBackgroundColor("#504945");
		$image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
		
		$image->stripImage();
		$image->setFormat("jpeg");
		$image->setImageCompressionQuality(90);
		$image->setImageCompression(Imagick::COMPRESSION_JPEG2000);
		
		$image->resizeImage($image_width, $image_height, Imagick::FILTER_LANCZOS, 1);
		
		$proxy->getfilenameheader($payload["headers"], $_GET["i"]);
		
		header("Content-Type: image/jpeg");
		echo $image->getImageBlob();
		
	}catch(ImagickException $error){
		
		header("X-Error: Could not convert the image: (" . $error->getMessage() . ")");
		$proxy->do404();
	}
	
}catch(Exception $error){

	if(isset($_GET["s"]) && $_GET["s"] === "animated"){

		header("Cache-Control: no-store");
		header("Pragma: no-cache");
		header("Expires: 0");
	}
	
	header("X-Error: " . $error->getMessage());
	$proxy->do404();
	die();
}
