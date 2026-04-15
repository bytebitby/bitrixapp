<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

$data = get_request_data();
app_log('INSTALL HIT', ['request' => $data]);

$domain = get_portal_domain($data);
$token = get_auth_token($data);

if (!$domain || !$token) {
    json_response(['success' => false, 'message' => 'No domain or token', 'domain' => $domain, 'has_token' => (bool)$token], 400);
}

$result = rest_call($domain, $token, 'bizproc.activity.add', [
    'CODE' => 'universal_webhook',
    'HANDLER' => 'http://89.169.154.151/handler.php',
    'AUTH_USER_ID' => 1,
    'USE_SUBSCRIPTION' => 'Y',
    'NAME' => ['ru' => 'Универсальный обработчик', 'en' => 'Universal Webhook Handler'],
    'DESCRIPTION' => ['ru' => 'Вызывает вебхук и возвращает результат в переменные БП', 'en' => 'Calls webhook and returns result'],
    'PROPERTIES' => [
        'webhook_url' => [
            'Name' => ['ru' => 'URL вебхука', 'en' => 'Webhook URL'],
            'Type' => 'string',
            'Required' => 'Y',
            'Multiple' => 'N',
        ],
    ],
    'RETURN_PROPERTIES' => [
        'webhook_result' => [
            'Name' => ['ru' => 'Результат', 'en' => 'Result'],
            'Type' => 'string',
            'Multiple' => 'N',
        ],
    ],
]);

json_response(['success' => empty($result['error']), 'portal' => $domain, 'result' => $result]);
PHPEOF
echo "install OK"