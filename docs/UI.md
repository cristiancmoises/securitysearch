# Search-first interface

Security Search v0.9.7 keeps the logo and search field as the landing page's
primary visual anchors. Navigation and SecurityOps links remain available
without competing with the search task.

## Landing-page hierarchy

The first page presents:

1. One subordinate Settings utility.
2. The Security Search logo.
3. The primary search field and submit action.
4. One compact hint: Google is the default and Brave is available.
5. Quiet text links to [securitytops.co](https://securitytops.co/) and
   [securityops.com.br](https://securityops.com.br/).

The portal links are not large buttons or promotional cards. Additional
services remain in the low-priority expandable directory and footer.

## SecOps theme behavior

SecOps is the configured first-visit theme. v0.9.4 fixes its application on the
landing page by making home-page colors consume the active theme's CSS tokens
instead of overriding them with a separate set of hard-coded colors. The normal
cascade is:

1. `static/style.css` supplies the base layout.
2. The selected theme stylesheet supplies shared color tokens.
3. Home-page component rules consume those tokens, with safe fallback values.

The SecOps stylesheet remains `static/themes/SecOps.css`. v0.9.4 introduced
asset version 11 to replace stale v10 CSS; v0.9.7 uses `config::VERSION=13` so
the current theme, image controllers, and restored background load through
`?v13`.

On the home page, SecOps uses the genuine tracked
`static/misc/secops.gif` background behind a restrained dark overlay. The CSS
`prefers-reduced-motion: reduce` and `prefers-reduced-data: reduce` paths replace
that image with a static radial background. The logo remains the primary visual
anchor above either treatment.

Theme selection remains a browser preference:

- A syntactically valid saved theme whose stylesheet exists takes precedence.
- The special `Dark` choice continues to use the base stylesheet without a
  separate theme file.
- An absent, malformed, overlong, or nonexistent theme cookie falls back to
  `config::DEFAULT_THEME`, which is `SecOps`.
- Selecting the configured default removes the redundant cookie; it does not
  overwrite other valid saved choices.

Container deployments also set `FOURGET_DEFAULT_THEME=SecOps`. Keep that
environment value, `config::DEFAULT_THEME`, the exact-case filename, and the
Settings option aligned.

## NSFW preference

`config::DEFAULT_NSFW=yes` and `FOURGET_DEFAULT_NSFW=yes` allow NSFW content by
default wherever the selected provider exposes that filter. An explicit
request value overrides a valid saved cookie, and Settings can persist `yes`,
`maybe`, or `no`; the application default applies only when neither override is
present. Provider-specific interpretation is documented in
[PROVIDERS.md](PROVIDERS.md).

## Responsive and accessible behavior

- Keep the logo and search form centered from 320 px through desktop.
- Avoid horizontal overflow and allow the field/action to reflow on narrow
  screens.
- Keep the Settings action and quiet domain links subordinate but reachable.
- Retain visible `:focus-visible` styling and usable touch targets.
- Do not require JavaScript for search, Settings, theme selection, or portal
  navigation.
- Avoid autofocus that opens a mobile keyboard or shifts the page unexpectedly.
- Mark links that open a new context with an appropriate `rel` value.

## Provider errors

All scraper failures use the neutral **Search provider unavailable** view.
Upstream text is escaped before rendering. The view preserves the search and
offers:

- **Retry search** with the same provider and filters;
- **Provider settings**;
- **Try Brave** on web and image paths.

The Brave action is an explicit provider choice. Security Search does not
silently resubmit the query, and the UI must not describe Brave results as
Google results. Google unusual-traffic responses use a concise rate-limit
explanation instead of exposing the long upstream block page. User-facing error
headings and guidance must remain professional and free of profanity.

## Image-result flow

Automatic loading is enabled by default but remains progressive enhancement.
Settings can save `image_infinite=no`. The server-rendered **Next page** link
must continue to work when JavaScript is disabled, the preference is off, or
the required browser APIs are missing. A failed automatic load stops additional
attempts and offers a first-page restart that preserves the query and filters.

An animated GIF, WebP, or APNG candidate starts with its normal lazy provider
thumbnail as a poster. `static/images-fallback.js` is declared early and, on a
poster error, tries up to two alternative proxied provider sources before
showing a local **Image unavailable** state. Its poster reference stays aligned
with `static/images-motion.js`, so a recovered source can proceed to motion
without duplicate requests.

A URL/filter/provider hint selects a preferred motion source. Google normally
uses the original. Brave can prefer its smaller animation-preserving resized URL
and preserve the original as fallback. Ordinary `.gif`, `.webp`, and `.apng`
URLs are probed, including common WebP names; static WebP returns to its poster.
WebP is a low-confidence candidate: it still receives structural validation and
the provider motion fallback, but skips the automatic cache-busted retry after
failure. Explicit user work stays at the front of the queue; automatic GIF/APNG
work is ordered before WebP. Inline data URLs are excluded. The motion controller
is also declared early and discovers candidates within 700 px of the viewport,
then swaps in the selected source through same-origin
`/proxy?...&s=animated`.

The endpoint caps decompressed output at 32 MiB, carries an
animation-specific GIF/APNG/PNG/WebP `Accept` value through redirects, validates
the returned raster MIME, and requires at least two structural frames before
forwarding the original bytes unchanged. Bounded format-specific parsers inspect
GIF blocks/frame bounds, animated WebP RIFF/`VP8X`/`ANIM`/`ANMF` structure, and
APNG CRC/order/`acTL`/`fcTL`/`fdAT` sequence, data, count, and canvas bounds.
Animation validation does not decode through ImageMagick. It also bounds
frames, dimensions, pixel-frames, container chunks, and GIF sub-block traversal.

The browser queues candidates and permits at most three validation loads at
once on desktop or two on coarse-pointer/mobile devices. The server separately
permits three expensive validations, nine bounded waiters for up to three
seconds, and 900 admitted candidates per client/minute. A busy-only rejection
does not consume that quota and advertises a two-second retry interval.

A failed preferred motion source first tries the provider fallback. An eligible
non-WebP candidate (normally GIF/APNG) then makes one delayed automatic
cache-busted retry after roughly 2.2–3.0 seconds, after the server's two-second
retry interval; low-confidence WebP skips it. A final failure retains the best
usable poster. That is a loading bound, not a playback cap. Completed candidates
use a soft LRU retention budget of 36 on desktop or 18 on
coarse-pointer/mobile devices. The
controller never interrupts an in-flight load or evicts a candidate inside the
observer margin, so the budget can be exceeded temporarily. It restores only
the oldest settled off-screen item to its poster, then automatically prepares
and activates it when it returns. A mutation observer registers infinite-scroll
additions. Reduced-motion disables motion entirely; data-saver disables
automatic activation. Providers without a usable URL/filter/metadata hint can
still leave an extensionless animation or ordinary `.png` APNG as a static
poster. SVG, video, gifv, data URLs, and raster formats outside the
GIF/WebP/APNG allowlist are not motion candidates.

For normal thumbnails, only a valid JPEG no larger than 128 KiB and 512 pixels
per axis, or a structurally validated animated GIF/WebP/APNG no larger than 1.5
MiB, 2,048 pixels per axis, and 4 MP can bypass ImageMagick; the upstream body is
capped at 16 MiB. This fast path reduces CPU without allowing large originals to
act as thumbnails, and preserves small native animations. Static or malformed
animation-capable formats, AVIF, and larger sources keep the bounded ImageMagick
resize path.

The ImageMagick fallback first enforces a detected-MIME allowlist of JPEG, PNG,
GIF, WebP, and AVIF. It is limited to one frame, 16,384 pixels per axis, 40 MP,
64 MiB each of memory and map, no disk-backed pixel cache, one thread, and ten
seconds; previous process limits are restored afterward. The container policy
denies delegates, filters, indirect `@` paths, and all coders by default before
enabling its narrow raster set. An animated GIF above 1.5 MiB may therefore fail
as a poster, but the poster error still allows automatic, click-free motion
activation through the separate 32 MiB `s=animated` path. Image requests derive
a bounded Referer from the already validated public source URL when no reviewed
provider-specific value is supplied. Derived and explicit values are
length/CRLF checked, and redirect targets remain SSRF-validated.

The motion badge becomes visible only after a candidate passes multi-frame
validation and loads. Its label reflects the URL, filter, or provider candidate
hint; it is not an authoritative MIME report.

## Release checks

Before promotion:

1. Fetch the home page without a theme cookie and verify it links to
   `/static/themes/SecOps.css?v13`; verify `static/misc/secops.gif` loads for the
   normal SecOps home and the static fallback applies under reduced motion/data.
2. Verify that stylesheet returns HTTP 200 with a CSS content type.
3. Test no cookie, an invalid cookie, `theme=Dark`, and a valid nondefault
   theme on the home, results, and Settings pages.
4. Inspect computed colors or screenshots—not only the stylesheet link—at
   representative phone, tablet, and desktop sizes.
5. Exercise keyboard traversal, visible focus, contrast, and no-JavaScript
   search/settings behavior.
6. Force a scraper failure and verify the neutral message, escaped upstream
   detail, same-query retry, Settings action, and explicit Brave URL.
7. Verify Google, Brave, DuckDuckGo, and Yandex separately with real result
   cards/API arrays. HTTP 200 by itself is not evidence of a working scraper.
   Give command-line HTML `/web` and `/images` requests a browser-like
   User-Agent; API requests do not need one. Record anti-abuse results as
   upstream limitations, not scraper success.
8. Test image automatic loading, its opt-out, the ordinary pagination link, and
   the default `nsfw=yes` plus saved/request `maybe` and `no` overrides.
9. Include verified animated, static, malformed, and unavailable fixtures.
   Confirm early controller placement, poster-first loading, both poster
   fallbacks, the local unavailable state, the same-origin motion URL, Brave
   resized-motion/original fallback, eligible non-WebP retry with
   2.2–3.0-second delay, WebP retry suppression and fallback, queue priority,
   truthful post-validation badge visibility, infinite-append registration,
   and no direct result-host image request.
10. Verify the 32 MiB decompressed ceiling and structural GIF/WebP/APNG parsers:
    malformed/truncated blocks, bad RIFF length or padding, static WebP, invalid
    PNG CRC/order/sequence/count/data, forged-acTL-only PNG, frame/canvas bounds,
    frame/dimension/pixel-frame limits, chunk/sub-block limits, and rejection
    outside the GIF/WebP/PNG allowlist. Confirm three/two browser load queues,
    three server workers plus nine bounded waiters, 900 admissions/client/minute,
    uncharged busy rejection with a two-second retry hint, reduced-motion/data
    saver behavior, soft 36/18 LRU retention without cancelling in-flight or
    near-viewport work, and automatic reactivation after an evicted item returns.
11. Exercise the thumbnail passthrough boundaries: accepted JPEG at no more than
    128 KiB and 512 px/axis, or structurally validated animated GIF/WebP/APNG at
    no more than 1.5 MiB, 2,048 px/axis, and 4 MP; rejection to the ImageMagick
    resize path above each bound; 16 MiB upstream cap; native animation
    preservation; animated GIF above 1.5 MiB continuing automatically through
    the 32 MiB motion path even if its poster fails; and static, malformed,
    AVIF, invalid-MIME, and invalid-dimension fallback. Verify the ImageMagick
    one-frame, 16,384-pixel/40-MP, 64-MiB memory/map, disk-zero, one-thread,
    ten-second limits, container coder/delegate policy, and bounded Referer
    derivation from a validated source URL.

Provider request behavior and anti-abuse limitations are documented in
[PROVIDERS.md](PROVIDERS.md).
