<?php
require_once __DIR__.'/news_sources.php';
require_once __DIR__.'/provider_dns.php';
/** Bounded RSS parser. Metadata only: no article fetch, image load or external XML entity. */
final class news_rss {
    public const MAX_BYTES=1048576;
    public static function precheck(string $body): void {
        if($body==='' || strlen($body)>self::MAX_BYTES) throw new news_failure('body_limit');
        // Restrict to UTF-8 XML so UTF-16/NUL tricks cannot hide a DOCTYPE check.
        if(str_contains($body,"\0") || !preg_match('//u',$body) || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$body)) throw new news_failure('unsafe_xml');
        if(!preg_match('/<rss(?:\s|>)/i',$body) && news_failure::challenge($body)) throw new news_failure('challenge',200);
        if(preg_match('/<\?xml[^>]*encoding\s*=\s*["\x27]([^"\x27]+)["\x27]/i',$body,$m) && !in_array(strtoupper($m[1]),['UTF-8','UTF8','US-ASCII'],true)) throw new news_failure('unsafe_xml');
    }
    public static function link(string $url): ?string {
        if($url==='' || strlen($url)>4096 || preg_match('/[\x00-\x20\x7f\\\\]/',$url)) return null;
        $p=parse_url($url);
        if(!is_array($p) || ($p['scheme']??'')!=='https' || isset($p['user']) || isset($p['pass']) || isset($p['port']) || !isset($p['host'])) return null;
        $host=strtolower($p['host']);
        if(filter_var(trim($host,'[]'),FILTER_VALIDATE_IP)!==false) return null;
        if(filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)===false || !str_contains($host,'.') || str_ends_with($host,'.') || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid)$/',$host)) return null;
        return $url; // Display only. Never resolve or follow result URLs server-side.
    }
    public static function text(string $s,int $limit=500): string {
        $s=html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $s=preg_replace('/[\x00-\x1f\x7f]/',' ',$s);
        $s=preg_replace('/\s+/u',' ',$s)??'';
        return mb_strcut(trim($s),0,$limit,'UTF-8');
    }
    public static function decode(string $body): array {
        self::precheck($body);
        if(!class_exists('DOMDocument')) throw new RuntimeException('Native PHP DOM is required.');
        $before=libxml_use_internal_errors(true);
        try {
            $doc=new DOMDocument();$doc->resolveExternals=false;$doc->substituteEntities=false;$doc->validateOnParse=false;
            $flags=LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING|LIBXML_COMPACT;
            if(defined('LIBXML_NO_XXE')) $flags|=LIBXML_NO_XXE;
            if(!$doc->loadXML($body,$flags) || $doc->doctype!==null) throw new news_failure('invalid_xml');
            $xp=new DOMXPath($doc);
            if($doc->documentElement?->nodeName!=='rss' || $xp->query('/rss/channel')->length!==1 || $xp->query('/rss/channel/title')->length!==1) throw new news_failure('invalid_feed');
            // Hard structural limits complement the byte limit and libxml depth limit.
            if($xp->query('//*')->length>10000 || $xp->query('//*[namespace-uri()="http://www.w3.org/2001/XInclude"]')->length) throw new news_failure('unsafe_xml');
            $nodes=$xp->query('/rss/channel/item');
            if($nodes->length>200) throw new news_failure('body_limit');
            $out=['status'=>'ok','news'=>[],'npt'=>null];$seen=[];
            foreach($nodes as $node) {
                $get=static function(string $name)use($xp,$node):string {
                    $n=$xp->query('./*[local-name()="'.$name.'"]',$node)->item(0);return $n?$n->textContent:'';
                };
                $url=self::link(trim($get('link')));$title=self::text($get('title'));
                if($url===null || $title==='' || isset($seen[$url])) continue;
                $seen[$url]=true;
                $author=self::text($get('source')?:$get('Source'),200);
                $rawDate=trim($get('pubDate'));$date=null;
                if(strlen($rawDate)<=80 && preg_match('/^(?:[A-Z][a-z]{2}, )?\d{1,2} [A-Z][a-z]{2} \d{4} /',$rawDate)) {
                    $stamp=strtotime($rawDate);
                    if($stamp!==false && $stamp>=946684800 && $stamp<=time()+86400) $date=$stamp;
                }
                $out['news'][]=['title'=>$title,'url'=>$url,'description'=>null,'author'=>$author!==''?$author:null,'date'=>$date,'thumb'=>['url'=>null,'ratio'=>'16:9']];
                if(count($out['news'])===40) break;
            }
            if($nodes->length>0 && !$out['news']) throw new news_failure('invalid_results');
            return $out;
        } finally {libxml_clear_errors();libxml_use_internal_errors($before);}
    }
}
