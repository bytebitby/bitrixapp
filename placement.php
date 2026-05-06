<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$data = get_request_data();
app_log('PLACEMENT HIT', [
    'meta' => request_meta(),
    'request' => $data,
]);

$placementOptionsRaw = request_value($data, 'PLACEMENT_OPTIONS') ?? request_value($data, 'placement_options') ?? '{}';
$placementOptions = json_decode((string)$placementOptionsRaw, true);
if (!is_array($placementOptions)) {
    $placementOptions = [];
}

$current = request_value($placementOptions, 'current_values', []);
if (!is_array($current)) {
    $current = [];
}

$isParserPlacement = array_key_exists('source_json', $current) || array_key_exists('json_path', $current);

function current_value(array $current, string $key, string $default = ''): string
{
    $value = $current[$key] ?? $default;
    return is_scalar($value) ? (string)$value : $default;
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Настройка ByteBit activity</title>
    <style>
        :root {
            color-scheme: light;
            --ink: #17202a;
            --muted: #5d6b7a;
            --line: #d7dee8;
            --card: #ffffff;
            --canvas: #f5f7fb;
            --accent: #0f6b57;
            --soft: #eaf5f1;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 16px;
            color: var(--ink);
            background: var(--canvas);
            font: 14px/1.45 "Segoe UI", Arial, sans-serif;
        }
        .layout {
            display: grid;
            gap: 14px;
        }
        .panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 16px;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 20px;
            line-height: 1.2;
        }
        p {
            margin: 0 0 12px;
            color: var(--muted);
        }
        .grid {
            display: grid;
            gap: 12px;
        }
        @media (min-width: 760px) {
            .grid.two {
                grid-template-columns: 170px 1fr;
                align-items: start;
            }
        }
        label {
            display: block;
            font-weight: 700;
            margin-bottom: 6px;
        }
        input, select, textarea {
            width: 100%;
            border: 1px solid #b9c5d2;
            border-radius: 6px;
            padding: 10px 11px;
            color: var(--ink);
            background: #fff;
            font: inherit;
        }
        textarea {
            min-height: 92px;
            resize: vertical;
            font-family: Consolas, "Courier New", monospace;
        }
        input:focus, select:focus, textarea:focus {
            outline: 2px solid rgba(15, 107, 87, 0.18);
            border-color: var(--accent);
        }
        .hint {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }
        .callout {
            margin-top: 12px;
            background: var(--soft);
            border: 1px solid #c7e4da;
            border-radius: 8px;
            padding: 12px;
        }
        code {
            padding: 1px 5px;
            border-radius: 4px;
            background: rgba(15, 23, 32, 0.07);
        }
        .status {
            position: sticky;
            bottom: 0;
            z-index: 2;
            border: 1px solid #cbd6e2;
            background: #ffffff;
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--muted);
            box-shadow: 0 -4px 20px rgba(20, 31, 45, 0.06);
        }
    </style>
