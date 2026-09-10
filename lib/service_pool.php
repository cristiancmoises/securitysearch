<?php
/** Fixed Redlib inventory, not a user-supplied URL list. No query/result history. */
final class service_pool {
    public const PRIMARY='https://redlib.privacyredirect.com';
    public const FALLBACKS=['https://redlib.nadeko.net','https://redlib.privadency.com'];

    /** Curated inventory, not an arbitrary operator/user-supplied URL. */
    public static function allowed(): array { return array_merge([self::PRIMARY],self::FALLBACKS); }
    public static function primary(): string {
        $origin=defined('config::REDLIB_PRIMARY') ? config::REDLIB_PRIMARY : self::PRIMARY;
        if (!is_string($origin) || !in_array($origin,self::allowed(),true))
            throw new RuntimeException('REDLIB_PRIMARY must be one of the approved external Redlib instances.');
        return $origin;
    }
    public static function origins(): array {
        $primary=self::primary();
        $enabled=!defined('config::REDLIB_FALLBACKS') || config::REDLIB_FALLBACKS===true;
        return $enabled ? array_values(array_unique(array_merge([$primary],self::allowed()))) : [$primary];
    }
    public static function disclosure(): string {
        $hosts=array_map(static fn($url)=>parse_url($url,PHP_URL_HOST),self::origins());
        return 'Reddit news uses '.$hosts[0].'. '.(count($hosts)>1 ?
            'After a failure, '.implode(' or ',array_slice($hosts,1)).' may receive the query. No background retry runs.' :
            'External instance fallback is disabled.');
    }
    public static function read(string $key) {
        return function_exists('apcu_enabled') && apcu_enabled() ? apcu_fetch($key) : false;
    }
    public static function write(string $key,$value,int $ttl): void {
        if (function_exists('apcu_enabled') && apcu_enabled()) apcu_store($key,$value,$ttl);
    }
    /** Callable accepts (origin, absolute monotonic deadline), returns parsed data.
     *  Continuations select exactly one origin; a new search can try at most three.
     *  Injectable clocks/store/transport permit genuinely offline failure tests. */
    public static function run(array $origins,callable $attempt,?callable $read=null,?callable $write=null,?callable $clock=null): array {
        $read??=[self::class,'read'];$write??=[self::class,'write'];$clock??=static fn()=>hrtime(true);
        foreach ($origins as $origin) if (!in_array($origin,self::origins(),true)) throw new InvalidArgumentException('Unapproved Redlib instance.');
        $until=$clock()+10000000000;$tried=0;
        foreach (array_slice(array_unique($origins),0,3) as $origin) {
            $key='securitysearch-redlib-health-v1-'.hash('sha256',$origin);
            if ($read($key)===true) continue;
            $now=$clock();if ($now>=$until-100000000) break;
            $tried++;
            try {
                $deadline=min($until,$now+3500000000);
                $data=$attempt($origin,$deadline);
                if ($clock()>$deadline) throw new RuntimeException('Redlib attempt exceeded its deadline.');
                if (!is_array($data)) throw new RuntimeException('Invalid Redlib result.');
                return ['origin'=>$origin,'data'=>$data,'attempts'=>$tried];
            } catch (Exception $error) {
                // No query strings, credentials or upstream bodies in this shared key.
                $write($key,true,20);
            }
        }
        throw new RuntimeException($tried===0 ? 'Redlib instances are briefly cooling down after failures. Retry in 20 seconds.' : 'The configured Redlib instances could not complete this request. Retry later or choose another news provider.');
    }
}
