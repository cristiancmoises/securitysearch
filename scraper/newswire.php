<?php
require_once __DIR__.'/../lib/news_http.php';
require_once __DIR__.'/../lib/service_pool.php';
/** Source-isolated RSS search. Only public no-query headlines may enter APCu. */
class newswire {
    public function getfilters($page): array {
        return ['source'=>['display'=>'News source','option'=>['auto'=>'Automatic RSS fallback','google'=>'Google News RSS','bing'=>'Bing News RSS']],
            'market'=>['display'=>'News edition','option'=>[news_sources::market()=>news_sources::market(),
                'en-US'=>'English · US','pt-BR'=>'Português · Brasil']]];
    }
    protected function read(string $key) {return service_pool::read($key);}
    protected function write(string $key,$value,int $ttl): void {service_pool::write($key,$value,$ttl);}
    protected function fetch(string $source,string $query,string $market,int $deadline): string {return news_http::fetch($source,$query,$market,$deadline);}
    protected function decode(string $body): array {
        try {return news_rss::decode($body);}
        catch(news_failure $e) {
            // Decode runs only after an HTTP-200 XML response passed transport policy.
            throw new news_failure($e->reason,$e->http_status??200,$e->curl_errno,$e->retry_after);
        }
    }
    protected function now(): int {return time();}
    protected function clock(): int {return hrtime(true);}
    protected function lock(string $key): bool {return !function_exists('apcu_enabled') || !apcu_enabled() || apcu_add($key,true,12);}
    protected function unlock(string $key): void {
        // Leave the bounded 12-second lease to expire. A slow DNS lookup can
        // outlive its lease; deleting here could remove a later worker's lock.
        // Successful refreshes are served from cache; failures enter cooldown.
    }

    public function probe(string $source,string $query,string $market): array {
        // Bypass caches and cooldowns only for an explicit operator CLI check.
        if(PHP_SAPI!=='cli') throw new RuntimeException('CLI only.');
        news_sources::order($source);news_sources::query($query);
        $until=$this->clock()+4500000000;
        $data=$this->decode($this->fetch($source,$query,$market,$until));
        if($this->clock()>$until) throw new news_failure('deadline');
        return $data;
    }
    public function news(array $get): array {
        if(!empty($get['npt'])) throw new news_failure('invalid_input'); // RSS has no authoritative next-page cursor.
        $query=news_sources::query($get['s']??'');$source=$get['source']??'auto';$market=$get['market']??news_sources::market();
        if(!is_string($source) || !is_string($market) || !isset(news_sources::MARKETS[$market])) throw new news_failure('invalid_input');
        $order=news_sources::order($source);$until=$this->clock()+9000000000;$last=null;
        foreach($order as $id) {
            $key='securitysearch-public-rss-v1-'.$id.'-'.$market;
            $cache=$query===''?$this->read($key):false;
            if(!is_array($cache) || !isset($cache['news'],$cache['_fetched_at']) || !is_array($cache['news']) || !is_int($cache['_fetched_at']) || $cache['_fetched_at']>$this->now() || $cache['_fetched_at']<$this->now()-600) $cache=false;
            if($cache && $cache['_fetched_at']>=$this->now()-120) { $cache['_cached']=true;return $cache; }
            $health='securitysearch-rss-health-v1-'.$id;
            $cooling=$this->read($health)===true;
            $lockKey=$key.'-lock';$locked=false;
            if(!$cooling && $query==='') $locked=$this->lock($lockKey);
            if($cooling || ($query===''&&!$locked)) {
                if($cache) {$cache['_stale']=true;$cache['_cached']=true;return $cache;}
                $last=new news_failure($cooling?'cooldown':'busy');continue;
            }
            try {
                $deadline=min($until,$this->clock()+4500000000);
                if($deadline<=$this->clock()) throw new news_failure('deadline');
                $data=$this->decode($this->fetch($id,$query,$market,$deadline));
                if($this->clock()>$deadline) throw new news_failure('deadline');
                $data['_source']=$id;$data['_market']=$market;$data['_cached']=false;$data['_stale']=false;$data['_fetched_at']=$this->now();
                if($query==='' && !empty($data['news'])) $this->write($key,$data,600);
                return $data; // Genuine empty searches succeed; never spray their query at another source.
            } catch(Exception $e) {
                $last=news_failure::from($e);$this->write($health,true,$last->cooldown());
                if($cache) {$cache['_stale']=true;$cache['_cached']=true;return $cache;}
            } finally {if($locked)$this->unlock($lockKey);}
        }
        throw $last??new news_failure('transport');
    }
}
