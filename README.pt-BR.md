# SecuritySearch v0.9.42

[English](README.md) · [Documentação](docs/INDEX.md)

Buscador PHP voltado à privacidade, baseado no 4get. Busca, filtros, paginação e temas funcionam
sem JavaScript; rolagem infinita e animações são opcionais. Sem histórico de consultas ou analytics.

## DeviantArt via SkunkyArt

A busca de imagens inclui **DeviantArt via SkunkyArt**, com atalho nativo na barra, links da arte
original, previews pela mídia assinada da instância, filtros de orientação e rótulo de IA, conteúdo
maduro e paginação. O endereço configurado é `https://skunkyart.securityops.co`, não a grafia
`securiyops.co`. A autenticação do DeviantArt permanece no SkunkyArt. A disponibilidade e os rótulos
do provedor não são garantidos.

## Atualização em uma execução

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
e arquivos de upload `.tar.gz` antigos sob `/root/securitysearch-incoming`.
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

Contexto histórico: os comandos de v0.9.40 totalizam 119. A auditoria atual exige 130/0.
