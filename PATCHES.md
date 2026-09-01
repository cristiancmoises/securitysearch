# Quick-Apply Patches

## v0.9.7 image delivery and provider reliability corrections

Current installations should use the complete tagged v0.9.7 source archive;
do not deploy this as a selection of individual files. It is a drop-in update
from v0.9.6 with no persistent-data migration, but the container must be rebuilt
so static asset version `13`, the PHP proxy, and the provider parsers change
together.

Image result rendering now validates malformed provider records, keeps up to
two same-origin poster fallbacks, and marks a card unavailable only after its
bounded fallback path is exhausted. The fallback and motion controllers load
early in the document, register infinite-scroll additions, prefetch within 700
pixels of the viewport, and try Brave's animation-preserving resized URL before
the original. The automatic queue prioritizes GIF/APNG over low-confidence
WebP. WebP still receives structural validation and a provider-source fallback,
but skips the cache-busted automatic retry used by eligible non-WebP candidates
(normally GIF/APNG).
Completed animations use a soft LRU budget of 36 on desktop or 18 on mobile;
visible and in-flight work is never interrupted, and an evicted off-screen item
is automatically prepared again when it returns. GIF and animated WebP are
structurally inspected by bounded parsers; APNG keeps its strict chunk validator.
The animated endpoint accepts at most 32 MiB, allows three active validations
plus nine short waiters, charges only admitted requests against a
900/client/minute allowance, and sends
a two-second busy retry hint; the browser waits 2.2–3.0 seconds before its sole
eligible non-WebP retry. Thumbnail downloads are capped at 16 MiB. The native
fast path is restricted to a JPEG no larger than 128 KiB and 512 pixels
per side, or to a structurally validated animated GIF, WebP, or APNG up to 1.5
MiB, 2,048 pixels per side, and 4 megapixels. Larger JPEG poster fallbacks and
static or malformed animation-capable formats remain on the bounded ImageMagick
thumbnail path. That fallback admits only JPEG/PNG/GIF/WebP/AVIF, decodes one
frame, and enforces 16,384-pixel/40-MP, 64-MiB memory/map, disk-zero, one-thread,
and ten-second limits. The container policy denies delegates, filters, indirect
paths, and all coders by default before enabling its narrow raster set. An
animated GIF above 1.5 MiB can therefore fail as a poster while the independent
32-MiB motion path still starts automatically without a click. Image fetches
derive their bounded Referer from the validated public source URL.

Google CSE no longer drops a final image page merely because its cursor says
there is no following page, and an invalid `tbLargeUrl` now falls back to a valid
`tbUrl`. Its query-free bootstrap token cache is five minutes. A single-flight
owner lock expires after 60 seconds; waiters use a published result for up to
six seconds, then fail fast rather than duplicating the bootstrap. Recognized
anti-abuse failures receive a 30-second negative cache both during bootstrap and
at `cse/element/v1`. Brave now validates image result URLs and dimensions,
derives motion hints from both metadata and URL paths, preserves its resized
source, and exposes that smaller animated source to the UI. These changes cannot
make an egress address accepted by Google or Brave: unusual-traffic and
proof-of-work responses remain neutral, explicit provider errors that must not
trigger a silent provider switch.

For Nginx Proxy Manager, inspect the live generated host configuration as well
as the database-backed advanced configuration. WAF path rules must test `$uri`,
not `$request_uri`, because the latter includes the encoded remote image URL and
can reject ordinary sources such as `/wp-content/uploads/...`. Derive a
separate argument-inspection variable and clear it only for the local `/proxy`
and `/proxy.php` routes; never disable argument checks globally. Preserve
timestamped NPM database and generated-host backups until the public animation,
WordPress-upload, and SSRF-control checks pass.

## v0.9.6 UI, provider, motion, and packaging corrections

Installations pinned to v0.9.6 should use that complete tagged source archive. It
includes the required Security Search logo, prevents empty-banner PHP warnings,
restores the tracked animated SecOps background with reduced-motion/data
fallbacks, and sets NSFW-capable filters to `yes` by default while retaining
user overrides. Animated originals now use a bounded validation queue rather
than a three/two playback cap: all visible validated GIF/WebP/APNG results keep
playing without a click. Provider MIME/format hints improve extensionless
discovery, including bounded GitHub Camo source hints, and the application
admits 120 motion requests/client/minute under a three-slot server semaphore.
Brave direct egress now fails after its first
recognized proof-of-work response; only a configured pool rotates for up to
three attempts. Google and Brave use 10-second connect and 20-second total
timeouts per upstream transfer; bare Google 429 responses receive the neutral
rate-limit state. Unsupported Google video/news choices and the empty-query
calculator warning are removed. Docker build context excludes private API-key
and proxy-pool files, with optional read-only runtime mounts documented for
intentional use. This is a drop-in update from v0.9.5.

