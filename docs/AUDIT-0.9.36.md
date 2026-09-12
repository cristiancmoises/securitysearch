# Audit scope — v0.9.36

Baseline: exact reconstructed v0.9.35 tree 5402b66a0fb8518d536976bd42381bd9c4217548.
Only lib/image_results.php changes visitor-request behavior. The first valid source stays
original. Relative, credential-bearing, non-HTTP(S), overlong and duplicate URLs continue
through the existing filter. The 32-entry scan bound counts invalid entries as well.
No image URL is fetched while selecting, and title/link escaping stays on the renderer path.

New required tests: 64 PHP selection/rendering assertions; seven native localhost PHP HTTP
cases for HTML/JSON agreement, animation, escaping, page/source limits and source fallback;
release contracts and immutable-publication/package fixtures. Previous mandatory commands
remain in their original order. Fault-injection testing belongs in disposable source copies.

The complete source audit must be run both unprepared and with explicitly synthetic private
fixture assets/prepared UI resources. Local missing native extensions are failures, never
passes or silent skips. Actual outcomes and logs are in the delivered VALIDATION report.
Synthetic SSH/Docker/publication tests do not change or prove the remote service.

The new renderer must be included in source and serving-image digest verification. Native
Alpine/FPM/Imagick audit and direct Google Web/Images, RSS and Binternet acceptance still
block cutover. Small PNG acceptance, upstream error policy, provider order/deadlines, artwork
exclusion, guarded cleanup and durable rollback receipts remain protected by existing tests.
