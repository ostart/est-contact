<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение email - Есть Контакт</title>
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

        .success-message {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #065f46;
            text-align: center;
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
                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
        </div>

        <h1>Требуется подтверждение email</h1>

        <p class="message">
            Для доступа к системе необходимо подтвердить ваш email адрес. Перейдите по ссылке в письме, которое было отправлено на указанный адрес.
        </p>

        @if (session('status') === 'verification-link-sent')
            <div class="success-message">
                Новая ссылка для подтверждения отправлена на ваш email!
            </div>
        @endif

        <div class="email-info">
            <p class="label">Письмо отправлено на:</p>
            <p class="address">{{ $email }}</p>
        </div>

        <div class="steps">
            <h3>Что нужно сделать:</h3>
            <ol>
                <li>Откройте вашу электронную почту</li>
                <li>Найдите письмо от «Есть Контакт»</li>
                <li>Нажмите на ссылку подтверждения</li>
                <li>Вернитесь и войдите в систему</li>
            </ol>
        </div>

        <div class="buttons">
            <form action="{{ route('email.resend') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-primary">Отправить письмо повторно</button>
            </form>
            
            <form action="{{ route('approval.logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-secondary">Выйти из системы</button>
            </form>
        </div>
    </div>
</body>
</html>
