<?php
/** Deployment-only selection: require both a parsed public feed and a keyword search.
 * The callback runs at the candidate, never on a visitor request. No result bodies
 * or query text are retained in the selection report. */
require_once __DIR__.'/service_pool.php';
final class redlib_selection {
    public static function choose(callable $probe): array {
        $report=['status'=>'unavailable','origin'=>null,'attempts'=>[]];
        foreach (array_values(array_unique(array_merge([service_pool::primary()],service_pool::allowed()))) as $origin) {
            $row=['origin'=>$origin,'feed_count'=>0,'search_count'=>0,'status'=>'unavailable'];
            $before=hrtime(true);
            try {
                foreach (['feed','search'] as $kind) {
                    $n=$probe($origin,$kind);
                    if (!is_int($n) || $n<1 || $n>25) throw new RuntimeException('No parsed Redlib results.');
                    $row[$kind.'_count']=$n;
                }
                $row['status']='ok';
            } catch (Throwable $e) { $row['error_class']=get_class($e); }
            $row['milliseconds']=round((hrtime(true)-$before)/1e6,1);
            $report['attempts'][]=$row;
            if ($row['status']==='ok') { $report['origin']=$origin;$report['status']='ok';break; }
        }
        return $report;
    }
}
