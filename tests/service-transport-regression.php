<?php
// Replace only native cURL I/O; the real proxy, service wrapper and guard run.
$functions=['curl_setopt','curl_exec','curl_errno','curl_error','curl_getinfo'];
foreach($functions as $name) if(function_exists($name)) {
    fwrite(STDERR,"Use isolated native transport doubles for this test.\n");exit(2);
}
if(!function_exists('curl_setopt')){function curl_setopt($handle,$key,$value){$GLOBALS['options'][$key]=$value;return true;}}
if(!function_exists('curl_exec')){function curl_exec($handle){$GLOBALS['calls']++;return false;}}
if(!function_exists('curl_errno')){function curl_errno($handle){return $GLOBALS['errno'];}}
if(!function_exists('curl_error')){function curl_error($handle){return 'private-transport-marker';}}
if(!function_exists('curl_getinfo')){function curl_getinfo($handle,$key){return 0;}}
require 'data/config.php';require 'lib/service_search.php';require 'lib/search_guard.php';
class fixed_transport_fixture extends service_search {
    protected const ORIGIN='https://1.1.1.1';
    public function web($get):array{return ['body'=>$this->fetch_path('/fixture',['q'=>'private-query-marker'])];}
}
class invalid_target_fixture extends fixed_transport_fixture {protected const ORIGIN='http://127.0.0.1';}
$n=0;$calls=0;$store=[];$options=[];
function transport_check($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
$read=static fn($key)=>$GLOBALS['store'][$key][0]??false;
$write=static function($key,$value,$ttl){$GLOBALS['store'][$key]=[$value,$ttl];};
foreach([5,6,7,28,35,52,55,56,60] as $errno) {
    $store=[];$provider=new fixed_transport_fixture();$before=$calls;$caught=null;
    try {search_guard::run($provider,'web',[], $read,$write);}catch(Exception $error){$caught=$error;}
    transport_check($caught instanceof provider_http_failure,'Native transport failure reaches the existing brief cooldown');
    transport_check($calls===$before+1 && count($store)===1 && reset($store)===[true,8],'Only fixed health boolean and eight-second TTL are retained');
    transport_check(!str_contains($caught->getMessage(),'private-'),'Public error does not expose transport or query details');
    try {search_guard::run($provider,'web',[], $read,$write);}catch(RuntimeException $error){}
    transport_check($calls===$before+1,'Repeated fresh search avoids a known failed transport');
    try {search_guard::run($provider,'web',['npt'=>'continuation'], $read,$write);}catch(Exception $error){}
    transport_check($calls===$before+2,'Continuation is never blocked by first-page health');
}
foreach([18,23,42] as $errno) {
    $store=[];try {search_guard::run(new fixed_transport_fixture(),'web',[], $read,$write);}catch(Exception $error){}
    transport_check($store===[],'Payload, callback and nontransport errors do not blacklist a service');
}
$store=[];$errno=6;$before=$calls;
try {search_guard::run(new invalid_target_fixture(),'web',[], $read,$write);}catch(Exception $error){}
transport_check($calls===$before && $store===[],'SSRF rejection makes no request and is not treated as provider downtime');
echo "PASS: $n service transport classification, bounded cooldown and privacy checks.\n";
