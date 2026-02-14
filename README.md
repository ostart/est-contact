# Est-Contact - Система управления контактами

Приложение для управления контактами участников, построенное на **PHP 8.3**, **Laravel 11**, **Filament 5** и **MySQL 5.7**.

## Основные возможности

- ✅ **Мультиязычность:** Русский (основной) и английский языки
- ✅ **4 роли пользователей:** Лидер, Менеджер, Администратор, Суперадмин
- ✅ **Управление контактами:** Полный CRUD с валидацией и комментариями
- ✅ **Жизненный цикл контакта:** 5 статусов с историей изменений
- ✅ **Email уведомления:** Автоматическое оповещение лидеров
- ✅ **Audit Log:** Полное логирование всех действий пользователей
- ✅ **Автоматические напоминания:** Проверка просроченных контактов
- ✅ **Адаптивный интерфейс:** Работает на мобильных и desktop устройствах

## Технологический стек

- **Backend:** PHP 8.3.0
- **Framework:** Laravel 11.48.0
- **Admin Panel:** Filament 5.2.1
- **Database:** MySQL 5.7 (Docker)
- **Frontend:** Livewire 3.x (включено в Filament)
- **Авторизация:** Spatie Laravel Permission 6.24.1
- **Логирование:** Spatie Laravel Activity Log 4.11.0

## Системные требования

- **PHP:** >= 8.3
- **Composer:** >= 2.x
- **Node.js & NPM:** >= 18.x
- **Docker:** >= 20.x (для MySQL)
- **Git**

### PHP расширения

Убедитесь, что включены следующие расширения в `php.ini`:
```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=curl
extension=zip
```

## Быстрый старт

### 1. Клонирование репозитория

```bash
git clone https://github.com/yourusername/est-contact.git
cd est-contact
```

### 2. Установка зависимостей

```bash
composer install
npm install
```

### 3. Настройка окружения

Скопируйте файл `.env.example` в `.env`:
```bash
copy .env.example .env  # Windows
```

Сгенерируйте ключ приложения:
```bash
php artisan key:generate
```

Убедитесь, что в `.env` настроены следующие параметры:
```env
APP_NAME="Est-Contact"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Europe/Moscow
APP_URL=http://localhost:8000

APP_LOCALE=ru
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=est_contact
DB_USERNAME=est_contact
DB_PASSWORD=secret
```

### 4. Запуск MySQL в Docker

```bash
docker-compose up -d
```

Проверьте, что контейнер запущен:
```bash
docker ps
```

### 5. Миграции и seed данных

```bash
php artisan migrate --seed
```

Эта команда создаст все таблицы и заполнит БД начальными данными:
- 4 роли (leader, manager, administrator, superadmin)
- Первого суперадминистратора (admin@example.com)
- Системные настройки по умолчанию

### 6. Сборка фронтенда (опционально)

```bash
npm run dev
```

Или для production:
```bash
npm run build
```

### 7. Запуск приложения

```bash
php artisan serve
```

Приложение будет доступно по адресу: **http://127.0.0.1:8000**

Админ-панель Filament: **http://127.0.0.1:8000/admin**

## Учетные данные по умолчанию

**Суперадминистратор:**
- **Email:** admin@example.com
- **Пароль:** password
- **Роли:** leader, manager, administrator, superadmin
- **Доступ к Dashboard:** Да
- **Email подтвержден:** Да

После входа вы получите доступ ко всем функциям:
1. **Лидер** → Просмотр контактов, изменение статусов, комментарии
2. **Менеджер** → Создание/редактирование/удаление контактов, назначение лидеров
3. **Администратор** → Управление пользователями и ролями
4. **Суперадмин** → Настройка системы (таймауты, email сервер)

## Роли и права доступа

### Лидер (Leader)
- Просмотр списка контактов
- Фильтрация "Мои контакты" (по умолчанию)
- Изменение статуса контакта
- Добавление комментариев (без удаления)
- Просмотр истории статусов

### Менеджер (Manager)
- Все функции Лидера +
- Создание новых контактов
- Редактирование данных контактов
- Удаление контактов
- Назначение ответственного лидера
- При назначении лидеру отправляется email уведомление

