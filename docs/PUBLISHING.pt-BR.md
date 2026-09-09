# Publicar SecuritySearch v0.9.19

O kit contém fonte completo e SHA-256, bundle Git e SHA-256, notas bilíngues, script/guias de deploy, este guia e `publish-securitysearch-v0.9.19.fish`. O bundle inclui o commit de remoção no Codeberg e a nova versão, com base em `34ac6854420a0dd41b05b1c558ce2d879068bf23`. Serve para o checkout existente em `~/securitysearch`, inclusive após o fast-forward para `0751f14`.

## Comando fish

Após verificar e extrair o kit, execute dentro dele:

```fish
fish ./publish-securitysearch-v0.9.19.fish ~/securitysearch
```

Requer Git, fish, Python 3 e sha256sum. O launcher verifica os hashes fixados, exige `main` limpo, confere bundle/tag, cria branch de backup e atualiza por fast-forward. O histórico anterior permanece. Checkout com alterações, branch divergente ou HEAD destacado interrompe a importação com explicação. Não há reset, stash ou resolução automática de conflitos. O arquivo de fonte sozinho não contém metadados Git; use o bundle para atualizar o clone existente.

Digite separadamente o token de cada host no prompt oculto. O token permanece em memória; o helper temporário do Git não contém o segredo. Não coloque tokens em comandos, documentos ou URLs de remotes. O publicador desativa armazenamento de credenciais e rastreamento HTTP detalhado.

| Host | Repositório | Acesso do token |
|---|---|---|
| codeberg.org | berkeley/securitysearch | escrita no repositório |
| github.com | cristiancmoises/securitysearch | Contents write; acesso a workflows se os arquivos alterados exigirem |
| git.securityops.co | cristiancmoises/securitysearch | escrita no repositório |
| git.securityops.com.br | cristiancmoises/securitysearch | escrita no repositório |

Os quatro repositórios precisam existir. São enviados `main` e a tag anotada `v0.9.19` por push normal. A tag remota é conferida antes da criação do release. Branch/tag incompatível interrompe aquele host, sem impedir a tentativa nos demais.

## Arquivos e retomada

Cada release recebe `securitysearch-v0.9.19.tar.gz` e seu `.sha256`. O arquivo precisa corresponder ao fonte da tag. O kit/bundle é material de importação; os assets publicados são o fonte e checksum. Releases novos começam como rascunho e só ficam públicos depois da verificação dos dois arquivos. Arquivos idênticos são reutilizados; ausentes podem ser enviados na próxima execução. Conteúdo diferente com o mesmo nome, ou tag conflitante, é preservado e informado.

Após falha temporária, rode novamente o mesmo launcher. Com a versão já importada e o checkout limpo, também pode executar:

```fish
python3 ~/securitysearch/scripts/publish-release.py --assets /caminho/absoluto/securitysearch-publication-v0.9.19
```

404 pode indicar repositório ausente ou falta de acesso. Erro de gateway não comprova token inválido. Os releases públicos são criados quando você roda o comando com seus tokens; não foram publicados neste ambiente.

## Recriar e fazer deploy

No fonte limpo, com a tag anotada apontando para o commit correto:

```sh
./release.sh 0.9.19
python3 scripts/build-publication-kit.py
```

Os arquivos gerados ficam em `dist/`, fora da imagem da aplicação. A captura JPEG está no próprio repositório e os dois READMEs usam link relativo compatível com as quatro forges.

Publicar Git/releases não faz deploy no VPS. Para o IONOS, use `scripts/deploy-ionos.fish` com o fonte e checksum em `~/Downloads`. São mantidos SSH 5119, root@securityops.co, bind privado e rollback. Veja [operações](OPERATIONS-0.9.19.pt-BR.md) e o [guia completo em inglês](PUBLISHING.md).

A verificação aceita downloads assinados dos hosts de assets conhecidos do GitHub sem enviar o token. Um redirecionamento do Forgejo para armazenamento externo desconhecido interrompe a verificação desse host; revise a configuração do armazenamento antes de tentar novamente.
