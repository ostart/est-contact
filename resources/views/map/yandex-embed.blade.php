<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Карта</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: Inter, system-ui, sans-serif; background: #f9fafb; }
        #map { width: 100%; height: 100%; }
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
    @if (blank($apiKey))
        <div class="error">
            Укажите ключ API Яндекс.Карт в переменной окружения <strong>YANDEX_MAPS_API_KEY</strong>
            (бесплатный тариф в <a href="https://developer.tech.yandex.ru/" target="_blank" rel="noopener">кабинете разработчика</a>).
        </div>
    @else
        <div id="map"></div>

        <script src="https://api-maps.yandex.ru/2.1/?apikey={{ urlencode($apiKey) }}&lang=ru_RU" type="text/javascript"></script>
        <script>
            ymaps.ready(function () {
                new ymaps.Map('map', {
                    center: [55.751574, 37.573856],
                    zoom: 10,
                    controls: [
                        'zoomControl',
                        'geolocationControl',
                        'searchControl',
                        'typeSelector',
                        'fullscreenControl',
                        'rulerControl',
                    ],
                });
            });
        </script>
    @endif
</body>
</html>
