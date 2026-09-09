<?php
require 'data/config.php';require 'lib/frontend.php';require 'lib/image_results.php';
function verify($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$f=new frontend();libxml_use_internal_errors(true);
foreach (glob('template/*.html') as $file) {
 $html=file_get_contents($file);verify(!preg_match('/<script\b|\son[a-z]+\s*=|javascript:/i',$html),'No executable template script: '.$file);
}
verify(glob('static/*.js')===['static/images-infinite.js','static/images-motion.js'],'Only scoped image enhancements are shipped');
verify(!file_exists('lib/image_flow.php'),'Timer snapshots removed');
foreach (['home.html','header.html'] as $template) {
 $html=$f->load($template);$dom=new DOMDocument();$dom->loadHTML($html);$xp=new DOMXPath($dom);
 $buttons=$xp->query('//div[contains(@class,"search-actions")]/button');
 verify($buttons->length===4,'Four native search actions');
 foreach ($buttons as $button) {
  verify($button->getAttribute('aria-label')===trim($button->textContent),'Icon button retains full accessible name');
  $icons=$button->getElementsByTagName('svg');
  verify($icons->length===1 && $icons[0]->getAttribute('aria-hidden')==='true' && $icons[0]->getAttribute('focusable')==='false','Decorative icon never replaces button semantics');
 }

 verify(array_map(fn($n)=>trim($n->textContent),iterator_to_array($buttons))===['Search','Search Image','Search Pinterest','Search YouTube'],'Requested button order');
 foreach ([1=>['/images','images'],2=>['/images','binternet'],3=>['/videos','invidious']] as $index=>$route) {
  verify($buttons[$index]->getAttribute('formaction')===$route[0] && $buttons[$index]->getAttribute('name')==='destination' && $buttons[$index]->getAttribute('value')===$route[1],'Native action routing');
 }
 verify(!str_contains($html,'search-destinations'),'Below-bar duplicate actions removed');
}
foreach (['binternet'=>['images','binternet'],'invidious'=>['videos','invidious'],'images'=>['images','google']] as $destination=>[$page,$expected]) {
 $_GET=['s'=>'GNU Guix','scraper'=>'brave','destination'=>$destination,'npt'=>'untrusted','view'=>'filmstrip','quality'=>'high'];$_COOKIE=[];
 [$provider,$filters]=$f->getscraperfilters($page);$get=$f->parsegetfilters($_GET,$filters);
 verify(get_class($provider)===$expected && $get['npt']===false,'Action overrides scraper select and removes old continuation');
}
$query='GNU Guix & "<script>"';
[$title,$body]=$f->provider_recovery('Google temporarily rate-limited this instance. <img src=x onerror=x>',['s'=>$query,'scraper'=>'google','view'=>'filmstrip','quality'=>'high','format'=>'gif','npt'=>'secret','frame'=>'secret','flow_start'=>'1'],'images');
verify($title==='Google is temporarily unavailable' && !str_contains($body,'<img'),'Provider recovery escapes diagnostics');
$dom=new DOMDocument();$dom->loadHTML($body);
foreach ($dom->getElementsByTagName('a') as $a) {
 $url=$a->getAttribute('href');verify(str_starts_with($url,'/'),'Recovery is an explicit same-origin action');parse_str(parse_url($url,PHP_URL_QUERY) ?? '',$params);
 verify(!isset($params['npt']) && !isset($params['frame']) && !isset($params['flow_start']),'Recovery never replays flow');
 if (isset($params['s'])) { verify($params['s']===$query && $params['view']==='filmstrip' && $params['quality']==='high','Recovery retains query/display'); }
}
$fixture=['title'=>'<svg onload=alert(1)>','url'=>'https://example.org/page','source'=>[['url'=>'javascript:alert(1)'],['url'=>'https://user:pass@example.org/a.gif'],['url'=>'https://example.org/a.gif','width'=>400,'height'=>300]]];
$bounded=image_results::bounded(['image'=>array_fill(0,40,$fixture),'npt'=>'next']);
[$html,$count]=image_results::render($f,['s'=>'GNU Guix','quality'=>'high'],$bounded);
verify($count===24 && $bounded['omitted']===16,'Rendered request count is bounded');
verify(!str_contains($html,'<svg') && !str_contains($html,'javascript:') && !str_contains($html,'user:pass'),'Image/title URL safety');
verify(str_contains($html,'View animation') && str_contains($html,'Original') && str_contains($html,'Preview'),'Native media alternatives');
verify(str_contains($html,'data-motion=') && str_contains($html,'s=animated') && str_contains($html,'s=poster'),'Animated candidates retain a true poster and a scoped motion source');
$policy=file_get_contents('lib/security_headers.php');foreach (['script','connect','worker'] as $kind) { verify(str_contains($policy,$kind."-src 'none'"),'CSP disables '.$kind); }
echo "PASS: native forms, scoped image enhancement, provider recovery, bounded cards and malicious result escaping.\n";
