# v0.9.19 — release, documentation and regression audit

Prepared 2026-09-09. The release integrates the completed v0.9.18 source into Codeberg history at `0751f145ff7b68d1f9c070c8bd8af79cd7bff717`, preserves its `static/misc/lain.gifv` deletion, updates asset/config marker to **23**, and adds bilingual release documentation, a real homepage screenshot and publication tools. It does not claim additional search-provider availability or a measured runtime improvement over v0.9.18.

The restored v0.9.18 source archive was verified against SHA256 `ab3902b86b831047e6dccdf1450bdcc9842441a93ad95059eda8675e71e21772` before integration. The release bundle starts from known public ancestor `34ac6854420a0dd41b05b1c558ce2d879068bf23`, so an existing checkout can receive the complete update without losing published history.

## Executed validation

- The complete established `sh scripts/test.sh` source suite passed: 12 PHP regression suites, two Node state suites, Docker transaction simulations, isolated localhost HTTP controller tests and synthetic benchmarks. Tests use neutral fixtures and generated tiny animations; they do not issue live provider searches.
- All **111 PHP files** passed syntax checks. Python, fish and shell syntax and Git whitespace checks passed.
- **14 publication regression tests** passed. They cover exact tagged-archive provenance and payload validation, checksums, tag protection, actual ordinary Git pushes to local bare repositories, divergent remote history, idempotent release reruns, draft/upload recovery, asset conflicts, valid Forgejo multipart uploads, sanitized errors, per-host failure continuation and credential-safe redirect handling.
- **Nine fish bundle-import integration tests passed**, checking actual temporary Git repositories: exact fast-forward and annotated tag import, preservation of a removed file, backup branch creation, reruns, and refusal of dirty/diverged checkouts, corrupted inputs and conflicting local tags.
- Source packaging requires a clean checkout and an annotated release tag at HEAD. Archive checks retain the existing required-file, forbidden-prompt and two-runtime-script restrictions. The publisher compares every archive entry with the tagged Git source before requesting any token-authenticated remote action.

The runtime suites retain coverage for bounded GIF/WebP/APNG decoding and original animated bytes, static posters, two-loading/four-active animation state, all six image views, continuation past ten pages, separate motion/scroll opt-outs, provider/filter/date isolation, Google-to-Brave recovery with a shared deadline, Redlib parsing and routes, private-address rejection, cURL option reset, API errors, CSP, configuration and deployment rollback simulations. Full behavior and remaining provider limits are documented in the historical v0.9.18 audit included in the source archive.

## Performance evidence

Local homepage HTTP with four PHP workers: **50/50 successes**, concurrency **4**, median **2.04 ms**, p95 **5.11 ms**. These are loopback timings from this run, not browser rendering, upstream provider, public TLS or VPS measurements. They are not a controlled before/after comparison.

| Synthetic workload, 1,000 iterations | Median | p95 |
|---|---:|---:|
| Render homepage | 0.0684 ms | 0.1110 ms |
| Parse 50 Invidious videos | 0.0759 ms | 0.1054 ms |
| Parse 50 Binternet images | 0.2495 ms | 0.3561 ms |

Reported peak PHP allocation was 2 MiB for each synthetic workload. Warm template output remained byte-identical. Image transfer, browser decoding/memory and provider latency are outside these measurements.

## Screenshot and documentation

A real browser visited `https://securityops.co/` on 2026-09-09. The captured viewport was **1363 × 936**, using `/static/style.css?v22` and `/static/themes/Tron.css?v22`. The 31,463-byte JPEG is embedded by relative path in both READMEs, so it remains part of the repository on each forge. The caption explicitly identifies the live asset-22 capture; v0.9.19 retains that interface and advances the package marker to 23.

The homepage capture was inspected for the Tron theme, compact search actions inside the input's right edge, navigation, plain Wiki/Git links and centered “In Code We Trust.” footer. This is a desktop homepage review, not a complete browser, accessibility or mobile acceptance test.

English and pt-BR READMEs, operation and publication guides describe the exact version, source import, token prompts, separate Git publication/VPS deployment and remaining limits. The hosted release notes contain both languages.

## Publication and deployment limits

The four-host publisher was exercised with offline API fixtures and real local Git repositories. No tokens were supplied in this environment; no authenticated external push, hosted release creation or asset upload was performed. Each host requires its own repository-write token when the operator runs the included fish launcher. Conflicting remote history/tags/assets stop that host without force-pushing or replacing files. Known signed GitHub downloads receive no Authorization header; an unknown external Forgejo storage redirect leaves verification pending.

No real Docker build, PHP 8.4/ImageMagick 7 container test, VPS cutover/rollback, vulnerability database scan, image scrolling/animation in a real browser, or mobile/assistive-technology audit was performed. Local runtime tests used PHP 8.3/ImageMagick 6. The included deploy updater retains the existing candidate gates, SSH port 5119, root@securityops.co and private binding 172.17.0.1:5140→80. It performs the container checks on the VPS when executed.

Google and Brave can still both fail or rate-limit. A fallback is labeled; false success is never returned. Redlib, Binternet and Invidious are external services. Historical Redlib availability checks returned 503; no new live provider availability claim is made here.

## Resumo em português do Brasil

A v0.9.19 incorpora o fonte completo ao histórico publicado, mantém a remoção de `lain.gifv`, atualiza o asset para 23 e entrega tag anotada, pacote completo, checksums, bundle Git e publicador para os quatro hosts. READMEs e guias de operação/publicação têm versões em português; as notas do release são bilíngues.

Passaram a suíte completa de regressão, a sintaxe dos 111 arquivos PHP e os testes de publicação/importação sem rede. O teste HTTP local teve 50/50 respostas corretas, mediana de 2,04 ms e p95 de 5,11 ms. A captura real da página inicial mostra o Tron da instância com asset 22, como informado nas legendas. Isso não comprova desempenho no VPS, disponibilidade dos provedores ou funcionamento de todos os recursos em dispositivos reais.

Os releases remotos e o deploy ainda dependem da execução local dos scripts com suas credenciais. O importador preserva o histórico e exige fast-forward; conflitos são informados sem sobrescrever tags ou arquivos existentes.
