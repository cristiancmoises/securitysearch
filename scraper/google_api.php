<?php

// @TODO check for consent.google.com page, if need be

class google_api{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("google_api");
	}
	
	public function getfilters($page){
		
		$base = [
			"country" => [ // gl=<country> (image: cr=countryAF)
				"display" => "Country",
				"option" => [
					"any" => "Instance's country",
					"af" => "Afghanistan",
					"al" => "Albania",
					"dz" => "Algeria",
					"as" => "American Samoa",
					"ad" => "Andorra",
					"ao" => "Angola",
					"ai" => "Anguilla",
					"aq" => "Antarctica",
					"ag" => "Antigua and Barbuda",
					"ar" => "Argentina",
					"am" => "Armenia",
					"aw" => "Aruba",
					"au" => "Australia",
					"at" => "Austria",
					"az" => "Azerbaijan",
					"bs" => "Bahamas",
					"bh" => "Bahrain",
					"bd" => "Bangladesh",
					"bb" => "Barbados",
					"by" => "Belarus",
					"be" => "Belgium",
					"bz" => "Belize",
					"bj" => "Benin",
					"bm" => "Bermuda",
					"bt" => "Bhutan",
					"bo" => "Bolivia",
					"ba" => "Bosnia and Herzegovina",
					"bw" => "Botswana",
					"bv" => "Bouvet Island",
					"br" => "Brazil",
					"io" => "British Indian Ocean Territory",
					"bn" => "Brunei Darussalam",
					"bg" => "Bulgaria",
					"bf" => "Burkina Faso",
					"bi" => "Burundi",
					"kh" => "Cambodia",
					"cm" => "Cameroon",
					"ca" => "Canada",
					"cv" => "Cape Verde",
					"ky" => "Cayman Islands",
					"cf" => "Central African Republic",
					"td" => "Chad",
					"cl" => "Chile",
					"cn" => "China",
					"cx" => "Christmas Island",
					"cc" => "Cocos (Keeling) Islands",
					"co" => "Colombia",
					"km" => "Comoros",
					"cg" => "Congo",
					"cd" => "Congo, the Democratic Republic",
					"ck" => "Cook Islands",
					"cr" => "Costa Rica",
					"ci" => "Cote D'ivoire",
					"hr" => "Croatia",
					"cu" => "Cuba",
					"cy" => "Cyprus",
					"cz" => "Czech Republic",
					"dk" => "Denmark",
					"dj" => "Djibouti",
					"dm" => "Dominica",
					"do" => "Dominican Republic",
					"ec" => "Ecuador",
					"eg" => "Egypt",
					"sv" => "El Salvador",
					"gq" => "Equatorial Guinea",
					"er" => "Eritrea",
					"ee" => "Estonia",
					"et" => "Ethiopia",
					"fk" => "Falkland Islands (Malvinas)",
					"fo" => "Faroe Islands",
					"fj" => "Fiji",
					"fi" => "Finland",
					"fr" => "France",
					"gf" => "French Guiana",
					"pf" => "French Polynesia",
					"tf" => "French Southern Territories",
					"ga" => "Gabon",
					"gm" => "Gambia",
					"ge" => "Georgia",
					"de" => "Germany",
					"gh" => "Ghana",
					"gi" => "Gibraltar",
					"gr" => "Greece",
					"gl" => "Greenland",
					"gd" => "Grenada",
					"gp" => "Guadeloupe",
					"gu" => "Guam",
					"gt" => "Guatemala",
					"gn" => "Guinea",
					"gw" => "Guinea-Bissau",
					"gy" => "Guyana",
					"ht" => "Haiti",
					"hm" => "Heard Island and Mcdonald Islands",
					"va" => "Holy See (Vatican City State)",
					"hn" => "Honduras",
					"hk" => "Hong Kong",
					"hu" => "Hungary",
					"is" => "Iceland",
					"in" => "India",
					"id" => "Indonesia",
					"ir" => "Iran, Islamic Republic",
					"iq" => "Iraq",
					"ie" => "Ireland",
					"il" => "Israel",
					"it" => "Italy",
					"jm" => "Jamaica",
					"jp" => "Japan",
					"jo" => "Jordan",
					"kz" => "Kazakhstan",
					"ke" => "Kenya",
					"ki" => "Kiribati",
					"kp" => "Korea, Democratic People's Republic",
					"kr" => "Korea, Republic",
					"kw" => "Kuwait",
					"kg" => "Kyrgyzstan",
					"la" => "Lao People's Democratic Republic",
					"lv" => "Latvia",
					"lb" => "Lebanon",
					"ls" => "Lesotho",
					"lr" => "Liberia",
					"ly" => "Libyan Arab Jamahiriya",
					"li" => "Liechtenstein",
					"lt" => "Lithuania",
					"lu" => "Luxembourg",
					"mo" => "Macao",
					"mk" => "Macedonia, the Former Yugosalv Republic",
					"mg" => "Madagascar",
					"mw" => "Malawi",
					"my" => "Malaysia",
					"mv" => "Maldives",
					"ml" => "Mali",
					"mt" => "Malta",
					"mh" => "Marshall Islands",
					"mq" => "Martinique",
					"mr" => "Mauritania",
					"mu" => "Mauritius",
					"yt" => "Mayotte",
					"mx" => "Mexico",
					"fm" => "Micronesia, Federated States",
					"md" => "Moldova, Republic",
					"mc" => "Monaco",
					"mn" => "Mongolia",
					"ms" => "Montserrat",
					"ma" => "Morocco",
					"mz" => "Mozambique",
					"mm" => "Myanmar",
					"na" => "Namibia",
					"nr" => "Nauru",
					"np" => "Nepal",
					"nl" => "Netherlands",
					"an" => "Netherlands Antilles",
					"nc" => "New Caledonia",
					"nz" => "New Zealand",
					"ni" => "Nicaragua",
					"ne" => "Niger",
					"ng" => "Nigeria",
					"nu" => "Niue",
					"nf" => "Norfolk Island",
					"mp" => "Northern Mariana Islands",
					"no" => "Norway",
					"om" => "Oman",
					"pk" => "Pakistan",
					"pw" => "Palau",
					"ps" => "Palestinian Territory, Occupied",
					"pa" => "Panama",
					"pg" => "Papua New Guinea",
					"py" => "Paraguay",
					"pe" => "Peru",
					"ph" => "Philippines",
					"pn" => "Pitcairn",
					"pl" => "Poland",
					"pt" => "Portugal",
					"pr" => "Puerto Rico",
					"qa" => "Qatar",
					"re" => "Reunion",
					"ro" => "Romania",
					"ru" => "Russian Federation",
					"rw" => "Rwanda",
					"sh" => "Saint Helena",
					"kn" => "Saint Kitts and Nevis",
					"lc" => "Saint Lucia",
					"pm" => "Saint Pierre and Miquelon",
					"vc" => "Saint Vincent and the Grenadines",
					"ws" => "Samoa",
					"sm" => "San Marino",
					"st" => "Sao Tome and Principe",
					"sa" => "Saudi Arabia",
					"sn" => "Senegal",
					"cs" => "Serbia and Montenegro",
					"sc" => "Seychelles",
					"sl" => "Sierra Leone",
					"sg" => "Singapore",
					"sk" => "Slovakia",
					"si" => "Slovenia",
					"sb" => "Solomon Islands",
					"so" => "Somalia",
					"za" => "South Africa",
					"gs" => "South Georgia and the South Sandwich Islands",
					"es" => "Spain",
					"lk" => "Sri Lanka",
					"sd" => "Sudan",
					"sr" => "Suriname",
					"sj" => "Svalbard and Jan Mayen",
					"sz" => "Swaziland",
					"se" => "Sweden",
					"ch" => "Switzerland",
					"sy" => "Syrian Arab Republic",
					"tw" => "Taiwan, Province of China",
					"tj" => "Tajikistan",
					"tz" => "Tanzania, United Republic",
					"th" => "Thailand",
					"tl" => "Timor-Leste",
					"tg" => "Togo",
					"tk" => "Tokelau",
					"to" => "Tonga",
					"tt" => "Trinidad and Tobago",
					"tn" => "Tunisia",
					"tr" => "Turkey",
					"tm" => "Turkmenistan",
					"tc" => "Turks and Caicos Islands",
					"tv" => "Tuvalu",
					"ug" => "Uganda",
					"ua" => "Ukraine",
					"ae" => "United Arab Emirates",
					"uk" => "United Kingdom",
					"us" => "United States",
					"um" => "United States Minor Outlying Islands",
					"uy" => "Uruguay",
					"uz" => "Uzbekistan",
					"vu" => "Vanuatu",
					"ve" => "Venezuela",
					"vn" => "Viet Nam",
					"vg" => "Virgin Islands, British",
					"vi" => "Virgin Islands, U.S.",
					"wf" => "Wallis and Futuna",
					"eh" => "Western Sahara",
					"ye" => "Yemen",
					"zm" => "Zambia",
					"zw" => "Zimbabwe"
				]
			],
			"nsfw" => [
				"display" => "NSFW",
				"option" => [
					"yes" => "Yes", // safe=active
					"no" => "No" // safe=off
				]
			],
			"sort" => [ // sort
				"display" => "Sort by",
				"option" => [
					"any" => "Any order",
					"date:d" => "Oldest",
					"date:a" => "Newest"
				]
			],
			"newer" => [
				"display" => "Newer than",
				"option" => "_DATE"
			],			
			"rm_dupes" => [ // filter
				"display" => "Remove duplicates",
				"option" => [
					"yes" => "Yes", // 1
					"no" => "No" // 0
				]
			]
		];
		
		switch($page){
			
			case "web":
				return array_merge(
					$base,
					[
						"lang" => [ // lr=<lang> (prefix lang with "lang_")
							"display" => "Language",
							"option" => [
								"any" => "Any language",
								"ar" => "Arabic",
								"bg" => "Bulgarian",
								"ca" => "Catalan",
								"cs" => "Czech",
								"da" => "Danish",
								"de" => "German",
								"el" => "Greek",
								"en" => "English",
								"es" => "Spanish",
								"et" => "Estonian",
								"fi" => "Finnish",
								"fr" => "French",
								"hr" => "Croatian",
								"hu" => "Hungarian",
								"id" => "Indonesian",
								"is" => "Icelandic",
								"it" => "Italian",
								"iw" => "Hebrew",
								"ja" => "Japanese",
								"ko" => "Korean",
								"lt" => "Lithuanian",
								"lv" => "Latvian",
								"nl" => "Dutch",
								"no" => "Norwegian",
								"pl" => "Polish",
								"pt" => "Portuguese",
								"ro" => "Romanian",
								"ru" => "Russian",
								"sk" => "Slovak",
								"sl" => "Slovenian",
								"sr" => "Serbian",
								"sv" => "Swedish",
								"tr" => "Turkish",
								"zh-CN" => "Chinese (Simplified)",
								"zh-TW" => "Chinese (Traditional)"
							]
						]
					]
				);
				break;
			
			case "images":
				return array_merge(
					$base,
					[
						"size" => [ // imgSize
							"display" => "Size",
							"option" => [
								"any" => "Any size",
								"icon" => "Icon",
								"small" => "Small",
								"medium" => "Medium",
								"large" => "Large",
								"xlarge" => "X-Large",
								"xxlarge" => "XX-Large",
								"huge" => "Huge"
							]
						],
						"format" => [ // fileType
							"display" => "Format",
							"option" => [
								"any" => "Any format",
								"jpg" => "JPG",
								"gif" => "GIF",
								"png" => "PNG",
								"bmp" => "BMP",
								"svg" => "SVG",
								"webp" => "WEBP",
								"ico" => "ICO",
								"craw" => "RAW"
							]
						],
						"color" => [
							"display" => "Color",
							"option" => [
								"any" => "Any color",
								
								"color" => "Full color", // imgColorType
								"mono" => "Black & White",
								"trans" => "Transparent background",
								
								"red" => "Red", // imgDominantColor
								"orange" => "Orange",
								"yellow" => "Yellow",
								"green" => "Green",
								"teal" => "Teal",
								"blue" => "Blue",
								"purple" => "Purple",
								"pink" => "Pink",
								"white" => "White",
								"gray" => "Gray",
								"black" => "Black",
								"brown" => "Brown"
							]
						],
						"type" => [ // imgType
							"display" => "Type",
							"option" => [
								"any" => "Any type",
								"clipart" => "Clip Art",
								"face" => "Faces",
								"lineart" => "Line Drawing",
								"stock" => "Stock photos",
								"photo" => "Photos",
								"animated" => "Animated",
							]
						],
						"rights" => [ // rights
							"display" => "Usage rights",
							"option" => [
								"any" => "Any license",
								"cc_publicdomain" => "Public domain",
								"cc_attribute" => "Attribution required",
								"cc_sharealike" => "Sharealike",
								"cc_noncommercial" => "Non-commercial use only",
								"cc_nonderived" => "Original works"
							]
						]
					]
				);
				break;
		}
	}
	
	private function get($proxy, $url, $get = []){
		
		$curlproc = curl_init();
			
		$headers = [
			"Accept: application/json",
			"Accept-Encoding: gzip"
		];
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER, $headers);
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);
		
		// follow redirects
		curl_setopt($curlproc, CURLOPT_FOLLOWLOCATION, true);

		$this->backend->assign_proxy($curlproc, $proxy);
		
		$data = curl_exec($curlproc);
		
		if(curl_errno($curlproc)){
			
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		
		return $data;
	}
	
	
	public function web($get){
		
		// rotate proxy + key on EVERY request
		$keydata = $this->backend->get_key();
		$proxy = $this->backend->get_ip($keydata["increment"]);
		
		if($get["npt"]){
			
			// $p is never used
			[$params, $p] = $this->backend->get(
				$get["npt"],
				"web"
			);
			
			$params = json_decode($params, true);
			
			$params["key"] = $keydata["key"];
			
		}else{
			
			//$json = file_get_contents("scraper/google.json");
			$params = [
				"q" => $get["s"],
				"cx" => config::GOOGLE_CX_ENDPOINT,
				"num" => 10,
				"start" => 1,
				"key" => $keydata["key"]
			];
			
			//
			// parse filters
			//
			if($get["newer"] !== false){
				
				$params["dateRestrict"] = "d" . (round((time() - $get["newer"]) / 100000));
			}
			
			if($get["rm_dupes"] == "no"){ $params["filter"] = "0"; }
			if($get["country"] != "any"){ $params["gl"] = $get["country"]; }
			if($get["lang"] != "any"){ $params["lr"] = "lang_" . $get["lang"]; }
			
			if($get["nsfw"] == "yes"){
				
				$params["safe"] = "off";
			}else{
				
				$params["safe"] = "active";
			}
			
			if($get["sort"] != "any"){ $params["sort"] = $get["sort"]; }
		}
		
		try{
			$json =
				$this->get(
					$proxy,
					"https://www.googleapis.com/customsearch/v1",
					$params
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
		
		if(isset($json["error"]["message"])){
			
			throw new Exception(
				"API returned an error: " .
				$json["error"]["message"] .
				" (key #" . $keydata["increment"] . ")"
			);
		}
		
		if(!isset($json["items"])){
			
			// google just doesnt return items when theres no results
			return $out;
		}
		
		foreach($json["items"] as $result){
			
			//
			// probe for thumbnail
			//
			$probes = [
				isset($result["pagemap"]["cse_thumbnail"][0]["src"]) ? $result["pagemap"]["cse_thumbnail"][0]["src"] : null,
				isset($result["pagemap"]["cse_image"][0]["src"]) ? $result["pagemap"]["cse_image"][0]["src"] : null,
				isset($result["pagemap"]["metatags"][0]["twitter:image"]) ? $result["pagemap"]["metatags"][0]["twitter:image"] : null,
				isset($result["pagemap"]["metatags"][0]["og:image"]) ? $result["pagemap"]["metatags"][0]["og:image"] : null
			];
			
			$thumb = [
				"url" => null,
				"ratio" => null
			];
			
			foreach($probes as $probe){
				
				if($probe !== null){
				
					$thumb = [
						"url" => $probe,
						"ratio" => "16:9"
					];
					break;
				}
			}
			
			//
			// probe for page format
			//
			$mime = "web";
			if(isset($result["mime"])){
				
				$result["mime"] =
					explode(
						"/",
						$result["mime"],
						2
					);
				
				if(count($result["mime"]) === 2){
					
					$mime = strtoupper($result["mime"][1]);
				}
			}
			
			$description = $result["snippet"];
			
			//
			// Get date
			//
			$description_split =
				explode(
					"...", $description, 2
				);
			
			if(count($description_split) === 1){
				
				$description = $result["snippet"];
			}elseif(strlen($description_split[0]) < 17){
				
				$date = trim($description_split[0]);
				$date_probe = strtotime($date);
				
				if($date_probe !== false){
					
					$description = $description_split[1];
				}else{
					
					//
					// fallback to getting date from meta tags
					//
					if(isset($result["pagemap"]["metatags"][0]["creationdate"])){
						
						$date = $result["pagemap"]["metatags"][0]["creationdate"];
						
					}elseif(isset($result["pagemap"]["metatags"][0]["moddate"])){
						
						$date = $result["pagemap"]["metatags"][0]["moddate"];
					}else{
						
						$date = null;
					}
					
					$description = $result["snippet"];
				}
			}
			
			if($date !== null){
				
				$date =
					strtotime(
						trim(
							str_replace(
								["D:", "'"],
								"",
								$date
							)
						)
					);
				
				if($date === false){
					
					$date = null;
				}
			}
			
			$out["web"][] = [
				"title" =>
					$this->titledots(
						$result["title"]
					),
				"description" =>
					$this->titledots(
						$description
					),
				"url" => $result["link"],
				"date" => $date,
				"type" => $mime,
				"thumb" => $thumb,
				"sublink" => [],
				"table" => []
			];
		}
		
		// get npt
		if(isset($json["queries"]["nextPage"][0]["startIndex"])){
			
			unset($params["key"]);
			$params["start"] = (int)$json["queries"]["nextPage"][0]["startIndex"];
			
			$out["npt"] =
				$this->backend->store(
					json_encode($params),
					"web",
					$proxy
				);
		}
		
		return $out;
	}
	
	
	public function image($get){
		
		// rotate proxy + key on EVERY request
		$keydata = $this->backend->get_key();
		$proxy = $this->backend->get_ip($keydata["increment"]);
		
		if($get["npt"]){
			
			// $p is never used
			[$params, $p] = $this->backend->get(
				$get["npt"],
				"web"
			);
			
			$params = json_decode($params, true);
			
			$params["key"] = $keydata["key"];
			
		}else{
			
			//$json = file_get_contents("scraper/google.json");
			$params = [
				"q" => $get["s"],
				"cx" => config::GOOGLE_CX_ENDPOINT,
				"num" => 10,
				"start" => 1,
				"searchType" => "image",
				"key" => $keydata["key"]
			];
			
			//
			// parse filters
			//
			if($get["newer"] !== false){
				
				$params["dateRestrict"] = "d" . (round((time() - $get["newer"]) / 100000));
			}
			
			if($get["rm_dupes"] == "no"){ $params["filter"] = "0"; }
			if($get["country"] != "any"){ $params["gl"] = $get["country"]; }
			
			if($get["nsfw"] == "yes"){
				
				$params["safe"] = "off";
			}else{
				
				$params["safe"] = "active";
			}
			
			if($get["sort"] != "any"){ $params["sort"] = $get["sort"]; }
			
			// image filters
			if($get["size"] != "any"){ $params["imgSize"] = $get["size"]; }
			if($get["format"] != "any"){ $params["fileType"] = $get["format"]; }
			
			switch($get["color"]){
				
				case "any":
					break;
				
				case "color":
				case "mono":
				case "trans":
					$params["imgColorType"] = $get["color"];
					break;
				
				default:
					$params["imgDominantColor"] = $get["color"];
					break;
			}
			
			if($get["type"] != "any"){ $params["imgType"] = $get["type"]; }
			if($get["rights"] != "any"){ $params["rights"] = $get["rights"]; }
		}
		
		try{
			$json =
				$this->get(
					$proxy,
					"https://www.googleapis.com/customsearch/v1",
					$params
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
			"image" => []
		];
		
		if(isset($json["error"]["message"])){
			
			throw new Exception(
				"API returned an error: " .
				$json["error"]["message"] .
				" (key #" . $keydata["increment"] . ")"
			);
		}
		
		if(!isset($json["items"])){
			
			// google just doesnt return items when theres no results
			return $out;
		}
		
		foreach($json["items"] as $image){
			
			$out["image"][] = [
				"title" => $this->titledots($image["title"]),
				"source" => [
					[
						"url" => $image["link"],
						"width" => (int)$image["image"]["width"],
						"height" => (int)$image["image"]["height"]
					],
					[
						"url" => $image["image"]["thumbnailLink"],
						"width" => (int)$image["image"]["thumbnailWidth"],
						"height" => (int)$image["image"]["thumbnailHeight"]
					]
				],
				"url" => $image["image"]["contextLink"]
			];
		}
		
		// get npt
		if(isset($json["queries"]["nextPage"][0]["startIndex"])){
			
			unset($params["key"]);
			$params["start"] = (int)$json["queries"]["nextPage"][0]["startIndex"];
			
			$out["npt"] =
				$this->backend->store(
					json_encode($params),
					"web",
					$proxy
				);
		}
		
		return $out;
	}
	
	
	private function titledots($title){
		
		return trim($title, " .\t\n\r\0\x0B…");
	}
}
