# Where to build

**Short answer: build on the VPS.** Build locally only for testing or when you
intend to transfer an image with Evelin; a local image does not appear on the
IONOS host by itself.

---

## Recommended workflow

### From a workstation, upload a release and build on the VPS

```bash
# Create a source release from a clean, committed revision.
./release.sh 0.9.2

# Upload with the approved Evelin profile.
ev --config ~/.evelin/client.toml cp \
  dist/securitysearch-v0.9.2.tar.gz \
  remote:/tmp/securitysearch-v0.9.2.tar.gz

# Open the IONOS VPS shell, unpack and deploy there.
ev --config ~/.evelin/client.toml shell
mkdir -p /opt/securitysearch/releases
tar -xzf /tmp/securitysearch-v0.9.2.tar.gz -C /opt/securitysearch/releases
cd /opt/securitysearch/releases/securitysearch-v0.9.2
./deploy.sh --fresh
```

This is the simplest setup. The VPS pulls Alpine packages directly from
their CDN, bypassing whatever connectivity issues your home network or
Mint's docker daemon might have.

---

## If you really want to build locally and ship the image

```bash
# On the workstation
cd /path/to/securitysearch-v0.9.2
docker compose build

# Save the built image to a tarball
docker save security-search:latest | gzip > security-search-image.tar.gz

# Transfer to the IONOS VPS
ev --config ~/.evelin/client.toml cp \
    security-search-image.tar.gz \
    remote:/tmp/security-search-image.tar.gz

# On the IONOS VPS — load and run
ev --config ~/.evelin/client.toml shell
docker load < /tmp/security-search-image.tar.gz
cd /opt/securitysearch/releases/securitysearch-v0.9.2
docker compose up -d              # uses the loaded image, doesn't rebuild
```

The image is ~150-200 MB compressed. Slower than rsync + remote build for
most VPS connections.

---

## Local-only testing on Mint (without touching the VPS)

```bash
cd /path/to/securitysearch-v0.9.2
docker compose up -d
curl -I http://127.0.0.1:5140/
```

If that works, the build is good. Tear down with `docker compose down`
before deploying for real.

---

## Why I'm not panicking about the apk error you saw

The error you saw was `apk update` getting `temporary error (try again later)`
from `dl-cdn.alpinelinux.org`. The Dockerfile in this bundle now tries 5
different mirrors automatically before failing — that error should not
recur even if your home connection has flaky routing to one of them.

The `pull access denied for security-search` message that came first is
**harmless** and not actually an error. Compose always checks the registry
first, fails (because we're not pulling, we're building), then falls back
to local build. You'll see this message every time you run
`docker compose up` on a project that uses `build:` instead of `image:` from
a registry. Ignore it.
