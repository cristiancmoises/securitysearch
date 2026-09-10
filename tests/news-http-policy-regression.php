<?php
require 'lib/news_http.php';
$n=0;function nh($yes,$label){global $n;$n++;if(!$yes)throw new RuntimeException($label);}
function response($status=200,$body='<rss><channel><title>Test</title></channel></rss>',$type='application/rss+xml'){return ['http'=>['code'=>$status],'headers'=>['content-type'=>$type],'body'=>$body];}
nh(class_exists('provider_http',false),'standalone CLI probe deadline dependency loaded');
foreach(['application/rss+xml','application/xml; charset=UTF-8','text/xml','application/rdf+xml']as$type)nh(str_starts_with(news_http::response(response(type:$type)),'<rss'),'XML type');
foreach([301,302,303,307,308,401,403,407,418,429,451,500,502,503]as$code){try{news_http::response(response($code));throw new LogicException('accepted status');}catch(news_failure$e){nh($e->http_status===$code,'HTTP status preserved');nh(!str_contains($e->getMessage(),'Test'),'no body in message');}}
foreach(['text/html','text/plain','application/json','']as$type){try{news_http::response(response(type:$type));throw new LogicException('accepted type');}catch(news_failure$e){nh($e->reason==='content_type','HTML/JSON not results');}}
try{news_http::response(response(body:'<html><script id="anubis_challenge"></script></html>',type:'text/html'));throw new LogicException('challenge accepted');}catch(news_failure$e){nh($e->reason==='challenge','HTTP200 challenge denied');}
try{news_http::response(response(body:str_repeat('x',1048577)));throw new LogicException('oversized accepted');}catch(news_failure$e){nh($e->reason==='body_limit','bounded data');}
nh(news_http::retry_after('900')===900,'Retry-After seconds');nh(news_http::retry_after('900000')===3600,'bounded delay');nh(news_http::retry_after('x')===0,'invalid retry');
$r=response(429);$r['headers']['retry-after']='1100';try{news_http::response($r);}catch(news_failure$e){nh($e->cooldown()===1100,'honor delay');}
// Production call sites must retain pinning/no-redirect policy, not just pure fixtures.
$s=file_get_contents('lib/news_http.php');nh(str_contains($s,"'no_redirect'=>true"),'RSS redirects disabled');nh(str_contains($s,'min($deadline,provider_http::deadline())'),'shared deadline');
$s=file_get_contents('lib/curlproxy.php');nh(str_contains($s,'$request_budget->no_redirect'),'transport enforces redirect flag');nh(str_contains($s,'throw new Exception($curl_error, $curl_errno)'),'native errno retained');
echo "PASS: $n response-policy assertions; no live transport used.\n";
