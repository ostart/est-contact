#!/usr/bin/env bash
# Скрипт для cron: запуск Laravel планировщика.
# Использование в cron: * * * * * /home/o/ostart/est-contact/scripts/cron-schedule.sh
#
# Скрипт автоматически определяет путь к проекту и запускает schedule:run.

set -e
cd "$(dirname "$0")/.."
/opt/php8.3/bin/php artisan schedule:run >> storage/logs/cron-schedule.log 2>&1
