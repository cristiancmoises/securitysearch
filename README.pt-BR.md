# Security Search

[English](README.md) | Português do Brasil

O Security Search é um mecanismo de metabusca por proxy, com código aberto e
implantação própria, derivado e reforçado a partir do
[4get](https://git.lolcat.ca/lolcat/4get). A instância de produção é publicada
em [securityops.co](https://securityops.co/).

## Versão atual do código-fonte: v0.9.6

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
  [SecurityTops](https://securitytops.co/) e
  [SecurityOps Brasil](https://securityops.com.br/).
- SecOps é o tema padrão para novos visitantes. A página inicial agora consome
  os tokens de cor do tema ativo, sem escondê-los sob uma segunda paleta; temas
  válidos já salvos continuam tendo precedência, e a versão de assets 12 evita
  reutilização de CSS e fundos antigos.
- Filtros de provedores compatíveis permitem conteúdo NSFW por padrão com
  `config::DEFAULT_NSFW=yes` e `FOURGET_DEFAULT_NSFW=yes`. Um parâmetro da
  requisição ou uma preferência salva ainda pode selecionar `maybe` ou `no`.
- Google continua sendo o provedor padrão de web e imagens. Um cache curto de
  90 segundos por saída reutiliza apenas os parâmetros CSE de inicialização e
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
sha256sum -c securitysearch-v0.9.6.tar.gz.sha256
tar -xzf securitysearch-v0.9.6.tar.gz
cd securitysearch-v0.9.6
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
  - FOURGET_DEFAULT_THEME=SecOps
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

- `FOURGET_DEFAULT_THEME=SecOps`: tema usado quando não há cookie de tema válido;
- `FOURGET_DEFAULT_NSFW=yes`: permite NSFW nos filtros compatíveis por padrão;
- `FOURGET_DEFAULT_SCRAPER_WEB=google`: provedor web padrão;
- `FOURGET_DEFAULT_SCRAPER_IMAGES=google`: provedor de imagens padrão;
- `FOURGET_PROXY_GOOGLE`: nome de um pool de proxy privado configurado para o Google;
- `FOURGET_PROXY_BRAVE`: nome de um pool de proxy privado configurado para o Brave,
  quando necessário;
- `FOURGET_GOOGLE_CX_ENDPOINT`: endpoint da pesquisa programável do Google, se
  uma implantação precisar substituir o valor padrão.

Para um pool privado, descomente o volume opcional somente leitura
`./data/proxies:/var/www/html/4get/data/proxies:ro` no Compose. Mantenha o
arquivo não rastreado com credenciais protegido no host; nunca o publique.

Leia [docs/PROVIDERS.md](docs/PROVIDERS.md) para precedência e limitações dos
provedores e [docs/configure.md](docs/configure.md) para a configuração geral.

## Provedores de busca

O provedor visível `google` usa o transporte compatível com Google Programmable
Search incluído no projeto. Ele é o padrão de web e imagens porque não exige um
renderizador Firefox/4play externo. O Google API opcional usa arquivo de chave
separado e excluído dos pacotes; nunca publique essa chave.

O transporte reutiliza parâmetros CSE validados por no máximo 90 segundos para
cada combinação de backend, CX e saída de rede. Esse cache APCu curto não contém
consultas nem documentos de resultado e evita as duas chamadas de bootstrap em
buscas próximas. Para páginas seguintes, o backend preserva a requisição e o
proxy de saída em estado NPT comprimido e protegido por criptografia
autenticada. Um erro reconhecido de token expirado ou rejeitado apaga a entrada,
obtém um token novo e permite uma única repetição.

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
5. links de texto discretos para `securitytops.co` e `securityops.com.br`.

Não há grade promocional de botões ou cartões competindo com a busca. O fluxo
principal funciona sem JavaScript, possui foco visível por teclado, alvos
adequados para toque e layout preparado de 320 px até telas desktop. A página
evita foco automático que abriria o teclado móvel sem solicitação. O contrato
completo da interface está em [docs/UI.md](docs/UI.md).

Na v0.9.4, `static/style.css` fornece a base, o CSS do tema selecionado fornece
tokens compartilhados e os componentes da página inicial consomem esses tokens
com valores de segurança. Sem cookie, com cookie inválido ou com tema
inexistente, o resultado é `SecOps`; `Dark` e outros temas válidos continuam
preservados. A v0.9.4 introduziu a invalidação `v11`; a v0.9.6 usa
`/static/themes/SecOps.css?v12` para atualizar também o fundo restaurado.

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

Candidatos GIF, WebP e APNG começam com a miniatura estática comum, carregada de
forma preguiçosa. Um indício de movimento em qualquer URL do resultado ou nos
metadados MIME/formato compatíveis do provedor seleciona o original full-size
para validação e reprodução. Toda URL `.gif`, `.webp` ou `.apng` é candidata,
inclusive WebP com nome comum; a validação de múltiplos frames devolve ao poster
qualquer WebP que seja estático. Um filtro de formato explícito também escolhe o
original full-size quando uma URL CDN assinada não possui extensão útil. URLs
`data:` são excluídas. Perto da área visível, o controlador busca a fonte pelo caminho local de
privacidade `/proxy?...&s=animated`, sem clique no lightbox e sem contato direto
do navegador com o provedor. O endpoint limita a resposta a 20 MB, aceita apenas
tipos MIME raster suportados e valida pelo menos dois frames antes de repassar
os bytes sem conversão. Também limita 1.000 frames, 16.384 pixels por eixo,
40 MP por frame, 250 milhões de pixel-frames decodificados e 8.192 chunks PNG.
GIF/WebP usa a contagem de frames do Imagick; PNG/APNG
usa análise estrita de chunks PNG e a contagem do `acTL`, pois o Imagick do
Alpine pode expor um APNG conhecido como um único frame. Em caso de falha
automática, o cartão volta ao poster e só tenta
novamente após ação deliberada por ponteiro/foco.

As validações entram em fila, com no máximo três originais carregando ao mesmo
tempo no desktop ou dois em dispositivos móveis/de ponteiro grosso. Esse limite
controla cargas, não reprodução: todos os candidatos visíveis já validados
continuam animados na grade sem clique. Cartões fora da tela voltam ao poster e
a fila avança quando surgem vagas. Novos resultados da rolagem contínua são
registrados; `prefers-reduced-motion` desativa animações e a economia de dados
desativa a ativação automática. A descoberta reconhece extensões GIF/WebP,
indícios explícitos de APNG/animated-PNG, parâmetros de formato em URLs
codificadas, origens limitadas do GitHub Camo e metadados MIME/formato do Google
ou Brave. Esses indícios permitem validar originais sem extensão; WebP estático
ainda volta ao poster. SVG, vídeo, gifv, URLs `data:` e formatos raster fora da
lista GIF/WebP/APNG não são aceitos.

A aplicação admite no máximo 120 solicitações de preview animado por endereço de
cliente a cada minuto e usa um semáforo global de três validações. Esses limites
cooperam com a fila do navegador sem limitar quantas animações visíveis e já
validadas continuam reproduzindo.

## Desempenho

A imagem de produção habilita PHP OPcache, compressão HTTP e cache de arquivos
estáticos. Miniaturas e favicons usam carregamento preguiçoso (`loading=lazy`) e
decodificação assíncrona. A página SecOps usa o fundo rastreado
`static/misc/secops.gif`; navegadores que pedem movimento reduzido ou economia
de dados recebem um fundo CSS estático. O Google reutiliza parâmetros CSE
validados por
até 90 segundos por backend, CX e saída, sem armazenar consultas ou resultados.
Cada transferência upstream do Google ou Brave usa timeout de 10 segundos para
conexão e 20 segundos no total, limitando a latência de cada requisição lenta.
Isso normalmente elimina as chamadas ao HTML e ao script de inicialização em
buscas próximas e reduz o volume upstream. Uma rejeição reconhecida do token em
cache apaga a entrada, faz uma inicialização nova e repete apenas uma vez;
tráfego incomum e CAPTCHA nunca são repetidos nem classificados como erro de
token. O NPT criptografado mantém a afinidade com o proxy. Pacotes de versão ficam fora do
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

| Produto | Modelo e origem dos resultados | Implantação e controle | Limite de dados publicado e interface relevante |
|---|---|---|---|
| **Security Search** | Fork auto-hospedável do 4get, com scrapers upstream selecionáveis, Google configurado como padrão e Brave por seleção explícita. Não mantém índice web independente. | O operador controla configuração, logs e proxy de saída. A busca principal é renderizada no servidor; uma falha não reenvia silenciosamente a consulta a outro provedor. | O upstream normalmente vê a saída da instância; o operador ainda processa consultas. Google ou Brave podem desafiar o IP de um VPS. Imagens têm carga automática padrão, opção de desativação e fallback **Next page**. [Arquitetura](#arquitetura-e-limite-de-confiança), [provedores](docs/PROVIDERS.md), [UI](docs/UI.md), [orientação do Google sobre tráfego incomum](https://support.google.com/websearch/answer/86640?hl=pt-BR). |
| **4get upstream** | Metabuscador por proxy com provedores de web, imagens, vídeos, notícias e outras categorias. | Projeto aberto operado por instâncias, com suporte a proxies rotativos por scraper. | O README oficial informa que a interface não exige JavaScript. Privacidade e logs dependem, no fim, do operador da instância escolhida. [Repositório e lista oficial de recursos](https://git.lolcat.ca/lolcat/4get). |
| **Google Search** | Sistemas do Google de rastreamento, índice e ranking de páginas, imagens e outros conteúdos. | Serviço hospedado e controlado pelo Google; personalização e controles de atividade variam conforme contexto, conta e configurações. | A política do Google diz que a atividade coletada pode incluir termos pesquisados e interações, além de informações da requisição/dispositivo como endereço IP. [Como a Busca funciona](https://developers.google.com/search/docs/fundamentals/how-search-works), [Política de Privacidade](https://policies.google.com/privacy?hl=pt-BR). |
| **Microsoft Bing** | Rastreador e índice da Microsoft para experiências de web, imagem, vídeo e outras categorias. | Serviço hospedado e controlado pela Microsoft, com controles do Bing e da Conta Microsoft. | A Microsoft diz que o Bing coleta termos pesquisados junto de dados como IP, localização, identificadores em cookies, horário e configuração do navegador. [Como o Bing entrega resultados](https://support.microsoft.com/en-us/bing/how-bing-delivers-search-results), [dados do histórico](https://support.microsoft.com/en-US/accounts-billing/how-microsoft-stores-and-maintains-your-search-history). |
| **DuckDuckGo** | Mantém o DuckDuckBot e vários índices; informa que links tradicionais e imagens vêm em grande parte do Bing. | Serviço hospedado pelo DuckDuckGo que intermedeia pedidos a parceiros; oferece versões HTML e Lite sem JavaScript, com menos recursos. | O DuckDuckGo afirma não salvar nem compartilhar histórico pessoal de busca e não enviar IP ou identificadores únicos do usuário aos parceiros. [Origem dos resultados](https://duckduckgo.com/duckduckgo-help-pages/results/sources), [privacidade da busca](https://duckduckgo.com/duckduckgo-help-pages/search-privacy), [versões sem JavaScript](https://duckduckgo.com/duckduckgo-help-pages/features/non-javascript). |
| **Brave Search** | Rastreador e índice independente operados pelo Brave; a mistura opcional com Google é uma escolha separada do usuário. | Serviço hospedado e controlado pelo Brave, com modos web e imagem. | O aviso do Brave descreve privacidade por padrão e documenta métricas agregadas opcionais, medição de anúncios, resultados locais anônimos e processamento temporário de IP para integridade do serviço. [Aviso de privacidade e detalhes do índice](https://search.brave.com/help/privacy-policy). |
| **Startpage** | Intermediário hospedado que envia consultas a provedores como Google e Bing; não mantém índice web próprio. | Serviço hospedado e controlado pelo Startpage; o Anonymous View opcional também intermedeia a navegação na página de destino. | O Startpage afirma não registrar visitas, pesquisas ou IPs comuns, com exceção antiabuso descrita na política; miniaturas de imagens passam por proxy. [Relação com parceiros](https://support.startpage.com/hc/en-us/articles/4522435533844-What-is-the-relationship-between-Startpage-and-your-search-partners-like-Google-and-Microsoft-Bing), [Política de Privacidade](https://safe.startpage.com/en/privacy-policy/), [busca de imagens](https://support.startpage.com/hc/en-us/articles/4521419354132-How-to-search-for-images-on-Startpage). |

## Criar uma versão

Com todas as mudanças rastreadas já commitadas e a árvore de trabalho limpa:

```bash
git diff --check
./release.sh 0.9.6
(cd dist && sha256sum -c securitysearch-v0.9.6.tar.gz.sha256)
git tag -a v0.9.6 -m "Security Search v0.9.6"
```

O script usa `git archive`, respeita `.gitattributes` e acrescenta somente o
diretório vazio obrigatório `icons/`, que o Git não consegue rastrear:

```text
dist/securitysearch-v0.9.6.tar.gz
dist/securitysearch-v0.9.6.tar.gz.sha256
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

A publicação v0.9.4 reescreveu o histórico sanitizado. A v0.9.6 deve preservar
esse histórico e publicar o inventário exato `main` mais as tags `v0.9.0` a
`v0.9.6`. Siga exatamente o procedimento com lease por ref, push atômico,
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
  dist/securitysearch-v0.9.6.tar.gz \
  remote:/srv/evelin/securitysearch-v0.9.6.tar.gz
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.6.tar.gz.sha256 \
  remote:/srv/evelin/securitysearch-v0.9.6.tar.gz.sha256
ev --config /home/berkeley/.evelin/client.toml shell
```

No VPS, valide o pacote, crie uma árvore irmã limpa e arquive a árvore ativa
exata antes de alterá-la:

```bash
cd /srv/evelin
sha256sum -c securitysearch-v0.9.6.tar.gz.sha256

rollback_stamp=$(date -u +%Y%m%dT%H%M%SZ)
rollback_archive=/root/security-search-pre-v0.9.6-${rollback_stamp}.tgz
old_tree=/root/security-search-update-old-${rollback_stamp}
release_tree=/root/security-search-v0.9.6
test -d /root/security-search-update
test ! -e "$old_tree"
test ! -e "$release_tree"

umask 077
tar -czf "$rollback_archive" -C /root security-search-update
test -s "$rollback_archive"
chmod 600 "$rollback_archive"

install -d -m 0750 "$release_tree"
tar -xzf securitysearch-v0.9.6.tar.gz \
  --strip-components=1 \
  -C "$release_tree"
test -f "$release_tree/docker-compose.yml"
test -f "$release_tree/Dockerfile"

previous_image_id=$(docker image inspect --format '{{.Id}}' security-search:latest)
test -n "$previous_image_id"
docker image tag "$previous_image_id" security-search:pre-v0.9.6

cd /root/security-search-v0.9.6
umask 077
printf 'SECURITYSEARCH_BIND_ADDRESS=172.17.0.1\n' > .env
chmod 600 .env
docker compose build --no-cache --pull

cd /root
docker compose -f /root/security-search-update/docker-compose.yml \
  down --remove-orphans
mv /root/security-search-update "$old_tree"
mv /root/security-search-v0.9.6 /root/security-search-update
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

Depois, confirme `/static/themes/SecOps.css?v12`, o tipo CSS, cartões reais de
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
mv /root/security-search-update /root/security-search-update-failed-v0.9.6
mv /root/security-search-update-old-YYYYMMDDTHHMMSSZ \
  /root/security-search-update
docker image tag security-search:pre-v0.9.6 security-search:latest
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
