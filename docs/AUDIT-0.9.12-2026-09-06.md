# v0.9.12 engineering audit — 6 September 2026

[Português abaixo](#português) · [Operating guide](OPERATIONS-0.9.12.md)

This is an implementation and regression audit of the changed paths, not a
penetration-test certificate or proof that every upstream provider always works.
Independent review covered provider routing/transport and actual browser output.

## Evidence

- Four network-free PHP suites passed: theme/layout/input/token regressions;
  Google URL/transport/cooldown/deadline invariants; strict proxy parsing and
  ambient routing overrides; five API exception contracts and eight isolated
  missing-key provider-selection cases. All 94 PHP files and JS syntax passed.
- Twenty-seven browser fixture checks passed with scripts disabled: animated
  desktop/mobile Lain, reduced motion, native keyboard still control, and six
  image layouts at 320, 390, 768 and 1440 pixels. No page/card overflow occurred;
  Filmstrip intentionally scrolls internally and keyboard focus reaches its end.
  Paired normal-motion frames differed; reduced-motion frames were identical and
  did not request the GIF. Bundled layout fixtures are not claimed as live results.
- Public HTTPS browser validation after cutover also passed with JavaScript
  disabled: desktop and true mobile emulation loaded and animated Lain without
  overflow or browser errors. Actual Google image Grid and mobile Filmstrip each
  rendered 20 results with loaded same-origin thumbnails and correct priority,
  lazy-loading and intrinsic size hints. Filmstrip omitted infinite pagination.
- Private IONOS pools were validated without displaying their contents: Google
  and CSE each had three valid SOCKS5h entries; Brave had three SOCKS5h entries
  plus one already-configured explicit direct entry. No routing policy was weakened.
- The candidate returned 20 actual Google web results in 2,296 ms and 20 image
  results in 2,177 ms. The final hop reported zero new connections, confirming
  connection reuse. A prior web sample returned 20 results in 6,154 ms; other
  baseline requests were challenged. These are point-in-time observations with
  different network/cache conditions, not a controlled speedup benchmark.
- Candidate and promoted-container HTTP checks passed: home/theme, exact original
  GIF SHA-256 and image/gif MIME, static cache policy, 503/no-store/Retry-After for
  missing Google API, 404 for loopback/file image-proxy probes, and 403 for private
  data and test-router paths. Apache denies the entire tests directory.
- Release packaging caught a stale requirement for the deliberately removed
  `secops.gif`. The requirement and its unused Lain decoration were removed,
  never restoring that artwork. A regression checks Lain's referenced assets;
  the final cache version was bumped from 15 to 16 for existing visitors.
- Production is `security-search:v0.9.12-r2-20260906`, with asset version 16. The
  previous container, private mounts, both networks and icon volume were retained.
  Health checks and public HTTPS home, GIF MIME/cache and provider-error behavior
  were verified after cutover.

## Separate status incidents

The two student SFTPGo aliases had real public 502 errors while the LAN service
was healthy. Nextcloud had the same failing forwarding group. Restricted new
WireGuard relays restored their public login responses; existing tunnels were
preserved. Student admin paths remain unavailable publicly. A separate observer
update handles probe failures transparently; historical outage samples are not
rewritten just because the services recovered.

## Limits

Google/Brave shared exits may still receive challenges. There is no automatic
provider substitution or CAPTCHA solving. A used pagination token can require
a fresh-search restart after an upstream failure. The original Lain GIF is about
8.7 MiB uncached; reduced motion/data or the native still control avoid animation.
Image sources may fail or exceed safety limits. Status snapshots cannot prove
video playback, successful authentication, payment or continuous uptime.

## Português

A auditoria cobre alterações, regressões e testes reais descritos acima; não é
certificação nem promessa de ausência total de falhas. Quatro suítes PHP e 27
verificações visuais sem JavaScript passaram. Lain animou no desktop/celular;
movimento reduzido não baixou o GIF. Seis layouts passaram em quatro larguras.

O candidato trouxe 20 resultados web em 2,30 s e 20 imagens em 2,18 s, reutilizando
a conexão. São medições pontuais, não benchmark controlado nem garantia. Os
proxies privados foram validados sem revelar valores; saídas Tor foram mantidas.
Os 502 de cursos/Cloud eram falhas reais do encaminhamento e foram corrigidos
com novos relays restritos, sem substituir túneis existentes nem expor o admin.
Histórico de incidentes permanece verdadeiro; bloqueios externos e tokens
consumidos continuam tendo tratamento e limites documentados.
