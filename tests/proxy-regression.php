<?php
// Network-free option-capture test. No credentials/config files are read.
// php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php
if(function_exists('curl_setopt')){
	fwrite(STDERR, "Run with -d disable_functions=curl_setopt; this test must not make network requests.\n");
	exit(2);
}
if(!function_exists('curl_setopt')){
	function curl_setopt($handle, $option, $value){
		$GLOBALS['proxy_test_options'][$option] = $value;
		return true;
	}
}
function proxy_check($value, $label){
	if(!$value){ throw new RuntimeException($label); }
}
class config{
	public const SOURCE_IP_GOOGLE = false;
	public const PROXY_AUDIT = 'fixture';
	public const PROXY_DIRECT = false;
}
require dirname(__DIR__) . '/lib/backend.php';
$backend = new backend('google');
$types = [
	'http'=>CURLPROXY_HTTP, 'https'=>CURLPROXY_HTTP,
	'socks4'=>CURLPROXY_SOCKS4, 'socks4a'=>CURLPROXY_SOCKS4A,
	'socks5'=>CURLPROXY_SOCKS5, 'socks5h'=>CURLPROXY_SOCKS5_HOSTNAME,
	'socks5a'=>CURLPROXY_SOCKS5_HOSTNAME, 'socks5_hostname'=>CURLPROXY_SOCKS5_HOSTNAME
];
foreach($types as $type=>$expected){
	$GLOBALS['proxy_test_options'] = [];
	$handle = curl_init();
	$backend->assign_proxy($handle, " \t".$type.":proxy.example:9050:audit:dummy:colon\r\n");
	$options = $GLOBALS['proxy_test_options'];
	proxy_check($options[CURLOPT_PROXYTYPE] === $expected, 'Proxy type '.$type);
	proxy_check($options[CURLOPT_PROXY] === (in_array($type,['http','https'],true) ? $type.'://' : '').'proxy.example:9050', 'Explicit proxy '.$type);
	proxy_check($options[CURLOPT_NOPROXY] === '', 'Ambient NO_PROXY disabled');
	proxy_check($options[CURLOPT_PROXYUSERPWD] === 'audit:dummy:colon', 'Password colons preserved');
	curl_close($handle);
}
foreach(['http:127.0.0.1:1::', 'https:proxy-service:65535::', 'socks5h:proxy.example.:9050::'] as $valid){
	$handle = curl_init();$backend->assign_proxy($handle,$valid);curl_close($handle);
}
$bad = [
	'', 'socks5h', 'socks5h:proxy.example:9050', 'http:proxy.example:80:',
	'unknown:proxy.example:9050:audit:private-marker',
	'http::80::', 'http:https://proxy.example:80::', 'http:user@proxy.example:80::',
	'http:proxy.example/path:80::', 'http:proxy example:80::', 'http:999.999.1.1:80::',
	'http:[::1]:80::', 'http:::1:80::', 'http:proxy.example:0::',
	'http:proxy.example:65536::', 'http:proxy.example:-1::', 'http:proxy.example:+1::',
	'http:proxy.example:abc::', 'http:proxy.example:1e2::',
	'http:proxy.example:999999999999999999999999999::',
	'raw_ip', 'raw_ip::::ignored', 'raw_ip:proxy.example:80::',
	'http:proxy.example:80::private-marker', "http:proxy.example:80:audit:private-marker\nInjected",
	"http:proxy.example:80:audit:private-marker\0"
];
foreach($bad as $line){
	$GLOBALS['proxy_test_options'] = [];$handle=curl_init();$rejected=false;
	try{ $backend->assign_proxy($handle,$line); }
	catch(Exception $error){
		$rejected=true;
		proxy_check(!str_contains($error->getMessage(),'private-marker'), 'Credentials not reflected');
	}
	proxy_check($rejected,'Malformed entry rejected');
	proxy_check($GLOBALS['proxy_test_options'] === [],'Malformed entry changes no routing options');
	curl_close($handle);
}
foreach(['http_proxy','https_proxy','all_proxy','ALL_PROXY'] as $name){putenv($name.'=http://ambient.invalid:9999');}
putenv('NO_PROXY=*');putenv('no_proxy=*');
$handle=curl_init();$GLOBALS['proxy_test_options']=[];
$backend->assign_proxy($handle,'raw_ip::::');
proxy_check($GLOBALS['proxy_test_options'][CURLOPT_PROXY] === '', 'Explicit direct ignores ambient proxy');
proxy_check($GLOBALS['proxy_test_options'][CURLOPT_PROXYUSERPWD] === '', 'Old credentials cleared');
$backend->assign_proxy($handle,'socks5h:proxy.example:9050::');
proxy_check($GLOBALS['proxy_test_options'][CURLOPT_NOPROXY] === '', 'Configured pool ignores ambient bypass');
curl_close($handle);
// Pool parsing/rotation uses an isolated disposable fixture, never repo data.
$original=getcwd();$temporary=sys_get_temp_dir().'/securitysearch-proxy-'.bin2hex(random_bytes(8));
mkdir($temporary,0700);mkdir($temporary.'/data',0700);mkdir($temporary.'/data/proxies',0700);
$fixture=$temporary.'/data/proxies/fixture.txt';
try{
	file_put_contents($fixture," # comment\r\n\t\r\n socks5h:proxy-a:9050::\r\nhttp:127.0.0.1:8080::\r\n");
	chdir($temporary);$pool=new backend('audit');
	proxy_check($pool->get_ip(0)==='socks5h:proxy-a:9050::','Pool trims CRLF and indentation');
	proxy_check($pool->get_ip(1)==='http:127.0.0.1:8080::','Pool rotation preserved');
	proxy_check($pool->get_ip(2)===$pool->get_ip(0),'Pool wrap preserved');
	proxy_check((new backend('direct'))->get_ip()==='raw_ip::::','Explicit direct contract');
	file_put_contents($fixture,"unknown:proxy-a:9050::\n");
	$rejected=false;try{$pool->get_ip(0);}catch(Exception $error){$rejected=true;}
	proxy_check($rejected,'Invalid selected pool entry rejected before transport');
}finally{
	chdir($original);unlink($fixture);rmdir($temporary.'/data/proxies');rmdir($temporary.'/data');rmdir($temporary);
}
echo "PASS: strict proxy parsing, CRLF, supported transports, credential privacy, ambient routing overrides and pool rotation.\n";
