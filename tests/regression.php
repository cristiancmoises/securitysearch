<?php
// Offline regression checks. Run from repository root: php -d apc.enable_cli=1 tests/regression.php
require 'data/config.php';
require 'lib/frontend.php';
function check($value, $label){ if(!$value){ throw new RuntimeException($label); } }
$frontend = new frontend();
$_GET = ['scraper'=>'google']; $_COOKIE = [];
[$provider, $filters] = $frontend->getscraperfilters('images');
foreach(['grid','compact','gallery','feed','list','filmstrip'] as $view){
 $get = $frontend->parsegetfilters(['s'=>'GNU Guix','view'=>$view], $filters);
 check($get['view']===$view, 'View whitelist: '.$view);
}
foreach(['<script>', ['gallery']] as $view){
 $get = $frontend->parsegetfilters(['s'=>'GNU Guix','view'=>$view], $filters);
 check(!in_array($get['view'], ['<script>', ['gallery']], true), 'Invalid view rejected');
}
$header = $frontend->load('header.html');
foreach(['Img'=>'img', 'Vids'=>'invidious', 'Chat'=>'chat', 'News'=>'news', 'Wiki'=>'wiki', 'Zupt'=>'zupt-web', 'Reddit'=>'libre'] as $label=>$host){
 foreach(['header.html','home.html'] as $template){
  check(preg_match('#href="https://'.preg_quote($host, '#').'\.securityops\.co/"[^>]*>'.preg_quote($label, '#').'</a>#', $frontend->load($template))===1, 'Correct external navigation: '.$template.' '.$label);
 }
}
$query = 'Guix & "privacy" <script> ação';
$suggestion = $frontend->video_suggestion($query);
check(str_contains($suggestion, 'https://invidious.securityops.co/search?q='.rawurlencode($query)), 'Invidious query encoded');
check(!str_contains($suggestion, '<script>') && !str_contains($suggestion, '<iframe') && !str_contains($suggestion, '<img'), 'Suggestion is opt-in, not an embed or query leak');
check(!str_contains($header, '{%video_suggestion%}'), 'Non-video header placeholder cleared');
$first = $frontend->load('header.html', ['search'=>'unique-private-query-one']);
$second = $frontend->load('header.html', ['search'=>'unique-private-query-two']);
check(str_contains($first, 'unique-private-query-one') && !str_contains($second, 'unique-private-query-one'), 'Shared template cache never caches rendered queries');
if(function_exists('apcu_cache_info')){
 foreach(apcu_cache_info(true) === false ? [] : (apcu_cache_info()['cache_list'] ?? []) as $entry){
  if(str_starts_with($entry['info'], 'securitysearch-template-')){
   $cached = apcu_fetch($entry['info']);
   check(!str_contains($cached, 'unique-private-query-'), 'Raw template cache contains no private query');
  }
 }
}
try{$frontend->load('../data/config.php');throw new LogicException('Traversal accepted');}
catch(InvalidArgumentException $e){}
require 'lib/curlproxy.php';
$resolve = (new ReflectionClass('proxy'))->getMethod('resolvepublictarget');
foreach(['http://user:secret@8.8.8.8/a.png','https://user@8.8.8.8/a.png','file:///etc/passwd','http://127.0.0.1/','http://[::1]/'] as $unsafe){
 check($resolve->invoke(new proxy(false), $unsafe)===false, 'Image proxy rejects credentials and private/non-HTTP targets');
}
check(str_contains($header, '/static/themes/Tron.css?v'.config::VERSION), 'Tron default');
preg_match_all('/url\("(\/static\/[^"?]+)(?:\?[^" ]*)?"\)/', file_get_contents('static/themes/Tron.css'), $assets);
foreach($assets[1] as $asset){
 check(is_file(ltrim($asset, '/')), 'Tron referenced asset exists: '.$asset);
}
$_COOKIE['theme'] = 'Lain';
check(str_contains($frontend->load('header.html'), '/static/themes/Lain.css'), 'Saved theme preserved');
$_COOKIE['theme'] = '../../etc/passwd';
check(str_contains($frontend->load('header.html'), '/static/themes/Tron.css'), 'Traversal theme rejected');
$r = new ReflectionClass('google_cse');
$provider = (new ReflectionClass('google'))->getProperty('delegate')->getValue($provider);
$decode = $r->getMethod('decode_response');
check($decode->invoke($provider, 'google.search.cse.api({"results":[]});')===['results'=>[]], 'Valid JSONP');
foreach(['<html>captcha</html>', 'google.search.cse.api(null);', 'broken'] as $input){
 try{$decode->invoke($provider, $input); throw new LogicException('Invalid response accepted');}
 catch(Exception $e){check(!($e instanceof LogicException), 'Malformed and challenge responses rejected');}
}
$deadline = $r->getProperty('request_deadline');
$budget = $r->getMethod('remaining_network_ms');
$deadline->setValue($provider, hrtime(true)+30000000000);
check($budget->invoke($provider)===20000, 'Per-hop budget capped');
$deadline->setValue($provider, hrtime(true)-1);
try{$budget->invoke($provider); throw new LogicException('Expired budget accepted');}
catch(Exception $e){check(!($e instanceof LogicException), 'Total budget enforced');}
if(function_exists('apcu_store')){
	$b = new backend('google');
	$token = $b->store('test payload', 'images', 'raw_ip::::');
	foreach(['broken', explode('.', $token)[0].'.x', explode('.', $token)[0].'.'.str_repeat('A',43)] as $bad){
		try{$b->get($bad,'images'); throw new LogicException('Bad token accepted');}
		catch(Exception $e){check(!($e instanceof LogicException),'Malformed or unauthenticated token rejected');}
	}
	check($b->get($token,'images')[0]==='test payload','Valid token still works after invalid attempts');
	try{$b->get($token,'images'); throw new LogicException('Token replay accepted');}
	catch(Exception $e){check(!($e instanceof LogicException),'Token expires after use');}
 apcu_store('securitysearch-test-cooldown', true, 30);
 try{$r->getMethod('throw_cse_request_cooldown')->invoke($provider,'securitysearch-test-cooldown'); throw new LogicException('Cooldown ignored');}
 catch(Exception $e){check(!($e instanceof LogicException), 'Cooldown prevents repeat upstream calls');}
 apcu_delete('securitysearch-test-cooldown');
}
echo "PASS: layouts, input validation, themes, JSONP, challenges, deadlines, tokens and cooldown.\n";
