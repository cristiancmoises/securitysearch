# v0.9.30 performance notes

SecuritySearch v0.9.30 is a concurrency and search-result delivery release. It keeps the
v0.9.29 anonymous-home fast path, Google/CSE reliability fixes, Brave recovery, image
layouts, local pictures, news, themes and provider gates. The changes below target work
that can delay useful searches when several browser requests arrive together.

## Event MPM + PHP-FPM

The previous container served PHP through Apache prefork/mod_php with
`MaxRequestWorkers 16`. Static files, keep-alive connections, PHP pages and slow decorative
favicon misses therefore competed for the same small process pool.

The default v0.9.30 candidate uses Apache event MPM with PHP-FPM over loopback FastCGI.
Apache may service static/keep-alive work using its threaded event workers while the PHP-FPM
pool keeps a separate `pm.max_children = 16` ceiling. This deliberately does not copy the
much larger public 4get.ca pool size: the safe capacity for this VPS must be established
from real memory/concurrency measurements.

The old prefork/mod_php packages and configuration remain as an explicit operator fallback
mode. Deployment itself pins `SECURITYSEARCH_PHP_RUNTIME=fpm`; if FPM/event readiness fails,
the candidate is rejected instead of silently deploying a different runtime.

Docker health now checks both `/` and `/settings`. The anonymous homepage may be served
without PHP, so checking it alone could miss a dead FPM pool.

Upstream 4get's Apache deployment guide also recommends `mpm_event` with PHP-FPM. That is
useful design evidence, not proof that this change by itself will reproduce 4get.ca's public
latency or capacity.

## Bounded favicon misses

Favicons are decorative. In v0.9.29 a cold favicon request could spend up to eight seconds
in its shared remote-fetch budget. With sixteen prefork PHP workers, a burst of unrelated
cold favicons could therefore compete directly with search requests.

v0.9.30 keeps successful on-disk favicon caching and the existing Google-favicon fallback,
but adds bounded admission:

- remote favicon work has a 2.5-second total budget;
- at most four remote favicon refreshes may be in flight when APCu is available;
- the same host gets one short lease, suppressing duplicate concurrent fetches;
- failed hosts get a 60-second negative cache;
- successful cached icons may be browser-cached for one day;
- placeholder 404 images may be browser-cached for five minutes;
- APCu keys contain fixed-size host hashes, not search queries, result bodies or credentials;
- icon writes are atomic.

If APCu is unavailable, functional behavior is preserved without the cross-request admission
limit. No search result is removed because its favicon fails.

## Evidence and limits

Focused offline tests verify the four-slot admission ceiling, owner-safe lease release,
negative caching, cached/placeholder HTTP policy and the FPM/event source configuration.
The native FPM configuration check intentionally requires Alpine's `php-fpm84` and Apache
module files; that check cannot pass in the Debian authoring environment and remains
mandatory in the isolated VPS audit image.

No competitive benchmark was run while building this release. The supplied
`tools/secops-web-benchmark-v3.fish` remains manual and byte-for-byte unchanged. A new
public result must be measured after guarded deployment before claiming a speed advantage.
