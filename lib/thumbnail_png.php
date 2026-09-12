<?php
/** Conservative unchanged-byte path for already-small opaque PNG thumbnails.
 * This is deliberately NOT a general PNG decoder. Any unsupported feature goes
 * to the existing bounded ImageMagick path, including alpha, palettes, metadata,
 * interlace, other bit depths, animation and requested poster/high-size modes.
 */
final class thumbnail_png {
    public const MAX_BYTES = 32768;
    public const MAX_WIDTH = 236;
    public const MAX_HEIGHT = 180;
    private const MAX_CHUNKS = 128;

    public static function eligible(string $body): bool {
        $total = strlen($body);
        if ($total < 57 || $total > self::MAX_BYTES ||
            substr($body, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;
        $offset = 8; $chunks = 0; $width = 0; $height = 0; $channels = 0;
        $idat = ''; $seen_data = false;
        while ($offset + 12 <= $total && ++$chunks <= self::MAX_CHUNKS) {
            $size = unpack('N', substr($body, $offset, 4))[1];
            if ($size > $total - $offset - 12) return false;
            $kind = substr($body, $offset + 4, 4);
            // No ancillary/profile/text payload is relayed by this fast path.
            if (!in_array($kind, ['IHDR', 'IDAT', 'IEND'], true)) return false;
            $data = substr($body, $offset + 8, $size);
            if (!hash_equals(hash('crc32b', $kind.$data, true),
                             substr($body, $offset + 8 + $size, 4))) return false;
            $offset += $size + 12;
            if ($chunks === 1) {
                if ($kind !== 'IHDR' || $size !== 13) return false;
                $h = unpack('Nw/Nh/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', $data);
                if ($h['w'] < 1 || $h['w'] > self::MAX_WIDTH ||
                    $h['h'] < 1 || $h['h'] > self::MAX_HEIGHT ||
                    $h['depth'] !== 8 || !in_array($h['color'], [0, 2], true) ||
                    $h['compression'] !== 0 || $h['filter'] !== 0 || $h['interlace'] !== 0) return false;
                $width = $h['w']; $height = $h['h']; $channels = $h['color'] === 2 ? 3 : 1;
                continue;
            }
            if ($kind === 'IDAT') {
                $seen_data = true; $idat .= $data;
                continue;
            }
            if ($kind !== 'IEND' || $size !== 0 || !$seen_data || $offset !== $total || $idat === '') return false;
            // The small canvas fixes the inflate ceiling BEFORE decompression.
            $stride = 1 + $width * $channels; $expected = $stride * $height;
            if (!function_exists('gzuncompress') || !function_exists('inflate_init') ||
                !function_exists('inflate_get_read_len') || !function_exists('inflate_add') ||
                !function_exists('inflate_get_status')) return false;
            $raster = @gzuncompress($idat, $expected + 1);
            if (!is_string($raster) || strlen($raster) !== $expected) return false;
            for ($row = 0; $row < $height; $row++) {
                if (ord($raster[$row * $stride]) > 4) return false;
            }
            // Confirm one complete zlib stream, not a valid prefix plus hidden
            // trailing bytes. The first, capped pass already bounds this data.
            $context = @inflate_init(ZLIB_ENCODING_DEFLATE);
            if ($context === false) return false;
            $checked = @inflate_add($context, $idat, ZLIB_FINISH);
            return is_string($checked) && strlen($checked) === $expected &&
                inflate_get_status($context) === ZLIB_STREAM_END &&
                inflate_get_read_len($context) === strlen($idat);
        }
        return false;
    }
}
