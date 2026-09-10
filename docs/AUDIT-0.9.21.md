# SecuritySearch v0.9.21 — audit and provenance

## Source and scope

Based on the published v0.9.20 tree `5b37bce9c534389362f4f14169ff826ca58cceb8`,
verified against GitHub main commit `7f9f25a2f56a2b2edb1189e076da29895f95c057`.
The full tree was reconstructed from the supplied v0.9.19 source plus the exact
r1 and v0.9.20 publication patches. Its tree matched exactly. Local reconstruction
commits are fixtures, not public release identifiers; the kit applies a binary
patch to the user's real history and packages the real annotated tag there.

Application version 0.9.21; asset marker 25. No production deployment, repository
push, public tag/release creation or authenticated attachment upload was performed.

## Checks actually executed

- **18 source test commands passed; 17 were blocked/skipped** by missing
  dependencies. All 35 entries in scripts/test.sh were attempted individually.
  This is not a successful full-suite acceptance. Raw command logs and exit codes
  are in the external kit's audit/source-tests.json and cmd-*.log.
- **27 publication/package tests passed**, including fixed one-host selection,
  actual temporary bare Git fast-forward/atomic push fixtures, draft/partial asset
  recovery, correct Forgejo multipart framing, byte/digest conflicts and secret
  stripping on allowed download redirects. APIs use mocks, not real credentials.
- **89 theme assertions**, **21 pool/deadline/metadata/health assertions**, the
  poster-preserving motion event suite and local-picture session/persistence/MIME/
  quota/stale-callback suite passed. JavaScript storage/decoding here use fixtures,
  not claims about a navigated browser profile.
- **20 actual localhost PHP experience checks** and **9 existing native theme HTTP
  checks** passed. They exercise real controllers/headers/forms with fixed image
  renderer data; no provider transport is called. Custom permits the local script
  but keeps connect-src none on the homepage; file input is outside forms/unnamed.
- **4 rank-timer tests passed**, covering repeat installation, refusal of different
  units/symlinks and retained timer on refresh failure. systemctl is mocked.
- Deployment/readiness/rollback and offline/live-gate regression fixtures passed.
  Messages such as Deployment healthy or Binternet returned real results in those
  test logs are simulated output, not observations of the user's infrastructure.
- **125 PHP**, **6 JavaScript/CommonJS**, and **28 Python** syntax checks passed.
  Fish parsing was unavailable here; the kit checks all its fish wrappers before
  running Python on the operator host, and source fish checks remain in the VPS gate.
- Onion address v3 checksum/format passed. No Tor reachability test was performed.

## Complete patch/packaging workflow checks

**10 full-tree workflow integration tests passed** in disposable clones of the
exact reconstructed v0.9.20 tree. They covered application/normal commit/repeat,
annotated tag reuse, untracked and tracked local-work preservation, wrong branch,
newer committed work, existing/conflicting tags, a failed pre-commit hook leaving
the update staged, full-source tar/checksum/bundle generation with byte-identical
repeat output, and validated deployment stopping at an intercepted SSH boundary.
The test-only package module used the fixture ancestor instead of the real public
commit. The shipped package/helper retain the actual v0.9.20 public ancestor.
No fixture archive, tag or bundle is distributed as the user's real release.

## Browser rendering versus browser navigation

Actual HTTP navigation in Chromium was blocked by its managed URL policy
(ERR_BLOCKED_BY_ADMINISTRATOR). The policy was not modified or bypassed. The
optional tests/browser-experience.py navigation/storage suite is supplied for an
unrestricted test host, but is **not claimed to pass here**.

Instead, actual localhost PHP HTML was rendered in Chromium with bundled CSS/
images embedded in memory and executable scripts removed. Seven snapshots were
inspected. The eighteen previews decoded, image selects computed to black with
cyan text, and the 390px mobile layout had no horizontal overflow. These are
**rendered snapshots, not end-to-end navigation, storage, CSP-enforcement or live
provider tests**. No serialized font/image HTML payload is distributed; screenshots
contain pixels only. Browser policy/network tests and screenshot evidence are
kept separate in the kit's audit and preview directories.

## Missing runtime and live coverage

PHP cURL, DOM/XML, mbstring, APCu and Imagick, fish and Docker are unavailable in
the authoring environment; dependency download attempts failed. The original
HTTP provider suite failed on missing mbstring. Real Redlib/Binternet DOM parser
and native cURL checks therefore remain mandatory on the VPS. New offline Redlib
DOM failover tests are part of the full suite, not silently skipped there.

The deployment retains the isolated full-suite, candidate readiness, live
Binternet and rollback gates. Do not disable extensions or bypass those gates.
No all-provider latency improvement, Tranco rank, fallback-instance reachability,
onion reachability or actual four-host API compatibility is inferred from fixtures.

## Privacy, operational limits and references

Only the blank public first news feed is cached (60 seconds). External Redlib
fallback may receive the query/server IP, with disclosure and a disable flag.
Custom image bytes/filename/EXIF are not sent by the local-picture controller;
normalized data lives in browser session or explicit persistent storage. Same-
origin scripts and a shared browser profile can read storage; no encrypted-vault
claim is made. Theme change alone does not erase a remembered picture: Remove does.

Tranco refresh is an optional separate CLI/systemd task; rendering never accesses
its API. The kit does not ship an invented rank. Missing/stale cache is unavailable.
A timer/metadata failure never rolls back an otherwise healthy deployment.

Primary contracts checked during development:
- https://raw.githubusercontent.com/redlib-org/redlib-instances/main/instances.json
  (inventory last updated 2026-09-09; nadeko.net and privacyredirect.com listed).
- https://tranco-list.eu/api_documentation (dated per-domain ranks endpoint).
- https://forgejo.org/docs/latest/user/api/usage/
- https://docs.gitea.com/api/1.22/operations/repo-create-release-attachment/

The publisher keeps tokens in memory and briefly in a trusted local Git child
environment, not in command arguments/files/URLs. Reruns preserve different
existing assets/tags rather than overwriting. Cross-host publication is not atomic.
