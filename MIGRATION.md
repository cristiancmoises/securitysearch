## v0.9.27

Startup-compiled public UI resources and a smaller shared page renderer. No visitor
page/result caching, feature removal or upstream search changes. New immutable
release, asset31; existing NPM/TLS/rollback and operator-media exclusions remain.
[Operations](docs/PERFORMANCE-0.9.27.md) · [Release](docs/RELEASE-0.9.27.md).

## v0.9.26

Google CSE record/pagination/bootstrap/session fixes; single-pass templates and
compiled homepage skin; manual benchmark script without published results; local
README captures; optional explicit Google web/image candidate verification.
[Release notes](docs/RELEASE-0.9.26.md) · [Upstream review](docs/UPSTREAM-0.9.26.md).

## v0.9.25

Homepage-only inline CSS, compressed banner, Google/Brave refusal-aware health and safe diagnostics, genuine image previews. Asset 29; attribution and private-artwork policy retained. See [release notes](docs/RELEASE-0.9.25.md).

## v0.9.24 — independent RSS news and refusal-aware transport

News RSS replaces the unavailable Redlib-only default; real headlines + keyword
verification remains mandatory before cutover. Public-feed-only cache, source/edition
isolation, bounded XML and detailed errors. Asset 28; private artwork/pictures preserved.
[Release notes](docs/RELEASE-0.9.24.md) · [Audit](docs/AUDIT-0.9.24.md).

## v0.9.23

Retire the failed self-hosted news default. Require a real candidate feed AND
keyword search before selecting an external Redlib primary. Repair local-picture
Settings CSP ordering and missing/collapsed editor; add a clear native entry,
byte-signature detection, GIF stills, preview and honest storage feedback. Asset 27.
All r1/r2 gates and private-artwork exclusions remain.
[Operations](docs/OPERATIONS-0.9.23.md) · [Release](docs/RELEASE-0.9.23.md).

## v0.9.22

Repair the offline Redlib transport fixture and add fail-fast test-only network
tripwires. Add bounded positive DNS metadata caching with public-IP validation.
Support verified operator-only historical Lain/SecOps packs outside shared source
history/releases; preserve historical Tron. Asset marker 26. No history rewrite,
production cURL disable, test bypass or claimed live speedup.
[Operations](docs/OPERATIONS-0.9.22.md) · [Release](docs/RELEASE-0.9.22.md).

## v0.9.21

Bounded Redlib failover, poster-preserving motion, local-only pictures, corrected themes/select colors, cached rank/onion footer and truthful metadata. Asset 25. [Release notes](docs/RELEASE-0.9.21.md) · [Operations](docs/OPERATIONS-0.9.21.md). Native full-runtime/VPS gates remain mandatory.

## v0.9.20 — release publication and r1 audit repair

Binternet legacy/modern markup compatibility, bounded legacy provider waits and
pure-black homepage with native theme previews. Includes the r1 cURL test-isolation
repair. Application 0.9.20, assets 24. Annotated tag and exact source-package
publication now have a dedicated four-host workflow; no deployment is performed
by publication. [Release notes](docs/RELEASE-0.9.20.md) ·
[Publishing](docs/PUBLISHING-0.9.20.md) ·
[Publicação](docs/PUBLISHING-0.9.20.pt-BR.md).

## v0.9.19

Complete v0.9.18 improvements integrated on Codeberg commit `0751f14`, preserving the removed Lain animation. Adds annotated release packaging, a fast-forward Git bundle for existing checkouts, four-host token publication, real homepage capture in English/pt-BR READMEs and matching release notes. Asset marker 23. [Release notes](docs/RELEASE-0.9.19.md) · [Publication](docs/PUBLISHING.md).

## v0.9.18

Smaller icons inside the search bar's right edge; plain footer Wiki/Git links; visible bounded GIF/WebP/APNG playback with static posters and independent opt-out; Reddit navigation and local news via Redlib; one labeled Brave fallback for failed new Google web/image searches. Continuations retain their provider. Asset marker 22. [Operations](docs/OPERATIONS-0.9.18.md) · [Validation](docs/AUDIT-0.9.18.md).

## v0.9.17

Real image append-on-scroll restored with one scoped local script. Removed the Automatic pages timer, Refresh navigation and snapshots. Native fallback, Filmstrip support, bounded requests, consumed-token recovery and updated IONOS readiness. Asset marker 21. [Operations](docs/OPERATIONS-0.9.17.md) · [Validation](docs/AUDIT-0.9.17.md).

