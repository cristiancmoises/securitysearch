# Direct upgrade: v0.9.30 → v0.9.40

The operator applies a cumulative binary Git patch directly to the current full clean
checkout. No intermediate installation or existing-tag movement is required.
The GitHub baseline read for this delivery was commit
`331cf550d8b279736126e7362fb699f99fc2bc66`, tree
`488b01ae481e8e4e95dad3743c2a175c43065c97`. See the machine-readable
[baseline record](../data/upgrade-baseline-0.9.30.json).
Only GitHub was independently queried; IONOS and the other remotes remain subject to runtime verification.

## Files and paths

Save each launcher and matching `.sha256` directly in `~/Downloads`:
`securitysearch-v0.9.40-deploy-ionos.fish`,
`securitysearch-v0.9.40-publish-four-remotes.fish`, and
`securitysearch-audit-diagnostics-0.9.40.fish`.
All three are self-contained. The complete update kit contains the same scripts, helpers,
cumulative/incremental patches, current documentation, review sources and validation evidence.
It is not a fresh-install application archive. Do not overlay the review source subset on production.
The optional installer only copies verified launchers; it does not connect to servers.

Use `~/securitysearch` on `main` with full history and no staged/unstaged/untracked work.
No local changes are reset, stashed or discarded. Existing private themes remain at
`~/.local/share/securitysearch/operator-themes-v1` and never enter the public patch.

## Exact sequence

1. Verify the downloaded checksum and native Fish syntax as shown in the README.
   `--check-only` inspects the payload and local checkout with no SSH or writes to the checkout.
2. Optionally run `--prepare-only`. It checks the exact base, applies/stages the reviewed
   cumulative patch, verifies the resulting tree and creates one normal commit using your
   configured Git identity. It requires only local Python/Git and no theme pack. It never
   connects, builds, tags, pushes, clears staged work or produces deployment acceptance.
3. Run `--audit-only`. It also prepares the commit when necessary, uploads/builds the
   candidate on IONOS, and requires all 119 native commands. It never replaces production.
4. After reviewing native audit success, run with no mode flag. Normal deployment repeats
   the audit, then the Google Web and Images/RSS/Binternet/image gates and guarded cutover.
5. Only after `DEPLOY COMPLETE`, run the separate four-remotes publisher. It revalidates
   production independently before tagging, packaging and pushing.

These are separate invocations, not a blind chained publish-on-exit script. Audit collection
success does not mean audit success. Audit-only success does not mean deployment success.

## Changes, failures and retries

Preparing applies one ordinary commit on your real history. A failed Git hook, missing
identity or interrupted commit may leave staged changes; inspect them instead of rerunning
with reset/force. Successfully prepared exact v0.9.40 is idempotent.
Both local and remote advisory locks remain; unrelated administrators can still interfere.

Audit-only leaves uploaded source, images and evidence and can consume substantial disk/CPU.
It does not clean older objects. Normal deploy can remove only eligible old stopped
SecuritySearch objects before a later candidate failure. It retains production and protected
rollback resources, NPM, networks, volumes, backups and build cache. Use `--cleanup-plan` first
when space is tight. Do not substitute `docker system prune` or delete volumes.

Missing/changed SSH host keys, incomplete audit transcripts, 429/CAPTCHA/no results, source
or image drift and conflicting tags block the workflow. Fix the cause and repeat the same
verified launcher. No native test or provider check is disabled to produce a green status.
The existing deployment code handles transaction rollback on a guarded cutover failure;
there is no new unconditional restore command that could overwrite newer production changes.

## PT-BR

Atualize diretamente da v0.9.30 para a v0.9.40. Não instale versões intermediárias e não
mova tags antigas. `--prepare-only` aplica o patch e cria apenas o commit local;
`--audit-only` constrói/testa na VPS sem substituir a produção; o modo normal exige
119/0 e provedores reais antes da troca; o publicador separado só roda depois de
`DEPLOY COMPLETE`. Trabalho staged após falha é preservado para inspeção, nunca apagado.
