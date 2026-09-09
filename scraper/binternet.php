<?php
require_once __DIR__ . '/../lib/service_search.php';

class binternet extends service_search {
    protected const ORIGIN = 'https://images.securityops.co';
    protected const PATH = '/search.php';

    public function getfilters($page): array {
        return ['format'=>['display'=>'Format on this page','option'=>[
            'any'=>'Any format','jpg'=>'JPEG','png'=>'PNG','gif'=>'GIF','webp'=>'WebP','avif'=>'AVIF','apng'=>'APNG'
        ]]];
    }

    public function image(array $get): array {
        $params=$this->parameters($get,'images');
        // Upstream Binternet bounds the HTML-escaped query to 64 bytes.
        if (strlen(htmlspecialchars($params['q'],ENT_QUOTES,'UTF-8'))>64) {
            throw new RuntimeException('Binternet accepts short searches (up to 64 bytes after escaping). Please shorten this query.');
        }
        $parsed=$this->decode($this->fetch($params),$get['format'] ?? 'any');
        $next=$parsed['continuation']; unset($parsed['continuation']);
        if ($next!==null) { $parsed['npt']=$this->continuation(['q'=>$params['q']]+$next,'images'); }
        return $parsed;
    }

    public function decode(string $body, string $format='any'): array {
        $old=libxml_use_internal_errors(true);
        try {
            $doc=new DOMDocument();
            if (!$doc->loadHTML('<?xml encoding="UTF-8">'.$body,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)) {
                throw new RuntimeException('Binternet returned an invalid page.');
            }
            $xp=new DOMXPath($doc);
            $containers=$xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," img-container ")]');
            if ($containers->length===0) { throw new RuntimeException('Binternet search is unavailable or its page format changed.'); }
            $out=['status'=>'ok','npt'=>null,'image'=>[],'continuation'=>null]; $seen=[];
            foreach ($xp->query('.//a[contains(concat(" ",normalize-space(@class)," ")," img-result ")]', $containers->item(0)) as $a) {
                if (count($out['image'])>=100) { break; }
                $parts=parse_url($a->getAttribute('href'));
                if (!is_array($parts) || ($parts['path'] ?? '')!=='/image_proxy.php' || isset($parts['host']) || isset($parts['scheme'])) { continue; }
                parse_str($parts['query'] ?? '',$query);
                $original=$query['url'] ?? null;
                if (!is_string($original) || strlen($original)>4096) { continue; }
                $url=parse_url($original);
                if (!is_array($url) || ($url['scheme'] ?? '')!=='https' || !in_array(strtolower($url['host'] ?? ''),['i.pinimg.com','pinimg.com'],true) ||
                    isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment']) || preg_match('/[\x00-\x20\x7f]/',$original)) { continue; }
                $ext=strtolower(pathinfo($url['path'] ?? '',PATHINFO_EXTENSION));
                if ($format!=='any' && !($ext===$format || ($format==='jpg' && $ext==='jpeg'))) { continue; }
                if (isset($seen[$original])) { continue; } $seen[$original]=true;
                $img=$a->getElementsByTagName('img')->item(0);
                $title=$img ? self::text($img->getAttribute('alt'),500) : '';
                $out['image'][]=['title'=>$title!=='' ? $title : 'Pinterest image',
                    'url'=>self::ORIGIN.'/image_proxy.php?url='.rawurlencode($original),
                    'source'=>[['url'=>$original,'width'=>null,'height'=>null]]];
            }
            foreach ($xp->query('//a[@href]') as $a) {
                if (stripos(trim($a->textContent),'Next page')!==0) { continue; }
                $p=parse_url($a->getAttribute('href'));
                if (!is_array($p) || ($p['path'] ?? '')!=='/search.php' || isset($p['host']) || isset($p['scheme'])) { continue; }
                parse_str($p['query'] ?? '',$query); $next=[];
                foreach (['bookmark','csrftoken','cursor'] as $key) {
                    if (isset($query[$key]) && is_string($query[$key]) && strlen($query[$key])<=16384 && !preg_match('/[\x00-\x1f\x7f]/',$query[$key])) { $next[$key]=$query[$key]; }
                }
                if (isset($next['bookmark']) || isset($next['cursor'])) { $out['continuation']=$next; break; }
            }
            return $out;
        } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
    }
}
