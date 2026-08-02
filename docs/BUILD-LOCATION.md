# Where to build

**Short answer: build on the VPS.** Don't build on your Mint laptop unless
you're testing locally — the image you built there can't run on the VPS
without a registry push or `docker save | docker load` transfer.

---

## Recommended workflow

### From Mint, push source to VPS, build there:

```bash
# On Mint (one time)
ssh-copy-id root@your-vps      # if you haven't already

# Push the project
rsync -avz --delete \
    --exclude='.git' \
    --exclude='icons/*' \
    --exclude='*.tgz' \
    ~/Downloads/security-search-update/ \
    root@your-vps:/root/security-search/

# Build & deploy on VPS
ssh root@your-vps
cd /root/security-search
./deploy.sh
```

This is the simplest setup. The VPS pulls Alpine packages directly from
their CDN, bypassing whatever connectivity issues your home network or
Mint's docker daemon might have.

---

## If you really want to build on Mint and ship the image

```bash
# On Mint
cd ~/Downloads/security-search-update
docker compose build

# Save the built image to a tarball
docker save security-search:latest | gzip > security-search-image.tar.gz

# Transfer to VPS
scp security-search-image.tar.gz root@your-vps:/tmp/

# On VPS — load and run
ssh root@your-vps
docker load < /tmp/security-search-image.tar.gz
cd /root/security-search          # must already have docker-compose.yml + Dockerfile
docker compose up -d              # uses the loaded image, doesn't rebuild
```

The image is ~150-200 MB compressed. Slower than rsync + remote build for
most VPS connections.

---

## Local-only testing on Mint (without touching the VPS)

```bash
cd ~/Downloads/security-search-update
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
