<?php
// Offline regression checks. Run from repository root: php -d apc.enable_cli=1 tests/regression.php
require 'data/config.php';
require 'lib/frontend.php';
function check($value, $label){ if(!$value){ throw new RuntimeException($label); } }
$frontend = new frontend();
$_GET = ['scraper'=>'google']; $_COOKIE = [];
[$provider, $filters] = $frontend->getscraperfilters('images');
foreach(['grid','compact','gallery','feed'] as $view){
 $get = $frontend->parsegetfilters(['s'=>'GNU Guix','view'=>$view], $filters);
 check($get['view']===$view, 'View whitelist: '.$view);
}
foreach(['<script>', ['gallery']] as $view){
 $get = $frontend->parsegetfilters(['s'=>'GNU Guix','view'=>$view], $filters);
 check(!in_array($get['view'], ['<script>', ['gallery']], true), 'Invalid view rejected');
}
$header = $frontend->load('header.html');
check(str_contains($header, '/static/themes/Lain.css?v14'), 'Lain default');
$_COOKIE['theme'] = 'Tron';
check(str_contains($frontend->load('header.html'), '/static/themes/Tron.css'), 'Saved theme preserved');
$_COOKIE['theme'] = '../../etc/passwd';
check(str_contains($frontend->load('header.html'), '/static/themes/Lain.css'), 'Traversal theme rejected');
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
