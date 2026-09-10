# Security Search v0.9.25

[English](README.md) · [Português do Brasil](README.pt-BR.md)

Metabuscador proxy PHP baseado no [4get](https://git.lolcat.ca/lolcat/4get), mantido
para a [SecurityOps](https://securityops.co). Pesquisas normais e temas locais
**funcionam sem JavaScript**; imagens pessoais e aprimoramentos de imagens usam
scripts locais opcionais. Aplicação **0.9.25**, recursos **29**.

## Carregamento inicial

A página inicial Black incorpora três folhas pequenas de CSS **específicas da
página inicial**, removendo as três requisições bloqueantes de style.css,
experience.css e Black.css. Outros temas continuam carregando sua folha externa;
as páginas de pesquisa/configurações mantêm o CSS completo. Caso o CSS gerado
esteja ausente, o código utiliza as folhas externas originais, sem deixar a página
sem estilo.

O mesmo logotipo WebP de 400 × 86 passa de **11.528 para 5.710 bytes**, preservando
dimensões explícitas e prioridade alta. O preload fica no início do documento.
Não há preconnect externo, carregador JavaScript de CSS nem fontes novas.

Na comparação local de gzip, HTML + CSS bloqueante passou de 15.796 para 9.866
bytes. Só o HTML passou de 7.771 para 9.866 bytes: um documento ligeiramente maior
elimina três requisições críticas e CSS desnecessário na página inicial. Isso
**não é uma medição do LCP da VPS**; os milissegundos estimados pelo Lighthouse
não são anunciados como ganhos comprovados. Faça uma nova medição após o deploy.

`scripts/build-home-css.py` regenera as folhas com PHP/tinycss2/cssselect2/lxml;
são dependências de desenvolvimento, não do serviço. Os hashes das entradas e
saídas são verificados nos testes; mudanças estruturais precisam de revisão visual.

## Google, Brave e imagens

Google continua padrão. Uma falha na primeira página pode tentar Brave uma vez,
com identificação visível e o mesmo prazo compartilhado. Uma resposta vazia válida,
provedor não Google explicitamente escolhido ou continuação não troca de provedor.
Nenhum terceiro provedor é consultado automaticamente.

Os erros de transporte agora preservam categorias seguras. Retry-After é limitado
a uma hora. Metadados APCu sem consultas/resultados e separados por saída de rede
aplicam pausas breves: 5 segundos para transporte; ao menos 60 para rate limit;
ao menos 120 para recusa/desafio. Erros de parsing não bloqueiam globalmente o provedor.
Brave não gira proxies para repetir um desafio. Um 502/503/504 sem Retry-After
nem desafio reconhecido permite no máximo uma nova tentativa na mesma saída,
dentro do prazo original. Google mantém sua tentativa transitória limitada.
TLS, limites de 4 MiB e proibição de redirecionamentos são preservados.

A espera por outro worker preparando a sessão Google fica limitada a dois segundos.
São aceitas variações estreitas de wrappers JSONP/Svelte, sem executar JavaScript
recebido. Foram testadas em fixtures; **isso não comprova disponibilidade externa**.
CAPTCHA, limites, recusas e falhas de rede ainda podem gerar um erro 503 honesto.

As prévias escolhem variantes menores realmente fornecidas, quando as dimensões
são conhecidas; não inventam URLs. Modos original/alta qualidade são preservados.
As primeiras quatro imagens são carregadas prontamente, apenas a primeira com
prioridade alta. As demais permanecem lazy. Animações limitadas, paginação,
Binternet e os seis layouts continuam disponíveis.

O recurso My picture continua processando a imagem apenas no navegador, com o
seletor sem nome e fora de formulários. Nada é enviado ao servidor. Persistência
local continua opcional; remover limpa os armazenamentos quando permitido. O pacote
privado histórico Lain/SecOps fica fora do Git e dos anexos públicos de todas as
forges, inclusive Codeberg. Só o deploy explícito com --theme-assets o inclui.

## Instâncias externas

As instâncias Redlib são operadas por terceiros independentes, **não pela Security Ops**.
A Security Ops mantém a integração, não essas instâncias. As políticas e a
disponibilidade são controladas pelos operadores externos; consultas e fallbacks
podem chegar a eles. As declarações de privacidade do SecuritySearch não são
promessas sobre serviços de terceiros. News RSS continua sendo o padrão separado,
com as verificações reais RSS/Binternet e o cache somente de manchetes públicas.
O rodapé onion/Tranco permanece; nenhum ranking é inventado.

## Aplicar e implantar

Baixe o kit e checksum em ~/Downloads e extraia securitysearch-update-0.9.25:

```fish
fish ~/Downloads/securitysearch-update-0.9.25/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.25/deploy-securitysearch.fish \
    ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Aceita o estado exato limpo da correção de atribuição ou v0.9.24-r1. Não apaga
alterações nem move tags antigas. Usa root@securityops.co, SSH 5119, preservando
172.17.0.1:5140 → 80, redes, configurações privadas compatíveis e Nginx Proxy Manager.
Todos os testes nativos, prontidão, RSS e Binternet precisam passar antes da troca.
Mantenha backups, rollback e o pacote privado de temas fora do repositório.

```fish
fish ~/Downloads/securitysearch-update-0.9.25/publish-securitysearch.fish ~/securitysearch
# Retomar somente .com.br, incluindo anexos da release:
fish ~/Downloads/securitysearch-update-0.9.25/publish-securitysearch.fish \
    ~/securitysearch --host git.securityops.com.br
```

A tag anotada nova é v0.9.25, com securitysearch-v0.9.25.tar.gz e seu .sha256.
O publicador verifica a origem do pacote e os anexos, pede tokens privados e
preserva conflitos. Não é uma transação atômica entre quatro servidores. v0.9.24
permanece imutável. O checksum não equivale a assinatura digital. Publicação e
deploy são operações distintas.

Para o diagnóstico real opcional após o deploy, execute diagnose-search.fish no
kit. Ele faz quatro consultas neutras fixas (Google/Brave, web/imagens), sem gravar
conteúdo, IPs ou credenciais. Não redefine limites nem reinicia o serviço.

## Validação

`sh scripts/test.sh --keep-going` exige todos os **61** comandos; conserva os 57
anteriores e acrescenta quatro suítes. A execução completa precisa de PHP com
curl/DOM/XML/mbstring/APCu/Imagick, Python, Node, Git e fish. O relatório diferencia
fixtures de testes nativos e não transforma dependências ausentes em aprovação.
[Notas bilíngues](docs/RELEASE-0.9.25.md) · [Auditoria](docs/AUDIT-0.9.25.md).
Licença [AGPL-3.0](license.txt). **In Code We Trust.**
