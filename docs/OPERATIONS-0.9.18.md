# Security Search v0.9.18 — operations

This complete source update builds on v0.9.17 commit `b0dc22d`. Asset/config marker: **22**. It retains the PHP/Docker architecture, integrations, compact icon actions, navigation, media filters and default Tron theme.

## Infinite image scrolling

Image search now appends results as the visitor scrolls; Filmstrip loads from its horizontal container. Automatic pages, Play/Pause, interval fields, Refresh navigation and encrypted timer snapshots are removed. Old frame links return HTTP 410 without a search. The existing ordinary encrypted continuation tokens and their 15-minute expiry are unchanged.

Append-on-scroll and bounded visible animation use two small local JavaScript files. This implements the latest request as a narrow exception to the earlier no-JavaScript requirement. Only image search permits `script-src 'self'` and `connect-src 'self'`; inline event handlers and workers remain blocked. All other pages retain `script-src 'none'` and `connect-src 'none'`. If a reverse proxy adds another CSP, it must allow the image enhancement on `/images`; multiple CSP headers are enforced together. The updater does not modify NPM headers.

Native Next page works with scripts disabled or unsupported. Settings → Load more images while scrolling → No omits the pagination script. The separate Play visible GIF, WebP and APNG previews preference controls the motion script. Save-Data also keeps manual navigation. Google remains the default provider, with saved user/provider/theme choices respected. Tron uses the lightweight CSS background; the updater migrates the instance default to Tron while preserving explicit browser preferences.

One request is in flight at a time. Each page contains at most 24 images, each JSON response is limited to 1 MiB, and the client deadline is 25 seconds. Appended previews are lazy/async/low priority. Before another full page would exceed 480 retained cards, native continuation starts a fresh document. No earlier cards are silently discarded. Empty/final pages and failures stop automatic loading; a failed continuation offers a fresh search because the provider token may have been consumed. This does not remove an upstream rate limit.

The last card must approach the viewport and further scroll activity must occur after each completed page. Hidden tabs issue no new requests; a request already in progress can finish. Leaving during a request aborts it. There are no navigation timers, viewport movement or polling. Automatic fallback is limited to new Google web/image searches; a continuation never switches providers. See [UI behavior](UI.md) for exact bounds and fallbacks.

## Google recovery, animated media and Reddit

Google is still the default web/image provider. A failed initial HTML search gets one Brave fallback, visibly labeled with the actual provider. Google has up to 12 seconds of network time; Brave receives at most 8 seconds within a shared 20-second deadline. Existing Google cooldown and query-free bootstrap caches remain. Compatible filters survive; unsupported provider filters reset. Empty Google results do not trigger fallback. API requests, Google API selection and continuations keep their existing provider contract. Both providers failing still yields HTTP 503. No challenge solver, proxy evasion or repeated background retry is added.

Brave image search supplies one page only. Its format selector filters that returned page locally; a restrictive format may leave no matches. Result headers and image JSON identify Brave after recovery. The client rejects a continuation/provider mismatch.

GIF, animated WebP and APNG use the same-origin media proxy. The browser starts at most two loads and keeps at most four animations active, only in the viewport. Offscreen, hidden-tab, reduced-motion, Save-Data and Settings opt-out restore a still poster. New infinite-scroll cards register automatically. Failed animation loads return to their poster without automatic retry. Native View animation remains available for larger files. The automatic path caps files at 8 MiB, 500 frames, 4096 px per side, 8 megapixels and 64 million total frame pixels; the native viewer retains its separate larger limits. Static WebP remains static. Only sources recognized by URL/metadata as GIF/WebP/APNG are offered automatic playback; animated AVIF and video formats are not added.

