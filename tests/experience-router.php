<?php
// LOCAL TEST ROUTER ONLY: fixed bundled data, no provider transport, no production endpoint.
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
chdir(dirname(__DIR__));
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('#\A/(?:static/|banner/|favicon\.)#',$path))return false;
if($path==='/'){$_SERVER['SCRIPT_NAME']='/index.php';require 'index.php';return true;}
if($path==='/settings'){$_SERVER['SCRIPT_NAME']='/settings.php';require 'settings.php';return true;}
if($path==='/proxy'){
    // Actual local animation bytes instead of the real provider/proxy network.
    if(($_GET['s']??'')==='animated'){header('Content-Type: image/gif');readfile('tests/fixtures/motion/two.gif');}
    else {header('Content-Type: image/webp');readfile('static/theme-previews/Art.webp');}return true;
}
if($path!=='/fixture-images'){http_response_code(404);return true;}
$_SERVER['SCRIPT_NAME']='/images.php';define('SECURITYSEARCH_IMAGE_ENHANCEMENT',true);
require 'lib/security_headers.php';require 'data/config.php';require 'lib/frontend.php';require 'lib/image_results.php';
$f=new frontend();$get=['s'=>'Local animated-preview fixture','scraper'=>'binternet','view'=>'grid','quality'=>'preview','format'=>'gif','nsfw'=>'yes'];
$filters=['scraper'=>['display'=>'Scraper','option'=>['binternet'=>'Pinterest via Binternet']],'quality'=>['display'=>'Quality','option'=>['preview'=>'Fast preview','high'=>'High quality']]];
$f->loadheader($get,$filters,'images');
$items=[];for($i=0;$i<8;$i++)$items[]=['title'=>'Offline animated preview '.($i+1),'url'=>'https://example.invalid/fixture','source'=>[['url'=>'https://example.invalid/two.gif?id='.$i,'width'=>360,'height'=>240]]];
[$html,$count]=image_results::render($f,$get,['image'=>$items]);
echo $f->load('images.html',['images'=>$html,'image_classes'=>' class="images-view-grid images-quality-preview"','image_view_help'=>'<p class="image-view-note">Local renderer fixture. Bundled animation, no search provider call.</p>','image_script'=>'<script defer src="/static/images-motion.js?v26"></script>','timetaken'=>null,'nextpage'=>'']);return true;
