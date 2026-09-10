<?php
/** Fixed Redlib inventory, not a user-supplied URL list. No query/result history. */
final class service_pool {
    public const PRIMARY='https://libre.securityops.co';
    public const FALLBACKS=['https://redlib.nadeko.net','https://redlib.privacyredirect.com'];

    public static function origins(): array {
        $enabled=!defined('config::REDLIB_FALLBACKS') || config::REDLIB_FALLBACKS===true;
        return $enabled ? array_merge([self::PRIMARY],self::FALLBACKS) : [self::PRIMARY];
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
