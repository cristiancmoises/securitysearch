<?php
/** Bounded CSE data decoding. Parses JSON, never evaluates upstream JavaScript. */
final class google_cse_protocol {
    private const LIMIT = 4194304;

    public static function response(string $payload): array {
        if (strlen($payload)>self::LIMIT) throw new upstream_search_failure('google','body_limit',200);
        // A BOM and bounded, inert comment prologues are data wrappers, not code.
        $text=ltrim(str_starts_with($payload,"\xEF\xBB\xBF") ? substr($payload,3) : $payload);
        $removed=0;
        for ($i=0; str_starts_with($text,'/*') && $i<4; $i++) {
            $end=strpos($text,'*/',2);
            if ($end===false || ($removed+=$end+2)>4096) throw new upstream_search_failure('google','format',200);
            $text=ltrim(substr($text,$end+2));
        }
        if (!preg_match('/\Agoogle\.search\.cse\.[A-Za-z0-9_]+\s*\(\s*/i',$text,$start) ||
            !preg_match('/\)\s*;?\s*\z/',$text,$end,PREG_OFFSET_CAPTURE)) {
            throw new upstream_search_failure('google','format',200);
        }
        $json=trim(substr($text,strlen($start[0]),$end[0][1]-strlen($start[0])));
        if (!str_starts_with($json,'{')) throw new upstream_search_failure('google','format',200);
        try {$data=json_decode($json,true,64,JSON_THROW_ON_ERROR);} catch (JsonException $e) {
            throw new upstream_search_failure('google','format',200);
        }
        if (!is_array($data) || (array_key_exists('results',$data) && (!is_array($data['results']) || !array_is_list($data['results'])))) {
            throw new upstream_search_failure('google','format',200);
        }
        return $data;
    }

    public static function script_path(string $html): ?string {
        if (strlen($html)>self::LIMIT) return null;
        // Support quote/spacing changes around the known assignment, not arbitrary script execution.
        if (!preg_match('/\brelativeUrl\s*=\s*([\'"])/',$html,$m,PREG_OFFSET_CAPTURE))return null;
        $quote=$m[1][0];$start=$m[0][1]+strlen($m[0][0]);$escaped=false;$end=null;
        for($i=$start,$max=min(strlen($html),$start+4097);$i<$max;$i++) {
            if($escaped){$escaped=false;continue;}
            if($html[$i]==='\\'){$escaped=true;continue;}
            if($html[$i]===$quote){$end=$i;break;}
        }
        if($end===null || !preg_match('/\A\s*;/',substr($html,$end+1,128)))return null;
        $value=substr($html,$start,$end-$start);
        $value=preg_replace_callback('/\\\\(?:x([0-9a-fA-F]{2})|u00([0-9a-fA-F]{2})|([\\\\\'"\/]))/',
            static fn($m)=>isset($m[3]) && $m[3]!=='' ? $m[3] : chr(hexdec(($m[1]??'')!==''?$m[1]:$m[2])),$value);
        if (!is_string($value) || str_contains($value,'\\') || preg_match('/[\x00-\x20\x7f]/',$value) ||
            !str_starts_with($value,'/cse.js') || str_starts_with($value,'//')) return null;
        $p=parse_url($value);
        if (!is_array($p) || ($p['path']??'')!=='/cse.js' || isset($p['host']) || isset($p['scheme']) || isset($p['fragment'])) return null;
        return $value;
    }

    public static function bootstrap(string $js): ?array {
        if (strlen($js)>self::LIMIT || !preg_match_all('/\}\s*\)\s*\(\s*(?=\{)/',$js,$matches,PREG_OFFSET_CAPTURE) || count($matches[0])>8) return null;
        foreach($matches[0] as [$marker,$offset]) {
            $begin=$offset+strlen($marker);$depth=0;$quoted=false;$escape=false;$end=null;
            for($i=$begin,$max=min(strlen($js),$begin+131072);$i<$max;$i++) {
                $c=$js[$i];
                if($quoted){if($escape){$escape=false;}elseif($c==='\\'){$escape=true;}elseif($c==='"'){$quoted=false;}continue;}
                if($c==='"'){$quoted=true;continue;}
                if($c==='{') {if(++$depth>64)break;}
                elseif($c==='}' && --$depth===0){$end=$i+1;break;}
            }
            if($end===null || !preg_match('/\A\s*\)\s*;/',substr($js,$end,128))) continue;
            try{$data=json_decode(substr($js,$begin,$end-$begin),true,64,JSON_THROW_ON_ERROR);}catch(JsonException $e){continue;}
            if(!is_array($data) || !is_string($data['cse_token']??null) || !is_string($data['cselibVersion']??null))continue;
            $token=$data['cse_token'];$lib=$data['cselibVersion'];
            if($token==='' || strlen($token)>4096 || preg_match('/[\x00-\x20\x7f]/',$token) || !preg_match('/\A[A-Za-z0-9_.-]{1,128}\z/',$lib))continue;
            return ['token'=>$token,'lib'=>$lib];
        }
        return null;
    }

