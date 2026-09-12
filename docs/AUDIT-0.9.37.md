# Audit scope — 0.9.37

All 106 prior mandatory commands remain in order; four additional required commands make
110. Deployment still requires a complete native audit with zero failures and the retained
live provider gates. Missing dependencies are failures, not successful skips.

New source tests cover schema-2 exact round trips/determinism, shared patch chunks, arbitrary
bytes, empty files, malformed/trailing/truncated gzip, duplicate JSON keys, integrity under
optimized Python, and encoded/decoded/per-file/aggregate/reference limits. HTTP helper tests
exercise strict prerequisite success, missing modules, false success, timeout, redaction and
ordering before worker startup. A real php -n subprocess forces the negative module case,
including on fully provisioned systems; this is not evidence of positive native acceptance.

The local v0.9.36 failure was reproduced as HTTP 500. Its retained PHP log identifies
undefined mb_strcut(), consistent with absent mbstring. The revised suite reports missing
curl, dom, xml, mbstring, apcu and imagick before workers start in this development runtime.
It remains blocked here. Complete HTTP success must be tested in the actual native image.

External operator tests exercise real disposable Git patching and loader execution with
simulated SSH/Docker/forge evidence. They do not log into IONOS or publish a release. No
fixture Git identities, private artwork, fonts or credentials belong in the public source.
Consult the packaged VALIDATION-0.9.37.md and raw audit logs for executed counts and failures.
