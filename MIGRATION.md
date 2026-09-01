# Security Search — Migration Notes

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

Production v0.9.6 uses the clean-sibling, checksum-verified artifact workflow in
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
