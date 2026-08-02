<?php

class ddg{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("ddg");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	/*
		curl functions
	*/
	private const req_web = 0;
	private const req_xhr = 1;
	
	private function get($proxy, $url, $get = [], $reqtype = self::req_web){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		// http2 bypass
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		switch($reqtype){
			case self::req_web:
				$headers =
					["User-Agent: " . config::USER_AGENT,
					"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip",
					"DNT: 1",
					"Sec-GPC: 1",
					"Connection: keep-alive",
					"Upgrade-Insecure-Requests: 1",
					"Sec-Fetch-Dest: document",
					"Sec-Fetch-Mode: navigate",
					"Sec-Fetch-Site: same-origin",
					"Sec-Fetch-User: ?1",
					"Priority: u=0, i",
					"TE: trailers"];
				break;
			
			case self::req_xhr:
				$headers =
					["User-Agent: " . config::USER_AGENT,
					"Accept: */*",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip",
					"Referer: https://duckduckgo.com/",
					"DNT: 1",
					"Sec-GPC: 1",
					"Connection: keep-alive",
					"Sec-Fetch-Dest: script",
					"Sec-Fetch-Mode: no-cors",
					"Sec-Fetch-Site: same-site",
					"Priority: u=1"];
				break;
		}
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER, $headers);
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);
		
		$data = curl_exec($curlproc);
		
		if(curl_errno($curlproc)){
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		return $data;
	}
	
	public function getfilters($pagetype){
		
		$base = [
			"country" => [
				"display" => "Country",
				"option" => [
					"us-en" => "US (English)",
					"ar-es" => "Argentina",
					"au-en" => "Australia",
					"at-de" => "Austria",
					"be-fr" => "Belgium (fr)",
					"be-nl" => "Belgium (nl)",
					"br-pt" => "Brazil",
					"bg-bg" => "Bulgaria",
					"ca-en" => "Canada (en)",
					"ca-fr" => "Canada (fr)",
					"ct-ca" => "Catalonia",
					"cl-es" => "Chile",
					"cn-zh" => "China",
					"co-es" => "Colombia",
					"hr-hr" => "Croatia",
					"cz-cs" => "Czech Republic",
					"dk-da" => "Denmark",
					"ee-et" => "Estonia",
					"fi-fi" => "Finland",
					"fr-fr" => "France",
					"de-de" => "Germany",
					"gr-el" => "Greece",
					"hk-tzh" => "Hong Kong",
					"hu-hu" => "Hungary",
					"in-en" => "India (en)",
					"id-en" => "Indonesia (en)",
					"ie-en" => "Ireland",
					"il-en" => "Israel (en)",
					"it-it" => "Italy",
					"jp-jp" => "Japan",
					"kr-kr" => "Korea",
					"lv-lv" => "Latvia",
					"lt-lt" => "Lithuania",
					"my-en" => "Malaysia (en)",
					"mx-es" => "Mexico",
					"nl-nl" => "Netherlands",
					"nz-en" => "New Zealand",
					"no-no" => "Norway",
					"pk-en" => "Pakistan (en)",
					"pe-es" => "Peru",
					"ph-en" => "Philippines (en)",
					"pl-pl" => "Poland",
					"pt-pt" => "Portugal",
					"ro-ro" => "Romania",
					"ru-ru" => "Russia",
					"xa-ar" => "Saudi Arabia",
					"sg-en" => "Singapore",
					"sk-sk" => "Slovakia",
					"sl-sl" => "Slovenia",
					"za-en" => "South Africa",
					"es-ca" => "Spain (ca)",
					"es-es" => "Spain (es)",
					"se-sv" => "Sweden",
					"ch-de" => "Switzerland (de)",
					"ch-fr" => "Switzerland (fr)",
					"tw-tzh" => "Taiwan",
					"th-en" => "Thailand (en)",
					"tr-tr" => "Turkey",
					"us-es" => "US (Spanish)",
					"ua-uk" => "Ukraine",
					"uk-en" => "United Kingdom",
					"vn-en" => "Vietnam (en)"
				]
			]
		];
		
		switch($pagetype){
			
			case "web":
				$base["country"]["option"] =
					array_merge(["any" => "All Regions"], $base["country"]["option"]);
				
				return array_merge($base,
					[
						"nsfw" => [
							"display" => "NSFW",
							"option" => [
								"yes" => "Yes",
								"maybe" => "Maybe",
								"no" => "No"
							]
						],
						"newer" => [
							"display" => "Newer than",
							"option" => "_DATE"
						],
						"older" => [
							"display" => "Older than",
							"option" => "_DATE"
						],
						"extendedsearch" => [
							// undefined display
							"option" => [
								"yes" => "Yes",
								"no" => "No",
							]
						]
					]
				);
				break;
			
			case "images":
				return array_merge($base,
					[
						"nsfw" => [
							"display" => "NSFW",
							"option" => [
								"yes" => "Yes",
								"no" => "No"
							]
						],
						"date" => [
							"display" => "Time posted",
							"option" => [
								"any" => "Any time",
								"Day" => "Past day",
								"Week" => "Past week",
								"Month" => "Past month"
							]
						],
						"size" => [
							"display" => "Size",
							"option" => [
								"any" => "Any size",
								"Small" => "Small",
								"Medium" => "Medium",
								"Large" => "Large",
								"Wallpaper" => "Wallpaper"
							]
						],
						"color" => [
							"display" => "Colors",
							"option" => [
								"any" => "All colors",
								"Monochrome" => "Black and white",
								"Red" => "Red",
								"Orange" => "Orange",
								"Yellow" => "Yellow",
								"Green" => "Green",
								"Blue" => "Blue",
								"Purple" => "Purple",
								"Pink" => "Pink",
								"Brown" => "Brown",
								"Black" => "Black",
								"Gray" => "Gray",
								"Teal" => "Teal",
								"White" => "White"
							]
						],
						"type" => [
							"display" => "Type",
							"option" => [
								"any" => "All types",
								"photo" => "Photograph",
								"clipart" => "Clipart",
								"gif" => "Animated GIF",
								"transparent" => "Transparent"
							]
						],
						"layout" => [
							"display" => "Layout",
							"option" => [
								"any" => "All layouts",
								"Square" => "Square",
								"Tall" => "Tall",
								"Wide" => "Wide"
							]
						],
						"license" => [
							"display" => "License",
							"option" => [
								"any" => "All licenses",
								"Any" => "All Creative Commons",
								"Public" => "Public domain",
								"Share" => "Free to Share and Use",
								"ShareCommercially" => "Free to Share and Use Commercially",
								"Modify" => "Free to Modify, Share, and Use",
								"ModifyCommercially" => "Free to Modify, Share, and Use Commercially"
							]
						]
					]
				);
				break;
			
			case "videos":
				return array_merge($base,
					[
						"nsfw" => [
							"display" => "NSFW",
							"option" => [
								"yes" => "Yes",
								"maybe" => "Maybe",
								"no" => "No"
							]
						],
						"date" => [
							"display" => "Time fetched",
							"option" => [
								"any" => "Any time",
								"d" => "Past day",
								"w" => "Past week",
								"m" => "Past month"
							]
						],
						"resolution" => [ //videoDefinition
							"display" => "Resolution",
							"option" => [
								"any" => "Any resolution",
								"high" => "High definition",
								"standard" => "Standard definition"
							]
						],
						"duration" => [ // videoDuration
							"display" => "Duration",
							"option" => [
								"any" => "Any duration",
								"short" => "Short (>5min)",
								"medium" => "Medium (5-20min)",
								"long" => "Long (<20min)"
							]
						],
						"license" => [
							"display" => "License",
							"option" => [
								"any" => "Any license",
								"creativeCommon" => "Creative Commons",
								"youtube" => "YouTube Standard"
							]
						]
					]
				);
				break;
				
			case "news":
				return array_merge($base,
					[
						"nsfw" => [
							"display" => "NSFW",
							"option" => [
								"yes" => "Yes",
								"maybe" => "Maybe",
								"no" => "No"
							]
						],
						"date" => [
							"display" => "Time posted",
							"option" => [
								"any" => "Any time",
								"d" => "Past day",
								"w" => "Past week",
								"m" => "Past month"
							]
						]
					]
				);
				break;
		}
	}
	
	public function web($get){
		
		if($get["npt"]){
			
			[$raw_data, $proxy] = $this->backend->get($get["npt"], "web");
			
			$raw_data = explode(",", $raw_data, 2);
			
			if($raw_data[0] == "0"){
				
				return $this->web_html($get, [$raw_data[1], $proxy]);
			}
			
			return $this->web_full($get, [$raw_data[1], $proxy]);
		}else{
			
			// we have $get["s"]
			if(
				strpos($get["s"], "\"") !== false || // contains quotes
				strpos($get["s"], ":") !== false // contains potential site: operator or whatever the fuck
			){
				
				return $this->web_html($get);
			}
			
			// no quotes sent, do full web search
			return $this->web_full($get);
		}
	}
	
	public function web_html($get, $npt = null){
		
		$out = [
			"status" => "ok",
			"spelling" => [
				"type" => "no_correction",
				"using" => null,
				"correction" => null
			],
			"npt" => null,
			"answer" => [],
			"web" => [],
			"image" => [],
			"video" => [],
			"news" => [],
			"related" => []
		];
		
		if($npt !== null){
			
			[$get_filters, $proxy] = $npt;
			
			$get_filters = json_decode($get_filters, true);
		}else{
			
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			// generate filters
			$get_filters = [
				"q" => $get["s"]
			];
			
			if($get["country"] == "any"){
				
				$get_filters["kl"] = "wt-wt";
			}else{
				
				$get_filters["kl"] = $get["country"];
			}
			
			switch($get["nsfw"]){
				
				case "yes": $get_filters["kp"] = "-2"; break;
				case "maybe": $get_filters["kp"] = "-1"; break;
				case "no": $get_filters["kp"] = "1"; break;
			}
			
			$df = true;
			
			if($get["newer"] === false){
				
				if($get["older"] !== false){
					
					$start = 36000;
					$end = $get["older"];
				}else{
					
					$df = false;
				}
			}else{
				
				$start = $get["newer"];
				
				if($get["older"] !== false){
					
					$end = $get["older"];
				}else{
					
					$end = time();
				}
			}
			
			if($df === true){
				$get_filters["df"] = date("Y-m-d", $start) . ".." . date("Y-m-d", $end);
			}
		}
		
		//
		// Get HTML
		//
		try{
			$html = $this->get(
				$proxy,
				"https://html.duckduckgo.com/html/",
				$get_filters
			);
		}catch(Exception $e){
			
			throw new Exception("Failed to fetch search page");
		}
		
		//$html = file_get_contents("scraper/ddg.html");
		
		$this->fuckhtml->load($html);
		
		//
		// Get next page token
		//
		$forms =
			$this->fuckhtml
			->getElementsByTagName(
				"form"
			);
		
		foreach(array_reverse($forms) as $form){
			
			$this->fuckhtml->load($form);
			
			$input_probe =
				$this->fuckhtml
				->getElementsByClassName(
					"btn--alt",
					"input"
				);
			
			if(count($input_probe) !== 0){
				
				// found next page!
				$inputs =
					$this->fuckhtml
					->getElementsByAttributeValue(
						"type",
						"hidden",
						"input"
					);
				
				$query = [];
				
				foreach($inputs as $q){
					
					$query[
						$this->fuckhtml
						->getTextContent(
							$q["attributes"]["name"]
						)
					] =
						$this->fuckhtml
						->getTextContent(
							$q["attributes"]["value"]
						);
				}
				
				$out["npt"] =
					$this->backend->store(
						"0," . json_encode($query),
						"web",
						$proxy
					);
				break;
			}
		}
		
		// reset
		$this->fuckhtml->load($html);
		
		//
		// parse wikipedia answer
		//
		$wiki_wrapper =
			$this->fuckhtml
			->getElementsByClassName(
				"zci-wrapper",
				"div"
			);
		
		if(count($wiki_wrapper) !== 0){
			
			$this->fuckhtml->load($wiki_wrapper[0]);
			
			$a =
				$this->fuckhtml
				->getElementsByTagName(
					"a"
				);
			
			if(count($a) !== 0){
				
				$link =
					$this->unshiturl(
						$this->fuckhtml
						->getTextContent(
							$a[0]["attributes"]["href"]
						)
					);
			}else{
				
				$link = null;
			}
			
			$title =
				$this->fuckhtml
				->getElementsByTagName(
					"h1"
				);
			
			if(count($title) !== 0){
				
				$title =
					$this->fuckhtml
					->getTextContent(
						$title[0]
					);
			}else{
				
				$title = null;
			}
			
			$description =
				$this->fuckhtml
				->getElementById(
					"zero_click_abstract",
					"div"
				);
			
			if($description !== false){
				
				$this->fuckhtml->load($description);
				
				$thumb =
					$this->fuckhtml
					->getElementsByTagName(
						"img"
					);
				
				if(count($thumb) !== 0){
					
					$thumb =
						$this->fuckhtml
						->getTextContent(
							$thumb[0]["attributes"]["src"]
						);
				}else{
					
					$thumb = null;
				}
				
				$as =
					$this->fuckhtml
					->getElementsByTagName(
						"a"
					);
				
				foreach($as as $a){
					
					$description["innerHTML"] =
						str_replace(
							$a["outerHTML"],
							"",
							$description["innerHTML"]
						);
				}
				
				$description =
					$this->fuckhtml
					->getTextContent(
						$description
					);
				
				$out["answer"][] = [
					"title" => $title,
					"description" => [
						[
							"type" => "text",
							"value" => $description
						]
					],
					"url" => $link,
					"thumb" => $thumb,
					"table" => [],
					"sublink" => []
				];
			}
			
			// reset
			$this->fuckhtml->load($html);
		}
		
		//
		// Get results
		//
		$results =
			$this->fuckhtml
			->getElementsByClassName(
				"result",
				"div"
			);
		
		foreach($results as $result){
			
			$this->fuckhtml->load($result);
			
			if(stripos($result["attributes"]["class"], "result--ad") !== false){
				
				// found an ad
				continue;
			}
			
			$title =
				$this->fuckhtml
				->getElementsByTagName(
					"h2"
				);
			
			if(count($title) === 0){
				
				// should not happen
				continue;
			}
			
			$title =
				$this->fuckhtml
				->getTextContent(
					$title[0]
				);
			
			$description_obj =
				$this->fuckhtml
				->getElementsByClassName(
					"result__snippet",
					"a"
				);
			
			if(count($description_obj) === 0){
				
				$description = null;
			}else{
				
				$description =
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$description_obj[0]
						)
					);
			}
			
			$url =
				$this->fuckhtml
				->getTextContent(
					$description_obj[0]["attributes"]["href"]
				);
			
			$out["web"][] = [
				"title" => $this->titledots($title),
				"description" => $description,
				"url" => $this->unshiturl($url),
				"date" => null,
				"type" => "web",
				"thumb" => [
					"ratio" => null,
					"url" => null
				],
				"sublink" => [],
				"table" => []
			];
		}
		
		return $out;
	}
	
	public function web_full($get, $npt = null){
		
		$out = [
			"status" => "ok",
			"spelling" => [
				"type" => "no_correction",
				"using" => null,
				"correction" => null
			],
			"npt" => null,
			"answer" => [],
			"web" => [],
			"image" => [],
			"video" => [],
			"news" => [],
			"related" => []
		];
		
		if($npt !== null){
			
			[$js_link, $proxy] = $npt;
			$js_link = "https://links.duckduckgo.com" . $js_link;
			
			$html = "";
			$get["extendedsearch"] = "no";
			
		}else{
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			// generate filters
			$get_filters = [
				"q" => $get["s"]
			];
			
			if($get["country"] == "any"){
				
				$get_filters["kl"] = "wt-wt";
			}else{
				
				$get_filters["kl"] = $get["country"];
			}
			
			switch($get["nsfw"]){
				
				case "yes": $get_filters["kp"] = "-2"; break;
				case "maybe": $get_filters["kp"] = "-1"; break;
				case "no": $get_filters["kp"] = "1"; break;
			}
			
			$df = true;
			
			if($get["newer"] === false){
				
				if($get["older"] !== false){
					
					$start = 36000;
					$end = $get["older"];
				}else{
					
					$df = false;
				}
			}else{
				
				$start = $get["newer"];
				
				if($get["older"] !== false){
					
					$end = $get["older"];
				}else{
					
					$end = time();
				}
			}
			
			if($df === true){
				$get_filters["df"] = date("Y-m-d", $start) . ".." . date("Y-m-d", $end);
			}
			
			//
			// Get HTML
			//
			try{
				$html = $this->get(
					$proxy,
					"https://duckduckgo.com/",
					$get_filters
				);
			}catch(Exception $e){
				
				throw new Exception("Failed to fetch search page");
			}
			
			$this->fuckhtml->load($html);
			
			$script =
				$this->fuckhtml
				->getElementById(
					"deep_preload_link",
					"link"
				);
			
			if(
				$script === null ||
				!isset($script["attributes"]["href"])
			){
				
				throw new Exception("Failed to grep d.js");
			}
			
			$js_link =
				$this->fuckhtml
				->getTextContent(
					$script["attributes"]["href"]
				);
		}
		
		//
		// Get d.js
		//
		try{
			$js = $this->get(
				$proxy,
				$js_link,
				[],
				ddg::req_xhr
			);
			
		}catch(Exception $e){
			
			throw new Exception("Failed to fetch d.js");
		}
		
		//$js = file_get_contents("scraper/fuck.js");
		//echo htmlspecialchars($js);
		
		$js_tmp =
			preg_split(
				'/DDG\.pageLayout\.load\(\s*\'d\'\s*,\s*/',
				$js,
				2
			);
		
		if(count($js_tmp) <= 1){
			
			//
			// Detect javascript challenge
			//
			if(
				preg_match(
					'/DDG\.deep\.initialize\(\'([^\']+)\'\ *\+ *jsa/i',
					$js,
					$challenge_url
				)
			){
				
				throw new Exception("DuckDuckGo returned a JSA challenge");
				
				// get JSA initial token
				if(
					!preg_match(
						'/let jsa *= *([0-9]+)/',
						$js,
						$jsa
					)
				){
					
					$jsa = 0;
				}else{
					
					$jsa = (int)$jsa[1];
				}
				
				// get function bodies
				preg_match_all(
					'/let *([A-Za-z0-9]+) *= *function\(.*\) *{(.*)};/sU',
					$js,
					$functions
				);
				
				$parsed_functions = [];
				
				for($i=0; $i<count($functions[0]); $i++){
					
					$functions[2][$i] = trim($functions[2][$i]);
					
					if(
						preg_match(
							'/return num *\* *([0-9]+)/i',
							$functions[2][$i],
							$num
						)
					){
						
						$parsed_functions[$functions[1][$i]] = [
							"type" => "multiplication",
							"num" => (int)$num[1]
						];
						continue;
					}
					
					if(
						preg_match(
							'/innerHTML *= *`([^`]+)`/i',
							$functions[2][$i],
							$challenge
						)
					){
						
						$challenge[1] =
							preg_replace(
								'/<\/(br)>/',
								'<$1>',
								$challenge[1]
							);
						
						$parsed_functions[$functions[1][$i]] = [
							"type" => "challenge",
							"text" => $challenge[1]
						];
					}
				}
				
				// get function call order
				preg_match_all(
					'/jsa *= *([A-Za-z0-9]+)\(jsa\)/i',
					$js,
					$call_order
				);
				
				foreach($call_order[1] as $order){
					
					if(!isset($parsed_functions[$order])){
						
						throw new Exception("JS challenge solve failure: DuckDuckGo called an unknown function");
					}
					
					if($parsed_functions[$order]["type"] == "multiplication"){
						
						$jsa = $jsa * $parsed_functions[$order]["num"];
						continue;
					}
					
					if($parsed_functions[$order]["type"] == "challenge"){
						
						// @TODO get parsed length
						//$parsed_functions[$order]["text"]
						
						$jsa = $jsa + strlen($parsed_functions[$order]["text"]);
					}
				}
				
				try{
					$js = $this->get(
						$proxy,
						"https://links.duckduckgo.com" . $challenge_url[1] . $jsa,
						[],
						ddg::req_xhr
					);
				}catch(Exception $error){
					
					throw new Exception("Failed to get challenged d.js");
				}
			}
			
			//
			// Detect JavaScript anomaly failure thingy
			//
			if(
				preg_match(
					'/DDG.deep.anomalyDetectionBlock\({/',
					$js
				)
			){
				
				throw new Exception("DuckDuckGo detected an anomaly in the Javascript challenge response");
			}
			
			throw new Exception("Failed to grep pageLayout(d)");
		}
		
		$json =
			json_decode(
				$this->fuckhtml
				->extract_json(
					$js_tmp[1]
				),
				true
			);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		//
		// Get search results + NPT token
		//
		foreach($json as $item){
			
			if(isset($item["c"])){
				
				if(
					!isset($item["s"]) &&
					isset($item["t"]) &&
					(
						$item["t"] == "DEEP_ERROR_NO_RESULTS" ||
						$item["t"] == "DEEP_SIMPLE_NO_RESULTS"
					)
				){
					
					return $out;
				}
				
				$table = [];
				
				// get youtube video information
				if(isset($item["video"]["thumbnail_url_template"])){
					
					$thumb =
						[
							"ratio" => "16:9",
							"url" => $this->bingimg($item["video"]["thumbnail_url_template"])
						];
				}else{
					
					$thumb =
						[
							"ratio" => null,
							"url" => null
						];
				}
				
				// get table items
				if(isset($item["rf"])){
					
					foreach($item["rf"] as $hint){
						
						if(
							!isset($hint["label"]["text"]) ||
							!isset($hint["items"][0]["text"])
						){
							
							continue;
						}
						
						$text = [];
						
						foreach($hint["items"] as $text_part){
							
							$text[] = $text_part["text"];
						}
						
						$text = implode(", ", $text);
						
						if(is_numeric($text)){
							
							$text = number_format((string)$text);
						}
						
						$table[$hint["label"]["text"]] = $text;
					}
				}
				
				// get ratings
				if(isset($item["ar"])){
					
					foreach($item["ar"] as $rating){
						
						if(
							isset($rating["aggregateRating"]["bestRating"]) &&
							isset($rating["aggregateRating"]["ratingValue"])
						){
							
							$text = $rating["aggregateRating"]["ratingValue"] . "/" . $rating["aggregateRating"]["bestRating"];
							
							if(isset($rating["aggregateRating"]["reviewCount"])){
								
								$text .= " (" . number_format($rating["aggregateRating"]["reviewCount"]) . " votes)";
							}
							
							$table["Rating"] = $text;
						}
					}
				}
				
				// get sublinks
				$sublinks = [];
				
				if(isset($item["l"])){
					
					foreach($item["l"] as $sublink){
						
						$sublinks[] = [
							"title" => $this->titledots($sublink["text"]),
							"description" => $this->titledots($sublink["snippet"]),
							"url" => $sublink["targetUrl"],
							"date" => null
						];
					}
				}
				
				$title =
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$item["t"]
						)
					);
				
				if(
					$title == "EOF" &&
					strpos(
						$item["c"],
						"google"
					)
				){
					
					continue;
				}
				
				// parse search result
				$out["web"][] = [
					"title" =>
						$this->titledots(
							$this->fuckhtml
							->getTextContent(
								$item["t"]
							)
						),
					"description" =>
						isset($item["a"]) ?
						$this->titledots(
							$this->fuckhtml
							->getTextContent(
								$item["a"]
							)
						) : null,
					"url" => $this->unshiturl($item["c"]),
					"date" =>
						isset($item["e"]) ?
						strtotime($item["e"]) : null,
					"type" => "web",
					"thumb" => $thumb,
					"sublink" => $sublinks,
					"table" => $table
				];
				continue;
			}
			
			if(isset($item["n"])){
				
				// get NPT
				$out["npt"] =
					$this->backend->store(
						"1," . $item["n"],
						"web",
						$proxy
					);
				continue;
			}
		}
		
		//
		// Get spelling
		//
		$js_tmp =
			preg_split(
				'/DDG\.page\.showMessage\(\s*\'spelling\'\s*,\s*/',
				$js,
				2
			);
		
		if(count($js_tmp) > 1){
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$js_tmp[1]
					),
					true
				);
			
			if($json !== null){
				
				// parse spelling
				// qc=2: including
				
				switch((int)$json["qc"]){
					
					case 2:
						$type = "including";
						break;
					
					default:
						$type = "not_many";
						break;
				}
				
				$out["spelling"] = [
					"type" => $type,
					"using" =>
						$this->fuckhtml
						->getTextContent(
							$json["suggestion"]
						),
					"correction" => html_entity_decode($json["recourseText"])
				];
			}
		}
		
		//
		// Get images
		//
		$js_tmp =
			preg_split(
				'/DDG\.duckbar\.load\(\s*\'images\'\s*,\s*/',
				$js,
				2
			);
		
		if(count($js_tmp) > 1){
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$js_tmp[1]
					),
					true
				);
			
			if($json !== null){
				
				foreach($json["results"] as $image){
					
					$ratio = $this->bingratio((int)$image["width"], (int)$image["height"]);
					
					$out["image"][] = [
						"title" => $image["title"],
						"source" =>	[
							[
								"url" => $image["image"],
								"width" => (int)$image["width"],
								"height" => (int)$image["height"]
							],
							[
								"url" => $this->bingimg($image["thumbnail"]),
								"width" => $ratio[0],
								"height" => $ratio[1]
							]
						],
						"url" => $this->unshiturl($image["url"])
					];
				}
			}
		}
		
		//
		// Get videos
		//
		$js_tmp =
			preg_split(
				'/DDG\.duckbar\.load\(\s*\'videos\'\s*,\s*/',
				$js,
				2
			);
		
		if(count($js_tmp) > 1){
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$js_tmp[1]
					),
					true
				);
			
			if($json !== null){
				
				foreach($json["results"] as $video){
					
					$thumb = [
						"ratio" => null,
						"url" => null
					];
					
					foreach(["large", "medium", "small"] as $contender){
						
						if(isset($video["images"][$contender])){
							
							$thumb = [
								"ratio" => "16:9",
								"url" => $this->bingimg($video["images"][$contender])
							];
							break;
						}
					}
					
					$out["video"][] = [
						"title" => $this->titledots($video["title"]),
						"description" =>
							$video["description"] != "" ?
							$this->titledots($video["description"]) : null,
						"date" => 
							isset($video["published"]) ?
							strtotime($video["published"]) : null,
						"duration" =>
							$video["duration"] != "" ?
							$this->hms2int($video["duration"]) : null,
						"views" =>
							isset($video["statistics"]["viewCount"]) ?
							(int)$video["statistics"]["viewCount"] : null,
						"thumb" => $thumb,
						"url" => $this->unshiturl($video["content"])
					];
				}
			}
		}
		
		//
		// Get news
		//
		$js_tmp =
			preg_split(
				'/DDG\.duckbar\.load\(\s*\'news\'\s*,\s*/',
				$js,
				2
			);
		
		if(count($js_tmp) > 1){
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$js_tmp[1]
					),
					true
				);
			
			if($json !== null){
				
				foreach($json["results"] as $news){
					
					if(isset($news["image"])){
						
						$thumb = [
							"ratio" => "16:9",
							"url" => $news["image"]
						];
					}else{
						
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}
					
					$out["news"][] = [
						"title" => $news["title"],
						"description" =>
							$this->fuckhtml
							->getTextContent(
								$news["excerpt"]
							),
						"date" => (int)$news["date"],
						"thumb" => $thumb,
						"url" => $news["url"]
					];
				}
			}
		}
		
		//
		// Get related searches
		//
		$js_tmp =
			preg_split(
				'/DDG\.duckbar\.loadModule\(\s*\'related_searches\'\s*,\s*/',
				$js,
				2
			);
		
		if(count($js_tmp) > 1){
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$js_tmp[1]
					),
					true
				);
			
			if($json !== null){
				
				foreach($json["results"] as $related){
					
					$out["related"][] = $related["text"];
				}
			}
		}
		
		//
		// Get instant answers
		//
		$js_tmp =
			preg_split(
				'/DDG\.duckbar\.add\(\s*/',
				$html . $js,
				2
			);
		
		if(count($js_tmp) > 1){
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$js_tmp[1]
					),
					true
				);
			
			if($json !== null){
				
				$json = $json["data"];
				$table = [];
				$sublinks = [];
				$description = [];
				
				// get official website
				if(
					isset($json["OfficialWebsite"]) &&
					$json["OfficialWebsite"] !== null
				){
					
					$sublinks["Website"] = $json["OfficialWebsite"];
				}
				
				// get sublinks & table elements
				if(isset($json["Infobox"]["content"])){
					foreach($json["Infobox"]["content"] as $info){
						
						if($info["data_type"] == "string"){
							
							// add table element
							$table[$info["label"]] = $info["value"];
							continue;
						}
						
						if($info["data_type"] == "wd_description"){
							
							$description[] = [
								"type" => "quote",
								"value" => $info["value"]
							];
							continue;
						}
						
						// add sublink
						switch($info["data_type"]){
							
							case "official_site":
							case "official_website":
								$type = "Website";
								break;
							
							case "wikipedia": $type = "Wikipedia"; break;
							case "itunes": $type = "iTunes"; break;
							case "amazon": $type = "Amazon"; break;
							
							case "imdb_title_id":
							case "imdb_id":
							case "imdb_name_id":
								$type = "IMDb";
								$delim = substr($info["value"], 0, 2);
								
								if($delim == "nm"){
									
									$prefix = "https://www.imdb.com/name/";
								}elseif($delim == "tt"){
									
									$prefix = "https://www.imdb.com/title/";
								}elseif($delim == "co"){
									
									$prefix = "https://www.imdb.com/search/title/?companies=";
								}else{
									
									$prefix = "https://www.imdb.com/title/";
								}
								break;

							case "imdb_name_id": $prefix = "https://www.imdb.com/name/"; $type = "IMDb"; break;
							case "twitter_profile": $prefix = "https://twitter.com/"; $type = "Twitter"; break;
							case "instagram_profile": $prefix = "https://instagram.com/"; $type = "Instagram"; break;
							case "facebook_profile": $prefix = "https://facebook.com/"; $type = "Facebook"; break;
							case "spotify_artist_id": $prefix = "https://open.spotify.com/artist/"; $type = "Spotify"; break;
							case "itunes_artist_id": $prefix = "https://music.apple.com/us/artist/"; $type = "iTunes"; break;
							case "rotten_tomatoes": $prefix = "https://rottentomatoes.com/"; $type = "Rotten Tomatoes"; break;
							case "youtube_channel": $prefix = "https://youtube.com/channel/"; $type = "YouTube"; break;
							case "soundcloud_id": $prefix = "https://soundcloud.com/"; $type = "SoundCloud"; break;
							
							default:							
								$prefix = null;
								$type = false;
						}
						
						if($type !== false){
							
							if($prefix === null){
								
								$sublinks[$type] = $info["value"];
							}else{
								
								$sublinks[$type] = $prefix . $info["value"];
							}
						}
					}
				}
				
				if(isset($json["Abstract"])){
					
					$description = $this->parse_rich_text($json["Abstract"]);
				}
				
				if(
					!isset($json["Image"]) ||
					$json["Image"] == "" ||
					$json["Image"] === null ||
					$json["Image"] == "https://duckduckgo.com/i/"
				){
					
					$image = null;
				}else{
					
					if(
						preg_match(
							'/^https?:\/\//',
							$json["Image"]
						)
					){
						
						$image = $json["Image"];
					}else{
						
						$image = "https://duckduckgo.com" . $json["Image"];
					}
				}
				
				$out["answer"][] = [
					"title" => $json["Heading"],
					"description" => $description,
					"url" => $json["AbstractURL"],
					"thumb" => $image,
					"table" => $table,
					"sublink" => $sublinks
				];
			}
		}
		
		if($get["extendedsearch"] == "no"){
			
			return $out;
		}
		
		//
		// Parse additional data endpoints
		//
		//nrj('/js/spice/dictionary/definition/create', null, null, null, null, 'dictionary_definition');
		
		preg_match_all(
			'/nrj\(\s*\'([^\']+)\'/',
			$js,
			$nrj
		);
		
		if(isset($nrj[1])){
			
			foreach($nrj[1] as $potential_endpoint){
				
				//
				// Probe for wordnik definition
				//
				preg_match(
					'/\/js\/spice\/dictionary\/definition\/([^\/]+)/',
					$potential_endpoint,
					$word
				);
				
				if(isset($word[1])){
					
					$word = $word[1];
					
					// found wordnik definition & word
					try{
						$nik =
							$this->get(
								$proxy,
								"https://duckduckgo.com/js/spice/dictionary/definition/" . $word,
								[],
								ddg::req_xhr
							);
						
					}catch(Exception $e){
						
						// fail gracefully
						return $out;
					}
					
					// remove javascript
					$js_tmp =
						preg_split(
							'/ddg_spice_dictionary_definition\(\s*/',
							$nik,
							2
						);
					
					if(count($js_tmp) > 1){
						
						$nik =
							json_decode(
								$this->fuckhtml
								->extract_json(
									$js_tmp[1]
								),
								true
							);
					}
					
					if($nik === null){
						
						return $out;
					}
					
					$answer_cat = [];
					$answer = [];
					
					foreach($nik as $snippet){
						
						if(!isset($snippet["partOfSpeech"])){ continue; }
						
						$push = [];
						
						// add text snippet
						if(isset($snippet["text"])){
							
							$push[] = [
								"type" => "text",
								"value" =>
									$this->fuckhtml
									->getTextContent(
										$snippet["text"]
									)
							];
						}
						
						// add example uses
						if(isset($snippet["exampleUses"])){
							
							foreach($snippet["exampleUses"] as $example){
								
								$push[] = [
									"type" => "quote",
									"value" => "\"" .
										$this->fuckhtml
										->getTextContent(
											$example["text"]
										) . "\""
								];
							}
						}
						
						// add citations
						if(isset($snippet["citations"])){
							
							foreach($snippet["citations"] as $citation){
								
								if(!isset($citation["cite"])){ continue; }
								
								$text =
									$this->fuckhtml
									->getTextContent(
										$citation["cite"]
									);
								
								if(isset($citation["source"])){
									
									$text .=
										" - " .
										$this->fuckhtml
										->getTextContent(
											$citation["source"]
										);
								}
								
								$push[] = [
									"type" => "quote",
									"value" => $text
								];
							}
						}
						
						// add related words
						if(isset($snippet["relatedWords"])){
							
							$relations = [];
							
							foreach($snippet["relatedWords"] as $related){
								
								$words = [];
								foreach($related["words"] as $wrd){
									
									$words[] =
										$this->fuckhtml
										->getTextContent(
											$wrd
										);
								}
								
								if(
									count($words) !== 0 &&
									isset($related["relationshipType"])
								){
									
									$relations[ucfirst($related["relationshipType"]) . "s"] =
										implode(", ", $words);
								}
							}
							
							foreach($relations as $relation_title => $relation_content){
								
								$push[] = [
									"type" => "quote",
									"value" => $relation_title . ": " . $relation_content
								];
							}
						}
						
						
						if(count($push) !== 0){
							
							// push data to answer_cat
							if(!isset($answer_cat[$snippet["partOfSpeech"]])){
								
								$answer_cat[$snippet["partOfSpeech"]] = [];
							}
							
							$answer_cat[$snippet["partOfSpeech"]] =
								array_merge(
									$answer_cat[$snippet["partOfSpeech"]],
									$push
								);
						}
					}
					
					foreach($answer_cat as $answer_title => $answer_content){
						
						$i = 0;
						$answer[] = [
							"type" => "title",
							"value" => $answer_title
						];
						
						$old_type = $answer[count($answer) - 1]["type"];
						
						foreach($answer_content as $ans){
							
							if(
								$ans["type"] == "text" &&
								$old_type == "text"
							){
								
								$i++;
								$c = count($answer) - 1;
								
								// append text to existing textfield
								$answer[$c] = [
									"type" => "text",
									"value" => $answer[$c]["value"] . "\n" . $i . ". " . $ans["value"]
								];
								
							}elseif($ans["type"] == "text"){
								
								$i++;
								$answer[] = [
									"type" => "text",
									"value" => $i . ". " . $ans["value"]
								];
							}else{
								
								// append normally
								$answer[] = $ans;
							}
							
							$old_type = $ans["type"];
						}
					}
					
					// yeah.. sometimes duckduckgo doesnt give us a definition back
					if(count($answer) !== 0){
						
						$out["answer"][] = [
							"title" => ucfirst($word),
							"description" => $answer,
							"url" => "https://www.wordnik.com/words/" . $word,
							"thumb" => null,
							"table" => [],
							"sublink" => []
						];
					}
				}
				
				//
				// Parse stackoverflow answer
				//
				if(
					preg_match(
						'/^\/a\.js.*src_id=stack_overflow/',
						$potential_endpoint
					)
				){
					
					// found stackoverflow answer
					try{
						$json =
							$this->get(
								$proxy,
								"https://duckduckgo.com" . $potential_endpoint,
								[],
								ddg::req_xhr
							);
						
					}catch(Exception $e){
						
						// fail gracefully
						return $out;
					}
					
					$json = explode("DDG.duckbar.add_array(", $json, 2);
					
					if(count($json) === 2){
						
						$json =
							json_decode(
								$this->fuckhtml
								->extract_json(
									$json[1]
								),
								true
							);
						
						if(
							$json !== null &&
							isset($json[0]["data"])
						){
							
							$json = $json[0]["data"];
							
							foreach($json as $answer){
								
								if(isset($answer["Heading"])){
									
									$title = $answer["Heading"];
								}elseif(isset($answer["title"])){
									
									$title = $answer["title"];
								}else{
									
									$title = null;
								}
								
								if(
									$title !== null &&
									isset($answer["Abstract"])
								){
									
									$description = $this->parse_rich_text($answer["Abstract"]);
									
									$out["answer"][] = [
										"title" => $title,
										"description" => $description,
										"url" => $answer["AbstractURL"],
										"thumb" => null,
										"table" => [],
										"sublink" => []
									];
								}
							}
						}
					}
				}
			}
		}
		
		return $out;
	}
	
	public function image($get){
		
		if($get["npt"]){
			
			[$js_link, $proxy] = $this->backend->get($get["npt"], "images");
			
		}else{
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$filters = [];
			
			if($get["date"] != "any"){ $filters[] = "time:{$get["date"]}"; }
			if($get["size"] != "any"){ $filters[] = "size:{$get["size"]}"; }
			if($get["color"] != "any"){ $filters[] = "color:{$get["color"]}"; }
			if($get["type"] != "any"){ $filters[] = "type:{$get["type"]}"; }
			if($get["layout"] != "any"){ $filters[] = "layout:{$get["layout"]}"; }
			if($get["license"] != "any"){ $filters[] = "license:{$get["license"]}"; }
			
			$filters = implode(",", $filters);
			
			$get_filters = [
				"q" => $get["s"],
				"iax" => "images",
				"ia" => "images"
			];
			
			if($filters != ""){
				
				$get_filters["iaf"] = $filters;
			}
			
			$nsfw = $get["nsfw"] == "yes" ? "-1" : "1";
			$get_filters["kp"] = $nsfw;
			
			try{
				
				$html = $this->get(
					$proxy,
					"https://duckduckgo.com",
					$get_filters,
					ddg::req_web
				);
			}catch(Exception $err){
				
				throw new Exception("Failed to fetch search page");
			}
			
			preg_match(
				'/vqd="([0-9-]+)"/',
				$html,
				$vqd
			);
			
			if(!isset($vqd[1])){
				
				throw new Exception("Failed to grep VQD token");
			}
			
			$js_link =
				"i.js?" .
				http_build_query([
					"l" => $get["country"],
					"o" => "json",
					"q" => $get["s"],
					"vqd" => $vqd[1],
					"f" => $filters,
					"p" => $nsfw
				]);
		}
		
		try{
			
			$json =
				$this->get(
					$proxy,
					"https://duckduckgo.com/" . $js_link,
					[],
					ddg::req_xhr
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to get i.js");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		if(!isset($json["results"])){
			
			return $out;
		}
		
		// get npt
		if(
			isset($json["next"]) &&
			$json["next"] !== null
		){
			
			$vqd = null;
			
			if(isset($vqd[1])){
				
				$vqd = $vqd[1];
			}else{
				
				$vqd = array_values($json["vqd"]);
				
				if(count($vqd) > 0){
					
					$vqd = $vqd[0];
				}
			}
			
			if($vqd !== null){
				
				$out["npt"] =
					$this->backend->store(
						$json["next"] . "&vqd=" . $vqd,
						"images",
						$proxy
					);
			}
		}
		
		// get images
		foreach($json["results"] as $image){
			
			$ratio =
				$this->bingratio(
					(int)$image["width"],
					(int)$image["height"]
				);
			
			$out["image"][] = [
				"title" => $this->titledots($image["title"]),
				"source" => [
					[
						"url" => $image["image"],
						"width" => (int)$image["width"],
						"height" => (int)$image["height"]
					],
					[
						"url" => $this->bingimg($image["thumbnail"]),
						"width" => $ratio[0],
						"height" => $ratio[1]
					]
				],
				"url" => $this->unshiturl($image["url"])
			];
		}
		
		return $out;
	}
	
	public function video($get){
		
		if($get["npt"]){
			
			[$js_link, $proxy] = $this->backend->get($get["npt"], "videos");
			
		}else{
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$get_filters = [
				"q" => $get["s"],
				"iax" => "videos",
				"ia" => "videos"
			];
			
			switch($get["nsfw"]){
				
				case "yes": $nsfw = "-2"; break;
				case "maybe": $nsfw = "-1"; break;
				case "no": $nsfw = "1"; break;
			}
			
			$filters = [];
			
			if($get["date"] != "any"){ $filters[] = "publishedAfter:{$date}"; }
			if($get["resolution"] != "any"){ $filters[] = "videoDefinition:{$resolution}"; }
			if($get["duration"] != "any"){ $filters[] = "videoDuration:{$duration}"; }
			if($get["license"] != "any"){ $filters[] = "videoLicense:{$license}"; }
			
			$filters = implode(",", $filters);
			
			if($filters != ""){
				
				$get_filters["iaf"] = $filters;
			}
			
			try{
				
				$html =
					$this->get(
						$proxy,
						"https://duckduckgo.com/",
						$get_filters,
						ddg::req_web
					);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			
			preg_match(
				'/vqd="([0-9-]+)"/',
				$html,
				$vqd
			);
			
			if(!isset($vqd[1])){
				
				throw new Exception("Failed to grep VQD token");
			}
			
			$js_link =
				"v.js?" .
				http_build_query([
					"l" => $get["country"],
					"o" => "json",
					"sr" => "1",
					"q" => $get["s"],
					"vqd" => $vqd[1],
					"f" => $filters,
					"p" => $nsfw
				]);
		}
		
		try{
			
			$json =
				$this->get(
					$proxy,
					"https://duckduckgo.com/" . $js_link,
					[],
					ddg::req_xhr
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"video" => [],
			"author" => [],
			"livestream" => [],
			"playlist" => [],
			"reel" => []
		];
		
		if(!isset($json["results"])){
			
			return $out;
		}
		
		// get NPT
		if(
			isset($json["next"]) &&
			$json["next"] !== null
		){
			
			$out["npt"] =
				$this->backend->store(
					$json["next"],
					"videos",
					$proxy
				);
		}
		
		foreach($json["results"] as $video){
			
			$thumb = [
				"ratio" => null,
				"url" => null
			];
			
			foreach(["large", "medium", "small"] as $contender){
				
				if(isset($video["images"][$contender])){
					
					$thumb = [
						"ratio" => "16:9",
						"url" => $this->bingimg($video["images"][$contender])
					];
					break;
				}
			}
			
			$out["video"][] = [
				"title" => $this->titledots($video["title"]),
				"description" => $this->titledots($video["description"]),
				"author" => [
					"name" =>
						(
							isset($video["uploader"]) &&
							$video["uploader"] != ""
						) ?
						$video["uploader"] : null,
					"url" => null,
					"avatar" => null
				],
				"date" =>
					(
						isset($video["published"]) &&
						$video["published"] != ""
					) ?
					strtotime($video["published"]) : null,
				"duration" =>
					(
						isset($video["duration"]) &&
						$video["duration"] != ""
					) ?
					$this->hms2int($video["duration"]) : null,
				"views" =>
					isset($video["statistics"]["viewCount"]) ?
					(int)$video["statistics"]["viewCount"] : null,
				"thumb" => $thumb,
				"url" => $this->unshiturl($video["content"])
			];
		}
		
		return $out;
	}
	
	public function news($get){
		
		if($get["npt"]){
			
			[$js_link, $proxy] = $this->backend->get($get["npt"], "news");
			
		}else{
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$get_filters = [
				"q" => $get["s"],
				"iar" => "news",
				"ia" => "news"
			];
			
			if($get["date"] != "any"){
				
				$date = $get["date"];
				$get_filters["df"] = $date;
			}else{
				
				$date = "";
			}
			
			switch($get["nsfw"]){
				
				case "yes": $get_filters["kp"] = "-2"; break;
				case "maybe": $get_filters["kp"] = "-1"; break;
				case "no": $get_filters["kp"] = "1"; break;
			}
			
			try{
				
				$html =
					$this->get(
						$proxy,
						"https://duckduckgo.com/",
						$get_filters,
						ddg::req_web
					);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			
			preg_match(
				'/vqd="([0-9-]+)"/',
				$html,
				$vqd
			);
			
			if(!isset($vqd[1])){
				
				throw new Exception("Failed to grep VQD token");
			}
			
			$js_link =
				"news.js?" .
				http_build_query([
					"l" => $get["country"],
					"o" => "json",
					"noamp" => "1",
					"m" => "30",
					"q" => $get["s"],
					"vqd" => $vqd[1],
					"p" => $get_filters["kp"],
					"df" => $date,
					"u" => "bing"
				]);
		}
		
		try{
			
			$json =
				$this->get(
					$proxy,
					"https://duckduckgo.com/" . $js_link,
					[],
					ddg::req_xhr
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"news" => []
		];
		
		if(!isset($json["results"])){
			
			return $out;
		}
		
		// get NPT
		if(
			isset($json["next"]) &&
			$json["next"] !== null
		){
			
			$out["npt"] =
				$this->backend->store(
					$json["next"],
					"news",
					$proxy
				);
		}
		
		foreach($json["results"] as $news){
			
			if(
				isset($news["image"]) &&
				$news["image"] != ""
			){
				
				$thumb = [
					"ratio" => "16:9",
					"url" => $news["image"]
				];
			}else{
				
				$thumb = [
					"ratio" => null,
					"url" => null
				];
			}
			
			$out["news"][] = [
				"title" => $news["title"],
				"author" =>
					(
						isset($news["source"]) &&
						$news["source"] != ""
					) ?
					$news["source"] : null,
				"description" =>
					(
						isset($news["excerpt"]) &&
						$news["excerpt"] != ""
					) ?
					$this->fuckhtml
					->getTextContent(
						$news["excerpt"]
					) : null,
				"date" =>
					isset($news["date"]) ?
					(int)$news["date"] : null,
				"thumb" => $thumb,
				"url" => $this->unshiturl($news["url"])
			];
		}
		
		return $out;
	}
	
	private function parse_rich_text($html){
		
		$description = [];
		
		// pre-process the html, remove useless elements
		$html =
			strip_tags(
				$html,
				[
					"h1", "h2", "h3", "h4", "h5", "h6", "h7",
					"pre", "code"
				]
			);
		
		$html =
			preg_replace(
				'/<(\/?)pre *[^>]*>\s*<\/?code *[^>]*>/i',
				'<$1pre>',
				$html
			);
		
		$this->fuckhtml->load($html);
		
		$tags =
			$this->fuckhtml
			->getElementsByTagName(
				"*"
			);
		
		if(count($tags) === 0){
			
			$description[] = [
				"type" => "text",
				"value" =>
					trim(
						$this->fuckhtml
						->getTextContent(
							$html,
							true,
							false
						)
					)
			];
		}else{
			
			$start = 0;
			$was_code_block = true;
			foreach($tags as $tag){
				
				$text =
					$this->fuckhtml
					->getTextContent(
						substr(
							$html,
							$start,
							$tag["startPos"] - $start
						),
						true,
						false
					);
				
				if($was_code_block){
					
					$text = ltrim($text);
					$was_code_block = false;
				}
				
				$description[] = [
					"type" => "text",
					"value" => $text
				];
				
				switch($tag["tagName"]){
					
					case "pre":
						$append = "code";
						$was_code_block = true;
						$c = count($description) - 1;
						$description[$c]["value"] =
							rtrim($description[$c]["value"]);
						break;
					
					case "code":
						$append = "inline_code";
						$c = count($description) - 1;
						$description[$c]["value"] =
							rtrim($description[$c]["value"]) . " ";
						break;
					
					case "h1":
					case "h2":
					case "h3":
					case "h4":
					case "h5":
					case "h6":
					case "h7":
						$append = "title";
						$c = count($description) - 1;
						$description[$c]["value"] =
							rtrim($description[$c]["value"]);
						break;
				}
				
				$description[] = [
					"type" => $append,
					"value" =>
						trim(
							$this->fuckhtml
							->getTextContent(
								$tag,
								true,
								false
							)
						)
				];
				
				$start = $tag["endPos"];
			}
			
			// shit out remainder
			$description[] = [
				"type" => "text",
				"value" =>
					trim(
						$this->fuckhtml
						->getTextContent(
							substr(
								$html,
								$start
							),
							true,
							false
						)
					)
			];
		}
		
		return $description;
	}
	
	private function titledots($title){
		
		$substr = substr($title, -3);
		
		if(
			$substr == "..." ||
			$substr == "…"
		){
						
			return trim(substr($title, 0, -3));
		}
		
		return trim($title);
	}
	
	private function hms2int($time){
		
		$parts = explode(":", $time, 3);
		$time = 0;
		
		if(count($parts) === 3){
			
			// hours
			$time = $time + ((int)$parts[0] * 3600);
			array_shift($parts);
		}
		
		if(count($parts) === 2){
			
			// minutes
			$time = $time + ((int)$parts[0] * 60);
			array_shift($parts);
		}
		
		// seconds
		$time = $time + (int)$parts[0];
		
		return $time;
	}

	
	private function unshiturl($url){
		
		// remove tracking redirect
		// yes, the privacy search engine has click-out tracking. great!
		$domain = parse_url($url, PHP_URL_HOST);
		
		if($domain == "duckduckgo.com"){
			
			$query = parse_url($url, PHP_URL_QUERY);
			parse_str($query, $query);
			
			if(isset($query["uddg"])){
				
				$url = $query["uddg"];
				$domain = parse_url($url, PHP_URL_HOST);
			}
		}
		
		// check for domains w/out first short subdomain (ex: www.)
		
		$subdomain = preg_replace(
			'/^[A-z0-9]{1,3}\./',
			"",
			$domain
		);
		
		switch($subdomain){
			case "ebay.com.au":
			case "ebay.at":
			case "ebay.ca":
			case "ebay.fr":
			case "ebay.de":
			case "ebay.com.hk":
			case "ebay.ie":
			case "ebay.it":
			case "ebay.com.my":
			case "ebay.nl":
			case "ebay.ph":
			case "ebay.pl":
			case "ebay.com.sg":
			case "ebay.es":
			case "ebay.ch":
			case "ebay.co.uk":
			case "cafr.ebay.ca":
			case "ebay.com":
			case "community.ebay.com":
			case "pages.ebay.com":
				
				// remove ebay tracking elements
				$old_params = parse_url($url, PHP_URL_QUERY);
				parse_str($old_params, $params);
				
				if(isset($params["mkevt"])){ unset($params["mkevt"]); }
				if(isset($params["mkcid"])){ unset($params["mkcid"]); }
				if(isset($params["mkrid"])){ unset($params["mkrid"]); }
				if(isset($params["campid"])){ unset($params["campid"]); }
				if(isset($params["customid"])){ unset($params["customid"]); }
				if(isset($params["toolid"])){ unset($params["toolid"]); }
				if(isset($params["_sop"])){ unset($params["_sop"]); }
				if(isset($params["_dcat"])){ unset($params["_dcat"]); }
				if(isset($params["epid"])){ unset($params["epid"]); }
				if(isset($params["epid"])){ unset($params["oid"]); }
				
				$params = http_build_query($params);
				
				if(strlen($params) === 0){
					$replace = "\?";
				}else{
					$replace = "";
				}
				
				$url = preg_replace(
					"/" . $replace . preg_quote($old_params, "/") . "$/",
					$params,
					$url
				);
				break;
		}
		
		return $url;
	}
	
	private function bingimg($url){
		
		$image = parse_url($url);
		
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
		
		return "https://" . $image["host"] . "/th?id=" . rawurlencode($id);
	}
	
	private function bingratio($width, $height){
		
		$ratio = [
			474 / $width,
			474 / $height
		];
		
		if($ratio[0] < $ratio[1]){
			
			$ratio = $ratio[0];
		}else{
			
			$ratio = $ratio[1];
		}
		
		return [
			floor($width * $ratio),
			floor($height * $ratio)
		];
	}
}
