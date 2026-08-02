<?php

class swisscows{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("swisscows");
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function getfilters($page){
		
		return [
			"type" => [
				"display" => "Type",
				"option" => [
					"track" => "Tracks",
					"playlist" => "Playlists"
				]
			]
		];
	}
	
	private function get($proxy, $url, $get = [], $web_req = false){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		
		// use http2
		curl_setopt($curlproc, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
		
		curl_setopt($curlproc, CURLOPT_HTTPHEADER,
			["User-Agent: " . config::USER_AGENT,
			"Accept: */*",
			"Accept-Language: en-US,en;q=0.5",
			"Accept-Encoding: gzip, deflate, br, zstd",
			"Access-Control-Request-Method: GET",
			"Access-Control-Request-Headers: cache-control",
			"Referer: https://swisscows.com/",
			"Origin: https://swisscows.com",
			"DNT: 1",
			"Sec-GPC: 1",
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
	
	public function music($get, $last_attempt = false){
		
		if($get["npt"]){
			
			[$params, $proxy] = $this->backend->get($get["npt"], "music");
			$params = json_decode($params, true);
			$type = $params["type"];
			$search = $params["s"];
			$offset = $params["offset"];
			
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$type = $get["type"];
			$offset = 0;
			$proxy = $this->backend->get_ip();
		}
		
		try{
			
			$json =
				$this->get(
					$proxy,
					"https://api.swisscows.com/audio/search/{$type}s",
					[
						"query" => $search,
						"offset" => $offset,
						"itemsCount" => 20,
						"region" => "en-US"
					]
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
			"song" => [],
			"playlist" => [],
			"album" => [],
			"podcast" => [],
			"author" => [],
			"user" => []
		];
		
		if(!isset($json["items"])){
			
			throw new Exception("Swisscows did not return an items object");
		}
		
		if($type == "track"){
			foreach($json["items"] as $item){
				
				$tags = $item["tags"];
				if(!empty($item["genre"])){
					
					$tags[] = $item["genre"];
				}
				
				$out["song"][] = [
					"title" => $item["title"],
					"description" => implode(", ", $tags),
					"url" => "/resolver?scraper=sc&target=t{$item["id"]}",
					"views" => null,
					"author" => [
						"name" => null,
						"url" => null,
						"avatar" => null
					],
					"thumb" => [
						"ratio" => "1:1",
						"url" => $item["artworkUrl"]
					],
					"date" => null,
					"duration" => $this->convert_time($item["duration"]),
					"stream" => [					
						"endpoint" => null,
						"url" => null
					]
				];
			}
		}else{
			
			foreach($json["items"] as $item){
				
				$out["playlist"][] = [
					"title" => $item["title"],
					"description" => $this->limitstrlen($item["description"]),
					"author" => [
						"name" => null,
						"url" => null,
						"avatar" => null
					],
					"thumb" => [
						"ratio" => "1:1",
						"url" => $item["artworkUrl"]
					],
					"date" => null,
					"duration" => $this->convert_time($item["duration"]),
					"url" => "/resolver?scraper=sc&target=p{$item["id"]}",
				];
			}
		}
		
		//
		// get NPT
		//
		if(
			isset($json["nextOffset"]) &&
			$json["nextOffset"] !== null
		){
			
			$out["npt"] =
				$this->backend->store(
					json_encode(
						[
							"type" => $type,
							"s" => $search,
							"offset" => $json["nextOffset"]
						]
					),
					"music",
					$proxy
				);
		}
		
		return $out;
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
	
	private function convert_time($time){
		
		list($hours, $minutes, $seconds) = explode(':', $time);
		
		return
			((int)$hours * 3600) +
			((int)$minutes * 60) +
			(float)$seconds;
	}
}
