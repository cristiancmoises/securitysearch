# Security Search v0.9.17 — operação e IONOS

A rolagem infinita volta a anexar imagens, inclusive lateralmente em Filmstrip. O painel Automatic pages, temporizadores e snapshots foram removidos. Links antigos com frame retornam 410 sem consultar o provedor. Tron, ícones compactos, integrações, filtros, seis layouts e navegação continuam.

O recurso usa um único script local, como exceção necessária ao pedido anterior sem JavaScript. As demais páginas bloqueiam scripts. Settings → Load more images while scrolling → No desativa o recurso. Next page funciona sem JavaScript e com Save-Data.

Há somente uma busca em andamento, até 24 imagens por resposta, limite de 1 MiB e prazo de 25 segundos. Imagens novas usam carregamento lazy e decodificação async. Antes de exceder 480 cartões, continue com Next page em uma página nova. Páginas vazias preservam a continuação manual; falhas oferecem reinício sem repetir o token consumido. O bloqueio de um provedor pode continuar.

## Deploy

Coloque estes três arquivos em `~/Downloads`: `securitysearch-v0.9.17.tar.gz`, `securitysearch-v0.9.17.tar.gz.sha256` e `deploy-securitysearch-v0.9.17.fish`.

```fish
fish ~/Downloads/deploy-securitysearch-v0.9.17.fish
```

O script verifica o checksum, copia para root@securityops.co via SSH 5119, confere novamente, extrai em diretório novo e executa o atualizador. O container existente deve se chamar `security-search` e usar exatamente **172.17.0.1:5140 → 80**. NPM continua com `http://172.17.0.1:5140`.

O VPS precisa de root, Python 3, Docker, curl, flock e recursos para build e candidato. O serviço atual fica ligado durante o build; há breve interrupção na troca. O candidato valida saúde, versão 21, Tron, CSP e script de paginação. O atualizador preserva configurações, volumes e redes compatíveis, mantém container de rollback e tenta restauração em caso de falha.

Retenha `/root/securitysearch-backups/<timestamp>`, imagens e volumes antigos: o novo container pode montar cópias de dados privados dessa pasta. Os backups contêm configurações sensíveis. Não execute o Compose antigo após a troca. Leia o [guia completo](OPERATIONS-0.9.17.md) para rollback, limitações de mounts, redes e herança das opções Docker.

Se NPM também definir CSP, permita o script e fetch local na rota `/images`; políticas adicionais podem bloquear a rolagem. O pacote não modifica NPM. Remova consultas das configurações de log do proxy e mantenha tokens de continuação privados.

## Validação

`sh scripts/test.sh` requer PHP com curl/DOM/mbstring/APCu/sodium, Python 3 e Node.js para os testes. Os testes usam provedores simulados em localhost, sem buscas externas. Consulte a [auditoria](AUDIT-0.9.17.md).

Build Docker, deploy real, rolagem em navegador/dispositivo, disponibilidade dos provedores e desempenho no VPS não foram verificados neste ambiente. O script executa as verificações de candidato e troca no servidor quando você o rodar.
