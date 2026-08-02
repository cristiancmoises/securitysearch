#!/bin/sh
# deploy.sh — Build and (re)launch Security Search.
#
# Usage:
#   ./deploy.sh             # normal redeploy (incremental build)
#   ./deploy.sh --fresh     # full rebuild, no docker layer cache
#   ./deploy.sh --logs      # follow container logs after starting
#
# Run this on the HOST that builds/runs the container — your VPS, normally.

set -eu

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
cd "$SCRIPT_DIR"

FRESH=0
FOLLOW_LOGS=0
for arg in "$@"; do
    case "$arg" in
        --fresh) FRESH=1 ;;
        --logs)  FOLLOW_LOGS=1 ;;
        --help|-h)
            sed -n '2,12p' "$0" | sed 's/^# \?//'
            exit 0 ;;
        *) echo "Unknown arg: $arg"; exit 1 ;;
    esac
done

echo "==> Security Search deploy from: $SCRIPT_DIR"

# ---- 1. Sanity checks ----------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
    echo "ERROR: docker not found in PATH"; exit 1
fi
if ! docker compose version >/dev/null 2>&1; then
    echo "ERROR: 'docker compose' plugin not installed"; exit 1
fi
if [ ! -f docker-compose.yml ]; then
    echo "ERROR: docker-compose.yml missing — are you in the right directory?"
    exit 1
fi
if [ ! -f Dockerfile ]; then
    echo "ERROR: Dockerfile missing"
    exit 1
fi

# ---- 2. Pre-deploy backup ------------------------------------------------
TS=$(date +%F-%H%M)
PARENT=$(cd .. && pwd)
PROJECT=$(basename "$SCRIPT_DIR")
BACKUP="$PARENT/sec-search-backup-$TS.tgz"

echo "==> Creating backup: $BACKUP"
tar --exclude="./.git" \
    --exclude="./icons/*" \
    --exclude="./*.tgz" \
    -czf "$BACKUP" -C "$PARENT" "$PROJECT" 2>/dev/null || {
    echo "WARN: backup failed — continuing anyway"
}
ls -lh "$BACKUP" 2>/dev/null || true

# ---- 3. Stop the existing container --------------------------------------
echo "==> Stopping existing container (if running)"
docker compose down --remove-orphans || true

# ---- 4. Build ------------------------------------------------------------
if [ "$FRESH" = "1" ]; then
    echo "==> Full rebuild (--no-cache)"
    docker compose build --no-cache --pull
else
    echo "==> Incremental build"
    docker compose build --pull
fi

# ---- 5. Launch -----------------------------------------------------------
echo "==> Starting container"
docker compose up -d

# ---- 6. Wait for healthy state ------------------------------------------
echo "==> Waiting for healthcheck (up to 60s)"
for i in $(seq 1 12); do
    sleep 5
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' security-search 2>/dev/null || echo "unknown")
    case "$STATUS" in
        healthy)
            echo "==> Container is healthy ✓"
            break
            ;;
        unhealthy)
            echo "ERROR: Container reported unhealthy"
            docker compose logs --tail=50 security-search
            exit 1
            ;;
        *)
            echo "  ... still $STATUS ($((i*5))s)"
            ;;
    esac
done

# ---- 7. Smoke test the app ----------------------------------------------
echo "==> Smoke test: GET http://127.0.0.1:5140/"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:5140/ || echo "000")
case "$HTTP_CODE" in
    200) echo "==> HTTP 200 ✓" ;;
    *)   echo "WARN: got HTTP $HTTP_CODE — check logs" ;;
esac

echo
echo "============================================"
echo " Security Search deployed."
echo " Container:    security-search"
echo " Local URL:    http://127.0.0.1:5140/"
echo " Backup at:    $BACKUP"
echo "============================================"
echo
echo " Useful commands:"
echo "   docker compose logs -f security-search    # tail logs"
echo "   docker compose ps                         # status"
echo "   docker compose restart                    # reload"
echo "   docker compose down                       # stop"
echo "   ./deploy.sh --fresh                       # full rebuild"
echo

if [ "$FOLLOW_LOGS" = "1" ]; then
    docker compose logs -f --tail=50 security-search
fi
