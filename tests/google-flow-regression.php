<?php
require __DIR__.'/fixtures/cse-transport.php';
require 'data/config.php';require 'scraper/google_cse.php';
class GoogleFixtureBackend {
    public array $saved=[];
    public function get_ip(){return 'raw_ip::::';}
    public function assign_proxy($handle,$proxy){}
    public function store($data,$kind,$proxy){$this->saved[]=[$data,$kind,$proxy];return 'fixture-next';}
    public function get($token,$kind){return [$this->saved[count($this->saved)-1][0],'raw_ip::::'];}
}
$n=0;function ok($v,$label){global $n;$n++;if(!$v)throw new RuntimeException($label);}
function provider(){ if(function_exists('apcu_clear_cache'))apcu_clear_cache();$g=new google_cse();$b=new GoogleFixtureBackend();(new ReflectionClass($g))->getProperty('backend')->setValue($g,$b);return [$g,$b]; }
function request_for($g,$page){$get=['s'=>'GNU Guix','npt'=>false];foreach($g->getfilters($page)as $k=>$v)$get[$k]=array_key_first($v['option']);return $get;}
$bootstrap='})({"cse_token":"test:token","cselibVersion":"test-lib"});';
$web=['unescapedUrl'=>'https://example.org/a','titleNoFormatting'=>'A result','contentNoFormatting'=>[],'richSnippet'=>['cseThumbnail'=>['src'=>'https://example.org/img','width'=>100,'height'=>0]]];
foreach(['web','images']as $page){
 [$g,$backend]=provider();$get=request_for($g,$page);$method=$page==='web'?'web':'image';
 $record=$page==='web'?$web:['unescapedUrl'=>'https://example.org/original.jpg','titleNoFormatting'=>'A picture','tbUrl'=>'https://example.org/small.jpg','tbWidth'=>240,'tbHeight'=>120];
 $data=['results'=>[$record,'bad row'],'cursor'=>['isExactTotalResults'=>true,'pages'=>[['start'=>0],['start'=>10]]]];
 $rows=[['body'=>$bootstrap],['body'=>'/*O_o*/google.search.cse.api('.json_encode($data).');']];$calls=0;$requested=[];
 $result=$g->$method($get);$key=$page==='web'?'web':'image';
 ok(count($result[$key])===1 && $calls===2,'Cold request is one bootstrap plus results '.$page);
 ok(str_starts_with($requested[0],'https://cse.google.com/cse.js?cx='),'Documented loader used without hosted HTML');
 ok($result['npt']==='fixture-next'&&json_decode($backend->saved[0][0],true)['start']===10,'Actual 10 offset retained despite requested page size 20');
 $get['npt']='fixture-next';$rows=[['body'=>'google.search.cse.api('.json_encode(['results'=>[$record],'cursor'=>['pages'=>[['start'=>0],['start'=>10]],'isExactTotalResults'=>true]]).');']];$calls=0;
 $result=$g->$method($get);ok(count($result[$key])===1&&$result['npt']===null&&$calls===1,'Final/continuation results preserved without rebootstrap');
}
[$g]=provider();$get=request_for($g,'web');$rows=[['body'=>'/* Different supported loader */'],['body'=>'<script>relativeUrl = "/cse.js?cx=fixture";</script>'],['body'=>$bootstrap],['body'=>'google.search.cse.api({"results":[]});']];$calls=0;$requested=[];$result=$g->web($get);ok($result['web']===[]&&$calls===4,'Format-only legacy discovery bounded to one pass');
foreach([['status'=>429],['status'=>403],['body'=>'<html>captcha</html>']]as $failure){
 [$g]=provider();$get=request_for($g,'web');$rows=[$failure];$calls=0;
 try{$g->web($get);throw new LogicException('Refusal accepted');}catch(upstream_search_failure $e){ok($calls===1,'Refusal/challenge never triggers alternate discovery');}
}
foreach(['{}','{"results":["bad"]}','{"results":"bad"}']as $json){
 [$g]=provider();$get=request_for($g,'web');$rows=[['body'=>$bootstrap],['body'=>'google.search.cse.api('.$json.');']];$calls=0;
 try{$g->web($get);throw new LogicException('Bad schema accepted');}catch(upstream_search_failure $e){ok($e->reason==='format'&&$calls===2,'Bad page is honest format failure, not zero results');}
}
$private_marker='private-google-fixture-marker';
$failures=[];
function safe_failure($call,$reason,$status,$label){
 global $private_marker,$failures;
 try{$call();$failures[]=$label.' accepted an upstream error';}
 catch(Exception $e){
  if(str_contains($e->getMessage(),$private_marker))$failures[]=$label.' exposed the private marker in the exception';
  // Web and image APIs serialize this exception message as the status field.
  if(str_contains(json_encode(['status'=>$e->getMessage()]),$private_marker))$failures[]=$label.' exposed the private marker in the API status';
  if(!($e instanceof upstream_search_failure))$failures[]=$label.' did not return a typed upstream failure';
  elseif($e->provider!=='google'||$e->reason!==$reason||$e->http_status!==$status)$failures[]=$label.' lost the safe failure classification';
 }
}
foreach(['web','images']as $page){
 $method=$page==='web'?'web':'image';
 foreach([
  ['message'=>'upstream detail '.$private_marker],
  ['message'=>'Google returned '.$private_marker],
  ['message'=>'outer detail','errors'=>[['message'=>$private_marker]]],
  ['errors'=>[['message'=>['nested'=>$private_marker]]]],
  $private_marker,
  ['code'=>500,'message'=>$private_marker]
 ]as $error){
  [$g]=provider();$get=request_for($g,$page);$rows=[['body'=>$bootstrap],['body'=>'google.search.cse.api('.json_encode(['error'=>$error]).');']];$calls=0;
  safe_failure(fn()=>$g->$method($get),'gateway',200,$page.' upstream detail');
  ok($calls===2,'Generic error does not refresh the session '.$page);
 }
 foreach([['code'=>429,'message'=>'token rate limited '.$private_marker],['code'=>403,'message'=>'forbidden '.$private_marker],['message'=>'captcha '.$private_marker]]as $error){
  [$g]=provider();$get=request_for($g,$page);$rows=[['body'=>$bootstrap],['body'=>'google.search.cse.api('.json_encode(['error'=>$error]).');']];$calls=0;
  $reason=isset($error['code'])?($error['code']===429?'rate_limited':'refused'):'challenge';
  safe_failure(fn()=>$g->$method($get),$reason,$error['code']??200,$page.' refusal');
  ok($calls===2,'Refusal never renews the session '.$page);
  if(function_exists('apcu_enabled')&&apcu_enabled()){
   safe_failure(fn()=>$g->$method($get),$reason,$error['code']??200,$page.' cooldown');
   ok($calls===2,'Cooldown blocks another upstream request '.$page);
  }
 }
}
[$g]=provider();$ref=new ReflectionClass($g);$decode=$ref->getMethod('decode_response');
foreach(['<html>'.$private_marker.'</html>','google.search.cse.api({"message":"'.$private_marker.'"}); trailing']as $body){
 safe_failure(fn()=>$decode->invoke($g,$body),'format',200,'Malformed response');
}
$request=$ref->getMethod('request_cse');
foreach([false,true]as $retry){
 [$g]=provider();$params=['cse_tok'=>'expired-fixture','cselibv'=>'old-fixture','q'=>'fixture'];$calls=0;
 $rows=[['body'=>'google.search.cse.api('.json_encode(['error'=>['code'=>403,'message'=>'cse token expired '.$private_marker]]).');']];
 if($retry)$rows=array_merge($rows,[['body'=>$bootstrap],['body'=>'google.search.cse.api({"results":[]});']]);
 if($retry){
  $result=$request->invokeArgs($g,['raw_ip::::',&$params,true]);
  ok($result===['results'=>[]]&&$calls===3&&$params['cse_tok']==='test:token','Expired session still renews exactly once');
 }else{
  safe_failure(function()use($request,$g,&$params){return $request->invokeArgs($g,['raw_ip::::',&$params,false]);},'refused',403,'Nonrenewable token error');
  ok($calls===1,'Nonrenewable token error makes one request');
 }
}
foreach(['web','images']as $page){
 foreach([['code'=>403,'message'=>'cse token expired '.$private_marker],['message'=>'upstream detail '.$private_marker],['message'=>'captcha '.$private_marker]]as $error){
  [$g,$backend]=provider();$get=request_for($g,$page);$get['npt']='fixture-next';$method=$page==='web'?'web':'image';$calls=0;
  $backend->saved[]=[json_encode(['cse_tok'=>'expired-fixture','cselibv'=>'old-fixture','q'=>'fixture','start'=>0]),$page,'raw_ip::::'];
  $rows=[['body'=>'google.search.cse.api({"error":{"code":403,"message":"cse token expired"}});'],['body'=>$bootstrap],['body'=>'google.search.cse.api('.json_encode(['error'=>$error]).');']];
  $reason=isset($error['code'])?'refused':(str_starts_with($error['message'],'captcha')?'challenge':'gateway');
  safe_failure(fn()=>$g->$method($get),$reason,$error['code']??200,'Failed renewal '.$page);
  ok($calls===3,'Failed renewal never attempts another refresh '.$page);
 }
}
ok($failures===[],implode("\n",$failures));
echo "PASS: $n actual web/image flow assertions with explicit offline transport doubles.\n";
