<?php
require_once __DIR__.'/../lib/service_search.php';
require_once __DIR__.'/../lib/luma_search.php';

/** Public LUMA results using the existing bounded transport and media relay. */
class luma extends service_search {
    protected const ORIGIN = luma_search::ORIGIN;
    protected const PATH = '/api/search';

    public function getfilters($page): array {
        return ['scope'=>['display'=>'LUMA search','option'=>['all'=>'Posts and accounts','posts'=>'Posts','accounts'=>'Accounts']],
            'feed'=>['display'=>'Public profile feed (@handle)','option'=>['all'=>'All posts','reels'=>'Reels']]];
    }

    private static function handle($value): ?string {
        if (!is_string($value)) return null;
        $value=strtolower($value);
        return preg_match('/\A[a-z0-9_](?:[a-z0-9_.]{0,28}[a-z0-9_])?\z/D',$value) ? $value : null;
    }

    public function image(array $get): array {
        $params=$this->parameters($get,'images');
        try { $query=luma_search::query($params['q'] ?? null); }
        catch (InvalidArgumentException $error) { throw new RuntimeException($error->getMessage()); }
        if ($query==='') throw new RuntimeException('Enter a public LUMA search.');
        $state=['q'=>$query];$path=self::PATH;$request=[];
        if (str_starts_with($query,'@')) {
            $handle=self::handle(substr($query,1));
            if ($handle===null) throw new RuntimeException('Enter a valid public LUMA @handle.');
            $path='/api/profile/'.$handle;
            $feed=$params['feed'] ?? $get['feed'] ?? 'all';
            if (!in_array($feed,['all','reels'],true)) throw new RuntimeException('Invalid LUMA profile feed.');
            $request=['feed'=>$feed];$state['feed']=$feed;
        } elseif (str_starts_with($query,'#')) {
            $tag=substr($query,1);
            if (!preg_match('/\A[\p{L}\p{N}_]{1,80}\z/uD',$tag)) throw new RuntimeException('Enter a valid public LUMA #tag.');
            $path='/api/tag/'.rawurlencode($tag);
        } else {
            $scope=$params['scope'] ?? $get['scope'] ?? 'all';
            if (!in_array($scope,['all','posts','accounts'],true)) throw new RuntimeException('Invalid LUMA search scope.');
            $request=['q'=>$query,'scope'=>$scope];$state['scope']=$scope;
        }
        if (isset($params['cursor'])) {
            if (!self::cursor($params['cursor'])) throw new RuntimeException('This LUMA page link has expired. Restart the search.');
            $request['cursor']=$params['cursor'];
        }
        $out=$this->decode($this->fetch_path($path,$request));
        $next=$out['cursor'];unset($out['cursor']);
        if ($next!==null && $next!==($params['cursor'] ?? null)) $out['npt']=$this->continuation($state+['cursor'=>$next],'images');
        return $out;
    }

    private static function cursor($value): bool {
        return is_string($value) && strlen($value)<=4096 && preg_match('/\A[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D',$value)===1;
    }

    private static function media($value): ?string {
        // Capabilities stay opaque; reject URLs, query parameters and traversal.
        return is_string($value) && preg_match('~\A/media/[A-Za-z0-9_-]{20,80}\z~D',$value) ? self::ORIGIN.$value : null;
    }

    public function decode(string $body): array {
        if (strlen($body)>2097152) throw new RuntimeException('LUMA returned an oversized response.');
        try { $data=json_decode($body,true,16,JSON_THROW_ON_ERROR); }
        catch (JsonException $error) { throw new RuntimeException('LUMA returned invalid JSON.'); }
        if (!is_array($data) || isset($data['error']) || ($data['source_kind'] ?? null)!=='live_instagram' ||
            ($data['live_instagram_verified'] ?? null)!==true || !is_array($data['items'] ?? null) ||
            !array_is_list($data['items']) || !is_array($data['profiles'] ?? null) || !array_is_list($data['profiles']) ||
            count($data['items'])+count($data['profiles'])>100 || !array_key_exists('next_cursor',$data) ||
            ($data['next_cursor']!==null && !self::cursor($data['next_cursor']))) {
            throw new RuntimeException('LUMA search is unavailable or its API format changed.');
        }
        $out=['status'=>'ok','npt'=>null,'image'=>[],'cursor'=>$data['next_cursor']];$seen=[];$usable=0;
        foreach ($data['profiles'] as $row) {
            if (!is_array($row) || !is_bool($row['private'] ?? null)) throw new RuntimeException('LUMA returned invalid account metadata.');
            if ($row['private']) {$usable++;continue;}
            $handle=self::handle($row['username'] ?? null);$photo_missing=($row['avatar'] ?? null)===null;
            $avatar=$photo_missing ? '/static/profile-placeholder.svg' : self::media($row['avatar']);
            if ($handle===null || $avatar===null || isset($seen['@'.$handle])) continue;
            $seen['@'.$handle]=true;$usable++;
            $out['image'][]=['title'=>self::text($row['name'] ?? '').' · @'.$handle.($photo_missing ? ' — Profile photo unavailable' : ''),
                'url'=>'/images?s='.rawurlencode('@'.$handle).'&scraper=luma',
                'source'=>[['url'=>$avatar,'width'=>null,'height'=>null]]];
        }
        foreach ($data['items'] as $row) {
            if (!is_array($row) || !is_array($row['media'] ?? null) || !array_is_list($row['media'])) continue;
            $handle=self::handle($row['username'] ?? null);$id=$row['shortcode'] ?? null;
            if ($handle===null || !is_string($id) || !preg_match('/\A[A-Za-z0-9_-]{5,64}\z/D',$id) || isset($seen[$id])) continue;
            foreach (array_slice($row['media'],0,32) as $medium) {
                if (!is_array($medium)) continue;
                $image=self::media($medium['image'] ?? null);
                if ($image===null) continue;
                $width=$medium['width'] ?? null;$height=$medium['height'] ?? null;
                $width=is_int($width)&&$width>0&&$width<=100000?$width:null;
                $height=is_int($height)&&$height>0&&$height<=100000?$height:null;
                $title=trim(self::text($row['caption'] ?? '',1024));
                $out['image'][]=['title'=>$title!==''?$title:'Public post by @'.$handle,
                    'url'=>'/images?s='.rawurlencode('@'.$handle).'&scraper=luma',
                    'source'=>[['url'=>$image,'width'=>$width,'height'=>$height]]];
                $seen[$id]=true;$usable++;break;
            }
        }
        if (($data['items']!==[] || $data['profiles']!==[]) && !$usable) throw new RuntimeException('LUMA returned no usable public image metadata.');
        return $out;
    }
}
