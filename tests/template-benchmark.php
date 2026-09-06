<?php
// Synthetic local rendering microbenchmark, not a provider/network benchmark.
require 'data/config.php'; require 'lib/frontend.php';
if(!function_exists('apcu_enabled') || !apcu_enabled()){
    fwrite(STDERR,"Enable APCu CLI for this benchmark.\n"); exit(2);
}
$method=(new ReflectionClass('frontend'))->getMethod('template_source');
foreach(['header.html','home.html','images.html'] as $template){
    $path=dirname(__DIR__).'/template/'.$template;
    $legacy=fn()=>implode('',array_map('trim',explode("\n",file_get_contents($path))));
    if($legacy()!==$method->invoke(null,$template)){throw new RuntimeException('Template bytes changed');}
    $iterations=1000;$start=hrtime(true);
    for($i=0;$i<$iterations;$i++){$legacy();}
    $uncached=(hrtime(true)-$start)/1e6;$start=hrtime(true);
    for($i=0;$i<$iterations;$i++){$method->invoke(null,$template);}
    $cached=(hrtime(true)-$start)/1e6;
    echo json_encode(['template'=>$template,'iterations'=>$iterations,'uncached_ms'=>round($uncached,3),'warm_cache_ms'=>round($cached,3),'identical_bytes'=>true])."\n";
}
