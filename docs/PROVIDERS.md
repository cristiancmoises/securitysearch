# Search providers

Security Search v0.9.5 keeps **Google** as the configured default for web and
image searches. Users can select another provider for one request with the
**Scraper** filter or save a preference in **Settings**. Brave is selectable for
both web and image search; availability still depends on Brave accepting the
instance's current outbound address.

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

## Google default

The visible `google` provider delegates to the bundled Google Programmable
Search compatibility transport in `scraper/google_cse.php`. It does not require
the separate 4play/Firefox renderer used by upstream 4get's newer direct Google
scraper.

The transport caches validated CSE bootstrap parameters for 90 seconds per
backend, CX, and outbound egress. It never stores a query or result document in
that cache. Nearby searches therefore skip the CSE HTML and loader-script
requests, removing two sequential upstream round trips and lowering request
volume. If a cached token receives a recognized token rejection, the scraper
deletes it, bootstraps once, and retries the result request exactly once.
Unusual-traffic and CAPTCHA responses never trigger that refresh.

On a cold cache key, an APCu single-flight lock allows only one request to make
the two bootstrap calls. Other requests poll the same generation for up to six
seconds and immediately consume the winner's parameters when published. The
15-second lock self-expires so a dead owner cannot strand the key; a bootstrap
failure is retained for five seconds so concurrent waiters do not repeat an
anti-abuse or upstream failure. These internal entries contain no query or
result data. Installations without APCu keep the uncached bootstrap behavior
instead of failing closed.

