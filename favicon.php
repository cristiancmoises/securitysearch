<?php
include_once __DIR__ . "/lib/security_headers_minimal.php";

if(!isset($_GET["s"]) || !is_string($_GET["s"]) || strlen($_GET["s"]) > 300){
	
	header("X-Error: Missing parameter (s)ite");
	die();
}

include "data/config.php";
require_once __DIR__."/lib/favicon_policy.php";
new favicon($_GET["s"]);

class favicon{
	private $proxy;
	private $filename;
    private $budget;
    private function fetch($url, $type = proxy::req_web, $all = false, $referer = null) {
        if ($this->budget === null) {
            $this->budget = (object)['deadline'=>hrtime(true)+8000000000,'remaining_bytes'=>2097152,'remaining_wire_bytes'=>2097152];
        }
        return $this->proxy->get($url,$type,$all,$referer,0,2097152,$this->budget);
    }
	
	public function __construct($url){
		
		header("Content-Type: image/png");
		
		if(
			preg_match(
				'/^https?:\/\/[A-Za-z0-9.-]+$/',
				$url
			) === 0
		){
			
			header("X-Error: Only provide the protocol and domain");
			$this->defaulticon();
		}
		
		$filename = str_replace(["https://", "http://"], "", $url);
		header("Content-Disposition: inline; filename=\"{$filename}.png\"");
		
		include "lib/curlproxy.php";
		$this->proxy = new proxy(false);
		
		$this->filename = parse_url($url, PHP_URL_HOST);
		
		/*
			Check if we have the favicon stored locally
		*/
		$icon_path = "icons/" . $filename . ".png";
		if(is_file($icon_path)){
			$icon = file_get_contents($icon_path);
			if($icon !== false && $icon !== ""){
				$this->success_headers(strlen($icon));
				echo $icon;
				return;
			}
		}

		// A failed favicon is cosmetic; do not let repeated failures monopolize
		// PHP workers needed for actual web/image/news searches.
		if(favicon_policy::is_negative($this->filename)){
			header("X-Error: Favicon temporarily unavailable");
			$this->defaulticon();
		}
		$lease=favicon_policy::acquire($this->filename);
		if($lease===null){
			header("X-Error: Favicon refresh is already busy");
			$this->defaulticon();
		}
		$this->remote_attempted=true;
		register_shutdown_function(static function() use ($lease){ favicon_policy::release($lease); });
		$this->budget=(object)['deadline'=>hrtime(true)+favicon_policy::REMOTE_BUDGET_NS,'remaining_bytes'=>2097152,'remaining_wire_bytes'=>2097152];
		
		/*
			Scrape html
		*/
		try{
			
			$payload = $this->fetch($url, $this->proxy::req_web, true);
			
		}catch(Exception $error){
			
			header("X-Error: Favicon temporarily unavailable");
			$this->favicon404();
		}
		//$payload["body"] = '<link rel="manifest" id="MANIFEST_LINK" href="/data/manifest/" crossorigin="use-credentials" />';
		
		// get link tags
		preg_match_all(
			'/< *link +(.*)[\/]?>/Uixs',
			$payload["body"],
			$linktags
		);
		
		/*
			Get relevant tags
		*/
		
		$linktags = $linktags[1];
		$attributes = [];
		
		/*
		header("Content-Type: text/plain");
		print_r($linktags);
		print_r($payload);
		die();*/
		
		for($i=0; $i<count($linktags); $i++){
			
			// get attributes
			preg_match_all(
				'/([A-Za-z0-9]+) *= *("[^"]*"|[^" ]+)/s',
				$linktags[$i],
				$tags
			);
			
			for($k=0; $k<count($tags[1]); $k++){
				
				$attributes[$i][] = [
					"name" => $tags[1][$k],
					"value" => trim($tags[2][$k], "\" \n\r\t\v\x00")
				];
			}
		}

		unset($payload);
		unset($linktags);

		$href = [];
		
		// filter out the tags we want
		foreach($attributes as &$group){
			
			$tmp_href = null;
			$tmp_rel = null;
			$badtype = false;
			
			foreach($group as &$attribute){
				
				switch($attribute["name"]){
					
					case "rel":
						
						$attribute["value"] = strtolower($attribute["value"]);
						
						if(
							(
								$attribute["value"] == "icon" ||
								$attribute["value"] == "manifest" ||
								$attribute["value"] == "shortcut icon" ||
								$attribute["value"] == "apple-touch-icon" ||
								$attribute["value"] == "mask-icon"
							) === false
						){
							
							break;
						}
						
						$tmp_rel = $attribute["value"];
						break;
					
					case "type":
						$attribute["value"] = explode("/", $attribute["value"], 2);
						
						if(strtolower($attribute["value"][0]) != "image"){
							
							$badtype = true;
							break;
						}
						break;
					
					case "href":
						
						// must not contain invalid characters
						// must be bigger than 1
						if(
							filter_var($attribute["value"], FILTER_SANITIZE_URL) == $attribute["value"] &&
							strlen($attribute["value"]) > 0
						){
							
							$tmp_href = $attribute["value"];
							break;
						}
						break;
				}
			}
			
			if(
				$badtype === false &&
				$tmp_rel !== null &&
				$tmp_href !== null
			){
				
				$href[$tmp_rel] = $tmp_href;
			}
		}
		
		/*
			Priority list
		*/
		/*
		header("Content-Type: text/plain");
		print_r($href);
		die();*/
		
		if(isset($href["icon"])){ $href = $href["icon"]; }
		elseif(isset($href["apple-touch-icon"])){ $href = $href["apple-touch-icon"]; }
		elseif(isset($href["manifest"])){
			
			// attempt to parse manifest, but fallback to []
			$href = $this->parsemanifest($href["manifest"], $url);
		}
		
		if(is_array($href)){
			
			if(isset($href["mask-icon"])){ $href = $href["mask-icon"]; }
			elseif(isset($href["shortcut icon"])){ $href = $href["shortcut icon"]; }
			else{
				
				$href = "/favicon.ico";
			}
		}
		
		$href = $this->proxy->getabsoluteurl($href, $url);
		/*
		header("Content-type: text/plain");
		echo $href;
		die();*/
		
		
		/*
			Download the favicon
		*/
		//$href = "https://git.lolcat.ca/assets/img/logo.svg";
		
		try{
			$payload =
				$this->fetch(
					$href,
					$this->proxy::req_image,
					true,
					$url
				);
				
		}catch(Exception $error){
			
			header("X-Error: Favicon temporarily unavailable");
			$this->favicon404();
		}
		
		/*
			Parse the file format
		*/
		$image = null;
		$format = $this->proxy->getimageformat($payload, $image);
		
		/*
			Convert the image
		*/
		try{
			
			/*
				@todo: fix issues with avif+transparency
				maybe using GD as fallback?
			*/
			if($format !== false){
				$image->setFormat($format);
			}
			
			$image->setBackgroundColor(new ImagickPixel("transparent"));
			$image->readImageBlob($payload["body"]);
			$image->resizeImage(16, 16, imagick::FILTER_LANCZOS, 1);
			$image->setFormat("png");
			
			$image = $image->getImageBlob();
			
			// Save atomically; never expose a partially written cache entry.
			$path="icons/".$this->filename.".png";
			$tmp=tempnam("icons", ".icon-");
			if($tmp!==false){
				if(file_put_contents($tmp,$image,LOCK_EX)!==false) rename($tmp,$path);
				if(is_file($tmp)) unlink($tmp);
			}
			favicon_policy::clear_failure($this->filename);
			$this->success_headers(strlen($image));
			echo $image;
			
		}catch(ImagickException $error){
			
			header("X-Error: Favicon temporarily unavailable");
			$this->favicon404();
		}
		
		return;
	}
	
