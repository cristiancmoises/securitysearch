<?php

class sepiasearch{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("sepiasearch");
	}
	
	public function getfilters($page){
		
		return [
			"nsfw" => [
				"display" => "NSFW",
				"option" => [
					"yes" => "Yes", // &sensitiveContent=both
					"no" => "No" // &sensitiveContent=false
				]
			],
			"language" => [
				"display" => "Language", // &language=
				"option" => [
					"any" => "Any language",
					"en" => "English",
					"fr" => "Français",
					"ar" => "العربية",
					"ca" => "Català",
					"cs" => "Čeština",
					"de" => "Deutsch",
					"el" => "ελληνικά",
					"eo" => "Esperanto",
					"es" => "Español",
					"eu" => "Euskara",
					"fa" => "فارسی",
					"fi" => "Suomi",
					"gd" => "Gàidhlig",
					"gl" => "Galego",
					"hr" => "Hrvatski",
					"hu" => "Magyar",
					"is" => "Íslenska",
					"it" => "Italiano",
					"ja" => "日本語",
					"kab" => "Taqbaylit",
					"nl" => "Nederlands",
					"no" => "Norsk",
					"oc" => "Occitan",
					"pl" => "Polski",
					"pt" => "Português (Brasil)",
					"pt-PT" => "Português (Portugal)",
					"ru" => "Pусский",
					"sk" => "Slovenčina",
					"sq" => "Shqip",
					"sv" => "Svenska",
					"th" => "ไทย",
					"tok" => "Toki Pona",
					"tr" => "Türkçe",
					"uk" => "украї́нська мо́ва",
					"vi" => "Tiếng Việt",
					"zh-Hans" => "简体中文（中国）",
					"zh-Hant" => "繁體中文（台灣）"
				]
			],
			"type" => [
				"display" => "Result type", // i handle this
				"option" => [
					"videos" => "Videos",
					"playlists" => "Playlists",
					"channels" => "Channels"
				]
			],
			"sort" => [
				"display" => "Sort by",
				"option" => [
					"best" => "Best match", // no filter
					"-publishedAt" => "Newest", // sort=-publishedAt
					"publishedAt" => "Oldest" // sort=publishedAt
				]
			],
			"newer" => [ // &startDate=2025-07-26T04:00:00.000Z
				"display" => "Newer than",
				"option" => "_DATE"
			],
			"duration" => [
				"display" => "Duration",
				"option" => [
					"any" => "Any duration",
					"short" => "Short (0-4mins)", // &durationRange=short
					"medium" => "Medium (4-10 mins)",
					"long" => "Long (10+ mins)",
				]
			],
			"category" => [
				"display" => "Category", // &categoryOneOf[]=
				"option" => [
					"any" => "Any category",
					"1" => "Music",
					"2" => "Films",
					"3" => "Vehicles",
					"4" => "Art",
					"5" => "Sports",
					"6" => "Travels",
					"7" => "Gaming",
					"8" => "People",
					"9" => "Comedy",
					"10" => "Entertainment",
					"11" => "News & Politics",
					"12" => "How To",
					"13" => "Education",
					"14" => "Activism",
					"15" => "Science & Technology",
					"16" => "Animals",
					"17" => "Kids",
					"18" => "Food"
				]
			],
			"display" => [
				"display" => "Display",
				"option" => [
					"any" => "Everything",
					"true" => "Live videos", // &isLive=true
					"false" => "VODs" // &isLive=false
				]
			],
			"license" => [
				"display" => "License", // &license=
				"option" => [
					"any" => "Any license",
					"1" => "Attribution",
					"2" => "Attribution - Share Alike",
					"3" => "Attribution - No Derivatives",
					"4" => "Attribution - Non Commercial",
					"5" => "Attribution - Non Commercial - Share Alike",
					"6" => "Attribution - Non Commercial - No Derivatives",
					"7" => "Public Domain Dedication"
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
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		curl_setopt(
			$curlproc,
			CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: application/json, text/plain, */*",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"DNT: 1",
			"Sec-GPC: 1",
			"Connection: keep-alive",
			"Referer: https://sepiasearch.org/search",
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
	
	public function video($get){
		
		if($get["npt"]){
			
			[$npt, $proxy] =
				$this->backend
				->get(
					$get["npt"],
					"videos"
				);
			
			$npt = json_decode($npt, true);
			$type = $npt["type"];
			$npt = $npt["npt"];
		}else{
			
			$proxy = $this->backend->get_ip();
						
			$npt = [
				"search" => $get["s"],
				"start" => 0,
				"count" => 20
			];
			
			if($get["type"] == "videos"){
				
				//
				// Parse video filters
				//
				switch($get["nsfw"]){
					
					case "yes": $npt["nsfw"] = "both"; break;
					case "no": $npt["nsfw"] = "false"; break;
				}
				
				$npt["boostLanguages[]"] = "en";
				if($get["language"] != "any"){
					
					$npt["languageOneOf[]"] = $get["language"];
				}
				
				if($get["sort"] != "best"){
					
					$npt["sort"] = $get["sort"];
				}
				
				if($get["newer"] !== false){
					
					$date = new DateTime("@{$get["newer"]}");
					$date->setTimezone(new DateTimeZone("UTC"));
					$formatted = $date->format("Y-m-d\TH:i:s.000\Z");
					
					$npt["startDate"] = $formatted;
				}
				
				switch($get["duration"]){
					
					case "short":
						$npt["durationMax"] = 240;
						break;
					
					case "medium":
						$npt["durationMin"] = 240;
						$npt["durationMax"] = 600;
						break;
					
					case "long":
						$npt["durationMin"] = 600;
						break;
				}
				
				if($get["category"] != "any"){
					
					$npt["categoryOneOf[]"] = $get["category"];
				}
				
				if($get["display"] != "any"){
					
					$npt["isLive"] = $get["display"];
				}
				
				if($get["license"] != "any"){
					
					// typo in license, lol
					$npt["licenceOneOf[]"] = $get["license"];
				}
			}
			
			$type = $get["type"];
		}
		
		switch($type){
			
			case "videos":
				$url = "https://sepiasearch.org/api/v1/search/videos";
				break;
			
			case "channels":
				$url = "https://sepiasearch.org/api/v1/search/video-channels";
				break;
			
			case "playlists":
				$url = "https://sepiasearch.org/api/v1/search/video-playlists";
				break;
		}
		
		//$json = file_get_contents("scraper/sepia.json");
		try{
			
			$json =
				$this->get(
					$proxy,
					$url,
					$npt
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to parse JSON");
		}
		
		if(isset($json["errors"])){
			
			$msg = [];
			foreach($json["errors"] as $error){
				
				if(isset($error["msg"])){
					
					$msg[] = $error["msg"];
				}
			}
			
			throw new Exception("Sepia Search returned error(s): " . implode(", ", $msg));
		}
		
		if(!isset($json["data"])){
			
			throw new Exception("Sepia Search did not return a data object");
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
		
		
		switch($get["type"]){
			
			case "videos":
				foreach($json["data"] as $video){
					
					if(count($video["account"]["avatars"]) !== 0){
						
						$avatar =
							$video["account"]["avatars"][count($video["account"]["avatars"]) - 1]["url"];
					}else{
						
						$avatar = null;
					}
					
					if($video["thumbnailUrl"] === null){
						
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}else{
						
						$thumb  = [
							"ratio" => "16:9",
							"url" => $video["thumbnailUrl"]
						];
					}
					
					if($video["isLive"]){
						
						$append = "livestream";
					}else{
						
						$append = "video";
					}
					
					$out[$append][] = [
						"title" => $video["name"],
						"description" =>
							$this->limitstrlen(
								$this->titledots(
									$video["description"]
								)
							),
						"author" => [
							"name" => $video["account"]["displayName"] . " ({$video["account"]["name"]})",
							"url" => $video["account"]["url"],
							"avatar" => $avatar
						],
						"date" => strtotime($video["publishedAt"]),
						"duration" => $video["isLive"] ? "_LIVE" : $video["duration"],
						"views" => $video["views"],
						"thumb" => $thumb,
						"url" => $video["url"]
					];
				}
				break;
			
			case "playlists":
				foreach($json["data"] as $playlist){
					
					if(count($playlist["ownerAccount"]["avatars"]) !== 0){
						
						$avatar =
							$playlist["ownerAccount"]["avatars"][count($playlist["ownerAccount"]["avatars"]) - 1]["url"];
					}else{
						
						$avatar = null;
					}
					
					if($playlist["thumbnailUrl"] === null){
						
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}else{
						
						$thumb  = [
							"ratio" => "16:9",
							"url" => $playlist["thumbnailUrl"]
						];
					}
					
					$out["playlist"][] = [
						"title" => $playlist["displayName"],
						"description" =>
							$this->limitstrlen(
								$this->titledots(
									$playlist["description"]
								)
							),
						"author" => [
							"name" => $playlist["ownerAccount"]["displayName"] . " ({$playlist["ownerAccount"]["name"]})",
							"url" => $playlist["ownerAccount"]["url"],
							"avatar" => $avatar
						],
						"date" => strtotime($playlist["createdAt"]),
						"duration" => $playlist["videosLength"],
						"views" => null,
						"thumb" => $thumb,
						"url" => $playlist["url"]
					];
				}
				break;
			
			case "channels":
				foreach($json["data"] as $channel){
					
					if(count($channel["avatars"]) !== 0){
						
						$thumb = [
							"ratio" => "1:1",
							"url" => $channel["avatars"][count($channel["avatars"]) - 1]["url"]
						];
					}else{
						
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}
					
					$out["author"][] = [
						"title" => $channel["displayName"] . " ({$channel["name"]})",
						"followers" => $channel["followersCount"],
						"description" =>
							$channel["videosCount"] . " videos. " .
							$this->limitstrlen(
								$this->titledots(
									$channel["description"]
								)
							),
						"thumb" => $thumb,
						"url" => $channel["url"]
					];
				}
				break;
		}
		
		// get next page
		if($json["total"] - 20 > $npt["start"]){
			
			$npt["start"] += 20;
			
			$npt = [
				"type" => $get["type"],
				"npt" => $npt
			];
			
			$out["npt"] =
				$this->backend
				->store(
					json_encode($npt),
					"videos",
					$proxy
				);
		}
		
		return $out;
	}
	
	private function titledots($title){
		
		$substr = substr($title, -3);
		
		if(
			$substr == "..." ||
			$substr == "…"
		){
			
			return trim(substr($title, 0, -3), " \n\r\t\v\x00\0\x0B\xc2\xa0");
		}
		
		return trim($title, " \n\r\t\v\x00\0\x0B\xc2\xa0");
	}
	
	private function limitstrlen($text){
		
		return
			explode(
				"\n",
				wordwrap(
					str_replace(
						["\n\r", "\r\n", "\n", "\r"],
						" ",
						$text
					),
					300,
					"\n"
				),
				2
			)[0];
	}
}
