#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SSH_HOST="${SSH:-hetzner-root}"
PROD_APP_IMAGE="${PROD_APP_IMAGE:-kevinjanada/gilatennis_web:latest}"
PROD_CONTAINER="${PROD_CONTAINER:-}"
SKIP_HEALTH="${SKIP_HEALTH:-0}"

if [[ "$#" -eq 0 ]]; then
  echo "Usage: $0 <artisan command> [args...]" >&2
  echo "Example: $0 migrate:status --pending" >&2
  exit 1
fi

resolve_container() {
  if [[ -n "$PROD_CONTAINER" ]]; then
    echo "$PROD_CONTAINER"
    return
  fi

  local matches count
  matches="$(ssh "$SSH_HOST" "docker ps -q -f ancestor=${PROD_APP_IMAGE}")"
  count="$(printf '%s\n' "$matches" | grep -c . || true)"

  if [[ "$count" -ne 1 ]]; then
    echo "ERROR: expected exactly 1 running '${PROD_APP_IMAGE}' container, found ${count}." >&2
    echo "If a deploy is in progress, wait for it to settle, or set PROD_CONTAINER=<id>." >&2
    exit 1
  fi

  echo "$matches"
}

CID="$(resolve_container)"

if [[ "$SKIP_HEALTH" -eq 0 ]]; then
  health="$(ssh "$SSH_HOST" "docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' ${CID}")"

  if [[ "$health" != "healthy" ]]; then
    echo "ERROR: container ${CID} is not healthy (status: ${health})." >&2
    echo "Retry once it is healthy, or set SKIP_HEALTH=1 to override." >&2
    exit 1
  fi
fi

cmd="$(printf '%q ' "$@")"

echo "==> ${SSH_HOST}: ${CID} (${PROD_APP_IMAGE})"
echo "==> php artisan ${cmd}"
ssh "$SSH_HOST" "docker exec -i ${CID} php artisan ${cmd}"
