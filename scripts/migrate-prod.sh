#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SSH_HOST="${SSH:-hetzner-root}"
PROD_DB_IMAGE="${PROD_DB_IMAGE:-mysql:8}"
BACKUP_DIR="${BACKUP_DIR:-storage/app/db-backups}"

SKIP_BACKUP=0
PRETEND=0

for arg in "$@"; do
  case "$arg" in
    --no-backup) SKIP_BACKUP=1 ;;
    --pretend) PRETEND=1 ;;
    *)
      echo "ERROR: unknown option '${arg}'. Supported: --no-backup, --pretend." >&2
      exit 1
      ;;
  esac
done

echo "==> [1/4] Migration status (before)"
scripts/prod-artisan.sh migrate:status --pending || true

if [[ "$SKIP_BACKUP" -eq 0 ]]; then
  ts="$(date +%Y%m%d_%H%M%S)"
  mkdir -p "$BACKUP_DIR"
  prod_dump="${BACKUP_DIR}/prod_premigrate_${ts}.sql"

  echo "==> [2/4] Backing up production database -> ${prod_dump}"
  remote_cmd="CID=\$(docker ps -q -f ancestor=${PROD_DB_IMAGE} | head -1); test -n \"\$CID\"; docker exec \"\$CID\" sh -c 'exec mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 --hex-blob \"\$MYSQL_DATABASE\"'"
  ssh "$SSH_HOST" "$remote_cmd" > "$prod_dump"

  if ! grep -q 'Dump completed' "$prod_dump"; then
    echo "ERROR: backup looks incomplete (missing 'Dump completed'):" >&2
    tail -n 5 "$prod_dump" >&2 || true
    exit 1
  fi

  echo "    $(wc -c < "$prod_dump" | tr -d ' ') bytes"
else
  echo "==> [2/4] Skipping backup (--no-backup)"
fi

if [[ "$PRETEND" -eq 1 ]]; then
  echo "==> [3/4] Migrations that would run"
  scripts/prod-artisan.sh migrate --pretend --force
  echo "==> [4/4] Dry run — nothing applied."
  exit 0
fi

echo "==> [3/4] Running migrations"
scripts/prod-artisan.sh migrate --force

echo "==> [4/4] Migration status (after)"
status="$(scripts/prod-artisan.sh migrate:status --pending)"
echo "$status"

if grep -q 'Pending' <<<"$status"; then
  echo "ERROR: migrations are still pending after 'migrate --force'." >&2
  exit 1
fi

echo "==> Done."
