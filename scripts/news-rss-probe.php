<?php
// Explicit CLI live gate. Does not claim an RSS source is available until parsed.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
chdir('/var/www/html/4get');require 'data/config.php';require 'lib/news_selection.php';require 'scraper/newswire.php';
ob_start();
try {
    $adapter=new newswire();
    $report=news_selection::choose(static function($source,$kind,$market)use($adapter){
        (new ReflectionProperty('provider_http','until'))->setValue(null,null);
        $result=$adapter->probe($source,$kind==='feed'?'':'technology',$market);
        return count($result['news']);
    });
} catch(Throwable $e) {$report=['status'=>'unavailable','source'=>null]+news_failure::from($e)->report();}
ob_end_clean();echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
