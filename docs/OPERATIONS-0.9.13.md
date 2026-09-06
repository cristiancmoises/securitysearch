# v0.9.13 operations

[Português](OPERATIONS-0.9.13.pt-BR.md) · [Previous baseline](OPERATIONS-0.9.12.md)

## Navigation and privacy

Home and result navigation expose Images (`images.securityops.co`), Videos
(`invidious.securityops.co`), Pixiv, Chat, News (`news.securityops.co`) and Wiki.
The internal Web/Images/Videos search tabs are separate. On video results, an
encoded Invidious search link remains visible even when the original provider
fails. This is a normal link, not an embedded player, automatic fallback, prefetch
or third-party script. Clicking sends the query to that instance; no playback
availability guarantee is implied. The navigation and suggestion need no JavaScript.

## Bounded performance work

APCu stores only raw, whitespace-compacted bundled templates for 300 seconds,
before replacement of query, theme or cookie values. Keys contain the installation
path hash, asset version, template mtime and size. Entries above 128 KiB are not
cached; deployments clear the process cache. Without APCu the normal file path is
used. This reduces local rendering work, not external provider/Tor latency. Search
HTML explicitly sends `private, no-store`; no persistent query/result cache or
tracking system was added. Existing connection reuse, request deadlines, six
image layouts and lightweight preview defaults remain in place.

Image URLs with userinfo are refused before DNS or network access. Conversion
errors no longer reflect ImageMagick paths or upstream transport details. All
caught image failures are non-cacheable. The legacy unconditional 304 response
for any client date was removed: without a validated source validator it could
keep a broken thumbnail indefinitely or skip request validation. JPEG output now
uses its matching JPEG compression constant. Existing SSRF, raster and size/time
limits remain required.

The sitemap uses the same fixed HTTPS canonical origin as the home page, never
the proxy-side HTTP scheme or a client Host header, and excludes Settings.
Search/proxy endpoints remain excluded from crawlers. Operator NPM overrides of
`/robots.txt` or `/sitemap.xml` must also be audited; changing repository files
does not automatically replace an edge override.

## Verification and safe rollout

The image moves to the currently supported Alpine 3.24 main/community branch
while retaining PHP 8.4 compatibility. The base manifest is pinned; `apk upgrade`
runs at build time, not inside a live container. Supply
`--build-arg APK_REFRESH=YYYY-MM-DD` during a scheduled rebuild so an unchanged
base image does not silently reuse an old package-install layer. Validate and
promote that candidate; do not restart the Docker daemon. Apache removes the
normal-response duplicate before setting its mandatory security headers.
Check support against [Alpine releases](https://www.alpinelinux.org/releases/)
before future branch migrations; do not mix repositories from different branches.

Run all four suites listed in the previous baseline, plus PHP lint and the
no-JavaScript browser fixtures. Regression tests cover six direct links, encoded
Invidious queries, absence of embeds, separation of cached templates from queries,
theme changes, template traversal and unsafe image URL rejection.

The development audit passed 31 no-JavaScript browser cases (320/390/768/1440px
image/video layouts and desktop/mobile/reduced-motion home). An isolated 1,000-call
template microbenchmark measured home preprocessing at 358.332 ms without cache
versus 6.988 ms warm, with identical bytes. These are aggregate local preprocessing
times, not search latency or an end-to-end speedup claim. Re-run
`php -d apc.enable_cli=1 tests/template-benchmark.php` on the target runtime.

Test a candidate before replacing production. Preserve all production networks,
private bindings, credentials and volume mounts. Retained rollback containers
must have restart disabled so a Docker restart cannot resurrect an obsolete
listener. Preserve their original policy in a private backup for deliberate
rollback. Check Docker IP assignments as well as process health after a daemon
restart: unrelated dynamic endpoints can conflict with reserved static addresses.
Do not delete networks, relax firewalls or expose private ports to fix this.

Measure HTTP status, actual result count and latency together. A 4–6 ms cooldown
error is not a successful search. Keep provider challenges and maintenance states
honest. No benchmark here promises more traffic or universal provider uptime.
