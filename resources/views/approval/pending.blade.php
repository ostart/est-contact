<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ожидание подтверждения - Есть Контакт</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .container {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            max-width: 480px;
            width: 100%;
            padding: 40px;
        }

        .icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 24px;
            background: #2563eb;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }

        h1 {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
            text-align: center;
        }

        .message {
            font-size: 15px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 24px;
            text-align: center;
        }

        .email-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 28px;
        }

        .email-info .label {
            font-size: 13px;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 6px;
        }

        .email-info .address {
            font-size: 15px;
            font-weight: 500;
            color: #1e3a8a;
            word-break: break-all;
        }

        .steps {
            margin-bottom: 28px;
        }

        .steps h3 {
            font-size: 15px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
        }

        .steps ol {
            list-style-position: inside;
            font-size: 14px;
            color: #4b5563;
            line-height: 1.9;
        }

        .steps li {
            padding-left: 4px;
        }

        .buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            display: inline-block;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            font-family: inherit;
            text-decoration: none;
            text-align: center;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.15s, color 0.15s;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #ffffff;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        @media (max-width: 480px) {
            .container {
                padding: 28px 20px;
            }

            h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
        </div>

        <h1>Регистрация успешно завершена</h1>

        <p class="message">
            Спасибо за регистрацию в системе Есть Контакт. Ваша заявка находится на рассмотрении.
        </p>

        <div class="email-info">
            <p class="label">Письмо с подтверждением отправлено на:</p>
            <p class="address">{{ $email }}</p>
        </div>

        <div class="steps">
            <h3>Что дальше?</h3>
            <ol>
                <li>Проверьте вашу электронную почту</li>
                <li>Подтвердите email по ссылке из письма</li>
                <li>Дождитесь подтверждения от администратора</li>
                <li>Получите уведомление о доступе</li>
            </ol>
        </div>

        <div class="buttons">
            <form action="{{ route('approval.logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-primary">Выйти из системы</button>
            </form>
        </div>
    </div>
</body>
</html>
