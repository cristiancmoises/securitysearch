# SecuritySearch 0.9.32 — development checkpoint toward 1.0.0

Asset version: 36. This is a tested incremental release, not a claim of universal
provider availability or a competitive benchmark win.

## Changes

- Pre-build cleanup removes only identified old, stopped SecuritySearch containers
  and eligible unused single-tag images at least one hour old. The running service,
  its image, newest retained rollback, other projects and all volumes/networks/backups
  remain. Cleanup and cutover use the same deployment lock. Every removal is recorded.
- Gzip asset handling denies encoded direct sidecar paths, does not label error pages
  as compressed successes, and delivers byte ranges from the original representation.
  Whole-resource responses retain prepared gzip without repeated compression work.
- An atomic release receipt is now required before marking cutover committed.
  Failure to persist the receipt invokes the existing rollback transaction.
- The operator accepts the exact corrected 0.9.30 or prepared 0.9.31 tree, or its own
  completed 0.9.32 tree for safe reruns. Deployment and publication remain separate.

No search adapter, query timeout, result count, JavaScript feature or NPM setting is
changed. Native audit and RSS/Binternet/Google Web/Images gates remain mandatory.
No global prune, force-push, tag replacement or backup deletion is added.

Cleanup uses the same deployment lock and verifies that the local Docker daemon
identifies the same production container inspected by the deployer. Unrelated
maintenance tools must cooperate with this lock; it cannot prevent external deletion
by another administrator or an independent cleanup job.
