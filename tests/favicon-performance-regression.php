<?php
/** Favicon admission/cache policy tests. No external request is performed. */
$cache=[];
if(!function_exists('apcu_enabled')){function apcu_enabled(){return true;}}
if(!function_exists('apcu_fetch')){function apcu_fetch($key,&$success=null){global $cache;$success=array_key_exists($key,$cache);return $success?$cache[$key]:false;}}
if(!function_exists('apcu_add')){function apcu_add($key,$value,$ttl=0){global $cache;if(array_key_exists($key,$cache))return false;$cache[$key]=$value;return true;}}
if(!function_exists('apcu_store')){function apcu_store($key,$value,$ttl=0){global $cache;$cache[$key]=$value;return true;}}
if(!function_exists('apcu_delete')){function apcu_delete($key){global $cache;if(!array_key_exists($key,$cache))return false;unset($cache[$key]);return true;}}
require __DIR__.'/../lib/favicon_policy.php';
$n=0;
function favcheck($ok,$label){global $n,$cache;$n++;if(!$ok)throw new RuntimeException($label.' cache='.json_encode(array_keys($cache)));}
favcheck(favicon_policy::REMOTE_BUDGET_NS===2500000000,'remote budget changed');
favcheck(favicon_policy::MAX_INFLIGHT===4,'slot limit changed');
favcheck(favicon_policy::SUCCESS_BROWSER_TTL===86400 && favicon_policy::FAILURE_BROWSER_TTL===300,'browser TTL changed');

$leases=[];
foreach(['a.example','b.example','c.example','d.example'] as $host){
    $lease=favicon_policy::acquire($host);favcheck(is_array($lease),'slot unexpectedly unavailable');$leases[]=$lease;
}
favcheck(favicon_policy::acquire('e.example')===null,'fifth concurrent remote favicon admitted');
favicon_policy::release($leases[0]);
$e=favicon_policy::acquire('e.example');favcheck(is_array($e),'released slot not reusable');

// Per-host lease prevents duplicate cold favicon work even when a global slot exists.
favicon_policy::release($leases[1]);
$first=favicon_policy::acquire('same.example');favcheck(is_array($first),'first per-host lease failed');
favcheck(favicon_policy::acquire('same.example')===null,'duplicate per-host fetch admitted');
// A forged owner must not delete the real lease.
$forged=$first;$forged['owner']='0000000000000000';favicon_policy::release($forged);
favcheck(favicon_policy::acquire('same.example')===null,'wrong owner released host lease');
favicon_policy::release($first);
$again=favicon_policy::acquire('same.example');favcheck(is_array($again),'real owner failed to release lease');

favicon_policy::mark_failure('failed.example');favcheck(favicon_policy::is_negative('failed.example'),'negative cache not set');
favicon_policy::clear_failure('failed.example');favcheck(!favicon_policy::is_negative('failed.example'),'negative cache not cleared');

// Cache keys are hashes/slot numbers only; host names and searches never enter APCu.
foreach(array_keys($cache) as $key){
    favcheck(!str_contains($key,'example') && !str_contains($key,'search='),'plaintext host/query leaked into favicon APCu key');
}
foreach(array_merge($leases,[$e,$again]) as $lease)favicon_policy::release($lease);
echo "PASS: $n favicon admission, negative-cache and privacy assertions.\n";
