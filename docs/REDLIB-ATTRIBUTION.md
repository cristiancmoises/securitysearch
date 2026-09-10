# Redlib operator attribution — redlib-attribution-1

## External Redlib operators — attribution correction

The external Redlib instances used or linked by SecuritySearch are provided and
operated by independent third-party individuals or organizations, **not by Security Ops**.
Security Ops maintains the SecuritySearch integration; it does not operate those
external instances. Their operators control their policies and availability. Queries
sent through the Reddit provider reach the selected instance, and enabled fallback
may send them to another external instance after a failure. The SecuritySearch
no-tracking statement must not be read as a guarantee about those services.
News RSS (Google/Bing) remains a separate provider; routing and privacy controls
are unchanged by this attribution-only patch.

The `redlib-attribution-1` correction is a normal commit **after** the published
v0.9.24 tag; it does not retarget that tag or replace its archives. Apply and deploy
with the matching `securitysearch-attribution-fix-1` kit. Publishing its `main`
commit does not rewrite already-published release files. [Details](docs/REDLIB-ATTRIBUTION.md).

## Operadores externos do Redlib — correção de atribuição

As instâncias externas do Redlib usadas ou indicadas pelo SecuritySearch são
fornecidas e operadas por pessoas ou organizações terceiras independentes,
**não pela Security Ops**. A Security Ops mantém a integração do SecuritySearch,
não essas instâncias externas. Seus operadores definem suas políticas e
disponibilidade. Consultas enviadas pelo provedor Reddit chegam à instância
selecionada; se habilitado, o fallback pode enviá-las a outra instância após uma
falha. A declaração de ausência de rastreamento do SecuritySearch não é uma
garantia sobre esses serviços. News RSS (Google/Bing) continua sendo um provedor
separado. Esta correção não altera o roteamento nem os controles de privacidade.

A correção `redlib-attribution-1` cria um commit normal **após** a tag publicada
v0.9.24; não move a tag nem substitui seus arquivos. Aplique e implante com o kit
`securitysearch-attribution-fix-1` correspondente. Publicar o commit na `main` não
reescreve releases existentes. [Detalhes](docs/REDLIB-ATTRIBUTION.md).

## Deployment and publication boundary

This patch changes notices, source comments and documentation only. It retains
application 0.9.24, asset marker 28, the Redlib allowlist, RSS default, timeouts,
private-theme exclusions and existing deployment/rollback gates. A native audit
also checks the new notice on public routes before the existing live RSS/Binternet
checks and cutover. No extra browser script, network call or dependency is added.

The matching helper preserves all existing tags, including v0.9.24. Do not use an
older release publisher to move the old tag onto this commit. Use the included
branch-only publisher for this correction. The old release's immutable snapshot
is not changed; a later source release must use a new version.

Operator attribution follows the SecuritySearch maintainer's explicit clarification.
No legal identity, affiliation, endorsement, privacy certification or service-level
agreement for an external operator has been independently verified.
