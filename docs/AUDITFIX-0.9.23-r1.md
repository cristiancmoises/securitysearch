# v0.9.23-r1 — Redlib audit expectation repair

Application version remains **0.9.23**, asset marker **27**. The v0.9.23 news
selection and browser-local picture implementations are preserved unchanged.

The operator supplied an audit summary with 45 passing commands and failures in
`tests/regression.php` and `tests/http-regression.py`. That tail does not include
their exception bodies. Source inspection found and isolated reproduction proved
two stale expectations in those exact suites:

* General navigation still required `Reddit` to link to `libre.securityops.co`,
  although v0.9.23 deliberately removed that instance. A shared assertion now
  requires the effective approved primary in both templates. Other external links,
  unresolved placeholders and retired-host rejection remain checked.
* The HTTP failure trace expected privacyredirect, nadeko, privacyredirect. The
  actual approved third host is **redlib.privadency.com**. The test now expects
  three distinct hosts in the specified order. Its eight-second client timeout,
  sub-three-second offline failure check, no-network tripwires, 503 checks,
  cooldown checks and continuation-origin assertions are unchanged.

A six-method contract suite checks real PHP template rendering for each approved
primary with fallbacks on/off, rejects old/wrong links, and compares the actual HTTP
assertion's expected array to real pool failure/cooldown callbacks. These focused
checks need no substitute native extensions. They do not replace the full native
PHP or HTTP tests. All 47 old commands remain; the new suite makes 48.

Deployment audit failures now show bounded excerpts of failing command sections
instead of only the last successful tests. Unstructured logs still use the bounded
tail. The complete log is always retained and any failing exit still blocks
cutover. The news gate's mocked test no longer prints a misleading live-verification
message. Real live news checks remain unchanged and must happen after offline tests.

Use the **matching r1 kit** on an exact clean applied v0.9.23 checkout. It creates a
normal repair commit, preserves local work and never retargets existing tags.
Do not bypass the full audit or manually alter the manifest hashes.

The authoring environment still lacks PHP curl/DOM/mbstring/APCu/Imagick, fish and
Docker. The reproduced assertion defects are fixed, but a successful full native
Docker audit or live provider check is not claimed. See the kit AUDIT.md and logs
for executed checks. A different native error must be investigated, not skipped.

Reuse the external operator-theme pack on private deployment. Historical Lain and
SecOps images remain excluded from all new source commits and release attachments,
including Codeberg. The updater keeps all existing live gates and rollback rules.
Retain `/root/securitysearch-backups/20260910T174754Z` and every other backup.

## Português do Brasil

Esta revisão corrige duas expectativas antigas dos testes: o link Reddit ainda
apontava para a instância removida, e a sequência HTTP repetia privacyredirect em
vez de verificar privadency como terceiro host. A aplicação, os limites de tempo,
os testes completos e a seleção real do Redlib continuam preservados. O resumo
recebido não contém as exceções completas; as falhas foram reproduzidas em partes
isoladas dos testes. A aprovação nativa completa ainda depende da auditoria na VPS.
Use o kit r1 correspondente, reutilize os temas privados fora do Git e preserve os
backups. Nenhuma implantação, tag ou release é publicada ao preparar este kit.
