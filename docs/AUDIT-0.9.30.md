# SecuritySearch v0.9.30 — executed audit

Application **0.9.30** · asset **34**.

## Scope

v0.9.30 changes the Apache/PHP runtime split, favicon admission/cache policy, version/release
metadata, documentation and their tests. Search-provider implementations are intentionally
unchanged. `audit/preservation-v0.9.30.json` verifies twelve critical Google/CSE, Brave,
Binternet, RSS, browser-local-image, Redlib-attribution and private-media paths are
byte-identical to the v0.9.29 baseline.

## Focused regressions

- favicon admission/privacy: **23 assertions passed**;
- cached favicon / placeholder HTTP policy: passed with an actual local PHP server;
- event-MPM/FPM source contracts: passed;
- source-derived deployment version/readiness: **10 tests passed** after the offline fixture
  was updated to model the new FPM identity probe;
- deployment transaction suite passed after adding the FPM readiness result to its mock;
- archive/checkout/private-pack deployment-import suite: **11 tests passed**;
- view-resource suite: **17 tests passed** after narrowing its startup-block fixture so it
  tests resource generation rather than accidentally attempting to launch PHP-FPM;
- v0.9.30 release/package suite: **28 tests passed**;
- Redlib ownership/attribution suite: **10 tests passed** with the required bilingual notice
  and historical-tag statement preserved.

These fixture corrections preserve the production checks; they do not bypass the new
runtime requirement.

## Complete source command set

Every one of the **81 mandatory `run_test` commands** was attempted against the final source
during authoring, including older release-regression suites. **60 returned zero; 21 were
nonzero/blocked by missing native authoring dependencies.** Exact command classification is
stored in `audit/source-results-v0.9.30.json`.

The blocked/nonzero set is limited to missing Fish; missing native PHP curl, DOM/XML,
mbstring, APCu or Imagick; the native Alpine httpd/curl static-home check; and the newly
mandatory `php-fpm84`/Apache module check. Missing dependencies are not reclassified as
successes.

The authoring environment cannot therefore claim a complete native production-image audit.
The supplied deployment still builds an isolated derivative of the actual candidate and must
pass the full suite there before production is touched.

## Syntax

Individual syntax checks passed for **166 PHP files, 82 Python files, 6 JavaScript/CommonJS
files and 5 POSIX shell files** in the final authoring tree. Three Fish files could not be
parsed locally because Fish is unavailable; the user-facing wrappers perform native Fish
syntax validation on the operator machine and the VPS audit validates source Fish scripts.

## FPM/event safety

The candidate defaults to `SECURITYSEARCH_PHP_RUNTIME=fpm`. Its entrypoint:

1. generates normal configuration and public render resources;
2. removes the mod_php Apache include only for FPM mode;
3. enables event MPM and loopback proxy_fcgi;
4. validates the FPM and Apache configuration;
5. starts FPM and waits for loopback readiness;
6. starts Apache only after those checks pass.

Candidate readiness independently verifies the runtime marker, event MPM, absence of
`php_module`, the FPM pid and process. Docker health requests both `/` and PHP-backed
`/settings`. The old production container is not altered before the candidate gates pass.
The explicit prefork fallback remains packaged for operator recovery but normal deployment
does not silently downgrade to it.

The PHP-FPM pool retains a 16-child ceiling, equal to the prior prefork PHP-worker ceiling.
Apache event workers are separately bounded. This is an architectural concurrency change,
not evidence of a particular public latency improvement.

## Favicon safety

Remote favicon fetching is cosmetic and is bounded to a 2.5-second total request budget.
When APCu is available, only four remote favicon refreshes may be in flight and duplicate
host refreshes are leased. Failures receive a 60-second negative cache. Lease/negative keys
contain SHA-256 host digests, not visitor queries, result bodies, cookies or credentials.
Writes remain atomic and existing image validation/fallback behavior remains.

## Deployment gates and limits

The VPS still must pass the complete native audit, candidate readiness, live RSS news check,
live Binternet check and requested Google web/image verification before cutover. Rollback
remains transactional. The manual competitive benchmark is not an acceptance shortcut and
was not run while building this release.

No VPS deployment, Nginx Proxy Manager modification, remote push, release upload, tag move,
backup deletion or private-artwork publication occurred during authoring.
