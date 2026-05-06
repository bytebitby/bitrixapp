<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$installUrl = app_url('install.php');
$handlerUrl = app_url('handler.php');
$placementUrl = app_url('placement.php');
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
        a {
            color: var(--accent);
            font-weight: 700;
        }
    </style>
</head>
<body>
    <main>
        <section>
            <div class="badge">Bitrix24 Local App</div>
            <h1>ByteBit Webhook</h1>
            <p>После установки в дизайнере бизнес-процессов появятся две activity: HTTP/webhook-запрос и парсинг JSON-ответа.</p>

            <h2>Как пользоваться</h2>
            <ol>
                <li>В локальном приложении Bitrix24 укажите путь установки: <code><?= htmlspecialchars((string)$installUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>.</li>
                <li>Откройте приложение один раз: оно само зарегистрирует activity в Bitrix24.</li>
                <li>В бизнес-процессе добавьте <code>ByteBit HTTP-запрос</code>. Можно просто вставить URL вебхука или настроить метод, headers, query, body и timeout.</li>
                <li>Ответ вернется в <code>webhook_result</code>, ошибки отдельно в <code>error_message</code>, HTTP-код в <code>http_status</code>, время в <code>duration_seconds</code>.</li>
                <li>Чтобы достать значение из ответа, добавьте <code>ByteBit Парсинг JSON</code>, передайте туда <code>webhook_result</code> и укажите путь: например <code>id</code>, <code>data.id</code> или <code>$.items[0].id</code>.</li>
                <li>Используйте выход <code>parsed_value</code> и запишите его в нужную переменную бизнес-процесса.</li>
            </ol>

            <div class="box">
                Служебные URL: handler <code><?= htmlspecialchars((string)$handlerUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>, placement <code><?= htmlspecialchars((string)$placementUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></code>.
            </div>
        </section>
    </main>
</body>
</html>
