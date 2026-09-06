# Release and deployment

These instructions prepare and deploy v0.9.11. They do not imply that the tag,
remote commit, hosted release, or IONOS deployment already exists.

## Build the source artifact

Commit the tested change first. The sanitized history published with v0.9.4
must remain clean across every advertised ref. Before running the release helper
or creating a tag, prove that none of the removed paths is reachable from any
local branch, remote-tracking ref, or tag:

```bash
for removed_path in \
  static/themes/Kuruminha.css \
  static/misc/mimi.jpg \
  securitysearch.zip
do
  test -z "$(git log --all --format= --name-only -- "$removed_path")" || {
    echo "removed path remains in reachable history: $removed_path" >&2
    exit 1
  }
done

# Prompt artifacts are forbidden regardless of extension, capitalization, or
# directory. Check every path reachable from every local ref, not only HEAD.
if git log --all --format= --name-only |
   LC_ALL=C grep -Eiq '(^|/)[^/]*(prompt|god[-_. ]?tier)[^/]*($|/)'; then
  echo "prompt artifact remains in reachable history" >&2
  exit 1
fi
```

An ordinary deletion commit does not satisfy this check. If a stale clone has
reintroduced removed objects, stop and repeat the coordinated history-cleaning
procedure before publication; do not silently force-push from that clone.

Then, from a clean working tree:

```bash
git diff --check
git status --short
./release.sh 0.9.11
(cd dist && sha256sum -c securitysearch-v0.9.11.tar.gz.sha256)
git tag -a v0.9.11 -m "Security Search v0.9.11"
```

The release helper uses `git archive`, respects `.gitattributes`, and adds only
the required empty `icons/` runtime-cache directory that Git cannot track:

```text
dist/securitysearch-v0.9.11.tar.gz
dist/securitysearch-v0.9.11.tar.gz.sha256
```

Inspect the archive before publishing:

```bash
archive_listing=$(tar -tzf dist/securitysearch-v0.9.11.tar.gz)
printf '%s\n' "$archive_listing" | sed -n '1,10p'
printf '%s\n' "$archive_listing" |
  grep -Fxq 'securitysearch-v0.9.11/icons/'
printf '%s\n' "$archive_listing" |
  grep -Fxq 'securitysearch-v0.9.11/banner/securitysearch.webp'
printf '%s\n' "$archive_listing" |
  grep -Fxq 'securitysearch-v0.9.11/static/misc/secops.gif'
printf '%s\n' "$archive_listing" |
  grep -Fxq 'securitysearch-v0.9.11/static/images-fallback.js'
printf '%s\n' "$archive_listing" |
  grep -Fxq 'securitysearch-v0.9.11/static/images-motion.js'
tar -xzOf dist/securitysearch-v0.9.11.tar.gz \
  securitysearch-v0.9.11/data/config.php |
  grep -Eq 'const VERSION = 14;'
if printf '%s\n' "$archive_listing" |
   LC_ALL=C grep -Ei '(\.bak($|\.)|data/api_keys/|^securitysearch-v0\.9\.7/dist/|securitysearch\.zip|Kuruminha\.css|mimi\.jpg|(^|/)[^/]*(prompt|god[-_. ]?tier)[^/]*($|/))'; then
  echo "unexpected release content"
  exit 1
fi
```

The required-directory, logo, and SecOps-background checks must succeed, and the
image-controller and version checks must also succeed. The forbidden-content
check must print nothing. The package must contain no API keys, proxy
credentials, cookies, or Evelin/SSH material.

## Publish to Git remotes

List the configured remotes and confirm their targets:

```bash
git remote -v
```

This checkout has three independently verifiable publication targets. Their
URLs do not embed credentials:

- `origin` → `git@github.com:cristiancmoises/securitysearch.git`
- `codeberg` → `git@codeberg.org:berkeley/securitysearch.git`
- `securityops_br` → `https://git.securityops.com.br/cristiancmoises/securitysearch.git`

`securityops` may be configured for
`https://git.securityops.co/cristiancmoises/securitysearch.git`, but the
repository was absent at the last verified check. Do not include or claim that
target until `git ls-remote securityops` succeeds and the repository owner has
created it. Never put access tokens in remote URLs.

