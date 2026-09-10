<?php
// CLI only; explicit real external check during deployment, not an offline test.
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
chdir('/var/www/html/4get');
require 'data/config.php';require 'lib/redlib_selection.php';require 'scraper/reddit.php';
final class candidate_redlib extends reddit {
    public function probe(string $origin,string $kind): int {
        if (!in_array($origin,service_pool::allowed(),true)) throw new RuntimeException('Unapproved probe origin.');
        // Use precisely the same fixed route, cURL transport, SSRF rules and DOM parser
        // as visitor searches. Each call has a fresh bounded CLI request budget.
        (new ReflectionProperty('provider_http','until'))->setValue(null,null);
        $path=$kind==='feed' ? '/r/news+worldnews/new' : '/r/news+worldnews/search';
        $params=$kind==='feed' ? ['t'=>'all'] : ['q'=>'technology','sort'=>'new','t'=>'all','restrict_sr'=>'on','type'=>'link'];
        $body=$this->fetch_redlib($origin,$path,$params,hrtime(true)+3500000000);
        $parsed=$this->decode($body,$origin);
        return count($parsed['news']);
    }

}
ob_start();
try {
    $adapter=new candidate_redlib();
    $report=redlib_selection::choose([$adapter,'probe']);
} catch(Throwable $e) {
    $report=['status'=>'unavailable','origin'=>null,'error_class'=>get_class($e)];
}
ob_end_clean();
echo json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
