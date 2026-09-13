# SecuritySearch delivery and performance review

## Findings

The release fixes two reproduced HTTP/rendering defects, removes a synchronous geometry read from
filmstrip scrolling, adds bounded failure-trace evidence, and corrects the distinction between TTFB
and complete-document ranking. The most substantial measured reduction concerns deliberately
oversized image metadata. No public-site ranking or ordinary-homepage TTFB improvement has been
established for this release.

| Area | Evidence | Disposition |
|---|---|---|
| Image labels | A 1 MiB ampersand title generated 15,729,245 bytes of card HTML | Bound before escaping; reuse escaped text |
| Homepage representation | A denied gzip-file path returned plain error bytes labelled gzip; encoded aliases bypassed the raw-path check | Deny decoded aliases; success-only metadata; identity ranges |
| Filmstrip scrolling | The previous eligible-scroll path called `getBoundingClientRect()` | Separate asynchronous viewport observer |
| Benchmark criterion | Retained v3 sorts total HTML delivery, despite displaying a TTFB column | New v4 explicitly sorts matched-round median TTFB |
| Google verification | Earlier candidate Images 429; later production Images success; no same-run equivalence established | Preserve gates; add sanitized failure trace, not a speculative scraper rewrite |
| Supplied Lighthouse excerpt | SecuritySearch navigation appears with Intermotors/Salesmanago requests and an unattributed 60 ms reflow | Attribution unresolved; do not transplant third-party cache changes |

## Baseline and evidence boundaries

The code baseline is the repaired v0.9.40-r2 tree
`b0ab9966f02dd0c41ad8f936347b64cee5642c9f`. The retrieved GitHub main commit
`6d2a46f9300eb907b68bbcc92d5c774a30464ddc` points at that tree. The new release retains the native
configuration-restoration and per-case favicon cache-isolation repairs. It does not use the
separate, incompatible 123-command build that previously shared the v0.9.40 label.[^source]

The supplied native record contains 119 completed commands, zero attach/container exits, and
matching log-size/hash proof. That is evidence for the recorded v0.9.40 candidate execution,
not an attestation of every service on the VPS or approval of changed v0.9.41 code. The earlier
Google report records Web success with 20 results and three Images 429 responses, separated
by 60- and 120-second pauses. A later production v0.9.30 Images diagnostic records 20 results,
two HTTP 200 transport operations and approximately 2.345 seconds overall. The different times,
configuration/session state and Web-then-Images versus Images-only sequences prevent causal
attribution to an application regression from those records alone.[^records]

The external release validation report identifies exactly which tests ran and which prerequisites
were unavailable. Synthetic provider responses, localhost servers and reconstructed Git fixtures
are not production deployments. The benchmark self-tests prove arithmetic, classification and
reporting behavior; their synthetic winners are not measurements of any public search engine.

## Interpreting the supplied Lighthouse material

The attachment reports a 60 ms forced reflow without an attributable source, an initial
SecuritySearch navigation value of 1,123 ms and 10.89 KiB, and a maximum critical-chain latency of
3,820 ms. The chain then names Intermotors login/menu/cart endpoints; cache entries name
Salesmanago, an Intermotors tagging host and social SVG resources. Those are materially different
origins from the stated initial navigation. The fragment does not contain the complete trace,
request initiators, final URL history, capture environment, or per-site benchmark samples.[^upload]

Consequently, the 1,123 ms value is not relabelled as a verified SecuritySearch TTFB. The chain is
not treated as evidence that SecuritySearch intentionally loads those trackers. The known foreign
hostnames are absent from the reviewed homepage template, page renderer, home-style helper and
infinite-scroll script. The configured anonymous snapshot has a no-script policy. These are source
observations, not independent proof of what every browser extension, service worker, custom proxy
or particular captured deployment did. No tracker-preconnect hints were added.

