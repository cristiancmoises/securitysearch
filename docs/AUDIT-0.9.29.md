# SecuritySearch v0.9.29 — executed audit

Application **0.9.29** · asset **33**.

## Source and scope

This release starts from the exact SecuritySearch v0.9.28 source tree
`4eff5d0df7caac951d8786ef909546bd79549d16` in the supplied/reconstructed local
checkout. The authoring baseline commit is a disposable fixture; it is not presented as
the user's published release commit. v0.9.29 is intentionally narrow: it changes the
anonymous-home delivery path, request-local result highlighting, Brave transient retry
handle reuse and Brave pagination validation. It does not rewrite working Google/CSE,
Binternet, RSS, browser-local picture, animation, private-theme or Docker-provider logic.

The operator's competitive benchmark is retained byte-for-byte as
`tools/secops-web-benchmark-v3.fish`, SHA-256
`bd6f2207de8d8d36b41f47f391b4cba54c7d264fb6dbd5605de244f6a57f2e1d`,
52,777 bytes. It was **not executed** and is not part of the automated audit. No new
4get.ca leaderboard, winner claim or comparative timing is published by this release.

## Implemented performance work

### Precompressed anonymous homepage

The strict anonymous-home fast path remains eligible only for canonical GET/HEAD `/`
with no query string, Cookie or Authorization. Startup now publishes an atomic gzip
representation from the exact already-validated static HTML snapshot when PHP zlib is
available. Apache may serve that precompressed representation only to a client advertising
a bare `gzip` token. An explicit `gzip;q=0` does not receive it. The plain static
representation remains available, and failure to create gzip falls back without breaking
the homepage.

Direct requests for either generated implementation file remain denied. Generated files
are ignored by Git and Docker build context, must not be symlinks or group/world writable,
and are rebuilt on normal container startup. Search pages and personalized/homepage
requests with cookies, authorization or query parameters remain on the dynamic/private
path.

Seven focused static-home source/security tests passed. The native Apache content-
negotiation regression is mandatory but could not run in this authoring environment
because its required Alpine httpd/curl runtime is unavailable.

### Request-local result highlighting

`frontend::highlighttext` now reuses a compiled query regular expression within one
frontend request object, bounded to four entries. The cache is not APCu/shared state and
contains no result data, cookies or cross-user information. Existing escaping and visible
highlight markup are preserved.

Eight search-hotpath assertions passed, including exact highlight markup, cache bounds and
Brave continuation validation. A synthetic fifty-result CPU batch records median local
work of about **0.068 ms** when rebuilding the regex for each result versus **0.044 ms**
with the request-local pattern cache. This is a microbenchmark of local CPU work only; it
is not provider, Apache, network, browser or competitive search latency.

### Brave transient retry handle

One Brave scraper object now owns one cURL easy handle for its request lifetime. A single
qualifying same-egress transient retry resets that same handle and reapplies the complete
TLS, timeout, no-redirect, proxy, header and response-bound configuration. The handle is
not shared across visitors and is closed with the provider object.

Malformed or nonpositive Brave `offset` values no longer create a continuation token.
Pagination still requires an explicit positive provider offset. The isolated transport
suite passed **41 adapter-path assertions**, plus its existing Svelte/bootstrap and token
renewal cases. Native cURL functions were deliberately disabled/replaced by test doubles
for that suite; it is not evidence of live Brave availability.

An attempted parser rewrite was locally slower and was completely reverted. It is not part
of the final source and is not claimed as an optimization.

### Anonymous-home gzip CPU reference

The current reference homepage is about 39 KiB plain and 9.5 KiB gzip. A local synthetic
loop records median PHP `gzencode` work of about **0.770 ms** versus approximately
**0.00016 ms** for reading an already prepared in-memory byte string. These numbers are
CPU-work references only. They do not measure Apache, Nginx Proxy Manager, disk cache,
public network delivery or 4get.ca.

Raw local references are in `audit/local-search-hotpath-profile.json`.

## Preservation and bug-fix boundary

