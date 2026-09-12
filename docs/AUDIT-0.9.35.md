# Audit scope — v0.9.35

Run `sh scripts/test.sh --keep-going` in the native application/audit image. All 97 previous
commands remain in order; the inventory now has 102 commands. A missing native dependency
is not success. The complete audit runs before provider acceptance and cutover.

New suites: native PHP/zlib PNG validation, actual PHP HTTP dispatch with fixed upstream
and decoder sentinels, native PHP Imagick fallback, release contracts and current-version
package/publication. Negative variants must fail assertions for an unverified CRC,
upstream-error passthrough and poster-path interference.

The accompanying delivery VALIDATION.txt and logs distinguish native PHP/Apache/browser
execution, mocked external Docker/SSH/API calls, expected mutation failures and tests
blocked by missing authoring dependencies. No IONOS access, remote publication or
competitive benchmark is performed as an authoring side effect.
