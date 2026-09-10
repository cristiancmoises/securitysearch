<?php
// Generates a config in an isolated tree; never writes the repository config.
$root=sys_get_temp_dir().'/securitysearch-config-'.bin2hex(random_bytes(6));
mkdir($root,0700);mkdir($root.'/docker');mkdir($root.'/data');mkdir($root.'/data/captcha');
copy('docker/gen_config.php',$root.'/docker/gen_config.php');copy('data/config.php',$root.'/data/config.php');
$marker='operator "quoted" $value \\ literal';
putenv('FOURGET_SERVER_NAME='.$marker);putenv('FOURGET_API_ENABLED=false');putenv('FOURGET_UNKNOWN=ignored');putenv('FOURGET_REDLIB_FALLBACKS=false');
try {
 $p=proc_open([PHP_BINARY,$root.'/docker/gen_config.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
 if(proc_close($p)!==0){throw new RuntimeException('Configuration generator failed: '.$error);}
 require $root.'/data/config.php';
 if(config::SERVER_NAME!==$marker || config::API_ENABLED!==false || defined('config::UNKNOWN') || config::REDLIB_FALLBACKS!==false || config::VERSION!==26 || config::DEFAULT_THEME!=="Black"){throw new RuntimeException('Configuration literals/types changed');}
 echo "PASS: quoted literals, boolean API disable, unknown environment keys and version integrity.\n";
} finally {
 unlink($root.'/docker/gen_config.php');unlink($root.'/data/config.php');rmdir($root.'/data/captcha');rmdir($root.'/data');rmdir($root.'/docker');rmdir($root);
}
