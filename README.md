# SecuritySearch v0.9.42

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

The release identifier is **0.9.42**; static assets use **42**. Images now offers
**DeviantArt via SkunkyArt**, including a native search-bar shortcut, original artwork links,
signed media previews, orientation and AI-label filters, Safe Search, and bounded pagination.
The service origin is `https://skunkyart.securityops.co` (the earlier `securiyops.co` spelling
was not used). SkunkyArt owns its DeviantArt authentication; no API secret is requested by SecuritySearch.
Provider availability and metadata are not guaranteed.

A normal deployment now explicitly performs **one native audit → live gates → cutover →
independent verification → old-build cleanup** in one invocation. There is no mandatory
separate audit-only invocation, and no pre-build deletion. Automatic retention removes recognized
older stopped containers, including the old rollback, unreferenced owned images and old upload
`.tar.gz` files. **The old container rollback option is lost after successful cleanup.**
Running services, images used by retained containers, volumes, networks, extracted source,
private backups, source history and NPM stay outside cleanup scope.

The existing v4 benchmark ranks **TTFB**; total HTML time is separate. The original v3 remains
byte-identical. Bounded labels, observer-based image scrolling, safe static-home delivery and
Google's sanitized traces are retained. No public-site or universal speed win is claimed.

All **124** v0.9.41 audit commands remain an exact prefix; six additions bring this audit to **130**.
Historical v0.9.40 extended its inventory to 119. The previous 124/0 native result is not v0.9.42 acceptance.
Every new candidate requires **130/0** and genuine Google **Web and Images**, RSS, Binternet,
and SkunkyArt acceptance. HTTP 200 containing a provider error is not successful search.

## Upgrade, audit and deploy

Use a full, clean `main` checkout in `~/securitysearch`; downloads remain in `~/Downloads`.
The observed GitHub baseline is v0.9.40-r2, tree `b0ab9966f02dd0c41ad8f936347b64cee5642c9f`.
The cumulative updater also supports corrected v0.9.30 tree
`488b01ae481e8e4e95dad3743c2a175c43065c97` plus the exact original v0.9.41 and v0.9.41-r1 trees.
It checks the tree, not just a version string. Unknown, shallow, non-main or dirty work is refused.
It does not reset, stash, rebase, amend commits, force-push or discard existing work.

Local requirements are Fish, Python 3, Git, OpenSSH and coreutils. Deployment needs no local PHP or
Docker. It uses `root@securityops.co:5119`, the existing `security-search` Docker container and the
private pack `~/.local/share/securitysearch/operator-themes-v1`. Keep SSH host-key verification enabled.

Save the self-contained launcher and matching checksum in `~/Downloads`, then run:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.42-deploy-ionos.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.42-deploy-ionos.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.42-deploy-ionos.fish" --check-only
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.42-deploy-ionos.fish"
end
```

`--check-only` is read-only with no SSH. `--prepare-only` creates only the ordinary local update commit.
The default invocation uploads/builds once and audits once on IONOS, then automatically proceeds
through genuine provider checks, production replacement, independent verification and retention.
It does not publish Git refs. There is no need to run `--audit-only` first.

`--audit-only` remains available for deliberate investigation. It may commit/upload/build but does
not replace production, contact providers or remove old objects. A later normal deployment is a
new execution and repeats its audit rather than reusing a partial receipt.

A 429, CAPTCHA, empty or fallback result, missing prerequisite, source drift or partial log blocks
cutover. Old containers/images/archives are not cleaned on an unsuccessful candidate run. After
verified success, partial cleanup is reported separately and never triggers rollback of an accepted
service after the old rollback has been deleted. There is no global prune or forced image removal.
See [retention policy](docs/RETENTION.md) for exact filename and resource boundaries.

## Commit and publish to four remotes

Only after `DEPLOY COMPLETE`, use the matching new publisher:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.42-publish-four-remotes.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.42-publish-four-remotes.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.42-publish-four-remotes.fish"
end
```

The publisher independently verifies deployed source/image identity and complete current evidence
before pushing `main`, immutable annotated `v0.9.42`, release notes and source/checksum assets to
GitHub `cristiancmoises/securitysearch`, Codeberg `berkeley/securitysearch`, and
`git.securityops.co/cristiancmoises/securitysearch` and `git.securityops.com.br/cristiancmoises/securitysearch`.
Tokens are entered privately, not saved or embedded in URLs. Conflicting tags/assets are refused.
Four-host publication is not atomic; matching partial work can be reconciled with `--host`.
`--verify-only` verifies without publishing. The published
v0.9.24 tag and later historical tags are not moved.

## Diagnostics, benchmark and privacy

The standalone `securitysearch-audit-diagnostics-0.9.42.fish` collects allowlisted audit evidence;
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

[Operations](docs/OPERATIONS-0.9.42.md) · [Publishing](docs/PUBLISHING.md) ·
[Audit](docs/AUDIT-0.9.42.md) · [Troubleshooting](docs/TROUBLESHOOTING-0.9.42.md) ·
[Release notes](docs/RELEASE-0.9.42.md) · [Changelog](CHANGELOG.md) · [License](license.txt).
