# Security Search v0.9.22

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![Página inicial preta](docs/screenshots/securitysearch-0.9.21-home-black.png)

**Renderização histórica da v0.9.21, não uma captura nova da VPS.** O controlador PHP real
gerou o HTML; Chromium renderizou com recursos locais incorporados e scripts
desativados. Recursos da captura: **25**; esta versão usa **26**.

Buscador proxy PHP baseado no [4get](https://git.lolcat.ca/lolcat/4get), mantido para
[SecurityOps](https://securityops.co/). Os provedores externos podem recusar,
limitar ou alterar respostas. Não há garantia de disponibilidade ou ganho de
velocidade em todos os provedores.

## Correção de manutenção r2

Inclui a correção r1 para arquivos-fonte sem `.git` e a r2 para carregar o helper
de temas pelo caminho explícito. A auditoria Docker reúne todas as falhas e
continua recusando a troca caso qualquer teste falhe. Use o kit **0.9.22-r2** sobre
um checkout exato já atualizado para v0.9.22 ou v0.9.22-r1; a versão da aplicação
e o marcador 26 permanecem. [Detalhes](docs/AUDITFIX-0.9.22-r2.md).

## Correção v0.9.22 e temas históricos separados

A fixture de notícias intercepta todas as origens Redlib; bloqueios de rede dos
processos filhos detectam tentativas externas imediatamente. O timeout do cliente
não foi aumentado, nem o cURL de produção desativado. Cache DNS positivo limitado
a hosts fixos reduz trabalho repetido, sem guardar consultas ou resultados. A
primeira resolução DNS síncrona ainda pode ultrapassar o timeout cURL; não se
promete velocidade medida ou prazo absoluto para todos os provedores.

Tron corresponde ao commit histórico. Lain/SecOps originais e prévias ficam em
**pack somente do operador**, fora do Git, acrescentado com `--theme-assets` apenas
ao arquivo privado de deploy. Não são incluídos nos tarballs públicos ou no
Codeberg. O histórico já publicado não é reescrito. Sem o pack, permanecem a paleta
Lain e a alternativa Matrix/SecOps. Consulte os
[comandos completos](docs/OPERATIONS-0.9.22.md) e [guia pt-BR](docs/OPERATIONS-0.9.22.pt-BR.md).

## Busca, notícias e imagens

Google continua com a tentativa limitada e identificada pelo Brave na primeira
busca web/imagens. A paginação não troca silenciosamente de provedor. Falhas
inequívocas de transporte cURL recebem uma pausa curta de oito segundos para a
primeira página; erros de parser e resultados vazios não são classificados como
falhas de rede. Limites existentes de conexão/requisição são preservados.

Reddit tenta **libre.securityops.co → redlib.nadeko.net →
redlib.privacyredirect.com**, em sequência, no máximo três vezes, com até 3,5
segundos por instância e dez segundos no total. Instâncias que falham aguardam
vinte segundos. A origem que respondeu aparece na interface e é mantida nos
links de continuação autenticados. Somente o feed público inicial, sem consulta,
pode ser armazenado por sessenta segundos; pesquisas com palavras-chave não.

**Privacidade:** em uma falha da instância principal, outro operador Redlib pode
receber a consulta e o IP do servidor. Cookies do visitante não são encaminhados.
O formulário e a resposta informam esse comportamento. Configure
`FOURGET_REDLIB_FALLBACKS=false` para usar apenas a instância principal. A lista
oficial de instâncias não comprova conectividade a partir da VPS.

A compatibilidade antiga/nova do Binternet, paginação limitada e uso da prévia
menor realmente retornada são mantidos. Continuam os seis layouts, qualidade,
filtros, paginação manual e rolagem infinita opcional. GIF/WebP animado/APNG
mantêm o poster durante o carregamento da camada animada, com **dois carregando
e quatro ativos**, botão Play/Pause, suspensão fora da tela/aba oculta e respeito
a movimento reduzido/economia de dados. Uma falha não dispara repetição automática.

## Temas e imagem pessoal

![Seletor corrigido](docs/screenshots/securitysearch-0.9.21-theme-picker.png)

Preto continua padrão. Dark, Wine e The Birthday Massacre saem dos seletores;
escolhas antigas migram para Black. O gentoo duplicado foi consolidado. Todos os
dezoito temas têm prévias locais pequenas; Lain e Stop são identificados como
paletas. Seleções nativas usam fundo preto e a cor de destaque do tema.

Tron restaura sua animação em WebP otimizado, aproximadamente 527 KB. Sem o pack opcional, SecOps usa a
animação Matrix já disponível, aproximadamente 1,37 MB, e cores em ciano; **não é
a arte histórica que havia sido removida**. Alternativas estáticas e o controle
de movimento não dependem de JavaScript. Apenas o tema escolhido carrega wallpaper.

**My picture:** selecione, salve e escolha JPEG/PNG/WebP de até 8 MiB. O controle
não pertence a formulário nem tem nome de campo. Um script opcional local lê,
redimensiona e recodifica no navegador: até 24 megapixels na origem, 1920 pixels
na maior dimensão de saída e data URL de até 2 MiB. Não há endpoint de upload
para imagem, nome do arquivo ou EXIF. O padrão é `sessionStorage` da aba,
sujeito à restauração de sessão do navegador. Remember on this device habilita
`localStorage` explicitamente; Remove limpa ambos. Isso não é um cofre criptografado:
um navegador compartilhado ou script da mesma origem pode acessar seu armazenamento.

A frase correta é **Works without JavaScript**. Busca normal e temas nativos
funcionam sem ele; rolagem/animações de imagens e a foto pessoal usam scripts
opcionais locais. A página inicial Black não emite script. No modo Custom, o
script é permitido mas conexões de rede continuam proibidas pelo CSP da inicial.

## Rodapé, Tranco e metadados

O rodapé mantém o endereço onion v3 existente e mostra Tranco discreto à direita,
com a data da lista. A renderização nunca consulta Tranco. Uma tarefa CLI/systemd
separada atualiza os metadados públicos diariamente. **Não é incluído um ranking
inventado:** antes de uma atualização válida aparece unavailable. Cache com mais
de três dias expira; resposta vazia é distinguida. Ranking não é avaliação de
segurança/qualidade. O endereço onion tem formato/checksum válidos; sua
acessibilidade não foi verificada.

Metadados canônicos/sociais, Website microdata, cartão 1200×630 e link do sitemap
foram ajustados. Configurações e buscas privadas continuam noindex. Não se promete
popularidade, posição no Google ou avaliações falsas.

## Atualizar, implantar e publicar

Use o kit correspondente **securitysearch-update-0.9.22-r2**, não os lançadores
antigos com hashes diferentes:

```fish
fish ~/Downloads/securitysearch-update-0.9.22-r2/apply-securitysearch.fish ~/securitysearch
fish ~/Downloads/securitysearch-update-0.9.22-r2/deploy-securitysearch.fish ~/securitysearch --rank-refresh
fish ~/Downloads/securitysearch-update-0.9.22-r2/publish-securitysearch.fish ~/securitysearch
```

São operações separadas. Aplicar a r2 aceita a árvore exata e limpa de v0.9.22 ou v0.9.22-r1 já aplicada
e cria um commit local normal. O deploy preserva SSH **5119**, **root@securityops.co**,
redes e **172.17.0.1:5140 → 80**; não recria Nginx Proxy Manager. Antes da troca,
a suíte offline completa, prontidão e teste real Binternet devem passar. Guarde
backups e rollback. O timer Tranco opcional é instalado somente após deploy bem-sucedido.
Falha de metadados não provoca rollback da aplicação.

Publicar cria/reutiliza a tag anotada **v0.9.22**, gera o pacote do commit real e
verifica hosts selecionados antes da primeira escrita. Rascunhos/arquivos iguais
são retomados; tags/notas/arquivos conflitantes nunca são sobrescritos. Tokens são
solicitados de forma privada, sem aparecer em argumentos, URLs ou arquivos.
Para retomar somente o host .co, **incluindo os anexos da release**:

```fish
fish ~/Downloads/securitysearch-update-0.9.22-r2/publish-securitysearch.fish ~/securitysearch --host git.securityops.co
```

Cada release concluída contém `securitysearch-v0.9.22.tar.gz` e
`securitysearch-v0.9.22.tar.gz.sha256`: código-fonte PHP completo da tag, não
binários ou imagem Docker. O bundle incremental local depende da v0.9.20.
Publicação não faz deploy e os quatro servidores não formam uma transação atômica.

[Codeberg](https://codeberg.org/berkeley/securitysearch/releases/tag/v0.9.22) ·
[GitHub](https://github.com/cristiancmoises/securitysearch/releases/tag/v0.9.22) ·
[SecurityOps .co](https://git.securityops.co/cristiancmoises/securitysearch/releases/tag/v0.9.22) ·
[SecurityOps .com.br](https://git.securityops.com.br/cristiancmoises/securitysearch/releases/tag/v0.9.22)

Os links só funcionam após publicação naquele host. SHA-256 verifica integridade,
não assinatura do autor. Tag anotada não é automaticamente assinada.

## Validação

```sh
sh scripts/test.sh
```

A suíte completa precisa de extensões PHP, Python, Node, Git e fish. O ambiente de
autoria não executou a suíte nativa completa; consulte o [relatório](docs/AUDIT-0.9.22.md).
Testes com fixtures não comprovam provedores reais, upload autenticado ou deploy.
A navegação do Chromium foi bloqueada pela política do ambiente; as imagens acima
são renderizações locais isoladas, não aprovação do teste de navegação/storage.
O teste opcional real está em `tests/browser-experience.py`.

[Notas bilíngues](docs/RELEASE-0.9.22.md) · [Operação/publicação](docs/OPERATIONS-0.9.22.pt-BR.md)

Licença [AGPL-3.0](license.txt). Mantidos API, Invidious, ações de busca, serviços
e **In Code We Trust.**.
