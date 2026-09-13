# SecuritySearch v0.9.42

See [README](../README.md), [PT-BR](../README.pt-BR.md), [SkunkyArt](SKUNKYART.md), and
[retention](RETENTION.md) for the full current behavior and complete Fish commands.

Upgrade from the exact clean supported source; preserve Git history and private themes.
Target SSH is root@securityops.co:5119, checkout ~/securitysearch and downloads ~/Downloads.
Normal operation is one native audit followed by live gates, cutover, independent verification and
post-success cleanup. Separate audit-only first is not required. Full candidate acceptance requires
130/0; provider failure never passes. Publish only after DEPLOY COMPLETE using
securitysearch-v0.9.42-publish-four-remotes.fish. No force push or existing tag movement.
