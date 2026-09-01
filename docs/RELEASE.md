# Release and deployment

These instructions prepare and deploy v0.9.6. They do not imply that the tag,
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
  securitysearch.zip \
  docs/GOD_TIER_SEARCH_ENGINE_PROMPT.md
do
  test -z "$(git log --all --format= --name-only -- "$removed_path")" || {
    echo "removed path remains in reachable history: $removed_path" >&2
    exit 1
  }
done
```

An ordinary deletion commit does not satisfy this check. If a stale clone has
reintroduced removed objects, stop and repeat the coordinated history-cleaning
procedure before publication; do not silently force-push from that clone.

Then, from a clean working tree:

```bash
git diff --check
git status --short
./release.sh 0.9.6
(cd dist && sha256sum -c securitysearch-v0.9.6.tar.gz.sha256)
git tag -a v0.9.6 -m "Security Search v0.9.6"
```

The release helper uses `git archive`, respects `.gitattributes`, and adds only
the required empty `icons/` runtime-cache directory that Git cannot track:

```text
dist/securitysearch-v0.9.6.tar.gz
dist/securitysearch-v0.9.6.tar.gz.sha256
```

Inspect the archive before publishing:

```bash
tar -tzf dist/securitysearch-v0.9.6.tar.gz | head
tar -tzf dist/securitysearch-v0.9.6.tar.gz |
  grep -Fxq 'securitysearch-v0.9.6/icons/'
tar -tzf dist/securitysearch-v0.9.6.tar.gz |
  grep -Fxq 'securitysearch-v0.9.6/banner/securitysearch.webp'
tar -tzf dist/securitysearch-v0.9.6.tar.gz |
  grep -Fxq 'securitysearch-v0.9.6/static/misc/secops.gif'
if tar -tzf dist/securitysearch-v0.9.6.tar.gz |
   grep -Ei '(\.bak($|\.)|data/api_keys/|^securitysearch-v0\.9\.6/dist/|prompt.*\.md|securitysearch\.zip|Kuruminha\.css|mimi\.jpg)'; then
  echo "unexpected release content"
  exit 1
fi
```

The required-directory, logo, and SecOps-background checks must succeed, and the
forbidden-content check must print nothing. The package must contain no API
keys, proxy credentials, cookies, or Evelin/SSH material.

## Publish to Git remotes

List the configured remotes and confirm their targets:

```bash
git remote -v
```

This checkout currently has four intended publication remotes. These URLs do
not embed credentials:

- `origin` → `git@github.com:cristiancmoises/securitysearch.git`
- `codeberg` → `git@codeberg.org:berkeley/securitysearch.git`
- `securityops` → `https://git.securityops.co/cristiancmoises/securitysearch.git`
- `securityops_br` → `https://git.securityops.com.br/cristiancmoises/securitysearch.git`

The remote inventory must contain only `main` and release tags v0.9.0 through
v0.9.6. Capture the exact current remote object IDs immediately before each
push and use an explicit lease for every ref. A lease mismatch means someone
updated that remote; stop and investigate instead of overwriting their work.
The following Bash block publishes the branch and complete tag inventory
atomically while also asserting that a previously absent ref is still absent:

