# SecuritySearch v0.9.42

[English](README.md) · [Documentação](docs/INDEX.md)

Buscador PHP voltado à privacidade, baseado no 4get. Busca, filtros, paginação e temas funcionam
sem JavaScript; rolagem infinita e animações são opcionais. Sem histórico de consultas ou analytics.

## Novidades

| Área | Comportamento atual |
|---|---|
| Versão | Release **0.9.42**, assets estáticos **42**. |
| Imagens | **DeviantArt via SkunkyArt**: atalho nativo, links da arte original, previews assinados, filtros de orientação/rótulo de IA/Safe Search e paginação limitada. |
| LUMA | **Pesquise no LUMA**: posts públicos, contas, `@perfis` e `#tags` exibidos dentro do SecuritySearch, com previews pelo proxy local e paginação. |
| Aparência | **GoroDaimon**: fundo branco e letras pretas na homepage, configurações e resultados, sem wallpaper ou scripts obrigatórios. |
| Aceite obrigatório | **130 comandos nativos**, preservando os 124 de v0.9.41 como prefixo exato, mais resultados reais de Google **Web e Images**, RSS, Binternet e SkunkyArt. HTTP 200 com erro do provedor não aprova. |
| Fluxo do release versionado original | Uma auditoria nativa → gates ao vivo → promoção → verificação independente → limpeza de versões antigas. Sem auditoria isolada obrigatória ou remoção antes da compilação. |
| Desempenho | Reutilização de conexões dentro de cada requisição PHP, metadados DNS públicos limitados para o LUMA e pausa breve após falhas de conexão. Resultados não são armazenados; a latência externa continua variável. |
| Tranco | Metadados públicos de posição são atualizados antes de gerar a homepage e pelo timer diário, sem consultas externas nas requisições dos visitantes. |