## v0.9.5 packaging correction

The tagged v0.9.5 source archive preserves the required empty `icons/`
runtime-cache directory. It is retained here as a historical packaging note.
Generated icons remain excluded, and v0.9.5 does not change the v0.9.4
application behavior.

## v0.9.4 patch set

The v0.9.4 list below remains a historical, narrow Google/theme/error subset
for code review; it is **not** a complete or safe v0.9.3-to-v0.9.4 upgrade.
That full release also changes animation validation, proxy/SSRF
handling, Apache/NPM hardening, container permissions, infinite scrolling,
performance, packaging, and documentation:

1. Replace `scraper/google_cse.php` to use a 90-second, query-free bootstrap
   cache scoped by backend, CX, and outbound egress; preserve one-use encrypted
   pagination state; and bound recognized token-error refreshes to one retry.
2. Replace `lib/frontend.php`, `static/style.css`, `template/home.html`, and
   `data/config.php` together. These files contain the professional error state,
   strict theme-name validation, SecOps cascade correction, and asset version
   `11`; deploying only part of this group can retain stale/broken styling.
3. Replace `scraper/qwant.php`, `captcha.php`, `resolver.php`, and
   `template/about.html` for warning-free and neutral user-facing failures.
4. Do not deploy this subset by itself. Build the full tagged archive, verify
   real Google/Brave web/image results and pagination, then inspect logs before
   switching production traffic.

The remainder of this file records the older fork bootstrap patches and is kept
for historical reference.

---

If you'd rather merge changes into your existing fork manually instead of
replacing files wholesale, here are the smallest atomic patches grouped
by risk level. Apply them in order; you can stop at any phase.

---

## 🔴 Tier 1 — Apply today, zero risk, high security value

### 1. Captcha brute-force fix

**File:** `lib/bot_protection.php`

```diff
@@ around line 133 @@
 			$answers[] = $regex;
 		}
 		
+		// dedup — prevents brute-force narrowing of the answer space
+		// (backported from upstream lolcat/4get @ 4349bf2)
+		$answers = array_unique($answers);
+		
 		if(
 			!$invalid &&
 			$key !== false // has captcha been gen'd?
 		){
```

### 2. Replace `robots.txt`

Use the `robots.txt` from this update zip. It blocks:
- All AI training bots (GPTBot, ClaudeBot, CCBot, Google-Extended, etc.)
- Aggressive SEO scanners (Ahrefs, Semrush, MJ12)
- Dynamic search endpoints from indexing (saves crawl budget + proxy bw)

### 3. Replace `sitemap.php`

Use the new `sitemap.php` — same five URLs but with dynamic `<lastmod>`
based on filesystem mtime, plus `<changefreq>` and `<priority>` for each.

---

## 🟡 Tier 2 — Apply this week, requires testing

### 4. Centralize security headers

1. Drop the new files in: `lib/security_headers.php` and
   `lib/security_headers_minimal.php`.

2. In each existing entry point, **delete** the 5–7 inline `header(...)`
   calls at the top, and **insert** one of these as the first line after
   `<?php`:

   ```php
   include_once __DIR__ . "/lib/security_headers.php";   # HTML responses
   ```
   ```php
   include_once __DIR__ . "/lib/security_headers_minimal.php";   # everything else
   ```

   HTML pages: `index.php`, `web.php`, `images.php`, `videos.php`,
   `news.php`, `music.php`, `donate.php`, `about.php`, `instances.php`,
   `settings.php`.

   Minimal: `proxy.php`, `favicon.php`, `captcha.php`, `opensearch.php`,
   `sitemap.php`, `ami4get.php`, `resolver.php`.

3. Test in your browser dev tools that
   `Content-Security-Policy: default-src 'none'; script-src 'self'; ...`
   appears on all responses. **Tightest CSP change:** dropped
   `'unsafe-inline'` from `script-src`. If something breaks, revert
   `lib/security_headers.php` to use `script-src 'self' 'unsafe-inline'`.

### 5. Update `template/home.html` `<head>`

Replace lines 1–22 of your current file with the new `<head>` section
(everything up through the closing `</script>` of the JSON-LD block).
This adds:

