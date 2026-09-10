# v0.9.22-r2 — deployment imports and complete failure reporting

Application version **0.9.22**, asset marker **26**. Includes the earlier r1
archive-independent operator-theme tests. PHP search logic, timeouts, themes,
artwork exclusion policy and the Docker image recipe are not changed by r2.

## Reported failure

The native VPS audit reached `tests/deploy-regression.py` and failed while loading
`operator_themes` from `scripts/deploy-ionos.py`. The transaction suite imports the
deployer by filename, so Python does not automatically search the deployer's
sibling directory. The branch was reached only when `static/operator-themes`
was present. A source-only test run without the optional pack did not exercise it.
The uploaded tail does not establish that the later HTTP suite passed.

The original failure was reproduced in an extracted source tree without `.git`,
with a tiny local WebP fixture pack and no `PYTHONPATH`. No original artwork,
external request, Docker engine or VPS was used in this reproduction.

## Repair

The deployer loads the adjacent `operator_themes.py` with
`importlib.util.spec_from_file_location`, using its own `__file__`. It never adds
the caller's directory to `sys.path`, does not accept an ambient same-named module,
and rejects a missing or symlinked helper. The existing validator is called
unchanged before Docker operations. A dangling optional-pack symlink now also
fails closed instead of being mistaken for an absent pack.

Eleven focused import/pack tests include the transaction suite in four layouts:
source archive and Git checkout, each with and without a private pack. They cover
unrelated working directories, poisoned `PYTHONPATH`/`sys.modules`, missing or
linked helpers, invalid checksums, and rejection before Docker activity.

`sh scripts/test.sh --keep-going` executes every listed command and summarizes
all failures at the end. Any failure keeps a nonzero exit status and blocks
cutover. Normal `sh scripts/test.sh` retains fail-fast behavior. No previous test
command or assertion was removed, and no timeout was enlarged. The isolated Docker
audit uses keep-going mode so a native-only error does not hide all later suites.
Six shell-runner regressions verify failure aggregation, missing-program failure,
argument handling, fail-fast behavior and unchanged deployment acceptance checks.

## Operator commands

Download and verify the matching `securitysearch-update-0.9.22-r2` kit. On an exact,
clean applied v0.9.22 or v0.9.22-r1 checkout:

```fish
fish ~/Downloads/securitysearch-update-0.9.22-r2/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.22-r2/deploy-securitysearch.fish \
    ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Reuse the existing external operator pack. Do not regenerate, commit or upload it
to a forge release. Without that explicit option, the public palette/Matrix
fallbacks remain in use. Existing different tags, work and release files are
preserved; no reset, stash, force push, tag movement or backup deletion is used.

The full native offline gate, candidate readiness and live Binternet gate remain
mandatory on the VPS. Nothing is installed into production merely by applying
this local patch. Native environment/provider success is not inferred from mocked
Docker transactions or from missing-extension authoring tests.

## Português do Brasil

A falha só aparecia com o pacote privado de temas presente: o teste importava o
atualizador pelo caminho do arquivo, mas o import procurava o módulo irmão no
caminho do chamador. A correção usa o caminho explícito do helper ao lado do
atualizador, sem alterar `PYTHONPATH` ou `sys.path`. Links inválidos e pacotes
alterados continuam sendo recusados antes de qualquer operação do Docker.

A auditoria Docker agora executa todos os comandos e reúne as falhas no final.
Qualquer falha mantém o status de erro e impede a troca do serviço. Não remova os
testes, não inclua `.git` no contêiner e não ignore a auditoria. Use o kit r2
correspondente, mantenha o pacote privado fora do Git e preserve todos os backups.
