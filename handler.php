<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function send_bizproc_result(?string $domain, ?string $token, mixed $eventToken, array $payload, string $message): ?array
{
    if (!is_string($eventToken) || $eventToken === '' || $domain === null || $token === null) {
        return null;
    }

    return rest_call($domain, $token, 'bizproc.event.send', [
        'event_token' => $eventToken,
        'return_values' => $payload,
        'log_message' => $message,
    ]);
}

function decode_json_object_property(string $raw, string $fieldName, array &$errors): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $errors[] = sprintf('%s должен быть JSON-объектом.', $fieldName);
        return [];
    }

    return $decoded;
}

function build_default_request_body(array $data, ?string $domain, mixed $eventToken): array
{
    return [
        'activity_id' => request_value($data, 'activity_id') ?? request_value($data, 'ACTIVITY_ID'),
        'workflow_id' => request_value($data, 'workflow_id') ?? request_value($data, 'WORKFLOW_ID'),
        'document_id' => request_value($data, 'document_id') ?? request_value($data, 'DOCUMENT_ID'),
        'portal_domain' => $domain,
        'event_token' => $eventToken,
    ];
}

function execute_parser_activity(array $data, ?string $domain, ?string $token, mixed $eventToken): never
{
    $sourceJson = stringify_value(activity_property($data, 'source_json', ''));
    $jsonPath = stringify_value(activity_property($data, 'json_path', ''));
    $defaultValue = stringify_value(activity_property($data, 'default_value', ''));

    $payload = [
        'parsed_value' => '',
        'path_found' => 'N',
        'parse_error' => '',
    ];

    if ($sourceJson === '') {
        $payload['parse_error'] = 'JSON-ответ не заполнен.';
    } elseif ($jsonPath === '') {
        $payload['parse_error'] = 'Путь к параметру не заполнен.';
    } else {
        $decoded = json_decode($sourceJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $payload['parse_error'] = 'Некорректный JSON: ' . json_last_error_msg();
        } else {
            $found = false;
            $value = extract_json_path($decoded, $jsonPath, $found);
            $payload['path_found'] = $found ? 'Y' : 'N';
            $payload['parsed_value'] = $found ? value_for_bizproc($value) : $defaultValue;
        }
    }

    app_log('PARSER RESULT', [
        'json_path' => $jsonPath,
        'path_found' => $payload['path_found'],
        'parse_error' => $payload['parse_error'],
    ]);

    $eventResult = send_bizproc_result(
        $domain,
        $token,
        $eventToken,
        $payload,
        $payload['parse_error'] === '' ? 'JSON-значение извлечено.' : 'Парсинг JSON завершился с ошибкой.'
    );

    json_response([
        'success' => $payload['parse_error'] === '' && ($eventResult === null || empty($eventResult['error'])),
        'event_result' => $eventResult,
        'result' => $payload,
    ], $payload['parse_error'] === '' ? 200 : 400);
}

