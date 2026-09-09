<?php
require_once __DIR__ . "/provider_http.php";
class backend{
	private $scraper;
	
	public function __construct($scraper){
		
		$this->scraper = $scraper;
	}
	
	/*
		Proxy stuff
	*/
	public function get_ip($proxy_index_raw = null){
		
		$pool = constant("config::PROXY_" . strtoupper($this->scraper));
		if($pool === false){
			
			// The request explicitly disables proxy use.
			return 'raw_ip::::';
		}
		
		// indent
		if($proxy_index_raw === null){
			
			$proxy_index_raw = apcu_inc("p." . $this->scraper);
		}
		
		$proxy_path = "data/proxies/" . $pool . ".txt";
		if(!is_readable($proxy_path)){

			throw new Exception("The configured proxy list is missing or unreadable by the web process.");
		}

		$proxylist = file_get_contents($proxy_path);
		if($proxylist === false){

			throw new Exception("The configured proxy list could not be read by the web process.");
		}
		$proxylist = array_map(function($entry){
			return trim($entry, " \t\r\n");
		}, explode("\n", $proxylist));

		// ignore empty or commented lines
		$proxylist = array_filter($proxylist, function($entry){
			$entry = ltrim($entry);
			return strlen($entry) > 0 && substr($entry, 0, 1) != "#";
		});
		
		$proxylist = array_values($proxylist);
		
		if(count($proxylist) === 0){
			
			throw new Exception("A proxy list was specified but it's empty!");
		}
		
		$selected = $proxylist[$proxy_index_raw % count($proxylist)];
		$this->parse_proxy_line($selected);
		return $selected;
	}

