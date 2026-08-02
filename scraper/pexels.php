<?php

class pexels{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("pexels");
	}
	
	public function getfilters($page){
		
		return [
			"time" => [ // date_from
				"display" => "Time posted",
				"option" => [
					"any" => "Any time",
					"last_24_hours" => "Last 24 hours",
					"last_week" => "Last week",
					"last_month" => "Last month",
					"last_year" => "Last year"
				]
			],
			"orientation" => [
				"display" => "Orientation",
				"option" => [
					"any" => "Any orientation",
					"landscape" => "Horizontal",
					"portrait" => "Vertical",
					"square" => "Square"
				]
			],
			"color" => [
				"display" => "Color",
				"option" => [
					"any" => "Any color",
					"795548" => "Brown",
					"F44336" => "Red",
					"E91E63" => "Hot pink",
					"9C27B0" => "Magenta",
					"673AB7" => "Purple",
					"3F51B5" => "Indigo",
					"2196F3" => "Blue",
					"03A9F4" => "Light blue",
					"00BCD4" => "Cyan",
					"009688" => "Forest green",
					"4CAF50" => "Green",
					"8BC34A" => "Lime",
					"CDDC39" => "Pear",
					"FFEB3B" => "Yellow",
					"FFC107" => "Gold",
					"FF9800" => "Orange",
					"FF5722" => "Tomato",
					"9E9E9E" => "Gray",
					"607D8B" => "Teal",
					"000000" => "Black",
					"FFFFFF" => "White"
				]
			],
			"people_count" => [
				"display" => "Head count",
				"option" => [
					"any" => "Any number",
					"0" => "0",
					"1" => "1",
					"2" => "2",
					"3_plus" => "3+",
				]
			],
			"people_age" => [
				"display" => "People's age",
				"option" => [
					"any" => "Any age",
					"baby" => "Baby",
					"child" => "Child",
					"teenager" => "Teenager",
					"adult" => "Adult",
					"senior_adult" => "Senior adult"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = []){
		
		$curlproc = curl_init();
		
		$search = $get["query"];
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: */*",
			"Accept-Language: en-US,en;q=0.9",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"Referer: https://www.pexels.com/search/" . rawurlencode($search) . "/",
			"Content-Type: application/json",
			"secret-key: H2jk9uKnhRmL6WPwh89zBezWvr", // hardcoded but like, people on github have been using this shit since 23'
			"X-Client-Type: react",
			"X-Next-Forwarded-CF-Connecting-IP: ",
			"X-Next-Forwarded-CF-IPCountry: ",
			"X-Next-Forwarded-CF-IPRegionCode: ",
			"X-Active-Experiment: ",
			"DNT: 1",
			"Sec-GPC: 1",
			"Alt-Used: www.pexels.com",
			"Connection: keep-alive",
			"Sec-Fetch-Dest: empty",
			"Sec-Fetch-Mode: cors",
			"Sec-Fetch-Site: same-origin",
			"Priority: u=4",
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
			
			// ?query=blue%20footed%20booby&page=1&per_page=24&seo_tags=true
			$filter = [
				"query" => $search,
				"page" => 1,
				"per_page" => 24,
				"seo_tags" => "true"
			];
			
			// add filters
			if($get["time"] != "any"){
				
				$filter["date_from"] = $get["time"];
			}
			
			if($get["orientation"] != "any"){
				
				$filter["orientation"] = $get["orientation"];
			}
			
			if($get["color"] != "any"){
				
				$filter["color"] = $get["color"];
			}
			
			if($get["people_count"] != "any"){
				
				$filter["people_count"] = $get["people_count"];
			}
			
			if($get["people_age"] != "any"){
				
				$filter["people_age"] = $get["people_age"];
			}
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		try{
			$html =
				$this->get(
					$proxy,
					"https://www.pexels.com/en-us/api/v3/search/photos",
					$filter
				);
			
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($html, true);
		
		if(!isset($json["data"])){
			
			throw new Exception("Pexels did not return a data object");
		}
		
		foreach($json["data"] as $image){
			
			$thumb_size =
				$this->imgratio(
					(int)$image["attributes"]["width"],
					(int)$image["attributes"]["height"],
					350
				);
			
			$out["image"][] = [
				"title" => $image["attributes"]["title"] . ": " . $image["attributes"]["description"],
				"source" => [
					[
						"url" => $image["attributes"]["image"]["download_link"],
						"width" => (int)$image["attributes"]["width"],
						"height" => (int)$image["attributes"]["height"]
					],
					[
						"url" =>
							preg_replace(
								'/(?:w|h)=[0-9]+$/',
								"w=350",
								$image["attributes"]["image"]["small"]
							),
						"width" => $thumb_size[0],
						"height" => $thumb_size[1]
					]
				],
				"url" =>
					"https://pexels.com/photo/" .
					$image["attributes"]["slug"] . "-" .
					$image["attributes"]["id"] . "/"
			];
		}
		
		// get next page
		if((int)$json["pagination"]["current_page"] < (int)$json["pagination"]["total_pages"]){
			
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
