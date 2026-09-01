<?php

function securitysearch_google_api_available(){

	static $available = null;
	if($available !== null){

		return $available;
	}

	$key_path = dirname(__DIR__) . "/data/api_keys/google_api.txt";
	if(!is_file($key_path) || !is_readable($key_path)){

		return $available = false;
	}

	$contents = file_get_contents($key_path);
	if($contents === false){

		return $available = false;
	}

	foreach(preg_split('/\R/', $contents) as $entry){

		$entry = ltrim($entry);
		if($entry !== "" && substr($entry, 0, 1) !== "#"){

			return $available = true;
		}
	}

	return $available = false;
}