function execute_webhook_activity(array $data, ?string $domain, ?string $token, mixed $eventToken): never
{
    $webhookUrl = stringify_value(activity_property($data, 'webhook_url', ''));
    $method = strtoupper(stringify_value(activity_property($data, 'http_method', 'POST')));
    if (!in_array($method, ['GET', 'POST'], true)) {
        $method = 'POST';
    }

    $timeout = (int)stringify_value(activity_property($data, 'timeout_seconds', '60'));
    if ($timeout < 1) {
        $timeout = 60;
    }
    if ($timeout > 300) {
        $timeout = 300;
    }

    $requestBodyRaw = stringify_value(activity_property($data, 'request_body', ''));
    $configErrors = [];
    $headers = decode_json_object_property(stringify_value(activity_property($data, 'request_headers', '')), 'Заголовки JSON', $configErrors);

    $resultPayload = [
        'webhook_result' => '',
        'http_status' => 0,
        'error_message' => '',
        'response_headers' => '',
        'duration_seconds' => '0.0000',
    ];

    if ($webhookUrl === '') {
        $configErrors[] = 'Не указан URL вебхука.';
    }

    if ($configErrors !== []) {
        $resultPayload['error_message'] = implode(' ', $configErrors);
        send_bizproc_result($domain, $token, $eventToken, $resultPayload, 'HTTP-запрос не выполнен: ошибка настроек activity.');
        json_response([
            'success' => false,
            'error' => 'CONFIG_ERROR',
            'result' => $resultPayload,
        ], 400);
    }

    $defaultBody = build_default_request_body($data, $domain, $eventToken);
    $requestBody = null;

    if ($method === 'POST') {
        if ($requestBodyRaw === '') {
            $requestBody = json_encode($defaultBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            json_decode($requestBodyRaw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $resultPayload['error_message'] = 'Поле "JSON для отправки" должно содержать корректный JSON: ' . json_last_error_msg();
                send_bizproc_result($domain, $token, $eventToken, $resultPayload, 'HTTP-запрос не выполнен: некорректный JSON.');
                json_response([
                    'success' => false,
                    'error' => 'INVALID_JSON_BODY',
                    'result' => $resultPayload,
                ], 400);
            }
            $requestBody = $requestBodyRaw;
        }
    } elseif ($requestBodyRaw !== '') {
        json_decode($requestBodyRaw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $resultPayload['error_message'] = 'Поле "JSON для отправки" заполнено, но это не корректный JSON: ' . json_last_error_msg();
            send_bizproc_result($domain, $token, $eventToken, $resultPayload, 'HTTP-запрос не выполнен: некорректный JSON.');
            json_response([
                'success' => false,
                'error' => 'INVALID_JSON_BODY',
                'result' => $resultPayload,
            ], 400);
        }
    }

    if (is_string($eventToken) && $eventToken !== '' && $domain !== null && $token !== null) {
        rest_call($domain, $token, 'bizproc.activity.log', [
            'event_token' => $eventToken,
            'log_message' => sprintf('Выполняем внешний HTTP-запрос: %s %s.', $method, $webhookUrl),
        ]);
    }

    $responseHeaders = [];
    $requestHeaders = ['Accept: application/json, text/plain, */*'];
    if (!array_key_exists('Content-Type', $headers) && !array_key_exists('content-type', $headers)) {
        $requestHeaders[] = 'Content-Type: application/json';
    }
    foreach ($headers as $name => $value) {
        if (is_scalar($value)) {
            $requestHeaders[] = trim((string)$name) . ': ' . trim((string)$value);
        }
    }

    $targetUrl = $webhookUrl;
    $startTime = microtime(true);
    $ch = curl_init($targetUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(15, $timeout),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
            $length = strlen($headerLine);
            $headerLine = trim($headerLine);

            if ($headerLine !== '' && str_contains($headerLine, ':')) {
                [$name, $value] = explode(':', $headerLine, 2);
                $responseHeaders[trim($name)] = trim($value);
            }

            return $length;
        },
    ]);

    if ($method === 'POST' && $requestBody !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    }

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $duration = microtime(true) - $startTime;

    $normalizedResponse = is_string($responseBody) ? normalize_webhook_result($responseBody) : '';
    $headerText = $responseHeaders !== []
        ? (string)json_encode($responseHeaders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        : '';
    $isHttpError = $httpStatus >= 400 || $httpStatus === 0;
    $isSuccess = $curlError === '' && !$isHttpError;

    $resultPayload = [
        'webhook_result' => $isSuccess ? $normalizedResponse : '',
        'http_status' => $httpStatus,
        'error_message' => $isSuccess ? '' : trim($curlError !== '' ? $curlError : $normalizedResponse),
        'response_headers' => $headerText,
        'duration_seconds' => number_format($duration, 4, '.', ''),
    ];

    app_log('WEBHOOK RESULT', [
        'url' => $targetUrl,
        'method' => $method,
        'http_status' => $httpStatus,
        'curl_error' => $curlError,
        'duration_seconds' => $resultPayload['duration_seconds'],
        'response_headers' => $responseHeaders,
        'response_preview' => is_string($responseBody) ? substr($responseBody, 0, 1500) : '',
    ]);

    $eventResult = send_bizproc_result(
        $domain,
        $token,
        $eventToken,
        $resultPayload,
        $isSuccess
            ? sprintf('Внешний HTTP-запрос выполнен, HTTP %d, %.4f сек.', $httpStatus, $duration)
            : sprintf('Внешний HTTP-запрос завершился с ошибкой, HTTP %d, %.4f сек.', $httpStatus, $duration)
    );

    json_response([
        'success' => $isSuccess && ($eventResult === null || empty($eventResult['error'])),
        'event_result' => $eventResult,
        'result' => $resultPayload,
    ], $isSuccess ? 200 : 502);
}

$data = get_request_data();
app_log('HANDLER HIT', [
    'meta' => request_meta(),
    'request' => $data,
]);

$domain = get_portal_domain($data);
$token = get_auth_token($data);
$eventToken = request_value($data, 'event_token') ?? request_value($data, 'EVENT_TOKEN');
$activityId = request_value($data, 'activity_id') ?? request_value($data, 'ACTIVITY_ID');
$workflowId = request_value($data, 'workflow_id') ?? request_value($data, 'WORKFLOW_ID');
$properties = request_value($data, 'properties') ?? request_value($data, 'PROPERTIES');

$isBusinessProcessCall = (
    (is_string($eventToken) && $eventToken !== '')
    || $activityId !== null
    || $workflowId !== null
    || $properties !== null
);

if (!$isBusinessProcessCall) {
    render_info_page(
        'Служебный обработчик activity',
        'Эту страницу не нужно открывать вручную. Bitrix24 вызывает handler.php автоматически во время выполнения бизнес-процесса.',
        [
            'handler_url' => app_url('handler.php'),
            'placement_url' => app_url('placement.php'),
            'webhook_activity_code' => app_config()['activity_code'],
            'parser_activity_code' => app_config()['parse_activity_code'],
            'request_keys' => array_keys($data),
        ]
    );
}

$looksLikeParser = activity_property($data, 'source_json') !== null || activity_property($data, 'json_path') !== null;

if ($looksLikeParser) {
    execute_parser_activity($data, $domain, $token, $eventToken);
}

execute_webhook_activity($data, $domain, $token, $eventToken);
