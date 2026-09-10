# Operação/publicação v0.9.21

Base: main limpo da v0.9.20 publicada, commit `7f9f25a2f56a2b2edb1189e076da29895f95c057`,
árvore `5b37bce9c534389362f4f14169ff826ca58cceb8`. Git, Python 3.10+ e fish locais;
Python/Docker na VPS existente. Preserve alterações locais antes de executar.

```fish
fish ./apply-securitysearch.fish ~/securitysearch
fish ./deploy-securitysearch.fish ~/securitysearch --rank-refresh
fish ./publish-securitysearch.fish ~/securitysearch
```

Confirme o SHA-256 externo e mantenha o diretório do kit inteiro. Aplicar verifica
hashes/árvore e cria commit normal. Falha de hook deixa a alteração staged; nada é
resetado, descartado ou publicado. O deploy usa apenas fonte commitado, SSH **5119**
para **root@securityops.co**, preserva bind/redes e não recria Nginx Proxy Manager.
A suíte offline completa roda isolada de segredos/volumes/rede externa; depois
prontidão e Binternet são verificados no candidato antes da troca. Rollback é
tentado em falha da substituição. Nunca ignore a auditoria nem remova backups:
o contêiner pode montar snapshots privados daquele diretório.

`--rank-refresh` instala opcionalmente unidades systemd após deploy bem-sucedido,
sem sobrescrever unidades diferentes. A execução imediata/diária atualiza somente
metadados públicos do domínio; falhas não fazem rollback do site. Consulte
`journalctl -u securitysearch-tranco.service`. Para retomar:

```fish
fish ./enable-rank-refresh.fish ~/securitysearch
```

Desative na VPS com `systemctl disable --now securitysearch-tranco.timer`.
Cache ausente/velho aparece como unavailable. Não há consulta Tranco durante a
busca nem coleta de visitantes. O ranking não é indicador de qualidade/segurança.

## Publicar novamente só um host, com os anexos

```fish
fish ./publish-securitysearch.fish ~/securitysearch --host git.securityops.co
```

Sem --host seleciona os quatro. Repetir --host seleciona um subconjunto. O kit
pede apenas os tokens dos hosts selecionados. Os repositórios/main precisam
existir. --prepare-only cria commit/tag/pacotes locais, sem pedir tokens/publicar.
Arquivos saem em `~/securitysearch-release-v0.9.21/`. A release recebe tar.gz e
SHA-256 do commit real; bundle incremental local depende da v0.9.20.

Não recrie a tag nem substitua arquivos para retomar. Tags/notas/anexos iguais
são reutilizados; diferenças param para revisão. Anexos Forgejo usam multipart e
seu conteúdo é verificado. Só HTTP de inexistência é tratado como recurso ausente.
Tokens não são colocados em URLs, arquivos ou argumentos; ficam em memória e
brevemente no ambiente do processo Git local. Não envie tokens nos logs.

Todos os hosts selecionados passam por preflight antes da primeira escrita.
Depois disso, alguns podem concluir e outros ficar pendentes; servidores
independentes não formam uma transação global. Repetir o mesmo comando reconcilia
estado compatível. Publicação não implanta ou reinicia a VPS.

## Privacidade

O fallback Redlib pode enviar a consulta para os operadores externos informados.
Use FOURGET_REDLIB_FALLBACKS=false para apenas a instância principal. Cache é
limitado ao feed público inicial sem consulta por sessenta segundos.

My picture usa JavaScript opcional, sem endpoint de upload. Armazenamento da aba
é padrão; lembrar no dispositivo é explícito. Outros scripts da mesma origem e
usuários do perfil do navegador podem acessar o conteúdo. Remove limpa ambos os
armazenamentos. Trocar para Black sozinho não apaga uma imagem já lembrada.

`fish ./audit-providers.fish` testa uma consulta neutra `teste` por combinação de
provedor/tipo na VPS, em sequência, e copia o JSON de volta. Código 2 informa
provedor vazio/indisponível; não é aprovação total nem benchmark p50/p95.

[Relatório/limitações](AUDIT-0.9.21.md) · [Guia detalhado em inglês](OPERATIONS-0.9.21.md)
