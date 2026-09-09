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

## Aplicar, implantar e publicar

No kit `securitysearch-update-0.9.20`:

```fish
fish ./apply-securitysearch.fish ~/securitysearch
fish ./deploy-securitysearch.fish ~/securitysearch
fish ./push-securitysearch.fish ~/securitysearch
```

A aplicação exige `main` limpo, verifica os arquivos da base e cria um único commit local com sua identidade Git. Alterações do Codex, arquivos não rastreados e commits conflitantes não são apagados: o script para, sem reset ou stash automático.

O deploy envia o fonte desse commit para **root@securityops.co**, SSH **5119**, preservando **172.17.0.1:5140 → 80**, redes e configurações privadas compatíveis. Não recria nem altera o Nginx Proxy Manager. Um layout de montagem ou porta diferente é recusado antes da troca.

Antes da troca, o atualizador exige a suíte offline completa em contêiner descartável sem rede, verifica o candidato e executa uma busca real `teste` no Binternet a partir da VPS. Havendo continuação, ela é verificada. Se uma dessas etapas falhar, o serviço anterior continua funcionando. Falha após a troca aciona restauração do contêiner anterior. Não apague o backup exibido: a versão nova pode montar seus snapshots de dados privados.

O envio aos quatro repositórios pede um token por host no terminal, verifica o histórico de todos e só permite fast-forward de `main`. Não altera tags, releases ou os remotes locais. A publicação entre servidores não é atômica; falhas parciais são informadas e podem ser reconciliadas repetindo o comando.

`fish ./audit-providers.fish` executa, separadamente, uma matriz real e sequencial dos provedores habilitados, usando uma busca neutra por combinação de provedor e página. O JSON informa estado, quantidade e tempo, sem despejar resultados, tokens ou credenciais. Resultado vazio ou indisponível não é marcado como busca bem-sucedida.

[Operação detalhada](docs/UPDATE-0.9.20.pt-BR.md) · [Auditoria e limitações](docs/AUDIT-0.9.20.md)

## Validação

`sh scripts/test.sh` requer PHP com curl, DOM/XML, mbstring, APCu, sodium, fileinfo e Imagick, além de Python 3, Node.js, Git e fish para os testes. As dependências de teste são instaladas apenas na imagem descartável de auditoria, não na aplicação em produção.

Docker, VPS, provedores ao vivo e testes dependentes das extensões ausentes não foram executados no ambiente de autoria. O relatório separa testes aprovados de bloqueios de ambiente. Os utilitários antigos de publicação continuam disponíveis apenas como histórico da v0.9.19; não os use para publicar a v0.9.20.

Licença: [AGPL-3.0](license.txt).
