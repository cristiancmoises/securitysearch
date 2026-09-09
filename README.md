# Security Search v0.9.19

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![Security Search — Tron](docs/screenshots/securitysearch-home.jpg)

Live homepage captured on 2026-09-09 at securityops.co (Tron, 1363 × 936). The instance served asset 22; v0.9.19 retains this interface and uses asset 23. This is a real browser capture.

A PHP search proxy based on [4get](https://git.lolcat.ca/lolcat/4get), maintained for [SecurityOps](https://securityops.co/). This complete source release uses two small, same-origin JavaScript enhancements for **infinite image scrolling and visible animated previews**. Other pages remain script-free; CSP permits scripts and fetch connections only on image search.

- Minimal 14 px SVG icons sit in a single row **inside the right edge of the search bar**, with labels on hover/focus: **Search → Search Image → Search Pinterest → Search YouTube**. Native forms route Pinterest through Binternet and YouTube through Invidious.
- Image search has six views: Grid, Compact grid, Gallery, Large feed, List and Filmstrip; Fast preview, High quality (up to 1280 px) and Original quality choices; and provider-specific format filters including GIF and WebP.
- **Infinite image scrolling** appends results, including sideways in Filmstrip. The timer panel is removed. Settings can disable automatic loading; native Next page navigation works without JavaScript. Long documents offer manual continuation before exceeding 480 cards.
- **Google stays the default.** A failed first web/image search automatically tries Brave once, with compatible filters and a visible provider notice. Google gets up to 12 seconds of network time and Brave up to 8 within a shared 20-second deadline. Continuations retain their provider; API behavior is unchanged. If both providers fail, the page reports an honest 503 with recovery links.
- **GIF, animated WebP and APNG previews play when visible**, with at most two loading and four active. Hidden/offscreen or disabled animations use static posters. Reduced motion, Save-Data and a separate Settings opt-out are respected.
- **Reddit** joins the top links. Local `/news` shows the latest r/news + r/worldnews posts; a keyword search shows related posts through your Redlib instance. The external News link still opens news.securityops.co.
- Tron uses a lightweight black/cyan background and clear text. The old 12 MB GIF is not fetched by default.
- Native image/source links, Original/Preview/View animation actions, a services disclosure, and the centered **In Code We Trust.** footer with plain Wiki/Git links work without scripts.
- Existing navigation, API support, saved themes, transport limits, private-address rejection, query-free Apache access logs and encrypted ordinary pagination are retained.

[Operations and IONOS deployment](docs/OPERATIONS-0.9.19.md) · [Audit and test evidence](docs/AUDIT-0.9.19.md) · [UI behavior](docs/UI.md) · [Release packaging](docs/RELEASE.md)

## Services

| Search integration | Instance |
|---|---|
| YouTube through Invidious | https://invidious.securityops.co |
| Pinterest through Binternet | https://images.securityops.co |
| Reddit news through Redlib | https://libre.securityops.co |

The navigation's **Img** link intentionally points to `https://img.securityops.co/`. Git links point to `https://git.securityops.co/`. Home, Settings, Vids, Img, Wiki, Reddit, Chat, Zupt, News and BR retain the requested destinations.

## Publish the complete update

The `v0.9.19` publication kit includes a history-preserving Git bundle, the complete source archive, checksums and one fish launcher. It updates an existing clean `main` checkout from the Codeberg history, then pushes the branch/tag and creates or resumes the release on Codeberg, GitHub and both SecurityOps forges. Enter each host's own token in the terminal. Re-running completes missing hosts/assets; conflicting tags or assets are never replaced.

```fish
fish ./publish-securitysearch-v0.9.19.fish ~/securitysearch
```

Run from the extracted publication kit. The launcher prints the checkout/commit and uses a fast-forward update, so your existing history remains. If `data/config.php` still says `VERSION = 17`, pushing the checkout alone publishes the old source; import this kit first. [English publication guide](docs/PUBLISHING.md) · [Português do Brasil](docs/PUBLISHING.pt-BR.md) · [Bilingual release notes](docs/RELEASE-0.9.19.md).

## Existing IONOS installation

Use the supplied `deploy-securitysearch-v0.9.19.fish` with the archive and checksum in `~/Downloads`. It copies over SSH port **5119** to **root@securityops.co**, builds and checks a candidate, and updates the existing `security-search` container while preserving **172.17.0.1:5140 → 80**. The updater retains a rollback container and private-data backups. Read the operation guide before pruning any retained files.

The Docker build and live VPS deployment were not run in the authoring environment. Candidate readiness and cutover checks run on your VPS when you execute the script. Existing Docker capabilities, security options and resource limits are inherited; fresh installations using the included Compose file apply its explicit hardening settings.

## Development and tests

```sh
sh scripts/test.sh
```

Requires PHP with curl, mbstring, DOM/XML, APCu, sodium, fileinfo and Imagick, plus Python 3 and Node.js (tests only). The suite includes isolated localhost HTTP controllers, neutral provider fixtures and real two-frame media files; it makes no external search requests. Docker targets Alpine 3.24 / PHP 8.4 / ImageMagick 7; local validation used PHP 8.3.

The runtime generates configuration from `FOURGET_*` environment variables. Default image/web provider is Google, default news provider is Reddit, and default theme is Tron. Existing saved choices take precedence. The v0.9.19 updater explicitly migrates the instance default to Tron, while retaining browser theme preferences. See [configuration](docs/configure.md), the current operation guide and the bundled Compose file. Keep secrets out of source archives.

The Redlib instance returned HTTP 503 during the 2026-09-09 availability check. Its parser and route integration passed offline fixtures; live Reddit news depends on the instance recovering. Brave image results have no continuation; its format filter checks the returned page locally and may leave no matches.

This is a search proxy, not an independent index. The operator processes searches and selected upstreams can rate-limit requests. Ordinary continuation tokens remain encrypted and short-lived. Timer snapshots are removed; old frame links expire safely. Reverse-proxy logs must also omit query strings. See the audit for limits.

Historical release details remain in [the previous README](docs/HISTORY-README-0.9.14.md), MIGRATION.md and PATCHES.md. Licensed under the repository's [AGPL-3.0 license](license.txt).
