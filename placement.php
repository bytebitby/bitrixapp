<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$data = get_request_data();
$placementOptionsRaw = request_value($data, 'PLACEMENT_OPTIONS') ?? request_value($data, 'placement_options') ?? '{}';
$placementOptions = json_decode((string)$placementOptionsRaw, true);

if (!is_array($placementOptions)) {
    $placementOptions = [];
}

$currentWebhook = request_value($placementOptions, 'current_values.webhook_url', '');
$currentWebhook = is_string($currentWebhook) ? $currentWebhook : '';
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Настройка activity</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17324d;
            --muted: #607086;
            --line: #d9e2ec;
            --card: #ffffff;
            --canvas: linear-gradient(180deg, #f7fbff 0%, #edf4fa 100%);
            --accent: #1565c0;
            --accent-soft: #e8f1ff;
        }
        body {
            margin: 0;
            padding: 16px;
            color: var(--ink);
            background: var(--canvas);
            font: 15px/1.5 "Segoe UI", Arial, sans-serif;
        }
        .layout {
            display: grid;
            gap: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 10px 28px rgba(20, 43, 66, 0.08);
            padding: 18px;
        }
        h1, h2 {
            margin: 0 0 10px;
        }
        h1 {
            font-size: 22px;
        }
        h2 {
            font-size: 16px;
        }
        p {
            margin: 0 0 10px;
            color: var(--muted);
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }
        input[type="url"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #b8c7d9;
            border-radius: 12px;
            padding: 13px 14px;
            font: inherit;
            color: var(--ink);
            background: #fff;
        }
        input[type="url"]:focus {
            outline: 2px solid rgba(21, 101, 192, 0.2);
            border-color: var(--accent);
        }
        .hint {
            margin-top: 10px;
            padding: 12px 14px;
            border-radius: 12px;
            background: var(--accent-soft);
            color: var(--ink);
        }
        code {
            padding: 2px 6px;
            border-radius: 6px;
            background: rgba(15, 23, 32, 0.06);
        }
        .status {
            margin-top: 12px;
            font-size: 13px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="layout">
        <section class="card">
            <h1>Вызов внешнего вебхука</h1>
            <p>Укажите публичный URL вебхука. Во время выполнения activity приложение отправит на него POST-запрос с данными бизнес-процесса.</p>
            <label for="webhook-url">URL вебхука</label>
            <input
                id="webhook-url"
                type="url"
                placeholder="https://example.com/webhook"
                value="<?= htmlspecialchars($currentWebhook, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
            >
            <div class="status" id="status">Изменения сохраняются в параметры activity автоматически.</div>
        </section>

        <section class="card">
            <h2>Что вернется в БП</h2>
            <p>После выполнения action результат будет доступен в разделе <strong>Дополнительно</strong> как возвращаемые значения activity.</p>
            <div class="hint">
                <div><code>webhook_result</code> — полный ответ внешнего вебхука</div>
                <div><code>http_status</code> — HTTP статус ответа</div>
                <div><code>error_message</code> — текст ошибки, если вызов не удался</div>
            </div>
        </section>
    </div>

    <script src="//api.bitrix24.com/api/v1/"></script>
    <script>
    (function () {
        const input = document.getElementById('webhook-url');
        const status = document.getElementById('status');
        let saveTimer = null;

        function save() {
            if (!window.BX24 || !BX24.placement || typeof BX24.placement.call !== 'function') {
                status.textContent = 'BX24 SDK недоступен. Значение нужно будет сохранить после открытия activity в Битрикс24.';
                return;
            }

            BX24.placement.call('setPropertyValue', {
                webhook_url: input.value.trim()
            }, function () {
                status.textContent = 'Параметр webhook_url сохранен в настройках activity.';
            });
        }

        input.addEventListener('input', function () {
            status.textContent = 'Сохраняем значение...';
            clearTimeout(saveTimer);
            saveTimer = setTimeout(save, 250);
        });

        if (window.BX24 && typeof BX24.init === 'function') {
            BX24.init(function () {
                status.textContent = 'Интерфейс готов. Можно указать URL вебхука.';
            });
        }
    })();
    </script>
</body>
</html>
