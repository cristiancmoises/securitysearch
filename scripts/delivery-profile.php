<?php
/** Read-only, fixed localhost delivery observations; run only through the operator CLI. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('curl_init')) { fwrite(STDERR,"PHP cURL required.\n"); exit(2); }
$rows=[];
for ($i=0;$i<3;$i++) {
    $body='';$headers=[];$handle=curl_init('http://127.0.0.1/');
    curl_setopt_array($handle,[CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTP,
        CURLOPT_CONNECTTIMEOUT_MS=>1000,CURLOPT_TIMEOUT_MS=>5000,CURLOPT_ENCODING=>'gzip',
        CURLOPT_HTTPHEADER=>['Host: securityops.co','User-Agent: SecuritySearch-DeliveryProfile/1.0','Connection: close'],
        CURLOPT_HEADERFUNCTION=>static function($ch,$line) use(&$headers){
            if (strlen($line)<=2048 && str_contains($line,':')) {
                [$key,$value]=explode(':',$line,2);$key=strtolower(trim($key));
                if(in_array($key,['content-type','content-encoding','cache-control','vary','server-timing','x-securitysearch-render'],true))
                    $headers[$key]=substr(preg_replace('/[\x00-\x1f\x7f]/',' ',trim($value)),0,300);
            } return strlen($line);
        },
        CURLOPT_WRITEFUNCTION=>static function($ch,$chunk) use(&$body){
            if(strlen($body)+strlen($chunk)>131072)return 0;$body.=$chunk;return strlen($chunk);
        }]);
    $ok=curl_exec($handle);$info=curl_getinfo($handle);$errno=curl_errno($handle);curl_close($handle);
    $match=[];$asset=null;
    if(preg_match('~/banner/securitysearch\.webp\?v([0-9]{1,4})~',$body,$match))$asset=(int)$match[1];
    $static=($headers['x-securitysearch-render']??'')==='static-home';
    $rows[]=['sample'=>$i+1,'status'=>$ok!==false && ($info['http_code']??0)===200 && $asset===33 && $static?'ok':'unavailable',
        'http_status'=>(int)($info['http_code']??0),'curl_errno'=>$errno,'asset_version'=>$asset,
        'ttfb_ms'=>round(($info['starttransfer_time']??0)*1000,3),'total_ms'=>round(($info['total_time']??0)*1000,3),
        'wire_body_bytes'=>(int)($info['size_download']??0),'decoded_body_bytes'=>strlen($body),'headers'=>$headers];
}
// No bodies, request cookies, process environment or upstream search requests.
echo json_encode(['schema'=>1,'scope'=>'Three fresh HTTP localhost connections inside the application container; no DNS/TLS/NPM/public-network or provider-search comparison.','samples'=>$rows],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
exit(count(array_filter($rows,static fn($row)=>$row['status']==='ok'))===3?0:2);
