# v0.9.22-r1 — archive-independent operator-theme audit

Application version remains **0.9.22**, asset marker **26**. This repair changes
only tests, the test runner and this note. Application PHP, provider networking,
Docker settings, operator artwork preparation and publication restrictions are
unchanged.

## Failure and correction

The supplied `20260910T154254Z` audit failed in
`test_prepare_refuses_repository_destination`. It passed the extracted source
root to `prepare`, which requires an actual Git checkout. `git rev-parse` returned
128 before the intended output-path policy ran. The test had passed in a developer
checkout but was not independent of that checkout's Git metadata.

The test now initializes its own disposable, empty Git repository in a temporary
directory. It checks both the repository root and a nested output path using the
specific `Keep operator assets OUTSIDE the Git checkout` error. Tripwires assert
that no original-image read/download or conversion is attempted, and no output
directory is created. It does not accept arbitrary exceptions as success.

A new two-case archive-layout regression executes the entire operator-theme suite
from a small source-only tar fixture and from the same fixture with `.git`. The
fixture contains existing source bytes and the public Tron image only. It confirms
that the archive case is not a Git checkout, succeeds without adding `.git` there,
and leaves all copied source unchanged. The real destination-rejection fixture
creates its separate temporary repository in both cases.

The new suite is mandatory in `scripts/test.sh`; all prior suites remain. No
production acceptance gate, no-network test boundary or expected exception is
bypassed. Pillow remains optional for the operator-side image-conversion test;
its documented skip in the audit image was not the cause of this failure.

## Applying the repair

Use the matching `securitysearch-update-0.9.22-r1` kit on an exact, clean, applied
v0.9.22 checkout. The helper verifies the tree, applies the four-file repair and
creates a normal commit. Repeat application is a no-op. Dirty trees, unrelated
commits, conflicting tags and failing commit hooks are preserved and reported.
Do not reset local work or manually edit the kit's hashes.

Use this kit's deploy launcher after repair; the original v0.9.22 launcher expects
old test hashes. Reuse the existing verified external operator-theme pack with
`--theme-assets` when deploying historical Lain/SecOps backgrounds. Do not copy
that pack into Git or into any source-release attachments.

The full native offline suite, candidate readiness and live Binternet gate must
still succeed on the VPS before cutover. Retain
`/root/securitysearch-backups/20260910T154254Z` and other backup/rollback paths.
Do not add `.git` to the deployed application or initialize a repository inside
the audit container.

The application tag stays `v0.9.22`. Existing different tags or release packages
are never moved or overwritten; stop and resolve that conflict separately if an
unrepaired version was already tagged or published. Do not run the older manual
Git/cURL snippets to replace an existing asset.

## Português do Brasil

A falha estava no teste fornecido: ele tratava o código extraído no contêiner como
um checkout Git. Agora o teste cria um repositório temporário próprio e verifica
a mensagem específica da recusa de gravar imagens dentro do checkout. Uma nova
regressão executa a suíte de temas em um arquivo-fonte sem `.git` e em um checkout.
Nenhuma verificação foi removida; não é necessário instalar Pillow no contêiner,
criar `.git` no aplicativo ou ignorar a auditoria. Use o kit r1 correspondente e
preserve seus backups e o pacote de imagens fora do repositório.
