# SecuritySearch 0.9.20 — audit evidence

Date: 2026-09-09. Status: **patch implemented; deployment and live availability unverified**.

## Source and limits

The authoring baseline is the user's supplied v0.9.19 source archive, recovered from their prior publication kit. The Codeberg head and direct Git network were unavailable. Critical source blobs were checked against the connected GitHub mirror: `scraper/binternet.php`, `lib/service_search.php`, `data/config.php`, `Dockerfile`, and `README.md` matched. A complete equality claim across all four remotes is not made. Application checks every touched baseline file byte-for-byte.

The old adapter expected `div.img-container`, `a.img-result`, leading slashes in proxy/pagination paths, and at most 64 HTML-escaped query bytes. The newer Binternet source uses `image-gallery`/`image-link`, page-relative links, and a 160-byte raw UTF-8 query limit. This is a demonstrated **source-contract incompatibility**, not proof of the sole cause of every live `Search paused` response. A service outage, 403/429, DNS/NPM problem or rejected redirect can still fail after the parser fix.

## Executed local checks

The checked-in runner contains **28 commands**: **12 completed successfully** and **16 were blocked by dependencies or skipped** in this environment. The blocked commands are not counted as passing. Full-runtime status remains unknown until the mandatory VPS audit gate runs.

All **118 PHP files** passed `php -l`; 4 JavaScript/CommonJS files passed `node --check`; 18 Python files, including the kit helpers, parsed successfully. Fish is not installed here. The apply fish launcher runs `fish --no-execute` against every kit fish file before applying changes on the user's machine; the VPS audit independently checks source fish and runs the historical fish integration suite.

Completed commands:

- `php tests/binternet-contract-regression.php`
- `php tests/theme-picker-regression.php`
- `php -d disable_functions=curl_setopt,curl_exec,curl_share_init,curl_share_setopt tests/provider-http-regression.php`
- `php -d apc.enable_cli=1 tests/provider-api-regression.php`
- `php tests/config-regression.php`
- `node tests/infinite-regression.cjs`
- `node tests/motion-regression.cjs`
- `python3 tests/publication-regression.py`
- `python3 tests/push-remotes-regression.py`
- `python3 tests/deploy-regression.py`
- `python3 tests/deploy-gates-regression.py`
- `python3 tests/theme-http-regression.py`

Highlights: 45 Binternet argument/route/host/length assertions; 73 theme catalog/markup assertions; mocked cURL connect/hop/shared-deadline checks covering all legacy call sites; 14 existing release-publisher unit tests; four new credential/publishing tests; eight deployment transaction simulations, readiness/mount checks and direct audit-gate simulations; actual localhost homepage/cookie/CSP checks. Deployment messages inside these test logs describe **simulated Docker state**, not actions on the user's VPS.

A separate Chromium rendering check passed **16 HTTP/layout assertions**: actual local homepage responses, saved theme cookie/redirect, rejected hostile/cross-site POST, Black CSS, no homepage scripts, form separation, visible image wallpaper, and desktop/mobile overflow. Browser navigation to localhost was blocked by the runtime's managed policy. HTTP assertions used Python's local HTTP client; Chromium rendered the returned HTML with only bundled resources embedded. The screenshots are local previews, never presented as live deployment screenshots.

## Blocked, skipped or not executed

- `python3 scripts/release-audit.py --syntax-only`
- `php -d apc.enable_cli=1 tests/regression.php`
- `php -d apc.enable_cli=1 tests/services-regression.php`
- `php tests/binternet-modern-regression.php`
- `php -d apc.enable_cli=1 tests/google-transport.php`
- `php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php`
- `php -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_error tests/stream-regression.php`
- `php -d apc.enable_cli=1 tests/ui-regression.php`
- `php -d apc.enable_cli=1 tests/reddit-regression.php`
- `php -d apc.enable_cli=1 tests/animation-regression.php`
- `php -d apc.enable_cli=1 tests/search-execution-regression.php`
- `php -d apc.enable_cli=1 -d disable_functions=curl_setopt,curl_exec,curl_errno,curl_getinfo tests/brave-transport.php`
- `python3 tests/publication-kit-regression.py`
- `python3 tests/http-regression.py`
- `php -d apc.enable_cli=1 tests/template-benchmark.php`
- `php -d apc.enable_cli=1 tests/performance.php`

PHP here is 8.4.23 and lacks cURL, DOM/XML, mbstring, APCu and Imagick. The nine historical publication-kit tests report an explicit unittest skip because fish is missing. Benchmarks requiring APCu/DOM were not produced. No synthetic provider speed percentages or fake p95 numbers are supplied. Error logs record these missing dependencies rather than disguising them as test successes.

The modern Binternet DOM fixture suite was **written but not executed here**. Its query, path and host contract tests do run without DOM and passed; that does not replace execution of the real PHP parser. Docker image builds, container/Apache integration, real provider requests, the IONOS deployment, public NPM/TLS checks and all four real remote pushes were not executed in the authoring environment.

## Security and behavior review

The fixed Binternet origin and public-IP validation remain; parsing never follows an upstream-controlled host. Only allowlisted HTTPS pinimg hosts are accepted as image sources, with credentials, ports, controls and backslashes rejected. Local path parsing accepts exactly the two known route spellings. Cursors are bounded and original query state is retained in encrypted continuation tokens. Unknown or unusable pages raise errors rather than successful empty results. Existing provider recovery and anti-abuse behavior are not bypassed.

The new helper shares DNS/TLS session state only inside one PHP request and sets finite cURL timeouts. Cookies, search bodies and rendered result pages are not placed in a shared cache. Selected source thumbnails reduce the need to retrieve originals for previews, but a live bandwidth/latency reduction has not been measured. Deadlines can reject a slow yet otherwise valid response; tuning is documented.

Theme selection is a non-sensitive browser preference, not an authentication operation. It is allowlisted, has an HttpOnly/SameSite cookie, rejects browser-declared cross-site POST and marks the homepage private/no-store. No JavaScript is added to the homepage. Existing image enhancements and their policies are retained.

Token entry requires an interactive terminal. The temporary askpass file contains no secret and verifies the HTTPS host, owner and repository. Child environments necessarily contain the token while Git uses it; this is not protection from privileged local process inspection. Git output is not dumped into credential errors. Real pushes are fast-forward only, with all-host preflight and post-push ref verification. Independent hosts cannot form an atomic transaction.

## Deployment acceptance gate

Before cutover, the VPS updater builds an isolated audit derivative, runs the complete offline suite with networking disabled and no production private volumes/environment, verifies the candidate, and executes a neutral real Binternet search. Available continuation is exercised with a fresh simulated per-page budget in the CLI-only probe. Failure keeps the old service running. The replacement preserves the established host binding/networks, and rollback is attempted if post-cutover readiness fails.

The optional all-provider matrix is a separate explicit operation: one neutral query per enabled provider/page combination, sequentially. It exposes empty/unavailable results and reports counts/times without raw results or credentials. One sample is not a distribution benchmark. These live results must be collected on the actual VPS before making availability or speed claims.

Raw evidence is shipped in the patch kit's `audit/` directory. The source patch's application/idempotence/preservation checks are recorded separately in `audit/application.json` after packaging; they exercise a temporary checkout, not the user's repository.
