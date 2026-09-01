# Security Search

[English](README.md) | [Português do Brasil](README.pt-BR.md)

Privacy-first proxy metasearch engine. Hardened fork of
[4get](https://git.lolcat.ca/lolcat/4get) deployed at
[securityops.co](https://securityops.co).

## Current source version: v0.9.6

- v0.9.6 restores the tracked Security Search logo to clean source archives,
  prevents an empty banner directory from producing PHP warnings, and restores
  the genuine `static/misc/secops.gif` SecOps home backdrop. Reduced-motion and
  reduced-data preferences receive a static theme fallback.

- v0.9.5 is a packaging correction: release archives now retain the required
  empty `icons/` runtime-cache directory while continuing to exclude generated
  icon files. Search, provider, animation, theme, and UI behavior remains the
  tested v0.9.4 implementation.

- The landing page is search-first: Settings, the Security Search logo, the
  primary search field, a compact privacy/provider hint, and two quiet links to
  [SecurityTops](https://securitytops.co/) and
  [SecurityOps Brasil](https://securityops.com.br/).
- SecOps is the default theme for new visitors. The home page now consumes the
  active theme's color tokens instead of masking them with a separate palette;
  valid saved themes remain selected, and asset version 12 invalidates stale
  theme CSS and background assets.
- NSFW-capable provider filters allow NSFW content by default through
  `config::DEFAULT_NSFW=yes` and `FOURGET_DEFAULT_NSFW=yes`. A request parameter
  or saved Settings preference can still select `maybe` or `no`.
- Google remains the default provider for web and image search. A short,
  per-egress 90-second cache reuses only CSE bootstrap parameters to remove two
  upstream round trips from nearby searches; queries and results are never
  cached. Concurrent cold misses share one bounded APCu bootstrap flight. A
  recognized rejected token gets exactly one fresh bootstrap and retry.
- Google unusual-traffic/IP blocks are not retried or disguised. A neutral
  provider-unavailable page offers the same-query retry, Settings, and an
  explicit **Try Brave** action. Security Search never silently sends the query
  to Brave.
- Brave is selectable from the Scraper picker for web and image search. A
  recognized proof-of-work page on direct egress fails after the first attempt.
  Only a configured proxy pool rotates to another address, with at most three
  bounded Brave attempts; the app never solves the challenge, loops
  indefinitely, or changes providers.
- The optional Google API provider remains available only to existing
  credential holders, but no Google API keys are included in source or
  production. Google's [current API overview](https://developers.google.com/custom-search/v1/overview)
  says it is closed to new customers and existing customers must transition by
  January 1, 2027.
- Image results load additional pages automatically by default. Settings offers
  an opt-out, and the normal **Next page** link remains the progressive fallback.
- Landing-page hierarchy, responsive behavior, and accessibility expectations:
  [docs/UI.md](docs/UI.md).
- Provider behavior and configuration: [docs/PROVIDERS.md](docs/PROVIDERS.md).
- Packaging, IONOS/Evelin deployment, verification, and rollback:
  [docs/RELEASE.md](docs/RELEASE.md).

## Architecture and trust boundary

Security Search is a self-hosted search proxy, not an independent web index. The
browser sends a query to the Security Search instance, and that instance queries
the selected upstream provider. This prevents a normal direct browser-to-provider
request, but it is not by itself an anonymity guarantee: the instance operator
still processes incoming searches, controls server and reverse-proxy logging,
and is responsible for transport security, retention, access, and abuse controls.
The upstream provider sees requests from the instance or its configured outbound
proxy and can still rate-limit or challenge that traffic.

For a private deployment, review the web-server and proxy logs, avoid committed
secrets, restrict administrative access, and publish an accurate operator privacy
notice. Self-hosting changes who must be trusted; it does not remove trust.

## Configuration at a glance

The container generates `data/config.php` from `FOURGET_*` environment values.
The production defaults are explicit in `docker-compose.yml`:

```yaml
environment:
  - FOURGET_DEFAULT_THEME=SecOps
  - FOURGET_DEFAULT_NSFW=yes
  - FOURGET_DEFAULT_SCRAPER_WEB=google
  - FOURGET_DEFAULT_SCRAPER_IMAGES=google
```

A valid saved browser preference takes precedence over its configured default.
Query-string provider selection takes precedence over both. Google and Brave can
run directly or through private pools named by `FOURGET_PROXY_GOOGLE` and
`FOURGET_PROXY_BRAVE`; never commit proxy credentials or Google API keys. See
[provider configuration](docs/PROVIDERS.md)
and the upstream [configuration guide](docs/configure.md).
For a private pool, uncomment the optional read-only
`./data/proxies:/var/www/html/4get/data/proxies:ro` Compose mount and keep the
untracked credential file restricted on the host.

For NSFW-capable provider filters, an explicit `nsfw` request parameter takes
precedence over the saved `nsfw` cookie, which takes precedence over
`DEFAULT_NSFW`. The production default `yes` permits NSFW results; users can
save `maybe` or `no` in Settings. Exact upstream filtering remains
provider-specific.

Provider failures do not trigger a silent fallback. On web and image error
pages, **Try Brave** is a user-initiated request that preserves the search and
filters while setting `scraper=brave`. This matters for privacy: Brave receives
the query only after that explicit choice (or an ordinary Brave selection).

## Image results and performance

Image search keeps the server-rendered **Next page** link as its baseline. With
the default `image_infinite=yes` preference and a browser that supports
`IntersectionObserver` and `fetch`, the next page is requested as the user nears
the end of the grid. Choosing **No** in Settings disables automatic loading. If
JavaScript is unavailable, the link provides normal page-by-page navigation.
If an automatic request fails, loading stops with a status message and offers
a **Restart image search** link. The restart preserves the query and filters,
removes the consumed continuation token, and begins again at page one.

Animated GIF, WebP, and APNG candidates start with the ordinary lazy
provider-thumbnail poster. A motion hint from either result URL or supported
provider MIME/format metadata selects the full-size original for validation and
playback. Every `.gif`, `.webp`, and `.apng` URL is eligible,
including ordinary WebP filenames; the proxy's multi-frame check restores the
poster when a WebP is actually static. An explicit provider format filter also
selects the full-size original when a signed CDN URL has no useful extension.
Data URLs are excluded. Near the viewport, the motion controller requests that
source through the same-origin
`/proxy?...&s=animated` privacy path—no lightbox click or direct provider fetch
is required. That endpoint caps the response at 20 MB, accepts only supported
raster MIME types, and validates at least two frames before forwarding the
bytes unchanged. It also enforces 1,000 frames, 16,384 pixels per axis, 40 MP
per frame, 250 million decoded pixel-frames, and 8,192 PNG chunks. GIF/WebP uses
Imagick frame counting; PNG/APNG uses strict PNG chunk parsing and the `acTL` frame count because Alpine Imagick can expose a
known APNG as one frame. An automatically
failed candidate returns to its poster and is not retried unless the user
deliberately requests it with pointer/focus.

Validation loads are queued and limited to three concurrent originals on
desktop or two on coarse-pointer/mobile devices. This bounds loading, not
playback: every visible candidate that passes validation keeps playing directly
in the grid without a click. Off-screen cards return to posters, and queued work
resumes as slots open. Infinite-scroll cards are registered automatically;
reduced-motion disables animation, and data-saver disables automatic
activation. Candidate discovery recognizes ordinary GIF/WebP extensions,
explicit APNG/animated-PNG hints, encoded URL format parameters, bounded GitHub
Camo source URLs, and Google or Brave MIME/format metadata. Provider hints allow
extensionless originals to be validated; static WebP candidates still fail
frame validation and return to their posters. SVG, video, gifv, inline data
URLs, and raster formats outside the GIF/WebP/APNG allowlist are not eligible.

The application admits at most 120 animated-preview requests per client address
per minute and uses a three-slot global validation semaphore. These server-side
bounds work with the browser queue so a large visible grid can continue to fill
without allowing unbounded simultaneous validation.

The production image enables PHP OPcache, HTTP compression and static caching;
image thumbnails and favicons use lazy loading and asynchronous decoding. The
default SecOps landing page uses the tracked `static/misc/secops.gif` background;
browsers requesting reduced motion or reduced data receive a static CSS
fallback. Google reuses validated CSE bootstrap parameters for at most
90 seconds per configured backend, CX, and outbound egress. It does not cache
queries or result documents. This normally removes the HTML and loader-script
bootstrap round trips from nearby searches and reduces upstream request volume.
A Google or Brave upstream transfer uses a 10-second connection timeout and a
20-second total timeout, bounding slow-path latency per request.
A recognized cached-token rejection deletes that entry, performs one fresh
bootstrap, and retries once; unusual-traffic/CAPTCHA responses are never retried
or treated as token failures. Encrypted next-page state keeps its original
proxy affinity.
Result-page polish is served as versioned, reusable CSS; missing font preloads
were removed; and release archives are excluded from the Docker build context.
Animated-grid originals are intentionally bounded and viewport-controlled as
described above; they can still use more bandwidth than static thumbnails.
The CSS, caching, lazy-loading, and build-context changes are delivery
optimizations, not benchmark guarantees. Perceived speed still depends on the
VPS, selected provider, throttling, outbound proxies, and network latency.

## Factual comparison

The products below have different trust models. “Proxy” means an intermediary
handles the query; it does not mean that the intermediary is automatically
trustworthy or that a user is anonymous. Hosted-service privacy entries summarize
the providers' own current policies, not an independent audit. Sources were
checked on 2026-09-01.

| Product | Search/result model | Deployment and control | Published data boundary and relevant UI |
|---|---|---|---|
| **Security Search** | Self-hosted 4get fork; selectable upstream scrapers, with Google configured by default and Brave available by explicit selection. It does not maintain an independent web index. | The instance operator controls configuration, logs and outbound proxying. Core search is server-rendered; provider failures do not silently resubmit a query elsewhere. | Upstreams normally see the instance egress rather than a direct browser request; the operator still processes queries. Google or Brave may challenge a VPS address. Images use default-on automatic loading with a Settings opt-out and **Next page** fallback. [Architecture](#architecture-and-trust-boundary), [providers](docs/PROVIDERS.md), [UI](docs/UI.md), [Google unusual-traffic guidance](https://support.google.com/websearch/answer/86640?hl=en). |
| **Upstream 4get** | Multi-provider proxy search engine with web, image, video, news and other scraper categories. | Open-source, instance-operated deployment with per-scraper rotating-proxy support. | Its official README says the interface does not require JavaScript. Privacy and logging ultimately depend on the chosen instance operator. [Official repository and feature list](https://git.lolcat.ca/lolcat/4get). |
| **Google Search** | Google-operated crawler, index and ranking systems covering web pages, images and other content. | Hosted and controlled by Google; result personalization and activity controls depend on context, account and settings. | Google's policy says collected activity may include search terms and interactions, plus device/request information such as IP address. [How Search works](https://developers.google.com/search/docs/fundamentals/how-search-works), [Privacy Policy](https://policies.google.com/privacy). |
| **Microsoft Bing** | Microsoft-operated crawler and index for web, image, video and other search experiences. | Hosted and controlled by Microsoft, with Bing and Microsoft Account controls. | Microsoft says Bing collects search terms together with data such as IP address, location, cookie identifiers, time and browser configuration. [How Bing delivers results](https://support.microsoft.com/en-us/bing/how-bing-delivers-search-results), [search-history data](https://support.microsoft.com/en-US/accounts-billing/how-microsoft-stores-and-maintains-your-search-history). |
| **DuckDuckGo** | Maintains DuckDuckBot and several indexes; it says traditional links and images are largely sourced from Bing. | DuckDuckGo-hosted service that proxies requests sent to result partners; HTML and Lite no-JavaScript variants are available with fewer features. | DuckDuckGo says it does not save or share personal search history and does not send partner requests with a user's IP or unique identifiers. [Result sources](https://duckduckgo.com/duckduckgo-help-pages/results/sources), [search privacy](https://duckduckgo.com/duckduckgo-help-pages/search-privacy), [non-JavaScript versions](https://duckduckgo.com/duckduckgo-help-pages/features/non-javascript). |
| **Brave Search** | Brave-operated independent crawler and index; optional Google fallback mixing is a separate user choice. | Hosted and controlled by Brave; web and image modes are available. | Brave's notice describes the service as private by default, documents optional aggregate metrics, ad measurement, anonymous local results and temporary IP processing for service integrity. [Privacy notice and index details](https://search.brave.com/help/privacy-policy). |
| **Startpage** | Hosted intermediary that submits queries to result partners including Google and Bing; it does not maintain its own web index. | Hosted and controlled by Startpage; its optional Anonymous View also proxies destination-page browsing. | Startpage says it does not record ordinary visits, searches or IP addresses, with an anti-abuse exception in its policy; image thumbnails are proxied. [Partner relationship](https://support.startpage.com/hc/en-us/articles/4522435533844-What-is-the-relationship-between-Startpage-and-your-search-partners-like-Google-and-Microsoft-Bing), [Privacy Policy](https://safe.startpage.com/en/privacy-policy/), [image search](https://support.startpage.com/hc/en-us/articles/4521419354132-How-to-search-for-images-on-Startpage). |

The v0.9.6 source keeps the existing network model (host port `5140` → container
port `80` on a `bridge` network), so nginx-proxy-manager does not need a routing
change. Build, provider, release, and production checks still gate publication
and deployment.

---

## Quick deploy

```bash
# After committing the tested source:
./release.sh 0.9.6
(cd dist && sha256sum -c securitysearch-v0.9.6.tar.gz.sha256)

# Follow docs/RELEASE.md to build /root/security-search-v0.9.6, then atomically
# swap that clean sibling into /root/security-search-update. Do not overlay it.
```

The intended Git publication targets and credential-free configured URLs are:

- `origin` — `git@github.com:cristiancmoises/securitysearch.git`
- `codeberg` — `git@codeberg.org:berkeley/securitysearch.git`
- `securityops` — `https://git.securityops.co/cristiancmoises/securitysearch.git`
- `securityops_br` — `https://git.securityops.com.br/cristiancmoises/securitysearch.git`

The v0.9.4 publication rewrote sanitized history. v0.9.6 must preserve that
history and publish the exact inventory `main` plus tags `v0.9.0` through
`v0.9.6`. Follow the per-ref lease, atomic-push, immutable-tag, and OID
verification procedure in [docs/RELEASE.md](docs/RELEASE.md) for every remote;
a failure on one must be reported even if another succeeds.

For local work or an already isolated source tree, `./deploy.sh --fresh`:

1. Sanity-checks docker / docker-compose are installed.
2. Backs up the current state to a `.tgz` in the parent directory.
3. Builds the image while the existing container remains online (with
   mirror-failover for Alpine package fetches —
   handles the geo-routing flakiness from Brazil/LATAM).
4. Stops the existing container only after the build succeeds.
5. Starts the new container, with automatic old-image rollback on a failed
   cutover.
6. Waits up to 60s for the healthcheck.
7. Discovers the effective Compose-published endpoint and smoke-tests it.
8. Prints useful follow-up commands.

Use `./deploy.sh --fresh` for a no-cache rebuild, or `./deploy.sh --logs` to
follow logs after starting. Production v0.9.6 uses the clean-sibling build and
atomic directory cutover in [docs/RELEASE.md](docs/RELEASE.md), so the active
tree is never updated by overlaying archive contents.

---

## Manual deploy (if you don't want the script)

```bash
docker compose down
docker compose build --no-cache
docker compose up -d
docker compose logs -f --tail=50 security-search
```

---

## What changed vs. your previous fork

See **[MIGRATION.md](MIGRATION.md)** for the full changelog.

Summary of high-impact changes:

| Area | Change |
|------|--------|
| **Security** | Captcha brute-force fix backported from upstream `4349bf2`. |
| **Security** | All HTTP security headers centralized in `lib/security_headers.php`. CSP tightened (no `'unsafe-inline'` in `script-src`). |
| **Security** | Container drops all kernel capabilities except those required by httpd, enables `no-new-privileges`, and applies resource limits. |
| **SEO** | `robots.txt` blocks dynamic search paths and 28 AI/SEO scrapers. `sitemap.php` has dynamic `<lastmod>`. |
| **SEO** | `template/home.html` now has `<link rel="canonical">`, Twitter Cards, and JSON-LD `WebSite` + `SearchAction` (Google sitelinks search box). |
| **Perf** | Apache OPcache, compression/static caching, reduced-motion/data fallbacks for the animated SecOps backdrop, lazy result media, bounded animation-validation queues, a release-free Docker build context, and a 90-second per-egress Google bootstrap cache that stores no queries or results. |
| **Backports** | Upstream fixes for Google, Yandex, Yep, Pinterest, Qwant, SoundCloud, fuckhtml.php JSON parser. |
| **Backports** | New image scrapers: Pexels, Unsplash, Pixabay. |
| **Reliability** | Dockerfile has multi-mirror failover for Alpine apk fetches. Healthcheck. tini PID 1. |
| **Providers** | Google is the configured web/image default through the bundled CSE-compatible transport. Unusual-traffic blocks are not retried; users get an explicit Brave action instead of a silent fallback. Direct Brave PoW challenges fail fast, while a configured pool can rotate for up to three bounded attempts. Google API remains opt-in with privately supplied keys. |
| **UX** | A minimalist, responsive landing page prioritizes the logo and search. The token-driven SecOps cascade works across the home/results UI, friendly errors provide clear actions, valid saved themes remain intact, and image auto-pagination is default-on with an opt-out. |

---

## What's NOT changed (preserved from your fork)

- The remaining bundled custom themes in `static/themes/`.
- Existing selectable themes and valid browser theme preferences.
- `template/donate.html`.
- Anubis bot policies in `anubis/`.
- `.well-known/security.txt` contact info.
- Fork-specific result and archive behavior in `lib/frontend.php`; result media
  now carries non-blocking browser loading hints.
- Unrelated static assets, including the existing audio and alternate-theme
  artwork.

---

## File map

```
security-search/
├── deploy.sh                            ← run this on the host
├── docker-compose.yml                   ← loopback/private host port 5140 → 80
├── Dockerfile                           ← Alpine 3.21 + PHP 8.4 + OPcache
├── .dockerignore                        ← excludes VCS, backups, docs, dist, icon cache
├── README.md                            ← this file
├── README.pt-BR.md                      ← full Brazilian Portuguese guide
├── manifest.webmanifest                 ← local install metadata
├── docs/UI.md                           ← landing hierarchy and responsive UI contract
├── MIGRATION.md                         ← detailed changelog
├── PATCHES.md                           ← incremental upgrade path
│
├── lib/
│   ├── security_headers.php             ← NEW — HTML response hardening
│   ├── security_headers_minimal.php     ← NEW — non-HTML response hardening
│   ├── bot_protection.php               ← captcha brute-force fix applied
│   ├── frontend.php                     ← scraper registry + lazy result media
│   ├── fuckhtml.php                     ← upstream JSON-parser fixes
│   └── ... (other libs unchanged)
│
├── scraper/
│   ├── pexels.php / unsplash.php / pixabay.php   ← NEW image sources
│   ├── google.php / google_cse.php / yandex.php / yep.php /
│   │   pinterest.php / qwant.php / brave.php     ← upstream fixes
│   └── ... (other scrapers unchanged)
│
├── docker/
│   ├── docker-entrypoint.sh             ← unchanged
│   ├── gen_config.php                   ← unchanged
│   ├── apache/
│   │   ├── http/httpd.conf              ← hardened (mod_remoteip, mod_deflate, mod_expires)
│   │   └── https/httpd.conf             ← hardened (modern TLS profile)
│   └── nginx-proxy-manager.conf         ← OPTIONAL paste-into-NPM advanced config
│
├── template/
│   ├── home.html                        ← search-first responsive landing page
│   └── images.html                      ← default-on progressive auto-pagination
│
├── static/home.js                       ← CSP-safe Services keyboard enhancement
├── static/images-infinite.js            ← image IntersectionObserver enhancement
├── static/images-motion.js              ← queued validated GIF/WebP/APNG playback
├── static/misc/secops.gif               ← tracked SecOps home background
├── static/{web,image}-results.css       ← cacheable page-specific result polish
├── static/themes/*.css                  ← bundled themes; SecOps is the default
├── anubis/                              ← bot policies preserved
├── .well-known/security.txt             ← preserved
└── robots.txt                           ← rewritten for crawl-budget management
```

---

## Verify after deploy

```bash
# Container healthy?
docker compose ps
# → Up X seconds (healthy)

# Discover the actual loopback/private bind and hit it directly (bypasses NPM).
app_endpoint=$(docker compose port security-search 80 | tail -n 1)
app_base="http://$app_endpoint"
curl -sI "$app_base/" | grep -iE 'content-security|strict-transport|x-frame'
# Expect: Content-Security-Policy: default-src 'none'; script-src 'self'; ...

# Through NPM (production URL)
curl -sI https://securityops.co/ | head -15
curl -s https://securityops.co/ | grep -o '<title>[^<]*</title>'
# Expect: <title>Security Search — Privacy-First Metasearch Engine</title>

# SecOps is selected and cache-busted for a first visit?
curl -fsS "$app_base/" |
  grep -q '/static/themes/SecOps.css?v12'
curl -fsSI "$app_base/static/themes/SecOps.css?v12" |
  grep -qi '^Content-Type: text/css'

# Search works? Assert result content; HTTP 200 alone also describes an error page.
release_ua='Mozilla/5.0 (release-smoke-test)'
google_web=$(curl -fsS -A "$release_ua" \
  "$app_base/web?s=security+privacy&scraper=google")
printf '%s' "$google_web" | grep -q 'class="text-result"'
! printf '%s' "$google_web" | grep -qi 'Search provider unavailable'

google_images=$(curl -fsS -A "$release_ua" \
  "$app_base/images?s=network+security&scraper=google")
printf '%s' "$google_images" | grep -q 'class="image-wrapper"'

# Sitemap valid?
curl -fsS https://securityops.co/sitemap | head -5
```

Repeat result-bearing checks for the API and Brave as described in
[docs/RELEASE.md](docs/RELEASE.md). A Google unusual-traffic page means the
instance egress is temporarily blocked; confirm the neutral message and
explicit **Try Brave** URL, but do not count it as successful Google results.

External validators:
- https://securityheaders.com/?q=securityops.co (target: A or A+)
- https://www.ssllabs.com/ssltest/analyze.html?d=securityops.co (target: A or A+)
- https://search.google.com/test/rich-results?url=https%3A%2F%2Fsecurityops.co%2F (target: WebSite + SearchAction parsed OK)

---

## Rollback

Before the v0.9.6 clean-tree cutover, create the exact rollback archive and
timestamped old directory documented in [docs/RELEASE.md](docs/RELEASE.md). The
active IONOS tree remains `/root/security-search-update`.

```bash
cd /root
docker compose -f /root/security-search-update/docker-compose.yml down
mv /root/security-search-update /root/security-search-update-failed-v0.9.6
mv /root/security-search-update-old-YYYYMMDDTHHMMSSZ \
  /root/security-search-update
docker image tag security-search:pre-v0.9.6 security-search:latest
cd /root/security-search-update
docker compose up -d --no-build
```

Use the exact recorded timestamp. If the old directory is unavailable, restore
the exact predeploy `.tgz` only after confirming the active path does not exist.
Delete the old directory and rollback image only after local/public health and
result checks pass; retain the `.tgz` as the release rollback archive.

---

## Troubleshooting

### `apk update` fails during build

The Dockerfile already includes mirror failover (tries 5 mirrors before
giving up). If even that fails, you have a network problem on the host.
Test:

```bash
docker run --rm alpine:3.21 sh -c 'apk update'
curl -sI https://dl-cdn.alpinelinux.org/alpine/v3.21/main/x86_64/APKINDEX.tar.gz
```

If the host can reach Alpine's CDN but the build cannot, configure
docker DNS:

```bash
sudo tee /etc/docker/daemon.json <<'EOF'
{
  "dns": ["1.1.1.1", "9.9.9.9", "8.8.8.8"],
  "dns-opts": ["ndots:0"]
}
EOF
sudo systemctl restart docker
```

### `pull access denied for security-search`

This is harmless. Compose tries the registry first, fails, falls back
to building. As long as you see `Building` afterwards, it's working.

### Container status: `unhealthy`

```bash
docker compose logs --tail=100 security-search
```

Most common cause: a PHP fatal error during request handling. Look for
`PHP Fatal error` lines. The provided Compose file currently keeps
`read_only: false`; if you enable the optional read-only-root hardening and see
filesystem errors, add the required tmpfs/writable mount or disable that option
while investigating the exact path.

### Google reports unusual traffic

Google applies this response to the instance's outbound IP or network. It is
not repaired by repeatedly refreshing CSE tokens, and repeated retries can make
an anti-abuse event worse. v0.9.4 and later recognize the condition, stop, and
offer an explicit Brave choice without sending the query automatically.

Verify the server's outbound traffic and rate, wait for the restriction to
clear, or configure a legitimate operator-controlled egress path. Google lists
automated services, search scrapers, VPNs, and shared networks among possible
causes in its [official unusual-traffic guidance](https://support.google.com/websearch/answer/86640?hl=en).

If the error view appears, confirm that it contains **Search provider
unavailable**, **Retry search**, **Provider settings**, and **Try Brave**, then
inspect `docker compose logs --tail=100 security-search` for PHP errors. Do not
describe the error page itself as working Google search.

### NPM proxy returns 502

The container is up but NPM can't reach it. Verify the host port:

```bash
docker compose ps
app_endpoint=$(docker compose port security-search 80 | tail -n 1)
curl -I "http://$app_endpoint/"             # must return HTTP 200
ss -tnlp | grep 5140                         # must show only loopback/private bind
```

Loopback is the secure default. A containerized NPM cannot reach the host's
loopback; set `SECURITYSEARCH_BIND_ADDRESS` in a mode-0600 `.env` to the private
Docker-host bridge address that NPM already uses (for example `172.17.0.1`),
then point NPM to that private address and port 5140. Never use the public VPS
address or `0.0.0.0`. Verify reachability from inside the NPM container and
verify externally that direct port 5140 is closed.

---

## License

AGPL-3.0 (inherited from upstream 4get).
