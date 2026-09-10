<?php
require __DIR__.'/../lib/provider_dns.php';
$n=0;function check($ok,$label){global $n;if(!$ok)throw new RuntimeException($label);$n++;}
$now=1000;$store=[];$calls=0;$writes=0;
$clock=static function()use(&$now){return $now;};
$read=static function($key)use(&$store){return $store[$key]??false;};
$write=static function($key,$row,$ttl)use(&$store,&$writes){$store[$key]=$row;$writes++;};
$resolver=static function($host)use(&$calls){$calls++;return ['ips'=>['1.1.1.1','2606:4700:4700::1111'],'ttl'=>600];};
$lookup=static fn($host)=>provider_dns::lookup($host,$resolver,$read,$write,$clock);
for($i=0;$i<30;$i++)check(count($lookup('images.securityops.co'))===2,'public answer');
check($calls===1 && $writes===1,'30 repeated metadata reads resolve once');
check(reset($store)['until']===1015,'TTL capped at fifteen seconds');
$now=1015;$lookup('images.securityops.co');check($calls===2,'expiration resolves afresh');
$before=[$calls,$writes];$lookup('example.org');$lookup('example.org');check($calls===$before[0]+2&&$writes===$before[1],'arbitrary hosts not shared');
$bad=['127.0.0.1','10.0.0.1','192.168.1.1','169.254.169.254','0.0.0.0','224.0.0.1','::1','::ffff:127.0.0.1','fc00::1','ff00::1','64:ff9b::a00:1','2002:7f00:1::'];
foreach($bad as $ip)check(!provider_dns::public_ip($ip),'private or transition address '.$ip);
foreach($bad as $ip){$result=provider_dns::lookup('redlib.privacyredirect.com',fn()=>['ips'=>['1.1.1.1',$ip],'ttl'=>15],fn()=>false,$write,$clock);check($result===[],'mixed answer refused');}
check(provider_dns::lookup('https://example.org/path',$resolver,$read,$write,$clock)===[],'URL not accepted as host');
$key='securitysearch-dns-v1-'.hash('sha256','images.securityops.co');$store[$key]=['ips'=>['127.0.0.1'],'until'=>$now+15];
$before=$calls;check($lookup('images.securityops.co')[0]==='1.1.1.1' && $calls===$before+1,'poisoned shared data revalidated');
$store[$key]=['ips'=>['1.1.1.1'],'until'=>$now+1000];$before=$calls;$lookup('images.securityops.co');check($calls===$before+1,'implausible expiration rejected');
$writesBefore=$writes;provider_dns::lookup('redlib.privacyredirect.com',fn()=>['ips'=>['1.1.1.1'],'ttl'=>0],fn()=>false,$write,$clock);check($writes===$writesBefore,'TTL zero not persisted');
provider_dns::lookup('redlib.privacyredirect.com',fn()=>['ips'=>[],'ttl'=>15],fn()=>false,$write,$clock);check($writes===$writesBefore,'failures not shared');
check($lookup('IMAGES.SECURITYOPS.CO.')[0]==='1.1.1.1','canonical host key');
echo "PASS: $n DNS metadata assertions; 30 fixed-host reads used one resolver callback (fixture, not live latency).\n";
