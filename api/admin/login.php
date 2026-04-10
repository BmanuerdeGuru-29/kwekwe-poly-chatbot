<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

kwekwe_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    kwekwe_method_not_allowed('POST, OPTIONS');
}

$payload = kwekwe_json_input();
$result = kwekwe_admin_login((string) ($payload['admin_key'] ?? ''));

if (!($result['authenticated'] ?? false)) {
    kwekwe_json_response($result, 401);
}

kwekwe_json_response($result);
