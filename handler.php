<?php
declare(strict_types=1);

/**
 * Handler для активности "Универсальный обработчик" в бизнес-процессах Битрикс24
 * Вызывается Битриксом при выполнении этой активности в БП
 */

require __DIR__ . '/bootstrap.php';

$data = get_request_data();
app_log('HANDLER HIT', ['request' => $data]);

$domain = get_portal_domain($data);
$token = get_auth_token($data);

// Параметры от Битрикса
$activityId = request_value($data, 'ACTIVITY_ID');
$workflowId = request_value($data, 'WORKFLOW_ID');
$documentId = request_value($data, 'DOCUMENT_ID');

// Наши свойства активности
$webhookUrl = request_value($data, 'webhook_url');

if (!$webhookUrl) {
    // Возвращаем ошибку в БП
    json_response([
        'success' => false,
        'error' => 'MISSING_WEBHOOK_URL',
        'error_description' => 'Не указан URL вебхука в параметрах активности',
    ], 400);
}

// Вызываем вебхук
app_log('Calling webhook', ['url' => $webhookUrl]);

$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'activity_id' => $activityId,
        'workflow_id' => $workflowId,
        'document_id' => $documentId,
        'portal_domain' => $domain,
    ]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

app_log('Webhook response', ['http_code' => $httpCode, 'error' => $error, 'response' => substr((string)$response, 0, 1000)]);

if ($error) {
    json_response([
        'success' => false,
        'error' => 'WEBHOOK_ERROR',
        'error_description' => $error,
    ], 500);
}

// Возвращаем результат в бизнес-процесс
json_response([
    'success' => true,
    'result' => [
        'webhook_result' => $response,
    ],
    'http_code' => $httpCode,
]);
