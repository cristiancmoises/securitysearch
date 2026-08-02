<?php

//
// Reference
// https://github.com/TecharoHQ/anubis/blob/ecc716940e34ebe7249974f2789a99a2c7115e4e/web/js/proof-of-work.mjs
//

class anubis{
	
	public function __construct(){
		
		include_once "fuckhtml.php";
		$this->fuckhtml = new fuckhtml();
	}
	
	public function scrape($html){
		
		$this->fuckhtml->load($html);
		
		$script =
			$this->fuckhtml
			->getElementById(
				"anubis_challenge",
				"script"
			);
		
		if($script === false){
			
			throw new Exception("Failed to scrape anubis challenge data");
		}
		
		$script =
			json_decode(
				$this->fuckhtml
				->getTextContent(
					$script
				),
				true
			);
		
		if($script === null){
			
			throw new Exception("Failed to decode anubis challenge data");
		}
		
		if(
			!isset($script["challenge"]) ||
			!isset($script["rules"]["difficulty"]) ||
			!is_int($script["rules"]["difficulty"]) ||
			!is_string($script["challenge"])
		){
			
			throw new Exception("Found invalid challenge data");
		}
		
		return $this->rape($script["challenge"], $script["rules"]["difficulty"]);
	}
	
	private function is_valid_hash($hash, $difficulty){
		
		for ($i=0; $i<$difficulty; $i++) {
			
			$index = (int)floor($i / 2);
			$nibble = $i % 2;
			
			$byte = ord($hash[$index]);
			$nibble = ($byte >> ($nibble === 0 ? 4 : 0)) & 0x0f;
			
			if($nibble !== 0){
				return false;
			}
		}
		
		return true;
	}
	
	public function rape($data, $difficulty = 5){
		
		$nonce = 0;
		
		while(true){
			
			$hash_binary = hash("sha256", $data . $nonce, true);
			
			if($this->is_valid_hash($hash_binary, $difficulty)){
				
				$hash_hex = bin2hex($hash_binary);
				
				return [
					"response" => $hash_hex,
					//"data" => $data,
					//"difficulty" => $difficulty,
					"nonce" => $nonce
				];
			}
			
			$nonce++;
		}
	}
}
