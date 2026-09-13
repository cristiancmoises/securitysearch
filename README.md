# SecuritySearch v0.9.40

[English](README.md) · [Português do Brasil](README.pt-BR.md) · [Documentation](docs/INDEX.md)

![SecuritySearch homepage](docs/screenshots/securitysearch-0.9.36-home.png)

Historical v0.9.36 local Chromium capture of the PHP homepage with networking blocked.
This is not a current production screenshot, search-results sample, or performance score.

SecuritySearch is a privacy-oriented PHP search proxy based on
[4get](https://git.lolcat.ca/lolcat/4get), maintained by Security Ops.
Web, image, video, news and music search use the existing provider integrations.
Core search, filters, pagination and bundled themes work without JavaScript.
My Picture, animation controls and infinite scrolling use optional same-origin JavaScript.
Provider rate limits and outages are reported as failures, not successful results.

**This delivery upgrades the verified v0.9.30 source directly to v0.9.40.**
Do not install v0.9.31–v0.9.39 first. The release identifier is `0.9.40`; the independent
static-asset identifier is `40`. A deployable package is not evidence of production acceptance.
The scripts require a complete native audit and live-provider checks before replacement/publication.

## What changes from v0.9.30

The cumulative update includes the retained v0.9.31–v0.9.38 fixes: static delivery and favicon
handling, image proxy/PNG validation, usable image-preview selection instead of 1-pixel
placeholders, smaller integrity-checked operator payloads, and complete bounded audit evidence.
The current search-state changes preserve literal queries `0`, `all`, and `any`, preserve
zero-valued filters, encode continuation tokens as one parameter, use the normalized query
for web oracles, and retain HTTP 503/no-store/Retry-After on music-provider failures.

The image selector examines at most 32 supplied sources per card without making image probes.
Provider transport, existing retry budgets and request limits remain protected. No query cache,
new provider fanout, advertising, JavaScript dependency, or benchmark winner claim is added.
Music output buffering preserves error headers; it is not a latency optimization.

The native audit contains **119 mandatory commands**. The first 115 v0.9.38 commands retain
their order; four new suites bring the total to 119.
They cover search-state, HTTP, release and publication behavior.
The audit refuses missing commands, duplicates, incomplete logs, nonzero exits and missing
native prerequisites. Read the candidate-specific validation report supplied with the update kit.

## Direct IONOS upgrade

Downloads: `~/Downloads`. Full clean Git checkout: `~/securitysearch`, branch `main`.
Local tools: Fish, Python 3, Git, OpenSSH (`ssh` and `scp`), coreutils and tar/gzip.
No local PHP, Docker, or `guix shell` is required by the launchers.

The independently observed GitHub v0.9.30 baseline is commit
`331cf550d8b279736126e7362fb699f99fc2bc66`, tree
`488b01ae481e8e4e95dad3743c2a175c43065c97`.
Your server and other forges were not queried to infer their state. The launcher checks the
actual local tree, not just the version string. The nine retained exact v0.9.30–v0.9.38
baselines and an already-applied exact v0.9.40 are supported; unknown or dirty work is refused.

Save the deployment launcher and its `.sha256` directly in `~/Downloads`, then:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.40-deploy-ionos-r2.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.40-deploy-ionos-r2.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.40-deploy-ionos-r2.fish" --check-only
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.40-deploy-ionos-r2.fish" --audit-only
end
```

`--check-only` is read-only and uses no SSH. `--audit-only` creates a normal patch commit
when needed, uploads source/private themes, builds and runs native tests on the VPS.
It does not replace production, clean older objects, run live-provider probes or publish.
Uploads, images and evidence remain on disk. Minimum free-space checks do not guarantee a build fits.
Success ends with `AUDIT ONLY COMPLETE — NOT DEPLOYED`.

To prepare the normal local commit without SSH, themes or a build, run the same verified
launcher with `--prepare-only`. This is optional: audit/deploy already applies the update
and creates its commit. Repeat invocation on the exact target creates no duplicate commit.
It never tags or pushes, and failure does not discard your staged work.

After reviewing a successful native audit, run the verified deployment launcher without flags:

```fish
fish --no-config "$HOME/Downloads/securitysearch-v0.9.40-deploy-ionos-r2.fish"
```

Target: `root@securityops.co`, SSH port `5119`, existing container `security-search`.
Private theme pack: `~/.local/share/securitysearch/operator-themes-v1`.
Existing Docker bindings, Nginx Proxy Manager, networks, volumes and private configuration
are preserved. Host keys must already be known and match; forwarding is explicitly disabled.
No host-key bypass, global prune, forced history rewrite or backup deletion is performed.

Normal deployment repeats the **119/0** native audit and requires genuine Google Web **and**
Images, RSS, Binternet and existing image checks before cutover. It retains the advisory lock,
production-drift checks, source/image verification, rollback protection and receipt validation.
Only `DEPLOY COMPLETE` indicates the guarded workflow completed.

Unlike audit-only, normal deployment retains targeted pre-build cleanup of eligible old,
stopped SecuritySearch objects. Such cleanup can precede a later candidate failure.
Running production, protected rollback objects, shared resources, NPM, volumes, networks,
backups and build cache remain protected. `--cleanup-plan` previews eligibility without deletion.

## Commit, push and release to all four remotes

The deployment/prepare launcher creates the ordinary local update commit on **your existing
history**. The separate publisher verifies the active deployed commit, image and retained
audit/live evidence before creating or reusing an immutable annotated `v0.9.40` tag and pushing.
It does not require deploying intermediate versions or moving your existing `v0.9.30` tag.
Earlier tags, including the published
v0.9.24 tag and v0.9.30, are never retargeted.

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.40-publish-four-remotes-r2.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.40-publish-four-remotes-r2.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.40-publish-four-remotes-r2.fish"
end
```

Targets are `github.com/cristiancmoises/securitysearch`,
`codeberg.org/berkeley/securitysearch`, `git.securityops.co/cristiancmoises/securitysearch`
and `git.securityops.com.br/cristiancmoises/securitysearch`.
The publisher pushes `main` and the tag, then reconciles release metadata, source archive
and checksum. Tokens are entered locally and not stored. Conflicting tags/assets and
non-fast-forward histories are refused. Four-host publication is not one atomic transaction;
a failure can leave some hosts updated. Retry the same script or select one `--host`.
Use `--verify-only` for deployed verification without publication.
The stable release flag is metadata, not a guarantee of bug-free or completed 1.0 acceptance.

## Diagnostics, tests and limits

`securitysearch-audit-diagnostics-0.9.40-r2.fish` collects a filtered retained audit report without
building. `--explain-latest` and `--explain-report FILE` read local reports without SSH.
Reports and checksum sidecars are mode 0600; raw queries, provider bodies, arbitrary logs and
credentials are excluded. Collection exit 0 means **collected**, not **audit passed**.
Offline review returns 0 for consistent zero-failure evidence, 1 for a recorded failure,
and 2 for missing/invalid/incomplete evidence. Reports never authorize deployment/publication.

Run `sh scripts/test.sh --keep-going` only in the intended test runtime. Missing Fish,
PHP extensions, Alpine httpd or PHP-FPM are failures, not skips. The audit captures at most
8 MiB with a 1,800-second deadline; uploads and Docker builds are outside that audit deadline.
Historical local logs or synthetic fixtures cannot establish current IONOS acceptance.

The manual `tools/secops-web-benchmark-v3.fish` remains optional and is never run by deployment,
CI or publication. No speedup against other search engines is claimed. The `--profile-only`
mode measures this instance's delivery paths and is not a search-quality benchmark.

External Redlib instances are operated by independent third parties, not by Security Ops.
Restricted Lain/SecOps originals and derivatives remain in the private operator pack and
are excluded from Git/public release assets; the known-media policy is not an AI detector.
The delivered update kit is not a fresh-install source distribution: unchanged fonts,
private artwork and synthetic reconstruction history are not shipped in it.

## Documentation

[Upgrade v0.9.30 → v0.9.40](docs/UPGRADE-0.9.30-to-0.9.40.md) ·
[Operations](docs/OPERATIONS-0.9.40.md) · [Publishing](docs/PUBLISHING.md) ·
[Troubleshooting](docs/TROUBLESHOOTING-0.9.40.md) · [Changelog](CHANGELOG.md) ·
[Release notes](docs/RELEASE-0.9.40.md) · [Audit contract](docs/AUDIT-0.9.40.md) ·
[Performance scope](docs/PERFORMANCE-0.9.40.md) · [Providers](docs/PROVIDERS.md) ·
[License](license.txt).


## Native audit repair r2 (includes r1)

For the 115/4 native-audit failure, use **securitysearch-v0.9.40-deploy-ionos-r2.fish** and the paired **securitysearch-v0.9.40-publish-four-remotes-r2.fish** in place of the original launchers above. Run `--check-only` then `--audit-only`; 119/0 and all live gates remain mandatory. The application version stays 0.9.40. See [r1 repair details](docs/AUDITFIX-0.9.40-r1.md).

Revision r2 also restores configuration after oversized generated output without masking the original fixture error. Each comparison reads at most the captured original length plus one byte. See [r2 cleanup details](docs/AUDITFIX-0.9.40-r2.md). Native 119/0 acceptance is still required.
