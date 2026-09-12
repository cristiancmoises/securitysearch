<?php
/** Real card-selection/rendering tests. No provider, proxy or image network calls. */
require __DIR__.'/../data/config.php';
require __DIR__.'/../lib/frontend.php';
require __DIR__.'/../lib/image_results.php';
error_reporting(E_ALL);
set_error_handler(static function($n,$s,$f,$l){throw new ErrorException($s,0,$n,$f,$l);});
$n=0;
function check($yes,$label){global $n;$n++;if(!$yes)throw new RuntimeException($label);}
function source($name,$w=null,$h=null){return ['url'=>'https://example.invalid/'.$name.'.png','width'=>$w,'height'=>$h];}
function item($sources,$get=[],$extra=[]){
    return image_results::items(new frontend(),$get,['image'=>[array_merge(['source'=>$sources],$extra)]])[0]??null;
}
function target($url){parse_str(parse_url($url,PHP_URL_QUERY)??'',$q);return $q['i']??null;}
function selected($sources,$name,$label){$i=item($sources);check(target($i['preview'])===source($name)['url'],$label);return $i;}
$original=source('original',1600,1200);
$preview=source('preview',320,240);
$pixel=source('pixel',1,1);
$i=selected([$original,$preview,$pixel],'preview','1x1 cannot beat usable preview');
check([$i['width'],$i['height']] === [320,240],'HTML layout dimensions follow chosen preview');
check(target($i['original'])===$original['url'],'Original link is preserved');
selected([$original,source('large',640,480),$preview],'preview','Smallest adequate provider preview');
selected([$original,$preview,source('large',640,480)],'preview','Candidate order does not pick larger preview');
selected([$original,source('small',120,90),source('tiny',64,48)],'small','Largest undersized preview avoids huge original');
selected([$original,$pixel],'original','Only tiny preview falls back to original');
selected([$original,source('strip',1,5000)],'original','A strip cannot meet fit-box eligibility');
selected([$original,source('edge',16,180)],'original','Tiny edge threshold is inclusive');
selected([$original,source('narrow',17,180)],'narrow','Nontiny portrait at boundary');
selected([$original,source('portrait',90,180)],'portrait','Portrait fit inside height limit');
selected([$original,source('landscape',236,60)],'landscape','Landscape fit inside width limit');
selected([$original,source('below',235,179),source('landscape',236,60)],'landscape','Adequate candidate beats undersized candidate');
selected([$original,source('a',320,240),source('b',240,320)],'a','Equal areas retain provider order');
selected([$original,source('unknown'),$pixel],'unknown','Unknown provider preview is not replaced by pixel');
selected([$original,source('a'),source('b')],'b','Last unknown hint when no known adequate preview');
selected([$original,source('unknown'),$preview],'preview','Known adequate beats unknown hint');
selected([$original,$preview,source('unknown')],'preview','Trailing unknown cannot displace known adequate');
selected([$original,source('small',120,90),source('unknown')],'unknown','Unknown hint preserved over undersized only');
selected([$original],'original','Single source remains a result');
selected([$pixel],'pixel','Single tiny original is not silently discarded');
selected([source('unknown')],'unknown','Single unknown source remains a result');
foreach([null,'',0,-1,100001,[],new stdClass(),'bad','12px','NaN',INF,NAN] as $bad){
    selected([$original,source('invalid-size',$bad,$bad),$preview],'preview','Invalid dimensions do not outrank valid preview');
}
selected([$original,source('numeric','320','240'),$pixel],'numeric','Integer string dimensions supported');
foreach(['javascript:alert(1)','data:image/png;base64,AAAA','file:///etc/passwd','https://u:p@example.invalid/x','/relative','https://'.str_repeat('a',8192)] as $url){
    selected([$original,array_merge($preview,['url'=>$url]),source('safe',300,200)],'safe','Invalid URL not selected');
}
check(item([['url'=>'javascript:alert(1)']])===null,'No valid source means no card');
$i=item([$original,$preview,$pixel],['quality'=>'high']);
check(target($i['preview'])===$original['url']&&str_ends_with($i['preview'],'s=high'),'High-quality display unchanged');
check(target($i['links'][1]['href'])===$preview['url'],'High-quality preview alternative uses useful thumbnail');
$i=item([$original,$preview,$pixel],['quality'=>'original']);
check(target($i['preview'])===$original['url']&&str_ends_with($i['preview'],'s=original'),'Original-quality display unchanged');
foreach(['GIF','WEBP','APNG'] as $format){
 $motion='https://example.invalid/motion.'.strtolower($format);
 $i=item([$original,$preview,$pixel],[],['motion_url'=>$motion,'motion_format'=>$format]);
 check(target($i['preview'])===$preview['url']&&str_ends_with($i['preview'],'s=poster'),'Poster uses useful supplied preview '.$format);
 check(target($i['motion'])===$motion&&str_ends_with($i['motion'],'s=animated&preview=1'),'Motion URL remains original motion '.$format);
 check(target($i['links'][1]['href'])===$motion,'View animation link unchanged '.$format);
}
$sources=[$original];
for($i=1;$i<32;$i++)$sources[]=source('p'.$i,16,16);
$sources[]=$preview;
selected($sources,'original','At most first 32 entries inspected');
$sources[31]=$preview;
selected($sources,'preview','32nd supplied entry is eligible');
$invalid=array_fill(0,32,['url'=>'javascript:bad']);$invalid[]=$original;
check(item($invalid)===null,'Bound counts invalid entries as well as valid URLs');
selected([$original,$preview,array_merge($preview,['width'=>1,'height'=>1]),$pixel],'preview','Duplicate URL cannot replace earlier usable metadata');
$rows=array_fill(0,40,['title'=>'<svg onload="boom"> & "quoted"','source'=>[$original,$preview,$pixel],'url'=>'https://example.invalid/page']);
$bound=image_results::bounded(['image'=>$rows,'npt'=>'cursor']);
[$html,$count]=image_results::render(new frontend(),['s'=>'fixture'],$bound);
check($count===24&&$bound['omitted']===16&&$bound['npt']==='cursor','Page cap and pagination metadata unchanged');
check(substr_count($html,'loading="eager"')===4&&substr_count($html,'loading="lazy"')===20,'Existing loading priorities retained');
check(substr_count($html,'<img ')===24,'Exactly one poster per card, no fan-out');
check(!str_contains($html,'<svg')&&!str_contains($html,'pixel.png'),'Unsafe title escaped and tiny source not rendered');
check(str_contains($html,'width="320" height="240"'),'Rendered geometry follows selected source');
check(str_contains($html,'rel="noreferrer nofollow"'),'Referrer/privacy link attributes retained');
echo "PASS: $n image preview selection, safety, rendering and preservation assertions; no provider requests.\n";
