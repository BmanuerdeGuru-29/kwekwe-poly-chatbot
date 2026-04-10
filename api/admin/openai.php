<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

kwekwe_handle_preflight();
kwekwe_require_admin();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    kwekwe_json_response(kwekwe_admin_openai_status());
}

if ($method !== 'POST') {
    kwekwe_method_not_allowed('GET, POST, OPTIONS');
}

$payload = kwekwe_json_input();
$action = strtolower(trim((string) ($payload['action'] ?? 'save'))) ?: 'save';

$result = match ($action) {
    'disable' => kwekwe_admin_disable_openai_fallback(),
    'reset' => kwekwe_admin_reset_openai_key(),
    default => kwekwe_admin_save_openai_key((string) ($payload['api_key'] ?? '')),
};

if (($result['status'] ?? 'ok') !== 'ok') {
    kwekwe_json_response($result, 400);
}

kwekwe_json_response($result);
