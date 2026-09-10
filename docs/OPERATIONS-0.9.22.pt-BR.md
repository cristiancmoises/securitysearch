# v0.9.22 — operação e publicação

Use os comandos completos do [guia de operação](OPERATIONS-0.9.22.md). O kit aceita
a árvore exata da v0.9.21 já aplicada ou a árvore publicada v0.9.20; não sobrescreve
trabalho local nem força tags. Não use o lançador v0.9.21 com os hashes novos.

A falha fornecida veio da fixture HTTP de notícias: somente a origem principal
era interceptada, e o fallback tentava rede durante a auditoria offline. A correção
cobre todas as origens e instala bloqueios de rede exclusivamente nos processos
filhos de teste. O timeout não foi ampliado e nenhum teste obrigatório foi removido.

Execute `restore-theme-assets.fish --repo ~/securitysearch --output
~/.local/share/securitysearch/operator-themes-v1` no kit para recuperar as imagens
históricas exatas. O programa primeiro usa o Git local, depois URLs fixas do commit
no Codeberg/GitHub. Valida o tamanho e o blob Git antes de converter. Pillow é
necessário somente nesta preparação; no Guix o lançador usa um ambiente temporário.
O pack fica fora do repositório e não deve ser anexado a releases.

No deploy, use `--theme-assets ~/.local/share/securitysearch/operator-themes-v1`.
Somente o arquivo privado enviado por SSH recebe as imagens. O commit, tarball
público e bundle de recuperação não recebem `secops.gif`, `lain.gifv` ou seus
derivados. O mesmo fonte limpo é publicado nos quatro hosts. Tron já corresponde
ao original; sem o pack, Lain mantém a paleta e SecOps usa Matrix. Não se altera
nem remove o histórico antigo já publicado; `.gitignore` sozinho não basta.

As animações derivadas têm até 640×400, 90 quadros amostrados e prévias pequenas.
Ao pular quadros, suas durações são combinadas; não é uma cópia exata quadro a
quadro. O programa recusa arquivos fora dos limites em vez de ignorar a validação.

O deploy preserva SSH 5119/root@securityops.co, bind 172.17.0.1:5140→80 e NPM.
Suíte nativa offline, prontidão e teste real Binternet continuam obrigatórios antes
da troca. Preserve todos os diretórios de backup e comandos de rollback exibidos.
O timer Tranco é opcional. Publicar não faz deploy. Para retomar somente a release
do host que falhou, use `publish-securitysearch.fish ~/securitysearch --host
git.securityops.co`; tar.gz e checksum são verificados e enviados juntos à release.

A resolução DNS PHP inicial ainda é síncrona. O novo cache limitado reduz trabalho
repetido, mas não comprova ganho de velocidade nem prazo absoluto para todos os
provedores. Consulte [auditoria](AUDIT-0.9.22.md) para distinguir testes executados,
fixtures e dependências indisponíveis.
