<?php

class proxy{
	
	public const req_web = 0;
	public const req_image = 1;
	private $cache;
	private $url;
	private $format;
	private $empty_header;
	private $cont;
	private $headers_tmp;
	private $headers;
	
	public function __construct($cache = true){
		
		$this->cache = $cache;
	}

	private function imagereferer($url){

		$parts = parse_url($url);
		if(
			!is_array($parts) ||
			!isset($parts["scheme"], $parts["host"]) ||
			!in_array(strtolower($parts["scheme"]), ["http", "https"], true) ||
			$parts["host"] === ""
		){

			return null;
		}

		$host = $parts["host"];
		if(strpos($host, ":") !== false && $host[0] !== "["){

			$host = "[" . $host . "]";
		}
		$referer = strtolower($parts["scheme"]) . "://" . $host;
		if(isset($parts["port"])){

			$referer .= ":" . (int)$parts["port"];
		}

		$path = isset($parts["path"]) && is_string($parts["path"]) ? $parts["path"] : "/";
		if($path === "" || $path[0] !== "/"){

			$path = "/";
		}
		$last_slash = strrpos($path, "/");
		$referer .= $last_slash === false ? "/" : substr($path, 0, $last_slash + 1);

		return
			strlen($referer) <= 8192 && preg_match('/[\r\n]/', $referer) !== 1 ?
			$referer :
			null;
	}
	
	public function do404(){
		
		http_response_code(404);
		header("Content-Type: image/png");
		
		$handle = fopen("lib/img404.png", "r");
		echo fread($handle, filesize("lib/img404.png"));
		fclose($handle);
		
		die();
		return;
	}
	
	public function getabsoluteurl($path, $relative){

		$path = trim((string)$path);
		if($path === ""){

			throw new Exception("Broken redirect");
		}

		try{

			$absolute = parse_url($path);
			$base = parse_url($relative);
		}catch(Throwable $error){

			throw new Exception("Broken redirect");
		}

		if(is_array($absolute) && isset($absolute["scheme"])){

			return $path;
		}
		if(!is_array($base) || !isset($base["scheme"], $base["host"])){

			throw new Exception("Broken redirect");
		}

		if(substr($path, 0, 2) === "//"){

			return strtolower($base["scheme"]) . ":" . $path;
		}

		$authority = strtolower($base["scheme"]) . "://";
		if(isset($base["user"])){

			$authority .= $base["user"];
			if(isset($base["pass"])){

				$authority .= ":" . $base["pass"];
			}
			$authority .= "@";
		}
		$authority .= $base["host"];
		if(isset($base["port"])){

			$authority .= ":" . $base["port"];
		}

		$fragment = strpos($path, "#");
		if($fragment !== false){

			$path = substr($path, 0, $fragment);
		}
		if($path === ""){

			$url = $authority . ($base["path"] ?? "/");
			return isset($base["query"]) ? $url . "?" . $base["query"] : $url;
		}
		if($path[0] === "?"){

			return $authority . ($base["path"] ?? "/") . $path;
		}

		$query = "";
		$query_offset = strpos($path, "?");
		if($query_offset !== false){

			$query = substr($path, $query_offset);
			$path = substr($path, 0, $query_offset);
		}

		if(isset($path[0]) && $path[0] === "/"){

			$resolved_path = $path;
		}else{

			$base_path = $base["path"] ?? "/";
			$slash = strrpos($base_path, "/");
			$resolved_path = ($slash === false ? "/" : substr($base_path, 0, $slash + 1)) . $path;
		}

		return $authority . $this->removedotsegments($resolved_path) . $query;
	}

	private function removedotsegments($path){

		$segments = explode("/", $path);
		$output = [];
		foreach($segments as $segment){

			if($segment === "."){

				continue;
			}
			if($segment === ".."){

				if(count($output) > 1){

					array_pop($output);
				}
				continue;
			}
			$output[] = $segment;
		}

		$result = implode("/", $output);
		if(
			(substr($path, -2) === "/." || substr($path, -3) === "/..") &&
			substr($result, -1) !== "/"
		){

			$result .= "/";
		}

		return $result === "" ? "/" : $result;
	}

