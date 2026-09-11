<?php
// Cache coordination tests with explicit child-process APCu doubles; no network.
$targets=['apcu_fetch','apcu_store','apcu_enabled','apcu_add','apcu_cas','apcu_delete'];
foreach($targets as $f)if(function_exists($f)){fwrite(STDERR,"Run with the isolated command in scripts/test.sh.\n");exit(2);}
$memory=[];$after_add=null;$after_fetch=null;$deleted=[];
if(!function_exists('apcu_fetch')){
 function apcu_enabled(){return true;}
 function apcu_fetch($key,&$hit=null){
  $hit=array_key_exists($key,$GLOBALS['memory']);$value=$GLOBALS['memory'][$key]??false;
  if(is_callable($GLOBALS['after_fetch']))($GLOBALS['after_fetch'])($key);
  return $value;
 }
 function apcu_store($key,$value,$ttl=0){$GLOBALS['memory'][$key]=$value;return true;}
 function apcu_add($key,$value,$ttl=0){
  if(array_key_exists($key,$GLOBALS['memory']))return false;
  $GLOBALS['memory'][$key]=$value;
  if(is_callable($GLOBALS['after_add']))($GLOBALS['after_add'])($key);
  return true;
 }
 function apcu_cas($key,$old,$new){if(($GLOBALS['memory'][$key]??null)!==$old)return false;$GLOBALS['memory'][$key]=$new;return true;}
 function apcu_delete($key){$GLOBALS['deleted'][]=$key;unset($GLOBALS['memory'][$key]);return true;}
}
require 'data/config.php';require 'scraper/google_cse.php';
$n=0;function ok($yes,$label){global $n;$n++;if(!$yes)throw new RuntimeException($label);}
$g=new google_cse();$method=(new ReflectionClass($g))->getMethod('generate_token');
$proxy='raw_ip::::';$key='g.cse.bootstrap.'.hash('sha256',config::GOOGLE_CX_ENDPOINT."\0".$proxy);
$old=['token'=>'rejected:token','lib'=>'fixture-lib','_flight'=>11];$fresh=['token'=>'renewed:token','lib'=>'fixture-lib','_flight'=>12];
$memory=[$key=>$fresh];$out=$method->invoke($g,$proxy,true,$old['token']);
ok($out['token']===$fresh['token']&&$out['cached']===true,'Reuse already renewed token');ok($deleted===[],'No cache deletion on reuse');
// Another request completes between first read and successful lock acquisition.
$memory=[$key=>$old];$deleted=[];$after_add=function($lock)use($key,$fresh){if($lock===$key.'.lock')$GLOBALS['memory'][$key]=$fresh;};
$out=$method->invoke($g,$proxy,true,$old['token']);
ok($out['token']===$fresh['token']&&$out['cached']===true,'Recheck token after lock acquisition');
ok(($memory[$key]??null)===$fresh,'New successful generation not deleted');ok(!isset($memory[$key.'.lock']),'Owned lock released');ok(!in_array($key,$deleted,true),'Bootstrap never discarded');
// Waiter sees the same flight id but different valid token: compare rejected token, not generation bookkeeping.
$after_add=null;$reads=0;$fresh['_flight']=$old['_flight'];$memory=[$key=>$old,$key.'.lock'=>99];
$after_fetch=function($f)use($key,$fresh,&$reads){if($f===$key && ++$reads===1)$GLOBALS['memory'][$key]=$fresh;};
$out=$method->invoke($g,$proxy,true,$old['token']);
ok($out['token']===$fresh['token'],'Waiter reuses replacement even when flight id is unchanged');ok($memory[$key.'.lock']===99,'Foreign lock preserved');
// A stale worker cannot release a replacement owner\'s lock.
$after_fetch=null;$lock=(new ReflectionClass($g))->getMethod('release_bootstrap_lock');$lock->invoke($g,$key.'.lock',88);
ok($memory[$key.'.lock']===99,'Compare-and-swap preserves changed owner');
echo "PASS: $n cache-race assertions using isolated APCu doubles, no live request.\n";
