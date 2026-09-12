<?php
/** Bounded, server-rendered image cards; no browser script is required. */
final class image_results {
    public const PAGE_SIZE = 24;
    public const MAX_SOURCES = 32;

    public static function remote($url): bool {
        if (!is_string($url) || $url === '' || strlen($url)>8192) { return false; }
        $p=parse_url($url);
        return is_array($p) && isset($p['scheme'],$p['host']) && $p['host']!=='' &&
            in_array(strtolower($p['scheme']),['http','https'],true) && !isset($p['user']) && !isset($p['pass']);
    }

    public static function bounded(array $results): array {
        $images=is_array($results['image'] ?? null) ? $results['image'] : [];
        $omitted=max(0,count($images)-self::PAGE_SIZE);
        return ['image'=>array_slice($images,0,self::PAGE_SIZE), 'npt'=>is_string($results['npt'] ?? null) ? $results['npt'] : null, 'omitted'=>$omitted];
    }

    public static function items(frontend $frontend,array $get,array $results): array {
        $quality=in_array($get['quality'] ?? null,['high','original'],true) ? $get['quality'] : 'preview';
        $items=[];
        foreach (array_slice($results['image'] ?? [],0,self::PAGE_SIZE) as $image) {
            if (!is_array($image) || !is_array($image['source'] ?? null)) { continue; }
            $sources=[];$seen=[];
            foreach (array_slice($image['source'],0,self::MAX_SOURCES) as $source) {
                $url=is_array($source) ? ($source['url'] ?? null) : null;
                if (!self::remote($url) || isset($seen[$url])) { continue; }
                $seen[$url]=true;
                $width=filter_var($source['width'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
                $height=filter_var($source['height'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]);
                $sources[]=['url'=>$url,'width'=>$width && $height ? $width : 236,'height'=>$width && $height ? $height : 180,'known_size'=>(bool)($width && $height)];
            }
            if (!$sources) { continue; }
            $original=$sources[0];$thumb=self::thumbnail($sources);
            $display=$quality==='preview' ? $thumb : $original;
            $title=is_string($image['title'] ?? null) ? $image['title'] : 'Image result';
            $result=self::remote($image['url'] ?? null) ? $image['url'] : $original['url'];
            $host=parse_url($result,PHP_URL_HOST) ?: 'Source';
            $src=$frontend->htmlimage($display['url'],$quality==='preview' ? 'thumb' : $quality);
            $motion=self::remote($image['motion_url'] ?? null) ? $image['motion_url'] : $original['url'];
            $format=$frontend->animatedimageformat($motion);
            $hint=is_string($image['motion_format'] ?? null) ? strtoupper($image['motion_format']) : '';
            $selected=strtolower((string)($get['format'] ?? ''));
            $animation=$format!==null || in_array($hint,['GIF','WEBP','APNG'],true) || in_array($selected,['gif','webp','apng','6'],true) || ($get['type'] ?? '')==='animated';
            $motion_src=$animation ? $frontend->htmlimage($motion,'animated').'&preview=1' : null;
            if ($animation) $src=$frontend->htmlimage($quality==='preview' ? $thumb['url'] : $original['url'],'poster').($quality==='preview' ? '' : '&quality=high');
            $links=[['label'=>'Original','href'=>$frontend->htmlimage($original['url'],'original')]];
            if ($quality!=='preview') { $links[]=['label'=>'Preview','href'=>$frontend->htmlimage($thumb['url'],'thumb')]; }
            if ($animation) { $links[]=['label'=>'View animation','href'=>$frontend->htmlimage($motion,'animated')]; }
            $items[]=['original'=>$links[0]['href'],'preview'=>$src,'title'=>$title,'source'=>$result,'host'=>$host,
                'width'=>$display['width'],'height'=>$display['height'],'links'=>$links,'motion'=>$motion_src];
        }
        return $items;
    }

    /** Select only supplied URLs: no probe, request, cache or format inference.
     * The first valid source remains the original. Prefer a real preview over a
     * multi-megapixel original, even when that preview needs a little upscaling.
     * 236 x 180 is the proxy's fit-inside box, not a crop: reaching either axis
     * is sufficient. Both edges must exceed 16px to avoid tiny/strip placeholders.
     * Unknown sizes are hints, never proof of a usable image; the proxy remains
     * responsible for network, MIME, dimension and decoding validation.
     */
    private static function thumbnail(array $sources): array {
        $adequate=null;$unknown=null;$small=null;
        foreach (array_slice($sources,1) as $source) {
            if (!$source['known_size']) { $unknown=$source;continue; }
            $w=$source['width'];$h=$source['height'];
            if ($w<=16 || $h<=16) { continue; }
            $area=$w*$h;
            if ($w>=236 || $h>=180) {
                if ($adequate===null || $area<$adequate['width']*$adequate['height']) {
                    $adequate=$source;
                }
            } elseif ($small===null || $area>$small['width']*$small['height']) {
                $small=$source;
            }
        }
        // Keep the provider's last unknown-size preview hint, rather than
        // inventing dimensions or preferring a known tracking pixel.
        return $adequate ?? $unknown ?? $small ?? $sources[0];
    }

    public static function render(frontend $frontend,array $get,array $results): array {
        $escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
        $html='';$count=0;
        foreach (self::items($frontend,$get,$results) as $item) {
            $title=$item['title'];
            $html.='<article class="image-wrapper"><div class="image"><a class="thumb" href="'.$escape($item['original']).'" aria-label="'.$escape('Open original: '.$title).'">'.
                '<img src="'.$escape($item['preview']).'"'.($item['motion']===null ? '' : ' data-motion="'.$escape($item['motion']).'"').' alt="'.$escape($title).'" width="'.$item['width'].'" height="'.$item['height'].'" loading="'.($count<4 ? 'eager' : 'lazy').'" decoding="async" fetchpriority="'.($count===0 ? 'high' : ($count<4 ? 'auto' : 'low')).'">'.
                '</a><a href="'.$escape($item['source']).'" rel="noreferrer nofollow"><div class="title">'.$escape($item['host']).'</div><div class="description">'.$escape($title).'</div></a>'.
                '<div class="image-links">';
            foreach ($item['links'] as $link) { $html.='<a href="'.$escape($link['href']).'">'.$escape($link['label']).'</a>'; }
            $html.='</div></div></article>';$count++;
        }
        if ($count===0) {
            $html='<div class="images-empty"><h2>'.(($get['s'] ?? '')==='' ? 'Search images' : 'No images on this page').'</h2><p>'.
                (($get['s'] ?? '')==='' ? 'Enter a query, then choose a provider, format, view and quality.' : 'Try another provider or a broader query. Format filters can leave a page empty.').'</p></div>';
        }
        return [$html,$count];
    }
}
