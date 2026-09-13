# SecuritySearch v0.9.41

[English](README.md) · [Português do Brasil](README.pt-BR.md) · [Documentação](docs/INDEX.md)

![Captura histórica do SecuritySearch](docs/screenshots/securitysearch-0.9.36-home.png)

Captura histórica local da v0.9.36 no Chromium, com rede bloqueada. Não é uma captura atual da
produção, resultado de pesquisa ao vivo nem medição de desempenho.

O SecuritySearch é um proxy de pesquisa em PHP, baseado no [4get](https://git.lolcat.ca/lolcat/4get)
e mantido pela Security Ops. Web, imagens, vídeo, notícias RSS e música preservam suas integrações.
Pesquisa, filtros, paginação e temas funcionam sem JavaScript. Animação, rolagem infinita e My Picture
usam scripts opcionais da própria origem. Não há novos anúncios, analytics externos ou histórico de consultas.

## Alterações

A aplicação usa **0.9.41** e os assets usam **41**. Títulos de imagens são limitados antes do escape,
evitando expansão excessiva do HTML/JSON. O filmstrip usa observação assíncrona da viewport, sem
leitura síncrona de geometria na rolagem. O Apache bloqueia aliases escapados da página inicial
interna, corrige cabeçalhos de erro e usa bytes sem compressão nas solicitações Range. A página
inicial anônima continua no caminho estático, sem PHP. Erros do Google incluem metadados limitados
sem consultas, corpos, credenciais ou sondagens adicionais. Nenhum bloqueio é tratado como sucesso.

O novo benchmark manual v4 ordena por **TTFB**, mantendo o tempo total do HTML separado. O v3 original
permanece byte a byte inalterado e ordena por entrega completa. Não há alegação de vitória pública
ou universal. Consulte [desempenho](docs/PERFORMANCE-0.9.41.md) e [pesquisa](docs/RESEARCH-0.9.41.md).

Os comandos históricos da v0.9.40 totalizam 119. Todos continuam como prefixo exato, com cinco
adições: a nova auditoria exige **124/0**. O resultado antigo de 119/0 na IONOS não aprova esta versão.

## Atualizar, auditar e implantar

Use o checkout completo e limpo na branch `main`, em `~/securitysearch`, e downloads em `~/Downloads`.
A base v0.9.40-r2 observada no GitHub é `b0ab9966f02dd0c41ad8f936347b64cee5642c9f`.
O atualizador cumulativo também aceita a árvore corrigida v0.9.30
`488b01ae481e8e4e95dad3743c2a175c43065c97` e as bases intermediárias exatas preservadas.
Ele verifica a árvore real, não apenas a versão. Trabalho desconhecido/sujo, clone raso ou outra
branch é recusado, sem reset, stash, rebase, amend, force-push ou descarte de alterações.

São necessários Fish, Python 3, Git, OpenSSH e coreutils localmente, não PHP/Docker. O destino é
`root@securityops.co:5119`, contêiner `security-search`, com o pacote privado
`~/.local/share/securitysearch/operator-themes-v1`. Preserve a verificação da chave SSH.

Salve o script completo e seu checksum em `~/Downloads`:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-deploy-ionos.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish" --check-only
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish" --audit-only
end
```

`--check-only` não altera arquivos nem usa SSH. `--prepare-only` cria somente o commit local normal.
`--audit-only` pode criar commit, enviar código/temas e compilar/testar na IONOS; **não é somente leitura**.
Não substitui a produção, limpa objetos antigos, consulta provedores, cria tags ou publica. Código,
imagens e evidências ficam no disco. O sucesso exige **124/0** e `AUDIT ONLY COMPLETE — NOT DEPLOYED`.

Após revisar a auditoria, execute o script verificado sem `--audit-only`. O deploy normal repete
todos os testes e exige Google **Web e Images**, RSS, Binternet e validações de imagem reais.
HTTP 429, CAPTCHA, fallback, resultado vazio, dependência ausente ou log parcial não aprovam o deploy.
A limpeza limitada de objetos antigos/parados do SecuritySearch pode acontecer antes de uma falha
posterior. Produção, rollback protegido, NPM, recursos compartilhados, redes, volumes, backups e
cache de compilação são preservados. Não há prune global.

## Commit e publicação nos quatro remotes

Somente após `DEPLOY COMPLETE`, use o publicador correspondente:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-publish-four-remotes.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
end
```

O publicador verifica independentemente código/imagem e evidências antes de enviar `main`, a tag
anotada imutável `v0.9.41`, notas e pacote-fonte/checksum ao GitHub `cristiancmoises/securitysearch`,
Codeberg `berkeley/securitysearch`, `git.securityops.co/cristiancmoises/securitysearch` e
`git.securityops.com.br/cristiancmoises/securitysearch`. Tokens são solicitados privadamente, não
salvos nem inseridos em URLs. Tags anteriores não são movidas. Conflitos são recusados. Os quatro
hosts não formam uma operação atômica; `--host` permite retomar trabalho parcial correspondente,
e `--verify-only` verifica sem publicar.

## Diagnósticos, benchmark e privacidade

`securitysearch-audit-diagnostics-0.9.41.fish` coleta evidências filtradas; `--explain-latest` e
`--explain-report FILE` leem relatórios offline. A coleta não verifica a identidade do código nem
autoriza publicação. Preserve leitores antigos para relatórios com outra árvore. Não compartilhe
configuração efetiva, tokens ou dados privados.

Execute `sh scripts/test.sh --keep-going` no runtime nativo. Ausência de Fish, extensões PHP ou
Alpine/FPM gera falha, não aprovação. O limite do log é 8 MiB e o prazo da auditoria é 1.800 segundos;
esses limites não cobrem uploads ou builds. O relatório externo de validação lista os testes executados.

`fish tools/secops-web-benchmark-v4.fish --self-test` testa offline. A execução normal faz GETs reais
sequenciais para sete páginas iniciais e salva HTML/CSV/JSON em `~/Downloads/securityops-benchmarks`.
Não roda automaticamente no deploy. O resultado vale para aquela máquina/rede/momento, não para
todos os buscadores ou a qualidade/latência de resultados de pesquisa.

Instâncias externas Redlib são operadas por terceiros, não pela Security Ops. My Picture não envia
imagem, nome do arquivo ou EXIF. Originais/derivados privados ficam fora do Git e dos assets públicos.
O kit não contém fontes tipográficas, credenciais, arte privada ou histórico Git sintético. É um
kit de atualização/revisão, não uma instalação do zero; não sobreponha a pasta source-review/.

[Operação](docs/OPERATIONS-0.9.41.pt-BR.md) · [Publicação](docs/PUBLISHING.pt-BR.md) ·
[Auditoria](docs/AUDIT-0.9.41.md) · [Notas](docs/RELEASE-0.9.41.md) ·
[Changelog](CHANGELOG.md) · [Licença](license.txt).
