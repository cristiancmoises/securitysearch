# v0.9.22 validation and boundaries

The provided v0.9.21 VPS log ends in `tests/http-regression.py` at the eight-second
request to `/news?s=failure`. The primary-only Reddit mock did not intercept the
new fallback transport. Its real DNS/HTTP work was inappropriate for an offline
fixture. The repair intercepts `fetch_redlib` for every allowed origin, keeps the
client timeout unchanged, tests cooldown/pagination, and installs test-child
network tripwires. No production cURL configuration or acceptance gate is disabled.

## Executed during authoring

- Four offline-boundary tests passed: all fallback origins are intercepted, every
  network tripwire aborts, default-runtime lint succeeds, and the old primary-only
  pattern demonstrably escapes into a tripwire. The real request/pool code is used;
  decoding is stubbed in this small suite. This is not native DOM/HTTP approval.
- 64 positive-DNS-cache assertions passed. Thirty same-fixed-host lookups within
  the fixture TTL use one resolver callback. TTL expiry, poisoning, private/mixed
  answers, unmapped public answers, zero TTL and nonshared hosts are covered.
  This is a callback-count check, not measured public-provider latency.
- Thirteen operator-theme and publication-policy tests passed: original-byte
  substitution refusal, local historical Tron blob, bounded real WebP conversion
  of two-color animation fixtures, checksums, symlinks, unexpected files, runtime
  optional style gates, derived path rejection, new-history added/deleted images,
  renamed original blob IDs and preservation of already-existing history.
- Twenty-seven v0.9.22 publication/package tests passed using temporary Git
  repositories and mocked APIs, including one-host selection and Forgejo multipart.
- The actual local PHP experience suite passed twenty HTTP/header/form checks;
  the native theme suite passed 92 assertions. Browser navigation and original
  Lain/SecOps appearance were not exercised here.
- All forty source-suite command entries were attempted individually. Final results
  are 23 passed and 17 dependency-blocked or explicitly skipped. Required fish and
  PHP curl/DOM/XML/mbstring/APCu/Imagick are unavailable here. The full native test
  gate is not represented as successful. Raw per-command results are in the kit.

Twelve full-tree workflow tests passed in disposable clones: v0.9.21 and v0.9.20
application, normal commit/tag creation, exact repeats, local/untracked/committed
work preservation, commit-hook failure, tag conflict refusal, deterministic full
source packages, an intercepted SSH boundary, and private overlay versus public
archive separation. No fixture commit or archive is distributed as the real release.
The first workflow pass caught an over-broad media-name rule rejecting the prior
Matrix screenshot; its exact known-public blob was allowlisted and the suite reran.
The rule still refuses unknown replacements under that same name.

The original local configuration regression initially caught the old asset-25
assertion after the asset-26 bump; that expectation was corrected and rerun.
Individual PHP/JavaScript/Python syntax checks passed; native fish parsing is
required by the launchers on the user's machine. Docker and real authenticated
forge uploads were not run. Deployment transaction/API output inside unit logs
is simulation output, not a production event.

## Historical assets and publication policy

The GitHub mirror inventory at the user-supplied historical commit identifies:

| Original | Git blob | Bytes |
|---|---|---:|
| tron.gif | 0a26936f72d7ffe53a1668fafd9ca8e7edfb03e5 | 12261897 |
| lain.gifv | fcd2163ef4f77991b0f66af12099bd13e7322b3c | 9056643 |
| secops.gif | b5e32642d24b7e31bdba2a4c06aa7aad1d41cb9f | 18952924 |

The local Tron original matches. Raw historical Lain/SecOps downloads were not
available in the authoring environment. They are not silently replaced by newly
generated imagery: the explicit operator preparer requires those exact Git blob
IDs and sizes, then hashes and bounds every derivative before deployment.
Conversion sampling alters frame count/size; it is not lossless reproduction.

All originals and derivatives designated operator-only remain outside all four
forges' new source releases, including Codeberg. Public palette/Matrix fallbacks
are exact-blob allowlisted. This is a known-file policy, not an AI detector and
not certification of compliance for all old repository content. Outgoing-history
checks refuse new intermediate commits containing restricted artwork. Existing
historical objects are not rewritten or purged.

SecOps loads either its public Matrix motion stylesheet or the private historical
one, never both, avoiding a redundant large-background request.

The initial PHP resolver is synchronous; a cURL timeout does not impose a strict
wall-clock deadline on that call. The new positive cache limits repeated work but
cannot guarantee network latency or provider availability. Tranco/onion status is
not inferred from unit tests. VPS deployment remains guarded by the full isolated
native suite, candidate readiness and the real Binternet check.

## References

- Git ignore behavior: https://git-scm.com/docs/gitignore
- Historical source: https://codeberg.org/berkeley/securitysearch/src/commit/81979bb217f97df1c6acc724ef7d8c9da2789d7c/static/misc
- Pinned mirror: https://github.com/cristiancmoises/securitysearch/tree/81979bb217f97df1c6acc724ef7d8c9da2789d7c/static/misc
- Pillow removed legacy WebP feature checks: https://pillow.readthedocs.io/en/stable/deprecations.html
