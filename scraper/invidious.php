<?php
require_once __DIR__ . '/../lib/service_search.php';

class invidious extends service_search {
    protected const ORIGIN = 'https://invidious.securityops.co';
    protected const PATH = '/api/v1/search';

    public function getfilters($page): array {
        return [
            'sort'=>['display'=>'Sort','option'=>['relevance'=>'Relevance','date'=>'Upload date','views'=>'Views','rating'=>'Rating']],
            'date'=>['display'=>'Uploaded','option'=>['none'=>'Any time','hour'=>'Last hour','today'=>'Today','week'=>'This week','month'=>'This month','year'=>'This year']],
            'duration'=>['display'=>'Duration','option'=>['none'=>'Any duration','short'=>'Under 4 minutes','medium'=>'4–20 minutes','long'=>'Over 20 minutes']]
        ];
    }

    public function video(array $get): array {
        $params = $this->parameters($get, 'videos');
        if (empty($get['npt'])) {
            $params += ['page'=>1,'type'=>'video'];
            foreach ($this->getfilters('videos') as $key=>$filter) {
                $params[$key] = isset($filter['option'][$get[$key] ?? '']) ? $get[$key] : array_key_first($filter['option']);
            }
        }
        $results = $this->decode($this->fetch($params));
        if ($results['video']!==[] && ($params['page'] ?? 1)<100) {
            $params['page']++;
            $results['npt']=$this->continuation($params,'videos');
        }
        return $results;
    }

    public function decode(string $body): array {
        try { $data=json_decode($body,true,32,JSON_THROW_ON_ERROR); }
        catch (JsonException $error) { throw new RuntimeException('Invidious returned an invalid response. Use the direct search link.'); }
        if (!is_array($data) || !array_is_list($data)) { throw new RuntimeException('Invidious search API is unavailable.'); }
        $out=['status'=>'ok','npt'=>null,'video'=>[],'author'=>[],'livestream'=>[],'playlist'=>[],'reel'=>[]];
        foreach (array_slice($data,0,100) as $item) {
            if (!is_array($item) || ($item['type'] ?? '')!=='video' || !is_string($item['videoId'] ?? null) ||
                !preg_match('/\A[A-Za-z0-9_-]{11}\z/',$item['videoId'])) { continue; }
            $id=$item['videoId'];
            $out['video'][]=[
                'title'=>self::text($item['title'] ?? '',500),
                'description'=>self::text($item['description'] ?? ''),
                'url'=>self::ORIGIN.'/watch?v='.$id,
                'thumb'=>['url'=>self::ORIGIN.'/vi/'.$id.'/mqdefault.jpg','ratio'=>'16:9'],
                'author'=>['name'=>self::text($item['author'] ?? '',200)],
                'date'=>is_int($item['published'] ?? null) && $item['published']>0 ? $item['published'] : null,
                'duration'=>!empty($item['liveNow']) ? 'LIVE' : (is_int($item['lengthSeconds'] ?? null) ? max(0,$item['lengthSeconds']) : null),
                'views'=>is_int($item['viewCount'] ?? null) ? max(0,$item['viewCount']) : null
            ];
        }
        if ($data!==[] && $out['video']===[]) { throw new RuntimeException('Invidious returned no recognizable video records.'); }
        return $out;
    }
}
