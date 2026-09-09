<?php
/** CLI-only, bounded live probe. Never emits credentials, queries or result URLs. */
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
chdir('/var/www/html/4get');
require 'data/config.php'; require 'lib/frontend.php';
ini_set('display_errors','0'); ini_set('log_errors','0');
ob_start();
$start=hrtime(true); $provider=$argv[1] ?? ''; $page=$argv[2] ?? '';
$report=['provider'=>$provider,'page'=>$page,'status'=>'unavailable','first_count'=>0,'pages'=>[]];
try {
    if ($provider==='--inventory') {
        $inventory=[];$f=new frontend();
        foreach(['web','images','videos','news','music'] as $kind) {
            $_GET=[];$_COOKIE=[];[, $filters]=$f->getscraperfilters($kind);
            foreach(array_keys($filters['scraper']['option']) as $name) { $inventory[]=['provider'=>$name,'page'=>$kind]; }
        }
        ob_end_clean();echo json_encode($inventory,JSON_THROW_ON_ERROR);exit;
    }
    if (!preg_match('/\A[a-z][a-z0-9_]{0,39}\z/',$provider) || !in_array($page,['web','images','videos','news','music'],true)) {
        throw new InvalidArgumentException('Invalid probe arguments.');
    }
    $q=$argv[3] ?? 'teste';
    if (strlen($q)>160 || trim($q)==='') { throw new InvalidArgumentException('Invalid query.'); }
    $_COOKIE=[]; $_GET=['scraper'=>$provider,'s'=>$q];
    $f=new frontend(); [$adapter,$filters]=$f->getscraperfilters($page);
    if (get_class($adapter)!==$provider) { throw new RuntimeException('Provider is not enabled for this page.'); }
    $get=$f->parsegetfilters($_GET,$filters);
    $method=['images'=>'image','videos'=>'video'][$page] ?? $page;
    $field=['images'=>'image','videos'=>'video','music'=>'song'][$page] ?? $page;
    $limit=($argv[4] ?? '1')==='2' ? 2 : 1;
    for($number=1;$number<=$limit;$number++) {
        if ($number>1 && class_exists('provider_http')) {
            // CLI keeps APCu tokens alive. Simulate the *new* HTTP request's
            // transport budget for the next page; this is not production code.
            (new ReflectionProperty('provider_http','until'))->setValue(null,null);
        }
        $before=hrtime(true); $result=$adapter->$method($get);
        if (!is_array($result) || !is_array($result[$field] ?? null)) { throw new RuntimeException('Invalid provider response.'); }
        if ($number===1) { $report['first_count']=count($result[$field]); }
        $report['pages'][]=['number'=>$number,'count'=>count($result[$field]),'milliseconds'=>round((hrtime(true)-$before)/1e6,1),'has_next'=>!empty($result['npt'])];
        if (empty($result['npt'])) { break; }
        $get['npt']=$result['npt'];
    }
    $report['status']=$report['first_count']>0 ? 'ok' : 'empty';
} catch(Throwable $e) {
    // Do not print exception messages: legacy adapters can include upstream URLs.
    $report['error_class']=get_class($e);
}
$report['milliseconds']=round((hrtime(true)-$start)/1e6,1);
ob_end_clean();
echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
// A valid JSON failure report is intentional. The caller enforces its gate.
