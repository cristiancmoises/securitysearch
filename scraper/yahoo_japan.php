<?php

class yahoo_japan{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("yahoo_japan");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function getfilters($page){
		
		return [];
	}
	
	private function get($proxy, $url, $get = [], $return_cookies = false, $is_xhr = false, $cookie = null){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		// http2 bypass
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		if($cookie !== null){
			
			$c = [];
			foreach($cookie as $name => $value){
				
				$c[] = "{$name}=$value";
			}
			
			$cookie = implode("; ", $c);
		}
		
		if($is_xhr){
			
			curl_setopt($curlproc, CURLOPT_HTTPHEADER,
				["User-Agent: " . config::USER_AGENT,
				"Accept: application/json, text/plain, */*",
				"Accept-Language: en-US,en;q=0.5",
				"Accept-Encoding: gzip",
				"Referer: https://search.yahoo.co.jp/",
				"DNT: 1",
				"Sec-GPC: 1",
				"Connection: keep-alive",
				"Cookie: " . $cookie,
				"Sec-Fetch-Dest: empty",
				"Sec-Fetch-Mode: cors",
				"Sec-Fetch-Site: same-origin",
				"TE: trailers"]
			);
		}else{
			
			curl_setopt($curlproc, CURLOPT_HTTPHEADER,
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
				"TE: trailers"]
			);
		}
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		if($return_cookies){
			
			// extract cookies
			$cookies_tmp = [];
			curl_setopt($curlproc, CURLOPT_HEADERFUNCTION, function($curlproc, $header) use (&$cookies_tmp){
				
				$length = strlen($header);
				
				$header = explode(":", $header, 2);
				
				if(trim(strtolower($header[0])) == "set-cookie"){
					
					$cookie_tmp = explode("=", trim($header[1]), 2);
					
					$cookies_tmp[trim($cookie_tmp[0])] =
						explode(";", $cookie_tmp[1], 2)[0];
				}
				
				return $length;
			});

		}
		
		$data = curl_exec($curlproc);
		
		if(curl_errno($curlproc)){
			
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		
		if($return_cookies){
			
			return [
				"cookies" => $cookies_tmp,
				"body" => $data
			];
		}
			
		return $data;
	}
	
	public function web($get){
		
		if($get["npt"]){
			
			[$url, $proxy] = $this->backend->get($get["npt"], "web");
			$params = [];
			
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$url = "https://search.yahoo.co.jp/search";
			$params = [
				"p" => $get["s"]
			];
		}
		
		try{
			$html = $this->get(
				$proxy,
				$url,
				$params
			);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch search page");
		}
		//$html = file_get_contents("scraper/yahoo_japan.html");
		
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
		
		$json_object =
			explode(
				'<script id="__NEXT_DATA__" type="application/json">',
				$html
			);
		
		if(count($json_object) !== 2){
			
			throw new Exception("Failed to find JSON script");
		}
		
		$json =
			json_decode(
				$this->fuckhtml
				->extract_json(
					$json_object[1]
				),
				true
			);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		//print_r($json);
		
		//
		// Extract mainline search results
		//
		if(!isset($json["props"]["pageProps"]["initialProps"]["pageData"]["algos"])){
			
			throw new Exception("Failed to access algos object");
		}
		
		foreach($json["props"]["pageProps"]["initialProps"]["pageData"]["algos"] as $result){
			
			switch($result["type"]){
				
				case "Algo":
					if(isset($result["visualWebImageGallery"]["imageThumbs"][0]["source"])){
						
						$thumb = [
							"ratio" => "1:1",
							"url" => $result["visualWebImageGallery"]["imageThumbs"][0]["source"]
						];
					}elseif(isset($result["visualWebImageSnippet"])){
						
						$thumb = [
							"ratio" => "1:1",
							"url" => $result["visualWebImageSnippet"]
						];
					}else{
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}
					
					$sublinks = [];
					
					if(isset($result["megaSiteSubLinks"]["mssl"])){
						
						foreach($result["megaSiteSubLinks"]["mssl"] as $sublink){
							
							$sublinks[] = [
								"title" =>
									$this->titledots(
										$this->fuckhtml
										->getTextContent(
											$sublink["title"]
										)
									),
								"description" =>
									$this->titledots(
										html_entity_decode(
											$this->fuckhtml
											->getTextContent(
												$sublink["description"]
											)
										)
									),
								"url" => $sublink["url"],
								"date" => null
							];
						}
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
								html_entity_decode(
									$this->fuckhtml
									->getTextContent(
										$result["description"]
									)
								)
							),
						"url" => $result["url"],
						"date" => isset($result["bylinedate"]) ? (int)$result["bylinedate"] : null,
						"type" => "web",
						"thumb" => $thumb,
						"sublink" => $sublinks,
						"table" => []
					];
					
					if(isset($result["anotherSuggest"]["exploreQueries"])){
						
						foreach($result["anotherSuggest"]["exploreQueries"] as $query){
							
							$out["related"][] = $query["query"];
						}
					}
					break;
			}
		}
		
