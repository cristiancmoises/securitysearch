# Performance scope: v0.9.34

The target is repeated cached favicon delivery on search-result pages, not homepage HTML.
The HTTP-homepage benchmark never requests those icons; it remains unchanged and manual.

A native PHP HTTP fixture before the change retransmitted its 744-byte PNG for a conditional
request, with no ETag, and imported proxy transport on a disk hit. After the change, identical
GET bytes receive a weak content validator; matching eligible GET/HEAD returns 304 and no
body, and a disk hit never imports the transport. Requests and headers are NOT eliminated.
Browser freshness lifetimes, cache keys and remote-request budgets stay unchanged.

Metadata checks and hashing add bounded local work. The body saving does not imply a fixed
wall-clock latency improvement. Files are capped at 128 KiB and 256 x 256 pixel metadata.
No CPU/Internet speed multiplier or global fastest claim is made. This does not fix upstream
HTTP 429; Google/Binternet/RSS acceptance remains required on the actual candidate.

Use --profile-only for bounded observations of your own delivery path. Do not conflate local
PHP timing with DNS/TCP/TLS/NPM latency or compare unmatched/failed benchmark samples.
