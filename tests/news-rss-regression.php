<?php
require 'lib/news_rss.php';
if(!class_exists('DOMDocument')||!function_exists('mb_strcut')){fwrite(STDERR,"NATIVE_RUNTIME_MISSING: DOM/mbstring for RSS parser\n");exit(2);}
$n=0;function rr($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
function rssxml($body){return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:News="urn:news"><channel><title>Fixture source</title>'.$body.'</channel></rss>';}
function item($url,$title='World news',$extra=''){return '<item><title>'.htmlspecialchars($title,ENT_XML1,'UTF-8').'</title><link>'.htmlspecialchars($url,ENT_XML1,'UTF-8').'</link><pubDate>Thu, 10 Sep 2026 12:00:00 GMT</pubDate>'.$extra.'</item>';}
$base=item('https://example.org/story','Café & &lt;script&gt;', '<source>Publisher &amp; Co</source><description><![CDATA[<script>evil()</script><img src="https://private.invalid/">]]></description>');
$r=news_rss::decode(rssxml($base));rr(count($r['news'])===1,'one result');rr($r['news'][0]['author']==='Publisher & Co','source attribution');rr($r['news'][0]['description']===null,'no remote HTML descriptions');rr($r['news'][0]['thumb']['url']===null,'no remote image request');rr($r['npt']===null,'no fabricated pagination');
$r=news_rss::decode(rssxml($base.$base));rr(count($r['news'])===1,'duplicate links removed');
$r=news_rss::decode(rssxml(''));rr($r['news']===[],'valid empty RSS');
$r=news_rss::decode(rssxml(item('https://example.org/b','Bing result','<News:Source>Source Name</News:Source>')));rr($r['news'][0]['author']==='Source Name','namespace source');
$many='';for($i=0;$i<90;$i++)$many.=item('https://example.org/'.$i);rr(count(news_rss::decode(rssxml($many))['news'])===40,'bounded result list');
$bad=['<html><title>checking your browser</title></html>','<rss><channel>','<!DOCTYPE rss SYSTEM "file:///etc/passwd"><rss/>',rssxml(item('javascript:alert(1)')),rssxml(item('http://127.0.0.1/')),'<rss><channel><title>x</title></channel><channel><title>y</title></channel></rss>',rssxml('<xi:include xmlns:xi="http://www.w3.org/2001/XInclude" href="file:///etc/passwd"/>')];
foreach($bad as$x){try{news_rss::decode($x);throw new LogicException('unsafe parser accepted');}catch(news_failure $e){rr(true,'fail closed');}}
$before=libxml_use_internal_errors(false);news_rss::decode(rssxml($base));rr(libxml_use_internal_errors()===false,'libxml state restored');libxml_use_internal_errors($before);
echo "PASS: $n native RSS/XML/escaping/security assertions.\n";
