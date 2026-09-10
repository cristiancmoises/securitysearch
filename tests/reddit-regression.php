<?php
require 'data/config.php';require 'lib/frontend.php';require 'scraper/reddit.php';
function verify($value,$label){if(!$value)throw new RuntimeException($label);}
$provider=new reddit();$body=file_get_contents('tests/fixtures/redlib-news.html');$result=$provider->decode($body);
verify(count($result['news'])===2,'Recognized and bounded Reddit permalinks');
verify($result['news'][0]['url']==='https://redlib.privacyredirect.com/r/news/comments/abc123/gnu_guix_release/','Source stays in Redlib');
verify($result['news'][0]['title']==='GNU Guix & privacy <script>' && $result['news'][0]['author']==='r/news · u/A&B','Decoded text, no flair-as-title');
verify($result['news'][0]['date']===strtotime('2026-09-09 01:23:45 UTC') && $result['news'][1]['date']===null,'Strict UTC date parsing');
verify($result['after']==='t3_next123','Formatted next link decoded');
$html=(new frontend())->drawtextresult($result['news'][0],$result['news'][0]['author'],null,'');
verify(!str_contains($html,'<script>') && !str_contains($html,'<b></b>') && str_contains($html,'A&amp;B') && !str_contains($html,'A&amp;amp;B'),'Blank-query rendering escapes exactly once');
foreach(['<div id="error">blocked</div>','<div>captcha</div>','<div id="column_one">unknown</div>'] as $bad){try{$provider->decode($bad);throw new LogicException('Bad payload accepted');}catch(RuntimeException $e){verify(!($e instanceof LogicException),'Reject error/malformed payload');}}
verify($provider->decode('<div id="column_one"><form id="search_sort"></form><center>No posts were found.</center></div>')['news']===[],'Recognizable empty search');
foreach(['https://evil.example/?after=t3_x','//evil.example/?after=t3_x','?after[]=t3_x','?after=t3_a%0d','/settings?after=t3_x'] as $href){
 $bad=preg_replace('#<footer>.*</footer>#s','<footer><a accesskey="N" href="'.htmlspecialchars($href,ENT_QUOTES).'">Next</a></footer>',$body);
 verify($provider->decode($bad)['after']===null,'Reject unsafe continuation');
}
class reddit_fixture extends reddit {
 public array $seen=[];
 protected function fetch_redlib(string $origin,string $path,array $params,int $deadline): string {$this->seen[]=[$path,$params];return file_get_contents('tests/fixtures/redlib-news.html');}
}
$fixture=new reddit_fixture();$one=$fixture->news(['s'=>'GNU Guix','sort'=>'relevance','time'=>'week']);
$fixture->news(['s'=>'tampered','npt'=>$one['npt'],'sort'=>'new']);
verify($fixture->seen[0][0]==='/r/news+worldnews/search' && $fixture->seen[1][1]['q']==='GNU Guix' && $fixture->seen[1][1]['sort']==='relevance' && $fixture->seen[1][1]['t']==='week','Authenticated pagination retains query/sort/period');
verify($fixture->seen[1][1]['after']==='t3_next123' && $fixture->seen[1][1]['restrict_sr']==='on','Only extracted cursor advances fixed search route');
$fixture->news(['s'=>'']);verify($fixture->seen[2][0]==='/r/news+worldnews/new' && !isset($fixture->seen[2][1]['q']),'Blank news opens feed without redirecting empty search');
$fixture->news(['s'=>'r/news']);verify($fixture->seen[3][1]['q']==='"r/news"','Reserved navigation prefix treated as search');
$_GET=[];$_COOKIE=[];[$default_provider,$filters]=(new frontend())->getscraperfilters('news');verify($default_provider instanceof newswire,'Default independent RSS news');
verify($provider instanceof reddit && $provider !== $default_provider,'Default selection preserves the separate Reddit parser fixture');

verify(count($provider->decode(str_replace('gnu_guix_release','notícias_do_brasil',$body))['news'])===2,'Unicode permalink retained safely');
verify($provider->decode('<div id="column_one"><div id="posts"></div></div>')['news']===[],'Empty feed container');
try {$provider->decode('<div id="column_one"><form id="search_sort"></form><div id="error">blocked</div></div>');throw new LogicException('Error accepted');}catch(RuntimeException $e){}
$fixture->news(['s'=>'https://www.reddit.com/r/news']);verify(str_starts_with($fixture->seen[4][1]['q'],'"'),'Reddit URL prefix stays a search');
echo "PASS: Redlib source-contract parsing, escaping, dates, empty/errors, fixed routes, safe cursors, default feed and authoritative pagination.\n";