		//
		// Extract extras from "shortcuts"
		//
		foreach($json["props"]["pageProps"]["initialProps"]["pageData"]["shortcuts"] as $shortcut_wrap){
			
			foreach($shortcut_wrap as $shortcut){
				
				switch($shortcut["type"]){
					//
					// Scrape videos
					//
					case "GoogleVideoUniversalShortcut":
						foreach($shortcut["videos"] as $video){
							
							if(isset($video["thumbnailUrl"])){
								
								$thumb = [
									"ratio" => "16:9",
									"url" => $video["thumbnailUrl"]
								];
							}else{
														
								$thumb = [
									"ratio" => null,
									"url" => null
								];
							}
							
							if(isset($video["publishedDate"])){
								
								$date = strtotime($video["publishedDate"]);
								
								if($date === false){
									
									$date = null;
								}
							}else{
								
								$date = null;
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
								"date" => $date,
								"duration" =>
									isset($video["duration"]) ?
									$this->hms2int($video["duration"]) : null,
								"views" => null,
								"thumb" => $thumb,
								"url" => $video["url"]
							];
						}
						break;
					
					//
					// Scrape images
					//
					case "ImageShortcut":
						foreach($shortcut["images"] as $image_cat){
							
							foreach($image_cat as $image){
								
								$ratio =
									$this->yahooratio(
										(int)$image["originalImageWidth"],
										(int)$image["originalImageHeight"]
									);
								
								$out["image"][] = [
									"title" =>
										$this->titledots(
											$this->fuckhtml
											->getTextContent(
												$image["title"]
											)
										),
									"source" =>	[
										[
											"url" => $image["originalImageUrl"],
											"width" => (int)$image["originalImageWidth"],
											"height" => (int)$image["originalImageHeight"]
										],
										[
											"url" => $image["thumbnailUrl"],
											"width" => $ratio[0],
											"height" => $ratio[1]
										]
									],
									"url" => $image["referrerUrl"]
								];
							}
						}
						break;
					
					case "GoogleRelatedQuestionsShortcut":
						foreach($shortcut["relatedQuestions"] as $question){
							
							//
							// Scrape answers, present them as search results
							//
							if(isset($question["result"]["thumbnailsInfo"][0]["thumbnailUrl"])){
								
								$thumb = [
									"ratio" => "16:9",
									"url" => $question["result"]["thumbnailsInfo"][0]["thumbnailUrl"]
								];
							}else{
								
								$thumb = [
									"ratio" => null,
									"url" => null
								];
							}
							
							$out["web"][] = [
								"title" =>
									$this->titledots(
										$this->fuckhtml
										->getTextContent(
											$question["result"]["title"]
										)
									),
								"description" =>
									$this->titledots(
										html_entity_decode(
											$this->fuckhtml
											->getTextContent(
												$question["result"]["answer"]
											)
										)
									),
								"url" => $question["result"]["url"],
								"date" => null,
								"type" => "web",
								"thumb" => $thumb,
								"sublink" => [],
								"table" => []
							];
						}
						break;
					
					case "NewsShortcut":
						foreach($shortcut["results"] as $news){
							
							if(isset($news["imageUrl"])){
								
								$thumb = [
									"ratio" => "16:9",
									"url" => $news["imageUrl"]
								];
							}else{
								
								$thumb = [
									"ratio" => null,
									"url" => null
								];
							}
							
							$out["news"][] = [
								"title" => $news["headLine"],
								"description" =>
									$this->fuckhtml
									->getTextContent(
										$news["text"]
									),
								"date" => (int)$news["publishTime"],
								"thumb" => $thumb,
								"url" => $news["newsLink"]
							];
						}
						break;
				}
			}
		}
		