The remote inventory must contain only `main` and release tags v0.9.0 through
v0.9.11. Capture the exact current remote object IDs immediately before each
push and use an explicit lease for every ref. A lease mismatch means someone
updated that remote; stop and investigate instead of overwriting their work.
The following Bash function publishes one named remote atomically while also
asserting that a previously absent ref is still absent. Invoke it independently
for each available target so one authentication/network failure is recorded for
that target and is never mistaken for another target's result:

```bash
publish_remote() (
  set -euo pipefail
  remote=$1
  release_tags=(v0.9.0 v0.9.1 v0.9.2 v0.9.3 v0.9.4 v0.9.5 v0.9.6 v0.9.11)
  expected_refs=(refs/heads/main)
  for tag in "${release_tags[@]}"; do
    expected_refs+=("refs/tags/${tag}")
  done
  expected_ref_inventory=$(printf '%s\n' "${expected_refs[@]}" | sort -u)

  remote_listing=$(git ls-remote --heads --tags "$remote")
  actual_ref_inventory=$(
    printf '%s\n' "$remote_listing" |
      awk '$2 !~ /\^\{\}$/ {print $2}' |
      sort -u
  )
  unexpected_refs=$(
    comm -23 \
      <(printf '%s' "$actual_ref_inventory") \
      <(printf '%s' "$expected_ref_inventory")
  )
  test -z "$unexpected_refs" || {
    printf '%s\n' "$remote has unexpected heads/tags:" "$unexpected_refs" >&2
    echo "rewrite or explicitly delete those exact refs before publishing" >&2
    exit 1
  }

  remote_main=$(
    awk '$2 == "refs/heads/main" {print $1}' <<<"$remote_listing"
  )
  lease_args=("--force-with-lease=refs/heads/main:${remote_main}")
  refspecs=("main:refs/heads/main")

  for tag in "${release_tags[@]}"; do
    remote_tag=$(
      awk -v ref="refs/tags/${tag}" '$2 == ref {print $1}' <<<"$remote_listing"
    )
    if [[ -n "$remote_tag" ]]; then
      local_tag=$(git rev-parse "refs/tags/${tag}")
      test "$remote_tag" = "$local_tag" || {
        echo "$remote already has a conflicting immutable ${tag} tag" >&2
        exit 1
      }
    fi
    lease_args+=("--force-with-lease=refs/tags/${tag}:${remote_tag}")
    refspecs+=("refs/tags/${tag}:refs/tags/${tag}")
  done

  git push --atomic "${lease_args[@]}" "$remote" "${refspecs[@]}"
)

for remote in origin codeberg securityops_br; do
  publish_remote "$remote"
  publish_status=$?
  if test "$publish_status" = 0; then
    printf '%s\n' "$remote published"
  else
    printf '%s\n' "$remote failed; record and investigate independently" >&2
  fi
done
```

Do not replace these leases with an unqualified `--force`, and do not add `+`
to the refspecs. The explicit expected value is what prevents a concurrent
remote update from being destroyed. The inventory check intentionally stops on
remote-only branches or tags: inspect each one and either include it in the
sanitized rewrite or delete that exact ref deliberately. Leaving an advertised
ref untouched can leave removed objects reachable.

Verify every advertised object independently; one successful push is not
evidence for any other remote or ref:

```bash
release_refs=(
  refs/heads/main
  refs/tags/v0.9.0
  refs/tags/v0.9.1
  refs/tags/v0.9.2
  refs/tags/v0.9.3
  refs/tags/v0.9.4
  refs/tags/v0.9.5
  refs/tags/v0.9.6
  refs/tags/v0.9.11
)
verify_remote() (
  set -euo pipefail
  remote=$1
  remote_listing=$(git ls-remote --heads --tags "$remote")
  actual_ref_inventory=$(
    printf '%s\n' "$remote_listing" |
      awk '$2 !~ /\^\{\}$/ {print $2}' |
      sort -u
  )
  expected_ref_inventory=$(printf '%s\n' "${release_refs[@]}" | sort -u)
  inventory_diff=$(
    comm -3 \
      <(printf '%s' "$actual_ref_inventory") \
      <(printf '%s' "$expected_ref_inventory")
  )
  test -z "$inventory_diff" || {
    printf '%s\n' "$remote ref inventory differs:" "$inventory_diff" >&2
    exit 1
  }

  for ref in "${release_refs[@]}"; do
    local_oid=$(git rev-parse "$ref")
    remote_oid=$(awk -v wanted="$ref" '$2 == wanted {print $1}' <<<"$remote_listing")
    test -n "$remote_oid" && test "$remote_oid" = "$local_oid" || {
      echo "$remote: $ref does not match local $local_oid" >&2
      exit 1
    }
  done
)

verification_failed=0
for remote in origin codeberg securityops_br; do
  verify_remote "$remote"
  verify_status=$?
  if test "$verify_status" = 0; then
    printf '%s\n' "$remote verified"
  else
    printf '%s\n' "$remote verification failed" >&2
    verification_failed=1
  fi
done
test "$verification_failed" = 0
```

