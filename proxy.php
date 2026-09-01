<?php
include_once __DIR__ . "/lib/security_headers_minimal.php";
include "data/config.php";
include "lib/curlproxy.php";
include "lib/animated_preview.php";
$proxy = new proxy();

if(
	!isset($_GET["i"]) ||
	!is_string($_GET["i"]) ||
	$_GET["i"] === "" ||
	strlen($_GET["i"]) > 16384
){
	
	header("X-Error: Missing or invalid URL(i)");
	$proxy->do404();
}
if(
	isset($_GET["s"]) &&
	(
		!is_string($_GET["s"]) ||
		!in_array($_GET["s"], ["original", "animated", "portrait", "landscape", "square", "thumb", "cover"], true)
	)
){

	header("X-Error: Invalid image size mode");
	$proxy->do404();
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
			header("Retry-After: 2");
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
			33554432,
			null,
			"image/gif,image/apng,image/png,image/webp;q=0.9,*/*;q=0.1"
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

			// One bounded structural pass validates GIF, WebP, or APNG and rejects
			// static/malformed data. In particular, avoid CRC-scanning APNG twice.
			$inspection = animated_preview_inspect_raster($payload["body"]);
			$frame_count = $inspection["frames"];
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
			'/\A(?:[A-Za-z0-9-]+\.)*bing\.(?:net|com)\z/i',
			$image["host"]
		)
	){
		
			if(!isset($image["path"])){
			
				header("X-Error: Missing bing image path");
				$proxy->do404();
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

					header("X-Error: Missing bing image id");
					$proxy->do404();
			}
			
			$id = $id[1];
		}
		
		if(!is_string($id) || $id === "" || strlen($id) > 4096){
			
			header("X-Error: Missing bing id parameter");
			$proxy->do404();
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
	$payload = $proxy->get($_GET["i"], $proxy::req_image, true, null, 0, 16777216);
	$resize_finfo = new finfo(FILEINFO_MIME_TYPE);
	$resize_mime = strtolower((string)$resize_finfo->buffer($payload["body"]));
	if(!in_array($resize_mime, ["image/jpeg", "image/png", "image/gif", "image/webp", "image/avif"], true)){

		throw new Exception("Remote thumbnail returned an unsupported raster format");
	}

	// Image grids already request a provider thumbnail. Relaying a genuinely
	// small JPEG or a structurally validated animation avoids an unnecessary
	// ImageMagick cycle. Originals used as poster fallbacks must still be resized
	// so a result page cannot download hundreds of megabytes of large JPEGs.
	if($_GET["s"] === "thumb" && strlen($payload["body"]) <= 1572864){

		$direct_mime = $resize_mime;
		$direct_types = [
			"image/jpeg" => "jpg",
			"image/png" => "png",
			"image/apng" => "png",
			"image/gif" => "gif",
			"image/webp" => "webp"
		];
		$direct_dimensions = @getimagesizefromstring($payload["body"]);
		$direct_passthrough =
			$direct_mime === "image/jpeg" &&
			strlen($payload["body"]) <= 131072 &&
			is_array($direct_dimensions) &&
			isset($direct_dimensions[0], $direct_dimensions[1]) &&
			$direct_dimensions[0] > 0 &&
			$direct_dimensions[1] > 0 &&
			$direct_dimensions[0] <= 512 &&
			$direct_dimensions[1] <= 512;
		if(in_array($direct_mime, ["image/png", "image/apng", "image/gif", "image/webp"], true)){

			try{

				$direct_motion = animated_preview_inspect_raster($payload["body"]);
				$direct_passthrough =
					$direct_motion["frames"] >= 2 &&
					$direct_motion["frames"] <= 1000 &&
					$direct_motion["width"] >= 1 &&
					$direct_motion["height"] >= 1 &&
					$direct_motion["width"] <= 2048 &&
					$direct_motion["height"] <= 2048 &&
					$direct_motion["width"] * $direct_motion["height"] <= 4000000 &&
					$direct_motion["width"] * $direct_motion["height"] * $direct_motion["frames"] <= 250000000;
			}catch(Throwable $error){

				// Static or malformed animation-capable formats use the established
				// ImageMagick thumbnail path instead of bypassing frame validation.
				$direct_passthrough = false;
			}
		}
		if(
			$direct_passthrough &&
			isset($direct_types[$direct_mime]) &&
			is_array($direct_dimensions) &&
			isset($direct_dimensions[0], $direct_dimensions[1]) &&
			$direct_dimensions[0] > 0 &&
			$direct_dimensions[1] > 0 &&
			$direct_dimensions[0] <= 2048 &&
			$direct_dimensions[1] <= 2048 &&
			$direct_dimensions[0] * $direct_dimensions[1] <= 4000000
		){

			$proxy->getfilenameheader($payload["headers"], $_GET["i"], $direct_types[$direct_mime]);
			header("Content-Type: " . $direct_mime);
			header("Content-Length: " . strlen($payload["body"]));
			echo $payload["body"];
			die();
		}
	}
	
	// Reject implausible raster headers before asking ImageMagick to allocate a
	// pixel cache. Decoder limits below remain authoritative when a format does
	// not expose dimensions through getimagesizefromstring().
	$raster_dimensions = @getimagesizefromstring($payload["body"]);
	if(
		is_array($raster_dimensions) &&
		isset($raster_dimensions[0], $raster_dimensions[1]) &&
		(
			$raster_dimensions[0] < 1 ||
			$raster_dimensions[1] < 1 ||
			$raster_dimensions[0] > 16384 ||
			$raster_dimensions[1] > 16384 ||
			$raster_dimensions[0] > intdiv(40000000, $raster_dimensions[1])
		)
	){

		throw new Exception("Remote image dimensions exceed the configured limit");
	}

	// ImageMagick's distribution defaults are intentionally broad. Thumbnail
	// conversion is instead confined to one frame, bounded memory/map/time, no
	// disk-backed pixel cache, and the same dimension envelope checked above.
	$imagick_resource_limits = [
		Imagick::RESOURCETYPE_AREA => 40000000,
		Imagick::RESOURCETYPE_MEMORY => 67108864,
		Imagick::RESOURCETYPE_MAP => 67108864,
		Imagick::RESOURCETYPE_DISK => 0,
		Imagick::RESOURCETYPE_THREAD => 1,
		Imagick::RESOURCETYPE_TIME => 10,
		Imagick::RESOURCETYPE_WIDTH => 16384,
		Imagick::RESOURCETYPE_HEIGHT => 16384,
		// ImageMagick rejects when the next frame reaches this value, so two
		// permits one static frame while refusing a second decoded frame.
		Imagick::RESOURCETYPE_LISTLENGTH => 2
	];
	$imagick_previous_limits = [];
	$image = null;
	$conversion_error = null;
	try{

		foreach($imagick_resource_limits as $resource_type => $resource_limit){

			$previous_limit = Imagick::getResourceLimit($resource_type);
			$imagick_previous_limits[$resource_type] =
				is_int($previous_limit) ?
				$previous_limit :
				($previous_limit >= PHP_INT_MAX ? PHP_INT_MAX : max(0, (int)$previous_limit));
			Imagick::setResourceLimit($resource_type, $resource_limit);
		}

		// The MIME allowlist above is authoritative. Let ImageMagick sniff the
		// blob instead of pre-setting an input format; pre-setting it can leave the
		// image without a writable current frame under a strict list policy.
		$image = new Imagick();

		if($resize_mime === "image/jpeg"){

			// Ask the JPEG decoder to subsample large originals near thumbnail size
			// before constructing its pixel cache.
			$image->setOption("jpeg:size", "512x512");
		}

		$image->readImageBlob($payload["body"]);
		
		$image_width = $image->getImageWidth();
		$image_height = $image->getImageHeight();
		if($image_width < 1 || $image_height < 1){

			throw new ImagickException("Image has invalid dimensions");
		}
		
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
		// ImageMagick policy coder patterns are case-sensitive.
		$image->setFormat("JPEG");
		$image->setImageCompressionQuality(90);
		$image->setImageCompression(Imagick::COMPRESSION_JPEG2000);
		
		$image->resizeImage($image_width, $image_height, Imagick::FILTER_LANCZOS, 1);
		
		$proxy->getfilenameheader($payload["headers"], $_GET["i"]);
		
		header("Content-Type: image/jpeg");
		echo $image->getImageBlob();

	}catch(Throwable $error){

		$conversion_error = $error;
	}finally{

		if($image instanceof Imagick){

			$image->clear();
			$image->destroy();
		}
		foreach($imagick_previous_limits as $resource_type => $resource_limit){

			Imagick::setResourceLimit($resource_type, $resource_limit);
		}
	}

	if($conversion_error !== null){

		$error_message = preg_replace('/[\r\n]+/', ' ', $conversion_error->getMessage());
		header("X-Error: Could not convert the image: (" . substr((string)$error_message, 0, 512) . ")");
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
}
