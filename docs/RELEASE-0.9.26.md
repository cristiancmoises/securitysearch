# SecuritySearch v0.9.26

Application 0.9.26 · asset marker 30. New tag; no earlier release is retargeted.

## English

Repair Google CSE web/image data handling: zero-height thumbnails, malformed optional
fields, bounded inert JSONP prefixes, explicit numeric page offsets and all-invalid
page detection. Preserve original and actual small/large preview URLs. Use the
query-free documented loader first; one bounded legacy discovery is format-only.
Recheck renewed tokens after cache-lock acquisition and preserve another worker's
session. Existing transport limits, TLS, cooldowns and labelled Brave fallback remain.

Use single-pass template substitution and request-local unrendered-source reuse.
Compile bundled homepage skin; retain inline Black critical CSS and optimized banner.
Expose only PHP origin time in Server-Timing, keeping HTML private/no-store.
Include a clearly labelled local homepage capture in both READMEs and the supplied
manual benchmark script without outputs, rankings, automatic execution or speed claims.

Optional --verify-google adds two fixed neutral candidate checks before cutover;
Google web AND image records are required, with no fallback masquerading as Google.
The existing full offline, RSS, Binternet and readiness gates remain. Private artwork
never enters shared source/releases. Remote Redlib operators remain explicitly third-party.

Runtime audit commands and isolated fixtures are reported separately in AUDIT. The
full native Docker runtime and live provider availability are not claimed here.
Google public endpoints are best-effort external services; these fixes do not solve
captchas, eliminate all restrictions, or establish a win over 4get.ca.

Release attachments: securitysearch-v0.9.26.tar.gz and its .tar.gz.sha256.
They are exact tagged PHP source, not compiled binaries or a Docker image.

## Português do Brasil

Corrige divisão por zero, registros opcionais malformados, JSONP limitado e paginação
numérica da integração CSE. Mantém URLs reais de prévias/originais. O carregador
oficial sem consulta reduz preparação no caminho suportado; recuperação legada só
ocorre por formato, nunca por bloqueio/captcha. Atualizações concorrentes de sessão
não descartam o novo token de outro processo. Prazos, TLS e fallback são mantidos.

Templates usam substituição única, CSS da página é compilado previamente e
Server-Timing mostra apenas tempo PHP. READMEs incluem captura local identificada e
script manual de benchmark sem execução/resultados. --verify-google exige Google web
e imagens reais antes do corte; auditorias anteriores permanecem. Imagens privadas
ficam fora dos releases e Redlib é identificado como serviço de terceiros.

Não declaramos auditoria Docker nativa completa, eliminação de todos os erros externos
nem vitória de velocidade. Anexos contêm código PHP da tag exata com checksum.
