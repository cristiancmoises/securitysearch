<?php
/** Real core PHP/zlib tests. No network, ImageMagick or fake PHP extensions. */
require __DIR__.'/../lib/thumbnail_png.php';
$n=0;
function check35($ok,$label){global $n;$n++;if(!$ok)throw new RuntimeException($label);}
function chunk35($kind,$data){return pack('N',strlen($data)).$kind.$data.hash('crc32b',$kind.$data,true);}
function png35($w=12,$h=8,$color=2,$depth=8,$filter=0,$interlace=0,$compressed=null,$before='', $after='') {
    $channels=($color===0?1:($color===2?3:4));
    $raw=str_repeat(chr($filter).str_repeat("\x80",$w*$channels),$h);
    return "\x89PNG\r\n\x1a\n".chunk35('IHDR',pack('NNCCCCC',$w,$h,$depth,$color,0,0,$interlace)).$before.
        chunk35('IDAT',$compressed ?? gzcompress($raw)).$after.chunk35('IEND','');
}
$rgb=png35();
check35(thumbnail_png::eligible($rgb),'Opaque RGB fits unchanged path');
check35(thumbnail_png::eligible(png35(1,1,0)),'Opaque grayscale fits');
check35(thumbnail_png::eligible(png35(236,180)),'Exact canvas boundary');
foreach(range(0,4) as $filter)check35(thumbnail_png::eligible(png35(filter:$filter)),'Valid row filter '.$filter);
foreach([[237,180],[236,181],[0,1],[1,0]] as [$w,$h])check35(!thumbnail_png::eligible(png35($w,$h)),'Out of bounds canvas');
foreach([1,3,4,5,6] as $color)check35(!thumbnail_png::eligible(png35(color:$color)),'Unsupported/alpha color '.$color);
foreach([1,2,4,16] as $depth)check35(!thumbnail_png::eligible(png35(depth:$depth)),'Unsupported depth '.$depth);
check35(!thumbnail_png::eligible(png35(interlace:1)),'Interlace normal path');
check35(!thumbnail_png::eligible(png35(filter:5)),'Invalid filter');
foreach(['tEXt','eXIf','iCCP','PLTE','tRNS','acTL','fcTL','gAMA'] as $tag)check35(!thumbnail_png::eligible(png35(before:chunk35($tag,'private'))),'Metadata/animation/extra feature normal path '.$tag);
check35(!thumbnail_png::eligible($rgb.'extra'),'Trailing bytes');
check35(!thumbnail_png::eligible(substr($rgb,0,-1)),'Truncated IEND');
check35(!thumbnail_png::eligible(substr($rgb,0,-12)),'Missing IEND');
$crc=$rgb;$crc[29]=chr(ord($crc[29])^1);check35(!thumbnail_png::eligible($crc),'CRC corruption');
check35(!thumbnail_png::eligible("\x89PNG\r\n\x1a\n".chunk35('IDAT','invalid')),'IHDR first');
check35(!thumbnail_png::eligible(png35(after:chunk35('IHDR',substr($rgb,16,13)))),'Duplicate IHDR');
check35(!thumbnail_png::eligible(png35(compressed:'invalid')),'Invalid zlib');
$raw=str_repeat("\0".str_repeat("\x80",36),8);
check35(!thumbnail_png::eligible(png35(compressed:gzencode($raw))),'Gzip is not PNG zlib');
check35(!thumbnail_png::eligible(png35(compressed:gzdeflate($raw))),'Raw deflate is not PNG zlib');
check35(!thumbnail_png::eligible(png35(compressed:gzcompress($raw).'extra')),'Trailing compressed bytes rejected');
check35(!thumbnail_png::eligible(png35(compressed:gzcompress($raw).gzcompress($raw))),'Multiple zlib streams rejected');
check35(!thumbnail_png::eligible(png35(compressed:gzcompress(substr($raw,1)))),'Missing row data');
check35(!thumbnail_png::eligible(png35(compressed:gzcompress($raw.'x'))),'Extra row data');
check35(!thumbnail_png::eligible(png35(compressed:gzcompress(str_repeat('x',2*1024*1024)))),'Inflate overrun rejected within small bound');
$compressed=gzcompress($raw);$split=substr($rgb,0,33);
foreach(str_split($compressed,2) as $part)$split.=chunk35('IDAT',$part);
$split.=chunk35('IEND','');check35(thumbnail_png::eligible($split),'Split consecutive IDAT');
$many=substr($rgb,0,33).str_repeat(chunk35('IDAT',''),128).chunk35('IDAT',$compressed).chunk35('IEND','');
check35(!thumbnail_png::eligible($many),'Chunk count bounded');
check35(!thumbnail_png::eligible(str_pad($rgb,32769,'x')),'Byte cap');
$length=$rgb; $length=substr($length,0,33).pack('N',0x7fffffff).substr($length,37);
check35(!thumbnail_png::eligible($length),'Chunk length overrun');
foreach(["",'not an image',substr($rgb,0,40)] as $bad)check35(!thumbnail_png::eligible($bad),'Malformed input');
$before=hash('sha256',$rgb);thumbnail_png::eligible($rgb);check35(hash('sha256',$rgb)===$before,'Input unmodified');
echo "PASS: $n native PHP bounded PNG assertions; no network or decoder substitution.\n";
