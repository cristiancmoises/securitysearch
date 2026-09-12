<?php
/** Core PHP filesystem and validator checks: no transport/APCu/Imagick doubles. */
require __DIR__.'/../lib/favicon_cache.php';
$checks=0;
function verify_icon($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
$bytes=file_get_contents(__DIR__.'/../lib/favicon404.png');$tag=favicon_cache::etag($bytes);
verify_icon((bool)preg_match('/\AW\/"ss-icon-[0-9a-f]{64}"\z/',$tag),'content-derived weak tag');
verify_icon($tag===favicon_cache::etag($bytes),'deterministic');
verify_icon($tag!==favicon_cache::etag($bytes.'x'),'changed bytes change tag');
foreach ([$tag,substr($tag,2),'*',' '.$tag."\t",'"other", '.$tag,'W/"comma,inside", '.substr($tag,2)] as $field)
 verify_icon(favicon_cache::matches($field,$tag),'valid list weak comparison');
foreach (['',' ', '"other"',$tag.',garbage',$tag.',','*, '.$tag,'w/'.substr($tag,2),$tag."\r\nX: bad",'"unterminated',str_repeat('x',4097),$tag.', '.str_repeat('"no", ',65).'"no"'] as $field)
 verify_icon(!favicon_cache::matches($field,$tag),'invalid/full-header/bounded parse');
foreach (['GET','HEAD'] as $method)
 verify_icon(favicon_cache::not_modified(['REQUEST_METHOD'=>$method,'HTTP_IF_NONE_MATCH'=>$tag],$tag),'safe method');
foreach (['POST','PUT','DELETE','OPTIONS'] as $method)
 verify_icon(!favicon_cache::not_modified(['REQUEST_METHOD'=>$method,'HTTP_IF_NONE_MATCH'=>'*'],$tag),'unsafe/other method not 304');
foreach (['HTTP_IF_MATCH','HTTP_IF_UNMODIFIED_SINCE','HTTP_RANGE','HTTP_IF_RANGE'] as $key)
 verify_icon(!favicon_cache::not_modified(['HTTP_IF_NONE_MATCH'=>$tag,$key=>'fixture'],$tag),'other preconditions retain original handling');
verify_icon(!favicon_cache::not_modified(['HTTP_IF_NONE_MATCH'=>[$tag]],$tag),'non-string header');
$root=sys_get_temp_dir().'/ss-favicon-'.bin2hex(random_bytes(8));mkdir($root,0700);$dir=$root.'/icons';mkdir($dir,0700);$path=$dir.'/fixture.invalid.png';
try {
 verify_icon(favicon_cache::read($dir,'fixture.invalid')===null,'missing is cache miss');
 file_put_contents($path,$bytes);
 verify_icon(favicon_cache::read($dir,'fixture.invalid')===$bytes,'valid small png bytes preserved');
 foreach (['../secret','/etc/passwd','x/y',"x\0y",str_repeat('a',254)] as $host)
  verify_icon(favicon_cache::read($dir,$host)===null,'host cannot select paths');
 unlink($path);file_put_contents($root.'/outside.png',$bytes);symlink($root.'/outside.png',$path);
 verify_icon(favicon_cache::read($dir,'fixture.invalid')===null,'symlink rejected');unlink($path);
 link($root.'/outside.png',$path);verify_icon(favicon_cache::read($dir,'fixture.invalid')===null,'hardlink rejected');unlink($path);
 mkdir($path);verify_icon(favicon_cache::read($dir,'fixture.invalid')===null,'directory rejected');rmdir($path);
 if(function_exists('posix_mkfifo')){posix_mkfifo($path,0600);verify_icon(favicon_cache::read($dir,'fixture.invalid')===null,'FIFO rejected without blocking');unlink($path);}
 foreach (['','not a PNG',substr($bytes,0,16),str_repeat('x',favicon_cache::MAX_BYTES+1)] as $bad){
  file_put_contents($path,$bad);verify_icon(favicon_cache::read($dir,'fixture.invalid')===null,'invalid/oversize cache is miss');unlink($path);
 }
 file_put_contents($path,$bytes);symlink($dir,$root.'/linked');
 verify_icon(favicon_cache::read($root.'/linked','fixture.invalid')===null,'symlink directory rejected');unlink($root.'/linked');
} finally {
 foreach (glob($dir.'/*')?:[] as $p){if(is_dir($p)&&!is_link($p))rmdir($p);else unlink($p);}
 rmdir($dir);unlink($root.'/outside.png');rmdir($root);
}
echo "PASS: $checks native favicon cache/HTTP-validator assertions; no network request.\n";
