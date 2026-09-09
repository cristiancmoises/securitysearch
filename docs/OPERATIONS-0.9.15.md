# Security Search v0.9.15 — operations

This release extends v0.9.14 from Codeberg baseline `0751f145ff7b68d1f9c070c8bd8af79cd7bff717`. It includes the entire application, Docker files, tests and deployment updater. Asset/config marker: **19**.

## What changes

The application serves no browser JavaScript; CSP explicitly sets `script-src`, `worker-src` and `connect-src` to `none`. Native forms place Search Image, Search Pinterest and Search YouTube underneath Search. The below-bar destination row, client lightbox, automatic image append, image retry/motion scripts and live instance pings are removed. Settings no longer offers the two obsolete JavaScript-only image toggles. Original, Preview and View animation links work through the media proxy.

Google remains the default for web/images, and saved provider choices still apply. The new provider recovery card offers deliberate alternate-provider searches, retains compatible options, strips continuation/timer state and returns HTTP 503 plus Retry-After 30. It does not promise to remove upstream blocking. [Google documents network-level restrictions on automated requests](https://support.google.com/websearch/answer/86640?hl=en).

Invidious and Binternet integrations, six image layouts, high quality up to 1280 px, provider-specific GIF/WebP and other format options, requested navigation and “In Code We Trust.” remain. Binternet format filtering checks URL extensions only within each returned page, and Safe Search is controlled by that instance. Media MIME/size validation can reject formats that a provider's filter lists.

## No-JavaScript automatic pages

Automatic pages is an explicit alternative to the requested infinite scrolling. It **replaces result pages; it does not move the viewport or append results**. Play next pages loads the next provider page immediately, then follows timed page links. Choose 10–120 seconds, default 30. Pause keeps current results; Play resumes; manual Next while paused stays paused. At most ten automatic/claimed advances follow the initial Play click. More results require manual continuation or a new session.

Each page renders at most 24 results. Larger provider pages disclose the omitted count; those additional entries are not displayed in this release. Errors, empty/final pages, expired state and storage refusal stop automatic mode. An empty filtered page still offers manual Next where available. Pause/resume avoids repeating provider searches, although media previews may download again.

Refresh timing begins after loading and varies by browser; browsers can suppress it. Background tabs can advance, so Pause before leaving a tab. This behavior and history replacement follow the [HTML refresh model](https://html.spec.whatwg.org/multipage/semantics.html#attr-meta-http-equiv-refresh). It is not a precise scrolling timer.

## Snapshot privacy and limits

Normal searches do not create result snapshots. After Play, results/query/filter state are encrypted in APCu with a random key carried only in the page's URL capability. Frames expire after **600 seconds**; reading/pausing/resuming does not renew that frame. New successful pages get new frames. Existing single-use backend continuation tokens and their 15-minute expiry are unchanged.

Limits: 128 KiB per serialized frame, 64 global slots (at most about 8 MiB of encrypted payload plus metadata), 20 frame creations per client address per ten-minute window, and ten advances per session. A reverse proxy that presents one shared client address may cause visitors to share that admission budget. Storage refusal retains manual navigation. Transition claims use atomic APCu add so a timer/manual race does not issue duplicate provider requests. Restarts/eviction can invalidate controls; they fail closed without a new provider request.

Frame URLs contain a decryption capability: keep them private. HTML responses use `private, no-store` and `Referrer-Policy: no-referrer`. Bundled Apache access logs omit query strings and client IPs. **Nginx Proxy Manager has separate logging**: configure that host's logging to omit query strings or disable access logging, and do not paste frame URLs into public logs/issues. This package does not alter NPM or existing logs.

## Deploy to the existing IONOS container

Host requirements: Linux root, Python 3, Docker daemon/CLI, curl, flock (usually util-linux), SSH on 5119, and enough disk/RAM for an image build plus a temporary candidate. The reported existing service is expected to be named `security-search` and bind exactly **172.17.0.1:5140 → 80**. For another container name, set `SECURITYSEARCH_CONTAINER` in the server environment when running the Python updater; no container ID is hardcoded.

Download the archive, checksum and fish file to `~/Downloads`, then:

```fish
fish ~/Downloads/deploy-securitysearch-v0.9.15.fish
```

An identical copy is included at `scripts/deploy-ionos.fish`. The script copies the archive/checksum to `root@securityops.co` using SSH port 5119, verifies both copies, extracts to a fresh directory and runs the updater. It uses your SSH authentication and normal host-key verification. It does not overwrite the old source directory.

The updater:

1. Acquires a host lock, checks tools, the existing live container, exact private bind, supported bridge networks and mounts.
2. Saves container inspection/effective PHP configuration to a root-only timestamped backup. Preserves settings/environment, existing mounts including anonymous volume identities, networks and aliases. Unmounted private data directories become retained read-only snapshot mounts.
3. Builds a new image while the old service runs. A candidate has no published ports, no production aliases and no restart policy. Readiness checks container health, asset 19, footer/navigation, absence of script markup, the no-JavaScript CSP and local encrypted-page capability.
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

After deployment, verify public HTTPS, the four search actions, each image view/quality, a neutral provider search, Pause/Play and Settings. Check Invidious and Binternet availability separately; successful video search does not prove YouTube playback. Refresh and visual/browser behavior were not tested with a real browser in authoring. Docker build, PHP 8.4/Apache/ImageMagick 7 runtime, live mounts, actual rollback and VPS/provider performance remain deployment checks. See the [audit](AUDIT-0.9.15.md).
