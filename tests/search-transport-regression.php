<?php
// Explicit transport doubles. Native cURL functions must be disabled for this child.
$curl_inits=0;$curl_resets=0;
$targets=['curl_init','curl_reset','curl_setopt','curl_exec','curl_errno','curl_getinfo','curl_close'];
foreach($targets as $f)if(function_exists($f)){fwrite(STDERR,"Use the isolated command in scripts/test.sh.\n");exit(2);}
// Missing extension constants are fixture option identifiers, not a native-extension emulation.
$names=['CURLOPT_URL','CURLOPT_HTTPHEADER','CURLOPT_HTTP_VERSION','CURL_HTTP_VERSION_2_0','CURLOPT_ENCODING','CURLOPT_RETURNTRANSFER','CURLOPT_SSL_VERIFYHOST','CURLOPT_SSL_VERIFYPEER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_NOPROXY','CURLOPT_PROXYUSERPWD','CURLOPT_PROXY','CURLOPT_PROTOCOLS','CURLPROTO_HTTPS','CURLOPT_FOLLOWLOCATION','CURLOPT_WRITEFUNCTION','CURLOPT_HEADERFUNCTION','CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS','CURLINFO_RESPONSE_CODE','CURLOPT_NOSIGNAL','CURLOPT_TCP_KEEPALIVE'];
foreach($names as $i=>$name)if(!defined($name))define($name,9000+$i);
if(!function_exists('curl_init')){
 function curl_init(){global $curl_inits;$curl_inits++;return(object)['options'=>[],'row'=>[]];}
 function curl_reset($h){global $curl_resets;$curl_resets++;$h->options=[];}
 function curl_setopt($h,$key,$value){$h->options[$key]=$value;return true;}
 function curl_errno($h){return $h->row['errno']??0;}
 function curl_getinfo($h,$key){return $h->row['status']??200;}
 function curl_close($h){}
 function curl_exec($h){
  $GLOBALS['calls']++;$h->row=array_shift($GLOBALS['rows'])??['status'=>200,'body'=>'<html>normal</html>'];
  $GLOBALS['last_options']=$h->options;
  if(isset($h->options[CURLOPT_HEADERFUNCTION])){
   $header=$h->options[CURLOPT_HEADERFUNCTION];$header($h,'HTTP/2 '.($h->row['status']??200)."\r\n");
   foreach(($h->row['headers']??[])as $k=>$v)$header($h,$k.': '.$v."\r\n");
  }
  $data=$h->row['body']??'<html>normal</html>';
  if(isset($h->options[CURLOPT_WRITEFUNCTION])){$write=$h->options[CURLOPT_WRITEFUNCTION];$write($h,$data);return true;}
  return $data;
 }
}
require 'data/config.php';require 'scraper/brave.php';require 'scraper/google_cse.php';
$n=0;function check($v,$s){global $n;$n++;if(!$v)throw new RuntimeException($s);}
foreach(['brave','google_cse']as $name){
 $provider=new $name();$ref=new ReflectionClass($provider);$get=$ref->getMethod('get');$label=$name==='brave'?'brave':'google';
 $call=fn()=>$name==='brave'?$get->invoke($provider,'raw_ip::::','https://search.brave.com/search',['q'=>'fixture'],'no','all'):$get->invoke($provider,'raw_ip::::','https://cse.google.com/cse/element/v1',['q'=>'fixture']);
 $provider->set_request_deadline(hrtime(true)+10000000000);
 $init_before=$curl_inits;$reset_before=$curl_resets;$calls=0;$rows=[['status'=>502],['status'=>200,'body'=>'good']];check($call()==='good'&&$calls===2,'One gateway retry '.$name);
 if($name==='brave')check($curl_inits-$init_before===1&&$curl_resets-$reset_before===1,'Brave transient retry reuses one easy handle');
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
// Exercise complete Brave web/image responses through the real transport and parser.
class BraveResponseBackend extends backend {
 public function __construct(){}
 public function get_ip($proxy_index_raw=null){return 'raw_ip::::';}
 public function assign_proxy(&$handle,string $proxy){}
}
function brave_response($symbol,$page,$response,$format='any'){
 $data=[['type'=>'data','data'=>[]],['type'=>'data','data'=>['body'=>['response'=>$response]]]];
 return brave_serialized_response($symbol,$page,json_encode($data),$format);
}
function brave_serialized_response($symbol,$page,$data,$format='any'){
 $provider=new brave();
 (new ReflectionClass($provider))->getProperty('backend')->setValue($provider,new BraveResponseBackend());
 $GLOBALS['calls']=0;
 $GLOBALS['rows']=[['body'=>'<html><title>Brave fixture</title><script>'.$symbol.'.start(app,node,{data:'.$data.'});</script></html>']];
 $get=['s'=>'fixture','npt'=>false,'nsfw'=>'no','country'=>'any','spellcheck'=>'yes','older'=>false,'newer'=>false,'format'=>$format];
 return $page==='web' ? $provider->web($get) : $provider->image($get);
}
$brave_failures=[];
$web_record=['title'=>'Fixture result','description'=>'A genuine result','url'=>'https://example.org/result'];
$image_record=['title'=>'Fixture picture','url'=>'https://example.org/picture','properties'=>['url'=>'https://example.org/original.jpg','width'=>640,'height'=>480],'thumbnail'=>['src'=>'https://example.org/preview.jpg','width'=>160,'height'=>120]];
foreach(['kit','__sveltekit_fixture']as $symbol){
 $result=brave_response($symbol,'web',['web'=>['results'=>[]]]);
 check($result['status']==='ok'&&$result['web']===[],'Genuine empty Brave web list '.$symbol);
 $result=brave_response($symbol,'web',['web'=>['results'=>[$web_record]]]);
 check(count($result['web'])===1&&$result['web'][0]['url']==='https://example.org/result','Genuine Brave web record '.$symbol);
 $result=brave_response($symbol,'images',['results'=>[]]);
 check($result['status']==='ok'&&$result['image']===[],'Genuine empty Brave image list '.$symbol);
 $result=brave_response($symbol,'images',['results'=>['invalid',$image_record]]);
 check(count($result['image'])===1&&$result['image'][0]['source'][0]['url']==='https://example.org/original.jpg','Valid Brave image survives invalid neighbor '.$symbol);
 $result=brave_response($symbol,'images',['results'=>[$image_record]],'png');
 check($result['status']==='ok'&&$result['image']===[],'Legitimate Brave format filter can empty parsed list '.$symbol);
 foreach([
  ['web',new stdClass(),'missing web results'],
  ['web','invalid','non-object web response'],
  ['web',['web'=>['results'=>'invalid']],'non-array web results'],
  ['images',new stdClass(),'missing image results'],
  ['images','invalid','non-object image response'],
  ['images',['results'=>'invalid'],'non-array image results'],
  ['images',['results'=>['invalid',null,[]]],'nonempty image list without usable records']
 ]as [$page,$response,$label]){
  try{brave_response($symbol,$page,$response);$brave_failures[]=$symbol.' '.$label.' accepted as success';}
  catch(upstream_search_failure $e){check($e->provider==='brave'&&$e->reason==='format'&&$e->http_status===200,'Typed Brave format failure '.$label);}
  catch(Throwable $e){$brave_failures[]=$symbol.' '.$label.' returned '.get_class($e).' instead of a typed format failure';}
 }
}
check($brave_failures===[],implode("\n",$brave_failures));
echo "PASS: complete Brave web/image Svelte responses reject malformed or unusable data while preserving genuine emptiness and format filters.\n";
// The serializer binds literal arguments in closed IIFEs; it never runs upstream code.
$iife_prefix='[(function(a,b){return {type:"data",data:{query:a,unused:b}}}("fixture",null)),';
$iife_web=$iife_prefix.'(function(a,b){return {type:"data",data:{body:{response:{web:{results:[{title:a,description:"A fixture result",url:b,age:void 0,thumbnail:{logo:false,original:"https://example.org/thumb.jpg"}}]}}}}}}("Fixture \\"quote\\" \\u00e9","https://example.org/result"))]';
$iife_images=$iife_prefix.'(function(a,b){return {type:"data",data:{body:{response:{results:[{title:a,url:b,properties:{url:"https://example.org/original.jpg",width:640,height:480},thumbnail:{src:"https://example.org/preview.jpg",width:160,height:120}}]}}}}}("Fixture \\u00e9 image","https://example.org/picture"))]';
$iife_failures=[];
foreach(['kit','__sveltekit_fixture']as $symbol){
 try{
  $result=brave_serialized_response($symbol,'web',$iife_web);
  check($result['web'][0]['title']==='Fixture "quote" é'&&$result['web'][0]['url']==='https://example.org/result','Closed IIFE web bindings and escaped string '.$symbol);
  check($result['web'][0]['thumb']['ratio']==='16:9'&&$result['web'][0]['date']===null,'Legacy false and void 0 reach their real Brave consumers '.$symbol);
 }catch(upstream_search_failure $e){$iife_failures[]=$symbol.' valid closed web IIFE rejected: '.$e->reason;}
 try{
  $result=brave_serialized_response($symbol,'images',$iife_images);
  check($result['image'][0]['title']==='Fixture é image'&&$result['image'][0]['source'][0]['width']===640,'Closed IIFE image bindings and dimensions '.$symbol);
 }catch(upstream_search_failure $e){$iife_failures[]=$symbol.' valid closed image IIFE rejected: '.$e->reason;}
}
function brave_parse_data($data){
 $provider=new brave();$ref=new ReflectionClass($provider);
 $ref->getProperty('fuckhtml')->getValue($provider)->load('<script>kit.start(app,node,{data:'.$data.'});</script>');
 return $ref->getMethod('get_js')->invoke($provider);
}
$escape_text="line\nquote\"slash\\unicode🚀";
$decoded=brave_parse_data('[{text:'.json_encode($escape_text).',no:false,missing:void 0,num:-1.5e2},[{},[]]]');
check($decoded[0]['text']===$escape_text&&$decoded[0]['no']==='false'&&$decoded[0]['missing']==='void 0'&&$decoded[0]['num']==='-1.5e2','Escapes and legacy primitives preserve literal data');
check($decoded[1]===[[],[]],'Nested empty collections preserve their shape');
check(count(brave_parse_data(str_repeat('[',64).'0'.str_repeat(']',64)))===1,'Depth bound permits valid data below its limit');
foreach([
 '[alert(1)]','[new Date()]','[()=>({})]','[{x:outside}]','[{x:1+2}]',
 '[(function(a){return {x:a};alert(1)}("x"))]',
 '[(function(a){return {x:a+1}}("x"))]',
 '[(function(a){return {x:outside}}("x"))]',
 '[(function(a,a){return {x:a}}("x","y"))]',
 '[(function(a){return {x:a}}(window.location))]',
 '[(function(a){return {x:a}}("x","y"))]',
 '[(function(a){return {x:a}}())]',
 '[(function(a){return {x:a}}(function(){return "x"}))]',
 '[(function(a){return ['.implode(',',array_fill(0,15,'a')).']}("'.str_repeat('x',300000).'"))]',
 '[{x:"\\uD800"}]',str_repeat('[',70).'0'.str_repeat(']',70),
 '['.str_repeat('0,',100001).'0]', '["'.str_repeat('x',4194304).'"]'
]as $index=>$data){
 try{brave_parse_data($data);$iife_failures[]='Unsupported serializer expression or budget case '.$index.' accepted';}
 catch(upstream_search_failure $e){check($e->provider==='brave'&&$e->reason==='format','Unsupported serializer fails safely');}
}
check($iife_failures===[],implode("\n",$iife_failures));
echo "PASS: closed IIFE literal bindings, escapes, legacy values and bounded rejection of executable expressions.\n";
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
// Public defaults and category choices must match the implemented adapters.
$provider_failures=[];
foreach(['pexels','unsplash','pixabay']as $name){
 $constant='config::PROXY_'.strtoupper($name);
 if(!defined($constant)||constant($constant)!==false)$provider_failures[]='Missing explicit direct default '.$name;
 else check((new backend($name))->get_ip()==='raw_ip::::','Explicit public proxy default '.$name);
}
require_once 'lib/frontend.php';$frontend=new frontend();
foreach([['videos','archiveorg'],['news','yep']]as [$page,$unsupported]){
 $_GET=['scraper'=>'brave'];$_COOKIE=[];[, $filters]=$frontend->getscraperfilters($page);
 if(isset($filters['scraper']['option'][$unsupported]))$provider_failures[]='Unimplemented category choice '.$page.' '.$unsupported;
 check(isset($filters['scraper']['option']['brave']),'Other category choices retained '.$page);
}
$source=file_get_contents('lib/frontend.php');preg_match('/case "web":(.*?)case "images":/s',$source,$web_registry);
check(str_contains($web_registry[1]??'','"yep" => "Yep"'),'Implemented Yep web choice retained');
// Real Cara parsing/transport, with only backend storage and proxy selection doubled.
require_once 'scraper/cara.php';
class CaraResponseBackend extends BraveResponseBackend {
 public function store(string $payload,string $page,string $proxy){$GLOBALS['cara_stored_skip']=json_decode($payload,true)['skip'];return 'cara-fixture-page2';}
}
#[AllowDynamicProperties]
class CaraResponseFixture extends cara {
 public function __construct(){$this->backend=new CaraResponseBackend();}
}
function cara_response($body){
 $GLOBALS['calls']=0;$GLOBALS['rows']=[['status'=>200,'body'=>$body]];
 return (new CaraResponseFixture())->image(['s'=>'fixture','npt'=>false,'sort'=>'Top','type'=>'any','fields'=>'any','category'=>'any','software'=>'any']);
}
$cara_record=['images'=>[['isCoverImg'=>false,'src'=>'fixture full.jpg'],['isCoverImg'=>true,'src'=>'fixture-cover.jpg']],'content'=>"Fixture\nimage",'id'=>'fixture-post'];
$result=cara_response('[]');check($result['status']==='ok'&&$result['image']===[]&&$result['npt']===null,'Genuine empty Cara list');
$result=cara_response(json_encode([['images'=>[]],$cara_record]));
check(count($result['image'])===1&&$result['image'][0]['title']==='Fixture image'&&$result['image'][0]['source'][0]['url']==='https://images.cara.app/fixture%20full.jpg','Cara skips genuine imageless rows and keeps valid images');
check($result['image'][0]['source'][1]['width']===500&&$result['image'][0]['url']==='https://cara.app/post/fixture-post','Cara cover and post metadata retained');
$result=cara_response(json_encode(array_fill(0,24,['images'=>[]])));
check($result['image']===[]&&$result['npt']==='cara-fixture-page2'&&$cara_stored_skip===24,'Cara imageless rows retain pagination accounting');
foreach([
 'invalid JSON','null','{}','{"error":"fixture"}','[null]','["invalid"]','[[]]','[{}]',
 '[{"images":null}]','[{"images":{}}]','[{"images":"invalid"}]',
 json_encode([array_replace($cara_record,['images'=>[null]])]),
 json_encode([array_replace($cara_record,['images'=>[['src'=>'fixture.jpg']]])]),
 json_encode([array_replace($cara_record,['images'=>[['isCoverImg'=>false,'src'=>[]]]])]),
 json_encode([array_replace($cara_record,['images'=>[['isCoverImg'=>false,'src'=>'']]])]),
 json_encode([array_replace($cara_record,['content'=>[]])]),
 json_encode([array_replace($cara_record,['id'=>[]])]),
 json_encode([$cara_record,['images'=>null]])
]as $index=>$body){
 try{cara_response($body);$provider_failures[]='Malformed Cara case '.$index.' accepted';}
 catch(upstream_search_failure $e){check($e->provider==='cara'&&$e->reason==='format'&&$e->http_status===200,'Typed Cara schema failure '.$index);}
 catch(Throwable $e){$provider_failures[]='Malformed Cara case '.$index.' returned '.get_class($e).' rather than a typed format failure';}
}
check($provider_failures===[],implode("\n",$provider_failures));
echo "PASS: explicit image-provider proxy defaults, implemented category choices, and Cara typed schema failures with valid empty/filter/pagination behavior.\n";
