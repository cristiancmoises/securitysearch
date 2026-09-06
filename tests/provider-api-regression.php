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
foreach(['web','images','news','videos','music'] as $endpoint){
	$source=file_get_contents('api/v1/'.$endpoint.'.php');
	api_check(preg_match('/catch\(Exception \$e\)\{([\s\S]*)$/',$source,$match)===1,'API catch exists');
	foreach(['http_response_code(503);','header("Cache-Control: no-store");','header("Retry-After: 30");'] as $statement){
		api_check(str_contains($match[1],$statement),'Consistent API failure contract '.$endpoint);
	}
}
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
echo "PASS: all five API error contracts and missing-key Google API selection/early guards.\n";
