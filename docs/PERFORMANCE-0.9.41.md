# v0.9.41 performance scope

The optimization target is useful content delivered correctly, not an early empty header.
Anonymous canonical homepage requests already bypass PHP using a validated snapshot. This
release preserves that path rather than adding a fake shell or benchmark-specific response.
Search and personalized requests stay dynamic and private/no-store.

In seven independent local CLI measurements, a synthetic one-card 1 MiB ampersand title
produced 15,729,245 bytes before versus 15,965 bytes after the fix. Median render time was
69.617 ms versus 0.422 ms; peak process memory was 35,663,872 versus 4,194,304 bytes. This
measures pathological metadata containment, not a typical search-result latency prediction.

An alternating localhost PHP fixture comparison used fourteen measured requests per case
per version after a discarded warmup. The large-title case's median header-arrival time was
56.949 ms before and 0.997 ms after; complete body time was 88.551 ms and 1.687 ms. These are
shared-renderer fixture requests without Internet/TLS/NPM/provider time, not full controller
acceptance. The ordinary-title case was 0.440 ms before and 0.549 ms after for header arrival:
**no ordinary-case speed improvement is demonstrated**. Its response remained 662 bytes.

The filmstrip scroll handler no longer asks for synchronous geometry. Deterministic tests
cover repeated scroll events while offscreen, subsequent visibility, single-flight loading
and observer disconnection. This removes a possible layout-forcing call site; it does not
attribute or claim to eliminate the uploaded report's unattributed 60 ms of reflow.

## Manual TTFB benchmark

Run the self-contained `tools/secops-web-benchmark-v4.fish`, or `python3 tools/ttfb_benchmark.py`
with installed Python/curl. The Fish default uses an isolated Guix shell; `--system-deps` uses
installed tools. `--self-test` is offline. A normal run sends real homepage GET requests;
never invoke it automatically from deployment, CI or a provider retry loop.

The seven targets are securityops.co, google.com, yandex.com, bing.com, search.brave.com,
4get.ca and duckduckgo.com. Defaults: 21 rounds, balanced rotating order, one discarded
warmup round, sequential requests, one-second pause and bounded timeout/size. No cookies,
query parameters, cache-busting, certificate bypass, concurrent load or automatic retries
are introduced. Stop/skip behavior for blocking and rate limits is retained.

The new winner uses median `time_starttransfer` on shared valid rounds, with at least five
and 80% of planned rounds. Total HTML time is displayed separately. All targets can win;
errors, origin mismatch, informational responses, incomplete runs and invalid/missing
measurements cannot win. A rank among qualifying sites is not a win over excluded sites.

Fresh curl processes create new connections; this is not a guaranteed cold DNS or TLS
cache test. Redirect/proxy/HTTP3 cases do not receive misleading component subtraction.
The test covers homepage HTTP responses, not JavaScript-rendered usability, search-result
latency or result quality. Repeat comparable runs from relevant locations and retain CSV,
JSON, versions, timestamps, family/proxy information and cache headers. Do not infer a
universal ranking. The retained v3 ranks total HTML time, so its winner is not a TTFB winner.

No real external v0.9.41 benchmark was completed by the builder. The uploaded excerpt is
not a complete seven-site timing dataset and cannot establish a baseline ranking. Read
[the research review](RESEARCH-0.9.41.md) for origin attribution and next measurement priorities.
