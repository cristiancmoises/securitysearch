# v0.9.23 — deployment and publication

Application 0.9.23, static asset marker 27. Use the matching kit; older manifests
correctly reject changed source. The full patch accepts exact clean v0.9.22-r2,
v0.9.22-r1 or v0.9.22 main trees. Unknown local work is preserved and refused.

## Apply and deploy from local fish

```fish
cd ~/Downloads
and sha256sum -c securitysearch-update-0.9.23.tar.gz.sha256
and tar -xzf securitysearch-update-0.9.23.tar.gz
and fish ~/Downloads/securitysearch-update-0.9.23/apply-securitysearch.fish ~/securitysearch
and fish ~/Downloads/securitysearch-update-0.9.23/deploy-securitysearch.fish \
    ~/securitysearch \
    --theme-assets ~/.local/share/securitysearch/operator-themes-v1 \
    --rank-refresh
```

Reuse the external, verified historical-theme pack; do not reconvert or put it in
Git. Deliberately omitting `--theme-assets` uses public palette/Matrix alternatives.
Deployment uses root@securityops.co, SSH 5119 and the existing Docker
172.17.0.1:5140 -> 80 binding and networks. NPM is not reconfigured. Keep backup
folders: a running container can mount private snapshots from them.

## News selection is a deployment gate, not an uptime promise

The compiled default is redlib.privacyredirect.com. The other allowed candidates
are redlib.nadeko.net and redlib.privadency.com, taken from the official inventory
updated 2026-09-09. libre.securityops.co is no longer used by active news routing
or the homepage Reddit link. Old continuations tied to retired origins must be
restarted; no token is silently transferred to another origin.

After the complete offline audit and candidate startup, `scripts/redlib-probe.php`
tries the current allowed primary first. It uses the actual adapter, fixed routes,
SSRF/TLS policy and DOM parser. One candidate must return at least one news item
from BOTH `/r/news+worldnews/new` and `/r/news+worldnews/search?q=technology` (with
fixed new/all/restrict_sr/type parameters). A homepage, 200-status challenge,
empty feed or feed-only success cannot qualify. The neutral query is intentional;
no private user query is used for this check.

The selected origin becomes `FOURGET_REDLIB_PRIMARY` in the replacement, and the
updater checks its effective PHP config before committing the transaction. The
runtime disclosure and homepage service link follow that configured value.
`redlib-live.json` in the printed backup directory records hosts, counts, timing
and error class, not titles, result bodies or credentials. Any selection failure
stops BEFORE the existing container is stopped. Native DNS remains synchronous;
a 40-second process timeout bounds the whole CLI check, while each transport
attempt keeps its 3.5-second network budget. Reachability is location/time dependent.

The existing full offline audit, live Binternet gate and rollback checks remain.
Source-code tests are not live provider success. The authoring environment could
not verify a working external instance; the candidate test is therefore required,
not optional. If every allowed host fails from the VPS, inspect the retained
report instead of forcing cutover or claiming success.

## My picture

On the homepage click **Use a picture from this device**, then **Choose File**.
The native opt-in POST only saves `theme=Custom` and opens the editor. Selecting a
file applies it automatically; no second Save appearance click is needed. The
Settings page also shows the editor when My picture is selected.

The optional local JavaScript must be enabled. Without it the file control is
explicitly disabled with instructions, rather than silently nonfunctional. No
file is sent to an upload endpoint. JPEG, PNG, WebP and GIF are accepted based on
bytes, not OS MIME labels; GIF becomes one still frame. Limits: 16 MiB input,
48 megapixels after decode, longest output edge 1920px, normalized data URL <=2 MiB.
HEIC/HEIF and SVG are not supported; export those as JPEG/PNG first. Browser decoding
itself can allocate memory before the pixel check, so do not describe it as a
sandbox for hostile image files.

Pictures default to this tab's sessionStorage. Browser session restore may restore
that storage. Remember on this device explicitly uses localStorage. Remove clears
both when access is allowed. Blocked/full storage retains a page-only background
and shows an explanation. This is not encrypted storage; shared-device users and
same-origin scripts can access stored pictures. Storage-clearing errors are shown.
Ordinary searches and bundled themes continue to work without JavaScript.

## Publish only after deployment succeeds

```fish
fish ~/Downloads/securitysearch-update-0.9.23/publish-securitysearch.fish ~/securitysearch
```

This creates/reuses an annotated v0.9.23 tag and publishes the exact real tagged
source as `securitysearch-v0.9.23.tar.gz` and its `.tar.gz.sha256`, not a Docker
image. Artifacts go to `~/securitysearch-release-v0.9.23/`. The local recovery bundle
requires published v0.9.20. Tokens are entered privately and are not put in argv,
URLs or files. Conflicting tags/assets are preserved; do not force replacements.
The same clean source goes to all four hosts, including Codeberg. Operator-only
Lain/SecOps images never enter new source commits or release attachments.

Retry ONLY one host including the release assets:

```fish
fish ~/Downloads/securitysearch-update-0.9.23/publish-securitysearch.fish \
    ~/securitysearch --host git.securityops.co
```

Publication does not deploy the VPS. Across hosts it is not globally atomic;
matching partial publication can be resumed. An optional full provider sample is
`fish ~/Downloads/securitysearch-update-0.9.23/audit-providers.fish`; exit 2 signals
an empty/unavailable provider, not a completely successful audit.
