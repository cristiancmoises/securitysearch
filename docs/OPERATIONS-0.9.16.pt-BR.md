# Security Search v0.9.16 — operação

Versão completa sem JavaScript no navegador, com marcador de assets **20**. Ícones compactos para Search, Image, Pinterest e YouTube ficam em coluna. Os rótulos aparecem ao passar o mouse ou focar pelo teclado. Alvos de 36 px no desktop e 44 px para ponteiros de toque (fonte raiz de 16 px).

Tron passa a ser o padrão no código, Compose e atualizador. O deploy migra o padrão antigo; preferências de tema salvas no navegador continuam válidas. Se você salvou outro tema, escolha Tron em Settings. O fundo leve usa CSS e não baixa o GIF antigo de 12 MB. Binternet, Invidious, seis layouts, qualidade alta, formatos disponíveis em cada provedor e os links/rodapé solicitados são mantidos.

O erro do Google passa a oferecer ações claras, mantendo a consulta e opções compatíveis. A resposta continua HTTP 503 e interrompe o modo automático. O cooldown local de 30 segundos não garante que o Google tenha liberado a instância.

**Automatic pages** é a alternativa sem JavaScript à rolagem solicitada: substitui páginas, sem mover o viewport ou anexar imagens. Intervalo de 10–120 segundos, padrão 30. Play next pages carrega a próxima página e inicia até dez avanços; Pause mantém os resultados. Next manual durante a pausa não reinicia o timer. Erros e páginas vazias/finais interrompem o modo automático; páginas vazias com continuação conservam Next manual.

São exibidos até 24 resultados por página do provedor; resultados adicionais omitidos são informados. Original/Preview/View animation são links nativos. Não há mais lightbox, tentativas automáticas de miniaturas ou carregamento por observação de rolagem.

Snapshots são criados somente após Play: criptografados em APCu, 600 segundos por página, sem renovar o prazo em Pause/Play. Limites: 128 KiB por frame, 64 slots globais, 20 criações por endereço de cliente a cada dez minutos. Um proxy que apresenta o mesmo endereço para todos compartilha essa cota. Falhas de armazenamento mantêm a navegação manual. A URL contém a chave de acesso; não publique esses links. Configure também os logs do NPM para omitir consultas. Apache já omite query strings, mas o pacote não modifica NPM.

O navegador controla o Refresh: o intervalo varia com carregamento/configurações e abas em segundo plano podem avançar. Pause antes de mudar de aba. Não é rolagem infinita real.

## Deploy IONOS

Requisitos no host: root, Python 3, Docker, curl, flock e recursos para compilar com o serviço antigo online. O atualizador espera `security-search` com **172.17.0.1:5140 → 80**. Mantém configuração efetiva, variáveis, volumes, redes e aliases; rejeita mounts que ocultariam o novo código ou políticas Apache/PHP/ImageMagick.

Baixe o arquivo completo, SHA-256 e script para `~/Downloads`:

```fish
fish ~/Downloads/deploy-securitysearch-v0.9.16.fish
```

O script envia por SSH **5119** a **root@securityops.co**, verifica checksum, extrai em pasta nova e executa o atualizador Python. O candidato não publica portas; verifica saúde, asset 20, tema Tron efetivo, rodapé, CSP e snapshots. O corte causa uma breve interrupção. O contêiner antigo fica retido com reinício automático desativado; rollback restaura a política anterior. O rollback manual usa o mesmo lock e verifica a imagem esperada. Falhas do host/Docker podem exigir recuperação manual.

**Não apague `/root/securitysearch-backups/<timestamp>`**: o serviço novo pode montar dados privados dessa pasta. Preserve também contêiner/imagem antigos enquanto precisar de rollback. As capacidades, opções de segurança e limites Docker existentes são herdados; o atualizador não aplica automaticamente toda a configuração Compose de uma instalação nova.

NPM continua com upstream `http://172.17.0.1:5140`. Não execute o Compose antigo após o corte: o novo contêiner é gerenciado pelo atualizador. O build Docker e o deploy real não foram executados neste ambiente. Teste HTTPS, busca neutra, serviços externos, views e Play/Pause no VPS.

`sh scripts/test.sh` executa regressões PHP/Python e HTTP local com provedor simulado. Não faz buscas externas. Consulte a [auditoria](AUDIT-0.9.16.md) e o [guia completo em inglês](OPERATIONS-0.9.16.md) para evidências e limites.
