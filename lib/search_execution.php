<?php
require_once __DIR__.'/search_guard.php';
/** One honest, bounded fallback for a new Google web/image search. */
final class search_execution {
    public static function run(frontend $frontend,object &$scraper,array &$get,array &$filters,string $page,bool $append=false): array {
        $method=$page==='images' ? 'image' : 'web';
        $eligible=in_array($page,['web','images'],true) && ($get['scraper'] ?? '')==='google' && in_array($get['npt'] ?? false,[false,''],true) && !$append && trim($get['s'] ?? '')!=='';
        $start=hrtime(true);$deadline=$start+20000000000;
        if ($eligible && method_exists($scraper,'set_request_deadline')) $scraper->set_request_deadline($start+12000000000);
        try { return [search_guard::run($scraper,$method,$get),'']; }
        catch (Exception $original) {
            if (!$eligible) throw $original;
            $saved=$_GET;
            try {
                // Serialize existing dates before the usual whitelist parser.
                $_GET=$get;
                foreach ($filters as $key=>$filter) {
                    if (($filter['option'] ?? null)==='_DATE' && is_int($_GET[$key] ?? null)) $_GET[$key]=date('Y-m-d',$_GET[$key]);
                }
                $_GET['scraper']='brave';unset($_GET['npt'],$_GET['destination']);
                [$alternative,$alternative_filters]=$frontend->getscraperfilters($page);
                $alternative_get=$frontend->parsegetfilters($_GET,$alternative_filters);
                $remaining=min($deadline,hrtime(true)+8000000000);
                if ($remaining-hrtime(true)<100000000) throw new RuntimeException('Fallback budget exhausted.');
                if (method_exists($alternative,'set_request_deadline')) $alternative->set_request_deadline($remaining);
                $result=search_guard::run($alternative,$method,$alternative_get);
            } catch (Exception $fallback) {
                $_GET=$saved;
                throw new RuntimeException('Google and its Brave fallback could not complete this search. Try another provider or retry later.');
            }
            $get=$alternative_get;$filters=$alternative_filters;$scraper=$alternative;
            $_GET=$alternative_get;
            return [$result,'Google is temporarily unavailable. Results from Brave; provider-specific filters may differ.'];
        }
    }
}
