<?php
/** Machine-readable upstream failure. No body, query, URL, token or raw exception text. */
final class news_failure extends RuntimeException {
    public readonly string $reason;
    public readonly ?int $http_status;
    public readonly ?int $curl_errno;
    public readonly int $retry_after;

    public function __construct(string $reason, ?int $http=null, ?int $curl=null, int $retry=0) {
        $allowed=['challenge','http_refused','rate_limited','http_error','redirect','transport',
            'dns_or_target','deadline','body_limit','content_type','invalid_xml','unsafe_xml',
            'invalid_feed','invalid_results','empty_probe','cooldown','busy','invalid_input','parser'];
        $this->reason=in_array($reason,$allowed,true) ? $reason : 'parser';
        $this->http_status=$http!==null && $http>=100 && $http<=599 ? $http : null;
        $this->curl_errno=$curl!==null && $curl>=1 && $curl<=99 ? $curl : null;
        $this->retry_after=max(0,min(3600,$retry));
        parent::__construct('News source unavailable ('.$this->reason.'). Choose another source or retry later.');
    }
    public static function from(Throwable $error): self {
        for($e=$error;$e!==null;$e=$e->getPrevious()) {
            if($e instanceof self) return $e;
            $code=$e->getCode();
            if(is_int($code) && $code>=300 && $code<=599) return self::http($code);
            if(is_int($code) && $code>0 && $code<100) return new self($code===28?'deadline':'transport',null,$code);
        }
        // Recognize only our bundled transport messages; never copy them to reports.
        $text=$error->getMessage();
        if(str_contains($text,'byte limit')) return new self('body_limit');
        if(str_contains($text,'time limit') || str_contains($text,'deadline')) return new self('deadline');
        if($text==='Invalid URL') return new self('dns_or_target');
        return new self('parser');
    }
    public static function http(int $status, int $retry=0): self {
        return new self(match($status) {
            401,403,407,418,451=>'http_refused',429=>'rate_limited',
            300,301,302,303,304,305,306,307,308,309=>'redirect',default=>'http_error'
        },$status,null,$retry);
    }
    public function cooldown(): int {
        return max($this->retry_after, match($this->reason) {
            'challenge','http_refused'=>600,'rate_limited'=>300,'redirect'=>120,default=>20
        });
    }
    public function report(): array {
        return ['reason'=>$this->reason,'http_status'=>$this->http_status,'curl_errno'=>$this->curl_errno];
    }
    /** A hint is not proof of a particular protection vendor. Fail closed on it. */
    public static function challenge(string $body): bool {
        $s=strtolower(substr($body,0,262144));
        return preg_match('/(?:id=["\x27]anubis_challenge|anubis_version|\/\.within\.website\/|cf-chl-|challenge-platform|verify you are human|checking your browser|javascript is required|\banubis\b|verifying your browser|making sure you.re not a bot|access denied)/',$s)===1;
    }
}
