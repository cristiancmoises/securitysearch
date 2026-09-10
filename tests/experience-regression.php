<?php
require 'data/config.php';require 'lib/service_pool.php';require 'lib/tranco.php';require 'lib/search_guard.php';
$n=0;function expect($v,$label){global $n;$n++;if(!$v)throw new RuntimeException($label);}
$origins=service_pool::origins();expect(count($origins)===3,'Three fixed origins');
$now=100000000000;$seen=[];$store=[];
$read=static function($k)use(&$store){return $store[$k][0]??false;};$write=static function($k,$v,$ttl)use(&$store){$store[$k]=[$v,$ttl];};$clock=static function()use(&$now){return $now;};
$res=service_pool::run($origins,function($o,$deadline)use(&$seen,&$now,$origins){$seen[]=$o;expect($deadline-$now<=3500000000,'Bound per-hop deadline');if($o===$origins[0])throw new RuntimeException('offline fixture failure');return ['news'=>[['title'=>'fixture']]];},$read,$write,$clock);
expect($res['origin']===$origins[1]&&count($seen)===2,'Sequential failover');expect(reset($store)[1]===20,'Brief cooldown');
$seen=[];service_pool::run($origins,function($o)use(&$seen){$seen[]=$o;return [];},$read,$write,$clock);expect(count($seen)===1&&$seen[0]===$origins[1],'Cooling primary skipped');
$seen=[];service_pool::run([$origins[2]],function($o)use(&$seen){$seen[]=$o;return [];},$read,$write,$clock);expect($seen===[$origins[2]],'Continuation fixed origin');
try{service_pool::run(['http://127.0.0.1'],fn()=>[], $read,$write,$clock);throw new LogicException('Accepted unsafe origin');}catch(InvalidArgumentException $e){$n++;}
$store=[];$seen=[];try{service_pool::run($origins,function($o,$deadline)use(&$now,&$seen){$seen[]=$o;$now=$deadline+1;return [];},$read,$write,$clock);throw new LogicException('Late success accepted');}catch(RuntimeException $e){expect(!($e instanceof LogicException)&&count($seen)<=3,'Deadline rejects late result');}
$stamp=strtotime('2026-09-10T12:00:00Z');
$row=securitysearch_tranco::parse('{"ranks":[{"date":"2026-09-08","rank":12345},{"date":"2026-09-09","rank":10000}]}',$stamp);expect($row['rank']===10000&&$row['date']==='2026-09-09','Most recent validated date (synthetic ranks)');
expect(securitysearch_tranco::parse('{"ranks":[]}',$stamp)['rank']===null,'Not in returned lists distinguished');
foreach(['{}','{"ranks":[{"date":"2026-09-31","rank":1}]}','{"ranks":[{"date":"2026-09-11","rank":1}]}','{"ranks":[{"date":"2026-09-09","rank":-1}]}',str_repeat('x',65537)]as$bad){try{securitysearch_tranco::parse($bad,$stamp);throw new LogicException('Accepted bad metadata');}catch(Throwable $e){expect(!($e instanceof LogicException),'Invalid metadata refused');}}
class good_provider {public function web($get){return ['status'=>'ok'];}}
class network_provider {public function web($get){throw new provider_http_failure('fixture network failure');}}
class parser_provider {public function web($get){throw new RuntimeException('fixture parser error');}}
$store=[];expect(search_guard::run(new good_provider(),'web',[],$read,$write)['status']==='ok','Normal response');
try{search_guard::run(new network_provider(),'web',[],$read,$write);}catch(provider_http_failure $e){}
expect(count($store)===1&&reset($store)[1]===8,'Definite transport failure cooled');
try{search_guard::run(new network_provider(),'web',[],$read,$write);throw new LogicException('Expected cooldown');}catch(RuntimeException $e){expect(str_contains($e->getMessage(),'8 seconds'),'Cooldown truthfully described');}
$store=[];try{search_guard::run(new parser_provider(),'web',[],$read,$write);}catch(RuntimeException $e){}expect(!$store,'Parser failure does not blacklist provider');
try{search_guard::run(new network_provider(),'web',['npt'=>'fixture'],$read,$write);}catch(provider_http_failure $e){}expect(!$store,'Pagination not poisoned by global first-page health');
echo "PASS: $n pool, deadlines, metadata parsing and search-health assertions. No real provider requests.\n";
