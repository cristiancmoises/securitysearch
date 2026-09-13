# DeviantArt via SkunkyArt

The adapter targets the retained SkunkyArt Enhanced OAuth2 service contract from version
2026.09.13.1-oauth2: `GET /api/search`, `q`, `p` (1–417), `scope=all`, `media=image`,
`orientation=any|landscape|portrait|square`, `ai=any|hide|only`, `mature=hide|include`.
Results contain `items` and boolean `has_more`; each artwork supplies original_url, preview/full
signed `/media?t=...` URLs, title, mature and optional dimensions. Changes to this contract fail
closed, not as fabricated empty success. The instance must remain correctly configured itself.

The source examined is the retained archive `skunkyart-enhanced-2026.09.13.1-oauth2.tar.gz`,
`internal/art/model.go` and `internal/art/server.go`. This is source-contract verification, not a
claim that the public instance was reachable during development. The persisted deployment guide
names skunkyart.securityops.co; the misspelled securiyops.co is not used or silently redirected.

One API request per page; no automatic provider fanout. Native continuation tokens retain the
query and filters, never an arbitrary next URL. The shared service transport pins public DNS
results, refuses redirects and caps decoded/wire bodies at 2 MiB under existing deadlines.
The adapter examines at most 100 returned items and emits the existing 24-card page representation.
Titles are bounded to 1024 UTF-8 bytes. Unknown dimensions remain unknown; the renderer owns display
fallback geometry. Media URLs stay on the fixed SkunkyArt host with the exact signed-media route.
Artwork links require HTTPS DeviantArt art paths; mature content is filtered by both service and adapter.

The toolbar button routes `/images?destination=skunkyart` through the existing native destination
handler; incompatible old continuation/filter state is reset. Existing image layouts and infinite
scroll reuse the same normalized card model. No remote SVG/font, new JavaScript library or live
provider request is needed to render the shortcut. No performance victory is implied.

Normal deployment probes the candidate adapter for a fixed neutral query, at most two pages when
continuation exists. Actual nonempty first results and valid available pagination are required.
A separately stored sanitized skunkyart-live.json is checked by the independent publisher verifier.
SkunkyArt availability cannot waive Google Web/Images, RSS or Binternet requirements.
