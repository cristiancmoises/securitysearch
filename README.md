# Security Search

[English](README.md) | [Português do Brasil](README.pt-BR.md)

Privacy-first proxy metasearch engine. Hardened fork of
[4get](https://git.lolcat.ca/lolcat/4get) deployed at
[securityops.co](https://securityops.co).

## Current release: v0.9.3

- The landing page is search-first: Settings, the Security Search logo, the
  primary search field, a compact privacy/provider hint, and two quiet links to
  [SecurityOps](https://securityops.co/) and
  [SecurityOps Brasil](https://securityops.com.br/).
- SecOps is the default theme for new visitors. A valid theme already saved in
  the browser remains selected.
- Google is the default provider for web and image search.
- Brave is available from the Scraper picker for web and image search.
- Image results load additional pages automatically by default. Settings offers
  an opt-out, and the normal **Next page** link remains the progressive fallback.
- Landing-page hierarchy, responsive behavior, and accessibility expectations:
  [docs/UI.md](docs/UI.md).
- Provider behavior and configuration: [docs/PROVIDERS.md](docs/PROVIDERS.md).
- Packaging, IONOS/Evelin deployment, verification, and rollback:
  [docs/RELEASE.md](docs/RELEASE.md).
- The release-quality improvement brief is available in
  [docs/GOD_TIER_SEARCH_ENGINE_PROMPT.md](docs/GOD_TIER_SEARCH_ENGINE_PROMPT.md).

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
  - FOURGET_DEFAULT_SCRAPER_WEB=google
  - FOURGET_DEFAULT_SCRAPER_IMAGES=google
```

A valid saved browser preference takes precedence over its configured default.
Query-string provider selection takes precedence over both. Brave can run
directly or through the proxy pool named by `FOURGET_PROXY_BRAVE`; never commit
proxy credentials or Google API keys. See [provider configuration](docs/PROVIDERS.md)
and the upstream [configuration guide](docs/configure.md).

## Image results and performance

Image search keeps the server-rendered **Next page** link as its baseline. With
the default `image_infinite=yes` preference and a browser that supports
`IntersectionObserver` and `fetch`, the next page is requested as the user nears
the end of the grid. Choosing **No** in Settings disables automatic loading. If
JavaScript is unavailable, the link provides normal page-by-page navigation.
If an automatic request fails, loading stops with a status message and offers
a **Restart image search** link. The restart preserves the query and filters,
removes the consumed continuation token, and begins again at page one.

The production image enables PHP OPcache, HTTP compression and static caching;
image thumbnails and favicons use lazy loading and asynchronous decoding. The
default SecOps landing backdrop is CSS-only instead of downloading its former
18.9 MB animation. Google CSE bootstrap tokens—not queries or results—are cached
for five minutes per endpoint and outbound proxy, with a bounded refresh for an
expired token. Result-page polish is served as versioned, reusable CSS; missing
font preloads were removed; and release archives are excluded from the Docker
build context.
These are delivery optimizations, not benchmark guarantees. Perceived speed
still depends on the VPS, selected provider, throttling, outbound proxies, and
network latency.

## Factual comparison

The products below have different trust models. “Proxy” means an intermediary
handles the query; it does not mean that the intermediary is automatically
trustworthy or that a user is anonymous. Hosted-service privacy entries summarize
the providers' own current policies, not an independent audit. Sources were
checked on 2026-09-01.

| Product | Search/result model | Deployment and control | Published data boundary and relevant UI |
|---|---|---|---|
| **Security Search** | Self-hosted 4get fork; selectable upstream scrapers, with Google default and Brave available. It does not maintain an independent web index. | The instance operator controls configuration, logs and outbound proxying. Core search is server-rendered. | Upstreams normally see the instance egress rather than a direct browser request; the operator still processes queries. Images use default-on automatic loading with a Settings opt-out and **Next page** fallback. [Architecture](#architecture-and-trust-boundary), [providers](docs/PROVIDERS.md), [UI](docs/UI.md). |
| **Upstream 4get** | Multi-provider proxy search engine with web, image, video, news and other scraper categories. | Open-source, instance-operated deployment with per-scraper rotating-proxy support. | Its official README says the interface does not require JavaScript. Privacy and logging ultimately depend on the chosen instance operator. [Official repository and feature list](https://git.lolcat.ca/lolcat/4get). |
| **Google Search** | Google-operated crawler, index and ranking systems covering web pages, images and other content. | Hosted and controlled by Google; result personalization and activity controls depend on context, account and settings. | Google's policy says collected activity may include search terms and interactions, plus device/request information such as IP address. [How Search works](https://developers.google.com/search/docs/fundamentals/how-search-works), [Privacy Policy](https://policies.google.com/privacy). |
| **Microsoft Bing** | Microsoft-operated crawler and index for web, image, video and other search experiences. | Hosted and controlled by Microsoft, with Bing and Microsoft Account controls. | Microsoft says Bing collects search terms together with data such as IP address, location, cookie identifiers, time and browser configuration. [How Bing delivers results](https://support.microsoft.com/en-us/bing/how-bing-delivers-search-results), [search-history data](https://support.microsoft.com/en-US/accounts-billing/how-microsoft-stores-and-maintains-your-search-history). |
| **DuckDuckGo** | Maintains DuckDuckBot and several indexes; it says traditional links and images are largely sourced from Bing. | DuckDuckGo-hosted service that proxies requests sent to result partners; HTML and Lite no-JavaScript variants are available with fewer features. | DuckDuckGo says it does not save or share personal search history and does not send partner requests with a user's IP or unique identifiers. [Result sources](https://duckduckgo.com/duckduckgo-help-pages/results/sources), [search privacy](https://duckduckgo.com/duckduckgo-help-pages/search-privacy), [non-JavaScript versions](https://duckduckgo.com/duckduckgo-help-pages/features/non-javascript). |
| **Brave Search** | Brave-operated independent crawler and index; optional Google fallback mixing is a separate user choice. | Hosted and controlled by Brave; web and image modes are available. | Brave's notice describes the service as private by default, documents optional aggregate metrics, ad measurement, anonymous local results and temporary IP processing for service integrity. [Privacy notice and index details](https://search.brave.com/help/privacy-policy). |
| **Startpage** | Hosted intermediary that submits queries to result partners including Google and Bing; it does not maintain its own web index. | Hosted and controlled by Startpage; its optional Anonymous View also proxies destination-page browsing. | Startpage says it does not record ordinary visits, searches or IP addresses, with an anti-abuse exception in its policy; image thumbnails are proxied. [Partner relationship](https://support.startpage.com/hc/en-us/articles/4522435533844-What-is-the-relationship-between-Startpage-and-your-search-partners-like-Google-and-Microsoft-Bing), [Privacy Policy](https://safe.startpage.com/en/privacy-policy/), [image search](https://support.startpage.com/hc/en-us/articles/4521419354132-How-to-search-for-images-on-Startpage). |

This bundle is **complete and ready to deploy**. Same network model as the
original (host port `5140` → container port `80` on a `bridge` network),
so your nginx-proxy-manager configuration does not need to change.

---

## Quick deploy

```bash
# On your VPS, after uploading the release tarball:
tar -xzf securitysearch-v0.9.3.tar.gz
cd securitysearch-v0.9.3
./deploy.sh --fresh
```

That's it. The script:

1. Sanity-checks docker / docker-compose are installed.
2. Backs up the current state to a `.tgz` in the parent directory.
3. Stops the existing container.
4. Builds the image (with mirror-failover for Alpine package fetches —
   handles the geo-routing flakiness from Brazil/LATAM).
5. Starts the new container.
6. Waits up to 60s for the healthcheck.
7. Smoke-tests `http://127.0.0.1:5140/`.
8. Prints useful follow-up commands.

Use `./deploy.sh --fresh` for a no-cache rebuild,
or `./deploy.sh --logs` to follow logs after starting.

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
| **Perf** | Apache OPcache, compression/static caching, a CSS-only default backdrop, lazy result media, a five-minute non-query Google bootstrap cache, and a release-free Docker build context. |
| **Backports** | Upstream fixes for Google, Yandex, Yep, Pinterest, Qwant, SoundCloud, fuckhtml.php JSON parser. |
| **Backports** | New image scrapers: Pexels, Unsplash, Pixabay. |
| **Reliability** | Dockerfile has multi-mirror failover for Alpine apk fetches. Healthcheck. tini PID 1. |
| **Providers** | Google is the production default for web/images through the bundled CSE-compatible transport; Brave is enabled and carries current upstream CAPTCHA/pagination handling. |
| **UX** | A minimalist, responsive landing page prioritizes the logo and Google-default search; SecOps is the first-visit theme, valid saved themes remain intact, and image auto-pagination is default-on with an opt-out. |

---

## What's NOT changed (preserved from your fork)

- All 20 custom themes in `static/themes/`.
- Existing selectable themes and valid browser theme preferences.
- `template/about.html`, `template/donate.html`.
- Anubis bot policies in `anubis/`.
- `.well-known/security.txt` contact info.
- Fork-specific result and archive behavior in `lib/frontend.php`; result media
  now carries non-blocking browser loading hints.
- All static assets (banners, audio, theme images).

---

## File map

```
security-search/
├── deploy.sh                            ← run this on the host
├── docker-compose.yml                   ← matches your existing 5140:80 setup
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
├── static/{web,image}-results.css       ← cacheable page-specific result polish
├── static/themes/*.css                  ← all 20 themes; SecOps is the default
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

# Direct hit (bypasses NPM)
curl -sI http://127.0.0.1:5140/ | grep -iE 'content-security|strict-transport|x-frame'
# Expect: Content-Security-Policy: default-src 'none'; script-src 'self'; ...

# Through NPM (production URL)
curl -sI https://securityops.co/ | head -15
curl -s https://securityops.co/ | grep -o '<title>[^<]*</title>'
# Expect: <title>Security Search — Privacy-first proxy search engine | securityops.co</title>

# Sitemap valid?
curl -s https://securityops.co/sitemap | head -5

# Search works?
curl -sI 'https://securityops.co/web?s=test' | head -3
```

External validators:
- https://securityheaders.com/?q=securityops.co (target: A or A+)
- https://www.ssllabs.com/ssltest/analyze.html?d=securityops.co (target: A or A+)
- https://search.google.com/test/rich-results?url=https%3A%2F%2Fsecurityops.co%2F (target: WebSite + SearchAction parsed OK)

---

## Rollback

The deploy script creates a tarball before each run.

```bash
docker compose down
cd ..
rm -rf security-search
tar xzf sec-search-backup-YYYY-MM-DD-HHMM.tgz
cd security-search
docker compose up -d
```

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

### NPM proxy returns 502

The container is up but NPM can't reach it. Verify the host port:

```bash
docker compose ps                          # confirm 0.0.0.0:5140->80
curl -I http://127.0.0.1:5140/             # must return HTTP 200
ss -tnlp | grep 5140                       # must show docker-proxy
```

Then in NPM, confirm the proxy host points to `<your-vps-ip>:5140` (not
the public IP, the same IP as `127.0.0.1` from the VPS's perspective —
typically the docker bridge gateway, e.g. `172.17.0.1`).

---

## License

AGPL-3.0 (inherited from upstream 4get).
