<?php
require_once __DIR__ . '/../lib/service_search.php';

/** Explicit DeviantArt selection via the operator's SkunkyArt JSON endpoint. */
class skunkyart extends service_search {
    protected const ORIGIN = 'https://skunkyart.securityops.co';
    protected const PATH = '/api/search';
    private const MAX_ITEMS = 100;

    public function getfilters($page): array {
        return [
            'orientation'=>['display'=>'Orientation','option'=>['any'=>'Any','landscape'=>'Landscape','portrait'=>'Portrait','square'=>'Square']],
            'ai'=>['display'=>'AI label (provider metadata)','option'=>['any'=>'Any','hide'=>'Hide labelled AI','only'=>'Only labelled AI']],
        ];
    }

    public function image(array $get): array {
        $params = $this->parameters($get, 'images');
        $q = $params['q'] ?? null;
        $page = $params['p'] ?? 1;
        if (!is_string($q) || trim($q)==='' || strlen($q)>500 || preg_match('//u',$q)!==1 || preg_match('/[\x00-\x1f\x7f]/',$q) ||
            !is_int($page) || $page<1 || $page>417) {
            throw new RuntimeException('SkunkyArt requires a valid search of 1–500 UTF-8 bytes. Restart this search.');
        }
        // Continuations carry the original filters; never a URL or credentials.
        $request = ['q'=>trim($q),'p'=>$page,'scope'=>'all','media'=>'image'];
        foreach (['orientation'=>['any','landscape','portrait','square'], 'ai'=>['any','hide','only']] as $key=>$allowed) {
            $value=$params[$key] ?? $get[$key] ?? 'any';
            if (!is_string($value) || !in_array($value,$allowed,true)) throw new RuntimeException('Invalid SkunkyArt filter.');
            $request[$key]=$value;
        }
        $mature = $params['mature'] ?? (($get['nsfw'] ?? 'no')==='yes' ? 'include' : 'hide');
        if (!in_array($mature,['hide','include'],true)) throw new RuntimeException('Invalid SkunkyArt content filter.');
        // Tightening Safe Search cannot be undone by an older page token.
        if (($get['nsfw'] ?? 'no')!=='yes') $mature='hide';
        $request['mature']=$mature;
        $out=$this->decode($this->fetch($request),$mature==='include');
        $more=$out['has_more']; unset($out['has_more']);
        if ($more && $page<417) {
            $request['p']=$page+1;
            $out['npt']=$this->continuation($request,'images');
        }
        return $out;
    }

    private static function label($value,int $limit=1024): string {
        if (!is_string($value)) return '';
        $text=substr($value,0,$limit);
        for($i=0;$i<4;$i++) {
            if(preg_match('//u',$text)===1) return trim($text);
            $text=substr($text,0,-1);
        }
        return '';
    }

    private static function media($value): ?string {
        if (!is_string($value) || strlen($value)>16384) return null;
        if (str_starts_with($value,self::ORIGIN)) $value=substr($value,strlen(self::ORIGIN));
        // Tokens are opaque. Never unwrap or fetch an upstream-supplied hostname.
        if (!preg_match('~\A/media\?t=[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z~D',$value)) return null;
        return self::ORIGIN.$value;
    }

    private static function artwork($value): ?string {
        if (!is_string($value) || strlen($value)>4096 || preg_match('/[\\\\\x00-\x20\x7f]/',$value)) return null;
        $p=parse_url($value);
        if (!is_array($p) || ($p['scheme']??'')!=='https' ||
            !in_array(strtolower($p['host']??''),['www.deviantart.com','deviantart.com'],true) ||
            isset($p['user']) || isset($p['pass']) || isset($p['port']) || isset($p['fragment']) ||
            !preg_match('~\A/[A-Za-z0-9_-]+/art/[^/]+\z~D',$p['path']??'')) return null;
        return $value;
    }

    public function decode(string $body,bool $allowMature=false): array {
        if (strlen($body)>2097152) throw new RuntimeException('SkunkyArt response exceeds the size limit.');
        try {$data=json_decode($body,true,16,JSON_THROW_ON_ERROR);} catch (JsonException $e) {
            throw new RuntimeException('SkunkyArt returned invalid JSON.');
        }
        if (!is_array($data) || isset($data['error']) || !is_array($data['items']??null) ||
            !array_is_list($data['items']) || !is_bool($data['has_more']??null) || count($data['items'])>self::MAX_ITEMS) {
            throw new RuntimeException('SkunkyArt search is unavailable or its API format changed.');
        }
        $out=['status'=>'ok','npt'=>null,'image'=>[],'has_more'=>$data['has_more']];$seen=[];$usable=0;
        foreach($data['items'] as $row) {
            if (!is_array($row) || !is_bool($row['mature']??null)) continue;
            $url=self::artwork($row['original_url']??null);
            $preview=self::media($row['preview']??null);$full=self::media($row['full']??null);
            if ($url===null || ($full===null && $preview===null)) continue;
            $usable++;
            if (!$allowMature && $row['mature']) continue;
            if (isset($seen[$url])) continue;$seen[$url]=true;
            $title=self::label($row['title']??null);
            $width=$row['width']??null;$height=$row['height']??null;
            $width=is_int($width)&&$width>0&&$width<=1000000?$width:null;
            $height=is_int($height)&&$height>0&&$height<=1000000?$height:null;
            $sources=[];
            if ($full!==null) $sources[]=['url'=>$full,'width'=>$width,'height'=>$height];
            if ($preview!==null && $preview!==$full) $sources[]=['url'=>$preview,'width'=>null,'height'=>null];
            $out['image'][]=['title'=>$title!==''?$title:'DeviantArt artwork','url'=>$url,'source'=>$sources];
        }
        if ($data['items']!==[] && !$usable) throw new RuntimeException('SkunkyArt returned no usable artwork metadata.');
        return $out;
    }
}
