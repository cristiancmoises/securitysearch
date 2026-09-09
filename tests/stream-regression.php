<?php
// Run with -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_error
if (function_exists('curl_exec')) { fwrite(STDERR,"Disable the four curl functions as documented.\n");exit(2); }
if (!function_exists('curl_exec')) {
function curl_setopt($handle,$key,$value) { $GLOBALS['options'][$key]=$value;return true; }
function curl_errno($handle) { return 0; }
function curl_error($handle) { return ''; }
function curl_exec($handle) {
 $head=$GLOBALS['options'][CURLOPT_HEADERFUNCTION];
 foreach (['HTTP/1.1 200 OK'."\r\n",'Content-Type: image/png'."\r\n"] as $line) { if (!$head($handle,$line)) { return false; } }
 if ($GLOBALS['length_header'] && !$head($handle,'Content-Length: 17'."\r\n")) { return false; }
 if (!$head($handle,"\r\n")) { return false; }
 $write=$GLOBALS['options'][CURLOPT_WRITEFUNCTION];
 foreach ([str_repeat('x',12),str_repeat('y',12)] as $chunk) { if (!$write($handle,$chunk)) { return false; } }
 return true;
}
}
require 'data/config.php';require 'lib/curlproxy.php';
$stream=(new ReflectionClass('proxy'))->getMethod('stream');
foreach ([true,false] as $length) {
 $GLOBALS['length_header']=$length;$budget=(object)['remaining'=>16];$failed=false;ob_start();
 try { $stream->invoke(new proxy(false),'https://8.8.8.8/test.png',null,'image',0,null,$budget); }
 catch(Exception $e) { $failed=str_contains($e->getMessage(),'byte limit'); }
 $body=ob_get_clean();
 if (!$failed || strlen($body)!==($length ? 0 : 12)) { throw new RuntimeException('Stream exceeded its body budget'); }
}
echo "PASS: oversized Content-Length rejected before output; chunked stream stops at shared decoded-byte budget.\n";
