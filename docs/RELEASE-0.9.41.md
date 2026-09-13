# SecuritySearch v0.9.41

The serving release is 0.9.41; static assets use identifier 41. This is a candidate package,
not evidence of deployment, live-provider availability or a faster public website.

## Fixed

Image title output is bounded before HTML escaping and shared between HTML and append JSON.
Labels preserve valid UTF-8 prefixes, normalize ASCII control whitespace, and use fixed
fallbacks for malformed/blank values. Original links, animation actions and preview selection
remain intact. A 1 MiB ampersand label no longer expands to 15.7 MB in one card.

Filmstrip pagination replaces a scroll-time getBoundingClientRect call with a separate
IntersectionObserver for viewport visibility. Existing no-JavaScript pagination, Save-Data,
single-flight requests, 24-card pages, 480-card cap and bounded streamed responses are retained.

Apache now denies percent-encoded aliases of its internal homepage snapshot. Direct error
responses do not claim to be gzip-compressed static successes or publicly cacheable snapshots.
Canonical anonymous GET/HEAD keeps the PHP-free precompressed route. Range/If-Range uses the
identity representation. Cookie, authorization and query requests remain on the dynamic path.

Google failure reports retain an allowlisted transport trace to distinguish, for example,
a successful bootstrap followed by refusal from failure on the initial transport. No extra
request, retry, cache flush, proxy change or altered acceptance condition is introduced.

## Measurement and verification

A new standalone manual v4 benchmark ranks valid matched homepage GET samples by median TTFB.
It retains total HTML time, DNS/TCP/TLS components where interpretable, and exclusions. Cross-
origin final documents, interim responses, challenges, 429s, insufficient samples and partial
runs cannot receive a full seven-target winner claim. The original v3 is byte-identical.

All 119 retained native commands remain in order; the required inventory is now 124 commands.
Missing runtime prerequisites or failed live checks are failures. The paired new publisher
independently requires the deployed source/image and complete acceptance evidence. Existing
v0.9.30/v0.9.40 tags are never moved. Prompts and private artwork remain outside public source.

Read [operations](OPERATIONS-0.9.41.md), [performance](PERFORMANCE-0.9.41.md),
[audit](AUDIT-0.9.41.md), and [research](RESEARCH-0.9.41.md).
