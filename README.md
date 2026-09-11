# SecuritySearch v0.9.30

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![SecuritySearch v0.9.30 homepage](docs/screenshots/securitysearch-0.9.30-home.png)

Local Chromium rendering of the v0.9.30 PHP-generated Black homepage with bundled resources
embedded. It is visual release evidence, not a live-VPS screenshot, Lighthouse run or
competitive benchmark. [Mobile capture](docs/screenshots/securitysearch-0.9.30-mobile.png).

SecuritySearch is a privacy-oriented PHP search proxy based on
[4get](https://git.lolcat.ca/lolcat/4get), maintained by Security Ops. Normal searches and
bundled themes work without JavaScript; local pictures and image enhancements use optional
same-origin scripts. External providers can refuse or rate-limit requests.

## v0.9.30: concurrent delivery without removing search features

This release targets server concurrency and decorative result-page work. Google/CSE, Brave,
Binternet, RSS news, image layouts, pagination, My Picture, themes, privacy controls,
deployment gates and rollback behavior are preserved.

### Apache event MPM + PHP-FPM

The previous image used Apache prefork/mod_php with a `MaxRequestWorkers` ceiling of 16.
v0.9.30 makes **Apache event MPM + PHP-FPM** the verified deployment runtime. The PHP pool
keeps `pm.max_children = 16`, so this release does not raise the prior PHP concurrency ceiling;
instead, Apache can handle static files and keep-alive connections separately from those
PHP children.

The upstream 4get Apache guide also recommends event MPM with PHP-FPM. SecuritySearch does
not copy the much larger pool size used by the public 4get.ca instance because safe worker
capacity depends on this VPS's memory and traffic. The prior prefork/mod_php runtime remains
an explicit operator fallback, but normal deployment pins FPM and refuses a candidate whose
FPM/event readiness checks fail.

Docker health checks both `/` and the PHP-backed `/settings` route, so a surviving static
homepage cannot hide a dead PHP-FPM pool.

### Favicons cannot monopolize search workers

Result favicons are cosmetic. A cold favicon miss previously had an eight-second remote
budget and could compete with useful search work. v0.9.30 keeps favicon discovery and the
existing fallback, but limits remote favicon work to a 2.5-second total budget and, when
APCu is available, at most four concurrent remote refreshes.

Duplicate work for the same host is temporarily suppressed. Recent failures receive a
short negative cache; successful stored icons get one-day browser caching and the existing
404 placeholder gets a five-minute browser cache. APCu keys contain hashed host identifiers,
not queries, result bodies, cookies or credentials. Failed favicons never turn a search
result into a failed search page.

## Performance and benchmark boundaries

The supplied `tools/secops-web-benchmark-v3.fish` remains **manual and byte-for-byte
unchanged**. It is not executed during install, deployment, release audit or packaging.
No winner badge or competitive result is published by this release.

Run it later on GNU Guix:

```fish
fish --no-config "$HOME/securitysearch/tools/secops-web-benchmark-v3.fish"
```

Or with an existing Python/curl toolchain:

```fish
fish --no-config "$HOME/securitysearch/tools/secops-web-benchmark-v3.fish" --system-deps
```

The benchmark measures HTTP homepage HTML delivery, not browser LCP, provider search latency
or search quality. A new result from the same client/network is required before claiming
SecuritySearch has overtaken 4get.ca.

## Preserved search, image and privacy behavior

Google web/image search retains the v0.9.26+ query-free bootstrap, record validation,
session-race fixes, explicit pagination offsets and bounded first-page Brave fallback.
Binternet retains modern/legacy parsing, six image layouts, ordinary pagination, optional
infinite scrolling and bounded animation controls. News RSS remains the default news source.

External Redlib instances are operated by independent third parties, **not by Security Ops**.
Security Ops maintains only the integration. **My Picture** continues to read and normalize
a selected file in the browser without uploading the picture, filename or EXIF metadata.
The historical Lain/SecOps operator pack remains outside Git and all public release assets,
including Codeberg.

## Apply, audit and deploy

From the extracted complete `securitysearch-update-0.9.30` kit, on a clean exact v0.9.29
checkout:

```fish
fish ./apply-securitysearch.fish "$HOME/securitysearch"
and fish ./deploy-securitysearch.fish "$HOME/securitysearch" \
    --theme-assets "$HOME/.local/share/securitysearch/operator-themes-v1" \
    --verify-google \
    --rank-refresh
```

Deployment uses `root@securityops.co`, SSH port 5119, and preserves the established Docker
networks/binding and Nginx Proxy Manager upstream. The complete isolated native audit,
candidate readiness, FPM/event runtime identity, RSS verification, live Binternet and
requested Google web/image checks must pass before cutover. A failed candidate leaves the
current production container in place. Keep every printed backup and rollback directory.

## Publish v0.9.30

After successful deployment:

```fish
fish ./publish-securitysearch.fish "$HOME/securitysearch"
# Retry one host only, including release attachments:
fish ./publish-securitysearch.fish "$HOME/securitysearch" --host git.securityops.com.br
```

Publication creates/reuses the annotated `v0.9.30` tag and publishes
`securitysearch-v0.9.30.tar.gz` plus its `.tar.gz.sha256`. Different existing tags, notes
or assets are preserved rather than force-replaced. Operator artwork never enters the
public source package.
The published
v0.9.24 tag and later historical release tags remain unchanged.

## Validation

```sh
sh scripts/test.sh --keep-going
```

Every earlier mandatory suite remains. v0.9.30 adds favicon-admission, favicon-HTTP,
FPM/event source, native FPM configuration and version-specific package/publication coverage.
The native FPM check must run in the Alpine production-image audit; a missing local
`php-fpm84` is not a pass. See [release notes](docs/RELEASE-0.9.30.md),
[performance notes](docs/PERFORMANCE-0.9.30.md) and [audit](docs/AUDIT-0.9.30.md).

License: [AGPL-3.0](license.txt). **In Code We Trust.**