The preservation report confirms these critical paths are byte-identical to the v0.9.28
baseline: Google/CSE adapter and protocol helper, Binternet, RSS news, local-picture
controller, image motion/infinite-scroll controllers, Dockerfile, operator-theme tool and
Redlib attribution helper. Existing Google/CSE code was deliberately left untouched
because this iteration reproduced no new Google-specific defect requiring a code change.

Private historical Lain/SecOps originals and derivatives remain outside Git and all public
source releases, including Codeberg. External Redlib instances remain identified as
independent third-party services, not Security Ops.

The manual benchmark source is also byte-identical to the operator-supplied file.

## Focused validation

Focused checks completed successfully in the available authoring runtime:

- `tests/static-home-regression.py`: **7 passed**.
- `tests/search-hotpath-regression.php`: **8 assertions passed**.
- isolated `tests/search-transport-regression.php`: **41 adapter-path assertions passed**
  plus its existing bootstrap/token cases with explicit cURL doubles.
- `tests/home-performance-regression.py`: **8 passed**.
- `tests/delivery-profile-regression.py`: **6 passed**.
- `tests/deploy-version-regression.py`: **10 passed**.
- `tests/publication-v0.9.29-regression.py`: **28 passed**.
- news audit contract/runtime and audit-runner focused suites passed after their expected
  total-command count was advanced to 76.
- current configuration/version regression passed for application 0.9.29 / asset 33.

Source syntax checks passed for **164 PHP**, **76 Python**, **6 JavaScript/CommonJS** and
**5 POSIX shell** files. Three source Fish files could not be parsed because Fish is not
installed in the authoring environment; native Fish parsing remains mandatory on the VPS.

A local Black homepage capture and mobile capture were produced from actual PHP-generated
HTML with bundled resources embedded. Direct browser navigation to localhost is blocked by
the managed authoring-browser policy; no policy was disabled. The captures are therefore
local renderings, not live VPS screenshots, Lighthouse evidence or competitive benchmark
results.

## Complete source-suite run

The final `sh scripts/test.sh --keep-going` contains **76** mandatory commands. It was run
against the final source tree in the authoring environment. Result:

| Result | Commands |
| --- | ---: |
| Returned zero | **56** |
| Returned nonzero | **20** |
| Runner exit | **1** |

The nonzero commands are retained in `audit/source-results-v0.9.29.json` and the complete
build-kit audit log. They are not relabelled as successful tests. The environment lacks
Fish and PHP curl, DOM/XML, mbstring, APCu and Imagick. The native static-home Apache test
also lacks its required Alpine httpd/curl runtime. Representative failures are explicit
missing-function/class errors such as `mb_strcut`, `curl_init`, `DOMDocument` and `Imagick`,
plus the native-runtime dependency gate. The HTTP search suite returns an assertion failure
in this dependency-limited environment rather than exposing a successful native search run;
it is not counted as passed.

Therefore this authoring run is **not a complete native Docker audit pass**. The VPS must
still run the isolated production-like audit with its actual extensions and Fish before
cutover. Missing dependencies were not stubbed or bypassed to manufacture a pass.

## Packaging and deployment boundaries

The v0.9.29 package/publication regression uses temporary Git repositories and mocked forge
APIs. It covers annotated-tag/source provenance, deterministic archive reruns, conflicting
tag/asset preservation, one-host publication selection, Forgejo multipart uploads and
private-token boundaries. The real source archive and recovery bundle are generated later
from the operator's actual annotated tag; fixture commit/tag identifiers are not published
as user release identifiers.

Deployment retains the existing isolated audit, candidate readiness, RSS, Binternet and
requested Google web/image checks, replacement verification and rollback. It does not add
benchmark-specific behavior or change Nginx Proxy Manager. Live provider availability,
production Docker execution, authenticated remote publication and public competitive
benchmarking were not performed during authoring.

## What determines the next optimization

After guarded deployment, rerun the unchanged benchmark from the same client/network and
compare the new securityops.co median TTFB and complete HTML delivery against a simultaneous
4get.ca sample. If the remaining difference is still mostly present before the first byte,
profile the public DNS/TCP/TLS and reverse-proxy path separately from the already-small PHP
processing time before making another application change. Do not remove working search or
privacy features merely to improve a homepage benchmark.
