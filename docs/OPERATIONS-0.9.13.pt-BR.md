# Operação da v0.9.13

[English](OPERATIONS-0.9.13.md) · [Base anterior](OPERATIONS-0.9.12.pt-BR.md)

## Navegação e privacidade

Início e resultados oferecem Images (`images.securityops.co`), Videos
(`invidious.securityops.co`), Pixiv, Chat, News (`news.securityops.co`) e Wiki.
As abas de busca interna continuam separadas. Na busca de vídeos, o link com a
consulta codificada para o Invidious também aparece se o provedor falhar.
Não há player incorporado, troca automática de provedor, pré-carregamento ou script
externo. A consulta só é enviada ao clicar; a reprodução depende do YouTube.
Navegação e sugestão funcionam sem JavaScript.

## Desempenho com limites

APCu guarda somente templates locais compactados por 300 segundos, antes de
substituir consultas, temas ou cookies. A chave inclui hash do caminho, versão
dos assets, data e tamanho do arquivo. Entradas acima de 128 KiB não são guardadas;
a implantação reinicia o cache do processo. Sem APCu, a leitura normal continua.
Isso reduz trabalho local, não a latência externa do provedor/Tor. O HTML de busca
envia `private, no-store`. Não foi criado cache persistente de consultas/resultados
nem rastreamento. Reuso de conexões, prazos máximos, seis layouts e prévias leves
da versão anterior foram preservados.

URLs de imagem com credenciais são rejeitadas antes da resolução DNS. Erros não
revelam caminhos internos do ImageMagick ou detalhes do transporte. Falhas tratadas
não são armazenáveis. Foi removido o 304 incondicional baseado em qualquer data do
cliente, que podia preservar uma miniatura quebrada ou pular a validação. A saída
JPEG usa a constante de compressão JPEG. Limites de SSRF, tamanho, formato e tempo
continuam obrigatórios.

O sitemap usa a origem canônica HTTPS fixa da página inicial, não o Host enviado
pelo cliente ou o HTTP interno do proxy, e exclui Configurações. Rotas de consulta
e proxy continuam fora da indexação. Overrides de `/robots.txt` e `/sitemap.xml`
no NPM também precisam de auditoria: editar o repositório não substitui o override.

## Verificação e publicação

Execute as quatro suítes da documentação anterior, lint PHP e os testes visuais
sem JavaScript. As regressões cobrem seis links, codificação da consulta, ausência
de embeds, isolamento do cache, temas, traversal e URLs de imagem inseguras.

A auditoria local passou em 31 cenários sem JavaScript (layouts em
320/390/768/1440px, início desktop/celular e movimento reduzido). Em 1.000 chamadas
sintéticas, preparar o template inicial levou 358,332 ms sem cache e 6,988 ms com
cache aquecido, com bytes idênticos. São tempos agregados de preparação local,
não latência de busca nem promessa de aceleração ponta a ponta. Repita com
`php -d apc.enable_cli=1 tests/template-benchmark.php` no ambiente de destino.

Valide uma instância candidata antes da troca. Preserve redes, portas privadas,
credenciais e volumes. Contêineres de rollback precisam ficar com reinício
automático desabilitado; a política original deve estar no backup privado para
reversão deliberada. Após reiniciar o Docker, confira também os IPs: endpoints
dinâmicos podem ocupar endereços estáticos reservados. Não apague redes, abra
portas privadas ou enfraqueça o firewall para resolver esse conflito.

Meça status HTTP, quantidade real de resultados e latência juntos. Um erro de
cooldown em 4–6 ms não é uma busca concluída. Não há promessa de tráfego orgânico,
latência garantida ou disponibilidade universal dos provedores.
