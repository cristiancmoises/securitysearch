# Performance scope — 0.9.37

This increment reduces duplicated deployment-resource bytes. It does not alter visitor
request execution and does not claim faster search, better provider availability or a
homepage benchmark win. The asset version remains 40; no UI cache invalidation is needed.

A same-input prototype using all nine v0.9.36 resource files reduced the embedded base64
resource payload from 7,858,712 to 2,244,176 bytes, with exact decoded byte equality. These
are locally measured packaging sizes, not network transfer or website latency. The final
v0.9.37 operator sizes are recorded separately in the delivery's operator-build.json.

Deduplication avoids repeating identical file diffs, but reconstruction still needs the
complete patches in memory. Explicit unique, per-file, aggregate, JSON and input limits
bound that work. The 8 MiB decoded-operator limit is not raised to accommodate growth.

The original manual tools/secops-web-benchmark-v3.fish remains byte-identical and manual.
No competitive benchmark, provider request, query cache or speculative fan-out is added.
