# SecuritySearch v0.9.24

Application **0.9.24** · asset marker **28**. Includes all v0.9.23-r1 repairs.

## English

News no longer depends on a Redlib instance accepting automated requests. The
new default **News RSS** adapter tries Google News RSS, then Bing News RSS on
failure. This is publisher-headline search, **not Reddit content**. The optional
Reddit provider remains separate; no challenge is solved or access refusal bypassed.
An explicit source selection disables automatic fallback. English/US and
Portuguese/Brazil editions are available. Existing saved provider choices are
preserved; select News RSS to leave a previously saved Reddit choice.

Requests use fixed HTTPS routes, existing validated public-IP resolution and
connection pinning, certificate verification, no redirects, a 1 MiB response cap,
and bounded request work. Default RSS attempts get 4.5 seconds each within nine
seconds of adapter work; the first synchronous DNS lookup is not bounded by cURL
alone. No claim of a measured all-provider speedup or perpetual provider uptime.

RSS XML rejects DTDs/entities, invalid UTF-8, XInclude, malformed structures,
unsafe result links and excess nodes/items. It disables external XML loading and
returns at most forty headlines with publisher/date/source links. It does not
fetch article bodies or image URLs, inject descriptions as HTML, invent pagination,
or disguise challenge pages and HTTP refusals as empty success.

Only a query-free public headline feed can be cached: fresh for 120 seconds,
retained up to 600 seconds for explicitly labelled stale display during a failure.
Cache keys separate source and edition. A short single-flight lock avoids
simultaneous public refreshes. Keyword results are never cached or logged. Definite
refusals/challenges cool that source for ten minutes; rate limits use at least five
minutes and respect a bounded Retry-After value. No automatic background retries.

Deployment requires **fresh, parsed, nonempty headlines AND a neutral keyword search
from the same RSS source**, persists that source/edition and verifies replacement
configuration. The obsolete Redlib-only success requirement is replaced by this
real news check, not skipped. The full offline suite and live Binternet gate stay
mandatory. Failures print safe per-stage HTTP/error/count evidence immediately and
retain `news-live.json` in the backup directory.

The supplied VPS diagnostic established two HTTP-200 pages without Redlib result
structure and with challenge indicators, and HTTP 418 from Nadeko. It did not
establish that other news sources work. Google/Bing RSS are best-effort public
endpoints, not a guaranteed API or service agreement. Live availability must be
established on the VPS; source implementations and fixture tests do not certify it.

Browser-local pictures, animated image previews, existing bundled themes, onion
and Tranco footer, operator-image separation and rollback rules remain. Private
Lain/SecOps originals and derivatives are not added to any new Git source commit
or release attachment, including Codeberg. Reuse the external operator pack only
with `--theme-assets` on private deployment.

Release assets are `securitysearch-v0.9.24.tar.gz` and its `.tar.gz.sha256` checksum,
generated from the actual annotated tag on the operator's computer. These contain
PHP source, not a prebuilt Docker image. Checksums are not publisher signatures.
No force push, changed old tag, replacement of conflicting assets, production
restart or remote publication occurred during preparation of this kit.

## Português do Brasil

Notícias deixam de depender de uma instância Redlib que aceite as requisições.
O novo padrão **News RSS** consulta Google News RSS e, se falhar, Bing News RSS.
São manchetes de publicadores, **não postagens do Reddit**. Reddit permanece como
provedor opcional; nenhum desafio ou bloqueio é contornado. A seleção explícita de
uma fonte desativa o fallback. Há edições inglês/EUA e português/Brasil. Provedores
já salvos no navegador continuam respeitados; selecione News RSS para trocar.

Rotas HTTPS fixas, validação de IP público, fixação da conexão ao IP validado,
verificação TLS, recusa de redirecionamentos e limite de 1 MiB são mantidos. O XML
recusa DTDs/entidades, XInclude, links inseguros e estruturas excessivas. São
exibidas até quarenta manchetes com fonte/data, sem buscar artigos ou imagens,
executar HTML de descrições, inventar paginação ou tratar bloqueios como sucesso.

Somente a página pública sem consulta pode ser armazenada: 120 segundos de cache
fresco, até 600 segundos para exibição antiga claramente identificada durante
falhas. Cache separado por fonte/edição e trava curta de atualização evitam trabalho
repetido. Consultas privadas não entram no cache. Recusas/desafios geram espera de
dez minutos; limites de requisição respeitam Retry-After com limite. Não há retry
em segundo plano. Isso não comprova ganho de latência em todos os provedores.

Antes da troca do contêiner, a fonte deve fornecer manchetes **e** resultados reais
para uma palavra neutra. A fonte/edição aprovada é persistida e verificada no novo
contêiner. A suíte offline completa e o teste Binternet continuam obrigatórios.
O relatório detalhado fica em `news-live.json`. Se todas as fontes falharem, a
produção não é substituída. Não há garantia externa de disponibilidade.

Imagens locais dos usuários, animações, temas, rodapé onion/Tranco e rollback são
preservados. Lain/SecOps privados ficam fora do Git e das releases, inclusive
Codeberg. Reutilize o pacote externo com `--theme-assets` somente no deploy privado.
Nenhuma VPS, tag antiga, branch remota ou release foi alterada ao preparar este kit.