O SkunkyArt usa `https://skunkyart.securityops.co`, não a grafia `securiyops.co`, e mantém sua
própria autenticação do DeviantArt. Disponibilidade e rótulos do provedor não são garantidos.
A remoção de versões antigas ocorre somente depois de verificar o novo serviço; veja os limites
e a perda do rollback antigo em [Limpeza depois do sucesso](#limpeza-depois-do-sucesso).

## Resultados LUMA e identidade independente

Escolha **Pesquise no LUMA**, imediatamente à esquerda do Pinterest na barra de busca, para
pesquisar dentro do SecuritySearch em `/images?s=sua-consulta&scraper=luma`. Consultas comuns
buscam posts e contas públicos; use `@perfil` para um perfil público ou `#tag` para um assunto.
São aceitos até 100 caracteres Unicode. Os filtros oferecem posts/contas e reels do perfil.
Uma consulta vazia abre o formulário interno. Links antigos de `/luma` redirecionam somente
para esta página, nunca para outro serviço.

Os resultados usam as visualizações de imagens e o proxy do SecuritySearch, sem iframe nem
navegação externa. O LUMA recebe do servidor a consulta, seus filtros suportados e seu próprio
cursor opaco; cookies, credenciais, referência e tokens de continuação do SecuritySearch não
são encaminhados. A busca funciona sem JavaScript. A rolagem opcional pode solicitar a próxima
página após os primeiros resultados; não há novas tentativas automáticas nem pesquisas antes
da seleção. Links de mídia e paginação expiram; reinicie a busca quando necessário.
Falhas aparecem na página interna de resultados, sem serem apresentadas como sucesso vazio.
Contas públicas sem foto usam um ícone genérico local identificado como foto indisponível,
não uma fotografia inventada. Contas privadas não aparecem como perfis acessíveis.

## Agilidade das buscas e metadados Tranco

| Caminho | Comportamento e limites |
|---|---|
| Transporte dos provedores | Chamadas sequenciais na mesma requisição PHP reutilizam DNS, sessões TLS e conexões. Cookies, credenciais, consultas e respostas não são compartilhados. Cada nova requisição de visitante recebe seu próprio pool. |
| Previews do LUMA | O host fixo usa o cache existente de metadados DNS públicos por até 15 segundos. Endereços são revalidados; respostas privadas ou mistas são rejeitadas e a conexão mantém o destino fixado. |
| Serviços indisponíveis | Falhas nativas de conexão usam a pausa existente de oito segundos na primeira página. Entrada inválida, falhas de conteúdo/parser, resultados vazios e paginação não marcam o serviço como offline. |
| Rodapé Tranco | A inicialização consulta a posição pública do domínio como `apache`, antes de gerar a homepage normal/gzip. O timer diário atualiza o dado e recria a página. Falhas preservam dados validados ou mostram indisponibilidade; nenhuma posição é inventada. |

O teste nativo HTTP/1.1 em loopback reduziu três conexões novas a uma em três chamadas
sequenciais, conferindo respostas idênticas e isolamento de cookies e cabeçalhos.
O atraso artificial do teste verifica a reutilização, não a velocidade real dos provedores.
O benchmark v4 separa TTFB da entrega completa do HTML; v3 permanece intacto.
O escopo padrão do LUMA mantém posts e contas. Escolha **Posts** ou **Accounts** quando
esse recorte atender à consulta; resultados não são descartados silenciosamente para acelerar.

Use `SECURITYSEARCH_RANK_REFRESH=0` somente para testes offline ou atualização administrada
separadamente. Em produção, a inicialização usa a atualização padrão e o timer existente
`securitysearch-tranco.timer`. Tranco mede popularidade de domínios, não qualidade de busca.

## Aparência branca GoroDaimon

Selecione **GoroDaimon** em **Choose appearance** na homepage ou em **Settings**, depois salve.
Fundos ficam brancos; letras, bordas e contornos de foco ficam pretos na homepage, menus,
controles e resultados. O logotipo aparece preto para manter o contraste, sem alterar o arquivo
original; as imagens dos resultados conservam suas cores. A preferência fica salva
neste navegador; o tema não carrega wallpaper nem script adicional.

## Identidade visual independente

A barra de busca usa símbolos genéricos de navegação, incluindo o símbolo independente de
livro do LUMA, em vez de logos de empresas. Os nomes identificam os destinos dos atalhos.
SecuritySearch e LUMA são projetos educacionais, independentes e de código aberto; não são
afiliados, patrocinados, endossados ou operados pela Meta ou pelo Instagram.
O LUMA pode acessar conteúdo público de fontes externas. Disponibilidade, restrições e termos
dessas fontes determinam o que pode ser obtido; uma homepage acessível não garante resultados.
Conteúdo privado, contorno de exigências de login e desvio de autenticação não são suportados,
e a integração não solicita nem encaminha cookies de plataforma ou tokens de acesso dos visitantes.

O propósito educacional e um aviso de independência não conferem imunidade jurídica nem
permissão para usar conteúdo ou marcas de terceiros. Direitos autorais, marcas, privacidade
e termos aplicáveis continuam relevantes; uma garantia jurídica exige análise de profissional
qualificado. Consulte as
[orientações oficiais da Meta para a marca Instagram](https://www.meta.com/brand/resources/instagram/instagram-brand/).

## Confiabilidade dos provedores

As correções de confiabilidade mantêm a versão **0.9.42**.
Falhas do Google CSE recebem classificações controladas, sem refletir o texto de erro externo.
As cinco rotas da API de busca tratam falhas de inicialização, filtros, provedor e PHP com HTTP 503,
`Cache-Control: no-store`, `Retry-After: 30` e uma mensagem JSON em `status`, sem detalhes internos.

O decodificador Svelte do Brave aceita dados literais e referências a parâmetros de funções
imediatamente invocadas (IIFEs), sem executar JavaScript. Rejeita expressões desconhecidas e
limita entrada/strings expandidas a 4 MiB, profundidade a 64 níveis e valores a 100.000 nós.
Estruturas ausentes e listas de imagens não vazias sem registros utilizáveis são falhas, não
sucessos vazios fabricados. Listas genuinamente vazias e filtros de formato legítimos continuam válidos.

Pexels, Unsplash e Pixabay têm configurações de proxy padrão explícitas. `false` significa conexão
direta; o operador pode selecionar um pool existente. Configurações privadas antigas precisam
adicionar os campos ausentes sem substituir seus outros valores. Não há fallback direto implícito.
As opções sem implementação de Archive.org em vídeos e Yep em notícias foram removidas; Yep Web
permanece disponível. O Cara valida a resposta e os registros de imagens antes de usá-los, preserva
listas vazias legítimas e retorna falha de formato controlada para dados malformados.

### Evidência — 2026-10-03

| Verificação | Resultado e limite |
|---|---|
| Regressões offline específicas | Contratos de erro do Google/API e parsing do Brave verificados; não são aprovação dos provedores ao vivo. |
| Auditoria nativa completa | Os 130 comandos obrigatórios passaram, sem falhas. Essa evidência é separada da disponibilidade dos provedores ao vivo. |
| Google e Brave em navegador real | Google Web e Images retornaram 20 resultados cada. Brave retornou 20 resultados Web, 20 na segunda página e 200 imagens. HTTPS e sandbox do navegador verificados; são buscas específicas, não garantia de disponibilidade permanente. |
| Buscas dos serviços após a troca final | Newswire retornou 40 notícias e SkunkyArt 23 imagens. Uma consulta ao Binternet expirou; uma verificação posterior e separada retornou 24 imagens e 24 na segunda página, sem reinício. A falha permanece registrada e não é contada como sucesso. |
| Outros provedores de imagens | Pexels retornou 24 imagens e Pixabay 100. Unsplash respondeu com redirecionamento HTTP 307 externo, que não foi seguido; Cara respondeu com HTTP 401 externo. As duas integrações indisponíveis não são declaradas funcionando; suas respostas na API pública são HTTP 503 controladas. |
| Repetição de respostas capturadas do Brave | 20 registros Web decodificados e renderizados; 167 registros de imagens decodificados e tratados como imagens utilizáveis. Sem rede ou novas consultas, isso não valida o transporte nativo até o provedor. |
| Verificações da API no navegador após ajustes de rede dos serviços | Binternet retornou 25 imagens e SkunkyArt 23, ambos com tokens de continuação, HTTPS 200 e TLS válido. A evidência vale para essas buscas, não para todos os provedores ou consultas. |
| Consulta a 57 combinações antes de substituir o código | 27 retornaram resultados, 4 respostas vazias e 26 estavam indisponíveis. Isso não significa que todos os provedores funcionam. |
| API JSON do Google | Inativa sem chave fornecida pelo operador; Google CSE é uma integração distinta. |
| Requisitos de aceite | Auditoria nativa completa 130/0, resultados reais nos gates obrigatórios, promoção protegida e verificação independente de fonte/imagem antes de publicar. As verificações acima não substituem esses requisitos. |

DNS de proxies, redes dos contêineres, serviços parados e restrições externas exigem verificações
operacionais separadas. Correções de parsing não comprovam a resolução desses problemas. Veja
[solução de problemas dos provedores](docs/TROUBLESHOOTING-0.9.42.md).

## Atualização em uma execução

Os kits autossuficientes de implantação e publicação v0.9.42 correspondem ao release original,
com árvores de origem fixadas; não contêm commits posteriores de manutenção em `main`.
Não reaplique um kit congelado ao checkout atualizado nem declare que ele implantou o `main`
atual. Preserve kits, listas de árvores permitidas e tags imutáveis.

Para manutenção, prepare a fonte atual revisada e os recursos privados necessários do operador
no host Docker. O `scripts/deploy-ionos.py` existente compila a árvore que contém o script e exige
o runtime nativo Docker/PHP de auditoria na VPS; defina explicitamente
`SECURITYSEARCH_VERIFY_GOOGLE=1`. Não é o instalador completo em uma execução. Verificações
independentes de fonte/imagem/evidência após a implantação e limpeza limitada após sucesso são
etapas separadas. Os comandos dos launchers abaixo se aplicam ao fluxo do release original.

Checkout completo e limpo em `~/securitysearch`, arquivos em `~/Downloads`, Fish, Python 3, Git,
OpenSSH e coreutils. VPS `root@securityops.co:5119`; pacote privado de temas em
`~/.local/share/securitysearch/operator-themes-v1`. PHP e Docker locais não são necessários.

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.42-deploy-ionos.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.42-deploy-ionos.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.42-deploy-ionos.fish"
end
```

O comando prepara commit normal, envia fonte/temas, compila, roda **130 comandos obrigatórios**
uma vez e prossegue automaticamente se auditoria e verificações reais dos provedores passarem.
Não é preciso executar auditoria isolada antes. `--audit-only` continua disponível como diagnóstico,
sem promoção ou limpeza; uma execução posterior de deploy repete a auditoria. `--check-only` não
altera o checkout nem usa SSH. `--prepare-only` somente prepara o commit local.

Os 124 comandos de v0.9.41 são prefixo intacto. v0.9.40 tinha 119 comandos; resultados anteriores
não aprovam esta versão. Google Web **e Images**, RSS, Binternet e SkunkyArt precisam passar.
HTTP 200 com erro do provedor, 429, CAPTCHA, fallback, dependências ausentes e logs incompletos
não autorizam promoção. Não há promessa de superar todos os sites no TTFB.

## Limpeza depois do sucesso

Somente depois de promover a nova versão e verificar sua identidade/evidência, a retenção remove
contêineres reconhecidos antigos e parados, inclusive o rollback antigo, imagens próprias sem uso
e arquivos de upload `.tar.gz` antigos reconhecidos.
**Após essa remoção, o rollback pelo contêiner antigo deixa de estar disponível.**
Não são alvos: contêineres em execução, imagens usadas por contêineres preservados, Tor, NPM,
volumes, redes, cache de compilação, backups privados ou diretórios de fonte extraída. Nada é
apagado antes da compilação. Falha parcial de limpeza é relatada separadamente, sem desfazer o
serviço aceito. Consulte [RETENTION](docs/RETENTION.md).

## Publicação nos quatro remotos

Apenas após `DEPLOY COMPLETE`:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.42-publish-four-remotes.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.42-publish-four-remotes.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.42-publish-four-remotes.fish"
end
```

GitHub `cristiancmoises/securitysearch`, Codeberg `berkeley/securitysearch`,
`git.securityops.co/cristiancmoises/securitysearch` e `git.securityops.com.br/cristiancmoises/securitysearch`.
Tokens são solicitados privadamente; nunca embutidos em URLs. Tags conflitantes não são movidas.
Sem reset, stash, rebase, force-push ou descarte de alterações. A publicação entre hosts não é atômica.

Instâncias Redlib são operadas por terceiros independentes, não pela Security Ops. My Picture não
envia fotos, nomes ou EXIF. Arte privada fica fora do Git e dos pacotes públicos. O kit não inclui
fontes tipográficas, credenciais ou histórico Git sintético. Capturas históricas são identificadas.
Não sobreponha `source-review/` na instalação.

[Operações](docs/OPERATIONS-0.9.42.pt-BR.md) · [Publicação](docs/PUBLISHING.pt-BR.md) ·
[Auditoria](docs/AUDIT-0.9.42.md) · [Changelog](CHANGELOG.md) · [Licença](license.txt).

Baseline corrigido v0.9.30: `488b01ae481e8e4e95dad3743c2a175c43065c97`.

Os comandos de auditoria da versão histórica v0.9.40 totalizam 119.
A auditoria atual exige 130/0.
