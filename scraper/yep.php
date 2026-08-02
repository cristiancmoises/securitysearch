<?php

class yep{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("yep");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function getfilters($page){
		
		return [
			"lang" => [
				"display" => "Language",
				"option" => [
					"any" => "Any language",
					"aa" => "Afar",
					"ab" => "Abkhazian",
					"ae" => "Avestan",
					"af" => "Afrikaans",
					"ak" => "Akan",
					"am" => "Amharic",
					"an" => "Aragonese",
					"ar" => "Arabic",
					"as" => "Assamese",
					"av" => "Avaric",
					"ay" => "Aymara",
					"az" => "Azerbaijani",
					"ba" => "Bashkir",
					"be" => "Belarusian",
					"bg" => "Bulgarian",
					"bh" => "Bihari",
					"bi" => "Bislama",
					"bm" => "Bambara",
					"bn" => "Bengali",
					"bo" => "Tibetan",
					"br" => "Breton",
					"bs" => "Bosnian",
					"ca" => "Catalan",
					"ce" => "Chechen",
					"ch" => "Chamorro",
					"co" => "Corsican",
					"cr" => "Cree",
					"cs" => "Czech",
					"cu" => "Church Slavic",
					"cv" => "Chuvash",
					"cy" => "Welsh",
					"da" => "Danish",
					"de" => "German",
					"dv" => "Divehi",
					"dz" => "Dzongkha",
					"ee" => "Ewe",
					"el" => "Greek",
					"en" => "English",
					"eo" => "Esperanto",
					"es" => "Spanish",
					"et" => "Estonian",
					"eu" => "Basque",
					"fa" => "Persian",
					"ff" => "Fulah",
					"fi" => "Finnish",
					"fj" => "Fijian",
					"fo" => "Faroese",
					"fr" => "French",
					"fy" => "Western Frisian",
					"ga" => "Irish",
					"gd" => "Scottish Gaelic",
					"gl" => "Galician",
					"gn" => "Guarani",
					"gu" => "Gujarati",
					"gv" => "Manx",
					"ha" => "Hausa",
					"he" => "Hebrew",
					"hi" => "Hindi",
					"ho" => "Hiri Motu",
					"hr" => "Croatian",
					"ht" => "Haitian",
					"hu" => "Hungarian",
					"hy" => "Armenian",
					"hz" => "Herero",
					"ia" => "Interlingua",
					"id" => "Indonesian",
					"ie" => "Interlingue",
					"ig" => "Igbo",
					"ii" => "Sichuan Yi",
					"ik" => "Inupiaq",
					"io" => "Ido",
					"is" => "Icelandic",
					"it" => "Italian",
					"iu" => "Inuktitut",
					"ja" => "Japanese",
					"jv" => "Javanese",
					"ka" => "Georgian",
					"kg" => "Kongo",
					"ki" => "Kikuyu",
					"kj" => "Kuanyama",
					"kk" => "Kazakh",
					"kl" => "Kalaallisut",
					"km" => "Central Khmer",
					"kn" => "Kannada",
					"ko" => "Korean",
					"kr" => "Kanuri",
					"ks" => "Kashmiri",
					"ku" => "Kurdish",
					"kv" => "Komi",
					"kw" => "Cornish",
					"ky" => "Kyrgyz",
					"la" => "Latin",
					"lb" => "Luxembourgish",
					"lg" => "Ganda",
					"li" => "Limburgish",
					"ln" => "Lingala",
					"lo" => "Lao",
					"lt" => "Lithuanian",
					"lu" => "Luba-Katanga",
					"lv" => "Latvian",
					"mg" => "Malagasy",
					"mh" => "Marshallese",
					"mi" => "Maori",
					"mk" => "Macedonian",
					"ml" => "Malayalam",
					"mn" => "Mongolian",
					"mr" => "Marathi",
					"ms" => "Malay",
					"mt" => "Maltese",
					"my" => "Burmese",
					"na" => "Nauru",
					"nb" => "Norwegian Bokmål",
					"nd" => "North Ndebele",
					"ne" => "Nepali",
					"ng" => "Ndonga",
					"nl" => "Dutch",
					"nn" => "Norwegian Nynorsk",
					"no" => "Norwegian",
					"nr" => "South Ndebele",
					"nv" => "Navajo",
					"ny" => "Chichewa",
					"oc" => "Occitan",
					"oj" => "Ojibwa",
					"om" => "Oromo",
					"or" => "Oriya",
					"os" => "Ossetian",
					"pa" => "Punjabi",
					"pi" => "Pali",
					"pl" => "Polish",
					"ps" => "Pashto",
					"pt" => "Portuguese",
					"qu" => "Quechua",
					"rm" => "Romansh",
					"rn" => "Rundi",
					"ro" => "Romanian",
					"ru" => "Russian",
					"rw" => "Kinyarwanda",
					"sa" => "Sanskrit",
					"sc" => "Sardinian",
					"sd" => "Sindhi",
					"se" => "Northern Sami",
					"sg" => "Sango",
					"si" => "Sinhala",
					"sk" => "Slovak",
					"sl" => "Slovenian",
					"sm" => "Samoan",
					"sn" => "Shona",
					"so" => "Somali",
					"sq" => "Albanian",
					"sr" => "Serbian",
					"ss" => "Swati",
					"st" => "Southern Sotho",
					"su" => "Sundanese",
					"sv" => "Swedish",
					"sw" => "Swahili",
					"ta" => "Tamil",
					"te" => "Telugu",
					"tg" => "Tajik",
					"th" => "Thai",
					"ti" => "Tigrinya",
					"tk" => "Turkmen",
					"tl" => "Tagalog",
					"tn" => "Tswana",
					"to" => "Tonga",
					"tr" => "Turkish",
					"ts" => "Tsonga",
					"tt" => "Tatar",
					"tw" => "Twi",
					"ty" => "Tahitian",
					"ug" => "Uyghur",
					"uk" => "Ukrainian",
					"ur" => "Urdu",
					"uz" => "Uzbek",
					"ve" => "Venda",
					"vi" => "Vietnamese",
					"vo" => "Volapük",
					"wa" => "Walloon",
					"wo" => "Wolof",
					"xh" => "Xhosa",
					"yi" => "Yiddish",
					"yo" => "Yoruba",
					"za" => "Zhuang",
					"zh" => "Chinese",
					"zh-cn" => "Chinese (Simplified)",
					"zh-tw" => "Chinese (Traditional)",
					"zu" => "Zulu"
				]
			],
			"nsfw" => [
				"display" => "NSFW",
				"option" => [
					"yes" => "Yes",
					"no" => "No"
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
		
		// use http2
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: */*",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"Referer: https://yep.com/",
			"Origin: https://yep.com",
			"DNT: 1",
			"Connection: keep-alive",
			"Sec-Fetch-Dest: empty",
			"Sec-Fetch-Mode: cors",
			"Sec-Fetch-Site: same-site",
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
	
	
	
	public function web($get){
		
		$search = $get["s"];
		if(strlen($search) === 0){
			
			throw new Exception("Search term is empty!");
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
		
		// parse filters
		$filters = [
			"limit" => 100, // wwwwwwwwwwwwwww
			"query" => $search,
		];
		
		if($get["nsfw"] == "no"){ $filters["safeSearch"] = "moderate"; }
		if($get["lang"] != "any"){ $filters["hl"] = $get["lang"]; }
		
		try{
			
			// https://api.yep.com/fs/2/search?limit=20&query=asmr
			$json =
				$this->get(
					$this->backend->get_ip(),
					"https://api.yep.com/fs/2/search",
					$filters
				);
			
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$this->detect_cf($json);
		
		$json = json_decode($json, true);
		//$json = json_decode(file_get_contents("scraper/yep.json"), true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		if(isset($json[1]["correction"])){
			
			$out["spelling"] = [
				"type" => "not_many",
				"using" => $search,
				"correction" => $json[1]["correction"][1]
			];
		}
		
		if(isset($json[1]["results"])){
			foreach($json[1]["results"] as $item){
				
				switch(strtolower($item["type"])){
					
					case "organic":
						$sublinks = [];
						
						if(isset($item["sitelinks"]["full"])){
							
							foreach($item["sitelinks"]["full"] as $link){
								
								$sublinks[] = [
									"title" => $link["title"],
									"date" => null,
									"description" =>
										$this->titledots(
											strip_tags(
												html_entity_decode(
													$link["snippet"]
												)
											)
										),
									"url" => $link["url"]
								];
							}
						}
						
						$out["web"][] = [
							"title" => $item["title"],
							"description" =>
								$this->titledots(
									strip_tags(
										html_entity_decode(
											$item["snippet"]
										)
									)
								),
							"url" => $item["url"],
							"date" => null,
							"type" => "web",
							"thumb" => [
								"url" => null,
								"ratio" => null
							],
							"sublink" => $sublinks,
							"table" => []
						];
						break;
				}
			}
		}
		
		return $out;
	}
	
	
	private function detect_cf($payload){
		
		// detect cloudflare page
		$this->fuckhtml->load($payload);
		
		if(
			count(
				$this->fuckhtml
				->getElementsByClassName(
					"cf-wrapper",
					"div"
				)
			) !== 0
		){
			
			throw new Exception("Blocked by Cloudflare. Please follow curl-impersonate installation instructions");
		}
	}
	
	
	private function titledots($title){
		
		$substr = substr($title, -4);
		
		if(
			strpos($substr, "...") !== false ||
			strpos($substr, "…") !== false
		){
						
			return trim(substr($title, 0, -4));
		}
		
		return trim($title);
	}
	
	private function unshiturl($url){
		
		$newurl = parse_url($url, PHP_URL_QUERY);
		parse_str($newurl, $newurl);
		
		if(isset($newurl["url"])){
			
			return $newurl["url"];
		}
		
		return $url;
	}
}