## v0.9.16

Compact native SVG search icons with accessible labels, shared action markup, and Tron as the effective default. Lightweight Tron background and corrected updater theme migration. [Operations](docs/OPERATIONS-0.9.16.md) · [Validation](docs/AUDIT-0.9.16.md).

## v0.9.15

No browser JavaScript; native search actions, provider recovery, opt-in timed image pages and stronger deployment rollback. Read [operations](docs/OPERATIONS-0.9.15.md) and [audit](docs/AUDIT-0.9.15.md). Earlier sections are historical.

## v0.9.14

See [the operation guide](docs/OPERATIONS-0.9.14.md) and [audit](docs/AUDIT-0.9.14.md) for integrated service search, high-quality previews, UI updates and deployment changes.

# Security Search — Migration Notes

## v0.9.12 — Lain, proxy validation and reliable status reporting

No database or persistent-volume migration is required. Rebuild the whole image
and retain private proxy mounts, both production networks and the icon volume.
The static asset version is now `16`. The original Lain GIF is visible on mobile
and desktop, with a native still-background control; six image layouts preserve
no-JavaScript navigation. Filmstrip uses manual pagination to avoid loading pages
prematurely while its vertical Next link remains visible.
The release helper no longer requires the removed SecOps artwork; its obsolete
Lain decoration was removed as well. No deleted artwork is restored.

Google reuses same-request/same-egress connections, shares query-free bootstrap
and cooldown state between CSE aliases, bounds responses to 4 MiB and preserves
the total 25-second deadline while waiting for concurrent bootstrap work.
Configured proxies are validated before transport and cannot silently fall back
to direct traffic. Check existing private entries for the supported five-field
format before promotion; use DNS names instead of ambiguous IPv6 literals.

All five search APIs return HTTP 503 for caught provider failures. Clients must
check status and `Retry-After`; explicit/saved Google API selection without a key
reports unavailable instead of silently selecting Google CSE. Fresh pickers still
hide that unconfigured option. No query is automatically sent to another provider.

See the [English](docs/OPERATIONS-0.9.12.md) and
[Portuguese](docs/OPERATIONS-0.9.12.pt-BR.md) operating guides and their tests.
Older sections below are historical, not current default settings.

Português: não há migração de dados. Reconstrua a imagem mantendo volumes, redes
e proxies privados. Assets `16`, Lain animado também no celular, seis layouts e
falhas HTTP honestas são publicados juntos. Valide o formato dos proxies antes
da troca e preserve o container anterior para retorno seguro.

## v0.9.7 — image delivery and provider reliability corrections

v0.9.7 is a drop-in update from v0.9.6 with no persistent-data migration.
Rebuild the container so the static asset version `13`, early image scripts,
proxy admission logic, structural validators, and provider parsers are deployed
as one tested unit.

- Image result records are defensively validated before rendering. Each poster
  may use two alternate provider sources through the same-origin proxy, and a
  local unavailable state replaces permanently broken cards without exposing a
  direct result-host request.
- Deferred fallback and motion scripts are emitted in the document head, before
  the result grid can finish loading. Infinite-scroll additions are registered,
  candidates are prepared up to 700 pixels ahead of the viewport, and an
  in-flight load is never interrupted merely because its card moves off-screen.
  Completed animations have a soft LRU retention budget of 36 on desktop or 18
  on mobile; only settled off-screen entries are evicted, and the observer
  automatically prepares them again when they return. A provider motion
  fallback covers alternate sources. Eligible non-WebP candidates (normally
  GIF/APNG) receive one delayed cache-busted retry, while low-confidence WebP
  still receives validation/fallback but skips that automatic retry.
  User-initiated work stays first, followed by GIF/APNG ahead of WebP in the
  automatic queue.
- GIF and animated WebP use bounded structural container parsers instead of
  ImageMagick frame discovery; APNG retains its strict PNG chunk validator. The
  animation download ceiling is 32 MiB. Three validations may run concurrently,
  up to nine requests may wait for at most three seconds, busy rejections are
  not charged, and admitted requests are limited to 900/client/minute. The
  eligible non-WebP retry waits 2.2–3.0 seconds, beyond the two-second retry
  hint; low-confidence WebP skips it.