If more remotes are intentionally configured, inspect each URL and push it
explicitly. Record authentication, permission, or network failures per remote.
Do not claim any remote or hosted release was published until its commit
and tag are visible there. Create hosted releases only after their matching tags
are visible, attaching both the tarball and checksum where supported.

## Pre-deployment verification

Build without cache, start the candidate, lint all PHP files inside the built
image, and inspect logs:

```bash
docker compose build --no-cache
docker compose up -d
docker compose ps
docker compose exec -T security-search sh -lc \
  'find /var/www/html/4get -type f -name "*.php" -print0 |
   xargs -0 -n1 php -l >/tmp/php-lint.log && tail -1 /tmp/php-lint.log'
docker compose logs --tail=200 security-search
```

An HTTP 200 response is not enough: scraper failures are rendered in a valid
HTML or JSON response. Assert result-bearing responses:

```bash
release_ua='Mozilla/5.0 (release-smoke-test)'

google_web=$(curl -fsS -A "$release_ua" \
  'http://127.0.0.1:5140/web?s=security+privacy&scraper=google')
printf '%s' "$google_web" | grep -q 'class="text-result"'
! printf '%s' "$google_web" | grep -qi 'Search provider unavailable'

google_images=$(curl -fsS -A "$release_ua" \
  'http://127.0.0.1:5140/images?s=network+security&scraper=google')
printf '%s' "$google_images" | grep -q 'class="image-wrapper"'
! printf '%s' "$google_images" | grep -qi 'Search provider unavailable'

google_web_api=$(curl -fsS \
  'http://127.0.0.1:5140/api/v1/web?s=security+privacy&scraper=google')
printf '%s' "$google_web_api" |
  grep -Eq '"status":"ok".*"web":\[\{'

google_image_api=$(curl -fsS \
  'http://127.0.0.1:5140/api/v1/images?s=network+security&scraper=google')
printf '%s' "$google_image_api" |
  grep -Eq '"status":"ok".*"image":\[\{'
```

Run equivalent result-bearing checks for Brave when the production egress is
accepted by Brave. If either provider returns an anti-abuse challenge, record
that upstream limitation separately; do not report it as successful search.
The browser-like User-Agent is required for these command-line HTML `/web` and
`/images` checks because header bot protection rejects curl's default agent.
The API commands do not require it.

When testing challenge behavior, confirm direct Brave egress stops after the
first recognized proof-of-work response. If a reviewed private pool is enabled,
confirm it rotates addresses for no more than three total attempts. A deliberately
unreachable Google/Brave test route should also demonstrate the per-transfer
10-second connect and 20-second total timeout bounds without an unbounded loop.

Run provider fixtures in addition to live probes. A Google image response with
`cursor.isExactTotalResults` and a non-empty `results` array must retain every
result while returning no next-page token. Cover valid `tbLargeUrl`, invalid or
missing `tbLargeUrl` with valid `tbUrl`, a missing original with a valid
thumbnail, and records with no usable source. Assert the fallback carries the
matching `tbUrl` dimensions. Exercise the 60-second single-flight owner lease,
a waiter consuming a publication within six seconds, fail-fast after that wait,
five-second ordinary bootstrap failure sharing, and the 30-second anti-abuse
cooldown during both bootstrap and `cse/element/v1` result requests. No cooldown
test may be counted as a successful Google result.

Brave fixtures must cover `properties.format`, a URL-extension-only animation
hint, a smaller `properties.resized` GIF/WebP, a missing original with a valid
resized source, and malformed URL/dimension fields. Assert that the resized
animated URL is the preferred `motion_url`, the original remains a fallback,
and no invalid source reaches rendered markup.

