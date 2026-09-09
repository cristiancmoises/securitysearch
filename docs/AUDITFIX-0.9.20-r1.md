# v0.9.20-r1 — offline audit harness correction

This is a maintenance revision of the v0.9.20 update kit. The application release
remains **0.9.20** and the asset marker remains **24**. It does not change the
Binternet parser, provider transport, default theme, runtime PHP configuration,
Docker image recipe, reverse-proxy settings, or acceptance criteria.

## Reported failure

The supplied VPS log identifies `tests/provider-http-regression.php` and reports
`Cannot redeclare function curl_setopt()` at line 6. The new test declared four
cURL doubles unconditionally. The syntax scanner correctly uses ordinary
`php -l`, with the cURL extension available in the Docker audit runtime. The
runtime command in `scripts/test.sh` already disables those four functions for
that child process, but those flags do not apply to the earlier syntax scan.

The original local authoring environment lacked cURL. Its successful syntax scan
therefore did not establish compatibility with the extension-enabled runtime.
This was a test-harness defect; it is not evidence of a failed image search or
of a missing cURL installation on the VPS.

## Correction

All four mock declarations are now conditional. Before installing any mock or
loading application code, the test refuses to run if any of the four functions
is still defined. This also rejects partially configured isolation. No real
network call is permitted through that path. The existing assertions about
transport deadlines, options and provider call sites are unchanged.

A new child-process regression covers ordinary syntax checking, the documented
isolated command and all 15 nonempty combinations of predeclared functions.
Predeclared functions are tripwires that fail if called. On a runtime with
ext-curl, the suite additionally verifies that an ordinary test invocation fails
safely while the properly isolated invocation passes. The fixtures are not
represented as a substitute for that native-extension check.

A failing offline audit still prevents cutover. The updater retains the complete
private `offline-audit.log` and now prints its last 120 lines (at most 24,000
characters) in the terminal. The offline audit has no production environment,
private volumes or external network. The code still removes only its disposable
audit container and retains the backup directory. The real candidate/Binternet
gates and rollback transaction are unchanged.

## Apply and deploy

Use the matching **r1** kit; the original deployment manifest expects the
unrepaired file bytes and must not be edited to bypass validation.

```fish
fish ~/Downloads/securitysearch-update-0.9.20-r1/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.20-r1/deploy-securitysearch.fish ~/securitysearch
```

Application detects a clean exact original v0.9.20 checkout and applies only the
repair delta, creating one normal local commit. It also supports the original
v0.9.19 baseline using the complete corrected patch. Both paths verify expected
file hashes and stop on local changes, conflicting files, changed baseline or
commit-hook failure. No reset, stash, force-push or removal of backups is used.
Repeated application to the exact repaired state does not create another commit.

The update starts a fresh deployment attempt over SSH port 5119. Do not change
the failed incoming source tree or delete the old backup to retry.

## Validation limits

See the r1 kit's `AUDIT.md` and `audit/` for the checks actually executed and raw
logs. Docker and the PHP extension-enabled full suite still need the mandatory
VPS gate. A successful harness fix is not a promise that every subsequent test,
provider or live readiness check will pass. Historical v0.9.20 audit records are
retained separately and are not relabelled as successful r1 full-runtime tests.

## Português do Brasil

A falha ocorreu no teste fornecido: ele redeclarava funções do cURL durante a
checagem de sintaxe. Esta revisão protege as quatro declarações e recusa a
execução caso alguma função real ainda esteja habilitada naquele processo de
teste. Não desative o cURL no `php.ini` nem ignore a auditoria. Aplique usando o
kit r1 e mantenha os backups existentes. A nova tentativa continua exigindo a
suíte offline completa e as verificações reais antes de substituir o serviço.
