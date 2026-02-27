<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аккаунт заблокирован - Есть Контакт</title>
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
            background: #dc2626;
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

        .reason-info {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 28px;
        }

        .reason-info .label {
            font-size: 13px;
            font-weight: 600;
            color: #991b1b;
            margin-bottom: 6px;
        }

        .reason-info .text {
            font-size: 15px;
            font-weight: 500;
            color: #7f1d1d;
            word-break: break-word;
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
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
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
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zM4 12c0-4.42 3.58-8 8-8 1.85 0 3.55.63 4.9 1.69L5.69 16.9C4.63 15.55 4 13.85 4 12zm8 8c-1.85 0-3.55-.63-4.9-1.69L18.31 7.1C19.37 8.45 20 10.15 20 12c0 4.42-3.58 8-8 8z"/>
            </svg>
        </div>

        <h1>Аккаунт заблокирован</h1>

        <p class="message">
            Ваш аккаунт был заблокирован администратором. Доступ к системе ограничен.
        </p>

        @if ($reason)
            <div class="reason-info">
                <p class="label">Причина блокировки:</p>
                <p class="text">{{ $reason }}</p>
            </div>
        @endif

        <div class="buttons">
            <form action="{{ route('approval.logout') }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-primary">Выйти из системы</button>
            </form>
        </div>
    </div>
</body>
</html>
