# v0.9.33 performance scope

This iteration reduces operator maintenance overhead, not browser or provider search time.
For 120 images, the inventory code needs 1 list + 8 batched inspect invocations instead of
1 list + 120 single-object invocations. This is a deterministic command-count observation
using simulated Docker responses. It is not 92.6% faster searching or a measured Docker
wall-clock reduction. Deletion-time guards and usage checks are retained, not optimized away.

The previous public benchmark did not qualify a winner. CSS/JS precompression, PHP-FPM and
anonymous-home optimization remain; no new competitor requests or Lighthouse run was made.
The existing manual benchmark is unchanged. Use the optional own-site profile to distinguish
DNS/TCP/TLS/proxy latency from PHP processing before changing unrelated infrastructure.

Cleanup only targets the explicitly recognized stopped/unreferenced old project objects.
No faster benchmark response is obtained by fewer results, hidden fallbacks or cache leaks.
