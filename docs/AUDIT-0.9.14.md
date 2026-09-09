# Security Search v0.9.14 — audit and validation

Prepared 2026-09-08. Source baseline: Codeberg commit
`0751f145ff7b68d1f9c070c8bd8af79cd7bff717`.

## Scope and result

Reviewed the PHP input/filter and rendering paths, proxy address validation,
provider transport/parsers, cache boundary, image/favicons, Docker configuration
and deployment transaction. A separate read-only review examined the source and
new updater. This is a source audit with automated checks, not a penetration
test, CVE certification or proof of production readiness.

Implemented fixes:

| Area | Finding / change | Validation |
|---|---|---|
| Proxy / SSRF | Old checks accepted CGNAT, benchmark and multicast destinations. Require global public ranges and reject multicast plus mapped/translation/tunnel IPv6 forms; retain DNS pinning and redirect checks. | Hostile literal tests, including 100.64/10, 198.18/15, multicast, private/mapped IPv6; public IPv4 accepted. |
| Access-log privacy | Combined Apache logs included query strings, IP, Referrer and user-agent. Replace with timestamp/method/path/status/bytes/duration. | Both production Apache configurations inspected; running Apache/NPM logs not tested here. |
| Configuration | Environment strings were emitted as executable PHP literals without escaping; API false could remain a truthy string. Use typed values and `var_export`, ignore unknown keys and prevent version override. | Isolated generator test preserves quotes, dollar signs and slashes; API false is a boolean. |
| Services | Add fixed-origin Invidious/Binternet transports with 12 s / 2 MiB shared bounds, no redirect following, no shared result cache and neutral errors. | Malformed/challenge payloads, escaping, ID/URL allowlists and pagination fixtures. Real payload parsing: 20 videos and 18 images. |
| Favicon | Separate 30 s / 100 MB fetches amplified work and error headers exposed diagnostics. Share 8 s / 2 MiB, redact errors, validate fallback PNG before atomic write. | Static review and PHP lint; full ImageMagick favicon conversion not exercised. |
| Original streams | Unlimited decoded streaming within 30 s. Cap image/audio transfer at 64/128 MiB shared over redirects. | Mocked header/body callbacks verify oversize rejection before output and chunked-stream cutoff. |
| Inputs | Array values could become null; request query length had only form protection. Normalize safely and bound queries to 500 bytes. | Request-shape and provider-selection tests. |
| Release | Latest commit removed Lain media but source/release gates referenced it. Keep deletion, use static background and validate the existing theme file. | Regression and archive-content checks. |
| Deployment | Preserve environment/config, data snapshots, volume identities, network aliases and private port. Stage candidate before cutover; retain old container and ID-based recovery. | Offline transaction simulations include failed readiness and failed cleanup; actual Docker API execution not available here. |

## Automated results

- **102 PHP files:** syntax checks pass.
- **7 frontend JavaScript files**, plus the retained browser-test runner: syntax checks pass.
- **7 PHP regression suites:** general behavior, new services, Google transport, API/provider selection, proxy routing, configuration generation, streamed media limits all pass.
- **Python deployment tests:** candidate has no published port/production alias, the live port/environment persist, anonymous volume names persist, old startup is replaced, and rollback is attempted even after cleanup errors.
- **HTTP integration:** real PHP HTTP responses for homepage/CSP and all six bundled image-layout fixtures pass. These check HTML contracts; they are not visual browser tests.
- **Fish command:** fish 3.7 syntax check passes. Shell test runner passes shellcheck. No SSH connection was made.
- **Real service retrieval:** the two public service endpoints returned HTTP 200 and result payloads. The new parsers accept those payloads. PHP's direct network path could not resolve external hosts in this environment; server-to-server operation on IONOS remains unverified.

## Performance measurements

Local PHP 8.3, four PHP CLI HTTP workers, loopback, 50 homepage requests,
four concurrent clients: **50/50 successes**, median **2.46 ms**, p95 **7.39 ms**,
about **1,063.55 requests/s** for this short sample. This excludes NPM, TLS,
Internet latency, media downloads and search-provider work. It is not a VPS
capacity prediction or a before/after speedup claim.

Latest 1,000-iteration synthetic runs:

| Work | Median | p95 | Peak PHP allocation |
|---|---:|---:|---:|
| Render home template | 0.0694 ms | 0.0843 ms | 2 MiB |
| Parse 50 Invidious videos | 0.0753 ms | 0.0940 ms | 2 MiB |
| Parse 50 Binternet images | 0.2496 ms | 0.3518 ms | 2 MiB |

The existing raw-template cache benchmark preserved identical bytes: 1,000
home-template reads/compactions took 57.924 ms without reuse and 2.300 ms with
the warm cache. This cache already existed in the baseline; it is retained,
not represented as a newly introduced optimization.

Run `sh scripts/test.sh` for offline checks and parser benchmarks. Run
`python3 scripts/benchmark-http.py --url http://172.17.0.1:5140/ --requests 50 --concurrency 4`
on the VPS for an environment-specific homepage measurement. Do not repeatedly
load-test third-party search endpoints.

## Remaining limits / considerations

- No Docker daemon here: the pinned Alpine 3.24 image build, PHP 8.4/Apache runtime,
  actual Docker cutover, mounted-file permissions and ImageMagick 7 high-preview
  decoding remain deployment-time gates. The updater performs candidate health
  validation but does not certify provider searches or every image codec.
- Visual/browser interaction tests were not run. The retained optional runner
  was adjusted for the deleted background and new menu; screenshot/UI behavior
  remains unverified. No responsive visual-quality claim is made from HTML tests.
- Legacy selectable direct providers still have some long or unbounded buffered
  responses and repeated-attempt budgets. They were not all rewritten. The new
  Invidious/Binternet adapters have bounded transport.
- Favicon ICO/SVG formats remain outside the production ImageMagick allowlist;
  they may need a fallback. Some existing favicon conversion/cache code remains
  older than the hardened image preview path. Raster decoder CPU/memory behavior
  needs testing under the actual container policy.
- Ordinary resized thumbnails retain conservative no-store behavior. A revisit
  can re-fetch/re-decode images. Shared search-result caching was deliberately
  not introduced because it changes query-retention behavior.
- NPM may independently retain query strings/IPs; its configuration was not
  accessed. Actual VPS exposure, TLS, firewall, dependencies/CVEs and host load
  were not assessed. Existing logs are not deleted by this update.
- Binternet's extension-based filter is not native Pinterest filtering and
  does not prove MIME type. Empty/malformed handling is limited by what the
  upstream frontend itself reports. Upstream rate limits and YouTube playback
  restrictions remain outside this application's control.
- The updater retains old images/containers and private snapshots. Do not prune
  them until recovery is no longer needed; do not delete snapshot directories
  that are mounted by the running container. Source-masking mounts or unsupported
  network configurations stop the updater before cutover.

No Git remote was pushed and no VPS deployment was performed in this run.
