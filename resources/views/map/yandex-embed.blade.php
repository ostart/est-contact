<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Карта</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: Inter, system-ui, sans-serif; background: #f9fafb; }
        iframe { display: block; width: 100%; height: 100%; border: 0; }
        .error {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 24px;
            text-align: center;
            color: #b45309;
            background: #fffbeb;
        }
    </style>
</head>
<body>
    @if (blank($embedUrl))
        <div class="error">
            Укажите URL виджета карты в переменной окружения <strong>YANDEX_MAPS_EMBED_URL</strong>
            (код iframe из <a href="https://yandex.ru/map-constructor/" target="_blank" rel="noopener">конструктора Яндекс.Карт</a>).
        </div>
    @else
        <iframe src="{{ $embedUrl }}" title="Яндекс Карта" loading="lazy"></iframe>
    @endif
</body>
</html>
