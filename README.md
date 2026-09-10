# Security Search v0.9.24

[English](README.md) · [Português do Brasil](README.pt-BR.md)

![SecuritySearch black homepage](docs/screenshots/securitysearch-0.9.21-home-black.png)

Earlier local release rendering, not a current production capture. The appearance
is retained; this release uses asset **28**.

A PHP search proxy based on [4get](https://git.lolcat.ca/lolcat/4get), maintained for
[SecurityOps](https://securityops.co). **Works without JavaScript** for normal
searches and bundled themes. Local pictures and image enhancements use optional,
same-origin scripts. External providers can refuse or rate-limit requests.

## News that does not require Redlib

**News RSS** is the new default, with Google News RSS followed by Bing News RSS on
failure. Select a single source to disable fallback. US English and Brazilian
Portuguese editions are supported. This is publisher-headline search, not Reddit.
The Reddit provider remains available explicitly and can still be unavailable.
Previously saved provider preferences are not overwritten: choose **News RSS** in
the news provider selector, or open `/news?scraper=newswire`.

The supplied VPS diagnostic found HTTP-200 pages with no Redlib results and
challenge indicators at two configured instances, plus HTTP 418 from Nadeko.
Increasing timeouts or removing parser checks would not make those pages news.
No challenge solver, identity spoofing or proxy rotation is introduced.

The new deployment gate requires fresh nonempty headlines and a neutral keyword
search from one RSS source on the VPS. It saves safe per-stage evidence to
`news-live.json`, persists the successful source/edition and verifies it after
cutover. If neither works, the running service is not replaced. The RSS endpoints
are best-effort external feeds, not a guaranteed API; local fixtures cannot prove
live availability.

## Performance and security boundaries

Only public query-free headlines can enter the cache: 120 seconds fresh and up to
600 seconds as visibly dated stale fallback. No keyword-query/result cache. Source
and edition have separate keys. A short refresh lock suppresses redundant public
work without waiting or a background task. Refused/challenged sources cool down
for ten minutes; rate-limited sources wait at least five minutes, respecting a
bounded Retry-After header. Real empty searches do not trigger a second provider.

News requests use fixed HTTPS destinations, the existing public-IP validation and
DNS pinning, certificate checks, no redirects and a 1 MiB response limit. At most
two RSS sources are tried sequentially, 4.5 seconds per attempt within a nine-second
adapter deadline. Initial synchronous DNS can exceed cURL time limits; this is not
an absolute all-provider latency guarantee or a measured speedup.

The RSS parser rejects DTDs, entities, XInclude, malformed XML/UTF-8 and excess
structure. It returns at most forty headlines with publisher/date/source links.
Descriptions, scripts and remote thumbnails are not rendered or fetched. RSS has
no authoritative next-page token: no invented pagination is presented. Search pages
stay private/no-store and noindex. No successful response is fabricated on failure.

## Existing search and appearance features

Web, images, video, music and optional Reddit remain separate providers. Google
web/image search retains its one labelled Brave fallback. Binternet retains modern
and legacy gallery parsing, bounded cursors and actual smaller previews. Images
retain six views, quality/format choices, ordinary pagination, optional infinite
scrolling and limited visible animation playback with still posters and controls.

Pure black remains the default. Choose appearance offers bundled image/palette
previews; settings/filter controls use black backgrounds and the theme accent.
**My picture** opens a local file chooser outside every form. JPEG/PNG/WebP/GIF are
recognized from bytes; GIF becomes a still image. Files are limited to 16 MiB and
48 megapixels after decode; normalized output is at most 1920 pixels/2 MiB. No
file, filename or EXIF metadata is uploaded. Storage defaults to this tab session;
Remember on this device opts into persistent browser storage. Removal clears both
when the browser permits it. A blocked/full store allows page-only display.

Historical Lain/SecOps images are optional operator assets outside the Git checkout.
They and their derivatives remain excluded from all new source releases, including
Codeberg. Tron retains its bundled optimized animation. Without the private pack,
Lain keeps its palette and SecOps its public Matrix alternative. Onion and dated
Tranco information remain; no rank is invented when metadata is unavailable.

## Install this update

From the complete extracted `securitysearch-update-0.9.24` kit on your computer:

```fish
fish ./apply-securitysearch.fish ~/securitysearch
and fish ./deploy-securitysearch.fish ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

The apply helper accepts exact clean v0.9.23-r1 or v0.9.23 source. It creates a normal
commit and refuses uncommitted/conflicting work. SSH is `root@securityops.co`, port
5119. Deployment preserves the existing `172.17.0.1:5140 → 80` binding, networks and
compatible private settings without recreating Nginx Proxy Manager. Reuse the theme
pack; do not commit it. Keep backups: a running container can mount their snapshots.

The full isolated offline audit, candidate readiness, real RSS news check and real
Binternet gate must pass. Rollback remains available. Do not use old manifest-based
launchers with new source, bypass tests or delete the printed rollback directories.

## Publish the source release

```fish
fish ./publish-securitysearch.fish ~/securitysearch
# Retry only this host, including release attachments:
fish ./publish-securitysearch.fish ~/securitysearch --host git.securityops.co
```

The annotated tag is **v0.9.24**. Source assets are `securitysearch-v0.9.24.tar.gz`
and `.tar.gz.sha256`, generated from the real tagged commit, not test history.
Publication uses private token prompts, non-force pushes, verified drafts and
multipart Forgejo uploads. Matching partial releases resume; conflicting tags,
notes and assets are never replaced. Only the private `-deploy.tar.gz` can contain
operator artwork. Publishing does not deploy the VPS.

[Codeberg](https://codeberg.org/berkeley/securitysearch/releases/tag/v0.9.24) ·
[GitHub](https://github.com/cristiancmoises/securitysearch/releases/tag/v0.9.24) ·
[SecurityOps .co](https://git.securityops.co/cristiancmoises/securitysearch/releases/tag/v0.9.24) ·
[SecurityOps .com.br](https://git.securityops.com.br/cristiancmoises/securitysearch/releases/tag/v0.9.24)

Release links work only after publication to that host. Source is PHP, not a
precompiled executable or Docker image. Checksums are not cryptographic signatures.

## Validation

```sh
sh scripts/test.sh --keep-going
```

All **55** command entries remain mandatory. PHP curl/DOM/XML/mbstring/APCu/Imagick,
Python, Node, Git and fish are needed for the full suite. A local missing dependency
is not a passing audit. [Audit](docs/AUDIT-0.9.24.md) distinguishes executed tests,
fixtures, missing native dependencies and unperformed live operations.
[Release notes / Notas da versão](docs/RELEASE-0.9.24.md).

License: [AGPL-3.0](license.txt). **In Code We Trust.**
