<?php
/** Immutable, unrendered UI resources prepared at process startup.
 * Never contains request data, rendered pages, configuration secrets or pictures.
 * Only an explicit process environment flag enables the fixed local artifact.
 */
final class view_resources {
    public const REVISION = 'securitysearch-views-1';
    private static bool $loaded = false;
    private static ?array $bundle = null;

    private static function load(): ?array {
        if (self::$loaded) return self::$bundle;
        self::$loaded = true;
        if (getenv('SECURITYSEARCH_RENDER_BUNDLE') !== '1') return null;
        $path = dirname(__DIR__).'/data/view-resources.generated.php';
        if (is_link(dirname($path)) || is_link($path) || !is_file($path)) return null;
        $stat = stat($path);
        // This is deployment-generated code, like config.php; never load a
        // group/world-writable artifact or one outside the application's data dir.
        if ($stat === false || $stat['size'] < 100 || $stat['size'] > 262144 ||
            ($stat['mode'] & 0022) !== 0 || $stat['uid'] !== fileowner(__FILE__)) return null;
        try { $bundle = include $path; } catch (Throwable $error) { return null; }
        if (!is_array($bundle) || ($bundle['revision'] ?? null) !== self::REVISION ||
            ($bundle['asset_version'] ?? null) !== config::VERSION ||
            !is_array($bundle['templates'] ?? null) || !is_array($bundle['styles'] ?? null) ||
            !is_array($bundle['catalog'] ?? null) ||
            !is_string($bundle['templates']['home.html'] ?? null) ||
            !is_string($bundle['templates']['header.html'] ?? null) ||
            !is_string($bundle['styles']['base'] ?? null) ||
            !is_string($bundle['styles']['black'] ?? null) ||
            !is_string($bundle['styles']['controls'] ?? null)) return null;
        return self::$bundle = $bundle;
    }
    public static function mode(): string { return self::load() === null ? 'dynamic' : 'compiled'; }
    public static function template(string $name): ?string {
        $bundle = self::load(); $text = $bundle['templates'][$name] ?? null;
        return is_string($text) ? $text : null;
    }
    public static function style(string $name): ?string {
        $bundle = self::load(); $text = $bundle['styles'][$name] ?? null;
        return is_string($text) ? $text : null;
    }
    public static function catalog(): ?array {
        $bundle = self::load(); return $bundle === null ? null : $bundle['catalog'];
    }
}
