<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$data = get_request_data();
app_log('INSTALL HIT', ['request' => $data]);

$domain = get_portal_domain($data);
$token = get_auth_token($data);
$handlerUrl = app_url('handler.php');
$placementUrl = app_url('placement.php');

if ($handlerUrl === null || $placementUrl === null) {
    render_install_page(false, [
        'status' => 200,
        'message' => 'Не удалось определить публичный URL приложения. Укажите APP_BASE_URL в переменных окружения или откройте install.php через внешний домен.',
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

$fields = activity_fields($handlerUrl, $placementUrl);
$addResult = rest_call($domain, $token, 'bizproc.activity.add', $fields);
$finalResult = $addResult;
$operation = 'add';

if (!empty($addResult['error']) && in_array($addResult['error'], ['ERROR_ACTIVITY_ALREADY_INSTALLED', 'ERROR_ACTIVITY_ADD_FAILURE'], true)) {
    $updateFields = $fields;
    unset($updateFields['CODE']);

    $finalResult = rest_call($domain, $token, 'bizproc.activity.update', [
        'CODE' => app_config()['activity_code'],
        'FIELDS' => $updateFields,
    ]);
    $operation = 'update';
}

$success = empty($finalResult['error']);

render_install_page($success, [
    'message' => $success
        ? sprintf('Операция `%s` выполнена успешно. Activity `%s` готово к использованию в бизнес-процессах.', $operation, app_config()['activity_code'])
        : 'Битрикс24 вернул ошибку при регистрации activity.',
    'details' => [
        'operation' => $operation,
        'portal' => $domain,
        'activity_code' => app_config()['activity_code'],
        'handler_url' => $handlerUrl,
        'placement_url' => $placementUrl,
        'response' => $finalResult,
    ],
]);
