<?php
// Explicit transport doubles. Native cURL functions must be disabled for this child.
$targets=['curl_init','curl_reset','curl_setopt','curl_exec','curl_errno','curl_getinfo','curl_close'];
foreach($targets as $f)if(function_exists($f)){fwrite(STDERR,"Use the isolated command in scripts/test.sh.\n");exit(2);}
// Missing extension constants are fixture option identifiers, not a native-extension emulation.
$names=['CURLOPT_URL','CURLOPT_HTTPHEADER','CURLOPT_HTTP_VERSION','CURL_HTTP_VERSION_2_0','CURLOPT_ENCODING','CURLOPT_RETURNTRANSFER','CURLOPT_SSL_VERIFYHOST','CURLOPT_SSL_VERIFYPEER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_NOPROXY','CURLOPT_PROXYUSERPWD','CURLOPT_PROXY','CURLOPT_PROTOCOLS','CURLPROTO_HTTPS','CURLOPT_FOLLOWLOCATION','CURLOPT_WRITEFUNCTION','CURLOPT_HEADERFUNCTION','CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS','CURLINFO_RESPONSE_CODE'];
foreach($names as $i=>$name)if(!defined($name))define($name,9000+$i);
if(!function_exists('curl_init')){
 function curl_init(){return(object)['options'=>[],'row'=>[]];}
 function curl_reset($h){$h->options=[];}
 function curl_setopt($h,$key,$value){$h->options[$key]=$value;return true;}
 function curl_errno($h){return $h->row['errno']??0;}
 function curl_getinfo($h,$key){return $h->row['status']??200;}
 function curl_close($h){}
 function curl_exec($h){
  $GLOBALS['calls']++;$h->row=array_shift($GLOBALS['rows'])??['status'=>200,'body'=>'<html>normal</html>'];
  $GLOBALS['last_options']=$h->options;$GLOBALS['requested'][]=$h->options[CURLOPT_URL];
  $header=$h->options[CURLOPT_HEADERFUNCTION];$header($h,'HTTP/2 '.($h->row['status']??200)."\r\n");
  foreach(($h->row['headers']??[])as $k=>$v)$header($h,$k.': '.$v."\r\n");
  $write=$h->options[CURLOPT_WRITEFUNCTION];$data=$h->row['body']??'<html>normal</html>';$write($h,$data);return true;
 }
}
