<?php
require_once __DIR__ . '/../lib/service_search.php';

/** Supports both the original Binternet markup and the SecurityOps 2026 UI. */
class binternet extends service_search {
    protected const ORIGIN = 'https://images.securityops.co';
    protected const PATH = '/search.php';
    private const QUERY_BYTES = 160;
    private const BOOKMARK_BYTES = 4096;

    public function getfilters($page): array {
        return ['format'=>['display'=>'Format on this page','option'=>[
            'any'=>'Any format','jpg'=>'JPEG','png'=>'PNG','gif'=>'GIF','webp'=>'WebP','avif'=>'AVIF','apng'=>'APNG'
        ]]];
    }

    public function image(array $get): array {
        $params=$this->parameters($get,'images');
        $q=$params['q'] ?? null;
        if (!is_string($q) || trim($q)==='' || strlen($q)>self::QUERY_BYTES || preg_match('//u',$q)!==1 || preg_match('/[\x00-\x1f\x7f]/',$q)) {
            throw new RuntimeException('Binternet accepts searches of 1–160 UTF-8 bytes. Please shorten this query.');
        }
        // Existing encrypted tokens can outlive an upgrade. Revalidate them,
        // and send only fields understood by the fixed Binternet endpoint.
        $request=['q'=>trim($q),'view'=>'grid','quality'=>'saver','scroll'=>'manual'];
        foreach (['bookmark','cursor','csrftoken'] as $key) {
            if (!isset($params[$key])) { continue; }
            if (!self::valid_cursor($params[$key])) { throw new RuntimeException('This Binternet page link has expired. Restart the search.'); }
            $request[$key]=$params[$key];
        }
        $parsed=$this->decode($this->fetch($request),is_string($get['format'] ?? null) ? $get['format'] : 'any');
        $next=$parsed['continuation']; unset($parsed['continuation']);
        if ($next!==null) {
            // A repeated cursor must not drive an infinite-scroll request loop.
            if (($next['bookmark'] ?? $next['cursor'] ?? null) !== ($request['bookmark'] ?? $request['cursor'] ?? null)) {
                $parsed['npt']=$this->continuation(['q'=>$request['q']]+$next,'images');
            }
        }
        return $parsed;
    }

    private static function valid_cursor($value): bool {
        return is_string($value) && $value!=='' && strlen($value)<=self::BOOKMARK_BYTES && !preg_match('/[\x00-\x1f\x7f]/',$value);
    }

    /** Route fragments only: no upstream-controlled host, traversal or redirect. */
    private static function route(string $href, string $page): ?array {
        if (strlen($href)>16384 || preg_match('/[\\\\\x00-\x20\x7f]/',$href)) { return null; }
        $p=parse_url($href);
        if (!is_array($p) || isset($p['host']) || isset($p['scheme']) || isset($p['user']) || isset($p['port']) || isset($p['fragment']) ||
            !in_array($p['path'] ?? '',[$page,'/'.$page],true)) { return null; }
        parse_str($p['query'] ?? '',$query);
        return $query;
    }

    private static function pin_image($url): bool {
        if (!is_string($url) || strlen($url)>4096 || preg_match('/[\\\\\x00-\x20\x7f]/',$url)) { return false; }
        $p=parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '')==='https' &&
            in_array(strtolower($p['host'] ?? ''),['i.pinimg.com','pinimg.com'],true) &&
            !isset($p['user']) && !isset($p['pass']) && !isset($p['port']) && !isset($p['fragment']);
    }

    private static function proxy_image(string $href): ?string {
        $q=self::route($href,'image_proxy.php');
        return $q!==null && self::pin_image($q['url'] ?? null) ? $q['url'] : null;
    }

    public function decode(string $body, string $format='any'): array {
        if (strlen($body)>2097152) { throw new RuntimeException('Binternet returned an oversized page.'); }
        $old=libxml_use_internal_errors(true);
        try {
            $doc=new DOMDocument();
            if (!$doc->loadHTML('<?xml encoding="UTF-8">'.$body,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)) {
                throw new RuntimeException('Binternet returned an invalid page.');
            }
            $xp=new DOMXPath($doc);
            $legacy='contains(concat(" ",normalize-space(@class)," ")," img-container ")';
            $modern='@id="image-gallery" and contains(concat(" ",normalize-space(@class)," ")," image-gallery ")';
            $containers=$xp->query('//*['.$legacy.' or ('.$modern.')]');
            if ($containers->length===0) { throw new RuntimeException('Binternet search is unavailable or its page format changed.'); }
            $out=['status'=>'ok','npt'=>null,'image'=>[],'continuation'=>null]; $seen=[];
            $anchors=$xp->query('.//a[contains(concat(" ",normalize-space(@class)," ")," img-result ") or contains(concat(" ",normalize-space(@class)," ")," image-link ")]',$containers->item(0));
            $valid=0;
            foreach ($anchors as $a) {
                if (count($out['image'])>=100) { break; }
                $original=self::proxy_image($a->getAttribute('href'));
                if ($original===null) { continue; }
                $valid++;
                $ext=strtolower(pathinfo(parse_url($original,PHP_URL_PATH) ?? '',PATHINFO_EXTENSION));
                if ($format!=='any' && !($ext===$format || ($format==='jpg' && $ext==='jpeg'))) { continue; }
                if (isset($seen[$original])) { continue; } $seen[$original]=true;
                $img=$a->getElementsByTagName('img')->item(0);
                $title=$img ? self::text($img->getAttribute('alt'),500) : '';
                $width=$img ? filter_var($img->getAttribute('width'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]) : false;
                $height=$img ? filter_var($img->getAttribute('height'),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>100000]]) : false;
                $sources=[['url'=>$original,'width'=>$width ?: null,'height'=>$height ?: null]];
                $preview=$img ? self::proxy_image($img->getAttribute('src')) : null;
                if ($preview!==null && $preview!==$original) {
                    $pw=null; $ph=null;
                    if ($width && $height && preg_match('~^/(\d{2,4})x/~',parse_url($preview,PHP_URL_PATH) ?? '',$m)) {
                        $pw=min((int)$m[1],$width); $ph=max(1,(int)round($height*$pw/$width));
                    }
                    $sources[]=['url'=>$preview,'width'=>$pw,'height'=>$ph];
                }
                $out['image'][]=['title'=>$title!=='' ? $title : 'Pinterest image',
                    'url'=>self::ORIGIN.'/image_proxy.php?url='.rawurlencode($original),'source'=>$sources];
            }
            if ($anchors->length>0 && $valid===0) { throw new RuntimeException('Binternet returned no usable image links. The service page may have changed.'); }
            foreach ($xp->query('//a[@href]') as $a) {
                $rel=preg_split('/\s+/',strtolower(trim($a->getAttribute('rel'))));
                if (!in_array('next',$rel,true) && $a->getAttribute('id')!=='next-page' && stripos(trim($a->textContent),'Next page')!==0) { continue; }
                $query=self::route($a->getAttribute('href'),'search.php');
                if ($query===null) { continue; }
                $next=[];
                foreach (['bookmark','csrftoken','cursor'] as $key) {
                    if (self::valid_cursor($query[$key] ?? null)) { $next[$key]=$query[$key]; }
                }
                if (isset($next['bookmark']) || isset($next['cursor'])) { $out['continuation']=$next; break; }
            }
            return $out;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    }
}
