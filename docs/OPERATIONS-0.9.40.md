# Operations — v0.9.40

Start with the [direct v0.9.30 upgrade](UPGRADE-0.9.30-to-0.9.40.md).
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

Production requires all 119 audit commands, complete execution/log/source/image evidence,
then genuine Google Web and Images, RSS, Binternet and image validation. Missing prerequisites
and external unavailability are not successes. Normal deployment installs the existing
rank-refresh timer; audit-only does not. Benchmarks are not run automatically.

SSH/SCP require known matching host keys. Agent/port forwarding and LocalCommand are disabled.
Do not paste tokens or private configuration into reports. Diagnostic collection filters
recognized failure fields and saves mode-0600 files; it is not an acceptance mechanism.
NPM, ports, volumes, networks, private configuration, backups and protected rollback objects
are preserved. No global cleanup, force-push, reset, rebase, tag movement or private-art upload.

See [troubleshooting](TROUBLESHOOTING-0.9.40.md) and [publication](PUBLISHING.md).


## Native audit repair r2 (includes r1)

For the 115/4 native-audit failure, use **securitysearch-v0.9.40-deploy-ionos-r2.fish** and the paired **securitysearch-v0.9.40-publish-four-remotes-r2.fish** in place of the original launchers above. Run `--check-only` then `--audit-only`; 119/0 and all live gates remain mandatory. The application version stays 0.9.40. See [r1 repair details](AUDITFIX-0.9.40-r1.md).

Revision r2 also restores configuration after oversized generated output without masking the original fixture error. Each comparison reads at most the captured original length plus one byte. See [r2 cleanup details](AUDITFIX-0.9.40-r2.md). Native 119/0 acceptance is still required.
