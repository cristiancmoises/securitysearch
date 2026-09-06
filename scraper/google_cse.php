<?php

class google_cse{
	
	public const req_html = 0;
	public const req_js = 1;
	private const TOKEN_TTL = 300;
	private const TOKEN_LOCK_TTL = 60;
	private const TOKEN_FAILURE_TTL = 5;
	private const TOKEN_ANTI_ABUSE_FAILURE_TTL = 30;
	private const TOKEN_WAIT_USEC = 50000;
	private const TOKEN_WAIT_ATTEMPTS = 120;
	private $backend;
	private $fuckhtml;
	private $backend_name;
	private $request_deadline;
	
	public function __construct($backend_name = "google_cse"){
		$this->request_deadline = hrtime(true) + 25000000000;
		$this->backend_name = $backend_name;
		
		include "lib/backend.php";
		$this->backend = new backend($backend_name);
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function getfilters($page){
		
		$base = [
			"country" => [ // gl=<country> (image: cr=countryAF)
				"display" => "Country",
				"option" => [
					"any" => "Any country",
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
					"yes" => "Yes", // safe=off
					"no" => "No" // safe=active
				]
			],
			"spellcheck" => [
				// display undefined
				"option" => [
					"yes" => "Yes",
					"no" => "No"
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
						],
						"sort" => [
							"display" => "Sort by",
							"option" => [
								"relevance" => "Relevance",
								"date" => "Date"
							]
						],
						"redundant" => [
							"display" => "Remove redundant",
							"option" => [
								"yes" => "Yes",
								"no" => "No",
							]
						]
					]
				);
				break;
			
			case "images":
				return array_merge(
					$base,
					[
						"size" => [ // imgsz
							"display" => "Size",
							"option" => [
								"any" => "Any size",
								"l" => "Large",
								"m" => "Medium",
								"i" => "Icon",
								"qsvga" => "Larger than 400x300",
								"vga" => "Larger than 640x480",
								"svga" => "Larger than 800x600",
								"xga" => "Larger than 1024x768",
								"2mp" => "Larger than 2MP",
								"4mp" => "Larger than 4MP",
								"6mp" => "Larger than 6MP",
								"8mp" => "Larger than 8MP",
								"10mp" => "Larger than 10MP",
								"12mp" => "Larger than 12MP",
								"15mp" => "Larger than 15MP",
								"20mp" => "Larger than 20MP",
								"40mp" => "Larger than 40MP",
								"70mp" => "Larger than 70MP"
							]
						],
						"color" => [ // imgc
							"display" => "Color",
							"option" => [
								"any" => "Any color",
								"color" => "Full color",
								"bnw" => "Black & white",
								"trans" => "Transparent",
								// from here, imgcolor
								"red" => "Red",
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
						"format" => [ // as_filetype
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
						]
					]
				);
				break;
		}
	}
	
	private function remaining_network_ms(){
		$remaining = (int)(($this->request_deadline - hrtime(true)) / 1000000);
		if($remaining < 100){ throw new Exception("Google search reached its 25-second request budget. Please retry later or choose another provider."); }
		return min(20000, $remaining);
	}

	private function get($proxy, $url, $get = [], $reqtype = self::req_js, $retried = false){
		$remaining = $this->remaining_network_ms();
		
		$curlproc = curl_init();
			
		if($get !== []){
			
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		// http2 bypass
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		if($reqtype === self::req_js){
			
			curl_setopt($curlproc, CURLOPT_HTTPHEADER,
				["User-Agent: " . config::USER_AGENT,
				"Accept: */*",
				"Accept-Language: en-US,en;q=0.5",
				"Accept-Encoding: gzip",
				"DNT: 1",
				"Sec-GPC: 1",
				"Alt-Used: cse.google.com",
				"Connection: keep-alive",
				"Referer: https://cse.google.com/cse?cx=" . config::GOOGLE_CX_ENDPOINT,
				"Sec-Fetch-Dest: script",
				"Sec-Fetch-Mode: no-cors",
				"Sec-Fetch-Site: same-origin",
				"TE: trailers"]
			);
		}else{
			
			curl_setopt($curlproc, CURLOPT_HTTPHEADER,
				["User-Agent: " . config::USER_AGENT,
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/png,image/svg+xml,*/*;q=0.8",
				"Accept-Language: en-US,en;q=0.5",
				"Accept-Encoding: gzip",
				"DNT: 1",
				"Sec-GPC: 1",
				"Connection: keep-alive",
				"Upgrade-Insecure-Requests: 1",
				"Sec-Fetch-Dest: document",
				"Sec-Fetch-Mode: navigate",
				"Sec-Fetch-Site: none",
				"Sec-Fetch-User: ?1",
				"Priority: u=0, i"]
			);
		}
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT_MS, min(5000, $remaining));
		curl_setopt($curlproc, CURLOPT_TIMEOUT_MS, $remaining);
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		$data = curl_exec($curlproc);
		$curl_error = curl_errno($curlproc) ? curl_error($curlproc) : null;
		$status = (int)curl_getinfo($curlproc, CURLINFO_RESPONSE_CODE);
		curl_close($curlproc);

		if($curl_error !== null){

			throw new Exception($curl_error);
		}
		// One retry for transient gateway errors on the SAME provider/egress.
		// No challenge solving, proxy rotation, TLS downgrade or unbounded loops.
		if(!$retried && in_array($status, [502, 503, 504], true) && !$this->is_google_anti_abuse_error($data)){
			usleep(150000);
			return $this->get($proxy, $url, [], $reqtype, true);
		}

		if(
			$status === 429 ||
			($status === 403 && $this->is_google_anti_abuse_error($data))
		){

			throw new Exception("Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.");
		}
		if($status >= 400){ throw new Exception("Google returned HTTP " . $status . ". Please retry later or choose another provider."); }

		if(!is_string($data)){

			throw new Exception("Google returned an empty response");
		}

		return $data;
	}

	private function decode_response($payload){

		if(
			!preg_match(
				'/google\.search\.cse\.[A-Za-z0-9]+\(([\S\s]*)\);/i',
				$payload,
				$match
			)
		){

			if($this->is_google_anti_abuse_error($payload)){

				throw new Exception("Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.");
			}

			throw new Exception("Failed to grep JSON");
		}

		$json = json_decode($match[1], true);
		if(!is_array($json)){

			throw new Exception("Google returned malformed JSON");
		}

		return $json;
	}

	private function is_google_anti_abuse_error($text){

		return
			is_string($text) &&
			preg_match(
				'/unusual\s+traffic|automated\s+(?:queries|traffic)|captcha|throttl|rate[ -]?limit|too many requests|(?:http|status|code)[^0-9]{0,8}429/i',
				$text
			) === 1;
	}

	private function request_cse($proxy, &$req_params, $retry_token_error){

		$cooldown_key = $this->cse_request_cooldown_key($proxy);
		$this->throw_cse_request_cooldown($cooldown_key);

		try{

			$payload =
				$this->get(
					$proxy,
					"https://cse.google.com/cse/element/v1",
					$req_params,
					self::req_js
				);

			$json = $this->decode_response($payload);
			$error_text = isset($json["error"]) ? json_encode($json["error"]) : "";
			$anti_abuse_error = $this->is_google_anti_abuse_error($error_text);
			if($anti_abuse_error){

				$this->remember_cse_request_cooldown($cooldown_key);
			}
			$token_error =
				is_string($error_text) &&
				preg_match(
					'/cse[_ -]?tok|token|expired|unauthorized|unauthorised|internal[ -]?api/i',
					$error_text
				) === 1;

			if(
				!$retry_token_error ||
				$error_text === "" ||
				$anti_abuse_error ||
				!$token_error
			){

				return $json;
			}

			// A short-cached or continuation token can expire. Bootstrap once with a
			// fresh token, then return the second response so provider errors never loop.
			$params =
				$this->generate_token(
					$proxy,
					true,
					$req_params["cse_tok"] ?? null
				);
			$req_params["cse_tok"] = $params["token"];
			$req_params["cselibv"] = $params["lib"];

			$retry_json =
				$this->decode_response(
					$this->get(
						$proxy,
						"https://cse.google.com/cse/element/v1",
						$req_params,
						self::req_js
					)
				);
			$retry_error_text = isset($retry_json["error"]) ? json_encode($retry_json["error"]) : "";
			if($this->is_google_anti_abuse_error($retry_error_text)){

				$this->remember_cse_request_cooldown($cooldown_key);
			}

			return $retry_json;
		}catch(Throwable $error){

			if($this->is_google_anti_abuse_error($error->getMessage())){

				$this->remember_cse_request_cooldown($cooldown_key);
			}

			throw $error;
		}
	}

	private function cse_request_cooldown_key($proxy){

		return
			"g.cse.request.failure." .
			hash(
				"sha256",
				$this->backend_name . "\0" . config::GOOGLE_CX_ENDPOINT . "\0" . serialize($proxy)
			);
	}

	private function throw_cse_request_cooldown($cooldown_key){

		if(!function_exists("apcu_fetch")){

			return;
		}

		apcu_fetch($cooldown_key, $hit);
		if($hit){

			throw new Exception("Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.");
		}
	}

	private function remember_cse_request_cooldown($cooldown_key){

		if(function_exists("apcu_store")){

			apcu_store($cooldown_key, true, self::TOKEN_ANTI_ABUSE_FAILURE_TTL);
		}
	}

	private function format_google_error($json){

		$message = "Google returned an error object";
		if(
			isset($json["error"]["errors"][0]["message"]) &&
			is_string($json["error"]["errors"][0]["message"]) &&
			$json["error"]["errors"][0]["message"] !== ""
		){

			$message = $json["error"]["errors"][0]["message"];
		}elseif(
			isset($json["error"]["message"]) &&
			is_string($json["error"]["message"]) &&
			$json["error"]["message"] !== ""
		){

			$message = $json["error"]["message"];
		}

		if($this->is_google_anti_abuse_error($message)){

			return "Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.";
		}

		return strpos($message, "Google returned") === 0 ? $message : "Google returned an error: " . $message;
	}
	
	public function web($get){
		
		// page 1
		// https://cse.google.com/cse/element/v1?rsz=filtered_cse&num=10&hl=en&source=gcsc&cselibv=8fa85d58e016b414&cx=d4e68b99b876541f0&q=asmr&safe=active&cse_tok=AB-tC_6RPUTmB4XK0lE9e1AFFC5r%3A1729563832926&lr=&cr=&gl=&filter=0&sort=&as_oq=&as_sitesearch=&exp=cc%2Capo&oq=asmr&gs_l=partner-web.3..0i512i433j0i512i433i131l2j0i512i433j0i512i433i131j0i512i433j0i512i433i131l2j0i512l2.10902.266627.5.267157.11.10.0.0.0.0.188.1108.2j7.9.0.csems%2Cnrl%3D10...0....1.34.partner-web..42.14.1500.WJQvMvfXkx4&cseclient=hosted-page-client&callback=google.search.cse.api8223&rurl=https%3A%2F%2Fcse.google.com%2Fcse%3Fcx%3Dd4e68b99b876541f0%23gsc.tab%3D0%26gsc.q%3Dtest%26gsc.sort%3D
		
		// page 2
		// https://cse.google.com/cse/element/v1?rsz=filtered_cse&num=10&hl=en&source=gcsc&start=10&cselibv=8fa85d58e016b414&cx=d4e68b99b876541f0&q=asmr&safe=active&cse_tok=AB-tC_6RPUTmB4XK0lE9e1AFFC5r%3A1729563832926&lr=&cr=&gl=&filter=0&sort=&as_oq=&as_sitesearch=&exp=cc%2Capo&callback=google.search.cse.api3595&rurl=https%3A%2F%2Fcse.google.com%2Fcse%3Fcx%3Dd4e68b99b876541f0%23gsc.tab%3D0%26gsc.q%3Dtest%26gsc.sort%3D
		
		if($get["npt"]){
			$retry_token_error = true;
			
			[$req_params, $proxy] =
				$this->backend->get(
					$get["npt"],
					"web"
				);
			
			$req_params =
				json_decode(
					$req_params,
					true
				);
			
		}else{
			
			$proxy = $this->backend->get_ip();
			$params = $this->generate_token($proxy);
			$retry_token_error = $params["cached"];
			
			//$json = file_get_contents("scraper/google_cse.txt");
			$req_params = [
				"rsz" => "filtered_cse",
				"num" => 20,
				"hl" => "en",
				"source" => "gcsc",
				"cselibv" => $params["lib"],
				"cx" => config::GOOGLE_CX_ENDPOINT,
				"q" => $get["s"],
				"safe" => $get["nsfw"] == "yes" ? "off" : "active",
				"cse_tok" => $params["token"],
				"lr" => $get["lang"] == "any" ? "" : "lang_" . $get["lang"],
				"cr" => $get["country"] == "any" ? "" : "country" . strtoupper($get["country"]),
				"gl" => "",
				"filter" => $get["redundant"] == "yes" ? "1" : "0",
				"sort" => $get["sort"] == "relevance" ? "" : "date",
				"as_oq" => "",
				"as_sitesearch" => "",
				"exp" => "cc,apo",
				"oq" => $get["s"],
				"gs_l" => "partner-web.3...33294.34225.3.34597.26.11.0.0.0.0.201.1132.6j4j1.11.0.csems,nrl=10...0....1.34.partner-web..34.19.1897.FKEeG5yh2iw",
				"cseclient" => "hosted-page-client",
				"callback" => "google.search.cse.api" . random_int(4000, 99999),
				"rurl" => "https://cse.google.com/cse?cx=" . config::GOOGLE_CX_ENDPOINT . "#gsc.tab=0&gsc.q=" . $get["s"] . "&gsc.sort="
			];
			
			if($get["spellcheck"] == "no"){
				
				$req_params["nfpr"] = "1";
			}
			
		}

		$json = $this->request_cse($proxy, $req_params, $retry_token_error);

		if(!$get["npt"]){

			unset($req_params["gs_l"]);
			$req_params["start"] = 0;
		}
		
		$req_params["start"] += 20;
		
		if(isset($json["error"])){

			throw new Exception($this->format_google_error($json));
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
		
		// detect word correction
		if(isset($json["spelling"]["type"])){
			
			switch($json["spelling"]["type"]){
				
				case "DYM": // did you mean? @TODO fix wording
					$type = "including";
					break;
				
				case "SPELL_CORRECTED_RESULTS": // not many results for
					$type = "not_many";
					break;
				
				default:
					$type = "not_many";
			}
			
			if(isset($json["spelling"]["originalQuery"])){
				
				$using = $json["spelling"]["originalQuery"];
			}
			elseif(isset($json["spelling"]["anchor"])){
				
				$using = html_entity_decode(strip_tags($json["spelling"]["anchor"]));
			}elseif(isset($json["spelling"]["originalAnchor"])){
				
				$using = html_entity_decode(strip_tags($json["spelling"]["originalAnchor"]));
			}
			
			$out["spelling"] = [
				"type" => $type,
				"using" => $using,
				"correction" => $json["spelling"]["correctedQuery"]
			];
		}
		
		if(!isset($json["results"])){
			
			return $out;
		}
		
		foreach($json["results"] as $result){
			
			// get date from description
			$description =
				explode(
					"...",
					trim($result["contentNoFormatting"], " ."),
					2
				);
			
			if(count($description) === 2){
				
				if($date = strtotime($description[0])){
					
					$description = ltrim($description[1]);
				}else{
					
					$date = null;
					$description = implode("...", $description);
				}
			}else{
				
				$description = implode("...", $description);
				$date = null;
			}
			
			$description = trim($description, " .");
			
			// get thumbnails
			if(isset($result["richSnippet"]["cseThumbnail"]["src"])){
				
				$thumb = [
					"url" => $this->unshit_thumb($result["richSnippet"]["cseThumbnail"]["src"]),
					"ratio" => "1:1"
				];
			}
			elseif(isset($result["richSnippet"]["cseImage"]["src"])){
				
				$thumb = [
					"url" => $result["richSnippet"]["cseImage"]["src"],
					"ratio" => "1:1"
				];
			}else{
				
				$thumb = [
					"url" => null,
					"ratio" => null
				];
			}
			
			if($thumb["url"] !== null){
				
				$found_size = false;
				
				// find correct ratio
				
				if(
					isset($result["richSnippet"]["cseThumbnail"]["width"]) &&
					isset($result["richSnippet"]["cseThumbnail"]["height"])
				){
					$found_size = true;
					$width = (int)$result["richSnippet"]["cseThumbnail"]["width"];
					$height = (int)$result["richSnippet"]["cseThumbnail"]["height"];
				}
				elseif(
					isset($result["richSnippet"]["metatags"]["ogImageWidth"]) &&
					isset($result["richSnippet"]["metatags"]["ogImageHeight"])
				){
					$found_size = true;
					$width = (int)$result["richSnippet"]["metatags"]["ogImageWidth"];
					$height = (int)$result["richSnippet"]["metatags"]["ogImageHeight"];
				}
				
				// calculate rounded ratio
				if($found_size){
					
					$aspect_ratio = $width / $height;
					
					if($aspect_ratio >= 1.5){
						
						$thumb["ratio"] = "16:9";
					}
					elseif($aspect_ratio >= 0.8){
						
						$thumb["ratio"] = "1:1";
					}else{
						
						$thumb["ratio"] = "9:16";
					}
				}
			}
			
			$out["web"][] = [
				"title" => rtrim($result["titleNoFormatting"], " ."),
				"description" => $description,
				"url" => $result["unescapedUrl"],
				"date" => $date,
				"type" => "web",
				"thumb" => $thumb,
				"sublink" => [],
				"table" => []
			];
		}
		
		// detect next page
		if(
			isset($json["cursor"]["isExactTotalResults"]) || // detects last page
			!isset($json["cursor"]["pages"]) // detects no results on page
		){
			
			return $out;
		}
		
		// get next page
		$out["npt"] =			
			$this->backend->store(
				json_encode(
					$req_params
				),
				"web",
				$proxy
			);
		
		return $out;
	}
	
	public function image($get){
		
		if($get["npt"]){
			$retry_token_error = true;
			
			[$req_params, $proxy] =
				$this->backend->get(
					$get["npt"],
					"images"
				);
			
			$req_params =
				json_decode(
					$req_params,
					true
				);
			
		}else{
			
			$proxy = $this->backend->get_ip();
			$params = $this->generate_token($proxy);
			$retry_token_error = $params["cached"];
			
			//$json = file_get_contents("scraper/google_cse.txt");
			$req_params = [
				"rsz" => "filtered_cse",
				"num" => 20,
				"hl" => "en",
				"source" => "gcsc",
				"cselibv" => $params["lib"],
				"searchtype" => "image",
				"cx" => config::GOOGLE_CX_ENDPOINT,
				"q" => $get["s"],
				"safe" => $get["nsfw"] == "yes" ? "off" : "active",
				"cse_tok" => $params["token"],
				"exp" => "cc,apo",
				"cseclient" => "hosted-page-client",
				"callback" => "google.search.cse.api" . random_int(4000, 99999),
				"rurl" => "https://cse.google.com/cse?cx=" . config::GOOGLE_CX_ENDPOINT . "#gsc.tab=1&gsc.q=" . $get["s"] . "&gsc.sort="
			];
			
			// add additional hidden filters
			
			// country (image search uses cr instead of gl)
			if($get["country"] != "any"){
				
				$req_params["cr"] = "country" . strtoupper($get["country"]);
			}
			
			// nsfw
			$req_params["safe"] = $get["nsfw"] == "yes" ? "off" : "active";
			
			// size
			if($get["size"] != "any"){
				
				$req_params["imgsz"] = $get["size"];
			}
			
			// format
			if($get["format"] != "any"){
				
				$req_params["as_filetype"] = $get["format"];
			}
			
			// color
			if($get["color"] != "any"){
				
				if(
					$get["color"] == "color" ||
					$get["color"] == "trans"
				){
					
					$req_params["imgc"] = $get["color"];
				}elseif($get["color"] == "bnw"){
					
					$req_params["imgc"] = "gray";
				}else{
					
					$req_params["imgcolor"] = $get["color"];
				}
			}
			
		}

		$json = $this->request_cse($proxy, $req_params, $retry_token_error);

		if(!$get["npt"]){

			$req_params["start"] = 0;
		}
		
		$req_params["start"] += 20;
		
		if(isset($json["error"])){

			throw new Exception($this->format_google_error($json));
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		// A response can contain a final page of image results while also marking
		// the cursor as exact/finished. Parse those results before deciding
		// whether another page token should be offered.
		if(!isset($json["results"]) || !is_array($json["results"])){
			
			return $out;
		}
		
		foreach($json["results"] as $result){

			$parsed_result = $this->parse_image_result($result);
			if($parsed_result !== null){

				$out["image"][] = $parsed_result;
			}
		}

		// Only decide whether a following page exists after preserving every
		// result returned on this page.
		if(
			isset($json["cursor"]["isExactTotalResults"]) || // detects last page
			!isset($json["cursor"]["pages"]) // detects no results on page
		){

			return $out;
		}
		
		// get next page
		$out["npt"] =			
			$this->backend->store(
				json_encode(
					$req_params
				),
				"images",
				$proxy
			);
		
		return $out;
	}

	private function motion_format_hint($result){

		foreach(["mime", "fileFormat"] as $field){

			if(
				isset($result[$field]) &&
				is_string($result[$field]) &&
				preg_match('#\A(?:image/)?(gif|webp|apng)(?:\s*;.*)?\z#i', trim($result[$field]), $match) === 1
			){

				return strtolower($match[1]);
			}
		}

		return null;
	}

	private function parse_image_result($result){

		if(!is_array($result)){

			return null;
		}

		$original_url = $this->valid_remote_image_url($result["unescapedUrl"] ?? null);
		$large_thumbnail_url = $this->valid_remote_image_url($result["tbLargeUrl"] ?? null);
		$thumbnail_url =
			$large_thumbnail_url ??
			$this->valid_remote_image_url($result["tbUrl"] ?? null);
		if($original_url === null){

			$original_url = $thumbnail_url;
		}
		if($original_url === null){

			return null;
		}

		$sources = [
			[
				"url" => $original_url,
				"width" => $this->positive_image_dimension($result["width"] ?? null),
				"height" => $this->positive_image_dimension($result["height"] ?? null)
			]
		];
		if($thumbnail_url !== null && $thumbnail_url !== $original_url){

			$sources[] = [
				"url" => $thumbnail_url,
				"width" => $this->positive_image_dimension(
					$large_thumbnail_url !== null ?
					($result["tbLargeWidth"] ?? null) :
					($result["tbWidth"] ?? null)
				),
				"height" => $this->positive_image_dimension(
					$large_thumbnail_url !== null ?
					($result["tbLargeHeight"] ?? null) :
					($result["tbHeight"] ?? null)
				)
			];
		}

		$title =
			isset($result["titleNoFormatting"]) && is_string($result["titleNoFormatting"]) ?
			rtrim($result["titleNoFormatting"], " .") :
			"Image result";
		if($title === ""){

			$title = "Image result";
		}

		return [
			"title" => $title,
			"motion_format" => $this->motion_format_hint($result),
			"source" => $sources,
			"url" =>
				$this->valid_remote_image_url($result["originalContextUrl"] ?? null) ??
				$original_url
		];
	}

	private function positive_image_dimension($dimension){

		if(!is_numeric($dimension)){

			return null;
		}

		$dimension = (int)$dimension;
		return $dimension > 0 && $dimension <= 1000000 ? $dimension : null;
	}

	private function valid_remote_image_url($url){

		if(
			!is_string($url) ||
			$url === "" ||
			strlen($url) > 16384 ||
			preg_match('/[\x00-\x20\x7f]/', $url) === 1
		){

			return null;
		}

		$parts = parse_url($url);
		if(
			!is_array($parts) ||
			!isset($parts["scheme"], $parts["host"]) ||
			!in_array(strtolower($parts["scheme"]), ["http", "https"], true) ||
			$parts["host"] === "" ||
			isset($parts["user"]) ||
			isset($parts["pass"])
		){

			return null;
		}

		return $url;
	}
	
	private function generate_token($proxy, $force_refresh = false, $rejected_token = null){

		$cache_key =
			"g.cse.bootstrap." .
			hash(
				"sha256",
				$this->backend_name . "\0" . config::GOOGLE_CX_ENDPOINT . "\0" . (string)$proxy
			);
		$lock_key = $cache_key . ".lock";
		$failure_key = $cache_key . ".failure";
		$cache_available =
			function_exists("apcu_fetch") &&
			function_exists("apcu_store") &&
			(
				!function_exists("apcu_enabled") ||
				apcu_enabled()
			);
		$singleflight_available =
			$cache_available &&
			function_exists("apcu_add") &&
			function_exists("apcu_cas") &&
			function_exists("apcu_delete");
		$previous_flight = null;

		if($cache_available){

			$cached = $this->get_cached_bootstrap($cache_key);
			if($cached !== null){

				if(
					!$force_refresh ||
					(
						is_string($rejected_token) &&
						!hash_equals($cached["token"], $rejected_token)
					)
				){

					return $this->return_cached_bootstrap($cached);
				}

				$previous_flight = $cached["_flight"] ?? null;
			}
		}

		$owns_lock = false;
		$flight = null;
		if($singleflight_available){

			$this->throw_cached_bootstrap_failure($failure_key);
			$flight = random_int(1, 2147483647);
			$owns_lock = apcu_add($lock_key, $flight, self::TOKEN_LOCK_TTL);

			if(!$owns_lock){

				for($attempt = 0; $attempt < self::TOKEN_WAIT_ATTEMPTS; $attempt++){

					$this->throw_cached_bootstrap_failure($failure_key);
					$cached = $this->get_cached_bootstrap($cache_key);
					if(
						$cached !== null &&
						(
							!$force_refresh ||
							(
								isset($cached["_flight"]) &&
								$cached["_flight"] !== $previous_flight
							)
						)
					){

						return $this->return_cached_bootstrap($cached);
					}

					usleep(self::TOKEN_WAIT_USEC);
				}

				$this->throw_cached_bootstrap_failure($failure_key);
				throw new Exception("Google is preparing a search session for another request. Please retry in a moment.");
			}

			// Close the cache-miss/publication race after acquiring the lock. A
			// forced refresh deliberately invalidates the rejected generation.
			if(!$force_refresh){

				$cached = $this->get_cached_bootstrap($cache_key);
				if($cached !== null){

					$this->release_bootstrap_lock($lock_key, $flight);
					return $this->return_cached_bootstrap($cached);
				}
			}else{

				apcu_delete($cache_key);
			}

			apcu_delete($failure_key);
		}elseif($cache_available && $force_refresh){

			// APCu can be built without the atomic primitives above. Preserve the
			// previous refresh behavior instead of making caching a requirement.
			apcu_delete($cache_key);
		}

		try{
		
			$html =
				$this->get(
					$proxy,
					"https://cse.google.com/cse",
					[
						"cx" => config::GOOGLE_CX_ENDPOINT
					],
					self::req_html
				);

			if($this->is_google_anti_abuse_error($html)){

				throw new Exception("Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.");
			}
		
			// detect captcha
			$this->fuckhtml->load($html);
		
			$title =
				$this->fuckhtml
				->getElementsByTagName(
					"title"
				);
		
			if(
				count($title) !== 0 &&
				$title[0]["innerHTML"] == "302 Moved"
			){
			
				throw new Exception("Google returned a captcha");
			}
		
			// get token
			preg_match(
				'/relativeUrl=\'([^\']+)\';/i',
				$html,
				$js_uri
			);
		
			if(!isset($js_uri[1])){
			
				throw new Exception("Failed to grep search token");
			}
		
			$js_uri =
				$this->fuckhtml
				->parseJsString(
					$js_uri[1]
				);
		
			// get parameters
			$js =
				$this->get(
					$proxy,
					"https://cse.google.com" . $js_uri,
					[],
					self::req_js
				);

			if($this->is_google_anti_abuse_error($js)){

				throw new Exception("Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.");
			}
		
			preg_match(
				'/}\)\(({[\S\s]+})\);/',
				$js,
				$json
			);
		
			if(!isset($json[1])){
			
				throw new Exception("Failed to grep JSON parameters");
			}
		
			$json = json_decode($json[1], true);

			if(
				!is_array($json) ||
				!isset($json["cse_token"], $json["cselibVersion"]) ||
				!is_string($json["cse_token"]) ||
				!is_string($json["cselibVersion"]) ||
				$json["cse_token"] === "" ||
				$json["cselibVersion"] === ""
			){

				throw new Exception("Google returned malformed bootstrap parameters");
			}
		
			$params = [
				"token" => $json["cse_token"],
				"lib" => $json["cselibVersion"]
			];

			if($cache_available){

				$stored_params = $params;
				if($owns_lock){

					$stored_params["_flight"] = $flight;
				}
				apcu_store($cache_key, $stored_params, self::TOKEN_TTL);
				if($singleflight_available){

					apcu_delete($failure_key);
				}
			}

			$params["cached"] = false;
			return $params;
		}catch(Throwable $error){

			if($owns_lock){

				$anti_abuse = $this->is_google_anti_abuse_error($error->getMessage());
				apcu_store(
					$failure_key,
					$anti_abuse ? "anti_abuse" : "upstream",
					$anti_abuse ? self::TOKEN_ANTI_ABUSE_FAILURE_TTL : self::TOKEN_FAILURE_TTL
				);
			}

			throw $error;
		}finally{

			if($owns_lock){

				$this->release_bootstrap_lock($lock_key, $flight);
			}
		}
	}

	private function get_cached_bootstrap($cache_key){

		$cached = apcu_fetch($cache_key, $hit);
		if(
			!$hit ||
			!is_array($cached) ||
			!isset($cached["token"], $cached["lib"]) ||
			!is_string($cached["token"]) ||
			!is_string($cached["lib"]) ||
			$cached["token"] === "" ||
			$cached["lib"] === ""
		){

			return null;
		}

		return $cached;
	}

	private function return_cached_bootstrap($cached){

		unset($cached["_flight"]);
		$cached["cached"] = true;
		return $cached;
	}

	private function throw_cached_bootstrap_failure($failure_key){

		$failure = apcu_fetch($failure_key, $hit);
		if(!$hit){

			return;
		}

		if($failure === "anti_abuse"){

			throw new Exception("Google temporarily rate-limited this instance. Please wait a moment and retry, or choose another provider in the Scraper filter.");
		}

		throw new Exception("Google could not prepare a search session in another request. Please retry in a moment.");
	}

	private function release_bootstrap_lock($lock_key, $flight){

		if(apcu_cas($lock_key, $flight, 0)){

			apcu_delete($lock_key);
		}
	}
	
	private function unshit_thumb($url){
		// https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQINE2vbnNLHXqoZr3RVsaEJFyOsj1_BiBnJch-e1nyz3oia7Aj5xVj
		// https://i.ytimg.com/vi/PZVIyA5ER3Y/mqdefault.jpg?sqp=-oaymwEFCJQBEFM&rs=AMzJL3nXeaCpdIar-ltNwl82Y82cIJfphA
		
		$parts = parse_url($url);
		
		if(
			isset($parts["host"]) &&
			preg_match(
				'/tbn.*\.gstatic\.com/',
				$parts["host"]
			)
		){
			
			parse_str($parts["query"], $params);
			
			if(isset($params["q"])){
				
				return "https://" . $parts["host"] . "/images?q=" . $params["q"];
			}
		}
		
		return $url;
	}
}
