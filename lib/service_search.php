<?php
// Fixed, operator-owned frontends. No user-selectable transport destinations.
require_once __DIR__ . '/curlproxy.php';
require_once __DIR__ . '/backend.php';

abstract class service_search {
    protected const ORIGIN = '';
    protected const PATH = '';
    protected backend $backend;

    public function __construct() { $this->backend = new backend(static::class); }

    protected function fetch(array $params): string {
        return $this->fetch_path(static::PATH,$params);
    }

    // Subclasses supply a fixed route; never follow an upstream continuation URL.
    protected function fetch_path(string $path,array $params): string {
        $url = static::ORIGIN . $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $budget = (object)['deadline'=>hrtime(true)+12000000000, 'remaining_bytes'=>2097152, 'remaining_wire_bytes'=>2097152];
        try {
            // Reuse the media proxy's public-IP validation and DNS pinning.
            // redirectcount=4 permits this hop but rejects any redirect before
            // another request can leave the fixed destination.
            $response = (new proxy(false))->get($url, proxy::req_web, false, null, 4, 2097152, $budget);
            return $response['body'];
        } catch (Exception $error) {
            throw new RuntimeException('The selected service is unavailable. Try its direct search link or retry later.');
        }
    }

    protected function parameters(array $get, string $kind): array {
        if (!empty($get['npt'])) {
            $stored = $this->backend->get($get['npt'], $kind)[0];
            $params = json_decode($stored, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($params)) { throw new RuntimeException('Invalid continuation. Restart this search.'); }
            return $params;
        }
        $query = $get['s'] ?? '';
        if (!is_string($query) || trim($query)==='' || strlen($query)>500) {
            throw new RuntimeException('Enter a search of 1–500 bytes.');
        }
        return ['q'=>$query];
    }

    protected function continuation(array $params, string $kind): string {
        return $this->backend->store(json_encode($params, JSON_THROW_ON_ERROR), $kind, 'raw_ip::::');
    }

    protected static function text($value, int $limit=2000): string {
        return is_string($value) ? mb_strcut($value, 0, $limit, 'UTF-8') : '';
    }
}
