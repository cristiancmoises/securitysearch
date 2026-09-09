<?php
require_once __DIR__ . '/../lib/service_search.php';

/** News/community links from the operator's Redlib frontend, never direct Reddit. */
class reddit extends service_search {
    protected const ORIGIN='https://libre.securityops.co';
    private const FEED='/r/news+worldnews/new';
    private const SEARCH='/r/news+worldnews/search';

    public function getfilters($page): array {
        return ['sort'=>['display'=>'Sort (keyword search)','option'=>['new'=>'Newest','relevance'=>'Relevance','top'=>'Top']],
            'time'=>['display'=>'Period (keyword search)','option'=>['all'=>'Any time','day'=>'Today','week'=>'This week','month'=>'This month','year'=>'This year']]];
    }

    public function news(array $get): array {
        if (empty($get['npt'])) {
            $query=is_string($get['s'] ?? null) ? trim($get['s']) : '';
            if (strlen($query)>500) { throw new RuntimeException('Enter a search of up to 500 bytes.'); }
            $params=['q'=>$query,'sort'=>'new','t'=>'all'];
            foreach (['sort'=>'sort','time'=>'t'] as $filter=>$field) {
                if (isset($this->getfilters('news')[$filter]['option'][$get[$filter] ?? ''])) $params[$field]=$get[$filter];
            }
        } else { $params=$this->parameters($get,'news'); }
        $path=$params['q']==='' ? self::FEED : self::SEARCH;
        $request=$params;
        if ($params['q']==='') { unset($request['q'],$request['sort']); }
        else {
            $request+=['restrict_sr'=>'on','type'=>'link'];
            // Redlib treats these bare prefixes as navigation; quote them for search.
            if (preg_match('#\A(?:(?:https?://)?(?:www\.|old\.|new\.)?reddit\.com/)?(?:r|u|user)/#i',$request['q'])) $request['q']='"'.$request['q'].'"';
        }
        $parsed=$this->decode($this->fetch_path($path,$request));
        $after=$parsed['after'];unset($parsed['after']);
        if ($after!==null && $after!==($params['after'] ?? null)) {
            $params['after']=$after;$parsed['npt']=$this->continuation($params,'news');
        }
        return $parsed;
    }

    private static function permalink(string $value): ?string {
        if (strlen($value)>4096 || preg_match('/[\x00-\x20\x7f]/',$value)) return null;
        $p=parse_url($value);
        if (!is_array($p) || isset($p['user']) || isset($p['pass']) || isset($p['port']) || isset($p['query']) || isset($p['fragment'])) return null;
        if (isset($p['host']) && (($p['scheme'] ?? '')!=='https' || strtolower($p['host'])!=='libre.securityops.co')) return null;
        if (!isset($p['host']) && (isset($p['scheme']) || !str_starts_with($value,'/') || str_starts_with($value,'//'))) return null;
        $path=$p['path'] ?? '';
        if (!preg_match('#\A/r/([A-Za-z0-9_]+)/comments/([a-z0-9]+)(?:/([^/]+))?/?\z#iu',$path,$match)) return null;
        return self::ORIGIN.'/r/'.$match[1].'/comments/'.$match[2].(isset($match[3]) ? '/'.rawurlencode(rawurldecode($match[3])) : '').(str_ends_with($path,'/') ? '/' : '');
    }

    public function decode(string $body): array {
        $previous=libxml_use_internal_errors(true);
        try {
            $doc=new DOMDocument();
            if (!$doc->loadHTML('<?xml encoding="UTF-8">'.$body,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING)) throw new RuntimeException('Redlib returned an invalid page.');
            $xp=new DOMXPath($doc);
            $class=static fn($name)=>'contains(concat(" ",normalize-space(@class)," ")," '.$name.' ")';
            if ($xp->query('//*[@id="error"]')->length || !$xp->query('//*[@id="column_one"]')->length) throw new RuntimeException('Reddit news is temporarily unavailable through Redlib.');
            $cards=$xp->query('//*[@id="column_one"]//div['.$class('post').']');
            $out=['status'=>'ok','news'=>[],'npt'=>null,'after'=>null];$seen=[];
            foreach ($cards as $card) {
                if (count($out['news'])>=25) break;
                $title=null;$url=null;
                foreach ($xp->query('.//h2['.$class('post_title').']//a[@href]',$card) as $a) {
                    $url=self::permalink($a->getAttribute('href'));
                    if ($url!==null) { $title=self::text(trim($a->textContent),500);break; }
                }
                if (!$title || !$url || isset($seen[$url])) continue;
                $seen[$url]=true;
                $text=static function($query) use($xp,$card) { $node=$xp->query($query,$card)->item(0);return $node ? trim($node->textContent) : ''; };
                $sub=$text('.//a['.$class('post_subreddit').']');
                $author=$text('.//a['.$class('post_author').']');
                $date=null;$created=$xp->query('.//span['.$class('created').'][@title]',$card)->item(0);
                if ($created) {
                    $stamp=DateTimeImmutable::createFromFormat('!M d Y, H:i:s T',$created->getAttribute('title'),new DateTimeZone('UTC'));
                    if ($stamp && DateTimeImmutable::getLastErrors()===false) $date=$stamp->getTimestamp();
                }
                $out['news'][]=['title'=>$title,'url'=>$url,'description'=>self::text($text('.//div['.$class('post_body').']')),
                    'author'=>self::text(implode(' · ',array_filter([$sub,$author])),300),'date'=>$date,
                    'thumb'=>['url'=>null,'ratio'=>'16:9']];
            }
            if ($cards->length && !$out['news']) throw new RuntimeException('Redlib returned no recognizable news links.');
            if (!$cards->length && !$xp->query('//*[@id="posts"] | //*[@id="column_one"]//center[contains(.,"No posts were found")]')->length) throw new RuntimeException('Redlib news page format changed.');
            foreach ($xp->query('//*[@id="column_one"]//footer//a[@accesskey="N" or @accesskey="n"][@href]') as $a) {
                $href=preg_replace('/[\t\r\n]/','',$a->getAttribute('href'));
                if (strlen($href)>8192) continue;
                $p=parse_url($href);
                if (!is_array($p) || isset($p['scheme']) || isset($p['host']) || isset($p['user']) || isset($p['fragment']) ||
                    (isset($p['path']) && !in_array($p['path'],[self::FEED,self::SEARCH],true))) continue;
                parse_str($p['query'] ?? '',$query);
                if (is_string($query['after'] ?? null) && preg_match('/\At3_[a-z0-9]{1,16}\z/i',$query['after'])) { $out['after']=$query['after'];break; }
            }
            return $out;
        } finally { libxml_clear_errors();libxml_use_internal_errors($previous); }
    }
}
