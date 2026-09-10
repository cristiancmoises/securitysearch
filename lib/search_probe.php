<?php
/** Explicit CLI diagnostic. Emits no queries, URLs, result content or credentials. */
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
chdir(dirname(__DIR__));
require_once 'data/config.php';require_once 'lib/search_health.php';
$source=$argv[1]??'';$page=$argv[2]??'';
if(!in_array($source,['google','brave'],true)||!in_array($page,['web','images'],true)){
    fwrite(STDERR,"Usage: php lib/search_probe.php {google|brave} {web|images}\n");exit(2);
}
$started=hrtime(true);$report=['schema'=>1,'version'=>trim(file_get_contents('data/release-version.txt')),
    'provider'=>$source,'page'=>$page,'status'=>'unavailable','result_count'=>null,'query'=>'fixed-neutral-probe'];
try {
    require_once 'lib/frontend.php';$f=new frontend();
    $_GET=['scraper'=>$source,'s'=>'GNU Guix'];
    [$provider,$filters]=$f->getscraperfilters($page);
    $get=$f->parsegetfilters($_GET,$filters);
    if(method_exists($provider,'set_request_deadline'))$provider->set_request_deadline(hrtime(true)+12000000000);
    $method=$page==='images'?'image':'web';$result=$provider->$method($get);
    $key=$page==='images'?'image':'web';$rows=$result[$key]??null;
    if(!is_array($rows))throw new upstream_search_failure($source,'format');
    $report['result_count']=count($rows);$report['status']=$rows===[]?'empty':'ok';
} catch(upstream_search_failure $error) {$report['failure']=$error->diagnostic();}
catch(Throwable $error) {$report['failure']=['reason'=>'unclassified','error_class'=>get_class($error)];}
$report['trace']=search_health::trace();$report['milliseconds']=round((hrtime(true)-$started)/1000000,1);
echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
exit(in_array($report['status'],['ok','empty'],true)?0:1);
