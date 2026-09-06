# Security Search

[English](README.md) | Português do Brasil

O Security Search é um mecanismo de metabusca por proxy, com código aberto e
implantação própria, derivado e reforçado a partir do
[4get](https://git.lolcat.ca/lolcat/4get). A instância de produção é publicada
em [securityops.co](https://securityops.co/).

## Versão atual do código-fonte: v0.9.12

- A animação original Lain fica visível no desktop e celular, com primeiro plano
  legível e opção de fundo estático sem JavaScript. Preferências de movimento/dados
  reduzidos e temas válidos salvos continuam sendo respeitados.
- Seis visualizações de imagens: grade, grade compacta, galeria sem recorte,
  feed amplo, lista e faixa horizontal. Paginação automática limitada a três páginas, respeitando Save-Data
  e prazo de 25 segundos. Links comuns continuam funcionando sem JavaScript.
- As chamadas Google compartilham um orçamento de 25 segundos. HTTP 502/503/504
  transitório permite uma repetição por etapa, no mesmo provedor/IP; desafios
  antiabuso não disparam essa repetição.
- Falhas do provedor retornam HTTP 503 e `Retry-After`. Uma resposta rápida de
  cooldown é identificada como erro, não como busca concluída.
- O Google reutiliza conexões dentro da mesma busca/saída, compartilha a
  inicialização e o cooldown entre seus aliases CSE e limita respostas a 4 MiB.
  Configurações inválidas de proxy falham sem permitir tráfego direto.
- [Operação, auditoria e rota do status](docs/OPERATIONS-0.9.12.pt-BR.md).

- A v0.9.10 corrige o bind de IP dentro do container, diferencia pools de proxy
  ilegíveis, preserva todas as variáveis de proxy no Compose, permite pool
  direto/Tor limitado para Brave e oculta Google API até existir uma chave
  privada utilizável. A busca de imagens ganha controles server-side
  **Grade/Feed amplo** e **Prévia rápida/Original**. Os resultados continuam
  renderizados no servidor e funcionam sem JavaScript.

- A v0.9.8 torna utilizável a configuração de saída do Google/Brave no Compose
  suportado: nomes de pools privados definidos no host são repassados sem
  colocar credenciais no Git. `scripts/check-egress.sh` verifica o IP público e
  o acesso ao Google sem enviar consultas de busca.

- A v0.9.7 recupera grades de imagens quando miniaturas do provedor ficam
  indisponíveis, inicia a descoberta de movimento antes que páginas grandes de
  resultados possam atrasá-la e reproduz GIF, WebP animado e APNG validados sem
  abrir o lightbox. O Brave pode usar primeiro sua fonte redimensionada menor,
  que preserva animação, e manter o original como fallback. Parsers estruturais
  limitados substituem o ImageMagick na validação de animações, enquanto filas
  curtas no servidor e no navegador absorvem rajadas normais da grade.
- O Google agora preserva parâmetros CSE sem consulta por cinco minutos e evita
  repetir inicializações frias por um curto período após uma resposta antiabuso
  reconhecida, inclusive nas chamadas ao endpoint de resultados `element/v1`.
  Resultados da última página de imagens também são preservados, e um
  `tbLargeUrl` inválido usa `tbUrl` como fallback defensivo. Essas mudanças
  reduzem chamadas evitáveis; não contornam desafios de rede do Google ou do
  Brave.

- A v0.9.6 restaura o logotipo rastreado do Security Search nos pacotes limpos,
  impede avisos PHP quando o diretório de banners está vazio e recupera o fundo
  SecOps genuíno `static/misc/secops.gif`. Preferências de movimento reduzido e
  economia de dados recebem um fundo estático.

- A v0.9.5 corrige o empacotamento: os arquivos de versão agora preservam o
  diretório vazio obrigatório `icons/`, usado como cache em tempo de execução,
  mas continuam excluindo os ícones gerados. Busca, provedores, animações, tema
  e interface mantêm a implementação testada da v0.9.4.

- A página inicial prioriza o logotipo e a busca, com apenas Configurações, uma
  indicação curta de privacidade/provedor e dois links discretos para
  [Chat](https://chat.securityops.co/) e
  [SecurityOps Brasil](https://securityops.com.br/).
- Lain é o tema padrão para novos visitantes. A página inicial agora consome
  os tokens de cor do tema ativo, sem escondê-los sob uma segunda paleta; temas
  válidos já salvos continuam tendo precedência, e a versão de assets 16 evita
  reutilização de CSS e fundos antigos.
- Filtros de provedores compatíveis permitem conteúdo NSFW por padrão com
  `config::DEFAULT_NSFW=yes` e `FOURGET_DEFAULT_NSFW=yes`. Um parâmetro da
  requisição ou uma preferência salva ainda pode selecionar `maybe` ou `no`.
- Google continua sendo o provedor padrão de web e imagens. Um cache curto de
  cinco minutos por saída reutiliza apenas os parâmetros CSE de inicialização e
  remove duas chamadas upstream de buscas próximas; consultas e resultados não
  são armazenados. Ausências simultâneas no cache compartilham uma única
  inicialização limitada por APCu. Um token rejeitado recebe apenas uma
  inicialização nova e uma repetição.
- Bloqueios do Google por tráfego incomum/IP não são repetidos nem ocultados.
  Uma página neutra oferece repetir a busca, abrir Configurações e a ação
  explícita **Try Brave**. A consulta nunca é enviada silenciosamente ao Brave.
- Brave permanece selecionável no filtro **Scraper** para web e imagens.
- Google API continua opcional somente para clientes que já possuam
  credenciais; não há chaves Google API incluídas no código-fonte nem em
  produção. A [documentação atual do Google](https://developers.google.com/custom-search/v1/overview)
  informa que a API está fechada a novos clientes e que os clientes atuais
  devem migrar até 1º de janeiro de 2027.
- Imagens carregam páginas adicionais automaticamente por padrão. É possível
  desativar esse comportamento nas Configurações, e o link **Next page**
  continua sendo a alternativa progressiva.

## Arquitetura e limite de confiança

O Security Search é um proxy de busca, não um índice independente da web. O
navegador envia a consulta à instância do Security Search; a instância consulta
o provedor upstream selecionado e devolve o resultado. Assim, o navegador não
faz a requisição normal diretamente ao provedor, mas isso não constitui, por si
só, uma garantia de anonimato.

O operador da instância ainda processa as consultas e controla os registros do
servidor, do proxy reverso e da infraestrutura. O provedor upstream normalmente
vê o endereço de saída da instância — ou do proxy de saída configurado — e pode
limitar, bloquear ou desafiar esse tráfego. Quem opera uma instância deve definir
de forma explícita TLS, retenção de logs, controle de acesso, proteção contra
abuso e uma política de privacidade correta. Hospedar por conta própria muda em
quem se confia; não elimina a necessidade de confiança.

## Requisitos e instalação

Requisitos recomendados:

- Docker com o plugin Docker Compose;
- porta local `5140` disponível, ou ajuste equivalente no Compose;
- acesso de saída aos provedores escolhidos;
- proxy reverso com HTTPS para uma implantação pública.

### Instalação pelo pacote de versão

Mantenha o arquivo e o checksum no mesmo diretório:

```bash
sha256sum -c securitysearch-v0.9.7.tar.gz.sha256
tar -xzf securitysearch-v0.9.7.tar.gz
cd securitysearch-v0.9.7
docker compose up -d --build
docker compose ps
curl -fsSI http://127.0.0.1:5140/
```

Para uma compilação limpa, use `./deploy.sh --fresh`. O padrão publica a porta
`80` somente em `127.0.0.1:5140`. Quando um NPM em contêiner precisar alcançar o
host, defina `SECURITYSEARCH_BIND_ADDRESS` num `.env` de modo 0600 para o endereço
privado da bridge Docker já usado pelo NPM (por exemplo, `172.17.0.1`). Nunca use
o IP público nem `0.0.0.0`; confirme externamente que a porta 5140 está fechada.

### Instalação pelo Git

```bash
git clone https://github.com/cristiancmoises/securitysearch.git
cd securitysearch
docker compose up -d --build
```

Use uma tag de versão em produção, em vez de depender continuamente da ponta da
branch `main`.

## Configuração

A imagem gera `data/config.php` a partir de variáveis `FOURGET_*`. Os padrões de
produção ficam explícitos em `docker-compose.yml`:

```yaml
environment:
  - FOURGET_DEFAULT_THEME=Lain
  - FOURGET_DEFAULT_NSFW=yes
  - FOURGET_DEFAULT_SCRAPER_WEB=google
  - FOURGET_DEFAULT_SCRAPER_IMAGES=google
```

Uma preferência válida salva no navegador tem precedência sobre o respectivo
padrão. Um parâmetro de provedor na URL tem precedência sobre cookie e padrão.
Credenciais, chaves de API e dados privados de proxy não devem ser gravados no
repositório nem incluídos no pacote de versão.

Nos filtros compatíveis com NSFW, o parâmetro explícito `nsfw` tem precedência
sobre o cookie salvo, que tem precedência sobre `DEFAULT_NSFW`. O padrão de
produção `yes` permite resultados NSFW; o usuário pode salvar `maybe` ou `no`
nas Configurações. O comportamento exato ainda depende do provedor upstream.

Falhas de provedor não acionam fallback silencioso. Nas páginas de erro de web
e imagens, **Try Brave** é uma ação iniciada pelo usuário que preserva consulta
e filtros e define `scraper=brave`. Isso é relevante para privacidade: o Brave
só recebe a consulta depois dessa escolha explícita (ou de uma seleção comum no
filtro).

Configurações importantes:

- `FOURGET_DEFAULT_THEME=Lain`: tema usado quando não há cookie de tema válido;
- `FOURGET_DEFAULT_NSFW=yes`: permite NSFW nos filtros compatíveis por padrão;
- `FOURGET_DEFAULT_SCRAPER_WEB=google`: provedor web padrão;
- `FOURGET_DEFAULT_SCRAPER_IMAGES=google`: provedor de imagens padrão;
- `FOURGET_PROXY_GOOGLE`: nome de um pool de proxy privado configurado para o Google;
- `FOURGET_PROXY_BRAVE`: nome de um pool de proxy privado configurado para o Brave,
  quando necessário;
- `FOURGET_PROXY_GOOGLE_CSE`, `FOURGET_PROXY_GOOGLE_API` e
  `FOURGET_PROXY_DDG`: pools opcionais também repassados pelo Compose;
- `FOURGET_GOOGLE_CX_ENDPOINT`: endpoint da pesquisa programável do Google, se
  uma implantação precisar substituir o valor padrão.

Para um pool privado, descomente o volume opcional somente leitura
`./data/proxies:/var/www/html/4get/data/proxies:ro` no Compose. Mantenha o
arquivo não rastreado com credenciais protegido no host; nunca o publique. No
container suportado, permita leitura ao GID 101 do Apache (por exemplo,
`root:101` com modo `0640`); um arquivo `0600` exclusivo do root não pode ser
lido durante as buscas.

Se a VPS tiver vários endereços roteados, `FOURGET_SOURCE_IP_GOOGLE` e
`FOURGET_SOURCE_IP_BRAVE` vinculam requisições diretas a um IPv4 ou IPv6
atribuído. Isso seleciona um endereço local existente; não é um proxy.

Se o endereço de saída do VPS estiver limitado pelo Google, defina
`FOURGET_PROXY_GOOGLE` (ou `FOURGET_PROXY_BRAVE`) com o nome do pool privado no
ambiente do host e valide-o antes de recriar o serviço:

```bash
./scripts/check-egress.sh /caminho/privado/google-egress.txt
docker compose up -d --force-recreate
```

O verificador faz somente sondas do IP público e do `robots.txt` do Google, sem
enviar consultas de busca ou exibir credenciais. Use apenas um proxy/VPN
legítimo e controlado; encaminhar consultas por outra instância 4get ou por
listas públicas aleatórias não é privado nem confiável.

Leia [docs/PROVIDERS.md](docs/PROVIDERS.md) para precedência e limitações dos
provedores e [docs/configure.md](docs/configure.md) para a configuração geral.

## Provedores de busca

O provedor visível `google` usa o transporte compatível com Google Programmable
Search incluído no projeto. Ele é o padrão de web e imagens porque não exige um
renderizador Firefox/4play externo. O Google API opcional usa arquivo de chave
separado e excluído dos pacotes; nunca publique essa chave.

O transporte reutiliza parâmetros CSE validados por no máximo cinco minutos para
cada combinação de backend, CX e saída de rede. Esse cache APCu curto não contém
consultas nem documentos de resultado e evita as duas chamadas de bootstrap em
buscas próximas. Para páginas seguintes, o backend preserva a requisição e o
proxy de saída em estado NPT comprimido e protegido por criptografia
autenticada. Um erro reconhecido de token expirado ou rejeitado apaga a entrada,
obtém um token novo e permite uma única repetição.

Uma falha comum de bootstrap é compartilhada por cinco segundos; uma falha
antiabuso reconhecida é compartilhada por 30 segundos, evitando que solicitações
concorrentes insistam no Google. O lock do proprietário expira em 60 segundos;
concorrentes aguardam a publicação por até seis segundos e então falham
rapidamente sem duplicar o bootstrap. O cooldown antiabuso de 30 segundos também
abrange `cse/element/v1`. Na busca de imagens, resultados são processados antes
de decidir se existe cursor seguinte, portanto a última página não é descartada
quando ainda contém cartões; um `tbLargeUrl` ausente ou inválido recua para um
`tbUrl` válido e suas dimensões correspondentes.

Tráfego incomum, tráfego automatizado, CAPTCHA ou bloqueio do IP de saída não é
erro de token: não há repetição, tentativa de contornar a proteção nem consulta
automática a outro provedor. A interface informa que o Google limitou
temporariamente a instância e oferece ações explícitas. O próprio Google inclui
serviços automatizados, scrapers, VPNs e redes compartilhadas entre as possíveis
causas na [orientação oficial sobre tráfego incomum](https://support.google.com/websearch/answer/86640?hl=pt-BR).

Brave é a alternativa principal disponível no seletor para web e imagens. Um
IP de datacenter pode receber CAPTCHA, proof-of-work ou bloqueio de faixa do
Brave. No acesso direto, um desafio proof-of-work reconhecido encerra a busca
após a primeira tentativa. Somente um pool configurado pode girar para outro
endereço, com no máximo três tentativas limitadas no Brave; o scraper não
resolve o desafio, não entra em loop e não troca de provedor. Um pool legítimo
em `FOURGET_PROXY_BRAVE` pode ser necessário para disponibilidade consistente. A
existência do seletor ou de **Try Brave** não garante que o Brave aceitará o IP
do VPS. Não se deve afirmar que um provedor está operacional antes de um teste
real a partir do host de produção.

`google_api` é uma opção separada para operadores que já possuem credenciais da
Custom Search JSON API. As chaves privadas ficam em
`data/api_keys/google_api.txt`, caminho excluído do pacote. A produção tem zero
chaves configuradas. Selecionar essa opção sem provisionamento deve retornar
erro de configuração; ela não é fallback automático de `google`. Segundo a
[documentação oficial](https://developers.google.com/custom-search/v1/overview),
a API está fechada a novos clientes e os clientes existentes devem migrar até
1º de janeiro de 2027.

Clientes da API podem escolher explicitamente o scraper:

```text
/api/v1/web?s=seguranca&scraper=google
/api/v1/images?s=privacidade&scraper=brave
```

Sem `scraper`, a API usa os mesmos padrões Google da interface. A API também não
muda de provedor silenciosamente. Para validar sucesso, confira `status=ok` e um
array de resultados não vazio; HTTP 200 sozinho também pode conter erro do
upstream.

## Interface e responsividade

A hierarquia da página inicial é intencionalmente curta:

1. uma ação utilitária de Configurações;
2. o logotipo Security Search;
3. o campo e a ação principal de busca;
4. uma indicação compacta: Google padrão, Brave disponível;
5. links de texto discretos para `chat.securityops.co` e `securityops.com.br`.

Não há grade promocional de botões ou cartões competindo com a busca. O fluxo
principal funciona sem JavaScript, possui foco visível por teclado, alvos
adequados para toque e layout preparado de 320 px até telas desktop. A página
evita foco automático que abriria o teclado móvel sem solicitação. O contrato
completo da interface está em [docs/UI.md](docs/UI.md).

Na v0.9.4, `static/style.css` fornece a base, o CSS do tema selecionado fornece
tokens compartilhados e os componentes da página inicial consomem esses tokens
com valores de segurança. Sem cookie, com cookie inválido ou com tema
inexistente, o resultado é `Lain`; `Dark` e outros temas válidos continuam
preservados. A v0.9.4 introduziu a invalidação `v11`; a v0.9.7 usa
`/static/themes/Lain.css?v16` para atualizar o tema, os controladores de
imagem e o fundo restaurado.

Falhas de scraper usam o título neutro **Search provider unavailable**. O texto
do upstream é escapado, e as ações oferecem repetir a mesma busca, abrir
Configurações e, em web/imagens, escolher Brave explicitamente. Mensagens de
erro visíveis ao usuário não contêm linguagem ofensiva.

## Rolagem contínua de imagens

O resultado de imagens sempre inclui um link **Next page** renderizado pelo
servidor quando o provedor devolve um token de continuação. A rolagem contínua é
uma melhoria opcional sobre esse fluxo:

- sem o cookie `image_infinite`, ou com `image_infinite=yes`, um navegador com
  `IntersectionObserver` e `fetch` busca a próxima página ao se aproximar do
  fim da grade;
- em **Settings → Load more image results automatically while scrolling → No**,
  o navegador salva `image_infinite=no` e não carrega o script de melhoria;
- sem JavaScript ou sem essas APIs, o link continua funcionando normalmente;
- se uma carga automática falhar, novas tentativas automáticas param; uma
  mensagem de estado e o link **Restart image search** (reiniciar busca de
  imagens) reiniciam a consulta na primeira página, preservando busca e filtros
  e removendo o token já usado.

Esse comportamento mantém a paginação progressiva e não transforma uma falha
do upstream em falha total da página.

Candidatos GIF, WebP e APNG começam com a miniatura comum do provedor como
poster, carregada de forma preguiçosa. Se essa fonte falhar, a grade tenta até
duas outras fontes pelo proxy antes de exibir o estado local **Image
unavailable**. Um indício de movimento em URL, filtro ou metadado MIME/formato
compatível seleciona a fonte preferencial. O Google normalmente usa o original;
o Brave pode preferir sua URL redimensionada menor, que preserva animação, e
manter o original como fallback. Toda URL `.gif`, `.webp` ou `.apng` é candidata,
inclusive WebP comum; a validação estrutural devolve WebP estático ao poster.
Um indício WebP é tratado como baixa confiança: ainda recebe validação e o
fallback de fonte do provedor, mas não gasta outra requisição automática com
cache busting depois de falhar. Trabalho iniciado pelo usuário continua em
primeiro lugar; a fila automática prioriza GIF e APNG em relação a WebP. URLs
`data:` são excluídas.

O controlador é carregado cedo e inicia a descoberta até 700 px ao redor da
área visível. Ele busca a fonte pelo caminho local de privacidade
`/proxy?...&s=animated`, sem clique no lightbox e sem contato direto do navegador
com o host do resultado. O endpoint limita a saída descomprimida a 32 MiB, pede
explicitamente GIF/APNG/PNG/WebP ao upstream e só repassa bytes depois que um
parser estrutural limitado comprova pelo menos dois frames. GIF, WebP animado e
APNG são inspecionados sem decodificação pelo ImageMagick. Também há limites de
1.000 frames, 16.384 pixels por eixo, 40 MP por frame, 250 milhões de
pixel-frames e 8.192 chunks de contêiner, além de um limite próprio para
sub-blocos GIF.

No navegador, no máximo três originais são validados ao mesmo tempo no desktop
ou dois em dispositivos móveis/de ponteiro grosso. Se a fonte preferencial
falhar, o cartão tenta primeiro o fallback do provedor. Um candidato não WebP
elegível (normalmente GIF/APNG) pode fazer depois uma única repetição atrasada
com cache busting; WebP de baixa confiança não faz essa repetição. A falha final
mantém o poster utilizável. O
limite controla cargas, não reprodução. Uma retenção LRU suave mantém até 36
animações concluídas no desktop ou 18 no móvel. Ela nunca interrompe uma carga
em andamento nem remove um candidato próximo da área visível, portanto o limite
pode ser ultrapassado temporariamente; o item concluído mais antigo e fora da
tela só volta ao poster quando isso é seguro. Ao retornar, o observador prepara
automaticamente a animação removida outra vez. Novos resultados da rolagem
contínua são registrados; `prefers-reduced-motion` desativa animações e a
economia de dados desativa a ativação automática. SVG, vídeo, gifv, URLs `data:`
e formatos fora da lista GIF/WebP/APNG não são candidatos a movimento.

A aplicação admite no máximo 900 solicitações de preview animado por endereço
de cliente a cada minuto. Três solicitações podem executar busca/validação cara,
enquanto até nove aguardam uma vaga por no máximo três segundos. Rejeições
causadas somente por fila cheia ou expirada não consomem a cota; o endpoint
indica repetição após dois segundos e o navegador espera de 2,2 a 3,0 segundos
antes da única repetição com cache busting de um candidato não WebP elegível.
Esses limites cooperam com a fila do navegador sem impor teto de três animações
em reprodução.

## Desempenho

A imagem de produção habilita PHP OPcache, compressão HTTP e cache de arquivos
estáticos. Miniaturas e favicons usam carregamento preguiçoso (`loading=lazy`) e
decodificação assíncrona. Para miniaturas, o proxy limita a entrada upstream a
16 MiB. Ele só repassa um JPEG diretamente com no máximo 128 KiB e 512 pixels
por eixo, ou GIF/WebP/APNG animado e validado estruturalmente com no máximo 1,5
MiB, 2.048 pixels por eixo e 4 MP. Isso evita conversão ImageMagick desnecessária
sem permitir que fallbacks de imagens originais aumentem excessivamente o peso
da página, e preserva pequenas miniaturas animadas nativas. Formatos capazes de
animação que sejam estáticos ou malformados, AVIF e entradas maiores continuam
no caminho limitado do ImageMagick. Antes de decodificar, esse fallback aceita
somente JPEG, PNG, GIF, WebP ou AVIF. O ImageMagick fica limitado a um frame,
16.384 pixels por eixo, 40 MP, 64 MiB para memória e para map, nenhum cache em
disco, uma thread e dez segundos. A policy do contêiner desabilita delegates,
filtros, leitura indireta de paths e todos os coders por padrão antes de liberar
o conjunto raster restrito. Assim, um GIF animado acima de 1,5 MiB pode falhar
como conversão limitada do poster; essa falha não bloqueia a descoberta de
movimento, e `/proxy?...&s=animated` ainda pode validar e reproduzir até 32 MiB
automaticamente, sem clique. Requisições genéricas de imagens derivam um Referer
limitado da URL pública já validada da fonte; Referers específicos e revisados
de provedores também passam por limite de tamanho e rejeição de CR/LF, em vez de
aceitar texto arbitrário de header. A página Lain usa o fundo rastreado
`static/misc/lain.gifv` também no celular; a opção de fundo estático e preferências de
movimento/dados reduzidos oferecem uma alternativa CSS. O Google reutiliza parâmetros CSE
validados por até cinco minutos por CX e saída, compartilhados pelos aliases Google/CSE, sem armazenar consultas
ou resultados.
Google usa até cinco segundos para conexão e um orçamento compartilhado de 25
segundos; os limites de Brave continuam específicos daquele provedor.
Isso normalmente elimina as chamadas ao HTML e ao script de inicialização em
buscas próximas e reduz o volume upstream. Uma rejeição reconhecida do token em
cache apaga a entrada, faz uma inicialização nova e repete apenas uma vez. Uma
falha comum de bootstrap é compartilhada por cinco segundos e uma falha
antiabuso reconhecida por 30 segundos. O lock do proprietário da inicialização
expira sozinho após 60 segundos; concorrentes aguardam por até seis segundos
por um resultado publicado e então falham rapidamente, sem iniciar outra
inicialização. O mesmo cooldown de 30 segundos abrange respostas antiabuso do
`cse/element/v1`, evitando insistência imediata pela mesma saída. Tráfego incomum
e CAPTCHA nunca são repetidos nem classificados como erro de token. O NPT
criptografado mantém a afinidade com o proxy. A última página de imagens é
processada antes da decisão sobre um cursor seguinte, e `tbLargeUrl` inválido
recua para `tbUrl` válido. Pacotes de versão ficam fora do
contexto Docker. O acabamento das páginas de resultado usa CSS versionado e
reutilizável, e preloads de fontes ausentes foram removidos. A rolagem contínua
busca uma página por vez quando necessário; ela não pré-carrega indefinidamente
toda a coleção no primeiro acesso.
Originais animados da grade ficam sujeitos ao limite e à ativação por viewport
descritos acima, mas ainda podem consumir mais banda do que miniaturas estáticas.

As mudanças de CSS, cache, carregamento preguiçoso e contexto de build são
otimizações de entrega, não promessas de benchmark. Latência e vazão dependem
do VPS, do provedor selecionado, de limites e desafios do upstream, do pool de
proxy e da rede. Valide o desempenho no mesmo host e caminho de rede da produção
e inspecione os logs por avisos ou erros do PHP.

## Comparação factual

Os produtos abaixo têm limites de confiança diferentes. “Proxy” significa que
um intermediário processa a consulta; não significa que o intermediário seja
automaticamente confiável ou que o usuário esteja anônimo. As linhas de
serviços hospedados resumem as políticas publicadas pelos próprios provedores,
e não uma auditoria independente. Fontes consultadas em 2026-09-01.

| Produto | Modelo de resultados e controle | Web/imagens e base sem JavaScript | UX de imagens e controle de conteúdo explícito | Limite de dados publicado e restrições de saída |
|---|---|---|---|---|
| **Security Search** | Fork auto-hospedável do 4get com scrapers upstream selecionáveis; o operador controla configuração, logs e proxy de saída. Não possui índice web independente. Google é o padrão configurado, não uma garantia de disponibilidade. | Web e imagens renderizadas no servidor funcionam sem JavaScript; carga contínua e movimento inline são melhorias progressivas. | Paginação automática padrão, fallback **Next page** e reprodução automática de GIF/WebP/APNG validados. `nsfw=yes` é o padrão local quando o provedor aceita o filtro. | O upstream normalmente vê a saída da instância/proxy; o operador ainda processa consultas. Google e Brave podem desafiar redes de VPS, e uma falha nunca reenvia silenciosamente a consulta. [Arquitetura](#arquitetura-e-limite-de-confiança), [provedores](docs/PROVIDERS.md), [UI](docs/UI.md). |
| **4get upstream** | Proxy multiprovedor aberto e auto-hospedável, com proxy por scraper; controle e logs pertencem ao operador da instância. | A lista oficial cobre web, imagens, vídeo, notícias e outras categorias e informa que a interface não exige JavaScript. | Comportamento de imagens e filtros depende do scraper e da revisão implantada. | Provedores veem a saída da instância/proxy; privacidade e retenção dependem do operador escolhido. [Repositório e lista oficial de recursos](https://git.lolcat.ca/lolcat/4get). |
| **Google Search** | Rastreador, índice e ranking hospedados pelo Google; não é auto-hospedável. | Experiências hospedadas de web e imagens, controladas pelo Google. | SafeSearch oferece Filtrar, Desfocar e Desativar, sujeito às políticas de conta, dispositivo ou rede. | A política do Google diz que a atividade coletada pode incluir consultas, interações e dados de requisição/dispositivo como IP. [Como a Busca funciona](https://developers.google.com/search/docs/fundamentals/how-search-works), [SafeSearch](https://support.google.com/websearch/answer/510?hl=pt-BR), [Política de Privacidade](https://policies.google.com/privacy?hl=pt-BR). |
| **Microsoft Bing** | Rastreador e índice hospedados pela Microsoft; não é auto-hospedável. | Experiências hospedadas de web, imagens, vídeo e outras categorias. | Bing SafeSearch oferece Estrito, Moderado e Desativado. | A Microsoft documenta processamento e controles do histórico de pesquisa no painel de privacidade. [Como o Bing entrega resultados](https://support.microsoft.com/en-us/bing/how-bing-delivers-search-results), [SafeSearch](https://support.microsoft.com/en-us/bing/turn-bing-safesearch-on-or-off), [histórico de pesquisa](https://support.microsoft.com/en-US/accounts-billing/security/search-history-on-the-privacy-dashboard). |
| **DuckDuckGo** | Serviço hospedado com DuckDuckBot e vários índices; informa que links tradicionais e imagens vêm em grande parte do Bing. | Web e imagens, além de variantes HTML/Lite sem JavaScript e com menos recursos. | Safe Search oferece estrito, moderado e desativado; parâmetros de URL também controlam carga automática de imagens/resultados. | O DuckDuckGo afirma não salvar histórico pessoal de busca e intermediar pedidos a parceiros sem IP ou identificadores únicos do usuário. [Origem dos resultados](https://duckduckgo.com/duckduckgo-help-pages/results/sources), [Safe Search](https://duckduckgo.com/duckduckgo-help-pages/features/safe-search), [privacidade da busca](https://duckduckgo.com/duckduckgo-help-pages/search-privacy). |
| **Brave Search** | Rastreador e índice independentes hospedados pelo Brave; não é auto-hospedável. A mistura opcional com Google é escolha separada. | Modos hospedados de web e imagens. | Safe Search oferece Desativado, Moderado e Estrito. | O Brave descreve privacidade por padrão e processamento temporário de IP para integridade. O scraper do Security Search ainda pode receber PoW/CAPTCHA. [Visão geral](https://search.brave.com/help), [Safe Search](https://search.brave.com/help/safesearch), [aviso de privacidade](https://search.brave.com/help/privacy-policy). |
| **Yandex Search** | Rastreador, base indexada e ranking hospedados pelo Yandex; não é auto-hospedável. | Busca hospedada de web e imagens. | A filtragem oferece Família, Moderado e Sem filtro. | A política do Yandex cobre entrega de resultados, personalização, publicidade, histórico e outras informações pessoais; pedidos diretos ou por proxy continuam sujeitos aos controles de rede. [Como a indexação funciona](https://www.yandex.com/support/webmaster/en/yandex-indexing/site-indexing), [configurações de busca](https://yandex.com/support/search/en/search-results/settings), [Política de Privacidade](https://yandex.com/legal/confidential/en/). |
| **Startpage** | Intermediário hospedado que envia consultas a parceiros como Google e Bing; não possui índice web independente nem é auto-hospedável. | Busca hospedada de web e imagens; Anonymous View opcional também intermedeia a navegação no destino. | Filtros de imagem incluem tamanho, cor, tipo—incluindo GIF animado—e licença. | O Startpage afirma não registrar buscas comuns nem IPs, sujeito à exceção antiabuso da política; miniaturas passam por proxy. [Relação com parceiros](https://support.startpage.com/hc/en-us/articles/4522435533844-What-is-the-relationship-between-Startpage-and-your-search-partners-like-Google-and-Microsoft-Bing), [Política de Privacidade](https://safe.startpage.com/en/privacy-policy/), [filtros de imagem](https://support.startpage.com/hc/en-us/articles/5319090860052-Image-filters). |

## Criar uma versão

Com todas as mudanças rastreadas já commitadas e a árvore de trabalho limpa:

```bash
git diff --check
./release.sh 0.9.7
(cd dist && sha256sum -c securitysearch-v0.9.7.tar.gz.sha256)
git tag -a v0.9.7 -m "Security Search v0.9.7"
```

O script usa `git archive`, respeita `.gitattributes` e acrescenta somente o
diretório vazio obrigatório `icons/`, que o Git não consegue rastrear:

```text
dist/securitysearch-v0.9.7.tar.gz
dist/securitysearch-v0.9.7.tar.gz.sha256
```

Antes de publicar, faça lint de todos os arquivos PHP na imagem, compile sem
cache, inicie o contêiner, teste saúde, cabeçalhos, interface, Configurações,
Google/Brave em web e imagens, API, rolagem contínua e fallback sem JavaScript.
Valide cartões/arrays de resultados: HTTP 200 sozinho também pode representar
uma página de erro. A criação desses arquivos e comandos de publicação não
significa que a tag ou versão remota já foi publicada.

Confira os destinos com `git remote -v`. Os alvos de publicação e suas URLs
configuradas sem credenciais embutidas são:

- `origin` — `git@github.com:cristiancmoises/securitysearch.git`;
- `codeberg` — `git@codeberg.org:berkeley/securitysearch.git`;
- `securityops` — `https://git.securityops.co/cristiancmoises/securitysearch.git`;
- `securityops_br` — `https://git.securityops.com.br/cristiancmoises/securitysearch.git`.

A publicação v0.9.4 reescreveu o histórico sanitizado. A v0.9.7 deve preservar
esse histórico e publicar o inventário exato `main` mais as tags `v0.9.0` a
`v0.9.7`. Siga exatamente o procedimento com lease por ref, push atômico,
imutabilidade de tags e verificação de OID em
[docs/RELEASE.md](docs/RELEASE.md) para cada remoto.

Valide os quatro separadamente. Se credencial, permissão ou rede falhar em um,
registre esse bloqueio mesmo que o outro funcione; não afirme que o Git remoto
ou a versão hospedada foi publicada sem enxergar commit e tag naquele destino.

## Implantação no IONOS com Evelin

Envie o pacote e o checksum usando o perfil aprovado, sem copiar credenciais
para scripts:

```bash
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.7.tar.gz \
  remote:/srv/evelin/securitysearch-v0.9.7.tar.gz
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.7.tar.gz.sha256 \
  remote:/srv/evelin/securitysearch-v0.9.7.tar.gz.sha256
ev --config /home/berkeley/.evelin/client.toml shell
```

No VPS, valide o pacote, crie uma árvore irmã limpa e arquive a árvore ativa
exata antes de alterá-la:

```bash
cd /srv/evelin
sha256sum -c securitysearch-v0.9.7.tar.gz.sha256

rollback_stamp=$(date -u +%Y%m%dT%H%M%SZ)
rollback_archive=/root/security-search-pre-v0.9.7-${rollback_stamp}.tgz
old_tree=/root/security-search-update-old-${rollback_stamp}
release_tree=/root/security-search-v0.9.7
test -d /root/security-search-update
test ! -e "$old_tree"
test ! -e "$release_tree"

umask 077
tar -czf "$rollback_archive" -C /root security-search-update
test -s "$rollback_archive"
chmod 600 "$rollback_archive"

install -d -m 0750 "$release_tree"
tar -xzf securitysearch-v0.9.7.tar.gz \
  --strip-components=1 \
  -C "$release_tree"
test -f "$release_tree/docker-compose.yml"
test -f "$release_tree/Dockerfile"

previous_image_id=$(docker image inspect --format '{{.Id}}' security-search:latest)
test -n "$previous_image_id"
docker image tag "$previous_image_id" security-search:pre-v0.9.7

cd /root/security-search-v0.9.7
umask 077
printf 'SECURITYSEARCH_BIND_ADDRESS=172.17.0.1\n' > .env
chmod 600 .env
docker compose build --no-cache --pull

cd /root
docker compose -f /root/security-search-update/docker-compose.yml \
  down --remove-orphans
mv /root/security-search-update "$old_tree"
mv /root/security-search-v0.9.7 /root/security-search-update
cd /root/security-search-update
docker compose up -d --no-build
docker compose ps
app_endpoint=$(docker compose port security-search 80 | tail -n 1)
curl -fsSI "http://$app_endpoint/"
```

O caminho de produção atual é `/root/security-search-update`; o layout antigo
de exemplo em `/opt/securitysearch/releases` não descreve este VPS. Não extraia
o pacote sobre a árvore antiga. O build ocorre na árvore irmã enquanto o
contêiner antigo atende; somente depois o contêiner é parado e os dois
diretórios são renomeados atomicamente no mesmo sistema de arquivos.

Antes do build, inventarie arquivos exclusivos de runtime e aprove uma lista
exata. Não copie `.git`, cache, `data/config.php` gerado ou todo o diretório
`data/`. A revisão atual da produção não encontrou arquivos de chave da API do
Google, portanto não há `data/api_keys/google_api.txt` a preservar. Copie um
override privado do Compose, arquivo de ambiente, credencial de proxy ou outro
segredo somente se ele existir, for necessário e tiver sido revisado
individualmente, mantendo permissões restritas.

Depois, confirme `/static/themes/Lain.css?v16`, o tipo CSS, cartões reais de
web/imagens, arrays `status=ok` não vazios na API, Brave separadamente, logs sem
avisos/fatais PHP e HTTP público em `securityops.co` e
`securityops.com.br`. Use um User-Agent semelhante ao de navegador nos curls
HTML para `/web` e `/images`, por exemplo
`Mozilla/5.0 (release-smoke-test)`; a API não precisa desse cabeçalho. Se o
Google retornar tráfego incomum, valide a mensagem neutra e **Try Brave**, mas
não contabilize a página como resultados Google. Só remova uma versão antiga
e a tag da imagem de rollback depois desses testes. Mantenha o `.tgz` exato
pré-deploy como o único arquivo de rollback desta versão. Consulte
[docs/RELEASE.md](docs/RELEASE.md) e
[docs/BUILD-LOCATION.md](docs/BUILD-LOCATION.md).

Se uma verificação obrigatória falhar, restaure o diretório e a imagem
preservados usando o timestamp exato registrado no corte:

```bash
cd /root
docker compose -f /root/security-search-update/docker-compose.yml down
mv /root/security-search-update /root/security-search-update-failed-v0.9.7
mv /root/security-search-update-old-YYYYMMDDTHHMMSSZ \
  /root/security-search-update
docker image tag security-search:pre-v0.9.7 security-search:latest
cd /root/security-search-update
docker compose up -d --no-build
```

Se o diretório antigo não estiver disponível, restaure o `.tgz` pré-deploy
somente depois de confirmar que `/root/security-search-update` não existe.

## Documentação relacionada

- [Interface e responsividade](docs/UI.md)
- [Provedores e paginação de imagens](docs/PROVIDERS.md)
- [Versão, verificação, rollback e implantação](docs/RELEASE.md)
- [Onde compilar](docs/BUILD-LOCATION.md)
- [Migração do fork](MIGRATION.md)

O projeto preserva a licença AGPLv3-only do 4get. Verifique o arquivo
[license.txt](license.txt) antes de redistribuir ou oferecer uma versão modificada pela
rede.
