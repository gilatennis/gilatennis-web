#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

SSH_HOST="${SSH:-hetzner-root}"
PROD_DB_IMAGE="${PROD_DB_IMAGE:-mysql:8}"
BACKUP_DIR="${BACKUP_DIR:-storage/app/db-backups}"
LOCAL_DB="${LOCAL_DB:-gilatennis_web}"
LOCAL_DB_HOST="${LOCAL_DB_HOST:-127.0.0.1}"
LOCAL_DB_PORT="${LOCAL_DB_PORT:-3306}"
LOCAL_DB_USER="${LOCAL_DB_USER:-root}"
LOCAL_DB_PASSWORD="${LOCAL_DB_PASSWORD:-}"

if [[ -z "${MYSQL_BIN:-}" ]]; then
  if [[ -x /opt/homebrew/opt/mysql@8.4/bin/mysql ]]; then
    MYSQL_BIN=/opt/homebrew/opt/mysql@8.4/bin/mysql
  else
    MYSQL_BIN=mysql
  fi
fi
if [[ -z "${MYSQLDUMP_BIN:-}" ]]; then
  if [[ -x /opt/homebrew/opt/mysql@8.4/bin/mysqldump ]]; then
    MYSQLDUMP_BIN=/opt/homebrew/opt/mysql@8.4/bin/mysqldump
  else
    MYSQLDUMP_BIN=mysqldump
  fi
fi

if [[ -n "$LOCAL_DB_PASSWORD" ]]; then
  export MYSQL_PWD="$LOCAL_DB_PASSWORD"
fi

local_mysql() {
  "$MYSQL_BIN" -h"$LOCAL_DB_HOST" -P"$LOCAL_DB_PORT" -u"$LOCAL_DB_USER" "$@"
}

ts="$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
local_backup="${BACKUP_DIR}/local_${LOCAL_DB}_${ts}.sql"
prod_dump="${BACKUP_DIR}/prod_gilatennis_${ts}.sql"

echo "==> [1/6] Backing up local database '${LOCAL_DB}' -> ${local_backup}"
"$MYSQLDUMP_BIN" -h"$LOCAL_DB_HOST" -P"$LOCAL_DB_PORT" -u"$LOCAL_DB_USER" \
  --single-transaction --routines --triggers --no-tablespaces \
  --databases "$LOCAL_DB" > "$local_backup"
echo "    $(wc -c < "$local_backup" | tr -d ' ') bytes"

echo "==> [2/6] Dumping production database (server-side via ${SSH_HOST})"
remote_cmd="CID=\$(docker ps -q -f ancestor=${PROD_DB_IMAGE} | head -1); test -n \"\$CID\"; docker exec \"\$CID\" sh -c 'exec mysqldump -uroot -p\"\$MYSQL_ROOT_PASSWORD\" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 --hex-blob \"\$MYSQL_DATABASE\"'"
ssh "$SSH_HOST" "$remote_cmd" > "$prod_dump"

if ! grep -q 'Dump completed' "$prod_dump"; then
  echo "ERROR: production dump looks incomplete (missing 'Dump completed'):" >&2
  tail -n 5 "$prod_dump" >&2 || true
  exit 1
fi
tables="$(grep -c '^CREATE TABLE' "$prod_dump" || true)"
echo "    $(wc -c < "$prod_dump" | tr -d ' ') bytes, ${tables} tables -> ${prod_dump}"

echo "==> [3/6] Dropping and recreating local database '${LOCAL_DB}'"
local_mysql -e "DROP DATABASE IF EXISTS \`${LOCAL_DB}\`; CREATE DATABASE \`${LOCAL_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> [4/6] Importing production dump into '${LOCAL_DB}'"
local_mysql "$LOCAL_DB" < "$prod_dump"

echo "==> [5/6] Running migrations and clearing caches"
php artisan migrate --force
php artisan optimize:clear

echo "==> [6/6] Verifying"
local_tables="$(local_mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${LOCAL_DB}';")"
echo "    local tables: ${local_tables} (production: ${tables})"
echo "    tournament_registrations: $(local_mysql -N -e "SELECT COUNT(*) FROM \`${LOCAL_DB}\`.tournament_registrations;")"

echo "==> Done. Local backup: ${local_backup}"
