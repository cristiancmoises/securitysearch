# SecuritySearch v0.9.22

Application **0.9.22** · asset marker **26**. Builds on v0.9.21 and retains its
search, privacy, UI, rank, onion and deployment features.

## English

Includes maintenance repairs r1 and r2: archive-independent operator-theme tests,
explicit sibling-helper loading for private-theme deployment, and complete
failure reporting in the Docker audit. Every failing command still blocks cutover.
No provider behavior, production cURL settings or artwork restrictions are changed
by these maintenance repairs. See `docs/AUDITFIX-0.9.22-r2.md`.

Fix the offline HTTP news-test timeout: its primary-only `fetch_path` mock did not
intercept the new Redlib fallback transport. The fixture now owns every
`fetch_redlib` origin. Test-child DNS/socket/cURL tripwires reject an accidental
external request immediately. The HTTP client deadline was not increased, and
503, origin cooldown, successful fallback and origin-bound pagination remain tested.
Production cURL and the complete deployment gates stay enabled.

Repeated DNS work is reduced by request-local memoization and an optional APCu
positive-answer cache for seven fixed service/CDN hosts, capped at 15 seconds and
at the supplied DNS TTL. All addresses are revalidated; mixed public/private
answers fail closed. Arbitrary hosts are never put in the shared cache. No query,
result, cookie or visitor identifier is stored. Native connection attempts have
a three-second cap inside their existing request deadline. PHP's initial synchronous
DNS call is still outside cURL's timeout; no hard all-provider wall-clock guarantee
or measured live speedup is claimed. The native theme catalog is memoized once per
PHP request rather than restatted for every option.

The bundled Tron GIF matches historical commit
`81979bb217f97df1c6acc724ef7d8c9da2789d7c`; its existing optimized animation remains.
A separate operator tool recovers exact Lain/SecOps originals from local Git or
pinned historical URLs, checks Git blob IDs and sizes, and converts them to bounded
animated WebP, stills and small previews. The optional pack is kept outside Git
and added only to a private deployment archive. It is **not** a release attachment.
Without the pack, Lain keeps its palette and SecOps keeps the bundled Matrix
alternative. Animation pause/reduced-motion behavior remains script-free.

**Codeberg boundary:** `secops.gif`, `lain.gifv`, and their private derivatives are
not reintroduced into new commits, public tarballs or incremental recovery bundles.
The same clean source is published to all four forges. Ignore rules are backed by
tracked-tree and outgoing-history checks, including original blob IDs under renamed
paths. Known public palette/Matrix images have exact allowlisted blob IDs. This is
not an AI detector and does not remove already-existing historical objects or
rewrite published history. Follow the hosting service's requirements separately.

## Português do Brasil

Inclui as correções r1 e r2: testes sem dependência de `.git`, carregamento explícito
do helper de temas e relatório completo das falhas da auditoria. Qualquer falha
continua impedindo a troca. As correções não alteram os provedores, o cURL de
produção nem as restrições das imagens. Veja `docs/AUDITFIX-0.9.22-r2.md`.

Corrige o timeout da auditoria HTTP de notícias: o mock antigo interceptava apenas
a instância principal, deixando os fallbacks escaparem para a rede real. Agora
cada origem de `fetch_redlib` pertence à fixture, e tentativas externas de DNS,
sockets ou cURL no processo de teste falham imediatamente. O timeout do cliente
não foi aumentado, os testes não foram removidos e o cURL de produção não é desativado.

Consultas DNS repetidas usam memoização por requisição e cache positivo APCu de até
15 segundos somente para sete hosts fixos. Endereços públicos são revalidados;
respostas mistas com endereços privados são recusadas. Não se guardam consultas,
resultados, cookies ou identificadores de visitantes. A resolução DNS síncrona
inicial continua fora do timeout do cURL: não se promete prazo absoluto nem ganho
medido em todos os provedores. O catálogo de temas é calculado uma vez por requisição.

Tron já corresponde ao arquivo histórico e mantém sua animação otimizada. Lain e
SecOps originais são recuperados e verificados por ferramenta separada, convertidos
em animações/prévias limitadas e mantidos fora do Git. Somente o pacote privado de
deploy recebe essas imagens. O fonte e as releases dos quatro hosts, incluindo
Codeberg, não recebem os originais nem seus derivados. Sem o pacote opcional,
Lain mantém a paleta e SecOps mantém a alternativa Matrix. Não há reescrita do
histórico existente. Arquivos conflitantes, tags e anexos não são substituídos.

## Packages / Pacotes

`securitysearch-v0.9.22.tar.gz` and `securitysearch-v0.9.22.tar.gz.sha256` contain
only the exact tagged PHP source, docs, tests and permitted bundled assets, not
Docker binaries or the private theme pack. SHA-256 checks integrity, not identity.
Publication supports `--host git.securityops.co` to resume that release and its
attachments only. Deployment is separate and must pass the complete isolated
native audit, candidate readiness and real Binternet gate before cutover.

See `docs/AUDIT-0.9.22.md` for executed tests and limitations. Native PHP/Docker,
real provider availability and real historical Lain/SecOps conversion were not
established in the authoring environment. Operator retrieval/conversion is
validated on the user's machine; authoring conversion tests use color-frame fixtures.
