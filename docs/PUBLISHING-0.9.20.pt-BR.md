# Publicar o SecuritySearch v0.9.20

Use o checkout `main` limpo, com histórico completo, **v0.9.20 e a correção r1**.
O kit verifica os arquivos conhecidos e não sobrescreve alterações locais.
Requisitos locais: Python 3.10+, Git, fish e `sha256sum`. Não é necessário instalar
`gh`, `tea` ou dependências via pip. Não crie um histórico artificial com
`git init` dentro de um pacote de código-fonte extraído.

## Executar

Na pasta extraída `securitysearch-publication-0.9.20`:

```fish
fish ./publish-securitysearch-v0.9.20.fish ~/securitysearch
```

O programa atualiza os READMEs em inglês/pt-BR e a documentação da release,
confirma somente essas alterações e as ferramentas de publicação em um commit
normal, executa os testes de publicação, cria a tag anotada `v0.9.20` no commit
final e gera o pacote. Mantém a aplicação em 0.9.20, recursos em 24 e a correção r1.
Sua identidade e configuração normal de assinatura do Git são respeitadas.
Tag anotada não significa assinatura criptográfica automática.

A saída padrão é `~/securitysearch-release-v0.9.20`, fora do checkout. Contém
`securitysearch-v0.9.20.tar.gz`, seu `.sha256`, um bundle Git incremental com
checksum, notas, `release-manifest.json` e `SHA256SUMS`. O bundle precisa do commit
`9f90eec4d30a713423b29ec6ef54a5f32e437185` (v0.9.19) para importação. Somente o
arquivo-fonte `.tar.gz` e seu `.sha256` são anexados às releases.

## Quatro remotes

São usados os repositórios existentes de `cristiancmoises` no GitHub,
`git.securityops.co` e `git.securityops.com.br`, e o repositório de `berkeley` no
Codeberg. Os quatro precisam ter uma branch `main` existente. O programa não cria
repositórios, não muda configurações e não altera o Nginx Proxy Manager.

Informe o token específico de cada host no terminal. GitHub exige acesso de
escrita a Contents neste repositório; Forgejo exige escrita no repositório.
Os tokens não são salvos em arquivos, URLs, argumentos ou remotes do Git. Ficam
na memória e brevemente no ambiente do processo Git; processos locais com
privilégios suficientes podem inspecioná-los. Use uma máquina confiável.

Antes da primeira escrita remota, os quatro hosts passam por verificações de
permissão, histórico, tag, notas e anexos existentes. Cada push de `main`+tag é
atômico naquele servidor, sem force. A release é criada como rascunho; os dois
anexos são enviados e verificados antes da publicação. Políticas/hooks do
servidor ainda podem recusar um push real após um dry-run aprovado.

Se algum host falhar depois que a publicação começar, repita o mesmo comando com
o mesmo checkout e arquivos. Hosts e anexos já concluídos são mantidos. Não
apague nem recrie a tag para tentar novamente. Tags diferentes, notas divergentes
ou anexos com bytes diferentes não são substituídos. Quatro servidores não podem
ser atualizados em uma única transação atômica.

## Preparação sem publicar

```fish
fish ./publish-securitysearch-v0.9.20.fish ~/securitysearch --prepare-only
```

Isso cria commit, tag e pacotes localmente, sem pedir tokens ou escrever nos
remotes. Para verificar os pacotes:

```fish
cd ~/securitysearch-release-v0.9.20
and sha256sum -c SHA256SUMS
```

Checkout sujo, histórico raso, baseline diferente ou hook de commit com falha
interrompem o processo. Alterações já aplicadas permanecem staged para análise;
não há reset, stash ou tag forçada.

## Deploy separado

A publicação não acessa a VPS via SSH nem reinicia serviços. As limitações da
auditoria r1 e a exigência da suíte completa antes do deploy continuam válidas.
As capturas do README são renderizações locais, não novas capturas de produção.

Após o commit de documentação, o kit de deploy r1 antigo recusa os novos hashes
dos READMEs. Para um deploy posterior, use `deploy-securitysearch.fish` deste
kit de publicação, que mantém os mesmos testes obrigatórios, backups, destino SSH
e porta 5119. Não é necessário refazer o deploy apenas para publicar a release.

[Notas bilíngues](RELEASE-0.9.20.md) · [Guia completo em inglês](PUBLISHING-0.9.20.md)
