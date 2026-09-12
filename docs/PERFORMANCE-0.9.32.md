# Performance scope — 0.9.32

This checkpoint keeps the prepared homepage/CSS/JS delivery from 0.9.31. Its runtime
changes fix asset-response correctness; they are not an asserted Internet speedup.
Native loopback measurements compare whole-resource gzip delivery before/after,
with failures and the exact method recorded in the downloadable evidence kit.

Disk cleanup is a capacity/reliability operation, not a benchmark shortcut. It runs
before Docker build, not against visitors' cache or query data. Single-tag images
are removed by immutable ID; multi-tag, shared and unidentified images are retained.
Build cache, volumes, networks, NPM and filesystem backups are not pruned.

The manual benchmark remains byte-for-byte unchanged. No competitor requests,
leaderboards or winner claims are produced during installation or the automated audit.
Homepage HTTP, browser rendering and successful search latency are separate metrics.
A benchmark that does not meet its qualification rules has no ranked winner.
