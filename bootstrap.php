<?php
declare(strict_types=1);

function app_log(string $message, array $context = []): void {
    $file = '/opt/bp-webhook/app.log';
    $line = sprintf("[%s] %s %s\n", date('c'), $message, $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : '');
    file_put_contents($file, $line, FILE_APPEND);
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_request_data(): array {
    $raw = file_get_contents('php://input');
    $json = [];
    if ($raw) { $decoded = json_decode($raw, true); if (is_array($decoded)) $json = $decoded; }
    return array_replace_recursive($_REQUEST, $json);
}

function request_value(array $data, string $key, mixed $default = null): mixed {
    if (array_key_exists($key, $data)) return $data[$key];
    if (!str_contains($key, '.')) return $default;
    $value = $data;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) return $default;
        $value = $value[$segment];
    }
    return $value;
}

function get_auth_token(array $data): ?string {
    foreach ([request_value($data, 'auth.access_token'), request_value($data, 'AUTH_ID'), request_value($data, 'access_token')] as $t) {
        if (is_string($t) && trim($t) !== '') return trim($t);
    }
    return null;
}

function get_portal_domain(array $data): ?string {
    foreach ([request_value($data, 'auth.domain'), request_value($data, 'DOMAIN'), request_value($data, 'domain')] as $d) {
        if (is_string($d) && trim($d) !== '') return preg_replace('#^https?://#', '', trim($d));
    }
    return null;
}

function rest_call(string $domain, string $token, string $method, array $fields = []): array {
    $url = 'https://' . $domain . '/rest/' . $method . '.json';
    $payload = $fields;
    $payload['auth'] = $token;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($payload), CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    app_log('REST call', ['method' => $method, 'error' => $error, 'response' => substr((string)$response, 0, 500)]);
    if ($error) return ['error' => 'CURL_ERROR', 'error_description' => $error];
    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) return ['error' => 'INVALID_RESPONSE', 'error_description' => (string)$response];
    return $decoded;
}