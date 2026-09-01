# Search providers

Security Search v0.9.7 keeps **Google** as the configured default for web and
image searches. Users can select another provider for one request with the
**Scraper** filter or save a preference in **Settings**. Brave is selectable for
both web and image search; availability still depends on Brave accepting the
instance's current outbound address.

“Configured default” describes deterministic provider selection, not an uptime
promise. Google and Brave can both challenge a shared or datacenter egress
address. DuckDuckGo and Yandex remain explicit alternatives, but no provider is
silently substituted and every path must be tested from the production network.

## Provider precedence

Provider selection is deterministic:

1. A valid `scraper` query parameter selects the provider for that request.
2. Otherwise, a valid saved `scraper_web` or `scraper_images` cookie applies.
3. Otherwise, `config::DEFAULT_SCRAPER_WEB` or
   `config::DEFAULT_SCRAPER_IMAGES` applies. Both production defaults are
   `google`.

Security Search does **not** silently send a failed Google query to another
provider. If Google is unavailable, the results page explains the upstream
failure and offers **Retry search**, **Provider settings**, and an explicit
**Try Brave** link for web and image searches. Choosing that link is the action
that sends the query to Brave.

## NSFW filter default

The application sets `config::DEFAULT_NSFW=yes`, and the production container
sets `FOURGET_DEFAULT_NSFW=yes`. Providers that expose the common NSFW filter
therefore receive the allow-NSFW value by default. Selection follows this
precedence:

1. An explicit valid `nsfw` request value.
2. A valid `nsfw` cookie saved through Settings.
3. `config::DEFAULT_NSFW` (`yes` in the tracked source and production Compose).

Users can save `maybe` or `no` without changing server configuration. Upstream
providers map these values to their own safe-search controls, so exact filtering
and result coverage remain provider-specific.

## Google default

The visible `google` provider delegates to the bundled Google Programmable
Search compatibility transport in `scraper/google_cse.php`. It does not require
the separate 4play/Firefox renderer used by upstream 4get's newer direct Google
scraper.

The transport caches validated CSE bootstrap parameters for 300 seconds per
backend, CX, and outbound egress. It never stores a query or result document in
that cache. Nearby searches therefore skip the CSE HTML and loader-script
requests, removing two sequential upstream round trips and lowering request
volume. If a cached token receives a recognized token rejection, the scraper
deletes it, bootstraps once, and retries the result request exactly once.
Unusual-traffic and CAPTCHA responses never trigger that refresh.

On a cold cache key, an APCu single-flight lock allows only one request to make
the two bootstrap calls. Other requests poll the same generation for up to six
seconds and immediately consume the winner's parameters when published. A
waiter that sees neither a published result nor a cached failure in that window
fails fast instead of duplicating the upstream bootstrap. The owner lock
self-expires after 60 seconds so a dead owner cannot strand the key. An ordinary
bootstrap failure is shared for five seconds; a recognized anti-abuse failure is
shared for 30 seconds so concurrent requests do not hammer Google. These
internal entries contain no query or result data. Installations without APCu
keep the uncached bootstrap behavior instead of failing closed.

The same egress-scoped 30-second anti-abuse cooldown also wraps requests to
`https://cse.google.com/cse/element/v1`. A recognized challenge in a decoded
error, retry response, or thrown transfer/parse error starts the cooldown; a
nearby result request fails locally with the neutral provider message instead
of immediately querying the throttled endpoint again. It is not a result cache
and does not bypass the provider.

When Google returns a continuation, the existing backend stores the next-page
request and outbound-proxy selection in an APCu entry whose request body is
compressed and protected with authenticated encryption. The browser receives
the opaque NPT reference and key used by the existing 4get pagination
mechanism. If a continuation request fails specifically because its CSE token
expired or was rejected, the scraper invalidates the short cache, obtains one
fresh bootstrap token, and retries that continuation exactly once. It does not
loop.

For image search, returned result cards are parsed before the scraper decides
whether a following cursor exists. A final response can mark its total exact and
still contain usable images; v0.9.7 preserves those cards while correctly
omitting a next-page token. Each record validates remote URLs and dimensions;
if `tbLargeUrl` is missing or invalid, the parser tries a valid `tbUrl` and uses
the matching thumbnail dimensions. A missing original may fall back to that
validated thumbnail, while a record with no usable source is discarded.

