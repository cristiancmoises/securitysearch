# Security Search v0.9.25

[English](README.md) · [Português do Brasil](README.pt-BR.md)

A PHP proxy metasearch engine based on [4get](https://git.lolcat.ca/lolcat/4get),
maintained for [SecurityOps](https://securityops.co). Normal search and bundled themes
**work without JavaScript**; local pictures and image enhancements use optional
same-origin scripts. Application **0.9.25**, asset marker **29**.

## Homepage performance

The default Black homepage embeds three small **homepage-only** stylesheets. It no
longer waits for `style.css`, `experience.css` and `Black.css` requests before first
paint. Other themes retain their selected external theme stylesheet; search and
settings pages retain the full shared CSS. Missing bundled inline CSS falls back
to the original external stylesheet rather than an unstyled page.

The existing 400 × 86 WebP logo is recompressed from **11,528 to 5,710 bytes**.
Its explicit dimensions, eager loading and high fetch priority are retained; its
preload now appears near the start of the document. No third-party preconnect,
JavaScript CSS loader, onload handler, or new font is introduced.

Reference local bytes (gzip comparison, **not measured production transfer or LCP**):
HTML plus render-blocking CSS is 15,796 → 9,866 bytes. HTML alone increases
7,771 → 9,866 compressed bytes; this deliberately trades a slightly larger document
for three fewer blocking requests and much less homepage CSS. Browser fixtures
check desktop/mobile geometry and colors. Lighthouse's estimated milliseconds are
not claimed as an observed speedup. Re-run Lighthouse after deployment.

The developer-only `scripts/build-home-css.py` regenerates the subsets using
PHP, tinycss2, cssselect2 and lxml. Those packages are not runtime dependencies.
The mandatory suite verifies generated CSS input/output hashes; structural changes
also need a browser parity review.

## Google and Brave reliability

Google remains the default web/image provider. A failed new first page may use
**one visibly identified Brave fallback** within the existing shared deadline.
Explicit non-Google providers, successful empty results and continuations do not
silently switch provider. No third provider is queried automatically.

Google/Brave now retain safe transport failure categories and honor bounded
`Retry-After`. Query-free, egress-specific APCu metadata briefly cools transport
failures (5 seconds), rate limits (at least 60 seconds) and refusals/challenges
(at least 120 seconds), with a one-hour maximum. No private query/result is cached.
Parser/format failures do not globally blacklist the provider.

Brave does not rotate proxy addresses to retry a challenge. A 502/503/504 without
Retry-After or a detected challenge may get at most one retry on the **same egress**
within the existing deadline. Google retains its one same-egress transient retry.
All TLS checks, no-redirect rules and 4 MiB response caps remain.

Google bootstrap contention waits at most two seconds for another worker instead
of tying up a request for the old long wait loop. Narrow JSONP/Svelte bootstrap
format variations are supported without evaluating upstream JavaScript.
These are tested fixtures, not proof of today's upstream page format or availability.
**CAPTCHAs, refusals, network faults and rate limits can still produce a truthful 503.**

For an explicit post-deployment diagnostic, run `diagnose-search.fish` from the kit.
It sends a fixed neutral query to Google/Brave web/images and records bounded
status/error/timing/count fields—not query history, tokens, IPs or response bodies.
The first synchronous DNS lookup is not bounded by cURL alone; the wrapper also
uses a process timeout. No live latency improvement is asserted by offline tests.

## Image results and local pictures

Preview mode chooses the smallest genuine provider-supplied variant when dimensions
are known; an unknown-size final variant retains the provider's preview hint.
Original/high-quality modes and original-image links remain unchanged. The first
four visible cards are eager; only the first receives high fetch priority, and the
other three use normal priority. Later cards remain lazy/low priority.

Binternet modern/legacy parsing, six layouts, normal pagination, optional infinite
scroll and bounded animated previews with still posters and Play/Pause remain.
**My picture** keeps its unnamed chooser outside forms. File decoding and resizing
happen in the browser; nothing is uploaded. Session storage is the default,
Remember on this device is explicit, and Remove clears both storage locations
when the browser allows it. Blocked storage permits page-only display.

Historical Lain/SecOps originals and derivatives remain **outside Git** and outside
all public release attachments, including Codeberg. Only an explicit private
`--theme-assets` deployment includes the existing external operator pack. Tron and
the public palette/Matrix alternatives remain. Redlib attribution, RSS news,
onion link and dated Tranco footer are retained; no rank is invented.

## External providers and privacy

External Redlib instances are operated by independent third-party individuals or
organizations, **not by Security Ops**. Security Ops maintains this integration,
not those instances. Their operators control their policies and availability;
Reddit queries and enabled fallbacks reach those external operators. Our privacy
statements must not be read as promises about third-party services.

News RSS remains the separate default, using Google/Bing publisher headlines.
Only query-free public headlines use its bounded cache; keyword searches do not.
The verified RSS source/edition and existing live RSS/Binternet deployment gates
remain unchanged. RSS and other upstream endpoints are best-effort dependencies.

## Apply, deploy, publish

Extract the complete `securitysearch-update-0.9.25` kit into `~/Downloads`:

```fish
fish ~/Downloads/securitysearch-update-0.9.25/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.25/deploy-securitysearch.fish \
    ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Accepts the exact clean attribution-hotfix tree or v0.9.24-r1 tree. Both paths
include the attribution correction. No reset, stash, force-push, or tag movement.
SSH uses `root@securityops.co` port **5119**, preserving `172.17.0.1:5140 → 80`,
Docker networks and compatible private settings without recreating NPM. All native
offline tests, candidate readiness, RSS and Binternet checks must pass before
cutover. Keep every backup/rollback directory and the external theme pack.

```fish
fish ~/Downloads/securitysearch-update-0.9.25/publish-securitysearch.fish ~/securitysearch
# Retry one host, INCLUDING its release tarball/checksum:
fish ~/Downloads/securitysearch-update-0.9.25/publish-securitysearch.fish \
    ~/securitysearch --host git.securityops.com.br
```

The new annotated tag is **v0.9.25**. Assets are `securitysearch-v0.9.25.tar.gz`
and `.tar.gz.sha256`, built from the exact real tag. Tokens are entered privately.
Selected hosts are preflighted, pushed without force, and release drafts receive
verified attachments before publication. Conflicting notes/tags/assets are not
replaced. Separate hosts cannot be published as one globally atomic transaction.
The published
v0.9.24 tag and its archives remain untouched. Checksums are integrity checks,
not digital signatures; annotated tags are not automatically signed.

## Tests and evidence

```sh
sh scripts/test.sh --keep-going
```

All **61** commands are mandatory: the prior 57 remain in order, with four new
suites. Full runtime tests require PHP curl/DOM/XML/mbstring/APCu/Imagick, Python,
Node, Git and fish. Native dependencies are never mocked to claim a full pass.
The kit's audit distinguishes real PHP HTTP and browser fixtures from simulated
cURL/Docker/API operations and unperformed VPS/Lighthouse runs.
[Release notes](docs/RELEASE-0.9.25.md) · [Audit](docs/AUDIT-0.9.25.md).

License: [AGPL-3.0](license.txt). **In Code We Trust.**
