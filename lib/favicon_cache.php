<?php
/** Public favicon bytes only. No visitor identity, query, result cache or network I/O. */
final class favicon_cache {
    public const MAX_BYTES = 131072;
    public const MAX_DIMENSION = 256;
    public const MAX_CONDITION_BYTES = 4096;
    public const MAX_CONDITION_TAGS = 64;

    private static function regular($stat): bool {
        return is_array($stat) && ($stat['mode'] & 0170000) === 0100000 &&
            ($stat['nlink'] ?? 0) === 1 && is_int($stat['size'] ?? null) &&
            $stat['size'] > 0 && $stat['size'] <= self::MAX_BYTES;
    }

    private static function same($left, $right): bool {
        if (!is_array($left) || !is_array($right)) return false;
        foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime', 'nlink'] as $key) {
            if (($left[$key] ?? null) !== ($right[$key] ?? null)) return false;
        }
        return true;
    }

    /** A cache miss is safe: existing discovery/fallback handles it normally. */
    public static function read(string $directory, string $host): ?string {
        if (!preg_match('/\A[A-Za-z0-9.-]{1,253}\z/', $host)) return null;
        clearstatcache(true, $directory);
        $parent = @lstat($directory);
        if (!is_array($parent) || ($parent['mode'] & 0170000) !== 0040000) return null;
        $path = $directory . '/' . $host . '.png';
        clearstatcache(true, $path);
        $before = @lstat($path);
        if (!self::regular($before)) return null;
        $stream = @fopen($path, 'rb');
        if ($stream === false) return null;
        try {
            $opened = fstat($stream);
            if (!self::regular($opened) || !self::same($before, $opened)) return null;
            // Even a concurrent grow cannot cause an unbounded allocation.
            $bytes = stream_get_contents($stream, self::MAX_BYTES + 1);
            $after = fstat($stream);
            clearstatcache(true, $path);
            $named = @lstat($path);
            clearstatcache(true, $directory);
            $current_parent = @lstat($directory);
            if (!is_array($current_parent) || ($current_parent['mode'] & 0170000) !== 0040000 ||
                $parent['dev'] !== $current_parent['dev'] || $parent['ino'] !== $current_parent['ino'] ||
                !self::same($opened, $after) || !self::same($after, $named) ||
                !is_string($bytes) || strlen($bytes) !== $after['size']) return null;
        } finally {
            fclose($stream);
        }
        if (strlen($bytes) > self::MAX_BYTES || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) return null;
        $size = @getimagesizefromstring($bytes);
        if (!is_array($size) || ($size['mime'] ?? '') !== 'image/png' ||
            $size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) return null;
        return $bytes;
    }

    public static function etag(string $bytes): string {
        return 'W/"ss-icon-' . hash('sha256', $bytes) . '"';
    }

    /** Weak comparison for If-None-Match, with bounded full-field parsing.
     * Invalid/truncated/oversized conditions never suppress a representation.
     */
    public static function matches(string $field, string $etag): bool {
        if (strlen($field) > self::MAX_CONDITION_BYTES) return false;
        $field = trim($field, " \t");
        if ($field === '*') return true;
        if ($field === '') return false;
        $opaque = str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
        $offset = 0; $count = 0; $matched = false; $length = strlen($field);
        while ($offset < $length) {
            if (++$count > self::MAX_CONDITION_TAGS ||
                !preg_match('/\G[ \t]*(?:W\/)?("[\x21\x23-\x7e\x80-\xff]*")[ \t]*(,|$)/', $field, $parts, 0, $offset)) return false;
            $offset += strlen($parts[0]);
            $matched = hash_equals($opaque, $parts[1]) || $matched;
            if ($parts[2] === ',' && $offset === $length) return false;
        }
        return $matched;
    }

    /** Only safe cache revalidation; leave other preconditions to normal delivery. */
    public static function not_modified(array $server, string $etag): bool {
        if (!in_array($server['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) return false;
        foreach (['HTTP_IF_MATCH', 'HTTP_IF_UNMODIFIED_SINCE', 'HTTP_RANGE', 'HTTP_IF_RANGE'] as $key) {
            if (isset($server[$key])) return false;
        }
        $field = $server['HTTP_IF_NONE_MATCH'] ?? null;
        return is_string($field) && self::matches($field, $etag);
    }
}
