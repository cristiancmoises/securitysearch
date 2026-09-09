# Packaging and deployment — v0.9.19

Create a source release from a clean committed checkout. If the annotated tag already exists at this commit, retain it and skip the tag command:

```sh
sh scripts/test.sh
# At the reviewed release commit, create the tag once:
git tag -a v0.9.19 -m "SecuritySearch v0.9.19"
./release.sh 0.9.19
python3 scripts/build-publication-kit.py
```

The release script rejects uncommitted/untracked source and forbidden prompt artifacts. It archives Git HEAD, supplies the empty icons directory, validates required source/assets and writes a SHA-256 checksum. Secrets, generated icon caches and Python bytecode are excluded. Only the two image enhancements and their two source-only Node tests may use JavaScript; unexpected scripts fail packaging. The prompt is a separate handoff artifact and never enters Git or the application image.

Deliver:

- `securitysearch-v0.9.19.tar.gz`
- `securitysearch-v0.9.19.tar.gz.sha256`
- `securitysearch-publication-v0.9.19.tar.gz` and its `.sha256`
- `deploy-securitysearch-v0.9.19.fish` (also inside the publication kit)

The fish script uses SSH port 5119, verifies the checksum locally and remotely, extracts to a fresh root-only directory on IONOS, then calls `scripts/deploy-ionos.py`. [The operations guide](OPERATIONS-0.9.19.md) describes the required existing bind, candidate, rollback and retained data. Do not overlay files into the running container: OPcache disables source timestamp checks in the image.

No Docker image is bundled. The script builds the image on the VPS while the old service stays online, validates the candidate, and performs a brief cutover. Source-only tests do not replace this gate.

The publication kit includes an incremental Git bundle based on the known public history, the complete source archive/checksum and the fish launcher. See [publication](PUBLISHING.md) or [publicação em pt-BR](PUBLISHING.pt-BR.md). Both READMEs use the bundled real screenshot at `docs/screenshots/securitysearch-home.jpg`.
