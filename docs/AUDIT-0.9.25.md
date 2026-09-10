# v0.9.25 — performance and resilience validation boundaries

Base source: attribution commit 1e32d17583c866e954c2e9dca7b40272a73232a5,
tree 6e86444c662b38f36b9d0983019b68b5969dbec9. The cumulative update also supports
v0.9.24-r1 tree c544cc4ce62604b96c0d5269a2872bff1add6a97. Local test histories
are fixtures; the release package/tag are created on the operator's real checkout.

## Measured local structure and bytes

Default Black homepage: 3 → 0 external blocking stylesheet links. Selected alternative
themes remain external; no JavaScript loader or cross-origin preconnect is added.
Actual PHP output: HTML 32,481 → 40,789 bytes; gzip HTML 7,771 → 9,866 bytes.
Previous external CSS totals 30,352 bytes / 8,025 gzip bytes. Combined HTML + blocking
CSS reference gzip: 15,796 → 9,866 bytes. Gzip figures are local reproducible
comparisons, not captured production wire transfers. Existing WebP logo: 11,528 →
5,710 bytes at unchanged 400 × 86 dimensions (lossy re-encoding with alpha retained).
Lighthouse's 90/430 ms estimates from different supplied runs are not measured gains.
No new Lighthouse score, Core Web Vitals percentile or all-provider speedup is asserted.

24 actual Chromium fixture state comparisons passed: six themes, two viewport
widths, expanded/collapsed chooser. Checked geometry and computed colors match the
baseline. Resources were embedded in PHP-produced HTML because the browser policy
rejected direct localhost navigation (ERR_BLOCKED_BY_ADMINISTRATOR). No policy was
disabled. These are not production navigation/LCP or native cache-persistence tests.
A first fixture incorrectly emptied inserted CSS strings; it was repaired, and
parity was then checked against the complete original CSS rather than relaxed.

## New regression coverage

Eight local PHP HTTP/CSS/asset methods cover zero blocking CSS, early eager/sized
logo, Custom script policy, theme selection, generated CSS hashes, no stylesheet
load handlers/preconnects, fixed CLI-probe arguments and no path injection.
54 health/image assertions cover bounded refusal/retry/cache entries, separate
egress keys, no secrets in keys, TTL expiry, no parser-wide cooldown, supplied
image variants, original links and first-row scheduling.
Actual Google/Brave transport methods execute with explicitly disabled/replaced
cURL functions in an isolated child: bounded transient retries, no refusal retries,
TLS/no-redirect/body/deadline policy, JSONP suffix rejection, Svelte parsing and
one-time expired-token renewal versus rate limits. These are transport doubles,
not native network tests, live provider responses or substitutes for native-runtime.php.

All 57 earlier command entries are retained in order and with their arguments;
four new suites bring the required list to 61. Old version-specific release tests
remain unchanged; new v0.9.25 packaging checks asset marker 29. The complete native
PHP/Docker suite must still pass before cutover. Missing cURL, DOM/XML, mbstring,
APCu, Imagick, fish and Docker in the authoring environment prevent a full native
pass. Exact executed counts/logs and workflow evidence accompany the update kit.
The ordinary and --keep-going runners still return nonzero for any failing command.

The old Brave transport unit test clears only its own test health key between
independent error cases so a 429 fixture does not mask the following timeout case.
Production health is not disabled. Token-expiry renewal remains allowed once;
CAPTCHA, refusal, rate limit and executable upstream JavaScript are not bypassed.

## Preservation and operation

Browser-local pictures, animation controllers, RSS routes/caches/parsers, Redlib
ownership text, operator media conversion/validation, Apache/Docker recipe, TLS
verification, continuation isolation and existing live RSS/Binternet gates remain.
The new readiness assertion verifies the inline Black homepage instead of demanding
its removed external Black.css link. The matching post-deployment attribution
check still verifies /, /about and /settings. No production deployment, remote
push, tag replacement or source-release upload was performed while creating the kit.

No user query/result is shared in new APCu health state or diagnostic output.
The optional CLI probe sends only its fixed neutral query, with no visitor history,
response bodies, IPs, keys or credentials in its JSON. Initial synchronous DNS and
external providers can still cause failure; cURL budgets are not guarantees of
end-to-end wall-clock latency. Source packages exclude operator-only artwork.

## Primary references

- https://developer.chrome.com/docs/performance/insights/render-blocking
- https://developer.chrome.com/docs/performance/insights/image-delivery
- https://developer.chrome.com/docs/performance/insights/lcp-breakdown
- https://developer.chrome.com/docs/performance/insights/network-dependency-tree
- https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Retry-After
- https://curl.se/libcurl/c/CURLOPT_TIMEOUT_MS.html
