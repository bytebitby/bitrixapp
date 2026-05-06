<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$data = get_request_data();
app_log('INSTALL HIT', [
    'meta' => request_meta(),
    'request' => $data,
]);

$domain = get_portal_domain($data);
$token = get_auth_token($data);
$handlerUrl = app_url('handler.php');
$placementUrl = app_url('placement.php');
$activityCodesToCleanup = array_values(array_unique([
    app_config()['activity_code'],
    app_config()['parse_activity_code'],
    'bytebit_webhook_activity',
    'bytebit_webhook_activity_v2',
    'bytebit_webhook_response_parser_v1',
]));

if ($handlerUrl === null || $placementUrl === null) {
    render_install_page(false, [
        'status' => 200,
        'message' => 'Не удалось определить публичный URL приложения. Укажите APP_BASE_URL в .env или откройте install.php через внешний домен.',
        'details' => [
            'detected_base_url' => app_config()['app_base_url'],
            'expected_handler' => $handlerUrl,
            'expected_placement' => $placementUrl,
        ],
    ]);
}

if ($domain === null || $token === null) {
    render_install_page(false, [
        'status' => 200,
        'message' => 'Bitrix24 не передал данные авторизации. Откройте этот URL как путь первоначальной установки локального приложения.',
        'details' => [
            'domain' => $domain,
            'has_token' => $token !== null,
            'handler_url' => $handlerUrl,
            'placement_url' => $placementUrl,
        ],
    ]);
}

$activityDefinitions = activity_definitions($handlerUrl, $placementUrl);
app_log('INSTALL FIELDS', [
    'activity_codes' => array_keys($activityDefinitions),
    'fields' => $activityDefinitions,
]);

$cleanupResults = [];
foreach ($activityCodesToCleanup as $codeToCleanup) {
    $cleanupResults[$codeToCleanup] = rest_call($domain, $token, 'bizproc.activity.delete', [
        'CODE' => $codeToCleanup,
    ]);
}

app_log('INSTALL CLEANUP RESULTS', [
    'cleanup_results' => $cleanupResults,
]);

$activityResults = [];
foreach ($activityDefinitions as $activityCode => $fields) {
    $activityResults[$activityCode] = rest_call($domain, $token, 'bizproc.activity.add', $fields);
}

$operation = 'delete+add';
$success = true;
foreach ($activityResults as $result) {
    if (!empty($result['error'])) {
        $success = false;
        break;
    }
}

$registeredActivities = rest_call($domain, $token, 'bizproc.activity.list', []);
app_log('INSTALL FINAL STATE', [
    'success' => $success,
    'operation' => $operation,
    'activity_results' => $activityResults,
    'activity_list' => $registeredActivities,
]);

render_install_page($success, [
    'message' => $success
        ? sprintf('Установка выполнена. В Битрикс24 добавлены activity `%s` и `%s`.', app_config()['activity_code'], app_config()['parse_activity_code'])
        : 'Битрикс24 вернул ошибку при регистрации activity.',
    'details' => [
        'operation' => $operation,
        'portal' => $domain,
        'activity_codes' => array_keys($activityDefinitions),
        'handler_url' => $handlerUrl,
        'placement_url' => $placementUrl,
        'cleanup_results' => $cleanupResults,
        'responses' => $activityResults,
        'activity_list' => $registeredActivities,
    ],
]);
