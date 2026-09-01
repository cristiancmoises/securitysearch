# Search-first interface

Security Search v0.9.4 keeps the logo and search field as the landing page's
primary visual anchors. Navigation and SecurityOps links remain available
without competing with the search task.

## Landing-page hierarchy

The first page presents:

1. One subordinate Settings utility.
2. The Security Search logo.
3. The primary search field and submit action.
4. One compact hint: Google is the default and Brave is available.
5. Quiet text links to [securityops.co](https://securityops.co/) and
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

The SecOps stylesheet remains `static/themes/SecOps.css`. Asset URLs use
`config::VERSION=11`, so v0.9.4 requests `?v11` and does not reuse the stale
v10 response.

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

An animated GIF, WebP, or APNG candidate starts with its normal lazy thumbnail
poster. A candidate hint from either result URL selects the full-size original,
and ordinary `.gif`, `.webp`, and `.apng` URLs are all probed; static WebP is
restored to its poster after frame validation. An active animation format filter
uses the full-size original even when its CDN URL is extensionless. Inline data
URLs are excluded. Near the viewport,
`static/images-motion.js` swaps in the chosen motion source through the same-origin
`/proxy?...&s=animated` path. The proxy applies a 20 MB cap, validates an allowed
raster MIME type, and requires at least two validated frames before forwarding
the original bytes unchanged. It also bounds frame/dimension/pixel-frame and PNG
chunk work. The byte cap is enforced on decompressed output,
not trusted content length. GIF/WebP uses an Imagick ping under temporary,
restored resource limits. PNG/APNG validates chunk CRCs/order,
`acTL`/`fcTL`/`fdAT` sequencing, frame data/counts, and canvas bounds rather
than trusting `acTL` alone; this also handles APNGs that Alpine Imagick exposes
as one frame. A validation or loading failure restores the poster; SVG, video,
gifv, and data URLs are ineligible.

The controller allows at most three active cards on desktop or two on
coarse-pointer devices. Automatic and deliberate activation both request the
poster and wait until it loads or errors. A successfully loaded or already
complete valid poster gets two animation-frame boundaries and one paint before
the motion source; a broken poster may proceed once settled without a paint
guarantee. User intent upgrades any pending automatic preparation rather than
duplicating it. Pointer/focus activation requires the actual image wrapper to
be in the viewport, respects the same cap, and evicts the oldest active card.
Leaving the viewport cancels pending listeners/frames and restores the poster;
a mutation observer registers infinite-scroll additions. Reduced-motion
disables motion entirely; data-saver disables automatic activation. Conservative
URL/filter discovery recognizes explicit APNG/animated-PNG filename hints even
with a `.png` extension, but may leave an extensionless animation or an APNG
with only an ordinary `.png` name as a static poster. Ordinary WebP is a
candidate; frame validation restores static WebP. Other raster
formats are not motion candidates.
An automatically failed candidate is suppressed individually; pointer or focus
can request one deliberate retry, after which another failure removes its motion
source and retains the poster.

The motion badge becomes visible only after a candidate passes multi-frame
validation and loads. Its label reflects the URL/filter candidate hint; it is
not an authoritative MIME report.

## Release checks

Before promotion:

1. Fetch the home page without a theme cookie and verify it links to
   `/static/themes/SecOps.css?v11`.
2. Verify that stylesheet returns HTTP 200 with a CSS content type.
3. Test no cookie, an invalid cookie, `theme=Dark`, and a valid nondefault
   theme on the home, results, and Settings pages.
4. Inspect computed colors or screenshots—not only the stylesheet link—at
   representative phone, tablet, and desktop sizes.
5. Exercise keyboard traversal, visible focus, contrast, and no-JavaScript
   search/settings behavior.
6. Force a scraper failure and verify the neutral message, escaped upstream
   detail, same-query retry, Settings action, and explicit Brave URL.
7. Verify successful providers with real result cards/API arrays. HTTP 200 by
   itself is not evidence of a working scraper. Give command-line HTML `/web`
   and `/images` requests a browser-like User-Agent; API requests do not need
   one.
8. Test image automatic loading, its opt-out, and the ordinary pagination link.
9. Include verified animated and false-positive/static fixtures. Confirm the
   poster-first flow, 20 MB proxy cap, MIME rejection fallback,
   Imagick-validated GIF/WebP, strict PNG chunk/`acTL`-validated APNG, and
   poster fallback for invalid/static candidates. Check the same-origin motion
   URL, desktop/coarse caps, oldest-card eviction, off-screen restore,
   poster settling for automatic and deliberate requests, successful-poster
   two-frame paint, broken-poster behavior, user-intent upgrade, pending-work
   cancellation, viewport-only pointer/focus, reduced-motion/data-saver
   behavior, data-URL exclusion, full-size-original selection, explicit
   animated-PNG `.png` discovery, conservative ordinary `.png`
   APNG/extensionless handling, rejection outside the GIF/WebP/PNG allowlist,
   truthful post-validation motion-badge visibility, and infinite-append
   registration.
10. Verify decompressed-byte rejection, temporary Imagick resource-limit
    restoration on success/failure, and malformed APNG CRC, ordering,
    sequence/count/data, forged-acTL-only, and canvas-bound cases.

Provider request behavior and anti-abuse limitations are documented in
[PROVIDERS.md](PROVIDERS.md).
