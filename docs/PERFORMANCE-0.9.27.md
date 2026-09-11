# v0.9.27: less origin work, unchanged page features

## Scope

The target is lower application CPU work and response latency under real traffic,
not a benchmark-specific page. Responses never branch on the benchmark user agent.
A win over another deployment cannot be inferred from a smaller application timer:
DNS, TLS, route length, reverse-proxy queues and upstream search response time remain
separate costs. The supplied comparative benchmark is retained as a manual tool;
no public benchmark, leaderboard, winner claim or new public run is part of this release.

## Startup-compiled resources

Before Apache starts, `docker/docker-entrypoint.sh` invokes
`php lib/build_view_resources.php --build`. Only fixed public template strings,
three homepage CSS strings, public theme names/preview URLs and source hashes enter
`data/view-resources.generated.php`. PHP's existing OPcache can retain its literals.
No configuration secrets, resolved server-name values, visitor requests, HTML
responses, cookies, searches, result sets, local pictures or private artwork are
compiled or cached. It is a resource bundle, not a shared page cache.

Each response still selects preferences, escapes replacements, renders the same
forms/controls and retrieves current Tranco metadata. Operator-only theme previews
are overlaid at request time using the unchanged validator. All existing search
providers, boundaries and attribution remain.

A small shared `page_renderer` owns the existing load API. `frontend` inherits that
API for search pages; the homepage no longer parses/loads the search-result class.
Unused style/footer replacements are not assembled for partial templates that do
not contain those placeholders. Replacement remains single-pass and non-recursive.

The dynamic filesystem path remains available. A build failure or
`SECURITYSEARCH_RENDER_BUNDLE=0` selects it without hiding features. Missing, stale
asset-version, invalid, symlinked, writable-by-group/others or differently owned
bundle files are not used. The builder writes atomically beside the trusted config;
this is deployment-owned PHP code, not an upload destination. It makes no requests.

## Operations and invalidation

Generation runs on every ordinary container startup, after normal config creation
and before Apache workers start. Existing deployment rollback and native acceptance
gates are retained. All public templates/CSS are treated as immutable for that
process lifetime. After intentional source edits, regenerate and restart/redeploy:
`php lib/build_view_resources.php --check` detects a stale bundle. Do not expect
editing a generated file or changing process environment through `docker exec` to
update existing Apache workers. The existing image already disables OPcache timestamp
validation, so PHP code changes likewise require process replacement.

The artifact is excluded from Git, source releases and Docker build input. It is
created inside the candidate/replacement after extraction; private theme packs
remain outside public source. No NPM configuration, shared cache, TLS policy, worker
count, rate limit or upstream timeout is changed to chase the benchmark.

`X-SecuritySearch-Render: compiled` confirms the homepage used the bundle.
`dynamic` means the fallback is active. `Server-Timing: app;dur=...` still measures
PHP processing only. Both modes retain `Cache-Control: private, no-store` and the
existing CSP, no-JavaScript default and all selected theme behavior.

## Read-only origin diagnosis

After deployment, run the kit's `diagnose-delivery.fish`, or run
`python3 scripts/delivery-profile.py` from the checkout. It uses SSH5119 and a fixed
non-root Docker exec command to observe three localhost homepage requests. It does
not rebuild, restart, change configuration or send any provider search. Results
contain app/TTFB/total timings, compression, bytes and allowlisted headers, not
response bodies, cookies or process environment. The JSON is written mode0600 in
`~/Downloads`. Missing access or malformed reports fail; an old asset or non-200
response cannot be called successful. These observations exclude the public
DNS/TLS path, NPM and geographic routing; they are not comparable as standalone
values to the seven-site benchmark. A low app duration and high external TTFB call
for investigating infrastructure, not weakening search validation or removing UI.

## References

- PHP OPcache configuration: https://www.php.net/manual/en/opcache.configuration.php
- Apache performance considerations: https://httpd.apache.org/docs/2.4/misc/perf-tuning.html
- Server-Timing: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Server-Timing
- HTTP caching: https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Cache-Control
