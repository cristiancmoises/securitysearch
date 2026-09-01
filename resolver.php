<?php
include_once __DIR__ . "/lib/security_headers_minimal.php";

$resolver = new resolver();

class resolver{
	
	public const resolvers = [
		"sc"
	];
	
	public function __construct(){
		
		include "data/config.php";
		
		if(
			!isset($_GET["scraper"]) ||
			!is_string($_GET["scraper"]) ||
			!in_array($_GET["scraper"], self::resolvers)
		){
			
			$this->do400("Missing or invalid scraper");
			return;
		}
		
		if(
			!isset($_GET["target"]) ||
			!is_string($_GET["target"])
		){
			
			$this->do400("Missing or invalid target");
			return;
		}
		
		$scraper = $_GET["scraper"];
		$target = $_GET["target"];
		
		try{
			
			include "resolve/{$scraper}.php";
			$resolver = new $scraper();
			$link = $resolver->resolve($target);
			
			if(is_string($link)){
				
				header("Location: {$link}");
			}
		}catch(Exception $error){
			
			$this->do404("Unable to resolve URL: " . $error->getMessage());
		}
	}
	
	public function do400($message){
		
		header("Content-Type: text/plain");
		http_response_code(400);
		echo $message;
	}
	
	public function do404($message){
		
		header("Content-Type: text/plain");
		http_response_code(404);
		echo $message;
	}
}