### Администратор (Administrator)
- Управление пользователями:
  - Создание пользователей
  - Разрешение/блокировка доступа
  - Назначение ролей
  - Предоставление доступа к Dashboard
  - Подтверждение email
  - Удаление пользователей

### Суперадмин (Superadmin)
- Все функции +
- Управление системными настройками:
  - Таймаут просрочки обработки контакта (по умолчанию 30 дней)
  - Настройки почтового сервера для рассылки

## Жизненный цикл контакта

```
1. Не обработан (not_processed)
   ↓
2. Назначен исполнитель (assigned)
   ↓
   ├─→ 2*. Просрочен (overdue) - если превышен таймаут
   ↓
3. Обработан:
   ├─→ A. Успешно (success) - ФИНАЛЬНЫЙ
   └─→ B. Неуспешно (failed) - ФИНАЛЬНЫЙ, можно вернуть в статус 1 или 2
```

## Дополнительные команды

### Проверка просроченных контактов

Вручную:
```bash
php artisan contacts:check-overdue
```

Автоматически (запуск scheduler):
```bash
php artisan schedule:work
```

### Очистка кэша

```bash
php artisan optimize:clear
```

### Просмотр маршрутов

```bash
php artisan route:list --path=admin
```

### Публикация конфигурации Filament

```bash
php artisan vendor:publish --tag=filament-config
```

## Настройка email уведомлений

Для работы email уведомлений при назначении контакта лидеру, настройте почтовый сервер в `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@est-contact.local"
MAIL_FROM_NAME="${APP_NAME}"
```

Для тестирования рекомендуется использовать [Mailtrap](https://mailtrap.io/) или [MailHog](https://github.com/mailhog/MailHog).

## Структура базы данных

### Основные таблицы:

- **users** - Пользователи системы
- **contacts** - Контакты участников
- **contact_comments** - Комментарии к контактам
- **contact_status_histories** - История изменения статусов
- **system_settings** - Системные настройки (key-value)
- **roles** - Роли пользователей (Spatie Permission)
- **permissions** - Права доступа (Spatie Permission)
- **activity_log** - Журнал аудита (Spatie Activity Log)

## Разработка

### Создание нового ресурса Filament

```bash
php artisan make:filament-resource ModelName
```

### Создание миграции

```bash
php artisan make:migration create_table_name_table
```

### Создание модели

```bash
php artisan make:model ModelName -m
```

## Troubleshooting

### Ошибка "SQLSTATE[HY000] [2002] Connection refused"

**Проблема:** MySQL контейнер не запущен.

**Решение:**
```bash
docker-compose up -d
docker ps  # проверить статус
```

### Ошибка "The zip extension is missing"

**Проблема:** PHP расширение zip не включено.

**Решение:**
1. Найдите ваш `php.ini`: `php --ini`
2. Откройте файл и раскомментируйте строку: `extension=zip`
3. Перезапустите терминал/сервер

### Ошибка "Unknown collation: 'utf8mb4_0900_ai_ci'"

**Проблема:** MySQL 5.7 не поддерживает collation `utf8mb4_0900_ai_ci`.

**Решение:** Уже исправлено в `config/database.php` - используется `utf8mb4_unicode_ci`.

### Laravel сервер не запускается

**Проблема:** Порт 8000 занят.

**Решение:** Используйте другой порт:
```bash
php artisan serve --port=8001
```

## Production deployment

Для развертывания в production:

1. Установите `APP_ENV=production` и `APP_DEBUG=false` в `.env`
2. Соберите фронтенд: `npm run build`
3. Оптимизируйте Laravel:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
4. Настройте cron для scheduler:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```
5. Настройте очереди для background jobs (email notifications):
```bash
php artisan queue:work
```

## Лицензия

Этот проект является частным и предназначен для внутреннего использования.

## Поддержка

Для получения помощи или сообщения о проблемах, пожалуйста, создайте issue в GitHub репозитории.

## Авторы

Разработано с использованием:
- [Laravel](https://laravel.com)
- [Filament](https://filamentphp.com)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Spatie Laravel Activity Log](https://spatie.be/docs/laravel-activitylog)

---

**Версия:** 1.0.0  
**Дата последнего обновления:** Февраль 2026  
**Статус:** ✅ В PRODUCTION
