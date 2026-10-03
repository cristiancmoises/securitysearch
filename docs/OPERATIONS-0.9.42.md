# SecuritySearch v0.9.42

See [README](../README.md), [PT-BR](../README.pt-BR.md), [SkunkyArt](SKUNKYART.md), and
[retention](RETENTION.md) for the full current behavior and complete Fish commands.

Upgrade from the exact clean supported source; preserve Git history and private themes.
Target SSH is root@securityops.co:5119, checkout ~/securitysearch and downloads ~/Downloads.
The original versioned kit performs one native audit followed by live gates, cutover, independent verification and
post-success cleanup. Separate audit-only first is not required. Full candidate acceptance requires
130/0; provider failure never passes. Publish only after DEPLOY COMPLETE using
securitysearch-v0.9.42-publish-four-remotes.fish. No force push or existing tag movement.

The frozen v0.9.42 kits do not include later `main` maintenance commits. Do not reapply them to
an updated checkout or claim that they deployed current `main`. Keep kits, tree allowlists and
immutable tags unchanged. Maintenance uses the reviewed current source and required private
operator resources on the Docker host: `scripts/deploy-ionos.py` builds its own source tree,
requires the native Docker/PHP audit runtime on the VPS and needs explicit
`SECURITYSEARCH_VERIFY_GOOGLE=1`. It is not the full one-shot installer; independent post-deployment
source/image/evidence verification and scoped cleanup after verified success remain separate.

## Provider checks

Separate three questions: does the application preserve its response contract, can the configured
network path reach the service, and does the provider return genuine usable results? A healthy
homepage or HTTP 200 is not proof of successful search. Valid empty results are not transport errors,
but they do not satisfy a deployment gate that requires nonempty results.

Use the existing `lib/search_probe.php` and deployment gates for bounded verification. Keep query
data, effective configuration, proxy addresses, credentials and raw provider responses out of
public reports. Record only the necessary status, reason, counts and verification scope.
Honor rate limits and challenges; do not rotate addresses to bypass them.

Before changing application code for a transport failure, check the configured proxy DNS and the
exact existing service/container network. For SkunkyArt, verify that the reverse proxy can reach
the service. For Binternet, inspect the existing container's state and startup error. Preserve
configuration and inspect capacity; do not recreate services, globally prune Docker or relax gates.

## Maintenance and evidence — 2026-10-03

Google CSE uses controlled failure classifications. The five search API routes return status-only
HTTP 503 failures with no-store and retry headers, without reflecting provider text or PHP details.
Brave decodes bounded literal Svelte data and closed IIFE bindings without executing JavaScript;
missing results and nonempty unusable image lists fail, while valid empties and filters remain valid.

Two obsolete proxy DNS entries were removed, and connectivity for the existing SkunkyArt and
Binternet services was restored. These operational changes do not guarantee every provider.

| Check | Evidence and scope |
|---|---|
| Captured Brave responses, offline | 20 web records decoded/rendered and 167 usable images decoded/parsed with networking disabled. This tests parsing, not native upstream transport. |
| Browser API searches after service-network repairs | Binternet: 25 images; SkunkyArt: 23 images. Both returned continuation tokens over HTTPS 200 with valid TLS. |
| 57-check snapshot before code replacement | 27 result-bearing responses, 4 empty responses and 26 unavailable combinations; not universal provider acceptance. |

Google JSON API is inactive without an operator-supplied key and is separate from Google CSE.
Private configurations need explicit `PROXY_PEXELS`, `PROXY_UNSPLASH` and `PROXY_PIXABAY` settings.
Their public defaults are `false` (direct connections); select an existing pool when required by
your routing policy. Add missing settings without overwriting other operator values. The backend
does not silently invent a route when configuration is absent.
Archive.org videos and Yep news are not offered without category implementations; Yep web remains.
Cara rejects malformed response/image structures with a controlled format failure, not a PHP crash
or fabricated empty success. API protection also covers adapter/filter initialization.
Every deployment still requires its own complete 130/0 native audit, genuine Google Web and Images,
RSS, Binternet and SkunkyArt live results, guarded cutover and independent source/image verification.
Publish only after verified acceptance; remove recognized old builds only after verified success.
The limited checks above are not a complete audit or deployment receipt.
