# Audit scope — 0.9.38

The 110 previous commands remain in order; five mandatory suites extend the inventory to
115. Deployment requires all 115, zero failures, complete transcript evidence and both
process/container completion. Native extensions, Fish, actual serving-image behavior and
Google Web+Images, RSS and Binternet checks are not bypassed.

A local synthetic Docker reproduction against v0.9.37 accepted empty, arbitrary and
one-command summary output when both exit codes were zero. The updated gate rejects each.
This is a reproduced validator defect, not proof of a production incident.

The inventory matches the source runner. Log and inventory SHA-256, execution bounds,
process exit and container state are persisted atomically. The publisher pins the inventory
and independently checks transcript and result. This is not attestation against compromised
root, daemon, image or tests. Checksums are not signatures.

Real child-process tests cover binary streams, exact/overflow limits, silent processes,
pipe-holding descendants, SIGTERM resistance, storage failures and symlink/replacement
refusal. Docker/SSH/provider fixtures are mocked; they are not native acceptance.

The stream cap is 8 MiB, attached execution deadline 1,800 seconds. Build/copy operations
have no new total deadline. Audit containers disable network and daemon log retention.
Exact-container cleanup remains mandatory; partial private host logs remain on failure.

Consult VALIDATION-0.9.38.md and raw logs for executed counts and prerequisite failures.
