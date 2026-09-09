# Security Search v0.9.17 — operations

This complete source update builds on v0.9.16 commit `62cecd5`. Asset/config marker: **21**. It retains the PHP/Docker architecture, integrations, compact icon actions, navigation, media filters and default Tron theme.

## Infinite image scrolling

Image search now appends results as the visitor scrolls; Filmstrip loads from its horizontal container. Automatic pages, Play/Pause, interval fields, Refresh navigation and encrypted timer snapshots are removed. Old frame links return HTTP 410 without a search. The existing ordinary encrypted continuation tokens and their 15-minute expiry are unchanged.

True append-on-scroll uses a small local JavaScript file. This implements the latest request as a narrow exception to the earlier no-JavaScript requirement. Only image search permits `script-src 'self'` and `connect-src 'self'`; inline event handlers and workers remain blocked. All other pages retain `script-src 'none'` and `connect-src 'none'`. If a reverse proxy adds another CSP, it must allow the image enhancement on `/images`; multiple CSP headers are enforced together. The updater does not modify NPM headers.

Native Next page works with scripts disabled or unsupported. Settings → Load more images while scrolling → No omits the script. Save-Data also keeps manual navigation. Google remains the default provider, with saved user/provider/theme choices respected. Tron uses the lightweight CSS background; the updater migrates the instance default to Tron while preserving explicit browser preferences.

One request is in flight at a time. Each page contains at most 24 images, each JSON response is limited to 1 MiB, and the client deadline is 25 seconds. Appended previews are lazy/async/low priority. Before another full page would exceed 480 retained cards, native continuation starts a fresh document. No earlier cards are silently discarded. Empty/final pages and failures stop automatic loading; a failed continuation offers a fresh search because the provider token may have been consumed. This does not remove an upstream rate limit.

The last card must approach the viewport and further scroll activity must occur after each completed page. Hidden tabs issue no new requests; a request already in progress can finish. Leaving during a request aborts it. There are no navigation timers, viewport movement, polling or automatic provider switching. See [UI behavior](UI.md) for exact bounds and fallbacks.

## Privacy and retained state

HTML and JSON search responses use `private, no-store` and `Referrer-Policy: no-referrer`. The script fetches only the same origin; image media continues through the validated proxy. It uses no storage, telemetry, external libraries or returned HTML injection. Only the existing Settings mechanism stores the user's preference cookie.

Ordinary pagination tokens are single-use capabilities; keep query URLs private. Two separate browser tabs can still race an ordinary continuation—the existing backend consume operation is not atomic across processes. The enhancer prevents in-page overlap and never retries an ambiguous token. Server restarts/eviction can invalidate continuations. There are no new result snapshots or timer storage quotas.

Bundled Apache access logs omit query strings and client IPs. Nginx Proxy Manager has its own logs: omit query strings there or disable that host's access logging. This package does not change proxy configuration or existing logs. The search operator and selected upstream still process queries.

## Deploy to the existing IONOS container

Host requirements: Linux root, Python 3, Docker daemon/CLI, curl, flock (usually util-linux), SSH on 5119, and enough disk/RAM for an image build plus a temporary candidate. The reported existing service is expected to be named `security-search` and bind exactly **172.17.0.1:5140 → 80**. For another container name, set `SECURITYSEARCH_CONTAINER` in the server environment when running the Python updater; no container ID is hardcoded.

Download the archive, checksum and fish file to `~/Downloads`, then:

```fish
fish ~/Downloads/deploy-securitysearch-v0.9.17.fish
```

An identical copy is included at `scripts/deploy-ionos.fish`. The script copies the archive/checksum to `root@securityops.co` using SSH port 5119, verifies both copies, extracts to a fresh directory and runs the updater. It uses your SSH authentication and normal host-key verification. It does not overwrite the old source directory.

The updater:

1. Acquires a host lock, checks tools, the existing live container, exact private bind, supported bridge networks and mounts.
2. Saves container inspection/effective PHP configuration to a root-only timestamped backup. Preserves settings/environment, existing mounts including anonymous volume identities, networks and aliases. Unmounted private data directories become retained read-only snapshot mounts.
3. Builds a new image while the old service runs. A candidate has no published ports, no production aliases and no restart policy. Readiness checks container health, asset 21, effective Tron/default stylesheet, footer/navigation, absence of homepage script markup, the homepage CSP, scoped image CSP and pagination script asset.
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

After deployment, verify public HTTPS, the four search actions, each image view/quality, a neutral provider search, continuous image loading, manual fallback and Settings. Check Invidious and Binternet availability separately; successful video search does not prove YouTube playback. Scrolling and visual/browser behavior were not tested with a real browser in authoring. Docker build, PHP 8.4/Apache/ImageMagick 7 runtime, live mounts, actual rollback and VPS/provider performance remain deployment checks. See the [audit](AUDIT-0.9.17.md).
