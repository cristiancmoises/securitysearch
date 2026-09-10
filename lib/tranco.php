<?php
/** Tranco metadata never performs network work while serving a visitor. */
final class securitysearch_tranco {
    public const DOMAIN='securityops.co';
    public static function path(): string {
        return sys_get_temp_dir().'/securitysearch-tranco-'.substr(hash('sha256',__DIR__),0,12).'.json';
    }
    public static function parse(string $body,?int $now=null): array {
        $now??=time();
        if (strlen($body)>65536) throw new RuntimeException('Tranco response too large.');
        $data=json_decode($body,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['ranks']) || !is_array($data['ranks']) || count($data['ranks'])>400) throw new RuntimeException('Invalid Tranco response.');
        $rows=[];
        foreach ($data['ranks'] as $row) {
            if (!is_array($row) || !is_string($row['date'] ?? null) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$row['date']) || !is_int($row['rank'] ?? null) || $row['rank']<1 || $row['rank']>100000000) continue;
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$row['date'],new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d')!==$row['date'] || $date->getTimestamp()>$now || $date->getTimestamp()<$now-45*86400) continue;
            $rows[$row['date']]=$row['rank'];
        }
        krsort($rows);
        if (!$rows && $data['ranks']!==[]) throw new RuntimeException('No valid recent Tranco entries.');
        return ['domain'=>self::DOMAIN,'fetched_at'=>$now,'date'=>$rows ? array_key_first($rows) : null,'rank'=>$rows ? reset($rows) : null];
    }
    public static function cached(): ?array {
        $path=self::path();
        if (is_link($path) || !is_file($path) || filesize($path)>1024) return null;
        $raw=@file_get_contents($path);if ($raw===false) return null;
        $row=json_decode($raw,true);
        if (!is_array($row) || ($row['domain'] ?? '')!==self::DOMAIN || !is_int($row['fetched_at'] ?? null) || $row['fetched_at']>time() || $row['fetched_at']<time()-3*86400) return null;
        if (!array_key_exists('rank',$row) || !array_key_exists('date',$row)) return null;
        if ($row['rank']===null) { if ($row['date']!==null) return null; }
        else {
            if (!is_int($row['rank']) || $row['rank']<1 || $row['rank']>100000000 || !is_string($row['date']) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$row['date'])) return null;
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$row['date'],new DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d')!==$row['date'] || $date->getTimestamp()>$row['fetched_at'] || $date->getTimestamp()<$row['fetched_at']-45*86400) return null;
        }
        return $row;
    }
    public static function refresh(): void {
        // This method is called only by the explicit CLI task, never by rendering.
        if (PHP_SAPI!=='cli') throw new RuntimeException('CLI only.');
        if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for the metadata refresh.');
        $body='';$ch=curl_init('https://tranco-list.eu/api/ranks/domain/'.self::DOMAIN);
        curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT_MS=>1500,CURLOPT_TIMEOUT_MS=>5000,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_PROXY=>'',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: SecuritySearch-rank-refresh/0.9.21'],
            CURLOPT_WRITEFUNCTION=>static function($handle,$chunk) use(&$body){if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
        $ok=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if ($ok===false || $code!==200) throw new RuntimeException('Tranco refresh failed (HTTP '.(int)$code.'); previous cache retained.');
        $row=self::parse($body);$path=self::path();
        if (is_link($path)) throw new RuntimeException('Refused symlink cache.');
        $temp=tempnam(sys_get_temp_dir(),'securitysearch-rank-');
        if ($temp===false) throw new RuntimeException('Cannot create rank cache.');
        try {
            if (file_put_contents($temp,json_encode($row,JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Cannot write rank cache.');
            chmod($temp,0644);
            if (!rename($temp,$path)) throw new RuntimeException('Cannot publish rank cache.');
        } finally {if(is_file($temp))unlink($temp);}
        echo 'Tranco metadata refreshed; no visitor data sent.'.PHP_EOL;
    }
}
if (PHP_SAPI==='cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    if (($argv[1] ?? '')!=='--refresh') {fwrite(STDERR,"Usage: php lib/tranco.php --refresh\n");exit(2);}
    try {securitysearch_tranco::refresh();} catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
}
