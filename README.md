# SecuritySearch v0.9.41

[English](README.md) · [Português do Brasil](README.pt-BR.md) · [Documentation](docs/INDEX.md)

![Historical SecuritySearch homepage](docs/screenshots/securitysearch-0.9.36-home.png)

Historical v0.9.36 local Chromium capture with networking blocked. It is not a current
production screenshot, live search result, or performance score.

SecuritySearch is a privacy-oriented PHP search proxy based on [4get](https://git.lolcat.ca/lolcat/4get),
maintained by Security Ops. Web, images, video, RSS news and music retain their provider integrations.
Core search, filters, pagination and bundled themes work without JavaScript. Optional same-origin
scripts implement image animation, infinite scrolling and the browser-local My Picture background.
No query history, advertising, external analytics or provider-unavailability-as-success is added.

## What is new

The release identifier is **0.9.41**; static assets use **41**. Bounded image labels avoid pathological
HTML/JSON expansion before escaping. Filmstrip pagination uses asynchronous viewport observation
instead of synchronous scroll-time geometry reads. Apache rejects encoded internal homepage aliases,
correctly labels errors, and uses identity bytes for ranges while retaining the PHP-free anonymous
homepage. Google errors keep bounded, query-free transport traces; request counts and gates are unchanged.

The new manual v4 benchmark ranks **TTFB**, with total HTML time displayed separately. The original
v3, which ranks total HTML delivery, remains byte-identical. No measured public-site or universal
speed win is claimed. See [performance](docs/PERFORMANCE-0.9.41.md) and [research](docs/RESEARCH-0.9.41.md).

All **119** v0.9.40 commands remain an exact prefix; five additions bring the current audit to **124**.
For historical context, v0.9.40 extended its predecessor's inventory to 119. Historical IONOS 119/0
acceptance is not v0.9.41 acceptance. Every new candidate requires **124/0** and the live gates.

## Upgrade, audit and deploy

Use a full, clean `main` checkout in `~/securitysearch`; downloads remain in `~/Downloads`.
The observed GitHub baseline is v0.9.40-r2, tree `b0ab9966f02dd0c41ad8f936347b64cee5642c9f`.
The cumulative updater also supports corrected v0.9.30 tree
`488b01ae481e8e4e95dad3743c2a175c43065c97` and the exact retained intermediate baselines.
It checks the tree, not just a version string. Unknown, shallow, non-main or dirty work is refused.
It does not reset, stash, rebase, amend commits, force-push or discard existing work.

Local requirements are Fish, Python 3, Git, OpenSSH and coreutils. Deployment needs no local PHP or
Docker. It uses `root@securityops.co:5119`, the existing `security-search` Docker container and the
private pack `~/.local/share/securitysearch/operator-themes-v1`. Keep SSH host-key verification enabled.

Save the self-contained launcher and matching checksum in `~/Downloads`, then run:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-deploy-ionos.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish" --check-only
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish" --audit-only
end
```

`--check-only` is read-only with no SSH. `--prepare-only` creates only the ordinary local update commit.
`--audit-only` may commit, upload source/private themes and build/test on IONOS; it is **not read-only**.
It does not replace production, clean older objects, probe providers, tag or publish. Source, images
and evidence remain on disk. Success must end with `AUDIT ONLY COMPLETE — NOT DEPLOYED` after **124/0**.

After reviewing the audit, execute the same verified launcher without `--audit-only`. Normal deployment
repeats all tests and genuine Google **Web and Images**, RSS, Binternet and decoded-image checks.
A 429, CAPTCHA, empty or fallback result, missing prerequisite or partial log is not approval.
Normal deployment retains limited pre-build cleanup of eligible old stopped SecuritySearch objects;
cleanup may precede later candidate failure. Production, protected rollback, NPM, shared resources,
networks, volumes, backups and build cache remain protected. No global prune is performed.

## Commit and publish to four remotes

Only after `DEPLOY COMPLETE`, use the matching new publisher:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-publish-four-remotes.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
end
```

The publisher independently verifies deployed source/image identity and complete current evidence
before pushing `main`, immutable annotated `v0.9.41`, release notes and source/checksum assets to
GitHub `cristiancmoises/securitysearch`, Codeberg `berkeley/securitysearch`, and
`git.securityops.co/cristiancmoises/securitysearch` and `git.securityops.com.br/cristiancmoises/securitysearch`.
Tokens are entered privately, not saved or embedded in URLs. Conflicting tags/assets are refused.
Four-host publication is not atomic; matching partial work can be reconciled with `--host`.
`--verify-only` verifies without publishing. The published
v0.9.24 tag and later historical tags are not moved.

## Diagnostics, benchmark and privacy

The standalone `securitysearch-audit-diagnostics-0.9.41.fish` collects allowlisted audit evidence;
`--explain-latest`/`--explain-report FILE` are offline. Reports are private, source remains explicitly
unverified by diagnostics, and collection success never authorizes deployment or publication.
Retain the older reader for older tree-pinned reports. Do not share effective configuration or tokens.

Run `sh scripts/test.sh --keep-going` in the native test runtime. Missing Fish, PHP extensions or
Alpine/FPM prerequisites fail rather than skip. Audit capture is bounded to 8 MiB / 1,800 seconds;
this is not a build/upload deadline. The separately delivered validation report lists actual runs.

Run `fish tools/secops-web-benchmark-v4.fish --self-test` for an offline check. A normal invocation
performs real sequential homepage requests to seven targets and saves HTML/CSV/JSON reports under
`~/Downloads/securityops-benchmarks`. It is never run automatically by audit or deployment. Same
machine/network/time results are not a ranking of every search engine or of search-result quality.

External Redlib instances are run by independent third parties, not by Security Ops. My Picture does
not upload pictures, filenames or EXIF data. Restricted operator originals/derivatives stay outside
Git and public assets. The update kit includes no font files, credentials, private art or synthetic
Git history; it is a patch/review kit, not a fresh-install distribution. Never overlay source-review/.

[Operations](docs/OPERATIONS-0.9.41.md) · [Publishing](docs/PUBLISHING.md) ·
[Audit](docs/AUDIT-0.9.41.md) · [Troubleshooting](docs/TROUBLESHOOTING-0.9.41.md) ·
[Release notes](docs/RELEASE-0.9.41.md) · [Changelog](CHANGELOG.md) · [License](license.txt).
