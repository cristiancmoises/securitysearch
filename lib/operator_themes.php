<?php
/** Optional operator-owned derivatives. Never bundled in source or forge releases. */
final class operator_themes {
    public static function available(string $name): bool {
        return isset(self::catalog()[$name]);
    }
    public static function catalog(): array {
        static $catalog=null;
        if ($catalog!==null) return $catalog;
        $catalog=[];$dir=dirname(__DIR__).'/static/operator-themes';$path=$dir.'/manifest.json';
        if (is_link($dir)||is_link($path)||!is_file($path)||filesize($path)>8192) return $catalog;
        $raw=@file_get_contents($path);if ($raw===false) return $catalog;
        $m=json_decode($raw,true,8);
        if (!is_array($m)||($m['schema']??null)!==1||($m['deployment_only']??null)!==true||
            ($m['source_commit']??'')!=='81979bb217f97df1c6acc724ef7d8c9da2789d7c') return $catalog;
        foreach (['Lain'=>'fcd2163ef4f77991b0f66af12099bd13e7322b3c','SecOps'=>'b5e32642d24b7e31bdba2a4c06aa7aad1d41cb9f'] as $name=>$blob) {
            if (($m['source_blobs'][$name]??null)!==$blob) continue;
            $slug=strtolower($name);$valid=true;
            foreach ([$slug.'.webp',$slug.'-still.webp',$name.'-preview.webp'] as $file) {
                $row=$m['assets'][$file]??null;$limit=str_ends_with($file,'-preview.webp')?16383:8388608;
                if (!is_array($row)||!is_int($row['size']??null)||$row['size']<13||$row['size']>$limit||
                    !is_string($row['sha256']??null)||!preg_match('/\A[a-f0-9]{64}\z/',$row['sha256'])||
                    is_link($dir.'/'.$file)||!is_file($dir.'/'.$file)||filesize($dir.'/'.$file)!==$row['size']) {$valid=false;break;}
            }
            // Content hashes are verified before deployment; rendering does not reread megabytes per request.
            if ($valid) $catalog[$name]='/static/operator-themes/'.$name.'-preview.webp';
        }
        return $catalog;
    }
}
