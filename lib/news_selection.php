<?php
require_once __DIR__.'/news_sources.php';
/** Explicit operator probe: both stages must return real counts from the SAME source. */
final class news_selection {
    public static function choose(callable $probe, ?string $market=null): array {
        $market??=news_sources::market();
        if(!isset(news_sources::MARKETS[$market])) throw new news_failure('invalid_input');
        $report=['status'=>'unavailable','provider'=>'newswire','source'=>null,'market'=>$market,'attempts'=>[]];
        foreach(news_sources::order() as $source) {
            $row=['source'=>$source,'status'=>'unavailable','feed_count'=>null,'search_count'=>null,
                'stages'=>['feed'=>['status'=>'not_tested'],'search'=>['status'=>'not_tested']]];
            foreach(['feed','search'] as $kind) {
                $before=hrtime(true);
                try {
                    $n=$probe($source,$kind,$market);
                    if(!is_int($n) || $n<1 || $n>40) throw new news_failure('empty_probe');
                    $row[$kind.'_count']=$n;$stage=['status'=>'ok','count'=>$n];
                } catch(Throwable $e) {
                    $stage=['status'=>'unavailable','count'=>null]+news_failure::from($e)->report();
                }
                $stage['milliseconds']=round((hrtime(true)-$before)/1e6,1);$row['stages'][$kind]=$stage;
                if($stage['status']!=='ok') break; // No search after a refused/malformed feed.
            }
            if($row['stages']['feed']['status']==='ok' && $row['stages']['search']['status']==='ok') {
                $row['status']='ok';$report['status']='ok';$report['source']=$source;
            }
            $report['attempts'][]=$row;
            if($report['status']==='ok') break;
        }
        return $report;
    }
}
