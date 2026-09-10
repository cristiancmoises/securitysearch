# v0.9.21 operations and publication

## Preconditions

Existing clean main checkout at the published v0.9.20 tree
`5b37bce9c534389362f4f14169ff826ca58cceb8`, containing commit
`7f9f25a2f56a2b2edb1189e076da29895f95c057`. Python 3.10+, Git and fish locally;
Docker and Python on the existing IONOS VPS. No new local PHP installation is
required to apply the kit. Native PHP verification is mandatory on the server.

Verify the downloaded outer SHA-256, extract the directory intact, then run:

```fish
fish ./apply-securitysearch.fish ~/securitysearch
fish ./deploy-securitysearch.fish ~/securitysearch --rank-refresh
fish ./publish-securitysearch.fish ~/securitysearch
```

The apply helper validates kit/source hashes and tree, commits only the patch,
and never resets, stashes or discards unrelated work. Stop and review any baseline
conflict. A failed commit hook leaves the update staged and does not continue.
Existing ignored operator configuration is not packaged by Git archive.

The deploy helper archives committed source, uploads by OpenSSH/scp **5119** to
**root@securityops.co**, and invokes the preserved guarded Docker updater. Host-key
verification is not disabled. Full isolated tests run with no production secrets,
volumes or external networking; the later candidate/live Binternet gates can
contact the provider. The old container remains until all pre-cutover gates pass.
An incompatible network/mount/port layout is refused. Replacement-readiness failure
attempts rollback. Keep every printed backup directory; a running container may
mount private snapshots from it. Nginx Proxy Manager is not recreated.

`--rank-refresh` optionally installs `securitysearch-tranco.service/.timer` after
successful deployment, only on systemd hosts. Existing different units are
preserved. It runs Docker exec as apache in the application container, once now
and daily with randomized delay. A metadata failure does not roll back the app;
inspect `journalctl -u securitysearch-tranco.service`. Re-enable later with:

```fish
fish ./enable-rank-refresh.fish ~/securitysearch
```

Disable the optional refresh with `systemctl disable --now securitysearch-tranco.timer`
on the VPS. Cache freshness is bounded to three days; unverified/missing/stale
rank displays unavailable. Page rendering never fetches Tranco. This is not a
visitor counter, quality score or request log.

## Release only, all hosts or one

The publisher runs the publication regressions, creates/reuses annotated v0.9.21,
and packages the exact real local commit into `~/securitysearch-release-v0.9.21/`.
To prepare without any token prompt or remote action:

```fish
fish ./publish-securitysearch.fish ~/securitysearch --prepare-only
```

To publish just one host, including source archive and checksum:

```fish
fish ./publish-securitysearch.fish ~/securitysearch --host git.securityops.co
```

Omit --host to select all four. Repeat --host for a subset. The fixed allowed
hosts are github.com, git.securityops.co, git.securityops.com.br and codeberg.org.
The three first use cristiancmoises; Codeberg uses berkeley. Existing repos/main
branches and token repository/release write access are required. The API version,
proxy health and permissions are verified when the user runs publication.

Matching files/drafts are reused. A failed create/upload response is reconciled
on repeat; do not delete tags or rebuild different archive bytes to retry. The
preflight must pass for every selected host before any selected host is written.
After writing starts, some hosts can succeed while others remain pending. Per-host
main/tag push is atomic; independent hosts are not globally atomic. Published
incomplete or conflicting releases require manual review, not destructive repair.

The publisher uses Forgejo multipart attachment fields, not GitHub raw upload
semantics, and only treats an actual not-found response as absence. It verifies
existing attachment contents rather than merely comparing filenames. Tokens stay
in process memory and temporarily a child Git environment on the trusted client;
they are not saved in URLs, files, history or command arguments. Do not paste them
into logs. No token is needed on the VPS.

## Privacy and diagnostics

Redlib may forward a failed query to the disclosed external instances. Disable
with FOURGET_REDLIB_FALLBACKS=false in the existing operator configuration. It is
not an automatically discovered arbitrary URL list. No query-bearing cache is
added; a sixty-second public blank-feed cache is the sole result-cache exception.

My picture is optional JavaScript with no upload endpoint. It normalizes JPEG/
PNG/WebP locally and uses tab storage unless the user explicitly remembers it.
The origin's other scripts and browser profile can read that storage; no encrypted
vault claim is made. Remove clears both storage locations. Selecting Black alone
does not delete a previously remembered Custom picture; use Remove first.

`fish ./audit-providers.fish` performs one neutral `teste` query per enabled
provider/type sequentially from the VPS and copies back a JSON report. Exit 2
means at least one provider was empty/unavailable, not a clean audit. It is a
smoke test, not an all-query or percentile benchmark. Never bypass failed gates.
