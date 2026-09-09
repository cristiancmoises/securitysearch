<?php
require 'data/config.php';
require 'lib/frontend.php';
require 'scraper/invidious.php';
require 'scraper/binternet.php';
function verify($ok,$label) { if (!$ok) { throw new RuntimeException($label); } }
$video=new invidious();
$fixture=json_encode([
 ['type'=>'video','videoId'=>'jRp4D9Xa4ek','title'=>'<script>alert(1)</script>','author'=>'A & B','lengthSeconds'=>321,'viewCount'=>42],
 ['type'=>'video','videoId'=>'<script>bad'], ['type'=>'channel','videoId'=>'jRp4D9Xa4ek']
]);
$result=$video->decode($fixture);
verify(count($result['video'])===1,'Only validated videos are rendered');
verify($result['video'][0]['url']==='https://invidious.securityops.co/watch?v=jRp4D9Xa4ek','Invidious playback');
$html=(new frontend())->drawtextresult($result['video'][0],null,null,'test');
verify(!str_contains($html,'<script>'),'Provider title escaped');
foreach (['<html>captcha</html>','{"error":"API disabled"}','null'] as $bad) {
 try { $video->decode($bad); throw new LogicException('Invalid payload accepted'); }
 catch (RuntimeException $e) { verify(!($e instanceof LogicException),'Fail closed on invalid API response'); }
}
$bin=new binternet();
$mk=fn($url)=>'<a class="img-result" href="/image_proxy.php?url='.rawurlencode($url).'"><img alt="A &amp; B"></a>';
$fixture='<div class="img-container">'.$mk('https://i.pinimg.com/originals/a.webp').$mk('https://i.pinimg.com/originals/a.webp').$mk('https://i.pinimg.com/originals/b.gif').$mk('https://127.0.0.1/internal').$mk('https://i.pinimg.com.evil.test/a.gif').$mk('https://user@i.pinimg.com/a.gif').'</div><a href="/search.php?q=ignored&amp;bookmark=next%2Bpage&amp;csrftoken=csrf">Next page</a>';
$result=$bin->decode($fixture);
verify(count($result['image'])===2,'Deduplication and remote host checks');
verify($result['continuation']===['bookmark'=>'next+page','csrftoken'=>'csrf'],'Only bounded continuation fields are retained');
verify(count($bin->decode($fixture,'webp')['image'])===1,'WebP extension filter');
verify(count($bin->decode($fixture,'gif')['image'])===1,'GIF extension filter');
verify(count($bin->decode($fixture,'png')['image'])===0,'Valid empty filtered page');
verify($bin->decode('<div class="img-container"></div>')['image']===[],'Recognizable empty result container');
try { $bin->decode('<html>Access denied</html>'); throw new LogicException('Challenge accepted'); }
catch (RuntimeException $e) { verify(!($e instanceof LogicException),'Unrecognized upstream page rejected'); }
$malicious=str_replace('/search.php?q=ignored','https://evil.test/search.php?q=ignored',$fixture);
verify($bin->decode($malicious)['continuation']===null,'Cross-origin next link rejected');
class fixture_invidious extends invidious {
 public array $seen=[];
 protected function fetch(array $params): string { $this->seen[]=$params;return '[{"type":"video","videoId":"jRp4D9Xa4ek","title":"fixture"}]'; }
}
$provider=new fixture_invidious();
$first=$provider->video(['s'=>'ação & privacy','sort'=>'views','npt'=>false]);
$second=$provider->video(['s'=>'changed','npt'=>$first['npt']]);
verify($provider->seen[1]['page']===2 && $provider->seen[1]['q']==='ação & privacy' && $provider->seen[1]['sort']==='views','Encrypted pagination preserves original query/filters');
$f=new frontend();$_GET=['scraper'=>'binternet'];$_COOKIE=[];
[$provider,$filters]=$f->getscraperfilters('images');
verify(get_class($provider)==='binternet','Binternet selectable');
foreach (['s'=>['bad'],'view'=>['bad'],'quality'=>['bad']] as $key=>$value) {
 $get=$f->parsegetfilters([$key=>$value],$filters); verify(!is_array($get[$key]) && $get[$key]!==null,'Array input normalized');
}
verify($f->parsegetfilters(['quality'=>'high'],$filters)['quality']==='high','High quality selectable');
$resolve=(new ReflectionClass('proxy'))->getMethod('resolvepublictarget');
foreach (['http://100.64.0.1/','http://198.18.0.1/','http://224.0.0.1/','http://[::ffff:127.0.0.1]/','http://[fc00::1]/','http://[ff02::1]/'] as $url) {
 verify($resolve->invoke(new proxy(false),$url)===false,'Special-use address rejected: '.$url);
}
verify($resolve->invoke(new proxy(false),'https://8.8.8.8/')!==false,'Public IPv4 remains usable');
foreach (['home.html','images.html','search.html'] as $template) { verify(str_contains($f->load($template),'In Code We Trust.'),'Centered trust footer'); }
echo "PASS: service parsers, escaping, filters, hostile URLs, pagination, provider selection, input bounds, SSRF ranges and footer.\n";
