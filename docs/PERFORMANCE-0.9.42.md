# SecuritySearch v0.9.42

See [README](../README.md), [PT-BR](../README.pt-BR.md), [SkunkyArt](SKUNKYART.md), and
[retention](RETENTION.md) for the full current behavior and complete Fish commands.

Upgrade from the exact clean supported source; preserve Git history and private themes.
Target SSH is root@securityops.co:5119, checkout ~/securitysearch and downloads ~/Downloads.
Normal operation is one native audit followed by live gates, cutover, independent verification and
post-success cleanup. Separate audit-only first is not required. Full candidate acceptance requires
130/0; provider failure never passes. Publish only after DEPLOY COMPLETE using
securitysearch-v0.9.42-publish-four-remotes.fish. No force push or existing tag movement.

## Measurement boundaries

One service API request per explicit SkunkyArt page; zero extra requests on other providers or the
homepage. Only a small inline SVG/native button is added to normal search markup. The previous
bounded image-label rendering, preview selection, PHP-free anonymous homepage and asynchronous
filmstrip geometry are unchanged. The new provider parser caps body bytes, rows and label bytes.

The one-pass deploy removes duplicated operator-side regression execution and the mandatory
operator instruction to perform an audit-only build before a normal build. It does not cache an
unverified audit result or skip the native audit. Native audit still runs once per normal deployment.
The seven-site manual benchmark remains unchanged and does not run automatically. No measured
public TTFB win or general browser speedup is asserted. Read the delivered validation report for
actual local measurements, which are fixture/loopback measurements rather than VPS benchmarks.
