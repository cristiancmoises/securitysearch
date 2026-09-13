<?php
/** Real image pipeline, bounded provider metadata, no network or extension substitutes. */
require __DIR__.'/../data/config.php';
require __DIR__.'/../lib/frontend.php';
require __DIR__.'/../lib/image_results.php';
error_reporting(E_ALL);
set_error_handler(static function($n,$s,$f,$l){throw new ErrorException($s,0,$n,$f,$l);});
$n=0;
function check($ok,$message){global $n;$n++;if(!$ok)throw new RuntimeException($message);}
function result($title){return ['image'=>[['title'=>$title,'url'=>'https://example.invalid/page','source'=>[
 ['url'=>'https://example.invalid/original.png','width'=>1600,'height'=>1200],
 ['url'=>'https://example.invalid/preview.png','width'=>320,'height'=>240]]]]];}
function card($title){return image_results::items(new frontend(),[],result($title))[0];}
foreach([null,false,0,[],new stdClass(),'','  ',"\x00\t\n\x7f", "\xff\xfe", "\xc3("] as $bad){
 check(card($bad)['title']==='Image result','Malformed/blank label fallback');
}
foreach(['0','all','any','São Paulo','日本語','😀','&<>"\''] as $text){
 $i=card($text);check($i['title']===$text,'Valid label preserved');
 check(strlen($i['title'])<=1024,'Label byte budget');
 check(preg_match('//u',$i['title'])===1,'Well-formed UTF-8');
 check(json_decode(json_encode($i,JSON_THROW_ON_ERROR),true)['title']===$text,'Append JSON shares safe label');
}
check(card("a\x00b\nc\t d\x7fe")['title']==='a b c d e','Normalize control whitespace');
foreach(['a','é','€','😀'] as $character){
 foreach([1021,1022,1023,1024,1025] as $prefix){
  $title=str_repeat('x',$prefix).str_repeat($character,300000);
  $i=card($title);check(strlen($i['title'])<=1024,'Oversized title bounded');
  check(preg_match('//u',$i['title'])===1,'Truncation keeps valid UTF-8');
  check(str_starts_with($title,$i['title']),'No fabricated bytes');
 }
}
$i=card(str_repeat('&',1048576));check($i['title']===str_repeat('&',1024),'Large entity-expansion input limited before escaping');
[$html,$count]=image_results::render(new frontend(),[],result(str_repeat('&',1048576)));
check($count===1,'One card remains');check(strlen($html)<18000,'Repeated HTML labels bounded');
check(substr_count($html,str_repeat('&amp;',1024))===3,'Same escaped label in all three positions');
check(!str_contains($html,'&amp;amp;'),'No double escaping');
[$html]=image_results::render(new frontend(),[],result('<img src=x onerror="bad">'));
check(!str_contains($html,'<img src=x'),'Markup remains escaped');check(str_contains($html,'&lt;img'),'Escaped text retained');
check(str_contains($i['original'],'original.png'),'Original link preserved');check(str_contains($i['preview'],'preview.png'),'Useful preview preserved');
check($i['source']==='https://example.invalid/page','Result URL preserved');
$many=result(str_repeat('x',1048576));$many['image']=array_fill(0,40,$many['image'][0]);
$items=image_results::items(new frontend(),[],$many);check(count($items)===24,'Original page admission limit');
check(strlen(json_encode($items,JSON_THROW_ON_ERROR))<50000,'Append representation bounded with large labels');
check(image_results::MAX_SOURCES===32,'Source examination budget unchanged');
echo "PASS $n image-label assertions\n";
