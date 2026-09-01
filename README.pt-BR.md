# Security Search

[English](README.md) | Português do Brasil

O Security Search é um mecanismo de metabusca por proxy, com código aberto e
implantação própria, derivado e reforçado a partir do
[4get](https://git.lolcat.ca/lolcat/4get). A instância de produção é publicada
em [securityops.co](https://securityops.co/).

## Versão atual: v0.9.3

- A página inicial prioriza o logotipo e a busca, com apenas Configurações, uma
  indicação curta de privacidade/provedor e dois links discretos:
  [SecurityOps](https://securityops.co/) e
  [SecurityOps Brasil](https://securityops.com.br/).
- SecOps é o tema padrão para novos visitantes. Uma preferência de tema válida
  já salva no navegador continua tendo precedência.
- Google é o provedor padrão para buscas web e de imagens.
- Brave está disponível no seletor **Scraper** para web e imagens.
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
sha256sum -c securitysearch-v0.9.3.tar.gz.sha256
tar -xzf securitysearch-v0.9.3.tar.gz
cd securitysearch-v0.9.3
docker compose up -d --build
docker compose ps
curl -fsSI http://127.0.0.1:5140/
```

Para uma compilação limpa, use `./deploy.sh --fresh`. O contêiner publica a
porta `80` em `127.0.0.1:5140`/porta `5140` do host conforme o Compose da
implantação; mantenha o proxy reverso apontando para o mesmo destino já usado em
produção.

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
  - FOURGET_DEFAULT_SCRAPER_WEB=google
  - FOURGET_DEFAULT_SCRAPER_IMAGES=google
```

Uma preferência válida salva no navegador tem precedência sobre o respectivo
padrão. Um parâmetro de provedor na URL tem precedência sobre cookie e padrão.
Credenciais, chaves de API e dados privados de proxy não devem ser gravados no
repositório nem incluídos no pacote de versão.

Configurações importantes:

- `FOURGET_DEFAULT_THEME=SecOps`: tema usado quando não há cookie de tema válido;
- `FOURGET_DEFAULT_SCRAPER_WEB=google`: provedor web padrão;
- `FOURGET_DEFAULT_SCRAPER_IMAGES=google`: provedor de imagens padrão;
- `FOURGET_PROXY_BRAVE`: nome de um pool de proxy configurado para o Brave,
  quando necessário;
- `FOURGET_GOOGLE_CX_ENDPOINT`: endpoint da pesquisa programável do Google, se
  uma implantação precisar substituir o valor padrão.

Leia [docs/PROVIDERS.md](docs/PROVIDERS.md) para precedência e limitações dos
provedores e [docs/configure.md](docs/configure.md) para a configuração geral.

## Provedores de busca

O provedor visível `google` usa o transporte compatível com Google Programmable
Search incluído no projeto. Ele é o padrão de web e imagens porque não exige um
renderizador Firefox/4play externo. O Google API opcional usa arquivo de chave
separado e excluído dos pacotes; nunca publique essa chave.

Brave é a alternativa principal disponível no seletor para web e imagens. Um
IP de datacenter pode receber CAPTCHA, proof-of-work ou bloqueio de faixa do
Brave. O aplicativo apresenta essa condição como erro do provedor; um pool
adequado em `FOURGET_PROXY_BRAVE` pode ser necessário para disponibilidade
consistente. Não se deve afirmar que um provedor está operacional antes de um
teste real a partir do host de produção.

Clientes da API podem escolher explicitamente o scraper:

```text
/api/v1/web?s=seguranca&scraper=google
/api/v1/images?s=privacidade&scraper=brave
```

Sem `scraper`, a API usa os mesmos padrões Google da interface.

## Interface e responsividade

A hierarquia da página inicial é intencionalmente curta:

1. uma ação utilitária de Configurações;
2. o logotipo Security Search;
3. o campo e a ação principal de busca;
4. uma indicação compacta: Google padrão, Brave disponível;
5. links de texto discretos para `securityops.co` e `securityops.com.br`.

Não há grade promocional de botões ou cartões competindo com a busca. O fluxo
principal funciona sem JavaScript, possui foco visível por teclado, alvos
adequados para toque e layout preparado de 320 px até telas desktop. A página
evita foco automático que abriria o teclado móvel sem solicitação. O contrato
completo da interface está em [docs/UI.md](docs/UI.md).

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

## Desempenho

A imagem de produção habilita PHP OPcache, compressão HTTP e cache de arquivos
estáticos. Miniaturas e favicons usam carregamento preguiçoso (`loading=lazy`) e
decodificação assíncrona. O fundo SecOps padrão é gerado por CSS e não baixa mais
a animação anterior de 18,9 MB. Tokens de inicialização do Google CSE — nunca
consultas ou resultados — ficam em cache por cinco minutos, separados por
endpoint e proxy de saída, com uma única renovação quando expiram. Pacotes de
versão também ficam fora do contexto de compilação Docker. O acabamento das
páginas de resultado usa CSS versionado e reutilizável, e preloads de fontes
ausentes foram removidos. A rolagem contínua busca uma página por vez quando
necessário; ela não pré-carrega indefinidamente toda a coleção no primeiro
acesso.

Essas são otimizações de entrega, não promessas de benchmark. Latência e vazão
dependem do VPS, do provedor selecionado, de limites e desafios do upstream, do
pool de proxy e da rede. Valide o desempenho no mesmo host e caminho de rede da
produção e inspecione os logs por avisos ou erros do PHP.

## Comparação factual

Os produtos abaixo têm limites de confiança diferentes. “Proxy” significa que
um intermediário processa a consulta; não significa que o intermediário seja
automaticamente confiável ou que o usuário esteja anônimo. As linhas de
serviços hospedados resumem as políticas publicadas pelos próprios provedores,
e não uma auditoria independente. Fontes consultadas em 2026-09-01.

| Produto | Modelo e origem dos resultados | Implantação e controle | Limite de dados publicado e interface relevante |
|---|---|---|---|
| **Security Search** | Fork auto-hospedável do 4get, com scrapers upstream selecionáveis, Google padrão e Brave disponível. Não mantém índice web independente. | O operador da instância controla configuração, logs e proxy de saída. A busca principal é renderizada pelo servidor. | O upstream normalmente vê a saída da instância em vez de uma requisição direta do navegador; o operador ainda processa consultas. Imagens têm carga automática padrão, opção de desativação e fallback **Next page**. [Arquitetura](#arquitetura-e-limite-de-confiança), [provedores](docs/PROVIDERS.md), [UI](docs/UI.md). |
| **4get upstream** | Metabuscador por proxy com provedores de web, imagens, vídeos, notícias e outras categorias. | Projeto aberto operado por instâncias, com suporte a proxies rotativos por scraper. | O README oficial informa que a interface não exige JavaScript. Privacidade e logs dependem, no fim, do operador da instância escolhida. [Repositório e lista oficial de recursos](https://git.lolcat.ca/lolcat/4get). |
| **Google Search** | Sistemas do Google de rastreamento, índice e ranking de páginas, imagens e outros conteúdos. | Serviço hospedado e controlado pelo Google; personalização e controles de atividade variam conforme contexto, conta e configurações. | A política do Google diz que a atividade coletada pode incluir termos pesquisados e interações, além de informações da requisição/dispositivo como endereço IP. [Como a Busca funciona](https://developers.google.com/search/docs/fundamentals/how-search-works), [Política de Privacidade](https://policies.google.com/privacy?hl=pt-BR). |
| **Microsoft Bing** | Rastreador e índice da Microsoft para experiências de web, imagem, vídeo e outras categorias. | Serviço hospedado e controlado pela Microsoft, com controles do Bing e da Conta Microsoft. | A Microsoft diz que o Bing coleta termos pesquisados junto de dados como IP, localização, identificadores em cookies, horário e configuração do navegador. [Como o Bing entrega resultados](https://support.microsoft.com/en-us/bing/how-bing-delivers-search-results), [dados do histórico](https://support.microsoft.com/en-US/accounts-billing/how-microsoft-stores-and-maintains-your-search-history). |
| **DuckDuckGo** | Mantém o DuckDuckBot e vários índices; informa que links tradicionais e imagens vêm em grande parte do Bing. | Serviço hospedado pelo DuckDuckGo que intermedeia pedidos a parceiros; oferece versões HTML e Lite sem JavaScript, com menos recursos. | O DuckDuckGo afirma não salvar nem compartilhar histórico pessoal de busca e não enviar IP ou identificadores únicos do usuário aos parceiros. [Origem dos resultados](https://duckduckgo.com/duckduckgo-help-pages/results/sources), [privacidade da busca](https://duckduckgo.com/duckduckgo-help-pages/search-privacy), [versões sem JavaScript](https://duckduckgo.com/duckduckgo-help-pages/features/non-javascript). |
| **Brave Search** | Rastreador e índice independente operados pelo Brave; a mistura opcional com Google é uma escolha separada do usuário. | Serviço hospedado e controlado pelo Brave, com modos web e imagem. | O aviso do Brave descreve privacidade por padrão e documenta métricas agregadas opcionais, medição de anúncios, resultados locais anônimos e processamento temporário de IP para integridade do serviço. [Aviso de privacidade e detalhes do índice](https://search.brave.com/help/privacy-policy). |
| **Startpage** | Intermediário hospedado que envia consultas a provedores como Google e Bing; não mantém índice web próprio. | Serviço hospedado e controlado pelo Startpage; o Anonymous View opcional também intermedeia a navegação na página de destino. | O Startpage afirma não registrar visitas, pesquisas ou IPs comuns, com exceção antiabuso descrita na política; miniaturas de imagens passam por proxy. [Relação com parceiros](https://support.startpage.com/hc/en-us/articles/4522435533844-What-is-the-relationship-between-Startpage-and-your-search-partners-like-Google-and-Microsoft-Bing), [Política de Privacidade](https://safe.startpage.com/en/privacy-policy/), [busca de imagens](https://support.startpage.com/hc/en-us/articles/4521419354132-How-to-search-for-images-on-Startpage). |

## Criar uma versão

Com todas as mudanças rastreadas já commitadas e a árvore de trabalho limpa:

```bash
./release.sh 0.9.3
sha256sum -c dist/securitysearch-v0.9.3.tar.gz.sha256
git tag -a v0.9.3 -m "Security Search v0.9.3"
```

O script usa `git archive`, inclui apenas conteúdo commitado permitido por
`.gitattributes` e gera:

```text
dist/securitysearch-v0.9.3.tar.gz
dist/securitysearch-v0.9.3.tar.gz.sha256
```

Antes de publicar, faça lint de todos os arquivos PHP na imagem, compile sem
cache, inicie o contêiner, teste saúde, cabeçalhos, interface, Configurações,
Google/Brave em web e imagens, API, rolagem contínua e fallback sem JavaScript.

## Implantação no IONOS com Evelin

Envie o pacote e o checksum usando o perfil aprovado, sem copiar credenciais
para scripts:

```bash
ev --config ~/.evelin/client.toml cp \
  dist/securitysearch-v0.9.3.tar.gz \
  remote:/tmp/securitysearch-v0.9.3.tar.gz
ev --config ~/.evelin/client.toml cp \
  dist/securitysearch-v0.9.3.tar.gz.sha256 \
  remote:/tmp/securitysearch-v0.9.3.tar.gz.sha256
ev --config ~/.evelin/client.toml shell
```

No VPS, valide antes de extrair e preserve a versão anterior até concluir os
testes:

```bash
cd /tmp
sha256sum -c securitysearch-v0.9.3.tar.gz.sha256
mkdir -p /opt/securitysearch/releases
tar -xzf securitysearch-v0.9.3.tar.gz -C /opt/securitysearch/releases
cd /opt/securitysearch/releases/securitysearch-v0.9.3
./deploy.sh --fresh
docker compose ps
curl -fsSI http://127.0.0.1:5140/
```

O deploy cria backup com data/hora, interrompe o contêiner antigo, compila a
substituição, aguarda o healthcheck e executa um teste HTTP local. Só remova uma
versão antiga depois de confirmar a nova por acesso local e público e de guardar
um caminho de rollback recuperável. Consulte [docs/RELEASE.md](docs/RELEASE.md)
e [docs/BUILD-LOCATION.md](docs/BUILD-LOCATION.md).

## Documentação relacionada

- [Interface e responsividade](docs/UI.md)
- [Provedores e paginação de imagens](docs/PROVIDERS.md)
- [Versão, verificação, rollback e implantação](docs/RELEASE.md)
- [Onde compilar](docs/BUILD-LOCATION.md)
- [Migração do fork](MIGRATION.md)
- [Prompt de melhoria de qualidade de versão](docs/GOD_TIER_SEARCH_ENGINE_PROMPT.md)

O projeto preserva a licença AGPLv3-only do 4get. Verifique o arquivo
[license.txt](license.txt) antes de redistribuir ou oferecer uma versão modificada pela
rede.
