# Security Search

Privacy-first proxy metasearch engine. Hardened fork of
[4get](https://git.lolcat.ca/lolcat/4get) deployed at
[securityops.co](https://securityops.co).

This bundle is **complete and ready to deploy**. Same network model as the
original (host port `5140` → container port `80` on a `bridge` network),
so your nginx-proxy-manager configuration does not need to change.

---

## Quick deploy

```bash
# On your VPS, in the directory you want to host the project:
unzip security-search-update.zip -d security-search
cd security-search
./deploy.sh
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
| **Security** | Container runs read-only rootfs, dropped all kernel caps except 5 needed by httpd, `no-new-privileges`, resource limits. |
| **SEO** | `robots.txt` blocks dynamic search paths and 28 AI/SEO scrapers. `sitemap.php` has dynamic `<lastmod>`. |
| **SEO** | `template/home.html` now has `<link rel="canonical">`, Twitter Cards, and JSON-LD `WebSite` + `SearchAction` (Google sitelinks search box). |
| **Perf** | Apache OPcache enabled (`validate_timestamps=0` for immutable deploys), `mod_deflate`, `mod_expires` with proper cache headers. |
| **Backports** | Upstream fixes for Google, Yandex, Yep, Pinterest, Qwant, SoundCloud, fuckhtml.php JSON parser. |
| **Backports** | New image scrapers: Pexels, Unsplash, Pixabay. |
| **Reliability** | Dockerfile has multi-mirror failover for Alpine apk fetches. Healthcheck. tini PID 1. |

---

## What's NOT changed (preserved from your fork)

- All 20 custom themes in `static/themes/`.
- `template/home.html` body, footer, audio element, all SecurityOps subdomain links, all Tor onion links.
- `template/about.html`, `template/donate.html`.
- Anubis bot policies in `anubis/`.
- `.well-known/security.txt` contact info.
- All 382 lines of fork-specific code in `lib/frontend.php`
  (SecOps theme branching, 4chan archive logic).
- All static assets (banners, audio, theme images).

---

## File map

```
security-search/
├── deploy.sh                            ← run this on the host
├── docker-compose.yml                   ← matches your existing 5140:80 setup
├── Dockerfile                           ← Alpine 3.21 + PHP 8.4 + OPcache
├── .dockerignore                        ← excludes .git, *.bak, docs, icons cache
├── README.md                            ← this file
├── MIGRATION.md                         ← detailed changelog
├── PATCHES.md                           ← incremental upgrade path
│
├── lib/
│   ├── security_headers.php             ← NEW — HTML response hardening
│   ├── security_headers_minimal.php     ← NEW — non-HTML response hardening
│   ├── bot_protection.php               ← captcha brute-force fix applied
│   ├── frontend.php                     ← scraper registry updated
│   ├── fuckhtml.php                     ← upstream JSON-parser fixes
│   └── ... (other libs unchanged)
│
├── scraper/
│   ├── pexels.php / unsplash.php / pixabay.php   ← NEW image sources
│   ├── google.php / yandex.php / yep.php /
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
│   └── home.html                        ← <head> rewritten for SEO; body unchanged
│
├── static/themes/*.css                  ← all 20 fork themes preserved
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
`PHP Fatal error` lines. If you see `read-only file system` errors,
some path needs a tmpfs mount — comment out `read_only: true` in
`docker-compose.yml` and report the path.

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
