<?php
/** Build the anonymous, query-free homepage served by Apache's static fast path.
 *
 * This artifact contains only the same public HTML an ordinary GET / with no
 * Cookie/Authorization/query would render.  It is generated locally at container
 * startup and after the optional public Tranco refresh.  Search text, cookies,
 * visitor data, credentials and private theme artwork are never inputs.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/../data/config.php';
require_once __DIR__.'/page_renderer.php';

function securitysearch_home_snapshot_path(): string {
    return dirname(__DIR__).'/home-anonymous.generated.fast';
}

function securitysearch_home_snapshot_gzip_path(): string {
    return securitysearch_home_snapshot_path().'.gz';
}

function securitysearch_publish_home_artifact(string $target, string $bytes): void {
    $root=dirname(__DIR__);
    if (is_link($target)) throw new RuntimeException('Refused linked anonymous-home artifact.');
    $tmp=tempnam($root,'.home-anonymous-');
    if ($tmp===false) throw new RuntimeException('Cannot allocate anonymous-home artifact.');
    try {
        if (file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes))
            throw new RuntimeException('Cannot write complete anonymous-home artifact.');
        if (!chmod($tmp,0644)) throw new RuntimeException('Cannot secure anonymous-home artifact.');
        if (!rename($tmp,$target)) throw new RuntimeException('Cannot publish anonymous-home artifact.');
    } finally {
        if (is_file($tmp)) @unlink($tmp);
    }
    clearstatcache(true,$target);
    $st=stat($target);
    if ($st===false || ($st['mode']&0022)!==0 || $st['size']!==strlen($bytes))
        throw new RuntimeException('Anonymous-home artifact validation failed.');
}

function securitysearch_build_home_snapshot(): string {
    $root=dirname(__DIR__);
    $target=securitysearch_home_snapshot_path();
    if (is_link($target)) throw new RuntimeException('Refused linked anonymous-home artifact.');

    // Snapshot semantics are deliberately identical to an anonymous GET /.
    $saved_cookie=$_COOKIE ?? [];
    $saved_server=$_SERVER ?? [];
    $_COOKIE=[];
    $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['SCRIPT_NAME']='/index.php';
    unset($_SERVER['HTTP_AUTHORIZATION'],$_SERVER['QUERY_STRING']);
    try {
        $frontend=new page_renderer();
        $html=$frontend->load('home.html',[
            'server_short_description'=>htmlspecialchars(config::SERVER_SHORT_DESCRIPTION,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')
        ]);
    } finally {
        $_COOKIE=$saved_cookie;
        $_SERVER=$saved_server;
    }

    if (!is_string($html) || strlen($html)<4096 || strlen($html)>196608)
        throw new RuntimeException('Anonymous homepage artifact has an invalid size.');
    foreach (['Security Search','In Code We Trust.','data-home-style="black"'] as $marker)
        if (!str_contains($html,$marker)) throw new RuntimeException('Anonymous homepage is missing a required marker.');
    if (stripos($html,'<script')!==false || str_contains($html,'<?'))
        throw new RuntimeException('Anonymous Black homepage unexpectedly contains executable markup.');
    if (preg_match('/\{\%[a-z0-9_-]+\%\}/i',$html))
        throw new RuntimeException('Anonymous homepage contains an unresolved template marker.');

    securitysearch_publish_home_artifact($target,$html);

    // Pre-compress the immutable anonymous document once at startup.  Apache can
    // serve this exact gzip stream to clients that advertise gzip support, avoiding
    // per-request compression work while retaining the plain representation for
    // clients that do not.  Search/personalized responses never use this artifact.
    $gzip_target=securitysearch_home_snapshot_gzip_path();
    if (is_link($gzip_target)) throw new RuntimeException('Refused linked compressed anonymous-home artifact.');
    if (function_exists('gzencode')) {
        $gzip=gzencode($html,6,ZLIB_ENCODING_GZIP);
        if (!is_string($gzip) || strlen($gzip)<64 || strlen($gzip)>=strlen($html))
            throw new RuntimeException('Anonymous-home gzip artifact validation failed.');
        securitysearch_publish_home_artifact($gzip_target,$gzip);
    } else {
        @unlink($gzip_target); // Plain static fast path remains fully functional.
    }
    return $target;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    if (($argv[1] ?? '')!=='--build') { fwrite(STDERR,"Usage: php lib/build_home_snapshot.php --build\n"); exit(2); }
    try {
        $path=securitysearch_build_home_snapshot();
        echo 'Anonymous homepage prepared: '.basename($path).PHP_EOL;
    } catch (Throwable $e) {
        fwrite(STDERR,$e->getMessage().PHP_EOL); exit(1);
    }
}
