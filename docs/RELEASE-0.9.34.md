# SecuritySearch 0.9.34 / asset 38

This development checkpoint makes cached favicon responses cheaper to revalidate and safer
to read. No search provider, timeout, result count or query-sharing policy changes.

- Disk hits no longer initialize the proxy. Content-derived weak ETags allow a matching
  If-None-Match to return 304 without an image body. HEAD is explicitly bodyless.
- Conditional processing is bounded; malformed headers retain ordinary responses. Other
  preconditions/ranges retain prior handling. Error placeholders remain 404.
- Cache entries are read only when non-symlink, single-link regular files containing bounded small PNGs with stable file
  identity; failed validation uses the existing discovery/fallback. This is PNG metadata
  checking, not a full decoder. Local malicious writers remain outside the trust boundary.
- remote_attempted is initialized; invalid input does not emit the former property warning.
- Existing pre-build project-only cleanup retains production and the newest rollback.
  Receipt ordering, production drift checks and all provider gates remain mandatory.

Standalone Fish deploy and publication accept exact clean corrected 0.9.30, 0.9.31, 0.9.32,
0.9.33 and already-applied 0.9.34 trees. All downloads belong in ~/Downloads. No local PHP,
Guix environment bootstrap, old archive or manual extraction is required. No tags are moved.

The changed favicon endpoint is a runtime file and is verified in the production container;
source-only tests are verified against retained host evidence, not demanded inside the image.
There are 97 mandatory suite entries; native/live VPS acceptance is required before release
publication. This checkpoint is not a measured public win over 4get.ca or a 1.0.0 certificate.
