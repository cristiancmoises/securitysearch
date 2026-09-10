<?php
require 'data/config.php';require 'lib/search_health.php';require 'lib/frontend.php';require 'lib/image_results.php';
$n=0;function ok($v,$s){global $n;$n++;if(!$v)throw new RuntimeException($s);}
$store=[];$write=static function($k,$v,$ttl)use(&$store){$store[$k]=$v;};$read=static fn($k)=>$store[$k]??false;
// Use by-reference reads so test fixtures reflect writes, without any shared cache.
$read=static function($k)use(&$store){return $store[$k]??false;};$proxy='socks5h:proxy.example:1080:name:secret';
foreach(['google','brave'] as $provider){
 $key=search_health::key($provider,$proxy);
 ok(!str_contains($key,'secret')&&!str_contains($key,'name'),'No egress secrets in key');
 ok($key!==search_health::key($provider,'raw_ip::::'),'Separate egress');
 foreach(['rate_limited'=>60,'refused'=>120,'challenge'=>120,'transport'=>5,'gateway'=>5] as $kind=>$ttl){
  $store=[];$error=new upstream_search_failure($provider,$kind,429,0,0);search_health::remember($provider,$proxy,$error,$write,1000);
  ok($store[$key]['until']===1000+$ttl,'Bounded TTL '.$kind);
  try{search_health::check($provider,$proxy,$read,1001);throw new LogicException('Cooldown ignored');}
  catch(upstream_search_failure $e){ok($e->retry_after===$ttl-1&&$e->reason===$kind,'Countdown');}
  search_health::check($provider,'raw_ip::::',$read,1001);
  search_health::check($provider,$proxy,$read,1000+$ttl);
 }
 foreach(['format','body_limit','busy','deadline'] as $kind){$store=[];search_health::remember($provider,$proxy,new upstream_search_failure($provider,$kind),$write,1000);ok($store===[],'No blanket parse/query cooldown');}
 $store=[];search_health::remember($provider,$proxy,search_health::http_failure($provider,503,'900'),$write,1000);ok($store[$key]['until']===1900,'Retry-After gateway respected');
}
foreach([''=>0,'0'=>0,'31'=>31,'999999999999999999999999999'=>3600,'+12'=>0,'-1'=>0,' 12'=>0,'12.5'=>0,'٣'=>0,'M'=>0] as $value=>$want)ok(search_health::retry_after((string)$value,1000)===$want,'ASCII Retry-After');
ok(search_health::retry_after(gmdate('D, d M Y H:i:s',1120).' GMT',1000)===120,'Date Retry-After');
foreach([null,true,[],['until'=>999999999,'reason'=>'refused','http_status'=>200,'curl_errno'=>0]] as $row){search_health::check('google','raw_ip::::',fn($key)=>$row,1000);ok(true,'Poisoned/invalid cache ignored');}
$f=new frontend();$images=['image'=>[['title'=>'Genuine previews','url'=>'https://example.com/page','source'=>[
 ['url'=>'https://example.com/original.png','width'=>2000,'height'=>1000],['url'=>'https://example.com/small.png','width'=>200,'height'=>100],['url'=>'https://example.com/large.png','width'=>900,'height'=>450]
]]]];
$items=image_results::items($f,['quality'=>'preview'],$images);ok(str_contains(urldecode($items[0]['preview']),'small.png'),'Smallest known supplied preview');ok(str_contains(urldecode($items[0]['original']),'original.png'),'Original retained');
$items=image_results::items($f,['quality'=>'original'],$images);ok(str_contains(urldecode($items[0]['preview']),'original.png'),'Original quality respected');
$images['image']=array_fill(0,6,$images['image'][0]);[$markup]=image_results::render($f,[],$images);
ok(substr_count($markup,'loading="eager"')===4 && substr_count($markup,'loading="lazy"')===2,'Bounded eager images');
ok(substr_count($markup,'fetchpriority="high"')===1&&substr_count($markup,'fetchpriority="auto"')===3,'Visible first row scheduling');
echo "PASS: $n health, Retry-After, privacy and genuine-preview assertions (offline).\n";
