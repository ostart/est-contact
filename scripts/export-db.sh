#!/usr/bin/env bash
# Экспорт MySQL в SQL-дамп (бэкап или для переноса на TimeWeb).
# Запуск из корня проекта. Требует .env с DB_* или передачу параметров.
#
# Использование:
#   ./scripts/export-db.sh              # читает DB_* из .env
#   ./scripts/export-db.sh dump.sql     # свой путь к файлу дампа

set -e
cd "$(dirname "$0")/.."

DUMP_FILE="${1:-dump_$(date +%Y%m%d_%H%M%S).sql}"

if [ -f .env ]; then
  export $(grep -v '^#' .env | xargs)
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
  echo "Задайте DB_DATABASE и DB_USERNAME в .env или переменных окружения."
  exit 1
fi

echo "==> Экспорт: $DB_DATABASE -> $DUMP_FILE"
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
  ${DB_PASSWORD:+-p"$DB_PASSWORD"} \
  --single-transaction --routines --triggers \
  "$DB_DATABASE" > "$DUMP_FILE"

echo "==> Готово: $DUMP_FILE"
echo "    Импорт на сервере: mysql -u USER -p DATABASE < $DUMP_FILE"
