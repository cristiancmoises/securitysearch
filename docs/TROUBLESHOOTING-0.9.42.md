# Troubleshooting — v0.9.42

See [operations](OPERATIONS-0.9.42.md), [README](../README.md), [PT-BR](../README.pt-BR.md)
and [SkunkyArt](SKUNKYART.md). The [previous troubleshooting guide](TROUBLESHOOTING-0.9.41.md)
retains guidance for source-tree, SSH, native-runtime and publication failures.

## Search failures

| Symptom | Meaning and safe next check |
|---|---|
| Google returns 429, refusal or a challenge | An upstream restriction, not successful search. Respect the controlled failure and cooldown; a Brave fallback does not approve the Google live gate. |
| Google returns an error inside HTTP 200 | Treat the response as unavailable, not an empty result. Explicit token expiration may renew once; refusals and anti-abuse responses must not trigger repeated renewal. |
| Brave reports an unsupported format | Check the known Svelte data structure with offline fixtures. The decoder never executes JavaScript; unknown syntax, missing results and nonempty unusable image lists fail closed. |
| Results disappear after an image-format filter | A valid parsed list may become empty after filtering. Compare with the same response using any format; do not mistake legitimate filtering for transport failure. |
| `curl_errno=5` | The configured proxy hostname could not resolve. Inspect that exact private proxy entry and DNS path; do not change public adapter code or expose the proxy address. |
| SkunkyArt reverse-proxy failure | Check the exact existing proxy-to-service network and service response. A connected network or healthy landing page alone does not prove search works. |
| Binternet is stopped or has an OCI startup error | Inspect the existing container and retained error before restarting it. Do not replace it or change unrelated runtime settings merely to obtain a health response. |
| Binternet reports cURL 28 and upstream status 0 | The transport timed out before an HTTP status arrived; this does not establish a parser failure, HTTP 403 or rate limiting. Keep the request budget bounded and verify recovery separately, without retry loops or address rotation. |
| Google JSON API is inactive | It requires an operator-supplied key. Do not embed credentials in URLs, examples or logs; this is distinct from the Google CSE integration. |
| Pexels, Unsplash or Pixabay reports missing proxy configuration | Add the corresponding explicit `PROXY_*` setting to the private configuration without replacing other values. Public defaults are `false` (direct); use an existing pool when your routing policy requires it. No implicit route is selected. |
| Archive.org videos or Yep news is absent from the picker | These category integrations are not implemented and are no longer advertised. Yep web remains available. |
| Cara reports an unsupported format | The response or image record has an invalid structure. Preserve the controlled failure; an empty list is accepted only when it is structurally valid. |
| Cara returns upstream HTTP 401 | The upstream rejected unauthenticated access. The current unauthenticated integration is unavailable; a controlled HTTP 503 is not successful search. Do not reuse embedded tokens or bypass authentication. |
| Unsplash returns upstream HTTP 307 | A redirect alone does not prove a canonical search route or an authentication challenge. Do not follow arbitrary destinations or change the fixed route without validating the destination; the observed redirect has not been approved as a replacement endpoint. |
| Search API returns HTTP 503 with a `status` message | A controlled failure, not a result object. `Cache-Control: no-store` and `Retry-After: 30` apply; raw provider messages and PHP internals are not reflected. |
| Docker/runc fails before PHP starts | No application test ran. Preserve the exit and runtime error; inspect disk, inodes and memory. Missing or failed runtime evidence is not a parser failure or a passing audit. |

Keep raw provider captures and effective configuration private. Use bounded existing probes only
when live verification is intended; offline fixtures and replays make no new provider requests.
Never waive a mandatory live gate, retry a challenge automatically or report all providers working
from a mixed result/empty/unavailable matrix. Every deployment requires its own complete 130/0 and the
separate genuine provider checks. See [retention](RETENTION.md) before any cleanup.

The [dated operations evidence](OPERATIONS-0.9.42.md#maintenance-and-evidence--2026-10-03)
separates captured Brave parsing replays from browser searches against Binternet and SkunkyArt.
Neither substitutes for the complete native audit and mandatory live acceptance.

## PT-BR

HTTP 200 pode conter erro externo, e HTTP 503 com `status` é falha controlada, não resultado.
Uma lista vazia legítima ou esvaziada por filtro não deve ser confundida com falha de transporte.
Confira DNS do proxy, rede e estado do serviço existente antes de atribuir indisponibilidade ao
parser. Bloqueios do Google não devem provocar renovação repetida de token nem rotação de endereço.
Falha do runtime antes do PHP significa teste não executado. Preserve somente a evidência necessária
em local privado; não exponha consultas, configuração efetiva, capturas, credenciais ou detalhes
internos e não dispense os gates.
