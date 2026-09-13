# Changelog

## 0.9.42

- Add DeviantArt via the fixed SkunkyArt API, native toolbar shortcut, bounded metadata,
  signed media handling, orientation/AI-label/Safe Search filters and continuation pages.
- Normal deployment is one native audit followed automatically by all real provider gates,
  guarded promotion, independent verification and scoped post-success retention.
- Remove automatic pre-build cleanup. Retire old stopped rollback containers only after
  success; never stop running services. Remove only owned unused images and recognized old
  upload gzip/checksum pairs. Keep private backups, extracted sources, volumes, networks and NPM.
- Preserve one-pass native acceptance and add a SkunkyArt live-result receipt verified independently.
- Archive uploads mask group write permissions at creation instead of requiring later chmod repairs.
- Keep Google Web/Images acceptance and rate-limit failures mandatory. No upstream restriction bypass.
- Add six audit suites; historical 124-command prefix preserved, current inventory 130.


## 0.9.41 — delivery correctness and bounded rendering

- Bound provider image titles before escaping; reuse escaped labels; retain original links and preview choices.
- Replace filmstrip scroll-time geometry reads with asynchronous viewport observation.
- Deny encoded internal homepage aliases; correct error cache/encoding headers and range representations.
- Retain privacy-filtered Google failure traces without new requests or relaxed acceptance.
- Add a manual matched-round TTFB benchmark; retain complete HTML timing and the unchanged v3 tool.
- Preserve all 119 old audit commands and append five suites (124 required); add release41 publication tests.
- Update current EN/PT-BR documentation and keep historical records explicitly historical.
- No deployed/public benchmark win, production acceptance, or upstream rate-limit resolution is claimed.

# SecuritySearch v0.9.40 — direct v0.9.30 upgrade

## 0.9.40 — native-audit repair r2

Fix a reproduced cleanup failure when the native fixture leaves generated configuration
larger than the 1 MiB source-capture bound. Compare at most original length plus one byte,
restore exact bytes/mode, and propagate the original fixture exception. Keep the original
capture limit, linked/replaced-file refusal, all 119 commands and native/live acceptance.
Add ten regression cases, including sparse output, short reads and exception preservation.
No visitor-path changes or evidence of an oversized IONOS configuration are claimed.
See [r2 details](docs/AUDITFIX-0.9.40-r2.md).

## 0.9.40 — native-audit repair r1

Restore source configuration after the real native homepage fixture; isolate favicon HTTP
cases by host without disabling APCu. Keep the 119-command inventory, runtime hash manifests,
production code and deployment/publication gates unchanged. See
[the repair notes](docs/AUDITFIX-0.9.40-r1.md) for the 115/4 failure and validation scope.


Date: 2026-09-12. Application identifier 0.9.40; asset identifier 40.
Implemented deployment candidate, not an assertion of IONOS acceptance or publication.
No v0.9.39 installation/tag is needed; its unpublished search-state work is included here.

## Application corrections

- Preserve literal queries `0`, `all`, `any` and zero-valued filters in generated navigation,
  pagination and recovery links. Keep absent/default-filter omission semantics.
- Encode continuation values independently, preserving valid backend tokens as one parameter.
- Use normalized search input for web oracles and empty-result text.
- Start music output buffering before output/provider handling, retaining error status and
  cache/retry headers. This uses response-buffer memory and is not a latency optimization.

## Cumulative changes since the deployed v0.9.30

Includes retained v0.9.31–v0.9.38 static-delivery/favicon improvements, bounded image proxy/PNG
validation, usable preview selection, compact integrity-checked operator resources, complete
ordered audit validation and bounded audit-only execution. Current r2 diagnostic behavior is
preserved with release-specific inventory/identity. No new query caches, provider fanout,
JavaScript dependencies or altered theme artwork are introduced by the current search-state work.

## Deployment and publication

Three self-contained Fish launchers cover deployment/preparation/audit, diagnostics and
independent four-forge publication. New `--prepare-only` applies and commits locally without
SSH, themes, builds, tags or pushes. Audit/deploy still prepare automatically when necessary.
The exact observed v0.9.30 tree upgrades directly; eight later retained baselines are also supported.
Current README/README.pt-BR, operations, publishing, migration, audit, performance,
troubleshooting and documentation-index files are updated; older versioned records stay historical.

119 ordered native commands must pass with complete execution evidence, followed during normal
deployment by genuine Google Web and Images/RSS/Binternet/image gates. Publisher independently
checks source, runtime, image and retained evidence. Locks, drift checks, rollback protection,
private-media exclusions, strict host-key checking and immutable tags remain mandatory.
Four-host publication can be partial on failure; conflicting tags/assets are never overwritten.

