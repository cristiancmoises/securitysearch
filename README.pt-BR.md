# Security Search v0.9.24

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![Página inicial preta](docs/screenshots/securitysearch-0.9.21-home-black.png)

Renderização local anterior; não é uma captura atual da VPS. Aparência mantida;
esta versão usa recursos **28**. Proxy de pesquisa PHP baseado no
[4get](https://git.lolcat.ca/lolcat/4get), mantido para [SecurityOps](https://securityops.co).
Buscas normais e temas prontos funcionam sem JavaScript. Imagens locais e melhorias
opcionais na busca de imagens usam scripts locais. Provedores externos podem falhar.

## Operadores externos do Redlib — correção de atribuição

As instâncias externas do Redlib usadas ou indicadas pelo SecuritySearch são
fornecidas e operadas por pessoas ou organizações terceiras independentes,
**não pela Security Ops**. A Security Ops mantém a integração do SecuritySearch,
não essas instâncias externas. Seus operadores definem suas políticas e
disponibilidade. Consultas enviadas pelo provedor Reddit chegam à instância
selecionada; se habilitado, o fallback pode enviá-las a outra instância após uma
falha. A declaração de ausência de rastreamento do SecuritySearch não é uma
garantia sobre esses serviços. News RSS (Google/Bing) continua sendo um provedor
separado. Esta correção não altera o roteamento nem os controles de privacidade.

A correção `redlib-attribution-1` cria um commit normal **após** a tag publicada
v0.9.24; não move a tag nem substitui seus arquivos. Aplique e implante com o kit
`securitysearch-attribution-fix-1` correspondente. Publicar o commit na `main` não
reescreve releases existentes. [Detalhes](docs/REDLIB-ATTRIBUTION.md).

## Notícias independentes do Redlib

O novo padrão **News RSS** consulta Google News RSS e, se falhar, Bing News RSS.
São manchetes de publicadores, **não conteúdo do Reddit**. Reddit continua opcional
e pode permanecer indisponível. A edição pode ser inglês/EUA ou português/Brasil.
Selecionar uma única fonte desativa o fallback. Preferências já salvas continuam
respeitadas: selecione News RSS ou abra `/news?scraper=newswire` para trocar.

O diagnóstico recebido mostrou duas respostas HTTP 200 sem resultados Redlib e com
indicadores de desafio, além de HTTP 418 do Nadeko. Aumentar timeouts ou ignorar o
parser não transforma essas páginas em notícias. Não há resolução de CAPTCHA,
troca de identidade nem rotação de proxies para contornar recusas.

O novo critério de deploy exige manchetes e resultados de busca neutra reais e não
vazios da mesma fonte RSS, dentro da VPS. O relatório detalhado fica em
`news-live.json`; fonte e edição são persistidas e conferidas após a troca. Se
ambas falharem, o contêiner atual não é substituído. Os endpoints RSS externos são
best-effort, não uma API garantida. Testes locais não comprovam disponibilidade.

## Desempenho e segurança

Apenas manchetes públicas sem consulta podem entrar no cache: 120 segundos frescas
e até 600 segundos para fallback antigo, claramente identificado e datado. Busca
por palavra não entra no cache. Fonte/edição têm chaves distintas e trava curta
contra atualizações concorrentes. Recusas/desafios suspendem a fonte por dez
minutos; rate limits aguardam pelo menos cinco minutos, respeitando Retry-After
limitado. Não há retry em segundo plano nem fallback para uma busca vazia válida.

Rotas HTTPS fixas, IP público validado, conexão fixada ao IP, verificação TLS,
recusa de redirecionamentos e limite de 1 MiB. São no máximo duas tentativas
sequenciais, com 4,5 segundos por tentativa e nove segundos no adaptador. A primeira
resolução DNS síncrona pode ultrapassar o timeout do cURL: não há promessa de
latência absoluta nem ganho medido em todos os provedores.

XML recusa DTDs, entidades externas, XInclude, links inseguros e estruturas enormes.
São no máximo quarenta títulos com publicador/data/link. Descrições HTML, artigos
completos e imagens remotas não são carregados. Não há paginação RSS inventada.
Páginas de busca permanecem privadas/no-store/noindex e falhas não viram sucesso.

## Recursos preservados

Web, imagens, vídeos, música e Reddit opcional permanecem separados. Google/Brave,
Binternet moderno/legado, seis layouts, qualidade/formato, paginação normal, rolagem
infinita e animações visíveis com limites continuam. Preto puro, seletor de temas,
pré-visualizações e controles pretos na cor do tema são mantidos.

**My picture** abre o seletor local fora dos formulários. JPEG, PNG, WebP e GIF são
identificados pelos bytes; GIF vira imagem estática. Limites: 16 MiB/48 megapixels
após decodificação, saída normalizada de até 1920 pixels/2 MiB. Arquivo, nome e EXIF
não são enviados ao servidor. Padrão: sessão da aba; Remember on this device salva
somente com opção explícita. Remove my picture limpa os dois armazenamentos quando
o navegador permite. Armazenamento bloqueado ou cheio permite uso só na página.

Lain/SecOps históricos e derivados privados continuam fora do Git e das releases,
inclusive Codeberg. Reutilize o pacote externo somente no deploy. Sem ele, Lain
mantém a paleta e SecOps a alternativa Matrix. Tron conserva sua animação otimizada.
Onion/Tranco são preservados, sem inventar ranking quando não houver metadados.

## Revisão de manutenção r1

Corrige a sobrescrita do objeto no teste Reddit e a dependência opcional de ctype
no Retry-After. Mantém limites e verificações reais de implantação.
[Detalhes](docs/AUDITFIX-0.9.24-r1.md). Versão 0.9.24 / assets 28 não mudam.

## Aplicar e implantar

Para a árvore exata e limpa v0.9.24-r1, use a pasta completa extraída
`securitysearch-attribution-fix-1`, no computador local:

```fish
fish ./apply-securitysearch.fish ~/securitysearch
and fish ./deploy-securitysearch.fish ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Aceita a árvore exata v0.9.24-r1 ou a correção de atribuição já aplicada. Cria um
commit normal, preserva a tag publicada v0.9.24 e recusa alterações conflitantes. SSH `root@securityops.co`, porta **5119**. Preserva bind
`172.17.0.1:5140 → 80`, redes e configurações privadas compatíveis. Não recria NPM.
A suíte offline, prontidão do candidato e verificações reais RSS/Binternet continuam
obrigatórias. Preserve backups/rollback: eles podem conter snapshots montados.
Não use launchers antigos com o manifesto novo e não pule os testes.

## Publicar o commit de atribuição

```fish
fish ./push-code.fish ~/securitysearch
# Retomar somente este host; atualizar main, não anexos de release:
fish ./push-code.fish ~/securitysearch --host git.securityops.com.br
```

O publicador atualiza somente **main**, com tokens privados e pushes fast-forward.
Verifica o histórico de saída para impedir a reintrodução das imagens restritas.
Não usa APIs de release, não move tags e não substitui tarballs/checksums já
publicados. Publicar o código não faz deploy da VPS.

### Release publicada anteriormente

A tag **v0.9.24** e seus arquivos permanecem como snapshots históricos anteriores
a este commit. Não mova essa tag nem execute o publicador de release antigo sobre
o commit novo. Uma release futura precisa usar outra versão. Somente o arquivo
privado `-deploy.tar.gz` recebe o pacote externo de temas. Releases por
host: [Codeberg](https://codeberg.org/berkeley/securitysearch/releases/tag/v0.9.24),
[GitHub](https://github.com/cristiancmoises/securitysearch/releases/tag/v0.9.24),
[.co](https://git.securityops.co/cristiancmoises/securitysearch/releases/tag/v0.9.24),
[.com.br](https://git.securityops.com.br/cristiancmoises/securitysearch/releases/tag/v0.9.24).

## Auditoria

```sh
sh scripts/test.sh --keep-going
```

Todos os **57** comandos são obrigatórios. A suíte completa precisa das extensões
PHP curl/DOM/XML/mbstring/APCu/Imagick, Python, Node, Git e fish. Dependência ausente
não equivale a teste aprovado. [Auditoria](docs/AUDIT-0.9.24.md) registra limitações;
[notas bilíngues](docs/RELEASE-0.9.24.md) detalham a mudança.
Licença [AGPL-3.0](license.txt). **In Code We Trust.**
