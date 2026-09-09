<?php
// No upstream requests: measures PHP parsing and rendering only.
require 'data/config.php';require 'lib/frontend.php';require 'scraper/invidious.php';require 'scraper/binternet.php';
$video=new invidious();$bin=new binternet();$f=new frontend();
$videos=[];$images='<div class="img-container">';
for($i=0;$i<50;$i++) {
 $videos[]=['type'=>'video','videoId'=>'jRp4D9Xa4ek','title'=>'GNU Guix tutorial '.$i,'description'=>str_repeat('Example description. ',20)];
 $images.='<a class="img-result" href="/image_proxy.php?url='.rawurlencode('https://i.pinimg.com/originals/'.$i.'.webp').'"><img alt="Fixture"></a>';
}
$images.='</div>';$json=json_encode($videos);$html=$f->load('home.html');
$jobs=[
 'render_home'=>fn()=>$f->load('home.html'),
 'parse_50_invidious_videos'=>fn()=>$video->decode($json),
 'parse_50_binternet_images'=>fn()=>$bin->decode($images),
];
foreach($jobs as $name=>$job) {
 $samples=[];for($i=0;$i<1000;$i++) { $t=hrtime(true);$job();$samples[]=(hrtime(true)-$t)/1e6; }
 sort($samples);echo json_encode(['test'=>$name,'iterations'=>count($samples),'median_ms'=>round($samples[499],4),'p95_ms'=>round($samples[949],4),'p99_ms'=>round($samples[989],4),'peak_php_bytes'=>memory_get_peak_usage(true)])."\n";
}