The Redlib adapter requests only the fixed `https://libre.securityops.co` origin. Blank `/news` lists r/news + r/worldnews newest posts; keyword searches use the same communities and native sort/time filters. The upper News link remains external at `news.securityops.co`; Reddit opens `libre.securityops.co`. The adapter parses Redlib HTML using its [search route](https://github.com/redlib-org/redlib/blob/main/src/search.rs) and [post template](https://github.com/redlib-org/redlib/blob/main/templates/utils.html) contract. Transport keeps a 12-second deadline, 2 MiB body cap, HTTPS/DNS pinning/private-address rejection and no redirects. Pagination rebuilds a fixed path with validated cursor data; external returned links cannot become transport destinations.

**The live Redlib instance returned 503 during the 2026-09-09 check.** Offline route/parser tests passed, but live news remains dependent on upstream recovery. Deployment readiness checks local helpers/assets without requiring that unavailable external service.

## Privacy and retained state

HTML and JSON search responses use `private, no-store` and `Referrer-Policy: no-referrer`. The script fetches only the same origin; image media continues through the validated proxy. The enhancements use no storage, telemetry, external libraries or returned HTML injection. Only the existing Settings mechanism stores the user's preference cookie.

Ordinary pagination tokens are single-use capabilities; keep query URLs private. Two separate browser tabs can still race an ordinary continuation—the existing backend consume operation is not atomic across processes. The enhancer prevents in-page overlap and never retries an ambiguous token. Server restarts/eviction can invalidate continuations. There are no new result snapshots or timer storage quotas.

Bundled Apache access logs omit query strings and client IPs. Nginx Proxy Manager has its own logs: omit query strings there or disable that host's access logging. This package does not change proxy configuration or existing logs. The search operator and selected upstream still process queries.

## Deploy to the existing IONOS container

Host requirements: Linux root, Python 3, Docker daemon/CLI, curl, flock (usually util-linux), SSH on 5119, and enough disk/RAM for an image build plus a temporary candidate. The reported existing service is expected to be named `security-search` and bind exactly **172.17.0.1:5140 → 80**. For another container name, set `SECURITYSEARCH_CONTAINER` in the server environment when running the Python updater; no container ID is hardcoded.

Download the archive, checksum and fish file to `~/Downloads`, then:

```fish
fish ~/Downloads/deploy-securitysearch-v0.9.18.fish
```

An identical copy is included at `scripts/deploy-ionos.fish`. The script copies the archive/checksum to `root@securityops.co` using SSH port 5119, verifies both copies, extracts to a fresh directory and runs the updater. It uses your SSH authentication and normal host-key verification. It does not overwrite the old source directory.

The updater:

1. Acquires a host lock, checks tools, the existing live container, exact private bind, supported bridge networks and mounts.
2. Saves container inspection/effective PHP configuration to a root-only timestamped backup. Preserves settings/environment, existing mounts including anonymous volume identities, networks and aliases. Unmounted private data directories become retained read-only snapshot mounts.
3. Builds a new image while the old service runs. A candidate has no published ports, no production aliases and no restart policy. Readiness checks container health, asset 22, effective Tron/default stylesheet, footer/navigation, absence of homepage script markup, the homepage CSP, scoped image CSP and pagination/motion script assets and new local PHP helpers without contacting a provider.
4. Writes a locked manual rollback command, disables the old container's restart policy, stops/renames it, creates the replacement with the original binding/settings and checks readiness plus host HTTP.
5. Retains the old container with restart disabled. On failure, independently attempts cleanup, reconciles Docker names after lost responses, restores the old name/restart policy and starts it. Cleanup or Docker failures can still require the printed manual rollback command.

Candidate and live containers may share the favicon cache and explicit data mounts. Mounts masking application code, Apache/PHP/ImageMagick policy or key runtime binaries stop preflight; static container IPs and unsupported network modes also stop before cutover. Existing Docker capabilities, security options and limits are inherited. The updater does not retroactively apply every fresh-install Compose setting.

**Retain `/root/securitysearch-backups/<timestamp>`**: the replacement may mount its private-data copies. Retain the old image/container and external mounts until rollback is no longer needed. Backups contain sensitive configuration. The generated rollback script uses the same lock, verifies the expected replacement image, disables its restart, and restores the old policy/container. Host/daemon outages can require manual recovery.

NPM keeps upstream `http://172.17.0.1:5140`. The replacement is managed by this updater, not by the old Compose project. Do not run old `docker compose up/down` commands after cutover. Use the bundled Compose configuration only for a deliberately prepared fresh installation.

## Verification and limits

```sh
sh scripts/test.sh
python3 scripts/benchmark-http.py --url http://172.17.0.1:5140/ --requests 50 --concurrency 4
```

The first command uses offline provider fixtures and a temporary localhost PHP server. The second is an optional homepage measurement on the VPS; do not load-test upstream search providers.

After deployment, verify public HTTPS, the four search actions, each image view/quality, a neutral provider search, continuous image loading, animated/still previews, Google fallback, Reddit news, manual fallback and Settings. Check Redlib, Invidious and Binternet availability separately; successful video search does not prove YouTube playback. Scrolling and visual/browser behavior were not tested with a real browser in authoring. Docker build, PHP 8.4/Apache/ImageMagick 7 runtime, live mounts, actual rollback and VPS/provider performance remain deployment checks. See the [audit](AUDIT-0.9.18.md).
