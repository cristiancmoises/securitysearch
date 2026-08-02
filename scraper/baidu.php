<?php

class baidu{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("baidu");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
		
		$this->handles = [];
		$this->proc = null;
		$this->handle_category = null;
		$this->handle_increment = 0;
		$this->sublink_increment = 0;
		
		$this->cookie = null;
	}
	
	public function getfilters($page){
		
		switch($page){
			
			case "web":
				return
					[
						"newer" => [
							"display" => "Newer than",
							"option" => "_DATE"
						],
						"older" => [
							"display" => "Older than",
							"option" => "_DATE"
						]
					];
				break;
			
			case "images":
				return
					[
						"sort" => [
							"display" => "Sort",
							"option" => [
								"relevance" => "Relevance", // no param
								"latest" => "Latest", // &latest=1
								"hot" => "Hot" // &hot=1
							]
						],
						"size" => [
							"display" => "Size",
							"option" => [
								"any" => "Any size",
								"7" => "Extra large (1080px+)", // &z=7
								"6" => "Large (600px~1080px)", // &z=6
								"5" => "Medium (300px~600px)", // &z=5
								"4" => "Small (1px~300px)" // &z=4
							]
						],
						"ratio" => [
							"display" => "Ratio",
							"option" => [
								"any" => "Any ratio",
								"1" => "Tall vertical", // &imgratio=1
								"2" => "Vertical", // &imgratio=2
								"3" => "Square", // &imgratio=3
								"4" => "Horizontal", // &imgratio=4
								"5" => "Wide horizontal" // &imgratio=5
							]
						],
						"format" => [
							"display" => "Format",
							"option" => [
								"any" => "Any format",
								"3" => "JPG", // &imgformat=3
								"5" => "JPEG", // &imgformat=5
								"4" => "PNG", // &imgformat=4
								"2" => "BMP", // &imgformat=2
								"6" => "GIF (Animated)" // &imgformat=6
							]
						],
						"color" => [
							"display" => "Color",
							"option" => [
								"any" => "Any color",
								"1024" => "White", // &ic=1024
								"2048" => "Black & White",
								"512" => "Black",
								"64" => "Magenta",
								"16" => "Blue",
								"1" => "Red",
								"2" => "Yellow",
								"32" => "Purple",
								"4" => "Green",
								"8" => "Teal",
								"256" => "Orange",
								"128" => "Brown"
							]
						],
						"type" => [
							"display" => "Type",
							"option" => [
								"any" => "Any type",
								"hd" => "HD", // &hd=1
								"isImgSet" => "Photo album", // &isImgSet=1
								"copyright" => "Copyright" // &copyright=1
							]
						]
					];
				break;
			
			case "videos":
				return [];
				break;
			
			case "news":
				return [
					"category" => [
						"display" => "Category",
						"option" => [
							"any" => "All news",
							"media" => "Media websites", // &medium=1
							"baijiahao" => "Baidu Baijiahao" // &medium=2
						]
					]
				];
				break;
		}
	}
	
	private function get($proxy, $url, $get = [], $referer = false){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
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
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		if($referer === false){
			if($this->cookie === null){
				
				curl_setopt($curlproc, CURLOPT_HTTPHEADER,
					["User-Agent: " . config::USER_AGENT,
					"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip, deflate, br, zstd",
					"DNT: 1",
					"Sec-GPC: 1",
					"Connection: keep-alive",
					"Upgrade-Insecure-Requests: 1",
					"Sec-Fetch-Dest: document",
					"Sec-Fetch-Mode: navigate",
					"Sec-Fetch-Site: cross-site",
					"Priority: u=0, i"]
				);
			}else{
				
				curl_setopt($curlproc, CURLOPT_HTTPHEADER,
					["User-Agent: " . config::USER_AGENT,
					"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip, deflate, br, zstd",
					"DNT: 1",
					"Sec-GPC: 1",
					"Connection: keep-alive",
					"Cookie: {$this->cookie}",
					"Upgrade-Insecure-Requests: 1",
					"Sec-Fetch-Dest: document",
					"Sec-Fetch-Mode: navigate",
					"Sec-Fetch-Site: cross-site",
					"Priority: u=0, i"]
				);
			}
		}else{
			
			if($this->cookie === null){
				
				curl_setopt($curlproc, CURLOPT_HTTPHEADER,
					["User-Agent: " . config::USER_AGENT,
					"Accept: application/json, text/plain, */*",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip, deflate, br, zstd",
					"Referer: {$referer}",
					"DNT: 1",
					"Sec-GPC: 1",
					"Connection: keep-alive",
					"Upgrade-Insecure-Requests: 1",
					"Sec-Fetch-Dest: empty",
					"Sec-Fetch-Mode: cors",
					"Sec-Fetch-Site: same-origin"]
				);
			}else{
				
				curl_setopt($curlproc, CURLOPT_HTTPHEADER,
					["User-Agent: " . config::USER_AGENT,
					"Accept: application/json, text/plain, */*",
					"Accept-Language: en-US,en;q=0.5",
					"Accept-Encoding: gzip, deflate, br, zstd",
					"Referer: {$referer}",
					"DNT: 1",
					"Sec-GPC: 1",
					"Connection: keep-alive",
					"Cookie: {$this->cookie}",
					"Upgrade-Insecure-Requests: 1",
					"Sec-Fetch-Dest: empty",
					"Sec-Fetch-Mode: cors",
					"Sec-Fetch-Site: same-origin"]
				);
			}
		}
		
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
		
		// store cookie
		if(strlen($this->cookie) !== 0){
			
			$this->cookie .= "; ";
		}
		
		foreach($cookies_tmp as $cookie_name => $cookie_value){
			
			$this->cookie .= $cookie_name . "=" . $cookie_value . "; ";
		}
		
		$this->cookie = rtrim($this->cookie, " ;");
		
		curl_close($curlproc);
		return $data;
	}
	
	private function redirect_add_url($proxy, $url){
		
		if(
			preg_match(
				'/^https?:\/\/(?:www\.)?baidu\.com\/link\?/',
				$url
			) === 0
		){
			
			// not a baidu redirect
			return;
		}
		
		$curlproc = curl_init();
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
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
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);
		
		curl_setopt($curlproc, CURLOPT_HEADER, true);
		curl_setopt($curlproc, CURLOPT_NOBODY, true);
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		curl_multi_add_handle($this->proc, $curlproc);
		$this->handles[$this->handle_category][$this->handle_increment][$this->sublink_increment] = $curlproc;
	}
	
	private function resolve_urls($proxy, &$collection, $categories){
		
		$this->proc = curl_multi_init();
		curl_multi_select($this->proc);
		
		foreach($categories as $category){
			
			$this->sublink_increment = 0;
			$this->handle_increment = 0;
			$this->handle_category = $category;
			
			foreach($collection[$category] as $item){
				
				$this->sublink_increment = 0;
				$this->redirect_add_url($proxy, $item["url"]);
				
				if(isset($item["sublink"])){
					
					foreach($item["sublink"] as $sublink){
						
						$this->sublink_increment++;
						$this->redirect_add_url($proxy, $sublink["url"]);
					}
				}
				
				$this->handle_increment++;
			}
		}
		
		do{
			$status = curl_multi_exec($this->proc, $active);
			
		}while($active && $status == CURLM_OK);
		
		//
		// if we reach this, we're done downloading garbage
		//
		
		foreach($this->handles as $category => $v){
			
			foreach($v as $index => $data){
				
				foreach($this->handles[$category][$index] as $sublinkindex => $handle){
					
					preg_match(
						'/location: ?(.*)$/im',
						curl_multi_getcontent($handle),
						$location
					);
					
					if(isset($location[1])){
						
						if($sublinkindex === 0){
							
							$collection[$category][$index]["url"] = trim($location[1]);
						}else{
							
							$collection[$category][$index]["sublink"][$sublinkindex - 1]["url"] = trim($location[1]);
						}
					}
					
					curl_multi_remove_handle($this->proc, $handle);
					curl_close($handle);
				}
			}
		}
		
		curl_multi_close($this->proc);
	}
	
	private function resolve_images($proxy, &$data){
		
		// get the image viewer that contains all of the images direct URLs
		// for some reason, getting the second image's url in the set
		// doesnt trigger the captcha
		
		if(
			!isset($data["image"][1]["url"]) ||
			preg_match(
				'/^https:\/\/image\.baidu\.com\/search\/detail/',
				$data["image"][1]["url"]
			) === 0
		){
			
			// we have an already resolved image link, do nothing
			return;
		}
		
		try{
			
			$html =
				$this->get(
					$proxy,
					$data["image"][1]["url"],
					[]
				);
		}catch(Exception $error){
			
			// fallback to the limited dataset we have
			return;
		}
		
		$this->fuckhtml->load($html);
		
		$script =
			$this->fuckhtml
			->getElementById(
				"image-detail-data",
				"script"
			);
		
		if($script){
			
			$json =
				json_decode(
					$script["innerHTML"],
					true
				);
			
			if(
				!isset($json["data"]["images"]) ||
				count($json["data"]["images"]) === 0
			){
				
				// do nothing
				return;
			}
			
			//
			// Discard all previously scraped images and use data
			// from the newly downloaded image carousel
			// the imageset !!should!! be the same
			//
			$data["image"] = [];
			
			foreach($json["data"]["images"] as $image){
				
				parse_str(parse_url($image["thumburl"], PHP_URL_QUERY), $thumb_size);
				
				$data["image"][] = [
					"title" =>
						$this->fuckhtml
						->getTextContent(
							$image["titleShow"]
						),
					"source" => [
						[
							"url" => $image["objurl"],
							"width" => (int)$image["width"],
							"height" => (int)$image["height"]
						],
						[ // thumbnail
							"url" => $image["thumburl"],
							"width" => (int)$thumb_size["w"],
							"height" => (int)$thumb_size["h"]
						]
					],
					"url" => $image["fromUrl"]
				];
			}
		}
	}
	
	public function web($get){
		
		if($get["npt"]){
			
			[$json, $proxy] = $this->backend->get($get["npt"], "web");
			
			$json = json_decode($json, true);
			$this->cookie = $json["cookie"];
			$npt_data = $json["req"];
			
			$npt_data["pn"] = $npt_data["pn"] + 20;
			
			try{
				
				$html = $this->get(
					$proxy,
					"https://www.baidu.com/s",
					$npt_data
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			
		}else{
			
			//
			// Get authentication token
			//
			$proxy = $this->backend->get_ip();
			
			// running this will give us shit in $this->cookie
			// @TODO probably not needed? I get blocked anyways ffs
			//$this->get($proxy, "https://www.baidu.com", []);
			
			$npt_data = [
				"wd" => $get["s"],
				"rn" => 20
			];
			
			// &gpc=stf%3D0%2C1752638400|stftype%3D2
			if(
				$get["older"] !== false ||
				$get["newer"] !== false
			){
				
				if($get["older"] === false){
					
					$get["older"] = 0;
				}
				
				$npt_data["gpc"] = "stf={$get["older"]},{$get["newer"]}|stftype=2";
			}
			
			try{
				
				$html = $this->get(
					$proxy,
					"https://www.baidu.com/s",
					$npt_data
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			
			$npt_data["pn"] = 0;
		}
		
		return $this->parse_search($proxy, "web", $npt_data, $html);
	}
	
	private function parse_search($proxy, $pagetype, $npt_data, $html){
		
		// @HACK
		// remove newlines from the html, cause it fucks with fuckhtml
		$html = str_replace(["\n", "\r"], "", $html);
		
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
		
		$this->fuckhtml->load($html);
		
		$this->detect_ass();
		
		$datafields =
			$this->fuckhtml
			->getElementsByAttributeName(
				"id",
				"div"
			);
		
		//
		// Get next page
		//
		$npt =
			$this->fuckhtml
			->getElementsByClassName(
				"n",
				"a"
			);
		
		if(count($npt) !== 0){
			
			$out["npt"] =
				$this->backend->store(
					json_encode([
						"req" => $npt_data,
						"cookie" => $this->cookie
					]),
					$pagetype,
					$proxy
				);
		}
		
		//
		// Get related searches
		//
		$related_container =
			$this->fuckhtml
			->getElementById(
				"rs_new",
				$datafields
			);
		
		if($related_container){
			
			$this->fuckhtml->load($related_container);
			
			$as =
				$this->fuckhtml
				->getElementsByClassName(
					"c-color-link",
					"a"
				);
			
			foreach($as as $a){
				
				$text =
					explode(
						">",
						$this->fuckhtml
						->getTextContent(
							$a
						),
						2
					);
				
				$out["related"][] = $text[count($text) - 1];
			}
		}
		
		foreach($datafields as $datafield){
			
			if(
				!isset($datafield["attributes"]["id"]) ||
				preg_match(
					'/^[0-9]+$/',
					$datafield["attributes"]["id"]
				) === 0
			){
				
				// not a search result
				continue;
			}
			
			$this->fuckhtml->load($datafield);
			$div =
				$this->fuckhtml
				->getElementsByTagName(
					"div"
				);
			
			//
			// Don't parse as a search result if it's a card
			//
			$card =
				$this->fuckhtml
				->getElementsByClassName(
					"cosc-card",
					$div
				);
			
			if(count($card) !== 0){
				
				//
				// Parse chinese youtube shorts
				//
				$ytshorts_probe =
					$this->fuckhtml
					->getElementsByClassName(
						"tts-b-item",
						$div
					);
				
				if(count($ytshorts_probe) !== 0){
					
					$videos =
						$this->fuckhtml
						->getElementsByAttributeValue(
							"data-show",
							"list",
							$div
						);
					
					foreach($videos as $video){
						
						$this->fuckhtml->load($video);
						
						$title =
							$this->fuckhtml
							->getElementsByClassName(
								"cosc-title-slot",
								"span"
							);
						
						if(count($title) === 0){
							
							continue;
						}
						
						$url =
							$this->fuckhtml
							->getElementsByTagName(
								"a"
							);
						
						if(count($url) === 0){
							
							continue;
						}
						
						$image =
							$this->fuckhtml
							->getElementsByClassName(
								"cos-image-body",
								"img"
							);
						
						if(count($image) === 0){
							
							$image = [
								"ratio" => null,
								"url" => null
							];
						}else{
							
							$image = [
								"ratio" => "1:1",
								"url" =>
									$this->fuckhtml
									->getTextContent(
										$image[0]["attributes"]["src"]
									)
							];
						}
						
						// get duration
						$divs =
							$this->fuckhtml
							->getElementsByAttributeName(
								"class",
								"div"
							);
						
						$duration = null;
						foreach($divs as $probe){
							
							if(strpos($probe["attributes"]["class"], "tag-bottom-right") !== false){
								
								$duration =
									$this->hms2int(
										$this->fuckhtml
										->getTextContent(
											$probe
										)
									);
								break;
							}
						}
						
						$out["video"][] = [
							"title" =>
								$this->fuckhtml
								->getTextContent(
									$title[0]
								),
							"description" => null,
							"date" => null,
							"duration" => $duration,
							"views" => null,
							"thumb" => $image,
							"url" =>
								$this->fuckhtml
								->getTextContent(
									$url[0]["attributes"]["href"]
								)
						];
					}
				}
				
				//
				// Parse image carousel
				//
				$is_image_carousel = false;
				foreach($div as $d){
					
					if(
						isset($d["attributes"]["class"]) &&
						strpos($d["attributes"]["class"], "image-container") !== false
					){
						
						$is_image_carousel = true;
						break;
					}
				}
				
				if($is_image_carousel){
					
					preg_match(
						'/<!--s-data:([\S\s]*)-->/U',
						$datafield["innerHTML"],
						$matches
					);
					
					if(isset($matches[1])){
						
						// weird behavior with the smaller image carousel where --cos* CSS variables are escaped wrong
						$json =
							$this->fuckhtml
							->parseJsObject(
								str_replace(
									"-\-",
									"--",
									$matches[1]
								)
							);
						
						if(
							$json !== null &&
							isset($json["imageList"][0]["images"])
						){
							
							// parse image carousel
							foreach($json["imageList"][0]["images"] as $image){
								
								parse_str(parse_url($image["thumburl"], PHP_URL_QUERY), $thumb_size);
								
								$out["image"][] = [
									"title" => "image",
									"source" => [
										[
											"url" => $image["objurl"],
											"width" => (int)$image["width"],
											"height" => (int)$image["height"]
										],
										[ // thumbnail
											"url" => $image["thumburl"],
											"width" => (int)$thumb_size["w"],
											"height" => (int)$thumb_size["h"]
										]
									],
									"url" => $image["jumpUrl"]
								];
							}
						}
					}
				}
				continue;
			}
			
			if(!isset($datafield["attributes"]["mu"])){
				
				// dont scrape if we dont have the direct link
				continue;
			}
			
			// class:FYB_RD -> News garbage, IGNORE
			
			$result =
				$this->fuckhtml
				->getElementsByClassName(
					"result",
					[$datafield]
				);
			
			if(count($result) !== 0){
				
				//
				// Parse normal search result
				//
				
				$title =
					$this->fuckhtml
					->getElementsByClassName(
						"sc-link",
						"a"
					);
				
				if(count($title) === 0){
					
					// should not happen
					continue;
				}
				
				$title =
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$title[0]
						)
					);
				
				$description =
					$this->fuckhtml
					->getElementsByClassName(
						"c-color",
						$div
					);
				
				if(count($description) !== 0){
					
					$this->fuckhtml->load($description[0]);
					
					$description =
						$this->fuckhtml
						->getElementsByAttributeName(
							"class",
							"span"
						);
					
					$found_desc = false;
					foreach($description as $desc){
						
						if(stripos($desc["attributes"]["class"], "summary-text") !== false){
							
							$found_desc = true;
							$description =
								$this->titledots(
									$this->fuckhtml
									->getTextContent(
										$desc
									)
								);
							break;
						}
					}
					
					if($found_desc === false){
						
						$description = null;
					}
					
					$this->fuckhtml->load($datafield);
				}else{
					
					$description = null;
				}
				
				// parse date
				$date_probe =
					$this->fuckhtml
					->getElementsByClassName(
						"cos-color-text-minor",
						"span"
					);
				
				if(count($date_probe) !== 0){
					
					$date =
						$this->parse_time(
							$this->fuckhtml
							->getTextContent(
								$date_probe[0]
							)
						);
				}else{
					
					$date = null;
				}
				
				// parse image
				$img =
					$this->fuckhtml
					->getElementsByTagName(
						"img"
					);
				
				if(count($img) !== 0){
					
					$image = [
						"ratio" => "16:9",
						"url" =>
							$this->unfuckthumb(
								$this->fuckhtml
								->getTextContent(
									$img[0]["attributes"]["src"]
								)
							)
					];
				}else{
					
					$image = [
						"ratio" => null,
						"url" => null
					];
				}
				
				// get page type
				$pagetype_probe =
					$this->fuckhtml
					->getElementsByTagName(
						"b"
					);
				
				$pagetype = "web";
				foreach($pagetype_probe as $probe){
					
					$pagetype =
						strtolower(
							trim(
								$this->fuckhtml
								->getTextContent(
									$probe
								),
								" 【】"
							)
						);
				}
				
				// get extra links
				$sublinks = [];
				
				foreach($div as $d){
					
					if(
						isset($d["attributes"]["class"]) &&
						strpos($d["attributes"]["class"], "exta-link") !== false
					){
						
						$this->fuckhtml->load($d);
						
						$links =
							$this->fuckhtml
							->getElementsByClassName(
								"cos-space-mt-xs",
								"div"
							);
						
						foreach($links as $link){
							
							$this->fuckhtml->load($link);
							$s_title =
								$this->fuckhtml
								->getElementsByTagName(
									"h3"
								);
							
							if(count($s_title) === 0){
								
								// should not happen
								continue;
							}
							
							$data2 =
								json_decode(
									$this->fuckhtml
									->getTextContent(
										$s_title[0]["attributes"]["data-click"]
									),
									true
								);
							
							if(!isset($data2["clk_info"])){
								
								// wtf
								continue;
							}
							
							$data2 =
								json_decode(
									$data2["clk_info"],
									true
								);
							
							if(!isset($data2["url"])){
								
								// no link, fuck off
								continue;
							}
							
							$url =
								rawurldecode(
									$data2["url"]
								);
							
							$data =
								$this->fuckhtml
								->getElementsByTagName(
									"p"
								);
							
							$s_description = null;
							
							if(count($data) !== 0){
								
								$data =
									json_decode(
										$this->fuckhtml
										->getTextContent(
											$data[0]["attributes"]["sub-show-log"]
										),
										true
									);
								
								if(isset($data["ext"]["content"])){
									
									$s_description = $data["ext"]["content"];
								}
							}
							
							$sublinks[] = [
								"title" =>
									$this->fuckhtml
									->getTextContent(
										$s_title[0]
									),
								"description" => $s_description,
								"url" => $url,
								"date" => null
							];
						}
						break;
					}
				}
				
				$out["web"][] = [
					"title" => $title,
					"description" => $description,
					"url" =>
						$this->fuckhtml
						->getTextContent(
							$datafield["attributes"]["mu"]
						),
					"date" => $date,
					"type" => $pagetype,
					"thumb" => $image,
					"sublink" => $sublinks,
					"table" => []
				];
				
				continue;
			}
			
			// parse special result
			$result =
				$this->fuckhtml
				->getElementsByClassName(
					"result-op",
					[$datafield]
				);
			
			if(count($result) !== 0){
				
				//
				// Parse video carousel
				//
				if(
					isset($datafield["attributes"]["tpl"]) &&
					stripos($datafield["attributes"]["tpl"], "video") !== false
				){
					
					preg_match(
						'/<!--s-data:([\S\s]*)-->/U',
						$datafield["innerHTML"],
						$matches
					);
					
					if(isset($matches[1])){
					
						$json =
							json_decode(
								$matches[1],
								true
							);
						
						if($json !== null){
							
							foreach($json["videoList"] as $video){
								
								$out["video"][] = [
									"title" => $video["title"],
									"description" =>
										$this->titledots(
											$video["desc"]
										),
									"date" =>
										$this->parse_time(
											$video["pubTime"]
										),
									"duration" =>
										$this->hms2int(
											$video["duration"]
										),
									"views" =>
										$this->parse_viewcount(
											$video["playCount"]
										),
									"thumb" => [
										"ratio" => "16:9",
										"url" => $video["poster"]
									],
									"url" => $video["bindProps"]["link"]
								];
							}
						}
					}
					continue;
				}
				
				//
				// Special result div (wiki entries, rich divs)
				//
				$title =
					$this->fuckhtml
					->getElementsByTagName(
						"h3"
					);
				
				if(count($title) === 0){
					
					// should have a title somewhere
					continue;
				}
				
				$title =
					explode(
						">",
						$this->fuckhtml
						->getTextContent(
							$title[0]
						),
						2
					);
				
				if(count($title) === 2){
					
					$title = $title[1];
				}else{
					
					$title = $title[0];
				}
				
				// probe for wiki-like entry
				$description =
					$this->fuckhtml
					->getElementsByClassName(
						"sc-paragraph",
						"p"
					);
				
				if(count($description) === 0){
					
					// try and get grey description
					$description =
						$this->fuckhtml
						->getElementsByClassName(
							"c-color-gray2",
							"p"
						);
					
					if(count($description) === 0){
						
						// probe for special social media description
						$description =
							$this->fuckhtml
							->getElementsByClassName(
								"c-color-text",
								"div"
							);
						
						if(isset($description[0]["attributes"]["aria-label"])){
							
							$description =
								$this->fuckhtml
								->getTextContent(
									$description[0]
									["attributes"]
									["aria-label"]
								);
						}else{
							
							// check for news tab description
							$span =
								$this->fuckhtml
								->getElementsByClassName(
									"c-font-normal",
									"span"
								);
							
							$description = null;
							
							foreach($span as $s){
								
								if(isset($s["attributes"]["aria-label"])){
									
									$description =
										$this->titledots(
											$this->fuckhtml
											->getTextContent(
												$span[count($span) - 1]
											)
										);
									
									break;
								}
							}
						}
					}else{
						
						$description =
							$this->fuckhtml
							->getTextContent(
								$description[0]
							);
					}
					
				}else{
					
					preg_match(
						'/<!--s-text-->([\S\s]*)<!--\/s-text-->/U',
						$description[count($description) - 1]["innerHTML"],
						$matches
					);
					
					if(isset($matches[1])){
						
						$description =
							$this->titledots(
								$this->fuckhtml
								->getTextContent(
									$matches[1]
								)
							);
					}else{
						
						$description = null;
					}
				}
				
				// get thumbnail
				$thumb =
					$this->fuckhtml
					->getElementsByTagName(
						"img"
					);
				
				if(count($thumb) !== 0){
					
					$thumb = [
						"ratio" => "1:1",
						"url" =>
							$this->unfuckthumb(
								$this->fuckhtml
								->getTextContent(
									$thumb[0]["attributes"]["src"]
								)
							)
					];
				}else{
					
					$thumb = [
						"ratio" => null,
						"url" => null
					];
				}
				
				// get sublinks
				preg_match(
					'/<!--s-data:([\S\s]*)-->/U',
					$datafield["innerHTML"],
					$matches
				);
				
				$sublinks = [];
				
				if(isset($matches[1])){
					
					$json =
						json_decode(
							$matches[1],
							true
						);
					
					if($json !== null){
						
						if(isset($json["buttons"])){
							
							foreach($json["buttons"] as $button){
								
								$sublinks[] = [
									"title" => $button["text"],
									"description" => null,
									"date" => null,
									"url" => $button["url"]
								];
							}
						}elseif(isset($json["mthreadList"])){
							
							foreach($json["mthreadList"] as $thread){
								
								$sublinks[] = [
									"title" =>
										$this->fuckhtml
										->getTextContent(
											$thread["title"]
										),
									"description" => null,
									"date" => null,
									"url" => $thread["ttsInfo"]["titleUrl"]
								];
							}
						}
					}
				}
				
				// get URL
				// handle http://fakeurl.baidu.com bullshit
				$url =
					$this->fuckhtml
					->getTextContent(
						$datafield["attributes"]["mu"]
					);
				
				if(
					preg_match(
						'/^https?:\/\/(?:fakeurl|nourl)(?:\.ubs)?\.baidu\.com/',
						$url
					)
				){
					
					// we got some bullshit, get jumpUrl instead
					$as =
						$this->fuckhtml
						->getElementsByTagName(
							"a"
						);
					
					if(count($as) !== 0){
						
						$url =
							$this->fuckhtml
							->getTextContent(
								$as[0]["attributes"]["href"]
							);
					}
				}
				
				// get xueshu sublinks
				// get list
				$xueshu_list =
					$this->fuckhtml
					->getElementsByClassName(
						"op-xueshu-links-d20-list",
						$div
					);
				
				if(count($xueshu_list) !== 0){
					
					$this->fuckhtml->load($xueshu_list[0]);
					
					$rows =
						$this->fuckhtml
						->getElementsByClassName(
							"c-row",
							"div"
						);
					
					// remove "read more" bullshit
					foreach($rows as $row){
						
						if(strpos($row["attributes"]["class"], "op-xueshu-links-more") !== false){
							
							$xueshu_list[0]["innerHTML"] =
								str_replace(
									$row["outerHTML"],
									"",
									$xueshu_list[0]["innerHTML"]
								);
						}
					}
					
					$this->fuckhtml->load($xueshu_list[0]);
					
					foreach($rows as $row){
						
						$this->fuckhtml->load($row);
						
						if(strpos($row["attributes"]["class"], "op-xueshu-links-more") !== false){
							
							continue;
						}
						
						$as =
							$this->fuckhtml
							->getElementsByTagName(
								"a"
							);
						
						foreach($as as $a){
							
							$sublinks[] = [
								"title" =>
									$this->titledots(
										$this->fuckhtml
										->getTextContent(
											$a
										)
									),
								"description" => null,
								"date" => null,
								"url" =>
									$this->fuckhtml
									->getTextContent(
										$a["attributes"]["href"]
									)
							];
						}
					}
				}
				
				$out["web"][] = [
					"title" => $title,
					"description" => $description,
					"url" => $url,
					"date" => null,
					"type" => "web",
					"thumb" => $thumb,
					"sublink" => $sublinks,
					"table" => []
				];
				continue;
			}
		}
		
		//
		// Remove tracking URLs and fetch additonal image resources
		//
		$this->resolve_urls($proxy, $out, ["web", "video"]);
		$this->resolve_images($proxy, $out);
		
		return $out;
	}
	
	public function image($get){
		
		// https://image.baidu.com/search/acjson?word=asmr&rn=60&pn=0&newReq=1
		//$json = file_get_contents("scraper/baidu_img.json");
		
		if($get["npt"]){
			
			[$params, $proxy] = $this->backend->get($get["npt"], "images");
			$params = json_decode($params, true);
			
			$params["pn"] = $params["pn"] + 60;
			
		}else{
			
			$proxy = $this->backend->get_ip();
			$params = [
				"word" => $get["s"],
				"rn" => 60, // results/page
				"pn" => 0, // item increment (0 * 60)
				"newReq" => 1 // otherwise json is fucked up
			];
			
			switch($get["sort"]){
				
				case "latest": $params["latest"] = 1; break;
				case "hot": $params["hot"] = 1; break;
			}
			
			if($get["size"] != "any"){
				
				$params["z"] = $get["size"];
			}
			
			if($get["ratio"] != "any"){
				
				$params["imgratio"] = $get["ratio"];
			}
			
			if($get["format"] != "any"){
				
				$params["imgformat"] = $get["format"];
			}
			
			if($get["color"] != "any"){
				
				$params["ic"] = $get["color"];
			}
			
			switch($get["type"]){
				
				case "hd": $params["hd"] = 1; break;
				case "isImgSet": $params["isImgSet"] = 1; break;
				case "copyright": $params["copyright"] = 1; break;
			}
		}
		
		try{
				
			$json =
				$this->get(
					$proxy,
					"https://image.baidu.com/search/acjson",
					$params,
					"https://image.baidu.com/search/index?tn=baiduimage&word=" . urlencode($get["s"])
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			// detect captcha first			
			$this->fuckhtml->load($json);
			$this->detect_ass();
			
			// fallback to json decode error
			throw new Exception("Failed to decode JSON");
		}
		
		if(
			isset($json["message"]) &&
			$json["message"] != "success"
		){
			
			throw new Exception("Baidu returned an error: {$json["message"]}");
		}
		
		if(!isset($json["data"]["images"])){
			
			throw new Exception("Baidu did not return an image object");
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		foreach($json["data"]["images"] as $image){
			
			parse_str(parse_url($image["thumburl"], PHP_URL_QUERY), $thumb_size);
			
			$out["image"][] = [
				"title" =>
					$this->fuckhtml
					->getTextContent(
						$image["titleShow"]
					),
				"source" => [
					[
						"url" => $image["objurl"],
						"width" => (int)$image["width"],
						"height" => (int)$image["height"]
					],
					[ // thumbnail
						"url" => $image["thumburl"],
						"width" => (int)$thumb_size["w"],
						"height" => (int)$thumb_size["h"]
					]
				],
				"url" => $image["fromUrl"]
			];
		}
		
		//
		// Detect if there's a next page
		//
		if((int)$json["data"]["totalNum"] >= $params["pn"] + 60){
			
			$out["npt"] =
				$this->backend->store(
					json_encode($params),
					"images",
					$proxy
				);
		}
		
		return $out;
	}
	
	public function video($get){
		
		// https://www.baidu.com/sf/vsearch?pd=video&tn=vsearch&wd=jak%2Band%2Bdaxter&async=1&pn=0
		// increase &pn +20 for pagination
		
		//$html = file_get_contents("scraper/baidu_vid.html");
		
		if($get["npt"]){
			
			[$params, $proxy] = $this->backend->get($get["npt"], "videos");
			$params = json_decode($params, true);
			
			$params["pn"] = $params["pn"] + 10;
		}else{
			
			$proxy = $this->backend->get_ip();
			$params = [
				"pd" => "video",
				"tn" => "vsearch",
				"wd" => $get["s"],
				"async" => 1,
				"pn" => 0
			];
		}
		
		try{
			$html =
				$this->get(
					$proxy,
					"https://www.baidu.com/sf/vsearch",
					$params
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to get search page");
		}
		
		$html =
			str_replace(
				["\r", "\n"],
				"",
				$html
			);
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"video" => [],
			"author" => [],
			"livestream" => [],
			"playlist" => [],
			"reel" => []
		];
		
		$html = explode("<script>", $html);
		
		foreach($html as $result){
			
			$result = trim($result);
			
			$this->fuckhtml->load($result);
			
			// get URL
			preg_match(
				'/<!-- *([^ ]*) *-->/',
				$result,
				$matches
			);
			
			if(!isset($matches[1])){
				
				// no link, give up
				continue;
			}
			
			$link = $matches[1];
			
			// get title
			$title =
				$this->fuckhtml
				->getElementsByClassName(
					"video-title",
					"a"
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
			
			// get thumbnail
			$img =
				$this->fuckhtml
				->getElementsByClassName(
					"border-radius",
					"img"
				);
			
			if(count($img) !== 0){
				
				$thumb = [
					"url" =>
						$this->unfuckthumb(
							$this->fuckhtml
							->getTextContent(
								$img[0]["attributes"]["src"]
							)
						),
					"ratio" => "16:9"
				];
			}else{
				
				$thumb = [
					"url" => null,
					"ratio" => null
				];
			}
			
			$span =
				$this->fuckhtml
				->getElementsByTagName(
					"span"
				);
			
			// get duration
			$duration =
				$this->fuckhtml
				->getElementsByClassName(
					"video_play_timer",
					$span
				);
			
			if(count($duration) !== 0){
				
				$duration =
					$this->hms2int(
						$this->fuckhtml
						->getTextContent(
							$duration[0]
						)
					);
			}else{
				
				$duration = null;
			}
			
			// get author
			// 来源：哔哩哔哩
			$author =
				$this->fuckhtml
				->getElementsByClassName(
					"wetSource",
					$span
				);
			
			if(count($author) !== 0){
				
				$author =
					explode(
						"：",
						$this->fuckhtml
						->getTextContent(
							$author[0]
						),
						2
					)[1];
			}else{
				
				$author = null;
			}
			
			// get date posted
			//发布时间：2024-05-06
			
			// AND get description
			// 简介：Our first look
			$infospans =
				array_merge(
					$this->fuckhtml
					->getElementsByClassName(
						"c-font-normal",
						$span
					),
					$this->fuckhtml
					->getElementsByClassName(
						"c-font-normal",
						"div"
					)
				);
			
			$date = null;
			$description = null;
			
			foreach($infospans as $infospan){
				
				$infospan =
					explode(
						"：",
						$this->fuckhtml
						->getTextContent(
							$infospan
						),
						2
					);
				
				if(count($infospan) !== 2){
					
					// should not happen
					continue;
				}
				
				$infospan[1] =
					$this->fuckhtml
					->getTextContent(
						$infospan[1]
					);
				
				switch($infospan[0]){
					
					case "发布时间": // date posted
						$date = $this->parse_time($infospan[1]);
						break;
					
					case "简介": // description
						$description = $infospan[1];
						break;
				}
			}
			
			$out["video"][] = [
				"title" => $this->titledots($title),
				"description" => $this->titledots($description),
				"author" => [
					"name" => $author,
					"url" => null,
					"avatar" => null
				],
				"date" => $date,
				"duration" => $duration,
				"views" => null,
				"thumb" => $thumb,
				"url" => $link
			];
		}
		
		if(count($out["video"]) === 10){
			
			// assume there's another page after this
			$out["npt"] =
				$this->backend->store(
					json_encode($params),
					"videos",
					$proxy
				);
		}
		
		return $out;
	}
	
	public function news($get){
		
		//$proxy = $this->backend->get_ip();
		//$html = file_get_contents("scraper/baidu.html");
		//$npt_data = [];
		
		if($get["npt"]){
			
			[$json, $proxy] = $this->backend->get($get["npt"], "news");
			
			$json = json_decode($json, true);
			$this->cookie = $json["cookie"];
			$npt_data = $json["req"];
			
			$npt_data["pn"] = $npt_data["pn"] + 20;
			
			try{
				
				$html = $this->get(
					$proxy,
					"https://www.baidu.com/s",
					$npt_data
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			
		}else{
			
			//
			// Get authentication token
			//
			$proxy = $this->backend->get_ip();
			
			$npt_data = [
				"wd" => $get["s"],
				"rn" => 20,
				"tn" => "news"
			];
			
			// @TODO add filters
			
			try{
				
				$html = $this->get(
					$proxy,
					"https://www.baidu.com/s",
					$npt_data
				);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch search page");
			}
			
			$npt_data["pn"] = 0;
		}
		
		$data = $this->parse_search($proxy, "news", $npt_data, $html);
		
		$out = [
			"status" => "ok",
			"npt" => $data["npt"],
			"news" => []
		];
		
		foreach($data["web"] as $article){
			
			$out["news"][] = [
				"title" => $article["title"],
				"author" => null,
				"description" => $article["description"],
				"date" => $article["date"],
				"thumb" => [
					"url" => $article["thumb"]["url"],
					"ratio" => $article["thumb"]["url"] !== null ? "16:9" : null,
				],
				"url" => $article["url"]
			];
		}
		
		return $out;
	}
	
	private function unfuckthumb($url){
		
		// probe for proxy URL
		$parsed_url = parse_url($url);
		if(
			preg_match(
				'/^https?:\/\/gimg(?:[0-9]+)?\.baidu\.com/',
				$url
			)
		){
			
			$parts = explode("src=", $url);
			if(count($parts) !== 2){
				
				// shits fucked
				return $url;
			}
			
			return urldecode(explode("&", $parts[1])[0]);
		}
		
		$q = explode("&", $url, 2);
		
		if(count($q) !== 2){
			
			// shits fucked, again
			return $url;
		}
		
		// baidu devs are fucking retarded and dont follow spec:
		// &fmt=auto?s=BB32F3A050471AEC72886934030090C4&sec=1753203600&t=0fb2194775d3bd3d1bb114b818479e0a
		parse_str(str_replace("?", "&", $q[1]), $query);
		
		if(isset($query["size"])){ unset($query["size"]); }
		if(isset($query["q"])){ $query["q"] = "90"; }
		
		$query = http_build_query($query);
		
		return
			str_replace(
				$q[1],
				$query,
				$url
			);
	}
	
	private function titledots($title){
		
		return trim($title, " .\t\n\r\0\x0B…");
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
	
	private function parse_viewcount($views){
		
		if(
			// 10k (wtf lol)
			preg_match(
				'/([0-9]+)万次/',
				$views,
				$matches
			)
		){
			
			return (int)$matches[1] * 10000;
		}
		
		if(
			// units
			preg_match(
				'/([0-9]+)次/',
				$views,
				$matches
			)
		){
			
			return (int)$matches[1];
		}
		
		return null;
	}
	
	private function parse_time($time){
		
		// 2023年8月7日 => yyyy/m/d
		if(
			preg_match(
				'/([0-9]{4})年([0-9]{1,2})月([0-9]{1,2})日/',
				$time,
				$matches
			)
		){
			
			return strtotime("{$matches[1]}/{$matches[2]}/{$matches[3]}");
		}
		
		// 昨天11:45 => yesterday at 11:45
		// 昨天 => yesterday
		if(
			preg_match(
				'/昨天(.*)/',
				$time,
				$matches
			)
		){
			
			return strtotime("Yesterday {$matches[1]}");
		}
		
		// 3天前 => 3 days ago
		if(
			preg_match(
				'/([0-9]{1,4})天前/',
				$time,
				$matches
			)
		){
			
			return strtotime("{$matches[1]} days ago");
		}
		
		// 1个月前 => 1 month ago
		if(
			preg_match(
				'/([0-9]{1,4})个月前/',
				$time,
				$matches
			)
		){
			
			return strtotime("{$matches[1]} months ago");
		}
		
		// attempt to parse as-is
		$time = strtotime($time);
		
		if($time !== false){
			
			return $time;
		}
		
		return null;
	}
	
	private function detect_ass(){
		
		$as =
			$this->fuckhtml
			->getElementsByTagName(
				"a"
			);
		
		if(
			count($as) === 0 ||
			preg_match(
				'/^https?:\/\/wappass\.baidu\.com\/static\/captcha/',
				$this->fuckhtml
				->getTextContent(
					$as[0]["attributes"]["href"]
				)
			)
		){
			
			throw new Exception("Baidu returned a Captcha");
		}
	}
}