Chrome's reflow guidance concerns geometry reads after DOM/style invalidation. It supports
investigating the actual `getBoundingClientRect()` call found in the filmstrip, but it does not
identify that call as the source of the attachment's unattributed time. A new clean-profile trace
with its JSON and request-initiator information is needed before making that connection.[^reflow]

## Where TTFB can change

Browser-observed first-byte delay includes more than PHP execution. DNS, connection establishment,
TLS, redirects and request/response travel can precede server processing. Google’s TTFB guidance
distinguishes origin work from these connection costs. A local server speedup cannot be interpreted
as an equal reduction for all remote clients.[^ttfb]

SecuritySearch already builds an anonymous homepage at startup and serves it through Apache
without invoking PHP when the canonical request has no query, cookie or authorization. A gzip
sidecar avoids recompressing that representation on each eligible request. FPM handles dynamic
requests in the existing event-MPM configuration. This release preserves those paths rather than
adding an empty page, premature success headers or a benchmark-specific response.[^code]

The deployment behind Nginx Proxy Manager is an additional layer, but its current effective
configuration, connection reuse, geographic path and queueing have not been independently measured
here. Raising thread/worker counts, changing all Docker networks or applying generic system-wide
TCP settings without those measurements risks resource pressure while leaving the dominant delay
unchanged. The release therefore does not perform such broad tuning.

For interpreting a future measurement, a large DNS/TCP/TLS component points to delivery topology
or connection costs; a large post-connect wait with a verified static snapshot points elsewhere
than image-result rendering. Personalized/search requests must be evaluated separately because
they intentionally include dynamic behavior and upstream provider time. HTML download time,
first-byte time, useful-result arrival and rendering are separate outcomes, not interchangeable
labels for whichever number looks best.

## Bounded image metadata

`lib/image_results.php` already bounds page admission to 24 cards and inspects at most 32 supplied
sources per card. It validates remote URLs, selects useful previews and retains the original
image action. Those limits did not bound the title before three HTML-escaped copies were produced.
An ampersand-heavy provider title amplified into a response much larger than the input and also
remained large in the append-JSON representation.[^code]

The revised helper examines at most 1,024 title bytes, cuts a valid UTF-8 prefix, normalizes ASCII
control whitespace and uses a fixed fallback when the retained label is malformed or blank.
A bounded source-host label is retained as well. Escaping occurs after the bound, once, and that
escaped value is reused in the three HTML positions. The original URL, preview URL, image dimensions,
source link and animation actions are not replaced with placeholders to obtain a smaller timing.

The new PHP suite exercises malformed types, blank/control-only strings, literal `0`/`all`/`any`,
multibyte boundary cases, escaping, JSON parity and bounded page output. It executes without
network requests or substitutes for missing native PHP extensions. The old preview-selection
suite continues to exercise tiny placeholders, dimensions, preview/original modes and animation.

| Local synthetic metric | v0.9.40-r2 | v0.9.41 code |
|---|---:|---:|
| One-card HTML for a 1 MiB ampersand title | 15,729,245 bytes | 15,965 bytes |
| Median rendering time, seven independent CLI runs | 69.617 ms | 0.422 ms |
| Peak PHP process allocation in those runs | 35,663,872 bytes | 4,194,304 bytes |
| Large-title localhost median header arrival, 14 measured GETs | 56.949 ms | 0.997 ms |
| Ordinary-title localhost median header arrival, 14 measured GETs | 0.440 ms | 0.549 ms |

The HTTP experiment alternated versions, discarded one warmup per case and read the complete
body. It used the actual shared renderer behind local PHP fixture servers, not production
controllers, provider transports, TLS or NPM. Header arrival is a local first-response approximation,
not browser field TTFB. The ordinary response stayed 662 bytes and did not demonstrate improvement.
The results support a bounded-work/resource-exhaustion fix, not a blanket speed multiplier.[^measure]

## Filmstrip observation

