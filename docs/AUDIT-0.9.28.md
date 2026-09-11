# SecuritySearch v0.9.28 — executed validation and limits

Application **0.9.28**, asset **32**. Base: the exact v0.9.27-r1 tree
`4749f37085fb791df67bf0f4a646c04f14b9e52c`. This release changes homepage
delivery and supporting deployment/tooling; provider/search implementations are
intentionally retained from the verified base.

## Anonymous-home fast path

Seven `tests/static-home-regression.py` methods passed. They use the actual builder,
renderer and Apache source configuration and cover:

- byte equivalence between the generated anonymous Black document and the dynamic
  anonymous renderer;
- atomic generation plus symlink refusal;
- exact GET/HEAD, query, Cookie and Authorization eligibility;
- CSP, cache and Vary policy;
- direct generated-file access policy;
- fail-open startup to the dynamic renderer;
- source/Docker/public-package exclusion;
- Tranco refresh rebuilding the public snapshot.

The native `tests/static-home-http-regression.py` requires Alpine `httpd` plus curl.
This authoring host provides Debian `apache2`, so that native integration test reports
**BLOCKED** here instead of being relabelled a pass. It remains mandatory inside the
VPS audit image, where the production Docker base supplies `httpd`.

Six rank-timer tests passed: new/repeat installation, exact managed v1-to-v2 upgrade,
modified-unit preservation, symlink refusal and refresh-failure behavior. Ten
source-derived deployment-version methods passed; they verify the actual PHP asset
value/config generator and do not duplicate a hand-written current-version literal.
Six delivery-profile methods passed with SSH mocked and validate the fixed localhost
probe, allowlisted metadata and required `static-home` identity.

## Local origin-path measurement

A local Apache 2.4 + mod_php authoring fixture used the same static eligibility and
response policy. After warm-up, sixty randomized fresh-curl samples per path measured:

| Path | median TTFB | median complete | descriptive p95 complete |
| --- | ---: | ---: | ---: |
| anonymous static | 1.871 ms | 2.013 ms | 7.016 ms |
| Cookie `theme=Black` dynamic | 3.071 ms | 3.291 ms | 14.759 ms |

The median local TTFB difference is **1.200 ms**. This is an origin-path fixture,
not a public benchmark and not evidence that the historical ~112 ms public gap to
4get.ca is closed. DNS, TCP/TLS, Nginx Proxy Manager, geography and VPS scheduling
are outside it. Raw structured evidence is `audit/local-static-home-profile.json`.

The operator's SecOps Web Benchmark 3.0.0 remains byte-for-byte manual source under
`tools/`. It was **not run** for this release and no leaderboard/winner claim is
published by the update.

## Publication/package and source validation

The v0.9.28 publication/package suite passed **28 methods** using temporary Git
repositories and mocked forge APIs. It verifies deterministic tagged source archives,
checksums, recovery bundles, single-host retries, Forgejo multipart uploads, conflict
preservation, secret boundaries, and refusal to package the generated runtime home.

Focused syntax checks passed for the new/modified Python and PHP components. Existing
source audit execution on this authoring host continues to encounter missing native PHP
extensions (curl, DOM/XML, mbstring, APCu, Imagick) and missing fish. Those failures are
not converted into successes. The native Docker audit is therefore still required on
the VPS.

All **74** `run_test` commands remain mandatory: the previous 71 entries stay in order
and this release appends static-home source tests, native static-home HTTP integration
and v0.9.28 publication/package checks. `sh scripts/test.sh --keep-going` retains a
nonzero status if any command fails.

## Security and privacy boundary

The static file is eligible only for anonymous canonical GET/HEAD `/`, with no query,
Cookie or Authorization header. Search/result pages, saved preferences and authenticated
or personalized requests remain dynamic. There is no benchmark User-Agent/IP branch.
The generated file is runtime-only, excluded from Git/Docker build context/public
release packages, served through an internal rewrite, and refused at its implementation
URL. A generation failure removes the candidate artifact and leaves the normal PHP
renderer available.

Search adapters, Google/Brave fixes, RSS, Binternet, browser-local pictures, animation
controllers, third-party Redlib ownership disclosure, Docker image recipe and the
private Lain/SecOps publication policy are not weakened by this optimization. Private
operator artwork stays outside all public release assets, including Codeberg.

## Visual documentation

The README images reuse the immediately preceding local Chromium interface capture.
The v0.9.28 anonymous snapshot preserves that visual DOM/CSS while changing delivery.
The reused image is labelled as such; it is not presented as a new VPS capture,
Lighthouse run, or competitive benchmark result.

## Mandatory production acceptance

A real deployment must still pass the complete isolated native audit, candidate
readiness (including `X-SecuritySearch-Render: static-home`), fresh RSS feed and keyword
search, live Binternet, requested Google web+image verification, replacement readiness
and rollback checks before cutover. Keep every printed backup/rollback path.
