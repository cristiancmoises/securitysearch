<?php
/** Request-local transport budget for legacy adapters; no result/query cache. */
class provider_http_failure extends RuntimeException {}
final class provider_http {
    private static ?int $until = null;
    private static $share = null;

    private static function setting(string $key, int $default, int $min, int $max): int {
        $value = defined('config::'.$key) ? constant('config::'.$key) : $default;
        return is_int($value) ? max($min, min($max, $value)) : $default;
    }

    public static function deadline(): int {
        return self::$until ??= hrtime(true) + self::setting('PROVIDER_TOTAL_TIMEOUT_MS', 20000, 1000, 60000)*1000000;
    }

    public static function remaining_ms(): int {
        $remaining = (int)floor((self::deadline()-hrtime(true))/1000000);
        if ($remaining < 1) { throw new RuntimeException('The provider request time limit was reached. Retry later or choose another provider.'); }
        return $remaining;
    }

    public static function apply($handle): void {
        $remaining = self::remaining_ms();
        $timeout = min($remaining, self::setting('PROVIDER_TIMEOUT_MS', 12000, 1000, 30000));
        $connect = min($timeout, self::setting('PROVIDER_CONNECT_TIMEOUT_MS', 3000, 250, 10000));
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT_MS, $connect);
        curl_setopt($handle, CURLOPT_TIMEOUT_MS, $timeout);
        curl_setopt($handle, CURLOPT_NOSIGNAL, true);
        curl_setopt($handle, CURLOPT_TCP_KEEPALIVE, 1);
        // Share DNS and TLS session state only within this PHP request. Never
        // share cookies, credentials, response bodies or visitors' query text.
        if (function_exists('curl_share_init')) {
            if (self::$share === null) {
                self::$share = curl_share_init();
                curl_share_setopt(self::$share, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
                curl_share_setopt(self::$share, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
            }
            curl_setopt($handle, CURLOPT_SHARE, self::$share);
        }
    }

    public static function exec($handle) {
        self::apply($handle);
        $result = curl_exec($handle);
        if ($result === false && function_exists('curl_errno') && in_array(curl_errno($handle), [5,6,7,28,35,52,55,56,60], true)) {
            throw new provider_http_failure('The provider connection failed. Retry later or choose another provider.');
        }
        return $result;
    }
}
