# v0.9.33 audit scope

The required runner contains all 89 previous commands unchanged in order, followed by four
new entries (93 total). Existing transaction tests additionally model production drift.

New coverage: complete batched Docker inventory, invalid/missing/duplicate/unexpected rows,
chronological rollback selection, future/ambiguous/tied dates, runtime identity/configuration
fingerprints, source/release identity and tagged publication packages. Public provider APIs
are not contacted by these offline fixtures. Simulated Docker tests are not native Docker
passes. Guard comparison errors contain section names, never private configuration values.

Run `sh scripts/test.sh --keep-going` in the serving image's isolated audit environment.
The delivered update kit records the exact source tree, individual actual command exits,
raw bounded logs, reproduction and mutation results. Missing capabilities remain failures,
not silently accepted tests. The IONOS native gate and live RSS/Binternet/Google checks remain
mandatory. The authoring environment does not provide Docker, Fish or all native PHP/Alpine
components; see the accompanying VALIDATION.txt for the actual execution results.

The Fish payload is independently hashed and decoded without shell heredocs or oversized
argument transport. Native Fish syntax must pass on the operator machine before execution.
Local screenshots are Chromium renderings of actual PHP output with resources embedded and
network blocked, not proof of production navigation or performance.
