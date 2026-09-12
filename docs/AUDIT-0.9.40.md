# Audit contract — v0.9.40

**119 mandatory commands must pass in the actual candidate image.**
The first 115 v0.9.38 commands remain in order, followed by the search-state PHP,
localhost HTTP, v0.9.40 release-contract and v0.9.40 publication suites.
The exact list is [audit-commands-0.9.40.json](../data/audit-commands-0.9.40.json).

Approval requires each command exactly once and in order, successful process/container exits,
matching source/image identity, complete bounded log/execution records and a 119/0 summary.
Log capture retains its 8 MiB/1,800-second limits. Builds/uploads are outside this deadline.
Missing Fish, PHP modules, Alpine httpd or native FPM is a failing prerequisite, not a skip.
A passing partial suite, diagnostic report or exit code alone cannot authorize deployment.

The focused localhost fixture executes the real frontend and music prologue; it is not the
whole music controller. The native controller suite additionally exercises the full music
route with genuine PHP prerequisites. Provider/API responses in regression tests are local
fixtures and are not live-provider or VPS evidence. Four-remote publication tests are simulated.

Normal deployment separately requires actual Google Web and Images, RSS, Binternet and
existing image acceptance. Audit-only excludes these live probes and cannot authorize
publication. The independent publisher rechecks all retained evidence against active production.

The delivered VALIDATION-0.9.40.md and raw log bundle record the executions performed for this
candidate. They are separate from this invariant contract and must distinguish local tests,
missing-runtime failures, historical logs and real IONOS evidence. No native success is implied
merely by this document or a ready-to-run deployment script.

See [operations](OPERATIONS-0.9.40.md) and [troubleshooting](TROUBLESHOOTING-0.9.40.md).
