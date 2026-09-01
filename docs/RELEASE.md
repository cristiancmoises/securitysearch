# Release and deployment

## Build a source release

From a clean, committed working tree:

```bash
./release.sh 0.9.1
git tag -a v0.9.1 -m "Security Search v0.9.1"
```

This produces:

```text
dist/securitysearch-v0.9.1.tar.gz
dist/securitysearch-v0.9.1.tar.gz.sha256
```

The archive is created with `git archive`, so it contains only committed source
and respects `.gitattributes`; stale zip bundles and backup files are excluded.

## Deploy on the IONOS VPS with Evelin

Use the configured Evelin profile rather than copying credentials into scripts:

```bash
# Local: upload the verified release.
ev --config ~/.evelin/client.toml cp \
  dist/securitysearch-v0.9.1.tar.gz \
  remote:/tmp/securitysearch-v0.9.1.tar.gz

# Open the approved remote shell, then run the following on the VPS.
ev --config ~/.evelin/client.toml shell
sha256sum /tmp/securitysearch-v0.9.1.tar.gz
mkdir -p /opt/securitysearch/releases
tar -xzf /tmp/securitysearch-v0.9.1.tar.gz -C /opt/securitysearch/releases
cd /opt/securitysearch/releases/securitysearch-v0.9.1
./deploy.sh --fresh
```

The deploy script creates a timestamped backup, stops the existing
`security-search` container, builds the replacement, waits for Docker's health
check, and performs a local HTTP smoke test. Keep the previous release until
the checks below succeed.

## Verify

```bash
docker compose ps
curl -fsSI http://127.0.0.1:5140/
curl -fsS 'http://127.0.0.1:5140/web?s=privacy&scraper=google' >/dev/null
curl -fsS 'http://127.0.0.1:5140/images?s=security&scraper=google' >/dev/null
curl -fsS 'http://127.0.0.1:5140/web?s=privacy&scraper=brave' >/dev/null
curl -fsS 'http://127.0.0.1:5140/images?s=security&scraper=brave' >/dev/null
```

If any check fails, use the backup tarball printed by `deploy.sh` to restore the
previous release, then investigate `docker compose logs --tail=100
security-search`.
