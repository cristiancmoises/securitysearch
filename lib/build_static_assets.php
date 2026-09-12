<?php
/** Build bounded gzip sidecars for fixed CSS/JS; never for visitor data.
 * CLI only. Sidecars live beside root-owned bundled resources, outside Git.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function securitysearch_asset_inputs(string $root): array {
    if (is_link($root) || !is_dir($root)) throw new RuntimeException('Unsafe asset root.');
    $paths=[]; $total=0;
    // Do not walk arbitrary subtrees, private artwork or user-controlled paths.
    foreach ([$root, $root.'/themes'] as $directory) {
        if (is_link($directory) || !is_dir($directory)) throw new RuntimeException('Unsafe asset directory.');
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isDot() || !preg_match('/\A[A-Za-z0-9][A-Za-z0-9_. -]*\.(css|js)\z/D', $file->getFilename())) continue;
            if ($file->isLink() || !$file->isFile()) throw new RuntimeException('Linked or nonregular static input.');
            $size=$file->getSize(); $total+=$size;
            if ($size>1048576 || $total>8388608 || count($paths)>=256) throw new RuntimeException('Static input budget exceeded.');
            $paths[]=$file->getPathname();
        }
    }
    sort($paths, SORT_STRING); return $paths;
}
function securitysearch_asset_clean(string $root): void {
    foreach ([$root, $root.'/themes'] as $directory) {
        if (is_link($directory) || !is_dir($directory)) throw new RuntimeException('Unsafe asset cleanup directory.');
        foreach (new DirectoryIterator($directory) as $file) {
            if (!preg_match('/\A[A-Za-z0-9][A-Za-z0-9_. -]*\.(css|js)\.pre\.gz\z/D', $file->getFilename())) continue;
            // unlink removes the sidecar itself, never a symlink target.
            if ((!$file->isFile() && !$file->isLink()) || !unlink($file->getPathname())) throw new RuntimeException('Could not remove stale sidecar.');
        }
    }
}
function securitysearch_asset_atomic(string $target,string $bytes): void {
    if (is_link($target)) throw new RuntimeException('Linked compressed output refused.');
    $tmp=tempnam(dirname($target),'.ss-asset-');
    if ($tmp===false) throw new RuntimeException('Cannot create asset temporary file.');
    try {
        if (file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes) || !chmod($tmp,0644)) throw new RuntimeException('Cannot persist compressed asset.');
        if (!rename($tmp,$target)) throw new RuntimeException('Cannot publish compressed asset.');
    } finally { if (is_file($tmp)) unlink($tmp); }
}
function securitysearch_build_static_assets(string $root): array {
    $paths=securitysearch_asset_inputs($root);
    // Preflight every output before changing any file.
    foreach ($paths as $path) if (is_link($path.'.pre.gz')) throw new RuntimeException('Linked compressed output refused.');
    if (!function_exists('gzencode') || !function_exists('gzdecode')) throw new RuntimeException('Native zlib unavailable.');
    securitysearch_asset_clean($root);
    $report=['schema'=>1,'files'=>[], 'input_bytes'=>0,'gzip_bytes'=>0];
    foreach ($paths as $path) {
        $data=file_get_contents($path);
        if (!is_string($data) || strlen($data)>1048576) throw new RuntimeException('Asset changed during preparation.');
        if (strlen($data)<256) continue;
        $gz=gzencode($data,9,ZLIB_ENCODING_GZIP);
        if (!is_string($gz) || gzdecode($gz)!==$data) throw new RuntimeException('Compression round-trip failed.');
        if (strlen($gz)>=strlen($data)) continue;
        securitysearch_asset_atomic($path.'.pre.gz',$gz);
        $report['files'][substr($path,strlen($root)+1)]=['sha256'=>hash('sha256',$data),'gzip_sha256'=>hash('sha256',$gz),'input_bytes'=>strlen($data),'gzip_bytes'=>strlen($gz)];
        $report['input_bytes']+=strlen($data);$report['gzip_bytes']+=strlen($gz);
    }
    return $report;
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    $root=dirname(__DIR__).'/static';
    try {
        if (count($argv)!==2 || !in_array($argv[1],['--build','--clean'],true)) throw new RuntimeException('Usage: php lib/build_static_assets.php --build|--clean');
        if ($argv[1]==='--clean') { securitysearch_asset_clean($root); echo "Prepared asset sidecars removed; original assets unchanged.\n"; }
        else { $r=securitysearch_build_static_assets($root);echo 'Prepared '.count($r['files']).' fixed CSS/JS variants; '.$r['input_bytes'].' source bytes / '.$r['gzip_bytes']." gzip bytes.\n"; }
    } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n");exit(1); }
}
