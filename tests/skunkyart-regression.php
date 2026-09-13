<?php
require 'data/config.php';require 'scraper/skunkyart.php';
$n=0;function check($ok,$why){global $n;$n++;if(!$ok)throw new RuntimeException($why);}
function bad($call,$why){try{$call();}catch(RuntimeException $e){check(true,$why);return;}check(false,$why);}
class skunky_fixture extends skunkyart {
 public array $requests=[];public array $stored=[];public array $prior=[];public string $body='';
 public function __construct(){}
 protected function parameters(array $get,string $kind):array{return $this->prior ?: ['q'=>$get['s']??''];}
 protected function fetch(array $params):string{$this->requests[]=$params;return $this->body;}
 protected function continuation(array $params,string $kind):string{$this->stored=$params;return 'opaque-page';}
}
function row($id=1){return ['original_url'=>'https://www.deviantart.com/artist/art/Work-'.$id,'preview'=>'/media?t=cHJldmlldw.c2ln','full'=>'/media?t=ZnVsbA.c2ln','mature'=>false,'title'=>'Title & <test>','width'=>1600,'height'=>1200];}
function body($rows,$more=false){return json_encode(['items'=>$rows,'has_more'=>$more],JSON_THROW_ON_ERROR);}
$p=new skunky_fixture();$r=$p->decode(body([row()]));
check(count($r['image'])===1,'one real row');check($r['image'][0]['source'][0]['width']===1600,'full dimensions');
check($r['image'][0]['source'][1]['width']===null,'no invented preview dimensions');
check(str_starts_with($r['image'][0]['source'][0]['url'],'https://skunkyart.securityops.co/media?t='),'signed service media');
check($r['image'][0]['url']===row()['original_url'],'original link');
foreach(['http://127.0.0.1/x','https://evil.invalid/media?t=a.b','//evil.invalid/x','/media?t=a.b&url=x','/media?t=a.b#x','/media?t=a.b\r\n','https://skunkyart.securityops.co.evil.invalid/media?t=a.b'] as $bad){$x=row();$x['full']=$x['preview']=$bad;bad(fn()=>$p->decode(body([$x])),'refuse foreign media');}
foreach(['http://www.deviantart.com/a/art/b','https://www.deviantart.com.evil.invalid/a/art/b','https://a:b@www.deviantart.com/a/art/b','file:///etc/passwd','https://www.deviantart.com/a/art/../b'] as $bad){$x=row();$x['original_url']=$bad;bad(fn()=>$p->decode(body([$x])),'refuse invalid original');}
check(count($p->decode(body([row(),row()]))['image'])===1,'dedupe');
$x=row();$x['mature']=true;check(count($p->decode(body([$x]))['image'])===0,'mature hidden');check(count($p->decode(body([$x]),true)['image'])===1,'explicit mature allowed');
$x=row();unset($x['mature']);bad(fn()=>$p->decode(body([$x])),'unknown mature label');
$x=row();$x['title']=str_repeat('é',128*1024);$out=$p->decode(body([$x]));check(strlen($out['image'][0]['title'])<=1024,'bounded title');check(preg_match('//u',$out['image'][0]['title'])===1,'valid UTF8');
$x=row();$x['title']='';check($p->decode(body([$x]))['image'][0]['title']==='DeviantArt artwork','fallback label');
foreach(['{}','[]','not-json','{"error":"secret"}',body(array_fill(0,101,row())),str_repeat('x',2097153),'"items"','{"items":[],"has_more":1}'] as $bad)bad(fn()=>$p->decode($bad),'invalid response');
check($p->decode(body([]))['image']===[],'genuine empty');
$p->body=body([row()],true);$out=$p->image(['s'=>'0','nsfw'=>'no','orientation'=>'landscape','ai'=>'hide']);
check(count($p->requests)===1,'one request per page');check($out['npt']==='opaque-page','continuation');check($p->stored['p']===2,'page increment');check($p->requests[0]['q']==='0','literal zero query');check($p->requests[0]['mature']==='hide','strict mature');check($p->requests[0]['media']==='image','images only');
$p->prior=$p->stored;$p->image(['s'=>'other','nsfw'=>'yes']);check($p->requests[1]['q']==='0','original query on nextpage');check($p->requests[1]['orientation']==='landscape','filter retention');check($p->requests[1]['mature']==='hide','cannot widen token');
$p->prior=['q'=>'x','p'=>417,'mature'=>'include'];$out=$p->image(['nsfw'=>'no']);check($out['npt']===null,'upperpagebound');check(end($p->requests)['mature']==='hide','strict user wins');
foreach([['q'=>'x','p'=>0],['q'=>'x','p'=>418],['q'=>'x','p'=>'2'],['q'=>'x','p'=>1,'orientation'=>'unknown'],['q'=>'x','mature'=>'bad'],['q'=>''],['q'=>str_repeat('x',501)],['q'=>"x\0y"]] as $param){$p->prior=$param;$before=count($p->requests);bad(fn()=>$p->image(['nsfw'=>'no']),'reject request');check(count($p->requests)===$before,'rejection before request');}
echo "PASS: $n SkunkyArt assertions; synthetic JSON, no provider requests.\n";