## PT-BR

Atualização direta da v0.9.30 para v0.9.40, sem instalar versões intermediárias.
Consultas/filtros com zero e os termos all/any são preservados; a continuação é codificada;
os oráculos recebem a consulta normalizada; falhas de música mantêm HTTP 503 e cabeçalhos.
`--prepare-only` cria apenas o commit local. Documentação atualizada em inglês/PT-BR.
Assets 40; auditoria obrigatória 119/0 e provedores reais antes do deploy/publicação.

# v0.9.38

Require complete audit evidence; bound capture; add audit-only mode. Asset 40 and visitor
code remain unchanged. Native acceptance remains mandatory.

# Changelog

## 0.9.37 — lossless operator compaction and HTTP audit diagnostics

- Deduplicate cumulative patch file sections with bounded schema-2 chunk/file integrity checks.
- Preserve all earlier exact baselines and add the v0.9.36 tree; no increased launcher limits.
- Diagnose missing PHP modules before spawning controller fixtures; keep failures nonzero.
- Separate native error logs from server stderr; report fixed-route unexpected HTTP status.
- Preserve visitor implementation, asset version 40, benchmark, privacy and deployment guards.
- Append four mandatory commands: 110 total. Native/live acceptance remains required.
- Existing v0.9.36 screenshots remain explicitly historical; no new UI claim.

## 0.9.36 — useful previews and bounded source selection

- Do not let a 1 × 1 or tiny strip placeholder displace a usable supplied preview.
- Prefer the smallest adequately sized preview, then unknown-size hints, then the largest
  non-tiny undersized preview; use the original only when no useful preview remains.
- Inspect at most 32 raw source entries per image; preserve the 24-card cap and pagination.
- Preserve original/high-quality links and separate animated poster/motion handling.
- Keep PNG validation, provider transport, cleanup and rollback bytes unchanged.
- Add four required audit commands (106 total) and cumulative operators from six baselines.

## 0.9.35 — bounded PNG thumbnail relay and error-response handling

- Preserve exact opaque PNG thumbnail bytes when already within 236 × 180 pixels and
  32 KiB; reject unsupported metadata/alpha/animation to the existing conversion path.
- Validate chunks/CRCs and bounded scanline/zlib data before skipping conversion.
- Reject non-200 upstream thumbnail/animation payloads before inspection/conversion.
- Keep sizing modes, providers, cleanup, rollback and acceptance gates unchanged.
- Add five mandatory suites (102 total) and cumulative operators from five baselines.


## 0.9.34

- Serve valid disk-cached favicons before proxy initialization.
- Add bounded content-derived ETag revalidation for GET/HEAD; preserve PNG bytes and 404 placeholders.
- Bound favicon reads and reject linked/non-regular/oversized or invalid PNG cache entries.
- Initialize remote-attempt state before the placeholder error path.
- Preserve existing search, cleanup, rollback and privacy policies.
- Add four mandatory suites (97 total), cumulative upgrades from corrected v0.9.30 through v0.9.33,
  separate deploy/publication Fish scripts, documentation and local captures.

## 0.9.33

- Batch immutable Docker inventory queries with strict completeness checks.
- Select retained rollbacks by retirement timestamp rather than creation time;
  preserve ambiguous dates, future dates and ties.
- Refuse stale production snapshots before cleanup and immediately before cutover.
- Extend stateful transaction tests to administrator configuration changes, restarts
  and replacement by another container during candidate verification.
- Preserve all earlier mandatory suites; add four new suite entries (93 total).
- Deliver cumulative separate Fish deployment/publication from corrected v0.9.30,
  v0.9.31 and v0.9.32, with updated documentation and local captures.

## 0.9.32 — 2026-09-11

- Add protected, release-scoped Docker cleanup before the candidate build.
- Require durable release evidence before committing the cutover transaction.
- Fix encoded sidecar URL denial, erroneous gzip headers on 404 responses, and
  original-byte delivery for Range requests.
- Preserve every previous audit command; add four mandatory suites.
- Keep cumulative upgrade from corrected 0.9.30 or exact 0.9.31 and separate Fish
  deployment/publication workflows. The 1.0.0 release remains a later milestone.

## 0.9.31

- Prepare public CSS/JS gzip once at startup and improve encoding negotiation.
- Preserve native audit, provider gates and source/runtime provenance checks.
- Add offline analysis of manual benchmark records and bounded own-site profiling.

## 0.9.30

- Introduce event MPM/PHP-FPM with bounded favicon refresh work.
- Repair static-home tests, deadline assertions and deployment evidence handling.

Detailed historical release notes remain in `docs/RELEASE-*.md`.
