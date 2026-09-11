<?php
require __DIR__.'/fixtures/cse-transport.php';
require 'data/config.php';require 'scraper/google_cse.php';
class GoogleFixtureBackend {
    public array $saved=[];
    public function get_ip(){return 'raw_ip::::';}
    public function assign_proxy($handle,$proxy){}
    public function store($data,$kind,$proxy){$this->saved[]=[$data,$kind,$proxy];return 'fixture-next';}
    public function get($token,$kind){return [$this->saved[count($this->saved)-1][0],'raw_ip::::'];}
}
$n=0;function ok($v,$label){global $n;$n++;if(!$v)throw new RuntimeException($label);}
function provider(){ $g=new google_cse();$b=new GoogleFixtureBackend();(new ReflectionClass($g))->getProperty('backend')->setValue($g,$b);return [$g,$b]; }
function request_for($g,$page){$get=['s'=>'GNU Guix','npt'=>false];foreach($g->getfilters($page)as $k=>$v)$get[$k]=array_key_first($v['option']);return $get;}
$bootstrap='})({"cse_token":"test:token","cselibVersion":"test-lib"});';
$web=['unescapedUrl'=>'https://example.org/a','titleNoFormatting'=>'A result','contentNoFormatting'=>[],'richSnippet'=>['cseThumbnail'=>['src'=>'https://example.org/img','width'=>100,'height'=>0]]];
foreach(['web','images']as $page){
 [$g,$backend]=provider();$get=request_for($g,$page);$method=$page==='web'?'web':'image';
 $record=$page==='web'?$web:['unescapedUrl'=>'https://example.org/original.jpg','titleNoFormatting'=>'A picture','tbUrl'=>'https://example.org/small.jpg','tbWidth'=>240,'tbHeight'=>120];
 $data=['results'=>[$record,'bad row'],'cursor'=>['isExactTotalResults'=>true,'pages'=>[['start'=>0],['start'=>10]]]];
 $rows=[['body'=>$bootstrap],['body'=>'/*O_o*/google.search.cse.api('.json_encode($data).');']];$calls=0;$requested=[];
 $result=$g->$method($get);$key=$page==='web'?'web':'image';
 ok(count($result[$key])===1 && $calls===2,'Cold request is one bootstrap plus results '.$page);
 ok(str_starts_with($requested[0],'https://cse.google.com/cse.js?cx='),'Documented loader used without hosted HTML');
 ok($result['npt']==='fixture-next'&&json_decode($backend->saved[0][0],true)['start']===10,'Actual 10 offset retained despite requested page size 20');
 $get['npt']='fixture-next';$rows=[['body'=>'google.search.cse.api('.json_encode(['results'=>[$record],'cursor'=>['pages'=>[['start'=>0],['start'=>10]],'isExactTotalResults'=>true]]).');']];$calls=0;
 $result=$g->$method($get);ok(count($result[$key])===1&&$result['npt']===null&&$calls===1,'Final/continuation results preserved without rebootstrap');
}
[$g]=provider();$get=request_for($g,'web');$rows=[['body'=>'/* Different supported loader */'],['body'=>'<script>relativeUrl = "/cse.js?cx=fixture";</script>'],['body'=>$bootstrap],['body'=>'google.search.cse.api({"results":[]});']];$calls=0;$requested=[];$result=$g->web($get);ok($result['web']===[]&&$calls===4,'Format-only legacy discovery bounded to one pass');
foreach([['status'=>429],['status'=>403],['body'=>'<html>captcha</html>']]as $failure){
 [$g]=provider();$get=request_for($g,'web');$rows=[$failure];$calls=0;
 try{$g->web($get);throw new LogicException('Refusal accepted');}catch(upstream_search_failure $e){ok($calls===1,'Refusal/challenge never triggers alternate discovery');}
}
foreach(['{}','{"results":["bad"]}','{"results":"bad"}']as $json){
 [$g]=provider();$get=request_for($g,'web');$rows=[['body'=>$bootstrap],['body'=>'google.search.cse.api('.$json.');']];$calls=0;
 try{$g->web($get);throw new LogicException('Bad schema accepted');}catch(upstream_search_failure $e){ok($e->reason==='format'&&$calls===2,'Bad page is honest format failure, not zero results');}
}
echo "PASS: $n actual web/image flow assertions with explicit offline transport doubles.\n";