The previous filmstrip guard read the grid rectangle while handling eligible scrolling, because
an explicit horizontal-root intersection alone did not establish whether the strip was in the
viewport. This was a reasonable correctness concern but introduced a synchronous geometry query
into a potentially high-frequency path.

The revised code keeps the explicit-root observer for approaching the end of the strip and adds
one implicit-root observer for viewport visibility. Both conditions and deliberate scrolling must
hold before another page loads. Intersection Observer provides asynchronous intersection information;
its specification explicitly contrasts this with repeated synchronous layout queries.[^observer]

The new test drives 1,000 offscreen scroll events with a geometry getter that would fail if called.
It then changes viewport visibility and checks that exactly one load occurs. Stop/disconnection,
manual Next links, single-flight continuation use, response-size limits, maximum card counts and
Save-Data fallback are preserved. This establishes the removed call path under deterministic
fixtures. It does not claim a measured 60 ms reduction or universally eliminate browser layout.

## HTTP representation correctness

The old Apache restriction inspected a literal raw request path. A percent-encoded spelling
could reach the decoded generated-file route. In addition, metadata attached with unconditional
`Header always` rules described some denied responses as publicly cacheable gzip static-home
successes even though the returned error bytes were not gzip. The directly accessible document
was a public homepage, not a private configuration file; the finding is not described as a leak
of credentials or private search history.[^code]

The repaired guard checks the decoded rewrite target while allowing only the canonical root
request to reach the internal rewrite. Success-only expressions now gate static-home encoding,
cache metadata and the static marker. Errors are no-store. The representation tests execute the
real Apache modules over an isolated localhost listener, including literal/encoded aliases,
GET, HEAD, identity, gzip, personalization boundaries and byte ranges.[^headers]

Range requests now select identity bytes consistently with the existing static-asset policy.
HTTP can legitimately define ranges on a selected coded representation; the old compressed
range is not automatically a protocol violation merely because a truncated gzip member cannot
be independently decompressed. The release chooses the simpler identity-range contract and tests
that the body and Content-Range match it. Cookies, queries and authorization still disqualify the
public homepage fast path; no search or personalized-response cache is introduced.[^http]

## Google availability and diagnostic limits

The Google scraper, protocol and provider transport remain unchanged. A 429 still blocks live
candidate acceptance. Neither an earlier native audit nor a later production search can be
substituted for successful candidate Web and Images results. RFC 6585 does not define a universal
quota-reset time or a specific rate-limit key, and Retry-After is optional.[^429]

When a probe fails, the gate now retains at most 12 validated transport records: fixed stage,
HTTP status, cURL error number, body-byte count and bounded numeric duration. Provider identity
must be Google. URLs, arbitrary headers, body contents, query text, exception text and extra
fields are not reflected. Invalid traces are omitted, never used to approve the request.

A trace such as transport 200 followed by transport 429 can narrow the next investigation without
triggering another diagnostic request. It still cannot prove the internal reason for the provider's
restriction. Existing three-attempt limits, 60/120-second fallback waits, long Retry-After deferral,
challenge/refusal handling and Google-only nonempty-result requirements remain.

## Reproducible public comparison

The retained v3 tool reports TTFB but sorts the winner by total HTML delivery. Thus, when that tool
is used, its winner is not automatically the TTFB winner. No actual benchmark CSV was supplied to
establish which criterion produced a particular 4get.ca ranking. The new tool names the criterion
explicitly and keeps v3 available unchanged for historical comparison.

V4 uses curl `time_starttransfer`, reports `time_total` separately, and performs complete verified-
TLS HTTPS GETs. Fresh processes mean new connections, not guaranteed cold DNS/TLS caches. Curl
connection reuse and timing definitions are documented independently of browser rendering; no
JavaScript or external subresources are measured by this HTTP tool.[^curl]

