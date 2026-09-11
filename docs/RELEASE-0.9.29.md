# SecuritySearch v0.9.29

Application **0.9.29** · asset **33**.

## English

v0.9.29 is a narrow performance/reliability release. The anonymous canonical homepage now
has a startup-generated gzip representation in addition to its validated plain static fast
path. Personalized/search requests are not cached by this mechanism.

Search-result highlighting reuses a bounded request-local compiled pattern. Brave retries
one qualifying transient gateway failure on the same cURL easy handle after complete option
reset, and malformed pagination links no longer generate invalid continuation tokens.

Google/CSE, Binternet, RSS news, local pictures, animated previews, themes, third-party
Redlib attribution, private-artwork exclusions and deployment/rollback gates are preserved.
The manual competitive benchmark is included unchanged and is not run or published by this
release.

## Português do Brasil

A v0.9.29 é uma versão focada e limitada de desempenho/confiabilidade. A homepage canônica
anônima passa a ter uma representação gzip gerada no startup além do caminho estático normal.
Buscas e páginas personalizadas não são armazenadas nesse mecanismo.

O destaque dos resultados reaproveita um padrão compilado limitado à requisição. O Brave
reutiliza seu easy handle em um único retry transitório permitido após reset completo das
opções, e links de paginação malformados não geram tokens de continuação inválidos.

Google/CSE, Binternet, RSS, My Picture, animações, temas, atribuição de terceiros do Redlib,
exclusão das artes privadas e gates de deploy/rollback são preservados. O benchmark manual
permanece inalterado e não é executado/publicado pela release.
