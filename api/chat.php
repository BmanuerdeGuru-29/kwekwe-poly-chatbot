<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

kwekwe_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    kwekwe_method_not_allowed('POST, OPTIONS');
}

$payload = kwekwe_build_chat_response(kwekwe_json_input());
if (isset($payload['error'])) {
    kwekwe_json_response(['detail' => $payload['error']], 400);
}

kwekwe_json_response($payload);
