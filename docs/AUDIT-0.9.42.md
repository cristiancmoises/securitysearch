# SecuritySearch v0.9.42

See [README](../README.md), [PT-BR](../README.pt-BR.md), [SkunkyArt](SKUNKYART.md), and
[retention](RETENTION.md) for the full current behavior and complete Fish commands.

Upgrade from the exact clean supported source; preserve Git history and private themes.
Target SSH is root@securityops.co:5119, checkout ~/securitysearch and downloads ~/Downloads.
Normal operation is one native audit followed by live gates, cutover, independent verification and
post-success cleanup. Separate audit-only first is not required. Full candidate acceptance requires
130/0; provider failure never passes. Publish only after DEPLOY COMPLETE using
securitysearch-v0.9.42-publish-four-remotes.fish. No force push or existing tag movement.

## Inventory

All 124 previous suites are retained in order, followed by six new suites for SkunkyArt parsing,
loopback card rendering, scoped retention, one-pass ordering/provider acceptance, release contracts
and current package/publication. Historical hash checks reconstruct only an exact, declared v42
runtime delta before checking original pinned bytes. The native inventory is 130 distinct commands.
No skipped dependency counts as success. Offline fixtures do not stand in for real providers.
