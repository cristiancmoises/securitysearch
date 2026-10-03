<?php
// Network-free API error contract and missing-key provider-selection checks.
// Run from repository root: php tests/provider-api-regression.php
function api_check($condition,$label){if(!$condition){throw new RuntimeException($label);}}
if(($argv[1] ?? '') === '--selection'){
	// Native functions are disabled only in this child. No real key file or
	// transport is read/executed, including if operator credentials exist.
	function is_file($path){return false;}
	function file_get_contents($path){throw new LogicException('Unexpected key/file read');}
	function curl_exec($handle){throw new LogicException('Unexpected network call');}
	class config{
		public const DEFAULT_SCRAPER_WEB='google';
		public const DEFAULT_SCRAPER_IMAGES='google';
		public const DEFAULT_NSFW='no';
		public const GOOGLE_CX_ENDPOINT='audit-only';
	}
	require 'lib/frontend.php';
	$page=$argv[2];$selection=$argv[3];$_GET=[];$_COOKIE=[];
	if($selection==='explicit'){$_GET['scraper']='google_api';}
	if($selection==='saved'){$_COOKIE['scraper_'.$page]='google_api';}
	if($selection==='invalid'){$_GET['scraper']='not-a-real-provider';}
	$frontend=new frontend();[$provider,$filters]=$frontend->getscraperfilters($page);
	if(in_array($selection,['explicit','saved'],true)){
		api_check(get_class($provider)==='google_api','Explicit/saved API choice preserved');
		api_check(!class_exists('google_cse',false),'No CSE fallback loaded');
		api_check(str_contains($filters['scraper']['option']['google_api'],'not configured'),'Unavailable option labeled');
		$guarded=false;
		try{$provider->{$page==='web'?'web':'image'}(['npt'=>false]);}
		catch(Exception $error){
			api_check(!($error instanceof LogicException),'No key read or network before unavailable guard');
			$guarded=str_contains($error->getMessage(),'not configured');
		}
		api_check($guarded,'Unavailable API rejects before backend key access');
	}else{
		api_check(get_class($provider)==='google','Fresh/invalid choice still uses default');
		api_check(!isset($filters['scraper']['option']['google_api']),'Fresh picker hides unconfigured API');
	}
	echo "PASS selection ".$page." ".$selection."\n";
	exit;
}
require_once 'lib/search_health.php';
class api_failure_fixture {
	public array $calls=[];
	public function __construct(public Throwable $failure){}
	public function __call($method,$arguments){$this->calls[]=$method;throw $this->failure;}
}
class api_frontend_fixture {
	public static ?string $fail_at=null;
	public static ?Throwable $failure=null;
	private function checkpoint(string $stage):void{
		if(self::$fail_at===$stage){throw self::$failure;}
	}
	public function __construct(){$this->checkpoint('constructor');}
	public function getscraperfilters($page,$selection=null){
		$this->checkpoint('getscraperfilters');
		return [$GLOBALS['api_fixture_scraper'],[]];
	}
	public function parsegetfilters($parameters,$filters){
		$this->checkpoint('parsegetfilters');
		return ['s'=>'fixture search','npt'=>false];
	}
}
class api_bot_fixture {
	public static array $calls=[];
	public function __construct($frontend,$get,$filters,$page,$html){self::$calls[]=[$page,$html];}
}
$issues=[];
$private_marker='private-api-fixture-marker';
$generic_message='This provider could not complete the search. Retry later or choose another provider.';
$failures=[
	[new Exception($private_marker),$generic_message],
	[new Error($private_marker),$generic_message],
	[new TypeError($private_marker),$generic_message],
	[new upstream_search_failure('google','rate_limited',429),'Google is temporarily unavailable because it is rate-limiting this instance.'],
	[new upstream_search_failure('brave','challenge',200),'Brave requires human verification; no automated challenge retry was performed.']
];
if(!method_exists(search_health::class,'public_message'))$issues[]='Public failure-message boundary is missing';
else foreach($failures as [$failure,$message]){
	if(search_health::public_message($failure)!==$message)$issues[]='Public failure-message boundary lost redaction or safe classification';
}
foreach(['web'=>'web','images'=>'image','news'=>'news','videos'=>'video','music'=>'music'] as $endpoint=>$method){
	$source=file_get_contents('api/v1/'.$endpoint.'.php');
	if(preg_match('/catch\(Throwable \$e\)\{([\s\S]*)$/',$source,$match)!==1)$issues[]='API does not contain all provider failures '.$endpoint;
	foreach(['http_response_code(503);','header("Cache-Control: no-store");','header("Retry-After: 30");'] as $statement){
		api_check(str_contains($source,$statement),'Consistent API failure contract '.$endpoint);
	}
	$boundary='require_once "lib/search_health.php";';
	$offset=strpos($source,$boundary);
	api_check($offset!==false,'API health boundary exists');
	$program=substr($source,$offset+strlen($boundary));
	$program=str_replace(['include "lib/bot_protection.php";','new frontend()','new bot_protection('],['','new api_frontend_fixture()','new api_bot_fixture('],$program);
	foreach($failures as [$failure,$message]){
		api_frontend_fixture::$fail_at=null;api_frontend_fixture::$failure=null;api_bot_fixture::$calls=[];
		$scraper=new api_failure_fixture($failure);$GLOBALS['api_fixture_scraper']=$scraper;$_GET=[];http_response_code(200);ob_start();
		try{eval($program);}catch(Throwable $escaped){$issues[]='Provider failure escaped API '.$endpoint.' '.get_class($failure);}
		$output=ob_get_clean();
		if(http_response_code()!==503)$issues[]='API failure lost HTTP503 '.$endpoint;
		if(json_decode($output,true)!==['status'=>$message])$issues[]='API failure lost its safe one-field JSON shape '.$endpoint.' '.get_class($failure);
		if(str_contains($output,$private_marker))$issues[]='API exposed the private marker '.$endpoint;
		if($scraper->calls!==[$method])$issues[]='API changed explicit provider routing '.$endpoint;
		if(api_bot_fixture::$calls!==[[$endpoint,false]])$issues[]='API changed captcha invocation '.$endpoint;
	}
	foreach(['constructor','getscraperfilters','parsegetfilters'] as $stage){
		foreach([new Exception($private_marker),new Error($private_marker),new TypeError($private_marker)] as $failure){
			api_frontend_fixture::$fail_at=$stage;api_frontend_fixture::$failure=$failure;api_bot_fixture::$calls=[];
			$scraper=new api_failure_fixture(new LogicException('Provider must not execute after initialization failure'));
			$GLOBALS['api_fixture_scraper']=$scraper;$_GET=[];http_response_code(200);ob_start();
			try{eval($program);}catch(Throwable $escaped){$issues[]='Initialization failure escaped API '.$endpoint.' '.$stage.' '.get_class($failure);}
			$output=ob_get_clean();
			if(http_response_code()!==503)$issues[]='Initialization failure lost HTTP503 '.$endpoint.' '.$stage;
			if(json_decode($output,true)!==['status'=>$generic_message])$issues[]='Initialization failure lost safe JSON shape '.$endpoint.' '.$stage;
			if(str_contains($output,$private_marker))$issues[]='Initialization exposed private marker '.$endpoint.' '.$stage;
			if($scraper->calls!==[])$issues[]='API executed provider after initialization failure '.$endpoint.' '.$stage;
			if(api_bot_fixture::$calls!==[[$endpoint,false]])$issues[]='Initialization changed captcha invocation '.$endpoint.' '.$stage;
		}
	}
}
api_check($issues===[],implode("\n",$issues));
foreach(['web','images'] as $page){
	foreach(['fresh','explicit','saved','invalid'] as $selection){
		$command=[PHP_BINARY,'-d','disable_functions=is_file,file_get_contents,curl_exec',__FILE__,'--selection',$page,$selection];
		$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
		api_check(is_resource($process),'Selection subprocess started');fclose($pipes[0]);
		$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
		$code=proc_close($process);
		api_check($code===0,'Selection regression '.$page.' '.$selection.': '.$error);
		echo $output;
	}
}
echo "PASS: all five API initialization/provider error contracts and missing-key Google API selection/early guards.\n";