When Google returns a continuation, the existing backend stores the next-page
request and outbound-proxy selection in an APCu entry whose request body is
compressed and protected with authenticated encryption. The browser receives
the opaque NPT reference and key used by the existing 4get pagination
mechanism. If a continuation request fails specifically because its CSE token
expired or was rejected, the scraper invalidates the short cache, obtains one
fresh bootstrap token, and retries that continuation exactly once. It does not
loop.

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
FOURGET_DEFAULT_SCRAPER_WEB: google
FOURGET_DEFAULT_SCRAPER_IMAGES: google
```

To replace the Programmable Search identifier, set
`FOURGET_GOOGLE_CX_ENDPOINT` in a private Compose override or host environment.
Do not commit secrets.

### Optional Google API provider

`google_api` remains an opt-in provider for operators who already have Google
Custom Search JSON API credentials. It reads keys from
`data/api_keys/google_api.txt`, which is excluded from source releases. The
tracked production configuration contains zero Google API keys, and the v0.9.5
packaging rules exclude that directory. Selecting `google_api` without privately
provisioning a key produces a configuration error. It is not an automatic
fallback for `google`.

Google's [official API overview](https://developers.google.com/custom-search/v1/overview)
says Custom Search JSON API is closed to new customers and that existing
customers must transition to an alternative by January 1, 2027. Do not present
this opt-in path as a new-customer availability solution.

## Brave

Brave is the primary user-selectable alternative for web and image search. The
parser recognizes upstream CAPTCHA, proof-of-work, regional, and range-ban
responses. When Brave intermittently schedules its proof-of-work page, the
scraper makes at most three attempts against Brave itself before reporting the
provider failure. It does not solve the challenge, change providers, or loop
indefinitely. Successful first attempts have no retry overhead. It uses the
instance's direct egress by default. Operators can set `FOURGET_PROXY_BRAVE` to
the name of a privately configured proxy pool.

Datacenter addresses can receive a Brave proof-of-work or CAPTCHA response.
That condition is presented through the same neutral provider-unavailable view,
not as a PHP crash. The existence of the picker or **Try Brave** link does not
guarantee that Brave will accept a particular VPS address.

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

The grid renders the ordinary proxied thumbnail as a lazy poster first. A
motion hint from either result URL selects the provider's full-size original
for validation and playback. Ordinary `.gif`, `.webp`, and
`.apng` URLs are candidates; an explicit provider format filter selects the
full-size original even when its signed URL is extensionless. Static WebP is
rejected by multi-frame validation and returns to its poster. Inline data URLs
are excluded. Near the viewport, the
motion controller requests the chosen source through the same-origin
`/proxy?...&s=animated` endpoint.

That endpoint caps the decompressed response body at 20 MB in cURL's write
callback, cumulatively across validated redirect hops and within one 30-second
deadline. Progress and declared content length can reject a response early, but
they are not the authoritative byte bound. The proxy validates the returned
MIME against the GIF/WebP/PNG raster allowlist and requires at least two frames.

GIF/WebP uses Imagick frame counting. Its ping runs with temporary ImageMagick
limits of 64 MiB memory, 64 MiB map, no disk, eight files, one thread, ten
seconds, 16,384 pixels per dimension, and 1,000 list entries; the prior limits
are restored after success or failure. PNG/APNG instead requires valid PNG
chunk CRCs and ordering, consistent `acTL`/`fcTL`/`fdAT` sequence and frame
counts, frame data, and frame bounds within the canvas, with at most 8,192
chunks and bounded-slice CRC work. A forged static PNG with
only an `acTL` chunk is rejected. This direct parser is required because Alpine
Imagick can expose a known APNG as one frame.

The endpoint forwards a verified payload unchanged; invalid, static,
oversized, or failed candidates fall back to the poster. SVG, video, gifv, and
data URLs are not eligible. The browser never contacts the result host
directly, although a verified full-size animation can use more instance
bandwidth than the thumbnail.

The production image adds two application admission bounds around this costly
path: at most 30 animated-candidate requests per minute for each client address
seen by the app, and at most three generation-tagged validations in flight
globally. A candidate is also rejected above 1,000 frames, 16,384 pixels on
either dimension, 40 megapixels per frame, 250 million decoded pixel-frames, or
20 MB. Valid responses from clean queryless/non-private upstream URLs are
browser/shared-cacheable for five minutes; query-bearing, credential-bearing,
or privacy-sensitive upstream URLs are browser-private for two minutes.
Upstream `no-store`/`no-cache`, failures, and 429 responses remain `no-store`.
At the public edge, the Nginx Proxy Manager `/proxy`
location uses the `media` zone at 60 requests/minute per client with
`burst=12`; a second `proxy` zone applies to every proxy request at 600/minute
with `burst=40`. A global NPM `map` leaves the media key empty by default and
sets it to the client address only for `s=animated`, so ordinary thumbnails do
not consume this budget. The map and zone must exist in NPM's global `http{}`
configuration; the checked-in location directive alone cannot create them.
The application evaluates PHP's decoded parameter and remains authoritative if
raw query encoding misses the conditional edge map. Port 5140 binds to loopback
by default, or to an explicitly configured private Docker-host bridge for NPM;
it must never be public.

Automatic motion is viewport-scoped and limited to three cards on desktop or two
on coarse-pointer devices. Automatic and deliberate activation both request the
poster and wait for its load/error state. A successfully loaded or already
complete valid poster gets two animation-frame boundaries and one paint before
motion; a broken poster is only settled and may proceed without a paint
guarantee. User intent upgrades pending automatic preparation instead of
duplicating it. Pointer/focus activation requires the actual image wrapper to
be in the viewport, uses the same limit, and evicts the oldest active card.
Off-screen cards cancel pending work and restore their posters; appended
infinite-scroll cards are observed.
Reduced-motion disables animation and data-saver disables automatic activation.
Because discovery uses URL/filter hints before the proxy fetch, an extensionless
animation can remain a static poster unless the selected filter identifies it.
Explicit APNG/animated-PNG filename hints are recognized even with a `.png`
extension, but an APNG with only an ordinary `.png` name can still be missed.
Ordinary WebP is probed; static candidates are rejected by frame validation.
Other
raster formats are not motion candidates. An automatic failure is not retried
automatically; pointer/focus can make one deliberate retry for that card, and a
second failure leaves its poster in place. The motion badge appears only after
validation, and its format text is a candidate hint rather than an authoritative
MIME result.

## Operations

Before promotion, test Google and Brave separately for web, images, and their
API paths. Use a browser-like User-Agent for command-line HTML `/web` and
`/images` checks so header bot protection does not replace the page with its
block response; API checks do not need that header. Assert real result items
rather than only HTTP status. Also exercise Google's recognized unusual-traffic
response, the explicit **Try Brave** action, first-page and continuation token
failures, bounded animated-grid media, and container logs. Never report a
fallback result as a successful Google response. See [RELEASE.md](RELEASE.md).
