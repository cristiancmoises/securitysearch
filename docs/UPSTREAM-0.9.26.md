# Upstream review for v0.9.26

Reviewed the original 4get repository, not an unofficial reimplementation:
https://git.lolcat.ca/lolcat/4get

Observed upstream head: 734926240d2e40abd9bbde072a741b1cfc682432 (2026-09-08).
Reviewed index.php, scraper/google.php and scraper/google_cse.php via upstream's
code view. Upstream Google now expects FPLAY_EXTERNAL_ENDPOINT, a separate 4play
renderer service. Copying just its direct Google file into this deployment would
not supply that renderer and would change network/security/deployment assumptions.
No 4play service, browser automation, or challenge-solving component is silently added.

The CSE source also uses a hosted HTML-to-JavaScript bootstrap and treats presence
of isExactTotalResults as final; neither is copied as a presumed fix. This patch
retains the existing hardened CSE transport and makes narrow source-level changes:
bounded parsing, independent record validation, actual returned page offsets and
session coordination. No wholesale upstream source replacement is made. Existing
4get attribution and AGPL license remain.

Google's documented Element loader uses cse.google.com/cse.js?cx=...:
https://developers.google.com/custom-search/docs/element
This supports using that query-free bootstrap entry point. It does not establish
an SLA, stability or authorization guarantee for every internal response format.
A format-only legacy path remains, bounded by the same request deadline.

The PHP single-pass map behavior is documented at:
https://www.php.net/manual/en/function.strtr.php
Replacement values are not rescanned. The template cache stores bundled source
only; no visitor query, result or rendered preference-specific document is shared.

The operator's homepage timing sample is not reproduced in README or release assets.
Homepage HTML delivery is not Google-result timing. Server-Timing distinguishes PHP
processing from transport and queue time without promising a network-wide speed win.
