# v0.9.11 operating guide

Historical v0.9.11 supplement. For current behavior see
[v0.9.12](OPERATIONS-0.9.12.md). See also [Português](OPERATIONS-0.9.11.pt-BR.md).

## Search reliability and privacy

Google remains the default, not a guaranteed-availability service. Query-free
bootstrap parameters are reused for five minutes; queries/results are not put in
that cache. A recognized anti-abuse response is suppressed briefly to avoid
repeated upstream traffic. A 4–6 ms error can therefore be a cooldown response,
not a successful Google search. Errors now return 503 with Retry-After: 30 and
no-store. API clients must handle this status instead of expecting all responses
to be 200. HTML is buffered until provider completion so these headers can be sent.

All Google hops share a monotonic 25-second network budget, including bootstrap
and token refresh. Each connection has at most five seconds to connect. Transient
502/503/504 can retry once per hop, on the same provider and egress, within that
budget. TLS verification stays on; CAPTCHA, 403 and 429 are not bypassed. No query
is silently sent to an alternative provider. Brave can also issue challenges.

## Image views and first-visit theme

`FOURGET_DEFAULT_THEME=Lain` matches the source default. Saved valid themes win;
reset the theme in Settings if an old browser preference remains. Asset version
14 busts prior CSS/controller URLs. Lain's 8.7 MB decorative GIF is omitted on
screens at most 600 px and for reduced-motion/reduced-data preferences.

The View filter offers Grid, Compact grid, uncropped Gallery and Large feed. These
are server-rendered, CSS layouts with row-major keyboard order. Fast preview is
the default; Original is an explicit heavier choice. Images stay behind the
existing validating proxy with lazy loading. Automatic pagination prefetches near
the page end, stops after three added pages, honors Save-Data and times out after
25 seconds. Next page works without JS; a failed one-time token offers Restart.

## Status path and reverse proxy

`/status/en.html` is an independent status snapshot, not a PHP search route.
IONOS NPM forwards `/status/` to a restricted private bridge endpoint. Verify the
private upstream from inside NPM before changing a route; validate the generated
Nginx config and keep a private backup. Do not replace working tunnels.
Do not add a global `Content-Type: application/json`: HTML, CSS and images must
retain their upstream MIME type. Search pages do not need wildcard CORS.

The public status page is read-only. Administration remains through Evelin/CT119,
not a fabricated web login. Status checks indicate point-in-time reachability,
not an uptime guarantee. A failed probe must remain visibly failed.

## Verification and deployment

Pagination tokens validate their shape, decoded key length and authenticated
payload before bounded decompression. Invalid attempts do not consume a valid
token; a successfully used token cannot be replayed. Errors offer a fresh search.
Apache prefork is capped at 16 workers (formerly 128) for the 512 MiB deployment;
this bounds concurrency but is not a substitute for production load monitoring.

Run `php -d apc.enable_cli=1 tests/regression.php` from the repository root. Lint
all tracked PHP and run `node --check` on the changed controller. Test negative
view/theme inputs, malformed JSONP, cooldown and deadline behavior. Build with
Docker, then verify home, settings, all four views and errors at 360, 768 and
1440 px, including without JavaScript. Test actual Google results and a failed
provider from the production egress; a 200 health check alone is insufficient.

Keep private proxy pools, keys and generated caches out of source artifacts.
Retain the previous image/container configuration and icon volume for rollback.
Start the candidate on a private test port first; only switch after validation.
Verify public HTTPS status, MIME types, security headers, assets and search again
after deployment. Do not force-push diverged remotes or publish credentials.

No synthetic views, purchased engagement or claimed organic growth is part of
this release. Search result pages remain noindex to avoid indexing private queries.
