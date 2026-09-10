<?php
require 'data/config.php';require 'lib/redlib_selection.php';
$n=0;function check_primary($ok,$message){global $n;$n++;if(!$ok)throw new RuntimeException($message);}
check_primary(!in_array('https://libre.securityops.co',service_pool::allowed(),true),'Old failed service removed');
check_primary(service_pool::primary()==='https://redlib.privacyredirect.com','External default');
check_primary(count(service_pool::allowed())===3,'Bounded inventory');
$seen=[];$report=redlib_selection::choose(function($host,$kind)use(&$seen){$seen[]=[$host,$kind];return 5;});
check_primary($report['status']==='ok' && count($seen)===2,'One good origin only; both modes checked');
check_primary($report['origin']===service_pool::primary(),'Use tested primary');
$seen=[];$origins=service_pool::allowed();
$report=redlib_selection::choose(function($host,$kind)use(&$seen,$origins){$seen[]=[$host,$kind];if($host===$origins[0] && $kind==='search')throw new RuntimeException('Search blocked');return 3;});
check_primary($report['origin']===$origins[1] && count($seen)===4,'Healthy home/feed is insufficient: search must work');
check_primary($report['attempts'][0]['feed_count']===3 && $report['attempts'][0]['search_count']===0,'Failure evidence retained');
foreach([0,-1,'5',true,26,[]]as$bad){$seen=[];$r=redlib_selection::choose(function($o,$k)use(&$seen,$bad){$seen[]=$k;return $bad;});check_primary($r['status']==='unavailable'&&count($seen)===3,'Empty/malformed count cannot enable deployment');}
$report=redlib_selection::choose(function(){throw new RuntimeException('secret-upstream-body');});
check_primary(!str_contains(json_encode($report),'secret-upstream-body'),'No upstream content logged');
check_primary(str_contains(service_pool::disclosure(),parse_url(service_pool::primary(),PHP_URL_HOST)),'Disclosure matches primary');
check_primary(!str_contains(service_pool::disclosure(),'libre.securityops.co'),'No obsolete primary claim');
foreach(service_pool::allowed()as$origin) {
    $code='class config {const REDLIB_PRIMARY='.var_export($origin,true).';const REDLIB_FALLBACKS=false;} require "lib/service_pool.php"; echo json_encode(service_pool::origins());';
    $pipes=[];$child=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($child);
    check_primary($exit===0&&json_decode($out,true)===[$origin],'Explicit primary respected with failover disabled');
}
$code='class config {const REDLIB_PRIMARY="http://127.0.0.1";} require "lib/service_pool.php"; try {service_pool::origins();exit(9);} catch(RuntimeException $e){exit(0);}';
$child=proc_open([PHP_BINARY,'-r',$code],[1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);check_primary(proc_close($child)===0,'Unapproved config rejected before request');
foreach(['home.html','header.html']as$template)check_primary(!str_contains(file_get_contents('template/'.$template),'libre.securityops.co'),'Retired service not linked or labelled in active templates');
echo "PASS: $n primary/selection assertions, using offline callbacks, not live availability.\n";