	private function resolvepublictarget($url){

		try{

			$url_parts = parse_url($url);
		}catch(Throwable $error){

			return false;
		}

		if(
			!is_array($url_parts) ||
			!isset($url_parts["scheme"], $url_parts["host"]) ||
			isset($url_parts["user"]) || isset($url_parts["pass"]) ||
			!in_array(strtolower($url_parts["scheme"]), ["http", "https"], true)
		){

			return false;
		}

		$scheme = strtolower($url_parts["scheme"]);
		$host = trim($url_parts["host"], "[]");
		if(
			$host === "" ||
			preg_match('/[\\x00-\\x20\\x7f\\/\\?#@]/', $host) === 1
		){

			return false;
		}

		$port = $url_parts["port"] ?? ($scheme === "https" ? 443 : 80);
		if(!is_int($port) || $port < 1 || $port > 65535){

			return false;
		}

		$literal = filter_var($host, FILTER_VALIDATE_IP);
		if($literal !== false){

			$addresses = [$literal];
		}else{

			$lookup_host = rtrim($host, ".");
			if(
				$lookup_host === "" ||
				filter_var($lookup_host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
			){

				return false;
			}

			$addresses = [];
			$records = @dns_get_record($lookup_host, DNS_A | DNS_AAAA);
			if(is_array($records)){

				foreach($records as $record){

					if(isset($record["ip"])){

						$addresses[] = $record["ip"];
					}elseif(isset($record["ipv6"])){

						$addresses[] = $record["ipv6"];
					}
				}
			}

			if($addresses === []){

				$fallback = @gethostbynamel($lookup_host . ".");
				if(is_array($fallback)){

					$addresses = $fallback;
				}
			}
		}

		$addresses = array_values(array_unique($addresses));
		if($addresses === [] || count($addresses) > 32){

			return false;
		}
		foreach($addresses as $address){

			if(
				filter_var(
					$address,
					FILTER_VALIDATE_IP,
					FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
				) === false
			){

				return false;
			}
		}

		usort($addresses, function($left, $right){

			return (int)(filter_var($left, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) <=>
				(int)(filter_var($right, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false);
		});

		return [
			"host" => $host,
			"port" => $port,
			"address" => $addresses[0],
			"literal" => $literal !== false
		];
	}

	public function validateurl($url){

		return $this->resolvepublictarget($url) !== false;
	}
	
	public function get($url, $reqtype = self::req_web, $acceptallcodes = false, $referer = null, $redirectcount = 0, $max_bytes = 100000000, $request_budget = null, $image_accept = null){

		$max_bytes = is_int($max_bytes) && $max_bytes > 0 ? $max_bytes : 100000000;
		if($request_budget === null){

			$request_budget = (object)[
				"deadline" => hrtime(true) + 30000000000,
				"remaining_bytes" => $max_bytes,
				"remaining_wire_bytes" => $max_bytes
			];
		}
		
		if($redirectcount >= 5){
			
			throw new Exception("Too many redirects");
		}
		
		if($url == "https://i.imgur.com/removed.png"){
			
			throw new Exception("Encountered imgur 404");
		}
		
		// sanitize URL
		$target = $this->resolvepublictarget($url);
		if($target === false){
			
			throw new Exception("Invalid URL");
		}
		$remaining_milliseconds = (int)ceil(($request_budget->deadline - hrtime(true)) / 1000000);
		if($remaining_milliseconds < 1){

			throw new Exception("Remote request exceeded the configured time limit");
		}
		$hop_max_bytes = $request_budget->remaining_bytes;
		if(!isset($request_budget->remaining_wire_bytes)){

			$request_budget->remaining_wire_bytes = $max_bytes;
		}
		$hop_max_wire_bytes = $request_budget->remaining_wire_bytes;
		if($hop_max_bytes < 1 || $hop_max_wire_bytes < 1){

			throw new Exception("Remote payload exceeds the configured byte limit");
		}
		
		$this->clientcache();
		
		$curl = curl_init();
		
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_ENCODING, ""); // default encoding
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_PROXY, "");
		curl_setopt($curl, CURLOPT_NOPROXY, "*");
		if(defined("CURLOPT_PROTOCOLS") && defined("CURLPROTO_HTTP") && defined("CURLPROTO_HTTPS")){

			curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}
		if(!$target["literal"]){

			$pinned_address = filter_var($target["address"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
				? "[" . $target["address"] . "]"
				: $target["address"];
			curl_setopt(
				$curl,
				CURLOPT_RESOLVE,
				[$target["host"] . ":" . $target["port"] . ":" . $pinned_address]
			);
		}
		
		switch($reqtype){
			case self::req_web:
				curl_setopt(
					$curl,
					CURLOPT_HTTPHEADER,
					[
						"User-Agent: " . config::USER_AGENT,
						"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
						"Accept-Language: en-US,en;q=0.5",
						"Accept-Encoding: gzip, deflate",
						"DNT: 1",
						"Connection: keep-alive",
						"Upgrade-Insecure-Requests: 1",
						"Sec-Fetch-Dest: document",
						"Sec-Fetch-Mode: navigate",
						"Sec-Fetch-Site: none",
						"Sec-Fetch-User: ?1"
					]
				);
				break;
			
			case self::req_image:
				
				if($referer === null){
					$referer = $this->imagereferer($url);
				}
				
				if(
					!is_string($image_accept) ||
					$image_accept === "" ||
					strlen($image_accept) > 512 ||
					preg_match('/[\r\n]/', $image_accept) === 1
				){

					$image_accept = "image/avif,image/webp,*/*";
				}

				$image_headers =
					[
						"User-Agent: " . config::USER_AGENT,
						"Accept: " . $image_accept,
						"Accept-Language: en-US,en;q=0.5",
						"Accept-Encoding: gzip, deflate",
						"DNT: 1",
						"Connection: keep-alive"
					];
				if(
					is_string($referer) &&
					$referer !== "" &&
					strlen($referer) <= 8192 &&
					preg_match('/[\r\n]/', $referer) !== 1
				){

					$image_headers[] = "Referer: " . $referer;
				}
				curl_setopt($curl, CURLOPT_HTTPHEADER, $image_headers);
				break;
		}
		
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $remaining_milliseconds);
		curl_setopt($curl, CURLOPT_TIMEOUT_MS, $remaining_milliseconds);
		
		// limit size of payloads
		$response_body = "";
		$response_headers = [];
		$header_bytes = 0;
		$payload_too_large = false;
		$headers_too_large = false;
		curl_setopt(
			$curl,
			CURLOPT_HEADERFUNCTION,
			function($curl, $line) use (&$response_headers, &$header_bytes, &$headers_too_large){

				$line_length = strlen($line);
				$header_bytes += $line_length;
				if($header_bytes > 1048576){

					$headers_too_large = true;
					return 0;
				}

				$line = rtrim($line, "\r\n");
				if(preg_match('#^HTTP/[0-9.]+\s+[0-9]{3}(?:\s|$)#i', $line)){

					// Keep the final response block if a proxy or 100 Continue response
					// produced an earlier set of headers.
					$response_headers = [$line];
				}elseif($response_headers !== []){

					$response_headers[] = $line;
				}

				return $line_length;
			}
		);
		curl_setopt(
			$curl,
			CURLOPT_WRITEFUNCTION,
			function($curl, $chunk) use (&$response_body, &$payload_too_large, $request_budget){

				$chunk_length = strlen($chunk);
				if($chunk_length > $request_budget->remaining_bytes){

					$payload_too_large = true;
					return 0;
				}

				$response_body .= $chunk;
				$request_budget->remaining_bytes -= $chunk_length;
				return $chunk_length;
			}
		);
		curl_setopt($curl, CURLOPT_BUFFERSIZE, 65536);
		curl_setopt($curl, CURLOPT_NOPROGRESS, false);
		curl_setopt(
			$curl,
			CURLOPT_PROGRESSFUNCTION,
			function($curl, $download_total, $downloaded_now, $upload_total, $uploaded_now) use ($hop_max_wire_bytes, &$payload_too_large){
			
			// Bound buffered downloads; animated grid previews use a lower cap.
			if($download_total > $hop_max_wire_bytes || $downloaded_now > $hop_max_wire_bytes){

				$payload_too_large = true;
				return 1;
			}

			return 0;
		});
		
		curl_exec($curl);
		$curl_errno = curl_errno($curl);
		$curl_error = curl_error($curl);
		$wire_bytes = defined("CURLINFO_SIZE_DOWNLOAD_T")
			? curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD_T)
			: curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);
		curl_close($curl);

		if($curl_errno){

			if($payload_too_large){

				throw new Exception("Remote payload exceeds the configured byte limit");
			}
			if($headers_too_large){

				throw new Exception("Remote response headers exceed the configured byte limit");
			}

			throw new Exception($curl_error);
		}
		$wire_bytes = max(0, (int)$wire_bytes);
		if($wire_bytes > $request_budget->remaining_wire_bytes){

			throw new Exception("Remote payload exceeds the configured byte limit");
		}
		$request_budget->remaining_wire_bytes -= $wire_bytes;
		
		$headers = [];
		$http = null;
		if(
			isset($response_headers[0]) &&
			preg_match('#^HTTP/([0-9.]+)\s+([0-9]{3})(?:\s|$)#i', $response_headers[0], $status)
		){

			$http = [
				"version" => (float)$status[1],
				"code" => (int)$status[2]
			];
		}

		if($http === null){

			throw new Exception("Remote server returned a malformed HTTP response");
		}

		foreach(array_slice($response_headers, 1) as $header){

			if($header === ""){

				break;
			}

			$header = explode(":", $header, 2);
			
			// malformed headers
			if(count($header) !== 2){ continue; }
			
			$headers[strtolower(trim($header[0]))] = trim($header[1]);
		}

		$body = $response_body;
		
		// check http code
		if(
			$http["code"] >= 300 &&
			$http["code"] <= 309
		){
			
			// redirect
			if(!isset($headers["location"])){
				
				throw new Exception("Broken redirect");
			}
			
			$redirectcount++;
			
			return $this->get(
				$this->getabsoluteurl($headers["location"], $url),
				$reqtype,
				$acceptallcodes,
				$referer,
				$redirectcount,
				$max_bytes,
				$request_budget,
				$image_accept
			);
		}else{
			if(
				$acceptallcodes === false &&
				$http["code"] > 300
			){
				
				throw new Exception("Remote server returned an error code! ({$http["code"]})");
			}
		}
		
		// check if data is okay
		switch($reqtype){
			
			case self::req_image:
				
				$format = false;
				
				if(isset($headers["content-type"])){
					
					if(stripos($headers["content-type"], "text/html") !== false){
						
						throw new Exception("Server returned html");
					}
					
					if(
						preg_match(
							'/image\/([^ ]+)/i',
							$headers["content-type"],
							$match
						)
					){
						
						$format = strtolower($match[1]);
						
						if(substr(strtolower($format), 0, 2) == "x-"){
							
							$format = substr($format, 2);
						}
					}
				}
				
				return [
					"http" => $http,
					"format" => $format,
					"headers" => $headers,
					"body" => $body
				];
				break;
			
			default:
				
				return [
					"http" => $http,
					"headers" => $headers,
					"body" => $body
				];
				break;
		}
		
		return;
	}
	
