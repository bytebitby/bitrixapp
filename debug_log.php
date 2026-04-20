<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$data = get_request_data();
app_log('DEBUG LOG HIT', [
    'meta' => request_meta(),
    'request' => $data,
]);

json_response([
    'success' => true,
]);