	private function parsemanifest($href, $url){
		
		if(
			// check if base64-encoded JSON manifest
			preg_match(
				'/^data:application\/json;base64,([A-Za-z0-9=]*)$/',
				$href,
				$json
			)
		){
			
			$json = base64_decode($json[1]);
			
			if($json === false){
				
				// could not decode the manifest regex
				return [];
			}
			
		}else{
			
			try{
				$json =
					$this->fetch(
						$this->proxy->getabsoluteurl($href, $url),
						$this->proxy::req_web,
						false,
						$url
					);
					
					$json = $json["body"];
					
			}catch(Exception $error){
				
				// could not fetch the manifest
				return [];
			}
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			// manifest did not return valid json
			return [];
		}
		
		if(
			isset($json["start_url"]) &&
			$this->proxy->validateurl($json["start_url"])
		){
			
			$url = $json["start_url"];
		}
		
		if(!isset($json["icons"][0]["src"])){
			
			// manifest does not contain a path to the favicon
			return [];
		}
		
		// horay, return the favicon path
		return $json["icons"][0]["src"];
	}
	
	private function favicon404(){
		
		// fallback to google favicons
		// ... probably blocked by cuckflare
		try{
			
			$image =
				$this->fetch(
					"https://t0.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=http://{$this->filename}&size=16",
					$this->proxy::req_image
				);
		}catch(Exception $error){
			
			$this->defaulticon();
		}
		
        // Only complete small PNGs may enter the shared favicon cache.
        $dims = @getimagesizefromstring($image['body']);
        if (!is_array($dims) || ($dims['mime'] ?? '') !== 'image/png' ||
            $dims[0] > 256 || $dims[1] > 256 || strlen($image['body']) > 131072) {
            $this->defaulticon();
        }
        $path = 'icons/'.$this->filename.'.png';
        $tmp = tempnam('icons', '.icon-');
        if ($tmp !== false) {
            if (file_put_contents($tmp,$image['body'],LOCK_EX) !== false) { rename($tmp,$path); }
            if (is_file($tmp)) { unlink($tmp); }
        }
		favicon_policy::clear_failure($this->filename);
		$this->success_headers(strlen($image['body']));
		
		echo $image["body"];
		die();
	}
	
	private function success_headers(int $length): void {
		header('Cache-Control: public, max-age='.favicon_policy::SUCCESS_BROWSER_TTL.', stale-while-revalidate=604800');
		header('Content-Length: '.$length);
	}

	private function defaulticon(){
		if($this->remote_attempted && isset($this->filename) && is_string($this->filename)) favicon_policy::mark_failure($this->filename);
		// Keep the established 404 contract but make the decorative fallback cheap
		// on repeat views instead of refetching it for every result page.
		http_response_code(404);
		header('Cache-Control: public, max-age='.favicon_policy::FAILURE_BROWSER_TTL);
		$path='lib/favicon404.png';
		$size=filesize($path);
		if(is_int($size)) header('Content-Length: '.$size);
		readfile($path);
		die();
	}
}