	public function stream_linear_image($url, $referer = null){
		
		$this->stream($url, $referer, "image");
	}
	
	public function stream_linear_audio($url, $referer = null){
		
		$this->stream($url, $referer, "audio");
	}
	
	private function stream($url, $referer, $format, $redirectcount = 0, $deadline = null){

		if($deadline === null){

			$this->clientcache();
			$deadline = hrtime(true) + 30000000000;
		}
		if($redirectcount >= 5){

			throw new Exception("Too many redirects");
		}

		$target = $this->resolvepublictarget($url);
		if($target === false){

			throw new Exception("Invalid URL");
		}
		$remaining_milliseconds = (int)ceil(($deadline - hrtime(true)) / 1000000);
		if($remaining_milliseconds < 1){

			throw new Exception("Remote request exceeded the configured time limit");
		}

		if($referer === null){

			$referer = $this->imagereferer($url);
		}

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_ENCODING, "");
		curl_setopt($curl, CURLOPT_PROXY, "");
		curl_setopt($curl, CURLOPT_NOPROXY, "*");
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
		if(defined("CURLOPT_PROTOCOLS") && defined("CURLPROTO_HTTP") && defined("CURLPROTO_HTTPS")){

			curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		}
		if(!$target["literal"]){

			$pinned_address = filter_var($target["address"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
				? "[" . $target["address"] . "]"
				: $target["address"];
			curl_setopt(
				$curl,
				CURLOPT_RESOLVE,
				[$target["host"] . ":" . $target["port"] . ":" . $pinned_address]
			);
		}

		$accept = $format === "audio"
			? "audio/webm,audio/ogg,audio/wav,audio/*;q=0.9,application/ogg;q=0.7,video/*;q=0.6,*/*;q=0.5"
			: "image/avif,image/webp,*/*";
		$stream_headers =
			[
				"User-Agent: " . config::USER_AGENT,
				"Accept: " . $accept,
				"Accept-Language: en-US,en;q=0.5",
				"Accept-Encoding: gzip, deflate, br",
				"DNT: 1",
				"Connection: keep-alive"
			];
		if(
			is_string($referer) &&
			$referer !== "" &&
			strlen($referer) <= 8192 &&
			preg_match('/[\r\n]/', $referer) !== 1
		){

			$stream_headers[] = "Referer: " . $referer;
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $stream_headers);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT_MS, $remaining_milliseconds);
		curl_setopt($curl, CURLOPT_TIMEOUT_MS, $remaining_milliseconds);

		$status = null;
		$headers = [];
		$header_bytes = 0;
		$headers_ready = false;
		$callback_error = null;
		curl_setopt(
			$curl,
			CURLOPT_HEADERFUNCTION,
			function($curl, $line) use (
				&$status,
				&$headers,
				&$header_bytes,
				&$headers_ready,
				&$callback_error,
				$format,
				$url
			){

				$line_length = strlen($line);
				$header_bytes += $line_length;
				if($header_bytes > 1048576){

					$callback_error = "Remote response headers exceed the configured byte limit";
					return 0;
				}

				$header = rtrim($line, "\r\n");
				if(preg_match('#^HTTP/[0-9.]+\s+([0-9]{3})(?:\s|$)#i', $header, $match)){

					$status = (int)$match[1];
					$headers = [];
					$headers_ready = false;
					return $line_length;
				}
				if($header !== ""){

					$pair = explode(":", $header, 2);
					if(count($pair) === 2){

						$headers[strtolower(trim($pair[0]))] = trim($pair[1]);
					}
					return $line_length;
				}

				if($status !== 200){

					return $line_length;
				}
				if(!isset($headers["content-type"])){

					$callback_error = "Resource is not an {$format} (no Content-Type)";
					return 0;
				}

				$content_type = strtolower($headers["content-type"]);
				$octet_stream = stripos($content_type, "octet-stream") !== false;
				if(stripos($content_type, $format . "/") === false && !$octet_stream){

					$callback_error = "Resource reported invalid Content-Type";
					return 0;
				}

				$filetype = $octet_stream ? "jpeg" : explode("/", explode(";", $content_type, 2)[0], 2)[1];
				header("Content-Type: {$format}/{$filetype}");
				$encoded = isset($headers["content-encoding"]) &&
					strtolower(trim($headers["content-encoding"])) !== "identity";
				if(
					!$encoded &&
					isset($headers["content-length"]) &&
					preg_match('/\A[0-9]+\z/D', $headers["content-length"])
				){

					header("Content-Length: " . $headers["content-length"]);
				}
				$this->getfilenameheader($headers, $url, $filetype);
				$headers_ready = true;
				return $line_length;
			}
		);
		curl_setopt(
			$curl,
			CURLOPT_WRITEFUNCTION,
			function($curl, $data) use (&$status, &$headers_ready, &$callback_error){

				$length = strlen($data);
				if($status >= 300 && $status <= 309){

					return $length;
				}
				if($status !== 200 || !$headers_ready){

					$callback_error = "Remote server returned a non-200 response";
					return 0;
				}

				echo $data;
				return $length;
			}
		);

		curl_exec($curl);
		$curl_errno = curl_errno($curl);
		$curl_error = curl_error($curl);
		curl_close($curl);

		if($callback_error !== null){

			throw new Exception($callback_error);
		}
		if($curl_errno){

			throw new Exception($curl_error);
		}
		if($status >= 300 && $status <= 309){

			if(!isset($headers["location"])){

				throw new Exception("Broken redirect");
			}
			return $this->stream(
				$this->getabsoluteurl($headers["location"], $url),
				$referer,
				$format,
				$redirectcount + 1,
				$deadline
			);
		}
		if($status !== 200){

			throw new Exception("Remote server returned a non-200 response");
		}
	}
	
