# SecuritySearch v0.9.24 — validation scope

## Evidence, not inferred uptime

The operator's diagnostic, observed 2026-09-10T18:38:52Z from the existing audit
image sharing the service's network namespace, showed two HTTP-200 HTML pages
without Redlib result structure and with challenge indicators, plus Nadeko HTTP
418 on feed and search. cURL returned zero and TLS verification returned zero for
those responses. The report does not contain full HTML and does not establish the
specific challenge product, perpetual unavailability, or other sources' uptime.
Production's ID, start time and restart count were unchanged in that report.

The new default is publisher News RSS, not Reddit. We do not claim Redlib itself
is fixed. The gate now requires actual nonempty RSS headlines AND keyword results
from one source on the VPS. It never approves a challenge, a cached result, a feed
without working search, or an HTTP-200 HTML page. Google/Bing public RSS endpoints
remain best-effort dependencies; authoring network access did not verify them.

## Tests and boundaries

The complete 48 previous test commands remain in order with their arguments.
Seven new commands make 55. Explicit Reddit tests now select `scraper=reddit`
because the fresh default intentionally changed to RSS; their assertions,
continuation semantics, no-network tripwires and original timeouts are retained.
Default RSS has separate native XML and HTTP/API tests. The old Redlib deployment
report tests remain for the optional diagnostic, while main's real acceptance
requires RSS and still checks Binternet before cutover.

Focused authoring checks passed: 66 news-source/error/selection assertions,
47 response-policy assertions, 16 cache/fallback orchestration assertions,
ten RSS deployment-report test methods and 27 version-specific publication tests.
These use offline callbacks, mocked transport or temporary Git histories where
stated. They are not evidence of upstream availability or real Docker operations.
The native RSS parser/HTTP tests require real DOM/mbstring/APCu; missing extensions
are never replaced by mocks to call a native audit successful.

The complete keep-going runner was executed from source-only archives both without
and with a synthetic operator pack. The authoring runtime lacks native PHP curl,
DOM/XML, mbstring, APCu, Imagick and fish, so it cannot pass the complete suite.
Exact counts, exits, logs, syntax results and final application/package workflow
evidence are in the update kit's `AUDIT.md` and `audit/` directory. The same runner
must pass in the VPS's native audit image. No command was removed or made optional.

## Security and performance assertions

RSS uses the existing validated public-IP resolution, DNS pinning and certificate
checks. New fixed-destination requests disallow redirects, cap responses at 1 MiB,
reject non-XML/challenge responses, and preserve sanitized native HTTP/cURL codes.
The XML parser rejects DTDs/entities/NUL/invalid UTF-8/XInclude/excess structure;
external resource loading/substitution and huge-parser modes are not enabled.
Only headlines, publisher/date and safe HTTPS source links are rendered. It never
fetches full articles, remote thumbnails or destination links. News API successes
now explicitly carry private/no-store and nosniff/noindex headers.

Only query-free public headlines can be cached, separated by source and edition.
Freshness is 120 seconds; stale display is bounded to 600 seconds and labelled.
A 12-second public refresh lock avoids redundant work without background tasks.
Keyword queries are not cached. Positive DNS metadata adds only two fixed hosts
to the existing 15-second-bounded cache. Refusals/challenges cool a host for ten
minutes; rate limits use at least five minutes and bounded Retry-After. Successful
empty queries do not trigger fallback. Explicit source choices disable fallback.

These are work bounds and callback-count improvements, not a measured all-provider
latency improvement. Initial synchronous DNS is not bounded by cURL alone; the
live CLI probe has a separate 30-second process timeout. Native XML processing and
actual external responsiveness remain subject to VPS validation.

## Preservation

Browser-local picture and image-animation controllers, private-theme validator,
Docker image recipe and Settings are unchanged. No private historical artwork is
in the new patch/kit. It remains outside Git and source releases, including Codeberg,
and is included only via explicit `--theme-assets` in the private deployment archive.
Existing tags, backup snapshots, running services and remote releases are not
rewritten or deleted. No VPS cutover, remote push or release upload occurred while
preparing the kit. Mocked 'verified' and 'deployment healthy' messages are test output.

Primary parser reference: https://www.php.net/manual/en/libxml.constants.php
