<?php
/**
 * Bounded admission for decorative favicon fetches.
 *
 * Search-result favicons are cosmetic. Slow or duplicated favicon work must not
 * consume every PHP worker and delay real searches. Only fixed-size hashes and
 * short-lived lease markers enter APCu; no query text, result URLs or bodies do.
 */
final class favicon_policy {
    public const REMOTE_BUDGET_NS = 2500000000; // 2.5 seconds total per favicon request.
    public const MAX_INFLIGHT = 4;
    public const LEASE_TTL = 12;
    public const FAILURE_TTL = 60;
    public const SUCCESS_BROWSER_TTL = 86400;
    public const FAILURE_BROWSER_TTL = 300;

    private static function apcu(): bool {
        return function_exists('apcu_enabled') && apcu_enabled() &&
            function_exists('apcu_fetch') && function_exists('apcu_add') && function_exists('apcu_delete') && function_exists('apcu_store');
    }

    private static function digest(string $host): string {
        return hash('sha256', strtolower($host));
    }

    private static function negative_key(string $host): string {
        return 'securitysearch-favicon-negative-v1-'.self::digest($host);
    }

    private static function host_key(string $host): string {
        return 'securitysearch-favicon-host-v1-'.self::digest($host);
    }

    private static function slot_key(int $slot): string {
        return 'securitysearch-favicon-slot-v1-'.$slot;
    }

    public static function is_negative(string $host): bool {
        if (!self::apcu()) return false;
        return apcu_fetch(self::negative_key($host)) === true;
    }

    public static function mark_failure(string $host): void {
        if (self::apcu()) apcu_store(self::negative_key($host), true, self::FAILURE_TTL);
    }

    public static function clear_failure(string $host): void {
        if (self::apcu()) apcu_delete(self::negative_key($host));
    }

    /** Return an opaque lease or null when another cosmetic fetch should win. */
    public static function acquire(string $host): ?array {
        if (!self::apcu()) return ['owner'=>'none','host'=>null,'slot'=>null];
        $owner=bin2hex(random_bytes(8));
        $host_key=self::host_key($host);
        if (!apcu_add($host_key,$owner,self::LEASE_TTL)) return null;
        for ($slot=0;$slot<self::MAX_INFLIGHT;$slot++) {
            $slot_key=self::slot_key($slot);
            if (apcu_add($slot_key,$owner,self::LEASE_TTL)) {
                return ['owner'=>$owner,'host'=>$host_key,'slot'=>$slot_key];
            }
        }
        if (apcu_fetch($host_key)===$owner) apcu_delete($host_key);
        return null;
    }

    /** Delete only leases still owned by this request. */
    public static function release(?array $lease): void {
        if (!self::apcu() || !is_array($lease) || ($lease['owner']??'')==='none') return;
        $owner=$lease['owner']??null;
        foreach (['slot','host'] as $field) {
            $key=$lease[$field]??null;
            if (is_string($key) && is_string($owner) && apcu_fetch($key)===$owner) apcu_delete($key);
        }
    }
}
