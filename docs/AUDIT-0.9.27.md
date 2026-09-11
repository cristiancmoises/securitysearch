# SecuritySearch v0.9.27 — validation record

Application 0.9.27 / asset31. Baseline is the exact v0.9.26 source tree
`eb8b51a5c9c89b7052009d2b00aed91f7819bf61`, also observed at the public GitHub
commit `d400152a04b49d3c5c52cb291e9dafe1680f89c2`. Authoring commits and tags
are local disposable fixtures, not claims of publication to the user's forges.

## Implementation and preservation

Fixed public unrendered template/CSS/theme metadata is compiled at container startup
and retained by the existing OPcache. A small shared page renderer avoids loading
search-result rendering on the homepage and skips unused style/footer work for
partial templates. The original dynamic renderer is available on explicit opt-out,
missing/invalid artifacts or build failure. No rendered-page cache, new query cache,
benchmark user-agent branch, provider request, NPM change or worker tuning is added.

All search adapters, CSE parsing/session fixes, DNS/TLS/deadline safeguards, RSS,
Redlib ownership notices, static UI/CSS, browser-local picture and motion controllers,
private-artwork preparation policy and the supplied manual benchmark are preserved.
The Docker recipe is unchanged; its entrypoint gains only resource generation before
Apache startup. Configuration and release markers advance to asset31 / v0.9.27.
Existing startup failure fallback and deployment acceptance/rollback paths remain.

Generated resources contain fixed public source strings and URLs, not configuration
values, per-user replacements, cookies, queries/results, photographs or private
operator artwork. The artifact is deployment-owned PHP, not untrusted input. Only
an explicit process environment value enables it; link/mode/owner/size/revision/
version checks refuse an untrusted or incompatible artifact. The builder quotes
source as literals, uses fixed paths, bounds inputs, and publishes atomically.
The process treats source as immutable until rebuild/restart, matching the existing
OPcache timestamp setting. Git, Docker input and public packages exclude artifacts;
new package checks also refuse force-tracked generated files or outgoing history.

## Executed focused checks

17 new resource/renderer methods pass with actual PHP. They cover all eighteen
bundled choices and three invalid/old aliases, exact compiled/dynamic HTML parity,
no full frontend/scraper loading on the homepage, preserved frontend::load behavior,
current config/Redlib primary, private-pack overlays, cross-render isolation, input
validation, missing/stale/malformed artifacts, unsafe modes, linked input/output,
atomic preservation on unsafe CSS, deterministic builds, and explicit opt-out.
Six theme cases use actual local PHP HTTP responses: CSP/private-no-store headers,
no new cookies, rendered markup and mode/timing headers are checked in both modes.
Shell startup failure is mocked separately; the real PHP builder runs above.

Six diagnostic methods pass with SSH mocked: fixed readonly Docker command, JSON
schema/ranges, header allowlisting/control-character rejection, no raw body/cookie
leak, private file mode and failure exit status. This does not establish real SSH,
Docker execution, local VPS compression or live latency.

The new release/package suite passes 28 methods using mocked APIs and temporary
Git repositories, including rejection of forcibly tracked generated content,
immutable tags, exact package bytes, partial uploads, one-host retries, multipart
Forgejo attachments and token boundaries. The complete-kit workflow is additionally
exercised in disposable full-source clones; its final log is shipped with the kit.

## Local renderer experiment — not a competitive-site benchmark

Three local PHP development servers used real OPcache with identical options,
8 excluded warmups and 80 randomized sequential rounds. No external site, search
provider, Docker, Apache or NPM was involved. Results on this authoring machine:

| Mode | Median PHP app time | Median localhost HTML time | HTML bytes |
|---|---:|---:|---:|
| v0.9.26 | 0.44 ms | 1.305 ms | 39,315 |
| v0.9.27 dynamic fallback | 0.48 ms | 1.354 ms | 39,315 |
| v0.9.27 compiled | 0.20 ms | 1.068 ms | 39,315 |

The compiled path reduced this measured PHP work by 0.24 ms (about 55%); it does
not establish a comparable percentage improvement in public latency. The dynamic
fallback has a small measured overhead and remains a functional fallback, not an
asserted speed improvement. No significance claim is made. Seven themed responses
were identical after normalizing the asset number; forms, controls and visible
content were not removed. The earlier 112 ms public gap cannot be inferred to have
closed: nearly all of it preceded the first byte, including unmeasured network and
proxy costs. The optional delivery diagnostic separates origin observations later.

The user-supplied SecOps Web Benchmark 3.0.0 is retained byte-for-byte. It and its
embedded self-tests were not executed or added to the automated gate. No seven-site
leaderboard, report, winner badge or competitive result is committed/published.
The local renderer experiment is a development measurement, not that benchmark.

## Browser evidence

24 actual Chromium comparisons cover six themes, two widths and expanded/collapsed
appearance. Finite entrance animations are finished and infinite effects paused
identically to compare stable geometry; computed colors/sizes and viewport fitting
are retained. The first fixture incorrectly compared different animation instants;
it was corrected and all cases reran. No runtime CSS/animation was changed for it.
The existing picture browser fixture also passes 33 real file chooser/decoder/canvas/
preview/removal/mobile checks with compiled resources enabled.

Actual PHP-produced HTML and local assets are embedded in a controlled document
because the managed browser blocks localhost navigation. The existing policy is
not bypassed. Captures are local release renderings, not live VPS screenshots,
Lighthouse evidence, native storage-persistence or production navigation results.
No external requests or script errors were observed in the controlled fixture.

## Complete audit and limitations

All 67 prior mandatory commands remain in order with the same arguments; three new
entries bring the count to 70. The actual keep-going runner is executed in complete
source archives without .git, including the optional synthetic-private-pack layout.
Raw command exits and diagnostics are shipped in the kit audit directory. Missing
native dependencies remain nonzero; no extension substitute or skip flag is used.
The available runtime lacks PHP curl/DOM/mbstring/APCu/Imagick, fish and Docker.
The full native audit is therefore not represented as passing here. The existing
mandatory native VPS gate, readiness, RSS/Binternet checks and explicit optional
Google web AND image gate remain required before cutover.

No real VPS deployment, provider benchmark, forge write, tag movement, backup
removal or historical image recovery was performed. Test output mentioning healthy
containers or verified releases is simulated and is not a production event.
Keep every existing backup/rollback directory and the external operator-artwork pack.

## Primary references

- https://www.php.net/manual/en/opcache.configuration.php
- https://httpd.apache.org/docs/2.4/misc/perf-tuning.html
- https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Server-Timing
- https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control