	private function parse_proxy_line(string $line){
		$line = trim($line, " \t\r\n");
		// A proxy typo must never fall through to direct/ambient egress. Reject
		// control characters before parsing, without reflecting credentials.
		if($line === "" || preg_match('/[\x00-\x1f\x7f]/', $line)){
			throw new Exception("The configured proxy entry is malformed.");
		}
		$parts = explode(":", $line, 5);
		if(count($parts) !== 5){
			throw new Exception("The configured proxy entry must have type:host:port:username:password fields.");
		}
		[$type, $address, $port, $username, $password] = $parts;
		if($type === "raw_ip"){
			if($line !== "raw_ip::::"){
				throw new Exception("The configured direct-egress entry must be exactly raw_ip::::.");
			}
			return $parts;
		}
		if(!in_array($type, ["http", "https", "socks4", "socks4a", "socks5", "socks5_hostname", "socks5h", "socks5a"], true)){
			throw new Exception("The configured proxy protocol is unsupported.");
		}
		// The legacy colon-delimited format supports DNS names and IPv4 only.
		// IPv6 literals are ambiguous here: use a DNS name instead of guessing.
		$valid_address = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
		if(!$valid_address && !preg_match('/^[0-9.]+$/', $address)){
			$valid_address = filter_var($address, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
		}
		if(!$valid_address || strpos($address, ":") !== false){
			throw new Exception("The configured proxy host is invalid; use a DNS name or IPv4 address.");
		}
		if(preg_match('/\A[0-9]+\z/', $port) !== 1 || (int)$port < 1 || (int)$port > 65535){
			throw new Exception("The configured proxy port must be between 1 and 65535.");
		}
		if($username === "" && $password !== ""){
			throw new Exception("The configured proxy password requires a username.");
		}
		// Split at most five fields: colons in passwords remain supported.
		return [$type, $address, $port, $username, $password];
	}
	
	// this function is also called directly on nextpage
	public function assign_proxy(&$curlproc, string $ip){
		// parse proxy line
		[
			$type,
			$address,
			$port,
			$username,
			$password
		] = $this->parse_proxy_line($ip);
		// Explicit instance routing wins over ambient no_proxy/NO_PROXY, which
		// could otherwise bypass a configured pool even with CURLOPT_PROXY set.
		curl_setopt($curlproc, CURLOPT_NOPROXY, "");
		curl_setopt($curlproc, CURLOPT_PROXYUSERPWD, "");
		
		switch($type){
			
			case "raw_ip":
				// Empty (not null) disables http_proxy/https_proxy/ALL_PROXY too.
				curl_setopt($curlproc, CURLOPT_PROXY, "");
				$this->assign_source_interface($curlproc);
				return;
			
			case "http":
			case "https":
				curl_setopt($curlproc, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
				curl_setopt($curlproc, CURLOPT_PROXY, $type . "://" . $address . ":" . $port);
				break;
			
			case "socks4":
				curl_setopt($curlproc, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
				curl_setopt($curlproc, CURLOPT_PROXY, $address . ":" . $port);
				break;
			
			case "socks5":
				curl_setopt($curlproc, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
				curl_setopt($curlproc, CURLOPT_PROXY, $address . ":" . $port);
				break;
			
			case "socks4a":
				curl_setopt($curlproc, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4A);
				curl_setopt($curlproc, CURLOPT_PROXY, $address . ":" . $port);
				break;
			
			case "socks5_hostname":
			case "socks5h":
			case "socks5a":
				curl_setopt($curlproc, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
				curl_setopt($curlproc, CURLOPT_PROXY, $address . ":" . $port);
				break;
		}
		
		if($username != ""){
			
			curl_setopt($curlproc, CURLOPT_PROXYUSERPWD, $username . ":" . $password);
		}
	}

	private function assign_source_interface(&$curlproc){

		$google_scrapers = ["google", "google_cse", "google_api"];
		$source_scraper = in_array($this->scraper, $google_scrapers, true) ? "GOOGLE" : strtoupper($this->scraper);
		$source_constant = "config::SOURCE_IP_" . $source_scraper;
		if(!defined($source_constant)){

			return;
		}

		$source_ip = constant($source_constant);
		if($source_ip === false || $source_ip === null || $source_ip === ""){

			return;
		}

		if(!is_string($source_ip) || filter_var($source_ip, FILTER_VALIDATE_IP) === false){

			throw new Exception("The configured provider source IP is invalid.");
		}

		$interfaces = net_get_interfaces();
		$source_packed = inet_pton(preg_replace('/%.+$/', '', $source_ip));
		$assigned = false;
		if(is_array($interfaces)){

			foreach($interfaces as $interface){

				foreach(($interface["unicast"] ?? []) as $address){

					$local_ip = preg_replace('/%.+$/', '', (string)($address["address"] ?? ""));
					if($local_ip !== "" && inet_pton($local_ip) === $source_packed){

						$assigned = true;
						break 2;
					}
				}
			}
		}

		if(!$assigned){

			throw new Exception("The configured provider source IP is not assigned to this container. Remove FOURGET_SOURCE_IP_* or configure container networking for that address.");
		}

		curl_setopt($curlproc, CURLOPT_INTERFACE, $source_ip);
	}
	
	// API key rotation
	public function get_key(){
		
		$keys = file_get_contents("data/api_keys/" . $this->scraper . ".txt");
		$keys = explode("\n", $keys);
		
		$keys = array_filter($keys, function($entry){
			$entry = ltrim($entry);
			return strlen($entry) > 0 && substr($entry, 0, 1) != "#";
		});
		
		$keys = array_values($keys);
		
		if(count($keys) === 0){
			
			throw new Exception("Please specify API keys in data/api_keys/" . $this->scraper . ".txt");
		}
		
		$increment = apcu_inc("s." . $this->scraper) % count($keys);
		return [
			"key" => $keys[$increment],
			"increment" => $increment
		];
	}
	
	
	/*
		Next page stuff
	*/
	public function store(string $payload, string $page, string $proxy){
		
		$key = sodium_crypto_secretbox_keygen();
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		
		$requestid = apcu_inc("requestid");
		
		apcu_store(
			$page[0] . "." . // first letter of page name
			$this->scraper . // scraper name
			$requestid,
			[
				$nonce,
				$proxy,
				// compress and encrypt
				sodium_crypto_secretbox(
					gzdeflate($payload),
					$nonce,
					$key
				)
			],
			900 // cache information for 15 minutes
		);

		return 
			$this->scraper . $requestid . "." .
			rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
	}
	
	public function get(string $npt, string $page){
		
		$page = $page[0];
		$explode = explode(".", $npt, 2);
		
		if(count($explode) !== 2 || preg_match('/\A[A-Za-z0-9_]+\.[A-Za-z0-9_-]{43}\z/', $npt) !== 1){
			
			throw new Exception("Malformed nextPageToken!");
		}
		
		$apcu = $page . "." . $explode[0];
		$key = $explode[1];
		
		$payload = apcu_fetch($apcu);
		
		if(!is_array($payload) || count($payload) !== 3 || !is_string($payload[0]) || strlen($payload[0]) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES || !is_string($payload[2])){
			
			throw new Exception("The next page token is invalid or has expired!");
		}
		
		$key = base64_decode(strtr($key, '-_', '+/') . '=', true);
		if(!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES){
			throw new Exception("Malformed nextPageToken!");
		}
		// Authenticate before decompressing: malformed input must not reach sodium
		// with a wrong-length key, or feed false into gzinflate and emit warnings.
		$plaintext = sodium_crypto_secretbox_open($payload[2], $payload[0], $key);
		$payload[2] = $plaintext === false ? false : @gzinflate($plaintext, 4194304);
		
		if($payload[2] === false){
			
			throw new Exception("The next page token is invalid or has expired!");
		}
		
		// remove the key after using successfully
		apcu_delete($apcu);
		
		return [
			$payload[2], // data
			$payload[1] // proxy
		];
	}
}
