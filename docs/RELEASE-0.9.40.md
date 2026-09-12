# SecuritySearch v0.9.40 — direct v0.9.30 upgrade

Date: 2026-09-12. Application identifier 0.9.40; asset identifier 40.
Implemented deployment candidate, not an assertion of IONOS acceptance or publication.
No v0.9.39 installation/tag is needed; its unpublished search-state work is included here.

## Application corrections

- Preserve literal queries `0`, `all`, `any` and zero-valued filters in generated navigation,
  pagination and recovery links. Keep absent/default-filter omission semantics.
- Encode continuation values independently, preserving valid backend tokens as one parameter.
- Use normalized search input for web oracles and empty-result text.
- Start music output buffering before output/provider handling, retaining error status and
  cache/retry headers. This uses response-buffer memory and is not a latency optimization.

## Cumulative changes since the deployed v0.9.30

Includes retained v0.9.31–v0.9.38 static-delivery/favicon improvements, bounded image proxy/PNG
validation, usable preview selection, compact integrity-checked operator resources, complete
ordered audit validation and bounded audit-only execution. Current r2 diagnostic behavior is
preserved with release-specific inventory/identity. No new query caches, provider fanout,
JavaScript dependencies or altered theme artwork are introduced by the current search-state work.

## Deployment and publication

Three self-contained Fish launchers cover deployment/preparation/audit, diagnostics and
independent four-forge publication. New `--prepare-only` applies and commits locally without
SSH, themes, builds, tags or pushes. Audit/deploy still prepare automatically when necessary.
The exact observed v0.9.30 tree upgrades directly; eight later retained baselines are also supported.
Current README/README.pt-BR, operations, publishing, migration, audit, performance,
troubleshooting and documentation-index files are updated; older versioned records stay historical.

119 ordered native commands must pass with complete execution evidence, followed during normal
deployment by genuine Google Web and Images/RSS/Binternet/image gates. Publisher independently
checks source, runtime, image and retained evidence. Locks, drift checks, rollback protection,
private-media exclusions, strict host-key checking and immutable tags remain mandatory.
Four-host publication can be partial on failure; conflicting tags/assets are never overwritten.

## PT-BR

Atualização direta da v0.9.30 para v0.9.40, sem instalar versões intermediárias.
Consultas/filtros com zero e os termos all/any são preservados; a continuação é codificada;
os oráculos recebem a consulta normalizada; falhas de música mantêm HTTP 503 e cabeçalhos.
`--prepare-only` cria apenas o commit local. Documentação atualizada em inglês/PT-BR.
Assets 40; auditoria obrigatória 119/0 e provedores reais antes do deploy/publicação.
