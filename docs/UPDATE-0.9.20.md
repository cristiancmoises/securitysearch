# SecuritySearch 0.9.20 — operations

## Baseline and safe application

This is a patch for the supplied v0.9.19 source (asset 23), not a replacement Git history. The Codeberg head could not be fetched in the authoring environment. Critical archived source blobs (Binternet, service transport, config, Dockerfile and README) matched the connected GitHub mirror. The kit manifest pins the SHA-256 before and after every touched file.

Run `fish apply-securitysearch.fish ~/securitysearch` from the extracted kit. It requires the checkout root, clean `main`, and configured `user.name`/`user.email`. It creates one commit containing only this patch. A dirty checkout, changed baseline, untracked collision or incompatible history stops the operation. Do not reset or delete a Codex change to force application. Reconcile the actual changes first. Reapplying an exact completed update is harmless.

A commit-hook/signing failure leaves the patch staged and reports that state; it does not erase the patch or attempt a reset. Inspect `git status` before retrying. The commit identity is yours; no synthetic baseline commit is imported.

## Copy and deploy from your machine

```fish
fish ./deploy-securitysearch.fish ~/securitysearch
```

The fish launcher and its supplied Python helper verify the committed patch against the manifest, archive the existing Git commit, calculate SHA-256, upload with `scp -P 5119` and run the Python updater through `ssh -p 5119 root@securityops.co`. SSH host-key and HTTPS certificate checks remain enabled. The source archive stays private under `/root/securitysearch-incoming/` on the VPS.

Host requirements: Python 3, Docker with its local Unix socket, curl and flock. `security-search` must already be running with the known `172.17.0.1:5140 -> 80` binding. The default name can be changed deliberately through `SECURITYSEARCH_CONTAINER` when running the server-side updater; the launcher does not guess container names. Application/runtime-masking mounts, static container addresses and incompatible port layouts are refused.

Deployment keeps the old container running while it builds the production image and a separate audit derivative. The derivative adds Python/Node/Git/fish and runs **every command in `scripts/test.sh`** in an isolated container with no network or production environment/volumes. Its captured output is `offline-audit.log` in the private backup directory. A skipped dependency or failing suite prevents cutover.

Next, an unexposed candidate receives the retained runtime settings and permitted private-data mounts. Readiness checks the release marker, Black asset 24, homepage no-script policy, scoped image assets and helpers. A neutral `teste` query exercises Binternet from the candidate's network; when returned, its continuation is exercised too. `binternet-live.json` records only status, counts, timings and an exception class. No query results or secret tokens are logged. The external call is capped by `timeout 35` as well as PHP transport deadlines.

Only after those gates pass does the updater stop/rename the previous container and create the replacement with the preserved published address/networks. This cutover causes a short interruption. Replacement readiness and the original host binding are checked. Failure restores the retained old container where Docker permits it. A recovery failure is reported with the exact rollback script path; automatic recovery is not claimed to be infallible.

Nginx Proxy Manager is not recreated, restarted or reconfigured. Public DNS, TLS and the NPM route still need a final browser check at the existing site. A successful container check is not a live public-site certificate audit.

## Rollback and retained private files

Use the exact `Rollback: bash .../rollback.sh` line printed by the updater. The script locks against concurrent deployment and refuses to replace a container belonging to another release. It restores the original restart policy. Do not invent a timestamp or choose a rollback directory from a different update.

Retain `/root/securitysearch-backups/<printed timestamp>/` and the rollback image/container. The new application may mount credential/proxy/captcha snapshots from that directory. Deleting it can break a healthy deployment. No prune command is included.

## Four-remotes publishing

```fish
fish ./push-securitysearch.fish ~/securitysearch
```

The helper prompts in a TTY for each host's separate token. Hosts/owners are fixed:

- `git.securityops.co/cristiancmoises/securitysearch`
- `git.securityops.com.br/cristiancmoises/securitysearch`
- `github.com/cristiancmoises/securitysearch`
- `codeberg.org/berkeley/securitysearch`

No token goes in a URL, command argument, file, shell-history line or repository. The temporary askpass program contains no token and releases credentials only for the intended HTTPS host/owner/repository. The token is still necessarily present in the short-lived Git child environment: this is not protection from another process with sufficient OS privileges. Run on a trusted machine, without tracing/debugging wrappers.

All four repositories and `main` branches must already exist, and the tokens must have permission to push this repository. All four histories and dry-run pushes are checked before any real push. No force push, automatic merge, repository creation, branch switch, tag movement or release-object creation occurs. If one host is missing/diverged/unreachable during preflight, no remote is pushed. If a later real push fails after others succeeded, successful hosts are retained and the command reports incomplete verification. Re-run to reconcile; cross-host atomicity is impossible here.

## Actual provider matrix and performance tuning

```fish
fish ./audit-providers.fish
```

This is an explicit live operation. It sends one neutral `teste` query per enabled provider/page combination, sequentially, with a one-second pause between entries. It uses direct adapters without fallback, counts results and reports elapsed time. It may take several minutes. A provider blocked by its source, missing configuration, parser mismatch, empty result or timeout remains visible; the command exits 2 when not every search has results. The report is copied back alongside the kit.

This matrix measures one sample per pair, **not** p50/p95 latency or a load test. Do not claim a speedup from these single observations. The local audit uses mocks/fixtures and is not this live matrix.

New operator settings are `FOURGET_PROVIDER_CONNECT_TIMEOUT_MS=3000`, `FOURGET_PROVIDER_TIMEOUT_MS=12000`, and `FOURGET_PROVIDER_TOTAL_TIMEOUT_MS=20000`. They bound the legacy helper; CSE and Brave retain their existing independent transport policy. Higher values can help a slow source but lengthen failure waits; lower values can discard otherwise valid slow responses. Settings are clamped to finite ranges. There is no new shared query/result cache or background retry loop.

The Binternet service origin remains `images.securityops.co`. The navigation's `img.securityops.co` is not automatically substituted. If the live gate fails, investigate that fixed origin's DNS/NPM route and upstream Pinterest status separately. A compatible HTML parser does not resolve an HTTP 403/429, an unavailable service or a redirect rejected by the existing fixed-origin transport.

## Appearance

New visitors get pure black. Existing browser theme cookies are retained: select **Choose appearance → Pure black → Save appearance** to change an existing browser. The form works without JavaScript. Image previews are local stills; selecting an animated wallpaper still loads that existing full wallpaper by explicit user choice.
