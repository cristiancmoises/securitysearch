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
TS=$(date -u +%Y%m%dT%H%M%SZ)
PARENT=$(cd .. && pwd)
PROJECT=$(basename "$SCRIPT_DIR")
BACKUP="$PARENT/sec-search-backup-$TS.tgz"

echo "==> Creating backup: $BACKUP"
umask 077
tar --exclude="$PROJECT/.git" \
    --exclude="$PROJECT/icons/*" \
    --exclude="$PROJECT/dist/*" \
    --exclude="$PROJECT/*.tgz" \
    -czf "$BACKUP" -C "$PARENT" "$PROJECT"
chmod 600 "$BACKUP"
ls -lh "$BACKUP"

OLD_IMAGE=$(docker image inspect --format '{{.Id}}' security-search:latest 2>/dev/null || true)
DEPLOY_SUCCEEDED=0
CUTOVER_STARTED=0
rollback_on_failure() {
    code=$?
    if [ "$DEPLOY_SUCCEEDED" = "1" ]; then
        return
    fi

    set +e
    if [ "$CUTOVER_STARTED" = "1" ]; then
        echo "ERROR: deployment failed; attempting container-image rollback" >&2
        docker compose down --remove-orphans >/dev/null 2>&1
        if [ -n "$OLD_IMAGE" ]; then
            if docker image tag "$OLD_IMAGE" security-search:latest &&
               docker compose up -d &&
               test "$(docker inspect --format='{{.State.Running}}' security-search 2>/dev/null)" = true; then
                echo "Rollback image restored and container started. Source backup: $BACKUP" >&2
            else
                echo "CRITICAL: automatic image rollback failed. Restore manually from: $BACKUP" >&2
            fi
        else
            echo "No previous image was available. Restore from: $BACKUP" >&2
        fi
    else
        # A failed pre-cutover build must not interrupt the running service.
        if [ -n "$OLD_IMAGE" ]; then
            docker image tag "$OLD_IMAGE" security-search:latest
        fi
        echo "Build failed before cutover; the existing container was left running." >&2
        echo "Source backup: $BACKUP" >&2
    fi
    exit "$code"
}
trap rollback_on_failure EXIT

# ---- 3. Build while the existing container remains online ----------------
if [ "$FRESH" = "1" ]; then
    echo "==> Full rebuild (--no-cache)"
    docker compose build --no-cache --pull
else
    echo "==> Incremental build"
    docker compose build --pull
fi

# ---- 4. Cut over only after a successful build ---------------------------
echo "==> Stopping existing container (if running)"
CUTOVER_STARTED=1
docker compose down --remove-orphans

# ---- 5. Launch -----------------------------------------------------------
echo "==> Starting container"
docker compose up -d

# ---- 6. Wait for healthy state ------------------------------------------
echo "==> Waiting for healthcheck (up to 60s)"
HEALTHY=0
for i in $(seq 1 12); do
    sleep 5
    STATUS=$(docker inspect --format='{{.State.Health.Status}}' security-search 2>/dev/null || echo "unknown")
    case "$STATUS" in
        healthy)
            echo "==> Container is healthy ✓"
            HEALTHY=1
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
if [ "$HEALTHY" != "1" ]; then
    echo "ERROR: healthcheck did not become healthy within 60 seconds"
    docker compose logs --tail=50 security-search
    exit 1
fi

# ---- 7. Smoke test the app ----------------------------------------------
PUBLISHED_ENDPOINT=$(docker compose port security-search 80 | tail -n 1)
if [ -z "$PUBLISHED_ENDPOINT" ]; then
    echo "ERROR: compose did not report a published HTTP endpoint"; exit 1
fi
echo "==> Smoke test: GET http://$PUBLISHED_ENDPOINT/"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://$PUBLISHED_ENDPOINT/" || true)
case "$HTTP_CODE" in
    200) echo "==> HTTP 200 ✓" ;;
    *)   echo "ERROR: got HTTP ${HTTP_CODE:-000}"; exit 1 ;;
esac

DEPLOY_SUCCEEDED=1
trap - EXIT

echo
echo "============================================"
echo " Security Search deployed."
echo " Container:    security-search"
echo " HTTP endpoint: http://$PUBLISHED_ENDPOINT/"
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
