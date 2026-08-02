<?php

class vimeo{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("vimeo");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function getfilters($page){
		
		return [
			"time" => [
				"display" => "Date uploaded", // &filter_uploaded=
				"option" => [
					"any" => "Any time",
					"today" => "Last 24 hours",
					"this-week" => "Last 7 days",
					"this-month" => "Last 30 days",
					"this-year" => "Last 365 days",
				]
			], 
			"display" => [
				"display" => "Display",
				"option" => [
					"video" => "Videos",
					"ondemand" => "On-Demand ($$)",
					"people" => "People",
					"channel" => "Channels",
					"group" => "Groups"
				]
			],
			"sort" => [
				"display" => "Sort by",
				"option" => [
					"relevance" => "Relevance", // no param
					"recent" => "Newest", // &sort=latest&direction=desc
					"popular" => "Most popular", // &sort=popularity&direction=desc
					"a_z" => "Title, A to Z", // &sort=alphabetical&direction=asc
					"z_a" => "Title, Z to A", // &sort=alphabetical&direction=desc
					"longest" => "Longest", // &sort=duration&direction=desc
					"shortest" => "Shortest", // &sort=duration&direction=asc
				]
			],
			"duration" => [
				"display" => "Duration", // &filter_duration=
				"option" => [
					"any" => "Any duration",
					"short" => "Short (less than 4 minutes)",
					"medium" => "Medium (4-10 minutes)",
					"long" => "Long (over 10 minutes)"
				]
			],
			"resolution" => [
				"display" => "Resolution",
				"option" => [
					"any" => "Any resolution",
					"4k" => "4K" // &filter_resolution=4k
				]
			],
			"category" => [
				"display" => "Category", // &filter_category=
				"option" => [
					"any" => "Any category",
					"animation" => "Animation",
					"comedy" => "Comedy",
					"music" => "Music",
					"experimental" => "Experimental",
					"documentary" => "Documentary",
					"identsandanimatedlogos" => "Idents and Animated Logos",
					"industry" => "Industry",
					"instructionals" => "Instructionals",
					"narrative" => "Narrative",
					"personal" => "Personal"
				]
			],
			"live" => [
				"display" => "Live events",
				"option" => [
					"any" => "Any",
					"yes" => "Live now" // &filter_live=now
				]
			],
			"hdr" => [
				"display" => "HDR", // &filter_hdr=
				"option" => [
					"any" => "Any",
					"hdr" => "Any HDR",
					"dolby_vision" => "Dolby Vision",
					"hdr10" => "HDR10",
					"hdr10+" => "HDR10+"
				]
			],
			"vimeo_360" => [
				"display" => "Vimeo 360°", // &filter_vimeo_360
				"option" => [
					"any" => "Any",
					"spatial" => "Spatial",
					"360" => "360°"
				]
			],
			"price" => [ // &filter_price=
				"display" => "Price",
				"option" => [
					"any" => "Any price",
					"free" => "Free",
					"paid" => "Paid"
				]
			],
			"collection" => [
				"display" => "Vimeo collections",
				"option" => [
					"any" => "Any collection",
					"staff_pick" => "Staff picks" // &filter_staffpicked=true
				]
			],
			"license" => [ // &filter_license=
				"display" => "License",
				"option" => [
					"any" => "Any license",
					"by-nc-nd" => "CC BY-NC-ND",
					"by" => "CC BY",
					"by-nc" => "CC BY-NC",
					"by-nc-sa" => "CC BY-NC-SA",
					"by-nd" => "CC BY-ND",
					"by-sa" => "CC BY-SA",
					"cc0" => "CC0"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = [], $jwt = false){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		if($jwt === false){
			
			curl_setopt(
				$curlproc,
				CURLOPT_HTTPHEADER,
				["User-Agent: " . config::USER_AGENT,
				"Accept: */*",
				"Accept-Language: en-US,en;q=0.5",
				"Accept-Encoding: gzip, deflate, br, zstd",
				"Referer: https://vimeo.com/search",
				"X-Requested-With: XMLHttpRequest",
				"DNT: 1",
				"Sec-GPC: 1",
				"Connection: keep-alive",
				"Sec-Fetch-Dest: empty",
				"Sec-Fetch-Mode: cors",
				"Sec-Fetch-Site: same-origin",
				"Priority: u=4"]
			);
			
		}else{
			
			curl_setopt(
				$curlproc,
				CURLOPT_HTTPHEADER,
				["User-Agent: " . config::USER_AGENT,
				"Accept: application/vnd.vimeo.*+json;version=3.3",
				"Accept-Language: en",
				"Accept-Encoding: gzip, deflate, br, zstd",
				"Referer: https://vimeo.com/",
				"Content-Type: application/json",
				"Authorization: jwt $jwt",
				"Vimeo-Page: /search/[[...slug]]",
				"Origin: https://vimeo.com",
				"DNT: 1",
				"Sec-GPC: 1",
				"Connection: keep-alive",
				"Sec-Fetch-Dest: empty",
				"Sec-Fetch-Mode: cors",
				"Sec-Fetch-Site: same-site",
				"Priority: u=4"]
			);
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
		
		curl_close($curlproc);
		return $data;
	}
	
	public function video($get){
		
		// parse shit
		if($get["npt"]){
			
			[$npt, $proxy] =
				$this->backend
				->get(
					$get["npt"],
					"videos"
				);
			
			$npt = json_decode($npt, true);
			$pagetype = $npt["pagetype"];
			$npt = $npt["npt"];
			
			$jwt = $this->get_jwt($proxy);
			
			try{
				
				$json =
					$this->get(
						$proxy,
						"https://api.vimeo.com" . $npt,
						[],
						$jwt
					);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch JSON");
			}
			
		}else{
			
			$proxy = null;
			$jwt = $this->get_jwt($proxy); // this gives us a proxy by reference
			
			// parse filters
			$npt = [
				"query" => $get["s"],
				"page" => 1,
				"per_page" => 24,
				"facets" => "type"
			];
			
			switch($get["display"]){
				
				case "video":
					$npt["filter_type"] = "clip";
					$npt["fields"] = "clip.name,stats.plays,clip.pictures,clip.user.name,clip.user.link,clip.user.pictures.sizes,clip.uri,clip.stats.plays,clip.duration,clip.created_time,clip.link,clip.description";
					break;
				
				case "ondemand":
					$npt["filter_type"] = "ondemand";
					$npt["sizes"] = "296x744";
					$npt["fields"] = "ondemand.link,ondemand.name,ondemand.pictures.sizes,ondemand.metadata.interactions.buy,ondemand.metadata.interactions.rent,ondemand.uri";
					break;
				
				case "people":
					$npt["filter_type"] = "people";
					$npt["fetch_user_profile"] = "1";
					$npt["fields"] = "people.name,people.location_details.formatted_address,people.metadata.public_videos.total,people.pictures.sizes,people.link,people.metadata.connections.followers.total,people.skills.name,people.skills.uri,people.background_video,people.uri";
					break;
				
				case "channel":
					$npt["filter_type"] = "channel";
					$npt["fields"] = "channel.name,channel.metadata.connections.users.total,channel.metadata.connections.videos.total,channel.pictures.sizes,channel.link,channel.uri";
					break;
				
				case "group":
					$npt["filter_type"] = "group";
					$npt["fields"] = "group.name,group.metadata.connections.users.total,group.metadata.connections.videos.total,group.pictures.sizes,group.link,group.uri";
					break;
			}
			
			// only apply filters if we're searching for videos
			if($get["display"] == "video"){
				
				switch($get["sort"]){
					
					case "relevance": break; // do nothing
					
					case "recent":
						$npt["sort"] = "latest";
						$npt["direction"] = "desc";
						break;
					
					case "popular":
						$npt["sort"] = "popularity";
						$npt["direction"] = "desc";
						break;
					
					case "a_z":
						$npt["sort"] = "alphabetical";
						$npt["direction"] = "asc";
						break;
					
					case "z_a":
						$npt["sort"] = "alphabetical";
						$npt["direction"] = "desc";
						break;
					
					case "longest":
						$npt["sort"] = "duration";
						$npt["direction"] = "desc";
						break;
					
					case "shortest":
						$npt["sort"] = "duration";
						$npt["direction"] = "asc";
						break;
				}
				
				if($get["time"] != "any"){
					
					$npt["filter_uploaded"] = $get["time"];
				}
				
				if($get["duration"] != "any"){
					
					$npt["filter_duration"] = $get["duration"];
				}
				
				if($get["resolution"] != "any"){
					
					$npt["filter_resolution"] = $get["resolution"];
				}
				
				if($get["category"] != "any"){
					
					$npt["filter_category"] = $get["category"];
				}
				
				if($get["live"] != "any"){
					
					$npt["filter_live"] = "now";
				}
				
				if($get["hdr"] != "any"){
					
					$npt["filter_hdr"] = $get["hdr"];
				}
				
				if($get["vimeo_360"] != "any"){
					
					$npt["filter_vimeo_360"] = $get["vimeo_360"];
				}
				
				if($get["price"] != "any"){
					
					$npt["filter_price"] = $get["price"];
				}
				
				if($get["collection"] == "staff_pick"){
					
					$npt["filter_staffpicked"] = "true";
				}
				
				if($get["license"] != "any"){
					
					$npt["filter_license"] = $get["license"];
				}
			}
			
			$pagetype = $npt["filter_type"];
			
			try{
				
				$json =
					$this->get(
						$proxy,
						"https://api.vimeo.com/search",
						$npt,
						$jwt
					);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch JSON");
			}
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to parse JSON");
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
		
		if(isset($json["error"])){
			
			$error = $json["error"];
			if(isset($json["developer_message"])){
				
				$error .= " ({$json["developer_message"]})";
			}
			
			throw new Exception("Vimeo returned an error: " . $error);
		}
		
		if(!isset($json["data"])){
			
			throw new Exception("Vimeo did not return a data object");
		}
		
		switch($pagetype){
			
			case "clip":
				foreach($json["data"] as $video){
					
					$video = $video["clip"];
					
					if(isset($video["user"]["pictures"]["sizes"])){
						
						$avatar = $video["user"]["pictures"]["sizes"][count($video["user"]["pictures"]["sizes"]) - 1]["link"];
					}else{
						
						$avatar = null;
					}
					
					$out["video"][] = [
						"title" => $video["name"],
						"description" =>
							$this->limitstrlen(
								$video["description"]
							),
						"author" => [
							"name" => $video["user"]["name"],
							"url" => $video["user"]["link"],
							"avatar" => $avatar
						],
						"date" => strtotime($video["created_time"]),
						"duration" => (int)$video["duration"],
						"views" => (int)$video["stats"]["plays"],
						"thumb" => [
							"ratio" => "16:9",
							"url" => $video["pictures"]["base_link"]
						],
						"url" => $video["link"]
					];
				}
				break;
			
			case "ondemand":
				foreach($json["data"] as $video){
					
					$video = $video["ondemand"];
					
					$description = [];
					if(isset($video["metadata"]["interactions"]["rent"]["display_price"])){
						
						$description[] = "Rent for " . $video["metadata"]["interactions"]["rent"]["display_price"];
					}
					
					if(isset($video["metadata"]["interactions"]["buy"]["display_price"])){
						
						$description[] = "Buy for " . $video["metadata"]["interactions"]["buy"]["display_price"];
					}
					
					$description = implode(", ", $description);
					
					$out["video"][] = [
						"title" => $video["name"],
						"description" => $description,
						"author" => [
							"name" => null,
							"url" => null,
							"avatar" => null
						],
						"date" => null,
						"duration" => null,
						"views" => null,
						"thumb" => [
							"ratio" => "9:16",
							"url" => $video["pictures"]["sizes"][0]["link"]
						],
						"url" => $video["link"]
					];
				}
				break;
			
			case "people":
				foreach($json["data"] as $user){
					
					$user = $user["people"];
					
					if(
						isset($user["pictures"]["sizes"]) &&
						count($user["pictures"]["sizes"]) !== 0
					){
						
						$thumb = [
							"ratio" => "1:1",
							"url" => $user["pictures"]["sizes"][count($user["pictures"]["sizes"]) - 1]["link"]
						];
					}else{
						
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}
					
					$out["author"][] = [
						"title" => $user["name"],
						"followers" => (int)$user["metadata"]["connections"]["followers"]["total"],
						"description" => $user["metadata"]["public_videos"]["total"] . " videos.",
						"thumb" => $thumb,
						"url" => $user["link"]
					];
				}
				break;
			
			case "channel":
			case "group":
				foreach($json["data"] as $channel){
					
					$channel = $channel[$npt["filter_type"]];
					
					if(
						isset($channel["pictures"]["sizes"]) &&
						count($channel["pictures"]["sizes"]) !== 0
					){
						
						$thumb = [
							"ratio" => "16:9",
							"url" => $channel["pictures"]["sizes"][count($channel["pictures"]["sizes"]) - 1]["link"]
						];
					}else{
						
						$thumb = [
							"ratio" => null,
							"url" => null
						];
					}
					
					$out["author"][] = [
						"title" => $channel["name"],
						"followers" => (int)$channel["metadata"]["connections"]["users"]["total"],
						"description" => $channel["metadata"]["connections"]["videos"]["total"] . " videos.",
						"thumb" => $thumb,
						"url" => $channel["link"]
					];
				}
				break;
		}
		
		//
		// get next page
		//
		if(
			isset($json["paging"]["next"]) &&
			is_string($json["paging"]["next"])
		){
			
			$out["npt"] =
				$this->backend
				->store(
					json_encode([
						"npt" => $json["paging"]["next"],
						"pagetype" => $pagetype
					]),
					"videos",
					$proxy
				);
		}
		
		return $out;
	}
	
	private function get_jwt(&$proxy){
		
		//
		// get jwt token
		// it's probably safe to cache this across proxies, cause the jwt doesnt contain an userID
		// only an appID, whatever shit that is
		// we can only cache it for 5 minutes though, otherwise vimeo cries about it
		//
		if($proxy === null){
			
			$proxy = $this->backend->get_ip();
		}
		
		$jwt = apcu_fetch("vimeo_jwt");
		
		if($jwt === false){
			/*
			$html =
				$this->get(
					$proxy,
					"https://vimeo.com/search",
					[],
					false
				);
			
			$this->fuckhtml->load($html);
			
			$captcha =
				$this->fuckhtml
				->getElementsByTagName(
					"title"
				);
			
			if(
				count($captcha) !== 0 &&
				$this->fuckhtml
				->getTextContent(
					$captcha[0]
				) == "Vimeo / CAPTCHA Challenge"
			){
				
				throw new Exception("Vimeo returned a Captcha");
			}
			
			$html =
				explode(
					'<script id="viewer-bootstrap" type="application/json">',
					$html,
					2
				);
			
			if(count($html) !== 2){
				
				throw new Exception("Failed to find JWT json");
			}
			
			$jwt =
				json_decode(
					$this->fuckhtml
					->extract_json(
						$html[1]
					),
					true
				);
			
			if($jwt === null){
				
				throw new Exception("Failed to decode JWT json");
			}
			
			if(!isset($jwt["jwt"])){
				
				throw new Exception("Failed to grep JWT");
			}
			
			$jwt = $jwt["jwt"];
			*/
			
			try{
				$json =
					$this->get(
						$proxy,
						"https://vimeo.com/_next/jwt",
						[],
						false
					);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch JWT token");
			}
			
			$this->fuckhtml->load($json);
			
			$captcha =
				$this->fuckhtml
				->getElementsByTagName(
					"title"
				);
			
			if(
				count($captcha) !== 0 &&
				$this->fuckhtml
				->getTextContent(
					$captcha[0]
				) == "Vimeo / CAPTCHA Challenge"
			){
				
				throw new Exception("Vimeo returned a Captcha");
			}
				
			$json = json_decode($json, true);
			
			if($json === null){
				
				throw new Exception("The JWT object could not be decoded");
			}
			
			if(!isset($json["token"])){
				
				throw new Exception("Vimeo did not return a JWT");
			}
			
			$jwt = $json["token"];
			
			apcu_store("vimeo_jwt", $jwt, 300);
		}
		
		return $jwt;
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
