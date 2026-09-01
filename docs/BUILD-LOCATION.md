# Where to build

Build and test locally first, then perform the release deployment build on the
IONOS VPS. A local Docker image does not appear on the VPS merely because the
source was committed or pushed.

## Current production workflow

The active IONOS source tree is `/root/security-search-update`. v0.9.4 uses a
source artifact and a no-cache build on that host:

```bash
# Workstation: after tests and commit.
./release.sh 0.9.4
(cd dist && sha256sum -c securitysearch-v0.9.4.tar.gz.sha256)

ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.4.tar.gz \
  remote:/tmp/securitysearch-v0.9.4.tar.gz
ev --config /home/berkeley/.evelin/client.toml cp \
  dist/securitysearch-v0.9.4.tar.gz.sha256 \
  remote:/tmp/securitysearch-v0.9.4.tar.gz.sha256
ev --config /home/berkeley/.evelin/client.toml shell
```

In the Evelin shell, verify the package, create a clean sibling, and archive the
exact active tree before changing it:

```bash
cd /tmp
sha256sum -c securitysearch-v0.9.4.tar.gz.sha256

rollback_stamp=$(date -u +%Y%m%dT%H%M%SZ)
rollback_archive=/root/security-search-pre-v0.9.4-${rollback_stamp}.tgz
old_tree=/root/security-search-update-old-${rollback_stamp}
release_tree=/root/security-search-v0.9.4
test -d /root/security-search-update
test ! -e "$old_tree"
test ! -e "$release_tree"

umask 077
tar -czf "$rollback_archive" -C /root security-search-update
test -s "$rollback_archive"
chmod 600 "$rollback_archive"

install -d -m 0750 "$release_tree"
tar -xzf securitysearch-v0.9.4.tar.gz \
  --strip-components=1 \
  -C "$release_tree"
test -f "$release_tree/docker-compose.yml"
test -f "$release_tree/Dockerfile"
```

Inventory runtime-only files before the build and write down an explicit
allowlist. Do not copy the old tree, generated `data/config.php`, `.git`, cache,
or all of `data/` into the sibling. The current production review found no
Google API key files, so there is no `data/api_keys/google_api.txt` to preserve.
If a private Compose override, environment file, proxy credential file, or
other runtime secret is actually found, copy only that exact reviewed path into
the sibling with restrictive permissions.

Keep the old container serving while the clean sibling builds. Preserve the
old image under a rollback tag before the candidate takes the `latest` tag:

```bash
previous_image_id=$(docker image inspect --format '{{.Id}}' security-search:latest)
test -n "$previous_image_id"
docker image tag "$previous_image_id" security-search:pre-v0.9.4

cd /root/security-search-v0.9.4
umask 077
printf 'SECURITYSEARCH_BIND_ADDRESS=172.17.0.1\n' > .env
chmod 600 .env
docker compose build --no-cache --pull
```

Only after the clean build succeeds, stop production and rename both sibling
directories on the same filesystem. Each `mv` is an atomic rename; no release
files are overlaid into the old tree:

```bash
cd /root
docker compose -f /root/security-search-update/docker-compose.yml \
  down --remove-orphans
mv /root/security-search-update "$old_tree"
mv /root/security-search-v0.9.4 /root/security-search-update

cd /root/security-search-update
docker compose up -d --no-build
docker compose ps
```

Keep both the timestamped old directory and exact `.tgz` until the local and
public result-bearing checks in [RELEASE.md](RELEASE.md) pass. Then remove the
old directory and rollback image tag only; retain the `.tgz` as the one
rollback archive for this release.

## Why the VPS rebuilds

- The deployed container runs on the VPS's kernel and Docker daemon.
- The VPS validates outbound access from the same address Google and Brave see.
- Remote building avoids transferring a large local image.
- The sibling's no-cache build prevents a stale Docker layer or v10 CSS
  response from hiding the SecOps v11 cache-busting and provider changes.

The Dockerfile tries multiple Alpine mirrors, which reduces sensitivity to a
single CDN route. Mirror failover does not fix general host DNS or connectivity
problems; inspect the exact build failure if all mirrors fail.

## Local candidate testing

```bash
cd /path/to/securitysearch
docker compose build --no-cache
docker compose up -d
docker compose ps
curl -fsSI http://127.0.0.1:5140/
```

Use the result-bearing and theme checks in [RELEASE.md](RELEASE.md). A healthy
container and HTTP 200 home page prove only that the application started; they
do not prove that an upstream scraper returned results.

Tear down the local candidate after testing:

```bash
docker compose down
```

## Optional image-transfer workflow

If the VPS temporarily cannot build but can run the local target architecture,
save and upload the tested image:

```bash
docker image tag security-search:latest security-search:v0.9.4
docker save security-search:v0.9.4 | gzip > security-search-v0.9.4-image.tar.gz
ev --config /home/berkeley/.evelin/client.toml cp \
  security-search-v0.9.4-image.tar.gz \
  remote:/tmp/security-search-v0.9.4-image.tar.gz
```

Then load it in the Evelin shell. It replaces only the clean sibling build step
above; preserve the old image tag first and perform the same directory cutover:

```bash
docker load < /tmp/security-search-v0.9.4-image.tar.gz
docker image tag security-search:v0.9.4 security-search:latest
```

This alternative must use a compatible architecture and does not replace the
clean source sibling, checksum, Git tag, exact backup, atomic renames, or
production smoke tests.