Seven fixed targets receive sequential, balanced rounds with the same client options, no query
parameters, no cache-busting, no credentials and no retries. Challenges/429s stop further requests
to that target. A full seven-target claim requires all seven to qualify; otherwise the scope is
explicitly narrower. Duplicate records, missing/nonpositive/nonfinite/reversed timings, insufficient
matched rounds and interrupted runs cannot quietly become a winning sample.

The final hostname must match the named service or its `www` counterpart. A redirect to an
unrelated site is excluded rather than awarded a fast time under the original name. Informational
responses are excluded from the final-document ranking because the recorded first byte may belong
to an interim response. Heuristic gate detection is not proof of content equivalence, and no
statistical significance or geographic universality is claimed.

## Remaining work and release decision

The code audit is not proof that every possible bug has been found. Its strongest evidence is the
reproduced defects, narrow reviewed changes, negative tests and retained native/live gates. The
current release contains 124 mandatory commands, with the old 119 exactly preserved. Current
runtime hashes are verified before approved historical reconstruction, so historical-preservation
checks still reject unexplained source drift rather than silently accepting changed expected hashes.

The next production decision is gated deployment of the exact package, not editing evidence or
publishing a reassuring status. Public TTFB improvement requires matched measurements of the new
served release. A large latency gap after static-route verification would justify focused analysis
of delivery topology, proxy connection behavior or resource contention; it would not justify
repeated upstream search probes or caching private queries. Those are measurement-dependent
follow-on changes, not hidden promises embedded in this release.

## Sources

[^source]: GitHub, `cristiancmoises/securitysearch` main read; commit [6d2a46f9300eb907b68bbcc92d5c774a30464ddc](https://github.com/cristiancmoises/securitysearch/commit/6d2a46f9300eb907b68bbcc92d5c774a30464ddc). Tree verified against the retained r2 source.
[^records]: Supplied `offline-audit-result.json` and `google-live.json` excerpts for `/root/securitysearch-backups/20260913T001240Z`, plus the later v0.9.30 Images probe printed in the conversation. These were supplied records, not newly fetched VPS evidence.
[^upload]: `Pasted markdown(4).md`, supplied Lighthouse excerpt, lines 6–68. Its full original report/trace and benchmark CSV were not supplied.
[^reflow]: Chrome for Developers, [Forced reflow](https://developer.chrome.com/docs/performance/insights/forced-reflow), official performance insight.
[^ttfb]: web.dev, [Optimize Time to First Byte](https://web.dev/articles/optimize-ttfb), official guidance.
[^code]: Reviewed source: `lib/image_results.php`, `static/images-infinite.js`, `lib/build_home_snapshot.php`, `docker/docker-entrypoint.sh`, `docker/apache/{http,https}/httpd.conf`, `docker/apache/fast-home.conf`, `scripts/deploy-ionos.py`, and the retained/added tests in this release.
[^measure]: Release-kit evidence: `render-before-after.json`, `http-before-after.json`, and `local-http-render-comparison.json`. Synthetic localhost experiments; raw samples and scope are retained.
[^observer]: W3C, [Intersection Observer](https://w3c.github.io/IntersectionObserver/), Editor's Draft of 19 March 2026; asynchronous position/visibility model, not a final Recommendation claim.
[^headers]: Apache HTTP Server, [mod_headers](https://httpd.apache.org/docs/current/mod/mod_headers.html) and [mod_rewrite introduction](https://httpd.apache.org/docs/current/rewrite/intro.html), official module documentation.
[^http]: IETF, [RFC 9110: HTTP Semantics](https://www.rfc-editor.org/rfc/rfc9110.html), representation metadata and range semantics.
[^429]: IETF, [RFC 6585, section 4](https://www.rfc-editor.org/rfc/rfc6585.html#section-4), HTTP 429 Too Many Requests.
[^curl]: curl project, [curl manual](https://curl.se/docs/manpage.html), `--write-out`, `time_starttransfer`, `time_total`, and connection reuse.
