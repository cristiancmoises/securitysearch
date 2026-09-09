<?php
if (!class_exists('DOMDocument') || !function_exists('mb_strcut')) {fwrite(STDERR,"SKIP: DOM and mbstring are required.\n");exit(77);}
require 'data/config.php';require 'scraper/binternet.php';
$n=0;function check($v,$label){global $n;$n++;if(!$v)throw new RuntimeException($label);}
$b=new binternet();
$original='https://i.pinimg.com/originals/ab/picture.webp';$preview='https://i.pinimg.com/236x/ab/picture.webp';
$card='<figure class="image-card"><a class="image-link" href="image_proxy.php?url='.rawurlencode($original).'"><img src="image_proxy.php?url='.rawurlencode($preview).'" width="1200" height="900" alt="A &amp; B"></a></figure>';
$wrap=fn($cards)=>'<div id="image-gallery" class="image-gallery"><section class="gallery-page">'.$cards.'</section></div>';
foreach ([1,2064,4096] as $len) {
 $bookmark=str_repeat('A',$len-1).'+';
 $html=$wrap($card.$card).'<nav><a id="next-page" rel="next" href="search.php?q=ignored&amp;bookmark='.rawurlencode($bookmark).'">More →</a></nav>';
 $out=$b->decode($html);
 check(count($out['image'])===1,'Modern results and deduplication');
 check($out['continuation']===['bookmark'=>$bookmark],'Modern relative continuation '.$len);
 check($out['image'][0]['title']==='A & B','UTF-8/entity title');
 check($out['image'][0]['source'][0]===['url'=>$original,'width'=>1200,'height'=>900],'Original dimensions retained');
 check($out['image'][0]['source'][1]===['url'=>$preview,'width'=>236,'height'=>177],'Smaller real preview, no URL invention');
 check($b->decode($html,'png')['image']===[],'Legitimate empty format result');
}
check($b->decode($wrap(''))['image']===[],'Modern empty gallery');
$badnext=['https://evil.test/search.php?bookmark=next','//evil.test/search.php?bookmark=next','search.php?bookmark%5B%5D=next','search.php?bookmark='.str_repeat('A',4097),'search.php?bookmark=%0D%0A'];
foreach ($badnext as $next) check($b->decode($wrap($card).'<a rel="next" href="'.$next.'">More</a>')['continuation']===null,'Unsafe continuation ignored');
foreach (['<h1>Access denied</h1>','<section class="empty-state">Pinterest refused this search</section>',$wrap('<a class="image-link" href="https://evil.test/a.jpg"><img></a>')] as $html) {
 try{$b->decode($html);throw new LogicException('Upstream error hidden');}catch(RuntimeException $e){check(true,'Failure is not empty success');}
}
class modern_fixture extends binternet {
 public string $html='';public array $state=['q'=>'teste'];
 protected function parameters(array $get,string $kind):array{return $this->state;}
 protected function fetch(array $params):string{return $this->html;}
 protected function continuation(array $params,string $kind):string{return json_encode($params);}
}
$f=new modern_fixture();$f->html=$wrap($card).'<a rel="next" href="search.php?bookmark=same">Next</a>';$f->state=['q'=>'teste','bookmark'=>'same'];
check($f->image([])['npt']===null,'Repeated cursor cannot loop');
$f->state=['q'=>'teste'];check(json_decode($f->image([])['npt'],true)===['q'=>'teste','bookmark'=>'same'],'Original query retained in continuation');
echo "PASS: $n modern Binternet DOM parser assertions.\n";
