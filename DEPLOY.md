# Деплой на TimeWeb

Инструкция по подготовке и выкладке проекта «Есть Контакт» на хостинг TimeWeb.

---

## 1. Подготовка кода к продакшену

### 1.1 Локально (перед загрузкой/пушем)

Либо выполнить вручную:

```bash
# Установить зависимости без dev (для продакшена)
composer install --no-dev --optimize-autoloader

# Кэш конфига и маршрутов (создаётся на сервере после деплоя, но можно проверить локально)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Ключ приложения (если ещё не задан в .env на сервере)
php artisan key:generate --show
```

Либо один скрипт (Linux / Git Bash): `./scripts/prepare-deploy.sh`

### 1.2 Где лежит .env на сервере

Файл `.env` должен находиться в **корне проекта** (рядом с `artisan`, `composer.json`, папками `app/`, `public/` и т.д.), то есть **на уровень выше папки `public`**. Document Root веб-сервера при этом должен указывать на папку `public`, тогда `.env` не будет доступен по HTTP.

### 1.3 Что не попадает на сервер

- Папка `.git` — по желанию (на TimeWeb Cloud часто деплой идёт из Git, тогда нужна).
- Файл `.env` — **не заливать из локальной копии**. На сервере создать свой `.env` в корне проекта (скопировать из `.env.example` и заполнить).
- Папки `node_modules`, `tests`, `storage/logs/*`, `storage/framework/cache/*`, `storage/framework/sessions/*`, `storage/framework/views/*` — не обязательны для работы, если на сервере заново ставите зависимости и крутите Laravel.

### 1.4 Корень сайта на хостинге

В настройках домена/хоста **Document Root** должен указывать на папку **`public`** проекта:

- `public_html` → удалить
- Создать симлинк через SSH-консоль: ln -s ~/est-contact/public ~/est-contact/public_html

Иначе Laravel не найдёт `index.php` и возможны ошибки 403/404.

---

## 2. База данных

### 2.1 Создание БД на TimeWeb

1. Панель управления TimeWeb → «Базы данных» → создать MySQL.
2. Записать: **хост**, **имя БД**, **логин**, **пароль** (и порт, если не 3306).

### 2.2 Переменные в .env на сервере

В `.env` на хостинге задать:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ваш-домен.ru

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=имя_базы
DB_USERNAME=логин_базы
DB_PASSWORD=пароль_базы
```

(Значения подставить из шага 2.1.)

### 2.3 Миграции и сидеры на сервере

Выполнить **на сервере** (SSH или «Выполнить скрипт» в панели TimeWeb), из корня проекта:

```bash
php artisan migrate --force
php artisan db:seed --force
```

- `--force` нужен, т.к. в production Laravel спрашивает подтверждение.
- Перед сидером при необходимости поправьте в `.env` переменные `SEED_ADMIN_*` (админ при первом заполнении БД).
- Настройки почты в БД (таблица `system_settings`) при сидинге заполняются из **MAIL_*** в `.env`. Задайте их до запуска `db:seed`. Если после сидинга почта в БД пустая — выполните **перед** сидингом `php artisan config:clear` (чтобы Laravel заново прочитал `.env`), затем снова `php artisan db:seed --force`. Опционально: `SEED_MAIL_NOTIFICATIONS_ENABLED=1` — включить рассылку при первом сидинге.

### 2.4 Альтернатива: Бэкап и перенос БД (скрипты в репозитории на случай проблем или отсутствия artisan)

- **Экспорт локальной БД** (резервная копия или перенос):
  - Linux / Git Bash: `./scripts/export-db.sh` или `./scripts/export-db.sh mydump.sql`
  - Windows PowerShell: `.\scripts\export-db.ps1` или `.\scripts\export-db.ps1 -DumpFile mydump.sql`  
  Скрипты читают `DB_*` из `.env` в корне проекта.
- **Импорт на сервере**: загрузить дамп в созданную на TimeWeb БД через phpMyAdmin или `mysql -u ... -p имя_базы < dump.sql`.

---

## 3. Почта (отправка писем при деплое на TimeWeb)

Чтобы с сервера уходили письма — **в т.ч. письмо подтверждения email при регистрации** и уведомления (например, о назначении контакта лидеру) — в **`.env` на хостинге** задайте SMTP TimeWeb.

### 3.1 Параметры SMTP TimeWeb

В панели TimeWeb создайте почтовый ящик на вашем домене (например, `noreply@ваш-домен.ru`) или используйте существующий. Затем в `.env` укажите:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.timeweb.ru
MAIL_PORT=2525
MAIL_USERNAME=полный_адрес_ящика@ваш-домен.ru
MAIL_PASSWORD=пароль_от_этого_ящика
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=полный_адрес_ящика@ваш-домен.ru
MAIL_FROM_NAME="${APP_NAME}"
```

