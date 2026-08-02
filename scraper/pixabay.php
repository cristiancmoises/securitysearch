<?php

class pixabay{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("pixabay");
	}
	
	public function getfilters($page){
		
		return [
			"nsfw" => [
				"display" => "NSFW",
				"option" => [
					"yes" => "Yes",
					"no" => "No"
				]
			],
			"time" => [
				"display" => "Time posted",
				"option" => [
					"any" => "Any time",
					"1d" => "< 24 hours",
					"3d" => "< 72 hours",
					"1w" => "< 7 days",
					"6m" => "< 6 months",
					"1y" => "< 12 months"
				]
			],
			"category" => [
				"display" => "Category",
				"option" => [
					"images" => "All images",
					"photos" => "Photos",
					"illustrations" => "Illustrations",
					"vectors" => "Vectors",
					"gifs" => "GIFs"
				]
			],
			"content_type" => [
				"display" => "Authenticity",
				"option" => [
					"authentic" => "Authentic only",
					"show_all" => "Authentic + AI slop",
					"ai" => "AI slop only"
				]
			],
			"order" => [
				"display" => "Sort by",
				"option" => [
					"relevance" => "Most relevant",
					"latest" => "Latest",
					"ec" => "Editor's choice",
					"trending" => "Trending"
				]
			],
			"orientation" => [
				"display" => "Orientation",
				"option" => [
					"any" => "Any orientation",
					"vertical" => "Vertical",
					"horizontal" => "Horizontal"
				]
			],
			"color" => [
				"display" => "Color",
				"option" => [
					"any" => "Any color",
					"transparent" => "Transparent",
					"grayscale" => "Grayscale",
					"red" => "Red",
					"orange" => "Orange",
					"yellow" => "Yellow",
					"green" => "Green",
					"turquoise" => "Turquoise",
					"blue" => "Blue",
					"brown" => "Brown",
					"black" => "Black",
					"gray" => "Gray",
					"white" => "White",
					"pink" => "Pink",
					"lilac" => "Lilac"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = [], $nsfw = true){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT . " Pixabay",
			"Accept: application/json",
			"Accept-Language: en-US,en;q=0.9",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"Referer: {$url}",
			"Cookie: " . ($nsfw ? "g_rated=off" : "g_rated=1"),
			"DNT: 1",
			"Sec-GPC: 1",
			"Connection: keep-alive",
			"Sec-Fetch-Dest: empty",
			"Sec-Fetch-Mode: no-cors",
			"Sec-Fetch-Site: same-origin",
			"x-fetch-bootstrap: 1",
			"Alt-Used: pixabay.com",
			"Priority: u=0",
			"Pragma: no-cache",
			"Cache-Control: no-cache"]
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
			
			[$npt, $proxy] =
				$this->backend->get(
					$get["npt"],
					"images"
				);
			
			$npt = json_decode($npt, true);
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$npt = [
				"s" => $search,
				"pagi" => 1,
				"category" => $get["category"]
			];
			
			// filters
			if($get["content_type"] != "show_all"){
				
				$npt["content_type"] = $get["content_type"];
			}
			
			if($get["order"] != "relevance"){
				
				$npt["order"] = $get["order"];
			}
			
			$npt["nsfw"] = $get["nsfw"] == "yes" ? true : false;
			
			if($get["orientation"] != "any"){
				
				$npt["orientation"] = $get["orientation"];
			}
			
			if($get["color"] != "any"){
				
				$npt["colors"] = $get["color"];
			}
			
			if($get["time"] != "any"){
				
				$npt["date"] = $get["time"];
			}
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		// https://pixabay.com/images/search/japan/?pagi=1
		
		$npt_pass = $npt;
		unset($npt_pass["s"]);
		unset($npt_pass["category"]);
		unset($npt_pass["nsfw"]);
		
		try{
			$json =
				$this->get(
					$proxy,
					"https://pixabay.com/{$npt["category"]}/search/" . rawurlencode($npt["s"]) . "/",
					$npt_pass,
					$npt["nsfw"]
				);
			
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		if(!isset($json["page"]["results"])){
			
			throw new Exception("Pixabay API did not return a results object");
		}
		
		//print_r($json);
		
		foreach($json["page"]["results"] as $image){
			
			if(
				!(
					$image["mediaType"] == "photo" ||
					$image["mediaType"] == "animation"
				)
			){
				
				continue;
			}
			
			// handle images that supply canvaRetouchUrl
			if(
				isset($image["canvaRetouchUrl"]) &&
				$image["canvaRetouchUrl"] !== null
			){
				
				parse_str(
					parse_url(
						$image["canvaRetouchUrl"],
						PHP_URL_QUERY
					),
					$base
				);
				
				if(!isset($base["image-url"])){
					
					// should not happen
					continue;
				}
				
				// get extension
				$base = $base["image-url"];
				
				$parsed_base = parse_url($base);
				
				$ext =
					explode(
						".",
						$parsed_base["path"]
					);
				
				$ext = $ext[count($ext) - 1];
				
				// restructure url
				$base =
					explode(
						"_",
						$base
					);
				
				unset($base[count($base) - 1]);
				$base = implode("_", $base);
				
				$ratio_1x = $this->imgratio((int)$image["width"], (int)$image["height"], 180);
				$ratio_2x = $this->imgratio((int)$image["width"], (int)$image["height"], 1920);
				
				$source = [
					[
						"url" => "{$base}_1920.{$ext}",
						"width" => $ratio_2x[0],
						"height" => $ratio_2x[1]
					],
					[
						"url" => "{$base}_180.{$ext}",
						"width" => $ratio_1x[0],
						"height" => $ratio_1x[1]
					]
				];
			}else{
				
				// get intended sizes if canva is not set
				// get 1x
				$source_1x = null;
				if(isset($image["sources"]["image_1x"])){ $source_1x = $image["sources"]["image_1x"]; }
				elseif(isset($image["sources"]["1x"])){ $source_1x = $image["sources"]["1x"]; }
				
				if($source_1x === null){ continue; } // should not happen
				
				preg_match(
					'/_([0-9]+)\./',
					$image["sources"]["1x"],
					$size_1x
				);
				
				$size_1x = (int)$size_1x[1];
				
				// get 2x
				$source_2x = null;
				if(isset($image["sources"]["gif_2x"])){ $source_2x = $image["sources"]["gif_2x"]; }
				elseif(isset($image["sources"]["2x"])){ $source_2x = $image["sources"]["2x"]; }
				
				if($source_2x === null){ continue; } // should not happen
				
				preg_match(
					'/_([0-9]+)\./',
					$source_2x,
					$size_2x
				);
				
				$size_2x = (int)$size_2x[1];
				
				// compute ratios
				$ratio_1x = $this->imgratio((int)$image["width"], (int)$image["height"], $size_1x);
				$ratio_2x = $this->imgratio((int)$image["width"], (int)$image["height"], $size_2x);
				
				// handle images that only give normal thumbnails
				$source = [
					[
						"url" => $source_2x,
						"width" => $ratio_2x[0],
						"height" => $ratio_2x[1]
					],
					[
						"url" => $source_1x,
						"width" => $ratio_1x[0],
						"height" => $ratio_1x[1]
					]
				];
			}
			
			$ratio_1x = $this->imgratio((int)$image["width"], (int)$image["height"], 180);
			$ratio_2x = $this->imgratio((int)$image["width"], (int)$image["height"], 1920);
			
			$out["image"][] = [
				"title" => $image["name"],
				"source" => $source,
				"url" => "https://pixabay.com" . $image["href"]
			];
		}
		
		// add next page
		if((int)$json["page"]["page"] < (int)$json["page"]["pages"]){
			
			$npt["pagi"]++;
			
			$out["npt"] =
				$this->backend->store(
					json_encode($npt),
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
