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
        'activity_code' => env_value('APP_ACTIVITY_CODE', 'bytebit_webhook_activity_v2'),
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

function request_meta(): array
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $rawHeaders = getallheaders();
        if (is_array($rawHeaders)) {
            $headers = $rawHeaders;
        }
    }

    return [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'headers' => $headers,
    ];
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
    $filter = [
        'INCLUDE' => [
            ['crm'],
            ['crm', 'CCrmDocumentDeal'],
            ['crm', 'CCrmDocumentDeal', 'DEAL'],
        ],
    ];

    if (env_value('APP_EXCLUDE_BOX', 'N') === 'Y') {
        $filter['EXCLUDE'] = ['box'];
    }

    return [
        'CODE' => app_config()['activity_code'],
        'HANDLER' => $handlerUrl,
        'AUTH_USER_ID' => 1,
        'USE_SUBSCRIPTION' => 'Y',
        'DOCUMENT_TYPE' => ['crm', 'CCrmDocumentDeal', 'DEAL'],
        'NAME' => 'ByteBit Webhook',
        'DESCRIPTION' => 'Calls an external webhook. Paste the target webhook URL into the standard activity field.',
        'PROPERTIES' => [
            'webhook_url' => [
                'Name' => 'Webhook URL',
                'Description' => 'Paste the public webhook URL here.',
                'Type' => 'string',
                'Required' => 'Y',
                'Multiple' => 'N',
                'Default' => '',
            ],
        ],
        'RETURN_PROPERTIES' => [
            'webhook_result' => [
                'Name' => 'Webhook response',
                'Type' => 'text',
                'Multiple' => 'N',
                'Default' => null,
            ],
            'http_status' => [
                'Name' => 'HTTP status',
                'Type' => 'int',
                'Multiple' => 'N',
                'Default' => null,
            ],
            'error_message' => [
                'Name' => 'Error message',
                'Type' => 'text',
                'Multiple' => 'N',
                'Default' => null,
            ],
        ],
        'FILTER' => $filter,
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
    $message = is_string($context['message'] ?? null) ? $context['message'] : '';
    $details = safe_json_for_html($context['details'] ?? []);

    $finishScript = $success ? <<<HTML
<script src="//api.bitrix24.com/api/v1/"></script>
<script>
var finishButton = document.getElementById('finish-install');
var countdownNode = document.getElementById('finish-countdown');
var secondsLeft = 15;

function updateCountdown() {
    if (countdownNode) {
        countdownNode.textContent = String(secondsLeft);
    }
}

function finishInstall() {
    if (window.BX24 && typeof BX24.installFinish === 'function') {
        BX24.installFinish();
    }
}

if (finishButton) {
    finishButton.addEventListener('click', finishInstall);
}

updateCountdown();
var timer = setInterval(function () {
    secondsLeft -= 1;
    updateCountdown();

    if (secondsLeft <= 0) {
        clearInterval(timer);
        finishInstall();
    }
}, 1000);
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
            --card: #ffffff;
            --text: #17202a;
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
        .actions {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 18px 0 16px;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 10px;
            padding: 10px 16px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        .hint {
            color: #5b6674;
            font-size: 14px;
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
            <div class="actions">
                <button class="button" id="finish-install" type="button">Завершить установку</button>
                <div class="hint">Окно закроется автоматически через <span id="finish-countdown">15</span> сек.</div>
            </div>
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

function render_info_page(string $title, string $message, array $details = [], int $status = 200): never
{
    $detailsJson = safe_json_for_html($details);

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
            --card: #ffffff;
            --text: #17324d;
            --muted: #607086;
            --border: #d9e2ec;
            --accent: #1565c0;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(180deg, #eef5fb 0%, #f8fbff 100%);
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
            margin: 0 0 12px;
            font-size: 28px;
        }
        p {
            margin: 0 0 12px;
        }
        .muted {
            color: var(--muted);
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
            <p>{$message}</p>
            <p class="muted">Для установки используйте <code>install.php</code>, а для настройки activity внутри бизнес-процесса Bitrix24 откроет <code>placement.php</code> автоматически.</p>
            <pre>{$detailsJson}</pre>
        </div>
    </main>
</body>
</html>
HTML;

    html_response($html, $status);
}
