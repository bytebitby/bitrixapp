<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$handlerUrl = app_url('handler.php');
$placementUrl = app_url('placement.php');
$activityCode = app_config()['activity_code'];
$activityFields = activity_fields((string)$handlerUrl, (string)$placementUrl);
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
            --bg: #eef5fb;
            --card: #ffffff;
            --text: #17324d;
            --muted: #607086;
            --border: #d9e2ec;
            --accent: #1565c0;
            --accent-strong: #0f4f99;
            --danger: #b42318;
        }
        body {
            margin: 0;
            background: linear-gradient(180deg, #eef5fb 0%, #f8fbff 100%);
            color: var(--text);
            font: 16px/1.5 "Segoe UI", Arial, sans-serif;
        }
        main {
            max-width: 980px;
            margin: 0 auto;
            padding: 24px 16px 40px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 16px 40px rgba(19, 31, 55, 0.08);
            padding: 24px;
        }
        .badge {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(21, 101, 192, 0.08);
            color: var(--accent);
            font-weight: 700;
        }
        h1 {
            margin: 0 0 10px;
            font-size: 30px;
        }
        p {
            margin: 0 0 12px;
        }
        .muted {
            color: var(--muted);
        }
        .buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 22px 0;
        }
        button {
            border: 0;
            border-radius: 12px;
            padding: 12px 16px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        button.secondary {
            background: #295b8f;
        }
        button.ghost {
            background: #eef4fb;
            color: var(--accent-strong);
            border: 1px solid #c9d9ec;
        }
        button.danger {
            background: var(--danger);
        }
        pre {
            overflow: auto;
            background: #0f1720;
            color: #d8f4ff;
            border-radius: 14px;
            padding: 16px;
            font-size: 13px;
            min-height: 220px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .grid {
            display: grid;
            gap: 16px;
        }
        @media (min-width: 900px) {
            .grid {
                grid-template-columns: 1.15fr 0.85fr;
            }
        }
        .mini {
            font-size: 14px;
        }
        code {
            padding: 2px 6px;
            border-radius: 6px;
            background: rgba(15, 23, 32, 0.06);
        }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <div class="badge">Bitrix24 Local App</div>
            <h1>ByteBit Webhook</h1>
            <p>Эта страница умеет регистрировать activity напрямую через <code>BX24.callMethod</code>, как в старом рабочем сценарии для облачного Bitrix24.</p>
            <p class="muted">Если action не показывается после серверной установки, используйте кнопки ниже и затем заново откройте дизайнер бизнес-процесса сделки.</p>

            <div class="buttons">
                <button id="install-btn">Установить activity</button>
                <button id="reinstall-btn" class="secondary">Удалить и установить заново</button>
                <button id="list-btn" class="ghost">Показать список activity</button>
                <button id="delete-btn" class="danger">Удалить activity</button>
            </div>

            <div class="grid">
                <section>
                    <p class="mini muted">Результат вызовов Bitrix24 API</p>
                    <pre id="output">Ожидание команды...</pre>
                </section>
                <section>
                    <p class="mini muted">Текущая конфигурация</p>
                    <pre id="config"><?= htmlspecialchars((string)json_encode([
                        'activity_code' => $activityCode,
                        'handler_url' => $handlerUrl,
                        'placement_url' => $placementUrl,
                        'fields' => $activityFields,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></pre>
                </section>
            </div>
        </div>
    </main>

    <script src="//api.bitrix24.com/api/v1/"></script>
    <script>
    (function () {
        const output = document.getElementById('output');
        const activityCode = <?= json_encode($activityCode, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const activityFields = <?= json_encode($activityFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const debugLogUrl = <?= json_encode(app_url('debug_log.php'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

        function show(data) {
            if (typeof data === 'string') {
                output.textContent = data;
                return;
            }

            output.textContent = JSON.stringify(data, null, 2);
        }

        function logClient(event, payload) {
            if (!debugLogUrl) {
                return;
            }

            fetch(debugLogUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    source: 'index.php',
                    event: event,
                    payload: payload,
                    timestamp: new Date().toISOString()
                })
            }).catch(function () {
                // Best-effort debug logging only.
            });
        }

        function call(method, params) {
            return new Promise(function (resolve, reject) {
                if (!window.BX24 || typeof BX24.callMethod !== 'function') {
                    logClient('bx24_unavailable', {
                        method: method,
                        params: params
                    });
                    reject(new Error('BX24 SDK недоступен'));
                    return;
                }

                logClient('bx24_request', {
                    method: method,
                    params: params
                });

                BX24.callMethod(method, params, function (result) {
                    if (result.error()) {
                        logClient('bx24_error', {
                            method: method,
                            params: params,
                            error: result.error(),
                            error_description: result.error_description()
                        });
                        reject(new Error(result.error() + ': ' + result.error_description()));
                        return;
                    }

                    const data = result.data();
                    logClient('bx24_success', {
                        method: method,
                        params: params,
                        data: data
                    });
                    resolve(data);
                });
            });
        }

        async function listActivities() {
            show('Получаем список activity...');
            try {
                const data = await call('bizproc.activity.list', {});
                show({
                    method: 'bizproc.activity.list',
                    result: data
                });
            } catch (error) {
                show({
                    method: 'bizproc.activity.list',
                    error: error.message
                });
            }
        }

        async function installActivity() {
            show('Регистрируем activity...');
            try {
                const data = await call('bizproc.activity.add', activityFields);
                const list = await call('bizproc.activity.list', {});
                show({
                    method: 'bizproc.activity.add',
                    result: data,
                    activity_list: list
                });
            } catch (error) {
                show({
                    method: 'bizproc.activity.add',
                    error: error.message
                });
            }
        }

        async function deleteActivity() {
            show('Удаляем activity...');
            try {
                const data = await call('bizproc.activity.delete', {
                    CODE: activityCode
                });
                const list = await call('bizproc.activity.list', {});
                show({
                    method: 'bizproc.activity.delete',
                    result: data,
                    activity_list: list
                });
            } catch (error) {
                show({
                    method: 'bizproc.activity.delete',
                    error: error.message
                });
            }
        }

        async function reinstallActivity() {
            show('Удаляем и регистрируем activity заново...');

            let deleteResult;
            try {
                deleteResult = await call('bizproc.activity.delete', {
                    CODE: activityCode
                });
            } catch (error) {
                deleteResult = {
                    warning: error.message
                };
            }

            try {
                const addResult = await call('bizproc.activity.add', activityFields);
                const list = await call('bizproc.activity.list', {});
                show({
                    method: 'reinstall',
                    delete_result: deleteResult,
                    add_result: addResult,
                    activity_list: list
                });
            } catch (error) {
                show({
                    method: 'reinstall',
                    delete_result: deleteResult,
                    error: error.message
                });
            }
        }

        document.getElementById('install-btn').addEventListener('click', installActivity);
        document.getElementById('reinstall-btn').addEventListener('click', reinstallActivity);
        document.getElementById('list-btn').addEventListener('click', listActivities);
        document.getElementById('delete-btn').addEventListener('click', deleteActivity);

        if (window.BX24 && typeof BX24.init === 'function') {
            BX24.init(function () {
                logClient('bx24_init', {
                    activity_code: activityCode,
                    fields: activityFields
                });
                show('BX24 SDK инициализирован. Можно ставить activity через кнопки выше.');
            });
        } else {
            logClient('bx24_init_missing', {
                activity_code: activityCode
            });
            show('BX24 SDK не инициализировался.');
        }
    })();
    </script>
</body>
</html>
