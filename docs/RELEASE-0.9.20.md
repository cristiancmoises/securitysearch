# SecuritySearch v0.9.20

Application version: **0.9.20** · asset marker: **24** · includes the **r1 audit repair**.

## English

Binternet image search recognizes both the legacy and newer gallery markup,
root-relative and page-relative image/proxy links, and bounded continuation
bookmarks. Searches use the newer service's 160-byte UTF-8 query limit. Preview
mode uses the smaller image actually supplied by Binternet while retaining the
original image; it does not invent thumbnail URLs. Invalid or blocked provider
pages are not treated as successful empty results.

Legacy cURL provider calls share bounded connection/request budgets, DNS and
TLS-session state within one PHP request. Baidu's multi-request loop waits rather
than busy-spinning. Google CSE and Brave keep their separate bounded transports.
These are local improvements and bounded waits, **not measured live speedups or a
promise that every external provider is available**.

The homepage defaults to pure black and restores the native **Choose appearance**
picker with twelve small previews of existing image themes. Saved preferences
remain respected. The homepage needs no JavaScript. Image layouts, quality
choices, manual pagination, infinite scrolling, animated previews and the
**In Code We Trust.** footer remain.

The r1 repair guards the four cURL test mocks, rejects incomplete mock isolation,
and prints a bounded offline-audit failure tail while keeping the full log.
Production cURL configuration and deployment acceptance gates are unchanged.

English and Brazilian Portuguese READMEs now document the release, checksums,
annotated tag, recovery bundle and four-host publication. The two included
screenshots are **local release renderings, not new live-VPS captures**.

### Packages and integrity

- `securitysearch-v0.9.20.tar.gz`: complete source from the exact tagged commit,
  including the required empty `icons/` directory.
- `securitysearch-v0.9.20.tar.gz.sha256`: SHA-256 checksum for that archive.

This is PHP source, not precompiled binaries or a Docker image. The publisher
checks every archive entry against the tagged source before upload and verifies
uploaded asset integrity. SHA-256 checksums are integrity checks, not publisher
signatures; an annotated tag is not automatically a cryptographically signed tag.

### Validation and operations

The r1 authoring audit reported 13 available source test commands passed and 16
blocked/skipped by missing dependencies. It did not establish a successful full
extension-enabled Docker suite or live provider matrix. Publication checks
Git history, documentation, tag/package consistency and the release workflow;
it does not certify VPS deployment or all-provider availability.

Deployment remains separate and requires the isolated full offline suite,
candidate readiness and the real Binternet check before cutover. Keep deployment
backups. Do not bypass an audit, disable production cURL, force a tag move or
replace conflicting published assets. Existing matching drafts can be resumed;
separate forges cannot be published as a single atomic transaction.

## Português do Brasil

O SecuritySearch v0.9.20 mantém o marcador de recursos **24** e inclui a
**correção r1 da auditoria offline**.

A pesquisa de imagens do Binternet reconhece os layouts antigo e novo, links
relativos à raiz ou à página e marcadores de continuação com limites. A consulta
segue o limite de 160 bytes UTF-8 do serviço mais novo. A prévia usa a imagem menor
realmente fornecida pelo Binternet, mantendo a original e sem inventar URLs.
Páginas bloqueadas ou inválidas não são tratadas como resultados vazios válidos.

As chamadas cURL legadas compartilham limites de conexão e requisição, além de
estado de DNS/TLS durante a mesma requisição PHP. O laço do Baidu espera por
atividade em vez de ocupar a CPU continuamente. Google CSE e Brave mantêm seus
transportes próprios. Isso **não comprova ganho de velocidade em todos os
provedores nem garante disponibilidade dos serviços externos**.

A página inicial usa preto puro por padrão e restaura o seletor nativo de
aparência, com doze pequenas prévias dos temas existentes. Preferências salvas
continuam válidas, e a página inicial não precisa de JavaScript. Layouts,
qualidade de imagem, paginação, rolagem infinita, prévias animadas e o rodapé
**In Code We Trust.** são mantidos.

A revisão r1 protege os quatro mocks do cURL, recusa isolamento incompleto e
mostra o final limitado da falha de auditoria, preservando o log completo.
Não desativa o cURL de produção nem ignora os critérios de aceitação do deploy.
Os READMEs em inglês e pt-BR foram atualizados. As capturas incluídas são
**renderizações locais, não novas capturas da VPS em produção**.

Cada release concluída contém `securitysearch-v0.9.20.tar.gz` e seu arquivo
`.tar.gz.sha256`. O pacote contém o código-fonte PHP da tag exata, não binários
compilados ou uma imagem Docker. A auditoria r1 anterior registrou 13 comandos
aprovados e 16 bloqueados/ignorados por dependências; não foi declarada aprovação
da suíte completa com extensões ou de todos os provedores reais.

A publicação verifica histórico, tag, pacote e anexos, mas **não faz deploy**.
O deploy continua exigindo a suíte offline completa, a prontidão do candidato e
o teste real do Binternet. Preserve backups; não force tags nem substitua anexos
conflitantes. Execuções repetidas retomam rascunhos compatíveis. Quatro servidores
não podem ser publicados em uma única transação atômica.
