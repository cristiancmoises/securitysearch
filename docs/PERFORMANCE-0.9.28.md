# v0.9.28: anonymous homepage static fast path

SecuritySearch 0.9.28 targets the remaining **origin-side** work on the canonical
homepage without removing search, appearance, privacy, provider, attribution or
accessibility features.

## Why this optimization

The operator's earlier SecOps Web Benchmark 3.0.0 sample reported a median complete
HTML time of 801.589 ms for `securityops.co` and 689.252 ms for `4get.ca`. The
corresponding TTFB medians were 800.544 ms and 688.615 ms. In that sample, almost
all of the observed gap existed before the response body transferred. Those numbers
are historical operator measurements, not v0.9.28 results and not global claims.

v0.9.27 reduced PHP rendering work to a small fraction of a millisecond in a local
fixture. v0.9.28 therefore avoids PHP entirely for the only request that can safely
be represented by a fixed document: an anonymous, query-free `GET` or `HEAD /`.

## Eligibility

Apache internally serves `home-anonymous.generated.fast` only when all of these are
true:

- the runtime startup explicitly enabled the fast path;
- method is GET or HEAD;
- query string is empty;
- Cookie header is empty;
- Authorization header is empty;
- the generated artifact exists.

Every other request follows the existing PHP route. The internal generated filename
returns HTTP 404 when requested directly, preserving `/` as the canonical address.
There is no user-agent or benchmark-specific branch.

The static response carries the same Black-home CSP and privacy/security headers,
plus `X-SecuritySearch-Render: static-home`. It is publicly cacheable for only 60
seconds and varies on Cookie and Authorization. Search/result routes remain private
and no-store. The generated document contains no query, cookie, visitor identifier,
credentials, local picture or private operator artwork.

## Generation and fallback

Container startup builds the existing immutable view resources and then renders the
anonymous Black homepage using the normal page renderer. The output is validated for
size, unresolved placeholders, executable markup and required public markers, then
published atomically with non-writable permissions. A failure deletes the artifact,
sets `SECURITYSEARCH_STATIC_HOME=0`, and leaves the existing dynamic homepage fully
functional.

The optional Tranco systemd timer is upgraded only when its existing v1 units match
the exact SecuritySearch-managed bytes. The v2 service refreshes public Tranco data
as the Apache user, then rebuilds the static homepage as root. Modified/operator
units are refused and preserved.

## Local authoring measurement

A Debian Apache 2.4 + mod_php fixture used the same eligibility and response policy,
with fresh curl processes and 60 randomized measurements per path after warm-up:

| Path | Median TTFB | Median complete | descriptive p95 complete |
|---|---:|---:|---:|
| anonymous static | 1.871 ms | 2.013 ms | 7.016 ms |
| Cookie `theme=Black` dynamic | 3.071 ms | 3.291 ms | 14.759 ms |

The median TTFB difference was 1.200 ms in this **localhost fixture**. It is not a
production benchmark, not a 4get comparison, and not evidence that the historical
~112 ms public gap is closed. Public DNS, TCP/TLS, Nginx Proxy Manager, geography
and VPS scheduling remain outside this measurement.

Run `diagnose-delivery.fish` after deployment to verify `static-home` inside the
container, then run the operator's unchanged `tools/secops-web-benchmark-v3.fish`
from the same client/network used for previous comparisons. Keep public benchmark
results separate from application processing measurements.

## Rollback

Set `SECURITYSEARCH_STATIC_HOME=0` and replace the container through the normal
validated deployment path to disable the optimization without changing page code.
A runtime build failure already takes this fallback automatically. Do not manually
serve the generated artifact through Nginx Proxy Manager or bypass candidate gates.
