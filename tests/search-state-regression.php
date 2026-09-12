<?php
/** Real production link generation; no providers or optional PHP extensions. */
require_once __DIR__ . '/../data/config.php';
require_once __DIR__ . '/../lib/frontend.php';
$frontend = new frontend();
$count = 0;
function same($actual, $expected, string $label): void {
    global $count;
    $count++;
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: " . $label . "\n");
        exit(1);
    }
}
function params(string $query): array { parse_str($query, $out); return $out; }
foreach (['0', 'all', 'any', 'C++ & <Guix>', 'ação 日本語', 'a#b?c=1', ' spaced '] as $term) {
    $q = ['s' => $term, 'nsfw' => '0', 'scraper' => 'brave'];
    same(params($frontend->buildquery($q)), $q, 'search and zero filter round trip');
    same(params($frontend->buildquery($q, true)), ['nsfw' => '0', 'scraper' => 'brave'], 'omit only search');
}
foreach ([null, false, ''] as $absent) {
    same(params($frontend->buildquery(['s' => '0', 'optional' => $absent])), ['s' => '0'], 'omit absence');
}
same(params($frontend->buildquery(['s' => 0, 'offset' => 0, 'enabled' => true])), ['s' => '0', 'offset' => '0', 'enabled' => '1'], 'typed zero is not false');
same(params($frontend->buildquery(['s' => 'any', 'format' => 'any', 'size' => 'all', 'npt' => 'old', 'extendedsearch' => true, 'spellcheck' => 'no'])), ['s' => 'any'], 'filter sentinels and state controls omitted');
$oldTimezone = date_default_timezone_get();
date_default_timezone_set('UTC');
same(params($frontend->buildquery(['s' => '0', 'newer' => 0, 'older' => 86400])), ['s' => '0', 'newer' => '1970-01-01', 'older' => '1970-01-02'], 'date values preserved');
date_default_timezone_set($oldTimezone);
$oldSeparator = ini_get('arg_separator.output');
ini_set('arg_separator.output', '&amp;');
same($frontend->buildquery(['s' => 'all', 'nsfw' => '0']), 's=all&nsfw=0', 'separator independent of ini');
ini_set('arg_separator.output', $oldSeparator);
foreach (['token.' . str_repeat('a', 43), 'a+b/c==', 'x&nsfw=1&s=changed', 'x#fragment', 'x%2B +', '日本語', ''] as $token) {
    $url = $frontend->htmlnextpage(['s' => '0', 'nsfw' => '0', 'npt' => 'old'], $token, 'images');
    same(params(parse_url($url, PHP_URL_QUERY)), ['s' => '0', 'nsfw' => '0', 'npt' => $token], 'continuation stays a single value');
    same(parse_url($url, PHP_URL_FRAGMENT), null, 'continuation cannot add fragment');
}
same($frontend->htmlnextpage([], 'abc', 'images'), 'images?npt=abc', 'no empty leading parameter');
foreach (['web', 'images', 'news', 'videos', 'music'] as $target) {
    [$title, $body] = $frontend->provider_recovery('fixture unavailable <script>', ['s' => '0', 'nsfw' => '0', 'scraper' => 'google', 'npt' => 'old', 'append' => 1], $target);
    preg_match_all('/href="([^"]+)"/', $body, $links);
    $searched = 0;
    foreach ($links[1] as $link) {
        $url = html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '/settings') { continue; }
        $p = params(parse_url($url, PHP_URL_QUERY));
        same($p['s'] ?? null, '0', 'recovery retains literal zero query');
        same($p['nsfw'] ?? null, '0', 'recovery retains zero filter');
        same(isset($p['npt']) || isset($p['append']), false, 'recovery does not replay continuation');
        $searched++;
    }
    same($searched > 0, true, 'recovery has a search link');
    same(str_contains($body, '<script>'), false, 'provider message stays escaped');
}
echo "PASS: {$count} search-state assertions (production frontend, no provider requests).\n";
