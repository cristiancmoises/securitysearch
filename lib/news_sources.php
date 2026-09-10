<?php
require_once __DIR__.'/news_failure.php';
/** Fixed public RSS routes. No destination or market string comes from a URL input. */
final class news_sources {
    public const ORIGINS=['google'=>'https://news.google.com','bing'=>'https://www.bing.com'];
    public const MARKETS=['en-US'=>['hl'=>'en-US','gl'=>'US','ceid'=>'US:en'],
        'pt-BR'=>['hl'=>'pt-BR','gl'=>'BR','ceid'=>'BR:pt-419']];
    public static function primary(): string {
        $id=defined('config::NEWS_RSS_PRIMARY') ? config::NEWS_RSS_PRIMARY : 'google';
        if(!is_string($id) || !isset(self::ORIGINS[$id])) throw new RuntimeException('Invalid NEWS_RSS_PRIMARY.');
        return $id;
    }
    public static function market(): string {
        $id=defined('config::NEWS_RSS_MARKET') ? config::NEWS_RSS_MARKET : 'en-US';
        if(!is_string($id) || !isset(self::MARKETS[$id])) throw new RuntimeException('Invalid NEWS_RSS_MARKET.');
        return $id;
    }
    public static function order(string $requested='auto'): array {
        if($requested!=='auto') {
            if(!isset(self::ORIGINS[$requested])) throw new news_failure('invalid_input');
            return [$requested];
        }
        return array_values(array_unique([self::primary(),...array_keys(self::ORIGINS)]));
    }
    public static function query($query): string {
        if(!is_string($query) || strlen($query)>500 || !preg_match('//u',$query) || preg_match('/[\x00-\x1f\x7f]/',$query)) throw new news_failure('invalid_input');
        return trim($query);
    }
    public static function url(string $id,string $query,string $market): string {
        if(!isset(self::ORIGINS[$id],self::MARKETS[$market])) throw new news_failure('invalid_input');
        $query=self::query($query);
        if($id==='google') {
            $params=self::MARKETS[$market];
            if($query!=='') $params=['q'=>$query]+$params;
            return self::ORIGINS[$id].($query===''?'/rss':'/rss/search').'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986);
        }
        return self::ORIGINS[$id].'/news/search?'.http_build_query([
            'q'=>$query===''?($market==='pt-BR'?'notícias':'world news'):$query,
            'format'=>'rss','mkt'=>$market,'setlang'=>$market,'count'=>40
        ],'','&',PHP_QUERY_RFC3986);
    }
    public static function label(string $id): string {return $id==='google'?'Google News RSS':($id==='bing'?'Bing News RSS':'News RSS');}
    public static function disclosure(string $requested='auto'): string {
        $order=self::order($requested);
        if(count($order)===1) return 'News RSS is publisher news, not Reddit. Only '.self::label($order[0]).' receives this search. Automatic fallback is disabled.';
        return 'News RSS is an independent news source, not Reddit. '.self::label($order[0]).
            ' receives this search; '.self::label($order[1]).' may receive it once if the first source fails. No background retry. Select a source to disable fallback.';
    }
}
