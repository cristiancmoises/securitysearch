# SecuritySearch 0.9.37 — bounded operators and audit diagnosis

Implemented checkpoint, not known deployed or published. Asset version remains 40 because
visitor code, styles, JavaScript and transport are unchanged. Existing v0.9.36 UI captures
are explicitly historical, not newly measured pages.

The standalone operators now decode a schema-2 envelope. Identical cumulative Git file-diff
sections share SHA-256-addressed chunks, split at 64 KiB. All seven corrected v0.9.30–v0.9.36
baselines reconstruct complete, independently hash-checked patches. No prior kit, PHP on
the client, dirty reset, force-push or relaxed source identity is required.

The decoder rejects unexpected resource names, duplicate keys, invalid types, noncanonical
base64, corrupt/missing/unused chunks, wrong file digests or sizes, truncated/multi-member
or trailing gzip, and excessive input/expansion/part counts. The operator limit remains
8 MiB; launcher input and decoded JSON remain bounded at 16 MiB. Reconstructed resources
have an explicit 16 MiB aggregate bound. Checksums are integrity checks, not signatures.

The earlier local HTTP-200 assertion was reproduced as HTTP 500 and traced to missing
mb_strcut() in lib/frontend.php. The mandatory mbstring extension was absent. A native
prerequisite check now fails before fixture workers launch; it never supplies a replacement
extension or turns missing dependencies into a pass. PHP diagnostics use a separate local
error log rather than reopening redirected stderr. Fixed-route status errors do not include
response bodies or search query URLs.

Four commands extend the unchanged 106-command audit prefix to 110. Full serving-image
Imagick/FPM acceptance and native Google Web/Images, RSS and Binternet gates remain required.
Project-only cleanup, inventory/drift checks, rollback and durable evidence remain intact.
See AUDIT-0.9.37.md and the delivery's executed validation report for exact local scope.
