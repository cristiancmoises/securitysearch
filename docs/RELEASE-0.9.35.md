# SecuritySearch v0.9.35 / asset 39

Development checkpoint toward 1.0.0. This release keeps the search providers and deployment
acceptance policy intact. It does not establish a global speed ranking.

## Request-path changes

- Relay exact bytes of strictly eligible already-small opaque PNG thumbnails: 32 KiB,
  236 × 180, RGB/grayscale 8-bit, non-interlaced and no ancillary metadata or animation.
- Check CRCs, chunk order/lengths, bounded inflated scanlines/filter types and one complete
  zlib stream. Other PNG features use the existing bounded ImageMagick conversion path.
- Require HTTP 200 for fetched thumbnail/animation payloads before rendering. Upstream
  error images no longer appear as successful previews or consume conversion work.
- Preserve explicit poster/high/other sizing, original streaming, animation controls,
  proxy/DNS/TLS restrictions, query privacy and no-store error responses.

## Operations

Cumulative updates from exact corrected 0.9.30, 0.9.31, 0.9.32, 0.9.33 and 0.9.34 trees.
Self-contained Fish deployment and publication are separate and all downloads go in
~/Downloads. Pre-build cleanup stays project-scoped under the deployment lock, protecting
production, the newest rollback, referenced/shared/recent/unknown images and all backups,
volumes, networks, build cache and NPM. Native audit, RSS/Binternet and direct Google
Web/Images remain mandatory. No 429 is accepted as a working provider.

Source-only files are verified on the retained VPS host source, runtime files inside the
active image. Source/tag/tar.gz agreement, private artwork exclusion, hidden token entry,
per-forge publication retry and immutable prior tags remain.
