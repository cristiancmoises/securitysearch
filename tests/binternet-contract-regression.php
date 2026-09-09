<?php
require 'data/config.php'; require 'scraper/binternet.php';
$count=0;
function check($v,$label){global $count;$count++;if(!$v)throw new RuntimeException($label);}
$ref=new ReflectionClass('binternet');$route=$ref->getMethod('route');$image=$ref->getMethod('pin_image');$cursor=$ref->getMethod('valid_cursor');
foreach (['search.php?q=a','/search.php?q=a'] as $url) check($route->invoke(null,$url,'search.php')===['q'=>'a'],'Accepted bounded local route');
foreach (['https://images.securityops.co/search.php?q=a','//evil.test/search.php?q=a','../search.php?q=a','/other/search.php?q=a','search.php#x',"search.php?q=a\nb",'search.php\\x','/search.php?q=a b'] as $url) check($route->invoke(null,$url,'search.php')===null,'Rejected route '.$url);
foreach (['https://i.pinimg.com/originals/a.jpg','https://pinimg.com/236x/a.webp'] as $url) check($image->invoke(null,$url),'Pinimg HTTPS accepted');
foreach (['http://i.pinimg.com/a.jpg','https://i.pinimg.com.evil.test/a.jpg','https://127.0.0.1/a.jpg','https://[::1]/a.jpg','https://user@i.pinimg.com/a.jpg','https://i.pinimg.com:443/a.jpg','https://i.pinimg.com/a.jpg#frag','https://i.pinimg.com\\@evil.test/a.jpg',"https://i.pinimg.com/a\x00.jpg",['bad']] as $url) check(!$image->invoke(null,$url),'Unsafe image rejected');
foreach ([1,2064,4096] as $n) check($cursor->invoke(null,str_repeat('A',$n)),'Cursor byte bound '.$n);
foreach (['',str_repeat('A',4097),"a\r\nb",['bad']] as $v) check(!$cursor->invoke(null,$v),'Invalid cursor rejected');
class contract_binternet extends binternet {
 public array $seen=[];
 protected function fetch(array $params):string{$this->seen=$params;return '';}
 public function decode(string $body,string $format='any'):array{return ['status'=>'ok','npt'=>null,'image'=>[],'continuation'=>null];}
}
$b=new contract_binternet();
foreach (['teste','ação & "privacidade"',str_repeat('a',160),str_repeat('é',80),str_repeat('&',60)] as $q) {
 $b->image(['s'=>$q]);check($b->seen['q']===$q,'Raw UTF-8 query retained');
 check($b->seen['quality']==='saver' && $b->seen['scroll']==='manual','Small static provider previews');
}
foreach ([str_repeat('a',161),str_repeat('é',81),"a\0b","a\nb",'  ',"\xff"] as $q) {
 try{$b->image(['s'=>$q]);throw new LogicException('Invalid query accepted');}catch(RuntimeException $e){check(true,'Query rejected');}
}
echo "PASS: $count Binternet contract assertions (route, SSRF allowlist, 2064/4096-byte cursors, raw 160-byte UTF-8 queries).\n";
