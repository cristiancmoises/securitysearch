<?php
require 'data/config.php';require 'lib/frontend.php';require 'lib/search_execution.php';require 'scraper/google.php';require 'scraper/brave.php';
function verify($value,$label){if(!$value)throw new RuntimeException($label);}
// Load both real providers to catch shared-import class redeclarations.
$google=new google();$brave=new brave();
$deadline=hrtime(true)+500000000;$google->set_request_deadline($deadline);$brave->set_request_deadline($deadline);
$br=new ReflectionClass($brave);$brave->set_request_deadline($deadline+1000000000);
verify($br->getProperty('request_deadline')->getValue($brave)===$deadline,'Deadline never expands');
$brave->set_request_deadline(hrtime(true)-1);
try{$br->getMethod('remaining_network_ms')->invoke($brave);throw new LogicException('Expired budget allowed');}catch(RuntimeException $e){}
class provider_fixture {
 public int $calls=0;public int $deadline=0;public int $deadline_set_at=0;public array $received=[];public bool $fail=false;
 public function set_request_deadline(int $deadline):void {$this->deadline=$deadline;$this->deadline_set_at=hrtime(true);}
 public function web($get):array {$this->calls++;$this->received=$get;if($this->fail)throw new RuntimeException('Google temporarily rate-limited this instance.');return ['web'=>[]];}
 public function image($get):array {$this->web($get);return ['image'=>[],'npt'=>null];}
}
class execution_frontend extends frontend {
 public provider_fixture $alternative;public int $selected=0;public int $selection_delay_us=0;
 public function __construct(){$this->alternative=new provider_fixture();}
 public function getscraperfilters($page){$this->selected++;if($this->selection_delay_us>0)usleep($this->selection_delay_us);return [$this->alternative,['s'=>['option'=>'_SEARCH'],'scraper'=>['option'=>['brave'=>'Brave']],'view'=>['option'=>['grid'=>'Grid','filmstrip'=>'Filmstrip']],'quality'=>['option'=>['preview'=>'Preview','high'=>'High']],'nsfw'=>['option'=>['yes'=>'Yes','no'=>'No']],'newer'=>['option'=>'_DATE'],'format'=>['option'=>['any'=>'Any','gif'=>'GIF']]]];}
}
// Exercise immediate and deliberately delayed selection. Scheduling latency is
// not part of the provider's assigned eight-second budget.
foreach([0,50000] as $selection_delay_us) foreach(['web','images'] as $page){
 $f=new execution_frontend();$f->selection_delay_us=$selection_delay_us;$provider=new provider_fixture();$get=['s'=>'GNU Guix','scraper'=>'google','npt'=>false,'view'=>'filmstrip','quality'=>'high','nsfw'=>'no','newer'=>strtotime('2025-01-01'),'format'=>'gif'];$filters=['newer'=>['option'=>'_DATE']];$_GET=$get;
 [$results,$notice]=search_execution::run($f,$provider,$get,$filters,$page);
 verify($f->selected===0 && $notice==='' && $provider->calls===1,'Genuine Google empty result does not fallback');
 $provider->fail=true;$initial_provider=$provider;
 [$results,$notice]=search_execution::run($f,$provider,$get,$filters,$page);
 verify($f->selected===1 && $f->alternative->calls===1 && $get['scraper']==='brave' && str_contains($notice,'Brave'),'Exactly one identified fallback');
 verify($get['s']==='GNU Guix' && $get['view']==='filmstrip' && $get['quality']==='high' && $get['format']==='gif' && $get['nsfw']==='no' && $get['newer']===strtotime('2025-01-01'),'Shared fields and dates survive fallback');
 verify($provider->deadline>0 && $provider->deadline<=$provider->deadline_set_at+8000000000,'Brave budget bounded from assignment');
 // Google receives start+12s, so +8s is the original shared start+20s limit.
 verify($provider->deadline<=$initial_provider->deadline+8000000000,'Fallback never exceeds original shared deadline');
}
foreach([['google','invalid',false],['google','0',false],['google',false,true],['google_api',false,false],['brave',false,false]] as [$name,$token,$append]){
 $f=new execution_frontend();$provider=new provider_fixture();$provider->fail=true;$get=['s'=>'GNU Guix','scraper'=>$name,'npt'=>$token];$filters=[];$_GET=$get;
 try{search_execution::run($f,$provider,$get,$filters,'images',$append);throw new LogicException('Failure hidden');}catch(RuntimeException $e){}
 verify($f->selected===0,'No fallback for continuations, append or non-default provider');
}
$f=new execution_frontend();$f->alternative->fail=true;$provider=new provider_fixture();$provider->fail=true;$get=['s'=>'GNU Guix','scraper'=>'google','npt'=>false];$filters=[];$_GET=$get;$saved=$_GET;
try{search_execution::run($f,$provider,$get,$filters,'web');throw new LogicException('Both failures hidden');}catch(RuntimeException $e){verify(str_contains($e->getMessage(),'Google and its Brave fallback'),'Both provider failures reported');}
verify($_GET===$saved && $get['scraper']==='google','Failure restores original routing state');
echo "PASS: real shared imports, shrinking deadlines, one Google fallback, empty success, filter/date retention, continuation isolation and honest dual failure.\n";
