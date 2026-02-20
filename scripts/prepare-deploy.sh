#!/usr/bin/env bash
# Подготовка кода к деплою: установка prod-зависимостей и кэширование.
# Запуск из корня проекта: ./scripts/prepare-deploy.sh

set -e
cd "$(dirname "$0")/.."

echo "==> Composer (production)"
composer install --no-dev --optimize-autoloader

echo "==> Laravel cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Готово. Можно заливать на сервер (кроме .env)."
echo "    На сервере: php artisan migrate --force && php artisan db:seed --force"
