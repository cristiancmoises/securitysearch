<?php
/** Search hot-path regressions: no external requests. */
require 'data/config.php';
require 'lib/frontend.php';
require 'lib/fuckhtml.php';
require 'lib/backend.php';
require 'scraper/brave.php';

$n=0;
function check_hot($value,string $label): void { global $n; $n++; if(!$value) throw new RuntimeException($label); }

// Repeated result rendering for one query reuses the exact same pattern while
// preserving the prior markup. The cache belongs to this one frontend object.
$frontend=new frontend();
$expected='The <b>fast</b> <b>search</b> keeps <b>fast</b>-<b>search</b> intact.';
check_hot($frontend->highlighttext('fast search','The fast search keeps fast-search intact.')===$expected,'Highlight output changed');
check_hot($frontend->highlighttext('fast search','A fast search')==='A <b>fast</b> <b>search</b>','Repeated highlight output');
$rf=new ReflectionClass($frontend);$cache=$rf->getProperty('highlight_regex_cache')->getValue($frontend);
check_hot(count($cache)===1 && isset($cache['fast search']),'Highlight pattern not reused');
foreach(['one','two','three','four','five'] as $q)$frontend->highlighttext($q,$q.' result');
$cache=$rf->getProperty('highlight_regex_cache')->getValue($frontend);
check_hot(count($cache)<=4,'Highlight cache is not bounded');

// Brave pagination keeps its established parser semantics but may never fabricate
// offset zero when a changed upstream page contains a malformed Next href.
class fixture_backend_hot extends backend {
    public array $stored=[];
    public function __construct(){}
    public function store(string $payload,string $page,string $proxy){$this->stored[]=[$payload,$page,$proxy];return 'fixture-token';}
}
$brave=new brave();$rb=new ReflectionClass($brave);$backend=new fixture_backend_hot();$rb->getProperty('backend')->setValue($brave,$backend);
$parser=$rb->getProperty('fuckhtml')->getValue($brave);
$parser->load('<html><title>fixture</title><div class="pagination"><a class="button" href="/search?q=x&amp;offset=42">Next</a></div></html>');
$token=$rb->getMethod('generatenextpagetoken')->invoke($brave,'fixture','no','all','1','news','raw_ip::::');
check_hot($token==='fixture-token' && count($backend->stored)===1,'Valid Brave continuation lost');
$stored=json_decode($backend->stored[0][0],true);check_hot(($stored['offset']??null)===42,'Valid Brave offset changed');

$backend->stored=[];$parser->load('<html><title>fixture</title><div class="pagination"><a class="button" href="/search?q=x">Next</a></div></html>');
check_hot($rb->getMethod('generatenextpagetoken')->invoke($brave,'fixture','no','all','1','news','raw_ip::::')===null,'Malformed offset accepted');
check_hot($backend->stored===[],'Malformed offset was persisted');

echo "PASS: $n request-local highlighting and Brave continuation assertions.\n";
