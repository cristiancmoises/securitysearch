# SecuritySearch v0.9.23 — audit and limits

## Provenance and scope

Built from the exact supplied v0.9.22-r2 tree
`99c6a9bae60056e5cc8d7a3b3b889e6b6036a15e`. GitHub's read-only connector still
reported the published v0.9.20 main tree at the time of inspection; this package
uses the more recent supplied r2 source rather than overwriting it with the mirror.
Fixture commits used for tests are not claimed as published release commits.
The final source tree and supported original/r1/r2 baselines are pinned in the kit
manifest. No VPS, remote branch, tag or release was modified during authoring.

## Reproduced local-picture failures

Actual localhost PHP responses from the r2 source archive reproduced two problems:
a fresh Settings POST selecting Custom emitted the local script under
`script-src 'none'`, and emitted no file editor. The homepage Custom redirect left
its picture section collapsed. The new HTTP responses allow only the selected
local script, emit the unnamed file input outside forms on both pages, and open
the editor before theme tiles. See `audit/picture-before-after.json` in the kit.

The actual local PHP suite adds 17 checks for controls, form ownership, open state,
Settings POST policy, no-store, no-script mode, preference persistence and switching
back to Black. Existing 20 experience and 9 homepage HTTP checks also passed.
The controller's 38 offline state checks cover signature-based file recognition,
blank MIME labels, preview/cleanup, generation races, size limits, blocked or full
storage, removal, and failures preserving the previous image. Storage/decoder
fixtures are not presented as real browser persistence tests.

## Actual browser fixture

33 checks passed in Chromium using real FileChooser events, FileReader, raster
JPEG/PNG/WebP/GIF decode, canvas re-encoding, preview dimensions, applied CSS
background, malformed input, removal, mobile width and disabled-JavaScript cues.
No outbound requests or unhandled JavaScript errors were observed in that fixture.
The image was a small colored test pattern, not the user's photograph.

IMPORTANT LIMIT: direct browser navigation to localhost was rejected by the
managed browser policy (`ERR_BLOCKED_BY_ADMINISTRATOR`). The test did not disable
that policy. Instead it rendered actual PHP-produced HTML with bundled resources
embedded and allowed the unchanged local controller by an inline CSP hash, after
the DOM as its normal deferred loading would do. It retained connect-src 'none'.
This validates actual browser image processing/display in a controlled document,
NOT the production HTTP/browser navigation or native storage-persistence path.
The opaque fixture origin naturally blocked storage, and the real page-only
fallback worked. Persistence is covered separately by state fixtures.

Desktop/mobile screenshots are fixture renderings, not new live-VPS captures.
The optional reproducible harness is `tests/picture-browser.py --output <outside-dir>`;
it requires Playwright, Pillow, BeautifulSoup and Chromium. Production gains none
of those dependencies. Never distribute the browser's font files.

## News

Official Redlib inventory (updated 2026-09-09) includes privacyredirect, nadeko and
privadency. Live search URLs could not be opened with the available web tool's
URL restrictions, and the authoring container cannot resolve external hosts.
These failures DO NOT prove those providers are down. Conversely, inventory
membership DOES NOT prove their searches are working. No instance is described
as live-verified here, and no live-provider latency improvement is claimed.

The deployment gate runs the real adapter transport and DOM parser from the
candidate on the VPS. It requires a positive parsed feed count AND positive
neutral-keyword count from the same approved instance, before stopping the old
container. It persists that tested primary and checks effective configuration
before committing cutover. Sanitized host/count/timing/class evidence is retained.
The first synchronous DNS call is not bounded by cURL alone; the entire CLI probe
has a 40-second process timeout. Every failed host leaves production unchanged.

22 pure PHP primary/selection assertions passed. Eight deployment-selection test
methods passed with Docker calls mocked, including malformed/empty/feed-only
reports, unsafe origins, runtime/deployer allowlist equality, config persistence,
failed reports, and ordering before cutover. The existing transaction scenarios
also include a failing news gate, so a failed check must not stop/rename production.
These are verification of the gate, not real Redlib availability results.

## Complete source suite

All 43 previous command entries remain unchanged in order, with four additional
mandatory commands (47 total). The real keep-going shell runner retains a nonzero
exit if any command fails. No native-runtime requirement or timeout was bypassed.
The kit contains raw per-command logs and runner status for source archives without
`.git`, both without the optional artwork and with a synthetic private-pack fixture.
Both contexts completed 30 commands successfully; 17 returned nonzero because
required PHP extensions or fish are absent. A complete native audit is NOT passed.

The nonzero diagnostics identify missing curl, DOM/XML, mbstring, APCu, Imagick or
fish. The full search HTTP suite fails after the PHP server reports missing
mb_strcut, not after a successful native run. Optional Pillow conversion is skipped
under system Python, as intended for the Docker audit. Existing import/pack/archive,
publication, motion, homepage/settings, DNS and deployment simulations run normally.
The full native gate, candidate checks and live Binternet test remain mandatory.

27 new version-specific publication/package tests passed with temporary Git repos
and mocked GitHub/Forgejo APIs. Forgejo multipart upload, single-host retries,
matching assets, conflicting tags/notes, archive provenance and token handling
retain their existing checks. Full-tree workflow results and exact source syntax
counts are supplied in the kit's workflow-tests.log and syntax.json.

## Environment and operational limits

Authoring PHP 8.4 lacks the extensions above; system Python 3.13.5 has no Pillow,
Node 22.16 and Git are available. fish and Docker are absent. Package retrieval
was attempted but external Debian DNS resolution failed. Python's optional image
and browser packages were used only in the authoring browser fixture, separately.

No Docker build/run, real VPS cutover, authenticated forge upload, production
browser journey or all-provider live benchmark is claimed. Simulated messages
such as 'Verified Redlib' or 'Deployment healthy' inside unit logs are fixture
output, not infrastructure events. The kit never bypasses those checks on the VPS.

Private historical Lain/SecOps originals and derivatives remain excluded from new
source commits and releases for all forges, including Codeberg. The external pack
is reused only by explicit --theme-assets for private deployment. Previous history,
images, backups, tags and conflicting release assets are not rewritten or deleted.

## Sources consulted

- https://raw.githubusercontent.com/redlib-org/redlib-instances/main/instances.json
- https://github.com/redlib-org/redlib/blob/main/templates/search.html
- https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/img-src
