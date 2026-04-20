<?php
declare(strict_types=1);

function load_env_file(string $path): void
{
    static $loaded = [];

    if (isset($loaded[$path]) || !is_file($path)) {
        return;
    }

    $loaded[$path] = true;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, '\'') && str_ends_with($value, '\''))) {
            $value = substr($value, 1, -1);
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

load_env_file(__DIR__ . DIRECTORY_SEPARATOR . '.env');

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    $value = trim($value);
    return $value === '' ? $default : $value;
}

function app_config(): array
{
    static $config;

    if ($config !== null) {
        return $config;
    }

    $baseUrl = env_value('APP_BASE_URL');
    if ($baseUrl === null) {
        $baseUrl = detect_base_url();
    }

    $logDir = env_value('APP_LOG_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'var');
    $logFile = env_value('APP_LOG_FILE', rtrim((string)$logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app.log');

    $config = [
        'app_base_url' => $baseUrl !== null ? rtrim($baseUrl, '/') : null,
        'log_file' => $logFile,
        'activity_code' => env_value('APP_ACTIVITY_CODE', 'bytebit_webhook_activity'),
    ];

    return $config;
}

function detect_base_url(): ?string
{
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? null;
    if (!is_string($host) || trim($host) === '') {
        return null;
    }

    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
    if (!is_string($proto) || trim($proto) === '') {
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        $proto = ($https !== '' && $https !== 'off') ? 'https' : 'http';
    }

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = str_replace('\\', '/', dirname($scriptName));
    if ($basePath === '/' || $basePath === '.') {
        $basePath = '';
    }

    return sprintf('%s://%s%s', strtolower($proto), trim($host), rtrim($basePath, '/'));
}

function app_url(string $path): ?string
{
    $baseUrl = app_config()['app_base_url'];
    if (!is_string($baseUrl) || $baseUrl === '') {
        return null;
    }

    return $baseUrl . '/' . ltrim($path, '/');
}

function ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    @mkdir($path, 0777, true);
}

function app_log(string $message, array $context = []): void
{
    $logFile = app_config()['log_file'];
    $directory = dirname($logFile);
    ensure_directory($directory);

    $line = sprintf(
        "[%s] %s %s\n",
        date('c'),
        $message,
        $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );

    @file_put_contents($logFile, $line, FILE_APPEND);
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function html_response(string $html, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

function get_request_data(): array
{
    $raw = file_get_contents('php://input');
    $json = [];

    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }

    return array_replace_recursive($_REQUEST, $json);
}

function request_value(array $data, string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $data)) {
        return $data[$key];
    }

    if (!str_contains($key, '.')) {
        return $default;
    }

    $value = $data;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function get_auth_token(array $data): ?string
{
    foreach (
        [
            request_value($data, 'auth.access_token'),
            request_value($data, 'AUTH_ID'),
            request_value($data, 'access_token'),
        ] as $token
    ) {
        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }
    }

    return null;
}

function get_portal_domain(array $data): ?string
{
    foreach (
        [
            request_value($data, 'auth.domain'),
            request_value($data, 'DOMAIN'),
            request_value($data, 'domain'),
        ] as $domain
    ) {
        if (!is_string($domain) || trim($domain) === '') {
            continue;
        }

        return preg_replace('#^https?://#i', '', trim($domain));
    }

    return null;
}

function rest_call(string $domain, string $token, string $method, array $fields = []): array
{
    $url = 'https://' . $domain . '/rest/' . $method . '.json';
    $payload = $fields;
    $payload['auth'] = $token;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    app_log('REST call', [
        'method' => $method,
        'http_code' => $httpCode,
        'error' => $error,
        'response' => is_string($response) ? substr($response, 0, 1000) : $response,
    ]);

    if ($error !== '') {
        return [
            'error' => 'CURL_ERROR',
            'error_description' => $error,
            'http_code' => $httpCode,
        ];
    }

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        return [
            'error' => 'INVALID_RESPONSE',
            'error_description' => (string)$response,
            'http_code' => $httpCode,
        ];
    }

    return $decoded;
}

