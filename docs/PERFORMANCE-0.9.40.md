# Performance scope — v0.9.40

The current runtime edits correct query state and HTTP errors. They do not establish an
end-to-end speedup. No new provider fanout, query cache, image probe, JavaScript/CSS dependency
or concurrent provider request is added. Music buffering delays flushing until render completion
and consumes memory proportional to the HTML response; it preserves headers, not latency.

The cumulative v0.9.30 upgrade retains earlier bounded static-delivery/favicon and image
preview work. Selecting a usable image rather than a 1-pixel placeholder may increase bytes.
Provider dimensions are metadata, not proof of content/download size. Existing image-card
limits, priorities, quality controls and original links remain covered by retained tests.

The optional manual benchmark and `--profile-only` mode are unchanged in scope. Neither runs
automatically in an install, audit or publication. Homepage timings are not search quality,
rendering, provider latency or competitor superiority. No result/winner badge is invented.
