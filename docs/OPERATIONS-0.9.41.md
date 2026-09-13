# Operations — v0.9.41

Start with the [direct v0.9.30 upgrade](UPGRADE-0.9.30-to-0.9.41.md).
The remote is `root@securityops.co:5119`; the existing Docker service is `security-search`.
All local paths remain `~/Downloads`, `~/securitysearch` and the private theme pack
`~/.local/share/securitysearch/operator-themes-v1`.

| Deployment-launcher mode | Local update/commit | Remote activity | Production replacement |
|---|---|---|---|
| `--check-only` | No | None | No |
| `--prepare-only` | Yes, if needed | None | No |
| `--audit-only` | Yes, if needed | Upload, build, network-isolated native audit; then filtered collection | No |
| No mode flag | Yes, if needed | Capacity, guarded targeted cleanup/build/audit/live gates/deployment | Only after gates |
| `--collect-audit` | No | Read retained audit evidence; save local filtered report | No |
| `--cleanup-plan` | No | Read Docker eligibility | No |
| `--profile-only` | No | Observe this instance's existing HTTP delivery paths | No |

Choose exactly one mode. `--prepare-only` is forbidden on the publication launcher.
Deployment and audit require the existing private theme pack; prepare/check/collection do not.
The 2 GiB/10,000-inode capacity floor is not a complete sizing estimate. Builds and uploads
are not covered by the audit's 1,800-second deadline; the audit log is capped at 8 MiB.

Production requires all 124 audit commands, complete execution/log/source/image evidence,
then genuine Google Web and Images, RSS, Binternet and image validation. Missing prerequisites
and external unavailability are not successes. Normal deployment installs the existing
rank-refresh timer; audit-only does not. Benchmarks are not run automatically.

SSH/SCP require known matching host keys. Agent/port forwarding and LocalCommand are disabled.
Do not paste tokens or private configuration into reports. Diagnostic collection filters
recognized failure fields and saves mode-0600 files; it is not an acceptance mechanism.
NPM, ports, volumes, networks, private configuration, backups and protected rollback objects
are preserved. No global cleanup, force-push, reset, rebase, tag movement or private-art upload.

See [troubleshooting](TROUBLESHOOTING-0.9.41.md) and [publication](PUBLISHING.md).
