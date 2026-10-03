<?php
require_once 'data/config.php';require_once 'scraper/luma.php';
$checks=0;
function verify_luma($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function payload_luma(array $changes=[]):string {
 return json_encode(array_replace(['source_kind'=>'live_instagram','live_instagram_verified'=>true,
  'profiles'=>[],'items'=>[['shortcode'=>'public01','username'=>'public_user','caption'=>'Public landscape <script>',
   'media'=>[['image'=>'/media/'.str_repeat('A',24),'video'=>null,'width'=>640,'height'=>480]],
   'type'=>'image','content_kind'=>'post','permalink'=>'https://www.instagram.com/p/public01/']],
  'next_cursor'=>null],$changes),JSON_THROW_ON_ERROR);
}
class fixture_luma extends luma {
 public array $calls=[];public string $reply='';
 protected function fetch_path(string $path,array $params):string{$this->calls[]=[$path,$params];return $this->reply;}
 protected function continuation(array $params,string $kind):string{return 'fixture-next';}
}
$l=new fixture_luma();$l->reply=payload_luma();
foreach (['landscape'=>['/api/search',['q'=>'landscape','scope'=>'all']],
 '@Public_User'=>['/api/profile/public_user',['feed'=>'all']],
 '#educação'=>['/api/tag/educa%C3%A7%C3%A3o',[]]] as $query=>$expected) {
 $l->calls=[];$out=$l->image(['s'=>$query,'cookie'=>'private','destination'=>'https://evil.invalid','npt'=>false]);
 verify_luma($l->calls===[$expected],'Only a fixed route and supported parameters are sent');
 verify_luma(count($out['image'])===1 && $out['npt']===null,'Actual response becomes one internal image card');
 verify_luma($out['image'][0]['source'][0]['url']===luma_search::ORIGIN.'/media/'.str_repeat('A',24),'Only fixed-origin opaque media is relayed');
 verify_luma($out['image'][0]['url']==='/images?s=%40public_user&scraper=luma','Result opens an internal public profile search');
}
$l->reply=payload_luma(['next_cursor'=>str_repeat('C',24).'.'.str_repeat('D',24)]);
verify_luma($l->image(['s'=>'landscape'])['npt']==='fixture-next','Provider continuation uses existing encrypted backend');
foreach (['',str_repeat('a',101),str_repeat('é',101),"a\r\nb","\xff",[],"\u{0085}",'@../private','#bad/slash'] as $query) {
 $l->calls=[];$rejected=false;try{$l->image(['s'=>$query]);}catch(Exception $e){$rejected=true;}
 verify_luma($rejected && !$l->calls,'Invalid query is rejected before network access');
}
foreach (['garbage',payload_luma(['source_kind'=>'fixture']),payload_luma(['live_instagram_verified'=>false]),
 payload_luma(['items'=>'bad']),payload_luma(['profiles'=>['invalid']]),payload_luma(['next_cursor'=>'https://evil.invalid']),
 payload_luma(['items'=>array_fill(0,101,[])])] as $body) {
 $rejected=false;try{$l->decode($body);}catch(RuntimeException $e){$rejected=true;}
 verify_luma($rejected,'Invalid or unverified response cannot become successful results');
}
foreach (['https://evil.invalid/a.jpg','//127.0.0.1/a','/media/../private','/media/'.str_repeat('A',24).'?host=evil','http://127.0.0.1/'] as $media) {
 $data=json_decode(payload_luma(),true);$data['items'][0]['media'][0]['image']=$media;
 $rejected=false;try{$l->decode(json_encode($data));}catch(RuntimeException $e){$rejected=true;}
 verify_luma($rejected,'Untrusted media destination never reaches the relay');
}
$profile=['username'=>'public_user','name'=>'Public <svg>','avatar'=>'/media/'.str_repeat('B',24),'private'=>false];
$out=$l->decode(payload_luma(['items'=>[],'profiles'=>[$profile]]));
verify_luma(count($out['image'])===1 && str_contains($out['image'][0]['title'],'Public <svg>'),'Public account metadata is retained for escaped rendering');
$profile['avatar']=null;
$out=$l->decode(payload_luma(['items'=>[],'profiles'=>[$profile]]));
verify_luma(count($out['image'])===1 && str_contains($out['image'][0]['title'],'Profile photo unavailable'),'A public account without a photo has an explicit truthful label');
verify_luma($out['image'][0]['source'][0]['url']==='/static/profile-placeholder.svg','Missing photo uses only the owned generic local profile icon');
unset($profile['avatar']);
verify_luma(count($l->decode(payload_luma(['items'=>[],'profiles'=>[$profile]]))['image'])===1,'Absent optional avatar preserves a public account');
require_once 'lib/frontend.php';require_once 'lib/image_results.php';
$f=new frontend();
$cards=image_results::items($f,['s'=>'public_user','format'=>'gif','type'=>'animated','quality'=>'original'],$out);
verify_luma(count($cards)===1 && $cards[0]['preview']==='/static/profile-placeholder.svg' && $cards[0]['original']==='/static/profile-placeholder.svg' && $cards[0]['motion']===null,'Local fallback bypasses remote transport and animation in every display mode');
verify_luma($cards[0]['source']==='/images?s=%40public_user&scraper=luma','Photo-free public account still opens its internal profile');
$unsafe=$out;$unsafe['image'][0]['source'][0]['url']='/static/profile-placeholder.svg?url=http://127.0.0.1';
verify_luma(image_results::items($f,[],$unsafe)===[],'Only the exact local icon path is allowed, never arbitrary local paths or parameters');
$profile['avatar']='https://evil.invalid/avatar.svg';
$rejected=false;try{$l->decode(payload_luma(['items'=>[],'profiles'=>[$profile]]));}catch(RuntimeException $e){$rejected=true;}
verify_luma($rejected,'Non-null invalid avatar is rejected, not disguised as a missing photo');
$profile['private']=true;
verify_luma($l->decode(payload_luma(['items'=>[],'profiles'=>[$profile]]))['image']===[],'Private accounts are not presented as accessible public results');
verify_luma($l->decode(payload_luma(['items'=>[]]))['image']===[],'Genuine empty response remains an empty result');
echo "PASS: $checks LUMA route, input, provenance, pagination, media and account checks.\n";
