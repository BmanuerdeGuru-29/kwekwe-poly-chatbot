<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

kwekwe_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    kwekwe_method_not_allowed('POST, OPTIONS');
}

$payload = kwekwe_json_input();
if (trim((string) ($payload['session_id'] ?? '')) === '' || trim((string) ($payload['message_content'] ?? '')) === '') {
    kwekwe_json_response(['detail' => 'session_id and message_content are required.'], 400);
}

$record = kwekwe_record_feedback($payload);
kwekwe_json_response([
    'status' => 'ok',
    'feedback' => $record,
], 201);
