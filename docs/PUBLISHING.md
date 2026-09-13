# Publication — v0.9.41

This is the current guide. Older numbered publishing documents are historical.
Start from the [direct v0.9.30 upgrade](UPGRADE-0.9.30-to-0.9.41.md).

## Commit locally

The verified deployment launcher creates a normal commit on `main` before audit/deploy.
To do only that step, run:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-deploy-ionos.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish" --prepare-only
end
```

This does not run SSH, require private themes, build, tag or push. It applies only the exact
reviewed cumulative patch, not `git add .`. Unknown/dirty/shallow/non-main source is refused.
An already-applied target creates no duplicate commit. Failure leaves your work for inspection.
Use your configured Git identity/signing; no identity or signing setting is changed by the launcher.

## Deploy and independently verify

Run the native audit and normal deployment first. Do not publish after `--prepare-only`,
`--audit-only`, a collected report, a green health endpoint alone, or a historical passing log.
Require `DEPLOY COMPLETE`. The publisher rechecks the active commit/tree, runtime hashes,
image, complete 124-command transcript/execution and retained Google Web/Images/RSS/Binternet evidence.

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-publish-four-remotes.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
end
```

## Targets and artifacts

| Host | Repository |
|---|---|
| GitHub | `cristiancmoises/securitysearch` |
| Codeberg | `berkeley/securitysearch` |
| `git.securityops.co` | `cristiancmoises/securitysearch` |
| `git.securityops.com.br` | `cristiancmoises/securitysearch` |

The publisher pushes `main` and an immutable annotated `v0.9.41` tag, then verifies/reconciles
a non-prerelease release, `securitysearch-v0.9.41.tar.gz` and its SHA-256 asset.
Packaging happens on your real source history, not this assistant's synthetic reconstruction.
The incremental recovery bundle retains the existing v0.9.30 base and is local recovery material;
the normal public release assets are the source tarball and checksum.
No earlier tag is moved. Existing conflicting tags or assets are preserved and block publication.
No force-push, reset, stash, rebase, remote repository creation or blanket staging is used.
Known restricted originals/derivatives and planning prompts are checked before release packaging.

Tokens are entered at local prompts; they are not saved, put into source/URLs, or printed.
TLS validation stays enabled and credential redirects are refused. The four hosts do not
form a distributed atomic transaction. Some may finish before another fails; the script
returns failure rather than claiming all succeeded. Retry the same script with unchanged
commit/tag/assets, optionally `--host git.securityops.com.br`. Matching partial work is reused;
conflicts are never overwritten. `--verify-only` performs deployed verification without a push.

References: [Git push semantics](https://git-scm.com/docs/git-push),
[Fish syntax-check mode](https://fishshell.com/docs/current/cmds/fish.html).
