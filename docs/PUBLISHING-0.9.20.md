# Publish SecuritySearch v0.9.20

## Requirements

Use a clean, complete `main` checkout containing v0.9.20 **and the r1 audit repair**.
The publication kit verifies its exact known files before changing anything.
Do not run `git init` inside an extracted source package to manufacture history.
Python 3.10+, Git, fish and `sha256sum` are required locally. The publisher uses
Python's standard library; no `gh`, `tea`, curl or pip dependency is required.

All four repositories and their `main` branches must exist:
`git.securityops.co/cristiancmoises/securitysearch`,
`git.securityops.com.br/cristiancmoises/securitysearch`,
`github.com/cristiancmoises/securitysearch`, and
`codeberg.org/berkeley/securitysearch`.

## One launcher

From the extracted publication kit:

```fish
fish ./publish-securitysearch-v0.9.20.fish ~/securitysearch
```

The first run updates and commits only the release documentation and publication
tools. It preserves the application release number and r1 runtime files. A dirty
checkout, unexpected file content, shallow history, failed commit hook or existing
different tag stops the process. Nothing is reset or stashed. If a commit hook
fails, intended changes remain staged for inspection; no tag/push is attempted.

Publication regression tests run locally before tag creation. The annotated tag
`v0.9.20` is created at the final documentation commit, never at the older README
commit. No existing tag is moved. Your Git identity and normal local commit/tag
signing configuration are used; the tool does not claim signatures by default.

The default output directory is `~/securitysearch-release-v0.9.20` when your source
is `~/securitysearch`. It is **outside** the checkout. It contains the source
archive, its checksum, an incremental recovery bundle plus checksum, release
notes, `release-manifest.json` and `SHA256SUMS`. The bundle preserves commits after
`9f90eec4d30a713423b29ec6ef54a5f32e437185` (v0.9.19); that baseline must be present
when importing the bundle. Only the source archive and its checksum are uploaded
as release assets.

## Tokens and remote state

Enter a separate token for each host in the terminal. No token is saved to a
file, URL, command argument or Git remote configuration. Tokens are in process
memory and briefly in the Git child environment; processes with sufficient local
privilege can inspect that environment. Use a trusted local machine.

GitHub requires access to this repository with Contents write; Forgejo requires
repository write access. Existing branch/tag protections still apply. The kit
does not create repositories or change their settings. Git credential prompts
are bound to the exact host and repository path. TLS verification stays enabled;
API redirects do not receive credentials at unrelated hosts.

All four repositories, branch ancestry, tags, release notes and existing assets
are preflighted before any remote write. A failed preflight stops the batch.
Successful dry-runs cannot guarantee server hooks/policies will accept the real
push. Each real `main`+tag push uses `--atomic` and no force. The API then creates
or resumes a matching draft, uploads missing assets, verifies their SHA-256, and
publishes only after both attachments are present.

If a later network/server failure leaves partial progress, rerun the **same
launcher with the same checkout and output files**. Successful hosts and uploaded
assets are verified and retained. Different tag objects—even at the same commit—,
release notes, names or asset bytes stop publication rather than being replaced.
Do not delete or recreate the local tag to retry. If a remote main has advanced
past the tagged commit, stop and review; the tool will not rewind it.

## Local-only preparation and inspection

```fish
fish ./publish-securitysearch-v0.9.20.fish ~/securitysearch --prepare-only
```

This still creates the local documentation commit, annotated tag and packages,
but requests no tokens and performs no remote writes. Review with:

```fish
git -C ~/securitysearch show --stat v0.9.20
git -C ~/securitysearch show v0.9.20:docs/RELEASE-0.9.20.md
cd ~/securitysearch-release-v0.9.20
and sha256sum -c SHA256SUMS
```

For an already prepared source tree, package-only and publish-only commands are:

```fish
cd ~/securitysearch
and python3 scripts/package-v0.9.20.py --output ~/securitysearch-release-v0.9.20
and python3 scripts/publish-v0.9.20.py --assets ~/securitysearch-release-v0.9.20
```

## Deployment remains separate

This launcher never contacts the VPS over SSH and never restarts a service. A
successful publication is not proof of a successful full application audit or
deployment. The existing r1 audit limitations remain in the release notes.

The README/tool commit changes exact file hashes, so the original r1 deployment
kit will refuse it. For a later deployment use `deploy-securitysearch.fish` from
**this** publication kit, whose manifest includes only these verified publication
changes on top of r1. Its runtime gates, backup policy, SSH target and port remain
unchanged. There is no need to redeploy merely to publish README/tag/release data.

## API references

The publisher follows the official GitHub Releases and Release Assets APIs and
the Forgejo-compatible repository release/attachment endpoints. Individual
Forgejo servers publish their instance schema at `/swagger.v1.json`.

- https://docs.github.com/en/rest/releases/releases
- https://docs.github.com/en/rest/releases/assets
- https://forgejo.org/docs/latest/user/api/usage/
- https://docs.gitea.com/api/1.22/operations/repo-create-release-attachment/