An unusual-traffic, automated-traffic, IP-rate-limit, or CAPTCHA response is a
different failure class. The scraper does not retry it as a token failure and
does not attempt to bypass the provider's anti-abuse control. It reports that
Google temporarily rate-limited the instance and lets the user retry later or
explicitly choose another provider. Google documents that automated services,
scrapers, VPNs, and shared networks can trigger this network-level response in
[Resolve Google Search's unusual-traffic message](https://support.google.com/websearch/answer/86640?hl=en).

### Configuration

The Docker image generates `data/config.php` from `FOURGET_*` variables. The
production defaults are explicit in `docker-compose.yml`:

```yaml
- FOURGET_DEFAULT_SCRAPER_WEB=google
- FOURGET_DEFAULT_SCRAPER_IMAGES=google
- FOURGET_DEFAULT_NSFW=yes
```

To replace the Programmable Search identifier, set
`FOURGET_GOOGLE_CX_ENDPOINT` in a private Compose override or host environment.
Google uses direct egress unless `FOURGET_PROXY_GOOGLE=<pool-name>` names a
private proxy pool. For a container deployment, keep the reviewed
`data/proxies/<pool-name>.txt` file only on the host and enable the optional
read-only `./data/proxies:/var/www/html/4get/data/proxies:ro` Compose mount.
Do not commit secrets or include a private pool in a release archive.

### Optional Google API provider

`google_api` remains an opt-in provider for operators who already have Google
Custom Search JSON API credentials. It reads keys from
`data/api_keys/google_api.txt`, which is excluded from source releases. The
tracked production configuration contains zero Google API keys, and the v0.9.7
packaging rules exclude that directory. Selecting `google_api` without privately
provisioning a key produces a configuration error. It is not an automatic
fallback for `google`.

Docker build context also excludes the entire `data/api_keys/` directory so a
locally provisioned key cannot be baked into an image layer. Container operators
who intentionally enable `google_api` must uncomment the optional read-only
`./data/api_keys:/var/www/html/4get/data/api_keys:ro` Compose mount and protect
the host file. The default `google` provider does not use this key directory.

Google's [official API overview](https://developers.google.com/custom-search/v1/overview)
says Custom Search JSON API is closed to new customers and that existing
customers must transition to an alternative by January 1, 2027. Do not present
this opt-in path as a new-customer availability solution.

## Brave

Brave is the primary user-selectable alternative for web and image search. The
parser recognizes upstream CAPTCHA, proof-of-work, regional, and range-ban
responses. When direct egress receives a recognized proof-of-work page, the
scraper reports the provider failure after that first attempt; repeating the
same datacenter address would only add latency. When `FOURGET_PROXY_BRAVE` names
a configured proxy pool, the scraper may rotate to another pool address for up
to three total, bounded Brave attempts. It does not solve the challenge, change
providers, or loop indefinitely. Successful first attempts have no retry
overhead.

For the supported container, store a private `<pool-name>.txt` under
`./data/proxies`, uncomment the optional read-only
`./data/proxies:/var/www/html/4get/data/proxies:ro` Compose mount, and set
`FOURGET_PROXY_BRAVE=<pool-name>`. Keep credentials out of Git and release
artifacts and restrict the host file's permissions.

Datacenter addresses can receive a Brave proof-of-work or CAPTCHA response.
That condition is presented through the same neutral provider-unavailable view,
not as a PHP crash. The existence of the picker or **Try Brave** link does not
guarantee that Brave will accept a particular VPS address.

For image results, Brave can expose the original URL, an animation-preserving
`properties.resized` URL, and a static thumbnail. The parser validates those
URLs, infers GIF/WebP/APNG from `properties.format` or a URL-path extension, and
retains the resized source. When it is an animation candidate, the grid prefers
that smaller Brave-proxied source and falls back to the original instead of
forcing every preview through a larger or hotlink-blocked origin.

## Upstream timeouts

Google CSE and Brave use a 10-second connection timeout and a 20-second total
timeout for each upstream cURL transfer. These bounds reduce stalls from an
unreachable route; a search can still contain more than one bounded transfer,
such as Google's cold bootstrap or a Brave proxy-pool rotation.

## API selection

API clients choose providers explicitly with `scraper`:

```text
/api/v1/web?s=security+news&scraper=google
/api/v1/images?s=privacy&scraper=brave
```

Without `scraper`, the API uses the same saved-cookie/configured-default
precedence as the browser UI. API requests do not silently switch providers.
Success must be verified from the JSON payload—`status` must be `ok` and the
relevant result array must contain data. HTTP 200 alone is insufficient because
provider errors are represented in the response body.

## Image pagination

Image results include a server-rendered **Next page** link whenever the selected
provider returns a continuation. Automatic loading is a progressive enhancement
enabled by default:

- No `image_infinite` cookie, or `image_infinite=yes`, enables automatic loading
  in browsers with `IntersectionObserver` and `fetch`.
- **Settings → Load more image results automatically while scrolling → No**
  saves `image_infinite=no` and omits the enhancement.
- Browsers without the required APIs use the ordinary link.
- A failed automatic request stops further automatic attempts and replaces the
  consumed continuation with a first-page restart that preserves the query and
  filters.

### Animated image results

The grid renders the ordinary proxied thumbnail as a lazy poster first. Invalid
or duplicate provider source entries are removed before markup is generated. If
the poster fails, an early deferred controller tries up to two other proxied
sources and then shows a local **Image unavailable** state instead of a broken
image icon.

A motion hint from a result URL, encoded format parameter, bounded GitHub Camo
source, or supported provider MIME/format metadata selects a preferred motion
source. Google CSE consumes `mime`/`fileFormat`. Brave consumes
`properties.format`, also infers a format from original/resized URL paths, and
can prefer its smaller animation-preserving `properties.resized` URL with the
original as fallback. Ordinary `.gif`, `.webp`, and `.apng` URLs are candidates;
an explicit format filter can identify an extensionless source. Static WebP is
rejected by multi-frame validation. WebP is deliberately a low-confidence hint:
it is still validated and can try the provider motion fallback, but it does not
receive an automatic cache-busted retry after failure. User-initiated work has
highest queue priority; among automatic work, GIF and APNG are queued before
WebP. Inline data URLs are excluded.

The motion controller is declared early and begins discovery within 700 px of
the viewport, before a large poster grid can delay it. It requests the selected
source through the same-origin `/proxy?...&s=animated` endpoint. The browser
never contacts the result host directly.

That endpoint caps decompressed output at 32 MiB in cURL's write callback,
cumulatively across validated redirects and within one 30-second request budget.
Progress and declared content length can reject a response early, but are not
the authoritative byte bound. An animation-specific `Accept` value requests
GIF, APNG/PNG, and WebP and is retained across redirects. Returned MIME must be
GIF, WebP, APNG, or PNG, and at least two frames must pass structural validation.

GIF inspection validates the signature, logical canvas, color tables, image
descriptors, sub-block termination, frame bounds, and trailer. WebP inspection
validates RIFF length/padding, `VP8X` animation flags, `ANIM`/`ANMF` structure,
frame bounds, and nested VP8/VP8L payload dimensions. APNG requires valid PNG
chunk CRCs/order, consistent `acTL`/`fcTL`/`fdAT` sequence and frame counts,
frame data, and canvas bounds. These parsers are bounded and do not decode the
animation through ImageMagick. A forged static PNG carrying only `acTL`, a
static WebP, malformed input, and unsupported raster types are rejected.

A candidate is rejected above 1,000 frames, 16,384 pixels on either dimension,
40 megapixels per frame, 250 million pixel-frames, 8,192 container chunks, or
32 MiB. GIF sub-block traversal has its own bound. Valid responses from clean,
queryless, non-private upstream URLs are browser/shared-cacheable for five
minutes; query-bearing or privacy-sensitive URLs are browser-private for two
minutes. Upstream `no-store`/`no-cache`, failures, and 429 responses remain
`no-store`.

The application admits at most 900 animated candidates per client address per
minute. Three generation-tagged slots perform expensive fetch/validation work;
up to nine requests can wait for a slot for at most three seconds. A request
rejected solely because that queue is full or expires is not charged against the
client quota, and busy responses advertise `Retry-After: 2`. These bounds limit
work, not playback.

The browser runs at most three validation loads on desktop or two on
coarse-pointer/mobile devices. A failed preferred source first tries the
provider motion fallback. An eligible non-WebP candidate (normally GIF/APNG)
then makes one delayed cache-busting retry after roughly 2.2–3.0 seconds,
honoring the busy response's two-second retry interval; low-confidence WebP
skips that retry. A final automatic failure retains the best available poster;
pointer/focus can still express deliberate intent where a source remains.

Successfully loaded candidates use a soft least-recently-used retention budget
of 36 on desktop or 18 on coarse-pointer/mobile devices. Trimming never cancels
an in-flight request and never evicts a candidate within the observer margin, so
the budget may be exceeded while work is visible or loading. When trimming is
possible, the oldest settled off-screen candidate returns to its poster. Its
motion metadata and observer remain registered, so it is prepared and activated
automatically when it comes back. Infinite-scroll cards are observed
automatically. Reduced-motion disables animation and data-saver disables
automatic activation.

For ordinary `s=thumb` requests, the proxy caps the upstream body at 16 MiB. It
directly relays a JPEG only at no more than 128 KiB and 512 pixels per axis, or
a structurally validated animated GIF/WebP/APNG when the body is at most 1.5
MiB, each axis is at most 2,048 pixels, and the image is at most 4 MP. This
avoids needless ImageMagick conversion without turning original-image fallbacks
into oversized thumbnails, and preserves small native animations. Static or
malformed animation-capable formats, AVIF, and larger inputs continue through
the bounded ImageMagick resize path. SVG, video, gifv, and data URLs are never
motion candidates.

Before ImageMagick sees fallback input, the proxy accepts only the detected MIME
types JPEG, PNG, GIF, WebP, or AVIF and rejects an early header dimension above
16,384 pixels per side or 40 MP. Per-request ImageMagick limits allow one decoded
frame—the `list-length` threshold is `2`, which rejects the second frame—plus
64 MiB of memory, 64 MiB of map, no disk-backed pixel cache, one thread, ten
seconds, and the same dimension/area envelope. Previous process limits are
restored afterward. The container policy denies all delegates and filters,
indirect `@` paths, and all coders by default, then enables only its narrow
raster coder set. The proxy MIME allowlist remains authoritative even though
the policy includes the underlying HEIC coder among that set. The production
image installs the ImageMagick HEIC module so allowed AVIF input can be decoded;
HEIC MIME itself is still outside the proxy allowlist.

An animated GIF above the 1.5 MiB passthrough ceiling therefore enters this
single-frame poster path and may fail within its resource limits. A broken
poster is not a motion veto: the early controllers still start the separate
`s=animated` request automatically, without a click, and that endpoint retains
its independent 32 MiB ceiling. When no reviewed provider-specific Referer is
supplied, buffered and streamed image requests derive one from the already
validated public source URL (scheme, host/port, and containing path). Both
derived and explicit values are length/CRLF checked; invalid values are omitted,
redirects remain independently SSRF-validated, and arbitrary browser Referer
input is not trusted.

Nginx Proxy Manager limits and caching are optional edge controls, not facts
about a deployment merely because the sample file exists. The checked-in sample
aligns its optional animated-media zone with the 900/minute application budget
and keeps the general proxy limit for non-animated thumbnails/originals. Its
global key maps place `s=animated` only in the 900/minute `media` zone, while all
other `/proxy` traffic remains in the 600/minute `proxy` zone; the limits are not
stacked on an animated request. Its cache becomes active only if the global
`secsearch` cache zone exists and `proxy_cache secsearch` is deliberately
enabled. Verify the effective live configuration with `nginx -T`. Port 5140
binds to loopback by default, or to an explicitly configured private Docker-host
bridge for NPM; it must never be public.

## Operations

Before promotion, test Google, Brave, DuckDuckGo, and Yandex separately for web,
images, and their API paths. Use a browser-like User-Agent for command-line HTML
`/web` and `/images` checks so header bot protection does not replace the page
with its block response; API checks do not need that header. Assert real result
items rather than only HTTP status. Also exercise Google's recognized
unusual-traffic response, the explicit **Try Brave** action, first-page and
continuation-token failures, a final image page that contains results but no
continuation, Brave's resized-motion/original fallback, bounded animated-grid
media, and container logs. Fixtures should also cover the 60-second bootstrap
owner lease, six-second
waiter fail-fast path, result-endpoint cooldown, invalid `tbLargeUrl` to valid
`tbUrl` fallback, WebP retry suppression, GIF/APNG queue priority, soft 36/18
retention and return reactivation, poster-independent motion, and the bounded
ImageMagick/policy/Referer paths. Provider success is specific to the tested
production egress and time; never report a fallback result as a successful
Google or Brave response. See
[RELEASE.md](RELEASE.md).
