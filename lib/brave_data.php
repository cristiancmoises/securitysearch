<?php
require_once __DIR__.'/search_health.php';

/** Decode Brave's data serializer, never JavaScript. Unknown syntax fails closed. */
final class brave_data {
    private const MAX_BYTES=4194304;
    private const MAX_DEPTH=64;
    private const MAX_NODES=100000;
    private int $offset=0;
    private int $nodes=0;
    private int $string_bytes=0;

    private function __construct(private string $source) {}

    public static function decode(string $source): array {
        if (strlen($source)>self::MAX_BYTES) self::fail();
        $parser=new self($source);
        $value=$parser->value(0,[]);
        $parser->space();
        if ($parser->offset!==strlen($source) || !is_array($value)) self::fail();
        return $value;
    }

    private static function fail(): never {
        throw new upstream_search_failure('brave','format',200);
    }

    private function space(): void {
        while (isset($this->source[$this->offset]) && strpos(" \t\r\n",$this->source[$this->offset])!==false) $this->offset++;
    }

    private function take(string $token): bool {
        $this->space();
        if ($this->offset+strlen($token)>strlen($this->source)) return false;
        if (substr_compare($this->source,$token,$this->offset,strlen($token))!==0) return false;
        $this->offset+=strlen($token);
        return true;
    }

    private function need(string $token): void {
        if (!$this->take($token)) self::fail();
    }

    private function identifier(): string {
        $this->space();
        if (preg_match('/\G[A-Za-z_$][A-Za-z0-9_$]*/',$this->source,$match,0,$this->offset)!==1) self::fail();
        $this->offset+=strlen($match[0]);
        return $match[0];
    }

    private function count_string(string $value): string {
        $this->string_bytes+=strlen($value);
        if ($this->string_bytes>self::MAX_BYTES) self::fail();
        return $value;
    }

    private function string(): string {
        $quote=$this->source[$this->offset++];
        $json='"';
        while (isset($this->source[$this->offset])) {
            $char=$this->source[$this->offset++];
            if ($char===$quote) {
                try {$value=json_decode($json.'"',true,2,JSON_THROW_ON_ERROR);}
                catch (JsonException $error) {self::fail();}
                return $this->count_string($value);
            }
            if (ord($char)<32) self::fail();
            if ($char!=='\\') {$json.=$char==='"' ? '\\"' : $char;continue;}
            $escape=$this->source[$this->offset++] ?? '';
            if ($escape==="'") {$json.="'";continue;}
            if (strpos('"\\/bfnrt',$escape)!==false && $escape!=='') {$json.='\\'.$escape;continue;}
            if ($escape==='v') {$json.='\\u000b';continue;}
            if ($escape==='0' && !preg_match('/[0-9]/',$this->source[$this->offset] ?? '')) {$json.='\\u0000';continue;}
            if ($escape==='u' || $escape==='x') {
                $length=$escape==='u' ? 4 : 2;
                $hex=substr($this->source,$this->offset,$length);
                if (strlen($hex)!==$length || preg_match('/\A[0-9a-fA-F]+\z/',$hex)!==1) self::fail();
                $this->offset+=$length;
                $json.='\\u'.($escape==='x' ? '00' : '').$hex;
                continue;
            }
            self::fail();
        }
        self::fail();
    }

    private function value(int $depth,array $parameters,bool $literal_only=false) {
        if ($depth>self::MAX_DEPTH || ++$this->nodes>self::MAX_NODES) self::fail();
        $this->space();
        $char=$this->source[$this->offset] ?? '';
        if ($char==='"' || $char==="'") return $this->string();
        if (!$literal_only && $char==='[') {
            $this->offset++;$out=[];
            if ($this->take(']')) return $out;
            while (true) {
                $out[]=$this->value($depth+1,$parameters);
                if ($this->take(']')) break;
                $this->need(',');
                if ($this->take(']')) break;
            }
            return $out;
        }
        if (!$literal_only && $char==='{') {
            $this->offset++;$out=[];
            if ($this->take('}')) return $out;
            while (true) {
                $this->space();$first=$this->source[$this->offset] ?? '';
                $key=$first==='"' || $first==="'" ? $this->string() : $this->identifier();
                if (array_key_exists($key,$out)) self::fail();
                $this->need(':');$out[$key]=$this->value($depth+1,$parameters);
                if ($this->take('}')) break;
                $this->need(',');
                if ($this->take('}')) break;
            }
            return $out;
        }
        if (!$literal_only && $char==='(' && $parameters===[]) return $this->iife($depth+1);
        if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/',$this->source,$match,0,$this->offset)===1) {
            $this->offset+=strlen($match[0]);return $match[0];
        }
        $name=$this->identifier();
        // Keep the primitive spelling consumed by the existing Brave adapter.
        if (in_array($name,['true','false','null','undefined'],true)) return $name;
        if ($name==='void') {$this->need('0');return 'void 0';}
        if (!$literal_only && isset($parameters[$name])) {$reference=new stdClass();$reference->parameter=$name;return $reference;}
        self::fail();
    }

    private function iife(int $depth) {
        $this->need('(');
        if ($this->identifier()!=='function') self::fail();
        $this->need('(');$names=[];
        if (!$this->take(')')) {
            do {
                $name=$this->identifier();
                if (isset($names[$name]) || count($names)>=128 || in_array($name,['function','return','void','true','false','null','undefined'],true)) self::fail();
                $names[$name]=true;
            } while ($this->take(','));
            $this->need(')');
        }
        $this->need('{');
        if ($this->identifier()!=='return') self::fail();
        $body=$this->value($depth,$names);
        $this->take(';');$this->need('}');$this->need('(');
        $arguments=[];
        if (!$this->take(')')) {
            do {$arguments[]=$this->value($depth,[],true);} while ($this->take(','));
            $this->need(')');
        }
        $this->need(')');
        if (count($arguments)!==count($names)) self::fail();
        $bindings=$names===[] ? [] : array_combine(array_keys($names),$arguments);
        return $this->bind($body,$bindings);
    }

    private function bind($value,array $bindings) {
        if ($value instanceof stdClass) return $this->count_string($bindings[$value->parameter]);
        if (is_array($value)) foreach ($value as &$child) $child=$this->bind($child,$bindings);
        return $value;
    }
}
