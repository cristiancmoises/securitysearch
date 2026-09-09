<?php
// Offline doubles only. Keep declarations conditional so plain `php -l` is
// valid with ext-curl loaded; the runtime guard must run before any test work.
// Run through scripts/test.sh, which disables these four functions for this
// test process only. Never change php.ini or the production cURL configuration.
$mockedFunctions = ['curl_setopt', 'curl_exec', 'curl_share_init', 'curl_share_setopt'];
foreach ($mockedFunctions as $function) {
    if (function_exists($function)) {
        fwrite(STDERR, 'Offline cURL mocks are not isolated: ' . $function . " is still defined.\n"
            . 'Run: php -d disable_functions=' . implode(',', $mockedFunctions)
            . " tests/provider-http-regression.php\n");
        exit(2);
    }
}
if (!function_exists('curl_setopt')) {
    function curl_setopt($h, $key, $value) { global $ops; $ops[$key] = $value; return true; }
}
if (!function_exists('curl_exec')) {
    function curl_exec($h) { global $calls; $calls++; return 'unchanged response'; }
}
if (!function_exists('curl_share_init')) {
    function curl_share_init() { return (object)['fixture' => true]; }
}
if (!function_exists('curl_share_setopt')) {
    function curl_share_setopt($h, $key, $value) { global $shares; $shares[] = $value; return true; }
}
require 'data/config.php'; require 'lib/provider_http.php';
foreach (['CURLOPT_CONNECTTIMEOUT_MS','CURLOPT_TIMEOUT_MS','CURLOPT_NOSIGNAL','CURLOPT_TCP_KEEPALIVE','CURLOPT_SHARE','CURLSHOPT_SHARE','CURL_LOCK_DATA_DNS','CURL_LOCK_DATA_SSL_SESSION'] as $i=>$key) { if (!defined($key)) define($key,20000+$i); }
$ops=[];$shares=[];$calls=0;
function check($v,$label){if(!$v)throw new RuntimeException($label);}
$h=(object)[];
check(provider_http::exec($h)==='unchanged response','Return contract preserved');
check($ops[CURLOPT_CONNECTTIMEOUT_MS]===3000 && $ops[CURLOPT_TIMEOUT_MS]<=12000,'Configured connect/hop caps');
check($shares===[CURL_LOCK_DATA_DNS,CURL_LOCK_DATA_SSL_SESSION],'Only DNS and TLS sessions shared');
$first=$ops[CURLOPT_SHARE];provider_http::exec($h);
check($first===$ops[CURLOPT_SHARE] && count($shares)===2,'Share reused within request');
$prop=(new ReflectionClass('provider_http'))->getProperty('until');
$prop->setValue(null,hrtime(true)+450000000);provider_http::exec($h);
check($ops[CURLOPT_TIMEOUT_MS]<=450 && $ops[CURLOPT_CONNECTTIMEOUT_MS]<=450,'Remaining request budget shrinks both caps');
$prop->setValue(null,hrtime(true)-1);$before=$calls;
try{provider_http::exec($h);throw new LogicException('Expired budget reached curl');}catch(RuntimeException $e){}
check($calls===$before,'Expired budget makes no network call');
foreach (glob('scraper/*.php') as $file) {
 $s=file_get_contents($file);
 if (in_array(basename($file),['brave.php','google_cse.php'],true)) continue;
 check(!str_contains($s,'curl_exec('),'Legacy curl_exec bypass: '.$file);
}
check(str_contains(file_get_contents('scraper/baidu.php'),'curl_multi_select($this->proc, 0.1)'),'Baidu waits instead of CPU spinning');
echo "PASS: legacy transport caps, total deadline, request-local DNS/TLS state, no cookie/result cache, raw return contract and every legacy call site.\n";
