<?php
// Network-free exercise of the actual transport. Disable the four functions below.
if (function_exists('curl_exec')) { fwrite(STDERR,"Run via scripts/test.sh.\n");exit(2); }
if (!function_exists('curl_exec')) {
function curl_setopt($handle,$key,$value) { $GLOBALS['options'][$key]=$value;return true; }
function curl_errno($handle) { return $GLOBALS['errno']; }
function curl_getinfo($handle,$key) { return $GLOBALS['status']; }
function curl_exec($handle) {
    $GLOBALS['calls']++;
    $write=$GLOBALS['options'][CURLOPT_WRITEFUNCTION];
    foreach ($GLOBALS['chunks'] as $chunk) if ($write($handle,$chunk)!==strlen($chunk)) return false;
    return true;
}
}
require 'data/config.php';require 'scraper/brave.php';
function check($test,$label) { if (!$test) throw new RuntimeException($label); }
$method=(new ReflectionClass('brave'))->getMethod('get');
$invoke=fn($provider)=>$method->invoke($provider,'raw_ip::::','https://search.brave.com/search',['q'=>'GNU Guix'],'no','all');
$calls=0;$errno=0;$status=200;$chunks=['<html>','neutral','</html>'];$options=[];
$provider=new brave();$provider->set_request_deadline(hrtime(true)+8000000000);
check($invoke($provider)==='<html>neutral</html>','Successful body assembly');
$first=$options[CURLOPT_TIMEOUT_MS];
check($first>0 && $first<=8000 && $options[CURLOPT_CONNECTTIMEOUT_MS]<=5000,'Fallback deadline bounds transport');
check($options[CURLOPT_PROTOCOLS]===CURLPROTO_HTTPS && $options[CURLOPT_FOLLOWLOCATION]===false && $options[CURLOPT_SSL_VERIFYPEER]===true,'HTTPS verification and no redirects');
usleep(2000);$invoke($provider);
check($options[CURLOPT_TIMEOUT_MS]<$first,'Successive calls share a shrinking absolute deadline');
$chunks=[str_repeat('x',4194304)];check(strlen($invoke($provider))===4194304,'Exact body bound accepted');
$chunks[]='x';
try{$invoke($provider);throw new LogicException('Oversized body accepted');}
catch(RuntimeException $error){check(str_contains($error->getMessage(),'size limit'),'Decoded limit rejects first excess byte');}
$chunks=['neutral'];$status=429;
try{$invoke($provider);throw new LogicException('HTTP failure accepted');}
catch(RuntimeException $error){check(str_contains($error->getMessage(),'unavailable'),'Provider status rejected');}
apcu_delete(search_health::key('brave','raw_ip::::')); // isolate the next transport case
$status=200;$errno=28;
try{$invoke($provider);throw new LogicException('Transport failure accepted');}
catch(RuntimeException $error){check(str_contains($error->getMessage(),'transport'),'Transport failure rejected');}
apcu_delete(search_health::key('brave','raw_ip::::')); // deadline case must reach the deadline guard
$errno=0;$provider->set_request_deadline(hrtime(true)-1);$before=$calls;
try{$invoke($provider);throw new LogicException('Expired request executed');}
catch(RuntimeException $error){check(str_contains($error->getMessage(),'budget'),'Expired budget rejected');}
check($calls===$before,'No curl_exec after deadline expiry');
echo "PASS: Brave body assembly, decoded 4 MiB bound, HTTPS/redirect policy, HTTP/transport failure, shared shrinking timeout and no expired network call.\n";
