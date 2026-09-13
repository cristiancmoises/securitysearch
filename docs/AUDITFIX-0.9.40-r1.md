# v0.9.40 native-audit repair r1

The reported IONOS audit observed **115 commands passed / 4 failed**. No production
replacement or publication was authorized. This repair retains application **0.9.40**,
asset **40**, the exact 119-command inventory, and all live-provider acceptance gates.

## Configuration contamination between native suites

`tests/static-home-http-regression.py` starts the real Docker entrypoint. That entrypoint
runs `docker/gen_config.php`, deliberately rewriting `data/config.php` while Apache runs.
The old test left that generated configuration in the shared audit source directory.
Three later release-preservation suites therefore hashed generated PHP rather than source.

Running the actual generator in an isolated copy reproduces the exact reported hashes:

- Source: `b68c7d9c07b35e0d98c8e2ea5d5328e25b027e4e949aa7bb95f0fa373f18ec83`.
- Generated: `247068fecd8df26190defda4c7e6457b879fc9ff79ec57525662c3c2ff9eff90`.

The native fixture now captures source configuration through an open descriptor, runs the
same native entrypoint and HTTP assertions, and restores the source bytes after normal
completion or a caught failure. Unsafe linked/replaced files and restoration failures stop
the audit. A killed process cannot guarantee cleanup; failed/incomplete execution remains
unacceptable. Only this test's configuration is restored; unrelated runtime changes remain
visible. `runtime_history.py`, all preservation hash manifests, the production configuration,
its generator, and the Docker entrypoint are unchanged. No hash is replaced with observed
runtime output, and configuration is not exempted from preservation tests.

## Favicon test isolation

The old favicon HTTP suite reused `fixture.invalid` across independent test cases. A miss
intentionally leaves a short-lived negative entry in native APCu. Deleting test marker files
and rewriting the PNG does not delete that entry. A subsequent symlink/miss case can therefore
correctly avoid the transport while the test incorrectly expects a fresh attempt.

Each independent test now has a distinct `.fixture.invalid` host. Two additional real-HTTP
cases exercise valid disk delivery after a failed refresh and independence between hosts.
No production TTL, cache admission, lease, transport, file-safety check or APCu setting is
relaxed. The original pasted excerpt did not include the favicon assertion traceback;
therefore this diagnosis identifies a concrete fixture defect, not proof of every possible
cause of that native suite's failure. Rerun the entire native audit.

## Deployment

Use `securitysearch-v0.9.40-deploy-ionos-r1.fish` and its checksum. It accepts the exact original
v0.9.40 tree (`c179affb7a919476c69a6d526498073bd98e8ec6`) and supported earlier baselines, including
corrected v0.9.30. It creates a normal repair commit rather than amending or resetting history.
First run `--check-only`, then `--audit-only`. The repaired candidate must achieve **119/0**.
Normal deployment repeats that audit and the live Google Web and Images, RSS, Binternet and
image checks. Use the paired `securitysearch-v0.9.40-publish-four-remotes-r1.fish` only after
`DEPLOY COMPLETE`. The previous publisher pins the old tree and is not suitable for r1.
No existing tag is moved. A conflicting existing v0.9.40 tag stops publication.

The r1 diagnostic launcher retains the same report schema and refusal rules, with its
expected-tree metadata pinned to the repaired source. Source identity remains NOT VERIFIED.
Use the original diagnostic launcher to review reports created before r1; retain both files.
Audit-only may commit/upload/build and retain images and evidence, but does not replace
production or clean old objects. Normal deployment retains its existing bounded cleanup.

## Português do Brasil

A auditoria da IONOS informou **115 comandos aprovados e 4 com falha**. O teste HTTP nativo
reescrevia `data/config.php` ao iniciar o entrypoint real e deixava a configuração gerada para
os testes seguintes. A correção restaura os bytes originais ao terminar o teste, inclusive
quando ocorre uma exceção, sem alterar os hashes esperados ou ignorar a configuração.

O teste de favicon também reutilizava o mesmo domínio entre casos independentes, permitindo
que a cache negativa do APCu contaminasse o caso seguinte. Agora cada caso usa um domínio
exclusivo. O APCu não é desativado, suas políticas não mudam e a auditoria continua obrigatória.
O trecho recebido não contém o traceback específico do favicon; a nova execução nativa é
necessária para confirmar o resultado completo.

Baixe o deploy r1 e o publicador r1, junto com seus SHA-256. Execute `--audit-only` antes do
deploy; só publique após `DEPLOY COMPLETE`. Nenhuma tag antiga é movida e nenhum trabalho
local é descartado. A versão do aplicativo permanece **0.9.40**.
