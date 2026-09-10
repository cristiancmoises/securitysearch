<?php
require 'data/config.php';require 'lib/frontend.php';require 'scraper/reddit.php';
class pool_fixture extends reddit {
 public array $requests=[];public bool $failPrimary=true;
 protected function fetch_redlib(string $origin,string $path,array $params,int $deadline): string {
  $this->requests[]=[$origin,$path,$params,$deadline];
  if($this->failPrimary && $origin===service_pool::PRIMARY)throw new RuntimeException('Fixture unavailable');
  return str_replace(service_pool::PRIMARY,$origin,file_get_contents('tests/fixtures/redlib-news.html'));
 }
}
$p=new pool_fixture();$one=$p->news(['s'=>'GNU Guix','sort'=>'relevance']);
if(count($p->requests)!==2 || $one['_service']!==service_pool::FALLBACKS[0] || !str_starts_with($one['news'][0]['url'],service_pool::FALLBACKS[0].'/'))throw new RuntimeException('Fallback contract');
$p->failPrimary=false;$p->news(['s'=>'tampered','npt'=>$one['npt']]);
if(count($p->requests)!==3 || $p->requests[2][0]!==service_pool::FALLBACKS[0] || $p->requests[2][2]['q']!=='GNU Guix' || $p->requests[2][2]['after']!=='t3_next123')throw new RuntimeException('Continuation switched origin/query');
if(str_contains($p->requests[0][1],'http'))throw new RuntimeException('User-controlled route');
echo "PASS: real DOM parsing with offline failover transport; pinned authenticated continuation and source attribution.\n";
