<?php
// Explicit transport doubles. Native cURL functions must be disabled for this child.
$targets=['curl_init','curl_reset','curl_setopt','curl_exec','curl_errno','curl_getinfo','curl_close'];
foreach($targets as $f)if(function_exists($f)){fwrite(STDERR,"Use the isolated command in scripts/test.sh.\n");exit(2);}
// Missing extension constants are fixture option identifiers, not a native-extension emulation.
$names=['CURLOPT_URL','CURLOPT_HTTPHEADER','CURLOPT_HTTP_VERSION','CURL_HTTP_VERSION_2_0','CURLOPT_ENCODING','CURLOPT_RETURNTRANSFER','CURLOPT_SSL_VERIFYHOST','CURLOPT_SSL_VERIFYPEER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_NOPROXY','CURLOPT_PROXYUSERPWD','CURLOPT_PROXY','CURLOPT_PROTOCOLS','CURLPROTO_HTTPS','CURLOPT_FOLLOWLOCATION','CURLOPT_WRITEFUNCTION','CURLOPT_HEADERFUNCTION','CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS','CURLINFO_RESPONSE_CODE'];
foreach($names as $i=>$name)if(!defined($name))define($name,9000+$i);
if(!function_exists('curl_init')){
 function curl_init(){return(object)['options'=>[],'row'=>[]];}
 function curl_reset($h){$h->options=[];}
 function curl_setopt($h,$key,$value){$h->options[$key]=$value;return true;}
 function curl_errno($h){return $h->row['errno']??0;}
 function curl_getinfo($h,$key){return $h->row['status']??200;}
 function curl_close($h){}
 function curl_exec($h){
  $GLOBALS['calls']++;$h->row=array_shift($GLOBALS['rows'])??['status'=>200,'body'=>'<html>normal</html>'];
  $GLOBALS['last_options']=$h->options;
  $header=$h->options[CURLOPT_HEADERFUNCTION];$header($h,'HTTP/2 '.($h->row['status']??200)."\r\n");
  foreach(($h->row['headers']??[])as $k=>$v)$header($h,$k.': '.$v."\r\n");
  $write=$h->options[CURLOPT_WRITEFUNCTION];$data=$h->row['body']??'<html>normal</html>';$write($h,$data);return true;
 }
}
require 'data/config.php';require 'scraper/brave.php';require 'scraper/google_cse.php';
$n=0;function check($v,$s){global $n;$n++;if(!$v)throw new RuntimeException($s);}
foreach(['brave','google_cse']as $name){
 $provider=new $name();$ref=new ReflectionClass($provider);$get=$ref->getMethod('get');$label=$name==='brave'?'brave':'google';
 $call=fn()=>$name==='brave'?$get->invoke($provider,'raw_ip::::','https://search.brave.com/search',['q'=>'fixture'],'no','all'):$get->invoke($provider,'raw_ip::::','https://cse.google.com/cse/element/v1',['q'=>'fixture']);
 $provider->set_request_deadline(hrtime(true)+10000000000);
 $calls=0;$rows=[['status'=>502],['status'=>200,'body'=>'good']];check($call()==='good'&&$calls===2,'One gateway retry '.$name);
 check($last_options[CURLOPT_FOLLOWLOCATION]===false&&$last_options[CURLOPT_SSL_VERIFYPEER]===true&&$last_options[CURLOPT_SSL_VERIFYHOST]===2,'TLS and no redirect');
 check($last_options[CURLOPT_TIMEOUT_MS]<=10000,'Absolute deadline');
 foreach([429,403,418,302]as $status){$calls=0;$rows=[['status'=>$status,'headers'=>['Retry-After'=>'300']]];
  try{$call();throw new LogicException('Refusal accepted');}catch(upstream_search_failure $e){check($e->http_status===$status&&$e->provider===$label,'Safe typed HTTP status');}
  check($calls===1,'No refusal retry');
 }
 $calls=0;$rows=[['status'=>503,'headers'=>['Retry-After'=>'300']]];try{$call();throw new LogicException('503 accepted');}catch(upstream_search_failure $e){check($e->retry_after===300&&$calls===1,'503 Retry-After no retry');}
 $calls=0;$rows=[['status'=>200,'errno'=>28]];try{$call();throw new LogicException('Transport accepted');}catch(upstream_search_failure $e){check($e->curl_errno===28&&$calls===1,'Transport failures kept');}
 $calls=0;$rows=[];$provider->set_request_deadline(hrtime(true)-1);try{$call();throw new LogicException('Expired accepted');}catch(Exception $e){check($calls===0,'No expired network execution');}
}
$b=new brave();$r=new ReflectionClass($b);$m=$r->getMethod('get_search_page');$proxy='raw_ip::::';$calls=0;$rows=[['status'=>200,'body'=>'<title>Human verification</title>']];
try{$m->invokeArgs($b,[&$proxy,'https://search.brave.com/images',[],'no','all']);throw new LogicException('Challenge accepted');}catch(upstream_search_failure $e){check($e->reason==='challenge'&&$calls===1,'No challenge rotation');}
$before=$calls;
foreach(['http://search.brave.com/search','https://evil.example/search','https://search.brave.com:9000/search','https://search.brave.com@127.0.0.1/search','https://search.brave.com/unsafe']as $bad){try{$r->getMethod('get')->invoke($b,$proxy,$bad,[],'no','all');throw new LogicException('Unsafe destination');}catch(InvalidArgumentException $e){check($calls===$before,'Unsafe URL before transport');}}
$g=new google_cse();$r=new ReflectionClass($g);$decode=$r->getMethod('decode_response');
foreach(['google.search.cse.api({"results":[]});'," \n google.search.cse.api_2 ( {\"results\":[]} ) \n"]as $body)check($decode->invoke($g,$body)===['results'=>[]],'Bounded JSONP wrapper');
foreach(['evil({"results":[]});','google.search.cse.api({"results":[]}); alert(1)','<html>normal</html>']as $body){try{$decode->invoke($g,$body);throw new LogicException('Unsafe wrapper');}catch(upstream_search_failure $e){check($e->reason==='format','Reject executable suffix/unrelated body');}}
check($r->getConstant('TOKEN_WAIT_ATTEMPTS')*$r->getConstant('TOKEN_WAIT_USEC')<=2000000,'Bootstrap contention cap');
echo "PASS: $n actual adapter paths with explicit network-free cURL doubles; not live/native transport results.\n";
// Actual parser handles both retained and generated Svelte bootstrap identifiers.
foreach(['kit','__sveltekit_fixture']as $symbol){
 $b=new brave();$r=new ReflectionClass($b);$html=$r->getProperty('fuckhtml')->getValue($b);
 $html->load('<script>'.$symbol.'.start(app,node,{data : [{type:"data",data:{query:"fixture",web:{results:[]}}}]});</script>');
 $decoded=$r->getMethod('get_js')->invoke($b);check(($decoded[0]['data']['web']['results']??null)===[],'Actual Svelte data parser, no JavaScript execution');
}
echo "PASS: two bounded Svelte bootstrap fixtures parsed with the actual parser.\n";
// A structured expired-token error may renew once; rate limits may not.
$g=new google_cse();$r=new ReflectionClass($g);$m=$r->getMethod('request_cse');$params=['cse_tok'=>'expired','cselibv'=>'old','q'=>'fixture'];
$calls=0;$rows=[
 ['body'=>'google.search.cse.api({"error":{"code":403,"message":"cse token expired"}});'],
 ['body'=>'})({"cse_token":"fresh-fixture","cselibVersion":"new-fixture"});'],
 ['body'=>'google.search.cse.api({"results":[]});']
];
$result=$m->invokeArgs($g,['raw_ip::::',&$params,true]);check($result===['results'=>[]]&&$calls===3&&$params['cse_tok']==='fresh-fixture','Existing one-time token renewal retained');
$g=new google_cse();$params=['q'=>'fixture'];$calls=0;$rows=[['body'=>'google.search.cse.api({"error":{"code":429,"message":"token rate limited"}});']];
try{$m->invokeArgs($g,['raw_ip::::',&$params,true]);throw new LogicException('429 accepted');}catch(upstream_search_failure $e){check($e->reason==='rate_limited'&&$calls===1,'No token refresh for structured rate limit');}
echo "PASS: token renewal versus rate limit uses actual request/bootstrap/parser with offline transport.\n";