- Thumbnail downloads are capped at 16 MiB. A JPEG no larger than 128 KiB and
  512 pixels per side, or a structurally validated animated GIF, WebP, or APNG,
  can pass through natively. The animated fast path is limited to 1.5 MiB,
  2,048 pixels per side, and 4 megapixels. Larger JPEG poster fallbacks and
  static or malformed animation-capable formats keep the bounded ImageMagick
  path. Its proxy MIME allowlist is JPEG/PNG/GIF/WebP/AVIF; conversion is limited
  to one frame, 16,384 pixels per side, 40 MP, 64 MiB each of memory/map, no disk
  cache, one thread, and ten seconds. The container policy denies delegates,
  filters, indirect paths, and all coders by default before enabling the narrow
  raster coder set. An animated GIF above 1.5 MiB may fail as a poster, but a
  poster failure does not block the separate 32-MiB motion endpoint from
  starting automatically without a click. Image requests derive a bounded
  Referer from the already validated public source URL.
- Brave image results preserve a valid animation-capable resized source and
  infer GIF/WebP/APNG hints from provider metadata or URL paths. Invalid URLs,
  credentials in URLs, and invalid dimensions are discarded. Google CSE parses
  result items before deciding whether the cursor has another page, preventing
  final-page image loss; an invalid `tbLargeUrl` falls back to a valid `tbUrl`.
- Google CSE's query-free bootstrap token cache is five minutes. Recognized
  Google anti-abuse failures have a separate 30-second negative cache during
  bootstrap and at `cse/element/v1`. The bootstrap owner lock expires after 60
  seconds; waiters consume a published result for up to six seconds and then
  fail fast rather than starting duplicate upstream work.
- Static asset cache version `13` replaces v0.9.6 version `12`.

Google unusual-traffic and Brave proof-of-work responses are upstream egress
restrictions, not successful searches. v0.9.7 reports them explicitly and does
not silently send the query to DuckDuckGo, Yandex, or another provider. A
reviewed private proxy pool or an intentionally configured official API remains
an operational choice, not an application guarantee.

At Nginx Proxy Manager, update both the database-backed advanced configuration
and the generated host configuration. WAF path rules must inspect `$uri`, never
`$request_uri`, so the encoded target in `/proxy?i=...` cannot make a legitimate
WordPress upload path look like a local scanner request. Derive argument WAF
input separately and clear it only when the local path is `/proxy` or
`/proxy.php`; do not clear `$args` or disable argument inspection globally.
Keep timestamped database and generated-config backups until nginx syntax,
public WordPress-upload media, animated media, and SSRF rejection controls all
pass.

## v0.9.6 — UI, provider, motion, and packaging corrections

v0.9.6 is a drop-in update from v0.9.5 with no persistent-data migration.
Rebuild the container so the corrected assets, configuration, and scripts reach
the production image.

- Clean Git archives and release packages include the Security Search logo, and
  an empty banner directory no longer produces a PHP warning.
- Docker build context excludes all private API-key and proxy-pool files;
  intentional container use requires the documented read-only runtime mounts.
- SecOps again uses the tracked `static/misc/secops.gif` home background, with a
  static CSS fallback for reduced-motion and reduced-data preferences. Static
  asset version 12 invalidates the previous theme/background cache.
- `DEFAULT_NSFW=yes` and `FOURGET_DEFAULT_NSFW=yes` allow NSFW content by
  default for provider filters that support it. An explicit request or saved
  user preference still overrides the default.
- Animated-result validation loads are queued at three concurrent desktop or
  two coarse-pointer/mobile requests, while every visible validated GIF,
  animated WebP, or APNG continues playing without a click. Provider
  MIME/format hints improve extensionless discovery; off-screen cards restore
  their posters. A bounded GitHub Camo hint covers encoded GIF/WebP/APNG source
  URLs while the proxy remains the authoritative multi-frame validator.
- The animated-preview admission limit is 120 requests per client address per
  minute under a three-slot global server semaphore.
- Brave direct egress fails fast after the first recognized proof-of-work
  challenge. A configured proxy pool may rotate addresses for at most three
  bounded Brave attempts. Google and Brave use 10-second connect and 20-second
  total timeouts for each upstream transfer; Compose provides an optional
  read-only `./data/proxies` mount for private pools.
- Bare Google HTTP 429 responses use the neutral rate-limit state instead of a
  generic parse error. Unsupported Google video/news choices were removed, and
  an empty web query no longer reaches the calculator oracle or emits warnings.

## v0.9.5 — release packaging correction

