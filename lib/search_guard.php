<?php
require_once __DIR__.'/provider_http.php';
require_once __DIR__.'/service_pool.php';
/** Only definite native transport failures briefly cool a new search.
 * Invalid queries, parser errors, empty results and continuations are not cached.
 * No query/result/URL/IP is stored. Proxy configuration changes split the key. */
final class search_guard {
    public static function run(object $scraper,string $method,array $get,?callable $read=null,?callable $write=null): array {
        if (!in_array($method,['web','image','video','news','music'],true)) throw new InvalidArgumentException('Invalid search kind.');
        $read??=[service_pool::class,'read'];$write??=[service_pool::class,'write'];
        $egress=[];
        if (class_exists('config')) foreach ((new ReflectionClass('config'))->getConstants() as $name=>$value) {
            if (str_starts_with($name,'PROXY_') || str_starts_with($name,'SOURCE_IP_')) $egress[$name]=$value;
        }
        $key='securitysearch-transport-health-v1-'.hash('sha256',get_class($scraper).':'.$method.':'.serialize($egress));
        $fresh=empty($get['npt']);
        if ($fresh && $read($key)===true) throw new RuntimeException('This provider recently had a network failure. Retry in 8 seconds or select another provider.');
        if (in_array($method,['video','news','music'],true) && method_exists($scraper,'set_request_deadline')) $scraper->set_request_deadline(provider_http::deadline());
        try { return $scraper->$method($get); }
        catch (provider_http_failure $error) {
            if ($fresh) $write($key,true,8);
            throw $error;
        }
    }
}
