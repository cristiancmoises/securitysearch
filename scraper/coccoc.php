<?php

class coccoc{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("coccoc");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
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
		
		curl_setopt($curlproc, CURLOPT_HTTPHEADER, [
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"DNT: 1",
			"Sec-GPC: 1",
			"Connection: keep-alive",
			//"Cookie: _contentAB_15040_vi=V-06_01; split_test_search=new_search; uid=L_bauXyZBY1B; vid=uCVQJQSTgb9QGT3o; ls=1753742684; serp_version=29223843/7621a70; savedS=direct",
			"Upgrade-Insecure-Requests: 1",
			"Sec-Fetch-Dest: document",
			"Sec-Fetch-Mode: navigate",
			"Sec-Fetch-Site: cross-site",
			"Priority: u=0, i"
		]);
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
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
		
		return [
			"nsfw" => [
				"display" => "NSFW",
				"option" => [
					"yes" => "Yes", // nsfw by default????
					"no" => "No" // &safe=1
				]
			],
			"time" => [
				"display" => "Time posted",
				"option" => [
					"any" => "Any time",
					"1w" => "1 week ago",
					"2w" => "2 weeks ago",
					"1m" => "1 month ago",
					"3m" => "3 months ago",
					"6m" => "6 months ago",
					"1Y" => "1 year ago"
				]
			],
			"filter" => [
				"display" => "Remove duplicates",
				"option" => [
					"no" => "No",
					"yes" => "Yes" // &filter=0
				]
			]
		];
	}
	
	public function web($get){
		
		if($get["npt"]){
			
			[$query, $proxy] =
				$this->backend->get(
					$get["npt"],
					"web"
				);
			
			$query = json_decode($query, true);
		}else{
			
			$proxy = $this->backend->get_ip();
			
			$query = [
				"query" => $get["s"]
			];
			
			// add filters
			if($get["nsfw"] == "no"){
				
				$query["safe"] = 1;
			}
			
			if($get["time"] != "any"){
				
				$query["tbs"] = $get["time"];
			}
			
			if($get["filter"] == "yes"){
				
				$query["filter"] = 0;
			}
		}
		
		try{
			
			$html =
				$this->get(
					$proxy,
					"https://coccoc.com/search",
					$query
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to get search page");
		}
		//$html = file_get_contents("scraper/coccoc.html");
		
		
		$html = explode("window.composerResponse", $html, 2);
		
		if(count($html) !== 2){
			
			throw new Exception("Failed to grep window.composerResponse");
		}
		
		$html =
			json_decode(
				$this->fuckhtml
				->extract_json(
					ltrim($html[1], " =")
				),
				true
			);
		
		if($html === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		if(
			isset($html["captcha"]) &&
			(int)$html["captcha"] === 1
		){
			
			throw new Exception("Coc Coc returned a Captcha");
		}
		
		if(!isset($html["search"]["search_results"])){
			
			throw new Exception("Coc Coc did not return a search_results object");
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
		
		// word correction
		foreach($html["top"] as $element){
			
			if(isset($element["spellChecker"][0]["query"])){
				
				$out["spelling"] = [
					"type" => "not_many",
					"using" => $html["search"]["query"],
					"correction" => $element["spellChecker"][0]["query"]
				];
			}
		}
		
		foreach($html["search"]["search_results"] as $result){
			
			if(isset($result["type"])){
				
				switch($result["type"]){
					
					//
					// Related searches
					//
					case "related_queries":
						$out["related"] = $result["queries"];
						continue 2;
					
					//
					// Videos
					//
					case "video_hits":
						foreach($result["results"] as $video){
							
							if(
								isset($video["image_url"]) &&
								!empty($video["image_url"])
							){
								
								$thumb = [
									"ratio" => "16:9",
									"url" => $video["image_url"]
								];
							}else{
								
								$thumb = [
									"ratio" => null,
									"url" => null
								];
							}
							
							$out["video"][] = [
								"title" =>
									$this->titledots(
										$this->fuckhtml
										->getTextContent(
											$video["title"]
										)
									),
								"description" => null,
								"author" => [
									"name" => $video["uploader"],
									"url" => null,
									"avatar" => null
								],
								"date" => (int)$video["date"],
								"duration" => (int)$video["duration"],
								"views" => null,
								"thumb" => $thumb,
								"url" => $video["url"]
							];
						}
						continue 2;
				}
			}
			
			if(
				!isset($result["title"]) ||
				!isset($result["url"])
			){
				
				// should not happen
				continue;
			}
			
			if(isset($result["rich"]["data"]["image_url"])){
				
				$thumb = [
					"url" => $result["rich"]["data"]["image_url"],
					"ratio" => "16:9"
				];
			}else{
				
				$thumb = [
					"url" => null,
					"ratio" => null
				];
			}
			
			$sublinks = [];
			
			if(isset($result["rich"]["data"]["linked_docs"])){
				
				foreach($result["rich"]["data"]["linked_docs"] as $sub){
					
					$sublinks[] = [
						"title" =>
							$this->titledots(
								$this->fuckhtml
								->getTextContent(
									$sub["title"]
								)
							),
						"description" =>
							$this->titledots(
								$this->fuckhtml
								->getTextContent(
									$sub["content"]
								)
							),
						"date" => null,
						"url" => $sub["url"]
					];
				}
			}
			
			// get date
			if(isset($result["date"])){
				
				$date = (int)$result["date"];
			}else{
				
				$date = null;
			}
			
			// probe for metadata
			$table = [];
			
			if(isset($result["rich"]["data"]["rating"])){
				
				$table["Rating"] = $result["rich"]["data"]["rating"];
				
				if(isset($result["rich"]["data"]["num_rating"])){
					
					$table["Rating"] .= " (" . number_format($result["rich"]["data"]["num_rating"]) . " ratings)";
				}
			}
			
			if(isset($result["rich"]["data"]["views"])){
				
				$table["Views"] = number_format($result["rich"]["data"]["views"]);
			}
			
			if(isset($result["rich"]["data"]["duration"])){
				
				$table["Duration"] = $this->int2hms($result["rich"]["data"]["duration"]);
			}
			
			if(isset($result["rich"]["data"]["channel_name"])){
				
				$table["Author"] = $result["rich"]["data"]["channel_name"];
			}
			
			if(isset($result["rich"]["data"]["video_quality"])){
				
				$table["Quality"] = $result["rich"]["data"]["video_quality"];
			}
			
			if(isset($result["rich"]["data"]["category"])){
				
				$table["Category"] = $result["rich"]["data"]["category"];
			}
			
			$out["web"][] = [
				"title" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$result["title"]
						)
					),
				"description" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$result["content"]
						)
					),
				"url" => $result["url"],
				"date" => $date,
				"type" => "web",
				"thumb" => $thumb,
				"sublink" => $sublinks,
				"table" => $table
			];
		}
		
		//
		// Get wikipedia head
		//
		if(isset($html["right"])){
			
			foreach($html["right"] as $wiki){
				
				$description = [];
				
				if(isset($wiki["short_intro"])){
					
					$description[] =
						[
							"type" => "quote",
							"value" => $wiki["short_intro"],
						];
				}
				
				if(isset($wiki["intro"])){
					
					$description[] =
						[
							"type" => "text",
							"value" => $wiki["intro"],
						];
				}
				
				// get table elements
				$table = [];
				
				if(isset($wiki["fields"])){
					
					foreach($wiki["fields"] as $element){
						
						$table[$element["title"]] = implode(", ", $element["value"]);
					}
				}
				
				// get sublinks
				$sublinks = [];
				
				if(isset($wiki["website"])){
					
					if(
						preg_match(
							'/^http/',
							$wiki["website"]
						) === 0
					){
						
						$sublinks["Website"] = "https://" . $wiki["website"];
					}else{
						
						$sublinks["Website"] = $wiki["website"];
					}
				}
				
				foreach($wiki["profiles"] as $sitename => $url){
					
					$sitename = explode("_", $sitename);
					$sitename = ucfirst($sitename[count($sitename) - 1]);
					
					$sublinks[$sitename] = $url;
				}
				
				$out["answer"][] = [
					"title" =>
						$this->titledots(
							$wiki["title"]
						),
					"description" => $description,
					"url" => null,
					"thumb" => isset($wiki["image"]["contentUrl"]) ? $wiki["image"]["contentUrl"] : null,
					"table" => $table,
					"sublink" => $sublinks
				];
			}
		}
		
		// get next page
		if((int)$html["search"]["page"] < (int)$html["search"]["max_page"]){
			
			// https://coccoc.com/composer?_=1754021153532&p=0&q=zbabduiqwhduwqhdnwq&reqid=bwcAs00q&s=direct&apiV=1
			// ^json endpoint, but we can just do &page=2 lol
			
			if(!isset($query["page"])){
				
				$query["page"] = 2;
			}else{
				
				$query["page"]++;
			}
			
			$out["npt"] =
				$this->backend
				->store(
					json_encode($query),
					"web",
					$proxy
				);
		}
		
		return $out;
	}
	
	public function video($get){
		
		//$html = file_get_contents("scraper/coccoc.html");
		if($get["npt"]){
			
			[$query, $proxy] =
				$this->backend->get(
					$get["npt"],
					"videos"
				);
			
			$query = json_decode($query, true);
		}else{
			
			$proxy = $this->backend->get_ip();
			
			$query = [
				"query" => $get["s"],
				"tbm" => "vid"
			];
			
			// add filters
			if($get["nsfw"] == "no"){
				
				$query["safe"] = 1;
			}
			
			if($get["time"] != "any"){
				
				$query["tbs"] = $get["time"];
			}
			
			if($get["filter"] == "yes"){
				
				$query["filter"] = 0;
			}
		}
		
		try{
			
			$html =
				$this->get(
					$proxy,
					"https://coccoc.com/search",
					$query
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to get search page");
		}
		
		$html = explode("window.composerResponse", $html, 2);
		
		if(count($html) !== 2){
			
			throw new Exception("Failed to grep window.composerResponse");
		}
		
		$html =
			json_decode(
				$this->fuckhtml
				->extract_json(
					ltrim($html[1], " =")
				),
				true
			);
		
		if($html === null){
			
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
		
		if(!isset($html["search_video"]["search_results"])){
			
			if(isset($html["search_video"]["error"]["title"])){
				
				if($html["search_video"]["error"]["title"] == "Không tìm thấy kết quả nào"){
					
					return $out;
				}
				
				throw new Exception("Coc Coc returned an error: " . $html["search_video"]["error"]["title"]);
			}
			
			throw new Exception("Coc Coc did not supply a search_results object");
		}
		
		foreach($html["search_video"]["search_results"] as $video){
			
			if(isset($video["rich"]["data"]["image_url"])){
				
				$thumb = [
					"ratio" => "16:9",
					"url" => $video["rich"]["data"]["image_url"]
				];
			}else{
				
				$thumb = [
					"ratio" => null,
					"url" => null
				];
			}
			
			$out["video"][] = [
				"title" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$video["title"]
						)
					),
				"description" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$video["content"]
						)
					),
				"author" => [
					"name" => 
						isset($video["rich"]["data"]["channel_name"]) ?
							$video["rich"]["data"]["channel_name"] : null,
					"url" => null,
					"avatar" => null
				],
				"date" =>
					isset($video["date"]) ?
						$video["date"] : null,
				"duration" =>
					isset($video["rich"]["data"]["duration"]) ?
						(int)$video["rich"]["data"]["duration"] : null,
				"views" => null,
				"thumb" => $thumb,
				"url" => $video["url"]
			];
		}
		
		// get next page
		if((int)$html["search_video"]["page"] < (int)$html["search_video"]["max_page"]){
			
			if(!isset($query["page"])){
				
				$query["page"] = 2;
			}else{
				
				$query["page"]++;
			}
			
			$out["npt"] =
				$this->backend
				->store(
					json_encode($query),
					"videos",
					$proxy
				);
		}
		
		return $out;
	}
	
	private function titledots($title){
		
		return trim($title, " .\t\n\r\0\x0B…");
	}
	
	private function int2hms($seconds){
		
		$hours = floor($seconds / 3600);
		$minutes = floor(($seconds % 3600) / 60);
		$seconds = $seconds % 60;
		
		return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
	}
}
