# Performance scope — 0.9.38

No visitor-path changes or search-speed measurements. The 232-file preservation manifest and
asset 40 remain unchanged. The original manual competitive benchmark was not executed.

Audit output is streamed in small reads into a private file, capped at 8 MiB. A bounded
copy is read for validation afterward; this is not an 8 MiB total-memory claim. Attached
audit execution is bounded to 1,800 seconds; image builds/source transfer are not. Builds
consume CPU, RAM, disk and network even with audit-only. No old-object cleanup occurs in
that mode; repeated runs intentionally retain source, images and evidence.

Schema-2 deduplication, 8 MiB operator and 16 MiB launcher limits remain. Final package
measurements are in the review kit's operator-build.json, not website speed measurements.
