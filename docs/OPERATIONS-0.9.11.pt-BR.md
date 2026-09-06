# Operação da v0.9.11

Documento histórico da v0.9.11. Consulte a operação atual da
[v0.9.12](OPERATIONS-0.9.12.pt-BR.md). [English](OPERATIONS-0.9.11.md).

## Confiabilidade e privacidade

Google é o padrão, não uma garantia de disponibilidade. Só os parâmetros de
inicialização, sem consulta, ficam em cache por cinco minutos. Consultas e
resultados não entram nesse cache. Após bloqueio antiabuso, um cooldown curto
evita repetir tráfego: um erro em 4–6 ms não significa busca concluída. Falhas
agora usam HTTP 503, Retry-After: 30 e no-store, inclusive na API. O HTML aguarda
o provedor antes de enviar os cabeçalhos.

As chamadas Google compartilham 25 segundos, com até cinco segundos para conexão.
HTTP 502/503/504 transitório permite uma repetição por etapa, no mesmo provedor e
saída, dentro do prazo total. Verificação TLS continua obrigatória. Não contornamos
CAPTCHA/403/429 nem enviamos a consulta silenciosamente a outro provedor. Brave
também pode bloquear o IP da instância.

## Imagens e tema

Lain é o padrão no código e em FOURGET_DEFAULT_THEME. Preferências válidas salvas
continuam prevalecendo; use Configurações para redefinir. Assets usam versão 14.
O GIF decorativo de 8,7 MB não é carregado em telas até 600 px ou com preferência
por menos movimento/dados.

O filtro View oferece grade, grade compacta, galeria sem recorte e feed amplo,
renderizados no servidor com CSS e ordem de teclado previsível. Fast preview é
o padrão; Original consome mais recursos. O proxy de imagens mantém validação e
lazy loading. Paginação automática para após três páginas adicionais, respeita
Save-Data e expira em 25 segundos. Next page funciona sem JS; falha em token de
uso único oferece reiniciar a busca.

## Status e implantação

/status/en.html é um snapshot independente, não uma rota PHP da busca. O NPM na
IONOS encaminha /status/ por endpoint privado restrito. Teste a partir do NPM,
guarde backup e valide nginx -t antes de alterar a rota. Não substitua túneis
funcionais. Não force Content-Type JSON global nem CORS curinga para consultas.
O painel público é somente leitura; administração permanece pelo Evelin/CT119.

Execute `php -d apc.enable_cli=1 tests/regression.php`, lint PHP e `node --check`
no controlador alterado. Teste entradas inválidas, JSONP, cooldown e prazo; depois
home, configurações, quatro layouts e erros em 360/768/1440 px, com e sem JS.
Teste buscas reais da rede da produção. Guarde imagem/configuração anterior e
volume de ícones; valide primeiro um candidato privado, depois HTTPS público,
MIME, cabeçalhos, assets e buscas novamente. Nunca publique chaves, pools privados
ou caches, nem use force-push para contornar divergência.

Esta versão não promete eliminar bloqueios externos nem aumentar visitas sem
métricas. Não gera visitas artificiais. Resultados continuam noindex para não
indexar consultas privadas.
