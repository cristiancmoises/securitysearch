<?php
require 'data/config.php';require 'lib/news_sources.php';require 'lib/news_rss.php';require 'lib/news_selection.php';require 'lib/service_pool.php';
$n=0;function nc($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
function badnc($fn,$reason){try{$fn();throw new LogicException('accepted');}catch(news_failure $e){nc($e->reason===$reason,'Expected '.$reason);}}
nc(str_contains(news_sources::disclosure('bing'),'Only Bing News RSS'),'explicit-source disclosure');nc(!str_contains(news_sources::disclosure('bing'),'Google'),'no misleading recipient');
nc(config::DEFAULT_SCRAPER_NEWS==='newswire','RSS default');nc(news_sources::order()===['google','bing'],'bounded independent order');
foreach(['google','bing']as$id)foreach(['en-US','pt-BR']as$market)foreach(['','GNU Guix & café']as$q){
 $url=news_sources::url($id,$q,$market);$p=parse_url($url);parse_str($p['query'],$params);
 nc(($p['scheme']??'')==='https' && $p['host']===parse_url(news_sources::ORIGINS[$id],PHP_URL_HOST),'fixed HTTPS origin');
 nc(($q===''||($params['q']??null)===$q),'query encoding');
}
foreach([[],str_repeat('a',501),"a\nb","\xff"]as$q)badnc(fn()=>news_sources::query($q),'invalid_input');
badnc(fn()=>news_sources::url('http://127.0.0.1','x','en-US'),'invalid_input');
badnc(fn()=>news_sources::url('google','x','untrusted'),'invalid_input');
foreach(['https://user:pass@example.org/x','https://127.0.0.1/x','http://example.org','//example.org','javascript:alert(1)','https://[::1]/','https://example.org:443/','https://local/x','https://example.org/xx yy','https://x.local/test']as$url)nc(news_rss::link($url)===null,'reject unsafe link');
nc(news_rss::link('https://news.google.com/rss/articles/abc?oc=5')!==null,'aggregator link display allowed');
foreach(['<!DOCTYPE rss><rss/>','<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss/>',"<rss>\0</rss>",'<?xml version="1.0" encoding="UTF-16"?><rss/>']as$xml)badnc(fn()=>news_rss::precheck($xml),'unsafe_xml');
badnc(fn()=>news_rss::precheck(str_repeat('x',1048577)),'body_limit');
foreach([403,418,429,503,302]as$code){$e=news_failure::http($code);nc($e->http_status===$code,'HTTP diagnostic retained');nc(!str_contains($e->getMessage(),'private'),'No private body');}
nc(news_failure::from(new RuntimeException('wrapped',0,new Exception('secret',418)))->reason==='http_refused','wrapped HTTP code');
nc(news_failure::from(new Exception('secret',28))->reason==='deadline','cURL timeout classification');
nc(news_failure::http(418)->cooldown()===600,'418 respected');
$trace=[];$report=news_selection::choose(function($source,$kind)use(&$trace){$trace[]=[$source,$kind];if($source==='google')throw new news_failure('challenge',200);return 3;});
nc($trace===[['google','feed'],['bing','feed'],['bing','search']],'no search after challenged feed');
nc($report['source']==='bing'&&$report['attempts'][0]['search_count']===null,'honest not-tested counts');
nc($report['attempts'][0]['stages']['feed']['http_status']===200,'challenge HTTP200 separate from result');
foreach([0,-1,'1',true,41,null]as$value){$r=news_selection::choose(fn()=>$value);nc($r['status']==='unavailable','nonempty actual int counts required');}
$r=news_selection::choose(fn()=>throw new RuntimeException('TOKEN_AND_PRIVATE_QUERY'));nc(!str_contains(json_encode($r),'TOKEN_AND_PRIVATE_QUERY'),'safe errors');
$cache=[];$writes=[];$calls=0;
try{service_pool::run([service_pool::primary()],function()use(&$calls){$calls++;throw new news_failure('http_refused',418);},fn($k)=>$cache[$k]??false,function($k,$v,$ttl)use(&$cache,&$writes){$cache[$k]=$v;$writes[]=$ttl;});}catch(RuntimeException $e){}
try{service_pool::run([service_pool::primary()],function()use(&$calls){$calls++;return [];},fn($k)=>$cache[$k]??false);}catch(RuntimeException $e){}
nc($calls===1&&$writes===[600],'Redlib refusal cooldown makes no second request');
echo "PASS: $n news source, policy, report and refusal assertions; no external requests.\n";
