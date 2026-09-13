# Publicação — v0.9.41

Este é o guia atual. Documentos antigos numerados são históricos.
Use primeiro a [atualização direta da v0.9.30](UPGRADE-0.9.30-to-0.9.41.md).

O launcher de deploy cria o commit normal sobre seu histórico `main`. Para somente
aplicar o patch revisado e criar esse commit localmente:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-deploy-ionos.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-deploy-ionos.fish" --prepare-only
end
```

Esse modo não usa SSH, temas, build, tag ou push. Não executa `git add .`.
Bases desconhecidas/sujas/shallow e branches diferentes de main são recusadas.
Repetir sobre a árvore-alvo não gera commit duplicado. Falhas preservam staged para inspeção.
A identidade e a configuração de assinatura Git do usuário não são alteradas.

Depois da auditoria nativa e de `DEPLOY COMPLETE`, execute o publicador separado:

```fish
begin
    cd "$HOME/Downloads"
    and sha256sum --check securitysearch-v0.9.41-publish-four-remotes.fish.sha256
    and fish --no-config --no-execute "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
    and fish --no-config "$HOME/Downloads/securitysearch-v0.9.41-publish-four-remotes.fish"
end
```

O script verifica produção, commit/árvore, imagem, runtime, 124 comandos completos e
evidências reais de Google Web/Images, RSS e Binternet. Preparação, audit-only, diagnóstico
coletado e endpoint saudável isolado não autorizam publicação.

Destinos: `github.com/cristiancmoises/securitysearch`, `codeberg.org/berkeley/securitysearch`,
`git.securityops.co/cristiancmoises/securitysearch` e
`git.securityops.com.br/cristiancmoises/securitysearch`.
Envia `main`, a tag anotada imutável `v0.9.41`, release não-prerelease, tar.gz e SHA-256.
O bundle incremental de recuperação mantém a base v0.9.30 e permanece como material local;
os assets públicos normais são tar.gz e checksum. Usa seu histórico real, não reconstruções sintéticas.

Não move tags anteriores, não sobrescreve assets conflitantes e não força push ou reescreve
histórico. Arte privada restrita e prompts são barrados pelo empacotador. Tokens são
informados localmente e não são salvos nem impressos. TLS continua validado.

Os quatro hosts são independentes: uma falha pode deixar alguns atualizados. Reexecute
com os mesmos commit/tag/assets ou selecione `--host git.securityops.com.br`.
Resultados parciais idênticos são reutilizados; conflitos bloqueiam. `--verify-only`
verifica a produção sem publicar. O rótulo estável da release não certifica ausência de bugs.


## Correção r2 da auditoria nativa (inclui r1)

Para a falha nativa 115/4, substitua os launchers anteriores por **securitysearch-v0.9.41-deploy-ionos.fish** e **securitysearch-v0.9.41-publish-four-remotes.fish**. Execute `--check-only` e depois `--audit-only`; 124/0 e todas as verificações reais continuam obrigatórias. A versão do aplicativo permanece 0.9.41. Consulte os [detalhes da correção r1](AUDITFIX-0.9.40-r1.md).

The superseded `securitysearch-v0.9.41-publish-four-remotes.fish` pins the original tree; do not use it for the r2 repair.

A revisão r2 também restaura a configuração após uma geração maior que o limite de captura, sem ocultar a falha original do teste. O comparador lê no máximo o tamanho do original mais um byte por comparação. Consulte a [correção r2](AUDITFIX-0.9.40-r2.md). A auditoria nativa 124/0 continua obrigatória.
