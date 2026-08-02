<?php

class flickr{
	
	const req_web = 0;
	const req_xhr = 1;
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("flickr");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function getfilters($page){
		
		return [
			"nsfw" => [
				"display" => "NSFW",
				"option" => [
					"yes" => "Yes",
					"maybe" => "Maybe",
					"no" => "No",
				]
			],
			"sort" => [
				"display" => "Sort by",
				"option" => [
					"relevance" => "Relevance",
					"date-posted-desc" => "Newest uploads",
					"date-posted-asc" => "Oldest uploads",
					"date-taken-desc" => "Newest taken",
					"date-taken-asc" => "Oldest taken",
					"interestingness-desc" => "Interesting"
				]
			],
			"color" => [
				"display" => "Color",
				"option" => [
					"any" => "Any color",
					// color_codes=
					"0" => "Red",
					"1" => "Brown",
					"2" => "Orange",
					"b" => "Pink",
					"4" => "Yellow",
					"3" => "Golden",
					"5" => "Lime",
					"6" => "Green",
					"7" => "Sky blue",
					"8" => "Blue",
					"9" => "Purple",
					"a" => "Hot pink",
					"c" => "White",
					"d" => "Gray",
					"e" => "Black",
					// styles= override
					"blackandwhite" => "Black & white",
				]
			],
			"style" => [ // styles=
				"display" => "Style",
				"option" => [
					"any" => "Any style",
					"depthoffield" => "Depth of field",
					"minimalism" => "Minimalism",
					"pattern" => "Patterns"
				]
			],
			"license" => [
				"display" => "License",
				"option" => [
					"any" => "Any license",
					"1,2,3,4,5,6,9,11,12,13,14,15,16" => "All creative commons",
					"4,5,6,9,10,11,12,13" => "Commercial use allowed",
					"1,2,4,5,9,10,11,12,14,15" => "Modifications allowed",
					"4,5,9,10,11,12" => "Commercial use & mods allowed",
					"7,9,10" => "No known copyright restrictions",
					"8" => "U.S Government works"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = [], $reqtype){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		if($reqtype === flickr::req_web){
			
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
		}else{
			
			curl_setopt($curlproc, CURLOPT_HTTPHEADER,
				["User-Agent: " . config::USER_AGENT,
				"Accept: */*",
				"Accept-Language: en-US,en;q=0.5",
				"Accept-Encoding: gzip",
				"Origin: https://www.flickr.com",
				"DNT: 1",
				"Sec-GPC: 1",
				"Connection: keep-alive",
				"Referer: https://www.flickr.com/",
				// Cookie:
				"Sec-Fetch-Dest: empty",
				"Sec-Fetch-Mode: cors",
				"Sec-Fetch-Site: same-site",
				"TE: trailers"]
			);
		}
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);
		
		// http2 bypass
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		$data = curl_exec($curlproc);
		
		if(curl_errno($curlproc)){
			
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		return $data;
	}
	
	public function image($get){
		
		if($get["npt"]){
			
			[$filters, $proxy] =
				$this->backend->get(
					$get["npt"], "images"
				);
			
			$filters = json_decode($filters, true);
			
			// Workaround for the future, if flickr deprecates &page argument on html page
			/*
			try{
				
				$json =
					$this->get(
						$proxy,
						"https://api.flickr.com/services/rest",
						[
							"sort" => $data["sort"],
							"parse_tags" => 1,
							// url_s,url_n,url_w,url_m,url_z,url_c,url_l,url_h,url_k,url_3k,url_4k,url_5k,url_6k,url_o
							"extras" => "can_comment,can_print,count_comments,count_faves,description,isfavorite,license,media,needs_interstitial,owner_name,path_alias,realname,rotation,url_sq,url_q,url_t,url_s,url_n,url_w,url_m,url_z,url_c,url_l",
							"per_page" => 100,
							"page" => $data["page"],
							"lang" => "en-US",
							"text" => $data["search"],
							"viewerNSID" => "",
							"method" => "flickr.photos.search",
							"csrf" => "",
							"api_key" => $data["api_key"],
							"format" => "json",
							"hermes" => 1,
							"hermesClient" => 1,
							"reqId" => $data["reqId"],
							"nojsoncallback" => 1
						]
					);
			}catch(Exception $error){
				
				throw new Exception("Failed to fetch JSON");
			}*/
			
		}else{
			
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
						
			// compute filters
			$filters = [
				"page" => 1,
				"sort" => $get["sort"]
			];
			
			if($get["style"] != "any"){
				
				$filters["styles"] = $get["style"];
			}
			
			if($get["color"] != "any"){
				
				if($get["color"] != "blackandwhite"){
					
					$filters["color_codes"] = $get["color"];
				}else{
					
					$filters["styles"] = "blackandwhite";
				}
			}
			
			if($get["license"] != "any"){
				
				$filters["license"] = $get["license"];
			}
			
			switch($get["nsfw"]){
				
				case "yes": $filters["safe_search"] = 0; break;
				case "maybe": $filters["safe_search"] = 2; break;
				case "no": $filters["safe_search"] = 1; break;
			}
		}
		
		$get_params = [
			"text" => $get["s"],
			"per_page" => 50,
			// scrape highest resolution
			"extras" => "url_s,url_n,url_w,url_m,url_z,url_c,url_l,url_h,url_k,url_3k,url_4k,url_5k,url_6k,url_o",
			"view_all" => 1
		];
		
		$get_params = array_merge($get_params, $filters);
		
		$html =
			$this->get(
				$proxy,
				"https://www.flickr.com/search/",
				$get_params,
				flickr::req_web
			);
		
		// @TODO
		// get api_key and reqId, if flickr deprecates &page
		
		$this->fuckhtml->load($html);
		
		//
		// get response JSON
		//
		$scripts =
			$this->fuckhtml
			->getElementsByClassName(
				"modelExport",
				"script"
			);
		
		$found = false;
		foreach($scripts as $script){
			
			$json =	
				preg_split(
					'/modelExport: ?/',
					$script["innerHTML"],
					2
				);
			
			if(count($json) !== 0){
				
				$found = true;
				$json = $json[1];
				break;
			}
		}
		
		if($found === false){
			
			throw new Exception("Failed to grep JSON");
		}
		
		$json =
			json_decode(
				$this->fuckhtml
				->extract_json(
					$json
				),
				true
			);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		if(!isset($json["main"]["search-photos-lite-models"][0]["data"]["photos"]["data"]["_data"])){
			
			throw new Exception("Failed to access data object");
		}
		
		foreach($json["main"]["search-photos-lite-models"][0]["data"]["photos"]["data"]["_data"] as $image){
			
			if(!isset($image["data"])){
				
				// flickr likes to gives us empty array objects
				continue;
			}
			
			$image = $image["data"];
			
			$title = [];
			
			if(isset($image["title"])){
				
				$title[] =
					$this->fuckhtml
					->getTextContent(
						$image["title"]
					);
			}
			
			if(isset($image["description"])){
				
				$title[] =
					$this->fuckhtml
					->getTextContent(
						str_replace(
							"\n",
							" ",
							$image["description"]
						)
					);
			}
			
			$title = implode(": ", $title);
			
			$sources = array_values($image["sizes"]["data"]);
			
			$suitable_sizes = ["n", "m", "w", "s"];
			
			$thumb = &$sources[0]["data"];
			foreach($suitable_sizes as $testing_size){
				
				if(isset($image["sizes"]["data"][$testing_size])){
					
					$thumb = &$image["sizes"]["data"][$testing_size]["data"];
					break;
				}
			}
			
			$og = &$sources[count($sources) - 1]["data"];
			
			$out["image"][] = [
				"title" => $title,
				"source" => [
					[
						"url" => "https:" . $og["displayUrl"],
						"width" => (int)$og["width"],
						"height" => (int)$og["height"]
					],
					[
						"url" => "https:" . $thumb["displayUrl"],
						"width" => (int)$thumb["width"],
						"height" => (int)$thumb["height"]
					]
				],
				"url" => "https://www.flickr.com/photos/" . $image["ownerNsid"] . "/" . $image["id"] . "/"
			];
		}
		
		$total_items = (int)$json["main"]["search-photos-lite-models"][0]["data"]["photos"]["data"]["totalItems"];
		
		if(($filters["page"]) * 50 < $total_items){
			
			$filters["page"]++;
			
			$out["npt"] =
				$this->backend->store(
					json_encode($filters),
					"images",
					$proxy
				);
		}
		
		return $out;
	}
}
