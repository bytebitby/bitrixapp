<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ByteBit Webhook App</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --card: #ffffff;
            --text: #17202a;
            --muted: #5d6b7a;
            --border: #d7dee8;
            --accent: #0f6b57;
        }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 16px/1.5 "Segoe UI", Arial, sans-serif;
        }
        main {
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 16px 42px;
        }
        section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 22px;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 28px;
            line-height: 1.2;
        }
        h2 {
            margin: 22px 0 8px;
            font-size: 18px;
        }
        h3 {
            margin: 16px 0 6px;
            font-size: 16px;
        }
        p {
            margin: 0 0 12px;
            color: var(--muted);
        }
        ul, ol {
            margin: 0;
            padding-left: 22px;
        }
        li {
            margin: 8px 0;
        }
        code {
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(15, 23, 32, 0.07);
        }
        .badge {
            display: inline-block;
            margin-bottom: 12px;
            color: var(--accent);
            font-weight: 700;
        }
        .note {
            margin-top: 14px;
            border: 1px solid #c9ded7;
            background: #edf7f3;
            border-radius: 8px;
            padding: 14px;
        }
    </style>
</head>
<body>
    <main>
        <section>
            <div class="badge">Bitrix24 Local App</div>
            <h1>Как пользоваться ByteBit Webhook</h1>
            <p>Приложение добавляет два действия для бизнес-процессов: HTTP-запрос и парсинг JSON-ответа.</p>

            <h2>ByteBit HTTP-запрос</h2>
            <p>Действие отправляет запрос во внешний сервис и возвращает ответ в бизнес-процесс.</p>

            <h3>Поля настройки</h3>
            <ul>
                <li><code>Заголовок</code> — название блока внутри шаблона. На выполнение запроса не влияет.</li>
                <li><code>URL вебхука</code> — полный адрес вебхука или API. Query-параметры пишутся прямо в URL, например <code>?id=123</code>.</li>
                <li><code>Метод запроса</code> — <code>GET</code> для получения данных, <code>POST</code> для отправки JSON.</li>
                <li><code>JSON для отправки</code> — тело POST-запроса. Если поле пустое, приложение отправит стандартный контекст бизнес-процесса: id действия, id процесса, документ и домен портала.</li>
                <li><code>Заголовки JSON</code> — дополнительные HTTP-заголовки. Используйте только если сервис требует токен или специальный заголовок, например <code>{"Authorization":"Bearer token"}</code>.</li>
                <li><code>Таймаут, сек</code> — сколько секунд ждать ответ внешнего сервиса. Если сервис не ответит за это время, текст ошибки попадет в <code>Текст ошибки</code>.</li>
                <li><code>Запускать от имени</code> — системное поле Bitrix24. Можно оставить пользователя, который настраивает процесс.</li>
                <li><code>Устанавливать текст статуса</code> и <code>Текст статуса</code> — системные поля Bitrix24 для подписи ожидания в журнале. На запрос не влияют.</li>
                <li><code>Ожидать ответа</code> — системное поле Bitrix24. Для этих действий должно оставаться <code>Да</code>, потому что приложение возвращает результат в бизнес-процесс после выполнения запроса.</li>
                <li><code>Период ожидания</code> — системный лимит Bitrix24 на ожидание ответа приложения. Можно оставить пустым. Если заполняете, ставьте больше, чем <code>Таймаут, сек</code>.</li>
            </ul>

            <h3>Результаты</h3>
            <ul>
                <li><code>Ответ вебхука</code> — тело успешного ответа.</li>
                <li><code>HTTP-статус</code> — код ответа, например <code>200</code>, <code>404</code>, <code>500</code>.</li>
                <li><code>Текст ошибки</code> — curl-ошибка, таймаут или тело ответа при HTTP-ошибке.</li>
                <li><code>Заголовки ответа</code> — HTTP-заголовки ответа.</li>
                <li><code>Время выполнения, сек</code> — длительность запроса с 4 знаками после запятой.</li>
            </ul>

            <div class="note">
                Поля Bitrix <code>Запускать от имени</code>, <code>Устанавливать текст статуса</code>, <code>Текст статуса</code>, <code>Ожидать ответа</code> и <code>Период ожидания</code> являются стандартными системными полями Bitrix24. Действие работает через ожидание ответа приложения: приложение само отправляет результат обратно в бизнес-процесс.
            </div>

            <h2>ByteBit Парсинг JSON</h2>
            <p>Действие достает одно значение из JSON-ответа и возвращает его в бизнес-процесс.</p>

            <h3>Поля настройки</h3>
            <ul>
                <li><code>Заголовок</code> — название блока внутри шаблона. На парсинг не влияет.</li>
                <li><code>JSON-ответ</code> — JSON-строка для разбора. Сюда подставляют результат <code>Ответ вебхука</code> из действия HTTP-запроса.</li>
                <li><code>Что вытащить</code> — путь к нужному полю. Примеры: <code>id</code>, <code>data.id</code>, <code>items[0].id</code>.</li>
                <li><code>Если не найдено</code> — значение, которое вернется, если указанного поля нет в JSON.</li>
                <li><code>Запускать от имени</code> — системное поле Bitrix24. Можно оставить текущее значение.</li>
                <li><code>Устанавливать текст статуса</code>, <code>Текст статуса</code>, <code>Ожидать ответа</code>, <code>Период ожидания</code> — системные поля Bitrix24. Для парсинга их менять не нужно.</li>
            </ul>

            <h3>Результаты</h3>
            <ul>
                <li><code>Найденное значение</code> — значение по указанному пути. Его записывают в переменную бизнес-процесса.</li>
                <li><code>Поле найдено</code> — <code>Y</code>, если путь найден, иначе <code>N</code>.</li>
                <li><code>Ошибка парсинга</code> — текст ошибки, если JSON некорректный или путь не указан.</li>
            </ul>
        </section>
    </main>
</body>
</html>
