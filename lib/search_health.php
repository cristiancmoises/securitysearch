<?php
/** Bounded, query-free health metadata for Google/Brave. No result cache. */
final class upstream_search_failure extends RuntimeException {
    public function __construct(
        public readonly string $provider,
        public readonly string $reason,
        public readonly int $http_status=0,
        public readonly int $curl_errno=0,
        public readonly int $retry_after=0
    ) {
        $label=['google'=>'Google','brave'=>'Brave'][$provider] ?? 'Provider';
        $text=[
            'rate_limited'=>'is temporarily unavailable because it is rate-limiting this instance',
            'refused'=>'is temporarily unavailable because it refused this request',
            'challenge'=>'requires human verification; no automated challenge retry was performed',
            'transport'=>'transport could not complete this search',
            'gateway'=>'is temporarily unavailable after an upstream gateway error',
            'redirect'=>'returned a redirect which was not followed',
            'body_limit'=>'response exceeded the safe size limit',
            'format'=>'returned an unsupported response format',
            'bootstrap_format'=>'returned an unsupported search-session format',
            'busy'=>'is preparing a search session for another request',
            'deadline'=>'search reached its request budget'
        ][$reason] ?? 'could not complete this search';
        parent::__construct($label.' '.$text.'.'.($retry_after>0 ? ' Retry in '.$retry_after.' seconds or choose another provider.' : ''));
    }
    public function diagnostic(): array {
        return ['provider'=>$this->provider,'reason'=>$this->reason,'http_status'=>$this->http_status,
            'curl_errno'=>$this->curl_errno,'retry_after'=>$this->retry_after];
    }
}
final class search_health {
    private static array $trace=[];
    public static function retry_after(string $value,?int $now=null): int {
        if (strlen($value)>80) return 0;
        if (preg_match('/\A[0-9]+\z/',$value)) return min(3600,(int)$value);
        if (!preg_match('/\A(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} GMT\z/',$value)) return 0;
        $t=strtotime($value);return $t===false ? 0 : max(0,min(3600,$t-($now ?? time())));
    }
    public static function key(string $provider,$egress): string {
        if (!in_array($provider,['google','brave'],true)) throw new InvalidArgumentException('Unknown provider.');
        $cx=$provider==='google' && defined('config::GOOGLE_CX_ENDPOINT') ? config::GOOGLE_CX_ENDPOINT : '';
        $ua=defined('config::USER_AGENT') ? config::USER_AGENT : '';
        return 'securitysearch-search-health-v1-'.hash('sha256',__DIR__.':'.$provider.':'.$cx.':'.$ua.':'.serialize($egress));
    }
    public static function remember(string $provider,$egress,upstream_search_failure $failure,?callable $write=null,?int $now=null): void {
        $ttl=match($failure->reason) {
            'rate_limited'=>max(60,min(3600,$failure->retry_after)),
            'refused','challenge'=>max(120,min(3600,$failure->retry_after)),
            'transport'=>5,
            'gateway'=>max(5,min(3600,$failure->retry_after)),
            default=>0
        };
        if ($ttl===0) return;
        $write??=static function($key,$value,$ttl){if(function_exists('apcu_enabled') && apcu_enabled())apcu_store($key,$value,$ttl);};
        $write(self::key($provider,$egress),['until'=>($now ?? time())+$ttl,'reason'=>$failure->reason,
            'http_status'=>$failure->http_status,'curl_errno'=>$failure->curl_errno],$ttl);
    }
    public static function check(string $provider,$egress,?callable $read=null,?int $now=null): void {
        $read??=static fn($key)=>function_exists('apcu_enabled') && apcu_enabled() ? apcu_fetch($key) : false;
        $row=$read(self::key($provider,$egress));$now??=time();
        if (!is_array($row) || !is_int($row['until']??null) || $row['until']<=$now || $row['until']>$now+3600 ||
            !in_array($row['reason']??'', ['rate_limited','refused','challenge','transport','gateway'],true) ||
            !is_int($row['http_status']??null) || $row['http_status']<0 || $row['http_status']>599 ||
            !is_int($row['curl_errno']??null) || $row['curl_errno']<0 || $row['curl_errno']>999) return;
        self::record($provider,'cooldown',$row['http_status'],$row['curl_errno'],0,0);
        throw new upstream_search_failure($provider,$row['reason'],$row['http_status'],$row['curl_errno'],$row['until']-$now);
    }
    public static function record(string $provider,string $stage,int $status,int $errno,int $bytes,float $ms): void {
        if (count(self::$trace)>=12) return;
        if(!in_array($provider,['google','brave'],true) || !in_array($stage,['transport','cooldown'],true))return;
        self::$trace[]=['provider'=>$provider,'stage'=>$stage,'http_status'=>$status,'curl_errno'=>$errno,
            'body_bytes'=>$bytes,'milliseconds'=>round(max(0,$ms),1)];
    }
    public static function trace(): array {return self::$trace;}
    public static function http_failure(string $provider,int $status,string $retry=''): upstream_search_failure {
        $kind=match(true){$status===429=>'rate_limited',in_array($status,[401,403,418],true)=>'refused',
            $status>=300 && $status<400=>'redirect',default=>'gateway'};
        return new upstream_search_failure($provider,$kind,$status,0,self::retry_after($retry));
    }
}
