# Operação — v0.9.41

Use o [guia direto da v0.9.30](UPGRADE-0.9.30-to-0.9.41.md).
Destino `root@securityops.co:5119`, contêiner `security-search`.
Downloads em `~/Downloads`, checkout em `~/securitysearch`, temas privados em
`~/.local/share/securitysearch/operator-themes-v1`.

| Modo | Efeito |
|---|---|
| `--check-only` | Verificação local, sem alterações ou SSH |
| `--prepare-only` | Aplica/stage/commit local quando necessário; sem SSH, temas, build, tags ou push |
| `--audit-only` | Prepara, envia, constrói e audita; não substitui produção, limpa antigos ou consulta provedores |
| Sem opção | Repete auditoria, verifica provedores e executa troca protegida |
| `--collect-audit` | Lê auditoria remota existente e salva diagnóstico filtrado local |
| `--cleanup-plan` | Consulta objetos elegíveis, sem excluir |
| `--profile-only` | Observa entrega HTTP da própria instância, sem benchmark competitivo |

Escolha um único modo. `--prepare-only` não é aceito no publicador.
Auditoria/deploy exigem os temas privados; preparação/verificação/coleta não.
A margem mínima de 2 GiB/10.000 inodes não garante espaço para toda a construção.
A captura da auditoria tem limite de 8 MiB e 1.800 segundos; build/upload ficam fora desse limite.

São obrigatórios 124/0 na imagem nativa, evidências completas e resultados reais de
Google Web e Images, RSS, Binternet e validação de imagens. Dependências ausentes e
indisponibilidade externa bloqueiam o fluxo. O deploy normal mantém o timer de rank-refresh;
audit-only não o instala. Benchmarks não são executados automaticamente.

Chaves SSH precisam ser conhecidas e corresponder; encaminhamentos e LocalCommand ficam
desabilitados. Coleta cria JSON/checksum privados, sem consultas, corpos ou credenciais.
Sucesso na coleta não autoriza deploy/publicação. NPM, portas, volumes, redes, configuração
privada, backups e rollback protegido são mantidos. Não há prune global ou reescrita do histórico.

Consulte [problemas comuns](TROUBLESHOOTING-0.9.41.md) e [publicação](PUBLISHING.pt-BR.md).


## Correção r2 da auditoria nativa (inclui r1)

Para a falha nativa 115/4, substitua os launchers anteriores por **securitysearch-v0.9.41-deploy-ionos.fish** e **securitysearch-v0.9.41-publish-four-remotes.fish**. Execute `--check-only` e depois `--audit-only`; 124/0 e todas as verificações reais continuam obrigatórias. A versão do aplicativo permanece 0.9.41. Consulte os [detalhes da correção r1](AUDITFIX-0.9.40-r1.md).

A revisão r2 também restaura a configuração após uma geração maior que o limite de captura, sem ocultar a falha original do teste. O comparador lê no máximo o tamanho do original mais um byte por comparação. Consulte a [correção r2](AUDITFIX-0.9.40-r2.md). A auditoria nativa 124/0 continua obrigatória.
