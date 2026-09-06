<?php
// Network-free transport invariants; invoke with php -d apc.enable_cli=1.
require 'data/config.php';
require 'scraper/google_cse.php';
function ensure($condition, $label){ if(!$condition){ throw new RuntimeException($label); } }
function rejected($fn, $label){
    try { $fn(); } catch(Exception $error){ return; }
    throw new RuntimeException($label);
}
$google = new google_cse('google');
$alias = new google_cse('google_cse');
$r = new ReflectionClass($google);
$url = $r->getMethod('validate_request_url');
foreach(['https://cse.google.com/cse', 'https://cse.google.com:443/cse.js?cx=example'] as $safe){
    $url->invoke($google, $safe);
}
foreach(['http://cse.google.com/', 'https://cse.google.com@127.0.0.1/',
    'https://127.0.0.1/', 'https://cse.google.com.evil.test/',
    'https://cse.google.com:8443/', "https://cse.google.com/\r\nHost: bad",
    'https://cse.google.com/#fragment', ['url']] as $unsafe){
    rejected(fn()=>$url->invoke($google, $unsafe), 'Unsafe Google URL accepted');
}
$transport = $r->getMethod('transport_for');
$first = $transport->invoke($google, 'raw_ip::::');
curl_setopt($first, CURLOPT_URL, 'https://example.org/previous');
$same = $transport->invoke($google, 'raw_ip::::');
ensure($first === $same, 'Same search/egress must reuse transport');
ensure(curl_getinfo($same, CURLINFO_EFFECTIVE_URL) === '', 'Old options must be reset');
ensure($transport->invoke($alias, 'raw_ip::::') !== $same, 'Transport must not cross search instances');
ensure($transport->invoke($google, 'socks5h:proxy.invalid:9050::') !== $same, 'Transport must not cross egress');

$key = $r->getMethod('cse_request_cooldown_key');
$cooldown = $key->invoke($google, 'raw_ip::::');
ensure($key->invoke($alias, 'raw_ip::::') === $cooldown, 'CSE aliases must share same-egress cooldown');
ensure($key->invoke($alias, 'socks5h:proxy.invalid:9050::') !== $cooldown, 'Distinct egress must be isolated');
apcu_store($cooldown, true, 30);
$started = hrtime(true);
rejected(fn()=>$r->getMethod('generate_token')->invoke($alias, 'raw_ip::::'), 'Bootstrap ignored provider cooldown');
ensure(hrtime(true)-$started < 100000000, 'Cooldown should not access network');
apcu_delete($cooldown);

// A contended bootstrap must honor the overall deadline, not its wait-loop count.
$proxy = 'socks5h:deadline.invalid:9050::';
$cache = 'g.cse.bootstrap.'.hash('sha256', config::GOOGLE_CX_ENDPOINT."\0".$proxy);
apcu_store($cache.'.lock', 123, 60);
$r->getProperty('request_deadline')->setValue($google, hrtime(true)-1);
$started = hrtime(true);
rejected(fn()=>$r->getMethod('generate_token')->invoke($google, $proxy), 'Expired waiter accepted');
ensure(hrtime(true)-$started < 100000000, 'Expired waiter exceeded deadline');
apcu_delete($cache.'.lock');
echo "PASS: Google URL allowlist, per-egress connection reuse, option reset, alias cooldown and waiter deadline.\n";
