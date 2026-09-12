# Audit scope — v0.9.31

All 81 previous mandatory commands are preserved in order. Four additional commands test
static asset preparation (11 methods), native Apache HTTP delivery (9 methods), offline
benchmark CSV analysis (6 methods), and current release publication/package handling.

The preparation and HTTP tests use actual PHP/zlib and Apache event/mod_deflate on loopback.
They are not browser, native Alpine FPM or live VPS tests. Publication tests use real
throwaway Git repositories and simulated remote APIs. No production credentials are used.

The authoring runtime lacks Fish and several PHP extensions required by legacy suites.
The full keep-going audit records failures rather than passing missing dependencies; exact
results and logs are shipped outside the source tree in the update-kit audit directory.
The full native VPS Docker audit remains mandatory before deployment. Provider rate limits
and failures do not qualify as successful Google/RSS/Binternet validation.

Fish wrappers must be syntax-checked with the installed Fish before execution. No heredoc,
special-variable version assignment, /mnt/data checksum or hidden local PHP requirement.
No deploy, remote push, new competitive benchmark or global cleanup is performed by authoring.
