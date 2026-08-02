<?php

class sc{
	
	public function __construct(){
		
		include "lib/backend.php";
		$this->backend = new backend("sc");
		
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
		
		$headers =
			["User-Agent: " . config::USER_AGENT,
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
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
			"Priority: u=0, i"];
		
		$this->backend->assign_proxy($curlproc, $proxy);
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER, $headers);
		
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
	
	function resolve($id){
		
		if(
			!preg_match(
				'/^(t|p)[0-9]+$/',
				$id
			)
		){
			
			throw new Exception("ID is invalid");
		}
		
		$type = $id[0] == "t" ? "track" : "playlist";
		$id = substr($id, 1);
		
		try{
			$html =
				$this->get(
					$this->backend->get_ip(),
					"https://w.soundcloud.com/player/",
					[
						"url" => "http://api.soundcloud.com/{$type}s/{$id}",
						"show_artwork" => "true"
					]
				);
		}catch(Exception $error){
			
			throw new Exception("Failed to fetch song embed page");
		}
		
		$this->fuckhtml->load($html);
		
		$link =
			$this->fuckhtml
			->getElementsByAttributeValue(
				"rel",
				"canonical",
				"link"
			);
		
		if(count($link) === 0){
			
			throw new Exception("Soundcloud could not resolve the song ID to an URL");
		}
		
		return
			$this->fuckhtml
			->getTextContent(
				$link[0]
				["attributes"]
				["href"]
			);
	}
}
