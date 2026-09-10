# SecuritySearch v0.9.25 — initial rendering and provider resilience

Application 0.9.25 · asset marker 29. Includes the independent-Redlib attribution
correction and all prior v0.9.24-r1 repairs. Existing published tags are not moved.

## English

The default Black homepage now embeds audited homepage-only CSS, removing its three
external render-blocking stylesheet requests without a JavaScript loader. Other
themes and result pages retain their external styles where needed. Its original
400 × 86 banner shrinks from 11,528 to 5,710 bytes; dimensions and high fetch priority
remain, and the preload is discovered earlier. Local gzip comparison of HTML plus
blocking CSS: 15,796 → 9,866 bytes. This is not a production LCP measurement.

Google/Brave failures retain safe status categories. Bounded Retry-After and
query-free, per-egress cooldowns reduce repeated refused or failed requests.
Brave no longer rotates addresses to retry challenges; transient 502/503/504 may
receive one same-egress retry without Retry-After/challenge, inside the existing
deadline. Google bootstrap waiters stop contending after two seconds. Narrow
JSONP/Svelte bootstrap variations are parsed without executing upstream scripts.
These fixtures do not establish current external availability or eliminate CAPTCHA.

Image previews use smaller genuine variants when dimensions permit, keep original
links, and avoid deprioritizing the other three eagerly loaded first-row cards.
Normal pagination, bounded animations, My picture, independent RSS news, operator
artwork exclusion, and external-Redlib attribution are preserved.

All 57 existing test commands remain, plus four new suites (61 total). Release
assets are the complete exact-tag source `securitysearch-v0.9.25.tar.gz` and its
SHA-256 checksum. The optional private theme pack is never included. Follow the
matching kit's apply/deploy/publish commands; one-host release retries are supported.
Native Docker audits and live RSS/Binternet gates are still mandatory. No claim
of successful authoring-environment Docker deployment, live-provider search or a
new Lighthouse score is made. See the included audit for actual evidence/limits.

## Português do Brasil

A página inicial Black incorpora CSS específico da página e elimina três folhas
externas bloqueantes, sem carregador JavaScript. Temas alternativos/resultados
continuam usando CSS externo quando necessário. O logotipo original de 400 × 86
passa de 11.528 para 5.710 bytes, mantendo dimensões, prioridade alta e preload
mais cedo. HTML + CSS bloqueante em gzip local: 15.796 → 9.866 bytes. Não é uma
medição do LCP da VPS nem dos milissegundos estimados pelo Lighthouse.

Google/Brave preservam categorias seguras de erro, Retry-After limitado e pausas
por saída de rede sem cache de consultas/resultados. Brave não troca de proxy
para repetir desafios. Falhas transitórias de gateway permitem no máximo uma
nova tentativa na mesma saída e prazo. A espera por sessão Google concorrente
fica limitada a dois segundos. Variações restritas de wrappers são aceitas sem
executar scripts. Provedores externos ainda podem bloquear ou limitar buscas.

Prévias usam variantes menores reais, mantendo links originais e a prioridade
normal das outras três imagens iniciais. My picture, animações, RSS, atribuição
a terceiros e exclusões do pacote privado são preservados. Os 57 testes anteriores
continuam obrigatórios; quatro novos totalizam 61. O deploy exige auditoria nativa
e verificações reais RSS/Binternet. O pacote público não contém os temas privados.
A tag v0.9.25 não move nem substitui tags/releases anteriores.