    public static function text($value,int $limit=8192): string {
        if(!is_string($value)) return '';
        $value=substr($value,0,$limit);
        // Remove only a truncated trailing UTF-8 sequence; malformed content is omitted.
        for($i=0;$i<4;$i++) {if(preg_match('//u',$value))return trim($value);$value=substr($value,0,-1);}
        return '';
    }

    public static function url($url): ?string {
        if(!is_string($url)||$url===''||strlen($url)>16384||preg_match('/[\x00-\x20\x7f]/',$url))return null;
        $p=parse_url($url);
        if(!is_array($p)||!in_array(strtolower($p['scheme']??''),['http','https'],true)||empty($p['host'])||isset($p['user'])||isset($p['pass']))return null;
        return $url;
    }

    public static function dimension($n): ?int {
        if(!is_int($n) && !(is_string($n)&&preg_match('/\A[0-9]{1,7}\z/',$n)))return null;
        $n=(int)$n;return $n>0 && $n<=1000000?$n:null;
    }

    public static function web_result($row): ?array {
        if(!is_array($row))return null;
        $url=self::url($row['unescapedUrl']??null);if($url===null)return null;
        $title=self::text($row['titleNoFormatting']??null,2048);
        if($title==='')$title=html_entity_decode(strip_tags(self::text($row['title']??null,4096)),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        if($title==='')return null;
        $description=self::text($row['contentNoFormatting']??null);$date=null;
        $parts=explode('...',$description,2);
        if(count($parts)===2 && strlen($parts[0])<=80 && preg_match('/[0-9]/',$parts[0])){
            $parsed=strtotime($parts[0]);if($parsed!==false){$date=$parsed;$description=trim($parts[1]);}
        }
        $snippet=is_array($row['richSnippet']??null)?$row['richSnippet']:[];
        $thumb=['url'=>null,'ratio'=>null];
        foreach(['cseThumbnail','cseImage'] as $key){
            $image=is_array($snippet[$key]??null)?$snippet[$key]:[];
            $src=self::url($image['src']??null);if($src===null)continue;
            $width=self::dimension($image['width']??null);$height=self::dimension($image['height']??null);
            $meta=is_array($snippet['metatags']??null)?$snippet['metatags']:[];
            if($width===null||$height===null){$width=self::dimension($meta['ogImageWidth']??null);$height=self::dimension($meta['ogImageHeight']??null);}
            $ratio=($width!==null&&$height!==null)?$width/$height:1;
            $thumb=['url'=>$src,'ratio'=>$ratio>=1.5?'16:9':($ratio>=0.8?'1:1':'9:16')];break;
        }
        return ['title'=>rtrim($title,' .'),'description'=>trim($description,' .'),'url'=>$url,'date'=>$date,
            'type'=>'web','thumb'=>$thumb,'sublink'=>[],'table'=>[]];
    }

    /** Follow explicit numeric offsets only. An exact result count is not an end-of-list flag. */
    public static function next_start(array $data,int $current,int $result_count): ?int {
        if($result_count<1 || $current<0 || $current>=1000)return null;
        $cursor=$data['cursor']??null;
        if(!is_array($cursor)||!is_array($cursor['pages']??null)||count($cursor['pages'])>100)return null;
        $next=null;
        foreach($cursor['pages'] as $page){
            if(!is_array($page))continue;$n=$page['start']??null;
            if(!is_int($n)&&!(is_string($n)&&preg_match('/\A[0-9]{1,4}\z/',$n)))continue;
            $n=(int)$n;
            if($n>$current&&$n<=1000&&($next===null||$n<$next))$next=$n;
        }
        return $next;
    }
}
