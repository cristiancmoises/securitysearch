# Interface behavior — v0.9.19

The homepage and result header place four 14 px SVG actions inside the right edge of the search bar in a horizontal row: Search, Search Image, Search Pinterest and Search YouTube. Native form destinations, full accessible names, focus labels and 28 px desktop / 44 px coarse-pointer targets remain. Tron is the default; explicit saved themes are honored. The input reserves room for the actions. The black/cyan CSS background does not request the old 12 MB GIF. Wiki/Git in the homepage footer are plain links, without the rectangle.

## Images

Grid, Compact grid, Gallery, Large feed, List and Filmstrip retain quality and format filters. Each provider page renders up to 24 images; omitted results are disclosed. Original, Preview and View animation links use the validated media proxy. Full originals can consume more bandwidth, and a WebP file need not be animated.

Image results now append when the visitor scrolls near the last card. Filmstrip observes inside its horizontal container and checks that the strip is on screen. Earlier cards stay in place. Clicking Next page also appends when the enhancement is available; modified clicks retain native navigation. No code moves the viewport, replaces the document or changes browser history. There is no timer/interval/Play/Pause panel.

The enhancement uses [Intersection Observer](https://developer.mozilla.org/en-US/docs/Web/API/Intersection_Observer_API), one passive scroll listener per relevant scroll container and one request at a time. It waits for scrolling before the first automatic request and for fresh scroll activity after each completed page. It does not fill a short page with an uncontrolled chain of requests. New requests pause while the document is hidden; an already-started request may finish. No animation loop or polling runs.

Settings → **Load more images while scrolling → No** omits the pagination script. Disabled/unsupported JavaScript and browsers reporting Save-Data keep native Next page navigation. Empty initial pages do not start automatic loading. The rest of the application retains native, script-free forms and links.

Visible GIF, animated WebP and APNG sources play through the privacy proxy with at most two loading/four active. Viewport exit, a hidden tab, reduced motion and Save-Data restore static posters. Settings → **Play visible GIF, WebP and APNG previews → No** omits the independent motion script. Malformed, static or oversized candidates restore the poster without retries. The native animation link remains. GIF/WebP/APNG first-frame extraction avoids decoding a full sequence just for a poster. Cropped WebP first frames may produce a cropped poster; the original animation is unchanged. Static formats do not acquire animation, and unsupported animated AVIF/video is outside this change.

## Google and Reddit

Google stays selected by default. One failed initial Google web/image search automatically tries Brave with a 12+8-second shared network budget. Successful fallback replaces the header's provider and shows a notice. Shared filters survive; incompatible ones reset. No fallback occurs for empty successful results, API calls or continuation pages. Brave supplies no image continuation; format filtering checks only the returned page. Dual failure still gives an honest error with recovery.

The new Reddit navigation link opens the operator's Redlib. Local News search shows r/news + r/worldnews recent posts when blank and related posts for keywords. Sort/period are labeled as keyword-search options. News in the top service navigation retains its external destination. Returned post text is escaped; paging uses validated encrypted continuation data. Live Redlib was unavailable (503) at the release check.

## Bounds and recovery

Each response is limited to 1 MiB and each request has a 25-second deadline. The server emits normalized JSON; the client validates every card and builds elements with text APIs, without parsing or inserting HTML. Appended images are lazy, async and low priority. Pagination URLs must remain on the same origin and `/images`; media URLs must use the local `/proxy` route. Both JSON and continuation provider identity must match the current stream. Fetch redirects are rejected.

To keep long sessions responsive, a page retains at most 480 cards. When less than one full provider page fits, a short message invites native Next page navigation to start a fresh document. Earlier results are not removed or reordered, including a focused card. No continuation is consumed solely to enforce this limit.

Final pages end loading. An empty page with a continuation stops automatic loading and retains native Next page. Status text announces loading, completion or recovery without moving focus. On a failed/ambiguous request, the current images stay visible and the link becomes **Restart search** with compatible search preferences retained. Choose another provider in the filters if necessary. The consumed continuation is never retried automatically. Leaving a page during a request aborts it and preserves restart recovery for browser history restoration.

The standalone provider error page still returns HTTP 503 with explicit recovery and no success timing. Upstream rate limits can continue. Legacy frame links return HTTP 410 and a new-search link without a provider request.

Local HTTP and Node state tests exercise these contracts. The live desktop homepage was captured and inspected in a real browser for v0.9.19. Image scrolling/animation, mobile behavior and assistive technologies still need real-device acceptance testing.
