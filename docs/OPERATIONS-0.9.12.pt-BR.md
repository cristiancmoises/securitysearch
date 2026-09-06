# Operação da v0.9.12

[English](OPERATIONS-0.9.12.md) · [Interface](UI.md) · [Provedores](PROVIDERS.md)

## Velocidade e disponibilidade

Um **erro de 4–6 milissegundos** pode ser uma rejeição local pelo cooldown do
APCu, sem consulta à rede; não é uma busca concluída. Já **4–6 segundos** podem
incluir Tor, TLS e processamento do provedor. Meça HTTP, quantidade de resultados,
tempo até o primeiro byte e duração total juntos. Não compare erros e sucessos
como se representassem a mesma operação.

Google continua sendo o padrão. As etapas de inicialização e busca reutilizam
uma conexão cURL dentro da mesma requisição/saída, quando o servidor permite,
reduzindo negociações repetidas. As opções são reiniciadas a cada etapa; conexões
não são compartilhadas entre usuários ou proxies diferentes. O comportamento
segue a [documentação do libcurl](https://curl.se/libcurl/c/curl_easy_reset.html).
Só é aceito `https://cse.google.com`, porta 443, sem seguir redirecionamentos e
mantendo a validação TLS. Respostas descomprimidas têm limite de 4 MiB; mensagens
de falha não expõem endereços internos dos proxies.

Os aliases Google e Google CSE compartilham o cache de inicialização de cinco
minutos e o cooldown antiabuso de 30 segundos quando CX e saída coincidem.
Não há consultas nem resultados nesse cache. Uma saída bloqueada não inicia
outro bootstrap. Requisições simultâneas podem aguardar o primeiro bootstrap por
até 24 segundos, sempre dentro do prazo monotônico total de 25 segundos,
sem repetir suas chamadas upstream.

Não há resolução de desafios, troca silenciosa de provedor, saída direta como
fallback do Tor, cache persistente de consultas ou garantia de latência.
HTTP 502/503/504 transitório mantém uma repetição por etapa, na mesma saída e
dentro do prazo. Google e Brave ainda podem bloquear saídas compartilhadas;
nesse caso, selecione outro provedor explicitamente. Um token de paginação
consumido/expirado oferece reiniciar a busca.

As cinco APIs de busca retornam 503 nas exceções de provedor tratadas. Selecionar
Google API explicitamente ou por preferência salva, sem chave configurada, falha
antes de enviar consultas; não muda silenciosamente para Google CSE. A lista
inicial esconde essa opção indisponível.

## Proxies privados

Pools ficam fora do Git, montados somente para leitura. O formato existente é
`tipo:host:porta:usuário:senha`. Linhas vazias/comentadas são ignoradas, espaços
externos são normalizados, e tipos não suportados, hosts/portas inválidos ou
entradas incompletas falham antes da rede. Um erro de digitação nunca autoriza
tráfego direto. Execute a regressão de proxies ao alterar o parser. Não imprima
pools completos, arquivos de ambiente ou Docker Env.

## Lain e visualizações

A versão de assets 15 invalida CSS/controladores antigos. O arquivo original
`static/misc/lain.gifv` é GIF, não vídeo: deve ser servido como `image/gif`, com
cache de arquivo estático. Ele tem aproximadamente 8,7 MB; a primeira visita
animada pode exigir esse download. O fundo fica visível também no celular,
com opção estática sem JavaScript e respeito às preferências de movimento/dados
reduzidos. Temas válidos já salvos continuam tendo precedência.

As seis visualizações CSS são grade, compacta, galeria, feed, lista e faixa
horizontal. Busca, filtros, layouts e paginação comum funcionam sem JavaScript;
controladores opcionais oferecem lightbox, prévias animadas e paginação
automática limitada. Prévia é o padrão mais leve; original é uma opção explícita.
Imagens continuam usando o proxy validado. Não remova limites de SSRF, MIME,
tamanho ou decodificação para disfarçar uma imagem indisponível.
A faixa horizontal usa somente Next page: paginação vertical automática poderia
baixar cartões laterais que o usuário ainda não alcançou.

## Monitor de status separado

`/status/` é gerado pelo observer do CT119 e encaminhado pelo NPM da IONOS por
relays privados restritos. Não representa a saúde de cada provedor de busca.
Confira domínio, resposta, horário da medição e idade do snapshot. Um
redirecionamento para login pode indicar acesso normal; um 502 no login público
é falha real do gateway, mesmo com a aplicação funcionando na LAN. Corrija a
rota comprovadamente defeituosa em vez de transformar um 502 em selo online.
Falhas de transporte precisam de repetição limitada e estados/histórico honestos.

## Testes e publicação

Execute na raiz do repositório:

```sh
php -d apc.enable_cli=1 tests/regression.php
php -d apc.enable_cli=1 tests/google-transport.php
php -d apc.enable_cli=1 -d disable_functions=curl_setopt tests/proxy-regression.php
php tests/provider-api-regression.php
```

Valide sintaxe PHP/JS, Lain em movimento e reduzido no desktop/celular, quadros
da animação, seis layouts sem JS e rejeições do proxy de imagens. Construa um
candidato isolado com as mesmas redes/montagens privadas da produção. Teste
resultados reais e falhas, não apenas o health check.

Mantenha backup privado da configuração e o container/imagem anterior. Antes da
troca, confirme que ninguém alterou o container ativo. Preserve o volume de
ícones. Depois, teste HTTPS, MIME/cache/CSP, status e buscas novamente. Não faça
force-push nem publique segredos. Consulte [a publicação](RELEASE.md).
