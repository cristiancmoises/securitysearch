<?php

class unsplash{
	
	public function __construct(){
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
		
		include "lib/backend.php";
		$this->backend = new backend("unsplash");
	}
	
	public function getfilters($page){
		
		return [
			"order_by" => [
				"display" => "Order by",
				"option" => [
					"relevance" => "Relevance",
					"latest" => "Newest",
					"editorial" => "Curated"
				]
			],
			"orientation" => [
				"display" => "Order by",
				"option" => [
					"any" => "Any orientation",
					"landscape" => "Landscape",
					"portrait" => "Portrait",
					"squarish" => "Square"
				]
			],
			"license" => [
				"display" => "License",
				"option" => [
					"any" => "Any license",
					"only" => "Unsplash+",
					"none" => "Free"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = [], $referer){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: */*",
			"Accept-Language: en-US",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"Referer: {$referer}",
			"client-geo-region: global",
			"x-client-version: 8999df28be3f138bf2c646df5d656e4dc6970ba0",
			"DNT: 1",
			"Sec-GPC: 1",
			"Connection: keep-alive",
			"Sec-Fetch-Dest: empty",
			"Sec-Fetch-Mode: cors",
			"Sec-Fetch-Site: same-origin",
			"Priority: u=0",
			"TE: trailers"]
		);
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);

		$this->backend->assign_proxy($curlproc, $proxy);
		
		$data = curl_exec($curlproc);
		
		if(curl_errno($curlproc)){
			
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		return $data;
	}
	
	public function image($get){
		
		if($get["npt"]){
			
			[$filter, $proxy] =
				$this->backend->get(
					$get["npt"],
					"images"
				);
			
			$filter = json_decode($filter, true);
			
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$filter = [
				"page" => 1,
				"per_page" => 20,
				"query" => $search
			];
			
			// add filters
			if($get["order_by"] != "relevance"){
				
				$filter["order_by"] = $get["order_by"];
			}
			
			if($get["orientation"] != "any"){
				
				$filter["orientation"] = $get["orientation"];
			}
			
			if($get["license"] != "any"){
				
				$filter["plus"] = $get["license"];
			}
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
				
		// https://unsplash.com/s/photos/shibuya-night?order_by=latest&orientation=landscape&license=free
		// https://unsplash.com/s/photos/%3Ctest-%3F!-haha == <test ?! haha
		
		// ?page=1&per_page=20&query=japan
		
		try{
			$json =
				$this->get(
					$proxy,
					"https://unsplash.com/napi/search/photos",
					$filter,
					"https://unsplash.com/s/photos/" . rawurlencode(str_replace(" ", "-", $filter["query"])),
				);
			
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Could not decode the JSON payload");
		}
		
		foreach($json["results"] as $image){
			
			$base = explode("?", $image["urls"]["raw"])[0];
			
			if(
				(bool)$image["premium"] ||
				(bool)$image["plus"]
			){
				
				// when we get "premium" images, give
				// 1. highest resolution with watermarks
				// 2. highest resolution without watermarks
				// (if width of image is above 900px, it has watermarks)
				// 3. thumbnail without watermark
				$x900 = $this->imgratio((int)$image["width"], (int)$image["height"], 900);
				$x500 = $this->imgratio((int)$image["width"], (int)$image["height"], 500);
				
				$source = [
					[
						"url" => $base,
						"width" => (int)$image["width"],
						"height" => (int)$image["height"]
					],
					[
						"url" => $base . "?w=900",
						"width" => $x900[0],
						"height" => $x900[1]
					],
					[
						"url" => $base . "?w=500",
						"width" => $x500[0],
						"height" => $x500[1]
					]
				];
			}else{
				
				$x500 = $this->imgratio((int)$image["width"], (int)$image["height"], 500);
				
				// image is free as in freedom(tm)
				$source = [
					[
						"url" => $base,
						"width" => (int)$image["width"],
						"height" => (int)$image["height"]
					],
					[
						"url" => $base . "?w=500",
						"width" => $x500[0],
						"height" => $x500[1]
					]
				];
			}
			
			$title = [];
			
			$image["description"] = trim($image["description"]);
			$image["alt_description"] = trim($image["alt_description"]);
			
			if(!empty($image["description"])){ $title[] = $image["description"]; }
			if(!empty($image["alt_description"])){ $title[] = $image["alt_description"]; }
			
			$title = implode(": ", $title);
			
			$out["image"][] = [
				"title" => $title,
				"source" => $source,
				"url" => "https://unsplash.com/photos/" . $image["slug"]
			];
		}
		
		// next page stuff
		if($filter["page"] < (int)$json["total_pages"]){
			
			$filter["page"]++;
			
			$out["npt"] =
				$this->backend->store(
					json_encode($filter),
					"images",
					$proxy
				);
		}
		
		return $out;
	}
	
	private function imgratio($width, $height, $max_width){
		
		$ratio = $max_width / $width;
		$new_height = floor($height * $ratio);
		
		return [
			$max_width,
			$new_height
		];
	}
}
