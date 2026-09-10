# Security Search v0.9.22

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![Security Search — pure black](docs/screenshots/securitysearch-0.9.21-home-black.png)

**Historical v0.9.21 local rendering, not a new production-VPS screenshot.** The actual PHP
controller generated this HTML; Chromium rendered it with bundled resources
embedded and scripts disabled. Capture asset version: **25**; this release uses **26**.

Security Search is a PHP search proxy based on [4get](https://git.lolcat.ca/lolcat/4get),
maintained for [SecurityOps](https://securityops.co/). External search providers can
refuse requests, rate-limit servers or change their pages. This release does not
claim that every provider is available or that every search is faster.

## Maintenance repair r2

Includes the r1 archive-layout repair and the r2 optional-theme deployment import
fix. The Docker audit collects every suite failure before returning nonzero;
no failed test allows cutover. Use the matching **0.9.22-r2 kit** for an already
applied v0.9.22 or v0.9.22-r1 checkout. Application version and asset marker stay
unchanged. [Repair and validation boundaries](docs/AUDITFIX-0.9.22-r2.md).

## v0.9.22 repair and operator-only historical themes

The offline news fixture now intercepts every Redlib fallback origin; test-only
DNS/socket/cURL tripwires catch leaks instead of waiting for external transport.
The client timeout is unchanged. Production cURL and deployment gates remain on.
Fixed service/CDN DNS answers can be reused for at most 15 seconds after public-IP
validation; arbitrary hosts are request-local only. No query/results are cached by
this DNS helper. First synchronous DNS resolution can exceed cURL timeouts; this
is reduced repeated work, not a measured live speed guarantee.

Tron matches the specified historical commit. Lain/SecOps originals are optional
**operator-only** assets: a pinned, verified restorer prepares animations, stills
and previews outside Git, then `--theme-assets` adds them only to the private VPS
archive. Their originals and derivatives do not enter any public source release,
including Codeberg. Existing historical objects are not rewritten. The public
palette/Matrix fallbacks remain usable without the pack. See the
[complete commands and policy](docs/OPERATIONS-0.9.22.md).

## Search and news

Web/image Google searches retain their bounded, visibly labelled Brave fallback.
Existing pages do not silently switch provider. Legacy provider calls share the
existing request-local connection/request budget; definite native cURL transport
failures now receive a narrowly scoped eight-second first-page cooldown. Parser
errors and empty results are not cached as network failures. Video/news/music
adapters with deadline support receive the shared request deadline.

Reddit news tries **libre.securityops.co → redlib.nadeko.net →
redlib.privacyredirect.com**, sequentially, at most three attempts. Each attempt
gets at most 3.5 seconds within a shared ten-second budget. Failed instances cool
for twenty seconds. The responding instance is named, and authenticated
pagination stays with it. Only the public, query-free first news feed is cached
for sixty seconds; keyword searches and personal results are not cached.

**Privacy boundary:** when the primary fails, an external Redlib operator may
receive the query and the server's IP. No visitor cookie is forwarded. The form
and result notice disclose the fallback. Set `FOURGET_REDLIB_FALLBACKS=false` in
the existing deployment configuration to restrict news to the primary; the fixed
inventory is not a user-supplied URL list. Inventory membership is not a health
check. Live reachability must be checked from the VPS.

Binternet's modern/legacy markup compatibility, bounded continuations, explicit
empty/error states and real smaller-preview selection remain. Image search keeps
six layouts, preview/high/original quality choices, format filters, optional
infinite scrolling and normal Next page links. GIF/animated WebP/APNG previews
retain their poster while a separate motion layer loads: at most **two loading
and four active**, with Play/Pause, visibility/reduced-motion/Save-Data opt-outs,
fifteen-second deadlines and no automatic retry of a failed preview.

## Appearance and browser-local pictures

![Theme picker](docs/screenshots/securitysearch-0.9.21-theme-picker.png)

Pure black remains the default. Dark, Wine and The Birthday Massacre are removed
from selectors; old saved choices migrate to Black. The duplicate gentoo choice
is consolidated. All eighteen choices have a small local preview; palette-only
Lain and Stop are identified rather than pretending to have a missing wallpaper.
Native settings and image-filter selects use black backgrounds and the theme's
accent, including cyan on Tron and SecOps.

Tron restores its bundled motion in optimized animated WebP (about 527 KB).
Without the optional operator pack, SecOps uses the existing Matrix animation (about 1.37 MB) with a cyan palette;
it is **not** the deliberately removed historical SecOps artwork. Their still
switch and reduced-motion/data alternatives require no JavaScript. Browser
wallpaper loading occurs only for the selected theme.

**My picture** is an explicit opt-in. Choose it, save, and select a JPEG, PNG or
WebP up to 8 MiB. The input is outside all forms and has no field name. An optional
same-origin script reads/resizes/re-encodes it in the browser, bounded to 24
megapixels and a 1920-pixel longest output edge with a two-MiB data-URL limit.
No upload endpoint receives the picture, filename or EXIF. The default is this
tab's `sessionStorage` (browser session restore policies still apply). Remember
on this device explicitly opts into `localStorage`; Remove clears both. A shared
browser or same-origin script can access its browser storage; this is not an
encrypted vault. Without storage permission, the picture is page-local only.

**Works without JavaScript** is accurate for ordinary searches, links and bundled
themes. It does not mean zero JavaScript everywhere: image scrolling/animation and
My picture use optional local scripts. The Black homepage emits no script; Custom
permits its script while keeping CSP `connect-src 'none'` on that homepage.

## Footer and discoverability

The footer links the existing v3 onion address, with Tor indicated, and displays
small dated Tranco metadata on the right. No Tranco request is made while serving
a page or search. A separate explicit CLI/systemd task updates public domain
metadata daily. No confirmed rank ships in this release: it displays unavailable
until refresh, expires old cache after three days, and distinguishes an empty
ranking response. Tranco rank is not a security or quality rating. The onion
address format/checksum is validated; reachability is not asserted.

Homepage canonical/social metadata and Website microdata are updated, the social
card is 1200×630, and the sitemap link matches the existing route. Settings and
private search pages remain noindex. No fake ratings, provider availability or
search-ranking guarantees are added.

## Install/update and release

For the existing IONOS installation, use the matching **securitysearch-update-0.9.22-r2**
kit; the previous exact-hash launchers intentionally reject these changed files.

```fish
fish ~/Downloads/securitysearch-update-0.9.22-r2/apply-securitysearch.fish ~/securitysearch
fish ~/Downloads/securitysearch-update-0.9.22-r2/deploy-securitysearch.fish ~/securitysearch --rank-refresh
fish ~/Downloads/securitysearch-update-0.9.22-r2/publish-securitysearch.fish ~/securitysearch
```

These are three separate operations. The r2 repair accepts the clean exact applied v0.9.22 or v0.9.22-r1
main tree and creates a normal local commit. Deployment preserves SSH **5119**,
**root@securityops.co**, Docker networks and **172.17.0.1:5140 → 80**; unsupported
layouts are refused. The isolated full offline suite, candidate readiness and live
Binternet check must pass before cutover. Keep all printed backup/rollback paths.
The optional rank timer is installed only after successful deployment and never
rolls back the application merely because metadata is unavailable.

Publication creates/reuses an annotated **v0.9.22**, packages that exact real
commit, then preflights selected repositories before any write. Matching drafts
and assets resume; conflicting tags, notes and assets are preserved. Forgejo uses
multipart attachments. Tokens are privately prompted, not put into command
arguments, files or URLs. A single-host retry includes the release files:

```fish
fish ~/Downloads/securitysearch-update-0.9.22-r2/publish-securitysearch.fish ~/securitysearch --host git.securityops.co
```

Completed releases provide **securitysearch-v0.9.22.tar.gz** (tagged PHP source,
not prebuilt binaries/Docker) and **securitysearch-v0.9.22.tar.gz.sha256**. A local
incremental recovery bundle requires published v0.9.20. Publication never deploys.

[Codeberg](https://codeberg.org/berkeley/securitysearch/releases/tag/v0.9.22) ·
[GitHub](https://github.com/cristiancmoises/securitysearch/releases/tag/v0.9.22) ·
[SecurityOps .co](https://git.securityops.co/cristiancmoises/securitysearch/releases/tag/v0.9.22) ·
[SecurityOps .com.br](https://git.securityops.com.br/cristiancmoises/securitysearch/releases/tag/v0.9.22)

Links become available only after each host's publication succeeds. SHA-256 checks
integrity; an annotated tag is not automatically a cryptographic signature.

## Validation

```sh
sh scripts/test.sh
```

The full gate requires PHP curl/DOM/mbstring/APCu/Imagick/sodium, Python, Node,
Git and fish. The authoring environment could not run the complete native suite;
see [audit](docs/AUDIT-0.9.22.md) for exact passes and blockers. No live provider
speedup, VPS deployment, onion reachability or authenticated release upload is
inferred from mock tests. An optional real-browser suite is in
`tests/browser-experience.py`; browser navigation was policy-blocked during
authoring, separately from the successfully rendered local screenshots.

[Release notes](docs/RELEASE-0.9.22.md) · [Operations and publication](docs/OPERATIONS-0.9.22.md) ·
[Operação/publicação](docs/OPERATIONS-0.9.22.pt-BR.md) · [r1 repair retained](docs/AUDITFIX-0.9.20-r1.md)

License: [AGPL-3.0](license.txt). Existing search actions, service directory,
Invidious integration, API and **In Code We Trust.** footer are retained.