**Важно:** на TimeWeb адрес отправителя должен совпадать с ящиком, под которым вы авторизуетесь в SMTP. То есть `MAIL_FROM_ADDRESS` и `MAIL_USERNAME` должны быть одним и тем же адресом.

- **Порты:** обычно `2525` (TLS) или `465` (SSL). Для порта 465 укажите `MAIL_ENCRYPTION=ssl`.
- **Порт 25** тоже допускается; тогда можно поставить `MAIL_ENCRYPTION=null` или `tls` по документации TimeWeb.

### 3.2 Включение email в приложении

В админке («Настройки» → почтовый сервер) или в настройках приложения должна быть опция «Включить рассылку» — она опирается на `SystemSetting::get('mail_notifications_enabled')`. Если эта настройка хранится в БД, включите её в интерфейсе после деплоя, чтобы уведомления начали уходить на почту (помимо сохранения в БД).

### 3.3 После смены .env

Выполните на сервере:

```bash
php artisan config:cache
```

---

## 4. После выкладки на сервер

1. **Права на каталоги:**
   - `storage` и `bootstrap/cache` — права на запись для веб-сервера (обычно `chmod -R 775 storage bootstrap/cache` и владелец — пользователь PHP/веб-сервера).

2. **Одна команда на сервере (из корня проекта):**
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan migrate --force
   php artisan db:seed --force
   ```

3. **Очередь и планировщик (если используете):**
   - В проекте есть команда `contacts:check-overdue`. Для её запуска по расписанию на TimeWeb добавьте задание cron, через админку TimeWeb. Варианты записи:
   
   **Вариант 3 — через скрипт (рекомендуется):**
   Используйте готовый скрипт `scripts/cron-schedule.sh` из репозитория:
   ```bash
   # На сервере сделать исполняемым:
   chmod +x ./scripts/cron-schedule.sh
   
   # В cron записать:
   * * * * * /home/o/ostart/est-contact/scripts/cron-schedule.sh
   ```
   
   - В `app/Console/Kernel.php` (или в `routes/console.php` в Laravel 11) должно быть запланировано выполнение `contacts:check-overdue`.

4. **Почта:** см. раздел 3 выше.

5. **SSL:** в панели TimeWeb включить HTTPS для домена.

---

## 5. TimeWeb Cloud / App Platform (Опционально, пока не использую)

Если используете **TimeWeb Cloud** и деплой из Git:

- В настройках приложения указать: **Build command** — например, `composer install --no-dev --optimize-autoloader`.
- **Start / deploy command** — при необходимости добавить `php artisan migrate --force`.
- Все секреты (APP_KEY, DB_*, MAIL_*, SEED_*) задать в переменных окружения в панели, без загрузки `.env` в репозиторий.

---

## 6. Чек-лист перед открытием сайта

- [ ] Document Root = папка `public`.
- [ ] `.env` создан на сервере, в нём production-значения (APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*, SEED_* при необходимости).
- [ ] Выполнены `migrate --force` и при первом запуске `db:seed --force`.
- [ ] Права на запись: `storage`, `bootstrap/cache`.
- [ ] Кэши конфига/маршрутов/видов при необходимости обновлены после смены .env.
- [ ] Cron для планировщика (если нужна команда просрочки контактов).
- [ ] SSL включён, почта настроена.