Verify the SecOps cascade and cache version:

```bash
home=$(curl -fsS http://127.0.0.1:5140/)
printf '%s' "$home" | grep -q '/static/themes/SecOps.css?v13'
curl -fsSI http://127.0.0.1:5140/static/themes/SecOps.css?v13 |
  grep -qi '^Content-Type: text/css'
curl -fsSI http://127.0.0.1:5140/static/misc/secops.gif |
  grep -qi '^Content-Type: image/gif'
curl -fsS http://127.0.0.1:5140/static/themes/SecOps.css?v13 |
  grep -Fq 'url("/static/misc/secops.gif?v13")'
docker compose exec -T security-search php -r \
  'include "/var/www/html/4get/data/config.php"; exit(config::VERSION === 13 && config::DEFAULT_NSFW === "yes" ? 0 : 1);'

invalid_theme=$(curl -fsS -H 'Cookie: theme=missing-theme' \
  http://127.0.0.1:5140/)
printf '%s' "$invalid_theme" |
  grep -q '/static/themes/SecOps.css?v13'
```

Complete the visual, cookie, keyboard, narrow-screen, error-action, and
no-JavaScript checks in [UI.md](UI.md). Include animated and false-positive
fixtures. Verify poster-first loading and that both early deferred scripts,
`images-fallback.js?v13` and `images-motion.js?v13`, appear in the document head
before the image grid. Every result-host request must remain behind the
same-origin `/proxy`; test primary plus two poster fallbacks, the local
unavailable state, a Brave resized-motion source plus original fallback, one
eligible delayed cache-busted retry, and infinite-scroll registration. WebP is a
low-confidence candidate: it must receive validation and its provider fallback,
but no automatic cache-busted retry. Confirm user intent stays first and the
automatic queue orders GIF/APNG before WebP.

Fill the retained set beyond 36 settled desktop animations and 18 mobile
animations. A visible or in-flight candidate must never be evicted, so the soft
budget may be exceeded temporarily. When trimming becomes possible, only the
oldest settled off-screen candidate returns to its poster; bringing it back into
the observer margin must automatically prepare and reactivate it without a
click or infinite-scroll reload.

Exercise genuine and false-positive GIF, WebP, and APNG fixtures. The structural
parsers must accept only multi-frame containers, validate GIF block framing,
WebP RIFF/chunk/frame geometry, and PNG CRC/order/`acTL`/`fcTL`/`fdAT`
sequence/count/data rules. Reject a forged acTL-only static PNG, static WebP,
unsupported rasters, data URLs, credentials in source URLs, and malformed
provider records. Test provider metadata and URL-extension hints, including
extensionless Brave metadata and explicit `.gif`, `.webp`, `.apng`, or animated
`.png` paths. A motion badge becomes active only after validation, and every
accepted visible animation plays without a click.

Exercise rejection fixtures above 32 MiB of decompressed response data, 1,000
frames, 16,384 pixels on either dimension, 40 megapixels per frame, 250 million
decoded pixel-frames, 8,192 container chunks, and 131,072 GIF sub-blocks. A
compressed response whose write-callback output crosses 32 MiB must be rejected
even when its content length does not prove that limit. Confirm at most three
generation-owned validations run globally, nine additional requests wait for
no more than three seconds, a busy rejection returns `Retry-After: 2`, the sole
eligible non-WebP browser retry waits 2.2–3.0 seconds, busy rejections do not
consume client quota, and the 901st admitted candidate in one minute is
rejected. Also verify the native thumbnail fast path only accepts a
JPEG up to 128 KiB and 512 pixels per side, or a structurally validated animated
GIF, WebP, or APNG up to 1.5 MiB, 2,048 pixels per side, and 4 megapixels. Larger
JPEGs, static or malformed animation-capable formats, and other supported
rasters must retain the bounded ImageMagick path; any thumbnail download crossing
the independent 16 MiB transfer limit must be rejected. An animated GIF above
1.5 MiB may fail that bounded poster path, but its `s=animated` request must still
start without a click and may succeed up to the independent 32 MiB ceiling.

