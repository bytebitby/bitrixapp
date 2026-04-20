<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

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
$documentId = request_value($data, 'document_id') ?? request_value($data, 'DOCUMENT_ID');
$webhookUrl = request_value($data, 'properties.webhook_url') ?? request_value($data, 'webhook_url');

$isBusinessProcessCall = (
    (is_string($eventToken) && $eventToken !== '')
    || $activityId !== null
    || $workflowId !== null
    || request_value($data, 'properties') !== null
);

if (!$isBusinessProcessCall) {
    render_info_page(
        'Это служебный обработчик activity',
        'Страница handler.php не предназначена для ручного открытия. Bitrix24 вызывает этот URL автоматически во время выполнения бизнес-процесса.',
        [
            'handler_url' => app_url('handler.php'),
            'placement_url' => app_url('placement.php'),
            'activity_code' => app_config()['activity_code'],
            'request_keys' => array_keys($data),
        ]
    );
}

if (!is_string($webhookUrl) || trim($webhookUrl) === '') {
    $resultPayload = [
        'webhook_result' => '',
        'http_status' => 0,
        'error_message' => 'Не указан URL вебхука в свойствах активности.',
    ];

    if (is_string($eventToken) && $eventToken !== '' && $domain !== null && $token !== null) {
        rest_call($domain, $token, 'bizproc.event.send', [
            'event_token' => $eventToken,
            'return_values' => $resultPayload,
            'log_message' => 'Activity завершилось с ошибкой: URL вебхука не заполнен.',
        ]);
    }

    json_response([
        'success' => false,
        'error' => 'MISSING_WEBHOOK_URL',
        'result' => $resultPayload,
    ], 400);
}

$requestBody = [
    'activity_id' => $activityId,
    'workflow_id' => $workflowId,
    'document_id' => $documentId,
    'portal_domain' => $domain,
    'event_token' => $eventToken,
];

if (is_string($eventToken) && $eventToken !== '' && $domain !== null && $token !== null) {
    rest_call($domain, $token, 'bizproc.activity.log', [
        'event_token' => $eventToken,
        'log_message' => 'Запускаем внешний вебхук из activity приложения.',
    ]);
}

$responseHeaders = [];
$ch = curl_init(trim($webhookUrl));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json, text/plain, */*',
    ],
    CURLOPT_TIMEOUT => 60,
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

$responseBody = curl_exec($ch);
$curlError = curl_error($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$normalizedResponse = is_string($responseBody) ? normalize_webhook_result($responseBody) : '';
$resultPayload = [
    'webhook_result' => $normalizedResponse,
    'http_status' => $httpStatus,
    'error_message' => $curlError !== '' ? $curlError : '',
];

app_log('WEBHOOK RESULT', [
    'url' => $webhookUrl,
    'http_status' => $httpStatus,
    'curl_error' => $curlError,
    'response_headers' => $responseHeaders,
    'response_preview' => is_string($responseBody) ? substr($responseBody, 0, 1500) : '',
]);

if (is_string($eventToken) && $eventToken !== '' && $domain !== null && $token !== null) {
    $eventResult = rest_call($domain, $token, 'bizproc.event.send', [
        'event_token' => $eventToken,
        'return_values' => $resultPayload,
        'log_message' => $curlError === ''
            ? sprintf('Внешний вебхук отработал, HTTP %d.', $httpStatus)
            : 'Внешний вебхук завершился с ошибкой.',
    ]);

    json_response([
        'success' => empty($eventResult['error']),
        'event_result' => $eventResult,
        'result' => $resultPayload,
    ], empty($eventResult['error']) ? 200 : 500);
}

json_response([
    'success' => $curlError === '',
    'result' => $resultPayload,
]);
