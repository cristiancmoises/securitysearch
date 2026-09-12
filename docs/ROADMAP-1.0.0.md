> **Current v0.9.40 operations:** [documentation index](INDEX.md) and [direct upgrade from v0.9.30](UPGRADE-0.9.30-to-0.9.40.md). Existing IONOS installations should use the guarded update, not generic fresh-install commands. Older version-specific examples below are reference/history, not current acceptance evidence.

# Road to 1.0.0

v0.9.38 is a development checkpoint, not a substitute for release acceptance.

New: bounded lossless operator deduplication and explicit HTTP native-prerequisite failures.
The reproduced local controller 500 came from unavailable mbstring, not a proven production defect.

Completed development work: guarded old-object cleanup, correct rollback chronology,
reduced inventory process count, pre-cutover drift rejection, and durable receipt ordering.
Cached favicon revalidation and bounded regular-file reads are also covered by native PHP HTTP tests.
Bounded opaque PNG relay and upstream-error-image rejection now have focused fixtures.
Native ImageMagick fallback and live image acceptance must still run in the candidate.
Usable thumbnail selection now rejects tiny alternatives when a useful source is supplied,
with a 32-entry source scan bound and HTML/JSON fixture parity.
Search providers and budgets are preserved rather than rewritten without evidence.

Next priorities: run native audit in the actual Alpine/FPM image; inject stateful failures
at the remaining cutover boundaries; verify first-attempt Google web/image availability;
validate image previews; profile the real NPM/TLS/network path without changing competitors'
benchmark eligibility or privacy rules. Resolve observed failures before a 1.0.0 claim.

Release requirements: cumulative verified-source upgrade; separate self-contained Fish
operators in ~/Downloads; source/tag/tarball agreement; truthful retained host evidence and
runtime verification; no private artwork/credentials in public releases; rollback preserved.
No claim of globally fastest search without a matching, reproducible, qualified measurement.

Implemented in v0.9.38: complete audit evidence, bounded execution logs and separate
audit-only mode. Next: actual IONOS 115/0 audit, resolve native failures, then genuine
provider and image acceptance before considering any further visitor-path changes.
