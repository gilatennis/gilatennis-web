#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SSH_HOST="${SSH:-hetzner-root}"
PROD_APP_IMAGE="${PROD_APP_IMAGE:-kevinjanada/gilatennis_web:latest}"
BACKUP_DIR="${BACKUP_DIR:-storage/app/db-backups}"
LOCAL_STORAGE_DIR="${LOCAL_STORAGE_DIR:-storage/app/public}"
REMOTE_STORAGE_DIR="${REMOTE_STORAGE_DIR:-/app/storage/app/public}"

ts="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR" "$LOCAL_STORAGE_DIR"
backup="${BACKUP_DIR}/local_storage_${ts}.tgz"

echo "==> [1/3] Backing up local uploads -> ${backup}"
tar -C "$LOCAL_STORAGE_DIR" -czf "$backup" .

echo "==> [2/3] Pulling production uploads from ${SSH_HOST} (${REMOTE_STORAGE_DIR})"
remote_cmd="CID=\$(docker ps -q -f ancestor=${PROD_APP_IMAGE} | head -1); test -n \"\$CID\"; docker exec \"\$CID\" tar -C ${REMOTE_STORAGE_DIR} -cf - ."
ssh "$SSH_HOST" "$remote_cmd" | tar -C "$LOCAL_STORAGE_DIR" -xf -

echo "==> [3/3] Verifying"
echo "    local files: $(find "$LOCAL_STORAGE_DIR" -type f | wc -l | tr -d ' ')"
echo "==> Done. Local backup: ${backup}"
