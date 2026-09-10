# Operating v0.9.24

Use the matching update kit: application/asset versions are 0.9.24/28. Old exact-hash
launchers are not compatible. The baseline is clean v0.9.23-r1 or v0.9.23. Preserve
local changes and existing old tags; this version creates v0.9.24 only.

The default news provider is `newswire`. `FOURGET_NEWS_RSS_PRIMARY` accepts only
`google` or `bing`; `FOURGET_NEWS_RSS_MARKET` accepts only `en-US` or `pt-BR`.
`FOURGET_DEFAULT_SCRAPER_NEWS=newswire` is installed by the updater. Saved browser
provider choices stay intact. Visit `/news?scraper=newswire` to select it explicitly.
RSS feed caching is independent of browser preferences and never stores keyword
results. Old cached Redlib choices do not qualify the RSS deployment probe.

The live probe makes at most four neutral requests: headlines, then keyword search
only if the same source's headlines qualify, with at most two sources. No challenge
solver or retry on that origin. It bypasses result caches. Safe status/error/count
information is written to `news-live.json` and failures print their actual stages.
Both stages need 1–40 results from one source before stopping the old container.
CLI process timeout is 30 seconds, in addition to adapter budgets. Initial DNS is
synchronous and is not independently bounded by a cURL timeout.

`redlib-live.json`/the older Redlib CLI remain optional diagnostics, not acceptance
of the new RSS provider. We replaced the unavailable Reddit requirement with real
publisher-news verification, not with a skip. Offline tests, candidate readiness,
live Binternet checks, replacement configuration verification and rollback remain.
Keep backup/rollback directories; mounted private data can depend on them.

Use `--theme-assets ~/.local/share/securitysearch/operator-themes-v1` on each private
deploy to include historical operator media. The public tagged source package and
incremental bundle never contain that pack. Do not attach it to a forge release.
`--rank-refresh` remains optional and unrelated to news availability.

New RSS sources are best-effort external services; the kit cannot certify their
uptime from the operator's network before the actual probe succeeds. Refusal of
all sources leaves the old service in place. No site can guarantee every query.
