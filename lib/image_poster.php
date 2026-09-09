<?php
require_once __DIR__.'/animated_preview.php';

/** Extract a static compressed frame before the bounded one-frame decoder. */
function image_poster_body(string $body): string {
    try { $inspection=animated_preview_inspect_raster($body); }
    catch (UnexpectedValueException $error) { return $body; } // Static input uses the normal decoder.
    if ($inspection['frames']<2) return $body;
    if (str_starts_with($body,'GIF')) {
        $offset=13+((ord($body[10]) & 0x80) ? 3*(1<<((ord($body[10]) & 7)+1)) : 0);
        $bytes=0;
        while ($offset<strlen($body)) {
            $marker=ord($body[$offset++]);
            if ($marker===0x21) { $offset=animated_preview_skip_gif_sub_blocks($body,$offset+1,$bytes);continue; }
            if ($marker!==0x2c) break;
            $packed=ord($body[$offset+8]);$offset+=9;
            if ($packed & 0x80) $offset+=3*(1<<(($packed & 7)+1));
            $offset=animated_preview_skip_gif_sub_blocks($body,$offset+1,$bytes);
            return substr($body,0,$offset)."\x3b";
        }
    } elseif (str_starts_with($body,"\x89PNG\r\n\x1a\n")) {
        // IDAT is always the default PNG image; discard animation control/data.
        $poster=substr($body,0,8);$offset=8;
        while ($offset+12<=strlen($body)) {
            $length=unpack('N',substr($body,$offset,4))[1];$type=substr($body,$offset+4,4);
            if (in_array($type,['IHDR','PLTE','tRNS','IDAT','IEND'],true)) $poster.=substr($body,$offset,$length+12);
            $offset+=$length+12;
        }
        return $poster;
    } elseif (str_starts_with($body,'RIFF')) {
        $offset=12;
        while ($offset+8<=strlen($body)) {
            $type=substr($body,$offset,4);$length=animated_preview_read_le32($body,$offset+4);$data=$offset+8;
            if ($type==='ANMF') {
                $width=substr($body,$data+6,3);$height=substr($body,$data+9,3);
                $frame=substr($body,$data+16,$length-16);$alpha=0;$child=0;
                while ($child+8<=strlen($frame)) {
                    $tag=substr($frame,$child,4);$size=animated_preview_read_le32($frame,$child+4);
                    if ($tag==='ALPH' || ($tag==='VP8L' && (animated_preview_read_le32($frame,$child+9) & 0x10000000))) $alpha=0x10;
                    $child+=$size+8+($size & 1);
                }
                $chunks='VP8X'.pack('V',10).chr($alpha)."\0\0\0".$width.$height.$frame;
                return 'RIFF'.pack('V',strlen($chunks)+4).'WEBP'.$chunks;
            }
            $offset+=$length+8+($length & 1);
        }
    }
    throw new UnexpectedValueException('Unable to extract a validated poster frame.');
}
