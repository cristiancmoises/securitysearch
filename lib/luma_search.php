<?php

/** Fixed origin and bounded input shared by the legacy route and LUMA adapter. */
final class luma_search {
    public const ORIGIN = 'https://instalibre.securityops.co';

    public static function query($query): string {
        if (!is_string($query) || strlen($query) > 640 ||
            preg_match('//u', $query) !== 1 ||
            preg_match('/\p{Cc}/u', $query) ||
            mb_strlen($query, 'UTF-8') > 100) {
            throw new InvalidArgumentException('LUMA accepts a search of up to 100 valid characters without control characters.');
        }
        $query = trim($query);
        return $query;
    }

    public static function destination(array $get): string {
        return '/images?s='.rawurlencode(self::query($get['s'] ?? '')).'&scraper=luma';
    }
}