- Canonical URL.
- Twitter Cards.
- JSON-LD `WebSite` + `SearchAction` — Google sitelinks search box.
- Better description and keywords.

Verify on https://search.google.com/test/rich-results that the
JSON-LD parses with no errors after deploy.

---

## 🟢 Tier 3 — Apply when you have time, infrastructure changes

### 6. Hardened `Dockerfile`

Replace your `Dockerfile`. Key changes:
- Adds `php84-opcache` (~30–50% PHP perf win)
- Adds `tini` PID 1 for zombie reaping
- Tighter file permissions (`755` dirs, `644` files, `775` for `icons/`)
- `HEALTHCHECK` directive
- Hardened `php.ini` snippets (`expose_php=Off`, `allow_url_include=Off`)
- Drops `EXPOSE 443` since NPM terminates TLS

### 7. Hardened `docker-compose.yml`

The current Compose file publishes port 5140 on loopback by default. For the
containerized NPM layout used on IONOS, set `SECURITYSEARCH_BIND_ADDRESS` in a
mode-0600 `.env` to the private Docker-host bridge address NPM can reach (for
example `172.17.0.1`) and configure NPM for that address and port 5140. Do not
bind the application to the VPS public address or `0.0.0.0`.

After recreating the container, use `docker compose port security-search 80`
to discover the effective private endpoint, verify it from the NPM container,
and verify externally that direct access to port 5140 is closed.

### 8. Apache config tightening

The new `docker/apache/http/httpd.conf`:
- Hides version (`ServerTokens Prod`)
- Disables TRACE
- Adds `mod_remoteip` so PHP sees real client IPs through NPM
- Denies access to `/lib`, `/scraper`, `/oracles`, `/docker` (defense
  in depth — they shouldn't be reachable but block them at Apache too)
- `mod_deflate` for text content
- `mod_expires` for static asset cache headers
- `RequestReadTimeout` against Slowloris
- Tuned MPM prefork values

### 9. NPM advanced config

See `docker/nginx-proxy-manager.conf`. Paste the contents into the
"Advanced" tab of your NPM proxy host. Adds:
- Edge-level caching (saves ~50% upstream load)
- Per-IP rate limiting (30 req/min on search, 60 req/min on API)
- Path blocks for known scanners
- Real-IP forwarding to the upstream

The `limit_req_zone` declarations need to live in NPM's main `nginx.conf`
or `/data/nginx/custom/http_top.conf`. Instructions are at the bottom of
the conf file.

---

## 🔵 Tier 4 — Upstream backports (review per-scraper)

These are wholesale file replacements. Before each one, diff against
your current version to see if you have any local changes:

```bash
diff scraper/google.php /path/to/upstream/scraper/google.php
```

Files in this update that are upstream-tracked (no fork-specific changes):

- `lib/fuckhtml.php` — bug fixes (backslash escape counting, isset on null)
- `scraper/brave.php` — debug code removal
- `scraper/google.php` — heavy refactor, much leaner
- `scraper/google_api.php` — adds image scraping
- `scraper/yandex.php` — video fix
- `scraper/yep.php` — image+news removed (always broke)
- `scraper/pinterest.php` — bot-block detection
- `scraper/qwant.php` — captcha-redirect detection

Files NEW in this update (just drop them in `scraper/`):

- `scraper/pexels.php`
- `scraper/unsplash.php`
- `scraper/pixabay.php`

After replacing the scraper files, also update:

- `lib/frontend.php` — remove `greppr`, `crowdview`, `curlie` from web
  scraper registry; remove `yep` from images and news registries; add
  `google_api`, `pexels`, `unsplash`, `pixabay` to images registry.
  See `lib/frontend.php` in this update for the exact lines.

- `settings.php` — same set of registry edits.

---

## Verification Checklist

After applying any tier, run through these:

- [ ] `docker compose logs security-search 2>&1 | grep -i error` — clean?
- [ ] `curl -I https://securityops.co/` shows expected headers
- [ ] `curl https://securityops.co/robots.txt` looks right
- [ ] `curl https://securityops.co/sitemap | xmllint --noout -` parses OK
- [ ] Search a query, verify results render
- [ ] Click into image search, verify thumbnails proxy through OK
- [ ] https://securityheaders.com/?q=securityops.co — should now grade A
- [ ] https://search.google.com/test/rich-results — JSON-LD valid
- [ ] https://www.ssllabs.com/ssltest/analyze.html?d=securityops.co — A or A+
