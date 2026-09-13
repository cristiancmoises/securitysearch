# Post-success retention

The normal operator performs one server build/audit run, candidate acceptance, cutover and
independent source/image/evidence verification. Only then it starts postdeploy_cleanup.py with
both exact accepted container and image IDs. The cleanup obtains the exclusive deployment lock
again and refuses identity drift or unhealthy production. It does not replace source files or
make provider requests. `--audit-only` never calls it. No automatic pre-build cleanup remains.

Eligibility is established from recognized SecuritySearch image tags (including audit-only tags),
container names, release metadata, and chronological build keys. Lower versions and earlier builds
of the same version are eligible; unknown/newer/future builds are retained. Only exited, created
or dead owned containers are removed, without force or volume flags. Running, paused or restarting
containers are never stopped. Old stopped rollback containers ARE removed after accepted promotion:
the existing rollback.sh may then no longer work. Evidence/private backup directories are preserved.

All images referenced by retained containers, including stopped unrelated containers, are kept.
An image is eligible only if every tag is recognized and older; foreign/shared tags, untagged images
and repository digests are conservatively retained. Each deletion is rechecked; Docker image rm
uses --no-prune and never --force. Shared layers or retained build cache can limit reclaimed bytes.
Do not estimate recovered space by summing Docker image sizes.

Archive scope is ONLY immediate `securitysearch-vX.Y.Z-deploy.tar.gz` uploads and adjacent checksums
within timestamp/commit folders below /root/securitysearch-incoming. The current upload is preserved
using the accepted run's source-directory.txt. If that pointer is unavailable, archive cleanup is
skipped rather than guessed. Symlinks, hard links, writable/untrusted paths and mounted input paths
are refused/preserved. Source trees, backup archives elsewhere, release assets, generated .gz web
assets, credentials, logs, volumes, networks and builder cache are never cleanup targets.

The standalone cleanup launcher defaults to a read-only plan. Explicit `--execute` retires older
objects relative to CURRENT healthy production, not an unaccepted pending version. It may therefore
remove an old stopped rollback but never deletes the current running container. This operation is
not a deployment, and does not promote pending candidates or waive later acceptance checks.

Cleanup cannot be atomic across Docker and filesystems. Completed deletions are not reversed.
Failure after deployment leaves the accepted service running and reports cleanup pending; no more
removals are attempted after a failure. No global prune, forced history change or recursive rm is used.

References: Docker `container rm` and `image rm` CLI documentation, and Git archive tar.umask.
https://docs.docker.com/reference/cli/docker/container/rm/
https://docs.docker.com/reference/cli/docker/image/rm/
https://git-scm.com/docs/git-archive
