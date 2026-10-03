<?php

/** Explicit browser navigation to the operator-owned educational service. */
final class luma_search {
    public const ORIGIN = 'https://instalibre.securityops.co';

    public static function destination(array $get): string {
        $query = $get['s'] ?? '';
        if (!is_string($query) || strlen($query) > 640 ||
            preg_match('//u', $query) !== 1 ||
            preg_match('/\p{Cc}/u', $query) ||
            mb_strlen($query, 'UTF-8') > 160) {
            throw new InvalidArgumentException('LUMA accepts a search of up to 160 valid characters without control characters.');
        }
        $query = trim($query);
        return self::ORIGIN . '/' . ($query === '' ? '' : '?q=' . rawurlencode($query));
    }
}
