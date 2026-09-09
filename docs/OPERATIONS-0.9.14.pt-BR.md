# Security Search v0.9.14 — operação

Atualização completa do código obtido no Codeberg, commit `0751f145ff7b68d1f9c070c8bd8af79cd7bff717`, destinada ao seu contêiner v0.9.12-r2. As melhorias v0.9.13 já presentes no repositório foram preservadas.

- Busca de vídeos do YouTube via Invidious dentro do Security Search. Reprodução abre sua instância Invidious; preferências existentes continuam sendo respeitadas.
- Busca do Pinterest via Binternet em `images.securityops.co`, com paginação. O filtro de formato verifica extensões dos resultados de cada página: o Binternet não oferece filtro nativo de formato. Buscas devem ser curtas (limite de 64 bytes após escape HTML).
- Seis visualizações: grade, grade compacta, galeria, feed grande, lista e faixa horizontal. Novo modo de alta qualidade, limitado a 1280×1280, além de prévia rápida e original. GIF/WebP/APNG continuam respeitando preferências de movimento e economia de dados.
- Menu solicitado e links Git atualizados; mensagem centralizada “In Code We Trust.”. O menu Img usa `img.securityops.co`, enquanto a integração Binternet usa `images.securityops.co`, conforme solicitado.
- O arquivo Lain removido no último commit não foi restaurado. O fundo ficou estático, preto/ciano, sem requisição a arquivo inexistente.
- Proteção contra endereços especiais/não públicos reforçada; logs Apache sem consulta/IP/Referrer; geração de configuração com escape seguro; limites menores para favicons e transferências originais.

## Envio e implantação

Baixe o arquivo `.tar.gz` e seu `.sha256` em `~/Downloads`; execute o comando fish fornecido. A transferência usa `root@securityops.co`, porta SSH **5119**. A extração ocorre em diretório novo; o programa não sobrescreve a árvore em execução.

O atualizador preserva configurações efetivas, variáveis, volumes, redes e o vínculo **172.17.0.1:5140 → 80**. Primeiro compila a imagem mantendo o serviço atual online. Depois valida um contêiner candidato sem porta publicada, realiza a troca e verifica saúde/conteúdo. Falhas na troca acionam tentativa de restauração do contêiner anterior. Uma breve interrupção é esperada durante a troca.

É necessário Docker, Python 3, curl e espaço/memória para a compilação paralela ao serviço atual. Montagens que ocultariam código novo, IP estático de contêiner, modo de rede incompatível ou porta diferente interrompem a atualização antes da troca, com diagnóstico.

**Guarde `/root/securitysearch-backups/<data>` e o contêiner/imagem anterior.** O novo contêiner pode usar os diretórios privados desse backup como volumes somente leitura. O backup contém configurações sensíveis. Não o publique nem apague automaticamente.

O contêiner novo passa a ser administrado pelo atualizador fornecido. Não execute `docker compose up/down` na pasta antiga após esta troca. Para voltar, use o script de rollback informado ao final. Ele identifica o contêiner antigo pelo ID, inclusive quando o nome principal está ausente.

O upstream do NPM permanece `http://172.17.0.1:5140`. Confira `https://securityops.co/` e faça buscas reais em Google, Invidious e Binternet. A configuração de logs do NPM é separada e não foi alterada.

## Verificações e limites

Regressões PHP, simulações de implantação, análise estática e teste HTTP local foram executados. O teste local de 50 requisições teve 50 sucessos, mediana 2,46 ms e p95 7,39 ms: esses números medem a página inicial local, não buscas externas nem sua VPS.

Os parsers aceitaram os dados reais obtidos dos seus serviços: 20 vídeos e 18 imagens. O PHP local não resolve hosts externos; portanto, a integração completa com a rede da VPS precisa ser validada após implantação. Docker/Apache de produção, decodificador ImageMagick 7, reprodução de vídeo, comportamento visual no navegador e carga real da VPS não foram certificados neste ambiente.

A auditoria encontrou limitações remanescentes em provedores legados, tratamento de favicons e cache de miniaturas. Não há alegação de ausência de vulnerabilidades. Consulte [auditoria em inglês](AUDIT-0.9.14.md) e [guia completo](OPERATIONS-0.9.14.md).

```sh
sh scripts/test.sh
python3 scripts/benchmark-http.py --url http://172.17.0.1:5140/ --requests 50 --concurrency 4
```

Não houve push nos remotos nem acesso/deploy na VPS durante a preparação.
