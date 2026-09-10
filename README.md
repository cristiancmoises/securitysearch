# Security Search v0.9.23

[English](README.md) · [Português do Brasil](README.pt-BR.md)

PHP search proxy maintained for [SecurityOps](https://securityops.co/), based on
[4get](https://git.lolcat.ca/lolcat/4get). Application **0.9.23**, asset marker **27**.
External providers can fail or rate-limit requests; this release does not promise
universal availability or measured all-provider speedups.

## News: verify the instance, not just its homepage

libre.securityops.co is no longer the active news default. The compiled first
choice is **redlib.privacyredirect.com**, with **redlib.nadeko.net** and
**redlib.privadency.com** as fixed sequential alternatives. They are in the official
Redlib inventory; membership is not evidence of working search.

The guarded deployer tests the actual feed AND a neutral keyword search on the
VPS candidate before cutover. The same instance must return nonempty parsed news
for both. Its URL becomes the replacement's effective **FOURGET_REDLIB_PRIMARY**.
If no candidate qualifies, the existing service stays running and the backup
contains `redlib-live.json`. This was not live-verified from the authoring environment.

Runtime fallbacks remain sequential, up to three bounded attempts. Queries are
not broadcast in parallel. Pagination stays with the responding origin; retired
origin continuations must be restarted. The displayed origin and direct Reddit
link follow the configured primary. An external Redlib operator receives the
server IP and query; visitor cookies are not forwarded. Only the initial public,
query-free feed can be cached, not keyword searches. **FOURGET_REDLIB_FALLBACKS=false**
retains the selected primary but disables visitor-request fallbacks.

## My picture: device-only selection that is visible and usable

![Local picture editor](docs/screenshots/securitysearch-0.9.23-local-picture-desktop.png)

**Local browser fixture, not a VPS screenshot.** Actual PHP HTML and bundled
resources were embedded for the managed authoring browser; the picture is a test
pattern. FileChooser, FileReader, decoding, canvas and CSS display were exercised.
Direct browser navigation was policy-blocked; no native navigation pass is claimed.

Click **Use a picture from this device**, then **Choose File**. The editor opens
before the theme grid and applies the file automatically. Settings also displays
the editor for My picture. The Settings response now recalculates its CSP after
the theme preference, fixing a local-script/`script-src 'none'` mismatch.

JPEG, PNG, WebP and GIF are recognized from bytes even with an empty OS MIME label.
Animated input becomes one still. Input <=16 MiB, <=48 megapixels after browser
decode, output <=1920px and <=2 MiB normalized data URL. HEIC/HEIF and SVG are not
supported. The browser may allocate decode memory before the dimension check.

The unnamed file input is outside all forms. The controller has no upload, fetch,
beacon, WebSocket or cookie write. It re-encodes locally; no filename or EXIF is
transmitted. Default storage is this tab's sessionStorage (browser session restore
can restore it). **Remember on this device** explicitly opts into localStorage.
**Remove my picture** clears both when permitted; failures are reported. Storage
blocked/full? The picture still displays on this page with an explanation. This
is not encrypted storage; same-origin scripts and shared-device users can read it.

Without JavaScript, the chooser is disabled with instructions. **Works without
JavaScript** applies to ordinary searches and bundled themes, not this optional
local-picture feature or optional image scrolling/animation. The Black homepage
still emits no executable script; Custom keeps `connect-src 'none'` on its homepage.

## Existing features retained

Google/Brave bounded search behavior, Binternet legacy/modern parsing, six image
layouts, actual smaller previews, manual pagination, optional infinite scrolling,
and poster-preserving GIF/WebP/APNG playback remain. Motion keeps two loading/four
active limits, pause controls and reduced-motion/Save-Data handling. Pure black,
native theme previews, black/accent-color selects, onion link, cached Tranco footer,
private-query noindex and honest metadata remain.

Historical Tron stays bundled. Lain/SecOps private historical artwork remains in
an **external operator pack**, never in new Git commits, source releases or their
derivatives on any forge including Codeberg. Pass --theme-assets during private
VPS deployment; without it use palette/Matrix alternatives. Existing old history
is not purged. The earlier r1/r2 import/archive fixes and all audit gates remain.

## Apply, deploy, publish

Use the complete matching **securitysearch-update-0.9.23** kit, not an old manifest.
See [English operations](docs/OPERATIONS-0.9.23.md) and
[Portuguese operations](docs/OPERATIONS-0.9.23.pt-BR.md) for verification and commands.
The kit supports clean exact v0.9.22-r2/r1/original main trees. It creates a normal
commit; dirty or unexpected work, existing conflicting tags and assets are preserved.

```fish
fish ~/Downloads/securitysearch-update-0.9.23/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.23/deploy-securitysearch.fish \
    ~/securitysearch --theme-assets ~/.local/share/securitysearch/operator-themes-v1 --rank-refresh
```

Deployment retains SSH **5119**, **root@securityops.co**, Docker networks and
**172.17.0.1:5140 -> 80**, without reconfiguring NPM. The full isolated offline suite,
candidate readiness, new Redlib feed+search gate and existing live Binternet gate
must pass before cutover. Keep printed backups and rollback paths.

After successful deployment:

```fish
fish ~/Downloads/securitysearch-update-0.9.23/publish-securitysearch.fish ~/securitysearch
```

Or retry only the previously failing host, including release files:

```fish
fish ~/Downloads/securitysearch-update-0.9.23/publish-securitysearch.fish \
    ~/securitysearch --host git.securityops.co
```

Each completed release contains **securitysearch-v0.9.23.tar.gz** and its
**.tar.gz.sha256**, generated from the real annotated tag. This is PHP source,
not a Docker image. Local artifacts are under `~/securitysearch-release-v0.9.23/`.
The incremental bundle requires published v0.9.20. Tokens are privately prompted;
matching drafts resume, conflicting assets/tags are not replaced. SHA-256 is an
integrity check, and an annotated tag is not automatically a signed tag.

[Codeberg release](https://codeberg.org/berkeley/securitysearch/releases/tag/v0.9.23) ·
[GitHub release](https://github.com/cristiancmoises/securitysearch/releases/tag/v0.9.23) ·
[SecurityOps .co](https://git.securityops.co/cristiancmoises/securitysearch/releases/tag/v0.9.23) ·
[SecurityOps .com.br](https://git.securityops.com.br/cristiancmoises/securitysearch/releases/tag/v0.9.23)

Each link works only after its host's publication succeeds. Publication never deploys.

## Validation

```sh
sh scripts/test.sh --keep-going
```

All **47** commands are mandatory; a nonzero exit still blocks deployment.
PHP curl/DOM/mbstring/APCu/Imagick/sodium, Python, Node, Git and fish are required
for the full suite. See [audit and limitations](docs/AUDIT-0.9.23.md); incomplete
native dependencies are not counted as passing tests. Browser fixtures, mock
Docker/API operations and real PHP HTTP tests are reported separately. No VPS
push/deployment or external-provider success is claimed during kit preparation.

[Release notes](docs/RELEASE-0.9.23.md) · [License: AGPL-3.0](license.txt).
**In Code We Trust.**
