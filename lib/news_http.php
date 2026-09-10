<?php
require_once __DIR__.'/curlproxy.php';
require_once __DIR__.'/provider_http.php';
require_once __DIR__.'/news_sources.php';
require_once __DIR__.'/news_rss.php';
/** Reuse validated public-IP resolution, pinned cURL transport, TLS and response caps. */
final class news_http {
    public static function fetch(string $source,string $query,string $market,int $deadline): string {
        $url=news_sources::url($source,$query,$market);
        $budget=(object)['deadline'=>min($deadline,provider_http::deadline()),
            'remaining_bytes'=>news_rss::MAX_BYTES,'remaining_wire_bytes'=>news_rss::MAX_BYTES,
            'no_redirect'=>true];
        try {$r=(new proxy(false))->get($url,proxy::req_web,true,null,4,news_rss::MAX_BYTES,$budget);}
        catch(Exception $e) {throw news_failure::from($e);}
        return self::response($r);
    }
    /** Pure response policy used after the bounded, pinned transport. */
    public static function response(array $r): string {
        $status=$r['http']['code']??0;
        $retry=self::retry_after($r['headers']['retry-after']??'');
        if($status!==200) throw news_failure::http((int)$status,$retry);
        $body=$r['body']??null;
        if(!is_string($body) || $body==='' || strlen($body)>news_rss::MAX_BYTES) throw new news_failure('body_limit',200);
        if(!preg_match('/<rss(?:\s|>)/i',$body) && news_failure::challenge($body)) throw new news_failure('challenge',200);
        $type=strtolower(trim(explode(';',$r['headers']['content-type']??'',2)[0]));
        if(!in_array($type,['application/rss+xml','application/xml','text/xml','application/rdf+xml'],true)) throw new news_failure('content_type',200);
        return $body;
    }
    public static function retry_after(string $value): int {
        if(strlen($value)>80) return 0;
        // Retry-After delta-seconds are ASCII digits; do not require ext-ctype.
        if(preg_match('/\A[0-9]+\z/',$value)===1) return min(3600,(int)$value);
        if(!preg_match('/\A(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} GMT\z/',$value)) return 0;
        $t=strtotime($value);return $t===false?0:max(0,min(3600,$t-time()));
    }
}
