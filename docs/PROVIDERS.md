# Search providers

Security Search v0.9.1 defaults to **Google** for web and image searches.
The provider can be changed per request with the **Scraper** picker, or saved
from **Settings** as a browser preference. Brave is always available in both
web and image search.

## Google default

The visible `google` provider uses the bundled Google Programmable Search
compatibility transport (`google_cse`). It is the reliable default for this
deployment because it does not require a separately managed browser-rendering
machine. The provider inherits the `GOOGLE_CX_ENDPOINT` configured in
`data/config.php`.

This is deliberately different from upstream 4get's newest direct Google
renderer: upstream's direct renderer requires an external 4play/Firefox
service. Do not set an unconfigured headful renderer as the production default.
If you operate a renderer later, introduce it as a separate provider and keep
the current default as a safe fallback.

### Configuration

The Docker image generates `data/config.php` from `FOURGET_*` variables. These
values are already set in `docker-compose.yml`:

```yaml
FOURGET_DEFAULT_SCRAPER_WEB: google
FOURGET_DEFAULT_SCRAPER_IMAGES: google
```

To change the Google programmable-search endpoint, provide
`FOURGET_GOOGLE_CX_ENDPOINT` in a private Compose override or host environment.
Never commit an API key. The optional `google_api` provider reads keys from
`data/api_keys/google_api.txt`, which is intentionally excluded from releases.

## Brave

Brave is the primary alternative for web and image search. The parser includes
the current upstream pagination selector and explicit CAPTCHA, proof-of-work,
and range-ban diagnostics. It runs directly by default; set
`FOURGET_PROXY_BRAVE` if your deployment routes it through a configured proxy
pool. Datacenter IPs may receive a Brave proof-of-work challenge; this is shown
as an actionable provider error instead of a PHP failure.

## API selection

API clients can select providers with `scraper`:

```text
/api/v1/web?s=security+news&scraper=google
/api/v1/images?s=privacy&scraper=brave
```

When no `scraper` is supplied, the API uses the same Google defaults as the
browser UI. Requests with a `scraper` parameter always take precedence over a
saved cookie/default.

## Operations

Search engines change their markup and anti-bot behavior often. Before
promoting an update, test a normal web search and image search with both Google
and Brave, then check the container logs for PHP warnings or upstream CAPTCHA
responses. See [RELEASE.md](RELEASE.md) for the release checklist.
