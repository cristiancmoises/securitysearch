# SecuritySearch v0.9.28

Application **0.9.28** · asset **32**.

## English

v0.9.28 adds an anonymous homepage static fast path. Container startup prepares the
same Black homepage that a cookie-free, query-free GET `/` would render. Apache may
serve that local file without invoking PHP only for GET/HEAD requests with no query,
Cookie or Authorization header. Personalized requests, searches and every other
route remain on the existing dynamic path.

The generated file is not tracked or shipped in source archives, is published
atomically with restrictive permissions and fails open to the ordinary renderer.
Direct requests to its implementation filename return 404. Its response preserves
the script-free CSP and security headers, varies on Cookie/Authorization and uses a
short 60-second public cache lifetime. Search pages remain private/no-store.

The optional Tranco timer can upgrade an exact SecuritySearch-managed v1 unit to v2;
a successful metadata refresh also rebuilds the anonymous homepage so its public
rank label does not remain stale. Different or locally modified systemd units are
preserved and refused.

Local Apache fixture measurements showed a 1.200 ms median TTFB reduction for the
static path versus the equivalent dynamic Black homepage. This is not a production
speed claim. The operator's competitive benchmark remains manual and unchanged;
public networking and Nginx Proxy Manager can dominate the measured TTFB.

All search-provider code and the v0.9.26 Google/CSE reliability repairs remain.
Local pictures, animated previews, RSS news, third-party Redlib attribution and the
private historical-theme publication policy are preserved.

## Português do Brasil

A v0.9.28 adiciona um caminho estático para a página inicial anônima. No início do
contêiner, o SecuritySearch prepara a mesma página Black exibida por um GET `/` sem
cookie nem consulta. O Apache usa esse arquivo somente em GET/HEAD sem query string,
Cookie ou Authorization. Preferências, buscas e todas as demais rotas continuam no
caminho PHP existente.

O arquivo gerado não entra no Git nem nos pacotes-fonte, é publicado atomicamente e
qualquer falha mantém o renderizador dinâmico. O nome interno retorna 404. A resposta
mantém CSP e cabeçalhos de segurança, varia por Cookie/Authorization e usa cache
público curto de 60 segundos. Resultados de busca continuam privados/no-store.

O timer opcional do Tranco atualiza uma unidade v1 somente quando ela corresponde
exatamente ao arquivo gerenciado pelo SecuritySearch. Após uma atualização válida,
a página estática também é reconstruída. Unidades modificadas pelo operador são
preservadas.

Em um fixture Apache local, o caminho estático reduziu a mediana de TTFB em 1,200 ms
contra a página Black dinâmica equivalente. Isso não é um benchmark de produção nem
prova superioridade sobre 4get.ca. O benchmark competitivo continua manual.
