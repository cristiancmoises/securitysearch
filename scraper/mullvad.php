<?php

class mullvad{
	
	public function __construct($engine){
		
		$this->engine = $engine;
		
		include "lib/backend.php";
		$this->backend = new backend("mullvad_{$this->engine}");
	}
	
	public function getfilters($page){
		return [
			"country" => [ // &country=
				"display" => "Country",
				"option" => [
					"any" => "Any country",
					"ar" => "Argentina",
					"au" => "Australia",
					"at" => "Austria",
					"be" => "Belgium",
					"br" => "Brazil",
					"ca" => "Canada",
					"cl" => "Chile",
					"cn" => "China",
					"dk" => "Denmark",
					"fi" => "Finland",
					"fr" => "France",
					"de" => "Germany",
					"hk" => "Hong Kong",
					"in" => "India",
					"id" => "Indonesia",
					"it" => "Italy",
					"jp" => "Japan",
					"kr" => "Korea, Republic",
					"my" => "Malaysia",
					"mx" => "Mexico",
					"nl" => "Netherlands",
					"nz" => "New Zealand",
					"no" => "Norway",
					"ph" => "Philippines",
					"pl" => "Poland",
					"pt" => "Portugal",
					"ru" => "Russian Federation",
					"sa" => "Saudi Arabia",
					"za" => "South Africa",
					"es" => "Spain",
					"se" => "Sweden",
					"ch" => "Switzerland",
					"tw" => "Taiwan",
					"tr" => "Turkey",
					"uk" => "United Kingdom",
					"us" => "United States"
				]
			],
			"language" => [ // &language=
				"display" => "Language",
				"option" => [
					"any" => "Any language",
					"ar" => "Arabic",
					"bg" => "Bulgarian",
					"ca" => "Catalan",
					"zh-hans" => "Chinese (Simplified)",
					"zh-hant" => "Chinese (Traditional)",
					"hr" => "Croatian",
					"cs" => "Czech",
					"da" => "Danish",
					"nl" => "Dutch",
					"en" => "English",
					"et" => "Estonian",
					"fi" => "Finnish",
					"fr" => "French",
					"de" => "German",
					"he" => "Hebrew",
					"hu" => "Hungarian",
					"is" => "Icelandic",
					"it" => "Italian",
					"jp" => "Japanese",
					"ko" => "Korean",
					"lv" => "Latvian",
					"lt" => "Lithuanian",
					"nb" => "Norwegian",
					"pl" => "Polish",
					"pt" => "Portuguese",
					"ro" => "Romanian",
					"ru" => "Russian",
					"sr" => "Serbian",
					"sk" => "Slovak",
					"sl" => "Slovenian",
					"es" => "Spanish",
					"sv" => "Swedish",
					"tr" => "Turkish"
				]
			],
			"time" => [ // &lastUpdated=
				"display" => "Time posted",
				"option" => [
					"any" => "Any time",
					"d" => "Past day",
					"w" => "Past week",
					"m" => "Past month",
					"y" => "Past year"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = []){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		// http2 bypass
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: */*",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"Referer: https://leta.mullvad.net/search",
			"DNT: 1",
			"Sec-GPC: 1",
			"Connection: keep-alive",
			"Cookie: engine=brave",
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
	
	public function web($get){
		
		if($get["npt"]){
			
			[$params, $proxy] = $this->backend->get($get["npt"], "web");
			$params = json_decode($params, true);
			
		}else{
			
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			// generate filters
			$params = [
				"q" => $get["s"],
				"engine" => $this->engine,
				"page" => 1
			];
			
			if($get["country"] != "any"){
				
				$params["country"] = $get["country"];
			}
			
			if($get["language"] != "any"){
				
				$params["language"] = $get["language"];
			}
			
			if($get["time"] != "any"){
				
				$params["lastUpdated"] = $get["time"];
			}
			
			$proxy = $this->backend->get_ip();
		}
		
		try{
			$json = $this->get(
				$proxy,
				"https://leta.mullvad.net/search/__data.json",
				$params
			);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch search page");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		if(!isset($json["nodes"])){
			
			throw new Exception("Mullvad did not return a nodes object");
		}
		
		$out = [
			"status" => "ok",
			"spelling" => [
				"type" => "no_correction",
				"using" => null,
				"correction" => null
			],
			"npt" => $nextpage,
			"answer" => [],
			"web" => [],
			"image" => [],
			"video" => [],
			"news" => [],
			"related" => []
		];
		
		// parse json payload
		foreach($json["nodes"] as $node){
			
			if(!isset($node["data"][0]["q"])){
				
				// not iterating through the query object
				continue;
			}
			
			// node 0 contains pointers to what we need to iterate through
			$node0 = &$node["data"][0];
			
			if(!isset($node["data"][$node0["success"]])){
				
				throw new Exception("Mullvad did not return a success object");
			}
			
			$success = &$node["data"][$node0["success"]];
			
			if($success === false){
				
				throw new Exception("Mullvad flagged the response as unsuccessful");
			}
			
			if(!isset($node["data"][$node0["items"]])){
				
				throw new Exception("Mullvad did not return an items object");
			}
			
			$search_pointers = &$node["data"][$node0["items"]];
			
			//
			// Iterate over results
			//
			foreach($search_pointers as $pointer){
				
				$pointer = &$node["data"][$pointer];
				
				$link = &$node["data"][$pointer["link"]];
				$title = &$node["data"][$pointer["title"]];
				$description = &$node["data"][$pointer["snippet"]];
				
				$date = null;
				if($this->engine == "google"){
					
					// attempt to extract date
					// Jan 12, 2017
					$date_parts = explode(" ... ", $description, 2);
					
					if(
						count($date_parts) === 2 &&
						strlen($date_parts[0]) < 15
					){
						
						$date = strtotime(trim($date_parts[0]));
						
						if($date === false){
							
							$date = null;
						}else{
							
							$description = trim($date_parts[1]);
						}
					}
				}
				
				$out["web"][] = [
					"title" => $this->titledots($title),
					"description" => $this->titledots($description),
					"url" => $link,
					"date" => $date,
					"type" => "web",
					"thumb" => [
						"url" => null,
						"ratio" => null
					],
					"sublink" => [],
					"table" => []
				];
			}
			
			//
			// Get nextpage
			//
			if(isset($node["data"][$node0["next"]])){
				
				$params["page"] = (int)$node["data"][$node0["next"]];
				
				$out["npt"] =
					$this->backend->store(
						json_encode($params),
						"web",
						$proxy
					);
			}
		}
		
		return $out;
	}
	
	private function titledots($title){
		
		return trim($title, " .\t\n\r\0\x0B…");
	}
}
