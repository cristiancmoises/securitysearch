# Operações v0.9.42

As instruções completas estão no [README em português](../README.pt-BR.md).
SSH root@securityops.co:5119. O kit versionado original prepara commit, envia fonte, compila, executa
130 testes obrigatórios, valida os provedores, promove e verifica o serviço e só então remove
objetos antigos reconhecidos. Não precisa rodar --audit-only antes. Falha de auditoria ou provedor
mantém a produção e não dispara retenção. A remoção do rollback antigo após sucesso é intencional.
Publique apenas após DEPLOY COMPLETE com securitysearch-v0.9.42-publish-four-remotes.fish.

Os kits congelados v0.9.42 não incluem commits posteriores de manutenção em `main`. Não os
reaplique ao checkout atualizado nem declare que implantaram o `main` atual. Preserve kits,
listas de árvores permitidas e tags imutáveis. A manutenção usa a fonte atual revisada e os
recursos privados necessários do operador no host Docker: `scripts/deploy-ionos.py` compila sua
própria árvore, exige o runtime nativo Docker/PHP de auditoria na VPS e precisa de
`SECURITYSEARCH_VERIFY_GOOGLE=1` explícito. Não é o instalador completo em uma execução;
verificações independentes de fonte/imagem/evidência após a implantação e limpeza limitada após
sucesso verificado continuam como etapas separadas.

## Verificação dos provedores

Separe contrato da aplicação, conectividade do caminho configurado e resultados úteis do provedor.
Homepage saudável ou HTTP 200 não comprovam uma busca bem-sucedida. Resultados genuinamente vazios
não são falhas de transporte, mas não aprovam um gate que exige resultados não vazios.

Use `lib/search_probe.php` e os gates existentes para validação limitada. Mantenha consultas,
configuração efetiva, endereços de proxies, credenciais e respostas brutas fora de relatórios públicos.
Registre somente estado, motivo, contagens e escopo necessários. Respeite bloqueios, desafios e
limites de requisições; não alterne endereços para contorná-los.

Em falhas de transporte, confira primeiro o DNS dos proxies e a rede exata dos serviços existentes.
No SkunkyArt, verifique o acesso do proxy reverso ao serviço. No Binternet, examine o estado e o
erro de inicialização do contêiner existente. Preserve configurações e confira a capacidade;
não recrie serviços, faça prune global ou afrouxe os gates.

## Manutenção e evidência — 2026-10-03

O Google CSE usa classificações controladas de falha. As cinco rotas da API retornam HTTP 503,
somente a mensagem de estado no JSON e cabeçalhos de não armazenamento e nova tentativa, sem
refletir texto externo ou detalhes do PHP. O Brave decodifica dados Svelte literais e referências
de IIFEs limitadas, sem executar JavaScript. Estruturas ausentes e listas não vazias sem imagens
utilizáveis falham; listas genuinamente vazias e filtros legítimos continuam válidos.

Duas entradas de proxy com DNS obsoleto foram removidas, e a conectividade dos serviços existentes
SkunkyArt e Binternet foi restaurada. Esses ajustes não garantem todos os provedores.

| Verificação | Evidência e escopo |
|---|---|
| Respostas capturadas do Brave, offline | 20 registros Web decodificados/renderizados e 167 imagens utilizáveis decodificadas/tratadas, com rede desativada. Isso valida parsing, não transporte nativo até o provedor. |
| Buscas da API no navegador após ajustes de rede dos serviços | Binternet: 25 imagens; SkunkyArt: 23 imagens. Ambos retornaram tokens de continuação por HTTPS 200 com TLS válido. |
| Consulta a 57 combinações antes de substituir o código | 27 respostas com resultados, 4 vazias e 26 indisponíveis; não é aprovação universal dos provedores. |

A API JSON do Google fica inativa sem chave fornecida pelo operador e é distinta do Google CSE.
Configurações privadas precisam dos campos explícitos `PROXY_PEXELS`, `PROXY_UNSPLASH` e
`PROXY_PIXABAY`. O padrão público é `false` (conexão direta); selecione um pool existente quando
exigido pela sua política de rede. Adicione campos ausentes sem sobrescrever outros valores do
operador. O backend não inventa uma rota silenciosamente quando falta configuração.
Archive.org em vídeos e Yep em notícias não são oferecidos sem implementação da categoria; Yep Web
permanece. O Cara rejeita estruturas malformadas com falha de formato controlada, sem crash do PHP
ou sucesso vazio fabricado. A proteção da API também cobre inicialização do adaptador e filtros.
Cada implantação exige sua própria auditoria nativa completa 130/0, resultados reais de Google Web
e Images, RSS, Binternet e SkunkyArt, promoção protegida e verificação independente de fonte/imagem.
Publique somente após o aceite verificado; remova versões antigas reconhecidas somente após sucesso
verificado. As verificações limitadas acima não são recibo de auditoria completa ou implantação.
