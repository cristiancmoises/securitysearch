# v0.9.22 — application, deployment, themes and publication

The kit accepts either the exact applied v0.9.21 tree or the published v0.9.20
publication tree. It refuses unrelated local changes instead of resetting/stashing.
Application creates one ordinary commit with your configured author identity.

```fish
cd ~/Downloads
and sha256sum -c securitysearch-update-0.9.22.tar.gz.sha256
and tar -xzf securitysearch-update-0.9.22.tar.gz
and fish ~/Downloads/securitysearch-update-0.9.22/apply-securitysearch.fish ~/securitysearch
```

## Recover the operator-only historical themes

```fish
fish ~/Downloads/securitysearch-update-0.9.22/restore-theme-assets.fish \
    --repo ~/securitysearch \
    --output ~/.local/share/securitysearch/operator-themes-v1
```

This explicit command reads the pinned originals from local Git first. If absent,
it tries the exact Codeberg historical URL, then the GitHub mirror at the same
commit. Original Git blob IDs and file sizes must match. It needs Python + Pillow;
on Guix the fish launcher uses `guix shell python python-pillow` if needed. No
`pip install` or credentials are used. This fetch occurs only during operator
preparation, not when a visitor selects a theme. The source checkout is not changed.
Existing complete packs are verified and retained; different/incomplete output
is refused. Do not place this directory inside Git or attach it to forge releases.

The private pack contains only six derivatives and a manifest. Output is bounded
at 640×400, at most 90 sampled frames, still previews at 240×135 below 16 KiB;
frame durations are combined when sampling. It is a visual optimization, not a
byte-identical replay of every source frame. Original input accepts GIF/animated
WebP, at most 600 frames and eight megapixels. An unsupported input stops safely.
Raw originals are temporary; only normalized derivatives remain in the pack.

## Deploy

```fish
fish ~/Downloads/securitysearch-update-0.9.22/deploy-securitysearch.fish \
    ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Only the temporary private deployment archive receives the verified pack. The Git
checkout, source tarball and incremental bundle remain clean. The remote updater
verifies the pack again before creating containers. Omitting `--theme-assets`
uses public palette/Matrix fallbacks; pass it again on later deployments to retain
the historical appearance. No custom visitor pictures are collected by this process.

SSH: root@securityops.co, port 5119. Preserve the current 172.17.0.1:5140→80 binding,
Docker networks and NPM upstream. All existing offline/native/candidate/Binternet
checks remain mandatory. Do not bypass the gate or delete deployment backups.
The log `/root/securitysearch-backups/20260910T143929Z/offline-audit.log` records the
reported failed v0.9.21 attempt. Test-suite simulations are labelled and do not
establish a production cutover. A new attempt creates its own retained backup.
The rank timer remains optional and is installed only after successful deployment.

## Publish v0.9.22 and release files

```fish
fish ~/Downloads/securitysearch-update-0.9.22/publish-securitysearch.fish ~/securitysearch
```

To resume only the previously failing host, including its tarball and checksum:

```fish
fish ~/Downloads/securitysearch-update-0.9.22/publish-securitysearch.fish \
    ~/securitysearch --host git.securityops.co
```

The four-host publisher uses private token prompts, non-force atomic main/tag
pushes per host, verified draft attachments and resumable publication. Never move
an existing tag or replace conflicting assets to make a retry pass. Different
hosts cannot be published as a single globally atomic transaction. Tokens stay
out of argument lists, URLs and saved files; they exist in trusted process memory
and briefly in the Git child environment. No deployment is performed by publishing.

Outputs: ~/securitysearch-release-v0.9.22/ . Only the source `.tar.gz` and its
`.sha256` are uploaded. The incremental recovery bundle excludes published v0.9.20
history and remains local. The policy inspects all new trees; an original image
added then deleted in a new intermediate commit is still refused. It cannot certify
unknown arbitrary artwork or retroactively purge old published objects.

## Live availability

```fish
fish ~/Downloads/securitysearch-update-0.9.22/audit-providers.fish
```

One neutral query per provider/page is an availability sample, not a latency SLA or
proof every query works. Exit 2 indicates empty/unavailable provider results.
