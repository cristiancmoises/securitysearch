<?php
/** Included only in the disposable HTTP test tree, after reddit is renamed. */
class reddit extends reddit_adapter {
    public array $fixture_calls = [];
    protected function fetch_redlib(string $origin, string $path, array $params, int $deadline): string {
        if (!in_array($origin, service_pool::origins(), true)) {
            throw new LogicException('Unexpected fixture origin.');
        }
        $this->fixture_calls[] = $origin;
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $seen = apcu_fetch('fixture-redlib-calls') ?: [];
            $seen[] = $origin;
            apcu_store('fixture-redlib-calls', $seen);
        }
        $query = $params['q'] ?? '';
        if ($query === 'failure' || ($query === 'fallback news' && $origin === service_pool::PRIMARY)) {
            return '<div id="error">Blocked fixture</div>';
        }
        return str_replace(service_pool::PRIMARY, $origin, file_get_contents('redlib-fixture.html'));
    }
}
