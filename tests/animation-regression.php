<?php
require 'data/config.php';require 'lib/frontend.php';require 'lib/image_results.php';require 'lib/image_poster.php';
function verify($value,$label){if(!$value)throw new RuntimeException($label);}
foreach(glob('tests/fixtures/motion/two.*') as $file){
 $body=file_get_contents($file);$inspection=animated_preview_inspect_raster($body);
 verify($inspection['frames']===2 && $inspection['width']===4 && $inspection['height']===4,'Valid two-frame container');
 $poster=image_poster_body($body);$image=new Imagick();$limit=Imagick::getResourceLimit(Imagick::RESOURCETYPE_LISTLENGTH);
 try { Imagick::setResourceLimit(Imagick::RESOURCETYPE_LISTLENGTH,2);$image->readImageBlob($poster);verify($image->getNumberImages()===1 && $image->getImageWidth()===4,'Poster decodes exactly one frame under strict policy'); }
 finally {$image->clear();Imagick::setResourceLimit(Imagick::RESOURCETYPE_LISTLENGTH,$limit);}
 foreach([substr($body,0,20),substr($body,0,-9),'garbage'] as $bad){try{animated_preview_inspect_raster($bad);throw new LogicException('Malformed animation accepted');}catch(UnexpectedValueException $e){}}
}
$static=file_get_contents('tests/fixtures/motion/static.webp');verify(image_poster_body($static)===$static,'Static WebP stays on ordinary bounded decoder path');
$f=new frontend();
foreach(['gif','webp','apng'] as $format){
 $image=['title'=>'Example','url'=>'https://example.org/page','source'=>[['url'=>'https://example.org/opaque','width'=>400,'height'=>300],['url'=>'https://example.org/thumb.jpg','width'=>200,'height'=>150]],'motion_format'=>$format];
 foreach(['preview','high','original'] as $quality){
  $item=image_results::items($f,['quality'=>$quality],['image'=>[$image]])[0];
  verify(str_contains($item['motion'],'s=animated&preview=1') && str_contains($item['preview'],'s=poster'),'Opaque provider metadata and every quality select scoped motion plus static poster');
 }
}
$image['motion_format']='avif';$item=image_results::items($f,[],['image'=>[$image]])[0];verify($item['motion']===null,'Unsupported animated containers do not enter raster autoplay');
echo "PASS: actual GIF/WebP/APNG frames, strict static poster extraction, malformed/static inputs and renderer motion contracts.\n";
