<?php
/** Public-IP-only DNS metadata cache. No paths, queries, cookies or results.
 * Shared entries are limited to nine fixed service/CDN names, for at most 15 s.
 * Other names are memoized only within one PHP request. Connections remain pinned.
 */
final class provider_dns {
    private const SHARED = ['redlib.privadency.com','redlib.nadeko.net',
        'redlib.privacyredirect.com','images.securityops.co','invidious.securityops.co',
        'i.pinimg.com','pinimg.com','news.google.com','www.bing.com'];
    private static array $local = [];

    public static function public_ip(string $address): bool {
        $packed = @inet_pton($address);
        if ($packed === false ||
            (strlen($packed) === 4 && ord($packed[0]) >= 224) ||
            (strlen($packed) === 16 && (ord($packed[0]) === 255 ||
                substr($packed,0,12) === str_repeat("\0",10)."\xff\xff" ||
                substr($packed,0,4) === "\x00\x64\xff\x9b" ||
                substr($packed,0,2) === "\x20\x02" || substr($packed,0,4) === "\x20\x01\0\0"))) return false;
        return filter_var($address, FILTER_VALIDATE_IP,
            FILTER_FLAG_GLOBAL_RANGE | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private static function valid($ips): bool {
        if (!is_array($ips) || !$ips || count($ips) > 32 || !array_is_list($ips)) return false;
        foreach ($ips as $ip) if (!is_string($ip) || !self::public_ip($ip)) return false;
        return true;
    }

    private static function native(string $host): array {
        $ips = []; $ttl = 15;
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) foreach ($records as $record) {
            if (isset($record['ip']) || isset($record['ipv6'])) {
                $ips[] = $record['ip'] ?? $record['ipv6'];
                $ttl = min($ttl, max(0, (int)($record['ttl'] ?? 0)));
            }
        }
        if (!$ips) {
            $ips = @gethostbynamel($host.'.') ?: [];
            $ttl = 5; // gethostbynamel supplies no DNS TTL.
        }
        return ['ips'=>array_values(array_unique($ips)), 'ttl'=>$ttl];
    }

    public static function lookup(string $host, ?callable $resolver=null, ?callable $read=null,
                                  ?callable $write=null, ?callable $clock=null): array {
        $host = strtolower(rtrim($host, '.'));
        if (strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) return [];
        $production = $resolver === null && $read === null && $write === null && $clock === null;
        $clock ??= static fn()=>time(); $now = $clock();
        if ($production && isset(self::$local[$host]) && self::$local[$host]['until'] > $now) return self::$local[$host]['ips'];
        $resolver ??= [self::class, 'native'];
        $read ??= static fn($key)=>function_exists('apcu_enabled') && apcu_enabled() ? apcu_fetch($key) : false;
        $write ??= static function($key,$row,$ttl) {if (function_exists('apcu_enabled') && apcu_enabled()) apcu_store($key,$row,$ttl);};
        $shared = in_array($host, self::SHARED, true);
        $key = 'securitysearch-dns-v1-'.hash('sha256',$host);
        $row = $shared ? $read($key) : false;
        if (!is_array($row) || !is_int($row['until'] ?? null) || $row['until'] <= $now ||
            $row['until'] > $now+15 || !self::valid($row['ips'] ?? null)) {
            $answer = $resolver($host);
            $ips = $answer['ips'] ?? [];
            if (!self::valid($ips)) return []; // Mixed public/private answers fail closed; no shared negative cache.
            $ttl = is_int($answer['ttl'] ?? null) ? max(0,min(15,$answer['ttl'])) : 0;
            $row = ['ips'=>array_values(array_unique($ips)), 'until'=>$now+$ttl];
            if ($shared && $ttl > 0) $write($key, $row, $ttl);
        }
        if ($production && count(self::$local) < 64) self::$local[$host] = $row;
        return $row['ips'];
    }
}