```bash
set -euo pipefail
release_tags=(v0.9.0 v0.9.1 v0.9.2 v0.9.3 v0.9.4 v0.9.5 v0.9.6)
expected_refs=(refs/heads/main)
for tag in "${release_tags[@]}"; do
  expected_refs+=("refs/tags/${tag}")
done
expected_ref_inventory=$(printf '%s\n' "${expected_refs[@]}" | sort -u)

for remote in origin codeberg securityops securityops_br; do
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
set -euo pipefail
release_refs=(
  refs/heads/main
  refs/tags/v0.9.0
  refs/tags/v0.9.1
  refs/tags/v0.9.2
  refs/tags/v0.9.3
  refs/tags/v0.9.4
  refs/tags/v0.9.5
  refs/tags/v0.9.6
)
for remote in origin codeberg securityops securityops_br; do
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
done
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

Verify the SecOps cascade and cache version:

```bash
home=$(curl -fsS http://127.0.0.1:5140/)
printf '%s' "$home" | grep -q '/static/themes/SecOps.css?v12'
curl -fsSI http://127.0.0.1:5140/static/themes/SecOps.css?v12 |
  grep -qi '^Content-Type: text/css'
curl -fsSI http://127.0.0.1:5140/static/misc/secops.gif |
  grep -qi '^Content-Type: image/gif'
curl -fsS http://127.0.0.1:5140/static/themes/SecOps.css?v12 |
  grep -Fq 'url("/static/misc/secops.gif?v12")'
docker compose exec -T security-search php -r \
  'include "/var/www/html/4get/data/config.php"; exit(config::DEFAULT_NSFW === "yes" ? 0 : 1);'

invalid_theme=$(curl -fsS -H 'Cookie: theme=missing-theme' \
  http://127.0.0.1:5140/)
printf '%s' "$invalid_theme" |
  grep -q '/static/themes/SecOps.css?v12'
```

Complete the visual, cookie, keyboard, narrow-screen, error-action, and
no-JavaScript checks in [UI.md](UI.md). Include animated and false-positive
fixtures. Verify poster-first loading, same-origin `/proxy?...&s=animated`, the
20 MB cap, MIME validation, Imagick frame counting for GIF/WebP, and strict PNG
chunk/`acTL` frame validation for APNG—including an APNG that Alpine Imagick
reports as one frame. Confirm PNG/APNG CRC and ordering checks,
`acTL`/`fcTL`/`fdAT` sequence/count/data checks, canvas bounds, and rejection of
a forged acTL-only static PNG. Verify poster fallback, three/two concurrent-load
queues, all visible validated animations playing without a click, and queue
advancement as off-screen cards restore. For both automatic and deliberate requests, verify poster
settling; require the two-animation-frame/one-paint gate only after a successful
poster, and verify that a broken poster may proceed once settled. Verify that
user intent upgrades pending automatic prep without duplication, pointer/focus
rechecks viewport geometry, and off-screen restoration cancels pending work.
Cover reduced-motion/data-saver behavior, infinite-append registration,
full-size-original selection, generic-WebP probing/static fallback, and data-URL exclusion. Include recognized
explicit APNG/animated-PNG filenames ending in `.png`, Google/Brave MIME or
format metadata identifying extensionless originals, conservative misses when
no hint exists, rejected static WebP, and an unsupported
raster case. Verify that the hint-labelled motion badge appears only after
multi-frame validation. Confirm there is no direct result-host image request.

Exercise rejection fixtures above 20 MB, 1,000 frames, 16,384 pixels on either
dimension, 40 megapixels per frame, 250 million decoded pixel-frames, and 8,192
PNG chunks. Include a compressed response whose decompressed
write-callback output exceeds 20 MB even when progress/content length would not
prove that; it must be rejected. Snapshot the Imagick memory, map, disk, file,
thread, time, width, height, and list-length resource limits and prove they are
restored after successful and failed inspection. Confirm the app returns 429
after 120 animated candidate admissions/client/minute and admits no more than
three generation-tagged validations globally in flight. Verify port 5140 is
loopback/private and unreachable on the public interface. On the VPS, validate the live NPM configuration (adjust the
container name only if the installation uses a different one):

```bash
npm_config=$(docker exec nginx-proxy-manager nginx -T 2>&1)
printf '%s' "$npm_config" |
  grep -F 'map $arg_s $securitysearch_media_key {'
printf '%s' "$npm_config" | grep -F 'default  "";'
printf '%s' "$npm_config" | grep -F 'animated $binary_remote_addr;'
printf '%s' "$npm_config" |
  grep -F 'limit_req_zone $securitysearch_media_key zone=media:10m rate=60r/m;'
printf '%s' "$npm_config" |
  grep -F 'limit_req_zone $binary_remote_addr zone=proxy:10m rate=600r/m;'
printf '%s' "$npm_config" |
  grep -F 'limit_req zone=media burst=12 nodelay;'
printf '%s' "$npm_config" |
  grep -F 'limit_req zone=proxy burst=40 nodelay;'
```

The map's empty default means ordinary thumbnail requests do not consume the
media zone; only raw `s=animated` is keyed by client address. The all-proxy zone
still covers encoded variants, while the application applies its stricter bound
to PHP's decoded value. The zone lines prove both budgets exist in NPM's global
`http{}` scope, and the location lines prove public `/proxy` applies them. A checked-in sample
configuration is not evidence that the live proxy loaded these directives.

## Deploy on the current IONOS VPS

The active production tree is `/root/security-search-update`; it is not the
optional `/opt/securitysearch/releases` layout used in older documentation.
Use the approved Evelin profile and upload both files:

```bash
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.6.tar.gz \
  remote:/srv/evelin/securitysearch-v0.9.6.tar.gz
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.6.tar.gz.sha256 \
  remote:/srv/evelin/securitysearch-v0.9.6.tar.gz.sha256
ev --config /home/berkeley/.evelin/client.toml shell
```

In the Evelin shell, verify the artifact, prepare a clean sibling, and make an
exact rollback archive before changing the active tree:

```bash
cd /srv/evelin
sha256sum -c securitysearch-v0.9.6.tar.gz.sha256

rollback_stamp=$(date -u +%Y%m%dT%H%M%SZ)
rollback_archive=/root/security-search-pre-v0.9.6-${rollback_stamp}.tgz
old_tree=/root/security-search-update-old-${rollback_stamp}
release_tree=/root/security-search-v0.9.6
test -d /root/security-search-update
test ! -e "$old_tree"
test ! -e "$release_tree"

umask 077
tar -czf "$rollback_archive" -C /root security-search-update
test -s "$rollback_archive"
chmod 600 "$rollback_archive"

install -d -m 0750 "$release_tree"
tar -xzf /srv/evelin/securitysearch-v0.9.6.tar.gz \
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
docker image tag "$previous_image_id" security-search:pre-v0.9.6

cd /root/security-search-v0.9.6
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
mv /root/security-search-v0.9.6 /root/security-search-update

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
  /root/security-search-update-failed-v0.9.6
mv /root/security-search-update-old-YYYYMMDDTHHMMSSZ \
  /root/security-search-update
docker image tag security-search:pre-v0.9.6 security-search:latest
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
`security-search:pre-v0.9.6` image tag:

```bash
test -n "${old_tree:-}"
case "$old_tree" in
  /root/security-search-update-old-*) ;;
  *) exit 1 ;;
esac
test -d "$old_tree"
test -s "$rollback_archive"
rm -rf -- "$old_tree"
docker image rm security-search:pre-v0.9.6
test -s "$rollback_archive"
```

If the Evelin shell was restarted, assign `old_tree` and `rollback_archive` to
the exact recorded paths before running the guarded cleanup. Retain that
predeploy `.tgz` as the single rollback archive for this release. Report the
commit, tag, artifact checksum, provider results, public health, cleanup, and
rollback path only after each item has been verified.