v0.9.5 is a drop-in update from v0.9.4 with no persistent-data migration. The
release helper now adds the required empty `icons/` runtime-cache directory to
the source archive; generated icon files remain excluded. Application, search,
theme, animation, and provider behavior is unchanged from v0.9.4.

## v0.9.4 — provider reliability and SecOps theme repair

This release is a drop-in update from v0.9.3. No persistent-data migration is
required. Rebuild the container so PHP OPcache and the immutable static assets
are replaced together.

- Static asset version `11` invalidates the cached v0.9.3 theme CSS.
- The SecOps theme now owns the page background and home color tokens; base and
  inline `!important` rules no longer override it.
- Google and Google CSE reuse only validated, query-free bootstrap parameters
  for up to 90 seconds per backend, CX, and outbound egress. Queries and result
  documents are never cached. Pagination continues to carry its token and proxy
  in encrypted, one-use `npt` state; a recognized token rejection invalidates
  the cache, performs one fresh bootstrap, and retries once. Google
  unusual-traffic/IP throttles are not retried automatically.
- Search failures use a neutral, responsive error panel with retry, provider
  settings, and a deliberate Brave option. Queries are never silently sent to
  a second provider.
- Google API is now consistently registered for web and image search, but it
  remains opt-in and requires keys in `data/api_keys/google_api.txt`.
- The Qwant unexpected-response path no longer emits PHP undefined-key warnings.
- User-visible CAPTCHA, URL-resolution, search-provider, and About text has
  been rewritten without profanity.

Before replacing v0.9.3, preserve the current deployment directory and its
environment/configuration. After deployment, verify actual Google result cards
and JSON arrays; HTTP 200 alone is insufficient because provider errors are
rendered as normal pages. See `docs/RELEASE.md` for the current IONOS workflow.

---

## Historical migration baseline

This document explains every change made in this drop-in update so you can
review before deploying. The work is grouped into four phases. Apply them
in order; each phase is independently testable.

> **Fork point:** `aa7c85b` (your previous master HEAD)
> **Upstream baseline:** `git.lolcat.ca/lolcat/4get` HEAD as of 2026‑05‑06

---

## Phase 1 — Critical Security Fixes

### 1.1 Captcha brute-force fix (`lib/bot_protection.php`)

Backported from upstream commit `4349bf2`. Without `array_unique($answers)`,
duplicate entries in the captcha answer pool let an attacker narrow the
solution space and brute-force a captcha by retrying. **One-line fix; high
severity if `FOURGET_BOT_PROTECTION` is ever set to `1`.**

```php
// after the foreach() that builds $answers (~line 135)
$answers = array_unique($answers);
```

### 1.2 Centralized HTTP security headers

