# SecuritySearch 0.9.38 — complete bounded native audit evidence

## v0.9.38: verified audit completion and audit-only execution

Two zero exit codes are no longer sufficient: the native audit must produce every one of
115 pinned command headers exactly once, in order, followed by a complete zero-failure
summary. Empty, truncated, reordered, duplicated and summary-only transcripts fail. A
structured, atomically persisted result binds completion to the log and command inventory
hashes. Publication independently validates the same evidence.

Audit output streams to a new private log, capped at 8 MiB. Attached audit execution has a
1,800-second total deadline, including silent processes and pipe-holding descendants.
Timeout, output overflow, nonzero exit, incomplete container state or evidence-write failure
cannot qualify. Partial output is retained; the exact disposable audit container is removed.
Docker builds and source transfer are outside this deadline. Builds can use the network;
the audit container itself uses `--network none` and `--log-driver none`.

`--audit-only` applies a verified source update and builds/runs the native offline audit on
IONOS under the deployment lock. It does not export effective PHP configuration or copy
private data from the running production container, run live provider probes, clean old
objects, replace production or authorize publication. Docker inspection metadata (including
environment/configuration fields) is read in memory for identity and drift checks; raw
metadata is not written to the audit-only report. It writes a distinct report. Images, uploaded source and evidence remain for inspection;
building still consumes host resources. A 2 GiB/10,000-inode precheck is a floor, not a build
capacity guarantee. Production identity is checked before and after.

232 previously pinned visitor/runtime files, themes, providers, request budgets and asset version **40**
remain unchanged from v0.9.37. This is validation hardening, not a search-speed claim.
Transcript checks validate trusted suites; they do not attest against compromised root,
Docker, images or deliberately dishonest tests.

## v0.9.38: auditoria completa verificável e execução sem deploy

Dois códigos de saída zero não bastam: a auditoria deve registrar os 115 comandos exatos,
uma única vez e na ordem correta, seguidos pelo resumo completo sem falhas. Logs vazios,
truncados, reordenados, duplicados ou contendo apenas um resumo são recusados. Um resultado
atômico vincula a conclusão aos hashes do log e do inventário. A publicação verifica esses
registros de forma independente.

A saída é gravada progressivamente em um novo log privado, limitado a 8 MiB. A execução
anexada tem prazo total de 1.800 segundos, inclusive quando não produz saída. Timeout,
excesso de saída, códigos diferentes de zero, estado incompleto ou falha de gravação
impedem aprovação. O log parcial permanece e o container temporário exato é removido.
Builds Docker e transferência de código ficam fora desse prazo. Builds podem usar a rede;
o container de auditoria usa `--network none` e `--log-driver none`.

`--audit-only` aplica a atualização verificada e compila/executa a auditoria na IONOS sob o
bloqueio do deploy, sem exportar a configuração PHP efetiva ou copiar dados privados do
container de produção, consultar provedores ao vivo, limpar objetos antigos, substituir a
produção ou autorizar publicação. Metadados de inspeção Docker, inclusive campos de ambiente
e configuração, são lidos em memória para verificar identidade e alterações; os metadados
brutos não são gravados no relatório audit-only. Gera um registro separado. Imagens, código enviado e evidências permanecem; a compilação consome recursos.
A exigência de 2 GiB e 10.000 inodes livres não garante espaço para qualquer build.
A identidade da produção é verificada antes e depois.

Os 232 arquivos preservados de execução/visitantes, temas, provedores e recursos **40** não mudam.
É um reforço de validação, não uma medição de buscas mais rápidas. A verificação pressupõe
testes confiáveis; não protege contra root, daemon, imagem ou teste comprometidos.


Native acceptance, deployment and publication are not established by development fixtures. See the delivery validation report for executed results.

Preservation accounting: 232 prior pinned paths are unchanged. The one removed entry from
the earlier 233-path manifest is scripts/deployment_state.py, an operator helper extended
only to allow offline-audit-result.json and audit-only.json records. Visitor code is not
excluded from preservation checks.
