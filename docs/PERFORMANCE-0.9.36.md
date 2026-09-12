# Performance scope — v0.9.36

This change addresses reproducible visual correctness, not a measured homepage bottleneck.
It replaces unconditional minimum-area selection with bounded metadata-only selection.
At most 32 raw sources per card and 24 cards per page are inspected by this renderer.
Selection is linear in inspected entries, with no sorting, network call, decoder or cache.

A useful undersized preview is preferred to the original when no adequate/unknown preview
exists. The original may be chosen when every preview is known tiny; that request can be
larger or slower. Choosing an adequate preview over a smaller undersized one can also
increase transferred bytes. Metadata area is not compressed byte size, content quality, format safety
or provider availability. Unknown-size URLs are hints. The existing proxy enforces transport
and decoding policy when an image is actually requested.

The unchanged PNG validation path is covered by the retained regression suites. No extension
to that eligibility policy was made. No live timings, Lighthouse score, competitor results
or search-speed percentages were measured in this increment. The original manual benchmark
is byte-identical and is not invoked by the deployment or publication scripts.