</head>
<body>
    <div class="layout">
        <?php if (!$isParserPlacement): ?>
        <section class="panel">
            <h1>HTTP-запрос</h1>
            <p>Для обычного вебхука достаточно вставить URL. Остальные поля нужны только если сервис требует метод POST, JSON или заголовок авторизации.</p>

            <div class="grid two">
                <div><label for="webhook-url">URL вебхука</label></div>
                <div>
                    <input id="webhook-url" data-prop="webhook_url" type="url" placeholder="https://example.com/webhook" value="<?= htmlspecialchars(current_value($current, 'webhook_url'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <div class="hint">Query-параметры можно писать прямо в URL: <code>?id=123</code>.</div>
                </div>

                <div><label for="http-method">Метод</label></div>
                <div>
                    <select id="http-method" data-prop="http_method">
                        <?php foreach (['GET', 'POST'] as $method): ?>
                            <option value="<?= $method ?>" <?= current_value($current, 'http_method', 'POST') === $method ? 'selected' : '' ?>><?= $method ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint"><code>GET</code> получает данные, <code>POST</code> отправляет JSON.</div>
                </div>

                <div><label for="request-body">JSON для отправки</label></div>
                <div>
                    <textarea id="request-body" data-prop="request_body" placeholder='{"id":"{{ID}}","source":"bitrix24"}'><?= htmlspecialchars(current_value($current, 'request_body'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    <div class="hint">Можно оставить пустым: тогда POST отправит стандартные данные бизнес-процесса.</div>
                </div>

                <div><label for="request-headers">Заголовки JSON</label></div>
                <div>
                    <textarea id="request-headers" data-prop="request_headers" placeholder='{"Authorization":"Bearer token"}'><?= htmlspecialchars(current_value($current, 'request_headers'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    <div class="hint">Необязательное поле. Заполняйте только если API требует токен или свой заголовок.</div>
                </div>

                <div><label for="timeout-seconds">Таймаут, сек</label></div>
                <input id="timeout-seconds" data-prop="timeout_seconds" type="number" min="1" max="300" step="1" value="<?= htmlspecialchars(current_value($current, 'timeout_seconds', '60'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>

            <div class="callout">
                Результаты: <code>Ответ вебхука</code>, <code>HTTP-статус</code>, <code>Текст ошибки</code>, <code>Время выполнения, сек</code>.
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isParserPlacement): ?>
        <section class="panel">
            <h1>Парсинг JSON</h1>
            <p>Эта activity берет JSON-ответ и достает из него одно поле, чтобы записать значение в переменную бизнес-процесса.</p>

            <div class="grid two">
                <div><label for="source-json">JSON-ответ</label></div>
                <div>
                    <textarea id="source-json" data-prop="source_json" placeholder='{"id":123,"data":{"deal":"D-1"}}'><?= htmlspecialchars(current_value($current, 'source_json'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
                    <div class="hint">Обычно сюда подставляют результат <code>Ответ вебхука</code> из первой activity.</div>
                </div>

                <div><label for="json-path">Что вытащить</label></div>
                <div>
                    <input id="json-path" data-prop="json_path" type="text" placeholder="id или data.deal или $.items[0].id" value="<?= htmlspecialchars(current_value($current, 'json_path'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                    <div class="hint">Примеры: <code>id</code>, <code>data.id</code>, <code>items[0].id</code>.</div>
                </div>

                <div><label for="default-value">Если не найдено</label></div>
                <input id="default-value" data-prop="default_value" type="text" value="<?= htmlspecialchars(current_value($current, 'default_value'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            </div>

            <div class="callout">
                Основной результат: <code>Найденное значение</code>. Его и записывайте в переменную БП.
            </div>
        </section>
        <?php endif; ?>

        <div class="status" id="status">Изменения сохраняются в параметры activity автоматически.</div>
    </div>

    <script src="//api.bitrix24.com/api/v1/"></script>
    <script>
    (function () {
        const status = document.getElementById('status');
        const fields = Array.from(document.querySelectorAll('[data-prop]'));
        let saveTimer = null;

        function payload() {
            return fields.reduce(function (result, field) {
                result[field.dataset.prop] = field.value.trim();
                return result;
            }, {});
        }

        function setStatus(text) {
            status.textContent = text;
        }

        function save() {
            if (!window.BX24 || !BX24.placement || typeof BX24.placement.call !== 'function') {
                setStatus('BX24 SDK недоступен. Откройте activity внутри дизайнера бизнес-процесса.');
                return;
            }

            BX24.placement.call('setPropertyValue', payload(), function () {
                setStatus('Параметры сохранены.');
            });
        }

        fields.forEach(function (field) {
            field.addEventListener('input', function () {
                setStatus('Сохраняем...');
                clearTimeout(saveTimer);
                saveTimer = setTimeout(save, 300);
            });
            field.addEventListener('change', save);
        });

        if (window.BX24 && typeof BX24.init === 'function') {
            BX24.init(function () {
                setStatus('Интерфейс готов.');
            });
        }
    })();
    </script>
</body>
</html>
