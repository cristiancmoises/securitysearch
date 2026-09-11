# v0.9.29 performance notes

SecuritySearch v0.9.29 is an evidence-driven hot-path release. It preserves the full
search/privacy interface and does not add benchmark-specific responses.

## Anonymous homepage compression

The strict anonymous-home fast path introduced in v0.9.28 remains eligible only for
GET/HEAD `/` with no query string, Cookie or Authorization. Startup now creates a gzip
representation from the exact validated HTML snapshot. Apache serves that immutable stream
to clients advertising gzip and serves the plain snapshot otherwise. Search and personalized
routes never use either artifact.

This avoids repeated compression work on that fixed document. The artifact is generated
outside Git, public source packages and Docker build context, is refused if symlinked or
world/group writable, and is removed/rebuilt on normal startup. A missing gzip artifact
falls back to the plain fast path rather than breaking the homepage.

The local reference document is about 39 KiB plain and 9.5 KiB gzip. A local PHP `gzencode`
loop is recorded only as a CPU-work reference; it is not an Apache, Nginx, network or public
benchmark measurement.

## Search-result CPU work

`frontend::highlighttext` now compiles the exact query highlight regular expression once
per frontend request object. The request-local cache is bounded to four entries and is not
APCu/shared state. It contains no results, cookies or cross-user data.

A controlled synthetic batch of fifty result texts showed lower local CPU time versus
rebuilding the same regex for every result. The measured difference is small relative to
provider/network latency; it is not presented as an end-to-end search speed claim.

## Brave transient retries and pagination

A Brave scraper object now retains one cURL easy handle for its own request lifetime. A
qualifying same-egress transient retry resets and reconfigures that handle, permitting
libcurl to reuse its connection cache. The handle is destroyed with the provider object and
never shared between visitors. All TLS, proxy, timeout, no-redirect and body-limit options
are applied again after reset.

Malformed pagination links no longer create a zero/invalid continuation token. A continuation
is emitted only from a positive numeric `offset` explicitly supplied by Brave.

## Competitive benchmark boundary

The operator-supplied `tools/secops-web-benchmark-v3.fish` remains unchanged and manual.
No competitive run is part of this release audit. A fresh-process homepage benchmark can be
dominated by DNS/TCP/TLS, reverse proxy and geography, so the new benchmark must be run from
the same client/network before making any claim versus 4get.ca.