	public function getfilenameheader($headers, $url, $filetype = "jpg"){
		
		// get filename from content-disposition header
		if(isset($headers["content-disposition"])){
			
			preg_match(
				'/filename=([^;]+)/',
				$headers["content-disposition"],
				$filename
			);
			
			if(isset($filename[1])){
				
				header("Content-Disposition: filename=\"" . trim($filename[1], "\"'") . "." . $filetype . "\"");
				return;
			}
		}
		
		// get filename from URL
		$filename = parse_url($url, PHP_URL_PATH);
		
		if($filename === null){
			
			// everything failed! rename file to domain name
			header("Content-Disposition: filename=\"" . parse_url($url, PHP_URL_HOST) . "." . $filetype . "\"");
			return;
		}
		
		// remove extension from filename
		$filename =
			explode(
				".",
				basename($filename)
			);
		
		if(count($filename) > 1){
			array_pop($filename);
		}
		
		$filename = implode(".", $filename);
		
		header("Content-Disposition: inline; filename=\"" . $filename . "." . $filetype . "\"");
		return;
	}
	
	public function getimageformat($payload, &$imagick){
		
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$format = $finfo->buffer($payload["body"]);
		
		if($format === false){
			
			if($payload["format"] === false){
				
				header("X-Error: Could not parse format");
				$this->favicon404();
			}
			
			$format = $payload["format"];
		}else{
			
			$format_tmp = explode("/", $format, 2);
			
			if($format_tmp[0] == "image"){
				
				$format_tmp = strtolower($format_tmp[1]);
				
				if(substr($format_tmp, 0, 2) == "x-"){
					
					$format_tmp = substr($format_tmp, 2);
				}
				
				$format = $format_tmp;
			}
		}
		
		switch($format){
			
			case "tiff": $format = "gif"; break;
			case "vnd.microsoft.icon": $format = "ico"; break;
			case "icon": $format = "ico"; break;
			case "svg+xml": $format = "svg"; break;
		}
		
		$imagick = new Imagick();
		
		if(
			!in_array(
				$format,
				array_map("strtolower", $imagick->queryFormats())
			)
		){
			
			// format could not be found, but imagemagick can
			// Some servers omit a usable file extension, so infer it when possible.
			$format = false;
		}
		
		return $format;
	}
	
	public function clientcache(){
		// A client date is not proof that a remote image still exists or is
		// unchanged. Never fabricate a 1970 validator or return 304 before URL
		// validation/fetch. Keep the method for existing proxy callers; their
		// explicit response Cache-Control remains responsible for reuse.
		return;
	}
}
