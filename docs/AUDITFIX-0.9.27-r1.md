# v0.9.27-r1 — source-derived readiness version check

Application **0.9.27**, asset **31**. This is a deployment/readiness repair, not a
change to search, themes, compiled rendering, Docker configuration or NPM.

## Reproduced failure

The supplied v0.9.27 source declares `config::VERSION = 31`, but the updater's
`healthy()` function still compared its PHP output with the literal `30|Black`.
The readiness unit fixture returned that same obsolete literal. As a result, the
offline suite passed while a correctly configured candidate was rejected with
“New source/config version is masked by an old setting or mount.”

The authoring reproduction runs the actual shipped PHP configuration under
`php -n`, obtaining `31|Black`, and feeds it to the unmodified original readiness
function. It reproduces that exact exception before any homepage request. Docker
inspection and execution are fixtures in this reproduction; no VPS is contacted.
This establishes a deterministic shipped-code defect, not an observed inventory
of the operator's mounts or production configuration.

## Correction and safeguards

The deployer now has one `ASSET_VERSION = 31` constant for both its identity check
and versioned image-enhancement URLs. A mandatory regression independently obtains
the marker from the actual source configuration and from the real
`docker/gen_config.php` output, rather than returning a copied expected marker.
It checks the release version against `data/release-version.txt` as well.

Older and newer assets, incorrect themes, malformed output and failed helper
readiness are still rejected. A bounded mismatch message reports the expected and
observed asset, without reflecting arbitrary output or asserting that a mount is
definitely responsible. The validator does not edit settings or accept both old
and new versions. The existing mount preflight remains unchanged.

The transaction suite includes candidate-readiness failure before production
mutation; replacement failures still exercise rollback. All 70 former audit
commands remain in order with the same arguments. One additional suite brings
the total to **71**. Offline audit failures still block cutover; RSS, Binternet,
requested Google verification and replacement checks remain enforced.

## Apply and deploy

Use the matching `securitysearch-update-0.9.27-r1` kit on the exact clean already
applied v0.9.27 source. The helper creates a normal local commit, preserves local
work and tags, and refuses conflicts. Use this kit's deploy/publish launchers,
not the old exact-manifest launchers. The tag remains `v0.9.27`; an existing tag at
another commit is not moved. A later release number is needed to replace already
published source, not a force push.

Reuse the operator-theme pack outside Git. Keep the previous backup directory
`/root/securitysearch-backups/20260911T012523Z` and every other backup/rollback path.
Do not lower the running application's asset version, delete configuration mounts,
change PHP settings, initialize Git in a container or bypass the readiness check.
The normal deployment rebuilds and audits a corrected candidate; no manual VPS
source edit is required. Provider availability remains an external condition.

## Português do Brasil

O código v0.9.27 usa asset **31**, mas o teste de prontidão ainda exigia
`30|Black`; o mock repetia o mesmo valor antigo. A correção compara com o asset
atual e valida esse contrato usando a configuração PHP real e seu gerador Docker.
Versões antigas, temas incorretos e respostas inválidas continuam sendo recusados.
Nenhuma configuração, montagem, código de busca ou proteção é removida. Use o kit
r1 correspondente, mantenha os temas privados fora do Git e preserve os backups.
