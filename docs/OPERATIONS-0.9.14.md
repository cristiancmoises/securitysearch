# Security Search v0.9.14

Built from Codeberg source `0751f145ff7b68d1f9c070c8bd8af79cd7bff717`.
This is a complete source update for the reported v0.9.12-r2 Docker deployment.
The source already contained v0.9.13 improvements; those are retained.

## Search and presentation

- YouTube via Invidious is the first video provider. Existing saved choices still win. Results appear inside Security Search; video and thumbnail links use `invidious.securityops.co`. This is search integration, not an embedded video player.
- Pinterest via Binternet is selectable in image search and Settings. It searches `images.securityops.co/search.php`, parses recognizable result markup and handles continuation links. The home search has separate Images, Pinterest and YouTube submit buttons.
- Binternet supplies original Pinterest CDN images. Media is fetched through Security Search's validating proxy; opening a result uses Binternet. This does not make Pinterest an independent index.
- Binternet has no native format filter. “Format on this page” filters returned URL extensions and can yield few or no matches; the normal Next link remains available. Its effective query limit is 64 bytes after HTML escaping. It does not expose a Safe Search control through this adapter.
- Google image filters retain GIF, WebP, JPEG, PNG, BMP, SVG, ICO and RAW; AVIF/APNG options were added. Upstream file-type hints do not guarantee actual MIME or animation. The proxy retains its raster allowlist; SVG/RAW/ICO need not render as previews.
- Six views remain: Grid, Compact grid, Gallery, Large feed, List and Filmstrip. Fast preview remains the default. High quality requests a bounded, uncropped proxy rendition up to 1280×1280, without upscaling. Original remains explicit. Larger originals may exceed the media safety limits and fall back to a small preview.
- Animated GIF/WebP/APNG, reduced-motion/data controls, lazy loading and bounded pagination remain. A WebP extension alone never proves animation.
- The deleted `lain.gifv` is not restored. Lain now has a static black/cyan background, with no missing-file request or wallpaper download. Saved themes are retained.
- Navigation: Home, Settings, Vids, Img, Wiki, Chat, Zupt, News, BR. `Img` deliberately uses `img.securityops.co`; the requested Binternet integration deliberately uses `images.securityops.co`. Git UI links use `git.securityops.co/`. The centered footer reads “In Code We Trust.”

## Performance and privacy limits

New service requests share a 12-second transport deadline, a 2 MiB body/wire budget, public-address validation and DNS pinning. Redirects are refused before another destination is fetched. Queries/results are not stored in a shared result cache; pagination state uses the existing encrypted 15-minute continuation mechanism. Provider outages return explicit errors without switching providers.

High previews fetch at most 16 MiB before conversion, under the existing 40 MP and ImageMagick CPU/memory limits. Original-image streaming now caps decoded transfer at 64 MiB; audio at 128 MiB; redirects share that budget. Unknown-length oversized transfers end incomplete at the bound. Favicon lookup stages share 8 seconds and 2 MiB, return generic diagnostics, and validate small fallback PNGs before caching.

Apache access logs now retain timestamp, method, URL path (without query), status, bytes and duration. Client addresses, query strings, Referrer and User-Agent are omitted. Nginx Proxy Manager is a separate logging boundary: this package does not change its configuration.

## Deploy on IONOS

Requirements: root, Python 3, Docker daemon/CLI, curl, sufficient disk/memory to build while the old service is running, and access to the pinned Alpine image/packages. No Docker daemon is available in the authoring environment, so the actual image build and live replacement remain deployment-time gates.

Use the supplied fish command/file after downloading the archive and checksum to `~/Downloads`. It uploads over SSH port 5119, validates SHA-256 and extracts into a fresh directory; it does not overlay the running source tree. Then it executes `scripts/deploy-ionos.py`.

The updater:

1. Locks against concurrent updates; verifies the existing `security-search` container and exactly the reported `172.17.0.1:5140 → 80` binding.
2. Records the old container/configuration in a root-only timestamped backup. Preserves effective PHP settings, environment, mounts (including anonymous volume names), networks and aliases; copies unmounted private data directories into retained read-only mounts.
3. Builds the image while production continues. Starts a candidate with no published ports and no production aliases, then checks health, asset version 18, footer and Zupt link.
4. Stops/renames the old container, creates the replacement with the original binding, validates readiness and checks HTTP through `172.17.0.1:5140`.
5. Attempts restoration of the old container on failure. A recovery command is written before stopping production. Keep the previous image/container until public and provider checks pass.

Source/configuration mounts masking application code, explicit static container IPs, nonstandard network modes, or a mismatched published port stop the updater before cutover. Read the diagnostic and adapt the deployment deliberately. Candidate and live containers may share the favicon cache. No database migration is performed.

**Retain `/root/securitysearch-backups/<timestamp>`:** the replacement may mount private data from this directory. It contains sensitive configuration and must not be published. Docker renames preserve the old container's writable layer. Rollback cannot survive deletion of the old container/image or its original external mounts.

The replacement is managed by the supplied updater, not the old Compose project. Do not run the old source directory's `docker compose up/down` after this cutover. Keep its files for recovery. The source Compose file remains available for fresh installations.

NPM keeps `http://172.17.0.1:5140`. Verify public HTTPS at `https://securityops.co/`, then a real Google image search, Invidious search and Binternet search from the UI. Search success does not prove YouTube playback works.

The printed manual rollback script is conditional and uses the retained old container ID. It can recover when the replacement name is absent. Hard termination of the Docker daemon/host or the updater may require this command; no script can guarantee automatic recovery after a host outage.

## Reproduce checks

```sh
sh scripts/test.sh
python3 scripts/benchmark-http.py --url http://172.17.0.1:5140/ --requests 50 --concurrency 4
```

The PHP suites require curl, mbstring, DOM/XML, APCu and sodium; media deployment uses ImageMagick 7 from the Docker image. Benchmarks describe their exact scope. Use homepage requests for load testing, not repeated upstream searches.

See [audit and validation](AUDIT-0.9.14.md). No remote Git push or VPS deployment was performed during preparation.

## Provider references

- [Invidious API](https://docs.invidious.io/api/)
- [Binternet search implementation](https://github.com/Ahwxorg/Binternet/blob/main/search.php)
- [PHP filter flags](https://www.php.net/manual/en/filter.constants.php)
