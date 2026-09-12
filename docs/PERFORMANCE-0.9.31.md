# Performance scope — v0.9.31

The default anonymous homepage already bypassed PHP and had prepared gzip in v0.9.30.
This iteration does NOT introduce that feature again. It extends prepared compression to
fixed CSS/JS and avoids the unnecessary dynamic route for gzip;q=1 homepage requests.

The original benchmark launches fresh curl processes and transfers only homepage HTML.
It does not download CSS/JS. CSS/JS work reduction benefits asset delivery/browser visits,
not a fabricated improvement to that benchmark. Level-9 homepage compression only affects
a small transfer component. Most public latency may remain DNS/TCP/TLS/proxy/queue time.

Local evidence: native Apache event + mod_deflate on loopback, actual style.css bytes,
fresh curl per request, 8 excluded warmups and 80 randomized paired measured rounds.
Dynamic gzip: median TTFB 0.8535 ms / total 0.8730 ms / payload 6136 bytes.
Prepared gzip: median TTFB 0.3025 ms / total 0.3255 ms / payload 6123 bytes.
These are local fixture observations, NOT public latency or a benchmark against 4get.
Source style.css is unchanged and both responses decompress to the exact same bytes.

Raw evidence is shipped in the separate update-kit audit directory, not as a new leaderboard.
Keep network/browser/search comparisons distinct. No proof of a public performance win.
