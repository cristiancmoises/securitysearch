# Security Search v0.9.20

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![Security Search — pure black](docs/screenshots/securitysearch-0.9.20-home-black.png)

Local rendering of this release, not a screenshot of the deployed VPS. The HTTP controller was exercised locally; Chromium rendered its HTML with bundled resources embedded for the isolated preview. Asset version: **24**.

Security Search is a PHP search proxy based on [4get](https://git.lolcat.ca/lolcat/4get), maintained for [SecurityOps](https://securityops.co/). Search providers remain external services: a working adapter cannot guarantee their availability.

## This update

Binternet accepts the older `img-container`/`img-result` markup and the newer `image-gallery`/`image-link` layout. Both root-relative and page-relative proxy/pagination links work. Query validation now follows the newer service's 160-byte UTF-8 limit rather than measuring 64 HTML-escaped bytes. Bookmarks up to 4096 bytes are supported; foreign hosts, unsafe URLs and repeated pagination cursors are rejected. Recognizable empty results are distinct from broken/blocked pages.

Fast previews use the smaller image actually returned by Binternet, while preserving the original URL and dimensions. No larger thumbnail URL is fabricated. The fixed service origin remains `https://images.securityops.co`; this release does not guess that the separate navigation host `img.securityops.co` is interchangeable.

Legacy cURL calls now share a request-local deadline: by default, 3 seconds to connect, 12 seconds per call and 20 seconds across those calls. DNS and TLS-session state are shared within one PHP request, never cookies or search results. Baidu's multi-request loop waits instead of spinning continuously. Google CSE and Brave retain their separate bounded transports and the existing first-search fallback policy. These are bounded waits and local optimizations, not measured claims about live provider speed.

Pure black is the new instance default. **Choose appearance** restores native, keyboard-accessible theme selection with small previews of existing image assets. Saved browser themes still win over the default. The homepage remains JavaScript-free; wallpaper is loaded only for the selected image theme. The twelve new still previews total about 45 KB.

![Native theme picker](docs/screenshots/securitysearch-0.9.20-theme-picker.png)

## Existing behavior retained

The four search actions remain inside the search bar: Search, Search Image, Search Pinterest and Search YouTube. Image search retains six layouts, preview/high/original quality choices, format filters, manual pagination, optional infinite scrolling and bounded animated previews. Only image search permits the two local enhancement scripts. The services disclosure, API routes, native links and **In Code We Trust.** footer remain.

Google stays the default web/image provider. A failed first Google search can try Brave once with a visible notice; continuation requests do not silently change provider. Reddit uses the configured Redlib service and YouTube uses Invidious. Their availability has not been established by this patch.

## Apply, deploy and publish

Use the `securitysearch-update-0.9.20` patch kit, not the old v0.9.19 publication launcher:

```fish
fish ./apply-securitysearch.fish ~/securitysearch
fish ./deploy-securitysearch.fish ~/securitysearch
fish ./push-securitysearch.fish ~/securitysearch
```

The first command requires a clean `main`, verifies every touched baseline file, applies only the patch and creates one local commit with your configured Git identity. It never resets, stashes or overwrites unrelated work. Deployment archives that verified commit and uploads over SSH **5119** to **root@securityops.co**. The updater retains the existing **172.17.0.1:5140 → 80** binding and Docker networks; it refuses unsupported mount/port layouts instead of changing Nginx Proxy Manager.

Before cutover, deployment must pass the full offline suite in a disposable, network-disabled audit container, candidate readiness, and a real neutral Binternet search from the VPS. Available Binternet pagination is checked too. Failed pre-cutover gates leave production running. A failed replacement readiness restores the retained old container. The printed backup directory may hold mounted private-data snapshots: do not prune it.

Publishing prompts privately for four separate tokens and preflights all four HTTPS repositories. Only fast-forward `main` pushes are allowed. Existing remote configuration, tags and release objects are untouched. Separate servers cannot be updated atomically; partial publication is reported and can be reconciled by rerunning.

[Operation details](docs/UPDATE-0.9.20.md) · [Detalhes em português](docs/UPDATE-0.9.20.pt-BR.md) · [Audit limitations](docs/AUDIT-0.9.20.md)

## Tests

```sh
sh scripts/test.sh
```

Requires PHP with curl, DOM/XML, mbstring, APCu, sodium, fileinfo and Imagick, plus Python 3, Node.js, Git and fish for tests. The production image does not gain Python/Node/fish: those dependencies are installed only in its disposable audit derivative.

For a separate, explicit live provider matrix after deployment, run `fish ./audit-providers.fish` from the patch kit. It makes one neutral `teste` search per enabled provider/page combination, sequentially. An unavailable or empty provider is not counted as a successful search. Results, credentials and pagination tokens are not dumped into the JSON report.

Local authoring did **not** execute Docker, the extension-dependent PHP suites, or live VPS/provider requests. See the audit report for passes versus dependency blockers. Historical release tools and documents remain under `scripts/` and `docs/` for v0.9.19; they are not the v0.9.20 publishing path.

License: [AGPL-3.0](license.txt).
