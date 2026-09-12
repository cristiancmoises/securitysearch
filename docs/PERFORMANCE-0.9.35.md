# Performance scope — v0.9.35

The change targets small opaque PNG image previews, not homepage delivery. Eligible images
avoid ImageMagick pixel-cache allocation, resizing and lossy JPEG encoding. Their original
bytes are delivered, which can be larger or smaller than the previous JPEG; the eligibility
limit is 32 KiB. Two capped zlib passes validate the complete stream and add bounded local
work. Do not infer a latency percentage without timing the actual serving runtime.

The controller fixture uses fixed upstream data and a sentinel at the conversion branch.
It proves selected routes, exact PNG bytes and early rejection of upstream errors. It does
not claim a real remote fetch or an Imagick conversion in the sentinel configuration.
The separate native ImageMagick suite requires that extension and remains a VPS audit gate.

Providers, bootstrap behavior, timeouts, NPM and the homepage benchmark remain unchanged.
No query/result cache, hidden fan-out or benchmark-client shortcut is added. Use the manual
benchmark or --profile-only after deployment for measurements of the appropriate scope.
