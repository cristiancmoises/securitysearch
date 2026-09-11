<?php
require 'data/config.php';require 'scraper/google_cse.php';
$n=0;
function ok($yes,$label){global $n;$n++;if(!$yes)throw new RuntimeException($label);}
function no($fn,$label){try{$fn();}catch(upstream_search_failure $e){ok(true,$label);return;}throw new RuntimeException($label);}
$plain='google.search.cse.api123({"results":[]});';
foreach([$plain,"\xEF\xBB\xBF".$plain,'/*O_o*/'.$plain,'/* comment */' . " \n " . $plain] as $body)ok(google_cse_protocol::response($body)===['results'=>[]],'Valid JSONP prologue');
foreach(['x=1;'.$plain,$plain.'alert(1);','/*'.str_repeat('x',4097).'*/'.$plain,'/*bad'.$plain,'evil({});','google.search.cse.api(null);','google.search.cse.api({"results":"bad"});','google.search.cse.api({"results":{"x":1}});','<html>captcha</html>',str_repeat(' ',4194305)]as $body)no(fn()=>google_cse_protocol::response($body),'Malformed/non-data wrapper refused');
$deep='google.search.cse.api({"results":[],"a":'.str_repeat('[',70).'0'.str_repeat(']',70).'});';no(fn()=>google_cse_protocol::response($deep),'JSON nesting bounded');
foreach(["relativeUrl='/cse.js?cx=x';",'relativeUrl = "/cse.js?cx=x" ;',"relativeUrl='/cse.js?cx\\x3dx';"]as $html)ok(google_cse_protocol::script_path($html)==='/cse.js?cx=x','Bounded quoted bootstrap path');
foreach(["relativeUrl='//evil.invalid/cse.js';","relativeUrl='/cse.js/evil';","relativeUrl='/search?q=x';","relativeUrl='/cse.js#bad';","relativeUrl='/cse.js?x\\x0aevil';","relativeUrl='/cse.js' + attacker;","relativeUrl='/cse.js?".str_repeat('a',4097)."';"]as $html)ok(google_cse_protocol::script_path($html)===null,'Unsafe bootstrap rejected');
foreach(['})({"cse_token":"abc:123","cselibVersion":"lib-v1"});','} ) ( {"cse_token":"abc:123","cselibVersion":"lib-v1","nested":{"a":"}x"}} );']as $js)ok(google_cse_protocol::bootstrap($js)===['token'=>'abc:123','lib'=>'lib-v1'],'Bootstrap JSON balanced');
foreach(['})({"cse_token":null,"cselibVersion":"ok"});','})({"cse_token":"x","cselibVersion":"<bad>"});','})({"cse_token":"x","cselibVersion":"ok"}+evil());','})({"cse_token":"x\n","cselibVersion":"ok"});']as $js)ok(google_cse_protocol::bootstrap($js)===null,'Invalid session data refused');
$base=['titleNoFormatting'=>'Guix','contentNoFormatting'=>'News','unescapedUrl'=>'https://example.org/a'];
foreach([0,'0',null,-2,[],true,'not a number','INF']as $h){$row=$base+['richSnippet'=>['cseThumbnail'=>['src'=>'https://example.org/image.jpg','width'=>300,'height'=>$h]]];$r=google_cse_protocol::web_result($row);ok($r['thumb']['ratio']==='1:1','Bad height cannot divide by zero');}
foreach(['bad',null,5,[],['unescapedUrl'=>'javascript:bad','titleNoFormatting'=>'bad'],['unescapedUrl'=>'https://user:secret@example.org/a','titleNoFormatting'=>'bad']]as $row)ok(google_cse_protocol::web_result($row)===null,'Malformed record safely omitted');
$r=google_cse_protocol::web_result($base+['richSnippet'=>'bad']);ok($r['title']==='Guix'&&$r['thumb']['url']===null,'Unexpected snippet shape');
$r=google_cse_protocol::web_result(['title'=>'<b>Safe</b> &amp; sound','unescapedUrl'=>'https://example.org/a','contentNoFormatting'=>[]]);ok($r['title']==='Safe & sound'&&$r['description']==='','Optional field safe fallback');
foreach([true,false]as $exact)ok(google_cse_protocol::next_start(['cursor'=>['isExactTotalResults'=>$exact,'pages'=>[['start'=>'0'],['start'=>10],['start'=>'20']]]],0,2)===10,'Exact count does not hide authoritative next offset');
foreach([[],['pages'=>[]],['pages'=>[['start'=>0],['start'=>-1],['start'=>'https://evil/'],['start'=>true],['start'=>1001]]]]as $cursor)ok(google_cse_protocol::next_start(['cursor'=>$cursor],0,1)===null,'No invented or unsafe next offset');
ok(google_cse_protocol::next_start(['cursor'=>['pages'=>[['start'=>10]]]],0,0)===null,'Empty page has no continuation');
$g=new google_cse();$r=new ReflectionClass($g);$m=$r->getMethod('parse_image_result');
$img=$m->invoke($g,['unescapedUrl'=>'https://example.org/o.jpg','width'=>2000,'height'=>1000,'tbLargeUrl'=>'https://example.org/l.jpg','tbLargeWidth'=>800,'tbLargeHeight'=>400,'tbUrl'=>'https://example.org/s.jpg','tbWidth'=>240,'tbHeight'=>120]);
ok(count($img['source'])===3 && $img['source'][2]['width']===240,'Original, large and small actual variants preserved');
foreach([true,[],0,'0','NaN']as $d)ok(google_cse_protocol::dimension($d)===null,'Reject invalid dimensions');
$refuse=$r->getMethod('decode_response');$decoded=$refuse->invoke($g,'/*O_o*/google.search.cse.api({"results":[{"titleNoFormatting":"Captcha explained"}]});');ok(count($decoded['results'])===1,'Article text is not an anti-abuse response');
echo "PASS: $n CSE protocol/result assertions; no external request.\n";
