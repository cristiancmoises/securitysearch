# Troubleshooting — v0.9.40

| Symptom | Meaning and safe action |
|---|---|
| Unsupported tree | A v0.9.30 version string is insufficient. Compare `git rev-parse 'HEAD^{tree}'` with the baseline record. Preserve the checkout; do not reset or force-apply the patch. |
| Dirty/shallow/non-main checkout | Preserve/finish your work and inspect history. No automatic stash, reset, rebase or branch switch is performed. |
| Commit hook/signing/identity failure | Patch changes may remain staged. Inspect `git status` and `git diff --cached`; complete/review the commit intentionally. No stage cleanup is attempted. |
| SSH host-key/authentication refusal | Verify the server/key through a trusted channel. Do not disable StrictHostKeyChecking or paste passwords into a command. |
| Missing private theme pack | Restore your existing private operator pack outside Git. Do not copy restricted originals into the public source. Prepare/check modes do not need that pack. |
| Insufficient capacity | Inspect the existing disk/inode report and read-only cleanup plan. Do not globally prune Docker or delete volumes/backups. The precheck is only a minimum. |
| Missing native dependency | The complete test must run in the intended serving-image runtime. Missing curl/DOM/XML/mbstring/APCu/Imagick/Fish/FPM is failure, not an ignorable test. |
| Audit timeout, output cap or incomplete inventory | Retain the exact execution/log evidence. No count-only or exit-only approval is accepted. |
| Google 429/CAPTCHA/unavailable | Not a pass and not replaced by Brave success. Honor the recorded retry policy; do not bypass the gate. |
| RSS/Binternet/image acceptance failure | Candidate is not qualified for cutover. Review the retained specific gate rather than using a health endpoint as a substitute. |
| Newest diagnostic invalid or incompatible | The reader refuses it rather than using an older passing result. Use the matching v0.9.40 tool and checksum pair. |
| Collection exit 0 but failed tests displayed | Collection succeeded. Native audit/deploy status is independent and remains failed. |
| Publication refuses audit-only evidence | Run and review the full guarded deployment. Audit-only deliberately cannot authorize publication. |
| One forge failed after others succeeded | Retain the same commit/tag/assets. Retry the same publisher or the affected `--host`; do not move a tag or force-push. |
| Runtime/source/image drift | Another change invalidated the evidence. Stop and inspect; do not waive the identity check or blindly restore an older container. |

Diagnostic files use mode 0600 and filtered fields. An abrupt kill or storage failure can
leave an incomplete JSON/checksum pair; the reader refuses it. Checksums detect changes,
not authorship. Native execution evidence remains on IONOS and is verified independently.

## PT-BR

Nenhuma falha deve ser convertida em aprovação por remoção de teste/gate. Preserve logs e
trabalho local; não use reset, force-push, prune global ou bypass de chave SSH.
Saída zero da coleta significa apenas relatório coletado. Auditoria nativa exige 119/0;
publicação exige deploy completo e verificação independente. Publicação parcial é retomada
com os mesmos arquivos/tag/commit, nunca sobrescrevendo um conflito.


## Native audit repair r2 (includes r1)

For the 115/4 native-audit failure, use **securitysearch-v0.9.40-deploy-ionos-r2.fish** and the paired **securitysearch-v0.9.40-publish-four-remotes-r2.fish** in place of the original launchers above. Run `--check-only` then `--audit-only`; 119/0 and all live gates remain mandatory. The application version stays 0.9.40. See [r1 repair details](AUDITFIX-0.9.40-r1.md).

Revision r2 also restores configuration after oversized generated output without masking the original fixture error. Each comparison reads at most the captured original length plus one byte. See [r2 cleanup details](AUDITFIX-0.9.40-r2.md). Native 119/0 acceptance is still required.
