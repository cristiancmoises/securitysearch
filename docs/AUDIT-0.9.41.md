# v0.9.41 audit contract

The exact 119-command v0.9.40 inventory is preserved as the first 119 entries. Five commands
extend the inventory to **124**: bounded image-label PHP assertions, native Apache homepage
representation cases, the network-free v4 benchmark self-test, release41 contracts, and
publication41 fixtures. New Google diagnostic tests extend the existing gate suite; filmstrip
observer tests extend the existing Node suite. No mandatory test becomes optional.

Use `sh scripts/test.sh --keep-going`. Only all 124 commands, once and in order, a complete
zero-failure summary, successful attach/container exits and matching bounded log evidence can
satisfy the native gate. Log capture remains 8 MiB / 1,800 seconds. A prior 119/0 audit does not
approve this tree. The source/current inventory and historic manifests are separate files.

Six reviewed runtime files have exact reversible deltas in `data/runtime-changes-0.9.41.json`.
Current hashes are checked before restoration of historical bytes for the old preservation
tests. All 232 historic hashes are still checked; unapproved modifications fail reconstruction.
Version/count literals in old release contracts advance only where they describe the current
release. Old expected runtime hashes and previous-command lists remain unchanged.

Native Apache HTTP tests use an isolated loopback listener and real installed Apache modules.
PHP label tests run the real shared rendering code and no provider calls. Node DOM fixtures
exercise the shipped JavaScript but are not a full browser performance trace. Benchmark
self-tests use synthetic measurements and make no Internet calls. Public timing measurements
must be made separately by the operator; a fixture winner is never a measured site winner.

The builder's external validation report records actual executions and unavailable native
prerequisites. That report must not substitute for the serving-image audit. Deployment also
requires genuine Google Web and Images, RSS, Binternet and image gates. A 429 is unavailable,
not a pass; extra diagnostic fields cannot alter that classification.