For ImageMagick fallbacks, reject MIME outside JPEG/PNG/GIF/WebP/AVIF and assert
one decoded frame (`list-length=2` rejects the second), 16,384 pixels per axis,
40 MP, 64 MiB memory, 64 MiB map, zero disk, one thread, and ten seconds.
Inspect the image policy in the built container: delegates and filters are
disabled, indirect `@` paths are denied, coders are
denied by default, and only `{JPEG,JPG,PNG,GIF,WEBP,AVIF,HEIC}` is re-enabled;
the proxy's narrower JPEG/PNG/GIF/WebP/AVIF MIME allowlist must still reject
HEIC input. Confirm the built image contains the ImageMagick HEIC module and a
valid AVIF fallback produces the bounded JPEG thumbnail instead of a 404.
Include success and failure fixtures to prove previous process limits are
restored. Also verify generic buffered and streamed image fetches
derive a bounded Referer from a validated public source URL, reviewed
provider-specific values are
length/CRLF checked, invalid values are omitted, and private or otherwise
invalid redirect targets remain rejected.

Verify port 5140 is loopback/private and unreachable on the public interface.
On the VPS, discover the live Nginx Proxy Manager container instead of assuming
its name, then identify the generated host file that serves `securityops.co`:

```bash
npm_container=$(
  docker ps --format '{{.Names}}\t{{.Image}}' |
    awk 'tolower($0) ~ /(nginx.proxy.manager|jc21\/nginx-proxy-manager|npm)/ {print $1}' |
    while IFS= read -r candidate; do
      if docker exec "$candidate" sh -lc \
        'grep -Eq "server_name .*securityops\\.co" /data/nginx/proxy_host/*.conf'; then
        printf '%s\n' "$candidate"
        break
      fi
    done
)
test -n "$npm_container"
npm_config=$(docker exec "$npm_container" nginx -T 2>&1)
printf '%s\n' "$npm_config" | grep -Fq 'server_name securityops.co'

npm_host_config=$(
  docker exec "$npm_container" sh -lc '
    for candidate in /data/nginx/proxy_host/*.conf; do
      grep -Eq "server_name .*securityops\\.co" "$candidate" && {
        printf "%s\\n" "$candidate"
        exit 0
      }
    done
    exit 1
  '
)
case "$npm_host_config" in
  /data/nginx/proxy_host/*.conf) ;;
  *) exit 1 ;;
esac
npm_host_text=$(docker exec "$npm_container" cat "$npm_host_config")
```

The WAF must inspect the local normalized `$uri`, never `$request_uri`; the
latter contains the encoded remote target and can incorrectly return 444 for a
normal source containing `/wp-content/uploads/`. SQL/XSS argument rules should
inspect a derived `$securitysearch_waf_args` value. Clear that derived value
only when `$uri` is exactly `/proxy` or `/proxy.php`, because `i=` necessarily
contains a remote URL; do not clear `$args` and do not disable argument
inspection on any other path.

Before changing NPM's database-backed advanced configuration or generated host
file, create exact timestamped backups on the mounted data filesystem and keep
both until all post-deployment checks pass:

```bash
npm_data_host=$(
  docker inspect --format \
    '{{range .Mounts}}{{if eq .Destination "/data"}}{{.Source}}{{end}}{{end}}' \
    "$npm_container"
)
test -d "$npm_data_host"
npm_backup_stamp=$(date -u +%Y%m%dT%H%M%SZ)
npm_database_backup="$npm_data_host/database.pre-securitysearch-v0.9.11-${npm_backup_stamp}.sqlite"
npm_generated_backup="$npm_data_host/nginx/proxy_host/${npm_host_config##*/}.pre-securitysearch-v0.9.11-${npm_backup_stamp}"
cp --preserve=mode,timestamps "$npm_data_host/database.sqlite" "$npm_database_backup"
cp --preserve=mode,timestamps \
  "$npm_data_host/nginx/proxy_host/${npm_host_config##*/}" \
  "$npm_generated_backup"
test -s "$npm_database_backup"
test -s "$npm_generated_backup"
sha256sum "$npm_database_backup" "$npm_generated_backup"
```

Apply the reviewed change persistently to NPM's database-backed advanced
configuration and regenerate or update the matching host file; changing only
the generated file will be lost. After reloading, prove that the live host has
one narrowly scoped proxy exemption and that all three SQL/XSS rules inspect
the derived value:

```bash
docker exec "$npm_container" nginx -t
docker exec "$npm_container" nginx -s reload
npm_host_text=$(docker exec "$npm_container" cat "$npm_host_config")
printf '%s\n' "$npm_host_text" | grep -Fq 'set $securitysearch_waf_args $args;'
test "$(printf '%s\n' "$npm_host_text" | grep -Fc 'set $securitysearch_waf_args "";')" = 1
printf '%s\n' "$npm_host_text" | grep -Eq 'if \(\$uri [^)]*\^/proxy'
test "$(printf '%s\n' "$npm_host_text" | grep -Ec 'if \(\$securitysearch_waf_args ')" -ge 3
! printf '%s\n' "$npm_host_text" | grep -Fq '$request_uri'
```

After reloading NPM and deploying the candidate, assert that an encoded public
WordPress upload is no longer rejected by the WAF and that the application
still blocks a loopback SSRF target:

```bash
wp_body=$(mktemp)
wp_status=$(curl -sS --get -o "$wp_body" -w '%{http_code}' \
  --data-urlencode 'i=http://cdn.osxdaily.com/wp-content/uploads/2013/07/dancing-banana.gif' \
  --data-urlencode 's=animated' \
  'https://securityops.co/proxy')
test "$wp_status" != 444
test "$wp_status" = 200
test "$(head -c 6 "$wp_body")" = GIF89a
rm -f -- "$wp_body"

ssrf_status=$(curl -sS --get -o /dev/null -w '%{http_code}' \
  --data-urlencode 'i=http://127.0.0.1/' \
  --data-urlencode 's=animated' \
  'https://securityops.co/proxy')
test "$ssrf_status" = 404

non_proxy_waf_status=$(curl -sS --get -o /dev/null -w '%{http_code}' \
  --data-urlencode 'securitysearch_waf_probe=<script>alert(1)</script>' \
  'https://securityops.co/')
test "$non_proxy_waf_status" = 444
```

A checked-in sample does not prove the live NPM process loaded the directives.
Retain `$npm_database_backup` and `$npm_generated_backup` through image, public
domain, log, WordPress-upload, and SSRF verification; record their exact paths
with the release evidence.

## Deploy on the current IONOS VPS

The active production tree is `/root/security-search-update`; it is not the
optional `/opt/securitysearch/releases` layout used in older documentation.
Use the approved Evelin profile and upload both files:

```bash
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.11.tar.gz \
  remote:/srv/evelin/securitysearch-v0.9.11.tar.gz
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.11.tar.gz.sha256 \
  remote:/srv/evelin/securitysearch-v0.9.11.tar.gz.sha256
ev --config /home/berkeley/.evelin/client.toml shell
```

In the Evelin shell, verify the artifact, prepare a clean sibling, and make an
exact rollback archive before changing the active tree:

```bash
cd /srv/evelin
sha256sum -c securitysearch-v0.9.11.tar.gz.sha256

rollback_stamp=$(date -u +%Y%m%dT%H%M%SZ)
rollback_archive=/root/security-search-pre-v0.9.11-${rollback_stamp}.tgz
old_tree=/root/security-search-update-old-${rollback_stamp}
release_tree=/root/security-search-v0.9.11
test -d /root/security-search-update
test ! -e "$old_tree"
test ! -e "$release_tree"

umask 077
tar -czf "$rollback_archive" -C /root security-search-update
test -s "$rollback_archive"
chmod 600 "$rollback_archive"

install -d -m 0750 "$release_tree"
tar -xzf /srv/evelin/securitysearch-v0.9.11.tar.gz \
  --strip-components=1 \
  -C "$release_tree"
test -f "$release_tree/docker-compose.yml"
test -f "$release_tree/Dockerfile"
```

Do not overlay the archive into `/root/security-search-update`. Inventory the
active tree for runtime-only material and approve an exact allowlist before
copying anything. Do not carry forward `.git`, cache, generated
`data/config.php`, or whole directories. The current production review found no
Google API key files, so copy no `data/api_keys/google_api.txt`. If a later
deployment deliberately enables `google_api`, copy only its exact reviewed key
file and enable the optional read-only `./data/api_keys:/var/www/html/4get/data/api_keys:ro`
Compose mount; Docker build context excludes the directory. Copy a private
Compose override, environment file, proxy credential file, or other secret only
if it is actually present, required, and individually reviewed; preserve its
restrictive mode. When `FOURGET_PROXY_GOOGLE` or `FOURGET_PROXY_BRAVE`
deliberately names a private pool, copy only the exact reviewed
`data/proxies/<pool>.txt` file into the clean sibling and enable the optional
read-only `./data/proxies:/var/www/html/4get/data/proxies:ro` Compose mount. Do
not copy the whole old proxies directory or publish the pool.

