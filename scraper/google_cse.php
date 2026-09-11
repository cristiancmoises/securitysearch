<?php
require_once __DIR__."/../lib/search_health.php";
require_once __DIR__."/../lib/google_cse_protocol.php";

class google_cse{
	
	public const req_html = 0;
	public const req_js = 1;
	private const TOKEN_TTL = 300;
	private const TOKEN_LOCK_TTL = 60;
	private const TOKEN_FAILURE_TTL = 5;
	private const TOKEN_ANTI_ABUSE_FAILURE_TTL = 30;
	private const TOKEN_WAIT_USEC = 50000;
	private const TOKEN_WAIT_ATTEMPTS = 40; // <=2s contention wait, preserving the owner's flight.
	private $backend;
	private $fuckhtml;
	private $backend_name;
	private $request_deadline;
	private $transport;
	private $transport_proxy;
	private const MAX_RESPONSE_BYTES = 4194304;
	
	public function __construct($backend_name = "google_cse"){
		$this->request_deadline = hrtime(true) + 25000000000;
		$this->backend_name = $backend_name;
		
		include_once "lib/backend.php";
		$this->backend = new backend($backend_name);
		
		include_once "lib/fuckhtml.php";
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
								"webp" => "WebP",
								"avif" => "AVIF",
								"apng" => "APNG",
								"ico" => "ICO",
								"craw" => "RAW"
							]
						]
					]
				);
				break;
		}
	}
	
	public function set_request_deadline(int $deadline): void { $this->request_deadline=min($this->request_deadline,$deadline); }

	private function remaining_network_ms(){
		$remaining = (int)(($this->request_deadline - hrtime(true)) / 1000000);
		if($remaining < 100){ throw new Exception("Google search reached its request budget. Please retry later or choose another provider."); }
		return min(20000, $remaining);
	}

	private function transport_for($proxy){
		// Reuse DNS/TLS/SOCKS connections only within this search and this egress.
		// Reset options before every hop; never retain a handle across users.
		if($this->transport === null || $this->transport_proxy !== $proxy){
			$this->transport = curl_init();
			$this->transport_proxy = $proxy;
		}else{
			curl_reset($this->transport);
		}
		return $this->transport;
	}

	private function get($proxy, $url, $get = [], $reqtype = self::req_js, $retried = false){
        search_health::check('google',$proxy);
        $started=hrtime(true);
		$remaining = $this->remaining_network_ms();
		$this->validate_request_url($url);
		
		$curlproc = $this->transport_for($proxy);
			
		if($get !== []){
			
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		// Negotiate HTTP/2 when available; no challenge bypass is performed.
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
		curl_setopt($curlproc, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
		curl_setopt($curlproc, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT_MS, min(5000, $remaining));
		curl_setopt($curlproc, CURLOPT_TIMEOUT_MS, $remaining);
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
        $retry_after='';
        curl_setopt($curlproc,CURLOPT_HEADERFUNCTION,static function($handle,$line) use(&$retry_after){
            if(str_starts_with($line,'HTTP/'))$retry_after='';
            if(stripos($line,'Retry-After:')===0)$retry_after=substr(trim(substr($line,12)),0,81);
            return strlen($line);
        });
		$data = "";
		$oversized = false;
		curl_setopt($curlproc, CURLOPT_WRITEFUNCTION, function($handle, $chunk) use (&$data, &$oversized){
			if(strlen($data) + strlen($chunk) > self::MAX_RESPONSE_BYTES){
				$oversized = true;
				return 0;
			}
			$data .= $chunk;
			return strlen($chunk);
		});
		$remaining=$this->remaining_network_ms();
		curl_setopt($curlproc,CURLOPT_CONNECTTIMEOUT_MS,min(5000,$remaining));
		curl_setopt($curlproc,CURLOPT_TIMEOUT_MS,$remaining);
		curl_exec($curlproc);
		$curl_errno = curl_errno($curlproc);
		$status = (int)curl_getinfo($curlproc, CURLINFO_RESPONSE_CODE);
        search_health::record('google','transport',$status,$curl_errno,strlen($data),(hrtime(true)-$started)/1000000);
        if($oversized) throw new upstream_search_failure('google','body_limit',$status);
        if($curl_errno!==0){
            $error=new upstream_search_failure('google','transport',$status,$curl_errno);
            search_health::remember('google',$proxy,$error);throw $error;
        }
        // Honor explicit Retry-After; transient retry never changes provider or egress.
        if(!$retried && in_array($status,[502,503,504],true) && $retry_after==='' &&
            !$this->is_google_anti_abuse_error($data) && $this->remaining_network_ms()>=1000){
            usleep(100000);return $this->get($proxy,$url,[],$reqtype,true);
        }
        if($status<200 || $status>=300){
            $error=search_health::http_failure('google',$status,$retry_after);
            search_health::remember('google',$proxy,$error);throw $error;
        }

		if(!is_string($data)){

			throw new Exception("Google returned an empty response");
		}

		return $data;
	}

	private function validate_request_url($url){
		$parts = is_string($url) ? parse_url($url) : false;
		if(!is_array($parts) || ($parts["scheme"] ?? "") !== "https" ||
			($parts["host"] ?? "") !== "cse.google.com" ||
			(isset($parts["port"]) && $parts["port"] !== 443) ||
			isset($parts["user"]) || isset($parts["pass"]) || isset($parts["fragment"]) ||
			preg_match('/[\x00-\x20\x7f]/', $url)){
			throw new Exception("Google returned an unsafe bootstrap URL.");
		}
	}

	private function decode_response($payload){
        try {return google_cse_protocol::response($payload);}
        catch(upstream_search_failure $error) {
            if($error->reason==='format' && $this->is_google_anti_abuse_error($payload)) {
                throw new upstream_search_failure('google','challenge',200);
            }
            throw $error;
        }
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

            $error_code=$json['error']['code'] ?? 0;
            // Preserve the existing one-time renewal of an explicitly expired token.
            // A refusal or rate limit is not an instruction to refresh credentials.
            $renewable=$retry_token_error && !$anti_abuse_error && $token_error &&
                preg_match('/cse[_ -]?tok|token|expired/i',$error_text);
            if(in_array($error_code,[401,403,418,429],true) &&
                !(in_array($error_code,[401,403],true) && $renewable)) {
                $failure=search_health::http_failure('google',$error_code);
                search_health::remember('google',$proxy,$failure);throw $failure;
            }
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
            $retry_code=$retry_json['error']['code'] ?? 0;
            if(in_array($retry_code,[401,403,418,429],true)) {
                $failure=search_health::http_failure('google',$retry_code);
                search_health::remember('google',$proxy,$failure);throw $failure;
            }
			if($this->is_google_anti_abuse_error($retry_error_text)){

				$this->remember_cse_request_cooldown($cooldown_key);
			}

			return $retry_json;
		}catch(Throwable $error){
            if ($error instanceof upstream_search_failure) search_health::remember('google',$proxy,$error);
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
				config::GOOGLE_CX_ENDPOINT . "\0" . serialize($proxy)
			);
	}

	private function throw_cse_request_cooldown($cooldown_key){

		if(!function_exists("apcu_fetch")){

			return;
		}

		apcu_fetch($cooldown_key, $hit);
		if($hit){

			throw new upstream_search_failure('google','challenge',200);
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
		
        $current_start=(int)($req_params['start']??0);
		
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
		
        $spelling=is_array($json['spelling']??null)?$json['spelling']:[];
        $correction=google_cse_protocol::text($spelling['correctedQuery']??null,2048);
        if(isset($spelling['type']) && is_string($spelling['type']) && $correction!=='') {
            $using=google_cse_protocol::text($spelling['originalQuery']??null,2048);
            if($using==='')$using=html_entity_decode(strip_tags(google_cse_protocol::text($spelling['originalAnchor']??$spelling['anchor']??null,4096)),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $out['spelling']=['type'=>$spelling['type']==='DYM'?'including':'not_many','using'=>$using,'correction'=>$correction];
        }
        if(!isset($json['results'])) {
            if(!is_array($json['cursor']??null))throw new upstream_search_failure('google','format',200);
            return $out;
        }
        if(!is_array($json['results']) || !array_is_list($json['results']))throw new upstream_search_failure('google','format',200);
        $seen=[];
        foreach(array_slice($json['results'],0,100) as $result) {
            $parsed=google_cse_protocol::web_result($result);
            if($parsed===null || isset($seen[$parsed['url']]))continue;
            $seen[$parsed['url']]=true;
            $out['web'][]=$parsed;
        }
        if($json['results']!==[] && $out['web']===[])throw new upstream_search_failure('google','format',200);

        $next=google_cse_protocol::next_start($json,$current_start,count($out['web']));
        if($next===null)return $out;
        $req_params['start']=$next;

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
		
        $current_start=(int)($req_params['start']??0);
		
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
		if(!isset($json["results"])){
            if(!is_array($json['cursor']??null))throw new upstream_search_failure('google','format',200);
            return $out;
        }
        if(!is_array($json['results']) || !array_is_list($json['results']))throw new upstream_search_failure('google','format',200);
		
		foreach(array_slice($json["results"],0,100) as $result){

			$parsed_result = $this->parse_image_result($result);
			if($parsed_result !== null){

				$out["image"][] = $parsed_result;
			}
		}

        if($json['results']!==[] && $out['image']===[])throw new upstream_search_failure('google','format',200);
        $next=google_cse_protocol::next_start($json,$current_start,count($out['image']));
        if($next===null)return $out;
        $req_params['start']=$next;

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
        if(!is_array($result))return null;
        $original=google_cse_protocol::url($result['unescapedUrl']??null);
        $original??=google_cse_protocol::url($result['tbLargeUrl']??null)??google_cse_protocol::url($result['tbUrl']??null);
        if($original===null)return null;
        $sources=[['url'=>$original,'width'=>google_cse_protocol::dimension($result['width']??null),'height'=>google_cse_protocol::dimension($result['height']??null)]];
        $seen=[$original=>true];
        foreach([['tbLargeUrl','tbLargeWidth','tbLargeHeight'],['tbUrl','tbWidth','tbHeight']] as [$url,$w,$h]) {
            $image=google_cse_protocol::url($result[$url]??null);
            if($image===null||isset($seen[$image]))continue;
            $seen[$image]=true;$sources[]=['url'=>$image,'width'=>google_cse_protocol::dimension($result[$w]??null),'height'=>google_cse_protocol::dimension($result[$h]??null)];
        }
        $title=google_cse_protocol::text($result['titleNoFormatting']??null,2048);
        return ['title'=>rtrim($title,' .')?:'Image result','motion_format'=>$this->motion_format_hint($result),
            'source'=>$sources,'url'=>google_cse_protocol::url($result['originalContextUrl']??null)??$original];
    }

	private function generate_token($proxy, $force_refresh = false, $rejected_token = null){
        search_health::check('google',$proxy);
		// A known provider block must not trigger another query-free bootstrap.
		$this->throw_cse_request_cooldown($this->cse_request_cooldown_key($proxy));

		$cache_key =
			"g.cse.bootstrap." .
			hash(
				"sha256",
				config::GOOGLE_CX_ENDPOINT . "\0" . (string)$proxy
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
					$this->remaining_network_ms();

					$this->throw_cached_bootstrap_failure($failure_key);
					$cached = $this->get_cached_bootstrap($cache_key);
					if(
						$cached !== null &&
						(
							!$force_refresh ||
                            (is_string($rejected_token) && !hash_equals($cached['token'],$rejected_token))
						)
					){

						return $this->return_cached_bootstrap($cached);
					}

					usleep(self::TOKEN_WAIT_USEC);
				}

				$this->throw_cached_bootstrap_failure($failure_key);
				throw new upstream_search_failure('google','busy',0,0,2);
			}

            // Recheck after lock acquisition: another worker may already have replaced
            // the specific rejected token. Never delete that newer successful generation.
            $cached=$this->get_cached_bootstrap($cache_key);
            if($cached!==null && (!$force_refresh || (is_string($rejected_token) && !hash_equals($cached['token'],$rejected_token)))) {
                $this->release_bootstrap_lock($lock_key,$flight);
                return $this->return_cached_bootstrap($cached);
            }
            if($force_refresh)apcu_delete($cache_key);

			apcu_delete($failure_key);
		}elseif($cache_available && $force_refresh){

			// APCu can be built without the atomic primitives above. Preserve the
			// previous refresh behavior instead of making caching a requirement.
			apcu_delete($cache_key);
		}

		try{
		
            // Google documents this query-free loader. Fast path avoids fetching the hosted HTML first.
            $js=$this->get($proxy,'https://cse.google.com/cse.js',['cx'=>config::GOOGLE_CX_ENDPOINT],self::req_js);
            $params=google_cse_protocol::bootstrap($js);
            if($params===null) {
                if($this->is_google_anti_abuse_error($js))throw new upstream_search_failure('google','challenge',200);
                // One format-only legacy discovery path; never retry a refusal/rate limit here.

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
		
            $js_uri=google_cse_protocol::script_path($html);
            if($js_uri===null) throw new upstream_search_failure('google','bootstrap_format',200);
            $js=$this->get($proxy,"https://cse.google.com".$js_uri,[],self::req_js);
            $params=google_cse_protocol::bootstrap($js);
            if($params===null) {
                if($this->is_google_anti_abuse_error($js)) throw new upstream_search_failure('google','challenge',200);
                throw new upstream_search_failure('google','bootstrap_format',200);
            }

            }

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
            if($error instanceof upstream_search_failure)search_health::remember('google',$proxy,$error);
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
	
}
