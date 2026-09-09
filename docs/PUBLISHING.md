# Publish SecuritySearch v0.9.19

The publication kit contains the complete source archive and SHA-256, an incremental Git bundle and SHA-256, bilingual release notes, deployment script/guides, this guide and `publish-securitysearch-v0.9.19.fish`. The bundle includes the Codeberg deletion commit plus the new release, based on known commit `34ac6854420a0dd41b05b1c558ce2d879068bf23`. It is intended for the existing `~/securitysearch` checkout, including a checkout already fast-forwarded to `0751f14`.

## One fish command

After verifying and extracting the publication kit, run from its directory:

```fish
fish ./publish-securitysearch-v0.9.19.fish ~/securitysearch
```

Requires Git, fish, Python 3 and sha256sum. The launcher checks pinned archive/bundle hashes, requires clean `main`, verifies the bundle and tag identity, saves a backup branch and fast-forwards to the release commit. It preserves the existing repository history. A detached, dirty or diverged checkout stops with an explanation. No automatic reset, stash or merge conflict resolution is performed. The complete source archive alone has no Git metadata; use the bundle to update an existing Git checkout.

The imported publisher then prompts separately for each token. Paste tokens into the hidden terminal prompt, never into this document or a remote URL. Tokens are kept in memory; the temporary Git authentication helper contains no token. The publisher disables credential storage and verbose HTTP tracing. Local Git author information belongs to the existing release commit; tokens authenticate remote writes.

| Host | Repository | Token access |
|---|---|---|
| codeberg.org | berkeley/securitysearch | repository write |
| github.com | cristiancmoises/securitysearch | Contents write; workflow permission if required by changed workflow files |
| git.securityops.co | cristiancmoises/securitysearch | repository write |
| git.securityops.com.br | cristiancmoises/securitysearch | repository write |

All four repositories must already exist. The publisher uses verified HTTPS and validates fixed API/upload destinations. It pushes `main` and annotated `v0.9.19` with ordinary Git updates, then verifies the remote tag commit before touching its release. A newer/diverged branch or conflicting tag stops that host; the remaining hosts are still attempted.

## Release files and retries

Each hosted release receives `securitysearch-v0.9.19.tar.gz` and `securitysearch-v0.9.19.tar.gz.sha256`. The source archive must match the local tagged source. The publication kit/bundle are operator handoff files, not hosted runtime assets. New releases begin as drafts and become public only after both assets are verified. Existing identical assets are reused; missing assets can be uploaded on rerun. Different content under an existing name or tag is reported, never replaced automatically. Partial failures retain the local checkout and any unfinished draft.

Rerun the same launcher after a temporary service error. To publish from an already imported clean checkout, with source files still in the kit directory:

```fish
python3 ~/securitysearch/scripts/publish-release.py --assets /absolute/path/to/securitysearch-publication-v0.9.19
```

Do not paste tokens in diagnostic output. A 404 can indicate a missing repository or insufficient access; a gateway error alone does not prove an invalid token. Review the host's reported result. Public releases are not created in the authoring environment because the tokens are entered on your computer when you run this command.

## Rebuild and deploy

From a clean source checkout at the release commit, create an annotated `v0.9.19` tag if it does not already exist, then:

```sh
./release.sh 0.9.19
python3 scripts/build-publication-kit.py
```

The archive is built from tagged Git HEAD. Generated archives/bundles stay in `dist/` and are excluded from the application image. README screenshot links use a bundled relative JPEG, so they work on all four forges.

Publishing Git releases does not deploy the VPS. Use the included `scripts/deploy-ionos.fish` with the source archive/checksum in `~/Downloads` for the separate IONOS update. It keeps SSH 5119, root@securityops.co, the existing private port binding and rollback. See [operations](OPERATIONS-0.9.19.md).

API references: [GitHub releases](https://docs.github.com/en/rest/releases/releases), [GitHub assets](https://docs.github.com/en/rest/releases/assets), [Forgejo token scopes](https://forgejo.org/docs/latest/user/authentication/token-scope/), [Codeberg OpenAPI](https://codeberg.org/swagger.v1.json).

Verification accepts signed downloads from known GitHub asset hosts without forwarding the token. A Forgejo redirect to an unknown external storage host leaves that host pending; review its storage configuration before retrying.