Build from the clean sibling while the old container remains online. Preserve
the current image under a rollback tag first:

```bash
previous_image_id=$(docker image inspect --format '{{.Id}}' security-search:latest)
test -n "$previous_image_id"
docker image tag "$previous_image_id" security-search:pre-v0.9.11

cd /root/security-search-v0.9.11
umask 077
printf 'SECURITYSEARCH_BIND_ADDRESS=172.17.0.1\n' > .env
chmod 600 .env
docker compose build --no-cache --pull
```

After that build succeeds, stop production and swap the two directories using
same-filesystem atomic renames. The old source remains intact under its
timestamped name:

```bash
cd /root
docker compose -f /root/security-search-update/docker-compose.yml \
  down --remove-orphans
mv /root/security-search-update "$old_tree"
mv /root/security-search-v0.9.11 /root/security-search-update

cd /root/security-search-update
docker compose up -d --no-build
docker compose ps

health_attempt=0
while [ "$health_attempt" -lt 12 ]; do
  health_status=$(docker inspect --format '{{.State.Health.Status}}' \
    security-search 2>/dev/null || true)
  test "$health_status" = healthy && break
  health_attempt=$((health_attempt + 1))
  sleep 5
done
test "$(docker inspect --format '{{.State.Health.Status}}' security-search)" = healthy
```

The exact `.tgz` and the timestamped old directory are both retained through
verification. This clean-tree cutover intentionally does not unpack release
files over the previous source tree.

## Production verification

Repeat the result-bearing, theme, Settings, API, and log checks from the VPS
against the effective private Compose endpoint, then verify the public endpoint:

```bash
docker compose ps
docker compose logs --tail=200 security-search
app_endpoint=$(docker compose port security-search 80 | tail -n 1)
curl -fsSI "http://$app_endpoint/"
curl -fsSI https://securityops.co/
curl -fsSI https://securityops.com.br/
```

For a forced or naturally occurring Google unusual-traffic response, verify all
of the following instead of repeatedly querying Google:

- the page says Google temporarily rate-limited the instance;
- the heading is **Search provider unavailable**;
- no automatic second-provider request occurs;
- **Try Brave** preserves the query/filters and includes `scraper=brave`;
- **Retry search** retains Google;
- logs contain no PHP warning, deprecation, or fatal error.

Google's anti-abuse response is not a successful Google smoke test. Promote only
with an accurate report of which provider paths returned actual results.

## Rollback

If a required check fails, retain the failed tree for inspection, restore the
timestamped old directory, and retag the preserved image:

```bash
cd /root
docker compose -f /root/security-search-update/docker-compose.yml down
mv /root/security-search-update \
  /root/security-search-update-failed-v0.9.11
mv /root/security-search-update-old-YYYYMMDDTHHMMSSZ \
  /root/security-search-update
docker image tag security-search:pre-v0.9.11 security-search:latest
cd /root/security-search-update
docker compose up -d --no-build
docker compose ps
```

Replace the timestamp placeholder with the exact value recorded during
cutover. If that old directory is unavailable, extract the exact predeploy
`.tgz` into `/root` only after confirming that
`/root/security-search-update` does not exist.

After health, result-bearing local checks, both public domains, NPM limits, and
logs pass, remove the timestamped old directory and the
`security-search:pre-v0.9.11` image tag:

```bash
test -n "${old_tree:-}"
case "$old_tree" in
  /root/security-search-update-old-*) ;;
  *) exit 1 ;;
esac
test -d "$old_tree"
test -s "$rollback_archive"
rm -rf -- "$old_tree"
docker image rm security-search:pre-v0.9.11
test -s "$rollback_archive"
```

If the Evelin shell was restarted, assign `old_tree` and `rollback_archive` to
the exact recorded paths before running the guarded cleanup. Retain that
predeploy `.tgz` as the single rollback archive for this release. Report the
commit, tag, artifact checksum, provider results, public health, cleanup, and
rollback path only after each item has been verified.