		// get next page
		if(isset($json["props"]["pageProps"]["initialProps"]["pageData"]["pager"]["nextPage"])){
			
			$out["npt"] =
				$this->backend->store(
					$json["props"]["pageProps"]["initialProps"]["pageData"]["pager"]["nextPage"],
					"web",
					$proxy
				);
		}
		
		$out["related"] = array_unique($out["related"]);
		
		return $out;
	}
	
	public function image($get){
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		if($get["npt"]){
			
			// parse JSON endpoint
			// https://search.yahoo.co.jp/image/api/search
			// ?p=minecraft
			// &ei=UTF-8
			// &n=20
			// &b=41
			// &vm=i
			// &cr=AiXVLGkAPLAUueqkG0dtUP6lo_3suz4Qsrv2QjabeXt4sk1wT8irS3LLvkSPRm-u7T1wvkE1ucQvhzYuB2QtDkjswUogRjoQVx_p73BaN3P1klQUFsnIPdgAttusXE0ii0pOcYCT
			// &se=0
			// &ue=0
			[$params, $proxy] = $this->backend->get($get["npt"], "images");
			
			$params = json_decode($params, true);
			
			// increment
			$params["params"]["b"] += 20;
			
			try{
				$json = $this->get(
					$proxy,
					"https://search.yahoo.co.jp/image/api/search",
					$params["params"],
					false,
					true,
					$params["cookies"]
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch JSON");
			}
			
			$json = json_decode($json, true);
			
			if($json === null){
				
				throw new Exception("Failed to decode JSON");
			}
			
			if(isset($json["Error"]["Message"])){
				
				throw new Exception("API returned an error: {$json["Error"]["Message"]}");
			}
			
			foreach($json["algos"] as $image){
				
				$out["image"][] = [
					"title" => $this->titledots($image["title"]),
					"source" => [
						[
							"url" => $image["original"]["url"],
							"width" => (int)$image["original"]["width"],
							"height" => (int)$image["original"]["height"]
						],
						[
							"url" => $image["thumbnail"]["url"],
							"width" => (int)$image["thumbnail"]["width"],
							"height" => (int)$image["thumbnail"]["height"]
						]
					],
					"url" => $image["refererUrl"]
				];
			}
			
			// detect next page
			if($json["algoAttribute"]["resultsIsLast"] === false){
				
				$out["npt"] =
					$this->backend->store(
						json_encode($params),
						"images",
						$proxy
					);
			}
			
		}else{
			
			// parse initial page
			$params = [
				"p" => $get["s"],
				"ei" => "UTF-8"
			];
			
			$proxy = $this->backend->get_ip();
			try{
				$html = $this->get(
					$proxy,
					"https://search.yahoo.co.jp/image/search",
					$params,
					true
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			//$html = file_get_contents("scraper/yahoo_japan.html");
			
			$json_object =
				explode(
					'<script id="__NEXT_DATA__" type="application/json">',
					$html["body"]
				);
			
			if(count($json_object) !== 2){
				
				throw new Exception("Failed to find JSON script");
			}
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$json_object[1]
					),
					true
				);
			
			if($json === null){
				
				throw new Exception("Failed to decode JSON");
			}
			
			if(!isset($json["props"]["initialProps"]["pageProps"]["algos"])){
				
				throw new Exception("Failed to access algos object");
			}
			
			foreach($json["props"]["initialProps"]["pageProps"]["algos"] as $image){
				
				$out["image"][] = [
					"title" => $this->titledots($image["title"]),
					"source" => [
						[
							"url" => $image["original"]["url"],
							"width" => (int)$image["original"]["width"],
							"height" => (int)$image["original"]["height"]
						],
						[
							"url" => $image["thumbnail"]["url"],
							"width" => (int)$image["thumbnail"]["width"],
							"height" => (int)$image["thumbnail"]["height"]
						]
					],
					"url" => $image["refererUrl"]
				];
			}
			
			// get next page
			if(
				$json["props"]["initialProps"]["pageProps"]["algoAttribute"]["resultsIsLast"] === false &&
				isset($json["props"]["initialProps"]["pageProps"]["crumb"]["crumbValue"])
			){
				
				$out["npt"] =
					$this->backend->store(
						json_encode([
							"params" => [
								"p" => $get["s"],
								"ei" => "UTF-8",
								"n" => 20, // number of results
								"b" => 1, // increment (+20 on every page)
								"vm" => "i",
								"cr" => $json["props"]["initialProps"]["pageProps"]["crumb"]["crumbValue"],
								"se" => 0,
								"ue" => 0
							],
							"cookies" => $html["cookies"]
						]),
						"images",
						$proxy
					);
			}
		}
		
		return $out;
	}
	
	public function video($get){
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"video" => [],
			"author" => [],
			"livestream" => [],
			"playlist" => [],
			"reel" => []
		];
		
		if($get["npt"]){
			
			// parse JSON endpoint
			// https://search.yahoo.co.jp/image/api/search
			// ?p=minecraft
			// &ei=UTF-8
			// &n=20
			// &b=41
			// &vm=i
			// &cr=AiXVLGkAPLAUueqkG0dtUP6lo_3suz4Qsrv2QjabeXt4sk1wT8irS3LLvkSPRm-u7T1wvkE1ucQvhzYuB2QtDkjswUogRjoQVx_p73BaN3P1klQUFsnIPdgAttusXE0ii0pOcYCT
			// &se=0
			// &ue=0
			[$params, $proxy] = $this->backend->get($get["npt"], "images");
			
			$params = json_decode($params, true);
			
			// increment
			$params["params"]["b"] += 20;
			
			try{
				$json = $this->get(
					$proxy,
					"https://search.yahoo.co.jp/video/api/search",
					$params["params"],
					false,
					true,
					$params["cookies"]
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch JSON");
			}
			
			$json = json_decode($json, true);
			
			if($json === null){
				
				throw new Exception("Failed to decode JSON");
			}
			
			if(isset($json["Error"]["Message"])){
				
				throw new Exception("API returned an error: {$json["Error"]["Message"]}");
			}
			
			foreach($json["algos"] as $video){
				
				if(isset($video["uploadDate"])){
					
					$date = strtotime($video["uploadDate"]);
					if($date === false){
						
						$date = null;
					}
					
				}else{
					
					$date = null;
				}
				
				if(isset($video["thumbnail"]["url"])){
					
					$thumb = [
						"ratio" => "16:9",
						"url" => $video["thumbnail"]["url"]
					];
				}else{
					
					$thumb = [
						"ratio" => null,
						"url" => null
					];
				}
				
				$out["video"][] = [
					"title" => $this->titledots($video["title"]),
					"description" =>
						$this->titledots(
							html_entity_decode(
								$this->fuckhtml
								->getTextContent(
									$video["summary"]
								)
							)
						),
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
					"date" => $date,
					"duration" =>
						(
							isset($video["duration"]) &&
							$video["duration"] != ""
						) ?
						$this->hms2int($video["duration"]) : null,
					"views" => null,
					"thumb" => $thumb,
					"url" => $video["refererUrl"]
				];
			}
			
			// detect next page
			if($json["algoAttribute"]["isLast"] === false){
				
				$out["npt"] =
					$this->backend->store(
						json_encode($params),
						"images",
						$proxy
					);
			}
			
		}else{
			
			// parse initial page
			$params = [
				"p" => $get["s"],
				"ei" => "UTF-8"
			];
			
			$proxy = $this->backend->get_ip();
			try{
				$html = $this->get(
					$proxy,
					"https://search.yahoo.co.jp/video/search",
					$params,
					true
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			//$html = file_get_contents("scraper/yahoo_japan.html");
			
			$json_object =
				explode(
					'<script id="__NEXT_DATA__" type="application/json">',
					$html["body"]
				);
			
			if(count($json_object) !== 2){
				
				throw new Exception("Failed to find JSON script");
			}
			
			$json =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$json_object[1]
					),
					true
				);
			
			if($json === null){
				
				throw new Exception("Failed to decode JSON");
			}
			
			if(!isset($json["props"]["initialProps"]["pageProps"]["algos"])){
				
				throw new Exception("Failed to access algos object");
			}
			
			foreach($json["props"]["initialProps"]["pageProps"]["algos"] as $video){
				
				if(isset($video["uploadDate"])){
					
					$date = strtotime($video["uploadDate"]);
					if($date === false){
						
						$date = null;
					}
					
				}else{
					
					$date = null;
				}
				
				if(isset($video["thumbnail"]["url"])){
					
					$thumb = [
						"ratio" => "16:9",
						"url" => $video["thumbnail"]["url"]
					];
				}else{
					
					$thumb = [
						"ratio" => null,
						"url" => null
					];
				}
				
				$out["video"][] = [
					"title" => $this->titledots($video["title"]),
					"description" =>
						$this->titledots(
							html_entity_decode(
								$this->fuckhtml
								->getTextContent(
									$video["summary"]
								)
							)
						),
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
					"date" => $date,
					"duration" =>
						(
							isset($video["duration"]) &&
							$video["duration"] != ""
						) ?
						$this->hms2int($video["duration"]) : null,
					"views" => null,
					"thumb" => $thumb,
					"url" => $video["refererUrl"]
				];
			}
			
			// get next page
			if(
				$json["props"]["initialProps"]["pageProps"]["algoAttribute"]["isLast"] === false &&
				isset($json["props"]["initialProps"]["pageProps"]["crumb"]["crumbValue"])
			){
				
				$out["npt"] =
					$this->backend->store(
						json_encode([
							"params" => [
								"n" => 20, // number of results
								"b" => 1, // increment (+20)
								"vm" => "i",
								"cr" => $json["props"]["initialProps"]["pageProps"]["crumb"]["crumbValue"],
								"p" => $get["s"],
								"pd" => "",
								"dr" => "",
								"hq" => "",
								"st" => "",
								"qrw" => "",
								"ei" => "UTF-8",
								"ue" => "0",
								"se" => "0"
							],
							"cookies" => $html["cookies"]
						]),
						"images",
						$proxy
					);
			}
		}
		
		return $out;
	}
	
	public function news($get){
		
		if($get["npt"]){
			
			[$params, $proxy] = $this->backend->get($get["npt"], "news");
			$params = json_decode($params, true);
			
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			
			$params = [
				"p" => $get["s"],
				"ei" => "UTF-8"
			];
		}
		
		try{
			$html = $this->get(
				$proxy,
				"https://chiebukuro.yahoo.co.jp/search",
				$params
			);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch search page");
		}
		//$html = file_get_contents("scraper/yahoo_japan.html");
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"news" => []
		];
		
		$json_object =
			explode(
				'window.PROPS = ',
				$html
			);
		
		if(count($json_object) !== 2){
			
			throw new Exception("Failed to find JSON script");
		}
		
		$json =
			json_decode(
				$this->fuckhtml
				->extract_json(
					$json_object[1]
				),
				true
			);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		if(!isset($json["listSearchResults"]["listContents"])){
			
			throw new Exception("Yahoo! did not return a listContents object");
		}
		
		foreach($json["listSearchResults"]["listContents"] as $news){
			
			$date = strtotime($news["datePosted"]);
			
			if($date === false){
				
				$date = null;
			}
			
			$thumb = [
				"ratio" => null,
				"url" => null
			];
			
			$out["news"][] = [
				"title" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$news["heading"]
						)
					),
				"author" => null,
				"description" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$news["summary"]
						)
					),
				"date" => $date,
				"thumb" => $thumb,
				"url" => $news["url"]
			];
		}
		
		// get next page
		if($json["pagination"]["currentPage"] != $json["pagination"]["totalNumberOfPages"]){
			
			if(!isset($params["b"])){
				
				$params["b"] = 1;
			}
			
			$params["b"] += 10;
			
			$out["npt"] =
				$this->backend->store(
					json_encode($params),
					"news",
					$proxy
				);
		}
		
		return $out;
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
	
	private function yahooratio($width, $height){
		
		$ratio = [
			144 / $width,
			256 / $height
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
