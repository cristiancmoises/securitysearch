<?php

class yandex{
	
	/*
		curl functions
	*/
	public function __construct(){
		
		include "lib/fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
		
		include "lib/backend.php";
		// backend included in the scraper functions
	}
	
	private function get($proxy, $url, $get = [], $nsfw, $get_cookie = 1){
		
		$curlproc = curl_init();
		
		if($get !== []){
			$get = http_build_query($get);
			$url .= "?" . $get;
		}
		
		curl_setopt($curlproc, CURLOPT_URL, $url);
		
		// extract "i" cookie
		if($get_cookie === 0){
			
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
		
		switch($nsfw){
			case "yes": $nsfw = "0"; break;
			case "maybe": $nsfw = "1"; break;
			case "no": $nsfw = "2"; break;
		}
		
		switch($get_cookie){
			
			case 0:
				$cookie = "";
				break;
			
			case 1:
				$cookie = "Cookie: yp=" . (time() - 4000033) . ".szm.1:1920x1080:876x1000#" . time() . ".sp.family:" . $nsfw;
				break;
			
			default:
				$cookie = "Cookie: i=" . $get_cookie;
		}
		
		$headers =
			["User-Agent: " . config::USER_AGENT,
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
			"Accept-Encoding: gzip",
			"Accept-Language: en-US,en;q=0.5",
			"DNT: 1",
			$cookie,
			"Referer: https://yandex.com/images/search",
			"Connection: keep-alive",
			"Upgrade-Insecure-Requests: 1",
			"Sec-Fetch-Dest: document",
			"Sec-Fetch-Mode: navigate",
			"Sec-Fetch-Site: cross-site",
			"Upgrade-Insecure-Requests: 1"];
		
		curl_setopt($curlproc, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curlproc, CURLOPT_HTTPHEADER, $headers);
		
		curl_setopt($curlproc, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curlproc, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curlproc, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($curlproc, CURLOPT_TIMEOUT, 30);

		$this->backend->assign_proxy($curlproc, $proxy);
		
		$data = curl_exec($curlproc);
		
		if($get_cookie === 0){
			
			if(isset($cookies_tmp["i"])){
				
				return $cookies_tmp["i"];
			}else{
				
				throw new Exception("Failed to get Yandex clearance cookie");
			}
		}
		
		if(curl_errno($curlproc)){
			
			throw new Exception(curl_error($curlproc));
		}
		
		curl_close($curlproc);
		return $data;
	}
	
	public function getfilters($pagetype){
		
		switch($pagetype){
			
			case "web":
				return [
					"lang" => [
						"display" => "Language",
						"option" => [
							"any" => "Any language",
							"en" => "English",
							"ru" => "Russian",
							"be" => "Belorussian",
							"fr" => "French",
							"de" => "German",
							"id" => "Indonesian",
							"kk" => "Kazakh",
							"tt" => "Tatar",
							"tr" => "Turkish",
							"uk" => "Ukrainian"
						]
					],
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
					"nsfw" => [
						"display" => "NSFW",
						"option" => [
							"yes" => "Yes",
							"maybe" => "Maybe",
							"no" => "No"
						]
					],
					"time" => [
						"display" => "Time posted",
						"option" => [
							"any" => "Any time",
							"week" => "Last week"
						]
					],
					"size" => [
						"display" => "Size",
						"option" => [
							"any" => "Any size",
							"small" => "Small",
							"medium" => "Medium",
							"large" => "Large",
							"wallpaper" => "Wallpaper"
						]
					],
					"color" => [
						"display" => "Colors",
						"option" => [
							"any" => "All colors",
							"color" => "Color images only",
							"gray" => "Black and white",
							"red" => "Red",
							"orange" => "Orange",
							"yellow" => "Yellow",
							"cyan" => "Cyan",
							"green" => "Green",
							"blue" => "Blue",
							"violet" => "Purple",
							"white" => "White",
							"black" => "Black"
						]
					],
					"type" => [
						"display" => "Type",
						"option" => [
							"any" => "All types",
							"photo" => "Photos",
							"clipart" => "White background",
							"lineart" => "Drawings and sketches",
							"face" => "People",
							"demotivator" => "Demotivators"
						]
					],
					"layout" => [
						"display" => "Layout",
						"option" => [
							"any" => "All layouts",
							"horizontal" => "Horizontal",
							"vertical" => "Vertical",
							"square" => "Square"
						]
					],
					"format" => [
						"display" => "Format",
						"option" => [
							"any" => "Any format",
							"jpeg" => "JPEG",
							"png" => "PNG",
							"gif" => "GIF"
						]
					]
				];
				break;
			
			case "videos":
				return [
					"nsfw" => [
						"display" => "NSFW",
						"option" => [
							"yes" => "Yes",
							"maybe" => "Maybe",
							"no" => "No"
						]
					],
					"time" => [
						"display" => "Time posted",
						"option" => [
							"any" => "Any time",
							"9" => "Recently"
						]
					],
					"duration" => [
						"display" => "Duration",
						"option" => [
							"any" => "Any duration",
							"short" => "Short"
						]
					]
				];
				break;
		}
	}
	
	public function web($get){
		
		$this->backend = new backend("yandex_w");
		
		// has captcha
		// https://yandex.com/search/touch/?text=lol&app_platform=android&appsearch_header=1&ui=webmobileapp.yandex&app_version=23070603&app_id=ru.yandex.searchplugin&search_source=yandexcom_touch_native&clid=2218567
		
		// https://yandex.com/search/site/?text=minecraft&web=1&frame=1&v=2.0&searchid=3131712
		// &within=777&from_day=26&from_month=8&from_year=2023&to_day=26&to_month=8&to_year=2023
		
		// get clearance cookie
		if(($cookie = apcu_fetch("yandexweb_cookie")) === false){
			
			$proxy = $this->backend->get_ip();
			
			$cookie =
				$this->get(
					$proxy,
					"https://yandex.ru/support2/smart-captcha/ru/",
					[],
					false,
					0
				);
			
			apcu_store("yandexweb_cookie", $cookie);
		}
		
		if($get["npt"]){
			
			[$npt, $proxy] = $this->backend->get($get["npt"], "web");
			
			$html =
				$this->get(
					$proxy,
					"https://yandex.com" . $npt,
					[],
					"yes",
					$cookie
				);
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = !isset($proxy) ? $this->backend->get_ip() : $proxy;
			$lang = $get["lang"];
			$older = $get["older"];
			$newer = $get["newer"];
			
			$params = [
				"text" => $search,
				"web" => "1",
				"frame" => "1",
				"searchid" => "3131712"
			];
			
			if($lang != "any"){
				
				$params["lang"] = $lang;
			}
			
			if(
				$newer === false &&
				$older !== false
			){
				
				$newer = 0;
			}
			
			if($newer !== false){
				
				$params["from_day"] = date("j", $newer);
				$params["from_month"] = date("n", $newer);
				$params["from_year"] = date("Y", $newer);
				
				if($older === false){
					
					$older = time();
				}
				
				$params["to_day"] = date("j", $older);
				$params["to_month"] = date("n", $older);
				$params["to_year"] = date("Y", $older);
			}
			
			try{
				$html =
					$this->get(
						$proxy,
						"https://yandex.com/search/site/",
						$params,
						"yes",
						$cookie
					);
			}catch(Exception $error){
				
				throw new Exception("Could not get search page");
			}
			
			/*
			$handle = fopen("scraper/yandex.html", "r");
			$html = fread($handle, filesize("scraper/yandex.html"));
			fclose($handle);*/
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
		
		$this->fuckhtml->load($html);
		
		// Scrape page blocked error
		$title =
			$this->fuckhtml
			->getElementsByTagName("title");
		
		if(
			count($title) !== 0 &&
			$title[0]["innerHTML"] == "403"
		){
			
			throw new Exception("Yandex blocked this proxy or 4get instance.");
		}
		
		// get nextpage
		$npt =
			$this->fuckhtml
			->getElementsByClassName(
				"b-pager__next",
				"a"
			);
		
		if(count($npt) !== 0){
			
			$out["npt"] =
				$this->backend->store(
					$this->fuckhtml
					->getTextContent(
						$npt
						[0]
						["attributes"]
						["href"]
					),
					"web",
					$proxy
				);
		}
		
		// get items
		$items =
			$this->fuckhtml
			->getElementsByClassName(
				"b-serp-item",
				"li"
			);
		
		foreach($items as $item){
			
			$this->fuckhtml->load($item);
			
			$link =
				$this->fuckhtml
				->getElementsByClassName(
					"b-serp-item__title-link",
					"a"
				)[0];
			
			$out["web"][] = [
				"title" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$link
						)
					),
				"description" =>
					$this->titledots(
						$this->fuckhtml
						->getTextContent(
							$this->fuckhtml
							->getElementsByClassName(
								"b-serp-item__text",
								"div"
							)[0]
						)
					),
				"url" =>
					$this->fuckhtml
					->getTextContent(
						$link
						["attributes"]
						["href"]
					),
				"date" => null,
				"type" => "web",
				"thumb" => [
					"url" => null,
					"ratio" => null
				],
				"sublink" => [],
				"table" => []
			];
		}
		
		return $out;
	}
	
	public function image($get){
		
		$this->backend = new backend("yandex_i");
		
		if($get["npt"]){
			
			[$request, $proxy] =
				$this->backend->get(
					$get["npt"],
					"images"
				);
			
			$request = json_decode($request, true);
			
			$nsfw = $request["nsfw"];
			unset($request["nsfw"]);
		}else{
			
			$search = $get["s"];
			if(strlen($search) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
			$nsfw = $get["nsfw"];
			$time = $get["time"];
			$size = $get["size"];
			$color = $get["color"];
			$type = $get["type"];
			$layout = $get["layout"];
			$format = $get["format"];
			/*
			$handle = fopen("scraper/yandex.json", "r");
			$json = fread($handle, filesize("scraper/yandex.json"));
			fclose($handle);*/
			
			// SIZE
			// large
			// 227.0=1;203.0=1;76fe94.0=1;41d251.0=1;75.0=1;371.0=1;291.0=1;307.0=1;f797ee.0=1;1cf7c2.0=1;deca32.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&isize=large&suggest_reqid=486139416166165501540886508227485&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// medium
			// 227.0=1;203.0=1;76fe94.0=1;41d251.0=1;75.0=1;371.0=1;291.0=1;307.0=1;f797ee.0=1;1cf7c2.0=1;deca32.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&isize=medium&suggest_reqid=486139416166165501540886508227485&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// small
			// 227.0=1;203.0=1;76fe94.0=1;41d251.0=1;75.0=1;371.0=1;291.0=1;307.0=1;f797ee.0=1;1cf7c2.0=1;deca32.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&isize=small&suggest_reqid=486139416166165501540886508227485&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// ORIENTATION
			// Horizontal
			// 227.0=1;203.0=1;76fe94.0=1;41d251.0=1;75.0=1;371.0=1;291.0=1;307.0=1;f797ee.0=1;1cf7c2.0=1;deca32.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&iorient=horizontal&suggest_reqid=486139416166165501540886508227485&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Vertical
			// 227.0=1;203.0=1;76fe94.0=1;41d251.0=1;75.0=1;371.0=1;291.0=1;307.0=1;f797ee.0=1;1cf7c2.0=1;deca32.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&iorient=vertical&suggest_reqid=486139416166165501540886508227485&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Square
			// 227.0=1;203.0=1;76fe94.0=1;41d251.0=1;75.0=1;371.0=1;291.0=1;307.0=1;f797ee.0=1;1cf7c2.0=1;deca32.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&iorient=square&suggest_reqid=486139416166165501540886508227485&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// TYPE
			// Photos
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&text=minecraft&type=photo&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// White background
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&text=minecraft&type=clipart&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Drawings and sketches
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&text=minecraft&type=lineart&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// People
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&text=minecraft&type=face&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Demotivators
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&text=minecraft&type=demotivator&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// COLOR
			// Color images only
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=color&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Black and white
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=gray&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Red
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=red&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Orange
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=orange&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Yellow
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=yellow&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Cyan
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=cyan&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Green
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=green&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Blue
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=blue&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Purple
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=violet&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// White
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=white&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// Black
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&icolor=black&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// FORMAT
			// jpeg
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&itype=jpg&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// png
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&itype=png&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// gif
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&itype=gifan&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// RECENT
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&recent=7D&text=minecraft&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			// WALLPAPER
			// 307.0=1;371.0=1;291.0=1;203.0=1;deca32.0=1;f797ee.0=1;1cf7c2.0=1;41d251.0=1;267.0=1;bde197.0=1"},"extraContent":{"names":["i-react-ajax-adapter"]}}}&yu=4861394161661655015&isize=wallpaper&text=minecraft&wp=wh16x9_1920x1080&uinfo=sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080
			
			
			$request = [
				"format" => "json",
				"request" => [
					"blocks" => [
						[
							"block" => "extra-content",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "i-global__params:ajax",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "search2:ajax",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "preview__isWallpaper",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "content_type_search",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "serp-controller",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "cookies_ajax",
							"params" => (object)[],
							"version" => 2
						],
						[
							"block" => "advanced-search-block",
							"params" => (object)[],
							"version" => 2
						]
					],
					"metadata" => [
						"bundles" => [
							"lb" => "AS?(E<X120"
						],
						"assets" => [
							// las base
							"las" => "justifier-height=1;justifier-setheight=1;fitimages-height=1;justifier-fitincuts=1;react-with-dom=1;"
							
							// las default
							//"las" => "justifier-height=1;justifier-setheight=1;fitimages-height=1;justifier-fitincuts=1;react-with-dom=1;227.0=1;203.0=1;76fe94.0=1;215f96.0=1;75.0=1"
						],
						"extraContent" => [
							"names" => [
								"i-react-ajax-adapter"
							]
						]
					]
				]
			];
			
			/*
				Apply filters
			*/
			if($time == "week"){
				$request["recent"] = "7D";
			}
			
			if($size != "any"){
				
				$request["isize"] = $size;
			}
			
			if($type != "any"){
				
				$request["type"] = $type;
			}
			
			if($color != "any"){
				
				$request["icolor"] = $color;
			}
			
			if($layout != "any"){
				
				$request["iorient"] = $layout;
			}
			
			if($format != "any"){
				
				$request["itype"] = $format;
			}
			
			$request["text"] = $search;
			$request["uinfo"] = "sw-1920-sh-1080-ww-1125-wh-999-pd-1-wp-16x9_1920x1080";
			
			$request["request"] = json_encode($request["request"]);
		}
		
		try{
			$json = $this->get(
				$proxy,
				"https://yandex.com/images/search",
				$request,
				$nsfw,
				"yandex_i"
			);
		}catch(Exception $err){
			
			throw new Exception("Failed to get JSON");
		}
		
		/*
		$handle = fopen("scraper/yandex.json", "r");
		$json = fread($handle, filesize("scraper/yandex.json"));
		fclose($handle);*/
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Failed to decode JSON");
		}
		
		if(
			isset($json["type"]) &&
			$json["type"] == "captcha"
		){
			
			throw new Exception("Yandex blocked this 4get instance. Please try again in ~7 minutes.");
		}
		
		$out = [
			"status" => "ok",
			"npt" => null,
			"image" => []
		];
		
		// get html
		$html = "";
		foreach($json["blocks"] as $block){
			
			$html .= $block["html"];
			// get next page
			if(
				isset($block["params"]["nextPageUrl"]) &&
				!empty($block["params"]["nextPageUrl"])
			){
				
				$request["nsfw"] = $nsfw;
				
				if(isset($request["p"])){
					
					$request["p"]++;
				}else{
					
					$request["p"] = 1;
				}
				
				$out["npt"] =
					$this->backend->store(
						json_encode($request),
						"images",
						$proxy
					);
			}
		}
		
		$this->fuckhtml->load($html);
		
		// get search results
		$data = null;
		
		foreach(
			$this->fuckhtml
			->getElementsByClassName(
				"Root",
				"div"
			) as $div
		){
			
			if(isset($div["attributes"]["data-state"])){
				
				$tmp = json_decode(
					$this->fuckhtml
					->getTextContent(
						$div["attributes"]["data-state"]
					),
					true
				);
				
				if(isset($tmp["initialState"]["serpList"])){
					
					$data = $tmp;
					break;
				}
			}
		}
		
		if($data === null){
			
			throw new Exception("Failed to extract JSON");
		}
		
		foreach($data["initialState"]["serpList"]["items"]["entities"] as $image){
			
			$title = [html_entity_decode($image["snippet"]["title"], ENT_QUOTES | ENT_HTML5)];
			
			if(isset($image["snippet"]["text"])){
				
				$title[] = html_entity_decode($image["snippet"]["text"], ENT_QUOTES | ENT_HTML5);
			}

			$tmp = [
				"title" =>
					$this->fuckhtml
					->getTextContent(
						$this->titledots(
							implode(": ", $title)
						)
					),
				"source" => [],
				"url" => htmlspecialchars_decode($image["snippet"]["url"])
			];
			
			// add preview URL
			$tmp["source"][]  = [
				"url" => htmlspecialchars_decode($image["viewerData"]["preview"][0]["url"]),
				"width" => (int)$image["viewerData"]["preview"][0]["w"],
				"height" => (int)$image["viewerData"]["preview"][0]["h"],
			];
			
			foreach($image["viewerData"]["dups"] as $dup){
				
				$tmp["source"][]  = [
					"url" => htmlspecialchars_decode($dup["url"]),
					"width" => (int)$dup["w"],
					"height" => (int)$dup["h"],
				];
			}
			
			$tmp["source"][] = [
				"url" =>
					preg_replace(
						'/^\/\//',
						"https://",
						htmlspecialchars_decode($image["viewerData"]["thumb"]["url"])
					),
				"width" => (int)$image["viewerData"]["thumb"]["w"],
				"height" => (int)$image["viewerData"]["thumb"]["h"]
			];
			
			$out["image"][] = $tmp;
		}
		
		return $out;
	}
	
	public function video($get){
		
		$this->backend = new backend("yandex_v");
		
		if($get["npt"]){
			
			[$get, $proxy] =
				$this->backend->get(
					$get["npt"],
					"video"
				);
			
			$get = json_decode($get, true);
		}else{
			
			if(strlen($get["s"]) === 0){
				
				throw new Exception("Search term is empty!");
			}
			
			$proxy = $this->backend->get_ip();
		}
		
		// https://yandex.com/video/search?text=skycamefalling&from=tabbar&format=json&ncrnd=7271&p=0&parent-reqid=&request={%22blocks%22%3A[{%22block%22%3A%22video-app%22%2C%22params%22%3A{}}]}&serpid=1777751040971457-16832445014469941403-balancer-l7leveler-kubr-yp-klg-151-BAL&yu=3091577281773194415&tmpl_version=releases-frontend-video-v1.1816.0__3bdc24e10a8a138a1194877428e220a3ca0dbc5a
		// https://yandex.com/video/search
		// ?text=skycamefalling
		// &from=tabbar
		// &format=json
		// &ncrnd=7271
		// &p=0
		// &parent-reqid=
		// &request={%22blocks%22%3A[{%22block%22%3A%22video-app%22%2C%22params%22%3A{}}]} {"blocks":[{"block":"video-app","params":{}}]}
		// &serpid=1777751040971457-16832445014469941403-balancer-l7leveler-kubr-yp-klg-151-BAL
		// &yu=3091577281773194415
		// &tmpl_version=releases-frontend-video-v1.1816.0__3bdc24e10a8a138a1194877428e220a3ca0dbc5a
		
		$params = [
			"text" => $get["s"],
			"from" => "tabbar",
			"format" => "json",
			"ncrnd" => 7271,
			"p" => 0,
			"parent-reqid" => "",
			"request" => json_encode((object)[
				"blocks" => [
					(object)[
						"block" => "video-app",
						"params" => (object)[]
					]
				]
			]),
			"serpid" => "1777751040971457-16832445014469941403-balancer-l7leveler-kubr-yp-klg-151-BAL",
			"yu" => 3091577281773194415,
			"tmpl_version" => "releases-frontend-video-v1.1816.0__3bdc24e10a8a138a1194877428e220a3ca0dbc5a"
		];
		
		if(isset($get["p"])){
			
			$params["p"] = $get["p"];
		}
		
		if($get["duration"] != "any"){
			
			$params["duration"] = $get["duration"];
		}
		
		if($get["time"] != "any"){
			
			$params["within"] = $get["time"];
		}
		
		/*
		$handle = fopen("scraper/yandex-video.json", "r");
		$json = fread($handle, filesize("scraper/yandex-video.json"));
		fclose($handle);
		*/
		try{
			$json =
				$this->get(
					$proxy,
					"https://yandex.com/video/search",
					$params,
					$get["nsfw"],
					"yandex_v"
				);
		}catch(Exception $error){
			
			throw new Exception("Could not fetch JSON");
		}
		
		$json = json_decode($json, true);
		
		if($json === null){
			
			throw new Exception("Could not parse JSON");
		}
		
		if(!isset($json["results"]["clips"]["items"])){
			
			throw new Exception("Yandex blocked this 4get instance. Please try again in 7~ minutes.");
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
		
		foreach($json["results"]["clips"]["items"] as $k => $data){
			
			if(isset($data["preview"]["posterSrc"])){
				
				$poster = $data["preview"]["posterSrc"];
				
				if(
					preg_match(
						'/^\/\//',
						$data["preview"]["posterSrc"]
					)
				){
					
					$poster = "https:" . $poster;
				}
				
				$thumb = [
					"ratio" => "16:9",
					"url" => $poster
				];
			}else{
				
				$thumb = [
					"ratio" => null,
					"url" => null
				];
			}
			
			$out["video"][] = [
				"title" => $data["relatedParams"]["text"],
				"description" => $this->titledots($data["description"]),
				"author" => [
					"name" =>
						isset($json["results"]["clips"]["dups"][$k]["host"]["secondPart"]["name"]) ?
						$json["results"]["clips"]["dups"][$k]["host"]["secondPart"]["name"] : null,
					"url" => 
						isset($json["results"]["clips"]["dups"][$k]["host"]["secondPart"]["origUrl"]) ?
						$json["results"]["clips"]["dups"][$k]["host"]["secondPart"]["origUrl"] : null,
					"avatar" => null
				],
				"date" =>
					isset($json["results"]["clips"]["dups"][$k]["date"]) ?
					strtotime($json["results"]["clips"]["dups"][$k]["date"]) : null,
				"duration" =>
					isset($json["results"]["clips"]["dups"][$k]["duration"]["value"]) ?
					(int)$json["results"]["clips"]["dups"][$k]["duration"]["value"] : null,
				"views" =>
					isset($json["results"]["clips"]["dups"][$k]["views"]["text"]) ?
					$this->parseviews($json["results"]["clips"]["dups"][$k]["views"]["text"]) : null,
				"thumb" => $thumb,
				"url" =>
					preg_replace(
						'/^http:\/\//',
						"https://",
						$data["relatedParams"]["related_url"]
					)
			];
		}
		
		// get npt
		if($json["results"]["search"]["hasNextPage"]){
			
			$get["p"] = (int)$json["results"]["search"]["currentPage"] + 1;
			
			$out["npt"] =
				$this->backend->store(
					json_encode($get),
					"video",
					$proxy
				);
		}
		
		return $out;
	}
	
	private function parseviews($number){
		
		// decimal should always be 1 number long
		$number = explode(" ", $number, 2);
		$number = $number[0];
		
		$unit = strtolower($number[strlen($number) - 1]);
		
		$tmp = explode(".", $number, 2);
		$number = (int)$number;
		
		if(count($tmp) === 2){
			
			$decimal = (int)$tmp[1];
		}else{
			
			$decimal = 0;
		}
		
		switch($unit){
			
			case "k":
				$exponant = 1000;
				break;
			
			case "m":
				$exponant = 1000000;
				break;
			
			case "b";
				$exponant = 1000000000;
				break;
			
			default:
				$exponant = 1;
				break;
		}
		
		return ($number * $exponant) + ($decimal * ($exponant / 10));
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
}
