<?php
require 'data/config.php';require 'scraper/newswire.php';
$n=0;function nw($ok,$message){global $n;$n++;if(!$ok)throw new RuntimeException($message);}
class fixture_rss extends newswire {
 public array $cache=[],$writes=[],$calls=[];public int $t=1000;public string $mode='ok';public bool $lockOk=true;
 protected function read(string $key){return $this->cache[$key]??false;}
 protected function write(string $key,$value,int $ttl):void{$this->cache[$key]=$value;$this->writes[]=[$key,$ttl];}
 protected function lock(string $key):bool{return $this->lockOk;}
 protected function unlock(string $key):void{}
 protected function now():int{return $this->t;}
 protected function clock():int{return 1000000000;}
 protected function fetch(string $id,string $q,string $market,int $deadline):string{
  $this->calls[]=[$id,$q,$market,$deadline];
  if($this->mode==='fail' || ($this->mode==='fallback'&&$id==='google'))throw new news_failure('http_refused',418);
  return $this->mode==='empty'?'empty':'ok';
 }
 protected function decode(string $body):array{return ['status'=>'ok','npt'=>null,'news'=>$body==='empty'?[]:[['title'=>'Fixture','url'=>'https://example.org/x']]];}
}
$f=new fixture_rss();$a=$f->news(['s'=>'']);$b=$f->news(['s'=>'']);nw(count($f->calls)===1&&$b['_cached']===true,'public feed cache eliminates second request');
$f->t+=121;$f->mode='fail';$c=$f->news(['s'=>'']);nw($c['_stale']===true&&$c['_fetched_at']===1000,'stale feed only labelled retained timestamp');
$f->t+=600;try{$f->news(['s'=>'']);throw new LogicException('expired cached data');}catch(news_failure $e){nw(true,'stale expires');}
$f=new fixture_rss();$f->news(['s'=>'private query']);$f->news(['s'=>'private query']);nw(count($f->calls)===2&&$f->writes===[],'no keyword result cache');
$f=new fixture_rss();$f->mode='fallback';$r=$f->news(['s'=>'technology']);nw($r['_source']==='bing'&&count($f->calls)===2,'one bounded independent fallback');
nw($f->writes[0][1]===600&&!str_contains(json_encode($f->writes),'technology'),'refusal cache has no query');
$f->calls=[];$f->news(['s'=>'different query']);nw(array_column($f->calls,0)===['bing'],'cooled source is not retried');
$f=new fixture_rss();$f->mode='empty';$r=$f->news(['s'=>'rare query']);nw($r['news']===[]&&count($f->calls)===1,'valid empty search is not a failure or fallback');
$f=new fixture_rss();$f->mode='fallback';try{$f->news(['s'=>'x','source'=>'google']);throw new LogicException('unexpected fallback');}catch(news_failure $e){nw(count($f->calls)===1,'explicit source forbids fallback');}
$f=new fixture_rss();$f->lockOk=false;try{$f->news(['s'=>'']);throw new LogicException('ignored lock');}catch(news_failure $e){nw($f->calls===[],'concurrent feed request does not stampede');}
foreach([['s'=>[]],['s'=>str_repeat('x',501)],['s'=>'x','source'=>'evil'],['s'=>'x','market'=>'evil'],['s'=>'x','npt'=>'reddit1.abc']]as$input){$f=new fixture_rss();try{$f->news($input);throw new LogicException('invalid accepted');}catch(news_failure $e){nw($f->calls===[],'invalid input/continuation before fetch');}}
$f=new fixture_rss();$f->news(['s'=>'','market'=>'pt-BR']);$f->news(['s'=>'','market'=>'en-US']);nw(count($f->calls)===2,'cache is edition isolated');
echo "PASS: $n RSS orchestration assertions with offline transport/decoder fixtures.\n";
