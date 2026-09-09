# Security Search v0.9.19 — operação e IONOS

A rolagem infinita volta a anexar imagens, inclusive lateralmente em Filmstrip. O painel Automatic pages, temporizadores e snapshots foram removidos. Links antigos com frame retornam 410 sem consultar o provedor. Tron, ícones compactos, integrações, filtros, seis layouts e navegação continuam.

O recurso usa dois scripts locais, para paginação e animação visível, como exceção necessária ao pedido anterior sem JavaScript. As demais páginas bloqueiam scripts. Settings → Load more images while scrolling → No desativa a rolagem. A preferência Play visible GIF, WebP and APNG previews controla a animação separadamente. Next page funciona sem JavaScript e com Save-Data.

Há somente uma busca em andamento, até 24 imagens por resposta, limite de 1 MiB e prazo de 25 segundos. Imagens novas usam carregamento lazy e decodificação async. Antes de exceder 480 cartões, continue com Next page em uma página nova. Páginas vazias preservam a continuação manual; falhas oferecem reinício sem repetir o token consumido. O bloqueio de um provedor pode continuar.

Google continua padrão. Uma falha na primeira busca web/imagens tenta Brave uma vez: até 12 segundos para Google e 8 para Brave, com prazo compartilhado de 20 segundos. O aviso identifica Brave e preserva filtros compatíveis. Paginação e API não trocam provedor. Brave imagens não tem próxima página; seu filtro de formato é local. Se ambos falharem, a resposta permanece 503.

GIF/WebP animado/APNG visíveis usam no máximo dois carregamentos e quatro animações ativas. Imagens fora da tela e abas ocultas voltam ao poster estático. Redução de movimento, Save-Data e opção em Settings são respeitados. A carga automática é limitada a 8 MiB, 500 quadros, 4096 px por lado, 8 MP e 64 milhões de pixels somados entre quadros. View animation mantém limites próprios maiores. WebP estático não anima.

Os ícones menores ficam dentro da extremidade direita da barra. Wiki/Git no rodapé perdem a caixa. Reddit entra na navegação; `/news` usa Redlib para r/news + r/worldnews, com posts recentes sem consulta e resultados relacionados com palavras-chave. O link superior News permanece externo. O Redlib retornou 503 na verificação de 2026-09-09; a integração passou nos testes locais, mas notícias ao vivo dependem da recuperação do serviço.

## Deploy

Coloque estes três arquivos em `~/Downloads`: `securitysearch-v0.9.19.tar.gz`, `securitysearch-v0.9.19.tar.gz.sha256` e `deploy-securitysearch-v0.9.19.fish`.

```fish
fish ~/Downloads/deploy-securitysearch-v0.9.19.fish
```

O script verifica o checksum, copia para root@securityops.co via SSH 5119, confere novamente, extrai em diretório novo e executa o atualizador. O container existente deve se chamar `security-search` e usar exatamente **172.17.0.1:5140 → 80**. NPM continua com `http://172.17.0.1:5140`.

O VPS precisa de root, Python 3, Docker, curl, flock e recursos para build e candidato. O serviço atual fica ligado durante o build; há breve interrupção na troca. O candidato valida saúde, versão 23, Tron, CSP, scripts de paginação/animação e helpers PHP locais. O atualizador preserva configurações, volumes e redes compatíveis, mantém container de rollback e tenta restauração em caso de falha.

Retenha `/root/securitysearch-backups/<timestamp>`, imagens e volumes antigos: o novo container pode montar cópias de dados privados dessa pasta. Os backups contêm configurações sensíveis. Não execute o Compose antigo após a troca. Leia o [guia completo](OPERATIONS-0.9.19.md) para rollback, limitações de mounts, redes e herança das opções Docker.

Se NPM também definir CSP, permita o script e fetch local na rota `/images`; políticas adicionais podem bloquear a rolagem. O pacote não modifica NPM. Remova consultas das configurações de log do proxy e mantenha tokens de continuação privados.

## Validação

`sh scripts/test.sh` requer PHP com curl/DOM/mbstring/APCu/sodium/fileinfo/Imagick, Python 3, Git, fish e Node.js para os testes. Os testes usam provedores simulados em localhost, sem buscas externas. Consulte a [auditoria](AUDIT-0.9.19.md).

A página inicial desktop foi capturada e inspecionada em navegador real. Build Docker, deploy real, rolagem/animação em navegador/dispositivo, disponibilidade dos provedores e desempenho no VPS não foram verificados neste ambiente. O script executa as verificações de candidato e troca no servidor quando você o rodar.
