# SecuritySearch v0.9.33 / asset 37

Development checkpoint toward 1.0.0. No new public speed-win or provider-availability claim.

## Changes

Pre-deployment Docker inspection uses validated batches of 16 immutable IDs, avoiding a
separate process per object. A deterministic 120-image call trace drops from 121 to 9
commands (Docker is simulated; no network latency claim). Missing, duplicate and unexpected
batch records fail closed.

Rollback preservation uses the retirement timestamp encoded in the generated rollback name.
Creation time was incorrect for deciding which formerly running container was retired most
recently. Ambiguous, future and tied dates are preserved.

A production fingerprint, held only in memory, detects changes to container identity,
restart state, configuration, mounts and networks before cleanup and just before cutover.
It prevents observed stale state from being used; it is not atomic against unrelated Docker
clients after the final observation. Existing receipt ordering and rollback remain necessary.

## Preserved

All search adapters, query budgets, normal provider fallback, private response policy,
local pictures, animation, themes, runtime PHP/Apache configuration and benchmark script
are unchanged. The asset/version markers change, but no browser feature is removed.
No NPM, global prune, volume/network/build-cache or backup deletion is introduced.

## Operations

Use the matching separate self-contained Fish deploy and publish files from ~/Downloads.
Cleanup occurs before the build only for eligible old project objects; current production
and newest rollback stay. A later build/provider failure does not undo completed old-object
cleanup. Native offline audit, RSS, Binternet and direct Google Web/Images remain mandatory.
Publish only after deployment/evidence verification. Existing tags and conflicting files
are preserved. Publication uses the actual operator commit, not authoring fixture history.

See AUDIT-0.9.33.md, PERFORMANCE-0.9.33.md and ROADMAP-1.0.0.md for scope and limitations.
