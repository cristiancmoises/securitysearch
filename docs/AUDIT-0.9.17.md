# v0.9.17 — infinite image scrolling audit and validation

Prepared 2026-09-09 from v0.9.16 commit `62cecd5`. This report covers the pagination change and a rerun of the established source regression suite. It is not a penetration-test or accessibility certification. Earlier source audit context remains in [v0.9.15](AUDIT-0.9.15.md); its no-JavaScript/timer architecture has been superseded by this release.

## Changes and review findings

- Removed the Automatic pages panel, timer/Refresh navigation, encrypted result snapshots and frame transition helper. Old frame URLs fail with HTTP 410 and a fresh-search action without a provider call. Normal pagination tokens remain unchanged.
- Added one image-only progressive enhancer: **9,818 bytes** source, **3,188 bytes** with reproducible gzip for measurement. Actual transfer compression depends on the serving configuration. No runtime dependency or third-party script is added. Home, Settings and other pages keep script/fetch restrictions; image search allows only same-origin scripts/fetch, with inline handlers and workers blocked.
- HTML and JSON share a normalized, server-validated image model. The client receives data and creates elements/text; it does not parse or inject provider HTML. All preview/action URLs must use the local proxy. Continuations must stay on `/images` at the same origin, with credentials/redirects/repeated tokens rejected. Malicious title text remains literal.
- One request is active at a time. The Next link loses its single-use URL while busy. Errors, aborted requests or ambiguous responses offer a fresh search instead of retrying a potentially consumed token. Existing images stay visible. A separate read-only review found that rejected bodies also needed cancellation; the catch path now aborts the request and regression tests verify that behavior.
- The observer follows the last image card; Filmstrip uses its horizontal root plus an on-screen check. Further scrolling is required after each completed request. Hidden tabs issue no new request, although an existing request may finish. No viewport animation, polling, history replacement or navigation timer is present.
- Each provider page is capped at 24 entries. A response has a 1 MiB client body limit and a 25-second deadline. Appended images are lazy/async/low priority. Before the next full page would exceed 480 retained cards, automatic loading stops with native continuation. No focused/read cards are removed. Empty pages preserve manual continuation where present.
- The saved Settings opt-out omits the script. Missing browser features, JavaScript disabled and Save-Data preserve native navigation. The compact search controls, Tron default, saved themes, provider integrations, quality/format choices, navigation and footer remain.
- Deployment readiness now checks marker 21, Tron, homepage content/CSP, image CSP and the new static asset. Release checks allow exactly the runtime enhancer and source-only Node test; timer helper requirements are removed. Docker excludes tests and operator tools.

## Executed validation

`sh scripts/test.sh` passed with eight PHP regression suites, the Node pagination state suite, the stateful Docker transaction simulation, localhost HTTP controller fixtures and synthetic template/parser benchmarks. Node tests execute the actual enhancement in a minimal synthetic DOM; they are not browser tests.

Coverage includes native action routing, theme preferences, input/URL validation, proxy SSRF and credential safeguards, provider parsing/cooldowns/deadlines, API errors, configuration generation, streaming byte limits, rendering escaping, and the new pagination behavior. The Docker simulator exercises successful cutover, replacement readiness failure, cleanup failure, lost candidate/replacement creation responses and lost rename response, plus candidate readiness failures and masking mounts. No Docker daemon is used by those simulations.

The HTTP fixture exercises all six views, the script opt-out and retained Lain preference, private JSON responses, fourteen consecutive appended provider pages followed by native navigation, query/format/date/quality preservation, empty/final responses, provider 503 recovery, no Refresh header and obsolete-frame rejection. It sends no external search requests. Node tests additionally cover single-flight observer/click overlap, loading beyond ten pages, literal hostile text, lazy priorities, short-page loop prevention, hidden/offscreen Filmstrip, unsupported/Save-Data fallback, the DOM budget, unsafe/repeated continuations, malformed data, redirects, wrong content type, failed status, declared/streamed body limits, timeout and page-leave cancellation.

All **104 PHP files** passed syntax checks. Fish and shell syntax checks passed. Image/shared/Tron stylesheets parsed, including nested media/supports rules. Source whitespace checks passed.

### Performance measurements

Local homepage: **50/50 successful HTTP responses**, concurrency 4 with four PHP workers, median **2.03 ms**, p95 **6.09 ms**. These are loopback PHP timings, not browser, upstream-search or VPS response times and not a controlled speed comparison.

| Synthetic workload | Median | p95 |
|---|---:|---:|
| Render homepage, 1,000 iterations | 0.0698 ms | 0.0889 ms |
| Parse 50 Invidious videos, 1,000 iterations | 0.0756 ms | 0.1038 ms |
| Parse 50 Binternet images, 1,000 iterations | 0.2529 ms | 0.4136 ms |

Peak PHP allocation reported by those benchmarks was 2 MiB. The template benchmark also confirmed byte-identical warm-cache output. These measurements exclude image downloads, browser memory/painting, provider requests, TLS/NPM and VPS conditions.

## Remaining limits

Real-browser scrolling, layout/scroll anchoring, mobile behavior, keyboard/assistive-technology acceptance and browser memory measurements were not performed. Local validation uses PHP 8.3; the Docker target is Alpine 3.24 / PHP 8.4 / ImageMagick 7. Actual Docker build, live VPS cutover/rollback, mount compatibility and provider availability remain deployment checks. The supplied script performs candidate/readiness checks when run on the VPS; it was not executed against the server here.

A user-defined NPM CSP can independently block the enhancement. Upstream rate limits persist. Ordinary backend token consumption is not atomic across separate tabs/processes; client single-flight covers normal in-page requests but cannot eliminate a two-tab race. A failed request may already have consumed its token, hence explicit restart recovery. Native browser history returns to the original query/page; accumulated cards are not persisted outside any browser back-forward cache. Retain private backups and existing volumes as described in [operations](OPERATIONS-0.9.17.md).
