# Security Search v0.9.20

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![Security Search — preto puro](docs/screenshots/securitysearch-0.9.20-home-black.png)

Prévia local desta versão; **não** é uma captura da VPS atualizada. O controlador HTTP foi testado em localhost, e o Chromium renderizou seu HTML com os recursos locais incorporados para a prévia isolada. Versão dos assets: **24**.

## Alterações

O adaptador Binternet reconhece tanto o HTML antigo (`img-container`/`img-result`) quanto o novo (`image-gallery`/`image-link`). Aceita links locais com ou sem a barra inicial, buscas de até 160 bytes UTF-8 e bookmarks de até 4096 bytes. URLs externas inesperadas, links malformados e continuação com cursor repetido são rejeitados. Página vazia reconhecível não é confundida com bloqueio ou mudança de HTML.

As prévias usam a imagem menor realmente fornecida pelo serviço; o endereço e as dimensões do original são preservados. Não são inventadas URLs de tamanho maior. A origem continua `https://images.securityops.co`: o endereço `img.securityops.co` da navegação não é trocado automaticamente.

As chamadas cURL legadas compartilham um orçamento por requisição: 3 segundos de conexão, 12 segundos por chamada e 20 segundos no conjunto dessas chamadas, por padrão. DNS e sessões TLS são reaproveitados somente dentro da requisição PHP, sem compartilhar cookies ou armazenar buscas. O loop de múltiplas conexões do Baidu deixa de fazer espera ocupada. Google CSE e Brave mantêm seus transportes limitados e a política anterior de recuperação. Não há promessa de velocidade medida em provedores externos.

**Preto puro** passa a ser o padrão da instância. **Choose appearance** permite escolher os temas existentes por um formulário nativo, com prévias pequenas de suas imagens. O tema já salvo no navegador continua sendo respeitado. A página inicial não recebe JavaScript. Doze miniaturas estáticas novas somam aproximadamente 45 KB; wallpapers completos só são usados pelo tema selecionado.

São mantidos os quatro botões da busca, seis layouts de imagens, níveis de qualidade, filtros de formato, rolagem infinita opcional, animações limitadas, paginação manual, API, links de serviços e o rodapé **In Code We Trust.** Google continua padrão; a primeira busca pode tentar Brave uma vez, com aviso. A paginação não troca de provedor silenciosamente.

## Release v0.9.20

Inclui a **correção r1 da auditoria offline** e mantém a versão da aplicação
**0.9.20** e os recursos em **24**. Os quatro mocks do cURL são registrados com
proteção; isolamento incompleto é recusado, e falhas mostram o final limitado do
log, preservando o arquivo completo. O cURL de produção não é desativado.
[Detalhes da correção](docs/AUDITFIX-0.9.20-r1.md).

### Pacotes

[Release no Codeberg](https://codeberg.org/berkeley/securitysearch/releases/tag/v0.9.20) ·
[Release no GitHub](https://github.com/cristiancmoises/securitysearch/releases/tag/v0.9.20) ·
[SecurityOps .co](https://git.securityops.co/cristiancmoises/securitysearch/releases/tag/v0.9.20) ·
[SecurityOps .com.br](https://git.securityops.com.br/cristiancmoises/securitysearch/releases/tag/v0.9.20)

Cada release concluída contém **`securitysearch-v0.9.20.tar.gz`** e seu
**`.tar.gz.sha256`**. O pacote contém o código-fonte PHP da tag exata, documentação,
testes e recursos locais; não é um executável compilado nem uma imagem Docker.
Cada link fica disponível após a conclusão da publicação naquele host.

```sh
sha256sum -c securitysearch-v0.9.20.tar.gz.sha256
tar -xzf securitysearch-v0.9.20.tar.gz
```

### Atualizar uma instalação existente

Aplique e implante o kit corrigido **`securitysearch-update-0.9.20-r1`** antes de
usar o kit de publicação. Não use o kit v0.9.20 original sem a correção nem o
publicador antigo da v0.9.19.

O deploy envia o código commitado para **root@securityops.co**, SSH **5119**,
preservando **172.17.0.1:5140 → 80**, redes e configurações privadas compatíveis.
Não recria o Nginx Proxy Manager. A suíte offline completa, a prontidão do candidato
e o teste real do Binternet precisam passar antes da troca. Preserve o diretório
de backup exibido: ele pode conter snapshots privados montados pelo contêiner.

### Publicação pelo mantenedor

Na pasta extraída **`securitysearch-publication-0.9.20`**:

```fish
fish ./publish-securitysearch-v0.9.20.fish ~/securitysearch
```

O programa verifica a base r1, atualiza os READMEs em inglês/pt-BR e a documentação,
cria um commit somente das alterações previstas, testa a publicação, cria a tag
anotada **`v0.9.20`** e gera o pacote desse commit. Depois pede quatro tokens e
verifica os quatro repositórios antes de qualquer escrita remota. Em cada host,
`main` e tag são enviados atomicamente, sem force. Anexos são enviados a um
rascunho e verificados antes de publicar a release.

Repetir o comando retoma rascunhos compatíveis e hosts pendentes. Tags, notas ou
anexos conflitantes não são substituídos. A publicação entre quatro servidores não
é atômica. Um bundle incremental local também é gerado para recuperação e depende
da base v0.9.19. Tokens não são salvos em arquivos, URLs ou argumentos.

**Publicar não faz deploy da VPS.** Após o commit de documentação, o kit de deploy
r1 antigo terá hashes diferentes para os READMEs; para um deploy posterior, use
`deploy-securitysearch.fish` deste kit de publicação.

`fish ./audit-providers.fish`, no kit de atualização r1, executa separadamente a
matriz real de provedores. Sua aprovação não é presumida por esta publicação.

[Notas bilíngues](docs/RELEASE-0.9.20.md) ·
[Guia de publicação](docs/PUBLISHING-0.9.20.pt-BR.md) ·
[Operação detalhada](docs/UPDATE-0.9.20.pt-BR.md) ·
[Auditoria e limitações](docs/AUDIT-0.9.20.md)

## Validação

`sh scripts/test.sh` requer PHP com curl, DOM/XML, mbstring, APCu, sodium, fileinfo e Imagick, além de Python 3, Node.js, Git e fish para os testes. As dependências de teste são instaladas apenas na imagem descartável de auditoria, não na aplicação em produção.

Docker, VPS, provedores ao vivo e testes dependentes das extensões ausentes não foram executados no ambiente de autoria. O relatório separa testes aprovados de bloqueios de ambiente. Os utilitários históricos da v0.9.19 permanecem disponíveis. Para a v0.9.20, o kit usa `scripts/package-v0.9.20.py` e `scripts/publish-v0.9.20.py`.

Licença: [AGPL-3.0](license.txt).