**Problem:** the same six `header()` calls were duplicated across seven PHP
files. Any change required editing all seven; some files had drifted (e.g.
`index.php` had a comment line, others didn't).

**Solution:** two new include files:

- `lib/security_headers.php` — for HTML responses. CSP tightened from
  `script-src 'self' 'unsafe-inline'` to `script-src 'self'` (no inline
  scripts; verified that no template uses `<script>...</script>` blocks
  or inline `onclick=` handlers). Adds COOP/CORP, full Permissions-Policy
  including `interest-cohort` and `browsing-topics` (Google FLoC opt-out),
  and `frame-ancestors 'none'`. HSTS bumped to 2 years + preload.

- `lib/security_headers_minimal.php` — for non-HTML endpoints (image proxy,
  favicon proxy, captcha image, sitemap.xml, opensearch.xml, JSON APIs).
  Uses `default-src 'none'` since these responses are never interpreted as
  HTML by the browser.

Every PHP entry point now starts with one line:

```php
include_once __DIR__ . "/lib/security_headers.php";
```

Files updated: `index.php`, `web.php`, `images.php`, `videos.php`,
`news.php`, `music.php`, `donate.php`, `about.php`, `instances.php`,
`settings.php` (HTML); `proxy.php`, `favicon.php`, `captcha.php`,
`opensearch.php`, `sitemap.php`, `ami4get.php`, `resolver.php` (minimal).

### 1.3 Hardened Dockerfile

- Added `php84-opcache` — biggest single perf win (~30–50% PHP latency
  reduction). Configured for immutable container deploys
  (`validate_timestamps=0`).
- Added `php84-session`, `php84-tokenizer`, `php84-xml` — were missing but
  used by some scrapers.
- Added `tini` as PID 1 to reap zombie httpd children properly.
- Replaced `chmod 777` on `icons/` with `chmod 775`.
- Wrote a hardened `php.ini` snippet (`/etc/php84/conf.d/99_security.ini`):
  `expose_php=Off`, `allow_url_include=Off`, secure session cookies,
  resource limits.
- Added a `HEALTHCHECK` directive so NPM/orchestrator can detect dead
  containers.
- Reduced `EXPOSE` to port 80 only (NPM terminates TLS at the edge).

### 1.4 Hardened `docker-compose.yml`

- Host port 5140 now binds to `127.0.0.1` by default instead of every interface.
  A containerized NPM deployment can set `SECURITYSEARCH_BIND_ADDRESS` in a
  restricted `.env` to its private Docker-host bridge address. The public VPS
  IP and `0.0.0.0` are explicitly unsupported because they bypass edge limits.
- `cap_drop: [ALL]` then `cap_add` only what httpd needs (SETUID, SETGID,
  NET_BIND_SERVICE).
- `security_opt: no-new-privileges:true` — the kernel refuses to grant
  any new privileges (prevents most container escapes via setuid binaries).
- Application source is root-owned and mode 0644/0755, so Apache workers cannot
  rewrite PHP or assets. The root filesystem remains writable to the root
  entrypoint because it generates `data/config.php`; only `/tmp` and the
  persistent `icons` volume are intended worker-write paths. A fully read-only
  root remains an optional future hardening step after relocating generated
  configuration and Apache runtime state.
- Resource limits (512 MB / 1 CPU). The original 4get README claims
  ~200–400 MB RAM, so 512 MB is comfortable headroom.
- Uses your existing `bridge` network (same as before).
- Added a `healthcheck` matching the Dockerfile's, plus a `logging`
  driver with rotation so logs don't fill the host disk.

### 1.5 `.dockerignore` overhaul

The previous one-line `.dockerignore` (`.git`) shipped backup files,
documentation, and the `.git` directory's history into the image. New
version excludes `*.bak`, `docs/`, both compose files, scraper test
artifacts, and any local `.env`. Estimated image-size reduction:
~15–25 MB.

### 1.6 Apache config

- `ServerTokens Prod` (was `OS`) and `ServerSignature Off` — hide
  version info.
- `TraceEnable Off` — defense against TRACE method abuse.
- `mod_remoteip` configured to trust docker bridge networks
  (`172.16.0.0/12`, `10.0.0.0/8`, `192.168.0.0/16`) so PHP sees the real
  client IP coming through NPM.
- Added explicit `Require all denied` for `/lib`, `/scraper`, `/oracles`,
  `/docker`, and all dotfiles (with `.well-known` whitelisted).
- `mod_deflate` configured for text content with sensible exclusions for
  already-compressed types.
- `mod_expires` for static asset cache headers.
- Connection-level hardening: `Timeout 30s`, `RequestReadTimeout` against
  Slowloris attacks.
- Tuned MPM prefork: `MaxRequestWorkers 128`, `MaxConnectionsPerChild 1000`.
- Logs go to stderr so `docker logs` captures them.
- TLS profile in the HTTPS variant rewritten to Mozilla "Intermediate"
  2024 (TLS 1.2+1.3 only, no CBC/3DES/RC4/MD5/SHA1).

---

## Phase 2 — SEO for `securityops.co`

### 2.1 New `template/home.html` head

Added the meta tags Google and other engines actually use to surface a
search engine on SERPs:

- `<link rel="canonical" href="https://securityops.co/">` — fixes
  duplicate-content concerns when accessed via `www.` / IP / onion.
- Twitter Card meta (`twitter:card`, `twitter:title`, etc.) — link
  previews on X, Mastodon, Discord, Slack, etc.
- `og:locale` + `og:locale:alternate` — declares EN/PT-BR.
- **JSON-LD `WebSite` + `SearchAction`** — this is the big one. With this
  structured data, Google can show a sitelinks search box for your domain
  on the SERP, letting users search Security Search directly from Google.
- `theme-color` and `color-scheme` — controls browser chrome on mobile.
- Updated title and description to be keyword-rich without being spammy.
- Reorganized `<link>` order so CSS comes after icon/canonical for
  faster First Contentful Paint.

### 2.2 New `robots.txt`

- Allows only landing pages (`/`, `/about`, `/instances`, `/api.txt`,
  `/opensearch`, `/.well-known/`).
- Disallows all dynamic search paths — Google indexing search-result
  pages is wasted crawl budget AND eats your captcha/proxy bandwidth.
- 28 explicit User-agent blocks for AI training crawlers
  (GPTBot, ClaudeBot, CCBot, Google-Extended, PerplexityBot, etc.)
  and abusive SEO scanners (AhrefsBot, SemrushBot, MJ12bot, etc.).
  Anubis already handles many of these, this is the explicit
  on-the-record opt-out.

### 2.3 Dynamic `sitemap.php`

Old version had a hard-coded `2023-07-31` lastmod. New version computes
lastmod from filesystem mtime of source files — every redeploy
automatically refreshes the date. Also added `<changefreq>` and
`<priority>` per URL.

---

## Phase 3 — Upstream Backports

Cherry-picked safe, high-value upstream changes. **Skipped**: changes
that conflicted with your fork's customizations
(`lib/frontend.php` SecOps theme branching, 4chan archive logic).

### 3.1 Files updated wholesale from upstream

| File | Reason |
|------|--------|
| `lib/fuckhtml.php` | Proper backslash-escape counting in JSON (was buggy on `\\\"`) + replaces `array_key_exists` with `isset` (the latter handles null-valued keys correctly). |
| `scraper/brave.php` | Cleanup of debug code. |
| `scraper/google.php` | Reduced 2470 → 1117 lines. Old HTML scraping logic removed; current selectors. Google's HTML breaks weekly — upstream's API-first approach is more stable. |
| `scraper/google_api.php` | Now includes image scraping (`google_api` is a registered image scraper option). |
| `scraper/yandex.php` | Video search fix. |
| `scraper/yep.php` | Image+news removed (always broke); web search reliable. |
| `scraper/pinterest.php` | Detects when Pinterest blocks the instance ("is_bad_bot"). |
| `scraper/qwant.php` | Detects Qwant's captcha redirect (returns clear error instead of empty results). |

### 3.2 New scrapers added

| File | Type | Notes |
|------|------|-------|
| `scraper/pexels.php` | Image | Free stock photos, generally fast. |
| `scraper/unsplash.php` | Image | Free stock photos. |
| `scraper/pixabay.php` | Image | Free stock photos + illustrations. |

All three are registered in `lib/frontend.php` and `settings.php` for the
images page.

### 3.3 Scrapers removed from registries (NOT from disk)

`greppr`, `crowdview`, `curlie` — upstream removed these because they
returned poor results too often. The PHP files are still present in
`scraper/` but no longer offered as a user choice. If you re-enable them
later, just add them back to `lib/frontend.php` and `settings.php`.

`yep` removed from `images` and `news` registries (yep web search
remains). Upstream confirmed yep's image and news endpoints are
unreliable.

### 3.4 Historical backport boundary

This subsection records the older fork baseline at the time that phase was
applied; it is not a current-tree inventory. v0.9.4 subsequently updates the
frontend, home/About templates, configuration defaults, and theme collection,
as described at the top of this document.

- At that baseline, `lib/frontend.php` changed only its scraper registry;
  fork-specific theme and archive behavior remained in place.

- At that baseline, `lib/bot_protection.php` changed only the captcha dedup
  line described in section 1.1.

- At that baseline, the then-current `static/themes/*.css` collection was
  unchanged.

- At that baseline, `template/home.html` changed only its `<head>`; later
  releases replaced the landing-page layout.

- At that baseline, `template/about.html` and `template/donate.html` were
  unchanged.

- At that baseline, `data/config.php` was unchanged and remained generated by
  `docker/gen_config.php` from environment values at container startup.

- Removed scrapers' files (greppr, crowdview, curlie) — left on disk
  in case you want them back.

---

## Phase 4 — NPM Edge Configuration

See `docker/nginx-proxy-manager.conf` for the paste-ready advanced config.
Highlights:

- **`X-Forwarded-*` headers** to feed `mod_remoteip` correct client IPs.
- **30-day cache** on `/static/`, `/banner/`, favicons.
- **15 min cache** on the image `/proxy` endpoint.
- **1 day cache** on `/favicon` (the favicon proxy).
- **Per-IP rate limits**: 30 req/min on search endpoints, 60 req/min on
  API endpoints (zones must be declared in NPM's main `nginx.conf` —
  instructions at the bottom of the file).
- **Path blocks** for known scanner paths (`/.env`, `/wp-admin`, etc.).
- **UA blocks** for sqlmap, nikto, masscan, etc.
- **Edge security headers** as defense in depth.

---

## How to Deploy

For a local tree or an already isolated deployment, the current helper is:

```bash
./deploy.sh --fresh
```

It creates a mode-0600 source backup, builds while the existing container stays
online, cuts over only after the build succeeds, waits for health, and tests the
effective Compose-published endpoint. If a post-cutover check fails, it attempts
to restore the previously tagged image and reports whether that restart
succeeded. There is no separate `--rollback` option.

Production v0.9.7 uses the clean-sibling, checksum-verified artifact workflow in
[`docs/RELEASE.md`](docs/RELEASE.md). Do not unzip or recursively copy a bundle
over the active source tree, and do not use an unresolved recursive-delete
command as rollback. The release guide preserves exact old-tree and image
identifiers until verification, then applies guarded cleanup.

NPM keeps using port 5140, but the listener must be private. Set
`SECURITYSEARCH_BIND_ADDRESS` to the Docker-host bridge address NPM can reach
and point NPM to that same private address. Do not publish port 5140 on the
public VPS interface.

The checked-in `docker/nginx-proxy-manager.conf` documents optional edge caching
and rate limiting. Validate the live NPM configuration independently; the file
alone does not prove that NPM loaded those directives.

## Rollback

Use the exact timestamped paths and preserved image ID recorded during cutover,
following the guarded rollback procedure in [`docs/RELEASE.md`](docs/RELEASE.md).
Never guess a path or recursively delete an unresolved target.

## What's NOT Done (Out of Scope For This Pass)

- **Migrating to `mpm_event` + `php-fpm`** — this is a real perf win
  (mpm_prefork can't share httpd memory across requests; mpm_event can
  handle 10x more concurrent connections) but it requires non-trivial
  refactor of the Dockerfile, the entrypoint, and Apache module loads.
  Defer until you actually see prefork running out of workers.
- **Anubis bot protection enabled by default** — `FOURGET_BOT_PROTECTION=0`
  was kept. To enable, set it to `1` and mount captcha images. The dedup
  fix in 1.1 is what makes that mode safe.
- **Full TLS 1.3 0-RTT** — NPM's modern Let's Encrypt config already
  enables this; no work needed in this stack.
- **`api.txt.bak` and other backup files** — removed during this pass.

## Files Changed Summary

```
Added:
  lib/security_headers.php
  lib/security_headers_minimal.php
  scraper/pexels.php
  scraper/unsplash.php
  scraper/pixabay.php
  docker/nginx-proxy-manager.conf
  MIGRATION.md            (this file)

Replaced:
  Dockerfile
  docker-compose.yml
  .dockerignore
  robots.txt
  sitemap.php
  template/home.html
  scraper/brave.php
  scraper/google.php
  scraper/google_api.php
  scraper/pinterest.php
  scraper/qwant.php
  scraper/yandex.php
  scraper/yep.php
  lib/fuckhtml.php
  docker/apache/http/httpd.conf
  docker/apache/https/httpd.conf
  docker/apache/https/conf.d/ssl.conf

Patched (small surgical edits):
  lib/bot_protection.php       (captcha dedup line)
  lib/frontend.php             (scraper registry only — fork code preserved)
  settings.php                 (scraper options aligned with frontend.php)
  index.php                    (replaced inline headers with include)
  web.php                      (replaced inline headers with include)
  images.php                   (replaced inline headers with include)
  videos.php                   (replaced inline headers with include)
  news.php                     (replaced inline headers with include)
  music.php                    (replaced inline headers with include)
  donate.php                   (replaced inline headers with include)
  about.php                    (added headers include)
  instances.php                (added headers include)
  proxy.php                    (added minimal headers include)
  favicon.php                  (added minimal headers include)
  captcha.php                  (added minimal headers include)
  opensearch.php               (added minimal headers include)
  ami4get.php                  (added minimal headers include)
  resolver.php                 (added minimal headers include)
  docker-compose.yaml          (removed — duplicate)

Removed:
  *.bak, *.bak.bak, *.bak.bak.bak files
  api.txt.bak
  template/home.bak3
  template/home.html.bak{,2,3}
  index.php.bak
  instances.php.bak
  settings.php.bak{,.bak}
  web.php.bak
  docker-compose.yaml          (kept docker-compose.yml; both shouldn't coexist)
```
