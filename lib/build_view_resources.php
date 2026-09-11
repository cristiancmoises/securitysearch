<?php
/** Build only fixed, public, unrendered resources. No user/config values are rendered.
 * Run before Apache starts; the normal filesystem renderer remains the fallback.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../data/config.php';
require_once __DIR__.'/view_resources.php';

function build_view_resources(): string {
    $root = dirname(__DIR__); $inputs = []; $templates = []; $styles = []; $catalog = [];
    $read = static function(string $relative, int $maximum) use ($root, &$inputs): string {
        $path = $root.'/'.$relative;
        if (!str_starts_with(realpath($path) ?: '', $root.'/') || is_link($path) || !is_file($path))
            throw new RuntimeException('Unavailable/linked bundled resource: '.$relative);
        $n = filesize($path);
        if ($n === false || $n < 1 || $n > $maximum) throw new RuntimeException('Invalid bundled resource size: '.$relative);
        $text = file_get_contents($path);
        if (!is_string($text) || strlen($text) !== $n) throw new RuntimeException('Resource changed during build: '.$relative);
        $inputs[$relative] = hash('sha256', $text); return $text;
    };
    // No directory supplied by a request or an operator argument is traversed.
    foreach (['about','donate','header','header_nofilters','home','images','instances','search-actions','search'] as $name) {
        $text = $read('template/'.$name.'.html', 131072);
        $templates[$name.'.html'] = implode('', array_map('trim', explode("\n", $text)));
    }
    foreach (['base'=>'home-base.css','controls'=>'home-controls.css','black'=>'home-black.css'] as $name=>$file) {
        $css = $read('static/'.$file, 24576);
        if (stripos($css, '</style') !== false || stripos($css, '@import') !== false)
            throw new RuntimeException('Unsafe bundled stylesheet.');
        $styles[$name] = $css;
    }
    foreach (['Black','Tron','SecOps','Custom','Ajattix','Art','Art1','Art2','Art3','Arte',
              'Cat','Cat2','Gentoo','Kawaii','Lain','SecurityOps','Stop','Valerie'] as $name) {
        if (!is_file($root.'/static/themes/'.$name.'.css')) continue;
        $read('static/themes/'.$name.'.css', 65536);
        // Store a public preview URL, never its bytes or operator-only derivatives.
        $preview = 'static/theme-previews/'.$name.'.webp';
        if (is_link($root.'/'.$preview)) throw new RuntimeException('Linked preview refused.');
        $catalog[$name] = is_file($root.'/'.$preview) ? '/'.$preview : null;
    }
    $bundle = ['revision'=>view_resources::REVISION,'asset_version'=>config::VERSION,
               'inputs'=>$inputs,'templates'=>$templates,'styles'=>$styles,'catalog'=>$catalog];
    // var_export quotes PHP delimiters as data; never interpolate text into source.
    $source = "<?php\n// Generated at application startup. No visitor data. Do not edit.\nreturn ".var_export($bundle,true).";\n";
    if (strlen($source) > 262144) throw new RuntimeException('View bundle exceeds limit.');
    return $source;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if (count($argv)!==2 || !in_array($argv[1],['--build','--check'],true))
            throw new RuntimeException('Usage: php lib/build_view_resources.php --build|--check');
        $path = dirname(__DIR__).'/data/view-resources.generated.php';
        if (is_link(dirname($path)) || is_link($path)) throw new RuntimeException('Linked output refused.');
        $source = build_view_resources();
        if ($argv[1] === '--check') {
            if (!is_file($path) || file_get_contents($path) !== $source) throw new RuntimeException('View bundle is absent or stale.');
            echo "Bundled UI matches public source; no requests made.\n";
        } else {
            $temp = tempnam(dirname($path), '.view-resources-');
            if ($temp === false) throw new RuntimeException('Unable to allocate view bundle.');
            try {
                if (file_put_contents($temp,$source,LOCK_EX)!==strlen($source) || !chmod($temp,0644) || !rename($temp,$path))
                    throw new RuntimeException('Unable to publish view bundle.');
            } finally { if (is_file($temp)) unlink($temp); }
            echo "Prepared immutable UI resources; personalized HTML is not cached.\n";
        }
    } catch (Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
}