function activity_fields(string $handlerUrl, string $placementUrl): array
{
    return [
        'CODE' => app_config()['activity_code'],
        'HANDLER' => $handlerUrl,
        'AUTH_USER_ID' => 1,
        'USE_SUBSCRIPTION' => 'Y',
        'USE_PLACEMENT' => 'Y',
        'PLACEMENT_HANDLER' => $placementUrl,
        'NAME' => [
            'ru' => 'Вызов внешнего вебхука',
            'en' => 'External Webhook Call',
        ],
        'DESCRIPTION' => [
            'ru' => 'Вызывает внешний вебхук и возвращает полный ответ в дополнительные результаты бизнес-процесса',
            'en' => 'Calls an external webhook and returns the full response into business process return values',
        ],
        'PROPERTIES' => [
            'webhook_url' => [
                'Name' => [
                    'ru' => 'URL вебхука',
                    'en' => 'Webhook URL',
                ],
                'Description' => [
                    'ru' => 'Публичный URL, который будет вызван во время выполнения действия',
                    'en' => 'Public URL that will be called when the activity is executed',
                ],
                'Type' => 'string',
                'Required' => 'Y',
                'Multiple' => 'N',
                'Default' => '',
            ],
        ],
        'RETURN_PROPERTIES' => [
            'webhook_result' => [
                'Name' => [
                    'ru' => 'Полный ответ вебхука',
                    'en' => 'Webhook full response',
                ],
                'Type' => 'text',
                'Multiple' => 'N',
                'Default' => null,
            ],
            'http_status' => [
                'Name' => [
                    'ru' => 'HTTP статус',
                    'en' => 'HTTP status',
                ],
                'Type' => 'int',
                'Multiple' => 'N',
                'Default' => null,
            ],
            'error_message' => [
                'Name' => [
                    'ru' => 'Ошибка',
                    'en' => 'Error message',
                ],
                'Type' => 'text',
                'Multiple' => 'N',
                'Default' => null,
            ],
        ],
        'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'],
        'FILTER' => [
            'INCLUDE' => [
                ['crm'],
                ['lists'],
            ],
            'EXCLUDE' => ['box'],
        ],
    ];
}

function normalize_webhook_result(string $responseBody): string
{
    $decoded = json_decode($responseBody, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return (string)json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    return $responseBody;
}

function safe_json_for_html(mixed $value): string
{
    return htmlspecialchars(
        (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function render_install_page(bool $success, array $context = []): never
{
    $title = $success ? 'Установка завершена' : 'Установка не завершена';
    $accent = $success ? '#1f7a45' : '#a62727';
    $statusText = $success ? 'Активити зарегистрировано в Битрикс24.' : 'Не удалось завершить установку приложения.';
    $message = $context['message'] ?? '';
    $details = safe_json_for_html($context['details'] ?? []);

    $finishScript = $success ? <<<HTML
<script src="//api.bitrix24.com/api/v1/"></script>
<script>
if (window.BX24 && typeof BX24.installFinish === 'function') {
    BX24.installFinish();
}
</script>
HTML : '';

    $html = <<<HTML
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f5f8;
            --card: #ffffff;
            --text: #17202a;
            --muted: #5b6674;
            --accent: {$accent};
            --border: #d8dee8;
        }
        body {
            margin: 0;
            background: linear-gradient(180deg, #eef3f9 0%, #f8fafc 100%);
            color: var(--text);
            font: 16px/1.5 "Segoe UI", Arial, sans-serif;
        }
        main {
            max-width: 860px;
            margin: 0 auto;
            padding: 32px 16px 48px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 16px 40px rgba(19, 31, 55, 0.08);
            padding: 24px;
        }
        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }
        .badge {
            display: inline-block;
            margin-bottom: 16px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.04);
            color: var(--accent);
            font-weight: 700;
        }
        p {
            margin: 0 0 12px;
        }
        pre {
            overflow: auto;
            background: #0f1720;
            color: #d8f4ff;
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <div class="badge">Bitrix24 Local App</div>
            <h1>{$title}</h1>
            <p>{$statusText}</p>
            <p>{$message}</p>
            <pre>{$details}</pre>
        </div>
    </main>
    {$finishScript}
</body>
</html>
HTML;

    $statusCode = (int)($context['status'] ?? ($success ? 200 : 500));
    html_response($html, $statusCode);
}
