#!/usr/bin/env bash
# Скрипт для cron: запуск Laravel планировщика.
# Использование в cron: * * * * * /home/o/ostart/est-contact/scripts/cron-schedule.sh
#
# Скрипт автоматически определяет путь к проекту и запускает schedule:run.

set -e
cd "$(dirname "$0")/.."
php artisan schedule:run >> /dev/null 2>&1
