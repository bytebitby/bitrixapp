<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$installUrl = app_url('install.php');
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
            max-width: 920px;
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
            margin: 20px 0 8px;
            font-size: 18px;
        }
        p {
            margin: 0 0 12px;
            color: var(--muted);
        }
        ol {
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
        .box {
            margin-top: 16px;
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
            <p>Эта инструкция для момента, когда приложение уже установлено и activity появились в дизайнере бизнес-процессов.</p>

            <h2>1. Отправить вебхук</h2>
            <ol>
                <li>Откройте нужный бизнес-процесс в Bitrix24.</li>
                <li>Добавьте activity <code>ByteBit HTTP-запрос</code>.</li>
                <li>В поле <code>URL вебхука</code> вставьте адрес сервиса. Если нужны query-параметры, добавьте их прямо в URL, например <code>?id=123</code>.</li>
                <li>Выберите метод: <code>GET</code>, если нужно только получить данные, или <code>POST</code>, если нужно отправить JSON.</li>
                <li>Если используете <code>POST</code>, заполните <code>JSON для отправки</code>. Если оставить поле пустым, приложение отправит стандартные данные бизнес-процесса.</li>
                <li>Поле <code>Заголовки JSON</code> заполняйте только если API требует токен или специальный заголовок.</li>
                <li>После выполнения смотрите результат в полях <code>Ответ вебхука</code>, <code>HTTP-статус</code>, <code>Текст ошибки</code> и <code>Время выполнения, сек</code>.</li>
            </ol>

            <h2>2. Достать значение из ответа</h2>
            <ol>
                <li>После HTTP-запроса добавьте activity <code>ByteBit Парсинг JSON</code>.</li>
                <li>В поле <code>JSON-ответ</code> подставьте результат <code>Ответ вебхука</code> из первой activity.</li>
                <li>В поле <code>Что вытащить</code> укажите путь к нужному значению: например <code>id</code>, <code>data.id</code> или <code>items[0].id</code>.</li>
                <li>Возьмите результат <code>Найденное значение</code> и запишите его в переменную бизнес-процесса.</li>
            </ol>

            <div class="box">
                Если activity не появились после обновления, откройте путь установки еще раз: <code><?= htmlspecialchars((string)$installUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>
            </div>
        </section>
    </main>
</body>
</html>
